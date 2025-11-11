<?php
session_start();
require_once "../database/config.php";
require_once '../database/cloudinary_upload_handler.php'; // For image uploads

// --- AUTHENTICATION & INITIALIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- FLASH MESSAGE HANDLING ---
$message = $_SESSION['flash_message'] ?? "";
$message_type = $_SESSION['flash_message_type'] ?? "";
unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);


// --- HANDLE PROFILE UPDATE (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $pincode = trim($_POST['pincode']);
    
    $image_url_to_update = $_SESSION['image_url']; 
    $upload_error = false;

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadToCloudinary($_FILES['profile_image'], 'teacher_photos'); 
        if (isset($upload_result['secure_url'])) {
            $image_url_to_update = $upload_result['secure_url'];
        } else {
            $_SESSION['flash_message'] = "Image upload failed: " . ($upload_result['error'] ?? 'Unknown error.');
            $_SESSION['flash_message_type'] = "error";
            $upload_error = true;
        }
    }

    if (!$upload_error) {
        $sql_update = "UPDATE teachers SET full_name = ?, email = ?, phone_number = ?, address = ?, pincode = ?, image_url = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql_update)) {
            mysqli_stmt_bind_param($stmt, "ssssssi", $full_name, $email, $phone_number, $address, $pincode, $image_url_to_update, $teacher_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['image_url'] = $image_url_to_update;
                $_SESSION['flash_message'] = "Profile updated successfully!";
                $_SESSION['flash_message_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Error updating profile. The email might already be in use.";
                $_SESSION['flash_message_type'] = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}


// --- DATA FETCHING ---
$profile_data = [];
$sql_profile = "SELECT * FROM teachers WHERE id = ?";
if ($stmt_profile = mysqli_prepare($link, $sql_profile)) {
    mysqli_stmt_bind_param($stmt_profile, "i", $teacher_id);
    mysqli_stmt_execute($stmt_profile);
    $result = mysqli_stmt_get_result($stmt_profile);
    if (!$profile_data = mysqli_fetch_assoc($result)) {
        die("Error: Could not retrieve teacher profile.");
    }
    mysqli_stmt_close($stmt_profile);
}

$class_teacher_info = "";
$sql_class_teacher = "SELECT class_name, section_name FROM classes WHERE teacher_id = ?";
if($stmt_ct = mysqli_prepare($link, $sql_class_teacher)){
    mysqli_stmt_bind_param($stmt_ct, "i", $teacher_id);
    mysqli_stmt_execute($stmt_ct);
    $result_ct = mysqli_stmt_get_result($stmt_ct);
    if($row = mysqli_fetch_assoc($result_ct)){
        $class_teacher_info = $row['class_name'] . ' - ' . $row['section_name'];
    }
    mysqli_stmt_close($stmt_ct);
}

$subjects_taught = [];
$sql_subjects = "SELECT DISTINCT s.subject_name FROM class_subject_teacher cst JOIN subjects s ON cst.subject_id = s.id WHERE cst.teacher_id = ?";
if($stmt_subj = mysqli_prepare($link, $sql_subjects)){
    mysqli_stmt_bind_param($stmt_subj, "i", $teacher_id);
    mysqli_stmt_execute($stmt_subj);
    $result_subj = mysqli_stmt_get_result($stmt_subj);
    while($row = mysqli_fetch_assoc($result_subj)){
        $subjects_taught[] = $row['subject_name'];
    }
    mysqli_stmt_close($stmt_subj);
}
mysqli_close($link);

require_once './teacher_header.php';
?>

<!-- ====== START: Custom Styles for 3D Effect ====== -->
<style>
    /* Creates the 3D space for the card to exist in */
    .perspective-container {
        perspective: 1000px;
    }

    /* The card's default state and transition for the hover effect */
    #profile-card {
        transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        transform-style: preserve-3d;
    }

    /* The 3D transformation on hover */
    .perspective-container:hover #profile-card {
        transform: rotateY(3deg) rotateX(5deg) scale(1.02);
    }
