<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];

// --- HANDLE SENDING A MESSAGE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $student_id = trim($_POST['student_id']);
    $subject_id = trim($_POST['subject_id']);
    $encrypted_message = trim($_POST['message_text']); // This is encrypted text

    if (!empty($encrypted_message)) {
        // Find the conversation ID
        $conversation_id = null;
        $sql_find_conv = "SELECT id FROM st_conversations WHERE student_id = ? AND teacher_id = ? AND subject_id = ?";
        $stmt_find = mysqli_prepare($link, $sql_find_conv);
        mysqli_stmt_bind_param($stmt_find, "iii", $student_id, $teacher_id, $subject_id);
        mysqli_stmt_execute($stmt_find);
        mysqli_stmt_bind_result($stmt_find, $conversation_id);
        mysqli_stmt_fetch($stmt_find);
        mysqli_stmt_close($stmt_find);

        if ($conversation_id) { // Should always exist if student messaged first
            // Insert the message
            $sql_insert_msg = "INSERT INTO st_messages (conversation_id, sender_role, sender_id, message_text) VALUES (?, 'Teacher', ?, ?)";
            $stmt_insert = mysqli_prepare($link, $sql_insert_msg);
            mysqli_stmt_bind_param($stmt_insert, "iis", $conversation_id, $teacher_id, $encrypted_message);
            mysqli_stmt_execute($stmt_insert);

            // Update the conversation timestamp
            $sql_update_conv = "UPDATE st_conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $sql_update_conv);
            mysqli_stmt_bind_param($stmt_update, "i", $conversation_id);
            mysqli_stmt_execute($stmt_update);
        }
    }
    header("location: teacher_messages.php?student_id=$student_id&subject_id=$subject_id");
    exit;
}

// --- FETCH CONVERSATIONS WITH UNREAD COUNT ---
$conversations = [];
// CRITICAL FIX: Changed 'c.id' to 'cls.id' in the JOIN condition
$sql_conv_list = "SELECT
                      stc.id as conversation_id, stc.student_id, stc.subject_id,
                      s.first_name, s.last_name,
                      sub.subject_name,
                      cls.class_name, cls.section_name,
                      (SELECT COUNT(m.id) FROM st_messages m 
                       LEFT JOIN st_message_read_status mrs ON m.id = mrs.message_id AND mrs.reader_id = ?
                       WHERE m.conversation_id = stc.id AND m.sender_role = 'Student' AND mrs.id IS NULL) AS unread_count
                  FROM st_conversations stc
                  JOIN students s ON stc.student_id = s.id
                  JOIN subjects sub ON stc.subject_id = sub.id
                  JOIN classes cls ON s.class_id = cls.id
                  WHERE stc.teacher_id = ?
                  ORDER BY stc.last_message_at DESC";
if ($stmt_list = mysqli_prepare($link, $sql_conv_list)) {
    mysqli_stmt_bind_param($stmt_list, "ii", $teacher_id, $teacher_id);
    mysqli_stmt_execute($stmt_list);
    $result_list = mysqli_stmt_get_result($stmt_list);
    $conversations = mysqli_fetch_all($result_list, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_list);
}

// --- FETCH DATA FOR ACTIVE CHAT DISPLAY ---
$current_student_id = $_GET['student_id'] ?? null;
$current_subject_id = $_GET['subject_id'] ?? null;
$current_conversation_details = null;
$messages = [];

