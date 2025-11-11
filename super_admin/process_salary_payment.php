<?php
// process_salary_payment.php

// Start the session
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    // It's better to send an error message if an unauthorized user somehow accesses this
    $_SESSION['message'] = "Unauthorized access.";
    $_SESSION['message_type'] = "danger";
    header("location: ../login.php");
    exit;
}

// --- Process form submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Validate the ONLY input we need: the salary_id
    if (isset($_POST["salary_id"]) && ctype_digit($_POST["salary_id"])) {
        $salary_id = (int)$_POST["salary_id"];
    } else {
        $_SESSION['message'] = "Invalid salary record ID provided.";
        $_SESSION['message_type'] = "danger";
        header("location: view_staff_salaries.php");
        exit;
    }

    // 2. Prepare an update statement using the primary key (`id`)
    // This is much safer and more efficient than matching by staff, role, and month.
    $sql = "UPDATE staff_salary SET status = 'Paid', paid_at = NOW() WHERE id = ? AND status = 'Generated'";

    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind the salary ID as a parameter
        mysqli_stmt_bind_param($stmt, "i", $salary_id);

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Check if a row was actually updated
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $_SESSION['message'] = "Salary payment has been successfully marked as 'Paid'.";
                $_SESSION['message_type'] = "success";
            } else {
                // This can happen if the salary was already 'Paid' or the ID was invalid
                $_SESSION['message'] = "Could not update status. The salary record may have already been paid or does not exist.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Oops! Something went wrong executing the update. Please try again later.";
            $_SESSION['message_type'] = "danger";
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['message'] = "Error preparing the database query.";
        $_SESSION['message_type'] = "danger";
    }

    // Close connection
    mysqli_close($link);

} else {
    // If it's not a POST request, just redirect without a message
    header("location: view_staff_salaries.php");
    exit;
}

// Redirect back to the view page to see the result
header("location: view_staff_salaries.php");
exit;
?>