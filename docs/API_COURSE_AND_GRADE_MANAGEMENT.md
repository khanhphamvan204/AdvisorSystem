# API QUẢN LÝ MÔN HỌC VÀ ĐIỂM

Tài liệu này mô tả các API endpoint để quản lý môn học (Courses) và điểm số (Grades) trong hệ thống.

## Mục lục
- [API Quản lý Môn học (Courses)](#api-quản-lý-môn-học-courses)
- [API Quản lý Điểm (Grades)](#api-quản-lý-điểm-grades)

---

## API QUẢN LÝ MÔN HỌC (COURSES)

### 1. Xem danh sách tất cả môn học (Public)
**Endpoint:** `GET /api/courses`

**Mô tả:** Xem danh sách tất cả môn học trong hệ thống. Hỗ trợ tìm kiếm và lọc theo khoa.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` (optional): Từ khóa tìm kiếm theo mã hoặc tên môn học
- `unit_id` (optional): ID của khoa để lọc môn học

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "courses": [
      {
        "course_id": 1,
        "course_code": "CS101",
        "course_name": "Lập trình căn bản",
        "credits": 3,
        "unit": {
          "unit_id": 1,
          "unit_name": "Khoa Công nghệ Thông tin",
          "type": "faculty"
        }
      }
    ]
  }
}
```

---

### 2. Xem chi tiết môn học (Public)
**Endpoint:** `GET /api/courses/{course_id}`

**Mô tả:** Xem thông tin chi tiết của một môn học và thống kê số sinh viên học.

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "course": {
      "course_id": 1,
      "course_code": "CS101",
      "course_name": "Lập trình căn bản",
      "credits": 3,
      "unit": {
        "unit_id": 1,
        "unit_name": "Khoa Công nghệ Thông tin",
        "type": "faculty"
      }
    },
    "statistics": {
      "total_students": 120,
      "passed_count": 100,
      "failed_count": 20,
      "pass_rate": 83.33
    }
  }
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy môn học"
}
```

---

### 3. Xem danh sách môn học của sinh viên (Student)
**Endpoint:** `GET /api/courses/my-courses`

**Mô tả:** Sinh viên xem danh sách các môn học của mình.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `semester_id` (optional): Lọc theo học kỳ

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "courses": [
      {
        "grade_id": 1,
        "course_code": "CS101",
        "course_name": "Lập trình căn bản",
        "credits": 3,
        "unit_name": "Khoa Công nghệ Thông tin",
        "semester": "Học kỳ 1 2024-2025",
        "grade_value": 8.5,
        "grade_letter": "B+",
        "grade_4_scale": 3.5,
        "status": "passed"
      }
    ],
    "summary": {
      "total_courses": 5,
      "total_credits": 15,
      "passed_credits": 12,
      "failed_courses": 1
    }
  }
}
```

---

### 4. Xem danh sách sinh viên học một môn (Advisor)
**Endpoint:** `GET /api/courses/{course_id}/students`

