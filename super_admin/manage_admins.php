<?php
require_once '../database/config.php';
session_start();

// Ensure only Super Admin can access
if (!isset($_SESSION['super_admin_id'])) {
    die("Access denied. Please login as Super Admin.");
}

$message = "";
$message_type = "";

// --- Handle Admin Deletion ---
if (isset($_POST['delete_admin_id']) && !empty($_POST['delete_admin_id'])) {
    $admin_id_to_delete = $_POST['delete_admin_id'];

    // First, get the image_url to delete the file from the server
    $sql_select_image = "SELECT image_url FROM admins WHERE admin_id = ?";
    if ($stmt_select = mysqli_prepare($link, $sql_select_image)) {
        mysqli_stmt_bind_param($stmt_select, "i", $admin_id_to_delete);
        mysqli_stmt_execute($stmt_select);
        mysqli_stmt_bind_result($stmt_select, $image_url_to_delete);
        mysqli_stmt_fetch($stmt_select);
        mysqli_stmt_close($stmt_select);

        // If an image exists, delete the file
        if ($image_url_to_delete && file_exists("../" . $image_url_to_delete)) {
            unlink("../" . $image_url_to_delete);
        }
    }

    // Now, delete the admin record from the database
    $sql_delete = "DELETE FROM admins WHERE admin_id = ?";

    if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
        mysqli_stmt_bind_param($stmt_delete, "i", $admin_id_to_delete);
        if (mysqli_stmt_execute($stmt_delete)) {
            $message = "Admin deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting admin: " . mysqli_stmt_error($stmt_delete);
            $message_type = "error";
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        $message = "Error preparing delete query: " . mysqli_error($link);
        $message_type = "error";
    }
}

// --- Fetch all Admins ---
$admins = [];
$sql_fetch = "SELECT admin_id, username, full_name, email, phone_number, address, gender, role, salary, qualification, join_date, dob, image_url 
              FROM admins ORDER BY admin_id DESC";

if ($result = mysqli_query($link, $sql_fetch)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $admins[] = $row;
    }
    mysqli_free_result($result);
} else {
    $message = "Error fetching admins: " . mysqli_error($link);
    $message_type = "error";
}

// Close database connection
mysqli_close($link);
?>

<?php require_once './admin_header.php'; ?>

<!-- Custom Styles for this page -->
<style>
    /* Reusing general styles from create_admin.php */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f7fa;
        color: #333;
        margin: 0;
        padding: 0;
    }

    .page-content-wrapper {
        padding: 20px;
        max-width: 1200px; /* Wider for table */
        margin: 20px auto;
        background-color: #ffffff;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .table-card {
        padding: 30px;
    }

    .table-card h2 {
        text-align: center;
        color: #007bff;
        margin-bottom: 30px;
        font-size: 2.2em;
        font-weight: 600;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 15px;
    }

    /* Message Styling */
    .message {
        padding: 12px 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        font-weight: bold;
        text-align: center;
        opacity: 0.95;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .message.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Table Styling */
    .admin-table-container {
        overflow-x: auto; /* Enables horizontal scrolling on small screens */
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .admin-table th,
    .admin-table td {
        border: 1px solid #e9ecef;
        padding: 12px 15px;
        text-align: left;
        vertical-align: middle;
    }

    .admin-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
        white-space: nowrap; /* Prevent headers from wrapping too much */
    }

    .admin-table tr:nth-child(even) {
        background-color: #fdfdfd;
    }

    .admin-table tr:hover {
        background-color: #e2f0ff;
        cursor: pointer;
    }

    .admin-table td img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #ddd;
    }

    /* Action Buttons */
    .action-buttons {
        white-space: nowrap; /* Keep buttons on one line */
    }

    .btn-action, .btn-delete {
        display: inline-block;
        padding: 8px 12px;
        border-radius: 5px;
        font-size: 0.9em;
        font-weight: 500;
        text-decoration: none;
        margin-right: 5px;
        transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }

    .btn-action {
        background-color: #28a745; /* Green for Edit */
        color: white;
        border: 1px solid #28a745;
    }

    .btn-action:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }

    .btn-delete {
        background-color: #dc3545; /* Red for Delete */
        color: white;
        border: 1px solid #dc3545;
    }

    .btn-delete:hover {
        background-color: #c82333;
        border-color: #bd2130;
    }

    .create-admin-btn-container {
        text-align: right;
        margin-bottom: 20px;
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 600;
        transition: background-color 0.3s ease;
    }

    .btn-primary:hover {
        background-color: #0056b3;
    }
</style>

<div class="page-content-wrapper">
    <div class="table-card">
        <h2>Manage Administrators</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="create-admin-btn-container">
            <a href="create_admin.php" class="btn-primary">Create New Admin</a>
        </div>

        <?php if (empty($admins)): ?>
            <p style="text-align: center; padding: 20px; color: #555;">No administrators found.</p>
        <?php else: ?>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Join Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['admin_id']); ?></td>
                                <td>
                                    <?php if ($admin['image_url']): ?>
                                        <img src="../<?php echo htmlspecialchars($admin['image_url']); ?>" alt="Profile Image">
                                    <?php else: ?>
                                        <img src="../uploads/default_admin.png" alt="No Image">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td><?php echo htmlspecialchars($admin['role']); ?></td>
                                <td><?php echo htmlspecialchars($admin['phone_number'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($admin['join_date'] ?: 'N/A'); ?></td>
                                <td class="action-buttons">
                                    <a href="edit_admin.php?id=<?php echo htmlspecialchars($admin['admin_id']); ?>" class="btn-action">Edit</a>
                                    <!-- Delete button using a form for POST request for security -->
                                    <form action="" method="POST" style="display:inline-block;">
                                        <input type="hidden" name="delete_admin_id" value="<?php echo htmlspecialchars($admin['admin_id']); ?>">
                                        <button type="submit" class="btn-delete" onclick="return confirm('Are you sure you want to delete this admin? This action cannot be undone.');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once './admin_footer.php'; ?>