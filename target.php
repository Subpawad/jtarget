<?php
include 'db_connection.php'; // Include your database connection file


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $year = isset($_POST['year']) ? $_POST['year'] : null;
    $branch_id = isset($_POST['branch_id']) ? $_POST['branch_id'] : null;

    if ($year && $branch_id) {
        // เริ่มต้นการทำธุรกรรม
        $conn->begin_transaction();

        try {
            // ลบข้อมูลเก่าหากมีอยู่
            $delete_sql = "DELETE FROM target WHERE target_year = ? AND branch_id = ?";
            if ($stmt = $conn->prepare($delete_sql)) {
                $stmt->bind_param("ii", $year, $branch_id);
                $stmt->execute();
                $stmt->close();
            } else {
                throw new Exception("Failed to prepare statement for deleting old targets: " . $conn->error);
            }

            // รับข้อมูลเป้าหมายรายเดือนจากฟอร์ม
            $total_target = 0; // สำหรับคำนวณยอดรวมปี
            $month_count = 0; // จำนวนเดือนที่กรอกข้อมูล

            for ($month = 1; $month <= 12; $month++) {
                $monthly_target = isset($_POST["target_$month"]) ? $_POST["target_$month"] : 0;
                $monthly_target = is_numeric($monthly_target) ? round(floatval($monthly_target)) : 0; // ปัดเศษและจัดการค่า

                if ($monthly_target > 0) {
                    $total_target += $monthly_target;
                    $month_count++;
                }

                // เพิ่มข้อมูลเป้าหมายใหม่หรืออัปเดตหากข้อมูลมีอยู่แล้ว
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

            // คำนวณเป้าหมายรายเดือนสำหรับเดือนที่ไม่ได้ระบุ
            if ($month_count > 0) {
                $average_target = round($total_target / $month_count); // เป้าหมายเฉลี่ยรายเดือน
                for ($month = 1; $month <= 12; $month++) {
                    $current_target = isset($_POST["target_$month"]) ? $_POST["target_$month"] : 0;
                    if ($current_target <= 0) {
                        // อัปเดตเป้าหมายสำหรับเดือนที่ไม่ได้ระบุ
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
    }
}

// ฟังก์ชันดึงข้อมูล areazone
function getAreazones($conn)
{
    return mysqli_query($conn, "SELECT * FROM areazone");
}

// ฟังก์ชันดึงข้อมูล branch ตาม areazone
function getBranches($conn, $areazone_id = null)
{
    if ($areazone_id) {
        return mysqli_query($conn, "SELECT * FROM branch WHERE areazone_id = '$areazone_id'");
    } else {
        return mysqli_query($conn, "SELECT * FROM branch WHERE 1=0"); // ไม่มีข้อมูลเมื่อไม่มี areazone_id
    }
}

// ดึงข้อมูลปี
$yearsQuery = "SELECT DISTINCT target_year FROM target ORDER BY target_year DESC";
$yearsResult = $conn->query($yearsQuery);
$years = [];
if ($yearsResult->num_rows > 0) {
    while ($row = $yearsResult->fetch_assoc()) {
        $years[] = $row['target_year'];
    }
}

// ดึงข้อมูลสาขา
$branchesQuery = "SELECT DISTINCT branch_name FROM branch ORDER BY branch_name";
$branchesResult = $conn->query($branchesQuery);

$branches = [];
if ($branchesResult && $branchesResult->num_rows > 0) {
    while ($row = $branchesResult->fetch_assoc()) {
        $branches[] = $row['branch_name']; 
    }
}



// รับค่าฟิลเตอร์ปีและสาขา
$year_filter = isset($_POST['year_filter']) ? $_POST['year_filter'] : '';
$branch_filter = isset($_POST['branch_filter']) ? $_POST['branch_filter'] : '';



// Query สำหรับดึงข้อมูลยอดขาย
$sql = "SELECT t.target_year, b.branch_name, t.target_month, t.target_amt AS target_amount
        FROM target t
        JOIN branch b ON t.branch_id = b.branch_id
        ORDER BY t.target_year, t.target_month, b.branch_name";
$result = $conn->query($sql);

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

        .button-Gu {
            margin: 2px;
            padding: 5px;
            background-color: #4CAF50; /* สีเขียวสำหรับปุ่มหลัก */
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 40%;
            text-align: center; /* จัดข้อความให้กลาง */
        }

        .button-table {
            margin: 2px;
            padding: 5px;
            background-color: #b7b6b3; /* สีเขียวสำหรับปุ่มหลัก */
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 40%;
            text-align: center; /* จัดข้อความให้กลาง */
        }

        .button-main {
            padding: 10px;
            background-color: #4CAF50; /* สีเขียวสำหรับปุ่มหลัก */
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 48%;
            text-align: center; /* จัดข้อความให้กลาง */
        }

        .button-main:hover {
            background-color: #009933; /* สีเขียวเข้มเมื่อเอาเมาส์ไปชี้ */
        }

        /* ปุ่มยกเลิก */
        .button-cancel {
            padding: 10px;
            background-color: #f44336; /* สีแดงสำหรับปุ่มยกเลิก */
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 48%;
            text-align: center; /* จัดข้อความให้กลาง */
        }

        .button-cancel:hover {
            background-color: #d32f2f; /* สีแดงเข้มเมื่อเอาเมาส์ไปชี้ */
        }

        .hidden {
            display: none;
        }

        .total {
            margin-top: 20px;
            font-size: 18px;
            font-weight: bold;
        }
        .modal-overlay {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            overflow: hidden; /* Prevent scrolling */
        }

        /* Modal Content */
        .modal-content {
            background-color: #fefefe;
            margin: auto; /* Center it horizontally */
            padding: 20px;
            border: 1px solid #888;
            width: 80%; /* Could be more or less, depending on screen size */
            max-width: 600px; /* Optional: Adjust width as needed */
            position: fixed; /* Fix position */
            top: 50%; /* Center vertically */
            left: 50%; /* Center horizontally */
            transform: translate(-50%, -50%); /* Center the modal */
        }

        /* Close Button */
        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close-button:hover,
        .close-button:focus {
            background-color: #d32f2f;
            text-decoration: none;
            cursor: pointer;
        }
        /* ซ่อนตารางโดยเริ่มต้น */
        #salesTable {
            display: none;
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
        <a href="index.php" class="menu-item">หน้าแรก</a>
        <a href="Add information.php" class="menu-item">เพิ่มข้อมูล</a>
        <a href="setting.php" class="menu-item">การตั้งค่าสิทธิ์</a>
        <a href="setting2.php" class="menu-item">ตั้งค่า2</a>
        <a href="averaging.php" class="menu-item">การเฉลี่ยยอด</a>
    </div>
    <div class="main-content">
        <div class="container">
            <h1>ตั้งค่าเป้ายอดขาย</h1>

            <!-- Step 1: Method Selection -->
            <div id="method-selection" class="step">
                <h2>เลือกวิธีการตั้งค่าเป้าหมาย</h2>
                <form>
                    <label><input type="radio" name="method" value="manual" checked> กรอกเป้าหมายยอดขาย</label><br>
                    <label><input type="radio" name="method" value="last_year_target"> ใช้เป้าหมายจากปีที่แล้ว</label><br>
                    <label><input type="radio" name="method" value="actual_sales"> คำนวณจากยอดขายจริงของปีที่แล้ว</label><br>
                    <div class="button-group">
                        <button type="button" class="button-Gu" onclick="goToNextStep()">ถัดไป</button>
                    </div>
                </form>
            </div>

            

            <!-- Step 2: Data Entry for Selected Method -->
            <div id="data-entry" class="step hidden">
                <h2>กรอกข้อมูล</h2>
                <form id="data-form" method="POST" action="target.php">
                    <label for="year">ปี:</label>
                    <select id="year" name="year">
                        <option value="2024">2024</option>
                        <option value="2025">2025</option>
                        <option value="2026">2026</option>
                    </select><br><br>

                    <label for="areazone">เลือก AreaZone:</label>
                    <select name="areazone_id" id="areazone">
                        <option value="">-- เลือก AreaZone --</option>
                        <?php while ($row = mysqli_fetch_assoc($areazones)) { ?>
                            <option value="<?php echo $row['areazone_id']; ?>">
                                <?php echo $row['areazone_name']; ?>
                            </option>
                        <?php } ?>
                    </select>

                    <label for="branch_id">เลือกสาขา:</label>
                    <select id="branch_id" name="branch_id">
                        <option value="">-- เลือกสาขา --</option>
                    </select>

                    <div id="manual-entry" class="hidden">
                        <h3>กรอกเป้าหมายยอดขาย</h3>
                        <label for="annual_target">เป้าหมายยอดขายรายปี:</label>
                        <input type="number" id="annual_target" name="annual_target" step="0.01" value="10,000" oninput="updateMonthlyTargets()"><br><br>

                        <h3>เป้าหมายยอดขายรายเดือน:</h3>
                        <table>
                            <tr>
                                <th>เดือน</th>
                                <th>เป้าหมายยอดขาย</th>
                            </tr>
                            <tr>
                                <td>มกราคม</td>
                                <td><input type="number" id="target_1" name="target_1" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>กุมภาพันธ์</td>
                                <td><input type="number" id="target_2" name="target_2" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>มีนาคม</td>
                                <td><input type="number" id="target_3" name="target_3" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>เมษายน</td>
                                <td><input type="number" id="target_4" name="target_4" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>พฤษภาคม</td>
                                <td><input type="number" id="target_5" name="target_5" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>มิถุนายน</td>
                                <td><input type="number" id="target_6" name="target_6" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>กรกฎาคม</td>
                                <td><input type="number" id="target_7" name="target_7" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>สิงหาคม</td>
                                <td><input type="number" id="target_8" name="target_8" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>กันยายน</td>
                                <td><input type="number" id="target_9" name="target_9" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>ตุลาคม</td>
                                <td><input type="number" id="target_10" name="target_10" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>พฤศจิกายน</td>
                                <td><input type="number" id="target_11" name="target_11" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                            <tr>
                                <td>ธันวาคม</td>
                                <td><input type="number" id="target_12" name="target_12" step="0.01" oninput="updateTotalTarget()" disabled></td>
                            </tr>
                        </table>
                        <div class="total">
                            <p>ยอดรวมเป้าหมายยอดขายรายปี: <span id="total-target">0</span></p>
                        </div>
                    </div>

                    <!-- Additional sections for other methods can be added here -->
                    <div class="button-group">
                        <button type="button" class="button-cancel" onclick="goBack()">ยกเลิก</button>
                        <button type="button" class="button-main" onclick="validateAndProceed()">บันทึกข้อมูล</button>
                    </div>
                </form>
                <!-- Modal Overlay -->
                    <div id="modal-overlay" class="modal-overlay">
                        <div class="modal-content">
                            <p>คุณแน่ใจหรือไม่ว่าต้องการบันทึกข้อมูลนี้?</p>
                            <button type="button" class="button-cancel" onclick="closeModal()">ยกเลิก</button>
                            <button type="button" class="button-main" onclick="confirmAction()">ตกลง</button>
                        </div>
                    </div>
                </div>
                <button class="button-table" onclick="toggleTable()">แสดง/ซ่อน ตารางยอดขาย</button>
                <div id="filterContainer">
                <label for="yearFilter">ปี:</label>
                        <select id="yearFilter" name="year_filter" onchange="filterTable()">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($years as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $year == $year_filter ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="branchFilter">สาขา:</label>
                            <select id="branchFilter" name="branch_filter" onchange="filterTable()">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch['branch_name']); ?>" <?php echo $branch['branch_name'] == $branch_filter ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>


                    <label for="monthFilter">เดือน:</label>
                    <select id="monthFilter" onchange="filterTable()">
                        <option value="">ทั้งหมด</option>
                        <option value="1">มกราคม</option>
                        <option value="2">กุมภาพันธ์</option>
                        <option value="3">มีนาคม</option>
                        <option value="4">เมษายน</option>
                        <option value="5">พฤษภาคม</option>
                        <option value="6">มิถุนายน</option>
                        <option value="7">กรกฎาคม</option>
                        <option value="8">สิงหาคม</option>
                        <option value="9">กันยายน</option>
                        <option value="10">ตุลาคม</option>
                        <option value="11">พฤศจิกายน</option>
                        <option value="12">ธันวาคม</option>
                    </select>
                </div>
                <table id="salesTable" border="1">
                    <thead>
                        <tr>
                            <th>ปี</th>
                            <th>สาขา</th>
                            <th>เดือน</th>
                            <th>เป้ายอดขาย</th>
                            <th>แก้ไข</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['target_year']); ?></td>
                        <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['target_month']); ?></td>
                        <td><?php echo htmlspecialchars($row['target_amount']); ?></td>
                        <td><a href="update.php?year=<?php echo urlencode($row['target_year']); ?>&branch_name=<?php echo urlencode($row['branch_name']); ?>&month=<?php echo urlencode($row['target_month']); ?>">แก้ไข</a></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            
            </div>
        </div>
    </div>

    <script>
     function filterTable() {
    var yearFilter = document.getElementById('yearFilter').value;
    var branchFilter = document.getElementById('branchFilter').value;
    var monthFilter = document.getElementById('monthFilter').value;

    var table = document.getElementById('salesTable');
    var rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        var year = cells[0].textContent || cells[0].innerText;
        var branch = cells[1].textContent || cells[1].innerText;
        var month = cells[2].textContent || cells[2].innerText;

        var showRow = true;

        if (yearFilter && year !== yearFilter) {
            showRow = false;
        }
        if (branchFilter && branch !== branchFilter) {
            showRow = false;
        }
        if (monthFilter && month !== monthFilter) {
            showRow = false;
        }

        rows[i].style.display = showRow ? '' : 'none';
    }
}



        function goToNextStep() {
            document.getElementById('method-selection').classList.add('hidden');
            document.getElementById('data-entry').classList.remove('hidden');

            const selectedMethod = document.querySelector('input[name="method"]:checked').value;

            document.getElementById('manual-entry').classList.toggle('hidden', selectedMethod !== 'manual');
        }

        function toggleTable() {
            var table = document.getElementById('salesTable');
            // เช็คสถานะการแสดงผลปัจจุบันและเปลี่ยนแปลง
            if (table.style.display === 'none' || table.style.display === '') {
                table.style.display = 'table';
            } else {
                table.style.display = 'none';
            }
        }

        // ฟังก์ชันสำหรับยืนยันการลบข้อมูล
        function confirmDelete(url) {
            if (confirm('คุณแน่ใจว่าต้องการลบข้อมูลนี้?')) {
                window.location.href = url;
            }
        }

        function updateMonthlyTargets() {
            const annualTarget = parseFloat(document.getElementById('annual_target').value) || 0;
            const monthlyTargets = annualTarget / 12;
            const remainder = annualTarget % 12;

            for (let month = 1; month <= 12; month++) {
                const input = document.getElementById(`target_${month}`);
                if (input) {
                    const value = Math.floor((monthlyTargets + (month <= remainder ? 0.01 : 0)) * 100) / 100;
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

        function showModal() {
            document.getElementById('modal-overlay').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('modal-overlay').style.display = 'none';
        }

        function confirmAction() {
            document.getElementById('data-form').submit();
            closeModal();
        }

        function validateAndProceed() {
            const areazone = document.getElementById('areazone').value;
            const branch = document.getElementById('branch_id').value;
            const annual_target = document.getElementById('annual_target').value;

            if (areazone === "") {
                alert("กรุณาเลือก AreaZone");
                return;
            }

            if (branch === "") {
                alert("กรุณาเลือกสาขา");
                return;
            }

            if (annual_target === "") {
                alert("กรุณาเลือกระบุเป้ายอดขาย!");
                return;
            }
    
            // ตรวจสอบว่าแต่ละเดือนมีค่าไม่เป็นศูนย์
            let allMonthsValid = true;
            for (let month = 1; month <= 12; month++) {
                const input = document.getElementById(`target_${month}`);
                if (input) {
                    const value = parseFloat(input.value) || 0;
                    if (value <= 0) {
                        alert(`กรุณากรอกเป้าหมายยอดขายที่มากกว่า 0 สำหรับเดือน ${monthToStr(month)}`);
                        allMonthsValid = false;
                        break;
                    }
                }
            }

            if (!allMonthsValid) {
                return;
            }
            showModal();
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
    </script>
</body>
</html>