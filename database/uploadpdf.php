<?php

require __DIR__ . '/./vendor/autoload.php'; // Adjust path if needed

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Exception\Upload\UploadException;

// --- IMPORTANT: CONFIGURE CLOUDINARY CREDENTIALS HERE ---
// Replace 'YOUR_CLOUD_NAME', 'YOUR_API_KEY', and 'YOUR_API_SECRET'
// with your actual Cloudinary account details.
Configuration::instance([
  'cloud' => [
    'cloud_name' => 'do9mane7a', // <<< REPLACE THIS
    'api_key' => '269318732537666',       // <<< REPLACE THIS
    'api_secret' => 'h_bfpaCLme-m3T20BEyyH6TfHEo'  // <<< REPLACE THIS
  ],
  'url' => [
    'secure' => true
  ]
]);

/**
 * Uploads a file to Cloudinary.
 *
 * @param array $file The file data from $_FILES (e.g., $_FILES['admissions_details_pdf']).
 * @param array $customOptions Optional array for Cloudinary upload parameters and custom settings, e.g.:
 *                       - 'folder' (string): Cloudinary folder name (default: 'misc_uploads')
 *                       - 'resource_type' (string): 'image', 'video', or 'raw' (default: 'image')
 *                       - 'allowed_mime_types' (array): Array of allowed MIME types (overrides defaults)
 *                       - 'max_size' (int): Maximum file size in bytes (default: 10MB)
 *                       - Any other valid Cloudinary SDK upload option.
 * @return array An associative array with ['success' => true, 'url' => '...', 'public_id' => '...'] on success.
 *               Returns ['success' => false, 'message' => '...'] on any error or if no file was selected.
 */
