<?php
// GET ?distribution=<id>: one distribution record awaiting verification. Requires the approval right.
require __DIR__ . '/_bootstrap.php';

requireMethod('GET');
$user = requireApiUser();
requireApiRight($user, 'approval');

jsonResponse(200, getUnverified('input_distrubution', isset($_GET['distribution']) ? $_GET['distribution'] : ''));
