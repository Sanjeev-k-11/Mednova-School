<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"]; // Primary key ID of the logged-in principal
$principal_full_name = $_SESSION["full_name"]; // Full name of the logged-in principal
$principal_role = $_SESSION["role"]; // Role will be 'Principle'

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Process Block/Unblock Request ---
if (isset($_POST['action']) && ($_POST['action'] == 'block_teacher' || $_POST['action'] == 'unblock_teacher')) {
    $teacher_id = (int)$_POST['teacher_id'];
    $reason = trim($_POST['reason']);
    $action_type = $_POST['action']; // 'block_teacher' or 'unblock_teacher'

    if (empty($teacher_id)) {
        set_session_message("Invalid teacher ID for action.", "danger");
        header("location: manage_teachers.php");
        exit;
    }

    if (empty($reason)) {
        set_session_message("Reason is required for " . ($action_type == 'block_teacher' ? "blocking" : "unblocking") . ".", "danger");
        header("location: manage_teachers.php");
        exit;
    }

    if ($action_type == 'block_teacher') {
        $sql = "UPDATE teachers SET is_blocked = 1, block_reason = ?, blocked_by_user_id = ?, blocked_by_user_role = ?, blocked_at = NOW(), unblock_reason = NULL, unblocked_by_user_id = NULL, unblocked_by_user_role = NULL, unblocked_at = NULL WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sisi", $reason, $principal_id, $principal_role, $teacher_id);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Teacher blocked successfully.", "success");
            } else {
                set_session_message("Error blocking teacher: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action_type == 'unblock_teacher') {
        $sql = "UPDATE teachers SET is_blocked = 0, unblock_reason = ?, unblocked_by_user_id = ?, unblocked_by_user_role = ?, unblocked_at = NOW(), block_reason = NULL, blocked_by_user_id = NULL, blocked_by_user_role = NULL, blocked_at = NULL WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sisi", $reason, $principal_id, $principal_role, $teacher_id);
            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Teacher unblocked successfully.", "success");
            } else {
                set_session_message("Error unblocking teacher: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    }
    header("location: manage_teachers.php");
    exit;
}

// --- Process Delete Request (Principal can still delete if allowed by FK rules) ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid teacher ID for deletion.", "danger");
        header("location: manage_teachers.php");
        exit;
    }

    $sql = "DELETE FROM teachers WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                 set_session_message("Teacher deleted successfully. Note: Related records (e.g., classes, timetable) might have been affected based on foreign key rules.", "success");
            } else {
                 set_session_message("Teacher not found or already deleted.", "danger");
            }
        } else {
            if (mysqli_errno($link) == 1451) { // Foreign key constraint error
                set_session_message("Cannot delete teacher. This teacher is currently assigned to a class, subject, timetable, or other records. Please reassign/remove related records first.", "danger");
            } else {
                set_session_message("Error deleting teacher: " . mysqli_error($link), "danger");
            }
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_teachers.php");
    exit;
}


// --- Fetch all Teachers for Display ---
$teachers = [];
$sql_fetch_teachers = "SELECT 
                        t.id, t.teacher_code, t.full_name, t.email, t.phone_number, t.address, 
                        t.pincode, t.image_url, t.salary, t.qualification, t.subject_taught, 
                        t.years_of_experience, t.gender, t.blood_group, t.dob, t.date_of_joining, 
                        t.van_service_taken, t.is_blocked, t.block_reason, t.blocked_at,
                        t.unblock_reason, t.unblocked_at,
                        v.van_number, d.department_name
                       FROM teachers t
                       LEFT JOIN vans v ON t.van_id = v.id
                       LEFT JOIN departments d ON t.department_id = d.id
                       ORDER BY t.full_name ASC";
if ($result = mysqli_query($link, $sql_fetch_teachers)) {
    $teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching teachers: " . mysqli_error($link);
    $message_type = "danger";
}

