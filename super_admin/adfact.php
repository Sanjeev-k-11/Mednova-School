<?php
// Start the session
session_start();

// Include configuration
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["super_admin_id"]; // Admin ID from session

// --- LOCAL STORAGE CHANGE: Define upload directory ---
$upload_dir_relative_to_script = '../uploads/admissions_pdfs/';
// Ensure the upload directory exists
if (!is_dir($upload_dir_relative_to_script)) {
    mkdir($upload_dir_relative_to_script, 0755, true); // Create recursively with write permissions
}
// For displaying the link, we'll use a path relative to the web root or current page.
$upload_dir_public_url = '../uploads/admissions_pdfs/';


// Initialize variables for all form fields based on admissions_settings table
$settings = [
    // --- FORM SEPARATION: Main Admissions Section Content ---
    'admissions_section_title' => '',
    'admissions_section_description' => '',
    'admissions_section_enabled' => true, // NEW FIELD: for enabling/disabling this section

    // --- FORM SEPARATION: Admission Process Steps ---
    'admission_process_title' => '',
    'admission_process_json' => '[]',
    'admissions_process_enabled' => true, // NEW FIELD: for enabling/disabling this section

    // --- FORM SEPARATION: Important Dates ---
    'important_dates_title' => '',
    'important_dates_json' => '[]',
    'important_dates_enabled' => true, // NEW FIELD: for enabling/disabling this section

    // --- FORM SEPARATION: Admissions Status & Application Details ---
    'is_admissions_open' => true, // Existing field, acts as an 'enabled' for the whole admissions process
    'application_start_date' => null,
    'application_end_date' => null,
    'application_link' => '',
    'notes_on_admissions_status' => '',

    // --- FORM SEPARATION: PDF URLs and Text content ---
    'admissions_details_pdf_url' => '', // Will store filename (e.g., 'details.pdf')
    'admissions_criteria_pdf_url' => '', // Will store filename (e.g., 'criteria.pdf')
    'admissions_details_text' => '',
    'admissions_criteria_text' => '',
];

$errors = [];
$success_message = "";

// Initialize editable arrays for form display
$admission_process_editable = [];
for ($i = 0; $i < 4; $i++) {
    $admission_process_editable[$i] = [
        'title' => '', 'description' => '', 'icon' => '' // SVG string
    ];
}

$important_dates_editable = [];
for ($i = 0; $i < 3; $i++) {
    $important_dates_editable[$i] = [
        'date_text' => '', 'description' => ''
    ];
}

/**
 * Sanitize a filename to remove potentially harmful characters.
 * Keeps letters, numbers, dashes, underscores, and dots.
 * @param string $filename
 * @return string
 */
function sanitize_filename($filename) {
    // Remove anything that isn't a letter, number, dot, dash, or underscore
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
    // Replace multiple dots with a single dot (except for extension dot)
    $filename = preg_replace('/\.(?!\w)/', '', $filename);
    return $filename;
}

/**
 * Fetches the current admissions settings from the database and populates variables.
 * This function is called on page load and after any form submission to ensure UI is up-to-date.
 */
