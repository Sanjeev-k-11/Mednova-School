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

// Security Check: Ensure student_id is set in the session
if (!isset($_SESSION["id"])) { // Changed from student_id to id to match student_dashboard_academics.php
    // If student_id is missing, something is wrong with the session. Log out for safety.
    header("location: ../logout.php");
    exit;
}

// Get the logged-in student's ID
$student_id = $_SESSION["id"]; // Changed from student_id to id

$student_class_id = null;
$student_full_name = "";
$class_name = "";
$section_name = "";
$subjects = []; // Array to store fetched subjects
$error_message = ""; // To store general errors
$info_message = ""; // To store general info messages

// --- MODIFIED SQL QUERY ---
// Use LEFT JOIN to ensure student details are fetched even if they are not assigned to a class.
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

                // --- ADDED NULL HANDLING ---
                // Handle cases where a student might not be in a class (LEFT JOIN returns NULLs)
                $class_name = $c_name ?? 'N/A'; // Use null coalescing operator for clean code
                $section_name = $s_name ?? 'Unassigned';
            }
        } else {
            // This error now correctly means the student ID itself is not in the database.
            $error_message .= "Your student record could not be found. Please log out and try again, or contact support.";
        }
    } else {
        $error_message .= "Oops! Something went wrong fetching your details. Please try again later.";
    }
    mysqli_stmt_close($stmt_student);
} else {
    $error_message .= "Failed to prepare the SQL statement for student details.";
}

// If student_class_id was found and is not NULL, fetch the subjects for that class
if (!empty($student_class_id)) {
    // Corrected table name from class_subjects to class_subject_teacher based on common schema pattern in your applications
    $sql_subjects = "SELECT s.id, s.subject_name, s.subject_code 
                     FROM class_subject_teacher cst
                     JOIN subjects s ON cst.subject_id = s.id
                     WHERE cst.class_id = ?";

    if ($stmt_subjects = mysqli_prepare($link, $sql_subjects)) {
        mysqli_stmt_bind_param($stmt_subjects, "i", $param_class_id);
        $param_class_id = $student_class_id;
        if (mysqli_stmt_execute($stmt_subjects)) {
            $result_subjects = mysqli_stmt_get_result($stmt_subjects);
            if (mysqli_num_rows($result_subjects) > 0) {
                while ($row = mysqli_fetch_assoc($result_subjects)) {
                    $subjects[] = $row;
                }
            } else {
                // This info message is now more accurate.
                $info_message .= "No subjects have been assigned to your class yet. Please check back later or contact an administrator.";
            }
        } else {
            $error_message .= "Oops! Something went wrong fetching subjects. Please try again later.";
        }
        mysqli_stmt_close($stmt_subjects);
    } else {
        $error_message .= "Failed to prepare the SQL statement for subjects.";
    }
} else {
    // If there's no error yet, set an informational message about the missing class assignment.
    if (empty($error_message)) {
         $info_message .= "You are not currently assigned to a class, so no subjects can be displayed. Please contact an administrator for assistance.";
    }
}

mysqli_close($link);

// IMPORTANT: Do NOT change the following require_once calls as per instructions.
require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Student Portal</title>
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, var(--dashboard-light-bg), #FFF8E1, #FFECB3, #FFDDAA); /* Academic: Light Yellow/Creamy */
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            color: var(--dashboard-text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px; /* Slightly wider than original, fits dashboard feel */
            margin: auto;
            padding: 20px;
            margin-top: 80px; /* To account for fixed header */
            margin-bottom: 100px; /* For good bottom spacing */
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
            background: rgba(255, 255, 255, 0.5); /* Lighter background for this inner block */
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
            text-shadow: 1px 1px 2px rgba(0,0,0,0.05); /* Lighter text shadow for sections */
        }
        .section-title i {
            color: var(--dashboard-primary);
        }

        .subject-display-item {
            background: var(--dashboard-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem 2rem;
            margin-bottom: 1rem;
            box-shadow: var(--dashboard-card-shadow);
            border: 1px solid var(--dashboard-card-border);
            transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .subject-display-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--dashboard-card-hover-shadow);
            border-color: var(--dashboard-primary); /* Highlight border on hover */
        }

        .subject-icon {
            font-size: 1.8rem; /* Slightly smaller for subject list than summary card */
            width: 50px; /* Adjusted size */
            height: 50px; /* Adjusted size */
            border-radius: 50%;
            background: var(--dashboard-icon-bg-orange); /* Matching dashboard icon background */
            color: var(--dashboard-primary); /* Matching dashboard icon color */
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            min-width: 50px;
            flex-shrink: 0;
        }

        .subject-details {
            flex-grow: 1;
        }

        .subject-details h5 {
            font-weight: 600;
            color: var(--dashboard-primary);
            margin-bottom: 0.25rem;
            font-size: 1.25rem;
        }

        .subject-details p {
            color: var(--dashboard-text-muted);
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        .subject-details strong {
            color: var(--dashboard-text-dark);
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
            color: var(--dashboard-primary); /* Keep text color consistent on hover */
        }
        
        /* Custom Alerts for consistent styling with dashboard theme */
        .alert-info-custom {
            background-color: #fff8e1; /* Lighter, creamy yellow */
            color: var(--dashboard-primary);
            border-left: 5px solid var(--dashboard-primary);
            border-radius: 0.75rem; /* Match card radius */
            padding: 1.5rem;
            box-shadow: var(--dashboard-card-shadow);
        }
        .alert-danger-custom {
            border-left: 5px solid #dc3545; /* Red accent */
            background-color: #ffe0e0; /* Very light red background */
            color: #dc3545; /* Red text */
            border-radius: 0.75rem; /* Match card radius */
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
            .subject-display-item {
                flex-direction: column;
                align-items: flex-start;
                padding: 1.25rem;
                text-align: left;
            }
            .subject-icon {
                margin-bottom: 1rem;
                margin-right: 0;
            }
            .back-link {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header Section -->
        <header class="page-header">
            <h1 class="page-title">
                <i class="fas fa-graduation-cap"></i> My Courses
            </h1>
            <div class="welcome-info-block">
                <p class="welcome-info">
                    Welcome, <strong><?php echo htmlspecialchars($student_full_name); ?></strong>!
                    You are currently enrolled in Class: <strong><?php echo htmlspecialchars($class_name . ' ' . $section_name); ?></strong>
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
                <i class="fas fa-book-open"></i> Enrolled Subjects
            </h2>

            <?php if (empty($error_message) && !empty($subjects)): ?>
                <div class="subject-list">
                    <?php foreach ($subjects as $subject): ?>
                        <div class="subject-display-item">
                            <i class="subject-icon fas fa-book"></i> <!-- Icon for individual subject -->
                            <div class="subject-details">
                                <h5><?php echo htmlspecialchars($subject['subject_name']); ?></h5>
                                <p>Subject Code: <strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php if (!empty($info_message)): // Display info message if there are no subjects or class assignment ?>
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
</body>
</html>

<?php
// IMPORTANT: Do NOT change the following require_once call as per instructions.
require_once './student_footer.php';
?>