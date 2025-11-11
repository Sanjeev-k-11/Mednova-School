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

// --- Filter Parameters (for Programs) ---
$programs_search_query = isset($_GET['programs_search']) ? trim($_GET['programs_search']) : '';
$programs_filter_status = isset($_GET['programs_status']) && $_GET['programs_status'] !== '' ? trim($_GET['programs_status']) : null;
$programs_filter_teacher_id = isset($_GET['programs_teacher_id']) && is_numeric($_GET['programs_teacher_id']) ? (int)$_GET['programs_teacher_id'] : null;

// --- Pagination Configuration (for Programs) ---
$programs_records_per_page = 10;
$programs_current_page = isset($_GET['programs_page']) && is_numeric($_GET['programs_page']) ? (int)$_GET['programs_page'] : 1;


// --- Filter Parameters (for Participants in Modal/Separate section if implemented) ---
$participants_search_query = isset($_GET['participants_search']) ? trim($_GET['participants_search']) : '';
$participants_filter_program_id = isset($_GET['participants_program_id']) && is_numeric($_GET['participants_program_id']) ? (int)$_GET['participants_program_id'] : null;
$participants_filter_student_id = isset($_GET['participants_student_id']) && is_numeric($_GET['participants_student_id']) ? (int)$_GET['participants_student_id'] : null;


// --- Fetch Dropdown Data (for Forms and Filters) ---
$all_teachers = [];
$sql_all_teachers = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_all_teachers)) {
    $all_teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_students_raw = []; // For dynamic JS filtering in participant assignment
$sql_all_students_raw = "SELECT id, first_name, last_name, registration_number, class_id FROM students ORDER BY first_name ASC, last_name ASC";
if ($result = mysqli_query($link, $sql_all_students_raw)) {
    $all_students_raw = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_classes = []; // For filtering students in participant assignment modal
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$program_statuses = ['Active', 'Upcoming', 'Completed', 'Cancelled'];


// --- Process Program Form Submissions (Add/Edit) ---
if (isset($_POST['form_action']) && ($_POST['form_action'] == 'add_program' || $_POST['form_action'] == 'edit_program')) {
    $action = $_POST['form_action'];

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $teacher_in_charge_id = empty(trim($_POST['teacher_in_charge_id'])) ? NULL : (int)$_POST['teacher_in_charge_id'];
    $program_date = trim($_POST['program_date']);
    $location = trim($_POST['location']);
    $image_url = trim($_POST['image_url']);
    $registration_deadline = empty(trim($_POST['registration_deadline'])) ? NULL : trim($_POST['registration_deadline']);
    $participant_limit = empty(trim($_POST['participant_limit'])) ? NULL : (int)$_POST['participant_limit'];
    $status = trim($_POST['status']);

    if (empty($name) || empty($program_date) || empty($status)) {
        set_session_message("Program Name, Program Date, and Status are required.", "danger");
        header("location: manage_cultural_programs.php");
        exit;
    }

    if ($action == 'add_program') {
        // Check for duplicate program name for same date (optional)
        $check_sql = "SELECT id FROM cultural_programs WHERE name = ? AND program_date = ?";
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "ss", $name, $program_date);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                set_session_message("A program with this name is already scheduled for this date.", "danger");
                mysqli_stmt_close($stmt);
                header("location: manage_cultural_programs.php");
                exit;
            }
            mysqli_stmt_close($stmt);
        }

        $sql = "INSERT INTO cultural_programs (name, description, teacher_in_charge_id, program_date, location, image_url, registration_deadline, participant_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssissssii", $name, $description, $teacher_in_charge_id, $program_date, $location, $image_url, $registration_deadline, $participant_limit, $status);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Cultural Program added successfully.", "success");
            } else {
                set_session_message("Error adding program: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'edit_program') {
        $program_id = (int)$_POST['program_id'];
        if (empty($program_id)) {
            set_session_message("Invalid Program ID for editing.", "danger");
            header("location: manage_cultural_programs.php");
            exit;
        }

        // Check for duplicate program name for same date, excluding current program (optional)
        $check_sql = "SELECT id FROM cultural_programs WHERE name = ? AND program_date = ? AND id != ?";
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "ssi", $name, $program_date, $program_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                set_session_message("Another program with this name is already scheduled for this date.", "danger");
                mysqli_stmt_close($stmt);
                header("location: manage_cultural_programs.php");
                exit;
            }
            mysqli_stmt_close($stmt);
        }

        $sql = "UPDATE cultural_programs SET name = ?, description = ?, teacher_in_charge_id = ?, program_date = ?, location = ?, image_url = ?, registration_deadline = ?, participant_limit = ?, status = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssissssiii", $name, $description, $teacher_in_charge_id, $program_date, $location, $image_url, $registration_deadline, $participant_limit, $status, $program_id);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Cultural Program updated successfully.", "success");
            } else {
                set_session_message("Error updating program: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    }
    header("location: manage_cultural_programs.php?programs_page={$programs_current_page}");
    exit;
}

// --- Process Program Deletion ---
if (isset($_GET['delete_program_id'])) {
    $delete_id = (int)$_GET['delete_program_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid Program ID for deletion.", "danger");
        header("location: manage_cultural_programs.php");
        exit;
    }

    // Check for associated participants before deleting
    $check_participants_sql = "SELECT COUNT(id) FROM program_participants WHERE program_id = ?";
    if ($stmt_check = mysqli_prepare($link, $check_participants_sql)) {
        mysqli_stmt_bind_param($stmt_check, "i", $delete_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_bind_result($stmt_check, $participant_count);
        mysqli_stmt_fetch($stmt_check);
        mysqli_stmt_close($stmt_check);

        if ($participant_count > 0) {
            set_session_message("Cannot delete program. There are {$participant_count} students currently registered. Please remove participants first.", "danger");
            header("location: manage_cultural_programs.php?programs_page={$programs_current_page}");
            exit;
        }
    }

    $sql = "DELETE FROM cultural_programs WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Cultural Program deleted successfully.", "success");
            } else {
                set_session_message("Program not found or already deleted.", "danger");
            }
        } else {
            set_session_message("Error deleting program: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_cultural_programs.php?programs_page={$programs_current_page}");
    exit;
}

