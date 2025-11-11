<?php
// ajax_get_subjects.php
session_start();
require_once "../database/config.php";

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"], $_SESSION["id"], $_GET['class_id']) || $_SESSION["role"] !== 'Teacher') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$teacher_id = $_SESSION["id"];
$class_id = (int)$_GET['class_id'];
$subjects = [];

$sql = "SELECT DISTINCT s.id, s.subject_name 
        FROM class_subject_teacher cst 
        JOIN subjects s ON cst.subject_id = s.id 
        WHERE cst.teacher_id = ? AND cst.class_id = ? 
        ORDER BY s.subject_name";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $class_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $subjects = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

echo json_encode($subjects);
?>