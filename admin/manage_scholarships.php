<?php
// manage_scholarships.php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php"); exit;
}

$edit_id = 0; $errors = [];
$scholarship_name_form = $description_form = $type_form = "Fixed"; // Default type
$value_form = "";

// Handle Create/Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['scholarship_name']);
    $desc = trim($_POST['description']);
    $type = $_POST['type'];
    $value = isset($_POST['value']) ? (float)$_POST['value'] : 0;
    $edit_id = (int)$_POST['id'];

    // --- Validation ---
    if (empty($name)) $errors[] = "Scholarship Name is required.";
    if ($value <= 0) $errors[] = "Value must be a positive number.";
    if ($type == 'Percentage' && $value > 100) $errors[] = "Percentage value cannot exceed 100.";

    if (empty($errors)) {
        // Check for duplicate name before proceeding
        $sql_check = "SELECT id FROM scholarships WHERE scholarship_name = ? AND id != ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "si", $name, $edit_id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $errors[] = "This Scholarship Name already exists.";
            }
            mysqli_stmt_close($stmt_check);
        }
    }
    
    // --- **CORRECTED LOGIC**: Proceed only if there are still no errors ---
    if (empty($errors)) {
        $sql = "";
        if ($edit_id > 0) { // Prepare UPDATE SQL
            $sql = "UPDATE scholarships SET scholarship_name=?, description=?, type=?, value=? WHERE id=?";
        } else { // Prepare CREATE SQL
            $sql = "INSERT INTO scholarships (scholarship_name, description, type, value) VALUES (?, ?, ?, ?)";
        }

        // Prepare the statement. This is where the original error happened.
        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind parameters based on whether we are updating or inserting
            if ($edit_id > 0) {
                mysqli_stmt_bind_param($stmt, "sssdi", $name, $desc, $type, $value, $edit_id);
            } else {
                mysqli_stmt_bind_param($stmt, "sssd", $name, $desc, $type, $value);
            }

            // Execute the statement
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = $edit_id > 0 ? "Scholarship updated successfully." : "Scholarship created successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error executing statement: " . mysqli_stmt_error($stmt);
                $_SESSION['message_type'] = "danger";
            }
            // Close the statement
            mysqli_stmt_close($stmt);
        } else {
            // This error message will show if mysqli_prepare fails
            $_SESSION['message'] = "Error preparing statement: " . mysqli_error($link);
            $_SESSION['message_type'] = "danger";
        }
        
        header("location: manage_scholarships.php"); 
        exit;

    } else { // If there were validation errors, repopulate form
        $scholarship_name_form = $name; 
        $description_form = $desc; 
        $type_form = $type; 
        $value_form = $value; 
        $edit_id = $edit_id;
    }
}

