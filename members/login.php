<?php
require __DIR__ . '/../functions/members/bootstrap.php';
require __DIR__ . '/_layout.php';

if (current_member()) {
    redirect('index.php');
}

$email = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (rate_limited('login_ip', client_ip(), 20, 900) || rate_limited('login_email', $email, 8, 900)) {
        $error = 'Too many login attempts. Wait 15 minutes and try again.';
    } else {
        $member = $email === '' ? null : db_one("SELECT * FROM members WHERE email = ?", [$email]);
        // Hash even when the account doesn't exist, so response time doesn't reveal it.
        $ok = password_verify($password, $member['password_hash'] ?? password_hash(random_bytes(16), PASSWORD_DEFAULT));
        if ($member && $ok) {
            if (password_needs_rehash($member['password_hash'], PASSWORD_DEFAULT)) {
                executeQuery("UPDATE members SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $member['id']]);
            }
            member_login($member);
            redirect(empty($member['phone_verified_at']) ? 'verify_phone.php' : 'index.php');
        }
        rate_hit('login_ip', client_ip());
        rate_hit('login_email', $email);
        $error = 'That email and password do not match an account.';
    }
}

layout_start('Log in');
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 text-gray-900 mb-4">Log in</h1>
                <?php if ($error) { ?><div class="alert alert-danger"><?= e($error) ?></div><?php } ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" class="form-control" value="<?= e($email) ?>" autocomplete="email" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-success btn-block">Log in</button>
                </form>
                <p class="text-center small mt-3 mb-0">New here? <a href="register.php">Create an account</a></p>
            </div>
        </div>
    </div>
</div>
<?php
layout_end();
