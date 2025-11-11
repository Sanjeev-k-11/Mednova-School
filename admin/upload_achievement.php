<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../database/config.php"; // Assuming this holds DB_SERVER, DB_USERNAME, DB_PASSWORD
require_once "../database/cloudinary_upload_handler.php"; // Assuming this holds CLOUDINARY_CLOUD_NAME, etc.

// CORRECTED: Path to Composer's autoload.php
// This should be relative to upload_achievement.php
// If vendor is in 'new school/' directory, then from 'new school/admin/', it's '../vendor/autoload.php'
require '../database/vendor/autoload.php'; 

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

// --- AUTHENTICATION & AUTHORIZATION ---
// Use 'Admin' role as in your provided code. Adjust if it should be 'Super Admin'.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION['id'] ?? null;
$admin_role = $_SESSION['role'] ?? null; // Make sure this is set in login if used

$flash_message = '';
$flash_message_type = '';

// CORRECTED: Initialize Cloudinary Configuration AFTER requiring cloudinary_upload_handler.php
// This ensures CLOUDINARY_CLOUD_NAME, etc., are defined before Configuration::instance uses them.
try {
    
} catch (Exception $e) {
    // Catch configuration errors early
    $flash_message = "Cloudinary configuration error: " . $e->getMessage();
    $flash_message_type = 'error';
    error_log("Cloudinary Config Error: " . $e->getMessage());
    // Potentially exit or prevent form submission if config failed
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($flash_message)) { // Only proceed if no config error
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS));
    $achievement_date = filter_input(INPUT_POST, 'achievement_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $certificate_file = $_FILES['certificate_file'] ?? null;

    if (!$student_id || empty($title) || empty($achievement_date)) {
        $flash_message = "Please fill in all required fields (Student, Title, Achievement Date).";
        $flash_message_type = 'error';
    } else {
        $certificate_url = null;
        $upload_error = false;

        // --- CLOUDINARY UPLOAD ---
        if ($certificate_file && $certificate_file['error'] == UPLOAD_ERR_OK) {
            $allowed_file_types = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($certificate_file['type'], $allowed_file_types)) {
                $flash_message = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
                $flash_message_type = 'error';
                $upload_error = true;
            } elseif ($certificate_file['size'] > 5 * 1024 * 1024) { // 5 MB limit
                $flash_message = "File size exceeds 5MB limit.";
                $flash_message_type = 'error';
                $upload_error = true;
            } else {
                try {
                    // This is where UploadApi is instantiated (line 90 approximately in this corrected code)
                    // The `use` statement at the top (line 12) makes `UploadApi` globally available from Cloudinary namespace.
                    $upload_api = new UploadApi(); 
                    $upload_result = $upload_api->upload(
                        $certificate_file['tmp_name'],
                        ['folder' => 'school_certificates', 'resource_type' => 'auto']
                    );
                    $certificate_url = $upload_result['secure_url'];
                } catch (Exception $e) {
                    $flash_message = "Certificate upload failed: " . $e->getMessage();
                    $flash_message_type = 'error';
                    $upload_error = true;
                    error_log("Cloudinary Upload Error: " . $e->getMessage());
                }
            }
        } elseif ($certificate_file && $certificate_file['error'] != UPLOAD_ERR_NO_FILE) {
            $flash_message = "File upload error: " . $certificate_file['error'];
            $flash_message_type = 'error';
            $upload_error = true;
        }

        if (!$upload_error) {
            // --- DATABASE INSERTION ---
            try {
                // Ensure $pdo is created only once or passed globally if config.php doesn't do it
                // Assuming config.php provides DB_SERVER, DB_NAME, etc. and PDO needs to be created.
                $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql_insert = "
                    INSERT INTO student_achievements 
                    (student_id, title, description, achievement_date, certificate_url, uploaded_by_user_id, uploaded_by_role)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt = $pdo->prepare($sql_insert);
                $stmt->execute([$student_id, $title, $description, $achievement_date, $certificate_url, $admin_id, $admin_role]);

                $flash_message = "Achievement and certificate uploaded successfully! ✨";
                $flash_message_type = 'success';
                $_POST = []; // Clear form data to prevent re-filling on refresh
            } catch (PDOException $e) {
                $flash_message = "Database error: " . $e->getMessage();
                $flash_message_type = 'error';
                error_log("DB Insert Achievement Error: " . $e->getMessage());
            }
        }
    }
}

