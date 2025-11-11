<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}

$teacher_id = $_SESSION["id"];
$teacher_name = $_SESSION["full_name"];

// --- DATA FETCHING (No changes here, it's already robust) ---
$teacher_assignments_classes = [];
$sql_assignments = "SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_id = ?";
if ($stmt_assign = mysqli_prepare($link, $sql_assignments)) {
    mysqli_stmt_bind_param($stmt_assign, "i", $teacher_id);
    mysqli_stmt_execute($stmt_assign);
    $result_assign = mysqli_stmt_get_result($stmt_assign);
    while ($row = mysqli_fetch_assoc($result_assign)) {
        $teacher_assignments_classes[] = $row['class_id'];
    }
    mysqli_stmt_close($stmt_assign);
}

if (empty($teacher_assignments_classes)) {
    $upcoming_exams = $graded_exams = $chart_data = $top_students = [];
    $stat_upcoming_count = $stat_graded_count = $stat_overall_avg = $stat_overall_pass_rate = 0;
} else {
    $class_ids_placeholder = implode(',', array_fill(0, count($teacher_assignments_classes), '?'));
    $class_ids_types = str_repeat('i', count($teacher_assignments_classes));
    $params = $teacher_assignments_classes;

    // Get Upcoming Exams
    $sql_upcoming = "SELECT es.exam_date, et.exam_name, s.subject_name, c.class_name, c.section_name, es.max_marks FROM exam_schedule es JOIN exam_types et ON es.exam_type_id = et.id JOIN subjects s ON es.subject_id = s.id JOIN classes c ON es.class_id = c.id WHERE es.class_id IN ($class_ids_placeholder) AND es.exam_date >= CURDATE() ORDER BY es.exam_date ASC, es.start_time ASC LIMIT 5";
    $stmt_upcoming = mysqli_prepare($link, $sql_upcoming);
    mysqli_stmt_bind_param($stmt_upcoming, $class_ids_types, ...$params);
    mysqli_stmt_execute($stmt_upcoming);
    $upcoming_exams = mysqli_fetch_all(mysqli_stmt_get_result($stmt_upcoming), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_upcoming);
    $stat_upcoming_count = count($upcoming_exams);

    // Get Recently Graded Exams
    $sql_graded = "SELECT es.id as schedule_id, es.exam_date, et.exam_name, s.subject_name, c.class_name, c.section_name, es.max_marks, es.passing_marks, COUNT(em.id) AS students_appeared, AVG(em.marks_obtained) AS avg_score, MAX(em.marks_obtained) AS high_score, SUM(CASE WHEN em.marks_obtained >= es.passing_marks THEN 1 ELSE 0 END) AS passed_count FROM exam_schedule es JOIN exam_types et ON es.exam_type_id = et.id JOIN subjects s ON es.subject_id = s.id JOIN classes c ON es.class_id = c.id LEFT JOIN exam_marks em ON es.id = em.exam_schedule_id WHERE es.class_id IN ($class_ids_placeholder) AND es.exam_date < CURDATE() GROUP BY es.id ORDER BY es.exam_date DESC LIMIT 10";
    $stmt_graded = mysqli_prepare($link, $sql_graded);
    mysqli_stmt_bind_param($stmt_graded, $class_ids_types, ...$params);
    mysqli_stmt_execute($stmt_graded);
    $graded_exams = mysqli_fetch_all(mysqli_stmt_get_result($stmt_graded), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_graded);
    $stat_graded_count = count($graded_exams);

    // Get Overall Stats
    $sql_stats = "SELECT AVG((em.marks_obtained / es.max_marks) * 100) AS overall_avg_percentage, (SUM(CASE WHEN em.marks_obtained >= es.passing_marks THEN 1 ELSE 0 END) / COUNT(em.id)) * 100 AS overall_pass_rate FROM exam_marks em JOIN exam_schedule es ON em.exam_schedule_id = es.id WHERE es.class_id IN ($class_ids_placeholder)";
    $stmt_stats = mysqli_prepare($link, $sql_stats);
    mysqli_stmt_bind_param($stmt_stats, $class_ids_types, ...$params);
    mysqli_stmt_execute($stmt_stats);
    $stats_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stats));
    $stat_overall_avg = $stats_row['overall_avg_percentage'] ?? 0;
    $stat_overall_pass_rate = $stats_row['overall_pass_rate'] ?? 0;
    mysqli_stmt_close($stmt_stats);

    // Get Chart Data
    $sql_chart = "SELECT s.subject_name, AVG((em.marks_obtained / es.max_marks) * 100) as avg_percentage FROM exam_marks em JOIN exam_schedule es ON em.exam_schedule_id = es.id JOIN subjects s ON es.subject_id = s.id WHERE es.class_id IN ($class_ids_placeholder) GROUP BY s.subject_name ORDER BY avg_percentage DESC";
    $stmt_chart = mysqli_prepare($link, $sql_chart);
    mysqli_stmt_bind_param($stmt_chart, $class_ids_types, ...$params);
    mysqli_stmt_execute($stmt_chart);
    $chart_data = mysqli_fetch_all(mysqli_stmt_get_result($stmt_chart), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_chart);

    // Get Top Students
    $sql_top_students = "SELECT st.first_name, st.last_name, c.class_name, c.section_name, AVG((em.marks_obtained / es.max_marks) * 100) as avg_percentage FROM exam_marks em JOIN exam_schedule es ON em.exam_schedule_id = es.id JOIN students st ON em.student_id = st.id JOIN classes c ON st.class_id = c.id WHERE es.class_id IN ($class_ids_placeholder) GROUP BY st.id ORDER BY avg_percentage DESC LIMIT 3";
    $stmt_top = mysqli_prepare($link, $sql_top_students);
    mysqli_stmt_bind_param($stmt_top, $class_ids_types, ...$params);
    mysqli_stmt_execute($stmt_top);
    $top_students = mysqli_fetch_all(mysqli_stmt_get_result($stmt_top), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_top);

    $chart_labels = json_encode(array_column($chart_data, 'subject_name'));
    $chart_values = json_encode(array_column($chart_data, 'avg_percentage'));
}
mysqli_close($link);

