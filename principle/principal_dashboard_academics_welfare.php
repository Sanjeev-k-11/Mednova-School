<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"];
$principal_name = $_SESSION["full_name"];

// --- DATA FETCHING ---

// Helper function to get stats efficiently
function get_stat($link, $sql, $params = [], $types = "") {
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

// 1. Get Stats for Cards
$total_exams_scheduled = get_stat($link, "SELECT COUNT(id) FROM exam_schedule");
$total_scholarships_assigned = get_stat($link, "SELECT COUNT(id) FROM student_scholarships");
$open_helpdesk_tickets = get_stat($link, "SELECT COUNT(id) FROM support_tickets WHERE status = 'Open'");
$upcoming_events_count = get_stat($link, "SELECT COUNT(id) FROM events WHERE start_date >= CURDATE()");
$total_clubs = get_stat($link, "SELECT COUNT(id) FROM sports_clubs WHERE status = 'Active'");
$total_competitions = get_stat($link, "SELECT COUNT(id) FROM competitions WHERE status != 'Cancelled'");
$total_cultural_programs = get_stat($link, "SELECT COUNT(id) FROM cultural_programs WHERE status != 'Cancelled'");


// 2. Get Upcoming Exams
$upcoming_exams = [];
$sql_exams = "SELECT es.exam_date, et.exam_name, s.subject_name, c.class_name, c.section_name
              FROM exam_schedule es
              JOIN exam_types et ON es.exam_type_id = et.id
              JOIN subjects s ON es.subject_id = s.id
              JOIN classes c ON es.class_id = c.id
              WHERE es.exam_date >= CURDATE()
              ORDER BY es.exam_date ASC, es.start_time ASC
              LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_exams)) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $upcoming_exams = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// 3. Get Recent Open Helpdesk Tickets
$recent_open_tickets = [];
$sql_tickets = "SELECT st.id, st.title, s.first_name, s.last_name, c.class_name, c.section_name, st.created_at
                FROM support_tickets st
                JOIN students s ON st.student_id = s.id
                JOIN classes c ON st.class_id = c.id
                WHERE st.status = 'Open'
                ORDER BY st.created_at DESC LIMIT 5";
if ($result = mysqli_query($link, $sql_tickets)) {
    $recent_open_tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// 4. Get Recent Cultural/Sports Activity (Combined)
$recent_activities = [];
$sql_cultural_programs = "SELECT id, name AS title, program_date AS date, 'Cultural Program' AS type, description FROM cultural_programs WHERE program_date >= CURDATE() ORDER BY program_date ASC LIMIT 3";
$sql_competitions = "SELECT id, name AS title, competition_date AS date, 'Competition' AS type, description FROM competitions WHERE competition_date >= CURDATE() ORDER BY competition_date ASC LIMIT 3";

if ($result = mysqli_query($link, $sql_cultural_programs)) { while ($row = mysqli_fetch_assoc($result)) { $recent_activities[] = $row; } }
if ($result = mysqli_query($link, $sql_competitions)) { while ($row = mysqli_fetch_assoc($result)) { $recent_activities[] = $row; } }

usort($recent_activities, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });
$recent_activities = array_slice($recent_activities, 0, 5); // Top 5 upcoming activities

// 5. Get Recent Study Materials Uploaded
$recent_study_materials = [];
$sql_study_materials = "SELECT sm.title, sm.uploaded_at, sm.file_name, c.class_name, c.section_name, subj.subject_name
                        FROM study_materials sm
                        JOIN classes c ON sm.class_id = c.id
                        JOIN subjects subj ON sm.subject_id = subj.id
                        ORDER BY sm.uploaded_at DESC LIMIT 5";
if ($result = mysqli_query($link, $sql_study_materials)) {
    $recent_study_materials = mysqli_fetch_all($result, MYSQLI_ASSOC);
}


mysqli_close($link);

