<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
// You might need to adjust this path and file name.
// If this file is in the admin folder, it would be admin_header.php.
// If this is a shared directory, ensure header.php exists or adjust.
require_once "./student_header.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Faculty Directory</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .toast {
            animation: slideIn 0.5s forwards, fadeOut 0.5s 4.5s forwards;
        }
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

<?php
// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login.php"); 
    exit;
}

$search_query = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? 'all'; // 'all', 'teacher', 'principle'

$faculty_members = [];

// --- SQL to fetch combined faculty data ---
$sql_parts = [];
$params = [];
$types = '';

// Add WHERE clause for search
$search_sql = '';
if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    $search_sql = " WHERE (full_name LIKE ? OR email LIKE ?)";
    $params = array_merge($params, [$search_param, $search_param]);
    $types .= 'ss';
}

// Add query for Teachers
if ($filter_role === 'all' || $filter_role === 'teacher') {
    $sql_teachers = "
        SELECT 
            id,
            full_name,
            email,
            phone_number,
            image_url,
            'Teacher' AS role_display,
            qualification,
            years_of_experience
        FROM teachers
        " . $search_sql;
    $sql_parts[] = $sql_teachers;
}

// Add query for Principles
if ($filter_role === 'all' || $filter_role === 'principle') {
    $sql_principles = "
        SELECT
            id,
            full_name,
            email,
            phone_number,
            image_url,
            'Principle' AS role_display,
            qualification,
            years_of_experience
        FROM principles
        " . $search_sql;
    $sql_parts[] = $sql_principles;
}

