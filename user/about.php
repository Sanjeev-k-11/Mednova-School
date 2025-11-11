<?php
// This is a dynamic PHP file for the About section.
// It fetches content from the school_settings table.

// Include your database configuration
require_once __DIR__ . '/../database/config.php'; // Adjust path if necessary

// Initialize an array to hold settings, with default values or empty strings
$settings = [
    // About Section - Defaults
    'about_section_title' => 'About Excellence School',
    'about_section_description' => 'Established in 1995, Excellence School has been at the forefront of quality education, nurturing young minds and building strong foundations for future success.',
    'about_image_url' => 'https://placehold.co/900x600/6b7280/d1d5db?text=School+Building', // Default fallback image
    'our_legacy_text' => 'Founded with a vision to provide world-class education, Excellence School has grown from a small institution to one of the most respected schools in the region. Our commitment to academic excellence and character development has shaped thousands of successful individuals.',
    'our_vision_text' => 'To be a globally recognized institution that nurtures confident, compassionate, and capable individuals who contribute positively to society.',
    'our_mission_text' => 'To provide a stimulating and supportive learning environment that encourages intellectual curiosity, creativity, and critical thinking while instilling strong moral values.',
    'features_json' => '[]', // Default to empty JSON array
    'principal_message_quote' => '"At Excellence School, we believe that every child is unique and has the potential to achieve greatness. Our role is to provide the right environment, guidance, and opportunities for them to discover their talents and reach their full potential. We are committed to preparing our students not just for examinations, but for life."',
    'principal_name' => 'Dr. Sarah Johnson',
    'principal_title_role' => 'Principal, Excellence School',
    'principal_qualifications' => 'M.Ed., Ph.D. in Educational Leadership',
    'principal_image_url' => 'https://placehold.co/400x400/1e293b/d1d5db?text=Dr.+Sarah+Johnson' // Default fallback image
];

// Fetch settings from the database
$sql = "SELECT * FROM about_settings WHERE id = 1"; // Query the about_settings table
if ($result = mysqli_query($link, $sql)) {
    if (mysqli_num_rows($result) == 1) {
        $db_settings = mysqli_fetch_assoc($result);
        // Overwrite default settings with database values if they exist
        foreach ($settings as $key => $value) {
            // Only update if DB value is not NULL and not an empty string
            if (isset($db_settings[$key]) && $db_settings[$key] !== NULL && $db_settings[$key] !== '') {
                $settings[$key] = $db_settings[$key];
            }
        }
    }
    mysqli_free_result($result);
} else {
    error_log("Error fetching school_settings for About section: " . mysqli_error($link));
}

// Decode features JSON for display
$features_display = [];
$decoded_features = json_decode($settings['features_json'], true);
if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_features)) {
    $features_display = $decoded_features;
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
    <title>About Section</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #4b5563; /* text-gray-600 */
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
        .bg-gradient-subtle {
            background-color: #f3f4f6; /* bg-gray-100 */
        }
        .card-hover {
            transition: all 0.3s ease-in-out;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .animate-slide-in-left {
            animation: slideInLeft 0.8s ease-out forwards;
            opacity: 0;
        }
        .animate-slide-in-right {
            animation: slideInRight 0.8s ease-out forwards;
            opacity: 0;
        }
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .animated-gradient-bg {
            position: relative;
            overflow: hidden;
            border-radius: 1.5rem; /* rounded-2xl */
        }
        .animated-gradient-bg::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                #6366f1, /* indigo-500 */
                #8b5cf6, /* violet-500 */
                #d8b4fe, /* purple-300 */
                #f9a8d4, /* pink-300 */
                #f472b6  /* pink-400 */
            );
            background-size: 200% 200%;
            animation: gradient-animation 10s ease-in-out infinite;
            z-index: 0;
        }
        .animated-gradient-bg > * {
            position: relative;
            z-index: 1;
        }
        @keyframes gradient-animation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <section id="about" class="section-padding bg-gradient-subtle">
        <div class="container-padding">
            <div class="text-center mb-16">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary">
                    <?php echo htmlspecialchars($settings['about_section_title']); ?>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    <?php echo nl2br(htmlspecialchars($settings['about_section_description'])); ?>
                </p>
            </div>

            <!-- History & Vision Section -->
            <div class="grid lg:grid-cols-2 gap-12 mb-20">
                <div class="animate-slide-in-left">
                    <img
                        src="<?php echo htmlspecialchars($settings['about_image_url']); ?>"
                        alt="Excellence School Building"
                        class="w-full h-96 object-cover rounded-2xl shadow-lg"
                        onerror="this.onerror=null;this.src='https://placehold.co/900x600/4b5563/d1d5db?text=Image+Unavailable';"
                    />
                </div>
                <div class="animate-slide-in-right">
                    <h3 class="text-3xl font-bold mb-6 text-gray-900">Our Legacy</h3>
                    <p class="text-lg text-gray-600 mb-6">
                        <?php echo nl2br(htmlspecialchars($settings['our_legacy_text'])); ?>
                    </p>
                    
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-xl font-semibold text-gray-900 mb-2">Our Vision</h4>
                            <p class="text-gray-600">
                                <?php echo nl2br(htmlspecialchars($settings['our_vision_text'])); ?>
                            </p>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold text-gray-900 mb-2">Our Mission</h4>
                            <p class="text-gray-600">
                                <?php echo nl2br(htmlspecialchars($settings['our_mission_text'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-20">
                <?php foreach ($features_display as $feature): ?>
                    <div class="card-hover text-center p-6 bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="p-0">
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <?php 
                                    // Make sure to echo the SVG as HTML, not escape it
                                    // Always check if the key exists before echoing
                                    echo $feature['icon'] ?? ''; 
                                ?>
                            </div>
                            <h3 class="text-xl font-semibold mb-3 text-gray-900"><?php echo htmlspecialchars($feature['title'] ?? ''); ?></h3>
                            <p class="text-gray-600"><?php echo htmlspecialchars($feature['description'] ?? ''); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Principal's Message -->
            <div class="card-hover animated-gradient-bg relative z-10 p-1">
                <div class="bg-white rounded-[1.4rem] p-8 lg:p-12">
                    <div class="grid lg:grid-cols-3 gap-8 items-center">
                        <div class="lg:col-span-1">
                            <img
                                src="<?php echo htmlspecialchars($settings['principal_image_url']); ?>"
                                alt="Principal Photo"
                                class="w-full max-w-xs mx-auto rounded-2xl shadow-lg"
                                onerror="this.onerror=null;this.src='https://placehold.co/400x400/4b5563/d1d5db?text=Image+Unavailable';"
                            />
                        </div>
                        <div class="lg:col-span-2">
                            <h3 class="text-3xl font-bold mb-4 text-gray-900">Principal's Message</h3>
                            <blockquote class="text-lg text-gray-600 italic mb-6">
                                "<?php echo nl2br(htmlspecialchars($settings['principal_message_quote'])); ?>"
                            </blockquote>
                            <div>
                                <p class="font-semibold text-gray-900 text-lg"><?php echo htmlspecialchars($settings['principal_name']); ?></p>
                                <p class="text-gray-600"><?php echo htmlspecialchars($settings['principal_title_role']); ?></p>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($settings['principal_qualifications']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>