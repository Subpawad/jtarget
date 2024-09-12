<?php
include 'db_connection.php'; // Include your database connection file

// ดึงข้อมูลพารามิเตอร์จาก URL
$year = isset($_GET['year']) ? intval($_GET['year']) : null;
$branch_name = isset($_GET['branch_name']) ? $_GET['branch_name'] : null;

if ($year && $branch_name) {
    // ดึง branch_id จาก branch_name
    $branch_sql = "SELECT branch_id FROM branch WHERE branch_name = ?";
    if ($branch_stmt = $conn->prepare($branch_sql)) {
        $branch_stmt->bind_param("s", $branch_name);
        $branch_stmt->execute();
        $branch_result = $branch_stmt->get_result();
        $branch_row = $branch_result->fetch_assoc();
        $branch_id = $branch_row['branch_id'];
        $branch_stmt->close();
    } else {
        echo "Failed to prepare statement for branch.";
        exit();
    }

    // ดึงข้อมูลเป้าหมายจากฐานข้อมูล
    $sql = "SELECT target_month, target_amt FROM target WHERE target_year = ? AND branch_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $year, $branch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $targets = [];
        while ($row = $result->fetch_assoc()) {
            $targets[$row['target_month']] = $row['target_amt'];
        }
        $stmt->close();
    } else {
        echo "Failed to prepare statement for targets.";
        exit();
    }
} else {
    echo "Invalid parameters.";
    exit();
}
// ดึงข้อมูลสาขาจากฐานข้อมูล
$branch_sql = "SELECT branch_id, branch_name FROM branch";
$branch_result = $conn->query($branch_sql);

if ($branch_result === false) {
    echo "Error: " . $conn->error;
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขเป้าหมายยอดขาย</title>
    <style>
        /* เพิ่ม CSS ตามต้องการ */
    </style>
</head>
<body>
    <h1>แก้ไขเป้าหมายยอดขาย</h1>
    <form method="POST" action="update_process.php">
        
       <h1>   <?php echo htmlspecialchars($year); ?></h1>
       <h1>  <?php echo htmlspecialchars($branch_name); ?></h1>
        
    <form action="">
        <h3>เป้าหมายยอดขายรายเดือน:</h3>
        <table>
            <tr>
                <th>เดือน</th>
                <th>เป้าหมายยอดขาย</th>
            </tr>
            <?php for ($month = 1; $month <= 12; $month++): ?>
            <tr>
                <td><?php echo date('F', mktime(0, 0, 0, $month, 10)); ?></td>
                <td><input type="number" name="target_<?php echo $month; ?>" step="0.01" value="<?php echo isset($targets[$month]) ? htmlspecialchars($targets[$month]) : 0; ?>"></td>
            </tr>
            <?php endfor; ?>
        </table>
        <button type="submit">บันทึกการเปลี่ยนแปลง</button>
    </form>
</body>
</html>
