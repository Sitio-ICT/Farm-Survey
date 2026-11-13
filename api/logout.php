<?php
// POST with Authorization: Bearer <token>: revoke that token.
require __DIR__ . '/_bootstrap.php';

requireMethod('POST');
$user = requireApiUser();
executeQuery("UPDATE api_tokens SET revoked_at = NOW() WHERE id = ?", [$user['token_id']]);

jsonResponse(200, ["message" => "Logged out."]);
