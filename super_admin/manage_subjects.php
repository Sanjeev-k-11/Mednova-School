<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}

// (All your existing PHP logic for handling POST/GET requests and fetching data is correct and remains here)
// ...
$errors = [];
$subject_name_form = $subject_code_form = "";
$edit_subject_id = 0;
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    if ($action === 'manage_subject') {
        $subject_name = trim($_POST['subject_name']);
        $subject_code = trim($_POST['subject_code']);
        $edit_id = (int)$_POST['id'];
        if (empty($subject_name)) $errors[] = "Subject Name is required.";
        else {
            $sql_check = "SELECT id FROM subjects WHERE subject_name = ? AND id != ?";
            if ($stmt_check = mysqli_prepare($link, $sql_check)) {
                mysqli_stmt_bind_param($stmt_check, "si", $subject_name, $edit_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) $errors[] = "This Subject Name already exists.";
                mysqli_stmt_close($stmt_check);
            }
        }
        if (!empty($subject_code)) {
             $sql_check = "SELECT id FROM subjects WHERE subject_code = ? AND id != ?";
            if ($stmt_check = mysqli_prepare($link, $sql_check)) {
                mysqli_stmt_bind_param($stmt_check, "si", $subject_code, $edit_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) $errors[] = "This Subject Code already exists.";
                mysqli_stmt_close($stmt_check);
            }
        }
        if (empty($errors)) {
            if ($edit_id > 0) {
                $sql = "UPDATE subjects SET subject_name = ?, subject_code = ? WHERE id = ?";
                if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "ssi", $subject_name, $subject_code, $edit_id);
            } else {
                $sql = "INSERT INTO subjects (subject_name, subject_code) VALUES (?, ?)";
                if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "ss", $subject_name, $subject_code);
            }
            if(isset($stmt)) { mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
            header("location: manage_subjects.php#manageSubjects"); exit;
        } else {
            $subject_name_form = $subject_name;
            $subject_code_form = $subject_code;
            $edit_subject_id = $edit_id;
        }
    }
    if ($action === 'assign_subjects') {
        $class_id_to_update = (int)$_POST['class_id'];
        $assigned_subjects = $_POST['subjects'] ?? [];
        $sql_delete = "DELETE FROM class_subjects WHERE class_id = ?";
        if($stmt_del = mysqli_prepare($link, $sql_delete)){
            mysqli_stmt_bind_param($stmt_del, "i", $class_id_to_update);
            mysqli_stmt_execute($stmt_del);
            mysqli_stmt_close($stmt_del);
        }
        if (!empty($assigned_subjects)) {
            $sql_insert = "INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)";
            if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                foreach ($assigned_subjects as $subject_id) {
                    mysqli_stmt_bind_param($stmt_insert, "ii", $class_id_to_update, $subject_id);
                    mysqli_stmt_execute($stmt_insert);
                }
                mysqli_stmt_close($stmt_insert);
            }
        }
        $_SESSION['message'] = "Subject assignments for the class have been updated.";
        $_SESSION['message_type'] = "success";
        header("location: manage_subjects.php?class_id=" . $class_id_to_update . "#assignSubjects");
        exit;
    }
}
if (isset($_GET['delete_subject_id'])) {
    $delete_id = (int)$_GET['delete_subject_id'];
    $sql_del = "DELETE FROM subjects WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql_del)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $sql_del_class = "DELETE FROM class_subjects WHERE subject_id = ?";
        if($stmt_c = mysqli_prepare($link, $sql_del_class)){
            mysqli_stmt_bind_param($stmt_c, "i", $delete_id);
            mysqli_stmt_execute($stmt_c); mysqli_stmt_close($stmt_c);
        }
        header("location: manage_subjects.php#manageSubjects"); exit;
    }
}
if (isset($_GET['edit_subject_id'])) {
    $edit_subject_id = (int)$_GET['edit_subject_id'];
    $sql_edit = "SELECT * FROM subjects WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql_edit)) {
        mysqli_stmt_bind_param($stmt, "i", $edit_subject_id);
        mysqli_stmt_execute($stmt);
        if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $subject_name_form = $row['subject_name'];
            $subject_code_form = $row['subject_code'];
        }
        mysqli_stmt_close($stmt);
    }
}
$all_classes = [];
$all_subjects = [];
$assigned_subject_ids = [];
$sql_classes = "SELECT * FROM classes ORDER BY class_name, section_name";
if ($res = mysqli_query($link, $sql_classes)) while ($row = mysqli_fetch_assoc($res)) $all_classes[] = $row;
$sql_subjects = "SELECT * FROM subjects ORDER BY subject_name";
if ($res = mysqli_query($link, $sql_subjects)) while ($row = mysqli_fetch_assoc($res)) $all_subjects[] = $row;
if ($selected_class_id > 0) {
    $sql_assigned = "SELECT subject_id FROM class_subjects WHERE class_id = ?";
    if($stmt = mysqli_prepare($link, $sql_assigned)){
        mysqli_stmt_bind_param($stmt, "i", $selected_class_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_assoc($res)) $assigned_subject_ids[] = $row['subject_id'];
        mysqli_stmt_close($stmt);
    }
}
mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Subjects</title>
    <!-- ENHANCED STYLES -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1200px; margin: auto; margin-top: 100px; margin-bottom: 100px; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border: 1px solid rgba(255, 255, 255, 0.18); }
        h2 { text-align: center; color: #1e2a4c; font-weight: 600; margin-bottom: 30px; }
        .tab-nav { overflow: hidden; border-bottom: 2px solid rgba(255,255,255,0.3); margin-bottom: 20px; }
        .tab-button { background-color: transparent; color: #fff; float: left; border: none; outline: none; cursor: pointer; padding: 14px 20px; transition: 0.3s; font-size: 17px; font-weight: 600; }
        .tab-button.active { background-color: rgba(255,255,255,0.2); border-bottom: 2px solid #fff; }
        .tab-content { display: none; padding: 6px 12px; }
        .container-wrapper { display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start; }
        .form-container { flex: 1; min-width: 300px; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .table-container { flex: 2; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        input[type=text], select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; transition: border-color 0.2s, box-shadow 0.2s; }
        input[type=text]:focus, select:focus { border-color: #6a82fb; box-shadow: 0 0 0 3px rgba(106, 130, 251, 0.2); outline: none; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(45deg, #6a82fb, #fc5c7d); transition: transform 0.2s; }
        .btn:hover { transform: scale(1.02); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eef2f7; }
        .data-table thead th { background-color: #1e2a4c; color: white; }
        .btn-action { display: inline-block; padding: 8px 15px; margin-right: 5px; border-radius: 5px; color: white; text-decoration: none; font-size: 14px; font-weight: 500; border: none; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-edit { background-color: #ffc107; color: #212529; }
        .btn-delete { background-color: #dc3545; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
        .alert-danger { color: #721c24; background-color: #f8d7da; }
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .checkbox-item { background: #f8f9fa; padding: 12px; border-radius: 5px; border: 1px solid #e9ecef; display: flex; align-items: center; }
        .checkbox-item label { display: flex; align-items: center; width: 100%; cursor: pointer; }
        .checkbox-item input[type="checkbox"] { margin-right: 10px; width: 18px; height: 18px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Subject Management</h2>
    <div class="tab-nav">
        <button class="tab-button" onclick="openTab(event, 'assignSubjects')" id="defaultOpen">Assign to Class</button>
        <button class="tab-button" onclick="openTab(event, 'manageSubjects')">Manage Subjects</button>
    </div>

    <div id="assignSubjects" class="tab-content">
        <div class="form-container" style="max-width: none; width: 100%;">
            <h3>Assign Subjects</h3>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?>"><?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
            <?php endif; ?>
            <form method="GET" action="manage_subjects.php">
                <div class="form-group">
                    <label for="class_id">Select a Class</label>
                    <select name="class_id" id="class_id" onchange="this.form.submit()">
                        <option value="">-- Choose a Class --</option>
                        <?php foreach ($all_classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php if ($selected_class_id == $class['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($selected_class_id > 0): ?>
                <hr style="margin: 20px 0;"><form method="POST" action="manage_subjects.php">
                    <input type="hidden" name="action" value="assign_subjects">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                    <h4>Select subjects for the chosen class:</h4>
                    <div class="checkbox-grid">
                        <?php foreach ($all_subjects as $subject): ?>
                            <div class="checkbox-item">
                                <label>
                                    <input type="checkbox" name="subjects[]" value="<?php echo $subject['id']; ?>" <?php if (in_array($subject['id'], $assigned_subject_ids)) echo 'checked'; ?>>
                                    <span><?php echo htmlspecialchars($subject['subject_name']); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 20px;"><input type="submit" class="btn" value="Save Subject Assignments"></div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div id="manageSubjects" class="tab-content">
        <div class="container-wrapper">
            <div class="form-container">
                <h2><?php echo ($edit_subject_id > 0) ? 'Edit Subject' : 'Add New Subject'; ?></h2>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul><?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?></ul></div>
                <?php endif; ?>
                <form action="manage_subjects.php" method="post">
                    <input type="hidden" name="action" value="manage_subject">
                    <input type="hidden" name="id" value="<?php echo $edit_subject_id; ?>">
                    <div class="form-group">
                        <label>Subject Name (e.g., Mathematics)</label>
                        <input type="text" name="subject_name" value="<?php echo htmlspecialchars($subject_name_form); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Subject Code (e.g., MATH101)</label>
                        <input type="text" name="subject_code" value="<?php echo htmlspecialchars($subject_code_form); ?>">
                    </div>
                    <input type="submit" class="btn" value="<?php echo ($edit_subject_id > 0) ? 'Update Subject' : 'Add Subject'; ?>">
                </form>
            </div>
            <div class="table-container">
                <h3>Existing Subjects</h3>
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Code</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($all_subjects)): ?>
                            <tr><td colspan="3" style="text-align:center;">No subjects created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_subjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_code'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="manage_subjects.php?edit_subject_id=<?php echo $subject['id']; ?>#manageSubjects" class="btn-action btn-edit">Edit</a>
                                    <a href="manage_subjects.php?delete_subject_id=<?php echo $subject['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure? This will unassign the subject from all classes.');">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // (JavaScript logic remains the same, it's already robust)
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) tabcontent[i].style.display = "none";
        tablinks = document.getElementsByClassName("tab-button");
        for (i = 0; i < tablinks.length; i++) tablinks[i].className = tablinks[i].className.replace(" active", "");
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
        window.location.hash = tabName;
    }

    document.addEventListener('DOMContentLoaded', (event) => {
        const hash = window.location.hash.substring(1);
        let tabToOpen = 'assignSubjects'; // Default tab
        if (hash === 'manageSubjects') {
            tabToOpen = hash;
        }
        document.querySelector(`.tab-button[onclick*="'${tabToOpen}'"]`).click();
    });
</script>

</body>
</html>
<?php require_once './admin_footer.php'; ?>