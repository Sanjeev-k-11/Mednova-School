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
$total_teachers = get_stat($link, "SELECT COUNT(id) FROM teachers WHERE is_blocked = 0");
$total_students = get_stat($link, "SELECT COUNT(id) FROM students WHERE status = 'Active'");
$total_classes = get_stat($link, "SELECT COUNT(id) FROM classes");
$pending_leaves_count = get_stat($link, "SELECT COUNT(id) FROM leave_applications WHERE status = 'Pending'");
$pending_indiscipline_reports_count = get_stat($link, "SELECT COUNT(id) FROM indiscipline_reports WHERE status = 'Pending Review'");
$current_borrowed_books = get_stat($link, "SELECT COUNT(id) FROM borrow_records WHERE status IN ('Borrowed', 'Overdue')");
$overdue_books = get_stat($link, "SELECT COUNT(id) FROM borrow_records WHERE status = 'Overdue'");


// 2. Get Recent Leave Applications (Pending for review by Principal)
$recent_pending_leaves = [];
$sql_recent_leaves = "SELECT la.id, s.first_name, s.last_name, c.class_name, c.section_name, la.leave_from, la.leave_to, la.reason
                      FROM leave_applications la
                      JOIN students s ON la.student_id = s.id
                      JOIN classes c ON la.class_id = c.id
                      WHERE la.status = 'Pending'
                      ORDER BY la.created_at DESC LIMIT 5";
