<?php
// admin_marksheet_report.php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["id"]; // ID of the currently logged-in admin

// --- HELPER FUNCTIONS (UNCHANGED) ---
function numberToWords(int $number): string {
    if ($number < 0) return "INVALID";
    if ($number == 0) return "ZERO";
    $ones = ["", "ONE", "TWO", "THREE", "FOUR", "FIVE", "SIX", "SEVEN", "EIGHT", "NINE", "TEN", "ELEVEN", "TWELVE", "THIRTEEN", "FOURTEEN", "FIFTEEN", "SIXTEEN", "SEVENTEEN", "EIGHTEEN", "NINETEEN"];
    $tens = ["", "", "TWENTY", "THIRTY", "FORTY", "FIFTY", "SIXTY", "SEVENTY", "EIGHTY", "NINETY"];
    if ($number < 20) return $ones[$number];
    if ($number < 100) {
        $ten = floor($number / 10);
        $one = $number % 10;
        return $tens[$ten] . ($one > 0 ? " " . $ones[$one] : "");
    }
    if ($number < 1000) {
        $hundred = floor($number / 100);
        $remainder = $number % 100;
        return $ones[$hundred] . " HUNDRED" . ($remainder > 0 ? " " . numberToWords($remainder) : "");
    }
    return "NUMBER TOO LARGE";
}
function calculateGrade(float $percentage): string {
    if ($percentage >= 91) return 'A1';
    if ($percentage >= 81) return 'A2';
    if ($percentage >= 71) return 'B1';
    if ($percentage >= 61) return 'B2';
    if ($percentage >= 51) return 'C1';
    if ($percentage >= 41) return 'C2';
    if ($percentage >= 33) return 'D';
    return 'E';
}

// --- FILTER PARAMETERS ---
$selected_class_id = $_GET['class_id'] ?? null;
$selected_student_id = $_GET['student_id'] ?? null; // New filter for single student
$selected_exam_type_id = $_GET['exam_type_id'] ?? null;
$combined_exam_ids = $_GET['combine_exam_ids'] ?? [];
$examination_year = $_GET['year'] ?? date('Y');
$is_yearly_report = ($selected_exam_type_id === 'yearly' && !empty($combined_exam_ids));

// --- PAGINATION SETUP for students ---
$students_per_page = 1; // Marksheet generation is typically 1 student per "page" for printing
$current_student_page = isset($_GET['student_page']) && is_numeric($_GET['student_page']) ? (int)$_GET['student_page'] : 1;
$offset_students = ($current_student_page - 1) * $students_per_page;


$report_title = "MARKS STATEMENT";
$exam_types = [];
$report_data = [];
$class_subjects = [];
$class_info = null; // To store details of the selected class

// Fetch all exam types for dropdowns
$sql_exam_types = "SELECT id, exam_name FROM exam_types ORDER BY exam_name";
$exam_types_result = mysqli_query($link, $sql_exam_types);
while($row = mysqli_fetch_assoc($exam_types_result)) {
    $exam_types[] = $row;
}

// Fetch all classes for the class filter dropdown
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name";
if ($stmt_all_classes = mysqli_prepare($link, $sql_all_classes)) {
    mysqli_stmt_execute($stmt_all_classes);
    $all_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_all_classes), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_all_classes);
}

