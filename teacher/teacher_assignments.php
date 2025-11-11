<?php
session_start();
require_once "../database/config.php";
require_once '../database/cloudinary_upload_handler.php'; // For file uploads

// --- AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];
$teacher_name = $_SESSION["full_name"]; // Assuming full_name is in session

// --- HANDLE POST REQUESTS (CREATE, UPDATE, DELETE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // CREATE or UPDATE
    if ($action === 'create' || $action === 'update') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $class_id = (int)$_POST['class_id'];
        $subject_id = (int)$_POST['subject_id'];
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $assignment_id = ($action === 'update') ? (int)$_POST['assignment_id'] : null;

        $file_url = $_POST['existing_file_url'] ?? null; // Keep existing file by default
        $upload_error = false;

        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadToCloudinary($_FILES['assignment_file'], 'assignments');
            if (isset($upload_result['secure_url'])) {
                $file_url = $upload_result['secure_url'];
            } else {
                $upload_error = true;
                $_SESSION['flash_message'] = "Error uploading file: " . ($upload_result['error'] ?? 'Unknown upload error');
            }
        }

        if (!$upload_error) {
            if ($action === 'create') {
                $sql = "INSERT INTO assignments (title, description, class_id, subject_id, teacher_id, due_date, file_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "ssiiiss", $title, $description, $class_id, $subject_id, $teacher_id, $due_date, $file_url);
            } else { // Update
                $sql = "UPDATE assignments SET title=?, description=?, class_id=?, subject_id=?, due_date=?, file_url=? WHERE id=? AND teacher_id=?";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "ssiissii", $title, $description, $class_id, $subject_id, $due_date, $file_url, $assignment_id, $teacher_id);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_message'] = "Assignment " . ($action === 'create' ? 'created' : 'updated') . " successfully!";
            } else {
                $_SESSION['flash_message'] = "Error: Could not process the request. " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // DELETE
    if ($action === 'delete') {
        $assignment_id = (int)$_POST['assignment_id'];
        // Security check: ensure teacher can only delete their own assignments
        $sql = "DELETE FROM assignments WHERE id = ? AND teacher_id = ?";
        if($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $assignment_id, $teacher_id);
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_message'] = "Assignment deleted successfully!";
            } else {
                $_SESSION['flash_message'] = "Error deleting assignment: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['flash_message'] = "Failed to prepare delete statement.";
        }
    }

    header("Location: teacher_assignments.php");
    exit();
}

// Handle Flash Message
$message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

// --- DATA FETCHING for DISPLAY ---
// Get all assignments created by this teacher
$assignments = [];
$sql = "SELECT a.*, c.class_name, c.section_name, s.subject_name 
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.teacher_id = ? 
        ORDER BY a.created_at DESC";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $assignments = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// Get classes for the creation/edit modal dropdown
$assigned_classes = [];
$sql_classes = "SELECT DISTINCT c.id, c.class_name, c.section_name FROM class_subject_teacher cst JOIN classes c ON cst.class_id = c.id WHERE cst.teacher_id = ? ORDER BY c.class_name";
if ($stmt_classes = mysqli_prepare($link, $sql_classes)) {
    mysqli_stmt_bind_param($stmt_classes, "i", $teacher_id);
    mysqli_stmt_execute($stmt_classes);
    $assigned_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_classes), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_classes);
}

