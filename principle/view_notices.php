<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"];
$principal_name = $_SESSION["full_name"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Filter Parameters ---
$filter_class_id = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$filter_teacher_id = isset($_GET['teacher_id']) && is_numeric($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Pagination Configuration ---
$records_per_page = 10; // Number of notices to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;


// --- Fetch Filter Dropdown Data ---
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching classes for filter: " . mysqli_error($link);
    $message_type = "danger";
}

$all_teachers = [];
$sql_all_teachers = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_all_teachers)) {
    $all_teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching teachers for filter: " . mysqli_error($link);
    $message_type = "danger";
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_class_id) {
    $where_clauses[] = "n.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if ($filter_teacher_id) {
    $where_clauses[] = "n.teacher_id = ?";
    $params[] = $filter_teacher_id;
    $types .= "i";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(n.title LIKE ? OR n.content LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(n.id)
                      FROM notices n
                      JOIN classes c ON n.class_id = c.id
                      LEFT JOIN teachers t ON n.teacher_id = t.id
                      WHERE " . $where_sql;

if ($stmt = mysqli_prepare($link, $total_records_sql)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_records = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
} else {
    $message = "Error counting notices: " . mysqli_error($link);
    $message_type = "danger";
}
$total_pages = ceil($total_records / $records_per_page);

// Ensure current_page is within bounds
if ($current_page < 1) {
    $current_page = 1;
} elseif ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
} elseif ($total_records == 0) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;


// --- Fetch Notices Data (with filters and pagination) ---
$notices = [];
$sql_fetch_notices = "SELECT
                            n.id, n.title, n.content, n.created_at, n.posted_by_name,
                            c.class_name, c.section_name,
                            t.full_name AS teacher_full_name -- Just to show who is associated with the notice
                        FROM notices n
                        JOIN classes c ON n.class_id = c.id
                        LEFT JOIN teachers t ON n.teacher_id = t.id
                        WHERE " . $where_sql . "
                        ORDER BY n.created_at DESC
                        LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_notices)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notices = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching notices: " . mysqli_error($link);
    $message_type = "danger";
}

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
    <title>View Notices - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #F0F8FF, #ADD8E6, #B0E0E6, #E0FFFF); /* Pale, light blues/cyans */
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
            max-width: 1200px;
            margin: 20px auto;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.95); /* Slightly transparent white */
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        h2 {
            color: #4682B4; /* SteelBlue */
            margin-bottom: 30px;
            border-bottom: 2px solid #B0E0E6;
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

        /* Filter Section */
        .filter-section {
            background-color: #f0f8ff; /* AliceBlue background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #b0e0e6;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        .filter-group.wide { /* For search input */
            flex: 2;
            min-width: 250px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4682B4;
        }
        .filter-group select,
        .filter-group input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #a0c4ff;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #fff;
            color: #333;
        }
        .filter-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%234682B4%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%234682B4%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
            margin-top: 10px;
        }
        .btn-filter, .btn-clear-filter, .btn-print {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-filter {
            background-color: #6495ED; /* CornflowerBlue */
            color: #fff;
            border: 1px solid #6495ED;
        }
        .btn-filter:hover {
            background-color: #4682B4;
        }
        .btn-clear-filter {
            background-color: #808080; /* Gray */
            color: #fff;
            border: 1px solid #808080;
        }
        .btn-clear-filter:hover {
            background-color: #696969;
        }
        .btn-print {
            background-color: #20b2aa; /* Light Sea Green */
            color: #fff;
            border: 1px solid #20b2aa;
        }
        .btn-print:hover {
            background-color: #1a968a;
        }


        /* Notices Table Display */
        .notices-section-container {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        h3 {
            color: #4682B4;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notices-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border: 1px solid #b0e0e6;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .notices-table th, .notices-table td {
            border-bottom: 1px solid #e0e0e0;
            padding: 15px;
            text-align: left;
            vertical-align: middle;
        }
        .notices-table th {
            background-color: #e0f8f8; /* Light Cyan */
            color: #4682B4;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .notices-table tr:nth-child(even) { background-color: #f8ffff; }
        .notices-table tr:hover { background-color: #eafaff; }
        .text-center {
            text-align: center;
        }
        .text-muted {
            color: #6c757d;
        }
        .no-results {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: #6c757d;
        }

        /* Pagination Styles (reused) */
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
        .pagination-info {
            color: #555;
            font-size: 0.95em;
            font-weight: 500;
        }
        .pagination-controls {
            display: flex;
            gap: 5px;
        }
        .pagination-controls a,
        .pagination-controls span {
            display: block;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #6495ED;
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .pagination-controls a:hover {
            background-color: #e9ecef;
            border-color: #B0E0E6;
        }
        .pagination-controls .current-page,
        .pagination-controls .current-page:hover {
            background-color: #6495ED;
            color: #fff;
            border-color: #6495ED;
            cursor: default;
        }
        .pagination-controls .disabled,
        .pagination-controls .disabled:hover {
            color: #6c757d;
            background-color: #e9ecef;
            border-color: #dee2e6;
            cursor: not-allowed;
        }

        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; font-size: 10pt; padding: 10mm; }
            .printable-area h2, .printable-area h3 { color: #000; border-bottom: 1px solid #ccc; font-size: 16pt; margin-bottom: 15px; }
            .printable-area .notices-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .printable-area .notices-table th, .printable-area .notices-table td { border: 1px solid #eee; padding: 8px 10px; }
            .printable-area .notices-table th { background-color: #e0f8f8; color: #000; }
            .printable-area .no-results, .pagination-container, .filter-section, .btn-print { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-group.wide { min-width: unset; }
            .filter-buttons { flex-direction: column; width: 100%; }
            .btn-filter, .btn-clear-filter, .btn-print { width: 100%; justify-content: center; }
            .notices-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="main-conten mt-28">
    <div class="container">
        <h2><i class="fas fa-bell"></i> View Notices</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter and Search Form -->
        <div class="filter-section">
            <form action="view_notices.php" method="GET" style="display:contents;">
                <div class="filter-group">
                    <label for="filter_class_id"><i class="fas fa-school"></i> Class:</label>
                    <select id="filter_class_id" name="class_id">
                        <option value="">-- All Classes --</option>
                        <?php foreach ($all_classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                <?php echo ($filter_class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_teacher_id"><i class="fas fa-chalkboard-teacher"></i> Posted By Teacher:</label>
                    <select id="filter_teacher_id" name="teacher_id">
                        <option value="">-- All Teachers --</option>
                        <?php foreach ($all_teachers as $teacher): ?>
                            <option value="<?php echo htmlspecialchars($teacher['id']); ?>"
                                <?php echo ($filter_teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group wide">
                    <label for="search_query"><i class="fas fa-search"></i> Search Notices:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Title or Content">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_class_id || $filter_teacher_id || !empty($search_query)): ?>
                        <a href="view_notices.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printNotices()"><i class="fas fa-print"></i> Print Notices</button>
                </div>
            </form>
        </div>

        <!-- Notices Overview Section -->
        <div class="notices-section-container printable-area">
            <h3><i class="fas fa-list-alt"></i> All Notices</h3>
            <?php if (empty($notices)): ?>
                <p class="no-results">No notices found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x:auto;" id="notices-table-wrapper">
                    <table class="notices-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Content</th>
                                <th>For Class</th>
                                <th>Posted By</th>
                                <th>Posted On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notices as $notice): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($notice['title']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($notice['content'], 0, 200)); ?>
                                        <?php if (strlen($notice['content']) > 200): ?>
                                            ... <a href="#" onclick="alert('Full Content: <?php echo htmlspecialchars($notice['content']); ?>'); return false;" class="text-muted">more</a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($notice['class_name'] . ' - ' . $notice['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($notice['posted_by_name'] ?: ($notice['teacher_full_name'] ?: 'N/A')); ?></td>
                                    <td><?php echo date("M j, Y", strtotime($notice['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_records > 0): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> notices
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $base_url_params = array_filter([
                                'class_id' => $filter_class_id,
                                'teacher_id' => $filter_teacher_id,
                                'search' => $search_query
                            ]);
                            $base_url = "view_notices.php?" . http_build_query($base_url_params);
                            ?>

                            <?php if ($current_page > 1): ?>
                                <a href="<?php echo $base_url . '&page=' . ($current_page - 1); ?>">Previous</a>
                            <?php else: ?>
                                <span class="disabled">Previous</span>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<a href="' . $base_url . '&page=1">1</a>';
                                if ($start_page > 2) {
                                    echo '<span>...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++):
                                if ($i == $current_page): ?>
                                    <span class="current-page"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo $base_url . '&page=' . $i; ?>"><?php echo $i; ?></a>
                                <?php endif;
                            endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span>...</span>';
                                }
                                echo '<a href="' . $base_url . '&page=' . $total_pages . '">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?php echo $base_url . '&page=' . ($current_page + 1); ?>">Next</a>
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

<script>
    // --- Print Functionality ---
    function printNotices() {
        const printableContent = document.querySelector('.notices-section-container').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=1000');

        printWindow.document.write('<html><head><title>School Notices Report</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
            h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 25px; text-align: center; }
            h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
            .notices-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .notices-table th, .notices-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
            .notices-table th { background-color: #e0f8f8; color: #000; font-weight: 700; text-transform: uppercase; }
            .notices-table tr:nth-child(even) { background-color: #f8ffff; }
            .pagination-container, .filter-section, .btn-print, .action-buttons-group { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(`<h2 style="text-align: center;">School Notices Report</h2>`);
        printWindow.document.write(printableContent);
        printWindow.document.write('</body></html>');
        
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    };
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>