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

// --- Filter Parameters (for Clubs) ---
$clubs_search_query = isset($_GET['clubs_search']) ? trim($_GET['clubs_search']) : '';
$clubs_filter_status = isset($_GET['clubs_status']) && $_GET['clubs_status'] !== '' ? trim($_GET['clubs_status']) : null;

// --- Pagination Configuration (for Clubs) ---
$clubs_records_per_page = 10;
$clubs_current_page = isset($_GET['clubs_page']) && is_numeric($_GET['clubs_page']) ? (int)$_GET['clubs_page'] : 1;


// --- Filter Parameters (for Memberships in Modal/Separate section if implemented) ---
$members_search_query = isset($_GET['members_search']) ? trim($_GET['members_search']) : '';
$members_filter_club_id = isset($_GET['members_club_id']) && is_numeric($_GET['members_club_id']) ? (int)$_GET['members_club_id'] : null;
$members_filter_student_id = isset($_GET['members_student_id']) && is_numeric($_GET['members_student_id']) ? (int)$_GET['members_student_id'] : null;


// --- Fetch Dropdown Data (for Forms and Filters) ---
$all_teachers = [];
$sql_all_teachers = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_all_teachers)) {
    $all_teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_departments = [];
$sql_all_departments = "SELECT id, department_name FROM departments ORDER BY department_name ASC";
if ($result = mysqli_query($link, $sql_all_departments)) {
    $all_departments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_students_raw = []; // For dynamic JS filtering in member assignment
$sql_all_students_raw = "SELECT id, first_name, last_name, registration_number, class_id FROM students ORDER BY first_name ASC, last_name ASC";
if ($result = mysqli_query($link, $sql_all_students_raw)) {
    $all_students_raw = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$all_classes = []; // For filtering students in member assignment modal
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}


// --- Process Club Form Submissions (Add/Edit) ---
if (isset($_POST['form_action']) && ($_POST['form_action'] == 'add_club' || $_POST['form_action'] == 'edit_club')) {
    $action = $_POST['form_action'];

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $teacher_in_charge_id = empty(trim($_POST['teacher_in_charge_id'])) ? NULL : (int)$_POST['teacher_in_charge_id'];
    $department_id = empty(trim($_POST['department_id'])) ? NULL : (int)$_POST['department_id'];
    $image_url = trim($_POST['image_url']);
    $meeting_schedule = trim($_POST['meeting_schedule']);
    $member_limit = empty(trim($_POST['member_limit'])) ? NULL : (int)$_POST['member_limit'];
    $status = trim($_POST['status']);

    if (empty($name) || empty($status)) {
        set_session_message("Club Name and Status are required.", "danger");
        header("location: manage_sports_clubs.php");
        exit;
    }

    if ($action == 'add_club') {
        // Check for duplicate club name
        $check_sql = "SELECT id FROM sports_clubs WHERE name = ?";
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "s", $name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                set_session_message("A club with this name already exists.", "danger");
                mysqli_stmt_close($stmt);
                header("location: manage_sports_clubs.php");
                exit;
            }
            mysqli_stmt_close($stmt);
        }

        $sql = "INSERT INTO sports_clubs (name, description, teacher_in_charge_id, department_id, image_url, meeting_schedule, member_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssiisssi", $name, $description, $teacher_in_charge_id, $department_id, $image_url, $meeting_schedule, $member_limit, $status);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Sports Club added successfully.", "success");
            } else {
                set_session_message("Error adding club: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'edit_club') {
        $club_id = (int)$_POST['club_id'];
        if (empty($club_id)) {
            set_session_message("Invalid Club ID for editing.", "danger");
            header("location: manage_sports_clubs.php");
            exit;
        }

        // Check for duplicate club name, excluding current club
        $check_sql = "SELECT id FROM sports_clubs WHERE name = ? AND id != ?";
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "si", $name, $club_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                set_session_message("A club with this name already exists.", "danger");
                mysqli_stmt_close($stmt);
                header("location: manage_sports_clubs.php");
                exit;
            }
            mysqli_stmt_close($stmt);
        }

        $sql = "UPDATE sports_clubs SET name = ?, description = ?, teacher_in_charge_id = ?, department_id = ?, image_url = ?, meeting_schedule = ?, member_limit = ?, status = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssiisssii", $name, $description, $teacher_in_charge_id, $department_id, $image_url, $meeting_schedule, $member_limit, $status, $club_id);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Sports Club updated successfully.", "success");
            } else {
                set_session_message("Error updating club: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    }
    header("location: manage_sports_clubs.php?clubs_page={$clubs_current_page}");
    exit;
}

// --- Process Club Deletion ---
if (isset($_GET['delete_club_id'])) {
    $delete_id = (int)$_GET['delete_club_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid Club ID for deletion.", "danger");
        header("location: manage_sports_clubs.php");
        exit;
    }

    // Check for associated members before deleting
    $check_members_sql = "SELECT COUNT(id) FROM club_members WHERE club_id = ?";
    if ($stmt_check = mysqli_prepare($link, $check_members_sql)) {
        mysqli_stmt_bind_param($stmt_check, "i", $delete_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_bind_result($stmt_check, $member_count);
        mysqli_stmt_fetch($stmt_check);
        mysqli_stmt_close($stmt_check);

        if ($member_count > 0) {
            set_session_message("Cannot delete club. There are {$member_count} students currently enrolled. Please remove members first.", "danger");
            header("location: manage_sports_clubs.php?clubs_page={$clubs_current_page}");
            exit;
        }
    }

    $sql = "DELETE FROM sports_clubs WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Sports Club deleted successfully.", "success");
            } else {
                set_session_message("Club not found or already deleted.", "danger");
            }
        } else {
            set_session_message("Error deleting club: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_sports_clubs.php?clubs_page={$clubs_current_page}");
    exit;
}

