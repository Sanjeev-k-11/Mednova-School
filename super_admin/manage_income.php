<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}
$admin_id = $_SESSION['super_admin_id'];
$edit_id = 0; $errors = [];
$source_form = $amount_form = $date_form = $desc_form = "";

// Handle Create/Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $source = trim($_POST['source']);
    $amount = (float)$_POST['amount'];
    $date = $_POST['income_date'];
    $desc = trim($_POST['description']);
    $edit_id = (int)$_POST['id'];

    if (empty($source)) $errors[] = "Income source is required.";
    if ($amount <= 0) $errors[] = "Amount must be a positive number.";
    if (empty($date)) $errors[] = "Income date is required.";

    if (empty($errors)) {
        if ($edit_id > 0) { // Update
            $sql = "UPDATE income SET source=?, amount=?, income_date=?, description=? WHERE id=?";
            if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "sdssi", $source, $amount, $date, $desc, $edit_id);
        } else { // Create
            $sql = "INSERT INTO income (source, amount, income_date, description, added_by_admin_id) VALUES (?, ?, ?, ?, ?)";
            if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "sdssi", $source, $amount, $date, $desc, $admin_id);
        }
        if(isset($stmt)){ mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
        $_SESSION['message'] = "Income record saved successfully."; $_SESSION['message_type'] = "success";
        header("location: manage_income.php"); exit;
    } else {
        $source_form = $source; $amount_form = $amount; $date_form = $date; $desc_form = $desc; $edit_id = $edit_id;
    }
}

// Handle GET requests
if(isset($_GET['delete_id'])){
    $delete_id = (int)$_GET['delete_id'];
    $sql = "DELETE FROM income WHERE id = ?";
    if($stmt = mysqli_prepare($link, $sql)){ mysqli_stmt_bind_param($stmt, "i", $delete_id); mysqli_stmt_execute($stmt); }
    $_SESSION['message'] = "Income record deleted."; $_SESSION['message_type'] = "success";
    header("location: manage_income.php"); exit;
}
if(isset($_GET['edit_id'])){
    $edit_id = (int)$_GET['edit_id'];
    $sql = "SELECT * FROM income WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $edit_id); mysqli_stmt_execute($stmt);
        if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $source_form = $row['source']; $amount_form = $row['amount'];
            $date_form = $row['income_date']; $desc_form = $row['description'];
        }
    }
}

// Fetch all income records and calculate totals
$all_income = [];
$total_income = 0;
$monthly_income = 0;
$current_month = date('m'); $current_year = date('Y');

$sql_fetch = "SELECT * FROM income ORDER BY income_date DESC";
if ($result = mysqli_query($link, $sql_fetch)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_income[] = $row;
        $total_income += $row['amount'];
        if (date('m', strtotime($row['income_date'])) == $current_month && date('Y', strtotime($row['income_date'])) == $current_year) {
            $monthly_income += $row['amount'];
        }
    }
}

mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Income</title>
    <!-- (Use your consistent admin CSS styles) -->
    <style>
        body { font-family: 'Segoe UI', sans-serif;   background-color: #f4f7f6; }
        .container { max-width: 1200px; margin: auto; margin-top: 100px; margin-bottom: 100px;}
        .form-container, .table-container { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .table-container { margin-top: 30px; }
        h2, h3 { color: #1e2a4c; }
        .summary-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .summary-card { background-color: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
        .summary-card h4 { margin: 0 0 10px 0; color: #6c757d; }
        .summary-card p { margin: 0; font-size: 1.5em; font-weight: 600; color: #28a745; }
        /* (Other styles for form, table, buttons can be reused) */
    </style>
</head>
<body>
<div class="container">
    <h2>Manage School Income</h2>
    <div class="form-container">
        <h3><?php echo ($edit_id > 0) ? 'Edit Income Record' : 'Add New Income'; ?></h3>
        <form action="manage_income.php" method="post">
            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
            <div class="form-grid">
                <div class="form-group"><label>Income Source</label><input type="text" name="source" value="<?php echo htmlspecialchars($source_form); ?>" required></div>
                <div class="form-group"><label>Amount (₹)</label><input type="number" name="amount" step="0.01" value="<?php echo htmlspecialchars($amount_form); ?>" required></div>
                <div class="form-group"><label>Income Date</label><input type="date" name="income_date" value="<?php echo htmlspecialchars($date_form); ?>" required></div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="3"><?php echo htmlspecialchars($desc_form); ?></textarea></div>
            <input type="submit" class="btn" value="<?php echo ($edit_id > 0) ? 'Update Record' : 'Add Record'; ?>">
        </form>
    </div>
    <div class="table-container">
        <h3>Income History</h3>
        <div class="summary-cards">
            <div class="summary-card"><h4>Income This Month</h4><p>₹<?php echo number_format($monthly_income, 2); ?></p></div>
            <div class="summary-card"><h4>Total Income Ever</h4><p>₹<?php echo number_format($total_income, 2); ?></p></div>
        </div>
        <table class="data-table">
            <thead><tr><th>Source</th><th>Amount</th><th>Date</th><th>Description</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($all_income as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['source']); ?></td>
                    <td>₹<?php echo number_format($item['amount'], 2); ?></td>
                    <td><?php echo date("M j, Y", strtotime($item['income_date'])); ?></td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td>
                        <a href="?edit_id=<?php echo $item['id']; ?>">Edit</a> | 
                        <a href="?delete_id=<?php echo $item['id']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php require_once './admin_footer.php'; ?>