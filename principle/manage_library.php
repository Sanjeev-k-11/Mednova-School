<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"]; // Primary key ID of the logged-in principal (for audit logs)
$principal_name = $_SESSION["full_name"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Configuration ---
const FINE_PER_DAY = 5.00; // Example: 5 units of currency per day overdue

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Process Borrow Record Actions (Return/Lost) ---
if (isset($_POST['borrow_action']) && ($_POST['borrow_action'] == 'return_book' || $_POST['borrow_action'] == 'mark_lost')) {
    $record_id = (int)$_POST['record_id'];
    $action = $_POST['borrow_action'];
    $reason = trim($_POST['reason'] ?? ''); // Reason is optional for return, required for lost

    if (empty($record_id)) {
        set_session_message("Invalid record ID for action.", "danger");
        header("location: manage_library.php");
        exit;
    }

    // Fetch existing record details to get book_id and due_date
    $fetch_record_sql = "SELECT br.book_id, br.due_date, b.title, b.available_copies FROM borrow_records br JOIN books b ON br.book_id = b.id WHERE br.id = ?";
    $fetch_record_stmt = mysqli_prepare($link, $fetch_record_sql);
    mysqli_stmt_bind_param($fetch_record_stmt, "i", $record_id);
    mysqli_stmt_execute($fetch_record_stmt);
    $result = mysqli_stmt_get_result($fetch_record_stmt);
    $record_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($fetch_record_stmt);

    if (!$record_data) {
        set_session_message("Borrow record not found.", "danger");
        header("location: manage_library.php");
        exit;
    }

    $book_id = $record_data['book_id'];
    $due_date = strtotime($record_data['due_date']);
    $return_lost_date = time(); // Current timestamp for return/lost date
    $fine_amount = 0.00;
    $status_update = '';
    $available_copies_change = 0; // -1 for lost, +1 for returned

    if ($action == 'return_book') {
        $status_update = 'Returned';
        $available_copies_change = 1;
        
        if ($return_lost_date > $due_date) {
            $diff_days = floor(abs($return_lost_date - $due_date) / (60 * 60 * 24)); // Use floor for full days overdue
            $fine_amount = $diff_days * FINE_PER_DAY;
        }
        set_session_message("Book '" . htmlspecialchars($record_data['title']) . "' returned successfully. Fine: ₹" . number_format($fine_amount, 2), "success");
    } elseif ($action == 'mark_lost') {
        if (empty($reason)) {
            set_session_message("Reason is required when marking a book as lost.", "danger");
            header("location: manage_library.php");
            exit;
        }
        $status_update = 'Lost';
        $available_copies_change = -1; // Reduce available copies for a lost book
        // Define fine for a lost book (e.g., fixed replacement cost or higher)
        $fine_amount = 500.00; // Example: Fixed fine for a lost book
        set_session_message("Book '" . htmlspecialchars($record_data['title']) . "' marked as lost. Fine: ₹" . number_format($fine_amount, 2), "success");
    }

    // Update borrow_records
    $sql_update_borrow = "UPDATE borrow_records SET status = ?, return_date = FROM_UNIXTIME(?), fine_amount = ?, notes = ?, recorded_by_admin_id = ? WHERE id = ?";
    if ($stmt_borrow = mysqli_prepare($link, $sql_update_borrow)) {
        mysqli_stmt_bind_param($stmt_borrow, "sdsii", $status_update, $return_lost_date, $fine_amount, $reason, $principal_id, $record_id);
        mysqli_stmt_execute($stmt_borrow);
        mysqli_stmt_close($stmt_borrow);
    }

    // Update books available_copies
    $sql_update_books = "UPDATE books SET available_copies = available_copies + ? WHERE id = ?";
    if ($stmt_books = mysqli_prepare($link, $sql_update_books)) {
        mysqli_stmt_bind_param($stmt_books, "ii", $available_copies_change, $book_id);
        mysqli_stmt_execute($stmt_books);
        mysqli_stmt_close($stmt_books);
    }
    
    header("location: manage_library.php");
    exit;
}

// --- Process Borrow Record Deletion ---
if (isset($_GET['delete_record_id'])) {
    $record_id = (int)$_GET['delete_record_id'];
    if (empty($record_id)) {
        set_session_message("Invalid Record ID for deletion.", "danger");
        header("location: manage_library.php");
        exit;
    }

    // Fetch book_id and status to potentially restore available_copies
    $fetch_record_sql = "SELECT book_id, status FROM borrow_records WHERE id = ?";
    $fetch_record_stmt = mysqli_prepare($link, $fetch_record_sql);
    mysqli_stmt_bind_param($fetch_record_stmt, "i", $record_id);
    mysqli_stmt_execute($fetch_record_stmt);
    $result = mysqli_stmt_get_result($fetch_record_stmt);
    $record_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($fetch_record_stmt);

    if ($record_data && ($record_data['status'] == 'Borrowed' || $record_data['status'] == 'Lost')) {
        // If a currently borrowed or lost book record is deleted, increment available_copies
        $sql_update_books = "UPDATE books SET available_copies = available_copies + 1 WHERE id = ?";
        $stmt_books = mysqli_prepare($link, $sql_update_books);
        mysqli_stmt_bind_param($stmt_books, "i", $record_data['book_id']);
        mysqli_stmt_execute($stmt_books);
        mysqli_stmt_close($stmt_books);
    }

    $sql = "DELETE FROM borrow_records WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $record_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Borrow record deleted successfully.", "success");
            } else {
                set_session_message("Borrow record not found or already deleted.", "danger");
            }
        } else {
            set_session_message("Error deleting borrow record: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_library.php");
    exit;
}


