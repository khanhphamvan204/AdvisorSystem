# Hướng Dẫn Cài Đặt và Chạy WebSocket cho Advisor System

## 📋 Mục Lục

1. [Tổng Quan Hệ Thống](#tổng-quan-hệ-thống)
2. [Yêu Cầu Hệ Thống](#yêu-cầu-hệ-thống)
3. [Cài Đặt Thư Viện](#cài-đặt-thư-viện)
4. [Cấu Hình Backend](#cấu-hình-backend)
5. [Cấu Hình Frontend](#cấu-hình-frontend)
6. [Chạy WebSocket Server](#chạy-websocket-server)
7. [Kiểm Tra Hoạt Động](#kiểm-tra-hoạt-động)
8. [Luồng Hoạt Động](#luồng-hoạt-động)
9. [API Endpoints](#api-endpoints)
10. [Sử Dụng trong Frontend](#sử-dụng-trong-frontend)
11. [Xử Lý Lỗi Thường Gặp](#xử-lý-lỗi-thường-gặp)

---

## 🎯 Tổng Quan Hệ Thống

### Kiến Trúc WebSocket

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│  Frontend   │ ←──────→ │ Laravel Echo │ ←──────→ │   Reverb    │
│  (Browser)  │  HTTP/WS │  (Client)    │  WebSocket│  Server     │
└─────────────┘         └──────────────┘         └─────────────┘
      ↓                                                   ↓
      │                                                   │
      └─────────────→  JWT Token Auth  ←──────────────┘
                            ↓
                    ┌──────────────┐
                    │   Laravel    │
                    │   Backend    │
                    └──────────────┘
```

### Cách Hoạt Động

**Backend (Laravel + Reverb):**

1. **Laravel Reverb Server**: WebSocket server chạy trên port 8080
2. **Broadcasting Routes**: `/api/broadcasting/auth` xác thực JWT token
3. **Private Channels**: Kiểm soát quyền truy cập qua `routes/channels.php`
4. **Events**: Broadcast events khi có message mới, đã đọc, typing...

**Frontend (JavaScript + Echo):**

1. **Laravel Echo**: Client library kết nối với Reverb
2. **JWT Authentication**: Gửi token trong header để xác thực
3. **Subscribe Channels**: Lắng nghe events real-time
4. **Auto-reconnect**: Tự động kết nối lại khi mất kết nối

---

## 🖥️ Yêu Cầu Hệ Thống

-   PHP >= 8.1
-   Composer
-   Node.js >= 18.x
-   NPM hoặc Yarn
-   Laravel 11.x

---

## 📦 Cài Đặt Thư Viện

### 1. Cài đặt Laravel Reverb (Backend)

```bash
composer require laravel/reverb
```

### 2. Publish cấu hình Reverb

```bash
php artisan reverb:install
```

Lệnh này sẽ:

-   Tạo file config `config/reverb.php`
-   Thêm các biến môi trường vào `.env`
-   Cài đặt dependencies cần thiết

### 3. Cài đặt Frontend Dependencies

```bash
npm install
```

Package.json đã bao gồm:

-   `laravel-echo`: ^2.2.6
-   `pusher-js`: ^8.4.0

---

## ⚙️ Cấu Hình Backend

### 1. Cấu hình File `.env`

```env
# Broadcasting Connection
BROADCAST_CONNECTION=reverb

# Reverb Server Configuration
REVERB_APP_ID=advisor-system
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Vite Environment Variables (cho frontend)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Production:** Thay đổi KEY và SECRET bằng giá trị ngẫu nhiên an toàn.

### 2. Cấu hình Broadcasting Authentication

File `routes/api.php` - Endpoint xác thực channel:

```php
// Broadcasting Authentication
Route::post('/broadcasting/auth', function (Illuminate\Http\Request $request) {
    // Lấy JWT payload từ middleware auth.api
    $userRole = $request->input('current_role');  // 'student' hoặc 'advisor'
    $userId = $request->input('current_user_id');

    // Tạo user object cho broadcasting
    $user = new stdClass();
    $user->id = $userId;
    $user->role = $userRole;

    // Set user resolver
    $request->setUserResolver(function () use ($user) {
        return $user;
    });

    return Illuminate\Support\Facades\Broadcast::auth($request);
})->middleware('auth.api');
```

**Giải thích:**

-   Middleware `auth.api` xác thực JWT token và gán `current_role`, `current_user_id` vào request
-   Tạo user object với `id` và `role` để broadcasting có thể xác thực
-   Trả về kết quả authorization cho channel

### 3. Cấu hình Private Channels

File `routes/channels.php`:

```php
use App\Models\Student;
use App\Models\Advisor;

// Channel cho student
Broadcast::channel('chat.student.{studentId}', function ($user, $studentId) {
    if (!$user) return false;

    $role = $user->role ?? null;
    $userId = $user->id ?? null;

    // Student có thể subscribe channel của chính mình
    if ($role === 'student' && $userId == $studentId) {
        return ['id' => $userId, 'role' => $role];
    }

    // Advisor có thể subscribe channel của student trong lớp mình
    if ($role === 'advisor') {
        $student = Student::with('class')->find($studentId);
        if ($student && $student->class && $student->class->advisor_id == $userId) {
            return ['id' => $userId, 'role' => $role];
        }
    }

    return false;
});

// Channel cho advisor
Broadcast::channel('chat.advisor.{advisorId}', function ($user, $advisorId) {
    if (!$user) return false;

    $role = $user->role ?? null;
    $userId = $user->id ?? null;

    // Advisor có thể subscribe channel của chính mình
    if ($role === 'advisor' && $userId == $advisorId) {
        return ['id' => $userId, 'role' => $role];
    }

    // Student có thể subscribe channel của advisor lớp mình
    if ($role === 'student') {
        $student = Student::with('class')->find($userId);
        if ($student && $student->class && $student->class->advisor_id == $advisorId) {
            return ['id' => $userId, 'role' => $role];
        }
    }

    return false;
});
```

**Quy tắc authorization:**

-   Mỗi user chỉ có thể subscribe channel của chính mình
-   Student có thể subscribe channel advisor của lớp mình
-   Advisor có thể subscribe channel của student trong lớp mình phụ trách

### 4. Cấu hình `bootstrap/app.php`

Đảm bảo load `routes/channels.php`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php', // ← Quan trọng!
        health: '/up',
    )
```

### 5. Broadcasting Events

File `app/Events/MessageSent.php`:

```php
class MessageSent implements ShouldBroadcast
{
    public $message;
    public $senderInfo;

    public function __construct(Message $message, array $senderInfo)
    {
        $this->message = $message;
        $this->senderInfo = $senderInfo;
    }

    // Broadcast đến 2 channels: student và advisor
    public function broadcastOn()
    {
        return [
            new PrivateChannel('chat.student.' . $this->message->student_id),
            new PrivateChannel('chat.advisor.' . $this->message->advisor_id),
        ];
    }

    public function broadcastAs()
    {
        return 'message.sent'; // Tên event
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'sender' => $this->senderInfo
        ];
    }
}
```

### 6. Trigger Broadcasting trong Controller

File `app/Http/Controllers/DialogController.php`:

```php
public function sendMessage(Request $request)
{
    // ... validate và tạo message

    $message = Message::create([
        'student_id' => $studentId,
        'advisor_id' => $advisorId,
        'sender_type' => $senderType,
        'content' => $request->input('content'),
        'is_read' => false
    ]);

    // Broadcast event
    broadcast(new MessageSent($message, $senderInfo))->toOthers();

    return response()->json([
        'success' => true,
        'data' => $message
    ]);
}
```

---

## 🎨 Cấu Hình Frontend

### 1. Cấu hình Echo với JWT

File `resources/js/echo.js`:

```javascript
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: "/api/broadcasting/auth", // ← Backend auth endpoint
    auth: {
        headers: {
            Accept: "application/json",
            get Authorization() {
                // Lấy JWT token động từ axios
                return (
                    window.axios.defaults.headers.common["Authorization"] || ""
                );
            },
        },
    },
});
```

**Quan trọng:**

-   `authEndpoint`: Phải trỏ đến `/api/broadcasting/auth`
-   `Authorization` header: Sử dụng getter để lấy token động
-   Token được set qua `axios.defaults.headers.common['Authorization']`

### 2. Build Frontend Assets

```bash
npm run build
```

Hoặc development mode:

```bash
npm run dev
```

---

## 🚀 Chạy WebSocket Server

### Cách 1: Chạy Đơn Giản

Mở terminal và chạy:

```bash
php artisan reverb:start
```

Output sẽ hiển thị:

```
  INFO  Starting Reverb server on localhost:8080

  ┌────────────────────────────────────────────────┐
  │ Reverb Server Running                          │
  │ Local: http://localhost:8080                   │
  └────────────────────────────────────────────────┘
```

### Cách 2: Chạy với Debug Mode

```bash
php artisan reverb:start --debug
```

### Cách 3: Chạy trên Host/Port Khác

```bash
php artisan reverb:start --host=0.0.0.0 --port=9000
```

### Cách 4: Chạy Background (Production)

Sử dụng với Supervisor hoặc systemd:

```bash
php artisan reverb:start > storage/logs/reverb.log 2>&1 &
```

---

## ✅ Kiểm Tra Hoạt Động

### 1. Kiểm tra Reverb Server đang chạy

Truy cập: `http://localhost:8080`

Hoặc sử dụng curl:

```bash
curl http://localhost:8080
```

### 2. Kiểm tra Broadcasting trong Laravel

Tạo file test `routes/web.php`:

```php
use App\Events\MessageSent;
use App\Models\Message;

Route::get('/test-broadcast', function () {
    $message = Message::first();
    $senderInfo = [
        'id' => 1,
        'name' => 'Test User',
        'avatar' => null,
        'type' => 'student'
    ];

    broadcast(new MessageSent($message, $senderInfo));

    return 'Event broadcasted!';
});
```

Truy cập: `http://localhost:8000/test-broadcast`

### 3. Kiểm tra Console Browser

Mở browser console (F12) và kiểm tra xem Echo có kết nối thành công không.

---

## 🌐 Sử Dụng trong Frontend

### 1. Import Echo (đã cấu hình trong `resources/js/bootstrap.js`)

```javascript
import "./echo";
```

### 2. Lắng nghe Event trong JavaScript/Vue/React

#### Ví dụ với Vanilla JavaScript:

```javascript
// Subscribe to student chat channel
window.Echo.private(`chat.student.${studentId}`)
    .listen(".message.sent", (e) => {
        console.log("New message received:", e.message);
        console.log("Sender info:", e.sender);

        // Cập nhật UI với tin nhắn mới
        appendMessageToChat(e.message, e.sender);
    })
    .listen(".message.read", (e) => {
        console.log("Message read:", e.message);

        // Cập nhật trạng thái đã đọc trong UI
        markMessageAsRead(e.message.message_id);
    });

// Listen for typing indicator
window.Echo.private(`chat.student.${studentId}`).listen(".user.typing", (e) => {
    if (e.is_typing) {
        showTypingIndicator(e.sender_name);
    } else {
        hideTypingIndicator();
    }
});
```

#### Ví dụ với Vue 3:

```vue
<script setup>
import { onMounted, onUnmounted } from "vue";
import axios from "axios";

const studentId = 123; // Get from auth

onMounted(() => {
    // Subscribe to chat channel
    window.Echo.private(`chat.student.${studentId}`)
        .listen(".message.sent", (e) => {
            messages.value.push(e.message);
            scrollToBottom();
        })
        .listen(".message.read", (e) => {
            updateMessageStatus(e.message.message_id, true);
        });
});

onUnmounted(() => {
    // Unsubscribe when component is destroyed
    window.Echo.leave(`chat.student.${studentId}`);
});

// Send message
const sendMessage = async () => {
    try {
        const response = await axios.post(
            "/api/messages/send",
            {
                partner_id: advisorId,
                content: messageContent.value,
            },
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                },
            }
        );

        // Message sẽ được broadcast tự động
        messageContent.value = "";
    } catch (error) {
        console.error("Error sending message:", error);
    }
};

// Send typing indicator
const onTyping = () => {
    axios.post(
        "/api/messages/typing",
        {
            partner_id: advisorId,
            is_typing: true,
        },
        {
            headers: {
                Authorization: `Bearer ${token}`,
            },
        }
    );
};
</script>
```

### 3. Xác Thực với Private Channel

Khi subscribe vào private channel, Laravel Echo tự động gửi request đến `/broadcasting/auth` để xác thực.

Đảm bảo bạn gửi JWT token trong header:

```javascript
window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ["ws", "wss"],
    auth: {
        headers: {
            Authorization: `Bearer ${yourJWTToken}`,
        },
    },
});
```

---

## 🔧 API Endpoints

### 1. Gửi Tin Nhắn

```http
POST /api/messages/send
Authorization: Bearer {token}
Content-Type: application/json

{
    "partner_id": 123,
    "content": "Hello, this is a test message",
    "attachment": null
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "message_id": 1,
        "student_id": 456,
        "advisor_id": 123,
        "sender_type": "student",
        "content": "Hello, this is a test message",
        "is_read": false,
        "sent_at": "2025-12-06T10:30:00.000000Z",
        "sender": {
            "id": 456,
            "name": "Nguyen Van A",
            "avatar": "https://...",
            "type": "student"
        }
    },
    "message": "Gửi tin nhắn thành công"
}
```

### 2. Lấy Danh Sách Tin Nhắn

```http
GET /api/messages?partner_id=123
Authorization: Bearer {token}
```

### 3. Gửi Trạng Thái Typing

```http
POST /api/messages/typing
Authorization: Bearer {token}
Content-Type: application/json

{
    "partner_id": 123,
    "is_typing": true
}
```

### 4. Đánh Dấu Đã Đọc

```http
PUT /api/messages/{messageId}/read
Authorization: Bearer {token}
```

---

## 🐛 Xử Lý Lỗi Thường Gặp

### 1. Lỗi: "Connection refused" khi kết nối WebSocket

**Nguyên nhân:** Reverb server chưa chạy

**Giải pháp:**

```bash
php artisan reverb:start
```

### 2. Lỗi: "Unauthenticated" khi subscribe channel

**Nguyên nhân:** JWT token không được gửi hoặc không hợp lệ

**Giải pháp:**

-   Đảm bảo gửi JWT token trong header
-   Kiểm tra token còn hiệu lực
-   Cập nhật Echo config với auth headers

```javascript
window.Echo = new Echo({
    // ... other config
    auth: {
        headers: {
            Authorization: `Bearer ${token}`,
        },
    },
});
```

### 3. Lỗi: "Channel not found" hoặc "Forbidden"

**Nguyên nhân:** Authorization trong `routes/channels.php` trả về false

**Giải pháp:**

-   Kiểm tra logic authorization trong `channels.php`
-   Đảm bảo user có quyền truy cập channel
-   Debug bằng cách thêm `Log::info()` trong channel callback

### 4. Lỗi: Events không được broadcast

**Nguyên nhân:** Event class chưa implement `ShouldBroadcast`

**Giải pháp:**

```php
class MessageSent implements ShouldBroadcast
{
    // ...
}
```

### 5. Lỗi: "REVERB_APP_KEY is undefined"

**Nguyên nhân:** Frontend chưa được build lại sau khi cập nhật .env

**Giải pháp:**

```bash
npm run build
# hoặc
npm run dev
```

### 6. Reverb Server bị crash

**Kiểm tra logs:**

```bash
tail -f storage/logs/laravel.log
```

**Khởi động lại:**

```bash
php artisan reverb:restart
```

---

## 📊 Monitoring và Production

### 1. Chạy Reverb với Supervisor (Linux)

Tạo file `/etc/supervisor/conf.d/reverb.conf`:

```ini
[program:reverb]
command=php /path/to/your/project/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/reverb.log
```

Restart supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
```

### 2. Sử dụng SSL trong Production

Cập nhật `.env`:

```env
REVERB_SCHEME=https
REVERB_PORT=443
REVERB_HOST=your-domain.com
```

### 3. Scaling với Multiple Servers

Reverb hỗ trợ horizontal scaling. Tham khảo [Laravel Reverb Documentation](https://laravel.com/docs/11.x/reverb#scaling).

---

## 📚 Tài Liệu Tham Khảo

-   [Laravel Broadcasting Documentation](https://laravel.com/docs/11.x/broadcasting)
-   [Laravel Reverb Documentation](https://laravel.com/docs/11.x/reverb)
-   [Laravel Echo Documentation](https://laravel.com/docs/11.x/broadcasting#client-side-installation)
-   [Pusher Protocol](https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol)

---

## 🎯 Quick Start Commands

```bash
# 1. Cài đặt dependencies
composer install
npm install

# 2. Cài đặt Reverb
composer require laravel/reverb
php artisan reverb:install

# 3. Build frontend
npm run build

# 4. Chạy servers
# Terminal 1: Laravel application
php artisan serve

# Terminal 2: Reverb WebSocket server
php artisan reverb:start

# Terminal 3 (Optional): Queue worker nếu dùng queue
php artisan queue:work
```

---

## ✨ Demo Usage Example

### Complete Chat Component Example

```vue
<template>
    <div class="chat-container">
        <div class="messages" ref="messagesContainer">
            <div
                v-for="msg in messages"
                :key="msg.message_id"
                :class="[
                    'message',
                    msg.sender_type === currentRole ? 'sent' : 'received',
                ]"
            >
                <div class="message-content">{{ msg.content }}</div>
                <div class="message-time">{{ formatTime(msg.sent_at) }}</div>
                <div v-if="msg.is_read" class="read-status">✓✓</div>
            </div>

            <div v-if="isPartnerTyping" class="typing-indicator">
                {{ partnerName }} đang nhập...
            </div>
        </div>

        <div class="input-area">
            <input
                v-model="newMessage"
                @input="handleTyping"
                @keyup.enter="sendMessage"
                placeholder="Nhập tin nhắn..."
            />
            <button @click="sendMessage">Gửi</button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";
import axios from "axios";

const props = defineProps({
    partnerId: Number,
    currentUserId: Number,
    currentRole: String, // 'student' or 'advisor'
    token: String,
});

const messages = ref([]);
const newMessage = ref("");
const isPartnerTyping = ref(false);
const partnerName = ref("");
let typingTimeout = null;

// Configure axios
axios.defaults.headers.common["Authorization"] = `Bearer ${props.token}`;

// Scroll to bottom
const scrollToBottom = () => {
    nextTick(() => {
        const container = messagesContainer.value;
        container.scrollTop = container.scrollHeight;
    });
};

// Fetch messages
const fetchMessages = async () => {
    try {
        const response = await axios.get(
            `/api/messages?partner_id=${props.partnerId}`
        );
        messages.value = response.data.data;
        scrollToBottom();
    } catch (error) {
        console.error("Error fetching messages:", error);
    }
};

// Send message
const sendMessage = async () => {
    if (!newMessage.value.trim()) return;

    try {
        await axios.post("/api/messages/send", {
            partner_id: props.partnerId,
            content: newMessage.value,
        });

        newMessage.value = "";

        // Stop typing indicator
        axios.post("/api/messages/typing", {
            partner_id: props.partnerId,
            is_typing: false,
        });
    } catch (error) {
        console.error("Error sending message:", error);
    }
};

// Handle typing
const handleTyping = () => {
    axios.post("/api/messages/typing", {
        partner_id: props.partnerId,
        is_typing: true,
    });

    // Clear previous timeout
    if (typingTimeout) clearTimeout(typingTimeout);

    // Set new timeout to stop typing
    typingTimeout = setTimeout(() => {
        axios.post("/api/messages/typing", {
            partner_id: props.partnerId,
            is_typing: false,
        });
    }, 3000);
};

// Setup WebSocket
onMounted(() => {
    fetchMessages();

    const channelName =
        props.currentRole === "student"
            ? `chat.student.${props.currentUserId}`
            : `chat.advisor.${props.currentUserId}`;

    window.Echo.private(channelName)
        .listen(".message.sent", (e) => {
            messages.value.push(e.message);
            scrollToBottom();
        })
        .listen(".message.read", (e) => {
            const msg = messages.value.find(
                (m) => m.message_id === e.message.message_id
            );
            if (msg) msg.is_read = true;
        })
        .listen(".user.typing", (e) => {
            if (e.sender_id !== props.currentUserId) {
                isPartnerTyping.value = e.is_typing;
                partnerName.value = e.sender_name;
            }
        });
});

onUnmounted(() => {
    const channelName =
        props.currentRole === "student"
            ? `chat.student.${props.currentUserId}`
            : `chat.advisor.${props.currentUserId}`;

    window.Echo.leave(channelName);
});

const formatTime = (time) => {
    return new Date(time).toLocaleTimeString("vi-VN", {
        hour: "2-digit",
        minute: "2-digit",
    });
};
</script>

<style scoped>
.chat-container {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.message {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
}

.message.sent {
    align-items: flex-end;
}

.message.received {
    align-items: flex-start;
}

.message-content {
    background: #e3f2fd;
    padding: 10px 15px;
    border-radius: 10px;
    max-width: 70%;
}

.message.sent .message-content {
    background: #1976d2;
    color: white;
}

.typing-indicator {
    font-style: italic;
    color: #666;
    padding: 10px;
}

.input-area {
    display: flex;
    padding: 15px;
    border-top: 1px solid #ddd;
}

.input-area input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    margin-right: 10px;
}

.input-area button {
    padding: 10px 20px;
    background: #1976d2;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
</style>
```

---

## 🎉 Hoàn Thành!

Bây giờ hệ thống WebSocket của bạn đã sẵn sàng để gửi và nhận tin nhắn real-time!

Để test:

1. Mở 2 browser/tabs khác nhau
2. Đăng nhập với student và advisor
3. Gửi tin nhắn từ một bên
4. Tin nhắn sẽ xuất hiện ngay lập tức ở bên kia

**Happy coding! 🚀**
