<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php"); exit;
}

// --- PAGINATION, SEARCH, and FILTER LOGIC ---
$records_per_page = 25;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

// --- Build the WHERE and HAVING clauses for the query ---
$where_clauses = [];
$params = [];
$param_types = '';
$having_clause = '';

if (!empty($search_term)) {
    $where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.registration_number LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = &$search_like;
    $params[] = &$search_like;
    $params[] = &$search_like;
    $param_types .= 'sss';
}

if ($filter === 'due') {
    // We filter by balance > 0
    $having_clause = "HAVING balance > 0";
} elseif ($filter === 'paid') {
    // We filter by balance <= 0 OR if no fees are assigned (balance is NULL)
    $having_clause = "HAVING balance <= 0 OR balance IS NULL";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Query to get the TOTAL number of records for pagination
$total_records = 0;
$sql_count = "SELECT COUNT(*) as total FROM (
                SELECT s.id, (SUM(sf.amount_due) - SUM(sf.amount_paid)) as balance
                FROM students s
                LEFT JOIN student_fees sf ON s.id = sf.student_id
                $where_sql
                GROUP BY s.id
                $having_clause
              ) as subquery";

if ($stmt_count = mysqli_prepare($link, $sql_count)) {
    if (!empty($params)) {
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_count, $param_types], $params));
    }
    mysqli_stmt_execute($stmt_count);
    $total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
    mysqli_stmt_close($stmt_count);
}
$total_pages = ceil($total_records / $records_per_page);

