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

// --- Process Scholarship Type Form Submissions (Add/Edit) ---
if (isset($_POST['form_action']) && ($_POST['form_action'] == 'add_scholarship_type' || $_POST['form_action'] == 'edit_scholarship_type')) {
    $action = $_POST['form_action'];

    $scholarship_name = trim($_POST['scholarship_name']);
    $description = trim($_POST['description']);
    $type = trim($_POST['type']);
    $value = (float)$_POST['value'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($scholarship_name) || empty($type) || empty($value)) {
        set_session_message("Scholarship Name, Type, and Value are required.", "danger");
        header("location: manage_scholarships.php");
        exit;
    }

    if ($value <= 0) {
        set_session_message("Value must be greater than 0.", "danger");
        header("location: manage_scholarships.php");
        exit;
    }

    if ($action == 'add_scholarship_type') {
        // Check for duplicate scholarship name
        $check_sql = "SELECT id FROM scholarships WHERE scholarship_name = ?";
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "s", $scholarship_name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                set_session_message("A scholarship with this name already exists.", "danger");
                mysqli_stmt_close($stmt);
                header("location: manage_scholarships.php");
                exit;
            }
            mysqli_stmt_close($stmt);
        }

        $sql = "INSERT INTO scholarships (scholarship_name, description, type, value, is_active) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssdii", $scholarship_name, $description, $type, $value, $is_active);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Scholarship type added successfully.", "success");
            } else {
                set_session_message("Error adding scholarship type: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'edit_scholarship_type') {
        $scholarship_id = (int)$_POST['scholarship_id'];
        if (empty($scholarship_id)) {
            set_session_message("Invalid Scholarship ID for editing.", "danger");
            header("location: manage_scholarships.php");
            exit;
        }

        // Check for duplicate scholarship name, excluding current scholarship
        $check_sql = "SELECT id FROM scholarships WHERE scholarship_name = ? AND id != ?";
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "si", $scholarship_name, $scholarship_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                set_session_message("Another scholarship with this name already exists.", "danger");
                mysqli_stmt_close($stmt);
                header("location: manage_scholarships.php");
                exit;
            }
            mysqli_stmt_close($stmt);
        }

        $sql = "UPDATE scholarships SET scholarship_name = ?, description = ?, type = ?, value = ?, is_active = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssdiis", $scholarship_name, $description, $type, $value, $is_active, $scholarship_id);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Scholarship type updated successfully.", "success");
            } else {
                set_session_message("Error updating scholarship type: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    }
    header("location: manage_scholarships.php?section=types&page={$current_page}");
    exit;
}

// --- Process Scholarship Type Deletion ---
if (isset($_GET['delete_type_id'])) {
    $delete_id = (int)$_GET['delete_type_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid Scholarship Type ID for deletion.", "danger");
        header("location: manage_scholarships.php");
        exit;
    }

    // Check for associated student scholarships before deleting
    $check_assignments_sql = "SELECT COUNT(id) FROM student_scholarships WHERE scholarship_id = ?";
    if ($stmt_check = mysqli_prepare($link, $check_assignments_sql)) {
        mysqli_stmt_bind_param($stmt_check, "i", $delete_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_bind_result($stmt_check, $assignment_count);
        mysqli_stmt_fetch($stmt_check);
        mysqli_stmt_close($stmt_check);

        if ($assignment_count > 0) {
            set_session_message("Cannot delete scholarship type. It is currently assigned to {$assignment_count} students. Please remove assignments first.", "danger");
            header("location: manage_scholarships.php?section=types&page={$current_page}");
            exit;
        }
    }

    $sql = "DELETE FROM scholarships WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Scholarship type deleted successfully.", "success");
            } else {
                set_session_message("Scholarship type not found or already deleted.", "danger");
            }
        } else {
            set_session_message("Error deleting scholarship type: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_scholarships.php?section=types&page={$current_page}");
    exit;
}

// --- Process Student Scholarship Assignment Submission ---
if (isset($_POST['form_action']) && $_POST['form_action'] == 'assign_scholarship') {
    $student_id = (int)$_POST['student_id_assign'];
    $scholarship_id = (int)$_POST['scholarship_id_assign'];
    $assigned_date = trim($_POST['assigned_date']);
    $notes = trim($_POST['notes_assign']);

    if (empty($student_id) || empty($scholarship_id) || empty($assigned_date)) {
        set_session_message("Student, Scholarship, and Assignment Date are required.", "danger");
        header("location: manage_scholarships.php?section=assignments&page={$current_page}");
        exit;
    }

    // Check for duplicate assignment
    $check_sql = "SELECT id FROM student_scholarships WHERE student_id = ? AND scholarship_id = ?";
    if ($stmt = mysqli_prepare($link, $check_sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $student_id, $scholarship_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            set_session_message("This student already has this scholarship assigned.", "danger");
            mysqli_stmt_close($stmt);
            header("location: manage_scholarships.php?section=assignments&page={$current_page}");
            exit;
        }
        mysqli_stmt_close($stmt);
    }

    $sql = "INSERT INTO student_scholarships (student_id, scholarship_id, assigned_date, notes, assigned_by_admin_id) VALUES (?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iissi", $student_id, $scholarship_id, $assigned_date, $notes, $principal_id);
        if (mysqli_stmt_execute($stmt)) {
            set_session_message("Scholarship assigned successfully.", "success");
        } else {
            set_session_message("Error assigning scholarship: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_scholarships.php?section=assignments&page={$current_page}");
    exit;
}

