<?php
session_start();
require_once "../database/config.php";

// --- AJAX HANDLER: For fetching participant data ---
if (isset($_GET['action']) && $_GET['action'] === 'get_participants') {
    header('Content-Type: application/json');

    // Authentication for API endpoint
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $competition_id = $_GET['competition_id'] ?? 0;
    if (!$competition_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Competition ID is required']);
        exit;
    }

    $participants = [];
    $sql = "SELECT s.first_name, s.last_name, s.registration_number, c.class_name, c.section_name, cp.registration_date
            FROM competition_participants cp
            JOIN students s ON cp.student_id = s.id
            JOIN classes c ON s.class_id = c.id
            WHERE cp.competition_id = ? ORDER BY s.first_name, s.last_name";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $competition_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $participants = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }

    echo json_encode($participants);
    exit; // Stop script execution after sending JSON data
}


// --- FULL PAGE LOGIC ---

// --- Authentication for the main page ---
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

// --- HANDLE POST ACTIONS (CREATE, UPDATE, DELETE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- DELETE ACTION ---
    if (isset($_POST['delete_competition'])) {
        $competition_id = $_POST['competition_id'];
        $sql = "DELETE FROM competitions WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $competition_id);
            $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Competition deleted successfully." : "Error deleting competition.";
        }
    }
    // --- CREATE / UPDATE ACTION ---
    else {
        $competition_id = $_POST['competition_id'] ?? null;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $teacher_id = !empty($_POST['teacher_in_charge_id']) ? trim($_POST['teacher_in_charge_id']) : null;
        $competition_date = trim($_POST['competition_date']);
        $location = trim($_POST['location']);
        $reg_deadline = !empty($_POST['registration_deadline']) ? trim($_POST['registration_deadline']) : null;
        $limit = !empty($_POST['participant_limit']) ? trim($_POST['participant_limit']) : null;
        $status = trim($_POST['status']);
        $image_url = $_POST['current_image_url'] ?? null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $image_url = "https://via.placeholder.com/400x200.png/1d4ed8/ffffff?text=Competition";
        }

        if (empty($name) || empty($competition_date)) {
            $_SESSION['error_message'] = "Competition Name and Date are required.";
        } else {
            if ($competition_id) { // UPDATE
                $sql = "UPDATE competitions SET name=?, description=?, teacher_in_charge_id=?, competition_date=?, location=?, image_url=?, registration_deadline=?, participant_limit=?, status=? WHERE id=?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssisssisis", $name, $description, $teacher_id, $competition_date, $location, $image_url, $reg_deadline, $limit, $status, $competition_id);
                    $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Competition updated successfully." : "Error updating competition.";
                }
            } else { // CREATE
                $sql = "INSERT INTO competitions (name, description, teacher_in_charge_id, competition_date, location, image_url, registration_deadline, participant_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssisssiss", $name, $description, $teacher_id, $competition_date, $location, $image_url, $reg_deadline, $limit, $status);
                    $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Competition created successfully." : "Error creating competition.";
                }
            }
        }
    }
    header("location: admin_competitions.php");
    exit;
}

