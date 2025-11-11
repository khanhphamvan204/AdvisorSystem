# API Documentation - Hệ thống Quản lý Cố vấn Học tập

## Thông tin chung

**Base URL:** `https://yourdomain.com/api`

**Authentication:** Bearer Token (JWT)

**Content-Type:** `application/json`

---

## 1. CLASS MANAGEMENT API

### 1.1. Lấy danh sách lớp

**Endpoint:** `GET /classes`

**Mô tả:** Lấy danh sách lớp học theo quyền của người dùng

**Phân quyền:**
- **Admin:** Xem các lớp thuộc khoa mình quản lý
- **Advisor:** Xem các lớp mình làm cố vấn
- **Student:** Xem lớp của mình

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Response Success (200):**
```json
{
  "success": true,
  "data": [
    {
      "class_id": 1,
      "class_name": "DH21CNTT",
      "advisor_id": 1,
      "faculty_id": 1,
      "description": "Lớp Đại học 2021 ngành Công nghệ Thông tin",
      "advisor": {
        "advisor_id": 1,
        "full_name": "ThS. Trần Văn An",
        "email": "gv.an@school.edu.vn"
      },
      "faculty": {
        "unit_id": 1,
        "unit_name": "Khoa Công nghệ Thông tin",
        "type": "faculty"
      }
    }
  ],
  "message": "Lấy danh sách lớp thành công"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy thông tin đơn vị quản lý"
}
```

---

### 1.2. Xem chi tiết lớp

**Endpoint:** `GET /classes/{id}`

**Mô tả:** Xem thông tin chi tiết của một lớp

**Phân quyền:**
- **Admin:** Chỉ xem lớp trong khoa mình quản lý
- **Advisor:** Chỉ xem lớp mình cố vấn
- **Student:** Chỉ xem lớp của mình

**Parameters:**
- `id` (path, required): ID của lớp

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "class_id": 1,
    "class_name": "DH21CNTT",
    "advisor_id": 1,
    "faculty_id": 1,
    "description": "Lớp Đại học 2021 ngành Công nghệ Thông tin",
    "advisor": {
      "advisor_id": 1,
      "full_name": "ThS. Trần Văn An",
      "email": "gv.an@school.edu.vn",
      "phone_number": "090111222"
    },
    "faculty": {
      "unit_id": 1,
      "unit_name": "Khoa Công nghệ Thông tin",
      "type": "faculty",
      "description": "Quản lý các ngành thuộc lĩnh vực CNTT"
    },
    "students": [
      {
        "student_id": 1,
        "user_code": "210001",
        "full_name": "Nguyễn Văn Hùng",
        "email": "sv.hung@school.edu.vn",
        "phone_number": "091122334",
        "status": "studying"
      }
    ]
  },
  "message": "Lấy thông tin lớp thành công"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem lớp này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy lớp"
}
```

---

### 1.3. Tạo lớp mới

**Endpoint:** `POST /classes`

**Mô tả:** Tạo lớp học mới (chỉ Admin)

**Phân quyền:** Admin (và chỉ tạo được lớp cho khoa mình quản lý)

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "class_name": "DH24CNTT",
  "advisor_id": 1,
  "faculty_id": 1,
  "description": "Lớp Đại học 2024 ngành Công nghệ Thông tin"
}
```

**Validation Rules:**
- `class_name`: required, string, max 50 ký tự, unique
- `advisor_id`: nullable, phải tồn tại trong bảng Advisors
- `faculty_id`: required, phải tồn tại trong bảng Units
- `description`: nullable, string

**Response Success (201):**
```json
{
  "success": true,
  "data": {
    "class_id": 6,
    "class_name": "DH24CNTT",
    "advisor_id": 1,
    "faculty_id": 1,
    "description": "Lớp Đại học 2024 ngành Công nghệ Thông tin"
  },
  "message": "Tạo lớp thành công"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ có thể tạo lớp cho khoa mình quản lý"
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "class_name": [
      "Tên lớp đã tồn tại"
    ],
    "faculty_id": [
      "Khoa không được để trống"
    ]
  }
}
```

---

### 1.4. Cập nhật thông tin lớp

**Endpoint:** `PUT /classes/{id}`

**Mô tả:** Cập nhật thông tin lớp (chỉ Admin)

**Phân quyền:** Admin (chỉ sửa được lớp trong khoa mình quản lý)

