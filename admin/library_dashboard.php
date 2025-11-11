<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./admin_header.php";     // Includes admin-specific authentication and sidebar

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php"); 
    exit;
}

$admin_id = $_SESSION['id'] ?? null; 
$flash_message = '';
$flash_message_type = ''; // 'success', 'error', 'info'

// --- CONSTANTS ---
$daily_fine_rate = 5.00; // Rs 5 per day overdue

// --- PAGINATION SETTINGS ---
$books_per_page = 10;
$borrow_records_per_page = 10;
$overdue_records_per_page = 10;
$financial_records_per_page = 10;

// Current page for each section
$current_page_books = filter_input(INPUT_GET, 'page_books', FILTER_VALIDATE_INT) ?: 1;
$current_page_borrow = filter_input(INPUT_GET, 'page_borrow', FILTER_VALIDATE_INT) ?: 1;
$current_page_overdue = filter_input(INPUT_GET, 'page_overdue', FILTER_VALIDATE_INT) ?: 1;
$current_page_financial = filter_input(INPUT_GET, 'page_financial', FILTER_VALIDATE_INT) ?: 1;

// Offset for each section
$offset_books = ($current_page_books - 1) * $books_per_page;
$offset_borrow = ($current_page_borrow - 1) * $borrow_records_per_page;
$offset_overdue = ($current_page_overdue - 1) * $overdue_records_per_page;
$offset_financial = ($current_page_financial - 1) * $financial_records_per_page;


// --- DATA FETCHING ---

// Dashboard Overview
$dashboard_stats = [
    'total_books' => 0,
    'available_books' => 0,
    'total_borrowed' => 0,
    'overdue_books' => 0,
    'total_fines_charged' => 0.00,
    'library_income' => 0.00,
    'library_expenses' => 0.00,
    'library_net_balance' => 0.00
];

$sql_stats = "
    SELECT
        (SELECT COUNT(id) FROM books) AS total_books,
        (SELECT SUM(available_copies) FROM books) AS available_books,
        (SELECT COUNT(id) FROM borrow_records WHERE status = 'Borrowed') AS total_borrowed,
        (SELECT COUNT(id) FROM borrow_records WHERE status = 'Overdue') AS overdue_books,
        (SELECT SUM(fine_amount) FROM borrow_records) AS total_fines_charged, -- Sum of all recorded fines
        (SELECT SUM(amount) FROM income WHERE category = 'Library Fine' OR source = 'Library Fine') AS library_income,
        (SELECT SUM(amount) FROM expenses WHERE category = 'Library') AS library_expenses
";
if ($result = mysqli_query($link, $sql_stats)) {
    $dashboard_stats_raw = mysqli_fetch_assoc($result);
    $dashboard_stats['total_books'] = $dashboard_stats_raw['total_books'] ?? 0;
    $dashboard_stats['available_books'] = $dashboard_stats_raw['available_books'] ?? 0;
    $dashboard_stats['total_borrowed'] = $dashboard_stats_raw['total_borrowed'] ?? 0;
    $dashboard_stats['overdue_books'] = $dashboard_stats_raw['overdue_books'] ?? 0;
    $dashboard_stats['total_fines_charged'] = number_format($dashboard_stats_raw['total_fines_charged'] ?? 0.00, 2);
    $dashboard_stats['library_income'] = number_format($dashboard_stats_raw['library_income'] ?? 0.00, 2);
    $dashboard_stats['library_expenses'] = number_format($dashboard_stats_raw['library_expenses'] ?? 0.00, 2);
    $dashboard_stats['library_net_balance'] = number_format(($dashboard_stats_raw['library_income'] ?? 0.00) - ($dashboard_stats_raw['library_expenses'] ?? 0.00), 2);
    mysqli_free_result($result);
} else {
    error_log("DB Error fetching dashboard stats: " . mysqli_error($link));
}

// All Overdue Books (with pagination)
$all_overdue_books = [];
$total_overdue_count = 0;

$sql_count_overdue = "SELECT COUNT(br.id) FROM borrow_records br WHERE br.status = 'Overdue'";
if ($result = mysqli_query($link, $sql_count_overdue)) {
    $total_overdue_count = mysqli_fetch_row($result)[0];
    mysqli_free_result($result);
}

$sql_all_overdue_books = "
    SELECT 
        br.id AS borrow_record_id,
        b.title,
        b.author,
        CONCAT(s.first_name, ' ', s.last_name) AS student_name, 
        s.email AS student_email,
        s.phone_number AS student_phone,
        br.borrow_date,
        br.due_date,
        DATEDIFF(CURDATE(), br.due_date) AS days_overdue,
        br.fine_amount
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN students s ON br.student_id = s.id
    WHERE br.status = 'Overdue'
    ORDER BY br.due_date ASC
    LIMIT ? OFFSET ?;
