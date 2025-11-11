<?php
session_start();
require_once "./config.php"; // Adjust path as needed

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$publicKey = $data['publicKey'] ?? null;
$userId = $_SESSION['id'];
$userRole = $_SESSION['role']; // 'Student' or 'Teacher'

if ($publicKey) {
    // Use INSERT ... ON DUPLICATE KEY UPDATE to add/update the key
    $sql = "INSERT INTO user_public_keys (user_id, user_role, public_key) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE public_key = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "isss", $userId, $userRole, $publicKey, $publicKey);
    mysqli_stmt_execute($stmt);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No public key provided']);
}