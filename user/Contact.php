<?php
// This is a dynamic PHP file for the Achievements and Contact section.
// It fetches content from the achievements_contact_settings table.

require_once __DIR__ . '/../database/config.php'; // Adjust path if necessary

// Initialize an array to hold settings, with default values or empty strings
$settings = [
    'achievements_main_title' => 'Achievements_',
    'achievements_subtitle' => 'Excellence in Action',
    'toppers_section_title' => 'Our Top Achievers',
    'toppers_json' => '[]',
    'awards_section_title' => 'Awards & Recognition_',
    'awards_subtitle' => 'Our Legacy of Excellence',
    'awards_json' => '[]',
    'contact_section_title' => 'Contact Info_',
    'contact_subtitle' => 'Connect With Us',
    'contact_address' => 'Innovate Academy, 123 Tech Drive, Silicon Valley, CA 90210',
    'contact_phone' => '+1 (555) 123-4567',
    'contact_email' => 'info@innovateacademy.edu'
];

// Fetch settings from the database
$sql = "SELECT * FROM achievements_contact_settings WHERE id = 1";
if ($result = mysqli_query($link, $sql)) {
    if (mysqli_num_rows($result) == 1) {
        $db_settings = mysqli_fetch_assoc($result);
        foreach ($settings as $key => $value) {
            if (isset($db_settings[$key]) && $db_settings[$key] !== NULL && $db_settings[$key] !== '') {
                $settings[$key] = $db_settings[$key];
            }
        }
    }
    mysqli_free_result($result);
} else {
    error_log("Error fetching achievements_contact_settings: " . mysqli_error($link));
}

