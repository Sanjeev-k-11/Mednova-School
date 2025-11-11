<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"]; // Primary key ID of the logged-in principal
$principal_full_name = $_SESSION["full_name"]; // Full name of the logged-in principal
$principal_role = $_SESSION["role"]; // Role will be 'Principle'

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Password Hashing Function ---
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// --- Pagination Configuration ---
$records_per_page = 10; // Number of students to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- Process Form Submissions (Add, Edit) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if the request is for Add/Edit student (not block/unblock modal)
    if (isset($_POST['form_action']) && ($_POST['form_action'] == 'add_student' || $_POST['form_action'] == 'edit_student')) {
        $action = $_POST['form_action'];

        // Collect and sanitize form data
        $registration_number = trim($_POST['registration_number']);
        $first_name = trim($_POST['first_name']);
        $middle_name = trim($_POST['middle_name']);
        $last_name = trim($_POST['last_name']);
        $dob = empty(trim($_POST['dob'])) ? NULL : trim($_POST['dob']);
        $gender = trim($_POST['gender']);
        $blood_group = trim($_POST['blood_group']);
        $image_url = trim($_POST['image_url']);
        $phone_number = trim($_POST['phone_number']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $pincode = trim($_POST['pincode']);
        $district = trim($_POST['district']);
        $state = trim($_POST['state']);
        $father_name = trim($_POST['father_name']);
        $mother_name = trim($_POST['mother_name']);
        $parent_phone_number = trim($_POST['parent_phone_number']);
        $father_occupation = trim($_POST['father_occupation']);
        $class_id = (int)$_POST['class_id'];
        $roll_number = trim($_POST['roll_number']);
        $previous_school = trim($_POST['previous_school']);
        $previous_class = trim($_POST['previous_class']);
        $admission_date = trim($_POST['admission_date']);
        $van_service_taken = isset($_POST['van_service_taken']) ? 1 : 0;
        $van_id = $van_service_taken == 1 && $_POST['van_id'] !== '' ? (int)$_POST['van_id'] : NULL;

        // Basic validation
        if (empty($registration_number) || empty($first_name) || empty($last_name) || empty($email) || empty($class_id) || empty($admission_date)) {
            set_session_message("Required fields (Reg. No., First Name, Last Name, Email, Class, Admission Date) cannot be empty.", "danger");
            header("location: manage_students.php?page={$current_page}");
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_session_message("Invalid email format.", "danger");
            header("location: manage_students.php?page={$current_page}");
            exit;
        }

        if ($action == 'add_student') {
            $password = trim($_POST['password']);
            if (empty($password)) {
                set_session_message("Password is required for new student.", "danger");
                header("location: manage_students.php?page={$current_page}");
                exit;
            }
            $hashed_password = hash_password($password);

            // Check for duplicate registration_number or email
            $check_sql = "SELECT id FROM students WHERE registration_number = ? OR email = ?";
            if ($stmt = mysqli_prepare($link, $check_sql)) {
                mysqli_stmt_bind_param($stmt, "ss", $registration_number, $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    set_session_message("A student with this registration number or email already exists.", "danger");
                    mysqli_stmt_close($stmt);
                    header("location: manage_students.php?page={$current_page}");
                    exit;
                }
                mysqli_stmt_close($stmt);
            }

            $sql = "INSERT INTO students (registration_number, first_name, middle_name, last_name, dob, gender, blood_group, image_url, phone_number, email, address, pincode, district, state, father_name, mother_name, parent_phone_number, father_occupation, class_id, roll_number, previous_school, previous_class, admission_date, van_service_taken, van_id, password, status, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssssssssssssssssisssiisi",
                    $registration_number, $first_name, $middle_name, $last_name, $dob, $gender, $blood_group, $image_url, $phone_number, $email, $address, $pincode, $district, $state, $father_name, $mother_name, $parent_phone_number, $father_occupation, $class_id, $roll_number, $previous_school, $previous_class, $admission_date, $van_service_taken, $van_id, $hashed_password, $principal_id);

                if (mysqli_stmt_execute($stmt)) {
                    set_session_message("Student added successfully.", "success");
                } else {
                    set_session_message("Error adding student: " . mysqli_error($link), "danger");
                }
                mysqli_stmt_close($stmt);
            }

        } elseif ($action == 'edit_student') {
            $student_id = (int)$_POST['student_id'];
            if (empty($student_id)) {
                set_session_message("Invalid student ID for editing.", "danger");
                header("location: manage_students.php?page={$current_page}");
                exit;
            }

            // Check for duplicate registration_number or email, excluding the current student
            $check_sql = "SELECT id FROM students WHERE (registration_number = ? OR email = ?) AND id != ?";
            if ($stmt = mysqli_prepare($link, $check_sql)) {
                mysqli_stmt_bind_param($stmt, "ssi", $registration_number, $email, $student_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    set_session_message("Another student with this registration number or email already exists.", "danger");
                    mysqli_stmt_close($stmt);
                    header("location: manage_students.php?page={$current_page}");
                    exit;
                }
                mysqli_stmt_close($stmt);
            }

            $password_field = trim($_POST['password']);
            $password_update = "";
            if (!empty($password_field)) {
                $hashed_password = hash_password($password_field);
                $password_update = ", password = ?";
            }

            $sql = "UPDATE students SET registration_number = ?, first_name = ?, middle_name = ?, last_name = ?, dob = ?, gender = ?, blood_group = ?, image_url = ?, phone_number = ?, email = ?, address = ?, pincode = ?, district = ?, state = ?, father_name = ?, mother_name = ?, parent_phone_number = ?, father_occupation = ?, class_id = ?, roll_number = ?, previous_school = ?, previous_class = ?, admission_date = ?, van_service_taken = ?, van_id = ? {$password_update} WHERE id = ?";

            if ($stmt = mysqli_prepare($link, $sql)) {
                $types = "sssssssssssssssssissssiis"; // Base types for fields before password
                $params = [
                    $registration_number, $first_name, $middle_name, $last_name, $dob, $gender, $blood_group, $image_url, $phone_number, $email, $address, $pincode, $district, $state, $father_name, $mother_name, $parent_phone_number, $father_occupation, $class_id, $roll_number, $previous_school, $previous_class, $admission_date, $van_service_taken, $van_id
                ];

                if (!empty($password_field)) {
                    $types .= "s";
                    $params[] = $hashed_password;
                }
                $types .= "i";
                $params[] = $student_id;

                mysqli_stmt_bind_param($stmt, $types, ...$params);

                if (mysqli_stmt_execute($stmt)) {
                    set_session_message("Student updated successfully.", "success");
                } else {
                    set_session_message("Error updating student: " . mysqli_error($link), "danger");
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    // Block/Unblock actions are handled in a separate POST block
    header("location: manage_students.php?page={$current_page}");
    exit;
}

// --- Process Block/Unblock Request (from modal) ---
if (isset($_POST['action']) && ($_POST['action'] == 'block_student' || $_POST['action'] == 'unblock_student')) {
    $student_id = (int)$_POST['student_id'];
    $reason = trim($_POST['reason']);
    $action_type = $_POST['action']; // 'block_student' or 'unblock_student'

    if (empty($student_id)) {
        set_session_message("Invalid student ID for action.", "danger");
        header("location: manage_students.php?page={$current_page}");
        exit;
    }

    if (empty($reason)) {
        set_session_message("Reason is required for " . ($action_type == 'block_student' ? "blocking" : "unblocking") . ".", "danger");
        header("location: manage_students.php?page={$current_page}");
        exit;
    }

    if ($action_type == 'block_student') {
        $sql = "UPDATE students SET status = 'Blocked', block_reason = ?, blocked_by_admin_id = ?, blocked_at = NOW(), unblock_reason = NULL, unblocked_by_admin_id = NULL, unblocked_at = NULL WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sii", $reason, $principal_id, $student_id); // principal_id acts as admin_id
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Student blocked successfully.", "success");
            } else {
                set_session_message("Error blocking student: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action_type == 'unblock_student') {
        $sql = "UPDATE students SET status = 'Active', unblock_reason = ?, unblocked_by_admin_id = ?, unblocked_at = NOW(), block_reason = NULL, blocked_by_admin_id = NULL, blocked_at = NULL WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sii", $reason, $principal_id, $student_id); // principal_id acts as admin_id
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Student unblocked successfully.", "success");
            } else {
                set_session_message("Error unblocking student: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    }
    header("location: manage_students.php?page={$current_page}");
    exit;
}


// --- Process Delete Request ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid student ID for deletion.", "danger");
        header("location: manage_students.php?page={$current_page}");
        exit;
    }

    $sql = "DELETE FROM students WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                 set_session_message("Student deleted successfully. Note: Related records (e.g., attendance, fees, marks) might have been affected based on foreign key rules.", "success");
            } else {
                 set_session_message("Student not found or already deleted.", "danger");
            }
        } else {
            if (mysqli_errno($link) == 1451) { // Foreign key constraint error
                set_session_message("Cannot delete student. This student has active records (e.g., fees, attendance, library books). Please ensure all related records are settled/removed first.", "danger");
            } else {
                set_session_message("Error deleting student: " . mysqli_error($link), "danger");
            }
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_students.php?page={$current_page}");
    exit;
}


