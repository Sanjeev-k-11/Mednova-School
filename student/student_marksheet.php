<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./student_header.php";   // Includes student-specific authentication and sidebar

// --- HELPER FUNCTIONS ---
function numberToWords(float $number): string { // Changed type hint to float as marks can be decimal
    if ($number < 0) return "INVALID";
    $integerPart = floor($number); // Get integer part for words
    if ($integerPart == 0) return "ZERO";
    
    $ones = ["", "ONE", "TWO", "THREE", "FOUR", "FIVE", "SIX", "SEVEN", "EIGHT", "NINE", "TEN", "ELEVEN", "TWELVE", "THIRTEEN", "FOURTEEN", "FIFTEEN", "SIXTEEN", "SEVENTEEN", "EIGHTEEN", "NINETEEN"];
    $tens = ["", "", "TWENTY", "THIRTY", "FORTY", "FIFTY", "SIXTY", "SEVENTY", "EIGHTY", "NINETY"];
    
    $words = "";
    if ($integerPart < 20) {
        $words = $ones[$integerPart];
    } elseif ($integerPart < 100) {
        $ten = floor($integerPart / 10);
        $one = $integerPart % 10;
        $words = $tens[$ten] . ($one > 0 ? " " . $ones[$one] : "");
    } elseif ($integerPart < 1000) {
        $hundred = floor($integerPart / 100);
        $remainder = $integerPart % 100;
        $words = $ones[$hundred] . " HUNDRED" . ($remainder > 0 ? " " . numberToWords($remainder) : "");
    } else {
        return "NUMBER TOO LARGE"; // Extend for larger numbers if needed
    }
    
    // Handle decimals if any, though usually not for "marks in words"
    // $decimalPart = $number - $integerPart;
    // if ($decimalPart > 0) {
    //     $words .= " POINT " . numberToWords(round($decimalPart * 100)); // Example for two decimal places
    // }
    
    return trim($words);
}

function calculateGrade(float $percentage): string {
    if ($percentage >= 91) return 'A1';
    if ($percentage >= 81) return 'A2';
    if ($percentage >= 71) return 'B1';
    if ($percentage >= 61) return 'B2';
    if ($percentage >= 51) return 'C1';
    if ($percentage >= 41) return 'C2';
    if ($percentage >= 33) return 'D'; // Standard passing percentage
    return 'E';
}

// --- BACKEND LOGIC ---
// Authenticate as Student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php"); // Redirect to login page if not logged in as a student
    exit;
}

// Get the logged-in student's ID from the session
// IMPORTANT: Ensure your login process sets $_SESSION['user_id'] (or $_SESSION['id'] if that's what you use)
// to the student's actual ID from the `students` table.
// Using 'user_id' as it's a more common and robust key. If your login uses 'id', change 'user_id' to 'id' below.
$student_id = $_SESSION['id'] ?? null; 

// --- CRITICAL CHECK: Validate student_id from session ---
if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Authentication Error!</strong>
            <span class='block sm:inline'> Your student ID is missing or invalid in the session. Please log in again.</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

// Fetch Student Profile and Class Details
$student_profile = null;
$class_info = null; // Will store class_name, section_name, and class_teacher_name

$sql_student_data = "
    SELECT
        s.id, s.roll_number, s.first_name, s.last_name, s.middle_name,
        s.mother_name, s.father_name, s.dob, s.class_id,
        c.class_name, c.section_name,
        t.full_name AS class_teacher_name 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN teachers t ON c.teacher_id = t.id 
    WHERE s.id = ? LIMIT 1
