<?php
session_start();
require_once "../database/config.php";

// --- HELPER FUNCTIONS (No changes) ---
function numberToWords(int $number): string { if ($number < 0) return "INVALID"; if ($number == 0) return "ZERO"; $ones = ["", "ONE", "TWO", "THREE", "FOUR", "FIVE", "SIX", "SEVEN", "EIGHT", "NINE", "TEN", "ELEVEN", "TWELVE", "THIRTEEN", "FOURTEEN", "FIFTEEN", "SIXTEEN", "SEVENTEEN", "EIGHTEEN", "NINETEEN"]; $tens = ["", "", "TWENTY", "THIRTY", "FORTY", "FIFTY", "SIXTY", "SEVENTY", "EIGHTY", "NINETY"]; if ($number < 20) return $ones[$number]; if ($number < 100) { $ten = floor($number / 10); $one = $number % 10; return $tens[$ten] . ($one > 0 ? " " . $ones[$one] : ""); } if ($number < 1000) { $hundred = floor($number / 100); $remainder = $number % 100; return $ones[$hundred] . " HUNDRED" . ($remainder > 0 ? " " . numberToWords($remainder) : ""); } return "NUMBER TOO LARGE"; }
function calculateGrade(float $percentage): string { if ($percentage >= 91) return 'A1'; if ($percentage >= 81) return 'A2'; if ($percentage >= 71) return 'B1'; if ($percentage >= 61) return 'B2'; if ($percentage >= 51) return 'C1'; if ($percentage >= 41) return 'C2'; if ($percentage >= 33) return 'D'; return 'E'; }