// --- Process Student Scholarship Assignment Deletion ---
if (isset($_GET['delete_assignment_id'])) {
    $delete_id = (int)$_GET['delete_assignment_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid Assignment ID for deletion.", "danger");
        header("location: manage_scholarships.php");
        exit;
    }

    $sql = "DELETE FROM student_scholarships WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Scholarship assignment deleted successfully.", "success");
            } else {
                set_session_message("Scholarship assignment not found or already deleted.", "danger");
            }
        } else {
            set_session_message("Error deleting assignment: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_scholarships.php?section=assignments&page={$current_page}");
    exit;
}


// --- Scholarship Types Pagination & Filters ---
$types_records_per_page = 10;
$types_current_page = isset($_GET['types_page']) && is_numeric($_GET['types_page']) ? (int)$_GET['types_page'] : 1;
$types_search_query = isset($_GET['types_search']) ? trim($_GET['types_search']) : '';

$types_where_clauses = ["1=1"];
$types_params = [];
$types_types = "";

if (!empty($types_search_query)) {
    $types_search_term = "%" . $types_search_query . "%";
    $types_where_clauses[] = "(scholarship_name LIKE ? OR description LIKE ? OR type LIKE ?)";
    $types_params[] = $types_search_term;
    $types_params[] = $types_search_term;
    $types_params[] = $types_search_term;
    $types_types .= "sss";
}
$types_where_sql = implode(" AND ", $types_where_clauses);

