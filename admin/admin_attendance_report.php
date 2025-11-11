<?php
// admin_attendance_report.php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["id"]; // The ID of the currently logged-in admin

// --- FILTER & DATE SETUP ---
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$class_filter = $_GET['class_id'] ?? ''; // Filter by class
$teacher_filter = $_GET['teacher_id'] ?? ''; // Filter by teacher assigned to a class
$search_query = trim($_GET['search'] ?? ''); // Search student name

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$month_name = date('F', mktime(0, 0, 0, $current_month, 10));

// --- PAGINATION SETUP ---
$records_per_page = 15; // Number of students per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- DATA FOR DROPDOWNS ---
// Get all classes
$all_classes = [];
$sql_get_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name";
if ($stmt_classes = mysqli_prepare($link, $sql_get_classes)) {
    mysqli_stmt_execute($stmt_classes);
    $result = mysqli_stmt_get_result($stmt_classes);
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_classes);
}

// Get all teachers
$all_teachers = [];
$sql_get_teachers = "SELECT id, full_name FROM teachers ORDER BY full_name";
if ($stmt_teachers = mysqli_prepare($link, $sql_get_teachers)) {
    mysqli_stmt_execute($stmt_teachers);
    $result = mysqli_stmt_get_result($stmt_teachers);
    $all_teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_teachers);
}

// --- STUDENT FETCHING (WITH FILTERS AND PAGINATION) ---
$students = [];
$student_ids_for_attendance_query = []; // To store IDs of students fetched for the current page
$total_records = 0;

$sql_students_base = "SELECT s.id, s.roll_number, s.first_name, s.last_name, c.class_name, c.section_name 
                      FROM students s
                      JOIN classes c ON s.class_id = c.id";
$sql_students_where_conditions = ["1=1"];
$params = [];
$types = "";

// Filter by Class
if (!empty($class_filter)) {
    $sql_students_where_conditions[] = "s.class_id = ?";
    $params[] = $class_filter;
    $types .= "i";
}

// Filter by Teacher (students in classes taught by this teacher)
if (!empty($teacher_filter)) {
    $sql_students_base .= " JOIN class_subject_teacher cst ON s.class_id = cst.class_id";
    $sql_students_where_conditions[] = "cst.teacher_id = ?";
    $params[] = $teacher_filter;
    $types .= "i";
}

// Search by Student Name
if (!empty($search_query)) {
    $sql_students_where_conditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ?)";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $types .= "ss";
}

$where_clause = implode(" AND ", $sql_students_where_conditions);

// 1. Get Total Records for Pagination
$sql_count = "SELECT COUNT(DISTINCT s.id) FROM (" . $sql_students_base . " WHERE " . $where_clause . ") AS subquery";
if ($stmt_count = mysqli_prepare($link, $sql_count)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    mysqli_stmt_bind_result($stmt_count, $total_records);
    mysqli_stmt_fetch($stmt_count);
    mysqli_stmt_close($stmt_count);
}
$total_pages = ceil($total_records / $records_per_page);

// 2. Fetch Students for Current Page
$sql_students = $sql_students_base . " WHERE " . $where_clause . " GROUP BY s.id ORDER BY s.roll_number, s.first_name LIMIT ?, ?";
$params[] = $offset;
$types .= "i";
$params[] = $records_per_page;
$types .= "i";

if($stmt_students = mysqli_prepare($link, $sql_students)) {
    mysqli_stmt_bind_param($stmt_students, $types, ...$params);
    mysqli_stmt_execute($stmt_students);
    $result = mysqli_stmt_get_result($stmt_students);
    $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_students);
    $student_ids_for_attendance_query = array_column($students, 'id');
}

// --- ATTENDANCE FETCHING ---
$attendance_records = [];
if (!empty($student_ids_for_attendance_query)) {
    $sql_attendance = "SELECT student_id, DAY(attendance_date) as day, status 
                       FROM attendance 
                       WHERE student_id IN (" . implode(',', array_fill(0, count($student_ids_for_attendance_query), '?')) . ") 
                       AND MONTH(attendance_date) = ? 
                       AND YEAR(attendance_date) = ?";
    
    $params_att = $student_ids_for_attendance_query;
    $types_att = str_repeat('i', count($student_ids_for_attendance_query));
    $params_att[] = $current_month;
    $types_att .= 'i';
    $params_att[] = $current_year;
    $types_att .= 'i';
    
    if($stmt_att = mysqli_prepare($link, $sql_attendance)) {
        mysqli_stmt_bind_param($stmt_att, $types_att, ...$params_att);
        mysqli_stmt_execute($stmt_att);
        $result = mysqli_stmt_get_result($stmt_att);
        while($row = mysqli_fetch_assoc($result)) {
            $attendance_records[$row['student_id']][$row['day']] = $row['status'];
        }
        mysqli_stmt_close($stmt_att);
    }
}

// --- SUMMARY STATISTICS ---
$overall_present = 0;
$overall_total_marked_days = 0; // Only count days where attendance was actually marked

