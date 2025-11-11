<?php
/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */

date_default_timezone_set('Asia/Kolkata');


define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // <--- Change this
define('DB_PASSWORD', ''); // <--- Change this
define('DB_NAME', 'new_school');    // <--- Change this

/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Set charset
if (!$link->set_charset("utf8mb4")) {
    printf("Error loading character set utf8mb4: %s\n", $link->error);
    // You might want to handle this error more gracefully in production
}


// --- Super Admin Data Setup Code ---

// 1. Define sample super admin data
$fullName = "John Doe";
$role = "Super Admin"; // Default role
$superAdminId = "123456";
$email = "john.doe@example.com";
$address = "123 Admin Street, Kolkata, India";
$imageUrl = "https://placehold.co/150x150/cccccc/ffffff?text=John+Doe"; // Placeholder image
$gender = "Male";
// Use a strong password for production, for example: "SecurePass123!"
$plainPassword = "123456";

// 2. Hash the password for security
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// 3. Prepare an INSERT statement to prevent SQL injection
$sql = "INSERT INTO super_admin (full_name, role, super_admin_id, password, email, address, image_url, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

if($stmt = mysqli_prepare($link, $sql)){
    // 4. Bind parameters to the prepared statement as strings
    mysqli_stmt_bind_param($stmt, "ssssssss", $param_fullName, $param_role, $param_superAdminId, $param_password, $param_email, $param_address, $param_imageUrl, $param_gender);

    // 5. Set parameters
    $param_fullName = $fullName;
    $param_role = $role;
    $param_superAdminId = $superAdminId;
    $param_password = $hashedPassword; // Store the hashed password
    $param_email = $email;
    $param_address = $address;
    $param_imageUrl = $imageUrl;
    $param_gender = $gender;

    // 6. Attempt to execute the prepared statement
    if(mysqli_stmt_execute($stmt)){
        echo "Super Admin data inserted successfully! 🚀";
    } else{
        echo "ERROR: Could not execute query: " . mysqli_stmt_error($stmt);
    }

    // Close statement
    mysqli_stmt_close($stmt);
} else {
    echo "ERROR: Could not prepare query: " . mysqli_error($link);
}

// Close connection
mysqli_close($link);

?>
