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
$principal_role = $_SESSION["role"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
// This function should be defined in config.php now.
// If it's still causing an error, ensure config.php has it.
// For redundancy, I'll place a local definition, but the ideal is in config.php
if (!function_exists('set_session_message')) {
    function set_session_message($msg, $type) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['message'] = $msg;
        $_SESSION['message_type'] = $type;
    }
}


// --- Filter Parameters ---
$filter_class_id = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$filter_creator_role = isset($_GET['creator_role']) ? trim($_GET['creator_role']) : null;
$filter_is_pinned = isset($_GET['is_pinned']) && $_GET['is_pinned'] !== '' ? (int)$_GET['is_pinned'] : null;
$filter_is_locked = isset($_GET['is_locked']) && $_GET['is_locked'] !== '' ? (int)$_GET['is_locked'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


// --- Pagination Configuration ---
$records_per_page = 10; // Number of forum posts to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;


// --- Fetch Filter Dropdown Data ---
$all_classes = [];
$sql_all_classes = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
if ($result = mysqli_query($link, $sql_all_classes)) {
    $all_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
} else {
    // If error, set message but don't stop execution
    set_session_message("Error fetching classes for filter: " . mysqli_error($link), "danger");
}


$creator_roles = ['Student', 'Teacher'];
$boolean_filters = [1 => 'Yes', 0 => 'No']; // For Is Pinned / Is Locked


// --- Process Post Moderation Actions (Pin/Lock/Delete Post) ---
if (isset($_GET['post_action']) && isset($_GET['post_id'])) {
    $post_id = (int)$_GET['post_id'];
    $action = $_GET['post_action']; // This will be 'pin', 'lock', or 'delete_post'

    if (empty($post_id)) {
        set_session_message("Invalid Post ID for action.", "danger");
        header("location: manage_student_forum.php?page={$current_page}"); // Redirect with current page
        exit;
    }

    $sql = "";
    $action_name = "";

    // CORRECTED: Match $action directly with 'pin' or 'lock'
    if ($action == 'pin') { // This will now correctly match 'pin' from JS
        $sql = "UPDATE forum_posts SET is_pinned = (1 - is_pinned) WHERE id = ?";
        $action_name = "Pinned status toggled";
    } elseif ($action == 'lock') { // This will now correctly match 'lock' from JS
        $sql = "UPDATE forum_posts SET is_locked = (1 - is_locked) WHERE id = ?";
        $action_name = "Locked status toggled";
    } elseif ($action == 'delete_post') {
        $sql = "DELETE FROM forum_posts WHERE id = ?";
        $action_name = "Post deleted";
    } else {
        set_session_message("Invalid action for forum post.", "danger");
        header("location: manage_student_forum.php?page={$current_page}"); // Redirect with current page
        exit;
    }

    if (!empty($sql) && $stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("{$action_name} successfully.", "success");
            } else {
                set_session_message("Post not found or status already set.", "danger");
            }
        } else {
            set_session_message("Error performing action: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
    } else if (empty($sql)) {
        set_session_message("SQL query for action was not generated.", "danger");
    } else {
        set_session_message("Failed to prepare statement for action: " . mysqli_error($link), "danger");
    }
    header("location: manage_student_forum.php?page={$current_page}");
    exit;
}

// --- Process Reply Deletion ---
if (isset($_GET['delete_reply_id']) && isset($_GET['post_id_for_reply'])) {
    $reply_id = (int)$_GET['delete_reply_id'];
    $post_id_for_reply_redirect = (int)$_GET['post_id_for_reply'];

    if (empty($reply_id)) {
        set_session_message("Invalid Reply ID for deletion.", "danger");
        header("location: manage_student_forum.php?view_post_id={$post_id_for_reply_redirect}");
        exit;
    }

    $sql = "DELETE FROM forum_replies WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $reply_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                set_session_message("Reply deleted successfully.", "success");
            } else {
                set_session_message("Reply not found or already deleted.", "danger");
            }
        } else {
            set_session_message("Error deleting reply: " . mysqli_error($link), "danger");
        }
        mysqli_stmt_close($stmt);
        
        // Update last_reply_at for the parent post if its last reply was deleted
        $sql_update_last_reply = "UPDATE forum_posts fp
                                  LEFT JOIN (SELECT post_id, MAX(created_at) AS latest_reply FROM forum_replies GROUP BY post_id) fr_max
                                  ON fp.id = fr_max.post_id
                                  SET fp.last_reply_at = COALESCE(fr_max.latest_reply, fp.created_at)
                                  WHERE fp.id = ?";
        if ($stmt_update = mysqli_prepare($link, $sql_update_last_reply)) {
            mysqli_stmt_bind_param($stmt_update, "i", $post_id_for_reply_redirect);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        }

    } else {
         set_session_message("Failed to prepare statement for deleting reply: " . mysqli_error($link), "danger");
    }
    header("location: manage_student_forum.php?view_post_id={$post_id_for_reply_redirect}");
    exit;
}


