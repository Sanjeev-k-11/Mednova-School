<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
// This file can be accessed by Principal, Teacher, Admin
// So it should check for any of these roles if necessary for security.
// For now, assume if you're in the 'principal' folder and accessing this, you're authorized.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role"], ['Principle', 'Teacher', 'Admin'])) {
    http_response_code(403); // Forbidden
    echo json_encode(["error" => "Unauthorized access."]);
    exit;
}

header('Content-Type: application/json'); // Respond with JSON

$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$messages = [];

if ($ticket_id > 0) {
    $sql_fetch_messages = "SELECT
                                stm.message, stm.created_at, stm.user_role,
                                COALESCE(s.first_name, t.full_name, p.full_name) AS sender_name -- Get sender name from student, teacher, or principal
                            FROM support_ticket_messages stm
                            LEFT JOIN students s ON stm.user_id = s.id AND stm.user_role = 'Student'
                            LEFT JOIN teachers t ON stm.user_id = t.id AND stm.user_role = 'Teacher'
                            LEFT JOIN principles p ON stm.user_id = p.id AND stm.user_role = 'Principle' -- Added join for Principal
                            WHERE stm.ticket_id = ?
                            ORDER BY stm.created_at ASC";
    
    if ($stmt = mysqli_prepare($link, $sql_fetch_messages)) {
        mysqli_stmt_bind_param($stmt, "i", $ticket_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($link);
echo json_encode($messages);
?>