// --- Process Member Assignment ---
if (isset($_POST['member_action']) && $_POST['member_action'] == 'add_member') {
    $club_id = (int)$_POST['club_id_assign'];
    $student_id = (int)$_POST['student_id_assign'];
    $role = trim($_POST['member_role']);
    $join_date = trim($_POST['join_date']);

    if (empty($club_id) || empty($student_id) || empty($role) || empty($join_date)) {
        set_session_message("All fields are required to add a member.", "danger");
        header("location: manage_sports_clubs.php?view_club_id={$club_id}");
        exit;
    }

    // Check for duplicate membership
    $check_sql = "SELECT id FROM club_members WHERE club_id = ? AND student_id = ?";
    if ($stmt = mysqli_prepare($link, $check_sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $club_id, $student_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            set_session_message("This student is already a member of this club.", "danger");
            mysqli_stmt_close($stmt);
            header("location: manage_sports_clubs.php?view_club_id={$club_id}");
            exit;
        }
        mysqli_stmt_close($stmt);
    }

    // Check member limit if set
    $club_limit_sql = "SELECT member_limit FROM sports_clubs WHERE id = ?";
    if ($stmt_limit = mysqli_prepare($link, $club_limit_sql)) {
        mysqli_stmt_bind_param($stmt_limit, "i", $club_id);
        mysqli_stmt_execute($stmt_limit);
        $result_limit = mysqli_stmt_get_result($stmt_limit);
        $club_details = mysqli_fetch_assoc($result_limit);
        mysqli_stmt_close($stmt_limit);

        if ($club_details && $club_details['member_limit'] !== NULL) {
            $current_members_sql = "SELECT COUNT(id) FROM club_members WHERE club_id = ?";
            if ($stmt_current_members = mysqli_prepare($link, $current_members_sql)) {
                mysqli_stmt_bind_param($stmt_current_members, "i", $club_id);
                mysqli_stmt_execute($stmt_current_members);
                $result_current_members = mysqli_stmt_get_result($stmt_current_members);
                $current_member_count = mysqli_fetch_row($result_current_members)[0];
                mysqli_stmt_close($stmt_current_members);

                if ($current_member_count >= $club_details['member_limit']) {
                    set_session_message("Club has reached its maximum member limit of {$club_details['member_limit']}.", "danger");
                    header("location: manage_sports_clubs.php?view_club_id={$club_id}");
                    exit;
                }
            }
        }
    }


    $sql = "INSERT INTO club_members (club_id, student_id, join_date, role) VALUES (?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiss", $club_id, $student_id, $join_date, $role);
        if (mysqli_stmt_execute($stmt)) {
            set_session_message("Student added to club successfully.", "success");
        } else {
            set_session_message("Error adding student to club: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_sports_clubs.php?view_club_id={$club_id}");
    exit;
}

