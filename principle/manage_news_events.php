<?php
session_start();
require_once "../database/config.php"; // Assumed to contain the $link database connection

// --- CSRF TOKEN GENERATION ---
// Generate a CSRF token if one doesn't exist for the session.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"];
$principal_name = $_SESSION["full_name"];

// --- HELPER FUNCTIONS ---

/**
 * Sets a feedback message in the session (flash message).
 * @param string $msg The message to display.
 * @param string $type The type of message ('success' or 'danger').
 */
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

/**
 * Verifies the CSRF token from a form submission.
 * Terminates the script with an error if the token is invalid.
 */
function verify_csrf_token() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_session_message("Invalid CSRF token. Action blocked for security reasons.", "danger");
        // Redirect to a safe page, perhaps the dashboard or the same page without action.
        header("location: manage_news_events.php");
        exit;
    }
}

// --- UNIVERSAL DELETE HANDLER ---
if (isset($_POST['action']) && $_POST['action'] == 'delete') {
    verify_csrf_token();
    $type = $_POST['type']; // 'news' or 'event'
    $delete_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $redirect_tab = ($type === 'news') ? 'news' : 'events';

    if (empty($delete_id)) {
        set_session_message("Invalid ID for deletion.", "danger");
        header("location: manage_news_events.php?tab={$redirect_tab}");
        exit;
    }

    $table_name = ($type === 'news') ? 'news_articles' : 'upcoming_events';
    $sql = "DELETE FROM {$table_name} WHERE id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message(ucfirst($type) . " item deleted successfully.", "success");
            } else {
                set_session_message(ucfirst($type) . " item not found or already deleted.", "danger");
            }
        } else {
            // SECURITY: Log the detailed error, but show a generic message to the user.
            error_log("Error deleting from {$table_name}: " . mysqli_error($link));
            set_session_message("Error deleting item. Please try again.", "danger");
        }
        mysqli_stmt_close($stmt);
    }
    // Note: Pagination state after deletion is handled client-side or would require recalculating pages here.
    header("location: manage_news_events.php?tab={$redirect_tab}");
    exit;
}


// --- NEWS ARTICLES MANAGEMENT ---

$news_records_per_page = 5;
$news_current_page = isset($_GET['news_page']) && is_numeric($_GET['news_page']) ? (int)$_GET['news_page'] : 1;
$news_search_query = isset($_GET['news_search']) ? trim($_GET['news_search']) : '';

// Process News Form Submissions (Add/Edit)
if (isset($_POST['news_form_action'])) {
    verify_csrf_token(); // CSRF check
    $action = $_POST['news_form_action'];

    $title = trim($_POST['news_title']);
    $date = trim($_POST['news_date']);
    $category = trim($_POST['news_category']);
    $image_url = trim($_POST['news_image_url']);
    $excerpt = trim($_POST['news_excerpt']);
    $content = trim($_POST['news_content']);

    if (empty($title) || empty($date) || empty($content)) {
        set_session_message("Title, Date, and Content are required fields.", "danger");
    } else {
        if ($action == 'add_news') {
            $sql = "INSERT INTO news_articles (title, date, category, image_url, excerpt, content, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssssi", $title, $date, $category, $image_url, $excerpt, $content, $principal_id);
                if (mysqli_stmt_execute($stmt)) {
                    set_session_message("News article added successfully.", "success");
                } else {
                    error_log("SQL Error (Add News): " . mysqli_error($link));
                    set_session_message("An error occurred while adding the news article.", "danger");
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action == 'edit_news') {
            $news_id = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
            if ($news_id > 0) {
                $sql = "UPDATE news_articles SET title = ?, date = ?, category = ?, image_url = ?, excerpt = ?, content = ? WHERE id = ?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssssssi", $title, $date, $category, $image_url, $excerpt, $content, $news_id);
                    if (mysqli_stmt_execute($stmt)) {
                        set_session_message("News article updated successfully.", "success");
                    } else {
                        error_log("SQL Error (Edit News): " . mysqli_error($link));
                        set_session_message("An error occurred while updating the news article.", "danger");
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                set_session_message("Invalid News ID for editing.", "danger");
            }
        }
    }
    header("location: manage_news_events.php?tab=news&news_page={$news_current_page}&news_search=" . urlencode($news_search_query));
    exit;
}

