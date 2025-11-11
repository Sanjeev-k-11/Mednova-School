<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];
$teacher_name = $_SESSION['full_name'];

// --- HANDLE POST ACTIONS: REPLYING AND CHANGING STATUS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ticket_id = $_POST['ticket_id'] ?? null;
    if ($ticket_id) {
        // Security check: verify the teacher owns this ticket
        $sql_verify = "SELECT id FROM support_tickets WHERE id = ? AND teacher_id = ?";
        if ($stmt_v = mysqli_prepare($link, $sql_verify)) {
            mysqli_stmt_bind_param($stmt_v, "ii", $ticket_id, $teacher_id);
            mysqli_stmt_execute($stmt_v);
            mysqli_stmt_store_result($stmt_v);
            if (mysqli_stmt_num_rows($stmt_v) == 1) {
                // Handle reply submission
                if (isset($_POST['reply_message']) && !empty(trim($_POST['reply_message']))) {
                    $reply_message = trim($_POST['reply_message']);
                    $sql_insert = "INSERT INTO support_ticket_messages (ticket_id, user_id, user_role, message) VALUES (?, ?, 'Teacher', ?)";
                    if ($stmt_i = mysqli_prepare($link, $sql_insert)) {
                        mysqli_stmt_bind_param($stmt_i, "iis", $ticket_id, $teacher_id, $reply_message);
                        mysqli_stmt_execute($stmt_i);
                        mysqli_stmt_close($stmt_i);
                    }
                    // Re-open ticket on reply
                    $sql_update = "UPDATE support_tickets SET status = 'Open', updated_at = NOW() WHERE id = ?";
                    if ($stmt_u = mysqli_prepare($link, $sql_update)) {
                        mysqli_stmt_bind_param($stmt_u, "i", $ticket_id);
                        mysqli_stmt_execute($stmt_u);
                        mysqli_stmt_close($stmt_u);
                    }
                    $_SESSION['success_message'] = "Reply sent successfully!";
                }
                // Handle status change
                if (isset($_POST['change_status'])) {
                    $new_status = $_POST['new_status'];
                    if (in_array($new_status, ['Open', 'Closed'])) {
                        $sql_update = "UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?";
                        if ($stmt_u = mysqli_prepare($link, $sql_update)) {
                            mysqli_stmt_bind_param($stmt_u, "si", $new_status, $ticket_id);
                            mysqli_stmt_execute($stmt_u);
                            mysqli_stmt_close($stmt_u);
                            $_SESSION['success_message'] = "Ticket status updated to " . $new_status;
                        }
                    }
                }
            } else {
                $_SESSION['error_message'] = "Unauthorized action. You do not have permission for this ticket.";
            }
            mysqli_stmt_close($stmt_v);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit;
}

// --- FETCH DATA FOR FILTERS AND DISPLAY ---
$view_ticket_id = $_GET['ticket_id'] ?? null;
$filter_status = $_GET['status'] ?? 'Open';
$filter_class = $_GET['class_id'] ?? '';
$teacher_classes = [];
$sql_classes = "SELECT DISTINCT c.id, c.class_name, c.section_name FROM support_tickets st JOIN classes c ON st.class_id = c.id WHERE st.teacher_id = ? ORDER BY c.class_name";
if ($stmt_c = mysqli_prepare($link, $sql_classes)) {
    mysqli_stmt_bind_param($stmt_c, "i", $teacher_id);
    mysqli_stmt_execute($stmt_c);
    $teacher_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_c), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_c);
}

