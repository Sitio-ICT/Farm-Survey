<?php
include("../connect.php");
session_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../image_store.php';
require_post();
require_right('register_farmer');

function imageDeleteFeedback($farmId, $message, $ok)
{
    $key = str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    $_SESSION["feedback"] = $message;
    $_SESSION["Lack_of_intfund_$key"] = "10";
    $location = $farmId > 0 ? "farm_view.php?view=$farmId&" : "index.php?";
    header("Location: ../../{$location}message" . ($ok ? "0" : "1") . "=$key");
    exit();
}

$imageId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$image = $imageId > 0 ? selectOne('images', ['id' => $imageId]) : null;
if (!$image) {
    imageDeleteFeedback(0, "That image was not found.", false);
}
$farmId = (int) $image['farm_id'];

$result = executeQuery("DELETE FROM images WHERE id = ?", [$imageId]);
if (!$result || $result->affected_rows !== 1) {
    error_log("image_delete: delete failed for image $imageId: " . $connection->error);
    imageDeleteFeedback($farmId, "Sorry, the image could not be deleted.", false);
}

// basename() keeps the delete inside uploads/ whatever the stored title says.
$file = uploadsDir() . basename((string) $image['title']);
if ($image['title'] !== '' && is_file($file)) {
    unlink($file);
}

imageDeleteFeedback($farmId, "Image Deleted Successfully", true);
