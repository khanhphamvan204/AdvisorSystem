# API Documentation - Advisor Controller

## Base URL
```
/api/advisors
```

## Authorization
- **Header**: `Authorization: Bearer {token}`
- **Roles**: `admin`, `advisor`

---

## 1. Get List of Advisors

### Endpoint
```http
GET /api/advisors
```

### Access Control
- **Admin**: Xem cố vấn trong đơn vị mình quản lý
- **Advisor**: Chỉ xem thông tin bản thân

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| role_filter | string | No | Lọc theo vai trò: `advisor`, `admin` |
| search | string | No | Tìm kiếm theo họ tên, mã GV, email |

### Response
```json
{
  "success": true,
  "data": [
    {
      "advisor_id": 1,
      "user_code": "GV001",
      "full_name": "ThS. Trần Văn An",
      "email": "gv.an@school.edu.vn",
      "phone_number": "090111222",
      "role": "advisor",
      "avatar_url": null,
      "created_at": "2024-09-01T00:00:00.000000Z",
      "last_login": "2025-11-14T08:00:00.000000Z",
      "unit": {
        "unit_id": 1,
        "unit_name": "Khoa Công nghệ Thông tin",
        "type": "faculty"
      },
      "classes": [
        {
          "class_id": 1,
          "class_name": "DH21CNTT"
        }
      ]
    }
  ],
  "message": "Lấy danh sách cố vấn thành công"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/advisors?role_filter=advisor&search=Trần" \
  -H "Authorization: Bearer {token}"
```

---

## 2. Get Advisor Details

