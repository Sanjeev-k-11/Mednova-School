<?php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

$student_id = $_SESSION["id"];
$student_class_id = null;

// --- STEP 1: Fetch the student's actual class_id from the database ---
$sql_get_class = "SELECT class_id FROM students WHERE id = ? LIMIT 1";
if ($stmt_class = mysqli_prepare($link, $sql_get_class)) {
    mysqli_stmt_bind_param($stmt_class, "i", $student_id);
    mysqli_stmt_execute($stmt_class);
    mysqli_stmt_bind_result($stmt_class, $student_class_id);
    mysqli_stmt_fetch($stmt_class);
    mysqli_stmt_close($stmt_class);
}

// --- STEP 2: Determine the view from URL and prepare the SQL query ---
$view = $_GET['view'] ?? 'all'; // Default view is 'all'
$sql = '';
$page_subtitle = '';
$allItems = [];

switch ($view) {
    case 'notices':
        $page_subtitle = 'Showing class-specific and general notices.';
        $sql = "SELECT 
                    title, 
                    content, 
                    posted_by_name AS posted_by, 
                    created_at,
                    'Notice' AS item_type 
                FROM notices 
                WHERE class_id = ? OR class_id IS NULL
                ORDER BY created_at DESC";
        break;
        
    case 'announcements':
        $page_subtitle = 'Showing all school-wide announcements.';
        $sql = "SELECT 
                    title, 
                    content, 
                    posted_by, 
                    created_at,
                    'Announcement' AS item_type 
                FROM announcements 
                WHERE is_active = 1
                ORDER BY created_at DESC";
        break;

    default: // 'all' view
        $page_subtitle = 'All the latest school announcements and class notices.';
        $sql = "(SELECT 
                    title, content, posted_by_name AS posted_by, created_at, 'Notice' AS item_type 
                FROM notices WHERE class_id = ? OR class_id IS NULL)
                UNION ALL
                (SELECT 
                    title, content, posted_by, created_at, 'Announcement' AS item_type 
                FROM announcements WHERE is_active = 1)
                ORDER BY created_at DESC";
        break;
}

if ($stmt = mysqli_prepare($link, $sql)) {
    // Only bind the class_id parameter if the query needs it
    if ($view === 'notices' || $view === 'all') {
        mysqli_stmt_bind_param($stmt, "i", $student_class_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $allItems[] = $row;
        }
    } else {
        echo "Oops! Something went wrong fetching updates. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Updates & Announcements</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Lighter background */
        }
    </style>
</head>
<body class="pt-24">

<div class="container mx-auto max-w-4xl mt-28 p-4 sm:p-6">
    <div class="text-center mb-8">
        <h1 class="text-4xl font-extrabold text-gray-800 tracking-tight">Updates & Announcements</h1>
        <p class="text-gray-600 mt-2 text-lg"><?php echo htmlspecialchars($page_subtitle); ?></p>
    </div>

    <!-- Tab Navigation -->
    <div class="mb-8 flex justify-center p-1.5 bg-gray-200 rounded-xl shadow-inner">
        <a href="?view=all" class="flex-1 text-center py-2 px-4 rounded-lg font-semibold transition-all duration-300 <?php echo ($view === 'all') ? 'bg-blue-600 text-white shadow' : 'text-gray-600 hover:bg-white'; ?>">
            <i class="fas fa-list-ul mr-2"></i>All Updates
        </a>
        <a href="?view=notices" class="flex-1 text-center py-2 px-4 rounded-lg font-semibold transition-all duration-300 <?php echo ($view === 'notices') ? 'bg-blue-600 text-white shadow' : 'text-gray-600 hover:bg-white'; ?>">
            <i class="fas fa-clipboard-list mr-2"></i>Notices
        </a>
        <a href="?view=announcements" class="flex-1 text-center py-2 px-4 rounded-lg font-semibold transition-all duration-300 <?php echo ($view === 'announcements') ? 'bg-blue-600 text-white shadow' : 'text-gray-600 hover:bg-white'; ?>">
            <i class="fas fa-bullhorn mr-2"></i>Announcements
        </a>
    </div>

    <?php if (empty($allItems)): ?>
        <div class="text-center py-16 px-6 bg-white rounded-2xl shadow-md">
            <i class="fas fa-bell-slash text-5xl text-gray-400"></i>
            <h3 class="text-2xl font-bold text-gray-700 mt-4">No Updates Found</h3>
            <p class="mt-2 text-gray-500">There are no items to display for this view. Please check back later.</p>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($allItems as $item): ?>
                <?php
                    $is_announcement = ($item['item_type'] === 'Announcement');
                    $card_class = $is_announcement ? 'border-green-500' : 'border-blue-500';
                    $icon_class = $is_announcement ? 'fa-bullhorn bg-green-100 text-green-600' : 'fa-clipboard-list bg-blue-100 text-blue-600';
                    $badge_class = $is_announcement ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                    $badge_text = $is_announcement ? 'School Announcement' : 'Class Notice';
                ?>
                <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 overflow-hidden border-l-4 <?php echo $card_class; ?>">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0 h-12 w-12 rounded-full flex items-center justify-center <?php echo $icon_class; ?>">
                                    <i class="fas <?php echo $icon_class; ?> text-xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($item['title']); ?></h2>
                                    <div class="text-sm text-gray-500 mt-1">
                                        <span>Posted by: <strong><?php echo htmlspecialchars($item['posted_by']); ?></strong></span>
                                    </div>
                                </div>
                            </div>
                            <span class="text-xs font-semibold py-1 px-3 rounded-full <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                        </div>

                        <div class="mt-4 pl-16 text-gray-700 leading-relaxed prose max-w-none">
                            <p><?php echo nl2br(htmlspecialchars($item['content'])); ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 text-right text-xs text-gray-500 font-medium">
                        <?php echo date("F j, Y, g:i a", strtotime($item['created_at'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

<?php
require_once './student_footer.php';
if($link) {
    mysqli_close($link);
}
?>