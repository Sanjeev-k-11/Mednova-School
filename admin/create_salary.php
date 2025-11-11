<?php
// Start the session
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

// --- Fetch all staff for the dropdown list ---
$all_staff = [];
$sql_staff = "
    (SELECT id, principle_code AS staff_code, full_name, 'Principal' AS role FROM principles)
    UNION ALL
    (SELECT id, teacher_code AS staff_code, full_name, 'Teacher' AS role FROM teachers)
    ORDER BY full_name ASC
";
if ($result = mysqli_query($link, $sql_staff)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_staff[] = $row;
    }
    mysqli_free_result($result);
} else {
    // You might want to log this error instead of dying
    die("Error fetching staff data: " . mysqli_error($link));
}
mysqli_close($link);

// Include the header
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Staff Salary</title>
    <style>
        @keyframes gradientAnimation { 
            0%{background-position:0% 50%} 
            50%{background-position:100% 50%} 
            100%{background-position:0% 50%} 
        }

        body { 
            font-family: 'Segoe UI', sans-serif; 
           
            background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); 
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
        }

        .container { 
            max-width: 800px; 
            margin: auto; 
            margin-top: 100px; 
            margin-bottom: 100px;
            background: rgba(255, 255, 255, 0.25); 
            backdrop-filter: blur(10px); 
            -webkit-backdrop-filter: blur(10px);
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        h2 { 
            text-align: center; 
            color: #1e2a4c; 
            font-weight: 600; 
            margin-bottom: 30px; 
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #1e2a4c;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .form-control:focus {
            outline: none;
            border-color: #5c97fc;
            box-shadow: 0 0 5px rgba(92, 151, 252, 0.5);
        }

        .btn-submit {
            display: block;
            width: 100%;
            padding: 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-submit:hover {
            background-color: #218838;
        }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
        .alert-danger { color: #721c24; background-color: #f8d7da; }
        .help-block { color: #dc3545; font-size: 14px; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Create Staff Salary</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <form action="process_create_salary.php" method="post">
        <div class="form-group">
            <label for="staff_id">Staff Member</label>
            <select name="staff_id_role" id="staff_id" class="form-control" required>
                <option value="">Select Staff Member</option>
                <?php foreach ($all_staff as $staff): ?>
                    <option value="<?php echo htmlspecialchars($staff['id'] . '-' . $staff['role']); ?>">
                        <?php echo htmlspecialchars($staff['full_name']) . ' (' . htmlspecialchars($staff['role']) . ' - ' . htmlspecialchars($staff['staff_code']) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="salary_month">Salary Month</label>
            <input type="month" name="salary_month_input" id="salary_month" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="base_salary">Base Salary</label>
            <input type="number" name="base_salary" id="base_salary" class="form-control" step="0.01" required>
        </div>

        <div class="form-group">
            <label for="bonuses">Bonuses</label>
            <input type="number" name="bonuses" id="bonuses" class="form-control" step="0.01" value="0.00">
        </div>

        <div class="form-group">
            <label for="deductions">Deductions</label>
            <input type="number" name="deductions" id="deductions" class="form-control" step="0.01" value="0.00">
        </div>
        
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
        </div>

        <div class="form-group">
            <input type="submit" class="btn-submit" value="Create Salary Record">
        </div>
    </form>
</div>

</body>
</html>
<?php 
require_once './admin_footer.php';
?>