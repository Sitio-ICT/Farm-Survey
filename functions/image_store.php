<?php
// Safe storage for farm photos.
// An upload is accepted only if it really decodes as a JPEG, PNG or WebP image. It is
// then re-encoded with GD, which drops all EXIF/XMP metadata (GPS position, camera,
// device serials), and saved under a random name. Files are never linked directly:
// image.php streams them to logged-in users.

require_once __DIR__ . '/app_config.php';

const IMAGE_MAX_BYTES = 10 * 1024 * 1024;
const IMAGE_MAX_PIXELS = 40000000;
const IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

/**
 * Photo storage folder, with a trailing separator: 'uploads_dir' from config.php, or
 * the project's uploads/ folder when that is empty.
 */
function uploadsDir()
{
    $dir = trim((string) (appConfig()['uploads_dir'] ?? ''));
    if ($dir === '') {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    }
    return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
}

/**
 * Validate an entry of $_FILES and store a clean copy in uploads/.
 * Returns [stored file name, null] on success or [null, user-facing error].
 */
function storeUploadedImage($file)
{
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        return [null, "Please select an image."];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, "Please select an image."];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, "The image is too large. The limit is 10 MB."];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, "The upload failed. Please try again."];
    }
    if (filesize($file['tmp_name']) > IMAGE_MAX_BYTES) {
        return [null, "The image is too large. The limit is 10 MB."];
    }

    $name = bin2hex(random_bytes(16));
    [$stored, $error] = reencodeImage($file['tmp_name'], uploadsDir() . $name);
    if ($error !== null) {
        return [null, $error];
    }
    return [basename($stored), null];
}

/**
 * Decode $source and write a metadata-free copy to $targetBase plus the extension for
 * its real type. Returns [written path, null] or [null, error].
 */
function reencodeImage($source, $targetBase)
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
    if (!isset(IMAGE_TYPES[$mime])) {
        return [null, "Only JPEG, PNG and WebP images can be uploaded."];
    }

    $info = @getimagesize($source);
    if ($info === false || $info['mime'] !== $mime) {
        return [null, "The file is not a valid image."];
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > IMAGE_MAX_PIXELS) {
        return [null, "The image dimensions are too large."];
    }

    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source);
            break;
        default:
            $image = @imagecreatefromwebp($source);
    }
    if (!$image) {
        return [null, "The file is not a valid image."];
    }

    // Phones store rotation as an EXIF flag. Apply it before the flag is dropped.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($source);
        $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        }
    }

    $target = $targetBase . '.' . IMAGE_TYPES[$mime];
    switch ($mime) {
        case 'image/jpeg':
            $ok = imagejpeg($image, $target, 85);
            break;
        case 'image/png':
            imagesavealpha($image, true);
            $ok = imagepng($image, $target, 6);
            break;
        default:
            imagesavealpha($image, true);
            $ok = imagewebp($image, $target, 85);
    }
    imagedestroy($image);

    if (!$ok) {
        @unlink($target);
        return [null, "The image could not be saved."];
    }
    return [$target, null];
}
