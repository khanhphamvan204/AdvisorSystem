# API Documentation - Hệ thống Quản lý Hoạt động

## Mục lục
1. [Tổng quan](#tổng-quan)
2. [Authentication](#authentication)
3. [Activity Management](#activity-management)
4. [Activity Roles Management](#activity-roles-management)
5. [Activity Registration (Student)](#activity-registration-student)
6. [Cancellation Requests](#cancellation-requests)
7. [Error Codes](#error-codes)

---

## Tổng quan

### Base URL
```
https://api.example.com/api
```

### Headers yêu cầu
```json
{
  "Authorization": "Bearer {jwt_token}",
  "Content-Type": "application/json",
  "Accept": "application/json"
}
```

### Response Format
Tất cả response đều có format chuẩn:
```json
{
  "success": true|false,
  "message": "Message mô tả",
  "data": {...},
  "errors": {...} // chỉ có khi validation fail
}
```

---

## Authentication

Hệ thống sử dụng JWT Authentication. Token được lưu trong custom claims:
- `id`: ID của user (student_id hoặc advisor_id)
- `role`: Vai trò (student, advisor, admin)
- `name`: Tên đầy đủ

Middleware kiểm tra:
- `auth.api`: Xác thực token
- `check_role:role1,role2`: Kiểm tra quyền truy cập

---

## Activity Management

### 1. Lấy danh sách hoạt động

**Endpoint:** `GET /activities`

**Roles:** `student`, `advisor`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `from_date` | date | No | Lọc từ ngày (YYYY-MM-DD) |
| `to_date` | date | No | Lọc đến ngày (YYYY-MM-DD) |
| `status` | string | No | Lọc theo trạng thái: `upcoming`, `ongoing`, `completed`, `cancelled` |

**Logic phân quyền:**
- **Student:** Chỉ thấy hoạt động được gán cho lớp mình
- **Advisor:** Chỉ thấy hoạt động do mình tạo

**Response Success (200):**
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
        "status": "completed",
        "advisor": {
          "advisor_id": 3,
          "full_name": "ThS. Lê Hoàng Cường"
        },
        "organizer_unit": {
          "unit_id": 3,
          "unit_name": "Phòng Công tác Sinh viên"
        },
        "classes": [
          {
            "class_id": 1,
            "class_name": "DH21CNTT"
          }
        ]
      }
    ]
}
```

---

### 2. Xem chi tiết hoạt động

**Endpoint:** `GET /activities/{activityId}`

**Roles:** `student`, `advisor`

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Logic phân quyền:**
- **Student:** Chỉ xem được hoạt động của lớp mình
- **Advisor:** Chỉ xem được hoạt động mình tạo

**Response Success (200) - Student:**
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
    "status": "completed",
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
    "classes": [
      {
        "class_id": 1,
        "class_name": "DH21CNTT"
      }
    ],
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
        "student_registration_status": "attended",
        "student_registration_id": 123
      }
    ]
  }
}
```

**Response Success (200) - Advisor:**
```json
{
  "success": true,
  "data": {
    "activity_id": 1,
    "title": "Hiến máu nhân đạo 2025",
    // ... các trường giống student
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
        // Không có student_registration_status và student_registration_id
      }
    ]
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem hoạt động này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Hoạt động không tồn tại"
}
```

---

### 3. Tạo hoạt động mới

**Endpoint:** `POST /activities`

**Role:** `advisor`

**Request Body:**
```json
{
  "title": "Workshop: Giới thiệu về AI tạo sinh",
  "general_description": "Workshop chuyên đề cho SV Khoa CNTT",
  "location": "Phòng Hội thảo H.201",
  "start_time": "2025-03-20 14:00:00",
  "end_time": "2025-03-20 16:00:00",
  "organizer_unit_id": 1,
  "status": "upcoming",
  "class_ids": [1, 2],
  "roles": [
    {
      "role_name": "Người tham dự",
      "description": "Tham gia buổi workshop",
      "requirements": "Có kiến thức cơ bản về lập trình",
      "points_awarded": 10,
      "point_type": "ren_luyen",
      "max_slots": 50
    },
    {
      "role_name": "Tình nguyện viên hỗ trợ",
      "description": "Hỗ trợ tổ chức sự kiện",
      "requirements": null,
      "points_awarded": 15,
      "point_type": "ctxh",
      "max_slots": 5
    }
  ]
}
```

**Validation Rules:**
| Field | Type | Rules |
|-------|------|-------|
| `title` | string | required, max:255 |
| `general_description` | string | nullable |
| `location` | string | nullable, max:255 |
| `start_time` | datetime | required, after:now |
| `end_time` | datetime | required, after:start_time |
| `organizer_unit_id` | integer | nullable, exists:Units,unit_id |
| `status` | string | nullable, in:upcoming,ongoing,completed,cancelled |
| `class_ids` | array | required, min:1 |
| `class_ids.*` | integer | exists:Classes,class_id |
| `roles` | array | required, min:1 |
| `roles.*.role_name` | string | required, max:100 |
| `roles.*.description` | string | nullable |
| `roles.*.requirements` | string | nullable |
| `roles.*.points_awarded` | integer | required, min:0 |
| `roles.*.point_type` | string | required, in:ctxh,ren_luyen |
| `roles.*.max_slots` | integer | nullable, min:1 |

**Logic kiểm tra:**
- Advisor chỉ được gán hoạt động cho các lớp mình quản lý
- `start_time` phải sau thời điểm hiện tại

**Response Success (201):**
```json
{
  "success": true,
  "message": "Tạo hoạt động thành công",
  "data": {
    "activity_id": 5,
    "advisor_id": 1,
    "organizer_unit_id": 1,
    "title": "Workshop: Giới thiệu về AI tạo sinh",
    "general_description": "Workshop chuyên đề cho SV Khoa CNTT",
    "location": "Phòng Hội thảo H.201",
    "start_time": "2025-03-20 14:00:00",
    "end_time": "2025-03-20 16:00:00",
    "status": "upcoming",
    "roles": [
      {
        "activity_role_id": 10,
        "activity_id": 5,
        "role_name": "Người tham dự",
        "description": "Tham gia buổi workshop",
        "requirements": "Có kiến thức cơ bản về lập trình",
        "points_awarded": 10,
        "point_type": "ren_luyen",
        "max_slots": 50
      }
    ],
    "classes": [
      {
        "class_id": 1,
        "class_name": "DH21CNTT"
      }
    ]
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ được gán hoạt động cho các lớp mình quản lý",
  "invalid_class_ids": [3, 4]
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "start_time": [
      "The start time field must be a date after now."
    ],
    "class_ids": [
      "The class ids field is required."
    ]
  }
}
```

---

### 4. Cập nhật hoạt động

**Endpoint:** `PUT /activities/{activityId}`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Request Body:**
```json
{
  "title": "Workshop: AI tạo sinh (Cập nhật)",
  "location": "Phòng H.202",
  "status": "ongoing",
  "class_ids": [1, 2, 3]
}
```

**Validation Rules:**
| Field | Type | Rules |
|-------|------|-------|
| `title` | string | sometimes required, max:255 |
| `general_description` | string | nullable |
| `location` | string | nullable, max:255 |
| `start_time` | datetime | sometimes required |
| `end_time` | datetime | sometimes required, after:start_time |
| `organizer_unit_id` | integer | nullable, exists:Units,unit_id |
| `status` | string | nullable, in:upcoming,ongoing,completed,cancelled |
| `class_ids` | array | sometimes, min:1 |
| `class_ids.*` | integer | exists:Classes,class_id |

**Logic kiểm tra:**
- Chỉ người tạo hoạt động mới được cập nhật
- Nếu có `class_ids`, phải là các lớp advisor đang quản lý

**Response Success (200):**
```json
{
  "success": true,
  "message": "Cập nhật hoạt động thành công",
  "data": {
    "activity_id": 5,
    "title": "Workshop: AI tạo sinh (Cập nhật)",
    "location": "Phòng H.202",
    "status": "ongoing",
    "classes": [
      {
        "class_id": 1,
        "class_name": "DH21CNTT"
      },
      {
        "class_id": 2,
        "class_name": "DH22KT"
      }
    ]
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền cập nhật hoạt động này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Hoạt động không tồn tại"
}
```

---

### 5. Xóa hoạt động

**Endpoint:** `DELETE /activities/{activityId}`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Logic kiểm tra:**
- Chỉ người tạo hoạt động mới được xóa
- Không cho phép xóa hoạt động đã `completed`

**Response Success (200):**
```json
{
  "success": true,
  "message": "Xóa hoạt động thành công"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể xóa hoạt động đã hoàn thành"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xóa hoạt động này"
}
```

---

### 6. Xem danh sách sinh viên đã đăng ký

**Endpoint:** `GET /activities/{activityId}/registrations`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "activity": {
      "activity_id": 1,
      "title": "Hiến máu nhân đạo 2025",
      "status": "completed",
      "start_time": "2025-03-15 08:00:00",
      "end_time": "2025-03-15 11:30:00"
    },
    "summary": {
      "total_registrations": 47,
      "by_status": {
        "registered": 2,
        "attended": 42,
        "absent": 2,
        "cancelled": 1
      }
    },
    "registrations": [
      {
        "registration_id": 1,
        "student": {
          "student_id": 1,
          "user_code": "210001",
          "full_name": "Nguyễn Văn Hùng",
          "email": "sv.hung@school.edu.vn",
          "phone_number": "091122334",
          "class_name": "DH21CNTT"
        },
        "role_name": "Tham gia hiến máu",
        "points_awarded": 5,
        "point_type": "ctxh",
        "status": "attended",
        "registration_time": "2025-03-10 10:30:00"
      }
    ]
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem danh sách này"
}
```

---

### 7. Cập nhật điểm danh

**Endpoint:** `POST /activities/{activityId}/attendance`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Request Body:**
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
    },
    {
      "registration_id": 3,
      "status": "attended"
    }
  ]
}
```

**Validation Rules:**
| Field | Type | Rules |
|-------|------|-------|
| `attendances` | array | required, min:1 |
| `attendances.*.registration_id` | integer | required, exists:Activity_Registrations,registration_id |
| `attendances.*.status` | string | required, in:attended,absent |

**Logic kiểm tra:**
- Chỉ người tạo hoạt động mới được điểm danh
- Hoạt động không được ở trạng thái `cancelled` hoặc `upcoming`
- Chỉ điểm danh cho các registration có status: `registered`, `attended`, `absent`

**Response Success (200):**
```json
{
  "success": true,
  "message": "Cập nhật điểm danh thành công",
  "data": {
    "total_updated": 3,
    "total_skipped": 0,
    "updated": [
      {
        "registration_id": 1,
        "student_name": "Nguyễn Văn Hùng",
        "student_code": "210001",
        "old_status": "registered",
        "new_status": "attended"
      },
      {
        "registration_id": 2,
        "student_name": "Trần Thị Thu Cẩm",
        "student_code": "210002",
        "old_status": "registered",
        "new_status": "absent"
      },
      {
        "registration_id": 3,
        "student_name": "Lê Văn Dũng",
        "student_code": "220001",
        "old_status": "registered",
        "new_status": "attended"
      }
    ],
    "skipped": []
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể điểm danh cho hoạt động chưa diễn ra hoặc đã bị hủy"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền điểm danh cho hoạt động này"
}
```

---

### 8. Xem danh sách sinh viên có thể phân công

**Endpoint:** `GET /activities/{activityId}/available-students`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Logic:**
- Lấy tất cả sinh viên trong các lớp được gán hoạt động
- Kiểm tra sinh viên đã đăng ký chưa
- Tính điểm rèn luyện và điểm CTXH của học kỳ gần nhất

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "activity": {
      "activity_id": 1,
      "title": "Hiến máu nhân đạo 2025",
      "status": "upcoming"
    },
    "assigned_classes": [
      {
        "class_id": 1,
        "class_name": "DH21CNTT"
      }
    ],
    "current_semester": {
      "semester_id": 2,
      "semester_name": "Học kỳ 2",
      "academic_year": "2024-2025"
    },
    "summary": {
      "total_students": 50,
      "available_count": 45,
      "unavailable_count": 5
    },
    "available_students": [
      {
        "student_id": 5,
        "user_code": "210003",
        "full_name": "Phan Thanh Bình",
        "email": "sv.binh@school.edu.vn",
        "phone_number": "094455667",
        "class_name": "DH21CNTT",
        "training_point": 85,
        "social_point": 15,
        "current_semester": "Học kỳ 2",
        "status": "studying",
        "can_assign": true,
        "reason_cannot_assign": null,
        "current_registration": null
      }
    ],
    "unavailable_students": [
      {
        "student_id": 1,
        "user_code": "210001",
        "full_name": "Nguyễn Văn Hùng",
        "email": "sv.hung@school.edu.vn",
        "phone_number": "091122334",
        "class_name": "DH21CNTT",
        "training_point": 90,
        "social_point": 20,
        "current_semester": "Học kỳ 2",
        "status": "studying",
        "can_assign": false,
        "reason_cannot_assign": "Đã đăng ký vai trò 'Tham gia hiến máu' (Trạng thái: registered)",
        "current_registration": {
          "registration_id": 1,
          "role_name": "Tham gia hiến máu",
          "registration_status": "registered"
        }
      }
    ]
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể xem danh sách sinh viên cho hoạt động đã hoàn thành hoặc bị hủy"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem danh sách này"
}
```

---

### 9. Phân công sinh viên tham gia hoạt động

**Endpoint:** `POST /activities/{activityId}/assign-students`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Request Body:**
```json
{
  "assignments": [
    {
      "student_id": 5,
      "activity_role_id": 1
    },
    {
      "student_id": 6,
      "activity_role_id": 1
    },
    {
      "student_id": 7,
      "activity_role_id": 2
    }
  ]
}
```

**Validation Rules:**
| Field | Type | Rules |
|-------|------|-------|
| `assignments` | array | required, min:1 |
| `assignments.*.student_id` | integer | required, exists:Students,student_id |
| `assignments.*.activity_role_id` | integer | required, exists:Activity_Roles,activity_role_id |

**Logic kiểm tra:**
- Chỉ người tạo hoạt động mới được phân công
- Hoạt động không được ở trạng thái `completed` hoặc `cancelled`
- Vai trò phải thuộc hoạt động này
- Sinh viên phải thuộc các lớp được gán hoạt động
- Sinh viên chưa đăng ký vai trò nào trong hoạt động này
- Vai trò còn slot trống (nếu có giới hạn)

**Response Success (200):**
```json
{
  "success": true,
  "message": "Phân công thành công 3 sinh viên",
  "data": {
    "total_assigned": 3,
    "total_skipped": 0,
    "assigned": [
      {
        "registration_id": 10,
        "student_id": 5,
        "student_code": "210003",
        "student_name": "Phan Thanh Bình",
        "role_name": "Tham gia hiến máu",
        "points_awarded": 5,
        "point_type": "ctxh"
      },
      {
        "registration_id": 11,
        "student_id": 6,
        "student_code": "210004",
        "student_name": "Võ Thị Kim Anh",
        "role_name": "Tham gia hiến máu",
        "points_awarded": 5,
        "point_type": "ctxh"
      },
      {
        "registration_id": 12,
        "student_id": 7,
        "student_code": "210005",
        "student_name": "Trịnh Bảo Quốc",
        "role_name": "Tình nguyện viên hỗ trợ",
        "points_awarded": 10,
        "point_type": "ctxh"
      }
    ],
    "skipped": []
  }
}
```

**Response Success với skipped (200):**
```json
{
  "success": true,
  "message": "Phân công thành công 2 sinh viên, bỏ qua 1",
  "data": {
    "total_assigned": 2,
    "total_skipped": 1,
    "assigned": [
      {
        "registration_id": 10,
        "student_id": 5,
        "student_code": "210003",
        "student_name": "Phan Thanh Bình",
        "role_name": "Tham gia hiến máu",
        "points_awarded": 5,
        "point_type": "ctxh"
      }
    ],
    "skipped": [
      {
        "student_id": 1,
        "student_name": "Nguyễn Văn Hùng",
        "reason": "Sinh viên đã đăng ký một vai trò khác trong hoạt động này"
      }
    ]
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể phân công cho hoạt động đã hoàn thành hoặc bị hủy"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền phân công cho hoạt động này"
}
```

---

### 10. Hủy phân công sinh viên

**Endpoint:** `DELETE /activities/{activityId}/assignments/{registrationId}`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |
| `registrationId` | integer | Yes | ID của đăng ký |

**Logic kiểm tra:**
- Chỉ người tạo hoạt động mới được hủy phân công
- Đăng ký phải thuộc hoạt động này
- Chỉ hủy được với status = `registered`

**Response Success (200):**
```json
{
  "success": true,
  "message": "Hủy phân công thành công",
  "data": {
    "student_name": "Phan Thanh Bình",
    "student_code": "210003",
    "role_name": "Tham gia hiến máu"
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Chỉ có thể hủy phân công ở trạng thái \"đã đăng ký\" (status: registered). Trạng thái hiện tại: attended"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền hủy phân công này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Đăng ký không tồn tại"
}
```

---

## Activity Roles Management

### 1. Lấy danh sách vai trò của hoạt động

**Endpoint:** `GET /activities/{activityId}/roles`

**Roles:** `student`, `advisor`

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Logic phân quyền:**
- **Student:** Chỉ xem được vai trò của hoạt động dành cho lớp mình
- **Advisor:** Chỉ xem được vai trò của hoạt động mình tạo

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "activity": {
      "activity_id": 1,
      "title": "Hiến máu nhân đạo 2025",
      "status": "completed",
      "start_time": "2025-03-15 08:00:00",
      "end_time": "2025-03-15 11:30:00"
    },
    "total_roles": 2,
    "roles": [
      {
        "activity_role_id": 1,
        "activity_id": 1,
        "role_name": "Tham gia hiến máu",
        "description": null,
        "requirements": null,
        "points_awarded": 5,
        "point_type": "ctxh",
        "max_slots": 100,
        "active_registrations_count": 45,
        "available_slots": 55
      },
      {
        "activity_role_id": 2,
        "activity_id": 1,
        "role_name": "Tình nguyện viên hỗ trợ",
        "description": "Hỗ trợ tổ chức sự kiện",
        "requirements": null,
        "points_awarded": 10,
        "point_type": "ctxh",
        "max_slots": 10,
        "active_registrations_count": 8,
        "available_slots": 2
      }
    ]
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem hoạt động này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Hoạt động không tồn tại"
}
```

---

### 2. Xem chi tiết vai trò

**Endpoint:** `GET /activities/{activityId}/roles/{roleId}`

**Roles:** `student`, `advisor`

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |
| `roleId` | integer | Yes | ID của vai trò |

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "activity_role_id": 1,
    "activity_id": 1,
    "role_name": "Tham gia hiến máu",
    "description": null,
    "requirements": null,
    "points_awarded": 5,
    "point_type": "ctxh",
    "max_slots": 100,
    "active_registrations_count": 45,
    "available_slots": 55,
    "activity": {
      "activity_id": 1,
      "title": "Hiến máu nhân đạo 2025",
      "status": "completed"
    }
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem vai trò này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Vai trò không tồn tại"
}
```

---

### 3. Thêm vai trò vào hoạt động

**Endpoint:** `POST /activities/{activityId}/roles`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Request Body:**
```json
{
  "role_name": "MC dẫn chương trình",
  "description": "Dẫn chương trình sự kiện",
  "requirements": "Có kỹ năng giao tiếp tốt",
  "points_awarded": 15,
  "point_type": "ren_luyen",
  "max_slots": 2
}
```

**Validation Rules:**
| Field | Type | Rules |
|-------|------|-------|
| `role_name` | string | required, max:100 |
| `description` | string | nullable, max:1000 |
| `requirements` | string | nullable, max:1000 |
| `points_awarded` | integer | required, min:0, max:100 |
| `point_type` | string | required, in:ctxh,ren_luyen |
| `max_slots` | integer | nullable, min:1, max:1000 |

**Logic kiểm tra:**
- Chỉ người tạo hoạt động mới được thêm vai trò
- Không trùng tên vai trò trong cùng hoạt động

**Response Success (201):**
```json
{
  "success": true,
  "message": "Thêm vai trò thành công",
  "data": {
    "activity_role_id": 15,
    "activity_id": 1,
    "role_name": "MC dẫn chương trình",
    "description": "Dẫn chương trình sự kiện",
    "requirements": "Có kỹ năng giao tiếp tốt",
    "points_awarded": 15,
    "point_type": "ren_luyen",
    "max_slots": 2
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Vai trò với tên này đã tồn tại trong hoạt động"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền thêm vai trò cho hoạt động này"
}
```

---

### 4. Cập nhật vai trò

**Endpoint:** `PUT /activities/{activityId}/roles/{roleId}`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |
| `roleId` | integer | Yes | ID của vai trò |

**Request Body:**
```json
{
  "role_name": "MC chính thức",
  "points_awarded": 20,
  "max_slots": 3
}
```

**Validation Rules:**
| Field | Type | Rules |
|-------|------|-------|
| `role_name` | string | sometimes required, max:100 |
| `description` | string | nullable, max:1000 |
| `requirements` | string | nullable, max:1000 |
| `points_awarded` | integer | sometimes required, min:0, max:100 |
| `point_type` | string | sometimes required, in:ctxh,ren_luyen |
| `max_slots` | integer | nullable, min:1, max:1000 |

**Logic kiểm tra:**
- Chỉ người tạo hoạt động mới được cập nhật
- Nếu đổi tên, không được trùng với vai trò khác
- `max_slots` không được nhỏ hơn số sinh viên đã đăng ký

**Response Success (200):**
```json
{
  "success": true,
  "message": "Cập nhật vai trò thành công",
  "data": {
    "activity_role_id": 15,
    "activity_id": 1,
    "role_name": "MC chính thức",
    "description": "Dẫn chương trình sự kiện",
    "requirements": "Có kỹ năng giao tiếp tốt",
    "points_awarded": 20,
    "point_type": "ren_luyen",
    "max_slots": 3
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể giảm số lượng slot xuống dưới 5 (số sinh viên đã đăng ký)"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền cập nhật vai trò này"
}
```

---

### 5. Xóa vai trò

**Endpoint:** `DELETE /activities/{activityId}/roles/{roleId}`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |
| `roleId` | integer | Yes | ID của vai trò |

**Logic kiểm tra:**
- Chỉ người tạo hoạt động mới được xóa vai trò
- Xóa cascade: tất cả registrations của vai trò này sẽ bị xóa

**Response Success (200):**
```json
{
  "success": true,
  "message": "Xóa vai trò thành công"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xóa vai trò này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Vai trò không tồn tại"
}
```

---

### 6. Xem danh sách sinh viên đăng ký vai trò

**Endpoint:** `GET /activities/{activityId}/roles/{roleId}/registrations`

**Role:** `advisor` (chỉ người tạo)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |
| `roleId` | integer | Yes | ID của vai trò |

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "role": {
      "activity_role_id": 1,
      "role_name": "Tham gia hiến máu",
      "points_awarded": 5,
      "point_type": "ctxh",
      "max_slots": 100
    },
    "summary": {
      "total_registrations": 47,
      "by_status": {
        "registered": 2,
        "attended": 42,
        "absent": 2,
        "cancelled": 1
      }
    },
    "registrations": [
      {
        "registration_id": 1,
        "student": {
          "student_id": 1,
          "user_code": "210001",
          "full_name": "Nguyễn Văn Hùng",
          "email": "sv.hung@school.edu.vn",
          "phone_number": "091122334",
          "class_name": "DH21CNTT"
        },
        "status": "attended",
        "registration_time": "2025-03-10 10:30:00",
        "points_awarded": 5,
        "point_type": "ctxh"
      }
    ]
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem danh sách này"
}
```

---

## Activity Registration (Student)

### 1. Đăng ký tham gia hoạt động

**Endpoint:** `POST /activity-registrations/register`

**Role:** `student`

**Request Body:**
```json
{
  "activity_role_id": 1
}
```

**Validation Rules:**
| Field | Type | Rules |
|-------|------|-------|
| `activity_role_id` | integer | required, exists:Activity_Roles,activity_role_id |

**Logic kiểm tra:**
- Hoạt động phải dành cho lớp của sinh viên
- Hoạt động không được ở trạng thái `completed` hoặc `cancelled`
- Hoạt động chưa bắt đầu (start_time > now)
- Sinh viên chưa đăng ký vai trò nào trong hoạt động này
- Vai trò còn slot trống (nếu có giới hạn)

**Response Success (201):**
```json
{
  "success": true,
  "message": "Đăng ký thành công",
  "data": {
    "registration_id": 50,
    "activity_title": "Hiến máu nhân đạo 2025",
    "role_name": "Tham gia hiến máu",
    "points_awarded": 5,
    "point_type": "ctxh",
    "activity_start_time": "2025-03-15 08:00:00",
    "activity_location": "Sảnh A, Cơ sở 1",
    "status": "registered",
    "registration_time": "2025-03-10 14:30:00"
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Bạn đã đăng ký một vai trò khác trong hoạt động này rồi"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Vai trò này đã hết chỗ"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể đăng ký hoạt động đã bắt đầu"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Hoạt động này không dành cho lớp của bạn"
}
```

---

### 2. Xem danh sách hoạt động đã đăng ký

**Endpoint:** `GET /activity-registrations/my-registrations`

**Role:** `student`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Lọc theo trạng thái đăng ký: `registered`, `attended`, `absent`, `cancelled` |
| `activity_status` | string | No | Lọc theo trạng thái hoạt động: `upcoming`, `ongoing`, `completed`, `cancelled` |

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "total": 5,
    "summary": {
      "registered": 2,
      "attended": 2,
      "absent": 0,
      "cancelled": 1
    },
    "registrations": [
      {
        "registration_id": 1,
        "activity_id": 1,
        "activity_title": "Hiến máu nhân đạo 2025",
        "activity_status": "completed",
        "role_name": "Tham gia hiến máu",
        "points_awarded": 5,
        "point_type": "ctxh",
        "activity_start_time": "2025-03-15 08:00:00",
        "activity_end_time": "2025-03-15 11:30:00",
        "activity_location": "Sảnh A, Cơ sở 1",
        "registration_status": "attended",
        "registration_time": "2025-03-10 10:30:00",
        "advisor_name": "ThS. Lê Hoàng Cường",
        "can_cancel": false
      },
      {
        "registration_id": 3,
        "activity_id": 2,
        "activity_title": "Workshop: AI tạo sinh",
        "activity_status": "upcoming",
        "role_name": "Người tham dự",
        "points_awarded": 10,
        "point_type": "ren_luyen",
        "activity_start_time": "2025-03-20 14:00:00",
        "activity_end_time": "2025-03-20 16:00:00",
        "activity_location": "Phòng H.201",
        "registration_status": "registered",
        "registration_time": "2025-03-12 09:00:00",
        "advisor_name": "ThS. Trần Văn An",
        "can_cancel": true
      }
    ]
  }
}
```

---

### 3. Tạo yêu cầu hủy đăng ký

**Endpoint:** `POST /activity-registrations/cancel`

**Role:** `student`

**Request Body:**
```json
{
  "registration_id": 3,
  "reason": "Em bị trùng lịch thi giữa kỳ môn học lại. Em xin phép hủy ạ."
}
```

**Validation Rules:**
| Field | Type | Rules |
|-------|------|-------|
| `registration_id` | integer | required, exists:Activity_Registrations,registration_id |
| `reason` | string | required, min:10, max:500 |

**Logic kiểm tra:**
- Đăng ký phải thuộc về sinh viên đang request
- Trạng thái đăng ký phải là `registered`
- Hoạt động không được ở trạng thái `completed` hoặc `cancelled`
- Hoạt động chưa bắt đầu (start_time > now)
- Chưa có yêu cầu hủy pending cho đăng ký này

**Response Success (201):**
```json
{
  "success": true,
  "message": "Gửi yêu cầu hủy thành công. Vui lòng chờ CVHT phê duyệt",
  "data": {
    "request_id": 5,
    "registration_id": 3,
    "activity_title": "Workshop: AI tạo sinh",
    "role_name": "Người tham dự",
    "reason": "Em bị trùng lịch thi giữa kỳ môn học lại. Em xin phép hủy ạ.",
    "status": "pending",
    "requested_at": "2025-03-15 10:00:00"
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Chỉ có thể hủy đăng ký ở trạng thái \"đã đăng ký\". Trạng thái hiện tại: attended"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể hủy đăng ký hoạt động đã bắt đầu"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Yêu cầu hủy đang chờ xử lý, vui lòng chờ CVHT phê duyệt"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền hủy đăng ký này"
}
```

---

### 4. Xem danh sách yêu cầu hủy của mình

**Endpoint:** `GET /activity-registrations/my-cancellation-requests`

**Role:** `student`

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "total": 2,
    "summary": {
      "pending": 1,
      "approved": 1,
      "rejected": 0
    },
    "requests": [
      {
        "request_id": 5,
        "registration_id": 3,
        "activity_id": 2,
        "activity_title": "Workshop: AI tạo sinh",
        "activity_status": "upcoming",
        "activity_start_time": "2025-03-20 14:00:00",
        "role_name": "Người tham dự",
        "reason": "Em bị trùng lịch thi giữa kỳ môn học lại. Em xin phép hủy ạ.",
        "request_status": "pending",
        "requested_at": "2025-03-15 10:00:00",
        "advisor_name": "ThS. Trần Văn An"
      },
      {
        "request_id": 1,
        "registration_id": 4,
        "activity_id": 2,
        "activity_title": "Workshop: AI tạo sinh",
        "activity_status": "completed",
        "activity_start_time": "2025-03-20 14:00:00",
        "role_name": "Người tham dự",
        "reason": "Em bị trùng lịch thi giữa kỳ môn học lại. Em xin phép hủy ạ.",
        "request_status": "approved",
        "requested_at": "2025-03-11 08:00:00",
        "advisor_name": "ThS. Trần Văn An"
      }
    ]
  }
}
```

---

### 5. Xem danh sách hoạt động đã tham gia (kèm vai trò)

**Endpoint:** `GET /my-participated-activities`

**Role:** `student`

**Description:** 
Endpoint này cho phép sinh viên xem tất cả các hoạt động mà họ đã tham gia, bao gồm cả thông tin chi tiết về vai trò trong từng hoạt động.

**Query Parameters:** Không có

**Response Success (200):**
```json
{
  "success": true,
  "data": [
    {
      "registration_id": 1,
      "registration_time": "2025-03-10 10:30:00",
      "registration_status": "attended",
      "activity": {
        "activity_id": 1,
        "title": "Hiến máu nhân đạo 2025",
        "general_description": "Hoạt động hiến máu cứu người",
        "location": "Sảnh A, Cơ sở 1",
        "start_time": "2025-03-15 08:00:00",
        "end_time": "2025-03-15 11:30:00",
        "status": "completed",
        "advisor": {
          "advisor_id": 3,
          "full_name": "ThS. Lê Hoàng Cường",
          "email": "cuonglh@university.edu.vn"
        },
        "organizer_unit": {
          "unit_id": 3,
          "unit_name": "Phòng Công tác Sinh viên"
        }
      },
      "role": {
        "activity_role_id": 1,
        "role_name": "Tham gia hiến máu",
        "description": "Vai trò tham gia hiến máu",
        "requirements": "Tham gia hiến máu tối thiểu 250ml",
        "points_awarded": 5,
        "point_type": "ctxh",
        "max_slots": 50
      }
    },
    {
      "registration_id": 3,
      "registration_time": "2025-03-12 09:00:00",
      "registration_status": "registered",
      "activity": {
        "activity_id": 2,
        "title": "Workshop: AI tạo sinh",
        "general_description": "Tìm hiểu về công nghệ AI tạo sinh",
        "location": "Phòng H.201",
        "start_time": "2025-03-20 14:00:00",
        "end_time": "2025-03-20 16:00:00",
        "status": "upcoming",
        "advisor": {
          "advisor_id": 5,
          "full_name": "ThS. Trần Văn An",
          "email": "antv@university.edu.vn"
        },
        "organizer_unit": {
          "unit_id": 1,
          "unit_name": "Khoa Công nghệ Thông tin"
        }
      },
      "role": {
        "activity_role_id": 5,
        "role_name": "Người tham dự",
        "description": "Vai trò người tham dự",
        "requirements": "Tham gia đầy đủ workshop",
        "points_awarded": 10,
        "point_type": "ren_luyen",
        "max_slots": 100
      }
    }
  ],
  "total": 2
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Chỉ sinh viên mới có thể truy cập endpoint này"
}
```

**Response Error (500):**
```json
{
  "success": false,
  "message": "Có lỗi xảy ra: [error message]"
}
```

**Notes:**
- Kết quả được sắp xếp theo thời gian đăng ký mới nhất trước
- Bao gồm tất cả các trạng thái đăng ký: `registered`, `attended`, `absent`, `cancelled`
- Bao gồm tất cả các trạng thái hoạt động: `upcoming`, `ongoing`, `completed`, `cancelled`
- Trường `status` trong response chỉ trạng thái đăng ký của sinh viên (registered, attended, absent, cancelled)
- Trường `activity.status` chỉ trạng thái của hoạt động (upcoming, ongoing, completed, cancelled)

---

## Cancellation Requests

### 1. Xem danh sách yêu cầu hủy cho CVHT

**Endpoint:** `GET /activity-registrations/cancellation-requests` (Route mới - chưa có trong document)

**Role:** `advisor`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Lọc theo trạng thái: `pending`, `approved`, `rejected` |

**Logic:**
- CVHT chỉ xem được yêu cầu hủy của sinh viên trong các lớp mình quản lý

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "total": 3,
    "summary": {
      "pending": 2,
      "approved": 1,
      "rejected": 0
    },
    "requests": [
      {
        "request_id": 5,
        "registration_id": 3,
        "student": {
          "student_id": 2,
          "user_code": "210002",
          "full_name": "Trần Thị Thu Cẩm",
          "class_name": "DH21CNTT"
        },
        "activity": {
          "activity_id": 2,
          "title": "Workshop: AI tạo sinh",
          "status": "upcoming",
          "start_time": "2025-03-20 14:00:00"
        },
        "role_name": "Người tham dự",
        "reason": "Em bị trùng lịch thi giữa kỳ môn học lại. Em xin phép hủy ạ.",
        "request_status": "pending",
        "requested_at": "2025-03-15 10:00:00"
      }
    ]
  }
}
```

---

### 2. Xem danh sách yêu cầu hủy của hoạt động

**Endpoint:** `GET /activities/{activityId}/cancellation-requests`

**Role:** `advisor` (chỉ người tạo hoạt động)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Lọc theo trạng thái: `pending`, `approved`, `rejected` |

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "activity": {
      "activity_id": 2,
      "title": "Workshop: AI tạo sinh",
      "status": "upcoming"
    },
    "summary": {
      "total_requests": 2,
      "pending": 1,
      "approved": 1,
      "rejected": 0
    },
    "requests": [
      {
        "request_id": 5,
        "registration_id": 3,
        "student": {
          "student_id": 2,
          "user_code": "210002",
          "full_name": "Trần Thị Thu Cẩm",
          "class_name": "DH21CNTT",
          "advisor_name": "ThS. Trần Văn An"
        },
        "role_name": "Người tham dự",
        "reason": "Em bị trùng lịch thi giữa kỳ môn học lại. Em xin phép hủy ạ.",
        "request_status": "pending",
        "requested_at": "2025-03-15 10:00:00"
      }
    ]
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem danh sách này"
}
```

---

### 3. Duyệt/từ chối yêu cầu hủy

**Endpoint:** `PATCH /activities/{activityId}/cancellation-requests/{requestId}`

**Role:** `advisor` (CVHT của sinh viên)

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `activityId` | integer | Yes | ID của hoạt động |
| `requestId` | integer | Yes | ID của yêu cầu hủy |

**Request Body:**
```json
{
  "status": "approved",
  "note": "Đã kiểm tra lịch thi, chấp thuận yêu cầu hủy"
}
```

**Validation Rules:**
| Field | Type | Rules |
|-------|------|-------|
| `status` | string | required, in:approved,rejected |
| `note` | string | nullable, max:500 |

**Logic kiểm tra:**
- Chỉ CVHT của sinh viên mới được duyệt
- Yêu cầu phải ở trạng thái `pending`
- Nếu `approved`: cập nhật registration status thành `cancelled`

**Response Success (200) - Approved:**
```json
{
  "success": true,
  "message": "Đã duyệt yêu cầu hủy thành công",
  "data": {
    "request_id": 5,
    "registration_id": 3,
    "student_name": "Trần Thị Thu Cẩm",
    "student_code": "210002",
    "activity_title": "Workshop: AI tạo sinh",
    "request_status": "approved",
    "registration_status": "cancelled"
  }
}
```

**Response Success (200) - Rejected:**
```json
{
  "success": true,
  "message": "Đã từ chối yêu cầu hủy",
  "data": {
    "request_id": 5,
    "registration_id": 3,
    "student_name": "Trần Thị Thu Cẩm",
    "student_code": "210002",
    "activity_title": "Workshop: AI tạo sinh",
    "request_status": "rejected",
    "registration_status": "registered"
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Yêu cầu hủy đã được xử lý trước đó (Status: approved)"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền duyệt yêu cầu này (Bạn không phải CVHT của sinh viên Trần Thị Thu Cẩm)"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Yêu cầu hủy không tồn tại"
}
```

---

## Error Codes

### HTTP Status Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request thành công |
| 201 | Created | Tạo resource thành công |
| 400 | Bad Request | Lỗi logic nghiệp vụ |
| 401 | Unauthorized | Chưa đăng nhập hoặc token hết hạn |
| 403 | Forbidden | Không có quyền truy cập |
| 404 | Not Found | Không tìm thấy resource |
| 422 | Unprocessable Entity | Validation failed |
| 500 | Internal Server Error | Lỗi server |

### Common Error Response Format

**401 - Unauthorized:**
```json
{
  "success": false,
  "message": "Token không hợp lệ hoặc đã hết hạn"
}
```

**403 - Forbidden:**
```json
{
  "success": false,
  "message": "Bạn không có quyền truy cập"
}
```

**422 - Validation Error:**
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "title": [
      "The title field is required."
    ],
    "start_time": [
      "The start time field must be a date after now."
    ]
  }
}
```

**500 - Server Error:**
```json
{
  "success": false,
  "message": "Lỗi khi xử lý yêu cầu: [error details]"
}
```
