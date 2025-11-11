<?php
session_start();

// Redirect logic is perfect and remains the same
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $role_dashboard = '';
    switch ($_SESSION["role"]) {
        case 'Super Admin': $role_dashboard = './super_admin/dashboard.php'; break;
        case 'Admin': $role_dashboard = './admin/dashboard.php'; break;
        case 'Principle': $role_dashboard = './principle/dashboard.php'; break;
        case 'Teacher': $role_dashboard = './teacher/dashboard.php'; break;
        case 'Student': $role_dashboard = './student/dashboard.php'; break;
        case 'Staff': $role_dashboard = './staff/dashboard.php'; break;
        default: $role_dashboard = './dashboard.php';
    }
    header("location: " . $role_dashboard);
    exit;
}

require_once "./database/config.php";

// All backend login logic is excellent and remains the same
$username = $password = "";
$username_err = $password_err = $login_err = "";
$roles = [
    'Super Admin' => ['table' => 'super_admin', 'login_field' => 'super_admin_id', 'email_field' => 'email', 'id_field' => 'id', 'full_name_field' => 'full_name', 'welcome' => './super_admin/dashboard.php'],
    'Admin'       => ['table' => 'admins',      'login_field' => 'admin_id',       'email_field' => 'email', 'id_field' => 'id', 'full_name_field' => 'full_name', 'welcome' => './admin/dashboard.php'],
    'Principle'   => ['table' => 'principles',  'login_field' => 'principle_code', 'email_field' => 'email', 'id_field' => 'id', 'full_name_field' => 'full_name', 'welcome' => './principle/dashboard.php'],
    'Teacher'     => ['table' => 'teachers',    'login_field' => 'teacher_code',   'email_field' => 'email', 'id_field' => 'id', 'full_name_field' => 'full_name', 'welcome' => './teacher/dashboard.php'],
     'Student'     => ['table' => 'students',    'login_field' => 'registration_number', 'email_field' => 'email', 'id_field' => 'id', 'full_name_field' => "CONCAT_WS(' ', first_name, middle_name, last_name)", 'welcome' => './student/student_dashboard.php'],
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["username"]))) { $username_err = "Please enter your ID, code, or email."; } else { $username = trim($_POST["username"]); }
    if (empty(trim($_POST["password"]))) { $password_err = "Please enter your password."; } else { $password = trim($_POST["password"]); }

    if (empty($username_err) && empty($password_err)) {
        $authenticated = false;
        foreach ($roles as $role => $details) {
            if ($authenticated) break;
            $table = $details['table'];
            $sql = "SELECT {$details['id_field']} as session_id, {$details['full_name_field']} AS full_name, password FROM {$table} WHERE {$details['login_field']} = ? OR {$details['email_field']} = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ss", $username, $username);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        mysqli_stmt_bind_result($stmt, $id, $full_name, $hashed_password);
                        if (mysqli_stmt_fetch($stmt)) {
                            if (password_verify($password, $hashed_password)) {
                                session_regenerate_id();
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id"] = $id;
                                $_SESSION["full_name"] = $full_name;
                                $_SESSION["role"] = $role;

                                // Add role-specific session IDs
                                if($role == 'Super Admin') $_SESSION['super_admin_id'] = $id;
                                if($role == 'Admin') $_SESSION['admin_id'] = $id;
                                if($role == 'Principle' || $role == 'Teacher') $_SESSION['staff_id'] = $id;
                                if($role == 'Student') $_SESSION['student_id'] = $id;
                                
                                header("location: " . $details['welcome']);
                                $authenticated = true;
                                exit;
                            }
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
        $login_err = "Invalid credentials. Please try again.";
    }
    mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System Login</title>
    <!-- ENHANCED STYLES -->
    <style>
        @keyframes gradientAnimation { 
            0%{background-position:0% 50%} 
            50%{background-position:100% 50%} 
            100%{background-position:0% 50%} 
        }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            margin: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); 
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
        }
        .wrapper { 
            width: 90%;
            max-width: 400px;
            padding: 40px; 
            background: rgba(255, 255, 255, 0.15); 
            backdrop-filter: blur(15px); 
            -webkit-backdrop-filter: blur(15px);
            border-radius: 20px; 
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            text-align: center; 
            color: #fff; 
        }
        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 20px;
            border: 2px solid rgba(255,255,255,0.5);
        }
        h2 { 
            margin-bottom: 10px; 
            font-weight: 600; 
            color: #fff; 
        }
        p { 
            margin-bottom: 30px; 
            color: rgba(255,255,255,0.8); 
        }
        .form-group { 
            margin-bottom: 20px; 
            text-align: left; 
            position: relative;
        }
        .form-group .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.6);
            width: 20px;
            height: 20px;
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 500; 
            color: rgba(255,255,255,0.9); 
        }
        input[type="text"], input[type="password"] { 
            width: 100%; 
            padding: 12px 12px 12px 50px; /* Add padding for icon */
            border: 1px solid rgba(255,255,255,0.3); 
            border-radius: 8px; 
            font-size: 16px; 
            background-color: rgba(255,255,255,0.1); 
            color: #fff; 
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input::placeholder { color: rgba(255,255,255,0.5); }
        input:focus {
            outline: none;
            border-color: #fff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }
        .btn { 
            width: 100%; 
            padding: 12px; 
            background-color: #fff; 
            color: #6a82fb; 
            border: none; 
            border-radius: 8px; 
            font-size: 18px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease; 
        }
        .btn:hover { 
            background-color: #f0f0f0; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transform: translateY(-2px);
        }
        .help-block { 
            color: #f8d7da; 
            font-size: 0.9em; 
            margin-top: 5px; 
            display: block; 
        }
        .alert { 
            color: #721c24; 
            background-color: #f8d7da; 
            border: 1px solid #f5c6cb; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
        }
        /* ADDED FOR FORGOT PASSWORD LINK */
        .forgot-password-link {
            text-align: center;
            margin-top: -5px; /* Adjust spacing */
        }
        .forgot-password-link a {
            color: #fff;
            font-size: 0.9em;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .forgot-password-link a:hover {
            text-decoration: underline;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <img src="./uploads/Basic.png" alt="School Logo" class="logo">
        <h2>School Portal</h2>
        <p>Welcome back! Please login to your account.</p>

        <?php if (!empty($login_err)) echo '<div class="alert">' . $login_err . '</div>'; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="ID, Code, or Email" required autofocus>
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                <input type="password" name="password" id="password" placeholder="Password" required>
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="Log In">
            </div>
            
            <!-- FORGOT PASSWORD LINK ADDED HERE -->
            <div class="form-group forgot-password-link">
                <a href="forgot-password.php">Forgot Password?</a>
            </div>

        </form>
    </div>
</body>
</html>