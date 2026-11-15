<?php
// Helpers shared by member pages and the staff review pages. No session handling here.

require_once __DIR__ . '/../app_config.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_dev()
{
    return (appConfig()['app_env'] ?? 'production') === 'development';
}

function app_url($path = '')
{
    return rtrim(appConfig()['app_url'], '/') . '/' . ltrim($path, '/');
}




function db_one($sql, $params = [])
{
    $row = executeQuery($sql, $params)->get_result()->fetch_assoc();
    return $row ?: null;
}

function db_all($sql, $params = [])
{
    return executeQuery($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}

function db_insert_id()
{
    global $connection;
    return (int) $connection->insert_id;
}
