<?php
// admin_helpdesk.php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["id"]; // Admin ID, might be used for logging views later if needed

// --- NO POST ACTIONS FOR ADMIN (VIEW-ONLY PAGE) ---
// All POST handling from the teacher version is removed.

// --- FETCH DATA FOR FILTERS AND DISPLAY ---
$view_ticket_id = $_GET['ticket_id'] ?? null;
$filter_status = $_GET['status'] ?? 'Open'; // Default status filter
$filter_class = $_GET['class_id'] ?? '';
$filter_assigned_teacher = $_GET['teacher_id'] ?? ''; // New filter for admin

// Fetch all classes for filter dropdown
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name";
if ($stmt_ac = mysqli_prepare($link, $sql_all_classes)) {
    mysqli_stmt_execute($stmt_ac);
    $all_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_ac), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_ac);
}

// Fetch all teachers for filter dropdown (teachers assigned to tickets)
$all_teachers = [];
$sql_all_teachers = "SELECT id, full_name FROM teachers ORDER BY full_name";
if ($stmt_at = mysqli_prepare($link, $sql_all_teachers)) {
    mysqli_stmt_execute($stmt_at);
    $all_teachers = mysqli_fetch_all(mysqli_stmt_get_result($stmt_at), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_at);
}


if ($view_ticket_id) {
    // --- VIEW SINGLE TICKET CONVERSATION ---
    $ticket = null;
    $messages = [];
    // Admin can view ANY ticket, so no teacher_id restriction in WHERE
    $sql_ticket = "SELECT st.*, s.first_name, s.last_name, c.class_name, c.section_name, sub.subject_name, t.full_name AS assigned_teacher_name
                   FROM support_tickets st 
                   JOIN students s ON st.student_id = s.id 
                   JOIN classes c ON st.class_id = c.id 
                   JOIN subjects sub ON st.subject_id = sub.id 
                   LEFT JOIN teachers t ON st.teacher_id = t.id 
                   WHERE st.id = ?";
    if ($stmt_t = mysqli_prepare($link, $sql_ticket)) {
        mysqli_stmt_bind_param($stmt_t, "i", $view_ticket_id);
        mysqli_stmt_execute($stmt_t);
        $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t));
        mysqli_stmt_close($stmt_t);
    }

    if ($ticket) {
        $sql_messages = "SELECT stm.*, 
                            CASE stm.user_role 
                                WHEN 'Student' THEN CONCAT(s.first_name, ' ', s.last_name) 
                                WHEN 'Teacher' THEN t.full_name 
                            END AS sender_name
                         FROM support_ticket_messages stm
                         LEFT JOIN students s ON stm.user_role = 'Student' AND stm.user_id = s.id
                         LEFT JOIN teachers t ON stm.user_role = 'Teacher' AND stm.user_id = t.id
                         WHERE stm.ticket_id = ? ORDER BY stm.created_at ASC";
        if ($stmt_m = mysqli_prepare($link, $sql_messages)) {
            mysqli_stmt_bind_param($stmt_m, "i", $view_ticket_id);
            mysqli_stmt_execute($stmt_m);
            $messages = mysqli_fetch_all(mysqli_stmt_get_result($stmt_m), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt_m);
        }
    }
} else {
    // --- VIEW LIST OF TICKETS (with Pagination and Stats) ---
    $tickets = [];
    $stats_ticket_list = ['Open' => 0, 'Closed' => 0]; // Specific stats for the main list, if needed

    // Get stats for ALL tickets (same as the top section, ensuring global view)
    $sql_all_stats_base = "SELECT st.status, COUNT(st.id) as count 
                           FROM support_tickets st 
                           JOIN students s ON st.student_id = s.id"; // Joined for robustness, even if student filter is not used

    $sql_all_stats = $sql_all_stats_base . " GROUP BY st.status";

    if ($stmt_stats = mysqli_prepare($link, $sql_all_stats)) {
        mysqli_stmt_execute($stmt_stats);
        $result_stats = mysqli_stmt_get_result($stmt_stats);
        while ($row = mysqli_fetch_assoc($result_stats)) {
            if (isset($stats_ticket_list[$row['status']])) {
                $stats_ticket_list[$row['status']] = $row['count'];
            }
        }
        mysqli_stmt_close($stmt_stats);
    }

    $records_per_page = 10;
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $records_per_page;
    $total_records = 0;

    $where_clauses = ["1=1"]; // Start with a true condition for admin to see all by default
    $params = [];
    $types = "";

    // Filter by Status
    if (!empty($filter_status)) {
        $where_clauses[] = "st.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
    // Filter by Class
    if (!empty($filter_class) && is_numeric($filter_class)) {
        $where_clauses[] = "st.class_id = ?";
        $params[] = $filter_class;
        $types .= "i";
    }
    // Filter by Assigned Teacher
    if (!empty($filter_assigned_teacher) && is_numeric($filter_assigned_teacher)) {
        $where_clauses[] = "st.teacher_id = ?";
        $params[] = $filter_assigned_teacher;
        $types .= "i";
    }

    $where_sql = "WHERE " . implode(" AND ", $where_clauses);

    // Count total records for pagination
    $sql_count = "SELECT COUNT(st.id) 
                  FROM support_tickets st 
                  JOIN students s ON st.student_id = s.id 
                  $where_sql";
    if ($stmt_count = mysqli_prepare($link, $sql_count)) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt_count, $types, ...$params);
        }
        mysqli_stmt_execute($stmt_count);
        mysqli_stmt_bind_result($stmt_count, $total_records);
        mysqli_stmt_fetch($stmt_count);
        mysqli_stmt_close($stmt_count);
    }
    $total_pages = ceil($total_records / $records_per_page);

    // Fetch paginated tickets
    $sql_list = "SELECT st.id, st.title, st.status, st.updated_at, s.first_name, s.last_name, 
                        c.class_name, c.section_name, sub.subject_name, t.full_name AS assigned_teacher_name
                 FROM support_tickets st 
                 JOIN students s ON st.student_id = s.id 
                 JOIN classes c ON st.class_id = c.id 
                 JOIN subjects sub ON st.subject_id = sub.id 
                 LEFT JOIN teachers t ON st.teacher_id = t.id 
                 $where_sql 
                 ORDER BY st.updated_at DESC 
                 LIMIT ? OFFSET ?";
    
    $params_list = $params;
    $types_list = $types;

    $params_list[] = $records_per_page;
    $params_list[] = $offset;
    $types_list .= "ii";

    if ($stmt_l = mysqli_prepare($link, $sql_list)) {
        mysqli_stmt_bind_param($stmt_l, $types_list, ...$params_list);
        mysqli_stmt_execute($stmt_l);
        $tickets = mysqli_fetch_all(mysqli_stmt_get_result($stmt_l), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_l);
    }
}

