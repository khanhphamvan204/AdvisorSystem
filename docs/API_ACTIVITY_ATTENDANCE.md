# HƯỚNG DẪN SỬ DỤNG IMPORT/EXPORT ĐIỂM DANH HOẠT ĐỘNG

## 📋 TỔNG QUAN

Service `ActivityAttendanceService` cung cấp các chức năng:

1. **Export danh sách đăng ký** - Xuất tất cả sinh viên đã đăng ký hoạt động
2. **Export file mẫu điểm danh** - Xuất file Excel để điểm danh
3. **Import điểm danh** - Nhập file điểm danh và cập nhật trạng thái
4. **Thống kê điểm danh** - Xem báo cáo tổng hợp

---

## 🔧 CÀI ĐẶT

### 1. Cài đặt PhpSpreadsheet (nếu chưa có)

```bash
composer require phpoffice/phpspreadsheet
```

### 2. Đăng ký Service Provider (nếu cần)

Thêm vào `config/app.php`:

```php
'providers' => [
    // ...
    App\Services\ActivityAttendanceService::class,
],
```

### 3. Thêm routes vào `routes/api.php`

```php
// Import routes điểm danh
require __DIR__.'/api_attendance.php';
```

### 4. Tạo thư mục lưu file

```bash
mkdir -p storage/app/exports
mkdir -p storage/app/temp
chmod -R 775 storage/app/exports
chmod -R 775 storage/app/temp
```

---

## 📚 API ENDPOINTS

### 1. Export Danh Sách Đăng Ký

**Xuất tất cả sinh viên đã đăng ký hoạt động (bao gồm tất cả trạng thái)**

```http
GET /api/activities/{activityId}/export-registrations
```

**Headers:**

```
Authorization: Bearer {token}
```

**Response:**

-   File Excel (.xlsx) được tải xuống

**Nội dung file:**

-   Thông tin hoạt động
-   Danh sách sinh viên: STT, MSSV, Họ tên, Lớp, Vai trò, Điểm, Loại điểm, Trạng thái, Ghi chú
-   Tổng kết theo trạng thái

**Use case:**

-   Xem tổng quan sinh viên đã đăng ký
-   Báo cáo cho ban tổ chức
-   Lưu trữ hồ sơ

---

### 2. Export File Mẫu Điểm Danh

**Xuất file Excel có sẵn danh sách sinh viên để điểm danh**

```http
GET /api/activities/{activityId}/export-attendance-template
```

**Headers:**

```
Authorization: Bearer {token}
```

**Response:**

-   File Excel (.xlsx) có định dạng sẵn

**Đặc điểm file mẫu:**

-   Header chuyên nghiệp với logo trường, thông tin tổ chức và quốc gia (giống file export-registrations)
-   Thông tin đầy đủ: Tên hoạt động, Đơn vị tổ chức, Thời gian, Địa điểm, Cố vấn phụ trách, Ngày xuất
-   Chỉ chứa sinh viên có status: `registered`, `attended`, hoặc `absent`
-   Có cột "Trạng thái điểm danh" để điền (tiếng Việt)
-   Có hướng dẫn chi tiết trong file
-   Cột điểm danh được tô màu vàng để dễ nhận biết

**Use case:**

-   Tải file trước khi tổ chức hoạt động
-   Điểm danh thủ công (in ra hoặc dùng laptop)
-   Import lại sau hoạt động

---

### 3. Import File Điểm Danh

**Nhập file điểm danh và cập nhật trạng thái cho sinh viên**

```http
POST /api/activities/{activityId}/import-attendance
Content-Type: multipart/form-data
```

**Headers:**

```
Authorization: Bearer {token}
```

**Body (form-data):**

```
file: [file.xlsx]
```

**Request:**

```bash
curl -X POST \
  https://your-domain.com/api/activities/1/import-attendance \
  -H 'Authorization: Bearer {token}' \
  -F 'file=@DiemDanh_HoatDong_20250101.xlsx'
```

**Response Success (200):**

```json
{
    "success": true,
    "message": "Import điểm danh thành công",
    "data": {
        "total_updated": 45,
        "total_skipped": 2,
        "total_errors": 1,
        "updated": [
            {
                "row": 11,
                "registration_id": 123,
                "mssv": "210001",
                "student_name": "Nguyễn Văn A",
                "old_status": "registered",
                "new_status": "attended"
            }
        ],
        "skipped": [
            {
                "row": 15,
                "registration_id": 127,
                "mssv": "210005",
                "student_name": "Trần Thị B",
                "reason": "Trạng thái hiện tại không cho phép cập nhật: cancelled"
            }
        ],
        "errors": [
            {
                "row": 20,
                "registration_id": 130,
                "mssv": "210010",
                "student_name": "Lê Văn C",
                "reason": "Trạng thái không hợp lệ. Chỉ chấp nhận: \"Có mặt\" hoặc \"Vắng mặt\""
            }
        ]
    }
}
```

**Quy tắc import:**