**Mô tả:** CVHT xem danh sách sinh viên trong lớp mình quản lý đã học môn này.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `semester_id` (required): ID của học kỳ

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "course_info": {
      "course_code": "CS101",
      "course_name": "Lập trình căn bản",
      "credits": 3,
      "unit_name": "Khoa Công nghệ Thông tin"
    },
    "semester_info": {
      "semester_name": "Học kỳ 1",
      "academic_year": "2024-2025"
    },
    "students": [
      {
        "grade_id": 1,
        "student_id": 101,
        "user_code": "SV2021001",
        "full_name": "Nguyễn Văn A",
        "class_name": "KTPM2021A",
        "grade_value": 8.5,
        "grade_letter": "B+",
        "grade_4_scale": 3.5,
        "status": "passed"
      }
    ],
    "statistics": {
      "total_students": 30,
      "passed_count": 25,
      "failed_count": 5,
      "pass_rate": 83.33,
      "average_grade": 7.5
    }
  }
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "Vui lòng cung cấp semester_id"
}
```

---

### 5. Xem danh sách môn học thuộc khoa (Admin)
**Endpoint:** `GET /api/courses/my-unit-courses`

**Mô tả:** Admin xem danh sách môn học thuộc khoa mình quản lý.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` (optional): Từ khóa tìm kiếm

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "unit_info": {
      "unit_id": 1,
      "unit_name": "Khoa Công nghệ Thông tin",
      "type": "faculty"
    },
    "courses": [
      {
        "course_id": 1,
        "course_code": "CS101",
        "course_name": "Lập trình căn bản",
        "credits": 3,
        "unit_id": 1
      }
    ],
    "total_courses": 25
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Admin chưa được gán vào khoa nào"
}
```

---

### 6. Tạo môn học mới (Admin)
**Endpoint:** `POST /api/courses`

**Mô tả:** Admin tạo môn học mới (chỉ được tạo môn học thuộc khoa của mình).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "course_code": "CS102",
  "course_name": "Cấu trúc dữ liệu và giải thuật",
  "credits": 4,
  "unit_id": 1
}
```

**Response Success (201):**
```json
{
  "success": true,
  "message": "Tạo môn học thành công",
  "data": {
    "course": {
      "course_id": 2,
      "course_code": "CS102",
      "course_name": "Cấu trúc dữ liệu và giải thuật",
      "credits": 4,
      "unit_id": 1,
      "unit": {
        "unit_id": 1,
        "unit_name": "Khoa Công nghệ Thông tin",
        "type": "faculty"
      }
    }
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ có thể thêm môn học thuộc khoa của mình"
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "course_code": ["Mã môn học đã tồn tại"],
    "credits": ["Số tín chỉ phải từ 1 đến 10"]
  }
}
```

---

### 7. Cập nhật môn học (Admin)
**Endpoint:** `PUT /api/courses/{course_id}`

**Mô tả:** Admin cập nhật thông tin môn học (chỉ được cập nhật môn học thuộc khoa của mình).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "course_code": "CS102",
  "course_name": "Cấu trúc dữ liệu",
  "credits": 3
}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Cập nhật môn học thành công",
  "data": {
    "course": {
      "course_id": 2,
      "course_code": "CS102",
      "course_name": "Cấu trúc dữ liệu",
      "credits": 3,
      "unit_id": 1
    }
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ có thể cập nhật môn học thuộc khoa của mình"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy môn học"
}
```

---

### 8. Xóa môn học (Admin)
**Endpoint:** `DELETE /api/courses/{course_id}`

**Mô tả:** Admin xóa môn học (chỉ được xóa môn học thuộc khoa của mình và chưa có điểm).

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Xóa môn học thành công"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Không thể xóa môn học đã có điểm. Vui lòng xóa điểm trước."
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ có thể xóa môn học thuộc khoa của mình"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy môn học"
}
```

---

## API QUẢN LÝ ĐIỂM (GRADES)

### 1. Xem điểm của chính mình (Student)
**Endpoint:** `GET /api/grades/my-grades`

**Mô tả:** Sinh viên xem điểm các môn học của mình.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `semester_id` (optional): Lọc theo học kỳ

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "grades": [
      {
        "grade_id": 1,
        "course_code": "CS101",
        "course_name": "Lập trình căn bản",
        "credits": 3,
        "semester": "Học kỳ 1 2024-2025",
        "semester_id": 1,
        "grade_10": 8.5,
        "grade_letter": "B+",
        "grade_4": 3.5,
        "status": "passed"
      }
    ],
    "summary": {
      "total_courses": 5,
      "passed_courses": 4,
      "failed_courses": 1,
      "studying_courses": 0
    }
  }
}
```

---

### 2. Xem điểm của sinh viên (Advisor)
**Endpoint:** `GET /api/grades/student/{student_id}`

**Mô tả:** CVHT xem điểm của sinh viên trong lớp mình quản lý.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `semester_id` (optional): Lọc theo học kỳ

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "student_info": {
      "student_id": 101,
      "user_code": "SV2021001",
      "full_name": "Nguyễn Văn A",
      "class_name": "KTPM2021A"
    },
    "grades": [
      {
        "grade_id": 1,
        "course_code": "CS101",
        "course_name": "Lập trình căn bản",
        "credits": 3,
        "semester": "Học kỳ 1 2024-2025",
        "grade_10": 8.5,
        "grade_letter": "B+",
        "grade_4": 3.5,
        "status": "passed"
      }
    ],
    "summary": {
      "total_courses": 5,
      "passed_courses": 4,
      "failed_courses": 1
    }
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ được xem điểm sinh viên trong lớp mình quản lý"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy sinh viên"
}
```

