<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}
$student_id = $_SESSION["id"];

// --- 1. FETCH CORE STUDENT, CLASS, TEACHER, AND VAN DETAILS ---
$student = null;
$sql_student = "SELECT 
                    s.*, 
                    c.class_name, c.section_name,
                    t.full_name AS class_teacher_name,
                    v.van_number, v.route_details, v.driver_name
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN teachers t ON c.teacher_id = t.id
                LEFT JOIN vans v ON s.van_id = v.id
                WHERE s.id = ?";
if ($stmt = mysqli_prepare($link, $sql_student)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
if (!$student) {
    die("Error: Could not find student profile.");
}
$student_class_id = $student['class_id'];

// --- 2. FETCH FEE SUMMARY ---
$fee_summary = [];
$sql_fees = "SELECT 
                SUM(amount_due) as total_due,
                SUM(amount_paid) as total_paid,
                SUM(CASE WHEN status IN ('Unpaid', 'Partially Paid') THEN amount_due - amount_paid ELSE 0 END) as outstanding_balance
             FROM student_fees 
             WHERE student_id = ?";
if ($stmt = mysqli_prepare($link, $sql_fees)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fee_summary = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// --- 3. FETCH CLASS SUBJECTS ---
$subjects = [];
$sql_subjects = "SELECT s.subject_name, s.subject_code
                 FROM class_subjects cs
                 JOIN subjects s ON cs.subject_id = s.id
                 WHERE cs.class_id = ?";
if ($stmt = mysqli_prepare($link, $sql_subjects)) {
    mysqli_stmt_bind_param($stmt, "i", $student_class_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $subjects = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// --- 4. FETCH EXAM MARKS ---
$marks_by_exam = [];
$sql_marks = "SELECT 
                et.exam_name,
                s.subject_name,
                em.marks_obtained,
                es.max_marks,
                es.passing_marks
             FROM exam_marks em
             JOIN exam_schedule es ON em.exam_schedule_id = es.id
             JOIN exam_types et ON es.exam_type_id = et.id
             JOIN subjects s ON es.subject_id = s.id
             WHERE em.student_id = ?
             ORDER BY et.exam_name, s.subject_name";
if ($stmt = mysqli_prepare($link, $sql_marks)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $marks_by_exam[$row['exam_name']][] = $row;
    }
    mysqli_stmt_close($stmt);
}

// --- 5. FETCH ONLINE TEST RESULTS ---
$test_results = [];
$sql_tests = "SELECT
                ot.title,
                s.subject_name,
                sta.score,
                sta.total_marks,
                sta.end_time
             FROM student_test_attempts sta
             JOIN online_tests ot ON sta.test_id = ot.id
             JOIN subjects s ON ot.subject_id = s.id
             WHERE sta.student_id = ? AND sta.status = 'Completed'
             ORDER BY sta.end_time DESC";
if ($stmt = mysqli_prepare($link, $sql_tests)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $test_results = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6 space-y-8">
    
    <!-- Main Profile Card -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="md:flex">
            <div class="md:flex-shrink-0 p-6 flex flex-col items-center justify-center bg-gray-50 md:w-1/4">
                <img class="h-32 w-32 rounded-full object-cover" src="<?php echo htmlspecialchars($student['image_url'] ?? 'https://via.placeholder.com/150'); ?>" alt="Student profile picture">
                <h2 class="mt-4 text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                <p class="text-gray-600"><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?></p>
                <span class="mt-2 text-sm font-semibold py-1 px-3 rounded-full bg-blue-100 text-blue-800">Roll No: <?php echo htmlspecialchars($student['roll_number'] ?? 'N/A'); ?></span>
            </div>
            <div class="p-8 flex-grow grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                <div><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Registration No.</h3><p class="text-gray-900"><?php echo htmlspecialchars($student['registration_number']); ?></p></div>
                <div><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Date of Birth</h3><p class="text-gray-900"><?php echo date("F j, Y", strtotime($student['dob'])); ?></p></div>
                <div><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Gender</h3><p class="text-gray-900"><?php echo htmlspecialchars($student['gender']); ?></p></div>
                <div><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Blood Group</h3><p class="text-gray-900"><?php echo htmlspecialchars($student['blood_group'] ?? 'N/A'); ?></p></div>
                <div><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Contact Number</h3><p class="text-gray-900"><?php echo htmlspecialchars($student['phone_number'] ?? 'N/A'); ?></p></div>
                <div><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Email</h3><p class="text-gray-900"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></p></div>
                <div class="md:col-span-2"><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Address</h3><p class="text-gray-900"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></p></div>
                <hr class="md:col-span-2 my-2">
                <div><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Father's Name</h3><p class="text-gray-900"><?php echo htmlspecialchars($student['father_name'] ?? 'N/A'); ?></p></div>
                <div><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Mother's Name</h3><p class="text-gray-900"><?php echo htmlspecialchars($student['mother_name'] ?? 'N/A'); ?></p></div>
                <div><h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Parent's Contact</h3><p class="text-gray-900"><?php echo htmlspecialchars($student['parent_phone_number'] ?? 'N/A'); ?></p></div>
            </div>
        </div>
    </div>

    <!-- Quick Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Fee Status Card -->
        <div class="bg-white p-6 rounded-xl shadow-md flex items-start gap-4">
            <div class="flex-shrink-0 h-12 w-12 rounded-full flex items-center justify-center bg-red-100 text-red-600"><i class="fas fa-dollar-sign text-xl"></i></div>
            <div>
                <h3 class="font-bold text-gray-800">Fee Status</h3>
                <p class="text-2xl font-bold text-red-600 mt-1">₹<?php echo number_format($fee_summary['outstanding_balance'] ?? 0, 2); ?></p>
                <p class="text-sm text-gray-500">Outstanding Balance</p>
            </div>
        </div>
        <!-- Class Teacher Card -->
        <div class="bg-white p-6 rounded-xl shadow-md flex items-start gap-4">
            <div class="flex-shrink-0 h-12 w-12 rounded-full flex items-center justify-center bg-green-100 text-green-600"><i class="fas fa-chalkboard-teacher text-xl"></i></div>
            <div>
                <h3 class="font-bold text-gray-800">Class Teacher</h3>
                <p class="text-lg font-semibold text-gray-900 mt-1"><?php echo htmlspecialchars($student['class_teacher_name'] ?? 'Not Assigned'); ?></p>
            </div>
        </div>
        <!-- Van Details Card -->
        <div class="bg-white p-6 rounded-xl shadow-md flex items-start gap-4">
            <div class="flex-shrink-0 h-12 w-12 rounded-full flex items-center justify-center bg-yellow-100 text-yellow-600"><i class="fas fa-bus-alt text-xl"></i></div>
            <div>
                <h3 class="font-bold text-gray-800">Transport Details</h3>
                <?php if ($student['van_service_taken']): ?>
                    <p class="text-lg font-semibold text-gray-900 mt-1"><?php echo htmlspecialchars($student['van_number']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($student['route_details']); ?></p>
                <?php else: ?>
                    <p class="text-lg font-semibold text-gray-500 mt-1">Not Availed</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabbed Details Section -->
    <div x-data="{ tab: 'subjects' }" class="bg-white rounded-xl shadow-md">
        <!-- Tabs -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6 px-6">
                <a href="#" @click.prevent="tab = 'subjects'" :class="{ 'border-blue-500 text-blue-600': tab === 'subjects', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': tab !== 'subjects' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Subjects</a>
                <a href="#" @click.prevent="tab = 'exams'" :class="{ 'border-blue-500 text-blue-600': tab === 'exams', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': tab !== 'exams' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Exam Results</a>
                <a href="#" @click.prevent="tab = 'tests'" :class="{ 'border-blue-500 text-blue-600': tab === 'tests', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': tab !== 'tests' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Online Tests</a>
            </nav>
        </div>
        <!-- Tab Content -->
        <div class="p-6">
            <!-- Subjects Tab -->
            <div x-show="tab === 'subjects'">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Your Subjects</h3>
                <?php if (empty($subjects)): ?> <p class="text-gray-500">No subjects assigned to your class yet.</p>
                <?php else: ?>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach($subjects as $subject): ?>
                            <li class="bg-gray-50 p-4 rounded-lg border"><?php echo htmlspecialchars($subject['subject_name']); ?> <span class="text-gray-400">(<?php echo htmlspecialchars($subject['subject_code']); ?>)</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Exam Results Tab -->
            <div x-show="tab === 'exams'" style="display: none;">
                 <?php if (empty($marks_by_exam)): ?> <p class="text-gray-500">No exam results have been published yet.</p>
                 <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach($marks_by_exam as $exam_name => $marks): ?>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($exam_name); ?> Results</h3>
                                <div class="overflow-x-auto border rounded-lg"><table class="min-w-full">
                                    <thead class="bg-gray-50"><tr><th class="p-3 text-left">Subject</th><th class="p-3 text-center">Marks Obtained</th><th class="p-3 text-center">Max Marks</th><th class="p-3 text-center">Percentage</th><th class="p-3 text-center">Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach($marks as $mark): $percentage = ($mark['marks_obtained'] / $mark['max_marks']) * 100; $passed = $mark['marks_obtained'] >= $mark['passing_marks']; ?>
                                        <tr class="border-t"><td class="p-3 font-medium"><?php echo htmlspecialchars($mark['subject_name']); ?></td><td class="p-3 text-center"><?php echo htmlspecialchars($mark['marks_obtained']); ?></td><td class="p-3 text-center"><?php echo htmlspecialchars($mark['max_marks']); ?></td><td class="p-3 text-center font-semibold"><?php echo number_format($percentage, 2); ?>%</td><td class="p-3 text-center"><span class="font-bold <?php echo $passed ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $passed ? 'Pass' : 'Fail'; ?></span></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                 <?php endif; ?>
            </div>

            <!-- Online Tests Tab -->
            <div x-show="tab === 'tests'" style="display: none;">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Completed Online Tests</h3>
                <?php if (empty($test_results)): ?> <p class="text-gray-500">You have not completed any online tests.</p>
                <?php else: ?>
                    <div class="overflow-x-auto border rounded-lg"><table class="min-w-full">
                        <thead class="bg-gray-50"><tr><th class="p-3 text-left">Test Title</th><th class="p-3 text-left">Subject</th><th class="p-3 text-center">Score</th><th class="p-3 text-center">Percentage</th><th class="p-3 text-left">Completed On</th></tr></thead>
                        <tbody>
                            <?php foreach($test_results as $test): $percentage = ($test['score'] / $test['total_marks']) * 100; ?>
                            <tr class="border-t"><td class="p-3 font-medium"><?php echo htmlspecialchars($test['title']); ?></td><td class="p-3"><?php echo htmlspecialchars($test['subject_name']); ?></td><td class="p-3 text-center font-semibold"><?php echo htmlspecialchars($test['score']); ?> / <?php echo htmlspecialchars($test['total_marks']); ?></td><td class="p-3 text-center font-semibold"><?php echo number_format($percentage, 2); ?>%</td><td class="p-3"><?php echo date("M j, Y, g:i a", strtotime($test['end_time'])); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

</body>
</html>
<?php require_once './student_footer.php'; ?>