# Troubleshooting Queue Issues

## ✅ Đã Sửa: Clear Jobs Cũ

Jobs cũ trong queue (được tạo trước khi sửa code) có data format cũ nên bị lỗi. Đã xóa tất cả jobs cũ.

## 🔧 Cách Test Đúng

### Bước 1: Clear All Jobs (đã làm)

```bash
php artisan queue:clear
# hoặc
php artisan tinker --execute="DB::table('jobs')->truncate();"
```

### Bước 2: Restart Queue Worker

```bash
# Stop worker cũ (Ctrl+C)
# Start worker mới
php artisan queue:work --verbose
```

### Bước 3: Tạo Notification MỚI

```http
POST /api/notifications
{
  "title": "Test notification",
  "summary": "Nội dung test",
  "class_ids": [1]
}
```

### Bước 4: Kiểm Tra Log

```bash
tail -f storage/logs/laravel.log
```

**Expected log:**

```
[timestamp] local.INFO: Email queued for sending {"student_id":123,"notification_id":456}
[timestamp] local.INFO: Queue job: Email sent successfully {"student_id":123,"notification_id":456}
```

---

## 🕐 Fix Timezone Issue

Bạn nói "lấy giờ cũng không chuẩn" - đây là cách fix:

### Option 1: Set Timezone Trong .env

```env
APP_TIMEZONE=Asia/Ho_Chi_Minh
```

### Option 2: Set Trong config/app.php

```php
'timezone' => 'Asia/Ho_Chi_Minh',
```

### Option 3: Format Đúng Trong Email

Trong `EmailService.php`, khi format thời gian:

```php
// Đảm bảo có timezone
use Carbon\Carbon;

$data = [
    'activityTime' => $activity->start_time
        ? Carbon::parse($activity->start_time)
            ->timezone('Asia/Ho_Chi_Minh')
            ->format('H:i d/m/Y')
        : null,
];
```

---

## 📋 Checklist Trước Khi Test

- [ ] Queue worker đã restart với code mới
- [ ] Jobs cũ đã được xóa (`SELECT COUNT(*) FROM jobs;` = 0)
- [ ] Failed jobs đã được xóa (`SELECT COUNT(*) FROM failed_jobs;` = 0)
- [ ] Timezone được set đúng trong `.env` hoặc `config/app.php`
- [ ] Email config trong `.env` đúng

---

## 🐛 Nếu Vẫn Lỗi

### Debug Student Object

Thêm debug log trong `SendNotificationEmailJob.php`:

```php
public function handle(EmailService $emailService): void
{
    // Query fresh data từ database
    $student = Student::find($this->studentId);
    $notification = Notification::find($this->notificationId);

    // DEBUG: Log để xem data
    Log::info('Job data', [
        'student_type' => get_class($student),
        'notification_type' => get_class($notification),
        'student' => $student,
        'notification' => $notification
    ]);

    // ... rest of code
}
```

### Check Database Connection

```bash
php artisan tinker
>>> App\Models\Student::first()
>>> App\Models\Notification::first()
```

### Test Job Manually

```bash
php artisan tinker
>>> $student = App\Models\Student::first();
>>> $notification = App\Models\Notification::first();
>>> App\Jobs\SendNotificationEmailJob::dispatch($student, $notification);
```

---

## ⚠️ Common Mistakes

1. **Không restart queue worker** sau khi sửa code

   - Fix: Ctrl+C và `php artisan queue:work` lại

2. **Jobs cũ vẫn còn trong queue**

   - Fix: `php artisan queue:clear`

3. **Timezone không đúng**

   - Fix: Set `APP_TIMEZONE=Asia/Ho_Chi_Minh` trong `.env`

4. **Email config sai**
   - Fix: Check `.env` có đủ MAIL\_\* settings

---

## 📊 Verify Success

### 1. Check API Response Time

```bash
# Before: 30-50 seconds
# After: < 2 seconds ✅
```

### 2. Check Jobs Table

```sql
-- Khi tạo notification
SELECT COUNT(*) FROM jobs; -- Có jobs

-- Sau vài giây (worker xử lý)
SELECT COUNT(*) FROM jobs; -- = 0 (đã xử lý xong)
```

### 3. Check Email Log

```bash
grep "Email sent successfully" storage/logs/laravel.log
```

### 4. Check Student Nhận Email

Kiểm tra inbox của email sinh viên.
