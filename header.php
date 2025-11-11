<?php
// Define the webroot path here
// This is the base directory where your "new school" project resides relative to the document root
$webroot = '/'; 
// Ensure it ends with a slash if it's a directory, or omit if it's a specific file.
// In this case, we'll use it for directory-based links, so a trailing slash is appropriate.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Immersive School & Portfolio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        /* --- General Body & Scrollbar Styles --- */
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #000000;
            color: #e5e7eb;
        }

        /* Custom scrollbar for a modern look */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #111; }
        ::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #2563eb; }

        /* --- Header & Navigation Styles --- */
        .combined-header {
            position: fixed; /* Changed from sticky to fixed */
            top: 0;
            width: 100%;
            z-index: 50;
            background-color: transparent;
            transition: background-color 0.3s ease, backdrop-filter 0.3s ease, box-shadow 0.3s ease;
        }
        .combined-header.scrolled {
            background-color: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(16px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .top-bar {
            background-color: #4285F4;
            padding: 0.5rem 2rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95rem;
            color: #ffffff;
            line-height: 1.5;
            transition: max-height 0.3s ease, padding 0.3s ease, opacity 0.3s ease, visibility 0.3s ease;
            max-height: 100px; /* Initial max height to transition from */
            overflow: hidden;
            visibility: visible;
        }
        .top-bar.hidden {
            max-height: 0; /* Shrinks to hide */
            padding-top: 0;
            padding-bottom: 0;
            opacity: 0;
            visibility: hidden;
        }
        .top-bar-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            white-space: nowrap;
            margin: 0.25rem 0.5rem;
        }
        .top-bar a { color: inherit; text-decoration: none; transition: color 0.3s ease; }
        .top-bar a:hover { color: #f0f0f0; }
        .top-bar svg { width: 1.2em; height: 1.2em; stroke: currentColor; fill: none; stroke-width: 1.5; vertical-align: middle; }
        .navbar {
            padding: 0.75rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar .logo { text-shadow: 0 0 5px rgba(255, 255, 255, 0.5), 0 0 15px rgba(0, 255, 255, 0.4); }
        .navbar ul li a::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, #00c6ff, #0072ff);
            transform: scaleX(0);
            transform-origin: center;
            transition: transform 0.4s cubic-bezier(0.19, 1, 0.22, 1);
        }
        .navbar ul li a:hover { color: #fff; transform: translateY(-4px) scale(1.1); }
        .navbar ul li a:hover::after { transform: scaleX(1); }
        .menu-toggle.active .bar:nth-child(1) { transform: translateY(8px) rotate(45deg); }
        .menu-toggle.active .bar:nth-child(2) { opacity: 0; }
        .menu-toggle.active .bar:nth-child(3) { transform: translateY(-8px) rotate(-45deg); }
        
        /* Add padding to the body to prevent content from being hidden behind the fixed header */
        body { padding-top: 100px; } /* Adjust this value to the initial height of your header */

        /* --- Parallax-specific styles (retained) --- */
        .hero-parallax-container { height: 300vh; position: relative; perspective: 1000px; transform-style: preserve-3d; }
        .header { position: sticky; top: 0; left: 0; width: 100%; height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; z-index: 1; }
        .header-content { max-width: 70rem; padding: 1rem; }
        .header h1 { font-size: clamp(2rem, 10vw, 4.5rem); font-weight: 700; margin: 0 0 1rem 0; }
        .header p { font-size: clamp(1rem, 4vw, 1.25rem); max-width: 40rem; margin: 0 auto; color: #d1d5db; }
        .parallax-grid-container { position: sticky; top: 0; height: 100vh; width: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; will-change: transform, opacity; }
        .parallax-row { display: flex; gap: 1.25rem; margin-bottom: 1.25rem; will-change: transform; width: 100%; justify-content: center; }
        .row-reverse { flex-direction: row-reverse; }
        .product-card { width: 30rem; height: 24rem; position: relative; flex-shrink: 0; transition: transform 0.3s ease; }
        .product-card:hover { transform: translateY(-20px); }
        .product-card a { display: block; width: 100%; height: 100%; }
        .product-card a:hover { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .product-card img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: left top; }
        .product-card .overlay { position: absolute; inset: 0; width: 100%; height: 100%; background-color: #000; opacity: 0; transition: opacity 0.3s ease; pointer-events: none; }
        .product-card:hover .overlay { opacity: 0.8; }
        .product-card h2 { position: absolute; bottom: 1rem; left: 1rem; color: white; opacity: 0; transition: opacity 0.3s ease; }
        .product-card:hover h2 { opacity: 1; }
        .content-below { height: 14vh; background-color: #111; display: flex; align-items: center; justify-content: center; }
        
        /* --- Enhanced Footer Section (retained) --- */
        .site-footer { background-color: #0a0a0a; color: #a0aec0; padding: 5rem 2rem 2rem; position: relative; border-top: 1px solid rgba(255, 255, 255, 0.1); overflow: hidden; }
        .site-footer::before { content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 80%; height: 2px; background: linear-gradient(90deg, transparent, #00c6ff, #0072ff, transparent); filter: blur(4px); }
        .footer-container { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 3rem; }
        .footer-column { min-width: 200px; }
        .footer-column h4 { color: #ffffff; font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; position: relative; letter-spacing: 0.5px; }
        .footer-column h4::after { content: ''; position: absolute; left: 0; bottom: -8px; width: 35px; height: 2px; background: linear-gradient(90deg, #00c6ff, #0072ff); }
        .footer-column p { line-height: 1.7; font-size: 0.95rem; }
        .footer-links { list-style: none; padding: 0; margin: 0; }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: #a0aec0; text-decoration: none; transition: color 0.3s ease, padding-left 0.3s cubic-bezier(0.19, 1, 0.22, 1); }
        .footer-links a:hover { color: #00c6ff; padding-left: 8px; }
        .social-links { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .social-links a { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; color: #e5e7eb; background-color: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 50%; text-decoration: none; font-size: 1.2rem; transition: background-color 0.3s ease, transform 0.3s ease, color 0.3s ease, border-color 0.3s ease; }
        .social-links a:hover { background-color: #0072ff; color: #ffffff; border-color: #0072ff; transform: translateY(-5px) scale(1.1); box-shadow: 0 8px 20px rgba(0, 114, 255, 0.3); }
        .footer-bottom { text-align: center; margin-top: 4rem; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.9rem; color: #718096; }
        
        /* --- Enhanced Back to Top Button (retained) --- */
        .back-to-top { position: fixed; bottom: 25px; right: 25px; width: 50px; height: 50px; background: linear-gradient(45deg, #00c6ff, #0072ff); color: #fff; border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: bold; cursor: pointer; box-shadow: 0 5px 20px rgba(0, 150, 255, 0.4); z-index: 999; opacity: 0; transform: scale(0.8) translateY(20px); pointer-events: none; transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.19, 1, 0.22, 1); }
        .back-to-top.visible { opacity: 1; transform: scale(1) translateY(0); pointer-events: auto; }
        .back-to-top:hover { transform: scale(1.1); box-shadow: 0 8px 25px rgba(0, 150, 255, 0.6); }

        /* --- MOBILE MENU OVERRIDES (retained) --- */
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; gap: 0.5rem; padding: 0.75rem 1rem; text-align: center; }
            .top-bar-item { margin: 0.25rem 0; }
            .navbar ul {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background: rgba(17, 24, 39, 0.9);
                border: 1px solid rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(16px);
                border-radius: 0 0 20px 20px;
                padding: 1rem 0;
                transform: translateY(10px);
                opacity: 0;
                pointer-events: none;
                transition: transform 0.3s ease, opacity 0.3s ease;
            }
            .navbar ul.active {
                display: flex;
                transform: translateY(0);
                opacity: 1;
                pointer-events: auto;
            }
            .navbar ul li {
                margin: 0.5rem 0;
                text-align: center;
            }
            .navbar ul li a { padding: 1rem; }
            .menu-toggle {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                cursor: pointer;
                width: 30px;
                height: 20px;
            }
            .menu-toggle .bar {
                display: block;
                width: 100%;
                height: 3px;
                background-color: #e5e7eb;
                transition: all 0.3s ease-in-out;
            }
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-200">

    <header class="combined-header" id="combined-header">
        <div class="top-bar" id="top-bar">
            <div class="top-bar-left flex-wrap md:flex-nowrap">
                <a href="tel:+1-555-123-4567" class="top-bar-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.57 2.37.49 4.86-.06 7.23a.98.98 0 0 1-1.25.56l-2.07-.48a2 2 0 0 0-2.32 2.32L5 15l2 2 3-3a1 1 0 0 1 1.7.35l.54 2.16a2 2 0 0 0 2.32 2.32l.48 2.07c.28 1.2-.56 2.45-1.8 2.53-2.38.16-4.75-.08-7.1-.73A19.79 19.79 0 0 1 2 13.91V20c0 1.1.9 2 2 2h3a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"></path></svg>
                    <span>+1 (555) 123-4567</span>
                </a>
                <a href="mailto:info@codemaster.edu" class="top-bar-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <span>info@codemaster.edu</span>
                </a>
                <a href="#" class="top-bar-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <span>123 Education St, Learning City</span>
                </a>
            </div>
            <div class="top-bar-right top-bar-item mt-2 md:mt-0">
                <span>Admissions Open for 2024-25</span>
            </div>
        </div>
        
        <nav class="navbar max-w-7xl mx-auto" id="main-navbar">
            <a href="<?php echo $webroot; ?>" class="logo text-2xl font-black text-white transition-transform hover:scale-105">CodeCraft</a>
        
            <div class="menu-toggle md:hidden" id="mobile-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>

            <ul class="hidden md:flex flex-row space-x-10 p-0 m-0" id="nav-menu">
                <li><a href="<?php echo $webroot; ?>index.php" class="relative font-medium text-gray-200 transition-colors hover:text-white">Home</a></li>
                <li><a href="<?php echo $webroot; ?>user/about_mednova_school.php" class="relative font-medium text-gray-200 transition-colors hover:text-white">About</a></li>
                <li><a href="<?php echo $webroot; ?>user/adm.php" class="relative font-medium text-gray-200 transition-colors hover:text-white">Academics</a></li>
                <li><a href="<?php echo $webroot; ?>user/admissions.php" class="relative font-medium text-gray-200 transition-colors hover:text-white">Admissions</a></li>
                <li><a href="<?php echo $webroot; ?>user/g.php" class="relative font-medium text-gray-200 transition-colors hover:text-white">Gallery</a></li>
                <li><a href="<?php echo $webroot; ?>user/e.php" class="relative font-medium text-gray-200 transition-colors hover:text-white">Events & News</a></li>
                <li><a href="<?php echo $webroot; ?>user/c.php" class="relative font-medium text-gray-200 transition-colors hover:text-white">Contact Us</a></li>
                <li><a href="<?php echo $webroot; ?>login.php" class="relative font-medium text-gray-200 transition-colors hover:text-white">LOGIN</a></li>
            </ul>
        </nav>
    </header>

    <div class="content-below">
        
    </div>

    <button id="back-to-top" class="back-to-top">
        &uarr;
    </button>
    
    <script>
        // DOMContentLoaded event listener to ensure all elements are loaded before running script
        document.addEventListener('DOMContentLoaded', function () {
            // Get the header elements
            const combinedHeader = document.getElementById('combined-header');
            const topBar = document.getElementById('top-bar');
            const mobileMenu = document.getElementById('mobile-menu');
            const navMenu = document.getElementById('nav-menu');

            // --- Header & Navbar Logic ---
            // Get the initial height of the top bar to know when to start the transition
            // Use a variable for the scroll threshold, as topBarHeight might be 0 on page load
            const scrollThreshold = 70; // A fixed value works better for reliable behavior

            window.addEventListener('scroll', () => {
                // Check if the user has scrolled past the fixed threshold
                if (window.scrollY > scrollThreshold) {
                    // Add the 'scrolled' class to the combined header
                    combinedHeader.classList.add('scrolled');
                    // Hide the top bar with a smooth transition
                    topBar.classList.add('hidden');
                } else {
                    // Remove the 'scrolled' class
                    combinedHeader.classList.remove('scrolled');
                    // Show the top bar
                    topBar.classList.remove('hidden');
                }
            });

            // Mobile Menu Toggle (retained from original code)
            if (mobileMenu && navMenu) {
                mobileMenu.addEventListener('click', () => {
                    mobileMenu.classList.toggle('active');
                    navMenu.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>