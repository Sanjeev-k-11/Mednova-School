<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];
$teacher_name = $_SESSION["full_name"] ?? 'Teacher';

// --- (All backend PHP for fetching data and handling the POST request is the same) ---
$teacher_classes = []; $teacher_subjects = [];
$sql_classes = "SELECT DISTINCT c.id, c.class_name, c.section_name FROM class_subject_teacher cst JOIN classes c ON cst.class_id = c.id WHERE cst.teacher_id = ? ORDER BY c.class_name, c.section_name";
if ($stmt_c = mysqli_prepare($link, $sql_classes)) { mysqli_stmt_bind_param($stmt_c, "i", $teacher_id); mysqli_stmt_execute($stmt_c); $teacher_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_c), MYSQLI_ASSOC); mysqli_stmt_close($stmt_c); }
$sql_subjects = "SELECT DISTINCT s.id, s.subject_name FROM class_subject_teacher cst JOIN subjects s ON cst.subject_id = s.id WHERE cst.teacher_id = ? ORDER BY s.subject_name";
if ($stmt_s = mysqli_prepare($link, $sql_subjects)) { mysqli_stmt_bind_param($stmt_s, "i", $teacher_id); mysqli_stmt_execute($stmt_s); $teacher_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt_s), MYSQLI_ASSOC); mysqli_stmt_close($stmt_s); }
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    mysqli_begin_transaction($link);
    try {
        $class_id = $_POST['class_id']; $subject_id = $_POST['subject_id']; $title = $_POST['title']; $time_limit = $_POST['time_limit'];
        $sql_test = "INSERT INTO online_tests (teacher_id, class_id, subject_id, title, time_limit_minutes, created_by) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_test = mysqli_prepare($link, $sql_test);
        mysqli_stmt_bind_param($stmt_test, "iiisis", $teacher_id, $class_id, $subject_id, $title, $time_limit, $teacher_name);
        mysqli_stmt_execute($stmt_test);
        $test_id = mysqli_insert_id($link);
        $sql_question = "INSERT INTO online_test_questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_question = mysqli_prepare($link, $sql_question);
        foreach ($_POST['questions'] as $q) {
            mysqli_stmt_bind_param($stmt_question, "issssssi", $test_id, $q['text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct'], $q['marks']);
            mysqli_stmt_execute($stmt_question);
        }
        mysqli_commit($link);
        $_SESSION['success_message'] = "Online test created successfully!";
        header("location: teacher_manage_tests.php");
        exit;
    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($link);
        $_SESSION['error_message'] = "Failed to create test. Please try again.";
        header("location: teacher_create_test.php");
        exit;
    }
}
require_once './teacher_header.php';
?>
<!-- --- NEW: ENHANCED STYLES --- -->
<style>
    body {
        background: linear-gradient(-45deg, #1d2b64, #373b44, #a72675, #292E49);
        background-size: 400% 400%;
        animation: gradientBG 25s ease infinite;
        color: white;
    }
    @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    .glass-card {
        background: rgba(0, 0, 0, 0.25);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
    }
    .form-input {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        transition: all 0.2s ease-in-out;
    }
    .form-input:focus {
        outline: none;
        border-color: #38bdf8; /* Light Blue */
        box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.4);
    }
    .question-block { transition: all 0.3s ease; }
    .question-block:hover { border-color: rgba(255, 255, 255, 0.3); }
</style>

