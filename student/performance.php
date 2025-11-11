<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}
$student_id = $_SESSION["id"];
$student_class_id = $_SESSION["class_id"] ?? null;

// --- 1. FETCH EXAM MARKS AND PROCESS FOR CHART & TABLES ---
$marks_by_exam = [];
$chart_data = [
    'categories' => [], // Subject names
    'series' => []      // Data for each exam type
];
$total_marks_obtained = 0;
$total_max_marks = 0;

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
    $temp_chart_series = [];
    $subjects_for_chart = [];

    while ($row = mysqli_fetch_assoc($result)) {
        // For detailed tables
        $marks_by_exam[$row['exam_name']][] = $row;

        // For overall percentage calculation
        $total_marks_obtained += $row['marks_obtained'];
        $total_max_marks += $row['max_marks'];

        // For chart data processing
        $percentage = ($row['max_marks'] > 0) ? ($row['marks_obtained'] / $row['max_marks']) * 100 : 0;
        $subjects_for_chart[$row['subject_name']] = true; // Collect unique subjects
        $temp_chart_series[$row['exam_name']][$row['subject_name']] = round($percentage, 2);
    }
    mysqli_stmt_close($stmt);

    // Finalize chart data structure
    if (!empty($subjects_for_chart)) {
        $chart_data['categories'] = array_keys($subjects_for_chart);
        foreach ($temp_chart_series as $exam_name => $subject_scores) {
            $series_data = [];
            foreach ($chart_data['categories'] as $subject) {
                // Ensure every subject has a score for each series (0 if not applicable)
                $series_data[] = $subject_scores[$subject] ?? 0;
            }
            $chart_data['series'][] = [
                'name' => $exam_name,
                'data' => $series_data
            ];
        }
    }
}
$overall_percentage = ($total_max_marks > 0) ? ($total_marks_obtained / $total_max_marks) * 100 : 0;

// --- 2. FETCH ATTENDANCE SUMMARY ---
$attendance_summary = ['total' => 0, 'present' => 0];
if ($student_class_id) {
    // For simplicity, we count total school days as total records for that class. 
    // A more complex system might have a separate "school_days" table.
    $sql_attendance = "SELECT 
                        (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE class_id = ?) as total_days,
                        (SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'Present') as present_days";
    if ($stmt_att = mysqli_prepare($link, $sql_attendance)) {
        mysqli_stmt_bind_param($stmt_att, "ii", $student_class_id, $student_id);
        mysqli_stmt_execute($stmt_att);
        $result_att = mysqli_stmt_get_result($stmt_att);
        $row_att = mysqli_fetch_assoc($result_att);
        $attendance_summary['total'] = $row_att['total_days'] ?? 0;
        $attendance_summary['present'] = $row_att['present_days'] ?? 0;
        mysqli_stmt_close($stmt_att);
    }
}
$attendance_percentage = ($attendance_summary['total'] > 0) ? ($attendance_summary['present'] / $attendance_summary['total']) * 100 : 0;

