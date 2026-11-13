<?php

include("../../../connect.php");
require_once __DIR__ . '/../../../auth.php';
require_right('users_management', true);
$itemId = $_POST['itemId'];
if(isset($_POST['itemId'])){
    $delete = delete('farm_items', $itemId, 'id');
}


?>