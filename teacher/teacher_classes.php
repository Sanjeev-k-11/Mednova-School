<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- PAGINATION & FILTER SETUP ---
$records_per_page = 12;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$search_query = $_GET['search'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$van_filter = $_GET['van_service'] ?? '';


// --- DATA FETCHING ---
$assigned_classes = [];
$sql_classes = "SELECT DISTINCT c.id, c.class_name, c.section_name FROM class_subject_teacher cst JOIN classes c ON cst.class_id = c.id WHERE cst.teacher_id = ? ORDER BY c.class_name, c.section_name";
if ($stmt_classes = mysqli_prepare($link, $sql_classes)) {
    mysqli_stmt_bind_param($stmt_classes, "i", $teacher_id);
    mysqli_stmt_execute($stmt_classes);
    $result = mysqli_stmt_get_result($stmt_classes);
    $assigned_classes = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_classes);
}

// Determine selected class
$selected_class_id = null;
$selected_class_name = "";
if (isset($_GET['class_id']) && !empty($assigned_classes)) {
    $get_class_id = $_GET['class_id'];
    foreach ($assigned_classes as $ac) {
        if ($ac['id'] == $get_class_id) {
            $selected_class_id = $ac['id'];
            $selected_class_name = $ac['class_name'] . ' - ' . $ac['section_name'];
            break;
        }
    }
}
if (!$selected_class_id && !empty($assigned_classes)) {
    $selected_class_id = $assigned_classes[0]['id'];
    $selected_class_name = $assigned_classes[0]['class_name'] . ' - ' . $assigned_classes[0]['section_name'];
}

// Fetch students for the selected class WITH pagination and filters
$students = [];
$total_records = 0;
if ($selected_class_id) {
    // Build WHERE clause and params for filtering
    $from_clause = "FROM students s LEFT JOIN vans v ON s.van_id = v.id";
    $where_clause = "WHERE s.class_id = ?";
    $params = [$selected_class_id];
    $types = "i";

    if (!empty($search_query)) {
        $where_clause .= " AND (CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.registration_number LIKE ?)";
        $search_term = "%" . $search_query . "%";
        array_push($params, $search_term, $search_term);
        $types .= "ss";
    }
    if (!empty($gender_filter)) {
        $where_clause .= " AND s.gender = ?";
        $params[] = $gender_filter;
        $types .= "s";
    }
    if ($van_filter !== '') {
        $where_clause .= " AND s.van_service_taken = ?";
        $params[] = $van_filter;
        $types .= "i";
    }

    // First, get the total count
    $sql_count = "SELECT COUNT(s.id) as total " . $from_clause . " " . $where_clause;
    if ($stmt_count = mysqli_prepare($link, $sql_count)) {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
        mysqli_stmt_execute($stmt_count);
        $total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
        mysqli_stmt_close($stmt_count);
    }

    // Now, fetch the students for the current page
    $sql_students = "SELECT s.*, v.van_number " . $from_clause . " " . $where_clause . " ORDER BY s.first_name, s.last_name LIMIT ? OFFSET ?";
    array_push($params, $records_per_page, $offset);
    $types .= "ii";

    if ($stmt_students = mysqli_prepare($link, $sql_students)) {
        mysqli_stmt_bind_param($stmt_students, $types, ...$params);
        mysqli_stmt_execute($stmt_students);
        $result_students = mysqli_stmt_get_result($stmt_students);
        $students = mysqli_fetch_all($result_students, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_students);
    }
}
$total_pages = ceil($total_records / $records_per_page);

mysqli_close($link);
require_once './teacher_header.php';
?>

<!-- Custom Styles -->
<style>
    body {
        background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
        background-size: 400% 400%;
        animation: gradientBG 15s ease infinite;
        color: white;
    }
    @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
    .glassmorphism {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .form-input, .form-select {
        background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.3); color: white; padding: 0.75rem 1rem;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); transition: all 0.2s ease-in-out;
    }
    .form-input::placeholder { color: rgba(255, 255, 255, 0.5); }
    .form-input:focus, .form-select:focus {
        background: rgba(0, 0, 0, 0.3); outline: none; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2), 0 0 0 2px #23d5ab;
    }
    .custom-select-wrapper { position: relative; }
    .custom-select-wrapper select { appearance: none; -webkit-appearance: none; padding-right: 2.5rem; }
    .custom-select-wrapper .select-arrow { position: absolute; top: 0; right: 0; bottom: 0; display: flex; align-items: center; padding: 0 1rem; pointer-events: none; }
    /* Modal Detail Styling */
    .detail-label { font-weight: 600; color: rgba(255,255,255,0.6); }
    .detail-value { color: white; }
</style>

