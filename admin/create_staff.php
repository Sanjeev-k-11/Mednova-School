<?php
// Start the session
session_start();

// Include configuration and helper files
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

// Check for and validate the  Admin ID immediately
$created_by_admin_id = null;
if (isset($_SESSION["admin_id"]) && is_numeric($_SESSION["admin_id"])) {
    $created_by_admin_id = (int)$_SESSION["admin_id"];
} else {
    // If the ID is missing or invalid, add a fatal error and stop execution
    $errors[] = "Fatal Error: Admin ID not found. Please log out and log back in.";
}

// -- NEW: Fetch available vans for the dropdown --
$vans = [];
$sql_vans = "SELECT id, van_number, route_details FROM vans WHERE status = 'Active' ORDER BY van_number";
if ($result_vans = mysqli_query($link, $sql_vans)) {
    while ($row_van = mysqli_fetch_assoc($result_vans)) {
        $vans[] = $row_van;
    }
}

// -- MODIFIED: Added new variables --
$role = $full_name = $email = $password = $phone_number = $address = $pincode = "";
$salary = $qualification = $subject_taught = $gender = $blood_group = $dob = "";
$years_of_experience = $date_of_joining = "";
$image_url = null;
$staff_code = ""; // <-- NEW
$van_service_taken = 0; // <-- NEW
$van_id = null; // <-- NEW

$success_message = "";

