<?php
// POST {"id": <id>, "<column>": <value>, ...}: correct a distribution record.
// Only real, writable columns are accepted; id and status cannot be changed here.
// Requires the approval or correct_survey right.
require __DIR__ . '/_bootstrap.php';

requireMethod('POST');
$user = requireApiUser();
requireApiRight($user, 'approval', 'correct_survey');

updateRecord('input_distrubution', readJsonBody());
