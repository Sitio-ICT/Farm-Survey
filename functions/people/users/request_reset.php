<?php
include("../../connect.php");
require_once __DIR__ . '/../../mail.php';
require_once __DIR__ . '/../../password_reset.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email'])) {
    $email = trim($_POST['email']);
    $user = selectOne('users', ['email' => $email]);
    if ($user) {
        $token = createResetToken($user['id'], 1);
        $name = htmlspecialchars($user['firstname']);
        $link = resetLink($token);
        sendMail(
            $user['email'],
            "Reset your Farm Survey password",
            "<p>Hello $name,</p><p>We received a request to reset your Farm Survey password.</p>"
                . "<p><a href=\"$link\">Choose a new password</a> (this link expires in 1 hour and works once).</p>"
                . "<p>If you did not ask for this, you can ignore this email.</p>"
        );
    }
}

// Same response whether or not the email exists, so the form cannot be used to find accounts.
header("Location: ../../../forgot_password.php?sent=1");
exit;
