<?php
// Start the session
session_start();

// Include configuration and Cloudinary handler
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["id"] ?? 0;
$errors = [];
$success_message = "";

// --- Helper function to update a single field in the DB ---
function update_setting($link, $column, $value, $admin_id) {
    $sql = "INSERT INTO gallery_settings (id, `$column`, updated_by_admin_id) VALUES (1, ?, ?)
            ON DUPLICATE KEY UPDATE `$column` = ?, updated_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP";
            
    if ($stmt = mysqli_prepare($link, $sql)) {
        // FIXED: The type string "sisi" now correctly matches the 4 placeholders.
        mysqli_stmt_bind_param($stmt, "sisi", $value, $admin_id, $value, $admin_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return true;
        }
    }
    // Log error for debugging, don't show to user
    error_log("Failed to update setting '$column': " . mysqli_error($link));
    return false;
}


// --- Process Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Handle Main Form Submission (Main content + New Upload) ---
    if (isset($_POST['update_gallery'])) {
        $sql_get = "SELECT gallery_items_json FROM gallery_settings WHERE id = 1";
        $result = mysqli_query($link, $sql_get);
        $row = mysqli_fetch_assoc($result);
        $gallery_items = $row ? json_decode($row['gallery_items_json'], true) : [];
        if (!is_array($gallery_items)) $gallery_items = [];

        if (isset($_FILES['new_gallery_item_file']) && $_FILES['new_gallery_item_file']['error'] == UPLOAD_ERR_OK) {
            $new_item_title = trim($_POST['new_item_title']);
            $new_item_category = trim($_POST['new_item_category']);

            if (empty($new_item_title) || empty($new_item_category)) {
                $errors[] = "Title and Category are required to upload a new item.";
            } else {
                $uploadResult = uploadToCloudinary($_FILES['new_gallery_item_file'], 'school_gallery');
                if (isset($uploadResult['error'])) {
                    $errors[] = "File upload failed: " . $uploadResult['error'];
                } else {
                    $gallery_items[] = [
                        'title' => $new_item_title,
                        'category' => $new_item_category,
                        'type' => strpos($_FILES['new_gallery_item_file']['type'], 'video') === 0 ? 'video' : 'image',
                        'description' => trim($_POST['new_item_description']),
                        'src_url' => $uploadResult['secure_url']
                    ];
                    $success_message = "New gallery item uploaded successfully!";
                }
            }
        }

        if (empty($errors)) {
            update_setting($link, 'gallery_section_title', trim($_POST['gallery_section_title']), $admin_id);
            update_setting($link, 'gallery_section_description', trim($_POST['gallery_section_description']), $admin_id);
            update_setting($link, 'view_more_button_text', trim($_POST['view_more_button_text']), $admin_id);
            update_setting($link, 'view_more_button_url', trim($_POST['view_more_button_url']), $admin_id);
            update_setting($link, 'gallery_items_json', json_encode(array_values($gallery_items), JSON_PRETTY_PRINT), $admin_id);

            $_SESSION['success_message'] = $success_message ?: "Main content updated successfully.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // --- Handle REMOVING a Gallery Item ---
    if (isset($_POST['remove_gallery_item'])) {
        $remove_index = (int)$_POST['remove_index'];
        $sql_get_items = "SELECT gallery_items_json FROM gallery_settings WHERE id = 1";
        $result = mysqli_query($link, $sql_get_items);
        $row = mysqli_fetch_assoc($result);
        $gallery_items = $row ? json_decode($row['gallery_items_json'], true) : [];

        if (is_array($gallery_items) && isset($gallery_items[$remove_index])) {
            array_splice($gallery_items, $remove_index, 1);
            update_setting($link, 'gallery_items_json', json_encode(array_values($gallery_items), JSON_PRETTY_PRINT), $admin_id);
            $_SESSION['success_message'] = "Gallery item removed successfully.";
        } else {
            $_SESSION['errors'] = ["Could not find the item to remove."];
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Check for messages from redirect session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}


// --- Fetch latest data for display ---
$settings = [];
$sql_fetch = "SELECT * FROM gallery_settings WHERE id = 1";
if ($result_fetch = mysqli_query($link, $sql_fetch)) {
    if (mysqli_num_rows($result_fetch) > 0) {
        $settings = mysqli_fetch_assoc($result_fetch);
    }
}
$categories_display = isset($settings['categories_json']) ? json_decode($settings['categories_json'], true) : [];
$gallery_items_display = isset($settings['gallery_items_json']) ? json_decode($settings['gallery_items_json'], true) : [];

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Gallery Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --bg-color: #f4f7f9;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --border-color: #e1e8ed;
            --shadow: 0 6px 15px rgba(0,0,0,0.07);
            --transition: all 0.3s ease;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-color); color: var(--text-dark); line-height: 1.6; }
        .container { max-width: 900px; margin: 2rem auto; background: var(--card-bg); border-radius: 12px; box-shadow: var(--shadow); padding: 2.5rem; }
        h2, h3 { font-family: 'Playfair Display', serif; text-align: center; color: var(--text-dark); }
        h2 { font-size: 2.25rem; margin-bottom: 2rem; }
        h3 { border-bottom: 2px solid var(--primary-color); padding-bottom: 0.75rem; margin-top: 2.5rem; margin-bottom: 2rem; font-size: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { font-weight: 600; display: block; margin-bottom: 0.5rem; }
        input, textarea, select { width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; transition: var(--transition); }
        input:focus, textarea:focus, select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2); }
        .btn { display: block; width: 100%; padding: 1rem; color: #fff; background-color: var(--primary-color); border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 600; text-align: center; margin-top: 1.5rem; transition: var(--transition); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(52, 152, 219, 0.4); }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; font-weight: 500; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        .alert-success { background-color: #d4edda; color: #155724; }
        hr.dashed { border: 0; border-top: 1px dashed #ccc; margin: 2.5rem 0; }
        .center-text { text-align: center; font-size: 0.9em; color: var(--text-light); margin-top: -1rem; margin-bottom: 2rem; }
        
        .categories-display-grid { display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: center; padding: 1rem 0; }
        .category-item-display { text-align: center; }
        .icon-circle { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem auto; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .icon-circle svg { width: 30px; height: 30px; }
        .category-item-display span { font-weight: 500; font-size: 0.9rem; color: #555; }

        .gallery-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin-top: 1.5rem; }
        .gallery-preview-item { border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: var(--shadow); background: var(--card-bg); transition: var(--transition); position: relative; }
        .gallery-preview-item:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .gallery-preview-item .media-wrapper { width: 100%; height: 120px; background-color: #eee; }
        .gallery-preview-item img, .gallery-preview-item video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .gallery-preview-item .info { padding: 0.75rem; }
        .gallery-preview-item h4 { font-family: 'Poppins', sans-serif; font-size: 0.95rem; font-weight: 600; margin: 0 0 0.25rem 0; text-align: left; border: none; padding: 0; }
        .gallery-preview-item p { font-size: 0.85rem; color: var(--text-light); margin: 0; }
        .gallery-preview-item .remove-btn-form { margin: 0; }
        .gallery-preview-item .remove-btn { width: 100%; border-radius: 0 0 6px 6px; margin-top: 0.75rem; padding: 0.6rem; font-size: 0.8rem; background-color: var(--danger-color); }
        .gallery-preview-item .remove-btn:hover { background-color: #c0392b; box-shadow: none; transform: none; }
    </style>
</head>
<body>
<div class="container">
    <h2>Manage Gallery Page</h2>
    <?php if(!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $error) echo htmlspecialchars($error)."<br>"; ?></div><?php endif; ?>
    <?php if($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
        
        <h3>Main Content & CTA Button</h3>
        <div class="form-group"><label for="gallery_section_title">Main Title</label><input type="text" id="gallery_section_title" name="gallery_section_title" value="<?php echo htmlspecialchars($settings['gallery_section_title'] ?? ''); ?>"></div>
        <div class="form-group"><label for="gallery_section_description">Main Description</label><textarea id="gallery_section_description" name="gallery_section_description" rows="3"><?php echo htmlspecialchars($settings['gallery_section_description'] ?? ''); ?></textarea></div>
        <div class="form-group"><label for="view_more_button_text">Button Text</label><input type="text" id="view_more_button_text" name="view_more_button_text" value="<?php echo htmlspecialchars($settings['view_more_button_text'] ?? ''); ?>"></div>
        <div class="form-group"><label for="view_more_button_url">Button URL</label><input type="text" id="view_more_button_url" name="view_more_button_url" value="<?php echo htmlspecialchars($settings['view_more_button_url'] ?? ''); ?>"></div>

        <hr class="dashed">

        <h3>Available Categories (Read-Only)</h3>
        <p class="center-text">Assign new uploads to one of these categories.</p>
        <div class="categories-display-grid">
            <?php if(empty($categories_display)): ?>
                <p>No categories found. They must be added to the database.</p>
            <?php else: ?>
                <?php foreach($categories_display as $category): ?>
                    <div class="category-item-display">
                        <div class="icon-circle"><?php echo $category['icon'] ?? ''; ?></div>
                        <span><?php echo htmlspecialchars($category['name']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <hr class="dashed">

        <h3>Upload New Gallery Item</h3>
        <div class="form-group"><label for="new_item_title">Item Title*</label><input type="text" id="new_item_title" name="new_item_title" placeholder="e.g., Annual Sports Day 2024"></div>
        <div class="form-group"><label for="new_item_description">Item Description</label><textarea id="new_item_description" name="new_item_description" rows="2" placeholder="A short description of the media."></textarea></div>
        <div class="form-group"><label for="new_item_category">Assign to Category*</label>
            <select id="new_item_category" name="new_item_category">
                <option value="">-- Select a Category --</option>
                <?php foreach($categories_display as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="new_gallery_item_file">Select Image or Video File*</label><input type="file" id="new_gallery_item_file" name="new_gallery_item_file" accept="image/*,video/*"></div>
        <p class="center-text" style="margin-top:-1rem; margin-bottom: 1.5rem;">Fields marked with * are required for new uploads.</p>

        <button type="submit" name="update_gallery" class="btn">Save All Changes & Upload New Item</button>
    </form>
    
    <hr class="dashed">

    <h3>Current Gallery Items</h3>
    <?php if(empty($gallery_items_display)): ?>
        <p class="center-text">No gallery items have been uploaded yet.</p>
    <?php else: ?>
        <div class="gallery-preview-grid">
            <?php foreach($gallery_items_display as $index => $item): ?>
                <div class="gallery-preview-item">
                    <div class="media-wrapper">
                        <?php if (($item['type'] ?? 'image') === 'video'): ?>
                            <video src="<?php echo htmlspecialchars($item['src_url']); ?>" muted playsinline></video>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($item['src_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                        <p>Category: <?php echo htmlspecialchars($item['category']); ?></p>
                    </div>
                    <form method="post" class="remove-btn-form" onsubmit="return confirm('Are you sure you want to permanently delete this item?');">
                        <input type="hidden" name="remove_index" value="<?php echo $index; ?>">
                        <button type="submit" name="remove_gallery_item" class="btn remove-btn">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>