// Build WHERE clause for News
$news_where_clauses = [];
$news_params = [];
$news_types = "";
if (!empty($news_search_query)) {
    $news_search_term = "%" . $news_search_query . "%";
    $news_where_clauses[] = "(title LIKE ? OR excerpt LIKE ? OR content LIKE ?)";
    array_push($news_params, $news_search_term, $news_search_term, $news_search_term);
    $news_types .= "sss";
}
$news_where_sql = empty($news_where_clauses) ? "1=1" : implode(" AND ", $news_where_clauses);

// Fetch Total News for Pagination
$total_news = 0;
$total_news_sql = "SELECT COUNT(id) FROM news_articles WHERE " . $news_where_sql;
if ($stmt = mysqli_prepare($link, $total_news_sql)) {
    if (!empty($news_params)) {
        mysqli_stmt_bind_param($stmt, $news_types, ...$news_params);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_news);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}
$total_news_pages = $total_news > 0 ? ceil($total_news / $news_records_per_page) : 1;
$news_current_page = max(1, min($news_current_page, $total_news_pages));
$news_offset = ($news_current_page - 1) * $news_records_per_page;

// Fetch News Articles Data
$news_articles = [];
$sql_fetch_news = "SELECT * FROM news_articles WHERE " . $news_where_sql . " ORDER BY date DESC, created_at DESC LIMIT ? OFFSET ?";
if ($stmt = mysqli_prepare($link, $sql_fetch_news)) {
    $news_params_pagination = $news_params;
    array_push($news_params_pagination, $news_records_per_page, $news_offset);
    $news_types_pagination = $news_types . "ii";
    mysqli_stmt_bind_param($stmt, $news_types_pagination, ...$news_params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $news_articles = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


// --- UPCOMING EVENTS MANAGEMENT ---

$events_records_per_page = 5;
$events_current_page = isset($_GET['events_page']) && is_numeric($_GET['events_page']) ? (int)$_GET['events_page'] : 1;
$events_search_query = isset($_GET['events_search']) ? trim($_GET['events_search']) : '';
$events_filter_type = isset($_GET['events_type']) ? trim($_GET['events_type']) : '';

// Process Event Form Submissions (Add/Edit)
if (isset($_POST['event_form_action'])) {
    verify_csrf_token(); // CSRF check
    $action = $_POST['event_form_action'];

    $title = trim($_POST['event_title']);
    $event_date = trim($_POST['event_date']); // Simplified name
    $time = trim($_POST['event_time']);
    $location = trim($_POST['event_location']);
    $type = trim($_POST['event_type']);
    $image_url = trim($_POST['event_image_url']);
    $description = trim($_POST['event_description']);

    if (empty($title) || empty($event_date)) {
        set_session_message("Title and Event Date are required fields.", "danger");
    } else {
        if ($action == 'add_event') {
            $sql = "INSERT INTO upcoming_events (title, event_date, time, location, type, image_url, description, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssssssi", $title, $event_date, $time, $location, $type, $image_url, $description, $principal_id);
                if (mysqli_stmt_execute($stmt)) {
                    set_session_message("Upcoming event added successfully.", "success");
                } else {
                    error_log("SQL Error (Add Event): " . mysqli_error($link));
                    set_session_message("An error occurred while adding the event.", "danger");
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action == 'edit_event') {
            $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
            if ($event_id > 0) {
                $sql = "UPDATE upcoming_events SET title = ?, event_date = ?, time = ?, location = ?, type = ?, image_url = ?, description = ? WHERE id = ?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sssssssi", $title, $event_date, $time, $location, $type, $image_url, $description, $event_id);
                    if (mysqli_stmt_execute($stmt)) {
                        set_session_message("Upcoming event updated successfully.", "success");
                    } else {
                        error_log("SQL Error (Edit Event): " . mysqli_error($link));
                        set_session_message("An error occurred while updating the event.", "danger");
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                set_session_message("Invalid Event ID for editing.", "danger");
            }
        }
    }
    header("location: manage_news_events.php?tab=events&events_page={$events_current_page}&events_search=" . urlencode($events_search_query) . "&events_type=" . urlencode($events_filter_type));
    exit;
}

// Build WHERE clause for Events
$events_where_clauses = [];
$events_params = [];
$events_types = "";
if ($events_filter_type) {
    $events_where_clauses[] = "type = ?";
    $events_params[] = $events_filter_type;
    $events_types .= "s";
}
if (!empty($events_search_query)) {
    $events_search_term = "%" . $events_search_query . "%";
    $events_where_clauses[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
    array_push($events_params, $events_search_term, $events_search_term, $events_search_term);
    $events_types .= "sss";
}
$events_where_sql = empty($events_where_clauses) ? "1=1" : implode(" AND ", $events_where_clauses);

// Fetch Total Events for Pagination
$total_events = 0;
$total_events_sql = "SELECT COUNT(id) FROM upcoming_events WHERE " . $events_where_sql;
if ($stmt = mysqli_prepare($link, $total_events_sql)) {
    if (!empty($events_params)) {
        mysqli_stmt_bind_param($stmt, $events_types, ...$events_params);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_events);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}
$total_events_pages = $total_events > 0 ? ceil($total_events / $events_records_per_page) : 1;
$events_current_page = max(1, min($events_current_page, $total_events_pages));
$events_offset = ($events_current_page - 1) * $events_records_per_page;

// Fetch Upcoming Events Data
$upcoming_events = [];
$sql_fetch_events = "SELECT * FROM upcoming_events WHERE " . $events_where_sql . " ORDER BY event_date ASC, time ASC LIMIT ? OFFSET ?";
if ($stmt = mysqli_prepare($link, $sql_fetch_events)) {
    $events_params_pagination = $events_params;
    array_push($events_params_pagination, $events_records_per_page, $events_offset);
    $events_types_pagination = $events_types . "ii";
    mysqli_stmt_bind_param($stmt, $events_types_pagination, ...$events_params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $upcoming_events = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}


mysqli_close($link);

// Determine active tab
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['news', 'events']) ? $_GET['tab'] : 'news';

// Retrieve and clear session messages
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// Include header
require_once './principal_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage News & Events - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #FFEFD5, #FFDAB9, #FFC0CB, #E0FFFF);
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
            max-width: 1200px;
            margin: 20px auto;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        h2 {
            color: #FF6347; /* Tomato */
            margin-bottom: 30px;
            border-bottom: 2px solid #FFDAB9;
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
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }


        /* Tabs for News/Events */
        .tab-controls {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .tab-button {
            padding: 12px 25px;
            background-color: #f8f8f8;
            border: 1px solid #e0e0e0;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-weight: 600;
            color: #555;
            transition: all 0.3s;
            margin-right: 5px;
            font-size: 1.1em;
        }
        .tab-button.active {
            background-color: #FF6347;
            color: #fff;
            border-color: #FF6347;
        }
        .tab-button:hover:not(.active) {
            background-color: #ffe0b3;
            color: #FF6347;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Collapsible Form Section */
        .form-section {
            background-color: #fefefe;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .form-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        .form-section-header h3 {
            color: #FF6347;
            margin: 0;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toggle-btn {
            background: none; border: none; font-size: 1.5em; color: #FF6347; cursor: pointer; transition: transform 0.3s;
        }
        .toggle-btn.expanded { transform: rotate(90deg); }
        .form-section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
            padding-top: 0;
        }
        .form-section-content.expanded {
            padding-top: 25px;
            max-height: 2000px; /* Adjust as needed */
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group input[type="time"], .form-group input[type="url"], .form-group textarea, .form-group select {
            width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.95rem; box-sizing: border-box;
        }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-actions { margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end; }
        .btn-form-submit, .btn-form-cancel {
            padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 0.95rem; font-weight: 600;
            transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; border: none;
        }
        .btn-form-submit { background-color: #FF6347; color: #fff; }
        .btn-form-submit:hover { background-color: #E5533D; }
        .btn-form-cancel { background-color: #6c757d; color: #fff; }
        .btn-form-cancel:hover { background-color: #5a6268; }


        /* Filter Section */
        .filter-section {
            background-color: #fffaf0; padding: 20px; border-radius: 8px; margin-bottom: 25px;
            border: 1px solid #ffe4b5; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group.wide { flex: 2; min-width: 250px; }
        .btn-filter, .btn-clear-filter, .btn-print {
             padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 0.95rem; font-weight: 600;
            transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; border: none;
        }
        .btn-filter { background-color: #FF8C00; color: #fff; }
        .btn-clear-filter { background-color: #6c757d; color: #fff; }
        .btn-print { background-color: #20B2AA; color: #fff; }

        /* Tables */
        .data-table {
            width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px;
            border: 1px solid #cfd8dc; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .data-table th, .data-table td { border-bottom: 1px solid #e0e0e0; padding: 15px; text-align: left; vertical-align: middle; }
        .data-table th { background-color: #ffe0b3; color: #FF6347; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; }
        .data-table tr:nth-child(even) { background-color: #fff5e8; }
        .data-table tr:hover { background-color: #ffeeda; }

        .action-buttons-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-action {
            padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; border: 1px solid transparent;
        }
        .btn-edit { background-color: #FFC107; color: #333; }
        .btn-delete { background-color: #dc3545; color: #fff; }

        /* Pagination Styles */
        .pagination-container {
            display: flex; justify-content: space-between; align-items: center; margin-top: 25px;
            padding-top: 15px; border-top: 1px solid #eee; flex-wrap: wrap; gap: 10px;
        }
        .pagination-info { font-weight: 500; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-controls a, .pagination-controls span {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;
            text-decoration: none; color: #FF6347; background-color: #fff;
        }
        .pagination-controls a:hover { background-color: #ffeeda; }
        .pagination-controls .current-page { background-color: #FF6347; color: #fff; border-color: #FF6347; }
        .pagination-controls .disabled { color: #aaa; background-color: #f8f8f8; cursor: not-allowed; }

        .no-results { text-align: center; padding: 50px; font-size: 1.2em; color: #6c757d; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .filter-section, .pagination-container { flex-direction: column; align-items: stretch; }
            .data-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-newspaper"></i> Manage News & Events</h2>

        <div class="tab-controls">
            <button class="tab-button <?php echo ($active_tab == 'news' ? 'active' : ''); ?>" data-tab="news">News Articles</button>
            <button class="tab-button <?php echo ($active_tab == 'events' ? 'active' : ''); ?>" data-tab="events">Upcoming Events</button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- News Articles Tab Content -->
        <div id="news" class="tab-content <?php echo ($active_tab == 'news' ? 'active' : ''); ?>">
            <!-- Add/Edit News Article Section -->
            <div class="form-section">
                <div class="form-section-header" id="news-form-header">
                    <h3 id="news-form-title"><i class="fas fa-plus-circle"></i> Add New News Article</h3>
                    <button class="toggle-btn"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="form-section-content" id="news-form-content">
                    <form id="news-form" action="manage_news_events.php?tab=news" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="news_form_action" id="news-form-action" value="add_news">
                        <input type="hidden" name="news_id" id="news-id" value="">

                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="news_title">News Title:</label>
                                <input type="text" id="news_title" name="news_title" required placeholder="e.g., School Wins National Robotics Competition">
                            </div>
                            <div class="form-group">
                                <label for="news_date">Date:</label>
                                <input type="date" id="news_date" name="news_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="news_category">Category:</label>
                                <input type="text" id="news_category" name="news_category" placeholder="e.g., Academic, Sports, Event">
                            </div>
                            <div class="form-group full-width">
                                <label for="news_image_url">Image URL (Optional):</label>
                                <input type="url" id="news_image_url" name="news_image_url" placeholder="https://example.com/image.jpg">
                            </div>
                            <div class="form-group full-width">
                                <label for="news_excerpt">Excerpt (Short Summary):</label>
                                <textarea id="news_excerpt" name="news_excerpt" rows="3" placeholder="A brief summary for display on news listings"></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label for="news_content">Full Content:</label>
                                <textarea id="news_content" name="news_content" rows="6" required placeholder="Detailed news article content"></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-form-submit" id="news-submit-btn"><i class="fas fa-save"></i> Save Article</button>
                            <button type="button" class="btn-form-cancel" id="news-cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- News Articles Overview Section -->
            <h3><i class="fas fa-list"></i> News Articles Overview</h3>
            <div class="filter-section">
                <form action="manage_news_events.php" method="GET" style="display:contents;">
                    <input type="hidden" name="tab" value="news">
                    <div class="filter-group wide">
                        <label for="news_search_query"><i class="fas fa-search"></i> Search News:</label>
                        <input type="text" id="news_search_query" name="news_search" value="<?php echo htmlspecialchars($news_search_query); ?>" placeholder="Title, Excerpt, Content">
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
                        <?php if (!empty($news_search_query)): ?>
                            <a href="manage_news_events.php?tab=news" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (empty($news_articles)): ?>
                <p class="no-results">No news articles found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Excerpt</th>
                                <th style="width: 150px; text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($news_articles as $article): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($article['title']); ?></td>
                                <td><?php echo date("F j, Y", strtotime($article['date'])); ?></td>
                                <td><?php echo htmlspecialchars($article['category'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($article['excerpt'], 0, 100)) . (strlen($article['excerpt']) > 100 ? '...' : ''); ?></td>
                                <td>
                                    <div class="action-buttons-group">
                                        <button class="btn-action btn-edit news-edit-btn" 
                                            data-id="<?php echo $article['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($article['title']); ?>"
                                            data-date="<?php echo htmlspecialchars($article['date']); ?>"
                                            data-category="<?php echo htmlspecialchars($article['category']); ?>"
                                            data-image_url="<?php echo htmlspecialchars($article['image_url']); ?>"
                                            data-excerpt="<?php echo htmlspecialchars($article['excerpt']); ?>"
                                            data-content="<?php echo htmlspecialchars($article['content']); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form action="manage_news_events.php" method="POST" class="delete-form" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="type" value="news">
                                            <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
                                            <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- News Pagination -->
                <div class="pagination-container">
                     <div class="pagination-info">
                        Showing <?php echo $news_offset + 1; ?> to <?php echo min($news_offset + $news_records_per_page, $total_news); ?> of <?php echo $total_news; ?> results
                    </div>
                    <div class="pagination-controls">
                        <?php if ($news_current_page > 1): ?>
                            <a href="?tab=news&news_page=<?php echo $news_current_page - 1; ?>&news_search=<?php echo urlencode($news_search_query); ?>">Previous</a>
                        <?php else: ?>
                            <span class="disabled">Previous</span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_news_pages; $i++): ?>
                            <a href="?tab=news&news_page=<?php echo $i; ?>&news_search=<?php echo urlencode($news_search_query); ?>" class="<?php echo ($i == $news_current_page) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($news_current_page < $total_news_pages): ?>
                            <a href="?tab=news&news_page=<?php echo $news_current_page + 1; ?>&news_search=<?php echo urlencode($news_search_query); ?>">Next</a>
                        <?php else: ?>
                            <span class="disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Events Tab Content -->
        <div id="events" class="tab-content <?php echo ($active_tab == 'events' ? 'active' : ''); ?>">
             <!-- Add/Edit Event Section -->
             <div class="form-section">
                <div class="form-section-header" id="event-form-header">
                    <h3 id="event-form-title"><i class="fas fa-plus-circle"></i> Add New Event</h3>
                    <button class="toggle-btn"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="form-section-content" id="event-form-content">
                    <form id="event-form" action="manage_news_events.php?tab=events" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="event_form_action" id="event-form-action" value="add_event">
                        <input type="hidden" name="event_id" id="event-id" value="">

                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="event_title">Event Title:</label>
                                <input type="text" id="event_title" name="event_title" required placeholder="e.g., Annual Sports Day">
                            </div>
                             <div class="form-group">
                                <label for="event_date">Event Date:</label>
                                <input type="date" id="event_date" name="event_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="event_time">Time:</label>
                                <input type="time" id="event_time" name="event_time">
                            </div>
                            <div class="form-group">
                                <label for="event_location">Location:</label>
                                <input type="text" id="event_location" name="event_location" placeholder="e.g., School Auditorium">
                            </div>
                            <div class="form-group">
                                <label for="event_type">Event Type:</label>
                                 <select id="event_type" name="event_type">
                                    <option value="">-- Select Type --</option>
                                    <option value="Academic">Academic</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Cultural">Cultural</option>
                                    <option value="Holiday">Holiday</option>
                                    <option value="Meeting">Meeting</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                             <div class="form-group full-width">
                                <label for="event_image_url">Image URL (Optional):</label>
                                <input type="url" id="event_image_url" name="event_image_url" placeholder="https://example.com/image.jpg">
                            </div>
                            <div class="form-group full-width">
                                <label for="event_description">Description:</label>
                                <textarea id="event_description" name="event_description" rows="4" placeholder="Details about the event"></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-form-submit" id="event-submit-btn"><i class="fas fa-save"></i> Save Event</button>
                            <button type="button" class="btn-form-cancel" id="event-cancel-btn" style="display:none;"><i class="fas fa-times"></i> Cancel Edit</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Events Overview Section -->
            <h3><i class="fas fa-calendar-alt"></i> Upcoming Events Overview</h3>
            <div class="filter-section">
                 <form action="manage_news_events.php" method="GET" style="display:contents;">
                    <input type="hidden" name="tab" value="events">
                    <div class="filter-group wide">
                        <label for="events_search_query"><i class="fas fa-search"></i> Search Events:</label>
                        <input type="text" id="events_search_query" name="events_search" value="<?php echo htmlspecialchars($events_search_query); ?>" placeholder="Title, Location, Description">
                    </div>
                    <div class="filter-group">
                        <label for="events_filter_type">Filter by Type:</label>
                        <select id="events_filter_type" name="events_type" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="Academic" <?php if($events_filter_type == 'Academic') echo 'selected'; ?>>Academic</option>
                            <option value="Sports" <?php if($events_filter_type == 'Sports') echo 'selected'; ?>>Sports</option>
                            <option value="Cultural" <?php if($events_filter_type == 'Cultural') echo 'selected'; ?>>Cultural</option>
                            <option value="Holiday" <?php if($events_filter_type == 'Holiday') echo 'selected'; ?>>Holiday</option>
                            <option value="Meeting" <?php if($events_filter_type == 'Meeting') echo 'selected'; ?>>Meeting</option>
                            <option value="Other" <?php if($events_filter_type == 'Other') echo 'selected'; ?>>Other</option>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                         <?php if (!empty($events_search_query) || !empty($events_filter_type)): ?>
                            <a href="manage_news_events.php?tab=events" class="btn-clear-filter"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (empty($upcoming_events)): ?>
                <p class="no-results">No upcoming events found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                         <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date & Time</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th style="width: 150px; text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php foreach ($upcoming_events as $event): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo date("F j, Y", strtotime($event['event_date'])); ?> <?php if($event['time']) echo ' at ' . date("g:i A", strtotime($event['time'])); ?></td>
                                <td><?php echo htmlspecialchars($event['location'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($event['type'] ?: 'N/A'); ?></td>
                               <td>
                                    <div class="action-buttons-group">
                                        <button class="btn-action btn-edit event-edit-btn"
                                            data-id="<?php echo $event['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($event['title']); ?>"
                                            data-event_date="<?php echo htmlspecialchars($event['event_date']); ?>"
                                            data-time="<?php echo htmlspecialchars($event['time']); ?>"
                                            data-location="<?php echo htmlspecialchars($event['location']); ?>"
                                            data-type="<?php echo htmlspecialchars($event['type']); ?>"
                                            data-image_url="<?php echo htmlspecialchars($event['image_url']); ?>"
                                            data-description="<?php echo htmlspecialchars($event['description']); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form action="manage_news_events.php" method="POST" class="delete-form" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="type" value="event">
                                            <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                 <!-- Events Pagination -->
                 <div class="pagination-container">
                     <div class="pagination-info">
                        Showing <?php echo $events_offset + 1; ?> to <?php echo min($events_offset + $events_records_per_page, $total_events); ?> of <?php echo $total_events; ?> results
                    </div>
                    <div class="pagination-controls">
                        <?php if ($events_current_page > 1): ?>
                            <a href="?tab=events&events_page=<?php echo $events_current_page - 1; ?>&events_search=<?php echo urlencode($events_search_query); ?>&events_type=<?php echo urlencode($events_filter_type); ?>">Previous</a>
                        <?php else: ?>
                            <span class="disabled">Previous</span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_events_pages; $i++): ?>
                            <a href="?tab=events&events_page=<?php echo $i; ?>&events_search=<?php echo urlencode($events_search_query); ?>&events_type=<?php echo urlencode($events_filter_type); ?>" class="<?php echo ($i == $events_current_page) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($events_current_page < $total_events_pages): ?>
                            <a href="?tab=events&events_page=<?php echo $events_current_page + 1; ?>&events_search=<?php echo urlencode($events_search_query); ?>&events_type=<?php echo urlencode($events_filter_type); ?>">Next</a>
                        <?php else: ?>
                            <span class="disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- TABS ---
    const tabControls = document.querySelector('.tab-controls');
    const tabContents = document.querySelectorAll('.tab-content');

    tabControls.addEventListener('click', (e) => {
        if (e.target.tagName === 'BUTTON') {
            const tabId = e.target.dataset.tab;

            // Update button styles
            tabControls.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            e.target.classList.add('active');
            
            // Show correct content
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === tabId) {
                    content.classList.add('active');
                }
            });

            // Update URL without reloading
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }
    });

    // --- COLLAPSIBLE FORMS ---
    function setupCollapsibleForm(headerId, contentId) {
        const header = document.getElementById(headerId);
        const content = document.getElementById(contentId);
        const toggleBtnIcon = header.querySelector('.toggle-btn i');
        
        header.addEventListener('click', () => {
            const isExpanded = content.classList.contains('expanded');
            if (isExpanded) {
                content.classList.remove('expanded');
                toggleBtnIcon.classList.remove('fa-chevron-down');
                toggleBtnIcon.classList.add('fa-chevron-right');
            } else {
                content.classList.add('expanded');
                toggleBtnIcon.classList.remove('fa-chevron-right');
                toggleBtnIcon.classList.add('fa-chevron-down');
            }
        });
        
        // Function to expand the form
        header.expand = () => {
            if (!content.classList.contains('expanded')) {
                 content.classList.add('expanded');
                 toggleBtnIcon.classList.remove('fa-chevron-right');
                 toggleBtnIcon.classList.add('fa-chevron-down');
            }
        };

        return header;
    }
    const newsFormHeader = setupCollapsibleForm('news-form-header', 'news-form-content');
    const eventFormHeader = setupCollapsibleForm('event-form-header', 'event-form-content');

    // --- DELETE CONFIRMATION ---
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // --- EDIT NEWS FUNCTIONALITY ---
    const newsForm = document.getElementById('news-form');
    const newsFormTitle = document.getElementById('news-form-title');
    const newsSubmitBtn = document.getElementById('news-submit-btn');
    const newsCancelBtn = document.getElementById('news-cancel-btn');

    document.querySelectorAll('.news-edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            
            // Populate form
            newsForm.querySelector('#news-form-action').value = 'edit_news';
            newsForm.querySelector('#news-id').value = data.id;
            newsForm.querySelector('#news_title').value = data.title;
            newsForm.querySelector('#news_date').value = data.date;
            newsForm.querySelector('#news_category').value = data.category;
            newsForm.querySelector('#news_image_url').value = data.imageUrl;
            newsForm.querySelector('#news_excerpt').value = data.excerpt;
            newsForm.querySelector('#news_content').value = data.content;
            
            // Update UI
            newsFormTitle.innerHTML = '<i class="fas fa-edit"></i> Edit News Article';
            newsSubmitBtn.innerHTML = '<i class="fas fa-save"></i> Update Article';
            newsCancelBtn.style.display = 'inline-flex';
            
            // Expand and scroll to form
            newsFormHeader.expand();
            newsForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    
    newsCancelBtn.addEventListener('click', () => {
        newsForm.reset();
        newsForm.querySelector('#news-form-action').value = 'add_news';
        newsForm.querySelector('#news-id').value = '';
        newsFormTitle.innerHTML = '<i class="fas fa-plus-circle"></i> Add New News Article';
        newsSubmitBtn.innerHTML = '<i class="fas fa-save"></i> Save Article';
        newsCancelBtn.style.display = 'none';
    });

    // --- EDIT EVENT FUNCTIONALITY ---
    const eventForm = document.getElementById('event-form');
    const eventFormTitle = document.getElementById('event-form-title');
    const eventSubmitBtn = document.getElementById('event-submit-btn');
    const eventCancelBtn = document.getElementById('event-cancel-btn');

    document.querySelectorAll('.event-edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;

            // Populate form
            eventForm.querySelector('#event-form-action').value = 'edit_event';
            eventForm.querySelector('#event-id').value = data.id;
            eventForm.querySelector('#event_title').value = data.title;
            eventForm.querySelector('#event_date').value = data.event_date;
            eventForm.querySelector('#event_time').value = data.time;
            eventForm.querySelector('#event_location').value = data.location;
            eventForm.querySelector('#event_type').value = data.type;
            eventForm.querySelector('#event_image_url').value = data.imageUrl;
            eventForm.querySelector('#event_description').value = data.description;

            // Update UI
            eventFormTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Event';
            eventSubmitBtn.innerHTML = '<i class="fas fa-save"></i> Update Event';
            eventCancelBtn.style.display = 'inline-flex';
            
            // Expand and scroll to form
            eventFormHeader.expand();
            eventForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    
    eventCancelBtn.addEventListener('click', () => {
        eventForm.reset();
        eventForm.querySelector('#event-form-action').value = 'add_event';
        eventForm.querySelector('#event-id').value = '';
        eventFormTitle.innerHTML = '<i class="fas fa-plus-circle"></i> Add New Event';
        eventSubmitBtn.innerHTML = '<i class="fas fa-save"></i> Save Event';
        eventCancelBtn.style.display = 'none';
    });
});
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>