<?php
// This is a dynamic PHP file for the "About Mednova" section.
// It fetches content from the about_mednova_settings table.

require_once __DIR__ . '/../database/config.php'; // Adjust path if necessary

// Initialize an array to hold settings, with default values or empty strings
$settings = [
    'hero_title' => 'Welcome to Mednova School',
    'hero_subtitle_1' => '**Igniting Minds, Shaping Futures:** Your Child\'s Journey of Excellence in Professional English Medium Education.',
    'hero_subtitle_2' => 'A CBSE-affiliated institution, meticulously crafting leaders from Nursery to Grade 12.',
    'hero_image_url' => 'https://placehold.co/1920x800/e0e7ff/6366f1?text=Mednova+School+Facade',
    'story_title' => 'Our Story & Guiding Philosophy',
    'story_description' => 'Established in 2005, Mednova School has blossomed into a vibrant learning sanctuary, dedicated to holistic growth and academic mastery from the foundational years to higher secondary.',
    'legacy_image_url' => 'https://placehold.co/900x600/D1D5DB/4B5563?text=Mednova+Students',
    'legacy_title' => 'A Legacy of Transformative Education',
    'legacy_description' => 'Mednova School embarked on its journey with a profound vision: to cultivate not just scholars, but confident, compassionate, and globally-aware citizens.',
    'vision_title' => 'Our Vision',
    'vision_description' => 'To be a leading educational institution, shaping students into resilient, ethical, and inventive individuals who actively contribute to a sustainable and diverse global society.',
    'mission_title' => 'Our Mission',
    'mission_description' => 'To deliver an inspiring and challenging academic program within a professional English medium setting, fostering intellectual curiosity, creativity, and critical thinking.',
    'core_pillars_title' => 'The Mednova Advantage: Our Core Pillars',
    'core_pillars_description' => 'Built upon foundational principles, we ensure every student thrives from Nursery to Grade 12.',
    'core_pillars_json' => '[]',
    'why_choose_us_title' => 'Why Choose Mednova School?',
    'why_choose_us_description' => 'Distinguished by our unwavering commitment to innovation, holistic growth, and student success.',
    'why_choose_us_json' => '[]',
    'curriculum_journey_title' => 'Our Comprehensive Curriculum Journey (Nursery to Grade 12)',
    'curriculum_journey_description' => 'Mednova School offers a dynamic CBSE curriculum, tailored to each developmental stage, ensuring progressive and engaging learning.',
    'curriculum_stages_json' => '[]',
    'infra_title' => 'World-Class Infrastructure & Facilities',
    'infra_description' => 'Our meticulously designed campus offers a stimulating environment, fostering learning, creativity, and exploration.',
    'infra_json' => '[]',
    'beyond_academics_title' => 'Beyond Academics: Cultivating Passion & Purpose',
    'beyond_academics_description' => 'We nurture well-rounded individuals through a rich tapestry of co-curricular activities, sparking joy and building character.',
    'beyond_academics_json' => '[]',
    'core_values_title' => 'Our Unwavering Core Values',
    'core_values_description' => 'These principles guide our every action, shaping the Mednova community and its future leaders.',
    'core_values_json' => '[]',
    'faculty_title' => 'Our Pillars of Strength: The Mednova Faculty',
    'faculty_description' => 'Our team of highly qualified and passionate educators is the heart of Mednova, inspiring students from Nursery to Grade 12.',
    'faculty_image_url' => 'https://placehold.co/240x240/a855f7/f3e8ff?text=Mednova+Teachers',
    'faculty_sub_title' => 'Inspiring Minds, Mentoring Futures',
    'faculty_sub_description' => 'At Mednova School, our educators are more than instructors; they are dedicated mentors, innovators, and lifelong learners.',
    'faculty_highlights_json' => '[]',
    'principal_image_url' => 'https://placehold.co/400x400/312e81/c7d2fe?text=Mrs.+Priya+Sharma',
    'principal_message_title' => 'A Message from Our Esteemed Principal',
    'principal_quote' => '"At Mednova School, we are profoundly dedicated to unlocking the unique potential within every single child."',
    'principal_name' => 'Mrs. Priya Sharma',
    'principal_role' => 'Principal, Mednova School',
    'principal_qualifications' => 'M.A. (English Literature), B.Ed., Advanced Certification in Educational Leadership'
];

