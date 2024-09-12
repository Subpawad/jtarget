<?php
include 'db_connection.php'; // Include your database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $year = isset($_POST['year']) ? intval($_POST['year']) : null;
    $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : null;

    if ($year && $branch_id) {
        // เริ่มต้นการทำธุรกรรม
        $conn->begin_transaction();

        try {
            // ลบข้อมูลเป้าหมายเดิม
            $delete_sql = "DELETE FROM target WHERE target_year = ? AND branch_id = ?";
            if ($stmt = $conn->prepare($delete_sql)) {
                $stmt->bind_param("ii", $year, $branch_id);
                $stmt->execute();
                $stmt->close();
            } else {
                throw new Exception("Failed to prepare statement for deleting old targets: " . $conn->error);
            }

            // รับข้อมูลเป้าหมายรายเดือนจากฟอร์ม
            for ($month = 1; $month <= 12; $month++) {
                $monthly_target = isset($_POST["target_$month"]) ? $_POST["target_$month"] : 0;
                $monthly_target = is_numeric($monthly_target) ? round(floatval($monthly_target)) : 0; // ปัดเศษและจัดการค่า

                if ($monthly_target > 0) {
                    $insert_sql = "INSERT INTO target (target_year, target_month, branch_id, target_amt) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE target_amt = VALUES(target_amt)";
                    if ($stmt = $conn->prepare($insert_sql)) {
                        $stmt->bind_param("iiii", $year, $month, $branch_id, $monthly_target);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute failed: " . $stmt->error);
                        }
                        $stmt->close();
                    } else {
                        throw new Exception("Failed to prepare statement for inserting/updating targets: " . $conn->error);
                    }
                }
            }

            // ยืนยันการทำธุรกรรม
            $conn->commit();
            header("Location: test.php"); // เปลี่ยนเส้นทางกลับไปที่หน้าหลัก
            exit;
        } catch (Exception $e) {
            // หากเกิดข้อผิดพลาด ย้อนกลับการทำธุรกรรม
            $conn->rollback();
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "Invalid parameters.";
    }
} else {
    echo "Invalid request method.";
}
?>
