<?php
// Password reset / invite tokens. Requires functions/connect.php and functions/mail.php.
// Only a SHA-256 hash of each token is stored, so a database leak does not expose usable links.

/**
 * Create a single-use token for $userId that expires after $ttlHours. Returns the raw token.
 */
function createResetToken($userId, $ttlHours = 1)
{
    $token = bin2hex(random_bytes(32));
    executeQuery(
        "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))",
        [$userId, hash('sha256', $token), (int) $ttlHours]
    );
    return $token;
}

/**
 * Return the unused, unexpired reset row for $token, or null.
 */
function findValidReset($token)
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = executeQuery(
        "SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
        [hash('sha256', $token)]
    );
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

/**
 * Mark every outstanding token for the user as used, so a reset link works once.
 */
function consumeResets($userId)
{
    executeQuery("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL", [$userId]);
}

function resetLink($token)
{
    return rtrim(appConfig()['app_url'], '/') . '/reset_password.php?token=' . $token;
}

/**
 * Password policy shared by every form that sets a password.
 */
function passwordPolicyError($password, $confirm)
{
    if ($password !== $confirm) {
        return "The two passwords do not match.";
    }
    if (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/', $password)) {
        return "Password must be at least 8 characters and include an uppercase letter, a lowercase letter and a number.";
    }
    return null;
}