// --- FETCH ALL COMPETITIONS FOR DISPLAY ---
$competitions = [];
$sql_competitions = "SELECT c.*, t.full_name AS teacher_name, (SELECT COUNT(*) FROM competition_participants WHERE competition_id = c.id) AS participant_count FROM competitions c LEFT JOIN teachers t ON c.teacher_in_charge_id = t.id ORDER BY c.competition_date DESC";
if ($result = mysqli_query($link, $sql_competitions)) {
    $competitions = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
    <title>Manage Competitions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Manage Competitions</h1>
        <button onclick="openModal('competitionModal')" class="bg-blue-600 text-white font-semibold py-2 px-5 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
            <i class="fas fa-plus"></i> New Competition
        </button>
    </div>

    <?php if ($success_message || $error_message): ?> <div class="mb-6 rounded-lg p-4 font-bold text-center <?php echo $success_message ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>"><?php if ($success_message) echo htmlspecialchars($success_message); else echo htmlspecialchars($error_message); ?></div> <?php endif; ?>

    <div class="bg-white rounded-xl shadow-md overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50 border-b"><tr><th class="p-4 text-left text-sm font-semibold text-gray-600">Competition Name</th><th class="p-4 text-left text-sm font-semibold text-gray-600">Date</th><th class="p-4 text-left text-sm font-semibold text-gray-600">Advisor</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Participants</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Status</th><th class="p-4 text-center text-sm font-semibold text-gray-600">Actions</th></tr></thead>
            <tbody>
                <?php if (empty($competitions)): ?><tr><td colspan="6" class="p-8 text-center text-gray-500">No competitions found.</td></tr>
                <?php else: foreach ($competitions as $comp): ?>
                    <tr class="hover:bg-gray-50 border-b">
                        <td class="p-4 font-medium text-gray-800"><?php echo htmlspecialchars($comp['name']); ?></td>
                        <td class="p-4 text-gray-600"><?php echo date("M j, Y", strtotime($comp['competition_date'])); ?></td>
                        <td class="p-4 text-gray-600"><?php echo htmlspecialchars($comp['teacher_name'] ?? 'N/A'); ?></td>
                        <td class="p-4 text-center text-gray-600"><span class="font-semibold"><?php echo $comp['participant_count']; ?></span><?php if ($comp['participant_limit']): ?> / <?php echo $comp['participant_limit']; ?><?php endif; ?></td>
                        <td class="p-4 text-center"><span class="text-xs font-semibold py-1 px-3 rounded-full bg-blue-100 text-blue-800 capitalize"><?php echo htmlspecialchars($comp['status']); ?></span></td>
                        <td class="p-4 text-center"><div class="flex justify-center items-center gap-2"><button onclick='viewParticipants(<?php echo $comp["id"]; ?>, "<?php echo htmlspecialchars(addslashes($comp["name"])); ?>")' class="w-9 h-9 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="View Participants"><i class="fas fa-users"></i></button><button onclick='editCompetition(<?php echo json_encode($comp, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="w-9 h-9 rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition" title="Edit"><i class="fas fa-pencil-alt"></i></button><button onclick='confirmDelete(<?php echo $comp["id"]; ?>, "<?php echo htmlspecialchars(addslashes($comp["name"])); ?>")' class="w-9 h-9 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition" title="Delete"><i class="fas fa-trash-alt"></i></button></div></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create/Edit Competition Modal -->
<div id="competitionModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4"><div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('competitionModal')"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-3xl w-full z-10">
            <form id="competitionForm" action="admin_competitions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="competition_id" id="competition_id"><input type="hidden" name="current_image_url" id="current_image_url">
                <div class="px-6 py-4 border-b"><h3 id="modalTitle" class="text-xl font-bold text-gray-900">Create New Competition</h3></div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label for="name" class="block text-sm font-medium text-gray-700">Competition Name</label><input type="text" name="name" id="name" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg" required></div>
                    <div><label for="teacher_in_charge_id" class="block text-sm font-medium text-gray-700">Teacher/Advisor</label><select name="teacher_in_charge_id" id="teacher_in_charge_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 rounded-lg"><option value="">-- Select an Advisor --</option><?php foreach ($teachers as $teacher): ?><option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="md:col-span-2"><label for="description" class="block text-sm font-medium text-gray-700">Description</label><textarea name="description" id="description" rows="4" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg"></textarea></div>
                    <div><label for="competition_date" class="block text-sm font-medium text-gray-700">Competition Date</label><input type="date" name="competition_date" id="competition_date" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg" required></div>
                    <div><label for="location" class="block text-sm font-medium text-gray-700">Location</label><input type="text" name="location" id="location" placeholder="e.g., School Auditorium" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg"></div>
                    <div><label for="registration_deadline" class="block text-sm font-medium text-gray-700">Registration Deadline</label><input type="date" name="registration_deadline" id="registration_deadline" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg"></div>
                    <div><label for="participant_limit" class="block text-sm font-medium text-gray-700">Participant Limit</label><input type="number" name="participant_limit" id="participant_limit" placeholder="Leave blank for no limit" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg"></div>
                    <div><label for="status" class="block text-sm font-medium text-gray-700">Status</label><select name="status" id="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 rounded-lg"><option value="Upcoming">Upcoming</option><option value="Active">Active</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select></div>
                    <div><label for="image" class="block text-sm font-medium text-gray-700">Competition Image/Banner</label><input type="file" name="image" id="image" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end gap-3"><button type="button" onclick="closeModal('competitionModal')" class="bg-white py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button><button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Save Competition</button></div>
            </form>
        </div>
    </div>
</div>

<!-- View Participants Modal -->
<div id="participantsModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4"><div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('participantsModal')"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-4xl w-full z-10">
            <div class="px-6 py-4 border-b"><h3 id="participantsModalTitle" class="text-xl font-bold text-gray-900">Competition Participants</h3></div>
            <div id="participantsListContainer" class="p-6 max-h-[70vh] overflow-y-auto"></div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end"><button type="button" onclick="closeModal('participantsModal')" class="bg-white py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button></div>
        </div>
    </div>
</div>

<script>
    function openModal(modalId) {
        document.getElementById('competitionForm').reset();
        document.getElementById('competition_id').value = '';
        document.getElementById('modalTitle').textContent = 'Create New Competition';
        document.getElementById(modalId).classList.remove('hidden');
    }
    function closeModal(modalId) { document.getElementById(modalId).classList.add('hidden'); }

    function editCompetition(data) {
        const form = document.getElementById('competitionForm');
        form.reset();
        document.getElementById('modalTitle').textContent = 'Edit Competition';
        document.getElementById('competition_id').value = data.id;
        document.getElementById('name').value = data.name;
        document.getElementById('description').value = data.description;
        document.getElementById('teacher_in_charge_id').value = data.teacher_in_charge_id || '';
        document.getElementById('competition_date').value = data.competition_date;
        document.getElementById('location').value = data.location;
        document.getElementById('registration_deadline').value = data.registration_deadline;
        document.getElementById('participant_limit').value = data.participant_limit || '';
        document.getElementById('status').value = data.status;
        document.getElementById('current_image_url').value = data.image_url;
        document.getElementById('competitionModal').classList.remove('hidden');
    }

    function confirmDelete(id, name) { if (confirm(`Are you sure you want to delete "${name}"?`)) { const form = document.createElement('form'); form.method = 'POST'; form.action = 'admin_competitions.php'; form.innerHTML = `<input type="hidden" name="competition_id" value="${id}"><input type="hidden" name="delete_competition" value="1">`; document.body.appendChild(form); form.submit(); } }

    async function viewParticipants(competitionId, competitionName) {
        const modal = document.getElementById('participantsModal');
        const title = document.getElementById('participantsModalTitle');
        const container = document.getElementById('participantsListContainer');

        title.textContent = `Participants for "${competitionName}"`;
        container.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i><p class="mt-2">Loading...</p></div>';
        modal.classList.remove('hidden');

        try {
            const response = await fetch(`admin_competitions.php?action=get_participants&competition_id=${competitionId}`);
            const participants = await response.json();
            if (participants.error) throw new Error(participants.error);

            if (participants.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center">No students have registered yet.</p>';
            } else {
                let tableHTML = '<table class="min-w-full divide-y divide-gray-200"><thead><tr><th class="px-4 py-2 text-left">Name</th><th class="px-4 py-2 text-left">Reg. No.</th><th class="px-4 py-2 text-left">Class</th><th class="px-4 py-2 text-left">Registered On</th></tr></thead><tbody>';
                participants.forEach(p => {
                    tableHTML += `<tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium text-gray-800">${p.first_name} ${p.last_name}</td>
                        <td class="px-4 py-2 text-gray-600">${p.registration_number}</td>
                        <td class="px-4 py-2 text-gray-600">${p.class_name} - ${p.section_name}</td>
                        <td class="px-4 py-2 text-gray-600">${new Date(p.registration_date).toLocaleString()}</td>
                    </tr>`;
                });
                tableHTML += '</tbody></table>';
                container.innerHTML = tableHTML;
            }
        } catch (error) {
            container.innerHTML = `<p class="text-red-500 text-center font-semibold">Failed to load participants: ${error.message}</p>`;
        }
    }
</script>

</body>
</html>
<?php require_once './admin_footer.php'; ?>