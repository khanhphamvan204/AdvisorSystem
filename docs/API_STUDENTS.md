# API Documentation - Student Controller

## Base URL
```
/api/students
```

## Authorization
- **Header**: `Authorization: Bearer {token}`
- **Roles**: `admin`, `advisor`, `student`

---

## 1. Get List of Students

### Endpoint
```http
GET /api/students
```

### Access Control
- **Admin**: Xem sinh viên trong các lớp thuộc khoa mình quản lý
- **Advisor**: Xem sinh viên trong các lớp mình làm cố vấn
- **Student**: Chỉ xem thông tin bản thân

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| class_id | integer | No | Lọc theo lớp |
| status | string | No | Lọc theo trạng thái: `studying`, `graduated`, `dropped` |
| search | string | No | Tìm kiếm theo họ tên, MSSV, email |

### Response
```json
{
  "success": true,
  "data": [
    {
      "student_id": 1,
      "user_code": "210001",
      "full_name": "Nguyễn Văn Hùng",
      "email": "sv.hung@school.edu.vn",
      "phone_number": "091122334",
      "status": "studying",
      "avatar_url": null,
      "created_at": "2024-09-01T00:00:00.000000Z",
      "last_login": null,
      "class": {
        "class_id": 1,
        "class_name": "DH21CNTT",
        "advisor": {
          "advisor_id": 1,
          "full_name": "ThS. Trần Văn An"
        },
        "faculty": {
          "unit_id": 1,
          "unit_name": "Khoa Công nghệ Thông tin"
        }
      }
    }
  ],
  "message": "Lấy danh sách sinh viên thành công"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/students?class_id=1&search=Nguyễn" \
  -H "Authorization: Bearer {token}"
```

---

## 2. Get Student Details

### Endpoint
```http
GET /api/students/{id}
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Student ID |

### Access Control
- **Admin**: Xem sinh viên trong khoa mình quản lý
- **Advisor**: Xem sinh viên trong lớp mình phụ trách
- **Student**: Chỉ xem thông tin của chính mình

### Response
```json
{
  "success": true,
  "data": {
    "student_id": 1,
    "user_code": "210001",
    "full_name": "Nguyễn Văn Hùng",
    "email": "sv.hung@school.edu.vn",
    "phone_number": "091122334",
    "status": "studying",
    "avatar_url": null,
    "created_at": "2024-09-01T00:00:00.000000Z",
    "class": {
      "class_id": 1,
      "class_name": "DH21CNTT",
      "advisor": {
        "advisor_id": 1,
        "full_name": "ThS. Trần Văn An",
        "email": "gv.an@school.edu.vn"
      },
      "faculty": {
        "unit_id": 1,
        "unit_name": "Khoa Công nghệ Thông tin"
      }
    },
    "semester_reports": [
      {
        "report_id": 1,
        "semester_id": 1,
        "gpa": 7.75,
        "credits_registered": 8,
        "credits_passed": 8,
        "semester": {
          "semester_name": "Học kỳ 1",
          "academic_year": "2024-2025"
        }
      }
    ],
    "academic_warnings": [
      {
        "warning_id": 1,
        "title": "Cảnh cáo học vụ HK1 2024-2025",
        "content": "...",
        "created_at": "2025-01-20T00:00:00.000000Z"
      }
    ],
    "course_grades": [
      {
        "grade_id": 1,
        "course": {
          "course_code": "IT001",
          "course_name": "Nhập môn Lập trình"
        },
        "semester": {
          "semester_name": "Học kỳ 1",
          "academic_year": "2024-2025"
        },
        "grade_value": 8.5,
        "status": "passed"
      }
    ]
  },
  "message": "Lấy thông tin sinh viên thành công"
}
```

### Error Responses
- **404 Not Found**:
```json
{
  "success": false,
  "message": "Không tìm thấy sinh viên"
}
```

- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn không có quyền xem sinh viên này"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/students/1" \
  -H "Authorization: Bearer {token}"
```

---

## 3. Create New Student

### Endpoint
```http
POST /api/students
```

### Access Control
- **Required Role**: `admin`
- **Restriction**: Admin chỉ tạo sinh viên cho lớp thuộc khoa mình quản lý

### Request Body
```json
{
  "user_code": "210007",
  "full_name": "Nguyễn Văn A",
  "email": "sv.a@school.edu.vn",
  "phone_number": "0901234567",
  "class_id": 1,
  "status": "studying",
  "password": "Password@123"
}
```

### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| user_code | string(20) | Yes | Mã sinh viên (MSSV) |
| full_name | string(100) | Yes | Họ và tên |
| email | string(100) | Yes | Email (unique) |
| phone_number | string(15) | No | Số điện thoại |
| class_id | integer | Yes | ID lớp học |
| status | enum | No | `studying`, `graduated`, `dropped` (mặc định: studying) |
| password | string | No | Mật khẩu (mặc định: Password@123) |

