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

// --- Pagination Configuration ---
$records_per_page = 10; // Number of departments to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Process Form Submissions (Add, Edit Department) ---
if (isset($_POST['form_action']) && ($_POST['form_action'] == 'add_department' || $_POST['form_action'] == 'edit_department')) {
    $action = $_POST['form_action'];

    $department_name = trim($_POST['department_name']);
    $hod_teacher_id = empty(trim($_POST['hod_teacher_id'])) ? NULL : (int)$_POST['hod_teacher_id'];
    $description = trim($_POST['description']);

    if (empty($department_name)) {
        set_session_message("Department Name is required.", "danger");
        header("location: manage_departments.php");
        exit;
    }

    if ($action == 'add_department') {
        // Check for duplicate department name
        $check_sql = "SELECT id FROM departments WHERE department_name = ?";
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "s", $department_name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                set_session_message("A department with this name already exists.", "danger");
                mysqli_stmt_close($stmt);
                header("location: manage_departments.php");
                exit;
            }
            mysqli_stmt_close($stmt);
        }

        $sql = "INSERT INTO departments (department_name, hod_teacher_id, description) VALUES (?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sis", $department_name, $hod_teacher_id, $description);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Department added successfully.", "success");
            } else {
                set_session_message("Error adding department: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'edit_department') {
        $department_id = (int)$_POST['department_id'];
        if (empty($department_id)) {
            set_session_message("Invalid Department ID for editing.", "danger");
            header("location: manage_departments.php");
            exit;
        }

        // Check for duplicate department name, excluding current department
        $check_sql = "SELECT id FROM departments WHERE department_name = ? AND id != ?";
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "si", $department_name, $department_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                set_session_message("A department with this name already exists.", "danger");
                mysqli_stmt_close($stmt);
                header("location: manage_departments.php");
                exit;
            }
            mysqli_stmt_close($stmt);
        }

        $sql = "UPDATE departments SET department_name = ?, hod_teacher_id = ?, description = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sisi", $department_name, $hod_teacher_id, $description, $department_id);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Department updated successfully.", "success");
            } else {
                set_session_message("Error updating department: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    }
    header("location: manage_departments.php?page={$current_page}");
    exit;
}

// --- Process Department Deletion ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid Department ID for deletion.", "danger");
        header("location: manage_departments.php");
        exit;
    }

    // Before deleting a department, ensure no teachers are associated with it
    // Or, if your FK is ON DELETE SET NULL, the teachers will just become unassigned
    // We'll check for restrictive FKs and warn
    $check_teachers_sql = "SELECT COUNT(id) FROM teachers WHERE department_id = ?";
    if ($stmt_check = mysqli_prepare($link, $check_teachers_sql)) {
        mysqli_stmt_bind_param($stmt_check, "i", $delete_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_bind_result($stmt_check, $teacher_count);
        mysqli_stmt_fetch($stmt_check);
        mysqli_stmt_close($stmt_check);

        if ($teacher_count > 0) {
            set_session_message("Cannot delete department. There are {$teacher_count} teachers currently assigned to it. Please reassign them first.", "danger");
            header("location: manage_departments.php?page={$current_page}");
            exit;
        }
    }

    $sql = "DELETE FROM departments WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Department deleted successfully.", "success");
            } else {
                set_session_message("Department not found or already deleted.", "danger");
            }
        } else {
            // General FK check in case other tables somehow link restrictively
            if (mysqli_errno($link) == 1451) { 
                set_session_message("Cannot delete department. It has related records (e.g., HOD assignment in other tables).", "danger");
            } else {
                set_session_message("Error deleting department: " . mysqli_error($link), "danger");
            }
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_departments.php?page={$current_page}");
    exit;
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(d.department_name LIKE ? OR t.full_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(d.id)
                      FROM departments d
                      LEFT JOIN teachers t ON d.hod_teacher_id = t.id
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
    $message = "Error counting departments: " . mysqli_error($link);
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


// --- Fetch Departments Data (with filters and pagination) ---
$departments = [];
$sql_fetch_departments = "SELECT
                                d.id, d.department_name, d.description, d.hod_teacher_id,
                                t.full_name AS hod_name,
                                (SELECT COUNT(id) FROM teachers WHERE department_id = d.id) AS teacher_count
                            FROM departments d
                            LEFT JOIN teachers t ON d.hod_teacher_id = t.id
                            WHERE " . $where_sql . "
                            ORDER BY d.department_name ASC
                            LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_departments)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $departments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching departments: " . mysqli_error($link);
    $message_type = "danger";
}

