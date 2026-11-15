<?php
// POST {"email": "...", "password": "..."} -> {"token": "...", "expires_at": "...", "user": {...}}
// Send the token on every other request as: Authorization: Bearer <token>
require __DIR__ . '/_bootstrap.php';

requireMethod('POST');
$body = readJsonBody();
$email = isset($body['email']) ? trim((string) $body['email']) : '';
$password = isset($body['password']) ? (string) $body['password'] : '';
if ($email === '' || $password === '') {
    jsonResponse(400, ["message" => "Email and password are required."]);
}

$user = selectOne('users', ['email' => $email]);
// Same answer for an unknown email and a wrong password, so accounts can't be discovered.
if (!$user || !password_verify($password, $user['passkey'])) {
    jsonResponse(401, ["message" => "Invalid email or password."]);
}
if (!empty($user['must_change_password'])) {
    jsonResponse(403, ["message" => "This account is still on a temporary password. Set your password on the web app first."]);
}

$designation = selectOne('designation', ['id' => $user['designation_id']]);
list($token, $expiresAt) = issueApiToken($user['id']);

jsonResponse(200, [
    "token" => $token,
    "token_type" => "Bearer",
    "expires_at" => $expiresAt,
    "user" => [
        "id" => (int) $user['id'],
        "name" => trim($user['firstname'] . ' ' . $user['lastname']),
        "rights" => userRights($designation ?: []),
    ],
]);
