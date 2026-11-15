<?php
// Staff decision on an identity check in the manual-review queue.
session_start();
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../mail.php';

require_post();
require_right('approval');

$id = (int) ($_POST['id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$note = trim((string) ($_POST['note'] ?? ''));
$back = app_base_path() . '/identity_reviews.php';

function review_feedback($message, $ok, $location)
{
    $key = str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    $_SESSION['feedback'] = $message;
    $_SESSION["Lack_of_intfund_$key"] = '10';
    header('Location: ' . $location . (strpos($location, '?') === false ? '?' : '&') . ($ok ? 'message0=' : 'message1=') . $key);
    exit;
}

if (!in_array($decision, ['approved', 'rejected'], true)) {
    review_feedback('Choose approve or reject.', false, "$back?id=$id");
}
if ($decision === 'rejected' && $note === '') {
    review_feedback('Add a note telling the member why their ID was rejected.', false, "$back?id=$id");
}

$v = db_one(
    "SELECT v.*, m.email, m.first_name, m.id_status FROM identity_verifications v
     JOIN members m ON m.id = v.member_id WHERE v.id = ?",
    [$id]
);
if (!$v || $v['outcome'] !== 'manual_review' || $v['reviewed_at']) {
    review_feedback('This case was already decided or does not exist.', false, $back);
}

executeQuery(
    "UPDATE identity_verifications SET reviewed_by = ?, reviewed_at = NOW(), review_decision = ?, review_note = ?
     WHERE id = ? AND reviewed_at IS NULL",
    [$_SESSION['userid'], $decision, mb_substr($note, 0, 500), $id]
);
if ($decision === 'approved') {
    executeQuery("UPDATE members SET id_status = 'verified', id_verified_at = NOW() WHERE id = ?", [$v['member_id']]);
} else {
    executeQuery("UPDATE members SET id_status = 'rejected' WHERE id = ? AND id_status <> 'verified'", [$v['member_id']]);
}

$subject = $decision === 'approved' ? 'Your identity is verified' : 'We could not verify your identity';
$body = '<p>Hello ' . e($v['first_name']) . ',</p>'
    . ($decision === 'approved'
        ? '<p>Our team has verified your identity. Your Farm Survey account is now fully registered.</p>'
        : '<p>Our team could not verify the ID you submitted.</p><p>Reason: ' . e($note) . '</p><p>You can log in and try again with another valid ID.</p>');
sendMail($v['email'], $subject, $body);

review_feedback($decision === 'approved' ? 'Identity approved.' : 'Identity rejected; the member has been told why.', true, $back);