if ($view_ticket_id) {
    // --- VIEW SINGLE TICKET CONVERSATION ---
    $ticket = null;
    $messages = [];
    $sql_ticket = "SELECT st.*, s.first_name, s.middle_name, s.last_name, s.roll_number, c.class_name, c.section_name, sub.subject_name FROM support_tickets st JOIN students s ON st.student_id = s.id JOIN classes c ON st.class_id = c.id JOIN subjects sub ON st.subject_id = sub.id WHERE st.id = ? AND st.teacher_id = ?";
    if ($stmt_t = mysqli_prepare($link, $sql_ticket)) {
        mysqli_stmt_bind_param($stmt_t, "ii", $view_ticket_id, $teacher_id);
        mysqli_stmt_execute($stmt_t);
        $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t));
        mysqli_stmt_close($stmt_t);
    }
    if ($ticket) {
        $sql_messages = "SELECT * FROM support_ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC";
        if ($stmt_m = mysqli_prepare($link, $sql_messages)) {
            mysqli_stmt_bind_param($stmt_m, "i", $view_ticket_id);
            mysqli_stmt_execute($stmt_m);
            $messages = mysqli_fetch_all(mysqli_stmt_get_result($stmt_m), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt_m);
        }
    } else {
        $_SESSION['error_message'] = "Ticket not found or you do not have permission to view it.";
        header("Location: teacher_helpdesk.php");
        exit;
    }
} else {
    // --- VIEW LIST OF TICKETS (with Pagination and Stats) ---
    $tickets = [];
    $stats = ['Open' => 0, 'Closed' => 0];
    // Get stats
    $sql_stats = "SELECT status, COUNT(id) as count FROM support_tickets WHERE teacher_id = ? GROUP BY status";
    if ($stmt_stats = mysqli_prepare($link, $sql_stats)) {
        mysqli_stmt_bind_param($stmt_stats, "i", $teacher_id);
        mysqli_stmt_execute($stmt_stats);
        $result_stats = mysqli_stmt_get_result($stmt_stats);
        while ($row = mysqli_fetch_assoc($result_stats)) {
            if (isset($stats[$row['status']])) {
                $stats[$row['status']] = $row['count'];
            }
        }
        mysqli_stmt_close($stmt_stats);
    }

    $records_per_page = 10;
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $records_per_page;
    $total_records = 0;

    $where_clauses = ["st.teacher_id = ?"];
    $params = [$teacher_id];
    $types = "i";
    if (!empty($filter_status) && in_array($filter_status, ['Open', 'Closed'])) {
        $where_clauses[] = "st.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
    if (!empty($filter_class)) {
        $where_clauses[] = "st.class_id = ?";
        $params[] = $filter_class;
        $types .= "i";
    }
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);

    $sql_count = "SELECT COUNT(st.id) FROM support_tickets st $where_sql";
    if ($stmt_count = mysqli_prepare($link, $sql_count)) {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
        mysqli_stmt_execute($stmt_count);
        mysqli_stmt_bind_result($stmt_count, $total_records);
        mysqli_stmt_fetch($stmt_count);
        mysqli_stmt_close($stmt_count);
    }
    $total_pages = ceil($total_records / $records_per_page);

    $sql_list = "SELECT st.id, st.title, st.status, st.updated_at, s.first_name, s.last_name, c.class_name, c.section_name, sub.subject_name FROM support_tickets st JOIN students s ON st.student_id = s.id JOIN classes c ON st.class_id = c.id JOIN subjects sub ON st.subject_id = sub.id $where_sql ORDER BY st.updated_at DESC LIMIT ? OFFSET ?";
    $params_list = $params;
    $types_list = $types;
    $params_list[] = $records_per_page;
    $params_list[] = $offset;
    $types_list .= "ii";
    if ($stmt_l = mysqli_prepare($link, $sql_list)) {
        mysqli_stmt_bind_param($stmt_l, $types_list, ...$params_list);
        mysqli_stmt_execute($stmt_l);
        $tickets = mysqli_fetch_all(mysqli_stmt_get_result($stmt_l), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_l);
    }
}

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