---

### 3. Nhập điểm cho sinh viên (Admin)
**Endpoint:** `POST /api/grades`

**Mô tả:** Admin nhập điểm cho sinh viên (chỉ được nhập điểm cho sinh viên trong các lớp thuộc khoa mình quản lý).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "student_id": 101,
  "course_id": 1,
  "semester_id": 1,
  "grade_value": 8.5
}
```

**Response Success (201):**
```json
{
  "success": true,
  "message": "Nhập điểm thành công",
  "data": {
    "grade": {
      "grade_id": 1,
      "student_name": "Nguyễn Văn A",
      "course_name": "Lập trình căn bản",
      "semester": "Học kỳ 1 2024-2025",
      "grade_10": 8.5,
      "grade_letter": "B+",
      "grade_4": 3.5,
      "status": "passed"
    }
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Sinh viên đã có điểm môn này trong học kỳ. Vui lòng sử dụng API cập nhật."
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ được nhập điểm cho sinh viên trong các lớp thuộc khoa mình quản lý"
}
```

**Response Error (403) - Admin chưa có khoa:**
```json
{
  "success": false,
  "message": "Admin chưa được gán vào khoa nào"
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "grade_value": ["Điểm phải từ 0 đến 10"],
    "student_id": ["Sinh viên không tồn tại"]
  }
}
```

---

### 4. Cập nhật điểm (Admin)
**Endpoint:** `PUT /api/grades/{grade_id}`

**Mô tả:** Admin cập nhật điểm của sinh viên (chỉ được cập nhật điểm của sinh viên trong các lớp thuộc khoa mình quản lý).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "grade_value": 9.0
}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Cập nhật điểm thành công",
  "data": {
    "grade": {
      "grade_id": 1,
      "student_name": "Nguyễn Văn A",
      "course_name": "Lập trình căn bản",
      "semester": "Học kỳ 1 2024-2025",
      "grade_10": 9.0,
      "grade_letter": "A",
      "grade_4": 4.0,
      "status": "passed"
    }
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ được cập nhật điểm cho sinh viên trong các lớp thuộc khoa mình quản lý"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy điểm"
}
```

---

### 5. Nhập điểm hàng loạt (Admin)
**Endpoint:** `POST /api/grades/batch-import`

**Mô tả:** Admin nhập điểm hàng loạt cho nhiều sinh viên trong một môn học và học kỳ (chỉ được nhập điểm cho sinh viên trong các lớp thuộc khoa mình quản lý).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "semester_id": 1,
  "course_id": 1,
  "grades": [
    {
      "student_id": 101,
      "grade_value": 8.5
    },
    {
      "student_id": 102,
      "grade_value": 7.0
    },
    {
      "student_id": 103,
      "grade_value": 9.0
    }
  ]
}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Nhập điểm hàng loạt thành công",
  "data": {
    "results": [
      {
        "student_id": 101,
        "user_code": "SV2021001",
        "full_name": "Nguyễn Văn A",
        "grade_value": 8.5,
        "status": "created"
      },
      {
        "student_id": 102,
        "user_code": "SV2021002",
        "full_name": "Trần Thị B",
        "grade_value": 7.0,
        "status": "updated"
      }
    ],
    "errors": [
      {
        "student_id": 103,
        "message": "Sinh viên không thuộc khoa bạn quản lý"
      }
    ],
    "summary": {
      "total_processed": 3,
      "created": 1,
      "updated": 1,
      "errors": 1
    }
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Admin chưa được gán vào khoa nào"
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "grades": ["Danh sách điểm không được để trống"],
    "grades.0.grade_value": ["Điểm phải từ 0 đến 10"]
  }
}
```

---

### 6. Xóa điểm (Admin)
**Endpoint:** `DELETE /api/grades/{grade_id}`

**Mô tả:** Admin xóa điểm của sinh viên (chỉ được xóa điểm của sinh viên trong các lớp thuộc khoa mình quản lý).

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Xóa điểm thành công"
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ được xóa điểm cho sinh viên trong các lớp thuộc khoa mình quản lý"
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Không tìm thấy điểm"
}
```