// --- Library Statistics ---
$library_stats = [];
$library_stats['total_books'] = 0;
$library_stats['available_books'] = 0;
$library_stats['total_borrowed'] = 0; // Currently borrowed or overdue
$library_stats['overdue_books'] = 0;
$library_stats['lost_books'] = 0;
$library_stats['total_fines_charged'] = 0.00;
$library_stats['library_income'] = 0.00;
$library_stats['library_expenses'] = 0.00;

// Fetch book counts
$sql_book_stats = "SELECT
                    COUNT(id) AS total_books_count,
                    SUM(available_copies) AS available_copies_count
                    FROM books";
if ($result = mysqli_query($link, $sql_book_stats)) {
    $data = mysqli_fetch_assoc($result);
    $library_stats['total_books'] = $data['total_books_count'] ?? 0;
    $library_stats['available_books'] = $data['available_copies_count'] ?? 0;
    mysqli_free_result($result);
}

// Fetch borrowing stats
$sql_borrow_stats = "SELECT
                        COUNT(CASE WHEN status IN ('Borrowed', 'Overdue') THEN 1 END) AS total_borrowed_count,
                        COUNT(CASE WHEN status = 'Overdue' THEN 1 END) AS overdue_books_count,
                        COUNT(CASE WHEN status = 'Lost' THEN 1 END) AS lost_books_count,
                        SUM(fine_amount) AS total_fines_sum
                     FROM borrow_records";
if ($result = mysqli_query($link, $sql_borrow_stats)) {
    $data = mysqli_fetch_assoc($result);
    $library_stats['total_borrowed'] = $data['total_borrowed_count'] ?? 0;
    $library_stats['overdue_books'] = $data['overdue_books_count'] ?? 0;
    $library_stats['lost_books'] = $data['lost_books_count'] ?? 0;
    $library_stats['total_fines_charged'] = $data['total_fines_sum'] ?? 0.00;
    mysqli_free_result($result);
}

// Fetch financial stats (adjust categories/sources as per your 'income' and 'expenses' tables)
$sql_financial_stats = "SELECT
                            (SELECT SUM(amount) FROM income WHERE source = 'Library Fine' OR category = 'Library') AS library_income_sum,
                            (SELECT SUM(amount) FROM expenses WHERE category = 'Library' OR category = 'Book Purchase') AS library_expenses_sum";
if ($result = mysqli_query($link, $sql_financial_stats)) {
    $data = mysqli_fetch_assoc($result);
    $library_stats['library_income'] = $data['library_income_sum'] ?? 0.00;
    $library_stats['library_expenses'] = $data['library_expenses_sum'] ?? 0.00;
    mysqli_free_result($result);
}


// --- Books Pagination & Filters ---
$books_records_per_page = 10;
$books_current_page = isset($_GET['books_page']) && is_numeric($_GET['books_page']) ? (int)$_GET['books_page'] : 1;
$books_search_query = isset($_GET['books_search']) ? trim($_GET['books_search']) : '';

$books_where_clauses = ["1=1"];
$books_params = [];
$books_types = "";

if (!empty($books_search_query)) {
    $books_search_term = "%" . $books_search_query . "%";
    $books_where_clauses[] = "(title LIKE ? OR author LIKE ? OR isbn LIKE ? OR genre LIKE ?)";
    $books_params[] = $books_search_term;
    $books_params[] = $books_search_term;
    $books_params[] = $books_search_term;
    $books_params[] = $books_search_term;
    $books_types .= "ssss";
}
$books_where_sql = implode(" AND ", $books_where_clauses);

