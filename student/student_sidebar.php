<?php
// student_sidebar.php - Full Enhanced Version
?>

<style>
/* ---------------- Sidebar Base ---------------- */
.student-sidebar {
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
body.sidebar-open .student-sidebar {
    left: 0;
}

/* Content push */
@media (min-width: 768px) {
    body.sidebar-open .main-content {
        margin-left: 280px;
        transition: margin-left 0.4s ease-in-out;
    }
}

/* ---------------- List and Links ---------------- */
.student-sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.student-sidebar li {
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.student-sidebar a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #a0aec0;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.student-sidebar a:hover {
    background-color: #2d3748;
    color: #fff;
    padding-left: 26px;
}

.student-sidebar a.active, .student-sidebar a.current-page {
    background: linear-gradient(90deg, #4c51bf, #6366f1);
    color: #fff;
    border-left: 4px solid #4299e1;
}

/* ---------------- Section Headings ---------------- */
.sidebar-section, .menu-section-heading {
    padding: 12px 20px;
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #718096;
    letter-spacing: 1px;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.menu-section-heading svg {
    transition: transform 0.3s ease;
}

.menu-section-heading.collapsed svg {
    transform: rotate(-90deg);
}

/* ---------------- Collapsible Submenu ---------------- */
.submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease-in-out;
}

.submenu li a {
    padding-left: 36px;
    font-size: 0.9rem;
}

/* ---------------- Icons ---------------- */
.student-sidebar a svg {
    height: 20px;
    width: 20px;
    fill: currentColor;
    flex-shrink: 0;
}

/* ---------------- Scrollbar ---------------- */
.student-sidebar::-webkit-scrollbar {
    width: 6px;
}
.student-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.15);
    border-radius: 6px;
}

/* ---------------- Toggle Button ---------------- */
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
@media (min-width: 1024px) {
    .sidebar-toggle {
        display: none;
    }
}
</style>

<!-- Toggle Button -->
<button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" 
         viewBox="0 0 24 24" width="24" height="24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<!-- Sidebar -->
<nav class="student-sidebar" aria-label="Student Sidebar">
    <ul>
        <!-- Academics -->
        <div class="menu-section-heading">🎓 Academics</div>
        <li><a href="student_dashboard.php" class="sidebar-item-link current-page">🏠 Dashboard</a></li>
                <li><a href="student_dashboard_academics.php" class="sidebar-item-link current-page">🏠 Student Academics</a></li>
        <li><a href="student_dashboard_welfare_activities.php" class="sidebar-item-link current-page">🏠 Dashboard Activities</a></li>
        <li><a href="student_courses.php" class="sidebar-item-link">📚 My Courses / Subjects</a></li>
        <li><a href="student_assignments.php" class="sidebar-item-link">📝 Assignments / Homework</a></li>
        <li><a href="student_exams.php" class="sidebar-item-link">🧾 Exams / Results</a></li>
        <li><a href="student_attendance.php" class="sidebar-item-link">🎓 Attendance</a></li>
        <li><a href="student_timetable.php" class="sidebar-item-link">📅 Timetable / Schedule</a></li>
        <li><a href="student_study_materials.php" class="sidebar-item-link">📂 Study Materials / Notes</a></li>
        <li><a href="student_tests.php" class="sidebar-item-link">🧮 Online Tests / Quizzes</a></li>
        <li><a href="student_achievements.php" class="sidebar-item-link">🏆 Achievements / Certificates</a></li>
        <li><a href="faculty_directory.php" class="sidebar-item-link">🧑‍🏫 Faculty Directory</a></li>

        <!-- Administration -->
        <div class="menu-section-heading">🏫 Administration</div>
        <li><a href="fee_payment.php" class="sidebar-item-link">💳 Fee Payment / Dues</a></li>
        <li><a href="applications.php" class="sidebar-item-link">📑Leave Applications</a></li>
        <li><a href="id_profile.php" class="sidebar-item-link">🪪 ID Card / Profile</a></li>
        <li><a href="library.php" class="sidebar-item-link">🔖 Library</a></li>
        <li><a href="hostel.php" class="sidebar-item-link">🏫 Hostel / Accommodation</a></li>
        <li><a href="canteen.php" class="sidebar-item-link">🍴 Canteen / Meal Plan</a></li>
        <li><a href="transport.php" class="sidebar-item-link">🚍 Transport / Bus Pass</a></li>
        <li><a href="student_helpdesk.php" class="sidebar-item-link">🛠 Support / Help Desk</a></li>

        <!-- Communication -->
        <div class="menu-section-heading">📢 Communication</div>
        <li><a href="student_notices.php" class="sidebar-item-link">📢 Notices / Announcements</a></li>
        <li><a href="student_messages.php" class="sidebar-item-link">💬 Messages / Chat</a></li>
        <li><a href="student_forum.php" class="sidebar-item-link">📡 Forum / Discussion Board</a></li>
        <li><a href="contact_faculty.php" class="sidebar-item-link">📞 Contact Faculty / Staff</a></li>

        <!-- Activities -->
        <div class="menu-section-heading">🎭 Activities & Engagement</div>
        <li><a href="events.php" class="sidebar-item-link">🎭 Events / Activities</a></li>
        <li><a href="sports_clubs.php" class="sidebar-item-link">🏀 Sports / Clubs</a></li>
        <li><a href="cultural_programs.php" class="sidebar-item-link">🎤 Cultural Programs</a></li>
        <li><a href="competitions.php" class="sidebar-item-link">📊 Competitions / Hackathons</a></li>

        <!-- Personal -->
        <div class="menu-section-heading">👤 Personal</div>
        <li><a href="my_profile.php" class="sidebar-item-link">👤 My Profile</a></li>
        <li><a href="performance.php" class="sidebar-item-link">📊 Performance / Progress Report</a></li>
        <li><a href="documents.php" class="sidebar-item-link">📂 My Documents</a></li>

        <!-- System -->
        <div class="menu-section-heading">⚙️ System</div>
        <li><a href="change_password.php" class="sidebar-item-link">🔐 Change Password</a></li>
         <li><a href="../logout.php" class="sidebar-item-link">🚪 Logout</a></li>
    </ul>
</nav>


<script>
function toggleSidebar() {
    document.body.classList.toggle("sidebar-open");
}

// Collapsible Submenus
function toggleSubmenu(id) {
    const submenu = document.getElementById(id);
    const heading = submenu.previousElementSibling;
    submenu.classList.toggle('open');
    if(submenu.classList.contains('open')){
        submenu.style.maxHeight = submenu.scrollHeight + "px";
        heading.classList.remove('collapsed');
    } else {
        submenu.style.maxHeight = null;
        heading.classList.add('collapsed');
    }
}

// Auto-open submenu if current page is inside
document.addEventListener('DOMContentLoaded', () => {
    const links = document.querySelectorAll('.sidebar-item-link');
    links.forEach(link => {
        if(link.href === window.location.href){
            link.classList.add('current-page');
            const submenu = link.closest('.submenu');
            if(submenu){
                toggleSubmenu(submenu.id);
            }
        }
    });
});
</script>
