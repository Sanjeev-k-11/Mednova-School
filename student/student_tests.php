<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

// Ensure student ID is set in the session. Using 'id' for consistency.
if (!isset($_SESSION["id"])) {
    header("location: ../logout.php");
    exit;
}
$student_id = $_SESSION["id"];
$student_class_id = $_SESSION["class_id"] ?? null; // Prefer session value, or null if not set

// Fetch student's full name, class name, and class_id for display and operations
$student_full_name = "Student"; // Default
$class_name = "N/A";
$section_name = "Unassigned";

$sql_get_student_info = "SELECT s.first_name, s.middle_name, s.last_name, s.class_id, c.class_name, c.section_name 
                         FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?";
if ($stmt = mysqli_prepare($link, $sql_get_student_info)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $first_name, $middle_name, $last_name, $db_class_id, $c_name, $s_name);
    if (mysqli_stmt_fetch($stmt)) {
        $student_full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
        $student_class_id = $db_class_id; // Update class_id from DB in case session was outdated/missing
        $class_name = $c_name ?? 'N/A';
        $section_name = $s_name ?? 'Unassigned';
    }
    mysqli_stmt_close($stmt);
}

// If class_id is still not set, error out.
if (!$student_class_id) {
    // For this page, an info message is probably better than immediate logout
    $info_message = "You are not currently assigned to a class, so online tests may not be available. Please contact an administrator.";
}

// --- HANDLE POST ACTIONS ---
$success_message = "";
$error_message = "";

// 1. START A NEW TEST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['start_test'])) {
    $test_id = $_POST['test_id'];
    
    // Security check: ensure student hasn't already attempted this test and it's published for their class
    $sql_check = "SELECT sta.id 
                  FROM student_test_attempts sta 
                  JOIN online_tests ot ON sta.test_id = ot.id
                  WHERE sta.test_id = ? AND sta.student_id = ? AND ot.class_id = ? AND ot.status = 'Published'";
    $stmt_check = mysqli_prepare($link, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "iii", $test_id, $student_id, $student_class_id);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);

    if (mysqli_stmt_num_rows($stmt_check) == 0) {
        // Fetch test details to get total marks and time limit
        $sql_test_info = "SELECT ot.time_limit_minutes, SUM(otq.marks) as total_marks 
                          FROM online_tests ot 
                          JOIN online_test_questions otq ON ot.id = otq.test_id 
                          WHERE ot.id = ? AND ot.class_id = ? AND ot.status = 'Published'
                          GROUP BY ot.id";
        $stmt_info = mysqli_prepare($link, $sql_test_info);
        mysqli_stmt_bind_param($stmt_info, "ii", $test_id, $student_class_id);
        mysqli_stmt_execute($stmt_info);
        mysqli_stmt_bind_result($stmt_info, $time_limit, $total_marks);
        mysqli_stmt_fetch($stmt_info);
        mysqli_stmt_close($stmt_info);

        if ($total_marks !== null) { // Ensure test has questions
            // Create a new attempt record
            $sql_start = "INSERT INTO student_test_attempts (test_id, student_id, start_time, total_marks, status) VALUES (?, ?, NOW(), ?, 'In Progress')";
            if ($stmt_start = mysqli_prepare($link, $sql_start)) {
                mysqli_stmt_bind_param($stmt_start, "iii", $test_id, $student_id, $total_marks);
                if (mysqli_stmt_execute($stmt_start)) {
                    header("location: student_tests.php?take_test=" . $test_id); // Redirect to the test view
                    exit;
                } else {
                    $error_message = "Error starting the test: " . mysqli_error($link);
                }
            } else {
                $error_message = "Failed to prepare test start statement.";
            }
        } else {
            $error_message = "This test is not properly configured or has no questions.";
        }
    } else {
        $error_message = "You have already attempted this test or it's not available.";
    }
    mysqli_stmt_close($stmt_check);
}

