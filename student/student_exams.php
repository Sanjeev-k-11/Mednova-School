<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./student_header.php"; // Includes authentication and sidebar

// Get the logged-in student's ID from the session
// CRITICAL FIX: Ensure this matches what your login system sets.
// It was previously $_SESSION['user_id'] in our discussion, but you reverted to $_SESSION['id'].
// I'm using $_SESSION['user_id'] as it's a more common and robust key.
// If your actual session variable is $_SESSION['id'], please change 'user_id' back to 'id' here.
$student_id = $_SESSION['id'] ?? null;
$exam_type_id = $_SESSION['id'] ?? null;
// --- CRITICAL CHECK: Validate student_id from session ---
if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    // If student_id is not set, not numeric, or invalid
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Authentication Error!</strong>
            <span class='block sm:inline'> Your student ID is missing or invalid in the session. Please log in again.</span>
          </div>";
    // Exit script to prevent further errors
    require_once "./student_footer.php";
    if ($link) mysqli_close($link);
    exit();
}

// --- Fetch Student Profile Details ---
$student_profile = null;
$student_class_id = 0; // Initialize for later use

$sql_student_profile = "
    SELECT
        s.id AS student_db_id,
        s.registration_number,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.class_id,
        c.class_name,
        c.section_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
";

// Prepare the statement
if ($stmt = mysqli_prepare($link, $sql_student_profile)) {
    // Bind the student ID parameter
    mysqli_stmt_bind_param($stmt, "i", $student_id);

    // Execute the statement and check for success
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $student_profile = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // If student profile is fetched successfully, set student_class_id
        if ($student_profile) {
            $student_class_id = $student_profile['class_id'];
        } else {
            // This block is reached if the query executed but returned no rows.
            // This means either the student_id doesn't exist, or their class_id is invalid/missing.
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                    <strong class='font-bold'>Error:</strong>
                    <span class='block sm:inline'> Student profile not found for ID: " . htmlspecialchars($student_id) . ". This ID might be invalid or deleted, or the assigned class might not exist.</span>
                  </div>";
            require_once "./student_footer.php";
            if ($link) mysqli_close($link);
            exit();
        }
    } else {
        // Handle execution error
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>Database Error!</strong>
                <span class='block sm:inline'> Could not execute student profile query: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</span>
              </div>";
        mysqli_stmt_close($stmt); // Ensure statement is closed on error
        require_once "./student_footer.php";
        if ($link) mysqli_close($link);
        exit();
    }
} else {
    // Handle preparation error (e.g., SQL syntax error in the query itself)
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Database Error!</strong>
            <span class='block sm:inline'> Could not prepare student profile query: " . htmlspecialchars(mysqli_error($link)) . "</span>
          </div>";
    require_once "./student_footer.php";
    if ($link) mysqli_close($link);
    exit();
}


$scheduled_exams = [];
$exam_results = [];
$exam_summary = [
    'total_exams' => 0,
    'passed_count' => 0,
    'failed_count' => 0,
    'average_marks' => 0,
    'pass_percentage' => 0
];

// The rest of your code relies on $student_class_id which is now guaranteed to be set
// if we reached this point, otherwise the script would have exited.

// --- Fetch Scheduled Exams for the student's class ---
$sql_scheduled_exams = "
    SELECT 
        es.exam_date,
        es.start_time,
        es.end_time,
        es.max_marks,
        es.passing_marks,
        et.exam_name,
        s.subject_name
    FROM exam_schedule es
    JOIN exam_types et ON es.exam_type_id = et.id
    JOIN subjects s ON es.subject_id = s.id
    WHERE es.class_id = ? AND es.exam_date >= CURDATE()
    ORDER BY es.exam_date ASC, es.start_time ASC
