# API Documentation - Import/Export Controller

## Base URL
```
/api/import-export
```

## Authorization
- **Required Role**: `admin`
- **Header**: `Authorization: Bearer {token}`

---

## 1. Download Template Files

### Endpoint
```http
GET /api/import-export/templates/download?type={type}
```

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| type | string | Yes | Template type: `classes`, `advisors`, `students` |

### Response
- **Success (200)**: File download `.xlsx`
- **Error (400)**: 
```json
{
  "success": false,
  "message": "Loại template không hợp lệ"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/import-export/templates/download?type=classes" \
  -H "Authorization: Bearer {token}" \
  --output Template_Classes.xlsx
```

---

## 2. Import Classes

### Endpoint
```http
POST /api/import-export/classes/import
```

### Request Body (multipart/form-data)
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| file | file | Yes | Excel file (.xlsx, .xls), max 5MB |

### Excel Structure
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| class_name | string(50) | Yes | Tên lớp (VD: DH21CNTT) |
| advisor_code | string(20) | No | Mã giảng viên cố vấn |
| faculty_name | string(150) | No | Tên khoa |
| description | text | No | Mô tả |

### Response
```json
{
  "success": true,
  "message": "Import thành công 5 lớp",
  "data": {
    "imported": 5,
    "errors": [
      "Dòng 3: Lớp DH21CNTT đã tồn tại",
      "Dòng 5: Không tìm thấy khoa Khoa ABC"
    ]
  }
}
```

### Error Responses
- **422 Validation Error**:
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "file": ["Vui lòng chọn file"]
  }
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/import-export/classes/import" \
  -H "Authorization: Bearer {token}" \
  -F "file=@Classes_Import.xlsx"
```

---

## 3. Import Advisors

### Endpoint
```http
POST /api/import-export/advisors/import
```

### Request Body (multipart/form-data)
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| file | file | Yes | Excel file (.xlsx, .xls), max 5MB |

### Excel Structure
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| user_code | string(20) | Yes | Mã giảng viên |
| full_name | string(100) | Yes | Họ và tên |
| email | string(100) | Yes | Email |
| phone_number | string(15) | No | Số điện thoại |
| unit_name | string(150) | No | Tên đơn vị (Khoa/Phòng) |
| role | enum | Yes | `advisor` hoặc `admin` |
| password | string | No | Mật khẩu (mặc định: Password@123) |

### Response
```json
{
  "success": true,
  "message": "Import thành công 10 giảng viên",
  "data": {
    "imported": 10,
    "errors": []
  }
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/import-export/advisors/import" \
  -H "Authorization: Bearer {token}" \
  -F "file=@Advisors_Import.xlsx"
```

---

## 4. Import Students

### Endpoint
```http
POST /api/import-export/students/import
```

### Request Body (multipart/form-data)
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| file | file | Yes | Excel file (.xlsx, .xls), max 5MB |

### Excel Structure
**Note**: File có nhiều sheet, mỗi sheet = 1 lớp học. Tên sheet phải trùng với tên lớp trong DB.

#### Mỗi sheet có cấu trúc:
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| user_code | string(20) | Yes | Mã sinh viên (MSSV) |
| full_name | string(100) | Yes | Họ và tên |
| email | string(100) | Yes | Email |
| phone_number | string(15) | No | Số điện thoại |
| status | enum | No | `studying`, `graduated`, `dropped` (mặc định: studying) |
| password | string | No | Mật khẩu (mặc định: Password@123) |

### Response
```json
{
  "success": true,
  "message": "Import thành công 50 sinh viên",
  "data": {
    "imported": 50,
    "errors": [
      "Sheet 'DH21CNTT' - Dòng 3: MSSV 210001 đã tồn tại",
      "Sheet 'DH22KT' - Dòng 5: Email sv.test@school.edu.vn đã tồn tại"
    ]
  }
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/import-export/students/import" \
  -H "Authorization: Bearer {token}" \
  -F "file=@Students_Import.xlsx"
```

---

## 5. Export Classes

### Endpoint
```http
GET /api/import-export/classes/export
```

### Response
- **Success (200)**: File download `Danh_sach_lop_{timestamp}.xlsx`

### Excel Output Columns
| Column | Description |
|--------|-------------|
| Tên lớp | class_name |
| Mã cố vấn | advisor.user_code |
| Tên cố vấn | advisor.full_name |
| Tên khoa | faculty.unit_name |
| Số sinh viên | students.count() |
| Mô tả | description |

### Example
```bash
curl -X GET "http://localhost:8000/api/import-export/classes/export" \
  -H "Authorization: Bearer {token}" \
  --output Danh_sach_lop.xlsx
```

---

## 6. Export Students by Class

### Endpoint
```http
GET /api/import-export/students/{classId}/export
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| classId | integer | Yes | ID của lớp |

### Response
- **Success (200)**: File download `Danh_sach_SV_{class_name}_{timestamp}.xlsx`
- **Error (404)**:
```json
{
  "success": false,
  "message": "Không tìm thấy lớp"
}
```
- **Error (403)**:
```json
{
  "success": false,
  "message": "Bạn không có quyền xuất danh sách lớp này"
}
```

### Excel Output Columns
| Column | Description |
|--------|-------------|
| MSSV | user_code |
| Họ và tên | full_name |
| Email | email |
| Số điện thoại | phone_number |
| Trạng thái | status |

### Example
```bash
curl -X GET "http://localhost:8000/api/import-export/students/1/export" \
  -H "Authorization: Bearer {token}" \
  --output Danh_sach_SV_DH21CNTT.xlsx
```

---

## Common Error Responses

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Token không hợp lệ hoặc đã hết hạn"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Bạn không có quyền truy cập"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Lỗi: {error_message}"
}
```

---

## Import Rules & Validations

### Common Rules
- Dòng đầu tiên là header (bắt buộc)
- Bỏ qua các dòng trống
- Tự động trim khoảng trắng thừa
- Email phải unique trong toàn hệ thống
- user_code phải unique trong toàn hệ thống

### Error Handling
- **Duplicate email/user_code**: Bỏ qua dòng và ghi log
- **Thiếu trường bắt buộc**: Bỏ qua dòng và ghi log
- **Tên lớp không tồn tại**: Báo lỗi
- **Mã cố vấn không tồn tại**: Set NULL cho advisor_id
- **Tên khoa không tồn tại**: Set NULL cho faculty_id

### Default Password
- Nếu không cung cấp: `Password@123`
- Tự động hash bằng bcrypt trước khi lưu

### Validation Rules
- **Email**: format email hợp lệ
- **Phone**: số điện thoại (10-11 số)
- **user_code**: không chứa ký tự đặc biệt
- **role**: chỉ nhận `advisor` hoặc `admin`
- **status**: chỉ nhận `studying`, `graduated`, `dropped`

### Admin Restrictions
- Admin chỉ có thể import dữ liệu cho khoa mình quản lý
- Dữ liệu không thuộc khoa mình sẽ bị bỏ qua và ghi log lỗi