// 2. SUBMIT A COMPLETED TEST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_test'])) {
    $test_id = $_POST['test_id'];
    $attempt_id = $_POST['attempt_id'];
    $answers = $_POST['answers'] ?? [];
    
    // Fetch correct answers and marks for scoring, ensuring it's the correct test
    $sql_questions = "SELECT otq.id, otq.correct_option, otq.marks 
                      FROM online_test_questions otq 
                      JOIN online_tests ot ON otq.test_id = ot.id
                      WHERE otq.test_id = ? AND ot.class_id = ?";
    $stmt_q = mysqli_prepare($link, $sql_questions);
    mysqli_stmt_bind_param($stmt_q, "ii", $test_id, $student_class_id);
    mysqli_stmt_execute($stmt_q);
    $result_q = mysqli_stmt_get_result($stmt_q);
    $correct_answers = [];
    while($row = mysqli_fetch_assoc($result_q)) {
        $correct_answers[$row['id']] = ['correct' => $row['correct_option'], 'marks' => $row['marks']];
    }
    mysqli_stmt_close($stmt_q);
    
    // Calculate score
    $score = 0;
    foreach ($answers as $question_id => $student_answer) {
        if (isset($correct_answers[$question_id]) && $correct_answers[$question_id]['correct'] === $student_answer) {
            $score += $correct_answers[$question_id]['marks'];
        }
    }

    // Update the attempt with the score and mark as completed
    $answers_json = json_encode($answers);
    $sql_submit = "UPDATE student_test_attempts SET end_time = NOW(), score = ?, status = 'Completed', student_answers_json = ? 
                   WHERE id = ? AND test_id = ? AND student_id = ? AND status = 'In Progress'"; // Ensure only 'In Progress' attempts are completed
    if ($stmt_submit = mysqli_prepare($link, $sql_submit)) {
        mysqli_stmt_bind_param($stmt_submit, "isiii", $score, $answers_json, $attempt_id, $test_id, $student_id);
        if (mysqli_stmt_execute($stmt_submit)) {
            $success_message = "Test submitted successfully!";
        } else {
            $error_message = "Error submitting test: " . mysqli_error($link);
        }
    } else {
        $error_message = "Failed to prepare test submission statement.";
    }
    
    header("location: student_tests.php?view=results&message=" . urlencode($success_message ?: $error_message)); // Redirect to the results tab
    exit;
}


// --- DETERMINE CURRENT VIEW (List, Take Test, or Results) ---
$test_to_take_id = $_GET['take_test'] ?? null;
$test_data = null;
$questions = [];

if ($test_to_take_id) {
    // --- DATA FOR "TAKE TEST" VIEW ---
    $sql_test = "SELECT ot.title, ot.time_limit_minutes, sta.id as attempt_id, sta.start_time
                 FROM online_tests ot
                 JOIN student_test_attempts sta ON ot.id = sta.test_id
                 WHERE ot.id = ? AND sta.student_id = ? AND sta.status = 'In Progress' AND ot.class_id = ?";
    if ($stmt = mysqli_prepare($link, $sql_test)) {
        mysqli_stmt_bind_param($stmt, "iii", $test_to_take_id, $student_id, $student_class_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $test_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($test_data) {
            // Fetch questions for this test
            $sql_q = "SELECT id, question_text, option_a, option_b, option_c, option_d FROM online_test_questions WHERE test_id = ?";
            $stmt_q = mysqli_prepare($link, $sql_q);
            mysqli_stmt_bind_param($stmt_q, "i", $test_to_take_id);
            mysqli_stmt_execute($stmt_q);
            $result_q = mysqli_stmt_get_result($stmt_q);
            $questions = mysqli_fetch_all($result_q, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt_q);
        } else {
            $error_message = "The test you are trying to take is not active or does not exist for you.";
            $test_to_take_id = null; // Reset to show list view
        }
    } else {
        $error_message = "Failed to prepare statement for taking test.";
        $test_to_take_id = null;
    }
} else {
    // --- DATA FOR "LISTING" VIEW ---
    // Fetch tests available for the student's class they haven't attempted AND are published
    $available_tests = [];
    if ($student_class_id) {
        $sql_available = "SELECT ot.id, ot.title, s.subject_name, ot.time_limit_minutes
                          FROM online_tests ot
                          JOIN subjects s ON ot.subject_id = s.id
                          WHERE ot.class_id = ? AND ot.status = 'Published'
                          AND ot.id NOT IN (SELECT test_id FROM student_test_attempts WHERE student_id = ?)";
        if ($stmt_avail = mysqli_prepare($link, $sql_available)) {
            mysqli_stmt_bind_param($stmt_avail, "ii", $student_class_id, $student_id);
            mysqli_stmt_execute($stmt_avail);
            $result_avail = mysqli_stmt_get_result($stmt_avail);
            $available_tests = mysqli_fetch_all($result_avail, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt_avail);
        } else {
            $error_message .= "Failed to prepare statement for available tests.";
        }
    } else {
        // Handled by $info_message earlier
    }

    // Fetch completed tests for the results tab
    $completed_tests = [];
    $sql_completed = "SELECT ot.title, s.subject_name, sta.score, sta.total_marks, sta.end_time
                      FROM student_test_attempts sta
                      JOIN online_tests ot ON sta.test_id = ot.id
                      JOIN subjects s ON ot.subject_id = s.id
                      WHERE sta.student_id = ? AND sta.status = 'Completed'
                      ORDER BY sta.end_time DESC";
    if ($stmt_comp = mysqli_prepare($link, $sql_completed)) {
        mysqli_stmt_bind_param($stmt_comp, "i", $student_id);
        mysqli_stmt_execute($stmt_comp);
        $result_comp = mysqli_stmt_get_result($stmt_comp);
        $completed_tests = mysqli_fetch_all($result_comp, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_comp);
    } else {
        $error_message .= "Failed to prepare statement for completed tests.";
    }
}

// Handle messages from redirection
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    if (strpos($message, 'Error') === 0 || strpos($message, 'Failed') === 0) {
        $error_message .= $message;
    } else {
        $success_message .= $message;
    }
}


