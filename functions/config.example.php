<?php
// Copy this file to functions/config.php and fill in real values.
// functions/config.php is git-ignored so credentials never reach the repository.
return [
    // Public base URL of the app, used to build password reset links.
    'app_url' => 'http://localhost/Farm-Survey',

    // Where farm photos are stored. Empty means the project's uploads/ folder, which
    // relies on uploads/.htaccess to block direct access (Apache with AllowOverride).
    // On a server, prefer a folder outside the web root, e.g. '/var/lib/farm-survey/uploads'.
    'uploads_dir' => '',

    // MySQL / MariaDB connection. The values below are XAMPP's defaults; use a
    // dedicated user with a strong password on any shared or production server.
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'username' => 'root',
        'password' => '',
        'name'     => 'farm_survey',
    ],

    // SMTP settings for PHPMailer (Gmail app password, Mailtrap, SendGrid, etc.).
    // Leave 'host' empty to disable email; the app will fall back to showing links to admins.
    'smtp' => [
        'host'       => '',
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'encryption' => 'tls', // 'tls', 'ssl' or ''
        'from_email' => 'no-reply@example.com',
        'from_name'  => 'Farm Survey',
    ],
];
