<?php

require __DIR__ . '/./vendor/autoload.php';   // Adjust path if needed

// Include Composer's autoloader for Cloudinary library
// ** Ensure composer install has been run in the root directory (School/) **
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Exception\Upload\UploadException;

// Configure Cloudinary (replace with your actual credentials)
Configuration::instance([
  'cloud' => [
    'cloud_name' => 'do9mane7a', // <<< IMPORTANT: Replace with your Cloud Name
    'api_key' => '269318732537666',       // <<< IMPORTANT: Replace with your API Key
    'api_secret' => 'h_bfpaCLme-m3T20BEyyH6TfHEo'], // <<< IMPORTANT: Replace with your FULL API Secret
  'url' => [
    'secure' => true]]);

/**
 * Uploads a file to Cloudinary.
 *
 * @param array $file The file data from $_FILES (e.g., $_FILES['gallery_items']['tmp_name'][$index]['file']).
 * @param string $folder Optional folder name within Cloudinary.
 * @return array An associative array with ['secure_url'] and ['public_id'] on success.
 *               Returns ['error' => 'message'] on any failure (PHP upload, validation, Cloudinary API).
 */
function uploadToCloudinary($file, $folder = 'school_gallery_media') { // Changed default folder to match gallery usage
    // 1. Check for basic PHP upload errors (including no file chosen/submitted)
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error_message = "File upload failed: ";
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: $error_message .= "File is too large according to server limits."; break;
            case UPLOAD_ERR_PARTIAL: $error_message .= "File was only partially uploaded."; break;
            case UPLOAD_ERR_NO_FILE: $error_message .= "No file was selected for upload."; break; // More specific for this case
            case UPLOAD_ERR_NO_TMP_DIR: $error_message .= "Missing temporary folder on the server."; break;
            case UPLOAD_ERR_CANT_WRITE: $error_message .= "Failed to write file to disk on the server."; break;
            case UPLOAD_ERR_EXTENSION: $error_message .= "A PHP extension blocked the upload."; break;
            default: $error_message .= "Unknown upload error (Code: " . ($file['error'] ?? 'N/A') . ")."; break;
        }
        error_log("PHP File Upload Error in uploadToCloudinary: " . $error_message . " for file " . ($file['name'] ?? 'N/A'));
        return ['error' => $error_message]; // Always return an error array for consistent handling
    }

    // 2. Basic file validation (before sending to Cloudinary)
    // Increased size limit to accommodate videos, adjust as needed.
    $max_size = 50 * 1024 * 1024; // 50MB limit 
    $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_video_types = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/x-flv', 'video/3gpp', 'video/quicktime']; // Common video types
    $allowed_types = array_merge($allowed_image_types, $allowed_video_types);

    if ($file['size'] > $max_size) {
        return ['error' => 'File is too large. Maximum size is ' . ($max_size / (1024 * 1024)) . 'MB.'];
    }

    // Get actual MIME type using finfo_open for security
    $real_file_type = false;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
           $real_file_type = finfo_file($finfo, $file['tmp_name']);
           finfo_close($finfo);
        }
    }
    // Fallback if finfo_open is not available (less reliable)
    if ($real_file_type === false && function_exists('mime_content_type')) {
        $real_file_type = mime_content_type($file['tmp_name']);
    }

    if ($real_file_type === false || !in_array($real_file_type, $allowed_types)) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // Provide better error message for allowed types
        $allowed_ext_display = array_unique(array_merge(
            array_map(fn($mime) => explode('/', $mime)[1], $allowed_image_types),
            array_map(fn($mime) => str_replace(['video/', 'x-'], '', $mime), $allowed_video_types) // Simplified for display
        ));
        return ['error' => 'Invalid file type or extension (' . htmlspecialchars($file_ext) . '). Allowed: ' . implode(', ', $allowed_ext_display) . '.'];
    }

    // Determine resource type for Cloudinary dynamically
    $resource_type = in_array($real_file_type, $allowed_video_types) ? 'video' : 'image';

    try {
        // Perform the upload to Cloudinary
        $uploadOptions = [
            'folder' => $folder,
            'resource_type' => $resource_type, // Now dynamic
            'use_filename' => true,
            'unique_filename' => true, // Changed to true for better safety and unique public_ids
        ];

        $uploadResult = (new UploadApi())->upload($file['tmp_name'], $uploadOptions);

        if (isset($uploadResult['secure_url']) && isset($uploadResult['public_id'])) {
            return [
                'secure_url' => $uploadResult['secure_url'],
                'public_id' => $uploadResult['public_id']
            ];
        } else {
            error_log("Cloudinary upload returned unexpected structure for file " . ($file['name'] ?? 'N/A') . ": " . print_r($uploadResult, true));
            return ['error' => 'Cloudinary upload failed with unexpected response from the API.'];
        }

    } catch (UploadException $e) {
        error_log("Cloudinary UploadException for file " . ($file['name'] ?? 'N/A') . ": " . $e->getMessage());
        return ['error' => 'Cloudinary API upload failed: ' . $e->getMessage()];
    } catch (\Exception $e) {
        error_log("General Exception during Cloudinary upload for file " . ($file['name'] ?? 'N/A') . ": " . $e->getMessage());
        return ['error' => 'An unexpected server error occurred during upload: ' . $e->getMessage()];
    }
}