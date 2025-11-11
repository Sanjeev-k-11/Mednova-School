<?php
// This is a dynamic PHP file for the Gallery section.
// It fetches content from the gallery_settings table.

require_once __DIR__ . '/../database/config.php'; // Adjust path if necessary

// Initialize an array to hold settings, with default values or empty strings
$settings = [
    'gallery_section_title' => 'School Gallery',
    'gallery_section_description' => 'Explore our vibrant school life through images and videos. From state-of-the-art infrastructure to exciting activities and memorable events.',
    'categories_json' => '[]',
    'gallery_items_json' => '[]',
    'view_more_button_text' => 'View More Photos & Videos',
    'view_more_button_url' => '#', // Default URL
    'infra_highlights_title' => 'Infrastructure Highlights',
    'infra_highlights_json' => '[]'
];

// Fetch settings from the database
$sql = "SELECT * FROM gallery_settings WHERE id = 1";
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
    error_log("Error fetching gallery_settings: " . mysqli_error($link));
}

// Decode JSON data for display
$categories_display = json_decode($settings['categories_json'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($categories_display)) {
    $categories_display = []; // Fallback to empty array on error
}

// Ensure 'all' category is always present at the start for filtering UX
$hasAllCategory = false;
foreach ($categories_display as $cat) {
    if (($cat['id'] ?? '') === 'all') {
        $hasAllCategory = true;
        break;
    }
}
if (!$hasAllCategory) {
    array_unshift($categories_display, [
        "id" => "all", 
        "name" => "All", 
        "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line></svg>'
    ]);
}


$gallery_items_display = json_decode($settings['gallery_items_json'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($gallery_items_display)) {
    $gallery_items_display = []; // Fallback to empty array on error
}

$infra_highlights_display = json_decode($settings['infra_highlights_json'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($infra_highlights_display)) {
    $infra_highlights_display = []; // Fallback to empty array on error
}

// Function to generate a placeholder background for items without actual image URLs
function generatePlaceholderImageClass($item) {
    $colors = [
        "from-indigo-400 to-blue-500",
        "from-teal-400 to-green-500",
        "from-purple-400 to-pink-500",
        "from-orange-400 to-red-500",
        "from-blue-400 to-cyan-500",
        "from-pink-400 to-purple-500"
    ];
    // Use title to generate a consistent hash for color, or just a simple rotating index
    $hash = crc32($item['title'] ?? 'default_item') + (count($item) > 0 ? array_keys($item)[0] : 0);
    return "bg-gradient-to-br " . $colors[$hash % count($colors)];
}

// mysqli_close($link); // Close connection if this is the end of script execution,
                      // or let the main application handle it if this file is included.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['gallery_section_title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-blue: #4c51bf;
            --secondary-purple: #a855f7;
            --light-blue: #667eea;
            --dark-gray: #1a202c;
            --medium-gray: #4a5568;
            --light-gray-bg: #f3f4f6;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition-speed: 0.3s;
        }

        body {
            font-family: 'Poppins', sans-serif; /* Modern font */
            background: linear-gradient(135deg, #e0f2f7, #d0e8f0); /* Soft background gradient */
            color: var(--dark-gray);
            line-height: 1.6;
        }
        .section-padding {
            padding: 5rem 1rem;
        }
        .container-padding {
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 1.5rem;
        }
        h2 {
            font-family: 'Playfair Display', serif; /* Elegant serif for main titles */
            color: var(--dark-gray);
            font-weight: 700;
        }
        h3 {
            font-family: 'Poppins', sans-serif;
            color: var(--primary-blue);
            font-weight: 700;
        }
        p {
            color: var(--medium-gray);
        }

        .text-gradient-primary {
            background-image: linear-gradient(to right, var(--primary-blue), var(--secondary-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* --- Buttons --- */
        .hero-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.75rem; /* Slightly more padding */
            border-radius: 9999px; /* rounded-full */
            font-weight: 600;
            color: white;
            background-image: linear-gradient(to right, var(--primary-blue), var(--light-blue)); /* Gradient button */
            background-size: 200% auto;
            transition: all var(--transition-speed) ease-in-out;
            box-shadow: var(--shadow-md);
            border: none; /* Ensure no border */
        }
        .hero-button:hover {
            transform: translateY(-3px) scale(1.02); /* More pronounced hover */
            box-shadow: 0 12px 20px -5px rgba(76, 81, 191, 0.4); /* Enhanced shadow */
            background-position: right center; /* Slide gradient */
        }

        .hero-button-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.75rem;
            border-radius: 9999px;
            font-weight: 600;
            background-color: transparent;
            border: 2px solid white;
            color: white;
            transition: all var(--transition-speed) ease-in-out;
            box-shadow: var(--shadow-sm);
        }
        .hero-button-outline:hover {
            transform: translateY(-3px) scale(1.02);
            background-color: rgba(255, 255, 255, 0.15); /* Softer white tint */
            box-shadow: 0 12px 20px -5px rgba(255, 255, 255, 0.2);
        }
        
        /* --- Cards and Hover --- */
        .card-hover {
            transition: all var(--transition-speed) ease-in-out;
        }
        .card-hover:hover {
            transform: translateY(-6px); /* More lift */
            box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.15); /* Stronger shadow */
        }
        .slide-card-hover {
            position: relative;
            overflow: hidden;
            z-index: 1;
            border-radius: 0.75rem; /* consistent with other cards */
            box-shadow: var(--shadow-md); /* Apply initial shadow */
        }
        .slide-card-hover::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent, rgba(76, 81, 191, 0.6)); /* Slightly darker gradient */
            transform: translateY(100%);
            transition: transform var(--transition-speed) ease-in-out;
            z-index: -1;
        }
        .slide-card-hover:hover::before {
            transform: translateY(0);
        }

        /* --- Filter Buttons --- */
        .filter-btn {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            color: var(--dark-gray);
            transition: all var(--transition-speed) ease-in-out;
            box-shadow: var(--shadow-sm);
        }
        .filter-btn:hover {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .filter-btn.bg-indigo-600 { /* Active state */
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
            box-shadow: var(--shadow-md);
            transform: translateY(-2px); /* Lift even if already active */
        }

        /* --- Modal Enhancements --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.9);
            animation: fadeIn 0.3s forwards;
            backdrop-filter: blur(5px); /* Subtle blur behind modal */
            -webkit-backdrop-filter: blur(5px);
            display: flex; /* Changed to flex for better centering */
            align-items: center;
            justify-content: center;
        }
        .modal-content-wrapper { /* New wrapper for content and title */
            position: relative;
            max-width: 90%;
            max-height: 90%;
            background-color: rgba(0,0,0,0.8); /* Darker background for modal content area */
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .modal-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 15px;
            text-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }
        .modal-content { /* Applies to img and video inside modal */
            display: block;
            max-width: 100%;
            max-height: calc(90vh - 100px); /* Account for title and close button */
            object-fit: contain;
            border-radius: 8px;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 25px;
            color: #f1f1f1;
            font-size: 48px; /* Larger close button */
            font-weight: bold;
            transition: var(--transition-speed);
            cursor: pointer;
            text-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }
        .close:hover, .close:focus {
            color: var(--secondary-purple); /* Gold on hover */
        }
        
        /* --- Infrastructure Highlights Card Improvements (existing) --- */
        .infra-card {
            background-color: var(--card-bg);
            border-radius: 0.75rem;
            box-shadow: var(--shadow-md);
            padding: 2.5rem;
            text-align: center;
            transition: all var(--transition-speed) ease-in-out;
        }
        .infra-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        .infra-icon-bg {
            width: 72px;
            height: 72px;
            background-color: var(--primary-blue); /* Changed icon background color */
            border-radius: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            box-shadow: var(--shadow-sm);
        }
        .infra-icon-bg svg {
            color: white;
            width: 36px;
            height: 36px;
        }
        .infra-count {
            font-size: 2.75rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }
        .infra-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--medium-gray);
        }

        /* Lucide icon styling for dynamic SVGs */
        /* Ensure dynamic SVG icons inherit base styles */
        .filter-btn svg, .infra-icon-bg svg {
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none; /* Ensure fill is none unless explicitly set */
        }
        .filter-btn.bg-indigo-600 svg { /* Active filter button icon */
            color: white;
        }

        @media (max-width: 768px) {
            .section-padding {
                padding: 3rem 0.5rem;
            }
            .container-padding {
                padding: 0 1rem;
            }
            .text-4xl { font-size: 2.5rem; }
            .lg\:text-5xl { font-size: 3rem; }
            .text-xl { font-size: 1.125rem; }
            .lg\:text-2xl { font-size: 1.5rem; }
            .grid-cols-2 { grid-template-columns: 1fr; }
            .lg\:grid-cols-3 { grid-template-columns: 1fr; }
            .xl\:grid-cols-4 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<section id="gallery" class="section-padding">
    <div class="container-padding">
        <div class="text-center mb-16">
            <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary">
                <?php echo htmlspecialchars($settings['gallery_section_title']); ?>
            </h2>
            <p class="text-xl max-w-3xl mx-auto">
                <?php echo nl2br(htmlspecialchars($settings['gallery_section_description'])); ?>
            </p>
        </div>

        <div class="flex flex-wrap justify-center gap-4 mb-12" id="filter-buttons">
            <?php 
            // Default first category to be active.
            $firstCategoryActive = true; 
            foreach ($categories_display as $category): ?>
                <button 
                    class="filter-btn flex items-center gap-2 py-2 px-4 rounded-full border-2 
                    text-gray-700 border-gray-300
                    hover:bg-indigo-600 hover:text-white transition-all duration-300 card-hover
                    <?php if ($firstCategoryActive) { echo 'bg-indigo-600 text-white border-indigo-600'; $firstCategoryActive = false; } ?>"
                    data-category="<?php echo htmlspecialchars($category['id'] ?? ''); ?>"
                >
                    <?php echo $category['icon'] ?? ''; ?>
                    <span class="font-medium"><?php echo htmlspecialchars($category['name'] ?? ''); ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-12" id="gallery-grid">
            <?php foreach ($gallery_items_display as $item): ?>
                <div class="bg-white rounded-lg group overflow-hidden slide-card-hover gallery-item" 
                     data-category="<?php echo htmlspecialchars($item['category'] ?? ''); ?>" 
                     data-src="<?php echo htmlspecialchars($item['src_url'] ?? ''); ?>" 
                     data-type="<?php echo htmlspecialchars($item['type'] ?? ''); ?>"
                     data-title="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                    <div class="relative w-full aspect-square">
                        <?php if (empty($item['src_url'])): ?>
                            <div class="absolute inset-0 <?php echo generatePlaceholderImageClass($item); ?> flex items-center justify-center text-white text-center p-4">
                                <?php if (($item['type'] ?? '') === "video"): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-2"><path d="m22 8-6 4 6 4V8Z"/><path d="M14 12H2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v8z"/></svg>
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-2"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                                <?php endif; ?>
                                <p class="font-medium text-sm mt-2"><?php echo htmlspecialchars($item['title'] ?? 'No Media'); ?></p>
                            </div>
                        <?php else: ?>
                            <?php if (($item['type'] ?? '') === "video"): ?>
                                <video src="<?php echo htmlspecialchars($item['src_url']); ?>" class="absolute inset-0 w-full h-full object-cover" muted loop preload="metadata"></video>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($item['src_url']); ?>" alt="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" class="absolute inset-0 w-full h-full object-cover">
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                            <button class="hero-button-outline view-media-btn" data-src="<?php echo htmlspecialchars($item['src_url'] ?? ''); ?>" data-type="<?php echo htmlspecialchars($item['type'] ?? ''); ?>" data-title="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                                <?php echo (($item['type'] ?? '') === "video" ? "Play Video" : "View Image"); ?>
                            </button>
                        </div>
                        <span class="absolute top-3 right-3 bg-white/90 text-gray-700 px-3 py-1 rounded-full text-xs font-semibold">
                            <?php echo htmlspecialchars(($item['type'] ?? '') === "video" ? "Video" : "Photo"); ?>
                        </span>
                    </div>
                    <div class="p-4 bg-white">
                        <h3 class="font-semibold text-base mb-1 text-indigo-700"><?php echo htmlspecialchars($item['title'] ?? ''); ?></h3>
                        <p class="text-sm text-gray-500"><?php echo nl2br(htmlspecialchars($item['description'] ?? '')); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-12">
            <a href="<?php echo htmlspecialchars($settings['view_more_button_url']); ?>" class="hero-button">
                <?php echo htmlspecialchars($settings['view_more_button_text']); ?>
            </a>
        </div>

        <div class="mt-20">
            <h3 class="text-3xl font-bold text-center mb-12">
                <?php echo htmlspecialchars($settings['infra_highlights_title']); ?>
            </h3>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach ($infra_highlights_display as $facility): ?>
                    <div class="infra-card card-hover">
                        <div class="infra-icon-bg">
                            <?php echo $facility['icon'] ?? ''; ?>
                        </div>
                        <div class="infra-count"><?php echo htmlspecialchars($facility['count'] ?? ''); ?></div>
                        <h4 class="infra-title"><?php echo htmlspecialchars($facility['title'] ?? ''); ?></h4>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<div id="mediaModal" class="modal">
    <div class="modal-content-wrapper">
        <span class="close" id="closeModalBtn">&times;</span>
        <h3 class="modal-title" id="modalMediaTitle"></h3>
        <img class="modal-content" id="modalImage" style="display: none;">
        <video class="modal-content" id="modalVideo" controls style="display: none;"></video>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterButtons = document.querySelectorAll('.filter-btn');
        const galleryGrid = document.getElementById('gallery-grid');
        const galleryItems = galleryGrid.querySelectorAll('.gallery-item');
        const modal = document.getElementById('mediaModal');
        const modalImage = document.getElementById('modalImage');
        const modalVideo = document.getElementById('modalVideo');
        const modalMediaTitle = document.getElementById('modalMediaTitle');
        const closeModalBtn = document.getElementById('closeModalBtn');

        // Function to filter gallery items with smooth transition
        function filterGallery(category) {
            galleryItems.forEach(item => {
                const itemCategory = item.getAttribute('data-category');
                if (category === 'all' || itemCategory === category) {
                    item.classList.remove('hide'); // Show with transition
                    item.style.display = 'block'; // Make it visible for layout
                } else {
                    item.classList.add('hide'); // Hide with transition
                    // After transition, set display to none to remove from layout flow
                    item.addEventListener('transitionend', function handler() {
                        if (item.classList.contains('hide')) {
                            item.style.display = 'none';
                        }
                        item.removeEventListener('transitionend', handler); // Remove listener
                    }, { once: true });
                }
            });
        }

        // Initial filtering on page load: activate the 'all' button if it exists, otherwise the first.
        let initialCategory = 'all';
        const allButton = document.querySelector('.filter-btn[data-category="all"]');
        if (allButton) {
            allButton.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
        } else if (filterButtons.length > 0) {
            filterButtons[0].classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
            initialCategory = filterButtons[0].getAttribute('data-category');
        }
        filterGallery(initialCategory);


        // Add click event listeners to filter buttons
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                filterButtons.forEach(btn => btn.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600'));
                this.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
                
                const category = this.getAttribute('data-category');
                filterGallery(category);
            });
        });

        // Event delegation for view media buttons (gallery items)
        galleryGrid.addEventListener('click', function(event) {
            const targetButton = event.target.closest('.view-media-btn');
            if (targetButton) {
                event.stopPropagation();
                
                const mediaSrc = targetButton.getAttribute('data-src');
                const mediaType = targetButton.getAttribute('data-type');
                const mediaTitle = targetButton.getAttribute('data-title');
                
                modal.style.display = 'flex'; // Show modal
                modalImage.style.display = 'none';
                modalVideo.style.display = 'none';
                modalVideo.pause();
                modalVideo.currentTime = 0;
                modalMediaTitle.textContent = mediaTitle; // Set modal title

                if (mediaType === 'video') {
                    modalVideo.src = mediaSrc;
                    modalVideo.style.display = 'block';
                    modalVideo.load();
                    modalVideo.play();
                } else {
                    modalImage.src = mediaSrc;
                    modalImage.style.display = 'block';
                }
            }
        });

        // Close modal logic
        function closeMediaModal() {
            modal.style.display = 'none';
            modalVideo.pause();
            modalVideo.src = ""; // Clear video src to stop buffering
            modalMediaTitle.textContent = ""; // Clear modal title
        }
        closeModalBtn.addEventListener('click', closeMediaModal);
        
        // Close modal when clicking outside of the content wrapper
        modal.addEventListener('click', (event) => {
            if (event.target === modal) { // Only if clicked directly on the modal background
                closeMediaModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.style.display === 'flex') {
                closeMediaModal();
            }
        });

        // Initialize Lucide icons
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    });
</script>

</body>
</html>