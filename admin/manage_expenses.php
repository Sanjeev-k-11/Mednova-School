<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & INITIALIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["id"];
$errors = [];
$form_data = ['id' => 0, 'category' => '', 'amount' => '', 'expense_date' => '', 'description' => ''];
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- HELPER FUNCTION FOR SUMMARY QUERIES ---
function get_summary_value($link, $sql, $params = [], $types = "") {
    $stmt = mysqli_prepare($link, $sql);
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $value = mysqli_fetch_array($result)[0] ?? 0;
    mysqli_stmt_close($stmt);
    return $value;
}

// --- HELPER FUNCTION FOR ADVANCED PAGINATION ---
function generate_pagination($current_page, $total_pages, $base_url, $window = 2) {
    if ($total_pages <= 1) return '';
    $html = '<nav class="flex items-center space-x-1">';
    $prev_link = ($current_page > 1) ? $base_url . 'page=' . ($current_page - 1) : '#';
    $prev_class = ($current_page > 1) ? 'hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    $html .= '<a href="' . $prev_link . '" class="px-3 py-2 text-sm leading-tight text-gray-600 bg-white border border-gray-300 rounded-l-lg ' . $prev_class . '">Previous</a>';
    $html .= '<ul class="flex items-center -space-x-px">';
    $start = max(1, $current_page - $window);
    $end = min($total_pages, $current_page + $window);
    if ($start > 1) {
        $html .= '<li><a href="' . $base_url . 'page=1" class="px-3 py-2 leading-tight text-gray-600 bg-white border border-gray-300 hover:bg-gray-100">1</a></li>';
        if ($start > 2) $html .= '<li><span class="px-3 py-2 leading-tight text-gray-600 bg-white border border-gray-300">...</span></li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $active_class = ($i == $current_page) ? 'z-10 text-white bg-indigo-600 border-indigo-600' : 'text-gray-600 bg-white hover:bg-gray-100';
        $html .= '<li><a href="' . $base_url . 'page=' . $i . '" class="px-3 py-2 leading-tight border border-gray-300 ' . $active_class . '">' . $i . '</a></li>';
    }
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) $html .= '<li><span class="px-3 py-2 leading-tight text-gray-600 bg-white border border-gray-300">...</span></li>';
        $html .= '<li><a href="' . $base_url . 'page=' . $total_pages . '" class="px-3 py-2 leading-tight text-gray-600 bg-white border border-gray-300 hover:bg-gray-100">' . $total_pages . '</a></li>';
    }
    $html .= '</ul>';
    $next_link = ($current_page < $total_pages) ? $base_url . 'page=' . ($current_page + 1) : '#';
    $next_class = ($current_page < $total_pages) ? 'hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    $html .= '<a href="' . $next_link . '" class="px-3 py-2 text-sm leading-tight text-gray-600 bg-white border border-gray-300 rounded-r-lg ' . $next_class . '">Next</a>';
    $html .= '</nav>';
    return $html;
}

// --- HANDLE POST REQUESTS (UNCHANGED) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_data = [ 'id' => (int)($_POST['id'] ?? 0), 'category' => trim($_POST['category'] ?? ''), 'amount' => (float)($_POST['amount'] ?? 0), 'expense_date' => $_POST['expense_date'] ?? '', 'description' => trim($_POST['description'] ?? '') ];
    if (empty($form_data['category'])) $errors[] = "Expense category is required.";
    if ($form_data['amount'] <= 0) $errors[] = "Amount must be a positive number.";
    if (empty($form_data['expense_date'])) $errors[] = "Expense date is required.";
    if (empty($errors)) {
        if ($form_data['id'] > 0) { $sql = "UPDATE expenses SET category=?, amount=?, expense_date=?, description=? WHERE id=?"; $stmt = mysqli_prepare($link, $sql); mysqli_stmt_bind_param($stmt, "sdssi", $form_data['category'], $form_data['amount'], $form_data['expense_date'], $form_data['description'], $form_data['id']); } else { $sql = "INSERT INTO expenses (category, amount, expense_date, description, added_by_admin_id) VALUES (?, ?, ?, ?, ?)"; $stmt = mysqli_prepare($link, $sql); mysqli_stmt_bind_param($stmt, "sdssi", $form_data['category'], $form_data['amount'], $form_data['expense_date'], $form_data['description'], $admin_id); }
        if (mysqli_stmt_execute($stmt)) { $_SESSION['message'] = "Expense record saved successfully."; $_SESSION['message_type'] = "success"; } else { $_SESSION['message'] = "Error saving record: " . mysqli_error($link); $_SESSION['message_type'] = "error"; }
        mysqli_stmt_close($stmt); header("location: manage_expenses.php"); exit;
    }
}

