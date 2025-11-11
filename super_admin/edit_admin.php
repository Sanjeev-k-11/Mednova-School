<?php
require_once '../database/config.php';
session_start();

// Ensure only Super Admin can access
if (!isset($_SESSION['super_admin_id'])) {
    die("Access denied. Please login as Super Admin.");
}

$message = "";
$message_type = "";
$admin_data = []; // To store existing admin data for pre-filling the form

// --- 1. Fetch Admin Data (for initial display) ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $admin_id = $_GET['id'];

    $sql_fetch = "SELECT admin_id, username, full_name, email, phone_number, address, gender, role, salary, qualification, join_date, dob, image_url 
                  FROM admins WHERE admin_id = ?";

    if ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $admin_id);
        mysqli_stmt_execute($stmt_fetch);
        $result_fetch = mysqli_stmt_get_result($stmt_fetch);

        if (mysqli_num_rows($result_fetch) == 1) {
            $admin_data = mysqli_fetch_assoc($result_fetch);
        } else {
            $message = "Admin not found.";
            $message_type = "error";
            // Redirect if admin not found
            header("Location: manage_admins.php?message=" . urlencode($message) . "&type=error");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        $message = "Error preparing fetch query: " . mysqli_error($link);
        $message_type = "error";
    }
} else {
    $message = "Invalid admin ID provided.";
    $message_type = "error";
    // Redirect if no ID or invalid ID
    header("Location: manage_admins.php?message=" . urlencode($message) . "&type=error");
    exit();
}


// --- 2. Handle Form Submission (Update Logic) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['admin_id'])) {
    // Re-validate admin_id from hidden input
    $admin_id = $_POST['admin_id'];

    $username     = $_POST['username'];
    $full_name    = $_POST['full_name'];
    $password_raw = $_POST['password']; // Potentially new password
    $email        = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $address      = $_POST['address'];
    $gender       = $_POST['gender'];
    $salary       = $_POST['salary'];
    $qualification= $_POST['qualification'];
    $join_date    = $_POST['join_date'];
    $dob          = $_POST['dob'];
    // Role remains 'Admin' and is not editable via form
    $role = 'Admin'; 

    $remove_image = isset($_POST['remove_current_image']) ? true : false;
    $current_image_url = $admin_data['image_url']; // Get existing image URL from the fetched data

    // Basic server-side validation for required fields
    if (empty($username) || empty($full_name) || empty($email)) {
        $message = "Please fill in all required fields (Username, Full Name, Email).";
        $message_type = "error";
    } else {
        $update_password_clause = "";
        $password_hash = null;
        if (!empty($password_raw)) {
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
            $update_password_clause = ", password = ?";
        }

        $new_image_url = $current_image_url; // Default to current image

        // --- Image Upload/Removal Logic ---
        if ($remove_image) {
            // Delete old image file if it exists
            if ($current_image_url && file_exists("../" . $current_image_url)) {
                unlink("../" . $current_image_url);
            }
            $new_image_url = null; // Set image_url in DB to NULL
        } elseif (!empty($_FILES["image"]["name"])) {
            $targetDir = "../uploads/admins/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $fileName = time() . "_" . basename($_FILES["image"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
            $allowedTypes = ["jpg","jpeg","png","gif"];

            if (in_array($fileType, $allowedTypes)) {
                if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
                    $message = "Sorry, your new image file is too large. Max 5MB allowed.";
                    $message_type = "error";
                } elseif (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                    // Delete old image file if a new one is successfully uploaded
                    if ($current_image_url && file_exists("../" . $current_image_url)) {
                        unlink("../" . $current_image_url);
                    }
                    $new_image_url = "uploads/admins/" . $fileName;
                } else {
                    $message = "New image upload failed. Error: " . $_FILES["image"]["error"];
                    $message_type = "error";
                }
            } else {
                $message = "Only JPG, JPEG, PNG, GIF files are allowed for the profile image.";
                $message_type = "error";
            }
        }

        // Only proceed with DB update if no image errors
        if ($message_type !== "error") {
            $sql_update = "UPDATE admins SET 
                            username = ?, 
                            full_name = ?, 
                            email = ?, 
                            phone_number = ?, 
                            address = ?, 
                            gender = ?, 
                            role = ?, 
                            salary = ?, 
                            qualification = ?, 
                            join_date = ?, 
                            dob = ?, 
                            image_url = ?" 
                            . $update_password_clause .
                            " WHERE admin_id = ?";

            if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                // Build bind_param types and variables dynamically based on password update
                $param_types = "sssssssdssss"; // For username to image_url
                $param_vars = [
                    $username, $full_name, $email, $phone_number, $address, 
                    $gender, $role, $salary, $qualification, $join_date, $dob, $new_image_url
                ];

                if ($update_password_clause) {
                    $param_types .= "s"; // Add 's' for password
                    array_splice($param_vars, 2, 0, [$password_hash]); // Insert password hash after full_name
                }
                $param_types .= "i"; // Add 'i' for admin_id at the end
                $param_vars[] = $admin_id;

                mysqli_stmt_bind_param($stmt_update, $param_types, ...$param_vars);

                if (mysqli_stmt_execute($stmt_update)) {
                    $message = "Admin '{$username}' updated successfully!";
                    $message_type = "success";
                    // Re-fetch data to show updated values in the form instantly
                    // If no new image was uploaded and no image was removed, $admin_data['image_url'] could be stale
                    // So we update it manually or refetch. Let's update it manually for simplicity here.
                    $admin_data['username'] = $username;
                    $admin_data['full_name'] = $full_name;
                    $admin_data['email'] = $email;
                    $admin_data['phone_number'] = $phone_number;
                    $admin_data['address'] = $address;
                    $admin_data['gender'] = $gender;
                    $admin_data['salary'] = $salary;
                    $admin_data['qualification'] = $qualification;
                    $admin_data['join_date'] = $join_date;
                    $admin_data['dob'] = $dob;
                    $admin_data['image_url'] = $new_image_url; // Update to reflect new/removed image
                    // The password field in the form will remain empty
                } else {
                    $message = "Error updating admin: " . mysqli_stmt_error($stmt_update);
                    $message_type = "error";
                }
                mysqli_stmt_close($stmt_update);
            } else {
                $message = "Error preparing update query: " . mysqli_error($link);
                $message_type = "error";
            }
        }
    }
}

