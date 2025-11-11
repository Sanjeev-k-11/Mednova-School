<?php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// Initialize variables for the form
$class_name = $section_name = "";
$edit_id = 0;
$errors = [];

// --- ACTION HANDLER: Process all form submissions (Create, Update, Delete) ---

// HANDLE CREATE & UPDATE (POST Request)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class_name = trim($_POST['class_name']);
    $section_name = trim($_POST['section_name']);
    $edit_id = (int)$_POST['id'];

    // Validation
    if (empty($class_name)) $errors[] = "Class Name is required.";
    if (empty($section_name)) $errors[] = "Section Name is required.";

    // If validation passes, check for duplicates
    if (empty($errors)) {
        // SQL to check if the class/section combo already exists (excluding the one we might be editing)
        $sql_check = "SELECT id FROM classes WHERE class_name = ? AND section_name = ? AND id != ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "ssi", $class_name, $section_name, $edit_id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $errors[] = "This Class and Section combination already exists.";
            }
            mysqli_stmt_close($stmt_check);
        }
    }

    if (empty($errors)) {
        if ($edit_id > 0) {
            // --- UPDATE operation ---
            $sql = "UPDATE classes SET class_name = ?, section_name = ? WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssi", $class_name, $section_name, $edit_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['message'] = "Class updated successfully!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error updating class.";
                    $_SESSION['message_type'] = "danger";
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            // --- CREATE operation ---
            $sql = "INSERT INTO classes (class_name, section_name) VALUES (?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ss", $class_name, $section_name);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['message'] = "New class created successfully!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error creating class.";
                    $_SESSION['message_type'] = "danger";
                }
                mysqli_stmt_close($stmt);
            }
        }
        // Redirect to the same page to prevent form re-submission on refresh
        header("location: manage_classes.php");
        exit;
    }
}

// HANDLE DELETE (GET Request)
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $sql = "DELETE FROM classes WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Class deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting class.";
            $_SESSION['message_type'] = "danger";
        }
        mysqli_stmt_close($stmt);
        header("location: manage_classes.php");
        exit;
    }
}

// HANDLE EDIT (GET Request to populate the form)
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $sql = "SELECT * FROM classes WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $edit_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($class_to_edit = mysqli_fetch_assoc($result)) {
            $class_name = $class_to_edit['class_name'];
            $section_name = $class_to_edit['section_name'];
        }
        mysqli_stmt_close($stmt);
    }
}

// --- DATA DISPLAY: Fetch all classes to show in the table ---
$all_classes = [];
$sql_fetch = "SELECT * FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_fetch)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_classes[] = $row;
    }
    mysqli_free_result($result);
}
mysqli_close($link);

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Classes & Sections</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;  background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container-wrapper { display: flex; gap: 30px; margin-bottom: 100px; max-width: 1200px; margin: auto; margin-top: 100px; align-items: flex-start; }
        .form-container, .table-container { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 30px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        .form-container { flex: 1; min-width: 300px; }
        .table-container { flex: 2; }
        h2 { text-align: center; color: #1a2c5a; font-weight: 700; margin-top: 0; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(-45deg, #007bff, #00bfff, #8a2be2, #007bff); background-size: 400% 400%; animation: gradientAnimation 8s ease infinite; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        .data-table thead th { background-color: #1a2c5a; color: white; }
        .btn-action { padding: 5px 10px; margin-right: 5px; border-radius: 5px; color: white; text-decoration: none; font-size: 14px; }
        .btn-edit { background-color: #ffc107; color: #212529; }
        .btn-delete { background-color: #dc3545; }
    </style>
</head>
<body>
<div class="container-wrapper">
    <!-- FORM CONTAINER for Create and Edit -->
    <div class="form-container">
        <h2><?php echo ($edit_id > 0) ? 'Edit Class' : 'Add New Class'; ?></h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul><?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?></ul></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
            <div class="form-group">
                <label for="class_name">Class Name (e.g., Class 10, UKG)</label>
                <input type="text" name="class_name" id="class_name" value="<?php echo htmlspecialchars($class_name); ?>" required>
            </div>
            <div class="form-group">
                <label for="section_name">Section (e.g., A, B, Rose)</label>
                <input type="text" name="section_name" id="section_name" value="<?php echo htmlspecialchars($section_name); ?>" required>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="<?php echo ($edit_id > 0) ? 'Update Class' : 'Add Class'; ?>">
            </div>
        </form>
    </div>

    <!-- TABLE CONTAINER for Viewing -->
    <div class="table-container">
        <h2>Existing Classes</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
            </div>
        <?php endif; ?>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Class Name</th>
                    <th>Section</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_classes)): ?>
                    <tr><td colspan="3" style="text-align:center;">No classes have been added yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($all_classes as $class): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                        <td><?php echo htmlspecialchars($class['section_name']); ?></td>
                        <td>
                            <a href="manage_classes.php?edit_id=<?php echo $class['id']; ?>" class="btn-action btn-edit">Edit</a>
                            <a href="manage_classes.php?delete_id=<?php echo $class['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this class? This cannot be undone.');">Delete</a>
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