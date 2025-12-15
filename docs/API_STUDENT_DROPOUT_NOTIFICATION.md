# Tài liệu - Tính năng gửi email thông báo sinh viên bỏ học

## Tổng quan

Khi cập nhật trạng thái sinh viên thành **"dropped" (bỏ học)**, hệ thống sẽ tự động gửi email thông báo cho giảng viên cố vấn của lớp.

## Luồng hoạt động

```
Admin/Advisor cập nhật status sinh viên → "dropped"
    ↓
Kiểm tra trạng thái cũ !== "dropped"
    ↓
Tìm advisor của lớp sinh viên
    ↓
Gửi email thông báo cho advisor
    ↓
Log kết quả gửi email
```

## Chi tiết kỹ thuật

### 1. API Endpoint

**Endpoint:** `PUT /api/students/{id}`

**Phương thức:** `PUT`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body (Ví dụ):**
```json
{
  "status": "dropped"
}
```

### 2. Các trạng thái sinh viên hợp lệ

- `studying` - Đang học
- `graduated` - Đã tốt nghiệp
- `dropped` - Bỏ học ⚠️ (Sẽ gửi email)
- `suspended` - Tạm ngừng
- `reserved` - Bảo lưu

### 3. Điều kiện gửi email

Email chỉ được gửi khi **TẤT CẢ** các điều kiện sau đều thỏa mãn:

1. ✅ Request có field `status` và giá trị là `"dropped"`
2. ✅ Trạng thái cũ của sinh viên **KHÔNG PHẢI** là `"dropped"` (tránh gửi lại khi cập nhật trùng)
3. ✅ Sinh viên thuộc một lớp hợp lệ
4. ✅ Lớp đó có giảng viên cố vấn
5. ✅ Giảng viên có địa chỉ email hợp lệ

### 4. Nội dung email

Email gửi cho giảng viên bao gồm:

- **Tiêu đề:** "Thông báo sinh viên bỏ học - [Tên sinh viên]"
- **Loại:** `student_dropout`
- **Thông tin sinh viên:**
  - Họ và tên
  - Mã sinh viên
  - Lớp
  - Email
  - Số điện thoại

**Template email:** Xem tại `resources/views/emails/notification.blade.php` (phần `@elseif($type === 'student_dropout')`)

### 5. Logging

Hệ thống ghi log các sự kiện sau:

**Thành công:**
```php
Log::info('Dropout notification email sent to advisor', [
    'student_id' => $student->student_id,
    'advisor_id' => $advisor->advisor_id,
    'advisor_email' => $advisor->email
]);
```

**Thất bại:**
```php
Log::error('Failed to send dropout notification to advisor', [
    'student_id' => $student->student_id,
    'advisor_id' => $advisor ? $advisor->advisor_id : null,
    'error' => $e->getMessage()
]);
```

**Cảnh báo (thiếu email):**
```php
Log::warning('Cannot send dropout notification - advisor email missing', [
    'student_id' => $student->student_id,
    'advisor_id' => $advisor ? $advisor->advisor_id : null
]);
```

## Các file liên quan

### 1. StudentController.php
**Đường dẫn:** `app/Http/Controllers/StudentController.php`

**Phương thức:** `update(Request $request, $id)`

**Dòng code chính:**
```php
// Lưu trạng thái cũ trước khi cập nhật
$oldStatus = $student->status;

// Cập nhật các trường
$student->update($request->all());

// Reload student với relationships
$student->load(['class', 'class.advisor', 'class.faculty']);

// Gửi email cho giảng viên nếu sinh viên chuyển sang trạng thái bỏ học
if ($request->has('status') && $request->status === 'dropped' && $oldStatus !== 'dropped') {
    if ($student->class && $student->class->advisor) {
        $emailService = new EmailService();
        $emailService->sendStudentDropoutNotificationToAdvisor($student, $student->class->advisor);
    }
}
```

### 2. EmailService.php
**Đường dẫn:** `app/Services/EmailService.php`

**Phương thức mới:** `sendStudentDropoutNotificationToAdvisor($student, $advisor)`

