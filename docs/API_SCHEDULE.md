# API Documentation - Schedule Management System

## Tổng quan

Hệ thống quản lý lịch học sinh viên với MongoDB, hỗ trợ:
- ✅ **Nhiều giai đoạn học** (Lý thuyết, Thực hành) với ngày bắt đầu/kết thúc riêng
- ✅ **Phân biệt LT/TH** - Tự động phát hiện từ cột "Ghi chú"
- ✅ **Kiểm tra xung đột chính xác** - So sánh thời gian thực (H:i format)
- ✅ **Import Excel** - Template chuẩn với 2 sheets
- ✅ **Hybrid Database** - MySQL (Semesters) + MongoDB (Schedules)

### Luồng hoạt động

1. **Import Excel**: Admin import file Excel chứa lịch học và đăng ký môn của sinh viên
2. **Lưu MongoDB**: Dữ liệu được lưu vào MongoDB với `semester` và `academic_year` từ Excel
3. **Check Conflict**: 
   - Client gửi request với `student_id`, `activity_id`, và `semester_id`
   - Backend lấy thông tin thời gian từ bảng `Activities` (MySQL)
   - Backend lấy `semester_name` và `academic_year` từ bảng `Semesters` (MySQL)
   - Dùng thông tin này để query lịch học trong MongoDB
   - So sánh thời gian để phát hiện xung đột

---

## 1. MongoDB Schema

### 1.1. Collection: `course_schedules`

Lưu thông tin lịch học của từng môn, mỗi môn có thể có nhiều giai đoạn.

```javascript
{
  _id: ObjectId("..."),
  course_code: "IT001",                    // Mã môn học
  course_name: "Nhập môn Lập trình",       // Tên môn học
  instructor: "ThS. Nguyễn Văn A",         // Giảng viên
  
  schedules: [                              // Danh sách giai đoạn
    {
      phase: "Lý thuyết",                   // Tên giai đoạn
      type: "LT",                           // LT hoặc TH (tự động phát hiện)
      start_date: ISODate("2024-09-05"),    // Ngày bắt đầu giai đoạn
      end_date: ISODate("2024-10-30"),      // Ngày kết thúc giai đoạn
      day_of_week: 2,                       // 2=Thứ 2, 3=Thứ 3, ..., 8=CN
      start_period: 1,                      // Tiết bắt đầu (1-17)
      end_period: 3,                        // Tiết kết thúc (1-17)
      start_time: "07:00",                  // Giờ bắt đầu (string H:i)
      end_time: "09:15",                    // Giờ kết thúc (string H:i)
      room: "B.101",                        // Phòng học
      note: "Lý thuyết"                     // Ghi chú
    },
    {
      phase: "Thực hành",
      type: "TH",
      start_date: ISODate("2024-11-01"),
      end_date: ISODate("2024-12-15"),
      day_of_week: 4,
      start_period: 4,
      end_period: 6,
      start_time: "09:15",
      end_time: "11:30",
      room: "H.201",
      note: "Thực hành"
    }
  ],
  
  updated_at: ISODate("2025-11-13T10:00:00Z")
}
```

**Indexes:**
```javascript
db.course_schedules.createIndex({ course_code: 1, academic_year: 1 }, { unique: true })
db.course_schedules.createIndex({ "schedules.day_of_week": 1 })
db.course_schedules.createIndex({ "schedules.start_date": 1, "schedules.end_date": 1 })
```

---

### 1.2. Collection: `student_schedules`

Lưu lịch học của từng sinh viên trong học kỳ.

**Lưu ý quan trọng:** 
- `semester` và `academic_year` trong MongoDB được lấy từ bảng `Semesters` trong MySQL
- Khi import Excel, hệ thống sẽ tự động map từ dữ liệu Excel sang MongoDB
- Khi query, API sẽ lấy thông tin từ MySQL (bằng `semester_id`) rồi dùng để tìm trong MongoDB

