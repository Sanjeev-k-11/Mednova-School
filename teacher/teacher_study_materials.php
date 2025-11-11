<?php
session_start();
require_once "../database/config.php";
// FIXED: Path now points to a more standard helper location and name
require_once "../database/cloudinary_upload_handler.php"; 

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- (All backend PHP logic for GET, POST, DELETE, etc., is the same as your provided code) ---
// --- It has been condensed here for brevity but is included in the full final code block ---
$teacher_classes = []; $teacher_subjects = [];
$sql_classes = "SELECT DISTINCT c.id, c.class_name, c.section_name FROM class_subject_teacher cst JOIN classes c ON cst.class_id = c.id WHERE cst.teacher_id = ? ORDER BY c.class_name, c.section_name";
if ($stmt_c = mysqli_prepare($link, $sql_classes)) { mysqli_stmt_bind_param($stmt_c, "i", $teacher_id); mysqli_stmt_execute($stmt_c); $teacher_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_c), MYSQLI_ASSOC); mysqli_stmt_close($stmt_c); }
$sql_subjects = "SELECT DISTINCT s.id, s.subject_name FROM class_subject_teacher cst JOIN subjects s ON cst.subject_id = s.id WHERE cst.teacher_id = ? ORDER BY s.subject_name";
if ($stmt_s = mysqli_prepare($link, $sql_subjects)) { mysqli_stmt_bind_param($stmt_s, "i", $teacher_id); mysqli_stmt_execute($stmt_s); $teacher_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt_s), MYSQLI_ASSOC); mysqli_stmt_close($stmt_s); }
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_material'])) { $material_id = $_POST['material_id']; $sql_get_file = "SELECT public_id, resource_type FROM study_materials WHERE id = ? AND teacher_id = ?"; if ($stmt = mysqli_prepare($link, $sql_get_file)) { mysqli_stmt_bind_param($stmt, "ii", $material_id, $teacher_id); mysqli_stmt_execute($stmt); if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) { deleteFromCloudinary($row['public_id'], $row['resource_type']); $sql_delete = "DELETE FROM study_materials WHERE id = ?"; if ($stmt_del = mysqli_prepare($link, $sql_delete)) { mysqli_stmt_bind_param($stmt_del, "i", $material_id); if (mysqli_stmt_execute($stmt_del)) { $_SESSION['success_message'] = "Material deleted successfully!"; } else { $_SESSION['error_message'] = "Database error on delete."; } } } else { $_SESSION['error_message'] = "Unauthorized action or material not found."; } } header("Location: teacher_study_materials.php"); exit; }
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['upload_material']) || isset($_POST['update_material']))) { $class_id = trim($_POST['class_id']); $subject_id = trim($_POST['subject_id']); $title = trim($_POST['title']); $description = trim($_POST['description']); $material_id = $_POST['material_id'] ?? null; if (empty($class_id) || empty($subject_id) || empty($title)) { $_SESSION['error_message'] = "Class, Subject, and Title are required."; } else { $uploadResult = null; if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] != UPLOAD_ERR_NO_FILE) { $uploadResult = uploadToCloudinary($_FILES['material_file']); if (isset($uploadResult['error'])) { $_SESSION['error_message'] = $uploadResult['error']; header("Location: teacher_study_materials.php"); exit; } } if (isset($_POST['update_material']) && $material_id) { if ($uploadResult) { $sql_old_file = "SELECT public_id, resource_type FROM study_materials WHERE id = ? AND teacher_id = ?"; if ($stmt_old = mysqli_prepare($link, $sql_old_file)) { mysqli_stmt_bind_param($stmt_old, "ii", $material_id, $teacher_id); mysqli_stmt_execute($stmt_old); if($old_file = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_old))){ deleteFromCloudinary($old_file['public_id'], $old_file['resource_type']); } } $sql = "UPDATE study_materials SET class_id=?, subject_id=?, title=?, description=?, file_name=?, file_url=?, public_id=?, resource_type=?, file_type=?, file_size=? WHERE id=? AND teacher_id=?"; if ($stmt = mysqli_prepare($link, $sql)) { mysqli_stmt_bind_param($stmt, "iisssssssiii", $class_id, $subject_id, $title, $description, $_FILES['material_file']['name'], $uploadResult['secure_url'], $uploadResult['public_id'], $uploadResult['resource_type'], $_FILES['material_file']['type'], $_FILES['material_file']['size'], $material_id, $teacher_id); } } else { $sql = "UPDATE study_materials SET class_id=?, subject_id=?, title=?, description=? WHERE id=? AND teacher_id=?"; if ($stmt = mysqli_prepare($link, $sql)) { mysqli_stmt_bind_param($stmt, "iissii", $class_id, $subject_id, $title, $description, $material_id, $teacher_id); } } if (isset($stmt) && mysqli_stmt_execute($stmt)) { $_SESSION['success_message'] = "Material updated successfully!"; } else { $_SESSION['error_message'] = "Error updating material."; } } else { if (!$uploadResult) { $_SESSION['error_message'] = "A file is required for new materials."; } else { $sql = "INSERT INTO study_materials (teacher_id, class_id, subject_id, title, description, file_name, file_url, public_id, resource_type, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; if ($stmt = mysqli_prepare($link, $sql)) { mysqli_stmt_bind_param($stmt, "iiisssssssi", $teacher_id, $class_id, $subject_id, $title, $description, $_FILES['material_file']['name'], $uploadResult['secure_url'], $uploadResult['public_id'], $uploadResult['resource_type'], $_FILES['material_file']['type'], $_FILES['material_file']['size']); if (mysqli_stmt_execute($stmt)) { $_SESSION['success_message'] = "Material uploaded successfully to Cloudinary!"; } else { $_SESSION['error_message'] = "Error saving material details."; } } } } } header("Location: teacher_study_materials.php"); exit; }
$records_per_page = 10; $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1; $offset = ($current_page - 1) * $records_per_page; $filter_class = $_GET['filter_class_id'] ?? ''; $filter_subject = $_GET['filter_subject_id'] ?? ''; $where_clauses = ["sm.teacher_id = ?"]; $params = [$teacher_id]; $types = "i"; if (!empty($filter_class)) { $where_clauses[] = "sm.class_id = ?"; $params[] = $filter_class; $types .= "i"; } if (!empty($filter_subject)) { $where_clauses[] = "sm.subject_id = ?"; $params[] = $filter_subject; $types .= "i"; } $where_sql = "WHERE " . implode(" AND ", $where_clauses); $sql_count = "SELECT COUNT(*) FROM study_materials sm $where_sql"; $total_records = 0; if ($stmt_count = mysqli_prepare($link, $sql_count)) { mysqli_stmt_bind_param($stmt_count, $types, ...$params); mysqli_stmt_execute($stmt_count); mysqli_stmt_bind_result($stmt_count, $total_records); mysqli_stmt_fetch($stmt_count); mysqli_stmt_close($stmt_count); } $total_pages = ceil($total_records / $records_per_page); $materials = []; $sql_fetch = "SELECT sm.*, c.class_name, c.section_name, s.subject_name FROM study_materials sm JOIN classes c ON sm.class_id = c.id JOIN subjects s ON sm.subject_id = s.id $where_sql ORDER BY sm.uploaded_at DESC LIMIT ? OFFSET ?"; $params[] = $records_per_page; $params[] = $offset; $types .= "ii"; if ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) { mysqli_stmt_bind_param($stmt_fetch, $types, ...$params); mysqli_stmt_execute($stmt_fetch); $materials = mysqli_fetch_all(mysqli_stmt_get_result($stmt_fetch), MYSQLI_ASSOC); mysqli_stmt_close($stmt_fetch); }
$success_message = $_SESSION['success_message'] ?? null; unset($_SESSION['success_message']); $error_message = $_SESSION['error_message'] ?? null; unset($_SESSION['error_message']);

require_once './teacher_header.php';
?>
<!-- --- NEW: ENHANCED STYLES --- -->
<style>
    body {
        background: linear-gradient(-45deg, #1d2b64, #373b44, #4286f4, #292E49);
        background-size: 400% 400%;
        animation: gradientBG 25s ease infinite;
    }
    @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    .glass-card {
        background: rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
    }
    .form-input {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
    }
    .form-input:focus {
        outline: none;
        box-shadow: 0 0 0 2px #4286f4;
    }
</style>
<body class="text-white">
<div class="container mx-auto mt-28 p-4 md:p-8">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <h1 class="text-4xl font-bold tracking-tight">Study Materials</h1>
            <button id="uploadBtn" class="mt-4 md:mt-0 bg-blue-600 hover:bg-blue-700 font-bold py-2 px-5 rounded-full shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-300">
                <i class="fas fa-upload mr-2"></i>Upload Material
            </button>
        </div>

        <div id="materialFormContainer" class="max-h-0 opacity-0 overflow-hidden transition-all duration-700 ease-in-out">
            <div class="glass-card rounded-2xl p-6 md:p-8 mb-8">
                <h2 id="formTitle" class="text-2xl font-semibold mb-5 border-b-2 border-white/10 pb-3">Upload New Material</h2>
                <form id="materialForm" action="teacher_study_materials.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="material_id" id="material_id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label for="class_id" class="block text-sm font-medium text-gray-300 mb-1">Class</label><select id="class_id" name="class_id" required class="form-input h-11 w-full rounded-lg shadow-sm transition-colors"><option value="">-- Select --</option><?php foreach ($teacher_classes as $class): ?><option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option><?php endforeach; ?></select></div>
                        <div><label for="subject_id" class="block text-sm font-medium text-gray-300 mb-1">Subject</label><select id="subject_id" name="subject_id" required class="form-input h-11 w-full rounded-lg shadow-sm transition-colors"><option value="">-- Select --</option><?php foreach ($teacher_subjects as $subject): ?><option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mt-6"><label for="title" class="block text-sm font-medium text-gray-300 mb-1">Title</label><input type="text" id="title" name="title" required placeholder="e.g., Chapter 1 Notes" class="form-input w-full h-11 rounded-lg shadow-sm transition-colors"></div>
                    <div class="mt-6"><label for="description" class="block text-sm font-medium text-gray-300 mb-1">Description</label><textarea id="description" name="description" rows="3" placeholder="Brief description of the material" class="form-input w-full rounded-lg shadow-sm transition-colors"></textarea></div>
                    <div class="mt-6"><label for="material_file" class="block text-sm font-medium text-gray-300 mb-1">File</label><input type="file" id="material_file" name="material_file" class="w-full text-sm text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-white/10 file:text-white hover:file:bg-white/20 transition-colors"><p id="fileHelpText" class="mt-1 text-xs text-gray-400">Max 10MB. Allowed: PDF, DOC, PPT, Images.</p></div>
                    <div class="mt-8 flex justify-end gap-4">
                        <button type="button" onclick="closeForm()" class="bg-gray-600/50 hover:bg-gray-500/50 font-bold py-2 px-6 rounded-full transition-colors">Cancel</button>
                        <button type="submit" name="upload_material" id="formSubmitBtn" class="bg-blue-600 hover:bg-blue-700 font-bold py-2 px-6 rounded-full shadow-md hover:shadow-lg transition-all">Upload</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="glass-card rounded-2xl shadow-xl p-6 md:p-8">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4 pb-4 border-b border-white/10">
                <h2 class="text-2xl font-semibold">Uploaded Materials</h2>
                <form method="GET" class="mt-4 md:mt-0 flex flex-wrap items-center gap-4">
                    <select name="filter_class_id" onchange="this.form.submit()" class="form-input h-11 rounded-full text-sm transition-colors"><option value="">All Classes</option><?php foreach ($teacher_classes as $class): ?><option value="<?php echo $class['id']; ?>" <?php if ($filter_class == $class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option><?php endforeach; ?></select>
                    <select name="filter_subject_id" onchange="this.form.submit()" class="form-input h-11 rounded-full text-sm transition-colors"><option value="">All Subjects</option><?php foreach ($teacher_subjects as $subject): ?><option value="<?php echo $subject['id']; ?>" <?php if ($filter_subject == $subject['id']) echo 'selected'; ?>><?php echo htmlspecialchars($subject['subject_name']); ?></option><?php endforeach; ?></select>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="border-b-2 border-white/10"><tr><th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Title / Description</th><th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Class & Subject</th><th class="p-4 text-left text-xs font-bold uppercase tracking-wider">File Details</th><th class="p-4 text-left text-xs font-bold uppercase tracking-wider">Uploaded</th><th class="p-4 text-center text-xs font-bold uppercase tracking-wider">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($materials)): ?>
                            <tr><td colspan="5" class="p-12 text-center text-gray-400 italic">No materials found. Upload one to get started!</td></tr>
                        <?php else: ?>
                            <?php foreach ($materials as $material): ?>
                                <tr class="hover:bg-white/5 transition-colors duration-200 border-b border-white/5">
                                    <td class="p-4 align-top"><div class="font-medium"><?php echo htmlspecialchars($material['title']); ?></div><div class="text-xs text-gray-300 mt-1"><?php echo htmlspecialchars($material['description']); ?></div></td>
                                    <td class="p-4 align-top whitespace-nowrap text-sm"><div class="text-gray-200"><?php echo htmlspecialchars($material['class_name'] . ' - ' . $material['section_name']); ?></div><div class="text-gray-400"><?php echo htmlspecialchars($material['subject_name']); ?></div></td>
                                    <td class="p-4 align-top whitespace-nowrap text-sm"><div class="text-gray-200"><?php echo htmlspecialchars($material['file_name']); ?></div><div class="text-gray-400"><?php echo round($material['file_size'] / 1024, 1); ?> KB</div></td>
                                    <td class="p-4 align-top whitespace-nowrap text-sm text-gray-300"><?php echo date('d M, Y', strtotime($material['uploaded_at'])); ?></td>
                                    <td class="p-4 align-top whitespace-nowrap text-center text-sm font-medium">
                                        <div class="flex justify-center items-center space-x-2">
                                            <a href="<?php echo htmlspecialchars($material['file_url']); ?>" download="<?php echo htmlspecialchars($material['file_name']); ?>" target="_blank" class="w-8 h-8 rounded-full bg-green-500/20 text-green-300 hover:bg-green-500/40 transition-all flex items-center justify-center" title="Download">view<i class="fas fa-download"></i></a>
                                            <button onclick='editMaterial(<?php echo json_encode($material); ?>)' class="w-8 h-8 rounded-full bg-yellow-500/20 text-yellow-300 hover:bg-yellow-500/40 transition-all flex items-center justify-center" title="Edit">Edit<i class="fas fa-pencil-alt"></i></button>
                                            <button onclick='confirmDelete(<?php echo $material["id"]; ?>, "<?php echo htmlspecialchars(addslashes($material["title"])); ?>")' class="w-8 h-8 rounded-full bg-red-500/20 text-red-300 hover:bg-red-500/40 transition-all flex items-center justify-center" title="Delete" >Delete<i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
             <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex flex-col md:flex-row justify-between items-center text-sm"><div class="text-gray-400 mb-4 md:mb-0">Showing <b><?php echo min($offset + 1, $total_records); ?></b> to <b><?php echo min($offset + $records_per_page, $total_records); ?></b> of <b><?php echo $total_records; ?></b> records.</div><nav class="inline-flex rounded-lg shadow-sm -space-x-px"><a href="?page=<?php echo max(1, $current_page - 1); ?>&filter_class_id=<?php echo $filter_class; ?>&filter_subject_id=<?php echo $filter_subject; ?>" class="relative inline-flex items-center px-3 py-2 rounded-l-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if($current_page <= 1) echo 'text-gray-500 cursor-not-allowed'; ?>"><i class="fas fa-chevron-left"></i></a><?php for ($i = 1; $i <= $total_pages; $i++): ?><a href="?page=<?php echo $i; ?>&filter_class_id=<?php echo $filter_class; ?>&filter_subject_id=<?php echo $filter_subject; ?>" class="relative inline-flex items-center px-4 py-2 border-t border-b border-white/20 <?php echo $i == $current_page ? 'bg-blue-600 font-bold' : 'bg-black/20 hover:bg-white/10'; ?>"><?php echo $i; ?></a><?php endfor; ?><a href="?page=<?php echo min($total_pages, $current_page + 1); ?>&filter_class_id=<?php echo $filter_class; ?>&filter_subject_id=<?php echo $filter_subject; ?>" class="relative inline-flex items-center px-3 py-2 rounded-r-lg border border-white/20 bg-black/20 hover:bg-white/10 <?php if($current_page >= $total_pages) echo 'text-gray-500 cursor-not-allowed'; ?>"><i class="fas fa-chevron-right"></i></a></nav></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="toast-container" class="fixed top-5 right-5 z-50 space-y-3"></div>

<script>
    const formContainer = document.getElementById('materialFormContainer'); const uploadBtn = document.getElementById('uploadBtn'); const formTitle = document.getElementById('formTitle'); const formSubmitBtn = document.getElementById('formSubmitBtn'); const materialForm = document.getElementById('materialForm'); const fileHelpText = document.getElementById('fileHelpText');
    function openForm() { formContainer.classList.remove('max-h-0', 'opacity-0'); formContainer.style.maxHeight = formContainer.scrollHeight + "px"; uploadBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Close Form'; }
    function closeForm() { formContainer.style.maxHeight = '0px'; formContainer.classList.add('opacity-0'); uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload Material'; resetForm(); }
    function resetForm() { materialForm.reset(); document.getElementById('material_id').value = ''; formTitle.textContent = 'Upload New Material'; formSubmitBtn.textContent = 'Upload Material'; formSubmitBtn.name = 'upload_material'; fileHelpText.textContent = 'Max 10MB. Allowed: PDF, DOC, PPT, Images.'; document.getElementById('material_file').required = true; }
    uploadBtn.addEventListener('click', () => { if (formContainer.classList.contains('max-h-0')) { resetForm(); openForm(); } else { closeForm(); } });
    function editMaterial(material) { resetForm(); formTitle.textContent = 'Edit Study Material'; document.getElementById('material_id').value = material.id; document.getElementById('class_id').value = material.class_id; document.getElementById('subject_id').value = material.subject_id; document.getElementById('title').value = material.title; document.getElementById('description').value = material.description; formSubmitBtn.textContent = 'Save Changes'; formSubmitBtn.name = 'update_material'; fileHelpText.textContent = 'Leave file blank to keep the current one: ' + material.file_name; document.getElementById('material_file').required = false; openForm(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
    function confirmDelete(id, title) { if (confirm(`Are you sure you want to delete the material "${title}"?\nThis action cannot be undone.`)) { const form = document.createElement('form'); form.method = 'POST'; form.action = 'teacher_study_materials.php'; const idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'material_id'; idInput.value = id; form.appendChild(idInput); const actionInput = document.createElement('input'); actionInput.type = 'hidden'; actionInput.name = 'delete_material'; actionInput.value = '1'; form.appendChild(actionInput); document.body.appendChild(form); form.submit(); } }
    function showToast(message, type = 'success') { const container = document.getElementById('toast-container'); const bgColor = type === 'success' ? 'bg-green-600' : 'bg-red-600'; const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-triangle"></i>'; const toast = document.createElement('div'); toast.className = `flex items-center w-full max-w-xs p-4 space-x-4 text-white ${bgColor} rounded-xl shadow-lg transform transition-all duration-300 translate-x-full opacity-0`; toast.innerHTML = `<div class="text-xl">${icon}</div><div class="pl-4 text-sm font-medium">${message}</div>`; container.appendChild(toast); setTimeout(() => { toast.classList.remove('translate-x-full', 'opacity-0'); }, 100); setTimeout(() => { toast.classList.add('opacity-0', 'translate-x-full'); setTimeout(() => toast.remove(), 300); }, 5000); }
    <?php if ($success_message): ?> showToast('<?php echo addslashes($success_message); ?>', 'success'); <?php elseif ($error_message): ?> showToast('<?php echo addslashes($error_message); ?>', 'error'); <?php endif; ?>
</script>
</body>
<?php require_once './teacher_footer.php'; ?>