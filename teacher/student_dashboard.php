<?php
// student_dashboard.php

// ALWAYS start the session at the very beginning of the script
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Authorization check: Ensure user is logged in and has 'Teacher' role
// Using strtolower for case-insensitive role comparison
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || (isset($_SESSION["role"]) && strtolower($_SESSION["role"]) !== 'teacher')) {
    // Log attempted unauthorized access for security
    error_log("Unauthorized access attempt to student_dashboard.php by user_id: " . ($_SESSION['user_id'] ?? 'N/A') . " with role: " . ($_SESSION['role'] ?? 'N/A'));
    header("location: ../login.php");
    exit;
}

// IMPORTANT: Define $webroot_path here or preferably from a central config file
// If you move it to a config file, make sure to include that config file here.
$webroot_path = '/new school/'; // MAKE SURE THIS IS CORRECT

// --- API MODE ---
// Handles AJAX requests for student dashboard data
if (isset($_GET['action']) && $_GET['action'] === 'dashboard') {
    header('Content-Type: application/json');

    // IMPORTANT: Adjust this path to your actual database configuration file.
    require_once "../database/config.php";

    $response = ['status' => 'error', 'message' => 'Invalid request.'];
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';

    if (empty($query)) {
        echo json_encode(['status' => 'error', 'message' => 'No query provided.']);
        exit;
    }

    $student_id = null;
    $student_sql = "SELECT id FROM students WHERE registration_number LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? LIMIT 1";
    if ($stmt = mysqli_prepare($link, $student_sql)) {
        $search_param = "%" . $query . "%";
        mysqli_stmt_bind_param($stmt, "ss", $search_param, $search_param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $student_id = $row['id'];
        }
        mysqli_stmt_close($stmt);
    }

    if (!$student_id) {
        echo json_encode(['status' => 'not_found', 'message' => 'No student found.']);
        exit;
    }

    $dashboard_data = [];

    // 1. Get Full Student Details (including van info)
    $details_sql = "SELECT s.*, c.class_name, c.section_name, v.van_number
                    FROM students s
                    LEFT JOIN classes c ON s.class_id = c.id
                    LEFT JOIN vans v ON s.van_id = v.id
                    WHERE s.id = ?";
    if ($stmt = mysqli_prepare($link, $details_sql)) {
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $details = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        // Format dates for better display and handle potential nulls
        $details['dob_formatted'] = !empty($details['dob']) ? date('d M, Y', strtotime($details['dob'])) : 'N/A';
        $details['admission_date_formatted'] = !empty($details['admission_date']) ? date('d M, Y', strtotime($details['admission_date'])) : 'N/A';
        $dashboard_data['details'] = $details;
        mysqli_stmt_close($stmt);
    }

    // 2. Get Attendance Summary
    $attendance_sql = "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present FROM attendance WHERE student_id = ?";
    if ($stmt = mysqli_prepare($link, $attendance_sql)) {
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $total = (int)($summary['total'] ?? 0);
        $present = (int)($summary['present'] ?? 0);
        $dashboard_data['attendance_summary'] = [
            'total_days_marked' => $total, 'present_days' => $present,
            'attendance_percentage' => $total > 0 ? round(($present / $total) * 100) : 0
        ];
        mysqli_stmt_close($stmt);
    }

    // 3. Get Fee Summary
    $fee_sql = "SELECT SUM(amount_due) AS total_due, SUM(amount_paid) AS total_paid, SUM(CASE WHEN status != 'Paid' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue FROM student_fees WHERE student_id = ?";
    if ($stmt = mysqli_prepare($link, $fee_sql)) {
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $due = (float)($summary['total_due'] ?? 0);
        $paid = (float)($summary['total_paid'] ?? 0);
        $dashboard_data['fee_summary'] = [
            'total_due' => $due, 'total_paid' => $paid, 'balance' => $due - $paid,
            'overdue_installments' => (int)($summary['overdue'] ?? 0)
        ];
        mysqli_stmt_close($stmt);
    }

    // 4. Get Academic Summary
    $exams_sql = "SELECT em.marks_obtained, es.max_marks, s.subject_name, et.exam_name
                  FROM exam_marks em
                  JOIN exam_schedule es ON em.exam_schedule_id = es.id
                  JOIN subjects s ON es.subject_id = s.id
                  JOIN exam_types et ON es.exam_type_id = et.id
                  WHERE em.student_id = ?
                  ORDER BY es.exam_date DESC
                  LIMIT 5";
    if ($stmt = mysqli_prepare($link, $exams_sql)) {
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $exams = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        $total_obtained = 0;
        $total_max = 0;
        foreach ($exams as $exam) {
            $total_obtained += $exam['marks_obtained'];
            $total_max += $exam['max_marks'];
        }
        $dashboard_data['academic_summary'] = [
            'recent_exams' => $exams,
            'overall_percentage' => $total_max > 0 ? round(($total_obtained / $total_max) * 100) : 0
        ];
        mysqli_stmt_close($stmt);
    }

    mysqli_close($link);
    echo json_encode(['status' => 'success', 'data' => $dashboard_data]);
    exit; // IMPORTANT: Exit after sending JSON response
}

