<?php
include('functions/connect.php');
require_once __DIR__ . '/functions/mail.php';
require_once __DIR__ . '/functions/password_reset.php';

$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';
$reset = findValidReset($token);
$error = '';

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
    $error = passwordPolicyError($password, $confirm);
    if (!$error) {
        update('users', $reset['user_id'], 'id', [
            'passkey' => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);
        consumeResets($reset['user_id']);
        header("location: login.php?reset=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Farm Survey - Set Password</title>

    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-primary">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-12 col-md-9">
                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <div class="row">
                            <div class="col-lg-6 d-none d-lg-block bg-password-image"></div>
                            <div class="col-lg-6">
                                <div class="p-5">
                                    <div class="text-center">
                                        <h1 class="h4 text-gray-900 mb-4">Set Your Password</h1>
                                    </div>
                                    <?php if (!$reset) { ?>
                                        <div class="alert alert-danger small">
                                            This link is invalid, has expired or has already been used.
                                        </div>
                                        <div class="text-center">
                                            <a class="small" href="forgot_password.php">Request a new link</a>
                                        </div>
                                    <?php } else { ?>
                                        <?php if ($error) { ?>
                                            <div class="alert alert-danger small"><?php echo htmlspecialchars($error) ?></div>
                                        <?php } ?>
                                        <form class="user" method="POST" action="reset_password.php" autocomplete="off">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token) ?>">
                                            <div class="form-group">
                                                <input type="password" class="form-control form-control-user" name="password"
                                                    pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                                    title="At least 8 characters with an uppercase letter, a lowercase letter and a number"
                                                    placeholder="New Password" required>
                                            </div>
                                            <div class="form-group">
                                                <input type="password" class="form-control form-control-user" name="confirm"
                                                    placeholder="Confirm New Password" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-user btn-block">Set Password</button>
                                        </form>
                                    <?php } ?>
                                    <hr>
                                    <div class="text-center">
                                        <a class="small" href="login.php">Back to Login</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>

</body>

</html>
