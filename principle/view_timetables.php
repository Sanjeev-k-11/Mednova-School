<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"];
$principal_name = $_SESSION["full_name"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Filter Parameters ---
$filter_class_id = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$filter_teacher_id = isset($_GET['teacher_id']) && is_numeric($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;

// --- Fetch Filter Dropdown Data ---
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching classes for filter: " . mysqli_error($link);
    $message_type = "danger";
}

$all_teachers = [];
$sql_all_teachers = "SELECT id, full_name FROM teachers WHERE is_blocked = 0 ORDER BY full_name ASC";
if ($result = mysqli_query($link, $sql_all_teachers)) {
    $all_teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching teachers for filter: " . mysqli_error($link);
    $message_type = "danger";
}


// --- Fetch Timetable Data (with filters) ---
$timetable_data = [];
$sql_fetch_timetable = "SELECT
                            ct.id AS timetable_entry_id,
                            ct.day_of_week,
                            cp.start_time,
                            cp.end_time,
                            c.id AS class_id,
                            c.class_name,
                            c.section_name,
                            s.subject_name,
                            t.full_name AS teacher_name,
                            t.id AS teacher_id
                        FROM class_timetable ct
                        JOIN classes c ON ct.class_id = c.id
                        JOIN subjects s ON ct.subject_id = s.id
                        JOIN teachers t ON ct.teacher_id = t.id
                        JOIN class_periods cp ON ct.period_id = cp.id
                        WHERE 1=1 "; // Start with 1=1 to easily append conditions

$params = [];
$types = "";

if ($filter_class_id) {
    $sql_fetch_timetable .= " AND ct.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if ($filter_teacher_id) {
    $sql_fetch_timetable .= " AND ct.teacher_id = ?";
    $params[] = $filter_teacher_id;
    $types .= "i";
}

// Order by Class, then Day of Week (specific order), then Start Time
$sql_fetch_timetable .= " ORDER BY c.class_name ASC, c.section_name ASC, 
                          FIELD(ct.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                          cp.start_time ASC";

if ($stmt = mysqli_prepare($link, $sql_fetch_timetable)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $timetable_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching timetable: " . mysqli_error($link);
    $message_type = "danger";
}

mysqli_close($link);

// --- Group timetable data for display ---
$grouped_timetable = [];
foreach ($timetable_data as $entry) {
    $class_key = $entry['class_name'] . ' - ' . $entry['section_name'];
    $grouped_timetable[$class_key]['class_info'] = ['id' => $entry['class_id'], 'name' => $class_key];
    $grouped_timetable[$class_key]['days'][$entry['day_of_week']][] = $entry;
}

// Order of days for display
$day_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];


// --- Retrieve and clear session messages ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- PAGE INCLUDES ---
require_once './principal_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetables - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #00C6FF, #0072FF, #4CAF50, #2196F3); /* Cool Blue/Green gradient */
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            color: #333;
        }
        @keyframes gradientAnimation {
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.95); /* Slightly transparent white */
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        h2 {
            color: #0056b3; /* Darker blue */
            margin-bottom: 30px;
            border-bottom: 2px solid #a7d9ff;
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 2.2em;
            font-weight: 700;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Filter Section */
        .filter-section {
            background-color: #e3f2fd; /* Light blue background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #bbdefb;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1; /* Allow filter groups to grow */
            min-width: 200px; /* Minimum width for filter dropdowns */
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1a237e; /* Darker blue for labels */
        }
        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #90caf9;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #fff;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%232196f3%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%232196f3%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
        }
        .btn-filter, .btn-clear-filter {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-filter {
            background-color: #2196F3; /* Blue */
            color: #fff;
            border: 1px solid #2196F3;
        }
        .btn-filter:hover {
            background-color: #1976D2;
        }
        .btn-clear-filter {
            background-color: #6c757d; /* Grey */
            color: #fff;
            border: 1px solid #6c757d;
        }
        .btn-clear-filter:hover {
            background-color: #5a6268;
        }


        /* Timetable Display Section */
        .timetable-section-container { /* New container for the whole section */
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .section-header { /* Style for headers of collapsible groups */
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #64b5f6; /* Light blue header for class */
            color: #fff;
            padding: 15px 20px;
            margin: 0 0 15px 0; /* Margin below the header */
            font-size: 1.6em;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .section-header:hover {
            background-color: #42a5f5; /* Darker blue on hover */
        }
        .section-header h3 {
            margin: 0;
            font-size: 1em; /* Make h3 inherit parent font-size */
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-toggle-btn {
            background: none;
            border: none;
            font-size: 1em; /* Adjust icon size relative to h3 */
            color: #fff;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .section-toggle-btn.rotated {
            transform: rotate(90deg); /* Rotate for collapsed state */
        }

        .timetable-content { /* Content inside the collapsible section */
            max-height: 2000px; /* Arbitrary large value for initial/expanded state */
            overflow: hidden;
            transition: max-height 0.5s ease-in-out; /* Smooth collapse/expand */
            padding-bottom: 20px; /* Space inside collapsed section */
        }
        .timetable-content.collapsed {
            max-height: 0;
            padding-bottom: 0;
        }

        .day-timetable-header { /* Used to be .day-timetable */
            background-color: #e0f2f7; /* Very light blue for day headers */
            padding: 10px 20px;
            border-bottom: 1px solid #c8e6c9;
            font-weight: 600;
            color: #1a237e; /* Darker blue text */
            font-size: 1.1em;
            border-top: 1px solid #bbdefb;
            margin-top: 15px; /* Spacing between days */
        }
        .day-timetable-header:first-of-type {
            margin-top: 0; /* No top margin for the first day */
        }

        .period-table {
            width: 100%;
            border-collapse: collapse;
        }
        .period-table th, .period-table td {
            border: 1px solid #e0f2f7; /* Lighter border for periods */
            padding: 12px 15px;
            text-align: left;
            vertical-align: middle;
        }
        .period-table th {
            background-color: #b3e5fc; /* Slightly darker blue for table header */
            color: #1a237e;
            font-weight: 700;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        .period-table tr:nth-child(even) {
            background-color: #fdfdfd;
        }
        .period-table tr:hover {
            background-color: #eef7fc;
        }
        .text-center {
            text-align: center;
        }
        .text-muted {
            color: #6c757d;
        }

        /* No results message */
        .no-results {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: #6c757d;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group {
                min-width: unset;
                width: 100%;
            }
            .btn-filter, .btn-clear-filter {
                width: 100%;
                justify-content: center;
            }
            .section-header h3 {
                font-size: 1.2em; /* Smaller on mobile */
            }
            .day-timetable-header {
                font-size: 1em;
            }
            .period-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="container">
        <h2><i class="fas fa-calendar-alt"></i> View School Timetables</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="filter-section">
            <form action="view_timetables.php" method="GET" style="display:contents;">
                <div class="filter-group">
                    <label for="filter_class_id"><i class="fas fa-school"></i> Filter by Class:</label>
                    <select id="filter_class_id" name="class_id">
                        <option value="">-- All Classes --</option>
                        <?php foreach ($all_classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                <?php echo ($filter_class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_teacher_id"><i class="fas fa-chalkboard-teacher"></i> Filter by Teacher:</label>
                    <select id="filter_teacher_id" name="teacher_id">
                        <option value="">-- All Teachers --</option>
                        <?php foreach ($all_teachers as $teacher): ?>
                            <option value="<?php echo htmlspecialchars($teacher['id']); ?>"
                                <?php echo ($filter_teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_class_id || $filter_teacher_id): ?>
                        <a href="view_timetables.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Timetable Display Section -->
        <div class="timetable-section-container"> <!-- New container for the whole section -->
            <?php if (empty($grouped_timetable)): ?>
                <p class="no-results">No timetable entries found matching your criteria.</p>
            <?php else: ?>
                <?php $class_index = 0; ?>
                <?php foreach ($grouped_timetable as $class_key => $class_data): ?>
                    <?php $class_index++; ?>
                    <div class="class-timetable-group">
                        <div class="section-header" onclick="toggleSection('timetable-content-<?php echo $class_index; ?>', this.querySelector('.section-toggle-btn'))"
                             aria-expanded="true" aria-controls="timetable-content-<?php echo $class_index; ?>">
                            <h3><i class="fas fa-school"></i> Class: <?php echo htmlspecialchars($class_data['class_info']['name']); ?></h3>
                            <button class="section-toggle-btn">
                                <i class="fas fa-chevron-down"></i> <!-- Down arrow initially -->
                            </button>
                        </div>
                        <div id="timetable-content-<?php echo $class_index; ?>" class="timetable-content"> <!-- Content is initially open -->
                            <?php
                            // Sort days according to predefined order
                            $sorted_days = [];
                            foreach ($day_order as $day) {
                                if (isset($class_data['days'][$day])) {
                                    $sorted_days[$day] = $class_data['days'][$day];
                                }
                            }
                            ?>
                            <?php foreach ($sorted_days as $day_name => $day_entries): ?>
                                <div class="day-timetable-header">Day: <?php echo htmlspecialchars($day_name); ?></div>
                                <div style="overflow-x:auto;">
                                    <table class="period-table">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Subject</th>
                                                <th>Teacher</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($day_entries as $period): ?>
                                                <tr>
                                                    <td><?php echo date("g:i A", strtotime($period['start_time'])) . ' - ' . date("g:i A", strtotime($period['end_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars($period['subject_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($period['teacher_name']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to toggle the collapse state of a section
        window.toggleSection = function(contentId, button) {
            const content = document.getElementById(contentId);
            const icon = button.querySelector('.fas');

            if (content.classList.contains('collapsed')) {
                // Expand the section
                content.classList.remove('collapsed');
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
                button.setAttribute('aria-expanded', 'true');
            } else {
                // Collapse the section
                content.classList.add('collapsed');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
                button.setAttribute('aria-expanded', 'false');
            }
        };

        // Initialize collapsed state on page load for any sections that should start collapsed
        // In this case, all timetable sections are set to start expanded, so no initial collapse logic is strictly needed
        // but if you wanted to change default, you'd iterate here.
    });
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>