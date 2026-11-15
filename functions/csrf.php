<?php
// Per-session CSRF token for the web app's forms and AJAX calls.
// Forms send it as a hidden "csrf_token" field; jQuery AJAX sends it as the
// X-CSRF-Token header (set up once per page by csrf_ajax_setup()).
// Checked for every state-changing request in functions/auth.php.

function csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_meta()
{
    return '<meta name="csrf-token" content="' . csrf_token() . '">';
}

/**
 * Makes every jQuery AJAX request on the page send the token. Output it after each
 * <script src="...jquery..."> tag, because a second jQuery replaces the global $.
 */
function csrf_ajax_setup()
{
    return '<script>if (window.jQuery) { jQuery.ajaxSetup({ headers: { "X-CSRF-Token": ' . json_encode(csrf_token()) . ' } }); }</script>';
}

function csrf_valid()
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
        $sent = $_POST['csrf_token'];
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'];
    } else {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $sent);
}

/**
 * Anything other than GET/HEAD can change state and must carry the token.
 */
function csrf_required()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
    return !in_array($method, ['GET', 'HEAD'], true);
}
