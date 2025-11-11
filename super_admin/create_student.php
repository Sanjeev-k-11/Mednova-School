<?php
session_start();
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}
$created_by_admin_id = $_SESSION["super_admin_id"];

// --- Fetch data for dropdowns ---
// Fetch Classes and Sections
$classes = [];
$sql_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name, section_name";
if ($result = mysqli_query($link, $sql_classes)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $classes[] = $row;
    }
}
// Fetch Active Vans
$vans = [];
$sql_vans = "SELECT id, van_number FROM vans WHERE status = 'Active' ORDER BY van_number";
if ($result = mysqli_query($link, $sql_vans)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $vans[] = $row;
    }
}

// --- Initialize form variables ---
$registration_number = $first_name = $middle_name = $last_name = $password = "";
$class_id = $roll_number = $gender = $dob = $blood_group = "";
$phone_number = $address = $pincode = $district = $state = $email = "";
$mother_name = $father_name = $parent_phone_number = $father_occupation = "";
$previous_class = $previous_school = "";
$van_service_taken = 0; $van_id = null;
$image_url = null;
$admission_date = date('Y-m-d'); // Default to today

$errors = [];
$success_message = "";

// --- Process form on POST request ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve all POST data
    $registration_number = trim($_POST['registration_number']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $password = trim($_POST['password']);
    $class_id = (int)$_POST['class_id'];
    $roll_number = trim($_POST['roll_number']);
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $blood_group = trim($_POST['blood_group']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $pincode = trim($_POST['pincode']);
    $district = trim($_POST['district']);
    $state = trim($_POST['state']);
    $email = trim($_POST['email']);
    $mother_name = trim($_POST['mother_name']);
    $father_name = trim($_POST['father_name']);
    $parent_phone_number = trim($_POST['parent_phone_number']);
    $father_occupation = trim($_POST['father_occupation']);
    $previous_class = trim($_POST['previous_class']);
    $previous_school = trim($_POST['previous_school']);
    $admission_date = $_POST['admission_date'];
    $van_service_taken = isset($_POST['van_service']) && $_POST['van_service'] == '1' ? 1 : 0;
    $van_id = $van_service_taken ? (int)$_POST['van_id'] : null;

    // --- Validation ---
    if (empty($registration_number) || !is_numeric($registration_number) || strlen($registration_number) != 6) {
        $errors[] = "A unique 6-digit Registration Number is required.";
    } else {
        $sql_check_reg = "SELECT id FROM students WHERE registration_number = ?";
        if($stmt_check = mysqli_prepare($link, $sql_check_reg)){
            mysqli_stmt_bind_param($stmt_check, "s", $registration_number);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if(mysqli_stmt_num_rows($stmt_check) > 0) $errors[] = "This Registration Number is already taken.";
            mysqli_stmt_close($stmt_check);
        }
    }
    if (empty($first_name)) $errors[] = "First Name is required.";
    if (empty($last_name)) $errors[] = "Last Name is required.";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password is required and must be at least 6 characters long.";
    if (empty($class_id)) $errors[] = "Current Class is required.";
    if (empty($father_name)) $errors[] = "Father's Name is required.";
    if (empty($parent_phone_number)) $errors[] = "Parent's Phone Number is required.";
    if ($van_service_taken && empty($van_id)) $errors[] = "Please select a van if van service is taken.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";

    // Image Upload
    if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] == UPLOAD_ERR_OK) {
        $uploadResult = uploadToCloudinary($_FILES['student_image'], 'student_photos');
        if (isset($uploadResult['error'])) {
            $errors[] = "Image Upload Failed: " . $uploadResult['error'];
        } else {
            $image_url = $uploadResult['secure_url'];
        }
    }

    // --- If no errors, INSERT into database ---
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql_insert = "INSERT INTO students (registration_number, first_name, middle_name, last_name, dob, gender, blood_group, image_url, phone_number, email, address, pincode, district, state, father_name, mother_name, parent_phone_number, father_occupation, class_id, roll_number, previous_school, previous_class, admission_date, van_service_taken, van_id, password, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql_insert)) {
            // FIX: The bind_param string had the wrong number of characters.
            // It must match the 27 placeholders in the SQL query.
            mysqli_stmt_bind_param($stmt, "ssssssssssssssssssissssiisi",
                $registration_number, $first_name, $middle_name, $last_name, $dob, $gender, $blood_group, 
                $image_url, $phone_number, $email, $address, $pincode, $district, $state, 
                $father_name, $mother_name, $parent_phone_number, $father_occupation, $class_id, 
                $roll_number, $previous_school, $previous_class, $admission_date, $van_service_taken, 
                $van_id, $hashed_password, $created_by_admin_id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Student " . htmlspecialchars($first_name) . " registered successfully with Registration No: " . htmlspecialchars($registration_number);
                // Clear all variables to reset the form
                $_POST = array(); // Clear post data
                $registration_number = $first_name = $middle_name = $last_name = $password = $class_id = $roll_number = $gender = $dob = $blood_group = $phone_number = $address = $pincode = $district = $state = $email = $mother_name = $father_name = $parent_phone_number = $father_occupation = $previous_class = $previous_school = "";
                $van_service_taken = 0; $van_id = null;
            } else {
                $errors[] = "Database error: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
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
    <title>Create New Student</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;  background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1000px; margin: auto; margin-top: 100px; margin-bottom: 100px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 30px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        h2 { text-align: center; color: #1a2c5a; font-weight: 700; margin-top: 0; }
        .section-title { color: #1a2c5a; border-bottom: 2px solid #23a6d5; padding-bottom: 5px; margin-top: 25px; margin-bottom: 20px; font-size: 1.4em; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(-45deg, #007bff, #00bfff, #8a2be2, #007bff); background-size: 400% 400%; animation: gradientAnimation 8s ease infinite; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .required-star { color: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <h2>New Student Registration</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul><?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?></ul></div>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
        
        <h3 class="section-title">Academic Details</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="registration_number">Registration Number <span class="required-star">*</span></label>
                <input type="text" name="registration_number" id="registration_number" placeholder="6-digit number" value="<?php echo htmlspecialchars($registration_number); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password <span class="required-star">*</span></label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label for="class_id">Current Class & Section <span class="required-star">*</span></label>
                <select name="class_id" id="class_id" required>
                    <option value="">-- Select Class --</option>
                    <?php foreach($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo ($class_id == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="roll_number">Roll Number</label>
                <input type="text" name="roll_number" id="roll_number" value="<?php echo htmlspecialchars($roll_number); ?>">
            </div>
            <div class="form-group">
                <label for="admission_date">Admission Date <span class="required-star">*</span></label>
                <input type="date" name="admission_date" id="admission_date" value="<?php echo htmlspecialchars($admission_date); ?>" required>
            </div>
        </div>

        <h3 class="section-title">Student's Personal Details</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="first_name">First Name <span class="required-star">*</span></label>
                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
            </div>
            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" name="middle_name" id="middle_name" value="<?php echo htmlspecialchars($middle_name); ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name <span class="required-star">*</span></label>
                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" name="dob" id="dob" value="<?php echo htmlspecialchars($dob); ?>">
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <select name="gender" id="gender">
                    <option value="">-- Select --</option>
                    <option value="Male" <?php echo ($gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($gender == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="blood_group">Blood Group</label>
                <input type="text" name="blood_group" id="blood_group" value="<?php echo htmlspecialchars($blood_group); ?>">
            </div>
            <div class="form-group">
                <label for="student_image">Profile Image</label>
                <input type="file" name="student_image" id="student_image" accept="image/*">
            </div>
        </div>

        <h3 class="section-title">Contact Information</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="phone_number">Student Phone Number</label>
                <input type="tel" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>">
            </div>
            <div class="form-group">
                <label for="email">Student Email (Optional)</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea name="address" id="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="pincode">Pincode</label>
                <input type="text" name="pincode" id="pincode" value="<?php echo htmlspecialchars($pincode); ?>">
            </div>
            <div class="form-group">
                <label for="district">District</label>
                <input type="text" name="district" id="district" value="<?php echo htmlspecialchars($district); ?>">
            </div>
            <div class="form-group">
                <label for="state">State</label>
                <input type="text" name="state" id="state" value="<?php echo htmlspecialchars($state); ?>">
            </div>
        </div>
        
        <h3 class="section-title">Parent / Guardian Details</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="father_name">Father's Name <span class="required-star">*</span></label>
                <input type="text" name="father_name" id="father_name" value="<?php echo htmlspecialchars($father_name); ?>" required>
            </div>
            <div class="form-group">
                <label for="mother_name">Mother's Name</label>
                <input type="text" name="mother_name" id="mother_name" value="<?php echo htmlspecialchars($mother_name); ?>">
            </div>
            <div class="form-group">
                <label for="parent_phone_number">Parent's Phone Number <span class="required-star">*</span></label>
                <input type="tel" name="parent_phone_number" id="parent_phone_number" value="<?php echo htmlspecialchars($parent_phone_number); ?>" required>
            </div>
            <div class="form-group">
                <label for="father_occupation">Father's/Guardian's Occupation</label>
                <input type="text" name="father_occupation" id="father_occupation" value="<?php echo htmlspecialchars($father_occupation); ?>">
            </div>
        </div>

        <h3 class="section-title">Previous Academic History</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="previous_class">Previous Class</label>
                <input type="text" name="previous_class" id="previous_class" value="<?php echo htmlspecialchars($previous_class); ?>">
            </div>
            <div class="form-group">
                <label for="previous_school">Previous School</label>
                <input type="text" name="previous_school" id="previous_school" value="<?php echo htmlspecialchars($previous_school); ?>">
                <input type="checkbox" id="same_school_checkbox" onchange="toggleSameSchool()"> <label for="same_school_checkbox">Same as Current</label>
            </div>
        </div>
        
        <h3 class="section-title">Other Details</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="van_service">Take Van Service?</label>
                <select name="van_service" id="van_service" onchange="toggleVanField()">
                    <option value="0" <?php echo (!$van_service_taken) ? 'selected' : ''; ?>>No</option>
                    <option value="1" <?php echo ($van_service_taken) ? 'selected' : ''; ?>>Yes</option>
                </select>
            </div>
            <div class="form-group" id="van_fields" style="display:none;">
                <label for="van_id">Assign Van</label>
                <select name="van_id" id="van_id">
                    <option value="">-- Select a Van --</option>
                    <?php foreach ($vans as $van): ?>
                        <option value="<?php echo $van['id']; ?>" <?php echo ($van_id == $van['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($van['van_number']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top: 30px;">
            <input type="submit" class="btn" value="Register Student">
        </div>
    </form>
</div>

<script>
    function toggleVanField() {
        var vanServiceSelect = document.getElementById('van_service');
        var vanFields = document.getElementById('van_fields');
        vanFields.style.display = (vanServiceSelect.value === '1') ? 'block' : 'none';
    }
    
    function toggleSameSchool() {
        var checkbox = document.getElementById('same_school_checkbox');
        var schoolInput = document.getElementById('previous_school');
        if (checkbox.checked) {
            schoolInput.value = 'Same as Current';
            schoolInput.readOnly = true;
        } else {
            schoolInput.value = '';
            schoolInput.readOnly = false;
        }
    }

    // Run functions on page load to set initial state if form is reloaded after error
    document.addEventListener('DOMContentLoaded', function() {
        toggleVanField();
        toggleSameSchool();
    });
</script>
</body>
</html>
<?php require_once './admin_footer.php'; ?>