</style>
<!-- ====== END: Custom Styles for 3D Effect ====== -->

<body class="bg-slate-900 min-h-screen flex mt-28 items-center justify-center p-4">

<div class="w-full max-w-6xl mx-auto  mt-28 mb-28 perspective-container">

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg shadow-lg <?php echo $message_type === 'success' ? 'bg-green-500/80' : 'bg-red-500/80'; ?> text-white text-center" role="alert">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- The Main Profile Card -->
    <div id="profile-card" class="bg-gradient-to-br from-pink-500 via-purple-600 to-cyan-400 text-white shadow-2xl rounded-3xl">
        <!-- Profile Header -->
        <div class="p-8 flex flex-col md:flex-row items-center justify-between">
            <div class="flex items-center">
                <div class="w-28 h-28 rounded-full border-4 border-white/50 bg-white shadow-lg overflow-hidden flex-shrink-0">
                    <img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($profile_data['image_url'] ?? '../assets/images/default-avatar.png'); ?>" alt="Profile Photo">
                </div>
                <div class="ml-6 text-center md:text-left mt-4 md:mt-0">
                    <h1 class="text-4xl font-bold"><?php echo htmlspecialchars($profile_data['full_name']); ?></h1>
                    <p class="text-lg text-white/70">Teacher Code: <?php echo htmlspecialchars($profile_data['teacher_code']); ?></p>
                </div>
            </div>
            <button onclick="openEditModal()" class="mt-6 md:mt-0 bg-white/20 backdrop-blur-sm border border-white/30 font-semibold py-2 px-6 rounded-full shadow-lg hover:bg-white/30 transition duration-300">
                Edit Profile
            </button>
        </div>

        <!-- Separator -->
        <div class="mx-8 border-t border-white/20"></div>

        <!-- Profile Details Grid -->
        <div class="p-8 grid grid-cols-1 md:grid-cols-3 gap-12">
            <!-- Column 1: Personal Info -->
            <div>
                <h3 class="font-bold text-xl mb-6">Personal Information</h3>
                <dl class="space-y-3 text-md">
                    <div class="flex justify-between"><dt class="font-semibold text-white/60">Email</dt><dd><?php echo htmlspecialchars($profile_data['email']); ?></dd></div>
                    <div class="flex justify-between"><dt class="font-semibold text-white/60">Phone</dt><dd><?php echo htmlspecialchars($profile_data['phone_number'] ?? 'N/A'); ?></dd></div>
                    <div class="flex justify-between"><dt class="font-semibold text-white/60">Gender</dt><dd><?php echo htmlspecialchars(ucfirst($profile_data['gender'] ?? 'N/A')); ?></dd></div>
                    <div class="flex justify-between"><dt class="font-semibold text-white/60">Date of Birth</dt><dd><?php echo date("M j, Y", strtotime($profile_data['dob'])); ?></dd></div>
                    <div class="flex justify-between"><dt class="font-semibold text-white/60">Address</dt><dd class="text-right"><?php echo htmlspecialchars($profile_data['address'] ?? 'N/A') . ', ' . htmlspecialchars($profile_data['pincode'] ?? ''); ?></dd></div>
                </dl>
            </div>
            <!-- Column 2: Professional Info -->
            <div>
                <h3 class="font-bold text-xl mb-6">Professional Details</h3>
                <dl class="space-y-3 text-md">
                    <div class="flex justify-between"><dt class="font-semibold text-white/60">Qualification</dt><dd><?php echo htmlspecialchars($profile_data['qualification']); ?></dd></div>
                    <div class="flex justify-between"><dt class="font-semibold text-white/60">Experience</dt><dd><?php echo htmlspecialchars($profile_data['years_of_experience']); ?> years</dd></div>
                    <div class="flex justify-between"><dt class="font-semibold text-white/60">Date of Joining</dt><dd><?php echo date("M j, Y", strtotime($profile_data['date_of_joining'])); ?></dd></div>
                    <?php if(!empty($class_teacher_info)): ?>
                        <div class="flex justify-between items-center"><dt class="font-semibold text-white/60">Class Teacher</dt><dd><span class="bg-cyan-200/80 text-cyan-900 font-bold px-3 py-1 rounded-full text-sm"><?php echo htmlspecialchars($class_teacher_info); ?></span></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
            <!-- Column 3: Subjects Taught -->
            <div>
                <h3 class="font-bold text-xl mb-6">Subjects Taught</h3>
                <?php if(empty($subjects_taught)): ?>
                    <p class="text-white/60">Not assigned to any subjects.</p>
                <?php else: ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach($subjects_taught as $subject): ?>
                            <span class="bg-white/20 text-white font-medium px-3 py-1 rounded-full"><?php echo htmlspecialchars($subject); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- Edit Profile Modal -->