```javascript
{
  _id: ObjectId("..."),
  student_id: 1,                            // ID sinh viên (từ SQL)
  student_code: "210001",                   // MSSV
  student_name: "Nguyễn Văn Hùng",          // Họ tên
  class_id: 1,                              // ID lớp (từ SQL)
  class_name: "DH21CNTT",                   // Tên lớp
  semester: "1",                            // Tên học kỳ (HK1, HK2, HK3, Hè) - từ Semesters.semester_name
  academic_year: "2024-2025",               // Năm học - từ Semesters.academic_year
  
  registered_courses: [                     // Danh sách môn đã đăng ký
    {
      course_code: "IT001",
      course_name: "Nhập môn Lập trình",
      schedules: [
        {
          phase: "Lý thuyết",
          type: "LT",
          start_date: ISODate("2024-09-05"),
          end_date: ISODate("2024-10-30"),
          day_of_week: 2,
          start_period: 1,
          end_period: 3,
          start_time: "07:00",
          end_time: "09:15",
          room: "B.101",
          note: "Lý thuyết"
        },
        {
          phase: "Thực hành",
          type: "TH",
          start_date: ISODate("2024-11-01"),
          end_date: ISODate("2024-12-15"),
          day_of_week: 4,
          start_period: 4,
          end_period: 6,
          start_time: "09:15",
          end_time: "11:30",
          room: "H.201",
          note: "Thực hành"
        }
      ]
    }
  ],
  
  flat_schedule: [                          // Lịch học phẳng (để query nhanh)
    {
      course_code: "IT001",
      phase: "Lý thuyết",
      start_date: ISODate("2024-09-05"),
      end_date: ISODate("2024-10-30"),
      day_of_week: 2,
      periods: [1, 2, 3],
      start_time_str: "07:00",              // String để so sánh conflict
      end_time_str: "09:15",                // String để so sánh conflict
      time_range: "07:00-09:15",            // Hiển thị
      room: "B.101"
    },
    {
      course_code: "IT001",
      phase: "Thực hành",
      start_date: ISODate("2024-11-01"),
      end_date: ISODate("2024-12-15"),
      day_of_week: 4,
      periods: [4, 5, 6],
      start_time_str: "09:15",
      end_time_str: "11:30",
      time_range: "09:15-11:30",
      room: "H.201"
    }
  ],
  
  updated_at: ISODate("2025-11-13T10:00:00Z")
}
```

**Indexes:**
```javascript
db.student_schedules.createIndex({ student_id: 1, semester: 1, academic_year: 1 }, { unique: true })
db.student_schedules.createIndex({ class_id: 1, semester: 1, academic_year: 1 })
db.student_schedules.createIndex({ student_code: 1, semester: 1, academic_year: 1 })
db.student_schedules.createIndex({ "flat_schedule.day_of_week": 1 })
db.student_schedules.createIndex({ "flat_schedule.start_date": 1, "flat_schedule.end_date": 1 })
```

---

## 2. Excel Template Structure

### 2.1. Sheet 1: "Lịch lớp học"

| Cột | Tên | Kiểu dữ liệu | Bắt buộc | Ví dụ | Ghi chú |
|-----|-----|--------------|----------|-------|---------|
| A | STT | Number | Không | 1 | Số thứ tự |
| B | Mã lớp học | String | **Có** | IT001 | Mã môn học (unique) |
| C | Tên môn học | String | **Có** | Nhập môn Lập trình | Tên đầy đủ |
| D | Giảng viên | String | **Có** | ThS. Nguyễn Văn A | Họ tên GV |
| E | Giai đoạn | String | Không | Lý thuyết | Tên giai đoạn, mặc định "Toàn khóa" |
| F | Ngày bắt đầu | Date | **Có** | 05/09/2024 | Format: dd/mm/yyyy |
| G | Ngày kết thúc | Date | **Có** | 30/10/2024 | Format: dd/mm/yyyy |
| H | Thứ | Number/String | **Có** | 2 | 2-7 hoặc CN |
| I | Tiết bắt đầu | Number | **Có** | 1 | 1-17 |
| J | Tiết kết thúc | Number | **Có** | 3 | 1-17 |
| K | Phòng | String | **Có** | B.101 | Mã phòng học |
| L | Ghi chú | String | Không | Lý thuyết | Dùng để phát hiện LT/TH |

