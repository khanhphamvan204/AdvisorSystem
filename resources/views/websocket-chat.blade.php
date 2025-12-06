<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WebSocket Chat Test - Advisor System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/js/app.js'])
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .status-bar {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: #f3f4f6;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
        }

        .status-dot.connected {
            background: #10b981;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .content-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .login-panel,
        .chat-container,
        .log-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .panel-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-title i {
            color: #667eea;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .user-info {
            background: #f0fdf4;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .user-info p {
            font-size: 13px;
            color: #065f46;
            margin-bottom: 5px;
        }

        .user-info p strong {
            color: #047857;
        }

        .chat-messages {
            height: 400px;
            overflow-y: auto;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f9fafb;
        }

        .message {
            display: flex;
            margin-bottom: 15px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 15px;
            position: relative;
        }

        .message.received .message-bubble {
            background: #e5e7eb;
            color: #1f2937;
        }

        .message.sent .message-bubble {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .message.sending .message-bubble {
            opacity: 0.6;
            background: linear-gradient(135deg, #8b9dc3, #9d8bb8);
        }

        .message.sent-confirmed .message-bubble {
            animation: confirmSent 0.3s ease;
        }

        @keyframes confirmSent {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .message-sender {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
            opacity: 0.8;
        }

        .message-content {
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .message-time {
            font-size: 10px;
            margin-top: 4px;
            opacity: 0.7;
        }

        .typing-indicator {
            display: none;
            padding: 10px 15px;
            background: #e5e7eb;
            border-radius: 15px;
            width: fit-content;
            margin-bottom: 10px;
        }

        .typing-indicator.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            background: #6b7280;
            border-radius: 50%;
            animation: bounce 1.4s infinite;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes bounce {

            0%,
            60%,
            100% {
                transform: translateY(0);
            }

            30% {
                transform: translateY(-10px);
            }
        }

        .message-input-group {
            display: flex;
            gap: 10px;
        }

        .message-input-group textarea {
            flex: 1;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            resize: none;
            font-family: 'Inter', sans-serif;
        }

        .message-input-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .message-input-group button {
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }

        .message-input-group button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .log-panel {
            margin-top: 20px;
        }

        .log-content {
            height: 300px;
            overflow-y: auto;
            background: #1f2937;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #e5e7eb;
        }

        .log-entry {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .log-time {
            color: #9ca3af;
            margin-right: 8px;
        }

        .log-info {
            color: #60a5fa;
        }

        .log-success {
            color: #34d399;
        }

        .log-warning {
            color: #fbbf24;
        }

        .log-error {
            color: #f87171;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .controls {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 13px;
            flex: 1;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-comments"></i> WebSocket Chat Test</h1>
            <div class="status-bar">
                <div class="status-item">
                    <span class="status-dot" id="authStatus"></span>
                    <span id="authStatusText">Not Authenticated</span>
                </div>
                <div class="status-item">
                    <span class="status-dot" id="wsStatus"></span>
                    <span id="wsStatusText">WebSocket Disconnected</span>
                </div>
                <div class="status-item">
                    <i class="fas fa-paper-plane"></i>
                    <span>Messages: <strong id="msgCount">0</strong></span>
                </div>
                <div class="status-item">
                    <i class="fas fa-bolt"></i>
                    <span>Events: <strong id="eventCount">0</strong></span>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Login Panel -->
            <div class="login-panel">
                <div class="panel-title">
                    <i class="fas fa-sign-in-alt"></i>
                    Authentication
                </div>

                <div id="loginForm">
                    <div class="form-group">
                        <label>User Type</label>
                        <select id="userType">
                            <option value="student">Student</option>
                            <option value="advisor">Advisor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Mã Số (User Code)</label>
                        <input type="text" id="userCode" placeholder="Nhập mã số sinh viên/giảng viên"
                            value="2154050544">
                    </div>

                    <div class="form-group">
                        <label>Mật Khẩu (Password)</label>
                        <input type="password" id="password" placeholder="Nhập mật khẩu" value="password">
                    </div>

                    <button class="btn btn-primary" onclick="login()">
                        <i class="fas fa-sign-in-alt"></i>
                        Login & Connect
                    </button>
                </div>

                <div id="userInfo" style="display: none;">
                    <div class="user-info">
                        <p><strong>Họ tên:</strong> <span id="userName"></span></p>
                        <p><strong>Vai trò:</strong> <span id="userRole"></span></p>
                        <p><strong>Mã số:</strong> <span id="userId"></span></p>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label>ID Người Nhận (Partner ID)</label>
                        <input type="number" id="partnerId" placeholder="Nhập ID người nhận" value="1">
                        <small style="color: #6b7280; font-size: 11px; display: block; margin-top: 5px;">
                            Để test: Student chat với Advisor ID=1, Advisor chat với Student ID=1
                        </small>
                    </div>

                    <button class="btn btn-secondary" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i>
                        Đăng Xuất
                    </button>
                </div>

                <div style="margin-top: 20px;">
                    <div class="panel-title" style="margin-bottom: 10px;">
                        <i class="fas fa-user"></i>
                        Test Credentials
                    </div>
                    <div style="font-size: 12px; color: #6b7280; line-height: 1.6;">
                        <p><strong>Student:</strong></p>
                        <p>Mã số: 2154050544</p>
                        <p>Mật khẩu: password</p>
                        <br>
                        <p><strong>Advisor:</strong></p>
                        <p>Mã số: GV001</p>
                        <p>Mật khẩu: password</p>
                    </div>
                </div>
            </div>

            <!-- Chat Container -->
            <div class="chat-container">
                <div class="panel-title">
                    <i class="fas fa-comments"></i>
                    Chat Messages
                    <div style="margin-left: auto; display: flex; gap: 10px;">
                        <button class="btn btn-sm btn-secondary" onclick="loadMessages()" style="width: auto;">
                            <i class="fas fa-sync"></i> Reload
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="clearMessages()" style="width: auto;">
                            <i class="fas fa-trash"></i> Clear
                        </button>
                    </div>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>Login to start chatting</p>
                    </div>
                </div>

                <div class="typing-indicator" id="typingIndicator">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>

                <div class="message-input-group">
                    <textarea id="messageInput" rows="2" placeholder="Type a message... (Shift+Enter for new line)"
                        onkeydown="handleKeyPress(event)" oninput="handleTyping()" disabled></textarea>
                    <button onclick="sendMessage()" id="sendBtn" disabled>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>

                <div class="controls">
                    <button class="btn btn-sm btn-secondary" onclick="testBroadcastAuth()">
                        <i class="fas fa-key"></i> Test Auth Endpoint
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="testBroadcast()">
                        <i class="fas fa-broadcast-tower"></i> Test Broadcast
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="getConversations()">
                        <i class="fas fa-list"></i> Get Conversations
                    </button>
                </div>
            </div>
        </div>

        <!-- Log Panel -->
        <div class="log-panel">
            <div class="panel-title">
                <i class="fas fa-terminal"></i>
                Event Logs
                <button class="btn btn-sm btn-secondary" onclick="clearLogs()" style="margin-left: auto; width: auto;">
                    <i class="fas fa-eraser"></i> Clear Logs
                </button>
            </div>
            <div class="log-content" id="logContent"></div>
        </div>
    </div>

    <script>
        let currentUser = null;
        let currentToken = null;
        let messageCount = 0;
        let eventCount = 0;
        let typingTimeout = null;
        let typingDebounceTimeout = null;
        let lastTypingStatus = false;

        // Add log message
        function addLog(message, type = 'info') {
            const logContent = document.getElementById('logContent');
            const time = new Date().toLocaleTimeString('vi-VN');
            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry';
            logEntry.innerHTML = `<span class="log-time">[${time}]</span><span class="log-${type}">${message}</span>`;
            logContent.appendChild(logEntry);
            logContent.scrollTop = logContent.scrollHeight;
        }

        // Update status
        function updateStatus(element, connected, text = null) {
            const dot = document.getElementById(element);
            const textEl = document.getElementById(element + 'Text');

            if (connected) {
                dot.classList.add('connected');
                if (textEl && text) textEl.textContent = text;
            } else {
                dot.classList.remove('connected');
                if (textEl && text) textEl.textContent = text;
            }
        }

        // Login function
        async function login() {
            const userType = document.getElementById('userType').value;
            const userCode = document.getElementById('userCode').value;
            const password = document.getElementById('password').value;

            if (!userCode || !password) {
                addLog('⚠️ Vui lòng nhập mã số và mật khẩu', 'warning');
                return;
            }

            addLog('🔄 Đang đăng nhập...', 'info');

            try {
                const response = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        user_code: userCode,
                        password: password,
                        role: userType
                    })
                });

                const data = await response.json();

                if (data.success && data.data.token) {
                    currentToken = data.data.token;
                    currentUser = {
                        id: data.data.user.id,
                        name: data.data.user.full_name,
                        role: data.data.user.role,
                        user_code: data.data.user.user_code,
                        type: userType
                    };

                    // Update UI
                    document.getElementById('loginForm').style.display = 'none';
                    document.getElementById('userInfo').style.display = 'block';
                    document.getElementById('userName').textContent = currentUser.name;
                    document.getElementById('userRole').textContent = currentUser.role;
                    document.getElementById('userId').textContent = currentUser.user_code;
                    document.getElementById('messageInput').disabled = false;
                    document.getElementById('sendBtn').disabled = false;

                    updateStatus('authStatus', true, 'Authenticated');
                    addLog('✅ Đăng nhập thành công!', 'success');
                    addLog(`👤 Đăng nhập với: ${currentUser.name} (${currentUser.role})`, 'success');
                    addLog(`📝 Mã số: ${currentUser.user_code}`, 'info');

                    // Connect WebSocket
                    connectWebSocket();
                } else {
                    addLog('❌ Đăng nhập thất bại: ' + (data.message || 'Lỗi không xác định'), 'error');
                }
            } catch (error) {
                addLog('❌ Lỗi đăng nhập: ' + error.message, 'error');
            }
        }

        // Logout function
        function logout() {
            if (window.Echo && currentUser) {
                const channelName = `chat.${currentUser.role}.${currentUser.id}`;
                window.Echo.leave(channelName);
                addLog(`👋 Left channel: ${channelName}`, 'info');
            }

            currentUser = null;
            currentToken = null;
            messageCount = 0;
            eventCount = 0;

            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('userInfo').style.display = 'none';
            document.getElementById('messageInput').disabled = true;
            document.getElementById('sendBtn').disabled = true;
            document.getElementById('chatMessages').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p>Login to start chatting</p>
                </div>
            `;

            updateStatus('authStatus', false, 'Not Authenticated');
            updateStatus('wsStatus', false, 'WebSocket Disconnected');
            document.getElementById('msgCount').textContent = '0';
            document.getElementById('eventCount').textContent = '0';

            addLog('👋 Logged out', 'info');
        }

        // Connect WebSocket
        function connectWebSocket() {
            if (!currentUser || !currentToken) {
                addLog('⚠️ Vui lòng đăng nhập trước', 'warning');
                return;
            }

            if (!window.Echo) {
                addLog('❌ Echo chưa được khởi tạo. Chạy: npm run dev', 'error');
                return;
            }

            // CRITICAL: Set JWT token in axios headers BEFORE subscribing
            // Echo's auth.headers getter will read from axios defaults
            if (window.axios) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                window.axios.defaults.headers.common['Authorization'] = `Bearer ${currentToken}`;
                window.axios.defaults.headers.common['Accept'] = 'application/json';
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
                window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
                addLog('✅ JWT token đã được cấu hình trong axios', 'success');
                addLog(`🔑 Token: ${currentToken.substring(0, 30)}...`, 'info');
            } else {
                addLog('❌ Axios không khả dụng!', 'error');
                return;
            }

            const channelName = `chat.${currentUser.role}.${currentUser.id}`;
            addLog(`🔄 Đang subscribe channel: ${channelName}`, 'info');
            addLog(`👤 User ID: ${currentUser.id}, Role: ${currentUser.role}`, 'info');

            window.Echo.private(channelName)
                .listen('.message.sent', (e) => {
                    addLog('📨 Message Sent Event Received!', 'success');
                    addLog(`📥 From: ${e.sender.name} (${e.sender.type})`, 'info');
                    addLog(`💬 Content: ${e.message.content}`, 'info');
                    eventCount++;
                    document.getElementById('eventCount').textContent = eventCount;

                    // Add message to UI if it's from a different user type or different ID
                    const isDifferentUser = e.sender.type !== currentUser.role || e.sender.id !== currentUser.id;
                    addLog(`🔍 Different user? ${isDifferentUser} (sender: ${e.sender.type}/${e.sender.id}, current: ${currentUser.role}/${currentUser.id})`, 'info');

                    if (isDifferentUser) {
                        const messageTime = e.message.created_at ? new Date(e.message.created_at) : new Date();
                        addMessageToUI(e.message.content, 'received', e.sender.name, messageTime);
                        addLog('✅ Message added to UI', 'success');
                    } else {
                        addLog('⚠️ Message from self, not displaying', 'warning');
                    }
                })
                .listen('.message.read', (e) => {
                    addLog('✅ Message Read Event Received', 'success');
                    eventCount++;
                    document.getElementById('eventCount').textContent = eventCount;
                })
                .listen('.user.typing', (e) => {
                    addLog(`⌨️ User Typing: ${e.sender_name}`, 'info');
                    eventCount++;
                    document.getElementById('eventCount').textContent = eventCount;

                    const indicator = document.getElementById('typingIndicator');
                    if (e.is_typing) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                })
                .subscribed(() => {
                    addLog(`✅ Successfully subscribed to ${channelName}`, 'success');
                    updateStatus('wsStatus', true, 'WebSocket Connected');
                })
                .error((error) => {
                    addLog(`❌ Subscription error: ${JSON.stringify(error)}`, 'error');
                    updateStatus('wsStatus', false, 'WebSocket Error');
                });
        }

        // Send message
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const content = input.value.trim();

            if (!content || !currentUser || !currentToken) return;

            // Get partner ID from input
            const partnerIdInput = document.getElementById('partnerId');
            const partnerId = partnerIdInput ? parseInt(partnerIdInput.value) : 1;

            if (!partnerId || isNaN(partnerId)) {
                addLog('⚠️ Vui lòng nhập Partner ID hợp lệ', 'warning');
                return;
            }

            // Clear input immediately
            input.value = '';
            input.style.height = 'auto';

            // Stop typing indicator immediately
            if (typingTimeout) {
                clearTimeout(typingTimeout);
                typingTimeout = null;
            }
            if (typingDebounceTimeout) {
                clearTimeout(typingDebounceTimeout);
                typingDebounceTimeout = null;
            }
            sendTypingStatusImmediate(false);

            // Show message immediately (optimistic UI)
            const tempId = 'temp-' + Date.now();
            addMessageToUI(content, 'sent', currentUser.name, new Date(), tempId, true);

            try {
                const response = await fetch('/api/messages/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${currentToken}`,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        user_type: currentUser.role,
                        user_id: currentUser.id,
                        partner_id: partnerId,
                        content: content
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Update temp message to confirmed
                    const tempMsg = document.getElementById(tempId);
                    if (tempMsg) {
                        tempMsg.classList.remove('sending');
                        tempMsg.classList.add('sent-confirmed');
                    }
                    messageCount++;
                    document.getElementById('msgCount').textContent = messageCount;
                } else {
                    // Remove temp message and show error
                    const tempMsg = document.getElementById(tempId);
                    if (tempMsg) tempMsg.remove();
                    addLog('❌ Gửi tin nhắn thất bại: ' + (data.message || 'Lỗi không xác định'), 'error');
                    input.value = content;
                }
            } catch (error) {
                // Remove temp message and show error
                const tempMsg = document.getElementById(tempId);
                if (tempMsg) tempMsg.remove();
                addLog('❌ Lỗi kết nối: ' + error.message, 'error');
                input.value = content;
            }
        }

        // Add message to UI
        function addMessageToUI(content, type, sender, time, messageId = null, isSending = false) {
            const chatMessages = document.getElementById('chatMessages');

            // Remove empty state
            const emptyState = chatMessages.querySelector('.empty-state');
            if (emptyState) emptyState.remove();

            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            if (isSending) messageDiv.classList.add('sending');
            if (messageId) messageDiv.id = messageId;

            const timeStr = time.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            const statusIcon = isSending ? '<i class="fas fa-clock" style="margin-left: 5px; opacity: 0.6;"></i>' : '';

            messageDiv.innerHTML = `
                <div class="message-bubble">
                    <div class="message-sender">${sender}</div>
                    <div class="message-content">${escapeHtml(content)}</div>
                    <div class="message-time">${timeStr} ${statusIcon}</div>
                </div>
            `;

            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Handle key press
        function handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }

        // Handle typing with debounce
        function handleTyping() {
            if (!currentUser || !currentToken) return;

            // Clear previous timeouts
            if (typingTimeout) clearTimeout(typingTimeout);
            if (typingDebounceTimeout) clearTimeout(typingDebounceTimeout);

            // Only send typing=true if not already typing (reduce API calls)
            if (!lastTypingStatus) {
                typingDebounceTimeout = setTimeout(() => {
                    sendTypingStatusImmediate(true);
                    lastTypingStatus = true;
                }, 500); // Debounce 500ms
            }

            // Auto stop typing after 3 seconds of no input
            typingTimeout = setTimeout(() => {
                sendTypingStatusImmediate(false);
                lastTypingStatus = false;
            }, 3000);
        }

        // Send typing status immediately (no debounce)
        async function sendTypingStatusImmediate(isTyping) {
            if (!currentUser || !currentToken) return;

            const partnerIdInput = document.getElementById('partnerId');
            const partnerId = partnerIdInput ? parseInt(partnerIdInput.value) : 1;

            try {
                await fetch('/api/messages/typing', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${currentToken}`,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        user_type: currentUser.role,
                        user_id: currentUser.id,
                        partner_id: partnerId,
                        is_typing: isTyping
                    })
                });
            } catch (error) {
                console.error('Typing status error:', error);
            }
        }

        // Clear messages
        function clearMessages() {
            document.getElementById('chatMessages').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p>No messages</p>
                </div>
            `;
            messageCount = 0;
            document.getElementById('msgCount').textContent = '0';
            addLog('🧹 Messages cleared', 'info');
        }

        // Clear logs
        function clearLogs() {
            document.getElementById('logContent').innerHTML = '';
            addLog('🧹 Logs cleared', 'info');
        }

        // Test broadcast auth endpoint
        async function testBroadcastAuth() {
            if (!currentUser || !currentToken) {
                addLog('⚠️ Vui lòng đăng nhập trước', 'warning');
                return;
            }

            addLog('🔍 Testing broadcasting auth endpoint...', 'info');
            try {
                const channelName = `chat.${currentUser.role}.${currentUser.id}`;
                const socketId = '123.456'; // Dummy socket ID for testing

                const response = await fetch('/api/broadcasting/auth', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${currentToken}`,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: `private-${channelName}`
                    })
                });

                const data = await response.text();

                if (response.ok) {
                    addLog('✅ Broadcasting auth test SUCCESS!', 'success');
                    addLog(`Response: ${data}`, 'success');
                } else {
                    addLog(`❌ Broadcasting auth test FAILED! Status: ${response.status}`, 'error');
                    addLog(`Response: ${data}`, 'error');
                }
            } catch (error) {
                addLog('❌ Auth test error: ' + error.message, 'error');
            }
        }

        // Test broadcast
        async function testBroadcast() {
            addLog('🚀 Triggering test broadcast...', 'info');
            try {
                const response = await fetch('/test-broadcast');
                const data = await response.json();
                addLog('✅ Broadcast triggered: ' + JSON.stringify(data), 'success');
            } catch (error) {
                addLog('❌ Broadcast error: ' + error.message, 'error');
            }
        }

        // Get conversations
        async function getConversations() {
            if (!currentUser || !currentToken) {
                addLog('⚠️ Please login first', 'warning');
                return;
            }

            addLog('🔄 Fetching conversations...', 'info');
            try {
                const response = await fetch(`/api/messages/conversations?user_type=${currentUser.role}&user_id=${currentUser.id}`, {
                    headers: {
                        'Authorization': `Bearer ${currentToken}`,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();
                addLog('✅ Conversations: ' + JSON.stringify(data), 'success');
            } catch (error) {
                addLog('❌ Error: ' + error.message, 'error');
            }
        }

        // Load messages
        function loadMessages() {
            addLog('🔄 Reload messages feature - implement as needed', 'info');
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-resize textarea
        document.getElementById('messageInput').addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Initialize
        window.addEventListener('load', () => {
            addLog('🎉 WebSocket Chat Test Loaded', 'success');
            addLog(`📡 Server: ${window.location.origin}`, 'info');

            if (window.Echo) {
                addLog('✅ Echo is available', 'success');
            } else {
                addLog('❌ Echo not available. Run: npm run dev', 'error');
            }

            addLog('👉 Login to start testing', 'info');
        });
    </script>
</body>

</html>