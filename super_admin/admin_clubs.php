<?php
session_start();
require_once "../database/config.php";

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

$departments = [];
$sql_depts = "SELECT id, department_name FROM departments ORDER BY department_name ASC";
if ($result = mysqli_query($link, $sql_depts)) {
    $departments = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// --- HANDLE POST ACTIONS (CREATE, UPDATE, DELETE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- DELETE ACTION ---
    if (isset($_POST['delete_club'])) {
        $club_id = $_POST['club_id'];
        $sql = "DELETE FROM sports_clubs WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $club_id);
            $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Club deleted successfully." : "Error deleting club.";
            mysqli_stmt_close($stmt);
        }
    }
    // --- CREATE / UPDATE ACTION ---
    else {
        $club_id = $_POST['club_id'] ?? null;
        $name = trim($_POST['name']);
        $department_id = !empty($_POST['department_id']) ? trim($_POST['department_id']) : null;
        $description = trim($_POST['description']);
        $teacher_id = !empty($_POST['teacher_in_charge_id']) ? trim($_POST['teacher_in_charge_id']) : null;
        $schedule = trim($_POST['meeting_schedule']);
        $member_limit = !empty($_POST['member_limit']) ? trim($_POST['member_limit']) : null;
        $status = trim($_POST['status']);
        $image_url = $_POST['current_image_url'] ?? null;

        // --- Image Upload Logic ---
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            // In a real app, replace this with your actual file upload handler
            $image_url = "https://via.placeholder.com/400x200.png/10b981/ffffff?text=New+Image";
        }

        if (empty($name) || empty($status)) {
            $_SESSION['error_message'] = "Club Name and Status are required.";
        } else {
            if ($club_id) { // UPDATE
                $sql = "UPDATE sports_clubs SET name = ?, department_id = ?, description = ?, teacher_in_charge_id = ?, image_url = ?, meeting_schedule = ?, member_limit = ?, status = ? WHERE id = ?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sisssissi", $name, $department_id, $description, $teacher_id, $image_url, $schedule, $member_limit, $status, $club_id);
                    $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Club updated successfully." : "Error updating club.";
                    mysqli_stmt_close($stmt);
                }
            } else { // CREATE
                $sql = "INSERT INTO sports_clubs (name, department_id, description, teacher_in_charge_id, image_url, meeting_schedule, member_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sisssiss", $name, $department_id, $description, $teacher_id, $image_url, $schedule, $member_limit, $status);
                    $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Club created successfully." : "Error creating club.";
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
    header("location: admin_clubs.php");
    exit;
}

