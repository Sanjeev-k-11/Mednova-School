<?php
// ajax_get_students_by_class.php
session_start();
require_once "../database/config.php";

// Basic authentication check (optional but recommended for AJAX endpoints)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

$class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

$students = [];

if ($class_id) {
    $sql = "SELECT id, first_name, last_name, roll_number 
            FROM students 
            WHERE class_id = ? 
            ORDER BY roll_number, first_name";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $class_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}
mysqli_close($link);
echo json_encode($students);
?>