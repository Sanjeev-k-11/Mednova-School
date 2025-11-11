<?php
session_start();
require_once "../database/config.php";
// You might have an upload handler function, e.g., for Cloudinary
// require_once "../database/cloudinary_upload_handler.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// --- PRE-FETCH DATA for form dropdowns ---
$teachers = [];
$sql_teachers = "SELECT id, full_name FROM teachers ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_teachers)) {
    $teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$departments = []; // Assuming you might want to add departments later
// ... code to fetch departments if needed ...

// --- HANDLE POST ACTIONS (CREATE, UPDATE, DELETE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- DELETE ACTION ---
    if (isset($_POST['delete_program'])) {
        $program_id = $_POST['program_id'];
        $sql = "DELETE FROM cultural_programs WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $program_id);
            $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Program deleted successfully." : "Error deleting program.";
            mysqli_stmt_close($stmt);
        }
    }
    // --- CREATE / UPDATE ACTION ---
    else {
        $program_id = $_POST['program_id'] ?? null;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $teacher_id = !empty($_POST['teacher_in_charge_id']) ? trim($_POST['teacher_in_charge_id']) : null;
        $program_date = trim($_POST['program_date']); // NOTE: variable name changed for clarity
        $status = trim($_POST['status']);
        $image_url = $_POST['current_image_url'] ?? null;
        $location = trim($_POST['location']);
        $reg_deadline = !empty($_POST['registration_deadline']) ? trim($_POST['registration_deadline']) : null;
        $limit = !empty($_POST['participant_limit']) ? trim($_POST['participant_limit']) : null;


        // --- Image Upload Logic ---
        // TODO: Replace this placeholder with actual image upload logic (e.g., to Cloudinary).
        // The commented-out require_once suggests Cloudinary might be the intended destination.
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $image_url = "https://via.placeholder.com/400x200.png/10b981/ffffff?text=New+Image";
        }

        // Server-side validation for required fields
        if (empty($name) || empty($status) || empty($program_date)) {
            $_SESSION['error_message'] = "Program Name, Date, and Status are required.";
        } else {
            if ($program_id) { // UPDATE
                $sql = "UPDATE cultural_programs SET name=?, description=?, teacher_in_charge_id=?, program_date=?, location=?, image_url=?, registration_deadline=?, participant_limit=?, status=? WHERE id=?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssisssisis", $name, $description, $teacher_id, $program_date, $location, $image_url, $reg_deadline, $limit, $status, $program_id);
                    $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Program updated successfully." : "Error updating program.";
                    mysqli_stmt_close($stmt);
                }
            } else { // CREATE
                $sql = "INSERT INTO cultural_programs (name, description, teacher_in_charge_id, program_date, location, image_url, registration_deadline, participant_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssisssiss", $name, $description, $teacher_id, $program_date, $location, $image_url, $reg_deadline, $limit, $status);
                    $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Program created successfully." : "Error creating program.";
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
    header("location: admin_cultural_programs.php");
    exit;
}

// --- FETCH ALL PROGRAMS FOR DISPLAY ---
$programs = [];
$sql_programs = "SELECT cp.*, t.full_name AS teacher_name, (SELECT COUNT(*) FROM program_participants WHERE program_id = cp.id) AS participant_count FROM cultural_programs cp LEFT JOIN teachers t ON cp.teacher_in_charge_id = t.id ORDER BY cp.program_date DESC";

if ($result_programs = mysqli_query($link, $sql_programs)) {
    $programs = mysqli_fetch_all($result_programs, MYSQLI_ASSOC);
}

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Cultural Programs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Manage Cultural Programs</h1>
        <button onclick="openModal('programModal')" class="bg-blue-600 text-white font-semibold py-2 px-5 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
            <i class="fas fa-plus"></i> New Program
        </button>
    </div>

    <?php if ($success_message || $error_message): ?>
        <div class="mb-6 rounded-lg p-4 font-bold text-center <?php echo $success_message ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php if ($success_message): echo '<i class="fas fa-check-circle mr-2"></i>' . htmlspecialchars($success_message); endif; ?>
            <?php if ($error_message): echo '<i class="fas fa-exclamation-triangle mr-2"></i>' . htmlspecialchars($error_message); endif; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-md overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50 border-b"><tr><th class="p-4 text-left text-sm font-semibold text-gray-600">Program Name</th><th class="p-4 text-left text-sm font-semibold text-gray-600">Date</th><th class="p-4 text-left text-sm font-semibold text-gray-600">Advisor</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Participants</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Status</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Actions</th></tr></thead>
            <tbody>
                <?php if (empty($programs)): ?><tr><td colspan="6" class="p-8 text-center text-gray-500">No programs found.</td></tr>
                <?php else: foreach ($programs as $program): ?>
                    <tr class="hover:bg-gray-50 border-b">
                        <td class="p-4 font-medium text-gray-800"><?php echo htmlspecialchars($program['name']); ?></td>
                        <td class="p-4 text-gray-600"><?php echo date("M j, Y", strtotime($program['program_date'])); ?></td>
                        <td class="p-4 text-gray-600"><?php echo htmlspecialchars($program['teacher_name'] ?? 'N/A'); ?></td>
                        <td class="p-4 text-center text-gray-600"><span class="font-semibold"><?php echo $program['participant_count']; ?></span><?php if ($program['participant_limit']): ?> / <?php echo $program['participant_limit']; ?><?php endif; ?></td>
                        <td class="p-4 text-center"><span class="text-xs font-semibold py-1 px-3 rounded-full bg-blue-100 text-blue-800 capitalize"><?php echo htmlspecialchars($program['status']); ?></span></td>
                        <td class="p-4 text-center"><div class="flex justify-center items-center gap-2"><button onclick='editProgram(<?php echo json_encode($program, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="w-9 h-9 rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition" title="Edit"><i class="fas fa-pencil-alt"></i></button><button onclick='confirmDelete(<?php echo $program["id"]; ?>, "<?php echo htmlspecialchars(addslashes($program["name"])); ?>")' class="w-9 h-9 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition" title="Delete"><i class="fas fa-trash-alt"></i></button></div></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create/Edit Program Modal -->