";
if ($stmt = mysqli_prepare($link, $sql_scheduled_exams)) {
    mysqli_stmt_bind_param($stmt, "i", $student_class_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $scheduled_exams[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>Database Error!</strong>
                <span class='block sm:inline'> Could not execute scheduled exams query: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</span>
              </div>";
        mysqli_stmt_close($stmt);
    }
} else {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Database Error!</strong>
            <span class='block sm:inline'> Could not prepare scheduled exams query: " . htmlspecialchars(mysqli_error($link)) . "</span>
          </div>";
}

// --- Fetch Exam Results for the logged-in student ---
// Fetch all exam results to calculate summary statistics
$sql_exam_results = "
    SELECT
        es.exam_date,
        es.start_time,
        es.max_marks,
        es.passing_marks,
        et.exam_name,
        s.subject_name,
        em.marks_obtained
    FROM exam_marks em
    JOIN exam_schedule es ON em.exam_schedule_id = es.id
    JOIN exam_types et ON es.exam_type_id = et.id
    JOIN subjects s ON es.subject_id = s.id
    WHERE em.student_id = ? AND es.exam_date <= CURDATE() -- FIX: Changed to <= CURDATE() to include today's exams
    ORDER BY es.exam_date DESC, es.start_time DESC
";
if ($stmt = mysqli_prepare($link, $sql_exam_results)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $exam_results[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>Database Error!</strong>
                <span class='block sm:inline'> Could not execute exam results query: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</span>
              </div>";
        mysqli_stmt_close($stmt);
    }
} else {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Database Error!</strong>
            <span class='block sm:inline'> Could not prepare exam results query: " . htmlspecialchars(mysqli_error($link)) . "</span>
          </div>";
}

// --- Calculate Exam Summary ---
if (!empty($exam_results)) {
    $total_marks_obtained = 0;
    $graded_count = 0;
    foreach ($exam_results as $result) {
        if ($result['marks_obtained'] !== null) {
            $exam_summary['total_exams']++;
            $graded_count++;
            $total_marks_obtained += $result['marks_obtained'];

            if ($result['marks_obtained'] >= $result['passing_marks']) {
                $exam_summary['passed_count']++;
            } else {
                $exam_summary['failed_count']++;
            }
        }
    }
    if ($graded_count > 0) {
        $exam_summary['average_marks'] = number_format($total_marks_obtained / $graded_count, 2);
        $exam_summary['pass_percentage'] = number_format(($exam_summary['passed_count'] / $graded_count) * 100, 2);
    }
}
?>

<div class="bg-gray-100 mt-28 mb-12 min-h-screen p-4 sm:p-6">
    <!-- Main Header Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Exams & Results Dashboard</h1>
        <p class="text-gray-600">Your one-stop view for upcoming exams and past performance.</p>
    </div>

    <!-- NEW: Student Profile Section -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Student Profile</h2>
        <?php if ($student_profile): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Student ID (DB)</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['student_db_id']); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Registration Number</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['registration_number']); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Full Name</p>
                    <p class="text-lg font-semibold text-gray-900">
                        <?php
                        echo htmlspecialchars($student_profile['first_name'] . ' ');
                        if (!empty($student_profile['middle_name'])) {
                            echo htmlspecialchars($student_profile['middle_name'] . ' ');
                        }
                        echo htmlspecialchars($student_profile['last_name']);
                        ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Class & Section</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['class_name'] . ' - ' . $student_profile['section_name']); ?></p>
                </div>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500">Student profile could not be loaded. (Check logs for details)</p>
        <?php endif; ?>
    </div>


    <!-- Exam Summary Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white transform transition duration-300 hover:scale-105">
            <h3 class="text-xl font-semibold opacity-85">Exams Taken</h3>
            <p class="text-4xl font-bold mt-2"><?php echo $exam_summary['total_exams']; ?></p>
        </div>
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-5 text-white transform transition duration-300 hover:scale-105">
            <h3 class="text-xl font-semibold opacity-85">Exams Passed</h3>
            <p class="text-4xl font-bold mt-2"><?php echo $exam_summary['passed_count']; ?></p>
        </div>
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg p-5 text-white transform transition duration-300 hover:scale-105">
            <h3 class="text-xl font-semibold opacity-85">Exams Failed</h3>
            <p class="text-4xl font-bold mt-2"><?php echo $exam_summary['failed_count']; ?></p>
        </div>
        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-5 text-white transform transition duration-300 hover:scale-105">
            <h3 class="text-xl font-semibold opacity-85">Avg. Marks</h3>
            <p class="text-4xl font-bold mt-2"><?php echo $exam_summary['average_marks']; ?>%</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Upcoming Exams Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 lg:col-span-2">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Upcoming Exams</h2>
            <?php if (empty($scheduled_exams)): ?>
                <div class="text-center p-6 border-2 border-dashed border-gray-300 rounded-xl text-gray-500">
                    <p class="text-lg">No upcoming exams scheduled at the moment.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Exam & Subject</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date & Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Marks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($scheduled_exams as $exam): ?>
                                <tr class="bg-white hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($exam['subject_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars(date('M d, Y', strtotime($exam['exam_date']))); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars(date('h:i A', strtotime($exam['start_time']))) . ' - ' . htmlspecialchars(date('h:i A', strtotime($exam['end_time']))); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                                            Max: <?php echo htmlspecialchars($exam['max_marks']); ?>
                                        </span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 ml-2 mt-1 md:mt-0">
                                            Pass: <?php echo htmlspecialchars($exam['passing_marks']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Exam Results Chart Section (Now a Doughnut Chart) -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Performance Overview</h2>
            <?php if ($exam_summary['total_exams'] == 0): ?>
                <div class="text-center p-6 border-2 border-dashed border-gray-300 rounded-xl text-gray-500">
                    <p class="text-lg">No graded exams to display for performance overview.</p>
                    <p class="text-sm">Results will appear here after grading.</p>
                </div>
            <?php else: ?>
                <div class="w-full max-w-xs mx-auto"> <!-- Added a container for better centering/sizing -->
                    <canvas id="examResultsDoughnutChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Exam Results Table -->
    <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">My Exam Results</h2>
        <?php if (empty($exam_results)): ?>
            <div class="text-center p-6 border-2 border-dashed border-gray-300 rounded-xl text-gray-500">
                <p class="text-lg">No exam results available yet.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Exam & Subject</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Marks</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($exam_results as $result): ?>
                            <?php
                            $marks_obtained = $result['marks_obtained'];
                            $max_marks = $result['max_marks'];
                            $passing_marks = $result['passing_marks'];
                            $exam_status = 'Pending';
                            $status_color_class = 'text-yellow-600 bg-yellow-100';
                            if ($marks_obtained !== null) {
                                if ($marks_obtained >= $passing_marks) {
                                    $exam_status = 'Passed';
                                    $status_color_class = 'text-green-600 bg-green-100';
                                } else {
                                    $exam_status = 'Failed';
                                    $status_color_class = 'text-red-600 bg-red-100';
                                }
                            }
                            ?>
                            <tr class="bg-white hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($result['exam_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($result['subject_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars(date('M d, Y', strtotime($result['exam_date']))); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo ($marks_obtained !== null) ? htmlspecialchars($marks_obtained) . ' / ' . htmlspecialchars($max_marks) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo $status_color_class; ?>">
                                        <?php echo htmlspecialchars($exam_status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="student_marksheet.php?exam_type_id=<?php echo $exam_type_id; ?>"
                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-500 text-white hover:bg-blue-600">
                                        View
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once "./student_footer.php"; // Closes the main-page-content div and HTML tags
if ($link) mysqli_close($link);
?>

<!-- Chart.js and Data for Visualization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const examSummary = <?php echo json_encode($exam_summary); ?>;

        if (examSummary.total_exams > 0) {
            const ctxDoughnut = document.getElementById('examResultsDoughnutChart').getContext('2d');
            new Chart(ctxDoughnut, {
                type: 'doughnut',
                data: {
                    labels: ['Passed Exams', 'Failed Exams'],
                    datasets: [{
                        data: [examSummary.passed_count, examSummary.failed_count],
                        backgroundColor: ['#34D399', '#EF4444'], // Green for passed, Red for failed
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '70%', // Make it a doughnut
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#4B5563', // gray-600
                                font: {
                                    size: 14
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            bodyColor: '#FFFFFF',
                            titleColor: '#FFFFFF',
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                                    return `${label}: ${value} (${percentage})`;
                                }
                            }
                        }
                    }
                }
            });
        }
    });
</script>