// --- PAGE INCLUDES ---
require_once './principal_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Dashboard - Academic & Welfare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(-45deg, #A8C0FF, #D8BFD8, #ADD8E6, #B0E0E6); /* Academic/Welfare: Softer blues, purples */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .welcome-header { margin-bottom: 20px; color: #fff; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .welcome-header h1 { font-weight: 600; font-size: 2.2em; }
        .welcome-header p { font-size: 1.1em; opacity: 0.9; }
        .dashboard-switcher { margin-top: 15px; }
        .dashboard-switcher a {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(5px);
            color: #fff; padding: 10px 15px; border-radius: 8px;
            text-decoration: none; font-weight: 600; font-size: 0.9em;
            transition: background 0.3s, transform 0.2s;
        }
        .dashboard-switcher a:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }

        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .card { background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; border: 1px solid rgba(255, 255, 255, 0.18); display: flex; align-items: center; color: #fff; transition: transform 0.3s, box-shadow 0.3s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .card-icon { font-size: 2rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; }
        .card-icon.bg-purple { background: #9370DB; } 
        .card-icon.bg-blue { background: #6495ED; }
        .card-icon.bg-teal { background: #20B2AA; }
        .card-icon.bg-green { background: #3CB371; }
        .card-icon.bg-yellow { background: #FFD700; }
        .card-icon.bg-orange { background: #FF8C00; }
        .card-icon.bg-indigo { background: #4B0082; }

        .card-content h3 { margin: 0; font-size: 1rem; opacity: 0.8; }
        .card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        
        .quick-actions { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; }
        .action-btn { background: #fff; color: #1e2a4c; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .action-btn:hover { background: #1e2a4c; color: #fff; transform: translateY(-3px); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
        .main-panel { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #1e2a4c; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .list-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #f4f4f4; }
        .list-item:last-child { border-bottom: none; }
        .list-item-icon { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; font-size: 1.1rem; }
        .list-item-icon.bg-purple-dark { background: #7B1FA2; } 
        .list-item-icon.bg-blue-dark { background: #303F9F; }
        .list-item-icon.bg-teal-dark { background: #00796B; }
        .list-item-icon.bg-green-dark { background: #2E7D32; }
        .list-item-icon.bg-yellow-dark { background: #FBC02D; }
        .list-item-icon.bg-orange-dark { background: #F57C00; }
        .list-item-content { flex-grow: 1; }
        .list-item-content h4 { margin: 0; color: #333; font-size: 1rem; }
        .list-item-content p { margin: 4px 0 0; color: #666; font-size: 0.9em; }
        .list-item-extra { margin-left: auto; text-align: right; color: #888; font-size: 0.9em; white-space: nowrap; flex-shrink: 0; padding-left: 15px; }
        .text-muted { color: #6c757d !important; }
        .badge { display: inline-block; padding: 0.3em 0.6em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.3rem; color: #fff; }
        .badge-info { background-color: #17a2b8; }
        .badge-primary { background-color: #007bff; }
        .badge-secondary { background-color: #6c757d; }
        .badge-warning { background-color: #ffc107; color: #212529; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome, Principal <?php echo htmlspecialchars($principal_name); ?>!</h1>
        <p>Academic & Welfare Overview – Focus on progress and well-being.</p>
        <div class="dashboard-switcher">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Switch to Operations & Management Dashboard
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-purple"><i class="fas fa-book-open"></i></div><div class="card-content"><h3>Exams Scheduled</h3><p><?php echo $total_exams_scheduled; ?></p></div></div>
        <div class="card"><div class="card-icon bg-blue"><i class="fas fa-award"></i></div><div class="card-content"><h3>Scholarships Assigned</h3><p><?php echo $total_scholarships_assigned; ?></p></div></div>
        <div class="card"><div class="card-icon bg-teal"><i class="fas fa-life-ring"></i></div><div class="card-content"><h3>Open Tickets</h3><p><?php echo $open_helpdesk_tickets; ?></p></div></div>
        <div class="card"><div class="card-icon bg-green"><i class="fas fa-calendar-alt"></i></div><div class="card-content"><h3>Upcoming Events</h3><p><?php echo $upcoming_events_count; ?></p></div></div>
        <div class="card"><div class="card-icon bg-yellow"><i class="fas fa-futbol"></i></div><div class="card-content"><h3>Active Clubs</h3><p><?php echo $total_clubs; ?></p></div></div>
        <div class="card"><div class="card-icon bg-orange"><i class="fas fa-trophy"></i></div><div class="card-content"><h3>Total Competitions</h3><p><?php echo $total_competitions; ?></p></div></div>
        <div class="card"><div class="card-icon bg-indigo"><i class="fas fa-masks-theater"></i></div><div class="card-content"><h3>Cultural Programs</h3><p><?php echo $total_cultural_programs; ?></p></div></div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="review_marks.php" class="action-btn"><i class="fas fa-graduation-cap"></i> Review Exam Marks</a>
        <a href="manage_scholarships.php" class="action-btn"><i class="fas fa-award"></i> Manage Scholarships</a>
        <a href="manage_helpdesk_tickets.php" class="action-btn"><i class="fas fa-life-ring"></i> Manage Helpdesk</a>
        <a href="view_all_online_tests.php" class="action-btn"><i class="fas fa-laptop-code"></i> View Online Tests</a>
        <a href="manage_sports_clubs.php" class="action-btn"><i class="fas fa-futbol"></i> Manage Sports Clubs</a>
        <a href="manage_cultural_programs.php" class="action-btn"><i class="fas fa-masks-theater"></i> Manage Programs</a>
        <a href="manage_competitions.php" class="action-btn"><i class="fas fa-trophy"></i> Manage Competitions</a>
        <a href="view_study_materials.php" class="action-btn"><i class="fas fa-book"></i> View Study Materials</a>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-graduation-cap"></i>Upcoming Exams</h3>
            <?php if (empty($upcoming_exams)): ?>
                <p class="text-muted text-center py-4">No upcoming exams found for the school.</p>
            <?php else: foreach ($upcoming_exams as $exam): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-purple-dark"><i class="fas fa-pen-alt"></i></div>
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
            <h3 class="panel-header"><i class="fas fa-life-ring"></i>Recent Open Helpdesk Tickets</h3>
            <?php if (empty($recent_open_tickets)): ?>
                <p class="text-muted text-center py-4">No open helpdesk tickets to review.</p>
            <?php else: foreach ($recent_open_tickets as $ticket): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-teal-dark"><i class="fas fa-ticket-alt"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($ticket['title']); ?></h4>
                        <p>Student: <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?> (Class <?php echo htmlspecialchars($ticket['class_name'] . ' - ' . $ticket['section_name']); ?>)</p>
                    </div>
                    <div class="list-item-extra">
                        Opened: <?php echo date("M j, Y", strtotime($ticket['created_at'])); ?>
                        <span class="badge badge-info">Open</span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-calendar-day"></i>Upcoming School Activities</h3>
            <?php if (empty($recent_activities)): ?>
                <p class="text-muted text-center py-4">No upcoming cultural programs or competitions.</p>
            <?php else: foreach ($recent_activities as $activity): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-yellow-dark"><i class="fas fa-<?php echo ($activity['type'] == 'Competition' ? 'trophy' : 'masks-theater'); ?>"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($activity['title']); ?></h4>
                        <p>Type: <?php echo htmlspecialchars($activity['type']); ?> - <?php echo htmlspecialchars(substr($activity['description'], 0, 50)) . '...'; ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j, Y", strtotime($activity['date'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-book"></i>Recent Study Materials</h3>
            <?php if (empty($recent_study_materials)): ?>
                <p class="text-muted text-center py-4">No recent study materials uploaded.</p>
            <?php else: foreach ($recent_study_materials as $material): ?>
                <div class="list-item">
                     <div class="list-item-icon bg-orange-dark"><i class="fas fa-upload"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                        <p>Class: <?php echo htmlspecialchars($material['class_name'] . ' - ' . $material['section_name']); ?> (<?php echo htmlspecialchars($material['subject_name']); ?>)</p>
                    </div>
                     <div class="list-item-extra"><?php echo date("M j, Y", strtotime($material['uploaded_at'])); ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
</body>
</html>

<?php
require_once './principal_footer.php';
?>