mysqli_close($link);
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Attendance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364); background-size: 400% 400%; animation: gradientBG 15s ease infinite; color: white; }
        @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
        .glassmorphism { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .form-select, .form-input { background: rgba(0, 0, 0, 0.25); border-color: rgba(255, 255, 255, 0.2); color: white; }
        .form-input::placeholder { color: rgba(255, 255, 255, 0.6); }
        .form-input:focus, .form-select:focus { border-color: #4CAF50; outline: none; box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.5); }
        .table-header-day { min-width: 40px; }
        .weekend { background-color: rgba(0,0,0,0.2); }
        .pagination-link {
            @apply px-3 py-1 mx-1 rounded-md transition-colors duration-200;
        }
        .pagination-link.active {
            @apply bg-blue-600 text-white;
        }
        .pagination-link:not(.active):hover {
            @apply bg-gray-700 text-white;
        }
        .disabled-link {
            @apply opacity-50 cursor-not-allowed;
        }
        .sticky-col-header {
            position: sticky;
            left: 0;
            z-index: 10;
            background-color: rgba(4, 30, 48, 0.7); /* Darker, slightly transparent background for sticky */
            backdrop-filter: blur(8px);
        }
        .sticky-col-data {
            position: sticky;
            left: 0;
            z-index: 5;
            background-color: rgba(30, 41, 59, 0.7); /* Darker, slightly transparent background for sticky */
            backdrop-filter: blur(8px);
        }
        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex flex-col items-center">
       

        <div class="container mx-auto mt-28 p-4 md:p-8">
            <h1 class="text-3xl md:text-4xl font-bold mb-6 text-white text-center">Admin: Attendance Report</h1>

            <div class="glassmorphism rounded-2xl p-4 md:p-6">
                <!-- Filter Form -->
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 mb-6 items-end">
                    <div>
                        <label for="month" class="block text-sm font-semibold text-white/80 mb-1">Month</label>
                        <select name="month" id="month" class="w-full h-10 form-select rounded-lg">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php if($m == $current_month) echo 'selected'; ?>><?php echo date('F', mktime(0,0,0,$m, 1, date('Y'))); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label for="year" class="block text-sm font-semibold text-white/80 mb-1">Year</label>
                        <select name="year" id="year" class="w-full h-10 form-select rounded-lg">
                            <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php if($y == $current_year) echo 'selected'; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label for="class_id" class="block text-sm font-semibold text-white/80 mb-1">Class</label>
                        <select name="class_id" id="class_id" class="w-full h-10 form-select rounded-lg">
                            <option value="">All Classes</option>
                            <?php foreach($all_classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php if($class['id'] == $class_filter) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="teacher_id" class="block text-sm font-semibold text-white/80 mb-1">Teacher</label>
                        <select name="teacher_id" id="teacher_id" class="w-full h-10 form-select rounded-lg">
                            <option value="">All Teachers</option>
                            <?php foreach($all_teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php if($teacher['id'] == $teacher_filter) echo 'selected'; ?>><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="search" class="block text-sm font-semibold text-white/80 mb-1">Search Student</label>
                        <input type="text" name="search" id="search" class="w-full h-10 form-input rounded-lg" placeholder="Student name..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    <div class="flex gap-2 col-span-1 sm:col-span-2 md:col-span-5 justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">View Report</button>
                    </div>
                </form>

                <?php if (empty($students)): ?>
                    <div class="text-center py-16"><p class="text-lg">No students found for the selected criteria.</p></div>
                <?php else: ?>
                    <!-- Report Table -->
                    <div class="table-container">
                        <table class="w-full text-sm text-center border-collapse">
                            <thead class="bg-black/20">
                                <tr>
                                    <th class="p-3 text-left sticky-col-header" style="min-width: 180px;">Student Name</th>
                                    <th class="p-3 text-left sticky-col-header" style="left: 180px; min-width: 80px;">Roll No.</th>
                                    <th class="p-3 text-left sticky-col-header" style="left: 260px; min-width: 120px;">Class</th>
                                    <?php for ($i = 1; $i <= $days_in_month; $i++): 
                                        $day_name = date('D', mktime(0,0,0,$current_month, $i, $current_year));
                                        $is_weekend = in_array($day_name, ['Sat', 'Sun']);
                                        $is_today = ($i == date('d') && $current_month == date('m') && $current_year == date('Y'));
                                    ?>
                                        <th class="p-2 table-header-day <?php if($is_weekend) echo 'weekend'; ?> <?php if($is_today) echo 'bg-blue-600/50'; ?>">
                                            <?php echo $i; ?><br><span class="font-normal text-xs text-white/60"><?php echo $day_name; ?></span>
                                        </th>
                                    <?php endfor; ?>
                                    <th class="p-3 text-green-400">P</th>
                                    <th class="p-3 text-red-400">A</th>
                                    <th class="p-3 text-yellow-400">L</th>
                                    <th class="p-3 text-blue-400">HD</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($students as $student): 
                                    $summary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0];
                                ?>
                                    <tr class="border-b border-white/10 hover:bg-white/5">
                                        <td class="p-2 text-left font-semibold sticky-col-data"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td class="p-2 text-left sticky-col-data" style="left: 180px;"><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                        <td class="p-2 text-left sticky-col-data" style="left: 260px;"><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?></td>
                                        <?php for ($i = 1; $i <= $days_in_month; $i++): 
                                            $status = $attendance_records[$student['id']][$i] ?? null;
                                            if($status) $summary[$status]++;
                                            $day_name = date('D', mktime(0,0,0,$current_month, $i, $current_year));
                                            $is_weekend = in_array($day_name, ['Sat', 'Sun']);
                                            $is_today = ($i == date('d') && $current_month == date('m') && $current_year == date('Y'));
                                        ?>
                                            <td class="p-2 <?php if($is_weekend) echo 'weekend'; ?> <?php if($is_today) echo 'bg-blue-600/20'; ?>">
                                                <?php
                                                    switch($status) {
                                                        case 'Present': echo '<i class="fas fa-check-circle text-green-400" title="Present"></i>'; break;
                                                        case 'Absent': echo '<i class="fas fa-times-circle text-red-400" title="Absent"></i>'; break;
                                                        case 'Late': echo '<i class="fas fa-clock text-yellow-400" title="Late"></i>'; break;
                                                        case 'Half Day': echo '<i class="fas fa-star-half-alt text-blue-400" title="Half Day"></i>'; break;
                                                        default: echo '<span class="text-white/30">-</span>'; break;
                                                    }
                                                ?>
                                            </td>
                                        <?php endfor; 
                                            $overall_present += $summary['Present'];
                                            $overall_total_marked_days += array_sum($summary); // Sum of P, A, L, HD for this student
                                        ?>
                                        <td class="p-2 font-bold text-green-400"><?php echo $summary['Present']; ?></td>
                                        <td class="p-2 font-bold text-red-400"><?php echo $summary['Absent']; ?></td>
                                        <td class="p-2 font-bold text-yellow-400"><?php echo $summary['Late']; ?></td>
                                        <td class="p-2 font-bold text-blue-400"><?php echo $summary['Half Day']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center items-center space-x-2 mt-8 text-white">
                        <?php
                        // Construct base URL for pagination links, preserving filters
                        $base_pagination_url = "admin_attendance_report.php?";
                        $query_params = [];
                        if ($current_month != date('m')) $query_params['month'] = $current_month;
                        if ($current_year != date('Y')) $query_params['year'] = $current_year;
                        if (!empty($class_filter)) $query_params['class_id'] = $class_filter;
                        if (!empty($teacher_filter)) $query_params['teacher_id'] = $teacher_filter;
                        if (!empty($search_query)) $query_params['search'] = urlencode($search_query);

                        $param_string = http_build_query($query_params);
                        if (!empty($param_string)) {
                            $base_pagination_url .= $param_string . "&";
                        }
                        ?>

                        <!-- Previous Button -->
                        <a href="<?php echo $base_pagination_url; ?>page=<?php echo max(1, $current_page - 1); ?>" class="pagination-link <?php echo ($current_page <= 1) ? 'disabled-link' : ''; ?>">Previous</a>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1) {
                            echo '<a href="' . $base_pagination_url . 'page=1" class="pagination-link">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="px-2">...</span>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="<?php echo $base_pagination_url; ?>page=<?php echo $i; ?>" class="pagination-link <?php echo ($i === $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="px-2">...</span>';
                            }
                            echo '<a href="' . $base_pagination_url . 'page=' . $total_pages . '" class="pagination-link">' . $total_pages . '</a>';
                        }
                        ?>

                        <!-- Next Button -->
                        <a href="<?php echo $base_pagination_url; ?>page=<?php echo min($total_pages, $current_page + 1); ?>" class="pagination-link <?php echo ($current_page >= $total_pages) ? 'disabled-link' : ''; ?>">Next</a>
                    </div>
                    <p class="text-center text-sm text-white/70 mt-4">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records.
                    </p>
                    <?php endif; ?>

                    <!-- Overall Summary Card -->
                    <?php 
                        $attendance_percentage = $overall_total_marked_days > 0 ? round(($overall_present / $overall_total_marked_days) * 100, 1) : 0;
                    ?>
                    <div class="mt-6 p-4 bg-black/20 rounded-lg text-center">
                        <h3 class="font-bold text-lg">Overall Attendance for <?php echo "$month_name $current_year"; ?> (Filtered Students)</h3>
                        <p class="text-3xl font-bold mt-2 <?php echo $attendance_percentage >= 80 ? 'text-green-400' : ($attendance_percentage >= 50 ? 'text-yellow-400' : 'text-red-400'); ?>">
                            <?php echo $attendance_percentage; ?>%
                        </p>
                        <p class="text-sm text-white/60">(<?php echo $overall_present; ?> present days out of <?php echo $overall_total_marked_days; ?> total marked days)</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        
    </div>
</body>
</html>
<?php

require_once './admin_footer.php';
?>