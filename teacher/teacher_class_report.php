<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// 1. CHECK IF TEACHER IS A CLASS TEACHER & GET THEIR CLASS INFO
$class_teacher_info = null;
$sql_class_teacher = "SELECT id, class_name, section_name FROM classes WHERE teacher_id = ? LIMIT 1";
if ($stmt = mysqli_prepare($link, $sql_class_teacher)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $class_teacher_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// --- FILTER SETUP ---
$selected_exam_type_id = $_GET['exam_type_id'] ?? null;

// --- DATA FETCHING ---
$exam_types = [];
$class_subjects = [];
$students = [];
$marks_data = [];
$student_rankings = [];

if ($class_teacher_info) {
    $class_id = $class_teacher_info['id'];

    // 2. Get all exam types for the filter dropdown
    $sql_exam_types = "SELECT id, exam_name FROM exam_types ORDER BY exam_name";
    $exam_types = mysqli_fetch_all(mysqli_query($link, $sql_exam_types), MYSQLI_ASSOC);

    // 3. Get all subjects for this class to build table headers
    $sql_subjects = "SELECT s.id, s.subject_name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_id = ? ORDER BY s.subject_name";
    if ($stmt = mysqli_prepare($link, $sql_subjects)) {
        mysqli_stmt_bind_param($stmt, "i", $class_id);
        mysqli_stmt_execute($stmt);
        $class_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }

    // 4. Get all students in this class
    $sql_students = "SELECT id, roll_number, first_name, last_name, image_url, registration_number, dob FROM students WHERE class_id = ? ORDER BY roll_number";
    if ($stmt = mysqli_prepare($link, $sql_students)) {
        mysqli_stmt_bind_param($stmt, "i", $class_id);
        mysqli_stmt_execute($stmt);
        $students = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
    
    // 5. If an exam type is selected, fetch all relevant marks
    if ($selected_exam_type_id && !empty($students)) {
        $student_ids = array_column($students, 'id');
        $sql_marks = "SELECT em.student_id, es.subject_id, em.marks_obtained, es.max_marks
                      FROM exam_marks em
                      JOIN exam_schedule es ON em.exam_schedule_id = es.id
                      WHERE es.class_id = ? AND es.exam_type_id = ? AND em.student_id IN (" . implode(',', array_fill(0, count($student_ids), '?')) . ")";
        
        $params = array_merge([$class_id, $selected_exam_type_id], $student_ids);
        $types = "ii" . str_repeat('i', count($student_ids));

        if ($stmt = mysqli_prepare($link, $sql_marks)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_assoc($result)) {
                $marks_data[$row['student_id']][$row['subject_id']] = [
                    'obtained' => $row['marks_obtained'],
                    'max' => $row['max_marks']
                ];
            }
            mysqli_stmt_close($stmt);
        }

        // Calculate totals and rankings
        foreach ($students as $student) {
            $total_obtained = 0;
            $total_max = 0;
            foreach ($class_subjects as $subject) {
                $marks = $marks_data[$student['id']][$subject['id']] ?? null;
                if ($marks) {
                    $total_obtained += $marks['obtained'];
                    $total_max += $marks['max'];
                }
            }
            $percentage = ($total_max > 0) ? ($total_obtained / $total_max) * 100 : 0;
            $student_rankings[$student['id']] = [
                'name' => $student['first_name'] . ' ' . $student['last_name'],
                'image_url' => $student['image_url'],
                'percentage' => $percentage
            ];
        }
        // Sort students by percentage descending
        uasort($student_rankings, function($a, $b) {
            return $b['percentage'] <=> $a['percentage'];
        });
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
</style>

<div class="container mx-auto mt-28 p-4 md:p-8">
    <h1 class="text-3xl md:text-4xl font-bold mb-6 text-white text-center">Class Performance Report</h1>

    <?php if (!$class_teacher_info): ?>
        <div class="glassmorphism rounded-xl p-8 text-center max-w-2xl mx-auto">
            <h2 class="text-2xl font-bold mb-2">Access Denied</h2>
            <p class="text-white/80">This report is only available to designated Class Teachers.</p>
        </div>
    <?php else: ?>
        <div class="glassmorphism rounded-2xl p-4 md:p-6">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Report for Class: <?php echo htmlspecialchars($class_teacher_info['class_name'] . ' - ' . $class_teacher_info['section_name']); ?></h2>
                <form method="GET" class="flex items-center gap-4 mt-4 md:mt-0">
                    <label class="font-semibold">Exam:</label>
                    <select name="exam_type_id" onchange="this.form.submit()" class="form-select h-12 rounded-lg">
                        <option value="">-- Select Exam Type --</option>
                        <?php foreach($exam_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php if($selected_exam_type_id == $type['id']) echo 'selected'; ?>><?php echo htmlspecialchars($type['exam_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_exam_type_id && !empty($student_rankings)): ?>
                <!-- Leaderboard Section -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-center mb-4">Class Toppers</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                        <?php $top_three = array_slice($student_rankings, 0, 3, true); $podium = [1 => 'gold', 2 => 'silver', 3 => 'bronze']; $rank = 1; ?>
                        <?php foreach($top_three as $student_id => $data): ?>
                            <div class="glassmorphism p-4 rounded-xl border-2 <?php echo 'border-'.$podium[$rank].'-400'; ?>">
                                <i class="fas fa-trophy text-3xl text-<?php echo $podium[$rank]; ?>-400 mb-2"></i>
                                <img src="<?php echo htmlspecialchars($data['image_url'] ?? '../assets/images/default-avatar.png'); ?>" class="w-20 h-20 rounded-full mx-auto border-4 border-white/20 object-cover">
                                <p class="font-bold mt-2"><?php echo htmlspecialchars($data['name']); ?></p>
                                <p class="text-2xl font-bold text-<?php echo $podium[$rank]; ?>-300"><?php echo round($data['percentage'], 2); ?>%</p>
                                <p class="text-sm text-white/60">Rank <?php echo $rank++; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Marks Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-center">
                        <thead class="bg-black/20">
                            <tr>
                                <th class="p-3 text-left sticky left-0 bg-gray-800/50 backdrop-blur-sm z-10">Student Name</th>
                                <th class="p-3 text-left">Roll</th>
                                <?php foreach($class_subjects as $subject): ?>
                                    <th class="p-3 min-w-[120px]"><?php echo htmlspecialchars($subject['subject_name']); ?></th>
                                <?php endforeach; ?>
                                <th class="p-3 min-w-[120px]">Total Marks</th>
                                <th class="p-3 min-w-[120px]">Percentage</th>
                                

                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $student): ?>
                                <tr class="border-b border-white/10 hover:bg-white/5">
                                    <td class="p-2 text-left font-semibold sticky left-0 bg-gray-800/50 backdrop-blur-sm z-10"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td class="p-2 text-left"><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                    <?php 
                                        $total_obtained = 0;
                                        $total_max = 0;
                                        foreach($class_subjects as $subject): 
                                            $marks = $marks_data[$student['id']][$subject['id']] ?? null;
                                            $subject_percentage = 0;
                                            if($marks && $marks['max'] > 0) {
                                                $subject_percentage = ($marks['obtained'] / $marks['max']) * 100;
                                                $total_obtained += $marks['obtained'];
                                                $total_max += $marks['max'];
                                            }
                                    ?>
                                        <td class="p-2">
                                            <?php if($marks): 
                                                $color = $subject_percentage >= 80 ? 'text-green-400' : ($subject_percentage >= 50 ? 'text-yellow-400' : 'text-red-400');
                                            ?>
                                                <span class="font-bold text-lg <?php echo $color; ?>"><?php echo round($subject_percentage, 1); ?>%</span>
                                                <p class="text-xs text-white/60"><?php echo $marks['obtained'] . ' / ' . $marks['max']; ?></p>
                                            <?php else: echo '<span class="text-white/40">-</span>'; endif; ?>
                                        </td>
                                    <?php endforeach; 
                                        $overall_percentage = ($total_max > 0) ? ($total_obtained / $total_max) * 100 : 0;
                                        $total_color = $overall_percentage >= 80 ? 'text-green-300' : ($overall_percentage >= 50 ? 'text-yellow-300' : 'text-red-300');
                                    ?>
                                    <td class="p-2 font-bold"><?php echo $total_obtained . ' / ' . $total_max; ?></td>
                                    <td class="p-2 font-bold text-xl <?php echo $total_color; ?>"><?php echo round($overall_percentage, 2); ?>%</td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif($selected_exam_type_id): ?>
                <div class="text-center py-16"><p>No marks have been uploaded for this exam yet.</p></div>
            <?php else: ?>
                <div class="text-center py-16"><p>Please select an exam type to view the report.</p></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once './teacher_footer.php'; ?>