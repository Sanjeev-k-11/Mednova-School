<?php
// admin_indiscipline.php
session_start();

// Include database connection and helper functions
require_once '../database/config.php';
require_once '../database/helpers.php';
require_once './admin_header.php'; // Assuming this provides the layout/header for admin pages

$error_message = '';
$success_message = '';

// --- IMPORTANT: REMOVE MOCK AUTHENTICATION FOR PRODUCTION ---
// For testing: uncomment the following lines to simulate an admin user.
// In a production environment, these session variables would be set upon successful login.
// $_SESSION['user_role'] = 'Super Admin'; // Simulate an admin role (used for initial role check)
// $_SESSION['id'] = 1;         // Simulate admin with ID 1 (ensure this ID exists in your 'admins' table, used for logged_in_admin_id)
// $_SESSION['loggedin'] = true; // Simulate login status
// $_SESSION['role'] = 'Super Admin'; // Simulate role for primary access control
// --- END Mock User Authentication ---

// --- Access Control: Only allow administrators to view this page ---
// Using the provided authentication check from the original script:
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    // A more user-friendly and consistent access denied page might be better,
    // but sticking to the original script's provided HTML for consistency.
    echo "<!DOCTYPE html><html><head><title>Access Denied</title><style>body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: #dc3545; text-align: center; padding-top: 50px; } .message { border: 1px solid #dc3545; padding: 15px; margin: 20px auto; max-width: 600px; border-radius: 8px; background-color: #f8d7da; }</style></head><body><div class='message'><h2>Access Denied</h2><p>You must be logged in as an administrator to view this page.</p><p><a href='../login.php'>Return to Login</a></p></div></body></html>";
    exit();
}

// Ensure the logged_in_admin_id is correctly set after successful authentication
$user_role = $_SESSION['role'] ?? null;
$logged_in_admin_id = null;
if ($user_role === 'Super Admin' && isset($_SESSION['id'])) {
    $logged_in_admin_id = $_SESSION['id'];
}
// Critical check: if an admin is logged in but their ID is somehow missing from the session.
if (is_null($logged_in_admin_id)) {
    error_log("Security Alert: Admin logged in successfully but their ID is missing from session. Session: " . print_r($_SESSION, true));
    echo "<!DOCTYPE html><html><head><title>Configuration Error</title><style>body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: #dc3545; text-align: center; padding-top: 50px; } .message { border: 1px solid #dc3545; padding: 15px; margin: 20px auto; max-width: 600px; border-radius: 8px; background-color: #f8d7da; }</style></head><body><div class='message'><h2>Configuration Error</h2><p>Your session is missing administrator ID. Please re-login.</p><p><a href='../login.php'>Return to Login</a></p></div></body></html>";
    exit();
}

// Generate a CSRF token for the review form (regenerate if not set or after successful submission)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


// --- ADMIN ROLE: Handle Report Review/Update (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'review_report') {

    // CSRF Protection: Validate the token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid request: CSRF token mismatch. Please refresh and try again.";
        // Regenerate token on failure for security
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $report_id = (int)$_POST['report_id'];
        $status = trim($_POST['status'] ?? '');
        $review_notes = trim($_POST['review_notes'] ?? '');
        $final_action_taken = trim($_POST['final_action_taken'] ?? '');

        // Input Validation: Ensure status is one of the allowed values
        $allowed_statuses = [
            'Pending Review',
            'Reviewed - Warning Issued',
            'Reviewed - Further Action',
            'Reviewed - No Action',
            'Closed'
        ];

        if (empty($status) || $report_id <= 0 || !in_array($status, $allowed_statuses)) {
            $error_message = "Invalid report ID or status provided. Status must be one of: " . implode(", ", $allowed_statuses);
        } else {
            // Prepared statement to update report details
            $sql = "UPDATE indiscipline_reports SET
                        status = ?,
                        review_notes = ?,
                        final_action_taken = ?,
                        reviewed_by_admin_id = ?,
                        review_date = NOW()
                    WHERE id = ?";

            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param(
                    $stmt, "sssii", // s:string, s:string, s:string, i:int, i:int
                    $status,
                    $review_notes,
                    $final_action_taken,
                    $logged_in_admin_id,
                    $report_id
                );

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Report #{$report_id} updated successfully.";
                    // Regenerate CSRF token after successful submission to prevent reuse
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
}

// --- Data Fetching for Admin Table (GET Request or initial page load) ---
$reports = [];

