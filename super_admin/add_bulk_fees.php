<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}
$admin_id = $_SESSION['super_admin_id'];
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- Data Fetching for UI ---
$all_classes = [];
$class_fee_structure = [];
$students_in_selected_class = [];
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$sql_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name, section_name";
if ($res = mysqli_query($link, $sql_classes)) while ($row = mysqli_fetch_assoc($res)) $all_classes[] = $row;

if ($selected_class_id > 0) {
    // Fetch fee structure for the selected class
    $sql_class_fees = "SELECT cf.amount, ft.fee_type_name FROM class_fees cf JOIN fee_types ft ON cf.fee_type_id = ft.id WHERE cf.class_id = ?";
    if ($stmt = mysqli_prepare($link, $sql_class_fees)) {
        mysqli_stmt_bind_param($stmt, "i", $selected_class_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $class_fee_structure[] = $row;
        mysqli_stmt_close($stmt);
    }

    // Fetch students and their scholarships for the selected class
    $sql_students_with_scholarships = "
        SELECT 
            s.id, 
            s.first_name, 
            s.last_name, 
            s.registration_number,
            GROUP_CONCAT(sc.scholarship_name SEPARATOR ', ') as scholarships
        FROM 
            students s
        LEFT JOIN 
            student_scholarships ss ON s.id = ss.student_id
        LEFT JOIN 
            scholarships sc ON ss.scholarship_id = sc.id
        WHERE 
            s.class_id = ? AND s.status = 'Active'
        GROUP BY 
            s.id
        ORDER BY 
            s.first_name, s.last_name";

    if ($stmt_students = mysqli_prepare($link, $sql_students_with_scholarships)) {
        mysqli_stmt_bind_param($stmt_students, "i", $selected_class_id);
        mysqli_stmt_execute($stmt_students);
        $result_students = mysqli_stmt_get_result($stmt_students);
        while ($row = mysqli_fetch_assoc($result_students)) $students_in_selected_class[] = $row;
        mysqli_stmt_close($stmt_students);
    }
}

// --- POST Handling for Bulk Fee Assignment ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class_id_to_assign = (int)$_POST['class_id'];
    $due_date = $_POST['due_date'];
    $assigned_count = 0; $skipped_count = 0; $student_count = 0;

    if (empty($due_date)) {
        $_SESSION['message'] = "A Due Date is required to assign fees.";
        $_SESSION['message_type'] = "danger";
    } else {
        // Fetch all students, fees, and scholarships
        $students_data = [];
        $sql_students = "SELECT s.id, s.van_service_taken, v.fee_amount, ss.scholarship_id, sc.type, sc.value, ft.fee_type_name, cf.amount AS fee_amount_class
                         FROM students s
                         LEFT JOIN vans v ON s.van_id = v.id
                         LEFT JOIN student_scholarships ss ON s.id = ss.student_id
                         LEFT JOIN scholarships sc ON ss.scholarship_id = sc.id AND sc.is_active = 1
                         LEFT JOIN class_fees cf ON s.class_id = cf.class_id
                         LEFT JOIN fee_types ft ON cf.fee_type_id = ft.id
                         WHERE s.class_id = ? AND s.status = 'Active'";

        if ($stmt_data = mysqli_prepare($link, $sql_students)) {
            mysqli_stmt_bind_param($stmt_data, "i", $class_id_to_assign);
            mysqli_stmt_execute($stmt_data);
            $result_data = mysqli_stmt_get_result($stmt_data);

            while ($row = mysqli_fetch_assoc($result_data)) {
                $student_id = $row['id'];
                if (!isset($students_data[$student_id])) {
                    $students_data[$student_id] = [
                        'van_service_taken' => $row['van_service_taken'],
                        'van_fee_amount' => $row['fee_amount'],
                        'fees' => [],
                        'scholarships' => []
                    ];
                }
                if ($row['fee_type_name']) {
                    $students_data[$student_id]['fees'][$row['fee_type_name']] = $row['fee_amount_class'];
                }
                if ($row['scholarship_id']) {
                    $students_data[$student_id]['scholarships'][] = ['type' => $row['type'], 'value' => $row['value']];
                }
            }
            mysqli_stmt_close($stmt_data);
        }
        $student_count = count($students_data);

        if ($student_count > 0) {
            $sql_check = "SELECT id FROM student_fees WHERE student_id = ? AND fee_type_name = ? AND DATE_FORMAT(due_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')";
            $stmt_check = mysqli_prepare($link, $sql_check);
            $sql_insert = "INSERT INTO student_fees (student_id, fee_type_name, due_date, amount_due, assigned_by_admin_id) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($link, $sql_insert);

            foreach ($students_data as $student_id => $student) {
                foreach ($student['fees'] as $fee_type_name => $amount) {
                    $amount_to_assign = (float)$amount;

                    if ($fee_type_name === 'Tuition Fee' && !empty($student['scholarships'])) {
                        foreach ($student['scholarships'] as $scholarship) {
                            if ($scholarship['type'] === 'Percentage') {
                                $reduction = $amount_to_assign * ($scholarship['value'] / 100);
                                $amount_to_assign -= $reduction;
                            } else {
                                $amount_to_assign -= $scholarship['value'];
                            }
                        }
                        $amount_to_assign = max(0, $amount_to_assign);
                    }

                    mysqli_stmt_bind_param($stmt_check, "iss", $student_id, $fee_type_name, $due_date);
                    mysqli_stmt_execute($stmt_check);
                    mysqli_stmt_store_result($stmt_check);

                    if (mysqli_stmt_num_rows($stmt_check) == 0) {
                        mysqli_stmt_bind_param($stmt_insert, "issdi", $student_id, $fee_type_name, $due_date, $amount_to_assign, $admin_id);
                        mysqli_stmt_execute($stmt_insert);
                        $assigned_count++;
                    } else {
                        $skipped_count++;
                    }
                }

                if ($student['van_service_taken'] && $student['van_fee_amount'] > 0) {
                    $transport_fee_name = 'Transport Fee';
                    mysqli_stmt_bind_param($stmt_check, "iss", $student_id, $transport_fee_name, $due_date);
                    mysqli_stmt_execute($stmt_check);
                    mysqli_stmt_store_result($stmt_check);
                    
                    if (mysqli_stmt_num_rows($stmt_check) == 0) {
                        mysqli_stmt_bind_param($stmt_insert, "issdi", $student_id, $transport_fee_name, $due_date, $student['van_fee_amount'], $admin_id);
                        mysqli_stmt_execute($stmt_insert);
                        $assigned_count++;
                    } else {
                        $skipped_count++;
                    }
                }
            }
            mysqli_stmt_close($stmt_check);
            mysqli_stmt_close($stmt_insert);
        }

        $_SESSION['message'] = "Bulk fee assignment complete for $student_count students. New fees assigned: $assigned_count. Records skipped (already exist): $skipped_count.";
        $_SESSION['message_type'] = "success";
    }
    header("location: add_bulk_fees.php?class_id=" . $class_id_to_assign);
    exit;
}

mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Fee Assignment</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;  background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 900px; margin: auto; margin-top: 100px; margin-bottom: 100px;}
        .main-title { color: #fff; text-align: center; font-weight: 600; font-size: 2em; margin-bottom: 20px; }
        .form-container { background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.18); box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        .form-group { margin-bottom: 25px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #1e2a4c; }
        select, input[type=date] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; background-color: rgba(255, 255, 255, 0.9); }
        select:focus, input[type=date]:focus { border-color: #6a82fb; box-shadow: 0 0 0 3px rgba(106, 130, 251, 0.2); outline: none; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(45deg, #dc3545, #fd7e14); transition: transform 0.2s, box-shadow 0.2s; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4); }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eef2f7; }
        .data-table thead th { background-color: #f8f9fa; color: #343a40; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; color: #fff; border: 1px solid rgba(255,255,255,0.3); }
        .alert-success { background-color: rgba(40, 167, 69, 0.8); }
        .alert-danger { background-color: rgba(220, 53, 69, 0.8); }
        .content-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-top: 25px; }
        .grid-card { background: #fff; padding: 25px; border-radius: 10px; }
        .grid-card h4 { margin-top: 0; color: #1e2a4c; }
        .student-list-container { max-height: 280px; overflow-y: auto; padding-right: 10px; }
        .student-list-container::-webkit-scrollbar { width: 6px; }
        .student-list-container::-webkit-scrollbar-track { background: #f1f1f1; }
        .student-list-container::-webkit-scrollbar-thumb { background: #888; border-radius: 3px; }
        .student-list ul { list-style: none; padding: 0; margin: 0; }
        .student-list li { padding: 8px 5px; border-bottom: 1px solid #eef2f7; display: flex; flex-direction: column; }
        .student-list li:last-child { border-bottom: none; }
        .student-list .student-name { font-weight: 600; color: #1e2a4c; }
        .student-list .details { font-size: 0.9em; color: #6c757d; display: block; margin-top: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="main-title">Bulk Fee Assignment to Class</h2>
    
    <div class="form-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="GET" action="add_bulk_fees.php" id="classSelectionForm">
            <div class="form-group">
                <label for="class_id">Step 1: Select a Class</label>
                <select name="class_id" id="class_id" onchange="this.form.submit()">
                    <option value="">-- Choose a Class to Begin --</option>
                    <?php foreach ($all_classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php if ($selected_class_id == $class['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($selected_class_id > 0): ?>
            <hr style="border: 0; border-top: 1px solid #e9ecef; margin: 30px 0;">
            <h3>Step 2: Review Details & Assign</h3>

            <div class="content-grid">
                <div class="grid-card">
                    <h4>Fee Structure</h4>
                    <table class="data-table">
                        <thead><tr><th>Fee Type</th><th>Amount (₹)</th></tr></thead>
                        <tbody>
                            <?php if (empty($class_fee_structure)): ?>
                                <tr><td colspan="2" style="text-align:center;">No fee structure is defined.</td></tr>
                            <?php else: ?>
                                <?php foreach ($class_fee_structure as $fee): ?>
                                    <tr><td><?php echo htmlspecialchars($fee['fee_type_name']); ?></td><td><?php echo number_format($fee['amount'], 2); ?></td></tr>
                                <?php endforeach; ?>
                                <tr><td><em>Transport Fee (if applicable)</em></td><td><em>Varies</em></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="grid-card">
                    <h4>Active Students (<?php echo count($students_in_selected_class); ?>)</h4>
                    <div class="student-list-container">
                        <div class="student-list">
                            <ul>
                                <?php if (empty($students_in_selected_class)): ?>
                                    <li>No active students found in this class.</li>
                                <?php else: ?>
                                    <?php foreach($students_in_selected_class as $student): ?>
                                        <li>
                                            <span class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                            <span class="details">Reg. No: <?php echo htmlspecialchars($student['registration_number']); ?></span>
                                            <?php if ($student['scholarships']): ?>
                                                <span class="details">Scholarships: <strong><?php echo htmlspecialchars($student['scholarships']); ?></strong></span>
                                            <?php else: ?>
                                                <span class="details">No Scholarships</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST" action="add_bulk_fees.php" onsubmit="return confirm('Assign fees to <?php echo count($students_in_selected_class); ?> students? This action cannot be easily undone.');">
                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                <div class="form-group" style="margin-top: 30px;">
                    <label for="due_date">Step 3: Set Due Date for this Assignment</label>
                    <input type="date" name="due_date" id="due_date" value="<?php echo date('Y-m-d', strtotime('first day of next month')); ?>" required>
                </div>
                <button type="submit" class="btn" <?php if (empty($class_fee_structure) && !hasTransportFees($selected_class_id, $link)) echo 'disabled'; ?>>
                    Confirm & Assign Fees to <?php echo count($students_in_selected_class); ?> Students
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php require_once './admin_footer.php'; ?>