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
    error_log("Failed to prepare statement for stat: " . mysqli_error($link));
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

// 2. Get Communication & Welfare Stats for cards

// Unread Internal Messages (Teacher-to-Teacher)
$unread_internal_messages = get_teacher_stat($link, 
    "SELECT COUNT(DISTINCT m.id)
     FROM messages m
     JOIN conversation_members cm ON m.conversation_id = cm.conversation_id
     WHERE cm.teacher_id = ? -- User is a member of this conversation
       AND m.sender_id != ? -- Message not sent by user
       AND m.id NOT IN (SELECT message_id FROM message_read_status WHERE reader_id = ? AND conversation_id = m.conversation_id)",
    [$teacher_id, $teacher_id, $teacher_id], "iii"
);

// Unread Student Messages (Teacher-to-Student)
$unread_student_messages = get_teacher_stat($link,
    "SELECT COUNT(DISTINCT stm.id)
     FROM st_messages stm
     JOIN st_conversations stc ON stm.conversation_id = stc.id
     WHERE stc.teacher_id = ? -- User is the teacher in this conversation
       AND stm.sender_role = 'Student' -- Message sent by a student
       AND stm.id NOT IN (SELECT message_id FROM st_message_read_status WHERE reader_id = ? AND conversation_id = stm.conversation_id)",
    [$teacher_id, $teacher_id], "ii"
);

// Pending Leave Approvals (If Class Teacher)
$pending_leaves_count = 0;
if ($class_teacher_class_id) {
    $pending_leaves_count = get_teacher_stat($link, "SELECT COUNT(id) FROM leave_applications WHERE class_teacher_id = ? AND status = 'Pending'", [$teacher_id], "i");
}

// Pending Indiscipline Reports (relevant to this teacher)
$pending_indiscipline_reports_count = 0;
// Reports made by this teacher, or reports concerning students in classes they teach
$sql_pending_ir_count = "SELECT COUNT(DISTINCT ir.id) FROM indiscipline_reports ir
                         WHERE ir.status = 'Pending Review'
                         AND (ir.reported_by_teacher_id = ? 
                              OR ir.reported_student_id IN (SELECT s.id FROM students s JOIN class_subject_teacher cst ON s.class_id = cst.class_id WHERE cst.teacher_id = ?))";
$pending_indiscipline_reports_count = get_teacher_stat($link, $sql_pending_ir_count, [$teacher_id, $teacher_id], "ii");


// Open Support Tickets assigned to this teacher
$open_support_tickets_count = get_teacher_stat($link, "SELECT COUNT(id) FROM support_tickets WHERE teacher_id = ? AND status = 'Open'", [$teacher_id], "i");


// 3. Fetch Pending Leave Applications Details (Only if Class Teacher)
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

// 4. Fetch New Open Support Tickets assigned to this teacher (Limit 5)
$open_support_tickets = [];
$sql_open_tickets = "SELECT st.id, st.title, s.first_name, s.middle_name, s.last_name, sub.subject_name, st.created_at
                     FROM support_tickets st
                     JOIN students s ON st.student_id = s.id
                     JOIN subjects sub ON st.subject_id = sub.id
                     WHERE st.teacher_id = ? AND st.status = 'Open'
                     ORDER BY st.created_at DESC LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_open_tickets)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $open_support_tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// 5. Fetch Recent Indiscipline Reports (relevant to this teacher, Limit 5)
