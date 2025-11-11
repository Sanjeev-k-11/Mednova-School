<?php
// Start the session
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// --- Initialize variables and error messages ---
$staff_id = $staff_role = $salary_month = $salary_year = $base_salary = $deductions = $bonuses = $net_payable = "";
$error = "";

// --- Process form submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate and sanitize inputs
    if (isset($_POST["staff_id_role"]) && !empty(trim($_POST["staff_id_role"]))) {
        list($staff_id, $staff_role) = explode('-', trim($_POST["staff_id_role"]));
        $staff_id = (int)$staff_id;
        $staff_role = htmlspecialchars($staff_role);
    } else {
        $error = "Staff member not selected.";
    }

    if (isset($_POST["salary_month_input"]) && !empty(trim($_POST["salary_month_input"]))) {
        $salary_month_full = trim($_POST["salary_month_input"]);
        $date_parts = explode('-', $salary_month_full);
        $salary_year = (int)$date_parts[0];
        $salary_month = date('F', mktime(0, 0, 0, (int)$date_parts[1], 1));
    } else {
        $error = "Salary month not selected.";
    }

    if (isset($_POST["base_salary"]) && is_numeric(trim($_POST["base_salary"]))) {
        $base_salary = (float)trim($_POST["base_salary"]);
    } else {
        $error = "Invalid or missing base salary.";
    }

    $deductions = isset($_POST["deductions"]) && is_numeric($_POST["deductions"]) ? (float)$_POST["deductions"] : 0.00;
    $bonuses = isset($_POST["bonuses"]) && is_numeric($_POST["bonuses"]) ? (float)$_POST["bonuses"] : 0.00;
    $notes = isset($_POST["notes"]) ? htmlspecialchars(trim($_POST["notes"])) : NULL;

    // If there are no errors, proceed with inserting the data
    if (empty($error)) {
        // Check for existing record to prevent duplicates
        $check_sql = "SELECT id FROM staff_salary WHERE staff_id = ? AND staff_role = ? AND salary_month = ? AND salary_year = ?";
        if ($stmt_check = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt_check, "isss", $staff_id, $staff_role, $salary_month, $salary_year);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $error = "Salary for this staff member for the selected month and year already exists.";
            }
            mysqli_stmt_close($stmt_check);
        } else {
            $error = "Error checking for existing record.";
        }
    }

    if (empty($error)) {
        $net_payable = $base_salary + $bonuses - $deductions;
        $generated_by_admin_id = $_SESSION["id"];

        $insert_sql = "INSERT INTO staff_salary (staff_id, staff_role, salary_month, salary_year, base_salary, deductions, bonuses, net_payable, notes, generated_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($link, $insert_sql)) {
            // Corrected line 72: changed the type definition string and the bind variables to match.
            mysqli_stmt_bind_param($stmt, "issiddddsi", $param_staff_id, $param_staff_role, $param_month, $param_year, $param_base_salary, $param_deductions, $param_bonuses, $param_net_payable, $param_notes, $param_admin_id);
            
            $param_staff_id = $staff_id;
            $param_staff_role = $staff_role;
            $param_month = $salary_month;
            $param_year = $salary_year;
            $param_base_salary = $base_salary;
            $param_deductions = $deductions;
            $param_bonuses = $bonuses;
            $param_net_payable = $net_payable;
            $param_notes = $notes;
            $param_admin_id = $generated_by_admin_id;

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "Salary record created successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Something went wrong. Please try again later." . mysqli_error($link);
                $_SESSION['message_type'] = "danger";
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $_SESSION['message'] = $error;
        $_SESSION['message_type'] = "danger";
    }

    mysqli_close($link);
    
    // Redirect back to the create salary page
    header("location: create_salary.php");
    exit;

} else {
    // If it's not a POST request, redirect
    header("location: create_salary.php");
    exit;
}
?>