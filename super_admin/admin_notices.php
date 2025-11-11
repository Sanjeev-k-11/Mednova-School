<?php
// admin_notices.php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["super_admin_id"]; // The ID of the currently logged-in admin

// --- HELPER FUNCTIONS (unchanged) ---
// Note: These helper functions (numberToWords, calculateGrade) are not used in this notices page
// and could be removed if they are not globally required by other includes.
// I've kept them for consistency with previous files.


// --- HANDLE DELETE ACTION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_notice'])) {
    $notice_id_to_delete = $_POST['notice_id'];
    
    // ADMIN can delete ANY notice. No teacher_id check needed here.
    $sql_delete = "DELETE FROM notices WHERE id = ?";
    if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
        mysqli_stmt_bind_param($stmt_delete, "i", $notice_id_to_delete);
        if (mysqli_stmt_execute($stmt_delete)) {
            $_SESSION['success_message'] = "Notice deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting notice: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        $_SESSION['error_message'] = "Error preparing statement for delete: " . mysqli_error($link);
    }
    header("location: admin_notices.php"); // Redirect to prevent form resubmission
    exit;
}

// --- HANDLE UPDATE ACTION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_notice'])) {
    $class_id = trim($_POST['class_id']);
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $notice_id_to_update = $_POST['notice_id'] ?? null;

    if (empty($class_id) || empty($title) || empty($content) || empty($notice_id_to_update)) {
        $_SESSION['error_message'] = "All fields and notice ID are required for update.";
    } else {
        // ADMIN can update ANY notice. No teacher_id check needed in WHERE.
        $sql_update = "UPDATE notices SET class_id = ?, title = ?, content = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql_update)) {
            mysqli_stmt_bind_param($stmt, "issi", $class_id, $title, $content, $notice_id_to_update);
            if (mysqli_stmt_execute($stmt)) {
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $_SESSION['success_message'] = "Notice updated successfully!";
                } else {
                    $_SESSION['success_message'] = "No changes were made."; // If data submitted is identical
                }
            } else {
                $_SESSION['error_message'] = "Error updating notice: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing statement for update: " . mysqli_error($link);
        }
    }
    header("location: admin_notices.php");
    exit;
}

// Flash messages
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

// --- FETCH DATA FOR DISPLAY ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Filter parameters for admin view
$filter_class_id = $_GET['filter_class_id'] ?? '';
$filter_teacher_id = $_GET['filter_teacher_id'] ?? ''; // New filter for admin

$where_clauses = ["1=1"]; // Start with a true condition for admin to see all by default
$params = [];
$types = "";

// Filter by Class
if (!empty($filter_class_id) && is_numeric($filter_class_id)) {
    $where_clauses[] = "n.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}

// Filter by Teacher who posted the notice
if (!empty($filter_teacher_id) && is_numeric($filter_teacher_id)) {
    $where_clauses[] = "n.teacher_id = ?";
    $params[] = $filter_teacher_id;
    $types .= "i";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Fetch all classes for the filter dropdown
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name";
if ($stmt_all_classes = mysqli_prepare($link, $sql_all_classes)) {
    mysqli_stmt_execute($stmt_all_classes);
    $all_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_all_classes), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_all_classes);
}

// Fetch all teachers for the filter dropdown (who can post notices)
$all_teachers = [];
$sql_all_teachers = "SELECT id, full_name FROM teachers ORDER BY full_name";
if ($stmt_all_teachers = mysqli_prepare($link, $sql_all_teachers)) {
    mysqli_stmt_execute($stmt_all_teachers);
    $all_teachers = mysqli_fetch_all(mysqli_stmt_get_result($stmt_all_teachers), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_all_teachers);
}


// Count total records for pagination
$sql_count = "SELECT COUNT(*) FROM notices n $where_sql";
$total_records = 0;
if ($stmt_count = mysqli_prepare($link, $sql_count)) {
    if (!empty($params)) { // Only bind if there are actual parameters
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    mysqli_stmt_bind_result($stmt_count, $total_records);
    mysqli_stmt_fetch($stmt_count);
    mysqli_stmt_close($stmt_count);
}

$total_pages = ceil($total_records / $records_per_page);

// Fetch paginated notices
$notices = [];
$sql_fetch = "SELECT n.id, n.class_id, n.title, n.content, n.created_at, n.posted_by_name, c.class_name, c.section_name 
              FROM notices n 
              JOIN classes c ON n.class_id = c.id 
              $where_sql 
              ORDER BY n.created_at DESC 
              LIMIT ? OFFSET ?";

// Need to duplicate params for the LIMIT/OFFSET binding as they are integers
$params_fetch = $params; // Copy existing params
$types_fetch = $types;   // Copy existing types

$params_fetch[] = $records_per_page;
$params_fetch[] = $offset;
$types_fetch .= "ii";

if ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
    mysqli_stmt_bind_param($stmt_fetch, $types_fetch, ...$params_fetch);
    mysqli_stmt_execute($stmt_fetch);
    $result_notices = mysqli_stmt_get_result($stmt_fetch);
    $notices = mysqli_fetch_all($result_notices, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_fetch);
}

mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Manage Notices</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(-45deg, #1d2b64, #373b44, #4286f4, #292E49); background-size: 400% 400%; animation: gradientBG 25s ease infinite; color: white; }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .bg-black\/20 { background-color: rgba(0, 0, 0, 0.2); } /* Define explicitly for Tailwind JIT compatibility if needed */
        .backdrop-blur-sm { backdrop-filter: blur(4px); }
        .modal { transition: opacity 0.3s ease-in-out; }
        .modal-panel { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
    </style>
</head>
<body class="text-white">
    <div class="min-h-screen flex flex-col">
       

        <div class="container mx-auto mt-28 p-4 md:p-8 flex-grow">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row justify-between items-center mb-8">
                    <h1 class="text-4xl font-bold tracking-tight">Manage All Notices</h1>
                </div>

                <!-- Flash Messages -->
                <?php if ($success_message): ?>
                    <div id="toast-success" class="flex items-center w-full max-w-xs p-4 mb-4 text-white bg-green-600 rounded-lg shadow-lg" role="alert">
                        <div class="text-xl"><i class="fas fa-check-circle"></i></div>
                        <div class="ml-3 text-sm font-medium"><?php echo $success_message; ?></div>
                        <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-green-600 text-white rounded-lg p-1.5 hover:bg-green-700 inline-flex h-8 w-8" data-dismiss-target="#toast-success" aria-label="Close">
                            <span class="sr-only">Close</span>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div id="toast-error" class="flex items-center w-full max-w-xs p-4 mb-4 text-white bg-red-600 rounded-lg shadow-lg" role="alert">
                        <div class="text-xl"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="ml-3 text-sm font-medium"><?php echo $error_message; ?></div>
                        <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-red-600 text-white rounded-lg p-1.5 hover:bg-red-700 inline-flex h-8 w-8" data-dismiss-target="#toast-error" aria-label="Close">
                            <span class="sr-only">Close</span>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Notice List Section -->
                <div class="bg-black/20 backdrop-blur-sm border border-white/10 rounded-2xl shadow-xl p-6 md:p-8">
                    <div class="flex flex-col md:flex-row justify-between items-center mb-4 pb-4 border-b border-white/10">
                        <h2 class="text-2xl font-semibold">All Posted Notices</h2>
                        <!-- Filter Form -->
                        <form method="GET" class="mt-4 md:mt-0 flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-2">
                                <label for="filter_class_id" class="text-sm font-medium text-gray-300">Class:</label>
                                <select name="filter_class_id" id="filter_class_id" class="bg-black/30 border-white/20 text-black rounded-full shadow-sm h-11 text-sm focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <option value="">All Classes</option>
                                    <?php foreach ($all_classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php if ($filter_class_id == $class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <label for="filter_teacher_id" class="text-sm font-medium text-gray-300">Teacher:</label>
                                <select name="filter_teacher_id" id="filter_teacher_id" class="bg-black/30 border-white/20 text-black rounded-full shadow-sm h-11 text-sm focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <option value="">All Teachers</option>
                                    <?php foreach ($all_teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>" <?php if ($filter_teacher_id == $teacher['id']) echo 'selected'; ?>><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full shadow-md hover:shadow-lg transition-all">Filter</button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="border-b-2 border-white/10">
                                <tr>
                                    <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Class</th>
                                    <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Title</th>
                                    <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Posted By</th>
                                    <th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Date Posted</th>
                                    <th class="p-4 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($notices)): ?>
                                    <tr><td colspan="5" class="p-12 text-center text-gray-400 italic">No notices found for the selected filters.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($notices as $notice): ?>
                                        <tr class="hover:bg-white/5 transition-colors duration-200 border-b border-white/5">
                                            <td class="p-4 whitespace-nowrap text-sm font-medium"><?php echo htmlspecialchars($notice['class_name'] . ' - ' . $notice['section_name']); ?></td>
                                            <td class="p-4 whitespace-nowrap text-sm text-gray-300"><?php echo htmlspecialchars($notice['title']); ?></td>
                                            <td class="p-4 whitespace-nowrap text-sm text-gray-300"><?php echo htmlspecialchars($notice['posted_by_name']); ?></td>
                                            <td class="p-4 whitespace-nowrap text-sm text-gray-300"><?php echo date('d M, Y h:i A', strtotime($notice['created_at'])); ?></td>
                                            <td class="p-4 whitespace-nowrap text-center text-sm font-medium">
                                                <div class="flex justify-center items-center space-x-2">
                                                    <button onclick='viewNotice(<?php echo json_encode($notice); ?>)' class="w-8 h-8 rounded-full bg-blue-500/20 text-blue-300 hover:bg-blue-500/40 transition-all flex items-center justify-center" title="View"><i class="fas fa-eye"></i></button>
                                                </div>
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
                            <div class="text-gray-400 mb-4 md:mb-0">
                                Showing <b><?php echo min($offset + 1, $total_records); ?></b> to <b><?php echo min($offset + $records_per_page, $total_records); ?></b> of <b><?php echo $total_records; ?></b> records.
                            </div>
                            <nav class="inline-flex rounded-lg shadow-sm -space-x-px">
                                <?php
                                // Preserve filters for pagination links
                                $pagination_params = ['filter_class_id' => $filter_class_id, 'filter_teacher_id' => $filter_teacher_id];
                                $param_string = http_build_query($pagination_params);
                                ?>
                                <a href="?page=<?php echo max(1, $current_page - 1); ?>&<?php echo $param_string; ?>" class="relative inline-flex items-center px-3 py-2 rounded-l-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if($current_page <= 1) echo 'text-gray-500 cursor-not-allowed'; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&<?php echo $param_string; ?>" class="relative inline-flex items-center px-4 py-2 border-t border-b border-white/20 <?php echo $i == $current_page ? 'bg-blue-600 text-white font-bold' : 'bg-black/20 hover:bg-white/10'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>&<?php echo $param_string; ?>" class="relative inline-flex items-center px-3 py-2 rounded-r-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if($current_page >= $total_pages) echo 'text-gray-500 cursor-not-allowed'; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- View/Edit Notice Modal (retained, but without 'create_notice' elements) -->
        <div id="noticeModal" class="fixed z-50 inset-0 overflow-y-auto hidden opacity-0 transition-opacity duration-300">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div id="modalBackdrop" class="fixed inset-0 bg-black bg-opacity-70 backdrop-blur-sm"></div>
                <div id="modalPanel" class="bg-gray-800 border border-white/10 rounded-2xl overflow-hidden shadow-2xl transform transition-all duration-300 scale-95 sm:max-w-lg w-full z-10">
                    <div class="px-6 py-4 border-b border-white/10">
                        <h3 id="modalTitle" class="text-2xl leading-6 font-bold"></h3>
                        <div class="mt-2 text-sm text-gray-400 flex items-center space-x-2 flex-wrap">
                            <span>For: <strong id="modalClass" class="text-gray-200"></strong></span>
                            <span>&bull;</span>
                            <span>By: <strong id="modalAuthor" class="text-gray-200"></strong></span>
                            <span>&bull;</span>
                            <span>Posted: <strong id="modalDate" class="text-gray-200"></strong></span>
                        </div>
                    </div>
                    <div class="p-6 text-gray-300 text-base leading-relaxed max-h-[60vh] overflow-y-auto">
                        <p id="modalContent"></p>
                    </div>
                    <div class="bg-black/20 px-6 py-3 flex justify-end">
                        <button onclick="closeModal()" type="button" class="px-6 py-2 bg-gray-700/70 border border-white/20 rounded-full font-semibold hover:bg-gray-600/70 transition-colors">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form Modal (This is new to handle admin editing) -->
        <div id="editFormModal" class="fixed z-50 inset-0 overflow-y-auto hidden opacity-0 transition-opacity duration-300">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div id="editModalBackdrop" class="fixed inset-0 bg-black bg-opacity-70 backdrop-blur-sm"></div>
                <div id="editModalPanel" class="bg-gray-800 border border-white/10 rounded-2xl overflow-hidden shadow-2xl transform transition-all duration-300 scale-95 sm:max-w-lg w-full z-10">
                    <div class="px-6 py-4 border-b border-white/10">
                        <h3 class="text-2xl leading-6 font-bold">Edit Notice</h3>
                    </div>
                    <div class="p-6">
                        <form id="editNoticeForm" action="admin_notices.php" method="post">
                            <input type="hidden" name="notice_id" id="edit_notice_id">
                            <input type="hidden" name="update_notice" value="1">

                            <div class="relative mb-4">
                                <i class="fas fa-users absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <label for="edit_class_id" class="sr-only">Class</label>
                                <select id="edit_class_id" name="class_id" required class="pl-10 h-11 w-full bg-black/30 border-white/20 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <option value="">-- Select a Class --</option>
                                    <?php foreach ($all_classes as $class): // Admin can re-assign to any class ?>
                                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="relative mb-4">
                                <i class="fas fa-heading absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <label for="edit_title" class="sr-only">Notice Title</label>
                                <input type="text" id="edit_title" name="title" required placeholder="Notice Title" class="pl-10 h-11 w-full bg-black/30 border-white/20 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            </div>
                            <div class="mt-6 relative mb-4">
                                <i class="fas fa-paragraph absolute left-3 top-5 text-gray-400"></i>
                                <label for="edit_content" class="sr-only">Message Content</label>
                                <textarea id="edit_content" name="content" rows="5" required placeholder="Type your message here..." class="pl-10 w-full bg-black/30 border-white/20 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors"></textarea>
                            </div>
                            <div class="bg-black/20 px-6 py-3 flex justify-end gap-4">
                                <button type="button" onclick="closeEditModal()" class="px-6 py-2 bg-gray-700/70 border border-white/20 rounded-full font-semibold hover:bg-gray-600/70 transition-colors">Cancel</button>
                                <button type="submit" class="px-6 py-2 bg-green-600 hover:bg-green-700 rounded-full font-semibold shadow-md hover:shadow-lg transition-all">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Container (for flash messages) -->
        <div id="toast-container" class="fixed top-5 right-5 z-50 space-y-3"></div>

        
    </div>

    <script>
        // --- VIEW MODAL SCRIPT ---
        const viewModal = document.getElementById('noticeModal');
        const viewModalPanel = document.getElementById('modalPanel');
        const viewModalBackdrop = document.getElementById('modalBackdrop');

        function viewNotice(notice) {
            document.getElementById('modalTitle').textContent = notice.title;
            document.getElementById('modalClass').textContent = notice.class_name + ' - ' + notice.section_name;
            document.getElementById('modalDate').textContent = new Date(notice.created_at).toLocaleString('en-US', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            document.getElementById('modalAuthor').textContent = notice.posted_by_name;
            document.getElementById('modalContent').innerHTML = notice.content.replace(/\n/g, '<br>'); // Display new lines correctly
            viewModal.classList.remove('hidden');
            setTimeout(() => { viewModal.classList.remove('opacity-0'); viewModalPanel.classList.remove('scale-95'); }, 10);
        }
        
        function closeModal() { 
            viewModal.classList.add('opacity-0'); 
            viewModalPanel.classList.add('scale-95'); 
            setTimeout(() => viewModal.classList.add('hidden'), 300); 
        }
        viewModalBackdrop.addEventListener('click', closeModal);

        // --- EDIT MODAL SCRIPT ---
        const editModal = document.getElementById('editFormModal');
        const editModalPanel = document.getElementById('editModalPanel');
        const editModalBackdrop = document.getElementById('editModalBackdrop');
        const editNoticeForm = document.getElementById('editNoticeForm');

        function openEditModal() {
            editModal.classList.remove('hidden');
            setTimeout(() => { editModal.classList.remove('opacity-0'); editModalPanel.classList.remove('scale-95'); }, 10);
        }

        function closeEditModal() {
            editModal.classList.add('opacity-0');
            editModalPanel.classList.add('scale-95');
            setTimeout(() => editModal.classList.add('hidden'), 300);
            editNoticeForm.reset(); // Reset form on close
        }
        editModalBackdrop.addEventListener('click', closeEditModal);


        function editNotice(notice) {
            document.getElementById('edit_notice_id').value = notice.id;
            document.getElementById('edit_class_id').value = notice.class_id;
            document.getElementById('edit_title').value = notice.title;
            document.getElementById('edit_content').value = notice.content;
            openEditModal(); // Open the edit modal
        }


        // --- DELETE CONFIRMATION ---
        function confirmDelete(id, title) {
            if (confirm(`Are you sure you want to delete the notice titled "${title}"?\nThis action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_notices.php'; // Ensure action points to admin page
                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'notice_id';
                hiddenId.value = id;
                form.appendChild(hiddenId);
                const hiddenAction = document.createElement('input');
                hiddenAction.type = 'hidden';
                hiddenAction.name = 'delete_notice';
                hiddenAction.value = '1';
                form.appendChild(hiddenAction);
                document.body.appendChild(form);
                form.submit();
            }
        }

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
            toast.className = `flex items-center w-full max-w-xs p-4 space-x-4 text-white ${bgColor} rounded-xl shadow-lg transform translate-x-full opacity-0 transition-all duration-300`;
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