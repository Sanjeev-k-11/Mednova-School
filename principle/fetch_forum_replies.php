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

$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$replies = [];

if ($post_id > 0) {
    $sql_fetch_replies = "SELECT
                                fr.id AS reply_id, fr.content, fr.created_at, fr.replier_role,
                                COALESCE(s.first_name, t.full_name) AS replier_name
                            FROM forum_replies fr
                            LEFT JOIN students s ON fr.replier_id = s.id AND fr.replier_role = 'Student'
                            LEFT JOIN teachers t ON fr.replier_id = t.id AND fr.replier_role = 'Teacher'
                            WHERE fr.post_id = ?
                            ORDER BY fr.created_at ASC";
    if ($stmt = mysqli_prepare($link, $sql_fetch_replies)) {
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $replies = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($link);
echo json_encode($replies);
?>