<?php
// This is a self-contained PHP file for the Gallery section.
// It includes all HTML, CSS, and inline SVG icons to be rendered in a browser.

// Define a helper function to convert Tailwind color classes to hex values for inline CSS gradients.
// This is necessary because Tailwind JIT won't always pick up dynamically generated classes in inline styles.
function get_tailwind_hex_color($class) {
    $colors = [
        'blue-50' => '#EFF6FF',
        'blue-700' => '#1D4ED8',
        'blue-800' => '#1E40AF', // Deep blue
        'blue-900' => '#1E3A8A',
        'emerald-600' => '#059669',
        'teal-700' => '#0F766E',
        'orange-400' => '#FB923C',
        'amber-400' => '#FBBF24',
        'amber-500' => '#F59E0B',
        'amber-600' => '#D97706',
        'amber-700' => '#B45309', // Elegant gold
        'red-600' => '#DC2626',
        'red-800' => '#991B1B',
        'purple-300' => '#D8B4FE', // Consistent hover border
        'purple-500' => '#A855F7',
        'purple-600' => '#9333EA',
        'purple-700' => '#7E22CE',
        'purple-800' => '#6B21A8',
        'violet-600' => '#7C3AED',
        'indigo-50' => '#EEF2FF', // Soft indigo for hover
        'indigo-500' => '#6366F1',
        'indigo-600' => '#4F46E5',
        'indigo-800' => '#3730A3',
        'gray-300' => '#D1D5DB', // Standard gray for borders etc.
        'gray-700' => '#4B5563', // Standard gray for text
        'white' => '#FFFFFF',
        'fcfaff' => '#FCFAFF' // Very subtle light purple tint on hover
    ];
    // Extract the color name and shade from the class
    preg_match('/(from|to|bg|text)-([a-z]+)-?(\d+)?/', $class, $matches);
    $color_name = isset($matches[2]) ? $matches[2] : '';
    $shade = isset($matches[3]) ? $matches[3] : '';

    $key = $color_name . ($shade ? '-' . $shade : '');
    return $colors[$key] ?? '#CCCCCC'; // Default to a neutral gray if not found
}

// Include header if available (adjust path as needed)
require_once __DIR__ . '/../header.php';

