<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"]; // Assuming 'id' is the primary key for the principal
$principal_name = $_SESSION["full_name"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Process Form Submissions (Add, Edit) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $class_name = trim($_POST['class_name']);
        $section_name = trim($_POST['section_name']);
        $teacher_id = $_POST['teacher_id'] === '' ? NULL : (int)$_POST['teacher_id']; // Handle NULL for no teacher

        // Basic validation
        if (empty($class_name) || empty($section_name)) {
            set_session_message("Class Name and Section are required.", "danger");
            header("location: manage_classes.php");
            exit;
        }

        if ($action == 'add') {
            // Check for duplicate class and section
            $check_sql = "SELECT id FROM classes WHERE class_name = ? AND section_name = ?";
            if ($stmt = mysqli_prepare($link, $check_sql)) {
                mysqli_stmt_bind_param($stmt, "ss", $class_name, $section_name);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    set_session_message("A class with this name and section already exists.", "danger");
                    mysqli_stmt_close($stmt);
                    header("location: manage_classes.php");
                    exit;
                }
                mysqli_stmt_close($stmt);
            }

            // Insert new class
            $sql = "INSERT INTO classes (class_name, section_name, teacher_id) VALUES (?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssi", $class_name, $section_name, $teacher_id);
                if (mysqli_stmt_execute($stmt)) {
                    set_session_message("Class added successfully.", "success");
                } else {
                    set_session_message("Error adding class: " . mysqli_error($link), "danger");
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action == 'edit') {
            $class_id = (int)$_POST['class_id'];
            if (empty($class_id)) {
                set_session_message("Invalid class ID for editing.", "danger");
                header("location: manage_classes.php");
                exit;
            }

            // Check for duplicate class and section, excluding the current class being edited
            $check_sql = "SELECT id FROM classes WHERE class_name = ? AND section_name = ? AND id != ?";
            if ($stmt = mysqli_prepare($link, $check_sql)) {
                mysqli_stmt_bind_param($stmt, "ssi", $class_name, $section_name, $class_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    set_session_message("Another class with this name and section already exists.", "danger");
                    mysqli_stmt_close($stmt);
                    header("location: manage_classes.php");
                    exit;
                }
                mysqli_stmt_close($stmt);
            }

            // Update class
            $sql = "UPDATE classes SET class_name = ?, section_name = ?, teacher_id = ? WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssii", $class_name, $section_name, $teacher_id, $class_id);
                if (mysqli_stmt_execute($stmt)) {
                    set_session_message("Class updated successfully.", "success");
                } else {
                    set_session_message("Error updating class: " . mysqli_error($link), "danger");
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    header("location: manage_classes.php"); // Redirect after POST to prevent re-submission
    exit;
}

// --- Process Delete Request ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if (empty($delete_id)) {
        set_session_message("Invalid class ID for deletion.", "danger");
        header("location: manage_classes.php");
        exit;
    }

    // Attempt to delete the class
    $sql = "DELETE FROM classes WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                 set_session_message("Class deleted successfully. Note: Related records (e.g., students, timetable) might have been affected based on foreign key rules.", "success");
            } else {
                 set_session_message("Class not found or already deleted.", "danger");
            }
        } else {
            // Check for foreign key constraint error specifically
            if (mysqli_errno($link) == 1451) { // Error 1451 is for foreign key constraint violation
                set_session_message("Cannot delete class. It is currently assigned to students or has related records (e.g., timetable, assignments). Please reassign students/delete related records first.", "danger");
            } else {
                set_session_message("Error deleting class: " . mysqli_error($link), "danger");
            }
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_classes.php");
    exit;
}

// --- Fetch all Classes for Display ---
$classes = [];
$sql_fetch_classes = "SELECT c.id, c.class_name, c.section_name, t.full_name AS teacher_name
                      FROM classes c
                      LEFT JOIN teachers t ON c.teacher_id = t.id
                      ORDER BY c.class_name ASC, c.section_name ASC";
if ($result = mysqli_query($link, $sql_fetch_classes)) {
    $classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching classes: " . mysqli_error($link);
    $message_type = "danger";
}

