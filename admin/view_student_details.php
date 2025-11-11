<?php
session_start();
require_once "../database/config.php";

// --- Auth Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php"); exit;
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("location: view_students.php"); exit; }

// --- Fetch Student's Main Details (with all joins for profile) ---
$student = null;
$sql_student = "SELECT 
                    s.*, 
                    c.class_name, c.section_name,
                    v.van_number,
                    blocker.full_name AS blocked_by_admin_name,
                    unblocker.full_name AS unblocked_by_admin_name 
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN vans v ON s.van_id = v.id
                LEFT JOIN principles AS blocker ON s.blocked_by_admin_id = blocker.id
                LEFT JOIN principles AS unblocker ON s.unblocked_by_admin_id = unblocker.id
                WHERE s.id = ?";

if ($stmt = mysqli_prepare($link, $sql_student)) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}
if (!$student) { header("location: view_students.php"); exit; }

// ==========================================================
// --- DYNAMIC FEE PIVOT LOGIC (Same as before) ---
// ==========================================================
$all_fee_types = [];
$fee_type_aliases = [];
$sql_fetch_types = "SELECT fee_type_name FROM fee_types ORDER BY fee_type_name ASC";
if ($result = mysqli_query($link, $sql_fetch_types)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_fee_types[] = $row['fee_type_name'];
        $fee_type_aliases[preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', strtolower($row['fee_type_name'])))] = $row['fee_type_name'];
    }
}

$dynamic_case_statements = "";
foreach ($fee_type_aliases as $alias => $original_name) {
    $safe_original_name = mysqli_real_escape_string($link, $original_name);
    $dynamic_case_statements .= ", SUM(CASE WHEN fee_type_name = '{$safe_original_name}' THEN amount_due ELSE 0 END) AS `{$alias}`";
}

$monthly_fee_records = [];
$sql_fees_pivot = "SELECT 
                MONTHNAME(due_date) as month,
                YEAR(due_date) as year
                {$dynamic_case_statements}
                , SUM(amount_due) as total_due,
                SUM(amount_paid) as total_paid,
                MAX(paid_at) as last_payment_date,
                GROUP_CONCAT(notes SEPARATOR '; ') as all_notes
            FROM student_fees 
            WHERE student_id = ?
            GROUP BY YEAR(due_date), MONTH(due_date)
            ORDER BY year DESC, MONTH(due_date) DESC";

if ($stmt_fees = mysqli_prepare($link, $sql_fees_pivot)) {
    mysqli_stmt_bind_param($stmt_fees, "i", $id);
    mysqli_stmt_execute($stmt_fees);
    $result_fees = mysqli_stmt_get_result($stmt_fees);
    while ($row = mysqli_fetch_assoc($result_fees)) {
        $monthly_fee_records[] = $row;
    }
    mysqli_stmt_close($stmt_fees);
}

