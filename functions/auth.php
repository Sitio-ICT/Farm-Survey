<?php
// Session guard for form handlers and AJAX endpoints under functions/.
// Pages are protected by header.php, but handlers are separate URLs that never
// include it, so each handler calls one of these first.
// Requires functions/connect.php to be included already (for require_right).

/**
 * Web root of the app (the part of the script path before /functions/), e.g. "/Farm-Survey".
 */
function app_base_path()
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $pos = strpos($script, '/functions/');
    return $pos === false ? '' : substr($script, 0, $pos);
}

function auth_deny($status, $ajax, $location, $message = null)
{
    http_response_code($status);
    if ($ajax) {
        exit;
    }
    if ($message !== null) {
        $key = str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        $_SESSION["feedback"] = $message;
        $_SESSION["Lack_of_intfund_$key"] = "10";
        $location .= (strpos($location, '?') === false ? '?' : '&') . "message1=$key";
    }
    header("Location: " . app_base_path() . "/" . $location);
    exit;
}

/**
 * Allow only logged-in users. AJAX callers get a bare 401 instead of a redirect.
 */
function require_login($ajax = false)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION["loggedin"])) {
        auth_deny(401, $ajax, "login.php");
    }
    // Same rule as header.php: a temporary password must be changed before anything else.
    $script = basename($_SERVER['SCRIPT_NAME']);
    if (!empty($_SESSION["must_change_password"]) && $script !== 'change_password.php') {
        auth_deny(403, $ajax, "profile.php?force=1");
    }
}

/**
 * Allow only logged-in users whose designation has $right
 * (view_report, take_survey, correct_survey, approval, users_management, register_farmer).
 */
function require_right($right, $ajax = false)
{
    require_login($ajax);
    $designation = selectOne('designation', ['id' => $_SESSION['designation']]);
    if (empty($designation[$right]) || (int) $designation[$right] !== 1) {
        auth_deny(403, $ajax, "index.php", "You don't have permission for that action.");
    }
}
