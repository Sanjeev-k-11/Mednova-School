<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['role'], ['Student', 'Teacher'])) {
    header("location: ../login.php");
    exit;
}
$user_id = $_SESSION["id"];
$user_role = $_SESSION["role"];
$user_name = $_SESSION["full_name"];
$user_class_id = null;
$teacher_classes = [];

// --- Determine class_id based on role and fetch necessary data ---
if ($user_role === 'Student') {
    // ==================================
    // ROBUST FIX APPLIED HERE for "Undefined array key" warning
    // ==================================
    // Safely check for class_id in session first.
    if (isset($_SESSION["class_id"])) {
        $user_class_id = $_SESSION["class_id"];
    } else {
        // Fallback: If not in session, fetch it directly from the database.
        // This makes the page robust even if the login script is incomplete.
        $sql_get_class = "SELECT class_id FROM students WHERE id = ? LIMIT 1";
        if ($stmt_get_class = mysqli_prepare($link, $sql_get_class)) {
            mysqli_stmt_bind_param($stmt_get_class, "i", $user_id);
            mysqli_stmt_execute($stmt_get_class);
            mysqli_stmt_bind_result($stmt_get_class, $fetched_class_id);
            if (mysqli_stmt_fetch($stmt_get_class)) {
                $user_class_id = $fetched_class_id;
                // Good practice: Update the session for other pages.
                $_SESSION["class_id"] = $fetched_class_id; 
            }
            mysqli_stmt_close($stmt_get_class);
        }
    }
    
} else { // Teacher logic
    $user_class_id = $_GET['class_id'] ?? null;

    // Fetch all classes this teacher is assigned to for the selection hub
    $sql_classes = "SELECT c.id, c.class_name, c.section_name 
                    FROM classes c WHERE c.teacher_id = ? 
                    UNION 
                    SELECT c.id, c.class_name, c.section_name 
                    FROM class_subject_teacher cst JOIN classes c ON cst.class_id = c.id 
                    WHERE cst.teacher_id = ? 
                    ORDER BY class_name, section_name";
    if ($stmt_classes = mysqli_prepare($link, $sql_classes)) {
        mysqli_stmt_bind_param($stmt_classes, "ii", $user_id, $user_id);
        mysqli_stmt_execute($stmt_classes);
        $result_classes = mysqli_stmt_get_result($stmt_classes);
        $teacher_classes = mysqli_fetch_all($result_classes, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_classes);
    }
    
    // Security Check: If a class_id is provided, ensure the teacher is authorized to view it
    if ($user_class_id) {
        $is_authorized = false;
        foreach ($teacher_classes as $class) {
            if ($class['id'] == $user_class_id) {
                $is_authorized = true;
                break;
            }
        }
        if (!$is_authorized) {
            $user_class_id = null; // Unset the class ID to prevent further processing
            $_SESSION['error_message'] = "You are not authorized to view this class forum.";
        }
    }
}

