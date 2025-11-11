<?php
session_start();
require_once "../database/config.php";

// --- AJAX HANDLER: For fetching participant data ---
if (isset($_GET['action']) && $_GET['action'] === 'get_participants') {
    header('Content-Type: application/json');

    // Authentication for this "API" endpoint
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $teacher_id = $_SESSION["id"];
    $program_id = $_GET['program_id'] ?? 0;

    // Security Check: Ensure the teacher is the advisor for the requested program
    $sql_verify = "SELECT id FROM cultural_programs WHERE id = ? AND teacher_in_charge_id = ?";
    $stmt_verify = mysqli_prepare($link, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $program_id, $teacher_id);
    mysqli_stmt_execute($stmt_verify);
    mysqli_stmt_store_result($stmt_verify);

    if (mysqli_stmt_num_rows($stmt_verify) == 0) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not authorized to view these participants.']);
        exit;
    }

    $participants = [];
    $sql = "SELECT s.first_name, s.last_name, s.registration_number, c.class_name, c.section_name, pp.registration_date
            FROM program_participants pp
            JOIN students s ON pp.student_id = s.id
            JOIN classes c ON s.class_id = c.id
            WHERE pp.program_id = ? ORDER BY s.first_name, s.last_name";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $program_id);
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
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- Fetch all programs assigned to this teacher ---
$programs = [];
$sql_programs = "SELECT
                  cp.*,
                  (SELECT COUNT(*) FROM program_participants WHERE program_id = cp.id) AS participant_count
              FROM cultural_programs cp
              WHERE cp.teacher_in_charge_id = ?
              ORDER BY cp.program_date DESC";

if ($stmt = mysqli_prepare($link, $sql_programs)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $programs = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Cultural Programs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-bold text-gray-800 tracking-tight">My Advised Programs</h1>
        <p class="text-gray-600 mt-2 text-lg">Details and participant lists for programs you are advising.</p>
    </div>

    <?php if (empty($programs)): ?>
        <div class="text-center py-16 px-6 bg-white rounded-2xl shadow-md">
            <i class="fas fa-calendar-times text-5xl text-gray-400"></i>
            <h3 class="text-2xl font-bold text-gray-700 mt-4">No Programs Assigned</h3>
            <p class="mt-2 text-gray-500">You are not currently assigned as an advisor for any cultural programs.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($programs as $program): ?>
                <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 flex flex-col">
                    <img class="h-48 w-full object-cover rounded-t-xl" src="<?php echo htmlspecialchars($program['image_url'] ?? 'https://via.placeholder.com/400x200.png/d1d5db/ffffff?text=Event'); ?>" alt="<?php echo htmlspecialchars($program['name']); ?>">
                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="font-bold text-xl text-gray-900"><?php echo htmlspecialchars($program['name']); ?></h3>
                        <p class="text-gray-600 mt-2 text-sm flex-grow"><?php echo htmlspecialchars($program['description']); ?></p>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-gray-500 space-y-2">
                            <p><i class="fas fa-calendar-day w-5 mr-1 text-gray-400"></i> <strong>Event Date:</strong> <?php echo date("F j, Y", strtotime($program['program_date'])); ?></p>
                            <p><i class="fas fa-map-marker-alt w-5 mr-1 text-gray-400"></i> <strong>Location:</strong> <?php echo htmlspecialchars($program['location'] ?? 'TBD'); ?></p>
                            <p><i class="fas fa-users w-5 mr-1 text-gray-400"></i> 
                                <strong>Participants:</strong> 
                                <span class="font-semibold text-gray-800"><?php echo $program['participant_count']; ?></span>
                                <?php if ($program['participant_limit']): ?>
                                    / <?php echo $program['participant_limit']; ?>
                                <?php endif; ?>
                            </p>
                            <p><i class="fas fa-flag w-5 mr-1 text-gray-400"></i> 
                                <strong>Status:</strong> 
                                <span class="font-semibold text-blue-600 capitalize"><?php echo htmlspecialchars($program['status']); ?></span>
                            </p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 rounded-b-xl">
                        <button onclick='viewParticipants(<?php echo $program["id"]; ?>, "<?php echo htmlspecialchars(addslashes($program["name"])); ?>")' class="w-full bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-users mr-2"></i>View Participants
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- View Participants Modal -->
<div id="participantsModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('participantsModal')"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-4xl w-full z-10">
            <div class="px-6 py-4 border-b"><h3 id="participantsModalTitle" class="text-xl font-bold text-gray-900">Program Participants</h3></div>
            <div id="participantsListContainer" class="p-6 max-h-[70vh] overflow-y-auto">
                <!-- Participant list will be loaded here by JavaScript -->
            </div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end">
                <button type="button" onclick="closeModal('participantsModal')" class="bg-white py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    async function viewParticipants(programId, programName) {
        const modal = document.getElementById('participantsModal');
        const title = document.getElementById('participantsModalTitle');
        const container = document.getElementById('participantsListContainer');

        title.textContent = `Participants for "${programName}"`;
        container.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i><p class="mt-2">Loading...</p></div>';
        modal.classList.remove('hidden');

        try {
            // Fetch from the same file with a query parameter to trigger the API handler
            const response = await fetch(`teacher_cultural_programs.php?action=get_participants&program_id=${programId}`);
            const participants = await response.json();

            if (participants.error) {
                throw new Error(participants.error);
            }

            if (participants.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center">No students have registered for this program yet.</p>';
            } else {
                let tableHTML = '<table class="min-w-full divide-y divide-gray-200"><thead><tr><th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Name</th><th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Reg. No.</th><th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Class</th><th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Registered On</th></tr></thead><tbody>';
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
<?php require_once './teacher_footer.php'; ?>