$total_scholarship_types = 0;
$total_types_sql = "SELECT COUNT(id) FROM scholarships WHERE " . $types_where_sql;
if ($stmt = mysqli_prepare($link, $total_types_sql)) {
    if (!empty($types_params)) {
        mysqli_stmt_bind_param($stmt, $types_types, ...$types_params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_scholarship_types = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
}
$total_types_pages = ceil($total_scholarship_types / $types_records_per_page);
if ($types_current_page < 1) $types_current_page = 1;
elseif ($types_current_page > $total_types_pages && $total_types_pages > 0) $types_current_page = $total_types_pages;
elseif ($total_scholarship_types == 0) $types_current_page = 1;
$types_offset = ($types_current_page - 1) * $types_records_per_page;

$scholarship_types = [];
$sql_fetch_types = "SELECT * FROM scholarships WHERE " . $types_where_sql . " ORDER BY scholarship_name ASC LIMIT ? OFFSET ?";
if ($stmt = mysqli_prepare($link, $sql_fetch_types)) {
    $types_params_pagination = $types_params;
    $types_params_pagination[] = $types_records_per_page;
    $types_params_pagination[] = $types_offset;
    $types_types_pagination = $types_types . "ii";
    mysqli_stmt_bind_param($stmt, $types_types_pagination, ...$types_params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $scholarship_types = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


// --- Student Scholarship Assignments Pagination & Filters ---
$assignments_records_per_page = 10;
$assignments_current_page = isset($_GET['assignments_page']) && is_numeric($_GET['assignments_page']) ? (int)$_GET['assignments_page'] : 1;
$assignments_filter_student_id = isset($_GET['assignments_student_id']) && is_numeric($_GET['assignments_student_id']) ? (int)$_GET['assignments_student_id'] : null;
$assignments_filter_scholarship_id = isset($_GET['assignments_scholarship_id']) && is_numeric($_GET['assignments_scholarship_id']) ? (int)$_GET['assignments_scholarship_id'] : null;
$assignments_search_query = isset($_GET['assignments_search']) ? trim($_GET['assignments_search']) : '';
$assignments_filter_class_id = isset($_GET['assignments_class_id']) && is_numeric($_GET['assignments_class_id']) ? (int)$_GET['assignments_class_id'] : null;


$assignments_where_clauses = ["1=1"];
$assignments_params = [];
$assignments_types = "";

if ($assignments_filter_student_id) {
    $assignments_where_clauses[] = "ss.student_id = ?";
    $assignments_params[] = $assignments_filter_student_id;
    $assignments_types .= "i";
}
if ($assignments_filter_scholarship_id) {
    $assignments_where_clauses[] = "ss.scholarship_id = ?";
    $assignments_params[] = $assignments_filter_scholarship_id;
    $assignments_types .= "i";
}
if ($assignments_filter_class_id) {
    $assignments_where_clauses[] = "s.class_id = ?";
    $assignments_params[] = $assignments_filter_class_id;
    $assignments_types .= "i";
}
if (!empty($assignments_search_query)) {
    $assignments_search_term = "%" . $assignments_search_query . "%";
    $assignments_where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR sch.scholarship_name LIKE ? OR ss.notes LIKE ?)";
    $assignments_params[] = $assignments_search_term;
    $assignments_params[] = $assignments_search_term;
    $assignments_params[] = $assignments_search_term;
    $assignments_params[] = $assignments_search_term;
    $assignments_types .= "ssss";
}
$assignments_where_sql = implode(" AND ", $assignments_where_clauses);

$total_assignments = 0;
$total_assignments_sql = "SELECT COUNT(ss.id)
                             FROM student_scholarships ss
                             JOIN students s ON ss.student_id = s.id
                             JOIN scholarships sch ON ss.scholarship_id = sch.id
                             WHERE " . $assignments_where_sql;
if ($stmt = mysqli_prepare($link, $total_assignments_sql)) {
    if (!empty($assignments_params)) {
        mysqli_stmt_bind_param($stmt, $assignments_types, ...$assignments_params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_assignments = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
}
$total_assignments_pages = ceil($total_assignments / $assignments_records_per_page);
if ($assignments_current_page < 1) $assignments_current_page = 1;
elseif ($assignments_current_page > $total_assignments_pages && $total_assignments_pages > 0) $assignments_current_page = $total_assignments_pages;
elseif ($total_assignments == 0) $assignments_current_page = 1;
$assignments_offset = ($assignments_current_page - 1) * $assignments_records_per_page;

$student_scholarship_assignments = [];
$sql_fetch_assignments = "SELECT
                                ss.id, ss.assigned_date, ss.notes,
                                s.id AS student_id, s.first_name, s.last_name, s.registration_number, s.class_id,
                                sch.id AS scholarship_id, sch.scholarship_name, sch.type, sch.value,
                                p.full_name AS assigned_by_principal_name
                             FROM student_scholarships ss
                             JOIN students s ON ss.student_id = s.id
                             JOIN scholarships sch ON ss.scholarship_id = sch.id
                             LEFT JOIN principles p ON ss.assigned_by_admin_id = p.id -- Assuming 'assigned_by_admin_id' stores principal ID
                             WHERE " . $assignments_where_sql . "
                             ORDER BY ss.assigned_date DESC
                             LIMIT ? OFFSET ?";
if ($stmt = mysqli_prepare($link, $sql_fetch_assignments)) {
    $assignments_params_pagination = $assignments_params;
    $assignments_params_pagination[] = $assignments_records_per_page;
    $assignments_params_pagination[] = $assignments_offset;
    $assignments_types_pagination = $assignments_types . "ii";
    mysqli_stmt_bind_param($stmt, $assignments_types_pagination, ...$assignments_params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_scholarship_assignments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


// --- Fetch all Students for Assignment Dropdown ---
$all_students_for_assign_filter = []; // All students for assignment form and filter
$sql_all_students_for_assign = "SELECT s.id, s.first_name, s.last_name, s.registration_number, c.class_name, c.section_name FROM students s JOIN classes c ON s.class_id = c.id ORDER BY s.first_name ASC, s.last_name ASC";
if ($result = mysqli_query($link, $sql_all_students_for_assign)) {
    $all_students_for_assign_filter = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// --- Fetch all Scholarships for Assignment Dropdown ---
$all_scholarships_for_assign_filter = [];
$sql_all_scholarships_for_assign = "SELECT id, scholarship_name, type, value FROM scholarships WHERE is_active = 1 ORDER BY scholarship_name ASC";
if ($result = mysqli_query($link, $sql_all_scholarships_for_assign)) {
    $all_scholarships_for_assign_filter = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// --- Fetch all Classes for Assignment Filter Dropdown ---
$all_classes_for_assign_filter = [];
$sql_all_classes_for_assign_filter = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes_for_assign_filter)) {
    $all_classes_for_assign_filter = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
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
    <title>Manage Scholarships - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #E0FFFF, #AFEEEE, #B0E0E6, #ADD8E6); /* Cool, light blue gradient */
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
            color: #008B8B; /* Dark Cyan */
            margin-bottom: 30px;
            border-bottom: 2px solid #AFEEEE;
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
            background-color: #20B2AA; /* Light Sea Green */
            color: #fff;
            padding: 15px 20px;
            margin: -30px -30px 20px -30px; /* Adjust margin to fill parent box padding */
            border-bottom: 1px solid #1A968A;
            border-radius: 10px 10px 0 0;
            font-size: 1.6em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .section-header:hover {
            background-color: #1A968A;
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


        /* Forms (Scholarship Types, Assignments, Filters) */
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
        .form-group input[type="number"],
        .form-group input[type="date"],
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
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2320B2AA%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%2320B2AA%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
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
        .btn-form-submit { background-color: #20B2AA; color: #fff; }
        .btn-form-submit:hover { background-color: #1A968A; }
        .btn-form-cancel { background-color: #6c757d; color: #fff; }
        .btn-form-cancel:hover { background-color: #5a6268; }

        .filter-section {
            background-color: #f0ffff; /* Azure background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #b2e0e0;
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
        .filter-group label { color: #008B8B; }
        .filter-buttons { margin-top: 0; }
        .btn-filter { background-color: #48D1CC; color: #fff; }
        .btn-filter:hover { background-color: #20B2AA; }
        .btn-clear-filter { background-color: #6c757d; color: #fff; }
        .btn-clear-filter:hover { background-color: #5a6268; }
        .btn-print { background-color: #28a745; color: #fff; }
        .btn-print:hover { background-color: #218838; }


        /* Tables */
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

        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-Active { background-color: #d4edda; color: #155724; }
        .status-Inactive { background-color: #f8d7da; color: #721c24; }
        .status-Fixed { background-color: #b0e0e6; color: #004085; }
        .status-Percentage { background-color: #e6e6fa; color: #483d8b; }

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
            text-decoration: none; color: #20B2AA; background-color: #fff; transition: all 0.2s ease;
        }
        .pagination-controls a:hover { background-color: #e9ecef; border-color: #AFEEEE; }
        .pagination-controls .current-page, .pagination-controls .current-page:hover {
            background-color: #20B2AA; color: #fff; border-color: #20B2AA; cursor: default;
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
        <h2><i class="fas fa-award"></i> Manage Scholarships</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Scholarship Type Section -->
        <div class="section-box" id="add-edit-type-section">
            <div class="section-header" onclick="toggleSection('add-edit-type-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="false" aria-controls="add-edit-type-content">
                <h3 id="type-form-title"><i class="fas fa-plus-circle"></i> Add New Scholarship Type</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="add-edit-type-content" class="section-content collapsed">
                <form id="type-form" action="manage_scholarships.php" method="POST">
                    <input type="hidden" name="form_action" id="type-form-action" value="add_scholarship_type">
                    <input type="hidden" name="scholarship_id" id="scholarship-id" value="">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="scholarship_name">Scholarship Name:</label>
                            <input type="text" id="scholarship_name" name="scholarship_name" required placeholder="e.g., Merit Scholarship, Sports Grant">
                        </div>
                        <div class="form-group">
                            <label for="type">Type:</label>
                            <select id="type" name="type" required>
                                <option value="">-- Select Type --</option>
                                <option value="Fixed">Fixed Amount</option>
                                <option value="Percentage">Percentage of Fees</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="value">Value:</label>
                            <input type="number" step="0.01" id="value" name="value" required min="0" placeholder="e.g., 5000.00 (Fixed) or 25.00 (Percentage)">
                        </div>
                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" id="is_active" name="is_active" checked>
                                Is Active
                            </label>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="3" placeholder="Brief description of the scholarship"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-form-submit" id="type-submit-btn"><i class="fas fa-plus-circle"></i> Add Type</button>
                        <button type="button" class="btn-form-cancel" id="type-cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Scholarship Types Overview Section -->
        <div class="section-box printable-area" id="types-overview-section">
            <div class="section-header" onclick="toggleSection('types-overview-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="true" aria-controls="types-overview-content">
                <h3><i class="fas fa-list"></i> Scholarship Types Overview</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="types-overview-content" class="section-content">
                <div class="filter-section">
                    <form action="manage_scholarships.php" method="GET" style="display:contents;">
                        <input type="hidden" name="section" value="types">
                        <input type="hidden" name="assignments_page" value="<?php echo htmlspecialchars($assignments_current_page); ?>">
                        <input type="hidden" name="assignments_search" value="<?php echo htmlspecialchars($assignments_search_query); ?>">
                        <input type="hidden" name="assignments_student_id" value="<?php echo htmlspecialchars($assignments_filter_student_id); ?>">
                        <input type="hidden" name="assignments_scholarship_id" value="<?php echo htmlspecialchars($assignments_filter_scholarship_id); ?>">
                        <input type="hidden" name="assignments_class_id" value="<?php echo htmlspecialchars($assignments_filter_class_id); ?>">
                        
                        <div class="filter-group wide">
                            <label for="types_search_query"><i class="fas fa-search"></i> Search Types:</label>
                            <input type="text" id="types_search_query" name="types_search" value="<?php echo htmlspecialchars($types_search_query); ?>" placeholder="Name, Description, Type">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                            <?php if (!empty($types_search_query)): ?>
                                <a href="manage_scholarships.php?section=types&assignments_page=<?php echo htmlspecialchars($assignments_current_page); ?>" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                            <button type="button" class="btn-print" onclick="printTable('types-table-wrapper', 'Scholarship Types Report')"><i class="fas fa-print"></i> Print Types</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($scholarship_types)): ?>
                    <p class="no-results">No scholarship types found matching your criteria.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;" id="types-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Active</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scholarship_types as $type): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type['scholarship_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($type['description'] ?: 'N/A', 0, 100)); ?>
                                            <?php if (strlen($type['description'] ?: '') > 100): ?>
                                                ... <a href="#" onclick="alert('Full Description: <?php echo htmlspecialchars($type['description']); ?>'); return false;" class="text-muted">more</a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($type['type']); ?>">
                                                <?php echo htmlspecialchars($type['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo ($type['type'] == 'Fixed' ? '₹' : '') . htmlspecialchars($type['value']) . ($type['type'] == 'Percentage' ? '%' : ''); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo ($type['is_active'] ? 'Active' : 'Inactive'); ?>">
                                                <?php echo ($type['is_active'] ? 'Yes' : 'No'); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <button class="btn-action btn-edit" onclick="editScholarshipType(<?php echo htmlspecialchars(json_encode($type)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="javascript:void(0);" onclick="confirmDeleteScholarshipType(<?php echo $type['id']; ?>, '<?php echo htmlspecialchars($type['scholarship_name']); ?>')" class="btn-action btn-delete">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_scholarship_types > 0): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo ($types_offset + 1); ?> to <?php echo min($types_offset + $types_records_per_page, $total_scholarship_types); ?> of <?php echo $total_scholarship_types; ?> types
                            </div>
                            <div class="pagination-controls">
                                <?php
                                $types_base_url_params = array_filter([
                                    'section' => 'types',
                                    'types_search' => $types_search_query,
                                    'assignments_page' => $assignments_current_page, // Keep assignments page in URL
                                    'assignments_search' => $assignments_search_query,
                                    'assignments_student_id' => $assignments_filter_student_id,
                                    'assignments_scholarship_id' => $assignments_filter_scholarship_id,
                                    'assignments_class_id' => $assignments_filter_class_id
                                ]);
                                $types_base_url = "manage_scholarships.php?" . http_build_query($types_base_url_params);
                                ?>

                                <?php if ($types_current_page > 1): ?>
                                    <a href="<?php echo $types_base_url . '&types_page=' . ($types_current_page - 1); ?>">Previous</a>
                                <?php else: ?>
                                    <span class="disabled">Previous</span>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $types_current_page - 2);
                                $end_page = min($total_types_pages, $types_current_page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="' . $types_base_url . '&types_page=1">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span>...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++):
                                    if ($i == $types_current_page): ?>
                                        <span class="current-page"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo $types_base_url . '&types_page=' . $i; ?>"><?php echo $i; ?></a>
                                    <?php endif;
                                endfor;

                                if ($end_page < $total_types_pages) {
                                    if ($end_page < $total_types_pages - 1) {
                                        echo '<span>...</span>';
                                    }
                                    echo '<a href="' . $types_base_url . '&types_page=' . $total_types_pages . '">' . $total_types_pages . '</a>';
                                }
                                ?>

                                <?php if ($types_current_page < $total_types_pages): ?>
                                    <a href="<?php echo $types_base_url . '&types_page=' . ($types_current_page + 1); ?>">Next</a>
                                <?php else: ?>
                                    <span class="disabled">Next</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assign Scholarship to Student Section -->
        <div class="section-box" id="assign-scholarship-section">
            <div class="section-header" onclick="toggleSection('assign-scholarship-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="false" aria-controls="assign-scholarship-content">
                <h3 id="assign-form-title"><i class="fas fa-user-tag"></i> Assign Scholarship to Student</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="assign-scholarship-content" class="section-content collapsed">
                <form id="assign-form" action="manage_scholarships.php" method="POST">
                    <input type="hidden" name="form_action" value="assign_scholarship">
                    
                    <div class="form-grid">
                         <div class="form-group">
                            <label for="assign_filter_class_id">Student's Class:</label>
                            <select id="assign_filter_class_id" onchange="filterAssignStudentsByClass(this.value)">
                                <option value="">-- Select Class to filter Students --</option>
                                <?php foreach ($all_classes_for_assign_filter as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="student_id_assign">Student:</label>
                            <select id="student_id_assign" name="student_id_assign" required>
                                <option value="">-- Select Student --</option>
                                <!-- Populated by JS filterAssignStudentsByClass -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="scholarship_id_assign">Scholarship Type:</label>
                            <select id="scholarship_id_assign" name="scholarship_id_assign" required>
                                <option value="">-- Select Scholarship --</option>
                                <?php foreach ($all_scholarships_for_assign_filter as $scholarship): ?>
                                    <option value="<?php echo htmlspecialchars($scholarship['id']); ?>">
                                        <?php echo htmlspecialchars($scholarship['scholarship_name'] . ' (' . ($scholarship['type'] == 'Fixed' ? '₹' : '') . $scholarship['value'] . ($scholarship['type'] == 'Percentage' ? '%' : '') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="assigned_date">Assigned Date:</label>
                            <input type="date" id="assigned_date" name="assigned_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="notes_assign">Notes (Optional):</label>
                            <textarea id="notes_assign" name="notes_assign" rows="3" placeholder="Add any specific notes for this assignment"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-form-submit"><i class="fas fa-plus-circle"></i> Assign Scholarship</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Student Scholarship Assignments Overview Section -->
        <div class="section-box printable-area" id="assignments-overview-section">
            <div class="section-header" onclick="toggleSection('assignments-overview-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="true" aria-controls="assignments-overview-content">
                <h3><i class="fas fa-users-cog"></i> Student Scholarship Assignments</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="assignments-overview-content" class="section-content">
                <div class="filter-section">
                    <form action="manage_scholarships.php" method="GET" style="display:contents;">
                        <input type="hidden" name="section" value="assignments">
                        <input type="hidden" name="types_page" value="<?php echo htmlspecialchars($types_current_page); ?>">
                        <input type="hidden" name="types_search" value="<?php echo htmlspecialchars($types_search_query); ?>">

                        <div class="filter-group">
                            <label for="assignments_filter_class_id"><i class="fas fa-school"></i> Student Class:</label>
                            <select id="assignments_filter_class_id" name="assignments_class_id" onchange="filterAssignmentsStudentsByClass(this.value)">
                                <option value="">-- All Classes --</option>
                                <?php foreach ($all_classes_for_assign_filter as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                        <?php echo ($assignments_filter_class_id == $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="assignments_filter_student_id"><i class="fas fa-user-graduate"></i> Student:</label>
                            <select id="assignments_filter_student_id" name="assignments_student_id">
                                <option value="">-- All Students --</option>
                                <!-- Populated by JS filterAssignmentsStudentsByClass -->
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="assignments_filter_scholarship_id"><i class="fas fa-award"></i> Scholarship:</label>
                            <select id="assignments_filter_scholarship_id" name="assignments_scholarship_id">
                                <option value="">-- All Scholarships --</option>
                                <?php foreach ($all_scholarships_for_assign_filter as $scholarship): ?>
                                    <option value="<?php echo htmlspecialchars($scholarship['id']); ?>"
                                        <?php echo ($assignments_filter_scholarship_id == $scholarship['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($scholarship['scholarship_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group wide">
                            <label for="assignments_search_query"><i class="fas fa-search"></i> Search Assignments:</label>
                            <input type="text" id="assignments_search_query" name="assignments_search" value="<?php echo htmlspecialchars($assignments_search_query); ?>" placeholder="Student Name, Scholarship Name, Notes">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                            <?php if ($assignments_filter_class_id || $assignments_filter_student_id || $assignments_filter_scholarship_id || !empty($assignments_search_query)): ?>
                                <a href="manage_scholarships.php?section=assignments&types_page=<?php echo htmlspecialchars($types_current_page); ?>&types_search=<?php echo htmlspecialchars($types_search_query); ?>" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                            <button type="button" class="btn-print" onclick="printTable('assignments-table-wrapper', 'Student Scholarship Assignments Report')"><i class="fas fa-print"></i> Print Assignments</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($student_scholarship_assignments)): ?>
                    <p class="no-results">No scholarship assignments found matching your criteria.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;" id="assignments-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name (Reg No.)</th>
                                    <th>Scholarship Name</th>
                                    <th>Value</th>
                                    <th>Assigned Date</th>
                                    <th>Notes</th>
                                    <th>Assigned By</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($student_scholarship_assignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name'] . ' (' . $assignment['registration_number'] . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['scholarship_name']); ?></td>
                                        <td>
                                            <?php echo ($assignment['type'] == 'Fixed' ? '₹' : '') . htmlspecialchars($assignment['value']) . ($assignment['type'] == 'Percentage' ? '%' : ''); ?>
                                        </td>
                                        <td><?php echo date("M j, Y", strtotime($assignment['assigned_date'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($assignment['notes'] ?: 'N/A', 0, 100)); ?>
                                            <?php if (strlen($assignment['notes'] ?: '') > 100): ?>
                                                ... <a href="#" onclick="alert('Full Notes: <?php echo htmlspecialchars($assignment['notes']); ?>'); return false;" class="text-muted">more</a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment['assigned_by_principal_name'] ?: 'N/A'); ?></td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <a href="javascript:void(0);" onclick="confirmDeleteScholarshipAssignment(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['scholarship_name']); ?>', '<?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>')" class="btn-action btn-delete">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_assignments > 0): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo ($assignments_offset + 1); ?> to <?php echo min($assignments_offset + $assignments_records_per_page, $total_assignments); ?> of <?php echo $total_assignments; ?> assignments
                            </div>
                            <div class="pagination-controls">
                                <?php
                                $assignments_base_url_params = array_filter([
                                    'section' => 'assignments',
                                    'types_page' => $types_current_page, // Keep types page in URL
                                    'types_search' => $types_search_query,
                                    'assignments_student_id' => $assignments_filter_student_id,
                                    'assignments_scholarship_id' => $assignments_filter_scholarship_id,
                                    'assignments_class_id' => $assignments_filter_class_id,
                                    'assignments_search' => $assignments_search_query
                                ]);
                                $assignments_base_url = "manage_scholarships.php?" . http_build_query($assignments_base_url_params);
                                ?>

                                <?php if ($assignments_current_page > 1): ?>
                                    <a href="<?php echo $assignments_base_url . '&assignments_page=' . ($assignments_current_page - 1); ?>">Previous</a>
                                <?php else: ?>
                                    <span class="disabled">Previous</span>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $assignments_current_page - 2);
                                $end_page = min($total_assignments_pages, $assignments_current_page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="' . $assignments_base_url . '&assignments_page=1">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span>...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++):
                                    if ($i == $assignments_current_page): ?>
                                        <span class="current-page"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo $assignments_base_url . '&assignments_page=' . $i; ?>"><?php echo $i; ?></a>
                                    <?php endif;
                                endfor;

                                if ($end_page < $total_assignments_pages) {
                                    if ($end_page < $total_assignments_pages - 1) {
                                        echo '<span>...</span>';
                                    }
                                    echo '<a href="' . $assignments_base_url . '&assignments_page=' . $total_assignments_pages . '">' . $total_assignments_pages . '</a>';
                                }
                                ?>

                                <?php if ($assignments_current_page < $total_assignments_pages): ?>
                                    <a href="<?php echo $assignments_base_url . '&assignments_page=' . ($assignments_current_page + 1); ?>">Next</a>
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
    // Raw data for JavaScript filtering (students for assignment form & filter)
    const allStudentsForAssignFilterRaw = <?php echo json_encode($all_students_for_assign_filter); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize collapsed state for sections
        document.querySelectorAll('.section-box .section-header').forEach(header => {
            const contentId = header.querySelector('.section-toggle-btn').getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            const button = header.querySelector('.section-toggle-btn');
            
            // "Add New Scholarship Type" and "Assign Scholarship to Student" start collapsed
            // "Scholarship Types Overview" and "Student Scholarship Assignments Overview" start expanded
            const startsCollapsed = button.getAttribute('aria-expanded') === 'false';

            if (startsCollapsed) {
                content.classList.add('collapsed');
                button.querySelector('.fas').classList.remove('fa-chevron-down');
                button.querySelector('.fas').classList.add('fa-chevron-right');
            } else {
                content.style.maxHeight = content.scrollHeight + 'px';
                setTimeout(() => content.style.maxHeight = null, 500);
            }
        });

        // Initialize student dropdown filters
        const initialAssignClassId = document.getElementById('assign_filter_class_id') ? document.getElementById('assign_filter_class_id').value : '';
        const initialAssignmentsFilterClassId = document.getElementById('assignments_filter_class_id') ? document.getElementById('assignments_filter_class_id').value : '';
        const initialAssignmentsFilterStudentId = <?php echo $assignments_filter_student_id ?: 'null'; ?>;

        filterAssignStudentsByClass(initialAssignClassId, null); // Assignment form student dropdown
        filterAssignmentsStudentsByClass(initialAssignmentsFilterClassId, initialAssignmentsFilterStudentId); // Assignment overview filter dropdown
    });

    // Function to toggle the collapse state of a section
    window.toggleSection = function(contentId, button) {
        const content = document.getElementById(contentId);
        const icon = button.querySelector('.fas');

        if (content.classList.contains('collapsed')) {
            content.classList.remove('collapsed');
            content.style.maxHeight = content.scrollHeight + 'px';
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
            button.setAttribute('aria-expanded', 'true');
            setTimeout(() => { content.style.maxHeight = null; }, 500);
        } else {
            content.style.maxHeight = content.scrollHeight + 'px';
            void content.offsetHeight; 
            content.classList.add('collapsed');
            content.style.maxHeight = '0';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
            button.setAttribute('aria-expanded', 'false');
        }
    };

    // --- Scholarship Type Management JS ---
    function editScholarshipType(typeData) {
        // Ensure the form section is expanded
        const formContent = document.getElementById('add-edit-type-content');
        const formToggleButton = document.querySelector('#add-edit-type-section .section-toggle-btn');
        if (formContent.classList.contains('collapsed')) {
            toggleSection('add-edit-type-content', formToggleButton);
        }

        document.getElementById('type-form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Scholarship Type: ' + typeData.scholarship_name;
        document.getElementById('type-form-action').value = 'edit_scholarship_type';
        document.getElementById('scholarship-id').value = typeData.id;

        document.getElementById('scholarship_name').value = typeData.scholarship_name || '';
        document.getElementById('description').value = typeData.description || '';
        document.getElementById('type').value = typeData.type || '';
        document.getElementById('value').value = typeData.value || '0.00';
        document.getElementById('is_active').checked = typeData.is_active == 1;

        document.getElementById('type-submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Type';
        document.getElementById('type-cancel-btn').style.display = 'inline-flex';

        document.getElementById('add-edit-type-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('type-cancel-btn').addEventListener('click', function() {
        document.getElementById('type-form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Scholarship Type';
        document.getElementById('type-form-action').value = 'add_scholarship_type';
        document.getElementById('scholarship-id').value = '';
        document.getElementById('type-form').reset();
        document.getElementById('is_active').checked = true; // Reset checkbox
        
        document.getElementById('type-submit-btn').innerHTML = '<i class="fas fa-plus-circle"></i> Add Type';
        document.getElementById('type-cancel-btn').style.display = 'none';

        // Optionally collapse the section
        const formContent = document.getElementById('add-edit-type-content');
        const formToggleButton = document.querySelector('#add-edit-type-section .section-toggle-btn');
        if (!formContent.classList.contains('collapsed')) {
             toggleSection('add-edit-type-content', formToggleButton);
        }
    });

    function confirmDeleteScholarshipType(id, name) {
        if (confirm(`Are you sure you want to permanently delete the scholarship type "${name}"? This will also affect any associated student scholarship assignments. This action cannot be undone.`)) {
            window.location.href = `manage_scholarships.php?delete_type_id=${id}`;
        }
    }

    // --- Student Scholarship Assignment JS ---
    function filterAssignStudentsByClass(classId) {
        const studentSelect = document.getElementById('student_id_assign');
        studentSelect.innerHTML = '<option value="">-- Select Student --</option>'; // Reset

        let studentsToDisplay = allStudentsForAssignFilterRaw;
        if (classId) {
            studentsToDisplay = allStudentsForAssignFilterRaw.filter(student => student.class_id == classId);
        }
        
        studentsToDisplay.forEach(student => {
            const option = document.createElement('option');
            option.value = student.id;
            option.textContent = `${student.first_name} ${student.last_name} (Reg: ${student.registration_number}, Class: ${student.class_name}-${student.section_name})`;
            studentSelect.appendChild(option);
        });
    }

    function filterAssignmentsStudentsByClass(classId, selectedStudentId = null) {
        const studentSelect = document.getElementById('assignments_filter_student_id');
        studentSelect.innerHTML = '<option value="">-- All Students --</option>'; // Reset

        let studentsToDisplay = allStudentsForAssignFilterRaw;
        if (classId) {
            studentsToDisplay = allStudentsForAssignFilterRaw.filter(student => student.class_id == classId);
        }
        
        studentsToDisplay.forEach(student => {
            const option = document.createElement('option');
            option.value = student.id;
            option.textContent = `${student.first_name} ${student.last_name} (Reg: ${student.registration_number})`;
            if (selectedStudentId && student.id == selectedStudentId) {
                option.selected = true;
            }
            studentSelect.appendChild(option);
        });

        // Re-add selected student if no class filter is active but a student was pre-selected
        if (!classId && selectedStudentId) {
            const studentIsAlreadyInDropdown = Array.from(studentSelect.options).some(opt => opt.value == selectedStudentId);
            if (!studentIsAlreadyInDropdown) {
                const selectedStudent = allStudentsForAssignFilterRaw.find(student => student.id == selectedStudentId);
                if (selectedStudent) {
                    const option = document.createElement('option');
                    option.value = selectedStudent.id;
                    option.textContent = `${selectedStudent.first_name} ${selectedStudent.last_name} (Reg: ${selectedStudent.registration_number})`;
                    option.selected = true;
                    studentSelect.appendChild(option);
                }
            }
        }
    }

    function confirmDeleteScholarshipAssignment(id, scholarshipName, studentName) {
        if (confirm(`Are you sure you want to remove the "${scholarshipName}" scholarship from "${studentName}"? This action cannot be undone.`)) {
            window.location.href = `manage_scholarships.php?delete_assignment_id=${id}`;
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

        // Expand the content section if it's collapsed before printing
        const sectionContent = tableWrapper.closest('.section-content');
        const sectionHeader = sectionContent ? sectionContent.previousElementSibling : null;
        let isSectionCollapsed = false;

        if (sectionContent && sectionContent.classList.contains('collapsed')) {
            isSectionCollapsed = true;
            // Temporarily expand the section for printing
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
                .data-table th { background-color: #e0f2f7; color: #000; font-weight: 700; text-transform: uppercase; }
                .data-table tr:nth-child(even) { background-color: #f8fcff; }
                .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
                .status-Fixed { background-color: #b0e0e6; color: #004085; }
                .status-Percentage { background-color: #e6e6fa; color: #483d8b; }
                .status-Active { background-color: #d4edda; color: #155724; }
                .status-Inactive { background-color: #f8d7da; color: #721c24; }
                .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group { display: none; }
                .fas { margin-right: 3px; }
                .text-muted { color: #6c757d; }

                /* For specific table structures */
                .printable-area .genre-group-wrapper .section-header { background-color: #f0f0f0; color: #333; border-bottom: 1px solid #ddd; }
                .printable-area .genre-group-wrapper .section-header h3 { font-size: 14pt; }
                .printable-area .genre-group-wrapper .section-toggle-btn { display: none; }
                .printable-area .genre-group-wrapper .section-content { max-height: none !important; overflow: visible !important; padding: 10px 0 !important; }

            `);
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(`<h2 style="text-align: center;">${printTitle}</h2>`);
            printWindow.document.write(tableWrapper.innerHTML);
            printWindow.document.write('</body></html>');
            
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();

            // Restore original collapsed state after printing
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