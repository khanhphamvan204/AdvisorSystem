# Hướng dẫn Tự động Cập nhật Trạng thái Hoạt động

## 📝 Tổng quan

Hệ thống tự động cập nhật trạng thái hoạt động dựa trên thời gian thực:
- **upcoming**: Chưa bắt đầu (trước `start_time`)
- **ongoing**: Đang diễn ra (giữa `start_time` và `end_time`)
- **completed**: Đã hoàn thành (sau `end_time`)
- **cancelled**: Đã hủy (không tự động thay đổi)

## 🗂️ Cấu trúc File

```
app/
├── Console/
│   └── Commands/
│       └── UpdateActivityStatus.php    # Command cập nhật trạng thái
└── Models/
    └── Activity.php                     # Model với accessor tính toán status
bootstrap/
└── app.php                              # Cấu hình scheduler
```

## ⏰ Lịch chạy tự động

Đã cấu hình trong `bootstrap/app.php`:

### 1. Mỗi ngày lúc 00:01 (đầu ngày mới)
```php
$schedule->command('activities:update-status')
    ->dailyAt('00:01')
    ->timezone('Asia/Ho_Chi_Minh');
```

### 2. Các thời điểm trong ngày (8:01, 12:01, 18:01)
```php
$schedule->command('activities:update-status')
    ->cron('1 8,12,18 * * *')
    ->timezone('Asia/Ho_Chi_Minh');
```

## 🚀 Cách kích hoạt

### Phương án 1: Laravel Scheduler Work (Khuyên dùng cho Development)

Chạy lệnh này để scheduler tự động chạy trong background:

```bash
php artisan schedule:work
```

Lệnh này sẽ chạy liên tục và tự động thực thi các scheduled tasks đúng thời gian.

### Phương án 2: Task Scheduler của Windows (Cho Production)

**Bước 1:** Mở **Task Scheduler** (tìm kiếm "Task Scheduler" trong Windows)

**Bước 2:** Chọn **Create Basic Task**

**Bước 3:** Cấu hình:
- **Name**: `Laravel Scheduler - Advisor System`
- **Trigger**: Daily, Start time: **00:00**, Recur every **1 day**
- **Advanced**: ✓ Repeat task every **1 minute** for a duration of **1 day**

**Bước 4:** Action:
- **Program/script**: `C:\php\php.exe` (đường dẫn đến php.exe trên máy bạn)
- **Arguments**: `artisan schedule:run`
- **Start in**: `E:\HK1 (2025 - 2026)\UndergraduateThesis\advisor_system`

### Phương án 3: PowerShell Script

Tạo file `run-scheduler.ps1`:

```powershell
$projectPath = "E:\HK1 (2025 - 2026)\UndergraduateThesis\advisor_system"
Set-Location $projectPath

while ($true) {
    php artisan schedule:run
    Start-Sleep -Seconds 60
}
```

Chạy script:
```powershell
powershell -ExecutionPolicy Bypass -File run-scheduler.ps1
```

### Phương án 4: Chạy thủ công

```bash
# Chạy command trực tiếp
php artisan activities:update-status

# Xem danh sách scheduled tasks
php✅ Kiểm tra hoạt động

### 1. Xem danh sách scheduled tasks
```bash
php artisan schedule:list
```

**Output mẫu:**
```
1 0       * * *  php artisan activities:update-status .... Next Due: 23 hours from now
1 8,12,18 * * *  php artisan activities:update-status ..... Next Due: 7 hours from now
```

### 2. Test command thủ công
```bash
php artisan activities:update-status
```

**Output mẫu:**
```
Bắt đầu cập nhật trạng thái hoạt động...
Thời gian hiện tại: 2025-12-16 17:08:07
✓ Đã cập nhật 3 hoạt động sang trạng thái 'completed'

=== THỐNG KÊ HOẠT ĐỘNG ===
Tổng số hoạt động: 7
  - completed: 6
  - upcoming: 1

✓ Hoàn thành! Tổng cộng đã cập nhật 3 hoạt động.
```⚙️ Tùy chỉnh lịch chạy

Nếu muốn thay đổi tần suất, sửa file `bootstrap/app.php` trong phần `->withSchedule()`:

```php
// Chạy mỗi giờ
$schedule->command('activities:update-status')->hourly();