if ($current_student_id && $current_subject_id) {
    foreach ($conversations as $conv) {
        if ($conv['student_id'] == $current_student_id && $conv['subject_id'] == $current_subject_id) {
            $current_conversation_details = $conv;
            break;
        }
    }

    if ($current_conversation_details) {
        $conversation_id = $current_conversation_details['conversation_id'];
        
        $sql_messages = "SELECT id, sender_role, message_text, created_at FROM st_messages WHERE conversation_id = ? ORDER BY created_at ASC";
        $stmt_messages = mysqli_prepare($link, $sql_messages);
        mysqli_stmt_bind_param($stmt_messages, "i", $conversation_id);
        mysqli_stmt_execute($stmt_messages);
        $result_messages = mysqli_stmt_get_result($stmt_messages);
        $messages = mysqli_fetch_all($result_messages, MYSQLI_ASSOC);
        
        $unread_message_ids = [];
        foreach ($messages as $message) {
            if ($message['sender_role'] === 'Student') $unread_message_ids[] = $message['id'];
        }
        
        if (!empty($unread_message_ids)) {
            $sql_mark_read = "INSERT IGNORE INTO st_message_read_status (message_id, conversation_id, reader_id) VALUES ";
            $values_to_insert = [];
            foreach ($unread_message_ids as $msg_id) $values_to_insert[] = "($msg_id, $conversation_id, $teacher_id)";
            $sql_mark_read .= implode(', ', $values_to_insert);
            mysqli_query($link, $sql_mark_read);
        }
    }
}

