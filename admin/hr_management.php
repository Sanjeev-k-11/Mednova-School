<?php
session_start();
require_once "../database/config.php";
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["id"];
$admin_name = $_SESSION["full_name"];

// --- DATA FETCHING ---

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

// 1. Get Stats for Cards
$total_teachers = get_stat($link, "SELECT COUNT(id) FROM teachers WHERE is_blocked = 0");
$total_principles = get_stat($link, "SELECT COUNT(id) FROM principles WHERE is_blocked = 0");
$total_other_admins = get_stat($link, "SELECT COUNT(id) FROM admins WHERE id != ?", [$admin_id], "i"); // Other admins, excluding current
$pending_staff_salaries = get_stat($link, "SELECT COUNT(id) FROM staff_salary WHERE status = 'Generated'");
$total_departments = get_stat($link, "SELECT COUNT(id) FROM departments");
$staff_with_van_service = get_stat($link, "SELECT COUNT(id) FROM teachers WHERE van_service_taken = 1"); // Assuming staff_table for van service
$staff_with_van_service += get_stat($link, "SELECT COUNT(id) FROM principles WHERE van_service_taken = 1");


// 2. Get Recent Staff Hires
$recent_hires = [];
$sql_teachers_hires = "SELECT id, full_name, 'Teacher' as role, date_of_joining as join_date FROM teachers ORDER BY date_of_joining DESC LIMIT 3";
$sql_principles_hires = "SELECT id, full_name, 'Principle' as role, date_of_joining as join_date FROM principles ORDER BY date_of_joining DESC LIMIT 3";
$sql_admins_hires = "SELECT id, full_name, 'Admin' as role, join_date FROM admins WHERE id != ? ORDER BY join_date DESC LIMIT 3"; // Other admins

if ($result = mysqli_query($link, $sql_teachers_hires)) { while ($row = mysqli_fetch_assoc($result)) { $recent_hires[] = $row; } }
if ($result = mysqli_query($link, $sql_principles_hires)) { while ($row = mysqli_fetch_assoc($result)) { $recent_hires[] = $row; } }
if ($stmt = mysqli_prepare($link, $sql_admins_hires)) {
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) { $recent_hires[] = $row; }
    mysqli_stmt_close($stmt);
}
usort($recent_hires, function($a, $b) { return strtotime($b['join_date']) - strtotime($a['join_date']); });
$recent_hires = array_slice($recent_hires, 0, 5); // Top 5 recent hires


