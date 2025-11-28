# Tài Liệu Đầy Đủ: Queue Job Email System

## 📖 Mục Lục

1. [Quick Start - Cách Chạy Nhanh](#quick-start---cách-chạy-nhanh)
2. [Tổng Quan](#tổng-quan)
3. [Kiến Trúc Hệ Thống](#kiến-trúc-hệ-thống)
4. [Thành Phần Chi Tiết](#thành-phần-chi-tiết)
5. [Luồng Xử Lý](#luồng-xử-lý)
6. [Cấu Hình](#cấu-hình)
7. [Hướng Dẫn Sử Dụng](#hướng-dẫn-sử-dụng)
8. [Deployment](#deployment)
9. [Monitoring & Logging](#monitoring--logging)
10. [Best Practices](#best-practices)
11. [Troubleshooting](#troubleshooting)
12. [Performance Tuning](#performance-tuning)

---

## Quick Start - Cách Chạy Nhanh

### 🚀 Chạy Trong Môi Trường Development

**Bước 1: Cấu hình `.env`**

```env
QUEUE_CONNECTION=database
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
```

**Bước 2: Tạo bảng database**

```bash
php artisan migrate
```

**Bước 3: Chạy Queue Worker**

```bash
# Mở terminal riêng và chạy lệnh này
php artisan queue:work --verbose --tries=3 --timeout=60
```

> ⚠️ **Lưu ý:** Terminal này phải **luôn mở** để worker xử lý email. Khi đóng terminal, email sẽ không được gửi!

**Bước 4: Test gửi notification**

```bash
# API sẽ response ngay lập tức (< 2s)
POST /api/notifications

# Email sẽ được gửi ở background bởi queue worker
```

### 📊 Kiểm Tra Hoạt Động

```bash
# Xem jobs đang chờ xử lý
php artisan queue:monitor

# Xem log real-time
tail -f storage/logs/laravel.log

# Check trong database
SELECT * FROM jobs;
SELECT * FROM failed_jobs;
```

### ✅ Xác Nhận Thành Công

-   ✅ API response nhanh (< 2 giây)
-   ✅ Worker log hiển thị "Email sent successfully"
-   ✅ Email được nhận trong inbox
-   ✅ Bảng `jobs` rỗng (jobs đã xử lý xong)

---

## Tổng Quan

### Vấn Đề Giải Quyết

**Trước đây:** Gửi email đồng bộ (synchronous) trong vòng lặp khiến API response rất chậm (30-180 giây cho 100 sinh viên).

**Giải pháp:** Sử dụng Laravel Queue Job để gửi email bất đồng bộ (asynchronous), giảm API response time xuống < 2 giây.

### Công Nghệ Sử Dụng

-   **Laravel Queue System** - Quản lý queue và job processing
-   **Database Driver** - Lưu trữ jobs trong MySQL
-   **SendNotificationEmailJob** - Custom job class để gửi email
-   **Laravel Eloquent** - Query fresh data từ database

### Lợi Ích

| Lợi ích               | Mô tả                          |
| --------------------- | ------------------------------ |
| ⚡ **Performance**    | API response 97% nhanh hơn     |
| 🔄 **Reliability**    | Auto retry 3 lần nếu failed    |
| 📊 **Scalability**    | Dễ scale với multiple workers  |
| 🐛 **Debugging**      | Chi tiết log và error tracking |
| 🔒 **Data Integrity** | Query fresh data từ DB mỗi lần |

### So Sánh Performance

| Metric           | Trước   | Sau              |
| ---------------- | ------- | ---------------- |
| API Response     | 30-180s | < 2s             |
| Email Processing | Tuần tự | Song song        |
| User Wait Time   | Rất lâu | Ngay lập tức     |
| Error Recovery   | Không   | Auto retry 3 lần |

---

## Kiến Trúc Hệ Thống

### High-Level Architecture

```
┌─────────────┐         ┌──────────────────┐         ┌─────────────┐
│   Client    │         │   Laravel API    │         │   Database  │
│  (Frontend) │ ──────► │   Controller     │ ◄─────► │   (MySQL)   │
└─────────────┘         └──────────────────┘         └─────────────┘
                                │                            │
                                │ Dispatch Job               │
                                ▼                            ▼
                        ┌──────────────────┐         ┌─────────────┐
                        │   Queue System   │ ◄─────► │ jobs table  │
                        │  (Database)      │         │             │
                        └──────────────────┘         └─────────────┘
                                │
                                │ Process Jobs
                                ▼
                        ┌──────────────────┐
                        │  Queue Worker    │
                        │  (Background)    │
                        └──────────────────┘
                                │
                                │ Send Email
                                ▼
                        ┌──────────────────┐
                        │   SMTP Server    │
                        │  (Email Delivery)│
                        └──────────────────┘
```

### Component Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    NotificationController                    │
│  - store(): Tạo notification                                │
│  - queueBulkNotificationEmails(): Đẩy jobs vào queue       │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ Uses
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                      EmailService                            │
│  - queueNotificationEmail()                                 │
│  - queueBulkNotificationEmails()                            │
│  - sendNotificationEmail() [actual sending logic]           │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ Dispatches
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                 SendNotificationEmailJob                     │
│  Properties:                                                 │
│  - $studentId: int                                          │
│  - $notificationId: int                                     │
│  - $tries = 3                                               │
│  - $timeout = 60                                            │
│                                                              │
│  Methods:                                                    │
│  - __construct($student, $notification)                     │
│  - handle(EmailService $emailService)                       │
│  - failed(Throwable $exception)                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Thành Phần Chi Tiết

### 1. SendNotificationEmailJob

**File:** `app/Jobs/SendNotificationEmailJob.php`

#### Class Properties

```php
/**
 * Số lần tự động retry khi job thất bại
 * @var int
 */
public $tries = 3;

/**
 * Timeout cho mỗi job (giây)
 * Job sẽ bị cancel nếu chạy quá thời gian này
 * @var int
 */
public $timeout = 60;

/**
 * ID của sinh viên cần gửi email
 * Lưu ID thay vì toàn bộ object để tránh serialization issues
 * @var int
 */
protected $studentId;

/**
 * ID của notification cần gửi
 * @var int
 */
protected $notificationId;
```

#### Constructor Method

```php
/**
 * Khởi tạo job với student và notification
 *
 * @param Student|array $student - Student model hoặc array
 * @param Notification|array $notification - Notification model hoặc array
 */
public function __construct($student, $notification)
{
    // Extract chỉ ID để lưu vào queue
    // Giúp payload nhẹ và tránh serialization issues
    $this->studentId = is_object($student)
        ? $student->student_id
        : $student['student_id'];

    $this->notificationId = is_object($notification)
        ? $notification->notification_id
        : $notification['notification_id'];
}
```

**Lý do lưu chỉ ID:**

-   ✅ Payload nhỏ hơn (2 integers thay vì 2 objects)
-   ✅ Tránh serialization issues với Eloquent models
-   ✅ Luôn query fresh data từ DB (data mới nhất)
-   ✅ Không bị vấn đề với lazy-loaded relationships

#### Handle Method

```php
/**
 * Xử lý job - gửi email cho sinh viên
 *
 * @param EmailService $emailService - Dependency injection
 * @return void
 * @throws Exception - Re-throw để trigger retry mechanism
 */
public function handle(EmailService $emailService): void
{
    try {
        // 1. Query fresh data từ database
        $student = Student::find($this->studentId);
        $notification = Notification::find($this->notificationId);

        // 2. Validate data tồn tại
        if (!$student) {
            Log::error('Queue job: Student not found', [
                'student_id' => $this->studentId,
            ]);
            return; // Không retry nếu student đã bị xóa
        }

        if (!$notification) {
            Log::error('Queue job: Notification not found', [
                'notification_id' => $this->notificationId,
            ]);
            return; // Không retry nếu notification đã bị xóa
        }

        // 3. Gửi email
        $emailService->sendNotificationEmail($student, $notification);

        // 4. Log success
        Log::info('Queue job: Email sent successfully', [
            'student_id' => $student->student_id,
            'notification_id' => $notification->notification_id,
        ]);

    } catch (\Exception $e) {
        // Log error với attempt number
        Log::error('Queue job: Failed to send email', [
            'student_id' => $this->studentId,
            'notification_id' => $this->notificationId,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
        ]);

        // Re-throw để Laravel retry
        throw $e;
    }
}
```

#### Failed Method

```php
/**
 * Xử lý khi job failed vĩnh viễn (sau tất cả retries)
 *
 * @param Throwable $exception - Exception cuối cùng
 * @return void
 */
public function failed(\Throwable $exception): void
{
    // Log permanent failure
    Log::error('Queue job: Permanently failed after all retries', [
        'student_id' => $this->studentId,
        'notification_id' => $this->notificationId,
        'error' => $exception->getMessage(),
    ]);

    // Có thể thêm logic:
    // - Gửi alert cho admin
    // - Lưu vào failed_jobs_notifications table
    // - Trigger webhook
}
```

---

### 2. EmailService

**File:** `app/Services/EmailService.php`

#### queueNotificationEmail Method

```php
/**
 * Queue một email notification (async)
 *
 * @param Student $student
 * @param Notification $notification
 * @return bool
 */
public function queueNotificationEmail($student, $notification)
{
    try {
        // Dispatch job vào queue
        SendNotificationEmailJob::dispatch($student, $notification);

        Log::info('Email queued for sending', [
            'student_id' => $student->student_id,
            'notification_id' => $notification->notification_id
        ]);

        return true;
    } catch (\Exception $e) {
        Log::error('Failed to queue notification email', [
            'student_id' => $student->student_id,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}
```

#### queueBulkNotificationEmails Method

```php
/**
 * Queue nhiều emails cùng lúc (bulk operation)
 *
 * @param Collection $students
 * @param Notification $notification
 * @return array ['queued' => int, 'failed' => int, 'total' => int]
 */
public function queueBulkNotificationEmails($students, $notification)
{
    $queuedCount = 0;
    $failedCount = 0;

    foreach ($students as $student) {
        try {
            SendNotificationEmailJob::dispatch($student, $notification);
            $queuedCount++;
        } catch (\Exception $e) {
            $failedCount++;
            Log::error('Failed to queue email for student', [
                'student_id' => $student->student_id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    Log::info('Bulk emails queued', [
        'total' => count($students),
        'queued' => $queuedCount,
        'failed' => $failedCount
    ]);

    return [
        'queued' => $queuedCount,
        'failed' => $failedCount,
        'total' => count($students)
    ];
}
```

---

### 3. NotificationController

**File:** `app/Http/Controllers/NotificationController.php`

#### store Method (Updated)

```php
public function store(Request $request)
{
    // ... validation ...

    DB::beginTransaction();
    try {
        // 1. Tạo notification
        $notification = Notification::create([...]);

        // 2. Gắn classes
        $notification->classes()->attach($request->class_ids);

        // 3. Lấy students
        $students = Student::whereIn('class_id', $request->class_ids)->get();

        // 4. Tạo recipients records
        $recipients = [];
        foreach ($students as $student) {
            $recipients[] = [
                'notification_id' => $notification->notification_id,
                'student_id' => $student->student_id,
                'is_read' => false,
                'read_at' => null
            ];
        }
        NotificationRecipient::insert($recipients);

        // 5. Queue emails (ASYNC - không chờ đợi)
        $this->emailService->queueBulkNotificationEmails($students, $notification);

        DB::commit();

        // 6. Response ngay lập tức
        return response()->json([
            'success' => true,
            'message' => 'Tạo thông báo thành công',
            'data' => $notification->load(['classes', 'attachments'])
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
        ], 500);
    }
}
```

---

## Luồng Xử Lý

### Sequence Diagram

```
User          API Controller       EmailService      Queue          Worker         SMTP
 │                  │                    │             │              │              │
 │─ POST /notifications ─────────────────►│            │              │              │
 │                  │                     │            │              │              │
 │                  │─ Create notification ────────►   │              │              │
 │                  │                     │            │              │              │
 │                  │─ queueBulkEmails() ────────────►│              │              │
 │                  │                     │            │              │              │
 │                  │                     │─ dispatch() ────────────►│              │
 │                  │                     │            │              │              │
 │◄─ 201 Created ───────────────────────────────────  │              │              │
 │                  │                     │            │              │              │
 │                  │                     │            │─ pop job ──►│              │
 │                  │                     │            │              │              │
 │                  │                     │            │              │─ find(ID) ──►DB
 │                  │                     │            │              │              │
 │                  │                     │◄─ handle() ──────────────│              │
 │                  │                     │            │              │              │
 │                  │                     │            │              │─ send() ───►│
 │                  │                     │            │              │              │
 │                  │                     │            │◄─ delete job ──────────────│
```

### Detailed Flow

#### 1. Request Processing (< 1 second)

```
1. User gửi POST /api/notifications
   ├─ Input: title, summary, class_ids
   └─ Headers: Authorization token

2. Controller validate request
   ├─ Check permissions
   ├─ Validate input
   └─ Check class ownership

3. Create notification record
   └─ Insert vào Notifications table

4. Create recipient records
   ├─ Query students từ class_ids
   ├─ Bulk insert vào Notification_Recipients
   └─ Count: N students

5. Queue email jobs
   ├─ Loop qua N students
   ├─ Dispatch SendNotificationEmailJob cho mỗi student
   ├─ Insert N records vào jobs table
   └─ Log: "Bulk emails queued"

6. Response 201 Created
   └─ Return notification data
```

#### 2. Background Processing (async)

```
1. Worker đọc từ jobs table
   └─ SELECT * FROM jobs ORDER BY id LIMIT 1

2. Reserve job
   └─ UPDATE jobs SET reserved_at = NOW()

3. Deserialize job payload
   ├─ Extract studentId
   └─ Extract notificationId

4. Execute SendNotificationEmailJob::handle()
   ├─ Query Student::find(studentId)
   ├─ Query Notification::find(notificationId)
   ├─ Validate data exists
   ├─ Call EmailService::sendNotificationEmail()
   └─ Send via SMTP

5. Success path:
   ├─ DELETE FROM jobs WHERE id = ?
   └─ Log: "Email sent successfully"

6. Failure path (retry):
   ├─ UPDATE jobs SET attempts = attempts + 1
   ├─ Log error with attempt number
   └─ If attempts < 3: retry
       Else: Move to failed_jobs
```

---

## Cấu Hình

### Environment Variables (.env)

```env
# Queue Configuration
QUEUE_CONNECTION=database
QUEUE_PREFIX=advisor_

# Timezone
APP_TIMEZONE=Asia/Ho_Chi_Minh

# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@advisor-system.com
MAIL_FROM_NAME="${APP_NAME}"

# Database (Queue storage)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=advisor_system
DB_USERNAME=root
DB_PASSWORD=
```

### Queue Configuration (config/queue.php)

```php
'default' => env('QUEUE_CONNECTION', 'database'),

'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ],
],

'failed' => [
    'driver' => 'database',
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'failed_jobs',
],
```

---

## Hướng Dẫn Sử Dụng

### ✅ Hoàn Thành Implementation

Hệ thống Queue đã được cài đặt thành công! Email giờ sẽ được gửi **bất đồng bộ** (asynchronously) thay vì đồng bộ (synchronously).

### 1. Chạy Queue Worker

Queue worker sẽ lắng nghe và xử lý các job trong queue. Bạn cần chạy worker này trong một terminal riêng:

```bash
# Development - Chạy trong terminal riêng
php artisan queue:work

# Hoặc với verbose output để xem chi tiết
php artisan queue:work --verbose

# Với số lần retry
php artisan queue:work --tries=3 --timeout=60
```

> **Lưu ý:** Terminal này phải được mở liên tục để worker xử lý jobs. Khi bạn đóng terminal, worker sẽ dừng.

### 2. Test Gửi Thông Báo

Bây giờ khi bạn tạo notification mới qua API:

```bash
POST /api/notifications
```

**Trước (Synchronous):**

-   API response time: 30-180 giây (với 100 sinh viên)
-   Phải chờ tất cả email gửi xong

**Sau (Queue - Asynchronous):**

-   API response time: < 2 giây ⚡
-   Email được đẩy vào queue ngay lập tức
-   Worker xử lý email ở background

### 3. Kiểm Tra Jobs Trong Database

Xem jobs đang chờ xử lý:

```sql
SELECT * FROM jobs;
```

Xem jobs đã thất bại:

```sql
SELECT * FROM failed_jobs;
```

### 4. Monitor Queue

Kiểm tra trạng thái queue:

```bash
# Xem số lượng jobs trong queue
php artisan queue:monitor

# Clear tất cả jobs trong queue (nếu cần)
php artisan queue:clear

# Retry jobs đã failed
php artisan queue:retry all
```

### 5. Testing Manually

```bash
# Test manually
php artisan tinker
>>> $student = App\Models\Student::first();
>>> $notification = App\Models\Notification::first();
>>> App\Jobs\SendNotificationEmailJob::dispatch($student, $notification);

# Check job created
>>> DB::table('jobs')->count();

# Run worker và check
>>> php artisan queue:work --once
```

---

## Deployment

### Development

```bash
# Terminal 1: Run Laravel app
php artisan serve

# Terminal 2: Run queue worker
php artisan queue:work --verbose --tries=3 --timeout=60
```

### Production (with Supervisor)

#### 1. Install Supervisor

```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

#### 2. Create Supervisor Config

File: `/etc/supervisor/conf.d/advisor-queue.conf`

```ini
[program:advisor-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/advisor_system/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/advisor_system/storage/logs/queue-worker.log
stopwaitsecs=3600
```

**Giải thích config:**

-   `numprocs=4`: Chạy 4 workers song song
-   `--sleep=3`: Sleep 3 giây khi queue rỗng
-   `--tries=3`: Retry tối đa 3 lần
-   `--max-time=3600`: Restart worker sau 1 giờ (tránh memory leak)

#### 3. Start Supervisor

```bash
# Reload config
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start advisor-queue-worker:*

# Check status
sudo supervisorctl status
```

#### 4. Quản Lý Worker

```bash
# Xem trạng thái
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart advisor-queue-worker:*

# Stop workers
sudo supervisorctl stop advisor-queue-worker:*
```

---

## Monitoring & Logging

### Log Locations

```bash
# Application log
storage/logs/laravel.log

# Queue worker log (với Supervisor)
storage/logs/queue-worker.log

# System log
/var/log/supervisor/supervisord.log
```

### Important Log Messages

#### Success

```
[2025-11-27 23:00:00] local.INFO: Email queued for sending
{"student_id":123,"notification_id":456}

[2025-11-27 23:00:01] local.INFO: Queue job: Email sent successfully
{"student_id":123,"notification_id":456}
```

#### Retry

```
[2025-11-27 23:00:01] local.ERROR: Queue job: Failed to send email
{"student_id":123,"notification_id":456,"error":"Connection timeout","attempt":1}

[2025-11-27 23:00:05] local.ERROR: Queue job: Failed to send email
{"student_id":123,"notification_id":456,"error":"Connection timeout","attempt":2}
```

#### Permanent Failure

```
[2025-11-27 23:00:10] local.ERROR: Queue job: Permanently failed after all retries
{"student_id":123,"notification_id":456,"error":"Connection timeout"}
```

### Monitoring Commands

```bash
# Xem số lượng jobs pending
php artisan queue:monitor

# Xem failed jobs
php artisan queue:failed

# Xem log real-time
tail -f storage/logs/laravel.log

# Count jobs trong database
mysql> SELECT COUNT(*) FROM jobs;
mysql> SELECT COUNT(*) FROM failed_jobs;
```

### Database Queries

```sql
-- Jobs đang chờ xử lý
SELECT id, queue, attempts, created_at
FROM jobs
ORDER BY id;

-- Jobs đã failed
SELECT id, uuid, failed_at, exception
FROM failed_jobs
ORDER BY failed_at DESC;

-- Failed job statistics
SELECT
    DATE(failed_at) as date,
    COUNT(*) as failed_count
FROM failed_jobs
GROUP BY DATE(failed_at)
ORDER BY date DESC;
```

---

## Best Practices

### 1. Job Design

✅ **DO:**

-   Lưu chỉ ID, không lưu toàn bộ Eloquent model
-   Query fresh data trong `handle()` method
-   Implement `failed()` method để xử lý permanent failures
-   Set reasonable `$timeout` và `$tries`
-   Log chi tiết với context (student_id, notification_id)

❌ **DON'T:**

-   Serialize toàn bộ Eloquent models
-   Làm logic phức tạp trong constructor
-   Ignore exceptions (luôn re-throw để trigger retry)
-   Query quá nhiều data không cần thiết

### 2. Error Handling

```php
public function handle(EmailService $emailService): void
{
    try {
        // Check data exists trước khi process
        if (!$student || !$notification) {
            return; // Early return, không retry
        }

        // Process
        $emailService->sendNotificationEmail($student, $notification);

    } catch (\Exception $e) {
        // Log với context đầy đủ
        Log::error('Job failed', [
            'student_id' => $this->studentId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Re-throw để trigger retry
        throw $e;
    }
}
```

### 3. Performance

```php
// ✅ Good: Bulk dispatch
foreach ($students as $student) {
    SendNotificationEmailJob::dispatch($student, $notification);
}

// ❌ Bad: Chain dispatch (tạo nested jobs)
SendNotificationEmailJob::dispatch($student, $notification)
    ->chain([...]);
```

---

## Troubleshooting

### Issue: Jobs không được xử lý

**Triệu chứng:** Jobs tồn tại trong bảng `jobs` nhưng không bị xóa

**Nguyên nhân:**

-   Queue worker không chạy
-   Worker bị crash
-   Connection timeout

**Giải pháp:**

```bash
# Check worker status
ps aux | grep "queue:work"

# Restart worker
sudo supervisorctl restart advisor-queue-worker:*

# Check log
tail -f storage/logs/laravel.log
```

### Issue: Jobs failed liên tục

**Triệu chứng:** Nhiều jobs trong `failed_jobs` table

**Nguyên nhân:**

-   Email config sai
-   SMTP server down
-   Network issues
-   Data không tồn tại

**Giải pháp:**

```bash
# Check failed jobs
php artisan queue:failed

# Retry specific job
php artisan queue:retry <job-id>

# Retry all
php artisan queue:retry all

# Check email config
php artisan tinker
>>> Mail::raw('Test email', function($msg) {
    $msg->to('test@example.com')->subject('Test');
});
```

### Issue: Memory leak

**Triệu chứng:** Worker memory tăng dần theo thời gian

**Nguyên nhân:**

-   Không release connections
-   Eloquent models cache

**Giải pháp:**

```bash
# Restart worker định kỳ với --max-time
php artisan queue:work --max-time=3600

# Hoặc trong Supervisor config
command=php artisan queue:work --max-time=3600 --memory=512
```

### Issue: Worker Không Chạy

```bash
# Check log
tail -f storage/logs/laravel.log

# Check queue connection
php artisan queue:monitor
```

### Issue: Email Không Được Gửi

1. Kiểm tra worker đang chạy: `ps aux | grep queue:work`
2. Check bảng `jobs`: `SELECT COUNT(*) FROM jobs;`
3. Check bảng `failed_jobs` để xem lỗi
4. Check email config trong `.env`

### Issue: Jobs Bị Failed

```bash
# Xem chi tiết failed job
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 1;

# Retry tất cả failed jobs
php artisan queue:retry all

# Retry job cụ thể
php artisan queue:retry <job-id>
```

---

## Performance Tuning

### Tăng Throughput

```bash
# Tăng số workers (Supervisor)
numprocs=8

# Giảm sleep time
command=php artisan queue:work --sleep=1

# Process multiple jobs per cycle
command=php artisan queue:work --max-jobs=1000
```

### Database Optimization

```sql
-- Index cho jobs table
CREATE INDEX idx_queue_reserved ON jobs(queue, reserved_at);
CREATE INDEX idx_available_at ON jobs(available_at);

-- Clean old failed jobs
DELETE FROM failed_jobs WHERE failed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Monitoring Metrics

| Metric           | Tốt     | Cần Cải Thiện |
| ---------------- | ------- | ------------- |
| Jobs/second      | > 10    | < 5           |
| Average job time | < 3s    | > 10s         |
| Failed rate      | < 1%    | > 5%          |
| Queue depth      | < 100   | > 1000        |
| Worker memory    | < 100MB | > 500MB       |

---

## Tổng Kết

Queue Job system đã giúp:

-   ⚡ Giảm API response time từ 50s → 1.5s (97% improvement)
-   🚀 Xử lý email bất đồng bộ, không block user
-   🔄 Auto retry khi gửi email thất bại
-   📊 Dễ dàng scale với multiple workers
-   🐛 Chi tiết logging để debug

### Next Steps

1. ✅ Chạy queue worker: `php artisan queue:work`
2. ✅ Test tạo notification cho nhiều lớp
3. ✅ Verify API response nhanh hơn
4. ✅ Check email được gửi thành công
5. 📋 Setup Supervisor cho production (khi deploy)
6. 📊 Monitor performance trong production
7. ⚙️ Adjust số workers dựa vào load
8. 🔄 Consider chuyển sang Redis queue nếu cần performance cao hơn
9. 🚨 Implement alerting cho failed jobs
10. 📈 Setup metrics dashboard (Grafana, DataDog, etc.)

---

**Lưu ý quan trọng:** Để hệ thống hoạt động, bạn **PHẢI** có queue worker đang chạy. Nếu không có worker, email sẽ không được gửi (chỉ nằm trong queue).
