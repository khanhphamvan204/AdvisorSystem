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
        "class_name": "DH21CNTT",
        "students": [
          {
            "student_id": 1,
            "user_code": "210001",
            "full_name": "Nguyễn Văn Hùng"
          }
        ]
      }
    ],
    "activities": [
      {
        "activity_id": 1,
        "title": "Hiến máu nhân đạo 2025",
        "status": "completed"
      }
    ],
    "notifications": [
      {
        "notification_id": 1,
        "title": "Thông báo Họp lớp DH21CNTT tháng 3/2025",
        "created_at": "2025-03-09T08:00:00.000000Z"
      }
    ]
  },
  "message": "Lấy thông tin cố vấn thành công"
}
```

### Error Responses
- **404 Not Found**:
```json
{
  "success": false,
  "message": "Không tìm thấy cố vấn"
}
```

- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn không có quyền xem thông tin cố vấn này"
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

### Error Responses
- **422 Validation Error**:
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "user_code": ["Mã giảng viên đã tồn tại"],
    "email": ["Email đã tồn tại"],
    "role": ["Vai trò không hợp lệ"]
  }
}
```

- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn chỉ có thể tạo cố vấn cho đơn vị mình quản lý"
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
- **Admin**: Cập nhật tất cả thông tin cố vấn trong đơn vị mình quản lý
- **Advisor**: Chỉ cập nhật một số trường của chính mình

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
  "phone_number": "090111222",
  "avatar_url": "https://example.com/avatar.jpg"
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
| avatar_url | string(255) | URL ảnh đại diện |

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
    "role": "advisor"
  },
  "message": "Cập nhật cố vấn thành công"
}
```

### Error Responses
- **403 Forbidden** (Advisor trying to update others):
```json
{
  "success": false,
  "message": "Bạn chỉ có thể cập nhật thông tin của mình"
}
```

- **403 Forbidden** (Admin trying to update outside their unit):
```json
{
  "success": false,
  "message": "Bạn không có quyền cập nhật cố vấn này"
}
```

### Example
```bash
# Admin update
curl -X PUT "http://localhost:8000/api/advisors/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "role": "admin"
  }'

# Advisor update
curl -X PUT "http://localhost:8000/api/advisors/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "090999999"
  }'
```

---

## 5. Delete Advisor

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

- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn không có quyền xóa cố vấn này"
}
```

- **404 Not Found**:
```json
{
  "success": false,
  "message": "Không tìm thấy cố vấn"
}
```

### Example
```bash
curl -X DELETE "http://localhost:8000/api/advisors/1" \
  -H "Authorization: Bearer {token}"
```

---

## 6. Get Advisor's Classes

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
          "email": "sv.hung@school.edu.vn",
          "status": "studying"
        }
      ]
    }
  ],
  "message": "Lấy danh sách lớp thành công"
}
```

### Error Responses
- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn không có quyền xem thông tin này"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/advisors/1/classes" \
  -H "Authorization: Bearer {token}"
```

---

## 7. Get Advisor Statistics

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

### Error Responses
- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn chỉ có thể xem thống kê của mình"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/advisors/1/statistics" \
  -H "Authorization: Bearer {token}"
```

---

## 8. Change Password

### Endpoint
```http
POST /api/advisors/change-password
```

### Access Control
- **Required Role**: `advisor`
- **Note**: Advisor chỉ đổi mật khẩu của chính mình

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

## 8. Reset Advisor Password (Admin Only)

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
Reset mật khẩu cố vấn về mã cố vấn (user_code) của họ.

### Response Success
```json
{
  "success": true,
  "message": "Đã reset mật khẩu của cố vấn ThS. Trần Văn An (GV001) về mã cố vấn thành công"
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

#### 404 Not Found - Advisor
```json
{
  "success": false,
  "message": "Không tìm thấy cố vấn"
}
```

#### 404 Not Found - Unit Info
```json
{
  "success": false,
  "message": "Không tìm thấy thông tin đơn vị quản lý"
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

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Lỗi: {error_message}"
}
```