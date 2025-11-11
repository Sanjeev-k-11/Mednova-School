<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["id"];
$admin_name = $_SESSION["full_name"];

// --- DATA FETCHING ---

// Helper function to get stats (reusing from user's provided code, renamed for clarity)
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

// 1. Get Stats for Cards
$total_students = get_stat($link, "SELECT COUNT(id) FROM students");
$total_teachers = get_stat($link, "SELECT COUNT(id) FROM teachers");
$total_principles = get_stat($link, "SELECT COUNT(id) FROM principles");
$total_admins = get_stat($link, "SELECT COUNT(id) FROM admins"); // Counting self might be okay here
$total_staff = $total_teachers + $total_principles + $total_admins; // Summing all staff roles

$total_classes = get_stat($link, "SELECT COUNT(id) FROM classes");
$active_students = get_stat($link, "SELECT COUNT(id) FROM students WHERE status = 'Active'");
$blocked_students = get_stat($link, "SELECT COUNT(id) FROM students WHERE status = 'Blocked'");

$pending_fees_amount = get_stat($link, "SELECT SUM(amount_due - amount_paid) AS total_remaining FROM student_fees WHERE status IN ('Unpaid', 'Partially Paid')");

// 2. Recent Announcements
$announcements = [];
$sql_announcements = "SELECT title, content, posted_by, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5";
if ($result = mysqli_query($link, $sql_announcements)) {
    $announcements = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// 3. Upcoming School Events
$events = [];
$sql_events = "SELECT title, description, start_date, end_date, event_type, color FROM events WHERE start_date >= CURDATE() ORDER BY start_date ASC LIMIT 5";
if ($result = mysqli_query($link, $sql_events)) {
    $events = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

mysqli_close($link);

// --- PAGE INCLUDES ---
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Overview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- ENHANCED STYLES -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(-45deg, #3498db, #8e44ad, #2ecc71, #f39c12); /* Admin Overview: Blue, Purple, Green, Orange */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .welcome-header { margin-bottom: 20px; color: #fff; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .welcome-header h1 { font-weight: 600; font-size: 2.2em; }
        .welcome-header p { font-size: 1.1em; opacity: 0.9; }
        .dashboard-switcher { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; }
        .dashboard-switcher a {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(5px);
            color: #fff; padding: 10px 15px; border-radius: 8px;
            text-decoration: none; font-weight: 600; font-size: 0.9em;
            transition: background 0.3s, transform 0.2s;
        }
        .dashboard-switcher a:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }

        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .card { 
            background: rgba(255, 255, 255, 0.2); 
            backdrop-filter: blur(10px);
            border-radius: 15px; 
            padding: 25px; 
            border: 1px solid rgba(255, 255, 255, 0.18);
            display: flex; 
            align-items: center; 
            color: #fff;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .card-icon { font-size: 2rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; }
        .card-icon.bg-blue { background: #3498db; }
        .card-icon.bg-purple { background: #8e44ad; }
        .card-icon.bg-orange { background: #f39c12; }
        .card-icon.bg-red { background: #e74c3c; }
        .card-icon.bg-green { background: #2ecc71; }

        .card-content h3 { margin: 0; font-size: 1rem; opacity: 0.8; }
        .card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .action-btn { background: #fff; color: #1e2a4c; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .action-btn:hover { background: #1e2a4c; color: #fff; transform: translateY(-3px); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
        .main-panel { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #1e2a4c; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .list-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #f4f4f4; }
        .list-item:last-child { border-bottom: none; }
        .list-item-icon { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; font-size: 1.1rem; }
        .list-item-icon.bg-blue-dark { background: #2980b9; } 
        .list-item-icon.bg-purple-dark { background: #6c3483; }
        .list-item-icon.bg-orange-dark { background: #d35400; }
        .list-item-icon.bg-green-dark { background: #27ae60; }

        .list-item-content h4 { margin: 0; color: #333; font-size: 1rem; }
        .list-item-content p { margin: 4px 0 0; color: #666; font-size: 0.9em; }
        .list-item-extra { margin-left: auto; text-align: right; color: #888; font-size: 0.9em; white-space: nowrap; flex-shrink: 0; padding-left: 15px; }
        .text-muted { color: #6c757d !important; }
        .badge { display: inline-block; padding: 0.3em 0.6em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.3rem; color: #fff; }
        .badge-info { background-color: #17a2b8; }
        .badge-primary { background-color: #007bff; }
        .badge-secondary { background-color: #6c757d; }
        .badge-warning { background-color: #ffc107; color: #212529; }
        .badge-success { background-color: #28a745; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome, Admin <?php echo htmlspecialchars($admin_name); ?>!</h1>
        <p>Main Hub – A quick overview of your school's current status.</p>
        <div class="dashboard-switcher">
            <a href="hr_management.php">
                <i class="fas fa-arrow-right"></i> Switch to HR & Staff Management
            </a>
            <a href="dashboard_financial.php">
                <i class="fas fa-arrow-right"></i> Switch to Financial & Resource Management
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-blue"><i class="fas fa-user-graduate"></i></div><div class="card-content"><h3>Total Students</h3><p><?php echo $total_students; ?></p></div></div>
        <div class="card"><div class="card-icon bg-purple"><i class="fas fa-users"></i></div><div class="card-content"><h3>Total Staff</h3><p><?php echo $total_staff; ?></p></div></div>
        <div class="card"><div class="card-icon bg-orange"><i class="fas fa-file-invoice-dollar"></i></div><div class="card-content"><h3>Pending Fees</h3><p>₹<?php echo number_format($pending_fees_amount, 2); ?></p></div></div>
        <div class="card"><div class="card-icon bg-red"><i class="fas fa-user-slash"></i></div><div class="card-content"><h3>Blocked Students</h3><p><?php echo $blocked_students; ?></p></div></div>
        <div class="card"><div class="card-icon bg-green"><i class="fas fa-school"></i></div><div class="card-content"><h3>Total Classes</h3><p><?php echo $total_classes; ?></p></div></div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="view_students.php" class="action-btn"><i class="fas fa-user-plus"></i> Manage Students</a>
        <a href="view_all_staff.php" class="action-btn"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a>
        <a href="manage_classes.php" class="action-btn"><i class="fas fa-school"></i> Manage Classes</a>
        <a href="add_bulk_fees.php" class="action-btn"><i class="fas fa-money-bill-wave"></i> Assign Bulk Fees</a>
        <a href="manage_announcements.php" class="action-btn"><i class="fas fa-bullhorn"></i> Manage Announcements</a>
        <a href="manage_events.php" class="action-btn"><i class="fas fa-calendar-plus"></i> Manage Events</a>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header">Recent Announcements</h3>
            <?php if (empty($announcements)): ?>
                <p class="text-muted text-center py-4">No recent announcements found.</p>
            <?php else: foreach ($announcements as $announcement): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-blue-dark"><i class="fas fa-bullhorn"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                        <p><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . (strlen($announcement['content']) > 100 ? '...' : ''); ?></p>
                    </div>
                    <div class="list-item-extra"><?php echo date("M j, Y", strtotime($announcement['created_at'])); ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <div class="main-panel">
            <h3 class="panel-header">Upcoming School Events</h3>
            <?php if (empty($events)): ?>
                <p class="text-muted text-center py-4">No upcoming events scheduled.</p>
            <?php else: foreach ($events as $event): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-purple-dark"><i class="fas fa-calendar-day"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                        <p>Type: <?php echo htmlspecialchars($event['event_type']); ?> - <?php echo htmlspecialchars(substr($event['description'] ?: 'N/A', 0, 50)) . '...'; ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j, Y", strtotime($event['start_date'])); ?>
                        <?php if ($event['end_date'] && $event['start_date'] != $event['end_date']): ?>
                            - <?php echo date("M j, Y", strtotime($event['end_date'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
</body>
</html>

<?php
require_once './admin_footer.php';
?>