// Categories for filtering
$categories = [
    ["id" => "all", "name" => "All", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="12" y1="3" x2="12" y2="21"/><line x1="3" y1="12" x2="21" y2="12"/></svg>'],
    ["id" => "infrastructure", "name" => "Infrastructure", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'],
    ["id" => "activities", "name" => "Activities", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"/><rect width="18" height="18" x="3" y="2" rx="2" ry="2"/><circle cx="12" cy="7" r="4"/><line x1="12" x2="12" y1="2" y2="2"/></svg>'],
    ["id" => "academics", "name" => "Academics", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><polyline points="10 2 10 22"/></svg>'],
    ["id" => "sports", "name" => "Sports", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 17.64L12 12l6.36-6.36A9 9 0 0 1 21 12h-3"/><path d="M11.95 21l-.03-2.92a2 2 0 0 1-2-2v-1.12a2 2 0 0 1 2-2h1.1a2 2 0 0 1 2 2V19l.03 2"/><path d="M3.64 17.64A9 9 0 0 1 3 12h3"/><path d="M12 3v9"/></svg>'],
    ["id" => "events", "name" => "Events", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 8A6 6 0 0 0 10 8"/><path d="M2 8A6 6 0 0 1 14 8"/><path d="M18 10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2V10z"/><rect x="4" y="10" width="16" height="10" rx="2" ry="2"/></svg>']
];

// Gallery items with diverse placeholder images
$galleryItems = [
    ["id" => 1, "title" => "Modern Science Laboratory", "category" => "infrastructure", "type" => "image", "description" => "State-of-the-art science lab with latest equipment.", "src" => "https://placehold.co/600x400/1E40AF/ffffff?text=Science+Lab"],
    ["id" => 2, "title" => "Library Reading Hall", "category" => "infrastructure", "type" => "image", "description" => "Spacious library with an extensive collection of books.", "src" => "https://placehold.co/600x400/B45309/ffffff?text=Library+Reading"],
    ["id" => 3, "title" => "Annual Sports Day", "category" => "sports", "type" => "image", "description" => "Students participating enthusiastically in various athletic events.", "src" => "https://placehold.co/600x400/991B1B/ffffff?text=Sports+Day+Action"],
    ["id" => 4, "title" => "Interactive Classroom Session", "category" => "academics", "type" => "image", "description" => "Engaged students during an interactive smart classroom session.", "src" => "https://placehold.co/600x400/1D4ED8/ffffff?text=Classroom+Learning"],
    ["id" => 5, "title" => "Cultural Performance - Annual Day", "category" => "events", "type" => "video", "description" => "Highlights from our dazzling annual day cultural program.", "src" => "https://www.w3schools.com/html/mov_bbb.mp4"],
    ["id" => 6, "title" => "Computer Lab & Robotics", "category" => "infrastructure", "type" => "image", "description" => "Advanced computer lab facilitating coding and robotics education.", "src" => "https://placehold.co/600x400/0F766E/ffffff?text=Computer+Robotics"],
    ["id" => 7, "title" => "Art & Craft Workshop", "category" => "activities", "type" => "image", "description" => "Students expressing their creativity during an art & craft workshop.", "src" => "https://placehold.co/600x400/F59E0B/ffffff?text=Art+Workshop"],
    ["id" => 8, "title" => "Basketball Championship", "category" => "sports", "type" => "image", "description" => "Intense moments from the inter-house basketball championship.", "src" => "https://placehold.co/600x400/6B21A8/ffffff?text=Basketball+Match"],
    ["id" => 9, "title" => "Science Exhibition Winners", "category" => "academics", "type" => "image", "description" => "Award-winning projects from our annual science exhibition.", "src" => "https://placehold.co/600x400/059669/ffffff?text=Science+Exhibition"],
    ["id" => 10, "title" => "Music & Dance Recital", "category" => "events", "type" => "video", "description" => "A captivating music and dance performance by our talented students.", "src" => "https://www.w3schools.com/html/mov_bbb.mp4"],
    ["id" => 11, "title" => "State-of-the-Art Auditorium", "category" => "infrastructure", "type" => "image", "description" => "Our spacious auditorium, venue for grand school events.", "src" => "https://placehold.co/600x400/D97706/ffffff?text=Auditorium+View"],
    ["id" => 12, "title" => "Debate Club Session", "category" => "activities", "type" => "image", "description" => "Students sharpening their public speaking and critical thinking in debate club.", "src" => "https://placehold.co/600x400/1E40AF/ffffff?text=Debate+Club"],
    ["id" => 13, "title" => "Football Practice", "category" => "sports", "type" => "image", "description" => "Team practice on our well-maintained football field.", "src" => "https://placehold.co/600x400/DC2626/ffffff?text=Football+Practice"],
    ["id" => 14, "title" => "Annual Farewell", "category" => "events", "type" => "image", "description" => "Emotional moments during the annual farewell ceremony.", "src" => "https://placehold.co/600x400/B45309/ffffff?text=Farewell+Event"],
    ["id" => 15, "title" => "Junior Playground", "category" => "infrastructure", "type" => "image", "description" => "Safe and engaging play area for our younger students.", "src" => "https://placehold.co/600x400/059669/ffffff?text=Playground"],
    ["id" => 16, "title" => "Maths Olympiad", "category" => "academics", "type" => "image", "description" => "Students focused during the inter-school Mathematics Olympiad.", "src" => "https://placehold.co/600x400/6B21A8/ffffff?text=Maths+Olympiad"],
];

// Infrastructure highlight data - Updated colors
$facilities = [
    ["title" => "Smart Classrooms", "count" => "40+", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3H6a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h4M14 3h4a2 2 0 0 1 2 2v14c0 1.1-.9 2-2 2h-4M8 21v-4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4"/></svg>', 'color_from' => 'blue-700', 'color_to' => 'indigo-800'],
    ["title" => "Science Labs", "count" => "6", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>', 'color_from' => 'emerald-600', 'color_to' => 'teal-700'],
    ["title" => "Sports Facilities", "count" => "15+", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2v10"/><path d="M12 12l4 4"/><path d="M12 12l-4 4"/></svg>', 'color_from' => 'amber-500', 'color_to' => 'amber-700'],
    ["title" => "Activity Rooms", "count" => "10+", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"/><rect width="18" height="18" x="3" y="2" rx="2" ry="2"/><circle cx="12" cy="7" r="4"/><line x1="12" x2="12" y1="2" y2="2"/></svg>', 'color_from' => 'purple-600', 'color_to' => 'violet-600']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Gallery - Mednova School</title>
    <meta name="description" content="Explore Mednova School's vibrant gallery showcasing our infrastructure, academic life, sports, activities, and special events through captivating photos and videos.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: white; /* Consistent white background */
            color: <?php echo get_tailwind_hex_color('gray-700'); ?>;
            line-height: 1.6;
            scroll-behavior: smooth;
        }
        /* Professional typography with serif headings */
        h1, h2, h3, h4 {
            font-family: 'Playfair Display', serif;
        }
        .section-padding {
            padding: 6rem 0; /* Consistent with other pages */
        }
        .container-padding {
            max-width: 1300px; /* Consistent with other pages */
            margin-left: auto;
            margin-right: auto;
            padding: 0 1.5rem;
        }
        @media (min-width: 640px) {
            .container-padding {
                padding: 0 2.5rem;
            }
        }
        @media (min-width: 1024px) {
            .container-padding {
                padding: 0 5rem;
            }
        }
        .text-gradient-primary {
            /* Deep Blue to Elegant Gold */
            background-image: linear-gradient(90deg, <?php echo get_tailwind_hex_color('blue-800'); ?>, <?php echo get_tailwind_hex_color('amber-700'); ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800; /* Make gradient text bolder */
        }

        /* Hero Section */
        .hero-section {
            background-image: linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.65)), url('https://placehold.co/1920x600/1E40AF/FBBF24?text=Mednova+School+Gallery'); /* Updated image with deep blue/gold feel */
            background-size: cover;
            background-position: center;
            position: relative;
            color: white;
            padding: 8rem 0;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.5);
            border-radius: 0 0 3rem 3rem;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }
        .hero-section h1 {
            font-size: 3.5rem;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            font-weight: 900;
        }
        .hero-section p {
            font-size: 1.5rem;
            line-height: 1.5;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Animations */
        .animate-fade-in {
            animation: fadeIn 1.2s ease-out forwards;
            opacity: 0;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Divider */
        .divider {
            height: 2px;
            background-image: linear-gradient(90deg, transparent, <?php echo get_tailwind_hex_color('gray-300'); ?>, transparent);
            margin: 4rem auto;
            max-width: 80%;
        }

        /* --- Global Card Hover Effects (Consistent with About/Academics) --- */
        .card-hover {
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            transform-origin: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            border: 1px solid <?php echo get_tailwind_hex_color('gray-300'); ?>;
        }
        .card-hover:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 18px 35px rgba(0, 0, 0, 0.15);
            border-color: <?php echo get_tailwind_hex_color('purple-300'); ?>; /* Light purple border on hover (purple-300) */
            background-color: <?php echo get_tailwind_hex_color('fcfaff'); ?>; /* Very subtle light purple tint on hover */
        }
        /* Specific hover effects for icon backgrounds within cards (if any) */
        .card-hover .icon-wrapper {
             transition: all 0.3s ease;
        }
        .card-hover:hover .icon-wrapper {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 6px 15px rgba(168, 85, 247, 0.6); /* purple-500 shadow */
        }

        /* --- Standardized Icon Wrapper Styling (Consistent) --- */
        .icon-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.4); /* indigo-500 shadow */
        }
        .icon-wrapper-lg { /* For larger icons like in Infrastructure Highlights */
            width: 4.5rem; /* 72px */
            height: 4.5rem; /* 72px */
            border-radius: 0.75rem; /* rounded-xl */
        }
        .icon-wrapper-md { /* For filter buttons */
            width: 2.5rem; /* 40px */
            height: 2.5rem; /* 40px */
            border-radius: 0.5rem; /* rounded-md */
        }
        .icon-wrapper svg {
            color: currentColor;
            width: 1.25rem; /* w-5 */
            height: 1.25rem; /* h-5 */
        }
        .icon-wrapper-lg svg {
            width: 2rem; /* w-8 */
            height: 2rem; /* h-8 */
        }


        /* Filter Button Styling */
        .filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 9999px; /* rounded-full */
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid <?php echo get_tailwind_hex_color('gray-300'); ?>;
            color: <?php echo get_tailwind_hex_color('gray-700'); ?>;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .filter-btn:hover:not(.active) {
            background-color: <?php echo get_tailwind_hex_color('indigo-50'); ?>; /* Soft indigo-50 */
            border-color: <?php echo get_tailwind_hex_color('indigo-500'); ?>; /* indigo-500 */
            color: <?php echo get_tailwind_hex_color('indigo-800'); ?>; /* Deep blue */
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .filter-btn.active {
            background-image: linear-gradient(45deg, <?php echo get_tailwind_hex_color('blue-800'); ?>, <?php echo get_tailwind_hex_color('amber-700'); ?>); /* Deep blue to Elegant gold */
            color: white;
            border-color: transparent;
            box-shadow: 0 5px 15px rgba(168, 85, 247, 0.4); /* purple-500 shadow */
            transform: translateY(-2px);
        }
        .filter-btn.active svg {
            color: white; /* Ensure active button icons are white */
        }
        .filter-btn svg {
            transition: color 0.3s ease; /* Transition icon color too */
        }

        /* Gallery Item Card */
        .gallery-card {
            background-color: white;
            border-radius: 1rem; /* rounded-xl */
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.08); /* Stronger shadow for individual cards */
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid <?php echo get_tailwind_hex_color('gray-300'); ?>;
        }
        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 25px rgba(0, 0, 0, 0.15);
            border-color: <?php echo get_tailwind_hex_color('amber-500'); ?>; /* Elegant gold border on hover */
        }
        .gallery-item-image-wrapper {
            position: relative;
            overflow: hidden;
            padding-bottom: 75%; /* 4:3 Aspect Ratio (3/4 * 100%) or square could be 100% */
            height: 0;
        }
        .gallery-item-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .gallery-card:hover .gallery-item-image {
            transform: scale(1.05);
        }
        .media-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-start;
            padding: 1rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }
        .gallery-card:hover .media-overlay {
            opacity: 1;
        }
        .media-overlay-text {
            color: white;
            font-weight: 600;
            font-size: 1.125rem; /* text-lg */
            margin-bottom: 0.25rem;
        }
        .media-overlay-description {
            color: <?php echo get_tailwind_hex_color('gray-300'); ?>;
            font-size: 0.875rem; /* text-sm */
        }
        .media-overlay-play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 9999px;
            padding: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .gallery-card:hover .media-overlay-play-button {
            opacity: 1;
        }
        .media-overlay-play-button svg {
            color: <?php echo get_tailwind_hex_color('indigo-500'); ?>; /* indigo-500 */
            width: 2rem;
            height: 2rem;
        }


        /* Modal styles */
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
            animation: fadeInModal 0.3s;
        }
        .modal-content-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            animation: zoomIn 0.3s;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
        }
        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes zoomIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        /* Gallery item transitions for filtering */
        .gallery-item {
            display: block;
            opacity: 1;
            transform: scale(1);
            transition: opacity 0.5s ease-in-out, transform 0.5s ease-in-out, height 0.5s ease-in-out, margin 0.5s ease-in-out, padding 0.5s ease-in-out, visibility 0.5s;
            will-change: transform, opacity, height, margin, padding; /* Optimize for smooth animation */
        }
        .gallery-item.hide {
            opacity: 0;
            transform: scale(0.95);
            height: 0;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            visibility: hidden;
            pointer-events: none; /* Disable interaction when hidden */
        }

        /* --- Infrastructure Highlights Card (Enhanced) --- */
        .infra-card-wrapper {
            background-color: white;
            border-radius: 1.5rem; /* Consistent rounded-2xl */
            padding: 2.5rem; /* Increased padding */
            text-align: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05); /* Softer, larger shadow */
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid <?php echo get_tailwind_hex_color('gray-300'); ?>;
        }
        .infra-card-wrapper:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 18px 35px rgba(0, 0, 0, 0.15);
            border-color: <?php echo get_tailwind_hex_color('purple-300'); ?>; /* purple-300 */
            background-color: <?php echo get_tailwind_hex_color('fcfaff'); ?>;
        }
        .infra-icon-bg { /* Using icon-wrapper for consistency */
            margin: 0 auto 1.5rem auto;
        }
        .infra-count {
            font-size: 2.75rem; /* Larger count text */
            font-weight: 700;
            color: <?php echo get_tailwind_hex_color('blue-800'); ?>; /* Darker indigo for emphasis */
            margin-bottom: 0.5rem;
        }
        .infra-title {
            font-size: 1.125rem; /* Slightly larger title */
            font-weight: 600;
            color: <?php echo get_tailwind_hex_color('gray-700'); ?>; /* Slightly darker gray for title */
        }

        /* CTA Button Styling (consistent) */
        .cta-button {
            background-image: linear-gradient(45deg, <?php echo get_tailwind_hex_color('blue-800'); ?>, <?php echo get_tailwind_hex_color('amber-700'); ?>); /* Deep blue to Elegant gold */
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(168, 85, 247, 0.4); /* purple-500 shadow */
            border: none;
        }
        .cta-button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 20px rgba(168, 85, 247, 0.6); /* purple-500 shadow */
            opacity: 0.9;
        }
        .cta-button-outline {
            background-color: transparent;
            border: 2px solid <?php echo get_tailwind_hex_color('blue-800'); ?>; /* Deep blue */
            color: <?php echo get_tailwind_hex_color('blue-800'); ?>; /* Deep blue */
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.2); /* indigo-500 shadow */
        }
        .cta-button-outline:hover {
            background-color: <?php echo get_tailwind_hex_color('blue-50'); ?>; /* blue-50 */
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3); /* indigo-500 shadow */
        }
    </style>
