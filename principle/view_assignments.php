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
$filter_subject_id = isset($_GET['subject_id']) && is_numeric($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
$filter_teacher_id = isset($_GET['teacher_id']) && is_numeric($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Pagination Configuration ---
$records_per_page = 10; // Number of assignments to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;


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

$all_subjects = [];
$sql_all_subjects = "SELECT id, subject_name FROM subjects ORDER BY subject_name ASC";
if ($result = mysqli_query($link, $sql_all_subjects)) {
    $all_subjects = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    $message = "Error fetching subjects for filter: " . mysqli_error($link);
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


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_class_id) {
    $where_clauses[] = "a.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if ($filter_subject_id) {
    $where_clauses[] = "a.subject_id = ?";
    $params[] = $filter_subject_id;
    $types .= "i";
}
if ($filter_teacher_id) {
    $where_clauses[] = "a.teacher_id = ?";
    $params[] = $filter_teacher_id;
    $types .= "i";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(a.id)
                      FROM assignments a
                      JOIN classes c ON a.class_id = c.id
                      JOIN subjects s ON a.subject_id = s.id
                      JOIN teachers t ON a.teacher_id = t.id
                      WHERE " . $where_sql;

if ($stmt = mysqli_prepare($link, $total_records_sql)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_records = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
} else {
    $message = "Error counting assignments: " . mysqli_error($link);
    $message_type = "danger";
}
$total_pages = ceil($total_records / $records_per_page);

// Ensure current_page is within bounds
if ($current_page < 1) {
    $current_page = 1;
} elseif ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
} elseif ($total_records == 0) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;


// --- Fetch Assignment Data (with filters and pagination) ---
$assignments = [];
$sql_fetch_assignments = "SELECT
                            a.id, a.title, a.description, a.due_date, a.file_url, a.created_at,
                            c.class_name, c.section_name,
                            s.subject_name,
                            t.full_name AS teacher_name
                        FROM assignments a
                        JOIN classes c ON a.class_id = c.id
                        JOIN subjects s ON a.subject_id = s.id
                        JOIN teachers t ON a.teacher_id = t.id
                        WHERE " . $where_sql . "
                        ORDER BY c.class_name ASC, s.subject_name ASC, a.due_date DESC
                        LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_assignments)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $assignments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $message = "Error fetching assignments: " . mysqli_error($link);
    $message_type = "danger";
}

mysqli_close($link);

