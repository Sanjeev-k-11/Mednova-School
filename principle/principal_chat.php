<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Principle') {
    header("location: ../login.php");
    exit;
}

$principal_id = $_SESSION["id"];
$principal_name = $_SESSION["full_name"];
$principal_role = $_SESSION["role"]; // 'Principle'

$message = '';
$message_type = '';

// --- Fetch all staff (teachers and other admins) for starting new chats ---
$all_staff_for_new_chat = [];
$sql_teachers = "SELECT id, full_name, 'Teacher' as role FROM teachers WHERE is_blocked = 0 AND id != ?";
$sql_admins = "SELECT id, full_name, 'Admin' as role FROM admins WHERE id != ?"; // Assuming admin cannot chat with themselves in this list
$sql_principals = "SELECT id, full_name, 'Principle' as role FROM principles WHERE id != ?";

$stmt_teachers = mysqli_prepare($link, $sql_teachers);
mysqli_stmt_bind_param($stmt_teachers, "i", $principal_id); // Exclude self if principal is listed in teachers
mysqli_stmt_execute($stmt_teachers);
$result_teachers = mysqli_stmt_get_result($stmt_teachers);
while ($row = mysqli_fetch_assoc($result_teachers)) {
    $all_staff_for_new_chat[] = $row;
}
mysqli_stmt_close($stmt_teachers);

// If there's a separate 'admins' table and principals are separate from admins
// You might need to adjust this to avoid listing yourself twice if you're in 'admins' too
// Assuming here principal is NOT in 'admins' table
// $stmt_admins = mysqli_prepare($link, $sql_admins);
// mysqli_stmt_bind_param($stmt_admins, "i", $principal_id);
// mysqli_stmt_execute($stmt_admins);
// $result_admins = mysqli_stmt_get_result($stmt_admins);
// while ($row = mysqli_fetch_assoc($result_admins)) {
//     $all_staff_for_new_chat[] = $row;
// }
// mysqli_stmt_close($stmt_admins);

// Also fetch other principals if multiple principals can chat
// $stmt_principals = mysqli_prepare($link, $sql_principals);
// mysqli_stmt_bind_param($stmt_principals, "i", $principal_id);
// mysqli_stmt_execute($stmt_principals);
// $result_principals = mysqli_stmt_get_result($stmt_principals);
// while ($row = mysqli_fetch_assoc($result_principals)) {
//     $all_staff_for_new_chat[] = $row;
// }
// mysqli_stmt_close($stmt_principals);

// Sort staff alphabetically
usort($all_staff_for_new_chat, function($a, $b) {
    return strcmp($a['full_name'], $b['full_name']);
});


