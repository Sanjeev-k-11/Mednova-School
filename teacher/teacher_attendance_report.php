<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- FILTER & DATE SETUP ---
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$class_filter = $_GET['class_id'] ?? '';
$search_query = $_GET['search'] ?? '';

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$month_name = date('F', mktime(0, 0, 0, $current_month, 10));

// --- DATA FETCHING ---

// 1. Get Teacher's assigned classes for the filter dropdown
$assigned_classes = [];
$sql_get_classes = "SELECT DISTINCT c.id, c.class_name, c.section_name FROM class_subject_teacher cst JOIN classes c ON cst.class_id = c.id WHERE cst.teacher_id = ? ORDER BY c.class_name";
if ($stmt_classes = mysqli_prepare($link, $sql_get_classes)) {
    mysqli_stmt_bind_param($stmt_classes, "i", $teacher_id);
    mysqli_stmt_execute($stmt_classes);
    $result = mysqli_stmt_get_result($stmt_classes);
    $assigned_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_classes);
}
$assigned_class_ids = !empty($assigned_classes) ? array_column($assigned_classes, 'id') : [];

// 2. Get students based on filters
$students = [];
if (!empty($assigned_class_ids)) {
    $sql_students = "SELECT id, roll_number, first_name, middle_name, last_name FROM students WHERE 1=1";
    $params = [];
    $types = "";

    // If a class is filtered, use it. Otherwise, get students from all assigned classes.
    if (!empty($class_filter)) {
        // Security check: ensure the filtered class is one the teacher is assigned to
        if(in_array($class_filter, $assigned_class_ids)) {
            $sql_students .= " AND class_id = ?";
            $params[] = $class_filter;
            $types .= "i";
        } else {
            // If class_filter is invalid for this teacher, treat as no filter
            $class_filter = ''; 
        }
    } 
    
    // Apply class_id filter if valid, or apply all assigned classes if no specific filter
    if(!empty($class_filter)) {
        $sql_students .= " AND class_id = ?";
        $params[] = $class_filter;
        $types .= "i";
    } else if (!empty($assigned_class_ids)) {
        $sql_students .= " AND class_id IN (" . implode(',', array_fill(0, count($assigned_class_ids), '?')) . ")";
        $params = array_merge($params, $assigned_class_ids);
        $types .= str_repeat('i', count($assigned_class_ids));
    }


    if(!empty($search_query)) {
        $sql_students .= " AND CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?";
        $params[] = "%" . $search_query . "%";
        $types .= "s";
    }
    $sql_students .= " ORDER BY roll_number, first_name";

    if($stmt_students = mysqli_prepare($link, $sql_students)) {
        if (!empty($params)) {
             mysqli_stmt_bind_param($stmt_students, $types, ...$params);
        }
        mysqli_stmt_execute($stmt_students);
        $result = mysqli_stmt_get_result($stmt_students);
        $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_students);
    }
}
$student_ids = !empty($students) ? array_column($students, 'id') : [];

// 3. Get all attendance data for these students for the selected month
$attendance_records = []; // [student_id][day] = status
if (!empty($student_ids)) {
    // Construct placeholders for student_ids
    $student_id_placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    
    $sql_attendance = "SELECT student_id, DAY(attendance_date) as day, status FROM attendance WHERE student_id IN (" . $student_id_placeholders . ") AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?";
    
    // Prepare params and types for binding
    $params_att = $student_ids;
    $types_att = str_repeat('i', count($student_ids));
    $params_att[] = $current_month;
    $types_att .= 'i';
    $params_att[] = $current_year;
    $types_att .= 'i';
    
    if($stmt_att = mysqli_prepare($link, $sql_attendance)) {
        mysqli_stmt_bind_param($stmt_att, $types_att, ...$params_att);
        mysqli_stmt_execute($stmt_att);
        $result = mysqli_stmt_get_result($stmt_att);
        while($row = mysqli_fetch_assoc($result)) {
            $attendance_records[$row['student_id']][$row['day']] = $row['status'];
        }
        mysqli_stmt_close($stmt_att);
    } else {
        error_log("DB Error in attendance records query: " . mysqli_error($link));
    }
}