function fetch_current_settings($link, &$settings, &$admission_process_editable, &$important_dates_editable, &$errors, &$success_message) {
    $sql_fetch = "SELECT * FROM admissions_settings WHERE id = 1";
    if ($result_fetch = mysqli_query($link, $sql_fetch)) {
        if (mysqli_num_rows($result_fetch) == 1) {
            $current_settings = mysqli_fetch_assoc($result_fetch);
            // Populate settings array with fetched data
            foreach ($settings as $key => $value) {
                if (isset($current_settings[$key]) && $current_settings[$key] !== NULL) {
                    // Special handling for boolean fields
                    if (in_array($key, ['is_admissions_open', 'admissions_section_enabled', 'admissions_process_enabled', 'important_dates_enabled'])) {
                        $settings[$key] = (bool)$current_settings[$key];
                    } else {
                        $settings[$key] = $current_settings[$key];
                    }
                }
            }
            
            // Handle admission_process_json for form display
            if (!empty($settings['admission_process_json'])) {
                $decoded_process = json_decode($settings['admission_process_json'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_process)) {
                    // Reset editable array before populating
                    foreach ($admission_process_editable as $index => $step) {
                        $admission_process_editable[$index] = ['title' => '', 'description' => '', 'icon' => ''];
                    }
                    foreach ($decoded_process as $index => $step) {
                        if (isset($admission_process_editable[$index])) {
                            $admission_process_editable[$index]['title'] = $step['title'] ?? '';
                            $admission_process_editable[$index]['description'] = $step['description'] ?? '';
                            $admission_process_editable[$index]['icon'] = $step['icon'] ?? '';
                        }
                    }
                }
            }

            // Handle important_dates_json for form display
            if (!empty($settings['important_dates_json'])) {
                $decoded_dates = json_decode($settings['important_dates_json'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_dates)) {
                    // Reset editable array before populating
                    foreach ($important_dates_editable as $index => $date_item) {
                        $important_dates_editable[$index] = ['date_text' => '', 'description' => ''];
                    }
                    foreach ($decoded_dates as $index => $date_item) {
                        if (isset($important_dates_editable[$index])) {
                            $important_dates_editable[$index]['date_text'] = $date_item['date_text'] ?? '';
                            $important_dates_editable[$index]['description'] = $date_item['description'] ?? '';
                        }
                    }
                }
            }

        } else {
            // Only show this message if it's the very first time and no data exists.
            // If the table is empty, a new entry (id=1) will be created on the first submit.
            // This message is mostly informational and can be suppressed after initial setup.
            // $success_message .= "Initial 'Admissions' settings are not found. A new entry will be created on submission.";
        }
        mysqli_free_result($result_fetch);
    } else {
        $errors[] = "Error fetching current 'Admissions' settings: " . mysqli_error($link);
    }
}

// Fetch initial settings when the page loads
fetch_current_settings($link, $settings, $admission_process_editable, $important_dates_editable, $errors, $success_message);


