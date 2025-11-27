# 📚 API Documentation - Quản Lý Lịch Học Sinh Viên

## 🎯 Tổng Quan

Hệ thống quản lý lịch học sử dụng:

-   **MySQL**: Lưu thông tin sinh viên, lớp, học kỳ
-   **MongoDB**: Lưu lịch học chi tiết
-   **JWT**: Xác thực và phân quyền

---

## 🔐 Authentication

Tất cả API đều yêu cầu JWT token trong header:

```http
Authorization: Bearer {your_jwt_token}
```

**Roles:**

-   `admin`: Toàn quyền
-   `advisor`: Quản lý lớp mình phụ trách
-   `student`: Chỉ xem lịch của mình

---

## 📥 1. Import Lịch Học

### 1.1. Import Đơn (1 File)

**Endpoint:**

```http
POST /api/admin/schedules/import
POST /api/advisor/schedules/import
```

**Roles:** Admin, Advisor

**Content-Type:** `multipart/form-data`

**Request Body:**

```
file: [Excel file .xls/.xlsx]
```

**Response Success (200):**

```json
{
    "success": true,
    "message": "Import lịch học thành công",
    "data": {
        "student_code": "2001221474",
        "student_name": "Nguyễn Thành Hoàn",
        "student_id": 5,
        "class_name": "13DHTH04",
        "semester": "Học kỳ 1",
        "academic_year": "2025-2026",
        "total_courses": 8,
        "total_schedules": 15
    }
}
```

**Response Warning (200):**

```json
{
  "success": true,
  "message": "Import thành công nhưng sinh viên chưa tồn tại trong hệ thống",
  "warning": "Vui lòng thêm sinh viên có mã 2001221474 vào hệ thống",
  "data": { ... }
}
```

**Response Error (403):**

```json
{
    "success": false,
    "message": "Bạn chỉ có thể import lịch cho sinh viên trong lớp mình phụ trách"
}
```

**Response Error (422):**

```json
{
    "success": false,
    "message": "File không hợp lệ. Vui lòng upload file Excel (.xlsx hoặc .xls)",
    "errors": {
        "file": ["The file must be a file of type: xlsx, xls."]
    }
}
```

**Response Error (500):**

```json
{
    "success": false,
    "message": "Lỗi khi import lịch học",
    "error": "Không tìm thấy mã sinh viên ở ô B5"
}
```

**Curl Example:**

```bash
curl -X POST https://api.example.com/api/admin/schedules/import \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@2001221474_NguyenThanhHoan.xls"
```

---

### 1.2. Import Hàng Loạt (Nhiều File)

**Endpoint:**

```http
POST /api/admin/schedules/import-batch
```

**Roles:** Admin only

**Content-Type:** `multipart/form-data`

**Mô tả:** API này cho phép Admin import lịch học cho nhiều sinh viên cùng một lúc bằng cách upload nhiều file Excel. Hệ thống sẽ xử lý từng file và trả về kết quả chi tiết cho từng file.

**Request Body:**

```
files[]: [File Excel 1]
files[]: [File Excel 2]
files[]: [File Excel 3]
...
```

**Validation:**

-   `files`: Required, phải là array, tối thiểu 1 file, tối đa 50 files
-   Mỗi file: Required, phải là file Excel (.xlsx hoặc .xls), tối đa 10MB

**Limit:** Tối đa 50 files/lần

**Response Success (200):**

```json
{
    "success": true,
    "message": "Hoàn thành import hàng loạt",
    "summary": {
        "total_files": 10,
        "success_count": 8,
        "failed_count": 1,
        "warning_count": 1
    },
    "details": {
        "success": [
            {
                "file_index": 1,
                "file_name": "2001221474.xls",
                "student_code": "2001221474",
                "student_name": "Nguyễn Thành Hoàn",
                "schedules_count": 15
            },
            {
                "file_index": 4,
                "file_name": "2001221475.xls",
                "student_code": "2001221475",
                "student_name": "Nguyễn Văn A",
                "schedules_count": 12
            }
        ],
        "warnings": [
            {
                "file_index": 2,
                "file_name": "2001222222.xls",
                "student_code": "2001222222",
                "message": "Sinh viên chưa tồn tại trong hệ thống"
            }
        ],
        "failed": [
            {
                "file_index": 3,
                "file_name": "invalid.xls",
                "error": "Không tìm thấy mã sinh viên ở ô C5"
            }
        ]
    }
}
```

**Response Error (403):**

```json
{
    "success": false,
    "message": "Chỉ Admin mới có quyền import hàng loạt"
}
```

**Response Error (422):**

