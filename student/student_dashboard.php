<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

$student_id = $_SESSION["id"];

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
    // Cannot determine class_id, set message and redirect if critical
    set_session_message("Error: Could not determine your class. Please contact support.", "danger");
    header("location: ../logout.php"); // Or to a generic error page
    exit;
}

// --- 1. FETCH STUDENT'S NAME & IMAGE ---
$student_name = $_SESSION["full_name"] ?? 'Student';
$student_image = $_SESSION["image_url"] ?? '../assets/images/default_profile.png'; // Updated default path

// --- 2. FETCH SUMMARY DATA ---
// Attendance
$attendance_summary = ['total' => 0, 'present' => 0];
$sql_att = "SELECT 
                (SELECT COUNT(DISTINCT attendance_date) FROM attendance WHERE student_id = ? AND class_id = ?) as total_marked_days, 
                (SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'Present') as present_days";
if ($stmt_att = mysqli_prepare($link, $sql_att)) {
    mysqli_stmt_bind_param($stmt_att, "iii", $student_id, $student_class_id, $student_id);
    mysqli_stmt_execute($stmt_att);
    $res_att = mysqli_stmt_get_result($stmt_att);
    $attendance_summary = mysqli_fetch_assoc($res_att) ?: $attendance_summary;
    mysqli_stmt_close($stmt_att);
}
$attendance_percentage = ($attendance_summary['total_marked_days'] > 0) ? ($attendance_summary['present_days'] / $attendance_summary['total_marked_days']) * 100 : 0;


// Upcoming Exams
$upcoming_exam_count = 0;
$sql_exam = "SELECT COUNT(*) FROM exam_schedule WHERE class_id = ? AND exam_date >= CURDATE()";
if ($stmt_exam = mysqli_prepare($link, $sql_exam)) {
    mysqli_stmt_bind_param($stmt_exam, "i", $student_class_id);
    mysqli_stmt_execute($stmt_exam);
    mysqli_stmt_bind_result($stmt_exam, $upcoming_exam_count);
    mysqli_stmt_fetch($stmt_exam);
    mysqli_stmt_close($stmt_exam);
}

// Outstanding Fees
$outstanding_fees = 0;
$sql_fee = "SELECT SUM(amount_due - amount_paid) FROM student_fees WHERE student_id = ? AND status IN ('Unpaid', 'Partially Paid')";
if ($stmt_fee = mysqli_prepare($link, $sql_fee)) {
    mysqli_stmt_bind_param($stmt_fee, "i", $student_id);
    mysqli_stmt_execute($stmt_fee);
    mysqli_stmt_bind_result($stmt_fee, $outstanding_fees);
    mysqli_stmt_fetch($stmt_fee);
    mysqli_stmt_close($stmt_fee);
}

// --- 3. FETCH RECENT ANNOUNCEMENTS (Limit 3) ---
$announcements = [];
$sql_announce = "SELECT title, content, posted_by, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3";
$result_announce = mysqli_query($link, $sql_announce);
if ($result_announce) $announcements = mysqli_fetch_all($result_announce, MYSQLI_ASSOC);

// --- 4. FETCH UPCOMING EVENTS (Limit 3) ---
$events = [];
$sql_events = "SELECT title, description, start_date, event_type, color FROM events WHERE start_date >= NOW() ORDER BY start_date ASC LIMIT 3";
$result_events = mysqli_query($link, $sql_events);
if ($result_events) $events = mysqli_fetch_all($result_events, MYSQLI_ASSOC);

// --- 5. FETCH TODAY'S TIMETABLE ---
$timetable_today = [];
$today_day_of_week = date('l'); // e.g., 'Monday'
$sql_tt = "SELECT cp.period_name, cp.start_time, cp.end_time, s.subject_name, t.full_name as teacher_name
           FROM class_timetable ct
           JOIN class_periods cp ON ct.period_id = cp.id
           JOIN subjects s ON ct.subject_id = s.id
           JOIN teachers t ON ct.teacher_id = t.id
           WHERE ct.class_id = ? AND ct.day_of_week = ?
           ORDER BY cp.start_time";