-   Chỉ chấp nhận trạng thái:
    -   **Tiếng Việt (khuyến nghị):** `"Có mặt"` hoặc `"Vắng mặt"`
    -   **Tiếng Anh (vẫn hỗ trợ):** `attended` hoặc `absent`
    -   **Không dấu:** `co mat` hoặc `vang mat` (hệ thống tự nhận diện)
-   KHÔNG cho phép sửa cột: STT, Registration ID, MSSV, Họ tên, Vai trò
-   Chỉ cập nhật sinh viên có status hiện tại: `registered`, `attended`, `absent`
-   Bỏ qua sinh viên có status: `cancelled`

**Error Codes:**

-   400: File không hợp lệ hoặc hoạt động chưa diễn ra
-   403: Không có quyền cập nhật
-   404: Hoạt động không tồn tại
-   422: Validation lỗi

---

### 4. Xem Thống Kê Điểm Danh

**Lấy báo cáo tổng hợp điểm danh**

```http
GET /api/activities/{activityId}/attendance-statistics
```

**Headers:**

```
Authorization: Bearer {token}
```

**Response Success (200):**

```json
{
    "success": true,
    "data": {
        "activity_id": 1,
        "activity_title": "Hiến máu nhân đạo 2025",
        "activity_status": "completed",
        "statistics": {
            "total": 50,
            "registered": 5,
            "attended": 42,
            "absent": 3,
            "cancelled": 0,
            "attendance_rate": 93.33
        }
    }
}
```

**Giải thích:**

-   `total`: Tổng số đăng ký
-   `registered`: Chưa điểm danh
-   `attended`: Có mặt
-   `absent`: Vắng mặt
-   `cancelled`: Đã hủy
-   `attendance_rate`: Tỷ lệ tham gia (%) = attended / (attended + absent)

---

## 🔄 QUY TRÌNH SỬ DỤNG CHUẨN

### **Quy trình 1: Điểm danh thủ công (offline)**

1. **Trước hoạt động (1-2 ngày):**

    - Gọi API `export-attendance-template`
    - In file Excel ra giấy hoặc mở trên laptop

2. **Trong hoạt động:**

    - Điểm danh thủ công trên file Excel
    - Điền `"Có mặt"` cho sinh viên có mặt
    - Điền `"Vắng mặt"` cho sinh viên vắng

3. **Sau hoạt động (trong ngày):**

    - Lưu file Excel
    - Gọi API `import-attendance` để cập nhật hệ thống
    - Kiểm tra kết quả import

4. **Hoàn tất:**
    - Gọi API `attendance-statistics` để xem tổng kết
    - Export lại `export-registrations` nếu cần báo cáo chính thức

### **Quy trình 2: Báo cáo nhanh**

1. Gọi API `export-registrations` để có file tổng hợp
2. File này bao gồm tất cả trạng thái, dùng để:
    - Báo cáo cho lãnh đạo
    - Lưu trữ hồ sơ
    - Đối soát với các đơn vị khác

---

## ⚠️ LƯU Ý QUAN TRỌNG

### **1. Về File Import**

-   **KHÔNG** được thay đổi cấu trúc file (cột, header)
-   **KHÔNG** được xóa/thêm dòng bất kỳ
-   **KHÔNG** được sửa Registration ID, MSSV
-   **CHỈ** được điền vào cột "Trạng thái điểm danh"
-   **Khuyến nghị điền tiếng Việt:**
    -   `"Có mặt"` cho sinh viên tham gia
    -   `"Vắng mặt"` cho sinh viên vắng
-   **Vẫn chấp nhận tiếng Anh:** `attended` hoặc `absent`

### **2. Về Trạng Thái**

| Trạng thái hiện tại | Có thể cập nhật? | Lý do                         |
| ------------------- | ---------------- | ----------------------------- |
| `registered`        | ✅ Có            | Mới đăng ký, chưa điểm danh   |
| `attended`          | ✅ Có            | Có thể sửa lại thành absent   |
| `absent`            | ✅ Có            | Có thể sửa lại thành attended |
| `cancelled`         | ❌ Không         | Sinh viên đã hủy đăng ký      |

### **3. Về Quyền Hạn**

-   Chỉ **Advisor tạo hoạt động** mới được:
    -   Export file
    -   Import điểm danh
    -   Xem thống kê
-   Admin có thể cấu hình thêm quyền nếu cần

### **4. Về File Size**

-   File upload tối đa: **5MB**
-   Format chấp nhận: `.xlsx`, `.xls`
-   Nên giữ file dưới 1000 dòng để xử lý nhanh

### **5. Về Thời Điểm**

-   **KHÔNG** thể điểm danh cho hoạt động:
    -   Status = `upcoming` (chưa diễn ra)
    -   Status = `cancelled` (đã hủy)
-   Nên điểm danh khi:
    -   Status = `ongoing` (đang diễn ra)
    -   Status = `completed` (đã kết thúc)

---

## 🧪 TESTING

### Test Case 1: Export file mẫu thành công

```bash
curl -X GET \
  http://localhost:8000/api/activities/1/export-attendance-template \
  -H 'Authorization: Bearer {token}' \
  --output DiemDanh_Test.xlsx
```

