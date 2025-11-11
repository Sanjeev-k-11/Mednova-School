<?php
// indiscipline.php
session_start();

// Include database connection and helper functions
require_once '../database/config.php';
require_once '../database/helpers.php';

$error_message = '';
$success_message = '';

// --- Mock User Authentication (REPLACE WITH YOUR REAL AUTHENTICATION) ---
// For testing: uncomment ONE of the following lines to simulate a user role.
// Ensure the ID exists in your teachers or admins table for testing.
$_SESSION['user_role'] = 'teacher'; $_SESSION['teacher_id'] = 1; // Simulate a teacher (ID 1)
// $_SESSION['user_role'] = 'admin'; $_SESSION['admin_id'] = 1;     // Simulate an admin (ID 1)

$user_role = $_SESSION['user_role'] ?? null;
$logged_in_teacher_id = $_SESSION['teacher_id'] ?? null;
$logged_in_admin_id = $_SESSION['admin_id'] ?? null;

if (!$user_role) {
    echo "Access Denied: Please log in.";
    exit();
}
// --- END Mock User Authentication ---


// --- Handle AJAX Request for Student Lookup (Only for Teacher Role) ---
// This part will exit the script after sending JSON, so no HTML will be rendered if this branch executes.
if ($user_role === 'teacher' && isset($_GET['action']) && $_GET['action'] === 'lookup_student_by_reg_no') {
    header('Content-Type: application/json');
    $reg_no = trim($_GET['reg_no'] ?? '');
    $student_data = [];
    $indiscipline_history = [];

    if (!empty($reg_no)) {
        // Fetch student details
        $sql_student = "SELECT s.id, s.first_name, s.middle_name, s.last_name, s.roll_number, c.class_name, c.section_name
                        FROM students s
                        LEFT JOIN classes c ON s.class_id = c.id
                        WHERE s.registration_number = ?";
        if ($stmt_student = mysqli_prepare($link, $sql_student)) {
            mysqli_stmt_bind_param($stmt_student, "s", $reg_no);
            mysqli_stmt_execute($stmt_student);
            $result_student = mysqli_stmt_get_result($stmt_student);
            $student_info = mysqli_fetch_assoc($result_student);
            mysqli_stmt_close($stmt_student);

            if ($student_info) {
                $student_data['student_id'] = $student_info['id'];
                $student_data['full_name'] = trim($student_info['first_name'] . ' ' . $student_info['middle_name'] . ' ' . $student_info['last_name']);
                $student_data['roll_number'] = $student_info['roll_number'];
                $student_data['class_name'] = trim($student_info['class_name'] . ' - ' . $student_info['section_name']);

                // Fetch indiscipline history for this student
                $sql_history = "SELECT incident_date, severity, description, status FROM indiscipline_reports WHERE reported_student_id = ? ORDER BY incident_date DESC LIMIT 5";
                if ($stmt_history = mysqli_prepare($link, $sql_history)) {
                    mysqli_stmt_bind_param($stmt_history, "i", $student_info['id']);
                    mysqli_stmt_execute($stmt_history);
                    $result_history = mysqli_stmt_get_result($stmt_history);
                    while ($row = mysqli_fetch_assoc($result_history)) {
                        $indiscipline_history[] = $row;
                    }
                    mysqli_stmt_close($stmt_history);
                }
            }
        }
    }
    echo json_encode(['student' => $student_data, 'history' => $indiscipline_history]);
    mysqli_close($link);
    exit(); // IMPORTANT: Terminate script after AJAX response
}

// --- Handle AJAX Request for Teacher's Reports (Only for Teacher Role) ---
// This part will exit the script after sending JSON, so no HTML will be rendered if this branch executes.
if ($user_role === 'teacher' && isset($_GET['action']) && $_GET['action'] === 'get_teacher_reports') {
    header('Content-Type: application/json');
    $teacher_reports = [];
    if ($logged_in_teacher_id) {
        $sql_reports = "SELECT
                            ir.id, ir.incident_date, ir.severity, ir.description, ir.status,
                            s.first_name, s.last_name, s.registration_number, c.class_name, c.section_name
                        FROM
                            indiscipline_reports ir
                        LEFT JOIN
                            students s ON ir.reported_student_id = s.id
                        LEFT JOIN
                            classes c ON ir.class_id = c.id
                        WHERE
                            ir.reported_by_teacher_id = ?
                        ORDER BY
                            ir.created_at DESC";
        if ($stmt_reports = mysqli_prepare($link, $sql_reports)) {
            mysqli_stmt_bind_param($stmt_reports, "i", $logged_in_teacher_id);
            mysqli_stmt_execute($stmt_reports);
            $result_reports = mysqli_stmt_get_result($stmt_reports);
            while ($row = mysqli_fetch_assoc($result_reports)) {
                $teacher_reports[] = $row;
            }
            mysqli_stmt_close($stmt_reports);
        }
    }
    echo json_encode($teacher_reports);
    mysqli_close($link);
    exit(); // IMPORTANT: Terminate script after AJAX response
}