// --- Process Member Deletion ---
if (isset($_GET['delete_member_id']) && isset($_GET['club_id_for_member'])) {
    $delete_id = (int)$_GET['delete_member_id'];
    $club_id_redirect = (int)$_GET['club_id_for_member'];

    if (empty($delete_id)) {
        set_session_message("Invalid Member ID for deletion.", "danger");
        header("location: manage_sports_clubs.php?view_club_id={$club_id_redirect}");
        exit;
    }

    $sql = "DELETE FROM club_members WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Club member removed successfully.", "success");
            } else {
                set_session_message("Club member not found or already removed.", "danger");
            }
        } else {
            set_session_message("Error removing club member: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_sports_clubs.php?view_club_id={$club_id_redirect}");
    exit;
}


// --- Build WHERE clause for total clubs and paginated data ---
$clubs_where_clauses = ["1=1"];
$clubs_params = [];
$clubs_types = "";

if ($clubs_filter_status) {
    $clubs_where_clauses[] = "sc.status = ?";
    $clubs_params[] = $clubs_filter_status;
    $clubs_types .= "s";
}
if (!empty($clubs_search_query)) {
    $clubs_search_term = "%" . $clubs_search_query . "%";
    $clubs_where_clauses[] = "(sc.name LIKE ? OR sc.description LIKE ? OR t.full_name LIKE ?)";
    $clubs_params[] = $clubs_search_term;
    $clubs_params[] = $clubs_search_term;
    $clubs_params[] = $clubs_search_term;
    $clubs_types .= "sss";
}

$clubs_where_sql = implode(" AND ", $clubs_where_clauses);


// --- Fetch Total Clubs for Pagination ---
$total_clubs = 0;
$total_clubs_sql = "SELECT COUNT(sc.id)
                      FROM sports_clubs sc
                      LEFT JOIN teachers t ON sc.teacher_in_charge_id = t.id
                      WHERE " . $clubs_where_sql;

