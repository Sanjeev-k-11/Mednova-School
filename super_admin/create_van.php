<?php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// Define variables and initialize with empty values
$van_number = $route_details = $driver_name = $khalasi_name = $status = "";
$errors = [];
$success_message = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Validate Van Number ---
    if (empty(trim($_POST["van_number"]))) {
        $errors[] = "Van Number is required.";
    } else {
        $van_number = trim($_POST["van_number"]);
        // Check if van number already exists
        $sql_check = "SELECT id FROM vans WHERE van_number = ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "s", $van_number);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $errors[] = "This Van Number already exists.";
            }
            mysqli_stmt_close($stmt_check);
        }
    }

    // --- Validate Status ---
    $allowed_statuses = ['Active', 'Inactive', 'Maintenance'];
    if (empty($_POST["status"]) || !in_array($_POST["status"], $allowed_statuses)) {
        $errors[] = "Please select a valid status.";
    } else {
        $status = $_POST["status"];
    }

    // --- Sanitize optional fields ---
    $route_details = trim($_POST["route_details"]);
    $driver_name = trim($_POST["driver_name"]);
    $khalasi_name = trim($_POST["khalasi_name"]);

    // --- If no errors, insert into database ---
    if (empty($errors)) {
        $sql = "INSERT INTO vans (van_number, route_details, driver_name, khalasi_name, status) VALUES (?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssss", $van_number, $route_details, $driver_name, $khalasi_name, $status);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "New van '" . htmlspecialchars($van_number) . "' created successfully!";
                // Clear form fields after success
                $van_number = $route_details = $driver_name = $khalasi_name = $status = "";
            } else {
                $errors[] = "Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
mysqli_close($link);

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Van</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 600px; margin: auto; margin-bottom: 100px; margin-top: 100px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 30px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        h2 { text-align: center; color: #1a2c5a; font-weight: 700; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(-45deg, #007bff, #00bfff, #8a2be2, #007bff); background-size: 400% 400%; animation: gradientAnimation 8s ease infinite; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
    </style>
</head>
<body>
<div class="container">
    <h2>Create New Van</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul><?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?></ul></div>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
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
            <input type="submit" class="btn" value="Create Van">
        </div>
    </form>
</div>
</body>
</html>
<?php require_once './admin_footer.php'; ?>