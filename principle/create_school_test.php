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
$principal_role = $_SESSION["role"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

$test_id_to_add_questions = null;

// Check if we are in the "add questions" phase
if (isset($_GET['add_questions_to_test']) && is_numeric($_GET['add_questions_to_test'])) {
    $test_id_to_add_questions = (int)$_GET['add_questions_to_test'];
    
    // Fetch test details to display
    $current_test_details = null;
    $sql_fetch_test = "SELECT ot.id, ot.title, c.class_name, c.section_name, s.subject_name
                       FROM online_tests ot
                       JOIN classes c ON ot.class_id = c.id
                       JOIN subjects s ON ot.subject_id = s.id
                       WHERE ot.id = ?";
    if ($stmt = mysqli_prepare($link, $sql_fetch_test)) {
        mysqli_stmt_bind_param($stmt, "i", $test_id_to_add_questions);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $current_test_details = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }

    if (!$current_test_details) {
        set_session_message("Test not found or invalid ID.", "danger");
        header("location: create_school_test.php");
        exit;
    }
}


// --- Process Form Submissions (Add Test or Add Question) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process Add Test Form
    if (isset($_POST['form_action']) && $_POST['form_action'] == 'add_test') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $class_id = (int)$_POST['class_id'];
        $subject_id = (int)$_POST['subject_id'];
        $teacher_id = empty(trim($_POST['teacher_id'])) ? NULL : (int)$_POST['teacher_id']; // Optional creator teacher
        $time_limit_minutes = (int)$_POST['time_limit_minutes'];
        $status = trim($_POST['status']);
        $created_by_text = $principal_name . " (" . $principal_role . ")"; // Principal is creating

        if (empty($title) || empty($class_id) || empty($subject_id) || empty($time_limit_minutes) || empty($status) || $time_limit_minutes <= 0) {
            set_session_message("Title, Class, Subject, Time Limit (must be > 0), and Status are required for a test.", "danger");
            header("location: create_school_test.php");
            exit;
        }

        $sql = "INSERT INTO online_tests (teacher_id, class_id, subject_id, title, description, time_limit_minutes, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "iiisssis", $teacher_id, $class_id, $subject_id, $title, $description, $time_limit_minutes, $status, $created_by_text);
            if (mysqli_stmt_execute($stmt)) {
                $new_test_id = mysqli_insert_id($link);
                set_session_message("Test created successfully. Now add questions!", "success");
                header("location: create_school_test.php?add_questions_to_test=" . $new_test_id);
                exit;
            } else {
                set_session_message("Error creating test: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }

    } // Process Add Question Form
    elseif (isset($_POST['form_action']) && $_POST['form_action'] == 'add_question_to_test' && isset($_POST['test_id'])) {
        $test_id = (int)$_POST['test_id'];
        $question_text = trim($_POST['question_text']);
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_option = trim($_POST['correct_option']);
        $marks = (int)$_POST['marks'];

        if (empty($test_id) || empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_option) || $marks <= 0) {
            set_session_message("All question fields (including 4 options, correct option, and marks > 0) are required.", "danger");
            header("location: create_school_test.php?add_questions_to_test=" . $test_id);
            exit;
        }

        $sql = "INSERT INTO online_test_questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "issssssi", $test_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $marks);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Question added successfully. Add another or finish!", "success");
            } else {
                set_session_message("Error adding question: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
        header("location: create_school_test.php?add_questions_to_test=" . $test_id);
        exit;

    }
    
    header("location: create_school_test.php"); // Fallback for other POSTs
    exit;
}


// --- Fetch Dropdown Data (for Add Test Form) ---
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    set_session_message("Error fetching classes for test creation: " . mysqli_error($link), "danger");
}

$all_subjects = [];
$sql_all_subjects = "SELECT id, subject_name FROM subjects ORDER BY subject_name ASC";
if ($result = mysqli_query($link, $sql_all_subjects)) {
    $all_subjects = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    set_session_message("Error fetching subjects for test creation: " . mysqli_error($link), "danger");
}