// --- FORM SEPARATION: Process Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_submitted = $_POST['form_name'] ?? ''; // Identify which form was submitted

    $current_errors = []; // Temporary errors for current submission
    $current_success_message = ''; // Temporary success for current submission

    switch ($form_submitted) {
        case 'main_content':
            // Sanitize and validate fields for Main Content
            $new_admissions_section_title = trim($_POST['admissions_section_title'] ?? '');
            $new_admissions_section_description = trim($_POST['admissions_section_description'] ?? '');
            $new_admissions_section_enabled = isset($_POST['admissions_section_enabled']) ? true : false; // NEW FIELD

            if (empty($current_errors)) {
                $sql_upsert = "INSERT INTO `admissions_settings` (
                    `id`, `admissions_section_title`, `admissions_section_description`, `admissions_section_enabled`, `updated_by_admin_id`
                ) VALUES (
                    1, ?, ?, ?, ?
                ) ON DUPLICATE KEY UPDATE
                    `admissions_section_title` = VALUES(`admissions_section_title`),
                    `admissions_section_description` = VALUES(`admissions_section_description`),
                    `admissions_section_enabled` = VALUES(`admissions_section_enabled`),
                    `updated_by_admin_id` = VALUES(`updated_by_admin_id`),
                    `updated_at` = CURRENT_TIMESTAMP";

                if ($stmt = mysqli_prepare($link, $sql_upsert)) {
                    mysqli_stmt_bind_param($stmt, "ssii", // 2 strings, 2 integers
                        $new_admissions_section_title,
                        $new_admissions_section_description,
                        $new_admissions_section_enabled,
                        $admin_id
                    );
                    if (mysqli_stmt_execute($stmt)) {
                        $current_success_message = "Main Admissions Content updated successfully.";
                    } else {
                        $current_errors[] = "Error updating Main Admissions Content: " . mysqli_error($link);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $current_errors[] = "Database preparation error for Main Admissions Content: " . mysqli_error($link);
                }
            }
            break;

        case 'admissions_status':
            // Sanitize and validate fields for Admissions Status & Application Details
            $new_is_admissions_open = isset($_POST['is_admissions_open']) ? true : false;
            $new_application_start_date = !empty($_POST['application_start_date']) ? $_POST['application_start_date'] : NULL;
            $new_application_end_date = !empty($_POST['application_end_date']) ? $_POST['application_end_date'] : NULL;
            $new_application_link = filter_var(trim($_POST['application_link'] ?? ''), FILTER_SANITIZE_URL);
            $new_notes_on_admissions_status = trim($_POST['notes_on_admissions_status'] ?? '');

            if (empty($current_errors)) {
                $sql_upsert = "INSERT INTO `admissions_settings` (
                    `id`, `is_admissions_open`, `application_start_date`, `application_end_date`,
                    `application_link`, `notes_on_admissions_status`, `updated_by_admin_id`
                ) VALUES (
                    1, ?, ?, ?, ?, ?, ?
                ) ON DUPLICATE KEY UPDATE
                    `is_admissions_open` = VALUES(`is_admissions_open`),
                    `application_start_date` = VALUES(`application_start_date`),
                    `application_end_date` = VALUES(`application_end_date`),
                    `application_link` = VALUES(`application_link`),
                    `notes_on_admissions_status` = VALUES(`notes_on_admissions_status`),
                    `updated_by_admin_id` = VALUES(`updated_by_admin_id`),
                    `updated_at` = CURRENT_TIMESTAMP";

                if ($stmt = mysqli_prepare($link, $sql_upsert)) {
                    // 1 int, 2 strings (dates can be NULL but bind as string), 2 strings, 1 int = 6 parameters
                    mysqli_stmt_bind_param($stmt, "issssi",
                        $new_is_admissions_open,
                        $new_application_start_date,
                        $new_application_end_date,
                        $new_application_link,
                        $new_notes_on_admissions_status,
                        $admin_id
                    );
                    if (mysqli_stmt_execute($stmt)) {
                        $current_success_message = "Admissions Status & Application Details updated successfully.";
                    } else {
                        $current_errors[] = "Error updating Admissions Status: " . mysqli_error($link);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $current_errors[] = "Database preparation error for Admissions Status: " . mysqli_error($link);
                }
            }
            break;

        case 'admissions_pdfs_text':
            // Sanitize and validate fields for PDF & Text Content
            $new_admissions_details_text = trim($_POST['admissions_details_text'] ?? '');
            $new_admissions_criteria_text = trim($_POST['admissions_criteria_text'] ?? '');

            // Use the currently fetched settings for initial existing filenames
            // This ensures we have the correct filenames from the DB before potential updates
            $current_admissions_details_pdf_url = $settings['admissions_details_pdf_url'];
            $current_admissions_criteria_pdf_url = $settings['admissions_criteria_pdf_url'];

            // Prepare variables to hold potentially new/cleared PDF URLs
            $updated_admissions_details_pdf_url = $current_admissions_details_pdf_url;
            $updated_admissions_criteria_pdf_url = $current_admissions_criteria_pdf_url;


            $pdf_fields = [
                'admissions_details_pdf' => 'admissions_details_pdf_url',
                'admissions_criteria_pdf' => 'admissions_criteria_pdf_url'
            ];

            foreach ($pdf_fields as $file_input_name => $db_column_name) {
                // Determine the current filename for this field
                $existing_filename_for_field = ($db_column_name == 'admissions_details_pdf_url') ? $current_admissions_details_pdf_url : $current_admissions_criteria_pdf_url;
                $updated_filename_for_field = $existing_filename_for_field; // Start by assuming it stays the same

                // Check if 'clear' checkbox is ticked
                $clear_checkbox_name = 'clear_' . $file_input_name;
                if (isset($_POST[$clear_checkbox_name]) && $_POST[$clear_checkbox_name] === 'on') {
                    if (!empty($existing_filename_for_field) && file_exists($upload_dir_relative_to_script . $existing_filename_for_field)) {
                        unlink($upload_dir_relative_to_script . $existing_filename_for_field); // Delete from local storage
                    }
                    $updated_filename_for_field = NULL; // Mark for DB update
                }

                // Check for new file upload
                if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
                    $file = $_FILES[$file_input_name];

                    // Basic validation
                    if ($file['type'] != 'application/pdf') {
                        $current_errors[] = "Only PDF files are allowed for " . str_replace('_', ' ', $file_input_name) . ".";
                        continue;
                    }
                    if ($file['size'] > 5 * 1024 * 1024) { // Max 5MB
                        $current_errors[] = str_replace('_', ' ', $file_input_name) . " file size exceeds 5MB limit.";
                        continue;
                    }

                    // Generate a unique filename to prevent conflicts
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = uniqid() . '_' . sanitize_filename(basename($file['name'], '.' . $extension)) . '.' . $extension;
                    $destination_path = $upload_dir_relative_to_script . $new_filename;

                    // Move the uploaded file
                    if (move_uploaded_file($file['tmp_name'], $destination_path)) {
                        // Delete old PDF from local storage if a new one is uploaded
                        if (!empty($existing_filename_for_field) && file_exists($upload_dir_relative_to_script . $existing_filename_for_field)) {
                            unlink($upload_dir_relative_to_script . $existing_filename_for_field);
                        }
                        $updated_filename_for_field = $new_filename; // Mark for DB update
                        $current_success_message .= "Uploaded " . str_replace('_', ' ', $file_input_name) . ". ";
                    } else {
                        $current_errors[] = "Failed to upload " . str_replace('_', ' ', $file_input_name) . " to local storage.";
                    }
                }
                
                // Assign the determined filename back to the respective variable
                if ($db_column_name == 'admissions_details_pdf_url') {
                    $updated_admissions_details_pdf_url = $updated_filename_for_field;
                } else {
                    $updated_admissions_criteria_pdf_url = $updated_filename_for_field;
                }
            }

            // After PDF handling, update the DB with text and potentially new/cleared PDF URLs
            if (empty($current_errors)) {
                $sql_upsert = "INSERT INTO `admissions_settings` (
                    `id`, `admissions_details_pdf_url`, `admissions_criteria_pdf_url`,
                    `admissions_details_text`, `admissions_criteria_text`, `updated_by_admin_id`
                ) VALUES (
                    1, ?, ?, ?, ?, ?
                ) ON DUPLICATE KEY UPDATE
                    `admissions_details_pdf_url` = VALUES(`admissions_details_pdf_url`),
                    `admissions_criteria_pdf_url` = VALUES(`admissions_criteria_pdf_url`),
                    `admissions_details_text` = VALUES(`admissions_details_text`),
                    `admissions_criteria_text` = VALUES(`admissions_criteria_text`),
                    `updated_by_admin_id` = VALUES(`updated_by_admin_id`),
                    `updated_at` = CURRENT_TIMESTAMP";

                if ($stmt = mysqli_prepare($link, $sql_upsert)) {
                    // 4 strings (pdf_urls, text), 1 int (admin_id) = 5 parameters
                    mysqli_stmt_bind_param($stmt, "ssssi",
                        $updated_admissions_details_pdf_url,
                        $updated_admissions_criteria_pdf_url,
                        $new_admissions_details_text,
                        $new_admissions_criteria_text,
                        $admin_id
                    );
                    if (mysqli_stmt_execute($stmt)) {
                        $current_success_message .= "Admissions PDF & Text Content updated successfully.";
                    } else {
                        $current_errors[] = "Error updating Admissions PDF/Text Content: " . mysqli_error($link);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $current_errors[] = "Database preparation error for Admissions PDF/Text Content: " . mysqli_error($link);
                }
            }
            break;

        case 'admission_process':
            $new_admission_process_title = trim($_POST['admission_process_title'] ?? '');
            $new_admissions_process_enabled = isset($_POST['admissions_process_enabled']) ? true : false; // NEW FIELD
            $new_admission_process = [];
            for ($i = 0; $i < 4; $i++) {
                $title = trim($_POST["admission_step{$i}_title"] ?? '');
                $description = trim($_POST["admission_step{$i}_description"] ?? '');
                $icon = trim($_POST["admission_step{$i}_icon"] ?? '');
                if (!empty($title)) {
                    $new_admission_process[] = [
                        'title' => $title,
                        'description' => $description,
                        'icon' => $icon
                    ];
                }
            }
            $new_admission_process_json = json_encode($new_admission_process);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $current_errors[] = "Error encoding admission process data: " . json_last_error_msg();
            }

            if (empty($current_errors)) {
                $sql_upsert = "INSERT INTO `admissions_settings` (
                    `id`, `admission_process_title`, `admission_process_json`, `admissions_process_enabled`, `updated_by_admin_id`
                ) VALUES (
                    1, ?, ?, ?, ?
                ) ON DUPLICATE KEY UPDATE
                    `admission_process_title` = VALUES(`admission_process_title`),
                    `admission_process_json` = VALUES(`admission_process_json`),
                    `admissions_process_enabled` = VALUES(`admissions_process_enabled`),
                    `updated_by_admin_id` = VALUES(`updated_by_admin_id`),
                    `updated_at` = CURRENT_TIMESTAMP";

                if ($stmt = mysqli_prepare($link, $sql_upsert)) {
                    mysqli_stmt_bind_param($stmt, "ssiii", // 2 strings, 2 integers
                        $new_admission_process_title,
                        $new_admission_process_json,
                        $new_admissions_process_enabled,
                        $admin_id
                    );
                    if (mysqli_stmt_execute($stmt)) {
                        $current_success_message = "Admission Process Steps updated successfully.";
                    } else {
                        $current_errors[] = "Error updating Admission Process Steps: " . mysqli_error($link);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $current_errors[] = "Database preparation error for Admission Process Steps: " . mysqli_error($link);
                }
            }
            break;

        case 'important_dates':
            $new_important_dates_title = trim($_POST['important_dates_title'] ?? '');
            $new_important_dates_enabled = isset($_POST['important_dates_enabled']) ? true : false; // NEW FIELD
            $new_important_dates = [];
            for ($i = 0; $i < 3; $i++) {
                $date_text = trim($_POST["important_date{$i}_text"] ?? '');
                $description = trim($_POST["important_date{$i}_description"] ?? '');
                if (!empty($date_text)) {
                    $new_important_dates[] = [
                        'date_text' => $date_text,
                        'description' => $description
                    ];
                }
            }
            $new_important_dates_json = json_encode($new_important_dates);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $current_errors[] = "Error encoding important dates data: " . json_last_error_msg();
            }

            if (empty($current_errors)) {
                $sql_upsert = "INSERT INTO `admissions_settings` (
                    `id`, `important_dates_title`, `important_dates_json`, `important_dates_enabled`, `updated_by_admin_id`
                ) VALUES (
                    1, ?, ?, ?, ?
                ) ON DUPLICATE KEY UPDATE
                    `important_dates_title` = VALUES(`important_dates_title`),
                    `important_dates_json` = VALUES(`important_dates_json`),
                    `important_dates_enabled` = VALUES(`important_dates_enabled`),
                    `updated_by_admin_id` = VALUES(`updated_by_admin_id`),
                    `updated_at` = CURRENT_TIMESTAMP";

                if ($stmt = mysqli_prepare($link, $sql_upsert)) {
                    mysqli_stmt_bind_param($stmt, "ssiii", // 2 strings, 2 integers
                        $new_important_dates_title,
                        $new_important_dates_json,
                        $new_important_dates_enabled,
                        $admin_id
                    );
                    if (mysqli_stmt_execute($stmt)) {
                        $current_success_message = "Important Dates updated successfully.";
                    } else {
                        $current_errors[] = "Error updating Important Dates: " . mysqli_error($link);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $current_errors[] = "Database preparation error for Important Dates: " . mysqli_error($link);
                }
            }
            break;
    }

    // After any form submission, re-fetch all settings to update the display
    // And merge current errors/success messages
    $errors = array_merge($errors, $current_errors);
    if (!empty($current_success_message)) {
        if (!empty($success_message)) $success_message .= "<br>"; // Add a line break if there are previous messages
        $success_message .= $current_success_message;
    }

    // Re-fetch all settings to ensure all form fields display the most current data
    fetch_current_settings($link, $settings, $admission_process_editable, $important_dates_editable, $errors, $success_message);
}

