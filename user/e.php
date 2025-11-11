<?php
// =================================================================================
// BACKEND LOGIC: DATABASE CONNECTION & DATA FETCHING
// =================================================================================

// 1. Include Database Configuration & Shared Header
require_once __DIR__ . '/../database/config.php'; // Adjust path if you place this file elsewhere
require_once __DIR__ . '/../header.php';           // Adjust path for your header

// 2. Initialize arrays to hold the data
$newsItems = [];
$eventItems = [];

// 3. Fetch News Articles from the database
$sql_news = "SELECT * FROM news_articles ORDER BY date DESC LIMIT 9";
if ($result_news = mysqli_query($link, $sql_news)) {
    while ($row = mysqli_fetch_assoc($result_news)) {
        $newsItems[] = $row;
    }
    mysqli_free_result($result_news);
}

// 4. Fetch Upcoming Events from the database
$sql_events = "SELECT * FROM upcoming_events ORDER BY created_at DESC LIMIT 6";
if ($result_events = mysqli_query($link, $sql_events)) {
    while ($row = mysqli_fetch_assoc($result_events)) {
        $eventItems[] = $row;
    }
    mysqli_free_result($result_events);
}

// 5. Close the database connection
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events & News - Mednova School</title>
    <meta name="description" content="Stay updated with the latest news and upcoming events at Mednova School. Explore our vibrant community life, academic achievements, and special celebrations.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        /* --- PREMIUM GREEN/TEAL THEME --- */
        :root {
            --bg-color: #F0FDFA; /* Very light teal (teal-50) */
            --card-bg: #FFFFFF;
            --text-dark: #0F766E; /* Deep teal (teal-700) */
            --text-medium: #115E59; /* Deeper teal (teal-800) */
            --text-light: #52525b; /* zinc-600 for body copy */
            --accent-primary: #059669; /* emerald-600 */
            --accent-secondary: #0D9488; /* teal-600 */
            --border-color: #CCFBF1; /* teal-100 */
            --hover-border-color: #5EEAD4; /* teal-300 */
            --shadow-color: rgba(13, 148, 136, 0.1); /* teal-600 with opacity */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-light);
            line-height: 1.7;
        }
        h1, h2, h3, h4 { font-family: 'Playfair Display', serif; color: var(--text-medium); }
        .section-padding { padding: 6rem 0; }
        .container-padding { max-width: 1300px; margin: auto; padding: 0 1.5rem; }
        @media (min-width: 1024px) { .container-padding { padding: 0 5rem; } }
        
        /* Gradient text with the new theme */
        .text-gradient-primary {
            background-image: linear-gradient(90deg, var(--accent-primary), var(--text-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 900;
        }

        /* Premium Hero Section */
        .hero-section {
            background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.7)), url('https://placehold.co/1920x600/0D9488/F0FDFA?text=Mednova+Updates');
            background-size: cover; background-position: center; color: white;
            padding: 8rem 0; text-shadow: 2px 2px 8px rgba(0,0,0,0.6);
            border-radius: 0 0 3rem 3rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .hero-section h1 { font-size: 3.5rem; line-height: 1.2; margin-bottom: 1.5rem; font-weight: 900; }
        .hero-section p { font-size: 1.5rem; line-height: 1.5; max-width: 800px; margin: 0 auto; }

        .animate-fade-in { animation: fadeIn 1.2s ease-out forwards; opacity: 0; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .divider { height: 1px; background-color: var(--border-color); margin: 4rem auto; max-width: 80%; }

        /* Premium White Card Style */
        .item-card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 16px var(--shadow-color);
            display: flex; flex-direction: column; height: 100%;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .item-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px var(--shadow-color);
            border-color: var(--hover-border-color);
        }
        .item-card-image-wrapper { position: relative; overflow: hidden; padding-bottom: 66.66%; height: 0; }
        .item-card-image { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .item-card:hover .item-card-image { transform: scale(1.05); }
        .item-card-content { padding: 1.5rem; display: flex; flex-direction: column; flex-grow: 1; }
        .item-card-meta { font-size: 0.875rem; color: #52525b; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .item-card-title { font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.75rem; line-height: 1.4; }
        .item-card-excerpt { font-size: 1rem; color: var(--text-light); margin-bottom: 1.5rem; flex-grow: 1; }
        .item-card-link { display: inline-flex; align-items: center; font-weight: 600; color: var(--accent-primary); transition: color 0.3s ease; margin-top: auto; }
        .item-card-link:hover { color: var(--text-dark); }
        .item-card-link svg { margin-left: 0.5rem; width: 1.25rem; height: 1.25rem; transition: transform 0.3s ease; }
        .item-card-link:hover svg { transform: translateX(4px); }
        
        .cta-button, .cta-button-outline {
            display: inline-flex; align-items: center; justify-content: center;
            font-bold: 600; padding: 0.75rem 2rem; border-radius: 9999px;
            font-size: 1.125rem; transition: all 0.3s ease;
        }
        .cta-button {
            background-color: var(--accent-primary); color: white;
            box-shadow: 0 5px 15px var(--shadow-color);
        }
        .cta-button:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 8px 20px var(--shadow-color); }
        .cta-button-outline {
            border: 2px solid var(--accent-primary); color: var(--accent-primary);
        }
        .cta-button-outline:hover { background-color: var(--accent-primary); color: white; transform: translateY(-3px); }
    </style>
</head>
<body>

    <section class="hero-section">
        <div class="container-padding animate-fade-in">
            <h1>Stay Informed: Mednova's Events & News</h1>
            <p>Catch up on the latest happenings, academic milestones, student achievements, and upcoming events that shape our vibrant school community.</p>
        </div>
    </section>

    <section id="latest-news" class="section-padding">
        <div class="container-padding">
            <div class="text-center mb-16 animate-fade-in">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary">Our Latest News & Achievements</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Discover inspiring stories, academic successes, and community engagement updates from Mednova School.</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                <?php foreach ($newsItems as $index => $news): ?>
                    <div class="item-card animate-fade-in" style="animation-delay: <?= 0.2 + ($index * 0.1); ?>s;">
                        <a href="news-details.php?id=<?= $news['id'] ?>" class="block">
                            <div class="item-card-image-wrapper">
                                <img class="item-card-image" src="<?= htmlspecialchars($news['image_url']) ?>" alt="<?= htmlspecialchars($news['title']) ?>" onerror="this.onerror=null;this.src='https://placehold.co/600x400/0D9488/ffffff?text=Mednova+News';">
                                <span class="absolute top-4 left-4 bg-white/90 text-teal-800 px-3 py-1 rounded-full text-xs font-semibold shadow-md"><?= htmlspecialchars($news['category']) ?></span>
                            </div>
                        </a>
                        <div class="item-card-content">
                            <div class="item-card-meta">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span><?= date('F d, Y', strtotime($news['date'])) ?></span>
                            </div>
                            <h3 class="item-card-title"><?= htmlspecialchars($news['title']) ?></h3>
                            <p class="item-card-excerpt"><?= htmlspecialchars($news['excerpt']) ?></p>
                            <a href="news-details.php?id=<?= $news['id'] ?>" class="item-card-link">
                                Read More
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12 animate-fade-in"><a href="#" class="cta-button">View All News</a></div>
        </div>
    </section>

    <div class="divider animate-fade-in"></div>

    <section id="upcoming-events" class="section-padding pt-0">
        <div class="container-padding">
            <div class="text-center mb-16 animate-fade-in">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary">Upcoming Events</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Mark your calendars! Explore our exciting lineup of school events, workshops, and celebrations.</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                <?php foreach ($eventItems as $index => $event): ?>
                    <div class="item-card animate-fade-in" style="animation-delay: <?= 0.2 + ($index * 0.1); ?>s;">
                        <a href="event-details.php?id=<?= $event['id'] ?>" class="block">
                            <div class="item-card-image-wrapper">
                                <img class="item-card-image" src="<?= htmlspecialchars($event['image_url']) ?>" alt="<?= htmlspecialchars($event['title']) ?>" onerror="this.onerror=null;this.src='https://placehold.co/600x400/059669/ffffff?text=Mednova+Event';">
                                <span class="absolute top-4 left-4 bg-white/90 text-emerald-800 px-3 py-1 rounded-full text-xs font-semibold shadow-md"><?= htmlspecialchars($event['type']) ?></span>
                            </div>
                        </a>
                        <div class="item-card-content">
                            <div class="item-card-meta">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span><?= htmlspecialchars($event['event_date']) ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><?= htmlspecialchars($event['time']) ?></span>
                            </div>
                            <h3 class="item-card-title"><?= htmlspecialchars($event['title']) ?></h3>
                            <p class="item-card-excerpt"><?= htmlspecialchars($event['description']) ?></p>
                            <a href="event-details.php?id=<?= $event['id'] ?>" class="item-card-link">
                                Learn More
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-12 animate-fade-in"><a href="#" class="cta-button-outline">View All Events</a></div>
        </div>
    </section>

</body>
</html>

<?php
require_once __DIR__ . '/../footer.php';
?>