**Data Validation Rules:**
```excel
Cột H (Thứ): List = 2,3,4,5,6,7,CN
Cột I,J (Tiết): Whole number, Between 1 and 17
Cột F,G (Ngày): Date format dd/mm/yyyy
```

**Tự động phát hiện LT/TH:**
- Nếu cột "Ghi chú" chứa: `thực hành`, `thuc hanh`, `(th)`, `phòng máy`, `pm` → Type = **TH**
- Ngược lại → Type = **LT**

**Ví dụ dữ liệu:**
```
1  IT001  Nhập môn Lập trình  ThS. Nguyễn Văn A  Lý thuyết   05/09/2024  30/10/2024  2  1  3  B.101  Lý thuyết
2  IT001  Nhập môn Lập trình  ThS. Nguyễn Văn A  Thực hành  01/11/2024  15/12/2024  4  4  6  H.201  Thực hành
3  BA001  Kinh tế vi mô       TS. Trần Thị B     Toàn khóa  05/09/2024  15/12/2024  3  7  9  A.305  Lý thuyết
```

---

### 2.2. Sheet 2: "Đăng ký lớp"

| Cột | Tên | Kiểu dữ liệu | Bắt buộc | Ví dụ |
|-----|-----|--------------|----------|-------|
| A | STT | Number | Không | 1 |
| B | MSSV | String | **Có** | 210001 |
| C | Họ tên | String | **Có** | Nguyễn Văn Hùng |
| D | Lớp | String | **Có** | DH21CNTT |
| E | Mã lớp học | String | **Có** | IT001 |
| F | Học kỳ | String | **Có** | HK1 |
| G | Năm học | String | **Có** | 2024-2025 |

**Ví dụ dữ liệu:**
```
1  210001  Nguyễn Văn Hùng     DH21CNTT  IT001  HK1  2024-2025
2  210001  Nguyễn Văn Hùng     DH21CNTT  BA001  HK1  2024-2025
3  210002  Trần Thị Thu Cẩm    DH21CNTT  IT001  HK1  2024-2025
```

---

## 3. Time Mapping (Tiết → Giờ)

### 3.1. Lý thuyết (LT)

| Tiết | Giờ bắt đầu | Giờ kết thúc | Ca |
|------|-------------|--------------|-----|
| 1 | 07:00 | 07:45 | Sáng |
| 2 | 07:45 | 08:30 | Sáng |
| 3 | 08:30 | 09:15 | Sáng |
| 4 | 09:40 | 10:25 | Sáng |
| 5 | 10:25 | 11:10 | Sáng |
| 6 | 11:10 | 11:55 | Sáng |
| 7 | 12:30 | 13:15 | Chiều |
| 8 | 13:15 | 14:00 | Chiều |
| 9 | 14:00 | 14:45 | Chiều |
| 10 | 15:10 | 15:55 | Chiều |
| 11 | 15:55 | 16:40 | Chiều |
| 12 | 16:40 | 17:25 | Chiều |
| 13 | 18:00 | 18:45 | Tối |
| 14 | 18:45 | 19:30 | Tối |
| 15 | 19:30 | 20:15 | Tối |
| 16 | 20:15 | 21:00 | Tối |
| 17 | 21:00 | 21:45 | Tối |

---

### 3.2. Thực hành (TH)