require_once './teacher_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encrypted Messages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 font-sans">
    <div class="h-screen flex flex-col pt-16">
        <div class="flex-grow flex flex-col sm:flex-row overflow-hidden" x-data="{ search: '' }">
            <!-- Left Panel: Student Conversation List -->
            <div class="w-full sm:w-1/3 lg:w-1/4 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col <?php echo ($current_conversation_details) ? 'hidden sm:flex' : 'flex'; ?>">
                <div class="p-4 border-b space-y-2"><h2 class="text-xl font-bold text-gray-800">Student Messages</h2><div class="relative"><i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i><input x-model.debounce.300ms="search" type="text" placeholder="Search students..." class="w-full pl-10 pr-4 py-2 border rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500"></div></div>
                <div class="flex-grow overflow-y-auto">
                    <?php if (empty($conversations)): ?><div class="p-8 text-center text-gray-500"><i class="fas fa-comments text-4xl mb-3"></i><p>No student conversations yet.</p></div>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($conversations as $conv): ?>
                                <li class="border-b" x-show="search === '' || '<?php echo strtolower(htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name'])); ?>'.includes(search.toLowerCase())">
                                    <a href="?student_id=<?php echo $conv['student_id']; ?>&subject_id=<?php echo $conv['subject_id']; ?>" class="flex items-center gap-4 p-4 hover:bg-gray-50 transition <?php echo ($current_student_id == $conv['student_id'] && $current_subject_id == $conv['subject_id']) ? 'bg-indigo-50 border-r-4 border-indigo-600' : ''; ?>">
                                        <div class="w-10 h-10 rounded-full bg-pink-200 text-pink-700 flex items-center justify-center font-bold text-lg"><?php echo strtoupper(substr($conv['first_name'], 0, 1)); ?></div>
                                        <div class="flex-grow"><div class="flex justify-between items-center"><h3 class="font-bold text-gray-800 truncate"><?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?></h3><?php if ($conv['unread_count'] > 0): ?><span class="bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $conv['unread_count']; ?></span><?php endif; ?></div><p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($conv['class_name'] . ' - ' . $conv['section_name']); ?> (<?php echo htmlspecialchars($conv['subject_name']); ?>)</p></div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Right Panel: Conversation View -->
            <div class="flex-grow flex flex-col bg-gray-50 <?php echo ($current_conversation_details) ? 'flex' : 'hidden sm:flex'; ?>">
                <?php if ($current_conversation_details): ?>
                    <div class="p-4 border-b bg-white flex justify-between items-center"><div><h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($current_conversation_details['first_name'] . ' ' . $current_conversation_details['last_name']); ?></h2><p class="text-sm text-gray-500">Subject: <strong><?php echo htmlspecialchars($current_conversation_details['subject_name']); ?></strong></p></div><a href="teacher_messages.php" class="sm:hidden bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-lg hover:bg-gray-300"><i class="fas fa-arrow-left"></i> Back</a></div>
                    <div id="messageContainer" class="flex-grow p-6 overflow-y-auto space-y-6">
                        <?php foreach ($messages as $message): ?>
                            <div class="flex <?php echo ($message['sender_role'] == 'Teacher') ? 'justify-end' : 'justify-start'; ?>"><div class="max-w-lg p-3 rounded-lg shadow <?php echo ($message['sender_role'] == 'Teacher') ? 'bg-blue-600 text-white rounded-br-none' : 'bg-white text-gray-800 rounded-bl-none border'; ?>"><p class="encrypted-message" data-message="<?php echo htmlspecialchars($message['message_text']); ?>"><i class="fas fa-spinner fa-spin mr-2"></i>Decrypting...</p><p class="text-xs mt-2 text-right <?php echo ($message['sender_role'] == 'Teacher') ? 'text-blue-200' : 'text-gray-400'; ?>"><?php echo date("h:i A", strtotime($message['created_at'])); ?></p></div></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="p-4 bg-white border-t">
                        <form id="messageForm" action="teacher_messages.php" method="POST">
                            <input type="hidden" name="student_id" id="studentIdInput" value="<?php echo $current_student_id; ?>"><input type="hidden" name="subject_id" value="<?php echo $current_subject_id; ?>"><input type="hidden" name="send_message" value="1">
                            <div class="flex items-center"><textarea id="messageInput" rows="1" class="flex-grow p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Type an encrypted message..." required></textarea><button type="submit" class="ml-4 bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-700 transition"><i class="fas fa-paper-plane"></i></button></div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex-grow flex items-center justify-center text-gray-500 text-center p-8"><div><i class="fas fa-comments text-6xl text-gray-300 mb-4"></i><h3 class="text-2xl font-semibold text-gray-700">Select a Conversation</h3><p class="text-gray-500 mt-1">Choose a student from the list on the left to view your secure messages.</p></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/libsodium-wrappers/dist/browsers/sodium.min.js"></script>
    <script>
    (async () => {
        await sodium.ready;

        let keyPair = JSON.parse(localStorage.getItem('chatKeyPair'));

        if (!keyPair) {
            console.log("Generating new key pair for Teacher...");
            keyPair = sodium.crypto_box_keypair('base64');
            localStorage.setItem('chatKeyPair', JSON.stringify(keyPair));
            
            await fetch('api/store_public_key.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ publicKey: keyPair.publicKey })
            });
        }

        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                const messageInput = document.getElementById('messageInput');
                const messageText = messageInput.value;
                const recipientStudentId = document.getElementById('studentIdInput').value;
                if (!messageText.trim()) return;

                try {
                    const response = await fetch(`api/get_public_key.php?role=Student&id=${recipientStudentId}`);
                    const data = await response.json();
                    if (!data.publicKey) {
                        alert("Could not find student's public key. They may need to log in to the chat first to generate one.");
                        return;
                    }
                    const studentPublicKey = data.publicKey;
                    const encryptedMessage = sodium.crypto_box_seal(messageText, sodium.from_base64(studentPublicKey, sodium.base64_variants.ORIGINAL), 'base64');
                    const hiddenEncryptedInput = document.createElement('input');
                    hiddenEncryptedInput.type = 'hidden';
                    hiddenEncryptedInput.name = 'message_text';
                    hiddenEncryptedInput.value = encryptedMessage;
                    messageForm.appendChild(hiddenEncryptedInput);
                    messageInput.value = '';
                    messageForm.submit();
                } catch (error) {
                    console.error('Encryption failed:', error);
                    alert('An error occurred while sending the message.');
                }
            });
        }

        const messageElements = document.querySelectorAll('.encrypted-message');
        for (const element of messageElements) {
            const encryptedText = element.dataset.message;
            try {
                const decryptedMessage = sodium.crypto_box_seal_open(
                    sodium.from_base64(encryptedText, sodium.base64_variants.ORIGINAL),
                    sodium.from_base64(keyPair.publicKey, sodium.base64_variants.ORIGINAL),
                    sodium.from_base64(keyPair.privateKey, sodium.base64_variants.ORIGINAL),
                    'text'
                );
                element.innerHTML = decryptedMessage.replace(/\n/g, '<br>');
                element.classList.remove('italic');
            } catch (error) {
                element.textContent = '[Cannot decrypt this message]';
                element.classList.add('text-red-400', 'italic');
            }
        }

        const messageContainer = document.getElementById('messageContainer');
        if (messageContainer) {
            messageContainer.scrollTop = messageContainer.scrollHeight;
        }
    })();
    </script>
</body>
</html>