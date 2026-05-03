# ⚙️ Hướng Dẫn Kỹ Thuật — Advisor System

> Tài liệu mô tả các công nghệ và kỹ thuật cốt lõi được áp dụng trong hệ thống quản lý cố vấn học tập, bao gồm cơ sở dữ liệu, giao tiếp thời gian thực, thông báo đẩy, email, tích hợp API bên ngoài và containerization.

---

## 📋 Mục Lục

1. [Tổng Quan Kiến Trúc](#1-tổng-quan-kiến-trúc)
2. [Cơ Sở Dữ Liệu](#2-cơ-sở-dữ-liệu)
3. [WebSocket — Giao Tiếp Thời Gian Thực](#3-websocket--giao-tiếp-thời-gian-thực)
4. [Push Notification — Firebase FCM](#4-push-notification--firebase-fcm)
5. [Email Service](#5-email-service)
6. [Google Calendar & Google Meet](#6-google-calendar--google-meet)
7. [Queue & Background Jobs](#7-queue--background-jobs)
8. [Xác Thực — JWT Authentication](#8-xác-thực--jwt-authentication)
9. [Docker & Containerization](#9-docker--containerization)
10. [Tổng Hợp Packages](#10-tổng-hợp-packages)

---

## 1. Tổng Quan Kiến Trúc

```
┌──────────────────────────────────────────────────────────────────┐
│                        Client (Web/Mobile)                        │
│          REST API          │          WebSocket (WS)              │
└────────────┬───────────────┴──────────────┬───────────────────────┘
             │                              │
    ┌────────▼──────────────────────────────▼────────┐
    │                  Nginx (Port 8000)              │
    └────────┬──────────────────────────────┬─────────┘
             │  HTTP                         │  WebSocket
    ┌────────▼────────┐             ┌────────▼────────┐
    │  Laravel App    │             │ Laravel Reverb  │
    │  (PHP-FPM)      │             │ (WS Server)     │
    └────────┬────────┘             └─────────────────┘
             │
    ┌────────┼──────────────────────────────────┐
    │        │                                  │
    ▼        ▼                ▼                 ▼
 MySQL    MongoDB         Firebase FCM     Google APIs
(Chính)  (NoSQL)       (Push Notification) (Calendar/Meet)
             │
    ┌────────▼────────┐
    │  Queue Worker   │
    │ (Background Job)│
    └─────────────────┘
```

---

## 2. Cơ Sở Dữ Liệu

### 2.1 MySQL 8.0 — Cơ Sở Dữ Liệu Quan Hệ Chính

| Thuộc tính | Giá trị                          |
| ---------- | -------------------------------- |
| Image      | `mysql:8.0`                      |
| Port       | `3307:3306`                      |
| Engine     | InnoDB                           |
| Charset    | `utf8mb4` / `utf8mb4_unicode_ci` |
| Auth       | `caching_sha2_password`          |

Hệ thống có **25 bảng** được thiết kế theo mô hình quan hệ đầy đủ với Foreign Key Constraints, Composite Index và Unique Constraints để đảm bảo toàn vẹn dữ liệu.

**Kỹ thuật nổi bật:**

- **InnoDB ACID** — đảm bảo tính nhất quán khi thao tác ghi đồng thời.
- **Composite Index** — tối ưu truy vấn:
    ```sql
    INDEX idx_students_position (class_id, position)       -- Bảng Students
    KEY   idx_conversation (student_id, advisor_id, sent_at) -- Bảng Messages
    ```
- **Cascade Rules** — tự động xử lý quan hệ khi xóa:
    ```sql
    ON DELETE CASCADE   -- Xóa sinh viên → xóa toàn bộ dữ liệu liên quan
    ON DELETE SET NULL  -- Xóa đơn vị → giữ advisor, gán unit_id = NULL
    ON DELETE RESTRICT  -- Bảo vệ records cần giữ lịch sử
    ```

### 2.2 MongoDB 7 — NoSQL Phụ Trợ

```
Image:   mongo:7
Port:    27017:27017
Package: mongodb/laravel-mongodb
```

Sử dụng song song với MySQL cho dữ liệu **phi cấu trúc, linh hoạt schema**:

- Lịch sử hội thoại AI Chatbot
- Logs hệ thống với metadata tùy biến
- Dữ liệu thời gian thực không cần schema cứng

### 2.3 Eloquent ORM

Laravel Eloquent ánh xạ 25 model sang bảng MySQL với các kỹ thuật:

| Kỹ thuật          | Mục đích                                                            |
| ----------------- | ------------------------------------------------------------------- |
| `$fillable`       | Chống lỗ hổng Mass Assignment                                       |
| `$hidden`         | Ẩn `password_hash` khỏi JSON response                               |
| `$casts`          | Type-safe: `datetime`, `string` tự động chuyển đổi                  |
| `serializeDate()` | Serialize datetime theo timezone VN thay vì UTC                     |
| **Relations**     | `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`, `hasOne` |
| **Accessor**      | Tính toán `computed_status` real-time trên `Activity`               |
| **Model Events**  | Auto-gán `sent_at = now()` khi tạo `Message` mới                    |

---

## 3. WebSocket — Giao Tiếp Thời Gian Thực

### 3.1 Laravel Reverb

```
Package:  laravel/reverb ^1.6
Protocol: WebSocket (RFC 6455)
```

**Laravel Reverb** là WebSocket server tích hợp sẵn trong Laravel, thay thế Pusher/Ably. Cho phép broadcast sự kiện server → client theo thời gian thực **không cần dịch vụ bên thứ ba**.

### 3.2 Private Channel — Kênh Chat Riêng Tư

Hệ thống chat dùng **Private Channel** để đảm bảo chỉ đúng người nhận sự kiện:

```php
// Mỗi cuộc trò chuyện có 2 kênh riêng biệt
new PrivateChannel('chat.student.' . $studentId)   // Kênh phía sinh viên
new PrivateChannel('chat.advisor.' . $advisorId)   // Kênh phía cố vấn
```

### 3.3 Các Sự Kiện WebSocket

| Event Class   | Tên sự kiện    | Mô tả                      |
| ------------- | -------------- | -------------------------- |
| `MessageSent` | `message.sent` | Gửi tin nhắn mới realtime  |
| `MessageRead` | `message.read` | Cập nhật trạng thái đã đọc |
| `UserTyping`  | `user.typing`  | Hiển thị "đang gõ..."      |

```php
// app/Events/MessageSent.php
class MessageSent implements ShouldBroadcastNow
{
    public function broadcastOn(): array {
        return [
            new PrivateChannel('chat.student.' . $this->message->student_id),
            new PrivateChannel('chat.advisor.' . $this->message->advisor_id),
        ];
    }

    public function broadcastAs(): string {
        return 'message.sent'; // Tên event client lắng nghe
    }

    public function broadcastWith(): array {
        return [
            'message' => $this->message->toArray(),
            'sender'  => $this->senderInfo,
        ];
    }
}
```

### 3.4 `ShouldBroadcastNow` vs `ShouldBroadcast`

| Interface            | Hành vi                                     |
| -------------------- | ------------------------------------------- |
| `ShouldBroadcast`    | Broadcast qua Queue (bất đồng bộ)           |
| `ShouldBroadcastNow` | Broadcast **ngay lập tức**, không qua Queue |

Chat và typing indicator dùng `ShouldBroadcastNow` để đảm bảo độ trễ thấp nhất.

### 3.5 Luồng Xử Lý Chat

```
[Client A gửi tin] → POST /api/messages
       │
       ▼
[MessageController] → lưu vào bảng Messages (MySQL)
       │
       ▼
[event(new MessageSent($message))]
       │
       ▼
[Laravel Reverb] → broadcast qua WebSocket
       │
       ├──→ Private Channel: chat.student.{id}  → [Client A nhận xác nhận]
       └──→ Private Channel: chat.advisor.{id}  → [Client B nhận tin mới]
```

---

## 4. Push Notification — Firebase FCM

### 4.1 Tổng Quan

```
Service:  Firebase Cloud Messaging (FCM) v1
Auth:     Service Account + OAuth2 (JWT RS256)
Package:  Tự implement (không dùng package bên thứ ba)
```

**FirebaseService** tự triển khai toàn bộ quy trình xác thực OAuth2 với Google, không phụ thuộc package FCM ngoài.

### 4.2 Luồng Xác Thực OAuth2

```
[Service Account JSON]
        │
        ▼
[Tạo JWT (RS256)] → header.payload.signature
        │
        ▼
[POST https://oauth2.googleapis.com/token]
        │
        ▼
[Nhận Access Token (1h)]
        │
        ▼
[Gọi FCM API với Bearer Token]
```

```php
// Tạo JWT từ Service Account để lấy OAuth2 token
$payload = [
    'iss'   => $credentials['client_email'],
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    'aud'   => 'https://oauth2.googleapis.com/token',
    'iat'   => $now,
    'exp'   => $now + 3600,
];
openssl_sign($header . '.' . $payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
```

### 4.3 Các Phương Thức Gửi Thông Báo

| Phương thức                             | Mô tả                      |
| --------------------------------------- | -------------------------- |
| `sendToDevice($fcmToken, ...)`          | Gửi đến 1 thiết bị cụ thể  |
| `sendToMultipleDevices($tokens, ...)`   | Gửi đến nhiều thiết bị     |
| `sendToTopic($topic, ...)`              | Gửi đến nhóm theo topic    |
| `subscribeToTopic($tokens, $topic)`     | Đăng ký thiết bị vào topic |
| `unsubscribeFromTopic($tokens, $topic)` | Hủy đăng ký topic          |

### 4.4 Topic-Based Notification

Push notification được tổ chức theo **topic** cho phép gửi hàng loạt hiệu quả:

```
Topic: "class_{class_id}"    → Gửi thông báo cho toàn lớp
Topic: "advisor_{id}"        → Gửi cho cố vấn cụ thể
```

### 4.5 So Sánh: Push Notification vs WebSocket

|                  | Firebase FCM                    | Laravel Reverb (WS)       |
| ---------------- | ------------------------------- | ------------------------- |
| **Khi nào dùng** | App ở background / tắt màn hình | App đang mở, cần realtime |
| **Đảm bảo nhận** | Có (FCM queue)                  | Không (mất kết nối = mất) |
| **Ứng dụng**     | Thông báo mới, cảnh báo học vụ  | Chat, typing indicator    |

---

## 5. Email Service

### 5.1 Tổng Quan

```
Driver:  Laravel Mail (SMTP)
Class:   EmailService + SendNotificationEmailJob
```

EmailService hỗ trợ cả gửi đồng bộ và bất đồng bộ qua Queue:

### 5.2 Các Loại Email

| Phương thức                                 | Trigger khi nào                  |
| ------------------------------------------- | -------------------------------- |
| `sendNotificationEmail()`                   | Cố vấn tạo thông báo mới cho lớp |
| `sendActivityEmail()`                       | Có hoạt động ngoại khóa mới      |
| `sendAcademicWarningEmail()`                | Sinh viên nhận cảnh báo học vụ   |
| `sendMeetingEmail()`                        | Tạo / cập nhật cuộc họp lớp      |
| `sendStudentDropoutNotificationToAdvisor()` | Sinh viên bỏ học                 |

### 5.3 Gửi Đồng Bộ vs Bất Đồng Bộ

```php
// Đồng bộ (chờ gửi xong mới response)
$emailService->sendNotificationEmail($student, $notification);

// Bất đồng bộ qua Queue (response ngay, gửi email ở background)
SendNotificationEmailJob::dispatch($student, $notification);
```

### 5.4 Bulk Email

```php
// Gửi hàng loạt cho toàn lớp, tối ưu bằng cách queue từng email
$emailService->queueBulkNotificationEmails($students, $notification);
// → Mỗi student được đẩy 1 Job độc lập vào Queue
// → Queue Worker xử lý tuần tự, không block HTTP request
```

---

## 6. Google Calendar & Google Meet

### 6.1 Tổng Quan

```
Package:    google/apiclient ^2.18
Scopes:     Calendar (full), Gmail (send)
Auth:       OAuth2 với Refresh Token
Timezone:   Asia/Ho_Chi_Minh
```

### 6.2 Tính Năng

| Phương thức                             | Mô tả                                       |
| --------------------------------------- | ------------------------------------------- |
| `createMeeting()`                       | Tạo event + tự động tạo Google Meet link    |
| `updateMeeting()`                       | Cập nhật thông tin + gửi email re-invite    |
| `deleteMeeting()`                       | Xóa event + thông báo hủy cho attendees     |
| `getAttendanceStatus()`                 | Kiểm tra RSVP (accepted/declined/tentative) |
| `getAuthUrl()` / `handleAuthCallback()` | OAuth2 authorization flow                   |

### 6.3 Tích Hợp Google Meet

Khi tạo cuộc họp, hệ thống tự động tạo phòng Google Meet:

```php
'conferenceData' => [
    'createRequest' => [
        'requestId'           => 'req-' . time() . '-' . $meetingId,
        'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
    ]
]
// → Link Google Meet được trả về và lưu vào cột meeting_link (Meetings)
```

### 6.4 OAuth2 Token Management

```
credentials.json  → Client ID/Secret (đăng ký Google Cloud Console)
token.json        → Access Token + Refresh Token (tự động refresh khi hết hạn)
```

Token được tự động refresh nếu hết hạn, không cần xác thực lại thủ công.

---

## 7. Queue & Background Jobs

### 7.1 Database Queue Driver

```
QUEUE_CONNECTION=database
Retry:    --tries=3
Timeout:  --timeout=90s
```

Jobs được lưu vào MySQL thay vì Redis, phù hợp với quy mô dự án.

### 7.2 Các Bảng Queue

| Bảng          | Mô tả                                |
| ------------- | ------------------------------------ |
| `jobs`        | Hàng đợi tác vụ chờ xử lý            |
| `job_batches` | Nhóm tác vụ xử lý theo lô (bulk)     |
| `failed_jobs` | Lưu tác vụ thất bại để retry / debug |

### 7.3 Queue Worker Container

```yaml
# Container chạy độc lập, liên tục xử lý jobs
command: php artisan queue:work --tries=3 --timeout=90
```

### 7.4 Luồng Xử Lý Queue

```
[Controller] → SendNotificationEmailJob::dispatch($student, $notification)
                                │
                                ▼
                     [Lưu vào bảng jobs (MySQL)]
                                │
                                ▼
              [Queue Worker container lấy job]
                                │
                                ▼
                   [EmailService::sendNotificationEmail()]
                                │
                     ┌──────────┴──────────┐
                  [Thành công]         [Thất bại]
                  [Xóa khỏi jobs]   [Retry ≤3 lần]
                                          │
                                   [failed_jobs]
```

---

## 8. Xác Thực — JWT Authentication

### 8.1 Tổng Quan

```
Package:  tymon/jwt-auth ^2.2
Guard:    student / advisor (multi-guard)
```

Cả `Student` và `Advisor` đều implement `JWTSubject`, cho phép hệ thống xác thực **đa model** với cùng middleware JWT.

### 8.2 Custom JWT Claims

```php
// Advisor model
public function getJWTCustomClaims(): array {
    return [
        'id'        => $this->advisor_id,
        'role'      => $this->role,       // 'advisor' | 'admin'
        'name'      => $this->full_name,
        'unit_name' => $this->unit->unit_name,
    ];
}

// Student model
public function getJWTCustomClaims(): array {
    return [
        'id'        => $this->student_id,
        'role'      => 'student',
        'name'      => $this->full_name,
        'unit_name' => $this->class->faculty->unit_name,
    ];
}
```

### 8.3 Bảo Mật

| Kỹ thuật                    | Mô tả                                                      |
| --------------------------- | ---------------------------------------------------------- |
| **bcrypt**                  | Hash mật khẩu trước khi lưu DB                             |
| **`$hidden`**               | `password_hash` không bao giờ xuất hiện trong API response |
| **PDO Prepared Statements** | Eloquent mặc định, chống SQL Injection                     |
| **Private WS Channel**      | Chỉ authenticated user mới subscribe được kênh chat riêng  |

---

## 9. Docker & Containerization

### 9.1 Danh Sách Container

| Container                | Image          | Port  | Vai trò                    |
| ------------------------ | -------------- | ----- | -------------------------- |
| `advisor_system_app`     | PHP 8.2 custom | —     | Laravel app (PHP-FPM)      |
| `advisor_system_queue`   | PHP 8.2 custom | —     | Queue worker               |
| `advisor_system_nginx`   | nginx:alpine   | 8000  | Web server / reverse proxy |
| `advisor_system_mysql`   | mysql:8.0      | 3307  | Cơ sở dữ liệu quan hệ      |
| `advisor_system_mongodb` | mongo:7        | 27017 | NoSQL database             |

### 9.2 Data Persistence

```yaml
volumes:
    mysql_data:   driver: local   # Dữ liệu MySQL bền vững qua restart
    mongodb_data: driver: local   # Dữ liệu MongoDB bền vững qua restart
```

### 9.3 Auto-Migration On Deploy

```yaml
command: sh -c "php artisan migrate --force && php-fpm"
# → Chạy migration tự động trước khi app nhận request
```

### 9.4 MySQL Tuning

```
--innodb_buffer_pool_size=256M   # Tăng cache InnoDB
--max_allowed_packet=256M        # Hỗ trợ import file lớn
--skip-log-bin                   # Tắt binary log (dev only)
--character-set-server=utf8mb4
```

### 9.5 Health Check

```yaml
# App chỉ khởi động sau khi MySQL sẵn sàng
mysql:
    healthcheck:
        test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
        interval: 5s
        retries: 20
        start_period: 40s
```

---

## 10. Tổng Hợp Packages

| Package                    | Version | Vai trò                              |
| -------------------------- | ------- | ------------------------------------ |
| `laravel/framework`        | ^12.0   | Core framework                       |
| `tymon/jwt-auth`           | ^2.2    | JWT multi-model authentication       |
| `laravel/reverb`           | ^1.6    | WebSocket server (realtime chat)     |
| `mongodb/laravel-mongodb`  | `*`     | Eloquent adapter cho MongoDB         |
| `google/apiclient`         | ^2.18   | Google Calendar + Meet integration   |
| `phpoffice/phpspreadsheet` | `*`     | Import/Export Excel (điểm, lịch học) |
| `phpoffice/phpword`        | ^1.4    | Xuất biên bản họp định dạng Word     |
| `laravel/telescope`        | ^5.15   | Debug & query monitoring             |

---

_Tài liệu kỹ thuật — Advisor System v1.0 | Cập nhật: 05/2026_
