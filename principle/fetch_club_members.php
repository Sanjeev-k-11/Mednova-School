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

$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
$members = [];

if ($club_id > 0) {
    $sql_fetch_members = "SELECT
                            cm.id AS member_id, cm.join_date, cm.role,
                            s.id AS student_id, s.first_name, s.last_name, s.registration_number,
                            c.class_name, c.section_name
                        FROM club_members cm
                        JOIN students s ON cm.student_id = s.id
                        JOIN classes c ON s.class_id = c.id
                        WHERE cm.club_id = ?
                        ORDER BY s.first_name ASC";
    if ($stmt = mysqli_prepare($link, $sql_fetch_members)) {
        mysqli_stmt_bind_param($stmt, "i", $club_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $members = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($link);
echo json_encode($members);
?>