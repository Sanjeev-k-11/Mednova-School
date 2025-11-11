<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- CHECK IF TEACHER IS A CLASS TEACHER ---
$class_teacher_info = null;
$sql_class_teacher = "SELECT id, class_name, section_name FROM classes WHERE teacher_id = ? LIMIT 1";
if ($stmt = mysqli_prepare($link, $sql_class_teacher)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $class_teacher_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Today's date (cannot be changed by the user)
$attendance_date = date('Y-m-d');
$message = '';
$message_type = 'success';

// --- HANDLE FORM SUBMISSION (SAVING/UPDATING ATTENDANCE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $class_teacher_info) {
    $students_attendance = $_POST['students'] ?? [];
    $edit_reason = $_POST['edit_reason'] ?? null;
    $student_id_for_edit = $_POST['student_id_for_edit'] ?? null;

    $target_student_id = $student_id_for_edit ?? null; // If this is an individual edit via modal

    $is_bulk_update = ($student_id_for_edit === null); // Determine if it's a bulk or individual update

    foreach ($students_attendance as $student_id => $status) {
        if ($target_student_id && $student_id != $target_student_id) {
            // If it's an individual edit, only process the target student
            continue;
        }

        $sql_check = "SELECT id, status, updated_at FROM attendance WHERE student_id = ? AND attendance_date = ?";
        $stmt_check = mysqli_prepare($link, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "is", $student_id, $attendance_date);
        mysqli_stmt_execute($stmt_check);
        $existing_record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
        mysqli_stmt_close($stmt_check);

        // Determine if update is allowed or if it's a first-time entry
        $can_update_freely = true;
        if ($existing_record) {
            $time_diff_minutes = (time() - strtotime($existing_record['updated_at'])) / 60;
            if ($time_diff_minutes > 10 && $student_id != $student_id_for_edit) {
                // If it's locked AND not the specific student being edited via modal
                $can_update_freely = false;
            }
        }
        
        if ($existing_record && $can_update_freely) {
            // Update existing record
            $sql_update = "UPDATE attendance SET status = ?, marked_by_teacher_id = ?, updated_at = NOW()";
            $params = [$status, $teacher_id];
            $types = "si";

            if ($student_id == $student_id_for_edit && !empty($edit_reason)) {
                $sql_update .= ", edit_reason = ?";
                $params[] = $edit_reason;
                $types .= "s";
            } else if ($is_bulk_update) {
                // Clear edit_reason if it's a bulk update over a previously individually edited record
                $sql_update .= ", edit_reason = NULL";
            }
            
            $sql_update .= " WHERE id = ?";
            $params[] = $existing_record['id'];
            $types .= "i";

            if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                mysqli_stmt_bind_param($stmt_update, $types, ...$params);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
            } else {
                error_log("Failed to prepare update statement: " . mysqli_error($link));
            }

        } elseif (!$existing_record) {
            // Insert new record
            $sql_insert = "INSERT INTO attendance (student_id, class_id, attendance_date, status, marked_by_teacher_id) VALUES (?, ?, ?, ?, ?)";
            if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                mysqli_stmt_bind_param($stmt_insert, "iissi", $student_id, $class_teacher_info['id'], $attendance_date, $status, $teacher_id);
                mysqli_stmt_execute($stmt_insert);
                mysqli_stmt_close($stmt_insert);
            } else {
                error_log("Failed to prepare insert statement: " . mysqli_error($link));
            }
        }
        // If $can_update_freely is false (locked and not individual edit), skip this student
    }
    
    $_SESSION['flash_message'] = "Attendance saved successfully!";
    header("Location: teacher_attendance.php");
    exit();
}

