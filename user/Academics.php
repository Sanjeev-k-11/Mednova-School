<?php
// This is a dynamic PHP file for the Academics section.
// It fetches content from the academics_settings table.

// Include your database configuration
require_once __DIR__ . '/../database/config.php'; // Adjust path if necessary

// Initialize an array to hold settings, with default values or empty strings
$settings = [
    'academics_section_title' => 'Academic Excellence',
    'academics_section_description' => 'Our comprehensive curriculum from Nursery to Class XII ensures holistic development and prepares students for future challenges with confidence and competence.',
    'academic_levels_json' => '[]', // Default to empty JSON array
    'academic_features_title' => 'Academic Features',
    'academic_features_json' => '[]' // Default to empty JSON array
];

// Fetch settings from the database
$sql = "SELECT * FROM academics_settings WHERE id = 1";
if ($result = mysqli_query($link, $sql)) {
    if (mysqli_num_rows($result) == 1) {
        $db_settings = mysqli_fetch_assoc($result);
        // Overwrite default settings with database values if they exist and are not empty
        foreach ($settings as $key => $value) {
            if (isset($db_settings[$key]) && $db_settings[$key] !== NULL && $db_settings[$key] !== '') {
                $settings[$key] = $db_settings[$key];
            }
        }
    }
    mysqli_free_result($result);
} else {
    error_log("Error fetching academics_settings: " . mysqli_error($link));
}

// Decode academic levels JSON for display
$academic_levels_display = [];
$decoded_levels = json_decode($settings['academic_levels_json'], true);
if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_levels)) {
    $academic_levels_display = $decoded_levels;
}

// Decode academic features JSON for display
$academic_features_display = [];
$decoded_features = json_decode($settings['academic_features_json'], true);
if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_features)) {
    $academic_features_display = $decoded_features;
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
    <title>Academic Excellence</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #000000; /* text-gray-600 */
        }
        html, body {
            background-color: #f3f4f6 !important;
            background-image: none !important;
        }

        .section-padding {
            padding: 5rem 0;
        }
        .container-padding {
            padding: 0 1rem;
        }
        @media (min-width: 640px) {
            .container-padding {
                padding: 0 2rem;
            }
        }
        @media (min-width: 1024px) {
            .container-padding {
                padding: 0 4rem;
            }
        }
        .text-gradient-primary {
            background-image: linear-gradient(90deg, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .card-hover {
            transition: all 0.3s ease-in-out;
            border-radius: 1.5rem; /* rounded-2xl */
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .academic-levels-card {
            background-color: #fff;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        .academic-features-container {
            position: relative;
            overflow: hidden;
            border-radius: 1.5rem; /* rounded-3xl */
            background-color: #f3f4f6; /* bg-gray-100 */
        }
        .animated-gradient-border {
            position: relative;
            z-index: 1;
        }
        .animated-gradient-border::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #a855f7, #6366f1, #a855f7, #f9a8d4, #f472b6);
            background-size: 200% 200%;
            animation: gradient-flow 5s ease-in-out infinite alternate;
            border-radius: 1.6rem;
            z-index: -1;
            filter: blur(8px);
            opacity: 0.8;
            transition: opacity 0.3s ease-in-out;
        }
        .animated-gradient-border:hover::before {
            opacity: 1;
        }
        @keyframes gradient-flow {
            0% { background-position: 0% 50%; }
            100% { background-position: 100% 50%; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <section id="academics" class="section-padding">
        <div class="container-padding">
            <div class="text-center mb-16">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary">
                    <?php echo htmlspecialchars($settings['academics_section_title']); ?>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    <?php echo nl2br(htmlspecialchars($settings['academics_section_description'])); ?>
                </p>
            </div>

            <!-- Academic Levels -->
            <div class="space-y-8 mb-20">
                <?php foreach ($academic_levels_display as $level): ?>
                    <div class="card-hover academic-levels-card overflow-hidden rounded-2xl shadow-sm">
                        <div class="p-0">
                            <div class="grid lg:grid-cols-4 gap-0">
                                <div class="lg:col-span-1 bg-gradient-to-br from-indigo-500 to-purple-600 p-8 text-white flex items-center rounded-t-2xl lg:rounded-l-2xl lg:rounded-tr-none">
                                    <div class="text-center w-full">
                                        <?php echo $level['icon'] ?? ''; ?>
                                        <h3 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($level['title'] ?? ''); ?></h3>
                                        <span class="inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-xs font-medium text-white ring-1 ring-inset ring-white/30">
                                            <?php echo htmlspecialchars($level['subtitle'] ?? ''); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="lg:col-span-3 p-8">
                                    <p class="text-lg text-gray-600 mb-6"><?php echo nl2br(htmlspecialchars($level['description'] ?? '')); ?></p>
                                    <div>
                                        <h4 class="font-semibold text-gray-900 mb-3">Key Subjects:</h4>
                                        <div class="flex flex-wrap gap-2">
                                            <?php 
                                            // Subjects are stored as an array in JSON
                                            $subjects = $level['subjects'] ?? [];
                                            foreach ($subjects as $subject): ?>
                                                <span class="inline-flex items-center rounded-full bg-gray-50 px-3 py-1 text-sm font-medium text-gray-800 ring-1 ring-inset ring-gray-200">
                                                    <?php echo htmlspecialchars($subject); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Academic Features -->
            <div class="bg-white rounded-3xl p-8 lg:p-12 border border-gray-200 animated-gradient-border">
                <h3 class="text-3xl font-bold text-center mb-12 text-gray-900">
                    <?php echo htmlspecialchars($settings['academic_features_title']); ?>
                </h3>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <?php foreach ($academic_features_display as $feature): ?>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <?php echo $feature['icon'] ?? ''; ?>
                            </div>
                            <h4 class="text-xl font-semibold mb-3 text-gray-900"><?php echo htmlspecialchars($feature['title'] ?? ''); ?></h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($feature['description'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
</body>
</html>