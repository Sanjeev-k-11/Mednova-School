<?php
// Start the session
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

// --- Fetch All Staff Data ---
$all_staff = [];
// This SQL query is updated to match your schema with 'is_blocked'.
// We alias 'is_blocked' to a more friendly 'status' for use in the PHP code.
$sql = "
    (SELECT 
        id, 
        principle_code AS staff_code, 
        full_name, 
        email, 
        phone_number, 
        image_url, 
        'Principal' AS role, 
        is_blocked AS status, 
        date_of_joining, 
        NULL AS subject_taught
    FROM principles)
    UNION ALL
    (SELECT 
        id, 
        teacher_code AS staff_code, 
        full_name, 
        email, 
        phone_number, 
        image_url, 
        'Teacher' AS role, 
        is_blocked AS status, 
        date_of_joining, 
        subject_taught
    FROM teachers)
    ORDER BY full_name ASC
";

if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_staff[] = $row;
    }
    mysqli_free_result($result);
} else {
    // A simple way to see errors during development
    die("Error executing query: " . mysqli_error($link));
}
mysqli_close($link);

// Include the header
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View All Staff</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1400px; margin: auto; margin-bottom: 100px; margin-top: 100px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 30px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        h2 { text-align: center; color: #1a2c5a; font-weight: 700; margin-bottom: 25px; }

        .table-container { overflow-x: auto; }
        .staff-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .staff-table th, .staff-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: middle; }
        .staff-table thead th { background-color: #1a2c5a; color: white; font-weight: 600; white-space: nowrap; }
        .staff-table tbody tr:hover { background-color: #f1f1f1; }
        .profile-img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
        
        .status-badge { padding: 5px 10px; border-radius: 12px; color: white; font-size: 0.8em; font-weight: bold; }
        .status-active { background-color: #28a745; }
        .status-blocked { background-color: #dc3545; }
        
        .role-badge { padding: 5px 10px; border-radius: 12px; color: white; font-size: 0.8em; font-weight: bold; }
        .role-principal { background-color: #e73c7e; }
        .role-teacher { background-color: #23a6d5; }

        .actions-cell { white-space: nowrap; }
        .btn-action {
            display: inline-block;
            padding: 6px 12px;
            margin-right: 5px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        .btn-view { background-color: #17a2b8; }
        .btn-edit { background-color: #ffc107; color: #212529;}
        .btn-block { background-color: #dc3545; }
        .btn-unblock { background-color: #28a745; }
        .no-staff-message { text-align: center; padding: 50px; font-size: 1.2em; color: #777; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <h2>All Staff Members</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; ?>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); // Clear the message after displaying ?>
    <?php endif; ?>

    <div class="table-container">
        <?php if (empty($all_staff)): ?>
            <p class="no-staff-message">No staff members have been added yet.</p>
        <?php else: ?>
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>Staff Code</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_staff as $staff): ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars($staff['image_url'] ?? '../assets/images/default_avatar.png'); ?>" alt="Profile" class="profile-img"></td>
                            <td><?php echo htmlspecialchars($staff['staff_code']); ?></td>
                            <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                            <td>
                                <?php $role_class = strtolower($staff['role']); ?>
                                <span class="role-badge role-<?php echo $role_class; ?>"><?php echo htmlspecialchars($staff['role']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($staff['subject_taught'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                    // The 'status' field here comes from the 'is_blocked' column in your DB
                                    // 0 = false (not blocked), 1 = true (blocked)
                                    $is_blocked = $staff['status']; 
                                    $status_text = $is_blocked ? 'Blocked' : 'Active';
                                    $status_class = $is_blocked ? 'blocked' : 'active';
                                ?>
                                <span class="status-badge status-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </td>
                            <td class="actions-cell">
                                <a href="view_staff_details.php?role=<?php echo $staff['role']; ?>&id=<?php echo $staff['id']; ?>" class="btn-action btn-view" title="View Details">View</a>
                                <a href="edit_staff.php?role=<?php echo $staff['role']; ?>&id=<?php echo $staff['id']; ?>" class="btn-action btn-edit" title="Edit Staff">Edit</a>
                                
                                <!-- This form handles the block/unblock action -->
                                <form method="POST" action="update_staff_status.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                                    <input type="hidden" name="role" value="<?php echo $staff['role']; ?>">
                                    <?php if (!$is_blocked): ?>
                                        <!-- If NOT blocked, show the "Block" button -->
                                        <input type="hidden" name="new_status" value="1"> <!-- 1 means block -->
                                        <button type="submit" class="btn-action btn-block" title="Block User">Block</button>
                                    <?php else: ?>
                                        <!-- If blocked, show the "Unblock" button -->
                                        <input type="hidden" name="new_status" value="0"> <!-- 0 means unblock -->
                                        <button type="submit" class="btn-action btn-unblock" title="Unblock User">Unblock</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
<?php 
require_once './admin_footer.php';
?>