<?php
// Start the session
session_start();

// Include configuration and Cloudinary handler
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php"; // Make sure this path is correct

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["admin_id"]; // Admin ID from session

// Initialize variables for form fields
$settings = [
    'hero_image_url' => '',
    'hero_title_prefix' => '',
    'hero_title_highlight' => '',
    'hero_title_suffix' => '',
    'hero_description' => '',
    'button1_text' => '',
    'button1_url' => '',
    'button2_text' => '',
    'button2_url' => '',
    'stat1_value' => '',
    'stat1_label' => '',
    'stat2_value' => '',
    'stat2_label' => '',
    'stat3_value' => '',
    'stat3_label' => '',
    'stat4_value' => '',
    'stat4_label' => ''
];
$errors = [];
$success_message = "";

// --- Fetch Current School Settings ---
$sql_fetch = "SELECT * FROM school_settings WHERE id = 1";
if ($result_fetch = mysqli_query($link, $sql_fetch)) {
    if (mysqli_num_rows($result_fetch) == 1) {
        $current_settings = mysqli_fetch_assoc($result_fetch);
        // Populate settings array with fetched data
        foreach ($settings as $key => $value) {
            if (isset($current_settings[$key])) {
                $settings[$key] = $current_settings[$key];
            }
        }
    } else {
        // This case should ideally not happen if initial data is inserted
        $errors[] = "School settings not found. Please ensure the initial settings are configured.";
    }
    mysqli_free_result($result_fetch);
} else {
    $errors[] = "Error fetching school settings: " . mysqli_error($link);
}