---

### 7. Xuất điểm lớp theo học kỳ (Advisor/Admin)
**Endpoint:** `GET /api/grades/export-class-grades/{class_id}/{semester_id}`

**Mô tả:** CVHT xuất danh sách điểm của tất cả sinh viên trong lớp mình quản lý theo học kỳ. Admin xuất điểm lớp thuộc khoa mình quản lý.

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "class_info": {
      "class_name": "KTPM2021A"
    },
    "semester_info": {
      "semester_name": "Học kỳ 1",
      "academic_year": "2024-2025"
    },
    "students_grades": [
      {
        "student_id": 101,
        "user_code": "SV2021001",
        "full_name": "Nguyễn Văn A",
        "courses": [
          {
            "course_code": "CS101",
            "course_name": "Lập trình căn bản",
            "credits": 3,
            "grade_10": 8.5,
            "grade_letter": "B+",
            "grade_4": 3.5,
            "status": "passed"
          }
        ]
      }
    ]
  }
}
```

**Response Error (403):**
```json
{
  "success": false,
  "message": "Bạn chỉ được xuất điểm lớp mình quản lý"
}
```
hoặc
```json
{
  "success": false,
  "message": "Lớp này không thuộc khoa bạn quản lý"
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

### 8. Xem danh sách sinh viên và điểm trong khoa (Admin)
**Endpoint:** `GET /api/grades/faculty-students`

**Mô tả:** Admin xem danh sách tất cả sinh viên trong khoa mình quản lý kèm theo thông tin điểm và thống kê học tập.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `semester_id` (optional): Lọc điểm theo học kỳ
- `class_id` (optional): Lọc theo lớp cụ thể
- `search` (optional): Tìm kiếm theo tên hoặc mã sinh viên

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "faculty_info": {
      "unit_id": 1,
      "unit_name": "Khoa Công nghệ Thông tin"
    },
    "students": [
      {
        "student_id": 101,
        "user_code": "SV2021001",
        "full_name": "Nguyễn Văn A",
        "email": "student1@example.com",
        "phone_number": "0123456789",
        "class_name": "KTPM2021A",
        "class_id": 1,
        "status": "active",
        "academic_summary": {
          "cpa_10": 7.8,
          "cpa_4": 3.2,
          "total_credits_passed": 45,
          "passed_courses": 14,
          "failed_courses": 1,
          "total_courses": 15,
          "semester_gpa_10": 7.5,
          "semester_gpa_4": 3.0,
          "semester_credits": 18
        }
      },
      {
        "student_id": 102,
        "user_code": "SV2021002",
        "full_name": "Trần Thị B",
        "email": "student2@example.com",
        "phone_number": "0987654321",
        "class_name": "KTPM2021B",
        "class_id": 2,
        "status": "active",
        "academic_summary": {
          "cpa_10": 8.2,
          "cpa_4": 3.5,
          "total_credits_passed": 48,
          "passed_courses": 16,
          "failed_courses": 0,
          "total_courses": 16,
          "semester_gpa_10": 8.0,
          "semester_gpa_4": 3.3,
          "semester_credits": 18
        }
      }
    ],
    "summary": {
      "total_students": 120,
      "total_classes": 5
    }
  }
}
```

**Giải thích `academic_summary`:**
- `cpa_10`, `cpa_4`: Điểm trung bình tích lũy (Cumulative Point Average) - toàn bộ các học kỳ
- `semester_gpa_10`, `semester_gpa_4`: Điểm trung bình học kỳ (Grade Point Average) - chỉ hiển thị khi có filter `semester_id`
- `total_credits_passed`: Tổng số tín chỉ đã qua (điểm >= 4.0)
- `semester_credits`: Số tín chỉ đăng ký trong học kỳ - chỉ hiển thị khi có filter `semester_id`

**Response Error (403):**
```json
{
  "success": false,
  "message": "Chỉ Admin mới có quyền xem danh sách này"
}
```
hoặc
```json
{
  "success": false,
  "message": "Admin chưa được gán vào khoa nào"
}
```

---

### 9. Xem tổng quan điểm của khoa (Admin)
**Endpoint:** `GET /api/grades/faculty-overview`

**Mô tả:** Admin xem tổng quan về điểm số và thống kê học tập của toàn bộ khoa mình quản lý.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `semester_id` (optional): Lọc thống kê theo học kỳ

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "faculty_info": {
      "unit_id": 1,
      "unit_name": "Khoa Công nghệ Thông tin"
    },
    "overview": {
      "total_students": 120,
      "total_classes": 5,
      "total_grades": 1800,
      "average_cpa": 3.2,
      "average_score": 7.8,
      "passed_rate": 92.5
    },
    "grade_statistics": {
      "passed": 1665,
      "failed": 135,
      "studying": 0
    },
    "grade_distribution": {
      "excellent": 450,
      "good": 600,
      "average": 450,
      "below_average": 165,
      "failed": 135
    },
    "class_statistics": [
      {
        "class_id": 1,
        "class_name": "KTPM2021A",
        "total_students": 40,
        "average_cpa": 3.3,
        "average_semester_gpa": 3.1,
        "passed_courses": 560,
        "failed_courses": 40
      },
      {
        "class_id": 2,
        "class_name": "KTPM2021B",
        "total_students": 38,
        "average_cpa": 3.4,
        "average_semester_gpa": 3.2,
        "passed_courses": 532,
        "failed_courses": 38
      }
    ]
  }
}
```