// --- Chat Actions (Create new conversation) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['chat_action'])) {
    $chat_action = $_POST['chat_action'];

    if ($chat_action === 'create_one_on_one') {
        $target_teacher_id = (int)$_POST['target_teacher_id'];
        if (empty($target_teacher_id)) {
            set_session_message("Please select a staff member to chat with.", "danger");
            header("location: principal_chat.php"); exit;
        }

        // Check if conversation already exists (between two users)
        $sql_check_conv = "SELECT c.id FROM conversations c
                           JOIN conversation_members cm1 ON c.id = cm1.conversation_id
                           JOIN conversation_members cm2 ON c.id = cm2.conversation_id
                           WHERE c.type = 'one-on-one'
                           AND cm1.teacher_id = ? AND cm2.teacher_id = ?
                           LIMIT 1";
        if ($stmt = mysqli_prepare($link, $sql_check_conv)) {
            mysqli_stmt_bind_param($stmt, "ii", $principal_id, $target_teacher_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                // Conversation already exists, redirect to it
                mysqli_stmt_bind_result($stmt, $existing_conv_id);
                mysqli_stmt_fetch($stmt);
                set_session_message("Conversation already exists.", "info");
                header("location: principal_chat.php?conversation_id=" . $existing_conv_id); exit;
            }
            mysqli_stmt_close($stmt);
        }

        // Create new conversation
        $sql_new_conv = "INSERT INTO conversations (type, created_by) VALUES ('one-on-one', ?)";
        if ($stmt = mysqli_prepare($link, $sql_new_conv)) {
            mysqli_stmt_bind_param($stmt, "i", $principal_id);
            if (mysqli_stmt_execute($stmt)) {
                $new_conversation_id = mysqli_insert_id($link);
                // Add members
                $sql_add_members = "INSERT INTO conversation_members (conversation_id, teacher_id) VALUES (?, ?), (?, ?)";
                $stmt_members = mysqli_prepare($link, $sql_add_members);
                mysqli_stmt_bind_param($stmt_members, "iiii", $new_conversation_id, $principal_id, $new_conversation_id, $target_teacher_id);
                mysqli_stmt_execute($stmt_members);
                mysqli_stmt_close($stmt_members);

                set_session_message("New chat started successfully.", "success");
                header("location: principal_chat.php?conversation_id=" . $new_conversation_id); exit;
            } else {
                set_session_message("Error starting new chat: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }

    } elseif ($chat_action === 'create_group') {
        $group_name = trim($_POST['group_name']);
        $selected_members = isset($_POST['group_members']) ? (array)$_POST['group_members'] : [];

        if (empty($group_name) || count($selected_members) < 1) { // Need at least one other member besides self
            set_session_message("Group name and at least one member are required.", "danger");
            header("location: principal_chat.php"); exit;
        }

        // Create new group conversation
        $sql_new_group_conv = "INSERT INTO conversations (type, group_name, created_by) VALUES ('group', ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql_new_group_conv)) {
            mysqli_stmt_bind_param($stmt, "si", $group_name, $principal_id);
            if (mysqli_stmt_execute($stmt)) {
                $new_group_id = mysqli_insert_id($link);
                // Add creator to group
                $sql_add_creator = "INSERT INTO conversation_members (conversation_id, teacher_id) VALUES (?, ?)";
                $stmt_creator = mysqli_prepare($link, $sql_add_creator);
                mysqli_stmt_bind_param($stmt_creator, "ii", $new_group_id, $principal_id);
                mysqli_stmt_execute($stmt_creator);
                mysqli_stmt_close($stmt_creator);

                // Add other selected members
                foreach ($selected_members as $member_id) {
                    $sql_add_member = "INSERT INTO conversation_members (conversation_id, teacher_id) VALUES (?, ?)";
                    $stmt_member = mysqli_prepare($link, $sql_add_member);
                    mysqli_stmt_bind_param($stmt_member, "ii", $new_group_id, (int)$member_id);
                    mysqli_stmt_execute($stmt_member);
                    mysqli_stmt_close($stmt_member);
                }

                set_session_message("New group chat '" . htmlspecialchars($group_name) . "' created successfully.", "success");
                header("location: principal_chat.php?conversation_id=" . $new_group_id); exit;
            } else {
                set_session_message("Error creating group chat: " . mysqli_error($link), "danger");
            }
            mysqli_stmt_close($stmt);
        }
    }
    header("location: principal_chat.php"); // Fallback redirect
    exit;
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
    <title>Staff Chat - Principal Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Body and Container Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #E0F7FA, #B2EBF2, #80DEEA, #4DD0E1); /* Light blues */
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            color: #333;
        }
        @keyframes gradientAnimation {
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }
        .chat-container {
            max-width: 1500px;
            margin: 20px auto;
            padding: 0;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            height: calc(100vh - 100px); /* Adjust height for main content margin & footer */
            overflow: hidden;
        }
        h2 {
            color: #00838F; /* Dark Cyan */
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #B2EBF2;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }


        /* Left Panel - Conversations List */
        .chat-sidebar {
            width: 350px;
            background-color: #f5fafd; /* Light blue-grey */
            border-right: 1px solid #e0e6e9;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            position: relative;
        }
        .chat-sidebar-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e6e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #e0f2f7;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .chat-sidebar-header h3 {
            margin: 0;
            color: #00838F;
            font-size: 1.2em;
        }
        .new-chat-btn {
            background-color: #00BCD4; /* Cyan */
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
            transition: background-color 0.3s;
        }
        .new-chat-btn:hover { background-color: #00ACC1; }

        .conversation-list {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }
        .conversation-item {
            padding: 12px 20px;
            border-bottom: 1px solid #edf2f5;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.2s;
        }
        .conversation-item:hover { background-color: #e8f5fd; }
        .conversation-item.active-chat {
            background-color: #d1ecf1;
            border-left: 4px solid #00BCD4;
        }
        .conversation-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            background-color: #B2EBF2;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #006064;
            font-weight: bold;
            font-size: 1.1em;
        }
        .conversation-info {
            flex-grow: 1;
        }
        .conversation-name {
            font-weight: 600;
            color: #333;
            font-size: 1em;
        }
        .conversation-last-message {
            font-size: 0.85em;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .unread-count {
            background-color: #dc3545;
            color: #fff;
            border-radius: 12px;
            padding: 3px 8px;
            font-size: 0.7em;
            font-weight: bold;
            flex-shrink: 0;
        }
        .online-status-indicator {
            width: 10px;
            height: 10px;
            background-color: #ccc; /* Grey for offline/unknown */
            border-radius: 50%;
            position: absolute;
            bottom: 0;
            right: 0;
            border: 2px solid #f5fafd; /* Match sidebar background */
        }
        .online-status-indicator.online { background-color: #28a745; } /* Green for online */


        /* Right Panel - Chat Area */
        .chat-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .chat-main-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e6e9;
            display: flex;
            align-items: center;
            gap: 15px;
            background-color: #e0f2f7;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .chat-main-header h3 {
            margin: 0;
            color: #00838F;
            font-size: 1.2em;
        }
        .chat-messages {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #eef7fa; /* Very light blue */
        }
        .message-bubble {
            display: flex;
            margin-bottom: 15px;
            max-width: 70%;
            align-items: flex-end;
        }
        .message-bubble.my-message {
            margin-left: auto;
            flex-direction: row-reverse;
        }
        .message-content-wrapper {
            background-color: #fff;
            padding: 10px 15px;
            border-radius: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
        }
        .message-bubble.my-message .message-content-wrapper {
            background-color: #00BCD4;
            color: #fff;
        }
        .message-sender-name {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .message-bubble.my-message .message-sender-name {
            color: #fff; /* Match bubble color */
            text-align: right;
        }
        .message-text {
            font-size: 0.95em;
            line-height: 1.4;
        }
        .message-time-stamp {
            font-size: 0.75em;
            color: #999;
            margin-top: 5px;
            text-align: right;
        }
        .message-bubble.my-message .message-time-stamp {
            color: rgba(255,255,255,0.8);
        }
        .chat-input-area {
            padding: 15px 20px;
            border-top: 1px solid #e0e6e9;
            background-color: #fefefe;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .chat-input-area textarea {
            flex-grow: 1;
            border: 1px solid #cfd8dc;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 1em;
            resize: none;
            min-height: 40px;
            max-height: 100px;
            overflow-y: auto;
        }
        .send-message-btn {
            background-color: #00BCD4;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .send-message-btn:hover { background-color: #00ACC1; }

        /* Modal for New Chat */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 100; /* Sit on top */
            left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.6);
            justify-content: center; align-items: center;
        }
        .modal-content {
            background-color: #fefefe; margin: auto; padding: 30px; border: 1px solid #888;
            width: 90%; max-width: 600px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative;
        }
        .modal-header { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h4 { margin: 0; color: #333; font-size: 1.5em; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: #000; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; }
        .form-group input[type="text"], .form-group select {
            width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 5px; font-size: 1rem; box-sizing: border-box;
        }
        .form-group select[multiple] {
            min-height: 120px;
        }
        .modal-footer { margin-top: 20px; text-align: right; }
        .btn-modal-submit, .btn-modal-cancel { padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
        .btn-modal-submit { background-color: #00BCD4; color: #fff; border: none; }
        .btn-modal-submit:hover { background-color: #00ACC1; }
        .btn-modal-cancel { background-color: #6c757d; color: #fff; border: none; }
        .btn-modal-cancel:hover { background-color: #5a6268; }

        .tab-buttons { display: flex; margin-bottom: 20px; border-bottom: 1px solid #eee; }
        .tab-button { flex-grow: 1; padding: 10px 15px; background: #f0f0f0; border: none; cursor: pointer; font-weight: 600; color: #555; transition: background 0.3s; }
        .tab-button.active-tab { background: #00BCD4; color: #fff; }
        .tab-button:hover:not(.active-tab) { background: #e0e0e0; }
        .tab-content { padding: 10px 0; }
        .tab-content.hidden { display: none; }

        /* No conversations/messages */
        .no-content-message {
            text-align: center;
            padding: 50px;
            color: #6c757d;
            font-size: 1.1em;
        }
        .online-dot {
            width: 8px;
            height: 8px;
            background-color: #ccc;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
            transition: background-color 0.3s;
        }
        .online-dot.is-online {
            background-color: #28a745; /* Green */
        }

        /* Responsive */
        @media (max-width: 992px) {
            .chat-sidebar { width: 100%; position: absolute; left: 0; height: 100%; z-index: 20; }
            .chat-main { flex-grow: 0; width: 100%; position: absolute; right: -100%; height: 100%; z-index: 10; transition: right 0.4s ease-in-out; }
            .chat-container.chat-open-main .chat-sidebar { left: -100%; }
            .chat-container.chat-open-main .chat-main { right: 0; }
            .chat-container { flex-direction: column; height: auto; min-height: 100vh; }
        }
        @media (max-width: 768px) {
            .chat-container { height: calc(100vh - 80px); } /* Adjust for smaller header/footer */
            .message-bubble { max-width: 85%; }
            .chat-input-area textarea { min-height: 35px; }
            .send-message-btn { width: 40px; height: 40px; font-size: 1em; }
        }
    </style>
</head>
<body>
<div class="main-content mt-28">
    <div class="chat-container">
        <!-- Conversations List (Left Panel) -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h3>Chats</h3>
                <button class="new-chat-btn" onclick="openNewChatModal()">
                    <i class="fas fa-plus"></i> New Chat
                </button>
            </div>
            <ul class="conversation-list" id="conversation-list">
                <!-- Conversations will be loaded here via AJAX -->
                <li class="no-content-message">Loading conversations...</li>
            </ul>
        </div>

        <!-- Chat Area (Right Panel) -->
        <div class="chat-main" id="chat-main">
            <div class="chat-main-header">
                <button class="back-to-list-btn" onclick="hideChatMain()" style="display:none;">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="conversation-avatar" id="main-chat-avatar"></div>
                <h3 id="main-chat-name">Select a Chat</h3>
                <span id="main-chat-online-status" class="online-dot" style="display:none;"></span>
            </div>
            <div class="chat-messages" id="chat-messages">
                <div class="no-content-message" id="chat-empty-message">No messages in this chat.</div>
            </div>
            <div class="chat-input-area" id="chat-input-area" style="display:none;">
                <textarea id="message-input" placeholder="Type your message..." rows="1"></textarea>
                <button class="send-message-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- New Chat Modal -->
<div id="newChatModal" class="modal ">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Start New Chat</h4>
            <span class="close-btn" onclick="closeNewChatModal()">&times;</span>
        </div>
        <div class="tab-buttons">
            <button class="tab-button active-tab" onclick="openTab(event, 'one-on-one-tab')">One-on-One</button>
            <button class="tab-button" onclick="openTab(event, 'group-chat-tab')">Group Chat</button>
        </div>

        <div id="one-on-one-tab" class="tab-content">
            <form action="principal_chat.php" method="POST">
                <input type="hidden" name="chat_action" value="create_one_on_one">
                <div class="form-group">
                    <label for="target_teacher_id">Select Staff Member:</label>
                    <select id="target_teacher_id" name="target_teacher_id" required>
                        <option value="">-- Select Staff --</option>
                        <?php foreach ($all_staff_for_new_chat as $staff): ?>
                            <option value="<?php echo htmlspecialchars($staff['id']); ?>">
                                <?php echo htmlspecialchars($staff['full_name']); ?> (<?php echo htmlspecialchars($staff['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeNewChatModal()">Cancel</button>
                    <button type="submit" class="btn-modal-submit">Start Chat</button>
                </div>
            </form>
        </div>

        <div id="group-chat-tab" class="tab-content hidden">
            <form action="principal_chat.php" method="POST">
                <input type="hidden" name="chat_action" value="create_group">
                <div class="form-group">
                    <label for="group_name">Group Name:</label>
                    <input type="text" id="group_name" name="group_name" required placeholder="e.g., Science Dept. Discussion">
                </div>
                <div class="form-group">
                    <label for="group_members">Select Members:</label>
                    <select id="group_members" name="group_members[]" multiple required size="5">
                        <?php foreach ($all_staff_for_new_chat as $staff): ?>
                            <option value="<?php echo htmlspecialchars($staff['id']); ?>">
                                <?php echo htmlspecialchars($staff['full_name']); ?> (<?php echo htmlspecialchars($staff['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Hold Ctrl/Cmd to select multiple members.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeNewChatModal()">Cancel</button>
                    <button type="submit" class="btn-modal-submit">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const principalId = <?php echo json_encode($principal_id); ?>;
    const principalName = <?php echo json_encode($principal_name); ?>;
    const principalRole = <?php echo json_encode($principal_role); ?>;
    const conversationList = document.getElementById('conversation-list');
    const chatMessages = document.getElementById('chat-messages');
    const messageInput = document.getElementById('message-input');
    const chatMainHeader = document.querySelector('.chat-main-header');
    const mainChatName = document.getElementById('main-chat-name');
    const mainChatAvatar = document.getElementById('main-chat-avatar');
    const mainChatOnlineStatus = document.getElementById('main-chat-online-status');
    const chatInputArea = document.getElementById('chat-input-area');
    let activeConversationId = null;
    let fetchMessagesInterval;
    let updateOnlineStatusInterval;

    const VAPID_PUBLIC_KEY = '<?php echo VAPID_PUBLIC_KEY; ?>'; // From config.php


    // --- General Chat Functions ---

    // Helper to format date/time
    function formatDateTime(datetimeString) {
        if (!datetimeString) return 'N/A';
        const date = new Date(datetimeString);
        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString(undefined, options);
    }

    // Helper to get initials for avatar
    function getInitials(name) {
        if (!name) return '?';
        const parts = name.split(' ');
        if (parts.length > 1) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return parts[0][0].toUpperCase();
    }

    // Function to load conversations list
    async function loadConversations() {
        try {
            const response = await fetch('ajax_chat.php?action=get_conversations');
            const data = await response.json();
            conversationList.innerHTML = ''; // Clear existing list

            if (data.conversations.length === 0) {
                conversationList.innerHTML = '<li class="no-content-message">No conversations yet. Start a new chat!</li>';
                return;
            }

            data.conversations.forEach(conv => {
                const item = document.createElement('li');
                item.className = 'conversation-item';
                if (conv.id == activeConversationId) {
                    item.classList.add('active-chat');
                }
                item.onclick = () => selectConversation(conv.id, conv.name, conv.type, conv.members_info);

                let avatarHtml = '';
                let conversationName = '';
                let lastMessageSnippet = conv.last_message ? `${conv.last_message.sender_name}: ${conv.last_message.message.substring(0, 30)}${conv.last_message.message.length > 30 ? '...' : ''}` : 'No messages yet.';
                let unreadBadge = conv.unread_count > 0 ? `<span class="unread-count">${conv.unread_count}</span>` : '';
                let onlineStatusDot = '';

                if (conv.type === 'one-on-one') {
                    const otherMember = conv.members_info.find(m => m.id != principalId);
                    conversationName = otherMember ? otherMember.full_name : 'Unknown User';
                    avatarHtml = `<div class="conversation-avatar">${getInitials(conversationName)}</div>`;
                    
                    if (otherMember) {
                        const lastSeen = new Date(otherMember.last_seen);
                        const now = new Date();
                        const isOnline = (now - lastSeen) < (5 * 60 * 1000); // Online if seen in last 5 mins
                        onlineStatusDot = `<span class="online-dot ${isOnline ? 'is-online' : ''}" title="${isOnline ? 'Online' : 'Last seen ' + formatDateTime(otherMember.last_seen)}"></span>`;
                    }
                } else { // group
                    conversationName = conv.group_name || 'Group Chat';
                    avatarHtml = `<div class="conversation-avatar"><i class="fas fa-users"></i></div>`;
                }

                item.innerHTML = `
                    <div class="conversation-avatar-wrapper" style="position:relative;">
                        ${avatarHtml}
                        ${onlineStatusDot}
                    </div>
                    <div class="conversation-info">
                        <div class="conversation-name">${conversationName}</div>
                        <div class="conversation-last-message">${lastMessageSnippet}</div>
                    </div>
                    ${unreadBadge}
                `;
                conversationList.appendChild(item);
            });
            // Update online statuses dynamically
            updateOnlineStatusInterval = setInterval(updateOnlineStatuses, 30000); // Every 30 seconds
        } catch (error) {
            console.error('Error loading conversations:', error);
            conversationList.innerHTML = '<li class="no-content-message">Failed to load conversations.</li>';
        }
    }

    async function updateOnlineStatuses() {
        try {
            const response = await fetch('ajax_chat.php?action=get_online_statuses');
            const onlineData = await response.json(); // { teacherId: { is_online: bool, last_seen: datetime } }

            document.querySelectorAll('.conversation-item').forEach(item => {
                const convId = item.onclick.toString().match(/selectConversation\((\d+)/)?.[1];
                if (!convId) return;

                const avatarWrapper = item.querySelector('.conversation-avatar-wrapper');
                let onlineDot = avatarWrapper.querySelector('.online-dot');
                if (!onlineDot) {
                    onlineDot = document.createElement('span');
                    onlineDot.className = 'online-status-indicator';
                    avatarWrapper.appendChild(onlineDot);
                }
                
                // For one-on-one chats
                const otherMemberId = item.onclick.toString().match(/selectConversation\(\d+,\s*'.*?',\s*'.*?',\s*\[.*?(\d+).*?\]\)/);
                if (onlineData[otherMemberId]) { // If it's a one-on-one and we have data for the other member
                    if (onlineData[otherMemberId].is_online) {
                        onlineDot.classList.add('online');
                        onlineDot.title = 'Online';
                    } else {
                        onlineDot.classList.remove('online');
                        onlineDot.title = 'Last seen ' + formatDateTime(onlineData[otherMemberId].last_seen);
                    }
                } else { // For groups or if no specific member found, hide dot
                    onlineDot.style.display = 'none';
                }
            });

            // Update main chat header online status too
            if (activeConversationId) {
                const convItem = document.querySelector(`.conversation-item.active-chat`);
                if (convItem) {
                    const onlineDot = convItem.querySelector('.online-status-indicator');
                    if (onlineDot && onlineDot.style.display !== 'none') {
                        mainChatOnlineStatus.className = onlineDot.className; // Copy classes
                        mainChatOnlineStatus.title = onlineDot.title;
                        mainChatOnlineStatus.style.display = 'inline-block';
                    } else {
                        mainChatOnlineStatus.style.display = 'none';
                    }
                }
            }


        } catch (error) {
            console.error('Error updating online statuses:', error);
        }
    }


    async function selectConversation(convId, convName, convType, membersInfo) {
        if (activeConversationId === convId) {
            // Already selected, just mark as read and refresh messages
            await markConversationAsRead(convId);
            return;
        }

        activeConversationId = convId;
        clearInterval(fetchMessagesInterval); // Clear any existing interval

        // Update UI for active chat
        document.querySelectorAll('.conversation-item').forEach(item => item.classList.remove('active-chat'));
        const selectedItem = document.querySelector(`.conversation-item[onclick*="selectConversation(${convId}"]`);
        if (selectedItem) {
            selectedItem.classList.add('active-chat');
            // Remove unread badge
            const unreadBadge = selectedItem.querySelector('.unread-count');
            if (unreadBadge) unreadBadge.remove();
        }

        mainChatName.textContent = convName;
        mainChatAvatar.innerHTML = convType === 'one-on-one' ? getInitials(convName) : '<i class="fas fa-users"></i>';
        mainChatOnlineStatus.style.display = 'none'; // Hide by default, update later if one-on-one

        chatInputArea.style.display = 'flex';
        document.getElementById('chat-empty-message').style.display = 'none';
        chatMessages.innerHTML = '<div class="no-content-message">Loading messages...</div>';

        // Display "Back to List" button on mobile
        if (window.innerWidth < 992) {
            document.querySelector('.back-to-list-btn').style.display = 'block';
            document.querySelector('.chat-container').classList.add('chat-open-main');
        }

        // Fetch messages initially
        await fetchMessages();
        // Start polling for new messages
        fetchMessagesInterval = setInterval(fetchMessages, 5000); // Every 5 seconds

        // Mark as read immediately
        await markConversationAsRead(convId);

        // Update online status in header for one-on-one
        if (convType === 'one-on-one') {
            const otherMember = membersInfo.find(m => m.id != principalId);
            if (otherMember) {
                const lastSeen = new Date(otherMember.last_seen);
                const now = new Date();
                const isOnline = (now - lastSeen) < (5 * 60 * 1000);
                mainChatOnlineStatus.className = `online-dot ${isOnline ? 'is-online' : ''}`;
                mainChatOnlineStatus.title = isOnline ? 'Online' : 'Last seen ' + formatDateTime(otherMember.last_seen);
                mainChatOnlineStatus.style.display = 'inline-block';
            }
        }
    }

    async function fetchMessages() {
        if (!activeConversationId) return;

        try {
            const response = await fetch(`ajax_chat.php?action=get_messages&conversation_id=${activeConversationId}`);
            const messages = await response.json();
            const currentScrollHeight = chatMessages.scrollHeight;
            const scrollBottom = chatMessages.scrollTop + chatMessages.clientHeight === currentScrollHeight;

            chatMessages.innerHTML = ''; // Clear current messages
            if (messages.length === 0) {
                chatMessages.innerHTML = '<div class="no-content-message">No messages in this chat.</div>';
            } else {
                messages.forEach(msg => {
                    const messageBubble = document.createElement('div');
                    messageBubble.className = `message-bubble ${msg.sender_id == principalId ? 'my-message' : ''}`;
                    
                    let senderName = msg.sender_name || (msg.user_role === 'Student' ? 'Student' : (msg.user_role === 'Teacher' ? 'Teacher' : 'Staff'));
                    if (msg.sender_id == principalId) senderName = "You"; // For logged-in user

                    messageBubble.innerHTML = `
                        <div class="message-content-wrapper">
                            <div class="message-sender-name">${senderName}</div>
                            <div class="message-text">${htmlspecialchars(msg.message_text)}</div>
                            <div class="message-time-stamp">${formatDateTime(msg.created_at)}</div>
                        </div>
                    `;
                    chatMessages.appendChild(messageBubble);
                });
            }

            // Scroll to bottom if it was already at the bottom or if it's the first load
            if (scrollBottom || messages.length === 0) { // messages.length === 0 condition for first load
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

        } catch (error) {
            console.error('Error fetching messages:', error);
            chatMessages.innerHTML = '<div class="no-content-message">Failed to load messages.</div>';
        }
    }

    async function sendMessage() {
        if (!activeConversationId || messageInput.value.trim() === '') return;

        const messageText = messageInput.value.trim();
        messageInput.value = ''; // Clear input immediately

        try {
            const response = await fetch('ajax_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'send_message',
                    conversation_id: activeConversationId,
                    sender_id: principalId,
                    message_text: messageText,
                    sender_role: principalRole
                })
            });
            const result = await response.json();
            if (result.success) {
                await fetchMessages(); // Refresh messages to show new one
                chatMessages.scrollTop = chatMessages.scrollHeight; // Scroll to bottom
            } else {
                console.error('Error sending message:', result.error);
                alert('Failed to send message: ' + result.error);
            }
        } catch (error) {
            console.error('Network error sending message:', error);
            alert('Network error: Could not send message.');
        }
    }

    async function markConversationAsRead(convId) {
        try {
            await fetch('ajax_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'mark_as_read',
                    conversation_id: convId,
                    reader_id: principalId
                })
            });
            loadConversations(); // Reload conversation list to update unread counts
        } catch (error) {
            console.error('Error marking as read:', error);
        }
    }

    // --- Modal Functions (New Chat) ---
    const newChatModal = document.getElementById('newChatModal');
    function openNewChatModal() {
        newChatModal.style.display = 'flex';
        // Reset to first tab
        openTab(null, 'one-on-one-tab');
        document.getElementById('target_teacher_id').value = '';
        document.getElementById('group_name').value = '';
        document.getElementById('group_members').selectedIndex = -1; // Deselect all
    }
    function closeNewChatModal() {
        newChatModal.style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target == newChatModal) {
            closeNewChatModal();
        }
    }
    function openTab(evt, tabName) {
        let i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.add("hidden");
        }
        tablinks = document.getElementsByClassName("tab-button");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active-tab");
        }
        document.getElementById(tabName).classList.remove("hidden");
        if(evt) evt.currentTarget.classList.add("active-tab");
        else document.querySelector(`.tab-button[onclick*="${tabName}"]`).classList.add("active-tab");
    }

    // --- Responsive UI Logic ---
    function showChatMain() {
        document.querySelector('.chat-container').classList.add('chat-open-main');
        document.querySelector('.back-to-list-btn').style.display = 'block';
    }
    function hideChatMain() {
        document.querySelector('.chat-container').classList.remove('chat-open-main');
        document.querySelector('.back-to-list-btn').style.display = 'none';
        activeConversationId = null; // Clear active conversation
        clearInterval(fetchMessagesInterval); // Stop polling messages
        chatMessages.innerHTML = '<div class="no-content-message">Select a chat to view messages.</div>';
        chatInputArea.style.display = 'none';
        mainChatName.textContent = 'Select a Chat';
        mainChatAvatar.innerHTML = '';
        mainChatOnlineStatus.style.display = 'none';
        document.querySelectorAll('.conversation-item').forEach(item => item.classList.remove('active-chat'));

    }
    if (window.innerWidth < 992) {
        // Initially hide main chat on mobile until a conversation is selected
        document.getElementById('chat-main').style.right = '-100%'; 
        document.querySelector('.back-to-list-btn').style.display = 'none';
        document.querySelector('.back-to-list-btn').onclick = hideChatMain; // Assign handler
    } else {
        document.querySelector('.back-to-list-btn').style.display = 'none'; // Always hide on desktop
    }


    // --- Push Notifications Registration (Service Worker) ---
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async function registerServiceWorkerAndSubscribe() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('Push notifications not supported by this browser.');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.register('../service-worker.js'); // Adjust path if needed
            console.log('Service Worker registered:', registration);

            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                console.log('No existing subscription, creating new one...');
                // You will need to define VAPID_PUBLIC_KEY in your PHP config.php
                const applicationServerKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey
                });
                console.log('New push subscription:', subscription);
            } else {
                console.log('Existing push subscription found:', subscription);
            }
            
            // Send subscription to your server to store it
            await fetch('ajax_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_subscription',
                    subscription: subscription,
                    user_id: principalId // Your logged-in user's ID
                })
            });
            console.log('Subscription saved on server.');

        } catch (error) {
            console.error('Service Worker registration or subscription failed:', error);
        }
    }

    // Call this function when the user grants permission (e.g., on page load, or after a button click)
    // Check Notification.permission first
    if (Notification.permission === 'granted') {
        registerServiceWorkerAndSubscribe();
    } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                registerServiceWorkerAndSubscribe();
            } else {
                console.warn('Notification permission denied.');
            }
        });
    }


    // Initial load of conversations
    loadConversations();

</script>
</body>
</html>

<?php
require_once './principal_footer.php';
?>