function uploadToCloudinary($file, $customOptions = []) {
    // Default options for validation and Cloudinary API call
    $options = array_merge([
        'folder' => 'misc_uploads', // Default Cloudinary folder
        'resource_type' => 'image', // Default resource type (most common for general use)
        'max_size' => 10 * 1024 * 1024, // Default max size: 10MB
        'use_filename' => true,
        'unique_filename' => true, // Often good practice for safety, can be set to false if needed
    ], $customOptions); // Merge custom options over defaults

    // Define default allowed MIME types based on resource_type
    $defaultAllowedMimeTypes = [];
    switch ($options['resource_type']) {
        case 'image':
            $defaultAllowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            break;
        case 'raw': // For PDFs, documents, etc.
            $defaultAllowedMimeTypes = [
                'application/pdf',
                'application/msword', // .doc
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                'application/vnd.ms-excel', // .xls
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
                'text/plain', // .txt
                // Add more if needed
            ];
            break;
        case 'video':
            $defaultAllowedMimeTypes = ['video/mp4', 'video/webm', 'video/quicktime'];
            break;
        // Default case or for custom resource types, rely on explicit 'allowed_mime_types'
        default:
            $defaultAllowedMimeTypes = []; // Be strict if resource_type isn't standard
            break;
    }

    // If 'allowed_mime_types' was provided in $customOptions, it overrides the defaults.
    // Otherwise, use the resource_type-based defaults.
    $allowedMimeTypes = $customOptions['allowed_mime_types'] ?? $defaultAllowedMimeTypes;

    // --- 1. Check for basic PHP upload errors (before Cloudinary processing) ---
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] == UPLOAD_ERR_NO_FILE) {
             return ['success' => false, 'message' => 'No file was selected for upload.'];
        }
        $error_message = "File upload failed: ";
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: $error_message .= "File is too large (exceeds PHP limit or form limit, check php.ini and form max_file_size)."; break;
            case UPLOAD_ERR_PARTIAL: $error_message .= "File was only partially uploaded."; break;
            case UPLOAD_ERR_NO_TMP_DIR: $error_message .= "Missing temporary folder on the server. Contact administrator."; break;
            case UPLOAD_ERR_CANT_WRITE: $error_message .= "Failed to write file to disk on the server. Check permissions."; break;
            case UPLOAD_ERR_EXTENSION: $error_message .= "A PHP extension blocked the upload. Contact administrator."; break;
            default: $error_message .= "Unknown upload error (Code: " . $file['error'] . ")."; break;
        }
        error_log("PHP File Upload Error in uploadToCloudinary for file " . ($file['name'] ?? 'N/A') . ": " . $error_message);
        return ['success' => false, 'message' => $error_message];
    }

    // --- 2. File size validation ---
    if ($file['size'] > $options['max_size']) {
        $max_size_mb = round($options['max_size'] / (1024 * 1024));
        return ['success' => false, 'message' => "File is too large. Maximum size is {$max_size_mb}MB."];
    }

    // --- 3. File type (MIME) validation using finfo_open for security ---
    $real_file_type = false;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
           $real_file_type = finfo_file($finfo, $file['tmp_name']);
           finfo_close($finfo);
        }
    }
    // Fallback if finfo_open is not available (less secure but better than no check)
    if ($real_file_type === false && function_exists('mime_content_type')) {
        $real_file_type = mime_content_type($file['tmp_name']);
    }

    if ($real_file_type === false || !in_array($real_file_type, $allowedMimeTypes)) {
         // Create a user-friendly list of allowed extensions for the error message
         $allowed_exts_map = [
             'image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/gif' => 'GIF', 'image/webp' => 'WEBP',
             'application/pdf' => 'PDF', 'application/msword' => 'DOC',
             'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
             'application/vnd.ms-excel' => 'XLS',
             'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
             'text/plain' => 'TXT',
             'video/mp4' => 'MP4', 'video/webm' => 'WEBM', 'video/quicktime' => 'MOV'
         ];
         $display_allowed_exts = [];
         foreach ($allowedMimeTypes as $mime) {
             $display_allowed_exts[] = $allowed_exts_map[$mime] ?? strtoupper(explode('/', $mime)[1]);
         }
         $allowed_exts_str = implode(', ', array_unique($display_allowed_exts));

        return ['success' => false, 'message' => "Invalid file type ('" . ($real_file_type ?: 'unknown') . "'). Allowed types: {$allowed_exts_str}."];
    }


    try {
        // Prepare Cloudinary upload parameters, excluding our internal custom options
        $cloudinaryUploadParams = $options; // Start with all options
        unset($cloudinaryUploadParams['max_size']); // Remove internal option
        unset($cloudinaryUploadParams['allowed_mime_types']); // Remove internal option


        // Perform the upload to Cloudinary
        $uploadResult = (new UploadApi())->upload($file['tmp_name'], $cloudinaryUploadParams);

        // Check if the upload was successful and returned expected keys
        if (isset($uploadResult['secure_url']) && isset($uploadResult['public_id'])) {
            return [
                'success' => true,
                'url' => $uploadResult['secure_url'],
                'public_id' => $uploadResult['public_id']
            ];
        } else {
            error_log("Cloudinary upload returned unexpected structure for file " . ($file['name'] ?? 'N/A') . ": " . print_r($uploadResult, true));
            return ['success' => false, 'message' => 'Cloudinary upload failed with unexpected response from the API.'];
        }

    } catch (UploadException $e) {
        // Catch Cloudinary specific upload exceptions (e.g., API errors, authentication failures)
        error_log("Cloudinary UploadException for file " . ($file['name'] ?? 'N/A') . ": " . $e->getMessage());
        return ['success' => false, 'message' => 'Cloudinary API upload failed: ' . $e->getMessage()];

    } catch (\Exception $e) {
        // Catch any other general exceptions during the process
        error_log("General Exception during Cloudinary upload for file " . ($file['name'] ?? 'N/A') . ": " . $e->getMessage());
        return ['success' => false, 'message' => 'An unexpected server error occurred during upload.'];
    }
}