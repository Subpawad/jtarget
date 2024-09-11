<?php
include 'db_connection.php'; // รวมไฟล์การเชื่อมต่อฐานข้อมูล

$year = $_GET['year'];
$branch_name = $_GET['branch_name'];

// รับ branch_id จาก branch_name
$sql = "SELECT branch_id FROM branch WHERE branch_name = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $branch_name);
$stmt->execute();
$result = $stmt->get_result();
$branch_id = $result->fetch_assoc()['branch_id'];

// ลบข้อมูลจากตาราง target
$sql = "DELETE FROM target WHERE target_year = ? AND branch_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $year, $branch_id);
if ($stmt->execute()) {
    echo "Record deleted successfully";
} else {
    echo "Error: " . $stmt->error;
}

header("Location: test.php"); // เปลี่ยนเส้นทางกลับไปที่หน้าหลัก
exit;
?>
