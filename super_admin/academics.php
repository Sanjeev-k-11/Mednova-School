<?php
// Start the session
session_start();

// Include configuration and Cloudinary handler
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php"; // Kept for consistency, though not used here

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["super_admin_id"]; // Admin ID from session

// Initialize variables for form fields
$settings = [
    'main_title' => '', 'main_description' => '',
    'journey_title' => '', 'academic_levels_json' => '[]',
    'holistic_title' => '', 'holistic_description' => '', 'holistic_items_json' => '[]',
    'features_title' => '', 'academic_features_json' => '[]'
];

$errors = [];
$success_message = "";

// Initialize editable arrays for form display
$academic_levels_editable = [];
$holistic_items_editable = [];
$academic_features_editable = [];


// --- Fetch Current Settings ---
$sql_fetch = "SELECT * FROM academics_mednova_settings WHERE id = 1";
if ($result_fetch = mysqli_query($link, $sql_fetch)) {
    if (mysqli_num_rows($result_fetch) == 1) {
        $current_settings = mysqli_fetch_assoc($result_fetch);
        foreach ($settings as $key => $value) {
            if (isset($current_settings[$key]) && $current_settings[$key] !== NULL) {
                $settings[$key] = $current_settings[$key];
            }
        }
        
        $academic_levels_editable = json_decode($settings['academic_levels_json'], true) ?: [];
        $holistic_items_editable = json_decode($settings['holistic_items_json'], true) ?: [];
        $academic_features_editable = json_decode($settings['academic_features_json'], true) ?: [];

    } else {
        $success_message .= "Initial 'Academics' settings are not found. A new entry will be created on submission.";
    }
    mysqli_free_result($result_fetch);
} else {
    $errors[] = "Error fetching current 'Academics' settings: " . mysqli_error($link);
}

