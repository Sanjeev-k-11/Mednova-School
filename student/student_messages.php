<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}
$student_id = $_SESSION["id"];

$student_class_id = null;
$sql_get_class = "SELECT class_id FROM students WHERE id = ? LIMIT 1";
if ($stmt_class = mysqli_prepare($link, $sql_get_class)) {
    mysqli_stmt_bind_param($stmt_class, "i", $student_id);
    mysqli_stmt_execute($stmt_class);
    mysqli_stmt_bind_result($stmt_class, $student_class_id);
    mysqli_stmt_fetch($stmt_class);
    mysqli_stmt_close($stmt_class);
}

// --- FETCH TEACHERS STUDENT CAN MESSAGE ---
$available_teachers = [];
$sql_teachers = "SELECT t.id AS teacher_id, t.full_name AS teacher_name, s.id AS subject_id, s.subject_name
                 FROM class_subject_teacher cst
                 JOIN teachers t ON cst.teacher_id = t.id
                 JOIN subjects s ON cst.subject_id = s.id
                 WHERE cst.class_id = ? ORDER BY t.full_name, s.subject_name";
if ($stmt_teachers = mysqli_prepare($link, $sql_teachers)) {
    mysqli_stmt_bind_param($stmt_teachers, "i", $student_class_id);
    mysqli_stmt_execute($stmt_teachers);
    $result_teachers = mysqli_stmt_get_result($stmt_teachers);
    $available_teachers = mysqli_fetch_all($result_teachers, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_teachers);
}

// --- HANDLE SENDING A MESSAGE (NOW STORES ENCRYPTED TEXT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $teacher_id = trim($_POST['teacher_id']);
    $subject_id = trim($_POST['subject_id']);
    $encrypted_message = trim($_POST['message_text']); // This is now encrypted text

    if (empty($encrypted_message)) {
        // Basic check, though JS should prevent this
    } else {
        $conversation_id = null;
        $sql_find_conv = "SELECT id FROM st_conversations WHERE student_id = ? AND teacher_id = ? AND subject_id = ?";
        $stmt_find = mysqli_prepare($link, $sql_find_conv);
        mysqli_stmt_bind_param($stmt_find, "iii", $student_id, $teacher_id, $subject_id);
        mysqli_stmt_execute($stmt_find);
        mysqli_stmt_bind_result($stmt_find, $conversation_id);
        mysqli_stmt_fetch($stmt_find);
        mysqli_stmt_close($stmt_find);

        if (!$conversation_id) {
            $sql_create_conv = "INSERT INTO st_conversations (student_id, teacher_id, subject_id) VALUES (?, ?, ?)";
            $stmt_create = mysqli_prepare($link, $sql_create_conv);
            mysqli_stmt_bind_param($stmt_create, "iii", $student_id, $teacher_id, $subject_id);
            mysqli_stmt_execute($stmt_create);
            $conversation_id = mysqli_insert_id($link);
            mysqli_stmt_close($stmt_create);
        }

        $sql_insert_msg = "INSERT INTO st_messages (conversation_id, sender_role, sender_id, message_text) VALUES (?, 'Student', ?, ?)";
        $stmt_insert = mysqli_prepare($link, $sql_insert_msg);
        mysqli_stmt_bind_param($stmt_insert, "iis", $conversation_id, $student_id, $encrypted_message);
        mysqli_stmt_execute($stmt_insert);

        $sql_update_conv = "UPDATE st_conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_conv);
        mysqli_stmt_bind_param($stmt_update, "i", $conversation_id);
        mysqli_stmt_execute($stmt_update);
    }
    header("location: student_messages.php?teacher_id=$teacher_id&subject_id=$subject_id");
    exit;
}

// --- FETCH DATA FOR DISPLAY ---
$current_teacher_id = $_GET['teacher_id'] ?? null;
$current_subject_id = $_GET['subject_id'] ?? null;
$current_conversation = null;
$messages = [];

if ($current_teacher_id && $current_subject_id) {
    foreach ($available_teachers as $teacher) {
        if ($teacher['teacher_id'] == $current_teacher_id && $teacher['subject_id'] == $current_subject_id) {
            $current_conversation = $teacher;
            break;
        }
    }

    if ($current_conversation) {
        $sql_get_conv_id = "SELECT id FROM st_conversations WHERE student_id = ? AND teacher_id = ? AND subject_id = ?";
        $stmt_conv_id = mysqli_prepare($link, $sql_get_conv_id);
        mysqli_stmt_bind_param($stmt_conv_id, "iii", $student_id, $current_teacher_id, $current_subject_id);
        mysqli_stmt_execute($stmt_conv_id);
        $result_conv_id = mysqli_stmt_get_result($stmt_conv_id);
        $conv_row = mysqli_fetch_assoc($result_conv_id);
        
        if($conv_row) {
            $conversation_id = $conv_row['id'];
            $sql_messages = "SELECT sender_role, message_text, created_at FROM st_messages WHERE conversation_id = ? ORDER BY created_at ASC";
            $stmt_messages = mysqli_prepare($link, $sql_messages);
            mysqli_stmt_bind_param($stmt_messages, "i", $conversation_id);
            mysqli_stmt_execute($stmt_messages);
            $result_messages = mysqli_stmt_get_result($stmt_messages);
            $messages = mysqli_fetch_all($result_messages, MYSQLI_ASSOC);
        }
    }
}