// (The rest of your GET and data fetching logic is correct and does not need to be changed)
// ...
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $sql_unassign = "DELETE FROM student_scholarships WHERE scholarship_id = ?";
    if($stmt_unassign = mysqli_prepare($link, $sql_unassign)){ mysqli_stmt_bind_param($stmt_unassign, "i", $delete_id); mysqli_stmt_execute($stmt_unassign); mysqli_stmt_close($stmt_unassign); }
    $sql_del = "DELETE FROM scholarships WHERE id = ?";
    if ($stmt_del = mysqli_prepare($link, $sql_del)) { mysqli_stmt_bind_param($stmt_del, "i", $delete_id); mysqli_stmt_execute($stmt_del); mysqli_stmt_close($stmt_del); $_SESSION['message'] = "Scholarship deleted."; $_SESSION['message_type'] = "success"; }
    header("location: manage_scholarships.php"); exit;
}
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $sql_edit = "SELECT * FROM scholarships WHERE id = ?";
    if ($stmt_edit = mysqli_prepare($link, $sql_edit)) {
        mysqli_stmt_bind_param($stmt_edit, "i", $edit_id);
        mysqli_stmt_execute($stmt_edit);
        if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_edit))) {
            $scholarship_name_form = $row['scholarship_name']; $description_form = $row['description'];
            $type_form = $row['type']; $value_form = $row['value'];
        }
        mysqli_stmt_close($stmt_edit);
    }
}
$all_scholarships = [];
$sql_fetch = "SELECT * FROM scholarships ORDER BY scholarship_name ASC";
if ($result = mysqli_query($link, $sql_fetch)) {
    while ($row = mysqli_fetch_assoc($result)) $all_scholarships[] = $row;
}
mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Scholarships</title>
    <!-- (Your enhanced CSS from the previous response goes here) -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1200px; margin: auto; margin-top: 100px; margin-bottom: 100px;}
        .main-title { color: #fff; text-align: center; font-weight: 600; font-size: 2em; margin-bottom: 20px; }
        .container-wrapper { display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start; }
        .form-container, .table-container { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-container { flex: 1; min-width: 300px; }
        .table-container { flex: 2; }
        h3 { margin-top: 0; color: #1e2a4c; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        input[type=text], input[type=number], select, textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(45deg, #6a82fb, #fc5c7d); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eef2f7; }
        .data-table thead th { background-color: #f8f9fa; color: #343a40; }
        .btn-action { display: inline-block; padding: 8px 15px; margin-right: 5px; border-radius: 5px; color: white; text-decoration: none; font-size: 14px; }
        .btn-edit { background-color: #ffc107; color: #212529; }
        .btn-delete { background-color: #dc3545; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
        .alert-danger { color: #721c24; background-color: #f8d7da; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="main-title">Manage Scholarship Types</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <div class="container-wrapper">
        <div class="form-container">
            <h3><?php echo ($edit_id > 0) ? 'Edit Scholarship' : 'Add New Scholarship'; ?></h3>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><ul><?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?></ul></div>
            <?php endif; ?>
            <form action="manage_scholarships.php" method="post">
                <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                <div class="form-group">
                    <label for="scholarship_name">Scholarship Name</label>
                    <input type="text" name="scholarship_name" id="scholarship_name" value="<?php echo htmlspecialchars($scholarship_name_form); ?>" required>
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select name="type" id="type">
                        <option value="Fixed" <?php if($type_form == 'Fixed') echo 'selected'; ?>>Fixed Amount</option>
                        <option value="Percentage" <?php if($type_form == 'Percentage') echo 'selected'; ?>>Percentage</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="value">Value (Amount in ₹ or Percentage)</label>
                    <input type="number" name="value" id="value" step="0.01" value="<?php echo htmlspecialchars($value_form); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="3"><?php echo htmlspecialchars($description_form); ?></textarea>
                </div>
                <input type="submit" class="btn" value="<?php echo ($edit_id > 0) ? 'Update Scholarship' : 'Add Scholarship'; ?>">
            </form>
        </div>
        <div class="table-container">
            <h3>Available Scholarships</h3>
            <table class="data-table">
                <thead><tr><th>Name</th><th>Type</th><th>Value</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if(empty($all_scholarships)): ?>
                        <tr><td colspan="4" style="text-align:center;">No scholarships created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($all_scholarships as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['scholarship_name']); ?></strong><p style="font-size: 0.9em; color: #6c757d; margin: 4px 0 0;"><?php echo htmlspecialchars($item['description']); ?></p></td>
                            <td><?php echo htmlspecialchars($item['type']); ?></td>
                            <td><strong><?php echo ($item['type'] == 'Fixed' ? '₹' : '') . number_format($item['value'], 2) . ($item['type'] == 'Percentage' ? '%' : ''); ?></strong></td>
                            <td style="white-space:nowrap;">
                                <a href="?edit_id=<?php echo $item['id']; ?>" class="btn-action btn-edit">Edit</a>
                                <a href="?delete_id=<?php echo $item['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure? This will remove the scholarship from all students it is assigned to.');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
<?php require_once './admin_footer.php'; ?>