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

// --- DATA FETCHING ---

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

// 2. Get Academic Stats for cards
$total_assignments_posted = get_teacher_stat($link, "SELECT COUNT(id) FROM assignments WHERE teacher_id = ?", [$teacher_id], "i");
$total_study_materials_uploaded = get_teacher_stat($link, "SELECT COUNT(id) FROM study_materials WHERE teacher_id = ?", [$teacher_id], "i");
$total_online_tests_created = get_teacher_stat($link, "SELECT COUNT(id) FROM online_tests WHERE teacher_id = ?", [$teacher_id], "i");
// Number of pending mark entries (exams where this teacher is assigned and marks are not yet uploaded by anyone)
$pending_mark_entries = get_teacher_stat($link, 
    "SELECT COUNT(es.id) 
    FROM exam_schedule es
    JOIN class_subject_teacher cst ON es.class_id = cst.class_id AND es.subject_id = cst.subject_id
    WHERE cst.teacher_id = ?
    AND es.id NOT IN (SELECT exam_schedule_id FROM exam_marks WHERE exam_schedule_id = es.id GROUP BY exam_schedule_id HAVING COUNT(student_id) = (SELECT COUNT(id) FROM students WHERE class_id = es.class_id))", // Ensure ALL students have marks uploaded
    [$teacher_id], "i"
);


// 3. Fetch Recent Assignments Posted by this Teacher (Limit 5)
$recent_assignments = [];
$sql_recent_assignments = "SELECT a.title, a.due_date, s.subject_name, c.class_name, c.section_name
                           FROM assignments a
                           JOIN subjects s ON a.subject_id = s.id
                           JOIN classes c ON a.class_id = c.id
                           WHERE a.teacher_id = ?
                           ORDER BY a.created_at DESC LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_recent_assignments)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recent_assignments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// 4. Fetch Recent Study Materials Uploaded by this Teacher (Limit 5)
$recent_study_materials = [];
$sql_recent_sm = "SELECT sm.title, sm.uploaded_at, s.subject_name, c.class_name, c.section_name
                  FROM study_materials sm
                  JOIN subjects s ON sm.subject_id = s.id
                  JOIN classes c ON sm.class_id = c.id
                  WHERE sm.teacher_id = ?
                  ORDER BY sm.uploaded_at DESC LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_recent_sm)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recent_study_materials = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// 5. Fetch Recently Published Online Tests by this Teacher (Limit 5)
$recent_online_tests = [];
$sql_recent_tests = "SELECT ot.title, ot.created_at, s.subject_name, c.class_name, c.section_name, ot.status
                     FROM online_tests ot
                     JOIN subjects s ON ot.subject_id = s.id
                     JOIN classes c ON ot.class_id = c.id
                     WHERE ot.teacher_id = ? AND ot.status = 'Published'
                     ORDER BY ot.created_at DESC LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_recent_tests)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recent_online_tests = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// 6. Fetch Upcoming Exams relevant to this teacher for mark entry (Limit 5)
