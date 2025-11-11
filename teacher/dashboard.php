<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}

// Get teacher's info from session
$teacher_id = $_SESSION["id"];
$teacher_name = $_SESSION["full_name"];

// --- DATA FETCHING (ENHANCED) ---

// Helper function to get stats efficiently
function get_teacher_stat($link, $sql, $params = [], $types = "") {
    if ($stmt = mysqli_prepare($link, $sql)) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_row($result)[0] ?? 0;
        mysqli_stmt_close($stmt);
        return $count;
    }
    return 0;
}

// 1. Get Class Teacher Info (if any) and current class details for display
$class_teacher_info = null;
$class_teacher_class_id = null; // Store just the ID for queries
$class_teacher_class_name = 'N/A';
$class_teacher_section_name = 'Unassigned';

$sql_class_teacher = "SELECT id, class_name, section_name FROM classes WHERE teacher_id = ? LIMIT 1";
if ($stmt = mysqli_prepare($link, $sql_class_teacher)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $class_teacher_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($class_teacher_info) {
        $class_teacher_class_id = $class_teacher_info['id'];
        $class_teacher_class_name = $class_teacher_info['class_name'];
        $class_teacher_section_name = $class_teacher_info['section_name'];
    }
}


// 2. Get Stats for Cards
$assigned_classes = get_teacher_stat($link, "SELECT COUNT(DISTINCT class_id) FROM class_subject_teacher WHERE teacher_id = ?", [$teacher_id], "i");
$taught_subjects = get_teacher_stat($link, "SELECT COUNT(DISTINCT subject_id) FROM class_subject_teacher WHERE teacher_id = ?", [$teacher_id], "i");

// "Total Students" specific to the class teacher's class for relevance
$total_students = 0;
if ($class_teacher_class_id) {
    $total_students = get_teacher_stat($link, "SELECT COUNT(id) FROM students WHERE class_id = ?", [$class_teacher_class_id], "i");
}

// Get pending leave applications count for their assigned class
$pending_leaves_count = 0;
if ($class_teacher_class_id) { // Only if they are a class teacher
    $pending_leaves_count = get_teacher_stat($link, "SELECT COUNT(id) FROM leave_applications WHERE class_teacher_id = ? AND status = 'Pending'", [$teacher_id], "i");
}

// 3. Get Today's Schedule
$todays_schedule = [];
$current_day = date('l'); // 'l' gives the full day name
$sql_schedule = "SELECT cp.start_time, cp.end_time, s.subject_name, c.class_name, c.section_name
                FROM class_timetable tt
                JOIN class_periods cp ON tt.period_id = cp.id
                JOIN subjects s ON tt.subject_id = s.id
                JOIN classes c ON tt.class_id = c.id
                WHERE tt.teacher_id = ? AND tt.day_of_week = ?
                ORDER BY cp.start_time ASC";
