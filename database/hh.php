<?php
// C:\xampp\htdocs\new school\helpers\cloudinary_upload_helper.php

// Ensure session is started if not already, for set_session_message to work
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// This helper needs config.php for Cloudinary credentials and set_session_message()
require_once __DIR__ . '/./config.php'; // Adjust path if helper is in a different location

// Include Composer's autoloader for Cloudinary library
// ** Ensure composer install has been run in the root directory (e.g., C:\xampp\htdocs\new school\) **
// and that 'vendor/autoload.php' exists relative to this helper file.
require_once __DIR__ . '/./vendor/autoload.php';

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Exception\Upload\UploadException;

// Configure Cloudinary (replace with your actual credentials from config.php)
Configuration::instance([
  'cloud' => [
    'cloud_name' => 'do9mane7a', // Replace with your Cloud Name
    'api_key' => '269318732537666',       // Replace with your API Key
    'api_secret' => 'h_bfpaCLme-m3T20BEyyH6TfHEo'], // Replace with your API Secret
  'url' => [
    'secure' => true]]);

/**
 * Uploads a file to Cloudinary.
 *
 * @param array $file The file data from $_FILES (e.g., $_FILES['document_file']).
 * @param string $folder Optional folder name within Cloudinary.
 * @return array|false On success, an associative array with ['secure_url'], ['public_id'], ['file_type'], ['file_size'].
 *                         Returns ['error' => 'message'] on validation or Cloudinary error.
 *                         Returns false if no file was uploaded or a basic PHP upload error occurred.
 */
function uploadToCloudinary($file, $folder = 'school_documents') {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] == UPLOAD_ERR_NO_FILE) {
            return false; // Indicate no file was submitted (not an error, just no upload)
        }
        $error_message = "File upload failed: ";
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE: $error_message .= "File is too large (php.ini limit)."; break;
            case UPLOAD_ERR_FORM_SIZE: $error_message .= "File is too large (HTML form limit)."; break;
            case UPLOAD_ERR_PARTIAL: $error_message .= "File was only partially uploaded."; break;
            case UPLOAD_ERR_NO_TMP_DIR: $error_message .= "Missing temporary folder on the server."; break;
            case UPLOAD_ERR_CANT_WRITE: $error_message .= "Failed to write file to disk on the server."; break;
            case UPLOAD_ERR_EXTENSION: $error_message .= "A PHP extension blocked the upload."; break;
            default: $error_message .= "Unknown upload error (Code: " . $file['error'] . ")."; break;
        }
        error_log("PHP File Upload Error in uploadToCloudinary: " . $error_message . " for file " . ($file['name'] ?? 'N/A'));
        return ['error' => $error_message];
    }

    $max_size = 50 * 1024 * 1024; // 50MB limit for documents
    // Common document types. Add/remove as needed.
    $allowed_types = [
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'
    ];

    if ($file['size'] > $max_size) {
        return ['error' => 'File is too large. Maximum size is 50MB.'];
    }

    $real_file_type = false;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
           $real_file_type = finfo_file($finfo, $file['tmp_name']);
           finfo_close($finfo);
        }
    }
    // Fallback if finfo not available/fails
    if ($real_file_type === false && function_exists('mime_content_type')) {
        $real_file_type = mime_content_type($file['tmp_name']);
    }

    if ($real_file_type === false || !in_array($real_file_type, $allowed_types)) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return ['error' => 'Invalid file type or extension (' . htmlspecialchars($file_ext) . '). Allowed: PDF, Word, Excel, PowerPoint, Text, Image files.'];
    }

    // Determine Cloudinary resource_type based on MIME type for better handling
    $cloudinary_resource_type = 'raw'; // Default for documents
    if (str_starts_with($real_file_type, 'image/')) {
        $cloudinary_resource_type = 'image';
    } else if (str_starts_with($real_file_type, 'video/')) {
         $cloudinary_resource_type = 'video';
    }


    try {
        $uploadResult = (new UploadApi())->upload($file['tmp_name'], [
            'folder' => $folder,
            'resource_type' => $cloudinary_resource_type, // Use determined resource type
            'use_filename' => true,
            'unique_filename' => false,
            'overwrite' => false, // Set to true if you want to overwrite files with same public_id
            'filename_override' => pathinfo($file['name'], PATHINFO_FILENAME) // Use original filename, without extension
        ]);

        if (isset($uploadResult['secure_url']) && isset($uploadResult['public_id'])) {
            return [
                'secure_url' => $uploadResult['secure_url'],
                'public_id' => $uploadResult['public_id'],
                'file_type' => $real_file_type, // Return the detected type
                'file_size' => $file['size'] // Return the size in bytes
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
        return ['error' => 'An unexpected server error occurred during upload.'];
    }
}