// --- HANDLE GET REQUESTS (UNCHANGED) ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $sql = "DELETE FROM expenses WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if(mysqli_stmt_execute($stmt)){ $_SESSION['message'] = "Expense record deleted."; $_SESSION['message_type'] = "success"; } else { $_SESSION['message'] = "Error deleting record."; $_SESSION['message_type'] = "error"; }
        mysqli_stmt_close($stmt);
    }
    header("location: manage_expenses.php"); exit;
}

// --- DATA FETCHING (UNCHANGED) ---
$total_expenses = get_summary_value($link, "SELECT SUM(amount) FROM expenses");
$monthly_expenses = get_summary_value($link, "SELECT SUM(amount) FROM expenses WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?", [date('Y'), date('m')], "is");
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 25;
$offset = ($page - 1) * $records_per_page;
$total_records = get_summary_value($link, "SELECT COUNT(*) FROM expenses");
$total_pages = ceil($total_records / $records_per_page);
$all_expenses = [];
$sql_fetch = "SELECT e.*, a.full_name as admin_name FROM expenses e JOIN admins a ON e.added_by_admin_id = a.id ORDER BY e.expense_date DESC LIMIT ? OFFSET ?";
if ($stmt = mysqli_prepare($link, $sql_fetch)) {
    mysqli_stmt_bind_param($stmt, "ii", $records_per_page, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) { $all_expenses[] = $row; }
    mysqli_stmt_close($stmt);
}

mysqli_close($link);
require_once './admin_header.php';
?>

<!-- ***** NEW STYLES FOR MODAL FORM ***** -->
<style>
    .form-input-underline {
        background-color: transparent;
        border: none;
        border-bottom: 1px solid #cbd5e1; /* slate-300 */
        border-radius: 0;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
        padding-left: 0.1rem;
        padding-right: 0.1rem;
        width: 100%;
        transition: border-color 0.2s ease-in-out;
    }
    .form-input-underline:focus {
        outline: none;
        border-bottom: 2px solid #4f46e5; /* indigo-600 */
        --tw-ring-shadow: 0 0 #0000; /* Removes default focus ring from Tailwind forms */
        box-shadow: none;
    }
    /* Custom placeholder for date input */
    input[type="date"]::before {
        content: attr(placeholder);
        color: #9ca3af; /* gray-400 */
        display: block;
    }
    input[type="date"]:focus::before,
    input[type="date"]:valid::before {
        display: none;
    }
</style>

<div class="container mx-auto mt-11 p-4 sm:p-6 lg:p-8">
    
    <!-- Page Header (Unchanged) -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-4 sm:mb-0">Manage School Expenses</h1>
        <button onclick="openModal()" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
            <i class="fas fa-plus mr-2"></i>Add New Expense
        </button>
    </div>

    <!-- Flash Messages & Summary Cards (Unchanged) -->
    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-lg flex items-center justify-between"><div><h4 class="text-sm font-semibold text-gray-500 uppercase">Expenses This Month</h4><p class="text-3xl font-bold text-red-600">₹<?php echo number_format($monthly_expenses, 2); ?></p></div><div class="bg-red-100 p-4 rounded-full"><i class="fas fa-calendar-day fa-2x text-red-500"></i></div></div>
        <div class="bg-white p-6 rounded-xl shadow-lg flex items-center justify-between"><div><h4 class="text-sm font-semibold text-gray-500 uppercase">Total Expenses All-Time</h4><p class="text-3xl font-bold text-red-600">₹<?php echo number_format($total_expenses, 2); ?></p></div><div class="bg-red-100 p-4 rounded-full"><i class="fas fa-chart-line fa-2x text-red-500"></i></div></div>
    </div>

    <!-- Table Container (Unchanged) -->
    <div class="bg-white shadow-xl rounded-lg overflow-hidden">
        <div class="overflow-x-auto"><table class="w-full min-w-full divide-y divide-gray-200"><thead class="bg-gray-100"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Category</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Amount</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Date</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider hidden md:table-cell">Description</th><th class="px-6 py-3 text-center text-xs font-medium text-gray-600 uppercase tracking-wider">Actions</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><?php if (empty($all_expenses)): ?><tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No expense records found.</td></tr><?php else: foreach ($all_expenses as $item): ?><tr><td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($item['category']); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-medium">₹<?php echo number_format($item['amount'], 2); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date("d M, Y", strtotime($item['expense_date'])); ?></td><td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate hidden md:table-cell" title="<?php echo htmlspecialchars($item['description']); ?>"><?php echo htmlspecialchars($item['description']); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center"><button onclick='editModal(<?php echo json_encode($item); ?>)' class="text-indigo-600 hover:text-indigo-900" title="Edit"><i class="fas fa-edit"></i></button><a href="?delete_id=<?php echo $item['id']; ?>" onclick="return confirm('Are you sure?');" class="text-red-600 hover:text-red-900 ml-4" title="Delete"><i class="fas fa-trash"></i></a></td></tr><?php endforeach; endif; ?></tbody></table></div>
        <?php if($total_records > 0): ?><div class="px-6 py-4 border-t flex flex-col md:flex-row justify-between items-center"><div class="text-sm text-gray-700 mb-4 md:mb-0">Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $records_per_page, $total_records); ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> records</div><?php echo generate_pagination($page, $total_pages, 'manage_expenses.php?'); ?></div><?php endif; ?>
    </div>