// If there was an error during POST, and admin_data wasn't fully refilled, 
// re-populate from $_POST to keep user's entered data.
// Note: Password field will be reset for security on error.
if ($_SERVER["REQUEST_METHOD"] == "POST" && $message_type === "error") {
    $admin_data = array_merge($admin_data, $_POST);
    // Ensure image_url reverts to original if there was an image upload error
    if (!isset($new_image_url)) { // If image upload failed and $new_image_url was never set
        $admin_data['image_url'] = $current_image_url;
    }
}

mysqli_close($link);
?>

<?php require_once './admin_header.php'; ?>

<!-- Custom Styles for this page (from create_admin.php, ensure consistency) -->
<style>
    /* Most styles are shared with create_admin.php */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f7fa;
        color: #333;
        margin: 0;
        padding: 0;
    }

    .page-content-wrapper {
        padding: 20px;
        max-width: 900px;
        margin: 20px auto;
        background-color: #ffffff;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .form-card {
        padding: 30px;
    }

    .form-card h2 {
        text-align: center;
        color: #007bff;
        margin-bottom: 30px;
        font-size: 2.2em;
        font-weight: 600;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 15px;
    }

    .message {
        padding: 12px 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        font-weight: bold;
        text-align: center;
        opacity: 0.95;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .message.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .form-section {
        margin-bottom: 30px;
        padding: 20px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        background-color: #fcfcfc;
    }

    .form-section-title {
        color: #0056b3;
        font-size: 1.5em;
        margin-bottom: 20px;
        border-bottom: 1px dashed #e0e0e0;
        padding-bottom: 10px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }

    @media (min-width: 768px) {
        .form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .form-grid.single-column {
            grid-template-columns: 1fr;
        }
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #555;
    }

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="password"],
    .form-group input[type="number"],
    .form-group input[type="date"],
    .form-group select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ced4da;
        border-radius: 5px;
        font-size: 1em;
        box-sizing: border-box;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .form-group input[type="file"] {
        padding: 8px;
        border: 1px solid #ced4da;
        border-radius: 5px;
        background-color: #f8f9fa;
        cursor: pointer;
    }

    .form-group input[type="text"]:focus,
    .form-group input[type="email"]:focus,
    .form-group input[type="password"]:focus,
    .form-group input[type="number"]:focus,
    .form-group input[type="date"]:focus,
    .form-group select:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        outline: none;
    }

    .form-group .static-value {
        padding: 12px;
        border: 1px solid #e0e0e0;
        background-color: #f0f0f0;
        border-radius: 5px;
        font-size: 1em;
        color: #555;
        display: block;
        width: 100%;
        box-sizing: border-box;
    }

    /* Image Preview Specific Styles */
    .current-image-preview {
        display: flex;
        align-items: center;
        margin-top: 10px;
        gap: 15px;
        padding: 10px;
        border: 1px solid #e9ecef;
        border-radius: 5px;
        background-color: #f8f9fa;
    }
    .current-image-preview img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #ddd;
    }
    .current-image-preview span {
        font-weight: 500;
        color: #555;
    }
    .remove-image-checkbox {
        margin-top: 10px;
        display: flex;
        align-items: center;
    }
    .remove-image-checkbox input {
        margin-right: 8px;
    }
    .remove-image-checkbox label {
        font-weight: normal;
        margin-bottom: 0;
        color: #6c757d;
        cursor: pointer;
    }


    /* Buttons */
    .button-group {
        text-align: center;
        margin-top: 30px;
        border-top: 1px solid #e9ecef;
        padding-top: 25px;
    }

    .btn-submit, .btn-secondary {
        display: inline-block;
        padding: 12px 25px;
        border-radius: 5px;
        font-size: 1.1em;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        margin: 0 10px;
    }

    .btn-submit {
        background-color: #007bff;
        color: white;
        border: 1px solid #007bff;
    }

    .btn-submit:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
        border: 1px solid #6c757d;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
        border-color: #545b62;
    }

    .required-label::after {
        content: " *";
        color: red;
        font-weight: bold;
    }
