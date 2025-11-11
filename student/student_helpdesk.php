<?php
session_start();
require_once "../database/config.php"; // Adjust path as necessary
require_once "./student_header.php";   // Includes student-specific authentication and sidebar

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php"); 
    exit;
}

// CORRECTED: Use the standard session key 'user_id' for clarity across roles
// Ensure your student login script sets $_SESSION['user_id']
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

// --- AJAX REQUEST HANDLING (for sending messages) ---
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'new_message' => ''];
    $ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $message_text = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS));
    
    if ($ticket_id && !empty($message_text)) {
        // Verify student owns this ticket before adding a message
        $sql_verify_owner = "SELECT id FROM support_tickets WHERE id = ? AND student_id = ?";
        $stmt_verify = mysqli_prepare($link, $sql_verify_owner);
        mysqli_stmt_bind_param($stmt_verify, "ii", $ticket_id, $student_id);
        mysqli_stmt_execute($stmt_verify);
        mysqli_stmt_store_result($stmt_verify);
        
        if (mysqli_stmt_num_rows($stmt_verify) > 0) {
            $sql_insert_message = "INSERT INTO support_ticket_messages (ticket_id, user_id, user_role, message) VALUES (?, ?, 'Student', ?)";
            if ($stmt = mysqli_prepare($link, $sql_insert_message)) {
                mysqli_stmt_bind_param($stmt, "iis", $ticket_id, $student_id, $message_text);
                if (mysqli_stmt_execute($stmt)) {
                    // Also update the parent ticket's `updated_at` timestamp
                    mysqli_query($link, "UPDATE support_tickets SET updated_at = NOW() WHERE id = $ticket_id");
                    $response['success'] = true;
                    $response['message'] = 'Message sent successfully.';
                    // Build HTML for the new message to append with JS
                    $response['new_message'] = '<div class="flex justify-end mb-4"><div class="bg-indigo-600 text-white rounded-lg py-2 px-4 max-w-xs md:max-w-md"><p>' . htmlspecialchars($message_text) . '</p><div class="text-right text-xs text-indigo-200 mt-1">Just now</div></div></div>';
                } else {
                    $response['message'] = "Database error: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $response['message'] = 'Unauthorized to post to this ticket.';
        }
        mysqli_stmt_close($stmt_verify);
    } else {
        $response['message'] = 'Invalid ticket ID or empty message.';
    }
    echo json_encode($response);
    mysqli_close($link);
    exit();
}

$flash_message = '';
$flash_message_type = '';
$student_info = null;
$student_class_id = null;
$student_subjects = [];

// --- DATA FETCHING for main page ---
$sql_student_info = "SELECT class_id FROM students WHERE id = ?";
if ($stmt = mysqli_prepare($link, $sql_student_info)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $student_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($student_info) {
        $student_class_id = $student_info['class_id'];
    }
}

// Fetch subjects and their assigned teachers for the student's class
if ($student_class_id) {
    $sql_subjects = "
        SELECT 
            s.id AS subject_id, 
            s.subject_name,
            t.id AS teacher_id,
            t.full_name AS teacher_name
        FROM class_subject_teacher cst
        JOIN subjects s ON cst.subject_id = s.id
        JOIN teachers t ON cst.teacher_id = t.id
        WHERE cst.class_id = ?
        ORDER BY s.subject_name ASC";
    if ($stmt = mysqli_prepare($link, $sql_subjects)) {
        mysqli_stmt_bind_param($stmt, "i", $student_class_id);
        mysqli_stmt_execute($stmt);
        $student_subjects = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    } else {
        error_log("DB Prepare Subjects Error: " . mysqli_error($link));
        $flash_message = "Error fetching subjects for your class.";
        $flash_message_type = 'error';
    }
} else {
    $flash_message = "Could not determine your class. Please contact administration.";
    $flash_message_type = 'error';
}

// --- HANDLE NEW TICKET SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_ticket_action'])) {
    $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS));
    $initial_message = trim(filter_input(INPUT_POST, 'initial_message', FILTER_SANITIZE_SPECIAL_CHARS));
    $teacher_id_for_subject = null;

    // Find the teacher ID for the selected subject
    foreach ($student_subjects as $subject) {
        if ($subject['subject_id'] == $subject_id) {
            $teacher_id_for_subject = $subject['teacher_id'];
            break;
        }
    }

    if (!$subject_id || !$teacher_id_for_subject || empty($title) || empty($initial_message)) {
        $flash_message = "Please select a subject and fill in all fields.";
        $flash_message_type = 'error';
    } else {
        // Use a transaction to ensure both ticket and message are created
        mysqli_begin_transaction($link);
        try {
            $sql_insert_ticket = "
                INSERT INTO support_tickets (student_id, class_id, subject_id, teacher_id, title)
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmt1 = mysqli_prepare($link, $sql_insert_ticket);
            mysqli_stmt_bind_param($stmt1, "iiiis", $student_id, $student_class_id, $subject_id, $teacher_id_for_subject, $title);
            mysqli_stmt_execute($stmt1);
            $new_ticket_id = mysqli_insert_id($link);

            $sql_insert_message = "
                INSERT INTO support_ticket_messages (ticket_id, user_id, user_role, message)
                VALUES (?, ?, 'Student', ?)
            ";
            $stmt2 = mysqli_prepare($link, $sql_insert_message);
            mysqli_stmt_bind_param($stmt2, "iis", $new_ticket_id, $student_id, $initial_message);
            mysqli_stmt_execute($stmt2);

            mysqli_commit($link);
            $flash_message = "Support ticket created successfully!";
            $flash_message_type = 'success';
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($link);
            $flash_message = "Failed to create support ticket. Please try again.";
            $flash_message_type = 'error';
            error_log("Support Ticket Creation Error: " . $exception->getMessage());
        } finally {
            if (isset($stmt1)) mysqli_stmt_close($stmt1);
            if (isset($stmt2)) mysqli_stmt_close($stmt2);
        }
    }
}