require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #10b981;
            --light-gray: #f3f4f6;
            --dark-gray: #4b5563;
            --text-color: #1f2937;
            --stat-icon-blue: #3b82f6;
            --stat-icon-green: #10b981;
            --stat-icon-orange: #f59e0b;
            --stat-icon-red: #ef4444;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--light-gray);
            color: var(--text-color);
        }
        .dashboard-container { max-width: 1600px; margin: auto; margin-top: 100px; margin-bottom: 50px; padding: 0 20px; }
        .header { margin-bottom: 30px; }
        .header h1 { font-size: 2.25rem; font-weight: 700; }
        .header p { font-size: 1.125rem; color: var(--dark-gray); }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background-color: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); display: flex; align-items: center; }
        .stat-icon { font-size: 1.75rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; color: #fff; }
        .stat-content h3 { font-size: 1rem; color: var(--dark-gray); margin: 0; font-weight: 500; }
        .stat-content p { font-size: 2rem; font-weight: 700; margin: 5px 0 0; color: var(--primary-color); }
        .main-layout { display: grid; grid-template-columns: repeat(12, 1fr); gap: 20px; }
        .main-content { grid-column: span 12; }
        @media (min-width: 1024px) { .main-content { grid-column: span 8; } }
        .sidebar { grid-column: span 12; }
        @media (min-width: 1024px) { .sidebar { grid-column: span 4; } }
        .panel { background-color: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }
        .panel-header { font-size: 1.25rem; font-weight: 600; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .table-responsive { overflow-x: auto; }
        .styled-table { width: 100%; border-collapse: collapse; }
        .styled-table th, .styled-table td { padding: 12px 15px; text-align: left; }
        .styled-table thead tr { background-color: var(--light-gray); color: var(--dark-gray); font-weight: 600; font-size: 0.875rem; text-transform: uppercase; }
        .styled-table tbody tr { border-bottom: 1px solid #e5e7eb; }
        .styled-table tbody tr:last-of-type { border-bottom: none; }
        .progress-bar { background-color: #e9ecef; border-radius: .25rem; height: 8px; width: 100px; }
        .progress-bar-fill { background-color: var(--secondary-color); height: 100%; border-radius: .25rem; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .list-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #e5e7eb; }
        .list-item:last-child { border-bottom: none; }
        .list-item .icon { font-size: 1.2rem; color: var(--primary-color); margin-right: 15px; }
        .list-item .content { flex-grow: 1; }
        .list-item .date { color: var(--dark-gray); font-weight: 500; }
        .top-student-list .list-item { gap: 15px; }
        .top-student-rank i { font-size: 2rem; }
        .rank-1 { color: #ffbf00; }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }
        .top-student-details strong { font-size: 1rem; color: var(--text-color); }
        .top-student-details .text-muted { font-size: 0.875rem; color: var(--dark-gray); }
        .top-student-score { font-weight: bold; font-size: 1.1rem; color: var(--secondary-color); margin-left: auto; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="header">
        <h1>Exam & Performance Dashboard</h1>
        <p>An overview of exam schedules, results, and class performance.</p>
    </div>

    <?php if (empty($teacher_assignments_classes)): ?>
        <div class="panel text-center"><p>You are not currently assigned to any classes or subjects.</p></div>
    <?php else: ?>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background-color: var(--stat-icon-blue);"><i class="fas fa-chart-line"></i></div>
            <div class="stat-content">
                <h3>Overall Average Score</h3>
                <p><?php echo round($stat_overall_avg, 1); ?>%</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background-color: var(--stat-icon-green);"><i class="fas fa-check-double"></i></div>
            <div class="stat-content">
                <h3>Overall Pass Rate</h3>
                <p><?php echo round($stat_overall_pass_rate, 1); ?>%</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background-color: var(--stat-icon-orange);"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-content">
                <h3>Upcoming Exams</h3>
                <p><?php echo $stat_upcoming_count; ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background-color: var(--stat-icon-red);"><i class="fas fa-graduation-cap"></i></div>
            <div class="stat-content">
                <h3>Exams Graded</h3>
                <p><?php echo $stat_graded_count; ?></p>
            </div>
        </div>
    </div>

    <div class="main-layout">
        <div class="main-content">
            <div class="panel mb-4" style="height: 400px;">
                <h3 class="panel-header"><i class="fas fa-chart-bar"></i>Subject Performance Overview</h3>
                <canvas id="performanceChart"></canvas>
            </div>
            <div class="panel">
                <h3 class="panel-header"><i class="fas fa-history"></i>Recently Graded Exams</h3>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Exam Name</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Avg. Score</th>
                                <th>High Score</th>
                                <th>Pass Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($graded_exams)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No graded exams found.</td></tr>
                            <?php else: foreach ($graded_exams as $exam): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo date("M j, Y", strtotime($exam['exam_date'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['class_name'] . ' - ' . $exam['section_name']); ?></td>
                                    <td>
                                        <?php
                                            $avg_percent = $exam['max_marks'] > 0 ? ($exam['avg_score'] / $exam['max_marks']) * 100 : 0;
                                            echo round($exam['avg_score'], 1) . ' / ' . $exam['max_marks'];
                                        ?>
                                        <div class="progress-bar mt-1">
                                            <div class="progress-bar-fill" style="width: <?php echo $avg_percent; ?>%;"></div>
                                        </div>
                                    </td>
                                    <td><?php echo round($exam['high_score'], 1); ?></td>
                                    <td>
                                        <?php
                                            $pass_rate = $exam['students_appeared'] > 0 ? ($exam['passed_count'] / $exam['students_appeared']) * 100 : 0;
                                            $badge_class = $pass_rate >= 50 ? 'badge-success' : 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo round($pass_rate, 1); ?>%</span>
                                    </td>
                                    <td>
                                        <a href="view_exam_results.php?id=<?php echo $exam['schedule_id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="sidebar">
            <div class="panel mb-4">
                <h3 class="panel-header"><i class="fas fa-trophy"></i>Top Performing Students</h3>
                <div class="top-student-list">
                    <?php if (empty($top_students)): ?>
                        <p class="text-center text-muted">No student data available yet.</p>
                    <?php else: 
                        $rank_icons = [['icon' => 'fa-medal', 'color' => 'rank-1'], ['icon' => 'fa-medal', 'color' => 'rank-2'], ['icon' => 'fa-medal', 'color' => 'rank-3']];
                        foreach ($top_students as $index => $student): ?>
                        <div class="list-item">
                            <div class="top-student-rank">
                                <i class="fas <?php echo $rank_icons[$index]['icon']; ?> <?php echo $rank_icons[$index]['color']; ?>"></i>
                            </div>
                            <div class="top-student-details">
                                <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                <div class="text-muted"><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?></div>
                            </div>
                            <div class="top-student-score">
                                <?php echo round($student['avg_percentage'], 1); ?>%
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="panel">
                <h3 class="panel-header"><i class="fas fa-bell"></i>Upcoming Exams</h3>
                <?php if (empty($upcoming_exams)): ?>
                    <p class="text-center text-muted">No upcoming exams scheduled.</p>
                <?php else: foreach ($upcoming_exams as $exam): ?>
                    <div class="list-item">
                        <div class="icon"><i class="fas fa-pen-alt"></i></div>
                        <div class="content">
                            <strong><?php echo htmlspecialchars($exam['subject_name']); ?></strong>
                            <div class="text-muted"><?php echo htmlspecialchars($exam['class_name'] . ' - ' . $exam['section_name']); ?></div>
                        </div>
                        <div class="date"><?php echo date("M j, Y", strtotime($exam['exam_date'])); ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if (!empty($chart_data)): ?>
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    // Create a subtle gradient for the chart bars
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(79, 70, 229, 0.8)');   
    gradient.addColorStop(1, 'rgba(129, 140, 248, 0.8)');

    // Use a professional-looking font for the chart text
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

    const performanceChart = new Chart(ctx, {
        type: 'bar', // Changed chart type to 'bar'
        data: {
            labels: <?php echo $chart_labels; ?>,
            datasets: [{
                label: 'Average Score (%)',
                data: <?php echo $chart_values; ?>,
                backgroundColor: gradient,
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 1,
                borderRadius: 5,
                hoverBackgroundColor: 'rgba(79, 70, 229, 1)',
            }]
        },
        options: {
            indexAxis: 'y', // This makes the bar chart horizontal
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: '#e5e7eb',
                        borderDash: [5, 5],
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%'
                        }
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 6,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return `Average Score: ${context.parsed.x.toFixed(2)}%`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

</body>
</html>

<?php
require_once './teacher_footer.php';
?>