**Giải thích `overview`:**
- `average_cpa`: Điểm trung bình tích lũy (CPA) trung bình của toàn khoa (thang 10)
- `average_score`: Điểm trung bình của các môn học trong học kỳ (chỉ hiển thị khi có filter `semester_id`)
- `passed_rate`: Tỷ lệ đỗ (%)

**Giải thích `class_statistics`:**
- `average_cpa`: CPA trung bình của lớp (thành tích tích lũy)
- `average_semester_gpa`: GPA trung bình của lớp trong học kỳ (chỉ hiển thị khi có filter `semester_id`)

**Giải thích `grade_distribution`:**
- `excellent`: Xuất sắc (>= 8.5)
- `good`: Giỏi (7.0 - 8.4)
- `average`: Khá (5.5 - 6.9)
- `below_average`: Trung bình (4.0 - 5.4)
- `failed`: Yếu/Kém (< 4.0)

**Response Error (403):**
```json
{
  "success": false,
  "message": "Chỉ Admin mới có quyền xem tổng quan"
}
```
hoặc
```json
{
  "success": false,
  "message": "Admin chưa được gán vào khoa nào"
}
```

---

## Quy tắc phân quyền

### Course Management:
1. **Public (Student, Advisor, Admin):**
   - Xem danh sách môn học
   - Xem chi tiết môn học

2. **Student:**
   - Xem danh sách môn học của mình

3. **Advisor:**
   - Xem danh sách sinh viên học một môn (chỉ sinh viên trong lớp mình quản lý)