// Decode JSON data for display
$toppers_display = json_decode($settings['toppers_json'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($toppers_display)) {
    $toppers_display = [];
}

$awards_display = json_decode($settings['awards_json'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($awards_display)) {
    $awards_display = [];
}

// mysqli_close($link); // Close connection if this is the end of script execution
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['achievements_main_title']); ?> - Innovate Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Enhanced Color Palette & Base for Frontend --- */
        :root {
            --bg-body: #f8f8f8; /* Very light grey/off-white */
            --text-main: #333333;
            --text-secondary: #666666;
            --card-bg: #ffffff;
            --border-light: #e0e0e0;

            --accent-heading: #2c3e50; /* Dark blue */
            --accent-gold-dark: #FFC107; /* Strong gold */
            --accent-gold-light: #FFD54F; /* Lighter glow gold */
            --accent-blue-dark: #0088CC; /* Stronger blue */
            --accent-blue-light: #00BFFF; /* Lighter blue */

            --shadow-subtle: 0 4px 12px rgba(0,0,0,0.05);
            --shadow-hover: 0 10px 25px rgba(0,0,0,0.1);
            --transition-speed: 0.3s;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Roboto Mono', monospace;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        .py-16 {
            padding-top: 4rem;
            padding-bottom: 4rem;
        }
        .mt-8 {
            margin-top: 4rem;
        }

        /* --- Global & Typography --- */
        .section-main-title { /* Renamed to avoid conflict with generic h2 */
            font-family: 'Playfair Display', serif; /* Elegant serif for main titles */
            font-size: 2.75rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
            color: var(--accent-heading);
            position: relative;
            z-index: 1;
        }
        .section-main-title::after { /* Blinking cursor effect */
            content: '_';
            color: var(--accent-gold-dark);
            animation: blink 1s infinite;
        }
        .section-subtitle {
            text-align: center;
            color: var(--accent-gold-dark);
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.2rem;
            margin-bottom: 3.5rem;
        }
        @keyframes blink {
            50% { opacity: 0; }
        }

        /* --- Section Toggle Styles --- */
        .section-toggle-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            transition: border-color var(--transition-speed);
        }
        .section-toggle-header h3 {
            margin: 0;
            padding: 0;
            border-bottom: none;
            text-align: left;
            font-size: 1.8rem;
            color: var(--accent-heading);
            font-family: 'Playfair Display', serif; /* Consistent elegant font */
        }
        .section-toggle-header .toggle-icon {
            transition: transform var(--transition-speed);
            color: var(--accent-blue-dark); /* Accent color for icon */
        }
        .section-toggle-header[aria-expanded="true"] .toggle-icon {
            transform: rotate(180deg);
        }
        .section-content-wrapper { /* Wrapper to manage max-height transition */
            max-height: 1000px; /* Arbitrary large value for smooth transition */
            overflow: hidden;
            transition: max-height 0.5s ease-out, opacity 0.5s ease-out;
            opacity: 1;
        }
        .section-content-wrapper.collapsed {
            max-height: 0;
            opacity: 0;
            padding-top: 0;
            padding-bottom: 0;
            margin-top: 0;
            margin-bottom: 0;
        }

        /* --- Card & Interface Design --- */
        .system-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            transition: all var(--transition-speed) ease;
            box-shadow: var(--shadow-subtle);
        }
        .system-card::before {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: linear-gradient(45deg, var(--accent-gold-light), var(--accent-blue-light), var(--accent-gold-light));
            filter: blur(8px);
            opacity: 0;
            transition: opacity 0.5s ease;
            z-index: 0;
        }
        .system-card:hover::before {
            opacity: 0.2;
        }
        .system-card:hover {
            border-color: var(--accent-gold-dark);
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        .system-card > * {
            position: relative;
            z-index: 1;
        }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
            margin-bottom: 4rem;
        }
        .card-title {
            font-size: 1.5rem;
            margin: 0 0 1rem 0;
            color: var(--accent-heading); /* Dark blue for card titles */
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Roboto Mono', monospace; /* Retain monospace for this */
        }
        .card-description {
            color: var(--text-secondary);
            line-height: 1.7;
            margin: 0;
        }

        /* --- Topper Card Specific Styling --- */
        .topper-card {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .topper-card .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 2px solid var(--accent-blue-dark);
            background-color: var(--bg-body); /* Light background for avatar */
            color: var(--accent-blue-dark);
            font-size: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            transition: all var(--transition-speed) ease;
        }
        .topper-card:hover .avatar {
            border-color: var(--accent-gold-dark);
            transform: scale(1.1);
            color: var(--accent-gold-dark);
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.5); /* Gold glow */
        }
        .topper-card h4 {
            font-size: 1.25rem;
            margin: 0;
            color: var(--accent-heading);
            font-family: 'Roboto Mono', monospace; /* Retain monospace */
        }
        .topper-card p.details {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0.5rem 0 0;
        }

        /* --- Awards Card Specific Styling --- */
        .awards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .award-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            padding: 2rem;
            border-radius: 8px;
            transition: all var(--transition-speed) ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-subtle);
        }
        .award-card:hover {
            border-color: var(--accent-blue-dark);
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        .award-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(0, 191, 255, 0.1), transparent);
            transform: rotate(45deg);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }
        .award-card:hover::before {
            opacity: 1;
        }
        .award-card > * {
            position: relative;
            z-index: 1;
        }
        .award-card h5 {
            font-size: 1.1rem;
            margin: 0 0 0.5rem 0;
            color: var(--accent-gold-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Roboto Mono', monospace; /* Retain monospace */
        }
        .award-card p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0;
            flex-grow: 1;
        }
        .award-card .icon {
            font-size: 1.5rem;
            color: var(--accent-blue-dark);
        }

        /* --- Contact Cards --- */
        .contact-grid .system-card {
            background-color: var(--card-bg); /* Use card-bg */
            border-color: var(--border-light);
            box-shadow: var(--shadow-subtle);
        }
        .contact-grid .system-card:hover {
            border-color: var(--accent-blue-dark); /* Blue hover for contact */
            box-shadow: var(--shadow-hover);
        }
        .contact-grid .card-title {
            color: var(--accent-heading);
        }
        .contact-grid .card-description {
            color: var(--text-main); /* Slightly darker for contact info itself */
            font-size: 1rem;
        }


        /* --- Responsive Adjustments --- */
        @media (max-width: 768px) {
            .section-main-title { font-size: 2.25rem; }
            .grid-container, .awards-grid { grid-template-columns: 1fr; }
            .py-16 {
                padding-top: 2rem;
                padding-bottom: 2rem;
            }
            .mt-8 {
                margin-top: 2rem;
            }
            .section-toggle-header h3 {
                font-size: 1.5rem;
            }
            .system-card, .award-card {
                padding: 1.5rem;
            }
            .card-title {
                font-size: 1.25rem;
            }
        }
        
    </style>
