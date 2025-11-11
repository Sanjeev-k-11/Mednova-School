<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- PAGINATION & FILTER SETUP ---
$records_per_page = 9; // 3x3 grid
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$class_filter = $_GET['class_id'] ?? '';
$exam_type_filter = $_GET['exam_type_id'] ?? '';
$subject_filter = $_GET['subject_id'] ?? '';
$view_filter = $_GET['view'] ?? 'upcoming';


// --- DATA FETCHING for FILTERS ---
$assigned_classes = [];
$assigned_subjects = [];
$exam_types = [];

// Get Classes
$sql_classes = "SELECT DISTINCT c.id, c.class_name, c.section_name FROM class_subject_teacher cst JOIN classes c ON cst.class_id = c.id WHERE cst.teacher_id = ? ORDER BY c.class_name, c.section_name";
if ($stmt = mysqli_prepare($link, $sql_classes)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $assigned_classes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}
$assigned_class_ids = !empty($assigned_classes) ? array_column($assigned_classes, 'id') : [];

// Get Subjects
$sql_subjects = "SELECT DISTINCT s.id, s.subject_name FROM class_subject_teacher cst JOIN subjects s ON cst.subject_id = s.id WHERE cst.teacher_id = ? ORDER BY s.subject_name";
if ($stmt = mysqli_prepare($link, $sql_subjects)) {
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $assigned_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// Get Exam Types
$sql_exam_types = "SELECT id, exam_name FROM exam_types ORDER BY exam_name";
$exam_types = mysqli_fetch_all(mysqli_query($link, $sql_exam_types), MYSQLI_ASSOC);


// --- BUILD EXAM QUERY ---
$exams = [];
$total_records = 0;

// *** FIX: Only run the query if the teacher is assigned to at least one class ***
if (!empty($assigned_class_ids)) {
    $from_clause = "FROM exam_schedule es
                    JOIN exam_types et ON es.exam_type_id = et.id
                    JOIN classes c ON es.class_id = c.id
                    JOIN subjects s ON es.subject_id = s.id";
    $where_clause = "WHERE es.class_id IN (" . implode(',', array_fill(0, count($assigned_class_ids), '?')) . ")";
                  
    $params = $assigned_class_ids;
    $types = str_repeat('i', count($assigned_class_ids));

    // Apply filters
    if (!empty($class_filter)) { $where_clause .= " AND es.class_id = ?"; $params[] = $class_filter; $types .= "i"; }
    if (!empty($exam_type_filter)) { $where_clause .= " AND es.exam_type_id = ?"; $params[] = $exam_type_filter; $types .= "i"; }
    if (!empty($subject_filter)) { $where_clause .= " AND es.subject_id = ?"; $params[] = $subject_filter; $types .= "i"; }

    // Filter for upcoming or past exams
    $today = date('Y-m-d');
    if ($view_filter == 'upcoming') {
        $where_clause .= " AND es.exam_date >= ?"; $params[] = $today; $types .= "s";
    } else {
        $where_clause .= " AND es.exam_date < ?"; $params[] = $today; $types .= "s";
    }

    // Get total count
    $sql_count = "SELECT COUNT(es.id) as total " . $from_clause . " " . $where_clause;
    if ($stmt_count = mysqli_prepare($link, $sql_count)) {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
        mysqli_stmt_execute($stmt_count);
        $total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
        mysqli_stmt_close($stmt_count);
    }

    // Fetch paginated data
    $order_by = ($view_filter == 'upcoming') ? "ORDER BY es.exam_date ASC, es.start_time ASC" : "ORDER BY es.exam_date DESC, es.start_time DESC";
    $sql_exams = "SELECT es.*, et.exam_name, c.class_name, c.section_name, s.subject_name " . $from_clause . " " . $where_clause . " " . $order_by . " LIMIT ? OFFSET ?";
    array_push($params, $records_per_page, $offset);
    $types .= "ii";

    if ($stmt = mysqli_prepare($link, $sql_exams)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exams = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}
$total_pages = ceil($total_records / $records_per_page);

mysqli_close($link);
require_once './teacher_header.php';
?>

<!-- Custom Styles -->
<style>
    body { background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientBG 15s ease infinite; color: white; }
    @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
    .glassmorphism { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
    .form-input, .form-select { background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.3); color: white; padding: 0.75rem 1rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); transition: all 0.2s ease-in-out; }
    .form-input:focus, .form-select:focus { background: rgba(0, 0, 0, 0.3); outline: none; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2), 0 0 0 2px #23d5ab; }
    .custom-select-wrapper { position: relative; }
    .custom-select-wrapper select { appearance: none; -webkit-appearance: none; padding-right: 2.5rem; }
    .custom-select-wrapper .select-arrow { position: absolute; top: 0; right: 0; bottom: 0; display: flex; align-items: center; padding: 0 1rem; pointer-events: none; }
    .tab-link.active { background-color: white; color: #23a6d5; }
</style>

<div class="container mx-auto mt-28 p-4 md:p-8">
    <h1 class="text-3xl md:text-4xl font-bold mb-6 text-white text-center">Exam Schedule</h1>

    <div class="glassmorphism rounded-2xl p-4 md:p-6">
        <div class="flex justify-center border-b border-white/20 mb-6">
            <a href="?view=upcoming" class="tab-link px-6 py-3 font-semibold rounded-t-lg <?php if($view_filter == 'upcoming') echo 'active'; ?>">Upcoming Exams</a>
            <a href="?view=past" class="tab-link px-6 py-3 font-semibold rounded-t-lg <?php if($view_filter == 'past') echo 'active'; ?>">Past Exams</a>
        </div>
        
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8 items-end">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_filter); ?>">
            <div>
                <label for="class_id" class="block text-sm font-semibold text-white/80 mb-2">Class</label>
                <div class="custom-select-wrapper">
                    <select id="class_id" name="class_id" class="w-full form-select rounded-xl"><option value="">All My Classes</option>
                        <?php foreach($assigned_classes as $class): ?><option value="<?php echo $class['id']; ?>" <?php if($class_filter == $class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option><?php endforeach; ?>
                    </select>
                    <div class="select-arrow"><i class="fas fa-chevron-down text-white/50"></i></div>
                </div>
            </div>
            <div>
                <label for="exam_type_id" class="block text-sm font-semibold text-white/80 mb-2">Exam Type</label>
                <div class="custom-select-wrapper">
                    <select name="exam_type_id" class="w-full form-select rounded-xl"><option value="">All Types</option>
                        <?php foreach($exam_types as $type): ?><option value="<?php echo $type['id']; ?>" <?php if($exam_type_filter == $type['id']) echo 'selected'; ?>><?php echo htmlspecialchars($type['exam_name']); ?></option><?php endforeach; ?>
                    </select>
                    <div class="select-arrow"><i class="fas fa-chevron-down text-white/50"></i></div>
                </div>
            </div>
            <div>
                <label for="subject_id" class="block text-sm font-semibold text-white/80 mb-2">Subject</label>
                <div class="custom-select-wrapper">
                    <select name="subject_id" class="w-full form-select rounded-xl"><option value="">All My Subjects</option>
                        <?php foreach($assigned_subjects as $subject): ?><option value="<?php echo $subject['id']; ?>" <?php if($subject_filter == $subject['id']) echo 'selected'; ?>><?php echo htmlspecialchars($subject['subject_name']); ?></option><?php endforeach; ?>
                    </select>
                    <div class="select-arrow"><i class="fas fa-chevron-down text-white/50"></i></div>
                </div>
            </div>
            <div class="lg:col-span-2 flex gap-2">
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 font-bold py-3 px-4 rounded-xl">Filter</button>
                <a href="?view=<?php echo htmlspecialchars($view_filter); ?>" class="w-full text-center bg-gray-500 hover:bg-gray-600 font-bold py-3 px-4 rounded-xl">Reset</a>
            </div>
        </form>

        <?php if (empty($exams)): ?>
            <div class="text-center py-16">
                <i class="fas fa-calendar-times fa-3x text-white/50 mb-4"></i>
                <p class="text-lg">No <?php echo htmlspecialchars($view_filter); ?> exams found matching your criteria.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($exams as $exam): ?>
                    <div class="glassmorphism rounded-xl p-5 flex flex-col">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="text-2xl font-bold"><?php echo date('d', strtotime($exam['exam_date'])); ?></p>
                                <p class="text-sm text-white/80"><?php echo date('M, Y', strtotime($exam['exam_date'])); ?></p>
                                <p class="text-xs font-semibold text-white/60"><?php echo date('l', strtotime($exam['exam_date'])); ?></p>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-lg"><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                                <p class="text-sm bg-white/20 px-2 py-1 rounded-full mt-1 inline-block"><?php echo htmlspecialchars($exam['exam_name']); ?></p>
                            </div>
                        </div>
                        <div class="border-t border-white/20 my-3"></div>
                        <div class="text-sm space-y-2 flex-grow">
                            <p><i class="fas fa-users w-5 mr-1 text-white/70"></i> <strong>Class:</strong> <?php echo htmlspecialchars($exam['class_name'] . ' - ' . $exam['section_name']); ?></p>
                            <p><i class="fas fa-clock w-5 mr-1 text-white/70"></i> <strong>Time:</strong> <?php echo date('h:i A', strtotime($exam['start_time'])) . ' - ' . date('h:i A', strtotime($exam['end_time'])); ?></p>
                        </div>
                        <div class="flex justify-between items-center mt-4 pt-3 border-t border-white/20 text-xs font-semibold">
                            <span>Max Marks: <strong class="text-base"><?php echo htmlspecialchars($exam['max_marks']); ?></strong></span>
                            <span>Passing Marks: <strong class="text-base"><?php echo htmlspecialchars($exam['passing_marks']); ?></strong></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-8 flex flex-col md:flex-row justify-between items-center text-sm">
                <div class="mb-4 md:mb-0 text-white/70">
                    Showing <strong><?php echo min($offset + 1, $total_records); ?></strong> to <strong><?php echo min($offset + $records_per_page, $total_records); ?></strong> of <strong><?php echo $total_records; ?></strong> records.
                </div>
                <?php if ($total_pages > 1): ?>
                <nav class="flex items-center space-x-1">
                    <?php
                    $query_params = http_build_query(array_filter(['view' => $view_filter, 'class_id' => $class_filter, 'exam_type_id' => $exam_type_filter, 'subject_id' => $subject_filter]));
                    if ($current_page > 1) { echo "<a href='?{$query_params}&page=" . ($current_page - 1) . "' class='px-3 py-2 bg-white/10 rounded-lg hover:bg-white/20 border border-white/20'>Previous</a>"; } else { echo "<span class='px-3 py-2 bg-white/5 text-white/50 rounded-lg border border-white/10 cursor-not-allowed'>Previous</span>"; }
                    $range = 1;
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                            if ($i == $current_page) { echo "<span class='px-4 py-2 bg-blue-500 rounded-lg font-bold border border-blue-400'>$i</span>"; } else { echo "<a href='?{$query_params}&page=$i' class='px-4 py-2 bg-white/10 rounded-lg hover:bg-white/20 border border-white/20'>$i</a>"; }
                        } elseif (($i == 2 && $current_page - $range > 2) || ($i == $total_pages - 1 && $current_page + $range < $total_pages - 1)) { echo "<span class='px-4 py-2'>...</span>"; }
                    }
                    if ($current_page < $total_pages) { echo "<a href='?{$query_params}&page=" . ($current_page + 1) . "' class='px-3 py-2 bg-white/10 rounded-lg hover:bg-white/20 border border-white/20'>Next</a>"; } else { echo "<span class='px-3 py-2 bg-white/5 text-white/50 rounded-lg border border-white/10 cursor-not-allowed'>Next</span>"; }
                    ?>
                </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once './teacher_footer.php'; ?>