# 🚀 Test WebSocket Chat - Quick Start

## Truy cập giao diện test
```
http://localhost:8000/websocket-chat
```

## Yêu cầu
1. **Reverb Server** phải đang chạy:
   ```bash
   php artisan reverb:start
   ```

2. **Vite Dev Server** phải đang chạy:
   ```bash
   npm run dev
   ```

3. **Laravel Server** phải đang chạy:
   ```bash
   php artisan serve
   ```

## Test Credentials

### Student
- **Mã số:** `2154050544`
- **Mật khẩu:** `password`

### Advisor
- **Mã số:** `GV001`
- **Mật khẩu:** `password`

## Cách test

1. **Đăng nhập:**
   - Chọn User Type (Student hoặc Advisor)
   - Nhập mã số và mật khẩu
   - Click "Login & Connect"

2. **Kiểm tra kết nối:**
   - ✅ Authentication Status → màu xanh
   - ✅ WebSocket Status → màu xanh

3. **Test real-time:**
   - Mở 2 tab trình duyệt
   - Tab 1: Login as Student
   - Tab 2: Login as Advisor
   - Gửi tin nhắn từ một tab → hiển thị real-time ở tab kia

## ✨ Tính năng

- ✅ Login bằng mã số (không còn lỗi 401)
- ✅ JWT Authentication động
- ✅ Gửi/nhận tin nhắn real-time
- ✅ Typing indicator
- ✅ Event logs chi tiết
- ✅ Status monitoring

## 🐛 Khắc phục lỗi

### Lỗi "Echo not available"
→ Chạy `npm run dev` và reload trang

### Lỗi "401 Unauthorized"
→ Kiểm tra:
- Reverb server đang chạy
- Mã số và mật khẩu đúng
- Token được gửi trong header

### WebSocket không kết nối
→ Kiểm tra:
- Reverb server chạy trên port 8080
- File `.env` có cấu hình REVERB đúng
- Console log (F12) để xem lỗi

## 📖 Chi tiết
Xem file `WEBSOCKET_TEST_GUIDE.md` để biết thêm chi tiết.
