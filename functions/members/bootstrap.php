<?php
// Shared setup for the marketplace member pages under members/.
// Members use their own session cookie (FSMEMBER), so a member login and a staff
// login never share state, and nothing here reads the staff `users` table.

// connect.php includes db.php: cookie hardening, $connection
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../password_reset.php'; // passwordPolicyError()
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/tokens.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/validation.php';

const MEMBER_TERMS_VERSION = '2026-11';

if (session_status() === PHP_SESSION_NONE) {
    session_name('FSMEMBER');
    session_start();
}

// Every POST to a member page must carry the session's CSRF token.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrf_valid()) {
    http_response_code(403);
    flash('error', 'Your session expired or the form was out of date. Please try again.');
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'login.php'));
    exit;
}

function redirect($path)
{
    header('Location: ' . $path);
    exit;
}

function flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes()
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/** 26-character random, URL-safe identifier (Crockford base32). */
function new_public_id()
{
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $id = '';
    foreach (str_split(random_bytes(26)) as $byte) {
        $id .= $alphabet[ord($byte) % 32];
    }
    return $id;
}

function client_ip()
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ---------------------------------------------------------------- sessions

function member_login(array $member)
{
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION['member_id'] = (int) $member['id'];
    $_SESSION['member_login_at'] = time();
    executeQuery("UPDATE members SET last_login_at = NOW() WHERE id = ?", [$member['id']]);
}

function member_logout()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_member()
{
    static $member = false;
    if ($member === false) {
        $member = empty($_SESSION['member_id'])
            ? null
            : db_one("SELECT * FROM members WHERE id = ?", [$_SESSION['member_id']]);
    }
    return $member;
}

/**
 * Pages for signed-in members. $needPhone: the phone must be confirmed first
 * (every page except the phone-verification page itself).
 */
function require_member($needPhone = true)
{
    $member = current_member();
    if (!$member) {
        redirect('login.php');
    }
    if ($member['account_status'] !== 'active') {
        member_logout();
        session_name('FSMEMBER');
        session_start();
        flash('error', 'This account is suspended. Contact support if you think this is a mistake.');
        redirect('login.php');
    }
    if ($needPhone && empty($member['phone_verified_at'])) {
        redirect('verify_phone.php');
    }
    return $member;
}

/** Registration is complete when phone, email and identity are all verified. */
function member_progress(array $m)
{
    $steps = [
        'account' => true,
        'phone' => !empty($m['phone_verified_at']),
        'email' => !empty($m['email_verified_at']),
        'identity' => $m['id_status'] === 'verified',
    ];
    $steps['complete'] = !in_array(false, $steps, true);
    return $steps;
}
