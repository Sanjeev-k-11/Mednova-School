<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

$student_id = $_SESSION['id'] ?? null; // Use 'id' for consistency with other files

if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    // Render an error message directly since we can't use the theme alerts yet
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Error</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body><div class="container mt-5"><div class="alert alert-danger" role="alert">
            <h4 class="alert-heading">Authentication Error!</h4>
            <p>Your student ID is missing or invalid in the session. Please log in again.</p>
            <hr><p class="mb-0"><a href="../login.php" class="alert-link">Click here to log in</a></p>
          </div></div></body></html>';
    // No need to include footer here as it's a full error page
    if($link) mysqli_close($link);
    exit();
}

$student_info = [];
$student_class_id = null;
$class_teacher_id = null; // To be fetched for submission
$applications = [];
$flash_message = '';
$flash_message_type = ''; // 'success', 'error', 'info'

// --- DATA FETCHING: Student Info (for greeting and class details) ---
$sql_student_info = "
    SELECT
        s.first_name, s.last_name, s.middle_name, s.class_id,
        c.class_name, c.section_name, c.teacher_id AS assigned_class_teacher_id
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id -- Use LEFT JOIN to avoid breaking if student has no class
    WHERE s.id = ?
";
if ($stmt = mysqli_prepare($link, $sql_student_info)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result_student_info = mysqli_stmt_get_result($stmt);
    $student_info_raw = mysqli_fetch_assoc($result_student_info);
    mysqli_stmt_close($stmt);

    if ($student_info_raw) {
        $student_info = $student_info_raw;
        $student_class_id = $student_info['class_id'];
        $class_teacher_id = $student_info['assigned_class_teacher_id']; // This is the ID of the class teacher
        // Provide defaults if class info is null due to LEFT JOIN
        $student_info['class_name'] = $student_info['class_name'] ?? 'N/A';
        $student_info['section_name'] = $student_info['section_name'] ?? 'Unassigned';
    } else {
        $flash_message = "Could not retrieve your student record. Please contact administration.";
        $flash_message_type = 'error';
        $student_info = [
            'first_name' => 'Unknown', 'last_name' => 'Student', 'middle_name' => '',
            'class_id' => null, 'class_name' => 'N/A', 'section_name' => 'Unassigned', 'assigned_class_teacher_id' => null
        ];
    }
} else {
    error_log("DB Prepare Student Info Error: " . mysqli_error($link));
    $flash_message = "Database error fetching student info.";
    $flash_message_type = 'error';
    $student_info = [
        'first_name' => 'Error', 'last_name' => 'Loading', 'middle_name' => '',
        'class_id' => null, 'class_name' => 'N/A', 'section_name' => 'Unassigned', 'assigned_class_teacher_id' => null
    ];
}