$should_fetch_data = false;
if (is_numeric($selected_class_id) && $selected_class_id > 0 && ($is_yearly_report || (is_numeric($selected_exam_type_id) && $selected_exam_type_id > 0))) {
    $should_fetch_data = true;

    // Get class info
    $sql_class_info = "SELECT id, class_name, section_name FROM classes WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql_class_info)) {
        mysqli_stmt_bind_param($stmt, "i", $selected_class_id);
        mysqli_stmt_execute($stmt);
        $class_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }

    if ($is_yearly_report) {
        $report_title = "CONSOLIDATED YEARLY MARKS STATEMENT";
    } else {
        foreach ($exam_types as $type) {
            if ($type['id'] == $selected_exam_type_id) {
                $report_title = strtoupper(htmlspecialchars($type['exam_name'])) . " MARKS STATEMENT";
                break;
            }
        }
    }

    // Get subjects for the selected class
    $sql_subjects = "SELECT s.id, s.subject_name, s.subject_code FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_id = ? ORDER BY s.id";
    if ($stmt = mysqli_prepare($link, $sql_subjects)) {
        mysqli_stmt_bind_param($stmt, "i", $selected_class_id);
        mysqli_stmt_execute($stmt);
        $class_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }

    // --- Fetch Students based on class_id and optional student_id filter ---
    $students_query_conditions = ["class_id = ?"];
    $students_query_params = [$selected_class_id];
    $students_query_types = "i";

    if (is_numeric($selected_student_id) && $selected_student_id > 0) {
        $students_query_conditions[] = "id = ?";
        $students_query_params[] = $selected_student_id;
        $students_query_types .= "i";
        // Override students_per_page and offset for single student view
        $students_per_page = 1;
        $offset_students = 0;
    }

    $sql_students_base = "SELECT id, roll_number, first_name, last_name, mother_name, father_name, dob FROM students WHERE " . implode(" AND ", $students_query_conditions);
    
    // Get total number of students for pagination
    $sql_count_students = "SELECT COUNT(id) FROM students WHERE " . implode(" AND ", $students_query_conditions);
    if ($stmt_count = mysqli_prepare($link, $sql_count_students)) {
        mysqli_stmt_bind_param($stmt_count, $students_query_types, ...$students_query_params);
        mysqli_stmt_execute($stmt_count);
        mysqli_stmt_bind_result($stmt_count, $total_students);
        mysqli_stmt_fetch($stmt_count);
        mysqli_stmt_close($stmt_count);
    } else {
        $total_students = 0;
    }
    $total_student_pages = ceil($total_students / $students_per_page);

    // Fetch students for the current page
    $students = [];
    $sql_students = $sql_students_base . " ORDER BY roll_number LIMIT ?, ?";
    $students_query_params[] = $offset_students;
    $students_query_types .= "i";
    $students_query_params[] = $students_per_page;
    $students_query_types .= "i";

    if ($stmt = mysqli_prepare($link, $sql_students)) {
        mysqli_stmt_bind_param($stmt, $students_query_types, ...$students_query_params);
        mysqli_stmt_execute($stmt);
        $students_result = mysqli_stmt_get_result($stmt);
        while($student = mysqli_fetch_assoc($students_result)) {
            $students[$student['id']] = $student;
        }
        mysqli_stmt_close($stmt);
    }

    $marks_data = [];
    if (!empty($students)) {
        $student_ids_to_fetch_marks = array_keys($students);
        $ids_to_fetch_exams = $is_yearly_report ? $combined_exam_ids : [$selected_exam_type_id];
        
        if (!empty($ids_to_fetch_exams) && !empty($class_subjects)) { // Only fetch marks if there are exams and subjects
            $exam_placeholders = implode(',', array_fill(0, count($ids_to_fetch_exams), '?'));
            $student_placeholders = implode(',', array_fill(0, count($student_ids_to_fetch_marks), '?'));

            $sql_marks = "SELECT em.student_id, es.subject_id, em.marks_obtained, es.max_marks 
                          FROM exam_marks em 
                          JOIN exam_schedule es ON em.exam_schedule_id = es.id 
                          WHERE es.class_id = ? 
                          AND es.exam_type_id IN ($exam_placeholders) 
                          AND em.student_id IN ($student_placeholders)";
            
            $params_marks = array_merge([$selected_class_id], $ids_to_fetch_exams, $student_ids_to_fetch_marks);
            $types_marks = "i" . str_repeat('i', count($ids_to_fetch_exams)) . str_repeat('i', count($student_ids_to_fetch_marks));

            if ($stmt_marks = mysqli_prepare($link, $sql_marks)) {
                mysqli_stmt_bind_param($stmt_marks, $types_marks, ...$params_marks);
                mysqli_stmt_execute($stmt_marks);
                $result = mysqli_stmt_get_result($stmt_marks);
                while($row = mysqli_fetch_assoc($result)) {
                    $student_id = $row['student_id'];
                    $subject_id = $row['subject_id'];
                    if (!isset($marks_data[$student_id][$subject_id])) {
                        $marks_data[$student_id][$subject_id] = ['obtained' => 0, 'max' => 0];
                    }
                    $marks_data[$student_id][$subject_id]['obtained'] += $row['marks_obtained'];
                    $marks_data[$student_id][$subject_id]['max'] += $row['max_marks'];
                }
                mysqli_stmt_close($stmt_marks);
            }
        }
    }

    foreach ($students as $student_id => $student_details) {
        $has_failed_subject = false;
        $has_unmarked_subject = false; // Renamed for clarity: at least one subject has no marks
        
        $report_data[$student_id] = $student_details;
        $total_obtained = 0;
        $total_max = 0;

        // Only iterate if there are subjects for the class
        if (!empty($class_subjects)) {
            foreach ($class_subjects as $subject) {
                $marks = $marks_data[$student_id][$subject['id']] ?? null;
                if ($marks && $marks['max'] > 0) {
                    $total_obtained += $marks['obtained'];
                    $total_max += $marks['max'];

                    if (($marks['obtained'] / $marks['max']) * 100 < 33) { // Assuming 33% is pass mark
                        $has_failed_subject = true;
                    }
                } else {
                    // No marks recorded for this subject OR max_marks is 0
                    $has_unmarked_subject = true;
                }
            }
        } else {
            // If the class has no subjects defined, it's effectively N/A
            $has_unmarked_subject = true; // This will trigger "N/A" or "Pending" if no other condition met
        }


        $final_result = "";
        if (empty($class_subjects)) {
            $final_result = "N/A (No Subjects Defined)";
        } elseif ($has_failed_subject) {
            $final_result = "FAIL";
        } elseif ($has_unmarked_subject) { // If no fails, but some subjects are not marked
            $final_result = "PENDING";
        } else { // No fails, all subjects marked, and all passed
            $final_result = "PASS";
        }
        
        $report_data[$student_id]['status'] = $final_result;
        $report_data[$student_id]['total_obtained'] = $total_obtained;
        $report_data[$student_id]['total_max'] = $total_max;
    }
}

