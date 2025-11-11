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
$filter_exam_type_id = isset($_GET['exam_type_id']) && is_numeric($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : null;
$filter_class_id = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$filter_subject_id = isset($_GET['subject_id']) && is_numeric($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
$filter_student_id = isset($_GET['student_id']) && is_numeric($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Pagination Configuration ---
$records_per_page = 20; // Number of mark entries to display per page
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

$all_students_raw = []; // All students for dynamic JS filtering
$sql_all_students_raw = "SELECT id, first_name, last_name, registration_number, class_id FROM students ORDER BY first_name ASC, last_name ASC";
if ($result = mysqli_query($link, $sql_all_students_raw)) {
    $all_students_raw = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching students for filter: " . mysqli_error($link);
    $message_type = "danger";
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_exam_type_id) {
    $where_clauses[] = "es.exam_type_id = ?";
    $params[] = $filter_exam_type_id;
    $types .= "i";
}
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
if ($filter_student_id) {
    $where_clauses[] = "em.student_id = ?";
    $params[] = $filter_student_id;
    $types .= "i";
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
$total_records_sql = "SELECT COUNT(em.id)
                      FROM exam_marks em
                      JOIN exam_schedule es ON em.exam_schedule_id = es.id
                      JOIN exam_types et ON es.exam_type_id = et.id
                      JOIN classes c ON es.class_id = c.id
                      JOIN subjects subj ON es.subject_id = subj.id
                      JOIN students s ON em.student_id = s.id
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
    $message = "Error counting mark entries: " . mysqli_error($link);
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


// --- Fetch Mark Entries (with filters and pagination) ---
$mark_entries = [];
$sql_fetch_marks = "SELECT
                        em.id AS mark_id, em.marks_obtained,
                        es.exam_date, es.max_marks, es.passing_marks,
                        et.exam_name,
                        c.class_name, c.section_name,
                        subj.subject_name,
                        s.id AS student_id, s.first_name, s.last_name, s.registration_number,
                        t.full_name AS uploaded_by_teacher
                    FROM exam_marks em
                    JOIN exam_schedule es ON em.exam_schedule_id = es.id
                    JOIN exam_types et ON es.exam_type_id = et.id
                    JOIN classes c ON es.class_id = c.id
                    JOIN subjects subj ON es.subject_id = subj.id
                    JOIN students s ON em.student_id = s.id
                    LEFT JOIN teachers t ON em.uploaded_by_teacher_id = t.id
                    WHERE " . $where_sql . "
                    ORDER BY et.exam_name ASC, c.class_name ASC, c.section_name ASC, subj.subject_name ASC, s.first_name ASC
                    LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_marks)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $mark_entries = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching mark entries: " . mysqli_error($link);
    $message_type = "danger";
}

mysqli_close($link);

// --- Group mark entries for display (Exam Type -> Class -> List of Student Marks) ---
$grouped_marks = [];
foreach ($mark_entries as $entry) {
    $exam_type_key = $entry['exam_name'];
    $class_key = $entry['class_name'] . ' - ' . $entry['section_name'];

    if (!isset($grouped_marks[$exam_type_key])) {
        $grouped_marks[$exam_type_key] = [
            'exam_type_info' => ['name' => $exam_type_key],
            'classes' => []
        ];
    }
    if (!isset($grouped_marks[$exam_type_key]['classes'][$class_key])) {
        $grouped_marks[$exam_type_key]['classes'][$class_key] = [
            'class_info' => ['name' => $class_key],
            'entries' => []
        ];
    }
    $grouped_marks[$exam_type_key]['classes'][$class_key]['entries'][] = $entry;
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
    <title>Review Marks - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #CCE2FF, #ADD8E6, #87CEFA, #6495ED); /* Soft blue gradient */
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
            color: #004085; /* Darker blue */
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
            background-color: #e0f2f7; /* Light cyan background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #b2ebf2;
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
            flex: 2;
            min-width: 250px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #004085;
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
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23004085%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23004085%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
            margin-top: 10px;
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
            background-color: #007bff; /* Primary Blue */
            color: #fff;
            border: 1px solid #007bff;
        }
        .btn-filter:hover {
            background-color: #0056b3;
        }
        .btn-clear-filter {
            background-color: #6c757d; /* Grey */
            color: #fff;
            border: 1px solid #6c757d;
        }
        .btn-clear-filter:hover {
            background-color: #5a6268;
        }
        .btn-print {
            background-color: #28a745; /* Green */
            color: #fff;
            border: 1px solid #28a745;
        }
        .btn-print:hover {
            background-color: #218838;
        }


        /* Marks Display */
        .marks-section-container {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .exam-type-group {
            margin-bottom: 25px;
            border: 1px solid #b0e0e6; /* Light blue border */
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .section-header { /* Header for each Exam Type collapsible group */
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #87CEEB; /* Sky Blue header */
            color: #fff;
            padding: 15px 20px;
            margin: 0;
            font-size: 1.6em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .section-header:hover {
            background-color: #5DBCD2; /* Darker Sky Blue on hover */
        }
        .section-header h3 {
            margin: 0;
            font-size: 1em;
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
            max-height: 2000px;
            overflow: hidden;
            transition: max-height 0.5s ease-in-out;
            padding: 15px 0;
        }
        .section-content.collapsed {
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
        }

        .class-marks-card { /* Card for each class within an exam type */
            background-color: #e0f2f7; /* Very light blue for class cards */
            border: 1px solid #b2ebf2;
            border-radius: 6px;
            margin: 15px 20px;
            padding: 15px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .class-marks-card h4 {
            color: #004085;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px dashed #a0c4ff;
            padding-bottom: 10px;
        }
        .marks-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        .marks-table th, .marks-table td {
            border: 1px solid #b2ebf2;
            padding: 12px 15px;
            text-align: left;
            vertical-align: middle;
        }
        .marks-table th {
            background-color: #b0e0e6;
            color: #004085;
            font-weight: 700;
            font-size: 0.85em;
            text-transform: uppercase;
        }
        .marks-table tr:nth-child(even) {
            background-color: #f8fcff;
        }
        .marks-table tr:hover {
            background-color: #eef7fc;
        }
        .pass-status {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
            white-space: nowrap;
        }
        .pass-status.passed { background-color: #d4edda; color: #155724; } /* Green */
        .pass-status.failed { background-color: #f8d7da; color: #721c24; } /* Red */

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
            color: #007bff;
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .pagination-controls a:hover {
            background-color: #e9ecef;
            border-color: #b0d4ff;
        }
        .pagination-controls .current-page,
        .pagination-controls .current-page:hover {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
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
            .printable-area .exam-type-group {
                border: 1px solid #ccc;
                margin-bottom: 15px;
                page-break-inside: avoid;
            }
            .printable-area .section-header {
                background-color: #e0e0e0;
                color: #333;
                border-bottom: 1px solid #ddd;
                font-size: 14pt;
                cursor: default;
            }
            .printable-area .section-toggle-btn {
                display: none;
            }
            .printable-area .section-content {
                max-height: none !important;
                overflow: visible !important;
                transition: none !important;
                padding: 10px 0 !important;
            }
            .printable-area .class-marks-card {
                margin: 10px 15px;
                border: 1px solid #ddd;
                background-color: #f9f9f9;
            }
            .printable-area .class-marks-card h4 {
                color: #555;
                font-size: 12pt;
                border-bottom: 1px dashed #ccc;
            }
            .printable-area .marks-table {
                font-size: 9pt;
            }
            .printable-area .marks-table th, .printable-area .marks-table td {
                border: 1px solid #eee;
                padding: 8px 10px;
            }
            .printable-area .marks-table th {
                background-color: #f0f0f0;
                color: #000;
            }
            .printable-area .pass-status {
                padding: 2px 5px;
                font-size: 0.65em;
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
        <h2><i class="fas fa-graduation-cap"></i> Review Exam Marks</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter and Search Form -->
        <div class="filter-section">
            <form action="review_marks.php" method="GET" style="display:contents;">
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
                    <label for="filter_student_id"><i class="fas fa-user-graduate"></i> Student:</label>
                    <select id="filter_student_id" name="student_id">
                        <option value="">-- All Students --</option>
                        <!-- Options populated by JavaScript -->
                    </select>
                </div>
                <div class="filter-group full-width">
                    <label for="search_query"><i class="fas fa-search"></i> Search Student:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Student Name / Reg. No.">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_exam_type_id || $filter_class_id || $filter_subject_id || $filter_student_id || !empty($search_query)): ?>
                        <a href="review_marks.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printMarksReport()"><i class="fas fa-print"></i> Print Report</button>
                </div>
            </form>
        </div>

        <!-- Exam Marks Display Section -->
        <div class="marks-section-container printable-area">
            <?php if (empty($grouped_marks)): ?>
                <p class="no-results">No exam marks found matching your criteria.</p>
            <?php else: ?>
                <?php $exam_type_index = 0; ?>
                <?php foreach ($grouped_marks as $exam_type_key => $exam_type_data): ?>
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
                            <?php foreach ($exam_type_data['classes'] as $class_key => $class_data): ?>
                                <div class="class-marks-card">
                                    <h4><i class="fas fa-chalkboard"></i> Class: <?php echo htmlspecialchars($class_key); ?></h4>
                                    <div style="overflow-x:auto;">
                                        <table class="marks-table">
                                            <thead>
                                                <tr>
                                                    <th>Reg. No.</th>
                                                    <th>Student Name</th>
                                                    <th>Exam Date</th>
                                                    <th>Subject</th>
                                                    <th>Max Marks</th>
                                                    <th>Passing Marks</th>
                                                    <th>Marks Obtained</th>
                                                    <th>Status</th>
                                                    <th>Uploaded By</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($class_data['entries'] as $mark):
                                                    $is_passed = ($mark['marks_obtained'] >= $mark['passing_marks']);
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($mark['registration_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($mark['first_name'] . ' ' . $mark['last_name']); ?></td>
                                                        <td><?php echo date("M j, Y", strtotime($mark['exam_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($mark['subject_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($mark['max_marks']); ?></td>
                                                        <td><?php echo htmlspecialchars($mark['passing_marks']); ?></td>
                                                        <td><?php echo htmlspecialchars($mark['marks_obtained']); ?></td>
                                                        <td>
                                                            <span class="pass-status <?php echo $is_passed ? 'passed' : 'failed'; ?>">
                                                                <?php echo $is_passed ? 'Passed' : 'Failed'; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($mark['uploaded_by_teacher'] ?: 'N/A'); ?></td>
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
                            $base_url_params = array_filter([
                                'exam_type_id' => $filter_exam_type_id,
                                'class_id' => $filter_class_id,
                                'subject_id' => $filter_subject_id,
                                'student_id' => $filter_student_id,
                                'search' => $search_query
                            ]);
                            $base_url = "review_marks.php?" . http_build_query($base_url_params);
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

        // Initialize collapsed state on page load (all sections start expanded)
        document.querySelectorAll('.marks-section-container .section-header').forEach(header => {
            const contentId = header.getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            
            if (content) { // Ensure content element exists
                content.style.maxHeight = content.scrollHeight + "px"; // Temporarily set height for smooth transition

                setTimeout(() => {
                    content.style.maxHeight = null; // Remove inline style
                }, 10);
            }
        });
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
            option.textContent = `${student.first_name} ${student.last_name} (${student.registration_number})`;
            if (selectedStudentId && student.id == selectedStudentId) {
                option.selected = true;
            }
            studentSelect.appendChild(option);
        });

        // If no classId is selected and there was a selectedStudentId, try to re-add it if it's not already there
        if (!classId && selectedStudentId) {
            const studentIsAlreadyInDropdown = Array.from(studentSelect.options).some(opt => opt.value == selectedStudentId);
            if (!studentIsAlreadyInDropdown) {
                const selectedStudent = allStudentsRaw.find(student => student.id == selectedStudentId);
                if (selectedStudent) {
                    const option = document.createElement('option');
                    option.value = selectedStudent.id;
                    option.textContent = `${selectedStudent.first_name} ${selectedStudent.last_name} (${selectedStudent.registration_number})`;
                    option.selected = true;
                    studentSelect.appendChild(option);
                }
            }
        }
    }

    // Function to print only the marks section
    function printMarksReport() {
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

        const printableContent = document.querySelector('.marks-section-container').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=1000');
        
        printWindow.document.write('<html><head><title>Marks Report Print</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
        // Include relevant styles from this page directly in the print window
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
            h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 15px; }
            .exam-type-group { border: 1px solid #ccc; margin-bottom: 15px; overflow: hidden; page-break-inside: avoid; }
            .section-header { background-color: #e0e0e0; color: #333; padding: 15px 20px; margin: 0; font-size: 14pt; font-weight: 600; display: flex; align-items: center; gap: 10px; }
            .section-header h3 { margin: 0; font-size: 1em; }
            .section-toggle-btn { display: none; }
            .section-content { padding: 15px 0; max-height: none !important; overflow: visible !important; transition: none !important; }
            .class-marks-card { margin: 10px 15px; border: 1px solid #ddd; background-color: #f9f9f9; page-break-inside: avoid; }
            .class-marks-card h4 { color: #555; margin-top: 0; margin-bottom: 15px; font-size: 12pt; display: flex; align-items: center; gap: 8px; border-bottom: 1px dashed #ccc; padding-bottom: 10px; }
            .marks-table { width: 100%; border-collapse: collapse; margin-top: 0; font-size: 0.95em; }
            .marks-table th, .marks-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
            .marks-table th { background-color: #f0f0f0; color: #000; font-weight: 700; text-transform: uppercase; }
            .marks-table tr:nth-child(even) { background-color: #fcfcfc; }
            .pass-status { padding: 2px 5px; border-radius: 5px; font-weight: 600; white-space: nowrap; font-size: 0.65em; }
            .pass-status.passed { background-color: #d4edda; color: #155724; }
            .pass-status.failed { background-color: #f8d7da; color: #721c24; }
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
        // printWindow.close(); // Optionally close after printing, but users might want to review
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>