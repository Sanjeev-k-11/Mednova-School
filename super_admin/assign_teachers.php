<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & INITIALIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}
$admin_id = $_SESSION["super_admin_id"];

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class_id = $_POST['class_id'];
    $class_teacher_id = !empty($_POST['class_teacher_id']) ? $_POST['class_teacher_id'] : NULL;
    $subject_teachers = $_POST['subject_teachers'] ?? [];

    $link->begin_transaction();
    try {
        // 1. Update the main Class Teacher
        $stmt_class = $link->prepare("UPDATE classes SET teacher_id = ? WHERE id = ?");
        $stmt_class->bind_param("ii", $class_teacher_id, $class_id);
        $stmt_class->execute();
        $stmt_class->close();

        // 2. Update or Insert Subject Teachers
        $stmt_subject = $link->prepare("INSERT INTO class_subject_teacher (class_id, subject_id, teacher_id, assigned_by_admin_id) VALUES (?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)");
        
        $stmt_delete = $link->prepare("DELETE FROM class_subject_teacher WHERE class_id = ? AND subject_id = ?");

        foreach ($subject_teachers as $subject_id => $teacher_id) {
            if (!empty($teacher_id)) {
                $stmt_subject->bind_param("iiii", $class_id, $subject_id, $teacher_id, $admin_id);
                $stmt_subject->execute();
            } else {
                // If dropdown is set to empty, un-assign the teacher
                $stmt_delete->bind_param("ii", $class_id, $subject_id);
                $stmt_delete->execute();
            }
        }
        $stmt_subject->close();
        $stmt_delete->close();

        $link->commit();
        $_SESSION['message'] = "Teacher assignments for the class have been updated successfully!";
        $_SESSION['message_type'] = "success";

    } catch (mysqli_sql_exception $exception) {
        $link->rollback();
        $_SESSION['message'] = "Error updating assignments: " . $exception->getMessage();
        $_SESSION['message_type'] = "error";
    }

    header("Location: assign_teachers.php");
    exit;
}

// --- DATA FETCHING FOR PAGE DISPLAY ---

// 1. Get all classes with their assigned class teacher's name
$classes_sql = "SELECT c.id, c.class_name, c.section_name, t.full_name as class_teacher_name, c.teacher_id as class_teacher_id
                FROM classes c
                LEFT JOIN teachers t ON c.teacher_id = t.id
                ORDER BY c.class_name, c.section_name";
$classes_result = mysqli_query($link, $classes_sql);

// 2. Get all teachers for dropdowns
$teachers = [];
$teachers_result = mysqli_query($link, "SELECT id, full_name FROM teachers ORDER BY full_name ASC");
while ($row = mysqli_fetch_assoc($teachers_result)) {
    $teachers[] = $row;
}

// 3. Get all subjects assigned to each class
$class_subjects = [];
$class_subjects_sql = "SELECT cs.class_id, s.id as subject_id, s.subject_name
                       FROM class_subjects cs
                       JOIN subjects s ON cs.subject_id = s.id";
$class_subjects_result = mysqli_query($link, $class_subjects_sql);
while ($row = mysqli_fetch_assoc($class_subjects_result)) {
    $class_subjects[$row['class_id']][] = $row;
}

// 4. Get all existing subject-teacher assignments
$subject_teacher_assignments = [];
$assignments_sql = "SELECT class_id, subject_id, teacher_id FROM class_subject_teacher";
$assignments_result = mysqli_query($link, $assignments_sql);
while ($row = mysqli_fetch_assoc($assignments_result)) {
    $subject_teacher_assignments[$row['class_id']][$row['subject_id']] = $row['teacher_id'];
}

$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);

mysqli_close($link);
require_once './admin_header.php';
?>

<div class="container mx-auto mt-12 p-4 sm:p-6 lg:p-8">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-4 sm:mb-0">Assign Teachers to Classes</h1>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-800 p-4 rounded-r-lg mb-8" role="alert">
        <p class="font-bold">Instructions:</p>
        <p>Use the accordions below to manage teachers for each class. You can assign a primary Class Teacher and individual Subject Teachers. Click "Save Changes" for each class to apply.</p>
    </div>
    
    <div class="space-y-4">
        <?php while($class = mysqli_fetch_assoc($classes_result)): ?>
        <div class="bg-white shadow-lg rounded-lg overflow-hidden transition-all duration-300 accordion">
            <!-- Accordion Header -->
            <button class="accordion-header w-full flex justify-between items-center p-4 text-left">
                <div>
                    <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></h2>
                    <p class="text-sm text-gray-500">
                        Class Teacher: <span class="font-semibold text-indigo-600"><?php echo htmlspecialchars($class['class_teacher_name'] ?? 'Not Assigned'); ?></span>
                    </p>
                </div>
                <i class="fas fa-chevron-down transform transition-transform duration-300"></i>
            </button>
            
            <!-- Accordion Content (Form for this class) -->
            <div class="accordion-content max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="p-6 bg-gray-50 border-t">
                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                    
                    <!-- Assign Class Teacher -->
                    <div class="mb-6 pb-6 border-b">
                        <label for="class-teacher-<?php echo $class['id']; ?>" class="block text-lg font-semibold text-gray-700 mb-2">Assign Class Teacher</label>
                        <select name="class_teacher_id" id="class-teacher-<?php echo $class['id']; ?>" class="block w-full md:w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Unassign / Not Set --</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($class['class_teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Assign Subject Teachers -->
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Assign Subject Teachers</h3>
                    <div class="space-y-4">
                        <?php if(isset($class_subjects[$class['id']])): ?>
                            <?php foreach($class_subjects[$class['id']] as $subject): 
                                $assigned_teacher_id = $subject_teacher_assignments[$class['id']][$subject['subject_id']] ?? null;
                            ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                                <label for="subject-<?php echo $class['id'].'-'.$subject['subject_id']; ?>" class="font-medium text-gray-600"><?php echo htmlspecialchars($subject['subject_name']); ?></label>
                                <select name="subject_teachers[<?php echo $subject['subject_id']; ?>]" id="subject-<?php echo $class['id'].'-'.$subject['subject_id']; ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Not Assigned --</option>
                                    <?php foreach($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>" <?php echo ($assigned_teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-500">No subjects have been assigned to this class yet. Please go to "Manage Subjects" to add them first.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-8 text-right">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-300">
                            <i class="fas fa-save mr-2"></i>Save Changes for this Class
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
document.querySelectorAll('.accordion-header').forEach(header => {
    header.addEventListener('click', () => {
        const content = header.nextElementSibling;
        const icon = header.querySelector('i');

        if (content.style.maxHeight) {
            content.style.maxHeight = null;
            icon.classList.remove('rotate-180');
        } else {
            // Close other open accordions
            document.querySelectorAll('.accordion-content').forEach(c => c.style.maxHeight = null);
            document.querySelectorAll('.accordion-header i').forEach(i => i.classList.remove('rotate-180'));

            content.style.maxHeight = content.scrollHeight + "px";
            icon.classList.add('rotate-180');
        } 
    });
});
</script>

<?php require_once './admin_footer.php'; ?>