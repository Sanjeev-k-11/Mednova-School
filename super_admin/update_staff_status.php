<?php
// update_staff_status.php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
// Ensure only a logged-in Super Admin can access this script.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// --- Process only POST requests ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Validate Input ---
    // Get the staff member's ID and cast it to an integer. Default to 0 if not provided.
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Get the role.
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    
    // Get the new status. It must be either '0' or '1'. Default to -1 for invalid values.
    $new_status = isset($_POST['new_status']) && in_array($_POST['new_status'], ['0', '1']) ? (int)$_POST['new_status'] : -1;

    // Proceed only if all inputs are valid.
    if ($id > 0 && !empty($role) && $new_status !== -1) {
        
        $table_name = '';
        // Determine which table to update based on the role.
        if ($role === 'Principal') {
            $table_name = 'principles';
        } elseif ($role === 'Teacher') {
            $table_name = 'teachers';
        }

        // Proceed only if the role was valid and a table name was set.
        if (!empty($table_name)) {
            // Prepare the SQL query to update the 'is_blocked' status.
            // Using a placeholder (?) prevents SQL injection.
            $sql = "UPDATE $table_name SET is_blocked = ? WHERE id = ?";
            
            if ($stmt = mysqli_prepare($link, $sql)) {
                // Bind the parameters: 'i' for integer.
                // The new status (0 or 1) and the ID are bound to the placeholders.
                mysqli_stmt_bind_param($stmt, "ii", $new_status, $id);
                
                // Execute the statement.
                if (mysqli_stmt_execute($stmt)) {
                    // If successful, set a success message in the session.
                    $_SESSION['message'] = "Staff status has been updated successfully.";
                    $_SESSION['message_type'] = 'success';
                } else {
                    // If it fails, set an error message.
                    $_SESSION['message'] = "Error updating status. Please try again.";
                    $_SESSION['message_type'] = 'danger';
                }
                // Close the statement.
                mysqli_stmt_close($stmt);
            }
        } else {
            $_SESSION['message'] = "Invalid role specified. Update failed.";
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        $_SESSION['message'] = "Invalid request parameters. Update failed.";
        $_SESSION['message_type'] = 'danger';
    }
}

// Close the database connection.
mysqli_close($link);

// --- Redirect back to the staff list page ---
// The user will see the result of the action here.
header("location: view_all_staff.php");
exit;
?>