// --- HANDLE POST ACTIONS (Only if a class context is established) ---
if ($user_class_id) {
    // 1. CREATE A NEW POST
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_post'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $redirect_url = ($user_role === 'Teacher') ? "student_forum.php?class_id=" . $user_class_id : "student_forum.php";


        if (empty($title) || empty($content)) {
            $_SESSION['error_message'] = "Title and content are required.";
        } else {
            $sql = "INSERT INTO forum_posts (class_id, creator_id, creator_role, title, content) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iisss", $user_class_id, $user_id, $user_role, $title, $content);
                $_SESSION['success_message'] = mysqli_stmt_execute($stmt) ? "Post created successfully!" : "Failed to create post.";
                mysqli_stmt_close($stmt);
            }
        }
        header("location: " . $redirect_url);
        exit;
    }

    // 2. REPLY TO AN EXISTING POST
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_reply'])) {
        $post_id = trim($_POST['post_id']);
        $content = trim($_POST['content']);
        $redirect_url = "student_forum.php?";
        if ($user_role === 'Teacher') $redirect_url .= "class_id=" . $user_class_id . "&";
        $redirect_url .= "post_id=" . $post_id;

        if (empty($post_id) || empty($content)) {
            $_SESSION['error_message'] = "Reply content cannot be empty.";
        } else {
            $sql_check = "SELECT id FROM forum_posts WHERE id = ? AND class_id = ?";
            if ($stmt_check = mysqli_prepare($link, $sql_check)) {
                mysqli_stmt_bind_param($stmt_check, "ii", $post_id, $user_class_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);

                if (mysqli_stmt_num_rows($stmt_check) == 1) {
                    mysqli_begin_transaction($link);
                    $sql_reply = "INSERT INTO forum_replies (post_id, replier_id, replier_role, content) VALUES (?, ?, ?, ?)";
                    $stmt_reply = mysqli_prepare($link, $sql_reply);
                    mysqli_stmt_bind_param($stmt_reply, "iiss", $post_id, $user_id, $user_role, $content);
                    mysqli_stmt_execute($stmt_reply);

                    $sql_update = "UPDATE forum_posts SET last_reply_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $stmt_update = mysqli_prepare($link, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, "i", $post_id);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_commit($link);
                    $_SESSION['success_message'] = "Reply posted successfully!";
                }
                mysqli_stmt_close($stmt_check);
            }
        }
        header("location: " . $redirect_url);
        exit;
    }
}

// --- Helper function to get user details ---
function getUserDetails($link, $id, $role) {
    $table = ($role === 'Student') ? 'students' : 'teachers';
    $name_col = ($role === 'Student') ? "CONCAT(first_name, ' ', last_name)" : 'full_name';
    $sql = "SELECT $name_col AS name, image_url FROM $table WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result) ?: ['name' => 'Unknown User', 'image_url' => null];
    }
    return ['name' => 'Database Error', 'image_url' => null];
}

// --- Determine view and fetch data ---
$post_id_view = $_GET['post_id'] ?? null;
$page_title = "Class Forum";

if ($user_class_id) { // Only fetch forum data if a class is selected
    if ($post_id_view) {
        $sql = "SELECT * FROM forum_posts WHERE id = ? AND class_id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $post_id_view, $user_class_id);
            mysqli_stmt_execute($stmt);
            $post_details = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if ($post_details) {
                $page_title = $post_details['title'];
                $post_creator = getUserDetails($link, $post_details['creator_id'], $post_details['creator_role']);
                $sql_replies = "SELECT * FROM forum_replies WHERE post_id = ? ORDER BY created_at ASC";
                if ($stmt_replies = mysqli_prepare($link, $sql_replies)) {
                    mysqli_stmt_bind_param($stmt_replies, "i", $post_id_view);
                    mysqli_stmt_execute($stmt_replies);
                    $replies = mysqli_fetch_all(mysqli_stmt_get_result($stmt_replies), MYSQLI_ASSOC);
                }
            }
        }
    } else {
        $sql = "SELECT fp.*, COUNT(fr.id) as reply_count FROM forum_posts fp LEFT JOIN forum_replies fr ON fp.id = fr.post_id WHERE fp.class_id = ? GROUP BY fp.id ORDER BY fp.is_pinned DESC, fp.last_reply_at DESC";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $user_class_id);
            mysqli_stmt_execute($stmt);
            $posts = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        }
    }
}
 
