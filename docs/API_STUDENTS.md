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
      "position": "leader",
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
    "position": "leader",
    "avatar_url": "/storage/avatars/student_1_1732212345.jpg",
    "created_at": "2024-09-01T00:00:00.000000Z",
    "class": {
      "class_id": 1,
      "class_name": "DH21CNTT"
    }
  },
  "message": "Lấy thông tin sinh viên thành công"
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

### Content-Type
- `application/json`

### Request Body
```json
{
  "user_code": "210007",
  "full_name": "Nguyễn Văn A",
  "email": "sv.a@school.edu.vn",
  "phone_number": "0901234567",
  "class_id": 1,
  "status": "studying",
  "position": "member",
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
| position | enum | No | `member`, `leader`, `vice_leader`, `secretary` (mặc định: member) |
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
    "position": "member",
    "created_at": "2025-11-15T10:00:00.000000Z"
  },
  "message": "Tạo sinh viên thành công"
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
- **Admin**: Cập nhật tất cả thông tin sinh viên trong khoa mình quản lý (trừ avatar)
- **Advisor**: Cập nhật một số thông tin sinh viên trong lớp mình phụ trách (trừ avatar)
- **Student**: Chỉ cập nhật số điện thoại của chính mình

### Content-Type
- `application/json`

### Request Body (Admin)
```json
{
  "user_code": "210007",
  "full_name": "Nguyễn Văn A",
  "email": "sv.a@school.edu.vn",
  "phone_number": "0901234567",
  "class_id": 1,
  "status": "studying",
  "position": "leader"
}
```

### Request Body (Advisor)
```json
{
  "full_name": "Nguyễn Văn A",
  "phone_number": "0901234567",
  "status": "studying",
  "position": "leader"
}
```

### Request Body (Student)
```json
{
  "phone_number": "0901234567"
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
| status | enum | `studying`, `graduated`, `dropped`, `suspended` |
| position | enum | `member`, `leader`, `vice_leader`, `secretary` |

#### Advisor can update:
| Field | Type | Description |
|-------|------|-------------|
| full_name | string(100) | Họ và tên |
| phone_number | string(15) | Số điện thoại |
| status | enum | `studying`, `graduated`, `dropped`, `suspended` |
| position | enum | `member`, `leader`, `vice_leader`, `secretary` |

#### Student can update:
| Field | Type | Description |
|-------|------|-------------|
| phone_number | string(15) | Số điện thoại |

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
    "status": "studying",
    "position": "leader",
    "avatar_url": "/storage/avatars/student_1_1732212345.jpg"
  },
  "message": "Cập nhật sinh viên thành công"
}
```

### Example
```bash
# Admin update status
curl -X PUT "http://localhost:8000/api/students/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"status": "graduated"}'

# Student update phone number
curl -X PUT "http://localhost:8000/api/students/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"phone_number": "0909999999"}'
```

---

## 5. Upload Student Avatar

### Endpoint
```http
POST /api/students/{id}/avatar
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Student ID |

### Access Control
- **Admin**: Upload avatar cho sinh viên trong khoa mình quản lý
- **Advisor**: Upload avatar cho sinh viên trong lớp mình phụ trách
- **Student**: Upload avatar của chính mình

### Content-Type
- `multipart/form-data`

### Request Body (Form Data)
```
avatar: [file] (required - image file: jpeg, png, jpg, gif, max 2MB)
```

### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| avatar | file | Yes | File ảnh đại diện (jpeg, png, jpg, gif, max 2MB) |

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
    "position": "leader",
    "avatar_url": "/storage/avatars/student_1_1732212345.jpg",
    "class": {
      "class_id": 1,
      "class_name": "DH21CNTT"
    }
  },
  "message": "Upload avatar thành công"
}
```

### Error Responses
- **403 Forbidden** (Student trying to upload for others):
```json
{
  "success": false,
  "message": "Bạn chỉ có thể upload avatar của mình"
}
```

- **422 Validation Error** (Invalid file):
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "avatar": [
      "File avatar không được để trống",
      "File phải là hình ảnh",
      "File phải có định dạng: jpeg, png, jpg, gif",
      "Kích thước file không được vượt quá 2MB"
    ]
  }
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/students/1/avatar" \
  -H "Authorization: Bearer {token}" \
  -F "avatar=@/path/to/avatar.jpg"
```

### Notes
- Avatar cũ sẽ tự động bị xóa khi upload avatar mới
- Avatar được lưu vào thư mục `storage/app/public/avatars/`
- URL avatar trả về có dạng: `/storage/avatars/student_{id}_{timestamp}.{ext}`
- Để truy cập avatar qua browser, cần chạy lệnh: `php artisan storage:link`

---

## 6. Delete Student

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

### Example
```bash
curl -X DELETE "http://localhost:8000/api/students/1" \
  -H "Authorization: Bearer {token}"
```

---

## 7. Change Password

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

### Response
```json
{
  "success": true,
  "message": "Đổi mật khẩu thành công"
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

## 8. Get Class Positions

### Endpoint
```http
GET /api/classes/{classId}/positions
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| classId | integer | Yes | ID của lớp |

### Access Control
- **Admin**: Xem chức vụ của các lớp thuộc khoa mình quản lý
- **Advisor**: Xem chức vụ của các lớp mình làm cố vấn
- **Student**: Xem chức vụ của lớp mình

### Response
```json
{
  "success": true,
  "data": {
    "class_name": "DH21CNTT",
    "positions": {
      "leader": {
        "student_id": 1,
        "user_code": "210001",
        "full_name": "Nguyễn Văn A",
        "position": "leader"
      },
      "vice_leader": {
        "student_id": 2,
        "user_code": "210002",
        "full_name": "Trần Thị B",
        "position": "vice_leader"
      },
      "secretary": {
        "student_id": 3,
        "user_code": "210003",
        "full_name": "Lê Văn C",
        "position": "secretary"
      },
      "members": [
        {
          "student_id": 4,
          "user_code": "210004",
          "full_name": "Phạm Thị D",
          "position": "member"
        }
      ]
    }
  },
  "message": "Lấy danh sách chức vụ thành công"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/classes/1/positions" \
  -H "Authorization: Bearer {token}"
```

---

## 9. Reset Student Password (Admin Only)

### Endpoint
```http
POST /api/students/{id}/reset-password
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Student ID |

### Access Control
- **Admin Only**: Chỉ admin mới có quyền reset mật khẩu sinh viên
- Admin chỉ có thể reset mật khẩu sinh viên thuộc khoa mình quản lý

### Description
Reset mật khẩu sinh viên về 123456.

### Response Success
```json
{
  "success": true,
  "message": "Đã reset mật khẩu của sinh viên Nguyễn Văn Hùng (210001) về 123456 thành công"
}
```

### Error Responses

#### 403 Forbidden - Not Admin
```json
{
  "success": false,
  "message": "Chỉ admin mới có quyền reset mật khẩu"
}
```

#### 403 Forbidden - No Permission
```json
{
  "success": false,
  "message": "Bạn không có quyền reset mật khẩu sinh viên này"
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/students/1/reset-password" \
  -H "Authorization: Bearer {admin_token}"
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

### 404 Not Found
```json
{
  "success": false,
  "message": "Không tìm thấy sinh viên"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Lỗi: {error_message}"
}
```