// --- BACKEND LOGIC (No changes) ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') { header("location: ../login.php"); exit; }
$teacher_id = $_SESSION["id"];
$class_teacher_info = null;
$sql_class_teacher = "SELECT id, class_name, section_name FROM classes WHERE teacher_id = ? LIMIT 1";
if ($stmt = mysqli_prepare($link, $sql_class_teacher)) { mysqli_stmt_bind_param($stmt, "i", $teacher_id); mysqli_stmt_execute($stmt); $class_teacher_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt); }
$selected_exam_type_id = $_GET['exam_type_id'] ?? null; $combined_exam_ids = $_GET['combine_exam_ids'] ?? []; $is_yearly_report = ($selected_exam_type_id === 'yearly' && !empty($combined_exam_ids));
$report_title = "MARKS STATEMENT"; $examination_year = date('Y'); $exam_types = []; $report_data = []; $class_subjects = [];
if ($class_teacher_info) {
    $class_id = $class_teacher_info['id'];
    $sql_exam_types = "SELECT id, exam_name FROM exam_types ORDER BY exam_name"; $exam_types_result = mysqli_query($link, $sql_exam_types); while($row = mysqli_fetch_assoc($exam_types_result)) { $exam_types[] = $row; }
    $should_fetch_data = ($is_yearly_report || (is_numeric($selected_exam_type_id) && $selected_exam_type_id > 0));
    if ($should_fetch_data) {
        if ($is_yearly_report) { $report_title = "CONSOLIDATED YEARLY MARKS STATEMENT"; } else { foreach ($exam_types as $type) { if ($type['id'] == $selected_exam_type_id) { $report_title = strtoupper($type['exam_name']) . " MARKS STATEMENT"; break; } } }
        $sql_subjects = "SELECT s.id, s.subject_name, s.subject_code FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_id = ? ORDER BY s.id"; if ($stmt = mysqli_prepare($link, $sql_subjects)) { mysqli_stmt_bind_param($stmt, "i", $class_id); mysqli_stmt_execute($stmt); $class_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC); mysqli_stmt_close($stmt); }
        $students = []; $sql_students = "SELECT id, roll_number, first_name, last_name, mother_name, father_name, dob FROM students WHERE class_id = ? ORDER BY roll_number"; if ($stmt = mysqli_prepare($link, $sql_students)) { mysqli_stmt_bind_param($stmt, "i", $class_id); mysqli_stmt_execute($stmt); $students_result = mysqli_stmt_get_result($stmt); while($student = mysqli_fetch_assoc($students_result)) { $students[$student['id']] = $student; } mysqli_stmt_close($stmt); }
        $marks_data = [];
        if (!empty($students)) {
            $student_ids = array_keys($students); $ids_to_fetch = $is_yearly_report ? $combined_exam_ids : [$selected_exam_type_id]; $placeholders = implode(',', array_fill(0, count($ids_to_fetch), '?'));
            $sql_marks = "SELECT em.student_id, es.subject_id, em.marks_obtained, es.max_marks FROM exam_marks em JOIN exam_schedule es ON em.exam_schedule_id = es.id WHERE es.class_id = ? AND es.exam_type_id IN ($placeholders) AND em.student_id IN (" . implode(',', array_fill(0, count($student_ids), '?')) . ")";
            $params = array_merge([$class_id], $ids_to_fetch, $student_ids); $types = "i" . str_repeat('i', count($ids_to_fetch)) . str_repeat('i', count($student_ids));
            if ($stmt_marks = mysqli_prepare($link, $sql_marks)) {
                mysqli_stmt_bind_param($stmt_marks, $types, ...$params); mysqli_stmt_execute($stmt_marks); $result = mysqli_stmt_get_result($stmt_marks);
                while($row = mysqli_fetch_assoc($result)) {
                    $student_id = $row['student_id']; $subject_id = $row['subject_id']; if (!isset($marks_data[$student_id][$subject_id])) { $marks_data[$student_id][$subject_id] = ['obtained' => 0, 'max' => 0]; }
                    $marks_data[$student_id][$subject_id]['obtained'] += $row['marks_obtained']; $marks_data[$student_id][$subject_id]['max'] += $row['max_marks'];
                } mysqli_stmt_close($stmt_marks);
            }
        }
        foreach ($students as $student_id => $student_details) {
            $fail_count = 0; $report_data[$student_id] = $student_details; $total_obtained = 0; $total_max = 0;
            foreach ($class_subjects as $subject) {
                $marks = $marks_data[$student_id][$subject['id']] ?? null;
                if ($marks && $marks['max'] > 0) { $total_obtained += $marks['obtained']; $total_max += $marks['max']; if (($marks['obtained'] / $marks['max']) * 100 < 33) { $fail_count++; } } else { $fail_count++; }
            }
            $final_result = ($fail_count == 0) ? "PASS" : "FAIL"; $report_data[$student_id]['status'] = $final_result; $report_data[$student_id]['total_obtained'] = $total_obtained; $report_data[$student_id]['total_max'] = $total_max;
        }
    }
}
mysqli_close($link);
require_once './teacher_header.php';
?>