// --- FETCH ALL CLUBS FOR DISPLAY ---
$clubs = [];
$sql_clubs = "SELECT sc.*, t.full_name AS teacher_name, d.department_name, (SELECT COUNT(*) FROM club_members WHERE club_id = sc.id) AS member_count FROM sports_clubs sc LEFT JOIN teachers t ON sc.teacher_in_charge_id = t.id LEFT JOIN departments d ON sc.department_id = d.id ORDER BY sc.name ASC";
if ($result_clubs = mysqli_query($link, $sql_clubs)) {
    $clubs = mysqli_fetch_all($result_clubs, MYSQLI_ASSOC);
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
    <title>Manage Sports & Clubs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Manage Clubs</h1>
        <button onclick="openModal('clubModal')" class="bg-blue-600 text-white font-semibold py-2 px-5 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
            <i class="fas fa-plus"></i> Create New Club
        </button>
    </div>

    <!-- Success/Error Message Display -->
    <?php if ($success_message || $error_message): ?>
        <div class="mb-6 rounded-lg p-4 font-bold text-center <?php echo $success_message ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php if ($success_message): echo '<i class="fas fa-check-circle mr-2"></i>' . htmlspecialchars($success_message); endif; ?>
            <?php if ($error_message): echo '<i class="fas fa-exclamation-triangle mr-2"></i>' . htmlspecialchars($error_message); endif; ?>
        </div>
    <?php endif; ?>

    <!-- Clubs Table -->
    <div class="bg-white rounded-xl shadow-md overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50 border-b"><tr><th class="p-4 text-left text-sm font-semibold text-gray-600">Club Name</th><th class="p-4 text-left text-sm font-semibold text-gray-600">Department</th><th class="p-4 text-left text-sm font-semibold text-gray-600">Advisor</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Members</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Status</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Actions</th></tr></thead>
            <tbody>
                <?php if (empty($clubs)): ?><tr><td colspan="6" class="p-8 text-center text-gray-500">No clubs have been created yet.</td></tr>
                <?php else: foreach ($clubs as $club): ?>
                    <tr class="hover:bg-gray-50 border-b">
                        <td class="p-4 font-medium text-gray-800"><?php echo htmlspecialchars($club['name']); ?></td>
                        <td class="p-4 text-gray-600"><?php echo htmlspecialchars($club['department_name'] ?? 'N/A'); ?></td>
                        <td class="p-4 text-gray-600"><?php echo htmlspecialchars($club['teacher_name'] ?? 'N/A'); ?></td>
                        <td class="p-4 text-center text-gray-600"><span class="font-semibold"><?php echo $club['member_count']; ?></span><?php if ($club['member_limit']): ?> / <?php echo $club['member_limit']; ?><?php if ($club['member_count'] >= $club['member_limit']): ?><span class="ml-2 text-xs font-bold text-red-500">FULL</span><?php endif; ?><?php endif; ?></td>
                        <td class="p-4 text-center"><?php if ($club['status'] == 'Active'): ?><span class="text-xs font-semibold py-1 px-3 rounded-full bg-green-100 text-green-800">Active</span><?php else: ?><span class="text-xs font-semibold py-1 px-3 rounded-full bg-red-100 text-red-800">Inactive</span><?php endif; ?></td>
                        <td class="p-4 text-center"><div class="flex justify-center items-center gap-2"><button onclick='viewMembers(<?php echo $club["id"]; ?>, "<?php echo htmlspecialchars(addslashes($club["name"])); ?>")' class="w-9 h-9 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="View Members"><i class="fas fa-users"></i></button><button onclick='editClub(<?php echo json_encode($club, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="w-9 h-9 rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition" title="Edit"><i class="fas fa-pencil-alt"></i></button><button onclick='confirmDelete(<?php echo $club["id"]; ?>, "<?php echo htmlspecialchars(addslashes($club["name"])); ?>")' class="w-9 h-9 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition" title="Delete"><i class="fas fa-trash-alt"></i></button></div></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create/Edit Club Modal -->
<div id="clubModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('clubModal')"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-2xl w-full z-10">
            <form id="clubForm" action="admin_clubs.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="club_id" id="club_id">
                <input type="hidden" name="current_image_url" id="current_image_url">
                <div class="px-6 py-4 border-b"><h3 id="modalTitle" class="text-xl font-bold text-gray-900">Create New Club</h3></div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label for="department_id" class="block text-sm font-medium text-gray-700">Department <span class="text-gray-400">(Optional)</span></label><select name="department_id" id="department_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 rounded-lg"><option value="">-- Select a Department --</option><?php foreach ($departments as $dept): ?><option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option><?php endforeach; ?></select></div>
                    <div><label for="name" class="block text-sm font-medium text-gray-700">Club Name</label><input type="text" name="name" id="name" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg" required></div>
                    <div class="md:col-span-2"><label for="description" class="block text-sm font-medium text-gray-700">Description</label><textarea name="description" id="description" rows="4" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg"></textarea></div>
                    <div><label for="teacher_in_charge_id" class="block text-sm font-medium text-gray-700">Teacher/Advisor</label><select name="teacher_in_charge_id" id="teacher_in_charge_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 rounded-lg"><option value="">-- Select an Advisor --</option><?php foreach ($teachers as $teacher): ?><option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option><?php endforeach; ?></select></div>
                    <div><label for="meeting_schedule" class="block text-sm font-medium text-gray-700">Meeting Schedule</label><input type="text" name="meeting_schedule" id="meeting_schedule" placeholder="e.g., Tuesdays at 3 PM" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg"></div>
                    <div><label for="member_limit" class="block text-sm font-medium text-gray-700">Member Limit</label><input type="number" name="member_limit" id="member_limit" placeholder="Leave blank for no limit" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg"></div>
                    <div><label for="status" class="block text-sm font-medium text-gray-700">Status</label><select name="status" id="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 rounded-lg"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                    <div class="md:col-span-2"><label for="image" class="block text-sm font-medium text-gray-700">Club Logo/Image</label><input type="file" name="image" id="image" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end gap-3"><button type="button" onclick="closeModal('clubModal')" class="bg-white py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button><button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Save Club</button></div>
            </form>
        </div>
    </div>
</div>

<!-- View Members Modal (Add this whole block) -->
<div id="membersModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('membersModal')"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-4xl w-full z-10">
            <div class="px-6 py-4 border-b"><h3 id="membersModalTitle" class="text-xl font-bold text-gray-900">Club Members</h3></div>
            <div id="membersListContainer" class="p-6 max-h-[70vh] overflow-y-auto"></div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end"><button type="button" onclick="closeModal('membersModal')" class="bg-white py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button></div>
        </div>
    </div>
</div>

<script>
    const clubModal = document.getElementById('clubModal');
    const modalTitle = document.getElementById('modalTitle');
    const clubForm = document.getElementById('clubForm');

    function openModal(modalId) { clubForm.reset(); document.getElementById('club_id').value = ''; modalTitle.textContent = 'Create New Club'; document.getElementById(modalId).classList.remove('hidden'); }
    function closeModal(modalId) { document.getElementById(modalId).classList.add('hidden'); }

    function editClub(clubData) {
        clubForm.reset();
        modalTitle.textContent = 'Edit Club';
        document.getElementById('club_id').value = clubData.id;
        document.getElementById('name').value = clubData.name;
        document.getElementById('department_id').value = clubData.department_id || '';
        document.getElementById('description').value = clubData.description;
        document.getElementById('teacher_in_charge_id').value = clubData.teacher_in_charge_id || '';
        document.getElementById('meeting_schedule').value = clubData.meeting_schedule;
        document.getElementById('member_limit').value = clubData.member_limit || '';
        document.getElementById('status').value = clubData.status;
        document.getElementById('current_image_url').value = clubData.image_url;
        clubModal.classList.remove('hidden');
    }

    function confirmDelete(id, name) { if (confirm(`Are you sure you want to delete the "${name}" club?`)) { const form = document.createElement('form'); form.method = 'POST'; form.action = 'admin_clubs.php'; form.innerHTML = `<input type="hidden" name="club_id" value="${id}"><input type="hidden" name="delete_club" value="1">`; document.body.appendChild(form); form.submit(); } }

    document.getElementById('department_id').addEventListener('change', function() {
        const nameInput = document.getElementById('name');
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value && nameInput.value.trim() === '') {
            nameInput.value = selectedOption.text + ' Club';
        }
    });

    async function viewMembers(clubId, clubName) {
        const modal = document.getElementById('membersModal');
        const title = document.getElementById('membersModalTitle');
        const container = document.getElementById('membersListContainer');

        title.textContent = `Members of "${clubName}"`;
        container.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i><p class="mt-2">Loading...</p></div>';
        modal.classList.remove('hidden');

        try {
            const response = await fetch(`api_get_club_members.php?club_id=${clubId}`);
            const members = await response.json();
            if (members.error) throw new Error(members.error);

            if (members.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center">This club has no members yet.</p>';
            } else {
                let tableHTML = '<table class="min-w-full divide-y divide-gray-200"><thead><tr><th class="px-4 py-2 text-left">Name</th><th class="px-4 py-2 text-left">Reg. No.</th><th class="px-4 py-2 text-left">Class</th><th class="px-4 py-2 text-left">Join Date</th></tr></thead><tbody>';
                members.forEach(member => {
                    tableHTML += `<tr>
                        <td class="px-4 py-2 font-medium">${member.first_name} ${member.last_name}</td>
                        <td class="px-4 py-2">${member.registration_number}</td>
                        <td class="px-4 py-2">${member.class_name} - ${member.section_name}</td>
                        <td class="px-4 py-2">${new Date(member.join_date).toLocaleDateString()}</td>
                    </tr>`;
                });
                tableHTML += '</tbody></table>';
                container.innerHTML = tableHTML;
            }
        } catch (error) {
            container.innerHTML = `<p class="text-red-500 text-center">Failed to load members: ${error.message}</p>`;
        }
    }
</script>

</body>
</html>
<?php require_once './admin_footer.php'; ?>