require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Academic Performance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- ApexCharts JS -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto max-w-7xl p-4 sm:p-6 space-y-8">
    <div class="text-center mb-6">
        <h1 class="text-4xl font-bold text-gray-800 tracking-tight">Academic Performance</h1>
        <p class="text-gray-600 mt-2 text-lg">An overview of your grades and attendance.</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Overall Percentage -->
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="flex-shrink-0 h-16 w-16 rounded-full flex items-center justify-center bg-blue-100 text-blue-600"><i class="fas fa-graduation-cap text-2xl"></i></div>
            <div>
                <h3 class="font-semibold text-gray-500">Overall Percentage</h3>
                <p class="text-4xl font-bold text-gray-800 mt-1"><?php echo number_format($overall_percentage, 2); ?>%</p>
            </div>
        </div>
        <!-- Attendance -->
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="flex-shrink-0 h-16 w-16 rounded-full flex items-center justify-center bg-green-100 text-green-600"><i class="fas fa-user-check text-2xl"></i></div>
            <div>
                <h3 class="font-semibold text-gray-500">Attendance</h3>
                <p class="text-4xl font-bold text-gray-800 mt-1"><?php echo number_format($attendance_percentage, 2); ?>%</p>
                <p class="text-sm text-gray-500"><?php echo $attendance_summary['present']; ?> / <?php echo $attendance_summary['total']; ?> Days</p>
            </div>
        </div>
        <!-- Overall Grade (Example) -->
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="flex-shrink-0 h-16 w-16 rounded-full flex items-center justify-center bg-yellow-100 text-yellow-600"><i class="fas fa-award text-2xl"></i></div>
            <div>
                <h3 class="font-semibold text-gray-500">Overall Grade</h3>
                <p class="text-4xl font-bold text-gray-800 mt-1">
                    <?php 
                        if ($overall_percentage >= 90) echo 'A+';
                        elseif ($overall_percentage >= 80) echo 'A';
                        elseif ($overall_percentage >= 70) echo 'B+';
                        elseif ($overall_percentage >= 60) echo 'B';
                        elseif ($overall_percentage >= 50) echo 'C';
                        elseif ($overall_percentage >= 40) echo 'D';
                        else echo 'F';
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Performance Chart -->
    <div class="bg-white p-6 rounded-xl shadow-md">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Subject Performance Comparison</h2>
        <?php if (!empty($chart_data['categories'])): ?>
            <div id="performanceChart"></div>
        <?php else: ?>
            <p class="text-center text-gray-500 py-8">No exam data available to generate the chart.</p>
        <?php endif; ?>
    </div>

    <!-- Detailed Results Section -->
    <div class="space-y-8">
        <?php if (empty($marks_by_exam)): ?>
            <div class="bg-white p-6 rounded-xl shadow-md text-center text-gray-500">No detailed exam results have been published yet.</div>
        <?php else: ?>
            <?php foreach ($marks_by_exam as $exam_name => $marks): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <h2 class="text-2xl font-bold text-gray-800 p-6 border-b"><?php echo htmlspecialchars($exam_name); ?> Results</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50"><tr><th class="p-4 text-left text-sm font-semibold text-gray-600">Subject</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Marks Obtained</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Max Marks</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Percentage</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Status</th></tr></thead>
                            <tbody>
                                <?php foreach($marks as $mark): $percentage = ($mark['max_marks'] > 0) ? ($mark['marks_obtained'] / $mark['max_marks']) * 100 : 0; $passed = $mark['marks_obtained'] >= $mark['passing_marks']; ?>
                                <tr class="border-t"><td class="p-4 font-medium text-gray-800"><?php echo htmlspecialchars($mark['subject_name']); ?></td><td class="p-4 text-center text-gray-700"><?php echo htmlspecialchars($mark['marks_obtained']); ?></td><td class="p-4 text-center text-gray-700"><?php echo htmlspecialchars($mark['max_marks']); ?></td><td class="p-4 text-center font-semibold text-gray-800"><?php echo number_format($percentage, 2); ?>%</td><td class="p-4 text-center"><span class="font-bold text-sm py-1 px-3 rounded-full <?php echo $passed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>"><?php echo $passed ? 'PASS' : 'FAIL'; ?></span></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Only try to render the chart if the chart data is available
    <?php if (!empty($chart_data['categories'])): ?>
        var options = {
            series: <?php echo json_encode($chart_data['series']); ?>,
            chart: {
                type: 'bar',
                height: 350,
                toolbar: { show: true }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    endingShape: 'rounded'
                },
            },
            dataLabels: { enabled: false },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: <?php echo json_encode($chart_data['categories']); ?>,
                labels: {
                    style: {
                        fontSize: '12px',
                        fontWeight: 'bold',
                    }
                }
            },
            yaxis: {
                title: { text: 'Percentage (%)' },
                min: 0,
                max: 100
            },
            fill: { opacity: 1 },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + "%"
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center'
            }
        };

        var chart = new ApexCharts(document.querySelector("#performanceChart"), options);
        chart.render();
    <?php endif; ?>
});
</script>

</body>
</html>
<?php require_once './student_footer.php'; ?>