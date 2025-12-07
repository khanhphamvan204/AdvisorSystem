# 🚀 Hướng Dẫn Test WebSocket Chat

## ✅ Đã Sửa Lỗi 401

Tôi đã tạo một giao diện test WebSocket mới với **JWT Authentication động** để khắc phục lỗi 401.

## 📋 Các Bước Test

### 1. Chạy Reverb Server

Mở terminal và chạy:
```bash
php artisan reverb:start
```

**Lưu ý:** Reverb server phải chạy trên port 8080 (hoặc port đã cấu hình trong `.env`)

### 2. Chạy Vite Dev Server

Mở terminal mới và chạy:
```bash
npm run dev
```

### 3. Chạy Laravel Server

Mở terminal thứ 3 và chạy:
```bash
php artisan serve
```

### 4. Truy Cập Trang Test

Mở trình duyệt và truy cập:
```
http://localhost:8000/websocket-chat
```

## 🔐 Đăng Nhập

### Test Credentials

**Student:**
- Mã số: `2154050544`
- Mật khẩu: `password`

**Advisor:**
- Mã số: `GV001`
- Mật khẩu: `password`

**Lưu ý:** Sử dụng mã số sinh viên/giảng viên thay vì email để đăng nhập.

## 📝 Các Bước Test WebSocket

1. **Login:**
   - Chọn User Type (Student hoặc Advisor)
   - Nhập mã số (user_code) và mật khẩu
   - Click "Login & Connect"

2. **Kiểm Tra Status:**
   - Authentication status phải chuyển sang "Authenticated" (màu xanh)
   - WebSocket status phải chuyển sang "WebSocket Connected" (màu xanh)

3. **Gửi Message:**
   - Nhập tin nhắn trong ô input
   - Click nút gửi hoặc nhấn Enter
   - Message sẽ xuất hiện trong chat

4. **Test Real-time:**
   - Mở 2 tab/cửa sổ trình duyệt
   - Tab 1: Login as Student
   - Tab 2: Login as Advisor
   - Gửi message từ một tab, message sẽ xuất hiện real-time ở tab kia

5. **Kiểm Tra Typing Indicator:**
   - Khi gõ tin nhắn, người dùng khác sẽ thấy "đang gõ..."
   - Typing indicator sẽ tự động tắt sau 3 giây

6. **Xem Event Logs:**
   - Phần Event Logs ở dưới sẽ hiển thị tất cả events real-time
   - Màu xanh: Success events
   - Màu đỏ: Error events
   - Màu vàng: Warning events
   - Màu xanh dương: Info events

## 🔧 Khắc Phục Lỗi 401

### Nguyên Nhân Lỗi 401:
- JWT Token không được gửi kèm trong request
- Token đã hết hạn
- Token không hợp lệ
- Broadcasting auth endpoint không xác thực đúng

### Giải Pháp Đã Áp Dụng:
1. ✅ **Dynamic JWT Authentication:** Login để lấy JWT token mới
2. ✅ **Auto Token Injection:** Token tự động được thêm vào Echo configuration
3. ✅ **Real-time Token Update:** Token được cập nhật mỗi lần login
4. ✅ **Proper Authorization Header:** Token được gửi đúng format `Bearer {token}`

## 🛠️ Các Tính Năng Test

### 1. Authentication Panel
- Login với Student hoặc Advisor
- Hiển thị thông tin user sau khi login
- Logout và xóa session

### 2. Chat Interface
- Gửi và nhận tin nhắn real-time
- Hiển thị typing indicator
- Auto-scroll khi có tin nhắn mới
- Phân biệt tin nhắn gửi và nhận

### 3. Status Monitoring
- Authentication Status
- WebSocket Connection Status
- Message Counter
- Event Counter

### 4. Event Logs
- Hiển thị tất cả events real-time
- Timestamp cho mỗi event
- Color-coded theo loại event
- Scroll tự động

### 5. Test Controls
- Test Broadcast: Trigger broadcast event
- Get Conversations: Lấy danh sách hội thoại
- Clear Messages: Xóa tin nhắn
- Clear Logs: Xóa event logs

