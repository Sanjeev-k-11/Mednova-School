<?php
// admin_assignments.php
session_start();
require_once "../database/config.php";
require_once '../database/cloudinary_upload_handler.php'; // For file uploads

// --- AUTHENTICATION ---
// Ensure only Admins can access this page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["super_admin_id"]; // The ID of the currently logged-in admin

// --- HANDLE POST REQUESTS (UPDATE, DELETE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // UPDATE
    if ($action === 'update') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $class_id = (int)$_POST['class_id'];
        $subject_id = (int)$_POST['subject_id'];
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $assignment_id = (int)$_POST['assignment_id'];

        $file_url = $_POST['existing_file_url'] ?? null; // Keep existing file by default
        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadToCloudinary($_FILES['assignment_file'], 'assignments');
            if (isset($upload_result['secure_url'])) {
                $file_url = $upload_result['secure_url'];
            } else {
                $_SESSION['flash_message'] = "Error uploading file: " . ($upload_result['error'] ?? 'Unknown');
                header("Location: admin_assignments.php"); exit();
            }
        }

        // Admin can update any assignment (no teacher_id restriction in WHERE)
        // IMPORTANT: Added `last_updated_by_admin_id` to the UPDATE query
        $sql = "UPDATE assignments SET 
                    title=?, description=?, class_id=?, subject_id=?, due_date=?, file_url=?, 
                    last_updated_by_admin_id=? 
                WHERE id=?";
        $stmt = mysqli_prepare($link, $sql);
        // Bind parameters: title, desc, class_id, subject_id, due_date, file_url, admin_id, assignment_id
        mysqli_stmt_bind_param($stmt, "ssiissii", $title, $description, $class_id, $subject_id, $due_date, $file_url, $admin_id, $assignment_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_message'] = "Assignment updated successfully!";
        } else {
            $_SESSION['flash_message'] = "Error: Could not process the update request. " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt);
    }
    
    // DELETE
    if ($action === 'delete') {
        $assignment_id = (int)$_POST['assignment_id'];
        // Admin can delete any assignment (no teacher_id restriction in WHERE)
        $sql = "DELETE FROM assignments WHERE id = ?";
        if($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $assignment_id);
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_message'] = "Assignment deleted successfully!";
            } else {
                $_SESSION['flash_message'] = "Error deleting assignment. " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        }
    }

    header("Location: admin_assignments.php");
    exit();
}

// Handle Flash Message
$message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

// --- PAGINATION & DATA FETCHING for DISPLAY ---
$records_per_page = 9; // Number of assignments per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Query to get total number of records
$count_sql = "SELECT COUNT(a.id)
              FROM assignments a
              LEFT JOIN teachers t ON a.teacher_id = t.id
              LEFT JOIN classes c ON a.class_id = c.id
              LEFT JOIN subjects s ON a.subject_id = s.id";
$count_stmt = mysqli_prepare($link, $count_sql);
mysqli_stmt_execute($count_stmt);
mysqli_stmt_bind_result($count_stmt, $total_records);
mysqli_stmt_fetch($count_stmt);
mysqli_stmt_close($count_stmt);

$total_pages = ceil($total_records / $records_per_page);

// Query to fetch assignments with pagination
$assignments = [];
$sql = "SELECT 
            a.*, 
            t.full_name as teacher_name, 
            c.class_name, c.section_name, s.subject_name,
            adm.full_name AS last_updated_admin_name, -- Added to select admin name
            a.updated_at
        FROM assignments a
        LEFT JOIN teachers t ON a.teacher_id = t.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN subjects s ON a.subject_id = s.id
        LEFT JOIN admins adm ON a.last_updated_by_admin_id = adm.id -- New JOIN to get admin's name
        ORDER BY a.created_at DESC
        LIMIT ?, ?"; 
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $offset, $records_per_page);
    mysqli_stmt_execute($stmt);
    $assignments = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// Get all classes for the creation modal dropdown (admin can see all)
$all_classes = [];
$sql_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name";
if ($stmt_classes = mysqli_prepare($link, $sql_classes)) {
    mysqli_stmt_execute($stmt_classes);
    $all_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_classes), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_classes);
}

