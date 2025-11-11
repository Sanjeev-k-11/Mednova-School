<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"]; // Primary key ID of the logged-in principal (for audit logs)
$principal_full_name = $_SESSION["full_name"];
$principal_role = $_SESSION["role"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Filter Parameters ---
$filter_reported_by_teacher_id = isset($_GET['reported_by_teacher_id']) && is_numeric($_GET['reported_by_teacher_id']) ? (int)$_GET['reported_by_teacher_id'] : null;
$filter_target_type = isset($_GET['target_type']) ? trim($_GET['target_type']) : null;
$filter_reported_student_id = isset($_GET['reported_student_id']) && is_numeric($_GET['reported_student_id']) ? (int)$_GET['reported_student_id'] : null;
$filter_reported_teacher_id = isset($_GET['reported_teacher_id']) && is_numeric($_GET['reported_teacher_id']) ? (int)$_GET['reported_teacher_id'] : null;
$filter_severity = isset($_GET['severity']) ? trim($_GET['severity']) : null;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : null;
$filter_class_id = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Pagination Configuration ---
$records_per_page = 10; // Number of reports to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;


// --- Fetch Filter Dropdown Data ---
$all_reporting_teachers = [];
$sql_reporting_teachers = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_reporting_teachers)) {
    $all_reporting_teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_students_raw = []; // All students for dynamic JS filtering
$sql_all_students_raw = "SELECT id, first_name, last_name, registration_number, class_id FROM students ORDER BY first_name ASC, last_name ASC";
if ($result = mysqli_query($link, $sql_all_students_raw)) {
    $all_students_raw = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_teachers_raw = []; // All teachers for dynamic JS filtering (excluding reporting teachers for clarity)
$sql_all_teachers_raw = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_all_teachers_raw)) {
    $all_teachers_raw = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$target_types = ['Student', 'Teacher'];
$severities = ['Minor', 'Moderate', 'Serious', 'Critical'];
$report_statuses = ['Pending Review', 'Reviewed - Warning Issued', 'Reviewed - Further Action', 'Reviewed - No Action', 'Closed'];


// --- Process Report Review Action ---
if (isset($_POST['action']) && $_POST['action'] == 'review_report') {
    $report_id = (int)$_POST['report_id'];
    $new_status = trim($_POST['new_status']);
    $review_notes = trim($_POST['review_notes']);
    $final_action = trim($_POST['final_action']);

    if (empty($report_id) || empty($new_status)) {
        set_session_message("Invalid report ID or new status for review.", "danger");
        header("location: review_indiscipline.php");
        exit;
    }

    $sql = "UPDATE indiscipline_reports SET status = ?, reviewed_by_admin_id = ?, review_date = NOW(), review_notes = ?, final_action_taken = ? WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sisssi", $new_status, $principal_id, $review_notes, $final_action, $report_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Indiscipline report reviewed successfully. Status: " . htmlspecialchars($new_status), "success");
            } else {
                set_session_message("Indiscipline report not found or its status has already changed.", "danger");
            }
        } else {
            set_session_message("Error reviewing report: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: review_indiscipline.php?page={$current_page}");
    exit;
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_reported_by_teacher_id) {
    $where_clauses[] = "ir.reported_by_teacher_id = ?";
    $params[] = $filter_reported_by_teacher_id;
    $types .= "i";
}
if ($filter_target_type) {
    $where_clauses[] = "ir.target_type = ?";
    $params[] = $filter_target_type;
    $types .= "s";
}
if ($filter_reported_student_id) {
    $where_clauses[] = "ir.reported_student_id = ?";
    $params[] = $filter_reported_student_id;
    $types .= "i";
}
if ($filter_reported_teacher_id) {
    $where_clauses[] = "ir.reported_teacher_id = ?";
    $params[] = $filter_reported_teacher_id;
    $types .= "i";
}
if ($filter_severity) {
    $where_clauses[] = "ir.severity = ?";
    $params[] = $filter_severity;
    $types .= "s";
}
if ($filter_status) {
    $where_clauses[] = "ir.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($filter_class_id) {
    $where_clauses[] = "ir.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(s_rep.first_name LIKE ? OR s_rep.last_name LIKE ? OR s_rep.registration_number LIKE ? OR t_rep.full_name LIKE ? OR ir.description LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sssss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(ir.id)
                      FROM indiscipline_reports ir
                      LEFT JOIN teachers t_by ON ir.reported_by_teacher_id = t_by.id
                      LEFT JOIN students s_rep ON ir.reported_student_id = s_rep.id
                      LEFT JOIN teachers t_rep ON ir.reported_teacher_id = t_rep.id
                      LEFT JOIN classes c ON ir.class_id = c.id
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
    $message = "Error counting indiscipline reports: " . mysqli_error($link);
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


// --- Fetch Indiscipline Reports Data (with filters and pagination) ---
$indiscipline_reports = [];
$sql_fetch_reports = "SELECT
                            ir.id, ir.target_type, ir.incident_date, ir.incident_time, ir.location,
                            ir.description, ir.evidence_url, ir.severity, ir.immediate_action_taken,
                            ir.status, ir.review_notes, ir.final_action_taken, ir.created_at, ir.review_date,
                            t_by.full_name AS reported_by_teacher_name,
                            s_rep.first_name AS reported_student_first_name, s_rep.last_name AS reported_student_last_name, s_rep.registration_number,
                            t_rep.full_name AS reported_teacher_name,
                            c.class_name, c.section_name,
                            adm_rev.full_name AS reviewed_by_admin_name
                        FROM indiscipline_reports ir
                        LEFT JOIN teachers t_by ON ir.reported_by_teacher_id = t_by.id
                        LEFT JOIN students s_rep ON ir.reported_student_id = s_rep.id
                        LEFT JOIN teachers t_rep ON ir.reported_teacher_id = t_rep.id
                        LEFT JOIN classes c ON ir.class_id = c.id
                        LEFT JOIN admins adm_rev ON ir.reviewed_by_admin_id = adm_rev.id
                        WHERE " . $where_sql . "
                        ORDER BY ir.created_at DESC
                        LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_reports)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $indiscipline_reports = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching indiscipline reports: " . mysqli_error($link);
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
    <title>Review Indiscipline Reports - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #FFEFD5, #FFDAB9, #FFC0CB, #DB7093); /* Soft peach, pink, rose */
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
            color: #C71585; /* Medium Violet Red */
            margin-bottom: 30px;
            border-bottom: 2px solid #FFC0CB;
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
            background-color: #ffe0f0; /* Light pink background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #ffd1e0;
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
            color: #C71585;
        }
        .filter-group select,
        .filter-group input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ffb6c1;
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
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23C71585%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23C71585%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
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
            background-color: #DA70D6; /* Orchid */
            color: #fff;
            border: 1px solid #DA70D6;
        }
        .btn-filter:hover {
            background-color: #C71585;
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


        /* Indiscipline Reports Table Display */
        .reports-section-container {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        h3 {
            color: #C71585;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .reports-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #d8bfd8;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .reports-table th, .reports-table td {
            border-bottom: 1px solid #e0e0e0;
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }
        .reports-table th {
            background-color: #ffe0f0; /* Light pink background */
            color: #C71585;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .reports-table tr:nth-child(even) {
            background-color: #fdf0f5;
        }
        .reports-table tr:hover {
            background-color: #fae6f0;
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
            font-size: 0.8em;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-Pending { background-color: #fff3cd; color: #856404; } /* Yellow */
        .status-Approved, .status-Reviewed-NoAction { background-color: #d4edda; color: #155724; } /* Green */
        .status-Rejected, .status-Closed { background-color: #f8d7da; color: #721c24; } /* Red */
        .status-Reviewed-WarningIssued { background-color: #ffe0b3; color: #cc6600; } /* Orange */
        .status-Reviewed-FurtherAction { background-color: #ffc107; color: #8a6d3b; } /* Darker Orange */

        .severity-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
            white-space: nowrap;
            margin-top: 5px;
            display: inline-block;
        }
        .severity-Minor { background-color: #e0e0e0; color: #555; }
        .severity-Moderate { background-color: #ffe0b3; color: #cc6600; }
        .severity-Serious { background-color: #ffb6c1; color: #c71585; }
        .severity-Critical { background-color: #f8d7da; color: #721c24; }

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
        .btn-review { background-color: #9370DB; color: #fff; border-color: #9370DB; }
        .btn-review:hover { background-color: #8A2BE2; border-color: #8A2BE2; }

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
            width: 80%; max-width: 600px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative;
        }
        .modal-header { padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h4 { margin: 0; color: #333; font-size: 1.5em; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.3s ease; }
        .close-btn:hover, .close-btn:focus { color: #000; text-decoration: none; }
        .modal-body .form-group label { width: auto; }
        .modal-body select, .modal-body textarea { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; box-sizing: border-box; }
        .modal-body textarea { min-height: 100px; resize: vertical; }
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
            .printable-area .reports-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .printable-area .reports-table th, .printable-area .reports-table td { border: 1px solid #eee; padding: 8px 10px; }
            .printable-area .reports-table th { background-color: #ffe0f0; color: #000; }
            .printable-area .status-badge, .printable-area .severity-badge { padding: 3px 6px; font-size: 0.7em; }
            .printable-area .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group, .modal { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-group.full-width { min-width: unset; }
            .filter-buttons { flex-direction: column; width: 100%; }
            .btn-filter, .btn-clear-filter, .btn-print { width: 100%; justify-content: center; }
            .reports-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-gavel"></i> Review Indiscipline Reports</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter and Search Form -->
        <div class="filter-section">
            <form action="review_indiscipline.php" method="GET" style="display:contents;">
                <div class="filter-group">
                    <label for="filter_reported_by_teacher_id"><i class="fas fa-chalkboard-teacher"></i> Reported By:</label>
                    <select id="filter_reported_by_teacher_id" name="reported_by_teacher_id">
                        <option value="">-- All Teachers --</option>
                        <?php foreach ($all_reporting_teachers as $teacher): ?>
                            <option value="<?php echo htmlspecialchars($teacher['id']); ?>"
                                <?php echo ($filter_reported_by_teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_target_type"><i class="fas fa-user-tag"></i> Target Type:</label>
                    <select id="filter_target_type" name="target_type" onchange="filterReportedUsers(this.value)">
                        <option value="">-- All Target Types --</option>
                        <?php foreach ($target_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"
                                <?php echo ($filter_target_type == $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group" id="reported_student_group">
                    <label for="filter_reported_student_id"><i class="fas fa-user-graduate"></i> Reported Student:</label>
                    <select id="filter_reported_student_id" name="reported_student_id">
                        <option value="">-- All Students --</option>
                        <!-- Options populated by JavaScript -->
                    </select>
                </div>
                <div class="filter-group" id="reported_teacher_group">
                    <label for="filter_reported_teacher_id"><i class="fas fa-user-tie"></i> Reported Teacher:</label>
                    <select id="filter_reported_teacher_id" name="reported_teacher_id">
                        <option value="">-- All Teachers --</option>
                        <!-- Options populated by JavaScript -->
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_severity"><i class="fas fa-exclamation-circle"></i> Severity:</label>
                    <select id="filter_severity" name="severity">
                        <option value="">-- All Severities --</option>
                        <?php foreach ($severities as $severity): ?>
                            <option value="<?php echo htmlspecialchars($severity); ?>"
                                <?php echo ($filter_severity == $severity) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($severity); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_status"><i class="fas fa-info-circle"></i> Status:</label>
                    <select id="filter_status" name="status">
                        <option value="">-- All Statuses --</option>
                        <?php foreach ($report_statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>"
                                <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_class_id"><i class="fas fa-school"></i> Class (Student Reports):</label>
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
                <div class="filter-group full-width">
                    <label for="search_query"><i class="fas fa-search"></i> Search Reports:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Student/Teacher name, Description">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_reported_by_teacher_id || $filter_target_type || $filter_reported_student_id || $filter_reported_teacher_id || $filter_severity || $filter_status || $filter_class_id || !empty($search_query)): ?>
                        <a href="review_indiscipline.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printIndisciplineReport()"><i class="fas fa-print"></i> Print Report</button>
                </div>
            </form>
        </div>

        <!-- Indiscipline Reports Table -->
        <div class="reports-section-container printable-area">
            <h3><i class="fas fa-list-alt"></i> Indiscipline Reports Overview</h3>
            <?php if (empty($indiscipline_reports)): ?>
                <p class="no-results">No indiscipline reports found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Incident Date</th>
                                <th>Reported By</th>
                                <th>Target</th>
                                <th>Class</th>
                                <th>Location</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th>Final Action</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($indiscipline_reports as $report): ?>
                                <tr>
                                    <td><?php echo date("M j, Y", strtotime($report['incident_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($report['reported_by_teacher_name'] ?: 'N/A'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($report['target_type']); ?>:
                                        <?php if ($report['target_type'] == 'Student'): ?>
                                            <strong><?php echo htmlspecialchars($report['reported_student_first_name'] . ' ' . $report['reported_student_last_name']); ?></strong> (Reg: <?php echo htmlspecialchars($report['registration_number']); ?>)
                                        <?php elseif ($report['target_type'] == 'Teacher'): ?>
                                            <strong><?php echo htmlspecialchars($report['reported_teacher_name']); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($report['class_name'] ? $report['class_name'] . ' - ' . $report['section_name'] : 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($report['location'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="severity-badge severity-<?php echo str_replace(' ', '', htmlspecialchars($report['severity'])); ?>">
                                            <?php echo htmlspecialchars($report['severity']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo str_replace([' ', '-'], '', htmlspecialchars($report['status'])); ?>">
                                            <?php echo htmlspecialchars($report['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($report['description'], 0, 100)); ?>
                                        <?php if (strlen($report['description']) > 100): ?>
                                            ... <a href="#" onclick="alert('Full Description: <?php echo htmlspecialchars($report['description']); ?>'); return false;" class="text-muted">more</a>
                                        <?php endif; ?>
                                        <?php if ($report['evidence_url']): ?>
                                            <a href="<?php echo htmlspecialchars($report['evidence_url']); ?>" target="_blank" class="text-muted" title="View Evidence"><i class="fas fa-paperclip"></i></a>
                                        <?php endif; ?>
                                        <div class="notes-text">Immediate Action: <?php echo htmlspecialchars($report['immediate_action_taken'] ?: 'N/A'); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($report['final_action_taken']): ?>
                                            <?php echo htmlspecialchars($report['final_action_taken']); ?>
                                            <?php if ($report['review_date']): ?>
                                                <div class="notes-text">(Reviewed: <?php echo date('M j, Y', strtotime($report['review_date'])); ?> by <?php echo htmlspecialchars($report['reviewed_by_admin_name'] ?: 'Admin'); ?>)</div>
                                            <?php endif; ?>
                                            <?php if (!empty($report['review_notes'])): ?>
                                                <div class="notes-text">Notes: <?php echo htmlspecialchars($report['review_notes']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons-group">
                                            <?php if ($report['status'] == 'Pending Review'): ?>
                                                <button class="btn-action btn-review" onclick="showReviewModal(<?php echo htmlspecialchars(json_encode($report)); ?>)">
                                                    <i class="fas fa-search-plus"></i> Review
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">Reviewed</span>
                                                <button class="btn-action btn-review" onclick="showReviewModal(<?php echo htmlspecialchars(json_encode($report)); ?>)" title="View Review Details">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
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
                                'reported_by_teacher_id' => $filter_reported_by_teacher_id,
                                'target_type' => $filter_target_type,
                                'reported_student_id' => $filter_reported_student_id,
                                'reported_teacher_id' => $filter_reported_teacher_id,
                                'severity' => $filter_severity,
                                'status' => $filter_status,
                                'class_id' => $filter_class_id,
                                'search' => $search_query
                            ]);
                            $base_url = "review_indiscipline.php?" . http_build_query($base_url_params);
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

<!-- Review/Action Modal -->
<div id="reviewModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="review-modal-title">Review Indiscipline Report</h4>
      <span class="close-btn" onclick="closeReviewModal()">&times;</span>
    </div>
    <form id="reviewForm" action="review_indiscipline.php" method="POST">
      <input type="hidden" name="action" value="review_report">
      <input type="hidden" name="report_id" id="modal-report-id">
      <div class="modal-body">
        <p><strong>Reported By:</strong> <span id="modal-reported-by"></span></p>
        <p><strong>Target:</strong> <span id="modal-target"></span></p>
        <p><strong>Class:</strong> <span id="modal-class"></span></p>
        <p><strong>Incident Date/Time:</strong> <span id="modal-datetime"></span></p>
        <p><strong>Location:</strong> <span id="modal-location"></span></p>
        <p><strong>Severity:</strong> <span id="modal-severity"></span></p>
        <p><strong>Description:</strong> <span id="modal-description"></span></p>
        <p><strong>Evidence:</strong> <span id="modal-evidence"></span></p>
        <p><strong>Immediate Action:</strong> <span id="modal-immediate-action"></span></p>
        <hr>
        <div class="form-group">
          <label for="new_status">Update Status:</label>
          <select id="new_status" name="new_status" required>
            <option value="">-- Select Status --</option>
            <?php foreach ($report_statuses as $status): ?>
                <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="review_notes">Review Notes (Optional):</label>
          <textarea id="review_notes" name="review_notes" rows="3" placeholder="Add your notes on this report"></textarea>
        </div>
        <div class="form-group">
          <label for="final_action">Final Action Taken (Optional):</label>
          <textarea id="final_action" name="final_action" rows="3" placeholder="e.g., Warning issued, Parent meeting, Suspension for 3 days"></textarea>
        </div>
        <p class="text-muted" id="modal-reviewed-info" style="font-size: 0.9em;"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-form-cancel" onclick="closeReviewModal()">Cancel</button>
        <button type="submit" class="btn-modal-submit" id="modal-submit-btn">Save Review</button>
      </div>
    </form>
  </div>
</div>

<script>
    // Raw data for JavaScript filtering (needed for dynamic dropdowns)
    const allStudentsRaw = <?php echo json_encode($all_students_raw); ?>;
    const allTeachersRaw = <?php echo json_encode($all_teachers_raw); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dynamic dropdowns on page load
        const initialTargetType = document.getElementById('filter_target_type').value;
        const initialReportedStudentId = <?php echo $filter_reported_student_id ?: 'null'; ?>;
        const initialReportedTeacherId = <?php echo $filter_reported_teacher_id ?: 'null'; ?>;
        filterReportedUsers(initialTargetType, initialReportedStudentId, initialReportedTeacherId);
    });

    // Function to filter Reported Student/Teacher dropdowns based on Target Type
    function filterReportedUsers(targetType, selectedStudentId = null, selectedTeacherId = null) {
        const studentGroup = document.getElementById('reported_student_group');
        const studentSelect = document.getElementById('filter_reported_student_id');
        const teacherGroup = document.getElementById('reported_teacher_group');
        const teacherSelect = document.getElementById('filter_reported_teacher_id');

        // Reset and hide both groups initially
        studentSelect.innerHTML = '<option value="">-- All Students --</option>';
        teacherSelect.innerHTML = '<option value="">-- All Teachers --</option>';
        studentGroup.style.display = 'none';
        teacherGroup.style.display = 'none';

        if (targetType === 'Student') {
            studentGroup.style.display = 'block';
            allStudentsRaw.forEach(student => {
                const option = document.createElement('option');
                option.value = student.id;
                option.textContent = `${student.first_name} ${student.last_name} (${student.registration_number})`;
                if (selectedStudentId && student.id == selectedStudentId) {
                    option.selected = true;
                }
                studentSelect.appendChild(option);
            });
        } else if (targetType === 'Teacher') {
            teacherGroup.style.display = 'block';
            allTeachersRaw.forEach(teacher => {
                const option = document.createElement('option');
                option.value = teacher.id;
                option.textContent = teacher.full_name;
                if (selectedTeacherId && teacher.id == selectedTeacherId) {
                    option.selected = true;
                }
                teacherSelect.appendChild(option);
            });
        }
        // If 'All Target Types' selected, both remain hidden.
    }


    // --- Review Modal Functions ---
    const reviewModal = document.getElementById('reviewModal');
    const modalReportId = document.getElementById('modal-report-id');
    const modalReportedBy = document.getElementById('modal-reported-by');
    const modalTarget = document.getElementById('modal-target');
    const modalClass = document.getElementById('modal-class');
    const modalDateTime = document.getElementById('modal-datetime');
    const modalLocation = document.getElementById('modal-location');
    const modalSeverity = document.getElementById('modal-severity');
    const modalDescription = document.getElementById('modal-description');
    const modalEvidence = document.getElementById('modal-evidence');
    const modalImmediateAction = document.getElementById('modal-immediate-action');
    const newStatusSelect = document.getElementById('new_status');
    const reviewNotesTextarea = document.getElementById('review_notes');
    const finalActionTextarea = document.getElementById('final_action');
    const modalSubmitBtn = document.getElementById('modal-submit-btn');
    const modalReviewedInfo = document.getElementById('modal-reviewed-info');

    function showReviewModal(reportData) {
        modalReportId.value = reportData.id;

        modalReportedBy.textContent = reportData.reported_by_teacher_name || 'N/A';
        
        let targetText = `${reportData.target_type}: `;
        if (reportData.target_type === 'Student') {
            targetText += `${reportData.reported_student_first_name} ${reportData.reported_student_last_name} (Reg: ${reportData.registration_number})`;
        } else if (reportData.target_type === 'Teacher') {
            targetText += reportData.reported_teacher_name;
        }
        modalTarget.textContent = targetText;

        modalClass.textContent = reportData.class_name ? `${reportData.class_name} - ${reportData.section_name}` : 'N/A';
        modalDateTime.textContent = `${formatDate(reportData.incident_date)} ${formatTime(reportData.incident_time)}`;
        modalLocation.textContent = reportData.location || 'N/A';
        modalSeverity.textContent = reportData.severity || 'N/A';
        modalDescription.textContent = reportData.description;
        
        if (reportData.evidence_url) {
            modalEvidence.innerHTML = `<a href="${reportData.evidence_url}" target="_blank"><i class="fas fa-paperclip"></i> View Evidence</a>`;
        } else {
            modalEvidence.textContent = 'None';
        }
        
        modalImmediateAction.textContent = reportData.immediate_action_taken || 'N/A';
        
        newStatusSelect.value = reportData.status || '';
        reviewNotesTextarea.value = reportData.review_notes || '';
        finalActionTextarea.value = reportData.final_action_taken || '';

        // If already reviewed, disable fields and show reviewer info
        if (reportData.status !== 'Pending Review') {
            newStatusSelect.disabled = true;
            reviewNotesTextarea.readOnly = true;
            finalActionTextarea.readOnly = true;
            modalSubmitBtn.style.display = 'none';
            modalReviewedInfo.innerHTML = `Reviewed by: ${reportData.reviewed_by_admin_name || 'Admin'} on ${formatDate(reportData.review_date)}`;
        } else {
            newStatusSelect.disabled = false;
            reviewNotesTextarea.readOnly = false;
            finalActionTextarea.readOnly = false;
            modalSubmitBtn.style.display = 'inline-flex';
            modalReviewedInfo.textContent = '';
        }

        reviewModal.style.display = 'flex'; // Show modal
    }

    function closeReviewModal() {
        reviewModal.style.display = 'none'; // Hide modal
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == reviewModal) {
            closeReviewModal();
        }
    }

    function formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00') return 'N/A';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }

    function formatTime(timeString) {
        if (!timeString) return '';
        const [hours, minutes, seconds] = timeString.split(':');
        const date = new Date();
        date.setHours(hours, minutes, seconds);
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    // --- Print Functionality ---
    window.printIndisciplineReport = function() {
        const printableContent = document.querySelector('.reports-section-container').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=1000');

        printWindow.document.write('<html><head><title>Indiscipline Report Print</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
            h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 15px; }
            h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
            .reports-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .reports-table th, .reports-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
            .reports-table th { background-color: #ffe0f0; color: #000; font-weight: 700; text-transform: uppercase; }
            .reports-table tr:nth-child(even) { background-color: #fdf0f5; }
            .status-badge, .severity-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
            .status-Pending { background-color: #fff3cd; color: #856404; }
            .status-Approved, .status-Reviewed-NoAction { background-color: #d4edda; color: #155724; }
            .status-Rejected, .status-Closed { background-color: #f8d7da; color: #721c24; }
            .status-Reviewed-WarningIssued { background-color: #ffe0b3; color: #cc6600; }
            .status-Reviewed-FurtherAction { background-color: #ffc107; color: #8a6d3b; }
            .severity-Minor { background-color: #e0e0e0; color: #555; }
            .severity-Moderate { background-color: #ffe0b3; color: #cc6600; }
            .severity-Serious { background-color: #ffb6c1; color: #c71585; }
            .severity-Critical { background-color: #f8d7da; color: #721c24; }
            .notes-text { font-size: 0.8em; color: #666; margin-top: 3px; display: block; }
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