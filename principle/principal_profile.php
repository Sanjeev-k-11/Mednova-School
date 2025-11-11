<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

// Get principal's ID from session
$principal_id = $_SESSION["id"];
$principal_data = null;
$van_data = null;

// --- DATA FETCHING ---
$sql = "SELECT 
            p.full_name, p.principle_code, p.email, p.phone_number, p.address, 
            p.pincode, p.image_url, p.salary, p.qualification, p.gender, 
            p.blood_group, p.dob, p.years_of_experience, p.date_of_joining, 
            p.van_service_taken, p.van_id
        FROM principles p
        WHERE p.id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $principal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $principal_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Fetch Van details if service is taken
if ($principal_data && $principal_data['van_service_taken'] == 1 && $principal_data['van_id']) {
    $sql_van = "SELECT van_number, route_details, driver_name, khalasi_name FROM vans WHERE id = ?";
    if ($stmt_van = mysqli_prepare($link, $sql_van)) {
        mysqli_stmt_bind_param($stmt_van, "i", $principal_data['van_id']);
        mysqli_stmt_execute($stmt_van);
        $result_van = mysqli_stmt_get_result($stmt_van);
        $van_data = mysqli_fetch_assoc($result_van);
        mysqli_stmt_close($stmt_van);
    }
}

mysqli_close($link);

// --- PAGE INCLUDES ---
require_once './principal_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(-45deg, #4CAF50, #2196F3, #FFC107, #E91E63);
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .profile-container { 
            max-width: 900px; 
            margin: auto; 
            padding: 20px; 
            margin-top: 80px; 
            margin-bottom: 100px; 
            background: rgba(255, 255, 255, 0.9); /* Slightly opaque white background */
            backdrop-filter: blur(5px); /* Soft blur effect */
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
        }
        .profile-header { 
            text-align: center; 
            margin-bottom: 30px; 
            padding-bottom: 20px; 
            border-bottom: 1px solid #eee; 
            color: #1e2a4c;
        }
        .profile-header h1 { 
            font-size: 2.5em; 
            margin-bottom: 10px; 
            color: #1e2a4c;
        }
        .profile-image-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px auto;
            border: 5px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .detail-card {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e0e0e0;
        }
        .detail-card h3 {
            font-size: 1.3em;
            color: #333;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 10px;
        }
        .detail-item {
            display: flex;
            margin-bottom: 10px;
            align-items: center;
        }
        .detail-item strong {
            width: 150px; /* Adjust as needed */
            color: #555;
            font-weight: 600;
            flex-shrink: 0;
            margin-right: 10px;
        }
        .detail-item span {
            flex-grow: 1;
            color: #333;
        }
        .detail-item:last-child {
            margin-bottom: 0;
        }
        .empty-profile-message {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: #6c757d;
        }
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        .action-buttons .btn {
            background-color: #2196F3;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .action-buttons .btn:hover {
            background-color: #1976D2;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<div class="profile-container">
    <?php if ($principal_data): ?>
        <div class="profile-header">
            <div class="profile-image-container">
                <img src="<?php echo htmlspecialchars($principal_data['image_url'] ?: '../assets/images/default_profile.png'); ?>" alt="Profile Image" class="profile-image">
            </div>
            <h1><?php echo htmlspecialchars($principal_data['full_name']); ?></h1>
            <p class="text-muted">Principal Code: <?php echo htmlspecialchars($principal_data['principle_code']); ?></p>
        </div>

        <div class="profile-details-grid">
            <!-- Personal Details -->
            <div class="detail-card">
                <h3><i class="fas fa-user-circle"></i> Personal Details</h3>
                <div class="detail-item"><strong>Full Name:</strong> <span><?php echo htmlspecialchars($principal_data['full_name']); ?></span></div>
                <div class="detail-item"><strong>Gender:</strong> <span><?php echo htmlspecialchars($principal_data['gender'] ?: 'N/A'); ?></span></div>
                <div class="detail-item"><strong>Date of Birth:</strong> <span><?php echo ($principal_data['dob'] && $principal_data['dob'] !== '0000-00-00') ? date("M j, Y", strtotime($principal_data['dob'])) : 'N/A'; ?></span></div>
                <div class="detail-item"><strong>Blood Group:</strong> <span><?php echo htmlspecialchars($principal_data['blood_group'] ?: 'N/A'); ?></span></div>
            </div>

            <!-- Contact Information -->
            <div class="detail-card">
                <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                <div class="detail-item"><strong>Email:</strong> <span><?php echo htmlspecialchars($principal_data['email']); ?></span></div>
                <div class="detail-item"><strong>Phone:</strong> <span><?php echo htmlspecialchars($principal_data['phone_number'] ?: 'N/A'); ?></span></div>
                <div class="detail-item"><strong>Address:</strong> <span><?php echo nl2br(htmlspecialchars($principal_data['address'] ?: 'N/A')); ?></span></div>
                <div class="detail-item"><strong>Pincode:</strong> <span><?php echo htmlspecialchars($principal_data['pincode'] ?: 'N/A'); ?></span></div>
            </div>

            <!-- Professional Information -->
            <div class="detail-card">
                <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
                <div class="detail-item"><strong>Qualification:</strong> <span><?php echo htmlspecialchars($principal_data['qualification'] ?: 'N/A'); ?></span></div>
                <div class="detail-item"><strong>Salary:</strong> <span><?php echo ($principal_data['salary'] !== NULL) ? '₹' . number_format($principal_data['salary'], 2) : 'N/A'; ?></span></div>
                <div class="detail-item"><strong>Years of Exp.:</strong> <span><?php echo htmlspecialchars($principal_data['years_of_experience'] ?? 0); ?> years</span></div>
                <div class="detail-item"><strong>Date of Joining:</strong> <span><?php echo ($principal_data['date_of_joining'] && $principal_data['date_of_joining'] !== '0000-00-00') ? date("M j, Y", strtotime($principal_data['date_of_joining'])) : 'N/A'; ?></span></div>
            </div>

            <!-- Van Service Details -->
            <div class="detail-card">
                <h3><i class="fas fa-bus"></i> Van Service</h3>
                <div class="detail-item"><strong>Service Taken:</strong> <span><?php echo ($principal_data['van_service_taken'] == 1) ? 'Yes' : 'No'; ?></span></div>
                <?php if ($principal_data['van_service_taken'] == 1): ?>
                    <?php if ($van_data): ?>
                        <div class="detail-item"><strong>Van Number:</strong> <span><?php echo htmlspecialchars($van_data['van_number']); ?></span></div>
                        <div class="detail-item"><strong>Route:</strong> <span><?php echo htmlspecialchars($van_data['route_details'] ?: 'N/A'); ?></span></div>
                        <div class="detail-item"><strong>Driver:</strong> <span><?php echo htmlspecialchars($van_data['driver_name'] ?: 'N/A'); ?></span></div>
                        <div class="detail-item"><strong>Khalasi:</strong> <span><?php echo htmlspecialchars($van_data['khalasi_name'] ?: 'N/A'); ?></span></div>
                    <?php else: ?>
                        <div class="detail-item"><strong>Van Details:</strong> <span>Not Assigned / Found</span></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="action-buttons">
            <!-- Link to an edit profile page (you'll need to create principal_edit_profile.php) -->
            <a href="principal_edit_profile.php" class="btn"><i class="fas fa-edit"></i> Edit Profile</a>
        </div>

    <?php else: ?>
        <p class="empty-profile-message">Principal profile data not found. Please contact support if this persists.</p>
    <?php endif; ?>
</div>
</body>
</html>

<?php
require_once './principal_footer.php';
?>