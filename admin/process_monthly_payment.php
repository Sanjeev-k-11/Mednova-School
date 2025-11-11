<?php
// process_monthly_payment.php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

// --- Process only POST requests ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Retrieve and sanitize input from the modal form ---
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
    $month_name = isset($_POST['month']) ? trim($_POST['month']) : '';
    $amount_paid_now = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0;
    $payment_date = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    // --- Validate the received data ---
    if ($student_id > 0 && $year > 0 && !empty($month_name) && $amount_paid_now > 0 && !empty($payment_date)) {
        
        // Convert month name to its numeric representation for the database query
        $month_number = date('m', strtotime($month_name));

        // 1. Fetch all fee items for this student for the given month/year that are not fully paid.
        // We order by ID to ensure a consistent payment application order.
        $sql_fetch = "SELECT id, amount_due, amount_paid FROM student_fees 
                      WHERE student_id = ? 
                      AND YEAR(due_date) = ? 
                      AND MONTH(due_date) = ? 
                      AND status IN ('Unpaid', 'Partially Paid')
                      ORDER BY id ASC";
        
        if ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
            mysqli_stmt_bind_param($stmt_fetch, "iii", $student_id, $year, $month_number);
            mysqli_stmt_execute($stmt_fetch);
            $result = mysqli_stmt_get_result($stmt_fetch);
            $fees_to_pay = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $fees_to_pay[] = $row;
            }
            mysqli_stmt_close($stmt_fetch);

            // 2. Loop through the fetched fees and apply the payment sequentially
            $remaining_payment_to_apply = $amount_paid_now;
            // Prepare a consistent note for all updated records from this transaction
            $note_to_add = "\nTransaction: Paid ₹" . number_format($amount_paid_now, 2) . " on " . $payment_date . ". Note: " . $notes;

            foreach ($fees_to_pay as $fee) {
                // If we've applied the whole payment, stop.
                if ($remaining_payment_to_apply <= 0) {
                    break;
                }

                $balance_on_this_fee = $fee['amount_due'] - $fee['amount_paid'];
                
                // The amount to pay for this specific fee item is the smaller of the two values
                $payment_for_this_fee = min($remaining_payment_to_apply, $balance_on_this_fee);
                
                $new_total_paid_for_fee = $fee['amount_paid'] + $payment_for_this_fee;
                $new_status = ($new_total_paid_for_fee >= $fee['amount_due']) ? 'Paid' : 'Partially Paid';
                
                // Update the current fee item in the database
                $sql_update = "UPDATE student_fees SET amount_paid = ?, status = ?, paid_at = ?, notes = CONCAT(IFNULL(notes, ''), ?) WHERE id = ?";
                if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                    mysqli_stmt_bind_param($stmt_update, "dsssi", $new_total_paid_for_fee, $new_status, $payment_date, $note_to_add, $fee['id']);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update);
                }
                
                // Decrease the remaining payment amount
                $remaining_payment_to_apply -= $payment_for_this_fee;
            }

            $_SESSION['message'] = "Payment of ₹" . number_format($amount_paid_now, 2) . " recorded successfully for $month_name, $year.";
            $_SESSION['message_type'] = "success";
        }
    } else {
        $_SESSION['message'] = "Invalid payment details provided. Please fill all required fields.";
        $_SESSION['message_type'] = "danger";
    }

    // Redirect back to the student's detail page to show the updated status
    header("location: view_student_details.php?id=" . $student_id);
    exit;

} else {
    // If not a POST request, redirect away.
    header("location: view_students.php");
    exit;
}
?>