<?php
require __DIR__ . '/../functions/members/bootstrap.php';
require __DIR__ . '/_layout.php';
require __DIR__ . '/../functions/members/identity/service.php';

$member = require_member();
if ($member['id_status'] === 'verified' || $member['id_status'] === 'manual_review') {
    redirect('index.php');
}

$maxPerDay = (int) (appConfig()['identity']['max_attempts_per_day'] ?? 3);
$idType = (string) ($_POST['id_type'] ?? 'nin');
$errors = [];

/** Image bytes from a data URL field (camera / resized in browser) or a plain file upload. */
function posted_image($dataField, $fileField)
{
    $data = (string) ($_POST[$dataField] ?? '');
    if (preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $data, $m)) {
        return base64_decode($m[2], true) ?: null;
    }
    if (!empty($_FILES[$fileField]['tmp_name']) && is_uploaded_file($_FILES[$fileField]['tmp_name'])) {
        return file_get_contents($_FILES[$fileField]['tmp_name']);
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!array_key_exists($idType, ID_TYPES)) {
        $errors['id_type'] = 'Choose an ID type.';
    }
    $number = normalize_id_number($idType, $_POST['id_number'] ?? '');
    if (!$number) {
        $errors['id_number'] = [
            'nin' => 'A NIN is 11 digits.',
            'drivers_licence' => "Enter the licence number as printed, e.g. ABC12345AA12.",
            'passport' => 'A Nigerian passport number is a letter followed by 8 digits, e.g. A12345678.',
            'voters_card' => 'The VIN on a voter\'s card is 19 letters and numbers.',
        ][$idType] ?? 'Enter the ID number.';
    }
    $selfie = sanitize_image(posted_image('selfie_data', 'selfie_file'), 800);
    if (!$selfie) {
        $errors['selfie'] = 'Take a clear selfie (JPEG or PNG, at least 200 × 200 pixels).';
    }
    $document = null;
    if ($idType !== 'nin') {
        $document = sanitize_image(posted_image('document_data', 'document_file'), 1600);
        if (!$document) {
            $errors['document'] = 'Upload a clear photo of the front of your ID.';
        }
    }
    if (empty($_POST['consent'])) {
        $errors['consent'] = 'We need your consent to check your ID.';
    }
    if (id_attempts_today($member['id']) >= $maxPerDay) {
        $errors['form'] = "You have reached $maxPerDay identity checks in 24 hours. Try again tomorrow, or contact support.";
    }

    if (!$errors) {
        try {
            $result = $idType === 'nin'
                ? verify_member_nin($member, $number, $selfie)
                : submit_member_document($member, $idType, $number, $document, $selfie);
        } catch (Throwable $ex) {
            error_log('Identity check failed: ' . $ex->getMessage());
            $result = ['outcome' => 'error', 'reasons' => ['service_error']];
        }
        switch ($result['outcome']) {
            case 'verified':
                flash('success', 'Your identity is verified.');
                redirect('index.php');
                break;
            case 'manual_review':
                flash('success', 'Thanks. Your ID has been sent to our team for a check, usually within one working day.');
                redirect('index.php');
                break;
            case 'rejected':
                $errors['form'] = implode(' ', array_map(function ($r) {
                    return ID_REASON_TEXT[$r] ?? $r;
                }, $result['reasons'])) . ' Check your details and try again.';
                break;
            default:
                $errors['form'] = 'We could not reach the ID service just now. Please try again in a few minutes; this attempt was not counted.';
        }
    }
}

