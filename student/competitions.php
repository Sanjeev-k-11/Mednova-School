<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}
$student_id = $_SESSION["id"];

// --- HANDLE REGISTER/UNREGISTER ACTIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $competition_id = $_POST['competition_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($competition_id && $action) {
        if ($action === 'register') {
            // --- Security Checks Before Registration ---
            $sql_check = "SELECT registration_deadline, participant_limit, 
                          (SELECT COUNT(*) FROM competition_participants WHERE competition_id = c.id) AS participant_count
                          FROM competitions c WHERE id = ?";
            $stmt_check = mysqli_prepare($link, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "i", $competition_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            $comp_status = mysqli_fetch_assoc($result_check);
            $can_register = true;

            // 1. Check if registration deadline has passed
            if ($comp_status['registration_deadline'] && strtotime($comp_status['registration_deadline']) < time()) {
                $_SESSION['error_message'] = "Sorry, the registration deadline has passed.";
                $can_register = false;
            }
            // 2. Check if participant limit is reached
            elseif ($comp_status['participant_limit'] !== null && $comp_status['participant_count'] >= $comp_status['participant_limit']) {
                $_SESSION['error_message'] = "Sorry, this competition is already full.";
                $can_register = false;
            }

            if ($can_register) {
                // 3. Prevent duplicate registration
                $sql_insert = "INSERT IGNORE INTO competition_participants (competition_id, student_id) VALUES (?, ?)";
                if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                    mysqli_stmt_bind_param($stmt_insert, "ii", $competition_id, $student_id);
                    mysqli_stmt_execute($stmt_insert);
                    if (mysqli_stmt_affected_rows($stmt_insert) > 0) {
                        $_SESSION['success_message'] = "Successfully registered for the competition!";
                    } else {
                        $_SESSION['error_message'] = "You are already registered for this competition.";
                    }
                }
            }
        } elseif ($action === 'unregister') {
            $sql_leave = "DELETE FROM competition_participants WHERE competition_id = ? AND student_id = ?";
            if ($stmt_leave = mysqli_prepare($link, $sql_leave)) {
                mysqli_stmt_bind_param($stmt_leave, "ii", $competition_id, $student_id);
                mysqli_stmt_execute($stmt_leave);
                $_SESSION['success_message'] = "You have unregistered from the competition.";
            }
        }
    }
    header("location: competitions.php");
    exit;
}

// --- Fetch all programs the current student is registered for ---
$my_comp_ids = [];
$sql_my_comps = "SELECT competition_id FROM competition_participants WHERE student_id = ?";
if ($stmt_my = mysqli_prepare($link, $sql_my_comps)) {
    mysqli_stmt_bind_param($stmt_my, "i", $student_id);
    mysqli_stmt_execute($stmt_my);
    $result_my = mysqli_stmt_get_result($stmt_my);
    while ($row = mysqli_fetch_assoc($result_my)) {
        $my_comp_ids[] = $row['competition_id'];
    }
}

// --- Fetch all active & upcoming competitions with their details ---
$competitions = [];
$sql_competitions = "SELECT
                  c.*,
                  t.full_name AS teacher_name,
                  (SELECT COUNT(*) FROM competition_participants WHERE competition_id = c.id) AS participant_count
              FROM competitions c
              LEFT JOIN teachers t ON c.teacher_in_charge_id = t.id
              WHERE c.status IN ('Active', 'Upcoming')
              ORDER BY c.competition_date ASC";