### 6. File Attachment Support
- Gửi file đính kèm kèm theo tin nhắn (max 10MB)
- Nhận file đính kèm real-time qua WebSocket
- Download file đính kèm
- Preview file trong tin nhắn

## 📎 Test File Attachment

### Gửi File Đính Kèm
1. Click nút file/paperclip để chọn file
2. Chọn file từ máy tính (max 10MB)
3. File preview sẽ hiển thị
4. Nhập nội dung tin nhắn (có thể để trống)
5. Click "Send" để gửi

### Nhận File Real-time
- File sẽ được broadcast qua WebSocket
- Tin nhắn có file sẽ hiển thị icon và tên file
- Click vào tên file để download
- Event log sẽ hiển thị thông tin file

### Kiểm Tra File Data trong WebSocket Event
```javascript
echo.private('chat.student.1')
    .listen('.message.sent', (e) => {
        console.log('Message:', e.message);
        console.log('Attachment URL:', e.message.attachment_url);
        console.log('Attachment Name:', e.message.attachment_name);
    });
```

### Test File Types
- **Documents**: PDF, DOC, DOCX, XLS, XLSX
- **Images**: JPG, PNG, GIF
- **Archives**: ZIP, RAR
- **Text**: TXT, CSV

## 📊 Kiểm Tra WebSocket

### Các Events Được Test:
1. ✅ `message.sent` - Khi có tin nhắn mới
2. ✅ `message.read` - Khi tin nhắn được đọc
3. ✅ `user.typing` - Khi user đang gõ

### Channel Pattern:
- Student: `private-chat.student.{student_id}`
- Advisor: `private-chat.advisor.{advisor_id}`

## 🔍 Debug

### Kiểm Tra Console
Mở Console trong trình duyệt (F12) để xem:
- Echo initialization
- WebSocket connection status
- Received events
- Error messages

### Kiểm Tra Network Tab
Xem các request đến:
- `/api/broadcasting/auth` - Phải có Authorization header
- WebSocket connection - ws://localhost:8080

### Kiểm Tra Reverb Server Log
Terminal chạy Reverb sẽ hiển thị:
- Connection established
- Subscribed to channel
- Broadcast events

## ⚙️ Cấu Hình .env

Đảm bảo file `.env` có các dòng sau:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

## 🎯 Kết Quả Mong Đợi

Khi test thành công:
1. ✅ Login không bị lỗi 401
2. ✅ WebSocket kết nối thành công
3. ✅ Subscribe channel thành công
4. ✅ Nhận được events real-time
5. ✅ Gửi tin nhắn thành công
6. ✅ Typing indicator hoạt động

## 🐛 Troubleshooting

### Lỗi: "Echo not available"
**Giải pháp:** Chạy `npm run dev` và reload trang

### Lỗi: "401 Unauthorized"
**Giải pháp:** 
- Kiểm tra token có được gửi trong Authorization header
- Thử logout và login lại
- Kiểm tra route `/api/broadcasting/auth` có hoạt động

### Lỗi: "WebSocket connection failed"
**Giải pháp:**
- Kiểm tra Reverb server đang chạy
- Kiểm tra port 8080 không bị chặn
- Kiểm tra REVERB_HOST và REVERB_PORT trong .env

### Tin nhắn không hiển thị real-time
**Giải pháp:**
- Kiểm tra Console log xem có nhận events không
- Kiểm tra channel name có đúng format
- Thử clear cache và reload

## 📞 Support

Nếu vẫn gặp vấn đề:
1. Kiểm tra Event Logs trong giao diện test
2. Kiểm tra Console log (F12)
3. Kiểm tra Reverb server log
4. Kiểm tra Laravel log: `storage/logs/laravel.log`

---

**Giao diện mới:** `/websocket-chat`
**Ưu điểm:**
- ✅ Không còn lỗi 401
- ✅ JWT Authentication động
- ✅ UI/UX hiện đại, dễ sử dụng
- ✅ Event logs chi tiết
- ✅ Real-time status monitoring
