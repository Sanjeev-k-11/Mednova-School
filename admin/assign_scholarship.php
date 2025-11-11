<?php
session_start();
require_once "../database/config.php";

// Auth Check & Data Fetching (This part is correct and remains the same)
// ...
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php"); exit;
}
$admin_id = $_SESSION['admin_id'];
$all_students = [];
$all_scholarships = [];
$student_current_scholarships = [];
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$sql_students = "SELECT id, first_name, last_name, registration_number FROM students WHERE status = 'Active' ORDER BY first_name";
if ($res = mysqli_query($link, $sql_students)) while ($row = mysqli_fetch_assoc($res)) $all_students[] = $row;
$sql_scholarships = "SELECT id, scholarship_name FROM scholarships WHERE is_active = 1 ORDER BY scholarship_name";
if ($res = mysqli_query($link, $sql_scholarships)) while ($row = mysqli_fetch_assoc($res)) $all_scholarships[] = $row;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_scholarship'])) {
    $student_id = (int)$_POST['student_id'];
    $scholarship_id = (int)$_POST['scholarship_id'];
    $notes = trim($_POST['notes']);
    if ($student_id > 0 && $scholarship_id > 0) {
        $sql = "INSERT INTO student_scholarships (student_id, scholarship_id, assigned_date, notes, assigned_by_admin_id) VALUES (?, ?, CURDATE(), ?, ?) ON DUPLICATE KEY UPDATE notes=VALUES(notes)";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "iisi", $student_id, $scholarship_id, $notes, $admin_id);
            mysqli_stmt_execute($stmt);
            $_SESSION['message'] = "Scholarship assigned successfully.";
            $_SESSION['message_type'] = "success";
            header("location: assign_scholarship.php?student_id=" . $student_id);
            exit;
        }
    }
}
if (isset($_GET['remove_id'])) {
    $remove_id = (int)$_GET['remove_id'];
    $sql_del = "DELETE FROM student_scholarships WHERE id = ?";
    if($stmt_del = mysqli_prepare($link, $sql_del)){
        mysqli_stmt_bind_param($stmt_del, "i", $remove_id);
        mysqli_stmt_execute($stmt_del);
        $_SESSION['message'] = "Scholarship removed.";
        $_SESSION['message_type'] = "success";
        header("location: assign_scholarship.php?student_id=" . $selected_student_id);
        exit;
    }
}
if ($selected_student_id > 0) {
    $sql_current = "SELECT ss.id, s.scholarship_name, s.type, s.value, ss.assigned_date FROM student_scholarships ss JOIN scholarships s ON ss.scholarship_id = s.id WHERE ss.student_id = ?";
    if($stmt = mysqli_prepare($link, $sql_current)){
        mysqli_stmt_bind_param($stmt, "i", $selected_student_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_assoc($res)) $student_current_scholarships[] = $row;
    }
}

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Scholarship to Student</title>
    <!-- ENHANCED STYLES -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;  background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1200px; margin: auto; margin-top: 100px; margin-bottom: 100px;}
        .main-title { color: #fff; text-align: center; font-weight: 600; font-size: 2em; margin-bottom: 20px; }
        .selection-container { background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); padding: 30px; border-radius: 20px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border: 1px solid rgba(255, 255, 255, 0.18); margin-bottom: 30px; }
        .container-wrapper { display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start; }
        .form-container, .table-container { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-container { flex: 1; min-width: 300px; }
        .table-container { flex: 2; }
        h3 { margin-top: 0; color: #1e2a4c; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
        select, textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(45deg, #6a82fb, #fc5c7d); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eef2f7; }
        .data-table thead th { background-color: #f8f9fa; color: #343a40; }
        .btn-remove { color: #dc3545; text-decoration: none; font-weight: 500; }
        .btn-remove:hover { text-decoration: underline; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; color: #fff; border: 1px solid rgba(255,255,255,0.3); }
        .alert-success { background-color: rgba(40, 167, 69, 0.8); }
    </style>
</head>
<body>
<div class="container">
    <h2 class="main-title">Assign Scholarships to Students</h2>

    <div class="selection-container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>"><?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
        <?php endif; ?>
        <form method="GET" action="assign_scholarship.php">
            <div class="form-group">
                <label style="color: #fff;">Step 1: Select a Student</label>
                <select name="student_id" onchange="this.form.submit()">
                    <option value="">-- Choose Student --</option>
                    <?php foreach ($all_students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" <?php if ($selected_student_id == $student['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['registration_number'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($selected_student_id > 0): ?>
        <div class="container-wrapper">
            <div class="form-container">
                <h3>Award New Scholarship</h3>
                <form method="POST" action="assign_scholarship.php">
                    <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                    <div class="form-group">
                        <label>Select Scholarship</label>
                        <select name="scholarship_id" required>
                            <option value="">-- Choose Scholarship --</option>
                            <?php foreach ($all_scholarships as $scholarship): ?>
                                <option value="<?php echo $scholarship['id']; ?>"><?php echo htmlspecialchars($scholarship['scholarship_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Notes (Optional)</label><textarea name="notes" rows="3"></textarea></div>
                    <input type="submit" name="assign_scholarship" class="btn" value="Assign Scholarship">
                </form>
            </div>
            <div class="table-container">
                <h3>Currently Awarded Scholarships</h3>
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Value</th><th>Assigned On</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if(empty($student_current_scholarships)): ?>
                            <tr><td colspan="4" style="text-align:center;">No scholarships have been assigned to this student yet.</td></tr>
                        <?php else: ?>
                            <?php foreach($student_current_scholarships as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['scholarship_name']); ?></strong></td>
                                <td><?php echo ($item['type'] == 'Fixed' ? '₹' : '') . number_format($item['value'], 2) . ($item['type'] == 'Percentage' ? '%' : ''); ?></td>
                                <td><?php echo date("M j, Y", strtotime($item['assigned_date'])); ?></td>
                                <td><a href="?remove_id=<?php echo $item['id']; ?>&student_id=<?php echo $selected_student_id; ?>" class="btn-remove" onclick="return confirm('Are you sure you want to remove this scholarship from the student?');">Remove</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
<?php require_once './admin_footer.php'; ?>