<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"];
$principal_name = $_SESSION["full_name"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Filter Parameters ---
$filter_class_id = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$filter_student_id = isset($_GET['student_id']) && is_numeric($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$filter_month = isset($_GET['month']) && is_numeric($_GET['month']) ? (int)$_GET['month'] : null;
$filter_year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : null;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Pagination Configuration ---
$records_per_page = 15; // Number of attendance records to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;


// --- Fetch Filter Dropdown Data ---
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching classes for filter: " . mysqli_error($link);
    $message_type = "danger";
}

$all_students_raw = []; // All students, to be filtered by JS for the student dropdown
$sql_all_students_raw = "SELECT id, first_name, last_name, class_id FROM students ORDER BY first_name ASC, last_name ASC";
if ($result = mysqli_query($link, $sql_all_students_raw)) {
    $all_students_raw = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching students for filter: " . mysqli_error($link);
    $message_type = "danger";
}

// Years for filter dropdown (e.g., current year and 4 years prior)
$current_year = date('Y');
$years_to_display = [];
for ($y = $current_year; $y >= $current_year - 4; $y--) {
    $years_to_display[] = $y;
}
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$attendance_statuses = ['Present', 'Absent', 'Late', 'Half Day'];


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_class_id) {
    $where_clauses[] = "a.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if ($filter_student_id) {
    $where_clauses[] = "a.student_id = ?";
    $params[] = $filter_student_id;
    $types .= "i";
}
if ($filter_month) {
    $where_clauses[] = "MONTH(a.attendance_date) = ?";
    $params[] = $filter_month;
    $types .= "i";
}
if ($filter_year) {
    $where_clauses[] = "YEAR(a.attendance_date) = ?";
    $params[] = $filter_year;
    $types .= "i";
}
if ($filter_status) {
    $where_clauses[] = "a.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.registration_number LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(a.id)
                      FROM attendance a
                      JOIN students s ON a.student_id = s.id
                      JOIN classes c ON a.class_id = c.id
                      WHERE " . $where_sql;

if ($stmt = mysqli_prepare($link, $total_records_sql)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_records = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
} else {
    $message = "Error counting attendance records: " . mysqli_error($link);
    $message_type = "danger";
}
$total_pages = ceil($total_records / $records_per_page);

// Ensure current_page is within bounds
if ($current_page < 1) {
    $current_page = 1;
} elseif ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
} elseif ($total_records == 0) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;


// --- Fetch Attendance Data (with filters and pagination) ---
$attendance_records = [];
$sql_fetch_attendance = "SELECT
                            a.attendance_date,
                            a.status,
                            a.remarks,
                            s.first_name,
                            s.last_name,
                            s.registration_number,
                            c.class_name,
                            c.section_name,
                            t_marker.full_name AS marked_by_teacher_name -- Corrected alias and join table
                        FROM attendance a
                        JOIN students s ON a.student_id = s.id
                        JOIN classes c ON a.class_id = c.id
                        LEFT JOIN teachers t_marker ON a.marked_by_teacher_id = t_marker.id -- Corrected join
                        WHERE " . $where_sql . "
                        ORDER BY a.attendance_date DESC, s.first_name ASC, s.last_name ASC
                        LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_attendance)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $attendance_records = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching attendance records: " . mysqli_error($link);
    $message_type = "danger";
}

mysqli_close($link);

