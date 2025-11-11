<?php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// Initialize variables
$van_number = $route_details = $driver_name = $khalasi_name = $status = "";
$id = 0;
$errors = [];

// --- Handle GET request to fetch data for the form ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        header("location: view_vans.php");
        exit;
    }

    $sql = "SELECT * FROM vans WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $van = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$van) {
            // Van not found, redirect
            $_SESSION['message'] = "Van not found.";
            $_SESSION['message_type'] = "danger";
            header("location: view_vans.php");
            exit;
        }

        // Populate variables for the form
        $van_number = $van['van_number'];
        $route_details = $van['route_details'];
        $driver_name = $van['driver_name'];
        $khalasi_name = $van['khalasi_name'];
        $status = $van['status'];
    }
}

// --- Handle POST request to update the data ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get hidden ID and other form data
    $id = (int)$_POST['id'];
    $van_number = trim($_POST["van_number"]);
    $route_details = trim($_POST["route_details"]);
    $driver_name = trim($_POST["driver_name"]);
    $khalasi_name = trim($_POST["khalasi_name"]);
    $status = $_POST["status"];

    // --- Validate Van Number ---
    if (empty($van_number)) {
        $errors[] = "Van Number is required.";
    } else {
        // Check if van number is taken by ANOTHER van
        $sql_check = "SELECT id FROM vans WHERE van_number = ? AND id != ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "si", $van_number, $id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $errors[] = "This Van Number is already in use by another van.";
            }
            mysqli_stmt_close($stmt_check);
        }
    }
    
    // Validate status
    if (!in_array($status, ['Active', 'Inactive', 'Maintenance'])) {
        $errors[] = "Invalid status selected.";
    }

    // --- If no errors, update the database ---
    if (empty($errors)) {
        $sql = "UPDATE vans SET van_number = ?, route_details = ?, driver_name = ?, khalasi_name = ?, status = ? WHERE id = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssi", $van_number, $route_details, $driver_name, $khalasi_name, $status, $id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "Van '" . htmlspecialchars($van_number) . "' updated successfully!";
                $_SESSION['message_type'] = "success";
                header("location: view_vans.php");
                exit;
            } else {
                $errors[] = "Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Van</title>
    <!-- Use the same styles as create_van.php for consistency -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 600px; margin: auto; margin-top: 100px; margin-bottom: 100px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 30px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        h2 { text-align: center; color: #1a2c5a; font-weight: 700; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(-45deg, #007bff, #00bfff, #8a2be2, #007bff); background-size: 400% 400%; animation: gradientAnimation 8s ease infinite; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Van Details</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul><?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?></ul></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <!-- Hidden input to store the van's ID -->
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <div class="form-group">
            <label for="van_number">Van Number <span style="color:red;">*</span></label>
            <input type="text" name="van_number" id="van_number" value="<?php echo htmlspecialchars($van_number); ?>" required>
        </div>
        <div class="form-group">
            <label for="route_details">Route Details</label>
            <textarea name="route_details" id="route_details" rows="3"><?php echo htmlspecialchars($route_details); ?></textarea>
        </div>
        <div class="form-group">
            <label for="driver_name">Driver Name</label>
            <input type="text" name="driver_name" id="driver_name" value="<?php echo htmlspecialchars($driver_name); ?>">
        </div>
        <div class="form-group">
            <label for="khalasi_name">Khalasi Name</label>
            <input type="text" name="khalasi_name" id="khalasi_name" value="<?php echo htmlspecialchars($khalasi_name); ?>">
        </div>
        <div class="form-group">
            <label for="status">Status <span style="color:red;">*</span></label>
            <select name="status" id="status" required>
                <option value="Active" <?php echo ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo ($status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                <option value="Maintenance" <?php echo ($status == 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
            </select>
        </div>
        <div class="form-group">
            <input type="submit" class="btn" value="Update Van">
        </div>
    </form>
</div>
</body>
</html>
<?php 
if (isset($link)) mysqli_close($link);
require_once './admin_footer.php'; 
?>