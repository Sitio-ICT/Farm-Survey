<?php
include("../connect.php");
session_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../image_store.php';
require_post();
require_right('register_farmer');

function imageUploadFeedback($farmId, $message, $ok)
{
    $key = str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    $_SESSION["feedback"] = $message;
    $_SESSION["Lack_of_intfund_$key"] = "10";
    $location = $farmId > 0 ? "farm_view.php?view=$farmId&" : "index.php?";
    header("Location: ../../{$location}message" . ($ok ? "0" : "1") . "=$key");
    exit();
}

$farmId = isset($_POST['farm']) ? (int) $_POST['farm'] : 0;
if ($farmId < 1 || !selectOne('farm', ['id' => $farmId])) {
    imageUploadFeedback(0, "That farm was not found.", false);
}

[$fileName, $error] = storeUploadedImage($_FILES['image'] ?? null);
if ($error !== null) {
    imageUploadFeedback($farmId, $error, false);
}

$stmt = $connection->prepare("INSERT INTO images (title, farm_id) VALUES (?, ?)");
$saved = $stmt && $stmt->bind_param('si', $fileName, $farmId) && $stmt->execute();
if (!$saved) {
    error_log("image_upload: insert failed: " . $connection->error);
    @unlink(uploadsDir() . $fileName);
    imageUploadFeedback($farmId, "Sorry, the image could not be saved.", false);
}

imageUploadFeedback($farmId, "Image successfully uploaded", true);
