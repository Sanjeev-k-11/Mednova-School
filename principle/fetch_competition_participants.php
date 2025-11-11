<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION for AJAX ---
// This AJAX endpoint should be secured. Only authorized roles should access.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role"], ['Principle', 'Teacher', 'Admin'])) {
    http_response_code(403); // Forbidden
    echo json_encode(["error" => "Unauthorized access."]);
    exit;
}

header('Content-Type: application/json'); // Respond with JSON

$competition_id = isset($_GET['competition_id']) ? (int)$_GET['competition_id'] : 0;
$participants = [];

if ($competition_id > 0) {
    $sql_fetch_participants = "SELECT
                                cpp.id AS participant_id, cpp.registration_date,
                                s.id AS student_id, s.first_name, s.last_name, s.registration_number,
                                c.class_name, c.section_name
                            FROM competition_participants cpp
                            JOIN students s ON cpp.student_id = s.id
                            JOIN classes c ON s.class_id = c.id
                            WHERE cpp.competition_id = ?
                            ORDER BY s.first_name ASC";
    if ($stmt = mysqli_prepare($link, $sql_fetch_participants)) {
        mysqli_stmt_bind_param($stmt, "i", $competition_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $participants = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    } else {
        // Log the error for debugging
        error_log("Failed to prepare statement for fetch_competition_participants.php: " . mysqli_error($link));
        // Return an error to the client
        echo json_encode(["error" => "Database query failed."]);
        exit;
    }
} else {
    echo json_encode(["error" => "Invalid competition ID."]);
    exit;
}

mysqli_close($link);
echo json_encode($participants);
?>