### Endpoint
```http
GET /api/advisors/{id}
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Advisor ID |

### Access Control
- **Admin**: Xem cố vấn trong đơn vị mình quản lý
- **Advisor**: Chỉ xem thông tin của chính mình

### Response
```json
{
  "success": true,
  "data": {
    "advisor_id": 1,
    "user_code": "GV001",
    "full_name": "ThS. Trần Văn An",
    "email": "gv.an@school.edu.vn",
    "phone_number": "090111222",
    "role": "advisor",
    "avatar_url": "/storage/avatars/advisor_1_1732212345.jpg",
    "created_at": "2024-09-01T00:00:00.000000Z",
    "unit": {
      "unit_id": 1,
      "unit_name": "Khoa Công nghệ Thông tin"
    },
    "classes": [
      {
        "class_id": 1,
        "class_name": "DH21CNTT"
      }
    ]
  },
  "message": "Lấy thông tin cố vấn thành công"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/advisors/1" \
  -H "Authorization: Bearer {token}"
```

---

## 3. Create New Advisor

### Endpoint
```http
POST /api/advisors
```

### Access Control
- **Required Role**: `admin`
- **Restriction**: Admin chỉ tạo cố vấn cho đơn vị mình quản lý

### Content-Type
- `application/json`

### Request Body
```json
{
  "user_code": "GV005",
  "full_name": "ThS. Nguyễn Văn B",
  "email": "gv.b@school.edu.vn",
  "phone_number": "090555666",
  "unit_id": 1,
  "role": "advisor",
  "password": "Password@123"
}
```

### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| user_code | string(20) | Yes | Mã giảng viên (unique) |
| full_name | string(100) | Yes | Họ và tên |
| email | string(100) | Yes | Email (unique) |
| phone_number | string(15) | No | Số điện thoại |
| unit_id | integer | No | ID đơn vị (nếu không có, dùng đơn vị của admin) |
| role | enum | Yes | `advisor` hoặc `admin` |
| password | string | No | Mật khẩu (mặc định: Password@123) |

### Response
```json
{
  "success": true,
  "data": {
    "advisor_id": 5,
    "user_code": "GV005",
    "full_name": "ThS. Nguyễn Văn B",
    "email": "gv.b@school.edu.vn",
    "phone_number": "090555666",
    "unit_id": 1,
    "role": "advisor",
    "created_at": "2025-11-15T10:00:00.000000Z"
  },
  "message": "Tạo cố vấn thành công"
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/advisors" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "user_code": "GV005",
    "full_name": "ThS. Nguyễn Văn B",
    "email": "gv.b@school.edu.vn",
    "role": "advisor"
  }'
```

---

## 4. Update Advisor Information

### Endpoint
```http
PUT /api/advisors/{id}
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Advisor ID |

### Access Control
- **Admin**: Cập nhật tất cả thông tin cố vấn trong đơn vị mình quản lý (trừ avatar)
- **Advisor**: Chỉ cập nhật số điện thoại của chính mình

### Content-Type
- `application/json`

### Request Body (Admin)
```json
{
  "user_code": "GV001",
  "full_name": "ThS. Trần Văn An",
  "email": "gv.an@school.edu.vn",
  "phone_number": "090111222",
  "unit_id": 1,
  "role": "advisor"
}
```

### Request Body (Advisor)
```json
{
  "phone_number": "090111222"
}
```

### Request Fields

#### Admin can update:
| Field | Type | Description |
|-------|------|-------------|
| user_code | string(20) | Mã giảng viên |
| full_name | string(100) | Họ và tên |
| email | string(100) | Email |
| phone_number | string(15) | Số điện thoại |
| unit_id | integer | ID đơn vị |
| role | enum | `advisor` hoặc `admin` |

#### Advisor can update:
| Field | Type | Description |
|-------|------|-------------|
| phone_number | string(15) | Số điện thoại |

### Response
```json
{
  "success": true,
  "data": {
    "advisor_id": 1,
    "user_code": "GV001",
    "full_name": "ThS. Trần Văn An",
    "email": "gv.an@school.edu.vn",
    "phone_number": "090999999",
    "role": "advisor",
    "avatar_url": "/storage/avatars/advisor_1_1732212345.jpg"
  },
  "message": "Cập nhật cố vấn thành công"
}
```

### Example
```bash
# Admin update role
curl -X PUT "http://localhost:8000/api/advisors/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"role": "admin"}'

# Advisor update phone number
curl -X PUT "http://localhost:8000/api/advisors/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"phone_number": "090999999"}'
```

---

## 5. Upload Advisor Avatar

### Endpoint
```http
POST /api/advisors/{id}/avatar
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Advisor ID |

### Access Control
- **Admin**: Upload avatar cho cố vấn trong đơn vị mình quản lý
- **Advisor**: Upload avatar của chính mình

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
    "advisor_id": 1,
    "user_code": "GV001",
    "full_name": "ThS. Trần Văn An",
    "email": "gv.an@school.edu.vn",
    "phone_number": "090111222",
    "role": "advisor",
    "avatar_url": "/storage/avatars/advisor_1_1732212345.jpg",
    "unit": {
      "unit_id": 1,
      "unit_name": "Khoa Công nghệ Thông tin"
    },
    "classes": [
      {
        "class_id": 1,
        "class_name": "DH21CNTT"
      }
    ]
  },
  "message": "Upload avatar thành công"
}
```

### Error Responses
- **403 Forbidden** (Advisor trying to upload for others):
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
curl -X POST "http://localhost:8000/api/advisors/1/avatar" \
  -H "Authorization: Bearer {token}" \
  -F "avatar=@/path/to/avatar.jpg"
```

### Notes
- Avatar cũ sẽ tự động bị xóa khi upload avatar mới
- Avatar được lưu vào thư mục `storage/app/public/avatars/`
- URL avatar trả về có dạng: `/storage/avatars/advisor_{id}_{timestamp}.{ext}`
- Để truy cập avatar qua browser, cần chạy lệnh: `php artisan storage:link`

---

## 6. Delete Advisor

### Endpoint
```http
DELETE /api/advisors/{id}
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Advisor ID |

### Access Control
- **Required Role**: `admin`
- **Restriction**: Admin chỉ xóa cố vấn trong đơn vị mình quản lý

### Response
```json
{
  "success": true,
  "message": "Xóa cố vấn thành công"
}
```

### Error Responses
- **400 Bad Request**:
```json
{
  "success": false,
  "message": "Không thể xóa cố vấn đang phụ trách lớp"
}
```

### Example
```bash
curl -X DELETE "http://localhost:8000/api/advisors/1" \
  -H "Authorization: Bearer {token}"
```

---

## 7. Get Advisor's Classes

### Endpoint
```http
GET /api/advisors/{id}/classes
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Advisor ID |

### Access Control
- **Admin**: Xem lớp của cố vấn trong đơn vị mình quản lý
- **Advisor**: Chỉ xem lớp của chính mình

### Response
```json
{
  "success": true,
  "data": [
    {
      "class_id": 1,
      "class_name": "DH21CNTT",
      "description": "Lớp đại học 2021 ngành CNTT",
      "faculty": {
        "unit_id": 1,
        "unit_name": "Khoa Công nghệ Thông tin"
      },
      "students": [
        {
          "student_id": 1,
          "user_code": "210001",
          "full_name": "Nguyễn Văn Hùng",
          "status": "studying"
        }
      ]
    }
  ],
  "message": "Lấy danh sách lớp thành công"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/advisors/1/classes" \
  -H "Authorization: Bearer {token}"
```

---

## 8. Get Advisor Statistics

### Endpoint
```http
GET /api/advisors/{id}/statistics
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Advisor ID |

### Access Control
- **Admin**: Xem thống kê cố vấn trong đơn vị mình quản lý
- **Advisor**: Chỉ xem thống kê của chính mình

### Response
```json
{
  "success": true,
  "data": {
    "total_classes": 2,
    "total_students": 50,
    "total_activities": 5,
    "total_notifications": 10,
    "total_meetings": 8,
    "classes_detail": [
      {
        "class_name": "DH21CNTT",
        "student_count": 30
      },
      {
        "class_name": "DH22CNTT",
        "student_count": 20
      }
    ]
  },
  "message": "Lấy thống kê thành công"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/advisors/1/statistics" \
  -H "Authorization: Bearer {token}"
```

---

## 9. Change Password

### Endpoint
```http
POST /api/advisors/change-password
```

### Access Control
- **Required Role**: `advisor` hoặc `admin`
- **Note**: Advisor chỉ đổi mật khẩu của chính mình

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
curl -X POST "http://localhost:8000/api/advisors/change-password" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "OldPassword@123",
    "new_password": "NewPassword@123",
    "new_password_confirmation": "NewPassword@123"
  }'
```

---

## 10. Reset Advisor Password (Admin Only)

### Endpoint
```http
POST /api/advisors/{id}/reset-password
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Advisor ID |

### Access Control
- **Admin Only**: Chỉ admin mới có quyền reset mật khẩu cố vấn
- Admin chỉ có thể reset mật khẩu cố vấn thuộc cùng đơn vị
- Admin không thể tự reset mật khẩu của chính mình

### Description
Reset mật khẩu cố vấn về 123456.

### Response Success
```json
{
  "success": true,
  "message": "Đã reset mật khẩu của cố vấn ThS. Trần Văn An (GV001) về 123456 thành công"
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
  "message": "Bạn không có quyền reset mật khẩu cố vấn này"
}
```

#### 403 Forbidden - Self Reset
```json
{
  "success": false,
  "message": "Không thể tự reset mật khẩu của chính mình"
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/advisors/2/reset-password" \
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
  "message": "Không tìm thấy cố vấn"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Lỗi: {error_message}"
}
```