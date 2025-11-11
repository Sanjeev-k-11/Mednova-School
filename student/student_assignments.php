<?php
// Start the session
session_start();

// Include database configuration file
require_once '../database/config.php'; // Adjust path if config.php is in a different location

// Check if the student is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php"); // Adjust path to your login page
    exit;
}

// Security Check: Ensure student ID is set in the session
// Assuming 'id' is the primary key for students and is stored in session
if (!isset($_SESSION["id"])) {
    // If student_id is missing, something is wrong with the session. Log out for safety.
    header("location: ../logout.php");
    exit;
}

// Get the logged-in student's ID
$student_id = $_SESSION["id"]; // Consistent with student_dashboard_academics.php

$student_class_id = null;
$student_full_name = "";
$class_name = "";
$section_name = "";
$assignments = []; // Array to store fetched assignments
$error_message = ""; // To store general errors
$info_message = ""; // To store general info messages

// Prepare a statement to get student's class_id and full name
// Changed JOIN to LEFT JOIN to gracefully handle students not assigned to a class
$sql_student = "SELECT s.first_name, s.middle_name, s.last_name, s.class_id, c.class_name, c.section_name 
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
                WHERE s.id = ?";

if ($stmt_student = mysqli_prepare($link, $sql_student)) {
    mysqli_stmt_bind_param($stmt_student, "i", $param_student_id);
    $param_student_id = $student_id;

    if (mysqli_stmt_execute($stmt_student)) {
        mysqli_stmt_store_result($stmt_student);

        if (mysqli_stmt_num_rows($stmt_student) == 1) {
            mysqli_stmt_bind_result($stmt_student, $first_name, $middle_name, $last_name, $class_id, $c_name, $s_name);
            if (mysqli_stmt_fetch($stmt_student)) {
                $student_class_id = $class_id;
                $student_full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
                // Handle cases where a student might not be in a class (LEFT JOIN returns NULLs)
                $class_name = $c_name ?? 'N/A';
                $section_name = $s_name ?? 'Unassigned';
            }
        } else {
            // Student record not found (shouldn't happen if $_SESSION["id"] is valid)
            $error_message .= "Your student record could not be found. Please log out and try again, or contact support.";
        }
    } else {
        $error_message .= "Oops! Something went wrong fetching your details. Please try again later.";
    }
    mysqli_stmt_close($stmt_student);
} else {
    $error_message .= "Failed to prepare the SQL statement for student details.";
}

// If student_class_id is found and not null, fetch the assignments for that class
if ($student_class_id) {
    $sql_assignments = "SELECT a.id, a.title, a.description, a.due_date, a.file_url,
                               sub.subject_name, t.full_name AS teacher_name
                        FROM assignments a
                        JOIN subjects sub ON a.subject_id = sub.id
                        JOIN teachers t ON a.teacher_id = t.id
                        WHERE a.class_id = ?
                        ORDER BY a.due_date ASC, a.created_at DESC";

    if ($stmt_assignments = mysqli_prepare($link, $sql_assignments)) {
        mysqli_stmt_bind_param($stmt_assignments, "i", $param_class_id);
        $param_class_id = $student_class_id;

        if (mysqli_stmt_execute($stmt_assignments)) {
            $result_assignments = mysqli_stmt_get_result($stmt_assignments);
            if (mysqli_num_rows($result_assignments) > 0) {
                while ($row = mysqli_fetch_assoc($result_assignments)) {
                    $assignments[] = $row;
                }
            } else {
                $info_message .= "No assignments have been posted for your class yet. Keep up the good work!";
            }
        } else {
            $error_message .= "Oops! Something went wrong fetching assignments. Please try again later.";
        }
        mysqli_stmt_close($stmt_assignments);
    } else {
        $error_message .= "Failed to prepare the SQL statement for assignments.";
    }
} else {
    // If student_class_id is null, it means student is not assigned to a class
    if (empty($error_message)) { // Only add if no other error is present
        $info_message .= "You are not currently assigned to a class, so no assignments can be displayed. Please contact an administrator for assistance.";
    }
}

// IMPORTANT: Do NOT change the following require_once calls as per instructions.
require_once './student_header.php';