$recent_indiscipline_reports = [];
$sql_recent_ir = "SELECT ir.id, ir.incident_date, ir.description, ir.target_type,
                         s.first_name AS student_first_name, s.last_name AS student_last_name,
                         t.full_name AS reported_teacher_name,
                         reporter.full_name AS reporter_name
                  FROM indiscipline_reports ir
                  LEFT JOIN students s ON ir.reported_student_id = s.id
                  LEFT JOIN teachers t ON ir.reported_teacher_id = t.id
                  LEFT JOIN teachers reporter ON ir.reported_by_teacher_id = reporter.id
                  WHERE ir.status = 'Pending Review'
                    AND (ir.reported_by_teacher_id = ? 
                         OR ir.reported_student_id IN (SELECT s_sub.id FROM students s_sub JOIN class_subject_teacher cst ON s_sub.class_id = cst.class_id WHERE cst.teacher_id = ?)
                         )
                  ORDER BY ir.created_at DESC LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_recent_ir)) {
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recent_indiscipline_reports = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


// 6. Fetch Recent Forum Activity (top 5 posts with latest activity in classes taught by this teacher)
$recent_forum_activity = [];
$sql_forum_activity = "SELECT fp.id, fp.title, c.class_name, c.section_name, fp.last_reply_at, fp.creator_role
                       FROM forum_posts fp
                       JOIN classes c ON fp.class_id = c.id
                       WHERE fp.class_id IN (SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_id = ?)
                       ORDER BY fp.last_reply_at DESC LIMIT 5";
if ($stmt = mysqli_prepare($link, $sql_forum_activity)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recent_forum_activity = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
    <title>Teacher Dashboard: Communication & Welfare</title>
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
        .card-icon.bg-purple-dark { background: #9c27b0; color: #fff; } /* New accent color */
        .card-icon.bg-orange-dark { background: #ff9800; color: #fff; } /* New accent color */


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
        .list-item-icon.bg-purple-accent { background: #9c27b0; }
        .list-item-icon.bg-orange-accent { background: #ff9800; }


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
                Manage your communications and student welfare here.
            <?php endif; ?>
        </p>
        <div class="dashboard-switcher">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> General Overview
            </a>
            <a href="dashboard_academic.php">
                <i class="fas fa-book"></i> Academic Management
            </a>
            <a href="dashboard_communication.php" class="active">
                <i class="fas fa-comments"></i> Communication & Welfare
            </a>
        </div>
    </div>

    <!-- Communication & Welfare Stat Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-blue-dark"><i class="fas fa-envelope-open-text"></i></div><div class="card-content"><h3>Unread Internal Msgs</h3><p><?php echo $unread_internal_messages; ?></p></div></div>
        <div class="card"><div class="card-icon bg-green-dark"><i class="fas fa-comment-dots"></i></div><div class="card-content"><h3>Unread Student Msgs</h3><p><?php echo $unread_student_messages; ?></p></div></div>
        <?php if ($class_teacher_class_id): ?>
            <div class="card"><div class="card-icon bg-yellow-dark"><i class="fas fa-file-signature"></i></div><div class="card-content"><h3>Pending Leaves</h3><p><?php echo $pending_leaves_count; ?></p></div></div>
        <?php endif; ?>
        <div class="card"><div class="card-icon bg-red-dark"><i class="fas fa-gavel"></i></div><div class="card-content"><h3>Pending IR Reports</h3><p><?php echo $pending_indiscipline_reports_count; ?></p></div></div>
        <div class="card"><div class="card-icon bg-purple-dark"><i class="fas fa-headset"></i></div><div class="card-content"><h3>Open Support Tickets</h3><p><?php echo $open_support_tickets_count; ?></p></div></div>
    </div>
    
    <!-- Communication & Welfare Quick Actions -->
    <div class="quick-actions">
        <a href="chat.php" class="action-btn"><i class="fas fa-users"></i> Internal Messaging</a>
        <a href="teacher_messages.php" class="action-btn"><i class="fas fa-user-friends"></i> Student Messaging</a>
        <?php if ($class_teacher_class_id): ?>
            <a href="teacher_leave_management.php?status=Pending" class="action-btn"><i class="fas fa-file-alt"></i> Review Leave Apps</a>
        <?php endif; ?>
        <a href="indiscipline.php" class="action-btn"><i class="fas fa-bell"></i> Manage IR Reports</a>
        <a href="teacher_helpdesk.php" class="action-btn"><i class="fas fa-life-ring"></i> View Support Tickets</a>
        <a href="student_forum.php" class="action-btn"><i class="fas fa-comments"></i> Class Forum</a>
    </div>

    <!-- Main Dashboard Grid for Communication & Welfare Items -->
    <div class="dashboard-grid">
        <?php if ($class_teacher_class_id): ?>
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-file-signature"></i>Recent Pending Leave Requests</h3>
            <?php if (empty($pending_leaves)): ?>
                <p class="text-muted text-center py-4">No pending leave requests to review.</p>
            <?php else: foreach ($pending_leaves as $leave): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-red-accent"><i class="fas fa-user-injured"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars(trim($leave['first_name'] . ' ' . $leave['middle_name'] . ' ' . $leave['last_name'])); ?></h4>
                        <p class="text-truncate">Reason: <?php echo htmlspecialchars($leave['reason']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j", strtotime($leave['leave_from'])) . ' - ' . date("M j", strtotime($leave['leave_to'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-headset"></i>New Open Support Tickets</h3>
            <?php if (empty($open_support_tickets)): ?>
                <p class="text-muted text-center py-4">No new open support tickets assigned to you.</p>
            <?php else: foreach ($open_support_tickets as $ticket): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-blue-accent"><i class="fas fa-ticket-alt"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($ticket['title']); ?></h4>
                        <p>Student: <?php echo htmlspecialchars(trim($ticket['first_name'] . ' ' . $ticket['last_name'])); ?> | Subject: <?php echo htmlspecialchars($ticket['subject_name']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j, Y", strtotime($ticket['created_at'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-bell"></i>Recent Indiscipline Reports</h3>
            <?php if (empty($recent_indiscipline_reports)): ?>
                <p class="text-muted text-center py-4">No recent pending indiscipline reports relevant to you.</p>
            <?php else: foreach ($recent_indiscipline_reports as $report):
                $reported_entity_name = '';
                if ($report['target_type'] === 'Student') {
                    $reported_entity_name = trim($report['student_first_name'] . ' ' . $report['student_last_name']);
                } elseif ($report['target_type'] === 'Teacher') {
                    $reported_entity_name = $report['reported_teacher_name'];
                }
            ?>
                <div class="list-item">
                    <div class="list-item-icon bg-orange-accent"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="list-item-content">
                        <h4>Report against: <?php echo htmlspecialchars($reported_entity_name); ?> (<?php echo htmlspecialchars($report['target_type']); ?>)</h4>
                        <p class="text-truncate">Incident: <?php echo htmlspecialchars($report['description']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo date("M j, Y", strtotime($report['incident_date'])); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-comments"></i>Recent Forum Activity</h3>
            <?php if (empty($recent_forum_activity)): ?>
                <p class="text-muted text-center py-4">No recent activity in your assigned class forums.</p>
            <?php else: foreach ($recent_forum_activity as $activity): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-green-accent"><i class="fas fa-comment-alt"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($activity['title']); ?></h4>
                        <p>Class: <?php echo htmlspecialchars($activity['class_name'] . ' - ' . $activity['section_name']); ?> | Last Reply: <?php echo date("M j, Y, g:i a", strtotime($activity['last_reply_at'])); ?></p>
                    </div>
                    <div class="list-item-extra">
                        <?php echo htmlspecialchars($activity['creator_role']); ?>
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