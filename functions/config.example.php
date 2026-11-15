<?php
// Copy this file to functions/config.php and fill in real values.
// functions/config.php is git-ignored so credentials never reach the repository.
return [
    // Public base URL of the app, used to build password reset links.
    'app_url' => 'http://localhost/Farm-Survey',

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

    // 'development' shows one-time codes and email links on screen when SMS or SMTP
    // is not set up. Always 'production' on a public server.
    'app_env' => 'development',

    // Marketplace members: keys for the identity vault. Generate each with
    //   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    // Losing vault_key makes stored selfies and ID images unreadable; rotating
    // hmac_key breaks duplicate-ID detection for earlier records.
    'vault' => [
        'vault_key'    => '',   // encrypts selfie and ID images at rest
        'hmac_key'     => '',   // keyed hash of ID numbers for duplicate detection
        // Encrypted files. Point this outside the web root on a server.
        'storage_path' => __DIR__ . '/../storage/vault',
    ],

    // SMS for phone verification codes. 'log' writes messages to storage/logs/sms.log.
    'sms' => [
        'driver'    => 'log',   // 'log' or 'termii'
        'api_key'   => '',
        'sender_id' => '',      // approved Termii sender ID
        'channel'   => 'dnd',   // 'dnd' delivers to DND numbers; needs an approved sender ID
        'base_url'  => 'https://api.ng.termii.com',
    ],

    // Identity verification. 'sandbox' needs no account (see functions/members/identity/SandboxProvider.php).
    'identity' => [
        'provider'        => 'sandbox',  // 'sandbox' or 'dojah'
        'base_url'        => 'https://sandbox.dojah.io', // https://api.dojah.io in production
        'app_id'          => '',
        'secret_key'      => '',
        'selfie_threshold'=> 80,         // provider face-match score (50-100) needed to pass
        'name_threshold'  => 0.85,       // 0-1 similarity of declared vs registry name
        'max_attempts_per_day' => 3,
    ],
];