mysqli_close($link);

// Flash messages (no POST actions, so these are unlikely to be set by this page itself)
$success_message = $_SESSION['success_message'] ?? null; unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null; unset($_SESSION['error_message']);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Helpdesk Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #3b82f6; /* Tailwind blue-500 */
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
        .tab-link {
            @apply px-6 py-2 text-sm font-semibold rounded-full transition-colors duration-300;
        }
        .tab-link.active {
            background-color: #38bdf8; /* Tailwind sky-400 */
            color: white;
            box-shadow: 0 4px 14px 0 rgba(56, 189, 248, 0.39);
        }
        .tab-link:not(.active):hover {
            @apply bg-white/5;
        }
    </style>
</head>
<body class="text-white">
    <div class="min-h-screen flex flex-col">
         

        <div class="container mx-auto mt-28 p-4 md:p-8 flex-grow">
            <div class="max-w-7xl mx-auto">
                <?php if ($view_ticket_id && $ticket): // --- SINGLE TICKET VIEW --- 
                ?>
                    <div class="glass-card rounded-2xl shadow-xl p-6 md:p-8">
                        <a href="admin_helpdesk.php" class="text-sky-300 hover:text-white font-medium mb-4 block text-lg"><i class="fas fa-arrow-left mr-2"></i>Back to All Tickets</a>
                        
                        <div class="border-b border-white/10 pb-4 mb-6">
                            <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($ticket['title']); ?></h1>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-300 mt-2">
                                <span><i class="fas fa-user-graduate mr-1 text-gray-400"></i> Student: <strong><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></strong></span>
                                <span><i class="fas fa-chalkboard mr-1 text-gray-400"></i> Class: <strong><?php echo htmlspecialchars($ticket['class_name'] . ' - ' . $ticket['section_name']); ?></strong></span>
                                <span><i class="fas fa-book mr-1 text-gray-400"></i> Subject: <strong><?php echo htmlspecialchars($ticket['subject_name']); ?></strong></span>
                                <span><i class="fas fa-user-tie mr-1 text-gray-400"></i> Assigned Teacher: <strong><?php echo htmlspecialchars($ticket['assigned_teacher_name'] ?? 'N/A'); ?></strong></span>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $ticket['status'] == 'Open' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-gray-500/20 text-gray-300'; ?>">Status: <?php echo $ticket['status']; ?></span>
                            </div>
                        </div>

                        <div class="h-96 overflow-y-auto bg-black/10 rounded-lg p-4 space-y-4">
                            <?php if (empty($messages)): ?>
                                <p class="text-center text-gray-400 italic">No messages in this conversation yet.</p>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="flex <?php echo $message['user_role'] == 'Teacher' ? 'justify-end' : 'justify-start'; ?>">
                                        <div class="max-w-lg p-3 rounded-xl <?php echo $message['user_role'] == 'Teacher' ? 'bg-blue-600' : 'bg-gray-700'; ?>">
                                            <p class="text-xs font-semibold mb-1 opacity-80"><?php echo htmlspecialchars($message['sender_name'] ?? $message['user_role']); ?></p>
                                            <p class="text-base"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                            <p class="text-xs mt-2 opacity-60 text-right"><?php echo date('d M Y, h:i A', strtotime($message['created_at'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- No reply form or action buttons for Admin (view-only) -->
                    </div>
                <?php else: // --- TICKET LIST VIEW --- 
                ?>
                    <div class="text-center mb-10">
                        <h1 class="text-4xl md:text-5xl font-bold tracking-tight">Helpdesk Overview</h1>
                        <p class="text-lg text-gray-300 mt-2">View all student support tickets.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div class="glass-card p-6 rounded-2xl flex items-center">
                            <div class="bg-yellow-500/20 text-yellow-300 rounded-full h-12 w-12 flex items-center justify-center text-xl"><i class="fas fa-folder-open"></i></div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-400">Open Tickets</p>
                                <p class="text-2xl font-bold"><?php echo $stats_ticket_list['Open']; ?></p>
                            </div>
                        </div>
                        <div class="glass-card p-6 rounded-2xl flex items-center">
                            <div class="bg-gray-500/20 text-gray-300 rounded-full h-12 w-12 flex items-center justify-center text-xl"><i class="fas fa-folder"></i></div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-400">Closed Tickets</p>
                                <p class="text-2xl font-bold"><?php echo $stats_ticket_list['Closed']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass-card p-6 md:p-8 rounded-2xl">
                        <div class="flex flex-col md:flex-row justify-between items-center mb-6 pb-4 border-b border-white/10">
                            <h2 class="text-2xl font-semibold">All Tickets</h2>
                            <!-- Filter Form -->
                            <form method="GET" class="mt-4 md:mt-0 flex flex-wrap items-center gap-4">
                                <!-- Status Filter Tabs (simplified to dropdown for space) -->
                                <select name="status" onchange="this.form.submit()" class="form-select rounded-full text-sm h-11">
                                    <option value="Open" <?php if ($filter_status == 'Open') echo 'selected'; ?>>Open</option>
                                    <option value="Closed" <?php if ($filter_status == 'Closed') echo 'selected'; ?>>Closed</option>
                                </select>
                                <!-- Class Filter -->
                                <select name="class_id" onchange="this.form.submit()" class="form-select rounded-full text-sm h-11">
                                    <option value="">All Classes</option>
                                    <?php foreach ($all_classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php if ($filter_class == $class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Assigned Teacher Filter -->
                                <select name="teacher_id" onchange="this.form.submit()" class="form-select rounded-full text-sm h-11">
                                    <option value="">All Teachers</option>
                                    <?php foreach ($all_teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>" <?php if ($filter_assigned_teacher == $teacher['id']) echo 'selected'; ?>><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="border-b-2 border-white/10">
                                    <tr>
                                        <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Student & Class</th>
                                        <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Title & Subject</th>
                                        <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Assigned Teacher</th>
                                        <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                                        <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Last Updated</th>
                                        <th class="p-4 text-center text-xs font-bold uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tickets)): ?>
                                        <tr>
                                            <td colspan="6" class="p-12 text-center text-gray-400 italic">No tickets found for this filter.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr class="hover:bg-white/5 transition-colors duration-200 border-b border-white/5">
                                                <td class="p-4 align-top">
                                                    <div class="font-medium text-gray-100"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                                                    <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($ticket['class_name'] . ' - ' . $ticket['section_name']); ?></div>
                                                </td>
                                                <td class="p-4 align-top">
                                                    <div class="font-medium text-gray-100"><?php echo htmlspecialchars($ticket['title']); ?></div>
                                                    <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($ticket['subject_name']); ?></div>
                                                </td>
                                                <td class="p-4 align-top text-sm text-gray-300">
                                                    <?php echo htmlspecialchars($ticket['assigned_teacher_name'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="p-4 align-top"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $ticket['status'] == 'Open' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-gray-500/20 text-gray-300'; ?>"><?php echo $ticket['status']; ?></span></td>
                                                <td class="p-4 align-top whitespace-nowrap text-sm text-gray-300"><?php echo date('d M, Y', strtotime($ticket['updated_at'])); ?></td>
                                                <td class="p-4 align-top whitespace-nowrap text-center text-sm">
                                                    <a href="?ticket_id=<?php echo $ticket['id']; ?>" class="w-8 h-8 rounded-full bg-sky-500/20 text-sky-300 hover:bg-sky-500/40 transition-all flex items-center justify-center mx-auto" title="View Details"><i class="fas fa-eye"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                            <div class="mt-6 flex flex-col md:flex-row justify-between items-center text-sm">
                                <div class="text-gray-400 mb-4 md:mb-0">Showing <b><?php echo min($offset + 1, $total_records); ?></b> to <b><?php echo min($offset + $records_per_page, $total_records); ?></b> of <b><?php echo $total_records; ?></b> records.</div>
                                <nav class="inline-flex rounded-lg shadow-sm -space-x-px">
                                    <?php
                                    // Preserve all filter parameters for pagination links
                                    $pagination_params = [
                                        'status' => $filter_status,
                                        'class_id' => $filter_class,
                                        'teacher_id' => $filter_assigned_teacher // Include new teacher filter
                                    ];
                                    // Filter out empty parameters for cleaner URLs
                                    $pagination_params = array_filter($pagination_params); 
                                    $param_string = http_build_query($pagination_params); 
                                    $base_pagination_url = "admin_helpdesk.php?" . $param_string . (!empty($param_string) ? "&" : "");
                                    ?>
                                    <a href="<?php echo $base_pagination_url; ?>page=<?php echo max(1, $current_page - 1); ?>" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if ($current_page <= 1) echo 'text-gray-500 cursor-not-allowed'; ?>"><i class="fas fa-chevron-left"></i></a>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="<?php echo $base_pagination_url; ?>page=<?php echo $i; ?>" class="pagination-link relative inline-flex items-center px-4 py-2 border-t border-b border-white/20 <?php echo $i == $current_page ? 'bg-blue-600 text-white font-bold' : 'bg-black/20 hover:bg-white/10'; ?>"><?php echo $i; ?></a>
                                    <?php endfor; ?>
                                    <a href="<?php echo $base_pagination_url; ?>page=<?php echo min($total_pages, $current_page + 1); ?>" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if ($current_page >= $total_pages) echo 'text-gray-500 cursor-not-allowed'; ?>"><i class="fas fa-chevron-right"></i></a>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Toast Container (for flash messages) -->
        <div id="toast-container" class="fixed top-5 right-5 z-50 space-y-3"></div>

        
    </div>
    <script>
        // --- TOAST NOTIFICATION SCRIPT (Adjusted for direct inclusion) ---
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
            if (!toastContainer) { // Create container if it doesn't exist
                const newContainer = document.createElement('div');
                newContainer.id = 'toast-container';
                newContainer.className = 'fixed top-5 right-5 z-50 space-y-3';
                document.body.appendChild(newContainer);
                toastContainer = newContainer;
            }

            const bgColor = type === 'success' ? 'bg-green-600' : 'bg-red-600';
            const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-triangle"></i>';
            
            const toast = document.createElement('div');
            toast.className = `flex items-center w-full max-w-xs p-4 space-x-4 text-white ${bgColor} rounded-lg shadow-lg transform translate-x-full opacity-0 transition-all duration-300`;
            toast.innerHTML = `<div class="text-xl">${icon}</div><div class="pl-4 text-sm font-medium">${message}</div>`;
            
            toastContainer.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            }, 50); // Small delay to ensure CSS transition applies

            // Animate out and remove after 5 seconds
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-x-full');
                setTimeout(() => toast.remove(), 300); // Remove element after fade out
            }, 5000);
        }
    </script>
</body>
</html>
<?php
require_once './admin_footer.php';
?>