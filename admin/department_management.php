<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./admin_header.php";     // Includes admin-specific authentication and sidebar

// --- AUTHENTICATION & AUTHORIZATION ---
// Changed to 'Super Admin' as per common practice for sensitive admin functions like department management
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php"); 
    exit;
}

$admin_id = $_SESSION['id'] ?? null; 
$flash_message = '';
$flash_message_type = ''; // 'success', 'error', 'info'

// --- HANDLE DEPARTMENT ACTIONS (Add/Edit/Delete) ---
// The form action should point to THIS file, which is admin_department_management.php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['department_action'])) {
    $action = $_POST['department_action'];
    
    if ($action === 'add' || $action === 'edit') {
        $department_name = trim($_POST['department_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $hod_teacher_id = filter_input(INPUT_POST, 'hod_teacher_id', FILTER_VALIDATE_INT);
        if ($hod_teacher_id === false || $hod_teacher_id === 0) $hod_teacher_id = NULL; // If not set, invalid, or 0, set to NULL (0 might come from default select option)

        if (empty($department_name)) {
            $flash_message = "Department Name is required.";
            $flash_message_type = 'error';
        } else {
            if ($action === 'add') {
                $sql_insert = "INSERT INTO departments (department_name, description, hod_teacher_id) VALUES (?, ?, ?)";
                if ($stmt = mysqli_prepare($link, $sql_insert)) {
                    mysqli_stmt_bind_param($stmt, "ssi", $department_name, $description, $hod_teacher_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $flash_message = "Department '{$department_name}' added successfully!";
                        $flash_message_type = 'success';
                    } else {
                        $flash_message = "Error adding department: " . mysqli_stmt_error($stmt);
                        if (mysqli_stmt_errno($stmt) == 1062) $flash_message = "Department name already exists.";
                        $flash_message_type = 'error';
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    error_log("DB Prepare Insert Dept Error: " . mysqli_error($link));
                    $flash_message = "Database error preparing add department.";
                    $flash_message_type = 'error';
                }
            } elseif ($action === 'edit') {
                $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
                if (!$department_id) { $flash_message = "Invalid Department ID for edit."; $flash_message_type = 'error'; }
                else {
                    $sql_update = "UPDATE departments SET department_name = ?, description = ?, hod_teacher_id = ? WHERE id = ?";
                    if ($stmt = mysqli_prepare($link, $sql_update)) {
                        mysqli_stmt_bind_param($stmt, "ssii", $department_name, $description, $hod_teacher_id, $department_id);
                        if (mysqli_stmt_execute($stmt)) {
                            $flash_message = "Department '{$department_name}' updated successfully!";
                            $flash_message_type = 'success';
                        } else {
                            $flash_message = "Error updating department: " . mysqli_stmt_error($stmt);
                            if (mysqli_stmt_errno($stmt) == 1062) $flash_message = "Department name already exists.";
                            $flash_message_type = 'error';
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        error_log("DB Prepare Update Dept Error: " . mysqli_error($link));
                        $flash_message = "Database error preparing edit department.";
                        $flash_message_type = 'error';
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
        if (!$department_id) { $flash_message = "Invalid Department ID for delete."; $flash_message_type = 'error'; }
        else {
            $sql_delete = "DELETE FROM departments WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql_delete)) {
                mysqli_stmt_bind_param($stmt, "i", $department_id);
                if (mysqli_stmt_execute($stmt)) {
                    $flash_message = "Department deleted successfully!";
                    $flash_message_type = 'success';
                } else {
                    $flash_message = "Error deleting department: " . mysqli_stmt_error($stmt);
                    $flash_message_type = 'error';
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("DB Prepare Delete Dept Error: " . mysqli_error($link));
                $flash_message = "Database error preparing delete department.";
                $flash_message_type = 'error';
            }
        }
    }
}

// --- HANDLE TEACHER ASSIGNMENT ACTION ---
// The form action should point to THIS file, which is admin_department_management.php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_teacher_action'])) {
    $teacher_id_to_assign = filter_input(INPUT_POST, 'teacher_id_to_assign', FILTER_VALIDATE_INT);
    $department_id_to_assign = filter_input(INPUT_POST, 'department_id_to_assign', FILTER_VALIDATE_INT);
    if ($department_id_to_assign === false || $department_id_to_assign === 0) $department_id_to_assign = NULL; // Allow unassigning (0 might come from default select option)

    if (!$teacher_id_to_assign) {
        $flash_message = "Invalid Teacher ID for assignment.";
        $flash_message_type = 'error';
    } else {
        $sql_assign = "UPDATE teachers SET department_id = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql_assign)) {
            mysqli_stmt_bind_param($stmt, "ii", $department_id_to_assign, $teacher_id_to_assign);
            if (mysqli_stmt_execute($stmt)) {
                $teacher_name_for_msg = '';
                $sql_get_teacher_name = "SELECT full_name FROM teachers WHERE id = ?";
                if($name_stmt = mysqli_prepare($link, $sql_get_teacher_name)){
                    mysqli_stmt_bind_param($name_stmt, "i", $teacher_id_to_assign);
                    mysqli_stmt_execute($name_stmt);
                    $name_result = mysqli_fetch_assoc(mysqli_stmt_get_result($name_stmt));
                    if($name_result) $teacher_name_for_msg = $name_result['full_name'];
                    mysqli_stmt_close($name_stmt);
                }
                
                $department_name_for_msg = 'No Department';
                if ($department_id_to_assign) { // Only try to get name if a department was actually assigned
                    $sql_get_dept_name = "SELECT department_name FROM departments WHERE id = ?";
                    if($dept_stmt = mysqli_prepare($link, $sql_get_dept_name)){
                        mysqli_stmt_bind_param($dept_stmt, "i", $department_id_to_assign);
                        mysqli_stmt_execute($dept_stmt);
                        $dept_result = mysqli_fetch_assoc(mysqli_stmt_get_result($dept_stmt));
                        if($dept_result) $department_name_for_msg = $dept_result['department_name'];
                        mysqli_stmt_close($dept_stmt);
                    }
                }

                $flash_message = "Teacher '{$teacher_name_for_msg}' assigned to '{$department_name_for_msg}' successfully!";
                $flash_message_type = 'success';
            } else {
                $flash_message = "Error assigning department to teacher: " . mysqli_stmt_error($stmt);
                $flash_message_type = 'error';
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log("DB Prepare Assign Teacher Error: " . mysqli_error($link));
            $flash_message = "Database error preparing teacher assignment.";
            $flash_message_type = 'error';
        }
    }
}


// --- DATA FETCHING FOR DISPLAY ---
$departments = [];
$sql_departments = "
    SELECT 
        d.id, d.department_name, d.description, d.hod_teacher_id,
        t.full_name AS hod_name
    FROM departments d
    LEFT JOIN teachers t ON d.hod_teacher_id = t.id
    ORDER BY d.department_name ASC";
if ($result = mysqli_query($link, $sql_departments)) {
    $departments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    error_log("DB Fetch Departments Error: " . mysqli_error($link));
    $flash_message = "Error loading departments list.";
    $flash_message_type = 'error';
}

$teachers = [];
$sql_teachers = "
    SELECT 
        t.id, t.full_name, t.email, t.department_id,
        d.department_name
    FROM teachers t
    LEFT JOIN departments d ON t.department_id = d.id
    ORDER BY t.full_name ASC";
if ($result = mysqli_query($link, $sql_teachers)) {
    $teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    error_log("DB Fetch Teachers Error: " . mysqli_error($link));
    $flash_message = "Error loading teachers list.";
    $flash_message_type = 'error';
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .dashboard-container { min-height: calc(100vh - 80px); }
        .toast-notification { position: fixed; top: 20px; right: 20px; z-index: 1000; opacity: 0; transform: translateY(-20px); transition: opacity 0.3s ease-out, transform 0.3s ease-out; }
        .toast-notification.show { opacity: 1; transform: translateY(0); }
        .modal-overlay { /* Added explicit display: none; for initial hide */
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0, 0, 0, 0.5); 
            display: none; /* Initially hidden */
            justify-content: center; 
            align-items: center; 
            z-index: 1000; 
        }
        .modal-content { background-color: white; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); max-width: 500px; text-align: center; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
<!-- admin_header.php content usually goes here -->

<div class="dashboard-container p-4 sm:p-6">
    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-notification"></div>

    <!-- Main Header Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Department & Teacher Management</h1>
        <p class="text-gray-600">As a Super Admin, manage school departments and assign teachers to them.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Department Management Card -->
        <div class="bg-white rounded-xl shadow-lg p-6 h-full flex flex-col">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-building mr-2 text-indigo-500"></i> Departments
            </h2>

            <button onclick="openDepartmentModal('add')" class="mb-6 bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md inline-flex items-center justify-center">
                <i class="fas fa-plus mr-2"></i> Add New Department
            </button>

            <?php if (empty($departments)): ?>
                <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 flex-grow flex flex-col items-center justify-center">
                    <i class="fas fa-boxes fa-4x mb-4 text-gray-400"></i>
                    <p class="text-xl font-semibold mb-2">No departments created yet!</p>
                    <p class="text-lg">Use the button above to add your first department.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HOD</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($departments as $dept): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($dept['hod_name'] ?: 'Not Assigned'); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick="openDepartmentModal('edit', <?php echo htmlspecialchars(json_encode($dept)); ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-edit"></i> Edit</button>
                                        <button onclick="confirmDeleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars(addslashes($dept['department_name'])); ?>')" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i> Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Teacher Department Assignment Card -->
        <div class="bg-white rounded-xl shadow-lg p-6 h-full flex flex-col">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-user-tie mr-2 text-indigo-500"></i> Assign Teachers to Departments
            </h2>
            <?php if (empty($teachers)): ?>
                <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 flex-grow flex flex-col items-center justify-center">
                    <i class="fas fa-user-times fa-4x mb-4 text-gray-400"></i>
                    <p class="text-xl font-semibold mb-2">No teachers found!</p>
                    <p class="text-lg">Please add teachers to assign them to departments.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Dept.</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assign New</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($teachers as $teacher): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($teacher['department_name'] ?: 'Unassigned'); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">
                                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="flex gap-2 items-center">
                                            <input type="hidden" name="assign_teacher_action" value="assign">
                                            <input type="hidden" name="teacher_id_to_assign" value="<?php echo $teacher['id']; ?>">
                                            <select name="department_id_to_assign" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                <option value="">-- Unassign --</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo htmlspecialchars($dept['id']); ?>" <?php echo ($teacher['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="bg-indigo-500 hover:bg-indigo-600 text-white py-1 px-3 rounded-md text-xs font-semibold">Assign</button>
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
</div>

<!-- Department Modal (Add/Edit) -->
<div id="departmentModal" class="modal-overlay hidden">
    <div class="modal-content">
        <h3 id="modalTitle" class="text-xl font-bold mb-4 text-gray-800"></h3>
        <form id="departmentForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
            <input type="hidden" name="department_action" id="departmentAction">
            <input type="hidden" name="department_id" id="departmentId">

            <div class="mb-4 text-left">
                <label for="department_name" class="block text-sm font-medium text-gray-700 mb-1">Department Name <span class="text-red-500">*</span></label>
                <input type="text" name="department_name" id="departmentName" required
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       placeholder="e.g., Mathematics">
            </div>
            <div class="mb-4 text-left">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                <textarea name="description" id="departmentDescription" rows="3"
                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                          placeholder="Brief description of the department..."></textarea>
            </div>
            <div class="mb-6 text-left">
                <label for="hod_teacher_id" class="block text-sm font-medium text-gray-700 mb-1">Head of Department (HOD) (Optional)</label>
                <select name="hod_teacher_id" id="hodTeacherId"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">-- Select HOD --</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo htmlspecialchars($teacher['id']); ?>">
                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeDepartmentModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md">Cancel</button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md">Save Department</button>
            </div>
        </form>
    </div>
</div>

<!-- admin_footer.php content usually goes here -->
<?php require_once "./admin_footer.php"; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toastContainer = document.getElementById('toast-container');
        const departmentModal = document.getElementById('departmentModal');
        const modalTitle = document.getElementById('modalTitle');
        const departmentAction = document.getElementById('departmentAction');
        const departmentId = document.getElementById('departmentId');
        const departmentName = document.getElementById('departmentName');
        const departmentDescription = document.getElementById('departmentDescription');
        const hodTeacherId = document.getElementById('hodTeacherId');

        // --- Toast Notification Function ---
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `p-4 rounded-lg shadow-lg text-white text-sm font-semibold flex items-center toast-notification`;
            let bgColor = ''; let iconClass = '';
            if (type === 'success') { bgColor = 'bg-green-500'; iconClass = 'fas fa-check-circle'; }
            else if (type === 'error') { bgColor = 'bg-red-500'; iconClass = 'fas fa-times-circle'; }
            else { bgColor = 'bg-blue-500'; iconClass = 'fas fa-info-circle'; }
            toast.classList.add(bgColor);
            toast.innerHTML = `<i class="${iconClass} mr-2"></i> ${message}`;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => { toast.classList.remove('show'); toast.addEventListener('transitionend', () => toast.remove()); }, 5000);
        }

        // --- Display initial flash message from PHP (if any) ---
        <?php if ($flash_message): ?>
            showToast("<?php echo htmlspecialchars($flash_message); ?>", "<?php echo htmlspecialchars($flash_message_type); ?>");
        <?php endif; ?>

        // --- Department Modal Functions ---
        window.openDepartmentModal = function(action, deptData = {}) {
            modalTitle.textContent = (action === 'add') ? 'Add New Department' : 'Edit Department';
            departmentAction.value = action;
            departmentId.value = deptData.id || '';
            departmentName.value = deptData.department_name || '';
            departmentDescription.value = deptData.description || '';
            // Handle HOD assignment carefully: if deptData.hod_teacher_id is null/undefined, set to empty string for select
            hodTeacherId.value = deptData.hod_teacher_id === null || deptData.hod_teacher_id === undefined ? '' : deptData.hod_teacher_id;
            
            departmentModal.style.display = 'flex'; // Explicitly show modal
            departmentModal.classList.remove('hidden'); // Ensure hidden class is removed
            
            // Focus on first input for better UX
            setTimeout(() => departmentName.focus(), 100);
        }

        window.closeDepartmentModal = function() {
            departmentModal.style.display = 'none'; // Explicitly hide modal
            departmentModal.classList.add('hidden'); // Ensure hidden class is added
            document.getElementById('departmentForm').reset(); // Clear form on close
        }

        window.confirmDeleteDepartment = function(id, name) {
            if (confirm(`Are you sure you want to delete the department "${name}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                // CORRECTED: Delete form action
                form.action = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>'; 
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'department_action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'department_id';
                idInput.value = id;
                form.appendChild(idInput);

                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // --- Add an event listener to close modal on overlay click ---
        departmentModal.addEventListener('click', function(event) {
            if (event.target === departmentModal) { // Only close if clicking the background, not inside the content
                closeDepartmentModal();
            }
        });

        // --- Initial check to ensure modal is hidden on load if it's somehow showing ---
        // This is a safety net. Ideally, it's hidden by CSS.
        departmentModal.style.display = 'none';
        departmentModal.classList.add('hidden');

    });
</script>