// 4. Calculate summary statistics
$overall_present = 0;
$overall_total_marked_days = 0; // Total days marked for any status for all students

mysqli_close($link);
require_once './teacher_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(-45deg, #e0f2f7, #e3f2fd, #bbdefb, #90caf9); /* Light Blue/Azure theme */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .page-header { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2.5rem 2rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); text-align: center; }
        .page-header h1 { font-weight: 700; color: #1a2a4b; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); margin-bottom: 1rem; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; gap: 15px; }
        .welcome-info-block { padding: 1rem; background: rgba(255, 255, 255, 0.5); border-radius: 0.5rem; display: inline-block; margin-top: 1rem; border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 2px 5px rgba(0,0,0,0.03); }
        .welcome-info { font-weight: 500; color: #666; margin-bottom: 0; font-size: 0.95rem; }
        .welcome-info strong { color: #333; }

        .dashboard-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); }
        .panel-header { font-size: 1.25rem; font-weight: 600; color: #1a2a4b; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }

        /* Filter Form Specifics */
        .filter-form .form-label { color: #1a2a4b; font-weight: 600; }
        .filter-form .form-select, .filter-form .form-control {
            background-color: rgba(255,255,255,0.8);
            border: 1px solid rgba(0,0,0,0.15);
            border-radius: 0.5rem;
            padding: 0.5rem 0.8rem;
            color: #333;
            font-size: 0.9rem;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        .filter-form .form-select:focus, .filter-form .form-control:focus {
            outline: none;
            border-color: #1a2a4b;
            box-shadow: 0 0 0 0.25rem rgba(26, 42, 75, 0.25);
            background-color: #fff;
        }
        .filter-form .btn-primary-themed {
            background-color: #1a2a4b; color: #fff; font-weight: 600; padding: 0.5rem 1.5rem; border-radius: 0.5rem; border: none; transition: background-color 0.2s, transform 0.2s;
        }
        .filter-form .btn-primary-themed:hover { background-color: #0d1a33; transform: translateY(-2px); color: #fff; }

        /* Attendance Table */
        .attendance-table-wrapper { overflow-x: auto; margin-top: 1.5rem; }
        .attendance-table { width: 100%; border-collapse: separate; border-spacing: 0; background-color: rgba(255,255,255,0.4); border-radius: 10px; overflow: hidden; }
        .attendance-table thead { background-color: rgba(0,0,0,0.08); }
        .attendance-table th { padding: 12px 10px; text-align: center; font-weight: 600; color: #1a2a4b; text-transform: uppercase; font-size: 0.8rem; border-bottom: 1px solid rgba(0,0,0,0.1); white-space: nowrap; }
        .attendance-table th:first-child, .attendance-table td:first-child { text-align: left; padding-left: 15px; }
        .attendance-table th:nth-child(2), .attendance-table td:nth-child(2) { text-align: left; } /* Roll no. */
        .attendance-table tbody tr { background-color: rgba(255,255,255,0.4); transition: background-color 0.2s ease; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .attendance-table tbody tr:hover { background-color: rgba(255,255,255,0.6); }
        .attendance-table tbody tr:last-child { border-bottom: none; }
        .attendance-table td { padding: 8px 5px; font-size: 0.85rem; color: #333; font-weight: 500; white-space: nowrap; }
        .attendance-table td.font-semibold { font-weight: 600; }

        /* Day specific styles */
        .table-header-day { min-width: 35px; max-width: 35px; } /* Fixed width for day columns */
        .weekend { background-color: rgba(0,0,0,0.15); } /* Darker background for weekends */
        .weekend span { color: rgba(255,255,255,0.7); } /* Lighter text for weekend dates */

        /* Status text colors */
        .status-P { color: #28a745; font-weight: 700; } /* Green */
        .status-A { color: #dc3545; font-weight: 700; } /* Red */
        .status-L { color: #ffc107; font-weight: 700; } /* Yellow */
        .status-HD { color: #007bff; font-weight: 700; } /* Blue */
        .status-default { color: rgba(0,0,0,0.3); } /* Grey for unmarked */

        /* Summary Card */
        .summary-card { 
            background-color: rgba(0,0,0,0.08); 
            border-radius: 10px; 
            padding: 1.5rem; 
            text-align: center; 
            margin-top: 1.5rem; 
            color: #1a2a4b; 
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.1);
        }
        .summary-card h3 { font-weight: 700; font-size: 1.3rem; margin-bottom: 0.5rem; color: #1a2a4b;}
        .summary-card .percentage { font-size: 3rem; font-weight: 700; margin-top: 1rem; }
        .summary-card .text-small { font-size: 0.9rem; opacity: 0.8; }
        .summary-card .text-green { color: #28a745; }
        .summary-card .text-yellow { color: #ffc107; }
        .summary-card .text-red { color: #dc3545; }

        /* No Students Found Message */
        .no-students-message {
            background-color: rgba(255,255,255,0.8);
            border: 1px solid rgba(0,0,0,0.2);
            border-radius: 10px;
            padding: 3rem;
            text-align: center;
            color: #1a2a4b;
            font-weight: 500;
            margin-top: 2rem;
        }
        .no-students-message i { font-size: 3rem; margin-bottom: 1.5rem; color: #1a2a4b; }
        .no-students-message p { font-size: 1.1rem; margin-bottom: 0; }

        /* Legend */
        .attendance-legend {
            background-color: rgba(0,0,0,0.08);
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-top: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            justify-content: center;
            color: #1a2a4b;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .attendance-legend span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .legend-indicator {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 5px;
            display: inline-block;
            text-align: center;
            line-height: 1.5rem;
            font-weight: 700;
            font-size: 0.85rem;
            color: #fff; /* Default text color for indicators */
        }
        .legend-indicator.P { background-color: #28a745; }
        .legend-indicator.A { background-color: #dc3545; }
        .legend-indicator.L { background-color: #ffc107; color: #333;}
        .legend-indicator.HD { background-color: #007bff; }
        .legend-indicator.- { background-color: rgba(0,0,0,0.3); }


        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-container { margin-top: 20px; padding: 10px; }
            .page-header h1 { font-size: 2em; flex-direction: column; gap: 5px; }
            .welcome-info-block { width: 100%; text-align: center; }
            .filter-form { grid-template-columns: 1fr; }
            .attendance-table th, .attendance-table td { padding: 8px 5px; font-size: 0.75rem; }
            .attendance-table th:first-child, .attendance-table td:first-child { min-width: 120px; }
            .attendance-table th:nth-child(2), .attendance-table td:nth-child(2) { min-width: 60px; }
            .table-header-day { min-width: 30px; max-width: 30px; font-size: 0.7rem;}
            .table-header-day span { display: none; } /* Hide day name on very small screens */
            .attendance-legend { flex-direction: column; align-items: center; gap: 1rem; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <header class="page-header">
        <h1 class="page-title">
            <i class="fas fa-chart-bar"></i> Attendance Report
        </h1>
        <div class="welcome-info-block">
            <p class="welcome-info">
                Teacher: <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
            </p>
        </div>
    </header>

    <div class="dashboard-panel">
        <!-- Filter Form -->
        <form method="GET" class="filter-form row g-3 align-items-end mb-4">
            <div class="col-md-3 col-sm-6">
                <label for="month" class="form-label">Month</label>
                <select name="month" id="month" class="form-select">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php if($m == $current_month) echo 'selected'; ?>><?php echo date('F', mktime(0,0,0,$m, 1, date('Y'))); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label for="year" class="form-label">Year</label>
                <select name="year" id="year" class="form-select">
                    <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if($y == $current_year) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label for="class_id" class="form-label">Class</label>
                <select name="class_id" id="class_id" class="form-select">
                    <option value="">All My Classes</option>
                    <?php foreach($assigned_classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php if($class['id'] == $class_filter) echo 'selected'; ?>><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                 <label for="search" class="form-label">Search Student</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Student name..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="col-md-1 col-sm-12 text-end">
                <button type="submit" class="btn btn-primary-themed w-100"><i class="fas fa-search"></i></button>
            </div>
        </form>
        
        <!-- Attendance Legend -->
        <div class="attendance-legend">
            <span><span class="legend-indicator P">P</span> Present</span>
            <span><span class="legend-indicator A">A</span> Absent</span>
            <span><span class="legend-indicator L">L</span> Late</span>
            <span><span class="legend-indicator HD">HD</span> Half Day</span>
            <span><span class="legend-indicator -">-</span> Unmarked</span>
        </div>


        <?php if (empty($students)): ?>
            <div class="no-students-message">
                <i class="fas fa-info-circle"></i>
                <p>No students found for the selected criteria or no classes assigned to you.</p>
            </div>
        <?php else: ?>
            <!-- Report Table -->
            <div class="attendance-table-wrapper">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th class="text-start">Student Name</th>
                            <th class="text-start">Roll No.</th>
                            <?php for ($i = 1; $i <= $days_in_month; $i++): 
                                $day_name = date('D', mktime(0,0,0,$current_month, $i, $current_year));
                                $is_weekend = in_array($day_name, ['Sat', 'Sun']);
                            ?>
                                <th class="table-header-day <?php if($is_weekend) echo 'weekend'; ?>">
                                    <?php echo $i; ?><br><span class="font-normal text-xs"><?php echo $day_name; ?></span>
                                </th>
                            <?php endfor; ?>
                            <th class="p-3 text-success">P</th>
                            <th class="p-3 text-danger">A</th>
                            <th class="p-3 text-warning">L</th>
                            <th class="p-3 text-primary">HD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $student): 
                            $summary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0];
                        ?>
                            <tr class="hover:bg-white/5">
                                <td class="font-semibold"><?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name'])); ?></td>
                                <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                <?php for ($i = 1; $i <= $days_in_month; $i++): 
                                    $status = $attendance_records[$student['id']][$i] ?? null;
                                    if($status) $summary[$status]++;
                                    $day_name = date('D', mktime(0,0,0,$current_month, $i, $current_year));
                                    $is_weekend = in_array($day_name, ['Sat', 'Sun']);
                                ?>
                                    <td class="p-2 <?php if($is_weekend) echo 'weekend'; ?>">
                                        <?php
                                            switch($status) {
                                                case 'Present': echo '<span class="status-P" title="Present">P</span>'; break;
                                                case 'Absent': echo '<span class="status-A" title="Absent">A</span>'; break;
                                                case 'Late': echo '<span class="status-L" title="Late">L</span>'; break;
                                                case 'Half Day': echo '<span class="status-HD" title="Half Day">HD</span>'; break;
                                                default: echo '<span class="status-default">-</span>'; break;
                                            }
                                        ?>
                                    </td>
                                <?php endfor; 
                                    $overall_present += $summary['Present'];
                                    $overall_total_marked_days += array_sum($summary); // Only count marked days
                                ?>
                                <td class="font-bold text-success"><?php echo $summary['Present']; ?></td>
                                <td class="font-bold text-danger"><?php echo $summary['Absent']; ?></td>
                                <td class="font-bold text-warning"><?php echo $summary['Late']; ?></td>
                                <td class="font-bold text-primary"><?php echo $summary['Half Day']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary Card -->
            <?php 
                $attendance_percentage = $overall_total_marked_days > 0 ? round(($overall_present / $overall_total_marked_days) * 100, 1) : 0;
            ?>
            <div class="summary-card">
                <h3>Overall Attendance for <?php echo "$month_name $current_year"; ?></h3>
                <p class="percentage <?php 
                    echo $attendance_percentage >= 80 ? 'text-success' : 
                         ($attendance_percentage >= 50 ? 'text-warning' : 'text-danger'); 
                ?>">
                    <?php echo $attendance_percentage; ?>%
                </p>
                <p class="text-small">(<?php echo $overall_present; ?> present days out of <?php echo $overall_total_marked_days; ?> total marked days)</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php require_once './teacher_footer.php'; ?>