<?php
// edit_staff.php
session_start();
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// Initialize variables
$staff = [];
$errors = [];
$vans = [];
$role = $full_name = $email = $phone_number = $address = $pincode = $staff_code = "";
$salary = $qualification = $subject_taught = $gender = $blood_group = $dob = "";
$years_of_experience = $date_of_joining = "";
$image_url = $current_image_url = "";
$van_service_taken = 0;
$van_id = null;

// --- Fetch available vans for the dropdown ---
$sql_vans = "SELECT id, van_number, route_details FROM vans WHERE status = 'Active' ORDER BY van_number";
if ($result_vans = mysqli_query($link, $sql_vans)) {
    while ($row_van = mysqli_fetch_assoc($result_vans)) {
        $vans[] = $row_van;
    }
}

// --- PART 1: Handle GET request to fetch and display data ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $role_get = isset($_GET['role']) ? $_GET['role'] : '';

    if ($id <= 0 || !in_array($role_get, ['Principal', 'Teacher'])) {
        header("location: view_all_staff.php");
        exit;
    }
    $role = $role_get; // Set role for form display

    $table_name = ($role === 'Principal') ? 'principles' : 'teachers';
    $code_column = ($role === 'Principal') ? 'principle_code' : 'teacher_code';

    $sql = "SELECT * FROM $table_name WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $staff = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$staff) {
            header("location: view_all_staff.php");
            exit;
        }

        // Populate variables for the form
        $staff_code = $staff[$code_column];
        $full_name = $staff['full_name'];
        $email = $staff['email'];
        $phone_number = $staff['phone_number'];
        $address = $staff['address'];
        $pincode = $staff['pincode'];
        $salary = $staff['salary'];
        $qualification = $staff['qualification'];
        $gender = $staff['gender'];
        $blood_group = $staff['blood_group'];
        $dob = $staff['dob'];
        $years_of_experience = $staff['years_of_experience'];
        $date_of_joining = $staff['date_of_joining'];
        $van_service_taken = $staff['van_service_taken'];
        $van_id = $staff['van_id'];
        $current_image_url = $staff['image_url'];
        if ($role === 'Teacher') {
            $subject_taught = $staff['subject_taught'];
        }
    }
}