// --- Process Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors)) {

    // Sanitize and validate main text fields
    $settings['main_title'] = trim($_POST['main_title'] ?? '');
    $settings['main_description'] = trim($_POST['main_description'] ?? '');
    $settings['journey_title'] = trim($_POST['journey_title'] ?? '');
    $settings['holistic_title'] = trim($_POST['holistic_title'] ?? '');
    $settings['holistic_description'] = trim($_POST['holistic_description'] ?? '');
    $settings['features_title'] = trim($_POST['features_title'] ?? '');


    // --- Process JSON fields ---
    function process_json_items($post_key, $fields, &$errors) {
        $items = [];
        if (isset($_POST[$post_key]) && is_array($_POST[$post_key])) {
            foreach ($_POST[$post_key] as $item_data) {
                $item = [];
                $is_valid = true;
                foreach ($fields as $field) {
                    $value = trim($item_data[$field] ?? '');
                    if (empty($value)) { // Basic validation
                        $is_valid = false;
                        break;
                    }
                    $item[$field] = $value;
                }
                if ($is_valid) {
                    $items[] = $item;
                }
            }
        }
        $json_data = json_encode($items);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "Error encoding {$post_key} data: " . json_last_error_msg();
        }
        return $json_data;
    }

    $settings['academic_levels_json'] = process_json_items('academic_levels', ['title', 'subtitle', 'description', 'subjects', 'icon'], $errors);
    $settings['holistic_items_json'] = process_json_items('holistic_items', ['title', 'description', 'icon'], $errors);
    $settings['academic_features_json'] = process_json_items('academic_features', ['title', 'description', 'icon'], $errors);
    
    // Only proceed to update if no errors occurred
    if (empty($errors)) {
        
        $sql_upsert = "INSERT INTO `academics_mednova_settings` (
            `id`, `main_title`, `main_description`, `journey_title`, `academic_levels_json`,
            `holistic_title`, `holistic_description`, `holistic_items_json`,
            `features_title`, `academic_features_json`, `updated_by_admin_id`
        ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `main_title` = VALUES(`main_title`), `main_description` = VALUES(`main_description`),
            `journey_title` = VALUES(`journey_title`), `academic_levels_json` = VALUES(`academic_levels_json`),
            `holistic_title` = VALUES(`holistic_title`), `holistic_description` = VALUES(`holistic_description`), `holistic_items_json` = VALUES(`holistic_items_json`),
            `features_title` = VALUES(`features_title`), `academic_features_json` = VALUES(`academic_features_json`),
            `updated_by_admin_id` = VALUES(`updated_by_admin_id`), `updated_at` = CURRENT_TIMESTAMP";

        if ($stmt = mysqli_prepare($link, $sql_upsert)) {
            $types = "sssssssssi"; // 9 strings, 1 int
            $params = [
                $settings['main_title'], $settings['main_description'],
                $settings['journey_title'], $settings['academic_levels_json'],
                $settings['holistic_title'], $settings['holistic_description'], $settings['holistic_items_json'],
                $settings['features_title'], $settings['academic_features_json'],
                $admin_id
            ];

            $bind_params = [];
            $bind_params[] = $types;
            foreach ($params as $key => $val) {
                $bind_params[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_params);

            if (mysqli_stmt_execute($stmt)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $errors[] = "Error updating settings: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Database preparation error: " . mysqli_error($link);
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Settings updated successfully.";
}

// Include admin header for consistent layout
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Academics Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5a7d9b;
            --secondary-color: #e7a33c;
            --accent-color: #2c3e50;
            --background-start: #eef2f9;
            --background-end: #dce5f1;
            --text-color: #333;
            --light-bg: #fff;
            --border-color: #e0e6f0;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
            --transition-speed: 0.3s;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--background-start), var(--background-end));
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
            padding-top: 80px;
        }
        .container {
            max-width: 950px;
            margin: 40px auto;
            background: var(--light-bg);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 40px;
            border: 1px solid var(--border-color);
        }
        h2, h3 {
            font-family: 'Playfair Display', serif;
            text-align: center;
            color: var(--accent-color);
            font-weight: 700;
            margin-bottom: 30px;
            letter-spacing: 1px;
        }
        h3 {
            font-size: 1.5em;
            margin-top: 40px;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }
        h3::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--secondary-color);
            border-radius: 2px;
        }
        .form-group { margin-bottom: 25px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--accent-color);
            font-size: 0.9em;
        }
        .form-group input:not([type="file"]), .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            box-sizing: border-box;
            background-color: #f7f9fc;
            font-size: 1em;
            color: var(--text-color);
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(90, 125, 155, 0.1);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .full-width { grid-column: 1 / -1; }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 16px;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: var(--primary-color);
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .btn-small {
            padding: 8px 16px;
            font-size: 0.85em;
            width: auto;
        }
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .alert { 
            padding: 18px; 
            margin-bottom: 25px; 
            border-radius: 12px; 
            font-weight: 500;
            text-align: center;
        }
        .alert-danger { 
            color: #721c24; 
            background-color: #f8d7da; 
            border: 1px solid #f5c6cb; 
        }
        .alert-success { 
            color: #155724; 
            background-color: #d4edda; 
            border: 1px solid #c3e6cb; 
        }

        .dynamic-item {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #fcfcfc;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: relative;
        }
        .remove-item-btn {
            background-color: var(--text-color);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 8px;
            cursor: pointer;
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.8em;
            opacity: 0.7;
            transition: opacity var(--transition-speed);
        }
        .remove-item-btn:hover {
            opacity: 1;
        }
        .add-new-button-container {
            text-align: center;
            margin: 30px 0;
        }
        
        .section-toggle-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            transition: border-color var(--transition-speed);
        }
        .section-toggle-header h3 {
            margin: 0;
            padding: 0;
            border-bottom: none;
            text-align: left;
            font-size: 1.8rem;
            color: var(--accent-color);
        }
        .section-toggle-header h3::after { display: none; }
        .section-toggle-header .toggle-icon { transition: transform var(--transition-speed); }
        .section-toggle-header[aria-expanded="true"] .toggle-icon { transform: rotate(180deg); }
        .section-content-wrapper {
            max-height: 4000px; /* Increased max-height for large sections */
            overflow: hidden;
            transition: max-height 0.5s ease-out, opacity 0.5s ease-out;
            opacity: 1;
        }
        .section-content-wrapper.collapsed {
            max-height: 0;
            opacity: 0;
            padding-top: 0;
            padding-bottom: 0;
            margin-top: 0;
            margin-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .container { padding: 25px; margin: 20px auto; }
            .form-grid { grid-template-columns: 1fr; }
            h2 { font-size: 2.25em; }
            h3 { font-size: 1.5em; }
            .section-toggle-header h3 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Manage "Academics" Page</h2>

    <?php 
    if (!empty($errors)) {
        echo '<div class="alert alert-danger full-width"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
    }
    if (!empty($success_message)) {
        echo '<div class="alert alert-success full-width">' . htmlspecialchars($success_message) . '</div>';
    }
    ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
        
        <!-- Main Section Content -->
        <div class="section-toggle-header" id="main-content-toggle-header" aria-expanded="true" aria-controls="main-content-section">
            <h3>Main Section</h3>
            <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <div id="main-content-section" class="section-content-wrapper">
            <div class="form-group full-width"><label for="main_title">Main Title</label><input type="text" name="main_title" id="main_title" value="<?php echo htmlspecialchars($settings['main_title']); ?>"></div>
            <div class="form-group full-width"><label for="main_description">Main Description</label><textarea name="main_description" id="main_description" rows="3"><?php echo htmlspecialchars($settings['main_description']); ?></textarea></div>
        </div>

        <hr style="margin: 40px 0; border: none; border-top: 1px dashed var(--border-color);">

        <!-- Dynamic JSON Sections -->
        <?php 
            function render_dynamic_section($title, $slug, $main_title, $main_description, $data, $fields, $main_title_field, $main_desc_field) {
                echo '<div class="section-toggle-header" id="'.$slug.'-toggle-header" aria-expanded="false" aria-controls="'.$slug.'-section-content">';
                echo '<h3>'.$title.'</h3>';
                echo '<svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>';
                echo '</div>';
                echo '<div id="'.$slug.'-section-content" class="section-content-wrapper collapsed">';
                echo '<div class="form-group full-width"><label for="'.$main_title_field.'">Section Title</label><input type="text" name="'.$main_title_field.'" id="'.$main_title_field.'" value="'.htmlspecialchars($main_title).'"></div>';
                if ($main_description !== null) { // Only render description field if not null
                    echo '<div class="form-group full-width"><label for="'.$main_desc_field.'">Section Description</label><textarea name="'.$main_desc_field.'" id="'.$main_desc_field.'" rows="3">'.htmlspecialchars($main_description).'</textarea></div>';
                }
                
                echo '<div class="form-grid" id="'.$slug.'-container">';
                foreach ($data as $index => $item_data) {
                    echo '<div class="dynamic-item full-width" data-index="'.$index.'">';
                    echo '<button type="button" class="remove-item-btn" data-type="'.$slug.'">Remove</button>';
                    echo '<h4>Item '.($index + 1).'</h4>';
                    foreach ($fields as $field_key => $field_info) {
                        $name = $slug.'['.$index.']['.$field_key.']';
                        $value = htmlspecialchars($item_data[$field_key] ?? '');
                        echo '<div class="form-group">';
                        echo '<label>'.$field_info['label'].'</label>';
                        if ($field_info['type'] === 'textarea') {
                            echo '<textarea name="'.$name.'" rows="3">'.$value.'</textarea>';
                        } else {
                            echo '<input type="text" name="'.$name.'" value="'.$value.'">';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                echo '<div class="add-new-button-container full-width">';
                echo '<button type="button" class="btn btn-small btn-outline add-new-btn" data-type="'.$slug.'">Add New Item</button>';
                echo '</div>';
                echo '</div>';
                echo '<hr style="margin: 40px 0; border: none; border-top: 1px dashed var(--border-color);">';
            }

            render_dynamic_section('Academic Journey', 'academic_levels', $settings['journey_title'], null, $academic_levels_editable, [
                'title' => ['label' => 'Title', 'type' => 'text'],
                'subtitle' => ['label' => 'Subtitle (e.g., Ages 3-5)', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'subjects' => ['label' => 'Subjects (comma-separated)', 'type' => 'text'],
                'icon' => ['label' => 'Icon (SVG Code)', 'type' => 'textarea']
            ], 'journey_title', '');

            render_dynamic_section('Holistic Learning Approach', 'holistic_items', $settings['holistic_title'], $settings['holistic_description'], $holistic_items_editable, [
                'title' => ['label' => 'Title', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'icon' => ['label' => 'Icon (SVG Code)', 'type' => 'textarea']
            ], 'holistic_title', 'holistic_description');
            
            render_dynamic_section('Key Academic Features', 'academic_features', $settings['features_title'], '', $academic_features_editable, [
                'title' => ['label' => 'Title', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'icon' => ['label' => 'Icon (SVG Code)', 'type' => 'textarea']
            ], 'features_title', '');
        ?>

        <div class="full-width" style="margin-top: 50px;">
            <button type="submit" class="btn">Save Academics Settings</button>
        </div>
    </form>
</div>

<!-- TEMPLATES FOR JAVASCRIPT CLONING -->
<template id="template-academic_levels">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="academic_levels">Remove</button>
        <h4>Item NEW_INDEX_PLUS_1</h4>
        <div class="form-group"><label>Title</label><input type="text" name="academic_levels[NEW_INDEX][title]"></div>
        <div class="form-group"><label>Subtitle (e.g., Ages 3-5)</label><input type="text" name="academic_levels[NEW_INDEX][subtitle]"></div>
        <div class="form-group"><label>Description</label><textarea name="academic_levels[NEW_INDEX][description]" rows="3"></textarea></div>
        <div class="form-group"><label>Subjects (comma-separated)</label><input type="text" name="academic_levels[NEW_INDEX][subjects]"></div>
        <div class="form-group"><label>Icon (SVG Code)</label><textarea name="academic_levels[NEW_INDEX][icon]" rows="3"></textarea></div>
    </div>
</template>

<template id="template-holistic_items">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="holistic_items">Remove</button>
        <h4>Item NEW_INDEX_PLUS_1</h4>
        <div class="form-group"><label>Title</label><input type="text" name="holistic_items[NEW_INDEX][title]"></div>
        <div class="form-group"><label>Description</label><textarea name="holistic_items[NEW_INDEX][description]" rows="3"></textarea></div>
        <div class="form-group"><label>Icon (SVG Code)</label><textarea name="holistic_items[NEW_INDEX][icon]" rows="3"></textarea></div>
    </div>
</template>

<template id="template-academic_features">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="academic_features">Remove</button>
        <h4>Item NEW_INDEX_PLUS_1</h4>
        <div class="form-group"><label>Title</label><input type="text" name="academic_features[NEW_INDEX][title]"></div>
        <div class="form-group"><label>Description</label><textarea name="academic_features[NEW_INDEX][description]" rows="3"></textarea></div>
        <div class="form-group"><label>Icon (SVG Code)</label><textarea name="academic_features[NEW_INDEX][icon]" rows="3"></textarea></div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Section Toggle Logic ---
        function setupSectionToggle(headerId, contentId, initialExpanded = false) {
            const header = document.getElementById(headerId);
            const content = document.getElementById(contentId);
            if (!header || !content) return;

            header.setAttribute('aria-expanded', initialExpanded ? 'true' : 'false');
            content.classList.toggle('collapsed', !initialExpanded);

            header.addEventListener('click', () => {
                const isExpanded = header.getAttribute('aria-expanded') === 'true';
                header.setAttribute('aria-expanded', !isExpanded);
                content.classList.toggle('collapsed', isExpanded);
            });
        }

        // Apply toggles to all sections
        setupSectionToggle('main-content-toggle-header', 'main-content-section', true);
        setupSectionToggle('academic_levels-toggle-header', 'academic_levels-section-content', false);
        setupSectionToggle('holistic_items-toggle-header', 'holistic_items-section-content', false);
        setupSectionToggle('academic_features-toggle-header', 'academic_features-section-content', false);
        
        // --- Dynamic Item Management ---
        function reindexItems(containerId, baseName) {
            const container = document.getElementById(containerId);
            const items = Array.from(container.children);
            items.forEach((item, index) => {
                item.dataset.index = index;
                const h4 = item.querySelector('h4');
                if (h4) h4.textContent = `Item ${index + 1}`;
                
                item.querySelectorAll('[name^="' + baseName + '"]').forEach(input => {
                    const oldName = input.getAttribute('name');
                    if (oldName) {
                        const newName = oldName.replace(/\[\d+\]/, `[${index}]`);
                        input.setAttribute('name', newName);
                    }
                });
            });
        }

        document.querySelectorAll('.add-new-btn').forEach(button => {
            button.addEventListener('click', function() {
                const type = this.dataset.type;
                const template = document.getElementById(`template-${type}`);
                if (!template) return;
                const container = document.getElementById(`${type}-container`);
                const newIndex = container.children.length;
                
                let itemHtml = template.innerHTML.replace(/NEW_INDEX/g, newIndex);
                itemHtml = itemHtml.replace(/NEW_INDEX_PLUS_1/g, newIndex + 1);
                
                container.insertAdjacentHTML('beforeend', itemHtml);
            });
        });

        document.addEventListener('click', function(event) {
            if (event.target && event.target.classList.contains('remove-item-btn')) {
                const itemDiv = event.target.closest('.dynamic-item');
                if (confirm('Are you sure you want to remove this item?')) {
                    const type = event.target.dataset.type;
                    const containerId = `${type}-container`;
                    itemDiv.remove();
                    reindexItems(containerId, type);
                }
            }
        });
    });
</script>

</body>
</html>
<?php 
if($link) mysqli_close($link);
require_once './admin_footer.php';
?>