**Parameters:**
- `id` (path, required): ID của lớp

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "class_name": "DH24CNTT_Updated",
  "advisor_id": 2,
  "description": "Mô tả đã cập nhật"
}
```

**Validation Rules:**
- `class_name`: optional, string, max 50 ký tự, unique (trừ bản thân)
- `advisor_id`: optional, phải tồn tại trong bảng Advisors
- `faculty_id`: optional, phải tồn tại trong bảng Units
- `description`: optional, string

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "class_id": 1,
    "class_name": "DH24CNTT_Updated",
    "advisor_id": 2,
    "faculty_id": 1,
    "description": "Mô tả đã cập nhật"
  },
  "message": "Cập nhật lớp thành công"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền cập nhật lớp này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy lớp"
}
```

---

### 1.5. Xóa lớp

**Endpoint:** `DELETE /classes/{id}`

**Mô tả:** Xóa lớp học (chỉ Admin)

**Phân quyền:** Admin (chỉ xóa được lớp trong khoa mình quản lý)

**Parameters:**
- `id` (path, required): ID của lớp

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Xóa lớp thành công"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể xóa lớp có sinh viên"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xóa lớp này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy lớp"
}
```

---

### 1.6. Lấy danh sách sinh viên trong lớp

**Endpoint:** `GET /classes/{id}/students`

**Mô tả:** Lấy danh sách sinh viên trong một lớp

**Phân quyền:**
- **Admin:** Xem sinh viên trong lớp thuộc khoa mình quản lý
- **Advisor:** Xem sinh viên trong lớp mình cố vấn
- **Student:** Xem danh sách bạn cùng lớp

**Parameters:**
- `id` (path, required): ID của lớp

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
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
      "avatar_url": null,
      "class_id": 1,
      "status": "studying",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "last_login": "2024-11-12T10:00:00.000000Z"
    },
    {
      "student_id": 2,
      "user_code": "210002",
      "full_name": "Trần Thị Thu Cẩm",
      "email": "sv.cam@school.edu.vn",
      "phone_number": "091234567",
      "avatar_url": null,
      "class_id": 1,
      "status": "studying",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "last_login": "2024-11-11T14:30:00.000000Z"
    }
  ],
  "message": "Lấy danh sách sinh viên thành công"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem lớp này"
}
```

---

## 2. SEMESTER MANAGEMENT API

### 2.1. Lấy danh sách học kỳ

**Endpoint:** `GET /semesters`

**Mô tả:** Lấy danh sách tất cả học kỳ (sắp xếp theo năm học và học kỳ giảm dần)

**Phân quyền:** Admin, Advisor, Student (tất cả đều có thể xem)

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": [
    {
      "semester_id": 1,
      "semester_name": "Học kỳ 1",
      "academic_year": "2024-2025",
      "start_date": "2024-09-05",
      "end_date": "2025-01-15"
    },
    {
      "semester_id": 2,
      "semester_name": "Học kỳ 2",
      "academic_year": "2024-2025",
      "start_date": "2025-02-10",
      "end_date": "2025-06-30"
    }
  ],
  "message": "Lấy danh sách học kỳ thành công"
}
```

---

### 2.2. Xem chi tiết học kỳ

**Endpoint:** `GET /semesters/{id}`

**Mô tả:** Xem thông tin chi tiết của một học kỳ

**Phân quyền:** Admin, Advisor, Student

**Parameters:**
- `id` (path, required): ID của học kỳ

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "semester_id": 1,
    "semester_name": "Học kỳ 1",
    "academic_year": "2024-2025",
    "start_date": "2024-09-05",
    "end_date": "2025-01-15"
  },
  "message": "Lấy thông tin học kỳ thành công"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy học kỳ"
}
```

---

### 2.3. Tạo học kỳ mới

**Endpoint:** `POST /semesters`

**Mô tả:** Tạo học kỳ mới (chỉ Admin)