";
if ($stmt = mysqli_prepare($link, $sql_student_data)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $student_profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($student_profile) {
            $class_info = [
                'id' => $student_profile['class_id'],
                'class_name' => $student_profile['class_name'],
                'section_name' => $student_profile['section_name'],
                'class_teacher_name' => $student_profile['class_teacher_name'] 
            ];
        }
    } else {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>Database Error!</strong>
                <span class='block sm:inline'> Could not execute student data query: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</span>
              </div>";
        require_once "./student_footer.php";
        if($link) mysqli_close($link);
        exit();
    }
    mysqli_stmt_close($stmt);
} else {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Database Error!</strong>
            <span class='block sm:inline'> Could not prepare student data query: " . htmlspecialchars(mysqli_error($link)) . "</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

if (!$student_profile) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Error:</strong>
            <span class='block sm:inline'> Your profile could not be found or your class is invalid. Please contact support.</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

// Initialize variables for report generation
$selected_exam_type_id = $_GET['exam_type_id'] ?? null;
$combined_exam_ids = $_GET['combine_exam_ids'] ?? [];
$is_yearly_report = ($selected_exam_type_id === 'yearly' && !empty($combined_exam_ids));

$report_title = "MARKS STATEMENT";
$examination_year = date('Y');
$exam_types = [];
$class_subjects = [];
$marks_data = [];
$report_for_student = []; // Will hold the single student's report data

// Fetch all exam types for the dropdown
$sql_exam_types = "SELECT id, exam_name FROM exam_types ORDER BY exam_name";
$exam_types_result = mysqli_query($link, $sql_exam_types);
while($row = mysqli_fetch_assoc($exam_types_result)) {
    $exam_types[] = $row;
}

$should_fetch_data = ($is_yearly_report || (is_numeric($selected_exam_type_id) && $selected_exam_type_id > 0));

if ($should_fetch_data) {
    // Set report title based on selection
    if ($is_yearly_report) {
        $report_title = "CONSOLIDATED YEARLY MARKS STATEMENT";
    } else {
        foreach ($exam_types as $type) {
            if ($type['id'] == $selected_exam_type_id) {
                $report_title = strtoupper($type['exam_name']) . " MARKS STATEMENT";
                break;
            }
        }
    }

    // Fetch subjects for the student's class
    $sql_subjects = "SELECT s.id, s.subject_name, s.subject_code FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_id = ? ORDER BY s.id";
    if ($stmt = mysqli_prepare($link, $sql_subjects)) {
        mysqli_stmt_bind_param($stmt, "i", $class_info['id']);
        mysqli_stmt_execute($stmt);
        $class_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }

    // Fetch marks for the logged-in student
    $ids_to_fetch = $is_yearly_report ? $combined_exam_ids : [$selected_exam_type_id];
    $placeholders = implode(',', array_fill(0, count($ids_to_fetch), '?'));

    // Fetch exam schedules to know expected subjects and max_marks
    $sql_exam_schedules = "
        SELECT 
            es.subject_id, 
            es.max_marks,
            em.marks_obtained -- Also try to fetch marks from exam_marks
        FROM exam_schedule es
        LEFT JOIN exam_marks em ON es.id = em.exam_schedule_id AND em.student_id = ?
        WHERE es.class_id = ? 
          AND es.exam_type_id IN ($placeholders)
    ";
    
    $schedule_params = array_merge([$student_id, $class_info['id']], $ids_to_fetch);
    $schedule_types = "ii" . str_repeat('i', count($ids_to_fetch));

    $expected_subjects_in_exam = []; // To track subjects that were part of the exam schedule

    if ($stmt_schedules = mysqli_prepare($link, $sql_exam_schedules)) {
        mysqli_stmt_bind_param($stmt_schedules, $schedule_types, ...$schedule_params);
        if (mysqli_stmt_execute($stmt_schedules)) {
            $result_schedules = mysqli_stmt_get_result($stmt_schedules);
            while($row = mysqli_fetch_assoc($result_schedules)) {
                $subject_id = $row['subject_id'];
                if (!isset($marks_data[$subject_id])) {
                    $marks_data[$subject_id] = ['obtained' => null, 'max' => 0]; // Initialize obtained as null, max as 0
                }
                
                // Aggregate max marks for combined exams
                $marks_data[$subject_id]['max'] += $row['max_marks']; 
                $expected_subjects_in_exam[$subject_id] = true; // Mark this subject as expected in the exam

                // If marks were obtained for this instance, sum them. 
                // If any instance is null, the overall 'obtained' for this subject remains null (PENDING).
                if ($row['marks_obtained'] !== null) {
                    if ($marks_data[$subject_id]['obtained'] === null) {
                        $marks_data[$subject_id]['obtained'] = $row['marks_obtained']; // First valid mark
                    } else {
                        $marks_data[$subject_id]['obtained'] += $row['marks_obtained']; // Sum subsequent valid marks
                    }
                } else {
                    // If a specific exam for this subject had NULL marks, the aggregated marks for the subject should also be NULL (pending)
                    $marks_data[$subject_id]['obtained'] = null; 
                }
            }
        } else {
            error_log("Database Error executing exam schedules query: " . mysqli_stmt_error($stmt_schedules));
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                    <strong class='font-bold'>Database Error!</strong>
                    <span class='block sm:inline'> Could not execute exam schedules query: " . htmlspecialchars(mysqli_stmt_error($stmt_schedules)) . "</span>
                  </div>";
        }
        mysqli_stmt_close($stmt_schedules);
    } else {
        error_log("Database Error preparing exam schedules query: " . mysqli_error($link));
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>Database Error!</strong>
                <span class='block sm:inline'> Could not prepare exam schedules query: " . htmlspecialchars(mysqli_error($link)) . "</span>
              </div>";
    }


    // Process marks for the single student for the final report structure
    $fail_count = 0; // Subjects failed due to low score (where marks were available)
    $ungraded_subject_count = 0; // Subjects that were scheduled (max_marks > 0) but have no obtained marks (null)
    $total_subjects_graded = 0; // Count of subjects where marks were obtained and max_marks > 0
    $total_max_for_graded_subjects = 0; // Sum of max_marks for subjects that actually received marks
    $total_obtained_for_graded_subjects = 0; // Sum of obtained_marks for subjects that actually received marks

    $report_for_student_subjects = [];

    foreach ($class_subjects as $subject) {
        $subject_id = $subject['id'];
        $marks_detail_for_subject = $marks_data[$subject_id] ?? ['obtained' => null, 'max' => 0];

        $subject_max_marks = $marks_detail_for_subject['max'];
        $subject_obtained_marks = $marks_detail_for_subject['obtained']; 

        $display_obtained = '-';
        $display_max = '-';
        $subject_grade = '-';
        $subject_marks_in_words = '-';
        $percentage = 0;

        // If this subject was part of the selected exam(s) schedule and had max marks
        if (isset($expected_subjects_in_exam[$subject_id]) && $subject_max_marks > 0) {
            $display_max = $subject_max_marks; // Always show max marks if scheduled

            if ($subject_obtained_marks !== null) { // Marks were recorded for this subject
                $total_subjects_graded++;
                $total_obtained_for_graded_subjects += $subject_obtained_marks;
                $total_max_for_graded_subjects += $subject_max_marks;

                $display_obtained = number_format($subject_obtained_marks, 2);
                $percentage = ($subject_obtained_marks / $subject_max_marks) * 100;
                $subject_grade = calculateGrade($percentage);
                $subject_marks_in_words = numberToWords($subject_obtained_marks);

                if ($percentage < 33) {
                    $fail_count++; // Failed due to low score
                }
            } else { // Subject was scheduled but marks are missing (null)
                $ungraded_subject_count++;
            }
        } 
        // If subject was not part of the exam schedules or had 0 max marks,
        // it will naturally show '-' as default, which is correct.

        $report_for_student_subjects[$subject_id] = [
            'subject_name' => $subject['subject_name'],
            'subject_code' => $subject['subject_code'],
            'obtained' => $display_obtained,
            'max' => $display_max,
            'percentage' => $percentage, // Retain actual percentage for potential future use
            'grade' => $subject_grade,
            'marks_in_words' => $subject_marks_in_words
        ];
    }

    // Determine overall final result status
    $final_result_status = "N/A"; // Default if no subjects were part of the report

    if (!empty($expected_subjects_in_exam)) { // If at least one subject was expected in the exam schedule
        if ($ungraded_subject_count > 0) {
            $final_result_status = "PENDING"; // Some subjects not graded
        } elseif ($fail_count > 0) {
            $final_result_status = "FAIL"; // All graded, but some failed
        } else {
            $final_result_status = "PASS"; // All graded and passed
        }
    }
    
    $report_for_student = [
        'profile' => $student_profile,
        'class_info' => $class_info,
        'marks_by_subject' => $report_for_student_subjects,
        'total_obtained' => $total_obtained_for_graded_subjects,
        'total_max' => $total_max_for_graded_subjects,
        'status' => $final_result_status 
    ];
}

mysqli_close($link);
?>

<!-- --- NEW: Styles for the exact replica design --- -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap');
    body { background-color: #e5e7eb; } /* Light gray background to highlight the paper */
    .marksheet-container {
        width: 21cm; /* A4 width */
        min-height: 29.7cm; /* A4 height */
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
                    <h1 class="text-2xl font-bold text-gray-800 flex-shrink-0">My Marksheets</h1>
                    <div>
                        <label for="examTypeSelect" class="font-semibold text-sm sr-only md:not-sr-only">Report Type:</label>
                        <select name="exam_type_id" id="examTypeSelect" onchange="handleExamSelection()" class="border-gray-300 rounded-md shadow-sm w-full sm:w-auto">
                            <option value="">-- Choose an Exam --</option>
                            <?php foreach($exam_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>" <?php if($selected_exam_type_id == $type['id']) echo 'selected'; ?>><?php echo htmlspecialchars($type['exam_name']); ?></option>
                            <?php endforeach; ?>
                            <option value="yearly" <?php if($selected_exam_type_id == 'yearly') echo 'selected'; ?>>-- Yearly Result (Consolidated) --</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-4 self-end md:self-auto">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">
                        <i class="fas fa-cogs mr-2"></i>Generate
                    </button>
                    <?php if ($should_fetch_data && !empty($report_for_student)): // Only show print button if data is generated ?>
                        <button type="button" onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                            <i class="fas fa-print mr-2"></i>Print Marksheet
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div id="yearlyOptions" class="mt-4 p-4 border-t border-gray-200" style="display: <?php echo ($selected_exam_type_id === 'yearly' ? 'block' : 'none'); ?>;">
                <p class="font-semibold mb-2 text-gray-700">Select exams to combine for yearly report:</p>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <?php foreach($exam_types as $type): ?>
                        <label class="flex items-center space-x-2 p-2 rounded-md bg-gray-50 border border-gray-200 hover:bg-gray-100 cursor-pointer">
                            <input type="checkbox" name="combine_exam_ids[]" value="<?php echo $type['id']; ?>" <?php if(in_array($type['id'], $combined_exam_ids)) echo 'checked'; ?> class="rounded text-blue-600 focus:ring-blue-500">
                            <span><?php echo htmlspecialchars($type['exam_name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>

    <?php if (!$student_profile): ?>
        <div class="bg-white rounded-xl shadow p-8 text-center max-w-2xl mx-auto">
            <h2 class="text-2xl font-bold mb-2 text-red-600">Profile Not Found</h2>
            <p>Your student profile could not be loaded. Please ensure you are logged in correctly.</p>
        </div>
    <?php elseif ($should_fetch_data && !empty($report_for_student)): ?>
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
                                        <p><span class="w-32 inline-block">STUDENT'S NAME</span>: <b class="ml-2"><?php echo strtoupper(htmlspecialchars($report_for_student['profile']['first_name'] . ' ' . $report_for_student['profile']['last_name'])); ?></b></p>
                                        <p><span class="w-32 inline-block">MOTHER'S NAME</span>: <b class="ml-2"><?php echo strtoupper(htmlspecialchars($report_for_student['profile']['mother_name'] ?: '-')); ?></b></p>
                                        <p><span class="w-32 inline-block">FATHER'S NAME</span>: <b class="ml-2"><?php echo strtoupper(htmlspecialchars($report_for_student['profile']['father_name'] ?: '-')); ?></b></p>
                                        <p><span class="w-32 inline-block">SCHOOL</span>: <b class="ml-2">Mednova School, Madhubani</b></p>
                                    </div>
                                    <div class="w-1/2 space-y-2">
                                        <p><span class="w-32 inline-block">ROLL NUMBER</span>: <b class="ml-2"><?php echo htmlspecialchars($report_for_student['profile']['roll_number']); ?></b></p>
                                        <p><span class="w-32 inline-block">CLASS</span>: <b class="ml-2"><?php echo htmlspecialchars($report_for_student['class_info']['class_name'] . ' - ' . $report_for_student['class_info']['section_name']); ?></b></p>
                                        <p><span class="w-32 inline-block">DATE OF BIRTH</span>: <b class="ml-2"><?php echo !empty($report_for_student['profile']['dob']) ? strtoupper(date('d F Y', strtotime($report_for_student['profile']['dob']))) : '-'; ?></b></p>
                                    </div>
                                </div>
                            </section>
                            
                            <!-- --- NEW: Replicated Marks Table --- -->
                            <section><table class="marksheet-table">
                                    <thead class="font-bold bg-gray-100 text-center"><tr><th>SUB CODE</th><th>SUBJECT</th><th>MAX MARKS</th><th>MARKS OBTAINED</th><th>MARKS IN WORDS</th><th>GRADE</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($report_for_student['marks_by_subject'] as $subject_id => $marks_detail): ?>
                                            <tr>
                                                <td class="text-center"><?php echo htmlspecialchars($marks_detail['subject_code']); ?></td>
                                                <td><?php echo strtoupper(htmlspecialchars($marks_detail['subject_name'])); ?></td>
                                                <td class="text-center"><?php echo $marks_detail['max']; ?></td>
                                                <td class="text-center font-bold"><?php echo $marks_detail['obtained']; ?></td>
                                                <td><?php echo $marks_detail['marks_in_words']; ?></td>
                                                <td class="text-center font-bold"><?php echo $marks_detail['grade']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="font-bold">
                                        <tr>
                                            <td colspan="2" class="text-right">GRAND TOTAL:</td>
                                            <td class="text-center"><?php echo $report_for_student['total_max']; ?></td>
                                            <td class="text-center"><?php echo number_format($report_for_student['total_obtained'], 2); ?></td>
                                            <td colspan="2">Result: <span class="ml-4"><?php echo $report_for_student['status']; ?></span></td>
                                        </tr>
                                    </tfoot>
                            </table></section>

                            <!-- --- NEW: Replicated Footer with dynamic signature --- -->
                            <footer class="mt-20">
                                <div class="flex justify-between items-end">
                                    <span>Date: <?php echo date('d/m/Y'); ?></span>
                                    <div class="text-center w-56">
                                        <div class="h-12"></div> <!-- Space for actual signature -->
                                        <p class="border-t border-black pt-1">
                                            <?php 
                                                $signature_name = $report_for_student['class_info']['class_teacher_name'] ?? 'SYSTEM GENERATED';
                                                echo strtoupper(htmlspecialchars($signature_name));
                                            ?>
                                            <br>
                                            <span style="font-size: 0.7em;">(Class Teacher Signature)</span>
                                        </p>
                                    </div>
                                    <div class="text-center w-56">
                                        <div class="h-12"></div> <!-- Space for actual signature -->
                                        <p class="border-t border-black pt-1">PRINCIPAL</p>
                                    </div>
                                </div>
                            </footer>
                        </div>
                    </div>
                </div>
            </div>
    <?php elseif($should_fetch_data): ?>
        <div class="bg-white rounded-lg shadow-md text-center py-16"><p>No marks have been uploaded for the selected exam(s) for your profile.</p></div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md text-center py-16 no-print"><p>Please select an exam type and click 'Generate' to view your marksheet.</p></div>
    <?php endif; ?>
</div>

<script>
    function handleExamSelection() {
        const select = document.getElementById('examTypeSelect');
        const yearlyOptions = document.getElementById('yearlyOptions');
        yearlyOptions.style.display = (select.value === 'yearly') ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', handleExamSelection);
</script>

<?php require_once './student_footer.php'; ?>