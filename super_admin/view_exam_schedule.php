<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}

// --- FILTERING LOGIC ---
$filter_exam_type_id = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$filter_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Build the WHERE clause for the query
$where_clauses = [];
$params = [];
$param_types = '';

if ($filter_exam_type_id > 0) {
    $where_clauses[] = "es.exam_type_id = ?";
    $params[] = &$filter_exam_type_id;
    $param_types .= 'i';
}
if ($filter_class_id > 0) {
    $where_clauses[] = "es.class_id = ?";
    $params[] = &$filter_class_id;
    $param_types .= 'i';
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// --- DATA FETCHING ---
$all_schedules = [];
$all_exam_types = [];
$all_classes = [];

// Fetch all exam schedules with details
$sql = "SELECT 
            es.*,
            et.exam_name,
            c.class_name, c.section_name,
            s.subject_name
        FROM exam_schedule es
        JOIN exam_types et ON es.exam_type_id = et.id
        JOIN classes c ON es.class_id = c.id
        JOIN subjects s ON es.subject_id = s.id
        $where_sql
        ORDER BY es.exam_date ASC, es.start_time ASC, c.class_name ASC";

if ($stmt = mysqli_prepare($link, $sql)) {
    if(!empty($params)){
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt, $param_types], $params));
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $all_schedules[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Fetch data for filter dropdowns
$sql_types = "SELECT id, exam_name FROM exam_types ORDER BY exam_name";
if ($res = mysqli_query($link, $sql_types)) while ($row = mysqli_fetch_assoc($res)) $all_exam_types[] = $row;
$sql_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name, section_name";
if ($res = mysqli_query($link, $sql_classes)) while ($row = mysqli_fetch_assoc($res)) $all_classes[] = $row;

mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Exam Schedule</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;   background: linear-gradient(-45deg, #6a82fb, #fc5c7d, #5c97fc, #a46afb); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1400px; margin: auto; margin-bottom: 100px; margin-top: 100px; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; }
        h2 { text-align: center; color: #fff; font-weight: 600; margin-bottom: 30px; }
        .filter-container { background-color: rgba(255,255,255,0.2); padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 20px; align-items: center; }
        .filter-group { flex: 1; }
        .filter-group label { display: block; margin-bottom: 5px; color: #fff; font-weight: 500; }
        .filter-group select { width: 100%; padding: 10px; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; background-color: rgba(255,255,255,0.8); }
        .filter-buttons button { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-filter { background-color: #fff; color: #1e2a4c; }
        .btn-reset { background-color: #6c757d; color: #fff; }
        .table-container { overflow-x: auto; background-color: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eef2f7; white-space: nowrap; }
        .data-table thead th { background-color: #1e2a4c; color: white; }
    </style>
</head>
<body>

<div class="container">
    <h2>Complete Exam Schedule</h2>

    <div class="filter-container">
        <form method="GET" action="" style="display:contents;">
            <div class="filter-group">
                <label for="exam_type_id">Filter by Exam</label>
                <select name="exam_type_id" id="exam_type_id">
                    <option value="">All Exams</option>
                    <?php foreach ($all_exam_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php if ($filter_exam_type_id == $type['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($type['exam_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="class_id">Filter by Class</label>
                <select name="class_id" id="class_id">
                    <option value="">All Classes</option>
                    <?php foreach ($all_classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php if ($filter_class_id == $class['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter">Apply Filter</button>
                <a href="view_exam_schedule.php" class="btn-reset" style="padding: 10px 20px; border-radius: 6px; text-decoration: none;">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Max Marks</th>
                    <th>Passing Marks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_schedules)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding: 40px;">No exam schedules found matching your criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_schedules as $schedule): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($schedule['exam_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($schedule['class_name'] . ' - ' . $schedule['section_name']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['subject_name']); ?></td>
                            <td><?php echo date("D, M j, Y", strtotime($schedule['exam_date'])); ?></td>
                            <td><?php echo date("g:i A", strtotime($schedule['start_time'])) . ' - ' . date("g:i A", strtotime($schedule['end_time'])); ?></td>
                            <td><?php echo htmlspecialchars($schedule['max_marks']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['passing_marks']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
<?php require_once './admin_footer.php'; ?>