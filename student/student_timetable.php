<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./student_header.php";  
// Authenticate as Student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php"); // Adjust path to your login page
    exit;
}

// Get the logged-in student's ID from the session
$student_id = $_SESSION['id'] ?? null; 

// --- CRITICAL CHECK: Validate student_id from session ---
if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Authentication Error!</strong>
            <span class='block sm:inline'> Your student ID is missing or invalid in the session. Please log in again.</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

// Fetch student's class information
$student_class_info = null;
$sql_student_class = "
    SELECT 
        s.class_id,
        c.class_name,
        c.section_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
    LIMIT 1";

if ($stmt = mysqli_prepare($link, $sql_student_class)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_class_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    error_log("Database Error preparing student class query: " . mysqli_error($link));
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Database Error!</strong>
            <span class='block sm:inline'> Could not prepare student class query.</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

if (!$student_class_info) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Error:</strong>
            <span class='block sm:inline'> Your class information could not be found. Please contact administration.</span>
          </div>";
    require_once "./student_footer.php";
    if($link) mysqli_close($link);
    exit();
}

$class_id = $student_class_info['class_id'];
$class_name = htmlspecialchars($student_class_info['class_name'] . ' - ' . $student_class_info['section_name']);

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$periods = [];
$timetable_data = []; // Structured as timetable_data[day_of_week][period_id] = {subject_name, teacher_name}

// 1. Fetch all periods (ordered by start time)
$sql_periods = "SELECT id, period_name, start_time, end_time FROM class_periods ORDER BY start_time";
if ($result = mysqli_query($link, $sql_periods)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $periods[] = $row;
    }
    mysqli_free_result($result);
} else {
    error_log("Database Error fetching periods: " . mysqli_error($link));
    // Continue, as this might just mean no periods are set, which is handled gracefully below
}

// 2. Fetch timetable entries for the student's class
if (!empty($periods)) { // Only try to fetch timetable if periods exist
    $sql_timetable = "
        SELECT
            ct.day_of_week,
            ct.period_id,
            s.subject_name,
            t.full_name AS teacher_name
        FROM class_timetable ct
        JOIN subjects s ON ct.subject_id = s.id
        JOIN teachers t ON ct.teacher_id = t.id
        WHERE ct.class_id = ?
        ORDER BY FIELD(ct.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), ct.period_id";

    if ($stmt = mysqli_prepare($link, $sql_timetable)) {
        mysqli_stmt_bind_param($stmt, "i", $class_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $timetable_data[$row['day_of_week']][$row['period_id']] = [
                'subject_name' => htmlspecialchars($row['subject_name']),
                'teacher_name' => htmlspecialchars($row['teacher_name'])
            ];
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Database Error preparing timetable query: " . mysqli_error($link));
        // This error will be handled by checking if $timetable_data is empty
    }
}

mysqli_close($link);

$current_day = date('l'); // Get the current day of the week, e.g., "Monday"
?>

<!-- Custom Styles for a vibrant, modern look -->
<style>
    body { background: linear-gradient(-45deg, #1d2b64, #485563, #2b5876, #4e4376); background-size: 400% 400%; animation: gradientBG 15s ease infinite; color: white; font-family: 'Inter', sans-serif; }
    @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
    .glassmorphism { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 1rem; }
    .timetable-cell { transition: background-color 0.3s ease-in-out; }
    .timetable-cell:hover { background-color: rgba(255, 255, 255, 0.1); }
</style>

<div class="container mx-auto mt-28 mb-12 p-4 md:p-8">
    <!-- Main Header Card -->
    <div class="glassmorphism rounded-xl shadow-lg p-6 mb-6 text-center md:text-left">
        <h1 class="text-3xl md:text-4xl font-extrabold text-white mb-2">My Class Timetable</h1>
        <p class="text-white/80">Here is the schedule for your class: <strong class="text-white"><?php echo $class_name; ?></strong></p>
    </div>

    <?php if (empty($periods) || empty($timetable_data)): ?>
        <div class="glassmorphism rounded-xl p-8 text-center max-w-2xl mx-auto">
            <i class="fas fa-calendar-times fa-3x text-yellow-300 mb-4"></i>
            <h2 class="text-2xl font-bold mb-2 text-white">No Timetable Available</h2>
            <p class="text-white/80">It seems the timetable for your class (<?php echo $class_name; ?>) has not been set up yet, or no periods are defined. Please check with your class teacher or administration.</p>
        </div>
    <?php else: ?>
        <div class="glassmorphism rounded-2xl p-4 md:p-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-white/20 border border-white/20">
                <thead class="bg-white/10 text-white">
                    <tr>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider">Time / Day</th>
                        <?php foreach ($days_of_week as $day): ?>
                            <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider <?php echo ($day === $current_day) ? 'bg-white/20 rounded-t-lg' : ''; ?>">
                                <?php echo htmlspecialchars($day); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    <?php foreach ($periods as $period): ?>
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-white bg-white/5 timetable-cell">
                                <div class="font-semibold"><?php echo htmlspecialchars($period['period_name']); ?></div>
                                <div class="text-xs text-white/70"><?php echo date('h:i A', strtotime($period['start_time'])) . ' - ' . date('h:i A', strtotime($period['end_time'])); ?></div>
                            </td>
                            <?php foreach ($days_of_week as $day): ?>
                                <?php
                                $entry = $timetable_data[$day][$period['id']] ?? null;
                                $cell_bg_color = '';
                                if (strpos($period['period_name'], 'Break') !== false || strpos($period['period_name'], 'Lunch') !== false) {
                                    $cell_bg_color = 'bg-white/5 text-white/80'; // Special background for breaks
                                }
                                $today_highlight = ($day === $current_day) ? 'bg-blue-600/20' : '';
                                ?>
                                <td class="px-3 py-3 whitespace-nowrap text-sm text-white/90 text-center timetable-cell <?php echo $cell_bg_color; ?> <?php echo $today_highlight; ?>">
                                    <?php if ($entry): ?>
                                        <div class="font-semibold text-yellow-300"><?php echo $entry['subject_name']; ?></div>
                                        <div class="text-xs text-white/70">(<?php echo $entry['teacher_name']; ?>)</div>
                                    <?php elseif (strpos($period['period_name'], 'Break') !== false || strpos($period['period_name'], 'Lunch') !== false): ?>
                                        <span class="text-xs font-medium"><?php echo htmlspecialchars($period['period_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-white/40">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require_once "./student_footer.php"; // Closes the main-page-content div and HTML tags
?>
