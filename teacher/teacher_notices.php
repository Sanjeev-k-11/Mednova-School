<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];
$teacher_name = $_SESSION["full_name"] ?? 'Teacher';

// =============================================================================
// MOVED THIS BLOCK UP - CRITICAL FIX
// Fetch the teacher's assigned classes immediately for authorization checks.
// =============================================================================
$teacher_classes = [];
$sql_classes = "SELECT c.id, c.class_name, c.section_name 
                FROM classes c 
                WHERE c.teacher_id = ? 
                UNION 
                SELECT c.id, c.class_name, c.section_name 
                FROM class_subject_teacher cst 
                JOIN classes c ON cst.class_id = c.id 
                WHERE cst.teacher_id = ? 
                ORDER BY class_name, section_name";
if ($stmt_classes = mysqli_prepare($link, $sql_classes)) {
    mysqli_stmt_bind_param($stmt_classes, "ii", $teacher_id, $teacher_id);
    mysqli_stmt_execute($stmt_classes);
    $result_classes = mysqli_stmt_get_result($stmt_classes);
    $teacher_classes = mysqli_fetch_all($result_classes, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_classes);
}
// =============================================================================

// --- HANDLE DELETE ACTION (with security check) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_notice'])) {
    $notice_id_to_delete = $_POST['notice_id'];
    // Security Check: Ensure the notice belongs to the logged-in teacher before deleting
    $sql_verify = "SELECT id FROM notices WHERE id = ? AND teacher_id = ?";
    if ($stmt_verify = mysqli_prepare($link, $sql_verify)) {
        mysqli_stmt_bind_param($stmt_verify, "ii", $notice_id_to_delete, $teacher_id);
        mysqli_stmt_execute($stmt_verify);
        mysqli_stmt_store_result($stmt_verify);
        if (mysqli_stmt_num_rows($stmt_verify) == 1) {
            $sql_delete = "DELETE FROM notices WHERE id = ?";
            if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
                mysqli_stmt_bind_param($stmt_delete, "i", $notice_id_to_delete);
                if (mysqli_stmt_execute($stmt_delete)) {
                    $_SESSION['success_message'] = "Notice deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Error deleting notice.";
                }
                mysqli_stmt_close($stmt_delete);
            }
        } else {
            $_SESSION['error_message'] = "Unauthorized action.";
        }
        mysqli_stmt_close($stmt_verify);
    }
    header("location: teacher_notices.php"); // Redirect to prevent form resubmission
    exit;
}

