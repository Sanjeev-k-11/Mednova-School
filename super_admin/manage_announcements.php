<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}

// --- ACTION HANDLER ---
$errors = [];
$title_form = $content_form = $posted_by_form = "";
$edit_id = 0;

// --- HANDLE POST for Create/Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'manage_announcement') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $posted_by = trim($_POST['posted_by']);
    $edit_id = (int)$_POST['id'];

    if (empty($title)) $errors[] = "Title is required.";
    if (empty($content)) $errors[] = "Content is required.";
    if (empty($posted_by)) $posted_by = "Admin Office"; // Default value

    if (empty($errors)) {
        if ($edit_id > 0) { // Update
            $sql = "UPDATE announcements SET title = ?, content = ?, posted_by = ? WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "sssi", $title, $content, $posted_by, $edit_id);
        } else { // Create
            $sql = "INSERT INTO announcements (title, content, posted_by) VALUES (?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "sss", $title, $content, $posted_by);
        }
        if (isset($stmt)) {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['message'] = $edit_id > 0 ? "Announcement updated successfully." : "Announcement posted successfully.";
            $_SESSION['message_type'] = "success";
            header("location: manage_announcements.php"); exit;
        }
    } else {
        // Repopulate form on error
        $title_form = $title;
        $content_form = $content;
        $posted_by_form = $posted_by;
        $edit_id = $edit_id;
    }
}

// --- HANDLE GET for Delete, Toggle Status, Edit ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Delete
    if (isset($_GET['delete_id'])) {
        $delete_id = (int)$_GET['delete_id'];
        $sql = "DELETE FROM announcements WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $delete_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['message'] = "Announcement deleted."; $_SESSION['message_type'] = "success";
            header("location: manage_announcements.php"); exit;
        }
    }
    // Toggle Status
    if (isset($_GET['toggle_id'])) {
        $toggle_id = (int)$_GET['toggle_id'];
        $sql = "UPDATE announcements SET is_active = !is_active WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $toggle_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("location: manage_announcements.php"); exit;
        }
    }
    // Populate form for editing
    if (isset($_GET['edit_id'])) {
        $edit_id = (int)$_GET['edit_id'];
        $sql = "SELECT * FROM announcements WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $edit_id);
            mysqli_stmt_execute($stmt);
            if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                $title_form = $row['title'];
                $content_form = $row['content'];
                $posted_by_form = $row['posted_by'];
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// --- DATA FETCHING ---
$all_announcements = [];
$sql_fetch = "SELECT * FROM announcements ORDER BY created_at DESC";
if ($result = mysqli_query($link, $sql_fetch)) {
    while ($row = mysqli_fetch_assoc($result)) $all_announcements[] = $row;
}
mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Announcements</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1200px; margin: auto; margin-top: 100px; margin-bottom: 100px; }
        .form-container, .table-container { background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); padding: 30px; border-radius: 20px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border: 1px solid rgba(255, 255, 255, 0.18); }
        .table-container { margin-top: 30px; }
        h2 { text-align: center; color: #fff; font-weight: 600; margin-bottom: 30px; }
        h3 { color: #1e2a4c; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #1e2a4c; }
        input[type=text], textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; background-color: rgba(255,255,255,0.8); }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(45deg, #6a82fb, #fc5c7d); }
        .data-table { width: 100%; border-collapse: collapse; background-color: #fff; border-radius: 10px; overflow: hidden;}
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eef2f7; }
        .data-table thead th { background-color: #1e2a4c; color: white; }
        .btn-action { padding: 6px 12px; margin-right: 5px; border-radius: 5px; color: white; text-decoration: none; font-size: 14px; border: none; cursor: pointer;}
        .btn-edit { background-color: #ffc107; color: #212529; }
        .btn-delete { background-color: #dc3545; }
        .btn-activate { background-color: #28a745; }
        .btn-deactivate { background-color: #6c757d; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #6c757d; font-weight: bold; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
        .alert-danger { color: #721c24; background-color: #f8d7da; }
    </style>
</head>
<body>
<div class="container">
    <h2>Manage Announcements</h2>

    <div class="form-container">
        <h3><?php echo ($edit_id > 0) ? 'Edit Announcement' : 'Create New Announcement'; ?></h3>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul><?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?></ul></div>
        <?php endif; ?>

        <form action="manage_announcements.php" method="post">
            <input type="hidden" name="action" value="manage_announcement">
            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($title_form); ?>" required>
            </div>
            <div class="form-group">
                <label for="content">Content</label>
                <textarea name="content" id="content" rows="4" required><?php echo htmlspecialchars($content_form); ?></textarea>
            </div>
            <div class="form-group">
                <label for="posted_by">Posted By (e.g., Principal's Office)</label>
                <input type="text" name="posted_by" id="posted_by" value="<?php echo htmlspecialchars($posted_by_form); ?>" placeholder="Admin Office">
            </div>
            <input type="submit" class="btn" value="<?php echo ($edit_id > 0) ? 'Update Announcement' : 'Post Announcement'; ?>">
        </form>
    </div>

    <div class="table-container">
        <h3>All Announcements</h3>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>"><?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
        <?php endif; ?>

        <table class="data-table">
            <thead><tr><th>Title</th><th>Posted By</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($all_announcements)): ?>
                    <tr><td colspan="5" style="text-align:center;">No announcements found.</td></tr>
                <?php else: ?>
                    <?php foreach ($all_announcements as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['title']); ?></strong><p style="font-size: 0.9em; color: #555; margin: 5px 0 0;"><?php echo htmlspecialchars(substr($item['content'], 0, 100)) . '...'; ?></p></td>
                        <td><?php echo htmlspecialchars($item['posted_by']); ?></td>
                        <td><?php echo date("M j, Y", strtotime($item['created_at'])); ?></td>
                        <td><span class="status-<?php echo $item['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td style="white-space: nowrap;">
                            <a href="?edit_id=<?php echo $item['id']; ?>" class="btn-action btn-edit">Edit</a>
                            <?php if ($item['is_active']): ?>
                                <a href="?toggle_id=<?php echo $item['id']; ?>" class="btn-action btn-deactivate">Deactivate</a>
                            <?php else: ?>
                                <a href="?toggle_id=<?php echo $item['id']; ?>" class="btn-action btn-activate">Activate</a>
                            <?php endif; ?>
                            <a href="?delete_id=<?php echo $item['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to permanently delete this announcement?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php require_once './admin_footer.php'; ?>