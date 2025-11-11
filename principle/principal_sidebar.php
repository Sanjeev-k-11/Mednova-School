<?php
// School/principal/principal_sidebar.php
// Principal Sidebar with universal toggle for all devices
?>

<style>
    /* Sidebar Base */
    .principal-sidebar {
        position: fixed;
        top: 0;
        left: -280px; /* Hidden by default */
        height: 100%;
        width: 280px;
        background-color: #1a202c; /* Dark background */
        color: #e2e8f0; /* Light text */
        transition: left 0.4s ease-in-out;
        z-index: 40;
        padding-top: 70px; /* Space for header/toggle */
        overflow-y: auto; /* Scroll for long content */
        box-shadow: 2px 0 6px rgba(0,0,0,0.25);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Sidebar open state - applies to ALL screen sizes now */
    body.sidebar-open .principal-sidebar {
        left: 0; /* Show sidebar when 'sidebar-open' class is present */
    }

    /* Main content adjustment when sidebar is open - applies to ALL screen sizes */
    /* Adjust this media query if you want content to be pushed differently on mobile vs desktop */
    /* For consistent push: apply on larger screens for better UX */
    @media (min-width: 768px) { /* Content push generally looks better on larger screens */
        body.sidebar-open .main-content {
            margin-left: 280px;
            transition: margin-left 0.4s ease-in-out;
        }
    }
    /* If you want content to slide under/overlay on mobile, no margin-left for smaller screens */
    /* If you want content push on mobile too, remove the @media (min-width: 768px) around the above rule */


    /* List styling */
    .principal-sidebar ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .principal-sidebar li {
        border-bottom: 1px solid rgba(255,255,255,0.05); /* Subtle separator */
    }

    /* Link styling */
    .principal-sidebar a {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 22px;
        color: #a0aec0; /* Lighter text for non-active links */
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .principal-sidebar a:hover {
        background-color: #2d3748; /* Darker on hover */
        color: #ffffff;
        padding-left: 28px; /* Smooth hover slide effect */
    }

    /* Active link styling */
    .principal-sidebar a.active {
        background: linear-gradient(90deg, #4CAF50, #66BB6A); /* Principal-friendly gradient */
        color: #fff;
        border-left: 4px solid #4CAF50; /* Highlight */
    }

    /* Sidebar Section Title */
    .sidebar-section {
        padding: 12px 20px;
        font-size: 0.8rem;
        text-transform: uppercase;
        color: #718096;
        letter-spacing: 1px;
        font-weight: 600;
        margin-top: 15px; /* Spacing between sections */
    }
     .sidebar-section:first-child {
        margin-top: 0; /* No top margin for the very first section */
    }

    /* Icons (using Font Awesome now for consistency and variety) */
    .principal-sidebar a .fa {
        font-size: 1.1rem; /* Adjust icon size */
        width: 20px; /* Fixed width for alignment */
        text-align: center;
        flex-shrink: 0;
    }

    /* Scrollbar styling */
    .principal-sidebar::-webkit-scrollbar {
        width: 6px;
    }
    .principal-sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.15);
        border-radius: 6px;
    }

    /* Toggle button - now always visible */
    .sidebar-toggle {
        position: fixed;
        top: 15px;
        left: 15px;
        background: #1a202c;
        border: none;
        color: #fff;
        padding: 10px;
        border-radius: 6px;
        cursor: pointer;
        z-index: 50;
        display: flex; /* Ensure it's always displayed */
        align-items: center;
        justify-content: center;
        transition: background 0.3s;
    }
    .sidebar-toggle:hover {
        background: #2d3748;
    }

    /* Adjust top-navbar position to avoid being covered by sidebar */
    .top-navbar {
        position: fixed;
        top: 0;
        left: 0; /* Default position */
        width: 100%;
        height: 60px;
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        padding: 0 20px;
        z-index: 30;
        transition: left 0.4s ease-in-out, width 0.4s ease-in-out;
    }

    /* When sidebar is open, push the top-navbar on larger screens */
    @media (min-width: 768px) {
        body.sidebar-open .top-navbar {
            left: 280px;
            width: calc(100% - 280px);
        }
    }

</style>

<!-- Toggle Button (now universally visible and functional) -->
<button class="sidebar-toggle" id="sidebar-toggle-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i> <!-- Font Awesome Bars icon -->
</button>

<!-- Sidebar -->
<nav class="principal-sidebar" id="principal-sidebar">
    <ul>
        <!-- Main -->
        <div class="sidebar-section">Main</div>
        <li><a href="dashboard.php" class="active">
            <i class="fas fa-chart-line fa-fw"></i>
            Dashboard
        </a></li>
        <li><a href="principal_dashboard_academics_welfare.php" class="active">
            <i class="fas fa-chart-line fa-fw"></i>
           Academics Dashboard
        </a></li>
        <li><a href="principal_profile.php">
            <i class="fas fa-user-circle fa-fw"></i>
            My Profile
        </a></li>

        <!-- Academics & Staff -->
        <div class="sidebar-section">Academics & Staff</div>
        <li><a href="manage_classes.php">
            <i class="fas fa-school fa-fw"></i>
            Manage Classes
        </a></li>
        <li><a href="manage_teachers.php">
            <i class="fas fa-chalkboard-teacher fa-fw"></i>
            Manage Teachers
        </a></li>
        <li><a href="manage_students.php">
            <i class="fas fa-user-graduate fa-fw"></i>
            Manage Students
        </a></li>
        <li><a href="view_timetables.php">
            <i class="fas fa-calendar-alt fa-fw"></i>
            View Timetables
        </a></li>
        <li><a href="view_exam_schedules.php">
            <i class="fas fa-pencil-alt fa-fw"></i>
            View Exam Schedules
        </a></li>
        <li><a href="view_assignments.php">
            <i class="fas fa-folder-open fa-fw"></i>
            View Assignments
        </a></li>
        <li><a href="view_attendance_reports.php">
            <i class="fas fa-calendar-check fa-fw"></i>
            Attendance Reports
        </a></li>
         <li><a href="review_marks.php">
            <i class="fas fa-graduation-cap fa-fw"></i>
            Review Marks
        </a></li>
        <li><a href="manage_library.php">
            <i class="fas fa-book-reader fa-fw"></i>
            Manage Library
        </a></li>


        <!-- Administration -->
        <div class="sidebar-section">Administration</div>
        <li><a href="approve_leaves.php">
            <i class="fas fa-calendar-times fa-fw"></i>
            Approve Leaves
        </a></li>
        <li><a href="review_indiscipline.php">
            <i class="fas fa-exclamation-triangle fa-fw"></i>
            Review Indiscipline
        </a></li>
        <li><a href="manage_fees.php">
            <i class="fas fa-money-bill-wave fa-fw"></i>
            Manage Fees
        </a></li>
        <li><a href="manage_staff_salary.php">
            <i class="fas fa-hand-holding-usd fa-fw"></i>
            Manage Staff Salary
        </a></li>
        <li><a href="view_income_expenses.php">
            <i class="fas fa-chart-pie fa-fw"></i>
            Income & Expenses
        </a></li>
        <li><a href="manage_departments.php">
            <i class="fas fa-building fa-fw"></i>
            Manage Departments
        </a></li>
        <li><a href="manage_vans.php">
            <i class="fas fa-bus fa-fw"></i>
            Manage Transport
        </a></li>
        <li><a href="manage_scholarships.php">
            <i class="fas fa-award fa-fw"></i>
            Manage Scholarships
        </a></li>


        <!-- Communication & Engagement -->
        <div class="sidebar-section">Communication & Engagement</div>
        <li><a href="manage_announcements.php">
            <i class="fas fa-bullhorn fa-fw"></i>
            Manage Announcements
        </a></li>
        <li><a href="manage_events.php">
            <i class="fas fa-calendar-plus fa-fw"></i>
            Manage Events
        </a></li>
        <li><a href="view_notices.php">
            <i class="fas fa-bell fa-fw"></i>
            View Notices
        </a></li>
        <li><a href="view_study_materials.php">
            <i class="fas fa-book fa-fw"></i>
            View Study Materials
        </a></li>
        <li><a href="manage_helpdesk_tickets.php">
            <i class="fas fa-life-ring fa-fw"></i>
            Manage Helpdesk
        </a></li>
        <li><a href="principal_chat.php">
            <i class="fas fa-comments fa-fw"></i>
            Staff Chat
        </a></li>
        <li><a href="manage_student_forum.php">
            <i class="fas fa-users-line fa-fw"></i>
            Student Forum Moderation
        </a></li>
        <li><a href="manage_sports_clubs.php">
            <i class="fas fa-futbol fa-fw"></i>
            Manage Sports Clubs
        </a></li>
        <li><a href="manage_cultural_programs.php">
            <i class="fas fa-masks-theater fa-fw"></i>
            Manage Cultural Programs
        </a></li>
        <li><a href="manage_competitions.php">
            <i class="fas fa-trophy fa-fw"></i>
            Manage Competitions
        </a></li>


        <!-- Online Tests -->
        <div class="sidebar-section">Online Tests</div>
        <li><a href="view_all_online_tests.php">
            <i class="fas fa-laptop-code fa-fw"></i>
            View All Tests
        </a></li>
        <li><a href="create_school_test.php">
            <i class="fas fa-plus-square fa-fw"></i>
            Create School Test
        </a></li>


        <!-- Website Content Management -->
        <div class="sidebar-section">Website Content</div>
        <li><a href="manage_contact_settings.php">
            <i class="fas fa-address-card fa-fw"></i>
            Contact Page Settings
        </a></li>
        <li><a href="manage_news_events.php">
            <i class="fas fa-newspaper fa-fw"></i>
            News & Events Content
        </a></li>
         


        <!-- Utilities -->
        <div class="sidebar-section">Utilities</div>
         
        <li><a href="../logout.php">
            <i class="fas fa-sign-out-alt fa-fw"></i>
            Logout
        </a></li>
    </ul>
</nav>

<script>
    // Universal toggle function for the button
    function toggleSidebar() {
        document.body.classList.toggle("sidebar-open");
    }

    // Event listener for clicks outside the sidebar (applies universally)
    document.addEventListener('click', (event) => {
        const sidebar = document.getElementById('principal-sidebar');
        const toggleBtn = document.getElementById('sidebar-toggle-btn'); // Get the button by its ID

        // If sidebar is not open, or elements are not found, do nothing.
        if (!document.body.classList.contains('sidebar-open') || !sidebar || !toggleBtn) {
            return;
        }

        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggleBtn = toggleBtn.contains(event.target);

        // If sidebar is open and click is outside the sidebar AND not on the toggle button
        if (!isClickInsideSidebar && !isClickOnToggleBtn) {
            document.body.classList.remove('sidebar-open'); // Close sidebar
        }
    });

    // Optional: Highlight active link based on current page
    document.addEventListener('DOMContentLoaded', () => {
        const currentPath = window.location.pathname.split('/').pop();
        const sidebarLinks = document.querySelectorAll('.principal-sidebar a');

        sidebarLinks.forEach(link => {
            const linkHref = link.getAttribute('href');
            // Check for direct match or dashboard specific match for root
            if (linkHref === currentPath || (linkHref === "principal_dashboard.php" && (currentPath === "" || currentPath === "principal_dashboard.php" || currentPath === "index.php"))) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    });
</script>