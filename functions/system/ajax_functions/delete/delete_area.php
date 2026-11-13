<?php

include("../../../connect.php");
require_once __DIR__ . '/../../../auth.php';
require_right('users_management', true);
$itemId = $_POST['itemId'];
dd($itemId);
if(isset($_POST['itemId'])){
    $delete = delete('cluster_lga', $itemId, 'id');
}


?>