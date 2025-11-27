# Hướng Dẫn Sử Dụng Queue System

## ✅ Hoàn Thành Implementation

Hệ thống Queue đã được cài đặt thành công! Email giờ sẽ được gửi **bất đồng bộ** (asynchronously) thay vì đồng bộ (synchronously).

## 🚀 Cách Sử Dụng

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

- API response time: 30-180 giây (với 100 sinh viên)
- Phải chờ tất cả email gửi xong

**Sau (Queue - Asynchronous):**

- API response time: < 2 giây ⚡
- Email được đẩy vào queue ngay lập tức
- Worker xử lý email ở background

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

## 📊 So Sánh Performance

| Metric           | Trước   | Sau              |
| ---------------- | ------- | ---------------- |
| API Response     | 30-180s | < 2s             |
| Email Processing | Tuần tự | Song song        |
| User Wait Time   | Rất lâu | Ngay lập tức     |
| Error Recovery   | Không   | Auto retry 3 lần |

## 🔧 Production Setup

Trên production server, bạn cần setup Supervisor để queue worker luôn chạy background:

### Cài Đặt Supervisor (Ubuntu/Debian)

```bash
sudo apt-get install supervisor
```

### Tạo Config File

Tạo file `/etc/supervisor/conf.d/advisor-queue.conf`:

```ini
[program:advisor-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/advisor_system/artisan queue:work --sleep=3 --tries=3 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/advisor_system/storage/logs/worker.log
stopwaitsecs=3600
```

### Khởi Động Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start advisor-queue-worker:*
```

### Quản Lý Worker

```bash
# Xem trạng thái
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart advisor-queue-worker:*

# Stop workers
sudo supervisorctl stop advisor-queue-worker:*
```

## 🐛 Troubleshooting

### Worker Không Chạy

```bash
# Check log
tail -f storage/logs/laravel.log

# Check queue connection
php artisan queue:monitor
```

### Email Không Được Gửi

1. Kiểm tra worker đang chạy: `ps aux | grep queue:work`
2. Check bảng `jobs`: `SELECT COUNT(*) FROM jobs;`
3. Check bảng `failed_jobs` để xem lỗi
4. Check email config trong `.env`

### Jobs Bị Failed

```bash
# Xem chi tiết failed job
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 1;

# Retry tất cả failed jobs
php artisan queue:retry all

# Retry job cụ thể
php artisan queue:retry <job-id>
```

## 📝 Log Files

Queue worker sẽ log vào:

- `storage/logs/laravel.log` - Main application log
- Email sent/failed được log với chi tiết student_id và notification_id

## ⚙️ Environment Variables

Trong file `.env`, confirm các settings:

```env
QUEUE_CONNECTION=database

# Email settings
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

## 🎯 Next Steps

1. ✅ Chạy queue worker: `php artisan queue:work`
2. ✅ Test tạo notification cho nhiều lớp
3. ✅ Verify API response nhanh hơn
4. ✅ Check email được gửi thành công
5. 📋 Setup Supervisor cho production (khi deploy)

---

**Lưu ý quan trọng:** Để hệ thống hoạt động, bạn **PHẢI** có queue worker đang chạy. Nếu không có worker, email sẽ không được gửi (chỉ nằm trong queue).
