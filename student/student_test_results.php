<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./student_header.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

$attempt_id = $_GET['id'] ?? null;
$student_id = $_SESSION['id'] ?? null;

$attempt_details = null;
if (is_numeric($attempt_id) && $attempt_id > 0 && is_numeric($student_id)) {
    $sql = "SELECT * FROM student_test_attempts WHERE id = ? AND student_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $attempt_id, $student_id);
        mysqli_stmt_execute($stmt);
        $attempt_details = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
}
mysqli_close($link);
?>
<div class="bg-gray-100 min-h-screen p-4 sm:p-6">
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Test Results</h1>
        <?php if ($attempt_details): ?>
            <p class="text-gray-600">Test ID: <?php echo htmlspecialchars($attempt_details['test_id']); ?></p>
            <p class="text-gray-600">Your Score: <span class="font-bold text-indigo-600"><?php echo htmlspecialchars($attempt_details['score'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($attempt_details['total_marks'] ?? 'N/A'); ?></span></p>
            <p class="text-gray-600">Status: <?php echo htmlspecialchars($attempt_details['status']); ?></p>
            <p class="text-gray-600">Completed at: <?php echo htmlspecialchars($attempt_details['end_time'] ?? 'N/A'); ?></p>
            <p class="text-gray-600 mt-4">More detailed results would appear here, showing correct/incorrect answers.</p>
        <?php else: ?>
            <p class="text-red-600">Could not find test attempt results.</p>
        <?php endif; ?>
    </div>
</div>
<?php require_once "./student_footer.php"; ?>