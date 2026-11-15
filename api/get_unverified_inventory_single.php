<?php
// GET ?inventory=<id>: one inventory record awaiting verification. Requires the approval right.
require __DIR__ . '/_bootstrap.php';

requireMethod('GET');
$user = requireApiUser();
requireApiRight($user, 'approval');

jsonResponse(200, getUnverified('input_inventory', isset($_GET['inventory']) ? $_GET['inventory'] : ''));
