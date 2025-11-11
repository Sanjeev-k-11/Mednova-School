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
$filter_student_id = isset($_GET['student_id']) && is_numeric($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Pagination Configuration ---
$records_per_page = 10; // Number of tickets to display per page
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

$all_subjects = [];
$sql_all_subjects = "SELECT id, subject_name FROM subjects ORDER BY subject_name ASC";
if ($result = mysqli_query($link, $sql_all_subjects)) {
    $all_subjects = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching subjects for filter: " . mysqli_error($link);
    $message_type = "danger";
}

$all_teachers = [];
$sql_all_teachers = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_all_teachers)) {
    $all_teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching teachers for filter: " . mysqli_error($link);
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

$ticket_statuses = ['Open', 'Closed'];


// --- Process Ticket Status Update ---
if (isset($_POST['action']) && $_POST['action'] == 'update_ticket_status') {
    $ticket_id = (int)$_POST['ticket_id'];
    $new_status = trim($_POST['new_status']);
    $message_text = trim($_POST['message_text']); // New message for the conversation

    if (empty($ticket_id) || empty($new_status)) {
        set_session_message("Invalid ticket ID or new status for update.", "danger");
        header("location: manage_helpdesk_tickets.php?page={$current_page}");
        exit;
    }

    // Update ticket status
    $sql_update_ticket = "UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?";
    if ($stmt_ticket = mysqli_prepare($link, $sql_update_ticket)) {
        mysqli_stmt_bind_param($stmt_ticket, "si", $new_status, $ticket_id);
        if (mysqli_stmt_execute($stmt_ticket)) {
            set_session_message("Ticket status updated to " . htmlspecialchars($new_status) . " successfully.", "success");
        } else {
            set_session_message("Error updating ticket status: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt_ticket);
    }

    // Add a message to the conversation if provided
    if (!empty($message_text)) {
        $sql_add_message = "INSERT INTO support_ticket_messages (ticket_id, user_id, user_role, message) VALUES (?, ?, ?, ?)";
        if ($stmt_message = mysqli_prepare($link, $sql_add_message)) {
            // Principal's ID as user_id, 'Teacher' (or 'Principal') as user_role
            // Your schema for support_ticket_messages has user_id and user_role
            mysqli_stmt_bind_param($stmt_message, "iiss", $ticket_id, $principal_id, $principal_role, $message_text);
            mysqli_stmt_execute($stmt_message);
            mysqli_stmt_close($stmt_message);
        }
    }
    header("location: manage_helpdesk_tickets.php?page={$current_page}");
    exit;
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_class_id) {
    $where_clauses[] = "st.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if ($filter_subject_id) {
    $where_clauses[] = "st.subject_id = ?";
    $params[] = $filter_subject_id;
    $types .= "i";
}
if ($filter_teacher_id) {
    $where_clauses[] = "st.teacher_id = ?";
    $params[] = $filter_teacher_id;
    $types .= "i";
}
if ($filter_student_id) {
    $where_clauses[] = "st.student_id = ?";
    $params[] = $filter_student_id;
    $types .= "i";
}
if ($filter_status) {
    $where_clauses[] = "st.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(st.title LIKE ? OR st.description LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR t_assign.full_name LIKE ?)";
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
$total_records_sql = "SELECT COUNT(st.id)
                      FROM support_tickets st
                      JOIN students s ON st.student_id = s.id
                      JOIN classes c ON st.class_id = c.id
                      JOIN subjects subj ON st.subject_id = subj.id
                      LEFT JOIN teachers t_assign ON st.teacher_id = t_assign.id
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
    $message = "Error counting tickets: " . mysqli_error($link);
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


// --- Fetch Support Tickets Data (with filters and pagination) ---
$support_tickets = [];
$sql_fetch_tickets = "SELECT
                            st.id, st.title, st.status, st.created_at, st.updated_at,
                            st.student_id, st.class_id, st.subject_id, st.teacher_id,
                            s.first_name AS student_first_name, s.last_name AS student_last_name, s.registration_number,
                            c.class_name, c.section_name,
                            subj.subject_name,
                            t_assign.full_name AS assigned_teacher_name
                        FROM support_tickets st
                        JOIN students s ON st.student_id = s.id
                        JOIN classes c ON st.class_id = c.id
                        JOIN subjects subj ON st.subject_id = subj.id
                        LEFT JOIN teachers t_assign ON st.teacher_id = t_assign.id
                        WHERE " . $where_sql . "
                        ORDER BY st.status DESC, st.created_at DESC
                        LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_tickets)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $support_tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching support tickets: " . mysqli_error($link);
    $message_type = "danger";
}

// --- Fetch Messages for a specific ticket if requested (for modal) ---
$ticket_messages = [];
if (isset($_GET['view_ticket_id']) && is_numeric($_GET['view_ticket_id'])) {
    $view_ticket_id = (int)$_GET['view_ticket_id'];
    $sql_fetch_messages = "SELECT
                                stm.message, stm.created_at, stm.user_role,
                                COALESCE(s.first_name, t.full_name) AS sender_name -- Get sender name from student or teacher
                            FROM support_ticket_messages stm
                            LEFT JOIN students s ON stm.user_id = s.id AND stm.user_role = 'Student'
                            LEFT JOIN teachers t ON stm.user_id = t.id AND stm.user_role = 'Teacher'
                            WHERE stm.ticket_id = ?
                            ORDER BY stm.created_at ASC";
    if ($stmt = mysqli_prepare($link, $sql_fetch_messages)) {
        mysqli_stmt_bind_param($stmt, "i", $view_ticket_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $ticket_messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
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
    <title>Manage Helpdesk Tickets - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #FFE4E1, #FFDAB9, #E0FFFF, #ADD8E6); /* Soft rosy, light blue */
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
            color: #B22222; /* FireBrick */
            margin-bottom: 30px;
            border-bottom: 2px solid #FFE4E1;
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
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group.wide { /* For search input */
            flex: 2;
            min-width: 250px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #B22222;
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
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23B22222%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23B22222%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
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
            background-color: #B22222; /* FireBrick */
            color: #fff;
            border: 1px solid #B22222;
        }
        .btn-filter:hover {
            background-color: #A52A2A;
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


        /* Tickets Table Display */
        .tickets-section-container {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        h3 {
            color: #B22222;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tickets-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #e6e6fa;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .tickets-table th, .tickets-table td {
            border-bottom: 1px solid #e0e0e0;
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }
        .tickets-table th {
            background-color: #fff0f5; /* Floral White */
            color: #B22222;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .tickets-table tr:nth-child(even) { background-color: #fcf8f8; }
        .tickets-table tr:hover { background-color: #faeded; }
        .text-center {
            text-align: center;
        }
        .text-muted {
            color: #6c757d;
        }
        .no-results {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: #6c757d;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-Open { background-color: #fff3cd; color: #856404; } /* Yellow */
        .status-Closed { background-color: #d4edda; color: #155724; } /* Green */

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
        .btn-view-details { background-color: #6495ED; color: #fff; border-color: #6495ED; }
        .btn-view-details:hover { background-color: #4682B4; border-color: #4682B4; }


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
            color: #B22222;
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .pagination-controls a:hover {
            background-color: #e9ecef;
            border-color: #ffb6c1;
        }
        .pagination-controls .current-page,
        .pagination-controls .current-page:hover {
            background-color: #B22222;
            color: #fff;
            border-color: #B22222;
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
            width: 80%; max-width: 700px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative;
        }
        .modal-header { padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h4 { margin: 0; color: #333; font-size: 1.5em; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.3s ease; }
        .close-btn:hover, .close-btn:focus { color: #000; text-decoration: none; }
        .modal-body { max-height: 70vh; overflow-y: auto; padding-right: 15px; } /* Scrollable body */
        .modal-body p { margin-bottom: 10px; line-height: 1.5; }
        .modal-body strong { color: #555; }
        .modal-body .message-history { border-top: 1px solid #eee; margin-top: 20px; padding-top: 15px; }
        .modal-body .message-item { 
            background-color: #f2f2f2; border-radius: 8px; padding: 10px 15px; margin-bottom: 10px;
            display: flex; flex-direction: column; 
        }
        .modal-body .message-item.student-message { background-color: #e3f2fd; align-self: flex-start; }
        .modal-body .message-item.teacher-message { background-color: #d4edda; align-self: flex-end; }
        .modal-body .message-item.principal-message { background-color: #fff0f5; align-self: flex-end; } /* New style for principal */

        .modal-body .message-sender { font-weight: 600; color: #333; font-size: 0.9em; margin-bottom: 5px; }
        .modal-body .message-content { font-size: 0.95em; color: #555; margin-bottom: 5px; }
        .modal-body .message-time { font-size: 0.8em; color: #888; text-align: right; }
        .modal-body textarea { min-height: 80px; resize: vertical; width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; box-sizing: border-box; margin-top: 10px; }
        .modal-footer { margin-top: 20px; text-align: right; }
        .btn-modal-submit { background-color: #B22222; color: white; }
        .btn-modal-submit:hover { background-color: #A52A2A; }
        .btn-modal-cancel { background-color: #6c757d; color: white; }
        .btn-modal-cancel:hover { background-color: #5a6268; }


        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; font-size: 10pt; padding: 10mm; }
            .printable-area h2, .printable-area h3 { color: #000; border-bottom: 1px solid #ccc; font-size: 16pt; margin-bottom: 15px; }
            .printable-area .tickets-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .printable-area .tickets-table th, .printable-area .tickets-table td { border: 1px solid #eee; padding: 8px 10px; }
            .printable-area .tickets-table th { background-color: #fff0f5; color: #000; }
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
            .tickets-table { display: block; overflow-x: auto; white-space: nowrap; }
            .modal-content { width: 95%; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-life-ring"></i> Manage Helpdesk Tickets</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter and Search Form -->
        <div class="filter-section">
            <form action="manage_helpdesk_tickets.php" method="GET" style="display:contents;">
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
                    <label for="filter_teacher_id"><i class="fas fa-chalkboard-teacher"></i> Assigned Teacher:</label>
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
                        <?php foreach ($ticket_statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>"
                                <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group wide">
                    <label for="search_query"><i class="fas fa-search"></i> Search Tickets:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Title, Student, Teacher">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_class_id || $filter_subject_id || $filter_teacher_id || $filter_student_id || $filter_status || !empty($search_query)): ?>
                        <a href="manage_helpdesk_tickets.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printTicketsReport()"><i class="fas fa-print"></i> Print Report</button>
                </div>
            </form>
        </div>

        <!-- Tickets Overview Table -->
        <div class="tickets-section-container printable-area">
            <h3><i class="fas fa-ticket-alt"></i> All Support Tickets</h3>
            <?php if (empty($support_tickets)): ?>
                <p class="no-results">No support tickets found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x:auto;" id="tickets-table-wrapper">
                    <table class="tickets-table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Title</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Last Update</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($support_tickets as $ticket): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['student_first_name'] . ' ' . $ticket['student_last_name'] . ' (Reg: ' . $ticket['registration_number'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['class_name'] . ' - ' . $ticket['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['assigned_teacher_name'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($ticket['status']); ?>">
                                            <?php echo htmlspecialchars($ticket['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date("M j, Y H:i", strtotime($ticket['created_at'])); ?></td>
                                    <td><?php echo date("M j, Y H:i", strtotime($ticket['updated_at'])); ?></td>
                                    <td class="text-center">
                                        <div class="action-buttons-group">
                                            <button class="btn-action btn-view-details" onclick="viewTicketDetails(<?php echo htmlspecialchars(json_encode($ticket)); ?>)">
                                                <i class="fas fa-eye"></i> View/Update
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
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> tickets
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $base_url_params = array_filter([
                                'class_id' => $filter_class_id,
                                'subject_id' => $filter_subject_id,
                                'teacher_id' => $filter_teacher_id,
                                'student_id' => $filter_student_id,
                                'status' => $filter_status,
                                'search' => $search_query
                            ]);
                            $base_url = "manage_helpdesk_tickets.php?" . http_build_query($base_url_params);
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

<!-- Ticket Details & Update Modal -->
<div id="ticketModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="ticket-modal-title">Ticket Details</h4>
      <span class="close-btn" onclick="closeTicketModal()">&times;</span>
    </div>
    <div class="modal-body">
      <p><strong>Ticket ID:</strong> <span id="modal-ticket-id-display"></span></p>
      <p><strong>Title:</strong> <span id="modal-ticket-title"></span></p>
      <p><strong>Student:</strong> <span id="modal-student-name"></span></p>
      <p><strong>Class:</strong> <span id="modal-class-name"></span></p>
      <p><strong>Subject:</strong> <span id="modal-subject-name"></span></p>
      <p><strong>Assigned To:</strong> <span id="modal-assigned-teacher"></span></p>
      <p><strong>Status:</strong> <span id="modal-status-display" class="status-badge"></span></p>
      <p><strong>Created:</strong> <span id="modal-created-at"></span></p>
      <p><strong>Last Updated:</strong> <span id="modal-updated-at"></span></p>

      <div class="message-history">
        <h4>Conversation History:</h4>
        <div id="modal-message-list">
          <!-- Messages will be loaded here via JS -->
          <p class="text-muted text-center">No messages yet.</p>
        </div>
      </div>
      
      <form id="updateTicketForm" action="manage_helpdesk_tickets.php" method="POST">
        <input type="hidden" name="action" value="update_ticket_status">
        <input type="hidden" name="ticket_id" id="modal-update-ticket-id">

        <hr>
        <div class="form-group">
            <label for="new_status">Update Status:</label>
            <select id="new_status" name="new_status" required>
                <option value="Open">Open</option>
                <option value="Closed">Closed</option>
            </select>
        </div>
        <div class="form-group">
            <label for="message_text">Add Message (Optional):</label>
            <textarea id="message_text" name="message_text" rows="3" placeholder="Type your message here..."></textarea>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-form-cancel" onclick="closeTicketModal()">Close</button>
            <button type="submit" class="btn-modal-submit" id="update-submit-btn">Update & Reply</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
    // Raw student data for JavaScript filtering (for filter dropdowns)
    const allStudentsRaw = <?php echo json_encode($all_students_raw); ?>;
    const principalRole = "<?php echo htmlspecialchars($principal_role); ?>"; // Pass principal's role to JS

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize student dropdown on page load based on any pre-selected class_id
        const initialClassId = document.getElementById('filter_class_id').value;
        const initialStudentId = <?php echo $filter_student_id ?: 'null'; ?>;
        filterStudentsByClass(initialClassId, initialStudentId);

        // If a ticket ID is passed in the URL (e.g., after an update), open the modal
        const viewTicketId = <?php echo isset($_GET['view_ticket_id']) ? json_encode((int)$_GET['view_ticket_id']) : 'null'; ?>;
        if (viewTicketId) {
            const ticketData = <?php echo json_encode($support_tickets); ?>.find(t => t.id === viewTicketId);
            if (ticketData) {
                viewTicketDetails(ticketData);
            }
        }
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

    // --- Ticket Details Modal Functions ---
    const ticketModal = document.getElementById('ticketModal');
    const modalTicketIdDisplay = document.getElementById('modal-ticket-id-display');
    const modalTicketTitle = document.getElementById('modal-ticket-title');
    const modalStudentName = document.getElementById('modal-student-name');
    const modalClassName = document.getElementById('modal-class-name');
    const modalSubjectName = document.getElementById('modal-subject-name');
    const modalAssignedTeacher = document.getElementById('modal-assigned-teacher');
    const modalStatusDisplay = document.getElementById('modal-status-display');
    const modalCreatedAt = document.getElementById('modal-created-at');
    const modalUpdatedAt = document.getElementById('modal-updated-at');
    const modalMessageList = document.getElementById('modal-message-list');
    const modalUpdateTicketId = document.getElementById('modal-update-ticket-id');
    const newStatusSelect = document.getElementById('new_status');
    const messageTextarea = document.getElementById('message_text');
    const updateSubmitBtn = document.getElementById('update-submit-btn');


    async function viewTicketDetails(ticketData) {
        modalTicketIdDisplay.textContent = ticketData.id;
        modalTicketTitle.textContent = ticketData.title;
        modalStudentName.textContent = `${ticketData.student_first_name} ${ticketData.student_last_name} (Reg: ${ticketData.registration_number})`;
        modalClassName.textContent = `${ticketData.class_name} - ${ticketData.section_name}`;
        modalSubjectName.textContent = ticketData.subject_name;
        modalAssignedTeacher.textContent = ticketData.assigned_teacher_name || 'N/A';
        
        modalStatusDisplay.textContent = ticketData.status;
        modalStatusDisplay.className = `status-badge status-${ticketData.status}`; // Apply dynamic status class

        modalCreatedAt.textContent = formatDateTime(ticketData.created_at);
        modalUpdatedAt.textContent = formatDateTime(ticketData.updated_at);
        modalUpdateTicketId.value = ticketData.id;
        newStatusSelect.value = ticketData.status; // Pre-select current status

        // Fetch and display messages for this ticket
        modalMessageList.innerHTML = '<p class="text-muted text-center">Loading messages...</p>';
        try {
            const response = await fetch(`fetch_ticket_messages.php?ticket_id=${ticketData.id}`);
            const messages = await response.json();
            modalMessageList.innerHTML = ''; // Clear loading message

            if (messages.length === 0) {
                modalMessageList.innerHTML = '<p class="text-muted text-center">No messages yet.</p>';
            } else {
                messages.forEach(msg => {
                    const messageItem = document.createElement('div');
                    let senderName = msg.sender_name || msg.user_role; // Fallback to role if name not found
                    let messageClass = '';

                    if (msg.user_role === 'Student') {
                        messageClass = 'student-message';
                    } else if (msg.user_role === 'Teacher') {
                        messageClass = 'teacher-message';
                    } else if (msg.user_role === 'Principle') { // Assuming principal will also use this system
                        messageClass = 'principal-message';
                        senderName = "Principal"; // Standardize Principal's display name
                    }
                     messageItem.className = `message-item ${messageClass}`;
                    
                    messageItem.innerHTML = `
                        <div class="message-sender">${senderName}</div>
                        <div class="message-content">${htmlspecialchars(msg.message)}</div>
                        <div class="message-time">${formatDateTime(msg.created_at)}</div>
                    `;
                    modalMessageList.appendChild(messageItem);
                });
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
            modalMessageList.innerHTML = '<p class="text-muted text-center">Error loading messages.</p>';
        }


        // Disable update controls if ticket is closed
        if (ticketData.status === 'Closed') {
            newStatusSelect.disabled = true;
            messageTextarea.disabled = true;
            updateSubmitBtn.disabled = true;
            updateSubmitBtn.textContent = 'Ticket Closed';
            updateSubmitBtn.classList.remove('btn-modal-submit'); // Optional: adjust styling for disabled
        } else {
            newStatusSelect.disabled = false;
            messageTextarea.disabled = false;
            updateSubmitBtn.disabled = false;
            updateSubmitBtn.textContent = 'Update & Reply';
            updateSubmitBtn.classList.add('btn-modal-submit');
        }

        ticketModal.style.display = 'flex'; // Show modal
    }

    function closeTicketModal() {
        ticketModal.style.display = 'none'; // Hide modal
        messageTextarea.value = ''; // Clear message input
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == ticketModal) {
            closeTicketModal();
        }
    }

    // Helper to format datetime string for display
    function formatDateTime(datetimeString) {
        if (!datetimeString || datetimeString === '0000-00-00 00:00:00') return 'N/A';
        const date = new Date(datetimeString);
        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString(undefined, options);
    }
    
    // Simple HTML escaping for message content
    function htmlspecialchars(str) {
        let div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }


    // --- Print Functionality ---
    window.printTicketsReport = function() {
        const printableContent = document.querySelector('.tickets-section-container').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=1000');

        printWindow.document.write('<html><head><title>Helpdesk Tickets Report</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
            h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 25px; text-align: center; }
            h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
            .tickets-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .tickets-table th, .tickets-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
            .tickets-table th { background-color: #fff0f5; color: #000; font-weight: 700; text-transform: uppercase; }
            .tickets-table tr:nth-child(even) { background-color: #fcf8f8; }
            .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
            .status-Open { background-color: #fff3cd; color: #856404; }
            .status-Closed { background-color: #d4edda; color: #155724; }
            .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group, .modal { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(`<h2 style="text-align: center;">Helpdesk Tickets Report</h2>`);
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