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
$records_per_page = 10; // Number of announcements to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_is_active = isset($_GET['is_active']) && $_GET['is_active'] !== '' ? (int)$_GET['is_active'] : null;


// --- Process Form Submissions (Add, Edit Announcement) ---
if (isset($_POST['form_action']) && ($_POST['form_action'] == 'add_announcement' || $_POST['form_action'] == 'edit_announcement')) {
    $action = $_POST['form_action'];

    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $posted_by = $principal_name . " (Principal)"; // Automatically set posted_by

    if (empty($title) || empty($content)) {
        set_session_message("Title and Content are required for an announcement.", "danger");
        header("location: manage_announcements.php");
        exit;
    }

    if ($action == 'add_announcement') {
        $sql = "INSERT INTO announcements (title, content, posted_by, is_active) VALUES (?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssi", $title, $content, $posted_by, $is_active);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Announcement added successfully.", "success");
            } else {
                set_session_message("Error adding announcement: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'edit_announcement') {
        $announcement_id = (int)$_POST['announcement_id'];
        if (empty($announcement_id)) {
            set_session_message("Invalid Announcement ID for editing.", "danger");
            header("location: manage_announcements.php");
            exit;
        }

        $sql = "UPDATE announcements SET title = ?, content = ?, is_active = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssii", $title, $content, $is_active, $announcement_id);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Announcement updated successfully.", "success");
            } else {
                set_session_message("Error updating announcement: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    }
    header("location: manage_announcements.php?page={$current_page}");
    exit;
}

// --- Process Announcement Deletion ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid Announcement ID for deletion.", "danger");
        header("location: manage_announcements.php");
        exit;
    }

    $sql = "DELETE FROM announcements WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Announcement deleted successfully.", "success");
            } else {
                set_session_message("Announcement not found or already deleted.", "danger");
            }
        } else {
            set_session_message("Error deleting announcement: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_announcements.php?page={$current_page}");
    exit;
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_is_active !== null) {
    $where_clauses[] = "is_active = ?";
    $params[] = $filter_is_active;
    $types .= "i";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(title LIKE ? OR content LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(id) FROM announcements WHERE " . $where_sql;

if ($stmt = mysqli_prepare($link, $total_records_sql)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_records = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
} else {
    $message = "Error counting announcements: " . mysqli_error($link);
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


// --- Fetch Announcements Data (with filters and pagination) ---
$announcements = [];
$sql_fetch_announcements = "SELECT * FROM announcements WHERE " . $where_sql . " ORDER BY created_at DESC LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_announcements)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $announcements = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching announcements: " . mysqli_error($link);
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
    <title>Manage Announcements - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #FFDAB9, #FFC0CB, #E0FFFF, #AFEEEE); /* Peach, pink, light blues */
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
            color: #FF69B4; /* Hot Pink */
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
            background-color: #FF69B4; /* Hot Pink */
            color: #fff;
            padding: 15px 20px;
            margin: -30px -30px 20px -30px; /* Adjust margin to fill parent box padding */
            border-bottom: 1px solid #C71585;
            border-radius: 10px 10px 0 0;
            font-size: 1.6em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .section-header:hover {
            background-color: #C71585;
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

        /* Forms (Announcement Add/Edit, Filters) */
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
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23FF69B4%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23FF69B4%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
        }
        .form-group input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.1);
        }
        .form-group.checkbox-group label {
            display: flex;
            align-items: center;
            font-weight: 500;
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
        .btn-form-submit { background-color: #FF69B4; color: #fff; }
        .btn-form-submit:hover { background-color: #C71585; }
        .btn-form-cancel { background-color: #6c757d; color: #fff; }
        .btn-form-cancel:hover { background-color: #5a6268; }

        .filter-section {
            background-color: #fff0f5; /* Floral White background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #ffe4e1;
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
        .filter-group label { color: #FF69B4; }
        .filter-buttons { margin-top: 0; }
        .btn-filter { background-color: #DA70D6; color: #fff; }
        .btn-filter:hover { background-color: #C71585; }
        .btn-clear-filter { background-color: #6c757d; color: #fff; }
        .btn-clear-filter:hover { background-color: #5a6268; }
        .btn-print { background-color: #20b2aa; color: #fff; }
        .btn-print:hover { background-color: #1a968a; }


        /* Announcements Table */
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
            background-color: #fff0f5; /* Floral White */
            color: #C71585;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .data-table tr:nth-child(even) { background-color: #fcf8f8; }
        .data-table tr:hover { background-color: #faeded; }

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

        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-Active { background-color: #d4edda; color: #155724; }
        .status-Inactive { background-color: #f8d7da; color: #721c24; }
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
            text-decoration: none; color: #FF69B4; background-color: #fff; transition: all 0.2s ease;
        }
        .pagination-controls a:hover { background-color: #e9ecef; border-color: #FFC0CB; }
        .pagination-controls .current-page, .pagination-controls .current-page:hover {
            background-color: #FF69B4; color: #fff; border-color: #FF69B4; cursor: default;
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
            .printable-area .data-table th { background-color: #fff0f5; color: #000; }
            .printable-area .status-badge { padding: 3px 6px; font-size: 0.7em; }
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
        <h2><i class="fas fa-bullhorn"></i> Manage Announcements</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Announcement Section -->
        <div class="section-box" id="add-edit-announcement-section">
            <div class="section-header" onclick="toggleSection('add-edit-announcement-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="false" aria-controls="add-edit-announcement-content">
                <h3 id="announcement-form-title"><i class="fas fa-plus-circle"></i> Add New Announcement</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="add-edit-announcement-content" class="section-content collapsed">
                <form id="announcement-form" action="manage_announcements.php" method="POST">
                    <input type="hidden" name="form_action" id="announcement-form-action" value="add_announcement">
                    <input type="hidden" name="announcement_id" id="announcement-id" value="">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">Title:</label>
                            <input type="text" id="title" name="title" required placeholder="e.g., School Holiday, Exam Schedule Update">
                        </div>
                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" id="is_active" name="is_active" checked>
                                Is Active (Visible to all)
                            </label>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="content">Content:</label>
                            <textarea id="content" name="content" rows="5" required placeholder="Detailed announcement content"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-form-submit" id="announcement-submit-btn"><i class="fas fa-plus-circle"></i> Add Announcement</button>
                        <button type="button" class="btn-form-cancel" id="announcement-cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Announcements Overview Section -->
        <div class="section-box printable-area" id="announcements-overview-section">
            <div class="section-header" onclick="toggleSection('announcements-overview-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="true" aria-controls="announcements-overview-content">
                <h3><i class="fas fa-list"></i> All Announcements</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="announcements-overview-content" class="section-content">
                <div class="filter-section">
                    <form action="manage_announcements.php" method="GET" style="display:contents;">
                        <input type="hidden" name="section" value="overview">
                        
                        <div class="filter-group">
                            <label for="filter_is_active"><i class="fas fa-toggle-on"></i> Status:</label>
                            <select id="filter_is_active" name="is_active">
                                <option value="">-- All Statuses --</option>
                                <option value="1" <?php echo ($filter_is_active === 1) ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo ($filter_is_active === 0) ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="filter-group wide">
                            <label for="search_query"><i class="fas fa-search"></i> Search Announcements:</label>
                            <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Title or Content">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                            <?php if ($filter_is_active !== null || !empty($search_query)): ?>
                                <a href="manage_announcements.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                            <button type="button" class="btn-print" onclick="printTable('announcements-table-wrapper', 'School Announcements Report')"><i class="fas fa-print"></i> Print Announcements</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($announcements)): ?>
                    <p class="no-results">No announcements found matching your criteria.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;" id="announcements-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Content</th>
                                    <th>Posted By</th>
                                    <th>Posted On</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($announcements as $announcement): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($announcement['content'], 0, 150)); ?>
                                            <?php if (strlen($announcement['content']) > 150): ?>
                                                ... <a href="#" onclick="alert('Full Content: <?php echo htmlspecialchars($announcement['content']); ?>'); return false;" class="text-muted">more</a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($announcement['posted_by'] ?: 'N/A'); ?></td>
                                        <td><?php echo date("M j, Y", strtotime($announcement['created_at'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo ($announcement['is_active'] ? 'Active' : 'Inactive'); ?>">
                                                <?php echo ($announcement['is_active'] ? 'Active' : 'Inactive'); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <button class="btn-action btn-edit" onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="javascript:void(0);" onclick="confirmDeleteAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')" class="btn-action btn-delete">
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
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> announcements
                            </div>
                            <div class="pagination-controls">
                                <?php
                                $base_url_params = array_filter([
                                    'section' => 'overview',
                                    'is_active' => $filter_is_active,
                                    'search' => $search_query
                                ]);
                                $base_url = "manage_announcements.php?" . http_build_query($base_url_params);
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
            
            // "Add New Announcement" starts collapsed
            if (button.getAttribute('aria-expanded') === 'false') {
                content.classList.add('collapsed');
                button.querySelector('.fas').classList.remove('fa-chevron-down');
                button.querySelector('.fas').classList.add('fa-chevron-right');
            } else {
                // "All Announcements" starts expanded
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

    // --- Announcement Management JS ---
    function editAnnouncement(announcementData) {
        // Ensure the form section is expanded
        const formContent = document.getElementById('add-edit-announcement-content');
        const formToggleButton = document.querySelector('#add-edit-announcement-section .section-toggle-btn');
        if (formContent.classList.contains('collapsed')) {
            toggleSection('add-edit-announcement-content', formToggleButton);
        }

        document.getElementById('announcement-form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Announcement: ' + announcementData.title;
        document.getElementById('announcement-form-action').value = 'edit_announcement';
        document.getElementById('announcement-id').value = announcementData.id;

        document.getElementById('title').value = announcementData.title || '';
        document.getElementById('content').value = announcementData.content || '';
        document.getElementById('is_active').checked = announcementData.is_active == 1;
        
        document.getElementById('announcement-submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Announcement';
        document.getElementById('announcement-cancel-btn').style.display = 'inline-flex';

        document.getElementById('add-edit-announcement-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('announcement-cancel-btn').addEventListener('click', function() {
        document.getElementById('announcement-form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Announcement';
        document.getElementById('announcement-form-action').value = 'add_announcement';
        document.getElementById('announcement-id').value = '';
        document.getElementById('announcement-form').reset(); // Resets all form fields
        document.getElementById('is_active').checked = true; // Reset checkbox to default checked

        document.getElementById('announcement-submit-btn').innerHTML = '<i class="fas fa-plus-circle"></i> Add Announcement';
        document.getElementById('announcement-cancel-btn').style.display = 'none';

        // Optionally collapse the section
        const formContent = document.getElementById('add-edit-announcement-content');
        const formToggleButton = document.querySelector('#add-edit-announcement-section .section-toggle-btn');
        if (!formContent.classList.contains('collapsed')) {
             toggleSection('add-edit-announcement-content', formToggleButton);
        }
    });

    function confirmDeleteAnnouncement(id, title) {
        if (confirm(`Are you sure you want to permanently delete the announcement "${title}"? This action cannot be undone.`)) {
            window.location.href = `manage_announcements.php?delete_id=${id}`;
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
                .data-table th { background-color: #fff0f5; color: #000; font-weight: 700; text-transform: uppercase; }
                .data-table tr:nth-child(even) { background-color: #fcf8f8; }
                .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
                .status-Active { background-color: #d4edda; color: #155724; }
                .status-Inactive { background-color: #f8d7da; color: #721c24; }
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