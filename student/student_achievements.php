<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../database/config.php";
require_once "./student_header.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

$student_id = $_SESSION['id'] ?? null;
$student_name = "Student";
$student_achievements = [];
$error_message = '';

// --- CRITICAL CHECK: Validate student_id from session ---
if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    $error_message = "Your student ID is missing or invalid in the session. Please log in again.";
} else {
    try {
        // Create a new PDO instance
        $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // --- Fetch Student's Name for Personalized Greeting ---
        $sql_student_name = "SELECT first_name, last_name FROM students WHERE id = :id LIMIT 1";
        $stmt_name = $pdo->prepare($sql_student_name);
        $stmt_name->bindParam(':id', $student_id, PDO::PARAM_INT);
        $stmt_name->execute();
        $row_name = $stmt_name->fetch(PDO::FETCH_ASSOC);
        if ($row_name) {
            $student_name = htmlspecialchars($row_name['first_name'] . ' ' . $row_name['last_name']);
        }

        // --- Fetch achievements for the logged-in student ---
        $sql_achievements = "
            SELECT
                id,
                title,
                description,
                achievement_date,
                certificate_url,
                uploaded_by_role,
                created_at
            FROM
                student_achievements
            WHERE
                student_id = :student_id
            ORDER BY
                achievement_date DESC, created_at DESC;
        ";
        $stmt_achievements = $pdo->prepare($sql_achievements);
        $stmt_achievements->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt_achievements->execute();
        $student_achievements = $stmt_achievements->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $error_message = "A database error occurred. Please try again later.";
    }
}
?>

<div class="bg-gray-50 mt-28 min-h-screen p-4 sm:p-6 font-sans">
    <?php if ($error_message): ?>
        <div class="max-w-4xl mx-auto mb-4 p-4 rounded-xl text-red-800 bg-red-100 border border-red-200 shadow-md">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                <p class="font-bold"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        </div>
    <?php else: ?>
        <!-- Main Header Card -->
        <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8 mb-8 text-center border-t-4 border-purple-500 transform transition-all duration-300 hover:scale-[1.01]">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-2 tracking-tight">My Achievements & Certificates</h1>
            <p class="text-gray-600 text-lg">Congratulations, <span class="font-bold text-purple-600"><?php echo $student_name; ?></span>! Celebrate your accomplishments.</p>
        </div>

        <!-- Achievements List -->
        <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Your Awards & Recognitions</h2>
            <?php if (empty($student_achievements)): ?>
                <div class="text-center p-12 bg-purple-50 border border-purple-200 rounded-2xl text-purple-800 shadow-lg transform transition-transform duration-300 hover:scale-[1.02]">
                    <i class="fas fa-trophy fa-4x text-purple-400 mb-6 animate-pulse"></i>
                    <h3 class="text-2xl font-bold mb-2">No achievements recorded yet!</h3>
                    <p class="text-lg text-purple-700">Keep up the great work. Your dedication will surely lead to success.</p>
                    <p class="text-sm text-purple-600 mt-4 opacity-75">Check back later for updates from your school administration.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($student_achievements as $achievement): ?>
                        <?php $is_new = (strtotime($achievement['created_at']) > strtotime('-7 days')); ?>
                        <div class="relative bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                            <?php if ($is_new): ?>
                                <span class="absolute top-3 right-3 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md z-10 animate-pulse-slow">NEW!</span>
                            <?php endif; ?>
                            <div class="p-6 flex flex-col h-full">
                                <div class="flex items-center mb-4">
                                    <i class="fas fa-award fa-2x text-purple-500 mr-4"></i>
                                    <h3 class="text-xl font-bold text-purple-800 flex-grow"><?php echo htmlspecialchars($achievement['title']); ?></h3>
                                </div>
                                <p class="text-gray-700 text-sm mb-4 line-clamp-3 leading-relaxed"><?php echo htmlspecialchars($achievement['description'] ?: 'No description provided.'); ?></p>
                                <div class="mt-auto pt-4 border-t border-purple-200">
                                    <div class="flex justify-between items-center text-xs text-gray-600 mb-3">
                                        <span>Achieved on: <span class="font-semibold text-purple-700"><?php echo date('M d, Y', strtotime($achievement['achievement_date'])); ?></span></span>
                                        <?php if ($achievement['uploaded_by_role']): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-200 text-purple-800 shadow-sm">
                                                <i class="fas fa-user-tag mr-1.5"></i> <?php echo htmlspecialchars($achievement['uploaded_by_role']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex justify-end mt-2">
                                        <?php if ($achievement['certificate_url']): ?>
                                            <a href="<?php echo htmlspecialchars($achievement['certificate_url']); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium text-sm rounded-full shadow-lg transition-colors duration-200">
                                                <i class="fas fa-file-download mr-2"></i> View Certificate
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500 text-sm italic flex items-center"><i class="fas fa-info-circle mr-1"></i> No Certificate Available</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once "./student_footer.php";
?>