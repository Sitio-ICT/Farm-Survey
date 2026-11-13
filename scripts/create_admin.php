<?php
// Create the first administrator from the command line (public sign-up was removed).
// Usage: php scripts/create_admin.php <email> <first name> <last name>
// The password is prompted for (or read from the ADMIN_PASSWORD environment variable). If no designation exists yet, an "Administrator"
// designation with every right is created.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

chdir(__DIR__ . '/../functions');
include 'connect.php';
require_once 'password_reset.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php <email> <first name> <last name>\n");
    exit(1);
}
list(, $email, $firstname, $lastname) = $argv;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Not a valid email address.\n");
    exit(1);
}
if (selectOne('users', ['email' => $email])) {
    fwrite(STDERR, "A user with that email already exists.\n");
    exit(1);
}

function prompt_hidden($label)
{
    fwrite(STDOUT, $label);
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $value = rtrim((string) shell_exec('powershell -NoProfile -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"'), "\r\n");
    } else {
        system('stty -echo');
        $value = rtrim((string) fgets(STDIN), "\r\n");
        system('stty echo');
    }
    fwrite(STDOUT, PHP_EOL);
    return $value;
}

// ADMIN_PASSWORD allows non-interactive setup (CI, provisioning scripts).
if (getenv('ADMIN_PASSWORD') !== false) {
    $password = $confirm = getenv('ADMIN_PASSWORD');
} else {
    $password = prompt_hidden("Password: ");
    $confirm = prompt_hidden("Confirm password: ");
}
$error = passwordPolicyError($password, $confirm);
if ($error) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}

$admin = executeQuery("SELECT id FROM designation WHERE users_management = 1 AND approval = 1 ORDER BY id LIMIT 1")
    ->get_result()->fetch_assoc();
if ($admin) {
    $designationId = $admin['id'];
} else {
    $designationId = insert('designation', [
        'title' => 'Administrator',
        'description' => 'Full access',
        'view_report' => 1, 'take_survey' => 1, 'correct_survey' => 1,
        'approval' => 1, 'users_management' => 1, 'register_farmer' => 1,
    ]);
}

$id = insert('users', [
    'firstname' => $firstname,
    'lastname' => $lastname,
    'email' => $email,
    'passkey' => password_hash($password, PASSWORD_DEFAULT),
    'designation_id' => $designationId,
    'must_change_password' => 0,
]);
if (!$id) {
    fwrite(STDERR, "Could not create the user: " . mysqli_error($connection) . "\n");
    exit(1);
}
echo "Administrator $email created (user id $id, designation id $designationId).\n";
