<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

$student_id = $_SESSION["id"];
$student_name = $_SESSION["full_name"];

// Robustly get student's class_id
$student_class_id = $_SESSION["class_id"] ?? null;
if (!$student_class_id) {
    $sql_get_class = "SELECT class_id FROM students WHERE id = ? LIMIT 1";
    if ($stmt_get_class = mysqli_prepare($link, $sql_get_class)) {
        mysqli_stmt_bind_param($stmt_get_class, "i", $student_id);
        mysqli_stmt_execute($stmt_get_class);
        mysqli_stmt_bind_result($stmt_get_class, $student_class_id);
        mysqli_stmt_fetch($stmt_get_class);
        mysqli_stmt_close($stmt_get_class);
        if ($student_class_id) $_SESSION["class_id"] = $student_class_id;
    }
}
if (!$student_class_id) {
    set_session_message("Error: Could not determine your class. Please contact support.", "danger");
    header("location: ../logout.php");
    exit;
}

// Helper function to get stats
function get_stat($link, $sql, $params = [], $types = "") {
    if ($stmt = mysqli_prepare($link, $sql)) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $value = mysqli_fetch_row($result)[0] ?? 0;
        mysqli_stmt_close($stmt);
        return $value;
    }
    return 0;
}

// --- 1. FETCH ACADEMIC STATS ---
$total_subjects = get_stat($link, "SELECT COUNT(DISTINCT subject_id) FROM class_subject_teacher WHERE class_id = ?", [$student_class_id], "i");
$pending_assignments = get_stat($link, "SELECT COUNT(a.id) FROM assignments a JOIN class_subject_teacher cst ON a.class_id = cst.class_id AND a.subject_id = cst.subject_id WHERE a.class_id = ? AND a.due_date >= CURDATE()", [$student_class_id], "i");
$upcoming_exams_count = get_stat($link, "SELECT COUNT(id) FROM exam_schedule WHERE class_id = ? AND exam_date >= CURDATE()", [$student_class_id], "i");
$total_online_tests = get_stat($link, "SELECT COUNT(id) FROM online_tests WHERE class_id = ? AND status = 'Published'", [$student_class_id], "i");


