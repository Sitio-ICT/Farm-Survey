<?php
require_once __DIR__ . '/../auth.php';
require_login(true);

if(isset($_POST['amount'])){
    $amount1 = floatval(preg_replace('/[^\d.]/', '', $_POST['amount']));
    echo $amount = number_format($amount1, 2);
}