if ($result_competitions = mysqli_query($link, $sql_competitions)) {
    $competitions = mysqli_fetch_all($result_competitions, MYSQLI_ASSOC);
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
    <title>School Competitions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-bold text-gray-800 tracking-tight">School Competitions</h1>
        <p class="text-gray-600 mt-2 text-lg">Showcase your skills and participate in exciting events.</p>
    </div>

    <!-- Success/Error Message Display -->
    <?php if ($success_message || $error_message): ?>
        <div class="max-w-3xl mx-auto mb-6 rounded-lg p-4 font-bold text-center <?php echo $success_message ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php if ($success_message): echo '<i class="fas fa-check-circle mr-2"></i>' . htmlspecialchars($success_message); endif; ?>
            <?php if ($error_message): echo '<i class="fas fa-exclamation-triangle mr-2"></i>' . htmlspecialchars($error_message); endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($competitions)): ?>
        <div class="text-center py-16 px-6 bg-white rounded-2xl shadow-md">
            <i class="fas fa-trophy text-5xl text-gray-400"></i>
            <h3 class="text-2xl font-bold text-gray-700 mt-4">No Competitions Available</h3>
            <p class="mt-2 text-gray-500">There are no competitions open for registration at this time.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($competitions as $comp): 
                $is_registered = in_array($comp['id'], $my_comp_ids);
                $is_full = ($comp['participant_limit'] !== null && $comp['participant_count'] >= $comp['participant_limit']);
                $deadline_passed = ($comp['registration_deadline'] && strtotime($comp['registration_deadline']) < time());
            ?>
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col">
                    <img class="h-48 w-full object-cover rounded-t-xl" src="<?php echo htmlspecialchars($comp['image_url'] ?? 'https://via.placeholder.com/400x200.png/d1d5db/ffffff?text=Competition'); ?>" alt="<?php echo htmlspecialchars($comp['name']); ?>">
                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="font-bold text-xl text-gray-900"><?php echo htmlspecialchars($comp['name']); ?></h3>
                        <p class="text-gray-600 mt-2 text-sm flex-grow"><?php echo htmlspecialchars($comp['description']); ?></p>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-gray-500 space-y-2">
                            <p><i class="fas fa-calendar-day w-5 mr-1 text-gray-400"></i> <strong>Event Date:</strong> <?php echo date("F j, Y", strtotime($comp['competition_date'])); ?></p>
                            <p><i class="fas fa-map-marker-alt w-5 mr-1 text-gray-400"></i> <strong>Location:</strong> <?php echo htmlspecialchars($comp['location'] ?? 'TBD'); ?></p>
                            <p><i class="fas fa-user-tie w-5 mr-1 text-gray-400"></i> <strong>Advisor:</strong> <?php echo htmlspecialchars($comp['teacher_name'] ?? 'N/A'); ?></p>
                            <p><i class="fas fa-users w-5 mr-1 text-gray-400"></i> <strong>Participants:</strong> <?php echo $comp['participant_count']; ?><?php if ($comp['participant_limit']): ?> / <?php echo $comp['participant_limit']; ?><?php endif; ?></p>
                            <p class="font-semibold <?php echo $deadline_passed ? 'text-red-600' : 'text-green-600'; ?>"><i class="fas fa-user-plus w-5 mr-1 text-gray-400"></i> <strong>Register By:</strong> <?php echo date("F j, Y", strtotime($comp['registration_deadline'])); ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 rounded-b-xl">
                        <form action="competitions.php" method="POST">
                            <input type="hidden" name="competition_id" value="<?php echo $comp['id']; ?>">
                            <?php if ($is_registered): ?>
                                <input type="hidden" name="action" value="unregister">
                                <button type="submit" class="w-full bg-red-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-700 transition-colors"><i class="fas fa-times mr-2"></i>Unregister</button>
                            <?php elseif ($deadline_passed): ?>
                                <button type="button" class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg cursor-not-allowed" disabled><i class="fas fa-lock mr-2"></i>Registration Closed</button>
                            <?php elseif ($is_full): ?>
                                <button type="button" class="w-full bg-yellow-500 text-white font-semibold py-2 px-4 rounded-lg cursor-not-allowed" disabled><i class="fas fa-users-slash mr-2"></i>Competition is Full</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="register">
                                <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors"><i class="fas fa-check mr-2"></i>Register Now</button>
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