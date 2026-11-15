<?php
// Deletes encrypted selfies and ID images 90 days after a verification was decided,
// as promised in members/privacy.php. Cases still waiting for review are kept.
// Run daily from cron:  php scripts/purge_identity_files.php
if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
require __DIR__ . '/../functions/connect.php';
require __DIR__ . '/../functions/members/common.php';
require __DIR__ . '/../functions/members/crypto.php';

const RETENTION_DAYS = 90;

$rows = db_all(
    "SELECT id, selfie_file, document_file FROM identity_verifications
     WHERE (selfie_file IS NOT NULL OR document_file IS NOT NULL)
       AND NOT (outcome = 'manual_review' AND reviewed_at IS NULL)
       AND COALESCE(reviewed_at, created_at) < DATE_SUB(NOW(), INTERVAL ? DAY)",
    [RETENTION_DAYS]
);
$dir = vault_dir();
$removed = 0;
foreach ($rows as $r) {
    foreach (['selfie_file', 'document_file'] as $col) {
        if ($r[$col] && preg_match('/^[a-f0-9]{40}\.bin$/', $r[$col]) && is_file("$dir/{$r[$col]}")) {
            unlink("$dir/{$r[$col]}");
            $removed++;
        }
    }
    executeQuery("UPDATE identity_verifications SET selfie_file = NULL, document_file = NULL WHERE id = ?", [$r['id']]);
}
echo "Checked " . count($rows) . " verifications, deleted $removed files.\n";