// --- Process Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors)) {

    // Sanitize and validate all text fields
    $settings['hero_title_prefix'] = trim($_POST['hero_title_prefix'] ?? '');
    $settings['hero_title_highlight'] = trim($_POST['hero_title_highlight'] ?? '');
    $settings['hero_title_suffix'] = trim($_POST['hero_title_suffix'] ?? '');
    $settings['hero_description'] = trim($_POST['hero_description'] ?? '');
    $settings['button1_text'] = trim($_POST['button1_text'] ?? '');
    $settings['button1_url'] = trim($_POST['button1_url'] ?? '');
    $settings['button2_text'] = trim($_POST['button2_text'] ?? '');
    $settings['button2_url'] = trim($_POST['button2_url'] ?? '');
    $settings['stat1_value'] = trim($_POST['stat1_value'] ?? '');
    $settings['stat1_label'] = trim($_POST['stat1_label'] ?? '');
    $settings['stat2_value'] = trim($_POST['stat2_value'] ?? '');
    $settings['stat2_label'] = trim($_POST['stat2_label'] ?? '');
    $settings['stat3_value'] = trim($_POST['stat3_value'] ?? '');
    $settings['stat3_label'] = trim($_POST['stat3_label'] ?? '');
    $settings['stat4_value'] = trim($_POST['stat4_value'] ?? '');
    $settings['stat4_label'] = trim($_POST['stat4_label'] ?? '');

    // --- Handle Hero Image Upload ---
    if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] == UPLOAD_ERR_OK) {
        $uploadResult = uploadToCloudinary($_FILES['hero_image'], 'school_hero_images'); // 'school_hero_images' is a Cloudinary folder
        if (isset($uploadResult['error'])) {
            $errors[] = "Hero Image Upload Failed: " . $uploadResult['error'];
        } else {
            $settings['hero_image_url'] = $uploadResult['secure_url'];
            // If you store public_id in the DB, you could delete the old image here.
            // (Assumes you have a 'hero_image_public_id' column in school_settings)
            // if (!empty($current_settings['hero_image_public_id'])) { 
            //     deleteFromCloudinary($current_settings['hero_image_public_id']); 
            // }
            // $settings['hero_image_public_id'] = $uploadResult['public_id']; // Store new public_id
        }
    }

    // Only proceed to update if no image upload errors occurred
    if (empty($errors)) {
        $sql_update = "UPDATE school_settings SET
            hero_image_url = ?,
            hero_title_prefix = ?,
            hero_title_highlight = ?,
            hero_title_suffix = ?,
            hero_description = ?,
            button1_text = ?,
            button1_url = ?,
            button2_text = ?,
            button2_url = ?,
            stat1_value = ?,
            stat1_label = ?,
            stat2_value = ?,
            stat2_label = ?,
            stat3_value = ?,
            stat3_label = ?,
            stat4_value = ?,
            stat4_label = ?,
            updated_by_admin_id = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = 1";

        if ($stmt = mysqli_prepare($link, $sql_update)) {
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssssssssssssi", // If you add public_id, add another 's'
                $settings['hero_image_url'],
                // $settings['hero_image_public_id'], // Add this if storing public_id
                $settings['hero_title_prefix'],
                $settings['hero_title_highlight'],
                $settings['hero_title_suffix'],
                $settings['hero_description'],
                $settings['button1_text'],
                $settings['button1_url'],
                $settings['button2_text'],
                $settings['button2_url'],
                $settings['stat1_value'],
                $settings['stat1_label'],
                $settings['stat2_value'],
                $settings['stat2_label'],
                $settings['stat3_value'],
                $settings['stat3_label'],
                $settings['stat4_value'],
                $settings['stat4_label'],
                $admin_id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "School settings updated successfully.";
                // Re-fetch to show the latest data, including new image URL
                $sql_fetch = "SELECT * FROM school_settings WHERE id = 1";
                $result_fetch = mysqli_query($link, $sql_fetch);
                $current_settings = mysqli_fetch_assoc($result_fetch);
                foreach ($settings as $key => $value) {
                    if (isset($current_settings[$key])) {
                        $settings[$key] = $current_settings[$key];
                    }
                }
                mysqli_free_result($result_fetch);

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
    <title>Manage School Settings</title>
    <!-- Google Fonts for professional typography -->
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Define the animation for gradients */
        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Define a subtle box-shadow animation for interactive elements */
        @keyframes shadowPulse {
            0% { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            50% { box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
            100% { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        }

        body {
            font-family: 'Open Sans', sans-serif; /* Clean sans-serif for body text */
            
            /* Animated gradient background with new palette */
            background: linear-gradient(-45deg, #2C3E50, #E7A33C, #A9CCE3, #F8F8F8);
            background-size: 400% 400%;
            animation: gradientAnimation 20s ease infinite; /* Slower, more elegant animation */
            color: #444; /* Soft dark grey for readability */
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: auto;
            margin-top: 100px; /* Adjust for header */
            margin-bottom: 50px;
            
            /* Warm white background with soft transparency */
            background: rgba(248, 248, 248, 0.95); /* #F8F8F8 with slight transparency */
            backdrop-filter: blur(8px); /* Frosted glass effect */
            -webkit-backdrop-filter: blur(8px); /* For Safari */
            padding: 40px; /* Increased padding */
            border-radius: 18px; /* Softer rounded corners */
            
            /* Elegant box shadow */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); /* Deeper, softer shadow */
            border: 1px solid rgba(255, 255, 255, 0.3); /* Subtle white border */
        }

        h2, h3 {
            font-family: 'Lora', serif; /* Elegant serif for headings */
            text-align: center;
            color: #1A2C5A; /* Deep blue for headings */
            font-weight: 700;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        h3 {
            font-size: 1.5em;
            margin-top: 40px;
            margin-bottom: 20px;
            border-bottom: 2px solid #E7A33C; /* Gold accent under sub-headings */
            display: inline-block;
            padding-bottom: 5px;
            margin-left: 50%; /* Center the border */
            transform: translateX(-50%); /* Center the border */
        }

        .form-group { margin-bottom: 22px; } /* Increased spacing between form groups */
        .form-group label {
            display: block;
            margin-bottom: 8px; /* More space for labels */
            font-weight: 600;
            color: #1A2C5A; /* Deep blue for labels */
            font-size: 0.95em;
        }
        .form-group input:not([type="file"]), .form-group textarea {
            width: 100%;
            padding: 14px; /* Larger input fields */
            border: 1px solid #C0D3EB; /* Softer border color */
            border-radius: 8px; /* More rounded inputs */
            box-sizing: border-box;
            background-color: #FFFFFF; /* Pure white input background */
            font-size: 1em;
            color: #333;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #E7A33C; /* Gold border on focus */
            box-shadow: 0 0 0 4px rgba(231, 163, 60, 0.2); /* Soft gold shadow on focus */
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px; /* Increased gap */
            margin-bottom: 20px;
        }
        .full-width { grid-column: 1 / -1; }

        .btn {
            display: block;
            width: 100%;
            padding: 16px; /* Larger button */
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 19px; /* Slightly larger font */
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            
            /* Button's animated gradient with new palette */
            background: linear-gradient(45deg, #c3c7d1ff, #2C3E50);
            background-size: 200% 200%;
            animation: gradientAnimation 6s ease infinite;
            
            transition: transform 0.3s ease, box-shadow 0.3s ease, background-position 0.3s ease;
            box-shadow: 0 6px 20px rgba(26, 44, 90, 0.3); /* Deep blue shadow */
        }
        .btn:hover {
            transform: translateY(-3px) scale(1.01); /* Lift and slightly enlarge */
            background-position: 100% 0%; /* Shift gradient on hover */
            box-shadow: 0 10px 25px rgba(26, 44, 90, 0.4); /* Enhanced shadow on hover */
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
            border-radius: 10px; /* Softer rounded corners for image */
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .current-image-container {
            margin-top: 20px;
            text-align: center;
            background-color: rgba(255, 255, 255, 0.7); /* Light background for image container */
            padding: 15px;
            border-radius: 10px;
            border: 1px dashed #A9CCE3; /* Dashed border for visual interest */
        }
        .current-image-container p {
            font-weight: 600;
            color: #1A2C5A;
            margin-bottom: 10px;
            font-size: 0.9em;
        }
        .current-image-container img {
            max-width: 350px; /* Slightly larger preview */
            max-height: 250px;
            border: 2px solid #E7A33C; /* Gold border for image */
            padding: 5px;
            background-color: #fff;
            object-fit: contain; /* Ensures image fits without cropping */
        }

        /* Responsive adjustment for smaller screens */
        @media (max-width: 768px) {
            .container {
                padding: 25px;
                margin-top: 80px;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            h2, h3 {
                font-size: 1.8em;
                margin-bottom: 20px;
            }
            h3 {
                font-size: 1.3em;
            }
            .btn {
                font-size: 1em;
                padding: 14px;
            }
            .current-image-container img {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Manage School Homepage Settings</h2>

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
        
        <div class="form-grid">
            <div class="form-group full-width">
                <label for="hero_image">Hero Image (Background of Hero Section)</label>
                <input type="file" name="hero_image" id="hero_image" accept="image/*">
                <?php if (!empty($settings['hero_image_url'])): ?>
                    <div class="current-image-container">
                        <p>Current Hero Image:</p>
                        <img src="<?php echo htmlspecialchars($settings['hero_image_url']); ?>" alt="Current Hero Image">
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="hero_title_prefix">Hero Title Prefix</label>
                <input type="text" name="hero_title_prefix" id="hero_title_prefix" value="<?php echo htmlspecialchars($settings['hero_title_prefix']); ?>">
            </div>
            <div class="form-group">
                <label for="hero_title_highlight">Hero Title Highlight</label>
                <input type="text" name="hero_title_highlight" id="hero_title_highlight" value="<?php echo htmlspecialchars($settings['hero_title_highlight']); ?>">
            </div>
            <div class="form-group">
                <label for="hero_title_suffix">Hero Title Suffix</label>
                <input type="text" name="hero_title_suffix" id="hero_title_suffix" value="<?php echo htmlspecialchars($settings['hero_title_suffix']); ?>">
            </div>
            <div class="form-group full-width">
                <label for="hero_description">Hero Description</label>
                <textarea name="hero_description" id="hero_description" rows="4"><?php echo htmlspecialchars($settings['hero_description']); ?></textarea>
            </div>

            <h3 class="full-width">Call-to-Action Buttons</h3>
            <div class="form-group">
                <label for="button1_text">Button 1 Text</label>
                <input type="text" name="button1_text" id="button1_text" value="<?php echo htmlspecialchars($settings['button1_text']); ?>">
            </div>
            <div class="form-group">
                <label for="button1_url">Button 1 URL</label>
                <input type="text" name="button1_url" id="button1_url" value="<?php echo htmlspecialchars($settings['button1_url']); ?>">
            </div>
            <div class="form-group">
                <label for="button2_text">Button 2 Text</label>
                <input type="text" name="button2_text" id="button2_text" value="<?php echo htmlspecialchars($settings['button2_text']); ?>">
            </div>
            <div class="form-group">
                <label for="button2_url">Button 2 URL</label>
                <input type="text" name="button2_url" id="button2_url" value="<?php echo htmlspecialchars($settings['button2_url']); ?>">
            </div>

            <h3 class="full-width">Statistics Section</h3>
            <div class="form-group">
                <label for="stat1_value">Statistic 1 Value</label>
                <input type="text" name="stat1_value" id="stat1_value" value="<?php echo htmlspecialchars($settings['stat1_value']); ?>">
            </div>
            <div class="form-group">
                <label for="stat1_label">Statistic 1 Label</label>
                <input type="text" name="stat1_label" id="stat1_label" value="<?php echo htmlspecialchars($settings['stat1_label']); ?>">
            </div>
            <div class="form-group">
                <label for="stat2_value">Statistic 2 Value</label>
                <input type="text" name="stat2_value" id="stat2_value" value="<?php echo htmlspecialchars($settings['stat2_value']); ?>">
            </div>
            <div class="form-group">
                <label for="stat2_label">Statistic 2 Label</label>
                <input type="text" name="stat2_label" id="stat2_label" value="<?php echo htmlspecialchars($settings['stat2_label']); ?>">
            </div>
            <div class="form-group">
                <label for="stat3_value">Statistic 3 Value</label>
                <input type="text" name="stat3_value" id="stat3_value" value="<?php echo htmlspecialchars($settings['stat3_value']); ?>">
            </div>
            <div class="form-group">
                <label for="stat3_label">Statistic 3 Label</label>
                <input type="text" name="stat3_label" id="stat3_label" value="<?php echo htmlspecialchars($settings['stat3_label']); ?>">
            </div>
            <div class="form-group">
                <label for="stat4_value">Statistic 4 Value</label>
                <input type="text" name="stat4_value" id="stat4_value" value="<?php echo htmlspecialchars($settings['stat4_value']); ?>">
            </div>
            <div class="form-group">
                <label for="stat4_label">Statistic 4 Label</label>
                <input type="text" name="stat4_label" id="stat4_label" value="<?php echo htmlspecialchars($settings['stat4_label']); ?>">
            </div>
        </div>

        <div class="form-group full-width" style="margin-top: 30px;">
            <input type="submit" class="btn" value="Update Settings">
        </div>
    </form>
</div>

</body>
</html>
<?php 
if($link) mysqli_close($link);
// Include admin footer for consistent layout
require_once './admin_footer.php';
?>