// --- DISPLAY MODE (HTML page) ---
// This part is executed if it's not an API request
require_once './teacher_header.php'; // This file should contain <html>, <head>, <body> tags, and possibly the sidebar.
?>

    <div class="max-w-6xl mt-28 mb-28 mx-auto p-4"> <!-- Added p-4 for consistent padding -->
        <div class="relative mb-6">
            <h1 class="text-3xl font-bold text-slate-800 text-center mb-4">Student Comprehensive Dashboard</h1>
            <div class="relative">
                <input type="search" id="student-search" placeholder="Enter student name or registration number..."
                       class="w-full pl-12 pr-4 py-3 text-lg border border-slate-300 rounded-full shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                       aria-label="Search for a student by name or registration number">
                <i class="fas fa-search text-slate-400 absolute left-4 top-1/2 -translate-y-1/2 text-xl" aria-hidden="true"></i>
            </div>
        </div>
        <div id="dashboard-container">
            <div class="text-center text-slate-500 p-10 bg-white rounded-xl shadow-sm" role="status">
                <i class="fas fa-user-graduate fa-4x mb-4 text-slate-300" aria-hidden="true"></i>
                <p class="text-lg">Enter a student's details to view their complete dashboard.</p>
            </div>
        </div>
    </div>

<script>
    const searchInput = document.getElementById('student-search');
    const dashboardContainer = document.getElementById('dashboard-container');
    let searchTimeout;

    // --- TEMPLATES ---
    const skeletonTemplate = `
        <div class="fade-in-up" aria-live="polite" aria-busy="true" role="status">
            <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-6 mb-6">
                <div class="w-24 h-24 rounded-full skeleton"></div>
                <div class="flex-1 space-y-3">
                    <div class="h-6 w-1/2 skeleton"></div>
                    <div class="h-4 w-1/3 skeleton"></div>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-md h-48 skeleton"></div>
                    <div class="bg-white p-6 rounded-xl shadow-md h-48 skeleton"></div>
                </div>
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
                    <div class="h-8 w-full skeleton mb-4"></div>
                    <div class="h-64 skeleton"></div>
                </div>
            </div>
        </div>`;

    const notFoundTemplate = `
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-5 rounded-lg fade-in-up shadow" role="alert">
            <p class="font-bold text-lg">Student Not Found</p>
            <p>Please check the name or registration number and try again.</p>
        </div>`;

    // Helper functions for dynamic content
    const getProgressBarColor = p => p >= 85 ? 'bg-teal-500' : p >= 60 ? 'bg-sky-500' : p >= 40 ? 'bg-yellow-500' : 'bg-red-500';
    const getGrade = p => {
        if (p >= 91) return 'A1';
        if (p >= 81) return 'A2';
        if (p >= 71) return 'B1';
        if (p >= 61) return 'B2';
        if (p >= 51) return 'C1';
        if (p >= 41) return 'C2';
        if (p >= 33) return 'D';
        return 'E';
    };
    const detailItem = (icon, label, value) => `
        <div class="flex items-start py-2">
            <i class="fas ${icon} text-slate-400 w-5 pt-1" aria-hidden="true"></i>
            <div class="ml-4">
                <p class="text-sm text-slate-500">${label}</p>
                <p class="font-semibold text-slate-800">${value || 'N/A'}</p>
            </div>
        </div>`;

    const dashboardTemplate = (data) => `
        <div class="fade-in-up" aria-live="polite">
            <!-- Header -->
            <header class="bg-white p-5 sm:p-6 rounded-xl shadow-md flex flex-col sm:flex-row items-center gap-6 mb-6">
                <img class="w-24 h-24 rounded-full border-4 border-indigo-200 object-cover" src="${data.details.image_url || '<?php echo $webroot_path; ?>assets/images/default-avatar.png'}" alt="Photo of ${data.details.first_name} ${data.details.last_name}">
                <div class="text-center sm:text-left">
                    <h2 class="text-3xl font-bold text-slate-800">${data.details.first_name} ${data.details.last_name}</h2>
                    <p class="text-slate-500 text-lg">${data.details.class_name} - ${data.details.section_name}</p>
                    <p class="text-sm text-slate-400">Reg. No: ${data.details.registration_number}</p>
                </div>
                <span class="ml-0 sm:ml-auto mt-2 sm:mt-0 px-4 py-1.5 text-sm font-semibold rounded-full ${data.details.status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${data.details.status}</span>
            </header>

            <!-- Main Grid -->
            <main class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <aside class="lg:col-span-1 space-y-6">
                    <!-- Attendance -->
                    <div class="bg-white p-6 rounded-xl shadow-md" aria-labelledby="attendance-heading">
                        <h3 id="attendance-heading" class="text-xl font-bold text-slate-700 mb-4 flex items-center">
                            <i class="fas fa-user-check mr-3 text-indigo-500" aria-hidden="true"></i>Attendance
                        </h3>
                        <div class="text-center mb-4">
                            <span class="text-5xl font-bold text-slate-800">${data.attendance_summary.attendance_percentage}%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-4 mb-2" role="progressbar" aria-valuenow="${data.attendance_summary.attendance_percentage}" aria-valuemin="0" aria-valuemax="100">
                            <div class="${getProgressBarColor(data.attendance_summary.attendance_percentage)} h-4 rounded-full" style="width: ${data.attendance_summary.attendance_percentage}%"></div>
                        </div>
                        <div class="flex justify-between text-sm font-medium text-slate-600">
                            <span>Present: ${data.attendance_summary.present_days}</span>
                            <span>Total: ${data.attendance_summary.total_days_marked}</span>
                        </div>
                    </div>
                    <!-- Fees -->
                    <div class="bg-white p-6 rounded-xl shadow-md" aria-labelledby="fees-heading">
                        <h3 id="fees-heading" class="text-xl font-bold text-slate-700 mb-4 flex items-center">
                            <i class="fas fa-receipt mr-3 text-indigo-500" aria-hidden="true"></i>Fees
                        </h3>
                        <div class="space-y-3 text-slate-700">
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Total Due:</span>
                                <span class="font-bold text-lg">₹${data.fee_summary.total_due.toFixed(2)}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Paid:</span>
                                <span class="font-bold text-green-600 text-lg">₹${data.fee_summary.total_paid.toFixed(2)}</span>
                            </div>
                            <hr class="my-2">
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Balance:</span>
                                <span class="font-bold text-xl ${data.fee_summary.balance > 0 ? 'text-red-600' : 'text-slate-800'}">₹${data.fee_summary.balance.toFixed(2)}</span>
                            </div>
                            ${data.fee_summary.overdue_installments > 0 ? `
                            <div class="mt-3 text-center bg-red-100 text-red-700 p-2 rounded-lg text-sm font-semibold" role="alert">
                                ${data.fee_summary.overdue_installments} installment(s) overdue!
                            </div>` : ''}
                        </div>
                    </div>
                </aside>
                <!-- Right Column with Tabs -->
                <section class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
                    <nav class="flex border-b mb-6" role="tablist">
                        <button data-tab="academics" class="tab-button active py-2 px-4 sm:px-6 font-semibold border-b-2 transition" role="tab" aria-controls="tab-academics" aria-selected="true">Academics</button>
                        <button data-tab="personal" class="tab-button py-2 px-4 sm:px-6 font-semibold border-b-2 border-transparent text-slate-500 hover:text-indigo-500 transition" role="tab" aria-controls="tab-personal" aria-selected="false">Personal Info</button>
                        <button data-tab="guardian" class="tab-button py-2 px-4 sm:px-6 font-semibold border-b-2 border-transparent text-slate-500 hover:text-indigo-500 transition" role="tab" aria-controls="tab-guardian" aria-selected="false">Guardian</button>
                        <button data-tab="school" class="tab-button py-2 px-4 sm:px-6 font-semibold border-b-2 border-transparent text-slate-500 hover:text-indigo-500 transition" role="tab" aria-controls="tab-school" aria-selected="false">School Info</button>
                    </nav>

                    <div id="tab-academics" class="tab-content active" role="tabpanel" aria-labelledby="academics-tab">
                        <div class="text-center bg-indigo-50 p-4 rounded-lg mb-5">
                            <p class="text-slate-500">Recent Exam Performance</p>
                            <span class="text-5xl font-bold text-indigo-600">${data.academic_summary.overall_percentage}%</span>
                            <span class="ml-2 text-2xl font-semibold text-slate-600">(Grade: ${getGrade(data.academic_summary.overall_percentage)})</span>
                        </div>
                        <h4 class="font-semibold text-slate-600 mb-2">Latest Exam Results:</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-600">
                                        <th class="p-2 text-left">Exam</th>
                                        <th class="p-2 text-left">Subject</th>
                                        <th class="p-2 text-center">Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.academic_summary.recent_exams.length > 0 ? data.academic_summary.recent_exams.map(e => `
                                    <tr class="border-b">
                                        <td class="p-2">${e.exam_name}</td>
                                        <td class="p-2 font-medium">${e.subject_name}</td>
                                        <td class="p-2 text-center font-bold">${e.marks_obtained}/${e.max_marks}</td>
                                    </tr>`).join('') : `<tr><td colspan="3" class="p-4 text-center text-slate-500">No exam data available.</td></tr>`}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="tab-personal" class="tab-content grid grid-cols-1 sm:grid-cols-2 gap-x-6" role="tabpanel" aria-labelledby="personal-tab" hidden>
                        ${detailItem('fa-cake-candles', 'Date of Birth', data.details.dob_formatted)}
                        ${detailItem('fa-venus-mars', 'Gender', data.details.gender)}
                        ${detailItem('fa-droplet', 'Blood Group', data.details.blood_group)}
                        ${detailItem('fa-phone', 'Phone', data.details.phone_number)}
                        ${detailItem('fa-envelope', 'Email', data.details.email)}
                    </div>
                    <div id="tab-guardian" class="tab-content grid grid-cols-1 sm:grid-cols-2 gap-x-6" role="tabpanel" aria-labelledby="guardian-tab" hidden>
                        ${detailItem('fa-user-tie', "Father's Name", data.details.father_name)}
                        ${detailItem('fa-briefcase', "Father's Occupation", data.details.father_occupation)}
                        ${detailItem('fa-person-dress', "Mother's Name", data.details.mother_name)}
                        ${detailItem('fa-mobile-screen-button', 'Parent Phone', data.details.parent_phone_number)}
                        <div class="sm:col-span-2">
                            ${detailItem('fa-location-dot', 'Address', `${data.details.address}, ${data.details.district}, ${data.details.state} - ${data.details.pincode}`)}
                        </div>
                    </div>
                    <div id="tab-school" class="tab-content grid grid-cols-1 sm:grid-cols-2 gap-x-6" role="tabpanel" aria-labelledby="school-tab" hidden>
                        ${detailItem('fa-hashtag', 'Roll Number', data.details.roll_number)}
                        ${detailItem('fa-calendar-check', 'Admission Date', data.details.admission_date_formatted)}
                        ${detailItem('fa-school-flag', 'Previous School', data.details.previous_school)}
                        ${detailItem('fa-van-shuttle', 'Van Service', data.details.van_service_taken == 1 ? 'Yes' : 'No')}
                        ${data.details.van_service_taken == 1 ? detailItem('fa-id-card', 'Van Number', data.details.van_number) : ''}
                    </div>
                </section>
            </main>
        </div>
    `;

    // --- EVENT LISTENERS ---
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        const query = searchInput.value.trim();
        if (query.length < 2) {
            dashboardContainer.innerHTML = `<div class="text-center text-slate-500 p-10 bg-white rounded-xl shadow-sm" role="status"><i class="fas fa-user-graduate fa-4x mb-4 text-slate-300"></i><p class="text-lg">Enter a student's details to view their complete dashboard.</p></div>`;
            return;
        }
        dashboardContainer.innerHTML = skeletonTemplate;
        searchTimeout = setTimeout(() => fetchDashboardData(query), 500);
    });

    dashboardContainer.addEventListener('click', (e) => {
        if (e.target.matches('.tab-button')) {
            const clickedButton = e.target;
            const tabId = clickedButton.dataset.tab;
            const container = clickedButton.closest('section'); // Get the parent section containing tabs

            // Deactivate all buttons and hide all content
            container.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
            });
            container.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
                content.setAttribute('hidden', 'true');
            });

            // Activate the clicked button and show its content
            clickedButton.classList.add('active');
            clickedButton.setAttribute('aria-selected', 'true');
            container.querySelector(`#tab-${tabId}`).classList.add('active');
            container.querySelector(`#tab-${tabId}`).removeAttribute('hidden');
        }
    });

    async function fetchDashboardData(query) {
        try {
            const response = await fetch(`<?php echo $webroot_path; ?>teacher/student_dashboard.php?action=dashboard&query=${encodeURIComponent(query)}`); // Ensure correct path for AJAX
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.status === 'success') {
                dashboardContainer.innerHTML = dashboardTemplate(result.data);
            } else {
                dashboardContainer.innerHTML = notFoundTemplate;
            }
        } catch (error) {
            console.error('Fetch Error:', error);
            dashboardContainer.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-5 rounded-lg shadow" role="alert"><p><strong>Error:</strong> Could not retrieve student data. Please try again later or contact support.</p><p>Details: ${error.message}</p></div>`;
        }
    }

    // Initial load/clear state
    if (searchInput.value.trim() === '') {
        dashboardContainer.innerHTML = `<div class="text-center text-slate-500 p-10 bg-white rounded-xl shadow-sm" role="status"><i class="fas fa-user-graduate fa-4x mb-4 text-slate-300"></i><p class="text-lg">Enter a student's details to view their complete dashboard.</p></div>`;
    }
</script>

<?php
require_once './teacher_footer.php'; // This file should contain </body> and </html> tags.
?>