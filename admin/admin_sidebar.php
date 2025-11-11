<?php
// sidebar.php

// PART 1: PHP INITIALIZATION
// =============================================================================
// This block sets up the variables used throughout the sidebar.
// It retrieves user information from the session and defines the base path for links.

// Start the session if it hasn't been started already.
// This is typically done in an entry point file (like index.php or header.php)
// but included here as a safeguard if this file is accessed directly.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Default values
$sidebar_display_name = 'Admin';
$sidebar_role_display = 'Admin';

// Determine the display name and role based on session data
if (isset($_SESSION['role'])) {
    $sidebar_role_display = ucfirst(htmlspecialchars($_SESSION['role']));
}

if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'user' && isset($_SESSION['username'])) {
        $sidebar_display_name = htmlspecialchars($_SESSION['username']);
    } elseif ($_SESSION['user_type'] === 'staff' && isset($_SESSION['display_name'])) {
        $sidebar_display_name = htmlspecialchars($_SESSION['display_name']);
    }
}
// Fallback if user_type is not 'user' or 'staff' but a username is available
// Or if the default 'Admin' is still present but a username exists
if ($sidebar_display_name === 'Admin' && isset($_SESSION['username'])) {
    $sidebar_display_name = htmlspecialchars($_SESSION['username']);
}


// Define the base path for all links.
// IMPORTANT: For production, consider moving this to a central config file.
// define('WEBROOT_PATH', '/new school/'); // Example for a global constant
$webroot_path = '/'; // MAKE SURE THIS IS CORRECT

?>

<!-- PART 2: HTML STRUCTURE
============================================================================= -->

<!-- Sidebar Overlay: Darkens the page when the sidebar is open -->
<div id="admin-sidebar-overlay" class="fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-30" aria-hidden="true"></div>

