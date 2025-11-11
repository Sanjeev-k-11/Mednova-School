<?php
session_start();
if (!isset($_SESSION['teacher_id'])) {
    // In a real app, redirect to login. For now, we'll set a default.
    $_SESSION['teacher_id'] = 1; 
}
$current_user_id = $_SESSION['teacher_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Staff Chat</title>
    <style>
        :root {
            --bg-color: #f0f2f5; --primary-white: #fff; --sidebar-header-bg: #f5f5f5; --border-color: #e0e0e0; --text-primary: #111b21; --text-secondary: #667781; --accent-color: #007bff; --sent-bubble-bg: #dcf8c6; --received-bubble-bg: #fff; --notification-badge-bg: #25d366; --chat-background: #efeae2;
        }
        body, html { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); height: 100%; overflow: hidden; }
        * { box-sizing: border-box; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #aaa; }
        .chat-container { display: flex; height: 100vh; width: 100%; background: var(--primary-white); }
        .sidebar { width: 350px; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; transition: transform 0.3s ease-in-out; }
        .main-chat { flex: 1; display: flex; flex-direction: column; background-color: var(--chat-background); overflow: hidden; }
        .sidebar-header { padding: 10px 16px; background-color: var(--sidebar-header-bg); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .sidebar-header h3 { margin: 0; font-size: 1.2rem; color: var(--text-primary); }
        .new-chat-btn { background: none; border: none; cursor: pointer; padding: 8px; border-radius: 50%; }
        .new-chat-btn:hover { background-color: #e7e7e7; }
        .new-chat-btn svg { width: 24px; height: 24px; fill: var(--text-secondary); }
        .conversations-list { flex: 1; overflow-y: auto; }
        .conversation-item { display: flex; align-items: center; padding: 12px 15px; cursor: pointer; border-bottom: 1px solid var(--border-color); transition: background-color 0.2s; }
        .conversation-item:hover { background-color: var(--bg-color); }
        .conversation-item.active { background-color: #e8e8e8; }
        .convo-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 15px; flex-shrink: 0; }
        .convo-details { flex: 1; overflow: hidden; }
        .convo-details .name { font-weight: 600; margin: 0 0 4px 0; color: var(--text-primary); }
        .convo-details .last-message { font-size: 0.9em; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .convo-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
        .unread-badge { background-color: var(--notification-badge-bg); color: white; font-size: 0.75em; font-weight: bold; padding: 3px 7px; border-radius: 12px; }
        #chat-window { display: none; flex: 1; flex-direction: column; overflow: hidden; }
        #placeholder { text-align: center; margin: auto; color: #777; max-width: 80%; }
        #placeholder h2 { font-weight: 400; }
        .chat-header { display: flex; align-items: center; padding: 10px 16px; background-color: var(--sidebar-header-bg); border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
        .chat-header .back-btn { background: none; border: none; cursor: pointer; display: none; margin-right: 10px; }
        .chat-header .back-btn svg { width: 24px; height: 24px; }
        .chat-header img { width: 40px; height: 40px; border-radius: 50%; margin-right: 15px; }
        .chat-header h4 { margin: 0; font-size: 1.1rem; }
        .messages-container { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        .message-bubble { max-width: 65%; padding: 8px 12px; border-radius: 8px; line-height: 1.4; word-wrap: break-word; box-shadow: 0 1px 1px rgba(0,0,0,0.05); }
        .message-bubble.sent { background-color: var(--sent-bubble-bg); align-self: flex-end; border-bottom-right-radius: 0; position: relative; }
        .message-bubble.received { background-color: var(--received-bubble-bg); align-self: flex-start; border-bottom-left-radius: 0; }
        .message-bubble .sender-name { font-size: 0.8em; font-weight: bold; color: var(--accent-color); margin-bottom: 4px; display: block; }
        .message-bubble .message-content p { margin: 0; }
        .message-bubble .timestamp { font-size: 0.75em; color: var(--text-secondary); text-align: right; margin-top: 5px; display: block; }
        .message-bubble img.message-image { max-width: 100%; border-radius: 6px; margin-top: 5px; display: block; cursor: zoom-in; }
        .message-form-container { padding: 10px 16px; background-color: var(--sidebar-header-bg); border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        #message-form { display: flex; align-items: center; width: 100%; background: var(--primary-white); border-radius: 25px; padding: 5px; }
        #message-text { flex: 1; border: none; padding: 10px; resize: none; font-size: 1em; background: transparent; max-height: 100px; overflow-y: auto; outline: none;}
        #message-form button, #image-upload-label { border: none; background: none; cursor: pointer; padding: 8px; color: var(--text-secondary); flex-shrink: 0; }
        #image-upload { display: none; }
        #message-form svg { width: 24px; height: 24px; fill: var(--text-secondary); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px; }
        .modal-close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .teacher-list-item { padding: 12px 8px; cursor: pointer; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 15px; }
        .teacher-list-item:hover { background-color: #f1f1f1; }
        .teacher-list-item img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .image-viewer-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); justify-content: center; align-items: center; }
        .modal-image-content { max-width: 90%; max-height: 90%; object-fit: contain; }
        .close-image-viewer { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
        .message-actions { position: absolute; top: -12px; right: -5px; display: none; background: white; border-radius: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1;}
        .message-bubble:hover .message-actions { display: block; }
        .delete-btn { background: none; border: none; cursor: pointer; font-size: 16px; padding: 4px 8px; }
        @media (max-width: 768px) {
            .sidebar { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; transform: translateX(0); }
            .main-chat { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 11; transform: translateX(100%); transition: transform 0.3s ease-in-out; }
            .chat-container.show-chat .sidebar { transform: translateX(-100%); }
            .chat-container.show-chat .main-chat { transform: translateX(0); }
            .chat-header .back-btn { display: block; }
            #placeholder { display: none; }
        }
    </style>
</head>
<body>

    <div class="chat-container  mt-28 sm:mt-10 p-28" id="chat-container">
        <!-- Sidebar with conversations -->
        <aside class="sidebar">
  <div class="sidebar-header">
    <h3>Chats</h3>
    <div class="sidebar-actions">
      <!-- Go to Dashboard -->
      <a href="dashboard.php" class="dashboard-btn" title="Go to Dashboard">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
          <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
        </svg>
      </a>

      <!-- Start New Chat -->
      <button class="new-chat-btn" id="newChatBtn" title="Start New Chat">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
          <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
        </svg>
      </button>
    </div>
  </div>

  <div class="conversations-list" id="conversations-list"></div>
</aside>


        <!-- Main chat window -->
        <main class="main-chat">
            <div id="chat-window">
                <header class="chat-header">
                    <button class="back-btn" id="back-to-convos-btn">
                        <svg viewBox="0 0 24 24"><path d="M20,11V13H8L13.5,18.5L12.08,19.92L4.16,12L12.08,4.08L13.5,5.5L8,11H20Z" /></svg>
                    </button>
                    <img id="chat-avatar" src="default_avatar.png" alt="Avatar">
                    <h4 id="chat-name">Select a Chat</h4>
                </header>
                <div class="messages-container" id="messages-container"></div>
                <div class="message-form-container">
                    <form id="message-form" enctype="multipart/form-data">
                        <input type="hidden" name="conversation_id" id="conversation_id_input">
                        <input type="hidden" name="action" value="send_message">
                        <input type="file" name="image_file" id="image-upload" accept="image/*">
                        <label for="image-upload" id="image-upload-label" title="Attach Image">
                            <svg viewBox="0 0 24 24"><path d="M16.5,6V17.5A4,4 0 0,1 12.5,21.5A4,4 0 0,1 8.5,17.5V5A2.5,2.5 0 0,1 11,2.5A2.5,2.5 0 0,1 13.5,5V15.5A1,1 0 0,1 12.5,16.5A1,1 0 0,1 11.5,15.5V6H10V15.5A2.5,2.5 0 0,0 12.5,18A2.5,2.5 0 0,0 15,15.5V5A4,4 0 0,0 11,1A4,4 0 0,0 7,5V17.5A5.5,5.5 0 0,0 12.5,23A5.5,5.5 0 0,0 18,17.5V6H16.5Z" /></svg>
                        </label>
                        <textarea name="message_text" id="message-text" rows="1" placeholder="Type a message..."></textarea>
                        <button type="submit" title="Send Message">
                            <svg viewBox="0 0 24 24"><path d="M2,21L23,12L2,3V10L17,12L2,14V21Z" /></svg>
                        </button>
                    </form>
                </div>
            </div>
            <div id="placeholder">
                <h2>Select a chat to start messaging</h2>
            </div>
        </main>
    </div>

    <!-- Modal for starting a new chat -->
    <div id="newChatModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Start a new conversation</h2>
                <span class="modal-close">&times;</span>
            </div>
            <div id="teacher-list"></div>
        </div>
    </div>
    
    <!-- Modal for viewing images -->
    <div id="imageViewerModal" class="image-viewer-modal">
        <span class="close-image-viewer">&times;</span>
        <img class="modal-image-content" id="fullScreenImage">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const currentUserId = <?php echo json_encode($current_user_id); ?>;
            let activeConversationId = null;
            let pollingInterval = null;
            const chatContainer = document.getElementById('chat-container');
            const convoListEl = document.getElementById('conversations-list');
            const messagesContainerEl = document.getElementById('messages-container');
            const messageForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-text');
            const conversationIdInput = document.getElementById('conversation_id_input');
            const chatWindow = document.getElementById('chat-window');
            const placeholder = document.getElementById('placeholder');
            const chatAvatar = document.getElementById('chat-avatar');
            const chatName = document.getElementById('chat-name');
            const backBtn = document.getElementById('back-to-convos-btn');
            const newChatBtn = document.getElementById('newChatBtn');
            const modal = document.getElementById('newChatModal');
            const modalClose = document.querySelector('.modal-close');
            const teacherListEl = document.getElementById('teacher-list');
            const imageViewerModal = document.getElementById('imageViewerModal');
            const fullScreenImage = document.getElementById('fullScreenImage');
            const closeImageViewerBtn = document.querySelector('.close-image-viewer');
            
            const apiCall = async (action, formData) => {
                try {
                    const response = await fetch('chat_api.php', { method: 'POST', body: formData });
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return await response.json();
                } catch (error) { console.error('API Call Error:', error); return { status: 'error', message: error.message }; }
            };
            const fetchAndRenderConversations = async () => {
                const formData = new FormData(); formData.append('action', 'get_conversations');
                const data = await apiCall('get_conversations', formData);
                if (data.status === 'success') renderConversations(data.conversations);
            };
            const fetchAndRenderMessages = async (conversationId) => {
                if (!conversationId) return;
                const formData = new FormData(); formData.append('action', 'get_messages'); formData.append('conversation_id', conversationId);
                const data = await apiCall('get_messages', formData);
                if (data.status === 'success') { renderMessages(data.messages); markConversationAsRead(conversationId); }
            };
            const scrollToBottom = () => { messagesContainerEl.scrollTop = messagesContainerEl.scrollHeight; };
            const sendMessage = async (event) => {
                event.preventDefault();
                const formData = new FormData(messageForm);
                if (!formData.get('message_text').trim() && (!formData.get('image_file') || formData.get('image_file').size === 0)) return;
                const data = await apiCall('send_message', formData);
                if (data.status === 'success') {
                    messageForm.reset(); messageInput.style.height = 'auto'; conversationIdInput.value = activeConversationId;
                    await fetchAndRenderMessages(activeConversationId);
                    fetchAndRenderConversations();
                } else { alert('Error sending message: ' + data.message); }
            };
            const markConversationAsRead = (conversationId) => {
                const formData = new FormData(); formData.append('action', 'mark_as_read'); formData.append('conversation_id', conversationId);
                apiCall('mark_as_read', formData);
                const convoItem = document.querySelector(`.conversation-item[data-id='${conversationId}'] .unread-badge`);
                if (convoItem) convoItem.style.display = 'none';
            };
            const renderConversations = (conversations) => {
                convoListEl.innerHTML = '';
                if (!conversations || conversations.length === 0) { convoListEl.innerHTML = '<p style="text-align: center; color: #888; padding: 20px;">No conversations yet.</p>'; return; }
                conversations.forEach(convo => {
                    const lastMessageText = convo.last_message_image ? '📷 Image' : (convo.last_message_text || 'No messages yet');
                    const item = document.createElement('div'); item.className = 'conversation-item';
                    item.dataset.id = convo.conversation_id; item.dataset.name = convo.conversation_name; item.dataset.avatar = convo.conversation_avatar || 'default_avatar.png';
                    if (convo.conversation_id == activeConversationId) item.classList.add('active');
                    item.innerHTML = `<img src="${convo.conversation_avatar || 'default_avatar.png'}" alt="Avatar" class="convo-avatar"><div class="convo-details"><p class="name">${convo.conversation_name}</p><p class="last-message">${convo.last_message_sender ? convo.last_message_sender.split(' ')[0] + ': ' : ''}${lastMessageText}</p></div><div class="convo-meta">${convo.unread_count > 0 ? `<span class="unread-badge">${convo.unread_count}</span>` : ''}</div>`;
                    convoListEl.appendChild(item);
                });
            };
            const renderMessages = (messages) => {
                messagesContainerEl.innerHTML = '';
                messages.forEach(msg => {
                    const isSent = msg.sender_id == currentUserId;
                    const bubble = document.createElement('div'); bubble.className = `message-bubble ${isSent ? 'sent' : 'received'}`; bubble.dataset.messageId = msg.id;
                    const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const deleteButtonHtml = isSent ? `<div class="message-actions"><button class="delete-btn" title="Delete Message">🗑️</button></div>` : '';
                    bubble.innerHTML = `${deleteButtonHtml} ${!isSent && msg.sender_name ? `<div class="sender-name">${msg.sender_name}</div>` : ''} <div class="message-content"> ${msg.image_url ? `<img src="${msg.image_url}" alt="Image" class="message-image">` : ''} ${msg.message_text ? `<p>${msg.message_text.replace(/\n/g, '<br>')}</p>` : ''} </div> <span class="timestamp">${time}</span>`;
                    messagesContainerEl.appendChild(bubble);
                });
                scrollToBottom();
            };
            const openConversation = (convoItem) => {
                activeConversationId = convoItem.dataset.id;
                document.querySelectorAll('.conversation-item').forEach(el => el.classList.remove('active'));
                convoItem.classList.add('active');
                placeholder.style.display = 'none'; chatWindow.style.display = 'flex';
                chatName.textContent = convoItem.dataset.name; chatAvatar.src = convoItem.dataset.avatar; conversationIdInput.value = activeConversationId;
                fetchAndRenderMessages(activeConversationId);
                if (pollingInterval) clearInterval(pollingInterval);
                pollingInterval = setInterval(() => fetchAndRenderMessages(activeConversationId), 5000);
                chatContainer.classList.add('show-chat');
            };
            
            convoListEl.addEventListener('click', (e) => { const convoItem = e.target.closest('.conversation-item'); if (convoItem) openConversation(convoItem); });
            backBtn.addEventListener('click', () => { chatContainer.classList.remove('show-chat'); activeConversationId = null; document.querySelectorAll('.conversation-item').forEach(el => el.classList.remove('active')); if (pollingInterval) clearInterval(pollingInterval); });
            messageForm.addEventListener('submit', sendMessage);
            messageInput.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(new Event('submit')); } });
            messageInput.addEventListener('input', () => { messageInput.style.height = 'auto'; messageInput.style.height = `${messageInput.scrollHeight}px`; });
            
            messagesContainerEl.addEventListener('click', async (e) => {
                if (e.target.classList.contains('message-image')) {
                    fullScreenImage.src = e.target.src; imageViewerModal.style.display = 'flex';
                }
                if (e.target.classList.contains('delete-btn')) {
                    const messageBubble = e.target.closest('.message-bubble'); const messageId = messageBubble.dataset.messageId;
                    if (confirm('Are you sure you want to delete this message?')) {
                        const formData = new FormData(); formData.append('action', 'delete_message'); formData.append('message_id', messageId);
                        const data = await apiCall('delete_message', formData);
                        if (data.status === 'success') { fetchAndRenderMessages(activeConversationId); fetchAndRenderConversations(); } 
                        else { alert('Error: ' + data.message); }
                    }
                }
            });

            const closeImageViewer = () => { imageViewerModal.style.display = 'none'; };
            closeImageViewerBtn.onclick = closeImageViewer;
            imageViewerModal.onclick = (e) => { if (e.target === imageViewerModal) { closeImageViewer(); } };

            newChatBtn.onclick = async () => {
                const formData = new FormData(); formData.append('action', 'get_all_teachers');
                const data = await apiCall('get_all_teachers', formData);
                if (data.status === 'success') {
                    teacherListEl.innerHTML = '';
                    data.teachers.forEach(teacher => {
                        const item = document.createElement('div'); item.className = 'teacher-list-item'; item.dataset.id = teacher.id;
                        item.innerHTML = `<img src="${teacher.image_url || 'default_avatar.png'}" alt="${teacher.full_name}"> <span>${teacher.full_name}</span>`;
                        teacherListEl.appendChild(item);
                    });
                    modal.style.display = "block";
                }
            };
            teacherListEl.addEventListener('click', async (e) => {
                const teacherItem = e.target.closest('.teacher-list-item');
                if (teacherItem) {
                    const otherTeacherId = teacherItem.dataset.id; modal.style.display = "none";
                    const formData = new FormData(); formData.append('action', 'start_one_on_one'); formData.append('other_teacher_id', otherTeacherId);
                    const data = await apiCall('start_one_on_one', formData);
                    if (data.status === 'success') { await fetchAndRenderConversations(); const newConvoItem = document.querySelector(`.conversation-item[data-id='${data.conversation_id}']`); if(newConvoItem) openConversation(newConvoItem); } 
                    else { alert('Error starting chat: ' + data.message); }
                }
            });
            modalClose.onclick = () => { modal.style.display = "none"; };
            window.onclick = (event) => { if (event.target == modal) modal.style.display = "none"; };
            
            fetchAndRenderConversations();
            setInterval(fetchAndRenderConversations, 10000);
        });
    </script>
</body>
</html>

<?php require_once './teacher_footer.php';
?>