<!-- --- NEW: Styles for the exact replica design --- -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap');
    body { background-color: #e5e7eb; } /* Light gray background to highlight the paper */
    .marksheet-container {
        width: 21cm;
        min-height: 29.7cm;
        padding: 1cm;
        margin: 1rem auto;
        background: white;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        position: relative;
    }
    .marksheet-double-border { border: 1px solid #6b7280; padding: 4px; }
    .marksheet-inner-content { border: 2px solid #000; padding: 1.5cm; }
    .marksheet-table { border-collapse: collapse; width: 100%; font-size: 11pt; }
    .marksheet-table th, .marksheet-table td { border: 1px solid #374151; padding: 4px 8px; }
    .watermark { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 100px; z-index: 1; overflow: hidden; }
    .watermark span { font-size: 5rem; font-weight: bold; color: #000; opacity: 0.04; transform: rotate(-45deg); user-select: none; }
    .temp-no-print { display: none !important; }

    @media print {
        @page { size: A4; margin: 0; }
        body > *:not(.printable-content) { display: none !important; }
        body { background-color: white; }
        .printable-content { margin: 0; padding: 0; }
        .marksheet-container { margin: 0; box-shadow: none; border: none; }
        .no-print { display: none !important; }
    }
</style>

<div class="container mx-auto mt-28 printable-content">
    <div class="no-print bg-white rounded-xl shadow p-4 md:p-6 mb-8 max-w-4xl mx-auto">
         <form method="GET" id="marksheetForm">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <h1 class="text-2xl font-bold text-gray-800 flex-shrink-0">Marksheets</h1>
                    <div><label for="examTypeSelect" class="font-semibold text-sm sr-only md:not-sr-only">Report Type:</label><select name="exam_type_id" id="examTypeSelect" onchange="handleExamSelection()" class="border-gray-300 rounded-md shadow-sm w-full sm:w-auto"><option value="">-- Choose an Option --</option><?php foreach($exam_types as $type): ?><option value="<?php echo $type['id']; ?>" <?php if($selected_exam_type_id == $type['id']) echo 'selected'; ?>><?php echo htmlspecialchars($type['exam_name']); ?></option><?php endforeach; ?><option value="yearly" <?php if($selected_exam_type_id == 'yearly') echo 'selected'; ?>>-- Yearly Result --</option></select></div>
                </div>
                <div class="flex items-center gap-4 self-end md:self-auto"><button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg"><i class="fas fa-cogs mr-2"></i>Generate</button><button type="button" onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg"><i class="fas fa-print mr-2"></i>Print All</button></div>
            </div>
            <div id="yearlyOptions" class="mt-4 p-4 border-t border-gray-200" style="display: none;"><p class="font-semibold mb-2 text-gray-700">Select exams to combine:</p><div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4"><?php foreach($exam_types as $type): ?><label class="flex items-center space-x-2 p-2 rounded-md bg-gray-50 border border-gray-200 hover:bg-gray-100 cursor-pointer"><input type="checkbox" name="combine_exam_ids[]" value="<?php echo $type['id']; ?>" <?php if(in_array($type['id'], $combined_exam_ids)) echo 'checked'; ?> class="rounded text-blue-600 focus:ring-blue-500"><span><?php echo htmlspecialchars($type['exam_name']); ?></span></label><?php endforeach; ?></div></div>
        </form>
    </div>

    <?php if (!$class_teacher_info): ?>
        <div class="bg-white rounded-xl shadow p-8 text-center max-w-2xl mx-auto"><h2 class="text-2xl font-bold mb-2 text-red-600">Access Denied</h2><p>This page is only for Class Teachers.</p></div>
    <?php elseif ($should_fetch_data && !empty($report_data)): ?>
        <?php foreach($report_data as $student_id => $data): ?>
            <div class="marksheet-container" id="container-student-<?php echo $student_id; ?>">
                <div class="marksheet-double-border">
                    <div class="marksheet-inner-content">
                        <div class="watermark"><?php for($i=0; $i<9; $i++): ?><span>Mednova SCHOOL</span><?php endfor; ?></div>
                        <div class="relative z-10">
                            <!-- --- NEW: Replicated Header --- -->
                            <header class="mb-6">
                                <div class="flex justify-between items-center">
                                    <img src="./assets/images/school-logo.png" alt="School Logo" class="h-20 w-20 object-contain">
                                    <div class="text-center">
                                        <h1 class="text-2xl font-bold tracking-wider">CENTRAL BOARD OF SECONDARY EDUCATION</h1>
                                        <h2 class="text-xl font-bold tracking-wide"><?php echo htmlspecialchars($report_title); ?></h2>
                                        <h3 class="text-base mt-1">Mednova School, Madhubani - Examination <?php echo htmlspecialchars($examination_year); ?></h3>
                                    </div>
                                    <img src="./assets/images/cbse-logo.png" alt="Board Logo" class="h-20 w-20 object-contain">
                                </div>
                            </header>

                            <!-- --- NEW: Replicated Candidate Details --- -->
                            <section class="mb-6 text-sm">
                                <div class="text-center mb-4"><span class="px-4 py-1 text-lg font-bold border-t-2 border-b-2 border-black tracking-widest">CANDIDATE'S DETAILS</span></div>
                                <div class="flex justify-between" style="font-size: 11pt;">
                                    <div class="w-1/2 space-y-2">
                                        <p><span class="w-32 inline-block">STUDENT'S NAME</span>: <b class="ml-2"><?php echo strtoupper(htmlspecialchars($data['first_name'] . ' ' . $data['last_name'])); ?></b></p>
                                        <p><span class="w-32 inline-block">MOTHER'S NAME</span>: <b class="ml-2"><?php echo strtoupper(htmlspecialchars($data['mother_name'] ?: '-')); ?></b></p>
                                        <p><span class="w-32 inline-block">FATHER'S NAME</span>: <b class="ml-2"><?php echo strtoupper(htmlspecialchars($data['father_name'] ?: '-')); ?></b></p>
                                        <p><span class="w-32 inline-block">SCHOOL</span>: <b class="ml-2">Mednova School, Madhubani</b></p>
                                    </div>
                                    <div class="w-1/2 space-y-2">
                                        <p><span class="w-32 inline-block">ROLL NUMBER</span>: <b class="ml-2"><?php echo htmlspecialchars($data['roll_number']); ?></b></p>
                                        <p><span class="w-32 inline-block">CLASS</span>: <b class="ml-2"><?php echo htmlspecialchars($class_teacher_info['class_name'] . ' - ' . $class_teacher_info['section_name']); ?></b></p>
                                        <p><span class="w-32 inline-block">DATE OF BIRTH</span>: <b class="ml-2"><?php echo !empty($data['dob']) ? strtoupper(date('d F Y', strtotime($data['dob']))) : '-'; ?></b></p>
                                    </div>
                                </div>
                            </section>
                            
                            <!-- --- NEW: Replicated Marks Table --- -->
                            <section><table class="marksheet-table">
                                    <thead class="font-bold bg-gray-100 text-center"><tr><th>SUB CODE</th><th>SUBJECT</th><th>MAX MARKS</th><th>MARKS OBTAINED</th><th>MARKS IN WORDS</th><th>GRADE</th></tr></thead>
                                    <tbody><?php foreach ($class_subjects as $subject): $marks = $marks_data[$student_id][$subject['id']] ?? null; $percentage = ($marks && $marks['max'] > 0) ? ($marks['obtained'] / $marks['max']) * 100 : 0; ?><tr><td class="text-center"><?php echo htmlspecialchars($subject['subject_code']); ?></td><td><?php echo strtoupper(htmlspecialchars($subject['subject_name'])); ?></td><td class="text-center"><?php echo $marks ? $marks['max'] : '-'; ?></td><td class="text-center font-bold"><?php echo $marks ? number_format($marks['obtained'], 2) : '-'; ?></td><td><?php echo $marks ? numberToWords((int)$marks['obtained']) : '-'; ?></td><td class="text-center font-bold"><?php echo $marks ? calculateGrade($percentage) : '-'; ?></td></tr><?php endforeach; ?></tbody>
                                    <tfoot class="font-bold"><tr><td colspan="2" class="text-right">GRAND TOTAL:</td><td class="text-center"><?php echo $data['total_max']; ?></td><td class="text-center"><?php echo number_format($data['total_obtained'], 2); ?></td><td colspan="2">Result: <span class="ml-4"><?php echo $data['status']; ?></span></td></tr></tfoot>
                            </table></section>

                            <!-- --- NEW: Replicated Footer --- -->
                            <footer class="mt-20"><div class="flex justify-between items-end">
                                <span>Date: <?php echo date('d/m/Y'); ?></span>
                                <div class="text-center w-56">
                                    <div class="h-12"></div>
                                    <p class="border-t border-black pt-1">Controller of Examinations</p>
                                </div>
                            </div></footer>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php elseif($should_fetch_data): ?>
        <div class="bg-white rounded-lg shadow-md text-center py-16"><p>No marks have been uploaded for the selected exam(s).</p></div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md text-center py-16 no-print"><p>Please select a report type and click 'Generate' to view the marksheets.</p></div>
    <?php endif; ?>
</div>

<script>
    function handleExamSelection() { const select = document.getElementById('examTypeSelect'); const yearlyOptions = document.getElementById('yearlyOptions'); yearlyOptions.style.display = (select.value === 'yearly') ? 'block' : 'none'; }
    document.addEventListener('DOMContentLoaded', handleExamSelection);
    // Note: Individual download button has been removed as per the new design focusing on the overall printout.
    // If you need it back, the previous code's JavaScript function can be re-added.
</script>

<?php require_once './teacher_footer.php'; ?>