<!-- Sidebar Container: Positioned off-screen by default -->
<div id="admin-sidebar" class="fixed inset-y-0 left-0 w-64 bg-gray-800 text-white transform -translate-x-full transition-transform duration-300 ease-in-out z-40" role="navigation" aria-label="Main Admin Navigation">
    <div class="p-4 flex flex-col h-full">
        <!-- Sidebar Header: Displays user info and close button -->
        <div class="flex items-center justify-between mb-6 shrink-0">
            <div>
                <div class="text-xl font-semibold"><?php echo $sidebar_display_name; ?></div>
                <span class="text-sm font-medium px-2 py-1 mt-1 inline-block rounded-full bg-indigo-600 text-white">
                    <?php echo $sidebar_role_display; ?>
                </span>
            </div>
            <button id="admin-sidebar-toggle-close" class="text-gray-400 hover:text-white focus:outline-none" aria-label="Close sidebar">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Navigation Links with Scrollbar -->
        <nav class="flex-grow space-y-2 overflow-y-auto custom-scrollbar" aria-label="Admin Menu">
            <!-- General Navigation Links -->
            <a href="<?php echo $webroot_path; ?>admin/dashboard.php" class="nav-link flex items-center px-3 py-2 rounded-md hover:bg-gray-700" aria-label="Go to Dashboard">
                <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                Dashboard
            </a>
            <a href="<?php echo $webroot_path; ?>admin/myprofile.php" class="nav-link flex items-center px-3 py-2 rounded-md hover:bg-gray-700" aria-label="View your Profile">
                <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                </svg>
                Profile
            </a>

            <!-- Accordion: Academic Setup -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="academic-setup-content" id="academic-setup-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3.5a1 1 0 00.002 1.84L10 11.23l7.394-3.71a1 1 0 00.002-1.84l-7-3.5zM3 9.333V14a1 1 0 001 1h12a1 1 0 001-1V9.333l-7.5 3.75-7.5-3.75z" />
                        </svg>
                        Academic Setup
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="academic-setup-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="academic-setup-header">
                    <a href="<?php echo $webroot_path; ?>admin/manage_classes.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Classes</a>
                    <a href="<?php echo $webroot_path; ?>admin/manage_subjects.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Subjects</a>
                    <a href="<?php echo $webroot_path; ?>admin/manage_exams.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Exams</a>
                    <a href="<?php echo $webroot_path; ?>admin/view_exam_schedule.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Exam Schedule</a>
                </div>
            </div>

            <!-- Accordion: Events & Communication -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="events-comms-content" id="events-comms-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                        </svg>
                        Events & Communication
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="events-comms-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="events-comms-header">
                    <a href="<?php echo $webroot_path; ?>admin/manage_announcements.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Announcements</a>
                    <a href="<?php echo $webroot_path; ?>admin/events_calendar.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Events Calendar</a>
                    <a href="<?php echo $webroot_path; ?>admin/manage_events.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Events</a>
                </div>
            </div>

            <!-- Accordion: Student Management -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="student-management-content" id="student-management-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zm-1.558 5.542a1 1 0 01.33-1.084 5 5 0 00-8.584 0 1 1 0 01.33 1.084l1.18 3.542A1 1 0 014 16h2a1 1 0 01.97-.788l1.18-3.542z" />
                        </svg>
                        Student Management
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="student-management-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="student-management-header">
                    <a href="<?php echo $webroot_path; ?>admin/create_student.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Create Student</a>
                    <a href="<?php echo $webroot_path; ?>admin/view_students.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Students</a>
                </div>
            </div>

            <!-- Accordion: Staff Management -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="staff-management-content" id="staff-management-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v-1h8v1zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-1a4 4 0 00-4-4H8a4 4 0 00-4 4v1h12z" />
                        </svg>
                        Staff Management
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="staff-management-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="staff-management-header">
                    <a href="<?php echo $webroot_path; ?>admin/create_staff.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Create Staff</a>
                    <a href="<?php echo $webroot_path; ?>admin/view_all_staff.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Staff</a>
                    <a href="<?php echo $webroot_path; ?>admin/assign_teachers.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Assign Teachers</a>
                    <a href="<?php echo $webroot_path; ?>admin/department_management.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Department Management</a>
                    <a href="<?php echo $webroot_path; ?>admin/library_dashboard.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Library Management</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_clubs.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Clubs</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_competitions.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Competitions</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_documents.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Documents</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_cultural_programs.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Cultural Programs</a>
                </div>
            </div>

            <!-- Accordion: School Scholarship -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="school-scholarship-content" id="school-scholarship-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 2a1 1 0 01.894.553l7 14A1 1 0 0117 18H3a1 1 0 01-.894-1.447l7-14A1 1 0 0110 2zM10 6a1 1 0 00-.707.293l-3 3a1 1 0 101.414 1.414L10 8.414l2.293 2.293a1 1 0 001.414-1.414l-3-3A1 1 0 0010 6z" />
                        </svg>
                        School Scholarship
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="school-scholarship-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="school-scholarship-header">
                    <a href="<?php echo $webroot_path; ?>admin/manage_scholarships.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Scholarships</a>
                    <a href="<?php echo $webroot_path; ?>admin/assign_scholarship.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Applications</a>
                    <a href="<?php echo $webroot_path; ?>admin/upload_achievement.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Upload Achievements</a>
                </div>
            </div>

            <!-- Accordion: Student Fees -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="student-fees-content" id="student-fees-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8.433 7.418c.158-.103.346-.196.567-.267v1.698a2.5 2.5 0 00-1.168-.217c-1.36.0-2.5 1.12-2.5 2.5 0 .274.044.54.128.793l.363-1.318A2.5 2.5 0 018.433 7.418zM11 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0 2a10 10 0 100-20 10 10 0 000 20z" clip-rule="evenodd" />
                        </svg>
                        Student Fees
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="student-fees-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="student-fees-header">
                    <a href="<?php echo $webroot_path; ?>admin/manage_fees.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Fee Structure</a>
                    <a href="<?php echo $webroot_path; ?>admin/assign_student_fees.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Assign Fees</a>
                    <a href="<?php echo $webroot_path; ?>admin/add_bulk_fees.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Add Bulk Fees</a>
                </div>
            </div>

            <!-- Accordion: Staff Payroll -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="staff-payroll-content" id="staff-payroll-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm12 6a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4a2 2 0 012-2h8zm-2 4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                        </svg>
                        Staff Payroll
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="staff-payroll-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="staff-payroll-header">
                    <a href="<?php echo $webroot_path; ?>admin/create_salary.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Generate Salary</a>
                    <a href="<?php echo $webroot_path; ?>admin/view_staff_salaries.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">View Salaries</a>
                    <a href="<?php echo $webroot_path; ?>admin/manage_income.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Income</a>
                    <a href="<?php echo $webroot_path; ?>admin/manage_expenses.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Expenses</a>
                </div>
            </div>

            <!-- Accordion: Timetable -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="timetable-content" id="timetable-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.34 3.03a1 1 0 01.66 1.869l-1 5A1 1 0 019.22 11H3a1 1 0 01-1-1V5a1 1 0 011-1h1.22a1 1 0 01.999.78l1.328 4.218 2.82-2.82a1 1 0 011.414 0l1.558 1.558a1 1 0 010 1.414l-3.333 3.333a1 1 0 01-1.414 0l-1.5-1.5a1 1 0 01-.05-1.46L6.22 5H7a1 1 0 010-2h4.34zM16 5a1 1 0 100-2 1 1 0 000 2zM18 7a1 1 0 100-2 1 1 0 000 2zM16 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        Timetable Management
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="timetable-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="timetable-header">
                    <a href="<?php echo $webroot_path; ?>admin/manage_periods.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Periods</a>
                    <a href="<?php echo $webroot_path; ?>admin/manage_timetable.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Timetables</a>
                </div>
            </div>

            <!-- Accordion: Transport -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="transport-content" id="transport-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.34 3.03a1 1 0 01.66 1.869l-1 5A1 1 0 019.22 11H3a1 1 0 01-1-1V5a1 1 0 011-1h1.22a1 1 0 01.999.78l1.328 4.218 2.82-2.82a1 1 0 011.414 0l1.558 1.558a1 1 0 010 1.414l-3.333 3.333a1 1 0 01-1.414 0l-1.5-1.5a1 1 0 01-.05-1.46L6.22 5H7a1 1 0 010-2h4.34zM16 5a1 1 0 100-2 1 1 0 000 2zM18 7a1 1 0 100-2 1 1 0 000 2zM16 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        Transport
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="transport-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="transport-header">
                    <a href="<?php echo $webroot_path; ?>admin/create_van.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Create Van</a>
                    <a href="<?php echo $webroot_path; ?>admin/view_vans.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Vans</a>
                </div>
            </div>

            <!-- Accordion: Student & Staff Operations (Renamed from Teacher Student) -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="student-staff-operations-content" id="student-staff-operations-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.34 3.03a1 1 0 01.66 1.869l-1 5A1 1 0 019.22 11H3a1 1 0 01-1-1V5a1 1 0 011-1h1.22a1 1 0 01.999.78l1.328 4.218 2.82-2.82a1 1 0 011.414 0l1.558 1.558a1 1 0 010 1.414l-3.333 3.333a1 1 0 01-1.414 0l-1.5-1.5a1 1 0 01-.05-1.46L6.22 5H7a1 1 0 010-2h4.34z" clip-rule="evenodd" />
                        </svg>
                        Student & Staff Operations
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="student-staff-operations-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="student-staff-operations-header">
                    <a href="<?php echo $webroot_path; ?>admin/indiscipline.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Indiscipline</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_assignments.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Assignments</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_attendance_report.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Attendance Report</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_marksheet_report.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Student Marksheets</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_notices.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Notices</a>
                    <a href="<?php echo $webroot_path; ?>admin/leave_management.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Leave Management</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_helpdesk.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Helpdesk Tickets</a>
                </div>
            </div>

            <!-- Accordion: Index Page (Website Settings) -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="index-page-content" id="index-page-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.34 3.03a1 1 0 01.66 1.869l-1 5A1 1 0 019.22 11H3a1 1 0 01-1-1V5a1 1 0 011-1h1.22a1 1 0 01.999.78l1.328 4.218 2.82-2.82a1 1 0 011.414 0l1.558 1.558a1 1 0 010 1.414l-3.333 3.333a1 1 0 01-1.414 0l-1.5-1.5a1 1 0 01-.05-1.46L6.22 5H7a1 1 0 010-2h4.34z" clip-rule="evenodd" />
                        </svg>
                        Website Homepage
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="index-page-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="index-page-header">
                    <a href="<?php echo $webroot_path; ?>admin/scman.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Homepage Banner</a>
                    <a href="<?php echo $webroot_path; ?>admin/adsettings.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Homepage About Section</a>
                    <a href="<?php echo $webroot_path; ?>admin/acdsettings.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Homepage Academics</a>
                    <a href="<?php echo $webroot_path; ?>admin/adfact.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Admissions Section</a>
                    <a href="<?php echo $webroot_path; ?>admin/gallery_settings.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Website Gallery</a>
                    <a href="<?php echo $webroot_path; ?>admin/achievements.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Website Achievements</a>
                </div>
            </div>

            <!-- Accordion: Content Management -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700" aria-expanded="false" aria-controls="content-management-content" id="content-management-header">
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.34 3.03a1 1 0 01.66 1.869l-1 5A1 1 0 019.22 11H3a1 1 0 01-1-1V5a1 1 0 011-1h1.22a1 1 0 01.999.78l1.328 4.218 2.82-2.82a1 1 0 011.414 0l1.558 1.558a1 1 0 010 1.414l-3.333 3.333a1 1 0 01-1.414 0l-1.5-1.5a1 1 0 01-.05-1.46L6.22 5H7a1 1 0 010-2h4.34z" clip-rule="evenodd" />
                        </svg>
                        Content Management
                    </span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="content-management-content" class="accordion-content pl-6 space-y-1" role="region" aria-labelledby="content-management-header">
                    <a href="<?php echo $webroot_path; ?>admin/academics.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Academics Page</a>
                    <a href="<?php echo $webroot_path; ?>admin/about_mednova_settings.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">About Us Page</a>
                    <a href="<?php echo $webroot_path; ?>admin/events_news.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Events & News Page</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_contact.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Contact Page Details</a>
                    <a href="<?php echo $webroot_path; ?>admin/admin_inquiries.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Inquiries</a>
                </div>
            </div>

        </nav>

        <!-- Logout Link: Pinned to the bottom -->
        <div class="mt-auto shrink-0">
            <a href="<?php echo $webroot_path; ?>logout.php" class="flex items-center px-3 py-2 rounded-md text-red-400 hover:bg-gray-700 hover:text-red-300" aria-label="Log out of your account">
                <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                </svg>
                Logout
            </a>
        </div>
    </div>
