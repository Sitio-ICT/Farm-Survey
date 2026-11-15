<?php
require __DIR__ . '/../functions/members/bootstrap.php';
require __DIR__ . '/_layout.php';

$member = require_member(false);
if (!empty($member['phone_verified_at'])) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        if (rate_limited('otp_send', $member['id'], 4, 3600)) {
            $error = 'You have asked for several codes. Wait an hour before requesting another.';
        } elseif (rate_limited('otp_send', $member['id'], 1, 60)) {
            $error = 'Please wait a minute before asking for a new code.';
        } else {
            rate_hit('otp_send', $member['id']);
            $code = issue_phone_otp($member['id']);
            sms_send($member['phone'], "Your Farm Survey verification code is $code. It expires in " . OTP_TTL_MINUTES . " minutes. Do not share it.");
            if (is_dev() && (appConfig()['sms']['driver'] ?? 'log') === 'log') {
                flash('dev', 'SMS is not configured, so here is the code: <strong>' . e($code) . '</strong>');
            }
            flash('success', 'A new code is on its way.');
            redirect('verify_phone.php');
        }
    } else {
        switch (check_phone_otp($member['id'], $_POST['code'] ?? '')) {
            case 'ok':
                executeQuery("UPDATE members SET phone_verified_at = NOW() WHERE id = ?", [$member['id']]);
                flash('success', 'Phone number confirmed.');
                redirect('index.php');
                break;
            case 'wrong':
                $error = 'That code is not right. Check the SMS and try again.';
                break;
            case 'locked':
                $error = 'Too many wrong codes. Request a new code.';
                break;
            default:
                $error = 'That code has expired. Request a new code.';
        }
    }
}

$masked = substr($member['phone'], 0, 7) . ' ••• ' . substr($member['phone'], -3);
layout_start('Confirm your phone', $member);
progress_steps($member, 'phone');
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h4 text-gray-900">Confirm your phone</h1>
                <p class="text-gray-600">We sent a 6-digit code to <strong><?= e($masked) ?></strong>. It expires in <?= OTP_TTL_MINUTES ?> minutes.</p>
                <?php if ($error) { ?><div class="alert alert-danger"><?= e($error) ?></div><?php } ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="code" class="sr-only">Verification code</label>
                        <input id="code" name="code" class="form-control form-control-lg text-center otp-input" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" placeholder="••••••" required autofocus>
                    </div>
                    <button class="btn btn-success btn-block">Confirm</button>
                </form>
                <form method="post" class="text-center mt-3">
                    <?= csrf_field() ?>
                    <button name="resend" value="1" class="btn btn-link btn-sm">Didn't get it? Send a new code</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
layout_end();
