<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php"); exit;
}

// =================================================================================
// --- ACTION HANDLER ---
// =================================================================================
$errors = [];
$exam_name_form = $description_form = "";
$edit_exam_type_id = 0;

// --- HANDLE POST REQUESTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // --- Action: Create/Update an Exam Type ---
    if ($action === 'manage_exam_type') {
        $exam_name = trim($_POST['exam_name']);
        $description = trim($_POST['description']);
        $edit_id = (int)$_POST['id'];

        if (empty($exam_name)) $errors[] = "Exam Name is required.";
        else { // Check for duplicates
            $sql_check = "SELECT id FROM exam_types WHERE exam_name = ? AND id != ?";
            if($stmt_check = mysqli_prepare($link, $sql_check)){
                mysqli_stmt_bind_param($stmt_check, "si", $exam_name, $edit_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if(mysqli_stmt_num_rows($stmt_check) > 0) $errors[] = "This Exam Name already exists.";
                mysqli_stmt_close($stmt_check);
            }
        }
        
        if (empty($errors)) {
            if ($edit_id > 0) {
                $sql = "UPDATE exam_types SET exam_name = ?, description = ? WHERE id = ?";
                if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "ssi", $exam_name, $description, $edit_id);
            } else {
                $sql = "INSERT INTO exam_types (exam_name, description) VALUES (?, ?)";
                if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "ss", $exam_name, $description);
            }
            if(isset($stmt)){ mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
            header("location: manage_exams.php#manageExamTypes"); exit;
        } else {
            // Repopulate form if there was an error
            $exam_name_form = $exam_name;
            $description_form = $description;
            $edit_exam_type_id = $edit_id;
        }
    }
    
    // --- Action: Save the exam schedule for a class ---
    if ($action === 'save_schedule') {
        // ... (This logic is correct from the previous step and remains unchanged)
        $exam_type_id = (int)$_POST['exam_type_id'];
        $class_id = (int)$_POST['class_id'];
        $subjects = $_POST['subjects'] ?? [];
        foreach($subjects as $subject_id => $details){
            $exam_date = $details['date']; $start_time = $details['start_time']; $end_time = $details['end_time'];
            $max_marks = (int)$details['max_marks']; $passing_marks = (int)$details['passing_marks'];
            $schedule_id = (int)$details['schedule_id'];
            if(!empty($exam_date) && !empty($start_time) && !empty($end_time) && $max_marks > 0){
                if($schedule_id > 0){
                    $sql = "UPDATE exam_schedule SET exam_date=?, start_time=?, end_time=?, max_marks=?, passing_marks=? WHERE id=?";
                    if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "sssiii", $exam_date, $start_time, $end_time, $max_marks, $passing_marks, $schedule_id);
                } else {
                    $sql = "INSERT INTO exam_schedule (exam_type_id, class_id, subject_id, exam_date, start_time, end_time, max_marks, passing_marks) VALUES (?,?,?,?,?,?,?,?)";
                    if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "iiisssii", $exam_type_id, $class_id, $subject_id, $exam_date, $start_time, $end_time, $max_marks, $passing_marks);
                }
                if(isset($stmt)){ mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
            } elseif ($schedule_id > 0) {
                 $sql_del = "DELETE FROM exam_schedule WHERE id = ?";
                 if($stmt_del = mysqli_prepare($link, $sql_del)) { mysqli_stmt_bind_param($stmt_del, "i", $schedule_id); mysqli_stmt_execute($stmt_del); mysqli_stmt_close($stmt_del); }
            }
        }
        $_SESSION['message'] = "Exam schedule updated successfully!"; $_SESSION['message_type'] = "success";
        header("location: manage_exams.php?exam_type_id=$exam_type_id&class_id=$class_id#scheduleExams"); exit;
    }
}