<div id="editProfileModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm overflow-y-auto h-full w-full z-50 hidden flex items-center justify-center p-4">
  <div class="relative w-full max-w-2xl mx-auto">
    <div class="bg-gradient-to-br from-pink-600 to-purple-700 border border-white/20 text-white rounded-2xl shadow-2xl">
      
      <!-- Header -->
      <div class="flex justify-between items-center border-b border-white/20 p-5">
        <h3 class="text-xl font-semibold">Edit Your Profile</h3>
        <button onclick="document.getElementById('editProfileModal').classList.add('hidden')" class="text-white/70 hover:text-white text-3xl font-light">&times;</button>
      </div>

      <!-- Form -->
      <form method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="p-6 space-y-6">
          
           

          <!-- Input Fields -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="full_name" class="block text-sm font-medium text-white/80 mb-2">Full Name</label>
              <input type="text" name="full_name" value="<?php echo htmlspecialchars($profile_data['full_name']); ?>" 
                placeholder="Enter your full name"
                class="w-full px-4 py-3 text-black bg-white/10 border border-white/30 rounded-lg shadow-sm 
                       focus:ring-2 focus:ring-cyan-400 focus:border-cyan-400 placeholder-white/50 transition" required>
            </div>
            <div>
              <label for="email" class="block text-sm font-medium text-white/80 mb-2">Email Address</label>
              <input type="email" name="email" value="<?php echo htmlspecialchars($profile_data['email']); ?>" 
                placeholder="Enter your email"
                class="w-full px-4 py-3 text-black bg-white/10 border border-white/30 rounded-lg shadow-sm 
                       focus:ring-2 focus:ring-cyan-400 focus:border-cyan-400 placeholder-white/50 transition" required>
            </div>
          </div>

          <div>
            <label for="address" class="block text-sm font-medium text-white/80 mb-2">Address</label>
            <textarea name="address" rows="3" 
              placeholder="Enter your full address"
              class="w-full px-4 py-3 text-black bg-white/10 border border-white/30 rounded-lg shadow-sm 
                     focus:ring-2 focus:ring-cyan-400 focus:border-cyan-400 placeholder-white/50 transition"><?php echo htmlspecialchars($profile_data['address']); ?></textarea>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end items-center p-5 bg-black/20 border-t border-white/20 rounded-b-xl">
          <button type="button" onclick="document.getElementById('editProfileModal').classList.add('hidden')" class="bg-white/20 hover:bg-white/30 font-bold py-2 px-6 rounded-lg mr-3 transition-colors">
            Cancel
          </button>
          <button type="submit" class="bg-cyan-500 hover:bg-cyan-600 font-bold py-2 px-6 rounded-lg transition-colors">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
function openEditModal() {
    document.getElementById('editProfileModal').classList.remove('hidden');
}

function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function(){
        const output = document.getElementById('image-preview');
        output.src = reader.result;
    };
    if (event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}
</script>

<?php require_once './teacher_footer.php'; ?>
</body>