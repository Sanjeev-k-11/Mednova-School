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
$filter_subject_id = isset($_GET['subject_id']) && is_numeric($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
$filter_exam_type_id = isset($_GET['exam_type_id']) && is_numeric($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : null;
$filter_upcoming_only = isset($_GET['upcoming_only']) ? true : false; // New filter for upcoming exams
$search_query = isset($_GET['search']) ? trim($_GET['search']) : ''; // New search parameter

// --- Pagination Configuration ---
$records_per_page = 10; // Number of schedules to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;


// --- Fetch Filter Dropdown Data ---
$all_exam_types = [];
$sql_all_exam_types = "SELECT id, exam_name FROM exam_types ORDER BY exam_name ASC";
if ($result = mysqli_query($link, $sql_all_exam_types)) {
    $all_exam_types = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching exam types for filter: " . mysqli_error($link);
    $message_type = "danger";
}

$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching classes for filter: " . mysqli_error($link);
    $message_type = "danger";
}

$all_subjects = [];
$sql_all_subjects = "SELECT id, subject_name FROM subjects ORDER BY subject_name ASC";
if ($result = mysqli_query($link, $sql_all_subjects)) {
    $all_subjects = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching subjects for filter: " . mysqli_error($link);
    $message_type = "danger";
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_class_id) {
    $where_clauses[] = "es.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if ($filter_subject_id) {
    $where_clauses[] = "es.subject_id = ?";
    $params[] = $filter_subject_id;
    $types .= "i";
}
if ($filter_exam_type_id) {
    $where_clauses[] = "es.exam_type_id = ?";
    $params[] = $filter_exam_type_id;
    $types .= "i";
}
if ($filter_upcoming_only) {
    $where_clauses[] = "es.exam_date >= CURDATE()";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(et.exam_name LIKE ? OR c.class_name LIKE ? OR c.section_name LIKE ? OR s.subject_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(es.id)
                      FROM exam_schedule es
                      JOIN exam_types et ON es.exam_type_id = et.id
                      JOIN classes c ON es.class_id = c.id
                      JOIN subjects s ON es.subject_id = s.id
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
    $message = "Error counting exam schedules: " . mysqli_error($link);
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


// --- Fetch Exam Schedules Data (with filters and pagination) ---
$exam_schedules = [];
$sql_fetch_schedules = "SELECT
                            es.id,
                            es.exam_date,
                            es.start_time,
                            es.end_time,
                            es.max_marks,
                            es.passing_marks,
                            et.exam_name,
                            c.class_name,
                            c.section_name,
                            s.subject_name
                        FROM exam_schedule es
                        JOIN exam_types et ON es.exam_type_id = et.id
                        JOIN classes c ON es.class_id = c.id
                        JOIN subjects s ON es.subject_id = s.id
                        WHERE " . $where_sql . "
                        ORDER BY et.exam_name ASC, c.class_name ASC, c.section_name ASC, es.exam_date ASC, es.start_time ASC
                        LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_schedules)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exam_schedules = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching exam schedules: " . mysqli_error($link);
    $message_type = "danger";
}

mysqli_close($link);

// --- Group exam schedules data for display ---
// Group by Exam Type -> Class -> List of Schedules
$grouped_schedules = [];
foreach ($exam_schedules as $schedule) {
    $exam_type_key = $schedule['exam_name'];
    $class_key = $schedule['class_name'] . ' - ' . $schedule['section_name'];

    $grouped_schedules[$exam_type_key]['exam_type_info'] = ['name' => $exam_type_key];
    $grouped_schedules[$exam_type_key]['classes'][$class_key][] = $schedule;
}


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
    <title>View Exam Schedules - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #A8C0FF, #373B44, #4CAF50, #0072FF); /* A professional blue/grey/green gradient */
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
            color: #1a237e; /* Dark blue */
            margin-bottom: 30px;
            border-bottom: 2px solid #a8c0ff;
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
            background-color: #f0f4f8; /* Light grey-blue background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #d9e2ec;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1; /* Allow filter groups to grow */
            min-width: 180px; /* Minimum width for filter dropdowns */
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50; /* Darker text for labels */
        }
        .filter-group select,
        .filter-group input[type="text"], /* Added for search input */
        .filter-group input[type="checkbox"] + label { /* Style for checkbox label */
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #c2d1e0;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #fff;
        }
        .filter-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%232c3e50%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%232c3e50%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
        }
        .filter-group .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px; /* Space between checkbox and label text */
            padding: 8px 12px; /* Padding for the entire clickable area */
            border: 1px solid #c2d1e0;
            border-radius: 5px;
            background-color: #fff;
            cursor: pointer;
            transition: background-color 0.2s ease;
            height: calc(100% - 2px); /* Adjust height to match select/input */
        }
        .filter-group .checkbox-wrapper:hover {
            background-color: #e9eff5;
        }
        .filter-group input[type="checkbox"] {
            transform: scale(1.2);
            flex-shrink: 0;
            margin: 0; /* Remove default margin */
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        .btn-filter, .btn-clear-filter, .btn-print { /* Added btn-print */
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none; /* For print button as <a> */
        }
        .btn-filter {
            background-color: #3f51b5; /* Indigo blue */
            color: #fff;
            border: 1px solid #3f51b5;
        }
        .btn-filter:hover {
            background-color: #303f9f;
        }
        .btn-clear-filter {
            background-color: #607d8b; /* Blue-grey */
            color: #fff;
            border: 1px solid #607d8b;
        }
        .btn-clear-filter:hover {
            background-color: #455a64;
        }
        .btn-print { /* Style for the new print button */
            background-color: #4CAF50; /* Green */
            color: #fff;
            border: 1px solid #4CAF50;
        }
        .btn-print:hover {
            background-color: #388E3C;
        }


        /* Exam Schedules Display */
        .schedules-section-container { /* Main container for all exam type groups */
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .exam-type-group {
            margin-bottom: 25px;
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .section-header { /* Header for each Exam Type collapsible group */
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #64b5f6; /* Light blue header */
            color: #fff;
            padding: 15px 20px;
            margin: 0;
            font-size: 1.6em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .section-header:hover {
            background-color: #42a5f5; /* Darker blue on hover */
        }
        .section-header h3 {
            margin: 0;
            font-size: 1em; /* Inherit font size from parent .section-header */
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-toggle-btn {
            background: none;
            border: none;
            font-size: 1em;
            color: #fff;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .section-toggle-btn.rotated {
            transform: rotate(90deg);
        }
        .section-content { /* Content area inside the collapsible group */
            max-height: 2000px; /* Large value for initial/expanded state */
            overflow: hidden;
            transition: max-height 0.5s ease-in-out;
            padding: 15px 0; /* Padding inside the content area */
        }
        .section-content.collapsed {
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
        }

        .class-schedule-card { /* Card for each class within an exam type */
            background-color: #e3f2fd; /* Very light blue for class cards */
            border: 1px solid #bbdefb;
            border-radius: 6px;
            margin: 15px 20px; /* Spacing within the exam type group */
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .class-schedule-card h4 {
            background-color: #90caf9; /* Medium blue for class card header */
            color: #1a237e;
            padding: 10px 15px;
            margin: 0;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        .schedule-table th, .schedule-table td {
            border: 1px solid #bbdefb;
            padding: 12px 15px;
            text-align: left;
            vertical-align: middle;
        }
        .schedule-table th {
            background-color: #bbdefb;
            color: #1a237e;
            font-weight: 700;
            font-size: 0.85em;
            text-transform: uppercase;
        }
        .schedule-table tr:nth-child(even) {
            background-color: #f8fcff;
        }
        .schedule-table tr:hover {
            background-color: #e9f5ff;
        }
        .text-center {
            text-align: center;
        }
        .text-muted {
            color: #6c757d;
        }

        /* No results message */
        .no-results {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: #6c757d;
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding: 10px 0;
            border-top: 1px solid #eee;
            flex-wrap: wrap; /* Allow wrapping on small screens */
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
            color: #3f51b5; /* Indigo */
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .pagination-controls a:hover {
            background-color: #e9ecef;
            border-color: #a8c0ff;
        }
        .pagination-controls .current-page,
        .pagination-controls .current-page:hover {
            background-color: #3f51b5;
            color: #fff;
            border-color: #3f51b5;
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
                font-size: 12pt;
                padding: 10mm;
            }
            .printable-area h2 {
                color: #000;
                border-bottom: 2px solid #ccc;
                font-size: 18pt;
            }
            .printable-area .exam-type-group {
                border: 1px solid #ccc;
                margin-bottom: 15px;
                page-break-inside: avoid; /* Prevent breaking a group across pages */
            }
            .printable-area .section-header {
                background-color: #eee;
                color: #333;
                border-bottom: 1px solid #ddd;
                font-size: 14pt;
                cursor: default; /* Remove pointer for print */
            }
            .printable-area .section-toggle-btn {
                display: none; /* Hide toggle button in print */
            }
            .printable-area .timetable-content, .printable-area .section-content {
                max-height: none !important; /* Ensure content is fully visible */
                overflow: visible !important;
                transition: none !important;
                padding: 10px 0 !important;
            }
            .printable-area .class-schedule-card {
                margin: 10px 15px;
                border: 1px solid #ddd;
            }
            .printable-area .class-schedule-card h4 {
                background-color: #f0f0f0;
                color: #555;
                font-size: 12pt;
            }
            .printable-area .schedule-table {
                font-size: 11pt;
            }
            .printable-area .schedule-table th, .printable-area .schedule-table td {
                border: 1px solid #eee;
                padding: 8px 10px;
            }
            .printable-area .pagination-container,
            .printable-area .filter-section,
            .printable-area .btn-print,
            .printable-area .btn-filter,
            .printable-area .btn-clear-filter {
                display: none; /* Hide filters and pagination from print */
            }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-calendar-check"></i> View Exam Schedules</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="filter-section">
            <form action="view_exam_schedules.php" method="GET" style="display:contents;">
                <div class="filter-group">
                    <label for="filter_exam_type_id"><i class="fas fa-clipboard"></i> Exam Type:</label>
                    <select id="filter_exam_type_id" name="exam_type_id">
                        <option value="">-- All Exam Types --</option>
                        <?php foreach ($all_exam_types as $exam_type): ?>
                            <option value="<?php echo htmlspecialchars($exam_type['id']); ?>"
                                <?php echo ($filter_exam_type_id == $exam_type['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam_type['exam_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_class_id"><i class="fas fa-school"></i> Class:</label>
                    <select id="filter_class_id" name="class_id">
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
                    <label for="filter_subject_id"><i class="fas fa-book-open"></i> Subject:</label>
                    <select id="filter_subject_id" name="subject_id">
                        <option value="">-- All Subjects --</option>
                        <?php foreach ($all_subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['id']); ?>"
                                <?php echo ($filter_subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="filter-group">
                    <label for="search_query"><i class="fas fa-search"></i> Search:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Exam/Class/Subject name">
                </div>
                 <div class="filter-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="upcoming_only" name="upcoming_only" <?php echo $filter_upcoming_only ? 'checked' : ''; ?>>
                        <label for="upcoming_only"><i class="fas fa-hourglass-start"></i> Show Upcoming Only</label>
                    </div>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_class_id || $filter_subject_id || $filter_exam_type_id || $filter_upcoming_only || !empty($search_query)): ?>
                        <a href="view_exam_schedules.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printSchedules()"><i class="fas fa-print"></i> Print Schedules</button>
                </div>
            </form>
        </div>

        <!-- Exam Schedules Display Section -->
        <div class="schedules-section-container printable-area"> <!-- Added printable-area class -->
            <?php if (empty($grouped_schedules)): ?>
                <p class="no-results">No exam schedules found matching your criteria.</p>
            <?php else: ?>
                <?php $exam_type_index = 0; ?>
                <?php foreach ($grouped_schedules as $exam_type_key => $exam_type_data): ?>
                    <?php $exam_type_index++; ?>
                    <div class="exam-type-group">
                        <div class="section-header" onclick="toggleSection('exam-type-content-<?php echo $exam_type_index; ?>', this.querySelector('.section-toggle-btn'))"
                             aria-expanded="true" aria-controls="exam-type-content-<?php echo $exam_type_index; ?>">
                            <h3><i class="fas fa-clipboard-list"></i> Exam Type: <?php echo htmlspecialchars($exam_type_data['exam_type_info']['name']); ?></h3>
                            <button class="section-toggle-btn">
                                <i class="fas fa-chevron-down"></i> <!-- Initially expanded -->
                            </button>
                        </div>
                        <div id="exam-type-content-<?php echo $exam_type_index; ?>" class="section-content">
                            <?php foreach ($exam_type_data['classes'] as $class_key => $class_schedules): ?>
                                <div class="class-schedule-card">
                                    <h4><i class="fas fa-chalkboard"></i> Class: <?php echo htmlspecialchars($class_key); ?></h4>
                                    <div style="overflow-x:auto;">
                                        <table class="schedule-table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Subject</th>
                                                    <th>Max Marks</th>
                                                    <th>Passing Marks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($class_schedules as $schedule): ?>
                                                    <tr>
                                                        <td><?php echo date("D, M j, Y", strtotime($schedule['exam_date'])); ?></td>
                                                        <td><?php echo date("g:i A", strtotime($schedule['start_time'])) . ' - ' . date("g:i A", strtotime($schedule['end_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($schedule['subject_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($schedule['max_marks']); ?></td>
                                                        <td><?php echo htmlspecialchars($schedule['passing_marks']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                 <?php if ($total_records > 0): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $base_url = "view_exam_schedules.php?" . http_build_query(array_filter([
                                'class_id' => $filter_class_id,
                                'subject_id' => $filter_subject_id,
                                'exam_type_id' => $filter_exam_type_id,
                                'upcoming_only' => $filter_upcoming_only ? 'on' : null,
                                'search' => $search_query
                            ]));
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
    document.addEventListener('DOMContentLoaded', function() {
        // Function to toggle the collapse state of a section
        window.toggleSection = function(contentId, button) {
            const content = document.getElementById(contentId);
            const icon = button.querySelector('.fas');

            if (content.classList.contains('collapsed')) {
                // Expand the section
                content.classList.remove('collapsed');
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
                button.setAttribute('aria-expanded', 'true');
            } else {
                // Collapse the section
                content.classList.add('collapsed');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
                button.setAttribute('aria-expanded', 'false');
            }
        };

        // Initialize collapsed state on page load for any sections that should start collapsed
        document.querySelectorAll('.schedules-section-container .section-header').forEach(header => {
            const button = header.querySelector('.section-toggle-btn');
            const contentId = header.getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            
            // Ensure content div is fully visible initially, then collapse if needed.
            content.style.maxHeight = content.scrollHeight + "px"; // Temporarily set height to its full size

            setTimeout(() => {
                content.style.maxHeight = null; 
                // All sections start expanded, so no class is added here for collapsing
            }, 10);
        });
    });

    // Function to print only the schedules section
    function printSchedules() {
        // Expand all collapsible sections before printing
        document.querySelectorAll('.section-content.collapsed').forEach(content => {
            content.classList.remove('collapsed');
            const button = content.previousElementSibling.querySelector('.section-toggle-btn');
            if (button) {
                button.querySelector('.fas').classList.remove('fa-chevron-right');
                button.querySelector('.fas').classList.add('fa-chevron-down');
                button.setAttribute('aria-expanded', 'true');
            }
        });

        const printableContent = document.querySelector('.schedules-section-container').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=1000');
        
        printWindow.document.write('<html><head><title>Exam Schedules Print</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
        // Include relevant styles from this page directly in the print window
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
            h2 { color: #1a237e; border-bottom: 2px solid #a8c0ff; padding-bottom: 12px; font-size: 2.2em; font-weight: 700; margin-bottom: 30px; }
            .exam-type-group { border: 1px solid #cfd8dc; border-radius: 8px; margin-bottom: 25px; overflow: hidden; page-break-inside: avoid; }
            .section-header { background-color: #64b5f6; color: #fff; padding: 15px 20px; margin: 0; font-size: 1.6em; font-weight: 600; display: flex; align-items: center; gap: 10px; }
            .section-header h3 { margin: 0; font-size: 1em; display: flex; align-items: center; gap: 10px; }
            .section-toggle-btn { display: none; } /* Hide toggle in print */
            .section-content { padding: 15px 0; max-height: none !important; overflow: visible !important; transition: none !important; }
            .class-schedule-card { background-color: #e3f2fd; border: 1px solid #bbdefb; border-radius: 6px; margin: 15px 20px; overflow: hidden; page-break-inside: avoid; }
            .class-schedule-card h4 { background-color: #90caf9; color: #1a237e; padding: 10px 15px; margin: 0; font-size: 1.2em; display: flex; align-items: center; gap: 8px; }
            .schedule-table { width: 100%; border-collapse: collapse; margin-top: 0; font-size: 0.95em; }
            .schedule-table th, .schedule-table td { border: 1px solid #bbdefb; padding: 10px 12px; text-align: left; vertical-align: middle; }
            .schedule-table th { background-color: #bbdefb; color: #1a237e; font-weight: 700; text-transform: uppercase; }
            .schedule-table tr:nth-child(even) { background-color: #f8fcff; }
            .no-results, .pagination-container, .filter-section { display: none; }
            .fas { margin-right: 5px; } /* Adjust icon spacing for print */
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printableContent);
        printWindow.document.write('</body></html>');
        
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        // printWindow.close(); // Optionally close after printing, but users might want to review
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>