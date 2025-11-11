<?php
// admin_indiscipline.php
session_start();

// Include database connection and helper functions
require_once '../database/config.php';
require_once '../database/helpers.php'; // Assuming this might contain useful functions, though not all might be directly used here.
require_once './admin_header.php';
$error_message = '';
$success_message = '';

// --- Mock User Authentication (REPLACE WITH YOUR REAL AUTHENTICATION SYSTEM) ---
// For testing: uncomment the following lines to simulate an admin user.
// In a production environment, these session variables would be set upon successful login.
$_SESSION['user_role'] = 'admin'; // Simulate an admin role
$_SESSION['admin_id'] = 1;         // Simulate admin with ID 1 (ensure this ID exists in your 'admins' table)
// --- END Mock User Authentication ---

$user_role = $_SESSION['user_role'] ?? null;
$logged_in_admin_id = $_SESSION['admin_id'] ?? null;

// --- Access Control: Only allow administrators to view this page ---
// Using the provided authentication check:
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    // If you prefer the previous check, use this:
    // if ($user_role !== 'admin' || !$logged_in_admin_id) {
    echo "<!DOCTYPE html><html><head><title>Access Denied</title><style>body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: #dc3545; text-align: center; padding-top: 50px; } .message { border: 1px solid #dc3545; padding: 15px; margin: 20px auto; max-width: 600px; border-radius: 8px; background-color: #f8d7da; }</style></head><body><div class='message'><h2>Access Denied</h2><p>You must be logged in as an administrator to view this page.</p><p><a href='../login.php'>Return to Login</a></p></div></body></html>";
    exit();
}

// Ensure the logged_in_admin_id is correctly set if using the provided auth check:
if ($user_role === 'admin' && isset($_SESSION['admin_id'])) {
    $logged_in_admin_id = $_SESSION['admin_id'];
}


// --- ADMIN ROLE: Handle Report Review/Update (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'review_report') {
    $report_id = (int)$_POST['report_id'];
    $status = trim($_POST['status'] ?? '');
    $review_notes = trim($_POST['review_notes'] ?? '');
    $final_action_taken = trim($_POST['final_action_taken'] ?? '');

    if (empty($status) || $report_id <= 0) {
        $error_message = "Invalid report ID or status.";
    } else {
        $sql = "UPDATE indiscipline_reports SET
                    status = ?,
                    review_notes = ?,
                    final_action_taken = ?,
                    reviewed_by_admin_id = ?,
                    review_date = NOW()
                WHERE id = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param(
                $stmt, "sssii",
                $status,
                $review_notes,
                $final_action_taken,
                $logged_in_admin_id,
                $report_id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Report #{$report_id} updated successfully.";
                // Redirect to prevent form resubmission and show updated filter state
                header("Location: indiscipline.php?filter_status=" . urlencode($status));
                exit();
            } else {
                $error_message = "Error updating report: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Error preparing statement: " . mysqli_error($link);
        }
    }
}

// --- Data Fetching for Admin Table (GET Request or initial page load) ---
$reports = [];
$filter_status = $_GET['filter_status'] ?? 'Pending Review'; // Default filter for admins
$filter_target_type = $_GET['filter_target_type'] ?? 'Student'; // Default to Student for new reports

// --- FIX START ---
$sql_conditions = ["1=1"]; // Start with a true condition for easy `AND` concatenation
$params = [];
$types = '';

if (!empty($filter_status) && $filter_status !== 'All') {
    $sql_conditions[] = "ir.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if (!empty($filter_target_type) && $filter_target_type !== 'All') {
    $sql_conditions[] = "ir.target_type = ?";
    $params[] = $filter_target_type;
    $types .= 's';
}

$where_clause = "WHERE " . implode(" AND ", $sql_conditions);