</head>
<body>

    <!-- Hero Section for Gallery -->
    <section class="hero-section">
        <div class="container-padding animate-fade-in" style="animation-delay: 0.2s;">
            <h1>Our School in Action: The Mednova Gallery</h1>
            <p>Dive into the vibrant world of Mednova School. Our gallery captures the essence of student life, academic pursuits, sporting achievements, and memorable celebrations.</p>
        </div>
    </section>

    <section id="gallery-main" class="section-padding">
        <div class="container-padding">
            <div class="text-center mb-16 animate-fade-in" style="animation-delay: 0.4s;">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary">Moments that Inspire</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    From the joyous laughter in our playgrounds to focused learning in smart classrooms,
                    each moment at Mednova School is a testament to our commitment to holistic development.
                </p>
            </div>

            <div class="flex flex-wrap justify-center gap-4 mb-12 animate-fade-in" style="animation-delay: 0.6s;" id="filter-buttons">
                <?php foreach ($categories as $category): ?>
                    <button
                        class="filter-btn flex items-center gap-2"
                        data-category="<?= $category['id'] ?>"
                    >
                        <?= $category['icon'] ?>
                        <?= $category['name'] ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-12" id="gallery-grid">
                <?php foreach ($galleryItems as $index => $item): ?>
                    <div class="gallery-card group gallery-item animate-fade-in" data-category="<?= $item['category'] ?>" style="animation-delay: <?= 0.8 + ($index * 0.1); ?>s;">
                        <div class="gallery-item-image-wrapper">
                            <img class="gallery-item-image" src="<?= $item['src'] ?>" alt="<?= $item['title'] ?>"
                                onerror="this.onerror=null;this.src='https://placehold.co/600x400/4b5563/d1d5db?text=Image+Unavailable';">
                            <div class="media-overlay" data-src="<?= $item['src'] ?>" data-type="<?= $item['type'] ?>" data-title="<?= htmlspecialchars($item['title']) ?>" data-description="<?= htmlspecialchars($item['description']) ?>">
                                <div class="media-overlay-text"><?= $item['title'] ?></div>
                                <div class="media-overlay-description"><?= $item['description'] ?></div>
                                <div class="media-overlay-play-button">
                                    <?php if ($item['type'] === "video"): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="absolute top-3 right-3 bg-white/90 text-gray-700 px-3 py-1 rounded-full text-xs font-semibold shadow">
                                <?= $item['type'] === "video" ? "Video" : "Photo" ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-12 animate-fade-in" style="animation-delay: 1.8s;">
                <a href="#" class="cta-button inline-flex items-center justify-center text-white font-bold py-3 px-8 rounded-full text-lg shadow-lg">
                    Load More Moments
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
        </div>
    </section>

    <div class="divider animate-fade-in" style="animation-delay: 2s;"></div>

    <section id="infrastructure-highlights" class="section-padding pt-0">
        <div class="container-padding">
            <div class="text-center mb-16 animate-fade-in" style="animation-delay: 2.2s;">
                <h3 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary">Our World-Class Facilities at a Glance</h3>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Mednova School is equipped with modern infrastructure designed to provide an optimal learning and growth environment for every student.
                </p>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach ($facilities as $index => $facility):
                    $from_hex = get_tailwind_hex_color($facility['color_from']);
                    $to_hex = get_tailwind_hex_color($facility['color_to']);
                ?>
                    <div class="infra-card-wrapper animate-fade-in" style="animation-delay: <?= 2.4 + ($index * 0.1); ?>s;">
                        <div class="icon-wrapper icon-wrapper-lg infra-icon-bg" style="background-image: linear-gradient(to bottom right, <?php echo $from_hex; ?>, <?php echo $to_hex; ?>); color: white;">
                            <?= $facility['icon'] ?>
                        </div>
                        <div class="infra-count"><?= $facility['count'] ?></div>
                        <h4 class="infra-title"><?= $facility['title'] ?></h4>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <div class="section-padding pt-0 text-center animate-fade-in" style="animation-delay: 3s;">
        <div class="container-padding">
            <h3 class="text-3xl lg:text-4xl font-bold mb-6 text-gray-900">Want to See More? Plan a Visit!</h3>
            <p class="text-xl text-gray-600 mb-10 max-w-3xl mx-auto leading-relaxed">
                Experience the Mednova difference firsthand. Schedule a campus tour to explore our facilities and meet our dedicated staff.
            </p>
            <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6">
                <a href="#visit-us" class="cta-button inline-flex items-center justify-center text-white font-bold py-3 px-10 rounded-full text-lg shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Plan Your Visit
                </a>
                <a href="#contact" class="cta-button-outline inline-flex items-center justify-center font-bold py-3 px-10 rounded-full text-lg shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    Contact Us
                </a>
            </div>
        </div>
    </div>


