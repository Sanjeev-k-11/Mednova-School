<?php
session_start();
require_once "../database/config.php";
// Assuming you have a file for your Cloudinary/upload functions.
// If not, you'll need to implement the upload logic.
// require_once "../database/cloudinary_upload_handler.php"; 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
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

// --- Handle initial page load to get student ID ---
// This part runs for both GET and before the POST logic.
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if ($id <= 0) {
    // If no ID is provided in GET or POST, we can't proceed.
    header("location: view_students.php");
    exit;
}


// --- Fetch data for dropdowns ---
// FIXED: Changed 'section' to 'section_name' to match your schema.
$sql_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name, section_name";
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

// --- Handle GET request to fetch student data for display ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $sql = "SELECT * FROM students WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        if (!$student) {
            // If student with that ID doesn't exist, redirect.
            header("location: view_students.php");
            exit;
        }
    }
}

// --- Handle POST request to update student data ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ID is already set from the top of the script.

    // --- Retrieve and validate all POST data ---
    $first_name = trim($_POST["first_name"]);
    if (empty($first_name)) { $errors[] = "First name is required."; }
    
    $last_name = trim($_POST["last_name"]);
    if (empty($last_name)) { $errors[] = "Last name is required."; }
    
    // ADDED: Validation for required class_id
    $class_id = $_POST["class_id"];
    if (empty($class_id)) { $errors[] = "Current class is required."; }

    $email = trim($_POST["email"]);
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email format."; }
    
    // Other fields
    $middle_name = trim($_POST["middle_name"]);
    $phone_number = trim($_POST["phone_number"]);
    $address = trim($_POST["address"]);
    $pincode = trim($_POST["pincode"]);
    $district = trim($_POST["district"]);
    $state = trim($_POST["state"]);
    $password = trim($_POST["password"]);
    $registration_number = trim($_POST["registration_number"]); // It's readonly, but we still get it
    $roll_number = trim($_POST["roll_number"]);
    $gender = trim($_POST["gender"]);
    $mother_name = trim($_POST["mother_name"]);
    $father_name = trim($_POST["father_name"]);
    $parent_phone_number = trim($_POST["parent_phone_number"]);
    $father_occupation = trim($_POST["father_occupation"]);
    $previous_class = trim($_POST["previous_class"]);
    $previous_school = trim($_POST["previous_school"]);
    $blood_group = trim($_POST["blood_group"]);
    $dob = trim($_POST["dob"]);
    $status = trim($_POST["status"]);

    $van_service_taken = (isset($_POST['van_service_taken']) && $_POST['van_service_taken'] == '1') ? 1 : 0;
    $van_id = null;
    if ($van_service_taken) {
        if (empty($_POST['van_id'])) { $errors[] = "Please select a van if van service is taken."; }
        else { $van_id = (int)$_POST['van_id']; }
    }
    
    // Get the current student data for password and image fallbacks
    $sql_get_current = "SELECT password, image_url FROM students WHERE id = ?";
    $stmt_current = mysqli_prepare($link, $sql_get_current);
    mysqli_stmt_bind_param($stmt_current, "i", $id);
    mysqli_stmt_execute($stmt_current);
    $result_current = mysqli_stmt_get_result($stmt_current);
    $current_data = mysqli_fetch_assoc($result_current);
    mysqli_stmt_close($stmt_current);

    // --- Image Upload Logic ---
    $image_url = $current_data['image_url']; // Default to old image
    if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] == UPLOAD_ERR_OK) {
        // This is where you would call your actual image upload function
        // e.g., $uploadResult = uploadToCloudinary($_FILES['student_image']);
        // For demonstration, we'll use a placeholder URL.
        $uploadResult = ["secure_url" => "https://placeholder.com/new_image.jpg"]; 
        if (isset($uploadResult['error'])) { 
            $errors[] = "Image Upload Failed: " . $uploadResult['error']; 
        } else { 
            // If you upload a new image, you might want to delete the old one here
            // using the public_id from your database if you store it.
            $image_url = $uploadResult['secure_url']; 
        }
    }

    // --- Password Logic (only update if a new one is entered) ---
    $hashed_password = $current_data['password'];
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    }
    
    // If no validation errors, proceed to database update
    if (empty($errors)) {
        $sql = "UPDATE students SET
            first_name=?, last_name=?, middle_name=?, phone_number=?, address=?, pincode=?, district=?, state=?, email=?,
            password=?, registration_number=?, image_url=?, class_id=?, roll_number=?, gender=?, mother_name=?, father_name=?,
            parent_phone_number=?, father_occupation=?, previous_class=?, previous_school=?, status=?, blood_group=?,
            dob=?, van_service_taken=?, van_id=?
        WHERE id=?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind parameters to the prepared statement
            mysqli_stmt_bind_param($stmt, "sssssssssssssississsssssiii",
                $first_name, $last_name, $middle_name, $phone_number, $address, $pincode, $district, $state, $email,
                $hashed_password, $registration_number, $image_url, $class_id, $roll_number, $gender,
                $mother_name, $father_name, $parent_phone_number, $father_occupation, $previous_class,
                $previous_school, $status, $blood_group, $dob, $van_service_taken, $van_id, $id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Student details updated successfully.";
                // Refresh student data from DB to show the latest info
                 $sql_refresh = "SELECT * FROM students WHERE id = ?";
                if ($stmt_refresh = mysqli_prepare($link, $sql_refresh)) {
                    mysqli_stmt_bind_param($stmt_refresh, "i", $id);
                    mysqli_stmt_execute($stmt_refresh);
                    $result_refresh = mysqli_stmt_get_result($stmt_refresh);
                    $student = mysqli_fetch_assoc($result_refresh);
                    mysqli_stmt_close($stmt_refresh);
                }
            } else {
                $errors[] = "Database update failed. Please try again. Error: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        }
    } 
    
    if (!empty($errors)) {
        // If errors, repopulate the $student array with POST data to refill the form correctly
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
        @media (min-width: 640px) { .form-grid { grid-template-columns: repeat(2, 1fr); } }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem;
            box-sizing: border-box; background-color: #f9fafb; color: #111827;
        }
        .btn {
            display: block; width: 100%; padding: 0.75rem 1.5rem; background-color: #2563eb; color: white;
            border: none; border-radius: 0.5rem; cursor: pointer; font-size: 1rem; font-weight: 600;
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

    <!-- IMPROVED: The form action includes the student ID to be more robust on re-submission -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . htmlspecialchars($id); ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($student['id'] ?? ''); ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label for="first_name">First Name <span class="required-star">*</span></label>
                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name <span class="required-star">*</span></label>
                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" name="middle_name" id="middle_name" value="<?php echo htmlspecialchars($student['middle_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($student['phone_number'] ?? ''); ?>">
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
                <label for="registration_number">Registration Number <span class="required-star">*</span></label>
                <input type="text" name="registration_number" id="registration_number" value="<?php echo htmlspecialchars($student['registration_number'] ?? ''); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="class_id">Current Class <span class="required-star">*</span></label>
                <select name="class_id" id="class_id" required>
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo (isset($student['class_id']) && $student['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                            <!-- FIXED: Changed to section_name -->
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="roll_number">Roll Number</label>
                <input type="text" name="roll_number" id="roll_number" value="<?php echo htmlspecialchars($student['roll_number'] ?? ''); ?>">
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
                <label for="parent_phone_number">Parent's Phone Number</label>
                <input type="tel" name="parent_phone_number" id="parent_phone_number" value="<?php echo htmlspecialchars($student['parent_phone_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="father_occupation">Occupation</label>
                <input type="text" name="father_occupation" id="father_occupation" value="<?php echo htmlspecialchars($student['father_occupation'] ?? ''); ?>">
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
                    <option value="Active" <?php echo (isset($student['status']) && $student['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Blocked" <?php echo (isset($student['status']) && $student['status'] == 'Blocked') ? 'selected' : ''; ?>>Blocked</option>
                </select>
            </div>
            <!-- IMPLEMENTED: Blood group changed to a select dropdown -->
            <div class="form-group">
                <label for="blood_group">Blood Group</label>
                <select name="blood_group" id="blood_group">
                    <option value="">-- Select Blood Group --</option>
                    <?php 
                        $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        foreach ($blood_groups as $bg) {
                            $selected = (isset($student['blood_group']) && $student['blood_group'] == $bg) ? 'selected' : '';
                            echo "<option value=\"$bg\" $selected>$bg</option>";
                        }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" name="dob" id="dob" value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="van_service_taken">Take Van Service?</label>
                <select name="van_service_taken" id="van_service_select" onchange="toggleVanField()">
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
                <label for="student_image" class="mt-2 block">Upload New Profile Image</label>
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
                // NOTE: Replace "Your School Name" with the actual name of your school
                previousSchoolInput.value = "Your School Name";
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