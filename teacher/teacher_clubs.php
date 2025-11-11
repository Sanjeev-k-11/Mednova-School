<?php
session_start();
require_once "../database/config.php";

// --- AJAX HANDLER: For fetching club member data ---
if (isset($_GET['action']) && $_GET['action'] === 'get_members') {
    header('Content-Type: application/json');

    // Authentication for this "API" endpoint
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $teacher_id = $_SESSION["id"];
    $club_id = $_GET['club_id'] ?? 0;

    // Security Check: Ensure the teacher is the advisor for the requested club
    $sql_verify = "SELECT id FROM sports_clubs WHERE id = ? AND teacher_in_charge_id = ?";
    $stmt_verify = mysqli_prepare($link, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $club_id, $teacher_id);
    mysqli_stmt_execute($stmt_verify);
    mysqli_stmt_store_result($stmt_verify);

    if (mysqli_stmt_num_rows($stmt_verify) == 0) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not authorized to view these members.']);
        exit;
    }

    $members = [];
    $sql = "SELECT s.first_name, s.last_name, s.registration_number, c.class_name, c.section_name, cm.join_date
            FROM club_members cm
            JOIN students s ON cm.student_id = s.id
            JOIN classes c ON s.class_id = c.id
            WHERE cm.club_id = ? ORDER BY s.first_name, s.last_name";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $club_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $members = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }

    echo json_encode($members);
    exit; // Stop script execution after sending JSON data
}

// --- FULL PAGE LOGIC ---

// --- Authentication for the main page ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- Fetch all clubs assigned to this teacher ---
$clubs = [];
$sql_clubs = "SELECT
                  sc.*,
                  d.department_name,
                  (SELECT COUNT(*) FROM club_members WHERE club_id = sc.id) AS member_count
              FROM sports_clubs sc
              LEFT JOIN departments d ON sc.department_id = d.id
              WHERE sc.teacher_in_charge_id = ?
              ORDER BY sc.name ASC";

if ($stmt = mysqli_prepare($link, $sql_clubs)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $clubs = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Advised Clubs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-bold text-gray-800 tracking-tight">My Advised Clubs</h1>
        <p class="text-gray-600 mt-2 text-lg">Details and member lists for clubs you are advising.</p>
    </div>

    <?php if (empty($clubs)): ?>
        <div class="text-center py-16 px-6 bg-white rounded-2xl shadow-md">
            <i class="fas fa-search-minus text-5xl text-gray-400"></i>
            <h3 class="text-2xl font-bold text-gray-700 mt-4">No Clubs Assigned</h3>
            <p class="mt-2 text-gray-500">You are not currently assigned as an advisor for any sports clubs.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($clubs as $club): ?>
                <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 flex flex-col">
                    <img class="h-48 w-full object-cover rounded-t-xl" src="<?php echo htmlspecialchars($club['image_url'] ?? 'https://via.placeholder.com/400x200.png/d1d5db/ffffff?text=Club'); ?>" alt="<?php echo htmlspecialchars($club['name']); ?>">
                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="font-bold text-xl text-gray-900"><?php echo htmlspecialchars($club['name']); ?></h3>
                        <p class="text-gray-600 mt-2 text-sm flex-grow"><?php echo htmlspecialchars($club['description']); ?></p>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-gray-500 space-y-2">
                            <p><i class="fas fa-building w-5 mr-1 text-gray-400"></i> <strong>Department:</strong> <?php echo htmlspecialchars($club['department_name'] ?? 'General'); ?></p>
                            <p><i class="fas fa-calendar-alt w-5 mr-1 text-gray-400"></i> <strong>Meets:</strong> <?php echo htmlspecialchars($club['meeting_schedule'] ?? 'TBD'); ?></p>
                            <p><i class="fas fa-users w-5 mr-1 text-gray-400"></i> 
                                <strong>Members:</strong> 
                                <span class="font-semibold text-gray-800"><?php echo $club['member_count']; ?></span>
                                <?php if ($club['member_limit']): ?>
                                    / <?php echo $club['member_limit']; ?>
                                <?php endif; ?>
                            </p>
                            <p><i class="fas fa-flag w-5 mr-1 text-gray-400"></i> 
                                <strong>Status:</strong> 
                                <span class="font-semibold text-blue-600 capitalize"><?php echo htmlspecialchars($club['status']); ?></span>
                            </p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 rounded-b-xl">
                        <button onclick='viewMembers(<?php echo $club["id"]; ?>, "<?php echo htmlspecialchars(addslashes($club["name"])); ?>")' class="w-full bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-users mr-2"></i>View Members
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- View Members Modal -->
<div id="membersModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal('membersModal')"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-4xl w-full z-10">
            <div class="px-6 py-4 border-b"><h3 id="membersModalTitle" class="text-xl font-bold text-gray-900">Club Members</h3></div>
            <div id="membersListContainer" class="p-6 max-h-[70vh] overflow-y-auto">
                <!-- Member list will be loaded here by JavaScript -->
            </div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end">
                <button type="button" onclick="closeModal('membersModal')" class="bg-white py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    async function viewMembers(clubId, clubName) {
        const modal = document.getElementById('membersModal');
        const title = document.getElementById('membersModalTitle');
        const container = document.getElementById('membersListContainer');

        title.textContent = `Members of "${clubName}"`;
        container.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i><p class="mt-2">Loading...</p></div>';
        modal.classList.remove('hidden');

        try {
            // Fetch from the same file with a query parameter to trigger the API handler
            const response = await fetch(`teacher_clubs.php?action=get_members&club_id=${clubId}`);
            const members = await response.json();

            if (members.error) {
                throw new Error(members.error);
            }

            if (members.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center">This club has no members yet.</p>';
            } else {
                let tableHTML = '<table class="min-w-full divide-y divide-gray-200"><thead><tr><th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Name</th><th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Reg. No.</th><th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Class</th><th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Join Date</th></tr></thead><tbody>';
                members.forEach(member => {
                    tableHTML += `<tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium text-gray-800">${member.first_name} ${member.last_name}</td>
                        <td class="px-4 py-2 text-gray-600">${member.registration_number}</td>
                        <td class="px-4 py-2 text-gray-600">${member.class_name} - ${member.section_name}</td>
                        <td class="px-4 py-2 text-gray-600">${new Date(member.join_date).toLocaleDateString()}</td>
                    </tr>`;
                });
                tableHTML += '</tbody></table>';
                container.innerHTML = tableHTML;
            }
        } catch (error) {
            container.innerHTML = `<p class="text-red-500 text-center font-semibold">Failed to load members: ${error.message}</p>`;
        }
    }
</script>

</body>
</html>
<?php require_once './teacher_footer.php'; ?>