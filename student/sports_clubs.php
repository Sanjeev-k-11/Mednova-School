<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}
$student_id = $_SESSION["id"];

// --- HANDLE JOIN/LEAVE ACTIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $club_id = $_POST['club_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($club_id && $action) {
        if ($action === 'join') {
            // Check member limit before allowing to join
            $sql_limit = "SELECT member_limit, (SELECT COUNT(*) FROM club_members WHERE club_id = sc.id) AS member_count FROM sports_clubs sc WHERE id = ?";
            $stmt_limit = mysqli_prepare($link, $sql_limit);
            mysqli_stmt_bind_param($stmt_limit, "i", $club_id);
            mysqli_stmt_execute($stmt_limit);
            $result_limit = mysqli_stmt_get_result($stmt_limit);
            $club_status = mysqli_fetch_assoc($result_limit);
            
            if ($club_status && $club_status['member_limit'] !== null && $club_status['member_count'] >= $club_status['member_limit']) {
                $_SESSION['error_message'] = "Cannot join, the club is full.";
            } else {
                // Prevent joining the same club twice
                $sql_check = "SELECT id FROM club_members WHERE club_id = ? AND student_id = ?";
                $stmt_check = mysqli_prepare($link, $sql_check);
                mysqli_stmt_bind_param($stmt_check, "ii", $club_id, $student_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) == 0) {
                    $sql_join = "INSERT INTO club_members (club_id, student_id) VALUES (?, ?)";
                    $stmt_join = mysqli_prepare($link, $sql_join);
                    mysqli_stmt_bind_param($stmt_join, "ii", $club_id, $student_id);
                    mysqli_stmt_execute($stmt_join);
                    $_SESSION['success_message'] = "Successfully joined the club!";
                }
            }
        } elseif ($action === 'leave') {
            $sql_leave = "DELETE FROM club_members WHERE club_id = ? AND student_id = ?";
            $stmt_leave = mysqli_prepare($link, $sql_leave);
            mysqli_stmt_bind_param($stmt_leave, "ii", $club_id, $student_id);
            mysqli_stmt_execute($stmt_leave);
            $_SESSION['success_message'] = "You have left the club.";
        }
    }
    header("location: sports_clubs.php");
    exit;
}

// --- Fetch all clubs the current student is a member of ---
$my_club_ids = [];
$sql_my_clubs = "SELECT club_id FROM club_members WHERE student_id = ?";
if ($stmt_my = mysqli_prepare($link, $sql_my_clubs)) {
    mysqli_stmt_bind_param($stmt_my, "i", $student_id);
    mysqli_stmt_execute($stmt_my);
    $result_my = mysqli_stmt_get_result($stmt_my);
    while ($row = mysqli_fetch_assoc($result_my)) {
        $my_club_ids[] = $row['club_id'];
    }
}

// --- Determine view from URL for tab navigation ---
$view = $_GET['view'] ?? 'all';

// --- Build SQL query based on the selected view ---
$sql_clubs = "SELECT
                  sc.id, sc.name, sc.department_id, sc.description, sc.image_url, sc.meeting_schedule, sc.member_limit,
                  t.full_name AS teacher_name,
                  d.department_name,
                  (SELECT COUNT(*) FROM club_members WHERE club_id = sc.id) AS member_count
              FROM sports_clubs sc
              LEFT JOIN teachers t ON sc.teacher_in_charge_id = t.id
              LEFT JOIN departments d ON sc.department_id = d.id
              WHERE sc.status = 'Active'";

if ($view === 'my_clubs' && !empty($my_club_ids)) {
    // If viewing "My Clubs", filter by the IDs of clubs the student has joined
    $placeholders = implode(',', array_fill(0, count($my_club_ids), '?'));
    $sql_clubs .= " AND sc.id IN ($placeholders)";
}
$sql_clubs .= " ORDER BY sc.name ASC";

$clubs = [];
if ($stmt_clubs = mysqli_prepare($link, $sql_clubs)) {
    if ($view === 'my_clubs' && !empty($my_club_ids)) {
        // Bind the student's club IDs to the query
        $types = str_repeat('i', count($my_club_ids));
        mysqli_stmt_bind_param($stmt_clubs, $types, ...$my_club_ids);
    }
    mysqli_stmt_execute($stmt_clubs);
    $result_clubs = mysqli_stmt_get_result($stmt_clubs);
    $clubs = mysqli_fetch_all($result_clubs, MYSQLI_ASSOC);
}


