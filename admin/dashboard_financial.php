<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["id"];
$admin_name = $_SESSION["full_name"];

// --- DATA FETCHING ---

// Helper function to get stats
function get_stat($link, $sql, $params = [], $types = "") {
    if ($stmt = mysqli_prepare($link, $sql)) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $value = mysqli_fetch_row($result)[0] ?? 0;
        mysqli_stmt_close($stmt);
        return $value;
    }
    return 0;
}

// 1. Get Stats for Cards
$current_year = date('Y');
$total_income_ytd = get_stat($link, "SELECT SUM(amount) FROM income WHERE YEAR(income_date) = ?", [$current_year], "i");
$total_expenses_ytd = get_stat($link, "SELECT SUM(amount) FROM expenses WHERE YEAR(expense_date) = ?", [$current_year], "i");
$net_balance_ytd = $total_income_ytd - $total_expenses_ytd;

$pending_fees_amount = get_stat($link, "SELECT SUM(amount_due - amount_paid) AS total_remaining FROM student_fees WHERE status IN ('Unpaid', 'Partially Paid')");
$total_library_books = get_stat($link, "SELECT SUM(total_copies) FROM books");
$available_library_books = get_stat($link, "SELECT SUM(available_copies) FROM books");
$total_active_vans = get_stat($link, "SELECT COUNT(id) FROM vans WHERE status = 'Active'");
$total_school_documents = get_stat($link, "SELECT COUNT(id) FROM school_documents WHERE is_active = 1");


// 2. Get Recent Financial Transactions (Income & Expenses)
$recent_transactions = [];
$sql_income = "SELECT 'Income' AS type, source AS description, amount, income_date AS date FROM income ORDER BY income_date DESC LIMIT 3";
$sql_expenses = "SELECT 'Expense' AS type, category AS description, amount, expense_date AS date FROM expenses ORDER BY expense_date DESC LIMIT 3";

if ($result = mysqli_query($link, $sql_income)) { while ($row = mysqli_fetch_assoc($result)) { $recent_transactions[] = $row; } }
if ($result = mysqli_query($link, $sql_expenses)) { while ($row = mysqli_fetch_assoc($result)) { $recent_transactions[] = $row; } }

