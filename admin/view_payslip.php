<?php
// view_payslip.php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php"); exit;
}

// Get the specific salary record ID from the URL
$salary_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($salary_id <= 0) { die("Invalid payslip ID."); }

// --- Fetch the salary record details ---
$salary_record = null;
$sql_salary = "SELECT * FROM staff_salary WHERE id = ?";
if ($stmt = mysqli_prepare($link, $sql_salary)) {
    mysqli_stmt_bind_param($stmt, "i", $salary_id);
    mysqli_stmt_execute($stmt);
    $salary_record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}
if (!$salary_record) { die("Salary record not found."); }

// --- Based on the salary record, fetch the corresponding staff member's details ---
$staff = null;
$table_name = ($salary_record['staff_role'] === 'Principal') ? 'principles' : 'teachers';
$code_column = ($salary_record['staff_role'] === 'Principal') ? 'principle_code' : 'teacher_code';
$sql_staff = "SELECT full_name, {$code_column} AS staff_code FROM {$table_name} WHERE id = ?";
if ($stmt_staff = mysqli_prepare($link, $sql_staff)) {
    mysqli_stmt_bind_param($stmt_staff, "i", $salary_record['staff_id']);
    mysqli_stmt_execute($stmt_staff);
    $staff = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_staff));
    mysqli_stmt_close($stmt_staff);
}
if (!$staff) { die("Staff member associated with this salary not found."); }

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - <?php echo htmlspecialchars($staff['full_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6;  }
        .payslip-container { width: 800px; margin: 30px auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e0e0e0; }
        .payslip-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1a2c5a; padding-bottom: 20px; margin-bottom: 20px; }
        .school-info h1 { margin: 0; font-size: 28px; color: #1a2c5a; font-weight: 700; }
        .school-info p { margin: 2px 0; color: #555; font-size: 14px; }
        .payslip-title h2 { margin: 0; color: #1a2c5a; font-size: 24px; font-weight: 700; text-align: right; }
        .payslip-title p { margin: 4px 0; font-size: 14px; color: #333; text-align: right; }
        .staff-details { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
        .detail-item p { margin: 5px 0; font-size: 15px; }
        .detail-item strong { color: #555; display: inline-block; width: 120px; }
        .salary-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .salary-table thead { background-color: #1a2c5a; color: #fff; }
        .salary-table th, .salary-table td { padding: 12px 15px; border: 1px solid #ddd; }
        .salary-table td:nth-child(2) { text-align: right; }
        .summary { margin-left: auto; width: 350px; }
        .summary p { display: flex; justify-content: space-between; margin: 10px 0; font-size: 16px; }
        .net-payable { border-top: 2px solid #1a2c5a; padding-top: 10px; font-weight: bold; font-size: 18px; }
        .footer-note { text-align: center; margin-top: 40px; color: #888; font-size: 12px; }
        .print-download-buttons { text-align: center; margin-top: 20px; }
        .print-download-buttons button { padding: 10px 20px; border: none; border-radius: 5px; background-color: #007bff; color: white; font-size: 16px; cursor: pointer; margin: 0 10px; }
        
        @media print {
            body { background-color: #fff; padding: 0; }
            .payslip-container { width: 100%; margin: 0; box-shadow: none; border: none; }
            .print-download-buttons { display: none; }
        }
    </style>
</head>
<body>
    <div class="payslip-container" id="payslip">
        <div class="payslip-header">
            <div class="school-info">
                <h1>Your School Name</h1>
                <p>123 School Address, City, State, Pincode</p>
                <p>Phone: +91 12345 67890</p>
            </div>
            <div class="payslip-title">
                <h2>Salary Slip</h2>
                <p>For <?php echo htmlspecialchars($salary_record['salary_month'] . ' ' . $salary_record['salary_year']); ?></p>
            </div>
        </div>

        <div class="staff-details">
            <div class="detail-item">
                <p><strong>Employee Name:</strong> <?php echo htmlspecialchars($staff['full_name']); ?></p>
                <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($staff['staff_code']); ?></p>
            </div>
            <div class="detail-item" style="text-align: right;">
                <p><strong>Payment Date:</strong> <?php echo date("d F, Y", strtotime($salary_record['paid_at'])); ?></p>
                <p><strong>Status:</strong> <span style="color: #28a745; font-weight: bold;">PAID</span></p>
            </div>
        </div>

        <table class="salary-table">
            <thead>
                <tr>
                    <th>Earnings</th>
                    <th>Amount (₹)</th>
                    <th>Deductions</th>
                    <th>Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Base Salary</td>
                    <td><?php echo number_format($salary_record['base_salary'], 2); ?></td>
                    <td>Professional Tax</td>
                    <td>0.00</td>
                </tr>
                <tr>
                    <td>Bonuses / Incentives</td>
                    <td><?php echo number_format($salary_record['bonuses'], 2); ?></td>
                    <td>Provident Fund (PF)</td>
                    <td>0.00</td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td>Other Deductions</td>
                    <td><?php echo number_format($salary_record['deductions'], 2); ?></td>
                </tr>
                <tr style="font-weight: bold; background: #f8f9fa;">
                    <td>Total Earnings</td>
                    <td><?php echo number_format($salary_record['base_salary'] + $salary_record['bonuses'], 2); ?></td>
                    <td>Total Deductions</td>
                    <td><?php echo number_format($salary_record['deductions'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="summary">
            <p class="net-payable">
                <span>Net Salary Paid:</span> 
                <span>₹<?php echo number_format($salary_record['net_payable'], 2); ?></span>
            </p>
        </div>
        
        <div class="footer-note">
            <p>This is a computer-generated payslip and does not require a signature.</p>
        </div>
    </div>
    
    <div class="print-download-buttons">
        <button onclick="window.print()">Print Payslip</button>
        <button onclick="downloadPayslip()">Download as PDF</button>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPayslip() {
            const element = document.getElementById('payslip');
            const opt = {
                margin: 0.5,
                filename: 'payslip_<?php echo str_replace(' ', '_', $staff['full_name']) . '_' . $salary_record['salary_month'] . '_' . $salary_record['salary_year']; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>