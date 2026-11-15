<?php
// GET ?cluster_id=<id>: one cluster record awaiting verification. Requires the approval right.
require __DIR__ . '/_bootstrap.php';

requireMethod('GET');
$user = requireApiUser();
requireApiRight($user, 'approval');

jsonResponse(200, getUnverified('input_cluster', isset($_GET['cluster_id']) ? $_GET['cluster_id'] : ''));
