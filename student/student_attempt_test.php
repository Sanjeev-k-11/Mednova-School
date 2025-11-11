<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php"); // Redirect to login page if not logged in as a student
    exit;
}

// IMPORTANT: Using 'id' as it's a more common and robust key for student ID.
// If your login system specifically sets $_SESSION['id'] for students, please change this line:
$student_id = $_SESSION['id'] ?? null; 

if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    // This is a critical error, redirect to login as student_id is essential
    header("location: ../login.php?error=no_student_id");
    exit();
}

$test_id = $_GET['test_id'] ?? null;
if (!is_numeric($test_id) || $test_id <= 0) {
    header("location: student_tests.php?error=invalid_test"); // Redirect if test_id is missing or invalid
    exit();
}

// --- AJAX REQUEST HANDLING ---
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unexpected error occurred. Please try again.'];

    $action = $_POST['action'] ?? '';

    if ($action === 'save_progress' || $action === 'submit_test') {
        $attempt_id = $_POST['attempt_id'] ?? null;
        $student_answers = json_decode($_POST['student_answers_json'] ?? '{}', true);
        $remaining_time = $_POST['remaining_time'] ?? null; // Can be 0 or null if time runs out

        // Basic validation for AJAX data
        if (!is_numeric($attempt_id) || !is_array($student_answers) || !is_numeric($test_id) || $remaining_time === null) {
            $response['message'] = 'Invalid or incomplete data for saving progress.';
            error_log("AJAX Data Validation Failed: attempt_id={$attempt_id}, student_answers=" . json_encode($student_answers) . ", test_id={$test_id}, remaining_time={$remaining_time}");
            echo json_encode($response);
            exit();
        }
        
        $status_to_save = ($action === 'submit_test') ? 'Completed' : 'In Progress';
        $end_time = ($action === 'submit_test') ? date('Y-m-d H:i:s') : null; // DATETIME string or NULL
        $score_to_save = null; 
        $total_marks_for_attempt = null;
        
        // Use a transaction for the "submit_test" action to ensure atomicity.
        if ($action === 'submit_test') {
            mysqli_begin_transaction($link);
            try {
                // Calculate score for final submission
                $sql_questions = "SELECT id, correct_option, marks FROM online_test_questions WHERE test_id = ?";
                $stmt_q = mysqli_prepare($link, $sql_questions);
                if (!$stmt_q) {
                    throw new Exception('Database error preparing scoring query: ' . mysqli_error($link));
                }
                mysqli_stmt_bind_param($stmt_q, "i", $test_id);
                mysqli_stmt_execute($stmt_q);
                $questions_result = mysqli_stmt_get_result($stmt_q);
                
                $correct_answers_map = [];
                $total_marks_possible = 0;
                while ($q_row = mysqli_fetch_assoc($questions_result)) {
                    $correct_answers_map[$q_row['id']] = [
                        'correct_option' => $q_row['correct_option'],
                        'marks' => $q_row['marks']
                    ];
                    $total_marks_possible += $q_row['marks'];
                }
                mysqli_stmt_close($stmt_q);

                $score_to_save = 0;
                foreach ($student_answers as $q_id => $s_answer) {
                    // Ensure chosen_option exists and is not null
                    if (isset($s_answer['chosen_option']) && $s_answer['chosen_option'] !== null) {
                        if (isset($correct_answers_map[$q_id]) && $correct_answers_map[$q_id]['correct_option'] === $s_answer['chosen_option']) {
                            $score_to_save += $correct_answers_map[$q_id]['marks'];
                        }
                    }
                }
                $total_marks_for_attempt = $total_marks_possible;
                
                // Prepare the update query for submission
                $sql_update_attempt = "
                    UPDATE student_test_attempts
                    SET 
                        student_answers_json = ?,
                        remaining_time_seconds = ?,
                        status = ?,
                        end_time = ?,
                        score = ?,
                        total_marks = ?
                    WHERE id = ? AND student_id = ? AND test_id = ?
                ";

                if ($stmt = mysqli_prepare($link, $sql_update_attempt)) {
                    $json_answers = json_encode($student_answers);
                    
                    mysqli_stmt_bind_param($stmt, "sisiiiii",
                        $json_answers,
                        $remaining_time,
                        $status_to_save,
                        $end_time,
                        $score_to_save,
                        $total_marks_for_attempt,
                        $attempt_id,
                        $student_id,
                        $test_id
                    );
                    
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_commit($link); // Commit the transaction on success
                        $response['success'] = true;
                        $response['message'] = 'Test submitted successfully!';
                        $response['redirect'] = 'student_test_results.php?attempt_id=' . $attempt_id;
                    } else {
                        throw new Exception('Database error executing update: ' . mysqli_stmt_error($stmt));
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    throw new Exception('Database error preparing update query: ' . mysqli_error($link));
                }

            } catch (Exception $e) {
                mysqli_rollback($link); // Rollback on failure
                $response['message'] = 'An unexpected error occurred during test submission. Please try again.';
                error_log("Transaction Failed: " . $e->getMessage());
            }

        } else { // 'save_progress' action
            $sql_update_attempt = "
                UPDATE student_test_attempts
                SET 
                    student_answers_json = ?,
                    remaining_time_seconds = ?,
                    status = ?
                WHERE id = ? AND student_id = ? AND test_id = ?
            ";
            
            if ($stmt = mysqli_prepare($link, $sql_update_attempt)) {
                $json_answers = json_encode($student_answers);
                
                mysqli_stmt_bind_param($stmt, "sisiii",
                    $json_answers,
                    $remaining_time,
                    $status_to_save,
                    $attempt_id,
                    $student_id,
                    $test_id
                );

                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = 'Progress saved.';
                } else {
                    error_log("Progress Save Error: " . mysqli_stmt_error($stmt));
                    $response['message'] = 'Error saving progress. Please check your network and try again.';
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("Progress Prepare Error: " . mysqli_error($link));
                $response['message'] = 'Error preparing save query. Please try again.';
            }
        }
    } else {
        $response['message'] = 'Invalid AJAX action.';
    }
    echo json_encode($response);
    mysqli_close($link);
    exit();
}
// --- END AJAX REQUEST HANDLING ---


