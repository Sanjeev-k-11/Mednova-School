<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- GET PARAMETERS for the selection funnel ---
$selected_class_id = $_GET['class_id'] ?? null;
$selected_subject_id = $_GET['subject_id'] ?? null;
$selected_exam_schedule_id = $_GET['exam_schedule_id'] ?? null;

// --- DATA FETCHING for dropdowns ---
// 1. Get classes teacher is assigned to
$assigned_classes = [];
$sql_classes = "SELECT DISTINCT c.id, c.class_name, c.section_name FROM class_subject_teacher cst JOIN classes c ON cst.class_id = c.id WHERE cst.teacher_id = ? ORDER BY c.class_name";
if ($stmt = mysqli_prepare($link, $sql_classes)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $assigned_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// 2. Get subjects for the selected class
$assigned_subjects = [];
if ($selected_class_id) {
    $sql_subjects = "SELECT DISTINCT s.id, s.subject_name FROM class_subject_teacher cst JOIN subjects s ON cst.subject_id = s.id WHERE cst.teacher_id = ? AND cst.class_id = ? ORDER BY s.subject_name";
    if ($stmt = mysqli_prepare($link, $sql_subjects)) {
        mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $selected_class_id);
        mysqli_stmt_execute($stmt);
        $assigned_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

// 3. Get exams for the selected class and subject
$scheduled_exams = [];
$exam_details = null;
if ($selected_class_id && $selected_subject_id) {
    $sql_exams = "SELECT es.id, et.exam_name, es.exam_date, es.max_marks FROM exam_schedule es JOIN exam_types et ON es.exam_type_id = et.id WHERE es.class_id = ? AND es.subject_id = ? ORDER BY es.exam_date DESC";
    if ($stmt = mysqli_prepare($link, $sql_exams)) {
        mysqli_stmt_bind_param($stmt, "ii", $selected_class_id, $selected_subject_id);
        mysqli_stmt_execute($stmt);
        $scheduled_exams = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

// 4. If an exam is selected, get its details
if($selected_exam_schedule_id) {
    foreach($scheduled_exams as $ex) {
        if ($ex['id'] == $selected_exam_schedule_id) {
            $exam_details = $ex;
            break;
        }
    }
}

// --- HANDLE MARK SUBMISSION (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['marks'])) {
    $marks_data = $_POST['marks'];
    $posted_exam_schedule_id = $_POST['exam_schedule_id'];
    
    $sql_upsert = "INSERT INTO exam_marks (exam_schedule_id, student_id, marks_obtained, uploaded_by_teacher_id) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), updated_at = NOW()";
    
    if ($stmt = mysqli_prepare($link, $sql_upsert)) {
        foreach ($marks_data as $student_id => $marks) {
            // Server-side validation for marks
            if (is_numeric($marks) && $marks >= 0) {
                mysqli_stmt_bind_param($stmt, "iidi", $posted_exam_schedule_id, $student_id, $marks, $teacher_id);
                mysqli_stmt_execute($stmt);
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    $_SESSION['flash_message'] = "Marks have been saved successfully!";
    // Redirect back with the same GET parameters to show the updated marks
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query(['class_id' => $_POST['class_id'], 'subject_id' => $_POST['subject_id'], 'exam_schedule_id' => $posted_exam_schedule_id]));
    exit();
}

// Handle Flash Message
$message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);


// --- FETCH STUDENTS & EXISTING MARKS for the selected exam ---
$students_with_marks = [];
if ($selected_class_id && $selected_subject_id && $selected_exam_schedule_id) {
    $sql_students = "SELECT s.id, s.roll_number, s.first_name, s.last_name, em.marks_obtained
                     FROM students s
                     LEFT JOIN exam_marks em ON s.id = em.student_id AND em.exam_schedule_id = ?
                     WHERE s.class_id = ?
                     ORDER BY s.roll_number, s.first_name";
    if ($stmt = mysqli_prepare($link, $sql_students)) {
        mysqli_stmt_bind_param($stmt, "ii", $selected_exam_schedule_id, $selected_class_id);
        mysqli_stmt_execute($stmt);
        $students_with_marks = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($link);
require_once './teacher_header.php';
?>

<!-- Custom Styles -->
<style>
    body { background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364); background-size: 400% 400%; animation: gradientBG 15s ease infinite; color: white; }
    @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
    .glassmorphism { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
    .form-select { background: rgba(0, 0, 0, 0.25); border-color: rgba(255, 255, 255, 0.2); }
    .form-select:disabled { background: rgba(0,0,0,0.5); opacity: 0.6; cursor: not-allowed; }
</style>

<div class="container mx-auto mt-28 mb-28 p-4 md:p-8">
    <h1 class="text-3xl md:text-4xl font-bold mb-6 text-white text-center">Upload Student Marks</h1>

    <?php if ($message): ?>
    <div class="max-w-4xl mx-auto mb-4 p-3 rounded-lg bg-green-500/80 text-center"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Step 1: Selection Form -->
    <div class="glassmorphism rounded-2xl p-4 md:p-6 mb-8">
        <form id="selectionForm" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
            <div>
                <label class="block text-sm font-semibold text-white/80 mb-2">Step 1: Select Class</label>
                <select name="class_id" onchange="this.form.submit()" class="w-full h-12 form-select rounded-lg">
                    <option value="">-- Select a Class --</option>
                    <?php foreach($assigned_classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php if($selected_class_id == $class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-white/80 mb-2">Step 2: Select Subject</label>
                <select name="subject_id" onchange="this.form.submit()" class="w-full h-12 form-select rounded-lg" <?php if(!$selected_class_id) echo 'disabled'; ?>>
                    <option value="">-- Select a Subject --</option>
                    <?php foreach($assigned_subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php if($selected_subject_id == $subject['id']) echo 'selected'; ?>><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-white/80 mb-2">Step 3: Select Exam</label>
                <select name="exam_schedule_id" class="w-full form-select h-12 rounded-lg" <?php if(!$selected_subject_id) echo 'disabled'; ?>>
                    <option value="">-- Select an Exam --</option>
                    <?php foreach($scheduled_exams as $exam): ?>
                        <option value="<?php echo $exam['id']; ?>" <?php if($selected_exam_schedule_id == $exam['id']) echo 'selected'; ?>><?php echo htmlspecialchars($exam['exam_name'] . ' (' . date('d-M-Y', strtotime($exam['exam_date'])) . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="w-full bg-blue-600 h-12 hover:bg-blue-700 font-bold py-2 px-4 rounded-lg" <?php if(!$selected_subject_id) echo 'disabled'; ?>>
                    <i class="fas fa-search mr-2"></i>Fetch Students
                </button>
            </div>
        </form>
    </div>

    <!-- Step 2: Marks Entry Form -->
    <?php if (!empty($students_with_marks)): ?>
        <div class="glassmorphism rounded-2xl p-4 md:p-6">
            <h2 class="text-2xl font-bold mb-2">Enter Marks</h2>
            <p class="text-white/80 mb-4">Exam: <strong class="text-teal-300"><?php echo htmlspecialchars($exam_details['exam_name']); ?></strong> | Max Marks: <strong class="text-teal-300"><?php echo htmlspecialchars($exam_details['max_marks']); ?></strong></p>

            <form method="POST">
                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
                <input type="hidden" name="exam_schedule_id" value="<?php echo $selected_exam_schedule_id; ?>">

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-white/20"><th class="p-3 w-24">Roll No.</th><th class="p-3">Student Name</th><th class="p-3 w-48">Marks Obtained</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_with_marks as $student): ?>
                                <tr class="border-b border-white/10 hover:bg-white/5">
                                    <td class="p-3 font-semibold"><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td class="p-3">
                                        <input type="number" step="0.01" min="0" max="<?php echo htmlspecialchars($exam_details['max_marks']); ?>" 
                                               name="marks[<?php echo $student['id']; ?>]" 
                                               value="<?php echo htmlspecialchars($student['marks_obtained'] ?? ''); ?>"
                                               class="w-full form-select rounded-lg" placeholder="Enter marks">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 text-right">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg transition-transform transform hover:scale-105">
                        Save Marks
                    </button>
                </div>
            </form>
        </div>
    <?php elseif($selected_class_id && $selected_subject_id && $selected_exam_schedule_id): ?>
        <div class="glassmorphism rounded-xl p-8 text-center">
            <p>No students found for the selected class.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once './teacher_footer.php'; ?>