</head>
<body>
    <main class="py-16">
        <section class="container">
            <h2 class="section-main-title"><?php echo htmlspecialchars($settings['achievements_main_title']); ?></h2>
            <p class="section-subtitle"><?php echo htmlspecialchars($settings['achievements_subtitle']); ?></p>
            
            <!-- Toppers Section -->
            <div class="section-toggle-header" id="toppers-toggle-header" aria-expanded="true" aria-controls="toppers-content">
                <h3><?php echo htmlspecialchars($settings['toppers_section_title']); ?></h3>
                <svg class="toggle-icon h-8 w-8" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            <div id="toppers-content" class="section-content-wrapper">
                <div class="grid-container">
                    <?php if (empty($toppers_display)): ?>
                        <p style="color:var(--text-secondary); text-align:center; padding:1.5rem; grid-column:1/-1;">No toppers listed yet.</p>
                    <?php else: ?>
                        <?php foreach ($toppers_display as $topper): ?>
                            <div class="system-card topper-card">
                                <div class="avatar"><?php echo $topper['icon'] ?? '<i class="fa-solid fa-user-graduate"></i>'; ?></div>
                                <h4><?php echo htmlspecialchars($topper['name'] ?? ''); ?></h4>
                                <p class="details"><?php echo htmlspecialchars($topper['details'] ?? ''); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div> <!-- End toppers-content -->
        </section>

        <section class="container mt-8">
            <!-- Awards & Recognition Section -->
            <div class="section-toggle-header" id="awards-toggle-header" aria-expanded="true" aria-controls="awards-content">
                <h3><?php echo htmlspecialchars($settings['awards_section_title']); ?></h3>
                <svg class="toggle-icon h-8 w-8" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            <div id="awards-content" class="section-content-wrapper">
                <p class="section-subtitle"><?php echo htmlspecialchars($settings['awards_subtitle']); ?></p>
                <div class="awards-grid">
                    <?php if (empty($awards_display)): ?>
                        <p style="color:var(--text-secondary); text-align:center; padding:1.5rem; grid-column:1/-1;">No awards listed yet.</p>
                    <?php else: ?>
                        <?php foreach ($awards_display as $award): ?>
                            <div class="award-card">
                                <h5><?php echo $award['icon'] ?? '<i class="fa-solid fa-award icon"></i>'; ?> <?php echo htmlspecialchars($award['title'] ?? ''); ?></h5>
                                <p><?php echo nl2br(htmlspecialchars($award['description'] ?? '')); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div> <!-- End awards-content -->
        </section>

        <section class="container mt-8">
            <!-- Contact Information Section -->
            <div class="section-toggle-header" id="contact-toggle-header" aria-expanded="true" aria-controls="contact-content">
                <h3><?php echo htmlspecialchars($settings['contact_section_title']); ?></h3>
                <svg class="toggle-icon h-8 w-8" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            <div id="contact-content" class="section-content-wrapper">
                <p class="section-subtitle"><?php echo htmlspecialchars($settings['contact_subtitle']); ?></p>
                <div class="grid-container contact-grid">
                    <div class="system-card">
                        <h4 class="card-title"><i class="fa-solid fa-location-dot"></i> Address</h4>
                        <p class="card-description"><?php echo nl2br(htmlspecialchars($settings['contact_address'])); ?></p>
                    </div>
                    <div class="system-card">
                        <h4 class="card-title"><i class="fa-solid fa-phone"></i> Phone</h4>
                        <p class="card-description"><?php echo htmlspecialchars($settings['contact_phone']); ?></p>
                    </div>
                    <div class="system-card">
                        <h4 class="card-title"><i class="fa-solid fa-envelope"></i> Email</h4>
                        <p class="card-description"><?php echo htmlspecialchars($settings['contact_email']); ?></p>
                    </div>
                </div>
            </div> <!-- End contact-content -->
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to set up a collapsible section
            function setupSectionToggle(headerId, contentId, initialExpanded = true) {
                const header = document.getElementById(headerId);
                const content = document.getElementById(contentId);
                if (!header || !content) return;

                // Set initial state
                header.setAttribute('aria-expanded', initialExpanded ? 'true' : 'false');
                content.classList.toggle('collapsed', !initialExpanded);

                header.addEventListener('click', () => {
                    const isExpanded = header.getAttribute('aria-expanded') === 'true';
                    header.setAttribute('aria-expanded', !isExpanded);
                    content.classList.toggle('collapsed', isExpanded);
                });
            }

            // Apply toggles to all sections, default to expanded
            setupSectionToggle('toppers-toggle-header', 'toppers-content', true);
            setupSectionToggle('awards-toggle-header', 'awards-content', true);
            setupSectionToggle('contact-toggle-header', 'contact-content', true);
        });
    </script>
</body>
</html>