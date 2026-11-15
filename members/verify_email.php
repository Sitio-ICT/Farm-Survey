<?php
require __DIR__ . '/../functions/members/bootstrap.php';

// POST: resend the link (signed-in member). GET ?token=: confirm the address.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member = require_member();
    if (!empty($member['email_verified_at'])) {
        redirect('index.php');
    }
    if (rate_limited('email_send', $member['id'], 3, 3600)) {
        flash('error', 'You have asked for several emails. Wait an hour before requesting another.');
    } else {
        rate_hit('email_send', $member['id']);
        $devLink = send_email_verification($member);
        if ($devLink) {
            flash('dev', 'Email is not configured. Verification link: <a href="' . e($devLink) . '">' . e($devLink) . '</a>');
        }
        flash('success', 'We sent a new link to ' . $member['email'] . '.');
    }
    redirect('index.php');
}

$memberId = consume_email_token($_GET['token'] ?? '');
if ($memberId) {
    executeQuery("UPDATE members SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?", [$memberId]);
    flash('success', 'Email address confirmed.');
} else {
    flash('error', 'That link has expired or was already used. Log in and request a new one.');
}
redirect(current_member() ? 'index.php' : 'login.php');
