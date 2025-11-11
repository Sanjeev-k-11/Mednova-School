<?php
session_start();
require_once "../database/config.php"; // Adjust path as needed

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

// Get user info from session
$user_id = $_SESSION["id"];
$user_role = $_SESSION["role"];

// Initialize variables for messages
$success_message = "";
$error_message = "";

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // --- VALIDATION ---
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } else {
        // --- Determine which table to query based on user's role ---
        $table_name = '';
        switch ($user_role) {
            case 'Student':
                $table_name = 'students';
                break;
            case 'Teacher':
                $table_name = 'teachers';
                break;
            case 'Admin':
                $table_name = 'admins';
                break;
            case 'Super Admin':
                $table_name = 'super_admin';
                break;
            // Add other roles like 'Principle' if they have their own tables
            default:
                $error_message = "Invalid user role.";
                break;
        }

        if ($table_name) {
            // 1. Fetch the current hashed password from the database
            $sql_get = "SELECT password FROM $table_name WHERE id = ?";
            if ($stmt_get = mysqli_prepare($link, $sql_get)) {
                mysqli_stmt_bind_param($stmt_get, "i", $user_id);
                mysqli_stmt_execute($stmt_get);
                mysqli_stmt_bind_result($stmt_get, $hashed_password_from_db);
                
                if (mysqli_stmt_fetch($stmt_get)) {
                    mysqli_stmt_close($stmt_get);

                    // 2. Verify the current password is correct
                    if (password_verify($current_password, $hashed_password_from_db)) {
                        
                        // 3. Hash the new password and update the database
                        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql_update = "UPDATE $table_name SET password = ? WHERE id = ?";
                        
                        if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                            mysqli_stmt_bind_param($stmt_update, "si", $hashed_new_password, $user_id);
                            if (mysqli_stmt_execute($stmt_update)) {
                                $success_message = "Your password has been updated successfully!";
                            } else {
                                $error_message = "Something went wrong. Please try again later.";
                            }
                            mysqli_stmt_close($stmt_update);
                        }
                    } else {
                        $error_message = "Incorrect current password.";
                    }
                } else {
                     $error_message = "User not found.";
                     mysqli_stmt_close($stmt_get);
                }
            }
        }
    }
}

// Include the appropriate header based on the user's role
require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-md p-4 sm:p-6">
    <div class="bg-white p-8 rounded-xl shadow-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Change Password</h1>
            <p class="text-gray-500 mt-2">Update your password for better security.</p>
        </div>

        <!-- Message Display -->
        <?php if (!empty($success_message)): ?>
            <div class="mb-6 rounded-lg p-4 font-bold text-center bg-green-100 text-green-700">
                <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="mb-6 rounded-lg p-4 font-bold text-center bg-red-100 text-red-700">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($success_message)): // Hide form on success ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="space-y-6">
                <!-- Current Password -->
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-lock text-gray-400"></i></div>
                        <input type="password" name="current_password" id="current_password" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>

                <!-- New Password -->
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-key text-gray-400"></i></div>
                        <input type="password" name="new_password" id="new_password" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Must be at least 8 characters long.</p>
                </div>

                <!-- Confirm New Password -->
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <div class="mt-1 relative">
                         <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-key text-gray-400"></i></div>
                        <input type="password" name="confirm_password" id="confirm_password" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Update Password
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
 <?php 
require_once './student_footer.php';
 ?>