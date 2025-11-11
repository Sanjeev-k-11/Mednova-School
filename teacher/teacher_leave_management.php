<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];
$teacher_name = $_SESSION["full_name"] ?? 'Teacher';

// Check if this teacher is a class teacher
$class_teacher_info = null;
$sql_class = "SELECT id, class_name, section_name FROM classes WHERE teacher_id = ? LIMIT 1";
if($stmt_class = mysqli_prepare($link, $sql_class)){
    mysqli_stmt_bind_param($stmt_class, "i", $teacher_id);
    mysqli_stmt_execute($stmt_class);
    $result_class_info = mysqli_stmt_get_result($stmt_class);
    $class_teacher_info = mysqli_fetch_assoc($result_class_info);
    mysqli_stmt_close($stmt_class);
}

// Handle POST action to approve/reject
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status']) && $class_teacher_info) {
    $application_id = $_POST['application_id'];
    $new_status = $_POST['new_status'];
    $review_notes = $_POST['review_notes'] ?? null; // Added for review notes

    if (in_array($new_status, ['Approved', 'Rejected'])) {
        $sql_update = "UPDATE leave_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW(), notes = ? WHERE id = ? AND class_teacher_id = ?";
        if($stmt_update = mysqli_prepare($link, $sql_update)){
            mysqli_stmt_bind_param($stmt_update, "sssi", $new_status, $teacher_name, $review_notes, $application_id, $teacher_id);
            if(mysqli_stmt_execute($stmt_update)){
                $_SESSION['success_message'] = "Leave application has been " . strtolower($new_status) . ".";
            } else {
                $_SESSION['error_message'] = "Failed to update status: " . mysqli_stmt_error($stmt_update);
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $_SESSION['error_message'] = "Failed to prepare update statement.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid status provided.";
    }
    header("Location: teacher_leave_management.php?" . http_build_query($_GET)); // Preserve filters after action
    exit;
}

// --- NEW: PAGINATION & STATS LOGIC ---
$applications = [];
$stats = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
$filter_status = $_GET['status'] ?? 'Pending'; // Default filter to 'Pending'

if ($class_teacher_info) {
    $class_id = $class_teacher_info['id'];

    // Get stats for all categories
    $sql_stats = "SELECT status, COUNT(id) as count FROM leave_applications WHERE class_id = ? GROUP BY status";
    if($stmt_stats = mysqli_prepare($link, $sql_stats)){
        mysqli_stmt_bind_param($stmt_stats, "i", $class_id);
        mysqli_stmt_execute($stmt_stats);
        $result_stats = mysqli_stmt_get_result($stmt_stats);
        while($row = mysqli_fetch_assoc($result_stats)){
            if(isset($stats[$row['status']])) {
                $stats[$row['status']] = $row['count'];
            }
        }
        mysqli_stmt_close($stmt_stats);
    }

    // Pagination setup
    $records_per_page = 10;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $records_per_page;
    $total_records = 0;

    // Build query for fetching applications
    $where_sql = "WHERE la.class_id = ?";
    $params = [$class_id];
    $types = "i";

    if(!empty($filter_status) && in_array($filter_status, ['Pending', 'Approved', 'Rejected'])) {
        $where_sql .= " AND la.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
    
    // Get total records for the current filter
    $sql_count = "SELECT COUNT(la.id) FROM leave_applications la $where_sql";
    if($stmt_count = mysqli_prepare($link, $sql_count)){
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
        mysqli_stmt_execute($stmt_count);
        mysqli_stmt_bind_result($stmt_count, $total_records);
        mysqli_stmt_fetch($stmt_count);
        mysqli_stmt_close($stmt_count);
    }
    $total_pages = ceil($total_records / $records_per_page);

    // Fetch paginated applications
    // We need to extend $params and $types for LIMIT and OFFSET
    $sql_fetch = "SELECT la.*, s.first_name, s.middle_name, s.last_name, s.roll_number FROM leave_applications la JOIN students s ON la.student_id = s.id $where_sql ORDER BY la.created_at DESC LIMIT ? OFFSET ?";
    
    $fetch_params = $params; // Copy initial params
    $fetch_types = $types;   // Copy initial types

    $fetch_params[] = $records_per_page;
    $fetch_params[] = $offset;
    $fetch_types .= "ii";

    if($stmt_fetch = mysqli_prepare($link, $sql_fetch)){
        mysqli_stmt_bind_param($stmt_fetch, $fetch_types, ...$fetch_params);
        mysqli_stmt_execute($stmt_fetch);
        $applications = mysqli_fetch_all(mysqli_stmt_get_result($stmt_fetch), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_fetch);
    } else {
        error_log("DB Error in fetching applications: " . mysqli_error($link));
        $_SESSION['error_message'] = "Database error fetching leave applications.";
    }
}

$success_message = $_SESSION['success_message'] ?? null; unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null; unset($_SESSION['error_message']);

require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Teacher Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
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
        .page-header { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2.5rem 2rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); text-align: center; }
        .page-header h1 { font-weight: 700; color: #1a2a4b; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); margin-bottom: 1rem; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; gap: 15px; }
        .welcome-info-block { padding: 1rem; background: rgba(255, 255, 255, 0.5); border-radius: 0.5rem; display: inline-block; margin-top: 1rem; border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 2px 5px rgba(0,0,0,0.03); }
        .welcome-info { font-weight: 500; color: #666; margin-bottom: 0; font-size: 0.95rem; }
        .welcome-info strong { color: #333; }

        .section-title { font-size: 1.25rem; font-weight: 600; color: #1a2a4b; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }

        /* Dashboard Panel for main content */
        .dashboard-panel { 
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(10px);
            border-radius: 15px; 
            padding: 2rem; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            border: 1px solid rgba(255, 255, 255, 0.5); 
        }

        /* Stat Cards */
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .stat-card { 
            background: rgba(255, 255, 255, 0.9); /* Slightly less transparent for stats */
            border-radius: 15px; 
            padding: 25px; 
            border: 1px solid rgba(255, 255, 255, 0.7);
            display: flex; 
            align-items: center; 
            color: #1a2a4b; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 18px rgba(0,0,0,0.12); }
        .stat-card-icon { font-size: 2rem; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; flex-shrink: 0; }
        .stat-card-icon.bg-yellow-dark { background: #ffc107; color: #fff; }
        .stat-card-icon.bg-green-dark { background: #4caf50; color: #fff; }
        .stat-card-icon.bg-red-dark { background: #f44336; color: #fff; }
        .stat-card-content h3 { margin: 0; font-size: 1rem; opacity: 0.9; }
        .stat-card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }

        /* Tabs (Status Filter) */
        .dashboard-tabs { 
            background: rgba(0,0,0,0.05); 
            border-radius: 50px; 
            padding: 5px; 
            display: inline-flex; /* To shrink to content */
            margin-bottom: 1.5rem; /* Space below tabs */
        }
        .tab-link {
            padding: 10px 25px;
            border-radius: 50px;
            text-decoration: none;
            color: #1a2a4b;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .tab-link:hover {
            background-color: rgba(26, 42, 75, 0.1); /* Light hover for non-active */
        }
        .tab-link.active {
            background-color: #1a2a4b; /* Dark blue background */
            color: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Themed Table */
        .themed-table-wrapper { overflow-x: auto; }
        .themed-table { width: 100%; border-collapse: separate; border-spacing: 0; background-color: rgba(255,255,255,0.4); border-radius: 10px; overflow: hidden; }
        .themed-table-header { background-color: rgba(0,0,0,0.08); }
        .themed-table-header th { padding: 12px 15px; text-align: left; font-weight: 600; color: #1a2a4b; text-transform: uppercase; font-size: 0.85rem; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .themed-table-row { background-color: rgba(255,255,255,0.4); transition: background-color 0.2s ease; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .themed-table-row:hover { background-color: rgba(255,255,255,0.6); }
        .themed-table-row:last-child { border-bottom: none; }
        .themed-table-cell { padding: 12px 15px; font-size: 0.9rem; color: #333; vertical-align: top; }
        .themed-table-cell .font-medium { font-weight: 500; color: #1a2a4b; }
        .themed-table-cell .text-muted { color: #666; font-size: 0.85rem; }
        .themed-table-cell .truncate-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px; /* Adjust as needed */
            display: inline-block;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.3em 0.8em;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        .status-badge.Pending { background-color: #ffc107; color: #333; }
        .status-badge.Approved { background-color: #28a745; color: #fff; }
        .status-badge.Rejected { background-color: #dc3545; color: #fff; }

        /* Action Buttons */
        .btn-themed-success { background-color: #28a745; color: #fff; font-weight: 500; padding: 0.4rem 0.9rem; border-radius: 0.5rem; border: none; font-size: 0.85rem; transition: background-color 0.2s, transform 0.2s; }
        .btn-themed-success:hover { background-color: #218838; transform: translateY(-1px); color: #fff; }
        .btn-themed-danger { background-color: #dc3545; color: #fff; font-weight: 500; padding: 0.4rem 0.9rem; border-radius: 0.5rem; border: none; font-size: 0.85rem; transition: background-color 0.2s, transform 0.2s; }
        .btn-themed-danger:hover { background-color: #c82333; transform: translateY(-1px); color: #fff; }
        .btn-themed-view-notes { background-color: #007bff; color: #fff; font-weight: 500; padding: 0.4rem 0.9rem; border-radius: 0.5rem; border: none; font-size: 0.85rem; transition: background-color 0.2s, transform 0.2s; }
        .btn-themed-view-notes:hover { background-color: #0069d9; transform: translateY(-1px); color: #fff; }


        /* Info Card for Access Denied / No Applications */
        .info-card { 
            background: rgba(255, 255, 255, 0.9); 
            border-radius: 15px; 
            padding: 3rem; 
            text-align: center; 
            color: #1a2a4b; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.7);
            max-width: 600px;
            margin: 2rem auto;
        }
        .info-card .icon { font-size: 3rem; margin-bottom: 1.5rem; color: #1a2a4b; }
        .info-card h2 { font-size: 2rem; font-weight: 700; margin-bottom: 1rem; }
        .info-card p { font-size: 1.1rem; opacity: 0.9; margin-bottom: 0.5rem; }
        .info-card .btn-themed-primary { margin-top: 1.5rem; }

        /* Pagination */
        .pagination-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding: 1rem 0;
            border-top: 1px solid rgba(0,0,0,0.08);
            color: #666; /* text-muted */
            font-size: 0.9rem;
        }
        .pagination-info {
            flex-grow: 1;
            text-align: left;
            margin-bottom: 0.5rem; /* For mobile */
        }
        .pagination-controls {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
            gap: 0.5rem;
        }
        .pagination-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            color: #1a2a4b;
            background-color: rgba(255,255,255,0.4);
            border: 1px solid rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        .pagination-link:hover:not(.active):not(.disabled) {
            background-color: rgba(255,255,255,0.6);
            border-color: #1a2a4b;
            color: #1a2a4b;
        }
        .pagination-link.active {
            background-color: #1a2a4b;
            border-color: #1a2a4b;
            color: white;
            font-weight: 600;
            cursor: default;
        }
        .pagination-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }


        /* Flash Message (Toast) */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
            min-width: 250px;
        }
        .toast-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast-notification.bg-success-themed { background-color: #28a745; }
        .toast-notification.bg-error-themed { background-color: #dc3545; }
        .toast-notification.bg-success-themed,
        .toast-notification.bg-error-themed {
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Review Notes Modal */
        .review-notes-modal .modal-header {
            background-color: #1a2a4b;
            color: #fff;
            border-bottom: none;
            border-radius: 1rem 1rem 0 0;
        }
        .review-notes-modal .modal-title {
            color: #fff;
        }
        .review-notes-modal .modal-content {
            border-radius: 1rem;
            background-color: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.7);
        }
        .review-notes-modal .modal-body {
            color: #333;
        }
        .review-notes-modal .form-control {
            background-color: rgba(255,255,255,0.7);
            border: 1px solid rgba(0,0,0,0.15);
            color: #333;
        }
        .review-notes-modal .form-control:focus {
            border-color: #1a2a4b;
            box-shadow: 0 0 0 0.25rem rgba(26, 42, 75, 0.25);
        }
        .review-notes-modal .modal-footer {
            background-color: rgba(0,0,0,0.05);
            border-top: none;
            border-radius: 0 0 1rem 1rem;
        }
        .btn-themed-close {
            background-color: #6c757d;
            color: #fff;
        }
        .btn-themed-close:hover {
            background-color: #5a6268;
            color: #fff;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-container { margin-top: 20px; padding: 10px; }
            .page-header h1 { font-size: 2em; flex-direction: column; gap: 5px; }
            .welcome-info-block { width: 100%; text-align: center; }
            .card-grid { grid-template-columns: 1fr; }
            .stat-card-content h3 { font-size: 0.9rem; }
            .stat-card-content p { font-size: 1.8em; }
            .dashboard-tabs { width: 100%; justify-content: center; padding: 3px; }
            .tab-link { flex-grow: 1; text-align: center; font-size: 0.85rem; padding: 8px 15px; }
            .themed-table-header th, .themed-table-cell { padding: 8px 10px; font-size: 0.8rem; }
            .themed-table-cell .truncate-text { max-width: 150px; }
            .pagination-container { flex-direction: column; align-items: center; }
            .pagination-info { text-align: center; margin-bottom: 1rem; }
            .pagination-controls { justify-content: center; }
            .btn-themed-success, .btn-themed-danger, .btn-themed-view-notes { width: 100%; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <header class="page-header">
        <h1 class="page-title">
            <i class="fas fa-file-signature"></i> Leave Management
        </h1>
        <div class="welcome-info-block">
            <p class="welcome-info">
                Teacher: <strong><?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?></strong>
                <?php if ($class_teacher_info): ?>
                    Class: <strong><?php echo htmlspecialchars($class_teacher_info['class_name'] . ' - ' . $class_teacher_info['section_name']); ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </header>

    <?php if (!empty($success_message)): ?>
        <div class="toast-notification bg-success-themed show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php elseif (!empty($error_message)): ?>
        <div class="toast-notification bg-error-themed show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!$class_teacher_info): ?>
        <div class="info-card">
            <i class="fas fa-exclamation-triangle icon"></i>
            <h2>Access Denied</h2>
            <p>You are not assigned as a Class Teacher. Only Class Teachers can manage leave applications.</p>
            <p><a href="teacher_dashboard.php" class="btn btn-themed-primary mt-3">Back to Dashboard</a></p>
        </div>
    <?php else: ?>
        <!-- Stat Cards -->
        <div class="card-grid">
            <div class="stat-card">
                <div class="stat-card-icon bg-yellow-dark"><i class="fas fa-clock"></i></div>
                <div class="stat-card-content">
                    <h3>Pending Applications</h3>
                    <p><?php echo $stats['Pending']; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon bg-green-dark"><i class="fas fa-check-circle"></i></div>
                <div class="stat-card-content">
                    <h3>Approved Applications</h3>
                    <p><?php echo $stats['Approved']; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon bg-red-dark"><i class="fas fa-times-circle"></i></div>
                <div class="stat-card-content">
                    <h3>Rejected Applications</h3>
                    <p><?php echo $stats['Rejected']; ?></p>
                </div>
            </div>
        </div>

        <div class="dashboard-panel">
            <h2 class="section-title">Leave Applications for Class <?php echo htmlspecialchars($class_teacher_info['class_name'] . ' - ' . $class_teacher_info['section_name']); ?></h2>
            
            <!-- Tabs (Status Filter) -->
            <div class="d-flex justify-content-center mb-4">
                <div class="dashboard-tabs">
                    <a href="?status=Pending" class="tab-link <?php echo ($filter_status == 'Pending') ? 'active' : ''; ?>">Pending (<?php echo $stats['Pending']; ?>)</a>
                    <a href="?status=Approved" class="tab-link <?php echo ($filter_status == 'Approved') ? 'active' : ''; ?>">Approved (<?php echo $stats['Approved']; ?>)</a>
                    <a href="?status=Rejected" class="tab-link <?php echo ($filter_status == 'Rejected') ? 'active' : ''; ?>">Rejected (<?php echo $stats['Rejected']; ?>)</a>
                </div>
            </div>

            <div class="themed-table-wrapper">
                <table class="themed-table">
                    <thead class="themed-table-header">
                        <tr>
                            <th>Roll No.</th>
                            <th>Student Name</th>
                            <th>Leave Dates</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($applications)): ?>
                            <tr class="themed-table-row"><td colspan="6" class="themed-table-cell text-center text-muted py-4">No applications found for '<?php echo htmlspecialchars($filter_status); ?>' status.</td></tr>
                        <?php else: ?>
                            <?php foreach($applications as $app): ?>
                                <tr class="themed-table-row">
                                    <td class="themed-table-cell"><?php echo htmlspecialchars($app['roll_number']); ?></td>
                                    <td class="themed-table-cell font-medium"><?php echo htmlspecialchars(trim($app['first_name'] . ' ' . $app['middle_name'] . ' ' . $app['last_name'])); ?></td>
                                    <td class="themed-table-cell">
                                        <?php echo date('M d, Y', strtotime($app['leave_from'])); ?> - <?php echo date('M d, Y', strtotime($app['leave_to'])); ?>
                                    </td>
                                    <td class="themed-table-cell">
                                        <span class="truncate-text" title="<?php echo htmlspecialchars($app['reason']); ?>">
                                            <?php echo htmlspecialchars($app['reason']); ?>
                                        </span>
                                    </td>
                                    <td class="themed-table-cell">
                                        <span class="status-badge <?php echo htmlspecialchars($app['status']); ?>"><?php echo htmlspecialchars($app['status']); ?></span>
                                        <?php if ($app['reviewed_by']): ?>
                                            <div class="text-muted mt-1">by <?php echo htmlspecialchars($app['reviewed_by']); ?> on <?php echo date('M d, Y', strtotime($app['reviewed_at'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="themed-table-cell text-center">
                                        <?php if($app['status'] == 'Pending'): ?>
                                            <div class="d-flex flex-column gap-2">
                                                <!-- Approve Form (now opens modal) -->
                                                <button type="button" class="btn-themed-success" data-bs-toggle="modal" data-bs-target="#reviewModal" 
                                                        data-app-id="<?php echo $app['id']; ?>" data-student-name="<?php echo htmlspecialchars(trim($app['first_name'] . ' ' . $app['last_name'])); ?>"
                                                        data-leave-dates="<?php echo date('M d, Y', strtotime($app['leave_from'])) . ' - ' . date('M d, Y', strtotime($app['leave_to'])); ?>"
                                                        data-action="Approved" data-current-notes="<?php echo htmlspecialchars($app['notes'] ?? ''); ?>">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                                <!-- Reject Form (now opens modal) -->
                                                <button type="button" class="btn-themed-danger" data-bs-toggle="modal" data-bs-target="#reviewModal" 
                                                        data-app-id="<?php echo $app['id']; ?>" data-student-name="<?php echo htmlspecialchars(trim($app['first_name'] . ' ' . $app['last_name'])); ?>"
                                                        data-leave-dates="<?php echo date('M d, Y', strtotime($app['leave_from'])) . ' - ' . date('M d, Y', strtotime($app['leave_to'])); ?>"
                                                        data-action="Rejected" data-current-notes="<?php echo htmlspecialchars($app['notes'] ?? ''); ?>">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <?php if (!empty($app['notes'])): ?>
                                                <button type="button" class="btn-themed-view-notes" data-bs-toggle="modal" data-bs-target="#viewNotesModal" 
                                                        data-student-name="<?php echo htmlspecialchars(trim($app['first_name'] . ' ' . $app['last_name'])); ?>"
                                                        data-notes="<?php echo htmlspecialchars($app['notes']); ?>">
                                                    <i class="fas fa-info-circle me-1"></i> View Notes
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">No actions</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">Showing <b><?php echo min($offset + 1, $total_records); ?></b> to <b><?php echo min($offset + $records_per_page, $total_records); ?></b> of <b><?php echo $total_records; ?></b> records.</div>
                    <nav class="pagination-controls">
                        <a href="?status=<?php echo $filter_status; ?>&page=<?php echo max(1, $current_page - 1); ?>" class="pagination-link <?php if($current_page <= 1) echo 'disabled'; ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                        <?php
                        $link_base = "?status=" . $filter_status;
                        $page_range = 2; // Number of pages to show before and after current
                        $start_page = max(1, $current_page - $page_range);
                        $end_page = min($total_pages, $current_page + $page_range);

                        if ($start_page > 1) {
                            echo '<a href="' . $link_base . '&page=1" class="pagination-link">1</a>';
                            if ($start_page > 2) { echo '<span class="pagination-link disabled">...</span>'; }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="<?php echo $link_base; ?>&page=<?php echo $i; ?>" class="pagination-link <?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor;

                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) { echo '<span class="pagination-link disabled">...</span>'; }
                            echo '<a href="' . $link_base . '&page=' . $total_pages . '" class="pagination-link">' . $total_pages . '</a>';
                        }
                        ?>
                        <a href="?status=<?php echo $filter_status; ?>&page=<?php echo min($total_pages, $current_page + 1); ?>" class="pagination-link <?php if($current_page >= $total_pages) echo 'disabled'; ?>">Next <i class="fas fa-chevron-right"></i></a>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Review Leave Application Modal -->
<div class="modal fade review-notes-modal" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="reviewForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel"><i class="fas fa-file-signature me-2"></i> Review Leave Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-2">Student: <strong id="modalStudentName"></strong> (Roll: <span id="modalRollNumber"></span>)</p>
                    <p class="text-muted mb-3">Leave Dates: <strong id="modalLeaveDates"></strong></p>
                    
                    <input type="hidden" name="application_id" id="modalApplicationId">
                    <input type="hidden" name="new_status" id="modalNewStatus">
                    
                    <div class="mb-3">
                        <label for="review_notes" class="form-label">Add Notes (Optional)</label>
                        <textarea class="form-control" name="review_notes" id="review_notes" rows="4" placeholder="Add any comments or conditions for approval/rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-themed-close" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-themed-primary" id="modalSubmitButton">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Notes Modal -->
<div class="modal fade review-notes-modal" id="viewNotesModal" tabindex="-1" aria-labelledby="viewNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewNotesModalLabel"><i class="fas fa-info-circle me-2"></i> Review Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2">Student: <strong id="viewNotesModalStudentName"></strong></p>
                <div class="alert alert-info">
                    <p class="mb-0" id="viewNotesContent"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-themed-close" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Review Modal Logic
        const reviewModal = document.getElementById('reviewModal');
        reviewModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Button that triggered the modal
            const applicationId = button.getAttribute('data-app-id');
            const studentName = button.getAttribute('data-student-name');
            const leaveDates = button.getAttribute('data-leave-dates');
            const action = button.getAttribute('data-action'); // 'Approved' or 'Rejected'
            const currentNotes = button.getAttribute('data-current-notes');

            const modalTitle = reviewModal.querySelector('.modal-title');
            const modalStudentName = reviewModal.querySelector('#modalStudentName');
            const modalLeaveDates = reviewModal.querySelector('#modalLeaveDates');
            const modalApplicationId = reviewModal.querySelector('#modalApplicationId');
            const modalNewStatus = reviewModal.querySelector('#modalNewStatus');
            const modalReviewNotes = reviewModal.querySelector('#review_notes');
            const modalSubmitButton = reviewModal.querySelector('#modalSubmitButton');

            modalStudentName.textContent = studentName;
            modalLeaveDates.textContent = leaveDates;
            modalApplicationId.value = applicationId;
            modalNewStatus.value = action;
            modalReviewNotes.value = currentNotes; // Pre-fill with existing notes

            modalTitle.innerHTML = `<i class="fas fa-${action === 'Approved' ? 'check' : 'times'} me-2"></i> ${action} Leave Application`;
            modalSubmitButton.textContent = `${action} Application`;
            modalSubmitButton.classList.remove('btn-themed-success', 'btn-themed-danger', 'btn-themed-primary'); // Clear previous
            modalSubmitButton.classList.add(action === 'Approved' ? 'btn-themed-success' : 'btn-themed-danger');
        });

        // View Notes Modal Logic
        const viewNotesModal = document.getElementById('viewNotesModal');
        viewNotesModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Button that triggered the modal
            const studentName = button.getAttribute('data-student-name');
            const notes = button.getAttribute('data-notes');

            const modalStudentName = viewNotesModal.querySelector('#viewNotesModalStudentName');
            const viewNotesContent = viewNotesModal.querySelector('#viewNotesContent');

            modalStudentName.textContent = studentName;
            viewNotesContent.innerHTML = notes.replace(/\n/g, '<br>'); // Display newlines properly
        });

        // Toast Notification Logic
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const bgColorClass = type === 'success' ? 'bg-success-themed' : 'bg-error-themed';
            const iconHtml = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-triangle"></i>';
            
            const toast = document.createElement('div');
            toast.className = `toast-notification ${bgColorClass}`;
            toast.innerHTML = `<div class="d-flex align-items-center gap-2">${iconHtml} ${message}</div>`;
            
            container.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100); // Small delay for CSS transition
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => toast.remove());
            }, 5000); // Auto-hide after 5 seconds
        }

        // Display initial flash messages from PHP (if any)
        <?php if ($success_message): ?>
            showToast('<?php echo addslashes($success_message); ?>', 'success');
        <?php elseif ($error_message): ?>
            showToast('<?php echo addslashes($error_message); ?>', 'error');
        <?php endif; ?>
    });
</script>
</body>
<?php require_once './teacher_footer.php'; ?>