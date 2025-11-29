# Hướng dẫn cấu hình Google OAuth

## ✅ Code đã được cập nhật

Hệ thống đã được cập nhật với **Gmail scope** để gửi email mời khi tạo cuộc họp.

## 📋 Các bước cần thực hiện

### Bước 1: Cấu hình Google Cloud Console

1. **Truy cập [Google Cloud Console](https://console.cloud.google.com/)**

2. **Chọn project của bạn** (hoặc tạo project mới)

3. **Bật APIs:**

    - Vào **APIs & Services** → **Library**
    - Tìm và bật **Google Calendar API**
    - Tìm và bật **Gmail API**

4. **Cấu hình OAuth Consent Screen:**

    - Vào **APIs & Services** → **OAuth consent screen**
    - Chọn **External** (hoặc Internal nếu dùng Google Workspace)
    - Điền thông tin ứng dụng
    - Thêm các scopes sau:
        - `https://www.googleapis.com/auth/calendar` (Google Calendar API)
        - `https://www.googleapis.com/auth/gmail.send` (Gmail API - send emails)

5. **Tạo OAuth 2.0 Client ID:**

    - Vào **APIs & Services** → **Credentials**
    - Nhấn **Create Credentials** → **OAuth 2.0 Client ID**
    - Chọn **Application type**: **Web application**

    **Authorized JavaScript origins:**

    ```
    http://localhost:8000
    http://127.0.0.1:8000
    ```

    **Authorized redirect URIs:**

    ```
    http://localhost:8000/api/auth/google/callback
    http://127.0.0.1:8000/api/auth/google/callback
    ```

6. **Download credentials:**
    - Sau khi tạo, nhấn nút **Download JSON**
    - Đổi tên file thành `credentials.json`
    - Đặt vào thư mục: `storage/app/google/credentials.json`

### Bước 2: Xóa token cũ (nếu đã xác thực trước đó)

```bash
DELETE http://localhost:8000/api/auth/google/revoke
```

Hoặc xóa file thủ công:

```
storage/app/google/token.json
```

### Bước 3: Xác thực với Google

```bash
GET http://localhost:8000/api/auth/google
```

Hệ thống sẽ redirect bạn đến trang xác thực Google. Bạn cần:

-   ✅ Chấp nhận quyền **View and manage your Google Calendar**
-   ✅ Chấp nhận quyền **Send emails on your behalf**

### Bước 4: Kiểm tra trạng thái

```bash
GET http://localhost:8000/api/auth/google/status
```

Response mong đợi:

```json
{
    "success": true,
    "data": {
        "credentials_exists": true,
        "token_exists": true,
        "is_authenticated": true,
        "token_expired": false,
        "has_refresh_token": true
    }
}
```

### Bước 5: Test tạo cuộc họp

```bash
POST http://localhost:8000/api/meetings
Content-Type: application/json

{
  "title": "Test Meeting",
  "description": "Testing email and calendar integration",
  "meeting_date": "2025-12-01",
  "start_time": "10:00:00",
  "end_time": "11:00:00",
  "class_id": "your_class_id"
}
```

### ✅ Kết quả mong đợi

Sau khi tạo meeting thành công:

1. ✅ **Google Meet link** được tạo
2. ✅ **Event xuất hiện** trên Google Calendar của bạn
3. ✅ **Email mời** được gửi đến các sinh viên trong lớp

## ⚠️ Lỗi thường gặp

### Lỗi: "Invalid Origin: URIs must not contain a path"

**Nguyên nhân:** Authorized JavaScript origins có dấu `/` cuối hoặc có path

**Giải pháp:**

-   ❌ Sai: `http://localhost:8000/`
-   ❌ Sai: `http://localhost:8000/api`
-   ✅ Đúng: `http://localhost:8000`

### Lỗi: "Access denied"

**Nguyên nhân:** User từ chối cấp quyền Gmail

**Giải pháp:** Xác thực lại và chấp nhận **tất cả các quyền**

### Lỗi: "Token expired"

**Nguyên nhân:** Token đã hết hạn

**Giải pháp:** Hệ thống tự động refresh token nếu có refresh_token. Nếu không có, xác thực lại.

## 📝 Ghi chú

-   Scope **Gmail::GMAIL_SEND** chỉ cho phép **gửi email**, không đọc hay xóa email
-   Calendar events sẽ tự động gửi email mời khi có tham số `sendUpdates: 'all'`
-   Refresh token chỉ được cấp **lần đầu tiên** xác thực