$total_books = 0;
$total_books_sql = "SELECT COUNT(id) FROM books WHERE " . $books_where_sql;
if ($stmt = mysqli_prepare($link, $total_books_sql)) {
    if (!empty($books_params)) {
        mysqli_stmt_bind_param($stmt, $books_types, ...$books_params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_books = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
}
$total_books_pages = ceil($total_books / $books_records_per_page);
if ($books_current_page < 1) $books_current_page = 1;
elseif ($books_current_page > $total_books_pages && $total_books_pages > 0) $books_current_page = $total_books_pages;
elseif ($total_books == 0) $books_current_page = 1;
$books_offset = ($books_current_page - 1) * $books_records_per_page;

$books = [];
// Changed ORDER BY to include genre
$sql_fetch_books = "SELECT * FROM books WHERE " . $books_where_sql . " ORDER BY genre ASC, title ASC LIMIT ? OFFSET ?";
if ($stmt = mysqli_prepare($link, $sql_fetch_books)) {
    $books_params_pagination = $books_params;
    $books_params_pagination[] = $books_records_per_page;
    $books_params_pagination[] = $books_offset;
    $books_types_pagination = $books_types . "ii";
    mysqli_stmt_bind_param($stmt, $books_types_pagination, ...$books_params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $books = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// --- Group books data for display (Genre -> List of Books) ---
$grouped_books = [];
foreach ($books as $book) {
    $genre_key = $book['genre'] ?: 'Uncategorized'; // Default genre for books without one
    if (!isset($grouped_books[$genre_key])) {
        $grouped_books[$genre_key] = [
            'genre_info' => ['name' => $genre_key],
            'list' => []
        ];
    }
    $grouped_books[$genre_key]['list'][] = $book;
}


// --- Borrow Records Pagination & Filters ---
$borrow_records_per_page = 10;
$borrow_current_page = isset($_GET['borrow_page']) && is_numeric($_GET['borrow_page']) ? (int)$_GET['borrow_page'] : 1;
$borrow_filter_student_id = isset($_GET['borrow_student_id']) && is_numeric($_GET['borrow_student_id']) ? (int)$_GET['borrow_student_id'] : null;
$borrow_filter_book_id = isset($_GET['borrow_book_id']) && is_numeric($_GET['borrow_book_id']) ? (int)$_GET['borrow_book_id'] : null;
$borrow_filter_status = isset($_GET['borrow_status']) ? trim($_GET['borrow_status']) : null;
$borrow_search_query = isset($_GET['borrow_search']) ? trim($_GET['borrow_search']) : '';


$borrow_where_clauses = ["1=1"];
$borrow_params = [];
$borrow_types = "";

if ($borrow_filter_student_id) {
    $borrow_where_clauses[] = "br.student_id = ?";
    $borrow_params[] = $borrow_filter_student_id;
    $borrow_types .= "i";
}
if ($borrow_filter_book_id) {
    $borrow_where_clauses[] = "br.book_id = ?";
    $borrow_params[] = $borrow_filter_book_id;
    $borrow_types .= "i";
}
if ($borrow_filter_status) {
    $borrow_where_clauses[] = "br.status = ?";
    $borrow_params[] = $borrow_filter_status;
    $borrow_types .= "s";
}
if (!empty($borrow_search_query)) {
    $borrow_search_term = "%" . $borrow_search_query . "%";
    $borrow_where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR b.title LIKE ? OR b.isbn LIKE ?)";
    $borrow_params[] = $borrow_search_term;
    $borrow_params[] = $borrow_search_term;
    $borrow_params[] = $borrow_search_term;
    $borrow_params[] = $borrow_search_term;
    $borrow_types .= "ssss";
}
$borrow_where_sql = implode(" AND ", $borrow_where_clauses);

$total_borrow_records = 0;
$total_borrow_records_sql = "SELECT COUNT(br.id)
                             FROM borrow_records br
                             JOIN students s ON br.student_id = s.id
                             JOIN books b ON br.book_id = b.id
                             WHERE " . $borrow_where_sql;
if ($stmt = mysqli_prepare($link, $total_borrow_records_sql)) {
    if (!empty($borrow_params)) {
        mysqli_stmt_bind_param($stmt, $borrow_types, ...$borrow_params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_borrow_records = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
}
$total_borrow_pages = ceil($total_borrow_records / $borrow_records_per_page);
if ($borrow_current_page < 1) $borrow_current_page = 1;
elseif ($borrow_current_page > $total_borrow_pages && $total_borrow_pages > 0) $borrow_current_page = $total_borrow_pages;
elseif ($total_borrow_records == 0) $borrow_current_page = 1;
$borrow_offset = ($borrow_current_page - 1) * $borrow_records_per_page;

$borrow_records = [];
$sql_fetch_borrow_records = "SELECT
                                br.id, br.borrow_date, br.due_date, br.return_date, br.fine_amount, br.status, br.notes,
                                s.id AS student_id, s.first_name, s.last_name, s.registration_number,
                                b.id AS book_id, b.title AS book_title, b.isbn,
                                t_rec.full_name AS recorded_by_teacher_name,
                                adm_rec.full_name AS recorded_by_admin_name
                             FROM borrow_records br
                             JOIN students s ON br.student_id = s.id
                             JOIN books b ON br.book_id = b.id
                             LEFT JOIN teachers t_rec ON br.recorded_by_teacher_id = t_rec.id
                             LEFT JOIN admins adm_rec ON br.recorded_by_admin_id = adm_rec.id
                             WHERE " . $borrow_where_sql . "
                             ORDER BY br.borrow_date DESC
                             LIMIT ? OFFSET ?";
if ($stmt = mysqli_prepare($link, $sql_fetch_borrow_records)) {
    $borrow_params_pagination = $borrow_params;
    $borrow_params_pagination[] = $borrow_records_per_page;
    $borrow_params_pagination[] = $borrow_offset;
    $borrow_types_pagination = $borrow_types . "ii";
    mysqli_stmt_bind_param($stmt, $borrow_types_pagination, ...$borrow_params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $borrow_records = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


// --- Fetch all Students for Filter Dropdown (Borrowing Records) ---
$all_students_for_borrow_filter = [];
$sql_all_students_for_borrow = "SELECT id, first_name, last_name FROM students ORDER BY first_name ASC, last_name ASC";
if ($result = mysqli_query($link, $sql_all_students_for_borrow)) {
    $all_students_for_borrow_filter = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// --- Fetch all Books for Filter Dropdown (Borrowing Records) ---
$all_books_for_borrow_filter = [];
$sql_all_books_for_borrow = "SELECT id, title, isbn FROM books ORDER BY title ASC";
if ($result = mysqli_query($link, $sql_all_books_for_borrow)) {
    $all_books_for_borrow_filter = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// All possible borrow statuses
$borrow_statuses = ['Borrowed', 'Returned', 'Overdue', 'Lost'];

mysqli_close($link);

// --- Retrieve and clear session messages ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- PAGE INCLUDES ---
require_once './principal_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Library - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #A8C0FF, #8E9EAB, #6A96C2, #4776B4); /* Subtle Blue/Grey */
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            color: #333;
        }
        @keyframes gradientAnimation {
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.95); /* Slightly transparent white */
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        h2 {
            color: #2c3e50; /* Dark blue-grey */
            margin-bottom: 30px;
            border-bottom: 2px solid #a8c0ff;
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 2.2em;
            font-weight: 700;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background-color: #fefefe;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .stat-card i {
            font-size: 2.5em;
            margin-bottom: 10px;
            opacity: 0.8;
        }
        .stat-card p {
            font-size: 2.2em;
            font-weight: 700;
            margin: 0;
            line-height: 1;
        }
        .stat-card span {
            font-size: 0.9em;
            font-weight: 500;
            opacity: 0.9;
        }

        /* Stat Card Colors */
        .stat-card.bg-blue { background: linear-gradient(45deg, #42a5f5, #2196f3); }
        .stat-card.bg-green { background: linear-gradient(45deg, #66bb6a, #43a047); }
        .stat-card.bg-orange { background: linear-gradient(45deg, #ffa726, #fb8c00); }
        .stat-card.bg-red { background: linear-gradient(45deg, #ef5350, #e53935); }
        .stat-card.bg-darkred { background: linear-gradient(45deg, #d32f2f, #b71c1c); }
        .stat-card.bg-purple { background: linear-gradient(45deg, #ab47bc, #8e24aa); }
        .stat-card.bg-teal { background: linear-gradient(45deg, #26a69a, #00897b); }
        .stat-card.bg-brown { background: linear-gradient(45deg, #8d6e63, #6d4c41); }


        /* Collapsible Section Styles */
        .section-box {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #3f51b5; /* Indigo Blue */
            color: #fff;
            padding: 15px 20px;
            margin: -30px -30px 20px -30px; /* Adjust margin to fill parent box padding */
            border-bottom: 1px solid #2c3e50;
            border-radius: 10px 10px 0 0;
            font-size: 1.6em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .section-header:hover {
            background-color: #303f9f;
        }
        .section-header h3 {
            margin: 0;
            font-size: 1em; /* Inherit font size */
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-toggle-btn {
            background: none;
            border: none;
            font-size: 1em;
            color: #fff;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .section-toggle-btn.rotated {
            transform: rotate(90deg);
        }
        .section-content {
            max-height: 2000px; /* Arbitrary large value */
            overflow: hidden;
            transition: max-height 0.5s ease-in-out;
            /* No padding here, padding is in .section-box */
        }
        .section-content.collapsed {
            max-height: 0;
            margin-top: 0; /* Remove potential margin from collapsed content */
            padding-bottom: 0; /* Clear padding if any inside */
            margin-bottom: 0;
        }


        /* Forms (Filters) */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%233f51b5%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%233f51b5%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
        }
        .form-actions {
            margin-top: 25px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn-form-submit, .btn-form-cancel, .btn-filter, .btn-clear-filter, .btn-print {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }
        .btn-form-submit { background-color: #3f51b5; color: #fff; }
        .btn-form-submit:hover { background-color: #303f9f; }
        .btn-form-cancel { background-color: #6c757d; color: #fff; }
        .btn-form-cancel:hover { background-color: #5a6268; }

        .filter-section {
            background-color: #e0f2f7; /* Light cyan background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #b2ebf2;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group.wide { flex: 2; min-width: 250px; }
        .filter-group label { color: #004085; }
        .filter-buttons { margin-top: 0; }
        .btn-filter { background-color: #007bff; color: #fff; }
        .btn-filter:hover { background-color: #0056b3; }
        .btn-clear-filter { background-color: #6c757d; color: #fff; }
        .btn-clear-filter:hover { background-color: #5a6268; }
        .btn-print { background-color: #28a745; color: #fff; }
        .btn-print:hover { background-color: #218838; }


        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .data-table th, .data-table td {
            border-bottom: 1px solid #e0e0e0;
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }
        .data-table th {
            background-color: #e0f2f7;
            color: #004085;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .data-table tr:nth-child(even) { background-color: #f8fcff; }
        .data-table tr:hover { background-color: #eef7fc; }

        .action-buttons-group {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap; /* Allow buttons to wrap */
        }
        .btn-action {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            border: 1px solid transparent;
        }
        /* No btn-edit for books here */
        .btn-delete { background-color: #dc3545; color: #fff; border-color: #dc3545; }
        .btn-delete:hover { background-color: #c82333; border-color: #bd2130; }
        .btn-return { background-color: #28a745; color: #fff; border-color: #28a745; }
        .btn-return:hover { background-color: #218838; border-color: #1e7e34; }
        .btn-lost { background-color: #ffc107; color: #333; border-color: #ffc107; } /* Use yellow for lost */
        .btn-lost:hover { background-color: #e0a800; border-color: #e0a800; }


        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-Borrowed { background-color: #cce5ff; color: #004085; }
        .status-Returned { background-color: #d4edda; color: #155724; }
        .status-Overdue { background-color: #fff3cd; color: #856404; }
        .status-Lost { background-color: #f8d7da; color: #721c24; }
        .status-Available { background-color: #e2f0d9; color: #388e3c; } /* Green shade for available */
        .status-OutOfStock { background-color: #fcdbd8; color: #d32f2f; } /* Red shade for out of stock */

        .text-center { text-align: center; }
        .text-muted { color: #6c757d; }
        .no-results { text-align: center; padding: 50px; font-size: 1.2em; color: #6c757d; }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding: 10px 0;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pagination-info { color: #555; font-size: 0.95em; font-weight: 500; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-controls a, .pagination-controls span {
            display: block; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;
            text-decoration: none; color: #3f51b5; background-color: #fff; transition: all 0.2s ease;
        }
        .pagination-controls a:hover { background-color: #e9ecef; border-color: #a8c0ff; }
        .pagination-controls .current-page, .pagination-controls .current-page:hover {
            background-color: #3f51b5; color: #fff; border-color: #3f51b5; cursor: default;
        }
        .pagination-controls .disabled, .pagination-controls .disabled:hover {
            color: #6c757d; background-color: #e9ecef; border-color: #dee2e6; cursor: not-allowed;
        }

        /* Modal Styles */
        .modal {
            display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.4); justify-content: center; align-items: center;
        }
        .modal-content {
            background-color: #fefefe; margin: auto; padding: 30px; border: 1px solid #888;
            width: 80%; max-width: 500px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative;
        }
        .modal-header { padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h4 { margin: 0; color: #333; font-size: 1.5em; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.3s ease; }
        .close-btn:hover, .close-btn:focus { color: #000; text-decoration: none; }
        .modal-body textarea { min-height: 100px; resize: vertical; width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; box-sizing: border-box; }
        .modal-footer { margin-top: 20px; text-align: right; }
        .btn-modal-submit { background-color: #3f51b5; color: white; }
        .btn-modal-submit:hover { background-color: #303f9f; }
        .btn-modal-cancel { background-color: #6c757d; color: white; }
        .btn-modal-cancel:hover { background-color: #5a6268; }

        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; font-size: 10pt; padding: 10mm; }
            .printable-area h2, .printable-area h3 { color: #000; border-bottom: 1px solid #ccc; font-size: 16pt; margin-bottom: 15px; }
            .printable-area .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px;}
            .printable-area .stat-card { background: #f0f0f0; color: #333; padding: 10px; border: 1px solid #eee; box-shadow: none; }
            .printable-area .stat-card i { font-size: 1.5em; }
            .printable-area .stat-card p { font-size: 1.5em; }
            .printable-area .stat-card span { font-size: 0.8em; }

            .printable-area .data-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .printable-area .data-table th, .printable-area .data-table td { border: 1px solid #eee; padding: 8px 10px; }
            .printable-area .data-table th { background-color: #e0f2f7; color: #000; }
            .printable-area .status-badge { padding: 3px 6px; font-size: 0.7em; }
            .printable-area .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group { display: none; }
            .printable-area .section-box .section-header { background-color: #f0f0f0; color: #333; border-bottom: 1px solid #ddd; }
            .printable-area .section-box .section-content { max-height: none !important; overflow: visible !important; transition: none !important; padding: 0 !important;}
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .section-box .section-header { margin: -15px -15px 15px -15px; padding: 12px 15px; font-size: 1.4em; }
            .form-grid, .filter-section { grid-template-columns: 1fr; }
            .filter-group.wide { min-width: unset; }
            .filter-buttons { flex-direction: column; width: 100%; }
            .btn-filter, .btn-clear-filter, .btn-print { width: 100%; justify-content: center; }
            .data-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-book-reader"></i> Manage Library</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Library Statistics Section -->
        <div class="stats-grid printable-area">
            <div class="stat-card bg-blue">
                <i class="fas fa-book"></i>
                <p><?php echo $library_stats['total_books']; ?></p>
                <span>Total Books</span>
            </div>
            <div class="stat-card bg-green">
                <i class="fas fa-check"></i>
                <p><?php echo $library_stats['available_books']; ?></p>
                <span>Available Books</span>
            </div>
            <div class="stat-card bg-orange">
                <i class="fas fa-hand-sparkles"></i>
                <p><?php echo $library_stats['total_borrowed']; ?></p>
                <span>Currently Borrowed</span>
            </div>
            <div class="stat-card bg-red">
                <i class="fas fa-hourglass-end"></i>
                <p><?php echo $library_stats['overdue_books']; ?></p>
                <span>Overdue Books</span>
            </div>
            <div class="stat-card bg-darkred">
                <i class="fas fa-times-circle"></i>
                <p><?php echo $library_stats['lost_books']; ?></p>
                <span>Lost Books</span>
            </div>
            <div class="stat-card bg-purple">
                <i class="fas fa-coins"></i>
                <p>₹<?php echo number_format($library_stats['total_fines_charged'], 2); ?></p>
                <span>Total Fines Charged</span>
            </div>
            <div class="stat-card bg-teal">
                <i class="fas fa-money-bill-wave"></i>
                <p>₹<?php echo number_format($library_stats['library_income'], 2); ?></p>
                <span>Library Income</span>
            </div>
            <div class="stat-card bg-brown">
                <i class="fas fa-money-bill-alt"></i>
                <p>₹<?php echo number_format($library_stats['library_expenses'], 2); ?></p>
                <span>Library Expenses</span>
            </div>
        </div>

        <!-- Books Overview Section -->
        <div class="section-box printable-area" id="books-overview-section">
            <div class="section-header" onclick="toggleSection('books-overview-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="true" aria-controls="books-overview-content">
                <h3><i class="fas fa-book"></i> Books by Genre</h3>
                 <button class="section-toggle-btn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="books-overview-content" class="section-content mb-12">
                <div class="filter-section">
                    <form action="manage_library.php" method="GET" style="display:contents;">
                        <input type="hidden" name="borrow_page" value="<?php echo htmlspecialchars($borrow_current_page); ?>">
                        <div class="filter-group wide">
                            <label for="books_search_query"><i class="fas fa-search"></i> Search Books:</label>
                            <input class="h-12" type="text" id="books_search_query" name="books_search" value="<?php echo htmlspecialchars($books_search_query); ?>" placeholder="Title, Author, ISBN, Genre">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                            <?php if (!empty($books_search_query)): ?>
                                <a href="manage_library.php?borrow_page=<?php echo htmlspecialchars($borrow_current_page); ?>" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                            <button type="button" class="btn-print" onclick="printTable('books-table-wrapper', 'Books Inventory Report')"><i class="fas fa-print"></i> Print Books</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($grouped_books)): ?>
                    <p class="no-results">No books found matching your criteria.</p>
                <?php else: ?>
                    <div id="books-table-wrapper"> <!-- Wrapper for printable area -->
                        <?php $genre_index = 0; ?>
                        <?php foreach ($grouped_books as $genre_key => $genre_data): ?>
                            <?php $genre_index++; ?>
                            <div class="genre-group-wrapper mb-12"> <!-- Corrected: New wrapper for each genre's content -->
                                <div class="section-header" onclick="toggleSection('genre-content-<?php echo $genre_index; ?>', this.querySelector('.section-toggle-btn'))"
                                    aria-expanded="true" aria-controls="genre-content-<?php echo $genre_index; ?>">
                                    <h3><i class="fas fa-tags"></i> Genre: <?php echo htmlspecialchars($genre_data['genre_info']['name']); ?></h3>
                                    <button class="section-toggle-btn">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                                <div id="genre-content-<?php echo $genre_index; ?>" class="section-content">
                                    <div style="overflow-x:auto;">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Author</th>
                                                    <th>ISBN</th>
                                                    <th>Total Copies</th>
                                                    <th>Available Copies</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($genre_data['list'] as $book): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                                                        <td><?php echo htmlspecialchars($book['isbn'] ?: 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($book['total_copies']); ?></td>
                                                        <td><?php echo htmlspecialchars($book['available_copies']); ?></td>
                                                        <td>
                                                            <span class="status-badge <?php echo ($book['available_copies'] > 0 ? 'status-Available' : 'status-OutOfStock'); ?>">
                                                                <?php echo ($book['available_copies'] > 0 ? 'Available' : 'Out of Stock'); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($total_books > 0): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo ($books_offset + 1); ?> to <?php echo min($books_offset + $books_records_per_page, $total_books); ?> of <?php echo $total_books; ?> books
                            </div>
                            <div class="pagination-controls">
                                <?php
                                $books_base_url_params = array_filter([
                                    'books_search' => $books_search_query,
                                    'borrow_page' => $borrow_current_page // Keep borrow page in URL
                                ]);
                                $books_base_url = "manage_library.php?" . http_build_query($books_base_url_params);
                                ?>

                                <?php if ($books_current_page > 1): ?>
                                    <a href="<?php echo $books_base_url . '&books_page=' . ($books_current_page - 1); ?>">Previous</a>
                                <?php else: ?>
                                    <span class="disabled">Previous</span>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $books_current_page - 2);
                                $end_page = min($total_books_pages, $books_current_page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="' . $books_base_url . '&books_page=1">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span>...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++):
                                    if ($i == $books_current_page): ?>
                                        <span class="current-page"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo $books_base_url . '&books_page=' . $i; ?>"><?php echo $i; ?></a>
                                    <?php endif;
                                endfor;

                                if ($end_page < $total_books_pages) {
                                    if ($end_page < $total_books_pages - 1) {
                                        echo '<span>...</span>';
                                    }
                                    echo '<a href="' . $books_base_url . '&books_page=' . $total_books_pages . '">' . $total_books_pages . '</a>';
                                }
                                ?>

                                <?php if ($books_current_page < $total_books_pages): ?>
                                    <a href="<?php echo $books_base_url . '&books_page=' . ($books_current_page + 1); ?>">Next</a>
                                <?php else: ?>
                                    <span class="disabled">Next</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Borrowing Records Section -->
        <div class="section-box printable-area" id="borrow-records-section">
            <div class="section-header" onclick="toggleSection('borrow-records-content', this.querySelector('.section-toggle-btn'))"
                 aria-expanded="true" aria-controls="borrow-records-content">
                <h3><i class="fas fa-history"></i> Borrowing Records</h3>
                <button class="section-toggle-btn">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="borrow-records-content" class="section-content">
                <div class="filter-section">
                    <form action="manage_library.php" method="GET" style="display:contents;">
                        <input type="hidden" name="books_page" value="<?php echo htmlspecialchars($books_current_page); ?>">
                        <input type="hidden" name="books_search" value="<?php echo htmlspecialchars($books_search_query); ?>">
                        <div class="filter-group">
                            <label for="borrow_filter_student_id"><i class="fas fa-user-graduate"></i> Student:</label>
                            <select id="borrow_filter_student_id" name="borrow_student_id">
                                <option value="">-- All Students --</option>
                                <?php foreach ($all_students_for_borrow_filter as $student): ?>
                                    <option value="<?php echo htmlspecialchars($student['id']); ?>"
                                        <?php echo ($borrow_filter_student_id == $student['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="borrow_filter_book_id"><i class="fas fa-book"></i> Book:</label>
                            <select id="borrow_filter_book_id" name="borrow_book_id">
                                <option value="">-- All Books --</option>
                                <?php foreach ($all_books_for_borrow_filter as $book): ?>
                                    <option value="<?php echo htmlspecialchars($book['id']); ?>"
                                        <?php echo ($borrow_filter_book_id == $book['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($book['title'] . ' (' . ($book['isbn'] ?: 'N/A') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="borrow_filter_status"><i class="fas fa-info-circle"></i> Status:</label>
                            <select id="borrow_filter_status" name="borrow_status">
                                <option value="">-- All Statuses --</option>
                                <?php foreach ($borrow_statuses as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>"
                                        <?php echo ($borrow_filter_status == $status) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group wide">
                            <label for="borrow_search_query"><i class="fas fa-search"></i> Search Records:</label>
                            <input type="text" id="borrow_search_query" name="borrow_search" value="<?php echo htmlspecialchars($borrow_search_query); ?>" placeholder="Student name, Book title/ISBN">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                            <?php if ($borrow_filter_student_id || $borrow_filter_book_id || $borrow_filter_status || !empty($borrow_search_query)): ?>
                                <a href="manage_library.php?books_page=<?php echo htmlspecialchars($books_current_page); ?>&books_search=<?php echo htmlspecialchars($books_search_query); ?>" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                            <button type="button" class="btn-print" onclick="printTable('borrow-records-table-wrapper', 'Library Borrowing Records Report')"><i class="fas fa-print"></i> Print Records</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($borrow_records)): ?>
                    <p class="no-results">No borrowing records found matching your criteria.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;" id="borrow-records-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Book</th>
                                    <th>ISBN</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Fine</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrow_records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name'] . ' (' . $record['registration_number'] . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($record['book_title']); ?></td>
                                        <td><?php echo htmlspecialchars($record['isbn'] ?: 'N/A'); ?></td>
                                        <td><?php echo date("M j, Y", strtotime($record['borrow_date'])); ?></td>
                                        <td><?php echo date("M j, Y", strtotime($record['due_date'])); ?></td>
                                        <td><?php echo ($record['return_date'] && $record['return_date'] !== '0000-00-00') ? date("M j, Y", strtotime($record['return_date'])) : 'N/A'; ?></td>
                                        <td><?php echo ($record['fine_amount'] > 0) ? '₹' . number_format($record['fine_amount'], 2) : 'No Fine'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo str_replace(' ', '', $record['status']); ?>">
                                                <?php echo htmlspecialchars($record['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['notes'] ?: 'N/A'); ?></td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <?php if ($record['status'] == 'Borrowed' || $record['status'] == 'Overdue'): ?>
                                                    <button class="btn-action btn-return" onclick="showBorrowActionModal(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['book_title']); ?>', 'return')">
                                                        <i class="fas fa-undo"></i> Return
                                                    </button>
                                                    <button class="btn-action btn-lost" onclick="showBorrowActionModal(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['book_title']); ?>', 'lost')">
                                                        <i class="fas fa-exclamation-triangle"></i> Lost
                                                    </button>
                                                <?php endif; ?>
                                                <a href="javascript:void(0);" onclick="confirmDeleteBorrowRecord(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['book_title']); ?>', '<?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>')" class="btn-action btn-delete">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_borrow_records > 0): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo ($borrow_offset + 1); ?> to <?php echo min($borrow_offset + $borrow_records_per_page, $total_borrow_records); ?> of <?php echo $total_borrow_records; ?> records
                            </div>
                            <div class="pagination-controls">
                                <?php
                                $borrow_base_url_params = array_filter([
                                    'books_page' => $books_current_page, // Keep books page in URL
                                    'books_search' => $books_search_query,
                                    'borrow_student_id' => $borrow_filter_student_id,
                                    'borrow_book_id' => $borrow_filter_book_id,
                                    'borrow_status' => $borrow_filter_status,
                                    'borrow_search' => $borrow_search_query
                                ]);
                                $borrow_base_url = "manage_library.php?" . http_build_query($borrow_base_url_params);
                                ?>

                                <?php if ($borrow_current_page > 1): ?>
                                    <a href="<?php echo $borrow_base_url . '&borrow_page=' . ($borrow_current_page - 1); ?>">Previous</a>
                                <?php else: ?>
                                    <span class="disabled">Previous</span>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $borrow_current_page - 2);
                                $end_page = min($total_borrow_pages, $borrow_current_page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="' . $borrow_base_url . '&borrow_page=1">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span>...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++):
                                    if ($i == $borrow_current_page): ?>
                                        <span class="current-page"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo $borrow_base_url . '&borrow_page=' . $i; ?>"><?php echo $i; ?></a>
                                    <?php endif;
                                endfor;

                                if ($end_page < $total_borrow_pages) {
                                    if ($end_page < $total_borrow_pages - 1) {
                                        echo '<span>...</span>';
                                    }
                                    echo '<a href="' . $borrow_base_url . '&borrow_page=' . $total_borrow_pages . '">' . $total_borrow_pages . '</a>';
                                }
                                ?>

                                <?php if ($borrow_current_page < $total_borrow_pages): ?>
                                    <a href="<?php echo $borrow_base_url . '&borrow_page=' . ($borrow_current_page + 1); ?>">Next</a>
                                <?php else: ?>
                                    <span class="disabled">Next</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Borrow Action Reason Modal -->
<div id="borrowActionModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="borrow-modal-title"></h4>
      <span class="close-btn" onclick="closeBorrowActionModal()">&times;</span>
    </div>
    <form id="borrowActionForm" action="manage_library.php" method="POST">
      <input type="hidden" name="borrow_action" id="borrow-modal-action">
      <input type="hidden" name="record_id" id="borrow-modal-record-id">
      <div class="modal-body">
        <div class="form-group">
          <label for="borrow-reason-text">Reason (Optional for return, Required for lost):</label>
          <textarea id="borrow-reason-text" name="reason" rows="4" placeholder="Enter any notes or reasons for this action"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-form-cancel" onclick="closeBorrowActionModal()">Cancel</button>
        <button type="submit" class="btn-form-submit" id="borrow-modal-submit-btn">Submit</button>
      </div>
    </form>
  </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize collapsed state for sections
        document.querySelectorAll('.section-box .section-header').forEach(header => {
            const contentId = header.querySelector('.section-toggle-btn').getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            const button = header.querySelector('.section-toggle-btn');
            
            // All sections start expanded by default (aria-expanded="true")
            if (button.getAttribute('aria-expanded') === 'false') {
                content.classList.add('collapsed');
                button.querySelector('.fas').classList.remove('fa-chevron-down');
                button.querySelector('.fas').classList.add('fa-chevron-right');
            } else {
                // Ensure content is expanded initially, remove inline style after transition is set
                // This prevents the content from "flickering" closed then open if it relies on CSS max-height
                content.style.maxHeight = content.scrollHeight + 'px';
                setTimeout(() => content.style.maxHeight = null, 500); // Allow CSS to take over for transitions
            }
        });
    });

    // Function to toggle the collapse state of a section
    window.toggleSection = function(contentId, button) {
        const content = document.getElementById(contentId);
        const icon = button.querySelector('.fas');

        if (content.classList.contains('collapsed')) {
            // Expand the section
            content.classList.remove('collapsed');
            content.style.maxHeight = content.scrollHeight + 'px'; // Set to scrollHeight for animation
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
            button.setAttribute('aria-expanded', 'true');
            // After animation, remove max-height to allow content to grow dynamically
            setTimeout(() => { content.style.maxHeight = null; }, 500); // Match transition time
        } else {
            // Collapse the section
            content.style.maxHeight = content.scrollHeight + 'px'; // Set current height before collapsing
            // Trigger reflow for CSS transition to work correctly when setting max-height to 0
            void content.offsetHeight; 
            content.classList.add('collapsed');
            content.style.maxHeight = '0'; // Collapse
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
            button.setAttribute('aria-expanded', 'false');
        }
    };

    // --- Borrowing Records JS (Modal and Delete Confirmation) ---
    const borrowActionModal = document.getElementById('borrowActionModal');
    const borrowModalTitle = document.getElementById('borrow-modal-title');
    const borrowModalAction = document.getElementById('borrow-modal-action');
    const borrowModalRecordId = document.getElementById('borrow-modal-record-id');
    const borrowReasonText = document.getElementById('borrow-reason-text');
    const borrowModalSubmitBtn = document.getElementById('borrow-modal-submit-btn');

    function showBorrowActionModal(recordId, bookTitle, action) {
        borrowReasonText.value = ''; // Clear previous reason
        borrowModalRecordId.value = recordId;

        if (action === 'return') {
            borrowModalTitle.innerHTML = `<i class="fas fa-undo"></i> Return Book: ${bookTitle}`;
            borrowModalAction.value = 'return_book';
            borrowReasonText.placeholder = 'Optional notes on return condition, etc.';
            borrowModalSubmitBtn.innerHTML = 'Confirm Return';
            borrowReasonText.removeAttribute('required'); // Reason is optional for return
        } else if (action === 'lost') {
            borrowModalTitle.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Mark Book Lost: ${bookTitle}`;
            borrowModalAction.value = 'mark_lost';
            borrowReasonText.placeholder = 'Required: Reason for marking as lost (e.g., Damaged, Not returned).';
            borrowModalSubmitBtn.innerHTML = 'Confirm Lost';
            borrowReasonText.setAttribute('required', 'required'); // Reason is required for lost
        }
        borrowActionModal.style.display = 'flex'; // Show modal
    }

    function closeBorrowActionModal() {
        borrowActionModal.style.display = 'none'; // Hide modal
        borrowReasonText.removeAttribute('required'); // Clean up required attribute
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == borrowActionModal) {
            closeBorrowActionModal();
        }
    }

    function confirmDeleteBorrowRecord(id, bookTitle, studentName) {
        if (confirm(`Are you sure you want to delete the borrowing record for "${bookTitle}" by "${studentName}"? This action cannot be undone.`)) {
            window.location.href = `manage_library.php?delete_record_id=${id}`;
        }
    }

    // --- Print Functionality (Universal for sections) ---
    function printTable(tableWrapperId, title) {
        const printTitle = title;
        const tableWrapper = document.getElementById(tableWrapperId);
        if (!tableWrapper) {
            alert('Printable section not found!');
            return;
        }

        // Find the parent section-content and its header
        const sectionContent = tableWrapper.closest('.section-content');
        const sectionHeader = sectionContent ? sectionContent.previousElementSibling : null;
        let isSectionCollapsed = false;

        if (sectionContent && sectionContent.classList.contains('collapsed')) {
            isSectionCollapsed = true;
            // Temporarily expand the section for printing
            sectionContent.classList.remove('collapsed');
            sectionContent.style.maxHeight = sectionContent.scrollHeight + 'px'; // Ensure content is expanded
            if (sectionHeader) {
                const icon = sectionHeader.querySelector('.fas');
                if (icon) {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-down');
                }
                sectionHeader.setAttribute('aria-expanded', 'true');
            }
        }
        
        // Give a brief moment for DOM to render expanded state if it was collapsed
        setTimeout(() => {
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write('<html><head><title>Print Report</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
            printWindow.document.write('<style>');
            // Inject most relevant CSS styles for printing
            printWindow.document.write(`
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
                h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 25px; text-align: center; }
                .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 9pt; }
                .data-table th, .data-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
                .data-table th { background-color: #e0f2f7; color: #000; font-weight: 700; text-transform: uppercase; }
                .data-table tr:nth-child(even) { background-color: #f8fcff; }
                .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
                .status-Borrowed { background-color: #cce5ff; color: #004085; }
                .status-Returned { background-color: #d4edda; color: #155724; }
                .status-Overdue { background-color: #fff3cd; color: #856404; }
                .status-Lost { background-color: #f8d7da; color: #721c24; }
                .status-Available { background-color: #e2f0d9; color: #388e3c; }
                .status-OutOfStock { background-color: #fcdbd8; color: #d32f2f; }
                .pagination-container, .filter-section, .btn-print, .action-buttons-group { display: none; }
                .fas { margin-right: 3px; }

                /* Specific to Books Overview print for genre grouping */
                .genre-group-wrapper { border: 1px solid #ccc; margin-bottom: 15px; page-break-inside: avoid; }
                .genre-group-wrapper .section-header { background-color: #f0f0f0; color: #333; border-bottom: 1px solid #ddd; }
                .genre-group-wrapper .section-header h3 { font-size: 14pt; }
                .genre-group-wrapper .section-toggle-btn { display: none; }
                .genre-group-wrapper .section-content { max-height: none !important; overflow: visible !important; padding: 10px 0 !important; }
            `);
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(`<h2 style="text-align: center;">${printTitle}</h2>`); // Main title for the printout
            printWindow.document.write(tableWrapper.innerHTML); // Only the table content
            printWindow.document.write('</body></html>');
            
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            // printWindow.close(); // Optionally close after printing

            // Restore original collapsed state after printing
            if (isSectionCollapsed) {
                // Collapse the section again, potentially with a slight delay
                setTimeout(() => {
                    sectionContent.classList.add('collapsed');
                    sectionContent.style.maxHeight = '0';
                    if (sectionHeader) {
                        const icon = sectionHeader.querySelector('.fas');
                        if (icon) {
                            icon.classList.remove('fa-chevron-down');
                            icon.classList.add('fa-chevron-right');
                        }
                        sectionHeader.setAttribute('aria-expanded', 'false');
                    }
                }, 100); // Small delay to ensure print dialog appears before re-collapsing
            }
        }, 100); // Small delay for expanding before printing
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>