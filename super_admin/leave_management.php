<?php
// admin_leave_management.php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["super_admin_id"]; // The ID of the currently logged-in admin

// --- FILTER PARAMETERS ---
$filter_status = $_GET['status'] ?? 'All'; // Default filter status
$filter_class_id = $_GET['class_id'] ?? '';
$search_query = trim($_GET['search'] ?? '');
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// --- STATS LOGIC (Always calculate for ALL applications, unfiltered) ---
$stats = ['All' => 0, 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
$sql_all_stats = "SELECT la.status, COUNT(la.id) as count FROM leave_applications la JOIN students s ON la.student_id = s.id GROUP BY la.status";

if($stmt_all_stats = mysqli_prepare($link, $sql_all_stats)){
    mysqli_stmt_execute($stmt_all_stats);
    $result_all_stats = mysqli_stmt_get_result($stmt_all_stats);
    while($row = mysqli_fetch_assoc($result_all_stats)){
        if(isset($stats[$row['status']])) {
            $stats[$row['status']] = $row['count'];
        }
        $stats['All'] += $row['count']; // Sum up for 'All'
    }
    mysqli_stmt_close($stmt_all_stats);
}

// --- PAGINATION SETUP ---
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;
$total_records = 0;

// --- DYNAMIC QUERY BUILDING FOR MAIN TABLE (WITH FILTERS) ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

// Filter by Status
if (!empty($filter_status) && $filter_status !== 'All') {
    $where_clauses[] = "la.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

// Filter by Class
if (!empty($filter_class_id) && is_numeric($filter_class_id)) {
    $where_clauses[] = "la.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}

// Filter by Student Name/Roll Number
if (!empty($search_query)) {
    $where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.roll_number LIKE ?)";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $types .= "sss";
}

// Filter by Date Range
if (!empty($filter_date_from)) {
    $where_clauses[] = "la.leave_from >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}
if (!empty($filter_date_to)) {
    $where_clauses[] = "la.leave_to <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// 1. Get total records for the current filter
$sql_count = "SELECT COUNT(la.id) FROM leave_applications la JOIN students s ON la.student_id = s.id $where_sql";
if($stmt_count = mysqli_prepare($link, $sql_count)){
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    mysqli_stmt_bind_result($stmt_count, $total_records);
    mysqli_stmt_fetch($stmt_count);
    mysqli_stmt_close($stmt_count);
}
$total_pages = ceil($total_records / $records_per_page);

// 2. Fetch paginated applications
$applications = [];
$sql_fetch = "SELECT la.*, s.first_name, s.last_name, s.roll_number, c.class_name, c.section_name 
              FROM leave_applications la 
              JOIN students s ON la.student_id = s.id 
              JOIN classes c ON la.class_id = c.id 
              $where_sql 
              ORDER BY la.created_at DESC 
              LIMIT ?, ?";

$params_fetch = $params;
$types_fetch = $types;

$params_fetch[] = $offset;
$params_fetch[] = $records_per_page;
$types_fetch .= "ii";

if($stmt_fetch = mysqli_prepare($link, $sql_fetch)){
    mysqli_stmt_bind_param($stmt_fetch, $types_fetch, ...$params_fetch);
    mysqli_stmt_execute($stmt_fetch);
    $applications = mysqli_fetch_all(mysqli_stmt_get_result($stmt_fetch), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_fetch);
}

// Fetch all classes for the filter dropdown
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name";
if ($stmt_all_classes = mysqli_prepare($link, $sql_all_classes)) {
    mysqli_stmt_execute($stmt_all_classes);
    $all_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_all_classes), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_all_classes);
}

mysqli_close($link);

// Flash messages
$success_message = $_SESSION['success_message'] ?? null; unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null; unset($_SESSION['error_message']);
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Leave Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #3b82f6; 
            color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .glass-card {
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }
        .tab-link.active {
            background-color: #38bdf8;
            color: white;
            box-shadow: 0 4px 14px 0 rgba(56, 189, 248, 0.39);
        }
        .form-input, .form-select {
            background: rgba(0, 0, 0, 0.25);
            border-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
        .form-input::placeholder { color: rgba(255, 255, 255, 0.6); }
        .form-input:focus, .form-select:focus { border-color: #4CAF50; outline: none; box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.5); }
        .pagination-link {
            @apply px-3 py-1 mx-1 rounded-md transition-colors duration-200;
        }
        .pagination-link.active {
            @apply bg-blue-600 text-white;
        }
        .pagination-link:not(.active):hover {
            @apply bg-gray-700 text-white;
        }
        .disabled-link {
            @apply opacity-50 cursor-not-allowed;
        }
        .stat-icon-total { background-color: rgba(255,255,255,0.2); color: white; }
        .stat-icon-pending { background-color: rgba(253,224,71,0.2); color: rgb(253 224 71); }
        .stat-icon-approved { background-color: rgba(134,239,172,0.2); color: rgb(134 239 172); }
        .stat-icon-rejected { background-color: rgba(252,165,165,0.2); color: rgb(252 165 165); }
    </style>
</head>
<body class="text-white">
    <div class="min-h-screen flex flex-col">
         

        <div class="container mx-auto mt-28 p-4 md:p-8 flex-grow">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-10">
                    <h1 class="text-4xl md:text-5xl font-bold tracking-tight">Student Leave Applications</h1>
                    <p class="text-lg text-gray-300 mt-2">Overview of all student leave requests across the school.</p>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 mb-8">
                    <div class="glass-card p-6 rounded-2xl flex items-center">
                        <div class="stat-icon-total rounded-full h-12 w-12 flex items-center justify-center text-xl"><i class="fas fa-list-alt"></i></div>
                        <div class="ml-4"><p class="text-sm text-gray-400">Total Applications</p><p class="text-2xl font-bold"><?php echo $stats['All']; ?></p></div>
                    </div>
                    <div class="glass-card p-6 rounded-2xl flex items-center">
                        <div class="stat-icon-pending rounded-full h-12 w-12 flex items-center justify-center text-xl"><i class="fas fa-clock"></i></div>
                        <div class="ml-4"><p class="text-sm text-gray-400">Pending</p><p class="text-2xl font-bold"><?php echo $stats['Pending']; ?></p></div>
                    </div>
                    <div class="glass-card p-6 rounded-2xl flex items-center">
                        <div class="stat-icon-approved rounded-full h-12 w-12 flex items-center justify-center text-xl"><i class="fas fa-check-circle"></i></div>
                        <div class="ml-4"><p class="text-sm text-gray-400">Approved</p><p class="text-2xl font-bold"><?php echo $stats['Approved']; ?></p></div>
                    </div>
                    <div class="glass-card p-6 rounded-2xl flex items-center">
                        <div class="stat-icon-rejected rounded-full h-12 w-12 flex items-center justify-center text-xl"><i class="fas fa-times-circle"></i></div>
                        <div class="ml-4"><p class="text-sm text-gray-400">Rejected</p><p class="text-2xl font-bold"><?php echo $stats['Rejected']; ?></p></div>
                    </div>
                </div>

                <div class="glass-card p-6 md:p-8 rounded-2xl shadow-xl">
                    <div class="flex justify-center mb-6">
                        <div class="bg-black/20 rounded-full p-1 flex space-x-1">
                            <?php
                            $base_filter_params = [
                                'class_id' => $filter_class_id,
                                'search' => $search_query,
                                'date_from' => $filter_date_from,
                                'date_to' => $filter_date_to
                            ];
                            $base_filter_string = http_build_query(array_filter($base_filter_params));
                            $base_filter_string = !empty($base_filter_string) ? '&' . $base_filter_string : '';
                            ?>
                            <a href="?status=All<?php echo $base_filter_string; ?>" class="tab-link px-6 py-2 text-sm font-semibold rounded-full transition-colors duration-300 <?php echo $filter_status == 'All' ? 'active' : 'text-gray-300 hover:bg-white/5'; ?>">All</a>
                            <a href="?status=Pending<?php echo $base_filter_string; ?>" class="tab-link px-6 py-2 text-sm font-semibold rounded-full transition-colors duration-300 <?php echo $filter_status == 'Pending' ? 'active' : 'text-gray-300 hover:bg-white/5'; ?>">Pending</a>
                            <a href="?status=Approved<?php echo $base_filter_string; ?>" class="tab-link px-6 py-2 text-sm font-semibold rounded-full transition-colors duration-300 <?php echo $filter_status == 'Approved' ? 'active' : 'text-gray-300 hover:bg-white/5'; ?>">Approved</a>
                            <a href="?status=Rejected<?php echo $base_filter_string; ?>" class="tab-link px-6 py-2 text-sm font-semibold rounded-full transition-colors duration-300 <?php echo $filter_status == 'Rejected' ? 'active' : 'text-gray-300 hover:bg-white/5'; ?>">Rejected</a>
                        </div>
                    </div>

                    <form method="GET" class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                        
                        <div>
                            <label for="class_id_filter" class="block text-sm font-semibold text-gray-300 mb-1">Filter by Class:</label>
                            <select name="class_id" id="class_id_filter" class="w-full h-10 form-select rounded-lg">
                                <option value="">All Classes</option>
                                <?php foreach($all_classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo ($filter_class_id == $class['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="search_input" class="block text-sm font-semibold text-gray-300 mb-1">Search Student:</label>
                            <input type="text" name="search" id="search_input" class="w-full h-10 form-input rounded-lg" placeholder="Name or Roll Number" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>

                        <div>
                            <label for="date_from_filter" class="block text-sm font-semibold text-gray-300 mb-1">Leave From:</label>
                            <input type="date" name="date_from" id="date_from_filter" class="w-full h-10 form-input rounded-lg" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>

                        <div>
                            <label for="date_to_filter" class="block text-sm font-semibold text-gray-300 mb-1">Leave To:</label>
                            <input type="date" name="date_to" id="date_to_filter" class="w-full h-10 form-input rounded-lg" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>

                        <div class="lg:col-span-4 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-md hover:shadow-lg transition-all">Apply Filters</button>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="border-b-2 border-white/10"><tr>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Student</th>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Class</th>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Date Range</th>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Reason</th>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Reviewed By</th>
                                <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php if(empty($applications)): ?>
                                    <tr><td colspan="7" class="p-12 text-center text-gray-400 italic">No leave applications found for the selected filters.</td></tr>
                                <?php else: ?>
                                    <?php foreach($applications as $app): ?>
                                    <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                        <td class="p-4 align-top"><div class="font-medium text-gray-100"><?php echo htmlspecialchars($app['first_name'].' '.$app['last_name']); ?></div><div class="text-xs text-gray-400 mt-1">Roll: <?php echo htmlspecialchars($app['roll_number']); ?></div></td>
                                        <td class="p-4 align-top text-sm font-medium text-gray-200"><?php echo htmlspecialchars($app['class_name'].' - '.$app['section_name']); ?></td>
                                        <td class="p-4 align-top whitespace-nowrap text-sm font-medium text-gray-200"><?php echo date('d M, Y', strtotime($app['leave_from'])); ?> to <?php echo date('d M, Y', strtotime($app['leave_to'])); ?></td>
                                        <td class="p-4 align-top text-sm text-gray-300 max-w-sm"><p class="truncate" title="<?php echo htmlspecialchars($app['reason']); ?>"><?php echo htmlspecialchars($app['reason']); ?></p></td>
                                        <td class="p-4 align-top whitespace-nowrap text-center text-sm">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                     if($app['status'] == 'Approved') echo 'bg-green-500/20 text-green-300';
                                                     elseif($app['status'] == 'Rejected') echo 'bg-red-500/20 text-red-300';
                                                     else echo 'bg-yellow-500/20 text-yellow-300';
                                                ?>">
                                                <?php echo htmlspecialchars($app['status']); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 align-top text-sm text-gray-300 max-w-xs">
                                            <?php if (!empty($app['reviewed_by'])): ?>
                                                <p class="text-xs text-white">by <?php echo htmlspecialchars($app['reviewed_by']); ?></p>
                                                <p class="text-xs text-white"><?php echo date('d M, Y', strtotime($app['reviewed_at'])); ?></p>
                                            <?php else: ?>
                                                <span class="text-gray-500">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 align-top whitespace-nowrap">
                                            <?php if ($app['status'] === 'Pending'): ?>
                                                <button class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded text-xs transition-colors mb-1" 
                                                         onclick="openModal('approve', <?php echo $app['id']; ?>)">Approve</button>
                                                <button class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-xs transition-colors"
                                                         onclick="openModal('reject', <?php echo $app['id']; ?>)">Reject</button>
                                            <?php else: ?>
                                                <span class="text-white text-xs">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                   <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex flex-col md:flex-row justify-between items-center text-sm">
                    <div class="text-gray-400 mb-4 md:mb-0">Showing <b><?php echo min($offset + 1, $total_records); ?></b> to <b><?php echo min($offset + $records_per_page, $total_records); ?></b> of <b><?php echo $total_records; ?></b> records.</div>
                    <nav class="inline-flex rounded-lg shadow-sm -space-x-px">
                        <?php
                        $pagination_params = [
                            'status' => $filter_status,
                            'class_id' => $filter_class_id,
                            'search' => $search_query,
                            'date_from' => $filter_date_from,
                            'date_to' => $filter_date_to
                        ];
                        $pagination_params = array_filter($pagination_params);
                        $param_string = http_build_query($pagination_params);
                        ?>
                        <a href="?page=<?php echo max(1, $current_page - 1); ?>&<?php echo $param_string; ?>" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if($current_page <= 1) echo 'text-gray-500 cursor-not-allowed'; ?>"><i class="fas fa-chevron-left"></i></a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo $param_string; ?>" class="pagination-link relative inline-flex items-center px-4 py-2 border-t border-b border-white/20 <?php echo $i == $current_page ? 'bg-blue-600 font-bold' : 'bg-black/20 hover:bg-white/10'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>&<?php echo $param_string; ?>" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if($current_page >= $total_pages) echo 'text-gray-500 cursor-not-allowed'; ?>"><i class="fas fa-chevron-right"></i></a>
                    </nav>
                </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div id="actionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="glass-card p-6 rounded-lg w-full max-w-md text-center">
                <h3 id="modalTitle" class="text-xl font-bold mb-4">Confirm Action</h3>
                <p id="modalBody" class="text-gray-300 mb-6"></p>
                <div class="flex justify-center space-x-4">
                    <button id="confirmButton" class="px-6 py-2 rounded-lg font-bold transition-colors">Confirm</button>
                    <button id="cancelButton" class="px-6 py-2 rounded-lg font-bold text-gray-300 bg-gray-600 hover:bg-gray-700 transition-colors">Cancel</button>
                </div>
            </div>
        </div>

        <div id="toast-container" class="fixed top-5 right-5 z-50 space-y-3"></div>

        
    </div>
    <script>
        // --- TOAST NOTIFICATION SCRIPT ---
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = <?php echo json_encode($success_message); ?>;
            const errorMessage = <?php echo json_encode($error_message); ?>;

            if (successMessage) {
                showToast(successMessage, 'success');
            } else if (errorMessage) {
                showToast(errorMessage, 'error');
            }
        });

        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toast-container');
            const bgColor = type === 'success' ? 'bg-green-600' : 'bg-red-600';
            const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-triangle"></i>';
            
            const toast = document.createElement('div');
            toast.className = `flex items-center w-full max-w-xs p-4 space-x-4 text-white ${bgColor} rounded-lg shadow-lg transform translate-x-full opacity-0 transition-all duration-300`;
            toast.innerHTML = `<div class="text-xl">${icon}</div><div class="pl-4 text-sm font-medium">${message}</div>`;
            
            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            }, 50);

            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // --- MODAL SCRIPT ---
        const modal = document.getElementById('actionModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        const confirmButton = document.getElementById('confirmButton');
        const cancelButton = document.getElementById('cancelButton');

        function openModal(action, applicationId) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            if (action === 'approve') {
                modalTitle.textContent = 'Approve Application?';
                modalBody.textContent = 'Are you sure you want to approve this leave application? This action cannot be undone.';
                confirmButton.textContent = 'Approve';
                confirmButton.className = 'px-6 py-2 rounded-lg font-bold transition-colors bg-green-500 hover:bg-green-600 text-white';
                confirmButton.onclick = () => {
                    window.location.href = `approve_leave.php?id=${applicationId}`;
                };
            } else if (action === 'reject') {
                modalTitle.textContent = 'Reject Application?';
                modalBody.textContent = 'Are you sure you want to reject this leave application? This action cannot be undone.';
                confirmButton.textContent = 'Reject';
                confirmButton.className = 'px-6 py-2 rounded-lg font-bold transition-colors bg-red-500 hover:bg-red-600 text-white';
                confirmButton.onclick = () => {
                    window.location.href = `reject_leave.php?id=${applicationId}`;
                };
            }
        }

        cancelButton.onclick = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };
    </script>
</body>
</html>

<?php
require_once './admin_footer.php';
?>