```json
{
    "success": false,
    "message": "Dữ liệu không hợp lệ",
    "errors": {
        "files": ["The files field is required."],
        "files.0": ["The files.0 must be a file of type: xlsx, xls."]
    }
}
```

**Response Error (500):**

```json
{
    "success": false,
    "message": "Lỗi khi import hàng loạt",
    "error": "Database connection failed"
}
```

**Curl Example:**

```bash
curl -X POST https://api.example.com/api/admin/schedules/import-batch \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "files[]=@student1.xls" \
  -F "files[]=@student2.xls" \
  -F "files[]=@student3.xls"
```

**Lưu ý:**

-   API này chỉ dành cho Admin
-   Các file sẽ được xử lý tuần tự (không song song)
-   Nếu một file lỗi, các file khác vẫn được tiếp tục xử lý
-   File tạm sẽ được tự động xóa sau khi xử lý (dù thành công hay thất bại)
-   Mỗi file chỉ chứa lịch học của 1 sinh viên
-   Sinh viên chưa có trong MySQL sẽ được import vào MongoDB nhưng sẽ có cảnh báo

---

## 📄 2. Download Template

**Endpoint:**

```http
GET /api/admin/schedules/download-template
GET /api/advisor/schedules/download-template
```

**Roles:** Admin, Advisor

**Response:** File Excel (.xlsx)

**File Name:** `Mau_lich_hoc_sinh_vien_YYYYMMDDHHmmss.xlsx`

**Curl Example:**

```bash
curl -X GET https://api.example.com/api/admin/schedules/download-template \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -o template.xlsx
```

---

## 👁️ 3. Xem Lịch Học Sinh Viên

### 3.1. Xem Lịch 1 Sinh Viên

**Endpoint:**

```http
GET /api/admin/schedules/student/{student_id}
GET /api/advisor/schedules/student/{student_id}
GET /api/student/schedules/my-schedule
```

**Roles:**

-   Admin: Xem bất kỳ
-   Advisor: Xem SV trong lớp mình
-   Student: Chỉ xem của mình

**Query Parameters:**

-   `semester_id` (optional): ID học kỳ cụ thể

**Response Success - Xem 1 Học Kỳ (200):**

```json
{
    "success": true,
    "data": {
        "student": {
            "student_id": 5,
            "user_code": "2001221474",
            "full_name": "Nguyễn Thành Hoàn",
            "email": "student@example.com",
            "phone_number": "0901234567",
            "class_name": "13DHTH04",
            "faculty_name": "Khoa Công nghệ Thông tin",
            "advisor_name": "ThS. Nguyễn Văn Lễ",
            "status": "studying",
            "position": "member"
        },
        "semester": {
            "semester_id": 1,
            "semester_name": "Học kỳ 1",
            "academic_year": "2025-2026",
            "start_date": "2025-08-18",
            "end_date": "2026-01-15"
        },
        "schedule": {
            "semester": "Học kỳ 1",
            "academic_year": "2025-2026",
            "education_type": "Đại học",
            "major": "Công nghệ thông tin",
            "total_courses": 8,
            "registered_courses": [
                {
                    "course_class_code": "10109729802",
                    "course_name": "Sinh hoạt cuối khóa",
                    "instructors": [
                        "TS. Phạm Nguyễn Huy Phương",
                        "ThS. Lê Doãn Lâm",
                        "TS. Huỳnh Văn Tiến"
                    ],
                    "schedules": [
                        {
                            "type": "LT",
                            "start_date": "2025-12-04 00:00:00",
                            "end_date": "2025-12-04 00:00:00",
                            "day_of_week": 5,
                            "start_period": 2,
                            "end_period": 3,
                            "start_time": "07:45",
                            "end_time": "09:15",
                            "room": "HT.C (Hội trường C - Tầng 4 dãy nhà C) - 140 Lê Trọng Tấn",
                            "instructor": "TS. Phạm Nguyễn Huy Phương",
                            "note": "Lý thuyết",
                            "schedule_type": "Lịch học"
                        }
                    ]
                }
            ],
            "flat_schedule": [
                {
                    "course_class_code": "10109729802",
                    "course_name": "Sinh hoạt cuối khóa",
                    "instructors": [
                        "TS. Phạm Nguyễn Huy Phương",
                        "ThS. Lê Doãn Lâm"
                    ],
                    "instructor": "TS. Phạm Nguyễn Huy Phương",
                    "type": "LT",
                    "start_date": "2025-12-04 00:00:00",
                    "end_date": "2025-12-04 00:00:00",
                    "day_of_week": 5,
                    "periods": [2, 3],
                    "start_time_str": "07:45",
                    "end_time_str": "09:15",
                    "time_range": "07:45-09:15",
                    "room": "HT.C",
                    "schedule_type": "Lịch học"
                }
            ],
            "updated_at": "2025-11-27 10:30:00"
        },
        "has_schedule": true
    }
}
```