// Main query to get students for the CURRENT page with their fee balance
$all_students = [];
$sql = "SELECT 
            s.id, s.first_name, s.last_name, s.registration_number, s.status, s.image_url,
            c.class_name, c.section_name,
            (SUM(sf.amount_due) - SUM(sf.amount_paid)) as balance
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_fees sf ON s.id = sf.student_id
        $where_sql
        GROUP BY s.id
        $having_clause
        ORDER BY s.first_name, s.last_name ASC
        LIMIT ?, ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    $limit_params = $params;
    $limit_params[] = &$offset;
    $limit_params[] = &$records_per_page;
    $limit_param_types = $param_types . 'ii';
    
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt, $limit_param_types], $limit_params));
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $all_students[] = $row;
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($link);

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1600px; margin: auto; margin-bottom: 100px; margin-top: 100px; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border: 1px solid rgba(255, 255, 255, 0.18); }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        h2 { color: #1e2a4c; font-weight: 600; margin: 0; }
        .btn-add { background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .table-container { overflow-x: auto; background-color: #fff; border-radius: 10px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
        .data-table thead th { background-color: #1e2a4c; color: white; white-space: nowrap; }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .status-badge { padding: 5px 10px; border-radius: 12px; color: white; font-size: 0.8em; font-weight: bold; }
        .status-active { background-color: #28a745; } .status-blocked { background-color: #dc3545; }
        .fee-status-paid { color: #28a745; font-weight: bold; }
        .fee-status-due { color: #dc3545; font-weight: bold; }
        .fee-status-due .due-amount { font-size: 0.9em; color: #555; display: block; }
        .btn-action { display: inline-block; padding: 8px 15px; margin-right: 5px; border-radius: 5px; color: white; text-decoration: none; font-size: 14px; font-weight: 500; border: none; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-view { background-color: #17a2b8; } .btn-edit { background-color: #ffc107; color: #212529; }
        .btn-block { background-color: #dc3545; } .btn-unblock { background-color: #28a745; }
        .filters-and-search { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 20px; }
        .filter-buttons .btn-filter { background-color: #fff; color: #555; border: 1px solid #ddd; padding: 8px 15px; border-radius: 5px; text-decoration: none; }
        .filter-buttons .btn-filter.active { background-color: #1e2a4c; color: white; }
        .search-form { display: flex; }
        .search-form input[type="search"] { width: 250px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px 0 0 5px; }
        .search-form button { padding: 8px 12px; border: 1px solid #1e2a4c; background-color: #1e2a4c; color: white; border-radius: 0 5px 5px 0; }
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 10px; color: #333; background-color: #fff; border-radius: 10px; }
        .pagination { list-style: none; padding: 0; margin: 0; display: flex; }
        .pagination li { margin: 0 2px; }
        .pagination a, .pagination span { display: block; padding: 8px 12px; text-decoration: none; border: 1px solid #ddd; border-radius: 5px; background-color: #fff; color: #333; }
        .pagination .active a, .pagination a:hover { background-color: #6a82fb; color: white; border-color: #6a82fb; }
        .pagination .disabled span { background-color: #f8f9fa; color: #ccc; }
        .pagination .ellipsis span { border: none; background: none; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 10px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        .modal-header h3 { margin: 0; color: #1a2c5a; }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .modal-body textarea { width: 100%; min-height: 100px; padding: 10px; border-radius: 5px; border: 1px solid #ccc; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-flex"><h2>Manage All Students</h2><a href="create_student.php" class="btn-add">+ Add New Student</a></div>
    <div class="filters-and-search">
        <div class="filter-buttons">
            <a href="?filter=all&search=<?php echo urlencode($search_term); ?>" class="btn-filter <?php echo ($filter === 'all') ? 'active' : ''; ?>">All Students</a>
            <a href="?filter=due&search=<?php echo urlencode($search_term); ?>" class="btn-filter <?php echo ($filter === 'due') ? 'active' : ''; ?>">Due Fees</a>
            <a href="?filter=paid&search=<?php echo urlencode($search_term); ?>" class="btn-filter <?php echo ($filter === 'paid') ? 'active' : ''; ?>">Paid Up</a>
        </div>
        <form action="" method="GET" class="search-form">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="search" name="search" placeholder="Search by Name or Reg No..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead><tr><th>Profile</th><th>Reg. No</th><th>Full Name</th><th>Class</th><th>Account Status</th><th>Fee Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($all_students)): ?>
                    <tr><td colspan="7" style="text-align:center; padding: 40px;">No students found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($all_students as $student): ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars($student['image_url'] ?? '../assets/images/default_avatar.png'); ?>" alt="Profile" class="profile-img"></td>
                            <td><?php echo htmlspecialchars($student['registration_number']); ?></td>
                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td><?php echo htmlspecialchars(($student['class_name'] ?? 'N/A') . ' - ' . ($student['section_name'] ?? 'N/A')); ?></td>
                            <td><span class="status-badge status-<?php echo strtolower($student['status']); ?>"><?php echo htmlspecialchars($student['status']); ?></span></td>
                            <td>
                                <?php
                                $balance = (float)($student['balance'] ?? 0);
                                if ($balance > 0) {
                                    echo '<div class="fee-status-due">Due<span class="due-amount">₹' . number_format($balance, 2) . '</span></div>';
                                } else {
                                    echo '<div class="fee-status-paid">Paid Up</div>';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="view_student_details.php?id=<?php echo $student['id']; ?>" class="btn-action btn-view">View</a>
                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn-action btn-edit">Edit</a>
                                <?php if ($student['status'] == 'Active'): ?>
                                    <button class="btn-action btn-block block-btn" data-studentid="<?php echo $student['id']; ?>">Block</button>
                                <?php else: ?>
                                    <button class="btn-action btn-unblock unblock-btn" data-studentid="<?php echo $student['id']; ?>">Unblock</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-container">
        <div>Showing <?php echo min($offset + 1, $total_records); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records.</div>
        <?php if ($total_pages > 1): ?>
        <ul class="pagination">
            <li class="<?php if($current_page <= 1) echo 'disabled'; ?>"><a href="<?php if($current_page > 1) echo '?page='.($current_page-1).'&filter='.$filter.'&search='.urlencode($search_term); else echo '#'; ?>">Previous</a></li>
            <?php 
                $start = max(1, $current_page - 1);
                $end = min($total_pages, $current_page + 1);
                if ($start > 1) { echo '<li><a href="?page=1&filter='.$filter.'&search='.urlencode($search_term).'">1</a></li>'; if ($start > 2) echo '<li class="ellipsis"><span>...</span></li>'; }
                for ($i = $start; $i <= $end; $i++) { echo '<li class="'.(($current_page == $i) ? 'active' : '').'"><a href="?page='.$i.'&filter='.$filter.'&search='.urlencode($search_term).'">'.$i.'</a></li>'; }
                if ($end < $total_pages) { if ($end < $total_pages - 1) echo '<li class="ellipsis"><span>...</span></li>'; echo '<li><a href="?page='.$total_pages.'&filter='.$filter.'&search='.urlencode($search_term).'">'.$total_pages.'</a></li>'; }
            ?>
            <li class="<?php if($current_page >= $total_pages) echo 'disabled'; ?>"><a href="<?php if($current_page < $total_pages) echo '?page='.($current_page+1).'&filter='.$filter.'&search='.urlencode($search_term); else echo '#'; ?>">Next</a></li>
        </ul>
        <?php endif; ?>
    </div>
</div>

<!-- MODALS ARE NOW INCLUDED -->
<div id="blockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reason for Blocking</h3>
            <span class="close-button" data-modal="blockModal">&times;</span>
        </div>
        <div class="modal-body">
            <form action="update_student_status.php" method="POST">
                <input type="hidden" name="student_id" id="modalBlockStudentId">
                <input type="hidden" name="new_status" value="Blocked">
                <div class="form-group">
                    <label for="block_reason">Please provide a reason:</label>
                    <textarea name="block_reason" id="block_reason" required></textarea>
                </div>
                <button type="submit" class="btn-action btn-block" style="width:100%;">Submit Block</button>
            </form>
        </div>
    </div>
</div>

<div id="unblockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reason for Unblocking</h3>
            <span class="close-button" data-modal="unblockModal">&times;</span>
        </div>
        <div class="modal-body">
            <form action="update_student_status.php" method="POST">
                <input type="hidden" name="student_id" id="modalUnblockStudentId">
                <input type="hidden" name="new_status" value="Active">
                <div class="form-group">
                    <label for="unblock_reason">Please provide a reason:</label>
                    <textarea name="unblock_reason" id="unblock_reason" required></textarea>
                </div>
                <button type="submit" class="btn-action btn-unblock" style="width:100%;">Submit Unblock</button>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT IS NOW INCLUDED -->
<script>
    const blockModal = document.getElementById('blockModal');
    const unblockModal = document.getElementById('unblockModal');
    const closeButtons = document.querySelectorAll('.close-button');
    const blockButtons = document.querySelectorAll('.block-btn');
    const unblockButtons = document.querySelectorAll('.unblock-btn');
    const modalBlockStudentIdInput = document.getElementById('modalBlockStudentId');
    const modalUnblockStudentIdInput = document.getElementById('modalUnblockStudentId');

    blockButtons.forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-studentid');
            modalBlockStudentIdInput.value = studentId;
            blockModal.style.display = 'block';
        });
    });

    unblockButtons.forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-studentid');
            modalUnblockStudentIdInput.value = studentId;
            unblockModal.style.display = 'block';
        });
    });

    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            document.getElementById(modalId).style.display = 'none';
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target == blockModal) blockModal.style.display = 'none';
        if (event.target == unblockModal) unblockModal.style.display = 'none';
    });
</script>

</body>
</html>
<?php require_once './admin_footer.php'; ?>