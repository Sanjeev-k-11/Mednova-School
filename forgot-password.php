<?php
session_start();
require_once "./database/config.php";
require_once "Mailer.php"; // Include our Mailer class

// Ensure mail configuration constants are available
if (!defined('OTP_LENGTH') || !defined('OTP_EXPIRY_MINUTES')) {
    // A fallback or a more graceful error is better for production
    die("Error: Mail configuration is not loaded correctly.");
}

$email = "";
$email_err = "";
$message = "";

// The roles array is essential for finding the user across different tables
$roles = [
    'Super Admin' => ['table' => 'super_admin', 'email_field' => 'email'],
    'Admin'       => ['table' => 'admins',      'email_field' => 'email'],
    'Principle'   => ['table' => 'principles',  'email_field' => 'email'],
    'Teacher'     => ['table' => 'teachers',    'email_field' => 'email'],
    'Student'     => ['table' => 'students',    'email_field' => 'email'],
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Standard email validation
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email address.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format.";
    } else {
        $email = trim($_POST["email"]);
    }

    if (empty($email_err)) {
        $user_found = false; 
        $user_role = '';
        foreach ($roles as $role => $details) {
            $sql = "SELECT {$details['email_field']} FROM {$details['table']} WHERE {$details['email_field']} = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        $user_found = true; 
                        $user_role = $role;
                        mysqli_stmt_close($stmt);
                        break; // User found, no need to check other tables
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }

        if ($user_found) {
            // Generate a secure numeric OTP
            $otp = implode('', array_map(fn() => random_int(0, 9), array_fill(0, OTP_LENGTH, null)));
            $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
            
            // SECURITY ENHANCEMENT: Use a prepared statement to delete old tokens
            $sql_delete = "DELETE FROM password_resets WHERE email = ?";
            if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
                mysqli_stmt_bind_param($stmt_delete, "s", $email);
                mysqli_stmt_execute($stmt_delete);
                mysqli_stmt_close($stmt_delete);
            }

            // Store the new hashed OTP in the database
            $sql_insert = "INSERT INTO password_resets (email, user_role, token, expires_at) VALUES (?, ?, ?, ?)";
            if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                mysqli_stmt_bind_param($stmt_insert, "ssss", $email, $user_role, $hashed_otp, $expires_at);
                if (mysqli_stmt_execute($stmt_insert)) {
                    // Send the email using our Mailer class
                    $mailer = new Mailer();
                    if ($mailer->sendPasswordResetOtp($email, $otp)) {
                        // Success! Store email in session and redirect to reset page
                        $_SESSION['reset_email'] = $email;
                        header("location: reset-password.php");
                        exit();
                    } else {
                        $message = '<div class="alert">Could not send the reset email due to a server error. Please contact support.</div>';
                    }
                }
                mysqli_stmt_close($stmt_insert);
            }
        } 
        
        // IMPORTANT SECURITY FEATURE: Always show a generic message.
        // This prevents "email enumeration," where an attacker could guess valid emails.
        $message = '<div class="alert-success">If an account with that email exists, a password reset code has been sent. Please check your inbox and spam folder.</div>';
    }
    mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - School Portal</title>
    <!-- ENHANCED STYLES - Copied from your login page for consistency -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .wrapper { width: 90%; max-width: 400px; padding: 40px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border-radius: 20px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border: 1px solid rgba(255, 255, 255, 0.18); text-align: center; color: #fff; }
        .logo { width: 80px; height: 80px; border-radius: 50%; margin-bottom: 20px; border: 2px solid rgba(255,255,255,0.5); }
        h2 { margin-bottom: 10px; font-weight: 600; color: #fff; }
        p { margin-bottom: 30px; color: rgba(255,255,255,0.8); }
        .form-group { margin-bottom: 20px; text-align: left; position: relative; }
        .form-group .icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.6); width: 20px; height: 20px; }
        input[type="email"] { width: 100%; padding: 12px 12px 12px 50px; border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; font-size: 16px; background-color: rgba(255,255,255,0.1); color: #fff; box-sizing: border-box; transition: border-color 0.3s, box-shadow 0.3s; }
        input::placeholder { color: rgba(255,255,255,0.5); }
        input:focus { outline: none; border-color: #fff; box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2); }
        .btn { width: 100%; padding: 12px; background-color: #fff; color: #6a82fb; border: none; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn:hover { background-color: #f0f0f0; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transform: translateY(-2px); }
        .help-block { color: #f8d7da; font-size: 0.9em; margin-top: 5px; display: block; text-align: left; }
        .alert { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;}
        .login-link { margin-top: 20px; font-size: 0.9em; }
        .login-link a { color: #fff; text-decoration: none; font-weight: 600; transition: opacity 0.2s; }
        .login-link a:hover { text-decoration: underline; opacity: 0.8;}
    </style>
</head>
<body>
    <div class="wrapper">
        <img src="./uploads//Basic.png" alt="School Logo" class="logo">
        <h2>Reset Password</h2>
        <p>Enter your email to receive a reset code.</p>
        
        <?php if (!empty($message)) echo $message; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
            <div class="form-group">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email address" required autofocus>
                <span class="help-block"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="Send Reset Code">
            </div>
        </form>
        <div class="login-link">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
</body>
</html>