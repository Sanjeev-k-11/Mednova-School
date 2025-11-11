<?php
// chat_api.php

header('Content-Type: application/json');

require_once '../database/config.php';
require_once  '../database/cloudinary_upload_handler.php'; // Your Cloudinary upload function

// --- AUTHENTICATION & SESSION ---
// This is CRUCIAL. Ensure the user is logged in before proceeding.
session_start();
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}
$current_user_id = (int)$_SESSION['teacher_id'];
// ---------------------------------


// --- ACTION ROUTER ---
// Determines which function to run based on the 'action' parameter.
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_conversations':
        get_conversations($link, $current_user_id);
        break;
    case 'get_messages':
        get_messages($link, $current_user_id, (int)($_POST['conversation_id'] ?? 0));
        break;
    case 'send_message':
        send_message($link, $current_user_id);
        break;
    case 'mark_as_read':
        mark_as_read($link, $current_user_id, (int)($_POST['conversation_id'] ?? 0));
        break;
    case 'start_one_on_one':
        start_one_on_one_conversation($link, $current_user_id, (int)($_POST['other_teacher_id'] ?? 0));
        break;
    case 'get_all_teachers':
        get_all_teachers($link, $current_user_id);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}

mysqli_close($link);
exit;
// --------------------


/**
 * Fetches all conversations for the current user, including the last message
 * and unread message count for each.
 */
function get_conversations($link, $current_user_id) {
    // This is a complex query to get all necessary data in one go.
    $sql = "
        SELECT
            c.id AS conversation_id,
            c.type,
            c.group_name,
            c.group_avatar_url,
            c.last_message_at,
            -- For one-on-one, get the other person's details
            other_user.full_name AS conversation_name,
            other_user.image_url AS conversation_avatar,
            other_user.id as other_user_id,
            -- Get the last message details
            last_msg.message_text AS last_message_text,
            last_msg.image_url AS last_message_image,
            last_msg_sender.full_name AS last_message_sender,
            -- Count unread messages for the current user
            (SELECT COUNT(*)
             FROM messages m
             LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.reader_id = ?
             WHERE m.conversation_id = c.id AND m.sender_id != ? AND mrs.id IS NULL
            ) AS unread_count
        FROM conversation_members cm
        JOIN conversations c ON cm.conversation_id = c.id
        -- Join to find the other member in a one-on-one chat
        LEFT JOIN conversation_members other_cm ON c.id = other_cm.conversation_id AND other_cm.teacher_id != ?
        LEFT JOIN teachers other_user ON other_cm.teacher_id = other_user.id AND c.type = 'one-on-one'
        -- Join to get the very last message in the conversation
        LEFT JOIN messages last_msg ON c.id = last_msg.conversation_id AND last_msg.id = (
            SELECT MAX(id) FROM messages WHERE conversation_id = c.id
        )
        LEFT JOIN teachers last_msg_sender ON last_msg.sender_id = last_msg_sender.id
        WHERE cm.teacher_id = ?
        ORDER BY c.last_message_at DESC
    ";

    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, 'iiii', $current_user_id, $current_user_id, $current_user_id, $current_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $conversations = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // If group name is set, use it as the conversation name
    foreach ($conversations as &$convo) {
        if ($convo['type'] === 'group') {
            $convo['conversation_name'] = $convo['group_name'];
            $convo['conversation_avatar'] = $convo['group_avatar_url'] ?: 'default_group_avatar.png'; // Provide a default
        }
    }

    echo json_encode(['status' => 'success', 'conversations' => $conversations]);
}

/**
 * Fetches all messages for a specific conversation.
 */