// --- Build WHERE clause for total records and paginated data ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($filter_class_id) {
    $where_clauses[] = "fp.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}
if ($filter_creator_role) {
    $where_clauses[] = "fp.creator_role = ?";
    $params[] = $filter_creator_role;
    $types .= "s";
}
if ($filter_is_pinned !== null) {
    $where_clauses[] = "fp.is_pinned = ?";
    $params[] = $filter_is_pinned;
    $types .= "i";
}
if ($filter_is_locked !== null) {
    $where_clauses[] = "fp.is_locked = ?";
    $params[] = $filter_is_locked;
    $types .= "i";
}
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $where_clauses[] = "(fp.title LIKE ? OR fp.content LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR t.full_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sssss";
}

$where_sql = implode(" AND ", $where_clauses);


// --- Fetch Total Records for Pagination ---
$total_records = 0;
$total_records_sql = "SELECT COUNT(fp.id)
                      FROM forum_posts fp
                      JOIN classes c ON fp.class_id = c.id
                      LEFT JOIN students s ON fp.creator_id = s.id AND fp.creator_role = 'Student'
                      LEFT JOIN teachers t ON fp.creator_id = t.id AND fp.creator_role = 'Teacher'
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
    set_session_message("Error counting forum posts: " . mysqli_error($link), "danger");
    $total_records = 0; // Ensure total_records is initialized even on error
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


// --- Fetch Forum Posts Data (with filters and pagination) ---
$forum_posts = [];
$sql_fetch_posts = "SELECT
                            fp.id, fp.title, fp.content, fp.created_at, fp.last_reply_at, fp.is_pinned, fp.is_locked, fp.creator_role,
                            c.class_name, c.section_name,
                            COALESCE(s.first_name, t.full_name) AS creator_name,
                            (SELECT COUNT(fr.id) FROM forum_replies fr WHERE fr.post_id = fp.id) AS reply_count
                        FROM forum_posts fp
                        JOIN classes c ON fp.class_id = c.id
                        LEFT JOIN students s ON fp.creator_id = s.id AND fp.creator_role = 'Student'
                        LEFT JOIN teachers t ON fp.creator_id = t.id AND fp.creator_role = 'Teacher'
                        WHERE " . $where_sql . "
                        ORDER BY fp.is_pinned DESC, fp.last_reply_at DESC
                        LIMIT ? OFFSET ?";

// Add pagination params to the end
$params_pagination = $params; // Copy existing params
$params_pagination[] = $records_per_page;
$params_pagination[] = $offset;
$types_pagination = $types . "ii"; // Add types for LIMIT and OFFSET

if ($stmt = mysqli_prepare($link, $sql_fetch_posts)) {
    mysqli_stmt_bind_param($stmt, $types_pagination, ...$params_pagination);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $forum_posts = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    set_session_message("Error fetching forum posts: " . mysqli_error($link), "danger");
}


