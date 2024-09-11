<?php
include 'db_connection.php'; // Include your database connection file

if (isset($_POST['areazone_id'])) {
    $areazone_id = $_POST['areazone_id'];

    // ดึงข้อมูล branch ตาม areazone_id
    $query = "SELECT * FROM branch WHERE areazone_id = '$areazone_id'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        echo '<option value="">-- เลือกสาขา --</option>';
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<option value="' . $row['branch_id'] . '">' . $row['branch_name'] . '</option>';
        }
    } else {
        echo '<option value="">ไม่มีสาขา</option>';
    }
}
?>
