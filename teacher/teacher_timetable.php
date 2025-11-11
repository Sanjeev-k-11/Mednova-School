<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- DATA FETCHING ---

// 1. Get all defined class periods from the database to build the table columns
$periods = [];
$sql_periods = "SELECT id, period_name, start_time, end_time FROM class_periods ORDER BY start_time";
$result_periods = mysqli_query($link, $sql_periods);
if ($result_periods) {
    $periods = mysqli_fetch_all($result_periods, MYSQLI_ASSOC);
}

// 2. Get the teacher's complete timetable schedule
$timetable = [];
$sql_timetable = "SELECT 
                    tt.day_of_week, 
                    tt.period_id,
                    c.class_name,
                    c.section_name,
                    s.subject_name
                  FROM class_timetable tt
                  JOIN classes c ON tt.class_id = c.id
                  JOIN subjects s ON tt.subject_id = s.id
                  WHERE tt.teacher_id = ?
                  ORDER BY tt.day_of_week, tt.period_id";

if ($stmt = mysqli_prepare($link, $sql_timetable)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    // Organize the fetched data into a structured array for easy lookup
    while ($row = mysqli_fetch_assoc($result)) {
        $timetable[$row['day_of_week']][$row['period_id']] = [
            'class' => $row['class_name'] . ' - ' . $row['section_name'],
            'subject' => $row['subject_name']
        ];
    }
    mysqli_stmt_close($stmt);
}

// Define the days of the week in order
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

mysqli_close($link);
require_once './teacher_header.php';
?>

<!-- Custom Styles -->
<style>
    body { background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364); background-size: 400% 400%; animation: gradientBG 15s ease infinite; color: white; }
    @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
    .glassmorphism { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
    
    /* Timetable specific styles */
    .timetable-table th, .timetable-table td {
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 0.75rem;
        text-align: center;
        min-width: 140px;
    }
    .timetable-table thead th {
        background-color: rgba(0, 0, 0, 0.2);
    }
    .day-header {
        font-weight: bold;
        background-color: rgba(0, 0, 0, 0.2);
        min-width: 120px;
    }
    .break-cell {
        background-color: rgba(30, 93, 117, 0.5);
        font-weight: bold;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }
</style>

<div class="container mx-auto mt-28 p-4 md:p-8">
    <h1 class="text-3xl md:text-4xl font-bold mb-6 text-white text-center">My Weekly Timetable</h1>

    <div class="glassmorphism rounded-2xl p-4 md:p-6">
        <?php if (empty($periods)): ?>
            <div class="text-center py-16">
                <p class="text-lg">The school timetable has not been set up yet.</p>
            </div>
        <?php elseif (empty($timetable)): ?>
             <div class="text-center py-16">
                <i class="fas fa-calendar-times fa-3x text-white/50 mb-4"></i>
                <p class="text-lg">Your timetable has not been assigned yet.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full timetable-table border-collapse">
                    <thead>
                        <tr>
                            <th>Day / Period</th>
                            <?php foreach ($periods as $period): ?>
                                <th>
                                    <p class="font-bold"><?php echo htmlspecialchars($period['period_name']); ?></p>
                                    <p class="text-xs text-white/70 font-normal"><?php echo date('h:i A', strtotime($period['start_time'])) . ' - ' . date('h:i A', strtotime($period['end_time'])); ?></p>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days_of_week as $day): ?>
                            <tr>
                                <td class="day-header"><?php echo $day; ?></td>
                                <?php foreach ($periods as $period): 
                                    $entry = $timetable[$day][$period['id']] ?? null;
                                    // Check if the period is a break
                                    $is_break = stripos($period['period_name'], 'break') !== false || stripos($period['period_name'], 'lunch') !== false;
                                ?>
                                    <?php if ($is_break): ?>
                                        <td class="break-cell" colspan="<?php echo count($periods); ?>">
                                            <?php echo htmlspecialchars($period['period_name']); ?>
                                        </td>
                                        <?php break; // Skip the rest of the periods for this day since it's a full-width break ?>
                                    <?php else: ?>
                                        <td>
                                            <?php if ($entry): ?>
                                                <p class="font-bold text-teal-300"><?php echo htmlspecialchars($entry['subject']); ?></p>
                                                <p class="text-sm text-white/80"><?php echo htmlspecialchars($entry['class']); ?></p>
                                            <?php else: ?>
                                                <p class="text-white/40">- Free -</p>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once './teacher_footer.php'; ?>