<?php
// sidebar.php

// PART 1: PHP INITIALIZATION
// =============================================================================
// This block sets up the variables used throughout the sidebar.
// It retrieves user information from the session and defines the base path for links.

$sidebar_display_name = 'Super Admin';
$sidebar_role_display = 'Super Admin';
if (isset($_SESSION['role'])) {
    $sidebar_role_display = ucfirst($_SESSION['role']);
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'user' && isset($_SESSION['username'])) {
        $sidebar_display_name = $_SESSION['username'];
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff' && isset($_SESSION['display_name'])) {
        $sidebar_display_name = $_SESSION['display_name'];
    } else if (isset($_SESSION['username'])) {
        $sidebar_display_name = $_SESSION['username'];
    }
}
$webroot_path = '/'; // MAKE SURE THIS IS CORRECT
?>

<!-- PART 2: HTML STRUCTURE
============================================================================= -->

<!-- Sidebar Overlay: Darkens the page when the sidebar is open -->
<div id="admin-sidebar-overlay" class="fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-30"></div>

<!-- Sidebar Container: Positioned off-screen by default -->
<div id="admin-sidebar" class="fixed inset-y-0 left-0 w-64 bg-gray-800 text-white transform -translate-x-full transition-transform duration-300 ease-in-out z-40">
    <div class="p-4 flex flex-col h-full">
        <!-- Sidebar Header: Displays user info and close button -->
        <div class="flex items-center justify-between mb-6 shrink-0">
            <div>
                <div class="text-xl font-semibold"><?php echo htmlspecialchars($sidebar_display_name); ?></div>
                <span class="text-sm font-medium px-2 py-1 mt-1 inline-block rounded-full bg-indigo-600 text-white">
                    <?php echo htmlspecialchars($sidebar_role_display); ?>
                </span>
            </div>
            <button id="admin-sidebar-toggle-close" class="text-gray-400 hover:text-white focus:outline-none">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Navigation Links with Scrollbar -->
        <nav class="flex-grow space-y-2 overflow-y-auto custom-scrollbar">
            <!-- General -->
            <a href="<?php echo $webroot_path; ?>super_admin/dashboard.php" class="nav-link flex items-center px-3 py-2 rounded-md hover:bg-gray-700">
                <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                Dashboard
            </a>
            <a href="<?php echo $webroot_path; ?>super_admin/feedashbord.php" class="nav-link flex items-center px-3 py-2 rounded-md hover:bg-gray-700">
                <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                Fee Dashboard
            </a>
            <a href="<?php echo $webroot_path; ?>super_admin/myprofile.php" class="nav-link flex items-center px-3 py-2 rounded-md hover:bg-gray-700">
                <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                </svg>
                Profile
            </a>

            <!-- Accordion: Academic Setup -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700">
                    <span class="flex items-center"><svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3.5a1 1 0 00.002 1.84L10 11.23l7.394-3.71a1 1 0 00.002-1.84l-7-3.5zM3 9.333V14a1 1 0 001 1h12a1 1 0 001-1V9.333l-7.5 3.75-7.5-3.75z" />
                        </svg>Academic Setup</span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div class="accordion-content pl-6 space-y-1">
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_classes.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Classes</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_subjects.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Subjects</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_exams.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Exams</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/view_exam_schedule.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Exam Schedule</a>

                </div>
            </div>

            <!-- Accordion: Events & Communication -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700">
                    <span class="flex items-center"><svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                        </svg>Events & Comms</span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div class="accordion-content pl-6 space-y-1">
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_announcements.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Announcements</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/events_calendar.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Events Calendar</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_events.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">manage Events</a>

                </div>
            </div>

            <!-- Accordion: Student Management -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700">
                    <span class="flex items-center"><svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zm-1.558 5.542a1 1 0 01.33-1.084 5 5 0 00-8.584 0 1 1 0 01.33 1.084l1.18 3.542A1 1 0 014 16h2a1 1 0 01.97-.788l1.18-3.542z" />
                        </svg>Student Management</span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div class="accordion-content pl-6 space-y-1">
                    <a href="<?php echo $webroot_path; ?>super_admin/create_student.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Create Student</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/view_students.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Students</a>
                </div>
            </div>

            <!-- Accordion: Staff Management -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700">
                    <span class="flex items-center"><svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v-1h8v1zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-1a4 4 0 00-4-4H8a4 4 0 00-4 4v1h12z" />
                        </svg>Staff Management</span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div class="accordion-content pl-6 space-y-1">
                    <a href="<?php echo $webroot_path; ?>super_admin/create_admin.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Create Admin</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_admins.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Admin</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/create_staff.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Create Staff</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/view_all_staff.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Staff</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/assign_teachers.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Assign Teachers</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/department_management.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Department Management</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/library_dashboard.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Library Management</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_clubs.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Clubs</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_competitions.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Competitions</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_documents.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Documents</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_cultural_programs.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Cultural Programs</a>
                </div>
            </div>
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
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_scholarships.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Scholarships</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/assign_scholarship.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Applications</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/upload_achievement.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Upload Achievements</a>
                </div>
            </div>

            <!-- Accordion: Student Fees -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700">
                    <span class="flex items-center"><svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8.433 7.418c.158-.103.346-.196.567-.267v1.698a2.5 2.5 0 00-1.168-.217c-1.36.0-2.5 1.12-2.5 2.5 0 .274.044.54.128.793l.363-1.318A2.5 2.5 0 018.433 7.418zM11 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0 2a10 10 0 100-20 10 10 0 000 20z" clip-rule="evenodd" />
                        </svg>Student Fees</span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div class="accordion-content pl-6 space-y-1">
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_fees.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Fee Structure</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/assign_student_fees.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Assign Fees</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/add_bulk_fees.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Add Bulk Fees</a>
                </div>
            </div>

            <!-- Accordion: Staff Payroll -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700">
                    <span class="flex items-center"><svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm12 6a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4a2 2 0 012-2h8zm-2 4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                        </svg>Staff Payroll</span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div class="accordion-content pl-6 space-y-1">
                    <a href="<?php echo $webroot_path; ?>super_admin/create_salary.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Generate Salary</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/view_staff_salaries.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">View Salaries</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_income.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Income </a>
                    <a href="<?php echo $webroot_path; ?>super_admin/manage_expenses.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Expenses</a>

                </div>
            </div>

            <!-- Accordion: Transport -->
            <div class="accordion">
                <button class="accordion-header flex items-center justify-between w-full px-3 py-2 text-left rounded-md hover:bg-gray-700">
                    <span class="flex items-center"><svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.34 3.03a1 1 0 01.66 1.869l-1 5A1 1 0 019.22 11H3a1 1 0 01-1-1V5a1 1 0 011-1h1.22a1 1 0 01.999.78l1.328 4.218 2.82-2.82a1 1 0 011.414 0l1.558 1.558a1 1 0 010 1.414l-3.333 3.333a1 1 0 01-1.414 0l-1.5-1.5a1 1 0 01-.05-1.46L6.22 5H7a1 1 0 010-2h4.34zM16 5a1 1 0 100-2 1 1 0 000 2zM18 7a1 1 0 100-2 1 1 0 000 2zM16 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>Transport</span>
                    <svg class="h-5 w-5 transform transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div class="accordion-content pl-6 space-y-1">
                    <a href="<?php echo $webroot_path; ?>super_admin/create_van.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Create Van</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/view_vans.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Vans</a>
                </div>
            </div>

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
                    <a href="<?php echo $webroot_path; ?>super_admin/indiscipline.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Indiscipline</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_assignments.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Assignments</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_attendance_report.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Attendance Report</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_marksheet_report.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Student Marksheets</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_notices.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Notices</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/leave_management.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Leave Management</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_helpdesk.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Helpdesk Tickets</a>
                </div>
            </div>

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
                    <a href="<?php echo $webroot_path; ?>super_admin/scman.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Homepage Banner</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/adsettings.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Homepage About Section</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/acdsettings.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Homepage Academics</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/adfact.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Admissions Section</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/gallery_settings.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Website Gallery</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/achievements.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Website Achievements</a>
                </div>
            </div>

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
                    <a href="<?php echo $webroot_path; ?>super_admin/academics.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Academics Page</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/about_mednova_settings.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">About Us Page</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/events_news.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Events & News Page</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_contact.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Contact Page Details</a>
                    <a href="<?php echo $webroot_path; ?>super_admin/admin_inquiries.php" class="nav-link block px-3 py-2 rounded-md hover:bg-gray-600">Manage Inquiries</a>
                </div>
            </div>
        </nav>

        <!-- Logout Link: Pinned to the bottom -->
        <div class="mt-auto shrink-0">
            <a href="<?php echo $webroot_path; ?>logout.php" class="flex items-center px-3 py-2 rounded-md text-red-400 hover:bg-gray-700 hover:text-red-300">
                <svg class="h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                </svg>
                Logout
            </a>
        </div>
    </div>
