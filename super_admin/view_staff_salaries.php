<?php
// view_staff_salaries.php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// --- Fetch All Staff Salary Data ---
$all_salaries = [];
$sql = "
    SELECT 
        ss.id, ss.staff_id, ss.staff_role, ss.salary_month, ss.salary_year, ss.net_payable, ss.status, ss.paid_at,
        CASE
            WHEN ss.staff_role = 'Principal' THEN p.full_name
            WHEN ss.staff_role = 'Teacher' THEN t.full_name
        END AS full_name,
        CASE
            WHEN ss.staff_role = 'Principal' THEN p.principle_code
            WHEN ss.staff_role = 'Teacher' THEN t.teacher_code
        END AS staff_code
    FROM 
        staff_salary ss
    LEFT JOIN principles p ON ss.staff_id = p.id AND ss.staff_role = 'Principal'
    LEFT JOIN teachers t ON ss.staff_id = t.id AND ss.staff_role = 'Teacher'
    ORDER BY ss.salary_year DESC, FIELD(ss.salary_month, 'December','November','October','September','August','July','June','May','April','March','February','January') DESC, full_name ASC
";

if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_salaries[] = $row;
    }
} else {
    die("Error executing query: " . mysqli_error($link));
}
mysqli_close($link);

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Staff Salaries</title>
    <style>
        /* (Your existing CSS is good, just adding the payslip button style) */
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1400px; margin: auto; margin-bottom: 100px; margin-top: 100px; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border: 1px solid rgba(255, 255, 255, 0.18); }
        h2 { text-align: center; color: #1e2a4c; font-weight: 600; margin-bottom: 30px; }
        .table-container { overflow-x: auto; }
        .salary-table { width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 10px; overflow: hidden; }
        .salary-table th, .salary-table td { padding: 15px 20px; text-align: left; vertical-align: middle; border-bottom: 1px solid #eef2f7; }
        .salary-table thead th { background-color: #1e2a4c; color: white; font-weight: 600; font-size: 14px; text-transform: uppercase; }
        .salary-table tbody tr:hover { background-color: #f8f9fa; }
        .status-badge { padding: 6px 12px; border-radius: 20px; color: white; font-size: 12px; font-weight: 600; }
        .status-generated { background-color: #ffc107; color: #212529;} /* Note: Corrected class name from status-Generated */
        .status-paid { background-color: #28a745; }
        .actions-cell { white-space: nowrap; }
        .btn-action { display: inline-block; padding: 8px 15px; margin-right: 5px; border-radius: 5px; color: white; text-decoration: none; font-size: 14px; font-weight: 500; border: none; cursor: pointer; transition: transform 0.2s ease; }
        .btn-action:hover { transform: scale(1.05); }
        .btn-pay { background-color: #007bff; }
        /* NEW BUTTON STYLE */
        .btn-payslip { background-color: #17a2b8; } /* Teal */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
    </style>
</head>
<body>

<div class="container">
    <h2>All Staff Salary Records</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <table class="salary-table">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Staff Code</th>
                    <th>Role</th>
                    <th>Month/Year</th>
                    <th>Net Payable</th>
                    <th>Status</th>
                    <th>Paid At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_salaries)): ?>
                    <tr><td colspan="8" style="text-align:center;">No salary records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($all_salaries as $salary): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($salary['full_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($salary['staff_code'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($salary['staff_role']); ?></td>
                            <td><?php echo htmlspecialchars($salary['salary_month']) . ', ' . htmlspecialchars($salary['salary_year']); ?></td>
                            <td>₹<?php echo number_format($salary['net_payable'], 2); ?></td>
                            <td>
                                <!-- Note: Corrected status class name to be lowercase for CSS matching -->
                                <span class="status-badge status-<?php echo strtolower($salary['status']); ?>"><?php echo htmlspecialchars($salary['status']); ?></span>
                            </td>
                            <td><?php echo $salary['paid_at'] ? date('M d, Y', strtotime($salary['paid_at'])) : 'N/A'; ?></td>
                            <td class="actions-cell">
                                <?php if ($salary['status'] === 'Generated'): ?>
                                    <!-- This form now correctly targets the payment script -->
                                    <form method="POST" action="process_salary_payment.php" style="display:inline;" onsubmit="return confirm('Mark this salary as PAID?');">
                                        <input type="hidden" name="salary_id" value="<?php echo $salary['id']; ?>">
                                        <!-- Redirect info -->
                                        <input type="hidden" name="redirect_to" value="view_staff_salaries.php">
                                        <button type="submit" class="btn-action btn-pay">Mark as Paid</button>
                                    </form>
                                <?php elseif ($salary['status'] === 'Paid'): ?>
                                    <!-- This is the new link to the payslip -->
                                    <a href="view_payslip.php?id=<?php echo $salary['id']; ?>" class="btn-action btn-payslip" target="_blank">View Payslip</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
<?php 
require_once './admin_footer.php';
?>