function get_messages($link, $current_user_id, $conversation_id) {
    if ($conversation_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid conversation ID.']);
        return;
    }

    // First, verify the user is a member of this conversation
    $verify_sql = "SELECT id FROM conversation_members WHERE conversation_id = ? AND teacher_id = ?";
    $stmt_verify = mysqli_prepare($link, $verify_sql);
    mysqli_stmt_bind_param($stmt_verify, 'ii', $conversation_id, $current_user_id);
    mysqli_stmt_execute($stmt_verify);
    if (mysqli_stmt_get_result($stmt_verify)->num_rows === 0) {
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
        return;
    }

    // Fetch messages
    $sql = "
        SELECT
            m.id,
            m.sender_id,
            m.message_text,
            m.image_url,
            m.created_at,
            t.full_name AS sender_name,
            t.image_url AS sender_avatar
        FROM messages m
        JOIN teachers t ON m.sender_id = t.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $conversation_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);

    echo json_encode(['status' => 'success', 'messages' => $messages]);
}

/**
 * Sends a new message (text and/or image) to a conversation.
 */
function send_message($link, $current_user_id) {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message_text = trim($_POST['message_text'] ?? '');
    $image_file = $_FILES['image_file'] ?? null;

    if ($conversation_id <= 0 || (empty($message_text) && (empty($image_file) || $image_file['error'] !== UPLOAD_ERR_OK))) {
        echo json_encode(['status' => 'error', 'message' => 'Missing conversation ID or message content.']);
        return;
    }

    // Again, verify membership
    $verify_sql = "SELECT c.type FROM conversation_members cm JOIN conversations c ON cm.conversation_id = c.id WHERE cm.conversation_id = ? AND cm.teacher_id = ?";
    $stmt_verify = mysqli_prepare($link, $verify_sql);
    mysqli_stmt_bind_param($stmt_verify, 'ii', $conversation_id, $current_user_id);
    mysqli_stmt_execute($stmt_verify);
    $result_verify = mysqli_stmt_get_result($stmt_verify);
    if ($result_verify->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
        return;
    }
    $conversation_type = mysqli_fetch_assoc($result_verify)['type'];

    // Handle image upload to Cloudinary
    $image_url = null;
    $public_id = null;
    if ($image_file && $image_file['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadToCloudinary($image_file, 'chat_images');
        if (isset($uploadResult['secure_url'])) {
            $image_url = $uploadResult['secure_url'];
            $public_id = $uploadResult['public_id'];
        } else {
            echo json_encode(['status' => 'error', 'message' => $uploadResult['error'] ?? 'Image upload failed.']);
            return;
        }
    }
    
    // Determine receiver_id for one-on-one chats
    $receiver_id = 0; // 0 or NULL for group chats
    if ($conversation_type === 'one-on-one') {
        $receiver_sql = "SELECT teacher_id FROM conversation_members WHERE conversation_id = ? AND teacher_id != ?";
        $stmt_receiver = mysqli_prepare($link, $receiver_sql);
        mysqli_stmt_bind_param($stmt_receiver, 'ii', $conversation_id, $current_user_id);
        mysqli_stmt_execute($stmt_receiver);
        $res_receiver = mysqli_stmt_get_result($stmt_receiver);
        if($row = mysqli_fetch_assoc($res_receiver)) {
            $receiver_id = $row['teacher_id'];
        }
    }


    // Use a transaction to ensure data integrity
    mysqli_begin_transaction($link);

    try {
        // Insert the message
        $sql_insert = "INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text, image_url, public_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($link, $sql_insert);
        mysqli_stmt_bind_param($stmt_insert, 'iiisss', $conversation_id, $current_user_id, $receiver_id, $message_text, $image_url, $public_id);
        mysqli_stmt_execute($stmt_insert);
        $message_id = mysqli_insert_id($link);

        // Update the conversation's last_message_at timestamp
        $sql_update = "UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, 'i', $conversation_id);
        mysqli_stmt_execute($stmt_update);

        mysqli_commit($link);
        echo json_encode(['status' => 'success', 'message' => 'Message sent.', 'message_id' => $message_id]);

    } catch (Exception $e) {
        mysqli_rollback($link);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}


/**
 * Marks all messages in a conversation as read by the current user.
 */
function mark_as_read($link, $current_user_id, $conversation_id) {
    if ($conversation_id <= 0) return;

    // Find all unread message IDs for this user in this conversation
    $sql_find_unread = "
        SELECT m.id FROM messages m
        LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.reader_id = ?
        WHERE m.conversation_id = ? AND m.sender_id != ? AND mrs.id IS NULL
    ";
    $stmt_find = mysqli_prepare($link, $sql_find_unread);
    mysqli_stmt_bind_param($stmt_find, 'iii', $current_user_id, $conversation_id, $current_user_id);
    mysqli_stmt_execute($stmt_find);
    $result = mysqli_stmt_get_result($stmt_find);
    $unread_messages = mysqli_fetch_all($result, MYSQLI_ASSOC);

    if (empty($unread_messages)) {
        echo json_encode(['status' => 'success', 'message' => 'No new messages to mark as read.']);
        return;
    }

    // Prepare a bulk insert statement
    $sql_insert_read = "INSERT INTO message_read_status (message_id, conversation_id, reader_id) VALUES (?, ?, ?)";
    $stmt_insert = mysqli_prepare($link, $sql_insert_read);

    foreach ($unread_messages as $message) {
        $message_id = $message['id'];
        mysqli_stmt_bind_param($stmt_insert, 'iii', $message_id, $conversation_id, $current_user_id);
        mysqli_stmt_execute($stmt_insert);
    }

    echo json_encode(['status' => 'success', 'message' => 'Messages marked as read.']);
}

/**
 * Finds an existing 1-on-1 conversation or creates a new one.
 */
function start_one_on_one_conversation($link, $user1_id, $user2_id) {
    if ($user1_id <= 0 || $user2_id <= 0 || $user1_id == $user2_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid teacher IDs provided.']);
        return;
    }

    // Check if a one-on-one conversation already exists between these two users
    $sql_find = "
        SELECT cm1.conversation_id
        FROM conversation_members cm1
        JOIN conversation_members cm2 ON cm1.conversation_id = cm2.conversation_id
        JOIN conversations c ON cm1.conversation_id = c.id
        WHERE cm1.teacher_id = ? AND cm2.teacher_id = ? AND c.type = 'one-on-one'
    ";
    $stmt_find = mysqli_prepare($link, $sql_find);
    mysqli_stmt_bind_param($stmt_find, 'ii', $user1_id, $user2_id);
    mysqli_stmt_execute($stmt_find);
    $result = mysqli_stmt_get_result($stmt_find);

    if ($row = mysqli_fetch_assoc($result)) {
        // Conversation exists
        echo json_encode(['status' => 'success', 'conversation_id' => $row['conversation_id'], 'existed' => true]);
        return;
    }

    // If not, create a new one
    mysqli_begin_transaction($link);
    try {
        // 1. Create conversation
        $sql_create_convo = "INSERT INTO conversations (type, created_by, last_message_at) VALUES ('one-on-one', ?, NOW())";
        $stmt_create_convo = mysqli_prepare($link, $sql_create_convo);
        mysqli_stmt_bind_param($stmt_create_convo, 'i', $user1_id);
        mysqli_stmt_execute($stmt_create_convo);
        $conversation_id = mysqli_insert_id($link);

        // 2. Add both members
        $sql_add_members = "INSERT INTO conversation_members (conversation_id, teacher_id) VALUES (?, ?), (?, ?)";
        $stmt_add_members = mysqli_prepare($link, $sql_add_members);
        mysqli_stmt_bind_param($stmt_add_members, 'iiii', $conversation_id, $user1_id, $conversation_id, $user2_id);
        mysqli_stmt_execute($stmt_add_members);

        mysqli_commit($link);
        echo json_encode(['status' => 'success', 'conversation_id' => $conversation_id, 'existed' => false]);
    } catch (Exception $e) {
        mysqli_rollback($link);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create conversation.']);
    }
}

/**
 * Gets a list of all teachers except the current one.
 */
function get_all_teachers($link, $current_user_id) {
    $sql = "SELECT id, full_name, image_url FROM teachers WHERE id != ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $current_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $teachers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    echo json_encode(['status' => 'success', 'teachers' => $teachers]);
}

?>
