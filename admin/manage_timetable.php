<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["id"];
$message = "";
$message_type = "";

// Handle POST requests for saving or deleting a timetable slot
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // Use INSERT...ON DUPLICATE KEY UPDATE to handle both new entries and updates
    if ($action == 'save_slot') {
        $class_id = $_POST['class_id'];
        $period_id = $_POST['period_id'];
        $day_of_week = $_POST['day_of_week'];
        $subject_id = $_POST['subject_id'];
        $teacher_id = $_POST['teacher_id'];

        $sql = "INSERT INTO class_timetable (class_id, period_id, day_of_week, subject_id, teacher_id, created_by_admin_id) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id), teacher_id = VALUES(teacher_id)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "iisssi", $class_id, $period_id, $day_of_week, $subject_id, $teacher_id, $admin_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Timetable slot saved successfully.";
                $message_type = "success";
            } else {
                $message = "Error: " . mysqli_error($link); // Show detailed error for debugging
                $message_type = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($action == 'delete_slot') {
        $class_id = $_POST['class_id'];
        $period_id = $_POST['period_id'];
        $day_of_week = $_POST['day_of_week'];

        $sql = "DELETE FROM class_timetable WHERE class_id = ? AND period_id = ? AND day_of_week = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "iis", $class_id, $period_id, $day_of_week);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Timetable slot deleted successfully.";
                $message_type = "success";
            } else {
                $message = "Error: Could not delete slot.";
                $message_type = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }
     // To keep the class selected after form submission
    $selected_class_id = $_POST['class_id'] ?? ($_GET['class_id'] ?? null);
    if ($selected_class_id) {
        header("Location: manage_timetable.php?class_id=" . $selected_class_id . "&message=" . urlencode($message) . "&type=" . $message_type);
        exit();
    }
}


// Fetch data for dropdowns and grid
$classes = mysqli_query($link, "SELECT id, class_name, section_name FROM classes ORDER BY class_name, section_name");
$subjects = mysqli_query($link, "SELECT id, subject_name FROM subjects ORDER BY subject_name");
$teachers = mysqli_query($link, "SELECT id, full_name FROM teachers ORDER BY full_name");
$periods = mysqli_query($link, "SELECT id, period_name, start_time, end_time FROM class_periods ORDER BY start_time");
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Fetch timetable data if a class is selected
$selected_class_id = $_GET['class_id'] ?? null;
$timetable_data = [];
if ($selected_class_id) {
    $sql = "SELECT tt.day_of_week, tt.period_id, s.subject_name, t.full_name as teacher_name, tt.subject_id, tt.teacher_id
            FROM class_timetable tt
            JOIN subjects s ON tt.subject_id = s.id
            JOIN teachers t ON tt.teacher_id = t.id
            WHERE tt.class_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $selected_class_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $timetable_data[$row['day_of_week']][$row['period_id']] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// Display messages from URL
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type'] ?? 'success');
}

mysqli_close($link);
require_once './admin_header.php';
?>

<div class="container mx-auto mt-[80%] p-4 sm:p-6 lg:p-8">
    <h1 class="text-2xl sm:text-3xl font-bold mt-12 text-gray-800 mb-6">Manage Class Timetable</h1>

    <?php if ($message): ?>
    <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <!-- Class Selector -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="flex flex-col sm:flex-row sm:items-center sm:gap-4">
            <label for="class_id" class="block text-sm font-medium text-gray-700 mb-2 sm:mb-0">Select a Class:</label>
            <select name="class_id" id="class_id" class="block w-full sm:w-auto mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" onchange="this.form.submit()">
                <option value="">-- Select Class --</option>
                <?php while ($class = mysqli_fetch_assoc($classes)): ?>
                    <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <noscript><button type="submit" class="mt-2 sm:mt-0 bg-blue-500 text-white py-2 px-4 rounded">View Timetable</button></noscript>
        </form>
    </div>

    <?php if ($selected_class_id): ?>
    <!-- Timetable Grid -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Period</th>
                        <?php foreach ($days as $day): ?>
                            <th class="px-2 py-3 text-center text-xs font-medium text-gray-600 uppercase tracking-wider"><?php echo $day; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($period = mysqli_fetch_assoc($periods)): ?>
                    <tr>
                        <td class="px-2 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($period['period_name']); ?><br>
                            <span class="text-xs text-gray-500"><?php echo date("g:i", strtotime($period['start_time'])) . ' - ' . date("g:i A", strtotime($period['end_time'])); ?></span>
                        </td>
                        <?php foreach ($days as $day): 
                            $slot = $timetable_data[$day][$period['id']] ?? null;
                        ?>
                        <td class="px-2 py-4 whitespace-nowrap text-sm text-center align-top">
                            <?php if ($slot): ?>
                                <div class="p-2 border rounded-lg bg-indigo-50 text-indigo-800">
                                    <p class="font-bold"><?php echo htmlspecialchars($slot['subject_name']); ?></p>
                                    <p class="text-xs"><?php echo htmlspecialchars($slot['teacher_name']); ?></p>
                                    <div class="mt-2">
                                        <button onclick='openSlotModal(<?php echo json_encode(['day' => $day, 'period_id' => $period['id'], 'subject_id' => $slot['subject_id'], 'teacher_id' => $slot['teacher_id']]); ?>)' class="text-blue-500 hover:text-blue-700 text-xs">Edit</button>
                                        
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this slot?');">
                                            <input type="hidden" name="action" value="delete_slot">
                                            <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                                            <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                            <input type="hidden" name="day_of_week" value="<?php echo $day; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs ml-2">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <button onclick='openSlotModal(<?php echo json_encode(['day' => $day, 'period_id' => $period['id']]); ?>)' class="w-full h-full flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 rounded-lg p-4 transition">
                                    <i class="fas fa-plus"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Slot Modal -->
<div id="slotModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center border-b pb-3">
            <h3 id="slotModalTitle" class="text-lg font-medium text-gray-900">Add/Edit Slot</h3>
            <button onclick="document.getElementById('slotModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <div class="mt-5">
            <form id="slotForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="action" value="save_slot">
                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                <input type="hidden" name="period_id" id="modalPeriodId">
                <input type="hidden" name="day_of_week" id="modalDayOfWeek">

                <div class="mb-4">
                    <label for="modalSubjectId" class="block text-sm font-medium text-gray-700">Subject</label>
                    <select name="subject_id" id="modalSubjectId" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        <option value="">-- Select Subject --</option>
                        <?php mysqli_data_seek($subjects, 0); while ($subject = mysqli_fetch_assoc($subjects)): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="modalTeacherId" class="block text-sm font-medium text-gray-700">Teacher</label>
                    <select name="teacher_id" id="modalTeacherId" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        <option value="">-- Select Teacher --</option>
                        <?php mysqli_data_seek($teachers, 0); while ($teacher = mysqli_fetch_assoc($teachers)): ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="flex justify-end pt-4 border-t">
                    <button type="button" onclick="document.getElementById('slotModal').classList.add('hidden')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg mr-2">Cancel</button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">Save Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSlotModal(data) {
    document.getElementById('slotForm').reset();
    document.getElementById('modalDayOfWeek').value = data.day;
    document.getElementById('modalPeriodId').value = data.period_id;
    
    // If editing, pre-select the values
    if (data.subject_id) {
        document.getElementById('modalSubjectId').value = data.subject_id;
    }
    if (data.teacher_id) {
        document.getElementById('modalTeacherId').value = data.teacher_id;
    }

    document.getElementById('slotModal').classList.remove('hidden');
}
</script>

<?php require_once './admin_footer.php'; ?>