<div class="container mx-auto mt-28 p-4 md:p-8">
    <h1 class="text-3xl md:text-4xl font-bold mb-6 text-white text-center shadow-sm">Your Assigned Classes</h1>

    <?php if (empty($assigned_classes)): ?>
        <div class="glassmorphism rounded-xl p-8 text-center"><p class="text-xl">You are not assigned to any classes.</p></div>
    <?php else: ?>
        <!-- Class Tabs & Main Content... -->
        <div class="flex flex-wrap justify-center gap-2 md:gap-4 mb-8">
             <?php foreach ($assigned_classes as $class): ?>
                <a href="?class_id=<?php echo $class['id']; ?>" class="px-4 py-2 rounded-full text-sm md:text-base font-semibold transition-transform transform hover:scale-105 <?php echo ($class['id'] == $selected_class_id) ? 'bg-white text-blue-600 shadow-lg' : 'glassmorphism hover:bg-white/20'; ?>">
                    <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="glassmorphism rounded-2xl p-4 md:p-6">
            <!-- Filter Form... -->
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6 items-end">
                 <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                <div>
                    <label for="search" class="block text-sm font-semibold text-white/80 mb-2">Search by Name/Reg. No.</label>
                    <input type="text" id="search" name="search" class="w-full form-input rounded-xl" placeholder="e.g., John Doe or 2024001" value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div>
                    <label for="gender" class="block text-sm font-semibold text-white/80 mb-2">Gender</label>
                    <div class="custom-select-wrapper">
                        <select id="gender" name="gender" class="w-full form-select rounded-xl"><option value="">All</option><option value="Male" <?php echo ($gender_filter == 'Male') ? 'selected' : ''; ?>>Male</option><option value="Female" <?php echo ($gender_filter == 'Female') ? 'selected' : ''; ?>>Female</option></select>
                        <div class="select-arrow"><i class="fas fa-chevron-down text-white/50"></i></div>
                    </div>
                </div>
                <div>
                    <label for="van_service" class="block text-sm font-semibold text-white/80 mb-2">Van Service</label>
                    <div class="custom-select-wrapper">
                        <select id="van_service" name="van_service" class="w-full form-select rounded-xl"><option value="">All</option><option value="1" <?php echo ($van_filter === '1') ? 'selected' : ''; ?>>Yes</option><option value="0" <?php echo ($van_filter === '0') ? 'selected' : ''; ?>>No</option></select>
                        <div class="select-arrow"><i class="fas fa-chevron-down text-white/50"></i></div>
                    </div>
                </div>
                <div class="flex gap-2"><button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 font-bold py-3 px-4 rounded-xl">Filter</button><a href="?class_id=<?php echo $selected_class_id; ?>" class="w-full text-center bg-gray-500 hover:bg-gray-600 font-bold py-3 px-4 rounded-xl">Reset</a></div>
            </form>
            
            <?php if (empty($students)): ?>
                <div class="text-center py-10"><p>No students found.</p></div>
            <?php else: ?>
                <!-- Student Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php foreach ($students as $student): ?>
                        <div class="glassmorphism rounded-xl p-4 flex flex-col transition-transform transform hover:-translate-y-1">
                            <div class="flex items-center mb-3">
                                <img src="<?php echo htmlspecialchars($student['image_url'] ?? '../assets/images/default-avatar.png'); ?>" class="w-16 h-16 rounded-full border-2 border-white/50 object-cover">
                                <div class="ml-4">
                                    <h3 class="font-bold text-lg leading-tight"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                                    <p class="text-sm text-white/70">Reg: <?php echo htmlspecialchars($student['registration_number']); ?></p>
                                </div>
                            </div>
                            <div class="text-sm space-y-2 flex-grow">
                                <p><i class="fas fa-id-card-alt w-5 mr-1 text-white/70"></i> <strong>Roll No:</strong> <?php echo htmlspecialchars($student['roll_number'] ?? 'N/A'); ?></p>
                                <p><i class="fas fa-phone w-5 mr-1 text-white/70"></i> <strong>Parent:</strong> <?php echo htmlspecialchars($student['parent_phone_number'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="flex justify-between items-center mt-4 pt-3 border-t border-white/20">
                                <?php if ($student['van_service_taken']): ?>
                                    <span class="inline-block bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-full"><i class="fas fa-bus mr-1"></i> Van User</span>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>
                                <button onclick='openStudentModal(<?php echo json_encode($student, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="text-xs bg-white/20 hover:bg-white/30 px-3 py-1 rounded-full">View Details</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination Controls... -->
                 <div class="mt-8 flex flex-col md:flex-row justify-between items-center text-sm">
                    <div class="mb-4 md:mb-0">Showing <strong><?php echo min($offset + 1, $total_records); ?></strong> to <strong><?php echo min($offset + $records_per_page, $total_records); ?></strong> of <strong><?php echo $total_records; ?></strong> records.</div>
                    <?php if ($total_pages > 1): ?>
                    <div class="flex items-center space-x-1">
                        <?php $query_params = http_build_query(array_filter(['class_id' => $selected_class_id, 'search' => $search_query, 'gender' => $gender_filter, 'van_service' => $van_filter])); /* Pagination links */ ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Student Details Modal -->
<div id="studentModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="glassmorphism rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 glassmorphism z-10 flex justify-between items-center p-4 border-b border-white/20">
            <h3 class="text-xl font-bold">Student Comprehensive Details</h3>
            <button onclick="document.getElementById('studentModal').classList.add('hidden')" class="text-2xl">&times;</button>
        </div>
        <div id="modalContent" class="p-6 space-y-6">
            <!-- Content will be injected by JavaScript -->
        </div>
    </div>
</div>

<script>
function openStudentModal(studentData) {
    const modal = document.getElementById('studentModal');
    const content = document.getElementById('modalContent');
    
    const dob = studentData.dob ? new Date(studentData.dob).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : 'N/A';
    const admission_date = studentData.admission_date ? new Date(studentData.admission_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : 'N/A';
    
    // Build the comprehensive HTML for the modal
    content.innerHTML = `
        <!-- Top Section -->
        <div class="flex flex-col sm:flex-row items-center gap-6 pb-6 border-b border-white/20">
            <img src="${studentData.image_url || '../assets/images/default-avatar.png'}" class="w-32 h-32 rounded-full border-4 border-white/50 object-cover" alt="Photo">
            <div class="text-center sm:text-left">
                <h4 class="text-3xl font-bold">${studentData.first_name} ${studentData.middle_name || ''} ${studentData.last_name}</h4>
                <div class="flex flex-wrap justify-center sm:justify-start gap-x-4 gap-y-1 mt-2 text-white/80">
                    <span>Reg: <strong>${studentData.registration_number}</strong></span>
                    <span>Roll: <strong>${studentData.roll_number || 'N/A'}</strong></span>
                    <span class="font-bold ${studentData.status === 'Active' ? 'text-green-300' : 'text-red-400'}">Status: ${studentData.status}</span>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
            <!-- Personal Details -->
            <div class="space-y-4">
                <h5 class="text-lg font-semibold border-b border-white/20 pb-2">Personal Details</h5>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span class="detail-label">Date of Birth</span><span class="detail-value">${dob}</span>
                    <span class="detail-label">Gender</span><span class="detail-value">${studentData.gender || 'N/A'}</span>
                    <span class="detail-label">Blood Group</span><span class="detail-value">${studentData.blood_group || 'N/A'}</span>
                </div>
            </div>

            <!-- Contact & Address -->
            <div class="space-y-4">
                <h5 class="text-lg font-semibold border-b border-white/20 pb-2">Contact & Address</h5>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span class="detail-label">Phone</span><span class="detail-value">${studentData.phone_number || 'N/A'}</span>
                    <span class="detail-label">Email</span><span class="detail-value">${studentData.email || 'N/A'}</span>
                    <span class="detail-label col-span-2">Address</span>
                    <span class="detail-value col-span-2">${studentData.address || 'N/A'}, ${studentData.district || ''}, ${studentData.state || ''} - ${studentData.pincode || ''}</span>
                </div>
            </div>

            <!-- Parental Information -->
            <div class="space-y-4">
                <h5 class="text-lg font-semibold border-b border-white/20 pb-2">Parental Information</h5>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span class="detail-label">Father's Name</span><span class="detail-value">${studentData.father_name || 'N/A'}</span>
                    <span class="detail-label">Mother's Name</span><span class="detail-value">${studentData.mother_name || 'N/A'}</span>
                    <span class="detail-label">Parent's Phone</span><span class="detail-value">${studentData.parent_phone_number || 'N/A'}</span>
                    <span class="detail-label">Father's Occupation</span><span class="detail-value">${studentData.father_occupation || 'N/A'}</span>
                </div>
            </div>

            <!-- Academic & Transport -->
            <div class="space-y-4">
                <h5 class="text-lg font-semibold border-b border-white/20 pb-2">Academic & Transport</h5>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span class="detail-label">Admission Date</span><span class="detail-value">${admission_date}</span>
                    <span class="detail-label">Previous School</span><span class="detail-value">${studentData.previous_school || 'N/A'}</span>
                    <span class="detail-label">Previous Class</span><span class="detail-value">${studentData.previous_class || 'N/A'}</span>
                    <span class="detail-label">Van Service</span>
                    <span class="detail-value">${studentData.van_service_taken ? `Yes (${studentData.van_number || 'Van details not found'})` : 'No'}</span>
                </div>
            </div>
        </div>
    `;

    modal.classList.remove('hidden');
}
</script>

<?php require_once './teacher_footer.php'; ?>