<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary

// Cloudinary configuration and library (for book covers)
require_once "../database/cloudinary_upload_handler.php"; // Your Cloudinary credentials
require '../database/vendor/autoload.php';        // Path to Composer's autoload.php

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php"); 
    exit;
}

$teacher_id = $_SESSION['id'] ?? null; 
$flash_message = '';
$flash_message_type = ''; // 'success', 'error', 'info'

// --- CORRECTED: Initialize Cloudinary Configuration ---
try { 
} catch (Exception $e) {
    error_log("Cloudinary Config Error: " . $e->getMessage());
    $flash_message = "Cloudinary service not available. Contact administrator.";
    $flash_message_type = 'error';
    // Optionally, disable file upload related forms if Cloudinary is essential
}

// --- Check if Teacher is assigned to the 'Library' department ---
$is_library_teacher = false;
$sql_check_library_role = "
    SELECT d.department_name 
    FROM teachers t
    JOIN departments d ON t.department_id = d.id
    WHERE t.id = ? AND d.department_name = 'Library' LIMIT 1
";
if ($stmt = mysqli_prepare($link, $sql_check_library_role)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        $is_library_teacher = true;
    }
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Prepare Library Role Check Error: " . mysqli_error($link));
}

// --- CONSTANTS ---
$daily_fine_rate = 5.00; // Rs 5 per day overdue


// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $is_library_teacher) {
    // --- Book Management Actions (Add/Edit/Delete) ---
    if (isset($_POST['book_action'])) {
        $book_action = $_POST['book_action'];
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $publication_year = filter_input(INPUT_POST, 'publication_year', FILTER_VALIDATE_INT);
        $genre = trim($_POST['genre'] ?? '');
        $total_copies = filter_input(INPUT_POST, 'total_copies', FILTER_VALIDATE_INT) ?: 1;
        $description = trim($_POST['description'] ?? '');
        $book_id = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT); // For edit/delete

        $cover_image_url = $_POST['current_cover_image_url'] ?? null; // Keep existing URL for edit

        if (empty($title) || empty($author) || !$publication_year || !$total_copies) {
            $flash_message = "Title, Author, Publication Year, and Total Copies are required for books.";
            $flash_message_type = 'error';
        } else {
            // Cloudinary Upload for new cover image
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png'];
                if (!in_array($_FILES['cover_image']['type'], $allowed_types)) {
                    $flash_message = "Invalid image type. Only JPG/PNG allowed for book cover."; $flash_message_type = 'error';
                } elseif ($_FILES['cover_image']['size'] > 2 * 1024 * 1024) { // 2MB limit
                    $flash_message = "Cover image size exceeds 2MB limit."; $flash_message_type = 'error';
                } else {
                    try {
                        $upload_api = new UploadApi();
                        $upload_result = $upload_api->upload(
                            $_FILES['cover_image']['tmp_name'],
                            ['folder' => 'book_covers', 'resource_type' => 'image']
                        );
                        $cover_image_url = $upload_result['secure_url'];
                    } catch (Exception $e) {
                        $flash_message = "Cover image upload failed: " . $e->getMessage(); $flash_message_type = 'error';
                        error_log("Cloudinary Book Cover Upload Error: " . $e->getMessage());
                    }
                }
            }

            if ($flash_message_type !== 'error') { // Proceed if no file upload errors
                if ($book_action === 'add') {
                    $sql_insert_book = "INSERT INTO books (title, author, isbn, publisher, publication_year, genre, total_copies, available_copies, cover_image_url, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    if ($stmt = mysqli_prepare($link, $sql_insert_book)) {
                        mysqli_stmt_bind_param($stmt, "ssssiisiss", $title, $author, $isbn, $publisher, $publication_year, $genre, $total_copies, $total_copies, $cover_image_url, $description);
                        if (mysqli_stmt_execute($stmt)) { $flash_message = "Book '{$title}' added successfully!"; $flash_message_type = 'success'; }
                        else { $flash_message = "Error adding book: " . mysqli_stmt_error($stmt); $flash_message_type = 'error'; }
                        mysqli_stmt_close($stmt);
                    }
                } elseif ($book_action === 'edit' && $book_id) {
                    $sql_update_book = "UPDATE books SET title=?, author=?, isbn=?, publisher=?, publication_year=?, genre=?, total_copies=?, description=?, cover_image_url=? WHERE id=?";
                    // Note: available_copies is not directly updated by admin here; it's managed by borrow/return
                    if ($stmt = mysqli_prepare($link, $sql_update_book)) {
                        mysqli_stmt_bind_param($stmt, "ssssissssi", $title, $author, $isbn, $publisher, $publication_year, $genre, $total_copies, $description, $cover_image_url, $book_id);
                        if (mysqli_stmt_execute($stmt)) { $flash_message = "Book '{$title}' updated successfully!"; $flash_message_type = 'success'; }
                        else { $flash_message = "Error updating book: " . mysqli_stmt_error($stmt); $flash_message_type = 'error'; }
                        mysqli_stmt_close($stmt);
                    }
                } elseif ($book_action === 'delete' && $book_id) {
                    // Check if book is currently borrowed
                    $sql_check_borrowed = "SELECT COUNT(*) FROM borrow_records WHERE book_id = ? AND status IN ('Borrowed', 'Overdue')";
                    if ($stmt_check = mysqli_prepare($link, $sql_check_borrowed)) {
                        mysqli_stmt_bind_param($stmt_check, "i", $book_id);
                        mysqli_stmt_execute($stmt_check);
                        mysqli_stmt_bind_result($stmt_check, $borrowed_count);
                        mysqli_stmt_fetch($stmt_check);
                        mysqli_stmt_close($stmt_check);
                        if ($borrowed_count > 0) {
                            $flash_message = "Cannot delete book. It is currently borrowed by {$borrowed_count} student(s).";
                            $flash_message_type = 'error';
                        } else {
                            $sql_delete_book = "DELETE FROM books WHERE id=?";
                            if ($stmt = mysqli_prepare($link, $sql_delete_book)) {
                                mysqli_stmt_bind_param($stmt, "i", $book_id);
                                if (mysqli_stmt_execute($stmt)) { $flash_message = "Book deleted successfully!"; $flash_message_type = 'success'; }
                                else { $flash_message = "Error deleting book: " . mysqli_stmt_error($stmt); $flash_message_type = 'error'; }
                                mysqli_stmt_close($stmt);
                            }
                        }
                    }
                }
            }
        }
    }
    // --- Borrow Book Action ---
    elseif (isset($_POST['borrow_action'])) {
        $student_id_borrow = filter_input(INPUT_POST, 'student_id_borrow', FILTER_VALIDATE_INT);
        $book_id_borrow = filter_input(INPUT_POST, 'book_id_borrow', FILTER_VALIDATE_INT);
        $due_date_borrow = trim($_POST['due_date_borrow'] ?? '');

        if (!$student_id_borrow || !$book_id_borrow || empty($due_date_borrow)) {
            $flash_message = "All fields (Student, Book, Due Date) are required for borrowing."; $flash_message_type = 'error';
        } else {
            // Check available copies
            $sql_check_copies = "SELECT available_copies, title FROM books WHERE id = ?";
            if($stmt_check = mysqli_prepare($link, $sql_check_copies)){
                mysqli_stmt_bind_param($stmt_check, "i", $book_id_borrow);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_bind_result($stmt_check, $available, $book_title);
                mysqli_stmt_fetch($stmt_check);
                mysqli_stmt_close($stmt_check);

                if ($available > 0) {
                    // Check if student already has this book borrowed and not returned
                    $sql_check_existing_borrow = "SELECT COUNT(*) FROM borrow_records WHERE student_id = ? AND book_id = ? AND status = 'Borrowed'";
                    if($stmt_check_exist = mysqli_prepare($link, $sql_check_existing_borrow)){
                        mysqli_stmt_bind_param($stmt_check_exist, "ii", $student_id_borrow, $book_id_borrow);
                        mysqli_stmt_execute($stmt_check_exist);
                        mysqli_stmt_bind_result($stmt_check_exist, $existing_borrow_count);
                        mysqli_stmt_fetch($stmt_check_exist);
                        mysqli_stmt_close($stmt_check_exist);

                        if ($existing_borrow_count > 0) {
                            $flash_message = "This student already has a copy of '{$book_title}' currently borrowed.";
                            $flash_message_type = 'error';
                        } else {
                            // Calculate initial status and fine (Overdue if due_date is in past)
                            $status_borrow = 'Borrowed';
                            $initial_fine = 0.00;
                            if (strtotime($due_date_borrow) < strtotime(date('Y-m-d'))) {
                                $status_borrow = 'Overdue';
                                $days_overdue = max(0, (strtotime(date('Y-m-d')) - strtotime($due_date_borrow)) / (60 * 60 * 24));
                                $initial_fine = $days_overdue * $daily_fine_rate;
                            }

                            $sql_borrow = "INSERT INTO borrow_records (book_id, student_id, borrow_date, due_date, status, fine_amount, recorded_by_teacher_id) VALUES (?, ?, CURDATE(), ?, ?, ?, ?)";
                            if ($stmt = mysqli_prepare($link, $sql_borrow)) {
                                mysqli_stmt_bind_param($stmt, "iissdi", $book_id_borrow, $student_id_borrow, $due_date_borrow, $status_borrow, $initial_fine, $teacher_id);
                                if (mysqli_stmt_execute($stmt)) {
                                    // Decrement available copies
                                    $sql_decrement = "UPDATE books SET available_copies = available_copies - 1 WHERE id = ?";
                                    if($stmt_dec = mysqli_prepare($link, $sql_decrement)){
                                        mysqli_stmt_bind_param($stmt_dec, "i", $book_id_borrow);
                                        mysqli_stmt_execute($stmt_dec);
                                        mysqli_stmt_close($stmt_dec);
                                    }
                                    $flash_message = "Book '{$book_title}' borrowed by student successfully!"; $flash_message_type = 'success';
                                } else { $flash_message = "Error borrowing book: " . mysqli_stmt_error($stmt); $flash_message_type = 'error'; }
                                mysqli_stmt_close($stmt);
                            }
                        }
                    }
                } else {
                    $flash_message = "Book '{$book_title}' is currently out of available copies."; $flash_message_type = 'error';
                }
            }
        }
    }
    // --- Return Book Action ---
    elseif (isset($_POST['return_action'])) {
        $borrow_record_id = filter_input(INPUT_POST, 'borrow_record_id', FILTER_VALIDATE_INT);
        $book_id_return = filter_input(INPUT_POST, 'book_id_return', FILTER_VALIDATE_INT);
        $status_return = $_POST['status_return'] ?? 'Returned'; // Can be 'Returned' or 'Lost'
        $manual_fine_input = filter_input(INPUT_POST, 'manual_fine_input', FILTER_VALIDATE_FLOAT) ?: 0.00; // Manual input from form

        if (!$borrow_record_id || !$book_id_return) {
            $flash_message = "Invalid borrow record or book ID for return."; $flash_message_type = 'error';
        } else {
            // Fetch borrow details to calculate final fine
            $sql_get_borrow_details = "SELECT book_id, due_date FROM borrow_records WHERE id = ?";
            if($stmt_get_br = mysqli_prepare($link, $sql_get_borrow_details)){
                mysqli_stmt_bind_param($stmt_get_br, "i", $borrow_record_id);
                mysqli_stmt_execute($stmt_get_br);
                mysqli_stmt_bind_result($stmt_get_br, $fetched_book_id, $due_date);
                mysqli_stmt_fetch($stmt_get_br);
                mysqli_stmt_close($stmt_get_br);

                if ($fetched_book_id != $book_id_return) { // Security check
                     $flash_message = "Book ID mismatch for return record."; $flash_message_type = 'error';
                } else {
                    $calculated_fine = 0.00;
                    $today_date = strtotime(date('Y-m-d'));
                    $due_date_timestamp = strtotime($due_date);

                    if ($today_date > $due_date_timestamp) {
                        $days_overdue = floor(($today_date - $due_date_timestamp) / (60 * 60 * 24));
                        $calculated_fine = $days_overdue * $daily_fine_rate;
                    }
                    
                    // The fine_amount in DB stores the *total* fine including previous fines and the new calculated/manual fine.
                    // If you want to show ONLY the calculated/manual part, you'd fetch previous fine_amount and add to it.
                    // For now, let's assume fine_amount_paid completely overrides/sets the new fine.
                    // If the user inputs a fine, that's the one we use, otherwise calculated.
                    $final_fine_to_record = $manual_fine_input > 0 ? $manual_fine_input : $calculated_fine;

                    $sql_return = "UPDATE borrow_records SET return_date = CURDATE(), status = ?, fine_amount = ?, recorded_by_teacher_id = ? WHERE id = ?";
                    if ($stmt = mysqli_prepare($link, $sql_return)) {
// Corrected Line 242:
mysqli_stmt_bind_param($stmt, "sdii", $status_return, $final_fine_to_record, $teacher_id, $borrow_record_id);                        if (mysqli_stmt_execute($stmt)) {
                            // Increment available copies ONLY if returned and not 'Lost'
                            if ($status_return === 'Returned') {
                                $sql_increment = "UPDATE books SET available_copies = available_copies + 1 WHERE id = ?";
                                if($stmt_inc = mysqli_prepare($link, $sql_increment)){
                                    mysqli_stmt_bind_param($stmt_inc, "i", $book_id_return);
                                    mysqli_stmt_execute($stmt_inc);
                                    mysqli_stmt_close($stmt_inc);
                                }
                            }
                            $flash_message = "Book return recorded successfully!"; $flash_message_type = 'success';
                        } else { $flash_message = "Error recording return: " . mysqli_stmt_error($stmt); $flash_message_type = 'error'; }
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    }
    // Redirect to prevent form resubmission
    header("Location: teacher_library.php");
    exit();
}


// --- DATA FETCHING FOR DISPLAY ---

// Dashboard Overview
$dashboard_stats = [
    'total_books' => 0,
    'available_books' => 0,
    'total_borrowed' => 0,
    'overdue_books' => 0,
    'total_fines' => 0.00
];
$sql_stats = "
    SELECT
        (SELECT COUNT(id) FROM books) AS total_books,
        (SELECT SUM(available_copies) FROM books) AS available_books,
        (SELECT COUNT(id) FROM borrow_records WHERE status = 'Borrowed') AS total_borrowed,
        (SELECT COUNT(id) FROM borrow_records WHERE status = 'Overdue') AS overdue_books,
        (SELECT SUM(fine_amount) FROM borrow_records WHERE status = 'Overdue') AS total_fines
";
if ($result = mysqli_query($link, $sql_stats)) {
    $dashboard_stats = mysqli_fetch_assoc($result);
    $dashboard_stats['total_fines'] = number_format($dashboard_stats['total_fines'] ?? 0.00, 2);
    mysqli_free_result($result);
}

// Overdue Students Summary (for top of page)
$overdue_students_summary = [];
$sql_overdue_summary = "
    SELECT 
        s.id AS student_id,
        CONCAT(s.first_name, ' ', s.last_name) AS student_name, -- Corrected student name fetching
        COUNT(br.id) AS overdue_count,
        SUM(br.fine_amount) AS total_fine
    FROM borrow_records br
    JOIN students s ON br.student_id = s.id
    WHERE br.status = 'Overdue'
    GROUP BY s.id, s.first_name, s.last_name
    ORDER BY total_fine DESC
    LIMIT 5;
";
if ($result = mysqli_query($link, $sql_overdue_summary)) {
    $overdue_students_summary = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}


// All Students (for borrowing dropdown)
$all_students = [];
$sql_all_students = "SELECT id, CONCAT(first_name, ' ', last_name, ' (Roll: ', roll_number, ')') AS full_student_name FROM students ORDER BY first_name";
if ($result = mysqli_query($link, $sql_all_students)) {
    $all_students = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// All Available Books (for borrowing dropdown)
$available_books_for_borrow = [];
$sql_available_books = "SELECT id, CONCAT(title, ' by ', author, ' (Available: ', available_copies, ')') AS full_book_name, available_copies FROM books WHERE available_copies > 0 ORDER BY title";
if ($result = mysqli_query($link, $sql_available_books)) {
    $available_books_for_borrow = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// Currently Borrowed Records (for return dropdown/table)
$current_borrowed_records = [];
$sql_current_borrowed = "
    SELECT 
        br.id AS borrow_record_id,
        br.book_id,
        b.title,
        s.id AS student_id,
        CONCAT(s.first_name, ' ', s.last_name, ' (Roll: ', s.roll_number, ')') AS student_name,
        br.borrow_date,
        br.due_date,
        br.fine_amount,
        br.status
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN students s ON br.student_id = s.id
    WHERE br.status IN ('Borrowed', 'Overdue')
    ORDER BY br.due_date ASC;
";
if ($result = mysqli_query($link, $sql_current_borrowed)) {
    $current_borrowed_records = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// All Books (for manage books table)
$all_books_manage = [];
$sql_all_books_manage = "SELECT id, title, author, isbn, publisher, publication_year, genre, total_copies, available_copies, cover_image_url, description FROM books ORDER BY title ASC";
if ($result = mysqli_query($link, $sql_all_books_manage)) {
    $all_books_manage = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// All Overdue Books (for overdue table)
$all_overdue_books = [];
$sql_all_overdue_books = "
    SELECT 
        br.id AS borrow_record_id,
        b.title,
        b.author,
        CONCAT(s.first_name, ' ', s.last_name) AS student_name, -- Corrected student name fetching
        br.borrow_date,
        br.due_date,
        DATEDIFF(CURDATE(), br.due_date) AS days_overdue,
        br.fine_amount
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN students s ON br.student_id = s.id
    WHERE br.status = 'Overdue'
    ORDER BY br.due_date ASC;
";
if ($result = mysqli_query($link, $sql_all_overdue_books)) {
    $all_overdue_books = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}


mysqli_close($link);

$default_book_cover = '../assets/images/default-book-cover.png'; // Adjust path
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - Teacher</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .dashboard-container { min-height: calc(100vh - 80px); }
        .toast-notification { position: fixed; top: 20px; right: 20px; z-index: 1000; opacity: 0; transform: translateY(-20px); transition: opacity 0.3s ease-out, transform 0.3s ease-out; }
        .toast-notification.show { opacity: 1; transform: translateY(0); }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-overlay:not(.hidden) { display: flex; }
        .modal-content { background-color: white; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); max-width: 600px; width: 90%; }
        .tab-button.active { @apply bg-indigo-600 text-white; }
        .book-cover-preview { max-width: 150px; max-height: 200px; object-fit: contain; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
<!-- admin_header.php content usually goes here -->

<div class="dashboard-container p-4 sm:p-6">
    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-notification"></div>

    <!-- Main Header Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Library Management System</h1>
        <p class="text-gray-600">As a Library Teacher, manage books, borrowings, and returns.</p>
    </div>

    <?php if (!$is_library_teacher): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative max-w-2xl mx-auto text-center shadow-md">
            <strong class="font-bold">Access Denied!</strong>
            <span class="block sm:inline"> You are not authorized to access this page. This page is only for teachers assigned to the 'Library' department.</span>
        </div>
    <?php else: ?>

    <!-- Overdue Summary Bar -->
    <?php if (!empty($overdue_students_summary)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl shadow-md mb-6 flex flex-wrap items-center justify-between">
            <div class="font-bold text-lg flex items-center mb-2 md:mb-0">
                <i class="fas fa-exclamation-triangle mr-3 text-red-600"></i>
                <span>Overdue Alert!</span>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                <?php foreach ($overdue_students_summary as $summary): ?>
                    <span>
                        <span class="font-semibold"><?php echo htmlspecialchars($summary['student_name']); ?>:</span> 
                        <?php echo htmlspecialchars($summary['overdue_count']); ?> book(s), Fine: ₹<?php echo number_format($summary['total_fine'], 2); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs for different sections -->
    <div class="bg-white rounded-xl shadow-lg mb-6">
        <div class="flex border-b border-gray-200">
            <button class="tab-button py-3 px-6 text-gray-700 font-medium hover:bg-gray-50 active" data-tab="dashboard">
                <i class="fas fa-chart-line mr-2"></i> Dashboard
            </button>
            <button class="tab-button py-3 px-6 text-gray-700 font-medium hover:bg-gray-50" data-tab="borrow-return">
                <i class="fas fa-exchange-alt mr-2"></i> Borrow / Return
            </button>
            <button class="tab-button py-3 px-6 text-gray-700 font-medium hover:bg-gray-50" data-tab="manage-books">
                <i class="fas fa-book mr-2"></i> Manage Books
            </button>
            <button class="tab-button py-3 px-6 text-gray-700 font-medium hover:bg-gray-50" data-tab="overdue-list">
                <i class="fas fa-clock mr-2"></i> Overdue List
            </button>
        </div>

        <div class="p-6">
            <!-- Dashboard Tab Content -->
            <div id="dashboard" class="tab-content">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-chart-pie mr-2 text-indigo-500"></i> Library Overview
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-5 text-center shadow-sm">
                        <i class="fas fa-book fa-3x text-blue-500 mb-3"></i>
                        <p class="text-sm text-gray-600">Total Books</p>
                        <p class="text-3xl font-bold text-blue-800"><?php echo htmlspecialchars($dashboard_stats['total_books']); ?></p>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-5 text-center shadow-sm">
                        <i class="fas fa-check-circle fa-3x text-green-500 mb-3"></i>
                        <p class="text-sm text-gray-600">Available Copies</p>
                        <p class="text-3xl font-bold text-green-800"><?php echo htmlspecialchars($dashboard_stats['available_books']); ?></p>
                    </div>
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-5 text-center shadow-sm">
                        <i class="fas fa-handshake fa-3x text-indigo-500 mb-3"></i>
                        <p class="text-sm text-gray-600">Books Borrowed</p>
                        <p class="text-3xl font-bold text-indigo-800"><?php echo htmlspecialchars($dashboard_stats['total_borrowed']); ?></p>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-5 text-center shadow-sm">
                        <i class="fas fa-hourglass-half fa-3x text-red-500 mb-3"></i>
                        <p class="text-sm text-gray-600">Overdue Books</p>
                        <p class="text-3xl font-bold text-red-800"><?php echo htmlspecialchars($dashboard_stats['overdue_books']); ?></p>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-5 text-center shadow-sm">
                        <i class="fas fa-coins fa-3x text-yellow-500 mb-3"></i>
                        <p class="text-sm text-gray-600">Total Fines</p>
                        <p class="text-3xl font-bold text-yellow-800">₹<?php echo htmlspecialchars($dashboard_stats['total_fines']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Borrow / Return Tab Content -->
            <div id="borrow-return" class="tab-content hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-exchange-alt mr-2 text-indigo-500"></i> Borrow and Return Books
                </h2>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Borrow Book Form -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 shadow-sm">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-arrow-alt-circle-right mr-2 text-blue-500"></i> Issue New Book
                        </h3>
                        <form action="teacher_library.php" method="POST">
                            <input type="hidden" name="borrow_action" value="borrow">
                            <div class="mb-4">
                                <label for="student_id_borrow" class="block text-sm font-medium text-gray-700 mb-1">Select Student <span class="text-red-500">*</span></label>
                                <select name="student_id_borrow" id="student_id_borrow" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">-- Select Student --</option>
                                    <?php foreach ($all_students as $student): ?>
                                        <option value="<?php echo htmlspecialchars($student['id']); ?>"><?php echo htmlspecialchars($student['full_student_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="book_id_borrow" class="block text-sm font-medium text-gray-700 mb-1">Select Book <span class="text-red-500">*</span></label>
                                <select name="book_id_borrow" id="book_id_borrow" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">-- Select Book --</option>
                                    <?php foreach ($available_books_for_borrow as $book): ?>
                                        <option value="<?php echo htmlspecialchars($book['id']); ?>"><?php echo htmlspecialchars($book['full_book_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-6">
                                <label for="due_date_borrow" class="block text-sm font-medium text-gray-700 mb-1">Due Date <span class="text-red-500">*</span></label>
                                <input type="date" name="due_date_borrow" id="due_date_borrow" required value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div class="text-right">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">Issue Book</button>
                            </div>
                        </form>
                    </div>

                    <!-- Return Book Form -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 shadow-sm">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-arrow-alt-circle-left mr-2 text-green-500"></i> Record Book Return
                        </h3>
                        <form action="teacher_library.php" method="POST">
                            <input type="hidden" name="return_action" value="return">
                            <div class="mb-4">
                                <label for="borrow_record_id" class="block text-sm font-medium text-gray-700 mb-1">Select Borrowed Book <span class="text-red-500">*</span></label>
                                <select name="borrow_record_id" id="borrow_record_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" onchange="updateReturnDetails()">
                                    <option value="">-- Select Borrowed Book --</option>
                                    <?php foreach ($current_borrowed_records as $record): ?>
                                        <option value="<?php echo htmlspecialchars($record['borrow_record_id']); ?>"
                                            data-book-id="<?php echo htmlspecialchars($record['book_id']); ?>"
                                            data-due-date="<?php echo htmlspecialchars($record['due_date']); ?>"
                                            data-status="<?php echo htmlspecialchars($record['status']); ?>">
                                            <?php echo htmlspecialchars($record['title']); ?> (by <?php echo htmlspecialchars($record['student_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="book_id_return" id="book_id_return">
                            <div class="mb-4">
                                <label for="status_return" class="block text-sm font-medium text-gray-700 mb-1">Return Status <span class="text-red-500">*</span></label>
                                <select name="status_return" id="status_return" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="Returned">Returned</option>
                                    <option value="Lost">Lost</option>
                                </select>
                            </div>
                            <div class="mb-6">
                                <label for="fine_amount_paid" class="block text-sm font-medium text-gray-700 mb-1">Fine (if any) <span class="text-gray-500 text-xs">(Calculated automatically if overdue)</span></label>
                                <input type="number" step="0.01" min="0" name="fine_amount_paid" id="fine_amount_paid" value="0.00" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div class="text-right">
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md">Record Return</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Manage Books Tab Content -->
            <div id="manage-books" class="tab-content hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-cogs mr-2 text-indigo-500"></i> Manage Library Books
                </h2>

                <button onclick="openBookModal('add')" class="mb-6 bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md inline-flex items-center justify-center">
                    <i class="fas fa-plus mr-2"></i> Add New Book
                </button>

                <?php if (empty($all_books_manage)): ?>
                    <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 flex-grow flex flex-col items-center justify-center">
                        <i class="fas fa-book-open fa-4x mb-4 text-gray-400"></i>
                        <p class="text-xl font-semibold mb-2">No books in the library yet!</p>
                        <p class="text-lg">Use the button above to add your first book.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ISBN</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Copies</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Available</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($all_books_manage as $book): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($book['author']); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($book['isbn'] ?: 'N/A'); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-center text-sm text-gray-600"><?php echo htmlspecialchars($book['total_copies']); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-center text-sm text-gray-600"><?php echo htmlspecialchars($book['available_copies']); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-left text-sm font-medium">
                                            <button onclick="openBookModal('edit', <?php echo htmlspecialchars(json_encode($book)); ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-edit"></i> Edit</button>
                                            <button onclick="confirmDeleteBook(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i> Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Overdue List Tab Content -->
            <div id="overdue-list" class="tab-content hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-clock mr-2 text-indigo-500"></i> Overdue Books
                </h2>
                <?php if (empty($all_overdue_books)): ?>
                    <div class="text-center p-8 bg-green-50 border border-green-200 rounded-lg text-green-800 flex-grow flex flex-col items-center justify-center">
                        <i class="fas fa-check-double fa-4x mb-4 text-green-400"></i>
                        <p class="text-xl font-semibold mb-2">No books are currently overdue!</p>
                        <p class="text-lg">Great job managing returns!</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book Title</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrowed On</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Days Overdue</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fine (₹<?php echo $daily_fine_rate; ?>/day)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($all_overdue_books as $book): ?>
                                    <tr class="hover:bg-red-50">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($book['student_name']); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo date('M d, Y', strtotime($book['borrow_date'])); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-red-600"><?php echo date('M d, Y', strtotime($book['due_date'])); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-center text-sm font-bold text-red-600"><?php echo htmlspecialchars($book['days_overdue']); ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-red-600">₹<?php echo number_format($book['days_overdue'] * $daily_fine_rate, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Book Management Modal (Add/Edit) -->
<div id="bookModal" class="modal-overlay hidden">
    <div class="modal-content">
        <h3 id="bookModalTitle" class="text-xl font-bold mb-4 text-gray-800"></h3>
        <form id="bookForm" action="teacher_library.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="book_action" id="bookAction">
            <input type="hidden" name="book_id" id="bookId">
            <input type="hidden" name="current_cover_image_url" id="currentCoverImageUrl">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="bookTitle" class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="bookTitle" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Book Title">
                </div>
                <div>
                    <label for="bookAuthor" class="block text-sm font-medium text-gray-700 mb-1">Author <span class="text-red-500">*</span></label>
                    <input type="text" name="author" id="bookAuthor" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Author Name">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="bookISBN" class="block text-sm font-medium text-gray-700 mb-1">ISBN (Optional)</label>
                    <input type="text" name="isbn" id="bookISBN" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="ISBN">
                </div>
                <div>
                    <label for="bookPublisher" class="block text-sm font-medium text-gray-700 mb-1">Publisher (Optional)</label>
                    <input type="text" name="publisher" id="bookPublisher" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Publisher">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="bookPublicationYear" class="block text-sm font-medium text-gray-700 mb-1">Publication Year <span class="text-red-500">*</span></label>
                    <input type="number" name="publication_year" id="bookPublicationYear" required min="1000" max="<?php echo date('Y'); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="bookGenre" class="block text-sm font-medium text-gray-700 mb-1">Genre (Optional)</label>
                    <input type="text" name="genre" id="bookGenre" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="e.g., Fiction, Science">
                </div>
            </div>
            <div class="mb-4">
                <label for="bookTotalCopies" class="block text-sm font-medium text-gray-700 mb-1">Total Copies <span class="text-red-500">*</span></label>
                <input type="number" name="total_copies" id="bookTotalCopies" required min="1" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="mb-4">
                <label for="bookDescription" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                <textarea name="description" id="bookDescription" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Brief summary of the book..."></textarea>
            </div>
            <div class="mb-6">
                <label for="coverImage" class="block text-sm font-medium text-gray-700 mb-1">Book Cover Image (JPG/PNG, Max 2MB)</label>
                <input type="file" name="cover_image" id="coverImage" accept=".jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-gray-500">
                <div id="currentCoverPreview" class="mt-2 text-center" style="display: none;">
                    <p class="text-xs text-gray-500 mb-1">Current Cover:</p>
                    <img id="currentCoverImg" src="#" alt="Current Cover" class="book-cover-preview mx-auto rounded-md">
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeBookModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md">Cancel</button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md">Save Book</button>
            </div>
        </form>
    </div>
</div>

<?php require_once "./teacher_footer.php"; // Using admin_footer as this is an admin/teacher-specific page ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toastContainer = document.getElementById('toast-container');
        const bookModal = document.getElementById('bookModal');
        const bookModalTitle = document.getElementById('bookModalTitle');
        const bookAction = document.getElementById('bookAction');
        const bookId = document.getElementById('bookId');
        const bookTitle = document.getElementById('bookTitle');
        const bookAuthor = document.getElementById('bookAuthor');
        const bookISBN = document.getElementById('bookISBN');
        const bookPublisher = document.getElementById('bookPublisher');
        const bookPublicationYear = document.getElementById('bookPublicationYear');
        const bookGenre = document.getElementById('bookGenre');
        const bookTotalCopies = document.getElementById('bookTotalCopies');
        const bookDescription = document.getElementById('bookDescription');
        const coverImageInput = document.getElementById('coverImage');
        const currentCoverPreview = document.getElementById('currentCoverPreview');
        const currentCoverImg = document.getElementById('currentCoverImg');
        const currentCoverImageUrl = document.getElementById('currentCoverImageUrl');
        
        const borrowRecordIdSelect = document.getElementById('borrow_record_id');
        const bookIdReturnInput = document.getElementById('book_id_return');
        const fineAmountInput = document.getElementById('fine_amount_paid');

        // --- Toast Notification Function ---
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `p-4 rounded-lg shadow-lg text-white text-sm font-semibold flex items-center toast-notification`;
            let bgColor = ''; let iconClass = '';
            if (type === 'success') { bgColor = 'bg-green-500'; iconClass = 'fas fa-check-circle'; }
            else if (type === 'error') { bgColor = 'bg-red-500'; iconClass = 'fas fa-times-circle'; }
            else { bgColor = 'bg-blue-500'; iconClass = 'fas fa-info-circle'; }
            toast.classList.add(bgColor);
            toast.innerHTML = `<i class="${iconClass} mr-2"></i> ${message}`;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => { toast.classList.remove('show'); toast.addEventListener('transitionend', () => toast.remove()); }, 5000);
        }

        // --- Display initial flash message from PHP (if any) ---
        <?php if ($flash_message): ?>
            showToast("<?php echo htmlspecialchars($flash_message); ?>", "<?php echo htmlspecialchars($flash_message_type); ?>");
        <?php endif; ?>

        // --- Tab Switching Logic ---
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        function showTab(tabId) {
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });

            document.getElementById(tabId).classList.remove('hidden');
            document.querySelector(`.tab-button[data-tab="${tabId}"]`).classList.add('active');
        }

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                showTab(button.dataset.tab);
            });
        });
        showTab('dashboard'); // Show dashboard tab by default on load

        // --- Book Modal Functions ---
        window.openBookModal = function(action, bookData = {}) {
            bookModalTitle.textContent = (action === 'add') ? 'Add New Book' : 'Edit Book Details';
            bookAction.value = action;
            bookId.value = bookData.id || '';
            bookTitle.value = bookData.title || '';
            bookAuthor.value = bookData.author || '';
            bookISBN.value = bookData.isbn || '';
            bookPublisher.value = bookData.publisher || '';
            bookPublicationYear.value = bookData.publication_year || '';
            bookGenre.value = bookData.genre || '';
            bookTotalCopies.value = bookData.total_copies || '';
            bookDescription.value = bookData.description || '';
            
            // Handle book cover image preview
            if (action === 'edit' && bookData.cover_image_url) {
                currentCoverImg.src = bookData.cover_image_url;
                currentCoverImageUrl.value = bookData.cover_image_url; // Hidden input to retain URL if no new upload
                currentCoverPreview.style.display = 'block';
            } else {
                currentCoverImg.src = '';
                currentCoverImageUrl.value = '';
                currentCoverPreview.style.display = 'none';
            }
            coverImageInput.value = ''; // Clear file input on modal open

            bookModal.classList.remove('hidden');
        }

        window.closeBookModal = function() {
            bookModal.classList.add('hidden');
            document.getElementById('bookForm').reset(); // Clear form on close
            currentCoverPreview.style.display = 'none'; // Hide preview
        }

        window.confirmDeleteBook = function(id, title) {
            if (confirm(`Are you sure you want to delete the book "${title}"? This will also delete all related borrow records that have been returned/lost. However, if the book is currently borrowed, deletion will be prevented.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'teacher_library.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'book_action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'book_id';
                idInput.value = id;
                form.appendChild(idInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        // --- Update Return Details (Fine Calculation) ---
        window.updateReturnDetails = function() {
            const selectedOption = borrowRecordIdSelect.options[borrowRecordIdSelect.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                fineAmountInput.value = '0.00';
                bookIdReturnInput.value = '';
                return;
            }

            const bookId = selectedOption.dataset.bookId;
            const dueDate = selectedOption.dataset.dueDate;
            const borrowStatus = selectedOption.dataset.status; // Get current borrow status from data-attribute
            const dailyFineRate = <?php echo $daily_fine_rate; ?>;

            bookIdReturnInput.value = bookId; // Set hidden book_id for return

            let calculatedFine = 0.00;
            const today = new Date();
            const due = new Date(dueDate);
            
            // Only calculate fine if the book is actually overdue (today > due date)
            if (today > due) {
                const diffTime = Math.abs(today.getTime() - due.getTime()); // Difference in milliseconds
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); // Convert to days
                calculatedFine = diffDays * dailyFineRate;
            }
            
            fineAmountInput.value = calculatedFine.toFixed(2);
        }
        borrowRecordIdSelect.addEventListener('change', updateReturnDetails); // Add event listener
        updateReturnDetails(); // Call on load to initialize


        // --- Set Max Due Date for Borrowing ---
        const today = new Date();
        const maxDueDate = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days from now
        document.getElementById('due_date_borrow').min = today.toISOString().split('T')[0];
        document.getElementById('due_date_borrow').max = maxDueDate.toISOString().split('T')[0];

    });
</script>