// --- HANDLE GET REQUESTS ---
if (isset($_GET['delete_exam_type_id'])) {
    $delete_id = (int)$_GET['delete_exam_type_id'];
    $sql = "DELETE FROM exam_types WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        // Also delete associated schedules
        $sql_del_sch = "DELETE FROM exam_schedule WHERE exam_type_id = ?";
        if($stmt_s = mysqli_prepare($link, $sql_del_sch)){ mysqli_stmt_bind_param($stmt_s, "i", $delete_id); mysqli_stmt_execute($stmt_s); mysqli_stmt_close($stmt_s); }
        header("location: manage_exams.php#manageExamTypes"); exit;
    }
}
if (isset($_GET['edit_exam_type_id'])) {
    $edit_exam_type_id = (int)$_GET['edit_exam_type_id'];
    $sql = "SELECT * FROM exam_types WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $edit_exam_type_id);
        mysqli_stmt_execute($stmt);
        if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $exam_name_form = $row['exam_name'];
            $description_form = $row['description'];
        }
        mysqli_stmt_close($stmt);
    }
}
$selected_exam_type_id = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// (Data fetching logic is correct and remains the same)
// ...
$all_exam_types = []; $all_classes = []; $class_subjects = []; $existing_schedule = [];
$sql_types = "SELECT * FROM exam_types ORDER BY exam_name ASC"; if ($res = mysqli_query($link, $sql_types)) while ($row = mysqli_fetch_assoc($res)) $all_exam_types[] = $row;
$sql_classes = "SELECT * FROM classes ORDER BY class_name, section_name"; if ($res = mysqli_query($link, $sql_classes)) while ($row = mysqli_fetch_assoc($res)) $all_classes[] = $row;
if($selected_class_id > 0){ $sql_subjects = "SELECT s.id, s.subject_name FROM subjects s JOIN class_subjects cs ON s.id = cs.subject_id WHERE cs.class_id = ? ORDER BY s.subject_name"; if($stmt = mysqli_prepare($link, $sql_subjects)){ mysqli_stmt_bind_param($stmt, "i", $selected_class_id); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); while($row = mysqli_fetch_assoc($res)) $class_subjects[] = $row; mysqli_stmt_close($stmt); } }
if($selected_class_id > 0 && $selected_exam_type_id > 0){ $sql_schedule = "SELECT * FROM exam_schedule WHERE exam_type_id = ? AND class_id = ?"; if($stmt = mysqli_prepare($link, $sql_schedule)){ mysqli_stmt_bind_param($stmt, "ii", $selected_exam_type_id, $selected_class_id); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); while($row = mysqli_fetch_assoc($res)) $existing_schedule[$row['subject_id']] = $row; mysqli_stmt_close($stmt); } }
mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Examinations</title>
    <!-- ENHANCED STYLES -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1400px; margin: auto; margin-top: 100px; margin-bottom: 100px; background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); padding: 40px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.2); }
        h2 { color: #ffffff; font-weight: 600; margin-top: 0; margin-bottom: 5px; }
        .tab-nav { overflow: hidden; border-bottom: 1px solid rgba(255, 255, 255, 0.3); margin-bottom: 30px; }
        .tab-button { background-color: transparent; color: rgba(255, 255, 255, 0.8); float: left; border: none; outline: none; cursor: pointer; padding: 14px 20px; transition: 0.3s; font-size: 18px; font-weight: 500; }
        .tab-button:hover { color: #fff; }
        .tab-button.active { color: #fff; font-weight: 600; border-bottom: 2px solid #fff; }
        .tab-content { display: none; }
        .form-container, .table-container { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .container-wrapper { display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start; }
        .form-container { flex: 1; min-width: 300px; }
        .table-container { flex: 2; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(45deg, #6a82fb, #fc5c7d); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eef2f7; }
        .data-table thead th { background-color: #1e2a4c; color: white; white-space: nowrap; }
        .btn-action { display: inline-block; padding: 8px 15px; margin-right: 5px; border-radius: 5px; color: white; text-decoration: none; font-size: 14px; }
        .btn-edit { background-color: #ffc107; color: #212529; }
        .btn-delete { background-color: #dc3545; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
        .alert-danger { color: #721c24; background-color: #f8d7da; }
        .selection-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Examination Management</h2>
    <div class="tab-nav">
        <button class="tab-button" onclick="openTab(event, 'scheduleExams')" id="defaultOpen">Schedule Exams</button>
        <button class="tab-button" onclick="openTab(event, 'manageExamTypes')">Manage Exam Types</button>
    </div>

    <!-- TAB 1: Schedule Exams -->
    <div id="scheduleExams" class="tab-content">
        <div class="form-container">
            <h3>Create / Edit Exam Schedule</h3>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?>"><?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
            <?php endif; ?>
            <form method="GET" action="manage_exams.php" id="selectionForm">
                <div class="selection-grid">
                    <div class="form-group"><label>Select Exam Type</label><select name="exam_type_id" onchange="document.getElementById('selectionForm').submit();"><option value="">-- Choose Exam --</option><?php foreach ($all_exam_types as $type): ?><option value="<?php echo $type['id']; ?>" <?php if ($selected_exam_type_id == $type['id']) echo 'selected'; ?>><?php echo htmlspecialchars($type['exam_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Select Class</label><select name="class_id" onchange="document.getElementById('selectionForm').submit();"><option value="">-- Choose Class --</option><?php foreach ($all_classes as $class): ?><option value="<?php echo $class['id']; ?>" <?php if ($selected_class_id == $class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option><?php endforeach; ?></select></div>
                </div>
            </form>
            <?php if ($selected_class_id > 0 && $selected_exam_type_id > 0): ?>
                <hr><form method="POST" action="manage_exams.php">
                    <input type="hidden" name="action" value="save_schedule"><input type="hidden" name="exam_type_id" value="<?php echo $selected_exam_type_id; ?>"><input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                    <h4>Enter schedule for the selected class subjects:</h4>
                    <div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>Subject</th><th>Date</th><th>Start Time</th><th>End Time</th><th>Max Marks</th><th>Passing Marks</th></tr></thead><tbody>
                        <?php if(empty($class_subjects)): ?>
                            <tr><td colspan="6" style="text-align:center;">No subjects are assigned to this class. Please assign subjects first.</td></tr>
                        <?php else: ?>
                            <?php foreach ($class_subjects as $subject): $schedule = $existing_schedule[$subject['id']] ?? null; ?>
                            <tr>
                                <input type="hidden" name="subjects[<?php echo $subject['id']; ?>][schedule_id]" value="<?php echo $schedule['id'] ?? 0; ?>">
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><input type="date" name="subjects[<?php echo $subject['id']; ?>][date]" value="<?php echo $schedule['exam_date'] ?? ''; ?>"></td>
                                <td><input type="time" name="subjects[<?php echo $subject['id']; ?>][start_time]" value="<?php echo $schedule['start_time'] ?? ''; ?>"></td>
                                <td><input type="time" name="subjects[<?php echo $subject['id']; ?>][end_time]" value="<?php echo $schedule['end_time'] ?? ''; ?>"></td>
                                <td><input type="number" name="subjects[<?php echo $subject['id']; ?>][max_marks]" value="<?php echo $schedule['max_marks'] ?? ''; ?>"></td>
                                <td><input type="number" name="subjects[<?php echo $subject['id']; ?>][passing_marks]" value="<?php echo $schedule['passing_marks'] ?? ''; ?>"></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody></table></div>
                    <?php if(!empty($class_subjects)): ?><div style="margin-top: 20px;"><input type="submit" class="btn" value="Save Schedule"></div><?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: Manage Exam Types (NOW FILLED IN) -->
    <div id="manageExamTypes" class="tab-content">
        <div class="container-wrapper">
            <div class="form-container">
                <h3><?php echo ($edit_exam_type_id > 0) ? 'Edit Exam Type' : 'Add New Exam Type'; ?></h3>
                 <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul><?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?></ul></div>
                <?php endif; ?>
                <form action="manage_exams.php" method="post">
                    <input type="hidden" name="action" value="manage_exam_type">
                    <input type="hidden" name="id" value="<?php echo $edit_exam_type_id; ?>">
                    <div class="form-group">
                        <label>Exam Name (e.g., Mid-Term Exam)</label>
                        <input type="text" name="exam_name" value="<?php echo htmlspecialchars($exam_name_form); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"><?php echo htmlspecialchars($description_form); ?></textarea>
                    </div>
                    <input type="submit" class="btn" value="<?php echo ($edit_exam_type_id > 0) ? 'Update Exam Type' : 'Add Exam Type'; ?>">
                </form>
            </div>
            <div class="table-container">
                <h3>Existing Exam Types</h3>
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Description</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if(empty($all_exam_types)): ?>
                             <tr><td colspan="3" style="text-align:center;">No exam types created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_exam_types as $type): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($type['exam_name']); ?></td>
                                <td><?php echo htmlspecialchars($type['description']); ?></td>
                                <td>
                                    <a href="manage_exams.php?edit_exam_type_id=<?php echo $type['id']; ?>#manageExamTypes" class="btn-action btn-edit">Edit</a>
                                    <a href="manage_exams.php?delete_exam_type_id=<?php echo $type['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure? This will delete all schedules associated with this exam.');">Delete</a>
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
    function openTab(evt, tabName) {
        let i, tabcontent, tablinks;
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
        let tabToOpen = 'scheduleExams';
        if (hash === 'manageExamTypes') tabToOpen = hash;
        document.querySelector(`.tab-button[onclick*="'${tabToOpen}'"]`).click();
    });
</script>
</body>
</html>
<?php require_once './admin_footer.php'; ?>