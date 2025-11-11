<?php
// Start the session
session_start();

// Include configuration and Cloudinary handler
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php"; // Make sure this path is correct

// Include admin header for consistent layout (assumed to provide <!DOCTYPE html>, <html>, <head>, <body>)
require_once './admin_header.php';

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["super_admin_id"]; // Admin ID from session

// Initialize variables for form fields based on about_settings table
$settings = [
    'about_section_title' => '',
    'about_section_description' => '',
    'about_image_url' => '', // School building image
    'our_legacy_text' => '',
    'our_vision_text' => '',
    'our_mission_text' => '',
    'features_json' => '[]', // Default to empty JSON array
    'principal_message_quote' => '',
    'principal_name' => '',
    'principal_title_role' => '',
    'principal_qualifications' => '',
    'principal_image_url' => '' // Principal's photo
];

$errors = [];
$success_message = "";

// Store features in an editable format for the form (assuming max 4 features for display consistency)
$features_editable = [];
for ($i = 0; $i < 4; $i++) {
    $features_editable[$i] = [
        'title' => '',
        'description' => '',
        'icon' => '' // SVG string
    ];
}


// --- Fetch Current About Settings ---
$sql_fetch = "SELECT * FROM about_settings WHERE id = 1";
if ($result_fetch = mysqli_query($link, $sql_fetch)) {
    if (mysqli_num_rows($result_fetch) == 1) {
        $current_settings = mysqli_fetch_assoc($result_fetch);
        // Populate settings array with fetched data
        foreach ($settings as $key => $value) {
            if (isset($current_settings[$key]) && $current_settings[$key] !== NULL) {
                $settings[$key] = $current_settings[$key];
            }
        }
        
        // Handle features_json specifically for form display
        if (!empty($settings['features_json'])) {
            $decoded_features = json_decode($settings['features_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_features)) {
                foreach ($decoded_features as $index => $feature) {
                    if (isset($features_editable[$index])) { // Ensure index exists to prevent overflow
                        $features_editable[$index]['title'] = $feature['title'] ?? '';
                        $features_editable[$index]['description'] = $feature['description'] ?? '';
                        $features_editable[$index]['icon'] = $feature['icon'] ?? '';
                    }
                }
            }
        }

    } else {
        // If no settings exist, it means the initial INSERT might not have run or failed.
        // We'll proceed with default values for display, and the INSERT will create the row.
        $success_message .= "Initial 'About Us' settings are not found. A new entry will be created on submission.";
    }
    mysqli_free_result($result_fetch);
} else {
    $errors[] = "Error fetching current 'About Us' settings: " . mysqli_error($link);
}

// --- Process Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors)) {

    // Sanitize and validate all text fields
    $settings['about_section_title'] = trim($_POST['about_section_title'] ?? '');
    $settings['about_section_description'] = trim($_POST['about_section_description'] ?? '');
    $settings['our_legacy_text'] = trim($_POST['our_legacy_text'] ?? '');
    $settings['our_vision_text'] = trim($_POST['our_vision_text'] ?? '');
    $settings['our_mission_text'] = trim($_POST['our_mission_text'] ?? '');
    $settings['principal_message_quote'] = trim($_POST['principal_message_quote'] ?? '');
    $settings['principal_name'] = trim($_POST['principal_name'] ?? '');
    $settings['principal_title_role'] = trim($_POST['principal_title_role'] ?? '');
    $settings['principal_qualifications'] = trim($_POST['principal_qualifications'] ?? '');

    // --- Handle Image Uploads ---
    // About Section Image (School Building)
    if (isset($_FILES['about_image']) && $_FILES['about_image']['error'] == UPLOAD_ERR_OK) {
        $uploadResult = uploadToCloudinary($_FILES['about_image'], 'school_about_images'); // Cloudinary folder
        if (isset($uploadResult['error'])) {
            $errors[] = "School Building Image Upload Failed: " . $uploadResult['error'];
        } else {
            $settings['about_image_url'] = $uploadResult['secure_url'];
            // Optionally, delete the old image from Cloudinary if you store public_id in DB
            // e.g., if (!empty($current_settings['about_image_public_id'])) { deleteFromCloudinary($current_settings['about_image_public_id']); }
        }
    }

    // Principal Image
    if (isset($_FILES['principal_image']) && $_FILES['principal_image']['error'] == UPLOAD_ERR_OK) {
        $uploadResult = uploadToCloudinary($_FILES['principal_image'], 'school_principal_images'); // Cloudinary folder
        if (isset($uploadResult['error'])) {
            $errors[] = "Principal Image Upload Failed: " . $uploadResult['error'];
        } else {
            $settings['principal_image_url'] = $uploadResult['secure_url'];
            // Optionally, delete the old image from Cloudinary
        }
    }

    // --- Process Features JSON ---
    $new_features = [];
    for ($i = 0; $i < 4; $i++) { // Assuming a fixed number of features for the form
        $feature_title = trim($_POST["feature{$i}_title"] ?? '');
        $feature_description = trim($_POST["feature{$i}_description"] ?? '');
        // Note: SVG icon input is taken directly as string
        $feature_icon = trim($_POST["feature{$i}_icon"] ?? ''); 

        // Only add feature if title is not empty (allows deleting features by clearing title)
        if (!empty($feature_title)) {
            $new_features[] = [
                'title' => $feature_title,
                'description' => $feature_description,
                'icon' => $feature_icon
            ];
        }
    }
    $settings['features_json'] = json_encode($new_features);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "Error encoding features data: " . json_last_error_msg();
    }
    

    // Only proceed to update if no errors occurred
    if (empty($errors)) {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both first-time creation and subsequent updates
        $sql_upsert = "INSERT INTO `about_settings` (
            `id`, `about_section_title`, `about_section_description`, `about_image_url`,
            `our_legacy_text`, `our_vision_text`, `our_mission_text`, `features_json`,
            `principal_message_quote`, `principal_name`, `principal_title_role`,
            `principal_qualifications`, `principal_image_url`, `updated_by_admin_id`
        ) VALUES (
            1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        ) ON DUPLICATE KEY UPDATE
            `about_section_title` = VALUES(`about_section_title`),
            `about_section_description` = VALUES(`about_section_description`),
            `about_image_url` = VALUES(`about_image_url`),
            `our_legacy_text` = VALUES(`our_legacy_text`),
            `our_vision_text` = VALUES(`our_vision_text`),
            `our_mission_text` = VALUES(`our_mission_text`),
            `features_json` = VALUES(`features_json`),
            `principal_message_quote` = VALUES(`principal_message_quote`),
            `principal_name` = VALUES(`principal_name`),
            `principal_title_role` = VALUES(`principal_title_role`),
            `principal_qualifications` = VALUES(`principal_qualifications`),
            `principal_image_url` = VALUES(`principal_image_url`),
            `updated_by_admin_id` = VALUES(`updated_by_admin_id`),
            `updated_at` = CURRENT_TIMESTAMP";

        if ($stmt = mysqli_prepare($link, $sql_upsert)) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssssssi", // Corrected type string: 12 's' for strings, 1 'i' for admin_id
                $settings['about_section_title'], $settings['about_section_description'], $settings['about_image_url'],
                $settings['our_legacy_text'], $settings['our_vision_text'], $settings['our_mission_text'], $settings['features_json'],
                $settings['principal_message_quote'], $settings['principal_name'], $settings['principal_title_role'],
                $settings['principal_qualifications'], $settings['principal_image_url'],
                $admin_id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "About Us settings updated successfully.";
                // Re-fetch to show the latest data, ensuring UI is in sync
                $sql_fetch_latest = "SELECT * FROM about_settings WHERE id = 1";
                $result_fetch_latest = mysqli_query($link, $sql_fetch_latest);
                $latest_settings = mysqli_fetch_assoc($result_fetch_latest);
                foreach ($settings as $key => $value) {
                    if (isset($latest_settings[$key]) && $latest_settings[$key] !== NULL) {
                        $settings[$key] = $latest_settings[$key];
                    }
                }
                // Re-decode features_json for accurate form display after update
                if (!empty($settings['features_json'])) {
                    $decoded_features_latest = json_decode($settings['features_json'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_features_latest)) {
                        // Reset features_editable and then populate with latest
                        foreach ($features_editable as $index => $value) {
                            $features_editable[$index] = ['title' => '', 'description' => '', 'icon' => ''];
                        }
                        foreach ($decoded_features_latest as $index => $feature) {
                            if (isset($features_editable[$index])) {
                                $features_editable[$index]['title'] = $feature['title'] ?? '';
                                $features_editable[$index]['description'] = $feature['description'] ?? '';
                                $features_editable[$index]['icon'] = $feature['icon'] ?? '';
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
?>

<title>Manage About Section Settings</title>
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
    /* @keyframes and other general styles */
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

    /* Styles specifically for this page's content wrapper */
    .container {
        max-width: 900px;
        margin: auto;
        margin-top: 100px;
        margin-bottom: 50px;
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
    }
    
    /* Collapsible Section Styles */
    .collapsible-section {
        margin-bottom: 25px;
        border: 1px solid #C0D3EB;
        border-radius: 10px;
        background-color: #fff;
        overflow: hidden; /* Ensures content is clipped during collapse */
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transition: box-shadow 0.3s ease;
    }
    .collapsible-section:hover {
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 25px;
        background-color: #E7A33C; /* Accent color for headers */
        color: white;
        cursor: pointer;
        font-family: 'Lora', serif;
        font-weight: 700;
        font-size: 1.25em;
        letter-spacing: 0.8px;
        border-bottom: 1px solid rgba(255,255,255,0.2);
        transition: background-color 0.3s ease;
    }
    .section-header:hover {
        background-color: #d19230; /* Slightly darker on hover */
    }

    .section-header h3 {
        margin: 0;
        color: white; /* Ensure heading color is white */
        font-size: 1.25em; /* Override default h3 size for headers */
        text-transform: none; /* Keep capitalization for visual style */
        border-bottom: none;
        padding-bottom: 0;
        margin-left: 0;
        transform: none;
        white-space: normal;
        flex-grow: 1; /* Allow h3 to take available space */
    }

    .toggle-icon {
        width: 28px;
        height: 28px;
        stroke: white;
        stroke-width: 2.5;
        transition: transform 0.3s ease;
    }
    .toggle-icon.rotated {
        transform: rotate(180deg); /* Rotate for up arrow */
    }

    .section-content {
        max-height: 0; /* Hidden by default */
        overflow: hidden;
        transition: max-height 0.5s ease-out; /* Smooth collapse/expand */
        padding: 0 25px; /* Adjust padding for collapsed state */
    }
    .section-content.open {
        max-height: 2000px; /* Large enough value to show all content */
        padding: 25px; /* Restore padding when open */
    }

    /* Existing form styles */
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
        margin-bottom: 20px;
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
        
        background: linear-gradient(45deg, #e6e8ecff, #2C3E50);
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

    .image-preview {
        margin-top: 15px;
        max-width: 100%;
        height: auto;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        display: block;
        margin-left: auto;
        margin-right: auto;
    }
    .current-image-container {
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

    .feature-item {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        background-color: #fcfcfc;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .feature-item input, .feature-item textarea {
        margin-bottom: 10px;
    }
    .feature-item label {
        font-size: 0.85em;
        color: #555;
        margin-bottom: 4px;
    }
    .feature-svg-preview {
        background-color: #a855f7; /* Matching original feature card icon bg */
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
    .feature-svg-preview svg {
        width: 24px;
        height: 24px;
        color: white; /* Ensure SVG color is white */
    }
    .feature-item h4 {
        font-size: 1.1em;
        font-weight: 700;
        color: #1A2C5A;
        margin-bottom: 15px;
        border-bottom: 1px dotted #A9CCE3;
        padding-bottom: 5px;
    }

    @media (max-width: 768px) {
        .container {
            padding: 25px;
            margin-top: 80px;
        }
        .form-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        h2 {
            font-size: 1.8em;
            margin-bottom: 20px;
        }
        .section-header {
            font-size: 1.1em;
            padding: 12px 20px;
        }
        .section-header h3 {
            font-size: 1.1em;
        }
        .section-content.open {
            padding: 20px;
        }
        .btn {
            font-size: 1em;
            padding: 14px;
        }
        .current-image-container img {
            max-width: 100%;
        }
        .feature-item {
            grid-column: 1 / -1; /* Ensure features span full width on small screens */
        }
    }
</style>

<div class="container">
    <h2>Manage About Section Settings</h2>

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

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
        
        <!-- About Section Main Content -->
        <div class="collapsible-section">
            <div class="section-header" role="button" aria-expanded="true" aria-controls="aboutContent">
                <h3>About Section Main Content</h3>
                <svg class="toggle-icon rotated" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 15 12 9 18 15"></polyline></svg>
            </div>
            <div id="aboutContent" class="section-content open">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="about_section_title">Main Title</label>
                        <input type="text" name="about_section_title" id="about_section_title" value="<?php echo htmlspecialchars($settings['about_section_title']); ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="about_section_description">Main Description</label>
                        <textarea name="about_section_description" id="about_section_description" rows="3"><?php echo htmlspecialchars($settings['about_section_description']); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="about_image">School Building Image</label>
                        <input type="file" name="about_image" id="about_image" accept="image/*">
                        <?php if (!empty($settings['about_image_url'])): ?>
                            <div class="current-image-container">
                                <p>Current School Building Image:</p>
                                <img src="<?php echo htmlspecialchars($settings['about_image_url']); ?>" alt="Current School Building Image">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-width">
                        <label for="our_legacy_text">Our Legacy Text</label>
                        <textarea name="our_legacy_text" id="our_legacy_text" rows="4"><?php echo htmlspecialchars($settings['our_legacy_text']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="our_vision_text">Our Vision Text</label>
                        <textarea name="our_vision_text" id="our_vision_text" rows="3"><?php echo htmlspecialchars($settings['our_vision_text']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="our_mission_text">Our Mission Text</label>
                        <textarea name="our_mission_text" id="our_mission_text" rows="3"><?php echo htmlspecialchars($settings['our_mission_text']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="collapsible-section">
            <div class="section-header" role="button" aria-expanded="false" aria-controls="featuresContent">
                <h3>Features (Up to 4)</h3>
                <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>
            <div id="featuresContent" class="section-content">
                <p style="text-align: center; font-size: 0.9em; color: #666; margin-bottom: 20px;">
                    Enter feature details. Clear a feature's title to remove it. For icons, paste the SVG code directly.
                </p>
                <div class="form-grid">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="feature-item">
                            <h4>Feature <?php echo $i + 1; ?></h4>
                            <div class="form-group">
                                <label for="feature<?php echo $i; ?>_title">Title</label>
                                <input type="text" name="feature<?php echo $i; ?>_title" id="feature<?php echo $i; ?>_title" value="<?php echo htmlspecialchars($features_editable[$i]['title'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="feature<?php echo $i; ?>_description">Description</label>
                                <textarea name="feature<?php echo $i; ?>_description" id="feature<?php echo $i; ?>_description" rows="2"><?php echo htmlspecialchars($features_editable[$i]['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="feature<?php echo $i; ?>_icon">Icon (SVG Code)</label>
                                <textarea name="feature<?php echo $i; ?>_icon" id="feature<?php echo $i; ?>_icon" rows="3"><?php echo htmlspecialchars($features_editable[$i]['icon'] ?? ''); ?></textarea>
                                <?php 
                                // Only show preview if icon SVG is present and somewhat valid
                                if (!empty($features_editable[$i]['icon']) && strpos($features_editable[$i]['icon'], '<svg') !== false): ?>
                                    <div class="feature-svg-preview">
                                        <?php echo $features_editable[$i]['icon']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Principal's Message Section -->
        <div class="collapsible-section">
            <div class="section-header" role="button" aria-expanded="false" aria-controls="principalContent">
                <h3>Principal's Message</h3>
                <svg class="toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>
            <div id="principalContent" class="section-content">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="principal_message_quote">Principal's Quote</label>
                        <textarea name="principal_message_quote" id="principal_message_quote" rows="5"><?php echo htmlspecialchars($settings['principal_message_quote']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="principal_name">Principal's Name</label>
                        <input type="text" name="principal_name" id="principal_name" value="<?php echo htmlspecialchars($settings['principal_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="principal_title_role">Principal's Title/Role</label>
                        <input type="text" name="principal_title_role" id="principal_title_role" value="<?php echo htmlspecialchars($settings['principal_title_role']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="principal_qualifications">Principal's Qualifications</label>
                        <input type="text" name="principal_qualifications" id="principal_qualifications" value="<?php echo htmlspecialchars($settings['principal_qualifications']); ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="principal_image">Principal's Photo</label>
                        <input type="file" name="principal_image" id="principal_image" accept="image/*">
                        <?php if (!empty($settings['principal_image_url'])): ?>
                            <div class="current-image-container">
                                <p>Current Principal Photo:</p>
                                <img src="<?php echo htmlspecialchars($settings['principal_image_url']); ?>" alt="Current Principal Photo">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group full-width" style="margin-top: 30px;">
            <input type="submit" class="btn" value="Update About Settings">
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sectionHeaders = document.querySelectorAll('.section-header');

        sectionHeaders.forEach(header => {
            header.addEventListener('click', function() {
                const content = this.nextElementSibling; // The .section-content div
                const icon = this.querySelector('.toggle-icon');
                const isExpanded = this.getAttribute('aria-expanded') === 'true';

                // Toggle content visibility and class
                if (isExpanded) {
                    content.classList.remove('open');
                    icon.classList.remove('rotated');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    content.classList.add('open');
                    icon.classList.add('rotated');
                    this.setAttribute('aria-expanded', 'true');
                }
            });
        });
    });
</script>

<?php 
if($link) mysqli_close($link);
// Include admin footer for consistent layout (assumed to close main layout divs, </body>, and </html>)
require_once './admin_footer.php';
?>