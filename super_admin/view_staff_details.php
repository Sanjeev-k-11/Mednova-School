<?php
// view_staff_details.php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// --- Get ID and Role from URL and Validate ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = isset($_GET['role']) ? $_GET['role'] : '';

if ($id <= 0 || !in_array($role, ['Principal', 'Teacher'])) {
    $_SESSION['message'] = "Invalid request. Staff member not specified.";
    $_SESSION['message_type'] = "danger";
    header("location: view_all_staff.php");
    exit;
}

// --- Determine table and code column based on role ---
$table_name = ($role === 'Principal') ? 'principles' : 'teachers';
$code_column = ($role === 'Principal') ? 'principle_code' : 'teacher_code';

// --- Fetch all staff details ---
$staff = null;
$sql = "SELECT s.*, v.van_number 
        FROM $table_name s 
        LEFT JOIN vans v ON s.van_id = v.id 
        WHERE s.id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $staff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

if (!$staff) {
    $_SESSION['message'] = "Staff member not found.";
    $_SESSION['message_type'] = "danger";
    header("location: view_all_staff.php");
    exit;
}

// --- NEW: Fetch Salary History for this staff member ---
$salary_history = [];
$sql_salary = "SELECT * FROM staff_salary WHERE staff_id = ? AND staff_role = ? ORDER BY salary_year DESC, FIELD(salary_month, 'December','November','October','September','August','July','June','May','April','March','February','January')";
if ($stmt_salary = mysqli_prepare($link, $sql_salary)) {
    mysqli_stmt_bind_param($stmt_salary, "is", $id, $role);
    mysqli_stmt_execute($stmt_salary);
    $result_salary = mysqli_stmt_get_result($stmt_salary);
    while ($row = mysqli_fetch_assoc($result_salary)) {
        $salary_history[] = $row;
    }
    mysqli_stmt_close($stmt_salary);
}

