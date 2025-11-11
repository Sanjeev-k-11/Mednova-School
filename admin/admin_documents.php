<?php
session_start();
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["id"];

// --- HANDLE POST ACTIONS (CREATE, UPDATE, DELETE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- DELETE ACTION ---
    if (isset($_POST['delete_document'])) {
        // Your existing delete logic
    }
    // --- CREATE / UPDATE ACTION ---
    else {
        $doc_id = $_POST['doc_id'] ?? null;
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $file_url = $_POST['current_file_url'] ?? null;
        $file_type = $_POST['current_file_type'] ?? null;

        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == UPLOAD_ERR_OK) {
            $uploadResult = uploadToCloudinary($_FILES['document_file'], 'school_documents');

            if (isset($uploadResult['error'])) {
                $_SESSION['error_message'] = $uploadResult['error'];
                header("location: admin_documents.php");
                exit;
            } else {
                $file_url = $uploadResult['secure_url'];
                $file_type = $uploadResult['file_type'];
            }
        }

        if (empty($title) || empty($file_url)) {
            $_SESSION['error_message'] = "Title and a file are required.";
        } else {
            if ($doc_id) { // UPDATE
                $sql = "UPDATE school_documents SET title=?, description=?, file_url=?, file_type=?, is_active=? WHERE id=?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssssii", $title, $description, $file_url, $file_type, $is_active, $doc_id);
                    $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Document updated successfully." : "Error updating document.";
                }
            } else { // CREATE
                $sql = "INSERT INTO school_documents (title, description, file_url, file_type, uploaded_by_admin_id, is_active) VALUES (?, ?, ?, ?, ?, ?)";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssssii", $title, $description, $file_url, $file_type, $admin_id, $is_active);
                    $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Document uploaded successfully." : "Error uploading document.";
                }
            }
        }
    }
    header("location: admin_documents.php");
    exit;
}

// --- PAGINATION LOGIC ---
$docs_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $docs_per_page;

// Count total documents for pagination
$total_docs = 0;
$count_sql = "SELECT COUNT(*) FROM school_documents";
if ($count_result = mysqli_query($link, $count_sql)) {
    $row = mysqli_fetch_array($count_result);
    $total_docs = $row[0];
}

$total_pages = ceil($total_docs / $docs_per_page);

// Fetch documents for the current page
$documents = [];
$sql_docs = "SELECT * FROM school_documents ORDER BY created_at DESC LIMIT ? OFFSET ?";
if ($stmt_docs = mysqli_prepare($link, $sql_docs)) {
    mysqli_stmt_bind_param($stmt_docs, "ii", $docs_per_page, $offset);
    mysqli_stmt_execute($stmt_docs);
    $result_docs = mysqli_stmt_get_result($stmt_docs);
    $documents = mysqli_fetch_all($result_docs, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_docs);
}

