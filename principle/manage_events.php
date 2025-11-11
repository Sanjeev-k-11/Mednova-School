<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"]; // Primary key ID of the logged-in principal
$principal_name = $_SESSION["full_name"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Pagination Configuration ---
$records_per_page = 10; // Number of events to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_event_type = isset($_GET['event_type']) && $_GET['event_type'] !== '' ? trim($_GET['event_type']) : null;


// --- Process Form Submissions (Add, Edit Event) ---
if (isset($_POST['form_action']) && ($_POST['form_action'] == 'add_event' || $_POST['form_action'] == 'edit_event')) {
    $action = $_POST['form_action'];

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = trim($_POST['start_date']);
    $end_date = empty(trim($_POST['end_date'])) ? NULL : trim($_POST['end_date']);
    $event_type = trim($_POST['event_type']);
    $color = trim($_POST['color']);
    $created_by_admin_id = $principal_id; // Principal creates the event

    // Basic validation
    if (empty($title) || empty($start_date) || empty($event_type) || empty($color)) {
        set_session_message("Title, Start Date, Event Type, and Color are required.", "danger");
        header("location: manage_events.php");
        exit;
    }

    if ($action == 'add_event') {
        $sql = "INSERT INTO events (title, description, start_date, end_date, event_type, color, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssssi", $title, $description, $start_date, $end_date, $event_type, $color, $created_by_admin_id);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Event added successfully.", "success");
            } else {
                set_session_message("Error adding event: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'edit_event') {
        $event_id = (int)$_POST['event_id'];
        if (empty($event_id)) {
            set_session_message("Invalid Event ID for editing.", "danger");
            header("location: manage_events.php");
            exit;
        }

        $sql = "UPDATE events SET title = ?, description = ?, start_date = ?, end_date = ?, event_type = ?, color = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssssi", $title, $description, $start_date, $end_date, $event_type, $color, $event_id);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Event updated successfully.", "success");
            } else {
                set_session_message("Error updating event: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    }
    header("location: manage_events.php?page={$current_page}");
    exit;
}

// --- Process Event Deletion ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid Event ID for deletion.", "danger");
        header("location: manage_events.php");
        exit;
    }

    $sql = "DELETE FROM events WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Event deleted successfully.", "success");
            } else {
                set_session_message("Event not found or already deleted.", "danger");
            }
        } else {
            set_session_message("Error deleting event: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_events.php?page={$current_page}");
    exit;
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_event_type) {
    $where_clauses[] = "e.event_type = ?";
    $params[] = $filter_event_type;
    $types .= "s";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(e.title LIKE ? OR e.description LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(e.id)
                      FROM events e
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
    $message = "Error counting events: " . mysqli_error($link);
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


// --- Fetch Events Data (with filters and pagination) ---
$events = [];
$sql_fetch_events = "SELECT
                            e.*,
                            adm.full_name AS created_by_admin_name
                        FROM events e
                        LEFT JOIN admins adm ON e.created_by_admin_id = adm.id
                        WHERE " . $where_sql . "
                        ORDER BY e.start_date DESC, e.start_date DESC
                        LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_events)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $events = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching events: " . mysqli_error($link);
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
    <title>Manage Events - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #F5DEB3, #FFDAB9, #ADD8E6, #87CEEB); /* Wheat, peach, light blues */
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
            max-width: 1200px;
            margin: 20px auto;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.95); /* Slightly transparent white */
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        h2 {
            color: #D2691E; /* Chocolate */
            margin-bottom: 30px;
            border-bottom: 2px solid #F5DEB3;
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

        /* Collapsible Section Styles */
        .section-box {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #D2691E; /* Chocolate */
            color: #fff;
            padding: 15px 20px;
            margin: -30px -30px 20px -30px; /* Adjust margin to fill parent box padding */
            border-bottom: 1px solid #A0522D;
            border-radius: 10px 10px 0 0;
            font-size: 1.6em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .section-header:hover {
            background-color: #A0522D;
        }
        .section-header h3 {
            margin: 0;
            font-size: 1em; /* Inherit font size */
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
        .section-content {
            max-height: 2000px; /* Arbitrary large value */
            overflow: hidden;
            transition: max-height 0.5s ease-in-out;
        }
        .section-content.collapsed {
            max-height: 0;
            margin-top: 0;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        /* Forms (Event Add/Edit, Filters) */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group input[type="color"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23D2691E%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23D2691E%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
        }
        .form-group input[type="color"] {
            padding: 5px; /* Adjust padding for color input */
            height: 40px; /* Standardize height */
        }
        .form-actions {
            margin-top: 25px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn-form-submit, .btn-form-cancel, .btn-filter, .btn-clear-filter, .btn-print {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }
        .btn-form-submit { background-color: #D2691E; color: #fff; }
        .btn-form-submit:hover { background-color: #A0522D; }
        .btn-form-cancel { background-color: #6c757d; color: #fff; }
        .btn-form-cancel:hover { background-color: #5a6268; }

        .filter-section {
            background-color: #fffaf0; /* Floral white background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #ffe4b5;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        .filter-group.wide { flex: 2; min-width: 250px; }
        .filter-group label { color: #D2691E; }
        .filter-buttons { margin-top: 0; }
        .btn-filter { background-color: #FF7F50; color: #fff; } /* Coral */
        .btn-filter:hover { background-color: #FF6347; }
        .btn-clear-filter { background-color: #6c757d; color: #fff; }
        .btn-clear-filter:hover { background-color: #5a6268; }
        .btn-print { background-color: #20b2aa; color: #fff; }
        .btn-print:hover { background-color: #1a968a; }


        /* Events Table */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .data-table th, .data-table td {
            border-bottom: 1px solid #e0e0e0;
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }
        .data-table th {
            background-color: #fffaf0; /* Floral White */
            color: #D2691E;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .data-table tr:nth-child(even) { background-color: #fcf8f0; }
        .data-table tr:hover { background-color: #faeed9; }

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
        .btn-edit { background-color: #FFC107; color: #333; border-color: #FFC107; }
        .btn-edit:hover { background-color: #e0a800; border-color: #e0a800; }
        .btn-delete { background-color: #dc3545; color: #fff; border-color: #dc3545; }
        .btn-delete:hover { background-color: #c82333; border-color: #bd2130; }

        .text-center { text-align: center; }
        .text-muted { color: #6c757d; }
        .no-results { text-align: center; padding: 50px; font-size: 1.2em; color: #6c757d; }

        /* Pagination Styles */
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
        .pagination-info { color: #555; font-size: 0.95em; font-weight: 500; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-controls a, .pagination-controls span {
            display: block; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;
            text-decoration: none; color: #D2691E; background-color: #fff; transition: all 0.2s ease;
        }
        .pagination-controls a:hover { background-color: #e9ecef; border-color: #F5DEB3; }
        .pagination-controls .current-page, .pagination-controls .current-page:hover {
            background-color: #D2691E; color: #fff; border-color: #D2691E; cursor: default;
        }
        .pagination-controls .disabled, .pagination-controls .disabled:hover {
            color: #6c757d; background-color: #e9ecef; border-color: #dee2e6; cursor: not-allowed;
        }

        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; font-size: 10pt; padding: 10mm; }
            .printable-area h2, .printable-area h3 { color: #000; border-bottom: 1px solid #ccc; font-size: 16pt; margin-bottom: 15px; }
            .printable-area .data-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .printable-area .data-table th, .printable-area .data-table td { border: 1px solid #eee; padding: 8px 10px; }
            .printable-area .data-table th { background-color: #fffaf0; color: #000; }
            .printable-area .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group { display: none; }
            .printable-area .section-box .section-header { background-color: #f0f0f0; color: #333; border-bottom: 1px solid #ddd; }
            .printable-area .section-box .section-content { max-height: none !important; overflow: visible !important; transition: none !important; padding: 0 !important;}
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .section-box .section-header { margin: -15px -15px 15px -15px; padding: 12px 15px; font-size: 1.4em; }
            .form-grid, .filter-section { grid-template-columns: 1fr; }
            .filter-group.wide { min-width: unset; }
            .filter-buttons { flex-direction: column; width: 100%; }
            .btn-filter, .btn-clear-filter, .btn-print { width: 100%; justify-content: center; }
            .data-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-calendar-alt"></i> Manage Events</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Event Section -->
        <div class="section-box" id="add-edit-event-section">
            <div class="section-header" onclick="toggleSection('add-edit-event-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="false" aria-controls="add-edit-event-content">
                <h3 id="event-form-title"><i class="fas fa-plus-circle"></i> Add New Event</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="add-edit-event-content" class="section-content collapsed">
                <form id="event-form" action="manage_events.php" method="POST">
                    <input type="hidden" name="form_action" id="event-form-action" value="add_event">
                    <input type="hidden" name="event_id" id="event-id" value="">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">Event Title:</label>
                            <input type="text" id="title" name="title" required placeholder="e.g., Annual Sports Day">
                        </div>
                        <div class="form-group">
                            <label for="event_type">Event Type:</label>
                            <select id="event_type" name="event_type" required>
                                <option value="">-- Select Type --</option>
                                <option value="Holiday">Holiday</option>
                                <option value="Exam">Exam</option>
                                <option value="School Event">School Event</option>
                                <option value="Meeting">Meeting</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="datetime-local" id="start_date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date (Optional):</label>
                            <input type="datetime-local" id="end_date" name="end_date">
                        </div>
                        <div class="form-group">
                            <label for="color">Event Color:</label>
                            <input type="color" id="color" name="color" value="#007bff">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="3" placeholder="Detailed description of the event"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-form-submit" id="event-submit-btn"><i class="fas fa-plus-circle"></i> Add Event</button>
                        <button type="button" class="btn-form-cancel" id="event-cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Events Overview Section -->
        <div class="section-box printable-area" id="events-overview-section">
            <div class="section-header" onclick="toggleSection('events-overview-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="true" aria-controls="events-overview-content">
                <h3><i class="fas fa-list"></i> All School Events</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="events-overview-content" class="section-content">
                <div class="filter-section">
                    <form action="manage_events.php" method="GET" style="display:contents;">
                        <input type="hidden" name="section" value="overview">
                        
                        <div class="filter-group">
                            <label for="filter_event_type"><i class="fas fa-tags"></i> Event Type:</label>
                            <select id="filter_event_type" name="event_type">
                                <option value="">-- All Types --</option>
                                <option value="Holiday" <?php echo ($filter_event_type == 'Holiday') ? 'selected' : ''; ?>>Holiday</option>
                                <option value="Exam" <?php echo ($filter_event_type == 'Exam') ? 'selected' : ''; ?>>Exam</option>
                                <option value="School Event" <?php echo ($filter_event_type == 'School Event') ? 'selected' : ''; ?>>School Event</option>
                                <option value="Meeting" <?php echo ($filter_event_type == 'Meeting') ? 'selected' : ''; ?>>Meeting</option>
                                <option value="Other" <?php echo ($filter_event_type == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="filter-group wide">
                            <label for="search_query"><i class="fas fa-search"></i> Search Events:</label>
                            <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Title or Description">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                            <?php if ($filter_event_type || !empty($search_query)): ?>
                                <a href="manage_events.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                            <button type="button" class="btn-print" onclick="printTable('events-table-wrapper', 'School Events Report')"><i class="fas fa-print"></i> Print Events</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($events)): ?>
                    <p class="no-results">No events found matching your criteria.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;" id="events-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Color</th>
                                    <th>Created By</th>
                                    <th>Created On</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['title']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($event['description'] ?: 'N/A', 0, 100)); ?>
                                            <?php if (strlen($event['description'] ?: '') > 100): ?>
                                                ... <a href="#" onclick="alert('Full Description: <?php echo htmlspecialchars($event['description']); ?>'); return false;" class="text-muted">more</a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                                        <td><?php echo date("M j, Y H:i A", strtotime($event['start_date'])); ?></td>
                                        <td><?php echo ($event['end_date'] && $event['end_date'] !== '0000-00-00 00:00:00') ? date("M j, Y H:i A", strtotime($event['end_date'])) : 'N/A'; ?></td>
                                        <td style="background-color: <?php echo htmlspecialchars($event['color']); ?>; width: 30px;"></td>
                                        <td><?php echo htmlspecialchars($event['created_by_admin_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo date("M j, Y", strtotime($event['created_at'])); ?></td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <button class="btn-action btn-edit" onclick="editEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="javascript:void(0);" onclick="confirmDeleteEvent(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['title']); ?>')" class="btn-action btn-delete">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
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
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> events
                            </div>
                            <div class="pagination-controls">
                                <?php
                                $base_url_params = array_filter([
                                    'section' => 'overview',
                                    'event_type' => $filter_event_type,
                                    'search' => $search_query
                                ]);
                                $base_url = "manage_events.php?" . http_build_query($base_url_params);
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
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize collapsed state for sections
        document.querySelectorAll('.section-box .section-header').forEach(header => {
            const contentId = header.querySelector('.section-toggle-btn').getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            const button = header.querySelector('.section-toggle-btn');
            
            // "Add New Event" starts collapsed
            if (button.getAttribute('aria-expanded') === 'false') {
                content.classList.add('collapsed');
                button.querySelector('.fas').classList.remove('fa-chevron-down');
                button.querySelector('.fas').classList.add('fa-chevron-right');
            } else {
                // "All School Events" starts expanded
                content.style.maxHeight = content.scrollHeight + 'px';
                setTimeout(() => content.style.maxHeight = null, 500);
            }
        });
    });

    // Function to toggle the collapse state of a section
    window.toggleSection = function(contentId, button) {
        const content = document.getElementById(contentId);
        const icon = button.querySelector('.fas');

        if (content.classList.contains('collapsed')) {
            // Expand the section
            content.classList.remove('collapsed');
            content.style.maxHeight = content.scrollHeight + 'px'; // Set to scrollHeight for animation
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
            button.setAttribute('aria-expanded', 'true');
            setTimeout(() => { content.style.maxHeight = null; }, 500);
        } else {
            // Collapse the section
            content.style.maxHeight = content.scrollHeight + 'px'; // Set current height before collapsing
            void content.offsetHeight; // Trigger reflow for CSS transition
            content.classList.add('collapsed');
            content.style.maxHeight = '0'; // Collapse
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
            button.setAttribute('aria-expanded', 'false');
        }
    };

    // --- Event Management JS ---
    function editEvent(eventData) {
        // Ensure the form section is expanded
        const formContent = document.getElementById('add-edit-event-content');
        const formToggleButton = document.querySelector('#add-edit-event-section .section-toggle-btn');
        if (formContent.classList.contains('collapsed')) {
            toggleSection('add-edit-event-content', formToggleButton);
        }

        document.getElementById('event-form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Event: ' + eventData.title;
        document.getElementById('event-form-action').value = 'edit_event';
        document.getElementById('event-id').value = eventData.id;

        document.getElementById('title').value = eventData.title || '';
        document.getElementById('description').value = eventData.description || '';
        document.getElementById('event_type').value = eventData.event_type || '';
        document.getElementById('start_date').value = eventData.start_date ? formatDateTimeLocal(eventData.start_date) : '';
        document.getElementById('end_date').value = eventData.end_date && eventData.end_date !== '0000-00-00 00:00:00' ? formatDateTimeLocal(eventData.end_date) : '';
        document.getElementById('color').value = eventData.color || '#007bff'; // Default color if none set
        
        document.getElementById('event-submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Event';
        document.getElementById('event-cancel-btn').style.display = 'inline-flex';

        document.getElementById('add-edit-event-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Helper to format ISO datetime string for datetime-local input
    function formatDateTimeLocal(datetimeString) {
        const date = new Date(datetimeString);
        // Ensure two digits for month, day, hour, minute
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }


    document.getElementById('event-cancel-btn').addEventListener('click', function() {
        document.getElementById('event-form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Event';
        document.getElementById('event-form-action').value = 'add_event';
        document.getElementById('event-id').value = '';
        document.getElementById('event-form').reset(); // Resets all form fields
        document.getElementById('color').value = '#007bff'; // Reset color to default

        document.getElementById('event-submit-btn').innerHTML = '<i class="fas fa-plus-circle"></i> Add Event';
        document.getElementById('event-cancel-btn').style.display = 'none';

        // Optionally collapse the section
        const formContent = document.getElementById('add-edit-event-content');
        const formToggleButton = document.querySelector('#add-edit-event-section .section-toggle-btn');
        if (!formContent.classList.contains('collapsed')) {
             toggleSection('add-edit-event-content', formToggleButton);
        }
    });

    function confirmDeleteEvent(id, title) {
        if (confirm(`Are you sure you want to permanently delete the event "${title}"? This action cannot be undone.`)) {
            window.location.href = `manage_events.php?delete_id=${id}`;
        }
    }

    // --- Print Functionality (Universal for sections) ---
    function printTable(tableWrapperId, title) {
        const printTitle = title;
        const tableWrapper = document.getElementById(tableWrapperId);
        if (!tableWrapper) {
            alert('Printable section not found!');
            return;
        }

        const sectionContent = tableWrapper.closest('.section-content');
        const sectionHeader = sectionContent ? sectionContent.previousElementSibling : null;
        let isSectionCollapsed = false;

        if (sectionContent && sectionContent.classList.contains('collapsed')) {
            isSectionCollapsed = true;
            sectionContent.classList.remove('collapsed');
            sectionContent.style.maxHeight = sectionContent.scrollHeight + 'px';
            if (sectionHeader) {
                const icon = sectionHeader.querySelector('.fas');
                if (icon) {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-down');
                }
                sectionHeader.setAttribute('aria-expanded', 'true');
            }
        }
        
        setTimeout(() => {
            const printWindow = window.open('', '_blank');
            printWindow.document.write('<html><head><title>Print Report</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
                h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 25px; text-align: center; }
                h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
                .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 9pt; }
                .data-table th, .data-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
                .data-table th { background-color: #fffaf0; color: #000; font-weight: 700; text-transform: uppercase; }
                .data-table tr:nth-child(even) { background-color: #fcf8f0; }
                .pagination-container, .filter-section, .btn-print, .action-buttons-group { display: none; }
                .fas { margin-right: 3px; }
                .text-muted { color: #6c757d; }
            `);
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(`<h2 style="text-align: center;">${printTitle}</h2>`);
            printWindow.document.write(tableWrapper.innerHTML);
            printWindow.document.write('</body></html>');
            
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();

            if (isSectionCollapsed) {
                setTimeout(() => {
                    sectionContent.classList.add('collapsed');
                    sectionContent.style.maxHeight = '0';
                    if (sectionHeader) {
                        const icon = sectionHeader.querySelector('.fas');
                        if (icon) {
                            icon.classList.remove('fa-chevron-down');
                            icon.classList.add('fa-chevron-right');
                        }
                        sectionHeader.setAttribute('aria-expanded', 'false');
                    }
                }, 100);
            }
        }, 100);
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>