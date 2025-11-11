<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}

// --- Get data from URL and Validate ---
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$month_name = isset($_GET['month']) ? $_GET['month'] : '';

if ($student_id <= 0 || $year <= 0 || empty($month_name)) {
    die("Invalid receipt details provided.");
}

// Convert month name to number for DB query
$month_number = date('m', strtotime($month_name));

// --- Fetch Student's Main Details ---
$student = null;
$sql_student = "SELECT s.first_name, s.middle_name, s.last_name, s.father_name, s.roll_number, c.class_name, c.section_name 
                FROM students s 
                LEFT JOIN classes c ON s.class_id = c.id 
                WHERE s.id = ?";
if ($stmt_student = mysqli_prepare($link, $sql_student)) {
    mysqli_stmt_bind_param($stmt_student, "i", $student_id);
    mysqli_stmt_execute($stmt_student);
    $student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student));
    mysqli_stmt_close($stmt_student);
}
if (!$student) { die("Student not found."); }

// --- Fetch all fee items for this student for the given month/year ---
$fee_items = [];
$totals = ['due' => 0, 'paid' => 0];
$sql_fees = "SELECT * FROM student_fees 
             WHERE student_id = ? 
             AND YEAR(due_date) = ? 
             AND MONTH(due_date) = ?";
if ($stmt_fees = mysqli_prepare($link, $sql_fees)) {
    mysqli_stmt_bind_param($stmt_fees, "iii", $student_id, $year, $month_number);
    mysqli_stmt_execute($stmt_fees);
    $result = mysqli_stmt_get_result($stmt_fees);
    while ($row = mysqli_fetch_assoc($result)) {
        $fee_items[] = $row;
        $totals['due'] += $row['amount_due'];
        $totals['paid'] += $row['amount_paid'];
    }
    mysqli_stmt_close($stmt_fees);
}

$balance_due = $totals['due'] - $totals['paid'];
$due_date = date('Y-m-d', strtotime("last day of $month_name $year"));

// You can create a more sophisticated receipt number if you wish
$receipt_no = str_pad($student_id, 4, '0', STR_PAD_LEFT) . $year . str_pad($month_number, 2, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Advice - <?php echo htmlspecialchars($student['first_name'] . ' - ' . $month_name . ' ' . $year); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
        }
        .receipt-container {
            width: 800px;
            margin: 30px auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e0e0e0;
        }
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .school-info {
            display: flex;
            align-items: center;
        }
        .school-logo {
            width: 60px;
            height: 60px;
            margin-right: 15px;
        }
        .school-details h1 {
            margin: 0;
            font-size: 24px;
            color: #1a2c5a;
            font-weight: 700;
        }
        .school-details p {
            margin: 2px 0;
            color: #555;
            font-size: 14px;
        }
        .payment-advice {
            text-align: right;
        }
        .payment-advice h2 {
            margin: 0;
            color: #d9534f;
            font-size: 22px;
            font-weight: 700;
        }
        .payment-advice p {
            margin: 4px 0;
            font-size: 14px;
            color: #333;
        }
        .billing-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .bill-to h3 {
            margin: 0 0 5px 0;
            font-size: 12px;
            color: #888;
            font-weight: 500;
        }
        .bill-to p {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1a2c5a;
        }
        .bill-to span {
            font-size: 14px;
            color: #555;
            display: block;
        }
        .fee-period {
            text-align: right;
        }
        .fee-period h3 {
            margin: 0 0 5px 0;
            font-size: 12px;
            color: #888;
            font-weight: 500;
        }
        .fee-period p {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1a2c5a;
        }
        .fee-period span {
            font-size: 14px;
            color: #d9534f;
            display: block;
        }
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .fee-table thead {
            background-color: #f8f9fa;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }
        .fee-table th, .fee-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .fee-table td {
            font-size: 15px;
        }
        .fee-table th:nth-child(2), .fee-table td:nth-child(2) {
            text-align: right;
        }
        .summary {
            margin-left: auto;
            width: 300px;
            text-align: right;
        }
        .summary p {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            font-size: 15px;
        }
        .summary .balance-due {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            font-weight: 700;
            font-size: 16px;
        }
        .footer-note {
            text-align: center;
            margin-top: 40px;
            border-top: 1px solid #e0e0e0;
            padding-top: 20px;
        }
        .footer-note h4 {
            color: #d9534f;
            margin: 0 0 5px 0;
        }
        .footer-note p {
            margin: 0;
            color: #555;
            font-size: 14px;
        }
        .print-download-buttons {
            text-align: center;
            margin-top: 20px;
        }
        .print-download-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            background-color: #007bff;
            color: white;
            font-size: 16px;
            cursor: pointer;
            margin: 0 10px;
        }

        /* Print-specific styles */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .receipt-container {
                width: 100%;
                margin: 0;
                box-shadow: none;
                border: none;
            }
            .print-download-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="receipt-container" id="receipt">
        <div class="receipt-header">
            <div class="school-info">
                <img src="../path/to/your/school_logo.png" alt="School Logo" class="school-logo">
                <div class="school-details">
                    <h1>Basic Public School</h1>
                    <p>Baliya Chowk Raiyam Madhubani, Bihar, 847211</p>
                    <p>Phone: +91 8877780197</p>
                </div>
            </div>
            <div class="payment-advice">
                <h2>PAYMENT ADVICE</h2>
                <p>Receipt No: <?php echo htmlspecialchars($receipt_no); ?></p>
                <p>Date: <?php echo date("d F, Y"); ?></p>
            </div>
        </div>

        <div class="billing-details">
            <div class="bill-to">
                <h3>BILL TO</h3>
                <p><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                <span>S/O <?php echo htmlspecialchars($student['father_name']); ?></span>
                <span>Class: <?php echo htmlspecialchars($student['class_name'] . ' | Roll: ' . $student['roll_number']); ?></span>
            </div>
            <div class="fee-period">
                <h3>FEE PERIOD</h3>
                <p><?php echo htmlspecialchars($month_name . ' ' . $year); ?></p>
                <span>Due Date: <?php echo date("d F, Y", strtotime($due_date)); ?></span>
            </div>
        </div>

        <table class="fee-table">
            <thead>
                <tr>
                    <th>PARTICULARS</th>
                    <th>AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <?php $item_num = 1; foreach ($fee_items as $item): ?>
                <tr>
                    <td><?php echo $item_num++; ?>. <?php echo htmlspecialchars($item['fee_type_name']); ?></td>
                    <td>₹<?php echo number_format($item['amount_due'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary">
            <p><span>Subtotal:</span> <span>₹<?php echo number_format($totals['due'], 2); ?></span></p>
            <p><span>Paid:</span> <span>₹<?php echo number_format($totals['paid'], 2); ?></span></p>
            <?php if ($balance_due > 0): ?>
                <p class="balance-due"><span>Balance Due:</span> <span>₹<?php echo number_format($balance_due, 2); ?></span></p>
            <?php endif; ?>
        </div>
        
        <div class="footer-note">
            <?php if ($balance_due > 0): ?>
                <h4>Payment Required</h4>
                <p>Please contact the school office to complete the payment.</p>
            <?php else: ?>
                <h4>Payment Complete</h4>
                <p>Thank you for your timely payment.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="print-download-buttons">
        <button onclick="window.print()">Print Receipt</button>
        <button onclick="downloadReceipt()">Download as PDF</button>
    </div>

    <!-- Include html2pdf library for PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
    <script>
        function downloadReceipt() {
            const element = document.getElementById('receipt');
            const opt = {
                margin:       0.5,
                filename:     'receipt_<?php echo $receipt_no; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            // New Promise-based usage:
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>