mysqli_close($link);
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Details - <?php echo htmlspecialchars($student['first_name']); ?></title>
    <style>
        /* (Your CSS is good, no changes needed here. Copied for completeness) */
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;  background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1400px; margin: auto; margin-bottom: 100px; margin-top: 100px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 40px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        .profile-header { display: flex; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 30px; }
        .profile-img-large { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: 0 0 15px rgba(0,0,0,0.2); margin-right: 25px; }
        .profile-info h2 { margin: 0; color: #1a2c5a; font-size: 2em; }
        .profile-info p { margin: 5px 0 0; color: #555; font-size: 1.1em; }
        .status-badge { display: inline-block; margin-top: 10px; padding: 6px 12px; border-radius: 15px; color: white; font-weight: bold; }
        .status-active { background-color: #28a745; }
        .status-blocked { background-color: #dc3545; }
        .section-title { color: #1a2c5a; border-bottom: 2px solid #23a6d5; padding-bottom: 5px; margin-top: 40px; margin-bottom: 20px; font-size: 1.4em; }
        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .detail-item { background-color: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #e73c7e; }
        .audit-item { border-left-color: #ffc107; }
        .detail-item strong { display: block; color: #495057; margin-bottom: 5px; font-size: 0.9em; text-transform: uppercase; }
        .detail-item span { color: #212529; font-size: 1.1em; word-wrap: break-word; }
        .fee-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .fee-table th, .fee-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; white-space: nowrap; }
        .fee-table thead th { background-color: #f8f9fa; color: #333; font-weight: 600; }
        .status-due { color: #dc3545; font-weight: bold; }
        .status-paid { color: #28a745; font-weight: bold; }
        .status-partially-paid { color: #fd7e14; font-weight: bold; }
        .actions-links a { display: block; margin-bottom: 4px; color: #007bff; text-decoration: none; }
        .actions-links a:hover { text-decoration: underline; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 10px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        .modal-header h3 { margin: 0; color: #1a2c5a; }
        .close-button { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .modal-body .form-group { margin-bottom: 15px; }
        .modal-body label { font-weight: 600; }
        .modal-body input, .modal-body textarea { width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc; box-sizing: border-box; }
        .btn { padding: 12px; width: 100%; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
        .btn-container { margin-top: 40px; text-align: right; }
        .btn-action { display: inline-block; padding: 10px 25px; border-radius: 5px; color: white; text-decoration: none; font-size: 16px; border: none; }
        .btn-back { background-color: #6c757d; }
        .btn-edit { background-color: #ffc107; color: #212529; margin-left: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="profile-header">
        <img src="<?php echo htmlspecialchars($student['image_url'] ?? '../assets/images/default_avatar.png'); ?>" alt="Profile" class="profile-img-large">
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></h2>
            <p>Registration No: <?php echo htmlspecialchars($student['registration_number']); ?></p>
            <span class="status-badge status-<?php echo strtolower($student['status']); ?>"><?php echo htmlspecialchars($student['status']); ?></span>
        </div>
    </div>
    
    <!-- Academic & School Details -->
    <h3 class="section-title">Academic & School Details</h3>
    <div class="details-grid">
        <div class="detail-item"><strong>Current Class:</strong> <span><?php echo htmlspecialchars(($student['class_name'] ?? 'N/A') . ' - ' . ($student['section_name'] ?? 'N/A')); ?></span></div>
        <div class="detail-item"><strong>Roll Number:</strong> <span><?php echo htmlspecialchars($student['roll_number'] ?? 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Admission Date:</strong> <span><?php echo !empty($student['admission_date']) ? date("F j, Y", strtotime($student['admission_date'])) : 'N/A'; ?></span></div>
        <div class="detail-item"><strong>Van Service:</strong> <span><?php echo $student['van_service_taken'] ? 'Yes (' . htmlspecialchars($student['van_number'] ?? 'Not Assigned') . ')' : 'No'; ?></span></div>
        <div class="detail-item"><strong>Previous Class:</strong> <span><?php echo htmlspecialchars($student['previous_class'] ?? 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Previous School:</strong> <span><?php echo htmlspecialchars($student['previous_school'] ?? 'N/A'); ?></span></div>
    </div>

    <!-- Personal Details -->
    <h3 class="section-title">Personal Details</h3>
    <div class="details-grid">
        <div class="detail-item"><strong>Date of Birth:</strong> <span><?php echo !empty($student['dob']) ? date("F j, Y", strtotime($student['dob'])) : 'N/A'; ?></span></div>
        <div class="detail-item"><strong>Gender:</strong> <span><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Blood Group:</strong> <span><?php echo htmlspecialchars($student['blood_group'] ?? 'N/A'); ?></span></div>
    </div>
    
    <!-- Parent / Guardian Details -->
    <h3 class="section-title">Parent / Guardian Details</h3>
    <div class="details-grid">
        <div class="detail-item"><strong>Father's Name:</strong> <span><?php echo htmlspecialchars($student['father_name'] ?? 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Mother's Name:</strong> <span><?php echo htmlspecialchars($student['mother_name'] ?? 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Parent's Phone:</strong> <span><?php echo htmlspecialchars($student['parent_phone_number'] ?? 'N/A'); ?></span></div>
        <div class="detail-item"><strong>Father's Occupation:</strong> <span><?php echo htmlspecialchars($student['father_occupation'] ?? 'N/A'); ?></span></div>
    </div>

    <!-- ======================= -->
    <!-- DYNAMIC FEE SECTION -->
    <!-- ======================= -->
    <h3 class="section-title">Monthly Fee Records</h3>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div style="overflow-x:auto;">
        <table class="fee-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Year</th>
                    <?php foreach ($all_fee_types as $fee_name): ?>
                        <th><?php echo htmlspecialchars($fee_name); ?></th>
                    <?php endforeach; ?>
                    <th>Total Due</th>
                    <th>Amount Paid</th>
                    <th>Amount Remaining</th>
                    <th>Status</th>
                    <th>Payment Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($monthly_fee_records)): ?>
                    <tr><td colspan="<?php echo count($all_fee_types) + 7; ?>" style="text-align:center;">No fee records found. Assign fees first.</td></tr>
                <?php else: ?>
                    <?php foreach ($monthly_fee_records as $record): 
                        $remaining = $record['total_due'] - $record['total_paid'];
                        $status = '';
                        if ($record['total_paid'] >= $record['total_due']) {
                            $status = '<span class="status-paid">Paid</span>';
                        } elseif ($record['total_paid'] > 0) {
                            $status = '<span class="status-partially-paid">Partial</span>';
                        } else {
                            $status = '<span class="status-due">Due</span>';
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['month']); ?></td>
                        <td><?php echo htmlspecialchars($record['year']); ?></td>
                        <?php foreach ($fee_type_aliases as $alias => $original_name): ?>
                            <td>₹<?php echo number_format($record[$alias], 2); ?></td>
                        <?php endforeach; ?>
                        <td><strong>₹<?php echo number_format($record['total_due'], 2); ?></strong></td>
                        <td>₹<?php echo number_format($record['total_paid'], 2); ?></td>
                        <td><strong>₹<?php echo number_format($remaining, 2); ?></strong></td>
                        <td><?php echo $status; ?></td>
                        <td><?php echo $record['last_payment_date'] ? date("M j, Y", strtotime($record['last_payment_date'])) : 'N/A'; ?></td>
                        <td class="actions-links">
                            <?php if ($remaining > 0): ?>
                            <a href="#" class="pay-btn" 
                                data-studentid="<?php echo $id; ?>" 
                                data-month="<?php echo $record['month']; ?>" 
                                data-year="<?php echo $record['year']; ?>" 
                                data-balance="<?php echo $remaining; ?>">Mark Paid</a>
                            <?php endif; ?>
                            <a href="view_receipt.php?student_id=<?php echo $id; ?>&year=<?php echo $record['year']; ?>&month=<?php echo $record['month']; ?>">View Receipt</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Account Status History (Audit Trail) -->
    <?php if (!empty($student['block_reason']) || !empty($student['unblock_reason'])): ?>
    <h3 class="section-title">Account Status History</h3>
    <div class="details-grid">
        <?php if ($student['status'] == 'Blocked' && !empty($student['block_reason'])): ?>
            <div class="detail-item audit-item"><strong>Last Block Reason:</strong> <span><?php echo nl2br(htmlspecialchars($student['block_reason'])); ?></span></div>
            <div class="detail-item audit-item"><strong>Blocked By:</strong> <span><?php echo htmlspecialchars($student['blocked_by_admin_name'] ?? 'Admin ID: '.$student['blocked_by_admin_id']); ?></span></div>
            <div class="detail-item audit-item"><strong>Blocked At:</strong> <span><?php echo !empty($student['blocked_at']) ? date("F j, Y, g:i a", strtotime($student['blocked_at'])) : 'N/A'; ?></span></div>
        <?php elseif ($student['status'] == 'Active' && !empty($student['unblock_reason'])): ?>
             <div class="detail-item audit-item"><strong>Last Unblock Reason:</strong> <span><?php echo nl2br(htmlspecialchars($student['unblock_reason'])); ?></span></div>
            <div class="detail-item audit-item"><strong>Unblocked By:</strong> <span><?php echo htmlspecialchars($student['unblocked_by_admin_name'] ?? 'Admin ID: '.$student['unblocked_by_admin_id']); ?></span></div>
            <div class="detail-item audit-item"><strong>Unblocked At:</strong> <span><?php echo !empty($student['unblocked_at']) ? date("F j, Y, g:i a", strtotime($student['unblocked_at'])) : 'N/A'; ?></span></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="btn-container">
        <a href="view_students.php" class="btn-action btn-back">← Back to List</a>
        <a href="edit_student.php?id=<?php echo $id; ?>" class="btn-action btn-edit">Edit Details</a>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <!-- Modal content is the same as before -->
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="paymentModalTitle">Record Payment</h3>
            <span class="close-button">&times;</span>
        </div>
        <div class="modal-body">
            <form action="process_monthly_payment.php" method="POST">
                <input type="hidden" name="student_id" id="modalStudentId">
                <input type="hidden" name="month" id="modalMonth">
                <input type="hidden" name="year" id="modalYear">
                <div class="form-group">
                    <label>Amount to Pay (Remaining: ₹<span id="balanceAmount"></span>)</label>
                    <input type="number" name="amount_paid" id="modalAmountPaid" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn">Submit Payment</button>
            </form>
        </div>
    </div>
</div>

<script>
    // JavaScript is the same as before
    const modal = document.getElementById('paymentModal');
    const closeButton = document.querySelector('#paymentModal .close-button');
    const payButtons = document.querySelectorAll('.pay-btn');
    payButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const studentId = this.dataset.studentid;
            const month = this.dataset.month;
            const year = this.dataset.year;
            const balance = this.dataset.balance;
            document.getElementById('modalStudentId').value = studentId;
            document.getElementById('modalMonth').value = month;
            document.getElementById('modalYear').value = year;
            document.getElementById('modalAmountPaid').value = parseFloat(balance).toFixed(2);
            document.getElementById('modalAmountPaid').max = balance;
            document.getElementById('balanceAmount').innerText = parseFloat(balance).toFixed(2);
            document.getElementById('paymentModalTitle').innerText = 'Record Payment for ' + month + ' ' + year;
            modal.style.display = 'block';
        });
    });
    closeButton.addEventListener('click', () => modal.style.display = 'none');
    window.addEventListener('click', (event) => {
        if (event.target == modal) modal.style.display = 'none';
    });
</script>

</body>
</html>
<?php 
require_once './admin_footer.php';
?>