| Tiết | Giờ bắt đầu | Giờ kết thúc | Ca |
|------|-------------|--------------|-----|
| 1 | 07:00 | 07:45 | Sáng |
| 2 | 07:45 | 08:30 | Sáng |
| 3 | 08:30 | 09:15 | Sáng |
| 4 | 09:15 | 10:00 | Sáng |
| 5 | 10:00 | 10:45 | Sáng |
| 6 | 10:45 | 11:30 | Sáng |
| 7 | 12:30 | 13:15 | Chiều |
| 8 | 13:15 | 14:00 | Chiều |
| 9 | 14:00 | 14:45 | Chiều |
| 10 | 14:45 | 15:30 | Chiều |
| 11 | 15:30 | 16:15 | Chiều |
| 12 | 16:15 | 17:00 | Chiều |
| 13 | 18:00 | 18:45 | Tối |
| 14 | 18:45 | 19:30 | Tối |
| 15 | 19:30 | 20:15 | Tối |
| 16 | 20:15 | 21:00 | Tối |
| 17 | 21:00 | 21:45 | Tối |

**Lưu ý:** Thực hành có khoảng nghỉ khác (tiết 4-6 liên tục không có break).

---

## 4. API Endpoints

### 4.1. Import Schedule

**Import lịch học từ file Excel vào MongoDB**

**Endpoint:**
```
POST /api/admin/schedules/import
```

**Role:** `admin` only

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```
file: lich_hoc_sinh_vien.xlsx
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Import thành công",
  "data": {
    "courses_imported": 45,
    "students_imported": 150
  }
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "File không hợp lệ",
  "errors": {
    "file": ["The file field is required."]
  }
}
```

**Response Error (500):**
```json
{
  "success": false,
  "message": "Lỗi khi import: Invalid date format for course IT001"
}
```

---

### 4.2. Check Schedule Conflict

**Kiểm tra xung đột lịch học của sinh viên với hoạt động**

**Endpoint:**
```
POST /api/schedules/check-conflict
```

**Role:** `student` (own), `advisor`, `admin`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "student_id": 1,
  "activity_id": 5,
  "semester_id": 1
}
```

**Validation Rules:**
```
student_id: required|integer|exists:Students,student_id
activity_id: required|integer|exists:Activities,activity_id
semester_id: required|integer|exists:Semesters,semester_id
```

**Lưu ý:** 
- API sẽ tự động lấy `start_time` và `end_time` từ bảng `Activities` trong MySQL
- API sẽ tự động lấy `semester_name` và `academic_year` từ bảng `Semesters` trong MySQL
- Sau đó sử dụng thông tin này để query lịch học trong MongoDB

**Response Success - No Conflict (200):**
```json
{
  "success": true,
  "data": {
    "has_conflict": false
  }
}
```

**Response Success - Has Conflict (200):**
```json
{
  "success": true,
  "data": {
    "has_conflict": true,
    "conflict_course": "IT001",
    "conflict_phase": "Lý thuyết",
    "conflict_time": "14:00-16:40",
    "conflict_room": "B.101",
    "conflict_periods": [9, 10, 11],
    "conflict_date_range": "2024-09-05 đến 2024-10-30",
    "activity": {
      "activity_id": 5,
      "title": "Workshop AI",
      "start_time": "2024-10-15 14:00:00",
      "end_time": "2024-10-15 16:00:00"
    }
  }
}
```

**Response Error (422):**
```json
{
  "success": false,
  "errors": {
    "student_id": ["The student id field is required."]
  }
}
```

---

### 4.3. Get Available Students for Activity

**Lấy danh sách sinh viên có thể tham gia hoạt động (không trùng lịch)**

**Endpoint:**
```
GET /api/advisor/activities/{activity_id}/available-students
```

**Role:** `advisor` only (người tạo hoạt động)

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "activity": {
      "activity_id": 1,
      "title": "Workshop AI",
      "start_time": "2024-10-15 14:00:00",
      "end_time": "2024-10-15 16:00:00",
      "status": "upcoming"
    },
    "semester": {
      "semester_id": 1,
      "semester": "1",
      "academic_year": "2024-2025"
    },
    "summary": {
      "total_students": 120,
      "available_count": 95,
      "unavailable_count": 25
    },
    "available_students": [
      {
        "student_id": 1,
        "user_code": "210001",
        "full_name": "Nguyễn Văn Hùng",
        "email": "sv.hung@school.edu.vn",
        "phone_number": "091122334",
        "class_name": "DH21CNTT",
        "training_point": 85,
        "social_point": 15,
        "can_assign": true,
        "reason_cannot_assign": null
      }
    ],
    "unavailable_students": [
      {
        "student_id": 2,
        "user_code": "210002",
        "full_name": "Trần Thị Thu Cẩm",
        "email": "sv.cam@school.edu.vn",
        "phone_number": "091234567",
        "class_name": "DH21CNTT",
        "training_point": 70,
        "social_point": 5,
        "can_assign": false,
        "reason_cannot_assign": "Trùng môn IT001 (14:00-16:40)"
      }
    ]
  }
}
```

