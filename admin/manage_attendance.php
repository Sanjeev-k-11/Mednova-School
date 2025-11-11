<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & INITIALIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["id"];

// --- API ENDPOINT FOR SAVING ATTENDANCE (AJAX) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'save_attendance') {
    header('Content-Type: application/json');
    $attendance_data = $_POST['attendance'] ?? [];
    $attendance_date = $_POST['attendance_date'];
    $class_id = $_POST['class_id'];
    $is_past_date = (new DateTime($attendance_date) < new DateTime('today'));
    
    $sql = "INSERT INTO attendance (student_id, class_id, attendance_date, status, marked_by_admin_id) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by_admin_id = VALUES(marked_by_admin_id)";

    $response = ['success' => false, 'message' => 'An unknown error occurred.'];
    if ($stmt = mysqli_prepare($link, $sql)) {
        $saved_count = 0;
        foreach ($attendance_data as $student_id => $status) {
            if (!empty($status)) {
                // If it's a past date, we only update existing records, not insert new ones.
                // This logic can be handled at the DB level with a more complex query, but PHP is fine for this scale.
                mysqli_stmt_bind_param($stmt, "iissi", $student_id, $class_id, $attendance_date, $status, $admin_id);
                if(mysqli_stmt_execute($stmt)){
                    $saved_count++;
                }
            }
        }
        mysqli_stmt_close($stmt);
        $response = ['success' => true, 'message' => "Attendance for {$saved_count} students saved successfully!"];
    } else {
        $response['message'] = 'Error preparing the database statement.';
    }
    echo json_encode($response);
    exit; // Terminate script after AJAX response
}


// --- DATA FETCHING FOR PAGE DISPLAY ---
$classes = mysqli_query($link, "SELECT id, class_name, section_name FROM classes ORDER BY class_name, section_name");
$selected_class_id = $_GET['class_id'] ?? null;
$selected_date = $_GET['date'] ?? date('Y-m-d');
$students_with_attendance = [];
$attendance_summary = ['Total' => 0, 'Present' => 0, 'Absent' => 0, 'Other' => 0];
$is_past_date = (new DateTime($selected_date) < new DateTime('today'));


if ($selected_class_id) {
    // 1. Fetch all students for the selected class
    $sql_students = "SELECT id, registration_number, first_name, last_name, roll_number FROM students WHERE class_id = ? ORDER BY roll_number, first_name";
    if ($stmt_students = mysqli_prepare($link, $sql_students)) {
        mysqli_stmt_bind_param($stmt_students, "i", $selected_class_id);
        mysqli_stmt_execute($stmt_students);
        $result_students = mysqli_stmt_get_result($stmt_students);
        while ($student = mysqli_fetch_assoc($result_students)) {
            $students_with_attendance[$student['id']] = $student;
            $students_with_attendance[$student['id']]['status'] = ''; // Default status
            $students_with_attendance[$student['id']]['record_exists'] = false; // Flag to check if record exists for past dates
        }
        mysqli_stmt_close($stmt_students);
        $attendance_summary['Total'] = count($students_with_attendance);
    }

    // 2. Fetch existing attendance records and update student data
    $sql_attendance = "SELECT student_id, status FROM attendance WHERE class_id = ? AND attendance_date = ?";
    if ($stmt_attendance = mysqli_prepare($link, $sql_attendance)) {
        mysqli_stmt_bind_param($stmt_attendance, "is", $selected_class_id, $selected_date);
        mysqli_stmt_execute($stmt_attendance);
        $result_attendance = mysqli_stmt_get_result($stmt_attendance);
        while ($att_record = mysqli_fetch_assoc($result_attendance)) {
            if (isset($students_with_attendance[$att_record['student_id']])) {
                $status = $att_record['status'];
                $students_with_attendance[$att_record['student_id']]['status'] = $status;
                $students_with_attendance[$att_record['student_id']]['record_exists'] = true;

                if ($status == 'Present') $attendance_summary['Present']++;
                elseif ($status == 'Absent') $attendance_summary['Absent']++;
                else $attendance_summary['Other']++;
            }
        }
        mysqli_stmt_close($stmt_attendance);
    }
}