**Chức năng:**
- Kiểm tra advisor và email hợp lệ
- Chuẩn bị dữ liệu email
- Gửi email qua Laravel Mail
- Ghi log kết quả

```php
public function sendStudentDropoutNotificationToAdvisor($student, $advisor)
{
    try {
        if (!$advisor || !$advisor->email) {
            Log::warning('Cannot send dropout notification - advisor email missing', [
                'student_id' => $student->student_id,
                'advisor_id' => $advisor ? $advisor->advisor_id : null
            ]);
            return false;
        }

        $data = [
            'type' => 'student_dropout',
            'subject' => 'Thông báo sinh viên bỏ học - ' . $student->full_name,
            'advisorName' => $advisor->full_name,
            'studentName' => $student->full_name,
            'studentCode' => $student->user_code,
            'studentEmail' => $student->email,
            'studentPhone' => $student->phone_number ?? 'Chưa cập nhật',
            'className' => $student->class ? $student->class->class_name : 'N/A',
        ];

        Mail::to($advisor->email)->send(new NotificationMail($data));

        Log::info('Dropout notification email sent to advisor', [
            'student_id' => $student->student_id,
            'advisor_id' => $advisor->advisor_id,
            'advisor_email' => $advisor->email
        ]);

        return true;
    } catch (\Exception $e) {
        Log::error('Failed to send dropout notification to advisor', [
            'student_id' => $student->student_id,
            'advisor_id' => $advisor ? $advisor->advisor_id : null,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}
```

### 3. notification.blade.php
**Đường dẫn:** `resources/views/emails/notification.blade.php`

**Template email mới thêm:**
```blade
@elseif($type === 'student_dropout')
<div style="...">
    <!-- Header -->
    <div style="...">
        🔴 THÔNG BÁO QUAN TRỌNG
    </div>
    
    <!-- Title -->
    <h2>Sinh viên bỏ học - Cập nhật trạng thái</h2>
    
    <!-- Greeting -->
    <p>Kính gửi {{ $advisorName }},</p>
    
    <!-- Message -->
    <p>Hệ thống xin thông báo một sinh viên trong lớp bạn phụ trách 
       đã được cập nhật trạng thái thành BỎ HỌC.</p>
    
    <!-- Student Info Table -->
    <table>
        <tr><td>👤 Họ và tên:</td><td>{{ $studentName }}</td></tr>
        <tr><td>🔢 Mã sinh viên:</td><td>{{ $studentCode }}</td></tr>
        <tr><td>🏫 Lớp:</td><td>{{ $className }}</td></tr>
        <tr><td>📧 Email:</td><td>{{ $studentEmail }}</td></tr>
        <tr><td>📱 Số điện thoại:</td><td>{{ $studentPhone }}</td></tr>
    </table>
    
    <!-- Note -->
    <div>
        💡 Lưu ý: Vui lòng cập nhật danh sách sinh viên trong lớp...
    </div>
</div>
@endif
```

## Ví dụ sử dụng

### Test Case 1: Cập nhật trạng thái từ "studying" sang "dropped"

**Request:**
```bash
curl -X PUT "http://localhost:8000/api/students/123" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "dropped"
  }'
```

**Kết quả:** ✅ Email được gửi cho advisor

**Log:**
```
[2025-12-15 10:30:00] local.INFO: Dropout notification email sent to advisor
{"student_id":123,"advisor_id":5,"advisor_email":"gv.advisor@school.edu.vn"}
```

---

### Test Case 2: Cập nhật trạng thái từ "dropped" sang "dropped" (không thay đổi)

**Request:**
```bash
curl -X PUT "http://localhost:8000/api/students/123" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "dropped",
    "phone_number": "0912345678"
  }'
```

**Kết quả:** ❌ Email KHÔNG được gửi (vì trạng thái cũ đã là "dropped")

---

### Test Case 3: Cập nhật trạng thái từ "studying" sang "suspended"

**Request:**
```bash
curl -X PUT "http://localhost:8000/api/students/123" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "suspended"
  }'
```

**Kết quả:** ❌ Email KHÔNG được gửi (trạng thái không phải "dropped")

