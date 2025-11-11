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
    $program_id = $_POST['program_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($program_id && $action) {
        if ($action === 'register') {
            // --- Security Checks Before Registration ---
            $sql_check = "SELECT registration_deadline, participant_limit, 
                          (SELECT COUNT(*) FROM program_participants WHERE program_id = cp.id) AS participant_count
                          FROM cultural_programs cp WHERE id = ?";
            $stmt_check = mysqli_prepare($link, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "i", $program_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            $program_status = mysqli_fetch_assoc($result_check);
            $can_register = true;

            // 1. Check if registration deadline has passed
            if ($program_status['registration_deadline'] && strtotime($program_status['registration_deadline']) < time()) {
                $_SESSION['error_message'] = "Sorry, the registration deadline has passed.";
                $can_register = false;
            }
            // 2. Check if participant limit is reached
            elseif ($program_status['participant_limit'] !== null && $program_status['participant_count'] >= $program_status['participant_limit']) {
                $_SESSION['error_message'] = "Sorry, this program is already full.";
                $can_register = false;
            }

            if ($can_register) {
                // 3. Prevent duplicate registration
                $sql_insert = "INSERT IGNORE INTO program_participants (program_id, student_id) VALUES (?, ?)";
                if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                    mysqli_stmt_bind_param($stmt_insert, "ii", $program_id, $student_id);
                    mysqli_stmt_execute($stmt_insert);
                    if (mysqli_stmt_affected_rows($stmt_insert) > 0) {
                        $_SESSION['success_message'] = "Successfully registered for the program!";
                    } else {
                        $_SESSION['error_message'] = "You are already registered for this program.";
                    }
                }
            }
        } elseif ($action === 'unregister') {
            $sql_leave = "DELETE FROM program_participants WHERE program_id = ? AND student_id = ?";
            if ($stmt_leave = mysqli_prepare($link, $sql_leave)) {
                mysqli_stmt_bind_param($stmt_leave, "ii", $program_id, $student_id);
                mysqli_stmt_execute($stmt_leave);
                $_SESSION['success_message'] = "You have unregistered from the program.";
            }
        }
    }
    header("location: cultural_programs.php");
    exit;
}

// --- Fetch all programs the current student is registered for ---
$my_program_ids = [];
$sql_my_programs = "SELECT program_id FROM program_participants WHERE student_id = ?";
if ($stmt_my = mysqli_prepare($link, $sql_my_programs)) {
    mysqli_stmt_bind_param($stmt_my, "i", $student_id);
    mysqli_stmt_execute($stmt_my);
    $result_my = mysqli_stmt_get_result($stmt_my);
    while ($row = mysqli_fetch_assoc($result_my)) {
        $my_program_ids[] = $row['program_id'];
    }
}

// --- Fetch all active programs with their details ---
$programs = [];
$sql_programs = "SELECT
                  cp.id, cp.name, cp.description, cp.image_url, cp.program_date, cp.registration_deadline, cp.participant_limit, cp.location,
                  t.full_name AS teacher_name,
                  (SELECT COUNT(*) FROM program_participants WHERE program_id = cp.id) AS participant_count
              FROM cultural_programs cp
              LEFT JOIN teachers t ON cp.teacher_in_charge_id = t.id
              WHERE cp.status = 'Active'
              ORDER BY cp.program_date ASC";

if ($result_programs = mysqli_query($link, $sql_programs)) {
    $programs = mysqli_fetch_all($result_programs, MYSQLI_ASSOC);
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
    <title>Cultural Programs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-bold text-gray-800 tracking-tight">Cultural Programs</h1>
        <p class="text-gray-600 mt-2 text-lg">Discover and participate in exciting school events.</p>
    </div>

    <!-- Success/Error Message Display -->
    <?php if ($success_message || $error_message): ?>
        <div class="max-w-3xl mx-auto mb-6 rounded-lg p-4 font-bold text-center <?php echo $success_message ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php if ($success_message): echo '<i class="fas fa-check-circle mr-2"></i>' . htmlspecialchars($success_message); endif; ?>
            <?php if ($error_message): echo '<i class="fas fa-exclamation-triangle mr-2"></i>' . htmlspecialchars($error_message); endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($programs)): ?>
        <div class="text-center py-16 px-6 bg-white rounded-2xl shadow-md">
            <i class="fas fa-palette text-5xl text-gray-400"></i>
            <h3 class="text-2xl font-bold text-gray-700 mt-4">No Programs Available</h3>
            <p class="mt-2 text-gray-500">There are no cultural programs open for registration at this time.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($programs as $program): 
                $is_registered = in_array($program['id'], $my_program_ids);
                $is_full = ($program['participant_limit'] !== null && $program['participant_count'] >= $program['participant_limit']);
                $deadline_passed = ($program['registration_deadline'] && strtotime($program['registration_deadline']) < time());
            ?>
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col">
                    <img class="h-48 w-full object-cover rounded-t-xl" src="<?php echo htmlspecialchars($program['image_url'] ?? 'https://via.placeholder.com/400x200.png/d1d5db/ffffff?text=Event'); ?>" alt="<?php echo htmlspecialchars($program['name']); ?>">
                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="font-bold text-xl text-gray-900"><?php echo htmlspecialchars($program['name']); ?></h3>
                        <p class="text-gray-600 mt-2 text-sm flex-grow"><?php echo htmlspecialchars($program['description']); ?></p>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-gray-500 space-y-2">
                            <p><i class="fas fa-calendar-day w-5 mr-1 text-gray-400"></i> <strong>Event Date:</strong> <?php echo date("F j, Y", strtotime($program['program_date'])); ?></p>
                            <p><i class="fas fa-map-marker-alt w-5 mr-1 text-gray-400"></i> <strong>Location:</strong> <?php echo htmlspecialchars($program['location'] ?? 'TBD'); ?></p>
                            <p><i class="fas fa-user-tie w-5 mr-1 text-gray-400"></i> <strong>Advisor:</strong> <?php echo htmlspecialchars($program['teacher_name'] ?? 'N/A'); ?></p>
                            <p><i class="fas fa-users w-5 mr-1 text-gray-400"></i> <strong>Participants:</strong> <?php echo $program['participant_count']; ?><?php if ($program['participant_limit']): ?> / <?php echo $program['participant_limit']; ?><?php endif; ?></p>
                            <p class="font-semibold <?php echo $deadline_passed ? 'text-red-600' : 'text-green-600'; ?>"><i class="fas fa-user-plus w-5 mr-1 text-gray-400"></i> <strong>Register By:</strong> <?php echo date("F j, Y", strtotime($program['registration_deadline'])); ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 rounded-b-xl">
                        <form action="cultural_programs.php" method="POST">
                            <input type="hidden" name="program_id" value="<?php echo $program['id']; ?>">
                            <?php if ($is_registered): ?>
                                <input type="hidden" name="action" value="unregister">
                                <button type="submit" class="w-full bg-red-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-700 transition-colors"><i class="fas fa-times mr-2"></i>Unregister</button>
                            <?php elseif ($deadline_passed): ?>
                                <button type="button" class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg cursor-not-allowed" disabled><i class="fas fa-lock mr-2"></i>Registration Closed</button>
                            <?php elseif ($is_full): ?>
                                <button type="button" class="w-full bg-yellow-500 text-white font-semibold py-2 px-4 rounded-lg cursor-not-allowed" disabled><i class="fas fa-users-slash mr-2"></i>Program is Full</button>
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