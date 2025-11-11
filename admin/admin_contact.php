<?php
// Start the session
session_start();

// Include database config
require_once "../database/config.php";

// Authentication Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["id"] ?? 0;
$errors = [];
$success_message = "";
$settings = [];

// --- Process Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and prepare data from POST
    $fields_to_update = [
        'location_address', 'location_map_url', 'phone_general', 'phone_admissions',
        'email_general', 'email_admissions', 'office_hours_weekdays', 'office_hours_saturday', 'office_hours_sunday'
    ];
    
    $update_data = [];
    foreach ($fields_to_update as $field) {
        $update_data[$field] = trim($_POST[$field] ?? '');
    }

    // SQL for INSERT ... ON DUPLICATE KEY UPDATE
    $sql = "INSERT INTO contact_settings (id, location_address, location_map_url, phone_general, phone_admissions, email_general, email_admissions, office_hours_weekdays, office_hours_saturday, office_hours_sunday, updated_by_admin_id) 
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            location_address = VALUES(location_address), location_map_url = VALUES(location_map_url), 
            phone_general = VALUES(phone_general), phone_admissions = VALUES(phone_admissions), 
            email_general = VALUES(email_general), email_admissions = VALUES(email_admissions), 
            office_hours_weekdays = VALUES(office_hours_weekdays), office_hours_saturday = VALUES(office_hours_saturday), 
            office_hours_sunday = VALUES(office_hours_sunday), updated_by_admin_id = VALUES(updated_by_admin_id)";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssssssssi",
            $update_data['location_address'], $update_data['location_map_url'],
            $update_data['phone_general'], $update_data['phone_admissions'],
            $update_data['email_general'], $update_data['email_admissions'],
            $update_data['office_hours_weekdays'], $update_data['office_hours_saturday'], $update_data['office_hours_sunday'],
            $admin_id
        );

        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Contact settings updated successfully.";
        } else {
            $errors[] = "Failed to update settings. Please try again.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $errors[] = "Database error. Could not prepare statement.";
    }
}

// --- Fetch Current Settings for Display ---
$sql_fetch = "SELECT * FROM contact_settings WHERE id = 1";
if ($result = mysqli_query($link, $sql_fetch)) {
    if (mysqli_num_rows($result) > 0) {
        $settings = mysqli_fetch_assoc($result);
    }
}

require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Contact Page Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db; --bg-color: #f4f7f9; --card-bg: #ffffff;
            --text-dark: #2c3e50; --border-color: #e1e8ed; --shadow: 0 6px 15px rgba(0,0,0,0.07);
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-color); }
        .container { max-width: 900px; margin: 2rem auto; background: var(--card-bg); border-radius: 12px; box-shadow: var(--shadow); padding: 2.5rem; }
        h2, h3 { font-family: 'Playfair Display', serif; color: var(--text-dark); text-align: center; }
        h2 { font-size: 2rem; margin-bottom: 2rem; }
        h3 { font-size: 1.4rem; margin-top: 2rem; margin-bottom: 1.5rem; text-align: left; border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .full-width { grid-column: 1 / -1; }
        label { font-weight: 600; display: block; margin-bottom: 0.5rem; }
        input, textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 1rem; color: #fff; background-color: var(--primary-color); border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 600; margin-top: 2rem; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; font-weight: 500; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        .alert-success { background-color: #d4edda; color: #155724; }
    </style>
</head>
<body>
<div class="container">
    <h2>Manage Contact Page Settings</h2>

    <?php if(!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $error) echo htmlspecialchars($error)."<br>"; ?></div><?php endif; ?>
    <?php if($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        
        <h3>Location Details</h3>
        <div class="form-group full-width">
            <label for="location_address">Full Address</label>
            <textarea id="location_address" name="location_address" rows="3"><?php echo htmlspecialchars($settings['location_address'] ?? ''); ?></textarea>
        </div>
        <div class="form-group full-width">
            <label for="location_map_url">Google Maps Embed URL</label>
            <input type="url" id="location_map_url" name="location_map_url" value="<?php echo htmlspecialchars($settings['location_map_url'] ?? ''); ?>">
        </div>

        <h3>Phone & Email</h3>
        <div class="form-grid">
            <div class="form-group"><label for="phone_general">General Phone</label><input type="text" id="phone_general" name="phone_general" value="<?php echo htmlspecialchars($settings['phone_general'] ?? ''); ?>"></div>
            <div class="form-group"><label for="phone_admissions">Admissions Phone</label><input type="text" id="phone_admissions" name="phone_admissions" value="<?php echo htmlspecialchars($settings['phone_admissions'] ?? ''); ?>"></div>
            <div class="form-group"><label for="email_general">General Email</label><input type="email" id="email_general" name="email_general" value="<?php echo htmlspecialchars($settings['email_general'] ?? ''); ?>"></div>
            <div class="form-group"><label for="email_admissions">Admissions Email</label><input type="email" id="email_admissions" name="email_admissions" value="<?php echo htmlspecialchars($settings['email_admissions'] ?? ''); ?>"></div>
        </div>

        <h3>Office Hours</h3>
        <div class="form-grid">
            <div class="form-group"><label for="office_hours_weekdays">Weekdays</label><input type="text" id="office_hours_weekdays" name="office_hours_weekdays" value="<?php echo htmlspecialchars($settings['office_hours_weekdays'] ?? ''); ?>"></div>
            <div class="form-group"><label for="office_hours_saturday">Saturday</label><input type="text" id="office_hours_saturday" name="office_hours_saturday" value="<?php echo htmlspecialchars($settings['office_hours_saturday'] ?? ''); ?>"></div>
            <div class="form-group full-width"><label for="office_hours_sunday">Sunday</label><input type="text" id="office_hours_sunday" name="office_hours_sunday" value="<?php echo htmlspecialchars($settings['office_hours_sunday'] ?? ''); ?>"></div>
        </div>
        
        <button type="submit" class="btn">Update Contact Settings</button>
    </form>
</div>
</body>
</html>