// --- TEACHER ROLE: Handle Indiscipline Report Submission (POST Request) ---
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_report') {
    if (!$logged_in_teacher_id) {
        $error_message = "Authentication error: Teacher ID not found.";
    } else {
        // Collect and sanitize input
        $reported_student_id = isset($_POST['reported_student_id']) && $_POST['reported_student_id'] !== '' ? (int)$_POST['reported_student_id'] : null;
        $class_id = isset($_POST['class_id']) && $_POST['class_id'] !== '' ? (int)$_POST['class_id'] : null;
        $subject_id = isset($_POST['subject_id']) && $_POST['subject_id'] !== '' ? (int)$_POST['subject_id'] : null;
        $incident_date = trim($_POST['incident_date'] ?? '');
        $incident_time = trim($_POST['incident_time'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $evidence_url = trim($_POST['evidence_url'] ?? '');
        $severity = trim($_POST['severity'] ?? '');
        $immediate_action_taken = trim($_POST['immediate_action_taken'] ?? '');

        // Basic validation
        if ($reported_student_id === null || empty($incident_date) || empty($description) || empty($severity)) {
            $error_message = "Please select a student and fill in all required fields (Incident Date, Description, Severity).";
        } else {
            if (empty($error_message)) {
                $sql = "INSERT INTO indiscipline_reports (
                            reported_by_teacher_id, target_type, reported_student_id,
                            class_id, subject_id, incident_date, incident_time, location,
                            description, evidence_url, severity, immediate_action_taken
                        ) VALUES (?, 'Student', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // target_type is always 'Student'

                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param(
                        $stmt, "iiissssssss",
                        $logged_in_teacher_id,
                        $reported_student_id,
                        $class_id,
                        $subject_id,
                        $incident_date,
                        $incident_time,
                        $location,
                        $description,
                        $evidence_url,
                        $severity,
                        $immediate_action_taken
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "Indiscipline report submitted successfully.";
                        // Clear form fields if successful
                        $_POST = [];
                        // Re-fetch report count after submission
                        $sql_count = "SELECT COUNT(id) FROM indiscipline_reports WHERE reported_by_teacher_id = ?";
                        if ($stmt_count = mysqli_prepare($link, $sql_count)) {
                            mysqli_stmt_bind_param($stmt_count, "i", $logged_in_teacher_id);
                            mysqli_stmt_execute($stmt_count);
                            mysqli_stmt_bind_result($stmt_count, $teacher_reports_count_new);
                            mysqli_stmt_fetch($stmt_count);
                            mysqli_stmt_close($stmt_count);
                            $teacher_reports_count = $teacher_reports_count_new; // Update for display
                        }
                    } else {
                        $error_message = "Error submitting report: " . mysqli_error($link);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_message = "Error preparing statement: " . mysqli_error($link);
                }
            }
        }
    }
}

// --- ADMIN ROLE: Handle Report Review/Update (POST Request) ---
if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'review_report') {
    if (!$logged_in_admin_id) {
        $error_message = "Authentication error: Admin ID not found.";
    } else {
        $report_id = (int)$_POST['report_id'];
        $status = trim($_POST['status'] ?? '');
        $review_notes = trim($_POST['review_notes'] ?? '');
        $final_action_taken = trim($_POST['final_action_taken'] ?? '');

        if (empty($status) || $report_id <= 0) {
            $error_message = "Invalid report ID or status.";
        } else {
            $sql = "UPDATE indiscipline_reports SET
                        status = ?,
                        review_notes = ?,
                        final_action_taken = ?,
                        reviewed_by_admin_id = ?,
                        review_date = NOW()
                    WHERE id = ?";

            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param(
                    $stmt, "sssii",
                    $status,
                    $review_notes,
                    $final_action_taken,
                    $logged_in_admin_id,
                    $report_id
                );

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Report #{$report_id} updated successfully.";
                } else {
                    $error_message = "Error updating report: " . mysqli_error($link);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Error preparing statement: " . mysqli_error($link);
            }
        }
    }
}