// --- Retrieve and clear session messages ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- PAGE INCLUDES ---
require_once './principal_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attendance Reports - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #E6E6FA, #ADD8E6, #98FB98, #87CEEB); /* Pastel purples, blues, greens */
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            color: #333;
        }
        @keyframes gradientAnimation {
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.95); /* Slightly transparent white */
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        h2 {
            color: #483d8b; /* Dark slate blue */
            margin-bottom: 30px;
            border-bottom: 2px solid #b0e0e6;
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 2.2em;
            font-weight: 700;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Filter Section */
        .filter-section {
            background-color: #f0f8ff; /* AliceBlue background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #b0e0e6;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group.full-width { /* For search input */
            flex: 2; /* Takes more space */
            min-width: 250px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #483d8b;
        }
        .filter-group select,
        .filter-group input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #a0c4ff;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #fff;
            color: #333;
        }
        .filter-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23483d8b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23483d8b%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
            margin-top: 10px; /* Space from dropdowns on wrap */
        }
        .btn-filter, .btn-clear-filter, .btn-print {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-filter {
            background-color: #6a5acd; /* Slate Blue */
            color: #fff;
            border: 1px solid #6a5acd;
        }
        .btn-filter:hover {
            background-color: #5d4aaf;
        }
        .btn-clear-filter {
            background-color: #808080; /* Gray */
            color: #fff;
            border: 1px solid #808080;
        }
        .btn-clear-filter:hover {
            background-color: #696969;
        }
        .btn-print {
            background-color: #20b2aa; /* Light Sea Green */
            color: #fff;
            border: 1px solid #20b2aa;
        }
        .btn-print:hover {
            background-color: #1a968a;
        }


        /* Attendance Table Display */
        .attendance-section-container {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        h3 {
            color: #483d8b;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .attendance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .attendance-table th, .attendance-table td {
            border-bottom: 1px solid #e0e0e0;
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }
        .attendance-table th {
            background-color: #e0f2f7; /* Light cyan */
            color: #483d8b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .attendance-table tr:nth-child(even) {
            background-color: #f8fcff;
        }
        .attendance-table tr:hover {
            background-color: #eef7fc;
        }
        .text-center {
            text-align: center;
        }
        .text-muted {
            color: #6c757d;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85em;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-Present { background-color: #d4edda; color: #155724; } /* Green */
        .status-Absent { background-color: #f8d7da; color: #721c24; } /* Red */
        .status-Late { background-color: #fff3cd; color: #856404; } /* Yellow */
        .status-HalfDay { background-color: #cce5ff; color: #004085; } /* Blue */

        /* No results message */
        .no-results {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: #6c757d;
        }

        /* Pagination Styles (reused) */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding: 10px 0;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pagination-info {
            color: #555;
            font-size: 0.95em;
            font-weight: 500;
        }
        .pagination-controls {
            display: flex;
            gap: 5px;
        }
        .pagination-controls a,
        .pagination-controls span {
            display: block;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #6a5acd;
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .pagination-controls a:hover {
            background-color: #e9ecef;
            border-color: #b0e0e6;
        }
        .pagination-controls .current-page,
        .pagination-controls .current-page:hover {
            background-color: #6a5acd;
            color: #fff;
            border-color: #6a5acd;
            cursor: default;
        }
        .pagination-controls .disabled,
        .pagination-controls .disabled:hover {
            color: #6c757d;
            background-color: #e9ecef;
            border-color: #dee2e6;
            cursor: not-allowed;
        }

        /* Print Specific Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .printable-area, .printable-area * {
                visibility: visible;
            }
            .printable-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                font-size: 10pt;
                padding: 10mm;
            }
            .printable-area h2 {
                color: #000;
                border-bottom: 1px solid #ccc;
                font-size: 16pt;
                margin-bottom: 15px;
            }
            .printable-area h3 {
                color: #000;
                font-size: 14pt;
                margin-top: 20px;
                margin-bottom: 15px;
            }
            .printable-area .attendance-table {
                border: 1px solid #ccc;
                font-size: 9pt;
                box-shadow: none;
                page-break-inside: avoid;
                width: 100%;
                border-collapse: collapse;
            }
            .printable-area .attendance-table th, .printable-area .attendance-table td {
                border: 1px solid #eee;
                padding: 8px 10px;
            }
            .printable-area .attendance-table th {
                background-color: #e0f2f7;
                color: #000;
            }
            .printable-area .status-badge {
                padding: 3px 6px;
                font-size: 0.7em;
            }
            .printable-area .no-results, .pagination-container, .filter-section, .btn-print {
                display: none;
            }
            .fas { margin-right: 3px; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-calendar-check"></i> View Attendance Reports</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter and Search Form -->
        <div class="filter-section">
            <form action="view_attendance_reports.php" method="GET" style="display:contents;">
                <div class="filter-group">
                    <label for="filter_class_id"><i class="fas fa-school"></i> Class:</label>
                    <select id="filter_class_id" name="class_id" onchange="filterStudentsByClass(this.value)">
                        <option value="">-- All Classes --</option>
                        <?php foreach ($all_classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                <?php echo ($filter_class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_student_id"><i class="fas fa-user-graduate"></i> Student:</label>
                    <select id="filter_student_id" name="student_id">
                        <option value="">-- All Students --</option>
                        <!-- Options populated by JavaScript -->
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_month"><i class="fas fa-calendar-alt"></i> Month:</label>
                    <select id="filter_month" name="month">
                        <option value="">-- All Months --</option>
                        <?php foreach ($months as $num => $name): ?>
                            <option value="<?php echo htmlspecialchars($num); ?>"
                                <?php echo ($filter_month == $num) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_year"><i class="fas fa-calendar-alt"></i> Year:</label>
                    <select id="filter_year" name="year">
                        <option value="">-- All Years --</option>
                        <?php foreach ($years_to_display as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>"
                                <?php echo ($filter_year == $year) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_status"><i class="fas fa-info-circle"></i> Status:</label>
                    <select id="filter_status" name="status">
                        <option value="">-- All Statuses --</option>
                        <?php foreach ($attendance_statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>"
                                <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group full-width">
                    <label for="search_query"><i class="fas fa-search"></i> Search Student:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Student Name / Reg. No.">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_class_id || $filter_student_id || $filter_month || $filter_year || $filter_status || !empty($search_query)): ?>
                        <a href="view_attendance_reports.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printAttendanceReport()"><i class="fas fa-print"></i> Print Report</button>
                </div>
            </form>
        </div>

        <!-- Attendance Report Table -->
        <div class="attendance-section-container printable-area">
            <h3><i class="fas fa-clipboard-list"></i> Detailed Attendance Records</h3>
            <?php if (empty($attendance_records)): ?>
                <p class="no-results">No attendance records found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student Name</th>
                                <th>Reg. No.</th>
                                <th>Class</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Marked By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td><?php echo date("M j, Y", strtotime($record['attendance_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($record['class_name'] . ' - ' . $record['section_name']); ?></td>
                                    <td><span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($record['status'])); ?>"><?php echo htmlspecialchars($record['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($record['remarks'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($record['marked_by_teacher_name'] ?: 'N/A'); ?></td> <!-- Corrected display -->
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_records > 0): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $base_url_params = array_filter([
                                'class_id' => $filter_class_id,
                                'student_id' => $filter_student_id,
                                'month' => $filter_month,
                                'year' => $filter_year,
                                'status' => $filter_status,
                                'search' => $search_query
                            ]);
                            $base_url = "view_attendance_reports.php?" . http_build_query($base_url_params);
                            ?>

                            <?php if ($current_page > 1): ?>
                                <a href="<?php echo $base_url . '&page=' . ($current_page - 1); ?>">Previous</a>
                            <?php else: ?>
                                <span class="disabled">Previous</span>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<a href="' . $base_url . '&page=1">1</a>';
                                if ($start_page > 2) {
                                    echo '<span>...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++):
                                if ($i == $current_page): ?>
                                    <span class="current-page"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo $base_url . '&page=' . $i; ?>"><?php echo $i; ?></a>
                                <?php endif;
                            endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span>...</span>';
                                }
                                echo '<a href="' . $base_url . '&page=' . $total_pages . '">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?php echo $base_url . '&page=' . ($current_page + 1); ?>">Next</a>
                            <?php else: ?>
                                <span class="disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Raw student data for JavaScript filtering
    const allStudentsRaw = <?php echo json_encode($all_students_raw); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize student dropdown on page load based on any pre-selected class_id
        const initialClassId = document.getElementById('filter_class_id').value;
        const initialStudentId = <?php echo $filter_student_id ?: 'null'; ?>;
        filterStudentsByClass(initialClassId, initialStudentId);

        // Function to print only the attendance reports section
        window.printAttendanceReport = function() {
            const printableContent = document.querySelector('.attendance-section-container').innerHTML;
            const printWindow = window.open('', '', 'height=800,width=1000');

            printWindow.document.write('<html><head><title>Attendance Report Print</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
                h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 15px; }
                h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
                .attendance-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
                .attendance-table th, .attendance-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
                .attendance-table th { background-color: #e0f2f7; color: #000; font-weight: 700; text-transform: uppercase; }
                .attendance-table tr:nth-child(even) { background-color: #f8fcff; }
                .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
                .status-Present { background-color: #d4edda; color: #155724; }
                .status-Absent { background-color: #f8d7da; color: #721c24; }
                .status-Late { background-color: #fff3cd; color: #856404; }
                .status-HalfDay { background-color: #cce5ff; color: #004085; }
                .no-results, .pagination-container, .filter-section, .btn-print { display: none; }
                .fas { margin-right: 3px; }
            `);
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printableContent);
            printWindow.document.write('</body></html>');

            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            // printWindow.close(); // Optionally close after printing
        };
    });

    // Function to filter students dropdown based on selected class
    function filterStudentsByClass(classId, selectedStudentId = null) {
        const studentSelect = document.getElementById('filter_student_id');
        studentSelect.innerHTML = '<option value="">-- All Students --</option>'; // Reset dropdown

        let studentsToDisplay = allStudentsRaw;

        if (classId) {
            studentsToDisplay = allStudentsRaw.filter(student => student.class_id == classId);
        }
        
        studentsToDisplay.forEach(student => {
            const option = document.createElement('option');
            option.value = student.id;
            option.textContent = `${student.first_name} ${student.last_name}`;
            if (selectedStudentId && student.id == selectedStudentId) {
                option.selected = true;
            }
            studentSelect.appendChild(option);
        });

        // If no classId is selected and there was a selectedStudentId, re-add it if it's not already there
        // This handles cases where a student might be selected, then 'All Classes' is chosen.
        if (!classId && selectedStudentId) {
            const studentIsAlreadyInDropdown = Array.from(studentSelect.options).some(opt => opt.value == selectedStudentId);
            if (!studentIsAlreadyInDropdown) {
                const selectedStudent = allStudentsRaw.find(student => student.id == selectedStudentId);
                if (selectedStudent) {
                    const option = document.createElement('option');
                    option.value = selectedStudent.id;
                    option.textContent = `${selectedStudent.first_name} ${selectedStudent.last_name}`;
                    option.selected = true;
                    studentSelect.appendChild(option);
                }
            }
        }
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>