</div>

<!-- ***** ENHANCED Add/Edit Modal ***** -->
<div id="expenseModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 p-6 border w-full max-w-lg shadow-xl rounded-lg bg-white">
        <div class="flex justify-between items-center border-b border-gray-200 pb-4 mb-6">
            <h3 id="modalTitle" class="text-xl font-semibold text-gray-800">Add New Expense</h3>
            <button onclick="document.getElementById('expenseModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-3xl transition-colors">&times;</button>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="mb-4 p-3 rounded-lg bg-red-100 text-red-700 text-sm"><ul><?php foreach ($errors as $error) echo "<li>- $error</li>"; ?></ul></div>
        <?php endif; ?>
        
        <form id="expenseForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="id" id="modalId" value="<?php echo $form_data['id']; ?>">
            
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="modalCategory" class="block text-sm font-medium text-gray-600">Category</label>
                        <input type="text" name="category" id="modalCategory" value="<?php echo htmlspecialchars($form_data['category']); ?>" class="form-input-underline" required>
                    </div>
                    <div>
                        <label for="modalAmount" class="block text-sm font-medium text-gray-600">Amount (₹)</label>
                        <input type="number" name="amount" id="modalAmount" step="0.01" value="<?php echo htmlspecialchars($form_data['amount']); ?>" class="form-input-underline" required>
                    </div>
                </div>
                <div>
                     <label for="modalDate" class="block text-sm font-medium text-gray-600">Expense Date</label>
                     <input type="date" name="expense_date" id="modalDate" value="<?php echo htmlspecialchars($form_data['expense_date']); ?>" placeholder="dd-mm-yyyy" class="form-input-underline" required>
                </div>
                <div>
                    <label for="modalDescription" class="block text-sm font-medium text-gray-600">Description</label>
                    <textarea name="description" id="modalDescription" rows="3" class="form-input-underline"><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                </div>
            </div>
            
            <div class="flex justify-end pt-6 mt-6 border-t border-gray-200 space-x-3">
                <button type="button" onclick="document.getElementById('expenseModal').classList.add('hidden')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-lg transition-colors">Save Record</button>
            </div>
        </form>
    </div>
</div>


<!-- Script (Unchanged) -->
<script>
    const modal = document.getElementById('expenseModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalForm = document.getElementById('expenseForm');
    const modalId = document.getElementById('modalId');
    const modalCategory = document.getElementById('modalCategory');
    const modalAmount = document.getElementById('modalAmount');
    const modalDate = document.getElementById('modalDate');
    const modalDescription = document.getElementById('modalDescription');

    function openModal() {
        modalForm.reset();
        modalTitle.innerText = 'Add New Expense';
        modalId.value = '0';
        modal.classList.remove('hidden');
    }

    function editModal(expense) {
        modalForm.reset();
        modalTitle.innerText = 'Edit Expense Record';
        modalId.value = expense.id;
        modalCategory.value = expense.category;
        modalAmount.value = expense.amount;
        modalDate.value = expense.expense_date;
        modalDescription.value = expense.description;
        modal.classList.remove('hidden');
    }

    <?php if (!empty($errors)): ?>
        modal.classList.remove('hidden');
        <?php if ($form_data['id'] > 0): ?>
            modalTitle.innerText = 'Edit Expense Record';
        <?php endif; ?>
    <?php endif; ?>
</script>

<?php require_once './admin_footer.php'; ?>