</style>

<div class="page-content-wrapper">
    <div class="form-card">
        <h2>Edit Administrator: <?php echo htmlspecialchars($admin_data['full_name'] ?? 'N/A'); ?></h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($admin_data['admin_id'] ?? ''); ?>">

            <div class="form-section">
                <h3 class="form-section-title">Account & Personal Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Admin ID:</label>
                        <span class="static-value"><?php echo htmlspecialchars($admin_data['admin_id'] ?? ''); ?></span>
                    </div>

                    <div class="form-group">
                        <label for="username" class="required-label">Username:</label>
                        <input type="text" id="username" name="username" required autocomplete="off" value="<?php echo htmlspecialchars($admin_data['username'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="full_name" class="required-label">Full Name:</label>
                        <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($admin_data['full_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current):</label>
                        <input type="password" id="password" name="password" autocomplete="new-password" placeholder="Enter new password if changing">
                        <small>Leave blank to keep the current password.</small>
                    </div>

                    <div class="form-group">
                        <label for="email" class="required-label">Email:</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($admin_data['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Phone Number:</label>
                        <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($admin_data['phone_number'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="address">Address:</label>
                        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($admin_data['address'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender:</label>
                        <select id="gender" name="gender">
                            <option value="Male" <?php echo (isset($admin_data['gender']) && $admin_data['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($admin_data['gender']) && $admin_data['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (isset($admin_data['gender']) && $admin_data['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="dob">Date of Birth:</label>
                        <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($admin_data['dob'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Employment Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Role:</label>
                        <span class="static-value">Admin</span>
                    </div>

                    <div class="form-group">
                        <label for="salary">Salary:</label>
                        <input type="number" id="salary" step="0.01" name="salary" value="<?php echo htmlspecialchars($admin_data['salary'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="qualification">Qualification:</label>
                        <input type="text" id="qualification" name="qualification" value="<?php echo htmlspecialchars($admin_data['qualification'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="join_date">Joining Date:</label>
                        <input type="date" id="join_date" name="join_date" value="<?php echo htmlspecialchars($admin_data['join_date'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section single-column">
                <h3 class="form-section-title">Profile Image</h3>
                <div class="form-group">
                    <label>Current Profile Image:</label>
                    <?php if (!empty($admin_data['image_url'])): ?>
                        <div class="current-image-preview">
                            <img src="../<?php echo htmlspecialchars($admin_data['image_url']); ?>" alt="Current Profile Image">
                            <span><?php echo basename($admin_data['image_url']); ?></span>
                        </div>
                        <div class="remove-image-checkbox">
                            <input type="checkbox" id="remove_current_image" name="remove_current_image" value="1">
                            <label for="remove_current_image">Remove current image</label>
                        </div>
                    <?php else: ?>
                        <p>No profile image uploaded.</p>
                    <?php endif; ?>

                    <label for="image" style="margin-top: 15px;">Upload New Profile Image (optional):</label>
                    <input type="file" id="image" name="image" accept="image/*">
                    <small>Allowed formats: JPG, JPEG, PNG, GIF. Max size: 5MB. Uploading a new image will replace the current one.</small>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn-submit">Update Admin</button>
                <a href="manage_admins.php" class="btn-secondary">Cancel / Back to List</a>
            </div>
        </form>
    </div>
</div>

<?php require_once './admin_footer.php'; ?>