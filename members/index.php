<?php
require __DIR__ . '/../functions/members/bootstrap.php';
require __DIR__ . '/_layout.php';
require __DIR__ . '/../functions/members/identity/service.php';

$member = require_member();
$p = member_progress($member);
$latest = db_one("SELECT * FROM identity_verifications WHERE member_id = ? ORDER BY id DESC LIMIT 1", [$member['id']]);
$reasons = $latest ? (json_decode($latest['reasons'], true) ?: []) : [];

layout_start('Your account', $member);
progress_steps($member, $p['email'] ? 'identity' : 'email');
?>
<div class="row">
    <div class="col-lg-8">
        <?php if ($p['complete']) { ?>
            <div class="card border-left-success shadow-sm mb-4">
                <div class="card-body">
                    <h1 class="h4 text-gray-900 mb-1"><i class="fas fa-check-circle text-success mr-1"></i> You are fully registered</h1>
                    <p class="mb-0 text-gray-700">
                        <?= $member['role'] === 'buyer'
                            ? 'You can browse and buy datasets once the catalogue opens.'
                            : 'You can claim surveys once the first commissioned surveys open in your area.' ?>
                    </p>
                </div>
            </div>
        <?php } else { ?>
            <h1 class="h4 text-gray-900 mb-3">Finish verifying your account</h1>
        <?php } ?>

        <div class="list-group shadow-sm mb-4 verify-list">
            <div class="list-group-item d-flex align-items-center">
                <i class="fas fa-mobile-alt fa-fw text-gray-500 mr-3"></i>
                <div class="flex-grow-1">
                    <strong>Phone</strong>
                    <div class="small text-gray-600"><?= e($member['phone']) ?></div>
                </div>
                <span class="badge badge-success">Confirmed</span>
            </div>

            <div class="list-group-item d-flex align-items-center">
                <i class="fas fa-envelope fa-fw text-gray-500 mr-3"></i>
                <div class="flex-grow-1">
                    <strong>Email</strong>
                    <div class="small text-gray-600"><?= e($member['email']) ?></div>
                </div>
                <?php if ($p['email']) { ?>
                    <span class="badge badge-success">Confirmed</span>
                <?php } else { ?>
                    <form method="post" action="verify_email.php" class="m-0">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-success">Resend link</button>
                    </form>
                <?php } ?>
            </div>

            <div class="list-group-item">
                <div class="d-flex align-items-center">
                    <i class="fas fa-id-card fa-fw text-gray-500 mr-3"></i>
                    <div class="flex-grow-1">
                        <strong>Identity</strong>
                        <div class="small text-gray-600">Valid government ID and a selfie</div>
                    </div>
                    <?php
                    switch ($member['id_status']) {
                        case 'verified':
                            echo '<span class="badge badge-success">Verified</span>';
                            break;
                        case 'manual_review':
                            echo '<span class="badge badge-warning">Being reviewed</span>';
                            break;
                        case 'rejected':
                            echo '<a href="verify_id.php" class="btn btn-sm btn-success">Try again</a>';
                            break;
                        default:
                            echo '<a href="verify_id.php" class="btn btn-sm btn-success">Verify ID</a>';
                    }
                    ?>
                </div>
                <?php if ($member['id_status'] === 'manual_review') { ?>
                    <p class="small text-gray-700 mt-2 mb-0 ml-5">
                        Our team is checking your ID. This usually takes one working day. We will email you when it is done.
                    </p>
                <?php } elseif ($member['id_status'] === 'rejected' && $latest) { ?>
                    <div class="small text-danger mt-2 mb-0 ml-5">
                        <?php foreach ($reasons as $r) { ?><div><?= e(ID_REASON_TEXT[$r] ?? $r) ?></div><?php } ?>
                        <?php if ($latest['review_note']) { ?><div>Reviewer note: <?= e($latest['review_note']) ?></div><?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h6 text-gray-900 font-weight-bold">Why we verify</h2>
                <p class="small text-gray-700">
                    Buyers pay for data they can trust. Verifying every collector and buyer with a real ID keeps
                    fake accounts and made-up data out, and makes sure payouts reach the right person.
                </p>
                <h2 class="h6 text-gray-900 font-weight-bold">What we keep</h2>
                <p class="small text-gray-700 mb-0">
                    We do not store your ID number, only a coded fingerprint of it and its last 4 digits. Your selfie is
                    encrypted and only our verification team can open it. Nothing about your identity is published or sold.
                </p>
            </div>
        </div>
    </div>
</div>
<?php
layout_end();
