<?php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// --- Fetch All Van Data ---
$all_vans = [];
$sql = "SELECT * FROM vans ORDER BY van_number ASC";
if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_vans[] = $row;
    }
    mysqli_free_result($result);
} else {
    die("Error fetching van data: " . mysqli_error($link));
}
mysqli_close($link);

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Vans</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1200px; margin: auto; margin-bottom: 100px; margin-top: 100px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 30px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        h2 { color: #1a2c5a; font-weight: 700; margin: 0; }
        .btn-add { background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        .data-table thead th { background-color: #1a2c5a; color: white; font-weight: 600; }
        .data-table tbody tr:hover { background-color: #f1f1f1; }
        .status-badge { padding: 5px 10px; border-radius: 12px; color: white; font-size: 0.8em; font-weight: bold; text-transform: uppercase; }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #6c757d; }
        .status-maintenance { background-color: #ffc107; color: #212529; }
        .no-data-message { text-align: center; padding: 50px; font-size: 1.2em; color: #777; }
    </style>
</head>
<body>
<div class="container">
    <div class="header-flex">
        <h2>Manage All Vans</h2>
        <a href="create_van.php" class="btn-add">+ Add New Van</a>
    </div>
    
    <div class="table-container">
        <?php if (empty($all_vans)): ?>
            <p class="no-data-message">No vans have been added yet. <a href="create_van.php">Click here to add one.</a></p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Van Number</th>
                        <th>Route Details</th>
                        <th>Driver Name</th>
                        <th>Khalasi Name</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_vans as $van): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($van['van_number']); ?></td>
                            <td><?php echo htmlspecialchars($van['route_details'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($van['driver_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($van['khalasi_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                    $status_class = 'status-' . strtolower($van['status']);
                                    echo '<span class="status-badge ' . $status_class . '">' . htmlspecialchars($van['status']) . '</span>';
                                ?>
                            </td>
                            <td>
                                <!-- Add links for Edit/Delete functionality in the future -->
    <a href="edit_van.php?id=<?php echo $van['id']; ?>" class="btn-action btn-edit">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php require_once './admin_footer.php'; ?>