// Get filter, search, sort, and pagination parameters from GET request or set defaults
$filter_status = $_GET['filter_status'] ?? 'Pending Review'; // Default filter for admins
$filter_target_type = $_GET['filter_target_type'] ?? 'All'; // Default to 'All' for admin view
$search_query = trim($_GET['search_query'] ?? '');
$sort_by = $_GET['sort_by'] ?? 'ir.created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Pagination setup
$records_per_page = 10; // Number of records to display per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1
$offset = ($current_page - 1) * $records_per_page;

$sql_conditions = ["1=1"]; // Start with a true condition for easy `AND` concatenation
$params = [];
$types = '';

// Add filter conditions
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

// Add search conditions
if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    // Search across multiple relevant columns (report ID, reporter, student, teacher, description, location)
    $sql_conditions[] = "(ir.id LIKE ? OR reporter.full_name LIKE ? OR student_target.first_name LIKE ? OR student_target.last_name LIKE ? OR student_target.registration_number LIKE ? OR teacher_target.full_name LIKE ? OR ir.description LIKE ? OR ir.location LIKE ?)";
    $params = array_merge($params, array_fill(0, 8, $search_param)); // Add search_param 8 times
    $types .= str_repeat('s', 8); // Add 's' 8 times to types string
}

$where_clause = "WHERE " . implode(" AND ", $sql_conditions);

// Validate sort_by and sort_order to prevent SQL injection for ORDER BY clause
$allowed_sort_columns = [
    'ir.created_at', 'ir.incident_date', 'ir.severity', 'ir.status',
    'reporter.full_name', 'student_target.last_name', 'teacher_target.full_name'
];
$allowed_sort_order = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'ir.created_at'; // Default safe column
}
if (!in_array(strtoupper($sort_order), $allowed_sort_order)) {
    $sort_order = 'DESC'; // Default safe order
}

// First, get total count of records for pagination without LIMIT/OFFSET
$count_sql = "SELECT COUNT(ir.id) AS total_records
              FROM indiscipline_reports ir
              LEFT JOIN teachers reporter ON ir.reported_by_teacher_id = reporter.id
              LEFT JOIN students student_target ON ir.reported_student_id = student_target.id AND ir.target_type = 'Student'
              LEFT JOIN teachers teacher_target ON ir.reported_teacher_id = teacher_target.id AND ir.target_type = 'Teacher'
              " . $where_clause;

$total_records = 0;
if ($stmt_count = mysqli_prepare($link, $count_sql)) {
    // Need a separate params array and types string for count query as the main query will have LIMIT/OFFSET
    $count_params = $params;
    $count_types = $types;

    if (!empty($count_params)) {
        mysqli_stmt_bind_param($stmt_count, $count_types, ...$count_params);
    }
    mysqli_stmt_execute($stmt_count);
    $result_count = mysqli_stmt_get_result($stmt_count);
    $row_count = mysqli_fetch_assoc($result_count);
    $total_records = $row_count['total_records'];
    mysqli_stmt_close($stmt_count);
}

$total_pages = ceil($total_records / $records_per_page);
// Adjust current_page if it's beyond the total pages (e.g., after deleting records or changing filters)
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $records_per_page;
}


// Main SQL query to fetch reports with all necessary joins
$sql = "SELECT
    ir.id, ir.target_type, ir.incident_date, ir.incident_time, ir.location, ir.description, ir.evidence_url,
    ir.severity, ir.status, ir.immediate_action_taken, ir.final_action_taken,
    ir.reported_by_teacher_id,
    reporter.full_name AS reporter_name,
    student_target.first_name AS student_first_name, student_target.middle_name AS student_middle_name, student_target.last_name AS student_last_name, student_target.registration_number AS student_reg_no,
    teacher_target.full_name AS teacher_target_name, -- Added for target teacher
    c.class_name, c.section_name,
    s.subject_name,
    admin.full_name AS reviewer_name, ir.review_date, ir.review_notes
FROM
    indiscipline_reports ir
LEFT JOIN
    teachers reporter ON ir.reported_by_teacher_id = reporter.id
LEFT JOIN
    students student_target ON ir.reported_student_id = student_target.id AND ir.target_type = 'Student'
LEFT JOIN
    teachers teacher_target ON ir.reported_teacher_id = teacher_target.id AND ir.target_type = 'Teacher'
LEFT JOIN
    classes c ON ir.class_id = c.id
LEFT JOIN
    subjects s ON ir.subject_id = s.id
LEFT JOIN
    admins admin ON ir.reviewed_by_admin_id = admin.id
" . $where_clause . " ORDER BY {$sort_by} {$sort_order} LIMIT ? OFFSET ?";

