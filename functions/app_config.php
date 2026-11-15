<?php
// Loads application settings from functions/config.php (git-ignored; copy
// functions/config.example.php to create it). Shared by db.php and mail.php.

function appConfigPath()
{
    return __DIR__ . '/config.php';
}

function hasAppConfig()
{
    return file_exists(appConfigPath());
}

/**
 * The settings array. Without config.php, the example file's values are used for
 * optional features (mail stays disabled); db.php refuses to run without config.php.
 */
function appConfig()
{
    static $config = null;
    if ($config === null) {
        $config = hasAppConfig() ? require appConfigPath() : require __DIR__ . '/config.example.php';
    }
    return $config;
}
