<?php
// Start the session
session_start();

// Include configuration and Cloudinary handler (though no images in Academics section currently)
require_once "../database/config.php";
// require_once "../database/cloudinary_upload_handler.php"; // Not strictly needed for this section

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["super_admin_id"]; // Admin ID from session

// Initialize variables for form fields based on academics_settings table
$settings = [
    'academics_section_title' => '',
    'academics_section_description' => '',
    'academic_levels_json' => '[]',
    'academic_features_title' => '',
    'academic_features_json' => '[]'
];

$errors = [];
$success_message = "";

// Initialize editable arrays for form display (matching the static examples)
$academic_levels_editable = [];
for ($i = 0; $i < 5; $i++) { // 5 academic levels
    $academic_levels_editable[$i] = [
        'title' => '', 'subtitle' => '', 'description' => '',
        'subjects' => '', // Stored as comma-separated string for input
        'icon' => '' // SVG string
    ];
}

$academic_features_editable = [];
for ($i = 0; $i < 4; $i++) { // 4 academic features
    $academic_features_editable[$i] = [
        'title' => '', 'description' => '', 'icon' => '' // SVG string
    ];
}


// --- Fetch Current Academics Settings ---
$sql_fetch = "SELECT * FROM academics_settings WHERE id = 1";
if ($result_fetch = mysqli_query($link, $sql_fetch)) {
    if (mysqli_num_rows($result_fetch) == 1) {
        $current_settings = mysqli_fetch_assoc($result_fetch);
        // Populate settings array with fetched data
        foreach ($settings as $key => $value) {
            if (isset($current_settings[$key]) && $current_settings[$key] !== NULL) {
                $settings[$key] = $current_settings[$key];
            }
        }
        
        // Handle academic_levels_json for form display
        if (!empty($settings['academic_levels_json'])) {
            $decoded_levels = json_decode($settings['academic_levels_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_levels)) {
                // Ensure array is reset before populating to clear old data if fewer items are fetched
                foreach ($academic_levels_editable as $index => $level) {
                    $academic_levels_editable[$index] = ['title' => '', 'subtitle' => '', 'description' => '', 'subjects' => '', 'icon' => ''];
                }
                foreach ($decoded_levels as $index => $level) {
                    if (isset($academic_levels_editable[$index])) {
                        $academic_levels_editable[$index]['title'] = $level['title'] ?? '';
                        $academic_levels_editable[$index]['subtitle'] = $level['subtitle'] ?? '';
                        $academic_levels_editable[$index]['description'] = $level['description'] ?? '';
                        $academic_levels_editable[$index]['subjects'] = implode(', ', $level['subjects'] ?? []); // Convert array back to comma-sep string
                        $academic_levels_editable[$index]['icon'] = $level['icon'] ?? '';
                    }
                }
            }
        }

        // Handle academic_features_json for form display
        if (!empty($settings['academic_features_json'])) {
            $decoded_features = json_decode($settings['academic_features_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_features)) {
                // Ensure array is reset before populating
                foreach ($academic_features_editable as $index => $feature) {
                    $academic_features_editable[$index] = ['title' => '', 'description' => '', 'icon' => ''];
                }
                foreach ($decoded_features as $index => $feature) {
                    if (isset($academic_features_editable[$index])) {
                        $academic_features_editable[$index]['title'] = $feature['title'] ?? '';
                        $academic_features_editable[$index]['description'] = $feature['description'] ?? '';
                        $academic_features_editable[$index]['icon'] = $feature['icon'] ?? '';
                    }
                }
            }
        }

    } else {
        // Initial settings not found, will be created on submission
        // $success_message .= "Initial 'Academics' settings are not found. A new entry will be created on submission."; // Commented out to reduce noise on first load
    }
    mysqli_free_result($result_fetch);
} else {
    $errors[] = "Error fetching current 'Academics' settings: " . mysqli_error($link);
}

// --- Process Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" ) { // Removed empty($errors) check here to allow processing even if a prior error occurred, but will still check specific fields for validation
    
    // Sanitize and validate all text fields
    $settings['academics_section_title'] = trim($_POST['academics_section_title'] ?? '');
    $settings['academics_section_description'] = trim($_POST['academics_section_description'] ?? '');
    $settings['academic_features_title'] = trim($_POST['academic_features_title'] ?? '');

    // --- Process Academic Levels JSON ---
    $new_academic_levels = [];
    for ($i = 0; $i < 5; $i++) {
        $title = trim($_POST["academic_level{$i}_title"] ?? '');
        $subtitle = trim($_POST["academic_level{$i}_subtitle"] ?? '');
        $description = trim($_POST["academic_level{$i}_description"] ?? '');
        $subjects_str = trim($_POST["academic_level{$i}_subjects"] ?? '');
        $icon = trim($_POST["academic_level{$i}_icon"] ?? '');

        // Convert comma-separated subjects string to array
        $subjects_array = array_map('trim', explode(',', $subjects_str));
        $subjects_array = array_filter($subjects_array); // Remove empty entries

        // Only add if title is not empty
        if (!empty($title)) {
            $new_academic_levels[] = [
                'title' => $title,
                'subtitle' => $subtitle,
                'description' => $description,
                'subjects' => $subjects_array,
                'icon' => $icon
            ];
        }
    }
    $settings['academic_levels_json'] = json_encode($new_academic_levels);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "Error encoding academic levels data: " . json_last_error_msg();
    }

    // --- Process Academic Features JSON ---
    $new_academic_features = [];
    for ($i = 0; $i < 4; $i++) {
        $title = trim($_POST["academic_feature{$i}_title"] ?? '');
        $description = trim($_POST["academic_feature{$i}_description"] ?? '');
        $icon = trim($_POST["academic_feature{$i}_icon"] ?? '');

        // Only add if title is not empty
        if (!empty($title)) {
            $new_academic_features[] = [
                'title' => $title,
                'description' => $description,
                'icon' => $icon
            ];
        }
    }
    $settings['academic_features_json'] = json_encode($new_academic_features);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "Error encoding academic features data: " . json_last_error_msg();
    }
    

    // Only proceed to update if no errors occurred
    if (empty($errors)) {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both first-time creation and subsequent updates
        $sql_upsert = "INSERT INTO `academics_settings` (
            `id`, `academics_section_title`, `academics_section_description`,
            `academic_levels_json`, `academic_features_title`, `academic_features_json`,
            `updated_by_admin_id`
        ) VALUES (
            1, ?, ?, ?, ?, ?, ?
        ) ON DUPLICATE KEY UPDATE
            `academics_section_title` = VALUES(`academics_section_title`),
            `academics_section_description` = VALUES(`academics_section_description`),
            `academic_levels_json` = VALUES(`academic_levels_json`),
            `academic_features_title` = VALUES(`academic_features_title`),
            `academic_features_json` = VALUES(`academic_features_json`),
            `updated_by_admin_id` = VALUES(`updated_by_admin_id`),
            `updated_at` = CURRENT_TIMESTAMP";

        if ($stmt = mysqli_prepare($link, $sql_upsert)) {
            mysqli_stmt_bind_param(
                $stmt,
                "sssssi", // 5 's' for strings/JSON, 1 'i' for admin_id = 6 parameters
                $settings['academics_section_title'],
                $settings['academics_section_description'],
                $settings['academic_levels_json'],
                $settings['academic_features_title'],
                $settings['academic_features_json'],
                $admin_id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Academics settings updated successfully.";
                // Re-fetch to show the latest data, ensuring UI is in sync
                // Note: Calling fetch_current_settings would achieve the same and avoid duplicating logic
                // fetch_current_settings($link, $settings, $academic_levels_editable, $academic_features_editable, $errors, $success_message);
                
                // Manual re-populate for brevity here, similar to fetch_current_settings's internal logic
                $sql_fetch_latest = "SELECT * FROM academics_settings WHERE id = 1";
                $result_fetch_latest = mysqli_query($link, $sql_fetch_latest);
                $latest_settings = mysqli_fetch_assoc($result_fetch_latest);
                foreach ($settings as $key => $value) {
                    if (isset($latest_settings[$key]) && $latest_settings[$key] !== NULL) {
                        $settings[$key] = $latest_settings[$key];
                    }
                }
                
                // Re-decode for editable form fields after update
                // Academic Levels
                foreach ($academic_levels_editable as $index => $value) { // Reset all first
                    $academic_levels_editable[$index] = ['title' => '', 'subtitle' => '', 'description' => '', 'subjects' => '', 'icon' => ''];
                }
                if (!empty($settings['academic_levels_json'])) {
                    $decoded_levels_latest = json_decode($settings['academic_levels_json'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_levels_latest)) {
                        foreach ($decoded_levels_latest as $index => $level) {
                            if (isset($academic_levels_editable[$index])) {
                                $academic_levels_editable[$index]['title'] = $level['title'] ?? '';
                                $academic_levels_editable[$index]['subtitle'] = $level['subtitle'] ?? '';
                                $academic_levels_editable[$index]['description'] = $level['description'] ?? '';
                                $academic_levels_editable[$index]['subjects'] = implode(', ', $level['subjects'] ?? []);
                                $academic_levels_editable[$index]['icon'] = $level['icon'] ?? '';
                            }
                        }
                    }
                }

                // Academic Features
                foreach ($academic_features_editable as $index => $value) { // Reset all first
                    $academic_features_editable[$index] = ['title' => '', 'description' => '', 'icon' => ''];
                }
                if (!empty($settings['academic_features_json'])) {
                    $decoded_features_latest = json_decode($settings['academic_features_json'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_features_latest)) {
                        foreach ($decoded_features_latest as $index => $feature) {
                            if (isset($academic_features_editable[$index])) {
                                $academic_features_editable[$index]['title'] = $feature['title'] ?? '';
                                $academic_features_editable[$index]['description'] = $feature['description'] ?? '';
                                $academic_features_editable[$index]['icon'] = $feature['icon'] ?? '';
                            }
                        }
                    }
                }

                mysqli_free_result($result_fetch_latest);

            } else {
                $errors[] = "Error updating settings: " . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Database preparation error: " . mysqli_error($link);
        }
    }
}