**Response Error (404):**
```json
{
  "success": false,
  "message": "Hoạt động không tồn tại"
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

### 4.4. Assign Students to Activity

**Phân công sinh viên tham gia hoạt động (chỉ sinh viên không trùng lịch)**

**Endpoint:**
```
POST /api/advisor/activities/{activity_id}/assign-students
```

**Role:** `advisor` only (người tạo hoạt động)

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "assignments": [
    {
      "student_id": 1,
      "activity_role_id": 2
    },
    {
      "student_id": 3,
      "activity_role_id": 2
    },
    {
      "student_id": 5,
      "activity_role_id": 3
    }
  ]
}
```

**Validation Rules:**
```
assignments: required|array|min:1
assignments.*.student_id: required|integer|exists:Students,student_id
assignments.*.activity_role_id: required|integer|exists:Activity_Roles,activity_role_id
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Phân công thành công 2 sinh viên, bỏ qua 1",
  "data": {
    "total_assigned": 2,
    "total_skipped": 1,
    "assigned": [
      {
        "registration_id": 101,
        "student_id": 1,
        "student_code": "210001",
        "student_name": "Nguyễn Văn Hùng",
        "role_name": "Người tham dự",
        "points_awarded": 8,
        "point_type": "ren_luyen"
      },
      {
        "registration_id": 102,
        "student_id": 3,
        "student_code": "220001",
        "student_name": "Lê Văn Dũng",
        "role_name": "Người tham dự",
        "points_awarded": 8,
        "point_type": "ren_luyen"
      }
    ],
    "skipped": [
      {
        "student_id": 5,
        "student_name": "Bùi Thị Hương",
        "reason": "Trùng môn IT001 (14:00-16:40)"
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

---

### 4.5. Download Template

**Tải file Excel template mẫu**

**Endpoint:**
```
GET /api/schedules/template/download
```

**Role:** `admin`, `advisor`

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
- File download: `lich_hoc_template_20251113.xlsx`

---

## 5. Conflict Detection Logic

### 5.1. Thuật toán kiểm tra xung đột

Hệ thống sử dụng **3 bước kiểm tra**:

#### Bước 1: Kiểm tra Thứ
```php
if ($schedule['day_of_week'] != $activityDayOfWeek) {
    continue; // Không trùng thứ → Không xung đột
}
```

#### Bước 2: Kiểm tra Giai đoạn (Ngày)
```php
$activityDate = "2024-10-15";
$schedStart = "2024-09-05";
$schedEnd = "2024-10-30";

if ($activityDate < $schedStart || $activityDate > $schedEnd) {
    continue; // Hoạt động không nằm trong giai đoạn học → Không xung đột
}
```

#### Bước 3: Kiểm tra Giờ trùng
```php
// Logic overlap: (StartA < EndB) AND (EndA > StartB)
$actStartStr = "14:00";
$actEndStr = "16:00";
$classStartStr = "14:00";
$classEndStr = "16:40";

