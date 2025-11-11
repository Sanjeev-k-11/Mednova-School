<?php
session_start();
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php"; // Placeholder function

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// --- Initialize variables ---
$student = [];
$errors = [];
$success_message = "";
$classes = [];
$vans = [];
$id = 0;

// --- Fetch data for dropdowns ---
$sql_classes = "SELECT id, class_name, section FROM classes ORDER BY class_name, section";
if ($result = mysqli_query($link, $sql_classes)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $classes[] = $row;
    }
}
$sql_vans = "SELECT id, van_number, route_details FROM vans WHERE status = 'Active' ORDER BY van_number";
if ($result = mysqli_query($link, $sql_vans)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $vans[] = $row;
    }
}

// --- Handle GET request to fetch student data ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        header("location: view_students.php");
        exit;
    }

    $sql = "SELECT * FROM students WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        if (!$student) {
            header("location: view_students.php");
            exit;
        }
    }
}

// --- Handle POST request to update student data ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['id'];

    // --- Retrieve and validate all POST data ---
    $name = trim($_POST["name"]);
    if (empty($name)) { $errors[] = "First name is required."; }
    $surname = trim($_POST["surname"]);
    if (empty($surname)) { $errors[] = "Surname is required."; }
    $middle_name = trim($_POST["middle_name"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $pincode = trim($_POST["pincode"]);
    $district = trim($_POST["district"]);
    $state = trim($_POST["state"]);
    $email = trim($_POST["email"]);
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email format."; }
    $password = trim($_POST["password"]);
    $reg_number = trim($_POST["reg_number"]);
    if (empty($reg_number) || !preg_match("/^\d{6}$/", $reg_number)) {
        $errors[] = "Registration number is required and must be a 6-digit number.";
    } else {
        $sql_check_reg = "SELECT id FROM students WHERE registration_number = ? AND id != ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check_reg)) {
            mysqli_stmt_bind_param($stmt_check, "si", $reg_number, $id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $errors[] = "This Registration Number is already in use by another student.";
            }
            mysqli_stmt_close($stmt_check);
        }
    }
    $current_class_id = $_POST["current_class"];
    $roll_number = trim($_POST["roll_number"]);
    $section = trim($_POST["section"]);
    $gender = trim($_POST["gender"]);
    $mother_name = trim($_POST["mother_name"]);
    $father_name = trim($_POST["father_name"]);
    $parent_phone = trim($_POST["parent_phone"]);
    $occupation = trim($_POST["occupation"]);
    $previous_class = trim($_POST["previous_class"]);
    $previous_school = trim($_POST["previous_school"]);
    $blood_group = trim($_POST["blood_group"]);
    $dob = trim($_POST["dob"]);
    $status = trim($_POST["status"]);
    $van_service_taken = (isset($_POST['van_service']) && $_POST['van_service'] == '1') ? 1 : 0;
    $van_id = null;
    if ($van_service_taken) {
        if (empty($_POST['van_id'])) { $errors[] = "Please select a van if van service is taken."; }
        else { $van_id = (int)$_POST['van_id']; }
    }
    
    // Get the current student data for potential password and image fallbacks
    $sql_get_current = "SELECT password, image_url FROM students WHERE id = ?";
    $stmt_current = mysqli_prepare($link, $sql_get_current);
    mysqli_stmt_bind_param($stmt_current, "i", $id);
    mysqli_stmt_execute($stmt_current);
    $result_current = mysqli_stmt_get_result($stmt_current);
    $current_data = mysqli_fetch_assoc($result_current);
    mysqli_stmt_close($stmt_current);

    // --- Image Upload Logic (check for new file) ---
    $image_url = $current_data['image_url'];
    if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] == UPLOAD_ERR_OK) {
        // Placeholder for Cloudinary upload. A real implementation would call an API.
        $uploadResult = ["secure_url" => "https://placeholder.com/image_new.jpg"];
        if (isset($uploadResult['error'])) { $errors[] = "Image Upload Failed: " . $uploadResult['error']; }
        else { $image_url = $uploadResult['secure_url']; }
    }

    // --- Password Logic (only update if a new one is entered) ---
    $hashed_password = $current_data['password'];
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    }
    
    // If no errors, proceed to database update
    if (empty($errors)) {
        $sql = "UPDATE students SET
            name=?, surname=?, middle_name=?, phone=?, address=?, pincode=?, district=?, state=?, email=?,
            password=?, registration_number=?, image_url=?, current_class_id=?, roll_number=?, section=?, gender=?, 
            mother_name=?, father_name=?, parent_phone=?, occupation=?, previous_class=?, previous_school=?, 
            status=?, blood_group=?, dob=?, van_service_taken=?, van_id=?
        WHERE id=?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssssssssiiisssssssisssiii",
                $name, $surname, $middle_name, $phone, $address, $pincode, $district, $state, $email,
                $hashed_password, $reg_number, $image_url, $current_class_id, $roll_number, $section, $gender,
                $mother_name, $father_name, $parent_phone, $occupation, $previous_class, $previous_school,
                $status, $blood_group, $dob, $van_service_taken, $van_id, $id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Student details updated successfully.";
            } else {
                $errors[] = "Something went wrong. Please try again later. Error: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // If errors, repopulate the $student array with POST data to refill the form
        $student = $_POST;
        $student['id'] = $id; // Keep the ID
    }
}
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; padding-top: 5rem; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
        h2 { text-align: center; color: #111827; margin-bottom: 1rem; }
        p { text-align: center; color: #6b7280; margin-bottom: 2rem; }
        .form-grid { display: grid; grid-template-columns: repeat(1, 1fr); gap: 1.5rem; }
        @media (min-width: 640px) {
            .form-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-sizing: border-box;
            background-color: #f9fafb;
            color: #111827;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem 1.5rem;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .btn:hover { background-color: #1d4ed8; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem; }
        .alert-danger { color: #b91c1c; background-color: #fef2f2; border: 1px solid #fca5a5; }
        .alert-success { color: #166534; background-color: #f0fdf4; border: 1px solid #bbf7d0; }
        .required-star { color: #ef4444; }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Student Profile</h2>
    <p>Use the form below to update student details.</p>

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
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($student['id'] ?? ''); ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label for="name">First Name <span class="required-star">*</span></label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($student['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="surname">Surname <span class="required-star">*</span></label>
                <input type="text" name="surname" id="surname" value="<?php echo htmlspecialchars($student['surname'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" name="middle_name" id="middle_name" value="<?php echo htmlspecialchars($student['middle_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" placeholder="Leave blank to keep current password">
            </div>
            <div class="form-group">
                <label for="reg_number">Registration Number (6 digits) <span class="required-star">*</span></label>
                <input type="text" name="reg_number" id="reg_number" value="<?php echo htmlspecialchars($student['registration_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="current_class">Current Class</label>
                <select name="current_class" id="current_class">
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo (isset($student['current_class_id']) && $student['current_class_id'] == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="roll_number">Roll Number</label>
                <input type="text" name="roll_number" id="roll_number" value="<?php echo htmlspecialchars($student['roll_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="section">Section</label>
                <input type="text" name="section" id="section" value="<?php echo htmlspecialchars($student['section'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <select name="gender" id="gender">
                    <option value="">-- Select Gender --</option>
                    <option value="Male" <?php echo (isset($student['gender']) && $student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo (isset($student['gender']) && $student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo (isset($student['gender']) && $student['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="mother_name">Mother's Name</label>
                <input type="text" name="mother_name" id="mother_name" value="<?php echo htmlspecialchars($student['mother_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="father_name">Father's Name</label>
                <input type="text" name="father_name" id="father_name" value="<?php echo htmlspecialchars($student['father_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="parent_phone">Parent's Phone Number</label>
                <input type="tel" name="parent_phone" id="parent_phone" value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="occupation">Occupation</label>
                <input type="text" name="occupation" id="occupation" value="<?php echo htmlspecialchars($student['occupation'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="previous_class">Previous Class</label>
                <input type="text" name="previous_class" id="previous_class" value="<?php echo htmlspecialchars($student['previous_class'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="previous_school">Previous School</label>
                <input type="text" name="previous_school" id="previous_school" value="<?php echo htmlspecialchars($student['previous_school'] ?? ''); ?>">
                <label class="mt-2 block"><input type="checkbox" id="same_school_checkbox" class="mr-2">Same as current school</label>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="unblocked" <?php echo (isset($student['status']) && $student['status'] == 'unblocked') ? 'selected' : ''; ?>>Unblocked</option>
                    <option value="blocked" <?php echo (isset($student['status']) && $student['status'] == 'blocked') ? 'selected' : ''; ?>>Blocked</option>
                </select>
            </div>
            <div class="form-group">
                <label for="blood_group">Blood Group</label>
                <input type="text" name="blood_group" id="blood_group" value="<?php echo htmlspecialchars($student['blood_group'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" name="dob" id="dob" value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="van_service">Take Van Service?</label>
                <select name="van_service" id="van_service_select" onchange="toggleVanField()">
                    <option value="0" <?php echo (isset($student['van_service_taken']) && $student['van_service_taken'] == 0) ? 'selected' : ''; ?>>No</option>
                    <option value="1" <?php echo (isset($student['van_service_taken']) && $student['van_service_taken'] == 1) ? 'selected' : ''; ?>>Yes</option>
                </select>
            </div>
            <div class="form-group" id="van_fields" style="display:none;">
                <label for="van_id">Assign Van</label>
                <select name="van_id" id="van_id">
                    <option value="">-- Select a Van --</option>
                    <?php foreach ($vans as $van): ?>
                        <option value="<?php echo $van['id']; ?>" <?php echo (isset($student['van_id']) && $student['van_id'] == $van['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($van['van_number'] . ' (' . $van['route_details'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-span-1 sm:col-span-2">
                <label for="address">Address</label>
                <textarea name="address" id="address" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="district">District</label>
                <input type="text" name="district" id="district" value="<?php echo htmlspecialchars($student['district'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="state">State</label>
                <input type="text" name="state" id="state" value="<?php echo htmlspecialchars($student['state'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="pincode">Pincode</label>
                <input type="text" name="pincode" id="pincode" value="<?php echo htmlspecialchars($student['pincode'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Current Image</label>
                <?php if (!empty($student['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($student['image_url']); ?>" alt="Student Profile Image" class="w-24 h-24 rounded-full object-cover">
                <?php else: ?>
                    <p class="text-gray-500">No image uploaded.</p>
                <?php endif; ?>
                <label for="student_image" class="mt-2 block">New Profile Image</label>
                <input type="file" name="student_image" id="student_image" accept="image/*">
            </div>
        </div>

        <div class="form-group mt-6 col-span-1 sm:col-span-2">
            <input type="submit" class="btn" value="Update Student Profile">
        </div>
    </form>
</div>

<script>
    function toggleVanField() {
        var vanServiceSelect = document.getElementById('van_service_select');
        var vanFields = document.getElementById('van_fields');
        vanFields.style.display = (vanServiceSelect.value === '1') ? 'block' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleVanField(); // Set initial state on page load

        const sameSchoolCheckbox = document.getElementById('same_school_checkbox');
        const previousSchoolInput = document.getElementById('previous_school');

        sameSchoolCheckbox.addEventListener('change', function() {
            if (this.checked) {
                // Assuming the school name is stored in a session or a constant
                // For this example, we'll use a placeholder
                previousSchoolInput.value = "Current School Name";
                previousSchoolInput.readOnly = true;
            } else {
                previousSchoolInput.value = "";
                previousSchoolInput.readOnly = false;
            }
        });
    });
</script>

</body>
</html>
<?php 
require_once './admin_footer.php';
if($link) mysqli_close($link);
?>