// Include admin header for consistent layout
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Academics Section Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        @keyframes shadowPulse {
            0% { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            50% { box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
            100% { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(-45deg, #2C3E50, #E7A33C, #A9CCE3, #F8F8F8);
            background-size: 400% 400%;
            animation: gradientAnimation 20s ease infinite;
            color: #444;
            line-height: 1.6;
            /* Ensure the body has enough space for header/footer if they are outside */
            padding-top: 20px; /* Example, adjust based on admin_header.php */
            padding-bottom: 20px; /* Example, adjust based on admin_footer.php */
        }

        .container {
            max-width: 900px;
            margin: auto;
            margin-top: 100px; /* Adjusted to allow for header if it's external */
            margin-bottom: 50px; /* Adjusted to allow for footer if it's external */
            background: rgba(248, 248, 248, 0.95);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 40px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        h2 {
            font-family: 'Lora', serif;
            text-align: center;
            color: #1A2C5A;
            font-weight: 700;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 2.2em; /* Ensure consistent main heading size */
        }
        /* Removed original h3 styling here as it's now handled by .form-header h3 */
        

        .form-group { margin-bottom: 22px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1A2C5A;
            font-size: 0.95em;
        }
        .form-group input:not([type="file"]), .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #C0D3EB;
            border-radius: 8px;
            box-sizing: border-box;
            background-color: #FFFFFF;
            font-size: 1em;
            color: #333;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #E7A33C;
            box-shadow: 0 0 0 4px rgba(231, 163, 60, 0.2);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            /* margin-bottom: 20px; Moved to .form-collapsible-content .form-grid */
        }
        .full-width { grid-column: 1 / -1; }

        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 19px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            
            background: linear-gradient(45deg, #1A2C5A, #2C3E50);
            background-size: 200% 200%;
            animation: gradientAnimation 6s ease infinite;
            
            transition: transform 0.3s ease, box-shadow 0.3s ease, background-position 0.3s ease;
            box-shadow: 0 6px 20px rgba(26, 44, 90, 0.3);
        }
        .btn:hover {
            transform: translateY(-3px) scale(1.01);
            background-position: 100% 0%;
            box-shadow: 0 10px 25px rgba(26, 44, 90, 0.4);
        }

        .alert { 
            padding: 18px; 
            margin-bottom: 25px; 
            border-radius: 8px; 
            font-size: 1em;
            font-weight: 600;
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

        .current-image-container { /* Not used in this specific file, but kept for consistency */
            margin-top: 20px;
            text-align: center;
            background-color: rgba(255, 255, 255, 0.7);
            padding: 15px;
            border-radius: 10px;
            border: 1px dashed #A9CCE3;
        }
        .current-image-container p {
            font-weight: 600;
            color: #1A2C5A;
            margin-bottom: 10px;
            font-size: 0.9em;
        }
        .current-image-container img {
            max-width: 350px;
            max-height: 250px;
            border: 2px solid #E7A33C;
            padding: 5px;
            background-color: #fff;
            object-fit: contain;
        }

        .dynamic-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fcfcfc;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .dynamic-item input, .dynamic-item textarea {
            margin-bottom: 10px;
        }
        .dynamic-item label {
            font-size: 0.85em;
            color: #555;
            margin-bottom: 4px;
        }
        .dynamic-item h4 {
            font-size: 1.1em;
            font-weight: 700;
            color: #1A2C5A;
            margin-top: 0; /* Important for accordion item headings */
            margin-bottom: 15px;
            border-bottom: 1px dotted #A9CCE3;
            padding-bottom: 5px;
            text-align: left; /* Ensure it's not centered */
            white-space: normal;
            transform: none;
        }
        .svg-preview {
            background-color: #a855f7; /* Matching original icon bg */
            border-radius: 8px;
            padding: 8px;
            margin-top: 10px;
            width: 48px; /* Fixed size for preview */
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .svg-preview svg {
            width: 24px;
            height: 24px;
            color: white; /* Ensure SVG color is white */
            fill: white;
        }
        /* Override for academic levels icon preview */
        .academic-level-icon-preview svg {
            width: 32px; /* Slightly larger for academic levels */
            height: 32px;
        }


        /* --- Collapsible Form Specific Styles (NEW / ADAPTED) --- */
        form.main-academics-form { /* Apply to the main form wrapper */
            background-color: #fefefe; /* Lighter background for individual forms */
            padding: 0; /* No padding directly on form, handled by inner elements */
            border-radius: 12px;
            margin-bottom: 30px; /* Space between forms */
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); /* Subtle shadow */
            border: 1px solid #e0e7ef; /* Light border */
        }
        
        .collapsible-section { /* Wrapper for each collapsible part */
            margin-bottom: 0; /* No margin here, as form.main-academics-form handles it */
            border-bottom: 1px solid #e0e7ef; /* Separator between sections */
        }
        .collapsible-section:last-of-type {
            border-bottom: none; /* No border for the last section */
        }


        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer; /* Indicate clickable area */
            padding: 25px 30px; /* Padding for the header area */
            background-color: #fefefe; /* Match form background */
            transition: background-color 0.3s ease;
        }
        .collapsible-section:first-of-type .form-header {
            border-radius: 12px 12px 0 0; /* Rounded top corners for the very first header */
        }
        .form-header:hover {
            background-color: #f5f8fc; /* Subtle hover effect */
        }

        .form-header h3 {
            margin: 0;
            padding: 0;
            text-align: left;
            border-bottom: none; /* Remove h3's individual border */
            transform: none; /* Remove h3's individual transform */
            flex-grow: 1; /* Allow h3 to take available space */
            color: #1A2C5A;
            font-size: 1.6em; /* Appropriate size for form section titles */
            text-transform: none; /* Not all caps for form titles */
            letter-spacing: normal;
        }

        .toggle-button {
            background: none;
            border: none;
            font-size: 1.6em; /* Size of the chevron icon */
            color: #1A2C5A;
            cursor: pointer;
            padding: 0;
            transition: transform 0.3s ease;
        }

        .toggle-button i {
            pointer-events: none; /* Ensures the click is on the button, not just the icon */
        }

        .toggle-button.rotated i {
            transform: rotate(180deg);
        }

        .form-collapsible-content {
            max-height: 0; /* Hidden by default */
            overflow: hidden;
            transition: max-height 0.4s ease-out, opacity 0.3s ease-out;
            opacity: 0;
            padding: 0 30px; /* Horizontal padding for content */
            padding-bottom: 25px; /* Bottom padding when open */
            background-color: #fefefe; /* Match form background */
        }

        .collapsible-section:last-of-type .form-collapsible-content {
             border-radius: 0 0 12px 12px; /* Rounded bottom corners for the last section's content */
        }

        .form-collapsible-content.open {
            max-height: 2000px; /* Sufficiently large to show all content */
            opacity: 1;
        }

        /* Adjust internal element spacing within collapsible content */
        .form-collapsible-content .form-grid {
            margin-top: 25px; /* Space between header and first grid item */
            margin-bottom: 0; /* Internal grid should not add extra margin at bottom of content */
        }
        .form-collapsible-content .form-group {
            margin-bottom: 20px;
        }
        .form-collapsible-content .form-actions {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        /* Submit button outside collapsible sections but still in the form */
        .main-form-submit {
            padding: 0 40px 40px; /* Adjust padding to match container padding */
            border-radius: 0 0 18px 18px; /* Match container bottom radius */
            background: rgba(248, 248, 248, 0.95); /* Match container background */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); /* Match container shadow */
            border-top: none; /* No top border */
            margin-top: -30px; /* Pull it up to sit correctly */
            position: relative;
            z-index: 1;
            padding-top: 40px;
        }
        /* Ensure the button within .main-form-submit takes full width */
        .main-form-submit .btn {
            width: 100%;
        }


        /* Responsive adjustments for new elements */
        @media (max-width: 768px) {
            .container {
                padding: 25px;
                margin-top: 80px;
            }
            .form-header {
                padding: 20px 25px;
            }
            .form-header h3 {
                font-size: 1.4em;
            }
            .toggle-button {
                font-size: 1.4em;
            }
            .form-collapsible-content {
                padding: 0 25px;
                padding-bottom: 20px;
            }
            .form-collapsible-content .form-grid {
                margin-top: 20px;
            }
            .main-form-submit {
                padding: 0 25px 25px;
                padding-top: 25px;
            }
        }
        @media (max-width: 480px) {
            .container {
                padding: 20px;
                margin-top: 60px;
            }
            h2 {
                font-size: 1.8em;
            }
            .form-header {
                padding: 15px 20px;
            }
            .form-header h3 {
                font-size: 1.2em;
            }
            .toggle-button {
                font-size: 1.2em;
            }
            .form-collapsible-content {
                padding: 0 20px;
                padding-bottom: 15px;
            }
            .form-collapsible-content .form-grid {
                margin-top: 15px;
            }
            .btn {
                font-size: 1em;
                padding: 14px;
            }
            .main-form-submit {
                padding: 0 20px 20px;
                padding-top: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Manage Academics Section Settings</h2>

    <?php 
    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
    }
    if (!empty($success_message)) {
        echo '<div class="alert alert-success">' . htmlspecialchars($success_message) . '</div>';
    }
    ?>

    <!-- The main form now wraps all collapsible sections and the final submit button -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="main-academics-form">
        
        <!-- --- Collapsible Section: Academics Section Main Content --- -->
        <div class="collapsible-section">
            <div class="form-header">
                <h3>Academics Section Main Content</h3>
                <button type="button" class="toggle-button" aria-expanded="false">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="form-collapsible-content">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="academics_section_title">Main Title</label>
                        <input type="text" name="academics_section_title" id="academics_section_title" value="<?php echo htmlspecialchars($settings['academics_section_title']); ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="academics_section_description">Main Description</label>
                        <textarea name="academics_section_description" id="academics_section_description" rows="3"><?php echo htmlspecialchars($settings['academics_section_description']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- --- Collapsible Section: Academic Levels (Up to 5) --- -->
        <div class="collapsible-section">
            <div class="form-header">
                <h3>Academic Levels (Up to 5)</h3>
                <button type="button" class="toggle-button" aria-expanded="false">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="form-collapsible-content">
                <p style="text-align: center; font-size: 0.9em; color: #666; margin-bottom: 20px;">
                    Enter details for each academic level. Clear a level's title to remove it. Subjects should be comma-separated (e.g., "Math, Science, English").
                </p>
                <div class="form-grid">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <div class="dynamic-item full-width">
                            <h4>Academic Level <?php echo $i + 1; ?></h4>
                            <div class="form-group">
                                <label for="academic_level<?php echo $i; ?>_title">Title</label>
                                <input type="text" name="academic_level<?php echo $i; ?>_title" id="academic_level<?php echo $i; ?>_title" value="<?php echo htmlspecialchars($academic_levels_editable[$i]['title'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="academic_level<?php echo $i; ?>_subtitle">Subtitle (e.g., "Ages 3-5")</label>
                                <input type="text" name="academic_level<?php echo $i; ?>_subtitle" id="academic_level<?php echo $i; ?>_subtitle" value="<?php echo htmlspecialchars($academic_levels_editable[$i]['subtitle'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="academic_level<?php echo $i; ?>_description">Description</label>
                                <textarea name="academic_level<?php echo $i; ?>_description" id="academic_level<?php echo $i; ?>_description" rows="2"><?php echo htmlspecialchars($academic_levels_editable[$i]['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="academic_level<?php echo $i; ?>_subjects">Subjects (Comma-separated)</label>
                                <input type="text" name="academic_level<?php echo $i; ?>_subjects" id="academic_level<?php echo $i; ?>_subjects" value="<?php echo htmlspecialchars($academic_levels_editable[$i]['subjects'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="academic_level<?php echo $i; ?>_icon">Icon (SVG Code)</label>
                                <textarea name="academic_level<?php echo $i; ?>_icon" id="academic_level<?php echo $i; ?>_icon" rows="3"><?php echo htmlspecialchars($academic_levels_editable[$i]['icon'] ?? ''); ?></textarea>
                                <?php 
                                if (!empty($academic_levels_editable[$i]['icon']) && strpos($academic_levels_editable[$i]['icon'], '<svg') !== false): ?>
                                    <div class="svg-preview academic-level-icon-preview">
                                        <?php echo $academic_levels_editable[$i]['icon']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- --- Collapsible Section: Academic Features (Up to 4) --- -->
        <div class="collapsible-section">
            <div class="form-header">
                <h3>Academic Features (Up to 4)</h3>
                <button type="button" class="toggle-button" aria-expanded="false">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="form-collapsible-content">
                <div class="form-group full-width">
                    <label for="academic_features_title">Academic Features Sub-title</label>
                    <input type="text" name="academic_features_title" id="academic_features_title" value="<?php echo htmlspecialchars($settings['academic_features_title']); ?>">
                </div>
                <p style="text-align: center; font-size: 0.9em; color: #666; margin-bottom: 20px;">
                    Enter feature details. Clear a feature's title to remove it. For icons, paste the SVG code directly.
                </p>
                <div class="form-grid">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="dynamic-item">
                            <h4>Feature <?php echo $i + 1; ?></h4>
                            <div class="form-group">
                                <label for="academic_feature<?php echo $i; ?>_title">Title</label>
                                <input type="text" name="academic_feature<?php echo $i; ?>_title" id="academic_feature<?php echo $i; ?>_title" value="<?php echo htmlspecialchars($academic_features_editable[$i]['title'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="academic_feature<?php echo $i; ?>_description">Description</label>
                                <textarea name="academic_feature<?php echo $i; ?>_description" id="academic_feature<?php echo $i; ?>_description" rows="2"><?php echo htmlspecialchars($academic_features_editable[$i]['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="academic_feature<?php echo $i; ?>_icon">Icon (SVG Code)</label>
                                <textarea name="academic_feature<?php echo $i; ?>_icon" id="academic_feature<?php echo $i; ?>_icon" rows="3"><?php echo htmlspecialchars($academic_features_editable[$i]['icon'] ?? ''); ?></textarea>
                            </div>
                            <?php 
                            if (!empty($academic_features_editable[$i]['icon']) && strpos($academic_features_editable[$i]['icon'], '<svg') !== false): ?>
                                <div class="svg-preview">
                                    <?php echo $academic_features_editable[$i]['icon']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- The single submit button for the entire form -->
        <div class="main-form-submit">
            <input type="submit" class="btn" value="Update All Academics Settings">
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const collapsibleSections = document.querySelectorAll('.collapsible-section');

    collapsibleSections.forEach((section, index) => {
        const header = section.querySelector('.form-header');
        const content = section.querySelector('.form-collapsible-content');
        const toggleButton = section.querySelector('.toggle-button');

        // Initial state: first section open, others closed
        if (index === 0) { // Make the first section open by default
            content.classList.add('open');
            toggleButton.classList.add('rotated');
            toggleButton.setAttribute('aria-expanded', 'true');
        } else {
            content.classList.remove('open');
            toggleButton.classList.remove('rotated');
            toggleButton.setAttribute('aria-expanded', 'false');
        }

        header.addEventListener('click', function() {
            const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';

            if (isExpanded) {
                content.classList.remove('open');
                toggleButton.classList.remove('rotated');
                toggleButton.setAttribute('aria-expanded', 'false');
            } else {
                content.classList.add('open');
                toggleButton.classList.add('rotated');
                toggleButton.setAttribute('aria-expanded', 'true');
            }
        });
    });
});
</script>

</body>
</html>
<?php 
if($link) mysqli_close($link);
// Include admin footer for consistent layout - NOT MODIFIED
require_once './admin_footer.php';
?>