$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sports & Clubs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-bold text-gray-800 tracking-tight">Sports & Activities</h1>
        <p class="text-gray-600 mt-2 text-lg">Explore and join clubs to get involved!</p>
    </div>

    <!-- Tab Navigation -->
    <div class="mb-8 flex justify-center p-1.5 bg-gray-200 rounded-xl shadow-inner max-w-md mx-auto">
        <a href="?view=all" class="flex-1 text-center py-2 px-4 rounded-lg font-semibold transition-all duration-300 <?php echo ($view === 'all') ? 'bg-blue-600 text-white shadow' : 'text-gray-600 hover:bg-white'; ?>">
            <i class="fas fa-list-ul mr-2"></i>All Clubs
        </a>
        <a href="?view=my_clubs" class="flex-1 text-center py-2 px-4 rounded-lg font-semibold transition-all duration-300 <?php echo ($view === 'my_clubs') ? 'bg-blue-600 text-white shadow' : 'text-gray-600 hover:bg-white'; ?>">
            <i class="fas fa-user-friends mr-2"></i>My Clubs
        </a>
    </div>

    <!-- Success/Error Message Display -->
    <?php if ($success_message || $error_message): ?>
        <div class="max-w-3xl mx-auto mb-6 rounded-lg p-4 font-bold text-center <?php echo $success_message ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php if ($success_message): echo '<i class="fas fa-check-circle mr-2"></i>' . htmlspecialchars($success_message); endif; ?>
            <?php if ($error_message): echo '<i class="fas fa-exclamation-triangle mr-2"></i>' . htmlspecialchars($error_message); endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($clubs)): ?>
        <div class="text-center py-16 px-6 bg-white rounded-2xl shadow-md">
            <i class="fas fa-search-minus text-5xl text-gray-400"></i>
            <h3 class="text-2xl font-bold text-gray-700 mt-4"><?php echo ($view === 'my_clubs') ? 'You Have Not Joined Any Clubs' : 'No Clubs Found'; ?></h3>
            <p class="mt-2 text-gray-500"><?php echo ($view === 'my_clubs') ? 'Click on "All Clubs" to explore and join.' : 'There are no active clubs available at this time.'; ?></p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($clubs as $club): 
                $is_member = in_array($club['id'], $my_club_ids);
                $is_full = ($club['member_limit'] !== null && $club['member_count'] >= $club['member_limit']);
            ?>
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col">
                    <img class="h-48 w-full object-cover rounded-t-xl" src="<?php echo htmlspecialchars($club['image_url'] ?? 'https://via.placeholder.com/400x200.png/d1d5db/ffffff?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($club['name']); ?>">
                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="font-bold text-xl text-gray-900"><?php echo htmlspecialchars($club['name']); ?></h3>
                        <p class="text-gray-600 mt-2 text-sm flex-grow"><?php echo htmlspecialchars($club['description']); ?></p>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-gray-500 space-y-2">
                            <p><i class="fas fa-building w-5 mr-1 text-gray-400"></i> <strong>Department:</strong> <?php echo htmlspecialchars($club['department_name'] ?? 'General'); ?></p>
                            <p><i class="fas fa-user-tie w-5 mr-1 text-gray-400"></i> <strong>Advisor:</strong> <?php echo htmlspecialchars($club['teacher_name'] ?? 'Not Assigned'); ?></p>
                            <p><i class="fas fa-users w-5 mr-1 text-gray-400"></i> <strong>Members:</strong> <?php echo $club['member_count']; ?><?php if ($club['member_limit']): ?> / <?php echo $club['member_limit']; ?><?php endif; ?></p>
                            <p><i class="fas fa-calendar-alt w-5 mr-1 text-gray-400"></i> <strong>Meets:</strong> <?php echo htmlspecialchars($club['meeting_schedule'] ?? 'TBD'); ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 rounded-b-xl">
                        <form action="sports_clubs.php" method="POST">
                            <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                            <?php if ($is_member): ?>
                                <input type="hidden" name="action" value="leave">
                                <button type="submit" class="w-full bg-red-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-700 transition-colors"><i class="fas fa-sign-out-alt mr-2"></i>Leave Club</button>
                            <?php elseif ($is_full): ?>
                                <button type="button" class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg cursor-not-allowed" disabled><i class="fas fa-lock mr-2"></i>Club is Full</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="join">
                                <button type="submit" class="w-full bg-green-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-green-700 transition-colors"><i class="fas fa-plus mr-2"></i>Join Club</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
<?php require_once './student_footer.php'; ?>