// Close connection
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - Student Portal</title>
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

            --badge-overdue-bg: #dc3545; /* Bootstrap danger red */
            --badge-overdue-text: #ffffff;
            --badge-due-soon-bg: #ffc107; /* Bootstrap warning yellow */
            --badge-due-soon-text: #212529;
            --badge-upcoming-bg: #17a2b8; /* Bootstrap info blue */
            --badge-upcoming-text: #ffffff;
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
            margin-top: 3rem;
            margin-bottom: 2rem;
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

        .assignment-card {
            background: var(--dashboard-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem; /* Space between cards */
            box-shadow: var(--dashboard-card-shadow);
            border: 1px solid var(--dashboard-card-border);
            transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
            height: 100%; /* Ensure cards in a row have same height */
            display: flex;
            flex-direction: column;
        }

        .assignment-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--dashboard-card-hover-shadow);
            border-color: var(--dashboard-primary);
        }
        
        .assignment-card.border-danger-custom { /* For overdue assignments */
            border-color: var(--badge-overdue-bg) !important;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.15); /* Reddish shadow */
        }

        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Align title and badge to the top */
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05); /* Subtle separator */
            padding-bottom: 0.75rem;
        }

        .assignment-header h5 {
            font-weight: 600;
            color: var(--dashboard-primary);
            margin-bottom: 0;
            font-size: 1.25rem;
            flex-grow: 1; /* Allow title to take space */
            padding-right: 10px; /* Space from badge */
        }

        .assignment-badge {
            display: inline-block;
            padding: 0.4em 0.7em;
            font-size: 0.8em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.3rem;
        }
        .assignment-badge.badge-overdue {
            background-color: var(--badge-overdue-bg);
            color: var(--badge-overdue-text);
        }
        .assignment-badge.badge-due-soon {
            background-color: var(--badge-due-soon-bg);
            color: var(--badge-due-soon-text);
        }
        .assignment-badge.badge-upcoming {
            background-color: var(--badge-upcoming-bg);
            color: var(--badge-upcoming-text);
        }

        .assignment-body {
            flex-grow: 1; /* Allow body to grow and push actions to bottom */
            padding-top: 0.5rem;
        }

        .assignment-description {
            font-size: 0.9em;
            color: var(--dashboard-text-muted);
            margin-bottom: 1rem;
        }

        .assignment-info p {
            margin-bottom: 0.5rem;
            font-size: 0.9em;
            color: var(--dashboard-text-dark);
        }
        .assignment-info p strong {
            color: var(--dashboard-primary); /* Highlight labels */
        }
        .assignment-info .due-date {
            font-weight: bold;
            color: var(--badge-overdue-bg); /* Use overdue red for actual date */
        }
        .assignment-info .due-date.upcoming {
            color: var(--dashboard-primary); /* Less urgent color for upcoming */
        }

        .assignment-actions {
            margin-top: 1rem;
            border-top: 1px solid rgba(0,0,0,0.05);
            padding-top: 1rem;
        }

        .btn-download {
            background-color: var(--dashboard-primary);
            border-color: var(--dashboard-primary);
            color: white;
            transition: background-color 0.3s, border-color 0.3s, transform 0.2s;
            font-weight: 500;
            border-radius: 0.5rem;
            padding: 0.6rem 1.2rem;
            font-size: 0.9rem;
        }
        .btn-download:hover {
            background-color: #8c4625; /* Darker shade of primary */
            border-color: #8c4625;
            color: white;
            transform: translateY(-2px);
        }
        .text-no-file {
            color: var(--dashboard-text-muted);
            font-style: italic;
            font-size: 0.85em;
        }

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
        }

        .back-link:hover {
            background: var(--dashboard-link-hover-bg-translucent);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            color: var(--dashboard-primary);
        }
        
        /* Custom Alerts for consistent styling with dashboard theme */
        .alert-info-custom {
            background-color: #fff8e1;
            color: var(--dashboard-primary);
            border-left: 5px solid var(--dashboard-primary);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--dashboard-card-shadow);
        }
        .alert-danger-custom {
            border-left: 5px solid #dc3545;
            background-color: #ffe0e0;
            color: #dc3545;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--dashboard-card-shadow);
        }
        .alert-heading {
            color: inherit;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        .alert p {
            margin-bottom: 0;
        }
        .alert i {
            margin-right: 10px;
            font-size: 1.2em;
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
                margin-top: 2rem;
                justify-content: center;
                text-align: center;
            }
            .assignment-card {
                padding: 1rem;
            }
            .assignment-header {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 0.5rem;
                padding-bottom: 0.5rem;
            }
            .assignment-header h5 {
                font-size: 1.1rem;
                margin-bottom: 0.5rem;
            }
            .assignment-badge {
                align-self: flex-start;
            }
            .btn-download {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header Section -->
        <header class="page-header">
            <h1 class="page-title">
                <i class="fas fa-clipboard-list"></i> My Assignments
            </h1>
            <div class="welcome-info-block">
                <p class="welcome-info">
                    Welcome, <strong><?php echo htmlspecialchars($student_full_name); ?></strong>!
                    Your Class: <strong><?php echo htmlspecialchars($class_name . ' ' . $section_name); ?></strong>
                </p>
            </div>
        </header>

        <main class="main-content-area">
            <?php
            // Display general error messages (using custom alert-danger-custom class)
            if (!empty($error_message)) {
                echo '<div class="alert alert-danger-custom mb-4" role="alert"><h4 class="alert-heading"><i class="fas fa-times-circle"></i> Error!</h4><p>' . htmlspecialchars($error_message) . '</p></div>';
            }
            ?>

            <h2 class="section-title">
                <i class="fas fa-tasks"></i> All Assignments
            </h2>

            <?php if (empty($error_message) && !empty($assignments)): ?>
                <div class="row">
                    <?php foreach ($assignments as $assignment):
                        $due_timestamp = strtotime($assignment['due_date']);
                        $today_timestamp = strtotime(date('Y-m-d'));
                        $seven_days_from_now = strtotime('+7 days', $today_timestamp);

                        $is_overdue = ($due_timestamp < $today_timestamp);
                        $is_due_soon = (!$is_overdue && $due_timestamp <= $seven_days_from_now);

                        $card_border_class = '';
                        $due_date_class = 'upcoming'; // Default for upcoming
                        $badge_html = '';

                        if ($is_overdue) {
                            $card_border_class = 'border-danger-custom';
                            $badge_html = '<span class="assignment-badge badge-overdue"><i class="fas fa-exclamation-circle me-1"></i> Overdue</span>';
                            $due_date_class = ''; // Remove upcoming class to allow overdue color
                        } elseif ($is_due_soon) {
                            $badge_html = '<span class="assignment-badge badge-due-soon"><i class="fas fa-clock me-1"></i> Due Soon</span>';
                            // $due_date_class remains 'upcoming'
                        } else {
                            $badge_html = '<span class="assignment-badge badge-upcoming"><i class="fas fa-calendar-alt me-1"></i> Upcoming</span>';
                            // $due_date_class remains 'upcoming'
                        }
                    ?>
                        <div class="col-md-6 col-lg-4 d-flex">
                            <div class="assignment-card <?php echo $card_border_class; ?>">
                                <div class="assignment-header">
                                    <h5><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                    <?php echo $badge_html; ?>
                                </div>
                                <div class="assignment-body">
                                    <p class="assignment-description"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                                    <div class="assignment-info">
                                        <p><strong>Subject:</strong> <?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                                        <p><strong>Assigned by:</strong> <?php echo htmlspecialchars($assignment['teacher_name']); ?></p>
                                        <p><strong>Due Date:</strong>
                                            <span class="due-date <?php echo $due_date_class; ?>">
                                                <?php echo date('F j, Y', $due_timestamp); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="assignment-actions">
                                    <?php if (!empty($assignment['file_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($assignment['file_url']); ?>" target="_blank" class="btn btn-download btn-block">
                                            <i class="fas fa-download me-2"></i> View/Download
                                        </a>
                                    <?php else: ?>
                                        <p class="text-center text-no-file">No file attached</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php if (!empty($info_message)): ?>
                    <div class="alert alert-info-custom p-4" role="alert">
                        <h4 class="alert-heading"><i class="fas fa-info-circle"></i> Information</h4>
                        <p><?php echo htmlspecialchars($info_message); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="text-center mt-5">
                <a href="student_dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome JS (if not already included by header/footer) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>

<?php
// IMPORTANT: Do NOT change the following require_once call as per instructions.
require_once './student_footer.php';
?>