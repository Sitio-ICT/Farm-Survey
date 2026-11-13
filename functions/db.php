<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_CHARSET', 'utf-8');
define('DB_NAME', 'farm_survey');

// Session cookie hardening. Applied before the session starts (login.php and the
// handlers include this file first), which is when the cookie is issued.
// SameSite=Lax stops other sites from POSTing to the handlers with the user's session.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
}

// connect to the database with the defined values
$connection = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
// if there's an error
if (!$connection) {
    echo "Failed to connect database" . die(mysqli_error($connection));
}
$dbselect = mysqli_select_db($connection, DB_NAME);
if (!$dbselect) {
    echo "Failed to select database" . die(mysqli_error($connection));
}