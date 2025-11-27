# Tối Ưu Hóa Gửi Email Bằng Laravel Queue

## Vấn Đề Hiện Tại

Email hiện đang được gửi đồng bộ (synchronously) trong `NotificationController.php` (dòng 177). Khi gửi thông báo cho toàn bộ sinh viên trong nhiều lớp:

- Hệ thống phải chờ từng email gửi xong mới gửi email tiếp theo
- Thời gian response API rất lâu (có thể lên đến vài phút với hàng trăm sinh viên)
- User phải chờ đợi trong khi không cần thiết

## Giải Pháp Đề Xuất

Sử dụng **Laravel Queue System** để gửi email bất đồng bộ (asynchronously):

### Lợi Ích

1. ⚡ **Response nhanh**: API trả về ngay lập tức (< 1 giây) thay vì phải chờ gửi hết email
2. 🚀 **Gửi song song**: Nhiều email được gửi đồng thời thay vì tuần tự
3. 🔄 **Tự động retry**: Email thất bại sẽ được tự động gửi lại
4. 📊 **Theo dõi được**: Có thể monitor queue và xử lý lỗi tốt hơn
5. 💪 **Scalable**: Dễ dàng tăng số worker để xử lý nhanh hơn

## Proposed Changes

### Job Class

#### [NEW] [SendNotificationEmailJob.php](<file:///e:/HK1%20(2025%20-%202026)/UndergraduateThesis/advisor_system/app/Jobs/SendNotificationEmailJob.php>)

Tạo Queue Job mới để xử lý việc gửi email trong background:

- Implement `ShouldQueue` interface
- Nhận student và notification data
- Gọi `EmailService` để gửi email
- Tự động retry 3 lần nếu thất bại
- Timeout sau 60 giây

---

### Service Layer

#### [MODIFY] [EmailService.php](<file:///e:/HK1%20(2025%20-%202026)/UndergraduateThesis/advisor_system/app/Services/EmailService.php>)

Thêm method mới để queue email thay vì gửi ngay:

- `queueNotificationEmail()`: Đẩy email vào queue
- `queueBulkNotificationEmails()`: Đẩy nhiều email vào queue cùng lúc
- Giữ nguyên các method hiện tại để backwards compatibility

---

### Controller

#### [MODIFY] [NotificationController.php](<file:///e:/HK1%20(2025%20-%202026)/UndergraduateThesis/advisor_system/app/Http/Controllers/NotificationController.php>)

Cập nhật `store()` method:

- Thay thế `sendNotificationEmail()` bằng `queueNotificationEmail()`
- Email sẽ được đẩy vào queue thay vì gửi ngay
- Response trả về ngay lập tức

---

### Configuration

#### [MODIFY] [.env](<file:///e:/HK1%20(2025%20-%202026)/UndergraduateThesis/advisor_system/.env>)

Cấu hình Queue driver:

- Sử dụng `database` driver cho development (dễ setup)
- Production có thể nâng cấp lên Redis để performance tốt hơn

## Implementation Details

### Queue Driver Options

#### Database (Recommended cho bắt đầu)

```
QUEUE_CONNECTION=database
```

- ✅ Dễ setup, không cần service ngoài
- ✅ Tận dụng database hiện có
- ⚠️ Performance khá tốt cho medium scale

#### Redis (Recommended cho production)

```
QUEUE_CONNECTION=redis
```

- ✅ Performance cao nhất
- ✅ Low latency
- ⚠️ Cần cài đặt Redis server

### Chạy Queue Worker

Sau khi implement, cần chạy queue worker để xử lý jobs:

```bash
# Development
php artisan queue:work --tries=3

# Production (với supervisor để auto-restart)
php artisan queue:work --tries=3 --timeout=60
```

### Migration Required

Cần tạo bảng `jobs` trong database để lưu queue:

```bash
php artisan queue:table
php artisan migrate
```

## Verification Plan

### Automated Tests

1. Tạo migration cho jobs table: `php artisan queue:table`
2. Chạy migration: `php artisan migrate`
3. Kiểm tra .env có `QUEUE_CONNECTION=database`
4. Test gửi notification và kiểm tra jobs table
5. Chạy queue worker: `php artisan queue:work`
6. Verify email được gửi từ queue

### Manual Verification

1. Tạo thông báo mới cho nhiều lớp (nhiều sinh viên)
2. Đo thời gian response API (phải < 2 giây)
3. Kiểm tra jobs table để xem email đang được xử lý
4. Chạy queue worker và kiểm tra log
5. Verify tất cả sinh viên nhận được email

### Performance Comparison

| Metric            | Trước (Synchronous)  | Sau (Queue)          |
| ----------------- | -------------------- | -------------------- |
| API Response Time | 30-180 giây (100 SV) | < 2 giây             |
| Email Processing  | Tuần tự              | Song song            |
| User Experience   | Phải chờ             | Ngay lập tức         |
| Error Handling    | Thất bại = mất email | Auto retry 3 lần     |
| Scalability       | Giới hạn             | Dễ scale với workers |

## User Review Required

> [!IMPORTANT] > **Queue Configuration Choice**
>
> Bạn muốn sử dụng queue driver nào:
>
> 1. **Database** - Dễ setup, không cần cài thêm gì, phù hợp cho bắt đầu
> 2. **Redis** - Performance cao hơn, cần cài Redis server
>
> Tôi recommend bắt đầu với **Database** driver trước, sau này có thể dễ dàng chuyển sang Redis khi cần.

> [!WARNING] > **Queue Worker Requirement**
>
> Sau khi implement, bạn cần chạy queue worker để xử lý email:
>
> ```bash
> php artisan queue:work
> ```
>
> Trên production server, nên dùng Supervisor để queue worker luôn chạy background.