// --- Process Participant Assignment ---
if (isset($_POST['participant_action']) && $_POST['participant_action'] == 'add_participant') {
    $program_id = (int)$_POST['program_id_assign'];
    $student_id = (int)$_POST['student_id_assign'];
    $registration_date = date('Y-m-d H:i:s'); // Set current timestamp

    if (empty($program_id) || empty($student_id)) {
        set_session_message("Program and Student are required to add a participant.", "danger");
        header("location: manage_cultural_programs.php?view_program_id={$program_id}");
        exit;
    }

    // Check for duplicate participation
    $check_sql = "SELECT id FROM program_participants WHERE program_id = ? AND student_id = ?";
    if ($stmt = mysqli_prepare($link, $check_sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $program_id, $student_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            set_session_message("This student is already registered for this program.", "danger");
            mysqli_stmt_close($stmt);
            header("location: manage_cultural_programs.php?view_program_id={$program_id}");
            exit;
        }
        mysqli_stmt_close($stmt);
    }

    // Check participant limit if set
    $program_limit_sql = "SELECT participant_limit FROM cultural_programs WHERE id = ?";
    if ($stmt_limit = mysqli_prepare($link, $program_limit_sql)) {
        mysqli_stmt_bind_param($stmt_limit, "i", $program_id);
        mysqli_stmt_execute($stmt_limit);
        $result_limit = mysqli_stmt_get_result($stmt_limit);
        $program_details = mysqli_fetch_assoc($result_limit);
        mysqli_stmt_close($stmt_limit);

        if ($program_details && $program_details['participant_limit'] !== NULL) {
            $current_participants_sql = "SELECT COUNT(id) FROM program_participants WHERE program_id = ?";
            if ($stmt_current_participants = mysqli_prepare($link, $current_participants_sql)) {
                mysqli_stmt_bind_param($stmt_current_participants, "i", $program_id);
                mysqli_stmt_execute($stmt_current_participants);
                $result_current_participants = mysqli_stmt_get_result($stmt_current_participants);
                $current_participant_count = mysqli_fetch_row($result_current_participants)[0];
                mysqli_stmt_close($stmt_current_participants);

                if ($current_participant_count >= $program_details['participant_limit']) {
                    set_session_message("Program has reached its maximum participant limit of {$program_details['participant_limit']}.", "danger");
                    header("location: manage_cultural_programs.php?view_program_id={$program_id}");
                    exit;
                }
            }
        }
    }


    $sql = "INSERT INTO program_participants (program_id, student_id, registration_date) VALUES (?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iis", $program_id, $student_id, $registration_date);
        if (mysqli_stmt_execute($stmt)) {
            set_session_message("Student added to program successfully.", "success");
        } else {
            set_session_message("Error adding student to program: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_cultural_programs.php?view_program_id={$program_id}");
    exit;
}

// --- Process Participant Deletion ---
if (isset($_GET['delete_participant_id']) && isset($_GET['program_id_for_participant'])) {
    $delete_id = (int)$_GET['delete_participant_id'];
    $program_id_redirect = (int)$_GET['program_id_for_participant'];

    if (empty($delete_id)) {
        set_session_message("Invalid Participant ID for deletion.", "danger");
        header("location: manage_cultural_programs.php?view_program_id={$program_id_redirect}");
        exit;
    }

    $sql = "DELETE FROM program_participants WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Program participant removed successfully.", "success");
            } else {
                set_session_message("Program participant not found or already removed.", "danger");
            }
        } else {
            set_session_message("Error removing program participant: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_cultural_programs.php?view_program_id={$program_id_redirect}");
    exit;
}