### Response
```json
{
  "success": true,
  "data": {
    "student_id": 7,
    "user_code": "210007",
    "full_name": "Nguyễn Văn A",
    "email": "sv.a@school.edu.vn",
    "phone_number": "0901234567",
    "class_id": 1,
    "status": "studying",
    "created_at": "2025-11-15T10:00:00.000000Z"
  },
  "message": "Tạo sinh viên thành công"
}
```

### Error Responses
- **422 Validation Error**:
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "user_code": ["Mã sinh viên đã tồn tại"],
    "email": ["Email đã tồn tại"],
    "class_id": ["Lớp không tồn tại"]
  }
}
```

- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn chỉ có thể tạo sinh viên cho lớp thuộc khoa mình quản lý"
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/students" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "user_code": "210007",
    "full_name": "Nguyễn Văn A",
    "email": "sv.a@school.edu.vn",
    "class_id": 1
  }'
```

---

## 4. Update Student Information

### Endpoint
```http
PUT /api/students/{id}
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Student ID |

### Access Control
- **Admin**: Cập nhật tất cả thông tin sinh viên trong khoa mình quản lý
- **Student**: Chỉ cập nhật một số trường của chính mình

### Request Body (Admin)
```json
{
  "user_code": "210007",
  "full_name": "Nguyễn Văn A",
  "email": "sv.a@school.edu.vn",
  "phone_number": "0901234567",
  "class_id": 1,
  "status": "studying"
}
```

### Request Body (Student)
```json
{
  "phone_number": "0901234567",
  "avatar_url": "https://example.com/avatar.jpg"
}
```

### Request Fields

#### Admin can update:
| Field | Type | Description |
|-------|------|-------------|
| user_code | string(20) | Mã sinh viên |
| full_name | string(100) | Họ và tên |
| email | string(100) | Email |
| phone_number | string(15) | Số điện thoại |
| class_id | integer | ID lớp học |
| status | enum | `studying`, `graduated`, `dropped` |

#### Student can update:
| Field | Type | Description |
|-------|------|-------------|
| phone_number | string(15) | Số điện thoại |
| avatar_url | string(255) | URL ảnh đại diện |

### Response
```json
{
  "success": true,
  "data": {
    "student_id": 1,
    "user_code": "210001",
    "full_name": "Nguyễn Văn Hùng",
    "email": "sv.hung@school.edu.vn",
    "phone_number": "0909999999",
    "status": "studying"
  },
  "message": "Cập nhật sinh viên thành công"
}
```

### Error Responses
- **403 Forbidden** (Student trying to update others):
```json
{
  "success": false,
  "message": "Bạn chỉ có thể cập nhật thông tin của mình"
}
```

- **403 Forbidden** (Admin trying to update outside their faculty):
```json
{
  "success": false,
  "message": "Bạn không có quyền cập nhật sinh viên này"
}
```

### Example
```bash
# Admin update
curl -X PUT "http://localhost:8000/api/students/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "graduated"
  }'

# Student update
curl -X PUT "http://localhost:8000/api/students/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "0909999999"
  }'
```

---

## 5. Delete Student

### Endpoint
```http
DELETE /api/students/{id}
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Student ID |

### Access Control
- **Required Role**: `admin`
- **Restriction**: Admin chỉ xóa sinh viên trong khoa mình quản lý

### Response
```json
{
  "success": true,
  "message": "Xóa sinh viên thành công"
}
```

### Error Responses
- **404 Not Found**:
```json
{
  "success": false,
  "message": "Không tìm thấy sinh viên"
}
```

- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn không có quyền xóa sinh viên này"
}
```

### Example
```bash
curl -X DELETE "http://localhost:8000/api/students/1" \
  -H "Authorization: Bearer {token}"
```

---

## 6. Change Password

### Endpoint
```http
POST /api/students/change-password
```

### Access Control
- **Required Role**: `student`
- **Note**: Student chỉ đổi mật khẩu của chính mình

### Request Body
```json
{
  "current_password": "OldPassword@123",
  "new_password": "NewPassword@123",
  "new_password_confirmation": "NewPassword@123"
}
```

### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| current_password | string | Yes | Mật khẩu hiện tại |
| new_password | string | Yes | Mật khẩu mới (min: 6 ký tự) |
| new_password_confirmation | string | Yes | Xác nhận mật khẩu mới |

### Response
```json
{
  "success": true,
  "message": "Đổi mật khẩu thành công"
}
```

### Error Responses
- **400 Bad Request**:
```json
{
  "success": false,
  "message": "Mật khẩu hiện tại không đúng"
}
```

- **422 Validation Error**:
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "new_password": ["Mật khẩu mới phải có ít nhất 6 ký tự"],
    "new_password_confirmation": ["Xác nhận mật khẩu không khớp"]
  }
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/students/change-password" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "OldPassword@123",
    "new_password": "NewPassword@123",
    "new_password_confirmation": "NewPassword@123"
  }'
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