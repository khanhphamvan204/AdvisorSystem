# API Documentation - Activity Management System

## Mục lục
- [1. Tổng quan](#1-tổng-quan)
- [2. Authentication](#2-authentication)
- [3. Activity APIs](#3-activity-apis)
  - [3.1. Lấy danh sách hoạt động](#31-lấy-danh-sách-hoạt-động)
  - [3.2. Xem chi tiết hoạt động](#32-xem-chi-tiết-hoạt-động)
  - [3.3. Tạo hoạt động mới](#33-tạo-hoạt-động-mới)
  - [3.4. Cập nhật hoạt động](#34-cập-nhật-hoạt-động)
  - [3.5. Xóa hoạt động](#35-xóa-hoạt-động)
  - [3.6. Xem danh sách sinh viên đã đăng ký](#36-xem-danh-sách-sinh-viên-đã-đăng-ký)
  - [3.7. Điểm danh sinh viên](#37-điểm-danh-sinh-viên)
  - [3.8. Lấy danh sách sinh viên có thể phân công](#38-lấy-danh-sách-sinh-viên-có-thể-phân-công)
  - [3.9. Phân công sinh viên](#39-phân-công-sinh-viên)
  - [3.10. Hủy phân công sinh viên](#310-hủy-phân-công-sinh-viên)
- [4. Error Codes](#4-error-codes)

---

## 1. Tổng quan

Hệ thống quản lý hoạt động ngoại khóa cho phép:
- **Advisor (CVHT)**: Tạo, quản lý hoạt động, vai trò, phân công sinh viên, điểm danh
- **Student (Sinh viên)**: Xem hoạt động của CVHT, xem trạng thái đăng ký

**Base URL**: `/api`

**Content-Type**: `application/json`

---

## 2. Authentication

Tất cả các endpoint yêu cầu JWT token trong header:

```http
Authorization: Bearer {your_jwt_token}
```

Token payload bao gồm:
```json
{
  "id": 1,
  "role": "student|advisor",
  "exp": 1234567890
}
```

---

## 3. Activity APIs

### 3.1. Lấy danh sách hoạt động

Lấy danh sách hoạt động theo quyền:
- **Student**: Chỉ thấy hoạt động của CVHT quản lý lớp mình
- **Advisor**: Chỉ thấy hoạt động do chính mình tạo

**Endpoint**: `GET /activities`

**Role**: Student, Advisor

**Query Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| from_date | datetime | No | Lọc từ ngày (format: YYYY-MM-DD) |
| to_date | datetime | No | Lọc đến ngày (format: YYYY-MM-DD) |
| per_page | integer | No | Số bản ghi mỗi trang (mặc định: 15) |

**Request Example**:
```http
GET /api/activities?from_date=2025-01-01&to_date=2025-12-31
Authorization: Bearer {token}
```

**Response Success (200)**:
```json
{
  "success": true,
  "data": [
    {
      "activity_id": 1,
      "title": "Hiến máu nhân đạo 2025",
      "general_description": "Hoạt động hiến máu cứu người",
      "location": "Sảnh A, Cơ sở 1",
      "start_time": "2025-03-15 08:00:00",
      "end_time": "2025-03-15 11:30:00",
      "status": "upcoming",
      "advisor": {
        "advisor_id": 3,
        "full_name": "ThS. Lê Hoàng Cường"
      },
      "organizer_unit": {
        "unit_id": 3,
        "unit_name": "Phòng Công tác Sinh viên"
      }
    }
  ]
}
```

**Trạng thái hoạt động**:
- `upcoming`: Sắp diễn ra
- `ongoing`: Đang diễn ra
- `completed`: Đã hoàn thành
- `cancelled`: Đã hủy

---

### 3.2. Xem chi tiết hoạt động

Xem thông tin chi tiết hoạt động bao gồm các vai trò và số lượng đăng ký.

**Endpoint**: `GET /api/activities/{activityId}`

**Role**: Student, Advisor

**Path Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| activityId | integer | Yes | ID của hoạt động |

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "activity_id": 1,
    "title": "Hiến máu nhân đạo 2025",
    "general_description": "Hoạt động hiến máu cứu người",
    "location": "Sảnh A, Cơ sở 1",
    "start_time": "2025-03-15 08:00:00",
    "end_time": "2025-03-15 11:30:00",
    "status": "upcoming",
    "advisor": {
      "advisor_id": 3,
      "full_name": "ThS. Lê Hoàng Cường",
      "email": "gv.cuong@school.edu.vn",
      "phone_number": "090333444"
    },
    "organizer_unit": {
      "unit_id": 3,
      "unit_name": "Phòng Công tác Sinh viên"
    },
    "roles": [
      {
        "activity_role_id": 1,
        "role_name": "Tham gia hiến máu",
        "description": null,
        "requirements": null,
        "points_awarded": 5,
        "point_type": "ctxh",
        "max_slots": 100,
        "registrations_count": 45,
        "available_slots": 55,
        "student_registration_status": null
      }
    ]
  }
}
```

---

### 3.3. Tạo hoạt động mới

**Endpoint**: `POST /activities`

**Role**: Advisor only

**Request Body**:
```json
{
  "title": "Workshop: Giới thiệu về AI",
  "general_description": "Workshop chuyên đề cho SV Khoa CNTT",
  "location": "Phòng Hội thảo H.201",
  "start_time": "2025-03-20 14:00:00",
  "end_time": "2025-03-20 16:00:00",
  "organizer_unit_id": 1,
  "status": "upcoming",
  "roles": [
    {
      "role_name": "Người tham dự",
      "description": "Tham dự workshop và học hỏi kiến thức",
      "requirements": "Sinh viên ngành CNTT",
      "points_awarded": 10,
      "point_type": "ren_luyen",
      "max_slots": 50
    }
  ]
}
```

**Validation Rules**:
- `title`: required, max:255
- `start_time`: required, date
- `end_time`: required, date, after:start_time
- `status`: in:upcoming,ongoing,completed,cancelled
- `roles`: required, array, min:1
- `roles.*.role_name`: required, max:100
- `roles.*.points_awarded`: required, integer, min:0
- `roles.*.point_type`: required, in:ctxh,ren_luyen

**Response Success (201)**:
```json
{
  "success": true,
  "message": "Tạo hoạt động thành công",
  "data": {
    "activity_id": 5,
    "title": "Workshop: Giới thiệu về AI",
    "roles": [...]
  }
}
```

---

### 3.4. Cập nhật hoạt động

**Endpoint**: `PUT /activities/{activityId}`

**Role**: Advisor (chỉ người tạo)

**Request Body**:
```json
{
  "title": "Workshop: AI tạo sinh (Updated)",
  "location": "Phòng H.202",
  "status": "ongoing"
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Cập nhật hoạt động thành công",
  "data": {...}
}
```

**Response Error (403)**:
```json
{
  "success": false,
  "message": "Bạn không có quyền cập nhật hoạt động này"
}
```

---

### 3.5. Xóa hoạt động

**Endpoint**: `DELETE /activities/{activityId}`

**Role**: Advisor (chỉ người tạo)

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Xóa hoạt động thành công"
}
```

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Không thể xóa hoạt động đã hoàn thành"
}
```

---

### 3.6. Xem danh sách sinh viên đã đăng ký

**Endpoint**: `GET /activities/{activityId}/registrations`

**Role**: Advisor (chỉ người tạo)

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "activity": {...},
    "total_registrations": 45,
    "registrations": [
      {
        "registration_id": 1,
        "student": {
          "student_id": 1,
          "user_code": "210001",
          "full_name": "Nguyễn Văn Hùng",
          "email": "sv.hung@school.edu.vn",
          "phone_number": "091122334"
        },
        "role_name": "Tham gia hiến máu",
        "points_awarded": 5,
        "point_type": "ctxh",
        "status": "registered",
        "registration_time": "2025-03-01 10:00:00"
      }
    ]
  }
}
```

---

### 3.7. Cập nhật điểm danh

**Endpoint**: `POST /activities/{activityId}/attendance`

**Role**: Advisor (chỉ người tạo)

**Request Body**:
```json
{
  "attendances": [
    {
      "registration_id": 1,
      "status": "attended"
    },
    {
      "registration_id": 2,
      "status": "absent"
    }
  ]
}
```

**Validation Rules**:
- `attendances`: required, array, min:1
- `attendances.*.registration_id`: required, exists:Activity_Registrations
- `attendances.*.status`: required, in:attended,absent

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Cập nhật điểm danh thành công",
  "data": {
    "total_updated": 2,
    "registrations": [...]
  }
}
```

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Hoạt động chưa diễn ra, không thể điểm danh"
}
```

---

## 4. Activity Role APIs

### 4.1. Lấy danh sách vai trò của hoạt động

**Endpoint**: `GET /activities/{activityId}/roles`

**Role**: Student, Advisor

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "activity": {...},
    "roles": [
      {
        "activity_role_id": 1,
        "role_name": "Tham gia hiến máu",
        "description": null,
        "requirements": null,
        "points_awarded": 5,
        "point_type": "ctxh",
        "max_slots": 100,
        "registrations_count": 45,
        "available_slots": 55
      }
    ]
  }
}
```

---

### 4.2. Xem chi tiết vai trò

**Endpoint**: `GET /activities/{activityId}/roles/{roleId}`

**Role**: Student, Advisor

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "activity_role_id": 1,
    "activity": {...},
    "role_name": "Tham gia hiến máu",
    "description": "Đóng góp máu cứu người",
    "requirements": "Khỏe mạnh, trên 18 tuổi",
    "points_awarded": 5,
    "point_type": "ctxh",
    "max_slots": 100,
    "registrations_count": 45,
    "available_slots": 55
  }
}
```

---

### 4.3. Thêm vai trò vào hoạt động

**Endpoint**: `POST /activities/{activityId}/roles`

**Role**: Advisor (chỉ người tạo hoạt động)

**Request Body**:
```json
{
  "role_name": "Tình nguyện viên hỗ trợ",
  "description": "Hỗ trợ tổ chức sự kiện",
  "requirements": "Nhiệt tình, có kinh nghiệm",
  "points_awarded": 10,
  "point_type": "ctxh",
  "max_slots": 10
}
```

**Response Success (201)**:
```json
{
  "success": true,
  "message": "Thêm vai trò thành công",
  "data": {...}
}
```

---

### 4.4. Cập nhật vai trò

**Endpoint**: `PUT /activities/{activityId}/roles/{roleId}`

**Role**: Advisor (chỉ người tạo hoạt động)

**Request Body**:
```json
{
  "role_name": "Tình nguyện viên cao cấp",
  "points_awarded": 15,
  "max_slots": 15
}
```

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Không thể giảm số lượng slot xuống dưới 10 (số sinh viên đã đăng ký)"
}
```

---

### 4.5. Xóa vai trò

**Endpoint**: `DELETE /activities/{activityId}/roles/{roleId}`

**Role**: Advisor (chỉ người tạo hoạt động)

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Không thể xóa vai trò đã có 5 sinh viên đăng ký"
}
```

---

### 4.6. Xem danh sách sinh viên đăng ký vai trò

**Endpoint**: `GET /activities/{activityId}/roles/{roleId}/registrations`

**Role**: Advisor (chỉ người tạo hoạt động)

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "role": {...},
    "total_registrations": 5,
    "registrations": [
      {
        "registration_id": 1,
        "student": {...},
        "status": "registered",
        "registration_time": "2025-03-01 10:00:00",
        "points_awarded": 10,
        "point_type": "ctxh"
      }
    ]
  }
}
```

---

## 5. Activity Registration APIs

### 5.1. Đăng ký tham gia hoạt động

**Endpoint**: `POST /activity-registrations/register`

**Role**: Student only

**Request Body**:
```json
{
  "activity_role_id": 1
}
```

**Response Success (201)**:
```json
{
  "success": true,
  "message": "Đăng ký thành công",
  "data": {
    "registration_id": 10,
    "activity_role_id": 1,
    "student_id": 5,
    "status": "registered",
    "registration_time": "2025-03-05 14:30:00"
  }
}
```

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Vai trò này đã hết chỗ"
}
```

```json
{
  "success": false,
  "message": "Bạn đã đăng ký vai trò này rồi"
}
```

```json
{
  "success": false,
  "message": "Hoạt động đã kết thúc hoặc bị hủy"
}
```

---

### 5.2. Xem danh sách hoạt động đã đăng ký

**Endpoint**: `GET /activity-registrations/my-registrations`

**Role**: Student only

**Response Success (200)**:
```json
{
  "success": true,
  "data": [
    {
      "registration_id": 1,
      "activity_title": "Hiến máu nhân đạo 2025",
      "role_name": "Tham gia hiến máu",
      "points_awarded": 5,
      "point_type": "ctxh",
      "activity_start_time": "2025-03-15 08:00:00",
      "activity_location": "Sảnh A, Cơ sở 1",
      "status": "registered",
      "registration_time": "2025-03-01 10:00:00",
      "advisor_name": "ThS. Lê Hoàng Cường"
    }
  ]
}
```

---

### 5.3. Tạo yêu cầu hủy đăng ký

**Endpoint**: `POST /activity-registrations/cancel`

**Role**: Student only

**Request Body**:
```json
{
  "registration_id": 1,
  "reason": "Em bị trùng lịch thi giữa kỳ môn học lại. Em xin phép hủy ạ."
}
```

**Validation Rules**:
- `registration_id`: required, exists:Activity_Registrations
- `reason`: required, max:500

**Response Success (201)**:
```json
{
  "success": true,
  "message": "Gửi yêu cầu hủy thành công",
  "data": {
    "request_id": 1,
    "registration_id": 1,
    "reason": "Em bị trùng lịch thi...",
    "status": "pending",
    "requested_at": "2025-03-05 10:00:00"
  }
}
```

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Chỉ có thể hủy đăng ký ở trạng thái 'đã đăng ký'"
}
```

```json
{
  "success": false,
  "message": "Yêu cầu hủy đã được gửi trước đó"
}
```

---

### 5.4. Xem danh sách yêu cầu hủy của mình

**Endpoint**: `GET /activity-registrations/my-cancellation-requests`

**Role**: Student only

**Response Success (200)**:
```json
{
  "success": true,
  "data": [
    {
      "request_id": 1,
      "registration_id": 4,
      "activity_title": "Workshop: Giới thiệu về AI",
      "role_name": "Người tham dự",
      "reason": "Em bị trùng lịch thi giữa kỳ...",
      "status": "pending",
      "requested_at": "2025-03-05 10:00:00",
      "advisor_name": "ThS. Trần Văn An"
    }
  ]
}
```

---

## 6. Cancellation Request APIs

### 6.1. Xem danh sách yêu cầu hủy của hoạt động

**Endpoint**: `GET /activities/{activityId}/cancellation-requests`

**Role**: Advisor (chỉ người tạo hoạt động)

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "activity": {...},
    "total_requests": 2,
    "requests": [
      {
        "request_id": 1,
        "registration_id": 4,
        "student": {
          "student_id": 2,
          "user_code": "210002",
          "full_name": "Trần Thị Thu Cẩm",
          "email": "sv.cam@school.edu.vn",
          "phone_number": "091234567"
        },
        "role_name": "Người tham dự",
        "reason": "Em bị trùng lịch thi giữa kỳ...",
        "status": "pending",
        "requested_at": "2025-03-05 10:00:00"
      }
    ]
  }
}
```

---

### 6.2. Duyệt/từ chối yêu cầu hủy

**Endpoint**: `PATCH /activities/{activityId}/cancellation-requests/{requestId}`

**Role**: Advisor (chỉ người tạo hoạt động)

**Request Body**:
```json
{
  "status": "approved"
}
```

**Validation Rules**:
- `status`: required, in:approved,rejected

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Đã duyệt yêu cầu hủy",
  "data": {
    "request_id": 1,
    "status": "approved"
  }
}
```

**Note**: Khi status = "approved", trạng thái của registration sẽ tự động chuyển sang "cancelled"

---

## 7. Error Codes

### Common HTTP Status Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request thành công |
| 201 | Created | Tạo resource thành công |
| 400 | Bad Request | Dữ liệu không hợp lệ hoặc vi phạm business logic |
| 401 | Unauthorized | Token không hợp lệ hoặc hết hạn |
| 403 | Forbidden | Không có quyền truy cập |
| 404 | Not Found | Resource không tồn tại |
| 422 | Unprocessable Entity | Validation error |
| 500 | Internal Server Error | Lỗi server |

### Error Response Format

```json
{
  "success": false,
  "message": "Mô tả lỗi",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

### Common Error Messages

**Authentication Errors**:
- "Token không được cung cấp"
- "Token đã hết hạn"
- "Token không hợp lệ"

**Authorization Errors**:
- "Bạn không có quyền truy cập"
- "Bạn không có quyền cập nhật hoạt động này"
- "Bạn không có quyền xóa hoạt động này"

**Validation Errors**:
- "Dữ liệu không hợp lệ"
- "Trường {field} là bắt buộc"
- "Trường {field} phải là số nguyên"

**Business Logic Errors**:
- "Hoạt động không tồn tại"
- "Vai trò này đã hết chỗ"
- "Bạn đã đăng ký vai trò này rồi"
- "Không thể xóa hoạt động đã hoàn thành"
- "Hoạt động chưa diễn ra, không thể điểm danh"

---

## Notes

1. **Date Format**: Tất cả date/datetime đều sử dụng format `YYYY-MM-DD HH:MM:SS`
2. **Point Types**: 
   - `ctxh`: Điểm cộng tác xã hội
   - `ren_luyen`: Điểm rèn luyện
3. **Activity Status**: `upcoming`, `ongoing`, `completed`, `cancelled`
4. **Registration Status**: `registered`, `attended`, `absent`, `cancelled`
5. **Cancellation Request Status**: `pending`, `approved`, `rejected`

---

## Postman Collection

Để test API, bạn có thể import Postman collection tại: [Link to collection]

**Environment Variables**:
```json
{
  "base_url": "http://your-domain.com/api",
  "token": "your_jwt_token_here"
}
```