// --- Fetch Total Records for Pagination ---
$total_records_sql = "SELECT COUNT(s.id) FROM students s";
if ($result = mysqli_query($link, $total_records_sql)) {
    $total_records = mysqli_fetch_row($result)[0];
    mysqli_free_result($result);
} else {
    $total_records = 0;
    $message = "Error counting students: " . mysqli_error($link);
    $message_type = "danger";
}
$total_pages = ceil($total_records / $records_per_page);

// Ensure current_page is within bounds
if ($current_page < 1) {
    $current_page = 1;
} elseif ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $records_per_page; // Recalculate offset for adjusted page
} elseif ($total_records == 0) {
    $current_page = 1;
    $offset = 0;
}


// --- Fetch Students for Display (with Pagination) ---
$students = [];
$sql_fetch_students = "SELECT
                        s.*,
                        c.class_name, c.section_name,
                        v.van_number, v.driver_name
                       FROM students s
                       JOIN classes c ON s.class_id = c.id
                       LEFT JOIN vans v ON s.van_id = v.id
                       ORDER BY s.first_name ASC, s.last_name ASC
                       LIMIT ? OFFSET ?";
if ($stmt = mysqli_prepare($link, $sql_fetch_students)) {
    mysqli_stmt_bind_param($stmt, "ii", $records_per_page, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching students: " . mysqli_error($link);
    $message_type = "danger";
}

// --- Fetch all Classes for Dropdown ---
$classes = [];
$sql_fetch_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_fetch_classes)) {
    $classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching classes for dropdown: " . mysqli_error($link);
    $message_type = "danger";
}