// Combine queries with UNION ALL
if (!empty($sql_parts)) {
    $sql_union = implode(" UNION ALL ", $sql_parts);
    $sql_union .= " ORDER BY full_name ASC";

    if ($stmt = mysqli_prepare($link, $sql_union)) {
        // If there are search parameters, bind them for each part of the UNION
        if (!empty($search_query)) {
            $full_types = '';
            $full_params = [];
            foreach($sql_parts as $part) {
                // For each part, two 's' types and two search_param params
                $full_types .= $types;
                $full_params = array_merge($full_params, [$search_param, $search_param]);
            }
            mysqli_stmt_bind_param($stmt, $full_types, ...$full_params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $faculty_members[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Database Error preparing faculty query: " . mysqli_error($link));
        // Fallback message
        $_SESSION['flash_message'] = "Error loading faculty directory.";
        $_SESSION['flash_message_type'] = 'error';
    }
} else {
    $_SESSION['flash_message'] = "No faculty roles selected for display. Please select a filter.";
    $_SESSION['flash_message_type'] = 'info';
}


mysqli_close($link);

// Define default avatar URL
$default_avatar_url = '../assets/images/default-avatar.png'; // Adjust path as needed
?>

<!-- Toast Notification Container -->
<div id="toast-container" class="  mt-28 top-5 right-5 z-50 w-full max-w-sm pointer-events-none">
    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="toast p-4 rounded-lg shadow-xl text-white pointer-events-auto transition-all duration-300 transform
        <?php echo ($_SESSION['flash_message_type'] === 'error') ? 'bg-red-500' : 'bg-blue-500'; ?>">
        <div class="flex items-center">
            <i class="mr-2
            <?php echo ($_SESSION['flash_message_type'] === 'error') ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle'; ?>"></i>
            <span class="font-semibold">
                <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
            </span>
        </div>
    </div>
    <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_message_type']); ?>
    <?php endif; ?>
</div>

<div class="bg-gray-100 min-h-screen p-4 sm:p-6 lg:p-8">
    <!-- Main Header Card -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2 sm:text-4xl">School Faculty Directory</h1>
        <p class="text-gray-600">Browse our dedicated team of educators and staff.</p>
    </div>

    <!-- Filter and Search Section -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <form action="faculty_directory.php" method="GET" class="flex flex-col md:flex-row gap-4 justify-between items-center">
            <div class="flex-grow flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                <div class="w-full sm:w-auto md:flex-grow">
                    <label for="search" class="sr-only">Search Faculty</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" name="search" id="search" placeholder="Search by name or email..."
                            value="<?php echo htmlspecialchars($search_query); ?>"
                            class="block w-full rounded-md border-gray-300 pl-10 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2">
                    </div>
                </div>
                <div class="w-full sm:w-auto">
                    <label for="role" class="sr-only">Filter by Role</label>
                    <select name="role" id="role"
                            class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md shadow-sm">
                        <option value="all" <?php echo ($filter_role === 'all') ? 'selected' : ''; ?>>All Faculty</option>
                        <option value="teacher" <?php echo ($filter_role === 'teacher') ? 'selected' : ''; ?>>Teachers</option>
                        <option value="principle" <?php echo ($filter_role === 'principle') ? 'selected' : ''; ?>>Principles</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-md shadow-md hover:shadow-lg transition-transform duration-200 ease-in-out transform hover:-translate-y-0.5">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
        </form>
    </div>

    <!-- Faculty List -->
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Our Team</h2>
        <?php if (empty($faculty_members)): ?>
            <div class="text-center p-8 bg-yellow-50 border border-yellow-200 rounded-xl text-yellow-800 shadow-md">
                <i class="fas fa-user-slash fa-4x text-yellow-400 mb-4"></i>
                <p class="text-xl font-bold mb-2">No faculty members found!</p>
                <p class="text-lg">Adjust your search or filters, or contact administration if you believe this is an error.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($faculty_members as $member): ?>
                    <div class="bg-gradient-to-br from-white to-blue-50 rounded-2xl shadow-md overflow-hidden flex flex-col items-center p-6 text-center border border-gray-200 transform hover:scale-105 transition-all duration-300 hover:shadow-lg">
                        <img class="w-28 h-28 rounded-full object-cover border-4 border-indigo-400 mb-4 shadow-sm" 
                             src="<?php echo htmlspecialchars($member['image_url'] ?: $default_avatar_url); ?>" 
                             alt="<?php echo htmlspecialchars($member['full_name']); ?>'s photo">
                        
                        <h3 class="text-xl font-bold text-indigo-800 mb-1"><?php echo htmlspecialchars($member['full_name']); ?></h3>
                        <p class="text-indigo-600 font-semibold mb-2 text-sm">
                            <?php echo htmlspecialchars($member['role_display']); ?>
                            <?php if ($member['qualification']): ?>
                                <span class="text-gray-500 text-xs font-normal">(<?php echo htmlspecialchars($member['qualification']); ?>)</span>
                            <?php endif; ?>
                        </p>
                        
                        <div class="text-gray-600 text-sm mt-2 space-y-1 w-full text-left">
                            <?php if ($member['email']): ?>
                                <p><i class="fas fa-envelope mr-2 text-indigo-500"></i><a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="hover:underline transition-colors duration-200 ease-in-out"><?php echo htmlspecialchars($member['email']); ?></a></p>
                            <?php endif; ?>
                            <?php if ($member['phone_number']): ?>
                                <p><i class="fas fa-phone mr-2 text-indigo-500"></i><a href="tel:<?php echo htmlspecialchars($member['phone_number']); ?>" class="hover:underline transition-colors duration-200 ease-in-out"><?php echo htmlspecialchars($member['phone_number']); ?></a></p>
                            <?php endif; ?>
                            <?php if (isset($member['years_of_experience']) && $member['years_of_experience'] !== null): ?>
                                <p><i class="fas fa-briefcase mr-2 text-indigo-500"></i> Exp: <?php echo htmlspecialchars($member['years_of_experience']); ?> yrs</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// You might need to adjust this path and file name.
// If this file is in the admin folder, it would be admin_footer.php.
// If this is a shared directory, ensure footer.php exists or adjust.
require_once "./student_footer.php";
?>
</body>
</html>
