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

// --- 1. FETCH WELFARE & ACTIVITIES STATS ---
$clubs_joined = get_stat($link, "SELECT COUNT(id) FROM club_members WHERE student_id = ?", [$student_id], "i");
$programs_registered = get_stat($link, "SELECT COUNT(id) FROM program_participants WHERE student_id = ?", [$student_id], "i");
$books_borrowed = get_stat($link, "SELECT COUNT(id) FROM borrow_records WHERE student_id = ? AND status IN ('Borrowed', 'Overdue')", [$student_id], "i");
$open_tickets = get_stat($link, "SELECT COUNT(id) FROM support_tickets WHERE student_id = ? AND status = 'Open'", [$student_id], "i");
$pending_leave_applications = get_stat($link, "SELECT COUNT(id) FROM leave_applications WHERE student_id = ? AND status = 'Pending'", [$student_id], "i");
$scholarships_awarded = get_stat($link, "SELECT COUNT(id) FROM student_scholarships WHERE student_id = ?", [$student_id], "i");


// --- 2. FETCH MY RECENT ACTIVITIES (Clubs & Programs) (Limit 5) ---
$my_recent_activities = [];
$sql_clubs_recent = "SELECT sc.name, 'Sports Club' as type, sc.id as activity_id, cm.join_date as date FROM sports_clubs sc JOIN club_members cm ON sc.id = cm.club_id WHERE cm.student_id = ? ORDER BY cm.join_date DESC LIMIT 3";
$sql_programs_recent = "SELECT cp.name, 'Cultural Program' as type, cp.id as activity_id, pp.registration_date as date FROM cultural_programs cp JOIN program_participants pp ON cp.id = pp.program_id WHERE pp.student_id = ? ORDER BY pp.registration_date DESC LIMIT 3";

if ($stmt_clubs = mysqli_prepare($link, $sql_clubs_recent)) {
    mysqli_stmt_bind_param($stmt_clubs, "i", $student_id);
    mysqli_stmt_execute($stmt_clubs);
    $result_clubs = mysqli_stmt_get_result($stmt_clubs);
    while ($row = mysqli_fetch_assoc($result_clubs)) { $my_recent_activities[] = $row; }
    mysqli_stmt_close($stmt_clubs);
}
if ($stmt_programs = mysqli_prepare($link, $sql_programs_recent)) {
    mysqli_stmt_bind_param($stmt_programs, "i", $student_id);
    mysqli_stmt_execute($stmt_programs);
    $result_programs = mysqli_stmt_get_result($stmt_programs);
    while ($row = mysqli_fetch_assoc($result_programs)) { $my_recent_activities[] = $row; }
    mysqli_stmt_close($stmt_programs);
}
usort($my_recent_activities, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
$my_recent_activities = array_slice($my_recent_activities, 0, 5);


// --- 3. FETCH LIBRARY OVERVIEW (Currently Borrowed Books) (Limit 5) ---
$borrowed_books_overview = [];
$sql_borrowed_books = "SELECT b.title, b.author, br.due_date, br.status, br.fine_amount
                       FROM borrow_records br
                       JOIN books b ON br.book_id = b.id
                       WHERE br.student_id = ? AND br.status IN ('Borrowed', 'Overdue')
                       ORDER BY br.due_date ASC
                       LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_borrowed_books)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $borrowed_books_overview = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


// --- 4. FETCH MY LEAVE APPLICATIONS (Limit 5) ---
$my_leave_applications = [];
$sql_leaves = "SELECT leave_from, leave_to, reason, status, created_at, reviewed_by FROM leave_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_leaves)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $my_leave_applications = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