4. **Admin:**
   - Xem danh sách môn học thuộc khoa mình
   - Tạo môn học mới (chỉ thuộc khoa mình)
   - Cập nhật môn học (chỉ môn học thuộc khoa mình)
   - Xóa môn học (chỉ môn học thuộc khoa mình và chưa có điểm)

### Grade Management:
1. **Student:**
   - Xem điểm của chính mình

2. **Advisor:**
   - Xem điểm của sinh viên trong lớp mình quản lý
   - Xuất điểm lớp theo học kỳ

3. **Admin:**
   - Nhập điểm cho sinh viên (chỉ sinh viên trong các lớp thuộc khoa mình)
   - Cập nhật điểm (chỉ sinh viên trong các lớp thuộc khoa mình)
   - Nhập điểm hàng loạt (chỉ sinh viên trong các lớp thuộc khoa mình)
   - Xóa điểm (chỉ sinh viên trong các lớp thuộc khoa mình)
   - Xem danh sách sinh viên và điểm trong khoa mình quản lý
   - Xem tổng quan điểm của khoa mình quản lý
   - Xuất điểm lớp theo học kỳ (chỉ lớp thuộc khoa mình)

---

## Quy tắc chuyển đổi điểm

Hệ thống tự động chuyển đổi điểm theo các thang đo:

### Thang điểm 10:
- 0.0 - 10.0

### Thang điểm chữ:
- 9.0 - 10.0: A (Xuất sắc)
- 8.5 - 8.9: B+ (Giỏi)
- 8.0 - 8.4: B (Khá giỏi)
- 7.0 - 7.9: C+ (Khá)
- 6.5 - 6.9: C (Trung bình khá)
- 5.5 - 6.4: D+ (Trung bình)
- 5.0 - 5.4: D (Trung bình yếu)
- 4.0 - 4.9: F+ (Yếu)
- 0.0 - 3.9: F (Kém)

### Thang điểm 4:
- 9.0 - 10.0: 4.0
- 8.5 - 8.9: 3.5
- 8.0 - 8.4: 3.0
- 7.0 - 7.9: 2.5
- 6.5 - 6.9: 2.0
- 5.5 - 6.4: 1.5
- 5.0 - 5.4: 1.0
- 4.0 - 4.9: 0.5
- 0.0 - 3.9: 0.0

### Trạng thái:
- `passed`: Điểm >= 4.0
- `failed`: Điểm < 4.0
- `studying`: Đang học (chưa có điểm cuối kỳ)

---

## Lưu ý quan trọng

1. **Quyền hạn Admin:**
   - Admin phải được gán vào một khoa cụ thể (`unit_id` trong bảng `Advisors`)
   - Admin chỉ có thể quản lý môn học và điểm của các lớp thuộc khoa mình
   - Mỗi lớp (`ClassModel`) có `faculty_id` liên kết với khoa
   - Hệ thống sẽ kiểm tra `faculty_id` của lớp sinh viên có khớp với `unit_id` của admin không

2. **Quản lý môn học:**
   - Mỗi môn học thuộc về một khoa (`unit_id`)
   - Không thể xóa môn học đã có điểm
   - Mã môn học phải là duy nhất

3. **Quản lý điểm:**
   - Điểm được tự động chuyển đổi sang thang chữ và thang 4
   - Không thể nhập điểm trùng lặp (cùng sinh viên, môn học, học kỳ)
   - Sử dụng API cập nhật để thay đổi điểm đã nhập

4. **Nhập điểm hàng loạt:**
   - Có thể tạo mới hoặc cập nhật điểm
   - Hệ thống sẽ báo lỗi cho từng sinh viên không hợp lệ
   - Sinh viên không thuộc khoa admin quản lý sẽ bị bỏ qua

5. **Token Authentication:**
   - Tất cả API đều yêu cầu Bearer token
   - Token chứa thông tin về role (student/advisor/admin) và user_id

6. **Error Handling:**
   - 200: Success
   - 201: Created
   - 400: Bad Request
   - 403: Forbidden (không có quyền)
   - 404: Not Found
   - 422: Validation Error
   - 500: Internal Server Error