if ($stmt = mysqli_prepare($link, $sql_schedule)) {
    mysqli_stmt_bind_param($stmt, "is", $teacher_id, $current_day);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $todays_schedule = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// 4. Get Recent Announcements (General announcements, not teacher-specific)
$announcements = [];
$sql_announcements = "SELECT title, content, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3";
if ($result = mysqli_query($link, $sql_announcements)) {
    $announcements = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// 5. Pending Leave Applications Details (Only if Class Teacher)
$pending_leaves = [];
if ($class_teacher_class_id) {
    $sql_leaves = "SELECT la.id, s.first_name, s.middle_name, s.last_name, la.leave_from, la.leave_to, la.reason 
                   FROM leave_applications la
                   JOIN students s ON la.student_id = s.id
                   WHERE la.class_teacher_id = ? AND la.status = 'Pending'
                   ORDER BY la.created_at DESC LIMIT 5";
    if ($stmt = mysqli_prepare($link, $sql_leaves)) {
        mysqli_stmt_bind_param($stmt, "i", $teacher_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $pending_leaves = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

// 6. Upcoming Exams for classes they teach
$upcoming_exams = [];
$sql_exams = "SELECT es.exam_date, et.exam_name, s.subject_name, c.class_name, c.section_name, es.start_time, es.end_time
              FROM exam_schedule es
              JOIN exam_types et ON es.exam_type_id = et.id
              JOIN subjects s ON es.subject_id = s.id
              JOIN classes c ON es.class_id = c.id
              WHERE es.class_id IN (SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_id = ?) 
              AND es.exam_date >= CURDATE()
              ORDER BY es.exam_date ASC, es.start_time ASC
              LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_exams)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $upcoming_exams = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

mysqli_close($link);

// --- PAGE INCLUDES ---
require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard: General Overview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Theme Styles (adapted from student dashboard, but with blue/purple for teacher) */
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(-45deg, #e0f2f7, #e3f2fd, #bbdefb, #90caf9); /* Light Blue/Azure theme */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .welcome-header { margin-bottom: 20px; color: #1a2a4b; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
        .welcome-header h1 { font-weight: 700; font-size: 2.5em; }
        .welcome-header p { font-size: 1.1em; opacity: 0.9; }

        .dashboard-switcher { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; }
        .dashboard-switcher a {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(5px);
            color: #1a2a4b; padding: 10px 15px; border-radius: 8px;
            text-decoration: none; font-weight: 600; font-size: 0.9em;
            transition: background 0.3s, transform 0.2s;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .dashboard-switcher a:hover { background: rgba(255, 255, 255, 0.6); transform: translateY(-2px); }
        .dashboard-switcher a.active {
            background: #1a2a4b; /* Darker blue for active tab */
            color: #fff;
            border-color: #1a2a4b;
        }

        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .card { 
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(10px);
            border-radius: 15px; 
            padding: 25px; 
            border: 1px solid rgba(255, 255, 255, 0.5);
            display: flex; 
            align-items: center; 
            color: #1a2a4b; /* Darker text for cards */
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .card-icon { font-size: 2rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; }
        .card-icon.bg-blue-dark { background: #3f51b5; color: #fff; } /* Darker blue, strong contrast */
        .card-icon.bg-green-dark { background: #4caf50; color: #fff; }
        .card-icon.bg-yellow-dark { background: #ffc107; color: #fff; }
        .card-icon.bg-red-dark { background: #f44336; color: #fff; }

        .card-content h3 { margin: 0; font-size: 1rem; opacity: 0.9; }
        .card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .action-btn { background: #fff; color: #1a2a4b; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .action-btn:hover { background: #1a2a4b; color: #fff; transform: translateY(-3px); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
        .main-panel { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #1a2a4b; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .list-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #f4f4f4; }
        .list-item:last-child { border-bottom: none; }
        .list-item-icon { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; font-size: 1.1rem; }
        .list-item-icon.bg-blue-accent { background: #2196f3; } /* Slightly lighter blue for list items */
        .list-item-icon.bg-red-accent { background: #ef5350; }
        .list-item-icon.bg-yellow-accent { background: #ffeb3b; color: #333;} /* Yellow icon needs darker text */
        .list-item-icon.bg-green-accent { background: #66bb6a; }

        .list-item-content { flex-grow: 1; }
        .list-item-content h4 { margin: 0; color: #333; font-size: 1rem; }
        .list-item-content p { margin: 4px 0 0; color: #666; font-size: 0.9em; }
        .list-item-extra { margin-left: auto; text-align: right; color: #888; font-size: 0.9em; white-space: nowrap; flex-shrink: 0; padding-left: 15px; }
        .text-muted { color: #6c757d !important; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-container { margin-top: 20px; padding: 10px; }
            .welcome-header h1 { font-size: 2em; }
            .card-grid, .quick-actions, .dashboard-grid { grid-template-columns: 1fr; }
            .panel-header { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?>!</h1>
        <p>
            <?php if ($class_teacher_info): ?>
                You are the Class Teacher for <strong>Class <?php echo htmlspecialchars($class_teacher_class_name . ' - ' . $class_teacher_section_name); ?></strong>.
            <?php else: ?>
                Here’s what’s happening today.
            <?php endif; ?>
        </p>
        <div class="dashboard-switcher">
            <a href="dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i> General Overview
            </a>
            <a href="dashboard_academic.php">
                <i class="fas fa-book"></i> Academic Management
            </a>
            <a href="dashboard_communication.php">
                <i class="fas fa-comments"></i> Communication & Welfare
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-blue-dark"><i class="fas fa-chalkboard-teacher"></i></div><div class="card-content"><h3>Assigned Classes</h3><p><?php echo $assigned_classes; ?></p></div></div>
        <div class="card"><div class="card-icon bg-green-dark"><i class="fas fa-book-open"></i></div><div class="card-content"><h3>Subjects Taught</h3><p><?php echo $taught_subjects; ?></p></div></div>
        <?php if ($class_teacher_class_id): // Only show these cards if they are a class teacher ?>
            <div class="card"><div class="card-icon bg-yellow-dark"><i class="fas fa-users"></i></div><div class="card-content"><h3>My Class Students</h3><p><?php echo $total_students; ?></p></div></div>
            <div class="card"><div class="card-icon bg-red-dark"><i class="fas fa-file-alt"></i></div><div class="card-content"><h3>Pending Leaves</h3><p><?php echo $pending_leaves_count; ?></p></div></div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <?php if ($class_teacher_class_id): ?>
            <a href="teacher_attendance.php?class_id=<?php echo $class_teacher_class_id; ?>" class="action-btn"><i class="fas fa-user-check"></i> Mark Attendance</a>
            <a href="teacher_leave_management.php" class="action-btn"><i class="fas fa-tasks"></i> Review Leave</a>
        <?php endif; ?>
        <a href="teacher_assignments.php" class="action-btn"><i class="fas fa-pencil-ruler"></i> Manage Assignments</a>
        <a href="teacher_study_materials.php" class="action-btn"><i class="fas fa-book-reader"></i> Upload Study Material</a>
        <a href="teacher_timetable.php" class="action-btn"><i class="fas fa-calendar-alt"></i> View My Timetable</a>
        <a href="student_forum.php" class="action-btn"><i class="fas fa-comments"></i> Class Forum</a>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-calendar-day"></i>Today's Schedule (<?php echo $current_day; ?>)</h3>
            <?php if (empty($todays_schedule)): ?>
                <p class="text-muted text-center py-4">You have no classes scheduled for today. Enjoy your day!</p>
            <?php else: foreach ($todays_schedule as $schedule): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-blue-accent"><i class="fas fa-clock"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($schedule['subject_name']); ?></h4>
                        <p>Class: <?php echo htmlspecialchars($schedule['class_name'] . ' - ' . $schedule['section_name']); ?></p>
                    </div>
                    <div class="list-item-extra font-medium">
                        <?php echo date("g:i A", strtotime($schedule['start_time'])) . ' - ' . date("g:i A", strtotime($schedule['end_time'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Pending Leave Applications Panel -->
        <?php if ($class_teacher_class_id): ?>
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-file-signature"></i>Pending Leave Requests</h3>
            <?php if (empty($pending_leaves)): ?>
                <p class="text-muted text-center py-4">No pending leave requests to review.</p>
            <?php else: foreach ($pending_leaves as $leave): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-red-accent"><i class="fas fa-user-injured"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars(trim($leave['first_name'] . ' ' . $leave['middle_name'] . ' ' . $leave['last_name'])); ?></h4>
                        <p class="text-truncate"><?php echo htmlspecialchars($leave['reason']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j", strtotime($leave['leave_from'])) . ' - ' . date("M j", strtotime($leave['leave_to'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Upcoming Exams Panel -->
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-graduation-cap"></i>Upcoming Exams</h3>
            <?php if (empty($upcoming_exams)): ?>
                <p class="text-muted text-center py-4">No upcoming exams found for your classes.</p>
            <?php else: foreach ($upcoming_exams as $exam): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-yellow-accent"><i class="fas fa-pen-alt"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($exam['exam_name']); ?> - <?php echo htmlspecialchars($exam['subject_name']); ?></h4>
                        <p>Class: <?php echo htmlspecialchars($exam['class_name'] . ' - ' . $exam['section_name']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("D, M j, Y", strtotime($exam['exam_date'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-bullhorn"></i>Recent Announcements</h3>
            <?php if (empty($announcements)): ?>
                <p class="text-muted text-center py-4">No recent announcements.</p>
            <?php else: foreach ($announcements as $announcement): ?>
                <div class="list-item">
                     <div class="list-item-icon bg-green-accent"><i class="fas fa-info"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                        <p><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . '...'; ?></p>
                    </div>
                     <div class="list-item-extra"><?php echo date("M j, Y", strtotime($announcement['created_at'])); ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once './teacher_footer.php';
?>