// FIX: Initialize success and error messages from session to prevent undefined variable warnings.
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage School Documents</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans antialiased leading-normal tracking-wide">
    <div class="container mx-auto mt-28 max-w-7xl p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h1 class="text-3xl font-extrabold text-gray-900">Manage School Documents</h1>
            <button onclick="openModal()" class="bg-blue-600 text-white font-semibold py-2 px-5 rounded-lg shadow-md hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fas fa-upload"></i> Upload New Document
            </button>
        </div>

        <?php if ($success_message || $error_message): ?>
        <div class="mb-6 rounded-lg p-4 font-bold text-center <?php echo $success_message ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo $success_message ?: $error_message; ?>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-lg overflow-x-auto border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">File Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Uploaded On</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($documents)): ?>
                    <tr>
                        <td colspan="5" class="p-8 text-center text-gray-500">No documents have been uploaded yet.</td>
                    </tr>
                    <?php else: foreach ($documents as $doc): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($doc['title']); ?></td>
                            <td class="px-6 py-4 text-gray-600"><span class="font-semibold text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full"><?php echo htmlspecialchars($doc['file_type'] ?? 'N/A'); ?></span></td>
                            <td class="px-6 py-4 text-gray-600 text-sm"><?php echo date("M j, Y, g:i a", strtotime($doc['created_at'])); ?></td>
                            <td class="px-6 py-4 text-center">
                                <?php echo $doc['is_active'] ? '<span class="text-xs font-semibold py-1 px-3 rounded-full bg-green-100 text-green-800">Active</span>' : '<span class="text-xs font-semibold py-1 px-3 rounded-full bg-red-100 text-red-800">Inactive</span>'; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center items-center gap-2">
                                    <a href="<?php echo htmlspecialchars($doc['file_url']); ?>" target="_blank" class="w-9 h-9 flex items-center justify-center rounded-full bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="View Document">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    <button onclick='editDoc(<?php echo json_encode($doc, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="w-9 h-9 rounded-full bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition" title="Edit Document">
                                        <i class="fas fa-pencil-alt text-sm"></i>
                                    </button>
                                    <button onclick='confirmDelete(<?php echo $doc["id"]; ?>, "<?php echo htmlspecialchars(addslashes($doc["title"])); ?>")' class="w-9 h-9 rounded-full bg-red-100 text-red-700 hover:bg-red-200 transition" title="Delete Document">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col sm:flex-row items-center justify-between mt-6 p-4 bg-white rounded-xl shadow-lg border border-gray-200">
            <div>
                <p class="text-sm text-gray-600">
                    Showing
                    <span class="font-bold"><?php echo min($offset + 1, $total_docs); ?></span>
                    to
                    <span class="font-bold"><?php echo min($offset + $docs_per_page, $total_docs); ?></span>
                    of
                    <span class="font-bold"><?php echo $total_docs; ?></span>
                    records
                </p>
            </div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px mt-4 sm:mt-0" aria-label="Pagination">
                <a href="?page=<?php echo $current_page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?php echo $current_page <= 1 ? 'pointer-events-none opacity-50' : ''; ?>">
                    <span class="sr-only">Previous</span>
                    <i class="fas fa-chevron-left h-5 w-5"></i>
                </a>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $current_page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <a href="?page=<?php echo $current_page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?php echo $current_page >= $total_pages ? 'pointer-events-none opacity-50' : ''; ?>">
                    <span class="sr-only">Next</span>
                    <i class="fas fa-chevron-right h-5 w-5"></i>
                </a>
            </nav>
        </div>
    </div>
    
    <!-- Create/Edit Document Modal -->
    <div id="docModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4"><div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal()"></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-2xl w-full z-10">
                <form id="docForm" action="admin_documents.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="doc_id" id="doc_id">
                    <input type="hidden" name="current_file_url" id="current_file_url">
                    <input type="hidden" name="current_file_type" id="current_file_type">
                    <div class="px-6 py-4 border-b">
                        <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Upload New Document</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Document Title</label>
                            <input type="text" name="title" id="title" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg" required>
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" id="description" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg"></textarea>
                        </div>
                        <div>
                            <label for="document_file" class="block text-sm font-medium text-gray-700">File</label>
                            <input type="file" name="document_file" id="document_file" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p id="file-help-text" class="text-xs text-gray-500 mt-1">Select a new file. Required for new documents.</p>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="is_active" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" checked>
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">Active (Visible to Students)</label>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 flex justify-end gap-3">
                        <button type="button" onclick="closeModal()" class="bg-white py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Save Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('docModal');
        const form = document.getElementById('docForm');
        const modalTitle = document.getElementById('modalTitle');
        const fileInput = document.getElementById('document_file');
        const fileHelpText = document.getElementById('file-help-text');

        function openModal() {
            form.reset();
            document.getElementById('doc_id').value = '';
            modalTitle.textContent = 'Upload New Document';
            fileInput.required = true;
            fileHelpText.textContent = 'Select a file. This is required for new documents.';
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        function editDoc(data) {
            form.reset();
            modalTitle.textContent = 'Edit Document';
            document.getElementById('doc_id').value = data.id;
            document.getElementById('title').value = data.title;
            document.getElementById('description').value = data.description;
            document.getElementById('current_file_url').value = data.file_url;
            document.getElementById('current_file_type').value = data.file_type;
            document.getElementById('is_active').checked = data.is_active == 1;
            fileInput.required = false;
            fileHelpText.textContent = `Current file: ${data.file_url.split('/').pop()}. Select a new file to replace it.`;
            modal.classList.remove('hidden');
        }

        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete the document "${name}"? This will also delete the file from the server.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_documents.php';
                form.innerHTML = `<input type="hidden" name="doc_id" value="${id}"><input type="hidden" name="delete_document" value="1">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
<?php require_once './admin_footer.php'; ?>
