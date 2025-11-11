<?php
// approve_leave.php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $application_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
    $admin_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
    $status = 'Approved';
    $reviewed_at = date('Y-m-d H:i:s');

    $sql = "UPDATE leave_applications SET status = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ? AND status = 'Pending'";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssi", $status, $admin_name, $reviewed_at, $application_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Leave application approved successfully.";
        } else {
            $_SESSION['error_message'] = "Error approving application: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Database error. Please try again later.";
    }
}

// Redirect back to the leave management page, preserving filters
header("location: leave_management.php?" . http_build_query($_GET));
exit;
?>