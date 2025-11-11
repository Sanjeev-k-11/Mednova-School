<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

$student_id = $_SESSION['id'] ?? null;

if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Authentication Error!</strong>
            <span class='block sm:inline'> Your student ID is missing or invalid in the session. Please log in again.</span>
          </div>";
    require_once "./student_footer.php"; // Include footer before exiting
    if($link) mysqli_close($link);
    exit();
}

$student_info = [];
$outstanding_fees = [];
$payment_records = []; // Renamed from payment_history

// --- DATA FETCHING FOR DISPLAY ---
// Student Info
$sql_student_info = "
    SELECT
        s.first_name, s.last_name, s.middle_name,
        c.class_name, c.section_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
";
if ($stmt = mysqli_prepare($link, $sql_student_info)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $student_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Prepare Student Info Error: " . mysqli_error($link));
    // Handle error more gracefully if needed
}

// Outstanding Fees
$sql_outstanding_fees = "
    SELECT
        sf.id,
        sf.fee_type_name,
        sf.due_date,
        sf.amount_due,
        sf.amount_paid,
        (sf.amount_due - sf.amount_paid) AS remaining_due,
        sf.status
    FROM student_fees sf
    WHERE sf.student_id = ? AND sf.status IN ('Unpaid', 'Partially Paid')
    ORDER BY sf.due_date ASC;
";
if ($stmt = mysqli_prepare($link, $sql_outstanding_fees)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $outstanding_fees = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Prepare Outstanding Fees Error: " . mysqli_error($link));
}

// Payment Records (similar to history, but renamed for "receipts" context)
$sql_payment_records = "
    SELECT
        sf.id,
        sf.fee_type_name,
        sf.amount_due,
        sf.amount_paid,
        (sf.amount_due - sf.amount_paid) AS balance_after_payment,
        sf.status,
        sf.paid_at,
        sf.assigned_at
    FROM student_fees sf
    WHERE sf.student_id = ? AND sf.status IN ('Paid', 'Partially Paid', 'Waived')
    ORDER BY sf.paid_at DESC, sf.assigned_at DESC;
";
if ($stmt = mysqli_prepare($link, $sql_payment_records)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $payment_records = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Prepare Payment Records Error: " . mysqli_error($link));
}

mysqli_close($link);

// --- NEW LOGIC: GROUP PAYMENTS BY MONTH AND YEAR ---
$grouped_records = [];
foreach ($payment_records as $record) {
    if ($record['paid_at'] === null) {
        continue; // Skip records with no paid date
    }
    $date = new DateTime($record['paid_at']);
    $month_year = $date->format('F Y'); // e.g., "September 2025"
    $month_year_sortable = $date->format('Y-m'); // e.g., "2025-09" for sorting

    if (!isset($grouped_records[$month_year_sortable])) {
        $grouped_records[$month_year_sortable] = [
            'display_date' => $month_year,
            'total_paid' => 0,
            'payments' => []
        ];
    }
    $grouped_records[$month_year_sortable]['total_paid'] += $record['amount_paid'];
    $grouped_records[$month_year_sortable]['payments'][] = $record;
}

// Sort the grouped records by the sortable key (most recent first)
krsort($grouped_records);

require_once "./student_header.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Payments - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .dashboard-container {
            min-height: calc(100vh - 80px); /* Adjust based on header/footer height */
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
<!-- student_header.php content usually goes here -->

<div class="dashboard-container p-4 sm:p-6">
    <!-- Main Header Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">My Fee Status</h1>
        <p class="text-gray-600">Welcome, <span class="font-semibold text-indigo-600">
            <?php echo htmlspecialchars($student_info['first_name'] . ' ' . ($student_info['middle_name'] ? $student_info['middle_name'] . ' ' : '') . $student_info['last_name']); ?>
        </span>! View your school fees and payment records here.</p>
        <p class="text-gray-600 text-sm">Class: <span class="font-medium"><?php echo htmlspecialchars($student_info['class_name'] . ' - ' . $student_info['section_name']); ?></span></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Outstanding Fees Card -->
        <div class="bg-white rounded-xl shadow-lg p-6 h-full flex flex-col">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2 text-red-500"></i> Outstanding Fees
            </h2>
            <?php if (empty($outstanding_fees)): ?>
                <div class="text-center p-8 bg-green-50 border border-green-200 rounded-lg text-green-800 flex-grow flex flex-col items-center justify-center">
                    <i class="fas fa-check-circle fa-4x mb-4 text-green-400"></i>
                    <p class="text-xl font-semibold mb-2">You have no outstanding fees!</p>
                    <p class="text-lg">All clear, keep up the great work!</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Due</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($outstanding_fees as $fee): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($fee['fee_type_name']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo date('M d, Y', strtotime($fee['due_date'])); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">₹<?php echo number_format($fee['amount_due'], 2); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-red-600">₹<?php echo number_format($fee['remaining_due'], 2); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($fee['status'] == 'Unpaid') ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo htmlspecialchars($fee['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Fee Receipts Card (Formerly Payment History) -->
        <div class="bg-white rounded-xl shadow-lg p-6 h-full flex flex-col">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-receipt mr-2 text-indigo-500"></i> Fee Receipts
            </h2>
            <?php if (empty($grouped_records)): ?>
                <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 flex-grow flex flex-col items-center justify-center">
                    <i class="fas fa-history fa-4x mb-4 text-gray-400"></i>
                    <p class="text-xl font-semibold mb-2">No payment records found!</p>
                    <p class="text-lg">Your past fee payments will appear here.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee Type(s)</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($grouped_records as $group): ?>
                                <!-- Group Header Row -->
                                <tr class="bg-gray-100 hover:bg-gray-200 cursor-pointer group-header">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-gray-900" colspan="2">
                                        <i class="fas fa-caret-down mr-2 text-gray-600 transition-transform transform"></i>
                                        <?php echo htmlspecialchars($group['display_date']); ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-indigo-600">
                                        ₹<?php echo number_format($group['total_paid'], 2); ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Total
                                        </span>
                                    </td>
                                </tr>
                                <!-- Individual Payments (hidden by default) -->
                                <?php foreach ($group['payments'] as $record): ?>
                                    <tr class="payment-detail hidden hover:bg-gray-50">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 pl-8"><?php echo $record['paid_at'] ? date('M d, Y', strtotime($record['paid_at'])) : 'N/A'; ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($record['fee_type_name']); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">₹<?php echo number_format($record['amount_paid'], 2); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($record['status'] == 'Paid') ? 'bg-green-100 text-green-800' : (($record['status'] == 'Partially Paid') ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                                                <?php echo htmlspecialchars($record['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // JavaScript to handle the collapsible group rows
    document.querySelectorAll('.group-header').forEach(header => {
        header.addEventListener('click', () => {
            const icon = header.querySelector('i');
            icon.classList.toggle('rotate-90');
            
            // Find all payment details for this group
            let nextRow = header.nextElementSibling;
            while (nextRow && nextRow.classList.contains('payment-detail')) {
                nextRow.classList.toggle('hidden');
                nextRow = nextRow.nextElementSibling;
            }
        });
    });
</script>

<!-- student_footer.php content usually goes here -->
<?php require_once "./student_footer.php"; ?>

</body>
</html>
