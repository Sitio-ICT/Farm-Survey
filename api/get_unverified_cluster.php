<?php
// GET: all cluster records awaiting verification. Requires the approval right.
require __DIR__ . '/_bootstrap.php';

requireMethod('GET');
$user = requireApiUser();
requireApiRight($user, 'approval');

jsonResponse(200, listUnverified('input_cluster'));
