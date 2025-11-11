<?php
// School/teacher/teacher_sidebar.php
// Enhanced Sidebar with grouping and responsive design
?>

<style>
    /* Sidebar Base */
    .teacher-sidebar {
        position: fixed;
        top: 0;
        left: -280px;
        height: 100%;
        width: 280px;
        background-color: #1a202c;
        color: #e2e8f0;
        transition: left 0.4s ease-in-out;
        z-index: 40;
        padding-top: 70px;
        overflow-y: auto;
        box-shadow: 2px 0 6px rgba(0,0,0,0.25);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Sidebar open */
    body.sidebar-open .teacher-sidebar {
        left: 0;
    }

    /* Content push */
    @media (min-width: 768px) {
        body.sidebar-open .main-content {
            margin-left: 280px;
            transition: margin-left 0.4s ease-in-out;
        }
    }

    /* List base */
    .teacher-sidebar ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .teacher-sidebar li {
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    /* Links */
    .teacher-sidebar a {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 22px;
        color: #a0aec0;
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .teacher-sidebar a:hover {
        background-color: #2d3748;
        color: #ffffff;
        padding-left: 28px; /* smooth hover slide */
    }

    /* Active link */
    .teacher-sidebar a.active {
        background: linear-gradient(90deg, #4c51bf, #6366f1);
        color: #fff;
        border-left: 4px solid #4299e1;
    }

    /* Sidebar Section Title */
    .sidebar-section {
        padding: 12px 20px;
        font-size: 0.8rem;
        text-transform: uppercase;
        color: #718096;
        letter-spacing: 1px;
        font-weight: 600;
    }

    /* SVG Icons */
    .teacher-sidebar a svg {
        height: 20px;
        width: 20px;
        fill: currentColor;
        flex-shrink: 0;
    }

    /* Scrollbar styling */
    .teacher-sidebar::-webkit-scrollbar {
        width: 6px;
    }
    .teacher-sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.15);
        border-radius: 6px;
    }

    /* Toggle button */
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
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s;
    }
    .sidebar-toggle:hover {
        background: #2d3748;
    }

    /* Hide toggle on desktop if sidebar is pinned */
    @media (min-width: 1024px) {
        .sidebar-toggle {
            display: none;
        }
    }
</style>

<!-- Toggle Button -->
<button class="sidebar-toggle" onclick="toggleSidebar()">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" 
         viewBox="0 0 24 24" width="24" height="24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<!-- Sidebar -->
<nav class="teacher-sidebar">
    <ul>
        <!-- Dashboard -->
        <div class="sidebar-section">Main</div>
        <li><a href="dashboard.php" class="active">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Dashboard
        </a></li>
        <li><a href="teacher_profile.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4zm0 2c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4z"/></svg>
            My Profile
        </a></li>

        <!-- Academics -->
        <div class="sidebar-section">Academics</div>
        <li><a href="student_dashboard.php">👨‍🏫 student Dashboard</a></li>
        <li><a href="indiscipline.php">👨‍🏫 manage_misconduct</a></li>
        <li><a href="exam_dashboard.php">👨‍🏫 Exam Dashboard</a></li>
        <li><a href="teacher_classes.php">📚 Classes</a></li>
        <li><a href="teacher_exams.php">📝 Exams</a></li>
        <li><a href="teacher_assignments.php">📂 Assignments</a></li>
        <li><a href="teacher_attendance_report.php">📊 Attendance Report</a></li>
        <li><a href="teacher_upload_marks.php">📑 Upload Marks</a></li>
        <li><a href="teacher_class_report.php">📘 Class Report</a></li>
        <li><a href="teacher_class_marksheet.php">📘 Class Marksheet</a></li>
        <li><a href="teacher_timetable.php">📅 Timetable</a></li>
        <li><a href="teacher_attendance.php">✅ Attendance</a></li>
        <li><a href="teacher_library.php">✅ teacher_library</a></li>



        <!-- Communication -->
        <div class="sidebar-section">Communication</div>
        <li><a href="teacher_notices.php">💬 notice</a></li>
        <li><a href="teacher_study_materials.php">📖 Study Materials</a></li>
        <li><a href="teacher_leave_management.php">🛎 Leave Management</a></li>
        <li><a href="teacher_helpdesk.php">🆘 Helpdesk</a></li>
        <li><a href="chat.php">👨‍🏫 Teacher Chat</a></li>
        <li><a href="teacher_messages.php">👨‍🏫 student Chat</a></li>
        <li><a href="student_forum.php">👨‍🏫  student_forum</a></li>
        <li><a href="teacher_clubs.php">👨‍🏫  teacher_clubs</a></li>
        <li><a href="teacher_competitions.php">👨‍🏫  teacher_competitions</a></li>
        <li><a href="teacher_cultural_programs.php">👨‍🏫  teacher_cultural_programs</a></li>

        <!-- Tests -->
        <div class="sidebar-section">Tests</div>
        <li><a href="teacher_manage_tests.php">⚙ Manage Tests</a></li>
        <li><a href="teacher_create_test.php">➕ Create Test</a></li>

        <!-- Utilities -->
        <div class="sidebar-section">Utilities</div>
        <li><a href="teacher_settings.php">⚙ Settings</a></li>
        <li><a href="../logout.php">🚪 Logout</a></li>
    </ul>
</nav>

<script>
    function toggleSidebar() {
        document.body.classList.toggle("sidebar-open");
    }
</script>
