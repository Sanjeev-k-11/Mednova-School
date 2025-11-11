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

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Filter Parameters ---
$filter_class_id = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$filter_subject_id = isset($_GET['subject_id']) && is_numeric($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
$filter_teacher_id = isset($_GET['teacher_id']) && is_numeric($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Pagination Configuration ---
$records_per_page = 10; // Number of tests to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;


// --- Fetch Filter Dropdown Data ---
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_subjects = [];
$sql_all_subjects = "SELECT id, subject_name FROM subjects ORDER BY subject_name ASC";
if ($result = mysqli_query($link, $sql_all_subjects)) {
    $all_subjects = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_teachers = [];
$sql_all_teachers = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_all_teachers)) {
    $all_teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$test_statuses = ['Draft', 'Published'];


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_class_id) {
    $where_clauses[] = "ot.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if ($filter_subject_id) {
    $where_clauses[] = "ot.subject_id = ?";
    $params[] = $filter_subject_id;
    $types .= "i";
}
if ($filter_teacher_id) {
    $where_clauses[] = "ot.teacher_id = ?";
    $params[] = $filter_teacher_id;
    $types .= "i";
}
if ($filter_status) {
    $where_clauses[] = "ot.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(ot.title LIKE ? OR ot.description LIKE ? OR t.full_name LIKE ? OR s.subject_name LIKE ? OR c.class_name LIKE ? OR c.section_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssssss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(ot.id)
                      FROM online_tests ot
                      JOIN classes c ON ot.class_id = c.id
                      JOIN subjects s ON ot.subject_id = s.id
                      LEFT JOIN teachers t ON ot.teacher_id = t.id
                      WHERE " . $where_sql;

if ($stmt = mysqli_prepare($link, $total_records_sql)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_records = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
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


// --- Fetch Online Tests Data (with filters and pagination) ---
$online_tests = [];
$sql_fetch_tests = "SELECT
                            ot.id, ot.title, ot.description, ot.time_limit_minutes, ot.status, ot.created_at,
                            ot.created_by, -- Assuming created_by is a free text field or role if not teacher_id
                            t.full_name AS teacher_creator_name,
                            c.class_name, c.section_name,
                            s.subject_name,
                            (SELECT COUNT(otq.id) FROM online_test_questions otq WHERE otq.test_id = ot.id) AS total_questions,
                            (SELECT SUM(otq.marks) FROM online_test_questions otq WHERE otq.test_id = ot.id) AS total_test_marks,
                            (SELECT COUNT(sta.id) FROM student_test_attempts sta WHERE sta.test_id = ot.id AND sta.status = 'Completed') AS completed_attempts_count
                        FROM online_tests ot
                        JOIN classes c ON ot.class_id = c.id
                        JOIN subjects s ON ot.subject_id = s.id
                        LEFT JOIN teachers t ON ot.teacher_id = t.id
                        WHERE " . $where_sql . "
                        ORDER BY ot.created_at DESC
                        LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_tests)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $online_tests = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
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
    <title>View Online Tests - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #F0F8FF, #E6E6FA, #D8BFD8, #ADD8E6); /* Soft blues and purples */
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
            color: #483D8B; /* Dark Slate Blue */
            margin-bottom: 30px;
            border-bottom: 2px solid #E6E6FA;
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
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }


        /* Filter Section */
        .filter-section {
            background-color: #f8f8ff; /* GhostWhite background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e6e6fa;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group.wide { flex: 2; min-width: 250px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #483D8B; }
        .filter-group select, .filter-group input[type="text"] {
            width: 100%; padding: 10px 12px; border: 1px solid #d8bfd8; border-radius: 5px;
            font-size: 1rem; box-sizing: border-box; background-color: #fff; color: #333;
        }
        .filter-group select {
            appearance: none; -webkit-appearance: none; -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23483D8B%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23483D8B%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat; background-position: right 10px center; background-size: 14px; padding-right: 30px;
        }
        .filter-buttons { display: flex; gap: 10px; flex-shrink: 0; margin-top: 10px; }
        .btn-filter, .btn-clear-filter, .btn-print {
            padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 600;
            transition: background-color 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-filter { background-color: #6A5ACD; color: #fff; border: 1px solid #6A5ACD; }
        .btn-filter:hover { background-color: #483D8B; }
        .btn-clear-filter { background-color: #808080; color: #fff; border: 1px solid #808080; }
        .btn-clear-filter:hover { background-color: #696969; }
        .btn-print { background-color: #20B2AA; color: #fff; border: 1px solid #20B2AA; }
        .btn-print:hover { background-color: #1A968A; }


        /* Tests Table Display */
        .tests-section-container {
            background-color: #fefefe; padding: 30px; border-radius: 10px; margin-bottom: 40px;
            border: 1px solid #e0e0e0; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        h3 { color: #483D8B; margin-bottom: 25px; font-size: 1.8em; display: flex; align-items: center; gap: 10px; }
        .tests-table {
            width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px;
            border: 1px solid #d8bfd8; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .tests-table th, .tests-table td { border-bottom: 1px solid #e0e0e0; padding: 15px; text-align: left; vertical-align: middle; }
        .tests-table th { background-color: #e6e6fa; color: #483D8B; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; }
        .tests-table tr:nth-child(even) { background-color: #f8f0ff; }
        .tests-table tr:hover { background-color: #efe8fa; }
        .text-center { text-align: center; }
        .text-muted { color: #6c757d; }
        .no-results { text-align: center; padding: 50px; font-size: 1.2em; color: #6c757d; }
        
        .status-badge { padding: 5px 10px; border-radius: 5px; font-size: 0.8em; font-weight: 600; white-space: nowrap; }
        .status-Draft { background-color: #ffc107; color: #856404; } /* Amber/Yellow */
        .status-Published { background-color: #28a745; color: #fff; } /* Green */
        .action-buttons-group { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .btn-action { padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; border: 1px solid transparent; }
        .btn-view-details { background-color: #6495ED; color: #fff; border-color: #6495ED; }
        .btn-view-details:hover { background-color: #4682B4; border-color: #4682B4; }


        /* Pagination Styles */
        .pagination-container {
            display: flex; justify-content: space-between; align-items: center; margin-top: 25px;
            padding: 10px 0; border-top: 1px solid #eee; flex-wrap: wrap; gap: 10px;
        }
        .pagination-info { color: #555; font-size: 0.95em; font-weight: 500; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-controls a, .pagination-controls span {
            display: block; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;
            text-decoration: none; color: #6A5ACD; background-color: #fff; transition: all 0.2s ease;
        }
        .pagination-controls a:hover { background-color: #e9ecef; border-color: #d8bfd8; }
        .pagination-controls .current-page, .pagination-controls .current-page:hover {
            background-color: #6A5ACD; color: #fff; border-color: #6A5ACD; cursor: default;
        }
        .pagination-controls .disabled, .pagination-controls .disabled:hover {
            color: #6c757d; background-color: #e9ecef; border-color: #dee2e6; cursor: not-allowed;
        }

        /* Modal Styles */
        .modal {
            display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center;
        }
        .modal-content {
            background-color: #fefefe; margin: auto; padding: 30px; border: 1px solid #888;
            width: 80%; max-width: 800px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative;
        }
        .modal-header { padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h4 { margin: 0; color: #333; font-size: 1.5em; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.3s ease; }
        .close-btn:hover { color: #000; }
        .modal-body { max-height: 70vh; overflow-y: auto; padding-right: 15px; }
        .modal-body p { margin-bottom: 10px; line-height: 1.5; }
        .modal-body strong { color: #555; }
        .modal-body .test-details { border-bottom: 1px dashed #ddd; padding-bottom: 15px; margin-bottom: 15px; }
        .modal-body .questions-list { margin-top: 20px; }
        .modal-body .question-item { 
            background-color: #f8f8ff; border: 1px solid #e6e6fa; border-radius: 8px; padding: 10px 15px; margin-bottom: 10px;
            display: flex; flex-direction: column;
        }
        .modal-body .question-text { font-weight: 600; color: #483D8B; font-size: 1em; margin-bottom: 5px; }
        .modal-body .options-list { list-style: none; padding: 0; margin: 0; font-size: 0.9em; }
        .modal-body .options-list li { margin-bottom: 3px; color: #555; }
        .modal-body .options-list .correct-option { font-weight: bold; color: #28a745; }
        .modal-body .question-marks { font-size: 0.8em; color: #888; text-align: right; margin-top: 5px; }

        .modal-footer { margin-top: 20px; text-align: right; }
        .btn-modal-close { background-color: #6c757d; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }


        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; font-size: 10pt; padding: 10mm; }
            .printable-area h2, .printable-area h3 { color: #000; border-bottom: 1px solid #ccc; font-size: 16pt; margin-bottom: 15px; }
            .printable-area .tests-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .printable-area .tests-table th, .printable-area .tests-table td { border: 1px solid #eee; padding: 8px 10px; }
            .printable-area .tests-table th { background-color: #e6e6fa; color: #000; }
            .printable-area .status-badge { padding: 3px 6px; font-size: 0.7em; }
            .printable-area .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group, .modal { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-group.wide { min-width: unset; }
            .filter-buttons { flex-direction: column; width: 100%; }
            .btn-filter, .btn-clear-filter, .btn-print { width: 100%; justify-content: center; }
            .tests-table { display: block; overflow-x: auto; white-space: nowrap; }
            .modal-content { width: 95%; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-laptop-code"></i> View All Online Tests</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter and Search Form -->
        <div class="filter-section">
            <form action="view_all_online_tests.php" method="GET" style="display:contents;">
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
                    <label for="filter_teacher_id"><i class="fas fa-chalkboard-teacher"></i> Creator Teacher:</label>
                    <select id="filter_teacher_id" name="teacher_id">
                        <option value="">-- All Teachers --</option>
                        <?php foreach ($all_teachers as $teacher): ?>
                            <option value="<?php echo htmlspecialchars($teacher['id']); ?>"
                                <?php echo ($filter_teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_status"><i class="fas fa-info-circle"></i> Status:</label>
                    <select id="filter_status" name="status">
                        <option value="">-- All Statuses --</option>
                        <?php foreach ($test_statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>"
                                <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group wide">
                    <label for="search_query"><i class="fas fa-search"></i> Search Tests:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Title, Description, Creator">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_class_id || $filter_subject_id || $filter_teacher_id || $filter_status || !empty($search_query)): ?>
                        <a href="view_all_online_tests.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printOnlineTests()"><i class="fas fa-print"></i> Print Tests</button>
                </div>
            </form>
        </div>

        <!-- Online Tests Overview Table -->
        <div class="tests-section-container printable-area">
            <h3><i class="fas fa-list-alt"></i> All Online Tests Overview</h3>
            <?php if (empty($online_tests)): ?>
                <p class="no-results">No online tests found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x:auto;" id="tests-table-wrapper">
                    <table class="tests-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Creator</th>
                                <th>Time Limit</th>
                                <th>Questions</th>
                                <th>Total Marks</th>
                                <th>Attempts</th>
                                <th>Status</th>
                                <th>Created On</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($online_tests as $test): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($test['title']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($test['description'] ?: 'N/A', 0, 100)); ?>
                                        <?php if (strlen($test['description'] ?: '') > 100): ?>
                                            ... <a href="#" onclick="alert('Full Description: <?php echo htmlspecialchars($test['description']); ?>'); return false;" class="text-muted">more</a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($test['class_name'] . ' - ' . $test['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($test['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($test['teacher_creator_name'] ?: ($test['created_by'] ?: 'N/A')); ?></td>
                                    <td><?php echo htmlspecialchars($test['time_limit_minutes']); ?> min</td>
                                    <td><?php echo htmlspecialchars($test['total_questions'] ?: '0'); ?></td>
                                    <td><?php echo htmlspecialchars($test['total_test_marks'] ?: '0'); ?></td>
                                    <td><?php echo htmlspecialchars($test['completed_attempts_count'] ?: '0'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($test['status']); ?>">
                                            <?php echo htmlspecialchars($test['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date("M j, Y", strtotime($test['created_at'])); ?></td>
                                    <td class="text-center">
                                        <div class="action-buttons-group">
                                            <button class="btn-action btn-view-details" onclick="viewTestDetails(<?php echo htmlspecialchars(json_encode($test)); ?>)">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_records > 0): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> tests
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $base_url_params = array_filter([
                                'class_id' => $filter_class_id,
                                'subject_id' => $filter_subject_id,
                                'teacher_id' => $filter_teacher_id,
                                'status' => $filter_status,
                                'search' => $search_query
                            ]);
                            $base_url = "view_all_online_tests.php?" . http_build_query($base_url_params);
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

<!-- Test Details Modal -->
<div id="testModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="test-modal-title">Test Details: <span id="modal-test-title"></span></h4>
      <span class="close-btn" onclick="closeTestModal()">&times;</span>
    </div>
    <div class="modal-body">
      <div class="test-details">
        <p><strong>Class:</strong> <span id="modal-test-class"></span></p>
        <p><strong>Subject:</strong> <span id="modal-test-subject"></span></p>
        <p><strong>Creator:</strong> <span id="modal-test-creator"></span></p>
        <p><strong>Description:</strong> <span id="modal-test-description"></span></p>
        <p><strong>Time Limit:</strong> <span id="modal-test-time-limit"></span> minutes</p>
        <p><strong>Total Questions:</strong> <span id="modal-test-total-questions"></span></p>
        <p><strong>Total Marks:</strong> <span id="modal-test-total-marks"></span></p>
        <p><strong>Status:</strong> <span id="modal-test-status" class="status-badge"></span></p>
        <p><strong>Completed Attempts:</strong> <span id="modal-test-completed-attempts"></span></p>
        <hr>
      </div>

      <div class="questions-list">
        <h4>Test Questions:</h4>
        <div id="modal-questions-content">
          <!-- Questions will be loaded here via JS -->
          <p class="text-muted text-center">Loading questions...</p>
        </div>
      </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn-modal-close" onclick="closeTestModal()">Close</button>
    </div>
  </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // If a test ID is passed in the URL (e.g., direct link), open the details modal
        const viewTestId = <?php echo isset($_GET['view_test_id']) ? json_encode((int)$_GET['view_test_id']) : 'null'; ?>;
        if (viewTestId) {
            const testData = <?php echo json_encode($online_tests); ?>.find(t => t.id === viewTestId);
            if (testData) {
                viewTestDetails(testData);
            }
        }
    });

    // --- Test Details Modal JS ---
    const testModal = document.getElementById('testModal');
    const modalTestTitle = document.getElementById('modal-test-title');
    const modalTestClass = document.getElementById('modal-test-class');
    const modalTestSubject = document.getElementById('modal-test-subject');
    const modalTestCreator = document.getElementById('modal-test-creator');
    const modalTestDescription = document.getElementById('modal-test-description');
    const modalTestTimeLimit = document.getElementById('modal-test-time-limit');
    const modalTestTotalQuestions = document.getElementById('modal-test-total-questions');
    const modalTestTotalMarks = document.getElementById('modal-test-total-marks');
    const modalTestStatus = document.getElementById('modal-test-status');
    const modalTestCompletedAttempts = document.getElementById('modal-test-completed-attempts');
    const modalQuestionsContent = document.getElementById('modal-questions-content');
    let currentTestIdForModal = null;

    async function viewTestDetails(testData) {
        currentTestIdForModal = testData.id;

        modalTestTitle.textContent = testData.title;
        modalTestClass.textContent = `${testData.class_name} - ${testData.section_name}`;
        modalTestSubject.textContent = testData.subject_name;
        modalTestCreator.textContent = testData.teacher_creator_name || testData.created_by || 'N/A';
        modalTestDescription.textContent = testData.description || 'No description provided.';
        modalTestTimeLimit.textContent = testData.time_limit_minutes;
        modalTestTotalQuestions.textContent = testData.total_questions || '0';
        modalTestTotalMarks.textContent = testData.total_test_marks || '0';
        
        modalTestStatus.textContent = testData.status;
        modalTestStatus.className = `status-badge status-${testData.status}`; // Apply dynamic status class

        modalTestCompletedAttempts.textContent = testData.completed_attempts_count || '0';

        // Fetch and display questions for this test
        modalQuestionsContent.innerHTML = '<p class="text-muted text-center">Loading questions...</p>';
        try {
            const response = await fetch(`fetch_test_questions.php?test_id=${testData.id}`);
            const questions = await response.json();
            modalQuestionsContent.innerHTML = ''; // Clear loading message

            if (questions.length === 0) {
                modalQuestionsContent.innerHTML = '<p class="text-muted text-center">No questions found for this test.</p>';
            } else {
                questions.forEach((q, index) => {
                    const questionItem = document.createElement('div');
                    questionItem.className = 'question-item';
                    
                    questionItem.innerHTML = `
                        <div class="question-text">${index + 1}. ${htmlspecialchars(q.question_text)}</div>
                        <ul class="options-list">
                            <li class="${q.correct_option === 'A' ? 'correct-option' : ''}">A) ${htmlspecialchars(q.option_a)}</li>
                            <li class="${q.correct_option === 'B' ? 'correct-option' : ''}">B) ${htmlspecialchars(q.option_b)}</li>
                            <li class="${q.correct_option === 'C' ? 'correct-option' : ''}">C) ${htmlspecialchars(q.option_c)}</li>
                            <li class="${q.correct_option === 'D' ? 'correct-option' : ''}">D) ${htmlspecialchars(q.option_d)}</li>
                        </ul>
                        <div class="question-marks">Marks: ${q.marks}</div>
                    `;
                    modalQuestionsContent.appendChild(questionItem);
                });
            }
        } catch (error) {
            console.error('Error fetching questions:', error);
            modalQuestionsContent.innerHTML = '<p class="text-muted text-center">Error loading questions.</p>';
        }

        testModal.style.display = 'flex'; // Show modal
    }

    function closeTestModal() {
        testModal.style.display = 'none';
        currentTestIdForModal = null; // Clear active test ID
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == testModal) {
            closeTestModal();
        }
    }

    // Helper to format datetime string for display
    function formatDateTime(datetimeString) {
        if (!datetimeString || datetimeString === '0000-00-00 00:00:00') return 'N/A';
        const date = new Date(datetimeString);
        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString(undefined, options);
    }
    
    // Simple HTML escaping for display purposes in JS
    function htmlspecialchars(str) {
        let div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }


    // --- Print Functionality ---
    window.printOnlineTests = function() {
        const printableContent = document.querySelector('.tests-section-container').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=1000');

        printWindow.document.write('<html><head><title>Online Tests Report</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
            h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 25px; text-align: center; }
            h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
            .tests-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .tests-table th, .tests-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
            .tests-table th { background-color: #e6e6fa; color: #000; font-weight: 700; text-transform: uppercase; }
            .tests-table tr:nth-child(even) { background-color: #f8f0ff; }
            .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
            .status-Draft { background-color: #ffc107; color: #856404; }
            .status-Published { background-color: #28a745; color: #fff; }
            .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group, .modal { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(`<h2 style="text-align: center;">Online Tests Report</h2>`);
        printWindow.document.write(printableContent);
        printWindow.document.write('</body></html>');

        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    };
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>