// Add LIMIT and OFFSET parameters to the main query's parameters
$params[] = $records_per_page;
$types .= 'i';
$params[] = $offset;
$types .= 'i';

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
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
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        .header {
            /* Assuming admin_header.php handles the actual header content */
            padding-top: 28px; /* Offset for a fixed header if any from admin_header.php */
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
            display: flex;
            align-items: center;
        }
        .message i {
            margin-right: 10px;
            font-size: 1.2em;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .submit-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        .submit-btn i {
            margin-right: 8px;
        }


        /* Admin Specific Styles */
        .filter-form {
            background-color: #eef4f8;
            padding: var(--padding-base);
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Adaptive columns */
            gap: 15px;
            align-items: end; /* Align items to the bottom */
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px; /* Spacing between label and input */
        }
        .filter-form label {
            margin-bottom: 0; /* Already accounted for by group gap */
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95em;
        }
        .filter-form select, .filter-form input[type="text"] {
            border-color: #c9d6e4;
            background-color: #fff;
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
            white-space: nowrap; /* Prevent button text from wrapping */
            height: fit-content; /* Adjust height to align with inputs/selects */
            align-self: flex-end; /* Align button to the bottom of its grid cell */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .filter-form button i {
            margin-right: 5px;
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
            min-width: 900px; /* Increased min-width for more columns and details */
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
            cursor: pointer; /* Indicate sortable */
            position: relative;
        }
        th .sort-icon {
            margin-left: 5px;
            font-size: 0.8em;
            color: #888;
            transition: transform 0.2s ease;
        }
        th.sortable:hover {
            background-color: #e6e6e6;
        }
        th.sorted-asc .sort-icon {
            color: var(--primary-color);
            transform: rotate(180deg);
        }
        th.sorted-desc .sort-icon {
            color: var(--primary-color);
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f0f0;
        }

        .status-pending-review { color: var(--warning-color); font-weight: bold; }
        .status-reviewed---warning-issued { color: #FF8C00; font-weight: bold; } /* Darker Orange */
        .status-reviewed---further-action { color: var(--danger-color); font-weight: bold; }
        .status-reviewed---no-action { color: var(--primary-color); }
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
            display: inline-flex;
            align-items: center;
        }
        .action-btn:hover {
            background-color: var(--secondary-dark);
            transform: translateY(-1px);
        }
        .action-btn i {
            margin-right: 5px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: var(--card-gap);
            gap: 8px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            text-decoration: none;
            color: var(--secondary-color);
            background-color: var(--text-light);
            transition: background-color 0.3s ease, border-color 0.3s ease;
            font-weight: 500;
        }
        .pagination a:hover {
            background-color: #e9ecef;
            border-color: var(--secondary-color);
        }
        .pagination .current-page {
            background-color: var(--secondary-color);
            color: var(--text-light);
            border-color: var(--secondary-dark);
            cursor: default;
        }
        .pagination .dots {
             background-color: var(--text-light);
             color: var(--text-dark);
             border: 1px solid var(--border-color);
             cursor: default;
        }
        .pagination .disabled {
            color: #6c757d;
            cursor: not-allowed;
            background-color: #e9ecef;
            border-color: #dee2e6;
            pointer-events: none; /* Disable click */
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
            display: flex;
            align-items: center;
        }
        .modal-content h3 i {
            margin-right: 10px;
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
            display: inline-flex;
            align-items: center;
        }
        .modal-details a i {
            margin-left: 5px;
            font-size: 0.9em;
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
            .filter-form {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
                grid-template-columns: 1fr; /* Stack columns on smaller screens */
                gap: 10px;
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
            .pagination {
                flex-direction: column;
                gap: 5px;
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
            <p class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></p>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <p class="message success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></p>
        <?php endif; ?>

        <h2><i class="fas fa-gavel"></i> Manage Indiscipline Reports</h2>

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
            <div class="filter-group">
                <label for="search_query">Search:</label>
                <input type="text" name="search_query" id="search_query" placeholder="ID, name, description..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
             <div class="filter-group">
                <label for="sort_by">Sort By:</label>
                <select name="sort_by" id="sort_by">
                    <option value="ir.created_at" <?php echo ($sort_by == 'ir.created_at') ? 'selected' : ''; ?>>Report Date</option>
                    <option value="ir.incident_date" <?php echo ($sort_by == 'ir.incident_date') ? 'selected' : ''; ?>>Incident Date</option>
                    <option value="ir.severity" <?php echo ($sort_by == 'ir.severity') ? 'selected' : ''; ?>>Severity</option>
                    <option value="ir.status" <?php echo ($sort_by == 'ir.status') ? 'selected' : ''; ?>>Status</option>
                    <option value="reporter.full_name" <?php echo ($sort_by == 'reporter.full_name') ? 'selected' : ''; ?>>Reporter Name</option>
                    <option value="student_target.last_name" <?php echo ($sort_by == 'student_target.last_name') ? 'selected' : ''; ?>>Student Name</option>
                    <option value="teacher_target.full_name" <?php echo ($sort_by == 'teacher_target.full_name') ? 'selected' : ''; ?>>Teacher Name</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="sort_order">Sort Order:</label>
                <select name="sort_order" id="sort_order">
                    <option value="DESC" <?php echo ($sort_order == 'DESC') ? 'selected' : ''; ?>>Descending</option>
                    <option value="ASC" <?php echo ($sort_order == 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                </select>
            </div>
            <button type="submit" class="submit-btn"><i class="fas fa-filter"></i> Apply Filters</button>
        </form>

        <?php if (empty($reports) && ($search_query || $filter_status !== 'All' || $filter_target_type !== 'All' || $current_page > 1)): ?>
            <p class="message info"><i class="fas fa-info-circle"></i> No indiscipline reports found matching your current filters/search criteria.</p>
        <?php elseif (empty($reports) && $total_records == 0): ?>
            <p class="message info"><i class="fas fa-info-circle"></i> No indiscipline reports have been submitted yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reporter</th>
                            <th>Target</th>
                            <th class="sortable <?php echo ($sort_by === 'ir.incident_date') ? 'sorted-'.strtolower($sort_order) : ''; ?>" onclick="sortTable('ir.incident_date')">Incident Date <span class="sort-icon fas <?php echo ($sort_by === 'ir.incident_date' ? ($sort_order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'); ?>"></span></th>
                            <th>Location</th>
                            <th class="sortable <?php echo ($sort_by === 'ir.severity') ? 'sorted-'.strtolower($sort_order) : ''; ?>" onclick="sortTable('ir.severity')">Severity <span class="sort-icon fas <?php echo ($sort_by === 'ir.severity' ? ($sort_order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'); ?>"></span></th>
                            <th class="sortable <?php echo ($sort_by === 'ir.status') ? 'sorted-'.strtolower($sort_order) : ''; ?>" onclick="sortTable('ir.status')">Status <span class="sort-icon fas <?php echo ($sort_by === 'ir.status' ? ($sort_order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'); ?>"></span></th>
                            <th>Final Action</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['id']); ?></td>
                                <td><?php echo htmlspecialchars($report['reporter_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                        if ($report['target_type'] === 'Student') {
                                            $student_full_name = trim($report['student_first_name'] . ' ' . ($report['student_middle_name'] ? $report['student_middle_name'] . ' ' : '') . $report['student_last_name']);
                                            echo '<i class="fas fa-user-graduate"></i> Student: ' . htmlspecialchars($student_full_name ?: 'N/A') . (isset($report['student_reg_no']) ? ' (Reg No: ' . htmlspecialchars($report['student_reg_no']) . ')' : '');
                                        } elseif ($report['target_type'] === 'Teacher') {
                                            echo '<i class="fas fa-chalkboard-teacher"></i> Teacher: ' . htmlspecialchars($report['teacher_target_name'] ?? 'N/A');
                                        } else {
                                            echo 'N/A'; // Fallback for unexpected target_type
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($report['incident_date']); ?></td>
                                <td><?php echo htmlspecialchars($report['location'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($report['severity']); ?></td>
                                <td class="status-<?php echo strtolower(str_replace([' ', '-'], '-', $report['status'])); ?>">
                                    <?php echo htmlspecialchars($report['status']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($report['final_action_taken'] ?: 'N/A'); ?></td>
                                <td>
                                    <button class="action-btn" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($report)); ?>)"><i class="fas fa-eye"></i> Details & Review</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    // Helper function to build query string for pagination
                    function buildPaginationQuery(array $params, int $page) {
                        $newParams = array_merge($params, ['page' => $page]);
                        unset($newParams['csrf_token'], $newParams['action'], $newParams['report_id']); // Remove post specific params
                        return http_build_query($newParams);
                    }
                    ?>
                    <a href="?<?php echo buildPaginationQuery($_GET, max(1, $current_page - 1)); ?>" class="<?php echo ($current_page == 1) ? 'disabled' : ''; ?>"><i class="fas fa-angle-left"></i> Previous</a>

                    <?php
                    // Display page numbers, showing a few around the current page
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    if ($start_page > 1) {
                        echo '<a href="?' . buildPaginationQuery($_GET, 1) . '">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="dots">...</span>';
                        }
                    }

                    for ($p = $start_page; $p <= $end_page; $p++):
                        $pageQueryString = buildPaginationQuery($_GET, $p);
                        if ($p == $current_page): ?>
                            <span class="current-page"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo $pageQueryString; ?>"><?php echo $p; ?></a>
                        <?php endif;
                    endfor;

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="dots">...</span>';
                        }
                        echo '<a href="?' . buildPaginationQuery($_GET, $total_pages) . '">' . $total_pages . '</a>';
                    }
                    ?>

                    <a href="?<?php echo buildPaginationQuery($_GET, min($total_pages, $current_page + 1)); ?>" class="<?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?>">Next <i class="fas fa-angle-right"></i></a>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- Admin Review Modal -->
        <div id="reviewModal" class="modal">
            <div class="modal-content">
                <span class="close-button" onclick="closeReviewModal()">&times;</span>
                <h3><i class="fas fa-clipboard-list"></i> Review Indiscipline Report <span id="modalReportId"></span></h3>
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
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

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
                        <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Save Review</button>
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
            document.getElementById('detail_reporter_name').textContent = report.reporter_name || 'N/A';
            let targetInfo = '';
            if (report.target_type === 'Student') {
                const studentFullName = [report.student_first_name, report.student_middle_name, report.student_last_name].filter(Boolean).join(' ');
                targetInfo = 'Student: ' + (studentFullName || 'N/A') + (report.student_reg_no ? ' (Reg No: ' + report.student_reg_no + ')' : '');
            } else if (report.target_type === 'Teacher') {
                targetInfo = 'Teacher: ' + (report.teacher_target_name || 'N/A');
            } else {
                targetInfo = 'N/A'; // Fallback for unexpected target_type
            }
            document.getElementById('detail_target_info').textContent = targetInfo;
            document.getElementById('detail_incident_date').textContent = report.incident_date || 'N/A';
            document.getElementById('detail_incident_time').textContent = report.incident_time || 'N/A';
            document.getElementById('detail_location').textContent = report.location || 'N/A';
            document.getElementById('detail_class').textContent = (report.class_name && report.section_name) ? `${report.class_name} - ${report.section_name}` : 'N/A';
            document.getElementById('detail_subject').textContent = report.subject_name || 'N/A';
            document.getElementById('detail_severity').textContent = report.severity || 'N/A';
            document.getElementById('detail_description').textContent = report.description || 'N/A';
            document.getElementById('detail_immediate_action_taken').textContent = report.immediate_action_taken || 'None';
            if (report.evidence_url) {
                document.getElementById('detail_evidence_url').innerHTML = `<a href="${report.evidence_url}" target="_blank">View Evidence <i class="fas fa-external-link-alt"></i></a>`;
            } else {
                document.getElementById('detail_evidence_url').textContent = 'None';
            }

            document.getElementById('detail_status_display').textContent = report.status || 'N/A';
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

        // Sorting function for table headers
        function sortTable(column) {
            const currentUrl = new URL(window.location.href);
            let currentSortBy = currentUrl.searchParams.get('sort_by');
            let currentSortOrder = currentUrl.searchParams.get('sort_order');

            if (currentSortBy === column) {
                // Toggle order if clicking the same column
                currentSortOrder = (currentSortOrder === 'ASC') ? 'DESC' : 'ASC';
            } else {
                // Default to DESC for new column sort
                currentSortOrder = 'DESC';
            }

            currentUrl.searchParams.set('sort_by', column);
            currentUrl.searchParams.set('sort_order', currentSortOrder);
            currentUrl.searchParams.set('page', 1); // Reset to first page on new sort
            window.location.href = currentUrl.toString();
        }

        // Set active sort indicator in table headers on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentUrl = new URL(window.location.href);
            const sortByParam = currentUrl.searchParams.get('sort_by');
            const sortOrderParam = currentUrl.searchParams.get('sort_order');

            document.querySelectorAll('th.sortable').forEach(header => {
                // Extract column name from the onclick attribute
                const match = header.getAttribute('onclick').match(/'([^']+)'/);
                const column = match ? match[1] : null;

                if (column && sortByParam === column) {
                    header.classList.add(`sorted-${sortOrderParam.toLowerCase()}`);
                }
            });
        });

    </script>
</body>
</html>

<?php
require_once './admin_footer.php';
?>