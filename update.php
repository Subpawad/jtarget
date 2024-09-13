<?php
include 'db_connection.php';

// รับค่าพารามิเตอร์จาก URL
$year = isset($_GET['year']) ? $_GET['year'] : null;
$branch_name = isset($_GET['branch_name']) ? $_GET['branch_name'] : null;
$month = isset($_GET['month']) ? $_GET['month'] : null;

// ตรวจสอบว่าพารามิเตอร์ทั้งหมดมีค่าหรือไม่
if (!$year || !$branch_name || !$month) {
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

// ค้นหาข้อมูลเป้าหมายตามปี, branch_id และเดือน
$sql = "SELECT target_amt FROM target WHERE target_year = ? AND branch_id = ? AND target_month = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $year, $branch_id, $month);
$stmt->execute();
$result = $stmt->get_result();
$target = $result->fetch_assoc();

// ตรวจสอบว่าพบข้อมูลเป้าหมายหรือไม่
if (!$target) {
    die('No target data found.');
}

$target_amount = $target['target_amt'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Target for Month</title>
</head>
<body>
    <h1>Update Target for <?php echo date('F', mktime(0, 0, 0, $month, 1)); ?></h1>
    
    <form action="update_process.php" method="POST">
        <input type="hidden" name="year" value="<?php echo htmlspecialchars($year); ?>">
        <input type="hidden" name="branch_name" value="<?php echo htmlspecialchars($branch_name); ?>">
        <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">

        <label for="target_amount">Target Amount:</label>
        <input type="text" name="target_amount" id="target_amount" value="<?php echo htmlspecialchars($target_amount); ?>">

        <button type="submit">Update</button>
    </form>

</body>
</html>
