<?php
// School/teacher/teacher_header.php
// This file contains the fixed header bar for all teacher-facing pages.
// It assumes session_start() has been called and the user's role is confirmed.

// Retrieve teacher info from session
$loggedInTeacherName = $_SESSION['full_name'] ?? 'Teacher';
$loggedInTeacherRole = $_SESSION['role'] ?? 'Teacher';

// School-wide settings (can be shared with admin_header)
$schoolName = "Basic Public School";
$logoUrl = "../uploads/basic.png"; // Path relative to the teacher folder

// Allow the main page to set its own title, otherwise use a default.
$pageTitle = $pageTitle ?? 'Teacher Portal';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Shared styles for the layout, can be moved to a global CSS file */
        .gradient-background-blue-cyan { background: linear-gradient(to right, #4facfe, #00f2fe); }
        .gradient-background-purple-pink { background: linear-gradient(to right, #a18cd1, #fbc2eb); }
        
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 20;
            background-color: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-logo {
            height: 40px;
            width: auto;
            margin-right: 0.5rem;
        }

        .header-school-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            flex-grow: 1;
        }

        .header-user-info {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            white-space: nowrap;
        }
        .header-user-info span { color: #374151; }
        .header-user-info span strong { font-weight: 600; }
        .header-user-info a { color: #ef4444; text-decoration: none; font-weight: 500; }
        .header-user-info a:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .header-user-info { display: none; }
            .fixed-header { gap: 0.5rem; }
            .header-school-name { font-size: 1rem; }
            .header-logo { height: 30px; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            const body = document.body;
            const sidebar = document.querySelector('.teacher-sidebar');
            const toggleButton = document.getElementById('teacher-sidebar-toggle-open');

            // Toggle sidebar on button click
            toggleButton?.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevents the document click listener from firing immediately
                body.classList.toggle('sidebar-open');
            });

            // Close sidebar when clicking anywhere on the page outside of the sidebar
            document.addEventListener('click', function(e) {
                // Check if the body has the 'sidebar-open' class AND the clicked element is not inside the sidebar
                if (body.classList.contains('sidebar-open') && !sidebar.contains(e.target) && !toggleButton.contains(e.target)) {
                    body.classList.remove('sidebar-open');
                }
            });

            // Handle window resize for mobile view
            window.addEventListener('resize', function() {
                if (window.innerWidth < 768) { // md breakpoint
                    body.classList.remove('sidebar-open');
                }
            });
        });
    </script>
</head>
<body class="min-h-screen">
    <?php
    // Include the teacher-specific sidebar navigation menu
    require_once "./teacher_sidebar.php";
    ?>

    <div class="fixed-header">
        <button id="teacher-sidebar-toggle-open" class="focus:outline-none text-gray-600 hover:text-gray-800 mr-2 md:mr-4" aria-label="Toggle sidebar">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="School Logo" class="header-logo">
        <span class="header-school-name"><?php echo htmlspecialchars($schoolName); ?></span>

        <div class="header-user-info">
            <a href="teacher_messages.php" class="notification-bell" title="Check Messages">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <div class="notification-dot"></div>
            </a>
            <span>Welcome, <strong><?php echo htmlspecialchars($loggedInTeacherName); ?></strong> (<?php echo htmlspecialchars(ucfirst($loggedInTeacherRole)); ?>)</span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', (event) => {
            // Your existing sidebar toggle logic here...

            // --- NEW NOTIFICATION LOGIC ---
            const checkNotifications = async () => {
                try {
                    // The path to the API file from the root
                    const response = await fetch('api_check_notifications.php');
                    if (!response.ok) return; // Don't do anything on server error

                    const data = await response.json();
                    
                    if (data.new_messages > 0) {
                        // If there are new messages, add a class to the body
                        // This will make the red dot appear via CSS
                        document.body.classList.add('has-new-notification');
                    } else {
                        // If no new messages, ensure the class is removed
                        document.body.classList.remove('has-new-notification');
                    }
                } catch (error) {
                    console.error("Error checking notifications:", error);
                }
            };

            // Check for notifications immediately on page load
            checkNotifications();

            // Then, check again every 30 seconds (30000 milliseconds)
            setInterval(checkNotifications, 30000);
        });
</script>