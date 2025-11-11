<?php
// ajax_get_subjects.php
session_start();
require_once "../database/config.php";

// Basic authentication check (optional but recommended for AJAX endpoints)
// You might want to allow both 'Teacher' and 'Admin' roles
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

$class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

$subjects = [];

if ($class_id) {
    // This query fetches subjects assigned to a specific class
    $sql = "SELECT s.id, s.subject_name
            FROM class_subjects cs
            JOIN subjects s ON cs.subject_id = s.id
            WHERE cs.class_id = ?
            ORDER BY s.subject_name";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $class_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $subjects[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}
mysqli_close($link);
echo json_encode($subjects);
?>