mysqli_close($link);
require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments - Teacher Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <style>
        /* General Theme Styles (adapted from student dashboard, but with blue/purple for teacher) */
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(-45deg, #e0f2f7, #e3f2fd, #bbdefb, #90caf9); /* Light Blue/Azure theme */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .page-header { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2.5rem 2rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); text-align: center; }
        .page-header h1 { font-weight: 700; color: #1a2a4b; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); margin-bottom: 1rem; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; gap: 15px; }
        .welcome-info-block { padding: 1rem; background: rgba(255, 255, 255, 0.5); border-radius: 0.5rem; display: inline-block; margin-top: 1rem; border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 2px 5px rgba(0,0,0,0.03); }
        .welcome-info { font-weight: 500; color: #666; margin-bottom: 0; font-size: 0.95rem; }
        .welcome-info strong { color: #333; }

        .section-title { font-size: 1.25rem; font-weight: 600; color: #1a2a4b; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }

        /* Dashboard Panel for main content */
        .dashboard-panel { 
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(10px);
            border-radius: 15px; 
            padding: 2rem; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            border: 1px solid rgba(255, 255, 255, 0.5); 
        }

        /* Themed Primary Button */
        .btn-themed-primary {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 0.6rem 1.5rem;
            border: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 0.5rem;
            color: white;
            background-color: #1a2a4b; /* Dark blue */
            transition: background-color 0.2s, transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
        }
        .btn-themed-primary:hover {
            background-color: #0d1a33; /* Darker blue */
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            color: white;
        }
        .btn-themed-secondary {
            background-color: #6c757d; /* Bootstrap secondary grey */
            color: #fff;
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            transition: background-color 0.2s, transform 0.2s;
        }
        .btn-themed-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            color: #fff;
        }
        .btn-themed-danger {
            background-color: #dc3545; /* Bootstrap danger red */
            color: #fff;
            font-weight: 500;
            padding: 0.4rem 0.9rem;
            border-radius: 0.5rem;
            border: none;
            font-size: 0.85rem;
            transition: background-color 0.2s, transform 0.2s;
        }
        .btn-themed-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            color: #fff;
        }


        /* Assignment Item Card */
        .assignment-item {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            color: #333;
        }
        .assignment-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.1);
        }
        .assignment-item h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a2a4b;
            margin-bottom: 0.5rem;
        }
        .assignment-item .meta-info {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
        }
        .assignment-item .description {
            font-size: 0.95rem;
            color: #444;
            flex-grow: 1; /* Allows description to take space */
            margin-bottom: 1rem;
        }
        .assignment-item .divider {
            border-top: 1px solid rgba(0,0,0,0.1);
            margin: 1rem 0;
        }
        .assignment-item .due-date {
            font-weight: 600;
            color: #1a2a4b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .assignment-item .due-date.overdue {
            color: #dc3545; /* Red for overdue */
        }
        .assignment-item .attachment-link {
            color: #007bff; /* Bootstrap primary blue */
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .assignment-item .attachment-link:hover {
            text-decoration: underline;
        }
        .assignment-item .actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .btn-sm-themed {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
            border-radius: 0.5rem;
            font-weight: 500;
            border: none;
            transition: background-color 0.2s, transform 0.2s;
        }
        .btn-sm-themed.edit { background-color: #007bff; color: #fff; }
        .btn-sm-themed.edit:hover { background-color: #0069d9; transform: translateY(-1px); }
        .btn-sm-themed.delete { background-color: #dc3545; color: #fff; }
        .btn-sm-themed.delete:hover { background-color: #c82333; transform: translateY(-1px); }


        /* Modal Styling */
        .modal-themed .modal-content {
            background: rgba(255, 255, 255, 0.95); /* Slightly opaque white */
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: #333;
        }
        .modal-themed .modal-header {
            background-color: #1a2a4b; /* Dark blue header */
            color: #fff;
            border-bottom: none;
            border-radius: 1rem 1rem 0 0;
        }
        .modal-themed .modal-title {
            color: #fff;
            font-weight: 700;
            font-size: 1.5rem;
        }
        .modal-themed .modal-body { padding: 1.5rem; }
        .modal-themed .modal-footer {
            background-color: rgba(0,0,0,0.05); /* Light footer */
            border-top: none;
            border-radius: 0 0 1rem 1rem;
            padding: 1rem 1.5rem;
        }
        .modal-themed .form-label {
            font-weight: 600;
            color: #1a2a4b;
            margin-bottom: 0.5rem;
        }
        .form-control-themed {
            display: block;
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid rgba(0,0,0,0.15);
            border-radius: 0.5rem;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            transition: border-color 0.2s, box-shadow 0.2s;
            font-size: 0.9rem;
            color: #333;
            background-color: rgba(255,255,255,0.8);
        }
        .form-control-themed:focus {
            outline: none;
            border-color: #1a2a4b;
            box-shadow: 0 0 0 3px rgba(26, 42, 75, 0.25);
            background-color: #fff;
        }
        .form-control-themed[type="file"] {
            padding: 0.375rem 0.75rem; /* Adjust padding for file input */
        }
        .form-control-themed[type="file"]::-webkit-file-upload-button {
            background-color: #007bff; /* Themed button inside file input */
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .form-control-themed[type="file"]::-webkit-file-upload-button:hover {
            background-color: #0069d9;
        }
        .current-file-info {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
        }
        .current-file-info a {
            color: #007bff;
            text-decoration: none;
        }
        .current-file-info a:hover {
            text-decoration: underline;
        }

        /* No Assignments Message */
        .no-assignments-message {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            color: #1a2a4b;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .no-assignments-message i { font-size: 3rem; margin-bottom: 1.5rem; color: #1a2a4b; }
        .no-assignments-message p { font-size: 1.1rem; margin-bottom: 0; }

        /* Flash Message (Toast) */
        .flash-message {
            background-color: #d4edda; color: #1a6d2f; border: 1px solid #c3e6cb; border-left: 5px solid #28a745; border-radius: 0.75rem; padding: 1rem 1.5rem; margin-bottom: 1.5rem; font-weight: 500; display: flex; align-items: center; gap: 10px; max-width: 600px; margin-left: auto; margin-right: auto;
        }
        .flash-message i { font-size: 1.2em; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-container { margin-top: 20px; padding: 10px; }
            .page-header h1 { font-size: 2em; flex-direction: column; gap: 5px; }
            .welcome-info-block { width: 100%; text-align: center; }
            .section-title { font-size: 1.1rem; }
            .assignment-item { padding: 1rem; }
            .assignment-item h3 { font-size: 1.2rem; }
            .assignment-item .meta-info { font-size: 0.8rem; }
            .assignment-item .description { font-size: 0.9rem; }
            .assignment-item .due-date, .assignment-item .attachment-link { font-size: 0.85rem; }
            .assignment-item .actions { flex-direction: column; gap: 0.5rem; }
            .btn-sm-themed { width: 100%; }
            .modal-themed .modal-dialog { margin: 0.5rem; }
            .flash-message { width: 95%; margin-left: auto; margin-right: auto; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <header class="page-header">
        <h1 class="page-title">
            <i class="fas fa-pencil-ruler"></i> Manage Assignments
        </h1>
        <div class="welcome-info-block">
            <p class="welcome-info">
                Teacher: <strong><?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?></strong>
            </p>
        </div>
    </header>

    <?php if ($message): ?>
    <div class="flash-message">
        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end mb-4">
        <button type="button" onclick="openCreateModal()" class="btn-themed-primary">
            <i class="fas fa-plus me-2"></i>Create New Assignment
        </button>
    </div>

    <!-- List of Assignments -->
    <div class="dashboard-panel">
        <h2 class="section-title">Your Created Assignments</h2>
        <?php if (empty($assignments)): ?>
            <div class="no-assignments-message">
                <i class="fas fa-info-circle"></i>
                <p>You have not created any assignments yet.</p>
            </div>
        <?php else: ?>
            <div class="row g-4"> <!-- Bootstrap grid -->
                <?php foreach($assignments as $assignment): ?>
                <div class="col-12 col-md-6 col-lg-4 d-flex">
                    <div class="assignment-item flex-grow-1">
                        <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                        <p class="meta-info"><?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['section_name']); ?> | <?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                        <p class="description"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                        <div class="divider"></div>
                        <p class="due-date <?php echo (strtotime($assignment['due_date']) < time()) ? 'overdue' : ''; ?>">
                            <i class="fas fa-calendar-day"></i> Due: <?php echo date('d M, Y', strtotime($assignment['due_date'])); ?>
                        </p>
                        <?php if($assignment['file_url']): ?>
                        <p><a href="<?php echo htmlspecialchars($assignment['file_url']); ?>" target="_blank" class="attachment-link"><i class="fas fa-paperclip"></i> View Attachment</a></p>
                        <?php endif; ?>
                        <div class="actions">
                            <button type="button" onclick='openEditModal(<?php echo json_encode($assignment); ?>)' class="btn-sm-themed edit">
                                <i class="fas fa-edit me-1"></i> Edit
                            </button>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this assignment? This action cannot be undone.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                <button type="submit" class="btn-sm-themed delete">
                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade modal-themed" id="assignmentModal" tabindex="-1" aria-labelledby="assignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="assignmentForm" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignmentModalLabel">Create New Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="assignment_id" id="assignmentId">
                    <input type="hidden" name="existing_file_url" id="existingFileUrl">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="class_id" class="form-label">Class</label>
                            <select id="class_id" name="class_id" class="form-control-themed" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach($assigned_classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="subject_id" class="form-label">Subject</label>
                            <select id="subject_id" name="subject_id" class="form-control-themed" required disabled>
                                <option value="">-- Select Class First --</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" id="title" name="title" class="form-control-themed" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description / Instructions</label>
                        <textarea id="description" name="description" rows="4" class="form-control-themed"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" id="due_date" name="due_date" class="form-control-themed" required>
                        </div>
                        <div class="col-md-6">
                            <label for="assignment_file" class="form-label">Attach File (Optional)</label>
                            <input type="file" id="assignment_file" name="assignment_file" class="form-control-themed">
                            <p id="currentFile" class="current-file-info"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-themed-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="submitButton" class="btn-themed-primary">Create Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const assignmentModal = new bootstrap.Modal(document.getElementById('assignmentModal'));
    const form = document.getElementById('assignmentForm');
    const classSelect = document.getElementById('class_id');
    const subjectSelect = document.getElementById('subject_id');

    classSelect.addEventListener('change', async function() {
        const classId = this.value;
        subjectSelect.innerHTML = '<option value="">Loading...</option>';
        subjectSelect.disabled = true;

        if (!classId) {
            subjectSelect.innerHTML = '<option value="">-- Select Class First --</option>';
            return;
        }
        
        try {
            const response = await fetch(`ajax_get_subjects.php?class_id=${classId}`);
            const subjects = await response.json();
            
            subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';
            if(subjects.length > 0) {
                subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.subject_name;
                    subjectSelect.appendChild(option);
                });
                subjectSelect.disabled = false;
            } else {
                 subjectSelect.innerHTML = '<option value="">-- No subjects found --</option>';
            }
        } catch (error) {
            console.error('Failed to fetch subjects:', error);
            subjectSelect.innerHTML = '<option value="">-- Error loading --</option>';
        }
    });

    function openCreateModal() {
        form.reset();
        document.getElementById('assignmentModalLabel').textContent = 'Create New Assignment';
        document.getElementById('formAction').value = 'create';
        document.getElementById('submitButton').textContent = 'Create Assignment';
        document.getElementById('currentFile').innerHTML = '';
        document.getElementById('existingFileUrl').value = '';
        subjectSelect.innerHTML = '<option value="">-- Select Class First --</option>';
        subjectSelect.disabled = true;
        assignmentModal.show();
    }

    async function openEditModal(assignment) {
        form.reset();
        document.getElementById('assignmentModalLabel').textContent = 'Edit Assignment';
        document.getElementById('formAction').value = 'update';
        document.getElementById('submitButton').textContent = 'Save Changes';
        
        // Populate fields
        document.getElementById('assignmentId').value = assignment.id;
        document.getElementById('title').value = assignment.title;
        document.getElementById('description').value = assignment.description;
        document.getElementById('due_date').value = assignment.due_date;
        document.getElementById('class_id').value = assignment.class_id;

        // Populate and select subject
        await new Promise(resolve => { // Use Promise to wait for subjects to load
            classSelect.addEventListener('change', function handler() {
                classSelect.removeEventListener('change', handler); // Remove listener after first trigger
                resolve();
            });
            classSelect.dispatchEvent(new Event('change')); // Trigger subject loading
        });
        
        // Now select the correct subject after it's loaded
        document.getElementById('subject_id').value = assignment.subject_id;

        // Handle existing file
        if (assignment.file_url) {
            document.getElementById('existingFileUrl').value = assignment.file_url;
            document.getElementById('currentFile').innerHTML = `Current file: <a href="${assignment.file_url}" target="_blank" class="text-info">View</a>. Upload new to replace.`;
        } else {
            document.getElementById('currentFile').textContent = 'No file currently attached.';
            document.getElementById('existingFileUrl').value = ''; // Ensure hidden field is cleared
        }

        assignmentModal.show();
    }
</script>

</body>
</html>

<?php require_once './teacher_footer.php'; ?>