**Response Success - Xem Tất Cả Học Kỳ (200):**

```json
{
  "success": true,
  "data": {
    "student": { ... },
    "total_semesters": 3,
    "schedules": [
      {
        "semester": "Học kỳ 1",
        "academic_year": "2025-2026",
        "education_type": "Đại học",
        "major": "Công nghệ thông tin",
        "total_courses": 8,
        "registered_courses": [ ... ],
        "flat_schedule": [ ... ],
        "updated_at": "2025-11-27 10:30:00"
      }
    ]
  }
}
```

**Curl Example:**

```bash
# Xem 1 học kỳ
curl -X GET "https://api.example.com/api/admin/schedules/student/5?semester_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Xem tất cả học kỳ
curl -X GET "https://api.example.com/api/admin/schedules/student/5" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Student xem lịch mình
curl -X GET "https://api.example.com/api/student/schedules/my-schedule?semester_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 3.2. Xem Lịch Cả Lớp

**Endpoint:**

```http
GET /api/admin/schedules/class/{class_id}
GET /api/advisor/schedules/class/{class_id}
```

**Roles:**

-   Admin: Xem bất kỳ
-   Advisor: Xem lớp mình phụ trách

**Query Parameters (Required):**

-   `semester_id`: ID học kỳ

**Response Success (200):**

```json
{
    "success": true,
    "data": {
        "class": {
            "class_id": 1,
            "class_name": "13DHTH04",
            "description": "Lớp Đại học 2013 ngành CNTT",
            "advisor_name": "ThS. Nguyễn Văn Lễ",
            "advisor_email": "lecntp@gmail.com",
            "faculty_name": "Khoa Công nghệ Thông tin"
        },
        "semester": {
            "semester_id": 1,
            "semester_name": "Học kỳ 1",
            "academic_year": "2025-2026",
            "start_date": "2025-08-18",
            "end_date": "2026-01-15"
        },
        "summary": {
            "total_students": 35,
            "students_with_schedule": 32,
            "students_without_schedule": 3
        },
        "students": [
            {
                "student_id": 5,
                "user_code": "2001221474",
                "full_name": "Nguyễn Thành Hoàn",
                "email": "student@example.com",
                "phone_number": "0901234567",
                "position": "member",
                "status": "studying",
                "has_schedule": true,
                "schedule": {
                    "semester": "Học kỳ 1",
                    "academic_year": "2025-2026",
                    "education_type": "Đại học",
                    "major": "Công nghệ thông tin",
                    "total_schedules": 15,
                    "flat_schedule": [
                        {
                            "course_class_code": "10109729802",
                            "course_name": "Sinh hoạt cuối khóa",
                            "instructors": [
                                "TS. Phạm Nguyễn Huy Phương",
                                "ThS. Lê Doãn Lâm"
                            ],
                            "instructor": "TS. Phạm Nguyễn Huy Phương",
                            "type": "LT",
                            "start_date": "2025-12-04 00:00:00",
                            "end_date": "2025-12-04 00:00:00",
                            "day_of_week": 5,
                            "periods": [2, 3],
                            "start_time_str": "07:45",
                            "end_time_str": "09:15",
                            "time_range": "07:45-09:15",
                            "room": "HT.C",
                            "schedule_type": "Lịch học"
                        }
                    ],
                    "updated_at": "2025-11-27 10:30:00"
                }
            },
            {
                "student_id": 6,
                "user_code": "2001221475",
                "full_name": "Nguyễn Văn A",
                "email": "student2@example.com",
                "phone_number": "0901234568",
                "position": "member",
                "status": "studying",
                "has_schedule": false,
                "schedule": null
            }
        ]
    }
}
```

**Curl Example:**

```bash
curl -X GET "https://api.example.com/api/admin/schedules/class/1?semester_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🔍 4. Kiểm Tra Xung Đột Lịch

**Endpoint:**

```http
POST /api/schedules/check-conflict
```

**Roles:** Admin, Advisor, Student

**Request Body:**

```json
{
    "student_id": 5,
    "activity_id": 10,
    "semester_id": 1
}
```

**Response - Không Xung Đột (200):**

```json
{
    "success": true,
    "data": {
        "has_conflict": false,
        "activity": {
            "activity_id": 10,
            "title": "Workshop AI",
            "start_time": "2025-12-10 14:00:00",
            "end_time": "2025-12-10 16:00:00"
        }
    }
}
```