mysqli_close($link);
require_once './admin_header.php';
?>

<!-- Custom CSS for better UI elements -->
<style>
    .attendance-radio-group label {
        cursor: pointer; padding: 0.5rem 1rem; border-radius: 9999px; transition: all 0.2s ease-in-out; border: 1px solid #e5e7eb; font-weight: 500; font-size: 0.875rem;
    }
    .attendance-radio-group input[type="radio"] { display: none; }
    .attendance-radio-group input[type="radio"]:checked + label { color: white; transform: scale(1.05); }
    input[id$="-present"]:checked + label { background-color: #10b981; border-color: #059669; }
    input[id$="-absent"]:checked + label { background-color: #ef4444; border-color: #dc2626; }
    input[id$="-late"]:checked + label { background-color: #f59e0b; border-color: #d97706; }
    input[id$="-halfday"]:checked + label { background-color: #3b82f6; border-color: #2563eb; }
    /* Style for disabled radio buttons */
    .attendance-radio-group input[type="radio"]:disabled + label {
        cursor: not-allowed; background-color: #f3f4f6; color: #9ca3af; border-color: #d1d5db;
    }
</style>

<div class="container mx-auto mt-12 p-4 sm:p-6 lg:p-8">

    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-6">Manage Student Attendance</h1>

    <div id="flash-message-container" class="mb-6"></div>

    <!-- Filter Section -->
    <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
        <form id="filterForm" method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
            <div>
                <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Select Class</label>
                <select name="class_id" id="class_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Select a Class --</option>
                    <?php while ($class = mysqli_fetch_assoc($classes)): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Select Date</label>
                <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300">
                View Attendance
            </button>
        </form>
    </div>
    
    <?php if ($selected_class_id): ?>
    <!-- Attendance Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-5 rounded-xl shadow-lg flex items-center"><div class="bg-blue-100 p-3 rounded-full mr-4"><i class="fas fa-users fa-lg text-blue-500"></i></div><div><p class="text-sm text-gray-500">Total Students</p><p class="text-2xl font-bold text-gray-800"><?php echo $attendance_summary['Total']; ?></p></div></div>
        <div class="bg-white p-5 rounded-xl shadow-lg flex items-center"><div class="bg-green-100 p-3 rounded-full mr-4"><i class="fas fa-user-check fa-lg text-green-500"></i></div><div><p class="text-sm text-gray-500">Present</p><p class="text-2xl font-bold text-gray-800"><?php echo $attendance_summary['Present']; ?></p></div></div>
        <div class="bg-white p-5 rounded-xl shadow-lg flex items-center"><div class="bg-red-100 p-3 rounded-full mr-4"><i class="fas fa-user-times fa-lg text-red-500"></i></div><div><p class="text-sm text-gray-500">Absent</p><p class="text-2xl font-bold text-gray-800"><?php echo $attendance_summary['Absent']; ?></p></div></div>
        <div class="bg-white p-5 rounded-xl shadow-lg flex items-center"><div class="bg-yellow-100 p-3 rounded-full mr-4"><i class="fas fa-exclamation-circle fa-lg text-yellow-500"></i></div><div><p class="text-sm text-gray-500">Late / Half Day</p><p class="text-2xl font-bold text-gray-800"><?php echo $attendance_summary['Other']; ?></p></div></div>
    </div>

    <form id="attendanceForm">
        <input type="hidden" name="action" value="save_attendance">
        <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
        <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">

        <div class="bg-white shadow-xl rounded-lg overflow-hidden">
            <div class="p-4 bg-gray-50 border-b flex flex-wrap gap-4 items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mr-4">
                        <?php echo $is_past_date ? '<i class="fas fa-edit mr-2"></i>Edit Mode (Past Date)' : 'Mark Attendance'; ?>
                    </h3>
                </div>
                <?php if (!$is_past_date): ?>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="markAll('Present')" class="px-4 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 rounded-full">Mark All Present</button>
                    <button type="button" onclick="markAll('Absent')" class="px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 rounded-full">Mark All Absent</button>
                    <button type="button" onclick="clearAll()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-full">Clear All</button>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="divide-y divide-gray-200">
                <?php foreach ($students_with_attendance as $student): 
                    $is_disabled = $is_past_date && !$student['record_exists'];    
                ?>
                <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                    <div>
                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                        <p class="text-sm text-gray-500">Roll No: <?php echo htmlspecialchars($student['roll_number'] ?? 'N/A'); ?> | Reg No: <?php echo htmlspecialchars($student['registration_number']); ?></p>
                    </div>
                    <div class="attendance-radio-group flex flex-wrap gap-2 justify-start md:justify-end">
                        <?php 
                            $statuses = ['Present', 'Absent', 'Late', 'Half Day'];
                            foreach($statuses as $status) {
                                $status_id = strtolower(str_replace(' ', '', $status));
                                $checked = ($student['status'] == $status) ? 'checked' : '';
                                $disabled_attr = $is_disabled ? 'disabled' : '';
                                echo "
                                <div class='flex items-center'>
                                    <input type='radio' id='status-{$student['id']}-{$status_id}' name='attendance[{$student['id']}]' value='{$status}' {$checked} {$disabled_attr}>
                                    <label for='status-{$student['id']}-{$status_id}'>{$status}</label>
                                </div>";
                            }
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="p-6 bg-gray-50 border-t text-right">
                <button type="submit" id="saveBtn" class="bg-indigo-600 hover:bg-indigo-800 text-white font-bold py-3 px-8 text-lg rounded-lg shadow-lg transition duration-300 w-full sm:w-auto">
                    <span class="btn-text"><i class="fas fa-save mr-2"></i>Save Attendance</span>
                </button>
            </div>
        </div>
    </form>
    <?php elseif ($selected_class_id): ?>
        <div class="bg-white p-8 rounded-xl shadow-lg text-center"><p class="text-gray-600">No students found for the selected class.</p></div>
    <?php endif; ?>
</div>

<script>
document.getElementById('filterForm').addEventListener('submit', function(e) {
    const classId = document.getElementById('class_id').value;
    if (!classId) {
        e.preventDefault();
        showFlashMessage('Please select a class to view attendance.', 'error');
    }
});

document.getElementById('attendanceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveBtn');
    const btnText = saveBtn.querySelector('.btn-text');
    const originalText = btnText.innerHTML;

    btnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    saveBtn.disabled = true;

    const formData = new FormData(this);

    fetch('manage_attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btnText.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Saved!';
            showFlashMessage(data.message, 'success');
            // Refresh summary cards and list after successful save by reloading the page with filters
            setTimeout(() => {
                window.location.href = `manage_attendance.php?class_id=${formData.get('class_id')}&date=${formData.get('attendance_date')}`;
            }, 1500);
        } else {
            showFlashMessage(data.message, 'error');
            btnText.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    })
    .catch(error => {
        showFlashMessage('An error occurred while saving.', 'error');
        btnText.innerHTML = originalText;
        saveBtn.disabled = false;
    });
});

function markAll(status) {
    const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]:not(:disabled)`);
    radios.forEach(radio => radio.checked = true);
}

function clearAll() {
    const allRadios = document.querySelectorAll('.attendance-radio-group input[type="radio"]:not(:disabled)');
    allRadios.forEach(radio => radio.checked = false);
}

function showFlashMessage(message, type) {
    const container = document.getElementById('flash-message-container');
    const bgColor = type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    container.innerHTML = `<div class="p-4 rounded-lg ${bgColor}">${message}</div>`;
    setTimeout(() => { container.innerHTML = ''; }, 5000);
}
</script>

<?php require_once './admin_footer.php'; ?>