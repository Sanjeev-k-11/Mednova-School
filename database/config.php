<?php
//database/config.php

date_default_timezone_set('Asia/Kolkata');


define('DB_SERVER', 'localhost:3306');
define('DB_USERNAME', 'jqopkpgo_new_school_mednova'); // <--- Change this
define('DB_PASSWORD', 'Kumar@2004'); // <--- Change this
define('DB_NAME', 'jqopkpgo_new_school'); 

// --- VAPID Keys for Push Notifications ---
// IMPORTANT: REPLACE 'YOUR_VAPID_PUBLIC_KEY_HERE' and 'YOUR_VAPID_PRIVATE_KEY_HERE'
// with the actual VAPID Public and Private Keys you generated.
define('VAPID_PUBLIC_KEY', 'YOUR_VAPID_PUBLIC_KEY_HERE');
define('VAPID_PRIVATE_KEY', 'YOUR_VAPID_PRIVATE_KEY_HERE');
define('VAPID_SUBJECT', 'mailto:sy781405@gmail.com');


// --- Cloudinary Credentials ---
// IMPORTANT: REPLACE THESE with your actual Cloudinary API credentials.
define('CLOUDINARY_CLOUD_NAME', 'YOUR_CLOUD_NAME_HERE'); // e.g., 'my-school-cloud'
define('CLOUDINARY_API_KEY', 'YOUR_API_KEY_HERE');       // e.g., '123456789012345'
define('CLOUDINARY_API_SECRET', 'YOUR_API_SECRET_HERE'); // e.g., 'abcdefghijklmnopqrstuvwxyz12345'


/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Set charset for proper data handling
if (!$link->set_charset("utf8mb4")) {
    printf("Error loading character set utf8mb4: %s\n", $link->error);
}

// --- Global Helper Function: set_session_message ---
if (!function_exists('set_session_message')) {
    function set_session_message($msg, $type) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['message'] = $msg;
        $_SESSION['message_type'] = $type;
    }
}

// --- Global PHP Helper function to format file size ---
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes, $decimals = 2) {
        if ($bytes === 0 || $bytes === null) return '0 Bytes';
        $k = 1024;
        $dm = $decimals < 0 ? 0 : $decimals;
        $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
    }
}

// --- Global Helper Function: get_stat ---
// Fetches a single aggregate value from the database
if (!function_exists('get_stat')) {
    function get_stat($link, $sql, $params = [], $types = "") {
        if ($stmt = mysqli_prepare($link, $sql)) {
            if (!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $value = mysqli_fetch_row($result)[0] ?? 0;
            mysqli_stmt_close($stmt);
            return $value;
        }
        return 0;
    }
}

?>