usort($recent_transactions, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
$recent_transactions = array_slice($recent_transactions, 0, 5); // Top 5 overall


// 3. Library Books Overview (Top 5 most borrowed / newest)
$library_overview = [];
$sql_library_overview = "SELECT title, author, available_copies, total_copies FROM books ORDER BY (total_copies - available_copies) DESC, created_at DESC LIMIT 5";
if ($result = mysqli_query($link, $sql_library_overview)) {
    $library_overview = mysqli_fetch_all($result, MYSQLI_ASSOC);
}


// 4. Van Fleet Overview (Top 3 active/maintenance)
$van_fleet_overview = [];
$sql_vans = "SELECT van_number, status, driver_name, route_details
             FROM vans
             WHERE status = 'Active' OR status = 'Maintenance'
             ORDER BY van_number ASC LIMIT 3";
if ($result = mysqli_query($link, $sql_vans)) {
    $van_fleet_overview = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// 5. Recent Document Uploads
$recent_documents = [];
$sql_documents = "SELECT sd.title, sd.created_at, adm.full_name AS uploaded_by_admin_name
                  FROM school_documents sd
                  LEFT JOIN admins adm ON sd.uploaded_by_admin_id = adm.id
                  WHERE sd.is_active = 1
                  ORDER BY sd.created_at DESC LIMIT 5";
if ($result = mysqli_query($link, $sql_documents)) {
    $recent_documents = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

mysqli_close($link);

// --- PAGE INCLUDES ---
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Financial & Resources</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(-45deg, #20B2AA, #7FFFD4, #66CDAA, #008B8B); /* Admin Financial: LightSeaGreen, Aquamarine, MediumAquaMarine, DarkCyan */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .welcome-header { margin-bottom: 20px; color: #fff; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .welcome-header h1 { font-weight: 600; font-size: 2.2em; }
        .welcome-header p { font-size: 1.1em; opacity: 0.9; }
        .dashboard-switcher { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; }
        .dashboard-switcher a {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(5px);
            color: #fff; padding: 10px 15px; border-radius: 8px;
            text-decoration: none; font-weight: 600; font-size: 0.9em;
            transition: background 0.3s, transform 0.2s;
        }
        .dashboard-switcher a:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }

        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .card { 
            background: rgba(255, 255, 255, 0.2); 
            backdrop-filter: blur(10px);
            border-radius: 15px; 
            padding: 25px; 
            border: 1px solid rgba(255, 255, 255, 0.18);
            display: flex; 
            align-items: center; 
            color: #fff;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .card-icon { font-size: 2rem; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; }
        .card-icon.bg-teal { background: #20B2AA; } 
        .card-icon.bg-aquamarine { background: #7FFFD4; }
        .card-icon.bg-darkcyan { background: #008B8B; }
        .card-icon.bg-yellowgreen { background: #9ACD32; }
        .card-icon.bg-saddlebrown { background: #8B4513; }
        .card-icon.bg-crimson { background: #DC143C; }

        .card-content h3 { margin: 0; font-size: 1rem; opacity: 0.8; }
        .card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .action-btn { background: #fff; color: #1e2a4c; padding: 15px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .action-btn:hover { background: #1e2a4c; color: #fff; transform: translateY(-3px); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
        .main-panel { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #1e2a4c; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .list-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #f4f4f4; }
        .list-item:last-child { border-bottom: none; }
        .list-item-icon { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; font-size: 1.1rem; }
        .list-item-icon.bg-teal-dark { background: #008080; } 
        .list-item-icon.bg-aquamarine-dark { background: #66CDAA; }
        .list-item-icon.bg-darkcyan-dark { background: #006060; }
        .list-item-icon.bg-crimson-dark { background: #B22222; }

        .list-item-content h4 { margin: 0; color: #333; font-size: 1rem; }
        .list-item-content p { margin: 4px 0 0; color: #666; font-size: 0.9em; }
        .list-item-extra { margin-left: auto; text-align: right; color: #888; font-size: 0.9em; white-space: nowrap; flex-shrink: 0; padding-left: 15px; }
        .text-muted { color: #6c757d !important; }
        .badge { display: inline-block; padding: 0.3em 0.6em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.3rem; color: #fff; }
        .badge-success { background-color: #28a745; }
        .badge-danger { background-color: #dc3545; }
        .badge-warning { background-color: #ffc107; color: #212529; }
        .badge-info { background-color: #17a2b8; }
        .badge-primary { background-color: #007bff; }
        .badge-secondary { background-color: #6c757d; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <h1>Welcome, Admin <?php echo htmlspecialchars($admin_name); ?>!</h1>
        <p>Financial & Resource Management – Your strategic overview of school resources.</p>
        <div class="dashboard-switcher">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Switch to Main Overview Dashboard
            </a>
            <a href="hr_management.php">
                <i class="fas fa-arrow-left"></i> Switch to HR & Staff Management
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="card-grid">
        <div class="card"><div class="card-icon bg-teal"><i class="fas fa-money-bill-wave"></i></div><div class="card-content"><h3>Total Income (YTD)</h3><p>₹<?php echo number_format($total_income_ytd, 2); ?></p></div></div>
        <div class="card"><div class="card-icon bg-crimson"><i class="fas fa-money-bill-alt"></i></div><div class="card-content"><h3>Total Expenses (YTD)</h3><p>₹<?php echo number_format($total_expenses_ytd, 2); ?></p></div></div>
        <div class="card"><div class="card-icon bg-aquamarine"><i class="fas fa-balance-scale"></i></div><div class="card-content"><h3>Net Balance (YTD)</h3><p>₹<?php echo number_format($net_balance_ytd, 2); ?></p></div></div>
        <div class="card"><div class="card-icon bg-darkcyan"><i class="fas fa-file-invoice-dollar"></i></div><div class="card-content"><h3>Pending Fees</h3><p>₹<?php echo number_format($pending_fees_amount, 2); ?></p></div></div>
        <div class="card"><div class="card-icon bg-yellowgreen"><i class="fas fa-book"></i></div><div class="card-content"><h3>Total Library Books</h3><p><?php echo $total_library_books; ?></p></div></div>
        <div class="card"><div class="card-icon bg-saddlebrown"><i class="fas fa-check-circle"></i></div><div class="card-content"><h3>Available Books</h3><p><?php echo $available_library_books; ?></p></div></div>
        <div class="card"><div class="card-icon bg-teal"><i class="fas fa-bus"></i></div><div class="card-content"><h3>Active Vans</h3><p><?php echo $total_active_vans; ?></p></div></div>
        <div class="card"><div class="card-icon bg-darkcyan"><i class="fas fa-file-alt"></i></div><div class="card-content"><h3>School Documents</h3><p><?php echo $total_school_documents; ?></p></div></div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="manage_fees.php" class="action-btn"><i class="fas fa-coins"></i> Manage Fees</a>
        <a href="create_salary.php" class="action-btn"><i class="fas fa-hand-holding-usd"></i> Assign Staff Salary</a>
        <a href="manage_expenses.php" class="action-btn"><i class="fas fa-chart-line"></i> View Expenses</a>
        <a href="library_dashboard.php" class="action-btn"><i class="fas fa-book-reader"></i> Manage Library</a>
        <a href="view_vans.php" class="action-btn"><i class="fas fa-bus"></i> Manage Transport</a>
        <a href="admin_documents.php" class="action-btn"><i class="fas fa-file-alt"></i> Manage Documents</a>
        <a href="adfact.php" class="action-btn"><i class="fas fa-user-plus"></i> Admissions Settings</a>
        <a href="scman.php" class="action-btn"><i class="fas fa-globe"></i> Website Settings</a>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-exchange-alt"></i>Recent Financial Transactions (YTD)</h3>
            <?php if (empty($recent_transactions)): ?>
                <p class="text-muted text-center py-4">No recent income or expense records.</p>
            <?php else: foreach ($recent_transactions as $transaction): ?>
                <div class="list-item">
                    <div class="list-item-icon <?php echo ($transaction['type'] == 'Income' ? 'bg-aquamarine-dark' : 'bg-crimson-dark'); ?>"><i class="fas fa-<?php echo ($transaction['type'] == 'Income' ? 'plus-circle' : 'minus-circle'); ?>"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($transaction['description']); ?></h4>
                        <p><?php echo htmlspecialchars($transaction['type']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        ₹<?php echo number_format($transaction['amount'], 2); ?>
                        <br>
                        <span class="text-muted"><?php echo date("M j, Y", strtotime($transaction['date'])); ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-book-open"></i>Library Books Overview</h3>
            <?php if (empty($library_overview)): ?>
                <p class="text-muted text-center py-4">No books found in the library.</p>
            <?php else: foreach ($library_overview as $book): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-darkcyan-dark"><i class="fas fa-book"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                        <p>Author: <?php echo htmlspecialchars($book['author']); ?></p>
                    </div>
                    <div class="list-item-extra">
                        Available: <?php echo htmlspecialchars($book['available_copies']); ?>/<?php echo htmlspecialchars($book['total_copies']); ?>
                        <span class="badge badge-<?php echo ($book['available_copies'] > 0 ? 'success' : 'danger'); ?>"><?php echo ($book['available_copies'] > 0 ? 'Available' : 'Out of Stock'); ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        
        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-bus-alt"></i>Van Fleet Status</h3>
            <?php if (empty($van_fleet_overview)): ?>
                <p class="text-muted text-center py-4">No vans found.</p>
            <?php else: foreach ($van_fleet_overview as $van): ?>
                <div class="list-item">
                    <div class="list-item-icon bg-teal-dark"><i class="fas fa-truck"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($van['van_number']); ?></h4>
                        <p>Driver: <?php echo htmlspecialchars($van['driver_name'] ?: 'N/A'); ?></p>
                    </div>
                    <div class="list-item-extra">
                        Status: <span class="badge badge-<?php echo ($van['status'] == 'Active' ? 'success' : ($van['status'] == 'Maintenance' ? 'warning' : 'secondary')); ?>"><?php echo htmlspecialchars($van['status']); ?></span>
                        <br>
                        <span class="text-muted"><?php echo htmlspecialchars($van['route_details'] ?: 'No Route'); ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="main-panel">
            <h3 class="panel-header"><i class="fas fa-file-upload"></i>Recent Document Uploads</h3>
            <?php if (empty($recent_documents)): ?>
                <p class="text-muted text-center py-4">No recent school documents uploaded.</p>
            <?php else: foreach ($recent_documents as $doc): ?>
                <div class="list-item">
                     <div class="list-item-icon bg-darkcyan-dark"><i class="fas fa-file-contract"></i></div>
                    <div class="list-item-content">
                        <h4><?php echo htmlspecialchars($doc['title']); ?></h4>
                        <p>Uploaded By: <?php echo htmlspecialchars($doc['uploaded_by_admin_name'] ?: 'N/A'); ?></p>
                    </div>
                     <div class="list-item-extra"><?php echo date("M j, Y", strtotime($doc['created_at'])); ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
</body>
</html>

<?php
require_once './admin_footer.php';
?>