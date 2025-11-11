<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

// --- PAGINATION AND SEARCH LOGIC ---
$docs_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($current_page - 1) * $docs_per_page;

// Count total documents for pagination, considering search query
$total_docs = 0;
$count_sql = "SELECT COUNT(*) FROM school_documents WHERE is_active = 1";
if (!empty($search_query)) {
    $count_sql .= " AND title LIKE ?";
}
if ($stmt_count = mysqli_prepare($link, $count_sql)) {
    if (!empty($search_query)) {
        $param_search = "%" . $search_query . "%";
        mysqli_stmt_bind_param($stmt_count, "s", $param_search);
    }
    mysqli_stmt_execute($stmt_count);
    $count_result = mysqli_stmt_get_result($stmt_count);
    $row = mysqli_fetch_array($count_result);
    $total_docs = $row[0];
    mysqli_stmt_close($stmt_count);
}
$total_pages = ceil($total_docs / $docs_per_page);

// Fetch documents for the current page, considering search query
$documents = [];
$sql = "SELECT id, title, description, file_url, file_type, created_at FROM school_documents WHERE is_active = 1";
if (!empty($search_query)) {
    $sql .= " AND title LIKE ?";
}
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    if (!empty($search_query)) {
        $param_search = "%" . $search_query . "%";
        mysqli_stmt_bind_param($stmt, "sii", $param_search, $docs_per_page, $offset);
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $docs_per_page, $offset);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $documents = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// Helper function to get a Font Awesome icon class based on file type
function getFileIconClass($file_type) {
    $file_type = strtolower($file_type);
    switch ($file_type) {
        case 'pdf': return 'fas fa-file-pdf text-red-500';
        case 'docx': case 'doc': return 'fas fa-file-word text-blue-500';
        case 'xlsx': case 'xls': return 'fas fa-file-excel text-green-500';
        case 'png': case 'jpg': case 'jpeg': case 'gif': return 'fas fa-file-image text-purple-500';
        default: return 'fas fa-file-alt text-gray-500';
    }
}

require_once './student_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Documents</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans antialiased leading-normal tracking-wide">
    <div class="container mx-auto mt-28 max-w-5xl p-4 sm:p-6">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">School Documents</h1>
            <p class="text-gray-600 mt-2 text-lg">Download academic calendars, fee structures, and other important notices.</p>
        </div>

        <!-- Search Bar -->
        <form action="" method="GET" class="mb-8">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" name="search" placeholder="Search for a document..." value="<?php echo htmlspecialchars($search_query); ?>" class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm shadow-sm">
            </div>
        </form>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
            <div class="space-y-4 p-6">
                <?php if (empty($documents)): ?>
                    <div class="text-center py-10">
                        <i class="fas fa-folder-open text-5xl text-gray-400"></i>
                        <p class="mt-4 text-gray-500 text-lg">
                            <?php echo !empty($search_query) ? "No documents match your search term." : "No documents have been uploaded yet."; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="p-4 border border-gray-200 rounded-lg flex flex-col sm:flex-row items-start sm:items-center justify-between transition hover:bg-gray-50">
                            <div class="flex items-start sm:items-center gap-4 flex-grow">
                                <i class="<?php echo getFileIconClass($doc['file_type']); ?> text-3xl w-8 text-center flex-shrink-0 mt-1 sm:mt-0"></i>
                                <div>
                                    <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($doc['title']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($doc['description']); ?></p>
                                    <p class="text-xs text-gray-400 mt-2">Uploaded on: <?php echo date("F j, Y", strtotime($doc['created_at'])); ?></p>
                                </div>
                            </div>
                            <a href="<?php echo htmlspecialchars($doc['file_url']); ?>" target="_blank" class="flex-shrink-0 mt-4 sm:mt-0 ml-0 sm:ml-4 w-full sm:w-auto text-center bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-download"></i>
                                <span>Download</span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_docs > 0): ?>
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
                <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_query); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?php echo $current_page <= 1 ? 'pointer-events-none opacity-50' : ''; ?>">
                    <span class="sr-only">Previous</span>
                    <i class="fas fa-chevron-left h-5 w-5"></i>
                </a>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $current_page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_query); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?php echo $current_page >= $total_pages ? 'pointer-events-none opacity-50' : ''; ?>">
                    <span class="sr-only">Next</span>
                    <i class="fas fa-chevron-right h-5 w-5"></i>
                </a>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php require_once './student_footer.php'; ?>