// --- 2. FETCH UPCOMING EXAMS (Limit 5) ---
$upcoming_exams = [];
$sql_upcoming_exams = "SELECT es.exam_date, et.exam_name, s.subject_name, es.start_time, es.end_time
                       FROM exam_schedule es
                       JOIN exam_types et ON es.exam_type_id = et.id
                       JOIN subjects s ON es.subject_id = s.id
                       WHERE es.class_id = ? AND es.exam_date >= CURDATE()
                       ORDER BY es.exam_date ASC, es.start_time ASC
                       LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_upcoming_exams)) {
    mysqli_stmt_bind_param($stmt, "i", $student_class_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $upcoming_exams = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// --- 3. FETCH PENDING ASSIGNMENTS (Limit 5) ---
$pending_assignments_list = [];
$sql_pending_assignments = "SELECT a.title, a.description, a.due_date, s.subject_name, t.full_name AS teacher_name
                            FROM assignments a
                            JOIN subjects s ON a.subject_id = s.id
                            JOIN teachers t ON a.teacher_id = t.id
                            WHERE a.class_id = ? AND a.due_date >= CURDATE()
                            ORDER BY a.due_date ASC
                            LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_pending_assignments)) {
    mysqli_stmt_bind_param($stmt, "i", $student_class_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $pending_assignments_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// --- 4. FETCH RECENT STUDY MATERIALS (Limit 5) ---
$recent_study_materials = [];
$sql_study_materials = "SELECT sm.title, sm.description, sm.file_name, sm.file_url, s.subject_name, t.full_name AS teacher_name
                        FROM study_materials sm
                        JOIN subjects s ON sm.subject_id = s.id
                        JOIN teachers t ON sm.teacher_id = t.id
                        WHERE sm.class_id = ?
                        ORDER BY sm.uploaded_at DESC
                        LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_study_materials)) {
    mysqli_stmt_bind_param($stmt, "i", $student_class_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recent_study_materials = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// --- 5. FETCH PROGRESS IN SUBJECTS (Example: Average Marks per Subject if available) ---
$subject_progress = [];
$sql_subject_avg_marks = "SELECT 
                            subj.subject_name,
                            AVG(em.marks_obtained / es.max_marks * 100) AS average_percentage,
                            MAX(em.marks_obtained) AS highest_mark,
                            MAX(es.max_marks) AS max_marks_for_highest
                          FROM exam_marks em
                          JOIN exam_schedule es ON em.exam_schedule_id = es.id
                          JOIN subjects subj ON es.subject_id = subj.id
                          WHERE em.student_id = ? AND es.max_marks > 0
                          GROUP BY subj.subject_name
                          ORDER BY subj.subject_name";
if ($stmt = mysqli_prepare($link, $sql_subject_avg_marks)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $subject_progress = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


mysqli_close($link);

// --- PAGE INCLUDES ---
require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Academics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(-45deg, #FFFDE7, #FFF8E1, #FFECB3, #FFDDAA); /* Academic: Light Yellow/Creamy */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .welcome-header { margin-bottom: 20px; color: #a0522d; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
        .welcome-header h1 { font-weight: 600; font-size: 2.2em; }
        .welcome-header p { font-size: 1.1em; opacity: 0.9; }
        .dashboard-switcher { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; }
        .dashboard-switcher a {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(5px);
            color: #a0522d; padding: 10px 15px; border-radius: 8px;
            text-decoration: none; font-weight: 600; font-size: 0.9em;
            transition: background 0.3s, transform 0.2s;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .dashboard-switcher a:hover { background: rgba(255, 255, 255, 0.6); transform: translateY(-2px); }

        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .card { 
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(10px);
            border-radius: 15px; 
            padding: 25px; 
            border: 1px solid rgba(255, 255, 255, 0.5);
            display: flex; 
            align-items: center; 
            color: #a0522d; /* Darker text for cards */
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .card-icon { font-size: 2rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; }
        .card-icon.bg-orange { background: #ffecb3; color: #a0522d; } 
        .card-icon.bg-yellow { background: #fff3cd; color: #856404; }
        .card-icon.bg-green { background: #d4edda; color: #155724; }
        .card-icon.bg-blue { background: #cce5ff; color: #004085; }

        .card-content h3 { margin: 0; font-size: 1rem; opacity: 0.9; }
        .card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .action-btn { background: #fff; color: #a0522d; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .action-btn:hover { background: #a0522d; color: #fff; transform: translateY(-3px); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
        .main-panel { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #a0522d; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .list-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #f4f4f4; }
        .list-item:last-child { border-bottom: none; }
        .list-item-icon { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; font-size: 1.1rem; }
        .list-item-icon.bg-orange-dark { background: #d35400; } 
        .list-item-icon.bg-yellow-dark { background: #FBC02D; }
        .list-item-icon.bg-green-dark { background: #27ae60; }
        .list-item-icon.bg-blue-dark { background: #2980b9; }

        .list-item-content h4 { margin: 0; color: #333; font-size: 1rem; }
        .list-item-content p { margin: 4px 0 0; color: #666; font-size: 0.9em; }
        .list-item-extra { margin-left: auto; text-align: right; color: #888; font-size: 0.9em; white-space: nowrap; flex-shrink: 0; padding-left: 15px; }
        .text-muted { color: #6c757d !important; }
        .badge { display: inline-block; padding: 0.3em 0.6em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.3rem; color: #fff; }
        .badge-info { background-color: #17a2b8; }
        .badge-success { background-color: #28a745; }
        .badge-primary { background-color: #007bff; }
        .badge-warning { background-color: #ffc107; color: #212529; }
        .badge-danger { background-color: #dc3545; }
        .progress-bar-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            height: 10px;
            margin-top: 5px;
        }
        .progress-bar {
            height: 100%;
            width: 0%;
            background-color: #4CAF50; /* Green */
            border-radius: 5px;
            transition: width 0.5s ease-in-out;
        }
        .progress-bar.red { background-color: #f44336; }
        .progress-bar.yellow { background-color: #ffc107; }

    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>!</h1>
        <p>Your Academic Progress at a Glance.</p>
        <div class="dashboard-switcher">
            <a href="student_dashboard.php">
                <i class="fas fa-arrow-left"></i> General Overview
            </a>
            <a href="student_dashboard_welfare_activities.php">
                <i class="fas fa-arrow-right"></i> Welfare & Activities
            </a>
        </div>
    </div>
    
    <!-- Academic Summary Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-orange"><i class="fas fa-book-open"></i></div><div class="card-content"><h3>Total Subjects</h3><p><?php echo $total_subjects; ?></p></div></div>
        <div class="card"><div class="card-icon bg-yellow"><i class="fas fa-clipboard-list"></i></div><div class="card-content"><h3>Pending Assignments</h3><p><?php echo $pending_assignments; ?></p></div></div>
        <div class="card"><div class="card-icon bg-green"><i class="fas fa-file-alt"></i></div><div class="card-content"><h3>Upcoming Exams</h3><p><?php echo $upcoming_exams_count; ?></p></div></div>
        <div class="card"><div class="card-icon bg-blue"><i class="fas fa-laptop-code"></i></div><div class="card-content"><h3>Online Tests</h3><p><?php echo $total_online_tests; ?></p></div></div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="student_timetable.php" class="action-btn"><i class="fas fa-calendar-alt"></i> My Timetable</a>
        <a href="student_assignments.php" class="action-btn"><i class="fas fa-folder-open"></i> Assignments</a>
        <a href="student_exams.php" class="action-btn"><i class="fas fa-pencil-alt"></i> Exam Schedule</a>
        <a href="student_exams.php" class="action-btn"><i class="fas fa-chart-bar"></i> All Results</a>
        <a href="student_study_materials.php" class="action-btn"><i class="fas fa-book-reader"></i> Study Materials</a>
        <a href="student_tests.php" class="action-btn"><i class="fas fa-laptop"></i> Take Online Test</a>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-file-alt"></i>Upcoming Exams</h3>
            <?php if (empty($upcoming_exams)): ?>
                <p class="text-muted text-center py-4">No upcoming exams scheduled.</p>
            <?php else: foreach ($upcoming_exams as $exam): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-orange-dark"><i class="fas fa-calendar"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($exam['exam_name']); ?>: <?php echo htmlspecialchars($exam['subject_name']); ?></h4>
                        <p>Time: <?php echo date('g:i A', strtotime($exam['start_time'])) . ' - ' . date('g:i A', strtotime($exam['end_time'])); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date('M j, Y', strtotime($exam['exam_date'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="student_exam_schedule.php" class="action-btn mt-4"><i class="fas fa-arrow-right"></i> View Full Schedule</a>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-clipboard-list"></i>Pending Assignments</h3>
            <?php if (empty($pending_assignments_list)): ?>
                <p class="text-muted text-center py-4">No pending assignments. Great job!</p>
            <?php else: foreach ($pending_assignments_list as $assignment): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-yellow-dark"><i class="fas fa-tasks"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($assignment['title']); ?> (<?php echo htmlspecialchars($assignment['subject_name']); ?>)</h4>
                        <p>Due: <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?> | By: <?php echo htmlspecialchars($assignment['teacher_name']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <span class="badge badge-warning">Due Soon</span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="student_assignments.php" class="action-btn mt-4"><i class="fas fa-arrow-right"></i> Manage Assignments</a>
        </div>
        
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-chart-line"></i>My Subject Progress</h3>
            <?php if (empty($subject_progress)): ?>
                <p class="text-muted text-center py-4">No exam data available to show progress.</p>
            <?php else: foreach ($subject_progress as $progress): 
                $avg_percent = round($progress['average_percentage'] ?? 0);
                $progress_class = 'red';
                if ($avg_percent >= 75) $progress_class = 'green';
                else if ($avg_percent >= 50) $progress_class = 'yellow';
            ?>
                <div class="list-item">
                    <div class="list-item-icon bg-green-dark"><i class="fas fa-book"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($progress['subject_name']); ?></h4>
                        <p>Avg: <?php echo $avg_percent; ?>% (Highest: <?php echo htmlspecialchars($progress['highest_mark']); ?>/<?php echo htmlspecialchars($progress['max_marks_for_highest']); ?>)</p>
                        <div class="progress-bar-container">
                            <div class="progress-bar <?php echo $progress_class; ?>" style="width: <?php echo min(100, $avg_percent); ?>%;"></div>
                        </div>
                    </div>
                    <div class="list-item-extra">
                        <span class="badge <?php echo ($avg_percent >= 50) ? 'badge-success' : 'badge-danger'; ?>"><?php echo $avg_percent; ?>%</span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="student_performance.php" class="action-btn mt-4"><i class="fas fa-arrow-right"></i> Detailed Progress</a>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-book-reader"></i>Recent Study Materials</h3>
            <?php if (empty($recent_study_materials)): ?>
                <p class="text-muted text-center py-4">No recent study materials uploaded.</p>
            <?php else: foreach ($recent_study_materials as $material): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-blue-dark"><i class="fas fa-file-pdf"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                        <p>Subject: <?php echo htmlspecialchars($material['subject_name']); ?> | Teacher: <?php echo htmlspecialchars($material['teacher_name']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php if ($material['file_url']): ?>
                            <a href="<?php echo htmlspecialchars($material['file_url']); ?>" target="_blank" class="badge badge-primary">Download <i class="fas fa-download"></i></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="student_study_materials.php" class="action-btn mt-4"><i class="fas fa-arrow-right"></i> All Study Materials</a>
        </div>
    </div>
</div>

</body>
</html>
<?php require_once './student_footer.php'; ?>