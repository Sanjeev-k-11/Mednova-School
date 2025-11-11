<?php
// =================================================================================
// 1. DATABASE CONNECTION & CONFIGURATION
// =================================================================================

// --- CHANGE: Include the database configuration file ---
// This file creates the $link variable for our database connection.
require_once '../database/config.php';


// =================================================================================
// 2. BACKEND DATA FETCHING LOGIC (Using MySQLi)
// =================================================================================

function fetch_academics_page_content($db_connection) {
    // The SQL query is the same.
    $sql = "SELECT * FROM academics_mednova_settings WHERE id = 1 LIMIT 1";

    // --- CHANGE: Execute query using mysqli ---
    $result = mysqli_query($db_connection, $sql);

    // Handle potential query errors
    if (!$result) {
        // In production, log this error instead of showing it to the user
        die("Database query failed: " . mysqli_error($db_connection));
    }

    // --- CHANGE: Fetch data using mysqli ---
    $settings = mysqli_fetch_assoc($result);

    if (!$settings) {
        return null; // No settings found
    }

    // Decode the JSON fields into PHP associative arrays. This part remains the same.
    $settings['academic_levels']   = json_decode($settings['academic_levels_json'], true) ?? [];
    $settings['holistic_items']    = json_decode($settings['holistic_items_json'], true) ?? [];
    $settings['academic_features'] = json_decode($settings['academic_features_json'], true) ?? [];

    return $settings;
}

// --- Fetch the dynamic content from the database, passing the $link from config.php ---
$page_content = fetch_academics_page_content($link);

// --- Graceful Error Handling ---
if (!$page_content) {
    exit('Error: Academics page settings could not be loaded. Please ensure the data exists in the database.');
}

// Close the database connection when it's no longer needed
mysqli_close($link);


// =================================================================================
// 3. PRESENTATION LOGIC (Styling and Helper Functions - No Changes Here)
// =================================================================================

function get_tailwind_hex_color($class) {
    $colors = ['white' => '#FFFFFF', 'gray-200' => '#E5E7EB', 'blue-50' => '#EFF6FF', 'blue-700' => '#1D4ED8', 'blue-800' => '#1E40AF', 'emerald-600' => '#059669', 'teal-50' => '#F0FDFA', 'teal-700' => '#0F766E', 'amber-400' => '#FBBF24', 'amber-600' => '#D97706', 'amber-700' => '#B45309', 'red-600' => '#DC2626', 'red-800' => '#991B1B', 'purple-50' => '#F5F3FF', 'purple-600' => '#9333EA', 'purple-700' => '#7E22CE', 'purple-800' => '#6B21A8', 'yellow-50' => '#FFFBEB', 'green-50' => '#F0FDF4', 'green-700' => '#15803D'];
    preg_match('/(from|to|bg|text)-([a-z]+)-?(\d+)?/', $class, $matches);
    $key = ($matches[2] ?? '') . (isset($matches[3]) ? '-' . $matches[3] : '');
    return $colors[$key] ?? '#CCCCCC';
}

function format_description($text) {
    $safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $formatted_text = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-semibold text-teal-700">$1</strong>', $safe_text);
    return nl2br($formatted_text);
}

$level_colors = [['from' => 'blue-700', 'to' => 'blue-800', 'text' => 'white'], ['from' => 'emerald-600', 'to' => 'teal-700', 'text' => 'white'], ['from' => 'amber-400', 'to' => 'amber-600', 'text' => 'white'], ['from' => 'red-600', 'to' => 'red-800', 'text' => 'white'], ['from' => 'purple-600', 'to' => 'purple-800', 'text' => 'white']];
$feature_colors = [['from' => 'blue-700', 'to' => 'blue-800', 'text' => 'white'], ['from' => 'amber-400', 'to' => 'amber-600', 'text' => 'white'], ['from' => 'emerald-600', 'to' => 'teal-700', 'text' => 'white'], ['from' => 'purple-600', 'to' => 'purple-700', 'text' => 'white']];

