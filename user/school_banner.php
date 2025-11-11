<?php
// This is a dynamic PHP file containing the HTML for the Hero section.
// It fetches content from the school_settings table.

// Include your database configuration
require_once __DIR__ . '/../database/config.php'; // Adjust path if necessary

// Initialize an array to hold settings, with default values or empty strings
$settings = [
    'hero_image_url' => 'uploads/sc.png', // Default fallback image
    'hero_title_prefix' => 'Shaping',
    'hero_title_highlight' => 'Tomorrow\'s',
    'hero_title_suffix' => 'Leaders Today',
    'hero_description' => 'Excellence School provides world-class education from Nursery to 12th grade, fostering creativity, critical thinking, and character development in every student.',
    'button1_text' => 'Apply Now',
    'button1_url' => '#apply',
    'button2_text' => 'Virtual Tour',
    'button2_url' => '#virtual-tour',
    'stat1_value' => '1500+',
    'stat1_label' => 'Students',
    'stat2_value' => '150+',
    'stat2_label' => 'Faculty',
    'stat3_value' => '98%',
    'stat3_label' => 'Success Rate',
    'stat4_value' => '25+',
    'stat4_label' => 'Years Excellence'
];

// Fetch settings from the database
$sql = "SELECT * FROM school_settings WHERE id = 1";
if ($result = mysqli_query($link, $sql)) {
    if (mysqli_num_rows($result) == 1) {
        $db_settings = mysqli_fetch_assoc($result);
        // Overwrite default settings with database values if they exist
        foreach ($settings as $key => $value) {
            if (isset($db_settings[$key]) && $db_settings[$key] !== NULL) {
                // Use default if DB value is empty for hero_image_url
                if ($key === 'hero_image_url' && empty($db_settings[$key])) {
                    // Keep the hardcoded default 'uploads/sc.png'
                } else {
                    $settings[$key] = $db_settings[$key];
                }
            }
        }
    }
    mysqli_free_result($result);
} else {
    // Handle error if query fails (e.g., log it)
    error_log("Error fetching school_settings: " . mysqli_error($link));
}

// Close database connection if this is a standalone script
// If it's included in a larger application, the main script should close it.
// mysqli_close($link); 
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hero Section</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin-top: 170px; /* Adjust this if you have a fixed header */
        }

        .hero-gradient {
            background-image: linear-gradient(180deg, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.8) 100%);
        }

        .container-padding {
            padding: 2rem 1rem;
        }

        @media (min-width: 640px) {
            .container-padding {
                padding: 2rem;
            }
        }

        @media (min-width: 1024px) {
            .container-padding {
                padding: 4rem;
            }
        }

        .text-gradient-accent {
            background-image: linear-gradient(90deg, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Adjusted to use <a> tags for buttons for better semantic linking */
        .hero-button {
            @apply flex items-center justify-center px-8 py-3 rounded-full text-lg font-semibold text-white bg-gradient-to-r from-indigo-500 to-purple-600 transition-all duration-300 transform hover:scale-105 hover:shadow-xl whitespace-nowrap;
        }

        .hero-button-outline {
            @apply flex items-center justify-center px-8 py-3 rounded-full text-lg font-semibold border-2 border-white text-white bg-transparent transition-all duration-300 transform hover:scale-105 hover:bg-white hover:text-indigo-600 whitespace-nowrap;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }

        /* Added for scroll indicator positioning */
        .scroll-indicator {
            z-index: 20; /* Ensure it's above the image and gradient */
        }
    </style>
</head>

<body class="bg-gray-900">
    <section id="home" class="relative pt-28 min-h-screen flex items-center overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img
                src="<?php echo htmlspecialchars($settings['hero_image_url']); ?>"
                alt="Hero Background Image"
                class="w-full h-full object-cover" />

            <div class="absolute inset-0 hero-gradient opacity-80"></div>
        </div>

        <div class="relative z-10 container-padding w-full">
            <div class="max-w-4xl text-white">
                <div class="animate-fade-in-up">
                    <h1 class="text-5xl lg:text-7xl font-bold mb-6 leading-tight">
                        <?php echo htmlspecialchars($settings['hero_title_prefix']); ?>
                        <span class="block text-gradient-accent"><?php echo htmlspecialchars($settings['hero_title_highlight']); ?></span>
                        <?php echo htmlspecialchars($settings['hero_title_suffix']); ?>
                    </h1>
                </div>

                <div class="animate-fade-in-up" style="animation-delay: 0.2s;">
                    <p class="text-xl lg:text-2xl mb-8 max-w-2xl leading-relaxed opacity-90">
                        <?php echo nl2br(htmlspecialchars($settings['hero_description'])); ?>
                    </p>
                </div>

                <div class="animate-fade-in-up flex flex-col sm:flex-row gap-4" style="animation-delay: 0.4s;">
                    <a href="<?php echo htmlspecialchars($settings['button1_url']); ?>" class="hero-button group">
                        <?php echo htmlspecialchars($settings['button1_text']); ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-5 w-5 group-hover:translate-x-1 transition-transform inline-block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="<?php echo htmlspecialchars($settings['button2_url']); ?>" class="hero-button-outline group">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-5 w-5 inline-block" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 19V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2m2-14v14h14V5H5m2 2v10l8-5-8-5Z" />
                        </svg>
                        <?php echo htmlspecialchars($settings['button2_text']); ?>
                    </a>
                </div>

                <div class="animate-fade-in-up mt-16 grid grid-cols-2 lg:grid-cols-4 gap-8" style="animation-delay: 0.6s;">
                    <div class="text-center">
                        <div class="text-3xl lg:text-4xl font-bold mb-2"><?php echo htmlspecialchars($settings['stat1_value']); ?></div>
                        <div class="text-lg opacity-90"><?php echo htmlspecialchars($settings['stat1_label']); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl lg:text-4xl font-bold mb-2"><?php echo htmlspecialchars($settings['stat2_value']); ?></div>
                        <div class="text-lg opacity-90"><?php echo htmlspecialchars($settings['stat2_label']); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl lg:text-4xl font-bold mb-2"><?php echo htmlspecialchars($settings['stat3_value']); ?></div>
                        <div class="text-lg opacity-90"><?php echo htmlspecialchars($settings['stat3_label']); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl lg:text-4xl font-bold mb-2"><?php echo htmlspecialchars($settings['stat4_value']); ?></div>
                        <div class="text-lg opacity-90"><?php echo htmlspecialchars($settings['stat4_label']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce scroll-indicator">
            <div class="w-6 h-10 border-2 border-white rounded-full flex justify-center">
                <div class="w-1 h-3 bg-white rounded-full mt-2 animate-pulse"></div>
            </div>
        </div>
    </section>
</body>

</html>