**Expected:** File .xlsx được tải xuống

### Test Case 2: Import điểm danh thành công

```bash
curl -X POST \
  http://localhost:8000/api/activities/1/import-attendance \
  -H 'Authorization: Bearer {token}' \
  -F 'file=@DiemDanh_Test.xlsx'
```

**Expected:**

```json
{
    "success": true,
    "message": "Import điểm danh thành công",
    "data": {
        "total_updated": 10,
        "total_skipped": 0,
        "total_errors": 0
    }
}
```

### Test Case 3: Import file có lỗi

**File có dòng status = "present" (sai format)**

**Expected:**

```json
{
    "success": true,
    "data": {
        "total_updated": 9,
        "total_errors": 1,
        "errors": [
            {
                "row": 15,
                "reason": "Trạng thái không hợp lệ. Chỉ chấp nhận: \"Có mặt\" hoặc \"Vắng mặt\""
            }
        ]
    }
}
```

---

## 🐛 TROUBLESHOOTING

### Lỗi 1: "File không hợp lệ"

**Nguyên nhân:**

-   File không phải .xlsx hoặc .xls
-   File bị hỏng
-   File quá lớn (>5MB)

**Giải pháp:**

-   Kiểm tra định dạng file
-   Mở file bằng Excel xem có lỗi không
-   Giảm kích thước file

### Lỗi 2: "Trạng thái không hợp lệ"

**Nguyên nhân:**

-   Điền sai từ khóa (ví dụ: "Đi học", "Nghỉ", v.v.)
-   Có khoảng trắng thừa

**Giải pháp:**

-   **Khuyến nghị:** Chỉ điền `"Có mặt"` hoặc `"Vắng mặt"`
-   **Hoặc:** `attended` hoặc `absent` (tiếng Anh)
-   **Hoặc:** `co mat` hoặc `vang mat` (không dấu)
-   Hệ thống tự động nhận diện và không phân biệt HOA/thường

### Lỗi 3: "Đăng ký không thuộc hoạt động này"

**Nguyên nhân:**

-   Dùng file mẫu của hoạt động khác
-   Registration ID bị sửa đổi

**Giải pháp:**

-   Export lại file mẫu cho đúng hoạt động
-   Không sửa cột Registration ID

---

## 📊 LUỒNG DỮ LIỆU

```
┌─────────────────────────────────────────────────────┐
│  1. Advisor export file mẫu                         │
│     GET /export-attendance-template                 │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│  2. File Excel với danh sách sinh viên              │
│     - Registration ID (không được sửa)              │
│     - MSSV, Họ tên (không được sửa)                 │
│     - Cột "Trạng thái điểm danh" (điền "Có mặt"/"Vắng mặt")│
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│  3. Advisor điểm danh và điền vào file              │
│     - Điền "Có mặt" cho SV có mặt                   │
│     - Điền "Vắng mặt" cho SV vắng                   │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│  4. Advisor upload file lên hệ thống                │
│     POST /import-attendance                         │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│  5. Hệ thống xử lý                                  │
│     - Validate từng dòng                            │
│     - Cập nhật status trong DB                      │
│     - Trả về kết quả (updated/skipped/errors)       │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│  6. Xem thống kê và export báo cáo                  │
│     GET /attendance-statistics                      │
│     GET /export-registrations                       │
└─────────────────────────────────────────────────────┘
```

---

## 📝 CHANGELOG

### Version 1.1.0 (2025-11-29)

-   ✅ **BREAKING CHANGE:** Cập nhật template header chuyên nghiệp (logo + thông tin đầy đủ)
-   ✅ **NEW FEATURE:** Hỗ trợ điền trạng thái bằng tiếng Việt ("Có mặt"/"Vắng mặt")
-   ✅ Tự động chuyển đổi tiếng Việt sang English khi lưu DB
-   ✅ Hỗ trợ nhập liệu không dấu (co mat/vang mat)
-   ✅ Cải thiện layout Excel template (độ rộng cột tối ưu)
-   ✅ Đồng bộ format giữa export-registrations và export-attendance-template

### Version 1.0.0 (2025-11-18)

-   ✅ Tạo service import/export điểm danh
-   ✅ Export danh sách đăng ký
-   ✅ Export file mẫu điểm danh
-   ✅ Import file điểm danh
-   ✅ Thống kê điểm danh
-   ✅ Validation và error handling
-   ✅ Logging và security

---

## 🔐 SECURITY

1. **Authorization:** Chỉ Advisor tạo hoạt động mới có quyền
2. **Validation:** Kiểm tra từng dòng dữ liệu import
3. **File Upload:** Giới hạn kích thước, kiểm tra MIME type
4. **Logging:** Ghi log mọi thao tác import/export
5. **Temporary Files:** Tự động xóa file tạm sau khi xử lý

---

## 📞 HỖ TRỢ

Nếu gặp vấn đề, vui lòng:

1. Kiểm tra log tại `storage/logs/laravel.log`
2. Xem lại hướng dẫn trong file Excel
3. Liên hệ IT Support: support@school.edu.vn