mysqli_close($link);
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Details - <?php echo htmlspecialchars($staff['full_name']); ?></title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;  background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 900px; margin: auto; margin-bottom: 100px; margin-top: 100px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 40px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        .profile-header { display: flex; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 30px; }
        .profile-img-large { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: 0 0 15px rgba(0,0,0,0.2); margin-right: 25px; }
        .profile-info h2 { margin: 0; color: #1a2c5a; font-size: 2em; }
        .profile-info p { margin: 5px 0 0; color: #555; font-size: 1.1em; }
        .status-badge { display: inline-block; margin-top: 10px; padding: 6px 12px; border-radius: 15px; color: white; font-weight: bold; }
        .status-active { background-color: #28a745; }
        .status-blocked { background-color: #dc3545; }
        .section-title { color: #1a2c5a; border-bottom: 2px solid #23a6d5; padding-bottom: 5px; margin-top: 30px; margin-bottom: 20px; font-size: 1.4em; }
        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .detail-item { background-color: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #e73c7e; }
        .detail-item strong { display: block; color: #495057; margin-bottom: 5px; font-size: 0.9em; text-transform: uppercase; }
        .detail-item span { color: #212529; font-size: 1.1em; }
        .btn-container { margin-top: 40px; text-align: right; }
        .btn-action { display: inline-block; padding: 10px 25px; border-radius: 5px; color: white; text-decoration: none; font-size: 16px; border: none; cursor: pointer; }
        .btn-back { background-color: #6c757d; }
        .btn-edit { background-color: #ffc107; color: #212529; margin-left: 10px; }

        /* Salary Table Styles */
        .salary-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .salary-table th, .salary-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .salary-table thead th { background-color: #1a2c5a; color: white; }
        .status-generated { background-color: #ffc107; color: #212529; padding: 5px 10px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .status-paid { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .btn-pay { background-color: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; }
        .btn-payslip { background-color: #17a2b8; color: white; padding: 6px 12px; border-radius: 5px; text-decoration: none; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
    </style>
</head>
<body>
<div class="container">
    <div class="profile-header">
        <img src="<?php echo htmlspecialchars($staff['image_url'] ?? '../assets/images/default_avatar.png'); ?>" alt="Profile Photo" class="profile-img-large">
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($staff['full_name']); ?></h2>
            <p><?php echo htmlspecialchars($role); ?> (Staff Code: <?php echo htmlspecialchars($staff[$code_column]); ?>)</p>
            <?php
                $is_blocked = $staff['is_blocked'];
                $status_text = $is_blocked ? 'Blocked' : 'Active';
                $status_class = $is_blocked ? 'status-blocked' : 'status-active';
            ?>
            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
        </div>
    </div>

    <h3 class="section-title">Personal & Contact Information</h3>
    <div class="details-grid">
        <div class="detail-item"><strong>Email:</strong> <span><?php echo htmlspecialchars($staff['email']); ?></span></div>
        <div class="detail-item"><strong>Phone:</strong> <span><?php echo htmlspecialchars($staff['phone_number'] ?? 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Gender:</strong> <span><?php echo htmlspecialchars($staff['gender'] ?? 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Date of Birth:</strong> <span><?php echo !empty($staff['dob']) ? date("F j, Y", strtotime($staff['dob'])) : 'N/A'; ?></span></div>
        <div class="detail-item"><strong>Blood Group:</strong> <span><?php echo htmlspecialchars($staff['blood_group'] ?? 'N/A'); ?></span></div>
        <div class="detail-item" style="grid-column: 1 / -1;"><strong>Address:</strong> <span><?php echo htmlspecialchars($staff['address'] . ', ' . $staff['pincode']); ?></span></div>
    </div>

    <h3 class="section-title">Professional Information</h3>
    <div class="details-grid">
        <div class="detail-item"><strong>Qualification:</strong> <span><?php echo htmlspecialchars($staff['qualification'] ?? 'N/A'); ?></span></div>
        <?php if ($role === 'Teacher'): ?>
        <div class="detail-item"><strong>Subject Taught:</strong> <span><?php echo htmlspecialchars($staff['subject_taught'] ?? 'N/A'); ?></span></div>
        <?php endif; ?>
        <div class="detail-item"><strong>Date of Joining:</strong> <span><?php echo !empty($staff['date_of_joining']) ? date("F j, Y", strtotime($staff['date_of_joining'])) : 'N/A'; ?></span></div>
        <div class="detail-item"><strong>Years of Experience:</strong> <span><?php echo htmlspecialchars($staff['years_of_experience']); ?> Years</span></div>
        <div class="detail-item"><strong>Defined Salary:</strong> <span><?php echo htmlspecialchars($staff['salary'] ? '₹' . number_format($staff['salary'], 2) : 'N/A'); ?></span></div>
        <div class="detail-item">
            <strong>Van Service:</strong> 
            <span><?php echo $staff['van_service_taken'] ? 'Yes (' . htmlspecialchars($staff['van_number'] ?? 'Not assigned') . ')' : 'No'; ?></span>
        </div>
    </div>

    <!-- ======================= -->
    <!-- Salary History Section  -->
    <!-- ======================= -->
    <h3 class="section-title">Salary History</h3>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <div style="overflow-x:auto;">
        <table class="salary-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Year</th>
                    <th>Base Salary</th>
                    <th>Net Payable</th>
                    <th>Status</th>
                    <th>Paid On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($salary_history)): ?>
                    <tr><td colspan="7" style="text-align:center;">No salary records found. Please generate salaries first.</td></tr>
                <?php else: ?>
                    <?php foreach ($salary_history as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['salary_month']); ?></td>
                        <td><?php echo htmlspecialchars($record['salary_year']); ?></td>
                        <td>₹<?php echo number_format($record['base_salary'], 2); ?></td>
                        <td><strong>₹<?php echo number_format($record['net_payable'], 2); ?></strong></td>
                        <td><span class="status-<?php echo strtolower($record['status']); ?>"><?php echo $record['status']; ?></span></td>
                        <td><?php echo $record['paid_at'] ? date("M j, Y", strtotime($record['paid_at'])) : 'N/A'; ?></td>
                        <td>
                            <?php if ($record['status'] == 'Generated'): ?>
                                <form action="process_salary_payment.php" method="POST" onsubmit="return confirm('Mark this salary as PAID?');">
                                    <input type="hidden" name="salary_id" value="<?php echo $record['id']; ?>">
                                    <input type="hidden" name="staff_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="role" value="<?php echo $role; ?>">
                                    <button type="submit" class="btn-pay">Mark as Paid</button>
                                </form>
                            <?php else: ?>
                                <a href="view_payslip.php?id=<?php echo $record['id']; ?>" class="btn-payslip" target="_blank">View Payslip</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="btn-container">
        <a href="view_all_staff.php" class="btn-action btn-back">← Back to List</a>
        <a href="edit_staff.php?role=<?php echo $role; ?>&id=<?php echo $id; ?>" class="btn-action btn-edit">Edit Details</a>
    </div>
</div>
</body>
</html>
<?php 
require_once './admin_footer.php';
?>