// --- 5. FETCH MY HELPDESK TICKETS (Limit 5) ---
$my_helpdesk_tickets = [];
$sql_tickets = "SELECT st.id, st.title, st.status, st.created_at, subj.subject_name
                FROM support_tickets st
                JOIN subjects subj ON st.subject_id = subj.id
                WHERE st.student_id = ?
                ORDER BY st.created_at DESC LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_tickets)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $my_helpdesk_tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
    <title>Student Dashboard - Welfare & Activities</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(-45deg, #E6FFFA, #B2EBF2, #80DEEA, #4DD0E1); /* Welfare & Activities: Light green/blue, aqua */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .welcome-header { margin-bottom: 20px; color: #006064; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
        .welcome-header h1 { font-weight: 600; font-size: 2.2em; }
        .welcome-header p { font-size: 1.1em; opacity: 0.9; }
        .dashboard-switcher { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; }
        .dashboard-switcher a {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(5px);
            color: #006064; padding: 10px 15px; border-radius: 8px;
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
            color: #006064; /* Darker text for cards */
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .card-icon { font-size: 2rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; }
        .card-icon.bg-teal { background: #E0F2F7; color: #006064; } 
        .card-icon.bg-green { background: #D4EDDA; color: #155724; }
        .card-icon.bg-blue { background: #CCE5FF; color: #004085; }
        .card-icon.bg-red { background: #F8D7DA; color: #721C24; }
        .card-icon.bg-yellow { background: #FFF3CD; color: #856404; }
        .card-icon.bg-purple { background: #E6E6FA; color: #483D8B; }

        .card-content h3 { margin: 0; font-size: 1rem; opacity: 0.9; }
        .card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .action-btn { background: #fff; color: #006064; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .action-btn:hover { background: #006064; color: #fff; transform: translateY(-3px); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
        .main-panel { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #006064; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .list-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #f4f4f4; }
        .list-item:last-child { border-bottom: none; }
        .list-item-icon { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; font-size: 1.1rem; }
        .list-item-icon.bg-teal-dark { background: #008080; } 
        .list-item-icon.bg-green-dark { background: #2E7D32; }
        .list-item-icon.bg-blue-dark { background: #1976D2; }
        .list-item-icon.bg-red-dark { background: #B22222; }
        .list-item-icon.bg-yellow-dark { background: #FBC02D; }
        .list-item-icon.bg-purple-dark { background: #6A5ACD; }

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
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>!</h1>
        <p>Your Welfare & Activities Dashboard.</p>
        <div class="dashboard-switcher">
            <a href="student_dashboard.php">
                <i class="fas fa-arrow-left"></i> General Overview
            </a>
            <a href="student_dashboard_academics.php">
                <i class="fas fa-arrow-left"></i> Academic Progress
            </a>
        </div>
    </div>
    
    <!-- Welfare & Activities Summary Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-teal"><i class="fas fa-futbol"></i></div><div class="card-content"><h3>Clubs Joined</h3><p><?php echo $clubs_joined; ?></p></div></div>
        <div class="card"><div class="card-icon bg-green"><i class="fas fa-palette"></i></div><div class="card-content"><h3>Programs Reg.</h3><p><?php echo $programs_registered; ?></p></div></div>
        <div class="card"><div class="card-icon bg-blue"><i class="fas fa-book-reader"></i></div><div class="card-content"><h3>Books Borrowed</h3><p><?php echo $books_borrowed; ?></p></div></div>
        <div class="card"><div class="card-icon bg-red"><i class="fas fa-life-ring"></i></div><div class="card-content"><h3>Open Tickets</h3><p><?php echo $open_tickets; ?></p></div></div>
        <div class="card"><div class="card-icon bg-yellow"><i class="fas fa-calendar-times"></i></div><div class="card-content"><h3>Pending Leaves</h3><p><?php echo $pending_leave_applications; ?></p></div></div>
        <div class="card"><div class="card-icon bg-purple"><i class="fas fa-award"></i></div><div class="card-content"><h3>Scholarships</h3><p><?php echo $scholarships_awarded; ?></p></div></div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="sports_clubs.php" class="action-btn"><i class="fas fa-futbol"></i> Sports Clubs</a>
        <a href="cultural_programs.php" class="action-btn"><i class="fas fa-masks-theater"></i> Cultural Programs</a>
        <a href="library.php" class="action-btn"><i class="fas fa-book-open"></i> My Library</a>
        <a href="student_helpdesk.php" class="action-btn"><i class="fas fa-question-circle"></i> Helpdesk</a>
        <a href="applications.php" class="action-btn"><i class="fas fa-plane-departure"></i> Apply for Leave</a>
        <a href="student_scholarships.php" class="action-btn"><i class="fas fa-graduation-cap"></i> My Scholarships</a>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-star"></i>My Recent Activities</h3>
            <?php if (empty($my_recent_activities)): ?>
                <p class="text-muted text-center py-4">No recent club or program activities.</p>
            <?php else: foreach ($my_recent_activities as $activity): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-teal-dark"><i class="fas <?php echo ($activity['type'] === 'Sports Club') ? 'fa-futbol' : 'fa-palette'; ?>"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($activity['name']); ?></h4>
                        <p><?php echo htmlspecialchars($activity['type']); ?> | Joined: <?php echo date('M j, Y', strtotime($activity['date'])); ?></p>
                    </div>
                    <div class="list-item-extra">
                         <a href="<?php echo ($activity['type'] === 'Sports Club') ? 'student_sports_clubs.php?view_club_id='.$activity['activity_id'] : 'student_cultural_programs.php?view_program_id='.$activity['activity_id']; ?>" class="badge badge-primary">Details</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="sports_clubs.php" class="action-btn mt-4"><i class="fas fa-arrow-right"></i> Explore All Activities</a>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-book-open"></i>My Borrowed Books</h3>
            <?php if (empty($borrowed_books_overview)): ?>
                <p class="text-muted text-center py-4">No books currently borrowed.</p>
            <?php else: foreach ($borrowed_books_overview as $book): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-green-dark"><i class="fas fa-book"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                        <p>Author: <?php echo htmlspecialchars($book['author']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        Due: <?php echo date('M j, Y', strtotime($book['due_date'])); ?>
                        <?php if ($book['status'] == 'Overdue'): ?>
                            <span class="badge badge-danger">Overdue!</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="library.php" class="action-btn mt-4"><i class="fas fa-arrow-right"></i> View All Borrowed Books</a>
        </div>
        
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-calendar-times"></i>My Leave Applications</h3>
            <?php if (empty($my_leave_applications)): ?>
                <p class="text-muted text-center py-4">No recent leave applications.</p>
            <?php else: foreach ($my_leave_applications as $leave): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-blue-dark"><i class="fas fa-plane-departure"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($leave['reason']); ?></h4>
                        <p>From: <?php echo date('M j, Y', strtotime($leave['leave_from'])); ?> To: <?php echo date('M j, Y', strtotime($leave['leave_to'])); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <span class="badge badge-<?php echo ($leave['status'] === 'Approved' ? 'success' : ($leave['status'] === 'Pending' ? 'warning' : 'danger')); ?>"><?php echo htmlspecialchars($leave['status']); ?></span>
                        <br>
                        <span class="text-muted">Reviewed By: <?php echo htmlspecialchars($leave['reviewed_by'] ?: 'N/A'); ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="applications.php" class="action-btn mt-4"><i class="fas fa-arrow-right"></i> Manage My Leaves</a>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-life-ring"></i>My Helpdesk Tickets</h3>
            <?php if (empty($my_helpdesk_tickets)): ?>
                <p class="text-muted text-center py-4">No helpdesk tickets created yet.</p>
            <?php else: foreach ($my_helpdesk_tickets as $ticket): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-red-dark"><i class="fas fa-ticket-alt"></i></div>
                    <div class="list-item-content">
                        <h4>Ticket #<?php echo htmlspecialchars($ticket['id']); ?>: <?php echo htmlspecialchars($ticket['title']); ?></h4>
                        <p>Subject: <?php echo htmlspecialchars($ticket['subject_name']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <span class="badge badge-<?php echo ($ticket['status'] === 'Open' ? 'danger' : 'success'); ?>"><?php echo htmlspecialchars($ticket['status']); ?></span>
                        <br>
                        <span class="text-muted">Created: <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="student_helpdesk.php" class="action-btn mt-4"><i class="fas fa-arrow-right"></i> View My Tickets</a>
        </div>
    </div>
</div>

</body>
</html>
<?php require_once './student_footer.php'; ?>