mysqli_close($link);

// --- Retrieve and clear session messages ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- PAGE INCLUDES ---
require_once './principal_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #FFD700, #FFA500, #FF6347, #FF4500); /* Golden to Orange gradient */
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            color: #333;
        }
        @keyframes gradientAnimation {
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.95); /* Slightly transparent white */
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        h2 {
            color: #d17a00; /* Darker golden-orange */
            margin-bottom: 30px;
            border-bottom: 2px solid #ffcc66;
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 2.2em;
            font-weight: 700;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Teacher List Table */
        h3 {
            color: #495057;
            margin-top: 40px;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .teacher-list-table {
            width: 100%;
            border-collapse: separate; /* For rounded corners */
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden; /* Ensures rounded corners are visible */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .teacher-list-table th, .teacher-list-table td {
            border-bottom: 1px solid #dee2e6;
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }
        .teacher-list-table th {
            background-color: #f1f3f5;
            color: #343a40;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .teacher-list-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .teacher-list-table tr:hover {
            background-color: #e9eff5;
        }
        .action-buttons-group {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            border: 1px solid transparent; /* default border */
        }
        .btn-delete {
            background-color: #dc3545; /* Red */
            color: #fff;
            border-color: #dc3545;
        }
        .btn-delete:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .btn-block {
            background-color: #6c757d; /* Grey */
            color: #fff;
            border-color: #6c757d;
        }
        .btn-block:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .btn-unblock {
            background-color: #28a745; /* Green */
            color: #fff;
            border-color: #28a745;
        }
        .btn-unblock:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .text-center {
            text-align: center;
        }
        .teacher-image-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            vertical-align: middle;
            margin-right: 10px;
            border: 2px solid #ddd;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-blocked {
            background-color: #f8d7da;
            color: #721c24;
        }
        .notes-text {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
            display: block;
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 100; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 30px;
            border: 1px solid #888;
            width: 80%; /* Could be more responsive */
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        .modal-header {
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h4 {
            margin: 0;
            color: #333;
            font-size: 1.5em;
        }
        .close-btn {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: #000;
            text-decoration: none;
        }
        .modal-body .form-group label {
            width: auto; /* Override fixed label width for modal */
        }
        .modal-body textarea {
            min-height: 100px;
            resize: vertical;
        }
        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }
        .btn-modal-submit {
            background-color: #FF8C00;
            color: white;
        }
        .btn-modal-submit:hover {
            background-color: #FF6347;
        }
        .btn-modal-cancel {
            background-color: #6c757d;
            color: white;
        }
        .btn-modal-cancel:hover {
            background-color: #5a6268;
        }


        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            .teacher-list-table th, .teacher-list-table td {
                padding: 10px;
                font-size: 0.9rem;
            }
            .teacher-list-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap; /* Prevent wrapping of table content */
            }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Teacher List Table -->
        <h3><i class="fas fa-list"></i> Teacher Overview</h3>
        <?php if (empty($teachers)): ?>
            <p class="text-center text-muted">No teachers found.</p>
        <?php else: ?>
            <div style="overflow-x:auto;"> <!-- Makes table scrollable on small screens -->
                <table class="teacher-list-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Dept.</th>
                            <th>Van</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars($teacher['image_url'] ?: '../assets/images/default_profile.png'); ?>" alt="Teacher Image" class="teacher-image-sm">
                                </td>
                                <td><?php echo htmlspecialchars($teacher['teacher_code']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['subject_taught']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['department_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['van_number'] ?: 'No Service'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo ($teacher['is_blocked'] == 1 ? 'status-blocked' : 'status-active'); ?>">
                                        <?php echo ($teacher['is_blocked'] == 1 ? 'Blocked' : 'Active'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($teacher['is_blocked'] == 1 && !empty($teacher['block_reason'])): ?>
                                        <span class="notes-text">Blocked: <?php echo htmlspecialchars($teacher['block_reason']); ?> (<?php echo date('M j, Y', strtotime($teacher['blocked_at'])); ?>)</span>
                                    <?php elseif ($teacher['is_blocked'] == 0 && !empty($teacher['unblock_reason'])): ?>
                                        <span class="notes-text">Unblocked: <?php echo htmlspecialchars($teacher['unblock_reason']); ?> (<?php echo date('M j, Y', strtotime($teacher['unblocked_at'])); ?>)</span>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="action-buttons-group">
                                        <?php if ($teacher['is_blocked'] == 0): ?>
                                            <button class="btn btn-block" onclick="showReasonModal(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['full_name']); ?>', 'block')">
                                                <i class="fas fa-user-lock"></i> Block
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-unblock" onclick="showReasonModal(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['full_name']); ?>', 'unblock')">
                                                <i class="fas fa-user-check"></i> Unblock
                                            </button>
                                        <?php endif; ?>
                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['full_name']); ?>')" class="btn btn-delete">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Block/Unblock Reason Modal -->
<div id="reasonModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="modal-title"></h4>
      <span class="close-btn" onclick="closeReasonModal()">&times;</span>
    </div>
    <form id="reasonForm" action="manage_teachers.php" method="POST">
      <input type="hidden" name="action" id="modal-action">
      <input type="hidden" name="teacher_id" id="modal-teacher-id">
      <div class="modal-body">
        <div class="form-group">
          <label for="reason-text">Reason:</label>
          <textarea id="reason-text" name="reason" rows="4" required placeholder="Enter reason for this action"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-modal-cancel" onclick="closeReasonModal()">Cancel</button>
        <button type="submit" class="btn btn-modal-submit" id="modal-submit-btn">Submit</button>
      </div>
    </form>
  </div>
</div>

<script>
    const reasonModal = document.getElementById('reasonModal');
    const modalTitle = document.getElementById('modal-title');
    const modalAction = document.getElementById('modal-action');
    const modalTeacherId = document.getElementById('modal-teacher-id');
    const reasonText = document.getElementById('reason-text');
    const modalSubmitBtn = document.getElementById('modal-submit-btn');

    function showReasonModal(teacherId, teacherName, action) {
        reasonText.value = ''; // Clear previous reason
        modalTeacherId.value = teacherId;

        if (action === 'block') {
            modalTitle.innerHTML = `<i class="fas fa-user-lock"></i> Block Teacher: ${teacherName}`;
            modalAction.value = 'block_teacher';
            reasonText.placeholder = 'e.g., Poor performance, violation of school policy, long leave.';
            modalSubmitBtn.innerHTML = 'Block Teacher';
            modalSubmitBtn.classList.remove('btn-modal-submit-unblock');
            modalSubmitBtn.classList.add('btn-modal-submit');

        } else if (action === 'unblock') {
            modalTitle.innerHTML = `<i class="fas fa-user-check"></i> Unblock Teacher: ${teacherName}`;
            modalAction.value = 'unblock_teacher';
            reasonText.placeholder = 'e.g., Performance improved, issue resolved, return from leave.';
            modalSubmitBtn.innerHTML = 'Unblock Teacher';
            modalSubmitBtn.classList.remove('btn-modal-submit');
            modalSubmitBtn.classList.add('btn-modal-submit-unblock'); // Custom class for unblock button if needed
        }
        reasonModal.style.display = 'flex'; // Show modal
    }

    function closeReasonModal() {
        reasonModal.style.display = 'none'; // Hide modal
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == reasonModal) {
            closeReasonModal();
        }
    }

    // Function for delete confirmation
    function confirmDelete(id, name) {
        if (confirm(`Are you sure you want to permanently delete teacher "${name}"? This action cannot be undone and may affect related records (e.g., classes, timetables, assignments, etc.).`)) {
            window.location.href = `manage_teachers.php?delete_id=${id}`;
        }
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>