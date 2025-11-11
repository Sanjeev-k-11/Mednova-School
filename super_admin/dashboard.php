<?php
session_start();
require_once "../database/config.php";

// Auth Check... (Your existing PHP is correct)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}
function get_count($link, $table, $condition = "") {
    $sql = "SELECT COUNT(*) FROM {$table} {$condition}";
    $result = mysqli_query($link, $sql);
    return $result ? mysqli_fetch_row($result)[0] : 0;
}
$total_students = get_count($link, 'students');
$total_teachers = get_count($link, 'teachers');
// REMOVED: $total_staff = get_count($link, 'staff'); // This table doesn't exist
$total_admins = get_count($link, 'admins'); // Assuming 'admins' table is for general admins
$total_principles = get_count($link, 'principles');
$total_classes = get_count($link, 'classes');
$active_students = get_count($link, 'students', "WHERE status = 'Active'");
$blocked_students = get_count($link, 'students', "WHERE status = 'Blocked'");

$pending_fees_amount = 0;
$sql_pending_fees = "SELECT SUM(amount_due - amount_paid) AS total_remaining FROM student_fees WHERE status IN ('Unpaid', 'Partially Paid')";
if ($result = mysqli_query($link, $sql_pending_fees)) $pending_fees_amount = mysqli_fetch_assoc($result)['total_remaining'] ?? 0;

$announcements = [];
$sql_announcements = "SELECT title, content, posted_by, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5";
if ($result = mysqli_query($link, $sql_announcements)) while ($row = mysqli_fetch_assoc($result)) $announcements[] = $row;

$events = [];
$sql_events = "SELECT title, description, start_date, color FROM events WHERE start_date >= CURDATE() ORDER BY start_date ASC LIMIT 5";
if ($result = mysqli_query($link, $sql_events)) while ($row = mysqli_fetch_assoc($result)) $events[] = $row;

mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <!-- Using Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- ENHANCED STYLES -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
             
            background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); 
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
        }
        .dashboard-container { max-width: 1600px; margin: auto; margin-top: 100px; margin-bottom: 100px; }
        .welcome-header { margin-bottom: 30px; color: #fff; }
        .welcome-header h1 { font-weight: 600; font-size: 2.2em; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .welcome-header p { font-size: 1.1em; opacity: 0.9; }
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
        .card-icon.bg-blue { background: #3B82F6; }
        .card-icon.bg-green { background: #10B981; }
        .card-icon.bg-red { background: #EF4444; }
        .card-icon.bg-yellow { background: #F59E0B; }
        .card-icon.bg-purple { background: #8B5CF6; }
        .card-content h3 { margin: 0; font-size: 1rem; opacity: 0.8; }
        .card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .main-panel { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #1e2a4c; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; }
        .list-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #f4f4f4; }
        .list-item:last-child { border-bottom: none; }
        .list-item-icon { width: 36px; height: 36px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; }
        .list-item-content h4 { margin: 0; color: #333; }
        .list-item-content p { margin: 4px 0 0; color: #666; font-size: 0.9em; }
        .list-item-extra { margin-left: auto; text-align: right; color: #888; font-size: 0.9em; }
        .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .action-button { background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; text-decoration: none; color: #1e2a4c; text-align: center; font-weight: 600; transition: all 0.2s; }
        .action-button:hover { background-color: #6a82fb; color: #fff; transform: translateY(-3px); box-shadow: 0 4px 10px rgba(106, 130, 251, 0.4); }
        .action-button i { font-size: 1.5rem; margin-bottom: 10px; display: block; }
        @media (max-width: 992px) { .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?>!</h1>
        <p>Here's a snapshot of your school's current status.</p>
    </div>

    <!-- Stat Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-blue"><i class="fas fa-user-graduate"></i></div><div class="card-content"><h3>Total Students</h3><p><?php echo $total_students; ?></p></div></div>
        <div class="card"><div class="card-icon bg-green"><i class="fas fa-chalkboard-teacher"></i></div><div class="card-content"><h3>Total Staff</h3><p><?php echo $total_teachers + $total_principles + $total_admins; ?></p></div></div>
        <div class="card"><div class="card-icon bg-yellow"><i class="fas fa-file-invoice-dollar"></i></div><div class="card-content"><h3>Pending Fees</h3><p>₹<?php echo number_format($pending_fees_amount, 0); ?></p></div></div>
        <div class="card"><div class="card-icon bg-red"><i class="fas fa-user-slash"></i></div><div class="card-content"><h3>Blocked Students</h3><p><?php echo $blocked_students; ?></p></div></div>
        <div class="card"><div class="card-icon bg-purple"><i class="fas fa-school"></i></div><div class="card-content"><h3>Total Classes</h3><p><?php echo $total_classes; ?></p></div></div>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header">Recent Announcements</h3>
            <div class="list">
                <?php if (empty($announcements)): ?>
                    <p>No recent announcements found.</p>
                <?php else: foreach ($announcements as $announcement): ?>
                    <div class="list-item">
                        <div class="list-item-icon bg-blue"><i class="fas fa-bullhorn"></i></div>
                        <div class="list-item-content">
                            <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                            <p><?php echo htmlspecialchars($announcement['content']); ?></p>
                        </div>
                        <div class="list-item-extra"><?php echo date("M j, Y", strtotime($announcement['created_at'])); ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="main-panel">
            <h3 class="panel-header">Quick Actions</h3>
            <div class="quick-actions">
                <a href="create_student.php" class="action-button"><i class="fas fa-user-plus"></i>Add Student</a>
                <a href="add_bulk_fees.php" class="action-button"><i class="fas fa-users"></i>Assign Bulk Fees</a>
                <a href="manage_fees.php" class="action-button"><i class="fas fa-cogs"></i>Fee Structure</a>
                <a href="manage_announcements.php" class="action-button"><i class="fas fa-edit"></i>Post Announcement</a>
                <a href="manage_events.php" class="action-button"><i class="fas fa-calendar-alt"></i>Create Event</a>
                <!-- Add a link for creating teachers/principals explicitly if 'Add Staff' was removed -->
                <a href="create_staff.php" class="action-button"><i class="fas fa-user-tie"></i>Add Teacher/Principal</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
require_once './admin_footer.php';
?>