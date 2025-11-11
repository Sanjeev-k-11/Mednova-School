<?php
session_start();

// Check if the user is logged in and is a Super Admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

require_once "../database/config.php";
require_once '../database/cloudinary_upload_handler.php'; // Contains the uploadToCloudinary() function

// Initialize variables
$message = "";
$message_type = "";
$user_id = $_SESSION["id"];

// Fetch current user data first, we need it for the form and for the update logic
$stmt_fetch = mysqli_prepare($link, "SELECT full_name, role, super_admin_id, email, address, image_url, gender, created_at FROM super_admin WHERE id = ?");
mysqli_stmt_bind_param($stmt_fetch, "i", $user_id);
mysqli_stmt_execute($stmt_fetch);
$result = mysqli_stmt_get_result($stmt_fetch);
$profile_data = mysqli_fetch_assoc($result) ?? [];
mysqli_stmt_close($stmt_fetch);

// Handle form submission for profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $gender = trim($_POST['gender']);
    $super_admin_id = trim($_POST['super_admin_id']);

    // [IMPROVEMENT] Start with the existing image URL. It will be overwritten only on successful upload.
    $image_url_to_update = $profile_data['image_url']; 
    $upload_error = false;

    // Check if a new profile image was uploaded
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        // [FIX] Corrected function name from 'upload_to_cloudinary' to 'uploadToCloudinary'
        $upload_result = uploadToCloudinary($_FILES['profile_image'], 'super_admin_photos'); 
        
        if (isset($upload_result['secure_url'])) {
            $image_url_to_update = $upload_result['secure_url']; // Overwrite with new URL
        } else {
            $message = "Image upload failed: " . ($upload_result['error'] ?? 'Unknown error.');
            $message_type = "error";
            $upload_error = true; // Flag the error
        }
    }

    // [IMPROVEMENT] Only proceed with the database update if the image upload (if any) did not fail.
    if (!$upload_error) {
        $sql_update = "UPDATE super_admin SET full_name = ?, email = ?, address = ?, gender = ?, super_admin_id = ?, image_url = ? WHERE id = ?";
        
        if ($stmt_update = mysqli_prepare($link, $sql_update)) {
            mysqli_stmt_bind_param($stmt_update, "ssssssi", $full_name, $email, $address, $gender, $super_admin_id, $image_url_to_update, $user_id);
            
            if (mysqli_stmt_execute($stmt_update)) {
                // Update session variables for immediate feedback across the site
                $_SESSION['full_name'] = $full_name;
                $_SESSION['image_url'] = $image_url_to_update;
                
                $message = "Profile updated successfully!";
                $message_type = "success";

                // [IMPROVEMENT] Re-fetch data after successful update to show the changes immediately
                $stmt_refetch = mysqli_prepare($link, "SELECT * FROM super_admin WHERE id = ?");
                mysqli_stmt_bind_param($stmt_refetch, "i", $user_id);
                mysqli_stmt_execute($stmt_refetch);
                $result_refetch = mysqli_stmt_get_result($stmt_refetch);
                $profile_data = mysqli_fetch_assoc($result_refetch);
                mysqli_stmt_close($stmt_refetch);

            } else {
                $message = "Error updating profile details: " . mysqli_error($link);
                $message_type = "error";
            }
            mysqli_stmt_close($stmt_update);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Super Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- STYLES (Your existing styles are good, no changes needed here) -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;  background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .profile-container { max-width: 800px; margin: auto; margin-top: 100px; margin-bottom: 100px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 15px; padding: 40px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.3); }
        .profile-header { color: #1e2a4c; margin-bottom: 20px; text-align: center; }
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; background: #6a82fb; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 3em; margin: 0 auto 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-content { display: flex; flex-direction: column; align-items: center; }
        .profile-info { width: 100%; text-align: left; margin-top: 30px; display: grid; grid-template-columns: 1fr; gap: 10px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: 600; color: #1e2a4c; }
        .info-value { color: #555; text-align: right; }
        .action-buttons { display: flex; gap: 15px; margin-top: 30px; justify-content: center; }
        .action-button { padding: 10px 20px; color: #fff; text-decoration: none; border-radius: 8px; transition: background-color 0.3s, transform 0.2s; border: none; cursor: pointer; font-weight: 600; }
        .back-button { background-color: #6a82fb; }
        .back-button:hover { background-color: #5c6efc; transform: translateY(-2px); }
        .edit-button { background-color: #10B981; }
        .edit-button:hover { background-color: #0c9c6f; transform: translateY(-2px); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); justify-content: center; align-items: center; }
        .modal-content { background-color: #fff; margin: auto; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 90%; max-width: 500px; animation-name: animatetop; animation-duration: 0.4s; }
        @keyframes animatetop { from { top: -300px; opacity: 0; } to { top: 0; opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; color: #1e2a4c; }
        .close-button { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.3s; }
        .close-button:hover, .close-button:focus { color: #000; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; color: #333; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .form-group textarea { resize: vertical; }
        .form-actions { text-align: right; margin-top: 20px; }
        .form-actions button { background-color: #6a82fb; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s; }
        .form-actions button:hover { background-color: #5c6efc; }
        .message-box { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
        .message-box.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-box.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .image-preview-container { text-align: center; }
        .image-preview-circle { width: 120px; height: 120px; border: 2px dashed #ccc; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px; }
        .image-preview { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>
    <!-- PROFILE DISPLAY (No changes needed) -->
    <div class="profile-container">
        <!-- ... your existing profile display HTML ... -->
        <div class="profile-content">
            <div class="profile-avatar">
                <?php if (!empty($profile_data['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($profile_data['image_url']); ?>" alt="Profile Image">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="profile-header">
                <h1><?php echo htmlspecialchars($profile_data['full_name'] ?? 'N/A'); ?></h1>
                <p>Role: <?php echo htmlspecialchars($profile_data['role'] ?? 'N/A'); ?></p>
            </div>
            <?php if (!empty($message)): ?>
                <div class="message-box <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <div class="profile-info">
                <div class="info-row">
                    <span class="info-label">Super Admin ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($profile_data['super_admin_id']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($profile_data['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender:</span>
                    <span class="info-value"><?php echo htmlspecialchars($profile_data['gender'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars($profile_data['address'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Account Created:</span>
                    <span class="info-value"><?php echo date("F j, Y, g:i a", strtotime($profile_data['created_at'])); ?></span>
                </div>
            </div>
            <div class="action-buttons">
                <button class="action-button edit-button" onclick="document.getElementById('editProfileModal').style.display='flex'">
                    <i class="fas fa-edit"></i> Edit Profile
                </button>
                <a href="dashboard.php" class="action-button back-button">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- EDIT PROFILE MODAL (with improvements) -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Profile</h2>
                <span class="close-button" onclick="document.getElementById('editProfileModal').style.display='none'">&times;</span>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="form-group image-preview-container">
                    <label for="profile_image">Profile Image</label>
                    <div class="image-preview-circle">
                        <!-- [UX IMPROVEMENT] Show current image or a placeholder in the preview -->
                        <img id="image-preview" class="image-preview" 
                             src="<?php echo htmlspecialchars($profile_data['image_url'] ?? ''); ?>" 
                             alt="Image Preview" 
                             style="<?php echo empty($profile_data['image_url']) ? 'display:none;' : 'display:block;'; ?>">
                    </div>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*" onchange="previewImage(event)">
                </div>
                
                <!-- Other form fields -->
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile_data['full_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="super_admin_id">Super Admin ID</label>
                    <input type="text" id="super_admin_id" name="super_admin_id" value="<?php echo htmlspecialchars($profile_data['super_admin_id'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile_data['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="Male" <?php if (($profile_data['gender'] ?? '') == 'Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if (($profile_data['gender'] ?? '') == 'Female') echo 'selected'; ?>>Female</option>
                        <option value="Other" <?php if (($profile_data['gender'] ?? '') == 'Other') echo 'selected'; ?>>Other</option>
                        <option value="" <?php if (empty($profile_data['gender'])) echo 'selected'; ?>>Prefer not to say</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="4"><?php echo htmlspecialchars($profile_data['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript for image preview -->
    <script>
        function previewImage(event) {
            const reader = new FileReader();
            const output = document.getElementById('image-preview');
            reader.onload = function() {
                output.src = reader.result;
                output.style.display = 'block';
            };
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }
    </script>
</body>
</html>
<?php
require_once './admin_footer.php';
?>