**Phân quyền:** Admin

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "semester_name": "Học kỳ hè",
  "academic_year": "2024-2025",
  "start_date": "2025-07-01",
  "end_date": "2025-08-31"
}
```

**Validation Rules:**
- `semester_name`: required, string, max 50 ký tự
- `academic_year`: required, string, max 20 ký tự
- `start_date`: required, date
- `end_date`: required, date, phải sau start_date

**Response Success (201):**
```json
{
  "success": true,
  "data": {
    "semester_id": 6,
    "semester_name": "Học kỳ hè",
    "academic_year": "2024-2025",
    "start_date": "2025-07-01",
    "end_date": "2025-08-31"
  },
  "message": "Tạo học kỳ thành công"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Học kỳ này đã tồn tại"
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "semester_name": [
      "Tên học kỳ không được để trống"
    ],
    "end_date": [
      "Ngày kết thúc phải sau ngày bắt đầu"
    ]
  }
}
```

---

### 2.4. Cập nhật thông tin học kỳ

**Endpoint:** `PUT /semesters/{id}`

**Mô tả:** Cập nhật thông tin học kỳ (chỉ Admin)

**Phân quyền:** Admin

**Parameters:**
- `id` (path, required): ID của học kỳ

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "semester_name": "Học kỳ 1 (Cập nhật)",
  "start_date": "2024-09-10",
  "end_date": "2025-01-20"
}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "semester_id": 1,
    "semester_name": "Học kỳ 1 (Cập nhật)",
    "academic_year": "2024-2025",
    "start_date": "2024-09-10",
    "end_date": "2025-01-20"
  },
  "message": "Cập nhật học kỳ thành công"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy học kỳ"
}
```

---

### 2.5. Xóa học kỳ

**Endpoint:** `DELETE /semesters/{id}`

**Mô tả:** Xóa học kỳ (chỉ Admin)

**Phân quyền:** Admin

**Parameters:**
- `id` (path, required): ID của học kỳ

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Xóa học kỳ thành công"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể xóa học kỳ có dữ liệu điểm"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy học kỳ"
}
```

---

### 2.6. Lấy báo cáo học kỳ

**Endpoint:** `GET /semesters/{id}/reports`

**Mô tả:** Lấy danh sách báo cáo học kỳ của sinh viên

**Phân quyền:**
- **Admin:** Xem báo cáo sinh viên trong khoa mình quản lý
- **Advisor:** Xem báo cáo sinh viên trong lớp mình cố vấn
- **Student:** Chỉ xem báo cáo của mình

**Parameters:**
- `id` (path, required): ID của học kỳ

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "semester": {
      "semester_id": 1,
      "semester_name": "Học kỳ 1",
      "academic_year": "2024-2025",
      "start_date": "2024-09-05",
      "end_date": "2025-01-15"
    },
    "reports": [
      {
        "report_id": 1,
        "student_id": 1,
        "semester_id": 1,
        "gpa": "7.75",
        "gpa_4_scale": "3.00",
        "cpa_10_scale": "7.75",
        "cpa_4_scale": "3.00",
        "credits_registered": 8,
        "credits_passed": 8,
        "training_point_summary": 85,
        "social_point_summary": 15,
        "outcome": "Học tiếp",
        "student": {
          "student_id": 1,
          "user_code": "210001",
          "full_name": "Nguyễn Văn Hùng",
          "email": "sv.hung@school.edu.vn",
          "class": {
            "class_id": 1,
            "class_name": "DH21CNTT",
            "faculty_id": 1
          }
        }
      }
    ]
  },
  "message": "Lấy báo cáo học kỳ thành công"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy học kỳ"
}
```

---

### 2.7. Lấy báo cáo của một sinh viên cụ thể

**Endpoint:** `GET /semesters/{semesterId}/students/{studentId}/report`

**Mô tả:** Lấy báo cáo học kỳ của một sinh viên cụ thể

**Phân quyền:**
- **Admin:** Xem báo cáo sinh viên trong khoa mình quản lý
- **Advisor:** Xem báo cáo sinh viên trong lớp mình cố vấn
- **Student:** Chỉ xem báo cáo của mình

**Parameters:**
- `semesterId` (path, required): ID của học kỳ
- `studentId` (path, required): ID của sinh viên

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "report_id": 1,
    "student_id": 1,
    "semester_id": 1,
    "gpa": "7.75",
    "gpa_4_scale": "3.00",
    "cpa_10_scale": "7.75",
    "cpa_4_scale": "3.00",
    "credits_registered": 8,
    "credits_passed": 8,
    "training_point_summary": 85,
    "social_point_summary": 15,
    "outcome": "Học tiếp",
    "student": {
      "student_id": 1,
      "user_code": "210001",
      "full_name": "Nguyễn Văn Hùng",
      "email": "sv.hung@school.edu.vn",
      "class_id": 1
    },
    "semester": {
      "semester_id": 1,
      "semester_name": "Học kỳ 1",
      "academic_year": "2024-2025"
    }
  },
  "message": "Lấy báo cáo thành công"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn không có quyền xem báo cáo này"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy báo cáo"
}
```

---

### 2.8. Lấy học kỳ hiện tại

**Endpoint:** `GET /semesters/current`

**Mô tả:** Lấy thông tin học kỳ đang diễn ra (dựa vào ngày hiện tại)

**Phân quyền:** Admin, Advisor, Student

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "semester_id": 1,
    "semester_name": "Học kỳ 1",
    "academic_year": "2024-2025",
    "start_date": "2024-09-05",
    "end_date": "2025-01-15"
  },
  "message": "Lấy học kỳ hiện tại thành công"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không có học kỳ đang diễn ra"
}
```

