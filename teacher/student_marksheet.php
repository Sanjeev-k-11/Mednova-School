<?php
require_once "../database/config.php"; // Adjust path if needed

// --- HELPER FUNCTIONS ---
function numberToWords(int $number): string {
    if ($number < 0 || $number > 100) return "INVALID";
    if ($number == 100) return "ONE HUNDRED";
    if ($number == 0) return "ZERO";

    $ones = ["", "ONE", "TWO", "THREE", "FOUR", "FIVE", "SIX", "SEVEN", "EIGHT", "NINE", "TEN", "ELEVEN", "TWELVE", "THIRTEEN", "FOURTEEN", "FIFTEEN", "SIXTEEN", "SEVENTEEN", "EIGHTEEN", "NINETEEN"];
    $tens = ["", "", "TWENTY", "THIRTY", "FORTY", "FIFTY", "SIXTY", "SEVENTY", "EIGHTY", "NINETY"];

    if ($number < 20) return $ones[$number];
    
    $ten = floor($number / 10);
    $one = $number % 10;
    
    return $tens[$ten] . ($one > 0 ? " " . $ones[$one] : "");
}

function calculateGrade(float $percentage): string {
    if ($percentage >= 91) return 'A1';
    if ($percentage >= 81) return 'A2';
    if ($percentage >= 71) return 'B1';
    if ($percentage >= 61) return 'B2';
    if ($percentage >= 51) return 'C1';
    if ($percentage >= 41) return 'C2';
    if ($percentage >= 33) return 'D';
    return 'E'; // Fail
}

// --- INITIALIZATION ---
$error_message = null; $student_details = null; $marks_data = []; $class_subjects = []; $total_obtained = 0; $total_max = 0; $final_result = ""; $selected_exam_type_id = null; $selected_exam_type_name = "";

