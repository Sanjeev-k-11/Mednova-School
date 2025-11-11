<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}
$admin_id = $_SESSION['super_admin_id'];

// --- Data Fetching ---
$all_students = [];
$student_details = null;
$student_class_fees = [];
$student_van_fee = null;
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Fetch all active students for the dropdown
$sql_students = "SELECT id, first_name, last_name, registration_number FROM students WHERE status = 'Active' ORDER BY first_name, last_name";
if ($res = mysqli_query($link, $sql_students)) {
    while ($row = mysqli_fetch_assoc($res)) $all_students[] = $row;
}

// If a student is selected, fetch their details and applicable fees
if ($selected_student_id > 0) {
    // 1. Get student's class and van details
    $sql_student_info = "SELECT s.class_id, s.van_service_taken, s.van_id, v.fee_amount 
                         FROM students s
                         LEFT JOIN vans v ON s.van_id = v.id
                         WHERE s.id = ?";
    if ($stmt = mysqli_prepare($link, $sql_student_info)) {
        mysqli_stmt_bind_param($stmt, "i", $selected_student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student_details = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }

    if ($student_details) {
        // 2. Get all fees assigned to the student's class
        $sql_class_fees = "SELECT cf.amount, cf.frequency, ft.fee_type_name 
                           FROM class_fees cf
                           JOIN fee_types ft ON cf.fee_type_id = ft.id
                           WHERE cf.class_id = ?";
        if ($stmt = mysqli_prepare($link, $sql_class_fees)) {
            mysqli_stmt_bind_param($stmt, "i", $student_details['class_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) $student_class_fees[] = $row;
            mysqli_stmt_close($stmt);
        }

        // 3. Get van fee if applicable
        if ($student_details['van_service_taken'] && $student_details['fee_amount'] > 0) {
            $student_van_fee = ['fee_type_name' => 'Transport Fee', 'amount' => $student_details['fee_amount'], 'frequency' => 'Monthly'];
        }
    }
}

// --- ACTION HANDLER: Process Fee Assignment on POST ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id_to_assign = (int)$_POST['student_id'];
    $due_date = $_POST['due_date'];
    $fees_to_assign = $_POST['fees']; // Array of fees to assign

    if (empty($due_date)) {
        $_SESSION['message'] = "Due Date is required to assign fees.";
        $_SESSION['message_type'] = "danger";
    } elseif (!empty($fees_to_assign)) {
        $assigned_count = 0;
        foreach ($fees_to_assign as $fee_key => $fee_data) {
            $fee_type_name = $fee_data['name'];
            $amount_due = (float)$fee_data['amount'];
            
            // PREVENT DUPLICATES: Check if this exact fee has been assigned for this month already
            $sql_check = "SELECT id FROM student_fees WHERE student_id = ? AND fee_type_name = ? AND DATE_FORMAT(due_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')";
            if($stmt_check = mysqli_prepare($link, $sql_check)){
                mysqli_stmt_bind_param($stmt_check, "iss", $student_id_to_assign, $fee_type_name, $due_date);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if(mysqli_stmt_num_rows($stmt_check) == 0){ // Only insert if not found
                    // Insert the fee record
                    $sql_insert = "INSERT INTO student_fees (student_id, fee_type_name, due_date, amount_due, assigned_by_admin_id) VALUES (?, ?, ?, ?, ?)";
                    if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                        mysqli_stmt_bind_param($stmt_insert, "issdi", $student_id_to_assign, $fee_type_name, $due_date, $amount_due, $admin_id);
                        if (mysqli_stmt_execute($stmt_insert)) {
                            $assigned_count++;
                        }
                        mysqli_stmt_close($stmt_insert);
                    }
                }
                mysqli_stmt_close($stmt_check);
            }
        }
        if ($assigned_count > 0) {
            $_SESSION['message'] = "$assigned_count fees have been successfully assigned for the selected period.";
            $_SESSION['message_type'] = "success";
        } else {
             $_SESSION['message'] = "No new fees were assigned. They may have already been assigned for this period.";
            $_SESSION['message_type'] = "warning";
        }
    }
    header("location: assign_student_fees.php?student_id=" . $student_id_to_assign);
    exit;
}
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Student Fees</title>
    <!-- (Use the same CSS as your other pages) -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;  background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 900px; margin: auto; margin-top: 100px; margin-bottom: 100px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 30px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        h2 { text-align: center; color: #1a2c5a; margin-top: 0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        select, input[type=date] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .data-table thead th { background-color: #1a2c5a; color: white; }
        .total-row td { font-weight: bold; border-top: 2px solid #333; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(-45deg, #007bff, #00bfff, #8a2be2, #007bff); background-size: 400% 400%; animation: gradientAnimation 8s ease infinite; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
        .alert-danger { color: #721c24; background-color: #f8d7da; }
        .alert-warning { color: #856404; background-color: #fff3cd; }
    </style>
</head>
<body>
<div class="container">
    <h2>Assign Fees to Student</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <form method="GET" action="assign_student_fees.php">
        <div class="form-group">
            <label for="student_id">Select a Student</label>
            <select name="student_id" id="student_id" onchange="this.form.submit()">
                <option value="">-- Choose a Student --</option>
                <?php foreach ($all_students as $student): ?>
                    <option value="<?php echo $student['id']; ?>" <?php if ($selected_student_id == $student['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['registration_number'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($selected_student_id > 0 && $student_details): ?>
        <hr style="margin: 30px 0;">
        <h3>Fee Structure to be Assigned</h3>
        <p>This is the fee structure that will be assigned. Uncheck any fees you don't want to assign for this period.</p>

        <form method="POST" action="assign_student_fees.php">
            <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
            <div class="form-group" style="max-width: 300px;">
                <label for="due_date">Due Date for this Assignment <span style="color:red">*</span></label>
                <input type="date" name="due_date" id="due_date" value="<?php echo date('Y-m-d', strtotime('next month')); ?>" required>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Assign?</th>
                        <th>Fee Type</th>
                        <th>Frequency</th>
                        <th>Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_amount = 0;
                    $combined_fees = $student_class_fees;
                    if ($student_van_fee) {
                        $combined_fees[] = $student_van_fee;
                    }
                    ?>
                    <?php foreach ($combined_fees as $index => $fee): 
                        $total_amount += (float)$fee['amount'];
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="fees[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($fee['fee_type_name']); ?>" checked>
                            <input type="hidden" name="fees[<?php echo $index; ?>][amount]" value="<?php echo htmlspecialchars($fee['amount']); ?>">
                        </td>
                        <td><?php echo htmlspecialchars($fee['fee_type_name']); ?></td>
                        <td><?php echo htmlspecialchars($fee['frequency']); ?></td>
                        <td><?php echo number_format($fee['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Total Amount to Assign:</td>
                        <td><?php echo number_format($total_amount, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="form-group" style="margin-top: 20px;">
                <button type="submit" class="btn">Assign Checked Fees</button>
            </div>
        </form>
    <?php elseif($selected_student_id > 0): ?>
         <div class="alert alert-warning">Could not find fee details for the selected student. Please ensure their class is set up correctly in "Manage Class Fees".</div>
    <?php endif; ?>
</div>
</body>
</html>
<?php require_once './admin_footer.php'; ?>