// --- Group assignments data for display (Class -> Subject -> List of Assignments) ---
$grouped_assignments = [];
foreach ($assignments as $assignment) {
    $class_key = $assignment['class_name'] . ' - ' . $assignment['section_name'];
    $subject_key = $assignment['subject_name'];

    if (!isset($grouped_assignments[$class_key])) {
        $grouped_assignments[$class_key] = [
            'class_info' => ['name' => $class_key],
            'subjects' => []
        ];
    }
    if (!isset($grouped_assignments[$class_key]['subjects'][$subject_key])) {
        $grouped_assignments[$class_key]['subjects'][$subject_key] = [
            'subject_info' => ['name' => $subject_key],
            'list' => []
        ];
    }
    $grouped_assignments[$class_key]['subjects'][$subject_key]['list'][] = $assignment;
}


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
    <title>View Assignments - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #FFE0B2, #FFCC80, #FFAB40, #FF9100); /* Warm orange gradient */
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
            color: #E65100; /* Deep orange */
            margin-bottom: 30px;
            border-bottom: 2px solid #FFCCBC;
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
            background-color: #fff3e0; /* Light orange background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #ffecb3;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1; /* Allow filter groups to grow */
            min-width: 180px; /* Minimum width for filter dropdowns */
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #ff6f00; /* Darker orange for labels */
        }
        .filter-group select,
        .filter-group input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ffb74d;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #fff;
        }
        .filter-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23ff6f00%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23ff6f00%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 30px;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        .btn-filter, .btn-clear-filter, .btn-print {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-filter {
            background-color: #FF8C00; /* Dark Orange */
            color: #fff;
            border: 1px solid #FF8C00;
        }
        .btn-filter:hover {
            background-color: #F57C00;
        }
        .btn-clear-filter {
            background-color: #6c757d; /* Grey */
            color: #fff;
            border: 1px solid #6c757d;
        }
        .btn-clear-filter:hover {
            background-color: #5a6268;
        }
        .btn-print {
            background-color: #4CAF50; /* Green */
            color: #fff;
            border: 1px solid #4CAF50;
        }
        .btn-print:hover {
            background-color: #388E3C;
        }


        /* Assignments Display */
        .assignments-section-container {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .class-group {
            margin-bottom: 25px;
            border: 1px solid #ffcc80; /* Light orange border */
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .section-header { /* Header for each Class collapsible group */
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #FFB74D; /* Medium orange header */
            color: #fff;
            padding: 15px 20px;
            margin: 0;
            font-size: 1.6em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .section-header:hover {
            background-color: #FFA726; /* Slightly darker orange on hover */
        }
        .section-header h3 {
            margin: 0;
            font-size: 1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-toggle-btn {
            background: none;
            border: none;
            font-size: 1em;
            color: #fff;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .section-toggle-btn.rotated {
            transform: rotate(90deg);
        }
        .section-content { /* Content area inside the collapsible group */
            max-height: 2000px;
            overflow: hidden;
            transition: max-height 0.5s ease-in-out;
            padding: 15px 0;
        }
        .section-content.collapsed {
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
        }

        .subject-assignments-card { /* Card for each subject within a class */
            background-color: #ffe0b2; /* Lighter orange for subject cards */
            border: 1px solid #ffccbc;
            border-radius: 6px;
            margin: 15px 20px;
            padding: 15px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .subject-assignments-card h4 {
            color: #e65100;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px dashed #ffb74d;
            padding-bottom: 10px;
        }
        .assignment-item {
            background-color: #ffffff;
            border: 1px solid #ffe0b2;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .assignment-item strong {
            color: #d17a00;
            font-size: 1.1em;
        }
        .assignment-item p {
            margin: 0;
            font-size: 0.95em;
            color: #555;
        }
        .assignment-item .meta {
            font-size: 0.85em;
            color: #888;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px dashed #eee;
        }
        .assignment-item .meta a {
            color: #007bff;
            text-decoration: none;
        }
        .assignment-item .meta a:hover {
            text-decoration: underline;
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

        /* Pagination Styles (reused from previous pages) */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding: 10px 0;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pagination-info {
            color: #555;
            font-size: 0.95em;
            font-weight: 500;
        }
        .pagination-controls {
            display: flex;
            gap: 5px;
        }
        .pagination-controls a,
        .pagination-controls span {
            display: block;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #ff8c00; /* Dark orange */
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .pagination-controls a:hover {
            background-color: #e9ecef;
            border-color: #ffc107;
        }
        .pagination-controls .current-page,
        .pagination-controls .current-page:hover {
            background-color: #ff8c00;
            color: #fff;
            border-color: #ff8c00;
            cursor: default;
        }
        .pagination-controls .disabled,
        .pagination-controls .disabled:hover {
            color: #6c757d;
            background-color: #e9ecef;
            border-color: #dee2e6;
            cursor: not-allowed;
        }

        /* Print Specific Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .printable-area, .printable-area * {
                visibility: visible;
            }
            .printable-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                font-size: 12pt;
                padding: 10mm;
            }
            .printable-area h2 {
                color: #000;
                border-bottom: 2px solid #ccc;
                font-size: 18pt;
            }
            .printable-area .class-group {
                border: 1px solid #ccc;
                margin-bottom: 15px;
                page-break-inside: avoid;
            }
            .printable-area .section-header {
                background-color: #eee;
                color: #333;
                border-bottom: 1px solid #ddd;
                font-size: 14pt;
                cursor: default;
            }
            .printable-area .section-toggle-btn {
                display: none;
            }
            .printable-area .section-content {
                max-height: none !important;
                overflow: visible !important;
                transition: none !important;
                padding: 10px 0 !important;
            }
            .printable-area .subject-assignments-card {
                margin: 10px 15px;
                border: 1px solid #ddd;
                background-color: #f9f9f9;
            }
            .printable-area .subject-assignments-card h4 {
                color: #555;
                font-size: 12pt;
                border-bottom: 1px dashed #ccc;
            }
            .printable-area .assignment-item {
                border: 1px solid #eee;
                background-color: #fff;
                padding: 10px;
                margin-bottom: 8px;
            }
            .printable-area .assignment-item strong {
                color: #333;
                font-size: 11pt;
            }
            .printable-area .assignment-item p {
                font-size: 10pt;
            }
            .printable-area .assignment-item .meta {
                font-size: 9pt;
                color: #777;
            }
            .printable-area .pagination-container,
            .printable-area .filter-section,
            .printable-area .btn-print {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-folder-open"></i> View Assignments</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter and Search Form -->
        <div class="filter-section">
            <form action="view_assignments.php" method="GET" style="display:contents;">
                <div class="filter-group">
                    <label for="filter_class_id"><i class="fas fa-school"></i> Class:</label>
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
                    <label for="filter_subject_id"><i class="fas fa-book-open"></i> Subject:</label>
                    <select id="filter_subject_id" name="subject_id">
                        <option value="">-- All Subjects --</option>
                        <?php foreach ($all_subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['id']); ?>"
                                <?php echo ($filter_subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_teacher_id"><i class="fas fa-chalkboard-teacher"></i> Teacher:</label>
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
                <div class="filter-group">
                    <label for="search_query"><i class="fas fa-search"></i> Search:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Assignment title/description">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_class_id || $filter_subject_id || $filter_teacher_id || !empty($search_query)): ?>
                        <a href="view_assignments.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printAssignments()"><i class="fas fa-print"></i> Print Assignments</button>
                </div>
            </form>
        </div>

        <!-- Assignments Display Section -->
        <div class="assignments-section-container printable-area">
            <?php if (empty($grouped_assignments)): ?>
                <p class="no-results">No assignments found matching your criteria.</p>
            <?php else: ?>
                <?php $class_index = 0; ?>
                <?php foreach ($grouped_assignments as $class_key => $class_data): ?>
                    <?php $class_index++; ?>
                    <div class="class-group">
                        <div class="section-header" onclick="toggleSection('class-content-<?php echo $class_index; ?>', this.querySelector('.section-toggle-btn'))"
                             aria-expanded="true" aria-controls="class-content-<?php echo $class_index; ?>">
                            <h3><i class="fas fa-school"></i> Class: <?php echo htmlspecialchars($class_data['class_info']['name']); ?></h3>
                            <button class="section-toggle-btn">
                                <i class="fas fa-chevron-down"></i> <!-- Initially expanded -->
                            </button>
                        </div>
                        <div id="class-content-<?php echo $class_index; ?>" class="section-content">
                            <?php foreach ($class_data['subjects'] as $subject_key => $subject_data): ?>
                                <div class="subject-assignments-card">
                                    <h4><i class="fas fa-book"></i> Subject: <?php echo htmlspecialchars($subject_data['subject_info']['name']); ?></h4>
                                    <?php foreach ($subject_data['list'] as $assignment): ?>
                                        <div class="assignment-item">
                                            <strong><?php echo htmlspecialchars($assignment['title']); ?></strong>
                                            <p><?php echo htmlspecialchars($assignment['description']); ?></p>
                                            <div class="meta">
                                                <span>Assigned by: <?php echo htmlspecialchars($assignment['teacher_name']); ?></span>
                                                <span>Due: <?php echo date("M j, Y", strtotime($assignment['due_date'])); ?></span>
                                                <?php if ($assignment['file_url']): ?>
                                                    <a href="<?php echo htmlspecialchars($assignment['file_url']); ?>" target="_blank" title="Download Assignment"><i class="fas fa-download"></i> File</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($total_records > 0): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $base_url_params = array_filter([
                                'class_id' => $filter_class_id,
                                'subject_id' => $filter_subject_id,
                                'teacher_id' => $filter_teacher_id,
                                'search' => $search_query
                            ]);
                            $base_url = "view_assignments.php?" . http_build_query($base_url_params);
                            ?>

                            <?php if ($current_page > 1): ?>
                                <a href="<?php echo $base_url . '&page=' . ($current_page - 1); ?>">Previous</a>
                            <?php else: ?>
                                <span class="disabled">Previous</span>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<a href="' . $base_url . '&page=1">1</a>';
                                if ($start_page > 2) {
                                    echo '<span>...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++):
                                if ($i == $current_page): ?>
                                    <span class="current-page"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo $base_url . '&page=' . $i; ?>"><?php echo $i; ?></a>
                                <?php endif;
                            endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span>...</span>';
                                }
                                echo '<a href="' . $base_url . '&page=' . $total_pages . '">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?php echo $base_url . '&page=' . ($current_page + 1); ?>">Next</a>
                            <?php else: ?>
                                <span class="disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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

        // Initialize collapsed state on page load (all sections start expanded)
        document.querySelectorAll('.assignments-section-container .section-header').forEach(header => {
            const contentId = header.getAttribute('aria-controls');
            const content = document.getElementById(contentId);
            
            if (content) { // Ensure content element exists
                content.style.maxHeight = content.scrollHeight + "px"; // Temporarily set height to its full size for smooth transition

                setTimeout(() => {
                    content.style.maxHeight = null; // Remove inline style
                    // If you want sections to start collapsed by default, add the 'collapsed' class and rotate icon here
                    // e.g., content.classList.add('collapsed');
                    //       header.querySelector('.fas').classList.remove('fa-chevron-down');
                    //       header.querySelector('.fas').classList.add('fa-chevron-right');
                    //       header.setAttribute('aria-expanded', 'false');
                }, 10);
            }
        });
    });

    // Function to print only the assignments section
    function printAssignments() {
        // Expand all collapsible sections before printing
        document.querySelectorAll('.section-content.collapsed').forEach(content => {
            content.classList.remove('collapsed');
            const button = content.previousElementSibling.querySelector('.section-toggle-btn');
            if (button) {
                button.querySelector('.fas').classList.remove('fa-chevron-right');
                button.querySelector('.fas').classList.add('fa-chevron-down');
                button.setAttribute('aria-expanded', 'true');
            }
        });

        const printableContent = document.querySelector('.assignments-section-container').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=1000');
        
        printWindow.document.write('<html><head><title>Assignments Print</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
        // Include relevant styles from this page directly in the print window
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
            h2 { color: #E65100; border-bottom: 2px solid #FFCCBC; padding-bottom: 12px; font-size: 2.2em; font-weight: 700; margin-bottom: 30px; }
            .class-group { border: 1px solid #ffcc80; border-radius: 8px; margin-bottom: 15px; overflow: hidden; page-break-inside: avoid; }
            .section-header { background-color: #FFB74D; color: #fff; padding: 15px 20px; margin: 0; font-size: 1.6em; font-weight: 600; display: flex; align-items: center; gap: 10px; }
            .section-header h3 { margin: 0; font-size: 1em; display: flex; align-items: center; gap: 10px; }
            .section-toggle-btn { display: none; } /* Hide toggle in print */
            .section-content { padding: 15px 0; max-height: none !important; overflow: visible !important; transition: none !important; }
            .subject-assignments-card { background-color: #ffe0b2; border: 1px solid #ffccbc; border-radius: 6px; margin: 15px 20px; padding: 15px; page-break-inside: avoid; }
            .subject-assignments-card h4 { color: #e65100; margin-top: 0; margin-bottom: 15px; font-size: 1.3em; display: flex; align-items: center; gap: 8px; border-bottom: 1px dashed #ffb74d; padding-bottom: 10px; }
            .assignment-item { background-color: #ffffff; border: 1px solid #ffe0b2; border-radius: 5px; padding: 15px; margin-bottom: 10px; box-shadow: none; display: flex; flex-direction: column; gap: 5px; }
            .assignment-item strong { color: #d17a00; font-size: 1.1em; }
            .assignment-item p { margin: 0; font-size: 0.95em; color: #555; }
            .assignment-item .meta { font-size: 0.85em; color: #888; display: flex; justify-content: space-between; align-items: center; margin-top: 5px; padding-top: 5px; border-top: 1px dashed #eee; }
            .assignment-item .meta a { color: #007bff; text-decoration: none; }
            .no-results, .pagination-container, .filter-section, .btn-print { display: none; }
            .fas { margin-right: 5px; }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printableContent);
        printWindow.document.write('</body></html>');
        
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        // printWindow.close(); // Optionally close after printing, but users might want to review
    }
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>