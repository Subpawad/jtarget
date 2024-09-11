<?php
include 'db_connection.php'; // รวมไฟล์เชื่อมต่อฐานข้อมูล

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['year']) && isset($_POST['areazone_id']) && isset($_POST['branch_id'])) {
        $year = $_POST['year'];
        $areazone_id = $_POST['areazone_id'];
        $branch_id = $_POST['branch_id'];

        // Query ดึงข้อมูลเป้าหมายยอดขายที่เคยกรอกไว้จากตาราง target
        $sql = "SELECT * FROM target WHERE branch_id = ? AND target_year = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $branch_id, $year);
            $stmt->execute();
            $result = $stmt->get_result();

            // เก็บข้อมูลเป้าหมายยอดขายแต่ละเดือนใน array
            $monthly_targets = [];
            while ($row = $result->fetch_assoc()) {
                $monthly_targets[$row['target_month']] = $row['sales_target'];
            }
            $stmt->close();
        }

        // คำนวณเป้าหมายยอดขายเฉลี่ยหากมีการป้อนเป้าหมายใหม่
        $total_target = 0;
        $month_count = 0;

        for ($month = 1; $month <= 12; $month++) {
            $current_target = isset($_POST["target_$month"]) ? $_POST["target_$month"] : 0;
            if ($current_target > 0) {
                $total_target += $current_target;
                $month_count++;
            }
        }

        try {
            // เริ่มการทำธุรกรรม
            $conn->begin_transaction();

            // หากมีการระบุเป้าหมายรายเดือน
            if ($month_count > 0) {
                $average_target = round($total_target / $month_count); // คำนวณเป้าหมายเฉลี่ย

                for ($month = 1; $month <= 12; $month++) {
                    $current_target = isset($_POST["target_$month"]) ? $_POST["target_$month"] : 0;
                    if ($current_target <= 0) {
                        // อัปเดตเป้าหมายยอดขายเฉลี่ยสำหรับเดือนที่ไม่ได้ป้อนข้อมูล
                        $insert_sql = "INSERT INTO target (target_year, target_month, branch_id, target_amt) 
                                       VALUES (?, ?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE target_amt = VALUES(target_amt)";
                        if ($stmt = $conn->prepare($insert_sql)) {
                            $stmt->bind_param("iiii", $year, $month, $branch_id, $average_target);
                            if (!$stmt->execute()) {
                                throw new Exception("Execute failed: " . $stmt->error);
                            }
                            $stmt->close();
                        } else {
                            throw new Exception("Failed to prepare statement for inserting/updating targets: " . $conn->error);
                        }
                    }
                }
            }

            // ยืนยันการทำธุรกรรม
            $conn->commit();
        } catch (Exception $e) {
            // หากเกิดข้อผิดพลาด ย้อนกลับการทำธุรกรรม
            $conn->rollback();
            echo "Error: " . $e->getMessage();
            exit();
        }
    } else {
        echo "Missing required form data.";
    }
}

// ฟังก์ชันดึงข้อมูล areazone
function getAreazones($conn) {
    return mysqli_query($conn, "SELECT * FROM areazone");
}

// ฟังก์ชันดึงข้อมูล branch ตาม areazone
function getBranches($conn, $areazone_id = null) {
    if ($areazone_id) {
        $stmt = $conn->prepare("SELECT * FROM branch WHERE areazone_id = ?");
        $stmt->bind_param("i", $areazone_id);
        $stmt->execute();
        return $stmt->get_result();
    } else {
        return mysqli_query($conn, "SELECT * FROM branch WHERE 1=0"); // ไม่มีข้อมูลเมื่อไม่มี areazone_id
    }
}



// รับค่า areazone_id จากการส่งฟอร์ม
$selected_areazone_id = isset($_POST['areazone_id']) ? $_POST['areazone_id'] : null;
$branches = getBranches($conn, $selected_areazone_id);
$areazones = getAreazones($conn);
?>


