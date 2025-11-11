<?php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION['admin_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $new_status = isset($_POST['new_status']) && in_array($_POST['new_status'], ['Active', 'Blocked']) ? $_POST['new_status'] : '';

    if ($student_id > 0 && !empty($new_status)) {
        if ($new_status === 'Blocked') {
            // --- Handle Block ---
            $block_reason = trim($_POST['block_reason']);
            if (empty($block_reason)) {
                 $_SESSION['message'] = "A block reason is required.";
                 $_SESSION['message_type'] = "danger";
            } else {
                // Clear unblock info and set block info
                $sql = "UPDATE students SET 
                            status = 'Blocked', 
                            block_reason = ?, 
                            blocked_by_admin_id = ?, 
                            blocked_at = NOW(), 
                            unblock_reason = NULL, 
                            unblocked_by_admin_id = NULL, 
                            unblocked_at = NULL 
                        WHERE id = ?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sii", $block_reason, $admin_id, $student_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['message'] = "Student has been blocked.";
                        $_SESSION['message_type'] = "success";
                    }
                }
            }
        } elseif ($new_status === 'Active') {
            // --- Handle Unblock ---
            $unblock_reason = trim($_POST['unblock_reason']);
            if (empty($unblock_reason)) {
                 $_SESSION['message'] = "An unblock reason is required.";
                 $_SESSION['message_type'] = "danger";
            } else {
                // Clear block info and set unblock info
                $sql = "UPDATE students SET 
                            status = 'Active', 
                            unblock_reason = ?, 
                            unblocked_by_admin_id = ?, 
                            unblocked_at = NOW(),
                            block_reason = NULL, 
                            blocked_by_admin_id = NULL, 
                            blocked_at = NULL 
                        WHERE id = ?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sii", $unblock_reason, $admin_id, $student_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['message'] = "Student has been unblocked.";
                        $_SESSION['message_type'] = "success";
                    }
                }
            }
        }
    } else {
        $_SESSION['message'] = "Invalid request.";
        $_SESSION['message_type'] = "danger";
    }
}
header("location: view_students.php");
exit;
?>