**Response - Có Xung Đột (200):**

```json
{
    "success": true,
    "data": {
        "has_conflict": true,
        "conflict_course": "Nhập môn Big Data",
        "conflict_course_class": "10110197104",
        "conflict_time": "09:40-11:55",
        "conflict_room": "B201 - 140 Lê Trọng Tấn",
        "conflict_instructor": "TS. Ngô Dương Hà",
        "conflict_periods": [4, 5, 6],
        "conflict_date_range": "2025-08-21 đến 2025-10-23",
        "conflict_date": "2025-12-10",
        "conflict_type": "LT",
        "conflict_schedule_type": "Lịch học",
        "activity": {
            "activity_id": 10,
            "title": "Workshop AI",
            "start_time": "2025-12-10 10:00:00",
            "end_time": "2025-12-10 12:00:00"
        }
    }
}
```

**Curl Example:**

```bash
curl -X POST https://api.example.com/api/schedules/check-conflict \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "student_id": 5,
    "activity_id": 10,
    "semester_id": 1
  }'
```

---

## 🗑️ 5. Xóa Lịch Học

**Endpoint:**

```http
DELETE /api/admin/schedules/student/{student_id}
```

**Roles:** Admin only

**Request Body:**

```json
{
    "semester_id": 1
}
```

**Response Success (200):**

```json
{
    "success": true,
    "message": "Đã xóa lịch học thành công"
}
```

**Response Not Found (404):**

```json
{
    "success": false,
    "message": "Không tìm thấy lịch học để xóa"
}
```

**Curl Example:**

```bash
curl -X DELETE https://api.example.com/api/admin/schedules/student/5 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"semester_id": 1}'
```

---

## 📋 Cấu Trúc File Excel

### Vị Trí Dữ Liệu

```
┌─────────────────────────────────────────────────────────┐
│ Dòng 1-2: Header trường + quốc gia                      │
├─────────────────────────────────────────────────────────┤
│ Dòng 3: LỊCH CỦA SINH VIÊN                              │
├─────────────────────────────────────────────────────────┤
│ Dòng 4: Trống                                           │
├─────────────────────────────────────────────────────────┤
│ Dòng 5: Mã SV (B5) | Họ tên (H5)                       │
│ Dòng 6: Lớp (B6) | Ngành (H6)                          │
│ Dòng 7: Hệ đào tạo (B7) | Loại đào tạo (H7)            │
├─────────────────────────────────────────────────────────┤
│ Dòng 8: Trống                                           │
├─────────────────────────────────────────────────────────┤
│ Dòng 9: HEADER BẢNG (12 cột)                           │
├─────────────────────────────────────────────────────────┤
│ Dòng 10+: DỮ LIỆU LỊCH HỌC                             │
└─────────────────────────────────────────────────────────┘
```

### 12 Cột Dữ Liệu (A-L)

| Cột | Tên           | Ví Dụ                  | Ghi Chú                |
| --- | ------------- | ---------------------- | ---------------------- |
| A   | STT           | 1, 2, 3                | Số thứ tự              |
| B   | Lớp học phần  | 10109729802            | Mã lớp học phần        |
| C   | Tên môn học   | Nhập môn Big Data      | Hoặc "(cùng môn trên)" |
| D   | Loại môn      | Lý thuyết / Thực hành  |                        |
| E   | Thứ           | 2, 3, 4, 5, 6, 7, CN   |                        |
| F   | Từ tiết       | 1-17                   | Tiết bắt đầu           |
| G   | Đến tiết      | 1-17                   | Tiết kết thúc          |
| H   | Ngày bắt đầu  | 04/12/2025             | dd/mm/yyyy             |
| I   | Ngày kết thúc | 04/12/2025             | dd/mm/yyyy             |
| J   | Giảng viên    | TS. Ngô Dương Hà       | Có học hàm học vị      |
| K   | Tên phòng     | A108 - Phòng máy tính  |                        |
| L   | Loại lịch     | Lịch học / Lịch học bù |                        |

### Quy Tắc Đặc Biệt

1. **Cùng môn, nhiều giảng viên:**

    - Dòng 1: Tên môn đầy đủ
    - Dòng 2+: `(cùng môn trên)`

2. **Lịch học bù:**

    - Cột L: `Lịch học bù`

3. **Tự động xác định học kỳ:**
    - Tháng 8-12: Học kỳ 1
    - Tháng 1-5: Học kỳ 2
    - Tháng 6-7: Học kỳ hè

---

