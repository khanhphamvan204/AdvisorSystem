# API Documentation - Activity Management (Advisor Features)

## Mục lục
- [1. Tổng quan](#1-tổng-quan)
- [2. Quản lý sinh viên và phân công](#2-quản-lý-sinh-viên-và-phân-công)
  - [2.1. Lấy danh sách sinh viên có thể phân công](#21-lấy-danh-sách-sinh-viên-có-thể-phân-công)
  - [2.2. Phân công sinh viên vào hoạt động](#22-phân-công-sinh-viên-vào-hoạt-động)
  - [2.3. Hủy phân công sinh viên](#23-hủy-phân-công-sinh-viên)

---

## 1. Tổng quan

Tài liệu này mô tả các API dành riêng cho **Advisor (CVHT)** để quản lý và phân công sinh viên vào các hoạt động.

**Base URL**: `/api`

**Authentication**: Tất cả endpoints yêu cầu JWT token với role = `advisor`

```http
Authorization: Bearer {advisor_jwt_token}
```

---

## 2. Quản lý sinh viên và phân công

### 2.1. Lấy danh sách sinh viên có thể phân công

Lấy danh sách TẤT CẢ sinh viên trong các lớp do CVHT quản lý, kèm thông tin:
- Có thể phân công được không
- Lý do không thể phân công (nếu có)
- Điểm rèn luyện (kỳ gần nhất) và điểm CTXH (tổng tất cả)

**Endpoint**: `GET /api/activities/{activityId}/available-students`

**Role**: Advisor only (chỉ người tạo hoạt động)

**Path Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| activityId | integer | Yes | ID của hoạt động |

**Request Example**:
```http
GET /api/activities/5/available-students
Authorization: Bearer {advisor_token}
```

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "activity": {
      "activity_id": 5,
      "title": "Workshop: Giới thiệu về AI",
      "status": "upcoming",
      "start_time": "2025-03-20 14:00:00"
    },
    "current_semester": {
      "semester_id": 1,
      "semester_name": "HK1 2024-2025",
      "academic_year": "2024-2025"
    },
    "summary": {
      "total_students": 50,
      "available_count": 35,
      "unavailable_count": 15
    },
    "available_students": [
      {
        "student_id": 1,
        "user_code": "B20DCCN001",
        "full_name": "Nguyễn Văn A",
        "email": "a@example.com",
        "phone_number": "0123456789",
        "class_name": "CNTT K63",
        "training_point": 50,
        "social_point": 120,
        "current_semester": "HK1 2024-2025",
        "status": "studying",
        "can_assign": true,
        "reason_cannot_assign": null,
        "current_registration": null
      }
    ],
    "unavailable_students": [
      {
        "student_id": 2,
        "user_code": "B20DCCN002",
        "full_name": "Trần Thị B",
        "email": "b@example.com",
        "phone_number": "0987654321",
        "class_name": "CNTT K63",
        "training_point": 45,
        "social_point": 95,
        "current_semester": "HK1 2024-2025",
        "status": "studying",
        "can_assign": false,
        "reason_cannot_assign": "Đã đăng ký vai trò 'Tình nguyện viên' với trạng thái 'registered'",
        "current_registration": {
          "registration_id": 123,
          "role_name": "Tình nguyện viên",
          "registration_status": "registered"
        }
      }
    ]
  }
}
```

**Response Fields Explained**:

| Field | Type | Description |
|-------|------|-------------|
| `training_point` | integer | Điểm rèn luyện từ kỳ học gần nhất |
| `social_point` | integer | Tổng điểm CTXH từ tất cả hoạt động |
| `can_assign` | boolean | Có thể phân công sinh viên này không |
| `reason_cannot_assign` | string\|null | Lý do không thể phân công |
| `current_registration` | object\|null | Thông tin đăng ký hiện tại (nếu đã đăng ký) |

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Không thể xem danh sách sinh viên vì hoạt động đã hoàn thành"
}
```

```json
{
  "success": false,
  "message": "Không thể xem danh sách sinh viên vì hoạt động đã bị hủy"
}
```

**Response Error (403)**:
```json
{
  "success": false,
  "message": "Bạn không có quyền xem danh sách này"
}
```

**Response Error (404)**:
```json
{
  "success": false,
  "message": "Hoạt động không tồn tại"
}
```

**Use Cases**:
1. CVHT xem danh sách sinh viên để quyết định phân công
2. Hiển thị điểm rèn luyện/CTXH để đánh giá sinh viên
3. Biết được sinh viên nào đã đăng ký, sinh viên nào chưa
4. Frontend có thể disable/highlight sinh viên không thể phân công

---

### 2.2. Phân công sinh viên vào hoạt động

Phân công một hoặc nhiều sinh viên vào các vai trò cụ thể của hoạt động.

**Endpoint**: `POST /api/activities/{activityId}/assign-students`

**Role**: Advisor only (chỉ người tạo hoạt động)

**Path Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| activityId | integer | Yes | ID của hoạt động |

**Request Body**:
```json
{
  "assignments": [
    {
      "student_id": 1,
      "activity_role_id": 5
    },
    {
      "student_id": 3,
      "activity_role_id": 5
    },
    {
      "student_id": 7,
      "activity_role_id": 6
    }
  ]
}
```

**Validation Rules**:
- `assignments`: required, array, min:1
- `assignments.*.student_id`: required, integer, exists:Students
- `assignments.*.activity_role_id`: required, integer, exists:Activity_Roles

**Request Example**:
```http
POST /api/activities/5/assign-students
Authorization: Bearer {advisor_token}
Content-Type: application/json

{
  "assignments": [
    {"student_id": 1, "activity_role_id": 5},
    {"student_id": 2, "activity_role_id": 5}
  ]
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Đã phân công 2 sinh viên, bỏ qua 0 sinh viên",
  "data": {
    "total_assigned": 2,
    "total_skipped": 0,
    "assigned": [
      {
        "registration_id": 101,
        "student_id": 1,
        "student_name": "Nguyễn Văn A",
        "student_code": "B20DCCN001",
        "role_name": "Người tham dự",
        "points_awarded": 10,
        "point_type": "ren_luyen"
      },
      {
        "registration_id": 102,
        "student_id": 2,
        "student_name": "Trần Thị B",
        "student_code": "B20DCCN002",
        "role_name": "Người tham dự",
        "points_awarded": 10,
        "point_type": "ren_luyen"
      }
    ],
    "skipped": []
  }
}
```

**Response với một số sinh viên bị bỏ qua**:
```json
{
  "success": true,
  "message": "Đã phân công 1 sinh viên, bỏ qua 2 sinh viên",
  "data": {
    "total_assigned": 1,
    "total_skipped": 2,
    "assigned": [
      {
        "registration_id": 101,
        "student_id": 1,
        "student_name": "Nguyễn Văn A",
        "student_code": "B20DCCN001",
        "role_name": "Người tham dự",
        "points_awarded": 10,
        "point_type": "ren_luyen"
      }
    ],
    "skipped": [
      {
        "student_id": 2,
        "student_name": "Trần Thị B",
        "student_code": "B20DCCN002",
        "role_name": "Người tham dự",
        "reason": "Sinh viên đã đăng ký vai trò này rồi",
        "current_status": "registered"
      },
      {
        "student_id": 3,
        "student_name": "Phạm Văn C",
        "student_code": "B20DCCN003",
        "role_name": "Tình nguyện viên",
        "reason": "Vai trò này đã hết chỗ"
      }
    ]
  }
}
```

**Lý do sinh viên bị bỏ qua (skipped)**:
- "Sinh viên không thuộc lớp bạn quản lý"
- "Sinh viên đã đăng ký vai trò này rồi"
- "Vai trò này đã hết chỗ"

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Không thể phân công cho hoạt động đã hoàn thành hoặc bị hủy"
}
```

```json
{
  "success": false,
  "message": "Vai trò ID 10 không thuộc hoạt động này"
}
```

**Response Error (403)**:
```json
{
  "success": false,
  "message": "Bạn không có quyền phân công cho hoạt động này"
}
```

**Response Error (422)**:
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "assignments": ["Danh sách phân công là bắt buộc"],
    "assignments.0.student_id": ["Sinh viên không tồn tại"]
  }
}
```

**Business Rules**:
1. Chỉ phân công được cho hoạt động có status: `upcoming` hoặc `ongoing`
2. Sinh viên phải thuộc lớp do CVHT quản lý
3. Sinh viên chưa đăng ký vai trò đó
4. Vai trò còn chỗ trống (nếu có giới hạn `max_slots`)
5. Vai trò phải thuộc hoạt động đang phân công
6. Sau khi phân công, registration có status = `registered`

---

### 2.3. Hủy phân công sinh viên

Hủy phân công một sinh viên đã được phân công (chỉ với status = `registered`).

**Endpoint**: `DELETE /api/activities/{activityId}/assignments/{registrationId}`

**Role**: Advisor only (chỉ người tạo hoạt động)

**Path Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| activityId | integer | Yes | ID của hoạt động |
| registrationId | integer | Yes | ID của đăng ký cần hủy |

**Request Example**:
```http
DELETE /api/activities/5/assignments/101
Authorization: Bearer {advisor_token}
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Hủy phân công thành công",
  "data": {
    "student_name": "Nguyễn Văn A",
    "student_code": "B20DCCN001",
    "role_name": "Người tham dự"
  }
}
```

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Chỉ có thể hủy phân công ở trạng thái 'đã đăng ký'"
}
```

```json
{
  "success": false,
  "message": "Đăng ký không thuộc hoạt động này"
}
```

**Response Error (403)**:
```json
{
  "success": false,
  "message": "Bạn không có quyền hủy phân công này"
}
```

**Response Error (404)**:
```json
{
  "success": false,
  "message": "Đăng ký không tồn tại"
}
```

**Business Rules**:
1. Chỉ hủy được đăng ký có status = `registered`
2. Không thể hủy nếu sinh viên đã `attended`, `absent`, hoặc `cancelled`
3. Đăng ký phải thuộc hoạt động đang thao tác
4. Chỉ CVHT tạo hoạt động mới có quyền hủy

---

## Use Case Scenarios

### Scenario 1: Phân công sinh viên vào hoạt động mới

1. CVHT tạo hoạt động với các vai trò
2. CVHT gọi `GET /api/activities/{id}/available-students` để xem danh sách sinh viên
3. CVHT chọn sinh viên phù hợp dựa trên điểm, trạng thái
4. CVHT gọi `POST /api/activities/{id}/assign-students` để phân công
5. Hệ thống trả về kết quả: số sinh viên được phân công, số bị bỏ qua và lý do

### Scenario 2: Điều chỉnh danh sách sinh viên

1. CVHT nhận thấy phân công sai sinh viên
2. CVHT gọi `DELETE /api/activities/{id}/assignments/{registrationId}` để hủy
3. CVHT phân công lại sinh viên khác

### Scenario 3: Xử lý vai trò đầy chỗ

1. Sinh viên A được phân công vào vai trò có `max_slots = 10`
2. Khi đã có 10 sinh viên, CVHT phân công thêm sinh viên B
3. Hệ thống trả về sinh viên B trong danh sách `skipped` với lý do "Vai trò này đã hết chỗ"

---

## Notes

1. **Điểm rèn luyện**: Chỉ tính từ kỳ học gần nhất (semester có `end_date` gần nhất)
2. **Điểm CTXH**: Tính tổng từ tất cả hoạt động đã tham gia
3. **Registration Status sau phân công**: `registered`
4. **Quyền hủy phân công**: Chỉ có thể hủy khi status = `registered`
5. **Phân công hàng loạt**: Có thể phân công nhiều sinh viên cùng lúc
6. **Xử lý lỗi**: Hệ thống không rollback toàn bộ nếu một số sinh viên bị lỗi, mà sẽ skip và báo cáo

---

## Error Handling Best Practices

### Frontend nên xử lý các case sau:

1. **Hiển thị danh sách sinh viên**:
   - Chia thành 2 tab: "Có thể phân công" và "Đã đăng ký"
   - Highlight sinh viên không thể phân công với lý do rõ ràng
   - Hiển thị điểm rèn luyện/CTXH để CVHT tham khảo

2. **Phân công hàng loạt**:
   - Cho phép chọn nhiều sinh viên
   - Hiển thị kết quả phân công và danh sách bị bỏ qua
   - Cho phép retry với các sinh viên bị bỏ qua

3. **Validation phía client**:
   - Kiểm tra sinh viên đã đăng ký chưa
   - Kiểm tra vai trò còn chỗ không
   - Disable nút phân công nếu hoạt động đã kết thúc

---

## Postman Examples

### 1. Lấy danh sách sinh viên
```
GET {{base_url}}/activities/5/available-students
Authorization: Bearer {{advisor_token}}
```

### 2. Phân công sinh viên
```
POST {{base_url}}/activities/5/assign-students
Authorization: Bearer {{advisor_token}}
Content-Type: application/json

{
  "assignments": [
    {"student_id": 1, "activity_role_id": 10},
    {"student_id": 2, "activity_role_id": 10}
  ]
}
```

### 3. Hủy phân công
```
DELETE {{base_url}}/activities/5/assignments/101
Authorization: Bearer {{advisor_token}}
```
