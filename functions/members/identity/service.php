<?php
// Decides the outcome of an identity check and records it.
// Rules: the registry name must match what the member declared, the date of birth
// must match, and the selfie must match the registry photo. Clear failures are
// rejected; partial matches go to a staff reviewer; one ID can back only one account.

require_once __DIR__ . '/providers.php';

const ID_TYPES = [
    'nin' => 'National Identification Number (NIN)',
    'drivers_licence' => "Driver's licence",
    'passport' => 'International passport',
    'voters_card' => "Voter's card (PVC)",
];

const ID_REASON_TEXT = [
    'no_record' => 'No record was found for that ID number.',
    'name_mismatch' => 'The name on the ID record does not match the name on your account.',
    'dob_mismatch' => 'The date of birth on the ID record does not match your account.',
    'selfie_low' => 'Your selfie could not be matched confidently to the ID photo.',
    'selfie_no_match' => 'Your selfie does not match the photo on the ID record.',
    'selfie_missing' => 'The ID service did not return a face match.',
    'id_linked_to_other_account' => 'This ID is already linked to another account.',
    'document_review' => 'Your document will be checked by our team.',
    'service_error' => 'The ID service could not be reached.',
];

function id_attempts_today($memberId)
{
    $row = db_one(
        "SELECT COUNT(*) AS n FROM identity_verifications
         WHERE member_id = ? AND outcome <> 'error' AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)",
        [$memberId]
    );
    return (int) $row['n'];
}

/** Another account already holds (or is under review for) this ID. */
function id_in_use_elsewhere($memberId, $idType, $hmac)
{
    return (bool) db_one(
        "SELECT v.id FROM identity_verifications v
         WHERE v.id_type = ? AND v.id_number_hmac = ? AND v.member_id <> ?
           AND (v.outcome = 'verified' OR (v.outcome = 'manual_review' AND v.reviewed_at IS NULL)
                OR v.review_decision = 'approved')
         LIMIT 1",
        [$idType, $hmac, $memberId]
    );
}

function record_verification(array $member, array $row)
{
    executeQuery(
        "INSERT INTO identity_verifications
           (member_id, id_type, id_number_hmac, id_number_last4, provider, provider_ref, outcome,
            name_score, dob_match, selfie_confidence, reasons, selfie_file, document_file, consent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            $member['id'], $row['id_type'], $row['hmac'], $row['last4'], $row['provider'],
            $row['provider_ref'] ?? null, $row['outcome'], $row['name_score'] ?? null,
            isset($row['dob_match']) ? (int) $row['dob_match'] : null, $row['selfie_confidence'] ?? null,
            json_encode($row['reasons'] ?? []), $row['selfie_file'] ?? null, $row['document_file'] ?? null,
        ]
    );
    $status = ['verified' => 'verified', 'manual_review' => 'manual_review', 'rejected' => 'rejected'][$row['outcome']] ?? null;
    if ($status && $member['id_status'] !== 'verified') {
        executeQuery(
            "UPDATE members SET id_status = ?, id_verified_at = IF(? = 'verified', NOW(), NULL) WHERE id = ?",
            [$status, $status, $member['id']]
        );
    }
}

/**
 * Automated NIN + selfie check. Returns ['outcome' => ..., 'reasons' => [...]].
 */
function verify_member_nin(array $member, $nin, $selfieJpeg)
{
    $cfg = appConfig()['identity'] ?? [];
    $hmac = vault_hmac($nin);
    $base = ['id_type' => 'nin', 'hmac' => $hmac, 'last4' => substr($nin, -4)];

    if (id_in_use_elsewhere($member['id'], 'nin', $hmac)) {
        $row = $base + ['provider' => 'manual', 'outcome' => 'manual_review',
            'reasons' => ['id_linked_to_other_account'], 'selfie_file' => vault_store($selfieJpeg)];
        record_verification($member, $row);
        return $row;
    }

    $provider = identity_provider();
    $declared = [
        'first_name' => $member['first_name'], 'middle_name' => $member['middle_name'],
        'last_name' => $member['last_name'], 'date_of_birth' => $member['date_of_birth'],
    ];
    $result = $provider->verifyNin($nin, $selfieJpeg, $declared);
    $row = $base + ['provider' => $provider->name(), 'provider_ref' => $result->reference];

    if ($result->status === 'error') {
        error_log('Identity provider error for member ' . $member['id'] . ': ' . $result->error);
        $row += ['outcome' => 'error', 'reasons' => ['service_error']];
        record_verification($member, $row);
        return $row;
    }
    if ($result->status === 'not_found') {
        $row += ['outcome' => 'rejected', 'reasons' => ['no_record']];
        record_verification($member, $row);
        return $row;
    }

    $nameScore = name_match_score($declared, $result->record);
    $dobMatch = parse_registry_date($result->record['date_of_birth']) === $member['date_of_birth'];
    $selfie = $result->selfieConfidence;
    $selfieMin = (float) ($cfg['selfie_threshold'] ?? 80);
    $nameMin = (float) ($cfg['name_threshold'] ?? 0.85);

    $reasons = [];
    if ($nameScore < $nameMin) {
        $reasons[] = 'name_mismatch';
    }
    if (!$dobMatch) {
        $reasons[] = 'dob_mismatch';
    }
    if ($selfie === null) {
        $reasons[] = 'selfie_missing';
    } elseif ($selfie < 50) {
        $reasons[] = 'selfie_no_match';
    } elseif ($selfie < $selfieMin) {
        $reasons[] = 'selfie_low';
    }

    if (!$reasons) {
        $outcome = 'verified';
    } elseif (in_array('selfie_no_match', $reasons, true) || ($nameScore < 0.5 && !$dobMatch)) {
        $outcome = 'rejected';
    } else {
        $outcome = 'manual_review';
    }

    // Keep the selfie (encrypted) when a person may need to look at it later.
    $selfieFile = $outcome === 'rejected' ? null : vault_store($selfieJpeg);

    $row += [
        'outcome' => $outcome, 'reasons' => $reasons, 'name_score' => $nameScore,
        'dob_match' => $dobMatch, 'selfie_confidence' => $selfie, 'selfie_file' => $selfieFile,
    ];
    record_verification($member, $row);
    return $row;
}

/**
 * Driver's licence, passport or voter's card: document photo + selfie go to staff review.
 */
function submit_member_document(array $member, $idType, $number, $documentJpeg, $selfieJpeg)
{
    $hmac = vault_hmac($number);
    $reasons = ['document_review'];
    if (id_in_use_elsewhere($member['id'], $idType, $hmac)) {
        $reasons[] = 'id_linked_to_other_account';
    }
    $row = [
        'id_type' => $idType, 'hmac' => $hmac, 'last4' => substr($number, -4), 'provider' => 'manual',
        'outcome' => 'manual_review', 'reasons' => $reasons,
        'document_file' => vault_store($documentJpeg), 'selfie_file' => vault_store($selfieJpeg),
    ];
    record_verification($member, $row);
    return $row;
}

/** Normalises an ID number for its type, or returns null if the format is wrong. */
function normalize_id_number($idType, $value)
{
    $v = strtoupper(preg_replace('/[\s\-]/', '', (string) $value));
    $patterns = [
        'nin' => '/^\d{11}$/',
        'drivers_licence' => '/^[A-Z]{3}[A-Z0-9]{6,12}$/',
        'passport' => '/^[A-Z]\d{8}$/',
        'voters_card' => '/^[A-Z0-9]{19}$/',
    ];
    return isset($patterns[$idType]) && preg_match($patterns[$idType], $v) ? $v : null;
}
