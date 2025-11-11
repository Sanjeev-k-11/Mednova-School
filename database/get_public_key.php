<?php
session_start();
require_once "./config.php"; // Adjust path as needed

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_GET['role'] ?? '';
$id = $_GET['id'] ?? 0;
$publicKey = null;

if ($role && $id) {
    $sql = "SELECT public_key FROM user_public_keys WHERE user_id = ? AND user_role = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "is", $id, $role);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $publicKey);
    mysqli_stmt_fetch($stmt);
}

echo json_encode(['publicKey' => $publicKey]);