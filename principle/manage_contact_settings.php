<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"]; // Primary key ID of the logged-in principal
$principal_name = $_SESSION["full_name"];
$principal_role = $_SESSION["role"];

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Helper for setting messages ---
function set_session_message($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

// --- Fetch existing settings or initialize default ---
$settings = null;
$sql_fetch_settings = "SELECT * FROM contact_settings WHERE id = 1";
if ($result = mysqli_query($link, $sql_fetch_settings)) {
    $settings = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
}

// If no settings exist, provide defaults (should be handled by ON DUPLICATE KEY UPDATE in schema insertion)
if (!$settings) {
    // This block should ideally not be hit if initial data is inserted on schema creation
    $settings = [
        'id' => 1,
        'location_address' => 'School Address Line 1, City, State, Pincode',
        'location_map_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15277.561490212004!2d78.36394595!3d17.433965999999998!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bcbd829f0000001%3A0x861c1e0e1e0e1e0e!2sMednova%20Hospital%20and%20Medical%20Centre!5e0!3m2!1sen!2sin!4v1700000000000!5m2!1sen!2sin',
        'phone_general' => '+1 (555) 123-4567',
        'phone_admissions' => '+1 (555) 123-4568',
        'email_general' => 'info@school.com',
        'email_admissions' => 'admissions@school.com',
        'office_hours_weekdays' => 'Monday - Friday: 8:00 AM - 4:00 PM',
        'office_hours_saturday' => 'Saturday: 9:00 AM - 1:00 PM (Admissions Only)',
        'office_hours_sunday' => 'Sunday: Closed',
        'updated_by_admin_id' => NULL,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}


// --- Process Form Submission (Update Settings) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['form_action']) && $_POST['form_action'] == 'update_contact_settings') {
        // Sanitize and validate inputs
        $location_address = trim($_POST['location_address']);
        $location_map_url = trim($_POST['location_map_url']);
        $phone_general = trim($_POST['phone_general']);
        $phone_admissions = trim($_POST['phone_admissions']);
        $email_general = trim($_POST['email_general']);
        $email_admissions = trim($_POST['email_admissions']);
        $office_hours_weekdays = trim($_POST['office_hours_weekdays']);
        $office_hours_saturday = trim($_POST['office_hours_saturday']);
        $office_hours_sunday = trim($_POST['office_hours_sunday']);
        $updated_by_admin_id = $principal_id; // Store principal's ID as updater

        // Basic validation
        if (empty($location_address) || empty($phone_general) || empty($email_general)) {
            set_session_message("Address, General Phone, and General Email are required.", "danger");
            header("location: manage_contact_settings.php");
            exit;
        }
        if (!empty($email_general) && !filter_var($email_general, FILTER_VALIDATE_EMAIL)) {
            set_session_message("Invalid format for General Email.", "danger");
            header("location: manage_contact_settings.php");
            exit;
        }
        if (!empty($email_admissions) && !filter_var($email_admissions, FILTER_VALIDATE_EMAIL)) {
            set_session_message("Invalid format for Admissions Email.", "danger");
            header("location: manage_contact_settings.php");
            exit;
        }
        // Basic URL validation for map link
        if (!empty($location_map_url) && !filter_var($location_map_url, FILTER_VALIDATE_URL)) {
             set_session_message("Invalid format for Google Map URL.", "danger");
            header("location: manage_contact_settings.php");
            exit;
        }


        $sql = "UPDATE contact_settings SET
                    location_address = ?,
                    location_map_url = ?,
                    phone_general = ?,
                    phone_admissions = ?,
                    email_general = ?,
                    email_admissions = ?,
                    office_hours_weekdays = ?,
                    office_hours_saturday = ?,
                    office_hours_sunday = ?,
                    updated_by_admin_id = ?,
                    updated_at = NOW()
                WHERE id = 1";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssssi",
                $location_address, $location_map_url, $phone_general, $phone_admissions,
                $email_general, $email_admissions, $office_hours_weekdays,
                $office_hours_saturday, $office_hours_sunday, $updated_by_admin_id
            );

            if (mysqli_stmt_execute($stmt)) {
                set_session_message("Contact settings updated successfully.", "success");
            } else {
                set_session_message("Error updating contact settings: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        } else {
            set_session_message("Failed to prepare update statement: " . mysqli_error($link), "danger");
        }
    }
    header("location: manage_contact_settings.php");
    exit;
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
    <title>Manage Contact Settings - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #F0FFFF, #AFEEEE, #B0E0E6, #ADD8E6); /* Cool, light blue gradient */
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
            max-width: 900px;
            margin: 20px auto;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.95); /* Slightly transparent white */
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        h2 {
            color: #008B8B; /* Dark Cyan */
            margin-bottom: 30px;
            border-bottom: 2px solid #AFEEEE;
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


        /* Form Section */
        .form-section {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 40px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .form-section h3 {
            color: #008B8B;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="url"],
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        .btn-submit {
            background-color: #008B8B; /* Dark Cyan */
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-submit:hover {
            background-color: #006060;
            transform: translateY(-2px);
        }

        /* Google Map Preview */
        .map-preview {
            width: 100%;
            height: 300px; /* Fixed height for preview */
            border: 1px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .map-preview iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="container">
        <h2><i class="fas fa-address-card"></i> Manage Contact Settings</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo ($message_type == 'success' ? 'check-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <form action="manage_contact_settings.php" method="POST">
                <input type="hidden" name="form_action" value="update_contact_settings">

                <h3><i class="fas fa-map-marker-alt"></i> Location Information</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="location_address">School Address:</label>
                        <textarea id="location_address" name="location_address" rows="4" required placeholder="Full physical address of the school"><?php echo htmlspecialchars($settings['location_address']); ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="location_map_url">Google Map Embed URL:</label>
                        <input type="url" id="location_map_url" name="location_map_url" value="<?php echo htmlspecialchars($settings['location_map_url'] ?: ''); ?>" placeholder="Paste Google Map embed code URL here">
                        <?php if (!empty($settings['location_map_url'])): ?>
                            <div class="map-preview">
                                <iframe src="<?php echo htmlspecialchars($settings['location_map_url']); ?>" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <hr style="margin: 30px 0;">

                <h3><i class="fas fa-phone-alt"></i> Contact Numbers</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="phone_general">General Inquiries Phone:</label>
                        <input type="text" id="phone_general" name="phone_general" value="<?php echo htmlspecialchars($settings['phone_general']); ?>" required placeholder="e.g., +1 (555) 123-4567">
                    </div>
                    <div class="form-group">
                        <label for="phone_admissions">Admissions Office Phone (Optional):</label>
                        <input type="text" id="phone_admissions" name="phone_admissions" value="<?php echo htmlspecialchars($settings['phone_admissions'] ?: ''); ?>" placeholder="e.g., +1 (555) 123-4568">
                    </div>
                </div>

                <hr style="margin: 30px 0;">

                <h3><i class="fas fa-envelope"></i> Email Addresses</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="email_general">General Inquiries Email:</label>
                        <input type="email" id="email_general" name="email_general" value="<?php echo htmlspecialchars($settings['email_general']); ?>" required placeholder="e.g., info@school.com">
                    </div>
                    <div class="form-group">
                        <label for="email_admissions">Admissions Office Email (Optional):</label>
                        <input type="email" id="email_admissions" name="email_admissions" value="<?php echo htmlspecialchars($settings['email_admissions'] ?: ''); ?>" placeholder="e.g., admissions@school.com">
                    </div>
                </div>

                <hr style="margin: 30px 0;">

                <h3><i class="fas fa-clock"></i> Office Hours</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="office_hours_weekdays">Weekdays (Mon-Fri):</label>
                        <input type="text" id="office_hours_weekdays" name="office_hours_weekdays" value="<?php echo htmlspecialchars($settings['office_hours_weekdays'] ?: ''); ?>" placeholder="e.g., Monday - Friday: 8:00 AM - 4:00 PM">
                    </div>
                    <div class="form-group">
                        <label for="office_hours_saturday">Saturday:</label>
                        <input type="text" id="office_hours_saturday" name="office_hours_saturday" value="<?php echo htmlspecialchars($settings['office_hours_saturday'] ?: ''); ?>" placeholder="e.g., Saturday: 9:00 AM - 1:00 PM (Admissions Only)">
                    </div>
                    <div class="form-group">
                        <label for="office_hours_sunday">Sunday:</label>
                        <input type="text" id="office_hours_sunday" name="office_hours_sunday" value="<?php echo htmlspecialchars($settings['office_hours_sunday'] ?: ''); ?>" placeholder="e.g., Sunday: Closed">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save All Contact Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // No dynamic elements or special JS for this page beyond general styling.
    });
</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>