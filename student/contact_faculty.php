<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}
$student_id = $_SESSION["id"];
$student_class_id = null;

// --- Get Student's Class ID (Robustly) ---
if (isset($_SESSION["class_id"])) {
    $student_class_id = $_SESSION["class_id"];
} else {
    // Fallback: If not in session, fetch it directly from the database.
    $sql_get_class = "SELECT class_id FROM students WHERE id = ? LIMIT 1";
    if ($stmt_get_class = mysqli_prepare($link, $sql_get_class)) {
        mysqli_stmt_bind_param($stmt_get_class, "i", $student_id);
        mysqli_stmt_execute($stmt_get_class);
        mysqli_stmt_bind_result($stmt_get_class, $fetched_class_id);
        if (mysqli_stmt_fetch($stmt_get_class)) {
            $student_class_id = $fetched_class_id;
            $_SESSION["class_id"] = $fetched_class_id; // Update session for future pages
        }
        mysqli_stmt_close($stmt_get_class);
    }
}

if (!$student_class_id) {
    die("Error: Could not determine your class. Please contact an administrator.");
}

// --- Fetch Faculty Members for the Student's Class and Group by Department ---
$faculty_by_department = [];
$sql = "SELECT 
            t.full_name,
            t.email,
            t.phone_number,
            t.image_url,
            s.subject_name,
            d.department_name
        FROM class_subject_teacher cst
        JOIN teachers t ON cst.teacher_id = t.id
        JOIN subjects s ON cst.subject_id = s.id
        LEFT JOIN departments d ON t.department_id = d.id
        WHERE cst.class_id = ?
        ORDER BY d.department_name, t.full_name, s.subject_name";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_class_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        // Group teachers by their department name. Handle those without a department.
        $department = $row['department_name'] ?? 'General Faculty';
        $faculty_by_department[$department][] = $row;
    }
    mysqli_stmt_close($stmt);
}

require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Faculty</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-6xl p-4 sm:p-6">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-bold text-gray-800 tracking-tight">Faculty Directory</h1>
        <p class="text-gray-600 mt-2 text-lg">Contact information for the teachers in your class.</p>
    </div>

    <?php if (empty($faculty_by_department)): ?>
        <div class="text-center py-16 px-6 bg-white rounded-2xl shadow-md">
            <i class="fas fa-user-slash text-5xl text-gray-400"></i>
            <h3 class="text-2xl font-bold text-gray-700 mt-4">No Faculty Found</h3>
            <p class="mt-2 text-gray-500">There is no faculty information available for your class at this time.</p>
        </div>
    <?php else: ?>
        <?php foreach ($faculty_by_department as $department => $teachers): ?>
            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-700 border-b-2 border-blue-500 pb-2 mb-6">
                    <?php echo htmlspecialchars($department); ?>
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($teachers as $teacher): ?>
                        <div class="bg-white rounded-xl shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                            <div class="p-6 flex items-center gap-5">
                                <!-- Avatar -->
                                <div class="flex-shrink-0">
                                    <?php if (!empty($teacher['image_url'])): ?>
                                        <img class="w-20 h-20 rounded-full object-cover" src="<?php echo htmlspecialchars($teacher['image_url']); ?>" alt="<?php echo htmlspecialchars($teacher['full_name']); ?>">
                                    <?php else: ?>
                                        <div class="w-20 h-20 rounded-full bg-blue-200 text-blue-700 flex items-center justify-center text-3xl font-bold">
                                            <?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Details -->
                                <div class="flex-grow">
                                    <h3 class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($teacher['full_name']); ?></h3>
                                    <p class="text-sm text-blue-600 font-semibold"><?php echo htmlspecialchars($teacher['subject_name']); ?></p>
                                </div>
                            </div>
                            <!-- Contact Info -->
                            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 space-y-3">
                                <?php if (!empty($teacher['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($teacher['email']); ?>" class="flex items-center gap-3 text-gray-600 hover:text-blue-700 transition-colors">
                                    <i class="fas fa-envelope w-4 text-center text-gray-400"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($teacher['email']); ?></span>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($teacher['phone_number'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($teacher['phone_number']); ?>" class="flex items-center gap-3 text-gray-600 hover:text-blue-700 transition-colors">
                                    <i class="fas fa-phone w-4 text-center text-gray-400"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($teacher['phone_number']); ?></span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
<?php require_once './student_footer.php'; ?>