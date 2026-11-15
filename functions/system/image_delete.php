<?php
include("../connect.php");
session_start();
require_once __DIR__ . '/../auth.php';
require_login();
$digits = 7;
$randms = str_pad(rand(0, pow(10, $digits) - 1), $digits, '0', STR_PAD_LEFT);


if (isset($_POST) && !empty($_POST['id']) && !empty($_POST['client_id'])) {
    $image_id = (int) $_POST['id'];
    $client_id = (int) $_POST['client_id'];

    // Delete image file from server (basename() keeps the delete inside uploads/)
    $image = selectOne('images', ['idimages' => $image_id]);
    if ($image && $image['image_name'] !== '') {
        $file = __DIR__ . '/../../uploads/' . basename($image['image_name']);
        if (is_file($file)) {
            unlink($file);
        }
    }

    // Delete image upload record from database
    $result = executeQuery("DELETE FROM images WHERE idimages = ?", [$image_id]);


    if (!$result) {
        $error = "Error: \n" . mysqli_error($connection); //checking for errors
        $_SESSION["feedback"] = "Sorry could not delete image! - $error";
        $_SESSION["Lack_of_intfund_$randms"] = "10";
        echo header("Location: ../../client_view.php?view=$client_id&message1=$randms");
        exit();
    } else {
        $_SESSION["feedback"] = "Image Deleted Successfully";
        $_SESSION["Lack_of_intfund_$randms"] = "10";
        echo header("Location: ../../client_view.php?view=$client_id&message0=$randms");
        exit();
    }
} else {
    $_SESSION["feedback"] = "Please Select Image";
    $_SESSION["Lack_of_intfund_$randms"] = "10";
    echo header("Location: ../../client_view.php?view=$client_id&message1=$randms");
    exit();
}
