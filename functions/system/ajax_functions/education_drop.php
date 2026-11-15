<?php
include("../../connect.php");
require_once __DIR__ . '/../../auth.php';
require_login(true);

$bank = $_POST['bank'];
if (isset($_POST['bank'])) {
    if ($bank == "FORMAL") {
?>

        <div class="form-group">
            <!-- <label for="">Branches</label> -->
            <div class="form-group">
                <input type="text" class="form-control form-control-user" name="education_grade" placeholder="Highest Education Grade..." required>
            </div>
        </div>

<?php
    }
}
?>