// 3. Upcoming Salary Payments (Next 5 pending)
$upcoming_salaries = [];
$sql_salaries = "SELECT ss.salary_month, ss.salary_year, ss.net_payable, ss.staff_role, 
                        COALESCE(t.full_name, p.full_name, adm.full_name) AS staff_name
                 FROM staff_salary ss
                 LEFT JOIN teachers t ON ss.staff_id = t.id AND ss.staff_role = 'Teacher'
                 LEFT JOIN principles p ON ss.staff_id = p.id AND ss.staff_role = 'Principle'
                 LEFT JOIN admins adm ON ss.staff_id = adm.id AND ss.staff_role = 'Admin'
                 WHERE ss.status = 'Generated' -- Assuming 'Generated' means pending payment
                 ORDER BY ss.salary_year ASC, FIELD(ss.salary_month, 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December') ASC
                 LIMIT 5";
if ($result = mysqli_query($link, $sql_salaries)) {
    $upcoming_salaries = mysqli_fetch_all($result, MYSQLI_ASSOC);
}


// 4. Department Overview (Top 5 by teacher count)
$department_overview = [];
$sql_departments = "SELECT d.department_name AS department_name, t.full_name AS hod_name,
       (SELECT COUNT(id) FROM teachers WHERE department_id = d.id) AS teacher_count
FROM departments d
LEFT JOIN teachers t ON d.hod_teacher_id = t.id
ORDER BY teacher_count DESC LIMIT 5";
if ($result = mysqli_query($link, $sql_departments)) {
    $department_overview = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
    <title>Admin Dashboard - HR & Staff Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(-45deg, #FF6347, #FFD700, #4682B4, #3CB371); /* Admin HR: Tomato, Gold, SteelBlue, MediumSeaGreen */
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
        .card-icon.bg-red { background: #FF6347; } 
        .card-icon.bg-gold { background: #FFD700; }
        .card-icon.bg-steelblue { background: #4682B4; }
        .card-icon.bg-mediumseagreen { background: #3CB371; }
        .card-icon.bg-purple { background: #8A2BE2; }

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
        .list-item-icon.bg-red-dark { background: #CD5C5C; } 
        .list-item-icon.bg-gold-dark { background: #DAA520; }
        .list-item-icon.bg-steelblue-dark { background: #36678D; }
        .list-item-icon.bg-mediumseagreen-dark { background: #2E8B57; }

        .list-item-content h4 { margin: 0; color: #333; font-size: 1rem; }
        .list-item-content p { margin: 4px 0 0; color: #666; font-size: 0.9em; }
        .list-item-extra { margin-left: auto; text-align: right; color: #888; font-size: 0.9em; white-space: nowrap; flex-shrink: 0; padding-left: 15px; }
        .text-muted { color: #6c757d !important; }
        .badge { display: inline-block; padding: 0.3em 0.6em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.3rem; color: #fff; }
        .badge-success { background-color: #28a745; }
        .badge-danger { background-color: #dc3545; }
        .badge-warning { background-color: #ffc107; color: #212529; }
        .badge-info { background-color: #17a2b8; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome, Admin <?php echo htmlspecialchars($admin_name); ?>!</h1>
        <p>HR & Staff Management – Oversee all personnel aspects of the school.</p>
        <div class="dashboard-switcher">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Switch to Main Overview Dashboard
            </a>
            <a href="dashboard_financial.php">
                <i class="fas fa-arrow-right"></i> Switch to Financial & Resource Management
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-red"><i class="fas fa-chalkboard-teacher"></i></div><div class="card-content"><h3>Total Teachers</h3><p><?php echo $total_teachers; ?></p></div></div>
        <div class="card"><div class="card-icon bg-gold"><i class="fas fa-user-tie"></i></div><div class="card-content"><h3>Total Principals</h3><p><?php echo $total_principles; ?></p></div></div>
        <div class="card"><div class="card-icon bg-steelblue"><i class="fas fa-user-shield"></i></div><div class="card-content"><h3>Other Admins</h3><p><?php echo $total_other_admins; ?></p></div></div>
        <div class="card"><div class="card-icon bg-mediumseagreen"><i class="fas fa-building"></i></div><div class="card-content"><h3>Departments</h3><p><?php echo $total_departments; ?></p></div></div>
        <div class="card"><div class="card-icon bg-red"><i class="fas fa-hourglass-half"></i></div><div class="card-content"><h3>Pending Salaries</h3><p><?php echo $pending_staff_salaries; ?></p></div></div>
        <div class="card"><div class="card-icon bg-purple"><i class="fas fa-bus-alt"></i></div><div class="card-content"><h3>Staff with Van</h3><p><?php echo $staff_with_van_service; ?></p></div></div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="view_all_staff.php" class="action-btn"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a>
        <a href="view_all_staff.php" class="action-btn"><i class="fas fa-user-tie"></i> Manage Principals</a>
         <a href="department_management.php" class="action-btn"><i class="fas fa-building"></i> Manage Departments</a>
        <a href="view_staff_salaries.php" class="action-btn"><i class="fas fa-hand-holding-usd"></i> Manage Staff Salary</a>
     </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-user-plus"></i>Recent Staff Hires</h3>
            <?php if (empty($recent_hires)): ?>
                <p class="text-muted text-center py-4">No recent staff hires.</p>
            <?php else: foreach ($recent_hires as $hire): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-mediumseagreen-dark"><i class="fas fa-<?php echo ($hire['role'] == 'Teacher') ? 'chalkboard-teacher' : (($hire['role'] == 'Principle') ? 'user-tie' : 'user-shield'); ?>"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($hire['full_name']); ?></h4>
                        <p><?php echo htmlspecialchars($hire['role']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        Joined: <?php echo date("M j, Y", strtotime($hire['join_date'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-money-check-alt"></i>Upcoming Salary Payments</h3>
            <?php if (empty($upcoming_salaries)): ?>
                <p class="text-muted text-center py-4">No pending salary payments found.</p>
            <?php else: foreach ($upcoming_salaries as $salary): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-gold-dark"><i class="fas fa-clock"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($salary['staff_name']); ?> (<?php echo htmlspecialchars($salary['staff_role']); ?>)</h4>
                        <p>For <?php echo htmlspecialchars($salary['salary_month'] . ' ' . $salary['salary_year']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        ₹<?php echo number_format($salary['net_payable'], 2); ?>
                        <span class="badge badge-warning">Pending</span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-building"></i>Department Overview</h3>
            <?php if (empty($department_overview)): ?>
                <p class="text-muted text-center py-4">No departments found.</p>
            <?php else: foreach ($department_overview as $dept): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-steelblue-dark"><i class="fas fa-sitemap"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($dept['department_name']); ?></h4>
                        <p>HOD: <?php echo htmlspecialchars($dept['hod_name'] ?: 'N/A'); ?></p>
                    </div>
                    <div class="list-item-extra">
                        Teachers: <?php echo htmlspecialchars($dept['teacher_count']); ?>
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