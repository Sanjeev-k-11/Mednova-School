<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & AUTHORIZATION for AJAX ---
// This AJAX endpoint needs to be secure.
// Only logged-in staff (Principal, Teacher, Admin) can access it.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role"], ['Principle', 'Teacher', 'Admin'])) {
    http_response_code(403); // Forbidden
    echo json_encode(["error" => "Unauthorized access."]);
    exit;
}

$user_id = $_SESSION["id"];
$user_role = $_SESSION["role"];

header('Content-Type: application/json'); // All responses will be JSON

$action = $_REQUEST['action'] ?? ''; // Use $_REQUEST to handle both GET/POST

switch ($action) {
    case 'get_conversations':
        // Fetch all conversations for the current user, with latest message and unread count
        $conversations = [];
        $sql_get_convs = "SELECT
                            c.id, c.type, c.group_name, c.group_avatar_url, c.last_message_at,
                            (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message_text,
                            (SELECT m.sender_id FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message_sender_id,
                            (SELECT COALESCE(s.full_name, t.full_name, p.full_name) FROM messages m
                                LEFT JOIN students s ON m.sender_id = s.id AND m.user_role = 'Student'
                                LEFT JOIN teachers t ON m.sender_id = t.id AND m.user_role = 'Teacher'
                                LEFT JOIN principles p ON m.sender_id = p.id AND m.user_role = 'Principle'
                                WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message_sender_name,
                            (SELECT COUNT(m.id) FROM messages m
                                LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.reader_id = ?
                                WHERE m.conversation_id = c.id AND mrs.message_id IS NULL AND m.sender_id != ?) AS unread_count,
                            GROUP_CONCAT(
                                JSON_OBJECT(
                                    'id', mem.teacher_id,
                                    'full_name', COALESCE(tmem.full_name, pmem.full_name, amem.full_name),
                                    'role', CASE WHEN tmem.id IS NOT NULL THEN 'Teacher' WHEN pmem.id IS NOT NULL THEN 'Principle' ELSE 'Admin' END,
                                    'last_seen', tmem.last_seen -- Assuming last_seen is only on teachers for now
                                )
                            ) AS members_json
                          FROM conversations c
                          JOIN conversation_members cm ON c.id = cm.conversation_id
                          LEFT JOIN conversation_members mem ON c.id = mem.conversation_id
                          LEFT JOIN teachers tmem ON mem.teacher_id = tmem.id
                          LEFT JOIN principles pmem ON mem.teacher_id = pmem.id -- Join for principals if they are members
                          LEFT JOIN admins amem ON mem.teacher_id = amem.id -- Join for admins if they are members
                          WHERE cm.teacher_id = ? -- Current user is a member
                          GROUP BY c.id
                          ORDER BY c.last_message_at DESC NULLS LAST, c.created_at DESC";

        if ($stmt = mysqli_prepare($link, $sql_get_convs)) {
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $row['members_info'] = json_decode("[" . $row['members_json'] . "]", true); // Decode JSON array
                unset($row['members_json']);

                // Determine conversation name for one-on-one based on other member
                if ($row['type'] === 'one-on-one') {
                    $otherMember = null;
                    foreach ($row['members_info'] as $member) {
                        if ($member['id'] != $user_id) {
                            $otherMember = $member;
                            break;
                        }
                    }
                    $row['name'] = $otherMember ? $otherMember['full_name'] : 'Unknown User';
                } else {
                    $row['name'] = $row['group_name'];
                }

                $row['last_message'] = $row['last_message_text'] ? [
                    'sender_id' => $row['last_message_sender_id'],
                    'sender_name' => $row['last_message_sender_name'],
                    'message' => $row['last_message_text']
                ] : null;
                unset($row['last_message_text'], $row['last_message_sender_id'], $row['last_message_sender_name']);

                $conversations[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        echo json_encode(['conversations' => $conversations]);
        break;

    case 'get_messages':
        $conversation_id = (int)($_GET['conversation_id'] ?? 0);
        $messages = [];
        if ($conversation_id > 0) {
            // Check if user is a member of this conversation
            $sql_check_member = "SELECT id FROM conversation_members WHERE conversation_id = ? AND teacher_id = ?";
            if ($stmt_check = mysqli_prepare($link, $sql_check_member)) {
                mysqli_stmt_bind_param($stmt_check, "ii", $conversation_id, $user_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) == 0) {
                    echo json_encode(["error" => "Access denied to this conversation."]);
                    mysqli_stmt_close($stmt_check);
                    exit;
                }
                mysqli_stmt_close($stmt_check);
            }

            $sql_get_messages = "SELECT
                                    m.id, m.sender_id, m.message_text, m.created_at, m.image_url, m.user_role,
                                    COALESCE(s.full_name, t.full_name, p.full_name) AS sender_name
                                 FROM messages m
                                 LEFT JOIN students s ON m.sender_id = s.id AND m.user_role = 'Student'
                                 LEFT JOIN teachers t ON m.sender_id = t.id AND m.user_role = 'Teacher'
                                 LEFT JOIN principles p ON m.sender_id = p.id AND m.user_role = 'Principle'
                                 WHERE m.conversation_id = ?
                                 ORDER BY m.created_at ASC";
            if ($stmt = mysqli_prepare($link, $sql_get_messages)) {
                mysqli_stmt_bind_param($stmt, "i", $conversation_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
                mysqli_stmt_close($stmt);
            }
        }
        echo json_encode($messages);
        break;

    case 'send_message':
        $conversation_id = (int)($_POST['conversation_id'] ?? 0);
        $sender_id = (int)($_POST['sender_id'] ?? 0); // This should be $user_id
        $message_text = trim($_POST['message_text'] ?? '');
        $sender_role = trim($_POST['sender_role'] ?? ''); // 'Principle' in this case

        if ($conversation_id > 0 && $sender_id === $user_id && !empty($message_text)) {
            // Update last_message_at for conversation
            $sql_update_conv_time = "UPDATE conversations SET last_message_at = NOW() WHERE id = ?";
            $stmt_conv_time = mysqli_prepare($link, $sql_update_conv_time);
            mysqli_stmt_bind_param($stmt_conv_time, "i", $conversation_id);
            mysqli_stmt_execute($stmt_conv_time);
            mysqli_stmt_close($stmt_conv_time);

            // Insert message
            $sql_insert_msg = "INSERT INTO messages (conversation_id, sender_id, user_role, message_text) VALUES (?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql_insert_msg)) {
                mysqli_stmt_bind_param($stmt, "iiss", $conversation_id, $sender_id, $sender_role, $message_text);
                if (mysqli_stmt_execute($stmt)) {
                    $new_message_id = mysqli_insert_id($link);

                    // Optional: Trigger push notifications for other members
                    // This requires a separate `send_push_notification.php` script
                    // and a `web-push` library. Example call:
                    // require 'send_push_notification.php';
                    // send_push_notification($conversation_id, $sender_id, $message_text, $link);

                    echo json_encode(['success' => true, 'message_id' => $new_message_id]);
                } else {
                    echo json_encode(['success' => false, 'error' => mysqli_error($link)]);
                }
                mysqli_stmt_close($stmt);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to prepare statement for sending message.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data for sending message.']);
        }
        break;

    case 'mark_as_read':
        $conversation_id = (int)($_POST['conversation_id'] ?? 0);
        $reader_id = (int)($_POST['reader_id'] ?? 0); // This should be $user_id

        if ($conversation_id > 0 && $reader_id === $user_id) {
            // Get all unread messages for this user in this conversation
            $sql_get_unread = "SELECT m.id FROM messages m
                               LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.reader_id = ?
                               WHERE m.conversation_id = ? AND mrs.message_id IS NULL AND m.sender_id != ?";
            
            if ($stmt_get_unread = mysqli_prepare($link, $sql_get_unread)) {
                mysqli_stmt_bind_param($stmt_get_unread, "iii", $reader_id, $conversation_id, $reader_id);
                mysqli_stmt_execute($stmt_get_unread);
                $result_unread = mysqli_stmt_get_result($stmt_get_unread);
                $unread_messages = mysqli_fetch_all($result_unread, MYSQLI_ASSOC);
                mysqli_stmt_close($stmt_get_unread);

                if (!empty($unread_messages)) {
                    $insert_values = [];
                    $insert_params = [];
                    $insert_types = str_repeat("ii", count($unread_messages)); // Each message needs conversation_id, message_id, reader_id

                    foreach ($unread_messages as $msg) {
                        $insert_values[] = "(?, ?, ?)";
                        $insert_params[] = $msg['id'];
                        $insert_params[] = $conversation_id;
                        $insert_params[] = $reader_id;
                    }
                    
                    $sql_mark_read = "INSERT INTO message_read_status (message_id, conversation_id, reader_id) VALUES " . implode(", ", $insert_values);
                    if ($stmt_mark_read = mysqli_prepare($link, $sql_mark_read)) {
                        mysqli_stmt_bind_param($stmt_mark_read, $insert_types, ...$insert_params);
                        mysqli_stmt_execute($stmt_mark_read);
                        mysqli_stmt_close($stmt_mark_read);
                    }
                }
            }
             // Update user's last_seen
            $sql_update_last_seen = "UPDATE teachers SET last_seen = NOW() WHERE id = ? AND ? = 'Teacher'"; // Only update if they are a 'Teacher' role
            if ($user_role === 'Principle' || $user_role === 'Admin') { // If Principal or Admin, update their respective tables
                $sql_update_last_seen = "UPDATE " . strtolower($user_role) . "s SET last_seen = NOW() WHERE id = ?";
                if ($user_role === 'Principle') {
                    $sql_update_last_seen = "UPDATE principles SET last_seen = NOW() WHERE id = ?";
                } else if ($user_role === 'Admin') {
                     $sql_update_last_seen = "UPDATE admins SET last_seen = NOW() WHERE id = ?";
                }
                if ($stmt_last_seen = mysqli_prepare($link, $sql_update_last_seen)) {
                    mysqli_stmt_bind_param($stmt_last_seen, "i", $user_id);
                    mysqli_stmt_execute($stmt_last_seen);
                    mysqli_stmt_close($stmt_last_seen);
                }
            } else { // Assume it's a teacher
                if ($stmt_last_seen = mysqli_prepare($link, $sql_update_last_seen)) {
                    mysqli_stmt_bind_param($stmt_last_seen, "i", $user_id);
                    mysqli_stmt_execute($stmt_last_seen);
                    mysqli_stmt_close($stmt_last_seen);
                }
            }


            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data for marking as read.']);
        }
        break;
        
    case 'get_online_statuses':
        // Fetch last_seen for all teachers
        $online_statuses = [];
        $sql_online = "SELECT id, last_seen FROM teachers"; // Fetch all teachers
        $result = mysqli_query($link, $sql_online);
        
        $five_minutes_ago = strtotime("-5 minutes");

        while($row = mysqli_fetch_assoc($result)) {
            $last_seen_timestamp = strtotime($row['last_seen']);
            $is_online = ($last_seen_timestamp > $five_minutes_ago);
            $online_statuses[$row['id']] = [
                'is_online' => $is_online,
                'last_seen' => $row['last_seen']
            ];
        }
        echo json_encode($online_statuses);
        break;


    case 'save_subscription':
        // Handle incoming push subscription from browser
        $data = json_decode(file_get_contents('php://input'), true);
        $subscription = $data['subscription'] ?? null;
        $user_id_sub = $data['user_id'] ?? null;

        if ($subscription && $user_id_sub === $user_id) { // Ensure the subscription is for the logged-in user
            $endpoint = $subscription['endpoint'];
            $public_key = $subscription['keys']['p256dh'];
            $auth_token = $subscription['keys']['auth'];
            $content_encoding = 'aesgcm'; // Most common, adjust if your client sends differently

            // Upsert the subscription (insert or update if endpoint already exists for user)
            $sql_upsert = "INSERT INTO push_subscriptions (teacher_id, endpoint, public_key, auth_token, content_encoding)
                           VALUES (?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE
                           endpoint = VALUES(endpoint), public_key = VALUES(public_key), auth_token = VALUES(auth_token), content_encoding = VALUES(content_encoding)";
            
            if ($stmt = mysqli_prepare($link, $sql_upsert)) {
                mysqli_stmt_bind_param($stmt, "issss", $user_id_sub, $endpoint, $public_key, $auth_token, $content_encoding);
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => mysqli_error($link)]);
                }
                mysqli_stmt_close($stmt);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to prepare subscription statement.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid subscription data.']);
        }
        break;

    default:
        echo json_encode(["error" => "Invalid action."]);
        break;
}

mysqli_close($link);
?>