// Fetch settings from the database
$sql = "SELECT * FROM about_mednova_settings WHERE id = 1";
if ($result = mysqli_query($link, $sql)) {
    if (mysqli_num_rows($result) == 1) {
        $db_settings = mysqli_fetch_assoc($result);
        foreach ($settings as $key => $value) {
            // Only update if DB value is not NULL and not an empty string
            if (isset($db_settings[$key]) && $db_settings[$key] !== NULL && $db_settings[$key] !== '') {
                $settings[$key] = $db_settings[$key];
            }
        }
    }
    mysqli_free_result($result);
} else {
    error_log("Error fetching about_mednova_settings: " . mysqli_error($link));
}

// Decode all JSON fields for display
$core_pillars_display = json_decode($settings['core_pillars_json'], true) ?: [];
$why_choose_us_display = json_decode($settings['why_choose_us_json'], true) ?: [];
$curriculum_stages_display = json_decode($settings['curriculum_stages_json'], true) ?: [];
$infra_display = json_decode($settings['infra_json'], true) ?: [];
$beyond_academics_display = json_decode($settings['beyond_academics_json'], true) ?: [];
$core_values_display = json_decode($settings['core_values_json'], true) ?: [];
$faculty_highlights_display = json_decode($settings['faculty_highlights_json'], true) ?: [];

// mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Mednova School - Nurturing Future Leaders with Professional English Medium Education</title>
    <meta name="description" content="<?php echo htmlspecialchars($settings['story_description']); ?>">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #374151; /* text-gray-700 */
            line-height: 1.6;
            scroll-behavior: smooth; /* Smooth scrolling for anchor links */
            background-color: white;
        }
        .section-padding { padding: 6rem 0; }
        .container-padding {
            max-width: 1300px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 1.5rem;
        }
        @media (min-width: 640px) { .container-padding { padding: 0 2.5rem; } }
        @media (min-width: 1024px) { .container-padding { padding: 0 5rem; } }
        
        .text-gradient-primary {
            background-image: linear-gradient(90deg, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
        }
        .bg-gradient-subtle { background-color: white; }
        .card-hover {
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            transform-origin: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .card-hover:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 18px 35px rgba(0, 0, 0, 0.15);
            border-color: #a78bfa;
            background-color: #fcfaff;
        }
        .card-hover .icon-gradient-bg { transition: all 0.3s ease; }
        .card-hover:hover .icon-gradient-bg {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 6px 15px rgba(168, 85, 247, 0.6);
        }
        .animate-slide-in-left { animation: slideInLeft 0.9s ease-out forwards; opacity: 0; }
        .animate-slide-in-right { animation: slideInRight 0.9s ease-out forwards; opacity: 0; }
        .animate-fade-in { animation: fadeIn 1.2s ease-out forwards; opacity: 0; }
        @keyframes slideInLeft { from { opacity: 0; transform: translateX(-80px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(80px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .animated-gradient-bg {
            position: relative;
            overflow: hidden;
            border-radius: 1.75rem;
            padding: 2px;
            background: transparent;
        }
        .animated-gradient-bg::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, #6366f1, #a855f7, #d8b4fe, #f9a8d4, #ec4899);
            background-size: 200% 200%;
            animation: gradient-animation 12s ease-in-out infinite alternate;
            z-index: 0;
            border-radius: inherit;
        }
        .animated-gradient-bg > div {
            position: relative;
            z-index: 1;
            background-color: white;
            border-radius: calc(1.75rem - 2px);
        }
        @keyframes gradient-animation { 0% { background-position: 0% 50%; } 100% { background-position: 100% 50%; } }
        
        .icon-gradient-bg {
            background-image: linear-gradient(45deg, #6366f1, #a855f7);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.4);
        }
        .hero-section {
            background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.6)), url('<?php echo htmlspecialchars($settings['hero_image_url']); ?>');
            background-size: cover;
            background-position: center;
            position: relative;
            color: white;
            padding: 10rem 0;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.5);
            border-radius: 0 0 3rem 3rem;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .divider {
            height: 2px;
            background-image: linear-gradient(90deg, transparent, #e5e7eb, transparent);
            margin: 4rem auto;
            max-width: 80%;
        }
        .cta-button {
            background-image: linear-gradient(45deg, #6366f1, #a855f7);
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(168, 85, 247, 0.4);
            border: none;
        }
        .cta-button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 20px rgba(168, 85, 247, 0.6);
            opacity: 0.9;
        }

        .icon-wrapper {
            width: 3.5rem;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            flex-shrink: 0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .icon-wrapper-lg {
            width: 4rem;
            height: 4rem;
            border-radius: 9999px;
        }
        .icon-wrapper svg { width: 1.5rem; height: 1.5rem; color: currentColor; }
        .icon-wrapper-lg svg { width: 2rem; height: 2rem; color: white; }
        .icon-wrapper svg.currentColor { color: currentColor !important; }

        /* --- Section Toggle Styles --- */
        .section-toggle-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            transition: border-color var(--transition-speed);
        }
        .section-toggle-header h3 {
            margin: 0;
            padding: 0;
            border-bottom: none;
            text-align: left;
            font-size: 1.8rem;
            color: #1f2937;
        }
        .section-toggle-header .toggle-icon {
            transition: transform var(--transition-speed);
        }
        .section-toggle-header[aria-expanded="true"] .toggle-icon {
            transform: rotate(180deg);
        }
        .section-content {
            max-height: 2000px; /* Arbitrary large value */
            overflow: hidden;
            transition: max-height 0.5s ease-out, opacity 0.5s ease-out;
            opacity: 1;
        }
        .section-content.collapsed {
            max-height: 0;
            opacity: 0;
            margin-bottom: 0;
            padding-bottom: 0;
        }

    </style>
</head>
<body class="bg-gradient-subtle">
    <?php require_once '../header.php'; ?>

    <!-- Hero/Intro Section -->
    <section class="hero-section text-center">
        <div class="container-padding animate-fade-in">
            <h1 class="text-5xl lg:text-7xl font-extrabold mb-6 leading-tight tracking-tight"><?php echo htmlspecialchars($settings['hero_title']); ?></h1>
            <p class="text-2xl lg:text-3xl font-light max-w-5xl mx-auto"><?php echo htmlspecialchars($settings['hero_subtitle_1']); ?></p>
            <p class="text-xl lg:text-2xl font-light max-w-4xl mx-auto mt-4 opacity-90"><?php echo htmlspecialchars($settings['hero_subtitle_2']); ?></p>
        </div>
    </section>

    <section id="about" class="section-padding bg-gradient-subtle">
        <div class="container-padding">
            <div class="text-center mb-16 animate-fade-in" style="animation-delay: 0.2s;">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary"><?php echo htmlspecialchars($settings['story_title']); ?></h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto"><?php echo nl2br(htmlspecialchars($settings['story_description'])); ?></p>
            </div>

            <!-- History & Vision Section -->
            <div class="grid lg:grid-cols-2 gap-16 items-center mb-20">
                <div class="animate-slide-in-left" style="animation-delay: 0.4s;">
                    <img src="<?php echo htmlspecialchars($settings['legacy_image_url']); ?>" alt="Mednova School Legacy" class="w-full h-[400px] object-cover rounded-3xl shadow-xl border border-gray-200 hover:shadow-2xl transition-shadow duration-300" onerror="this.onerror=null;this.src='https://placehold.co/900x600/4b5563/d1d5db?text=Image+Unavailable';">
                </div>
                <div class="animate-slide-in-right" style="animation-delay: 0.6s;">
                    <h3 class="text-3xl font-bold mb-6 text-gray-900"><?php echo htmlspecialchars($settings['legacy_title']); ?></h3>
                    <p class="text-lg text-gray-700 mb-6 leading-relaxed"><?php echo nl2br(htmlspecialchars($settings['legacy_description'])); ?></p>
                    <div class="space-y-6 mt-8">
                        <div>
                            <h4 class="text-xl font-semibold text-gray-900 mb-2 flex items-center"><span class="mr-3 text-indigo-500 text-2xl font-bold">&#10022;</span> <?php echo htmlspecialchars($settings['vision_title']); ?></h4>
                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($settings['vision_description'])); ?></p>
                        </div>
                        <div>
                            <h4 class="text-xl font-semibold text-gray-900 mb-2 flex items-center"><span class="mr-3 text-purple-500 text-2xl font-bold">&#10022;</span> <?php echo htmlspecialchars($settings['mission_title']); ?></h4>
                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($settings['mission_description'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Divider -->
            <div class="divider animate-fade-in" style="animation-delay: 0.8s;"></div>

            <!-- Core Pillars / Features Grid -->
            <div class="section-toggle-header" id="core-pillars-toggle-header" aria-expanded="true" aria-controls="core-pillars-content">
                <h3 class="text-3xl lg:text-4xl font-bold text-gray-900"><?php echo htmlspecialchars($settings['core_pillars_title']); ?></h3>
                <svg class="toggle-icon h-8 w-8 text-indigo-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            <div id="core-pillars-content" class="section-content">
                <p class="text-lg text-gray-600 max-w-2xl mx-auto text-center mb-12 animate-fade-in"><?php echo nl2br(htmlspecialchars($settings['core_pillars_description'])); ?></p>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-20">
                    <?php foreach ($core_pillars_display as $index => $feature): ?>
                        <div class="card-hover text-center p-6 bg-white rounded-2xl animate-fade-in" style="animation-delay: <?php echo 0.2 + ($index * 0.15); ?>s;">
                            <div class="icon-wrapper icon-wrapper-lg icon-gradient-bg mx-auto mb-4"><?php echo $feature['icon']; ?></div>
                            <h3 class="text-xl font-semibold mb-3 text-gray-900"><?php echo htmlspecialchars($feature['title']); ?></h3>
                            <p class="text-gray-700 text-sm leading-relaxed"><?php echo htmlspecialchars($feature['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>


            <!-- Why Choose Us Section -->
            <div class="section-toggle-header" id="why-choose-us-toggle-header" aria-expanded="true" aria-controls="why-choose-us-content">
                <h3 class="text-3xl lg:text-4xl font-bold text-gray-900"><?php echo htmlspecialchars($settings['why_choose_us_title']); ?></h3>
                <svg class="toggle-icon h-8 w-8 text-indigo-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            <div id="why-choose-us-content" class="section-content">
                <p class="text-lg text-gray-600 max-w-3xl mx-auto text-center mb-12 animate-fade-in"><?php echo nl2br(htmlspecialchars($settings['why_choose_us_description'])); ?></p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 mb-20">
                    <?php foreach ($why_choose_us_display as $index => $item): ?>
                        <div class="card-hover bg-white p-6 rounded-2xl flex items-start space-x-4 animate-fade-in" style="animation-delay: <?php echo 0.2 + ($index * 0.1); ?>s;">
                            <div class="icon-wrapper rounded-xl bg-gradient-to-br from-indigo-100 to-violet-100 text-indigo-700"><?php echo $item['icon']; ?></div>
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($item['title']); ?></h4>
                                <p class="text-gray-700 text-sm leading-relaxed"><?php echo htmlspecialchars($item['description']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Other sections would follow the same pattern... -->
            
            <!-- Faculty Section -->
            <div class="section-toggle-header" id="faculty-toggle-header" aria-expanded="true" aria-controls="faculty-content">
                <h3 class="text-3xl lg:text-4xl font-bold text-gray-900"><?php echo htmlspecialchars($settings['faculty_title']); ?></h3>
                <svg class="toggle-icon h-8 w-8 text-indigo-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            <div id="faculty-content" class="section-content">
                <p class="text-lg text-gray-600 max-w-2xl mx-auto text-center mb-12 animate-fade-in"><?php echo nl2br(htmlspecialchars($settings['faculty_description'])); ?></p>
                <div class="card-hover bg-white rounded-3xl shadow-xl border border-gray-200 p-8 lg:p-12 mb-20 animate-fade-in">
                    <div class="flex flex-col lg:flex-row items-center lg:items-start space-y-8 lg:space-y-0 lg:space-x-12">
                        <img src="<?php echo htmlspecialchars($settings['faculty_image_url']); ?>" alt="Mednova Faculty" class="w-56 h-56 object-cover rounded-full shadow-md flex-shrink-0 border-4 border-purple-200" onerror="this.onerror=null;this.src='https://placehold.co/240x240/4b5563/d1d5db?text=Image+Unavailable';">
                        <div>
                            <h4 class="text-2xl font-bold mb-4 text-gray-900"><?php echo htmlspecialchars($settings['faculty_sub_title']); ?></h4>
                            <p class="text-lg text-gray-700 mb-5 leading-relaxed"><?php echo nl2br(htmlspecialchars($settings['faculty_sub_description'])); ?></p>
                            <ul class="list-disc list-inside text-gray-700 space-y-3 pl-4">
                                <?php foreach ($faculty_highlights_display as $highlight): ?>
                                    <li class="flex items-center"><span class="mr-2 text-indigo-500">&#10003;</span> <?php echo htmlspecialchars($highlight); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Principal's Message -->
            <div class="card-hover animated-gradient-bg animate-fade-in">
                <div class="bg-white rounded-[1.65rem] p-8 lg:p-14">
                    <div class="grid lg:grid-cols-3 gap-8 items-center">
                        <div class="lg:col-span-1 text-center">
                            <img src="<?php echo htmlspecialchars($settings['principal_image_url']); ?>" alt="Principal Photo" class="w-full max-w-xs mx-auto rounded-full shadow-lg border-4 border-indigo-200 transform hover:scale-105 transition-transform duration-300" onerror="this.onerror=null;this.src='https://placehold.co/400x400/4b5563/d1d5db?text=Image+Unavailable';">
                        </div>
                        <div class="lg:col-span-2">
                            <h3 class="text-3xl font-bold mb-5 text-gray-900"><?php echo htmlspecialchars($settings['principal_message_title']); ?></h3>
                            <blockquote class="text-xl text-gray-600 italic mb-6 leading-loose">"<?php echo nl2br(htmlspecialchars($settings['principal_quote'])); ?>"</blockquote>
                            <div>
                                <p class="font-bold text-gray-900 text-xl"><?php echo htmlspecialchars($settings['principal_name']); ?></p>
                                <p class="text-gray-700 text-lg"><?php echo htmlspecialchars($settings['principal_role']); ?></p>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($settings['principal_qualifications']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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

            // Apply toggles to all sections, with initial states
            setupSectionToggle('core-pillars-toggle-header', 'core-pillars-content', true);
            setupSectionToggle('why-choose-us-toggle-header', 'why-choose-us-content', true);
            setupSectionToggle('faculty-toggle-header', 'faculty-content', true);
            // Example of a default collapsed section:
            // setupSectionToggle('some-other-toggle-header', 'some-other-content', false); 
        });

        // Initialize Lucide icons if used
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    </script>
</body>
</html>
<?php
// Include footer if available (adjust path as needed)
require_once __DIR__ . '/../footer.php';
?>