// Handle Flash Message
if(isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// --- DATA FETCHING for DISPLAY ---
$students = [];
if ($class_teacher_info) {
    $sql_students = "SELECT s.id, s.roll_number, s.first_name, s.middle_name, s.last_name, s.image_url, 
                     a.status, a.updated_at, a.edit_reason
                     FROM students s
                     LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = ?
                     WHERE s.class_id = ?
                     ORDER BY s.roll_number, s.first_name";
    if ($stmt = mysqli_prepare($link, $sql_students)) {
        mysqli_stmt_bind_param($stmt, "si", $attendance_date, $class_teacher_info['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

$attendance_marked_time = null;
if (!empty($students)) {
    foreach($students as $s) {
        if(!empty($s['updated_at'])) {
            $attendance_marked_time = $s['updated_at'];
            break;
        }
    }
}
$time_diff_minutes = $attendance_marked_time ? (time() - strtotime($attendance_marked_time)) / 60 : null;
$is_locked = ($time_diff_minutes !== null && $time_diff_minutes > 10);

mysqli_close($link);
require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance</title>
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
        .dashboard-container { max-width: 1200px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .page-header { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2.5rem 2rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); text-align: center; }
        .page-header h1 { font-weight: 700; color: #1a2a4b; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); margin-bottom: 1rem; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; gap: 15px; }
        .welcome-info-block { padding: 1rem; background: rgba(255, 255, 255, 0.5); border-radius: 0.5rem; display: inline-block; margin-top: 1rem; border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 2px 5px rgba(0,0,0,0.03); }
        .welcome-info { font-weight: 500; color: #666; margin-bottom: 0; font-size: 0.95rem; }
        .welcome-info strong { color: #333; }

        .dashboard-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #1a2a4b; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }

        .attendance-table-wrapper { overflow-x: auto; }
        .attendance-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .attendance-table th, .attendance-table td { padding: 12px 15px; border: none; vertical-align: middle; }
        .attendance-table th { background-color: rgba(0,0,0,0.05); color: #1a2a4b; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .attendance-table tbody tr { background-color: rgba(255,255,255,0.4); transition: background-color 0.2s ease; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .attendance-table tbody tr:hover { background-color: rgba(255,255,255,0.6); }
        .attendance-table tbody tr:last-child { border-bottom: none; }
        .attendance-table td { font-size: 0.95rem; color: #333; }
        .attendance-table td.font-semibold { font-weight: 600; }

        .status-select { 
            background-color: rgba(255,255,255,0.8); 
            border: 1px solid rgba(0,0,0,0.15); 
            border-radius: 0.5rem; 
            padding: 0.5rem 0.8rem; 
            font-size: 0.9rem; 
            color: #1a2a4b;
            appearance: none; /* Remove default arrow */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1em;
            cursor: pointer;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        .status-select:focus { outline: none; border-color: #1a2a4b; box-shadow: 0 0 0 0.25rem rgba(26, 42, 75, 0.25); background-color: #fff; }
        .status-select:disabled { background-color: rgba(0,0,0,0.1); cursor: not-allowed; opacity: 0.8; }
        
        .status-select option { background-color: #fff; color: #333; }
        .status-select option.bg-green-600 { background-color: #28a745; color: #fff; }
        .status-select option.bg-red-600 { background-color: #dc3545; color: #fff; }
        .status-select option.bg-yellow-600 { background-color: #ffc107; color: #333; }
        .status-select option.bg-blue-600 { background-color: #007bff; color: #fff; }

        .student-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px; border: 1px solid rgba(0,0,0,0.1); }
        .badge-edit-reason { background-color: #ffc107; color: #333; font-size: 0.75rem; padding: 0.3em 0.6em; border-radius: 0.5rem; margin-left: 10px; cursor: pointer; transition: background-color 0.2s ease; }
        .badge-edit-reason:hover { background-color: #e0a800; }

        /* Buttons */
        .btn-themed-primary { background-color: #1a2a4b; color: #fff; font-weight: 600; padding: 10px 25px; border-radius: 10px; border: none; transition: background-color 0.2s, transform 0.2s; }
        .btn-themed-primary:hover { background-color: #0d1a33; transform: translateY(-2px); color: #fff; }
        .btn-themed-secondary { background-color: #6c757d; color: #fff; font-weight: 600; padding: 10px 25px; border-radius: 10px; border: none; transition: background-color 0.2s, transform 0.2s; }
        .btn-themed-secondary:hover { background-color: #5a6268; transform: translateY(-2px); color: #fff; }
        .btn-themed-warning { background-color: #ffc107; color: #333; font-weight: 600; padding: 10px 25px; border-radius: 10px; border: none; transition: background-color 0.2s, transform 0.2s; }
        .btn-themed-warning:hover { background-color: #e0a800; transform: translateY(-2px); color: #333; }
        .btn-sm-edit { background-color: #ffc107; color: #333; font-size: 0.75rem; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 0.5rem; border: none; transition: background-color 0.2s; }
        .btn-sm-edit:hover { background-color: #e0a800; }


        /* Info Messages */
        .info-card { background-color: rgba(0,0,0,0.1); border-radius: 0.75rem; padding: 1.5rem; text-align: center; color: #1a2a4b; margin-top: 2rem; }
        .info-card .icon { font-size: 3rem; margin-bottom: 1rem; color: #1a2a4b; }
        .info-card h2 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
        .info-card p { font-size: 1.1rem; opacity: 0.9; }

        /* Flash Message */
        .flash-message { background-color: #d4edda; color: #1a6d2f; border: 1px solid #c3e6cb; border-left: 5px solid #28a745; border-radius: 0.75rem; padding: 1rem 1.5rem; margin-bottom: 1.5rem; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .flash-message i { font-size: 1.2em; }

        /* Lock Status */
        .lock-status { font-weight: 600; font-size: 0.95rem; margin-top: 10px; }
        .lock-status.unlocked { color: #28a745; }
        .lock-status.locked { color: #dc3545; }
        .lock-status i { margin-right: 5px; }

        /* Modal */
        .modal-backdrop.show { opacity: 0.7 !important; } /* Darker backdrop */
        .modal-themed .modal-content {
            background: rgba(255, 255, 255, 0.9); /* Slightly opaque white */
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: #333;
        }
        .modal-themed .modal-header { border-bottom: 1px solid rgba(0,0,0,0.1); padding: 1.5rem; }
        .modal-themed .modal-title { font-weight: 700; color: #1a2a4b; font-size: 1.5rem; }
        .modal-themed .modal-body { padding: 1.5rem; }
        .modal-themed .modal-footer { border-top: 1px solid rgba(0,0,0,0.1); padding: 1rem 1.5rem; background-color: rgba(0,0,0,0.05); border-radius: 0 0 1rem 1rem; }
        .modal-themed .form-label { font-weight: 500; color: #333; margin-bottom: 0.5rem; }
        .modal-themed .form-control { background-color: rgba(255,255,255,0.8); border-color: rgba(0,0,0,0.15); color: #333; }
        .modal-themed .form-control:focus { border-color: #1a2a4b; box-shadow: 0 0 0 0.25rem rgba(26, 42, 75, 0.25); background-color: #fff; }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container { margin-top: 20px; padding: 10px; }
            .page-header h1 { font-size: 2em; flex-direction: column; gap: 5px; }
            .welcome-info-block { width: 100%; text-align: center; }
            .attendance-table th, .attendance-table td { padding: 8px 10px; font-size: 0.85rem; }
            .student-avatar { width: 30px; height: 30px; margin-right: 5px; }
            .badge-edit-reason { margin-left: 5px; padding: 0.2em 0.4em; }
            .status-select { padding: 0.4rem 0.6rem; font-size: 0.85rem; }
            .attendance-buttons { flex-direction: column; gap: 10px; }
            .attendance-buttons .btn { width: 100%; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <header class="page-header">
        <h1 class="page-title">
            <i class="fas fa-user-check"></i> Mark Daily Attendance
        </h1>
        <div class="welcome-info-block">
            <p class="welcome-info">
                Class: <strong><?php echo htmlspecialchars($class_teacher_info['class_name'] . ' - ' . $class_teacher_info['section_name']); ?></strong> | Date: <strong><?php echo date('F d, Y', strtotime($attendance_date)); ?></strong>
            </p>
        </div>
    </header>

    <?php if ($message): ?>
    <div class="flash-message">
        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if (!$class_teacher_info): ?>
        <div class="info-card">
            <i class="fas fa-exclamation-triangle icon"></i>
            <h2>Access Denied</h2>
            <p>You are not assigned as a Class Teacher. Only Class Teachers can mark attendance.</p>
            <p><a href="teacher_dashboard.php" class="btn btn-themed-primary mt-3">Back to Dashboard</a></p>
        </div>
    <?php elseif (empty($students)): ?>
        <div class="info-card">
            <i class="fas fa-info-circle icon"></i>
            <h2>No Students Found</h2>
            <p>There are no students assigned to your class.</p>
            <p><a href="teacher_dashboard.php" class="btn btn-themed-primary mt-3">Back to Dashboard</a></p>
        </div>
    <?php else: ?>
        <div class="dashboard-panel">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <h3 class="panel-header mb-0">Student List</h3>
                <div class="text-end">
                    <?php if ($attendance_marked_time): ?>
                        <p class="text-muted mb-0">Last Updated: <?php echo date('h:i:s A', strtotime($attendance_marked_time)); ?></p>
                        <?php if($is_locked): ?>
                            <p class="lock-status locked"><i class="fas fa-lock"></i> Edits Locked</p>
                        <?php else: ?>
                            <p class="lock-status unlocked"><i class="fas fa-lock-open"></i> Edits Unlocked (<?php echo 10 - floor($time_diff_minutes); ?> min left)</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="lock-status unlocked"><i class="fas fa-info-circle"></i> Attendance not marked yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <form id="attendanceForm" method="POST">
                <?php if (!$is_locked): ?>
                <div class="d-flex gap-3 mb-4 attendance-buttons">
                    <button type="button" class="btn btn-themed-primary flex-grow-1" onclick="markAll('Present')">
                        <i class="fas fa-check-circle me-2"></i> Mark All Present
                    </button>
                    <button type="button" class="btn btn-themed-secondary flex-grow-1" onclick="markAll('Absent')">
                        <i class="fas fa-times-circle me-2"></i> Mark All Absent
                    </button>
                </div>
                <?php endif; ?>

                <div class="attendance-table-wrapper">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Roll No.</th>
                                <th style="width: 50%;">Student Name</th>
                                <th class="text-center" style="width: 20%;">Status</th>
                                <?php if ($is_locked): ?>
                                    <th class="text-center" style="width: 20%;">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td class="font-semibold"><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                    <td class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($student['image_url'] ?? '../assets/images/default-avatar.png'); ?>" class="student-avatar">
                                        <span><?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name'])); ?></span>
                                        <?php if ($student['edit_reason']): ?>
                                            <span class="badge-edit-reason" title="Edit Reason: <?php echo htmlspecialchars($student['edit_reason']); ?>">
                                                <i class="fas fa-info-circle me-1"></i> Edited
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <select name="students[<?php echo $student['id']; ?>]" class="status-select" <?php if($is_locked) echo 'disabled'; ?>>
                                            <option value="Present" <?php if($student['status'] == 'Present') echo 'selected'; ?> class="bg-green-600">Present</option>
                                            <option value="Absent" <?php if($student['status'] == 'Absent' || !$student['status']) echo 'selected'; ?> class="bg-red-600">Absent</option>
                                            <option value="Late" <?php if($student['status'] == 'Late') echo 'selected'; ?> class="bg-yellow-600">Late</option>
                                            <option value="Half Day" <?php if($student['status'] == 'Half Day') echo 'selected'; ?> class="bg-blue-600">Half Day</option>
                                        </select>
                                    </td>
                                    <?php if ($is_locked): ?>
                                        <td class="text-center">
                                            <button type="button" onclick="openEditModal('<?php echo $student['id']; ?>', '<?php echo htmlspecialchars(addslashes(trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']))); ?>', '<?php echo htmlspecialchars($student['status']); ?>', '<?php echo htmlspecialchars(addslashes($student['edit_reason'] ?? '')); ?>')" class="btn btn-sm-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if(!$is_locked): ?>
                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-themed-primary">
                        <i class="fas fa-save me-2"></i> Save Attendance
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Reason Modal -->
<div class="modal fade modal-themed" id="editReasonModal" tabindex="-1" aria-labelledby="editReasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editReasonForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editReasonModalLabel">Reason for Late Edit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="studentNameForEdit" class="text-muted mb-3"></p>
                    <input type="hidden" name="student_id_for_edit" id="student_id_for_edit">
                    
                    <div class="mb-3">
                        <label for="new_status" class="form-label">New Status</label>
                        <select id="new_status" name="new_status" class="form-control status-select">
                            <option value="Present" class="bg-green-600">Present</option>
                            <option value="Absent" class="bg-red-600">Absent</option>
                            <option value="Late" class="bg-yellow-600">Late</option>
                            <option value="Half Day" class="bg-blue-600">Half Day</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_reason" class="form-label">Reason (Required)</label>
                        <textarea name="edit_reason" id="edit_reason" rows="3" required class="form-control" placeholder="e.g., Parent called to inform..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary-themed" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-themed-warning">
                        <i class="fas fa-save me-2"></i> Submit Edit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function markAll(status) {
        const selects = document.querySelectorAll('select[name^="students["]');
        selects.forEach(select => {
            select.value = status;
        });
    }

    function openEditModal(studentId, studentName, currentStatus, editReason) {
        document.getElementById('student_id_for_edit').value = studentId;
        document.getElementById('studentNameForEdit').textContent = `Editing for: ${studentName}`;
        document.getElementById('new_status').value = currentStatus; // Pre-fill with current status
        document.getElementById('edit_reason').value = editReason; // Pre-fill with existing reason

        const editModal = new bootstrap.Modal(document.getElementById('editReasonModal'));
        editModal.show();
    }
    
    document.getElementById('editReasonForm').addEventListener('submit', function(event) {
        event.preventDefault();
        
        const studentId = this.querySelector('#student_id_for_edit').value;
        const newStatus = this.querySelector('#new_status').value;
        const reason = this.querySelector('#edit_reason').value;

        // Ensure reason is provided for a late edit
        if (!reason.trim()) {
            alert('Reason is required for editing attendance after the initial window.');
            return;
        }

        const formData = new FormData();
        formData.append(`students[${studentId}]`, newStatus);
        formData.append('student_id_for_edit', studentId);
        formData.append('edit_reason', reason);

        fetch('teacher_attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if(response.ok) {
                // If successful, close modal and reload page to reflect changes
                const editModal = bootstrap.Modal.getInstance(document.getElementById('editReasonModal'));
                editModal.hide();
                window.location.reload();
            } else {
                alert('An error occurred while submitting the edit. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('A network error occurred while submitting the edit.');
        });
    });
</script>

</body>
</html>

<?php require_once './teacher_footer.php'; ?>