---

## 3. ERROR CODES

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Yêu cầu thành công |
| 201 | Created | Tạo mới thành công |
| 400 | Bad Request | Dữ liệu không hợp lệ hoặc vi phạm ràng buộc |
| 401 | Unauthorized | Token không hợp lệ hoặc hết hạn |
| 403 | Forbidden | Không có quyền truy cập |
| 404 | Not Found | Không tìm thấy tài nguyên |
| 422 | Unprocessable Entity | Validation failed |
| 500 | Internal Server Error | Lỗi server |

---

## 4. AUTHENTICATION

Tất cả API đều yêu cầu JWT token trong header:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

Token được lấy từ API đăng nhập và chứa thông tin:
- `id`: ID của user (student_id hoặc advisor_id)
- `role`: Vai trò (student, advisor, admin)
- `name`: Tên đầy đủ

---

## 5. ROLE PERMISSIONS SUMMARY

### Admin
- ✅ Quản lý lớp trong khoa mình (CRUD)
- ✅ Quản lý học kỳ (CRUD)
- ✅ Xem sinh viên trong khoa mình
- ✅ Xem báo cáo sinh viên trong khoa mình

### Advisor
- ✅ Xem lớp mình cố vấn
- ✅ Xem tất cả học kỳ
- ✅ Xem sinh viên trong lớp mình cố vấn
- ✅ Xem báo cáo sinh viên trong lớp mình cố vấn
- ❌ KHÔNG được tạo/sửa/xóa lớp
- ❌ KHÔNG được tạo/sửa/xóa học kỳ

### Student
- ✅ Xem lớp của mình
- ✅ Xem tất cả học kỳ
- ✅ Xem bạn cùng lớp
- ✅ Xem báo cáo của mình
- ❌ KHÔNG được tạo/sửa/xóa lớp
- ❌ KHÔNG được tạo/sửa/xóa học kỳ

---

## 6. EXAMPLES

### Example 1: Admin tạo lớp mới

```bash
curl -X POST https://yourdomain.com/api/classes \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci..." \
  -H "Content-Type: application/json" \
  -d '{
    "class_name": "DH24CNTT",
    "advisor_id": 1,
    "faculty_id": 1,
    "description": "Lớp 2024 CNTT"
  }'
```

### Example 2: Advisor xem sinh viên trong lớp

```bash
curl -X GET https://yourdomain.com/api/classes/1/students \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci..."
```

### Example 3: Student xem báo cáo của mình

```bash
curl -X GET https://yourdomain.com/api/semesters/1/students/5/report \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci..."
```

### Example 4: Admin lấy học kỳ hiện tại

```bash
curl -X GET https://yourdomain.com/api/semesters/current \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci..."
```

---

## 7. TESTING NOTES

### Postman Collection
Import file `api_collection.json` vào Postman để test nhanh.

### Environment Variables
```
BASE_URL: https://yourdomain.com/api
TOKEN: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

### Test Scenarios
1. ✅ Admin tạo lớp cho khoa khác → Expect 403
2. ✅ Advisor xem lớp không cố vấn → Expect 403
3. ✅ Student xem báo cáo người khác → Expect 403
4. ✅ Xóa lớp có sinh viên → Expect 400
5. ✅ Tạo học kỳ trùng → Expect 400

---

## 8. CHANGELOG

### Version 1.0.0 (12/11/2024)
- ✅ Initial release
- ✅ Class Management API
- ✅ Semester Management API
- ✅ Role-based access control
- ✅ Complete validation & error handling

---

**Liên hệ hỗ trợ:** support@school.edu.vn

**Tài liệu cập nhật:** 12/11/2024