include_once '../header.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_content['main_title']); ?> - Mednova School</title>
    <meta name="description" content="<?php echo htmlspecialchars($page_content['main_description']); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; color: #374151; line-height: 1.6; background-color: white; scroll-behavior: smooth; }
        h1, h2, h3, h4 { font-family: 'Playfair Display', serif; }
        .section-padding { padding: 6rem 0; }
        .container-padding { max-width: 1300px; margin: 0 auto; padding: 0 1.5rem; }
        @media (min-width: 640px) { .container-padding { padding: 0 2.5rem; } }
        @media (min-width: 1024px) { .container-padding { padding: 0 5rem; } }
        .text-gradient-primary { background-image: linear-gradient(90deg, <?php echo get_tailwind_hex_color('blue-800'); ?>, <?php echo get_tailwind_hex_color('amber-700'); ?>); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 800; }
        .card-hover { transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08); border: 1px solid #e5e7eb; }
        .card-hover:hover { transform: translateY(-8px) scale(1.01); box-shadow: 0 18px 35px rgba(0, 0, 0, 0.15); border-color: #a78bfa; background-color: #fcfaff; }
        .animated-gradient-border { position: relative; overflow: hidden; border-radius: 1.75rem; padding: 2px; background: transparent; }
        .animated-gradient-border::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(45deg, <?php echo get_tailwind_hex_color('blue-800'); ?>, <?php echo get_tailwind_hex_color('amber-700'); ?>, <?php echo get_tailwind_hex_color('purple-700'); ?>); background-size: 200% 200%; animation: gradient-animation 12s ease-in-out infinite alternate; z-index: 0; }
        .animated-gradient-border > div { position: relative; z-index: 1; background-color: white; border-radius: calc(1.75rem - 2px); }
        @keyframes gradient-animation { 0% { background-position: 0% 50%; } 100% { background-position: 100% 50%; } }
        .animate-fade-in { opacity: 0; animation: fadeIn 1.2s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <section id="academics" class="section-padding bg-white">
        <div class="container-padding">
            <div class="text-center mb-16 animate-fade-in" style="animation-delay: 0.2s;">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary"><?php echo htmlspecialchars($page_content['main_title']); ?></h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto"><?php echo htmlspecialchars($page_content['main_description']); ?></p>
            </div>

            <div class="space-y-8 mb-20">
                <h3 class="text-3xl font-bold text-center text-gray-900 mb-12 animate-fade-in" style="animation-delay: 0.4s;"><?php echo htmlspecialchars($page_content['journey_title']); ?></h3>
                <?php foreach ($page_content['academic_levels'] as $index => $level):
                    $colors = $level_colors[$index % count($level_colors)];
                    $from_hex = get_tailwind_hex_color($colors['from']);
                    $to_hex = get_tailwind_hex_color($colors['to']);
                ?>
                    <div class="card-hover overflow-hidden rounded-2xl shadow-sm animate-fade-in" style="animation-delay: <?php echo 0.6 + ($index * 0.2); ?>s;">
                        <div class="grid lg:grid-cols-4 gap-0 h-full">
                            <div class="lg:col-span-1 p-8 text-white flex items-center justify-center" style="background-image: linear-gradient(to bottom right, <?php echo $from_hex; ?>, <?php echo $to_hex; ?>);">
                                 <div class="text-center w-full">
                                    <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center bg-white/20 text-<?php echo htmlspecialchars($colors['text']); ?>"><?php echo $level['icon']; ?></div>
                                    <h3 class="text-2xl font-bold mb-2 text-white"><?php echo htmlspecialchars($level['title']); ?></h3>
                                    <span class="inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-xs font-medium text-white ring-1 ring-inset ring-white/30"><?php echo htmlspecialchars($level['subtitle']); ?></span>
                                </div>
                            </div>
                            <div class="lg:col-span-3 p-8 flex flex-col justify-center bg-white">
                                <p class="text-lg text-gray-700 mb-6"><?php echo format_description($level['description']); ?></p>
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">Key Subjects:</h4>
                                    <div class="flex flex-wrap gap-2">
                                        <?php
                                            // Ensure 'subjects' key exists and is a string before exploding
                                            $subjects = isset($level['subjects']) && is_string($level['subjects']) ? explode(',', $level['subjects']) : [];
                                            foreach ($subjects as $subject): ?>
                                            <span class="inline-flex items-center rounded-full bg-gray-50 px-3 py-1 text-sm font-medium text-gray-800 ring-1 ring-inset ring-gray-200"><?php echo htmlspecialchars(trim($subject)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mb-16 animate-fade-in" style="animation-delay: 1.8s;">
                <h3 class="text-3xl lg:text-4xl font-bold mb-6 text-gray-900"><?php echo htmlspecialchars($page_content['holistic_title']); ?></h3>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto"><?php echo htmlspecialchars($page_content['holistic_description']); ?></p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-20">
                <?php foreach ($page_content['holistic_items'] as $index => $item): ?>
                    <div class="card-hover text-center p-6 bg-white rounded-2xl border border-gray-100 flex flex-col items-center animate-fade-in" style="animation-delay: <?php echo 2.0 + ($index * 0.15); ?>s;">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center <?php echo htmlspecialchars($item['bg_class'] ?? 'bg-gray-100'); ?> mb-4 <?php echo htmlspecialchars($item['text_class'] ?? 'text-gray-800'); ?>"><?php echo $item['icon']; ?></div>
                        <h4 class="text-xl font-semibold mb-2 text-gray-900"><?php echo htmlspecialchars($item['title']); ?></h4>
                        <p class="text-gray-700 flex-grow"><?php echo htmlspecialchars($item['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="animated-gradient-border animate-fade-in" style="animation-delay: 2.8s;">
                <div class="bg-white rounded-[1.65rem] p-8 lg:p-12">
                    <h3 class="text-3xl font-bold text-center mb-12 text-gray-900"><?php echo htmlspecialchars($page_content['features_title']); ?></h3>
                    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                        <?php foreach ($page_content['academic_features'] as $index => $feature):
                            $colors = $feature_colors[$index % count($feature_colors)];
                            $from_hex = get_tailwind_hex_color($colors['from']);
                            $to_hex = get_tailwind_hex_color($colors['to']);
                        ?>
                            <div class="card-hover text-center p-6 bg-white rounded-2xl shadow-sm border border-gray-100 animate-fade-in" style="animation-delay: <?php echo 3.0 + ($index * 0.15); ?>s;">
                                <div class="w-14 h-14 rounded-xl mx-auto mb-4 flex items-center justify-center text-<?php echo htmlspecialchars($colors['text']); ?>" style="background-image: linear-gradient(to bottom right, <?php echo $from_hex; ?>, <?php echo $to_hex; ?>);">
                                    <?php echo $feature['icon']; ?>
                                </div>
                                <h4 class="text-xl font-semibold mb-3 text-gray-900"><?php echo htmlspecialchars($feature['title']); ?></h4>
                                <p class="text-gray-700"><?php echo format_description($feature['description']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
<?php

include_once '../footer.php';
?>