</div>

<!-- (Optional) This button would typically be in your main header to open the sidebar -->
<!-- <button id="admin-sidebar-toggle-open" class="fixed top-4 left-4 p-2 bg-gray-700 text-white rounded-md z-50">
    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
    </svg>
</button> -->

<!-- PART 3: CSS STYLING
============================================================================= -->
<style>
    /* Custom Scrollbar Styles */
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #2d3748;
        /* Dark gray */
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #4a5568;
        /* Medium gray */
        border-radius: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #718096;
        /* Light gray on hover */
    }

    /* Accordion Styles */
    .accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out, padding-top 0.3s ease-in-out, padding-bottom 0.3s ease-in-out;
    }

    /* Add padding for better visual spacing when open */
    .accordion-header.active+.accordion-content {
        max-height: 500px;
        /* Sufficiently large height to cover most content, adjust if needed */
        padding-top: 0.25rem;
        /* Small padding at the top of content */
        padding-bottom: 0.25rem;
        /* Small padding at the bottom of content */
    }

    /* Rotate arrow icon when accordion is active */
    .accordion-header.active svg:last-child {
        transform: rotate(180deg);
    }

    /* Active Navigation Link Style */
    .nav-link.active {
        background-color: #4a5568;
        /* Medium gray for active link background */
        color: white;
    }
