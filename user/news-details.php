<?php
// =================================================================================
// BACKEND LOGIC: DATABASE CONNECTION & DATA FETCHING
// =================================================================================

// 1. Include Database Configuration & Shared Header
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../header.php';

// 2. Get the News ID from the URL and validate it
$news_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$news_id) {
    // If ID is missing or invalid, we can redirect or show an error
    header("Location: e.php"); // Redirect back to the main news page
    exit;
}

// 3. Initialize arrays to hold the data
$article = null;
$recentNews = [];

// 4. Fetch the specific news article using a prepared statement to prevent SQL injection
$sql_article = "SELECT * FROM news_articles WHERE id = ? LIMIT 1";
if ($stmt = mysqli_prepare($link, $sql_article)) {
    mysqli_stmt_bind_param($stmt, "i", $news_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $article = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// 5. If the article was not found, display a user-friendly message
if (!$article) {
    // We can show a simple error page here.
    echo "<div style='text-align:center; padding: 5rem; font-family: sans-serif;'><h1>Article Not Found</h1><p>The news article you are looking for does not exist or has been moved.</p><a href='e.php' style='color: #059669;'>Return to News Page</a></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// 6. Fetch a few recent news articles for the sidebar (excluding the current one)
$sql_recent = "SELECT id, title, date, image_url FROM news_articles WHERE id != ? ORDER BY date DESC LIMIT 4";
if ($stmt_recent = mysqli_prepare($link, $sql_recent)) {
    mysqli_stmt_bind_param($stmt_recent, "i", $news_id);
    mysqli_stmt_execute($stmt_recent);
    $result_recent = mysqli_stmt_get_result($stmt_recent);
    while ($row = mysqli_fetch_assoc($result_recent)) {
        $recentNews[] = $row;
    }
    mysqli_stmt_close($stmt_recent);
}

// 7. Close the database connection
mysqli_close($link);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> - Mednova School News</title>
    <meta name="description" content="<?php echo htmlspecialchars($article['excerpt']); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #F0FDFA; /* Very light teal (teal-50) */
            --card-bg: #FFFFFF;
            --text-dark: #0F766E; /* Deep teal (teal-700) */
            --text-medium: #115E59; /* Deeper teal (teal-800) */
            --text-light: #475569; /* slate-600 for body copy */
            --accent-primary: #059669; /* emerald-600 */
            --border-color: #CCFBF1; /* teal-100 */
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-light);
        }
        h1, h2, h3, h4 {
            font-family: 'Lora', serif;
            color: var(--text-medium);
        }
        .container-padding { max-width: 1200px; margin: auto; padding: 0 1rem; }
        .article-content a { color: var(--accent-primary); text-decoration: underline; }
        .article-content p { margin-bottom: 1.5rem; line-height: 1.8; }
        .article-content h2, .article-content h3 { margin-top: 2rem; margin-bottom: 1rem; }
        .breadcrumb a:hover { color: var(--accent-primary); }
    </style>
</head>
<body>
    <main class="py-16 md:py-24">
        <div class="container-padding">
            <div class="grid lg:grid-cols-3 gap-8 lg:gap-12">
                <!-- Main Article Content -->
                <div class="lg:col-span-2">
                    <article class="bg-white p-6 md:p-10 rounded-lg shadow-lg">
                        <!-- Breadcrumb Navigation -->
                        <div class="breadcrumb text-sm text-gray-500 mb-4">
                            <a href="index.php" class="hover:text-teal-600">Home</a> &raquo;
                            <a href="e.php" class="hover:text-teal-600">News & Events</a> &raquo;
                            <span class="text-gray-700"><?php echo htmlspecialchars($article['title']); ?></span>
                        </div>

                        <!-- Category & Date -->
                        <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                            <span class="bg-teal-100 text-teal-800 px-3 py-1 rounded-full font-medium"><?php echo htmlspecialchars($article['category']); ?></span>
                            <span>&bull;</span>
                            <span><?php echo date('F d, Y', strtotime($article['date'])); ?></span>
                        </div>

                        <!-- Title -->
                        <h1 class="text-3xl md:text-4xl  font-bold mb-6 text-teal-900"><?php echo htmlspecialchars($article['title']); ?></h1>
                        
                        <!-- Feature Image -->
                        <div class="mb-8 rounded-lg overflow-hidden">
                            <img src="<?php echo htmlspecialchars($article['image_url']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" class="w-full h-auto object-cover" onerror="this.style.display='none'">
                        </div>
                        
                        <!-- Article Body -->
                        <div class="article-content text-lg">
                            <!-- Excerpt as lead paragraph -->
                            <p class="text-xl  font-medium text-gray-700 border-l-4 border-teal-500 pl-4">
                                <?php echo htmlspecialchars($article['excerpt']); ?>
                            </p>
                              <p class="text-black">
                            <!-- Full Content (handles new lines) -->
                            <?php echo nl2br(htmlspecialchars($article['content'])); ?>
                              </p>
                        </div>
                    </article>
                </div>

                <!-- Sidebar for Recent News -->
                <aside class="lg:col-span-1">
                    <div class="sticky top-8 bg-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-xl font-bold mb-6 border-b pb-3 text-teal-800">Recent News</h3>
                        <div class="space-y-6">
                            <?php foreach ($recentNews as $item): ?>
                            <a href="news-details.php?id=<?php echo $item['id']; ?>" class="flex items-center space-x-4 group">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-20 h-20 object-cover rounded-md flex-shrink-0" onerror="this.onerror=null;this.src='https://placehold.co/80x80/115E59/ffffff?text=Mednova';">
                                <div>
                                    <p class="font-semibold text-gray-800 group-hover:text-teal-600 transition-colors duration-300 leading-tight"><?php echo htmlspecialchars($item['title']); ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo date('F d, Y', strtotime($item['date'])); ?></p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <a href="e.php" class="block w-full text-center bg-teal-600 text-white font-semibold py-3 rounded-lg mt-8 hover:bg-teal-700 transition-colors duration-300">
                            View All News
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </main>

<?php
require_once __DIR__ . '/../footer.php';
?>
</body>
</html>