// --- Build WHERE clause for total programs and paginated data ---
$programs_where_clauses = ["1=1"];
$programs_params = [];
$programs_types = "";

if ($programs_filter_status) {
    $programs_where_clauses[] = "cp.status = ?";
    $programs_params[] = $programs_filter_status;
    $programs_types .= "s";
}
if ($programs_filter_teacher_id) {
    $programs_where_clauses[] = "cp.teacher_in_charge_id = ?";
    $programs_params[] = $programs_filter_teacher_id;
    $programs_types .= "i";
}
if (!empty($programs_search_query)) {
    $programs_search_term = "%" . $programs_search_query . "%";
    $programs_where_clauses[] = "(cp.name LIKE ? OR cp.description LIKE ? OR cp.location LIKE ? OR t.full_name LIKE ?)";
    $programs_params[] = $programs_search_term;
    $programs_params[] = $programs_search_term;
    $programs_params[] = $programs_search_term;
    $programs_params[] = $programs_search_term;
    $programs_types .= "ssss";
}

$programs_where_sql = implode(" AND ", $programs_where_clauses);


// --- Fetch Total Programs for Pagination ---
$total_programs = 0;
$total_programs_sql = "SELECT COUNT(cp.id)
                      FROM cultural_programs cp
                      LEFT JOIN teachers t ON cp.teacher_in_charge_id = t.id
                      WHERE " . $programs_where_sql;

