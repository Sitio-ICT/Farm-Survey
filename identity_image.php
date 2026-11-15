<?php
// Streams a decrypted selfie or ID image to staff with the approval right.
// Every request is written to vault_access_log.
session_start();
require_once __DIR__ . '/functions/connect.php';
require_once __DIR__ . '/functions/auth.php';
require_once __DIR__ . '/functions/members/common.php';
require_once __DIR__ . '/functions/members/crypto.php';

require_right('approval');

$id = (int) ($_GET['id'] ?? 0);
$item = $_GET['item'] ?? '';
if (!in_array($item, ['selfie', 'document'], true)) {
    http_response_code(400);
    exit;
}
$row = db_one("SELECT selfie_file, document_file FROM identity_verifications WHERE id = ?", [$id]);
$file = $row[$item === 'selfie' ? 'selfie_file' : 'document_file'] ?? null;
if (!$file) {
    http_response_code(404);
    exit;
}

executeQuery(
    "INSERT INTO vault_access_log (user_id, verification_id, item, purpose, ip) VALUES (?, ?, ?, 'identity_review', ?)",
    [$_SESSION['userid'], $id, $item, $_SERVER['REMOTE_ADDR'] ?? null]
);

try {
    $bytes = vault_read($file);
} catch (Throwable $e) {
    error_log('identity_image: ' . $e->getMessage());
    http_response_code(500);
    exit;
}
header('Content-Type: image/jpeg');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
