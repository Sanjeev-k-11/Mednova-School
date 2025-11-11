<?php
session_start();
require_once "../database/config.php";

// Authentication and Authorization Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$message = "";
$message_type = "";

// Handle POST requests for Add, Update, Delete
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // Add new period
    if ($action == 'add') {
        $period_name = trim($_POST['period_name']);
        $start_time = trim($_POST['start_time']);
        $end_time = trim($_POST['end_time']);

        $sql = "INSERT INTO class_periods (period_name, start_time, end_time) VALUES (?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sss", $period_name, $start_time, $end_time);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Period added successfully.";
                $message_type = "success";
            } else {
                $message = "Error: Could not add period. It might already exist.";
                $message_type = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Update existing period
    if ($action == 'update') {
        $period_id = $_POST['period_id'];
        $period_name = trim($_POST['period_name']);
        $start_time = trim($_POST['start_time']);
        $end_time = trim($_POST['end_time']);
        
        $sql = "UPDATE class_periods SET period_name = ?, start_time = ?, end_time = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssi", $period_name, $start_time, $end_time, $period_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Period updated successfully.";
                $message_type = "success";
            } else {
                $message = "Error: Could not update period.";
                $message_type = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Delete period
    if ($action == 'delete') {
        $period_id = $_POST['period_id'];
        
        $sql = "DELETE FROM class_periods WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $period_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Period deleted successfully.";
                $message_type = "success";
            } else {
                $message = "Error: Could not delete period. It might be in use in a timetable.";
                $message_type = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch all periods to display
$periods = [];
$sql = "SELECT id, period_name, start_time, end_time FROM class_periods ORDER BY start_time ASC";
if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $periods[] = $row;
    }
    mysqli_free_result($result);
}
mysqli_close($link);

require_once './admin_header.php';
?>

<div class="container mx-auto mt-12 p-4 sm:p-6 lg:p-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Manage Class Periods</h1>
        <button onclick="openPeriodModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300">
            <i class="fas fa-plus mr-2"></i>Add New Period
        </button>
    </div>

    <?php if ($message): ?>
    <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Responsive Table -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Period Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Start Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">End Time</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($periods)): ?>
                        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No periods found. Please add one.</td></tr>
                    <?php else: foreach ($periods as $period): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($period['period_name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo date("g:i A", strtotime($period['start_time'])); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo date("g:i A", strtotime($period['end_time'])); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                            <button onclick='editPeriodModal(<?php echo json_encode($period); ?>)' class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this period?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Period Modal -->
<div id="periodModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center border-b pb-3">
            <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Add New Period</h3>
            <button onclick="document.getElementById('periodModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <div class="mt-5">
            <form id="periodForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="period_id" id="modalPeriodId">
                
                <div class="mb-4">
                    <label for="period_name" class="block text-sm font-medium text-gray-700">Period Name</label>
                    <input type="text" name="period_name" id="modalPeriodName" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="start_time" class="block text-sm font-medium text-gray-700">Start Time</label>
                        <input type="time" name="start_time" id="modalStartTime" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-700">End Time</label>
                        <input type="time" name="end_time" id="modalEndTime" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t">
                    <button type="button" onclick="document.getElementById('periodModal').classList.add('hidden')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg mr-2">Cancel</button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">Save Period</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPeriodModal() {
    document.getElementById('periodForm').reset();
    document.getElementById('modalTitle').innerText = 'Add New Period';
    document.getElementById('modalAction').value = 'add';
    document.getElementById('modalPeriodId').value = '';
    document.getElementById('periodModal').classList.remove('hidden');
}

function editPeriodModal(period) {
    document.getElementById('periodForm').reset();
    document.getElementById('modalTitle').innerText = 'Edit Period';
    document.getElementById('modalAction').value = 'update';
    document.getElementById('modalPeriodId').value = period.id;
    document.getElementById('modalPeriodName').value = period.period_name;
    document.getElementById('modalStartTime').value = period.start_time;
    document.getElementById('modalEndTime').value = period.end_time;
    document.getElementById('periodModal').classList.remove('hidden');
}
</script>

<?php require_once './admin_footer.php'; ?>