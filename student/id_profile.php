<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login.php"); 
    exit;
}

$user_id = $_SESSION['id'] ?? null; 
$user_role = $_SESSION['role'] ?? null;

if (!isset($user_id) || !is_numeric($user_id) || empty($user_role)) {
    // If essential session data is missing, redirect to login
    $_SESSION['flash_message'] = "Your session is invalid. Please log in again.";
    $_SESSION['flash_message_type'] = 'error';
    header("location: ../login.php");
    exit();
}

$profile_data = null;
$error_message = '';
$default_avatar_url = '../assets/images/default-avatar.png'; // Adjust path as needed

// --- Dynamic Profile Data Fetching ---
$sql = "";
$param_type = "i"; // All user IDs are INT

switch ($user_role) {
    case 'Super Admin':
        $sql = "
            SELECT 
                id, full_name, role, super_admin_id AS unique_id, email, address, image_url, gender, created_at
            FROM super_admin 
            WHERE id = ? LIMIT 1";
        break;
    case 'Admin':
        $sql = "
            SELECT 
                id, admin_id AS unique_id, username, full_name, email, phone_number, address, gender, role, salary, qualification, join_date, dob, image_url, created_at
            FROM admins 
            WHERE id = ? LIMIT 1";
        break;
    case 'Principle':
        $sql = "
            SELECT 
                id, principle_code AS unique_id, full_name, phone_number, address, pincode, email, image_url, salary, qualification, role, gender, blood_group, dob, years_of_experience, date_of_joining, created_at
            FROM principles 
            WHERE id = ? LIMIT 1";
        break;
    case 'Teacher':
        $sql = "
            SELECT 
                id, teacher_code AS unique_id, full_name, email, phone_number, address, pincode, image_url, salary, qualification, subject_taught, years_of_experience, gender, blood_group, dob, date_of_joining, created_at
            FROM teachers 
            WHERE id = ? LIMIT 1";
        break;
    case 'Student':
        $sql = "
            SELECT 
                s.id, s.registration_number AS unique_id, s.first_name, s.middle_name, s.last_name, s.dob, s.gender, s.blood_group, s.image_url, s.phone_number, s.email, s.address, s.pincode, s.district, s.state, s.father_name, s.mother_name, s.parent_phone_number, s.father_occupation,
                c.class_name, c.section_name, s.roll_number, s.admission_date, s.created_at
            FROM students s
            JOIN classes c ON s.class_id = c.id
            WHERE s.id = ? LIMIT 1";
        break;
    default:
        $error_message = "Unrecognized user role: " . htmlspecialchars($user_role) . ".";
        break;
}

if (empty($error_message) && !empty($sql)) {
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, $param_type, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $profile_data = mysqli_fetch_assoc($result);
            if (!$profile_data) {
                $error_message = "Profile not found for User ID: " . htmlspecialchars($user_id) . " with role: " . htmlspecialchars($user_role) . ".";
            }
        } else {
            $error_message = "Database query failed: " . mysqli_stmt_error($stmt);
            error_log("Profile Fetch Error: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Failed to prepare database query: " . mysqli_error($link);
        error_log("Profile Query Prepare Error: " . mysqli_error($link));
    }
}

mysqli_close($link);

