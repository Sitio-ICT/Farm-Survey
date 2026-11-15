<?php
require __DIR__ . '/../functions/members/bootstrap.php';
require __DIR__ . '/_layout.php';

if (current_member()) {
    redirect('index.php');
}

$states = db_all("SELECT name, simple_name FROM states ORDER BY name");
$lgas = [];
foreach (db_all("SELECT state_id, local_name FROM locals ORDER BY local_name") as $row) {
    $lgas[$row['state_id']][] = $row['local_name'];
}
$stateNames = array_column($states, 'name');

$in = [
    'role' => 'collector', 'first_name' => '', 'middle_name' => '', 'last_name' => '', 'date_of_birth' => '',
    'email' => '', 'phone' => '', 'state' => '', 'lga' => '', 'organisation' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($in as $k => $_) {
        $in[$k] = trim((string) ($_POST[$k] ?? ''));
    }

    if (rate_limited('register', client_ip(), 5, 3600)) {
        $errors['form'] = 'Too many sign-ups from this network. Please try again in an hour.';
    }

    if (!in_array($in['role'], ['collector', 'buyer'], true)) {
        $errors['role'] = 'Choose how you will use the platform.';
    }
    foreach (['first_name' => 'First name', 'last_name' => 'Surname'] as $k => $label) {
        if (clean_name($in[$k]) === null) {
            $errors[$k] = "$label is required and may contain only letters, spaces, hyphens and apostrophes.";
        }
    }
    if ($in['middle_name'] !== '' && clean_name($in['middle_name']) === null) {
        $errors['middle_name'] = 'Middle name may contain only letters, spaces, hyphens and apostrophes.';
    }
    $dob = DateTime::createFromFormat('!Y-m-d', $in['date_of_birth']);
    if (!$dob || $dob->format('Y-m-d') !== $in['date_of_birth']) {
        $errors['date_of_birth'] = 'Enter your date of birth exactly as on your ID.';
    } elseif (age_from_dob($in['date_of_birth']) < 18) {
        $errors['date_of_birth'] = 'You must be 18 or older to register.';
    } elseif (age_from_dob($in['date_of_birth']) > 100) {
        $errors['date_of_birth'] = 'Check the year of birth.';
    }
    $in['email'] = strtolower($in['email']);
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || strlen($in['email']) > 120) {
        $errors['email'] = 'Enter a valid email address.';
    }
    $phone = normalize_ng_phone($in['phone']);
    if (!$phone) {
        $errors['phone'] = 'Enter a Nigerian mobile number, e.g. 0803 123 4567.';
    }
    if (!in_array($in['state'], $stateNames, true)) {
        $errors['state'] = 'Choose your state.';
    } elseif (!in_array($in['lga'], $lgas[$in['state']] ?? [], true)) {
        $errors['lga'] = 'Choose your LGA.';
    }
    if ($in['role'] === 'buyer' && $in['organisation'] !== '' && mb_strlen($in['organisation']) > 120) {
        $errors['organisation'] = 'Keep the organisation name under 120 characters.';
    }
    $pwError = passwordPolicyError((string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
    if ($pwError) {
        $errors['password'] = $pwError;
    }
    if (empty($_POST['accept_terms'])) {
        $errors['accept_terms'] = 'You need to accept the terms and privacy notice to register.';
    }

    if (!$errors) {
        // Same answer whether the email or the phone is taken, so the form can't be used
        // to look up who has an account; the person is pointed to login instead.
        $taken = db_one("SELECT id FROM members WHERE email = ? OR phone = ? LIMIT 1", [$in['email'], $phone]);
        if ($taken) {
            $errors['form'] = 'An account already uses that email address or phone number. Log in instead, or use different details.';
        }
    }

    if (!$errors) {
        rate_hit('register', client_ip());
        executeQuery(
            "INSERT INTO members (public_id, role, first_name, middle_name, last_name, date_of_birth, email, phone,
                                  state, lga, organisation, password_hash, terms_version, terms_accepted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                new_public_id(), $in['role'], clean_name($in['first_name']),
                $in['middle_name'] === '' ? null : clean_name($in['middle_name']), clean_name($in['last_name']),
                $in['date_of_birth'], $in['email'], $phone, $in['state'], $in['lga'],
                $in['role'] === 'buyer' && $in['organisation'] !== '' ? $in['organisation'] : null,
                password_hash($_POST['password'], PASSWORD_DEFAULT), MEMBER_TERMS_VERSION,
            ]
        );
        $member = db_one("SELECT * FROM members WHERE id = ?", [db_insert_id()]);
        member_login($member);

        $code = issue_phone_otp($member['id']);
        sms_send($member['phone'], "Your Farm Survey verification code is $code. It expires in " . OTP_TTL_MINUTES . " minutes. Do not share it.");
        rate_hit('otp_send', $member['id']);
        if (is_dev() && (appConfig()['sms']['driver'] ?? 'log') === 'log') {
            flash('dev', 'SMS is not configured, so here is the code: <strong>' . e($code) . '</strong>');
        }
        $devLink = send_email_verification($member);
        if ($devLink) {
            flash('dev', 'Email is not configured. Verification link: <a href="' . e($devLink) . '">' . e($devLink) . '</a>');
        }
        flash('success', 'Account created. Enter the code we sent to your phone.');
        redirect('verify_phone.php');
    }
}

