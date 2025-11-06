# API_POINT_MANAGEMENT

## Tổng quan
API quản lý điểm rèn luyện và điểm công tác xã hội (CTXH) của sinh viên từ các hoạt động đã tham gia.

**Base URL:** `/api`

**Authentication:** Tất cả các endpoint yêu cầu Bearer Token trong header
```
Authorization: Bearer {access_token}
```

---

## Endpoints

### 1. Xem điểm của sinh viên

Lấy tổng điểm rèn luyện (theo kỳ học), điểm CTXH (tổng tất cả) và danh sách chi tiết các hoạt động đã tham gia.

**Endpoint:** `GET /api/student-points`

**Role:** Student, Advisor

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**

| Tham số | Bắt buộc | Kiểu | Mô tả |
|---------|----------|------|-------|
| student_id | Có (nếu role = advisor) | integer | ID của sinh viên cần xem điểm |
| semester_id | Không | integer | ID kỳ học để lọc điểm rèn luyện. Nếu không truyền, lấy tất cả |

**Lưu ý:**
- **Student:** Tự động xem điểm của chính mình, không cần truyền `student_id`
- **Advisor:** Bắt buộc truyền `student_id`, chỉ xem được điểm của sinh viên trong lớp mình quản lý
- **Điểm rèn luyện:** Được lọc theo kỳ học (nếu có `semester_id`)
- **Điểm CTXH:** Luôn tính tổng từ tất cả hoạt động (không lọc theo thời gian)

**Request Example (Advisor - lọc theo kỳ):**
```
GET /api/student-points?student_id=123&semester_id=1
```

**Request Example (Student - xem tất cả):**
```
GET /api/student-points
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "student_info": {
      "student_id": 123,
      "full_name": "Nguyễn Văn A",
      "user_code": "SV001"
    },
    "filter_info": {
      "semester_id": 1,
      "semester_name": "HK1 2024-2025",
      "academic_year": "2024-2025"
    },
    "summary": {
      "total_training_points": 50,
      "total_social_points": 120
    },
    "training_activities": [
      {
        "activity_title": "Hội thảo kỹ năng mềm",
        "role_name": "Người tham gia",
        "points_awarded": 10,
        "point_type": "ren_luyen",
        "activity_date": "2024-10-20 14:00:00"
      }
    ],
    "social_activities": [
      {
        "activity_title": "Hiến máu nhân đạo",
        "role_name": "Tình nguyện viên",
        "points_awarded": 15,
        "point_type": "ctxh",
        "activity_date": "2024-10-15 08:00:00"
      },
      {
        "activity_title": "Chương trình từ thiện",
        "role_name": "Ban tổ chức",
        "points_awarded": 25,
        "point_type": "ctxh",
        "activity_date": "2024-09-10 08:00:00"
      }
    ]
  }
}
```

**Response Error:**

*422 - Validation Error:*
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "student_id": ["The student_id field is required when current_role is advisor."]
  }
}
```

*403 - Forbidden:*
```json
{
  "success": false,
  "message": "Bạn không có quyền xem thông tin sinh viên này"
}
```

*404 - Not Found:*
```json
{
  "success": false,
  "message": "Sinh viên không tồn tại"
}
```

---

### 2. Xem tổng hợp điểm cả lớp

Lấy danh sách tổng điểm của tất cả sinh viên trong một lớp. Điểm rèn luyện có thể lọc theo kỳ học, điểm CTXH luôn tính tổng tất cả.

**Endpoint:** `GET /api/student-points/class-summary`

**Role:** Advisor only

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**

| Tham số | Bắt buộc | Kiểu | Mô tả |
|---------|----------|------|-------|
| class_id | Có | integer | ID của lớp cần xem |
| semester_id | Không | integer | ID kỳ học để lọc điểm rèn luyện. Nếu không truyền, lấy tất cả |

**Request Example (với filter kỳ học):**
```
GET /api/student-points/class-summary?class_id=5&semester_id=1
```

**Request Example (không filter):**
```
GET /api/student-points/class-summary?class_id=5
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "class_name": "CNTT K15A",
    "filter_info": {
      "semester_id": 1,
      "semester_name": "HK1 2024-2025",
      "academic_year": "2024-2025"
    },
    "total_students": 45,
    "students": [
      {
        "student_id": 123,
        "user_code": "SV001",
        "full_name": "Nguyễn Văn A",
        "total_training_points": 50,
        "total_social_points": 120
      },
      {
        "student_id": 124,
        "user_code": "SV002",
        "full_name": "Trần Thị B",
        "total_training_points": 45,
        "total_social_points": 95
      }
    ]
  }
}
```

**Response Error:**

*422 - Validation Error:*
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "class_id": ["The class_id field is required."]
  }
}
```

