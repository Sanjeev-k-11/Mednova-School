<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./student_header.php";   // Includes student-specific authentication and sidebar

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php"); 
    exit;
}

$student_id = $_SESSION['id'] ?? null; 

if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Authentication Error!</strong>
            <span class='block sm:inline'> Your student ID is missing or invalid in the session. Please log in again.</span>
          </div>";
    require_once "./student_footer.php"; 
    if($link) mysqli_close($link);
    exit();
}

// --- CONSTANTS ---
$daily_fine_rate = 5.00; // Rs 5 per day overdue (consistent with teacher_library)
$default_book_cover = '../assets/images/default-book-cover.png'; // Adjust path

// --- PAGINATION & FILTERING PARAMETERS ---
$search_query = $_GET['search'] ?? '';
$filter_genre = $_GET['genre'] ?? 'all';
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$records_per_page = 6; // Display 6 books per page

$offset = ($current_page - 1) * $records_per_page;

$available_genres = [];
$all_books = [];
$borrowed_books = [];
$borrow_history = [];
$total_books_count = 0; // For pagination

// --- DATA FETCHING ---

// 1. Fetch all unique genres for filtering
$sql_genres = "SELECT DISTINCT genre FROM books WHERE genre IS NOT NULL AND genre != '' ORDER BY genre ASC";
if ($result = mysqli_query($link, $sql_genres)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $available_genres[] = htmlspecialchars($row['genre']);
    }
    mysqli_free_result($result);
} else {
    error_log("DB Error fetching genres: " . mysqli_error($link));
}

// 2. Build WHERE clause for all books (for count and main query)
$where_clause = "WHERE 1=1";
$params_where = [];
$types_where = '';

if (!empty($search_query)) {
    $where_clause .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params_where = array_merge($params_where, [$search_param, $search_param, $search_param]);
    $types_where .= 'sss';
}
if ($filter_genre !== 'all' && in_array($filter_genre, $available_genres)) { // Validate genre against fetched list
    $where_clause .= " AND genre = ?";
    $params_where[] = $filter_genre;
    $types_where .= 's';
}

// 2a. Count total books for pagination
$sql_count_books = "SELECT COUNT(id) AS total FROM books " . $where_clause;
if ($stmt_count = mysqli_prepare($link, $sql_count_books)) {
    if (!empty($params_where)) {
        mysqli_stmt_bind_param($stmt_count, $types_where, ...$params_where);
    }
    mysqli_stmt_execute($stmt_count);
    mysqli_stmt_bind_result($stmt_count, $total_books_count);
    mysqli_stmt_fetch($stmt_count);
    mysqli_stmt_close($stmt_count);
} else {
    error_log("DB Error preparing count books query: " . mysqli_error($link));
}

// 2b. Fetch all books (with search, filter, and pagination)
$sql_all_books = "
    SELECT 
        id, title, author, isbn, publisher, publication_year, genre, 
        total_copies, available_copies, cover_image_url, description
    FROM books
    " . $where_clause . "
    ORDER BY title ASC
    LIMIT ? OFFSET ?;
";
$params_all_books = array_merge($params_where, [$records_per_page, $offset]);
$types_all_books = $types_where . 'ii'; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_all_books)) {
    mysqli_stmt_bind_param($stmt, $types_all_books, ...$params_all_books);
    mysqli_stmt_execute($stmt);
    $all_books = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Error preparing all books query: " . mysqli_error($link));
}


// 3. Fetch currently borrowed books for the student
$sql_borrowed_books = "
    SELECT 
        br.id AS borrow_record_id,
        b.title,
        b.author,
        b.cover_image_url,
        br.borrow_date,
        br.due_date,
        br.status,
        br.fine_amount
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    WHERE br.student_id = ? AND br.status IN ('Borrowed', 'Overdue')
    ORDER BY br.due_date ASC;
";
if ($stmt = mysqli_prepare($link, $sql_borrowed_books)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $borrowed_books = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Error preparing borrowed books query: " . mysqli_error($link));
}

// 4. Fetch borrow history for the student
$sql_borrow_history = "
    SELECT 
        br.id AS borrow_record_id,
        b.title,
        b.author,
        b.cover_image_url,
        br.borrow_date,
        br.return_date,
        br.status,
        br.fine_amount
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    WHERE br.student_id = ? AND br.status IN ('Returned', 'Lost')
    ORDER BY br.borrow_date DESC
    LIMIT 5; -- Show last 5 history items