// --- Fetch Replies for a specific post if requested (for modal) ---
$current_post_for_modal = null;
$post_replies = [];
if (isset($_GET['view_post_id']) && is_numeric($_GET['view_post_id'])) {
    $view_post_id = (int)$_GET['view_post_id'];
    
    // Fetch the post details first
    $sql_fetch_post_details = "SELECT
                                fp.id, fp.title, fp.content, fp.created_at, fp.last_reply_at, fp.is_pinned, fp.is_locked, fp.creator_role,
                                c.class_name, c.section_name,
                                COALESCE(s.first_name, t.full_name) AS creator_name
                            FROM forum_posts fp
                            JOIN classes c ON fp.class_id = c.id
                            LEFT JOIN students s ON fp.creator_id = s.id AND fp.creator_role = 'Student'
                            LEFT JOIN teachers t ON fp.creator_id = t.id AND fp.creator_role = 'Teacher'
                            WHERE fp.id = ?";
    if ($stmt_post = mysqli_prepare($link, $sql_fetch_post_details)) {
        mysqli_stmt_bind_param($stmt_post, "i", $view_post_id);
        mysqli_stmt_execute($stmt_post);
        $result_post = mysqli_stmt_get_result($stmt_post);
        $current_post_for_modal = mysqli_fetch_assoc($result_post);
        mysqli_stmt_close($stmt_post);
    } else {
        set_session_message("Error fetching post details for modal: " . mysqli_error($link), "danger");
    }

    // Then fetch its replies
    $sql_fetch_replies = "SELECT
                                fr.id AS reply_id, fr.content, fr.created_at, fr.replier_role,
                                COALESCE(s.first_name, t.full_name) AS replier_name
                            FROM forum_replies fr
                            LEFT JOIN students s ON fr.replier_id = s.id AND fr.replier_role = 'Student'
                            LEFT JOIN teachers t ON fr.replier_id = t.id AND fr.replier_role = 'Teacher'
                            WHERE fr.post_id = ?
                            ORDER BY fr.created_at ASC";
    if ($stmt_replies = mysqli_prepare($link, $sql_fetch_replies)) {
        mysqli_stmt_bind_param($stmt_replies, "i", $view_post_id);
        mysqli_stmt_execute($stmt_replies);
        $result_replies = mysqli_stmt_get_result($stmt_replies);
        $post_replies = mysqli_fetch_all($result_replies, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_replies);
    } else {
        set_session_message("Error fetching post replies for modal: " . mysqli_error($link), "danger");
    }
}