$upcoming_exams_for_marks = [];
$sql_upcoming_exams_marks = "SELECT es.exam_date, et.exam_name, s.subject_name, c.class_name, c.section_name, es.max_marks
                             FROM exam_schedule es
                             JOIN exam_types et ON es.exam_type_id = et.id
                             JOIN subjects s ON es.subject_id = s.id
                             JOIN classes c ON es.class_id = c.id
                             JOIN class_subject_teacher cst ON es.class_id = cst.class_id AND es.subject_id = cst.subject_id
                             WHERE cst.teacher_id = ? 
                             AND es.exam_date >= CURDATE()
                             ORDER BY es.exam_date ASC, es.start_time ASC
                             LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_upcoming_exams_marks)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $upcoming_exams_for_marks = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
    <title>Teacher Dashboard: Academic Management</title>
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
        .list-item-icon.bg-purple-accent { background: #9c27b0; } /* New accent color */
        .list-item-icon.bg-orange-accent { background: #ff9800; } /* New accent color */


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
            .dashboard-switcher a { font-size: 0.8em; padding: 8px 10px; }
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
                Manage your academic responsibilities here.
            <?php endif; ?>
        </p>
        <div class="dashboard-switcher">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> General Overview
            </a>
            <a href="dashboard_academic.php" class="active">
                <i class="fas fa-book"></i> Academic Management
            </a>
            <a href="dashboard_communication.php">
                <i class="fas fa-comments"></i> Communication & Welfare
            </a>
        </div>
    </div>

    <!-- Academic Stat Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-blue-dark"><i class="fas fa-file-alt"></i></div><div class="card-content"><h3>Assignments Posted</h3><p><?php echo $total_assignments_posted; ?></p></div></div>
        <div class="card"><div class="card-icon bg-green-dark"><i class="fas fa-book-reader"></i></div><div class="card-content"><h3>Study Materials Uploaded</h3><p><?php echo $total_study_materials_uploaded; ?></p></div></div>
        <div class="card"><div class="card-icon bg-yellow-dark"><i class="fas fa-laptop-code"></i></div><div class="card-content"><h3>Online Tests Created</h3><p><?php echo $total_online_tests_created; ?></p></div></div>
        <div class="card"><div class="card-icon bg-red-dark"><i class="fas fa-percent"></i></div><div class="card-content"><h3>Pending Mark Entries</h3><p><?php echo $pending_mark_entries; ?></p></div></div>
    </div>
    
    <!-- Academic Quick Actions -->
    <div class="quick-actions">
        <a href="teacher_assignments.php" class="action-btn"><i class="fas fa-upload"></i> Post New Assignment</a>
        <a href="teacher_study_materials.php" class="action-btn"><i class="fas fa-plus-circle"></i> Upload Study Material</a>
        <a href="teacher_manage_tests.php" class="action-btn"><i class="fas fa-laptop"></i> Create Online Test</a>
        <a href="teacher_upload_marks.php" class="action-btn"><i class="fas fa-marker"></i> Enter Exam Marks</a>
        <a href="teacher_timetable.php" class="action-btn"><i class="fas fa-calendar-alt"></i> View My Timetable</a>
        <a href="teacher_exams.php?view=upcoming" class="action-btn"><i class="fas fa-clipboard-list"></i> View Exam Schedules</a>
    </div>

    <!-- Main Dashboard Grid for Academic Items -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-file-alt"></i>Recent Assignments Posted</h3>
            <?php if (empty($recent_assignments)): ?>
                <p class="text-muted text-center py-4">No recent assignments posted by you.</p>
            <?php else: foreach ($recent_assignments as $assignment): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-blue-accent"><i class="fas fa-tasks"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                        <p>Subject: <?php echo htmlspecialchars($assignment['subject_name']); ?> | Class: <?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['section_name']); ?></p>
                    </div>
                    <div class="list-item-extra font-medium">
                        Due: <?php echo date("M j, Y", strtotime($assignment['due_date'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-book-reader"></i>Recent Study Materials</h3>
            <?php if (empty($recent_study_materials)): ?>
                <p class="text-muted text-center py-4">No recent study materials uploaded by you.</p>
            <?php else: foreach ($recent_study_materials as $material): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-green-accent"><i class="fas fa-folder-open"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                        <p>Subject: <?php echo htmlspecialchars($material['subject_name']); ?> | Class: <?php echo htmlspecialchars($material['class_name'] . ' - ' . $material['section_name']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j, Y", strtotime($material['uploaded_at'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-laptop-code"></i>Recently Published Online Tests</h3>
            <?php if (empty($recent_online_tests)): ?>
                <p class="text-muted text-center py-4">No online tests published by you recently.</p>
            <?php else: foreach ($recent_online_tests as $test): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-purple-accent"><i class="fas fa-check-square"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($test['title']); ?></h4>
                        <p>Subject: <?php echo htmlspecialchars($test['subject_name']); ?> | Class: <?php echo htmlspecialchars($test['class_name'] . ' - ' . $test['section_name']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j, Y", strtotime($test['created_at'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-pencil-alt"></i>Upcoming Exams (for Marks)</h3>
            <?php if (empty($upcoming_exams_for_marks)): ?>
                <p class="text-muted text-center py-4">No upcoming exams requiring mark entry.</p>
            <?php else: foreach ($upcoming_exams_for_marks as $exam): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-orange-accent"><i class="fas fa-calendar-check"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($exam['exam_name']); ?> - <?php echo htmlspecialchars($exam['subject_name']); ?></h4>
                        <p>Class: <?php echo htmlspecialchars($exam['class_name'] . ' - ' . $exam['section_name']); ?> | Max Marks: <?php echo htmlspecialchars($exam['max_marks']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j, Y", strtotime($exam['exam_date'])); ?>
                    </div>
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