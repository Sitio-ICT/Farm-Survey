<?php
// Phone OTPs and email-verification links for members. Only SHA-256 hashes are stored.

const OTP_TTL_MINUTES = 10;
const OTP_MAX_ATTEMPTS = 5;
const EMAIL_TOKEN_TTL_HOURS = 48;

/** Retires earlier codes of the same purpose and returns a fresh 6-digit code. */
function issue_phone_otp($memberId)
{
    executeQuery(
        "UPDATE member_tokens SET used_at = NOW() WHERE member_id = ? AND purpose = 'phone_otp' AND used_at IS NULL",
        [$memberId]
    );
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    executeQuery(
        "INSERT INTO member_tokens (member_id, purpose, token_hash, expires_at)
         VALUES (?, 'phone_otp', ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))",
        [$memberId, hash('sha256', $memberId . '|' . $code), OTP_TTL_MINUTES]
    );
    return $code;
}

/**
 * Checks a code. Returns 'ok', 'wrong', 'expired' (no live code) or 'locked' (too many tries).
 */
function check_phone_otp($memberId, $code)
{
    $row = db_one(
        "SELECT * FROM member_tokens
         WHERE member_id = ? AND purpose = 'phone_otp' AND used_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1",
        [$memberId]
    );
    if (!$row) {
        return 'expired';
    }
    if ((int) $row['attempts'] >= OTP_MAX_ATTEMPTS) {
        return 'locked';
    }
    $code = preg_replace('/\D/', '', (string) $code);
    if (!hash_equals($row['token_hash'], hash('sha256', $memberId . '|' . $code))) {
        executeQuery("UPDATE member_tokens SET attempts = attempts + 1 WHERE id = ?", [$row['id']]);
        return (int) $row['attempts'] + 1 >= OTP_MAX_ATTEMPTS ? 'locked' : 'wrong';
    }
    executeQuery("UPDATE member_tokens SET used_at = NOW() WHERE id = ?", [$row['id']]);
    return 'ok';
}

function issue_email_token($memberId)
{
    executeQuery(
        "UPDATE member_tokens SET used_at = NOW() WHERE member_id = ? AND purpose = 'email_verify' AND used_at IS NULL",
        [$memberId]
    );
    $token = bin2hex(random_bytes(32));
    executeQuery(
        "INSERT INTO member_tokens (member_id, purpose, token_hash, expires_at)
         VALUES (?, 'email_verify', ?, DATE_ADD(NOW(), INTERVAL ? HOUR))",
        [$memberId, hash('sha256', $token), EMAIL_TOKEN_TTL_HOURS]
    );
    return $token;
}

/** Returns the member id the link belongs to and consumes it, or null. */
function consume_email_token($token)
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $row = db_one(
        "SELECT * FROM member_tokens
         WHERE token_hash = ? AND purpose = 'email_verify' AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
        [hash('sha256', $token)]
    );
    if (!$row) {
        return null;
    }
    executeQuery("UPDATE member_tokens SET used_at = NOW() WHERE id = ?", [$row['id']]);
    return (int) $row['member_id'];
}

/**
 * Emails a verification link. Returns the link when mail is not configured and the
 * app runs in development (so the page can show it), otherwise null.
 */
function send_email_verification(array $member)
{
    require_once __DIR__ . '/../mail.php';
    $link = app_url('members/verify_email.php?token=' . issue_email_token($member['id']));
    $html = '<p>Hello ' . e($member['first_name']) . ',</p>'
        . '<p>Confirm your email address for your Farm Survey account:</p>'
        . '<p><a href="' . e($link) . '">Verify my email</a></p>'
        . '<p>The link works once and expires in ' . EMAIL_TOKEN_TTL_HOURS . ' hours. '
        . 'If you did not create an account, ignore this email.</p>';
    $sent = sendMail($member['email'], 'Verify your email address', $html);
    return (!$sent && is_dev()) ? $link : null;
}
