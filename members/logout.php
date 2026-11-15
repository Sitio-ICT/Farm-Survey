<?php
require __DIR__ . '/../functions/members/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed');
}
member_logout();
header('Location: login.php');