*403 - Forbidden:*
```json
{
  "success": false,
  "message": "Bạn không có quyền xem thông tin lớp này"
}
```

---

## Models và Quan hệ

### Student
- Có nhiều `ActivityRegistration`
- Thuộc về một `Class`

### ActivityRegistration
- Thuộc về một `Student`
- Thuộc về một `ActivityRole`
- Có trạng thái: `registered`, `attended`, `cancelled`

### ActivityRole
- Thuộc về một `Activity`
- Có các thuộc tính:
  - `role_name`: Tên vai trò
  - `points_awarded`: Điểm thưởng
  - `point_type`: Loại điểm (`ren_luyen` hoặc `ctxh`)

### Activity
- Có nhiều `ActivityRole`
- Có thuộc tính `start_time`: Thời gian bắt đầu hoạt động

---

## Quy tắc tính điểm

### Điểm Rèn Luyện (Training Points)
1. **Lọc theo KỲ HỌC:**
   - Sử dụng parameter `semester_id` để lọc
   - Chỉ tính các hoạt động có `start_time` nằm trong khoảng `start_date` và `end_date` của kỳ học
   - Nếu không truyền `semester_id`, lấy tất cả điểm rèn luyện

2. **Chỉ tính hoạt động đã tham gia:** Status = `attended`

### Điểm CTXH (Social Points)
1. **Tính TỔNG từ TẤT CẢ hoạt động:**
   - Không lọc theo thời gian
   - Cộng tất cả điểm CTXH từ mọi hoạt động đã tham gia

2. **Chỉ tính hoạt động đã tham gia:** Status = `attended`

### Chung
1. **Hai loại điểm:**
   - `ren_luyen`: Điểm rèn luyện (tính theo kỳ)
   - `ctxh`: Điểm công tác xã hội (tính tổng tất cả)

2. **Điểm được tính theo vai trò:** Mỗi vai trò trong hoạt động có số điểm khác nhau

3. **Hoạt động hợp lệ:** Chỉ tính các hoạt động có status = `attended`

---

## Error Codes

| Code | Message | Mô tả |
|------|---------|-------|
| 200 | Success | Thành công |
| 401 | Unauthorized | Chưa đăng nhập hoặc token không hợp lệ |
| 403 | Forbidden | Không có quyền truy cập |
| 404 | Not Found | Không tìm thấy dữ liệu |
| 422 | Validation Error | Dữ liệu không hợp lệ |

---

## Ví dụ sử dụng

### Student xem điểm của mình
```bash
curl -X GET "http://localhost:8000/api/student-points" \
  -H "Authorization: Bearer {student_token}" \
  -H "Accept: application/json"
```

### Advisor xem điểm của sinh viên
```bash
curl -X GET "http://localhost:8000/api/student-points?student_id=123" \
  -H "Authorization: Bearer {advisor_token}" \
  -H "Accept: application/json"
```

### Advisor xem tổng hợp điểm lớp
```bash
curl -X GET "http://localhost:8000/api/student-points/class-summary?class_id=5" \
  -H "Authorization: Bearer {advisor_token}" \
  -H "Accept: application/json"
```

---

## Lưu ý quan trọng

1. **Phân quyền:**
   - Student chỉ xem được điểm của chính mình
   - Advisor chỉ xem được điểm sinh viên trong lớp mình quản lý

2. **Tính toán điểm:**
   - Điểm được tính từ tất cả các hoạt động đã tham gia (không phân biệt học kỳ)
   - Chỉ tính hoạt động có status = `attended`

3. **Middleware:**
   - `auth.api`: Kiểm tra authentication
   - `check_role:student,advisor`: Kiểm tra role của user