// --- HANDLE FORM SUBMISSION (New Leave Application) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($flash_message)) {
    $leave_from = filter_input(INPUT_POST, 'leave_from', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $leave_to = filter_input(INPUT_POST, 'leave_to', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $reason = trim(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_SPECIAL_CHARS));

    // Basic validation
    if (empty($leave_from) || empty($leave_to) || empty($reason)) {
        $flash_message = "Please fill in all required fields for your application.";
        $flash_message_type = 'error';
    } elseif ($student_class_id === null) {
        $flash_message = "Your class information is missing. Cannot submit application.";
        $flash_message_type = 'error';
    } elseif (strtotime($leave_from) > strtotime($leave_to)) {
        $flash_message = "'Leave From' date cannot be after 'Leave To' date.";
        $flash_message_type = 'error';
    } else {
        $sql_insert_application = "
            INSERT INTO leave_applications
            (student_id, class_id, class_teacher_id, leave_from, leave_to, reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        if ($stmt = mysqli_prepare($link, $sql_insert_application)) {
            mysqli_stmt_bind_param($stmt, "iiisss",
                $student_id,
                $student_class_id,
                $class_teacher_id, // Can be NULL if class has no assigned teacher
                $leave_from,
                $leave_to,
                $reason
            );
            if (mysqli_stmt_execute($stmt)) {
                $flash_message = "Your leave application has been submitted successfully! It is now Pending review.";
                $flash_message_type = 'success';
                // Clear form fields
                unset($_POST['leave_from']);
                unset($_POST['leave_to']);
                unset($_POST['reason']);
            } else {
                $flash_message = "Database error submitting application: " . mysqli_stmt_error($stmt);
                $flash_message_type = 'error';
                error_log("DB Insert Application Error: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            $flash_message = "Database error preparing application submission.";
            $flash_message_type = 'error';
            error_log("DB Prepare Application Error: " . mysqli_error($link));
        }
    }
}

// --- PAGINATION LOGIC ---
$records_per_page = 6; // Display 6 applications per page (2 rows in a 2-column grid)
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;
$total_applications_count = 0;

// --- DATA FETCHING: Student's Existing Applications ---
if ($student_id !== null) {
    // 1. Get total count for pagination
    $sql_count = "SELECT COUNT(la.id) FROM leave_applications la WHERE la.student_id = ?";
    if ($stmt_count = mysqli_prepare($link, $sql_count)) {
        mysqli_stmt_bind_param($stmt_count, "i", $student_id);
        mysqli_stmt_execute($stmt_count);
        mysqli_stmt_bind_result($stmt_count, $total_applications_count);
        mysqli_stmt_fetch($stmt_count);
        mysqli_stmt_close($stmt_count);
    } else {
        error_log("DB Prepare Applications Count Error: " . mysqli_error($link));
        $flash_message = "Database error fetching application count.";
        $flash_message_type = 'error';
    }

    $total_pages = ceil($total_applications_count / $records_per_page);

    // 2. Fetch applications for current page
    $sql_applications = "
        SELECT
            la.id,
            la.leave_from,
            la.leave_to,
            la.reason,
            la.status,
            la.reviewed_by,
            la.reviewed_at,
            la.created_at,
            t.full_name AS class_teacher_name_for_app
        FROM leave_applications la
        LEFT JOIN teachers t ON la.class_teacher_id = t.id
        WHERE la.student_id = ?
        ORDER BY la.created_at DESC
        LIMIT ?, ?;
    ";
    if ($stmt = mysqli_prepare($link, $sql_applications)) {
        mysqli_stmt_bind_param($stmt, "iii", $student_id, $offset, $records_per_page);
        mysqli_stmt_execute($stmt);
        $applications = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    } else {
        error_log("DB Prepare Applications List Error: " . mysqli_error($link));
        $flash_message = "Database error fetching your applications list.";
        $flash_message_type = 'error';
    }
} else {
    $flash_message = "Student ID not found in session, cannot load applications.";
    $flash_message_type = 'error';
}


mysqli_close($link);
require_once "./student_header.php"; // Assuming this includes the opening HTML and basic structure
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - Student Portal</title>
    <!-- Bootstrap CSS (Version 5.3) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter (Modern, clean font) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons (Version 6.4) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Keyframe animation for background gradient */
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

        /* CSS Variables for easier theme management - adapted from dashboard_academics */
        :root {
            --dashboard-primary: #a0522d; /* Dark Sienna / SaddleBrown */
            --dashboard-light-bg: #FFFDE7; /* Very light yellow */
            --dashboard-card-bg: rgba(255, 255, 255, 0.7); /* Translucent white for cards */
            --dashboard-card-border: rgba(255, 255, 255, 0.5); /* Lighter border for cards */
            --dashboard-card-shadow: 0 4px 15px rgba(0,0,0,0.1); /* Subtle shadow */
            --dashboard-card-hover-shadow: 0 8px 25px rgba(0,0,0,0.15); /* Stronger shadow on hover */
            --dashboard-text-dark: #333;
            --dashboard-text-muted: #666;
            --dashboard-icon-bg-orange: #ffecb3; /* Light orange for icons */
            --dashboard-link-bg-translucent: rgba(255, 255, 255, 0.4);
            --dashboard-link-hover-bg-translucent: rgba(255, 255, 255, 0.6);
            --dashboard-link-border-translucent: rgba(255,255,255,0.3);

            /* Status Badges */
            --status-pending-bg: #fff3cd; /* Light Yellow */
            --status-pending-text: #856404; /* Dark Yellow */
            --status-approved-bg: #d4edda; /* Light Green */
            --status-approved-text: #28a745; /* Green */
            --status-rejected-bg: #f8d7da; /* Light Red */
            --status-rejected-text: #dc3545; /* Red */

            /* Alert Colors */
            --alert-info-bg: #fff8e1; /* themed light yellow */
            --alert-info-text: var(--dashboard-primary);
            --alert-danger-bg: #ffe0e0; /* themed light red */
            --alert-danger-text: var(--status-rejected-text);
            --alert-success-bg: #d4edda; /* themed light green */
            --alert-success-text: var(--status-approved-text);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, var(--dashboard-light-bg), #FFF8E1, #FFECB3, #FFDDAA);
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            color: var(--dashboard-text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
            margin-top: 80px; /* To account for fixed header */
            margin-bottom: 100px;
        }

        .page-header {
            background: var(--dashboard-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--dashboard-card-shadow);
            border: 1px solid var(--dashboard-card-border);
            text-align: center;
        }

        .page-header h1 {
            font-weight: 700;
            color: var(--dashboard-primary);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            font-size: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .welcome-info-block {
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 0.5rem;
            display: inline-block;
            margin-top: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }

        .welcome-info {
            font-weight: 500;
            color: var(--dashboard-text-muted);
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        .welcome-info strong {
            color: var(--dashboard-text-dark);
        }

        .section-title {
            font-weight: 600;
            margin-top: 0; /* Removed for inner panel use */
            margin-bottom: 1.5rem; /* Consistent spacing */
            color: var(--dashboard-primary);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.05);
        }
        .section-title i {
            color: var(--dashboard-primary);
        }

        /* Dashboard Panel - for the main content cards */
        .dashboard-panel {
            background: var(--dashboard-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--dashboard-card-shadow);
            border: 1px solid var(--dashboard-card-border);
            height: 100%; /* Ensure panels in a grid have same height */
            display: flex;
            flex-direction: column;
        }

        /* Form Styling */
        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dashboard-text-dark);
            margin-bottom: 0.5rem;
        }
        .form-group input[type="date"],
        .form-group textarea {
            display: block;
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid rgba(0,0,0,0.15);
            border-radius: 0.5rem; /* Rounded corners */
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); /* Subtle inner shadow */
            transition: border-color 0.2s, box-shadow 0.2s;
            font-size: 0.9rem;
            color: var(--dashboard-text-dark);
            background-color: rgba(255,255,255,0.8);
        }
        .form-group input[type="date"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--dashboard-primary);
            box-shadow: 0 0 0 3px rgba(var(--dashboard-primary-rgb), 0.25); /* Themed focus ring */
            background-color: white;
        }
        .form-group textarea {
            resize: vertical;
        }
        .text-required {
            color: var(--status-rejected-text); /* Red for required fields */
            font-size: 0.85em;
            margin-left: 0.25rem;
        }
        .form-actions {
            margin-top: 2rem;
            text-align: right;
        }

        /* Themed Primary Button */
        .btn-primary-themed {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 0.8rem 2rem; /* Adjusted padding */
            border: none;
            box-shadow: var(--dashboard-card-shadow);
            text-align: center;
            font-size: 0.95rem; /* Slightly larger */
            font-weight: 600;
            border-radius: 9999px; /* full rounded */
            color: white;
            background-color: var(--dashboard-primary);
            transition: background-color 0.2s, transform 0.2s, box-shadow 0.2s;
            text-decoration: none; /* For anchor tags used as buttons */
        }
        .btn-primary-themed:hover {
            background-color: #8c4625; /* Darker shade */
            transform: translateY(-2px);
            box-shadow: var(--dashboard-card-hover-shadow);
            color: white;
        }

        /* Application List Item */
        .application-item {
            background-color: rgba(255,255,255,0.4);
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            height: 100%; /* Ensure items in a row have same height */
        }
        .application-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: var(--dashboard-primary);
        }
        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }
        .application-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dashboard-primary);
            margin-bottom: 0;
        }
        .application-body {
            flex-grow: 1;
            font-size: 0.9rem;
            color: var(--dashboard-text-dark);
            line-height: 1.7;
        }
        .application-body p {
            margin-bottom: 0.5rem;
        }
        .application-body span.font-semibold {
            color: var(--dashboard-primary);
        }
        .application-footer {
            font-size: 0.8rem;
            color: var(--dashboard-text-muted);
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0,0,0,0.08);
        }
        .application-footer p {
            margin-bottom: 0.25rem;
        }
        .application-footer .italic {
            font-style: italic;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .status-badge.Pending { background-color: var(--status-pending-bg); color: var(--status-pending-text); }
        .status-badge.Approved { background-color: var(--status-approved-bg); color: var(--status-approved-text); }
        .status-badge.Rejected { background-color: var(--status-rejected-bg); color: var(--status-rejected-text); }

        /* No Records Blocks */
        .no-records-block {
            text-align: center;
            padding: 2rem;
            border-radius: 0.75rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: #f8f8f8; /* Light gray for general info */
            border: 1px solid #e0e0e0;
            color: var(--dashboard-text-muted);
        }
        .no-records-block i {
            color: #b0b0b0;
            margin-bottom: 1rem;
        }
        .no-records-block p {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .no-records-block .sub-text {
            font-size: 1rem;
        }
        .no-records-block.alert-danger-themed {
            background-color: var(--alert-danger-bg);
            border-color: var(--status-rejected-text);
            color: var(--alert-danger-text);
        }
        .no-records-block.alert-danger-themed i {
            color: #ef9a9a; /* Lighter red */
        }

        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
            min-width: 250px;
        }
        .toast-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast-notification.bg-success-themed { background-color: var(--status-approved-text); }
        .toast-notification.bg-error-themed { background-color: var(--status-rejected-text); }
        .toast-notification.bg-info-themed { background-color: var(--dashboard-primary); }
        .toast-notification.bg-success-themed,
        .toast-notification.bg-error-themed,
        .toast-notification.bg-info-themed {
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }


        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            background: var(--dashboard-link-bg-translucent);
            backdrop-filter: blur(5px);
            color: var(--dashboard-primary);
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9em;
            transition: background 0.3s, transform 0.2s, box-shadow 0.3s;
            border: 1px solid var(--dashboard-link-border-translucent);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-top: 3rem; /* Space from content */
        }

        .back-link:hover {
            background: var(--dashboard-link-hover-bg-translucent);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            color: var(--dashboard-primary);
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding: 1rem 0;
            border-top: 1px solid rgba(0,0,0,0.08);
            color: var(--dashboard-text-muted);
            font-size: 0.9rem;
        }
        .pagination-info {
            flex-grow: 1;
            text-align: left;
            margin-bottom: 0.5rem; /* For mobile */
        }
        .pagination-controls {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
            gap: 0.5rem;
        }
        .pagination-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            color: var(--dashboard-text-dark);
            background-color: rgba(255,255,255,0.4);
            border: 1px solid rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        .pagination-link:hover:not(.active):not(.disabled) {
            background-color: rgba(255,255,255,0.6);
            border-color: var(--dashboard-primary);
            color: var(--dashboard-primary);
        }
        .pagination-link.active {
            background-color: var(--dashboard-primary);
            border-color: var(--dashboard-primary);
            color: white;
            font-weight: 600;
            cursor: default;
        }
        .pagination-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }


        /* Mobile responsiveness */
        @media (max-width: 767.98px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
                margin-top: 20px;
                margin-bottom: 50px;
            }
            .page-header {
                padding: 1.5rem;
                margin-top: 1rem;
            }
            .page-header h1 {
                font-size: 2rem;
                flex-direction: column;
                gap: 5px;
            }
            .welcome-info-block {
                width: 100%;
                text-align: center;
            }
            .section-title {
                font-size: 1.5rem;
                justify-content: center;
                text-align: center;
            }
            .dashboard-panel {
                padding: 1.5rem;
            }
            .form-actions {
                text-align: center;
            }
            .btn-primary-themed {
                width: 100%;
                padding: 0.7rem 1.5rem;
            }
            .application-item {
                padding: 1rem;
            }
            .application-header {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 0.75rem;
                padding-bottom: 0.75rem;
            }
            .application-header h3 {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }
            .status-badge {
                align-self: flex-start;
            }
            .application-body, .application-footer {
                font-size: 0.85rem;
            }
            .toast-notification {
                left: 10px;
                right: 10px;
                width: auto;
            }
            .pagination-container {
                flex-direction: column;
                align-items: center;
            }
            .pagination-info {
                text-align: center;
                margin-bottom: 1rem;
            }
            .pagination-controls {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-notification"></div>

    <!-- Page Header Section -->
    <header class="page-header">
        <h1 class="page-title">
            <i class="fas fa-file-alt"></i> My Applications
        </h1>
        <div class="welcome-info-block">
            <p class="welcome-info">
                Welcome, <strong>
                    <?php echo htmlspecialchars($student_info['first_name'] . ' ' . ($student_info['middle_name'] ? $student_info['middle_name'] . ' ' : '') . $student_info['last_name']); ?>
                </strong>!
                Your Class: <strong><?php echo htmlspecialchars($student_info['class_name'] . ' ' . $student_info['section_name']); ?></strong>
            </p>
        </div>
    </header>

    <div class="row g-4"> <!-- Using Bootstrap 5 grid with gap -->
        <!-- New Application Submission Form -->
        <div class="col-12 col-lg-4 d-flex">
            <div class="dashboard-panel">
                <h2 class="section-title">
                    <i class="fas fa-edit"></i> Submit New Application
                </h2>
                <?php if ($student_class_id === null): ?>
                    <div class="no-records-block alert-danger-themed flex-grow-1">
                        <i class="fas fa-exclamation-triangle fa-3x mb-4"></i>
                        <p>Cannot submit application.</p>
                        <p class="sub-text">Your class information is missing. Please contact administration.</p>
                    </div>
                <?php else: ?>
                    <form action="applications.php" method="POST">
                        <div class="form-group mb-4">
                            <label for="leave_from">Leave From <span class="text-required">*</span></label>
                            <input type="date" name="leave_from" id="leave_from" required
                                   value="<?php echo htmlspecialchars($_POST['leave_from'] ?? ''); ?>">
                        </div>
                        <div class="form-group mb-4">
                            <label for="leave_to">Leave To <span class="text-required">*</span></label>
                            <input type="date" name="leave_to" id="leave_to" required
                                   value="<?php echo htmlspecialchars($_POST['leave_to'] ?? ''); ?>">
                        </div>
                        <div class="form-group mb-6">
                            <label for="reason">Reason for Leave <span class="text-required">*</span></label>
                            <textarea name="reason" id="reason" rows="4" required
                                      placeholder="e.g., Family event, Sickness, etc."><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary-themed">
                                <i class="fas fa-paper-plane me-2"></i> Submit Application
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Applications List -->
        <div class="col-12 col-lg-8 d-flex">
            <div class="dashboard-panel">
                <h2 class="section-title">
                    <i class="fas fa-list-alt"></i> My Applications
                </h2>
                <?php if (empty($applications) && $total_applications_count == 0): // Check total count, not just current page ?>
                    <div class="no-records-block flex-grow-1">
                        <i class="fas fa-clipboard-list fa-4x mb-4"></i>
                        <p>No applications submitted yet!</p>
                        <p class="sub-text">Submit your first leave request using the form.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3"> <!-- Bootstrap grid for list items -->
                        <?php foreach ($applications as $app): ?>
                            <div class="col-12 col-md-6 d-flex">
                                <div class="application-item flex-grow-1">
                                    <div class="application-header">
                                        <h3>Leave Period</h3>
                                        <span class="status-badge <?php echo htmlspecialchars($app['status']); ?>">
                                            <?php echo htmlspecialchars($app['status']); ?>
                                        </span>
                                    </div>
                                    <div class="application-body">
                                        <p><span class="font-semibold">From:</span> <?php echo date('M d, Y', strtotime($app['leave_from'])); ?></p>
                                        <p><span class="font-semibold">To:</span> <?php echo date('M d, Y', strtotime($app['leave_to'])); ?></p>
                                        <p><span class="font-semibold">Reason:</span> <?php echo nl2br(htmlspecialchars($app['reason'])); ?></p>
                                    </div>
                                    <div class="application-footer">
                                        <p>Submitted: <?php echo date('M d, Y h:i A', strtotime($app['created_at'])); ?></p>
                                        <?php if ($app['reviewed_by']): ?>
                                            <p>Reviewed by: <span class="font-semibold"><?php echo htmlspecialchars($app['reviewed_by']); ?></span> on <?php echo date('M d, Y h:i A', strtotime($app['reviewed_at'])); ?></p>
                                        <?php elseif ($app['status'] == 'Pending'): ?>
                                            <p class="italic">Awaiting review from <?php echo htmlspecialchars($app['class_teacher_name_for_app'] ?: 'administration'); ?>.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_applications_count); ?> of <?php echo $total_applications_count; ?> records
                        </div>
                        <div class="pagination-controls">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i> Previous</a>
                            <?php else: ?>
                                <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1" class="pagination-link">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="pagination-link disabled">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?>" class="pagination-link <?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="pagination-link disabled">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '" class="pagination-link">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>" class="pagination-link">Next <i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="pagination-link disabled">Next <i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="text-center">
        <a href="student_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toastContainer = document.getElementById('toast-container');

        // --- Function to show Toast Notifications ---
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            
            let bgColorClass = '';
            let iconClass = '';
            if (type === 'success') {
                bgColorClass = 'bg-success-themed';
                iconClass = 'fas fa-check-circle';
            } else if (type === 'error') {
                bgColorClass = 'bg-error-themed';
                iconClass = 'fas fa-times-circle';
            } else { // info
                bgColorClass = 'bg-info-themed';
                iconClass = 'fas fa-info-circle';
            }

            toast.className = `toast-notification ${bgColorClass}`; // Apply base and type-specific class
            toast.innerHTML = `<i class="${iconClass} me-2"></i> ${message}`; // Use me-2 for margin-right
            
            toastContainer.appendChild(toast);
            
            // Show animation
            setTimeout(() => toast.classList.add('show'), 10);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => toast.remove());
            }, 5000);
        }

        // --- Display initial flash message from PHP (if any) ---
        <?php if ($flash_message): ?>
            showToast("<?php echo htmlspecialchars($flash_message); ?>", "<?php echo htmlspecialchars($flash_message_type); ?>");
        <?php endif; ?>
    });
</script>
</body>
</html>
<?php require_once "./student_footer.php"; ?>