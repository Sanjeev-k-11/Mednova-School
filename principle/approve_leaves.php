<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"]; // Primary key ID of the logged-in principal (for audit logs)
$principal_name = $_SESSION["full_name"];
$principal_role = $_SESSION["role"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Filter Parameters ---
$filter_class_id = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$filter_student_id = isset($_GET['student_id']) && is_numeric($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Pagination Configuration ---
$records_per_page = 10; // Number of leave applications to display per page
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

$all_students_raw = []; // All students for dynamic JS filtering
$sql_all_students_raw = "SELECT id, first_name, last_name, registration_number, class_id FROM students ORDER BY first_name ASC, last_name ASC";
if ($result = mysqli_query($link, $sql_all_students_raw)) {
    $all_students_raw = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching students for filter: " . mysqli_error($link);
    $message_type = "danger";
}

$leave_statuses = ['Pending', 'Approved', 'Rejected'];


// --- Process Leave Application Actions (Approve/Reject) ---
if (isset($_POST['action']) && ($_POST['action'] == 'approve_leave' || $_POST['action'] == 'reject_leave')) {
    $application_id = (int)$_POST['application_id'];
    $notes = trim($_POST['notes']);
    $action_type = $_POST['action']; // 'approve_leave' or 'reject_leave'

    if (empty($application_id)) {
        set_session_message("Invalid application ID for action.", "danger");
        header("location: approve_leaves.php");
        exit;
    }

    $status_update = ($action_type == 'approve_leave') ? 'Approved' : 'Rejected';

    $sql = "UPDATE leave_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW(), notes = ? WHERE id = ? AND status = 'Pending'";
    if ($stmt = mysqli_prepare($link, $sql)) {
        $reviewed_by_name_and_role = $principal_full_name . " (" . $principal_role . ")"; // Store full name and role
        mysqli_stmt_bind_param($stmt, "sssi", $status_update, $reviewed_by_name_and_role, $notes, $application_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Leave application " . strtolower($status_update) . " successfully.", "success");
            } else {
                set_session_message("Leave application not found or its status has already changed.", "danger");
            }
        } else {
            set_session_message("Error " . strtolower($status_update) . " leave application: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: approve_leaves.php?page={$current_page}");
    exit;
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_class_id) {
    $where_clauses[] = "la.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if ($filter_student_id) {
    $where_clauses[] = "la.student_id = ?";
    $params[] = $filter_student_id;
    $types .= "i";
}
if ($filter_status) {
    $where_clauses[] = "la.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.registration_number LIKE ? OR la.reason LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(la.id)
                      FROM leave_applications la
                      JOIN students s ON la.student_id = s.id
                      JOIN classes c ON la.class_id = c.id
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
    $message = "Error counting leave applications: " . mysqli_error($link);
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


// --- Fetch Leave Applications Data (with filters and pagination) ---
$leave_applications = [];
$sql_fetch_applications = "SELECT
                                la.id, la.leave_from, la.leave_to, la.reason, la.status, la.created_at,
                                la.reviewed_by, la.reviewed_at, la.notes AS review_notes,
                                s.first_name, s.last_name, s.registration_number,
                                c.class_name, c.section_name,
                                t_class.full_name AS class_teacher_name
                            FROM leave_applications la
                            JOIN students s ON la.student_id = s.id
                            JOIN classes c ON la.class_id = c.id
                            LEFT JOIN teachers t_class ON la.class_teacher_id = t_class.id
                            WHERE " . $where_sql . "
                            ORDER BY la.created_at DESC
                            LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_applications)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $leave_applications = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching leave applications: " . mysqli_error($link);
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
    <title>Approve Leaves - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #FFF0F5, #ADD8E6, #98FB98, #B0E0E6); /* Soft pink, blue, green */
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
            color: #8A2BE2; /* BlueViolet */
            margin-bottom: 30px;
            border-bottom: 2px solid #E0BBE4;
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
            background-color: #f8f0ff; /* Light purple background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e6d9ff;
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
            color: #8A2BE2;
        }
        .filter-group select,
        .filter-group input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d8bfd8;
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
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%238A2BE2%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%238A2BE2%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
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
            background-color: #9370DB; /* Medium Purple */
            color: #fff;
            border: 1px solid #9370DB;
        }
        .btn-filter:hover {
            background-color: #8A2BE2;
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


        /* Leave Applications Table Display */
        .applications-section-container {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        h3 {
            color: #8A2BE2;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .applications-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #d8bfd8;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .applications-table th, .applications-table td {
            border-bottom: 1px solid #e0e0e0;
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }
        .applications-table th {
            background-color: #e6e6fa; /* Lavender background */
            color: #483d8b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .applications-table tr:nth-child(even) {
            background-color: #f8f0ff;
        }
        .applications-table tr:hover {
            background-color: #efe8fa;
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
        .status-Pending { background-color: #fff3cd; color: #856404; } /* Yellow */
        .status-Approved { background-color: #d4edda; color: #155724; } /* Green */
        .status-Rejected { background-color: #f8d7da; color: #721c24; } /* Red */

        .action-buttons-group {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-action {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            border: 1px solid transparent;
        }
        .btn-approve { background-color: #28a745; color: #fff; border-color: #28a745; }
        .btn-approve:hover { background-color: #218838; border-color: #1e7e34; }
        .btn-reject { background-color: #dc3545; color: #fff; border-color: #dc3545; }
        .btn-reject:hover { background-color: #c82333; border-color: #bd2130; }

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
            color: #9370DB;
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .pagination-controls a:hover {
            background-color: #e9ecef;
            border-color: #d8bfd8;
        }
        .pagination-controls .current-page,
        .pagination-controls .current-page:hover {
            background-color: #9370DB;
            color: #fff;
            border-color: #9370DB;
            cursor: default;
        }
        .pagination-controls .disabled,
        .pagination-controls .disabled:hover {
            color: #6c757d;
            background-color: #e9ecef;
            border-color: #dee2e6;
            cursor: not-allowed;
        }

        /* Modal Styles */
        .modal {
            display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.4); justify-content: center; align-items: center;
        }
        .modal-content {
            background-color: #fefefe; margin: auto; padding: 30px; border: 1px solid #888;
            width: 80%; max-width: 500px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative;
        }
        .modal-header { padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h4 { margin: 0; color: #333; font-size: 1.5em; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.3s ease; }
        .close-btn:hover, .close-btn:focus { color: #000; text-decoration: none; }
        .modal-body textarea { min-height: 100px; resize: vertical; width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; box-sizing: border-box; }
        .modal-footer { margin-top: 20px; text-align: right; }
        .btn-modal-submit { background-color: #9370DB; color: white; }
        .btn-modal-submit:hover { background-color: #8A2BE2; }
        .btn-modal-cancel { background-color: #6c757d; color: white; }
        .btn-modal-cancel:hover { background-color: #5a6268; }

        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; font-size: 10pt; padding: 10mm; }
            .printable-area h2, .printable-area h3 { color: #000; border-bottom: 1px solid #ccc; font-size: 16pt; margin-bottom: 15px; }
            .printable-area .applications-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .printable-area .applications-table th, .printable-area .applications-table td { border: 1px solid #eee; padding: 8px 10px; }
            .printable-area .applications-table th { background-color: #e6e6fa; color: #000; }
            .printable-area .status-badge { padding: 3px 6px; font-size: 0.7em; }
            .printable-area .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group, .modal { display: none; }
            .fas { margin-right: 3px; }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-group.full-width { min-width: unset; }
            .filter-buttons { flex-direction: column; width: 100%; }
            .btn-filter, .btn-clear-filter, .btn-print { width: 100%; justify-content: center; }
            .applications-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-clipboard-list"></i> Approve Leave Applications</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter and Search Form -->
        <div class="filter-section">
            <form action="approve_leaves.php" method="GET" style="display:contents;">
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
                    <label for="filter_status"><i class="fas fa-info-circle"></i> Status:</label>
                    <select id="filter_status" name="status">
                        <option value="">-- All Statuses --</option>
                        <?php foreach ($leave_statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>"
                                <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group full-width">
                    <label for="search_query"><i class="fas fa-search"></i> Search:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Student Name / Reason">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_class_id || $filter_student_id || $filter_status || !empty($search_query)): ?>
                        <a href="approve_leaves.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printLeaveApplications()"><i class="fas fa-print"></i> Print Report</button>
                </div>
            </form>
        </div>

        <!-- Leave Applications Table -->
        <div class="applications-section-container printable-area">
            <h3><i class="fas fa-list-alt"></i> Leave Applications Overview</h3>
            <?php if (empty($leave_applications)): ?>
                <p class="no-results">No leave applications found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="applications-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Reg. No.</th>
                                <th>Class</th>
                                <th>Class Teacher</th>
                                <th>Leave From</th>
                                <th>Leave To</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th>Review Notes</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_applications as $app): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($app['class_name'] . ' - ' . $app['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['class_teacher_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo date("M j, Y", strtotime($app['leave_from'])); ?></td>
                                    <td><?php echo date("M j, Y", strtotime($app['leave_to'])); ?></td>
                                    <td><?php echo htmlspecialchars($app['reason']); ?></td>
                                    <td><?php echo date("M j, Y", strtotime($app['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($app['status'])); ?>">
                                            <?php echo htmlspecialchars($app['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['reviewed_by'] ?: 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                            if (!empty($app['review_notes'])) {
                                                echo htmlspecialchars($app['review_notes']);
                                                if ($app['reviewed_at']) {
                                                    echo ' (' . date("M j, Y", strtotime($app['reviewed_at'])) . ')';
                                                }
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons-group">
                                            <?php if ($app['status'] == 'Pending'): ?>
                                                <button class="btn-action btn-approve" onclick="showActionModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>', 'approve')">
                                                    <i class="fas fa-check-circle"></i> Approve
                                                </button>
                                                <button class="btn-action btn-reject" onclick="showActionModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>', 'reject')">
                                                    <i class="fas fa-times-circle"></i> Reject
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">No actions</span>
                                            <?php endif; ?>
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
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $base_url_params = array_filter([
                                'class_id' => $filter_class_id,
                                'student_id' => $filter_student_id,
                                'status' => $filter_status,
                                'search' => $search_query
                            ]);
                            $base_url = "approve_leaves.php?" . http_build_query($base_url_params);
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

<!-- Approve/Reject Leave Modal -->
<div id="actionModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="modal-title"></h4>
      <span class="close-btn" onclick="closeActionModal()">&times;</span>
    </div>
    <form id="actionForm" action="approve_leaves.php" method="POST">
      <input type="hidden" name="action" id="modal-action">
      <input type="hidden" name="application_id" id="modal-application-id">
      <div class="modal-body">
        <div class="form-group">
          <label for="notes-text">Notes / Reason (Optional):</label>
          <textarea id="notes-text" name="notes" rows="4" placeholder="Enter any notes or reasons for this action"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-form-cancel" onclick="closeActionModal()">Cancel</button>
        <button type="submit" class="btn-form-submit" id="modal-submit-btn">Submit</button>
      </div>
    </form>
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

        // If no classId is selected and there was a selectedStudentId, re-add it if it's not already there
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

    // --- Modal Functions ---
    const actionModal = document.getElementById('actionModal');
    const modalTitle = document.getElementById('modal-title');
    const modalAction = document.getElementById('modal-action');
    const modalApplicationId = document.getElementById('modal-application-id');
    const notesText = document.getElementById('notes-text');
    const modalSubmitBtn = document.getElementById('modal-submit-btn');

    function showActionModal(applicationId, studentName, action) {
        notesText.value = ''; // Clear previous notes
        modalApplicationId.value = applicationId;

        if (action === 'approve') {
            modalTitle.innerHTML = `<i class="fas fa-check-circle"></i> Approve Leave for ${studentName}`;
            modalAction.value = 'approve_leave';
            notesText.placeholder = 'Optional notes for approval (e.g., Granted, Approved with conditions).';
            modalSubmitBtn.innerHTML = 'Approve Leave';
            modalSubmitBtn.classList.remove('btn-modal-submit-reject'); // Ensure correct class
            modalSubmitBtn.classList.add('btn-modal-submit'); // Default submit button style

        } else if (action === 'reject') {
            modalTitle.innerHTML = `<i class="fas fa-times-circle"></i> Reject Leave for ${studentName}`;
            modalAction.value = 'reject_leave';
            notesText.placeholder = 'Optional notes for rejection (e.g., Insufficient reason, Not approved).';
            modalSubmitBtn.innerHTML = 'Reject Leave';
            modalSubmitBtn.classList.remove('btn-modal-submit'); // Remove default
            modalSubmitBtn.classList.add('btn-modal-submit-reject'); // Use reject specific style
        }
        actionModal.style.display = 'flex'; // Show modal
    }

    function closeActionModal() {
        actionModal.style.display = 'none'; // Hide modal
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == actionModal) {
            closeActionModal();
        }
    }

    // Function to print only the applications section
    window.printLeaveApplications = function() {
        const printableContent = document.querySelector('.applications-section-container').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=1000');

        printWindow.document.write('<html><head><title>Leave Applications Report</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
            h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 15px; }
            h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
            .applications-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .applications-table th, .applications-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
            .applications-table th { background-color: #e6e6fa; color: #000; font-weight: 700; text-transform: uppercase; }
            .applications-table tr:nth-child(even) { background-color: #f8f0ff; }
            .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
            .status-Pending { background-color: #fff3cd; color: #856404; }
            .status-Approved { background-color: #d4edda; color: #155724; }
            .status-Rejected { background-color: #f8d7da; color: #721c24; }
            .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group, .modal { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
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