";
if ($stmt = mysqli_prepare($link, $sql_all_overdue_books)) {
    mysqli_stmt_bind_param($stmt, "ii", $overdue_records_per_page, $offset_overdue);
    mysqli_stmt_execute($stmt);
    $all_overdue_books = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Error preparing all overdue books query: " . mysqli_error($link));
}


// All Borrow Records (history with pagination)
$all_borrow_records = [];
$total_borrow_records_count = 0;

$sql_count_borrow_records = "SELECT COUNT(id) FROM borrow_records";
if ($result = mysqli_query($link, $sql_count_borrow_records)) {
    $total_borrow_records_count = mysqli_fetch_row($result)[0];
    mysqli_free_result($result);
}

$sql_all_borrow_records = "
    SELECT 
        br.id AS borrow_record_id,
        b.title,
        b.author,
        CONCAT(s.first_name, ' ', s.last_name) AS student_name,
        br.borrow_date,
        br.due_date,
        br.return_date,
        br.fine_amount,
        br.status,
        t.full_name AS recorded_by_teacher
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    JOIN students s ON br.student_id = s.id
    LEFT JOIN teachers t ON br.recorded_by_teacher_id = t.id -- Assuming recorded by teacher
    ORDER BY br.borrow_date DESC
    LIMIT ? OFFSET ?;
";
if ($stmt = mysqli_prepare($link, $sql_all_borrow_records)) {
    mysqli_stmt_bind_param($stmt, "ii", $borrow_records_per_page, $offset_borrow);
    mysqli_stmt_execute($stmt);
    $all_borrow_records = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Error preparing all borrow records query: " . mysqli_error($link));
}


// All Books (manage list for reference with pagination)
$all_books_manage = [];
$total_books_manage_count = 0;

$sql_count_books_manage = "SELECT COUNT(id) FROM books";
if ($result = mysqli_query($link, $sql_count_books_manage)) {
    $total_books_manage_count = mysqli_fetch_row($result)[0];
    mysqli_free_result($result);
}

$sql_all_books_manage = "
    SELECT 
        id, title, author, isbn, genre, 
        total_copies, available_copies
    FROM books 
    ORDER BY title ASC
    LIMIT ? OFFSET ?;
";
if ($stmt = mysqli_prepare($link, $sql_all_books_manage)) {
    mysqli_stmt_bind_param($stmt, "ii", $books_per_page, $offset_books);
    mysqli_stmt_execute($stmt);
    $all_books_manage = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Error preparing all books manage query: " . mysqli_error($link));
}


// Library Financial Overview (income/expenses with pagination)
$library_financial_records = [];
$total_financial_count = 0;

$sql_count_financial = "SELECT COUNT(id) FROM (
    SELECT id FROM income WHERE category = 'Library Fine' OR source = 'Library Fine'
    UNION ALL
    SELECT id FROM expenses WHERE category = 'Library'
) AS library_finance";
if ($result = mysqli_query($link, $sql_count_financial)) {
    $total_financial_count = mysqli_fetch_row($result)[0];
    mysqli_free_result($result);
}

$sql_library_financial = "
    (SELECT 
        'Income' AS type,
        'Library Fine' AS category_name,
        source AS description,
        amount,
        income_date AS date
    FROM income
    WHERE category = 'Library Fine' OR source = 'Library Fine')
    UNION ALL
    (SELECT 
        'Expense' AS type,
        category AS category_name,
        description,
        amount,
        expense_date AS date
    FROM expenses
    WHERE category = 'Library')
    ORDER BY date DESC
    LIMIT ? OFFSET ?;
";
if ($stmt = mysqli_prepare($link, $sql_library_financial)) {
    mysqli_stmt_bind_param($stmt, "ii", $financial_records_per_page, $offset_financial);
    mysqli_stmt_execute($stmt);
    $library_financial_records = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Error preparing library financial query: " . mysqli_error($link));
}

mysqli_close($link);