// --- PART 2: Handle POST request to process form submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get hidden fields
    $id = (int)$_POST['id'];
    $role = $_POST['role'];
    $current_image_url = $_POST['current_image_url'];
    
    // Determine table and code columns for validation
    $table_name = ($role === 'Principal') ? 'principles' : 'teachers';
    $other_table_name = ($role === 'Principal') ? 'teachers' : 'principles';
    $code_column = ($role === 'Principal') ? 'principle_code' : 'teacher_code';
    $other_code_column = ($role === 'Principal') ? 'teacher_code' : 'principle_code';

    // --- Validate and Sanitize Inputs ---
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $staff_code = trim($_POST["staff_code"]);
    // (Other fields are sanitized below)

    // Staff Code
    if (empty($staff_code)) {
        $errors[] = "Staff Code is required.";
    } else {
        // Check if code is taken by ANOTHER user in either table
        $sql_check_code = "SELECT id FROM $table_name WHERE $code_column = ? AND id != ? UNION SELECT id FROM $other_table_name WHERE $other_code_column = ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check_code)) {
            mysqli_stmt_bind_param($stmt_check, "sis", $staff_code, $id, $staff_code);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) $errors[] = "This Staff Code is already in use by another staff member.";
            mysqli_stmt_close($stmt_check);
        }
    }

    // Email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email is taken by ANOTHER user in either table
        $sql_check_email = "SELECT id FROM principles WHERE email = ? AND id != ? AND ? = 'Principal' UNION SELECT id FROM teachers WHERE email = ? AND id != ? AND ? = 'Teacher'";
        if ($stmt_check = mysqli_prepare($link, $sql_check_email)) {
             mysqli_stmt_bind_param($stmt_check, "sissis", $email, $id, $role, $email, $id, $role);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) $errors[] = "This email is already registered to another user.";
            mysqli_stmt_close($stmt_check);
        }
    }

    // Password (optional update)
    $password = trim($_POST["password"]);
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "New password must have at least 6 characters.";
    }

    // Other fields...
    $phone_number = trim($_POST["phone_number"]);
    $address = trim($_POST["address"]);
    $pincode = trim($_POST["pincode"]);
    $salary = !empty($_POST["salary"]) ? trim($_POST["salary"]) : null;
    $qualification = trim($_POST["qualification"]);
    $gender = trim($_POST["gender"]);
    $blood_group = trim($_POST["blood_group"]);
    $dob = trim($_POST["dob"]);
    $years_of_experience = !empty($_POST["years_of_experience"]) ? (int)$_POST["years_of_experience"] : 0;
    $date_of_joining = trim($_POST["date_of_joining"]);
    $van_service_taken = (isset($_POST['van_service']) && $_POST['van_service'] == '1') ? 1 : 0;
    $van_id = $van_service_taken ? (int)$_POST['van_id'] : null;

    if ($role === 'Teacher') {
        $subject_taught = trim($_POST["subject_taught"]);
        if (empty($subject_taught)) $errors[] = "Subject Taught is required for a Teacher.";
    }

    // --- Handle Image Upload ---
    $image_url = $current_image_url; // Default to old image
    if (isset($_FILES['staff_image']) && $_FILES['staff_image']['error'] == UPLOAD_ERR_OK) {
        $uploadResult = uploadToCloudinary($_FILES['staff_image'], 'staff_photos');
        if (isset($uploadResult['error'])) {
            $errors[] = "Image Upload Failed: " . $uploadResult['error'];
        } else {
            $image_url = $uploadResult['secure_url']; // Set new image URL
        }
    }

    // --- If no errors, proceed to database update ---
    if (empty($errors)) {
        // Construct the base SQL
        if ($role === 'Principal') {
            $sql = "UPDATE principles SET principle_code=?, full_name=?, phone_number=?, address=?, pincode=?, email=?, image_url=?, salary=?, qualification=?, gender=?, blood_group=?, dob=?, years_of_experience=?, date_of_joining=?, van_service_taken=?, van_id=? ";
        } else { // Teacher
            $sql = "UPDATE teachers SET teacher_code=?, full_name=?, phone_number=?, address=?, pincode=?, email=?, image_url=?, salary=?, qualification=?, subject_taught=?, gender=?, blood_group=?, dob=?, years_of_experience=?, date_of_joining=?, van_service_taken=?, van_id=? ";
        }

        // Conditionally add password to the query
        if (!empty($password)) {
            $sql .= ", password=? ";
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= "WHERE id=?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind parameters - this is complex due to the conditional password
            if ($role === 'Principal') {
                if (!empty($password)) {
                    mysqli_stmt_bind_param($stmt, "ssssssdsssssisiiisi", $staff_code, $full_name, $phone_number, $address, $pincode, $email, $image_url, $salary, $qualification, $gender, $blood_group, $dob, $years_of_experience, $date_of_joining, $van_service_taken, $van_id, $hashed_password, $id);
                } else {
                    mysqli_stmt_bind_param($stmt, "ssssssdsssssisiiii", $staff_code, $full_name, $phone_number, $address, $pincode, $email, $image_url, $salary, $qualification, $gender, $blood_group, $dob, $years_of_experience, $date_of_joining, $van_service_taken, $van_id, $id);
                }
            } else { // Teacher
                if (!empty($password)) {
                    mysqli_stmt_bind_param($stmt, "ssssssdssssssisiiisi", $staff_code, $full_name, $phone_number, $address, $pincode, $email, $image_url, $salary, $qualification, $subject_taught, $gender, $blood_group, $dob, $years_of_experience, $date_of_joining, $van_service_taken, $van_id, $hashed_password, $id);
                } else {
                    mysqli_stmt_bind_param($stmt, "ssssssdssssssisiiiii", $staff_code, $full_name, $phone_number, $address, $pincode, $email, $image_url, $salary, $qualification, $subject_taught, $gender, $blood_group, $dob, $years_of_experience, $date_of_joining, $van_service_taken, $van_id, $id);
                }
            }
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "Staff details updated successfully.";
                $_SESSION['message_type'] = 'success';
                header("location: view_all_staff.php");
                exit;
            } else {
                $errors[] = "Something went wrong. Please try again. Error: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Include header
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Staff Member</title>
    <!-- (Copy the same styles from your create_staff.php page) -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 800px; margin: auto; margin-bottom: 100px; margin-top: 100px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 30px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        h2 { text-align: center; color: #1a2c5a; font-weight: 700; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(-45deg, #007bff, #00bfff, #8a2be2, #007bff); background-size: 400% 400%; animation: gradientAnimation 8s ease infinite; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .current-img-preview { max-width: 100px; border-radius: 8px; margin-top: 10px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Staff: <?php echo htmlspecialchars($full_name); ?></h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul>
            <?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?>
        </ul></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
        <!-- Hidden fields to pass ID and Role on submit -->
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="role" value="<?php echo $role; ?>">
        <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($current_image_url); ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?php echo htmlspecialchars($role); ?>" readonly style="background:#e9ecef;">
            </div>
            
            <div class="form-group">
                <label for="staff_code">Staff Code</label>
                <input type="text" name="staff_code" id="staff_code" value="<?php echo htmlspecialchars($staff_code); ?>">
            </div>

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($full_name); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" placeholder="Leave blank to keep current password">
            </div>
            <!-- ... more form fields below ... -->
        </div>

        <!-- All other fields pre-populated -->
        <div class="form-grid">
            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>">
            </div>
            <div class="form-group">
                <label for="pincode">Pincode</label>
                <input type="text" name="pincode" id="pincode" value="<?php echo htmlspecialchars($pincode); ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea name="address" id="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="qualification">Qualification</label>
                <input type="text" name="qualification" id="qualification" value="<?php echo htmlspecialchars($qualification); ?>">
            </div>
            
            <div class="form-group" id="teacher_fields" style="display: <?php echo ($role === 'Teacher') ? 'block' : 'none'; ?>;">
                <label for="subject_taught">Subject Taught</label>
                <input type="text" name="subject_taught" id="subject_taught" value="<?php echo htmlspecialchars($subject_taught); ?>">
            </div>

            <div class="form-group">
                <label for="salary">Salary</label>
                <input type="number" step="0.01" name="salary" id="salary" value="<?php echo htmlspecialchars($salary); ?>">
            </div>

            <div class="form-group">
                <label for="gender">Gender</label>
                <select name="gender" id="gender">
                    <option value="Male" <?php echo ($gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($gender == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <!-- more fields -->
            <div class="form-group">
                <label for="blood_group">Blood Group</label>
                <input type="text" name="blood_group" id="blood_group" value="<?php echo htmlspecialchars($blood_group); ?>">
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" name="dob" id="dob" value="<?php echo htmlspecialchars($dob); ?>">
            </div>
            <div class="form-group">
                <label for="years_of_experience">Years of Experience</label>
                <input type="number" name="years_of_experience" id="years_of_experience" value="<?php echo htmlspecialchars($years_of_experience); ?>">
            </div>
            <div class="form-group">
                <label for="date_of_joining">Date of Joining</label>
                <input type="date" name="date_of_joining" id="date_of_joining" value="<?php echo htmlspecialchars($date_of_joining); ?>">
            </div>
            <div class="form-group">
                <label for="van_service">Take Van Service?</label>
                <select name="van_service" id="van_service" onchange="toggleVanField()">
                    <option value="0" <?php echo (!$van_service_taken) ? 'selected' : ''; ?>>No</option>
                    <option value="1" <?php echo ($van_service_taken) ? 'selected' : ''; ?>>Yes</option>
                </select>
            </div>
            <div class="form-group" id="van_fields" style="display:<?php echo ($van_service_taken) ? 'block' : 'none';?>;">
                <label for="van_id">Assign Van</label>
                <select name="van_id" id="van_id">
                    <option value="">-- Select a Van --</option>
                    <?php foreach ($vans as $v): ?>
                        <option value="<?php echo $v['id']; ?>" <?php echo ($van_id == $v['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($v['van_number']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="staff_image">Update Profile Image</label>
            <?php if ($current_image_url): ?>
                <p>Current Image: <img src="<?php echo htmlspecialchars($current_image_url); ?>" alt="Current Profile Photo" class="current-img-preview"></p>
            <?php endif; ?>
            <input type="file" name="staff_image" id="staff_image" accept="image/*">
        </div>

        <div class="form-group">
            <input type="submit" class="btn" value="Update Staff Member">
        </div>
    </form>
</div>
<script>
    function toggleVanField() {
        var vanServiceSelect = document.getElementById('van_service');
        var vanFields = document.getElementById('van_fields');
        vanFields.style.display = (vanServiceSelect.value === '1') ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', toggleVanField);
</script>
</body>
</html>
<?php 
mysqli_close($link);
require_once './admin_footer.php';
?>