// Include the appropriate header and footer based on role, or a generic one.
// For this example, we'll include a generic header.php and footer.php
require_once "./student_header.php"; // Adjust this path if your headers are role-specific.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo htmlspecialchars($user_role); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .profile-card {
            background-image: linear-gradient(to right top, #d1d5db, #e5e7eb, #f3f4f6, #f9fafb, #ffffff);
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
<!-- The header.php content is assumed to be included here -->

<div class="container mx-auto p-4 md:p-8 mt-16">
    <?php if ($error_message || !$profile_data): ?>
        <div class="max-w-xl mx-auto bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-center shadow-md">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message ?: "Profile data could not be loaded."); ?></span>
            <p class="mt-2 text-sm">Please ensure your account is properly configured or contact support.</p>
        </div>
    <?php else: ?>
        <div class="profile-card max-w-4xl mx-auto rounded-2xl shadow-xl overflow-hidden border-t-8 border-indigo-600">
            <div class="bg-indigo-600 text-white p-6 md:p-8 text-center relative">
                <img src="<?php echo htmlspecialchars($profile_data['image_url'] ?? $default_avatar_url); ?>" 
                     alt="<?php echo htmlspecialchars($profile_data['full_name'] ?? $profile_data['first_name'] . ' ' . $profile_data['last_name']); ?> Profile"
                     class="w-32 h-32 rounded-full object-cover border-4 border-white mx-auto shadow-lg mb-4">
                
                <h1 class="text-3xl md:text-4xl font-extrabold mb-1"><?php echo htmlspecialchars($profile_data['full_name'] ?? ($profile_data['first_name'] . ' ' . ($profile_data['middle_name'] ? $profile_data['middle_name'] . ' ' : '') . $profile_data['last_name'])); ?></h1>
                <p class="text-indigo-200 text-lg font-semibold"><?php echo htmlspecialchars($user_role); ?></p>
            </div>

            <div class="p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 text-gray-800">
                <!-- Common Details -->
                <div class="mb-4 md:mb-0">
                    <p class="text-sm text-gray-500">Unique ID / Registration No.</p>
                    <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['unique_id'] ?? 'N/A'); ?></p>
                </div>
                <div class="mb-4 md:mb-0">
                    <p class="text-sm text-gray-500">Email Address</p>
                    <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['email'] ?? 'N/A'); ?></p>
                </div>

                <?php if (isset($profile_data['phone_number'])): ?>
                <div class="mb-4 md:mb-0">
                    <p class="text-sm text-gray-500">Phone Number</p>
                    <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['phone_number'] ?? 'N/A'); ?></p>
                </div>
                <?php endif; ?>

                <?php if (isset($profile_data['gender'])): ?>
                <div class="mb-4 md:mb-0">
                    <p class="text-sm text-gray-500">Gender</p>
                    <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['gender'] ?? 'N/A'); ?></p>
                </div>
                <?php endif; ?>

                <?php if (isset($profile_data['dob'])): ?>
                <div class="mb-4 md:mb-0">
                    <p class="text-sm text-gray-500">Date of Birth</p>
                    <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['dob'] ? date('M d, Y', strtotime($profile_data['dob'])) : 'N/A'); ?></p>
                </div>
                <?php endif; ?>

                <?php if (isset($profile_data['address'])): ?>
                <div class="md:col-span-2 mb-4 md:mb-0">
                    <p class="text-sm text-gray-500">Address</p>
                    <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['address'] ?? 'N/A'); ?></p>
                </div>
                <?php endif; ?>

                <!-- Role-Specific Details -->
                <?php if ($user_role === 'Admin' || $user_role === 'Principle' || $user_role === 'Teacher'): ?>
                    <?php if (isset($profile_data['salary'])): ?>
                    <div>
                        <p class="text-sm text-gray-500">Salary</p>
                        <p class="text-lg font-medium">₹<?php echo htmlspecialchars(number_format($profile_data['salary'], 2) ?? 'N/A'); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($profile_data['qualification'])): ?>
                    <div>
                        <p class="text-sm text-gray-500">Qualification</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['qualification'] ?? 'N/A'); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($profile_data['join_date'])): ?>
                    <div>
                        <p class="text-sm text-gray-500">Join Date</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['join_date'] ? date('M d, Y', strtotime($profile_data['join_date'])) : 'N/A'); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($profile_data['years_of_experience'])): ?>
                    <div>
                        <p class="text-sm text-gray-500">Years of Experience</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['years_of_experience'] ?? '0'); ?> yrs</p>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($user_role === 'Teacher'): ?>
                    <div>
                        <p class="text-sm text-gray-500">Subject Taught</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['subject_taught'] ?? 'N/A'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($user_role === 'Student'): ?>
                    <div>
                        <p class="text-sm text-gray-500">Class</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['class_name'] . ' - ' . $profile_data['section_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Roll Number</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['roll_number'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Admission Date</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['admission_date'] ? date('M d, Y', strtotime($profile_data['admission_date'])) : 'N/A'); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500">Father's Name</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['father_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500">Mother's Name</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['mother_name'] ?? 'N/A'); ?></p>
                    </div>
                    <?php if (isset($profile_data['parent_phone_number'])): ?>
                    <div>
                        <p class="text-sm text-gray-500">Parent Phone</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['parent_phone_number'] ?? 'N/A'); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($profile_data['father_occupation'])): ?>
                    <div>
                        <p class="text-sm text-gray-500">Father's Occupation</p>
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['father_occupation'] ?? 'N/A'); ?></p>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- General Audit Fields -->
                <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-500">Account Created On</p>
                    <p class="text-lg font-medium"><?php echo htmlspecialchars($profile_data['created_at'] ? date('M d, Y h:i A', strtotime($profile_data['created_at'])) : 'N/A'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons (e.g., Edit Profile) -->
        <div class="max-w-4xl mx-auto mt-6 flex justify-end">
            <a href="#" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-full shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-edit mr-3"></i> Edit Profile (Coming Soon)
            </a>
        </div>

    <?php endif; ?>
</div>

<!-- The footer.php content is assumed to be included here -->
<?php require_once "./student_footer.php"; // Adjust this path if your footers are role-specific. ?>
</body>
</html>