require_once './teacher_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Helpdesk - Teacher Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(-45deg, #e0f2f7, #e3f2fd, #bbdefb, #90caf9); /* Light Blue/Azure theme */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .page-header { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2.5rem 2rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); text-align: center; }
        .page-header h1 { font-weight: 700; color: #1a2a4b; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); margin-bottom: 1rem; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; gap: 15px; }
        .welcome-info-block { padding: 1rem; background: rgba(255, 255, 255, 0.5); border-radius: 0.5rem; display: inline-block; margin-top: 1rem; border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 2px 5px rgba(0,0,0,0.03); }
        .welcome-info { font-weight: 500; color: #666; margin-bottom: 0; font-size: 0.95rem; }
        .welcome-info strong { color: #333; }
        .dashboard-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); }
        .stat-card { background: rgba(255, 255, 255, 0.9); border-radius: 15px; padding: 25px; border: 1px solid rgba(255, 255, 255, 0.7); display: flex; align-items: center; color: #1a2a4b; box-shadow: 0 4px 10px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 18px rgba(0,0,0,0.12); }
        .stat-card-icon { font-size: 2rem; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; flex-shrink: 0; }
        .stat-card-icon.bg-yellow-dark { background: #ffc107; color: #fff; }
        .stat-card-icon.bg-gray-dark { background: #6c757d; color: #fff; }
        .stat-card-content h3 { margin: 0; font-size: 1rem; opacity: 0.9; }
        .stat-card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        .form-control-themed { background-color: rgba(255,255,255,0.8); border: 1px solid rgba(0,0,0,0.15); border-radius: 0.5rem; padding: 0.5rem 0.8rem; color: #333; font-size: 0.9rem; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s ease; }
        .themed-table { width: 100%; border-collapse: separate; border-spacing: 0; background-color: rgba(255,255,255,0.4); border-radius: 10px; overflow: hidden; }
        .themed-table-header { background-color: rgba(0,0,0,0.08); }
        .themed-table-header th { padding: 12px 15px; text-align: left; font-weight: 600; color: #1a2a4b; text-transform: uppercase; font-size: 0.85rem; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .themed-table-row { background-color: rgba(255,255,255,0.4); transition: background-color 0.2s ease; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .themed-table-row:hover { background-color: rgba(255,255,255,0.6); }
        .themed-table-cell { padding: 12px 15px; font-size: 0.9rem; color: #333; vertical-align: top; }
        .status-badge { display: inline-block; padding: 0.3em 0.8em; border-radius: 50px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-badge.Open { background-color: #ffc107; color: #333; }
        .status-badge.Closed { background-color: #6c757d; color: #fff; }
        .back-link { display: inline-flex; align-items: center; background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(5px); color: #1a2a4b; padding: 10px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9em; transition: background 0.3s, transform 0.2s; border: 1px solid rgba(255,255,255,0.3); }
        .back-link:hover { background: rgba(255, 255, 255, 0.6); transform: translateY(-2px); }
        .btn-themed-primary { background-color: #1a2a4b; color: #fff; font-weight: 600; padding: 10px 25px; border-radius: 10px; border: none; transition: background-color 0.2s, transform 0.2s; }
        .btn-themed-secondary { background-color: #6c757d; color: #fff; font-weight: 600; padding: 10px 25px; border-radius: 10px; border: none; transition: background-color 0.2s, transform 0.2s; }
        .ticket-header { border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 1.5rem; margin-bottom: 1.5rem; }
        .ticket-header h2 { font-weight: 700; color: #1a2a4b; }
        .message-container { height: 50vh; overflow-y: auto; background-color: rgba(0,0,0,0.05); border-radius: 10px; padding: 1rem; }
        .message-bubble { max-width: 75%; padding: 0.75rem 1rem; border-radius: 1rem; word-wrap: break-word; }
        .message-bubble p { margin-bottom: 0.5rem; }
        .message-bubble .timestamp { font-size: 0.75rem; opacity: 0.8; }
        .student-message { background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .teacher-message { background-color: #d1e7fd; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .pagination-container { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-top: 2rem; padding: 1rem 0; border-top: 1px solid rgba(0,0,0,0.08); color: #666; font-size: 0.9rem; }
        .pagination-controls { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 0.5rem; }
        .pagination-link { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border-radius: 8px; text-decoration: none; font-weight: 500; color: #1a2a4b; background-color: rgba(255,255,255,0.4); border: 1px solid rgba(0,0,0,0.1); transition: all 0.2s ease; }
        .pagination-link.active { background-color: #1a2a4b; border-color: #1a2a4b; color: white; }
        .pagination-link.disabled { opacity: 0.5; cursor: not-allowed; }
        .toast-notification { position: fixed; top: 20px; right: 20px; z-index: 1000; opacity: 0; transform: translateY(-20px); transition: opacity 0.3s ease-out, transform 0.3s ease-out; min-width: 250px; }
        .toast-notification.show { opacity: 1; transform: translateY(0); }
        .toast-notification.bg-success-themed { background-color: #28a745; }
        .toast-notification.bg-error-themed { background-color: #dc3545; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php if ($view_ticket_id && $ticket): // --- SINGLE TICKET VIEW --- ?>
        <a href="teacher_helpdesk.php" class="back-link mb-4"><i class="fas fa-arrow-left me-2"></i>Back to Helpdesk</a>
        <div class="dashboard-panel">
            <div class="ticket-header">
                <div class="d-flex justify-content-between align-items-start">
                    <h2 class="mb-1"><?php echo htmlspecialchars($ticket['title']); ?></h2>
                    <span class="status-badge <?php echo htmlspecialchars($ticket['status']); ?>"><?php echo htmlspecialchars($ticket['status']); ?></span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 text-muted" style="font-size: 0.9rem;">
                    <span><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars(trim($ticket['first_name'] . ' ' . $ticket['last_name'])); ?> (Roll: <?php echo htmlspecialchars($ticket['roll_number']); ?>)</span>
                    <span><i class="fas fa-chalkboard me-1"></i> <?php echo htmlspecialchars($ticket['class_name'] . ' - ' . $ticket['section_name']); ?></span>
                    <span><i class="fas fa-book me-1"></i> <?php echo htmlspecialchars($ticket['subject_name']); ?></span>
                </div>
            </div>
            <div class="message-container mb-4">
                <?php if (empty($messages)): ?>
                    <div class="text-center text-muted p-5">No messages in this conversation yet.</div>
                <?php else: foreach ($messages as $message): ?>
                    <div class="d-flex mb-3 <?php echo $message['user_role'] == 'Teacher' ? 'justify-content-end' : 'justify-content-start'; ?>">
                        <div class="message-bubble <?php echo $message['user_role'] == 'Teacher' ? 'teacher-message' : 'student-message'; ?>">
                            <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                            <div class="timestamp text-end"><?php echo date('d M Y, h:i A', strtotime($message['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <form action="teacher_helpdesk.php?ticket_id=<?php echo $view_ticket_id; ?>" method="post">
                <input type="hidden" name="ticket_id" value="<?php echo $view_ticket_id; ?>">
                <div class="mb-3">
                    <textarea name="reply_message" rows="3" required class="form-control form-control-themed" placeholder="Type your reply..."></textarea>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <form method="POST" action="teacher_helpdesk.php?ticket_id=<?php echo $view_ticket_id; ?>" class="d-inline">
                        <input type="hidden" name="ticket_id" value="<?php echo $view_ticket_id; ?>">
                        <input type="hidden" name="new_status" value="<?php echo $ticket['status'] == 'Open' ? 'Closed' : 'Open'; ?>">
                        <button type="submit" name="change_status" class="btn btn-themed-secondary">
                            <i class="fas <?php echo $ticket['status'] == 'Open' ? 'fa-check-circle' : 'fa-history'; ?> me-2"></i><?php echo $ticket['status'] == 'Open' ? 'Mark as Closed' : 'Re-open Ticket'; ?>
                        </button>
                    </form>
                    <button type="submit" name="send_reply" class="btn btn-themed-primary"><i class="fas fa-paper-plane me-2"></i>Send Reply</button>
                </div>
            </form>
        </div>

    <?php else: // --- TICKET LIST VIEW --- ?>
        <header class="page-header">
            <h1 class="page-title"><i class="fas fa-headset"></i> Support Helpdesk</h1>
            <div class="welcome-info-block"><p class="welcome-info">Teacher: <strong><?php echo htmlspecialchars($teacher_name); ?></strong></p></div>
        </header>
        <div class="row g-4 mb-4">
            <div class="col-md-6 d-flex">
                <div class="stat-card flex-grow-1">
                    <div class="stat-card-icon bg-yellow-dark"><i class="fas fa-folder-open"></i></div>
                    <div class="stat-card-content"><h3>Open Tickets</h3><p><?php echo $stats['Open']; ?></p></div>
                </div>
            </div>
            <div class="col-md-6 d-flex">
                <div class="stat-card flex-grow-1">
                    <div class="stat-card-icon bg-gray-dark"><i class="fas fa-folder-closed"></i></div>
                    <div class="stat-card-content"><h3>Closed Tickets</h3><p><?php echo $stats['Closed']; ?></p></div>
                </div>
            </div>
        </div>

        <div class="dashboard-panel">
            <form method="GET" class="row g-3 align-items-end mb-4 pb-4 border-bottom">
                <div class="col-md-5">
                    <label for="status" class="form-label fw-bold">Status</label>
                    <select name="status" id="status" onchange="this.form.submit()" class="form-select form-control-themed">
                        <option value="Open" <?php if ($filter_status == 'Open') echo 'selected'; ?>>Open</option>
                        <option value="Closed" <?php if ($filter_status == 'Closed') echo 'selected'; ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="class_id" class="form-label fw-bold">Class</label>
                    <select name="class_id" id="class_id" onchange="this.form.submit()" class="form-select form-control-themed">
                        <option value="">All Classes</option>
                        <?php foreach ($teacher_classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php if ($filter_class == $class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <a href="teacher_helpdesk.php" class="btn btn-secondary w-100">Reset</a>
                </div>
            </form>
            
            <div class="themed-table-wrapper">
                <table class="themed-table">
                    <thead class="themed-table-header">
                        <tr><th>Student & Class</th><th>Title & Subject</th><th>Status</th><th>Last Updated</th><th class="text-center">Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr class="themed-table-row"><td colspan="5" class="themed-table-cell text-center text-muted py-4">No tickets found for this filter.</td></tr>
                        <?php else: foreach ($tickets as $ticket): ?>
                            <tr class="themed-table-row">
                                <td class="themed-table-cell">
                                    <div class="fw-bold"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                                    <div class="text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($ticket['class_name'] . ' - ' . $ticket['section_name']); ?></div>
                                </td>
                                <td class="themed-table-cell">
                                    <div class="fw-bold"><?php echo htmlspecialchars($ticket['title']); ?></div>
                                    <div class="text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($ticket['subject_name']); ?></div>
                                </td>
                                <td class="themed-table-cell"><span class="status-badge <?php echo htmlspecialchars($ticket['status']); ?>"><?php echo htmlspecialchars($ticket['status']); ?></span></td>
                                <td class="themed-table-cell"><?php echo date('d M, Y', strtotime($ticket['updated_at'])); ?></td>
                                <td class="themed-table-cell text-center"><a href="?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary" title="View / Reply"><i class="fas fa-external-link-alt"></i> View</a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">Showing <b><?php echo min($offset + 1, $total_records); ?></b> to <b><?php echo min($offset + $records_per_page, $total_records); ?></b> of <b><?php echo $total_records; ?></b> records.</div>
                    <nav class="pagination-controls">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $current_page - 1)])); ?>" class="pagination-link <?php if ($current_page <= 1) echo 'disabled'; ?>"><i class="fas fa-chevron-left"></i></a>
                        <?php
                        // Logic for smart page links
                        $link_base = "?status=" . $filter_status . "&class_id=" . $filter_class;
                        $page_range = 2; 
                        $start_page = max(1, $current_page - $page_range);
                        $end_page = min($total_pages, $current_page + $page_range);

                        if ($start_page > 1) { echo '<a href="' . $link_base . '&page=1" class="pagination-link">1</a>'; if ($start_page > 2) { echo '<span class="pagination-link disabled">...</span>'; } }
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="<?php echo $link_base; ?>&page=<?php echo $i; ?>" class="pagination-link <?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor;
                        if ($end_page < $total_pages) { if ($end_page < $total_pages - 1) { echo '<span class="pagination-link disabled">...</span>'; } echo '<a href="' . $link_base . '&page=' . $total_pages . '" class="pagination-link">' . $total_pages . '</a>'; }
                        ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $current_page + 1)])); ?>" class="pagination-link <?php if ($current_page >= $total_pages) echo 'disabled'; ?>"><i class="fas fa-chevron-right"></i></a>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1100"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const bgColorClass = type === 'success' ? 'bg-success text-white' : 'bg-danger text-white';
        const iconHtml = type === 'success' ? '<i class="fas fa-check-circle me-2"></i>' : '<i class="fas fa-exclamation-triangle me-2"></i>';
        
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center ${bgColorClass} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${iconHtml} ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        container.appendChild(toastEl);
        
        const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }
    
    <?php if ($success_message): ?> showToast('<?php echo addslashes($success_message); ?>', 'success'); <?php endif; ?>
    <?php if ($error_message): ?> showToast('<?php echo addslashes($error_message); ?>', 'error'); <?php endif; ?>
</script>
</body>
</html>
<?php mysqli_close($link); ?>
<?php require_once './teacher_footer.php'; ?>