";
if ($stmt = mysqli_prepare($link, $sql_borrow_history)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $borrow_history = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("DB Error preparing borrow history query: " . mysqli_error($link));
}


mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .dashboard-container {
            min-height: calc(100vh - 80px); /* Adjust based on header/footer height */
        }
        .book-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .status-badge.Borrowed { background-color: #90cdf4; color: #2a4365; } /* Blue */
        .status-badge.Overdue { background-color: #feb2b2; color: #9b2c2c; } /* Red */
        .status-badge.Returned { background-color: #9ae6b4; color: #276749; } /* Green */
        .status-badge.Lost { background-color: #e2e8f0; color: #4a5568; } /* Gray */
        .pagination-link.active {
            @apply bg-indigo-600 text-white;
        }
    </style>
</head>
<body class="bg-gray-100 mt-28 font-sans antialiased">
<!-- student_header.php content usually goes here -->

<div class="dashboard-container p-4 sm:p-6">
    <!-- Main Header Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">School Library</h1>
        <p class="text-gray-600">Explore our collection, view your borrowed books, and track your library history.</p>
    </div>

    <!-- Currently Borrowed Books Section -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-book-reader mr-2 text-indigo-500"></i> Your Borrowed Books
        </h2>
        <?php if (empty($borrowed_books)): ?>
            <div class="text-center p-8 bg-green-50 border border-green-200 rounded-lg text-green-800">
                <i class="fas fa-thumbs-up fa-4x mb-4 text-green-400"></i>
                <p class="text-xl font-semibold mb-2">No books currently borrowed!</p>
                <p class="text-lg">Time to find your next read!</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($borrowed_books as $book): ?>
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 flex book-card">
                        <img src="<?php echo htmlspecialchars($book['cover_image_url'] ?: $default_book_cover); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-24 h-32 object-cover rounded-md mr-4 shadow-md">
                        <div class="flex-grow">
                            <h3 class="text-lg font-bold text-gray-900 mb-1 line-clamp-2"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="text-sm text-gray-600 mb-2">by <?php echo htmlspecialchars($book['author']); ?></p>
                            <div class="text-xs text-gray-700">
                                <p><span class="font-medium">Borrowed:</span> <?php echo date('M d, Y', strtotime($book['borrow_date'])); ?></p>
                                <p><span class="font-medium">Due:</span> <span class="<?php echo ($book['status'] === 'Overdue') ? 'text-red-600 font-bold' : 'text-gray-700'; ?>"><?php echo date('M d, Y', strtotime($book['due_date'])); ?></span></p>
                                <?php 
                                    $current_fine = 0.00;
                                    if ($book['status'] === 'Overdue') {
                                        $today_date = strtotime(date('Y-m-d'));
                                        $due_date_timestamp = strtotime($book['due_date']);
                                        if ($today_date > $due_date_timestamp) {
                                            $days_overdue = floor(($today_date - $due_date_timestamp) / (60 * 60 * 24));
                                            $current_fine = $days_overdue * $daily_fine_rate;
                                        }
                                    }
                                ?>
                                <?php if ($current_fine > 0): ?>
                                    <p class="text-red-700 font-bold"><i class="fas fa-exclamation-triangle mr-1"></i> Fine: ₹<?php echo number_format($current_fine, 2); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="mt-2 px-2 py-0.5 rounded-full text-xs font-semibold status-badge <?php echo htmlspecialchars($book['status']); ?>">
                                <?php echo htmlspecialchars($book['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Browse All Books Section -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6 h-full flex flex-col">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-book-open mr-2 text-indigo-500"></i> Browse All Books
            </h2>
            <form action="library.php" method="GET" class="mb-6 flex flex-col sm:flex-row gap-3">
                <input type="text" name="search" placeholder="Search by title, author, ISBN..."
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       class="flex-grow mt-1 block border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <select name="genre" class="mt-1 block border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm w-full sm:w-auto">
                    <option value="all">All Genres</option>
                    <?php foreach ($available_genres as $genre): ?>
                        <option value="<?php echo htmlspecialchars($genre); ?>" <?php echo ($filter_genre === $genre) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($genre); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md w-full sm:w-auto">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>

            <?php if (empty($all_books) && ($search_query || $filter_genre !== 'all')): ?>
                <div class="text-center p-8 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800 flex-grow flex flex-col items-center justify-center">
                    <i class="fas fa-exclamation-circle fa-4x mb-4 text-yellow-400"></i>
                    <p class="text-xl font-semibold mb-2">No books match your criteria!</p>
                    <p class="text-lg">Try a different search or filter.</p>
                </div>
            <?php elseif (empty($all_books) && !$search_query && $filter_genre === 'all'): ?>
                 <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 flex-grow flex flex-col items-center justify-center">
                    <i class="fas fa-book-open fa-4x mb-4 text-gray-400"></i>
                    <p class="text-xl font-semibold mb-2">No books in the library catalog yet!</p>
                    <p class="text-lg">Check back later for new additions.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($all_books as $book): ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 flex book-card">
                            <img src="<?php echo htmlspecialchars($book['cover_image_url'] ?: $default_book_cover); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-24 h-32 object-cover rounded-md mr-4 shadow-md">
                            <div class="flex-grow">
                                <h3 class="text-lg font-bold text-gray-900 mb-1 line-clamp-2"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="text-sm text-gray-600 mb-2">by <?php echo htmlspecialchars($book['author']); ?></p>
                                <p class="text-xs text-gray-500">ISBN: <?php echo htmlspecialchars($book['isbn'] ?: 'N/A'); ?></p>
                                <p class="text-xs text-gray-500 mb-2">Genre: <?php echo htmlspecialchars($book['genre'] ?: 'N/A'); ?></p>
                                <div class="flex justify-between items-center text-xs mt-2">
                                    <span class="px-2 py-0.5 rounded-full text-indigo-700 bg-indigo-100 font-semibold">Available: <?php echo htmlspecialchars($book['available_copies']); ?>/<?php echo htmlspecialchars($book['total_copies']); ?></span>
                                    <?php if ($book['available_copies'] > 0): ?>
                                        <span class="text-green-600 font-semibold"><i class="fas fa-check-circle mr-1"></i> Available</span>
                                    <?php else: ?>
                                        <span class="text-red-600 font-semibold"><i class="fas fa-times-circle mr-1"></i> Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination Controls -->
                <?php
                $total_pages = ceil($total_books_count / $records_per_page);
                if ($total_pages > 1):
                ?>
                <nav class="mt-8 flex justify-center" aria-label="Pagination">
                    <ul class="flex items-center -space-x-px">
                        <?php if ($current_page > 1): ?>
                            <li>
                                <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_query); ?>&genre=<?php echo urlencode($filter_genre); ?>" class="pagination-link py-2 px-3 ml-0 leading-tight text-gray-500 bg-white rounded-l-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php
                        // Logic to show a reasonable number of page links
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1) {
                            echo '<li><a href="?page=1&search=' . urlencode($search_query) . '&genre=' . urlencode($filter_genre) . '" class="pagination-link py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li><span class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300">...</span></li>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&genre=<?php echo urlencode($filter_genre); ?>" 
                                   class="pagination-link py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 <?php echo ($i === $current_page) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li><span class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300">...</span></li>';
                            }
                            echo '<li><a href="?page=' . $total_pages . '&search=' . urlencode($search_query) . '&genre=' . urlencode($filter_genre) . '" class="pagination-link py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <?php if ($current_page < $total_pages): ?>
                            <li>
                                <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_query); ?>&genre=<?php echo urlencode($filter_genre); ?>" class="pagination-link py-2 px-3 leading-tight text-gray-500 bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="text-center text-sm text-gray-600 mt-4">
                    Showing <?php echo min($total_books_count, $offset + 1); ?> to <?php echo min($total_books_count, $offset + $records_per_page); ?> of <?php echo $total_books_count; ?> records.
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Borrow History Section -->
        <div class="lg:col-span-1 bg-white rounded-xl shadow-lg p-6 h-full flex flex-col">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-history mr-2 text-indigo-500"></i> Borrow History
            </h2>
            <?php if (empty($borrow_history)): ?>
                <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 flex-grow flex flex-col items-center justify-center">
                    <i class="fas fa-book fa-4x mb-4 text-gray-400"></i>
                    <p class="text-xl font-semibold mb-2">No past borrowing records!</p>
                    <p class="text-lg">Your returned or lost books will appear here.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrowed</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Returned</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($borrow_history as $record): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 line-clamp-1"><?php echo htmlspecialchars($record['title']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo date('M d, Y', strtotime($record['borrow_date'])); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo $record['return_date'] ? date('M d, Y', strtotime($record['return_date'])) : '-'; ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-badge <?php echo htmlspecialchars($record['status']); ?>">
                                            <?php echo htmlspecialchars($record['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once "./student_footer.php"; ?>
</body>
</html>