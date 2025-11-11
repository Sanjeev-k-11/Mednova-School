
<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./student_header.php";   // Includes student-specific authentication and sidebar

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php"); 
    exit;
}

$student_id = $_SESSION['id'] ?? null; 

if (!isset($student_id) || !is_numeric($student_id) || $student_id <= 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
            <strong class='font-bold'>Authentication Error!</strong>
            <span class='block sm:inline'> Your student ID is missing or invalid in the session. Please log in again.</span>
          </div>";
    require_once "./student_footer.php"; 
    if($link) mysqli_close($link);
    exit();
}

// --- DATA FETCHING ---
$student_transport_info = null;

// Fetch the student's van_service_taken status and assigned van details if applicable
$sql_student_transport = "
    SELECT 
        s.van_service_taken,
        s.van_id,
        v.van_number,
        v.route_details,
        v.driver_name,
        v.khalasi_name,
        v.fee_amount,
        v.status AS van_status
    FROM students s
    LEFT JOIN vans v ON s.van_id = v.id
    WHERE s.id = ?
    LIMIT 1;
";

if ($stmt = mysqli_prepare($link, $sql_student_transport)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_transport_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    // Handle database error gracefully
    error_log("DB Error preparing student transport query: " . mysqli_error($link));
    // Set a flag or message to display an error on the page
    $db_error = true;
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Transport Details - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .dashboard-container {
            min-height: calc(100vh - 80px); /* Adjust based on header/footer height */
        }
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-100 mt-28 font-sans antialiased">
<!-- student_header.php content usually goes here -->

<div class="dashboard-container p-4 sm:p-6">
    <!-- Main Header Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">My Transport Details</h1>
        <p class="text-gray-600">View information about your school transportation service.</p>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="max-w-4xl mx-auto bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-center shadow-md">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">Could not load transport details. Please try again later or contact support.</span>
        </div>
    <?php elseif ($student_transport_info && $student_transport_info['van_service_taken'] && $student_transport_info['van_id']): ?>
        <!-- Student IS using transport service -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Van Details Card -->
            <div class="info-card text-white rounded-xl shadow-lg p-6 transform hover:scale-105 transition-transform duration-300">
                <div class="flex items-center mb-4">
                    <i class="fas fa-bus-alt fa-3x mr-4"></i>
                    <div>
                        <p class="text-sm opacity-80">Your Van Number</p>
                        <h2 class="text-3xl font-bold"><?php echo htmlspecialchars($student_transport_info['van_number']); ?></h2>
                    </div>
                </div>
                <p class="text-sm opacity-80">Status: 
                    <span class="font-semibold <?php echo ($student_transport_info['van_status'] === 'Active') ? 'text-green-300' : 'text-yellow-300'; ?>">
                        <?php echo htmlspecialchars($student_transport_info['van_status']); ?>
                    </span>
                </p>
            </div>

            <!-- Driver Details Card -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Driver & Staff</h3>
                <div class="space-y-4">
                    <div class="flex items-center">
                        <i class="fas fa-user-tie fa-2x text-gray-500 w-10 text-center mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-500">Driver's Name</p>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($student_transport_info['driver_name'] ?: 'Not Assigned'); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-user-friends fa-2x text-gray-500 w-10 text-center mr-4"></i>
                        <div>
                            <p class="text-sm text-gray-500">Khalasi's Name</p>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($student_transport_info['khalasi_name'] ?: 'Not Assigned'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Details Card -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Fee Information</h3>
                <div class="flex items-center">
                    <i class="fas fa-rupee-sign fa-2x text-green-500 w-10 text-center mr-4"></i>
                    <div>
                        <p class="text-sm text-gray-500">Transport Fee (Monthly)</p>
                        <p class="text-2xl font-bold text-gray-900">₹<?php echo number_format($student_transport_info['fee_amount'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Route Details Card -->
            <div class="md:col-span-2 lg:col-span-3 bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-map-marked-alt mr-2 text-indigo-500"></i> Your Route Details
                </h3>
                <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($student_transport_info['route_details'])); ?></p>
            </div>
        </div>

    <?php else: ?>
        <!-- Student is NOT using transport service -->
        <div class="bg-white rounded-xl shadow-lg p-8 text-center max-w-2xl mx-auto">
            <i class="fas fa-bus-alt fa-4x text-gray-400 mb-4"></i>
            <h2 class="text-2xl font-bold mb-2 text-gray-900">You are not subscribed to the transport service.</h2>
            <p class="text-gray-600 text-lg">If you would like to opt-in for school transportation, please contact the administrative office.</p>
            <div class="mt-6">
                <a href="contact.php" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-full shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-phone-alt mr-3"></i> Contact Us
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- student_footer.php content usually goes here -->
<?php require_once "./student_footer.php"; ?>
</body>
</html>