// Processing form data when form is submitted, only if no fatal errors exist
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors)) {

    // --- Validate and Sanitize Inputs ---

    // Role
    if (empty(trim($_POST["role"])) || !in_array($_POST["role"], ['Principle', 'Teacher'])) {
        $errors[] = "A valid role is required.";
    } else {
        $role = trim($_POST["role"]);
    }

    // -- NEW: Staff Code Validation --
    if (empty(trim($_POST["staff_code"]))) {
        $errors[] = "Staff Code is required.";
    } else {
        $staff_code = trim($_POST["staff_code"]);
        // Check if staff code already exists in either table
        $sql_check_code = "SELECT principle_code FROM principles WHERE principle_code = ? UNION SELECT teacher_code FROM teachers WHERE teacher_code = ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check_code)) {
            mysqli_stmt_bind_param($stmt_check, "ss", $staff_code, $staff_code);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $errors[] = "This Staff Code is already in use.";
            }
            mysqli_stmt_close($stmt_check);
        }
    }

    // Full Name
    if (empty(trim($_POST["full_name"]))) {
        $errors[] = "Full name is required.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }

    // Email
    if (empty(trim($_POST["email"]))) {
        $errors[] = "Email is required.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        $email = trim($_POST["email"]);
        // Check if email already exists
        $sql_check_email = "SELECT id FROM principles WHERE email = ? UNION SELECT id FROM teachers WHERE email = ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check_email)) {
            mysqli_stmt_bind_param($stmt_check, "ss", $email, $email);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $errors[] = "This email is already registered.";
            }
            mysqli_stmt_close($stmt_check);
        }
    }

    // Password
    if (empty(trim($_POST["password"]))) {
        $errors[] = "Password is required.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $errors[] = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Subject Taught (only if role is Teacher)
    if ($role === 'Teacher' && empty(trim($_POST["subject_taught"]))) {
        $errors[] = "Subject Taught is required for a Teacher.";
    } else {
        $subject_taught = trim($_POST["subject_taught"]);
    }
    
    // -- NEW: Van Service Validation --
    $van_service_taken = (isset($_POST['van_service']) && $_POST['van_service'] == '1') ? 1 : 0;
    if ($van_service_taken) {
        if (empty($_POST['van_id'])) {
            $errors[] = "Please select a van if van service is taken.";
        } else {
            $van_id = (int)$_POST['van_id'];
        }
    } else {
        $van_id = null;
    }


    // Other fields
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


    // --- Handle Image Upload ---
    if (isset($_FILES['staff_image']) && $_FILES['staff_image']['error'] == UPLOAD_ERR_OK) {
        $uploadResult = uploadToCloudinary($_FILES['staff_image'], 'staff_photos');
        if (isset($uploadResult['error'])) {
            $errors[] = "Image Upload Failed: " . $uploadResult['error'];
        } else {
            $image_url = $uploadResult['secure_url'];
        }
    }

    // --- If no errors, proceed to database insertion ---
    if (empty($errors)) {
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "";
        
        if ($role === 'Principle') {
            $sql = "INSERT INTO principles (principle_code, full_name, phone_number, address, pincode, email, password, image_url, salary, qualification, gender, blood_group, dob, years_of_experience, date_of_joining, van_service_taken, van_id, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        } elseif ($role === 'Teacher') {
            $sql = "INSERT INTO teachers (teacher_code, full_name, phone_number, address, pincode, email, password, image_url, salary, qualification, subject_taught, years_of_experience, gender, blood_group, dob, date_of_joining, van_service_taken, van_id, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        }

        if ($stmt = mysqli_prepare($link, $sql)) {
            if ($role === 'Principle') {
                mysqli_stmt_bind_param($stmt, "ssssssssdssssisiii", $staff_code, $full_name, $phone_number, $address, $pincode, $email, $hashed_password, $image_url, $salary, $qualification, $gender, $blood_group, $dob, $years_of_experience, $date_of_joining, $van_service_taken, $van_id, $created_by_admin_id);
            } else { // Teacher
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssssdssisssisii", // Corrected type string
        $staff_code, $full_name, $phone_number, $address, $pincode,
        $email, $hashed_password, $image_url, $salary, $qualification,
        $subject_taught, $years_of_experience, $gender, $blood_group,
        $dob, $date_of_joining, $van_service_taken, $van_id, $created_by_admin_id
    );

            }

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "New " . htmlspecialchars($role) . " created successfully with Staff Code: " . htmlspecialchars($staff_code);
                // Clear form fields
                $_POST = array(); 
                $role = $full_name = $email = $password = $phone_number = $address = $pincode = "";
                $salary = $qualification = $subject_taught = $gender = $blood_group = $dob = "";
                $years_of_experience = $date_of_joining = "";
                $image_url = null;
                $staff_code = ""; $van_service_taken = 0; $van_id = null;
            } else {
                $errors[] = "Something went wrong. Please try again later. Error: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Staff</title>
    <style>
        /* Define the animation */
        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            
            /* The animated gradient background */
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: auto;
            margin-bottom: 100px;
            /* --- NEW: This adds the top margin to prevent header overlap --- */
            /* Adjust 100px to the actual height of your header if needed */
            margin-top: 100px; 
            
            /* Enhanced styling for the form container */
            background: rgba(255, 255, 255, 0.9); /* Semi-transparent white */
            backdrop-filter: blur(10px); /* Frosted glass effect */
            -webkit-backdrop-filter: blur(10px); /* For Safari */
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        h2 { text-align: center; color: #1a2c5a; font-weight: 700; }
        p { text-align: center; color: #555; margin-top: -10px; margin-bottom: 25px; }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #23a6d5;
            box-shadow: 0 0 0 3px rgba(35, 166, 213, 0.2);
        }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; }
        
        /* --- MODIFIED: Animated Gradient Button --- */
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            
            /* Button's animated gradient */
            background: linear-gradient(-45deg, #007bff, #00bfff, #8a2be2, #007bff);
            background-size: 400% 400%;
            animation: gradientAnimation 8s ease infinite;
            
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 6px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .required-star { color: #e73c7e; }

        /* Responsive adjustment for smaller screens */
        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Create New Staff Member</h2>
    <p>Fill this form to create a new Principal or Teacher account.</p>

    <?php 
    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
    }
    if (!empty($success_message)) {
        echo '<div class="alert alert-success">' . htmlspecialchars($success_message) . '</div>';
    }
    ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
        
        <div class="form-grid">
            <div class="form-group">
                <label for="role">Role <span class="required-star">*</span></label>
                <select name="role" id="role" onchange="toggleSubjectField()">
                    <option value="">-- Select Role --</option>
                    <option value="Principle" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Principle') ? 'selected' : ''; ?>>Principal</option>
                    <option value="Teacher" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Teacher') ? 'selected' : ''; ?>>Teacher</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="staff_code">Staff Code <span class="required-star">*</span></label>
                <input type="text" name="staff_code" id="staff_code" value="<?php echo htmlspecialchars($_POST['staff_code'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="full_name">Full Name <span class="required-star">*</span></label>
                <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email <span class="required-star">*</span></label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required-star">*</span></label>
                <input type="password" name="password" id="password">
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="pincode">Pincode</label>
                <input type="text" name="pincode" id="pincode" value="<?php echo htmlspecialchars($_POST['pincode'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="address">Address</label>
            <textarea name="address" id="address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="qualification">Qualification</label>
                <input type="text" name="qualification" id="qualification" value="<?php echo htmlspecialchars($_POST['qualification'] ?? ''); ?>">
            </div>
            
            <div class="form-group" id="teacher_fields" style="display: none;">
                <label for="subject_taught">Subject Taught <span class="required-star">*</span></label>
                <input type="text" name="subject_taught" id="subject_taught" value="<?php echo htmlspecialchars($_POST['subject_taught'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="salary">Salary</label>
                <input type="number" step="0.01" name="salary" id="salary" value="<?php echo htmlspecialchars($_POST['salary'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="gender">Gender</label>
                <select name="gender" id="gender">
                    <option value="">-- Select Gender --</option>
                    <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="blood_group">Blood Group</label>
                <input type="text" name="blood_group" id="blood_group" value="<?php echo htmlspecialchars($_POST['blood_group'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" name="dob" id="dob" value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="years_of_experience">Years of Experience</label>
                <input type="number" name="years_of_experience" id="years_of_experience" value="<?php echo htmlspecialchars($_POST['years_of_experience'] ?? '0'); ?>">
            </div>

            <div class="form-group">
                <label for="date_of_joining">Date of Joining</label>
                <input type="date" name="date_of_joining" id="date_of_joining" value="<?php echo htmlspecialchars($_POST['date_of_joining'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="van_service">Take Van Service?</label>
                <select name="van_service" id="van_service" onchange="toggleVanField()">
                    <option value="0" <?php echo (!isset($_POST['van_service']) || $_POST['van_service'] == '0') ? 'selected' : ''; ?>>No</option>
                    <option value="1" <?php echo (isset($_POST['van_service']) && $_POST['van_service'] == '1') ? 'selected' : ''; ?>>Yes</option>
                </select>
            </div>

            <div class="form-group" id="van_fields" style="display:none;">
                <label for="van_id">Assign Van</label>
                <select name="van_id" id="van_id">
                    <option value="">-- Select a Van --</option>
                    <?php foreach ($vans as $van): ?>
                        <option value="<?php echo $van['id']; ?>" <?php echo (isset($_POST['van_id']) && $_POST['van_id'] == $van['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($van['van_number'] . ' (' . $van['route_details'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>

        <div class="form-group">
            <label for="staff_image">Profile Image</label>
            <input type="file" name="staff_image" id="staff_image" accept="image/*">
        </div>

        <div class="form-group">
            <input type="submit" class="btn" value="Create Staff Member">
        </div>
    </form>
</div>

<script>
    function toggleSubjectField() {
        var roleSelect = document.getElementById('role');
        var teacherFields = document.getElementById('teacher_fields');
        teacherFields.style.display = (roleSelect.value === 'Teacher') ? 'block' : 'none';
    }

    function toggleVanField() {
        var vanServiceSelect = document.getElementById('van_service');
        var vanFields = document.getElementById('van_fields');
        vanFields.style.display = (vanServiceSelect.value === '1') ? 'block' : 'none';
    }

    // Run functions on page load to set the initial state, especially after a validation error
    document.addEventListener('DOMContentLoaded', function() {
        toggleSubjectField();
        toggleVanField();
    });
</script>

</body>
</html>
<?php 
if($link) mysqli_close($link);
require_once './admin_footer.php';
?>