$left = max(0, $maxPerDay - id_attempts_today($member['id']));
layout_start('Verify your identity', $member);
progress_steps($member, 'identity');
?>
<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 text-gray-900 mb-1">Verify your identity</h1>
                <p class="text-gray-600">
                    We check your ID against the official record and match your selfie to the ID photo.
                    Your name and date of birth must match your account:
                    <strong><?= e(trim($member['first_name'] . ' ' . ($member['middle_name'] ?? '') . ' ' . $member['last_name'])) ?></strong>,
                    born <strong><?= e(date('j M Y', strtotime($member['date_of_birth']))) ?></strong>.
                </p>

                <?php if (isset($errors['form'])) { ?>
                    <div class="alert alert-danger"><?= e($errors['form']) ?></div>
                <?php } elseif ($errors) { ?>
                    <div class="alert alert-danger">Please fix the highlighted fields.</div>
                <?php } ?>

                <form method="post" enctype="multipart/form-data" id="idForm" novalidate>
                    <?= csrf_field() ?>

                    <h2 class="members-section">1. Choose your ID</h2>
                    <div class="id-type-grid mb-2">
                        <?php foreach (ID_TYPES as $key => $label) { ?>
                            <label class="id-type<?= $idType === $key ? ' selected' : '' ?>">
                                <input type="radio" name="id_type" value="<?= e($key) ?>" <?= $idType === $key ? 'checked' : '' ?>>
                                <span><?= e($label) ?></span>
                                <?php if ($key === 'nin') { ?><small class="text-success">Instant</small><?php } else { ?><small class="text-gray-500">Checked by our team</small><?php } ?>
                            </label>
                        <?php } ?>
                    </div>
                    <?= field_error($errors, 'id_type') ?>

                    <div class="form-group mt-3">
                        <label for="id_number" id="id_number_label">ID number</label>
                        <input id="id_number" name="id_number" class="form-control<?= invalid($errors, 'id_number') ?>" value="<?= e($_POST['id_number'] ?? '') ?>" autocomplete="off" required>
                        <small class="form-text text-gray-500" id="id_number_help"></small>
                        <?= field_error($errors, 'id_number') ?>
                    </div>

                    <div class="doc-only"<?= $idType === 'nin' ? ' hidden' : '' ?>>
                        <h2 class="members-section">2. Photo of the front of your ID</h2>
                        <div class="form-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input<?= invalid($errors, 'document') ?>" id="document_file" name="document_file" accept="image/*" capture="environment">
                                <label class="custom-file-label" for="document_file">Take or choose a photo…</label>
                            </div>
                            <input type="hidden" name="document_data" id="document_data">
                            <img id="document_preview" class="doc-preview mt-2" alt="" hidden>
                            <small class="form-text text-gray-500">Lay the card flat in good light. All four corners and the photo must be visible.</small>
                            <?= field_error($errors, 'document') ?>
                        </div>
                    </div>

                    <h2 class="members-section"><span class="step-num-selfie">2</span>. Take a selfie</h2>
                    <div class="selfie-box<?= isset($errors['selfie']) ? ' is-invalid' : '' ?>">
                        <div class="selfie-stage">
                            <video id="selfie_video" playsinline muted hidden></video>
                            <img id="selfie_preview" alt="Your selfie" hidden>
                            <div id="selfie_placeholder" class="selfie-placeholder">
                                <i class="fas fa-user-circle"></i>
                                <span>Face the camera in good light. Remove glasses and caps.</span>
                            </div>
                            <div class="selfie-oval" id="selfie_oval" hidden></div>
                        </div>
                        <div class="selfie-actions">
                            <button type="button" class="btn btn-outline-success" id="selfie_start"><i class="fas fa-camera mr-1"></i> Open camera</button>
                            <button type="button" class="btn btn-success" id="selfie_capture" hidden><i class="fas fa-circle mr-1"></i> Take photo</button>
                            <button type="button" class="btn btn-outline-secondary" id="selfie_retake" hidden>Retake</button>
                            <label class="btn btn-link mb-0" id="selfie_upload_label">
                                or upload a photo
                                <input type="file" id="selfie_file" name="selfie_file" accept="image/*" capture="user" hidden>
                            </label>
                        </div>
                        <input type="hidden" name="selfie_data" id="selfie_data">
                    </div>
                    <?= field_error($errors, 'selfie') ?>

                    <div class="custom-control custom-checkbox mt-4">
                        <input type="checkbox" class="custom-control-input<?= invalid($errors, 'consent') ?>" id="consent" name="consent" value="1" <?= !empty($_POST['consent']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="consent">
                            I consent to Farm Survey checking this ID and my selfie with a licensed identity-verification
                            provider and the national identity record, for the sole purpose of verifying my account.
                        </label>
                        <?= field_error($errors, 'consent') ?>
                    </div>

                    <button class="btn btn-success btn-lg btn-block mt-4" id="submit_id">Verify my identity</button>
                    <p class="text-center small text-gray-500 mt-2 mb-0"><?= $left ?> of <?= $maxPerDay ?> attempts left today.</p>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
layout_end(['assets/verify_id.js']);