// --- DATA FETCHING for display after POST processing ---
$tickets = [];
$sql_tickets = "
    SELECT 
        st.id,
        st.title,
        st.status,
        st.updated_at,
        s.subject_name,
        t.full_name AS teacher_name
    FROM support_tickets st
    JOIN subjects s ON st.subject_id = s.id
    JOIN teachers t ON st.teacher_id = t.id
    WHERE st.student_id = ?
    ORDER BY st.status ASC, st.updated_at DESC
";
if ($stmt = mysqli_prepare($link, $sql_tickets)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $tickets = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// If a specific ticket is selected, fetch its details and messages
$selected_ticket_id = $_GET['ticket_id'] ?? null;
$selected_ticket = null;
$ticket_messages = [];
if ($selected_ticket_id && is_numeric($selected_ticket_id)) {
    $sql_selected_ticket = "
        SELECT 
            st.id, st.title, st.status, st.created_at, st.updated_at,
            s.subject_name,
            t.full_name AS teacher_name, t.image_url AS teacher_image
        FROM support_tickets st
        JOIN subjects s ON st.subject_id = s.id
        JOIN teachers t ON st.teacher_id = t.id
        WHERE st.id = ? AND st.student_id = ?
    ";
    if ($stmt = mysqli_prepare($link, $sql_selected_ticket)) {
        mysqli_stmt_bind_param($stmt, "ii", $selected_ticket_id, $student_id);
        mysqli_stmt_execute($stmt);
        $selected_ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
    
    if ($selected_ticket) {
        $sql_messages = "
            SELECT 
                stm.id, stm.user_id, stm.user_role, stm.message, stm.created_at
            FROM support_ticket_messages stm
            WHERE stm.ticket_id = ?
            ORDER BY stm.created_at ASC
        ";
        if ($stmt = mysqli_prepare($link, $sql_messages)) {
            mysqli_stmt_bind_param($stmt, "i", $selected_ticket_id);
            mysqli_stmt_execute($stmt);
            $ticket_messages = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);
        }
    }
}


mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Desk - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .dashboard-container { min-height: calc(100vh - 80px); }
        .toast-notification { position: fixed; top: 20px; right: 20px; z-index: 1000; opacity: 0; transform: translateY(-20px); transition: opacity 0.3s ease-out, transform 0.3s ease-out; }
        .toast-notification.show { opacity: 1; transform: translateY(0); }
        .status-badge.Open { background-color: #9ae6b4; color: #276749; } /* Green */
        .status-badge.Closed { background-color: #e2e8f0; color: #4a5568; } /* Gray */
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
<!-- student_header.php content usually goes here -->

<div class="dashboard-container p-4 sm:p-6">
    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-notification"></div>

    <!-- Main Header Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Help Desk & Support</h1>
        <p class="text-gray-600">Get help from your teachers by creating a support ticket.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Ticket List & New Ticket Form Section -->
        <div class="lg:col-span-1 bg-white rounded-xl shadow-lg h-full flex flex-col">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-ticket-alt mr-2 text-indigo-500"></i> My Tickets
                </h2>
            </div>
            <div class="flex-grow overflow-y-auto p-4">
                <!-- New Ticket Button -->
                <button onclick="document.getElementById('newTicketModal').classList.remove('hidden')" class="w-full mb-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md inline-flex items-center justify-center text-sm">
                    <i class="fas fa-plus mr-2"></i> Create New Ticket
                </button>

                <?php if (empty($tickets)): ?>
                    <div class="text-center p-8 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
                        <i class="fas fa-check-circle fa-4x mb-4 text-gray-400"></i>
                        <p class="text-xl font-semibold mb-2">No tickets yet!</p>
                        <p class="text-lg">Click the button above to create your first ticket.</p>
                    </div>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($tickets as $ticket): ?>
                            <li>
                                <a href="student_helpdesk.php?ticket_id=<?php echo $ticket['id']; ?>" 
                                   class="block p-4 rounded-lg transition-colors duration-200 <?php echo ($selected_ticket_id == $ticket['id']) ? 'bg-indigo-100 border border-indigo-300' : 'bg-gray-50 hover:bg-gray-100 border border-transparent'; ?>">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($ticket['title']); ?></p>
                                            <p class="text-xs text-gray-600">To: <?php echo htmlspecialchars($ticket['teacher_name']); ?> (<?php echo htmlspecialchars($ticket['subject_name']); ?>)</p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold status-badge <?php echo htmlspecialchars($ticket['status']); ?>">
                                            <?php echo htmlspecialchars($ticket['status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-right text-xs text-gray-400 mt-2">Last updated: <?php echo date('M d, h:i A', strtotime($ticket['updated_at'])); ?></p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ticket Conversation View -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg flex flex-col h-full">
            <?php if (!$selected_ticket): ?>
                <div class="flex-grow flex flex-col items-center justify-center text-center p-8">
                    <i class="fas fa-comments fa-5x text-gray-300 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-700">Select a Ticket</h2>
                    <p class="text-gray-500">Choose a ticket from the left to view the conversation, or create a new one.</p>
                </div>
            <?php else: ?>
                <!-- Ticket Header -->
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($selected_ticket['title']); ?></h2>
                        <p class="text-sm text-gray-500">With: <?php echo htmlspecialchars($selected_ticket['teacher_name']); ?> | Subject: <?php echo htmlspecialchars($selected_ticket['subject_name']); ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold status-badge <?php echo htmlspecialchars($selected_ticket['status']); ?>">
                        <?php echo htmlspecialchars($selected_ticket['status']); ?>
                    </span>
                </div>

                <!-- Messages Area -->
                <div id="messageArea" class="flex-grow p-6 overflow-y-auto" style="max-height: 60vh;">
                    <?php foreach ($ticket_messages as $message): ?>
                        <?php if ($message['user_role'] === 'Student'): ?>
                            <!-- Student Message (Right Aligned) -->
                            <div class="flex justify-end mb-4">
                                <div class="bg-indigo-600 text-white rounded-lg py-2 px-4 max-w-xs md:max-w-md">
                                    <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                    <div class="text-right text-xs text-indigo-200 mt-1"><?php echo date('M d, h:i A', strtotime($message['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Teacher Message (Left Aligned) -->
                            <div class="flex justify-start mb-4">
                                <div class="bg-gray-200 text-gray-800 rounded-lg py-2 px-4 max-w-xs md:max-w-md">
                                    <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                    <div class="text-right text-xs text-gray-500 mt-1"><?php echo date('M d, h:i A', strtotime($message['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Message Input Form -->
                <?php if ($selected_ticket['status'] === 'Open'): ?>
                <div class="p-4 bg-gray-50 border-t border-gray-200">
                    <form id="sendMessageForm">
                        <input type="hidden" name="ticket_id" value="<?php echo $selected_ticket_id; ?>">
                         
                    </form>
                </div>
                <?php else: ?>
                    <div class="p-4 bg-gray-100 border-t border-gray-200 text-center text-gray-600 font-semibold">
                        This ticket is closed. You can no longer send messages.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Ticket Modal -->
<div id="newTicketModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-xl font-bold text-gray-800">Create a New Support Ticket</h3>
            <button onclick="document.getElementById('newTicketModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form action="student_helpdesk.php" method="POST">
            <input type="hidden" name="new_ticket_action" value="create">
            <div class="p-6">
                <div class="mb-4">
                    <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Select Subject/Teacher <span class="text-red-500">*</span></label>
                    <select id="subject_id" name="subject_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- Choose a Subject --</option>
                        <?php foreach ($student_subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>">
                                <?php echo htmlspecialchars($subject['subject_name']); ?> (Teacher: <?php echo htmlspecialchars($subject['teacher_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Ticket Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="e.g., Issue with recent assignment">
                </div>
                <div class="mb-4">
                    <label for="initial_message" class="block text-sm font-medium text-gray-700 mb-1">Your Message <span class="text-red-500">*</span></label>
                    <textarea name="initial_message" id="initial_message" rows="5" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Please describe your issue in detail..."></textarea>
                </div>
            </div>
            <div class="p-4 bg-gray-50 flex justify-end gap-3 rounded-b-xl">
                <button type="button" onclick="document.getElementById('newTicketModal').classList.add('hidden')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md">Cancel</button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md">Create Ticket</button>
            </div>
        </form>
    </div>
</div>


<?php require_once "./student_footer.php"; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toastContainer = document.getElementById('toast-container');
        const messageArea = document.getElementById('messageArea');
        const sendMessageForm = document.getElementById('sendMessageForm');
        const messageInput = document.getElementById('messageInput');

        // --- Function to show Toast Notifications ---
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `p-4 rounded-lg shadow-lg text-white text-sm font-semibold flex items-center toast-notification`;
            let bgColor = ''; let iconClass = '';
            if (type === 'success') { bgColor = 'bg-green-500'; iconClass = 'fas fa-check-circle'; }
            else if (type === 'error') { bgColor = 'bg-red-500'; iconClass = 'fas fa-times-circle'; }
            else { bgColor = 'bg-blue-500'; iconClass = 'fas fa-info-circle'; }
            toast.classList.add(bgColor);
            toast.innerHTML = `<i class="${iconClass} mr-2"></i> ${message}`;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => { toast.classList.remove('show'); toast.addEventListener('transitionend', () => toast.remove()); }, 5000);
        }

        // --- Display initial flash message from PHP (if any) ---
        <?php if ($flash_message): ?>
            showToast("<?php echo htmlspecialchars($flash_message); ?>", "<?php echo htmlspecialchars($flash_message_type); ?>");
        <?php endif; ?>

        // --- Auto-scroll message area to the bottom ---
        if (messageArea) {
            messageArea.scrollTop = messageArea.scrollHeight;
        }

        // --- Handle AJAX for sending a new message ---
        if (sendMessageForm) {
            sendMessageForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const messageText = messageInput.value.trim();
                
                if (!messageText) return;

                try {
                    const response = await fetch('student_helpdesk.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        messageArea.insertAdjacentHTML('beforeend', data.new_message);
                        messageInput.value = ''; // Clear input
                        messageArea.scrollTop = messageArea.scrollHeight; // Scroll to new message
                    } else {
                        showToast(data.message || 'Failed to send message.', 'error');
                    }
                } catch (error) {
                    console.error('Network error sending message:', error);
                    showToast('A network error occurred. Please try again.', 'error');
                }
            });
        }
    });
</script>
</body>
</html>