// --- INITIAL PAGE DATA FETCHING ---
$test_details = null;
$questions = [];
$current_attempt = null;
$initial_remaining_time = null;
$student_previous_answers = [];

// Fetch test details
$sql_test_details = "
    SELECT 
        ot.id, ot.title, ot.description, ot.time_limit_minutes,
        sub.subject_name, t.full_name AS teacher_name,
        ot.status AS test_status_published
    FROM online_tests ot
    JOIN subjects sub ON ot.subject_id = sub.id
    JOIN teachers t ON ot.teacher_id = t.id
    WHERE ot.id = ? AND ot.class_id = (SELECT class_id FROM students WHERE id = ?)
";
if ($stmt = mysqli_prepare($link, $sql_test_details)) {
    mysqli_stmt_bind_param($stmt, "ii", $test_id, $student_id);
    mysqli_stmt_execute($stmt);
    $test_details = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

if (!$test_details || $test_details['test_status_published'] !== 'Published') {
    $_SESSION['flash_message'] = "Test not found or not available.";
    $_SESSION['flash_message_type'] = 'error';
    header("location: student_tests.php");
    exit();
}

// Check for existing attempt
$sql_check_attempt = "SELECT * FROM student_test_attempts WHERE test_id = ? AND student_id = ?";
if ($stmt = mysqli_prepare($link, $sql_check_attempt)) {
    mysqli_stmt_bind_param($stmt, "ii", $test_id, $student_id);
    mysqli_stmt_execute($stmt);
    $current_attempt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

if ($current_attempt) {
    if ($current_attempt['status'] === 'Completed') {
        $_SESSION['flash_message'] = "You have already completed this test.";
        $_SESSION['flash_message_type'] = 'info';
        header("location: student_test_results.php?attempt_id=" . $current_attempt['id']);
        exit();
    }
    // Test is 'In Progress'
    $initial_remaining_time = $current_attempt['remaining_time_seconds'];
    $student_previous_answers = json_decode($current_attempt['student_answers_json'] ?? '{}', true);
} else {
    // No existing attempt, create a new one
    $sql_total_marks = "SELECT SUM(marks) AS total_marks_possible FROM online_test_questions WHERE test_id = ?";
    $stmt_total_marks = mysqli_prepare($link, $sql_total_marks);
    mysqli_stmt_bind_param($stmt_total_marks, "i", $test_id);
    mysqli_stmt_execute($stmt_total_marks);
    $total_marks_possible = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_total_marks))['total_marks_possible'] ?? 0;
    mysqli_stmt_close($stmt_total_marks);

    $initial_remaining_time = $test_details['time_limit_minutes'] * 60; // Convert minutes to seconds

    $sql_create_attempt = "
        INSERT INTO student_test_attempts (test_id, student_id, start_time, total_marks, status, remaining_time_seconds)
        VALUES (?, ?, NOW(), ?, 'In Progress', ?)
    ";
    
    if ($stmt = mysqli_prepare($link, $sql_create_attempt)) {
        mysqli_stmt_bind_param($stmt, "iiii", $test_id, $student_id, $total_marks_possible, $initial_remaining_time);
        mysqli_stmt_execute($stmt);
        $new_attempt_id = mysqli_insert_id($link);
        mysqli_stmt_close($stmt);

        // Fetch the newly created attempt
        $sql_check_attempt = "SELECT * FROM student_test_attempts WHERE id = ?";
        $stmt_new = mysqli_prepare($link, $sql_check_attempt);
        mysqli_stmt_bind_param($stmt_new, "i", $new_attempt_id);
        mysqli_stmt_execute($stmt_new);
        $current_attempt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_new));
        mysqli_stmt_close($stmt_new);
        
        $_SESSION['flash_message'] = "Test started successfully!";
        $_SESSION['flash_message_type'] = 'success';
    } else {
        error_log("Failed to create new attempt: " . mysqli_error($link));
        $_SESSION['flash_message'] = "Failed to start test. Database error.";
        $_SESSION['flash_message_type'] = 'error';
        header("location: student_tests.php");
        exit();
    }
}

