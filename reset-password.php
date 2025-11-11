<?php
session_start();
require_once "./database/config.php";

// Gatekeeper: If the user hasn't started the reset process, send them away.
if (!isset($_SESSION['reset_email'])) {
    header("location: forgot-password.php");
    exit();
}
$email = $_SESSION['reset_email'];

// Initialize variables
$otp_err = $password_err = $confirm_password_err = $message = "";
$stage = 1; // Start at stage 1 (OTP verification)

// If the user has already verified their OTP, move them to stage 2.
if (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true) {
    $stage = 2;
}

// The roles array is needed to know which table to update later
$roles = [
    'Super Admin' => ['table' => 'super_admin'], 'Admin' => ['table' => 'admins'],
    'Principle'   => ['table' => 'principles'],  'Teacher' => ['table' => 'teachers'],
    'Student'     => ['table' => 'students'],
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // === STAGE 1: HANDLE OTP VERIFICATION ===
    if ($action === 'verify_otp') {
        if (empty(trim($_POST['otp']))) {
            $otp_err = "Please enter the OTP.";
        } else {
            $otp_submitted = trim($_POST['otp']);
            $sql = "SELECT token, user_role FROM password_resets WHERE email = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1";
            
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        mysqli_stmt_bind_result($stmt, $hashed_otp_db, $user_role);
                        mysqli_stmt_fetch($stmt);
                        
                        if (password_verify($otp_submitted, $hashed_otp_db)) {
                            // SUCCESS: OTP is correct. Set session flags to move to the next stage.
                            $_SESSION['otp_verified'] = true;
                            $_SESSION['reset_user_role'] = $user_role; // Save the role for the next step
                            $stage = 2; // Advance the stage
                        } else {
                            $otp_err = "The OTP you entered is incorrect.";
                        }
                    } else {
                        $otp_err = "The OTP is invalid or has expired. Please request a new one.";
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    // === STAGE 2: HANDLE NEW PASSWORD SUBMISSION ===
    elseif ($action === 'set_password' && $stage === 2) {
        if (empty(trim($_POST["password"]))) {
            $password_err = "Please enter a new password.";
        } elseif (strlen(trim($_POST["password"])) < 6) {
            $password_err = "Password must have at least 6 characters.";
        } else {
            $password = trim($_POST["password"]);
        }

        if (empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm the password.";
        } else {
            if (empty($password_err) && ($password !== trim($_POST["confirm_password"]))) {
                $confirm_password_err = "Passwords did not match.";
            }
        }
        
        if (empty($password_err) && empty($confirm_password_err)) {
            $new_password_hashed = password_hash($password, PASSWORD_DEFAULT);
            $user_role = $_SESSION['reset_user_role'];
            $table_to_update = $roles[$user_role]['table'];

            $sql_update = "UPDATE {$table_to_update} SET password = ? WHERE email = ?";
            if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                mysqli_stmt_bind_param($stmt_update, "ss", $new_password_hashed, $email);
                if (mysqli_stmt_execute($stmt_update)) {
                    // FINAL SUCCESS: Password updated. Clean up and show success message.
                    $sql_delete = "DELETE FROM password_resets WHERE email = ?";
                    if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
                        mysqli_stmt_bind_param($stmt_delete, "s", $email);
                        mysqli_stmt_execute($stmt_delete);
                        mysqli_stmt_close($stmt_delete);
                    }
                    // Unset all session variables used in the reset process
                    unset($_SESSION['reset_email'], $_SESSION['otp_verified'], $_SESSION['reset_user_role']);
                    
                    $message = '<div class="alert-success">Password reset successfully! You can now <a href="index.php">login with your new password</a>.</div>';
                } else {
                    $message = '<div class="alert">Oops! Something went wrong. Please try again later.</div>';
                }
                mysqli_stmt_close($stmt_update);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - School Portal</title>
    <!-- Paste the same enhanced styles from your other pages -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .wrapper { width: 90%; max-width: 400px; padding: 40px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border-radius: 20px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border: 1px solid rgba(255, 255, 255, 0.18); text-align: center; color: #fff; }
        h2 { margin-bottom: 10px; font-weight: 600; color: #fff; }
        p { margin-bottom: 30px; color: rgba(255,255,255,0.8); }
        .form-group { margin-bottom: 20px; text-align: left; position: relative; }
        .form-group .icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.6); width: 20px; height: 20px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px 12px 12px 50px; border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; font-size: 16px; background-color: rgba(255,255,255,0.1); color: #fff; box-sizing: border-box; transition: border-color 0.3s, box-shadow 0.3s; }
        input::placeholder { color: rgba(255,255,255,0.5); }
        input:focus { outline: none; border-color: #fff; box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2); }
        .btn { width: 100%; padding: 12px; background-color: #fff; color: #6a82fb; border: none; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn:hover { background-color: #f0f0f0; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transform: translateY(-2px); }
        .help-block { color: #f8d7da; font-size: 0.9em; margin-top: 5px; display: block; text-align: left; }
        .alert { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;}
        .alert-success a { color: #0c5460; font-weight: bold; text-decoration: none; }
        .alert-success a:hover { text-decoration: underline; }
        .login-link { margin-top: 20px; font-size: 0.9em; }
        .login-link a { color: #fff; text-decoration: none; font-weight: 600; transition: opacity 0.2s; }
        .login-link a:hover { text-decoration: underline; opacity: 0.8;}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php // Show final success message if it exists, otherwise show forms ?>
        <?php if (!empty($message)): ?>
            <h2>Process Complete</h2>
            <?php echo $message; ?>
        <?php else: ?>

            <?php // STAGE 1 FORM (VERIFY OTP) ?>
            <?php if ($stage === 1): ?>
                <h2>Verify Your Identity</h2>
                <p>A reset code was sent to <strong><?php echo htmlspecialchars($email); ?></strong>. Please enter it below.</p>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="form-group">
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 8V6a2 2 0 00-2-2h-4a2 2 0 00-2 2v2h-2a2 2 0 00-2 2v4a2 2 0 002 2h4a2 2 0 002-2v-4a2 2 0 00-2-2h-2V6h4v2h2zm-4 2H8v4h6v-4z" clip-rule="evenodd" /></svg>
                        <input type="text" name="otp" placeholder="Enter OTP Code" required autofocus>
                        <span class="help-block"><?php echo $otp_err; ?></span>
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn" value="Verify Code">
                    </div>
                </form>
                <div class="login-link">
                    <a href="forgot-password.php">Request a new code</a>
                </div>
            <?php endif; ?>

            <?php // STAGE 2 FORM (SET NEW PASSWORD) ?>
            <?php if ($stage === 2): ?>
                <h2>Create a New Password</h2>
                <p>Verification successful! Please set your new password.</p>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                    <input type="hidden" name="action" value="set_password">
                    <div class="form-group">
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2-2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                        <input type="password" name="password" placeholder="New Password" required autofocus>
                        <span class="help-block"><?php echo $password_err; ?></span>
                    </div>
                    <div class="form-group">
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2-2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                        <span class="help-block"><?php echo $confirm_password_err; ?></span>
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn" value="Reset Password">
                    </div>
                </form>
            <?php endif; ?>

        <?php endif; // End check for final success message ?>
    </div>
</body>
</html>