// Include admin header for consistent layout
require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admissions Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Existing CSS styles ... */
        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
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
        /* Moved form-specific h3 styling to form-header h3 */
        /* Removed original h3 styling here as it's now handled by .form-header h3 */
        

        .form-group { margin-bottom: 22px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1A2C5A;
            font-size: 0.95em;
        }
        .form-group .inline-label {
            display: inline-flex;
            align-items: center;
            font-weight: normal;
            color: #333;
            font-size: 1em;
            cursor: pointer;
        }
        .form-group .inline-label input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
        }

        .form-group input:not([type="file"]), .form-group textarea, .form-group input[type="date"], .form-group input[type="url"] {
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
        .form-group input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #C0D3EB;
            border-radius: 8px;
            background-color: #FFFFFF;
            font-size: 1em;
            color: #333;
        }


        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 20px; /* Kept for consistent spacing if not immediately followed by other grid */
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
        .dynamic-item h4 { /* Sub-heading within dynamic items */
            font-size: 1.1em;
            font-weight: 700;
            color: #1A2C5A;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px dotted #A9CCE3;
            padding-bottom: 5px;
            text-align: left; /* Align left in dynamic item */
            white-space: normal; /* Override potential nowrap */
            transform: none; /* Override potential transform */
        }
        .svg-preview {
            background-color: #4c51bf;
            border-radius: 8px;
            padding: 8px;
            margin-top: 10px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .svg-preview svg {
            width: 24px;
            height: 24px;
            color: white;
            fill: white; /* Ensure SVG fill color is white */
        }
        .important-date-item {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .important-date-item input, .important-date-item textarea {
            width: 100%;
        }
        .pdf-display a {
            color: #1A2C5A;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .pdf-display a:hover {
            color: #E7A33C;
            text-decoration: underline;
        }

        /* --- Collapsible Form Specific Styles --- */
        form.collapsible-form {
            background-color: #fefefe;
            padding: 0; /* No padding directly on form, handled by inner elements */
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e0e7ef;
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer; /* Indicate clickable area */
            padding: 25px 30px; /* Padding for the header area */
            border-bottom: 1px solid #e0e7ef; /* Separator for content */
            border-radius: 12px 12px 0 0; /* Rounded top corners */
            background-color: #fefefe; /* Match form background */
            transition: background-color 0.3s ease;
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
            border-radius: 0 0 12px 12px; /* Rounded bottom corners */
            background-color: #fefefe; /* Match form background */
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
        .form-group.checkbox-group {
            margin-bottom: 15px;
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
        }
        @media (max-width: 480px) {
            .section {
                padding: 20px;
                margin-bottom: 25px;
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
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Manage Admissions Section Settings</h2>

    <?php 
    // Display general errors/success messages at the top
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

    <!-- --- FORM SEPARATION: Form for Main Admissions Section Content --- -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="collapsible-form">
        <input type="hidden" name="form_name" value="main_content">
        <div class="form-header">
            <h3>Main Admissions Section Content</h3>
            <button type="button" class="toggle-button" aria-expanded="false">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="form-collapsible-content">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label for="admissions_section_title">Main Title</label>
                    <input type="text" name="admissions_section_title" id="admissions_section_title" value="<?php echo htmlspecialchars($settings['admissions_section_title']); ?>">
                </div>
                <div class="form-group full-width">
                    <label for="admissions_section_description">Main Description</label>
                    <textarea name="admissions_section_description" id="admissions_section_description" rows="3"><?php echo htmlspecialchars($settings['admissions_section_description']); ?></textarea>
                </div>
                <div class="form-group checkbox-group full-width">
                    <input type="checkbox" name="admissions_section_enabled" id="admissions_section_enabled" <?php echo $settings['admissions_section_enabled'] ? 'checked' : ''; ?>>
                    <label for="admissions_section_enabled" class="inline-label">Enable Main Admissions Section on Frontend</label>
                </div>
            </div>
            <div class="form-actions">
                <input type="submit" name="submit_main_content" class="btn" value="Update Main Content">
            </div>
        </div>
    </form>

    <!-- --- FORM SEPARATION: Form for Admissions Status & Application Details --- -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="collapsible-form">
        <input type="hidden" name="form_name" value="admissions_status">
        <div class="form-header">
            <h3>Admissions Status & Application Details</h3>
            <button type="button" class="toggle-button" aria-expanded="false">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="form-collapsible-content">
            <div class="form-grid">
                <div class="form-group checkbox-group full-width">
                    <input type="checkbox" name="is_admissions_open" id="is_admissions_open" <?php echo $settings['is_admissions_open'] ? 'checked' : ''; ?>>
                    <label for="is_admissions_open" class="inline-label">Admissions Open (Overall Status)</label>
                </div>
                <div class="form-group">
                    <label for="application_start_date">Application Start Date</label>
                    <input type="date" name="application_start_date" id="application_start_date" value="<?php echo htmlspecialchars($settings['application_start_date'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="application_end_date">Application End Date</label>
                    <input type="date" name="application_end_date" id="application_end_date" value="<?php echo htmlspecialchars($settings['application_end_date'] ?? ''); ?>">
                </div>
                <div class="form-group full-width">
                    <label for="application_link">Application Link (URL)</label>
                    <input type="url" name="application_link" id="application_link" value="<?php echo htmlspecialchars($settings['application_link'] ?? ''); ?>" placeholder="https://example.com/apply">
                </div>
                <div class="form-group full-width">
                    <label for="notes_on_admissions_status">Notes on Admissions Status</label>
                    <textarea name="notes_on_admissions_status" id="notes_on_admissions_status" rows="3"><?php echo htmlspecialchars($settings['notes_on_admissions_status'] ?? ''); ?></textarea>
                    <p style="font-size: 0.85em; color: #777; margin-top: 5px;">E.g., "Applications for the next academic year will open soon."</p>
                </div>
            </div>
            <div class="form-actions">
                <input type="submit" name="submit_admissions_status" class="btn" value="Update Admissions Status">
            </div>
        </div>
    </form>

    <!-- --- FORM SEPARATION: Form for Admissions PDF & Text Content --- -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="collapsible-form">
        <input type="hidden" name="form_name" value="admissions_pdfs_text">
        <div class="form-header">
            <h3>Admissions PDF & Text Content</h3>
            <button type="button" class="toggle-button" aria-expanded="false">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="form-collapsible-content">
            <div class="form-grid">
                <!-- Admissions Details PDF -->
                <div class="dynamic-item full-width">
                    <h4>Admissions Details PDF</h4>
                    <?php if (!empty($settings['admissions_details_pdf_url'])): ?>
                        <div class="pdf-display" style="margin-bottom: 15px;">
                            <strong>Current PDF:</strong> <a href="<?php echo htmlspecialchars($upload_dir_public_url . $settings['admissions_details_pdf_url']); ?>" target="_blank">View Admissions Details PDF</a>
                            <label style="display: block; margin-top: 5px; font-size: 0.9em; color: #666;">
                                <input type="checkbox" name="clear_admissions_details_pdf"> Remove current PDF
                            </label>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="admissions_details_pdf">Upload New Admissions Details PDF (Max 5MB)</label>
                        <input type="file" name="admissions_details_pdf" id="admissions_details_pdf" accept="application/pdf">
                    </div>
                </div>

                <!-- Admissions Details Text -->
                <div class="form-group full-width">
                    <label for="admissions_details_text">Admissions Details (Text Content)</label>
                    <textarea name="admissions_details_text" id="admissions_details_text" rows="5"><?php echo htmlspecialchars($settings['admissions_details_text'] ?? ''); ?></textarea>
                    <p style="font-size: 0.85em; color: #777; margin-top: 5px;">This text will be displayed if no PDF is uploaded or as supplementary information.</p>
                </div>

                <!-- Admissions Criteria PDF -->
                <div class="dynamic-item full-width">
                    <h4>Admissions Criteria PDF</h4>
                    <?php if (!empty($settings['admissions_criteria_pdf_url'])): ?>
                        <div class="pdf-display" style="margin-bottom: 15px;">
                            <strong>Current PDF:</strong> <a href="<?php echo htmlspecialchars($upload_dir_public_url . $settings['admissions_criteria_pdf_url']); ?>" target="_blank">View Admissions Criteria PDF</a>
                            <label style="display: block; margin-top: 5px; font-size: 0.9em; color: #666;">
                                <input type="checkbox" name="clear_admissions_criteria_pdf"> Remove current PDF
                            </label>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="admissions_criteria_pdf">Upload New Admissions Criteria PDF (Max 5MB)</label>
                        <input type="file" name="admissions_criteria_pdf" id="admissions_criteria_pdf" accept="application/pdf">
                    </div>
                </div>

                <!-- Admissions Criteria Text -->
                <div class="form-group full-width">
                    <label for="admissions_criteria_text">Admissions Criteria (Text Content)</label>
                    <textarea name="admissions_criteria_text" id="admissions_criteria_text" rows="5"><?php echo htmlspecialchars($settings['admissions_criteria_text'] ?? ''); ?></textarea>
                    <p style="font-size: 0.85em; color: #777; margin-top: 5px;">This text will be displayed if no PDF is uploaded or as supplementary information.</p>
                </div>
            </div>
            <div class="form-actions">
                <input type="submit" name="submit_admissions_pdfs_text" class="btn" value="Update PDFs & Text">
            </div>
        </div>
    </form>

    <!-- --- FORM SEPARATION: Form for Admission Process Steps --- -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="collapsible-form">
        <input type="hidden" name="form_name" value="admission_process">
        <div class="form-header">
            <h3>Admission Process Steps (Up to 4)</h3>
            <button type="button" class="toggle-button" aria-expanded="false">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="form-collapsible-content">
            <div class="form-group full-width checkbox-group">
                <input type="checkbox" name="admissions_process_enabled" id="admissions_process_enabled" <?php echo $settings['admissions_process_enabled'] ? 'checked' : ''; ?>>
                <label for="admissions_process_enabled" class="inline-label">Enable Admission Process Section on Frontend</label>
            </div>
            <div class="form-group full-width">
                <label for="admission_process_title">Process Section Title</label>
                <input type="text" name="admission_process_title" id="admission_process_title" value="<?php echo htmlspecialchars($settings['admission_process_title']); ?>">
            </div>
            <p style="text-align: center; font-size: 0.9em; color: #666; margin-bottom: 20px;">
                Enter details for each step. Clear a step's title to remove it. For icons, paste the SVG code directly.
            </p>
            <div class="form-grid">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="dynamic-item full-width">
                        <h4>Step <?php echo $i + 1; ?></h4>
                        <div class="form-group">
                            <label for="admission_step<?php echo $i; ?>_title">Title</label>
                            <input type="text" name="admission_step<?php echo $i; ?>_title" id="admission_step<?php echo $i; ?>_title" value="<?php echo htmlspecialchars($admission_process_editable[$i]['title'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="admission_step<?php echo $i; ?>_description">Description</label>
                            <textarea name="admission_step<?php echo $i; ?>_description" id="admission_step<?php echo $i; ?>_description" rows="2"><?php echo htmlspecialchars($admission_process_editable[$i]['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="admission_step<?php echo $i; ?>_icon">Icon (SVG Code)</label>
                            <textarea name="admission_step<?php echo $i; ?>_icon" id="admission_step<?php echo $i; ?>_icon" rows="3"><?php echo htmlspecialchars($admission_process_editable[$i]['icon'] ?? ''); ?></textarea>
                            <?php 
                            if (!empty($admission_process_editable[$i]['icon']) && strpos($admission_process_editable[$i]['icon'], '<svg') !== false): ?>
                                <div class="svg-preview">
                                    <?php echo $admission_process_editable[$i]['icon']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="form-actions">
                <input type="submit" name="submit_admission_process" class="btn" value="Update Process Steps">
            </div>
        </div>
    </form>

    <!-- --- FORM SEPARATION: Form for Important Dates --- -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="collapsible-form">
        <input type="hidden" name="form_name" value="important_dates">
        <div class="form-header">
            <h3>Important Dates (Up to 3)</h3>
            <button type="button" class="toggle-button" aria-expanded="false">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="form-collapsible-content">
            <div class="form-group full-width checkbox-group">
                <input type="checkbox" name="important_dates_enabled" id="important_dates_enabled" <?php echo $settings['important_dates_enabled'] ? 'checked' : ''; ?>>
                <label for="important_dates_enabled" class="inline-label">Enable Important Dates Section on Frontend</label>
            </div>
            <div class="form-group full-width">
                <label for="important_dates_title">Important Dates Section Title</label>
                <input type="text" name="important_dates_title" id="important_dates_title" value="<?php echo htmlspecialchars($settings['important_dates_title']); ?>">
            </div>
            <p style="text-align: center; font-size: 0.9em; color: #666; margin-bottom: 20px;">
                Enter details for each important date. Clear a date's text to remove it.
            </p>
            <div class="form-grid">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="dynamic-item important-date-item">
                        <h4>Date Entry <?php echo $i + 1; ?></h4>
                        <div class="form-group">
                            <label for="important_date<?php echo $i; ?>_text">Date Text (e.g., "December 2023")</label>
                            <input type="text" name="important_date<?php echo $i; ?>_text" id="important_date<?php echo $i; ?>_text" value="<?php echo htmlspecialchars($important_dates_editable[$i]['date_text'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="important_date<?php echo $i; ?>_description">Description</label>
                            <textarea name="important_date<?php echo $i; ?>_description" id="important_date<?php echo $i; ?>_description" rows="2"><?php echo htmlspecialchars($important_dates_editable[$i]['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="form-actions">
                <input type="submit" name="submit_important_dates" class="btn" value="Update Important Dates">
            </div>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.collapsible-form');

    forms.forEach((form, index) => {
        const header = form.querySelector('.form-header');
        const content = form.querySelector('.form-collapsible-content');
        const toggleButton = form.querySelector('.toggle-button');

        // Initial state: first form open, others closed
        if (index === 0) { // Make the first form open by default
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