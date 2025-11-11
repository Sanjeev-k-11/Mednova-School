<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./student_header.php";   // Includes student-specific authentication and sidebar

// --- BACKEND LOGIC ---
// Authenticate as Student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php"); // Redirect to login page if not logged in as a student
    exit;
}

$student_id = $_SESSION['id'] ?? null; 

// --- CRITICAL CHECK: Validate student_id from session ---
if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Authentication Error!</strong>
            <span class='block sm:inline'> Your student ID is missing or invalid in the session. Please log in again.</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

// Get current month and year or from GET parameters
$current_month = $_GET['month'] ?? date('m');
$current_year = $_GET['year'] ?? date('Y');

// Ensure month and year are valid
$current_month = sprintf("%02d", max(1, min(12, (int)$current_month)));
$current_year = max(date('Y') - 10, min(date('Y') + 1, (int)$current_year)); // Allow +/- 10 years from current year for selection

$attendance_records = [];
$attendance_summary = [
    'Present' => 0,
    'Absent' => 0,
    'Late' => 0,
    'Half Day' => 0,
    'Total Days' => 0,
    'Total Marked Days' => 0 // Days where attendance was explicitly marked
];

// Fetch student's attendance for the selected month/year
$sql_attendance = "
    SELECT
        attendance_date,
        status,
        remarks
    FROM
        attendance
    WHERE
        student_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?
    ORDER BY
        attendance_date ASC;
";

if ($stmt = mysqli_prepare($link, $sql_attendance)) {
    mysqli_stmt_bind_param($stmt, "iii", $student_id, $current_month, $current_year);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $attendance_records[] = $row;
            // Update summary
            if (isset($attendance_summary[$row['status']])) {
                $attendance_summary[$row['status']]++;
            }
            $attendance_summary['Total Marked Days']++;
        }
    } else {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>Database Error!</strong>
                <span class='block sm:inline'> Could not execute attendance query: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</span>
              </div>";
    }
    mysqli_stmt_close($stmt);
} else {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Database Error!</strong>
            <span class='block sm:inline'> Could not prepare attendance query: " . htmlspecialchars(mysqli_error($link)) . "</span>
          </div>";
}

// Calculate total days in the month for context (up to current date if current month/year)
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$attendance_summary['Total Days'] = $days_in_month;
if ($current_month == date('m') && $current_year == date('Y')) {
    $attendance_summary['Total Days'] = date('d'); // Only count days up to today in current month
}
// Percentage of attendance
$attendance_percentage = 0;
if ($attendance_summary['Total Days'] > 0) {
    // We only count Present and Half Day towards present percentage for simplicity
    $present_count_for_percentage = $attendance_summary['Present'] + ($attendance_summary['Half Day'] / 2); // Half day counts as 0.5
    $attendance_percentage = ($present_count_for_percentage / $attendance_summary['Total Days']) * 100;
}


mysqli_close($link);
?>

<div class="bg-gray-100 mt-28 mb-12 min-h-screen p-4 sm:p-6">
    <!-- Main Header Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">My Attendance</h1>
        <p class="text-gray-600">Overview of your attendance records.</p>
    </div>

    <!-- Month/Year Selector -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Select Period</h2>
        <form action="student_attendance.php" method="GET" class="flex flex-col sm:flex-row gap-4">
            <div class="flex-grow">
                <label for="month" class="block text-sm font-medium text-gray-700 sr-only">Month</label>
                <select id="month" name="month" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo sprintf("%02d", $m); ?>" <?php echo ($current_month == $m) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex-grow">
                <label for="year" class="block text-sm font-medium text-gray-700 sr-only">Year</label>
                <select id="year" name="year" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): // Last 5 years ?>
                        <option value="<?php echo $y; ?>" <?php echo ($current_year == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex-shrink-0">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">
                    View Attendance
                </button>
            </div>
        </form>
    </div>

    <!-- Attendance Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white transform transition duration-300 hover:scale-105">
            <h3 class="text-xl font-semibold opacity-85">Total Days (Marked)</h3>
            <p class="text-4xl font-bold mt-2"><?php echo $attendance_summary['Total Marked Days']; ?></p>
        </div>
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-5 text-white transform transition duration-300 hover:scale-105">
            <h3 class="text-xl font-semibold opacity-85">Present</h3>
            <p class="text-4xl font-bold mt-2"><?php echo $attendance_summary['Present']; ?></p>
        </div>
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg p-5 text-white transform transition duration-300 hover:scale-105">
            <h3 class="text-xl font-semibold opacity-85">Absent</h3>
            <p class="text-4xl font-bold mt-2"><?php echo $attendance_summary['Absent']; ?></p>
        </div>
        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-5 text-white transform transition duration-300 hover:scale-105">
            <h3 class="text-xl font-semibold opacity-85">Late</h3>
            <p class="text-4xl font-bold mt-2"><?php echo $attendance_summary['Late']; ?></p>
        </div>
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-5 text-white transform transition duration-300 hover:scale-105">
            <h3 class="text-xl font-semibold opacity-85">Half Day</h3>
            <p class="text-4xl font-bold mt-2"><?php echo $attendance_summary['Half Day']; ?></p>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6 text-center">
        <h3 class="text-2xl font-bold text-gray-900 mb-2">Attendance Percentage:</h3>
        <p class="text-5xl font-extrabold <?php echo $attendance_percentage >= 80 ? 'text-green-600' : ($attendance_percentage >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
            <?php echo number_format($attendance_percentage, 1); ?>%
        </p>
        <p class="text-gray-600 mt-2">(Calculated based on Present + Half Day/2 vs. Total Days in month up to today)</p>
    </div>

    <!-- Detailed Attendance List -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Detailed Attendance for <?php echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)); ?></h2>
        <?php if (empty($attendance_records)): ?>
            <div class="text-center p-6 border-2 border-dashed border-gray-300 rounded-xl text-gray-500">
                <p class="text-lg">No attendance records found for this period.</p>
                <p class="text-sm">Attendance data will appear here once marked by your teacher.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($attendance_records as $record): ?>
                            <?php
                            $status_color_class = '';
                            switch ($record['status']) {
                                case 'Present':
                                    $status_color_class = 'bg-green-100 text-green-800';
                                    break;
                                case 'Absent':
                                    $status_color_class = 'bg-red-100 text-red-800';
                                    break;
                                case 'Late':
                                    $status_color_class = 'bg-yellow-100 text-yellow-800';
                                    break;
                                case 'Half Day':
                                    $status_color_class = 'bg-purple-100 text-purple-800';
                                    break;
                                default:
                                    $status_color_class = 'bg-gray-100 text-gray-800';
                                    break;
                            }
                            ?>
                            <tr class="bg-white hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars(date('M d, Y (D)', strtotime($record['attendance_date']))); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo $status_color_class; ?>">
                                        <?php echo htmlspecialchars($record['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($record['remarks'] ?: '-'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once "./student_footer.php"; // Closes the main-page-content div and HTML tags
?>