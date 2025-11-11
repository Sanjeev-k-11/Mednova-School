<?php
session_start();
require_once "../database/config.php";

header('Content-Type: application/json');

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$club_id = $_GET['club_id'] ?? 0;

if (!$club_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Club ID is required']);
    exit;
}

$members = [];
$sql = "SELECT 
            s.first_name, s.last_name, s.registration_number,
            c.class_name, c.section_name,
            cm.join_date, cm.role
        FROM club_members cm
        JOIN students s ON cm.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE cm.club_id = ?
        ORDER BY s.first_name, s.last_name";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $club_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $members = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

echo json_encode($members);
?>