mysqli_close($link);
// Removed require_once './admin_header.php'; because the header is now inline and has no-print class
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Marksheet Report</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" rel="stylesheet">

    <!-- Custom Styles -->
    <style>
        html { box-sizing: border-box; } /* Crucial for print layout */
        *, *::before, *::after { box-sizing: inherit; }

        /* Green/Teal Gradient Background for screen display */
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(-45deg, #004d40, #00897b, #26a69a); /* Updated gradient colors */
            background-size: 400% 400%; 
            animation: gradientBG 15s ease infinite; 
            color: white; 
            min-height: 100vh; /* Ensure body takes full height */
            display: flex;
            flex-direction: column;
            margin: 0; /* Reset body margin */
            padding: 0; /* Reset body padding */
        }
        @keyframes gradientBG { 
            0% {background-position: 0% 50%;} 
            50% {background-position: 100% 50%;} 
            100% {background-position: 0% 50%;} 
        }
        .glassmorphism { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .form-select, .form-input { 
            background: rgba(0, 0, 0, 0.25); 
            border-color: rgba(255, 255, 255, 0.2); 
            color: white; 
        }
        .form-input::placeholder { color: rgba(255, 255, 255, 0.6); }
        .form-input:focus, .form-select:focus { border-color: #4CAF50; outline: none; box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.5); }
        /* Style for form elements in filters for better visibility */
        .no-print select, .no-print input[type="text"], .no-print input[type="number"] {
            color: #333; /* Darker text for white background */
            background-color: #f8f8f8;
            border: 1px solid #ccc;
        }
        .no-print select:focus, .no-print input:focus {
            border-color: #00897b;
            box-shadow: 0 0 0 2px rgba(0, 137, 123, 0.3);
        }

        /* Styles for the exact replica design */
        .marksheet-container {
            width: 21cm;
            height: 29.7cm; /* Explicit A4 height for print */
            padding: 1cm;
            margin: 1rem auto; /* Center with some margin from top/bottom */
            background: white;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            position: relative;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
            page-break-after: always; /* Ensure each marksheet prints on a new page */
            overflow: hidden; /* Prevent content overflow within the A4 boundary */
        }
        .marksheet-container:last-child {
            page-break-after: avoid; /* Prevent an extra blank page after the very last marksheet */
        }
        .marksheet-double-border { border: 1px solid #6b7280; padding: 4px; }
        .marksheet-inner-content { border: 2px solid #000; padding: 1.5cm; }
        .marksheet-table { border-collapse: collapse; width: 100%; font-size: 11pt; }
        .marksheet-table th, .marksheet-table td { border: 1px solid #374151; padding: 4px 8px; }
        .watermark { 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            display: flex; 
            flex-wrap: wrap;
            justify-content: center; 
            align-items: center; 
            gap: 100px; 
            z-index: 1; 
            overflow: hidden; 
            pointer-events: none; /* Make watermark unclickable/unselectable */
        }
        .watermark span { 
            font-size: 5rem; 
            font-weight: bold; 
            color: #000; 
            opacity: 0.04; 
            transform: rotate(-45deg); 
            user-select: none; 
            -webkit-user-select: none; /* Safari */
            -moz-user-select: none; /* Firefox */
            -ms-user-select: none; /* IE 10+ */
        }
        /* The .no-print class is added to elements that should NOT appear during printing */
        .no-print { /* This class is added to the filter form and pagination to hide them during print */ }

        .pagination-link {
            @apply px-3 py-1 mx-1 rounded-md transition-colors duration-200;
        }
        .pagination-link.active {
            @apply bg-blue-600 text-white;
        }
        .pagination-link:not(.active):hover {
            @apply bg-gray-700 text-white;
        }
        .disabled-link {
            @apply opacity-50 cursor-not-allowed;
        }

        /* --- Print Styles --- */
        @media print {
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                /* Crucial: Override all background properties with a single white background */
                background: white !important; 
                background-color: white !important; /* Redundant but safe */
                color: black !important; /* Ensure text is black for print */
                -webkit-print-color-adjust: exact !important; /* For better background/color printing */
                print-color-adjust: exact !important;
                height: auto !important; /* Allow body to shrink/grow with content */
                min-height: 0 !important; /* Reset min-height */
                display: block !important; /* Reset flex display */
            }

            /* Hide everything that has the .no-print class */
            .no-print {
                display: none !important;
            }

            /* The main outer div wrapper, containing everything else */
            body > div.min-h-screen {
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                min-height: 0 !important;
                display: block !important;
                overflow: visible !important; /* Allow internal page breaks to work */
            }

            /* The div with .container and .printable-content (this now acts as the sole print area) */
            .printable-content {
                width: 100% !important; /* Allow content to use full print width */
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                flex-grow: 0 !important; /* Reset flex-grow */
                display: block !important; /* Reset flex display */
                overflow: visible !important; /* Important for page-break-after to work */
            }

            .marksheet-container {
                width: 21cm !important;
                height: 29.7cm !important; /* Explicit A4 dimensions */
                margin: 0 !important; /* No margin between printed marksheets */
                padding: 1cm !important; /* Maintain internal padding of marksheet */
                box-shadow: none !important;
                border: none !important; /* Remove outer container border for print */
                page-break-after: always !important; /* Each marksheet on new page */
                page-break-before: auto !important; /* Let browser decide if it needs a break before */
                page-break-inside: avoid !important; /* Avoid breaking content within a single marksheet */
                overflow: hidden !important; /* Prevent content overflow within the A4 boundary */
            }
            .marksheet-container:last-child {
                page-break-after: avoid !important; /* Prevent extra blank page at the very end */
            }

            /* Ensure images print */
            img {
                display: inline-block !important; /* Ensure images are not hidden by other rules */
                max-width: 100% !important; /* Prevent images from breaking layout */
                height: auto !important;
            }
            
            /* Ensure text colors are black or specific for readability, overriding screen styles */
            .text-red-600 { color: #dc2626 !important; }
            .text-green-600 { color: #16a34a !important; }
            /* New pending status color */
            .text-orange-500 { color: #f97316 !important; } /* Tailwind orange-500 */
            /* Force all text to black unless specified by a strong print-specific rule */
            .text-teal-300, .text-gray-800, .text-gray-700, .text-white { color: black !important; } 
            b, strong { font-weight: bold !important; }

            /* Watermark might need further adjustment depending on actual image content */
            .watermark span {
                color: #000 !important; /* Ensure watermark text is black */
                opacity: 0.04 !important; /* Maintain transparency */
            }
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex flex-col items-center">
        <!-- Header -->
        <header class="w-full bg-gray-800 bg-opacity-70 text-white py-4 shadow-lg fixed top-0 z-40 no-print">
            <?php require_once './admin_header.php';?>
        </header>

        <div class="container mx-auto mt-28 printable-content flex-grow"> <!-- Added flex-grow -->
            <div class="no-print bg-white rounded-xl shadow p-4 md:p-6 mb-8 max-w-4xl mx-auto text-gray-800">
                <form method="GET" id="marksheetForm">
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-4">
                        <h1 class="text-2xl font-bold text-gray-800 flex-shrink-0">Generate Marksheets</h1>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="class_id" class="block font-semibold text-sm text-gray-700 mb-1">Select Class:</label>
                            <select name="class_id" id="class_id" class="border-gray-300 rounded-md shadow-sm w-full text-gray-800">
                                <option value="">-- All Classes --</option>
                                <?php foreach($all_classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php if($selected_class_id == $class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="student_id" class="block font-semibold text-sm text-gray-700 mb-1">Select Student (Optional):</label>
                            <select name="student_id" id="student_id" class="border-gray-300 rounded-md shadow-sm w-full text-gray-800" disabled>
                                <option value="">-- Select Class First --</option>
                            </select>
                        </div>
                        <div>
                            <label for="year" class="block font-semibold text-sm text-gray-700 mb-1">Examination Year:</label>
                            <select name="year" id="year" class="border-gray-300 rounded-md shadow-sm w-full text-gray-800">
                                <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php if($examination_year == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-span-1 md:col-span-2 lg:col-span-3">
                            <label for="examTypeSelect" class="block font-semibold text-sm text-gray-700 mb-1">Report Type:</label>
                            <select name="exam_type_id" id="examTypeSelect" onchange="handleExamSelection()" class="border-gray-300 rounded-md shadow-sm w-full text-gray-800">
                                <option value="">-- Choose an Option --</option>
                                <?php foreach($exam_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php if($selected_exam_type_id == $type['id']) echo 'selected'; ?>><?php echo htmlspecialchars($type['exam_name']); ?></option>
                                <?php endforeach; ?>
                                <option value="yearly" <?php if($selected_exam_type_id == 'yearly') echo 'selected'; ?>>-- Consolidated Yearly Result --</option>
                            </select>
                        </div>
                    </div>

                    <div id="yearlyOptions" class="mt-4 p-4 border-t border-gray-200 bg-gray-50 rounded-md" style="display: <?php echo ($selected_exam_type_id === 'yearly') ? 'block' : 'none'; ?>;">
                        <p class="font-semibold mb-2 text-gray-700">Select exams to combine for Yearly Report:</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            <?php foreach($exam_types as $type): ?>
                                <label class="flex items-center space-x-2 p-2 rounded-md bg-white border border-gray-200 hover:bg-gray-100 cursor-pointer">
                                    <input type="checkbox" name="combine_exam_ids[]" value="<?php echo $type['id']; ?>" <?php if(in_array($type['id'], $combined_exam_ids)) echo 'checked'; ?> class="rounded text-blue-600 focus:ring-blue-500">
                                    <span class="text-gray-800"><?php echo htmlspecialchars($type['exam_name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-4">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-lg">
                            <i class="fas fa-cogs mr-2"></i>Generate Report
                        </button>
                        <?php if ($should_fetch_data && !empty($report_data)): ?>
                            <button type="button" onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-lg">
                                <i class="fas fa-print mr-2"></i>Print Current Marksheet
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (!$selected_class_id): ?>
                <div class="bg-white rounded-xl shadow p-8 text-center max-w-2xl mx-auto text-gray-800 no-print"><h2 class="text-2xl font-bold mb-2">No Class Selected</h2><p>Please select a class from the dropdown above to generate marksheets.</p></div>
            <?php elseif (!$should_fetch_data): ?>
                <div class="bg-white rounded-xl shadow p-8 text-center max-w-2xl mx-auto text-gray-800 no-print"><h2 class="text-2xl font-bold mb-2">Select Exam Type</h2><p>Please select an exam type or 'Yearly Result' to generate marksheets.</p></div>
            <?php elseif (!empty($report_data)): ?>
                <?php foreach($report_data as $student_id => $data): ?>
                    <div class="marksheet-container" id="container-student-<?php echo $student_id; ?>">
                        <div class="marksheet-double-border">
                            <div class="marksheet-inner-content">
                                <div class="watermark"><?php for($i=0; $i<9; $i++): ?><span>Mednova SCHOOL</span><?php endfor; ?></div>
                                <div class="relative z-10">
                                    <!-- Header -->
                                    <header class="mb-6">
                                        <div class="flex justify-between items-center">
                                            <!-- IMPORTANT: Replace with your actual school logo path. Example: `../assets/images/school-logo.png` -->
                                            <img src="./assets/images/school-logo.png" alt="School Logo" class="h-20 w-20 object-contain">
                                            <div class="text-center">
                                                <h1 class="text-2xl font-bold tracking-wider">CENTRAL BOARD OF SECONDARY EDUCATION</h1>
                                                <h2 class="text-xl font-bold tracking-wide"><?php echo htmlspecialchars($report_title); ?></h2>
                                                <h3 class="text-base mt-1">Mednova School, Madhubani - Examination <?php echo htmlspecialchars($examination_year); ?></h3>
                                            </div>
                                            <!-- IMPORTANT: Replace with your actual CBSE logo path. Example: `../assets/images/cbse-logo.png` -->
                                            <img src="./assets/images/cbse-logo.png" alt="Board Logo" class="h-20 w-20 object-contain">
                                        </div>
                                    </header>

                                    <!-- Candidate Details -->
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
                                                <p><span class="w-32 inline-block">CLASS</span>: <b class="ml-2"><?php echo htmlspecialchars($class_info['class_name'] . ' - ' . $class_info['section_name']); ?></b></p>
                                                <p><span class="w-32 inline-block">DATE OF BIRTH</span>: <b class="ml-2"><?php echo !empty($data['dob']) ? strtoupper(date('d F Y', strtotime($data['dob']))) : '-'; ?></b></p>
                                            </div>
                                        </div>
                                    </section>
                                    
                                    <!-- Marks Table -->
                                    <section><table class="marksheet-table">
                                            <thead class="font-bold bg-gray-100 text-center"><tr><th>SUB CODE</th><th>SUBJECT</th><th>MAX MARKS</th><th>MARKS OBTAINED</th><th>MARKS IN WORDS</th><th>GRADE</th></tr></thead>
                                            <tbody>
                                                <?php if (!empty($class_subjects)): ?>
                                                    <?php foreach ($class_subjects as $subject): 
                                                        $marks = $marks_data[$student_id][$subject['id']] ?? null; 
                                                        $obtained_marks = $marks ? $marks['obtained'] : 0;
                                                        $max_marks = $marks ? $marks['max'] : 0;
                                                        $percentage = ($max_marks > 0) ? ($obtained_marks / $max_marks) * 100 : 0;
                                                    ?>
                                                        <tr>
                                                            <td class="text-center"><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                                            <td><?php echo strtoupper(htmlspecialchars($subject['subject_name'])); ?></td>
                                                            <td class="text-center"><?php echo $max_marks > 0 ? $max_marks : '-'; ?></td>
                                                            <td class="text-center font-bold"><?php echo $marks ? number_format($obtained_marks, 2) : '-'; ?></td>
                                                            <td><?php echo $marks ? numberToWords((int)$obtained_marks) : '-'; ?></td>
                                                            <td class="text-center font-bold"><?php echo $marks ? calculateGrade($percentage) : '-'; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="6" class="text-center text-red-500">No subjects assigned or found for this class.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot class="font-bold">
                                                <tr>
                                                    <td colspan="2" class="text-right">GRAND TOTAL:</td>
                                                    <td class="text-center"><?php echo $data['total_max']; ?></td>
                                                    <td class="text-center"><?php echo number_format($data['total_obtained'], 2); ?></td>
                                                    <td colspan="2">Result: <span class="ml-4 
                                                        <?php 
                                                            if ($data['status'] == 'PASS') echo 'text-green-600'; 
                                                            elseif ($data['status'] == 'FAIL') echo 'text-red-600';
                                                            elseif ($data['status'] == 'PENDING') echo 'text-orange-500'; /* New color for PENDING */
                                                            else echo 'text-gray-600'; /* Default for N/A etc. */
                                                        ?>"><?php echo $data['status']; ?></span></td>
                                                </tr>
                                            </tfoot>
                                    </table></section>

                                    <!-- Footer -->
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

                <!-- Pagination for Marksheets (if showing multiple students) -->
                <?php if ($total_students > $students_per_page && $total_student_pages > 1): ?>
                    <div class="no-print flex justify-center items-center space-x-2 mt-8 text-white">
                        <?php
                        // Preserve current filters for pagination links
                        $pagination_query_params = $_GET;
                        unset($pagination_query_params['student_page']); // Remove existing page param
                        $base_pagination_url = "admin_marksheet_report.php?" . http_build_query($pagination_query_params) . "&";
                        ?>

                        <!-- Previous Button -->
                        <a href="<?php echo $base_pagination_url; ?>student_page=<?php echo max(1, $current_student_page - 1); ?>" class="pagination-link <?php echo ($current_student_page <= 1) ? 'disabled-link' : ''; ?>">Previous</a>

                        <!-- Page Numbers -->
                        <?php
                        $page_range = 2; // Number of pages to show around current page
                        $start_page = max(1, $current_student_page - $page_range);
                        $end_page = min($total_student_pages, $current_student_page + $page_range);

                        if ($start_page > 1) {
                            echo '<a href="' . $base_pagination_url . 'student_page=1" class="pagination-link">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="px-2">...</span>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="<?php echo $base_pagination_url; ?>student_page=<?php echo $i; ?>" class="pagination-link <?php echo ($i === $current_student_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php
                        if ($end_page < $total_student_pages) {
                            if ($end_page < $total_student_pages - 1) {
                                echo '<span class="px-2">...</span>';
                            }
                            echo '<a href="' . $base_pagination_url . 'student_page=' . $total_student_pages . '" class="pagination-link">' . $total_student_pages . '</a>';
                        }
                        ?>

                        <!-- Next Button -->
                        <a href="<?php echo $base_pagination_url; ?>student_page=<?php echo min($total_student_pages, $current_student_page + 1); ?>" class="pagination-link <?php echo ($current_student_page >= $total_student_pages) ? 'disabled-link' : ''; ?>">Next</a>
                    </div>
                    <p class="no-print text-center text-sm text-white/70 mt-4">
                        Showing student <?php echo $offset_students + 1; ?> of <?php echo $total_students; ?>.
                    </p>
                <?php endif; ?>

            <?php elseif($should_fetch_data): ?>
                <div class="bg-white rounded-xl shadow p-8 text-center max-w-2xl mx-auto text-gray-800 no-print"><p>No marks have been uploaded for the selected exam(s) in this class.</p></div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow p-8 text-center max-w-2xl mx-auto no-print text-gray-800">
                    <h2 class="text-2xl font-bold mb-2">Generate Student Marksheets</h2>
                    <p>Please select a class and an exam type (or yearly result) from the filters above and click 'Generate Report'.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="w-full bg-gray-800 bg-opacity-70 text-white py-4 shadow-inner mt-auto no-print">
    <?php require_once './admin_footer.php';?>        
    </footer>
    </div>

    <script>
        function handleExamSelection() {
            const select = document.getElementById('examTypeSelect');
            const yearlyOptions = document.getElementById('yearlyOptions');
            yearlyOptions.style.display = (select.value === 'yearly') ? 'block' : 'none';
        }

        // --- Dynamic Student Dropdown Logic ---
        document.addEventListener('DOMContentLoaded', function() {
            handleExamSelection(); // Call on load to set initial state of yearly options

            const classSelect = document.getElementById('class_id');
            const studentSelect = document.getElementById('student_id');
            const initialSelectedStudent = "<?php echo htmlspecialchars($selected_student_id); ?>";

            async function loadStudentsForClass(classId, selectedStudentId = null) {
                studentSelect.innerHTML = '<option value="">Loading students...</option>';
                studentSelect.disabled = true;

                if (!classId) {
                    studentSelect.innerHTML = '<option value="">-- Select Class First --</option>';
                    return;
                }

                try {
                    const response = await fetch(`ajax_get_students_by_class.php?class_id=${classId}`);
                    const students = await response.json();

                    studentSelect.innerHTML = '<option value="">-- All Students in Class --</option>';
                    if (students.length > 0) {
                        students.forEach(student => {
                            const option = document.createElement('option');
                            option.value = student.id;
                            option.textContent = `${student.first_name} ${student.last_name} (Roll: ${student.roll_number})`;
                            if (selectedStudentId == student.id) { // Use == for type coercion
                                option.selected = true;
                            }
                            studentSelect.appendChild(option);
                        });
                        studentSelect.disabled = false;
                    } else {
                        studentSelect.innerHTML = '<option value="">-- No students found --</option>';
                    }
                } catch (error) {
                    console.error('Failed to fetch students:', error);
                    studentSelect.innerHTML = '<option value="">-- Error loading students --</option>';
                }
            }

            // Load students initially if a class is already selected (e.g., after form submission)
            if (classSelect.value) {
                loadStudentsForClass(classSelect.value, initialSelectedStudent);
            } else {
                studentSelect.innerHTML = '<option value="">-- Select Class First --</option>';
            }

            // Add event listener for class select changes
            classSelect.addEventListener('change', function() {
                loadStudentsForClass(this.value);
            });
        });
    </script>
</body>
</html>