// --- Helper function for pagination HTML ---
function render_pagination($total_records, $records_per_page, $current_page, $page_param_name) {
    $total_pages = ceil($total_records / $records_per_page);
    if ($total_pages <= 1) return; // No pagination needed

    $query_params = $_GET; // Get current GET parameters
    unset($query_params[$page_param_name]); // Remove current page param to build new links

    echo '<nav class="mt-8 flex justify-center" aria-label="Pagination">';
    echo '<ul class="flex items-center -space-x-px">';

    // Previous button
    if ($current_page > 1) {
        $query_params[$page_param_name] = $current_page - 1;
        echo '<li><a href="?' . http_build_query($query_params) . '" class="pagination-link py-2 px-3 ml-0 leading-tight text-gray-500 bg-white rounded-l-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700">Previous</a></li>';
    }

    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);

    if ($start_page > 1) {
        $query_params[$page_param_name] = 1;
        echo '<li><a href="?' . http_build_query($query_params) . '" class="pagination-link py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">1</a></li>';
        if ($start_page > 2) {
            echo '<li><span class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300">...</span></li>';
        }
    }

    for ($i = $start_page; $i <= $end_page; $i++) {
        $query_params[$page_param_name] = $i;
        $active_class = ($i === $current_page) ? 'active' : '';
        echo '<li><a href="?' . http_build_query($query_params) . '" class="pagination-link py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 ' . $active_class . '">' . $i . '</a></li>';
    }

    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            echo '<li><span class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300">...</span></li>';
        }
        $query_params[$page_param_name] = $total_pages;
        echo '<li><a href="?' . http_build_query($query_params) . '" class="pagination-link py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">' . $total_pages . '</a></li>';
    }

    // Next button
    if ($current_page < $total_pages) {
        $query_params[$page_param_name] = $current_page + 1;
        echo '<li><a href="?' . http_build_query($query_params) . '" class="pagination-link py-2 px-3 leading-tight text-gray-500 bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700">Next</a></li>';
    }
    echo '</ul></nav>';
    echo '<div class="text-center text-sm text-gray-600 mt-4">Showing ' . min($total_records, $offset + 1) . ' to ' . min($total_records, $offset + $records_per_page) . ' of ' . $total_records . ' records.</div>';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Library Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .dashboard-container { min-height: calc(100vh - 80px); }
        .stat-card {
            @apply bg-white rounded-xl shadow-lg p-5 text-center flex flex-col items-center justify-center transform transition duration-300 hover:scale-105;
        }
        .stat-card i { @apply text-4xl mb-3; }
        .stat-card p.label { @apply text-sm text-gray-600; }
        .stat-card p.value { @apply text-3xl font-bold text-gray-900; }

        .pagination-link.active {
            @apply bg-indigo-600 text-white;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
<!-- admin_header.php content usually goes here -->

<div class="dashboard-container p-4 sm:p-6">
    <!-- Main Header Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Admin Library Dashboard</h1>
        <p class="text-gray-600">Comprehensive overview and management for the school library.</p>
    </div>

    <!-- Dashboard Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-6">
        <div class="stat-card bg-blue-50 border-b-4 border-blue-500">
            <i class="fas fa-book text-blue-500"></i>
            <p class="label">Total Books</p>
            <p class="value"><?php echo htmlspecialchars($dashboard_stats['total_books']); ?></p>
        </div>
        <div class="stat-card bg-green-50 border-b-4 border-green-500">
            <i class="fas fa-check-circle text-green-500"></i>
            <p class="label">Available Copies</p>
            <p class="value"><?php echo htmlspecialchars($dashboard_stats['available_books']); ?></p>
        </div>
        <div class="stat-card bg-indigo-50 border-b-4 border-indigo-500">
            <i class="fas fa-handshake text-indigo-500"></i>
            <p class="label">Books Borrowed</p>
            <p class="value"><?php echo htmlspecialchars($dashboard_stats['total_borrowed']); ?></p>
        </div>
        <div class="stat-card bg-red-50 border-b-4 border-red-500">
            <i class="fas fa-hourglass-half text-red-500"></i>
            <p class="label">Overdue Books</p>
            <p class="value"><?php echo htmlspecialchars($dashboard_stats['overdue_books']); ?></p>
        </div>
        <div class="stat-card bg-yellow-50 border-b-4 border-yellow-500">
            <i class="fas fa-coins text-yellow-500"></i>
            <p class="label">Total Fines Charged</p>
            <p class="value">₹<?php echo htmlspecialchars($dashboard_stats['total_fines_charged']); ?></p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="stat-card bg-purple-50 border-b-4 border-purple-500">
            <i class="fas fa-hand-holding-usd text-purple-500"></i>
            <p class="label">Library Income</p>
            <p class="value">₹<?php echo htmlspecialchars($dashboard_stats['library_income']); ?></p>
        </div>
        <div class="stat-card bg-pink-50 border-b-4 border-pink-500">
            <i class="fas fa-shopping-cart text-pink-500"></i>
            <p class="label">Library Expenses</p>
            <p class="value">₹<?php echo htmlspecialchars($dashboard_stats['library_expenses']); ?></p>
        </div>
        <div class="stat-card lg:col-span-2 bg-teal-50 border-b-4 border-teal-500">
            <i class="fas fa-balance-scale text-teal-500"></i>
            <p class="label">Library Net Balance</p>
            <p class="value">₹<?php echo htmlspecialchars($dashboard_stats['library_net_balance']); ?></p>
        </div>
    </div>

    <!-- All Overdue Books Section -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-clock mr-2 text-red-500"></i> All Overdue Books
        </h2>
        <?php if (empty($all_overdue_books)): ?>
            <div class="text-center p-8 bg-green-50 border border-green-200 rounded-lg text-green-800">
                <i class="fas fa-check-double fa-4x mb-4 text-green-400"></i>
                <p class="text-xl font-semibold mb-2">No books are currently overdue!</p>
                <p class="text-lg">All students are returning books on time.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book Title</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Contact</th>
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
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">
                                    <?php if ($book['student_email']): ?><a href="mailto:<?php echo htmlspecialchars($book['student_email']); ?>" class="text-indigo-600 hover:underline"><i class="fas fa-envelope"></i></a><?php endif; ?>
                                    <?php if ($book['student_phone']): ?><a href="tel:<?php echo htmlspecialchars($book['student_phone']); ?>" class="text-indigo-600 hover:underline ml-2"><i class="fas fa-phone"></i></a><?php endif; ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo date('M d, Y', strtotime($book['borrow_date'])); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-red-600"><?php echo date('M d, Y', strtotime($book['due_date'])); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-center text-sm font-bold text-red-600"><?php echo htmlspecialchars($book['days_overdue']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-red-600">₹<?php echo number_format($book['days_overdue'] * $daily_fine_rate, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($total_overdue_count, $overdue_records_per_page, $current_page_overdue, 'page_overdue'); ?>
        <?php endif; ?>
    </div>

    <!-- All Borrow Records Section -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-indigo-500"></i> All Borrow History
        </h2>
        <?php if (empty($all_borrow_records)): ?>
            <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
                <i class="fas fa-clipboard-list fa-4x mb-4 text-gray-400"></i>
                <p class="text-xl font-semibold mb-2">No borrowing records found yet!</p>
                <p class="text-lg">Library transactions will appear here.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book Title</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrowed On</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Returned On</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fine</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($all_borrow_records as $record): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 line-clamp-1"><?php echo htmlspecialchars($record['title']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($record['student_name']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo date('M d, Y', strtotime($record['borrow_date'])); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo date('M d, Y', strtotime($record['due_date'])); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo $record['return_date'] ? date('M d, Y', strtotime($record['return_date'])) : '-'; ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">₹<?php echo number_format($record['fine_amount'], 2); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-badge <?php echo htmlspecialchars($record['status']); ?>">
                                        <?php echo htmlspecialchars($record['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($record['recorded_by_teacher'] ?: 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($total_borrow_records_count, $borrow_records_per_page, $current_page_borrow, 'page_borrow'); ?>
        <?php endif; ?>
    </div>

    <!-- All Books List (Reference) -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-book mr-2 text-indigo-500"></i> Library Book Catalog
        </h2>
        <?php if (empty($all_books_manage)): ?>
            <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
                <i class="fas fa-book-open fa-4x mb-4 text-gray-400"></i>
                <p class="text-xl font-semibold mb-2">No books in the library catalog!</p>
                <p class="text-lg">Add books to the library to see them here.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ISBN</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Genre</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Copies</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Available Copies</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($all_books_manage as $book): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 line-clamp-1"><?php echo htmlspecialchars($book['title']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($book['author']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($book['isbn'] ?: 'N/A'); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($book['genre'] ?: 'N/A'); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-center text-sm text-gray-600"><?php echo htmlspecialchars($book['total_copies']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-center text-sm <?php echo ($book['available_copies'] > 0) ? 'text-green-600' : 'text-red-600'; ?> font-bold"><?php echo htmlspecialchars($book['available_copies']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($total_books_manage_count, $books_per_page, $current_page_books, 'page_books'); ?>
        <?php endif; ?>
    </div>

    <!-- Library Financial Overview Section -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-dollar-sign mr-2 text-green-500"></i> Library Financials (Fines & Expenses)
        </h2>
        <?php if (empty($library_financial_records)): ?>
            <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
                <i class="fas fa-coins fa-4x mb-4 text-gray-400"></i>
                <p class="text-xl font-semibold mb-2">No financial records for the library yet!</p>
                <p class="text-lg">Income from fines or library expenses will appear here.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($library_financial_records as $record): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium <?php echo ($record['type'] === 'Income') ? 'text-green-600' : 'text-red-600'; ?>"><?php echo htmlspecialchars($record['type']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($record['category_name']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 line-clamp-1"><?php echo htmlspecialchars($record['description'] ?: '-'); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-bold <?php echo ($record['type'] === 'Income') ? 'text-green-600' : 'text-red-600'; ?>">₹<?php echo number_format($record['amount'], 2); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($total_financial_count, $financial_records_per_page, $current_page_financial, 'page_financial'); ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once "./admin_footer.php"; ?>
</body>
</html>