// Chạy mỗi 30 phút
$schedule->command('activities:update-status')->everyThirtyMinutes();

// Chạy mỗi 15 phút
$schedule->command('activities:update-status')->everyFifteenMinutes();

// Chạy mỗi phút (độ chính xác cao nhất)
$schedule->command('activities:update-status')->everyMinute();

// Chạy vào giờ cụ thể
$schedule->command('activities:update-status')->dailyAt('09:00');

// Chạy 2 lần trong ngày
$schedule->command('activities:update-status')->twiceDaily(8, 18);

Nếu muốn thay đổi tần suất chạy, mở file `app/Console/Kernel.php`:

```php
// Chạy mỗi giờ
$schedule->command('activities:update-status')->hourly();

// Chạy mỗi 30 phút
$schedule->command('activities:update-status')->everyThirtyMinutes();

// Chạy mỗi 15 phút
$schedule->command('activities:update-status')->everyFifteenMinutes();

// Chạy mỗi phút (độ chính xác cao nhất)
$schedule->command('activities:update-status')->everyMinute();

// Chạy vào các giờ cụ thể
$schedule->command('activities:update-status')->dailyAt('09:00');

// Chạy nhiều lần trong ngày
$schedule->command('activities:update-status')->twiceDaily(8, 18); // 8:00 và 18:00

// Chạy theo cron expression tùy chỉnh
$schedule->command('activities:update-status')->cron('0 */6 * * *'); // Mỗi 6 giờ
```

## 💻 Sử dụng trong Code

Model `Activity` đã được thêm các method hỗ trợ:

### 1. Accessor `computed_status`
Tính toán trạng thái real-time dựa trên thời gian (không lưu DB):

```php
$activity = Activity::find(1);
$realStatus = $activity->computed_status; 
// Returns: 'upcoming', 'ongoing', 'completed', hoặc 'cancelled'
```

### 2. Method `getRealTimeStatus()`
Tương tự accessor nhưng dạng method:

```php
$activity = Activity::find(1);
$status = $activity->getRealTimeStatus();
```

### 3. Method `updateStatusBasedOnTime()`
Cập nhật trạng thái vào database:

```php
$activity = Activity::find(1);
$updated = $activity->updateStatusBasedOnTime();
// Returns: true nếu có cập nhật, false nếu không thay đổi
```⚠️ Lưu ý quan trọng

1. **Scheduler cần chạy liên tục**: Sử dụng `php artisan schedule:work` hoặc setup Task Scheduler
2. **Timezone**: Đã cấu hình `Asia/Ho_Chi_Minh`
3. **Status `cancelled`**: Không bị tự động thay đổi
4. **Database locking**: Command sử dụng bulk update nên an toàn với nhiều records
5. **Production**: Nên setup Task Scheduler/Cron để tự động chạy 24/7

## 🔧 Troubleshooting

### Scheduler không chạy

**Kiểm tra danh sách:**
```bash
php artisan schedule:list
```

**Xem log:**
```bash
# Windows
Get-Content storage/logs/laravel.log -Tail 50

# Hoặc chạy với verbose
php artisan schedule:run -v
```

### Command không tìm thấy

```bash
# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan optimize:clear

# Dump autoload
composer dump-autoload
```

### Scheduler không cập nhật đúng giờ

```bash
# Kiểm tra timezone server
php -r "echo date_default_timezone_get();"

# Kiểm tra thời gian hệ thống
php -r "echo date('Y-m-d H:i:s');"
```

## 📊 Logic cập nhật

```
Kiểm tra từng Activity:
├─ Nếu status = 'cancelled' → Giữ nguyên
├─ Nếu now < start_time → status = 'upcoming'
├─ Nếu start_time ≤ now < end_time → status = 'ongoing'
└─ Nếu now ≥ end_time → status = 'completed'
```

## 🎯 Best Practices

1. **Development**: Sử dụng `php artisan schedule:work`
2. **Production**: Setup Task Scheduler hoặc Cron Job
3. **Testing**: Chạy `php artisan activities:update-status` để test ngay
4. **Monitoring**: Thêm log hoặc notification khi command chạy
5. **API**: Sử dụng `computed_status` để hiển thị trạng thái real-time
# Chạy với verbose mode
php artisan schedule:run -v
```

### Command không được tìm thấy
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear

# Dump autoload
composer dump-autoload
```