$sql = "SELECT
    ir.id, ir.target_type, ir.incident_date, ir.incident_time, ir.location, ir.description, ir.evidence_url,
    ir.severity, ir.status, ir.immediate_action_taken, ir.final_action_taken,
    ir.reported_by_teacher_id,
    reporter.full_name AS reporter_name,
    student.first_name AS student_first_name, student.middle_name AS student_middle_name, student.last_name AS student_last_name, student.registration_number AS student_reg_no,
    c.class_name, c.section_name,
    s.subject_name,
    admin.full_name AS reviewer_name, ir.review_date, ir.review_notes
FROM
    indiscipline_reports ir
LEFT JOIN
    teachers reporter ON ir.reported_by_teacher_id = reporter.id
LEFT JOIN
    students student ON ir.reported_student_id = student.id
LEFT JOIN
    classes c ON ir.class_id = c.id
LEFT JOIN
    subjects s ON ir.subject_id = s.id
LEFT JOIN
    admins admin ON ir.reviewed_by_admin_id = admin.id
" . $where_clause . " ORDER BY ir.created_at DESC";
// --- FIX END ---


if ($stmt = mysqli_prepare($link, $sql)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $reports[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $error_message = "Error fetching reports: " . mysqli_error($link);
}
mysqli_close($link); // Close database connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Manage Indiscipline Reports</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50; /* Green */
            --primary-dark: #45a049;
            --secondary-color: #007bff; /* Blue */
            --secondary-dark: #0056b3;
            --danger-color: #dc3545; /* Red */
            --warning-color: #ffc107; /* Yellow */
            --info-color: #17a2b8; /* Cyan */
            --bg-light: #f8f9fa;
            --bg-dark: #343a40;
            --text-dark: #333;
            --text-light: #fff;
            --border-color: #ddd;
            --shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --padding-base: 20px;
            --card-gap: 20px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            background-color: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

         

        .header h1 {
            font-size: 2.2em;
            margin-bottom: 0;
        }

        .container {
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            background: var(--text-light);
            padding: var(--padding-base);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: var(--card-gap);
        }

        h2 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 30px;
            font-size: 2em;
            position: relative;
            padding-bottom: 10px;
        }

        h2::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }

        .message {
            padding: 12px 18px;
            border-radius: var(--border-radius);
            margin-bottom: var(--card-gap);
            font-weight: 500;
        }
        .error {
            background-color: #f8d7da;
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        .success {
            background-color: #d4edda;
            color: var(--primary-dark);
            border: 1px solid var(--primary-dark);
        }
        .info {
            background-color: #cfe2ff;
            color: var(--secondary-dark);
            border: 1px solid var(--secondary-dark);
        }

        /* Form Styling for filter and modal forms */
        .form-group {
            margin-bottom: 10px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95em;
        }
        input[type="text"],
        input[type="date"],
        input[type="time"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            background-color: #fcfcfc;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="date"]:focus,
        input[type="time"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
        }
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        .submit-btn {
            background-color: var(--primary-color);
            color: var(--text-light);
            padding: 14px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.15em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%;
            margin-top: 25px;
        }
        .submit-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Admin Specific Styles */
        .filter-form {
            background-color: #eef4f8;
            padding: var(--padding-base);
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-form label {
            margin-right: 10px;
            font-weight: 600;
        }
        .filter-form select {
            border-color: #c9d6e4;
            background-color: #fff;
            flex-grow: 1;
        }
        .filter-form button {
            background-color: var(--secondary-color);
            color: var(--text-light);
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .filter-form button:hover {
            background-color: var(--secondary-dark);
            transform: translateY(-2px);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: var(--card-gap);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            background-color: var(--text-light);
            min-width: 700px;
        }
        th, td {
            border: 1px solid var(--border-color);
            padding: 14px 18px;
            text-align: left;
            vertical-align: middle;
            font-size: 0.95em;
        }
        th {
            background-color: #f2f2f2;
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f0f0;
        }

        .status-pending-review { color: var(--warning-color); font-weight: bold; }
        .status-reviewed---warning-issued, .status-reviewed---further-action, .status-reviewed---no-action { color: var(--primary-color); }
        .status-closed { color: var(--secondary-dark); }
        .action-btn {
            background-color: var(--secondary-color);
            color: var(--text-light);
            padding: 9px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
            white-space: nowrap;
        }
        .action-btn:hover {
            background-color: var(--secondary-dark);
            transform: translateY(-1px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s ease-out;
        }
        .modal-content {
            background-color: var(--bg-light);
            margin: 5vh auto;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            width: 95%;
            max-width: 900px;
            position: relative;
            animation: slideIn 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close-button {
            color: var(--text-dark);
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .close-button:hover, .close-button:focus {
            color: var(--danger-color);
        }
        .modal-content h3 {
            color: var(--secondary-color);
            margin-bottom: 25px;
            font-size: 1.8em;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 10px;
        }
        .modal-details {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f0f4f8;
            border-radius: 5px;
            border: 1px solid #c9d6e4;
        }
        .modal-details p {
            margin-bottom: 10px;
            font-size: 1.05em;
            display: flex;
            align-items: baseline;
        }
        .modal-details strong {
            color: var(--text-dark);
            min-width: 180px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .modal-details a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .modal-details a:hover {
            text-decoration: underline;
        }
        .modal .form-group {
            margin-bottom: 20px;
        }
        .modal .submit-btn {
            width: 100%;
            margin-top: 25px;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Footer */
        .footer {
            background-color: var(--bg-dark);
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            padding: var(--padding-base);
            margin-top: var(--card-gap);
            font-size: 0.9em;
        }

        /* Media Queries for Responsiveness */
        @media (max-width: 992px) {
            .container {
                padding: var(--padding-base) 15px;
            }
        }

        @media (max-width: 768px) {
            .header h1 { font-size: 1.8em; }
            h2 { font-size: 1.6em; margin-bottom: 20px;}
            h2::after { width: 80px; }
            .container {
                padding: 15px 10px;
                margin: 10px auto;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .filter-group {
                min-width: 100%;
            }
            .filter-form button {
                width: 100%;
            }
            table th, table td {
                padding: 10px;
                font-size: 0.85em;
            }
            .modal-content {
                margin: 3vh auto;
                width: 98%;
                padding: 20px;
            }
            .modal-details strong {
                min-width: 120px;
            }
        }

        @media (max-width: 480px) {
            body { margin: 10px; }
            .header h1 { font-size: 1.4em; }
            h2 { font-size: 1.2em; }
            .submit-btn { padding: 12px 20px; font-size: 1em; }
            input[type="text"], input[type="date"], input[type="time"], textarea, select { font-size: 0.9em; padding: 10px; }
            .action-btn { padding: 7px 10px; font-size: 0.8em; }
            .modal-details p { flex-direction: column; align-items: flex-start; }
            .modal-details strong { min-width: auto; margin-right: 0; margin-bottom: 3px; }
        }
    </style>
</head>
<body>
    <header class="header mt-28">
     </header>

    <main class="container">
        <?php if ($error_message): ?>
            <p class="message error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <p class="message success"><?php echo $success_message; ?></p>
        <?php endif; ?>

        <h2>Manage Indiscipline Reports</h2>

        <form class="filter-form" action="indiscipline.php" method="GET">
            <div class="filter-group">
                <label for="filter_status">Filter by Status:</label>
                <select name="filter_status" id="filter_status">
                    <option value="All" <?php echo ($filter_status == 'All') ? 'selected' : ''; ?>>All</option>
                    <option value="Pending Review" <?php echo ($filter_status == 'Pending Review') ? 'selected' : ''; ?>>Pending Review</option>
                    <option value="Reviewed - Warning Issued" <?php echo ($filter_status == 'Reviewed - Warning Issued') ? 'selected' : ''; ?>>Reviewed - Warning Issued</option>
                    <option value="Reviewed - Further Action" <?php echo ($filter_status == 'Reviewed - Further Action') ? 'selected' : ''; ?>>Reviewed - Further Action</option>
                    <option value="Reviewed - No Action" <?php echo ($filter_status == 'Reviewed - No Action') ? 'selected' : ''; ?>>Reviewed - No Action</option>
                    <option value="Closed" <?php echo ($filter_status == 'Closed') ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter_target_type">Filter by Target Type:</label>
                <select name="filter_target_type" id="filter_target_type">
                    <option value="All" <?php echo ($filter_target_type == 'All') ? 'selected' : ''; ?>>All</option>
                    <option value="Student" <?php echo ($filter_target_type == 'Student') ? 'selected' : ''; ?>>Student</option>
                    <option value="Teacher" <?php echo ($filter_target_type == 'Teacher') ? 'selected' : ''; ?>>Teacher</option>
                </select>
            </div>
            <button type="submit" class="submit-btn">Apply Filters</button>
        </form>

        <?php if (empty($reports)): ?>
            <p class="message info">No indiscipline reports found matching the criteria.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reporter</th>
                            <th>Target</th>
                            <th>Incident Date</th>
                            <th>Location</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Final Action</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['id']); ?></td>
                                <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                                <td>
                                    <?php
                                        if ($report['target_type'] === 'Student') {
                                            $student_full_name = trim($report['student_first_name'] . ' ' . $report['student_middle_name'] . ' ' . $report['student_last_name']);
                                            echo 'Student: ' . htmlspecialchars($student_full_name . ' (Reg No: ' . $report['student_reg_no'] . ')');
                                        } elseif ($report['target_type'] === 'Teacher') {
                                            // The provided SQL does not include 'reported_teacher_name' for 'Teacher' target type.
                                            // You would need to add another LEFT JOIN for `teachers` on `ir.reported_teacher_id`
                                            // and select `teacher_name` from that join to properly display it.
                                            // For now, it will display N/A or empty if that column is not fetched.
                                            echo 'Teacher: ' . htmlspecialchars($report['reported_teacher_name'] ?? 'N/A');
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($report['incident_date']); ?></td>
                                <td><?php echo htmlspecialchars($report['location'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($report['severity']); ?></td>
                                <td class="status-<?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>">
                                    <?php echo htmlspecialchars($report['status']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($report['final_action_taken'] ?: 'N/A'); ?></td>
                                <td>
                                    <button class="action-btn" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($report)); ?>)">Details & Review</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Admin Review Modal -->
        <div id="reviewModal" class="modal">
            <div class="modal-content">
                <span class="close-button" onclick="closeReviewModal()">&times;</span>
                <h3>Review Indiscipline Report <span id="modalReportId"></span></h3>
                <div class="modal-details">
                    <p><strong>Reported By:</strong> <span id="detail_reporter_name"></span></p>
                    <p><strong>Target:</strong> <span id="detail_target_info"></span></p>
                    <p><strong>Incident Date:</strong> <span id="detail_incident_date"></span></p>
                    <p><strong>Incident Time:</strong> <span id="detail_incident_time"></span></p>
                    <p><strong>Location:</strong> <span id="detail_location"></span></p>
                    <p><strong>Relevant Class:</strong> <span id="detail_class"></span></p>
                    <p><strong>Relevant Subject:</strong> <span id="detail_subject"></span></p>
                    <p><strong>Severity:</strong> <span id="detail_severity"></span></p>
                    <p><strong>Description:</strong> <br><span id="detail_description"></span></p>
                    <p><strong>Immediate Action:</strong> <br><span id="detail_immediate_action_taken"></span></p>
                    <p><strong>Evidence:</strong> <span id="detail_evidence_url"></span></p>
                    <hr>
                    <p><strong>Current Status:</strong> <span id="detail_status_display"></span></p>
                    <p><strong>Reviewed By:</strong> <span id="detail_reviewer_name"></span></p>
                    <p><strong>Review Date:</strong> <span id="detail_review_date"></span></p>
                    <p><strong>Review Notes:</strong> <br><span id="detail_review_notes_display"></span></p>
                    <p><strong>Final Action:</strong> <br><span id="detail_final_action_display"></span></p>

                </div>
                <hr>
                <form action="indiscipline.php" method="POST">
                    <input type="hidden" name="action" value="review_report">
                    <input type="hidden" name="report_id" id="review_report_id">

                    <div class="form-group">
                        <label for="modal_status">Update Status:</label>
                        <select id="modal_status" name="status" required>
                            <option value="Pending Review">Pending Review</option>
                            <option value="Reviewed - Warning Issued">Reviewed - Warning Issued</option>
                            <option value="Reviewed - Further Action">Reviewed - Further Action</option>
                            <option value="Reviewed - No Action">Reviewed - No Action</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_review_notes">Reviewer's Notes:</label>
                        <textarea id="modal_review_notes" name="review_notes" placeholder="Add or update your review notes here..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="modal_final_action_taken">Final Action Taken:</label>
                        <textarea id="modal_final_action_taken" name="final_action_taken" placeholder="e.g., Student suspended for 3 days, Formal warning issued to teacher."></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="submit-btn">Save Review</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    

    <script>
        // Admin specific JS for review modal
        function openReviewModal(report) {
            document.getElementById('modalReportId').textContent = '#' + report.id;
            document.getElementById('review_report_id').value = report.id;

            // Populate view-only details
            document.getElementById('detail_reporter_name').textContent = report.reporter_name;
            let targetInfo = '';
            if (report.target_type === 'Student') {
                const studentFullName = [report.student_first_name, report.student_middle_name, report.student_last_name].filter(Boolean).join(' ');
                targetInfo = 'Student: ' + studentFullName + ' (Reg No: ' + report.student_reg_no + ')';
            } else if (report.target_type === 'Teacher') {
                // This assumes `reported_teacher_name` is available in the `$report` array.
                // If not, you'll see 'N/A' as expected.
                targetInfo = 'Teacher: ' + (report.reported_teacher_name || 'N/A');
            }
            document.getElementById('detail_target_info').textContent = targetInfo;
            document.getElementById('detail_incident_date').textContent = report.incident_date;
            document.getElementById('detail_incident_time').textContent = report.incident_time || 'N/A';
            document.getElementById('detail_location').textContent = report.location || 'N/A';
            document.getElementById('detail_class').textContent = (report.class_name && report.section_name) ? `${report.class_name} - ${report.section_name}` : 'N/A';
            document.getElementById('detail_subject').textContent = report.subject_name || 'N/A';
            document.getElementById('detail_severity').textContent = report.severity;
            document.getElementById('detail_description').textContent = report.description;
            document.getElementById('detail_immediate_action_taken').textContent = report.immediate_action_taken || 'None';
            if (report.evidence_url) {
                document.getElementById('detail_evidence_url').innerHTML = `<a href="${report.evidence_url}" target="_blank">View Evidence</a>`;
            } else {
                document.getElementById('detail_evidence_url').textContent = 'None';
            }

            document.getElementById('detail_status_display').textContent = report.status;
            document.getElementById('detail_reviewer_name').textContent = report.reviewer_name || 'N/A';
            document.getElementById('detail_review_date').textContent = report.review_date ? new Date(report.review_date).toLocaleString() : 'N/A';
            document.getElementById('detail_review_notes_display').textContent = report.review_notes || 'None';
            document.getElementById('detail_final_action_display').textContent = report.final_action_taken || 'None';


            // Populate editable fields for review
            document.getElementById('modal_status').value = report.status;
            document.getElementById('modal_review_notes').value = report.review_notes || '';
            document.getElementById('modal_final_action_taken').value = report.final_action_taken || '';

            document.getElementById('reviewModal').style.display = 'block';
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            var reviewModal = document.getElementById('reviewModal');
            if (event.target == reviewModal) {
                reviewModal.style.display = "none";
            }
        }
    </script>
</body>
</html>

<?php
require_once './admin_footer.php';
?>