</style>

<!-- PART 4: JAVASCRIPT FUNCTIONALITY
============================================================================= -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get references to sidebar elements
        const toggleOpenBtn = document.getElementById('admin-sidebar-toggle-open'); // Assumed to be in header
        const toggleCloseBtn = document.getElementById('admin-sidebar-toggle-close');
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('admin-sidebar-overlay');

        /**
         * Opens the sidebar by removing the transform class and showing the overlay.
         */
        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-50');
        }

        /**
         * Closes the sidebar by adding the transform class and hiding the overlay.
         */
        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            overlay.classList.remove('opacity-50');
        }

        // Event Listeners for sidebar toggle
        if (toggleOpenBtn) {
            toggleOpenBtn.addEventListener('click', openSidebar);
        }
        if (toggleCloseBtn) {
            toggleCloseBtn.addEventListener('click', closeSidebar);
        }
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Accordion functionality
        const accordions = document.querySelectorAll('.accordion-header');
        accordions.forEach(accordion => {
            accordion.addEventListener('click', () => {
                const isActive = accordion.classList.toggle('active');
                accordion.setAttribute('aria-expanded', isActive);
            });
        });

        // Active link highlighting and accordion expansion
        const currentPage = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');

        navLinks.forEach(link => {
            // Normalize link path to match window.location.pathname
            // Remove trailing slash if present, and ensure it starts with /new school/
            let linkPath = new URL(link.href).pathname;
            if (linkPath.endsWith('/') && linkPath.length > 1) {
                linkPath = linkPath.slice(0, -1);
            }
            if (!linkPath.startsWith('<?php echo $webroot_path; ?>')) {
                 // Prepend webroot_path if it's not already there for comparison
                 // This handles cases where links might be relative or absolute differently
                 linkPath = '<?php echo $webroot_path; ?>' + linkPath.substring(1);
            }


            // Remove trailing slash from currentPage for accurate comparison
            let currentPathNormalized = currentPage;
            if (currentPathNormalized.endsWith('/') && currentPathNormalized.length > 1) {
                currentPathNormalized = currentPathNormalized.slice(0, -1);
            }

            if (currentPathNormalized === linkPath) {
                link.classList.add('active'); // Highlight the current link

                // Find the parent accordion and activate it
                const parentAccordion = link.closest('.accordion');
                if (parentAccordion) {
                    const accordionHeader = parentAccordion.querySelector('.accordion-header');
                    if (accordionHeader && !accordionHeader.classList.contains('active')) {
                        accordionHeader.classList.add('active'); // Open the accordion
                        accordionHeader.setAttribute('aria-expanded', 'true'); // Update ARIA attribute
                    }
                }
            }
        });
    });
</script>