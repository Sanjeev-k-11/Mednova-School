<?php
// Start the session
session_start();

// Include configuration and Cloudinary handler
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php"; // Make sure this path is correct

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["super_admin_id"]; // Admin ID from session

// Initialize variables for form fields based on about_mednova_settings table
$settings = [
  'hero_title' => '', 'hero_subtitle_1' => '', 'hero_subtitle_2' => '', 'hero_image_url' => '',
  'story_title' => '', 'story_description' => '', 'legacy_image_url' => '', 'legacy_title' => '', 'legacy_description' => '', 'vision_title' => '', 'vision_description' => '', 'mission_title' => '', 'mission_description' => '',
  'core_pillars_title' => '', 'core_pillars_description' => '', 'core_pillars_json' => '[]',
  'why_choose_us_title' => '', 'why_choose_us_description' => '', 'why_choose_us_json' => '[]',
  'curriculum_journey_title' => '', 'curriculum_journey_description' => '', 'curriculum_stages_json' => '[]',
  'infra_title' => '', 'infra_description' => '', 'infra_json' => '[]',
  'beyond_academics_title' => '', 'beyond_academics_description' => '', 'beyond_academics_json' => '[]',
  'core_values_title' => '', 'core_values_description' => '', 'core_values_json' => '[]',
  'faculty_title' => '', 'faculty_description' => '', 'faculty_image_url' => '', 'faculty_sub_title' => '', 'faculty_sub_description' => '', 'faculty_highlights_json' => '[]',
  'principal_image_url' => '', 'principal_message_title' => '', 'principal_quote' => '', 'principal_name' => '', 'principal_role' => '', 'principal_qualifications' => ''
];

$errors = [];
$success_message = "";

// Initialize editable arrays for form display
$core_pillars_editable = [];
$why_choose_us_editable = [];
$curriculum_stages_editable = [];
$infra_editable = [];
$beyond_academics_editable = [];
$core_values_editable = [];
$faculty_highlights_editable = [];


// --- Fetch Current Settings ---
$sql_fetch = "SELECT * FROM about_mednova_settings WHERE id = 1";
if ($result_fetch = mysqli_query($link, $sql_fetch)) {
    if (mysqli_num_rows($result_fetch) == 1) {
        $current_settings = mysqli_fetch_assoc($result_fetch);
        foreach ($settings as $key => $value) {
            if (isset($current_settings[$key]) && $current_settings[$key] !== NULL) {
                $settings[$key] = $current_settings[$key];
            }
        }
        
        // Handle all JSON fields for form display
        $core_pillars_editable = json_decode($settings['core_pillars_json'], true) ?: [];
        $why_choose_us_editable = json_decode($settings['why_choose_us_json'], true) ?: [];
        $curriculum_stages_editable = json_decode($settings['curriculum_stages_json'], true) ?: [];
        $infra_editable = json_decode($settings['infra_json'], true) ?: [];
        $beyond_academics_editable = json_decode($settings['beyond_academics_json'], true) ?: [];
        $core_values_editable = json_decode($settings['core_values_json'], true) ?: [];
        $faculty_highlights_editable = json_decode($settings['faculty_highlights_json'], true) ?: [];

    } else {
        $success_message .= "Initial 'About Mednova' settings are not found. A new entry will be created on submission.";
    }
    mysqli_free_result($result_fetch);
} else {
    $errors[] = "Error fetching current 'About Mednova' settings: " . mysqli_error($link);
}