// --- DATA FETCHING FOR DROPDOWNS ---
$students = [];
try {
    // Re-establish PDO connection if needed, or use a global $pdo if config.php handles it that way
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql_students = "SELECT id, first_name, last_name, roll_number FROM students ORDER BY first_name, last_name";
    $stmt = $pdo->query($sql_students);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB Fetch Students Error: " . $e->getMessage());
    $flash_message = "Error loading student list.";
    $flash_message_type = 'error';
}
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Student Achievement</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-container {
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="container mx-auto p-4 md:p-8 mt-16 form-container">
    <?php if ($flash_message): ?>
        <div class="max-w-4xl mx-auto mb-6 p-4 rounded-lg text-white font-semibold transition-all duration-300 transform scale-100 <?php echo ($flash_message_type === 'error') ? 'bg-red-600' : (($flash_message_type === 'success') ? 'bg-green-600' : 'bg-blue-600'); ?> shadow-md">
            <div class="flex items-center">
                <?php if ($flash_message_type === 'success'): ?>
                    <i class="fas fa-check-circle mr-3 text-2xl"></i>
                <?php elseif ($flash_message_type === 'error'): ?>
                    <i class="fas fa-exclamation-triangle mr-3 text-2xl"></i>
                <?php else: ?>
                    <i class="fas fa-info-circle mr-3 text-2xl"></i>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($flash_message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-xl p-8 mb-8 text-center border-t-4 border-indigo-500">
        <h1 class="text-4xl font-extrabold text-gray-900 mb-2 tracking-tight">Upload Student Achievement</h1>
        <p class="text-gray-600 text-lg">Record a student's success and attach their certificate for posterity.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-4xl mx-auto border-b-4 border-indigo-500">
        <!-- CORRECTED: Form action should be to the same file or a dedicated processing file -->
        <!-- I've assumed it's meant to be processed by this file, so action is removed, or explicitly set to current file name -->
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                <div>
                    <label for="student_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        Select Student <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select id="student_id" name="student_id" required class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200">
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo htmlspecialchars($student['id']); ?>"
                                    <?php echo (isset($_POST['student_id']) && $_POST['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (Roll: ' . $student['roll_number'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-700">
                            <i class="fas fa-chevron-down text-sm"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">
                        Achievement Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="title" id="title" required maxlength="255"
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
                           placeholder="e.g., Robotics Competition Winner">
                </div>

                <div class="col-span-1 md:col-span-2">
                    <label for="achievement_date" class="block text-sm font-semibold text-gray-700 mb-2">
                        Achievement Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="achievement_date" id="achievement_date" required
                           value="<?php echo htmlspecialchars($_POST['achievement_date'] ?? date('Y-m-d')); ?>"
                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200">
                </div>
            </div>

            <div class="mb-6">
                <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" id="description" rows="4"
                          class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
                          placeholder="Provide more details about the achievement..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="mb-6">
                <label for="certificate_file" class="block text-sm font-semibold text-gray-700 mb-2">
                    Upload Certificate <span class="text-gray-500 text-xs">(JPG, PNG, PDF - Max 5MB)</span>
                </label>
                <input type="file" name="certificate_file" id="certificate_file" accept=".jpg,.jpeg,.png,.pdf"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-100 file:text-indigo-700 hover:file:bg-indigo-200 transition-colors duration-200">
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-full shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                    <i class="fas fa-upload mr-3"></i>
                    Upload Achievement
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>

<?php require_once './admin_footer.php';
?>