mysqli_close($link);

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
    <title>Manage Student Forum - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #F0F8FF, #E6E6FA, #D8BFD8, #ADD8E6); /* Soft blues and purples */
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
            color: #483D8B; /* Dark Slate Blue */
            margin-bottom: 30px;
            border-bottom: 2px solid #E6E6FA;
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
        .alert-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }


        /* Filter Section */
        .filter-section {
            background-color: #f8f8ff; /* GhostWhite background */
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e6e6fa;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group.wide { flex: 2; min-width: 250px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #483D8B; }
        .filter-group select, .filter-group input[type="text"] {
            width: 100%; padding: 10px 12px; border: 1px solid #d8bfd8; border-radius: 5px;
            font-size: 1rem; box-sizing: border-box; background-color: #fff; color: #333;
        }
        .filter-group select {
            appearance: none; -webkit-appearance: none; -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23483D8B%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3Cpath%20fill%3D%22%23483D8B%22%20d%3D%22M287%20223a17.6%2017.6%200%200%200-13.2-6.4H18.8c-7.7%200-13.5%207.6-13.5%2013.2s7.6%2013.2%2013.2%2013.2h255.4c7.7%200%2013.5-7.6%2013.5-13.2z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat; background-position: right 10px center; background-size: 14px; padding-right: 30px;
        }
        .filter-buttons { display: flex; gap: 10px; flex-shrink: 0; margin-top: 10px; }
        .btn-filter, .btn-clear-filter, .btn-print {
            padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 600;
            transition: background-color 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-filter { background-color: #6A5ACD; color: #fff; border: 1px solid #6A5ACD; }
        .btn-filter:hover { background-color: #483D8B; }
        .btn-clear-filter { background-color: #808080; color: #fff; border: 1px solid #808080; }
        .btn-clear-filter:hover { background-color: #696969; }
        .btn-print { background-color: #20B2AA; color: #fff; border: 1px solid #20B2AA; }
        .btn-print:hover { background-color: #1A968A; }


        /* Posts Table Display */
        .posts-section-container {
            background-color: #fefefe; padding: 30px; border-radius: 10px; margin-bottom: 40px;
            border: 1px solid #e0e0e0; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        h3 { color: #483D8B; margin-bottom: 25px; font-size: 1.8em; display: flex; align-items: center; gap: 10px; }
        .posts-table {
            width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px;
            border: 1px solid #d8bfd8; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .posts-table th, .posts-table td { border-bottom: 1px solid #e0e0e0; padding: 15px; text-align: left; vertical-align: middle; }
        .posts-table th { background-color: #e6e6fa; color: #483D8B; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; }
        .posts-table tr:nth-child(even) { background-color: #f8f0ff; }
        .posts-table tr:hover { background-color: #efe8fa; }
        .text-center { text-align: center; }
        .text-muted { color: #6c757d; }
        .no-results { text-align: center; padding: 50px; font-size: 1.2em; color: #6c757d; }
        
        .status-badge { padding: 5px 10px; border-radius: 5px; font-size: 0.8em; font-weight: 600; white-space: nowrap; }
        .status-Pinned { background-color: #ffd700; color: #333; } /* Gold */
        .status-NotPinned { background-color: #ccc; color: #555; }
        .status-Locked { background-color: #ef5350; color: #fff; } /* Red */
        .status-Unlocked { background-color: #8bc34a; color: #fff; } /* Green */

        .action-buttons-group { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .btn-action { padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; border: 1px solid transparent; }
        .btn-pin, .btn-unpin { background-color: #FFD700; color: #333; border-color: #FFD700; }
        .btn-unpin { background-color: #ccc; }
        .btn-lock, .btn-unlock { background-color: #EF5350; color: #fff; border-color: #EF5350; }
        .btn-unlock { background-color: #8BC34A; }
        .btn-delete-post { background-color: #dc3545; color: #fff; border-color: #dc3545; }
        .btn-view-replies { background-color: #6495ED; color: #fff; border-color: #6495ED; }
        .btn-pin:hover, .btn-lock:hover, .btn-delete-post:hover, .btn-view-replies:hover { transform: translateY(-1px); }


        /* Pagination Styles */
        .pagination-container {
            display: flex; justify-content: space-between; align-items: center; margin-top: 25px;
            padding: 10px 0; border-top: 1px solid #eee; flex-wrap: wrap; gap: 10px;
        }
        .pagination-info { color: #555; font-size: 0.95em; font-weight: 500; }
        .pagination-controls { display: flex; gap: 5px; }
        .pagination-controls a, .pagination-controls span {
            display: block; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;
            text-decoration: none; color: #6A5ACD; background-color: #fff; transition: all 0.2s ease;
        }
        .pagination-controls a:hover { background-color: #e9ecef; border-color: #d8bfd8; }
        .pagination-controls .current-page, .pagination-controls .current-page:hover {
            background-color: #6A5ACD; color: #fff; border-color: #6A5ACD; cursor: default;
        }
        .pagination-controls .disabled, .pagination-controls .disabled:hover {
            color: #6c757d; background-color: #e9ecef; border-color: #dee2e6; cursor: not-allowed;
        }

        /* Modal Styles */
        .modal {
            display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center;
        }
        .modal-content {
            background-color: #fefefe; margin: auto; padding: 30px; border: 1px solid #888;
            width: 80%; max-width: 800px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative;
        }
        .modal-header { padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h4 { margin: 0; color: #333; font-size: 1.5em; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.3s ease; }
        .close-btn:hover { color: #000; }
        .modal-body { max-height: 70vh; overflow-y: auto; padding-right: 15px; }
        .modal-body p { margin-bottom: 10px; line-height: 1.5; }
        .modal-body strong { color: #555; }
        .modal-body .post-details { border-bottom: 1px dashed #ddd; padding-bottom: 15px; margin-bottom: 15px; }
        .modal-body .replies-list { margin-top: 20px; }
        .modal-body .reply-item { 
            background-color: #f8f8ff; border: 1px solid #e6e6fa; border-radius: 8px; padding: 10px 15px; margin-bottom: 10px;
            display: flex; flex-direction: column; position: relative;
        }
        .modal-body .reply-item.teacher-reply { background-color: #e0f2f7; }
        .modal-body .reply-item .reply-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .modal-body .reply-item .reply-author { font-weight: 600; color: #483D8B; font-size: 0.95em; }
        .modal-body .reply-item .reply-time { font-size: 0.8em; color: #888; }
        .modal-body .reply-item .reply-content { font-size: 0.9em; line-height: 1.4; color: #555; }
        .modal-body .delete-reply-btn {
            position: absolute; top: 10px; right: 10px; background: none; border: none; color: #dc3545;
            font-size: 1.1em; cursor: pointer; transition: color 0.2s;
        }
        .modal-body .delete-reply-btn:hover { color: #c82333; }
        .modal-footer { margin-top: 20px; text-align: right; }
        .btn-modal-close { background-color: #6c757d; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }

        /* Print Specific Styles */
        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; font-size: 10pt; padding: 10mm; }
            .printable-area h2, .printable-area h3 { color: #000; border-bottom: 1px solid #ccc; font-size: 16pt; margin-bottom: 15px; }
            .printable-area .posts-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .printable-area .posts-table th, .printable-area .posts-table td { border: 1px solid #eee; padding: 8px 10px; }
            .printable-area .posts-table th { background-color: #e6e6fa; color: #000; }
            .printable-area .status-badge { padding: 3px 6px; font-size: 0.7em; }
            .printable-area .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group, .modal { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-group.wide { min-width: unset; }
            .filter-buttons { flex-direction: column; width: 100%; }
            .btn-filter, .btn-clear-filter, .btn-print { width: 100%; justify-content: center; }
            .posts-table { display: block; overflow-x: auto; white-space: nowrap; }
            .modal-content { width: 95%; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="container">
        <h2><i class="fas fa-users-line"></i> Manage Student Forum</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter and Search Form -->
        <div class="filter-section">
            <form action="manage_student_forum.php" method="GET" style="display:contents;">
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
                    <label for="filter_creator_role"><i class="fas fa-user-tag"></i> Creator Role:</label>
                    <select id="filter_creator_role" name="creator_role">
                        <option value="">-- All Roles --</option>
                        <?php foreach ($creator_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>"
                                <?php echo ($filter_creator_role == $role) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_is_pinned"><i class="fas fa-thumbtack"></i> Pinned Status:</label>
                    <select id="filter_is_pinned" name="is_pinned">
                        <option value="">-- All --</option>
                        <option value="1" <?php echo ($filter_is_pinned === 1) ? 'selected' : ''; ?>>Pinned</option>
                        <option value="0" <?php echo ($filter_is_pinned === 0) ? 'selected' : ''; ?>>Not Pinned</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_is_locked"><i class="fas fa-lock"></i> Locked Status:</label>
                    <select id="filter_is_locked" name="is_locked">
                        <option value="">-- All --</option>
                        <option value="1" <?php echo ($filter_is_locked === 1) ? 'selected' : ''; ?>>Locked</option>
                        <option value="0" <?php echo ($filter_is_locked === 0) ? 'selected' : ''; ?>>Unlocked</option>
                    </select>
                </div>
                <div class="filter-group wide">
                    <label for="search_query"><i class="fas fa-search"></i> Search Posts:</label>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Title, Content, Creator">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <?php if ($filter_class_id || $filter_creator_role || $filter_is_pinned !== null || $filter_is_locked !== null || !empty($search_query)): ?>
                        <a href="manage_student_forum.php" class="btn-clear-filter"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php endif; ?>
                    <button type="button" class="btn-print" onclick="printForumPosts()"><i class="fas fa-print"></i> Print Posts</button>
                </div>
            </form>
        </div>

        <!-- Forum Posts Overview Table -->
        <div class="posts-section-container printable-area">
            <h3><i class="fas fa-comments"></i> Forum Posts Overview</h3>
            <?php if (empty($forum_posts)): ?>
                <p class="no-results">No forum posts found matching your criteria.</p>
            <?php else: ?>
                <div style="overflow-x:auto;" id="posts-table-wrapper">
                    <table class="posts-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Content</th>
                                <th>Class</th>
                                <th>Creator</th>
                                <th>Created / Last Reply</th>
                                <th>Replies</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forum_posts as $post): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($post['content'], 0, 100)); ?>
                                        <?php if (strlen($post['content']) > 100): ?>
                                            ... <a href="#" onclick="alert('Full Content: <?php echo htmlspecialchars($post['content']); ?>'); return false;" class="text-muted">more</a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($post['class_name'] . ' - ' . $post['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($post['creator_name'] ?: ($post['creator_role'] ?: 'N/A')) . ' (' . htmlspecialchars($post['creator_role']) . ')'; ?></td>
                                    <td><?php echo date("M j, Y H:i", strtotime($post['created_at'])) . '<br><small>Last reply: ' . date("M j, Y H:i", strtotime($post['last_reply_at'])) . '</small>'; ?></td>
                                    <td><?php echo htmlspecialchars($post['reply_count']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo ($post['is_pinned'] ? 'Pinned' : 'NotPinned'); ?>">
                                            <?php echo ($post['is_pinned'] ? 'Pinned' : 'Not Pinned'); ?>
                                        </span>
                                        <span class="status-badge status-<?php echo ($post['is_locked'] ? 'Locked' : 'Unlocked'); ?>">
                                            <?php echo ($post['is_locked'] ? 'Locked' : 'Unlocked'); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons-group">
                                            <button class="btn-action btn-view-replies" onclick="viewPostReplies(<?php echo htmlspecialchars(json_encode($post)); ?>)">
                                                <i class="fas fa-eye"></i> View Replies
                                            </button>
                                            <a href="javascript:void(0);" onclick="confirmTogglePostStatus(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars($post['title']); ?>', 'pin', <?php echo $post['is_pinned']; ?>)" class="btn-action <?php echo $post['is_pinned'] ? 'btn-unpin' : 'btn-pin'; ?>">
                                                <i class="fas fa-thumbtack"></i> <?php echo $post['is_pinned'] ? 'Unpin' : 'Pin'; ?>
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmTogglePostStatus(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars($post['title']); ?>', 'lock', <?php echo $post['is_locked']; ?>)" class="btn-action <?php echo $post['is_locked'] ? 'btn-unlock' : 'btn-lock'; ?>">
                                                <i class="fas fa-lock"></i> <?php echo $post['is_locked'] ? 'Unlock' : 'Lock'; ?>
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDeletePost(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars($post['title']); ?>')" class="btn-action btn-delete-post">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_records > 0): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> posts
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $base_url_params = array_filter([
                                'class_id' => $filter_class_id,
                                'creator_role' => $filter_creator_role,
                                'is_pinned' => $filter_is_pinned,
                                'is_locked' => $filter_is_locked,
                                'search' => $search_query
                            ]);
                            $base_url = "manage_student_forum.php?" . http_build_query($base_url_params);
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

<!-- View Replies Modal -->
<div id="repliesModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h4 id="replies-modal-title">Replies for Post: <span id="modal-post-title"></span></h4>
      <span class="close-btn" onclick="closeRepliesModal()">&times;</span>
    </div>
    <div class="modal-body">
      <div class="post-details">
        <p><strong>Creator:</strong> <span id="modal-post-creator"></span></p>
        <p><strong>Class:</strong> <span id="modal-post-class"></span></p>
        <p><strong>Created:</strong> <span id="modal-post-created-at"></span></p>
        <p><strong>Content:</strong> <span id="modal-post-content"></span></p>
        <p><strong>Status:</strong> 
            <span id="modal-post-pinned-status" class="status-badge"></span>
            <span id="modal-post-locked-status" class="status-badge"></span>
        </p>
        <hr>
      </div>

      <div class="replies-list">
        <h4>All Replies:</h4>
        <div id="modal-replies-content">
          <!-- Replies will be loaded here via JS -->
          <p class="text-muted text-center">No replies yet.</p>
        </div>
      </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn-modal-close" onclick="closeRepliesModal()">Close</button>
    </div>
  </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // If a post ID is passed in the URL (e.g., after a reply deletion), open the replies modal
        const viewPostId = <?php echo isset($_GET['view_post_id']) ? json_encode((int)$_GET['view_post_id']) : 'null'; ?>;
        if (viewPostId) {
            const postData = <?php echo json_encode($forum_posts); ?>.find(p => p.id === viewPostId);
            if (postData) {
                viewPostReplies(postData);
            }
        }
    });

    // --- Post Moderation JS ---
    function confirmTogglePostStatus(id, title, actionType, currentStatus) {
        let message = '';
        if (actionType === 'pin') {
            message = currentStatus ? `Are you sure you want to unpin the post "${title}"?` : `Are you sure you want to pin the post "${title}"?`;
        } else if (actionType === 'lock') {
            message = currentStatus ? `Are you sure you want to unlock the post "${title}"? New replies will be allowed.` : `Are you sure you want to lock the post "${title}"? No new replies will be allowed.`;
        }

        if (confirm(message)) {
            // CORRECTED: Ensure the base URL includes current pagination and filters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('post_action', actionType);
            urlParams.set('post_id', id);
            // 'page' parameter is already in current_page in PHP, no need to add again here.
            
            window.location.href = `manage_student_forum.php?${urlParams.toString()}`;
        }
    }

    function confirmDeletePost(id, title) {
        if (confirm(`Are you sure you want to permanently delete the post "${title}" and ALL its replies? This action cannot be undone.`)) {
            // CORRECTED: Ensure the base URL includes current pagination and filters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('post_action', 'delete_post');
            urlParams.set('post_id', id);
            
            window.location.href = `manage_student_forum.php?${urlParams.toString()}`;
        }
    }

    // --- View Replies Modal JS ---
    const repliesModal = document.getElementById('repliesModal');
    const modalPostTitle = document.getElementById('modal-post-title');
    const modalPostCreator = document.getElementById('modal-post-creator');
    const modalPostClass = document.getElementById('modal-post-class');
    const modalPostCreatedAt = document.getElementById('modal-post-created-at');
    const modalPostContent = document.getElementById('modal-post-content');
    const modalPostPinnedStatus = document.getElementById('modal-post-pinned-status');
    const modalPostLockedStatus = document.getElementById('modal-post-locked-status');
    const modalRepliesContent = document.getElementById('modal-replies-content');
    let currentPostIdForReplies = null; // To keep track for reply deletion redirect

    async function viewPostReplies(postData) {
        currentPostIdForReplies = postData.id;

        document.getElementById('modal-post-title').textContent = postData.title; // Use specific element ID
        modalPostCreator.textContent = `${postData.creator_name} (${postData.creator_role})`;
        modalPostClass.textContent = `${postData.class_name} - ${postData.section_name}`;
        modalPostCreatedAt.textContent = formatDateTime(postData.created_at);
        modalPostContent.textContent = postData.content;
        
        modalPostPinnedStatus.textContent = postData.is_pinned ? 'Pinned' : 'Not Pinned';
        modalPostPinnedStatus.className = `status-badge status-${postData.is_pinned ? 'Pinned' : 'NotPinned'}`;

        modalPostLockedStatus.textContent = postData.is_locked ? 'Locked' : 'Unlocked';
        modalPostLockedStatus.className = `status-badge status-${postData.is_locked ? 'Locked' : 'Unlocked'}`;

        modalRepliesContent.innerHTML = '<p class="text-muted text-center">Loading replies...</p>';

        try {
            const response = await fetch(`fetch_forum_replies.php?post_id=${postData.id}`);
            const replies = await response.json();
            modalRepliesContent.innerHTML = ''; // Clear loading message

            if (replies.length === 0) {
                modalRepliesContent.innerHTML = '<p class="text-muted text-center">No replies yet.</p>';
            } else {
                replies.forEach(reply => {
                    const replyItem = document.createElement('div');
                    replyItem.className = `reply-item ${reply.replier_role.toLowerCase()}-reply`; // student-reply or teacher-reply
                    
                    replyItem.innerHTML = `
                        <div class="reply-header">
                            <div class="reply-author">${reply.replier_name} (${reply.replier_role})</div>
                            <div class="reply-time">${formatDateTime(reply.created_at)}</div>
                        </div>
                        <div class="reply-content">${htmlspecialchars(reply.content)}</div>
                        <button class="delete-reply-btn" onclick="confirmDeleteReply(${reply.reply_id}, '${htmlspecialchars(reply.replier_name)}', '${postData.title}')" title="Delete Reply">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    `;
                    modalRepliesContent.appendChild(replyItem);
                });
            }
        } catch (error) {
            console.error('Error fetching replies:', error);
            modalRepliesContent.innerHTML = '<p class="text-muted text-center">Error loading replies.</p>';
        }

        repliesModal.style.display = 'flex'; // Show modal
    }

    function closeRepliesModal() {
        repliesModal.style.display = 'none';
        currentPostIdForReplies = null; // Clear active post ID
    }

    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target == repliesModal) {
            closeRepliesModal();
        }
    }

    function confirmDeleteReply(replyId, replierName, postTitle) {
        if (confirm(`Are you sure you want to permanently delete this reply by "${replierName}" in post "${postTitle}"? This action cannot be undone.`)) {
            // Ensure the base URL includes current pagination and filters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('delete_reply_id', replyId);
            urlParams.set('post_id_for_reply', currentPostIdForReplies); // Pass for redirect back to modal view
            
            window.location.href = `manage_student_forum.php?${urlParams.toString()}`;
        }
    }

    // Helper to format datetime string for display
    function formatDateTime(datetimeString) {
        if (!datetimeString || datetimeString === '0000-00-00 00:00:00') return 'N/A';
        const date = new Date(datetimeString);
        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString(undefined, options);
    }
    
    // Simple HTML escaping for display purposes in JS
    function htmlspecialchars(str) {
        let div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }


    // --- Print Functionality ---
    window.printForumPosts = function() {
        const printableContent = document.querySelector('.posts-section-container').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=1000');

        printWindow.document.write('<html><head><title>Student Forum Report</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">');
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20mm; }
            h2 { color: #000; border-bottom: 1px solid #ccc; padding-bottom: 12px; font-size: 16pt; margin-bottom: 25px; text-align: center; }
            h3 { color: #000; font-size: 14pt; margin-top: 20px; margin-bottom: 15px; }
            .posts-table { border: 1px solid #ccc; font-size: 9pt; box-shadow: none; page-break-inside: avoid; width: 100%; border-collapse: collapse; }
            .posts-table th, .posts-table td { border: 1px solid #eee; padding: 8px 10px; text-align: left; vertical-align: middle; }
            .posts-table th { background-color: #e6e6fa; color: #000; font-weight: 700; text-transform: uppercase; }
            .posts-table tr:nth-child(even) { background-color: #f8f0ff; }
            .status-badge { padding: 3px 6px; border-radius: 5px; font-size: 0.7em; font-weight: 600; white-space: nowrap; }
            .status-Pinned { background-color: #ffd700; color: #333; }
            .status-NotPinned { background-color: #ccc; color: #555; }
            .status-Locked { background-color: #ef5350; color: #fff; }
            .status-Unlocked { background-color: #8bc34a; color: #fff; }
            .no-results, .pagination-container, .filter-section, .btn-print, .action-buttons-group, .modal { display: none; }
            .fas { margin-right: 3px; }
            .text-muted { color: #6c757d; }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(`<h2 style="text-align: center;">Student Forum Report</h2>`);
        printWindow.document.write(printableContent);
        printWindow.document.write('</body></html>');

        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    };
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>