layout_start('Create your account');
?>
<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 text-gray-900 mb-1">Create your account</h1>
                <p class="text-gray-600 mb-4">
                    It takes about five minutes. You will confirm your phone and email, then verify your identity
                    with a valid government ID and a selfie.
                </p>

                <?php if (isset($errors['form'])) { ?>
                    <div class="alert alert-danger"><?= e($errors['form']) ?></div>
                <?php } elseif ($errors) { ?>
                    <div class="alert alert-danger">Please fix the highlighted fields.</div>
                <?php } ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>

                    <h2 class="members-section">How will you use the platform?</h2>
                    <div class="row role-choice mb-1">
                        <div class="col-md-6 mb-2">
                            <label class="role-card<?= $in['role'] === 'collector' ? ' selected' : '' ?>">
                                <input type="radio" name="role" value="collector" <?= $in['role'] === 'collector' ? 'checked' : '' ?>>
                                <i class="fas fa-clipboard-list"></i>
                                <strong>Data collector</strong>
                                <span>Run farm and market surveys and earn when your verified data sells.</span>
                            </label>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="role-card<?= $in['role'] === 'buyer' ? ' selected' : '' ?>">
                                <input type="radio" name="role" value="buyer" <?= $in['role'] === 'buyer' ? 'checked' : '' ?>>
                                <i class="fas fa-chart-bar"></i>
                                <strong>Data buyer</strong>
                                <span>Buy verified survey datasets or commission new surveys.</span>
                            </label>
                        </div>
                    </div>
                    <?= field_error($errors, 'role') ?>

                    <h2 class="members-section">Your details <small>exactly as on your ID</small></h2>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="first_name">First name</label>
                            <input id="first_name" name="first_name" class="form-control<?= invalid($errors, 'first_name') ?>" value="<?= e($in['first_name']) ?>" autocomplete="given-name" required>
                            <?= field_error($errors, 'first_name') ?>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="middle_name">Middle name <span class="text-gray-500">(optional)</span></label>
                            <input id="middle_name" name="middle_name" class="form-control<?= invalid($errors, 'middle_name') ?>" value="<?= e($in['middle_name']) ?>" autocomplete="additional-name">
                            <?= field_error($errors, 'middle_name') ?>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="last_name">Surname</label>
                            <input id="last_name" name="last_name" class="form-control<?= invalid($errors, 'last_name') ?>" value="<?= e($in['last_name']) ?>" autocomplete="family-name" required>
                            <?= field_error($errors, 'last_name') ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="date_of_birth">Date of birth</label>
                            <input id="date_of_birth" name="date_of_birth" type="date" max="<?= date('Y-m-d', strtotime('-18 years')) ?>" class="form-control<?= invalid($errors, 'date_of_birth') ?>" value="<?= e($in['date_of_birth']) ?>" required>
                            <?= field_error($errors, 'date_of_birth') ?>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="phone">Mobile number</label>
                            <input id="phone" name="phone" type="tel" inputmode="tel" placeholder="0803 123 4567" class="form-control<?= invalid($errors, 'phone') ?>" value="<?= e($in['phone']) ?>" autocomplete="tel" required>
                            <?= field_error($errors, 'phone') ?>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" class="form-control<?= invalid($errors, 'email') ?>" value="<?= e($in['email']) ?>" autocomplete="email" required>
                            <?= field_error($errors, 'email') ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="state">State</label>
                            <select id="state" name="state" class="custom-select<?= invalid($errors, 'state') ?>" required>
                                <option value="">Choose…</option>
                                <?php foreach ($states as $s) { ?>
                                    <option value="<?= e($s['name']) ?>" <?= $in['state'] === $s['name'] ? 'selected' : '' ?>><?= e(trim($s['simple_name'] ?: $s['name'])) ?></option>
                                <?php } ?>
                            </select>
                            <?= field_error($errors, 'state') ?>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="lga">Local government area</label>
                            <select id="lga" name="lga" class="custom-select<?= invalid($errors, 'lga') ?>" data-selected="<?= e($in['lga']) ?>" required>
                                <option value="">Choose a state first</option>
                            </select>
                            <?= field_error($errors, 'lga') ?>
                        </div>
                    </div>
                    <div class="form-group buyer-only"<?= $in['role'] === 'buyer' ? '' : ' hidden' ?>>
                        <label for="organisation">Organisation <span class="text-gray-500">(optional)</span></label>
                        <input id="organisation" name="organisation" class="form-control<?= invalid($errors, 'organisation') ?>" value="<?= e($in['organisation']) ?>" autocomplete="organization">
                        <?= field_error($errors, 'organisation') ?>
                    </div>

                    <h2 class="members-section">Password</h2>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" class="form-control<?= invalid($errors, 'password') ?>" autocomplete="new-password" required>
                            <small class="form-text text-gray-500">At least 8 characters, with an uppercase letter, a lowercase letter and a number.</small>
                            <?= field_error($errors, 'password') ?>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="password_confirm">Confirm password</label>
                            <input id="password_confirm" name="password_confirm" type="password" class="form-control" autocomplete="new-password" required>
                        </div>
                    </div>

                    <div class="custom-control custom-checkbox mb-4">
                        <input type="checkbox" class="custom-control-input<?= invalid($errors, 'accept_terms') ?>" id="accept_terms" name="accept_terms" value="1" <?= !empty($_POST['accept_terms']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="accept_terms">
                            I agree to the <a href="terms.php" target="_blank">terms of use</a> and
                            <a href="privacy.php" target="_blank">privacy notice</a>, and I consent to my ID
                            details being checked against the national identity database for verification.
                        </label>
                        <?= field_error($errors, 'accept_terms') ?>
                    </div>

                    <button class="btn btn-success btn-lg btn-block">Create account</button>
                    <p class="text-center small mt-3 mb-0">Already registered? <a href="login.php">Log in</a></p>
                </form>
            </div>
        </div>
    </div>
</div>
<script>window.LGAS = <?= json_encode($lgas, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php
layout_end(['assets/register.js']);