$all_teachers = []; // Optional teacher for creation
$sql_all_teachers = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_all_teachers)) {
    $all_teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    set_session_message("Error fetching teachers for test creation: " . mysqli_error($link), "danger");
}

$test_statuses = ['Draft', 'Published'];
$correct_options = ['A', 'B', 'C', 'D'];


// --- Fetch existing questions for current test (if in add_questions phase) ---
$existing_questions = [];
if ($test_id_to_add_questions) {
    $sql_fetch_questions = "SELECT * FROM online_test_questions WHERE test_id = ? ORDER BY id ASC";
    if ($stmt = mysqli_prepare($link, $sql_fetch_questions)) {
        mysqli_stmt_bind_param($stmt, "i", $test_id_to_add_questions);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing_questions = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    } else {
        set_session_message("Error fetching existing questions: " . mysqli_error($link), "danger");
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
    <title>Create School Test - Principal Panel</title>
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
            max-width: 900px;
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
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }


        /* Form Section */
        .form-section {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .form-section h3 {
            color: #008B8B;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
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
            appearance: none; -webkit-appearance: none; -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23008B8B%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23008B8B%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat; background-position: right 10px center; background-size: 14px; padding-right: 30px;
        }
        .form-actions {
            margin-top: 25px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn-form-submit, .btn-secondary {
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
        .btn-form-submit { background-color: #008B8B; color: #fff; }
        .btn-form-submit:hover { background-color: #006060; }
        .btn-secondary { background-color: #6c757d; color: #fff; }
        .btn-secondary:hover { background-color: #5a6268; }

        /* Question List */
        .question-list-wrapper {
            margin-top: 30px;
            border-top: 1px dashed #eee;
            padding-top: 20px;
        }
        .question-card {
            background-color: #f8f8ff;
            border: 1px solid #e0e0f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .question-card .question-text {
            font-weight: 600;
            color: #483D8B;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .question-card .options-list {
            list-style: upper-alpha;
            margin: 0 0 10px 20px;
            padding: 0;
            font-size: 0.95em;
        }
        .question-card .options-list li {
            margin-bottom: 5px;
            color: #555;
        }
        .question-card .options-list .correct-option {
            font-weight: bold;
            color: #28a745;
        }
        .question-card .question-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85em;
            color: #888;
            border-top: 1px dashed #eee;
            padding-top: 10px;
            margin-top: 10px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-plus-square"></i> Create School Test</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$test_id_to_add_questions): ?>
            <!-- Phase 1: Create New Test Details -->
            <div class="form-section">
                <h3><i class="fas fa-file-alt"></i> Test Details</h3>
                <form action="create_school_test.php" method="POST">
                    <input type="hidden" name="form_action" value="add_test">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">Test Title:</label>
                            <input type="text" id="title" name="title" required placeholder="e.g., Chapter 1 Quiz, Mid-Term Exam">
                        </div>
                        <div class="form-group">
                            <label for="class_id">Class:</label>
                            <select id="class_id" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($all_classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subject_id">Subject:</label>
                            <select id="subject_id" name="subject_id" required>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($all_subjects as $subject): ?>
                                    <option value="<?php echo htmlspecialchars($subject['id']); ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="teacher_id">Creator Teacher (Optional):</label>
                            <select id="teacher_id" name="teacher_id">
                                <option value="">-- Select Teacher --</option>
                                <?php foreach ($all_teachers as $teacher): ?>
                                    <option value="<?php echo htmlspecialchars($teacher['id']); ?>">
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="time_limit_minutes">Time Limit (minutes):</label>
                            <input type="number" id="time_limit_minutes" name="time_limit_minutes" required min="1" value="60">
                        </div>
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status" required>
                                <option value="Draft">Draft</option>
                                <option value="Published">Published</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="description">Description (Optional):</label>
                            <textarea id="description" name="description" rows="3" placeholder="Brief description of the test"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-form-submit"><i class="fas fa-arrow-right"></i> Create Test & Add Questions</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Phase 2: Add Questions to Existing Test -->
            <div class="form-section">
                <h3><i class="fas fa-question-circle"></i> Add Questions to: <?php echo htmlspecialchars($current_test_details['title']); ?></h3>
                <p class="text-muted">For Class: <?php echo htmlspecialchars($current_test_details['class_name'] . ' - ' . $current_test_details['section_name']); ?> | Subject: <?php echo htmlspecialchars($current_test_details['subject_name']); ?></p>
                <hr>

                <form action="create_school_test.php" method="POST">
                    <input type="hidden" name="form_action" value="add_question_to_test">
                    <input type="hidden" name="test_id" value="<?php echo htmlspecialchars($test_id_to_add_questions); ?>">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="question_text">Question Text:</label>
                            <textarea id="question_text" name="question_text" rows="3" required placeholder="Enter the full question text here"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="option_a">Option A:</label>
                            <input type="text" id="option_a" name="option_a" required placeholder="Option A text">
                        </div>
                        <div class="form-group">
                            <label for="option_b">Option B:</label>
                            <input type="text" id="option_b" name="option_b" required placeholder="Option B text">
                        </div>
                        <div class="form-group">
                            <label for="option_c">Option C:</label>
                            <input type="text" id="option_c" name="option_c" required placeholder="Option C text">
                        </div>
                        <div class="form-group">
                            <label for="option_d">Option D:</label>
                            <input type="text" id="option_d" name="option_d" required placeholder="Option D text">
                        </div>
                        <div class="form-group">
                            <label for="correct_option">Correct Option:</label>
                            <select id="correct_option" name="correct_option" required>
                                <option value="">-- Select Correct Option --</option>
                                <?php foreach ($correct_options as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="marks">Marks for this Question:</label>
                            <input type="number" id="marks" name="marks" required min="1" value="1">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-form-submit"><i class="fas fa-plus"></i> Add Question</button>
                        <a href="view_all_online_tests.php?view_test_id=<?php echo htmlspecialchars($test_id_to_add_questions); ?>" class="btn-secondary"><i class="fas fa-check"></i> Finish & View Test</a>
                    </div>
                </form>

                <?php if (!empty($existing_questions)): ?>
                    <div class="question-list-wrapper">
                        <h4><i class="fas fa-list-ol"></i> Existing Questions (<?php echo count($existing_questions); ?>)</h4>
                        <?php foreach ($existing_questions as $q_index => $question): ?>
                            <div class="question-card">
                                <div class="question-text"><?php echo ($q_index + 1); ?>. <?php echo htmlspecialchars($question['question_text']); ?></div>
                                <ul class="options-list">
                                    <li class="<?php echo ($question['correct_option'] == 'A' ? 'correct-option' : ''); ?>">A) <?php echo htmlspecialchars($question['option_a']); ?></li>
                                    <li class=" <?php echo ($question['correct_option'] == 'B' ? 'correct-option' : ''); ?>">B) <?php echo htmlspecialchars($question['option_b']); ?></li>
                                    <li class=" <?php echo ($question['correct_option'] == 'C' ? 'correct-option' : ''); ?>">C) <?php echo htmlspecialchars($question['option_c']); ?></li>
                                    <li class="<?php echo ($question['correct_option'] == 'D' ? 'correct-option' : ''); ?>">D) <?php echo htmlspecialchars($question['option_d']); ?></li>
                                </ul>
                                <div class="question-footer">
                                    <span>Correct Answer: <strong><?php echo htmlspecialchars($question['correct_option']); ?></strong></span>
                                    <span>Marks: <strong><?php echo htmlspecialchars($question['marks']); ?></strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // No general collapsible sections here as the main content is conditional
    });
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>