// --- Process Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors)) {

    // Sanitize and validate all text fields
    foreach ($settings as $key => $value) {
        if (strpos($key, '_json') === false) {
            $settings[$key] = trim($_POST[$key] ?? '');
        }
    }

    // Handle Image Uploads
    $image_fields_to_check = [
        'hero_image_file' => 'hero_image_url',
        'legacy_image_file' => 'legacy_image_url',
        'faculty_image_file' => 'faculty_image_url',
        'principal_image_file' => 'principal_image_url'
    ];
    foreach ($image_fields_to_check as $file_input_name => $db_url_field) {
        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
            $uploadResult = uploadToCloudinary($_FILES[$file_input_name], 'about_mednova_images');
            if (isset($uploadResult['error'])) {
                $errors[] = "Image Upload Failed for {$file_input_name}: " . $uploadResult['error'];
            } else {
                $settings[$db_url_field] = $uploadResult['secure_url'];
            }
        }
    }

    // --- Process all JSON fields ---
    function process_json_items($post_key, $fields, &$errors) {
        $items = [];
        if (isset($_POST[$post_key]) && is_array($_POST[$post_key])) {
            foreach ($_POST[$post_key] as $item_data) {
                $item = [];
                $is_valid = true;
                foreach ($fields as $field) {
                    $value = trim($item_data[$field] ?? '');
                    if (empty($value)) { // Basic validation: all fields in a dynamic item are required
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
    
    $settings['core_pillars_json'] = process_json_items('core_pillars', ['title', 'description', 'icon'], $errors);
    $settings['why_choose_us_json'] = process_json_items('why_choose_us', ['title', 'description', 'icon'], $errors);
    $settings['curriculum_stages_json'] = process_json_items('curriculum_stages', ['stage', 'description', 'icon'], $errors);
    $settings['infra_json'] = process_json_items('infra', ['title', 'description', 'icon'], $errors);
    $settings['beyond_academics_json'] = process_json_items('beyond_academics', ['title', 'description', 'icon'], $errors);
    $settings['core_values_json'] = process_json_items('core_values', ['value', 'description', 'icon'], $errors);
    
    $faculty_highlights_items = [];
    if (isset($_POST['faculty_highlights']) && is_array($_POST['faculty_highlights'])) {
        foreach ($_POST['faculty_highlights'] as $highlight_text) {
            $text = trim($highlight_text);
            if (!empty($text)) {
                $faculty_highlights_items[] = $text;
            }
        }
    }
    $settings['faculty_highlights_json'] = json_encode($faculty_highlights_items);


    // Only proceed to update if no errors occurred
    if (empty($errors)) {
        
        $columns = array_keys($settings);
        $placeholders = array_fill(0, count($columns), '?');
        // CORRECTED: Use VALUES(column) syntax for the UPDATE part
        $update_parts = array_map(fn($col) => "`$col` = VALUES(`$col`)", $columns);

        $sql_upsert = "INSERT INTO `about_mednova_settings` (`id`, " . implode(', ', array_map(fn($col) => "`$col`", $columns)) . ", `updated_by_admin_id`) 
                       VALUES (1, " . implode(', ', $placeholders) . ", ?) 
                       ON DUPLICATE KEY UPDATE " . implode(', ', $update_parts) . ", `updated_by_admin_id` = VALUES(`updated_by_admin_id`), `updated_at` = CURRENT_TIMESTAMP";

        if ($stmt = mysqli_prepare($link, $sql_upsert)) {
            // Build the parameters for binding. Only need them for the INSERT part.
            $types = str_repeat('s', count($columns)) . 'i'; // All settings as strings, plus one integer for admin_id
            
            $params = array_values($settings);
            $params[] = $admin_id; // For INSERT part only
            
            // Create an array of references for mysqli_stmt_bind_param
            $bind_params = [];
            $bind_params[] = $types;
            foreach ($params as $key => $val) {
                $bind_params[] = &$params[$key];
            }

            // Use call_user_func_array for robust binding
            call_user_func_array([$stmt, 'bind_param'], $bind_params);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "About Mednova settings updated successfully. Reloading page to reflect changes...";
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
    <title>Manage About Mednova Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--background-start), var(--background-end));
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
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
            max-height: 2000px;
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
    <h2>Manage "About Mednova" Page</h2>

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
        
        <!-- Hero Section -->
        <div class="section-toggle-header" id="hero-toggle-header" aria-expanded="true" aria-controls="hero-section-content">
            <h3>Hero Section</h3>
            <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <div id="hero-section-content" class="section-content-wrapper">
            <div class="form-group"><label for="hero_title">Title</label><input type="text" name="hero_title" id="hero_title" value="<?php echo htmlspecialchars($settings['hero_title']); ?>"></div>
            <div class="form-group"><label for="hero_subtitle_1">Subtitle 1</label><input type="text" name="hero_subtitle_1" id="hero_subtitle_1" value="<?php echo htmlspecialchars($settings['hero_subtitle_1']); ?>"></div>
            <div class="form-group"><label for="hero_subtitle_2">Subtitle 2</label><input type="text" name="hero_subtitle_2" id="hero_subtitle_2" value="<?php echo htmlspecialchars($settings['hero_subtitle_2']); ?>"></div>
            <div class="form-group"><label for="hero_image_file">Background Image</label><input type="file" name="hero_image_file" id="hero_image_file" accept="image/*"></div>
        </div>

        <!-- Story Section -->
        <div class="section-toggle-header" id="story-toggle-header" aria-expanded="false" aria-controls="story-section-content">
            <h3>Story & Philosophy Section</h3>
            <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <div id="story-section-content" class="section-content-wrapper collapsed">
            <div class="form-group"><label for="story_title">Title</label><input type="text" name="story_title" id="story_title" value="<?php echo htmlspecialchars($settings['story_title']); ?>"></div>
            <div class="form-group"><label for="story_description">Description</label><textarea name="story_description" id="story_description" rows="3"><?php echo htmlspecialchars($settings['story_description']); ?></textarea></div>
            <div class="form-group"><label for="legacy_image_file">Legacy Image</label><input type="file" name="legacy_image_file" id="legacy_image_file" accept="image/*"></div>
            <div class="form-group"><label for="legacy_title">Legacy Title</label><input type="text" name="legacy_title" id="legacy_title" value="<?php echo htmlspecialchars($settings['legacy_title']); ?>"></div>
            <div class="form-group"><label for="legacy_description">Legacy Description</label><textarea name="legacy_description" id="legacy_description" rows="4"><?php echo htmlspecialchars($settings['legacy_description']); ?></textarea></div>
            <div class="form-group"><label for="vision_title">Vision Title</label><input type="text" name="vision_title" id="vision_title" value="<?php echo htmlspecialchars($settings['vision_title']); ?>"></div>
            <div class="form-group"><label for="vision_description">Vision Description</label><textarea name="vision_description" id="vision_description" rows="3"><?php echo htmlspecialchars($settings['vision_description']); ?></textarea></div>
            <div class="form-group"><label for="mission_title">Mission Title</label><input type="text" name="mission_title" id="mission_title" value="<?php echo htmlspecialchars($settings['mission_title']); ?>"></div>
            <div class="form-group"><label for="mission_description">Mission Description</label><textarea name="mission_description" id="mission_description" rows="3"><?php echo htmlspecialchars($settings['mission_description']); ?></textarea></div>
        </div>

        <!-- Dynamic JSON Sections -->
        <?php 
            function render_dynamic_section($title, $slug, $main_title, $main_description, $data, $fields, $main_title_field, $main_desc_field) {
                echo '<div class="section-toggle-header" id="'.$slug.'-toggle-header" aria-expanded="false" aria-controls="'.$slug.'-section-content">';
                echo '<h3>'.$title.'</h3>';
                echo '<svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
                echo '</div>';
                echo '<div id="'.$slug.'-section-content" class="section-content-wrapper collapsed">';
                echo '<div class="form-group full-width"><label for="'.$main_title_field.'">Section Title</label><input type="text" name="'.$main_title_field.'" id="'.$main_title_field.'" value="'.htmlspecialchars($main_title).'"></div>';
                echo '<div class="form-group full-width"><label for="'.$main_desc_field.'">Section Description</label><textarea name="'.$main_desc_field.'" id="'.$main_desc_field.'" rows="3">'.htmlspecialchars($main_description).'</textarea></div>';
                
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
            }

            render_dynamic_section('Core Pillars', 'core_pillars', $settings['core_pillars_title'], $settings['core_pillars_description'], $core_pillars_editable, [
                'title' => ['label' => 'Title', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'icon' => ['label' => 'Icon (SVG Code)', 'type' => 'textarea']
            ], 'core_pillars_title', 'core_pillars_description');
            
            render_dynamic_section('Why Choose Us', 'why_choose_us', $settings['why_choose_us_title'], $settings['why_choose_us_description'], $why_choose_us_editable, [
                'title' => ['label' => 'Title', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'icon' => ['label' => 'Icon (SVG Code)', 'type' => 'textarea']
            ], 'why_choose_us_title', 'why_choose_us_description');
            
            render_dynamic_section('Curriculum Journey', 'curriculum_stages', $settings['curriculum_journey_title'], $settings['curriculum_journey_description'], $curriculum_stages_editable, [
                'stage' => ['label' => 'Stage (e.g., Early Years)', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'icon' => ['label' => 'Icon (SVG Code)', 'type' => 'textarea']
            ], 'curriculum_journey_title', 'curriculum_journey_description');

            render_dynamic_section('Infrastructure & Facilities', 'infra', $settings['infra_title'], $settings['infra_description'], $infra_editable, [
                'title' => ['label' => 'Title', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'icon' => ['label' => 'Icon (SVG Code)', 'type' => 'textarea']
            ], 'infra_title', 'infra_description');
            
            render_dynamic_section('Beyond Academics', 'beyond_academics', $settings['beyond_academics_title'], $settings['beyond_academics_description'], $beyond_academics_editable, [
                'title' => ['label' => 'Title', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'icon' => ['label' => 'Icon (SVG Code)', 'type' => 'textarea']
            ], 'beyond_academics_title', 'beyond_academics_description');

            render_dynamic_section('Core Values', 'core_values', $settings['core_values_title'], $settings['core_values_description'], $core_values_editable, [
                'value' => ['label' => 'Value (e.g., Integrity)', 'type' => 'text'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'icon' => ['label' => 'Icon (SVG Code)', 'type' => 'textarea']
            ], 'core_values_title', 'core_values_description');
        ?>
        
        <!-- Faculty Section -->
        <div class="section-toggle-header" id="faculty-toggle-header" aria-expanded="false" aria-controls="faculty-section-content">
            <h3>Faculty Section</h3>
            <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <div id="faculty-section-content" class="section-content-wrapper collapsed">
            <div class="form-group"><label for="faculty_title">Main Title</label><input type="text" name="faculty_title" id="faculty_title" value="<?php echo htmlspecialchars($settings['faculty_title']); ?>"></div>
            <div class="form-group"><label for="faculty_description">Main Description</label><textarea name="faculty_description" id="faculty_description" rows="3"><?php echo htmlspecialchars($settings['faculty_description']); ?></textarea></div>
            <div class="form-group"><label for="faculty_image_file">Faculty Image</label><input type="file" name="faculty_image_file" id="faculty_image_file" accept="image/*"></div>
            <div class="form-group"><label for="faculty_sub_title">Sub-title</label><input type="text" name="faculty_sub_title" id="faculty_sub_title" value="<?php echo htmlspecialchars($settings['faculty_sub_title']); ?>"></div>
            <div class="form-group"><label for="faculty_sub_description">Sub-description</label><textarea name="faculty_sub_description" id="faculty_sub_description" rows="4"><?php echo htmlspecialchars($settings['faculty_sub_description']); ?></textarea></div>
            
            <h4 style="margin-top: 20px; font-weight: 600;">Faculty Highlights (Bulleted List)</h4>
            <div id="faculty-highlights-container">
                <?php foreach ($faculty_highlights_editable as $index => $highlight): ?>
                    <div class="dynamic-item full-width" data-index="<?php echo $index; ?>">
                        <button type="button" class="remove-item-btn" data-type="faculty_highlights">Remove</button>
                        <div class="form-group">
                            <label>Highlight Text <?php echo $index + 1; ?></label>
                            <input type="text" name="faculty_highlights[]" value="<?php echo htmlspecialchars($highlight); ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="add-new-button-container full-width">
                <button type="button" class="btn btn-small btn-outline add-new-btn" data-type="faculty_highlights">Add New Highlight</button>
            </div>
        </div>

        <!-- Principal's Message Section -->
        <div class="section-toggle-header" id="principal-toggle-header" aria-expanded="false" aria-controls="principal-section-content">
            <h3>Principal's Message</h3>
            <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <div id="principal-section-content" class="section-content-wrapper collapsed">
            <div class="form-group"><label for="principal_message_title">Title</label><input type="text" name="principal_message_title" id="principal_message_title" value="<?php echo htmlspecialchars($settings['principal_message_title']); ?>"></div>
            <div class="form-group"><label for="principal_quote">Quote</label><textarea name="principal_quote" id="principal_quote" rows="5"><?php echo htmlspecialchars($settings['principal_quote']); ?></textarea></div>
            <div class="form-group"><label for="principal_name">Name</label><input type="text" name="principal_name" id="principal_name" value="<?php echo htmlspecialchars($settings['principal_name']); ?>"></div>
            <div class="form-group"><label for="principal_role">Role</label><input type="text" name="principal_role" id="principal_role" value="<?php echo htmlspecialchars($settings['principal_role']); ?>"></div>
            <div class="form-group"><label for="principal_qualifications">Qualifications</label><input type="text" name="principal_qualifications" id="principal_qualifications" value="<?php echo htmlspecialchars($settings['principal_qualifications']); ?>"></div>
            <div class="form-group"><label for="principal_image_file">Image</label><input type="file" name="principal_image_file" id="principal_image_file" accept="image/*"></div>
        </div>

        <div class="full-width" style="margin-top: 50px;">
            <button type="submit" class="btn">Save All "About Mednova" Settings</button>
        </div>

        <!-- Hidden input fields to hold the dynamic data for submission -->
        <div id="hidden-dynamic-inputs" style="display: none;"></div>
    </form>
</div>

<!-- TEMPLATES FOR JAVASCRIPT CLONING (for dynamic sections) -->
<template id="dynamic-item-template-core_pillars">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="core_pillars">Remove</button>
        <h4>Item NEW_INDEX_PLUS_1</h4>
        <div class="form-group"><label>Title</label><input type="text" name="core_pillars[NEW_INDEX][title]"></div>
        <div class="form-group"><label>Description</label><textarea name="core_pillars[NEW_INDEX][description]" rows="3"></textarea></div>
        <div class="form-group"><label>Icon (SVG Code)</label><textarea name="core_pillars[NEW_INDEX][icon]" rows="3"></textarea></div>
    </div>
</template>

<template id="dynamic-item-template-why_choose_us">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="why_choose_us">Remove</button>
        <h4>Item NEW_INDEX_PLUS_1</h4>
        <div class="form-group"><label>Title</label><input type="text" name="why_choose_us[NEW_INDEX][title]"></div>
        <div class="form-group"><label>Description</label><textarea name="why_choose_us[NEW_INDEX][description]" rows="3"></textarea></div>
        <div class="form-group"><label>Icon (SVG Code)</label><textarea name="why_choose_us[NEW_INDEX][icon]" rows="3"></textarea></div>
    </div>
</template>

<template id="dynamic-item-template-curriculum_stages">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="curriculum_stages">Remove</button>
        <h4>Item NEW_INDEX_PLUS_1</h4>
        <div class="form-group"><label>Stage (e.g., Early Years)</label><input type="text" name="curriculum_stages[NEW_INDEX][stage]"></div>
        <div class="form-group"><label>Description</label><textarea name="curriculum_stages[NEW_INDEX][description]" rows="3"></textarea></div>
        <div class="form-group"><label>Icon (SVG Code)</label><textarea name="curriculum_stages[NEW_INDEX][icon]" rows="3"></textarea></div>
    </div>
</template>

<template id="dynamic-item-template-infra">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="infra">Remove</button>
        <h4>Item NEW_INDEX_PLUS_1</h4>
        <div class="form-group"><label>Title</label><input type="text" name="infra[NEW_INDEX][title]"></div>
        <div class="form-group"><label>Description</label><textarea name="infra[NEW_INDEX][description]" rows="3"></textarea></div>
        <div class="form-group"><label>Icon (SVG Code)</label><textarea name="infra[NEW_INDEX][icon]" rows="3"></textarea></div>
    </div>
</template>

<template id="dynamic-item-template-beyond_academics">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="beyond_academics">Remove</button>
        <h4>Item NEW_INDEX_PLUS_1</h4>
        <div class="form-group"><label>Title</label><input type="text" name="beyond_academics[NEW_INDEX][title]"></div>
        <div class="form-group"><label>Description</label><textarea name="beyond_academics[NEW_INDEX][description]" rows="3"></textarea></div>
        <div class="form-group"><label>Icon (SVG Code)</label><textarea name="beyond_academics[NEW_INDEX][icon]" rows="3"></textarea></div>
    </div>
</template>

<template id="dynamic-item-template-core_values">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="core_values">Remove</button>
        <h4>Item NEW_INDEX_PLUS_1</h4>
        <div class="form-group"><label>Value (e.g., Integrity)</label><input type="text" name="core_values[NEW_INDEX][value]"></div>
        <div class="form-group"><label>Description</label><textarea name="core_values[NEW_INDEX][description]" rows="3"></textarea></div>
        <div class="form-group"><label>Icon (SVG Code)</label><textarea name="core_values[NEW_INDEX][icon]" rows="3"></textarea></div>
    </div>
</template>

<template id="dynamic-item-template-faculty_highlights">
    <div class="dynamic-item full-width" data-index="NEW_INDEX">
        <button type="button" class="remove-item-btn" data-type="faculty_highlights">Remove</button>
        <div class="form-group">
            <label>Highlight Text NEW_INDEX_PLUS_1</label>
            <input type="text" name="faculty_highlights[]">
        </div>
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
        setupSectionToggle('hero-toggle-header', 'hero-section-content', true);
        setupSectionToggle('story-toggle-header', 'story-section-content', false);
        setupSectionToggle('core_pillars-toggle-header', 'core_pillars-section-content', false);
        setupSectionToggle('why_choose_us-toggle-header', 'why_choose_us-section-content', false);
        setupSectionToggle('curriculum_stages-toggle-header', 'curriculum_stages-section-content', false);
        setupSectionToggle('infra-toggle-header', 'infra-section-content', false);
        setupSectionToggle('beyond_academics-toggle-header', 'beyond_academics-section-content', false);
        setupSectionToggle('core_values-toggle-header', 'core_values-section-content', false);
        setupSectionToggle('faculty-toggle-header', 'faculty-section-content', false);
        setupSectionToggle('principal-toggle-header', 'principal-section-content', false);

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
                    if (oldName.includes('[]')) {
                         // For simple arrays like faculty highlights, no re-indexing of name is needed
                    } else {
                        const newName = oldName.replace(/\[\d+\]/, `[${index}]`);
                        input.setAttribute('name', newName);
                    }
                });
            });
        }

        document.querySelectorAll('.add-new-btn').forEach(button => {
            button.addEventListener('click', function() {
                const type = this.dataset.type;
                const template = document.getElementById(`dynamic-item-template-${type}`);
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