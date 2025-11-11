<?php
session_start();
require_once "../database/config.php";

header('Content-Type: application/json');

// --- AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$teacher_id = $_SESSION["id"];
$response = ['new_messages' => 0];

// 1. Get the teacher's last check timestamp from the database
$last_check_time = null;
$sql_get_time = "SELECT last_message_check FROM teachers WHERE id = ?";
if ($stmt_get = mysqli_prepare($link, $sql_get_time)) {
    mysqli_stmt_bind_param($stmt_get, "i", $teacher_id);
    mysqli_stmt_execute($stmt_get);
    mysqli_stmt_bind_result($stmt_get, $last_check_time);
    mysqli_stmt_fetch($stmt_get);
    mysqli_stmt_close($stmt_get);
}

// 2. Count new messages received since the last check
// We only count messages sent by students in conversations the teacher is part of.
$sql_count = "SELECT COUNT(m.id) 
              FROM st_messages m
              JOIN st_conversations c ON m.conversation_id = c.id
              WHERE c.teacher_id = ? 
              AND m.sender_role = 'Student'";

$params = [$teacher_id];
$types = "i";

if ($last_check_time) {
    // If we have a last check time, only count messages newer than that
    $sql_count .= " AND m.created_at > ?";
    $params[] = $last_check_time;
    $types .= "s";
}

if ($stmt_count = mysqli_prepare($link, $sql_count)) {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    mysqli_stmt_execute($stmt_count);
    mysqli_stmt_bind_result($stmt_count, $new_message_count);
    mysqli_stmt_fetch($stmt_count);
    $response['new_messages'] = $new_message_count;
    mysqli_stmt_close($stmt_count);
}

// 3. Update the teacher's last_message_check timestamp to NOW()
// This "resets" the counter for the next check.
$sql_update_time = "UPDATE teachers SET last_message_check = NOW() WHERE id = ?";
if ($stmt_update = mysqli_prepare($link, $sql_update_time)) {
    mysqli_stmt_bind_param($stmt_update, "i", $teacher_id);
    mysqli_stmt_execute($stmt_update);
    mysqli_stmt_close($stmt_update);
}


echo json_encode($response);
?>