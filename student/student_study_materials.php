<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./student_header.php";  // Includes student-specific authentication and sidebar

// --- BACKEND LOGIC ---
// Authenticate as Student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php"); // Redirect to login page if not logged in as a student
    exit;
}

// Get the logged-in student's ID from the session
$student_id = $_SESSION['id'] ?? null; 

// --- CRITICAL CHECK: Validate student_id from session ---
if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Authentication Error!</strong>
            <span class='block sm:inline'> Your student ID is missing or invalid in the session. Please log in again.</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

// Fetch student's class information
$student_class_info = null;
$sql_student_class = "
    SELECT 
        s.class_id,
        c.class_name,
        c.section_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
    LIMIT 1";

if ($stmt = mysqli_prepare($link, $sql_student_class)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_class_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    error_log("Database Error preparing student class query: " . mysqli_error($link));
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Database Error!</strong>
            <span class='block sm:inline'> Could not prepare student class query.</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

if (!$student_class_info) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Error:</strong>
            <span class='block sm:inline'> Your class information could not be found. Please contact administration.</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

$class_id = $student_class_info['class_id'];
$class_name = htmlspecialchars($student_class_info['class_name'] . ' - ' . $student_class_info['section_name']);

$selected_subject_id = $_GET['subject_id'] ?? null;
$subjects_for_class = [];
$study_materials = [];

// 1. Fetch subjects for the student's class (for filter dropdown)
$sql_subjects = "
    SELECT DISTINCT
        s.id,
        s.subject_name
    FROM subjects s
    JOIN class_subjects cs ON s.id = cs.subject_id
    WHERE cs.class_id = ?
    ORDER BY s.subject_name";

if ($stmt = mysqli_prepare($link, $sql_subjects)) {
    mysqli_stmt_bind_param($stmt, "i", $class_id);
    mysqli_stmt_execute($stmt);
    $subjects_for_class = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("Database Error preparing subjects query: " . mysqli_error($link));
}

// 2. Fetch study materials for the student's class, optionally filtered by subject
$sql_materials = "
    SELECT
        sm.id,
        sm.title,
        sm.description,
        sm.file_name,
        sm.file_url,
        sm.uploaded_at,
        sub.subject_name,
        t.full_name AS teacher_name
    FROM study_materials sm
    JOIN subjects sub ON sm.subject_id = sub.id
    JOIN teachers t ON sm.teacher_id = t.id
    WHERE sm.class_id = ?
";

$params = [$class_id];
$types = "i";

if ($selected_subject_id && is_numeric($selected_subject_id)) {
    $sql_materials .= " AND sm.subject_id = ?";
    $params[] = $selected_subject_id;
    $types .= "i";
}

$sql_materials .= " ORDER BY sm.uploaded_at DESC";

if ($stmt = mysqli_prepare($link, $sql_materials)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $study_materials = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    error_log("Database Error preparing study materials query: " . mysqli_error($link));
}

mysqli_close($link);
?>

<!-- Custom Styles for a clean, professional look -->
<style>
    body { background-color: #f8f9fc; font-family: 'Inter', sans-serif; }
    .card-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
    .card-hover { transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
    .card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
</style>

<div class="container mx-auto mt-28 mb-12 p-4 md:p-8">
    <!-- Main Header Card -->
    <div class="bg-white rounded-xl card-shadow p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Study Materials</h1>
        <p class="text-gray-600">Access your class study resources here. You are viewing materials for: <strong class="text-indigo-600"><?php echo $class_name; ?></strong></p>
    </div>

    <!-- Filter Section -->
    <div class="bg-white rounded-xl card-shadow p-6 mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Filter by Subject</h2>
        <form action="student_study_materials.php" method="GET" class="flex flex-col sm:flex-row gap-4 items-center">
            <div class="flex-grow w-full">
                <label for="subject_id" class="block text-sm font-medium text-gray-700 sr-only">Subject</label>
                <select id="subject_id" name="subject_id" class="w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="">-- All Subjects --</option>
                    <?php foreach ($subjects_for_class as $subject): ?>
                        <option value="<?php echo htmlspecialchars($subject['id']); ?>" <?php echo ($selected_subject_id == $subject['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-shrink-0 w-full sm:w-auto">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md">
                    Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Study Materials List -->
    <div class="bg-white rounded-xl card-shadow p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Available Materials</h2>
        <?php if (empty($study_materials)): ?>
            <div class="text-center p-12 border-2 border-dashed border-gray-300 rounded-xl text-gray-500">
                <i class="fas fa-box-open fa-3x mb-4"></i>
                <p class="text-lg">No study materials found for <?php echo !empty($selected_subject_id) ? "this subject" : "your class"; ?> yet.</p>
                <p class="text-sm mt-2">Please check back later or contact your teachers.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($study_materials as $material): ?>
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm card-hover flex flex-col">
                        <div class="p-5 flex-grow">
                            <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($material['title']); ?></h3>
                            <div class="text-sm text-indigo-600 font-medium mb-1">
                                <i class="fas fa-book-open mr-2"></i> Subject: <?php echo htmlspecialchars($material['subject_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500 mb-2">
                                <i class="fas fa-user-circle mr-2"></i> Uploaded by: <?php echo htmlspecialchars($material['teacher_name']); ?>
                            </div>
                            <p class="text-gray-700 text-sm mb-4 line-clamp-3">
                                <?php echo htmlspecialchars($material['description'] ?: 'No description provided.'); ?>
                            </p>
                            <div class="text-xs text-gray-400 mt-auto">
                                <i class="fas fa-clock mr-1"></i> Uploaded on: <?php echo date('M d, Y', strtotime($material['uploaded_at'])); ?>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-5 py-4 flex justify-between items-center rounded-b-lg border-t border-gray-200">
                            <a href="<?php echo htmlspecialchars($material['file_url']); ?>" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-800 font-medium text-sm flex items-center">
                                <i class="fas fa-download mr-2"></i> View / Download
                            </a>
                            <span class="text-xs text-gray-500 truncate max-w-[50%]"><?php echo htmlspecialchars($material['file_name']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once "./student_footer.php"; // Closes the main-page-content div and HTML tags
?>