if ($actStartStr < $classEndStr && $actEndStr > $classStartStr) {
    return ['has_conflict' => true]; // Có xung đột
}
```

### 5.2. Ví dụ thực tế

**Lịch học sinh viên:**
- Môn IT001 - Lý thuyết
  - Giai đoạn: 05/09/2024 → 30/10/2024
  - Thứ 3 (day_of_week = 3)
  - Giờ: 14:00 → 16:40

**Hoạt động:**
- Ngày: 15/10/2024 (Thứ 3)
- Giờ: 14:00 → 16:00

**Kết quả:**
1. ✅ Cùng thứ 3
2. ✅ Ngày 15/10 nằm trong giai đoạn 05/09 → 30/10
3. ✅ Giờ trùng: 14:00 < 16:40 AND 16:00 > 14:00

→ **Có xung đột!**

---

## 6. Error Codes

| HTTP Code | Error Type | Description |
|-----------|------------|-------------|
| 200 | Success | Thành công |
| 400 | Bad Request | Dữ liệu không hợp lệ (status, logic) |
| 401 | Unauthorized | Chưa đăng nhập hoặc token hết hạn |
| 403 | Forbidden | Không có quyền truy cập |
| 404 | Not Found | Không tìm thấy tài nguyên |
| 422 | Validation Error | Validation failed |
| 500 | Server Error | Lỗi server |

---

## 7. Testing Guide

### 7.1. Test Import Schedule

**Postman:**
```bash
POST http://localhost:8000/api/admin/schedules/import
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data

Body:
- file: lich_hoc_sinh_vien.xlsx
```

**cURL:**
```bash
curl -X POST \
  http://localhost:8000/api/admin/schedules/import \
  -H 'Authorization: Bearer {admin_token}' \
  -F 'file=@/path/to/lich_hoc_sinh_vien.xlsx'
```

---

### 7.2. Test Check Conflict

**Postman:**
```bash
POST http://localhost:8000/api/schedules/check-conflict
Authorization: Bearer {token}
Content-Type: application/json

Body (JSON):
{
  "student_id": 1,
  "activity_id": 5,
  "semester_id": 1
}
```

**cURL:**
```bash
curl -X POST \
  http://localhost:8000/api/schedules/check-conflict \
  -H 'Authorization: Bearer {token}' \
  -H 'Content-Type: application/json' \
  -d '{
    "student_id": 1,
    "activity_id": 5,
    "semester_id": 1
  }'
```

---

### 7.3. Test Get Available Students

**Postman:**
```bash
GET http://localhost:8000/api/advisor/activities/1/available-students
Authorization: Bearer {advisor_token}
```

**Expected Response:**
- Danh sách `available_students`: Sinh viên không trùng lịch
- Danh sách `unavailable_students`: Sinh viên trùng lịch với `reason_cannot_assign`

---

## 8. Setup Instructions

### 8.1. MongoDB Setup

```bash
# Install MongoDB
sudo apt-get install mongodb

# Start MongoDB
sudo systemctl start mongodb

# Create database
mongo
use advisor_system

# Create indexes
db.course_schedules.createIndex({ course_code: 1 })
db.student_schedules.createIndex({ student_id: 1, semester: 1, academic_year: 1 }, { unique: true })
db.student_schedules.createIndex({ student_code: 1, semester: 1, academic_year: 1 })
```

---

### 8.2. Laravel Setup

**.env:**
```env
MONGODB_URI=mongodb://localhost:27017
MONGODB_DATABASE=advisor_system
```

**Install PHP MongoDB Extension:**
```bash
sudo pecl install mongodb
echo "extension=mongodb.so" | sudo tee -a /etc/php/8.2/cli/php.ini
```

**Install Composer Package:**
```bash
composer require mongodb/laravel-mongodb
```

---

### 8.3. Import Sample Data

1. Download template: `/api/schedules/template/download`
2. Fill data theo hướng dẫn
3. Import: `POST /api/admin/schedules/import`

---