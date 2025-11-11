<?php
// process_monthly_payment.php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
    $month_name = isset($_POST['month']) ? $_POST['month'] : '';
    $amount_paid_now = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0;
    $payment_date = $_POST['payment_date'];
    $notes = trim($_POST['notes']);

    // Convert month name to number
    $month_number = date('m', strtotime($month_name));

    if ($student_id > 0 && $year > 0 && $month_number > 0 && $amount_paid_now > 0) {
        // 1. Fetch all unpaid/partially paid fees for this student for the given month/year
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

            // 2. Loop through the fees and apply the payment
            $remaining_payment = $amount_paid_now;
            $note_to_add = "\nPaid: " . number_format($amount_paid_now, 2) . " on " . $payment_date . ". Note: " . $notes;

            foreach ($fees_to_pay as $fee) {
                if ($remaining_payment <= 0) break;

                $balance_on_fee = $fee['amount_due'] - $fee['amount_paid'];
                $payment_for_this_fee = min($remaining_payment, $balance_on_fee);
                
                $new_total_paid = $fee['amount_paid'] + $payment_for_this_fee;
                $new_status = ($new_total_paid >= $fee['amount_due']) ? 'Paid' : 'Partially Paid';
                
                $sql_update = "UPDATE student_fees SET amount_paid = ?, status = ?, paid_at = ?, notes = CONCAT(IFNULL(notes, ''), ?) WHERE id = ?";
                if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                    mysqli_stmt_bind_param($stmt_update, "dsssi", $new_total_paid, $new_status, $payment_date, $note_to_add, $fee['id']);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update);
                }
                
                $remaining_payment -= $payment_for_this_fee;
            }
            $_SESSION['message'] = "Payment of ₹" . number_format($amount_paid_now, 2) . " recorded successfully.";
            $_SESSION['message_type'] = "success";
        }
    } else {
        $_SESSION['message'] = "Invalid payment details provided.";
        $_SESSION['message_type'] = "danger";
    }
    header("location: view_student_details.php?id=" . $student_id);
    exit;
}
?>