require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Tests - Student Portal</title>
    <!-- Bootstrap CSS (Version 5.3) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter (Modern, clean font) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons (Version 6.4) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js for interactive elements -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

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

            --success-color: #28a745; /* Bootstrap green for success */
            --danger-color: #dc3545; /* Bootstrap red for danger/overdue */
            --info-color: #17a2b8; /* Bootstrap info blue */
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

        /* General Card Styling for content blocks */
        .dashboard-panel {
            background: var(--dashboard-card-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: var(--dashboard-card-shadow);
            border: 1px solid var(--dashboard-card-border);
        }
        .dashboard-panel-padding {
             padding: 2rem;
        }

        /* Tab Navigation */
        .dashboard-tabs {
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .dashboard-tabs nav {
            display: flex;
            gap: 1.5rem; /* Equivalent to gap-6 */
            padding-left: 1.5rem; /* Equivalent to px-6 */
            padding-right: 1.5rem; /* Equivalent to px-6 */
        }
        .dashboard-tabs a {
            white-space: nowrap;
            padding: 1rem 0.25rem; /* Equivalent to py-4 px-1 */
            border-bottom: 2px solid transparent;
            font-weight: 500; /* Medium */
            font-size: 0.875rem; /* text-sm */
            color: var(--dashboard-text-muted);
            transition: color 0.2s, border-color 0.2s;
            text-decoration: none;
        }
        .dashboard-tabs a:hover {
            color: var(--dashboard-text-dark);
        }
        .dashboard-tabs a.active-tab { /* Alpine.js will add this class */
            border-color: var(--dashboard-primary);
            color: var(--dashboard-primary);
            font-weight: 600; /* Semibold */
        }

        /* Available Tests List Item */
        .test-list-item {
            padding: 1rem;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
            margin-bottom: 1rem; /* Spacing for items */
            background-color: rgba(255,255,255,0.4); /* Lighter background for list items */
        }
        .test-list-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-color: var(--dashboard-primary);
        }
        .test-list-item h3 {
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--dashboard-primary);
            margin-bottom: 0.25rem;
        }
        .test-list-item p {
            font-size: 0.875rem;
            color: var(--dashboard-text-muted);
            margin-bottom: 0;
        }

        /* General Button Styling (Primary themed) */
        .btn-primary-themed {
            background-color: var(--dashboard-primary);
            color: white;
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            transition: background-color 0.2s, transform 0.2s;
            text-decoration: none; /* For anchor tags */
        }
        .btn-primary-themed:hover {
            background-color: #8c4625; /* Darker shade */
            transform: translateY(-2px);
            color: white; /* Ensure text color stays white on hover */
        }
        .btn-secondary-themed { /* For Previous button */
            background-color: #e0e0e0;
            color: var(--dashboard-text-dark);
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            transition: background-color 0.2s, transform 0.2s;
            text-decoration: none;
        }
        .btn-secondary-themed:hover {
            background-color: #cccccc;
            transform: translateY(-2px);
            color: var(--dashboard-text-dark);
        }
        .btn-secondary-themed:disabled, .btn-primary-themed:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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
        }
        .back-link:hover {
            background: var(--dashboard-link-hover-bg-translucent);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            color: var(--dashboard-primary);
        }
        
        /* Test Taking View */
        .test-title {
            font-size: 2.25rem; /* text-3xl */
            font-weight: 700; /* font-bold */
            color: var(--dashboard-primary);
            margin-bottom: 0.5rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .test-description {
            color: var(--dashboard-text-muted);
        }
        .timer-display {
            font-size: 1.25rem; /* text-xl */
            font-weight: 700; /* font-bold */
            color: white;
            background-color: var(--danger-color);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .question-text {
            font-size: 1.25rem; /* text-lg */
            font-weight: 600; /* font-semibold */
            color: var(--dashboard-text-dark);
            margin-bottom: 1rem;
        }

        /* Question Options */
        .question-option-label {
            display: block;
            padding: 1rem;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 0.75rem;
            background-color: rgba(255,255,255,0.4);
            transition: all 0.2s ease;
            cursor: pointer;
            margin-bottom: 0.75rem; /* space-y-3 equivalent */
            display: flex;
            align-items: center;
        }
        .question-option-label:hover {
            background-color: rgba(255,255,255,0.6);
            border-color: var(--dashboard-primary);
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .question-option-label input[type="radio"] {
            margin-right: 0.75rem;
            accent-color: var(--dashboard-primary); /* Style the radio button itself */
            transform: scale(1.2); /* Slightly larger radio button */
        }
        .question-option-label input[type="radio"]:checked + span {
            font-weight: 600;
            color: var(--dashboard-primary);
        }


        /* Results Table */
        .results-table-container {
            overflow-x-auto;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 0.75rem;
            background-color: rgba(255,255,255,0.4); /* Lighter background for table container */
        }
        .results-table {
            min-width: 100%; /* Ensure table takes full width */
            border-collapse: collapse; /* Remove double borders */
        }
        .results-table thead {
            background-color: #f8f9fa; /* Light grey for header */
        }
        .results-table th {
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: var(--dashboard-text-dark);
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .results-table td {
            padding: 0.75rem;
            color: var(--dashboard-text-dark);
            border-bottom: 1px solid rgba(0,0,0,0.05); /* Lighter internal borders */
        }
        .results-table tbody tr:last-child td {
            border-bottom: none;
        }
        .results-table td.text-center { text-align: center; }
        .results-table td.font-semibold { font-weight: 600; }
        .results-table td.font-medium { font-weight: 500; }

        /* Custom Alerts for consistent styling */
        .alert-info-custom {
            background-color: #fff8e1; /* Lighter, creamy yellow */
            color: var(--dashboard-primary);
            border-left: 5px solid var(--dashboard-primary);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--dashboard-card-shadow);
        }
        .alert-danger-custom {
            border-left: 5px solid var(--danger-color);
            background-color: #ffe0e0;
            color: var(--danger-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--dashboard-card-shadow);
        }
        .alert-success-custom {
            border-left: 5px solid var(--success-color);
            background-color: #d4edda; /* Bootstrap light green */
            color: var(--success-color);
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
            .dashboard-panel-padding {
                padding: 1.5rem;
            }
            .dashboard-tabs nav {
                flex-wrap: wrap;
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }
            .dashboard-tabs a {
                padding: 0.75rem 0.5rem;
                font-size: 0.8em;
            }
            .test-list-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .test-list-item button {
                width: 100%;
                margin-top: 1rem;
            }
            .timer-display {
                font-size: 1rem;
                padding: 0.4rem 0.8rem;
                gap: 0.3rem;
            }
            .test-title {
                font-size: 1.8rem;
            }
            .question-text {
                font-size: 1.1rem;
            }
            .question-option-label {
                padding: 0.8rem;
                font-size: 0.9rem;
            }
            .results-table th, .results-table td {
                padding: 0.5rem;
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Page Header Section -->
    <header class="page-header">
        <h1 class="page-title">
            <i class="fas fa-laptop-code"></i> Online Tests
        </h1>
        <div class="welcome-info-block">
            <p class="welcome-info">
                Welcome, <strong><?php echo htmlspecialchars(explode(' ', $student_full_name)[0]); ?></strong>!
                Your Class: <strong><?php echo htmlspecialchars($class_name . ' ' . $section_name); ?></strong>
            </p>
        </div>
    </header>

    <main class="main-content-area">
        <?php
        // Display any error messages
        if (!empty($error_message)) {
            echo '<div class="alert alert-danger-custom mb-4" role="alert"><h4 class="alert-heading"><i class="fas fa-times-circle"></i> Error!</h4><p>' . htmlspecialchars($error_message) . '</p></div>';
        }
        // Display any success messages
        if (!empty($success_message)) {
            echo '<div class="alert alert-success-custom mb-4" role="alert"><h4 class="alert-heading"><i class="fas fa-check-circle"></i> Success!</h4><p>' . htmlspecialchars($success_message) . '</p></div>';
        }
        // Display any info messages (e.g., if student not in class)
        if (!empty($info_message)) {
            echo '<div class="alert alert-info-custom mb-4" role="alert"><h4 class="alert-heading"><i class="fas fa-info-circle"></i> Information</h4><p>' . htmlspecialchars($info_message) . '</p></div>';
        }
        ?>

        <?php if ($test_to_take_id && $test_data): // --- TEST TAKING VIEW --- ?>
        <div x-data="testRunner(<?php echo count($questions); ?>, <?php echo $test_data['time_limit_minutes']; ?>, '<?php echo $test_data['start_time']; ?>')" class="dashboard-panel dashboard-panel-padding">
            <h1 class="test-title"><?php echo htmlspecialchars($test_data['title']); ?></h1>
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                <p class="test-description mb-0">Answer the questions below.</p>
                <div class="timer-display">
                    <i class="fas fa-clock"></i> Time Left: <span x-text="timerDisplay"></span>
                </div>
            </div>

            <form id="testForm" action="student_tests.php" method="POST">
                <input type="hidden" name="test_id" value="<?php echo htmlspecialchars($test_to_take_id); ?>">
                <input type="hidden" name="attempt_id" value="<?php echo htmlspecialchars($test_data['attempt_id']); ?>">

                <!-- Questions -->
                <div class="question-list-container mb-4">
                    <?php foreach ($questions as $index => $q): ?>
                        <div x-show="currentQuestion === <?php echo $index; ?>" x-transition.opacity>
                            <h2 class="question-text"><?php echo ($index + 1) . '. ' . htmlspecialchars($q['question_text']); ?></h2>
                            <div class="question-options">
                                <label class="question-option-label"><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="A" required> <span><?php echo htmlspecialchars($q['option_a']); ?></span></label>
                                <label class="question-option-label"><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="B"> <span><?php echo htmlspecialchars($q['option_b']); ?></span></label>
                                <label class="question-option-label"><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="C"> <span><?php echo htmlspecialchars($q['option_c']); ?></span></label>
                                <label class="question-option-label"><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="D"> <span><?php echo htmlspecialchars($q['option_d']); ?></span></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Navigation -->
                <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                    <button type="button" @click="prevQuestion()" :disabled="currentQuestion === 0" class="btn btn-secondary-themed">Previous</button>
                    <span class="font-semibold text-dashboard-text-dark" x-text="'Question ' + (currentQuestion + 1) + ' of ' + totalQuestions"></span>
                    <button type="button" @click="nextQuestion()" x-show="currentQuestion < totalQuestions - 1" class="btn btn-primary-themed">Next</button>
                    <button type="submit" name="submit_test" x-show="currentQuestion === totalQuestions - 1" class="btn btn-primary-themed">Submit Test</button>
                </div>
            </form>
        </div>

        <?php else: // --- TEST LISTING VIEW --- ?>
        <div x-data="{ tab: '<?php echo (isset($_GET['view']) && $_GET['view'] === 'results') ? 'results' : 'available'; ?>' }" class="dashboard-panel">
            <div class="dashboard-tabs">
                <nav>
                    <a href="#" @click.prevent="tab = 'available'" :class="{ 'active-tab': tab === 'available' }">Available Tests</a>
                    <a href="#" @click.prevent="tab = 'results'" :class="{ 'active-tab': tab === 'results' }">My Results</a>
                </nav>
            </div>
            <div class="dashboard-panel-padding">
                <!-- Available Tests Tab -->
                <div x-show="tab === 'available'">
                    <h2 class="section-title mb-4"><i class="fas fa-play-circle"></i> Tests Ready To Take</h2>
                    <?php if (empty($available_tests)): ?>
                        <div class="alert alert-info-custom py-4" role="alert">
                            <p class="mb-0 text-center"><i class="fas fa-info-circle me-2"></i> No new tests are available for you at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="available-tests-list">
                            <?php foreach($available_tests as $test): ?>
                            <div class="test-list-item">
                                <div>
                                    <h3><?php echo htmlspecialchars($test['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($test['subject_name']); ?> | Time Limit: <?php echo htmlspecialchars($test['time_limit_minutes']); ?> minutes</p>
                                </div>
                                <form action="student_tests.php" method="POST">
                                    <input type="hidden" name="test_id" value="<?php echo htmlspecialchars($test['id']); ?>">
                                    <button type="submit" name="start_test" class="btn btn-primary-themed">
                                        <i class="fas fa-play me-2"></i>Start Test
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Results Tab -->
                <div x-show="tab === 'results'" style="display: none;">
                    <h2 class="section-title mb-4"><i class="fas fa-chart-bar"></i> My Completed Test Results</h2>
                    <?php if (empty($completed_tests)): ?>
                        <div class="alert alert-info-custom py-4" role="alert">
                            <p class="mb-0 text-center"><i class="fas fa-info-circle me-2"></i> You have not completed any tests yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="results-table-container">
                            <table class="results-table">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th>Test Title</th>
                                        <th>Subject</th>
                                        <th class="text-center">Score</th>
                                        <th class="text-center">Percentage</th>
                                        <th>Completed On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($completed_tests as $test):
                                        $percentage = ($test['total_marks'] > 0) ? ($test['score'] / $test['total_marks']) * 100 : 0;
                                        $percentage_color_class = '';
                                        if ($percentage >= 75) $percentage_color_class = 'text-success';
                                        elseif ($percentage >= 50) $percentage_color_class = 'text-warning'; // Bootstrap warning color
                                        else $percentage_color_class = 'text-danger'; // Bootstrap danger color
                                    ?>
                                    <tr>
                                        <td class="font-medium"><?php echo htmlspecialchars($test['title']); ?></td>
                                        <td><?php echo htmlspecialchars($test['subject_name']); ?></td>
                                        <td class="text-center font-semibold"><?php echo htmlspecialchars($test['score']); ?> / <?php echo htmlspecialchars($test['total_marks']); ?></td>
                                        <td class="text-center font-semibold <?php echo $percentage_color_class; ?>"><?php echo number_format($percentage, 2); ?>%</td>
                                        <td><?php echo date("M j, Y, g:i a", strtotime($test['end_time'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center mt-5">
            <a href="student_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
        </div>
    </main>
</div>

<script>
    function testRunner(totalQuestions, timeLimitMinutes, startTimeString) {
        return {
            totalQuestions: totalQuestions,
            currentQuestion: 0,
            timerDisplay: '00:00',
            init() {
                const startTime = new Date(startTimeString.replace(/-/g, '/')); // Fix for cross-browser date parsing
                const endTime = new Date(startTime.getTime() + timeLimitMinutes * 60000);
                
                const timerInterval = setInterval(() => {
                    const now = new Date();
                    const timeLeft = endTime.getTime() - now.getTime();
                    
                    if (timeLeft <= 0) {
                        clearInterval(timerInterval);
                        this.timerDisplay = '00:00';
                        alert('Time is up! The test will be submitted automatically.');
                        document.getElementById('testForm').submit();
                        return;
                    }
                    
                    const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                    this.timerDisplay = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                }, 1000);
            },
            nextQuestion() {
                if (this.currentQuestion < this.totalQuestions - 1) {
                    this.currentQuestion++;
                }
            },
            prevQuestion() {
                if (this.currentQuestion > 0) {
                    this.currentQuestion--;
                }
            }
        }
    }
</script>

</body>
</html>
<?php mysqli_close($link); // Close connection here as it's the end of PHP logic execution ?>
<?php require_once './student_footer.php'; ?>