<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มเป้ายอดขาย</title>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            background-color: #f0f0f0;
        }

        .sidebar {
            width: 250px;
            background-color: #0066cc;
            color: white;
            padding: 20px;
            height: 100vh;
        }

        .main-content {
            flex-grow: 1;
            padding: 20px;
        }

        .profile-pic {
            width: 120px;
            height: 120px;
            background-color: white;
            border-radius: 15px;
            margin: 0 auto 20px;
        }

        .user-info {
            text-align: center;
            margin-bottom: 30px;
        }

        .menu-item {
            display: block;
            padding: 12px 15px;
            margin-bottom: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .container {
            width: 70%;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2 {
            text-align: center;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        input[type="number"] {
            width: 100%;
            padding: 5px;
            box-sizing: border-box;
            /* Allow decimal input */
            step: 0.01;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        button {
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 48%;
        }

        button:hover {
            background-color: #45a049;
        }

        .hidden {
            display: none;
        }

        .total {
            margin-top: 20px;
            font-size: 18px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="profile-pic"></div>
        <div class="user-info">
            <h3>ชื่อ - นามสกุล</h3>
            <p>ตำแหน่ง</p>
        </div>
        <a href="index.html" class="menu-item">หน้าแรก</a>
        <a href="Add information.html" class="menu-item">เพิ่มข้อมูล</a>
        <a href="setting.html" class="menu-item">การตั้งค่าสิทธิ์</a>
        <a href="setting2.html" class="menu-item">ตั้งค่า2</a>
        <a href="averaging.html" class="menu-item">การเฉลี่ยยอด</a>
    </div>
    <div class="main-content">
    <div class="container">
        <!-- Step 2: Data Entry for Selected Method -->
        <div id="data-entry">
            <h2>แก้ไขข้อมูล</h2>
            <form method="POST" action="">
                <label for="year">ปี:</label>
                <select id="year" name="year">
                    <option value="2024">2024</option>
                    <option value="2025">2025</option>
                    <option value="2026">2026</option>
                </select><br><br>

                <label for="areazone">เลือก AreaZone:</label>
                <select name="areazone_id" id="areazone">
                    <option value="<?php echo $row['areazone_id']; ?>">-- เลือก AreaZone --</option>
                    <?php while ($row = mysqli_fetch_assoc($areazones)) { ?>
                       
                    <?php } ?>
                </select>

                <label for="branch_id">เลือกสาขา:</label>
                <select id="branch_id" name="branch_id">
                    <option value="<?php echo $row['branch_id']; ?>">-- เลือกสาขา --</option>
                </select>

                <div id="manual-entry">
                    <h3>กรอกเป้าหมายยอดขาย</h3>
                    <label for="annual_target">เป้าหมายยอดขายรายปี:</label>
                    <input type="number" id="annual_target" name="annual_target" step="0.01" oninput="updateMonthlyTargets()"><br><br>

                    <h3>เป้าหมายยอดขายรายเดือน:</h3>
                    <table>
                        <tr>
                            <th>เดือน</th>
                            <th>เป้าหมายยอดขาย</th>
                        </tr>
                        <tr>
                            <td>มกราคม</td>
                            <td><input type="number" id="target_1" name="target_1" step="0.01" oninput="updateTotalTarget()"></td>
                        </tr>
                        <tr>
                            <td>กุมภาพันธ์</td>
                            <td><input type="number" id="target_2" name="target_2" step="0.01" oninput="updateTotalTarget()"></td>
                        </tr>
                        <!-- เพิ่มแถวที่เหลือให้ครบ 12 เดือน -->
                        <tr>
                            <td>ธันวาคม</td>
                            <td><input type="number" id="target_12" name="target_12" step="0.01" oninput="updateTotalTarget()"></td>
                        </tr>
                    </table>
                    <div class="total">
                        <p>ยอดรวมเป้าหมายยอดขายรายปี: <span id="total-target">0</span></p>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit">บันทึก</button>
                    <button type="button" id="back-button" onclick="goBack()">ย้อนกลับ</button>
                </div>
            </form>
        </div>
    </div>
</div>
    <script>
       

    
        function updateMonthlyTargets() {
            const annualTarget = parseFloat(document.getElementById('annual_target').value) || 0;
            const monthlyTargets = annualTarget / 12;
            const remainder = annualTarget % 12;

            for (let month = 1; month <= 12; month++) {
                const input = document.getElementById(`target_${month}`);
                if (input) {
                    // const value = (monthlyTargets);
                    const value = Math.floor((monthlyTargets + (month <= remainder ? +0.01 : 0)) * 100) / 100;
                    input.value = value.toFixed(2);
                    input.disabled = false;
                }
            }
            updateTotalTarget();
        }

        function updateTotalTarget() {
            let totalSum = 0;

            for (let month = 1; month <= 12; month++) {
                const input = document.getElementById(`target_${month}`);
                if (input) {
                    totalSum += parseFloat(input.value) || 0;
                }
            }

            document.getElementById('total-target').textContent = totalSum.toFixed(2);
        }

        function goBack() {
            window.history.back();
        }

        function monthToStr(month) {
            const months = ["jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec"];
            return months[month - 1];
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('areazone').addEventListener('change', function() {
                var areazone_id = this.value;
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'fetch_branches.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        document.getElementById('branch_id').innerHTML = xhr.responseText;
                    }
                };
                xhr.send('areazone_id=' + encodeURIComponent(areazone_id));
            });
        });

        document.getElementById('date').addEventListener('change', function() {
            var dateValue = new Date(this.value);
            var year = dateValue.getFullYear();
            document.getElementById('year').value = year;
        });
    </script>
</body>

</html>