## ⚠️ Error Codes

| Code | Message                            | Nguyên Nhân         |
| ---- | ---------------------------------- | ------------------- |
| 401  | Token không hợp lệ hoặc đã hết hạn | JWT expired/invalid |
| 403  | Bạn không có quyền truy cập        | Insufficient role   |
| 404  | Không tìm thấy                     | Resource not found  |
| 422  | Dữ liệu không hợp lệ               | Validation failed   |
| 500  | Lỗi server                         | Internal error      |

---

## 📊 Dữ Liệu Lưu Trong MongoDB

### Collection: `student_schedules`

```json
{
  "_id": ObjectId("..."),
  "student_code": "2001221474",
  "student_name": "Nguyễn Thành Hoàn",
  "class_name": "13DHTH04",
  "education_type": "Đại học",
  "education_mode": "Chính quy",
  "major": "Công nghệ thông tin",
  "semester": "Học kỳ 1",
  "academic_year": "2025-2026",
  "registered_courses": [
    {
      "course_class_code": "10109729802",
      "course_name": "Sinh hoạt cuối khóa",
      "instructors": ["TS. Phạm Nguyễn Huy Phương", "ThS. Lê Doãn Lâm"],
      "schedules": [
        {
          "type": "LT",
          "start_date": ISODate("2025-12-04T00:00:00Z"),
          "end_date": ISODate("2025-12-04T00:00:00Z"),
          "day_of_week": 5,
          "start_period": 2,
          "end_period": 3,
          "start_time": "07:45",
          "end_time": "09:15",
          "room": "HT.C",
          "instructor": "TS. Phạm Nguyễn Huy Phương",
          "note": "Lý thuyết",
          "schedule_type": "Lịch học"
        }
      ]
    }
  ],
  "flat_schedule": [
    {
      "course_class_code": "10109729802",
      "course_name": "Sinh hoạt cuối khóa",
      "instructors": ["TS. Phạm Nguyễn Huy Phương"],
      "instructor": "TS. Phạm Nguyễn Huy Phương",
      "type": "LT",
      "start_date": ISODate("2025-12-04T00:00:00Z"),
      "end_date": ISODate("2025-12-04T00:00:00Z"),
      "day_of_week": 5,
      "periods": [2, 3],
      "start_time_str": "07:45",
      "end_time_str": "09:15",
      "time_range": "07:45-09:15",
      "room": "HT.C",
      "schedule_type": "Lịch học"
    }
  ],
  "updated_at": ISODate("2025-11-27T03:30:00Z")
}
```

---

## 🔄 Flow Diagram

### Import Flow

```
User Upload Excel
    ↓
Validate File Format
    ↓
Read Student Info (B5, H5, B6, H6, B7, H7)
    ↓
Read Schedule Data (Row 10+)
    ↓
Auto Detect Semester
    ↓
Group by Course Class Code
    ↓
Save to MongoDB
    ↓
Check Student in MySQL
    ↓
Return Response
```

### Check Conflict Flow

```
Receive Activity Time
    ↓
Get Student Schedule from MongoDB
    ↓
Loop through Activity Dates
    ↓
For Each Date:
  - Check Day of Week
  - Check Date Range
  - Check Time Overlap
    ↓
Return Conflict or No Conflict
```

---

## 💡 Tips & Best Practices

### 1. Import Lịch Học

-   Download template trước khi import
-   Kiểm tra kỹ thông tin sinh viên (dòng 5-7)
-   Đảm bảo ngày tháng đúng định dạng dd/mm/yyyy
-   Import từng file để dễ debug lỗi
-   Dùng import-batch cho nhiều sinh viên

### 2. Kiểm Tra Xung Đột

-   Luôn check trước khi tạo hoạt động
-   Xử lý trường hợp xung đột một cách thân thiện
-   Hiển thị thông tin chi tiết về xung đột

### 3. Performance

-   Sử dụng index trong MongoDB:
    ```javascript
    db.student_schedules.createIndex({
        student_code: 1,
        semester: 1,
        academic_year: 1,
    });
    ```
-   Cache thông tin học kỳ hiện tại
-   Batch import cho nhiều file

### 4. Error Handling

-   Luôn kiểm tra JWT token
-   Validate dữ liệu trước khi xử lý
-   Log chi tiết để debug
-   Trả về message rõ ràng

---

## 📞 Support

Nếu gặp vấn đề, liên hệ:

-   Email: support@example.com
-   Slack: #schedule-support
-   Github Issues: https://github.com/your-repo/issues

---

**Version:** 1.0.0  
**Last Updated:** 2025-11-27  
**Author:** Development Team
