<?php
session_start();
require_once '../database/config.php';
require_once '../database/cloudinary_upload_handler.php'; // For Cloudinary uploads

// Ensure only Super Admin can access
if (!isset($_SESSION['super_admin_id'])) {
    die("Access denied. Please login as Super Admin.");
}

$message = "";
$message_type = ""; // 'success' or 'error'

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form data
    $admin_id     = trim($_POST['admin_id']); // **NEW: Get admin_id from form**
    $username     = trim($_POST['username']);
    $full_name    = trim($_POST['full_name']);
    $password_raw = $_POST['password'];
    $email        = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']) ?: null;
    $address      = trim($_POST['address']) ?: null;
    $gender       = $_POST['gender'] ?: null;
    $salary       = !empty($_POST['salary']) ? (float)$_POST['salary'] : null;
    $qualification= trim($_POST['qualification']) ?: null;
    $join_date    = !empty($_POST['join_date']) ? $_POST['join_date'] : null;
    $dob          = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $role         = 'Admin'; 

    // Basic server-side validation for required fields
    if (empty($admin_id) || empty($username) || empty($full_name) || empty($password_raw) || empty($email)) {
        $message = "Please fill in all required fields (Admin ID, Username, Full Name, Password, Email).";
        $message_type = "error";
    } else {
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        // --- IMAGE UPLOAD HANDLING with Cloudinary ---
        $image_url = null;
        $public_id = null;
        $resource_type = null;
        if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
            $uploadResult = uploadToCloudinary($_FILES["image"], 'admins');
            if (isset($uploadResult['secure_url'])) {
                $image_url = $uploadResult['secure_url'];
                $public_id = $uploadResult['public_id'];
                $resource_type = $uploadResult['resource_type'];
            } else {
                $message = "Image upload failed: " . ($uploadResult['error'] ?? 'Unknown error');
                $message_type = "error";
            }
        }

        // --- INSERT INTO DATABASE ---
        if ($message_type !== "error") {
            $sql = "INSERT INTO admins 
                    (admin_id, username, full_name, password, email, phone_number, address, gender, role, salary, qualification, join_date, dob, image_url, public_id, resource_type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param(
                    $stmt, 
                    "sssssssssdssssss",
                    $admin_id, $username, $full_name, $password, $email, $phone_number, 
                    $address, $gender, $role, $salary, $qualification, $join_date, $dob, 
                    $image_url, $public_id, $resource_type
                );

                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Admin '{$username}' with ID '{$admin_id}' created successfully!";
                    header("Location: manage_admins.php");
                    exit;
                } else {
                    if (mysqli_errno($link) == 1062) {
                        $message = "Error: An admin with this Admin ID, Username, or Email already exists.";
                    } else {
                        $message = "Error creating admin: " . mysqli_stmt_error($stmt);
                    }
                    $message_type = "error";
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing query: " . mysqli_error($link);
                $message_type = "error";
            }
        }
    }
}

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #eef2f7; /* Light blue-gray background */
            color: #333;
        }
        .main-container {
            padding-top: 40px;
            padding-bottom: 40px;
        }
        .form-container {
            max-width: 900px;
            margin: auto;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 40px;
        }
        .page-title {
            text-align: center;
            color: #2c3e50; /* Darker blue-gray for titles */
            margin-bottom: 30px;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .form-section-title {
            color: #2980b9; /* Bright blue for section titles */
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1; /* Light gray separator */
            padding-bottom: 10px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 0.9rem;
        }
        .form-control, .form-select {
            border: 1px solid #ced4da;
            border-radius: 8px; /* Softer corners */
            padding: 12px;
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
            outline: none;
        }
        .static-value {
            padding: 12px;
            border: 1px solid #e0e0e0;
            background-color: #f8f9fa;
            border-radius: 8px;
            font-size: 1rem;
            color: #555;
        }
        .required-label::after {
            content: " *";
            color: #e74c3c;
        }
        .button-group {
            text-align: center;
            margin-top: 30px;
            border-top: 1px solid #ecf0f1;
            padding-top: 25px;
        }
        .btn-submit {
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .btn-submit:hover { background-color: #0b5ed7; }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            text-decoration: none;
        }
        .btn-secondary:hover { background-color: #5a6268; }
        .alert-custom { border-radius: 8px; padding: 1rem; font-weight: 500; }
        .alert-success-custom { background-color: #d1e7dd; color: #0f5132; border-left: 5px solid #0f5132; }
        .alert-error-custom { background-color: #f8d7da; color: #842029; border-left: 5px solid #842029; }
    </style>
</head>
<body>
<div class="main-container">
    <div class="form-container">
        <h2 class="page-title">Create New Admin</h2>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $message_type === 'success' ? 'alert-success-custom' : 'alert-error-custom'; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="create_admin.php" enctype="multipart/form-data">
            <div class="form-section">
                <h3 class="form-section-title">Account & Personal Information</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="admin_id" class="form-label required-label">Admin ID</label>
                        <input type="text" id="admin_id" name="admin_id" class="form-control" required placeholder="e.g., ADM0001" value="<?php echo htmlspecialchars($_POST['admin_id'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="username" class="form-label required-label">Username</label>
                        <input type="text" id="username" name="username" class="form-control" required autocomplete="off" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="full_name" class="form-label required-label">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label required-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label required-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="phone_number" class="form-label">Phone Number</label>
                        <input type="text" id="phone_number" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
                    </div>
                    <div class="col-md-12">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="gender" class="form-label">Gender</label>
                        <select id="gender" name="gender" class="form-select">
                            <option value="Male" <?php echo (($_POST['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (($_POST['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (($_POST['gender'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="date" id="dob" name="dob" class="form-control" value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Employment Details</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <div class="static-value">Admin</div>
                    </div>
                    <div class="col-md-6">
                        <label for="salary" class="form-label">Salary</label>
                        <input type="number" id="salary" step="0.01" name="salary" class="form-control" value="<?php echo htmlspecialchars($_POST['salary'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="qualification" class="form-label">Qualification</label>
                        <input type="text" id="qualification" name="qualification" class="form-control" value="<?php echo htmlspecialchars($_POST['qualification'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="join_date" class="form-label">Joining Date</label>
                        <input type="date" id="join_date" name="join_date" class="form-control" value="<?php echo htmlspecialchars($_POST['join_date'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Profile Image</h3>
                <div class="mb-3">
                    <label for="image" class="form-label">Upload Profile Image</label>
                    <input type="file" id="image" name="image" class="form-control" accept="image/*">
                    <small class="form-text text-muted">Allowed formats: JPG, JPEG, PNG, GIF. Max size: 5MB.</small>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-submit">Create Admin</button>
                <a href="manage_admins.php" class="btn btn-secondary">Back to Admin List</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php require_once './admin_footer.php'; ?>