<div id="mediaModal" class="modal">
    <span class="close">&times;</span>
    <div class="modal-content-container">
        <img class="modal-content" id="modalImage" style="display: none;">
        <video class="modal-content" id="modalVideo" controls preload="metadata" style="display: none;"></video>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterButtons = document.querySelectorAll('.filter-btn');
        const galleryGrid = document.getElementById('gallery-grid');
        const galleryItems = document.querySelectorAll('.gallery-item');
        const modal = document.getElementById('mediaModal');
        const modalImage = document.getElementById('modalImage');
        const modalVideo = document.getElementById('modalVideo');
        const closeModal = document.querySelector('.close');

        // Function to filter gallery items with animation
        function filterGallery(category) {
            let showDelay = 0; // Delay for items being shown
            let hideDelay = 0; // Delay for items being hidden (optional, can be combined)

            // First pass: Mark items to hide/show, apply hide animations
            galleryItems.forEach(item => {
                const itemCategory = item.getAttribute('data-category');
                if (category === 'all' || itemCategory === category) {
                    item.classList.remove('hide');
                    item.style.display = 'block'; // Ensure it's display block for transition
                } else {
                    item.classList.add('hide');
                    item.style.animationDelay = ''; // Clear explicit delay for hiding
                }
            });

            // Second pass: Apply show animations and manage visibility after hide
            galleryItems.forEach(item => {
                const itemCategory = item.getAttribute('data-category');
                if (category === 'all' || itemCategory === category) {
                    // Items to show: stagger animation
                    item.style.animationDelay = `${showDelay}s`;
                    showDelay += 0.05; // Stagger effect
                } else {
                    // Items to hide: hide them completely after their transition
                    setTimeout(() => {
                        if (item.classList.contains('hide')) { // Double check it's still intended to be hidden
                            item.style.display = 'none';
                        }
                    }, 500); // Matches CSS transition duration
                }
            });
        }

        // Initial filtering on page load to show 'All' and set active button
        filterGallery('all');
        document.querySelector('.filter-btn[data-category="all"]').classList.add('active');


        // Add click event listeners to filter buttons
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Update active button state
                filterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                // Get the category and filter the items
                const category = this.getAttribute('data-category');
                filterGallery(category);
            });
        });

        // Add click event listeners to media overlays
        galleryGrid.addEventListener('click', function(event) {
            const mediaOverlay = event.target.closest('.media-overlay');
            if (mediaOverlay) {
                const mediaSrc = mediaOverlay.getAttribute('data-src');
                const mediaType = mediaOverlay.getAttribute('data-type');
                const mediaTitle = mediaOverlay.getAttribute('data-title');
                const mediaDescription = mediaOverlay.getAttribute('data-description');

                modal.style.display = 'block';
                modalImage.style.display = 'none';
                modalVideo.style.display = 'none';

                if (mediaType === 'video') {
                    modalVideo.src = mediaSrc;
                    modalVideo.style.display = 'block';
                    modalVideo.load(); // Load video metadata
                    modalVideo.play();
                } else {
                    modalImage.src = mediaSrc;
                    modalImage.style.display = 'block';
                }
                // Optionally, add title/description to modal if you enhance it
                // document.getElementById('modalTitle').textContent = mediaTitle;
                // document.getElementById('modalDescription').textContent = mediaDescription;
            }
        });

        // Close modal when close button is clicked
        closeModal.addEventListener('click', () => {
            modal.style.display = 'none';
            modalVideo.pause(); // Pause video when modal closes
            modalVideo.currentTime = 0; // Reset video to start
        });

        // Close modal when clicking outside of the content
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
                modalVideo.pause();
                modalVideo.currentTime = 0;
            }
        });
    });
</script>

</body>
</html>

<?php
// Include footer if available (adjust path as needed)
require_once __DIR__ . '/../footer.php';
?>