mysqli_close($link);
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Manage All Assignments</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364); background-size: 400% 400%; animation: gradientBG 15s ease infinite; color: white; }
        @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
        .glassmorphism { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .form-input, .form-select, .form-textarea { background: rgba(0, 0, 0, 0.25); border-color: rgba(255, 255, 255, 0.2); color: white; }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }
        .form-input::placeholder, .form-textarea::placeholder { color: rgba(255, 255, 255, 0.6); }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #4CAF50; outline: none; box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.5); }
        .pagination-link {
            @apply px-3 py-1 mx-1 rounded-md transition-colors duration-200;
        }
        .pagination-link.active {
            @apply bg-blue-600 text-white;
        }
        .pagination-link:not(.active):hover {
            @apply bg-gray-700 text-white;
        }
        .disabled-link {
            @apply opacity-50 cursor-not-allowed;
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex flex-col items-center">
        <!-- Replaced teacher_header with a simple header -->
         

        <div class="container mx-auto mt-28 mb-28 p-4 md:p-8">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <h1 class="text-3xl md:text-4xl font-bold text-white">Manage All Assignments</h1>
                <!-- Removed "Create New Assignment" button for admin view -->
            </div>

            <?php if ($message): ?>
            <div class="mb-4 p-3 rounded-lg bg-green-500/80 text-center"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- List of Assignments -->
            <div class="glassmorphism rounded-2xl p-4 md:p-6">
                <h2 class="text-2xl font-bold mb-4">All Assignments</h2>
                <?php if (empty($assignments)): ?>
                    <p class="text-center text-white/70 py-10">No assignments found.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($assignments as $assignment): ?>
                        <div class="glassmorphism rounded-xl p-5 flex flex-col">
                            <h3 class="text-xl font-bold text-teal-300"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p class="text-sm text-white/80 mt-1">
                                Assigned by: <span class="font-semibold"><?php echo htmlspecialchars($assignment['teacher_name']); ?></span>
                            </p>
                            <p class="text-sm text-white/80 mt-1">
                                Class: <span class="font-semibold"><?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['section_name']); ?></span> | Subject: <span class="font-semibold"><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                            </p>
                            <p class="text-xs text-white/60 mt-3 flex-grow"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                            <div class="border-t border-white/10 my-3"></div>
                            <div class="text-sm">
                                <p><i class="fas fa-calendar-day w-5 mr-1 text-white/70"></i> Due Date: <strong class="<?php echo (strtotime($assignment['due_date']) < time()) ? 'text-red-400' : ''; ?>"><?php echo date('d M, Y', strtotime($assignment['due_date'])); ?></strong></p>
                                <?php if($assignment['file_url']): ?>
                                <p><i class="fas fa-paperclip w-5 mr-1 text-white/70"></i> Attachment: <a href="<?php echo htmlspecialchars($assignment['file_url']); ?>" target="_blank" class="text-blue-400 hover:underline">View File</a></p>
                                <?php endif; ?>
                                <?php if (!empty($assignment['last_updated_admin_name'])): ?>
                                <p class="text-xs text-white/50 mt-1">Last Updated by: <strong class="text-white/70"><?php echo htmlspecialchars($assignment['last_updated_admin_name']); ?></strong> on <?php echo date('d M, Y H:i', strtotime($assignment['updated_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-end gap-2 mt-4">
                                <button onclick='openEditModal(<?php echo json_encode($assignment); ?>)' class="text-xs bg-white/10 hover:bg-white/20 px-3 py-1 rounded-full">Edit</button>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this assignment?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                    <button type="submit" class="text-xs bg-red-500/50 hover:bg-red-500/80 px-3 py-1 rounded-full">Delete</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center items-center space-x-2 mt-8 text-white">
                        <!-- Previous Button -->
                        <a href="?page=<?php echo max(1, $current_page - 1); ?>" class="pagination-link <?php echo ($current_page <= 1) ? 'disabled-link' : ''; ?>">Previous</a>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1) {
                            echo '<a href="?page=1" class="pagination-link">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="px-2">...</span>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=<?php echo $i; ?>" class="pagination-link <?php echo ($i === $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="px-2">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '" class="pagination-link">' . $total_pages . '</a>';
                        }
                        ?>

                        <!-- Next Button -->
                        <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>" class="pagination-link <?php echo ($current_page >= $total_pages) ? 'disabled-link' : ''; ?>">Next</a>
                    </div>
                    <p class="text-center text-sm text-white/70 mt-4">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records.
                    </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        
    </div>


<!-- Create/Edit Modal -->
<div id="assignmentModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="glassmorphism rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex-shrink-0 flex justify-between items-center p-4 border-b border-white/20">
            <h3 id="modalTitle" class="text-xl font-bold">Edit Assignment</h3>
            <button onclick="document.getElementById('assignmentModal').classList.add('hidden')" class="text-2xl">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form id="assignmentForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="update">
                <input type="hidden" name="assignment_id" id="assignmentId">
                <input type="hidden" name="existing_file_url" id="existingFileUrl">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="class_id" class="block font-semibold mb-2">Class</label>
                        <select id="class_id" name="class_id" class="w-full h-12 form-select rounded-lg" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach($all_classes as $class): // Use $all_classes for admin ?>
                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="subject_id" class="block font-semibold mb-2">Subject</label>
                        <select id="subject_id" name="subject_id" class="w-full h-12 form-select rounded-lg" required disabled>
                            <option value="">-- Select Class First --</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label for="title" class="block font-semibold mb-2">Title</label>
                    <input type="text" id="title" name="title" class="w-full h-12 form-input rounded-lg" required>
                </div>
                <div class="mt-4">
                    <label for="description" class="block font-semibold mb-2">Description / Instructions</label>
                    <textarea id="description" name="description" rows="4" class="w-full form-textarea rounded-lg"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="due_date" class="block font-semibold mb-2">Due Date</label>
                        <input type="date" id="due_date" name="due_date" class="w-full h-12 form-input rounded-lg" required>
                    </div>
                    <div>
                        <label for="assignment_file" class="block font-semibold mb-2">Attach File (Optional)</label>
                        <input type="file" id="assignment_file" name="assignment_file" class="w-full h-12 text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-500/50 file:text-blue-100 hover:file:bg-blue-500/80">
                        <p id="currentFile" class="text-xs mt-2 text-white/60"></p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('assignmentModal').classList.add('hidden')" class="bg-gray-500 hover:bg-gray-600 font-bold py-2 px-4 rounded-lg">Cancel</button>
                    <button type="submit" id="submitButton" class="bg-blue-600 hover:bg-blue-700 font-bold py-2 px-4 rounded-lg">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('assignmentModal');
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
            // Adjust path to ajax_get_subjects.php if needed (e.g., if it's in a different folder)
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

    async function openEditModal(assignment) {
        form.reset();
        document.getElementById('modalTitle').textContent = 'Edit Assignment';
        document.getElementById('formAction').value = 'update';
        document.getElementById('submitButton').textContent = 'Save Changes';
        
        // Populate fields
        document.getElementById('assignmentId').value = assignment.id;
        document.getElementById('title').value = assignment.title;
        document.getElementById('description').value = assignment.description;
        document.getElementById('due_date').value = assignment.due_date;
        document.getElementById('class_id').value = assignment.class_id;

        // Populate and select subject
        // Trigger the change event on class_id to load subjects for the selected class
        classSelect.dispatchEvent(new Event('change'));
        
        // Use a slight delay to allow subjects to load via AJAX before setting the value
        setTimeout(() => {
            document.getElementById('subject_id').value = assignment.subject_id;
        }, 300); // Increased delay slightly for network latency

        // Handle existing file
        if (assignment.file_url) {
            document.getElementById('existingFileUrl').value = assignment.file_url;
            document.getElementById('currentFile').innerHTML = `Current file: <a href="${assignment.file_url}" target="_blank" class="text-blue-400">View</a>. Upload a new file to replace it.`;
        } else {
            document.getElementById('currentFile').textContent = 'No file currently attached.';
        }

        modal.classList.remove('hidden');
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.classList.add('hidden');
        }
    }
</script>
</body>
</html>

<?php
require_once './admin_footer.php';
?>