---

### Test Case 4: Cập nhật sinh viên không có advisor

**Request:**
```bash
curl -X PUT "http://localhost:8000/api/students/456" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "dropped"
  }'
```

**Kết quả:** ⚠️ Cập nhật thành công nhưng không gửi email

**Log:**
```
[2025-12-15 10:35:00] local.WARNING: Cannot send dropout notification - advisor email missing
{"student_id":456,"advisor_id":null}
```

## Xử lý lỗi

### Lỗi 1: Advisor không có email

**Nguyên nhân:** Advisor trong database có `email = NULL` hoặc `email = ''`

**Xử lý:** 
- Ghi log warning
- Không gửi email
- API vẫn trả về thành công (200)
- Không làm gián đoạn việc cập nhật sinh viên

### Lỗi 2: SMTP Server không khả dụng

**Nguyên nhân:** Mail server bị lỗi hoặc cấu hình sai

**Xử lý:**
- Ghi log error với chi tiết exception
- API vẫn trả về thành công (200)
- Không làm rollback việc cập nhật sinh viên

### Lỗi 3: Timeout khi gửi email

**Nguyên nhân:** Kết nối SMTP chậm

**Xử lý:**
- Catch exception và log
- Cập nhật sinh viên vẫn thành công
- Khuyến nghị: Sử dụng Queue để gửi email background

## Best Practices

### 1. Sử dụng Queue (Khuyến nghị cho production)

Nếu muốn gửi email background để không làm chậm API response:

```php
// Thay vì gọi trực tiếp:
$emailService->sendStudentDropoutNotificationToAdvisor($student, $student->class->advisor);

// Có thể sử dụng Queue:
dispatch(new SendStudentDropoutNotificationJob($student, $student->class->advisor));
```

### 2. Kiểm tra trước khi gửi

Luôn kiểm tra:
- ✅ Advisor tồn tại
- ✅ Advisor có email
- ✅ Email hợp lệ (format)

### 3. Logging đầy đủ

Ghi log cho mọi trường hợp:
- Success → `Log::info()`
- Warning (thiếu email) → `Log::warning()`
- Error (exception) → `Log::error()`

### 4. Không làm gián đoạn nghiệp vụ chính

Email là tính năng phụ, không được làm fail API update sinh viên.

## Troubleshooting

### Vấn đề: Email không được gửi

**Checklist:**

1. ✅ Kiểm tra cấu hình mail trong `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Advisor System"
```

2. ✅ Kiểm tra log file `storage/logs/laravel.log`:
```bash
tail -f storage/logs/laravel.log | grep "dropout"
```

3. ✅ Kiểm tra advisor có email:
```sql
SELECT advisor_id, full_name, email 
FROM Advisors 
WHERE advisor_id = (
    SELECT advisor_id 
    FROM Classes 
    WHERE class_id = [student_class_id]
);
```

4. ✅ Test gửi email thủ công:
```php
php artisan tinker

$student = App\Models\Student::find(123);
$advisor = $student->class->advisor;
$emailService = new App\Services\EmailService();
$emailService->sendStudentDropoutNotificationToAdvisor($student, $advisor);
```

## Tổng kết

### Ưu điểm

✅ Tự động thông báo cho giảng viên khi có sinh viên bỏ học
✅ Không làm gián đoạn flow cập nhật sinh viên
✅ Logging đầy đủ để theo dõi
✅ Template email đẹp và chuyên nghiệp
✅ Xử lý lỗi tốt, không throw exception

### Hạn chế

⚠️ Gửi email synchronous (đồng bộ) có thể làm chậm API response nếu SMTP chậm
⚠️ Nếu SMTP server down, email sẽ mất

### Khuyến nghị cải tiến

🚀 **Sử dụng Queue** để gửi email background (tương tự như `SendNotificationEmailJob` hiện có)

📊 **Thêm tracking** để biết email đã được gửi thành công hay chưa (lưu vào database)

🔄 **Retry mechanism** khi gửi email thất bại

---

**Ngày cập nhật:** 15/12/2025
**Tác giả:** GitHub Copilot