<div id="programModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('programModal')"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-3xl w-full z-10">
            <form id="programForm" action="admin_cultural_programs.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="program_id" id="program_id">
                <input type="hidden" name="current_image_url" id="current_image_url">
                <div class="px-6 py-4 border-b">
                    <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Create New Program</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Program Name</label>
                        <input type="text" name="name" id="name" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label for="teacher_in_charge_id" class="block text-sm font-medium text-gray-700">Teacher/Advisor</label>
                        <select name="teacher_in_charge_id" id="teacher_in_charge_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 rounded-lg">
                            <option value="">-- Select an Advisor --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="description" rows="4" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg"></textarea>
                    </div>
                    <div>
                        <label for="program_date" class="block text-sm font-medium text-gray-700">Program Date</label>
                        <input type="date" name="program_date" id="program_date" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                        <input type="text" name="location" id="location" placeholder="e.g., School Auditorium" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label for="registration_deadline" class="block text-sm font-medium text-gray-700">Registration Deadline</label>
                        <input type="date" name="registration_deadline" id="registration_deadline" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label for="participant_limit" class="block text-sm font-medium text-gray-700">Participant Limit</label>
                        <input type="number" name="participant_limit" id="participant_limit" placeholder="Leave blank for no limit" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 rounded-lg" required>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700">Program Image/Banner</label>
                        <input type="file" name="image" id="image" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('programModal')" class="bg-white py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Save Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Custom Confirmation Modal (replaces browser's confirm()) -->
<div id="confirmDeleteModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50"></div>
        <div class="bg-white rounded-lg p-6 shadow-xl transform transition-all max-w-sm w-full z-10 text-center">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Confirm Deletion</h3>
            <p id="confirmMessage" class="text-gray-600 mb-6"></p>
            <div class="flex justify-center gap-4">
                <button type="button" onclick="closeModal('confirmDeleteModal')" class="bg-gray-200 py-2 px-4 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</button>
                <button type="button" onclick="deleteProgram()" class="bg-red-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    let programToDelete = null;

    function openModal(modalId) {
        document.getElementById('programForm').reset();
        document.getElementById('program_id').value = '';
        document.getElementById('modalTitle').textContent = 'Create New Program';
        document.getElementById(modalId).classList.remove('hidden');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function editProgram(data) {
        const form = document.getElementById('programForm');
        form.reset();
        document.getElementById('modalTitle').textContent = 'Edit Program';
        document.getElementById('program_id').value = data.id;
        document.getElementById('name').value = data.name;
        document.getElementById('description').value = data.description;
        document.getElementById('teacher_in_charge_id').value = data.teacher_in_charge_id || '';
        document.getElementById('program_date').value = data.program_date;
        document.getElementById('location').value = data.location;
        document.getElementById('registration_deadline').value = data.registration_deadline;
        document.getElementById('participant_limit').value = data.participant_limit || '';
        document.getElementById('status').value = data.status;
        document.getElementById('current_image_url').value = data.image_url;
        document.getElementById('programModal').classList.remove('hidden');
    }

    function confirmDelete(id, name) {
        programToDelete = id;
        document.getElementById('confirmMessage').textContent = `Are you sure you want to delete "${name}"? This action cannot be undone.`;
        document.getElementById('confirmDeleteModal').classList.remove('hidden');
    }

    function deleteProgram() {
        closeModal('confirmDeleteModal');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin_cultural_programs.php';
        form.innerHTML = `<input type="hidden" name="program_id" value="${programToDelete}"><input type="hidden" name="delete_program" value="1">`;
        document.body.appendChild(form);
        form.submit();
    }
</script>

</body>
</html>
<?php require_once './admin_footer.php'; ?>