if ($stmt_tt = mysqli_prepare($link, $sql_tt)) {
    mysqli_stmt_bind_param($stmt_tt, "is", $student_class_id, $today_day_of_week);
    mysqli_stmt_execute($stmt_tt);
    $result_tt = mysqli_stmt_get_result($stmt_tt);
    $timetable_today = mysqli_fetch_all($result_tt, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_tt);
}

// --- 6. FETCH RECENT EXAM PERFORMANCE (Last Exam Type) ---
$recent_performance = [];
$last_exam_type_id = get_stat($link, "SELECT exam_type_id FROM exam_schedule WHERE class_id = ? ORDER BY exam_date DESC LIMIT 1", [$student_class_id], "i");

if ($last_exam_type_id) {
    $sql_perf = "SELECT et.exam_name, s.subject_name, em.marks_obtained, es.max_marks
                 FROM exam_marks em
                 JOIN exam_schedule es ON em.exam_schedule_id = es.id
                 JOIN exam_types et ON es.exam_type_id = et.id
                 JOIN subjects s ON es.subject_id = s.id
                 WHERE em.student_id = ? AND es.exam_type_id = ?
                 ORDER BY s.subject_name";
    if ($stmt_perf = mysqli_prepare($link, $sql_perf)) {
        mysqli_stmt_bind_param($stmt_perf, "ii", $student_id, $last_exam_type_id);
        mysqli_stmt_execute($stmt_perf);
        $result_perf = mysqli_stmt_get_result($stmt_perf);
        $recent_performance = mysqli_fetch_all($result_perf, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_perf);
    }
}

// --- 7. FETCH MY ACTIVITIES (Clubs & Programs) ---
$my_activities = [];
$sql_clubs = "SELECT sc.name, 'Sports Club' as type, sc.id as activity_id FROM sports_clubs sc JOIN club_members cm ON sc.id = cm.club_id WHERE cm.student_id = ?";
$sql_programs = "SELECT cp.name, 'Cultural Program' as type, cp.id as activity_id FROM cultural_programs cp JOIN program_participants pp ON cp.id = pp.program_id WHERE pp.student_id = ?";
                   
if ($stmt_clubs = mysqli_prepare($link, $sql_clubs)) {
    mysqli_stmt_bind_param($stmt_clubs, "i", $student_id);
    mysqli_stmt_execute($stmt_clubs);
    $result_clubs = mysqli_stmt_get_result($stmt_clubs);
    while ($row = mysqli_fetch_assoc($result_clubs)) { $my_activities[] = $row; }
    mysqli_stmt_close($stmt_clubs);
}
if ($stmt_programs = mysqli_prepare($link, $sql_programs)) {
    mysqli_stmt_bind_param($stmt_programs, "i", $student_id);
    mysqli_stmt_execute($stmt_programs);
    $result_programs = mysqli_stmt_get_result($stmt_programs);
    while ($row = mysqli_fetch_assoc($result_programs)) { $my_activities[] = $row; }
    mysqli_stmt_close($stmt_programs);
}
// Sort activities alphabetically
usort($my_activities, function($a, $b) { return strcmp($a['name'], $b['name']); });


mysqli_close($link);

