# Point Management Controller - Quản lý Điểm Rèn Luyện & CTXH

## 📋 Mục lục
- [Tổng quan](#tổng-quan)
- [Các chức năng](#các-chức-năng)
- [API Endpoints](#api-endpoints)
- [Quyền truy cập](#quyền-truy-cập)
- [Cấu trúc dữ liệu](#cấu-trúc-dữ-liệu)
- [Ví dụ sử dụng](#ví-dụ-sử-dụng)

## 🎯 Tổng quan

Controller này quản lý việc xem và cập nhật điểm rèn luyện, điểm CTXH (Công tác xã hội) của sinh viên theo từng học kỳ.

**Đặc điểm nổi bật:**
- ✅ Xem điểm ngay cả khi chưa có báo cáo chính thức
- ✅ Tự động tính điểm từ các hoạt động đã tham gia
- ✅ Phân quyền rõ ràng giữa Sinh viên và GVCN
- ✅ Hỗ trợ xem theo học kỳ cụ thể hoặc học kỳ hiện tại

## 🔧 Các chức năng

### 1. Xem điểm sinh viên (`getStudentPoints`)
- **Mô tả:** Xem chi tiết điểm rèn luyện và CTXH của một sinh viên
- **Vai trò:** Student (xem điểm của mình), Advisor (xem điểm sinh viên trong lớp)
- **Tính năng:**
  - Tự động tính điểm từ các hoạt động đã tham gia
  - Hiển thị cả điểm tạm tính và điểm chính thức (nếu có)
  - Liệt kê chi tiết các hoạt động đã tham gia

### 2. Cập nhật điểm sinh viên (`updateStudentPoints`)
- **Mô tả:** GVCN cập nhật điểm đánh giá chính thức cho sinh viên
- **Vai trò:** Advisor only
- **Tính năng:**
  - Tạo hoặc cập nhật báo cáo học kỳ
  - Nhập điểm rèn luyện và CTXH chính thức
  - Ghi nhận kết quả đánh giá (outcome)

### 3. Xem tổng quan điểm cả lớp (`getClassPointsSummary`)
- **Mô tả:** GVCN xem điểm của toàn bộ sinh viên trong lớp
- **Vai trò:** Advisor only
- **Tính năng:**
  - Hiển thị danh sách điểm của tất cả sinh viên
  - So sánh điểm từ hoạt động và điểm chính thức
  - Theo dõi tiến độ đánh giá của cả lớp

## 🌐 API Endpoints

### 1. Xem điểm sinh viên
```http
GET /api/student-points
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `student_id` (integer, required nếu role là advisor) - ID sinh viên cần xem
- `semester_id` (integer, optional) - ID học kỳ (mặc định: học kỳ hiện tại)

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "student_info": {
      "student_id": 1,
      "full_name": "Nguyễn Văn A",
      "user_code": "2021001"
    },
    "semester": {
      "semester_id": 1,
      "semester_name": "Học kỳ 1",
      "academic_year": "2024-2025"
    },
    "summary": {
      "training_point_from_activities": 45,
      "social_point_from_activities": 30,
      "training_point_summary": 50,
      "social_point_summary": 35,
      "has_official_report": true
    },
    "activities": [
      {
        "activity_title": "Ngày hội tình nguyện",
        "role_name": "Thành viên",
        "points_awarded": 10,
        "point_type": "ctxh",
        "activity_date": "2024-10-15 08:00:00"
      }
    ],
    "outcome": "Xuất sắc"
  }
}
```

**Response khi chưa có báo cáo (200):**
```json
{
  "success": true,
  "data": {
    "summary": {
      "training_point_from_activities": 45,
      "social_point_from_activities": 30,
      "training_point_summary": null,
      "social_point_summary": null,
      "has_official_report": false
    },
    "outcome": "Chưa có báo cáo chính thức",
    "note": "Điểm hiển thị là tổng điểm từ các hoạt động đã tham gia..."
  }
}
```

### 2. Cập nhật điểm sinh viên
```http
POST /api/student-points/update
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "student_id": 1,
  "semester_id": 1,
  "training_point_summary": 85,
  "social_point_summary": 40,
  "outcome": "Xuất sắc"
}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Cập nhật điểm thành công",
  "data": {
    "report_id": 1,
    "student_id": 1,
    "semester_id": 1,
    "training_point_summary": 85,
    "social_point_summary": 40,
    "outcome": "Xuất sắc"
  }
}
```

### 3. Xem tổng quan điểm cả lớp
```http
GET /api/class-points-summary
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `class_id` (integer, required) - ID lớp học
- `semester_id` (integer, required) - ID học kỳ

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "class_name": "CNTT K16",
    "semester_id": 1,
    "total_students": 35,
    "students": [
      {
        "student_id": 1,
        "user_code": "2021001",
        "full_name": "Nguyễn Văn A",
        "training_point_from_activities": 45,
        "social_point_from_activities": 30,
        "training_point_summary": 85,
        "social_point_summary": 40,
        "outcome": "Xuất sắc",
        "has_official_report": true
      },
      {
        "student_id": 2,
        "user_code": "2021002",
        "full_name": "Trần Thị B",
        "training_point_from_activities": 25,
        "social_point_from_activities": 15,
        "training_point_summary": null,
        "social_point_summary": null,
        "outcome": "Chưa có báo cáo",
        "has_official_report": false
      }
    ]
  }
}
```

## 🔐 Quyền truy cập

| Endpoint | Student | Advisor |
|----------|---------|---------|
| `getStudentPoints` | ✅ (chỉ xem điểm của mình) | ✅ (xem sinh viên trong lớp) |
| `updateStudentPoints` | ❌ | ✅ |
| `getClassPointsSummary` | ❌ | ✅ |

**Lưu ý:**
- Advisor chỉ có thể xem/cập nhật điểm cho sinh viên trong lớp mình phụ trách
- Student chỉ có thể xem điểm của chính mình

## 📊 Cấu trúc dữ liệu

### Bảng liên quan:
- `Students` - Thông tin sinh viên
- `SemesterReports` - Báo cáo điểm học kỳ
- `Semesters` - Thông tin học kỳ
- `ActivityRegistrations` - Đăng ký tham gia hoạt động
- `Activities` - Các hoạt động
- `ActivityRoles` - Vai trò trong hoạt động và điểm

### Loại điểm:
- **training_point** (điểm rèn luyện): 0-100
- **social_point** (điểm CTXH): 0-100

### Trạng thái tham gia:
- `attended` - Đã tham gia (được tính điểm)
- `registered` - Đã đăng ký (chưa tính điểm)
- `cancelled` - Đã hủy (không tính điểm)

## 💡 Ví dụ sử dụng

### Ví dụ 1: Sinh viên xem điểm của mình
```bash
curl -X GET "http://localhost:8000/api/student-points" \
  -H "Authorization: Bearer {student_token}"
```

### Ví dụ 2: GVCN xem điểm sinh viên cụ thể
```bash
curl -X GET "http://localhost:8000/api/student-points?student_id=5&semester_id=2" \
  -H "Authorization: Bearer {advisor_token}"
```

### Ví dụ 3: GVCN cập nhật điểm cho sinh viên
```bash
curl -X POST "http://localhost:8000/api/student-points/update" \
  -H "Authorization: Bearer {advisor_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "student_id": 5,
    "semester_id": 2,
    "training_point_summary": 80,
    "social_point_summary": 35,
    "outcome": "Tốt"
  }'
```

### Ví dụ 4: GVCN xem tổng quan điểm cả lớp
```bash
curl -X GET "http://localhost:8000/api/class-points-summary?class_id=10&semester_id=2" \
  -H "Authorization: Bearer {advisor_token}"
```

## 🔍 Validation Rules

### getStudentPoints:
- `student_id`: required nếu role là advisor, phải tồn tại trong DB
- `semester_id`: optional, phải tồn tại trong DB

### updateStudentPoints:
- `student_id`: required, phải tồn tại
- `semester_id`: required, phải tồn tại
- `training_point_summary`: optional, 0-100
- `social_point_summary`: optional, 0-100
- `outcome`: optional, max 255 ký tự

### getClassPointsSummary:
- `class_id`: required, phải tồn tại
- `semester_id`: required, phải tồn tại

## ⚠️ Error Codes

| Code | Message | Mô tả |
|------|---------|-------|
| 401 | Token không hợp lệ | Chưa đăng nhập hoặc token hết hạn |
| 403 | Không có quyền truy cập | Cố gắng truy cập dữ liệu không được phép |
| 404 | Không tìm thấy | Sinh viên, học kỳ hoặc lớp không tồn tại |
| 422 | Dữ liệu không hợp lệ | Validation lỗi |

## 🚀 Tính năng nổi bật

### 1. Tự động tính điểm từ hoạt động
Hệ thống tự động tính tổng điểm từ các hoạt động sinh viên đã tham gia:
- Chỉ tính hoạt động có trạng thái `attended`
- Chỉ tính hoạt động trong khoảng thời gian của học kỳ
- Phân loại theo `point_type`: ctxh hoặc ren_luyen

### 2. Xem điểm linh hoạt
- Không cần có báo cáo chính thức vẫn xem được điểm tạm tính
- Phân biệt rõ ràng giữa điểm tạm tính và điểm chính thức
- Tự động lấy học kỳ hiện tại nếu không chỉ định

### 3. Bảo mật và phân quyền
- GVCN chỉ xem/sửa sinh viên trong lớp mình
- Sinh viên chỉ xem được điểm của mình
- Sử dụng JWT middleware để xác thực

## 📝 Notes

- Điểm từ hoạt động được tính tự động, không cần cập nhật thủ công
- Báo cáo chính thức (training_point_summary, social_point_summary) do GVCN nhập
- Có thể có sự chênh lệch giữa điểm từ hoạt động và điểm báo cáo chính thức
- Hệ thống tự động lấy học kỳ hiện tại dựa trên ngày hiện tại