// --- Fetch All Teachers for HOD Dropdown ---
$all_teachers_for_dropdown = [];
$sql_all_teachers_for_dropdown = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_all_teachers_for_dropdown)) {
    $all_teachers_for_dropdown = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching teachers for HOD dropdown: " . mysqli_error($link);
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
    <title>Manage Departments - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #F0FFF0, #E0FFFF, #AFEEEE, #B0E0E6); /* Light greens and blues */
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
            color: #2E8B57; /* Sea Green */
            margin-bottom: 30px;
            border-bottom: 2px solid #98FB98;
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
            background-color: #4CAF50; /* Green */
            color: #fff;
            padding: 15px 20px;
            margin: -30px -30px 20px -30px; /* Adjust margin to fill parent box padding */
            border-bottom: 1px solid #388E3C;
            border-radius: 10px 10px 0 0;
            font-size: 1.6em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .section-header:hover {
            background-color: #388E3C;
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

        /* Forms (Department Add/Edit, Filters) */
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
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%234CAF50%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%234CAF50%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
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
        .btn-form-submit { background-color: #4CAF50; color: #fff; }
        .btn-form-submit:hover { background-color: #388E3C; }
        .btn-form-cancel { background-color: #6c757d; color: #fff; }
        .btn-form-cancel:hover { background-color: #5a6268; }

        .filter-section {
            background-color: #e8f5e9; /* Light green background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #c8e6c9;
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
        .filter-group label { color: #2E8B57; }
        .filter-buttons { margin-top: 0; }
        .btn-filter { background-color: #66BB6A; color: #fff; }
        .btn-filter:hover { background-color: #4CAF50; }
        .btn-clear-filter { background-color: #6c757d; color: #fff; }
        .btn-clear-filter:hover { background-color: #5a6268; }
        .btn-print { background-color: #20b2aa; color: #fff; }
        .btn-print:hover { background-color: #1a968a; }


        /* Departments Table */
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
            background-color: #e0f2f7;
            color: #004085;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .data-table tr:nth-child(even) { background-color: #f8fcff; }
        .data-table tr:hover { background-color: #eef7fc; }

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
            text-decoration: none; color: #4CAF50; background-color: #fff; transition: all 0.2s ease;
        }
        .pagination-controls a:hover { background-color: #e9ecef; border-color: #c8e6c9; }
        .pagination-controls .current-page, .pagination-controls .current-page:hover {
            background-color: #4CAF50; color: #fff; border-color: #4CAF50; cursor: default;
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
            .printable-area .data-table th { background-color: #e0f2f7; color: #000; }
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
        <h2><i class="fas fa-building"></i> Manage Departments</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Department Section -->
        <div class="section-box" id="add-edit-department-section">
            <div class="section-header" onclick="toggleSection('add-edit-department-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="false" aria-controls="add-edit-department-content">
                <h3 id="department-form-title"><i class="fas fa-plus-circle"></i> Add New Department</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="add-edit-department-content" class="section-content collapsed">
                <form id="department-form" action="manage_departments.php" method="POST">
                    <input type="hidden" name="form_action" id="department-form-action" value="add_department">
                    <input type="hidden" name="department_id" id="department-id" value="">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="department_name">Department Name:</label>
                            <input type="text" id="department_name" name="department_name" required placeholder="e.g., Science, Arts, Sports">
                        </div>
                        <div class="form-group">
                            <label for="hod_teacher_id">Head of Department (HOD):</label>
                            <select id="hod_teacher_id" name="hod_teacher_id">
                                <option value="">-- Select HOD (Optional) --</option>
                                <?php foreach ($all_teachers_for_dropdown as $teacher): ?>
                                    <option value="<?php echo htmlspecialchars($teacher['id']); ?>">
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="3" placeholder="Brief description of the department's focus"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-form-submit" id="department-submit-btn"><i class="fas fa-plus-circle"></i> Add Department</button>
                        <button type="button" class="btn-form-cancel" id="department-cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Departments Overview Section -->
        <div class="section-box printable-area" id="departments-overview-section">
            <div class="section-header" onclick="toggleSection('departments-overview-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="true" aria-controls="departments-overview-content">
                <h3><i class="fas fa-list"></i> Departments Overview</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="departments-overview-content" class="section-content">
                <div class="filter-section">
                    <form action="manage_departments.php" method="GET" style="display:contents;">
                        <div class="filter-group wide">
                            <label for="search_query"><i class="fas fa-search"></i> Search Departments:</label>
                            <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Department Name or HOD">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                            <?php if (!empty($search_query)): ?>
                                <a href="manage_departments.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                            <button type="button" class="btn-print" onclick="printTable('departments-table-wrapper', 'Departments Report')"><i class="fas fa-print"></i> Print Departments</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($departments)): ?>
                    <p class="no-results">No departments found matching your criteria.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;" id="departments-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Department Name</th>
                                    <th>HOD</th>
                                    <th>Teachers Count</th>
                                    <th>Description</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $department): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($department['department_name']); ?></td>
                                        <td><?php echo htmlspecialchars($department['hod_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($department['teacher_count']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($department['description'] ?: 'N/A', 0, 100)); ?>
                                            <?php if (strlen($department['description'] ?: '') > 100): ?>
                                                ... <a href="#" onclick="alert('Full Description: <?php echo htmlspecialchars($department['description']); ?>'); return false;" class="text-muted">more</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <button class="btn-action btn-edit" onclick="editDepartment(<?php echo htmlspecialchars(json_encode($department)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="javascript:void(0);" onclick="confirmDeleteDepartment(<?php echo $department['id']; ?>, '<?php echo htmlspecialchars($department['department_name']); ?>', <?php echo $department['teacher_count']; ?>)" class="btn-action btn-delete">
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
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                            </div>
                            <div class="pagination-controls">
                                <?php
                                $base_url_params = array_filter([
                                    'search' => $search_query
                                ]);
                                $base_url = "manage_departments.php?" . http_build_query($base_url_params);
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
            
            // "Add New Department" starts collapsed by default
            if (button.getAttribute('aria-expanded') === 'false') {
                content.classList.add('collapsed');
                button.querySelector('.fas').classList.remove('fa-chevron-down');
                button.querySelector('.fas').classList.add('fa-chevron-right');
            } else {
                // Ensure content is expanded initially, remove inline style after transition is set
                content.style.maxHeight = content.scrollHeight + 'px';
                setTimeout(() => content.style.maxHeight = null, 500); // Allow CSS to take over for transitions
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
            // After animation, remove max-height to allow content to grow dynamically
            setTimeout(() => { content.style.maxHeight = null; }, 500); // Match transition time
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

    // --- Department Management JS ---
    function editDepartment(departmentData) {
        // Ensure the form section is expanded
        const formContent = document.getElementById('add-edit-department-content');
        const formToggleButton = document.querySelector('#add-edit-department-section .section-toggle-btn');
        if (formContent.classList.contains('collapsed')) {
            toggleSection('add-edit-department-content', formToggleButton);
        }

        document.getElementById('department-form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Department: ' + departmentData.department_name;
        document.getElementById('department-form-action').value = 'edit_department';
        document.getElementById('department-id').value = departmentData.id;

        document.getElementById('department_name').value = departmentData.department_name || '';
        document.getElementById('hod_teacher_id').value = departmentData.hod_teacher_id || '';
        document.getElementById('description').value = departmentData.description || '';

        document.getElementById('department-submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Department';
        document.getElementById('department-cancel-btn').style.display = 'inline-flex';

        document.getElementById('add-edit-department-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('department-cancel-btn').addEventListener('click', function() {
        document.getElementById('department-form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Department';
        document.getElementById('department-form-action').value = 'add_department';
        document.getElementById('department-id').value = '';
        document.getElementById('department-form').reset(); // Resets all form fields

        document.getElementById('department-submit-btn').innerHTML = '<i class="fas fa-plus-circle"></i> Add Department';
        document.getElementById('department-cancel-btn').style.display = 'none';

        // Optionally collapse the section after canceling
        const formContent = document.getElementById('add-edit-department-content');
        const formToggleButton = document.querySelector('#add-edit-department-section .section-toggle-btn');
        if (!formContent.classList.contains('collapsed')) {
             toggleSection('add-edit-department-content', formToggleButton);
        }
    });

    function confirmDeleteDepartment(id, name, teacherCount) {
        if (teacherCount > 0) {
            alert(`Cannot delete department "${name}". There are ${teacherCount} teachers currently assigned to it. Please reassign them first.`);
            return false;
        }
        if (confirm(`Are you sure you want to permanently delete the department "${name}"? This action cannot be undone.`)) {
            window.location.href = `manage_departments.php?delete_id=${id}`;
        }
    }

    // --- Print Functionality ---
    function printTable(tableWrapperId, title) {
        const printTitle = title;
        const tableWrapper = document.getElementById(tableWrapperId);
        if (!tableWrapper) {
            alert('Printable section not found!');
            return;
        }
        
        // Give a brief moment for DOM to render before printing
        setTimeout(() => {
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write('<html><head><title>Print Report</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
            printWindow.document.write('<style>');
            // Inject most relevant CSS styles for printing
            printWindow.document.write(`
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
                h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 25px; text-align: center; }
                h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
                .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 9pt; }
                .data-table th, .data-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
                .data-table th { background-color: #e0f2f7; color: #000; font-weight: 700; text-transform: uppercase; }
                .data-table tr:nth-child(even) { background-color: #f8fcff; }
                .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group { display: none; }
                .fas { margin-right: 3px; }
                .text-muted { color: #6c757d; }
            `);
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(`<h2 style="text-align: center;">${printTitle}</h2>`); // Main title for the printout
            printWindow.document.write(tableWrapper.innerHTML); // Only the table content
            printWindow.document.write('</body></html>');
            
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            // printWindow.close(); // Optionally close after printing
        }, 100); // Small delay before printing
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>