# API Documentation - Grade Excel Import/Export System

## Mục lục
1. [Cấu trúc File Excel](#cấu-trúc-file-excel)
2. [API Endpoints](#api-endpoints)
3. [Quy trình sử dụng](#quy-trình-sử-dụng)
4. [Examples](#examples)
5. [Error Handling](#error-handling)

---

## Cấu trúc File Excel

### File Template: `template_import_diem.xlsx`

File Excel phải có **đúng 2 sheet** với tên chính xác:

#### **Sheet 1: "ThongTinChung"**

| STT | Trường | Giá trị | Ghi chú |
|-----|--------|---------|---------|
| 1 | Học kỳ (semester_id) | 1 | Nhập ID học kỳ (bắt buộc) |
| 2 | Mã môn học | IT001 | Nhập mã môn học (bắt buộc) |
| 3 | Khoa | Công nghệ Thông tin | Tự động điền |

**Lưu ý:**
- Cột A: STT (không cần sửa)
- Cột B: Tên trường (không cần sửa)
- Cột C: **GIÁ TRỊ CẦN ĐIỀN** (bắt buộc)
- Cột D: Ghi chú (tham khảo)

**Ví dụ điền đúng:**
```
A2: 1
B2: Học kỳ (semester_id)
C2: 1                          <- Điền semester_id ở đây
D2: Nhập ID học kỳ (bắt buộc)

A3: 2
B3: Mã môn học
C3: IT001                      <- Điền mã môn học ở đây
D3: Nhập mã môn học (bắt buộc)
```

---

#### **Sheet 2: "DanhSachDiem"**

| STT | Mã SV | Họ tên | Lớp | Điểm 10 | Điểm chữ | Điểm 4 | Trạng thái | Ghi chú |
|-----|-------|--------|------|---------|----------|--------|------------|---------|
| 1 | 210001 | Nguyễn Văn Hùng | DH21CNTT | 8.5 | - | - | - | |
| 2 | 210002 | Trần Thị Cẩm | DH21CNTT | 7.0 | - | - | - | |
| 3 | 210005 | Phan Thanh Bình | DH21CNTT | 9.0 | - | - | - | Điểm cao |
| 4 | 210006 | Võ Thị Kim Anh | DH21CNTT | 3.5 | - | - | - | |

**Cột cần điền (bắt buộc):**
- **Cột A (STT)**: Số thứ tự
- **Cột B (Mã SV)**: Mã sinh viên (bắt buộc, phải tồn tại trong hệ thống)
- **Cột E (Điểm 10)**: Điểm thang 10 (bắt buộc, từ 0.0 đến 10.0)

**Cột tự động tính (có thể bỏ trống):**
- Cột C (Họ tên): Tham khảo, hệ thống lấy từ DB
- Cột D (Lớp): Tham khảo, hệ thống lấy từ DB
- Cột F (Điểm chữ): Hệ thống tự tính (A, B+, B, C+, C, D+, D, F)
- Cột G (Điểm 4): Hệ thống tự tính (thang 4.0)
- Cột H (Trạng thái): Hệ thống tự tính (passed/failed)
- Cột I (Ghi chú): Tùy chọn

**Quy tắc điểm:**
```
10.0        -> A  (4.00)
8.5 - 9.9   -> B+ (3.50)
8.0 - 8.4   -> B  (3.00)
7.0 - 7.9   -> C+ (2.50)
6.5 - 6.9   -> C  (2.00)
5.5 - 6.4   -> D+ (1.50)
5.0 - 5.4   -> D  (1.00)
< 5.0       -> F  (0.00)

Đạt (passed): Điểm >= 4.0
Rớt (failed): Điểm < 4.0
```

**Ví dụ file hoàn chỉnh:**

```excel
Sheet: ThongTinChung
+-----+------------------------+----------+
| STT | Trường                | Giá trị  |
+-----+------------------------+----------+
| 1   | Học kỳ (semester_id)  | 1        |
| 2   | Mã môn học            | IT001    |
| 3   | Khoa                  | CNTT     |
+-----+------------------------+----------+

Sheet: DanhSachDiem
+-----+--------+------------------+----------+---------+----------+--------+------------+----------+
| STT | Mã SV  | Họ tên          | Lớp      | Điểm 10 | Điểm chữ | Điểm 4 | Trạng thái | Ghi chú  |
+-----+--------+------------------+----------+---------+----------+--------+------------+----------+
| 1   | 210001 | Nguyễn Văn Hùng | DH21CNTT | 8.5     |          |        |            |          |
| 2   | 210002 | Trần Thị Cẩm    | DH21CNTT | 7.0     |          |        |            |          |
| 3   | 210005 | Phan Thanh Bình | DH21CNTT | 9.0     |          |        |            | Xuất sắc |
+-----+--------+------------------+----------+---------+----------+--------+------------+----------+
```

---

## API Endpoints

### 1. Download Template Excel

**Tải file Excel mẫu để import điểm**

```http
GET /api/grades/download-template
```

**Headers:**
```
Authorization: Bearer {admin_token}
Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
```

**Authorization:** Admin only

**Response:** File Excel (`.xlsx`)
- File name: `template_import_diem_YYYYMMDDHHmmss.xlsx`
- Content-Type: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

**Response Headers:**
```
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="template_import_diem_20251114103000.xlsx"
```

**Success (200):**
- Trả về file Excel để download

**Error Responses:**

**403 Forbidden:**
```json
{
  "success": false,
  "message": "Chỉ Admin mới có quyền tải template"
}
```

**500 Internal Server Error:**
```json
{
  "success": false,
  "message": "Lỗi khi tải template: [Chi tiết lỗi]"
}
```

---

### 2. Import Điểm từ Excel

**Nhập điểm hàng loạt từ file Excel**

```http
POST /api/grades/import-excel
```

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data
```

**Body (form-data):**
```
file: [File Excel]
```

**File Requirements:**
- Format: `.xlsx` hoặc `.xls`
- Max size: 5MB (5120 KB)
- Cấu trúc: Đúng 2 sheet (ThongTinChung, DanhSachDiem)

**Authorization:** Admin only

**Success Response (200):**
```json
{
  "success": true,
  "message": "Import hoàn tất: 45 thành công, 3 cập nhật, 2 lỗi",
  "data": {
    "success": [
      {
        "row": 2,
        "user_code": "210001",
        "full_name": "Nguyễn Văn Hùng",
        "class_name": "DH21CNTT",
        "grade_value": 8.5,
        "status": "passed"
      },
      {
        "row": 3,
        "user_code": "210005",
        "full_name": "Phan Thanh Bình",
        "class_name": "DH21CNTT",
        "grade_value": 9.0,
        "status": "passed"
      }
    ],
    "updated": [
      {
        "row": 5,
        "user_code": "210002",
        "full_name": "Trần Thị Thu Cẩm",
        "class_name": "DH21CNTT",
        "grade_value": 7.0,
        "status": "passed"
      }
    ],
    "errors": [
      {
        "row": 10,
        "user_code": "220999",
        "error": "Không tìm thấy sinh viên với mã: 220999"
      },
      {
        "row": 15,
        "user_code": "210020",
        "error": "Sinh viên không thuộc khoa bạn quản lý"
      }
    ],
    "summary": {
      "total_rows": 50,
      "success_count": 45,
      "updated_count": 3,
      "error_count": 2
    }
  }
}
```

**Error Responses:**

**403 Forbidden:**
```json
{
  "success": false,
  "message": "Chỉ Admin mới có quyền import điểm"
}
```

**422 Unprocessable Entity:**
```json
{
  "success": false,
  "message": "File không hợp lệ",
  "errors": {
    "file": [
      "Trường file là bắt buộc.",
      "File phải là định dạng: xlsx, xls.",
      "File không được vượt quá 5120 KB."
    ]
  }
}
```

**500 Internal Server Error:**
```json
{
  "success": false,
  "message": "Lỗi khi import file: File Excel phải có ít nhất 2 sheet: \"ThongTinChung\" và \"DanhSachDiem\""
}
```

```json
{
  "success": false,
  "message": "Lỗi khi import file: Không tìm thấy học kỳ với ID: 999"
}
```

```json
{
  "success": false,
  "message": "Lỗi khi import file: Môn học IT999 không thuộc khoa bạn quản lý"
}
```

---

### 3. Export Điểm ra Excel

**Xuất bảng điểm lớp theo học kỳ ra file Excel**

```http
GET /api/grades/export-excel/{class_id}/{semester_id}
```

**Headers:**
```
Authorization: Bearer {admin_token}
Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
```

**Path Parameters:**
- `class_id` (integer, required): ID của lớp
- `semester_id` (integer, required): ID của học kỳ

**Authorization:** Admin only

**Example:**
```http
GET /api/grades/export-excel/1/1
```

**Response:** File Excel (`.xlsx`)
- File name: `bangdiem_{class_name}_{semester_name}_YYYYMMDDHHmmss.xlsx`
- Content-Type: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

**File Structure:**
```excel
BẢNG ĐIỂM LỚP DH21CNTT
Học kỳ: HK1 - 2024-2025
Khoa: Công nghệ Thông tin

+-----+--------+------------------+---------+---------+-----+
| STT | Mã SV  | Họ tên          | IT001   | IT002   | GPA |
+-----+--------+------------------+---------+---------+-----+
| 1   | 210001 | Nguyễn Văn Hùng | 8.5     | 7.0     | 7.8 |
| 2   | 210002 | Trần Thị Cẩm    | 7.0     | 5.0     | 6.0 |
+-----+--------+------------------+---------+---------+-----+
```

**Success (200):**
- Trả về file Excel để download

**Error Responses:**

**403 Forbidden:**
```json
{
  "success": false,
  "message": "Chỉ Admin mới có quyền xuất điểm"
}
```

```json
{
  "success": false,
  "message": "Admin chưa được gán vào khoa nào"
}
```

```json
{
  "success": false,
  "message": "Lớp này không thuộc khoa bạn quản lý"
}
```

**404 Not Found:**
```json
{
  "success": false,
  "message": "Không tìm thấy lớp"
}
```

```json
{
  "success": false,
  "message": "Không tìm thấy học kỳ"
}
```

**500 Internal Server Error:**
```json
{
  "success": false,
  "message": "Lỗi khi xuất file Excel: [Chi tiết lỗi]"
}
```

---

## Quy trình sử dụng

### Bước 1: Tải template Excel

**Request:**
```http
GET /api/grades/download-template
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response:**
- File: `template_import_diem_20251114103000.xlsx`

### Bước 2: Điền dữ liệu vào Excel

1. **Mở file template vừa tải**

2. **Sheet "ThongTinChung"** - Điền vào cột C:
   - Dòng 2: Nhập `semester_id` (VD: 1)
   - Dòng 3: Nhập `course_code` (VD: IT001)

3. **Sheet "DanhSachDiem"** - Bắt đầu từ dòng 2:
   - Cột A: STT (1, 2, 3...)
   - Cột B: Mã sinh viên (VD: 210001)
   - Cột E: Điểm 10 (VD: 8.5)
   - Các cột khác có thể để trống

4. **Lưu file**

### Bước 3: Upload file lên hệ thống

**Request:**
```http
POST /api/grades/import-excel
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: multipart/form-data

file: [Chọn file Excel đã điền]
```

**Response:**
```json
{
  "success": true,
  "message": "Import hoàn tất: 48 thành công, 0 cập nhật, 2 lỗi",
  "data": {
    "summary": {
      "total_rows": 50,
      "success_count": 48,
      "updated_count": 0,
      "error_count": 2
    }
  }
}
```

### Bước 4: Kiểm tra kết quả

- **success**: Danh sách điểm nhập thành công
- **updated**: Danh sách điểm đã cập nhật
- **errors**: Danh sách lỗi (nếu có)
- **summary**: Tổng quan kết quả

### Bước 5: Xuất điểm (nếu cần)

**Request:**
```http
GET /api/grades/export-excel/1/1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response:**
- File: `bangdiem_DH21CNTT_HK1_20251114103000.xlsx`

---

## Examples

### Example 1: Import điểm thành công hoàn toàn

**File Excel:**
```excel
Sheet: ThongTinChung
C2: 1 (semester_id)
C3: IT001 (course_code)

Sheet: DanhSachDiem
| STT | Mã SV  | Điểm 10 |
|-----|--------|---------|
| 1   | 210001 | 8.5     |
| 2   | 210002 | 7.0     |
| 3   | 210005 | 9.0     |
```

**Response:**
```json
{
  "success": true,
  "message": "Import hoàn tất: 3 thành công, 0 cập nhật, 0 lỗi",
  "data": {
    "success": [
      {
        "row": 2,
        "user_code": "210001",
        "full_name": "Nguyễn Văn Hùng",
        "class_name": "DH21CNTT",
        "grade_value": 8.5,
        "status": "passed"
      },
      {
        "row": 3,
        "user_code": "210002",
        "full_name": "Trần Thị Thu Cẩm",
        "class_name": "DH21CNTT",
        "grade_value": 7.0,
        "status": "passed"
      },
      {
        "row": 4,
        "user_code": "210005",
        "full_name": "Phan Thanh Bình",
        "class_name": "DH21CNTT",
        "grade_value": 9.0,
        "status": "passed"
      }
    ],
    "updated": [],
    "errors": [],
    "summary": {
      "total_rows": 3,
      "success_count": 3,
      "updated_count": 0,
      "error_count": 0
    }
  }
}
```

---

### Example 2: Import có lỗi và cập nhật

**File Excel:**
```excel
Sheet: DanhSachDiem
| STT | Mã SV  | Điểm 10 |
|-----|--------|---------|
| 1   | 210001 | 9.5     | <- Cập nhật điểm cũ
| 2   | 999999 | 8.0     | <- Lỗi: Không tồn tại
| 3   | 210005 | 7.5     | <- Thành công
| 4   | 220001 | 8.0     | <- Lỗi: Khác khoa
```

**Response:**
```json
{
  "success": true,
  "message": "Import hoàn tất: 1 thành công, 1 cập nhật, 2 lỗi",
  "data": {
    "success": [
      {
        "row": 4,
        "user_code": "210005",
        "full_name": "Phan Thanh Bình",
        "class_name": "DH21CNTT",
        "grade_value": 7.5,
        "status": "passed"
      }
    ],
    "updated": [
      {
        "row": 2,
        "user_code": "210001",
        "full_name": "Nguyễn Văn Hùng",
        "class_name": "DH21CNTT",
        "grade_value": 9.5,
        "status": "passed"
      }
    ],
    "errors": [
      {
        "row": 3,
        "user_code": "999999",
        "error": "Không tìm thấy sinh viên với mã: 999999"
      },
      {
        "row": 5,
        "user_code": "220001",
        "error": "Sinh viên không thuộc khoa bạn quản lý"
      }
    ],
    "summary": {
      "total_rows": 4,
      "success_count": 1,
      "updated_count": 1,
      "error_count": 2
    }
  }
}
```

---

### Example 3: Lỗi validate file

**Request:**
```http
POST /api/grades/import-excel
Content-Type: multipart/form-data

file: document.pdf  <- Sai định dạng
```

**Response (422):**
```json
{
  "success": false,
  "message": "File không hợp lệ",
  "errors": {
    "file": [
      "File phải là định dạng: xlsx, xls."
    ]
  }
}
```

---

### Example 4: Lỗi cấu trúc Excel

**File Excel:** Chỉ có 1 sheet hoặc sai tên sheet

**Response (500):**
```json
{
  "success": false,
  "message": "Lỗi khi import file: File Excel phải có ít nhất 2 sheet: \"ThongTinChung\" và \"DanhSachDiem\""
}
```

---

### Example 5: Lỗi thông tin chung

**File Excel:**
```excel
Sheet: ThongTinChung
C2: 999 (semester_id không tồn tại)
C3: IT001
```

**Response (500):**
```json
{
  "success": false,
  "message": "Lỗi khi import file: Không tìm thấy học kỳ với ID: 999"
}
```

---

### Example 6: Xuất điểm thành công

**Request:**
```http
GET /api/grades/export-excel/1/1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response:**
- Status: 200 OK
- File: `bangdiem_DH21CNTT_HK1_20251114103000.xlsx`
- Content-Type: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

**File nội dung:**
```excel
BẢNG ĐIỂM LỚP DH21CNTT
Học kỳ: HK1 - 2024-2025
Khoa: Công nghệ Thông tin

+-----+--------+----------------------+---------+---------+---------+-----+
| STT | Mã SV  | Họ tên              | IT001   | IT002   | IT003   | GPA |
+-----+--------+----------------------+---------+---------+---------+-----+
| 1   | 210001 | Nguyễn Văn Hùng     | 8.5     | 7.0     | 9.0     | 8.2 |
| 2   | 210002 | Trần Thị Thu Cẩm    | 4.0     | 5.0     | 6.5     | 5.2 |
| 3   | 210005 | Phan Thanh Bình     | 9.0     | 8.5     | 9.5     | 9.0 |
| 4   | 210006 | Võ Thị Kim Anh      | 6.5     | 7.0     | 7.5     | 7.0 |
+-----+--------+----------------------+---------+---------+---------+-----+
```

---

## Error Handling

### Các loại lỗi thường gặp

#### 1. Lỗi File

| Lỗi | HTTP Code | Message |
|-----|-----------|---------|
| Không có file | 422 | Trường file là bắt buộc |
| Sai định dạng | 422 | File phải là định dạng: xlsx, xls |
| Quá dung lượng | 422 | File không được vượt quá 5120 KB |
| Thiếu sheet | 500 | File Excel phải có ít nhất 2 sheet |
| Sai tên sheet | 500 | Thiếu sheet "ThongTinChung" |

#### 2. Lỗi Thông tin chung

| Lỗi | HTTP Code | Message |
|-----|-----------|---------|
| Thiếu semester_id | 500 | Thiếu thông tin học kỳ (semester_id) |
| semester_id không tồn tại | 500 | Không tìm thấy học kỳ với ID: {id} |
| Thiếu course_code | 500 | Thiếu mã môn học |
| course_code không tồn tại | 500 | Không tìm thấy môn học với mã: {code} |
| Môn học khác khoa | 500 | Môn học {code} không thuộc khoa bạn quản lý |

#### 3. Lỗi Dữ liệu điểm

| Lỗi | Message trong errors array |
|-----|----------------------------|
| Thiếu mã SV | Thiếu mã sinh viên |
| Điểm không phải số | Điểm không hợp lệ (phải là số) |
| Điểm ngoài phạm vi | Điểm phải nằm trong khoảng 0-10 |
| Sinh viên không tồn tại | Không tìm thấy sinh viên với mã: {code} |
| Sinh viên khác khoa | Sinh viên không thuộc khoa bạn quản lý |

#### 4. Lỗi Quyền hạn

| Lỗi | HTTP Code | Message |
|-----|-----------|---------|
| Không phải admin | 403 | Chỉ Admin mới có quyền import điểm |
| Admin chưa có khoa | 403 | Admin chưa được gán vào khoa nào |
| Lớp khác khoa | 403 | Lớp này không thuộc khoa bạn quản lý |

### Best Practices

1. **Validate trước khi import:**
   - Kiểm tra định dạng file
   - Kiểm tra cấu trúc Excel
   - Kiểm tra dữ liệu mẫu

2. **Xử lý lỗi:**
   - Đọc kỹ phần `errors` trong response
   - Kiểm tra `row` để biết dòng bị lỗi
   - Sửa lỗi và import lại

3. **Performance:**
   - Khuyến nghị tối đa 500-1000 dòng/lần
   - File lớn nên chia nhỏ thành nhiều file
   - Backup dữ liệu trước khi import

4. **Security:**
   - Chỉ admin có quyền import/export
   - Admin chỉ xử lý dữ liệu khoa mình quản lý
   - File được validate kỹ càng

---

## Testing Guide

### Postman Collection

#### 1. Download Template
```
GET {{base_url}}/api/grades/download-template
Headers:
  Authorization: Bearer {{admin_token}}
```

#### 2. Import Excel
```
POST {{base_url}}/api/grades/import-excel
Headers:
  Authorization: Bearer {{admin_token}}
Body (form-data):
  file: [Select Excel file]
```

#### 3. Export Excel
```
GET {{base_url}}/api/grades/export-excel/1/1
Headers:
  Authorization: Bearer {{admin_token}}
```

### Environment Variables
```json
{
  "base_url": "http://localhost:8000/api",
  "admin_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "advisor_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "student_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

---

## Support

### Liên hệ hỗ trợ
- Email: admin@school.edu.vn
- Hotline: 0378890133

### Tài liệu tham khảo
- [PhpSpreadsheet Documentation](https://phpspreadsheet.readthedocs.io/)
- [Laravel File Upload](https://laravel.com/docs/filesystem)

### Changelog
- **v1.0.0** (2025-11-14): Phiên bản đầu tiên
  - Import/Export điểm từ Excel
  - Download template
  - Validate dữ liệu
  - Phân quyền theo khoa