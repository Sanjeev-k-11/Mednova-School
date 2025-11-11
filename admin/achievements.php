<?php
// Start the session
session_start();

// Include configuration and Cloudinary handler
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php"; // Include Cloudinary handler if you plan to use it for images later

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["admin_id"]; // Admin ID from session

// --- Initialize variables for form data and errors ---
$achievements_main_title = $achievements_subtitle = $toppers_section_title = $toppers_json = $awards_section_title = $awards_subtitle = $awards_json = $contact_section_title = $contact_subtitle = $contact_address = $contact_phone = $contact_email = "";
$achievements_main_title_err = $achievements_subtitle_err = $toppers_section_title_err = $toppers_json_err = $awards_section_title_err = $awards_subtitle_err = $awards_json_err = $contact_section_title_err = $contact_subtitle_err = $contact_address_err = $contact_phone_err = $contact_email_err = "";

// --- Handle form submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate and sanitize inputs
    if (empty(trim($_POST["achievements_main_title"]))) {
        $achievements_main_title_err = "Please enter the main achievements title.";
    } else {
        $achievements_main_title = trim($_POST["achievements_main_title"]);
    }

    $achievements_subtitle = trim($_POST["achievements_subtitle"]); // Subtitle can be optional

    if (empty(trim($_POST["toppers_section_title"]))) {
        $toppers_section_title_err = "Please enter the toppers section title.";
    } else {
        $toppers_section_title = trim($_POST["toppers_section_title"]);
    }

    // Toppers JSON validation (basic)
    $toppers_json_input = trim($_POST["toppers_json_hidden"]); // Changed name to hidden field
    if (empty($toppers_json_input)) {
        // Not strictly an error if there are no toppers, but the prompt implies it should be validated.
        // Let's make it optional if the user enters nothing, but validate if they do.
        $toppers_json = "[]"; // Default to empty array if nothing is provided
    } elseif (!json_decode($toppers_json_input)) {
        $toppers_json_err = "Invalid JSON format for toppers data.";
    } else {
        $toppers_json = $toppers_json_input;
    }

    if (empty(trim($_POST["awards_section_title"]))) {
        $awards_section_title_err = "Please enter the awards section title.";
    } else {
        $awards_section_title = trim($_POST["awards_section_title"]);
    }

    $awards_subtitle = trim($_POST["awards_subtitle"]); // Subtitle can be optional

    // Awards JSON validation (basic)
    $awards_json_input = trim($_POST["awards_json_hidden"]); // Changed name to hidden field
    if (empty($awards_json_input)) {
        // Default to empty array if nothing is provided
        $awards_json = "[]";
    } elseif (!json_decode($awards_json_input)) {
        $awards_json_err = "Invalid JSON format for awards data.";
    } else {
        $awards_json = $awards_json_input;
    }

    if (empty(trim($_POST["contact_section_title"]))) {
        $contact_section_title_err = "Please enter the contact section title.";
    } else {
        $contact_section_title = trim($_POST["contact_section_title"]);
    }

    $contact_subtitle = trim($_POST["contact_subtitle"]); // Subtitle can be optional

    if (empty(trim($_POST["contact_address"]))) {
        $contact_address_err = "Please enter the contact address.";
    } else {
        $contact_address = trim($_POST["contact_address"]);
    }

    if (empty(trim($_POST["contact_phone"]))) {
        $contact_phone_err = "Please enter the contact phone number.";
    } else {
        $contact_phone = trim($_POST["contact_phone"]);
    }

    if (empty(trim($_POST["contact_email"]))) {
        $contact_email_err = "Please enter the contact email.";
    } elseif (!filter_var(trim($_POST["contact_email"]), FILTER_VALIDATE_EMAIL)) {
        $contact_email_err = "Invalid email format.";
    } else {
        $contact_email = trim($_POST["contact_email"]);
    }


    // --- Check for errors before inserting/updating data ---
    if (empty($achievements_main_title_err) && empty($toppers_section_title_err) && empty($toppers_json_err) && empty($awards_section_title_err) && empty($awards_json_err) && empty($contact_section_title_err) && empty($contact_address_err) && empty($contact_phone_err) && empty($contact_email_err)) {

        // Check if a record already exists
        $sql = "SELECT id FROM achievements_contact_settings WHERE id = 1"; // Assuming a single record with ID 1
        $result = mysqli_query($link, $sql);

        if (mysqli_num_rows($result) > 0) {
            // A record exists, so UPDATE
            $sql = "UPDATE achievements_contact_settings SET
                        achievements_main_title = ?,
                        achievements_subtitle = ?,
                        toppers_section_title = ?,
                        toppers_json = ?,
                        awards_section_title = ?,
                        awards_subtitle = ?,
                        awards_json = ?,
                        contact_section_title = ?,
                        contact_subtitle = ?,
                        contact_address = ?,
                        contact_phone = ?,
                        contact_email = ?,
                        updated_by_admin_id = ?,
                        updated_at = NOW()
                    WHERE id = 1";

            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssssssssssi", $param_achievements_main_title, $param_achievements_subtitle, $param_toppers_section_title, $param_toppers_json, $param_awards_section_title, $param_awards_subtitle, $param_awards_json, $param_contact_section_title, $param_contact_subtitle, $param_contact_address, $param_contact_phone, $param_contact_email, $param_updated_by_admin_id);

                $param_achievements_main_title = $achievements_main_title;
                $param_achievements_subtitle = $achievements_subtitle;
                $param_toppers_section_title = $toppers_section_title;
                $param_toppers_json = $toppers_json;
                $param_awards_section_title = $awards_section_title;
                $param_awards_subtitle = $awards_subtitle;
                $param_awards_json = $awards_json;
                $param_contact_section_title = $contact_section_title;
                $param_contact_subtitle = $contact_subtitle;
                $param_contact_address = $contact_address;
                $param_contact_phone = $contact_phone;
                $param_contact_email = $contact_email;
                $param_updated_by_admin_id = $admin_id;

                if (mysqli_stmt_execute($stmt)) {
                    // Success, redirect or show a success message
                    header("location: achievements.php?status=success");
                    exit;
                } else {
                    echo "Something went wrong. Please try again later.";
                }

                mysqli_stmt_close($stmt);
            }
        } else {
            // No record exists, so INSERT
            $sql = "INSERT INTO achievements_contact_settings (
                        achievements_main_title,
                        achievements_subtitle,
                        toppers_section_title,
                        toppers_json,
                        awards_section_title,
                        awards_subtitle,
                        awards_json,
                        contact_section_title,
                        contact_subtitle,
                        contact_address,
                        contact_phone,
                        contact_email,
                        updated_by_admin_id,
                        created_at,
                        updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssssssssssi", $param_achievements_main_title, $param_achievements_subtitle, $param_toppers_section_title, $param_toppers_json, $param_awards_section_title, $param_awards_subtitle, $param_awards_json, $param_contact_section_title, $param_contact_subtitle, $param_contact_address, $param_contact_phone, $param_contact_email, $param_updated_by_admin_id);

                $param_achievements_main_title = $achievements_main_title;
                $param_achievements_subtitle = $achievements_subtitle;
                $param_toppers_section_title = $toppers_section_title;
                $param_toppers_json = $toppers_json;
                $param_awards_section_title = $awards_section_title;
                $param_awards_subtitle = $awards_subtitle;
                $param_awards_json = $awards_json;
                $param_contact_section_title = $contact_section_title;
                $param_contact_subtitle = $contact_subtitle;
                $param_contact_address = $contact_address;
                $param_contact_phone = $contact_phone;
                $param_contact_email = $contact_email;
                $param_updated_by_admin_id = $admin_id;

                if (mysqli_stmt_execute($stmt)) {
                    // Success, redirect or show a success message
                    header("location: achievements.php?status=success");
                    exit;
                } else {
                    echo "Something went wrong. Please try again later.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}

// --- Fetch existing data to pre-fill the form for editing ---
$sql_fetch = "SELECT * FROM achievements_contact_settings WHERE id = 1"; // Fetch the single record
$result_fetch = mysqli_query($link, $sql_fetch);
$row = mysqli_fetch_assoc($result_fetch);

// Close connection
mysqli_close($link);

// Include admin header
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Achievements & Contact Settings</title>
    <!-- Your existing style.css -->
    <link rel="stylesheet" href="../css/style.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* General Body and Container */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 25px;
            font-size: 2em;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            display: block;
        }

        p {
            text-align: center;
            margin-bottom: 30px;
            color: #555;
            font-size: 1.1em;
        }

        /* Form Elements */
        form fieldset {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            background-color: #fdfdfd;
            transition: all 0.3s ease;
        }

        form fieldset:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        form legend {
            font-size: 1.4em;
            font-weight: bold;
            color: #3498db;
            padding: 0 10px;
            background-color: #fff;
            border-radius: 5px;
            margin-bottom: 15px; /* Added spacing */
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #444;
            font-size: 0.95em;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box; /* Ensures padding doesn't expand width */
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.2);
            outline: none;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 90px;
        }

        .help-block {
            color: #e74c3c;
            font-size: 0.85em;
            margin-top: 5px;
            display: block;
        }

        /* Buttons */
        .btn {
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease, color 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
            border: 1px solid #3498db;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .btn-default {
            background-color: #ecf0f1;
            color: #333;
            border: 1px solid #ccc;
        }

        .btn-default:hover {
            background-color: #bdc3c7;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
            border: 1px solid #e74c3c;
            margin-left: 10px;
            vertical-align: top; /* Align with input fields */
        }

        .btn-danger:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }

        .btn-success {
            background-color: #2ecc71;
            color: white;
            border: 1px solid #2ecc71;
            margin-top: 10px;
        }

        .btn-success:hover {
            background-color: #27ae60;
            border-color: #27ae60;
        }

        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 1em;
            text-align: center;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Dynamic JSON Field Styling */
        .dynamic-field-group {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #fafafa;
            display: flex; /* Use flexbox for layout */
            gap: 10px; /* Space between items */
            align-items: flex-end; /* Align items to the bottom */
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }

        .dynamic-field-group .form-group {
            flex: 1; /* Allow form groups to grow */
            min-width: 150px; /* Minimum width before wrapping */
            margin-bottom: 0; /* Remove margin-bottom as gap handles spacing */
        }

        .dynamic-field-group .form-control {
            margin-bottom: 0; /* No bottom margin on inputs within dynamic group */
        }

        .dynamic-field-group .remove-btn {
            align-self: flex-end; /* Align button to the bottom */
            margin-bottom: 0; /* No bottom margin */
        }

        .add-item-btn {
            margin-top: 15px;
        }

        .dynamic-field-container {
            margin-top: 10px;
            border: 1px dashed #ccc;
            padding: 15px;
            border-radius: 5px;
            background-color: #fcfcfc;
        }
        .dynamic-field-container p {
            font-size: 0.9em;
            color: #777;
            text-align: left;
            margin-bottom: 15px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                margin: 20px;
                padding: 20px;
            }
            .dynamic-field-group {
                flex-direction: column; /* Stack items vertically on small screens */
                align-items: stretch; /* Stretch items to full width */
            }
            .dynamic-field-group .form-group {
                min-width: unset; /* Remove min-width constraint */
            }
            .dynamic-field-group .remove-btn {
                 width: 100%; /* Full width button */
                 margin-top: 10px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Manage Achievements & Contact Settings</h2>
        <p>This page allows you to update the content for the achievements, toppers, awards, and contact sections of the website.</p>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'success') : ?>
            <div class="alert success">Settings updated successfully!</div>
        <?php endif; ?>
        <?php if (!empty($achievements_main_title_err) || !empty($toppers_section_title_err) || !empty($toppers_json_err) || !empty($awards_section_title_err) || !empty($awards_json_err) || !empty($contact_section_title_err) || !empty($contact_address_err) || !empty($contact_phone_err) || !empty($contact_email_err)) : ?>
            <div class="alert error">Please correct the errors below.</div>
        <?php endif; ?>


        <form id="settingsForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <fieldset>
                <legend>Achievements Section</legend>
                <div class="form-group">
                    <label for="achievements_main_title">Main Title</label>
                    <input type="text" id="achievements_main_title" name="achievements_main_title" class="form-control <?php echo (!empty($achievements_main_title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($row['achievements_main_title'] ?? ''); ?>">
                    <span class="help-block"><?php echo $achievements_main_title_err; ?></span>
                </div>
                <div class="form-group">
                    <label for="achievements_subtitle">Subtitle</label>
                    <input type="text" id="achievements_subtitle" name="achievements_subtitle" class="form-control" value="<?php echo htmlspecialchars($row['achievements_subtitle'] ?? ''); ?>">
                </div>
            </fieldset>

            <fieldset>
                <legend>Toppers Section</legend>
                <div class="form-group">
                    <label for="toppers_section_title">Section Title</label>
                    <input type="text" id="toppers_section_title" name="toppers_section_title" class="form-control <?php echo (!empty($toppers_section_title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($row['toppers_section_title'] ?? ''); ?>">
                    <span class="help-block"><?php echo $toppers_section_title_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Toppers Data</label>
                    <div id="toppers-container" class="dynamic-field-container">
                        <p>Add students who achieved top positions or significant milestones.</p>
                        <!-- Dynamic Toppers fields will be added here by JavaScript -->
                    </div>
                    <button type="button" id="add-topper-btn" class="btn btn-success add-item-btn"><i class="fas fa-plus"></i> Add Topper</button>
                    <input type="hidden" name="toppers_json_hidden" id="toppers_json_hidden" value='<?php echo htmlspecialchars($row['toppers_json'] ?? '[]'); ?>'>
                    <span class="help-block"><?php echo $toppers_json_err; ?></span>
                </div>
            </fieldset>

            <fieldset>
                <legend>Awards & Recognition Section</legend>
                <div class="form-group">
                    <label for="awards_section_title">Section Title</label>
                    <input type="text" id="awards_section_title" name="awards_section_title" class="form-control <?php echo (!empty($awards_section_title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($row['awards_section_title'] ?? ''); ?>">
                    <span class="help-block"><?php echo $awards_section_title_err; ?></span>
                </div>
                <div class="form-group">
                    <label for="awards_subtitle">Subtitle</label>
                    <input type="text" id="awards_subtitle" name="awards_subtitle" class="form-control" value="<?php echo htmlspecialchars($row['awards_subtitle'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Awards Data</label>
                    <div id="awards-container" class="dynamic-field-container">
                        <p>List important awards and recognitions received by the institution or students.</p>
                        <!-- Dynamic Awards fields will be added here by JavaScript -->
                    </div>
                    <button type="button" id="add-award-btn" class="btn btn-success add-item-btn"><i class="fas fa-plus"></i> Add Award</button>
                    <input type="hidden" name="awards_json_hidden" id="awards_json_hidden" value='<?php echo htmlspecialchars($row['awards_json'] ?? '[]'); ?>'>
                    <span class="help-block"><?php echo $awards_json_err; ?></span>
                </div>
            </fieldset>

            <fieldset>
                <legend>Contact Information</legend>
                <div class="form-group">
                    <label for="contact_section_title">Section Title</label>
                    <input type="text" id="contact_section_title" name="contact_section_title" class="form-control <?php echo (!empty($contact_section_title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($row['contact_section_title'] ?? ''); ?>">
                    <span class="help-block"><?php echo $contact_section_title_err; ?></span>
                </div>
                <div class="form-group">
                    <label for="contact_subtitle">Subtitle</label>
                    <input type="text" id="contact_subtitle" name="contact_subtitle" class="form-control" value="<?php echo htmlspecialchars($row['contact_subtitle'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="contact_address">Address</label>
                    <input type="text" id="contact_address" name="contact_address" class="form-control <?php echo (!empty($contact_address_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($row['contact_address'] ?? ''); ?>">
                    <span class="help-block"><?php echo $contact_address_err; ?></span>
                </div>
                <div class="form-group">
                    <label for="contact_phone">Phone Number</label>
                    <input type="text" id="contact_phone" name="contact_phone" class="form-control <?php echo (!empty($contact_phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($row['contact_phone'] ?? ''); ?>">
                    <span class="help-block"><?php echo $contact_phone_err; ?></span>
                </div>
                <div class="form-group">
                    <label for="contact_email">Email Address</label>
                    <input type="email" id="contact_email" name="contact_email" class="form-control <?php echo (!empty($contact_email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($row['contact_email'] ?? ''); ?>">
                    <span class="help-block"><?php echo $contact_email_err; ?></span>
                </div>
            </fieldset>

            <div class="form-group text-center">
                <input type="submit" class="btn btn-primary" value="Save Settings">
                <a href="./dashboard.php" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Toppers Dynamic Fields ---
            const toppersContainer = document.getElementById('toppers-container');
            const addTopperBtn = document.getElementById('add-topper-btn');
            const toppersJsonHidden = document.getElementById('toppers_json_hidden');

            function addTopperField(name = '', details = '') {
                const div = document.createElement('div');
                div.classList.add('dynamic-field-group');
                div.innerHTML = `
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control topper-name" value="${name}" placeholder="Topper Name" required>
                    </div>
                    <div class="form-group">
                        <label>Details</label>
                        <input type="text" class="form-control topper-details" value="${details}" placeholder="e.g., Class 12 Top Performer" required>
                    </div>
                    <button type="button" class="btn btn-danger remove-btn"><i class="fas fa-minus-circle"></i> Remove</button>
                `;
                div.querySelector('.remove-btn').addEventListener('click', function() {
                    div.remove();
                });
                toppersContainer.appendChild(div);
            }

            addTopperBtn.addEventListener('click', () => addTopperField());

            // Load existing toppers data
            try {
                const existingToppers = JSON.parse(toppersJsonHidden.value);
                if (Array.isArray(existingToppers)) {
                    existingToppers.forEach(topper => addTopperField(topper.name, topper.details));
                }
            } catch (e) {
                console.error("Error parsing existing toppers JSON:", e);
                // Optionally add a default empty field if parsing fails and no fields are present
                if (toppersContainer.children.length === 1) { // Only the <p> tag is there
                    addTopperField();
                }
            }
            // If no toppers are loaded and it's just the paragraph, add one empty field
            if (toppersContainer.children.length === 1) {
                addTopperField();
            }


            // --- Awards Dynamic Fields ---
            const awardsContainer = document.getElementById('awards-container');
            const addAwardBtn = document.getElementById('add-award-btn');
            const awardsJsonHidden = document.getElementById('awards_json_hidden');

            function addAwardField(title = '', description = '') {
                const div = document.createElement('div');
                div.classList.add('dynamic-field-group');
                div.innerHTML = `
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" class="form-control award-title" value="${title}" placeholder="Award Title" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" class="form-control award-description" value="${description}" placeholder="e.g., Won 1st place in the senior category" required>
                    </div>
                    <button type="button" class="btn btn-danger remove-btn"><i class="fas fa-minus-circle"></i> Remove</button>
                `;
                div.querySelector('.remove-btn').addEventListener('click', function() {
                    div.remove();
                });
                awardsContainer.appendChild(div);
            }

            addAwardBtn.addEventListener('click', () => addAwardField());

            // Load existing awards data
            try {
                const existingAwards = JSON.parse(awardsJsonHidden.value);
                if (Array.isArray(existingAwards)) {
                    existingAwards.forEach(award => addAwardField(award.title, award.description));
                }
            } catch (e) {
                console.error("Error parsing existing awards JSON:", e);
                // Optionally add a default empty field if parsing fails and no fields are present
                if (awardsContainer.children.length === 1) { // Only the <p> tag is there
                    addAwardField();
                }
            }
            // If no awards are loaded and it's just the paragraph, add one empty field
            if (awardsContainer.children.length === 1) {
                addAwardField();
            }

            // --- Form Submission Logic ---
            document.getElementById('settingsForm').addEventListener('submit', function(event) {
                // Collect Toppers data
                const toppersData = [];
                toppersContainer.querySelectorAll('.dynamic-field-group').forEach(group => {
                    const name = group.querySelector('.topper-name').value.trim();
                    const details = group.querySelector('.topper-details').value.trim();
                    if (name && details) { // Only add if both fields are not empty
                        toppersData.push({ name: name, details: details });
                    }
                });
                toppersJsonHidden.value = JSON.stringify(toppersData);

                // Collect Awards data
                const awardsData = [];
                awardsContainer.querySelectorAll('.dynamic-field-group').forEach(group => {
                    const title = group.querySelector('.award-title').value.trim();
                    const description = group.querySelector('.award-description').value.trim();
                    if (title && description) { // Only add if both fields are not empty
                        awardsData.push({ title: title, description: description });
                    }
                });
                awardsJsonHidden.value = JSON.stringify(awardsData);

                // Basic client-side validation for dynamically added fields
                let isValid = true;
                if (!validateDynamicFields(toppersContainer, ['.topper-name', '.topper-details'])) {
                    isValid = false;
                    alert('Please fill in all Topper Name and Details fields or remove empty entries.');
                }
                if (!validateDynamicFields(awardsContainer, ['.award-title', '.award-description'])) {
                    isValid = false;
                    alert('Please fill in all Award Title and Description fields or remove empty entries.');
                }

                if (!isValid) {
                    event.preventDefault(); // Stop form submission if validation fails
                }
            });

            // Helper function for dynamic field validation
            function validateDynamicFields(container, selectors) {
                let allFilled = true;
                container.querySelectorAll('.dynamic-field-group').forEach(group => {
                    let groupFilled = true;
                    selectors.forEach(selector => {
                        const input = group.querySelector(selector);
                        if (input && input.value.trim() === '') {
                            groupFilled = false;
                            input.classList.add('is-invalid'); // Add visual indicator
                        } else if (input) {
                            input.classList.remove('is-invalid');
                        }
                    });
                    if (!groupFilled) allFilled = false;
                });
                return allFilled;
            }
        });
    </script>

</body>
</html>
<?php
// Include admin footer
require_once './admin_footer.php';
?>