// --- DATA FETCHING ---
if (isset($_GET['reg_no']) && isset($_GET['dob']) && isset($_GET['exam_id'])) {
    $registration_number = trim($_GET['reg_no']);
    $dob = trim($_GET['dob']);
    $selected_exam_type_id = (int)$_GET['exam_id'];

    $exam_types = mysqli_fetch_all(mysqli_query($link, "SELECT id, exam_name FROM exam_types"), MYSQLI_ASSOC);
    foreach ($exam_types as $type) {
        if ($type['id'] == $selected_exam_type_id) { $selected_exam_type_name = $type['exam_name']; break; }
    }

    $sql_student = "SELECT s.*, c.class_name, c.section_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.registration_number = ? AND s.dob = ?";
    if ($stmt_student = mysqli_prepare($link, $sql_student)) {
        mysqli_stmt_bind_param($stmt_student, "ss", $registration_number, $dob);
        mysqli_stmt_execute($stmt_student);
        $student_details = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student));
        mysqli_stmt_close($stmt_student);
    }

    if ($student_details) {
        $student_id = $student_details['id'];
        $class_id = $student_details['class_id'];

        $sql_subjects = "SELECT s.id, s.subject_name, s.subject_code FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_id = ? ORDER BY s.id";
        if ($stmt_subjects = mysqli_prepare($link, $sql_subjects)) {
            mysqli_stmt_bind_param($stmt_subjects, "i", $class_id); mysqli_stmt_execute($stmt_subjects);
            $class_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt_subjects), MYSQLI_ASSOC); mysqli_stmt_close($stmt_subjects);
        }

        $sql_marks = "SELECT es.subject_id, em.marks_obtained, es.max_marks FROM exam_marks em JOIN exam_schedule es ON em.exam_schedule_id = es.id WHERE em.student_id = ? AND es.exam_type_id = ?";
        if ($stmt_marks = mysqli_prepare($link, $sql_marks)) {
            mysqli_stmt_bind_param($stmt_marks, "ii", $student_id, $selected_exam_type_id); mysqli_stmt_execute($stmt_marks);
            $result = mysqli_stmt_get_result($stmt_marks);
            while($row = mysqli_fetch_assoc($result)) {
                $marks_data[$row['subject_id']] = ['obtained' => $row['marks_obtained'], 'max' => $row['max_marks']];
            }
            mysqli_stmt_close($stmt_marks);
        }
        
        $fail_count = 0;
        foreach ($class_subjects as $subject) {
            $marks = $marks_data[$subject['id']] ?? null;
            if ($marks && $marks['max'] > 0) {
                if (($marks['obtained'] / $marks['max']) * 100 < 33) { $fail_count++; }
            } else {
                $fail_count++; 
            }
        }
        
        if ($fail_count == 0) $final_result = "PASS"; else $final_result = "FAIL";

    } else {
        $error_message = "Student Not Found. The details provided do not match any records.";
    }
} else {
    $error_message = "Required information is missing. This page cannot be accessed directly.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Marksheet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tinos:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #d1d5db; font-family: 'Tinos', serif; color: #1f2937; }
        .marksheet {
            width: 21cm; height: 29.7cm; /* A4 Paper size */
            margin: 2rem auto; padding: 1.5cm;
            background: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden; /* Important for watermark */
        }
        .marksheet-border {
            position: absolute; top: 1cm; left: 1cm; right: 1cm; bottom: 1cm;
            border: 2px solid #e11d48;
            background: radial-gradient(circle, transparent 2px, #fecdd3 2px) repeat-x,
                        radial-gradient(circle, transparent 2px, #fecdd3 2px) repeat-x,
                        radial-gradient(circle, transparent 2px, #fecdd3 2px) repeat-y,
                        radial-gradient(circle, transparent 2px, #fecdd3 2px) repeat-y;
            background-size: 8px 4px, 8px 4px, 4px 8px, 4px 8px;
            background-position: 0 0, 0 100%, 0 0, 100% 0;
            pointer-events: none;
        }
        .marksheet-content { position: relative; z-index: 10; }
        .marksheet-table th, .marksheet-table td { border: 1px solid #9ca3af; padding: 6px 10px; }
        .watermark {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            display: flex; flex-wrap: wrap; justify-content: center; align-items: center;
            gap: 100px; z-index: 1; overflow: hidden;
        }
        .watermark span {
            font-size: 6rem; font-weight: bold; color: #1f2937;
            opacity: 0.04; transform: rotate(-45deg); user-select: none;
        }
        @media print {
            body { background: none; }
            .no-print { display: none; }
            .marksheet { margin: 0; box-shadow: none; border: 1px solid #000; height: auto; }
            .marksheet-border { border-color: #000; background: none; }
        }
    </style>
</head>
<body>
    <?php if (!empty($error_message)): ?>
        <div class="max-w-xl mx-auto bg-white p-8 rounded-xl shadow-lg text-center mt-20">
             <h1 class="text-2xl font-bold text-red-600 mb-2">An Error Occurred</h1>
             <p class="text-gray-600"><?php echo $error_message; ?></p>
             <a href="javascript:history.back()" class="mt-6 inline-block bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700 no-print">Go Back</a>
        </div>
    <?php elseif ($student_details): ?>
        <div class="marksheet">
            <div class="marksheet-border"></div>
            <div class="watermark">
                <?php for($i=0; $i<12; $i++): ?><span>YOUR SCHOOL</span><?php endfor; ?>
            </div>
            <div class="marksheet-content">
                <header class="text-center">
                    <div class="flex justify-between items-center text-xs font-semibold">
                        <span>Sl. No. <?php echo htmlspecialchars($student_details['registration_number']); ?></span>
                        <img src="./assets/images/cbse-logo.png" alt="Board Logo" class="h-16"> <!-- Your board logo -->
                        <span>Enrolment No.</span>
                    </div>
                    <h1 class="text-xl font-bold mt-2">CENTRAL BOARD OF SECONDARY EDUCATION</h1>
                    <h2 class="text-lg">MARKS STATEMENT</h2>
                    <h3 class="text-lg font-bold">ALL INDIA SECONDARY SCHOOL EXAMINATION, <?php echo date('Y'); ?></h3>
                </header>

                <section class="mt-6 text-sm">
                    <div class="grid grid-cols-2 gap-x-8">
                        <div>
                            <p>Name of Student: <span class="font-bold ml-2"><?php echo strtoupper(htmlspecialchars($student_details['first_name'] . ' ' . $student_details['last_name'])); ?></span></p>
                            <p>Mother's Name: <span class="font-bold ml-2"><?php echo strtoupper(htmlspecialchars($student_details['mother_name'])); ?></span></p>
                            <p>Father's Name: <span class="font-bold ml-2"><?php echo strtoupper(htmlspecialchars($student_details['father_name'])); ?></span></p>
                            <p>Date of Birth: <span class="font-bold ml-2"><?php echo date('d/m/Y', strtotime($student_details['dob'])); ?></span></p>
                            <p>School: <span class="font-bold ml-2">YOUR SCHOOL NAME, YOUR CITY</span></p>
                        </div>
                    </div>
                </section>
                
                <section class="mt-4">
                    <table class="w-full marksheet-table text-xs border-collapse">
                        <thead>
                            <tr class="font-bold bg-gray-100">
                                <th rowspan="2">SUB CODE</th>
                                <th rowspan="2">SUBJECT</th>
                                <th colspan="3">MARKS OBTAINED</th>
                                <th rowspan="2">MARKS IN WORDS</th>
                                <th rowspan="2">GRADE</th>
                            </tr>
                            <tr class="font-bold bg-gray-100">
                                <th>THEORY</th>
                                <th>PRACTICAL</th>
                                <th>TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($class_subjects as $subject): 
                                $marks = $marks_data[$subject['id']] ?? null;
                                $percentage = ($marks && $marks['max'] > 0) ? ($marks['obtained'] / $marks['max']) * 100 : 0;
                            ?>
                            <tr>
                                <td class="text-center"><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td class="font-semibold"><?php echo strtoupper(htmlspecialchars($subject['subject_name'])); ?></td>
                                <td class="text-center"><?php echo $marks ? round($marks['obtained'] * 0.8) : '-'; ?></td> <!-- Assuming 80% Theory -->
                                <td class="text-center"><?php echo $marks ? round($marks['obtained'] * 0.2) : '-'; ?></td> <!-- Assuming 20% Practical -->
                                <td class="text-center font-bold"><?php echo $marks ? $marks['obtained'] : '-'; ?></td>
                                <td><?php echo $marks ? numberToWords((int)$marks['obtained']) : '-'; ?></td>
                                <td class="text-center font-bold"><?php echo $marks ? calculateGrade($percentage) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <footer class="mt-8 text-sm">
                    <p>Result: <span class="font-bold ml-4"><?php echo $final_result; ?></span></p>
                    <div class="flex justify-between mt-20">
                        <span>Place: YOUR CITY</span>
                        <div class="text-center">
                            <p><em>(Signature)</em></p>
                            <p>Controller of Examinations</p>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <button onclick="window.print()" class="no-print bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700">Print Marksheet</button>
                    </div>
                </footer>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>