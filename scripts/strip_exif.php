<?php
// Re-encodes the photos already in uploads/ so they lose their EXIF/XMP metadata
// (GPS position, camera and device details). New uploads are cleaned on save;
// run this once on a server that has photos from before that change.
//
// Usage: php scripts/strip_exif.php            (cleans every image in uploads/)
//        php scripts/strip_exif.php --dry-run  (lists what would change)
//
// File names stay the same, so the images table needs no update. A file whose
// content doesn't match its extension is reported and left alone.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../functions/image_store.php';

$dryRun = in_array('--dry-run', $argv, true);
$cleaned = 0;
$skipped = 0;

foreach (glob(uploadsDir() . '*') as $path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!is_file($path) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        continue;
    }
    $name = basename($path);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $expected = IMAGE_TYPES[$mime] ?? null;
    if ($expected === null || ($expected !== $ext && !($expected === 'jpg' && $ext === 'jpeg'))) {
        echo "skip  $name: content is " . $mime . "\n";
        $skipped++;
        continue;
    }
    if ($dryRun) {
        echo "would clean  $name\n";
        continue;
    }

    $tmpBase = uploadsDir() . '.strip-' . bin2hex(random_bytes(8));
    [$written, $error] = reencodeImage($path, $tmpBase);
    if ($error !== null) {
        echo "skip  $name: $error\n";
        $skipped++;
        continue;
    }
    if (!rename($written, $path)) {
        @unlink($written);
        echo "skip  $name: could not replace the file\n";
        $skipped++;
        continue;
    }
    echo "clean $name\n";
    $cleaned++;
}

echo "\n$cleaned cleaned, $skipped skipped\n";
exit($skipped > 0 ? 1 : 0);