// --- Fetch all Vans for Dropdown ---
$vans = [];
$sql_fetch_vans = "SELECT id, van_number, driver_name FROM vans ORDER BY van_number ASC";
if ($result = mysqli_query($link, $sql_fetch_vans)) {
    $vans = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching vans for dropdown: " . mysqli_error($link);
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
    <title>Manage Students - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #FFD700, #FFA500, #FF6347, #FF4500); /* Golden to Orange gradient */
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
            color: #d17a00; /* Darker golden-orange */
            margin-bottom: 30px;
            border-bottom: 2px solid #ffcc66;
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

        /* Form Section */
        .form-section, .list-section { /* Combined styling for main sections */
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
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .section-header h3 {
            margin: 0;
            color: #495057;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-toggle-btn {
            background: none;
            border: none;
            font-size: 1.5em;
            color: #6c757d;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .section-toggle-btn.rotated {
            transform: rotate(90deg);
        }
        .section-content.collapsed {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .section-content:not(.collapsed) {
            max-height: 2000px; /* Arbitrary large value */
            transition: max-height 0.5s ease-in;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 10px; /* Adjust spacing in grid */
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #FF8C00; /* Darker orange focus */
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.2);
            outline: none;
        }
        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23FF8C00%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23FF8C00%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 14px;
            padding-right: 40px;
        }
        .form-group input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2); /* Slightly larger checkbox */
        }
        .form-group.checkbox-group label {
            display: flex;
            align-items: center;
        }
        .form-actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background-color: #FF8C00; /* Darker orange */
            color: #fff;
        }
        .btn-primary:hover {
            background-color: #FF6347; /* Tomato orange */
            transform: translateY(-2px);
        }
        .btn-cancel {
            background-color: #6c757d;
            color: #fff;
        }
        .btn-cancel:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }


        /* Student List Table */
        .student-list-table {
            width: 100%;
            border-collapse: separate; /* For rounded corners */
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden; /* Ensures rounded corners are visible */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .student-list-table th, .student-list-table td {
            border-bottom: 1px solid #dee2e6;
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }
        .student-list-table th {
            background-color: #f1f3f5;
            color: #343a40;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .student-list-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .student-list-table tr:hover {
            background-color: #e9eff5;
        }
        .action-buttons-group {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn-action { /* Generic class for table action buttons */
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
        .btn-edit {
            background-color: #FFC107; /* Amber */
            color: #333;
            border-color: #FFC107;
        }
        .btn-edit:hover {
            background-color: #e0a800;
            border-color: #e0a800;
        }
        .btn-delete {
            background-color: #dc3545; /* Red */
            color: #fff;
            border-color: #dc3545;
        }
        .btn-delete:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .btn-block {
            background-color: #6c757d; /* Grey */
            color: #fff;
            border-color: #6c757d;
        }
        .btn-block:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .btn-unblock {
            background-color: #28a745; /* Green */
            color: #fff;
            border-color: #28a745;
        }
        .btn-unblock:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .text-center {
            text-align: center;
        }
        .student-image-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            vertical-align: middle;
            margin-right: 10px;
            border: 2px solid #ddd;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-blocked {
            background-color: #f8d7da;
            color: #721c24;
        }
        .notes-text {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
            display: block;
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 100; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 30px;
            border: 1px solid #888;
            width: 80%; /* Could be more responsive */
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        .modal-header {
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h4 {
            margin: 0;
            color: #333;
            font-size: 1.5em;
        }
        .close-btn {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: #000;
            text-decoration: none;
        }
        .modal-body .form-group label {
            width: auto; /* Override fixed label width for modal */
        }
        .modal-body textarea {
            min-height: 100px;
            resize: vertical;
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }
        .btn-modal-submit {
            background-color: #FF8C00;
            color: white;
        }
        .btn-modal-submit:hover {
            background-color: #FF6347;
        }
        .btn-modal-submit-unblock { /* A distinct color for unblock button */
            background-color: #28a745;
            color: white;
        }
        .btn-modal-submit-unblock:hover {
            background-color: #218838;
        }
        .btn-modal-cancel {
            background-color: #6c757d;
            color: white;
        }
        .btn-modal-cancel:hover {
            background-color: #5a6268;
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

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .student-list-table th, .student-list-table td {
                padding: 10px;
                font-size: 0.9rem;
            }
            .student-list-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap; /* Prevent wrapping of table content */
            }
            .pagination-container {
                justify-content: center;
            }
            .pagination-controls {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-user-graduate"></i> Manage Students</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Student Form -->
        <div class="form-section" id="add-edit-section">
            <div class="section-header">
                <h3 id="form-title"><i class="fas fa-user-plus"></i> Add New Student</h3>
                <button class="section-toggle-btn" onclick="toggleSection('add-edit-content', this)" aria-expanded="false" aria-controls="add-edit-content">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="add-edit-content" class="section-content collapsed">
                <form id="student-form" action="manage_students.php" method="POST">
                    <input type="hidden" name="form_action" id="form-action" value="add_student">
                    <input type="hidden" name="student_id" id="student-id" value="">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="registration_number">Registration Number:</label>
                            <input type="text" id="registration_number" name="registration_number" required placeholder="Unique Registration ID">
                        </div>
                        <div class="form-group">
                            <label for="first_name">First Name:</label>
                            <input type="text" id="first_name" name="first_name" required placeholder="e.g., John">
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name:</label>
                            <input type="text" id="middle_name" name="middle_name" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name:</label>
                            <input type="text" id="last_name" name="last_name" required placeholder="e.g., Doe">
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required placeholder="student@school.com">
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" placeholder="Required for new student">
                        </div>
                        <div class="form-group">
                            <label for="phone_number">Phone Number:</label>
                            <input type="text" id="phone_number" name="phone_number" placeholder="e.g., +91 9876543210">
                        </div>
                        <div class="form-group">
                            <label for="dob">Date of Birth:</label>
                            <input type="date" id="dob" name="dob">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender:</label>
                            <select id="gender" name="gender">
                                <option value="">-- Select Gender --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="blood_group">Blood Group:</label>
                            <input type="text" id="blood_group" name="blood_group" placeholder="e.g., A+">
                        </div>
                        <div class="form-group">
                            <label for="class_id">Class:</label>
                            <select id="class_id" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="roll_number">Roll Number:</label>
                            <input type="text" id="roll_number" name="roll_number" placeholder="e.g., 01, A-101">
                        </div>
                        <div class="form-group">
                            <label for="admission_date">Admission Date:</label>
                            <input type="date" id="admission_date" name="admission_date" required>
                        </div>
                        <div class="form-group">
                            <label for="image_url">Image URL:</label>
                            <input type="text" id="image_url" name="image_url" placeholder="URL for profile image (e.g., Cloudinary)">
                        </div>
                        <div class="form-group">
                            <label for="address">Address:</label>
                            <textarea id="address" name="address" rows="2" placeholder="Student's full address"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="pincode">Pincode:</label>
                            <input type="text" id="pincode" name="pincode" placeholder="e.g., 123456">
                        </div>
                        <div class="form-group">
                            <label for="district">District:</label>
                            <input type="text" id="district" name="district" placeholder="e.g., Central District">
                        </div>
                        <div class="form-group">
                            <label for="state">State:</label>
                            <input type="text" id="state" name="state" placeholder="e.g., New State">
                        </div>
                        <div class="form-group">
                            <label for="father_name">Father's Name:</label>
                            <input type="text" id="father_name" name="father_name" required placeholder="Father's Full Name">
                        </div>
                        <div class="form-group">
                            <label for="mother_name">Mother's Name:</label>
                            <input type="text" id="mother_name" name="mother_name" required placeholder="Mother's Full Name">
                        </div>
                        <div class="form-group">
                            <label for="parent_phone_number">Parent's Phone:</label>
                            <input type="text" id="parent_phone_number" name="parent_phone_number" required placeholder="Parent's contact number">
                        </div>
                        <div class="form-group">
                            <label for="father_occupation">Father's Occupation:</label>
                            <input type="text" id="father_occupation" name="father_occupation" placeholder="e.g., Engineer, Business Owner">
                        </div>
                        <div class="form-group">
                            <label for="previous_school">Previous School:</label>
                            <input type="text" id="previous_school" name="previous_school" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label for="previous_class">Previous Class:</label>
                            <input type="text" id="previous_class" name="previous_class" placeholder="Optional">
                        </div>
                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" id="van_service_taken" name="van_service_taken" onchange="toggleVanDropdown()">
                                Van Service Taken
                            </label>
                        </div>
                        <div class="form-group" id="van-id-group" style="display:none;">
                            <label for="van_id">Assign Van:</label>
                            <select id="van_id" name="van_id">
                                <option value="">-- Select Van --</option>
                                <?php foreach ($vans as $van): ?>
                                    <option value="<?php echo htmlspecialchars($van['id']); ?>">
                                        <?php echo htmlspecialchars($van['van_number'] . ' (' . ($van['driver_name'] ?: 'No Driver') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="submit-btn"><i class="fas fa-save"></i> Add Student</button>
                        <button type="button" class="btn btn-cancel" id="cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Student List Table -->
        <div class="list-section" id="student-list-section">
            <div class="section-header">
                <h3><i class="fas fa-list"></i> Student Overview</h3>
                <button class="section-toggle-btn" onclick="toggleSection('student-list-content', this)" aria-expanded="true" aria-controls="student-list-content">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="student-list-content" class="section-content">
                <?php if (empty($students)): ?>
                    <p class="text-center text-muted">No students found. Add a new student above.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;"> <!-- Makes table scrollable on small screens -->
                        <table class="student-list-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Reg. No.</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Van Service</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($student['image_url'] ?: '../assets/images/default_profile.png'); ?>" alt="Student Image" class="student-image-sm">
                                        </td>
                                        <td><?php echo htmlspecialchars($student['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['phone_number'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['van_number'] ? $student['van_number'] . ' (' . ($student['driver_name'] ?: 'No Driver') . ')' : 'No Service'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo ($student['status'] == 'Blocked' ? 'status-blocked' : 'status-active'); ?>">
                                                <?php echo htmlspecialchars($student['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($student['status'] == 'Blocked' && !empty($student['block_reason'])): ?>
                                                <span class="notes-text">Blocked: <?php echo htmlspecialchars($student['block_reason']); ?> (<?php echo date('M j, Y', strtotime($student['blocked_at'])); ?>)</span>
                                            <?php elseif ($student['status'] == 'Active' && !empty($student['unblock_reason'])): ?>
                                                <span class="notes-text">Unblocked: <?php echo htmlspecialchars($student['unblock_reason']); ?> (<?php echo date('M j, Y', strtotime($student['unblocked_at'])); ?>)</span>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <button class="btn-action btn-edit" onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <?php if ($student['status'] == 'Active'): ?>
                                                    <button class="btn-action btn-block" onclick="showReasonModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>', 'block')">
                                                        <i class="fas fa-user-lock"></i> Block
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-action btn-unblock" onclick="showReasonModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>', 'unblock')">
                                                        <i class="fas fa-user-check"></i> Unblock
                                                    </button>
                                                <?php endif; ?>
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" class="btn-action btn-delete">
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
                                <?php if ($current_page > 1): ?>
                                    <a href="?page=<?php echo ($current_page - 1); ?>">Previous</a>
                                <?php else: ?>
                                    <span class="disabled">Previous</span>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="?page=1">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span>...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++):
                                    if ($i == $current_page): ?>
                                        <span class="current-page"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    <?php endif;
                                endfor;

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span>...</span>';
                                    }
                                    echo '<a href="?page=' . $total_pages . '">' . $total_pages . '</a>';
                                }
                                ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?page=<?php echo ($current_page + 1); ?>">Next</a>
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

<!-- Block/Unblock Reason Modal -->
<div id="reasonModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="modal-title"></h4>
      <span class="close-btn" onclick="closeReasonModal()">&times;</span>
    </div>
    <form id="reasonForm" action="manage_students.php" method="POST">
      <input type="hidden" name="action" id="modal-action">
      <input type="hidden" name="student_id" id="modal-student-id">
      <div class="modal-body">
        <div class="form-group">
          <label for="reason-text">Reason:</label>
          <textarea id="reason-text" name="reason" rows="4" required placeholder="Enter reason for this action"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-modal-cancel" onclick="closeReasonModal()">Cancel</button>
        <button type="submit" class="btn btn-modal-submit" id="modal-submit-btn">Submit</button>
      </div>
    </form>
  </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        toggleVanDropdown(); // Set initial state of van dropdown on page load

        // Initialize collapsed state based on aria-expanded attribute
        document.querySelectorAll('.section-toggle-btn').forEach(button => {
            const contentId = button.getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            if (content) {
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                if (!isExpanded) {
                    content.classList.add('collapsed');
                    button.querySelector('.fas').classList.remove('fa-chevron-down');
                    button.querySelector('.fas').classList.add('fa-chevron-right');
                } else {
                     content.classList.remove('collapsed');
                     button.querySelector('.fas').classList.remove('fa-chevron-right');
                     button.querySelector('.fas').classList.add('fa-chevron-down');
                }
            }
        });
    });

    function toggleSection(contentId, button) {
        const content = document.getElementById(contentId);
        const icon = button.querySelector('.fas');

        if (content.classList.contains('collapsed')) {
            content.classList.remove('collapsed');
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
            button.setAttribute('aria-expanded', 'true');
        } else {
            content.classList.add('collapsed');
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
            button.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleVanDropdown() {
        const vanServiceCheckbox = document.getElementById('van_service_taken');
        const vanIdGroup = document.getElementById('van-id-group');
        if (vanServiceCheckbox.checked) {
            vanIdGroup.style.display = 'block';
        } else {
            vanIdGroup.style.display = 'none';
            document.getElementById('van_id').value = ''; // Clear selection when hidden
        }
    }

    // Function to populate the form for editing
    function editStudent(studentData) {
        // First, ensure the form section is expanded
        const formContent = document.getElementById('add-edit-content');
        const formToggleButton = document.querySelector('#add-edit-section .section-toggle-btn');
        if (formContent.classList.contains('collapsed')) {
            toggleSection('add-edit-content', formToggleButton);
        }

        document.getElementById('form-title').innerHTML = '<i class="fas fa-user-edit"></i> Edit Student: ' + studentData.first_name + ' ' + studentData.last_name;
        document.getElementById('form-action').value = 'edit_student';
        document.getElementById('student-id').value = studentData.id;

        // Populate fields
        document.getElementById('registration_number').value = studentData.registration_number || '';
        document.getElementById('first_name').value = studentData.first_name || '';
        document.getElementById('middle_name').value = studentData.middle_name || '';
        document.getElementById('last_name').value = studentData.last_name || '';
        document.getElementById('email').value = studentData.email || '';
        document.getElementById('password').value = ''; // Password field intentionally left empty
        document.getElementById('password').placeholder = 'Leave empty to keep current password';

        document.getElementById('phone_number').value = studentData.phone_number || '';
        document.getElementById('dob').value = studentData.dob && studentData.dob !== '0000-00-00' ? studentData.dob : '';
        document.getElementById('gender').value = studentData.gender || '';
        document.getElementById('blood_group').value = studentData.blood_group || '';
        document.getElementById('class_id').value = studentData.class_id || '';
        document.getElementById('roll_number').value = studentData.roll_number || '';
        document.getElementById('admission_date').value = studentData.admission_date && studentData.admission_date !== '0000-00-00' ? studentData.admission_date : '';
        document.getElementById('image_url').value = studentData.image_url || '';
        document.getElementById('address').value = studentData.address || '';
        document.getElementById('pincode').value = studentData.pincode || '';
        document.getElementById('district').value = studentData.district || '';
        document.getElementById('state').value = studentData.state || '';
        document.getElementById('father_name').value = studentData.father_name || '';
        document.getElementById('mother_name').value = studentData.mother_name || '';
        document.getElementById('parent_phone_number').value = studentData.parent_phone_number || '';
        document.getElementById('father_occupation').value = studentData.father_occupation || '';
        document.getElementById('previous_school').value = studentData.previous_school || '';
        document.getElementById('previous_class').value = studentData.previous_class || '';

        const vanServiceCheckbox = document.getElementById('van_service_taken');
        vanServiceCheckbox.checked = studentData.van_service_taken == 1;
        toggleVanDropdown(); // Update visibility of van_id dropdown

        document.getElementById('van_id').value = studentData.van_id || '';

        document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Student';
        document.getElementById('cancel-btn').style.display = 'inline-flex'; // Show cancel button

        document.getElementById('form-title').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Function to reset the form to 'Add New Student' mode
    document.getElementById('cancel-btn').addEventListener('click', function() {
        document.getElementById('form-title').innerHTML = '<i class="fas fa-user-plus"></i> Add New Student';
        document.getElementById('form-action').value = 'add_student';
        document.getElementById('student-id').value = '';

        document.getElementById('student-form').reset(); // Resets all form fields

        document.getElementById('password').placeholder = 'Required for new student'; // Reset placeholder
        document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Add Student';
        document.getElementById('cancel-btn').style.display = 'none';

        toggleVanDropdown(); // Ensure van dropdown state is correct for 'add' mode (initially hidden)
    });

    // Modal functions (already present from previous version)
    const reasonModal = document.getElementById('reasonModal');
    const modalTitle = document.getElementById('modal-title');
    const modalAction = document.getElementById('modal-action');
    const modalStudentId = document.getElementById('modal-student-id');
    const reasonText = document.getElementById('reason-text');
    const modalSubmitBtn = document.getElementById('modal-submit-btn');

    function showReasonModal(studentId, studentName, action) {
        reasonText.value = ''; // Clear previous reason
        modalStudentId.value = studentId;

        if (action === 'block') {
            modalTitle.innerHTML = `<i class="fas fa-user-lock"></i> Block Student: ${studentName}`;
            modalAction.value = 'block_student';
            reasonText.placeholder = 'e.g., Non-payment of fees, prolonged absence, indiscipline.';
            modalSubmitBtn.innerHTML = 'Block Student';
            modalSubmitBtn.classList.remove('btn-modal-submit-unblock'); // Ensure correct class
            modalSubmitBtn.classList.add('btn-modal-submit'); // Default submit button style

        } else if (action === 'unblock') {
            modalTitle.innerHTML = `<i class="fas fa-user-check"></i> Unblock Student: ${studentName}`;
            modalAction.value = 'unblock_student';
            reasonText.placeholder = 'e.g., Fees paid, issues resolved, returned to school.';
            modalSubmitBtn.innerHTML = 'Unblock Student';
            modalSubmitBtn.classList.remove('btn-modal-submit'); // Remove default
            modalSubmitBtn.classList.add('btn-modal-submit-unblock'); // Use unblock specific style
        }
        reasonModal.style.display = 'flex'; // Show modal
    }

    function closeReasonModal() {
        reasonModal.style.display = 'none'; // Hide modal
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == reasonModal) {
            closeReasonModal();
        }
    }

    // Function for delete confirmation
    function confirmDelete(id, name) {
        if (confirm(`Are you sure you want to permanently delete student "${name}"? This action cannot be undone and may affect many related records (e.g., attendance, fees, marks, etc.).`)) {
            window.location.href = `manage_students.php?delete_id=${id}&page=<?php echo $current_page; ?>`;
        }
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>