// The rest of your file (HTML part) is correct and does not need to be changed.
// Make sure to include the correct header based on the user role.
$header_file = ($user_role === 'Student') ? './student_header.php' : './teacher_header.php';
require_once $header_file;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-5xl p-4">
    
    <!-- (The rest of your HTML remains the same) -->
    <!-- Success/Error Message Display -->
    <?php if (isset($_SESSION['success_message']) || isset($_SESSION['error_message'])): ?>
        <div class="mb-6 rounded-lg p-4 font-bold text-center <?php echo isset($_SESSION['success_message']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php
            if (isset($_SESSION['success_message'])) {
                echo '<i class="fas fa-check-circle mr-2"></i> ' . htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
            } else {
                echo '<i class="fas fa-exclamation-triangle mr-2"></i> ' . htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if ($user_role === 'Teacher' && !$user_class_id): // --- TEACHER CLASS SELECTION VIEW --- ?>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Select a Class Forum</h1>
        <p class="text-gray-600 mb-6">Choose one of your assigned classes to view its discussion forum.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <?php foreach ($teacher_classes as $class): ?>
                <a href="?class_id=<?php echo $class['id']; ?>" class="block p-6 bg-white rounded-xl shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                    <div class="text-3xl text-blue-600 mb-3"><i class="fas fa-chalkboard-teacher"></i></div>
                    <h2 class="font-bold text-xl text-gray-800"><?php echo htmlspecialchars($class['class_name']); ?></h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($class['section_name']); ?></p>
                </a>
            <?php endforeach; ?>
        </div>

    <?php elseif ($user_class_id && $post_id_view): // --- POST DETAIL VIEW --- ?>
        <?php if (isset($post_details) && $post_details): ?>
            <a href="?<?php if ($user_role === 'Teacher') echo 'class_id=' . $user_class_id; ?>" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 font-semibold mb-6"><i class="fas fa-arrow-left"></i> Back to Forum</a>
            <div class="bg-white p-6 rounded-lg shadow-md mb-6 border-l-4 border-blue-500">
                <div class="flex items-center gap-4 mb-4"><div class="w-12 h-12 rounded-full flex items-center justify-center text-xl font-bold <?php echo $post_details['creator_role'] === 'Teacher' ? 'bg-indigo-200 text-indigo-700' : 'bg-gray-200 text-gray-700'; ?>"><?php echo strtoupper(substr($post_creator['name'], 0, 1)); ?></div><div><div class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($post_creator['name']); ?><?php if($post_details['creator_role'] === 'Teacher'): ?><span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-2 py-0.5 rounded-full ml-2">Teacher</span><?php endif; ?></div><div class="text-sm text-gray-500"><?php echo date("F j, Y, g:i a", strtotime($post_details['created_at'])); ?></div></div></div>
                <h1 class="text-3xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($post_details['title']); ?></h1>
                <div class="prose max-w-none text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($post_details['content'])); ?></div>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-4"><?php echo count($replies); ?> Replies</h2>
            <div class="space-y-4">
                <?php foreach ($replies as $reply): $replier = getUserDetails($link, $reply['replier_id'], $reply['replier_role']); ?>
                <div class="bg-white p-4 rounded-lg shadow-sm flex items-start gap-4 <?php echo $reply['replier_role'] === 'Teacher' ? 'border-l-4 border-indigo-400' : ''; ?>"><div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center font-bold <?php echo $reply['replier_role'] === 'Teacher' ? 'bg-indigo-200 text-indigo-700' : 'bg-gray-200 text-gray-700'; ?>"><?php echo strtoupper(substr($replier['name'], 0, 1)); ?></div><div class="flex-grow"><div class="flex justify-between items-center"><div class="font-bold text-gray-800"><?php echo htmlspecialchars($replier['name']); ?><?php if($reply['replier_role'] === 'Teacher'): ?><span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-2 py-0.5 rounded-full ml-2">Teacher</span><?php endif; ?></div><div class="text-xs text-gray-500"><?php echo date("F j, Y, g:i a", strtotime($reply['created_at'])); ?></div></div><p class="text-gray-700 mt-1"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p></div></div>
                <?php endforeach; ?>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md mt-8"><h3 class="text-xl font-bold text-gray-800 mb-4">Post a Reply</h3><form action="student_forum.php" method="POST"><input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post_id_view); ?>"><textarea name="content" rows="5" class="w-full p-3 border rounded-md focus:ring-2 focus:ring-blue-500" placeholder="Type your reply here..." required></textarea><div class="text-right mt-4"><button type="submit" name="create_reply" class="bg-blue-600 text-white font-semibold py-2 px-6 rounded-lg hover:bg-blue-700 transition">Submit Reply</button></div></form></div>
        <?php else: ?><div class="text-center p-8 bg-white rounded-lg shadow-md"><p class="text-red-500">Post not found.</p></div><?php endif; ?>

    <?php elseif ($user_class_id): // --- FORUM LIST VIEW --- ?>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Class Forum</h1>
            <button onclick="document.getElementById('createPostModal').classList.remove('hidden')" class="bg-blue-600 text-white font-semibold py-2 px-5 rounded-lg hover:bg-blue-700 transition flex items-center gap-2"><i class="fas fa-plus"></i> New Post</button>
        </div>
        <div class="bg-white rounded-lg shadow-md overflow-hidden"><div class="p-4 border-b border-gray-200 bg-gray-50 grid grid-cols-12 font-bold text-sm text-gray-600 uppercase tracking-wider"><div class="col-span-8 md:col-span-9">Topic</div><div class="col-span-2 text-center hidden md:block">Replies</div><div class="col-span-4 md:col-span-3 text-right">Last Activity</div></div>
            <?php if (empty($posts)): ?><div class="p-8 text-center text-gray-500">No discussions started yet. Be the first!</div>
            <?php else: foreach ($posts as $post): ?>
                <div class="hover:bg-gray-50 border-b border-gray-200 p-4 grid grid-cols-12 gap-4 items-center">
                    <div class="col-span-12 md:col-span-9"><a href="?<?php if ($user_role === 'Teacher') echo 'class_id=' . $user_class_id . '&'; ?>post_id=<?php echo $post['id']; ?>" class="font-bold text-blue-600 hover:underline text-lg flex items-center gap-2"><?php if ($post['is_pinned']): ?><i class="fas fa-thumbtack text-gray-500 text-base"></i><?php endif; ?><?php echo htmlspecialchars($post['title']); ?></a><?php $creator = getUserDetails($link, $post['creator_id'], $post['creator_role']); ?><div class="text-sm text-gray-500 mt-1 flex items-center gap-2">By <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($creator['name']); ?></span><?php if ($post['creator_role'] === 'Teacher'): ?><span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-2 py-0.5 rounded-full">Teacher</span><?php endif; ?> on <?php echo date("M j, Y", strtotime($post['created_at'])); ?></div></div>
                    <div class="col-span-2 hidden md:flex items-center justify-center text-center text-gray-700 font-semibold text-lg"><span class="rounded-full bg-gray-200 text-gray-700 px-3 py-1"><?php echo $post['reply_count']; ?></span></div>
                    <div class="col-span-12 md:col-span-3 text-sm text-gray-600 text-right"><?php echo date("F j, Y", strtotime($post['last_reply_at'])); ?><br><span class="text-xs text-gray-500"><?php echo date("g:i a", strtotime($post['last_reply_at'])); ?></span></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Post Modal -->
<?php if ($user_class_id): ?>
<div id="createPostModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4"><div class="fixed inset-0 bg-black bg-opacity-50" onclick="this.parentElement.parentElement.classList.add('hidden')"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-xl w-full z-10">
            <form action="student_forum.php?<?php if ($user_role === 'Teacher') echo 'class_id=' . $user_class_id; ?>" method="POST">
                <div class="px-6 py-4 border-b"><h3 class="text-xl font-bold text-gray-900">Start a New Discussion</h3></div>
                <div class="p-6 space-y-4">
                    <div><label for="title" class="block text-sm font-medium text-gray-700">Title</label><input type="text" name="title" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required></div>
                    <div><label for="content" class="block text-sm font-medium text-gray-700">Content</label><textarea name="content" rows="6" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required></textarea></div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('createPostModal').classList.add('hidden')" class="bg-white py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" name="create_post" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Create Post</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>
<?php
// IMPORTANT: Do NOT change the following require_once call as per instructions.
require_once './student_footer.php';
?>