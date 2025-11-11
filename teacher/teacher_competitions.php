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
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- Fetch all active competitions ---
$competitions = [];
$sql_competitions = "SELECT
                  c.*,
                  t.full_name AS teacher_name,
                  (SELECT COUNT(*) FROM competition_participants WHERE competition_id = c.id) AS participant_count
              FROM competitions c
              LEFT JOIN teachers t ON c.teacher_in_charge_id = t.id
              WHERE c.status IN ('Upcoming', 'Active')
              ORDER BY c.competition_date ASC";

if ($result = mysqli_query($link, $sql_competitions)) {
    $competitions = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Competitions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-bold text-gray-800 tracking-tight">School Competitions</h1>
        <p class="text-gray-600 mt-2 text-lg">View details and participant lists for upcoming school competitions.</p>
    </div>

    <?php if (empty($competitions)): ?>
        <div class="text-center py-16 px-6 bg-white rounded-2xl shadow-md">
            <i class="fas fa-trophy text-5xl text-gray-400"></i>
            <h3 class="text-2xl font-bold text-gray-700 mt-4">No Competitions Found</h3>
            <p class="mt-2 text-gray-500">There are no active or upcoming competitions scheduled at this time.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($competitions as $comp): ?>
                <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 flex flex-col">
                    <img class="h-48 w-full object-cover rounded-t-xl" src="<?php echo htmlspecialchars($comp['image_url'] ?? 'https://via.placeholder.com/400x200.png/d1d5db/ffffff?text=Competition'); ?>" alt="<?php echo htmlspecialchars($comp['name']); ?>">
                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="font-bold text-xl text-gray-900"><?php echo htmlspecialchars($comp['name']); ?></h3>
                        <p class="text-gray-600 mt-2 text-sm flex-grow"><?php echo htmlspecialchars($comp['description']); ?></p>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-gray-500 space-y-2">
                            <p><i class="fas fa-calendar-day w-5 mr-1 text-gray-400"></i> <strong>Event Date:</strong> <?php echo date("F j, Y", strtotime($comp['competition_date'])); ?></p>
                            <p><i class="fas fa-map-marker-alt w-5 mr-1 text-gray-400"></i> <strong>Location:</strong> <?php echo htmlspecialchars($comp['location'] ?? 'TBD'); ?></p>
                            <p><i class="fas fa-user-tie w-5 mr-1 text-gray-400"></i> <strong>Advisor:</strong> <?php echo htmlspecialchars($comp['teacher_name'] ?? 'N/A'); ?></p>
                            <p><i class="fas fa-users w-5 mr-1 text-gray-400"></i> 
                                <strong>Participants:</strong> 
                                <span class="font-semibold text-gray-800"><?php echo $comp['participant_count']; ?></span>
                                <?php if ($comp['participant_limit']): ?>
                                    / <?php echo $comp['participant_limit']; ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 rounded-b-xl">
                        <button onclick='viewParticipants(<?php echo $comp["id"]; ?>, "<?php echo htmlspecialchars(addslashes($comp["name"])); ?>")' class="w-full bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
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
            <div class="px-6 py-4 border-b"><h3 id="participantsModalTitle" class="text-xl font-bold text-gray-900">Competition Participants</h3></div>
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

    async function viewParticipants(competitionId, competitionName) {
        const modal = document.getElementById('participantsModal');
        const title = document.getElementById('participantsModalTitle');
        const container = document.getElementById('participantsListContainer');

        title.textContent = `Participants for "${competitionName}"`;
        container.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i><p class="mt-2">Loading...</p></div>';
        modal.classList.remove('hidden');

        try {
            // Fetch from the same file with a query parameter to trigger the API handler
            const response = await fetch(`teacher_competitions.php?action=get_participants&competition_id=${competitionId}`);
            const participants = await response.json();

            if (participants.error) {
                throw new Error(participants.error);
            }

            if (participants.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center">No students have registered for this competition yet.</p>';
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