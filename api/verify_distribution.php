<?php
// POST {"id": <id>}: mark a distribution record as verified. Requires the approval right.
require __DIR__ . '/_bootstrap.php';

requireMethod('POST');
$user = requireApiUser();
requireApiRight($user, 'approval');

$body = readJsonBody();
verifyRecord('input_distrubution', isset($body['id']) ? $body['id'] : '');