// --- Data Fetching for Teacher Form or Admin Table (GET Request or initial page load) ---
// This block only runs if the script is NOT exiting due to an AJAX request.
if (mysqli_ping($link)) { // Ensure link is still open if not exited by AJAX
    if ($user_role === 'teacher') {
        // Fetch count of reports by current teacher
        $teacher_reports_count = 0;
        if ($logged_in_teacher_id) {
            $sql_count = "SELECT COUNT(id) FROM indiscipline_reports WHERE reported_by_teacher_id = ?";
            if ($stmt_count = mysqli_prepare($link, $sql_count)) {
                mysqli_stmt_bind_param($stmt_count, "i", $logged_in_teacher_id);
                mysqli_stmt_execute($stmt_count);
                mysqli_stmt_bind_result($stmt_count, $teacher_reports_count);
                mysqli_stmt_fetch($stmt_count);
                mysqli_stmt_close($stmt_count);
            }
        }

        // Needed for the form dropdowns
        $all_classes = get_all_classes($link);
        $all_subjects = get_all_subjects($link);
    } elseif ($user_role === 'admin') {
        $reports = [];
        $filter_status = $_GET['filter_status'] ?? 'Pending Review'; // Default filter for admins
        $filter_target_type = $_GET['filter_target_type'] ?? 'Student'; // Default to Student for new reports

        $sql_parts = [
            "SELECT
                ir.id, ir.target_type, ir.incident_date, ir.incident_time, ir.location, ir.description, ir.evidence_url,
                ir.severity, ir.status, ir.immediate_action_taken, ir.final_action_taken,
                ir.reported_by_teacher_id,
                reporter.full_name AS reporter_name,
                student.first_name AS student_first_name, student.middle_name AS student_middle_name, student.last_name AS student_last_name, student.registration_number AS student_reg_no,
                c.class_name, c.section_name,
                s.subject_name,
                admin.full_name AS reviewer_name, ir.review_date, ir.review_notes
            FROM
                indiscipline_reports ir
            LEFT JOIN
                teachers reporter ON ir.reported_by_teacher_id = reporter.id
            LEFT JOIN
                students student ON ir.reported_student_id = student.id
            LEFT JOIN
                classes c ON ir.class_id = c.id
            LEFT JOIN
                subjects s ON ir.subject_id = s.id
            LEFT JOIN
                admins admin ON ir.reviewed_by_admin_id = admin.id
            WHERE 1=1"
        ];
        $params = [];
        $types = '';

        if (!empty($filter_status) && $filter_status !== 'All') {
            $sql_parts[] = "ir.status = ?";
            $params[] = $filter_status;
            $types .= 's';
        }
        if (!empty($filter_target_type) && $filter_target_type !== 'All') {
            $sql_parts[] = "ir.target_type = ?";
            $params[] = $filter_target_type;
            $types .= 's';
        }

        $sql_parts[] = "ORDER BY ir.created_at DESC";
        $sql = implode(" AND ", $sql_parts);


        if ($stmt = mysqli_prepare($link, $sql)) {
            if (!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $reports[] = $row;
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Error fetching reports: " . mysqli_error($link);
        }
    }
    mysqli_close($link);
}
require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indiscipline <?php echo ucfirst($user_role); ?> Interface</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50; /* Green */
            --primary-dark: #45a049;
            --secondary-color: #007bff; /* Blue */
            --secondary-dark: #0056b3;
            --danger-color: #dc3545; /* Red */
            --warning-color: #ffc107; /* Yellow */
            --info-color: #17a2b8; /* Cyan */
            --bg-light: #f8f9fa;
            --bg-dark: #343a40;
            --text-dark: #333;
            --text-light: #fff;
            --border-color: #ddd;
            --shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --padding-base: 20px; /* Increased base padding */
            --card-gap: 20px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            background-color: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background-color: var(--primary-color);
            color: var(--text-light);
            padding: var(--padding-base);
            text-align: center;
            box-shadow: var(--shadow);
            margin-bottom: var(--card-gap);
        }

        .header h1 {
            font-size: 2.2em; /* Slightly larger heading */
            margin-bottom: 0;
        }

        .container {
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            background: var(--text-light);
            padding: var(--padding-base);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: var(--card-gap);
        }

        h2 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 30px; /* Increased margin */
            font-size: 2em; /* Slightly larger main heading */
            position: relative;
            padding-bottom: 10px;
        }

        h2::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 100px; /* Longer underline */
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }

        .message {
            padding: 12px 18px; /* Increased padding */
            border-radius: var(--border-radius);
            margin-bottom: var(--card-gap);
            font-weight: 500;
        }
        .error {
            background-color: #f8d7da;
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        .success {
            background-color: #d4edda;
            color: var(--primary-dark);
            border: 1px solid var(--primary-dark);
        }
        .info { /* New style for informational messages */
            background-color: #cfe2ff;
            color: var(--secondary-dark);
            border: 1px solid var(--secondary-dark);
        }

        /* Reusable Card Style */
        .card {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--padding-base);
            box-shadow: var(--shadow);
            background-color: var(--bg-light);
            margin-bottom: var(--card-gap); /* Default spacing between cards */
        }
        .card h3, .card h4 {
            color: var(--secondary-color);
            margin-bottom: 15px;
            font-size: 1.4em;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 5px;
        }

        /* Form Styling */
        .form-grid { /* For forms with multi-column layout */
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 10px; /* Space between form groups within a grid */
        }
        label {
            display: block;
            margin-bottom: 8px; /* Increased label margin */
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95em;
        }
        input[type="text"],
        input[type="date"],
        input[type="time"],
        textarea,
        select {
            width: 100%;
            padding: 12px; /* Increased padding */
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            background-color: #fcfcfc;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="date"]:focus,
        input[type="time"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2); /* Stronger focus ring */
            outline: none;
        }
        textarea {
            resize: vertical;
            min-height: 120px; /* Taller textarea */
        }
        .submit-btn {
            background-color: var(--primary-color);
            color: var(--text-light);
            padding: 14px 30px; /* Larger button */
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.15em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%; /* Full width within its grid column */
            margin-top: 25px;
        }
        .submit-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        .full-width {
            grid-column: 1 / -1; /* Make an element span all columns in a grid */
        }

        /* Teacher Specific Layout */
        .teacher-dashboard {
            position: relative; /* For positioning the reports count */
        }
        .teacher-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .teacher-reports-count {
            position: absolute;
            top: -10px; /* Adjust if needed based on H2 position */
            right: 0;
            background-color: var(--info-color);
            color: var(--text-light);
            padding: 10px 20px; /* Larger padding */
            border-radius: 25px; /* Pill shape */
            font-weight: 600;
            box-shadow: var(--shadow);
            z-index: 10;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px; /* Space between text and count number */
        }
        .teacher-reports-count:hover {
            background-color: #138496; /* Darker cyan on hover */
            transform: translateY(-2px);
        }
        #teacher_reports_count_display {
            background-color: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 1em;
        }

        /* Teacher's main content grid after lookup */
        #teacher_content_grid {
            display: grid;
            grid-template-columns: 1fr 2fr; /* Student info/history (1 part) | Report Form (2 parts) */
            gap: var(--card-gap);
            margin-top: var(--card-gap);
        }

        /* Student Info & History Specifics */
        .student-details-and-history-card {
            background-color: #e6f7ff; /* Lighter blue background */
            border-color: #99ddff; /* Darker blue border */
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        #student_details_display p strong {
            display: inline-block;
            min-width: 100px; /* Align details */
            margin-right: 10px;
        }
        #student_indiscipline_history h4 {
            color: var(--info-color);
            margin-bottom: 12px;
            font-size: 1.2em;
        }
        #student_indiscipline_history ul {
            list-style-type: decimal;
            margin-left: 25px;
            padding-left: 0;
            border-left: 3px solid var(--info-color); /* Stronger left border */
            padding-left: 15px;
        }
        #student_indiscipline_history ul li {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f0faff;
            border-radius: 5px;
            font-size: 0.95em;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); /* Subtle shadow for list items */
        }

        /* Admin Specific Styles */
        .filter-form {
            background-color: #eef4f8;
            padding: var(--padding-base);
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .filter-group {
            flex: 1; /* Distribute space more evenly */
            min-width: 200px; /* Increased min-width for filters */
        }
        .filter-form label {
            margin-right: 10px;
            font-weight: 600;
        }
        .filter-form select {
            border-color: #c9d6e4;
            background-color: #fff;
            flex-grow: 1; /* Allow select to take available space */
        }
        .filter-form button {
            background-color: var(--secondary-color);
            color: var(--text-light);
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .filter-form button:hover {
            background-color: var(--secondary-dark);
            transform: translateY(-2px);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: var(--card-gap);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            background-color: var(--text-light);
            min-width: 700px; /* Ensure a minimum width for table on smaller screens before scroll */
        }
        th, td {
            border: 1px solid var(--border-color);
            padding: 14px 18px; /* Increased padding */
            text-align: left;
            vertical-align: middle;
            font-size: 0.95em;
        }
        th {
            background-color: #f2f2f2;
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f0f0;
        }

        .status-pending-review { color: var(--warning-color); font-weight: bold; }
        .status-reviewed---warning-issued, .status-reviewed---further-action, .status-reviewed---no-action { color: var(--primary-color); }
        .status-closed { color: var(--secondary-dark); }
        .action-btn {
            background-color: var(--secondary-color);
            color: var(--text-light);
            padding: 9px 15px; /* Slightly larger button */
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
            white-space: nowrap;
        }
        .action-btn:hover {
            background-color: var(--secondary-dark);
            transform: translateY(-1px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s ease-out;
        }
        .modal-content {
            background-color: var(--bg-light);
            margin: 5vh auto;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            width: 95%;
            max-width: 900px; /* Larger max-width for modals */
            position: relative;
            animation: slideIn 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close-button {
            color: var(--text-dark);
            position: absolute;
            top: 20px; /* Adjusted position */
            right: 30px; /* Adjusted position */
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .close-button:hover, .close-button:focus {
            color: var(--danger-color);
        }
        .modal-content h3 {
            color: var(--secondary-color);
            margin-bottom: 25px;
            font-size: 1.8em;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 10px;
        }
        .modal-details {
            margin-bottom: 30px;
            padding: 20px; /* Increased padding */
            background-color: #f0f4f8;
            border-radius: 5px;
            border: 1px solid #c9d6e4;
        }
        .modal-details p {
            margin-bottom: 10px;
            font-size: 1.05em;
            display: flex;
            align-items: baseline;
        }
        .modal-details strong {
            color: var(--text-dark);
            min-width: 180px; /* Increased min-width for alignment */
            margin-right: 15px;
            flex-shrink: 0;
        }
        .modal-details a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .modal-details a:hover {
            text-decoration: underline;
        }
        .modal .form-group {
            margin-bottom: 20px;
        }
        .modal .submit-btn {
            width: 100%;
            margin-top: 25px;
        }
        /* Teacher Reports Modal Specific Styles */
        #myReportsModal .report-item {
            border: 1px solid #eee;
            border-left: 5px solid var(--info-color); /* Thicker border */
            padding: 15px; /* More padding */
            margin-bottom: 12px;
            border-radius: 5px;
            background-color: #fdfdfd;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08); /* Subtle shadow */
        }
        #myReportsModal .report-item p {
            margin-bottom: 5px;
        }
        #myReportsModal .report-item strong {
            display: inline-block;
            min-width: 130px; /* Align details */
            color: var(--secondary-dark);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Footer */
        .footer {
            background-color: var(--bg-dark);
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            padding: var(--padding-base);
            margin-top: var(--card-gap);
            font-size: 0.9em;
        }

        /* Media Queries for Responsiveness */
        @media (max-width: 992px) { /* Tablets and smaller desktops */
            .container {
                padding: var(--padding-base) 15px;
            }
            #teacher_content_grid {
                grid-template-columns: 1fr; /* Stack student info/history and report form */
            }
            .teacher-reports-count {
                top: -5px; /* Adjust for potentially less space */
                right: 15px; /* Adjust for container padding */
            }
        }

        @media (max-width: 768px) { /* Larger phones and small tablets */
            .header h1 { font-size: 1.8em; }
            h2 { font-size: 1.6em; margin-bottom: 20px;}
            h2::after { width: 80px; }
            .container {
                padding: 15px 10px;
                margin: 10px auto;
            }
            .teacher-header {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 20px;
            }
            .teacher-reports-count {
                position: static; /* Remove absolute positioning on small screens */
                width: 100%;
                text-align: center;
                margin-bottom: 15px;
                justify-content: center; /* Center content horizontally */
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .filter-group {
                min-width: 100%;
            }
            .filter-form button {
                width: 100%;
            }
            table th, table td {
                padding: 10px;
                font-size: 0.85em;
            }
            .modal-content {
                margin: 3vh auto;
                width: 98%;
                padding: 20px;
            }
            .modal-details strong {
                min-width: 120px;
            }
            .form-grid {
                grid-template-columns: 1fr; /* Single column for all form fields */
            }
        }

        @media (max-width: 480px) { /* Smaller phones */
            body { margin: 10px; }
            .header h1 { font-size: 1.4em; }
            h2 { font-size: 1.2em; }
            .submit-btn { padding: 12px 20px; font-size: 1em; }
            input[type="text"], input[type="date"], input[type="time"], textarea, select { font-size: 0.9em; padding: 10px; }
            .action-btn { padding: 7px 10px; font-size: 0.8em; }
            .card { padding: 15px; }
            .card h3, .card h4 { font-size: 1.2em; }
            .modal-details p { flex-direction: column; align-items: flex-start; } /* Stack strong and span */
            .modal-details strong { min-width: auto; margin-right: 0; margin-bottom: 3px; }
        }
    </style>
</head>
<body>
    

    <main class="container mt-28">
        <?php if ($error_message): ?>
            <p class="message error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <p class="message success"><?php echo $success_message; ?></p>
        <?php endif; ?>

        <?php if ($user_role === 'teacher'): ?>
            <section class="teacher-dashboard mt-28">
                <div class="teacher-header">
                    <h2>Report Student Indiscipline</h2>
                    <div class="teacher-reports-count" onclick="openMyReportsModal()">
                        My Reports: <span id="teacher_reports_count_display"><?php echo $teacher_reports_count; ?></span>
                    </div>
                </div>

                <div class="card student-lookup-section">
                    <h3>Student Lookup</h3>
                    <div class="form-group">
                        <label for="registration_number">Student Registration Number:</label>
                        <input type="text" id="registration_number" name="registration_number"
                               value="<?php echo htmlspecialchars($_POST['registration_number'] ?? ''); ?>"
                               placeholder="Enter student registration number" required
                               oninput="lookupStudent()">
                    </div>
                    <p id="student_lookup_status" class="message error" style="display: none;"></p>
                </div>

                <!-- This grid container will hold student info/history and the report form -->
                <div id="teacher_content_grid" style="display: none;">
                    <!-- Student Details & History Card -->
                    <div id="student_info_history_card" class="card student-details-and-history-card">
                        <h3>Student Details:</h3>
                        <div id="student_details_display">
                            <p><strong>Name:</strong> <span id="student_full_name"></span></p>
                            <p><strong>Class:</strong> <span id="student_class"></span></p>
                            <p><strong>Roll Number:</strong> <span id="student_roll_number"></span></p>
                        </div>
                        <hr style="margin: 15px 0; border-color: #c9d6e4;">
                        <div id="student_indiscipline_history">
                            <h4>Indiscipline History for this Student (Last 5):</h4>
                            <ul id="history_list">
                                <li>Enter registration number above to view history.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- New Indiscipline Report Form Card -->
                    <div id="report_form_section" class="card">
                        <h3>New Indiscipline Report</h3>
                        <form action="indiscipline.php" method="POST" class="form-grid">
                            <input type="hidden" name="action" value="submit_report">
                            <input type="hidden" name="reported_student_id" id="form_reported_student_id">

                            <div class="form-group">
                                <label for="class_id">Relevant Class (Optional):</label>
                                <select id="class_id" name="class_id">
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($all_classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="subject_id">Relevant Subject (Optional):</label>
                                <select id="subject_id" name="subject_id">
                                    <option value="">-- Select Subject --</option>
                                    <?php foreach ($all_subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="incident_date">Incident Date:</label>
                                <input type="date" id="incident_date" name="incident_date" value="<?php echo htmlspecialchars($_POST['incident_date'] ?? date('Y-m-d')); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="incident_time">Incident Time (Optional):</label>
                                <input type="time" id="incident_time" name="incident_time" value="<?php echo htmlspecialchars($_POST['incident_time'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="location">Location (Optional):</label>
                                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" placeholder="e.g., Classroom 5, Playground">
                            </div>

                            <div class="form-group">
                                <label for="severity">Severity:</label>
                                <select id="severity" name="severity" required>
                                    <option value="">-- Select Severity --</option>
                                    <option value="Minor" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'Minor') ? 'selected' : ''; ?>>Minor</option>
                                    <option value="Moderate" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'Moderate') ? 'selected' : ''; ?>>Moderate</option>
                                    <option value="Serious" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'Serious') ? 'selected' : ''; ?>>Serious</option>
                                    <option value="Critical" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'Critical') ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label for="description">Description of Incident:</label>
                                <textarea id="description" name="description" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label for="immediate_action_taken">Immediate Action Taken (Optional):</label>
                                <textarea id="immediate_action_taken" name="immediate_action_taken"><?php echo htmlspecialchars($_POST['immediate_action_taken'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label for="evidence_url">Evidence URL (Optional):</label>
                                <input type="text" id="evidence_url" name="evidence_url" value="<?php echo htmlspecialchars($_POST['evidence_url'] ?? ''); ?>" placeholder="Link to image, video, or document">
                            </div>

                            <div class="form-group full-width">
                                <button type="submit" class="submit-btn">Submit Report</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Teacher's My Reports Modal -->
            <div id="myReportsModal" class="modal">
                <div class="modal-content">
                    <span class="close-button" onclick="closeMyReportsModal()">&times;</span>
                    <h3>My Filed Reports</h3>
                    <div id="my_reports_list">
                        <p>Loading your reports...</p>
                    </div>
                </div>
            </div>

        <?php elseif ($user_role === 'admin'): ?>
            <h2>Manage Indiscipline Reports</h2>

            <form class="filter-form" action="indiscipline.php" method="GET">
                <div class="filter-group">
                    <label for="filter_status">Filter by Status:</label>
                    <select name="filter_status" id="filter_status">
                        <option value="All" <?php echo ($filter_status == 'All') ? 'selected' : ''; ?>>All</option>
                        <option value="Pending Review" <?php echo ($filter_status == 'Pending Review') ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="Reviewed - Warning Issued" <?php echo ($filter_status == 'Reviewed - Warning Issued') ? 'selected' : ''; ?>>Reviewed - Warning Issued</option>
                        <option value="Reviewed - Further Action" <?php echo ($filter_status == 'Reviewed - Further Action') ? 'selected' : ''; ?>>Reviewed - Further Action</option>
                        <option value="Reviewed - No Action" <?php echo ($filter_status == 'Reviewed - No Action') ? 'selected' : ''; ?>>Reviewed - No Action</option>
                        <option value="Closed" <?php echo ($filter_status == 'Closed') ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_target_type">Filter by Target Type:</label>
                    <select name="filter_target_type" id="filter_target_type">
                        <option value="All" <?php echo ($filter_target_type == 'All') ? 'selected' : ''; ?>>All</option>
                        <option value="Student" <?php echo ($filter_target_type == 'Student') ? 'selected' : ''; ?>>Student</option>
                        <option value="Teacher" <?php echo ($filter_target_type == 'Teacher') ? 'selected' : ''; ?>>Teacher</option>
                    </select>
                </div>
                <button type="submit" class="submit-btn">Apply Filters</button>
            </form>

            <?php if (empty($reports)): ?>
                <p class="message info">No indiscipline reports found matching the criteria.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reporter</th>
                                <th>Target</th>
                                <th>Incident Date</th>
                                <th>Location</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Final Action</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($report['id']); ?></td>
                                    <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                                    <td>
                                        <?php
                                            if ($report['target_type'] === 'Student') {
                                                $student_full_name = trim($report['student_first_name'] . ' ' . $report['student_middle_name'] . ' ' . $report['student_last_name']);
                                                echo 'Student: ' . htmlspecialchars($student_full_name . ' (Reg No: ' . $report['student_reg_no'] . ')');
                                            } elseif ($report['target_type'] === 'Teacher') {
                                                echo 'Teacher: ' . htmlspecialchars($report['reported_teacher_name'] ?: 'N/A');
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($report['incident_date']); ?></td>
                                    <td><?php echo htmlspecialchars($report['location'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($report['severity']); ?></td>
                                    <td class="status-<?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>">
                                        <?php echo htmlspecialchars($report['status']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($report['final_action_taken'] ?: 'N/A'); ?></td>
                                    <td>
                                        <button class="action-btn" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($report)); ?>)">Details & Review</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Admin Review Modal -->
            <div id="reviewModal" class="modal">
                <div class="modal-content">
                    <span class="close-button" onclick="closeReviewModal()">&times;</span>
                    <h3>Review Indiscipline Report <span id="modalReportId"></span></h3>
                    <div class="modal-details">
                        <p><strong>Reported By:</strong> <span id="detail_reporter_name"></span></p>
                        <p><strong>Target:</strong> <span id="detail_target_info"></span></p>
                        <p><strong>Incident Date:</strong> <span id="detail_incident_date"></span></p>
                        <p><strong>Incident Time:</strong> <span id="detail_incident_time"></span></p>
                        <p><strong>Location:</strong> <span id="detail_location"></span></p>
                        <p><strong>Relevant Class:</strong> <span id="detail_class"></span></p>
                        <p><strong>Relevant Subject:</strong> <span id="detail_subject"></span></p>
                        <p><strong>Severity:</strong> <span id="detail_severity"></span></p>
                        <p><strong>Description:</strong> <br><span id="detail_description"></span></p>
                        <p><strong>Immediate Action:</strong> <br><span id="detail_immediate_action_taken"></span></p>
                        <p><strong>Evidence:</strong> <span id="detail_evidence_url"></span></p>
                        <hr>
                        <p><strong>Current Status:</strong> <span id="detail_status_display"></span></p>
                        <p><strong>Reviewed By:</strong> <span id="detail_reviewer_name"></span></p>
                        <p><strong>Review Date:</strong> <span id="detail_review_date"></span></p>
                        <p><strong>Review Notes:</strong> <br><span id="detail_review_notes_display"></span></p>
                        <p><strong>Final Action:</strong> <br><span id="detail_final_action_display"></span></p>

                    </div>
                    <hr>
                    <form action="indiscipline.php" method="POST">
                        <input type="hidden" name="action" value="review_report">
                        <input type="hidden" name="report_id" id="review_report_id">

                        <div class="form-group">
                            <label for="modal_status">Update Status:</label>
                            <select id="modal_status" name="status" required>
                                <option value="Pending Review">Pending Review</option>
                                <option value="Reviewed - Warning Issued">Reviewed - Warning Issued</option>
                                <option value="Reviewed - Further Action">Reviewed - Further Action</option>
                                <option value="Reviewed - No Action">Reviewed - No Action</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="modal_review_notes">Reviewer's Notes:</label>
                            <textarea id="modal_review_notes" name="review_notes" placeholder="Add or update your review notes here..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="modal_final_action_taken">Final Action Taken:</label>
                            <textarea id="modal_final_action_taken" name="final_action_taken" placeholder="e.g., Student suspended for 3 days, Formal warning issued to teacher."></textarea>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="submit-btn">Save Review</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: // No recognized role ?>
            <p class="message error">Access denied or unrecognized user role.</p>
        <?php endif; ?>
    </main>

    

    <script>
        // Teacher specific JS for student lookup
        let studentLookupTimeout;

        function lookupStudent() {
            clearTimeout(studentLookupTimeout); // Clear previous timeout
            studentLookupTimeout = setTimeout(() => { // Set a new timeout (debounce)
                var regNo = document.getElementById('registration_number').value.trim();
                var teacherContentGrid = document.getElementById('teacher_content_grid'); // Main grid container
                var studentLookupStatus = document.getElementById('student_lookup_status');

                var studentFullName = document.getElementById('student_full_name');
                var studentClass = document.getElementById('student_class');
                var studentRollNumber = document.getElementById('student_roll_number');
                var historyList = document.getElementById('history_list');
                var formReportedStudentId = document.getElementById('form_reported_student_id');

                // --- Step 1: Reset all dynamic sections and feedback ---
                teacherContentGrid.style.display = 'none'; // Hide the entire content grid
                studentLookupStatus.style.display = 'none'; // Hide status message initially

                studentFullName.textContent = '';
                studentClass.textContent = '';
                studentRollNumber.textContent = '';
                formReportedStudentId.value = '';
                historyList.innerHTML = '<li>Enter registration number above to view history.</li>'; // Reset history message


                if (regNo.length > 0) {
                    studentLookupStatus.style.display = 'block';
                    studentLookupStatus.className = 'message info';
                    studentLookupStatus.textContent = 'Searching for student...';

                    fetch(`indiscipline.php?action=lookup_student_by_reg_no&reg_no=${encodeURIComponent(regNo)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.student && data.student.student_id) {
                                // --- Step 2: Student Found - Populate and display details & history ---
                                formReportedStudentId.value = data.student.student_id;
                                studentFullName.textContent = data.student.full_name;
                                studentClass.textContent = data.student.class_name || 'N/A';
                                studentRollNumber.textContent = data.student.roll_number || 'N/A';
                                
                                studentLookupStatus.style.display = 'none'; // Hide lookup status message
                                teacherContentGrid.style.display = 'grid'; // Show the entire content grid

                                if (data.history && data.history.length > 0) {
                                    historyList.innerHTML = ''; // Clear "No history"
                                    data.history.forEach((item, index) => {
                                        const li = document.createElement('li');
                                        li.textContent = `${index + 1}. ${item.incident_date} - ${item.severity}: ${item.description} (Status: ${item.status})`;
                                        historyList.appendChild(li);
                                    });
                                } else {
                                    historyList.innerHTML = '<li>No indiscipline history found for this student.</li>';
                                }
                                
                                // The report form is part of the same grid and will now be visible.

                            } else {
                                // Student not found
                                teacherContentGrid.style.display = 'none'; // Ensure content grid is hidden
                                studentLookupStatus.className = 'message error';
                                studentLookupStatus.textContent = 'Student not found or invalid registration number.';
                                studentLookupStatus.style.display = 'block';
                                formReportedStudentId.value = ''; // Ensure student ID is cleared
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching student details:', error);
                            teacherContentGrid.style.display = 'none'; // Ensure content grid is hidden
                            studentLookupStatus.className = 'message error';
                            studentLookupStatus.textContent = 'Error fetching student details. Please try again.';
                            studentLookupStatus.style.display = 'block';
                            formReportedStudentId.value = '';
                        });
                } else {
                     studentLookupStatus.style.display = 'none'; // Hide message if input is empty
                }
            }, 300); // Debounce time: 300ms
        }

        // --- Teacher's My Reports Modal Functions ---
        function openMyReportsModal() {
            document.getElementById('myReportsModal').style.display = 'block';
            const myReportsList = document.getElementById('my_reports_list');
            myReportsList.innerHTML = '<p class="message info">Loading your reports...</p>'; // Show loading message

            fetch(`indiscipline.php?action=get_teacher_reports`)
                .then(response => response.json())
                .then(reports => {
                    myReportsList.innerHTML = ''; // Clear loading message
                    if (reports.length > 0) {
                        reports.forEach(report => {
                            const studentFullName = [report.first_name, report.last_name].filter(Boolean).join(' ');
                            const reportItem = `
                                <div class="report-item">
                                    <p><strong>Report ID:</strong> ${report.id}</p>
                                    <p><strong>Student:</strong> ${studentFullName} (Reg No: ${report.registration_number})</p>
                                    <p><strong>Incident Date:</strong> ${report.incident_date}</p>
                                    <p><strong>Severity:</strong> ${report.severity}</p>
                                    <p><strong>Description:</strong> ${report.description.substring(0, 100)}...</p>
                                    <p><strong>Status:</strong> <span class="status-${report.status.toLowerCase().replace(/ /g, '-')}">${report.status}</span></p>
                                </div>
                            `;
                            myReportsList.insertAdjacentHTML('beforeend', reportItem);
                        });
                    } else {
                        myReportsList.innerHTML = '<p class="message info">You have not filed any reports yet.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching teacher reports:', error);
                    myReportsList.innerHTML = '<p class="message error">Error loading your reports. Please try again.</p>';
                });
        }

        function closeMyReportsModal() {
            document.getElementById('myReportsModal').style.display = 'none';
        }

        // Admin specific JS for review modal
        function openReviewModal(report) {
            document.getElementById('modalReportId').textContent = '#' + report.id;
            document.getElementById('review_report_id').value = report.id;

            // Populate view-only details
            document.getElementById('detail_reporter_name').textContent = report.reporter_name;
            let targetInfo = '';
            if (report.target_type === 'Student') {
                const studentFullName = [report.student_first_name, report.student_middle_name, report.student_last_name].filter(Boolean).join(' ');
                targetInfo = 'Student: ' + studentFullName + ' (Reg No: ' + report.student_reg_no + ')';
            } else if (report.target_type === 'Teacher') {
                targetInfo = 'Teacher: ' + (report.reported_teacher_name || 'N/A');
            }
            document.getElementById('detail_target_info').textContent = targetInfo;
            document.getElementById('detail_incident_date').textContent = report.incident_date;
            document.getElementById('detail_incident_time').textContent = report.incident_time || 'N/A';
            document.getElementById('detail_location').textContent = report.location || 'N/A';
            document.getElementById('detail_class').textContent = (report.class_name && report.section_name) ? `${report.class_name} - ${report.section_name}` : 'N/A';
            document.getElementById('detail_subject').textContent = report.subject_name || 'N/A';
            document.getElementById('detail_severity').textContent = report.severity;
            document.getElementById('detail_description').textContent = report.description;
            document.getElementById('detail_immediate_action_taken').textContent = report.immediate_action_taken || 'None';
            if (report.evidence_url) {
                document.getElementById('detail_evidence_url').innerHTML = `<a href="${report.evidence_url}" target="_blank">View Evidence</a>`;
            } else {
                document.getElementById('detail_evidence_url').textContent = 'None';
            }

            document.getElementById('detail_status_display').textContent = report.status;
            document.getElementById('detail_reviewer_name').textContent = report.reviewer_name || 'N/A';
            document.getElementById('detail_review_date').textContent = report.review_date ? new Date(report.review_date).toLocaleString() : 'N/A';
            document.getElementById('detail_review_notes_display').textContent = report.review_notes || 'None';
            document.getElementById('detail_final_action_display').textContent = report.final_action_taken || 'None';


            // Populate editable fields for review
            document.getElementById('modal_status').value = report.status;
            document.getElementById('modal_review_notes').value = report.review_notes || '';
            document.getElementById('modal_final_action_taken').value = report.final_action_taken || '';

            document.getElementById('reviewModal').style.display = 'block';
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
        }

        // Close ANY modal if user clicks outside of it
        window.onclick = function(event) {
            var reviewModal = document.getElementById('reviewModal');
            var myReportsModal = document.getElementById('myReportsModal');

            if (event.target == reviewModal) {
                reviewModal.style.display = "none";
            }
            if (event.target == myReportsModal) {
                myReportsModal.style.display = "none";
            }
        }
    </script>
</body>
</html>

<?php
require_once './teacher_footer.php';
?>