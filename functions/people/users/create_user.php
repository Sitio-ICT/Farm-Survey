<?php
include("../../connect.php");
require_once __DIR__ . '/../../mail.php';
require_once __DIR__ . '/../../password_reset.php';
session_start();
$digits = 7;
$randms = str_pad(rand(0, pow(10, $digits) - 1), $digits, '0', STR_PAD_LEFT);

if(isset($_POST['email']) && isset($_POST['firstname'])){
    // initiialize post data
    // username future addition
    // $username =  $_POST['username'];
    // $findUsername = selectAll('users', ['username' => $username]);
    // if($findUsername){
    //     $_SESSION["feedback"] = "Username Already Exists!";
    //     $_SESSION["Lack_of_intfund_$randms"] = "10";
    //     echo header("Location: ../../../users.php?message1=$randms");
    //     exit();
    // }
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $lastname =  $_POST['lastname'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $findEmail = selectAll('users', ['email' => $email]);
    if($findEmail){
        $_SESSION["feedback"] = "Email is in use by another user!";
        $_SESSION["Lack_of_intfund_$randms"] = "10";
        echo header("Location: ../../../users.php?message1=$randms");
        exit();
    }
    $designation = $_POST['designation'];

    // Random one-time password; the user must replace it on first login.
    $tempPassword = bin2hex(random_bytes(6));
    $passkey = password_hash($tempPassword, PASSWORD_DEFAULT);

    $userData = [
        'firstname' => $firstname,
        'middlename' => $middlename,
        'lastname' => $lastname,
        'passkey' => $passkey,
        'email' => $email,
        'phone'=> $phone,
        // 'status' => "Activated",
        'designation_id' => $designation,
        'must_change_password' => 1
    ];
    $createUser =  insert('users', $userData);
    if (!$createUser) {
        $error = "Error: \n" . mysqli_error($connection); //checking for errors
        $_SESSION["feedback"] = "Sorry could not create User! - $error";
        $_SESSION["Lack_of_intfund_$randms"] = "10";
        echo header("Location: ../../../users.php?message1=$randms");
        exit();
    } else {
        // Email a 48-hour link so the user can set their own password directly.
        $token = createResetToken($createUser, 48);
        $name = htmlspecialchars($firstname);
        $link = resetLink($token);
        $sent = sendMail(
            $email,
            "Your Farm Survey account",
            "<p>Hello $name,</p><p>An account has been created for you on Farm Survey.</p>"
                . "<p><a href=\"$link\">Set your password</a> (this link expires in 48 hours).</p>"
                . "<p>If you were given a temporary password instead, you will be asked to change it when you first log in.</p>"
        );
        $_SESSION["feedback"] = "User successfully created. Temporary password (shown once): $tempPassword. "
            . ($sent ? "A set-password link was emailed to $email." : "The invite email could not be sent, so share the temporary password securely.")
            . " The user must change it on first login.";
        $_SESSION["Lack_of_intfund_$randms"] = "10";
        echo header("Location: ../../../users.php?message0=$randms");
        exit();
    }
}