if ($stmt = mysqli_prepare($link, $total_programs_sql)) {
    if (!empty($programs_params)) {
        mysqli_stmt_bind_param($stmt, $programs_types, ...$programs_params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_programs = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
}
$total_programs_pages = ceil($total_programs / $programs_records_per_page);

if ($programs_current_page < 1) $programs_current_page = 1;
elseif ($programs_current_page > $total_programs_pages && $total_programs_pages > 0) $programs_current_page = $total_programs_pages;
elseif ($total_programs == 0) $programs_current_page = 1;
$programs_offset = ($programs_current_page - 1) * $programs_records_per_page;


// --- Fetch Programs Data (with filters and pagination) ---
$cultural_programs = [];
$sql_fetch_programs = "SELECT
                        cp.id, cp.name, cp.description, cp.image_url, cp.program_date, cp.location,
                        cp.registration_deadline, cp.participant_limit, cp.status, cp.created_at,
                        t.full_name AS teacher_in_charge_name, t.id AS teacher_in_charge_id,
                        (SELECT COUNT(pp.id) FROM program_participants pp WHERE pp.program_id = cp.id) AS current_participants_count
                    FROM cultural_programs cp
                    LEFT JOIN teachers t ON cp.teacher_in_charge_id = t.id
                    WHERE " . $programs_where_sql . "
                    ORDER BY cp.program_date DESC
                    LIMIT ? OFFSET ?";

$programs_params_pagination = $programs_params;
$programs_params_pagination[] = $programs_records_per_page;
$programs_params_pagination[] = $programs_offset;
$programs_types_pagination = $programs_types . "ii";

if ($stmt = mysqli_prepare($link, $sql_fetch_programs)) {
    mysqli_stmt_bind_param($stmt, $programs_types_pagination, ...$programs_params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $cultural_programs = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
    <title>Manage Cultural Programs - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #F0F8FF, #E6E6FA, #FFE0F0, #FFDAB9); /* Light blues and rosy shades */
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
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }


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
            background-color: #DA70D6; /* Orchid */
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
        .section-header:hover { background-color: #C71585; }
        .section-header h3 { margin: 0; font-size: 1em; display: flex; align-items: center; gap: 10px; }
        .section-toggle-btn { background: none; border: none; font-size: 1em; color: #fff; cursor: pointer; transition: transform 0.3s ease; }
        .section-toggle-btn.rotated { transform: rotate(90deg); }
        .section-content { max-height: 2000px; overflow: hidden; transition: max-height 0.5s ease-in-out; }
        .section-content.collapsed { max-height: 0; margin-top: 0; padding-bottom: 0; margin-bottom: 0; }


        /* Forms (Program Add/Edit, Participant Add, Filters) */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group input[type="date"], .form-group textarea, .form-group select {
            width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.95rem; box-sizing: border-box;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group select {
            appearance: none; -webkit-appearance: none; -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23DA70D6%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23DA70D6%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat; background-position: right 10px center; background-size: 14px; padding-right: 30px;
        }
        .form-actions { margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end; }
        .btn-form-submit, .btn-form-cancel, .btn-filter, .btn-clear-filter, .btn-print {
            padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 0.95rem; font-weight: 600;
            transition: background-color 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; border: none;
        }
        .btn-form-submit { background-color: #DA70D6; color: #fff; }
        .btn-form-submit:hover { background-color: #C71585; }
        .btn-form-cancel { background-color: #6c757d; color: #fff; }
        .btn-form-cancel:hover { background-color: #5a6268; }

        .filter-section {
            background-color: #fff0f5; padding: 20px; border-radius: 8px; margin-bottom: 30px;
            border: 1px solid #ffe4e1; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group.wide { flex: 2; min-width: 250px; }
        .filter-group label { color: #C71585; }
        .filter-buttons { margin-top: 0; }
        .btn-filter { background-color: #FF69B4; color: #fff; }
        .btn-filter:hover { background-color: #DA70D6; }
        .btn-clear-filter { background-color: #6c757d; color: #fff; }
        .btn-clear-filter:hover { background-color: #5a6268; }
        .btn-print { background-color: #20B2AA; color: #fff; }
        .btn-print:hover { background-color: #1A968A; }


        /* Tables */
        .data-table {
            width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px;
            border: 1px solid #cfd8dc; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .data-table th, .data-table td { border-bottom: 1px solid #e0e0e0; padding: 15px; text-align: left; vertical-align: middle; }
        .data-table th { background-color: #ffe0f0; color: #C71585; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; }
        .data-table tr:nth-child(even) { background-color: #fdf0f5; }
        .data-table tr:hover { background-color: #fae6f0; }

        .action-buttons-group { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .btn-action {
            padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; border: 1px solid transparent;
        }
        .btn-edit { background-color: #FFC107; color: #333; border-color: #FFC107; }
        .btn-edit:hover { background-color: #e0a800; border-color: #e0a800; }
        .btn-delete { background-color: #dc3545; color: #fff; border-color: #dc3545; }
        .btn-delete:hover { background-color: #c82333; border-color: #bd2130; }
        .btn-view-participants { background-color: #9370DB; color: #fff; border-color: #9370DB; }
        .btn-view-participants:hover { background-color: #8A2BE2; }


        .status-badge { padding: 5px 10px; border-radius: 5px; font-size: 0.8em; font-weight: 600; white-space: nowrap; }
        .status-Active { background-color: #d4edda; color: #155724; }
        .status-Upcoming { background-color: #fff3cd; color: #856404; }
        .status-Completed { background-color: #cce5ff; color: #004085; }
        .status-Cancelled { background-color: #f8d7da; color: #721c24; }

        .text-center { text-align: center; }
        .text-muted { color: #6c757d; }
        .no-results { text-align: center; padding: 50px; font-size: 1.2em; color: #6c757d; }

        /* Pagination Styles */
        .pagination-container {
            display: flex; justify-content: space-between; align-items: center; margin-top: 25px;
            padding: 10px 0; border-top: 1px solid #eee; flex-wrap: wrap; gap: 10px;
        }
        .pagination-info { color: #555; font-size: 0.95em; font-weight: 500; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-controls a, .pagination-controls span {
            display: block; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;
            text-decoration: none; color: #DA70D6; background-color: #fff; transition: all 0.2s ease;
        }
        .pagination-controls a:hover { background-color: #e9ecef; border-color: #ffe0f0; }
        .pagination-controls .current-page, .pagination-controls .current-page:hover {
            background-color: #DA70D6; color: #fff; border-color: #DA70D6; cursor: default;
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
        .modal-body .program-details { border-bottom: 1px dashed #ddd; padding-bottom: 15px; margin-bottom: 15px; }
        .modal-body .participants-list-wrapper { margin-top: 20px; }
        .modal-body .participant-item { 
            background-color: #f8f0ff; border: 1px solid #e6d9ff; border-radius: 8px; padding: 10px 15px; margin-bottom: 8px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-body .participant-info { display: flex; flex-direction: column; }
        .modal-body .participant-name { font-weight: 600; color: #8A2BE2; font-size: 1em; }
        .modal-body .participant-details { font-size: 0.85em; color: #555; }
        .modal-body .participant-actions .btn-action { margin-left: 8px; }
        
        .modal-body .form-assign-participant { 
            border-top: 1px dashed #ddd; padding-top: 15px; margin-top: 20px; 
            display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;
            background-color: #f9f9f9; padding: 15px; border-radius: 8px;
        }
        .modal-body .form-assign-participant .form-group { flex: 1; min-width: 150px; margin-bottom: 0; }
        .modal-body .form-assign-participant .form-actions { margin-top: 0; padding-top: 0; border-top: none; justify-content: flex-start; }
        .modal-body .form-assign-participant select, .modal-body .form-assign-participant input { font-size: 0.9em; }

        .modal-footer { margin-top: 20px; text-align: right; }
        .btn-modal-close { background-color: #6c757d; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }


        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; font-size: 10pt; padding: 10mm; }
            .printable-area h2, .printable-area h3 { color: #000; border-bottom: 1px solid #ccc; font-size: 16pt; margin-bottom: 15px; }
            .printable-area .data-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .printable-area .data-table th, .printable-area .data-table td { border: 1px solid #eee; padding: 8px 10px; }
            .printable-area .data-table th { background-color: #ffe0f0; color: #000; }
            .printable-area .status-badge { padding: 3px 6px; font-size: 0.7em; }
            .printable-area .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group, .modal { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
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
            .modal-content { width: 95%; }
            .modal-body .form-assign-participant { flex-direction: column; align-items: stretch; }
            .modal-body .form-assign-participant .form-group { min-width: unset; width: 100%; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-masks-theater"></i> Manage Cultural Programs</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Program Section -->
        <div class="section-box" id="add-edit-program-section">
            <div class="section-header" onclick="toggleSection('add-edit-program-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="false" aria-controls="add-edit-program-content">
                <h3 id="program-form-title"><i class="fas fa-plus-circle"></i> Add New Cultural Program</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="add-edit-program-content" class="section-content collapsed">
                <form id="program-form" action="manage_cultural_programs.php" method="POST">
                    <input type="hidden" name="form_action" id="program-form-action" value="add_program">
                    <input type="hidden" name="program_id" id="program-id" value="">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Program Name:</label>
                            <input type="text" id="name" name="name" required placeholder="e.g., Annual Drama Fest, School Orchestra">
                        </div>
                        <div class="form-group">
                            <label for="teacher_in_charge_id">Teacher In Charge:</label>
                            <select id="teacher_in_charge_id" name="teacher_in_charge_id">
                                <option value="">-- Select Teacher (Optional) --</option>
                                <?php foreach ($all_teachers as $teacher): ?>
                                    <option value="<?php echo htmlspecialchars($teacher['id']); ?>">
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="program_date">Program Date:</label>
                            <input type="date" id="program_date" name="program_date" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Location:</label>
                            <input type="text" id="location" name="location" placeholder="e.g., School Auditorium, Main Hall">
                        </div>
                        <div class="form-group">
                            <label for="registration_deadline">Registration Deadline (Optional):</label>
                            <input type="date" id="registration_deadline" name="registration_deadline">
                        </div>
                        <div class="form-group">
                            <label for="participant_limit">Participant Limit (Optional):</label>
                            <input type="number" id="participant_limit" name="participant_limit" min="1" placeholder="e.g., 50">
                        </div>
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Upcoming">Upcoming</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="image_url">Image URL (Optional):</label>
                            <input type="text" id="image_url" name="image_url" placeholder="URL for program banner/image">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="3" placeholder="Brief description of the program"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-form-submit" id="program-submit-btn"><i class="fas fa-plus-circle"></i> Add Program</button>
                        <button type="button" class="btn-form-cancel" id="program-cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cultural Programs Overview Section -->
        <div class="section-box printable-area" id="programs-overview-section">
            <div class="section-header" onclick="toggleSection('programs-overview-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="true" aria-controls="programs-overview-content">
                <h3><i class="fas fa-list"></i> Cultural Programs Overview</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="programs-overview-content" class="section-content">
                <div class="filter-section">
                    <form action="manage_cultural_programs.php" method="GET" style="display:contents;">
                        <input type="hidden" name="section" value="overview">
                        
                        <div class="filter-group">
                            <label for="programs_filter_status"><i class="fas fa-toggle-on"></i> Status:</label>
                            <select class="h-12 rounded-xl border" id="programs_filter_status" name="programs_status">
                                <option value="">-- All Statuses --</option>
                                <?php foreach ($program_statuses as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>"
                                        <?php echo ($programs_filter_status == $status) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="programs_filter_teacher_id"><i class="fas fa-chalkboard-teacher"></i> In Charge:</label>
                            <select class="h-12 rounded-xl border" id="programs_filter_teacher_id" name="programs_teacher_id">
                                <option value="">-- All Teachers --</option>
                                <?php foreach ($all_teachers as $teacher): ?>
                                    <option value="<?php echo htmlspecialchars($teacher['id']); ?>"
                                        <?php echo ($programs_filter_teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group wide">
                            <label for="programs_search_query"><i class="fas fa-search"></i> Search Programs:</label>
                            <input class="h-12 rounded-xl border" type="text" id="programs_search_query" name="programs_search" value="<?php echo htmlspecialchars($programs_search_query); ?>" placeholder="Program Name, Description, Location">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                            <?php if ($programs_filter_status || $programs_filter_teacher_id || !empty($programs_search_query)): ?>
                                <a href="manage_cultural_programs.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                            <button type="button" class="btn-print" onclick="printTable('programs-table-wrapper', 'Cultural Programs Report')"><i class="fas fa-print"></i> Print Programs</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($cultural_programs)): ?>
                    <p class="no-results">No cultural programs found matching your criteria.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;" id="programs-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Program Name</th>
                                    <th>Description</th>
                                    <th>In Charge</th>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Reg. Deadline</th>
                                    <th>Limit</th>
                                    <th>Participants</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cultural_programs as $program): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($program['name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($program['description'] ?: 'N/A', 0, 100)); ?>
                                            <?php if (strlen($program['description'] ?: '') > 100): ?>
                                                ... <a href="#" onclick="alert('Full Description: <?php echo htmlspecialchars($program['description']); ?>'); return false;" class="text-muted">more</a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($program['teacher_in_charge_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo date("M j, Y", strtotime($program['program_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($program['location'] ?: 'N/A'); ?></td>
                                        <td><?php echo ($program['registration_deadline'] && $program['registration_deadline'] !== '0000-00-00') ? date("M j, Y", strtotime($program['registration_deadline'])) : 'N/A'; ?></td>
                                        <td><?php echo htmlspecialchars($program['participant_limit'] ?: 'No Limit'); ?></td>
                                        <td><?php echo htmlspecialchars($program['current_participants_count']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($program['status'])); ?>">
                                                <?php echo htmlspecialchars($program['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <button class="btn-action btn-view-participants" onclick="viewProgramParticipants(<?php echo htmlspecialchars(json_encode($program)); ?>)">
                                                    <i class="fas fa-users"></i> View Parts.
                                                </button>
                                                <button class="btn-action btn-edit" onclick="editProgram(<?php echo htmlspecialchars(json_encode($program)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="javascript:void(0);" onclick="confirmDeleteProgram(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['name']); ?>', <?php echo $program['current_participants_count']; ?>)" class="btn-action btn-delete">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_programs > 0): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo ($programs_offset + 1); ?> to <?php echo min($programs_offset + $programs_records_per_page, $total_programs); ?> of <?php echo $total_programs; ?> programs
                            </div>
                            <div class="pagination-controls">
                                <?php
                                $programs_base_url_params = array_filter([
                                    'programs_search' => $programs_search_query,
                                    'programs_status' => $programs_filter_status,
                                    'programs_teacher_id' => $programs_filter_teacher_id
                                ]);
                                $programs_base_url = "manage_cultural_programs.php?" . http_build_query($programs_base_url_params);
                                ?>

                                <?php if ($programs_current_page > 1): ?>
                                    <a href="<?php echo $programs_base_url . '&programs_page=' . ($programs_current_page - 1); ?>">Previous</a>
                                <?php else: ?>
                                    <span class="disabled">Previous</span>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $programs_current_page - 2);
                                $end_page = min($total_programs_pages, $programs_current_page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="' . $programs_base_url . '&programs_page=1">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span>...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++):
                                    if ($i == $programs_current_page): ?>
                                        <span class="current-page"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo $programs_base_url . '&programs_page=' . $i; ?>"><?php echo $i; ?></a>
                                    <?php endif;
                                endfor;

                                if ($end_page < $total_programs_pages) {
                                    if ($end_page < $total_programs_pages - 1) {
                                        echo '<span>...</span>';
                                    }
                                    echo '<a href="' . $programs_base_url . '&programs_page=' . $total_programs_pages . '">' . $total_programs_pages . '</a>';
                                }
                                ?>

                                <?php if ($programs_current_page < $total_programs_pages): ?>
                                    <a href="<?php echo $programs_base_url . '&programs_page=' . ($programs_current_page + 1); ?>">Next</a>
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

<!-- View Participants Modal -->
<div id="participantsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="participants-modal-title">Participants of: <span id="modal-program-name"></span></h4>
      <span class="close-btn" onclick="closeParticipantsModal()">&times;</span>
    </div>
    <div class="modal-body">
      <div class="program-details">
        <p><strong>Total Participants:</strong> <span id="modal-total-participants"></span></p>
        <p><strong>Participant Limit:</strong> <span id="modal-participant-limit"></span></p>
        <p id="modal-participant-status" class="text-muted"></p>
        <hr>
      </div>

      <div class="participants-list-wrapper">
        <h4>Current Participants:</h4>
        <div id="modal-participants-content">
          <!-- Participants will be loaded here via JS -->
          <p class="text-muted text-center">No participants yet.</p>
        </div>
      </div>

      <h4 style="margin-top: 30px;"><i class="fas fa-user-plus"></i> Enroll New Participant</h4>
      <form id="add-participant-form" class="form-assign-participant" action="manage_cultural_programs.php" method="POST">
        <input type="hidden" name="participant_action" value="add_participant">
        <input type="hidden" name="program_id_assign" id="participant-form-program-id">

        <div class="form-group">
            <label for="assign_student_class_id">Student's Class:</label>
            <select id="assign_student_class_id" onchange="filterAssignStudentsByClass(this.value)">
                <option value="">-- Select Class to Filter --</option>
                <?php foreach ($all_classes as $class): ?>
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
                <!-- Populated by JS -->
            </select>
        </div>
        <div class="form-actions" style="grid-column: 1 / -1;">
            <button type="submit" class="btn-form-submit"><i class="fas fa-plus"></i> Enroll Student</button>
        </div>
      </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn-modal-close" onclick="closeParticipantsModal()">Close</button>
    </div>
  </div>
</div>


<script>
    // Raw student data for JavaScript filtering (for participant assignment)
    const allStudentsRaw = <?php echo json_encode($all_students_raw); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize collapsed state for sections
        document.querySelectorAll('.section-box .section-header').forEach(header => {
            const contentId = header.querySelector('.section-toggle-btn').getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            const button = header.querySelector('.section-toggle-btn');
            
            // "Add New Cultural Program" section starts collapsed
            if (button.getAttribute('aria-expanded') === 'false') {
                content.classList.add('collapsed');
                button.querySelector('.fas').classList.remove('fa-chevron-down');
                button.querySelector('.fas').classList.add('fa-chevron-right');
            } else {
                // "Cultural Programs Overview" starts expanded
                content.style.maxHeight = content.scrollHeight + 'px';
                setTimeout(() => content.style.maxHeight = null, 500);
            }
        });

        // Initialize today's date for Program Date input
        document.getElementById('program_date').value = new Date().toISOString().slice(0, 10);
        document.getElementById('registration_deadline').value = new Date().toISOString().slice(0, 10);
    });

    // Function to toggle the collapse state of a section
    window.toggleSection = function(contentId, button) {
        const content = document.getElementById(contentId);
        const icon = button.querySelector('.fas');

        if (content.classList.contains('collapsed')) {
            content.classList.remove('collapsed');
            content.style.maxHeight = content.scrollHeight + 'px'; // Set to scrollHeight for animation
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

    // --- Program Management JS ---
    function editProgram(programData) {
        // Ensure the form section is expanded
        const formContent = document.getElementById('add-edit-program-content');
        const formToggleButton = document.querySelector('#add-edit-program-section .section-toggle-btn');
        if (formContent.classList.contains('collapsed')) {
            toggleSection('add-edit-program-content', formToggleButton);
        }

        document.getElementById('program-form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Program: ' + programData.name;
        document.getElementById('program-form-action').value = 'edit_program';
        document.getElementById('program-id').value = programData.id;

        document.getElementById('name').value = programData.name || '';
        document.getElementById('description').value = programData.description || '';
        document.getElementById('teacher_in_charge_id').value = programData.teacher_in_charge_id || '';
        document.getElementById('program_date').value = programData.program_date && programData.program_date !== '0000-00-00' ? programData.program_date : '';
        document.getElementById('location').value = programData.location || '';
        document.getElementById('image_url').value = programData.image_url || '';
        document.getElementById('registration_deadline').value = programData.registration_deadline && programData.registration_deadline !== '0000-00-00' ? programData.registration_deadline : '';
        document.getElementById('participant_limit').value = programData.participant_limit || ''; // Can be null
        document.getElementById('status').value = programData.status || 'Active';
        
        document.getElementById('program-submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Program';
        document.getElementById('program-cancel-btn').style.display = 'inline-flex';

        document.getElementById('add-edit-program-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('program-cancel-btn').addEventListener('click', function() {
        document.getElementById('program-form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Cultural Program';
        document.getElementById('program-form-action').value = 'add_program';
        document.getElementById('program-id').value = '';
        document.getElementById('program-form').reset();
        
        document.getElementById('program-submit-btn').innerHTML = '<i class="fas fa-plus-circle"></i> Add Program';
        document.getElementById('program-cancel-btn').style.display = 'none';

        document.getElementById('program_date').value = new Date().toISOString().slice(0, 10); // Reset date
        document.getElementById('registration_deadline').value = new Date().toISOString().slice(0, 10); // Reset date


        // Optionally collapse the section
        const formContent = document.getElementById('add-edit-program-content');
        const formToggleButton = document.querySelector('#add-edit-program-section .section-toggle-btn');
        if (!formContent.classList.contains('collapsed')) {
             toggleSection('add-edit-program-content', formToggleButton);
        }
    });

    function confirmDeleteProgram(id, name, participantCount) {
        if (participantCount > 0) {
            alert(`Cannot delete program "${name}". There are ${participantCount} students currently registered. Please remove participants first.`);
            return false;
        }
        if (confirm(`Are you sure you want to permanently delete the program "${name}"? This action cannot be undone.`)) {
            window.location.href = `manage_cultural_programs.php?delete_program_id=${id}`;
        }
    }

    // --- Program Participants Modal JS ---
    const participantsModal = document.getElementById('participantsModal');
    const modalProgramName = document.getElementById('modal-program-name');
    const modalTotalParticipants = document.getElementById('modal-total-participants');
    const modalParticipantLimit = document.getElementById('modal-participant-limit');
    const modalParticipantStatus = document.getElementById('modal-participant-status');
    const modalParticipantsContent = document.getElementById('modal-participants-content');
    const participantFormProgramId = document.getElementById('participant-form-program-id');
    const assignStudentClassIdSelect = document.getElementById('assign_student_class_id');
    const studentIdAssignSelect = document.getElementById('student_id_assign');
    let currentProgramIdForParticipants = null; // For refreshing participants list

    async function viewProgramParticipants(programData) {
        currentProgramIdForParticipants = programData.id;
        modalProgramName.textContent = programData.name;
        modalTotalParticipants.textContent = programData.current_participants_count;
        modalParticipantLimit.textContent = programData.participant_limit || 'No Limit';

        if (programData.participant_limit && programData.current_participants_count >= programData.participant_limit) {
            modalParticipantStatus.textContent = 'Program is at full capacity.';
            modalParticipantStatus.style.color = 'red';
        } else {
            modalParticipantStatus.textContent = '';
            modalParticipantStatus.style.color = '';
        }

        participantFormProgramId.value = programData.id;
        document.getElementById('add-participant-form').reset(); // Clear form
        assignStudentClassIdSelect.value = ''; // Reset class filter
        filterAssignStudentsByClass(''); // Populate student dropdown with all students

        // Fetch and display participants for this program
        modalParticipantsContent.innerHTML = '<p class="text-muted text-center">Loading participants...</p>';
        try {
            const response = await fetch(`fetch_program_participants.php?program_id=${programData.id}`);            const participants = await response.json();
            modalParticipantsContent.innerHTML = ''; // Clear loading message

            if (participants.length === 0) {
                modalParticipantsContent.innerHTML = '<p class="text-muted text-center">No participants yet.</p>';
            } else {
                participants.forEach(participant => {
                    const participantItem = document.createElement('div');
                    participantItem.className = 'participant-item';
                    participantItem.innerHTML = `
                        <div class="participant-info">
                            <div class="participant-name">${participant.first_name} ${participant.last_name} (Reg: ${participant.registration_number})</div>
                            <div class="participant-details">Class: ${participant.class_name}-${participant.section_name} | Registered: ${formatDate(participant.registration_date)}</div>
                        </div>
                        <div class="participant-actions">
                            <a href="javascript:void(0);" onclick="confirmRemoveParticipant(${participant.participant_id}, '${participant.first_name} ${participant.last_name}', '${programData.name}')" class="btn-action btn-delete">
                                <i class="fas fa-user-minus"></i> Remove
                            </a>
                        </div>
                    `;
                    modalParticipantsContent.appendChild(participantItem);
                });
            }
        } catch (error) {
            console.error('Error fetching participants:', error);
            modalParticipantsContent.innerHTML = '<p class="text-muted text-center">Error loading participants.</p>';
        }

        participantsModal.style.display = 'flex'; // Show modal
    }

    function closeParticipantsModal() {
        participantsModal.style.display = 'none';
        currentProgramIdForParticipants = null; // Clear active program ID
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == participantsModal) {
            closeParticipantsModal();
        }
    }

    function filterAssignStudentsByClass(classId) {
        studentIdAssignSelect.innerHTML = '<option value="">-- Select Student --</option>'; // Reset

        let studentsToDisplay = allStudentsRaw;
        if (classId) {
            studentsToDisplay = allStudentsRaw.filter(student => student.class_id == classId);
        }
        
        studentsToDisplay.forEach(student => {
            const option = document.createElement('option');
            option.value = student.id;
            option.textContent = `${student.first_name} ${student.last_name} (Reg: ${student.registration_number})`;
            studentIdAssignSelect.appendChild(option);
        });
    }

    function confirmRemoveParticipant(participantId, studentName, programName) {
        if (confirm(`Are you sure you want to remove "${studentName}" from "${programName}"? This action cannot be undone.`)) {
            window.location.href = `manage_cultural_programs.php?delete_participant_id=${participantId}&program_id_for_participant=${currentProgramIdForParticipants}`;
        }
    }

    // Helper to format date string for display
    function formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00' || dateString === '0000-00-00 00:00:00') return 'N/A';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }


    // --- Print Functionality (Universal for sections) ---
    function printTable(tableWrapperId, title) {
        const printTitle = title;
        const tableWrapper = document.getElementById(tableWrapperId);
        if (!tableWrapper) {
            alert('Printable section not found!');
            return;
        }
        
        // Ensure section content is expanded before printing
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
            // Inject most relevant CSS styles for printing
            printWindow.document.write(`
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
                h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 25px; text-align: center; }
                h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
                .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 9pt; }
                .data-table th, .data-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
                .data-table th { background-color: #ffe0f0; color: #000; font-weight: 700; text-transform: uppercase; }
                .data-table tr:nth-child(even) { background-color: #fdf0f5; }
                .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
                .status-Active { background-color: #d4edda; color: #155724; }
                .status-Upcoming { background-color: #fff3cd; color: #856404; }
                .status-Completed { background-color: #cce5ff; color: #004085; }
                .status-Cancelled { background-color: #f8d7da; color: #721c24; }
                .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group { display: none; }
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