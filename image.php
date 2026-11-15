<?php
// Streams a farm photo from uploads/ to a logged-in user. The folder itself is not
// publicly readable (uploads/.htaccess), so this is the only way to view an image.
include('functions/connect.php');
session_start();
require_once __DIR__ . '/functions/image_store.php';

if (empty($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

$imageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$image = $imageId > 0 ? selectOne('images', ['id' => $imageId]) : null;
$file = $image ? uploadsDir() . basename((string) $image['title']) : null;

if (!$image || $image['title'] === '' || !is_file($file)) {
    http_response_code(404);
    exit('Image not found');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!isset(IMAGE_TYPES[$mime])) {
    http_response_code(404);
    exit('Image not found');
}

session_write_close();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="farm-' . (int) $image['farm_id'] . '-' . $imageId . '.' . IMAGE_TYPES[$mime] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($file);