if ($result = mysqli_query($link, $sql_recent_leaves)) {
    $recent_pending_leaves = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// 3. Get Recent Indiscipline Reports (Pending for review by Principal) - focusing on student reports
$recent_pending_indiscipline = [];
$sql_indiscipline = "SELECT ir.id, s.first_name, s.last_name, c.class_name, c.section_name, ir.incident_date, ir.description, ir.severity
                     FROM indiscipline_reports ir
                     JOIN students s ON ir.reported_student_id = s.id
                     LEFT JOIN classes c ON ir.class_id = c.id
                     WHERE ir.target_type = 'Student' AND ir.status = 'Pending Review'
                     ORDER BY ir.created_at DESC LIMIT 5";
if ($result = mysqli_query($link, $sql_indiscipline)) {
    $recent_pending_indiscipline = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// 4. Get Recent Staff Joinees (Teachers and Admins)
$recent_staff_joinees = [];
$sql_teachers_joinees = "SELECT id, full_name, 'Teacher' as role, date_of_joining as join_date FROM teachers ORDER BY date_of_joining DESC LIMIT 3";
$sql_admins_joinees = "SELECT id, full_name, 'Admin' as role, join_date FROM admins ORDER BY join_date DESC LIMIT 3";
$sql_principals_joinees = "SELECT id, full_name, 'Principle' as role, date_of_joining as join_date FROM principles WHERE id != ? ORDER BY date_of_joining DESC LIMIT 3"; // Exclude self

if ($result = mysqli_query($link, $sql_teachers_joinees)) { while ($row = mysqli_fetch_assoc($result)) { $recent_staff_joinees[] = $row; } }
if ($result = mysqli_query($link, $sql_admins_joinees)) { while ($row = mysqli_fetch_assoc($result)) { $recent_staff_joinees[] = $row; } }
if ($stmt = mysqli_prepare($link, $sql_principals_joinees)) {
    mysqli_stmt_bind_param($stmt, "i", $principal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) { $recent_staff_joinees[] = $row; }
    mysqli_stmt_close($stmt);
}

usort($recent_staff_joinees, function($a, $b) { return strtotime($b['join_date']) - strtotime($a['join_date']); });
$recent_staff_joinees = array_slice($recent_staff_joinees, 0, 5); // Get top 5 overall


// 5. Get Recent Announcements
$announcements = [];
$sql_announcements = "SELECT title, content, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3";
if ($result = mysqli_query($link, $sql_announcements)) {
    $announcements = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
    <title>Principal Dashboard - Operations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(-45deg, #4CAF50, #2196F3, #FFC107, #E91E63); /* Operations: Green, Blue, Amber, Red */
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
        .card-icon.bg-green { background: #4CAF50; } 
        .card-icon.bg-blue { background: #2196F3; }
        .card-icon.bg-orange { background: #FF9800; }
        .card-icon.bg-red { background: #F44336; }
        .card-icon.bg-purple { background: #9C27B0; }
        .card-icon.bg-teal { background: #009688; }
        .card-icon.bg-brown { background: #795548; }

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
        .list-item-icon.bg-green-dark { background: #388E3C; } 
        .list-item-icon.bg-blue-dark { background: #1976D2; }
        .list-item-icon.bg-orange-dark { background: #FB8C00; }
        .list-item-icon.bg-red-dark { background: #D32F2F; }
        .list-item-content { flex-grow: 1; }
        .list-item-content h4 { margin: 0; color: #333; font-size: 1rem; }
        .list-item-content p { margin: 4px 0 0; color: #666; font-size: 0.9em; }
        .list-item-extra { margin-left: auto; text-align: right; color: #888; font-size: 0.9em; white-space: nowrap; flex-shrink: 0; padding-left: 15px; }
        .text-muted { color: #6c757d !important; }
        .badge { display: inline-block; padding: 0.3em 0.6em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.3rem; color: #fff; }
        .badge-danger { background-color: #dc3545; }
        .badge-warning { background-color: #ffc107; color: #212529; }
        .badge-info { background-color: #17a2b8; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome, Principal <?php echo htmlspecialchars($principal_name); ?>!</h1>
        <p>Operations & Management Hub – Your daily overview.</p>
        <div class="dashboard-switcher">
            <a href="principal_dashboard_academics_welfare.php">
                <i class="fas fa-arrow-right"></i> Switch to Academic & Welfare Dashboard
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-green"><i class="fas fa-chalkboard-teacher"></i></div><div class="card-content"><h3>Total Teachers</h3><p><?php echo $total_teachers; ?></p></div></div>
        <div class="card"><div class="card-icon bg-blue"><i class="fas fa-users"></i></div><div class="card-content"><h3>Total Students</h3><p><?php echo $total_students; ?></p></div></div>
        <div class="card"><div class="card-icon bg-orange"><i class="fas fa-school"></i></div><div class="card-content"><h3>Total Classes</h3><p><?php echo $total_classes; ?></p></div></div>
        <div class="card"><div class="card-icon bg-red"><i class="fas fa-file-alt"></i></div><div class="card-content"><h3>Pending Leaves</h3><p><?php echo $pending_leaves_count; ?></p></div></div>
        <div class="card"><div class="card-icon bg-purple"><i class="fas fa-exclamation-triangle"></i></div><div class="card-content"><h3>Pending Indiscipline</h3><p><?php echo $pending_indiscipline_reports_count; ?></p></div></div>
        <div class="card"><div class="card-icon bg-teal"><i class="fas fa-book"></i></div><div class="card-content"><h3>Borrowed Books</h3><p><?php echo $current_borrowed_books; ?></p></div></div>
        <div class="card"><div class="card-icon bg-brown"><i class="fas fa-hourglass-end"></i></div><div class="card-content"><h3>Overdue Books</h3><p><?php echo $overdue_books; ?></p></div></div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="manage_teachers.php" class="action-btn"><i class="fas fa-user-tie"></i> Manage Teachers</a>
        <a href="manage_students.php" class="action-btn"><i class="fas fa-user-graduate"></i> Manage Students</a>
        <a href="manage_classes.php" class="action-btn"><i class="fas fa-school"></i> Manage Classes</a>
        <a href="approve_leaves.php" class="action-btn"><i class="fas fa-clipboard-list"></i> Approve Leaves</a>
        <a href="review_indiscipline.php" class="action-btn"><i class="fas fa-gavel"></i> Review Indiscipline</a>
        <a href="manage_announcements.php" class="action-btn"><i class="fas fa-bullhorn"></i> Manage Announcements</a>
        <a href="manage_events.php" class="action-btn"><i class="fas fa-calendar-plus"></i> Manage Events</a>
     </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-file-signature"></i>Recent Pending Leave Requests</h3>
            <?php if (empty($recent_pending_leaves)): ?>
                <p class="text-muted text-center py-4">No pending student leave requests to review.</p>
            <?php else: foreach ($recent_pending_leaves as $leave): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-red-dark"><i class="fas fa-user-injured"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?> (Class <?php echo htmlspecialchars($leave['class_name'] . ' - ' . $leave['section_name']); ?>)</h4>
                        <p class="text-truncate"><?php echo htmlspecialchars($leave['reason']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j", strtotime($leave['leave_from'])) . ' - ' . date("M j", strtotime($leave['leave_to'])); ?>
                        <span class="badge badge-warning">Pending</span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-exclamation-triangle"></i>Recent Pending Indiscipline Reports</h3>
            <?php if (empty($recent_pending_indiscipline)): ?>
                <p class="text-muted text-center py-4">No pending indiscipline reports to review.</p>
            <?php else: foreach ($recent_pending_indiscipline as $report): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-orange-dark"><i class="fas fa-gavel"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?> (Class <?php echo htmlspecialchars($report['class_name'] . ' - ' . $report['section_name']); ?>)</h4>
                        <p class="text-truncate">Severity: <?php echo htmlspecialchars($report['severity']); ?> - <?php echo htmlspecialchars($report['description']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j, Y", strtotime($report['incident_date'])); ?>
                        <span class="badge badge-warning">Pending Review</span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-user-plus"></i>Recent Staff Joinees</h3>
            <?php if (empty($recent_staff_joinees)): ?>
                <p class="text-muted text-center py-4">No recent staff joinees.</p>
            <?php else: foreach ($recent_staff_joinees as $joinee): ?>
                <div class="list-item">
                    <div class="list-item-icon <?php echo ($joinee['role'] == 'Teacher') ? 'bg-green-dark' : 'bg-blue-dark'; ?>"><i class="fas fa-<?php echo ($joinee['role'] == 'Teacher') ? 'chalkboard-teacher' : (($joinee['role'] == 'Principle') ? 'user-tie' : 'user-shield'); ?>"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($joinee['full_name']); ?></h4>
                        <p><?php echo htmlspecialchars($joinee['role']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        Joined: <?php echo date("M j, Y", strtotime($joinee['join_date'])); ?>
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
                     <div class="list-item-icon bg-info"><i class="fas fa-info"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                        <p><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . (strlen($announcement['content']) > 100 ? '...' : ''); ?></p>
                    </div>
                     <div class="list-item-extra"><?php echo date("M j, Y", strtotime($announcement['created_at'])); ?></div>
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