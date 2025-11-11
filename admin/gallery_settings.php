<?php
// Start the session and include necessary files
session_start(); // Manually start session since admin_header.php is removed


// --- Authentication Check ---
// Redirect to login if user is not logged in or not an Admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["role"] ?? '') !== 'Admin') {
    header("location: ../login.php");
    exit;
}

require_once  "../database/config.php";
require_once  "../database/cloudinary_upload_handler.php";
require_once './admin_header.php';
// Initialize session variables for messages
if (!isset($_SESSION['admin_message'])) {
    $_SESSION['admin_message'] = [];
}

// Function to safely get a value from an array
function get_array_value($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

// Function to decode JSON safely
function decode_json_safe($json_string) {
    $decoded = json_decode($json_string, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
}

// --- Process Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    // Use $_SESSION["id"] for the logged-in user's ID if available, otherwise 'admin' for logging
    $admin_id = $_SESSION["id"] ?? 'admin'; 

    // CSRF Protection (simple token, enhance for production)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Invalid CSRF token. Please try again.'];
        header("Location: gallery_settings.php");
        exit;
    }

    switch ($action) {
        case 'update_general_settings':
            $gallery_section_title = trim($_POST['gallery_section_title'] ?? '');
            $gallery_section_description = trim($_POST['gallery_section_description'] ?? '');
            $view_more_button_text = trim($_POST['view_more_button_text'] ?? '');
            $view_more_button_url = trim($_POST['view_more_button_url'] ?? '');
            $infra_highlights_title = trim($_POST['infra_highlights_title'] ?? '');

            $sql = "UPDATE gallery_settings SET gallery_section_title = ?, gallery_section_description = ?, view_more_button_text = ?, view_more_button_url = ?, infra_highlights_title = ? WHERE id = 1";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssss", $gallery_section_title, $gallery_section_description, $view_more_button_text, $view_more_button_url, $infra_highlights_title);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['admin_message'][] = ['type' => 'success', 'text' => 'General settings updated successfully.'];
                } else {
                    $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Error updating general settings: ' . mysqli_error($link)];
                    error_log("Error updating general gallery settings: " . mysqli_error($link));
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Database error preparing statement for general settings.'];
                error_log("Database error preparing statement for general settings: " . mysqli_error($link));
            }
            break;

        case 'add_category':
        case 'edit_category':
            $category_id = trim($_POST['category_id'] ?? '');
            $category_name = trim($_POST['category_name'] ?? '');
            $category_icon = trim($_POST['category_icon'] ?? '');

            // Prevent editing the ID of the 'all' category even if someone tries to bypass JS
            if ($action === 'edit_category' && $category_id === 'all' && ($_POST['original_category_id'] ?? '') !== 'all') {
                 // This specific check might be over-engineered if newCategoryId field is truly readonly.
                 // The front-end readonly should be sufficient, but a backend check is safer for custom inputs.
                 // However, for an "edit" action where ID is passed, if the ID is 'all', it should be immutable.
            }

            if (empty($category_id) || empty($category_name) || empty($category_icon)) {
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'All category fields are required.'];
            } else {
                $sql_select = "SELECT categories_json FROM gallery_settings WHERE id = 1";
                $result = mysqli_query($link, $sql_select);
                $row = mysqli_fetch_assoc($result);
                $categories = decode_json_safe($row['categories_json']);

                $is_new = true;
                foreach ($categories as $key => $cat) {
                    if (($cat['id'] ?? '') === $category_id) { // Use null coalescing for safety
                        $categories[$key]['name'] = $category_name;
                        $categories[$key]['icon'] = $category_icon;
                        $is_new = false;
                        break;
                    }
                }

                if ($is_new) {
                    // Ensure 'all' category is always first
                    if ($category_id === 'all') {
                        $categories = array_merge([['id' => 'all', 'name' => $category_name, 'icon' => $category_icon]], array_filter($categories, function($c){ return ($c['id'] ?? '') !== 'all'; }));
                    } else {
                        $categories[] = ['id' => $category_id, 'name' => $category_name, 'icon' => $category_icon];
                    }
                }
                
                $categories_json = json_encode($categories);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'JSON encoding error for categories: ' . json_last_error_msg()];
                    error_log('JSON encoding error for categories: ' . json_last_error_msg());
                } else {
                    $sql_update = "UPDATE gallery_settings SET categories_json = ? WHERE id = 1";
                    if ($stmt = mysqli_prepare($link, $sql_update)) {
                        mysqli_stmt_bind_param($stmt, "s", $categories_json);
                        if (mysqli_stmt_execute($stmt)) {
                            $_SESSION['admin_message'][] = ['type' => 'success', 'text' => 'Category ' . ($is_new ? 'added' : 'updated') . ' successfully.'];
                        } else {
                            $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Error saving categories: ' . mysqli_error($link)];
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
            }
            break;

        case 'delete_category':
            $category_id_to_delete = $_POST['delete_category_id'] ?? '';
            if (empty($category_id_to_delete) || $category_id_to_delete === 'all') { // Prevent deleting 'all'
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Invalid category to delete or cannot delete "All" category.'];
            } else {
                $sql_select = "SELECT categories_json FROM gallery_settings WHERE id = 1";
                $result = mysqli_query($link, $sql_select);
                $row = mysqli_fetch_assoc($result);
                $categories = decode_json_safe($row['categories_json']);

                $categories = array_filter($categories, function($cat) use ($category_id_to_delete) {
                    return ($cat['id'] ?? '') !== $category_id_to_delete; // Use null coalescing for safety
                });
                $categories = array_values($categories); // Re-index array

                $categories_json = json_encode($categories);
                $sql_update = "UPDATE gallery_settings SET categories_json = ? WHERE id = 1";
                if ($stmt = mysqli_prepare($link, $sql_update)) {
                    mysqli_stmt_bind_param($stmt, "s", $categories_json);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['admin_message'][] = ['type' => 'success', 'text' => 'Category deleted successfully.'];
                    } else {
                        $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Error deleting category: ' . mysqli_error($link)];
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            break;

        case 'add_gallery_item':
        case 'edit_gallery_item':
            $item_id = trim($_POST['item_id'] ?? ''); // Unique ID for existing items
            $item_title = trim($_POST['item_title'] ?? '');
            $item_description = trim($_POST['item_description'] ?? '');
            $item_category = trim($_POST['item_category'] ?? '');
            $item_type = trim($_POST['item_type'] ?? ''); // 'image' or 'video'
            $item_src_url = trim($_POST['item_src_url'] ?? ''); // For external URLs
            $item_public_id = trim($_POST['item_public_id'] ?? ''); // For Cloudinary

            $is_new_item = empty($item_id);

            if (empty($item_title) || empty($item_category) || empty($item_type)) {
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Title, Category, and Type are required for gallery items.'];
                break;
            }

            $current_media_src = null;
            $current_media_public_id = null;
            $current_media_type = $item_type; // Assume type from form initially

            // If editing, fetch current item to retain existing Cloudinary public_id if no new file/url is provided
            if (!$is_new_item) {
                $sql_select_current = "SELECT gallery_items_json FROM gallery_settings WHERE id = 1";
                $result_current = mysqli_query($link, $sql_select_current);
                $row_current = mysqli_fetch_assoc($result_current);
                $current_items = decode_json_safe($row_current['gallery_items_json']);
                foreach ($current_items as $ci) {
                    if (($ci['id'] ?? '') == $item_id) { // Use null coalescing for safety
                        $current_media_src = $ci['src_url'] ?? null;
                        $current_media_public_id = $ci['public_id'] ?? null;
                        $current_media_type = $ci['type'] ?? $item_type; // Use existing type if available
                        break;
                    }
                }
            }
            
            // Handle file upload if present
            if (isset($_FILES['gallery_media_file']) && $_FILES['gallery_media_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = uploadToCloudinary($_FILES['gallery_media_file']);
                if (isset($upload_result['error'])) {
                    $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Cloudinary Upload Error: ' . $upload_result['error']];
                    header("Location: gallery_settings.php");
                    exit;
                } else {
                    // Delete old Cloudinary asset if it exists and a new one is uploaded
                    if ($current_media_public_id && $current_media_src && strpos($current_media_src, 'res.cloudinary.com') !== false) {
                        deleteFromCloudinary($current_media_public_id, $current_media_type);
                    }
                    $item_src_url = $upload_result['secure_url'];
                    $item_public_id = $upload_result['public_id'];
                    $item_type = $upload_result['type']; // Use actual type determined by Cloudinary
                }
            } elseif (!empty($item_src_url)) {
                // If a new URL is provided, and it's not a Cloudinary URL, clear public_id
                if (strpos($item_src_url, 'res.cloudinary.com') === false) {
                    // If previously Cloudinary, delete the old asset
                    if ($current_media_public_id && $current_media_src && strpos($current_media_src, 'res.cloudinary.com') !== false) {
                        deleteFromCloudinary($current_media_public_id, $current_media_type);
                    }
                    $item_public_id = null; // Clear public ID for external URLs
                } else {
                    // If it's a Cloudinary URL, try to extract public_id if not explicitly set
                    if (empty($item_public_id)) {
                        preg_match('/v\d+\/(.+?)\.\w+$/', $item_src_url, $matches);
                        $item_public_id = $matches[1] ?? null;
                    }
                }
            } else {
                // No new file or URL provided, retain existing media if editing
                if (!$is_new_item) {
                    $item_src_url = $current_media_src;
                    $item_public_id = $current_media_public_id;
                    $item_type = $current_media_type;
                } else {
                     $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Either upload a file or provide a media URL for the gallery item.'];
                     break;
                }
            }

            if (empty($item_src_url) && empty($item_public_id)) {
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Media source is required for the gallery item.'];
                break;
            }

            $sql_select = "SELECT gallery_items_json FROM gallery_settings WHERE id = 1";
            $result = mysqli_query($link, $sql_select);
            $row = mysqli_fetch_assoc($result);
            $gallery_items = decode_json_safe($row['gallery_items_json']);

            $new_item = [
                'id' => $is_new_item ? uniqid() : $item_id,
                'title' => $item_title,
                'description' => $item_description,
                'category' => $item_category,
                'type' => $item_type,
                'src_url' => $item_src_url,
                'public_id' => $item_public_id, // Store public_id for Cloudinary deletion
            ];

            if ($is_new_item) {
                $gallery_items[] = $new_item;
            } else {
                $found = false;
                foreach ($gallery_items as $key => $item) {
                    if (($item['id'] ?? '') === $item_id) { // Use null coalescing for safety
                        $gallery_items[$key] = $new_item;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Gallery item not found for editing.'];
                    break;
                }
            }
            
            $gallery_items_json = json_encode($gallery_items);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'JSON encoding error for gallery items: ' . json_last_error_msg()];
                error_log('JSON encoding error for gallery items: ' . json_last_error_msg());
            } else {
                $sql_update = "UPDATE gallery_settings SET gallery_items_json = ? WHERE id = 1";
                if ($stmt = mysqli_prepare($link, $sql_update)) {
                    mysqli_stmt_bind_param($stmt, "s", $gallery_items_json);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['admin_message'][] = ['type' => 'success', 'text' => 'Gallery item ' . ($is_new_item ? 'added' : 'updated') . ' successfully.'];
                    } else {
                        $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Error saving gallery item: ' . mysqli_error($link)];
                        error_log("Error saving gallery item: " . mysqli_error($link));
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            break;

        case 'delete_gallery_item':
            $item_id_to_delete = $_POST['delete_item_id'] ?? '';
            if (empty($item_id_to_delete)) {
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Invalid gallery item to delete.'];
            } else {
                $sql_select = "SELECT gallery_items_json FROM gallery_settings WHERE id = 1";
                $result = mysqli_query($link, $sql_select);
                $row = mysqli_fetch_assoc($result);
                $gallery_items = decode_json_safe($row['gallery_items_json']);

                $item_found_and_deleted = false;
                $public_id_to_delete = null;
                $type_to_delete = null;

                foreach ($gallery_items as $key => $item) {
                    if (($item['id'] ?? '') === $item_id_to_delete) { // Use null coalescing for safety
                        if (isset($item['public_id']) && !empty($item['public_id']) && strpos(($item['src_url'] ?? ''), 'res.cloudinary.com') !== false) {
                            $public_id_to_delete = $item['public_id'];
                            $type_to_delete = $item['type'] ?? 'image'; // Default to image if not specified
                        }
                        unset($gallery_items[$key]);
                        $item_found_and_deleted = true;
                        break;
                    }
                }

                if (!$item_found_and_deleted) {
                    $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Gallery item not found for deletion.'];
                    break;
                }

                $gallery_items = array_values($gallery_items); // Re-index array
                $gallery_items_json = json_encode($gallery_items);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'JSON encoding error after deleting gallery item: ' . json_last_error_msg()];
                    error_log('JSON encoding error after deleting gallery item: ' . json_last_error_msg());
                } else {
                    $sql_update = "UPDATE gallery_settings SET gallery_items_json = ? WHERE id = 1";
                    if ($stmt = mysqli_prepare($link, $sql_update)) {
                        mysqli_stmt_bind_param($stmt, "s", $gallery_items_json);
                        if (mysqli_stmt_execute($stmt)) {
                            // If there's a Cloudinary asset, delete it
                            if ($public_id_to_delete) {
                                $delete_result = deleteFromCloudinary($public_id_to_delete, $type_to_delete);
                                if (isset($delete_result['error'])) {
                                    $_SESSION['admin_message'][] = ['type' => 'warning', 'text' => 'Gallery item deleted from DB, but Cloudinary deletion failed: ' . $delete_result['error']];
                                } else {
                                    $_SESSION['admin_message'][] = ['type' => 'success', 'text' => 'Gallery item and Cloudinary asset deleted successfully.'];
                                }
                            } else {
                                $_SESSION['admin_message'][] = ['type' => 'success', 'text' => 'Gallery item deleted successfully.'];
                            }
                        } else {
                            $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Error deleting gallery item: ' . mysqli_error($link)];
                            error_log("Error deleting gallery item: " . mysqli_error($link));
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
            }
            break;

        case 'add_infra_highlight':
        case 'edit_infra_highlight':
            $highlight_id = trim($_POST['highlight_id'] ?? '');
            $highlight_title = trim($_POST['highlight_title'] ?? '');
            $highlight_count = trim($_POST['highlight_count'] ?? '');
            $highlight_icon = trim($_POST['highlight_icon'] ?? '');
            $highlight_color_from = trim($_POST['highlight_color_from'] ?? '');
            $highlight_color_to = trim($_POST['highlight_color_to'] ?? '');

            if (empty($highlight_title) || empty($highlight_count) || empty($highlight_icon) || empty($highlight_color_from) || empty($highlight_color_to)) {
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'All infrastructure highlight fields are required.'];
                break;
            }

            $sql_select = "SELECT infra_highlights_json FROM gallery_settings WHERE id = 1";
            $result = mysqli_query($link, $sql_select);
            $row = mysqli_fetch_assoc($result);
            $highlights = decode_json_safe($row['infra_highlights_json']);

            $is_new_highlight = empty($highlight_id);
            $new_highlight = [
                'id' => $is_new_highlight ? uniqid() : $highlight_id,
                'title' => $highlight_title,
                'count' => $highlight_count,
                'icon' => $highlight_icon,
                'color_from' => $highlight_color_from,
                'color_to' => $highlight_color_to,
            ];

            if ($is_new_highlight) {
                $highlights[] = $new_highlight;
            } else {
                $found = false;
                foreach ($highlights as $key => $hl) {
                    if (($hl['id'] ?? '') === $highlight_id) { // Use null coalescing for safety
                        $highlights[$key] = $new_highlight;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Infrastructure highlight not found for editing.'];
                    break;
                }
            }

            $infra_highlights_json = json_encode($highlights);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'JSON encoding error for infra highlights: ' . json_last_error_msg()];
                error_log('JSON encoding error for infra highlights: ' . json_last_error_msg());
            } else {
                $sql_update = "UPDATE gallery_settings SET infra_highlights_json = ? WHERE id = 1";
                if ($stmt = mysqli_prepare($link, $sql_update)) {
                    mysqli_stmt_bind_param($stmt, "s", $infra_highlights_json);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['admin_message'][] = ['type' => 'success', 'text' => 'Infrastructure highlight ' . ($is_new_highlight ? 'added' : 'updated') . ' successfully.'];
                    } else {
                        $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Error saving infrastructure highlight: ' . mysqli_error($link)];
                        error_log("Error saving infrastructure highlight: " . mysqli_error($link));
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            break;

        case 'delete_infra_highlight':
            $highlight_id_to_delete = $_POST['delete_highlight_id'] ?? '';
            if (empty($highlight_id_to_delete)) {
                $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Invalid highlight to delete.'];
            } else {
                $sql_select = "SELECT infra_highlights_json FROM gallery_settings WHERE id = 1";
                $result = mysqli_query($link, $sql_select);
                $row = mysqli_fetch_assoc($result);
                $highlights = decode_json_safe($row['infra_highlights_json']);

                $highlights = array_filter($highlights, function($hl) use ($highlight_id_to_delete) {
                    return ($hl['id'] ?? '') !== $highlight_id_to_delete; // Use null coalescing for safety
                });
                $highlights = array_values($highlights); // Re-index array

                $infra_highlights_json = json_encode($highlights);
                $sql_update = "UPDATE gallery_settings SET infra_highlights_json = ? WHERE id = 1";
                if ($stmt = mysqli_prepare($link, $sql_update)) {
                    mysqli_stmt_bind_param($stmt, "s", $infra_highlights_json);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['admin_message'][] = ['type' => 'success', 'text' => 'Infrastructure highlight deleted successfully.'];
                    } else {
                        $_SESSION['admin_message'][] = ['type' => 'error', 'text' => 'Error deleting infrastructure highlight: ' . mysqli_error($link)];
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            break;
    }
    header("Location: gallery_settings.php");
    exit;
}

// Generate a new CSRF token for each request
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// --- Fetch Current Gallery Settings for Display ---
$settings = [
    'gallery_section_title' => '',
    'gallery_section_description' => '',
    'categories_json' => '[]',
    'gallery_items_json' => '[]',
    'view_more_button_text' => '',
    'view_more_button_url' => '',
    'infra_highlights_title' => '',
    'infra_highlights_json' => '[]'
];

$sql = "SELECT * FROM gallery_settings WHERE id = 1";
if ($result = mysqli_query($link, $sql)) {
    if (mysqli_num_rows($result) == 1) {
        $db_settings = mysqli_fetch_assoc($result);
        foreach ($settings as $key => $value) {
            if (isset($db_settings[$key])) {
                $settings[$key] = $db_settings[$key];
            }
        }
    }
    mysqli_free_result($result);
} else {
    error_log("Error fetching gallery_settings: " . mysqli_error($link));
}

// Decode JSON data for display
$categories_display = decode_json_safe($settings['categories_json']);
// Ensure 'all' category exists and is first if there are categories.
$hasAllCategory = false;
foreach ($categories_display as $cat) {
    if (($cat['id'] ?? '') === 'all') { // Use null coalescing for safety
        $hasAllCategory = true;
        break;
    }
}
if (!$hasAllCategory) {
    array_unshift($categories_display, [
        "id" => "all",
        "name" => "All",
        "icon" => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line></svg>'
    ]);
}
// For select options, filter 'all' to be implicit or handle separately
$categories_for_select = array_filter($categories_display, function($cat){ return ($cat['id'] ?? '') !== 'all'; });


$gallery_items_display = decode_json_safe($settings['gallery_items_json']);
$infra_highlights_display = decode_json_safe($settings['infra_highlights_json']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Gallery Management</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../css/admin_styles.css"> <!-- Assuming common admin styles -->

    <style>
        /* Custom Styles for a more polished Admin UI */
        body {
            font-family: 'Inter', sans-serif; /* A modern font */
        }
        .sidebar-link.active {
            background-color: #4f46e5; /* Deeper indigo for active state */
            color: #e0e7ff;
        }
        .card {
            background-color: #fff;
            border-radius: 0.75rem; /* More rounded corners */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* Stronger shadow */
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb; /* Light border */
        }
        .form-input, .form-textarea, .form-select {
            display: block;
            width: 100%;
            padding: 0.625rem 1rem; /* Slightly more padding */
            border: 1px solid #d1d5db; /* Lighter border */
            border-radius: 0.5rem; /* More rounded inputs */
            font-size: 1rem;
            line-height: 1.5;
            color: #374151; /* Darker text */
            background-color: #fff;
            transition: all 0.2s ease-in-out; /* Smooth transitions */
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            border-color: #6366f1; /* Indigo focus color */
            outline: 0;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25); /* Focus ring */
        }
        .btn-primary {
            background-color: #6366f1; /* Indigo-600 */
            color: #fff;
            font-weight: 600; /* Semibold */
            padding: 0.75rem 1.5rem; /* Larger buttons */
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-primary:hover {
            background-color: #4f46e5; /* Indigo-700 */
            transform: translateY(-1px); /* Subtle lift effect */
        }
        .btn-secondary {
            background-color: #e0e7ff; /* Indigo-100 */
            color: #4f46e5; /* Indigo-700 */
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .btn-secondary:hover {
            background-color: #c7d2fe; /* Indigo-200 */
            transform: translateY(-1px);
        }
        .btn-danger {
            background-color: #ef4444; /* Red-500 */
            color: #fff;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-danger:hover {
            background-color: #dc2626; /* Red-600 */
            transform: translateY(-1px);
        }
        /* Alert messages */
        .alert-success {
            background-color: #dcfce7; /* Green-100 */
            color: #15803d; /* Green-700 */
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #4ade80; /* Green-400 */
            font-weight: 500;
        }
        .alert-error {
            background-color: #fee2e2; /* Red-100 */
            color: #b91c1c; /* Red-700 */
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #f87171; /* Red-400 */
            font-weight: 500;
        }
        .alert-warning {
            background-color: #fffbeb; /* Yellow-100 */
            color: #b45309; /* Yellow-700 */
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #fcd34d; /* Yellow-400 */
            font-weight: 500;
        }

        /* Modal styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Darker overlay */
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px); /* Blurred background */
        }
        .modal-content {
            background-color: #fefefe;
            padding: 2.5rem; /* More padding */
            border: none; /* No default border */
            width: 95%; /* Wider on small screens */
            max-width: 600px; /* Max width */
            border-radius: 0.75rem; /* Consistent border radius */
            position: relative;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: fadeIn 0.3s ease-out; /* Fade-in animation */
        }
        .close-button {
            color: #9ca3af; /* Gray-400 */
            font-size: 2.25rem; /* Larger close button */
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            cursor: pointer;
            transition: color 0.2s ease-in-out;
        }
        .close-button:hover,
        .close-button:focus {
            color: #4b5563; /* Gray-600 */
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                display: none; /* Hide sidebar on mobile */
            }
            .modal-content {
                width: 95%; /* Make modal wider on smaller screens */
                padding: 1.5rem;
            }
            .btn-primary, .btn-secondary, .btn-danger {
                padding: 0.625rem 1.25rem; /* Slightly smaller buttons on mobile */
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 flex min-h-screen">

    

        <!-- Main Content Wrapper (padding, max-width) -->
        <main class="p-6 flex-1 m-16">
            <div class="max-w-7xl mx-auto">

                <?php
                // Display messages
                foreach ($_SESSION['admin_message'] as $msg) {
                    echo '<div class="alert-' . htmlspecialchars($msg['type']) . '" role="alert">' . htmlspecialchars($msg['text']) . '</div>';
                }
                $_SESSION['admin_message'] = []; // Clear messages after display
                ?>

                <!-- General Settings Card -->
                <div class="card mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-3">General Gallery Settings</h2>
                    <form action="gallery_settings.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_general_settings">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="gallery_section_title" class="block text-gray-700 text-sm font-semibold mb-2">Section Title:</label>
                                <input type="text" id="gallery_section_title" name="gallery_section_title" class="form-input" value="<?php echo htmlspecialchars(get_array_value($settings, 'gallery_section_title')); ?>" required>
                            </div>
                            <div>
                                <label for="view_more_button_text" class="block text-gray-700 text-sm font-semibold mb-2">"View More" Button Text:</label>
                                <input type="text" id="view_more_button_text" name="view_more_button_text" class="form-input" value="<?php echo htmlspecialchars(get_array_value($settings, 'view_more_button_text')); ?>" required>
                            </div>
                            <div class="md:col-span-2">
                                <label for="gallery_section_description" class="block text-gray-700 text-sm font-semibold mb-2">Section Description:</label>
                                <textarea id="gallery_section_description" name="gallery_section_description" class="form-textarea" rows="4" required><?php echo htmlspecialchars(get_array_value($settings, 'gallery_section_description')); ?></textarea>
                            </div>
                            <div>
                                <label for="view_more_button_url" class="block text-gray-700 text-sm font-semibold mb-2">"View More" Button URL:</label>
                                <input type="url" id="view_more_button_url" name="view_more_button_url" class="form-input" value="<?php echo htmlspecialchars(get_array_value($settings, 'view_more_button_url')); ?>" required>
                            </div>
                            <div>
                                <label for="infra_highlights_title" class="block text-gray-700 text-sm font-semibold mb-2">Infrastructure Highlights Title:</label>
                                <input type="text" id="infra_highlights_title" name="infra_highlights_title" class="form-input" value="<?php echo htmlspecialchars(get_array_value($settings, 'infra_highlights_title')); ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary mt-6">Update General Settings</button>
                    </form>
                </div>

                <!-- Categories Management Card -->
                <div class="card mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-3">Manage Gallery Categories</h2>
                    <button class="btn-primary mb-6" onclick="openCategoryModal('add')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block mr-2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Add New Category
                    </button>

                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Icon</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($categories_display)): ?>
                                    <tr><td colspan="4" class="py-6 px-4 text-center text-gray-500">No categories defined.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($categories_display as $category): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars(get_array_value($category, 'id')); ?></td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars(get_array_value($category, 'name')); ?></td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800 flex items-center gap-2">
                                                <div class="w-6 h-6 text-gray-600 flex-shrink-0"><?php echo get_array_value($category, 'icon'); ?></div>
                                                <span class="text-xs text-gray-500 truncate max-w-[100px]">[SVG Code]</span>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm">
                                                
                                                <?php if (get_array_value($category, 'id') !== 'all'): // Prevent deleting 'all' category ?>
                                                    <form action="gallery_settings.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="delete_category">
                                                        <input type="hidden" name="delete_category_id" value="<?php echo htmlspecialchars(get_array_value($category, 'id')); ?>">
                                                        <button type="submit" class="btn-danger text-sm py-1 px-3">Delete</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Gallery Items Management Card -->
                <div class="card mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-3">Manage Gallery Items</h2>
                    <button class="btn-primary mb-6" onclick="openGalleryItemModal('add')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block mr-2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Add New Gallery Item
                    </button>

                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Title</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Category</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Media</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($gallery_items_display)): ?>
                                    <tr><td colspan="5" class="py-6 px-4 text-center text-gray-500">No gallery items defined.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($gallery_items_display as $item): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars(get_array_value($item, 'title')); ?></td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars(get_array_value($item, 'category')); ?></td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars(ucfirst(get_array_value($item, 'type'))); ?></td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800">
                                                <?php if (get_array_value($item, 'src_url')): ?>
                                                    <a href="<?php echo htmlspecialchars(get_array_value($item, 'src_url')); ?>" target="_blank" class="text-indigo-600 hover:underline">View Media</a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm">
                                                <button type="button" class="btn-secondary text-sm py-1 px-3 mr-2"
                                                    onclick="openGalleryItemModal('edit',
                                                        '<?php echo htmlspecialchars(get_array_value($item, 'id')); ?>',
                                                        '<?php echo htmlspecialchars(get_array_value($item, 'title')); ?>',
                                                        '<?php echo htmlspecialchars(get_array_value($item, 'description')); ?>',
                                                        '<?php echo htmlspecialchars(get_array_value($item, 'category')); ?>',
                                                        '<?php echo htmlspecialchars(get_array_value($item, 'type')); ?>',
                                                        '<?php echo htmlspecialchars(get_array_value($item, 'src_url')); ?>',
                                                        '<?php echo htmlspecialchars(get_array_value($item, 'public_id')); ?>')">Edit</button>
                                                <form action="gallery_settings.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this gallery item? This will also delete the file from Cloudinary if it was uploaded there.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="delete_gallery_item">
                                                    <input type="hidden" name="delete_item_id" value="<?php echo htmlspecialchars(get_array_value($item, 'id')); ?>">
                                                    <button type="submit" class="btn-danger text-sm py-1 px-3">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Infrastructure Highlights Management Card -->
                <div class="card">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-3">Manage Infrastructure Highlights</h2>
                    <button class="btn-primary mb-6" onclick="openInfraHighlightModal('add')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block mr-2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Add New Highlight
                    </button>

                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Title</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Count</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Icon</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Colors</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($infra_highlights_display)): ?>
                                    <tr><td colspan="5" class="py-6 px-4 text-center text-gray-500">No infrastructure highlights defined.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($infra_highlights_display as $highlight): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars(get_array_value($highlight, 'title')); ?></td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars(get_array_value($highlight, 'count')); ?></td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800 flex items-center gap-2">
                                                <div class="w-6 h-6 text-gray-600 flex-shrink-0"><?php echo get_array_value($highlight, 'icon'); ?></div>
                                                <span class="text-xs text-gray-500 truncate max-w-[100px]">[SVG Code]</span>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800">
                                                <span class="text-xs font-medium text-gray-700">From:</span> <code class="text-indigo-600 bg-indigo-50 px-1 py-0.5 rounded"><?php echo htmlspecialchars(get_array_value($highlight, 'color_from')); ?></code><br>
                                                <span class="text-xs font-medium text-gray-700">To:</span> <code class="text-blue-600 bg-blue-50 px-1 py-0.5 rounded"><?php echo htmlspecialchars(get_array_value($highlight, 'color_to')); ?></code>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap text-sm">
                                                 
                                                <form action="gallery_settings.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this highlight?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="delete_infra_highlight">
                                                    <input type="hidden" name="delete_highlight_id" value="<?php echo htmlspecialchars(get_array_value($highlight, 'id')); ?>">
                                                    <button type="submit" class="btn-danger text-sm py-1 px-3">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div> <!-- Close .max-w-7xl -->
        </main>
    </div> <!-- Close .flex-1 -->

<!-- Category Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal('categoryModal')">&times;</span>
        <h3 class="text-2xl font-bold mb-6 text-gray-800" id="categoryModalTitle">Add New Category</h3>
        <form action="gallery_settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" id="categoryAction">
            <input type="hidden" name="original_category_id" id="originalCategoryId"> <!-- To keep track if ID was 'all' -->

            <div class="mb-4">
                <label for="newCategoryId" class="block text-gray-700 text-sm font-semibold mb-2">Category ID (Unique, e.g., 'academics'):</label>
                <input type="text" id="newCategoryId" name="category_id" class="form-input" required>
                <p id="categoryIdReadonlyMessage" class="text-sm text-yellow-600 mt-1 hidden">The ID for the 'All' category cannot be changed.</p>
            </div>
            <div class="mb-4">
                <label for="categoryName" class="block text-gray-700 text-sm font-semibold mb-2">Category Name (e.g., 'Academics'):</label>
                <input type="text" id="categoryName" name="category_name" class="form-input" required>
            </div>
            <div class="mb-4">
                <label for="categoryIcon" class="block text-gray-700 text-sm font-semibold mb-2">Category Icon (Full SVG code):</label>
                <textarea id="categoryIcon" name="category_icon" class="form-textarea" rows="3" placeholder="e.g., <svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;24&quot; height=&quot;24&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot;><path d=&quot;M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20&quot;/><polyline points=&quot;10 2 10 22&quot;/></svg>" required></textarea>
                <p class="text-xs text-gray-500 mt-1">Find SVG icons from <a href="https://lucide.dev/icons/" target="_blank" class="text-indigo-600 hover:underline">Lucide Icons</a> (copy SVG code).</p>
            </div>
            <button type="submit" class="btn-primary mt-4">Save Category</button>
        </form>
    </div>
</div>

<!-- Gallery Item Modal -->
<div id="galleryItemModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal('galleryItemModal')">&times;</span>
        <h3 class="text-2xl font-bold mb-6 text-gray-800" id="galleryItemModalTitle">Add New Gallery Item</h3>
        <form action="gallery_settings.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" id="galleryItemAction">
            <input type="hidden" name="item_id" id="galleryItemId">
            <input type="hidden" name="item_public_id" id="galleryItemPublicId"> <!-- For existing Cloudinary public_id -->

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="itemTitle" class="block text-gray-700 text-sm font-semibold mb-2">Title:</label>
                    <input type="text" id="itemTitle" name="item_title" class="form-input" required>
                </div>
                <div>
                    <label for="itemCategory" class="block text-gray-700 text-sm font-semibold mb-2">Category:</label>
                    <select id="itemCategory" name="item_category" class="form-select" required>
                        <option value="">Select a Category</option>
                        <?php foreach ($categories_for_select as $cat): ?>
                            <option value="<?php echo htmlspecialchars(get_array_value($cat, 'id')); ?>"><?php echo htmlspecialchars(get_array_value($cat, 'name')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-4 mt-4">
                <label for="itemDescription" class="block text-gray-700 text-sm font-semibold mb-2">Description:</label>
                <textarea id="itemDescription" name="item_description" class="form-textarea" rows="2"></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-semibold mb-2">Media Type:</label>
                <div class="flex flex-wrap gap-4 items-center">
                    <label class="flex items-center">
                        <input type="radio" id="itemTypeImage" name="item_type" value="image" class="mr-2 h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" onchange="toggleMediaInput()" required>
                        <span class="text-gray-700">Image</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" id="itemTypeVideo" name="item_type" value="video" class="mr-2 h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" onchange="toggleMediaInput()">
                        <span class="text-gray-700">Video</span>
                    </label>
                </div>
            </div>
            <div class="mb-4" id="mediaFileInputGroup">
                <label for="galleryMediaFile" class="block text-gray-700 text-sm font-semibold mb-2">Upload File (Image/Video):</label>
                <input type="file" id="galleryMediaFile" name="gallery_media_file" class="form-input p-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                <p class="text-xs text-gray-500 mt-1">Max 50MB. Uploading a new file will replace any existing media for this item.</p>
            </div>
            <div class="mb-4" id="mediaUrlInputGroup">
                <label for="itemSrcUrl" class="block text-gray-700 text-sm font-semibold mb-2">Or Provide Media URL:</label>
                <input type="url" id="itemSrcUrl" name="item_src_url" class="form-input" placeholder="e.g., https://example.com/image.jpg or https://example.com/video.mp4">
                <p class="text-xs text-gray-500 mt-1">Provide a direct URL to an image or video file. This will override uploaded files if both are provided.</p>
            </div>
            <div class="mb-4" id="currentMediaPreview" style="display: none;">
                <label class="block text-gray-700 text-sm font-semibold mb-2">Current Media:</label>
                <div class="flex items-center space-x-4">
                    <img id="currentImagePreview" src="" alt="Current Image" class="max-w-[150px] max-h-[100px] object-cover border border-gray-300 rounded-md" style="display: none;">
                    <video id="currentVideoPreview" src="" controls class="max-w-[150px] max-h-[100px] border border-gray-300 rounded-md" style="display: none;"></video>
                    <p id="currentMediaUrlText" class="text-sm text-gray-600 break-all"></p>
                </div>
            </div>

            <button type="submit" class="btn-primary mt-4">Save Gallery Item</button>
        </form>
    </div>
</div>

<!-- Infrastructure Highlight Modal -->
<div id="infraHighlightModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal('infraHighlightModal')">&times;</span>
        <h3 class="text-2xl font-bold mb-6 text-gray-800" id="infraHighlightModalTitle">Add New Highlight</h3>
        <form action="gallery_settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" id="infraHighlightAction">
            <input type="hidden" name="highlight_id" id="highlightId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="highlightTitle" class="block text-gray-700 text-sm font-semibold mb-2">Title:</label>
                    <input type="text" id="highlightTitle" name="highlight_title" class="form-input" required>
                </div>
                <div>
                    <label for="highlightCount" class="block text-gray-700 text-sm font-semibold mb-2">Count (e.g., "40+", "6"):</label>
                    <input type="text" id="highlightCount" name="highlight_count" class="form-input" required>
                </div>
            </div>
            <div class="mb-4 mt-4">
                <label for="highlightIcon" class="block text-gray-700 text-sm font-semibold mb-2">Icon (Full SVG code):</label>
                <textarea id="highlightIcon" name="highlight_icon" class="form-textarea" rows="3" placeholder="e.g., <svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;32&quot; height=&quot;32&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot;><path d=&quot;M10 3H6a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h4M14 3h4a2 2 0 0 1 2 2v14c0 1.1-.9 2-2 2h-4M8 21v-4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4&quot;/></svg>" required></textarea>
                <p class="text-xs text-gray-500 mt-1">Find SVG icons from <a href="https://lucide.dev/icons/" target="_blank" class="text-indigo-600 hover:underline">Lucide Icons</a> (copy SVG code). Max icon size is recommended to be 32x32 for highlights.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="highlightColorFrom" class="block text-gray-700 text-sm font-semibold mb-2">Gradient Start Color (Tailwind Class, e.g., 'blue-700'):</label>
                    <input type="text" id="highlightColorFrom" name="highlight_color_from" class="form-input" required>
                </div>
                <div>
                    <label for="highlightColorTo" class="block text-gray-700 text-sm font-semibold mb-2">Gradient End Color (Tailwind Class, e.g., 'indigo-800'):</label>
                    <input type="text" id="highlightColorTo" name="highlight_color_to" class="form-input" required>
                </div>
            </div>
            <button type="submit" class="btn-primary mt-6">Save Highlight</button>
        </form>
    </div>
</div>

</div> <!-- Close .container -->

<script>
    // General Modal Functions
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        // Reset forms within the modal if needed
        const form = document.getElementById(modalId).querySelector('form');
        if (form) {
            form.reset();
            // Clear any previous error messages or previews
            const mediaPreview = document.getElementById('currentMediaPreview');
            if(mediaPreview) mediaPreview.style.display = 'none';
            const currentImage = document.getElementById('currentImagePreview');
            if(currentImage) currentImage.style.display = 'none';
            const currentVideo = document.getElementById('currentVideoPreview');
            if(currentVideo) {
                currentVideo.style.display = 'none';
                currentVideo.pause();
                currentVideo.src = '';
            }
        }
        // Specific reset for category modal
        const newCategoryIdField = document.getElementById('newCategoryId');
        if (newCategoryIdField) {
            newCategoryIdField.removeAttribute('readonly');
            document.getElementById('categoryIdReadonlyMessage').classList.add('hidden');
        }
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
        }
    }

    // Category Modal Specific Functions
    function openCategoryModal(mode, id = '', name = '', icon = '') {
        const modal = document.getElementById('categoryModal');
        document.getElementById('categoryModalTitle').textContent = mode === 'add' ? 'Add New Category' : 'Edit Category';
        document.getElementById('categoryAction').value = mode === 'add' ? 'add_category' : 'edit_category';
        document.getElementById('originalCategoryId').value = id; // Store original ID for server-side validation if needed

        const newCategoryIdField = document.getElementById('newCategoryId');
        const categoryIdReadonlyMessage = document.getElementById('categoryIdReadonlyMessage');

        newCategoryIdField.value = id;
        document.getElementById('categoryName').value = name;
        document.getElementById('categoryIcon').value = icon;

        if (mode === 'edit' && id === 'all') {
            newCategoryIdField.setAttribute('readonly', 'readonly');
            newCategoryIdField.classList.add('bg-gray-100', 'cursor-not-allowed'); // Visual cue for readonly
            categoryIdReadonlyMessage.classList.remove('hidden');
        } else {
            newCategoryIdField.removeAttribute('readonly');
            newCategoryIdField.classList.remove('bg-gray-100', 'cursor-not-allowed');
            categoryIdReadonlyMessage.classList.add('hidden');
        }
        
        openModal('categoryModal');
    }

    // Gallery Item Modal Specific Functions
    function toggleMediaInput() {
        // This function simply toggles visibility, but the "required" attribute
        // will be managed by the server-side logic based on whether a file or URL is provided.
        // It helps guide the user in the UI.
        const fileInputGroup = document.getElementById('mediaFileInputGroup');
        const urlInputGroup = document.getElementById('mediaUrlInputGroup');
        
        if (document.getElementById('itemTypeImage').checked || document.getElementById('itemTypeVideo').checked) {
            fileInputGroup.style.display = 'block';
            urlInputGroup.style.display = 'block';
        } else {
            fileInputGroup.style.display = 'none';
            urlInputGroup.style.display = 'none';
        }
    }

    function openGalleryItemModal(mode, id = '', title = '', description = '', category = '', type = '', src_url = '', public_id = '') {
        const modal = document.getElementById('galleryItemModal');
        document.getElementById('galleryItemModalTitle').textContent = mode === 'add' ? 'Add New Gallery Item' : 'Edit Gallery Item';
        document.getElementById('galleryItemAction').value = mode === 'add' ? 'add_gallery_item' : 'edit_gallery_item';
        document.getElementById('galleryItemId').value = id;
        document.getElementById('galleryItemPublicId').value = public_id;

        document.getElementById('itemTitle').value = title;
        document.getElementById('itemDescription').value = description;
        document.getElementById('itemCategory').value = category;

        // Reset radio buttons first
        document.getElementById('itemTypeImage').checked = false;
        document.getElementById('itemTypeVideo').checked = false;

        if (type === 'image') {
            document.getElementById('itemTypeImage').checked = true;
        } else if (type === 'video') {
            document.getElementById('itemTypeVideo').checked = true;
        }
        toggleMediaInput(); // Adjust visibility based on type selection

        document.getElementById('itemSrcUrl').value = src_url;

        // Show current media preview for editing
        const currentMediaPreview = document.getElementById('currentMediaPreview');
        const currentImage = document.getElementById('currentImagePreview');
        const currentVideo = document.getElementById('currentVideoPreview');
        const currentMediaUrlText = document.getElementById('currentMediaUrlText');

        if (mode === 'edit' && src_url) {
            currentMediaPreview.style.display = 'block';
            currentMediaUrlText.textContent = src_url;

            if (type === 'image') {
                currentImage.src = src_url;
                currentImage.style.display = 'block';
                currentVideo.style.display = 'none';
                currentVideo.pause();
                currentVideo.src = ''; // Clear video source
            } else if (type === 'video') {
                currentVideo.src = src_url;
                currentVideo.style.display = 'block';
                currentImage.style.display = 'none';
                currentImage.src = ''; // Clear image source
                currentVideo.load(); // Load video metadata
            } else {
                 // Fallback if type is not clearly image/video but src_url exists
                currentImage.style.display = 'none';
                currentVideo.style.display = 'none';
            }
        } else {
            currentMediaPreview.style.display = 'none';
            currentImage.style.display = 'none';
            currentVideo.style.display = 'none';
            currentVideo.pause();
            currentVideo.src = '';
            currentImage.src = '';
            currentMediaUrlText.textContent = '';
        }
        document.getElementById('galleryMediaFile').value = ''; // Clear file input on open

        openModal('galleryItemModal');
    }

    // Infrastructure Highlight Modal Specific Functions
    function openInfraHighlightModal(mode, id = '', title = '', count = '', icon = '', color_from = '', color_to = '') {
        const modal = document.getElementById('infraHighlightModal');
        document.getElementById('infraHighlightModalTitle').textContent = mode === 'add' ? 'Add New Highlight' : 'Edit Highlight';
        document.getElementById('infraHighlightAction').value = mode === 'add' ? 'add_infra_highlight' : 'edit_infra_highlight';
        document.getElementById('highlightId').value = id;

        document.getElementById('highlightTitle').value = title;
        document.getElementById('highlightCount').value = count;
        document.getElementById('highlightIcon').value = icon;
        document.getElementById('highlightColorFrom').value = color_from;
        document.getElementById('highlightColorTo').value = color_to;

        openModal('infraHighlightModal');
    }

    // Initialize Lucide icons
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    });

</script>
</body>
</html>
<?php
mysqli_close($link);
require_once './admin_footer.php';
?>