// --- Fetch all Teachers for Dropdown ---
$teachers = [];
$sql_fetch_teachers = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_fetch_teachers)) {
    $teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching teachers for dropdown: " . mysqli_error($link);
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
    <title>Manage Classes - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #1e2a4c;
            margin-bottom: 25px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
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
        .form-section {
            background-color: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }
        .form-section h3 {
            color: #343a40;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-group select {
            appearance: none; /* Remove default arrow */
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23007bff%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23007bff%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E'); /* Custom arrow */
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
        }
        .btn-primary {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            margin-left: 10px;
            transition: background-color 0.3s ease;
        }
        .btn-cancel:hover {
            background-color: #5a6268;
        }

        .class-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        .class-list-table th, .class-list-table td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: left;
            vertical-align: middle;
        }
        .class-list-table th {
            background-color: #e9ecef;
            color: #495057;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .class-list-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .class-list-table tr:hover {
            background-color: #f1f1f1;
        }
        .action-buttons-group {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn-edit, .btn-delete {
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
            border: 1px solid #ffc107;
        }
        .btn-edit:hover {
            background-color: #e0a800;
            border-color: #e0a800;
        }
        .btn-delete {
            background-color: #dc3545;
            color: #fff;
            border: 1px solid #dc3545;
        }
        .btn-delete:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-school"></i> Manage Classes</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Class Form -->
        <div class="form-section">
            <h3 id="form-title"><i class="fas fa-plus-circle"></i> Add New Class</h3>
            <form id="class-form" action="manage_classes.php" method="POST">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="class_id" id="class-id" value="">

                <div class="form-group">
                    <label for="class_name">Class Name:</label>
                    <input type="text" id="class_name" name="class_name" required placeholder="e.g., Grade 1, Nursery">
                </div>
                <div class="form-group">
                    <label for="section_name">Section Name:</label>
                    <input type="text" id="section_name" name="section_name" required placeholder="e.g., A, B, Ruby">
                </div>
                <div class="form-group">
                    <label for="teacher_id">Class Teacher:</label>
                    <select id="teacher_id" name="teacher_id">
                        <option value="">-- Select Class Teacher (Optional) --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo htmlspecialchars($teacher['id']); ?>">
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-primary" id="submit-btn"><i class="fas fa-save"></i> Add Class</button>
                    <button type="button" class="btn-cancel" id="cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                </div>
            </form>
        </div>

        <!-- Class List Table -->
        <h3><i class="fas fa-list"></i> Existing Classes</h3>
        <?php if (empty($classes)): ?>
            <p class="text-center text-muted">No classes found. Add a new class above.</p>
        <?php else: ?>
            <table class="class-list-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Class Name</th>
                        <th>Section</th>
                        <th>Class Teacher</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($class['id']); ?></td>
                            <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($class['section_name']); ?></td>
                            <td><?php echo htmlspecialchars($class['teacher_name'] ?: 'N/A'); ?></td>
                            <td class="text-center">
                                <div class="action-buttons-group">
                                    <button class="btn-edit" onclick="editClass(<?php echo htmlspecialchars(json_encode($class)); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['class_name'] . ' ' . $class['section_name']); ?>')" class="btn-delete">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    // Function to populate the form for editing
    function editClass(classData) {
        document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Class';
        document.getElementById('form-action').value = 'edit';
        document.getElementById('class-id').value = classData.id;
        document.getElementById('class_name').value = classData.class_name;
        document.getElementById('section_name').value = classData.section_name;

        // Select the teacher in the dropdown
        const teacherSelect = document.getElementById('teacher_id');
        let teacherFound = false;
        for (let i = 0; i < teacherSelect.options.length; i++) {
            if (teacherSelect.options[i].value == classData.teacher_id) { // Use == for loose comparison if teacher_id might be numeric string
                teacherSelect.options[i].selected = true;
                teacherFound = true;
                break;
            }
        }
        if (!teacherFound) {
            teacherSelect.value = ''; // Reset if teacher not found (e.g., N/A or deleted teacher)
        }


        document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Class';
        document.getElementById('cancel-btn').style.display = 'inline-block';

        // Scroll to form
        document.getElementById('form-title').scrollIntoView({ behavior: 'smooth' });
    }

    // Function to reset the form to 'Add New Class' mode
    document.getElementById('cancel-btn').addEventListener('click', function() {
        document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Class';
        document.getElementById('form-action').value = 'add';
        document.getElementById('class-id').value = '';
        document.getElementById('class_name').value = '';
        document.getElementById('section_name').value = '';
        document.getElementById('teacher_id').value = ''; // Reset teacher dropdown
        document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Add Class';
        document.getElementById('cancel-btn').style.display = 'none';
    });

    // Function for delete confirmation
    function confirmDelete(id, name) {
        if (confirm(`Are you sure you want to delete the class "${name}"? This action cannot be undone and may affect related records (e.g., students, timetables).`)) {
            window.location.href = `manage_classes.php?delete_id=${id}`;
        }
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>