</div>

<!-- PART 3: CSS STYLING
============================================================================= -->
<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #2d3748;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #4a5568;
        border-radius: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #718096;
    }

    .accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out;
    }

    .accordion-header.active+.accordion-content {
        max-height: 500px;
    }

    .accordion-header.active svg:last-child {
        transform: rotate(180deg);
    }

    .nav-link.active {
        background-color: #4a5568;
        color: white;
    }
</style>

<!-- PART 4: JAVASCRIPT FUNCTIONALITY
============================================================================= -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleOpenBtn = document.getElementById('admin-sidebar-toggle-open');
        const toggleCloseBtn = document.getElementById('admin-sidebar-toggle-close');
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('admin-sidebar-overlay');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-50');
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            overlay.classList.remove('opacity-50');
        }

        if (toggleOpenBtn) toggleOpenBtn.addEventListener('click', openSidebar);
        if (toggleCloseBtn) toggleCloseBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);

        const accordions = document.querySelectorAll('.accordion-header');
        accordions.forEach(accordion => {
            accordion.addEventListener('click', () => {
                accordion.classList.toggle('active');
            });
        });

        const currentPage = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            const linkPath = new URL(link.href).pathname;
            if (currentPage === linkPath) {
                link.classList.add('active');
                const parentAccordion = link.closest('.accordion');
                if (parentAccordion) {
                    parentAccordion.querySelector('.accordion-header').classList.add('active');
                }
            }
        });
    });
</script>