<body>
<div class="container mx-auto mt-28 p-4 md:p-8">
    <div class="max-w-5xl mx-auto">
        <div class="glass-card p-8 rounded-3xl shadow-2xl">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 pb-4 border-b border-white/10">
                <h1 class="text-3xl font-extrabold tracking-tight drop-shadow-lg">Create New Online Test</h1>
                <a href="teacher_manage_tests.php" class="mt-4 md:mt-0 text-sky-400 hover:text-sky-300 font-medium transition-colors">
                    <i class="fas fa-tasks mr-2"></i>Manage Tests
                </a>
            </div>
            
            <form id="testForm" action="teacher_create_test.php" method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="relative"><label for="title" class="sr-only">Test Title</label><i class="fas fa-book-open absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="text" name="title" required placeholder="Test Title" class="pl-10 form-input h-11 w-full rounded-lg shadow-inner"></div>
                    <div class="relative"><label for="time_limit" class="sr-only">Time Limit</label><i class="fas fa-clock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="number" name="time_limit" required min="1" placeholder="Time Limit (Minutes)" class="pl-10 h-11 form-input w-full rounded-lg shadow-inner"></div>
                    <div class="relative"><label for="class_id" class="sr-only">Class</label><i class="fas fa-users absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i><select name="class_id" required class="pl-10 h-11 form-input w-full rounded-lg shadow-inner appearance-none"><option value="">-- Select Class --</option><?php foreach($teacher_classes as $class):?><option value="<?php echo $class['id'];?>"><?php echo htmlspecialchars($class['class_name'].' - '.$class['section_name']);?></option><?php endforeach;?></select></div>
                    <div class="relative"><label for="subject_id" class="sr-only">Subject</label><i class="fas fa-book absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i><select name="subject_id" required class="pl-10 h-11 form-input w-full rounded-lg shadow-inner appearance-none"><option value="">-- Select Subject --</option><?php foreach($teacher_subjects as $subject):?><option value="<?php echo $subject['id'];?>"><?php echo htmlspecialchars($subject['subject_name']);?></option><?php endforeach;?></select></div>
                </div>

                <h2 class="text-2xl font-semibold mt-8 mb-4 border-t border-white/10 pt-4">Questions</h2>
                <div id="questionsContainer" class="space-y-6">
                    <!-- Dynamic questions will be inserted here -->
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center mt-8 pt-6 border-t border-white/10">
                    <button type="button" id="addQuestionBtn" class="bg-sky-600/50 text-sky-200 font-bold py-2 px-5 rounded-full hover:bg-sky-500/50 transition-all duration-300 transform hover:-translate-y-0.5 shadow-lg"><i class="fas fa-plus mr-2"></i>Add Question</button>
                    <button type="submit" class="w-full md:w-auto mt-4 md:mt-0 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-full shadow-lg hover:shadow-2xl transition-all duration-300">Create Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="questionTemplate">
    <div class="question-block glass-card p-6 rounded-2xl relative border border-transparent">
        <button type="button" class="remove-question-btn absolute top-4 right-4 w-8 h-8 bg-red-600/80 text-white rounded-full hover:bg-red-500 transition-all duration-200 transform hover:scale-110" title="Remove Question">&times;</button>
        <div class="mb-4"><label class="block text-sm font-medium text-gray-300">Question #<span class="question-number">1</span></label><textarea name="questions[0][text]" rows="2" required placeholder="Enter the question text" class="mt-3 px-5 py-2 h-28  form-input w-full rounded-md shadow-sm"></textarea></div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-400">Option A</label><input type="text" name="questions[0][option_a]" required class="mt-1 px-5 py-2 h-11 form-input w-full rounded-md shadow-sm"></div>
            <div><label class="block text-sm font-medium text-gray-400">Option B</label><input type="text" name="questions[0][option_b]" required class="mt-1 px-5 py-2 h-11 form-input w-full rounded-md shadow-sm"></div>
            <div><label class="block text-sm font-medium text-gray-400">Option C</label><input type="text" name="questions[0][option_c]" required class="mt-1 px-5 py-2 h-11 form-input w-full rounded-md shadow-sm"></div>
            <div><label class="block text-sm font-medium text-gray-400">Option D</label><input type="text" name="questions[0][option_d]" required class="mt-1 px-5 py-2 h-11 form-input w-full rounded-md shadow-sm"></div>
        </div>
        <div class="grid grid-cols-2 gap-4 mt-4">
            <div><label class="block text-sm font-medium text-gray-300">Correct Answer</label><select name="questions[0][correct]" required class="mt-1 h-11 px-5 py-2 form-input w-full rounded-md shadow-sm"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div>
            <div><label class="block text-sm font-medium text-gray-300">Marks</label><input type="number" name="questions[0][marks]" value="1" min="1" required class="mt-1 h-11 form-input w-full rounded-md shadow-sm"></div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('questionsContainer');
    const addBtn = document.getElementById('addQuestionBtn');
    const template = document.getElementById('questionTemplate');
    let questionCount = 0;

    function addQuestion() {
        questionCount++;
        const clone = template.content.cloneNode(true);
        clone.querySelector('.question-number').textContent = questionCount;
        clone.querySelectorAll('[name]').forEach(input => {
            input.name = input.name.replace(/\[0\]/, `[${questionCount}]`);
        });
        container.appendChild(clone);
    }

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-question-btn')) {
            e.target.closest('.question-block').remove();
            // Renumber remaining questions
            document.querySelectorAll('.question-number').forEach((num, index) => {
                num.textContent = index + 1;
            });
            questionCount = container.children.length;
        }
    });

    addBtn.addEventListener('click', addQuestion);
    addQuestion(); // Add the first question by default
});
</script>
</body>
<?php require_once './teacher_footer.php'; ?>