// --- PAGE INCLUDES ---
require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Overview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(-45deg, #A8D9FF, #C0E6FF, #D9F0FF, #EBF7FF); /* Light blue, fresh */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .welcome-header { margin-bottom: 20px; color: #1e3a8a; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
        .welcome-header h1 { font-weight: 600; font-size: 2.2em; }
        .welcome-header p { font-size: 1.1em; opacity: 0.9; }
        .dashboard-switcher { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; }
        .dashboard-switcher a {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(5px);
            color: #1e3a8a; padding: 10px 15px; border-radius: 8px;
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
            color: #1e3a8a; /* Dark blue text for cards */
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .card-icon { font-size: 2rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; }
        .card-icon.bg-green { background: #d4edda; color: #155724; } 
        .card-icon.bg-yellow { background: #fff3cd; color: #856404; }
        .card-icon.bg-red { background: #f8d7da; color: #721c24; }
        .card-icon.bg-blue { background: #cce5ff; color: #004085; }

        .card-content h3 { margin: 0; font-size: 1rem; opacity: 0.9; }
        .card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .action-btn { background: #fff; color: #1e3a8a; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .action-btn:hover { background: #1e3a8a; color: #fff; transform: translateY(-3px); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
        .main-panel { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #1e3a8a; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .list-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #f4f4f4; }
        .list-item:last-child { border-bottom: none; }
        .list-item-icon { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; font-size: 1.1rem; }
        .list-item-icon.bg-blue-dark { background: #1e3a8a; } 
        .list-item-icon.bg-purple-dark { background: #483D8B; }
        .list-item-icon.bg-green-dark { background: #2E7D32; }
        .list-item-icon.bg-orange-dark { background: #DAA520; }

        .list-item-content h4 { margin: 0; color: #333; font-size: 1rem; }
        .list-item-content p { margin: 4px 0 0; color: #666; font-size: 0.9em; }
        .list-item-extra { margin-left: auto; text-align: right; color: #888; font-size: 0.9em; white-space: nowrap; flex-shrink: 0; padding-left: 15px; }
        .text-muted { color: #6c757d !important; }
        .badge { display: inline-block; padding: 0.3em 0.6em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.3rem; color: #fff; }
        .badge-info { background-color: #17a2b8; }
        .badge-success { background-color: #28a745; }
        .badge-primary { background-color: #007bff; }
        .badge-warning { background-color: #ffc107; color: #212529; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>!</h1>
        <p>Your Student Overview Dashboard.</p>
        <div class="dashboard-switcher">
            <a href="student_dashboard_academics.php">
                <i class="fas fa-arrow-right"></i> Academic Progress
            </a>
            <a href="student_dashboard_welfare_activities.php">
                <i class="fas fa-arrow-right"></i> Welfare & Activities
            </a>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-green"><i class="fas fa-user-check"></i></div><div class="card-content"><h3>Attendance</h3><p><?php echo number_format($attendance_percentage, 1); ?>%</p></div></div>
        <div class="card"><div class="card-icon bg-yellow"><i class="fas fa-file-alt"></i></div><div class="card-content"><h3>Upcoming Exams</h3><p><?php echo $upcoming_exam_count; ?></p></div></div>
        <div class="card"><div class="card-icon bg-red"><i class="fas fa-dollar-sign"></i></div><div class="card-content"><h3>Outstanding Fees</h3><p>₹<?php echo number_format($outstanding_fees ?? 0, 2); ?></p></div></div>
        <div class="card"><div class="card-icon bg-blue"><i class="fas fa-book"></i></div><div class="card-content"><h3>My Activities</h3><p><?php echo count($my_activities); ?></p></div></div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="my_profile.php" class="action-btn"><i class="fas fa-user-circle"></i> My Profile</a>
        <a href="student_timetable.php" class="action-btn"><i class="fas fa-calendar-alt"></i> My Timetable</a>
        <a href="student_exams.php" class="action-btn"><i class="fas fa-pencil-alt"></i> Exam Schedule</a>
        <a href="fee_payment.php" class="action-btn"><i class="fas fa-money-check-alt"></i> My Fees</a>
        <a href="student_assignments.php" class="action-btn"><i class="fas fa-folder-open"></i> Assignments</a>
        <a href="student_study_materials.php" class="action-btn"><i class="fas fa-book-reader"></i> Study Materials</a>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Left Column -->
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-calendar-day"></i>Today's Timetable (<?php echo $today_day_of_week; ?>)</h3>
            <?php if (!empty($timetable_today)): ?>
                <?php foreach ($timetable_today as $slot): ?>
                    <div class="list-item">
                        <div class="list-item-icon bg-blue-dark"><i class="fas fa-clock"></i></div>
                        <div class="list-item-content">
                            <h4><?php echo htmlspecialchars($slot['subject_name']); ?></h4>
                            <p>Teacher: <?php echo htmlspecialchars($slot['teacher_name']); ?></p>
                        </div>
                        <div class="list-item-extra">
                            <?php echo date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center py-4">No classes scheduled for today. Enjoy your day!</p>
            <?php endif; ?>
            <a href="student_timetable.php" class="action-btn mt-4"><i class="fas fa-calendar-alt"></i> View Full Timetable</a>
        </div>

        <!-- Right Column - Combined Announcements & Events -->
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-bullhorn"></i>Recent Announcements</h3>
            <?php if(empty($announcements)): ?>
                <p class="text-muted text-center py-4">No new announcements.</p>
            <?php else: foreach($announcements as $item): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-purple-dark"><i class="fas fa-info-circle"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                        <p><?php echo htmlspecialchars(substr($item['content'], 0, 80)); ?><?php echo (strlen($item['content']) > 80 ? '...' : ''); ?></p>
                    </div>
                    <div class="list-item-extra"><?php echo date("M j", strtotime($item['created_at'])); ?></div>
                </div>
            <?php endforeach; endif; ?>
            <a href="student_announcements.php" class="action-btn mt-4"><i class="fas fa-bullhorn"></i> View All Announcements</a>
            
            <h3 class="panel-header mt-8"><i class="fas fa-calendar-alt"></i>Upcoming Events</h3>
            <?php if(empty($events)): ?>
                <p class="text-muted text-center py-4">No upcoming events.</p>
            <?php else: foreach($events as $event): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-green-dark" style="background-color: <?php echo htmlspecialchars($event['color'] ?: '#2E7D32'); ?>;"><i class="fas fa-calendar"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                        <p>Type: <?php echo htmlspecialchars($event['event_type']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date('M j, Y', strtotime($event['start_date'])); ?>
                        <?php if ($event['end_date'] && $event['start_date'] != $event['end_date']): ?>
                            - <?php echo date("M j, Y", strtotime($event['end_date'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="student_events.php" class="action-btn mt-4"><i class="fas fa-calendar-check"></i> View All Events</a>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-chart-line"></i>Recent Exam Performance</h3>
            <?php if(empty($recent_performance)): ?>
                <p class="text-muted text-center py-4">No recent exam results to display.</p>
            <?php else: ?>
                <p class="text-muted mb-4">Latest Exam Type: <strong><?php echo htmlspecialchars($recent_performance[0]['exam_name'] ?? 'N/A'); ?></strong></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($recent_performance as $mark): 
                        $percentage = ($mark['max_marks'] > 0) ? ($mark['marks_obtained'] / $mark['max_marks']) * 100 : 0; ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-100 flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($mark['subject_name']); ?></p>
                                <p class="text-2xl font-bold <?php echo ($percentage >= 40) ? 'text-green-600' : 'text-red-600'; ?>"><?php echo htmlspecialchars($mark['marks_obtained']); ?><span class="text-sm font-normal text-gray-500">/<?php echo htmlspecialchars($mark['max_marks']); ?></span></p>
                            </div>
                            <p class="font-bold text-lg <?php echo ($percentage >= 40) ? 'text-green-600' : 'text-red-600'; ?>"><?php echo number_format($percentage, 0); ?>%</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="student_performance.php" class="action-btn mt-4"><i class="fas fa-chart-bar"></i> View All Results</a>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-star"></i>My Activities</h3>
            <?php if(empty($my_activities)): ?>
                <p class="text-muted text-center py-4">You are not enrolled in any clubs or programs.</p>
            <?php else: foreach($my_activities as $activity): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-orange-dark"><i class="fas <?php echo ($activity['type'] === 'Sports Club') ? 'fa-futbol' : 'fa-palette'; ?>"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($activity['name']); ?></h4>
                        <p><?php echo htmlspecialchars($activity['type']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <a href="<?php echo ($activity['type'] === 'Sports Club') ? 'student_sports_clubs.php?view_club_id='.$activity['activity_id'] : 'student_cultural_programs.php?view_program_id='.$activity['activity_id']; ?>" class="badge badge-primary">Details</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="student_activities.php" class="action-btn mt-4"><i class="fas fa-compass"></i> Explore All Activities</a>
        </div>
    </div>
</div>

</body>
</html>
<?php require_once './student_footer.php'; ?>