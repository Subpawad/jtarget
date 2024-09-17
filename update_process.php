<?php
include 'db_connection.php';

// รับค่าจาก POST
$year = isset($_POST['year']) ? $_POST['year'] : null;
$branch_name = isset($_POST['branch_name']) ? $_POST['branch_name'] : null;
$month = isset($_POST['month']) ? $_POST['month'] : null;
$target_amount = isset($_POST['target_amount']) ? $_POST['target_amount'] : null;

// ตรวจสอบว่าพารามิเตอร์ทั้งหมดมีค่าหรือไม่
if (!$year || !$branch_name || !$month || !$target_amount) {
    die("Missing required parameters.");
}

// ค้นหา branch_id จาก branch_name
$branch_sql = "SELECT branch_id FROM branch WHERE branch_name = ?";
$stmt = $conn->prepare($branch_sql);
$stmt->bind_param('s', $branch_name);
$stmt->execute();
$result = $stmt->get_result();
$branch = $result->fetch_assoc();

if (!$branch) {
    die('Branch not found.');
}

$branch_id = $branch['branch_id'];

// อัปเดตเป้าหมายตามปี, branch_id และเดือน
$update_sql = "UPDATE target SET target_amt = ? WHERE target_year = ? AND branch_id = ? AND target_month = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param('diii', $target_amount, $year, $branch_id, $month);

if ($stmt->execute()) {
    // อัปเดตสำเร็จ เปลี่ยนเส้นทางไปหน้าหลัก
    header("Location: test.php");
    exit();  // ต้องใช้ exit() เพื่อหยุดการทำงานหลังจากเปลี่ยนเส้นทาง
} else {
    echo "Error updating target: " . $conn->error;
}
?>
