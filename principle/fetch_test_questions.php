<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION for AJAX ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role"], ['Principle', 'Teacher', 'Admin'])) {
    http_response_code(403); // Forbidden
    echo json_encode(["error" => "Unauthorized access."]);
    exit;
}

header('Content-Type: application/json'); // Respond with JSON

$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$questions = [];

if ($test_id > 0) {
    $sql_fetch_questions = "SELECT
                                otq.id, otq.question_text, otq.option_a, otq.option_b, otq.option_c, otq.option_d,
                                otq.correct_option, otq.marks
                            FROM online_test_questions otq
                            WHERE otq.test_id = ?
                            ORDER BY otq.id ASC"; // Order by question ID
    if ($stmt = mysqli_prepare($link, $sql_fetch_questions)) {
        mysqli_stmt_bind_param($stmt, "i", $test_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $questions = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    } else {
        error_log("Failed to prepare statement for fetch_test_questions.php: " . mysqli_error($link));
        echo json_encode(["error" => "Database query failed."]);
        exit;
    }
} else {
    echo json_encode(["error" => "Invalid test ID."]);
    exit;
}

mysqli_close($link);
echo json_encode($questions);
?>