require_once './student_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encrypted Messages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
    <div class="h-screen flex flex-col pt-16">
        <div class="flex-grow flex flex-col sm:flex-row overflow-hidden">
            <!-- Left Panel: Teacher List -->
            <div class="w-full sm:w-1/3 lg:w-1/4 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col <?php echo ($current_conversation) ? 'hidden sm:flex' : 'flex'; ?>">
                <div class="p-4 border-b"><h2 class="text-xl font-bold text-gray-800">Direct Messages</h2></div>
                <div class="flex-grow overflow-y-auto">
                    <ul>
                        <?php foreach ($available_teachers as $teacher): ?>
                            <li class="border-b"><a href="?teacher_id=<?php echo $teacher['teacher_id']; ?>&subject_id=<?php echo $teacher['subject_id']; ?>" class="flex items-center gap-4 p-4 hover:bg-gray-50 transition <?php echo ($current_teacher_id == $teacher['teacher_id'] && $current_subject_id == $teacher['subject_id']) ? 'bg-indigo-50 border-r-4 border-indigo-600' : ''; ?>">
                                <div class="w-10 h-10 rounded-full bg-indigo-200 text-indigo-700 flex items-center justify-center font-bold text-lg"><?php echo strtoupper(substr($teacher['teacher_name'], 0, 1)); ?></div>
                                <div><h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($teacher['teacher_name']); ?></h3><p class="text-sm text-gray-600"><?php echo htmlspecialchars($teacher['subject_name']); ?></p></div>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <!-- Right Panel: Conversation View -->
            <div class="flex-grow flex flex-col bg-gray-50 <?php echo ($current_conversation) ? 'flex' : 'hidden sm:flex'; ?>">
                <?php if ($current_conversation): ?>
                    <div class="p-4 border-b bg-white flex justify-between items-center">
                        <div><h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($current_conversation['teacher_name']); ?></h2><p class="text-sm text-gray-500">Subject: <strong><?php echo htmlspecialchars($current_conversation['subject_name']); ?></strong></p></div>
                        <a href="student_messages.php" class="sm:hidden bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-lg hover:bg-gray-300"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                    <div id="messageContainer" class="flex-grow p-6 overflow-y-auto space-y-6">
                        <?php foreach ($messages as $message): ?>
                            <div class="flex <?php echo ($message['sender_role'] == 'Student') ? 'justify-end' : 'justify-start'; ?>">
                                <div class="max-w-lg p-3 rounded-lg shadow <?php echo ($message['sender_role'] == 'Student') ? 'bg-blue-600 text-white rounded-br-none' : 'bg-white text-gray-800 rounded-bl-none border'; ?>">
                                    <p class="encrypted-message" data-message="<?php echo htmlspecialchars($message['message_text']); ?>"><i class="fas fa-spinner fa-spin mr-2"></i>Decrypting...</p>
                                    <p class="text-xs mt-2 text-right <?php echo ($message['sender_role'] == 'Student') ? 'text-blue-200' : 'text-gray-400'; ?>"><?php echo date("h:i A", strtotime($message['created_at'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="p-4 bg-white border-t">
                        <form id="messageForm" action="student_messages.php" method="POST">
                            <input type="hidden" name="teacher_id" id="teacherIdInput" value="<?php echo $current_teacher_id; ?>">
                            <input type="hidden" name="subject_id" value="<?php echo $current_subject_id; ?>">
                            <input type="hidden" name="send_message" value="1">
                            <div class="flex items-center">
                                <textarea id="messageInput" rows="1" class="flex-grow p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Type an encrypted message..." required></textarea>
                                <button type="submit" class="ml-4 bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-700 transition"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex-grow flex items-center justify-center text-gray-500 text-center"><div_><i class="fas fa-comments text-5xl mb-4"></i><h3 class="text-2xl font-semibold">Select a conversation</h3><p>Choose a teacher to view your secure messages.</p></div></div>
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
            console.log("Generating new key pair...");
            keyPair = sodium.crypto_box_keypair('base64');
            localStorage.setItem('chatKeyPair', JSON.stringify(keyPair));
            
            await fetch('../database/store_public_key.php', {
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
                const recipientTeacherId = document.getElementById('teacherIdInput').value;
                
                if (!messageText.trim()) return;

                try {
                    const response = await fetch(`../database/get_public_key.php?role=Teacher&id=${recipientTeacherId}`);
                    const data = await response.json();
                    if (!data.publicKey) {
                        alert("Could not find teacher's public key. They may need to log in to the chat first to generate one.");
                        return;
                    }
                    const teacherPublicKey = data.publicKey;

                    const encryptedMessage = sodium.crypto_box_seal(
                        messageText,
                        sodium.from_base64(teacherPublicKey, sodium.base64_variants.ORIGINAL),
                        'base64'
                    );

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
                element.innerHTML = decryptedMessage.replace(/\n/g, '<br>'); // Keep line breaks
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