if ($stmt = mysqli_prepare($link, $total_clubs_sql)) {
    if (!empty($clubs_params)) {
        mysqli_stmt_bind_param($stmt, $clubs_types, ...$clubs_params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_clubs = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
}
$total_clubs_pages = ceil($total_clubs / $clubs_records_per_page);

if ($clubs_current_page < 1) $clubs_current_page = 1;
elseif ($clubs_current_page > $total_clubs_pages && $total_clubs_pages > 0) $clubs_current_page = $total_clubs_pages;
elseif ($total_clubs == 0) $clubs_current_page = 1;
$clubs_offset = ($clubs_current_page - 1) * $clubs_records_per_page;


// --- Fetch Clubs Data (with filters and pagination) ---
$sports_clubs = [];
$sql_fetch_clubs = "SELECT
                        sc.id, sc.name, sc.description, sc.image_url, sc.meeting_schedule, sc.member_limit, sc.status, sc.created_at,
                        t.full_name AS teacher_in_charge_name, t.id AS teacher_in_charge_id,
                        d.department_name, d.id AS department_id,
                        (SELECT COUNT(cm.id) FROM club_members cm WHERE cm.club_id = sc.id) AS current_members_count
                    FROM sports_clubs sc
                    LEFT JOIN teachers t ON sc.teacher_in_charge_id = t.id
                    LEFT JOIN departments d ON sc.department_id = d.id
                    WHERE " . $clubs_where_sql . "
                    ORDER BY sc.name ASC
                    LIMIT ? OFFSET ?";

$clubs_params_pagination = $clubs_params;
$clubs_params_pagination[] = $clubs_records_per_page;
$clubs_params_pagination[] = $clubs_offset;
$clubs_types_pagination = $clubs_types . "ii";

if ($stmt = mysqli_prepare($link, $sql_fetch_clubs)) {
    mysqli_stmt_bind_param($stmt, $clubs_types_pagination, ...$clubs_params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sports_clubs = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


// --- Fetch Members for a specific club if requested (for modal) ---
$current_club_for_modal = null;
$club_members = [];
if (isset($_GET['view_club_id']) && is_numeric($_GET['view_club_id'])) {
    $view_club_id = (int)$_GET['view_club_id'];
    
    // Fetch club details for modal header
    $sql_fetch_club_details = "SELECT sc.id, sc.name, sc.member_limit, (SELECT COUNT(cm.id) FROM club_members cm WHERE cm.club_id = sc.id) AS current_members_count
                               FROM sports_clubs sc WHERE sc.id = ?";
    if ($stmt_club_details = mysqli_prepare($link, $sql_fetch_club_details)) {
        mysqli_stmt_bind_param($stmt_club_details, "i", $view_club_id);
        mysqli_stmt_execute($stmt_club_details);
        $result_club_details = mysqli_stmt_get_result($stmt_club_details);
        $current_club_for_modal = mysqli_fetch_assoc($result_club_details);
        mysqli_stmt_close($stmt_club_details);
    }

    // Fetch members for this club
    $sql_fetch_members = "SELECT
                            cm.id AS member_id, cm.join_date, cm.role,
                            s.id AS student_id, s.first_name, s.last_name, s.registration_number,
                            c.class_name, c.section_name
                        FROM club_members cm
                        JOIN students s ON cm.student_id = s.id
                        JOIN classes c ON s.class_id = c.id
                        WHERE cm.club_id = ?
                        ORDER BY s.first_name ASC";
    if ($stmt_members = mysqli_prepare($link, $sql_fetch_members)) {
        mysqli_stmt_bind_param($stmt_members, "i", $view_club_id);
        mysqli_stmt_execute($stmt_members);
        $result_members = mysqli_stmt_get_result($stmt_members);
        $club_members = mysqli_fetch_all($result_members, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_members);
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
    <title>Manage Sports Clubs - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #E8F5E9, #C8E6C9, #A5D6A7, #81C784); /* Greenish pastel */
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
            color: #2E7D32; /* Dark Green */
            margin-bottom: 30px;
            border-bottom: 2px solid #A5D6A7;
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
        .section-header:hover { background-color: #388E3C; }
        .section-header h3 { margin: 0; font-size: 1em; display: flex; align-items: center; gap: 10px; }
        .section-toggle-btn { background: none; border: none; font-size: 1em; color: #fff; cursor: pointer; transition: transform 0.3s ease; }
        .section-toggle-btn.rotated { transform: rotate(90deg); }
        .section-content { max-height: 2000px; overflow: hidden; transition: max-height 0.5s ease-in-out; }
        .section-content.collapsed { max-height: 0; margin-top: 0; padding-bottom: 0; margin-bottom: 0; }


        /* Forms (Club Add/Edit, Member Add, Filters) */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group input[type="date"], .form-group textarea, .form-group select {
            width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.95rem; box-sizing: border-box;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group select {
            appearance: none; -webkit-appearance: none; -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%234CAF50%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%234CAF50%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat; background-position: right 10px center; background-size: 14px; padding-right: 30px;
        }
        .form-actions { margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end; }
        .btn-form-submit, .btn-form-cancel, .btn-filter, .btn-clear-filter, .btn-print {
            padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 0.95rem; font-weight: 600;
            transition: background-color 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; border: none;
        }
        .btn-form-submit { background-color: #4CAF50; color: #fff; }
        .btn-form-submit:hover { background-color: #388E3C; }
        .btn-form-cancel { background-color: #6c757d; color: #fff; }
        .btn-form-cancel:hover { background-color: #5a6268; }

        .filter-section {
            background-color: #e8f5e9; padding: 20px; border-radius: 8px; margin-bottom: 30px;
            border: 1px solid #c8e6c9; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group.wide { flex: 2; min-width: 250px; }
        .filter-group label { color: #2E7D32; }
        .filter-buttons { margin-top: 0; }
        .btn-filter { background-color: #66BB6A; color: #fff; }
        .btn-filter:hover { background-color: #4CAF50; }
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
        .data-table th { background-color: #e0f2f7; color: #004085; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; }
        .data-table tr:nth-child(even) { background-color: #f8fcff; }
        .data-table tr:hover { background-color: #eef7fc; }

        .action-buttons-group { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .btn-action {
            padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; border: 1px solid transparent;
        }
        .btn-edit { background-color: #FFC107; color: #333; border-color: #FFC107; }
        .btn-edit:hover { background-color: #e0a800; border-color: #e0a800; }
        .btn-delete { background-color: #dc3545; color: #fff; border-color: #dc3545; }
        .btn-delete:hover { background-color: #c82333; border-color: #bd2130; }
        .btn-view-members { background-color: #64B5F6; color: #fff; border-color: #64B5F6; }
        .btn-view-members:hover { background-color: #42A5F5; }


        .status-badge { padding: 5px 10px; border-radius: 5px; font-size: 0.8em; font-weight: 600; white-space: nowrap; }
        .status-Active { background-color: #d4edda; color: #155724; }
        .status-Inactive { background-color: #f8d7da; color: #721c24; }
        .status-Member { background-color: #e3f2fd; color: #1976D2; }
        .status-Captain { background-color: #fff3cd; color: #FBC02D; }

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
            text-decoration: none; color: #4CAF50; background-color: #fff; transition: all 0.2s ease;
        }
        .pagination-controls a:hover { background-color: #e9ecef; border-color: #c8e6c9; }
        .pagination-controls .current-page, .pagination-controls .current-page:hover {
            background-color: #4CAF50; color: #fff; border-color: #4CAF50; cursor: default;
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
        .modal-body .club-details { border-bottom: 1px dashed #ddd; padding-bottom: 15px; margin-bottom: 15px; }
        .modal-body .members-list-wrapper { margin-top: 20px; }
        .modal-body .member-item { 
            background-color: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 8px; padding: 10px 15px; margin-bottom: 8px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-body .member-info { display: flex; flex-direction: column; }
        .modal-body .member-name { font-weight: 600; color: #2E7D32; font-size: 1em; }
        .modal-body .member-details { font-size: 0.85em; color: #555; }
        .modal-body .member-actions .btn-action { margin-left: 8px; }
        
        .modal-body .form-assign-member { 
            border-top: 1px dashed #ddd; padding-top: 15px; margin-top: 20px; 
            display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;
            background-color: #f9f9f9; padding: 15px; border-radius: 8px;
        }
        .modal-body .form-assign-member .form-group { flex: 1; min-width: 150px; margin-bottom: 0; }
        .modal-body .form-assign-member .form-actions { margin-top: 0; padding-top: 0; border-top: none; justify-content: flex-start; }
        .modal-body .form-assign-member select, .modal-body .form-assign-member input { font-size: 0.9em; }

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
            .printable-area .data-table th { background-color: #e8f5e9; color: #000; }
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
            .modal-body .form-assign-member { flex-direction: column; align-items: stretch; }
            .modal-body .form-assign-member .form-group { min-width: unset; width: 100%; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-futbol"></i> Manage Sports Clubs</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Club Section -->
        <div class="section-box" id="add-edit-club-section">
            <div class="section-header" onclick="toggleSection('add-edit-club-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="false" aria-controls="add-edit-club-content">
                <h3 id="club-form-title"><i class="fas fa-plus-circle"></i> Add New Sports Club</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="add-edit-club-content" class="section-content collapsed">
                <form id="club-form" action="manage_sports_clubs.php" method="POST">
                    <input type="hidden" name="form_action" id="club-form-action" value="add_club">
                    <input type="hidden" name="club_id" id="club-id" value="">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Club Name:</label>
                            <input type="text" id="name" name="name" required placeholder="e.g., Football Club, Chess Club">
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
                            <label for="department_id">Department:</label>
                            <select id="department_id" name="department_id">
                                <option value="">-- Select Department (Optional) --</option>
                                <?php foreach ($all_departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['id']); ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="meeting_schedule">Meeting Schedule:</label>
                            <input type="text" id="meeting_schedule" name="meeting_schedule" placeholder="e.g., Tuesdays 3-4 PM in Gym">
                        </div>
                        <div class="form-group">
                            <label for="member_limit">Member Limit (Optional):</label>
                            <input type="number" id="member_limit" name="member_limit" min="1" placeholder="e.g., 20">
                        </div>
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="image_url">Image URL:</label>
                            <input type="text" id="image_url" name="image_url" placeholder="URL for club logo/image">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="3" placeholder="Brief description of the club"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-form-submit" id="club-submit-btn"><i class="fas fa-plus-circle"></i> Add Club</button>
                        <button type="button" class="btn-form-cancel" id="club-cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sports Clubs Overview Section -->
        <div class="section-box printable-area" id="clubs-overview-section">
            <div class="section-header" onclick="toggleSection('clubs-overview-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="true" aria-controls="clubs-overview-content">
                <h3><i class="fas fa-list"></i> Sports Clubs Overview</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="clubs-overview-content" class="section-content">
                <div class="filter-section">
                    <form action="manage_sports_clubs.php" method="GET" style="display:contents;">
                        <input type="hidden" name="section" value="overview">
                        
                        <div class="filter-group">
                            <label for="clubs_filter_status"><i class="fas fa-toggle-on"></i> Status:</label>
                            <select class="h-12" id="clubs_filter_status" name="clubs_status">
                                <option value="">-- All Statuses --</option>
                                <option value="Active" <?php echo ($clubs_filter_status == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($clubs_filter_status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="filter-group wide">
                            <label for="clubs_search_query"><i class="fas fa-search"></i> Search Clubs:</label>
                            <input class="h-12" type="text" id="clubs_search_query" name="clubs_search" value="<?php echo htmlspecialchars($clubs_search_query); ?>" placeholder="Club Name, Description, Teacher">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                            <?php if ($clubs_filter_status || !empty($clubs_search_query)): ?>
                                <a href="manage_sports_clubs.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                            <button type="button" class="btn-print" onclick="printTable('clubs-table-wrapper', 'Sports Clubs Report')"><i class="fas fa-print"></i> Print Clubs</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($sports_clubs)): ?>
                    <p class="no-results">No sports clubs found matching your criteria.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;" id="clubs-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Club Name</th>
                                    <th>Description</th>
                                    <th>In Charge</th>
                                    <th>Department</th>
                                    <th>Schedule</th>
                                    <th>Limit</th>
                                    <th>Members</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sports_clubs as $club): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($club['name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($club['description'] ?: 'N/A', 0, 100)); ?>
                                            <?php if (strlen($club['description'] ?: '') > 100): ?>
                                                ... <a href="#" onclick="alert('Full Description: <?php echo htmlspecialchars($club['description']); ?>'); return false;" class="text-muted">more</a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($club['teacher_in_charge_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($club['department_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($club['meeting_schedule'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($club['member_limit'] ?: 'No Limit'); ?></td>
                                        <td><?php echo htmlspecialchars($club['current_members_count']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo str_replace(' ', '', htmlspecialchars($club['status'])); ?>">
                                                <?php echo htmlspecialchars($club['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <button class="btn-action btn-view-members" onclick="viewClubMembers(<?php echo htmlspecialchars(json_encode($club)); ?>)">
                                                    <i class="fas fa-users"></i> View Members
                                                </button>
                                                <button class="btn-action btn-edit" onclick="editClub(<?php echo htmlspecialchars(json_encode($club)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="javascript:void(0);" onclick="confirmDeleteClub(<?php echo $club['id']; ?>, '<?php echo htmlspecialchars($club['name']); ?>', <?php echo $club['current_members_count']; ?>)" class="btn-action btn-delete">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_clubs > 0): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo ($clubs_offset + 1); ?> to <?php echo min($clubs_offset + $clubs_records_per_page, $total_clubs); ?> of <?php echo $total_clubs; ?> clubs
                            </div>
                            <div class="pagination-controls">
                                <?php
                                $clubs_base_url_params = array_filter([
                                    'clubs_search' => $clubs_search_query,
                                    'clubs_status' => $clubs_filter_status
                                ]);
                                $clubs_base_url = "manage_sports_clubs.php?" . http_build_query($clubs_base_url_params);
                                ?>

                                <?php if ($clubs_current_page > 1): ?>
                                    <a href="<?php echo $clubs_base_url . '&clubs_page=' . ($clubs_current_page - 1); ?>">Previous</a>
                                <?php else: ?>
                                    <span class="disabled">Previous</span>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $clubs_current_page - 2);
                                $end_page = min($total_clubs_pages, $clubs_current_page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="' . $clubs_base_url . '&clubs_page=1">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span>...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++):
                                    if ($i == $clubs_current_page): ?>
                                        <span class="current-page"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo $clubs_base_url . '&clubs_page=' . $i; ?>"><?php echo $i; ?></a>
                                    <?php endif;
                                endfor;

                                if ($end_page < $total_clubs_pages) {
                                    if ($end_page < $total_clubs_pages - 1) {
                                        echo '<span>...</span>';
                                    }
                                    echo '<a href="' . $clubs_base_url . '&clubs_page=' . $total_clubs_pages . '">' . $total_clubs_pages . '</a>';
                                }
                                ?>

                                <?php if ($clubs_current_page < $total_clubs_pages): ?>
                                    <a href="<?php echo $clubs_base_url . '&clubs_page=' . ($clubs_current_page + 1); ?>">Next</a>
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

<!-- View Members Modal -->
<div id="membersModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="members-modal-title">Members of: <span id="modal-club-name"></span></h4>
      <span class="close-btn" onclick="closeMembersModal()">&times;</span>
    </div>
    <div class="modal-body">
      <div class="club-details">
        <p><strong>Total Members:</strong> <span id="modal-total-members"></span></p>
        <p><strong>Member Limit:</strong> <span id="modal-member-limit"></span></p>
        <p id="modal-member-status" class="text-muted"></p>
        <hr>
      </div>

      <div class="members-list-wrapper">
        <h4>Current Members:</h4>
        <div id="modal-members-content">
          <!-- Members will be loaded here via JS -->
          <p class="text-muted text-center">No members yet.</p>
        </div>
      </div>

      <h4 style="margin-top: 30px;"><i class="fas fa-user-plus"></i> Add New Member</h4>
      <form id="add-member-form" class="form-assign-member" action="manage_sports_clubs.php" method="POST">
        <input type="hidden" name="member_action" value="add_member">
        <input type="hidden" name="club_id_assign" id="member-form-club-id">

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
        <div class="form-group">
            <label for="member_role">Role:</label>
            <select id="member_role" name="member_role" required>
                <option value="Member">Member</option>
                <option value="Captain">Captain</option>
                <option value="Co-Captain">Co-Captain</option>
                <option value="Treasurer">Treasurer</option>
            </select>
        </div>
        <div class="form-group">
            <label for="join_date">Join Date:</label>
            <input type="date" id="join_date" name="join_date" required value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-actions" style="grid-column: 1 / -1;">
            <button type="submit" class="btn-form-submit"><i class="fas fa-plus"></i> Add Member</button>
        </div>
      </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn-modal-close" onclick="closeMembersModal()">Close</button>
    </div>
  </div>
</div>


<script>
    // Raw student data for JavaScript filtering (for member assignment)
    const allStudentsRaw = <?php echo json_encode($all_students_raw); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize collapsed state for sections
        document.querySelectorAll('.section-box .section-header').forEach(header => {
            const contentId = header.querySelector('.section-toggle-btn').getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            const button = header.querySelector('.section-toggle-btn');
            
            // "Add New Sports Club" section starts collapsed
            if (button.getAttribute('aria-expanded') === 'false') {
                content.classList.add('collapsed');
                button.querySelector('.fas').classList.remove('fa-chevron-down');
                button.querySelector('.fas').classList.add('fa-chevron-right');
            } else {
                // "Sports Clubs Overview" starts expanded
                content.style.maxHeight = content.scrollHeight + 'px';
                setTimeout(() => content.style.maxHeight = null, 500);
            }
        });

        // Initialize join date for Add Member form
        document.getElementById('join_date').value = new Date().toISOString().slice(0, 10);
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

    // --- Club Management JS ---
    function editClub(clubData) {
        // Ensure the form section is expanded
        const formContent = document.getElementById('add-edit-club-content');
        const formToggleButton = document.querySelector('#add-edit-club-section .section-toggle-btn');
        if (formContent.classList.contains('collapsed')) {
            toggleSection('add-edit-club-content', formToggleButton);
        }

        document.getElementById('club-form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Club: ' + clubData.name;
        document.getElementById('club-form-action').value = 'edit_club';
        document.getElementById('club-id').value = clubData.id;

        document.getElementById('name').value = clubData.name || '';
        document.getElementById('description').value = clubData.description || '';
        document.getElementById('teacher_in_charge_id').value = clubData.teacher_in_charge_id || '';
        document.getElementById('department_id').value = clubData.department_id || '';
        document.getElementById('image_url').value = clubData.image_url || '';
        document.getElementById('meeting_schedule').value = clubData.meeting_schedule || '';
        document.getElementById('member_limit').value = clubData.member_limit || ''; // Can be null
        document.getElementById('status').value = clubData.status || 'Active';
        
        document.getElementById('club-submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Club';
        document.getElementById('club-cancel-btn').style.display = 'inline-flex';

        document.getElementById('add-edit-club-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('club-cancel-btn').addEventListener('click', function() {
        document.getElementById('club-form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Sports Club';
        document.getElementById('club-form-action').value = 'add_club';
        document.getElementById('club-id').value = '';
        document.getElementById('club-form').reset();
        
        document.getElementById('club-submit-btn').innerHTML = '<i class="fas fa-plus-circle"></i> Add Club';
        document.getElementById('club-cancel-btn').style.display = 'none';

        // Optionally collapse the section
        const formContent = document.getElementById('add-edit-club-content');
        const formToggleButton = document.querySelector('#add-edit-club-section .section-toggle-btn');
        if (!formContent.classList.contains('collapsed')) {
             toggleSection('add-edit-club-content', formToggleButton);
        }
    });

    function confirmDeleteClub(id, name, memberCount) {
        if (memberCount > 0) {
            alert(`Cannot delete club "${name}". There are ${memberCount} students currently enrolled. Please remove them first.`);
            return false;
        }
        if (confirm(`Are you sure you want to permanently delete the club "${name}"? This action cannot be undone.`)) {
            window.location.href = `manage_sports_clubs.php?delete_club_id=${id}`;
        }
    }

    // --- Club Members Modal JS ---
    const membersModal = document.getElementById('membersModal');
    const modalClubName = document.getElementById('modal-club-name');
    const modalTotalMembers = document.getElementById('modal-total-members');
    const modalMemberLimit = document.getElementById('modal-member-limit');
    const modalMemberStatus = document.getElementById('modal-member-status');
    const modalMembersContent = document.getElementById('modal-members-content');
    const memberFormClubId = document.getElementById('member-form-club-id');
    const assignStudentClassIdSelect = document.getElementById('assign_student_class_id');
    const studentIdAssignSelect = document.getElementById('student_id_assign');
    let currentClubIdForMembers = null; // For refreshing members list

    async function viewClubMembers(clubData) {
        currentClubIdForMembers = clubData.id;
        modalClubName.textContent = clubData.name;
        modalTotalMembers.textContent = clubData.current_members_count;
        modalMemberLimit.textContent = clubData.member_limit || 'No Limit';

        if (clubData.member_limit && clubData.current_members_count >= clubData.member_limit) {
            modalMemberStatus.textContent = 'Club is at full capacity.';
            modalMemberStatus.style.color = 'red';
        } else {
            modalMemberStatus.textContent = '';
            modalMemberStatus.style.color = '';
        }

        memberFormClubId.value = clubData.id;
        document.getElementById('add-member-form').reset(); // Clear form
        document.getElementById('join_date').value = new Date().toISOString().slice(0, 10);
        assignStudentClassIdSelect.value = ''; // Reset class filter
        filterAssignStudentsByClass(''); // Populate student dropdown with all students

        // Fetch and display members for this club
        modalMembersContent.innerHTML = '<p class="text-muted text-center">Loading members...</p>';
        try {
            const response = await fetch(`fetch_club_members.php?club_id=${clubData.id}`);
            const members = await response.json();
            modalMembersContent.innerHTML = ''; // Clear loading message

            if (members.length === 0) {
                modalMembersContent.innerHTML = '<p class="text-muted text-center">No members yet.</p>';
            } else {
                members.forEach(member => {
                    const memberItem = document.createElement('div');
                    memberItem.className = 'member-item';
                    memberItem.innerHTML = `
                        <div class="member-info">
                            <div class="member-name">${member.first_name} ${member.last_name} (Reg: ${member.registration_number})</div>
                            <div class="member-details">Class: ${member.class_name}-${member.section_name} | Role: ${member.role} | Joined: ${formatDate(member.join_date)}</div>
                        </div>
                        <div class="member-actions">
                            <a href="javascript:void(0);" onclick="confirmRemoveMember(${member.member_id}, '${member.first_name} ${member.last_name}', '${clubData.name}')" class="btn-action btn-delete">
                                <i class="fas fa-user-minus"></i> Remove
                            </a>
                        </div>
                    `;
                    modalMembersContent.appendChild(memberItem);
                });
            }
        } catch (error) {
            console.error('Error fetching members:', error);
            modalMembersContent.innerHTML = '<p class="text-muted text-center">Error loading members.</p>';
        }

        membersModal.style.display = 'flex'; // Show modal
    }

    function closeMembersModal() {
        membersModal.style.display = 'none';
        currentClubIdForMembers = null; // Clear active club ID
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == membersModal) {
            closeMembersModal();
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

    function confirmRemoveMember(memberId, studentName, clubName) {
        if (confirm(`Are you sure you want to remove "${studentName}" from "${clubName}"? This action cannot be undone.`)) {
            window.location.href = `manage_sports_clubs.php?delete_member_id=${memberId}&club_id_for_member=${currentClubIdForMembers}`;
        }
    }

    // Helper to format date string for display
    function formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00') return 'N/A';
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
                .data-table th { background-color: #e8f5e9; color: #000; font-weight: 700; text-transform: uppercase; }
                .data-table tr:nth-child(even) { background-color: #f9fdf9; }
                .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
                .status-Active { background-color: #d4edda; color: #155724; }
                .status-Inactive { background-color: #f8d7da; color: #721c24; }
                .status-Member { background-color: #e3f2fd; color: #1976D2; }
                .status-Captain { background-color: #fff3cd; color: #FBC02D; }
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
        }, 100); // Small delay before printing
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>