// Fetch questions for the test
$sql_questions = "SELECT id, question_text, option_a, option_b, option_c, option_d, marks FROM online_test_questions WHERE test_id = ? ORDER BY id ASC";
if ($stmt = mysqli_prepare($link, $sql_questions)) {
    mysqli_stmt_bind_param($stmt, "i", $test_id);
    mysqli_stmt_execute($stmt);
    $questions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

mysqli_close($link);
require_once "./student_header.php"; // Include the header
?>

<!-- Custom styles for the test page -->
<style>
    .question-nav-item {
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }
    .question-nav-item.current {
        background-color: #3b82f6; /* blue-500 */
        color: white;
        border: 2px solid #2563eb; /* blue-600 */
        transform: scale(1.1);
    }
    .question-nav-item.answered {
        background-color: #10b981; /* emerald-500 */
        color: white;
    }
    .question-nav-item.skipped {
        background-color: #f59e0b; /* amber-500 */
        color: white;
    }
    .question-nav-item.unattempted {
        background-color: #e5e7eb; /* gray-200 */
        color: #4b5563; /* gray-700 */
    }
    .question-nav-item:hover:not(.current) {
        background-color: #d1d5db; /* gray-300 */
        transform: scale(1.05);
    }
    .option-label {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }
    .option-label:hover {
        background-color: #e5e7eb;
    }
    .option-label input[type="radio"] {
        margin-right: 0.75rem;
        transform: scale(1.2); /* Make radio buttons slightly larger */
    }
    .btn-timer-warning {
        background-color: #facc15; /* amber-400 */
        color: #1a202c; /* gray-900 */
    }
    .btn-timer-danger {
        background-color: #ef4444; /* red-500 */
        color: white;
    }
    /* Modal styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    .modal-content {
        background-color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        max-width: 500px;
        text-align: center;
    }
</style>

<div class="bg-gray-100 min-h-screen p-4 sm:p-6">
    <!-- Flash Message Display -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="max-w-4xl mx-auto mb-4 p-3 rounded-lg <?php echo ($_SESSION['flash_message_type'] === 'error') ? 'bg-red-500/80' : 'bg-green-500/80'; ?> text-center text-white">
            <?php echo $_SESSION['flash_message']; unset($_SESSION['flash_message']); unset($_SESSION['flash_message_type']); ?>
        </div>
    <?php endif; ?>

    <!-- Test Details & Timer -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6 flex flex-col sm:flex-row justify-between items-center">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 mb-2"><?php echo htmlspecialchars($test_details['title']); ?></h1>
            <p class="text-gray-600">Subject: <span class="font-semibold"><?php echo htmlspecialchars($test_details['subject_name']); ?></span> | Created by: <span class="font-semibold"><?php echo htmlspecialchars($test_details['teacher_name']); ?></span></p>
            <p class="text-gray-700 mt-2"><?php echo htmlspecialchars($test_details['description']); ?></p>
            <p class="text-sm text-gray-500 mt-1">Time Limit: <span class="font-semibold"><?php echo htmlspecialchars($test_details['time_limit_minutes']); ?> minutes</span></p>
        </div>
        <div class="mt-4 sm:mt-0 flex flex-col items-end">
            <div id="test-timer" class="text-4xl font-bold px-6 py-3 rounded-full btn-timer-warning">
                00:00:00
            </div>
            <p class="text-sm text-gray-500 mt-1">Remaining Time</p>
        </div>
    </div>

    <!-- Test Navigation and Question Area -->
    <div class="bg-white rounded-xl shadow-lg p-6 grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Question Navigation (Sidebar on large screens) -->
        <div class="lg:col-span-1 border-b lg:border-b-0 lg:border-r border-gray-200 pb-4 lg:pb-0 lg:pr-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Questions</h3>
            <div class="flex flex-wrap gap-3" id="question-navigation">
                <?php foreach ($questions as $index => $q): ?>
                    <div class="question-nav-item unattempted" data-question-index="<?php echo $index; ?>" title="Question <?php echo $index + 1; ?>">
                        <?php echo $index + 1; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-6">
                <button id="submit-test-btn" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-md transition-transform transform hover:scale-105">
                    Submit Test
                </button>
            </div>
        </div>

        <!-- Question Display Area -->
        <div class="lg:col-span-3">
            <div id="question-display-area">
                <!-- Question content will be loaded here by JavaScript -->
                <p class="text-center text-gray-500">Loading question...</p>
            </div>

            <!-- Navigation Buttons -->
            <div class="mt-6 flex justify-between">
                <button id="prev-question-btn" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md disabled:opacity-50 disabled:cursor-not-allowed">Previous</button>
                <div>
                    <button id="clear-answer-btn" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-md mr-3">Clear Answer</button>
                    <button id="next-question-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Modal Structure -->
<div id="custom-modal" class="modal-overlay hidden">
    <div class="modal-content">
        <p id="modal-message" class="text-lg font-semibold mb-4"></p>
        <div id="modal-actions" class="flex justify-center gap-4">
            <button id="modal-cancel" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">Cancel</button>
            <button id="modal-confirm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">OK</button>
        </div>
    </div>
</div>

<?php require_once "./student_footer.php"; // Include the footer ?>

<script>
    const questions = <?php echo json_encode($questions); ?>;
    const attemptId = <?php echo json_encode($current_attempt['id']); ?>;
    const testId = <?php echo json_encode($test_id); ?>;
    let studentAnswers = <?php echo json_encode($student_previous_answers); ?>;
    let currentQuestionIndex = 0;
    let timerInterval;
    let remainingTimeSeconds = <?php echo (int)$initial_remaining_time; ?>; // Initial time in seconds

    const questionDisplayArea = document.getElementById('question-display-area');
    const questionNavigation = document.getElementById('question-navigation');
    const timerElement = document.getElementById('test-timer');
    const prevBtn = document.getElementById('prev-question-btn');
    const nextBtn = document.getElementById('next-question-btn');
    const clearAnswerBtn = document.getElementById('clear-answer-btn'); // Changed from skipBtn
    const submitTestBtn = document.getElementById('submit-test-btn');
    
    // Modal elements
    const modal = document.getElementById('custom-modal');
    const modalMessage = document.getElementById('modal-message');
    const modalConfirmBtn = document.getElementById('modal-confirm');
    const modalCancelBtn = document.getElementById('modal-cancel');

    const AUTO_SAVE_INTERVAL = 30; // Auto-save every 30 seconds
    let autoSaveCounter = 0;

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
        const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${h}:${m}:${s}`;
    }

    // Custom Modal Functions
    function showModal(message, isConfirm = false, onConfirm = () => {}) {
        modalMessage.textContent = message;
        modalConfirmBtn.onclick = () => {
            onConfirm();
            modal.classList.add('hidden');
        };
        modalCancelBtn.onclick = () => {
            modal.classList.add('hidden');
        };

        if (isConfirm) {
            modalConfirmBtn.classList.remove('hidden');
            modalCancelBtn.classList.remove('hidden');
            modalConfirmBtn.textContent = 'Yes';
            modalCancelBtn.textContent = 'Cancel';
        } else {
            modalConfirmBtn.classList.remove('hidden');
            modalCancelBtn.classList.add('hidden');
            modalConfirmBtn.textContent = 'OK';
        }

        modal.classList.remove('hidden');
    }

    function updateTimerDisplay() {
        timerElement.textContent = formatTime(remainingTimeSeconds);

        if (remainingTimeSeconds <= 60 && remainingTimeSeconds > 0) { // Last 1 minute
            timerElement.classList.remove('btn-timer-warning');
            timerElement.classList.add('btn-timer-danger');
        } else if (remainingTimeSeconds <= 300 && remainingTimeSeconds > 60) { // Last 5 minutes
            timerElement.classList.add('btn-timer-warning');
        }

        if (remainingTimeSeconds <= 0) {
            clearInterval(timerInterval);
            timerElement.textContent = '00:00:00';
            showModal('Time is up! Your test will be submitted automatically.');
            submitTest(true); // Auto-submit
        } else {
            remainingTimeSeconds--;
            autoSaveCounter++;
            if (autoSaveCounter >= AUTO_SAVE_INTERVAL) {
                saveProgress();
                autoSaveCounter = 0;
            }
        }
    }

    function startTimer() {
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(updateTimerDisplay, 1000);
        updateTimerDisplay(); // Initial call to display time immediately
    }

    function displayQuestion(index) {
        if (index < 0 || index >= questions.length) return;

        currentQuestionIndex = index;
        const question = questions[currentQuestionIndex];
        const studentAnswer = studentAnswers[question.id] || {};

        questionDisplayArea.innerHTML = `
            <p class="text-sm text-gray-500 mb-2">Question <span class="font-bold">${currentQuestionIndex + 1}</span> of <span class="font-bold">${questions.length}</span> (Marks: ${question.marks})</p>
            <h3 class="text-xl font-bold text-gray-900 mb-4">${question.question_text}</h3>
            <div class="space-y-3">
                <label class="option-label">
                    <input type="radio" name="answer_q${question.id}" value="A" ${studentAnswer.chosen_option === 'A' ? 'checked' : ''}>
                    <span>${question.option_a}</span>
                </label>
                <label class="option-label">
                    <input type="radio" name="answer_q${question.id}" value="B" ${studentAnswer.chosen_option === 'B' ? 'checked' : ''}>
                    <span>${question.option_b}</span>
                </label>
                <label class="option-label">
                    <input type="radio" name="answer_q${question.id}" value="C" ${studentAnswer.chosen_option === 'C' ? 'checked' : ''}>
                    <span>${question.option_c}</span>
                </label>
                <label class="option-label">
                    <input type="radio" name="answer_q${question.id}" value="D" ${studentAnswer.chosen_option === 'D' ? 'checked' : ''}>
                    <span>${question.option_d}</span>
                </label>
            </div>
        `;

        // Add event listener to save answer when selected
        questionDisplayArea.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', (event) => {
                const qid = question.id;
                const chosenOption = event.target.value;
                studentAnswers[qid] = { chosen_option: chosenOption };
                updateQuestionNavStatus(qid, 'answered');
                saveProgress(); // Auto-save on answer change
            });
        });

        updateNavButtons();
        updateQuestionNavHighlight();
    }

    function updateQuestionNavStatus(questionId, status) {
        const question = questions.find(q => q.id === questionId);
        if (!question) return;

        const navItem = document.querySelector(`.question-nav-item[data-question-index="${questions.indexOf(question)}"]`);
        if (navItem) {
            navItem.classList.remove('unattempted', 'answered', 'skipped');
            navItem.classList.add(status);
        }
    }

    function updateQuestionNavHighlight() {
        document.querySelectorAll('.question-nav-item').forEach(item => {
            item.classList.remove('current');
            if (parseInt(item.dataset.questionIndex) === currentQuestionIndex) {
                item.classList.add('current');
            }
        });
    }

    function updateNavButtons() {
        prevBtn.disabled = currentQuestionIndex === 0;
        nextBtn.textContent = (currentQuestionIndex === questions.length - 1) ? 'Finish' : 'Next';
        // Clear Answer button's disabled state: enabled if current question has an answer
        const currentQ = questions[currentQuestionIndex];
        const hasAnswer = studentAnswers[currentQ.id] && studentAnswers[currentQ.id].chosen_option !== null;
        clearAnswerBtn.disabled = !hasAnswer;
    }

    async function saveProgress(isFinalSubmit = false) {
        const formData = new FormData();
        formData.append('action', isFinalSubmit ? 'submit_test' : 'save_progress');
        formData.append('attempt_id', attemptId);
        formData.append('test_id', testId);
        formData.append('student_answers_json', JSON.stringify(studentAnswers));
        formData.append('remaining_time', remainingTimeSeconds);

        try {
            const response = await fetch('student_attempt_test.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                if (isFinalSubmit) {
                    showModal(data.message, false, () => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    });
                } else {
                    console.log('Progress saved:', data.message);
                }
            } else {
                console.error('Error saving progress:', data.message);
                if (isFinalSubmit) showModal('Error submitting test: ' + data.message);
            }
        } catch (error) {
            console.error('Network error saving progress:', error);
            if (isFinalSubmit) showModal('Network error submitting test.');
        }
    }

    function submitTest(autoSubmit = false) {
        if (!autoSubmit) {
            showModal(
                'Are you sure you want to submit the test? You cannot change answers after submission.',
                true,
                () => {
                    clearInterval(timerInterval); // Stop the timer
                    saveProgress(true); // Call saveProgress with isFinalSubmit = true
                }
            );
        } else {
            clearInterval(timerInterval);
            saveProgress(true);
        }
    }

    // --- Event Listeners ---
    prevBtn.addEventListener('click', () => displayQuestion(currentQuestionIndex - 1));
    nextBtn.addEventListener('click', () => {
        if (currentQuestionIndex === questions.length - 1) {
            submitTest();
        } else {
            displayQuestion(currentQuestionIndex + 1);
        }
    });

    // Event listener for Clear Answer button (formerly Skip)
    clearAnswerBtn.addEventListener('click', () => {
        const question = questions[currentQuestionIndex];
        if (studentAnswers[question.id] && studentAnswers[question.id].chosen_option !== null) {
            showModal(
                'Are you sure you want to clear your answer for this question?',
                true,
                () => {
                    studentAnswers[question.id] = { chosen_option: null }; // Clear the answer
                    updateQuestionNavStatus(question.id, 'skipped'); // Mark as skipped/unanswered
                    displayQuestion(currentQuestionIndex); // Re-render to clear radio selection
                    saveProgress(); // Save the cleared answer
                }
            );
        } else {
            showModal('This question has not been answered yet.');
        }
    });
    
    submitTestBtn.addEventListener('click', () => submitTest());

    questionNavigation.addEventListener('click', (event) => {
        let navItem = event.target.closest('.question-nav-item');
        if (navItem) {
            const index = parseInt(navItem.dataset.questionIndex);
            displayQuestion(index);
        }
    });

    // --- Initial Setup on DOMContentLoaded ---
    document.addEventListener('DOMContentLoaded', () => {
        if (questions.length === 0) {
            questionDisplayArea.innerHTML = "<p class='text-center text-gray-500'>No questions found for this test.</p>";
            prevBtn.disabled = true;
            nextBtn.disabled = true;
            clearAnswerBtn.disabled = true;
            submitTestBtn.disabled = true;
            return;
        }

        // Initialize question navigation statuses based on previous answers
        questions.forEach((q, index) => {
            const navItem = document.querySelector(`.question-nav-item[data-question-index="${index}"]`);
            if (navItem) {
                if (studentAnswers[q.id] && studentAnswers[q.id].chosen_option !== null) {
                    navItem.classList.add('answered');
                    navItem.classList.remove('unattempted', 'skipped');
                } else if (studentAnswers[q.id] && studentAnswers[q.id].chosen_option === null) {
                    // It's considered skipped if there's an entry but chosen_option is null
                    navItem.classList.add('skipped');
                    navItem.classList.remove('unattempted', 'answered');
                }
                // If no entry in studentAnswers[q.id], it remains 'unattempted'
            }
        });

        displayQuestion(currentQuestionIndex);
        startTimer();
    });
</script>
