<?php
// Staff queue for identity checks that need a person: partial matches, document
// IDs (licence, passport, PVC) and IDs already linked to another account.
include('header.php');
require_once __DIR__ . '/functions/members/common.php';
require_once __DIR__ . '/functions/members/crypto.php';
require_once __DIR__ . '/functions/members/validation.php';
require_once __DIR__ . '/functions/members/identity/service.php';

if ((int) ($findRights['approval'] ?? 0) !== 1) {
    echo '<div class="container-fluid"><div class="alert alert-danger">You need the approval right to review identity checks.</div></div>';
    include('footer.php');
    exit;
}

$viewId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
?>
<link href="members/assets/members.css" rel="stylesheet">
<div class="container-fluid">
<?php if (!$viewId) {
    $queue = db_all(
        "SELECT v.*, m.first_name, m.last_name, m.role, m.state, m.lga
         FROM identity_verifications v JOIN members m ON m.id = v.member_id
         WHERE v.outcome = 'manual_review' AND v.reviewed_at IS NULL
         ORDER BY v.created_at ASC"
    );
    $recent = db_all(
        "SELECT v.id, v.review_decision, v.reviewed_at, m.first_name, m.last_name, u.firstname AS reviewer
         FROM identity_verifications v JOIN members m ON m.id = v.member_id
         LEFT JOIN users u ON u.id = v.reviewed_by
         WHERE v.reviewed_at IS NOT NULL ORDER BY v.reviewed_at DESC LIMIT 10"
    );
    ?>
    <h1 class="h3 mb-4 text-gray-800">Identity reviews</h1>
    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Waiting for review (<?= count($queue) ?>)</h6></div>
        <div class="card-body">
            <?php if (!$queue) { ?>
                <p class="mb-0 text-gray-600">Nothing waiting. New cases appear here when an automatic check can't decide.</p>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Submitted</th><th>Member</th><th>Role</th><th>Area</th><th>ID</th><th>Why it needs review</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($queue as $v) { ?>
                        <tr>
                            <td><?= e(date('j M Y H:i', strtotime($v['created_at']))) ?></td>
                            <td><?= e($v['first_name'] . ' ' . $v['last_name']) ?></td>
                            <td><?= e(ucfirst($v['role'])) ?></td>
                            <td><?= e($v['lga'] . ', ' . $v['state']) ?></td>
                            <td><?= e(ID_TYPES[$v['id_type']] ?? $v['id_type']) ?> ••<?= e($v['id_number_last4']) ?></td>
                            <td><?php foreach (json_decode($v['reasons'], true) ?: [] as $r) { ?><span class="badge badge-warning mr-1"><?= e($r) ?></span><?php } ?></td>
                            <td><a class="btn btn-sm btn-primary" href="identity_reviews.php?id=<?= (int) $v['id'] ?>">Review</a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </div>
    <?php if ($recent) { ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Recently decided</h6></div>
        <div class="card-body small">
            <?php foreach ($recent as $r) { ?>
                <div><?= e(date('j M H:i', strtotime($r['reviewed_at']))) ?> · <?= e($r['first_name'] . ' ' . $r['last_name']) ?> ·
                    <strong><?= e($r['review_decision']) ?></strong> by <?= e($r['reviewer'] ?? 'unknown') ?></div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
<?php } else {
    $v = db_one(
        "SELECT v.*, m.first_name, m.middle_name, m.last_name, m.date_of_birth, m.role, m.state, m.lga,
                m.email, m.phone, m.created_at AS member_since, m.id_status
         FROM identity_verifications v JOIN members m ON m.id = v.member_id WHERE v.id = ?",
        [$viewId]
    );
    if (!$v) {
        echo '<div class="alert alert-danger">Not found.</div></div>';
        include('footer.php');
        exit;
    }
    $history = db_all("SELECT id, id_type, outcome, provider, created_at FROM identity_verifications WHERE member_id = ? ORDER BY id DESC", [$v['member_id']]);
    $others = db_all(
        "SELECT v2.member_id, m2.first_name, m2.last_name, v2.outcome FROM identity_verifications v2
         JOIN members m2 ON m2.id = v2.member_id
         WHERE v2.id_type = ? AND v2.id_number_hmac = ? AND v2.member_id <> ?",
        [$v['id_type'], $v['id_number_hmac'], $v['member_id']]
    );
    $open = $v['outcome'] === 'manual_review' && !$v['reviewed_at'];
    ?>
    <a href="identity_reviews.php" class="small">&larr; Back to queue</a>
    <h1 class="h3 mb-4 mt-2 text-gray-800">Review: <?= e($v['first_name'] . ' ' . $v['last_name']) ?></h1>
    <div class="row">
        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Declared by the member</h6></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Full name</dt><dd class="col-7"><?= e(trim($v['first_name'] . ' ' . $v['middle_name'] . ' ' . $v['last_name'])) ?></dd>
                        <dt class="col-5">Date of birth</dt><dd class="col-7"><?= e(date('j M Y', strtotime($v['date_of_birth']))) ?></dd>
                        <dt class="col-5">Role</dt><dd class="col-7"><?= e(ucfirst($v['role'])) ?></dd>
                        <dt class="col-5">Area</dt><dd class="col-7"><?= e($v['lga'] . ', ' . $v['state']) ?></dd>
                        <dt class="col-5">Phone / email</dt><dd class="col-7"><?= e($v['phone']) ?><br><?= e($v['email']) ?></dd>
                        <dt class="col-5">Member since</dt><dd class="col-7"><?= e(date('j M Y', strtotime($v['member_since']))) ?></dd>
                    </dl>
                </div>
            </div>
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Check results</h6></div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-5">ID</dt><dd class="col-7"><?= e(ID_TYPES[$v['id_type']] ?? $v['id_type']) ?> ending <?= e($v['id_number_last4']) ?></dd>
                        <dt class="col-5">Provider</dt><dd class="col-7"><?= e($v['provider']) ?> <?= $v['provider_ref'] ? '(' . e($v['provider_ref']) . ')' : '' ?></dd>
                        <dt class="col-5">Name match</dt><dd class="col-7"><?= $v['name_score'] === null ? 'n/a' : e(round($v['name_score'] * 100) . '%') ?></dd>
                        <dt class="col-5">Date of birth</dt><dd class="col-7"><?= $v['dob_match'] === null ? 'n/a' : ($v['dob_match'] ? 'matches' : '<span class="text-danger">does not match</span>') ?></dd>
                        <dt class="col-5">Selfie match</dt><dd class="col-7"><?= $v['selfie_confidence'] === null ? 'n/a' : e($v['selfie_confidence'] . ' / 100') ?></dd>
                        <dt class="col-5">Flags</dt><dd class="col-7"><?php foreach (json_decode($v['reasons'], true) ?: [] as $r) { ?><div><?= e(ID_REASON_TEXT[$r] ?? $r) ?></div><?php } ?></dd>
                    </dl>
                    <?php if ($others) { ?>
                        <div class="alert alert-danger mt-3 mb-0">Same ID number used by:
                            <?php foreach ($others as $o) { ?><div><?= e($o['first_name'] . ' ' . $o['last_name']) ?> (<?= e($o['outcome']) ?>)</div><?php } ?>
                        </div>
                    <?php } ?>
                    <div class="mt-3 text-gray-600">Attempts: <?php foreach ($history as $h) { ?><span class="badge badge-light"><?= e($h['id_type'] . ' ' . $h['outcome']) ?></span> <?php } ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Images <span class="font-weight-normal text-gray-500">(opening them is logged)</span></h6></div>
                <div class="card-body review-images">
                    <div class="row">
                        <?php if ($v['selfie_file']) { ?>
                            <div class="col-md-6 mb-3"><div class="small font-weight-bold mb-1">Selfie</div>
                                <img src="identity_image.php?id=<?= (int) $v['id'] ?>&amp;item=selfie" alt="Selfie"></div>
                        <?php } ?>
                        <?php if ($v['document_file']) { ?>
                            <div class="col-md-6 mb-3"><div class="small font-weight-bold mb-1">ID document</div>
                                <img src="identity_image.php?id=<?= (int) $v['id'] ?>&amp;item=document" alt="ID document"></div>
                        <?php } ?>
                    </div>
                    <p class="small text-gray-600 mb-0">For document IDs, check that the name, date of birth and number on the card match the declared details and that the selfie is the person on the card.</p>
                </div>
            </div>
            <?php if ($open) { ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Decision</h6></div>
                <div class="card-body">
                    <form method="post" action="functions/members/review_identity.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                        <div class="form-group">
                            <label for="note">Note to the member <span class="text-gray-500">(required when rejecting; shown to them)</span></label>
                            <textarea id="note" name="note" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <button name="decision" value="approved" class="btn btn-success mr-2"><i class="fas fa-check mr-1"></i>Approve</button>
                        <button name="decision" value="rejected" class="btn btn-danger"><i class="fas fa-times mr-1"></i>Reject</button>
                    </form>
                </div>
            </div>
            <?php } else { ?>
                <div class="alert alert-secondary">Decided: <strong><?= e($v['review_decision'] ?? $v['outcome']) ?></strong>
                    <?= $v['reviewed_at'] ? 'on ' . e(date('j M Y H:i', strtotime($v['reviewed_at']))) : '' ?>
                    <?= $v['review_note'] ? '· ' . e($v['review_note']) : '' ?></div>
            <?php } ?>
        </div>
    </div>
<?php } ?>
</div>
<?php
include('footer.php');