// --- HANDLE CREATE / UPDATE ACTION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['create_notice']) || isset($_POST['update_notice']))) {
    $class_id = trim($_POST['class_id']);
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $notice_id_to_update = $_POST['notice_id'] ?? null;

    if (empty($class_id) || empty($title) || empty($content)) {
        $_SESSION['error_message'] = "All fields are required.";
    } else {
        // The authorization check now works because $teacher_classes is populated
        $is_allowed = false;
        foreach ($teacher_classes as $class) {
            if ($class['id'] == $class_id) {
                $is_allowed = true;
                break;
            }
        }

        if ($is_allowed) {
            if (isset($_POST['update_notice']) && !empty($notice_id_to_update)) {
                // UPDATE logic (with security check)
                $sql_update = "UPDATE notices SET class_id = ?, title = ?, content = ? WHERE id = ? AND teacher_id = ?";
                if ($stmt = mysqli_prepare($link, $sql_update)) {
                    mysqli_stmt_bind_param($stmt, "issii", $class_id, $title, $content, $notice_id_to_update, $teacher_id);
                    if (mysqli_stmt_execute($stmt)) {
                        if (mysqli_stmt_affected_rows($stmt) > 0) {
                            $_SESSION['success_message'] = "Notice updated successfully!";
                        } else {
                            $_SESSION['success_message'] = "No changes were made.";
                        }
                    } else {
                        $_SESSION['error_message'] = "Error updating notice.";
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                // CREATE logic
                $sql_insert = "INSERT INTO notices (teacher_id, class_id, title, content, posted_by_name) VALUES (?, ?, ?, ?, ?)";
                if ($stmt = mysqli_prepare($link, $sql_insert)) {
                    mysqli_stmt_bind_param($stmt, "iisss", $teacher_id, $class_id, $title, $content, $teacher_name);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['success_message'] = "Notice posted successfully!";
                    } else {
                        $_SESSION['error_message'] = "Error posting notice.";
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        } else {
            $_SESSION['error_message'] = "You are not authorized to post to this class.";
        }
    }
    header("location: teacher_notices.php");
    exit;
}

// Flash messages
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

// --- FETCH DATA FOR DISPLAY ---
// $teacher_classes is already fetched from the top. We just need to fetch the notices for the table.
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;
$filter_class_id = $_GET['filter_class_id'] ?? '';

$where_clauses = ["n.teacher_id = ?"];
$params = [$teacher_id];
$types = "i";
if (!empty($filter_class_id) && is_numeric($filter_class_id)) {
    $where_clauses[] = "n.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Count total records for pagination
$sql_count = "SELECT COUNT(*) FROM notices n $where_sql";
$total_records = 0;
if ($stmt_count = mysqli_prepare($link, $sql_count)) {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
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
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";
if ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
    mysqli_stmt_bind_param($stmt_fetch, $types, ...$params);
    mysqli_stmt_execute($stmt_fetch);
    $result_notices = mysqli_stmt_get_result($stmt_fetch);
    $notices = mysqli_fetch_all($result_notices, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_fetch);
}

require_once './teacher_header.php';
?>
<!-- Your HTML and JavaScript code remains the same as it is correct. -->
<style>
    body { background: linear-gradient(-45deg, #1d2b64, #373b44, #4286f4, #292E49); background-size: 400% 400%; animation: gradientBG 25s ease infinite; }
    @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
</style>
<body class="text-white">
<div class="container mx-auto mt-28 p-4 md:p-8">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <h1 class="text-4xl font-bold tracking-tight">Messages & Notices</h1>
            <button id="createNoticeBtn" class="mt-4 md:mt-0 bg-blue-600 hover:bg-blue-700 font-bold py-2 px-5 rounded-full shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-300">
                <i class="fas fa-pen-alt mr-2"></i>Create New Notice
            </button>
        </div>

        <div id="noticeFormContainer" class="max-h-0 opacity-0 overflow-hidden transition-all duration-700 ease-in-out">
            <div class="bg-black/20 backdrop-blur-sm border border-white/10 rounded-2xl shadow-xl p-6 md:p-8 mb-8">
                <h2 id="formTitle" class="text-2xl font-semibold mb-5 border-b-2 border-white/10 pb-3">New Notice Details</h2>
                <form id="noticeForm" action="teacher_notices.php" method="post">
                    <input type="hidden" name="notice_id" id="notice_id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative"><i class="fas fa-users absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i><select id="class_id" name="class_id" required class="pl-10 h-11 w-full bg-black/30 border-white/20 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors"><option value="">-- Select a Class --</option><?php foreach ($teacher_classes as $class): ?><option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option><?php endforeach; ?></select></div>
                        <div class="relative"><i class="fas fa-heading absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="text" id="title" name="title" required placeholder="Notice Title" class="pl-10 h-11 w-full bg-black/30 border-white/20 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors"></div>
                    </div>
                    <div class="mt-6 relative"><i class="fas fa-paragraph absolute left-3 top-5 text-gray-400"></i><textarea id="content" name="content" rows="5" required placeholder="Type your message here..." class="pl-10 w-full bg-black/30 border-white/20 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors"></textarea></div>
                    <div class="mt-6 flex justify-end gap-4">
                        <button type="button" onclick="closeForm()" class="bg-gray-600/50 hover:bg-gray-500/50 font-bold py-2 px-6 rounded-full transition-colors">Cancel</button>
                        <button type="submit" name="create_notice" id="formSubmitBtn" class="bg-green-600 hover:bg-green-700 font-bold py-2 px-6 rounded-full shadow-md hover:shadow-lg transition-all">Post Notice</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-black/20 backdrop-blur-sm border border-white/10 rounded-2xl shadow-xl p-6 md:p-8">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4 pb-4 border-b border-white/10">
                <h2 class="text-2xl font-semibold">Posted Notices</h2>
                <form method="GET" class="mt-4 md:mt-0 flex items-center gap-2"><label for="filter_class_id" class="text-sm font-medium text-gray-300">Filter:</label><select name="filter_class_id" id="filter_class_id" onchange="this.form.submit()" class="bg-black/30 border-white/20 rounded-full shadow-sm h-11 text-sm focus:ring-blue-500 focus:border-blue-500 transition-colors"><option value="">All My Classes</option><?php foreach ($teacher_classes as $class): ?><option value="<?php echo $class['id']; ?>" <?php if ($filter_class_id == $class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option><?php endforeach; ?></select></form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="border-b-2 border-white/10"><tr><th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Class</th><th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Title</th><th class="p-4 h-11 text-left text-xs font-bold uppercase tracking-wider">Created By</th><th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Date Posted</th><th class="p-4 text-center text-xs font-bold uppercase tracking-wider">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($notices)): ?>
                            <tr><td colspan="5" class="p-12 text-center text-gray-400 italic">No notices found.</td></tr>
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
                                            <button onclick='editNotice(<?php echo json_encode($notice); ?>)' class="w-8 h-8 rounded-full bg-yellow-500/20 text-yellow-300 hover:bg-yellow-500/40 transition-all flex items-center justify-center" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                                            <button onclick='confirmDelete(<?php echo $notice["id"]; ?>, "<?php echo htmlspecialchars(addslashes($notice["title"])); ?>")' class="w-8 h-8 rounded-full bg-red-500/20 text-red-300 hover:bg-red-500/40 transition-all flex items-center justify-center" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex flex-col md:flex-row justify-between items-center text-sm"><div class="text-gray-400 mb-4 md:mb-0">Showing <b><?php echo min($offset + 1, $total_records); ?></b> to <b><?php echo min($offset + $records_per_page, $total_records); ?></b> of <b><?php echo $total_records; ?></b> records.</div><nav class="inline-flex rounded-lg shadow-sm -space-x-px"><a href="?page=<?php echo max(1, $current_page - 1); ?>&filter_class_id=<?php echo $filter_class_id; ?>" class="relative inline-flex items-center px-3 py-2 rounded-l-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if($current_page <= 1) echo 'text-gray-500 cursor-not-allowed'; ?>"><i class="fas fa-chevron-left"></i></a><?php for ($i = 1; $i <= $total_pages; $i++): ?><a href="?page=<?php echo $i; ?>&filter_class_id=<?php echo $filter_class_id; ?>" class="relative inline-flex items-center px-4 py-2 border-t border-b border-white/20 <?php echo $i == $current_page ? 'bg-blue-600 text-white font-bold' : 'bg-black/20 hover:bg-white/10'; ?>"><?php echo $i; ?></a><?php endfor; ?><a href="?page=<?php echo min($total_pages, $current_page + 1); ?>&filter_class_id=<?php echo $filter_class_id; ?>" class="relative inline-flex items-center px-3 py-2 rounded-r-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if($current_page >= $total_pages) echo 'text-gray-500 cursor-not-allowed'; ?>"><i class="fas fa-chevron-right"></i></a></nav></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="noticeModal" class="fixed z-50 inset-0 overflow-y-auto hidden opacity-0 transition-opacity duration-300"><div class="flex items-center justify-center min-h-screen px-4"><div id="modalBackdrop" class="fixed inset-0 bg-black bg-opacity-70 backdrop-blur-sm"></div><div id="modalPanel" class="bg-gray-800 border border-white/10 rounded-2xl overflow-hidden shadow-2xl transform transition-all duration-300 scale-95 sm:max-w-lg w-full z-10"><div class="px-6 py-4 border-b border-white/10"><h3 id="modalTitle" class="text-2xl leading-6 font-bold"></h3><div class="mt-2 text-sm text-gray-400 flex items-center space-x-2 flex-wrap"><span>For: <strong id="modalClass" class="text-gray-200"></strong></span><span>&bull;</span><span>By: <strong id="modalAuthor" class="text-gray-200"></strong></span><span>&bull;</span><span>Posted: <strong id="modalDate" class="text-gray-200"></strong></span></div></div><div class="p-6 text-gray-300 text-base leading-relaxed max-h-[60vh] overflow-y-auto"><p id="modalContent"></p></div><div class="bg-black/20 px-6 py-3 flex justify-end"><button onclick="closeModal()" type="button" class="px-6 py-2 bg-gray-700/70 border border-white/20 rounded-full font-semibold hover:bg-gray-600/70 transition-colors">Close</button></div></div></div></div>

<div id="toast-container" class="fixed top-5 right-5 z-50 space-y-3"></div>

<script>
    // --- FORM & MODAL SCRIPT ---
    const noticeFormContainer = document.getElementById('noticeFormContainer');
    const createNoticeBtn = document.getElementById('createNoticeBtn');
    const formTitle = document.getElementById('formTitle');
    const formSubmitBtn = document.getElementById('formSubmitBtn');
    const noticeForm = document.getElementById('noticeForm');
    const modal = document.getElementById('noticeModal');
    const modalPanel = document.getElementById('modalPanel');
    const modalBackdrop = document.getElementById('modalBackdrop');

    function openForm() { noticeFormContainer.classList.remove('max-h-0', 'opacity-0'); noticeFormContainer.style.maxHeight = noticeFormContainer.scrollHeight + "px"; createNoticeBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Close Form'; }
    function closeForm() { noticeFormContainer.style.maxHeight = '0px'; noticeFormContainer.classList.add('opacity-0'); createNoticeBtn.innerHTML = '<i class="fas fa-pen-alt mr-2"></i>Create New Notice'; resetForm(); }
    function resetForm() { noticeForm.reset(); document.getElementById('notice_id').value = ''; formTitle.textContent = 'New Notice Details'; formSubmitBtn.textContent = 'Post Notice'; formSubmitBtn.name = 'create_notice'; }
    
    createNoticeBtn.addEventListener('click', () => { if (noticeFormContainer.classList.contains('max-h-0')) { resetForm(); openForm(); } else { closeForm(); } });

    function editNotice(notice) {
        resetForm();
        formTitle.textContent = 'Edit Notice';
        document.getElementById('notice_id').value = notice.id;
        document.getElementById('class_id').value = notice.class_id;
        document.getElementById('title').value = notice.title;
        document.getElementById('content').value = notice.content;
        formSubmitBtn.textContent = 'Save Changes';
        formSubmitBtn.name = 'update_notice';
        openForm();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function viewNotice(notice) {
        document.getElementById('modalTitle').textContent = notice.title;
        document.getElementById('modalClass').textContent = notice.class_name + ' - ' + notice.section_name;
        document.getElementById('modalDate').textContent = new Date(notice.created_at).toLocaleString('en-US', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        document.getElementById('modalAuthor').textContent = notice.posted_by_name;
        document.getElementById('modalContent').innerHTML = notice.content.replace(/\n/g, '<br>'); // Display new lines correctly
        modal.classList.remove('hidden');
        setTimeout(() => { modal.classList.remove('opacity-0'); modalPanel.classList.remove('scale-95'); }, 10);
    }
    
    function closeModal() { modal.classList.add('opacity-0'); modalPanel.classList.add('scale-95'); setTimeout(() => modal.classList.add('hidden'), 300); }
    modalBackdrop.addEventListener('click', closeModal);

    function confirmDelete(id, title) {
        if (confirm(`Are you sure you want to delete the notice titled "${title}"?\nThis action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'teacher_notices.php';
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

    // --- TOAST NOTIFICATION SCRIPT ---
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toast-container');
        const bgColor = type === 'success' ? 'bg-green-600' : 'bg-red-600';
        const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-triangle"></i>';
        
        const toast = document.createElement('div');
        toast.className = `flex items-center w-full max-w-xs p-4 space-x-4 text-white ${bgColor} rounded-xl shadow-lg transform transition-all duration-300 translate-x-full opacity-0`;
        toast.innerHTML = `<div class="text-xl">${icon}</div><div class="pl-4 text-sm font-medium">${message}</div>`;
        
        toastContainer.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
        }, 100);

        // Animate out and remove after 5 seconds
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    <?php if ($success_message): ?>
        showToast('<?php echo addslashes($success_message); ?>', 'success');
    <?php elseif ($error_message): ?>
        showToast('<?php echo addslashes($error_message); ?>', 'error');
    <?php endif; ?>
</script>
</body>
<?php require_once './teacher_footer.php'; ?>