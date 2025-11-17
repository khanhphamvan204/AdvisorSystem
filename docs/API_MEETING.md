# Tài liệu API - Module Quản lý Cuộc họp lớp

## Mục lục
1. [Tổng quan](#tổng-quan)
2. [Authentication](#authentication)
3. [Endpoints](#endpoints)
4. [Error Codes](#error-codes)
5. [Examples](#examples)

---

## Tổng quan

Module quản lý cuộc họp lớp bao gồm các chức năng:
- Tạo, xem, sửa, xóa cuộc họp
- Điểm danh sinh viên
- Xuất biên bản họp tự động
- Upload/Download biên bản
- Sinh viên gửi feedback
- Thống kê cuộc họp

**Base URL**: `https://api.example.com/api`

---

## Authentication

Tất cả endpoints yêu cầu JWT token trong header:

```http
Authorization: Bearer {your_jwt_token}
```

### Phân quyền

| Role | Quyền hạn |
|------|-----------|
| **Student** | Xem cuộc họp lớp mình, tải biên bản, gửi feedback |
| **Advisor** | Toàn quyền với cuộc họp của lớp mình phụ trách |
| **Admin** | Toàn quyền với tất cả cuộc họp |

---

## Endpoints

### 1. Lấy danh sách cuộc họp

```http
GET /api/meetings
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `class_id` | integer | No | Lọc theo lớp (chỉ advisor/admin) |
| `status` | string | No | Lọc theo trạng thái: `scheduled`, `completed`, `cancelled` |
| `from_date` | date | No | Lọc từ ngày (YYYY-MM-DD) |
| `to_date` | date | No | Lọc đến ngày (YYYY-MM-DD) |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "meeting_id": 1,
        "advisor_id": 1,
        "class_id": 1,
        "title": "Họp lớp DH21CNTT tháng 3/2025",
        "summary": "Thông báo điểm rèn luyện...",
        "class_feedback": "Lớp không có ý kiến.",
        "meeting_link": null,
        "location": "Phòng B.101",
        "meeting_time": "2025-03-15 10:00:00",
        "end_time": "2025-03-15 11:30:00",
        "status": "completed",
        "minutes_file_path": "meetings/BienBan_DH21CNTT_15032025.docx",
        "advisor": {
          "advisor_id": 1,
          "full_name": "ThS. Trần Văn An",
          "email": "gv.an@school.edu.vn"
        },
        "class": {
          "class_id": 1,
          "class_name": "DH21CNTT"
        },
        "attendees": [
          {
            "meeting_student_id": 1,
            "student_id": 1,
            "attended": true,
            "student": {
              "student_id": 1,
              "full_name": "Nguyễn Văn Hùng",
              "position": "leader"
            }
          }
        ]
      }
    ],
  }
}
```

---

### 2. Xem chi tiết cuộc họp

```http
GET /api/meetings/{id}
```

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | ID cuộc họp |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "meeting_id": 1,
    "title": "Họp lớp DH21CNTT tháng 3/2025",
    "summary": "...",
    "class_feedback": "...",
    "meeting_time": "2025-03-15 10:00:00",
    "end_time": "2025-03-15 11:30:00",
    "status": "completed",
    "minutes_file_path": "meetings/BienBan_DH21CNTT_15032025.docx",
    "advisor": {...},
    "class": {...},
    "attendees": [...],
    "feedbacks": [
      {
        "feedback_id": 1,
        "student_id": 1,
        "feedback_content": "Em thấy biên bản ghi thiếu...",
        "created_at": "2025-03-16 08:00:00",
        "student": {
          "full_name": "Nguyễn Văn Hùng"
        }
      }
    ]
  }
}
```

---

### 3. Tạo cuộc họp mới

```http
POST /api/meetings
```

**Quyền**: Advisor, Admin

**Request Body:**

```json
{
  "class_id": 1,
  "title": "Họp lớp DH21CNTT tháng 4/2025",
  "summary": "Thông báo lịch thi cuối kỳ...",
  "class_feedback": null,
  "meeting_link": "https://meet.google.com/abc-defg-hij",
  "location": "Họp Online",
  "meeting_time": "2025-04-15 14:00:00",
  "end_time": "2025-04-15 15:30:00"
}
```

**Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `class_id` | integer | Yes | ID lớp |
| `title` | string | Yes | Tiêu đề (max: 255) |
| `summary` | string | No | Nội dung họp |
| `class_feedback` | string | No | Ý kiến đóng góp của lớp |
| `meeting_link` | string (URL) | No | Link họp online (max: 2083) |
| `location` | string | No | Địa điểm (max: 255) |
| `meeting_time` | datetime | Yes | Thời gian bắt đầu |
| `end_time` | datetime | No | Thời gian kết thúc |

**Lưu ý:** Hệ thống sẽ tự động gán cuộc họp cho TẤT CẢ sinh viên trong lớp được chọn.

**Response Success (201):**

```json
{
  "success": true,
  "message": "Tạo cuộc họp thành công",
  "data": {
    "meeting_id": 5,
    "title": "Họp lớp DH21CNTT tháng 4/2025",
    "status": "scheduled",
    ...
  }
}
```

---

### 4. Cập nhật cuộc họp

```http
PUT /api/meetings/{id}
```

**Quyền**: Advisor (của lớp), Admin

**Request Body:**

```json
{
  "title": "Họp lớp DH21CNTT tháng 4/2025 (Cập nhật)",
  "meeting_time": "2025-04-16 14:00:00",
  "status": "completed"
}
```

**Fields:** Tất cả fields đều optional (chỉ gửi fields cần update)

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Tiêu đề |
| `summary` | string | Nội dung họp |
| `class_feedback` | string | Ý kiến lớp |
| `meeting_link` | string | Link họp |
| `location` | string | Địa điểm |
| `meeting_time` | datetime | Thời gian bắt đầu |
| `end_time` | datetime | Thời gian kết thúc |
| `status` | string | Trạng thái: `scheduled`, `completed`, `cancelled` |

**Response Success (200):**

```json
{
  "success": true,
  "message": "Cập nhật cuộc họp thành công",
  "data": {...}
}
```

---

### 5. Xóa cuộc họp

```http
DELETE /api/meetings/{id}
```

**Quyền**: Advisor (của lớp), Admin

**Response Success (200):**

```json
{
  "success": true,
  "message": "Xóa cuộc họp thành công"
}
```

---

### 6. Điểm danh sinh viên

```http
POST /api/meetings/{id}/attendance
```

**Quyền**: Advisor (của lớp), Admin

**Request Body:**

```json
{
  "attendances": [
    {
      "student_id": 1,
      "attended": true
    },
    {
      "student_id": 2,
      "attended": false
    },
    {
      "student_id": 3,
      "attended": true
    }
  ]
}
```

**Response Success (200):**

```json
{
  "success": true,
  "message": "Điểm danh thành công",
  "data": {
    "meeting_id": 1,
    "status": "completed",
    "attendees": [...]
  }
}
```

**Lưu ý:** Tự động chuyển trạng thái cuộc họp sang `completed` nếu đang `scheduled`

---

### 7. Xuất biên bản họp tự động

```http
GET /api/meetings/{id}/export-minutes
```

**Quyền**: Advisor (của lớp), Admin

**Description**: Tự động tạo biên bản từ template và dữ liệu cuộc họp

**Response**: File .docx (download)

**Headers:**

```
Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document
Content-Disposition: attachment; filename="BienBan_DH21CNTT_15032025.docx"
```

**Error Response (500):**

```json
{
  "success": false,
  "message": "Không tìm thấy file template biên bản"
}
```

---

### 8. Upload biên bản thủ công

```http
POST /api/meetings/{id}/upload-minutes
```

**Quyền**: Advisor (của lớp), Admin

**Content-Type**: `multipart/form-data`

**Form Data:**

| Field | Type | Description |
|-------|------|-------------|
| `minutes_file` | file | File biên bản (.doc, .docx, .pdf, max 10MB) |

**Example Request (cURL):**

```bash
curl -X POST https://api.example.com/api/meetings/1/upload-minutes \
  -H "Authorization: Bearer {token}" \
  -F "minutes_file=@/path/to/bienban.docx"
```

**Response Success (200):**

```json
{
  "success": true,
  "message": "Upload biên bản thành công",
  "data": {
    "file_path": "meetings/BienBan_DH21CNTT_1710500000.docx",
    "file_url": "/storage/meetings/BienBan_DH21CNTT_1710500000.docx"
  }
}
```

---

### 9. Tải biên bản đã lưu

```http
GET /api/meetings/{id}/download-minutes
```

**Quyền**: Tất cả (nhưng phải thuộc lớp hoặc là CVHT/Admin)

**Response**: File download

**Error Response (404):**

```json
{
  "success": false,
  "message": "Biên bản chưa được tạo"
}
```

---

### 10. Xóa biên bản

```http
DELETE /api/meetings/{id}/minutes
```

**Quyền**: Advisor (của lớp), Admin

**Response Success (200):**

```json
{
  "success": true,
  "message": "Xóa biên bản thành công"
}
```

---

### 11. Cập nhật nội dung họp & ý kiến lớp

```http
PUT /api/meetings/{id}/summary
```

**Quyền**: Advisor (của lớp), Admin

**Request Body:**

```json
{
  "summary": "Thông báo về danh sách điểm rèn luyện HK2...",
  "class_feedback": "Lớp không có ý kiến."
}
```

**Response Success (200):**

```json
{
  "success": true,
  "message": "Cập nhật nội dung họp thành công",
  "data": {...}
}
```

---

### 12. Sinh viên gửi feedback

```http
POST /api/meetings/{id}/feedbacks
```

**Quyền**: Student (của lớp)

**Request Body:**

```json
{
  "feedback_content": "Em thấy biên bản họp ghi thiếu phần ý kiến về quỹ lớp."
}
```

**Response Success (201):**

```json
{
  "success": true,
  "message": "Gửi feedback thành công",
  "data": {
    "feedback_id": 1,
    "meeting_id": 1,
    "student_id": 1,
    "feedback_content": "Em thấy biên bản họp ghi thiếu...",
    "created_at": "2025-03-16 08:00:00",
    "student": {
      "student_id": 1,
      "full_name": "Nguyễn Văn Hùng"
    }
  }
}
```

---

### 13. Xem danh sách feedback

```http
GET /api/meetings/{id}/feedbacks
```

**Quyền**: Tất cả (nhưng phải thuộc lớp hoặc là CVHT/Admin)

**Response Success (200):**

```json
{
  "success": true,
  "data": [
    {
      "feedback_id": 1,
      "meeting_id": 1,
      "student_id": 1,
      "feedback_content": "...",
      "created_at": "2025-03-16 08:00:00",
      "student": {
        "full_name": "Nguyễn Văn Hùng",
        "position": "leader"
      }
    }
  ]
}
```

---

### 14. Thống kê cuộc họp

```http
GET /api/meetings/statistics/overview
```

**Quyền**: Advisor, Admin

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `from_date` | date | Từ ngày |
| `to_date` | date | Đến ngày |
| `class_id` | integer | Lọc theo lớp |

**Response Success (200):**

```json
{
  "success": true,
  "data": {
    "total_meetings": 25,
    "scheduled": 5,
    "completed": 18,
    "cancelled": 2,
    "with_minutes": 15,
    "attendance": {
      "total_attendees": 480,
      "attended_count": 432,
      "attendance_rate": 90.00
    }
  }
}
```

---

## Error Codes

| Status Code | Description |
|-------------|-------------|
| **200** | Success |
| **201** | Created |
| **400** | Bad Request |
| **401** | Unauthorized (Token không hợp lệ) |
| **403** | Forbidden (Không có quyền) |
| **404** | Not Found |
| **422** | Validation Error |
| **500** | Internal Server Error |

### Error Response Format:

```json
{
  "success": false,
  "message": "Mô tả lỗi",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

---

## Examples

### Example 1: Quy trình tạo cuộc họp và xuất biên bản

```javascript
// 1. Tạo cuộc họp
const createMeeting = await fetch('/api/meetings', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    class_id: 1,
    title: 'Họp lớp tháng 4/2025',
    meeting_time: '2025-04-15 14:00:00',
    location: 'Phòng B.101'
  })
});

const meeting = await createMeeting.json();
const meetingId = meeting.data.meeting_id;

// 2. Điểm danh sinh viên
await fetch(`/api/meetings/${meetingId}/attendance`, {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    attendances: [
      { student_id: 1, attended: true },
      { student_id: 2, attended: false }
    ]
  })
});

// 3. Cập nhật nội dung và ý kiến lớp
await fetch(`/api/meetings/${meetingId}/summary`, {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    summary: 'Thông báo điểm rèn luyện...',
    class_feedback: 'Lớp không có ý kiến.'
  })
});

// 4. Xuất biên bản
window.location.href = `/api/meetings/${meetingId}/export-minutes?token=${token}`;
```

### Example 2: Sinh viên xem cuộc họp và gửi feedback

```javascript
// 1. Lấy danh sách cuộc họp của lớp
const meetings = await fetch('/api/meetings', {
  headers: {
    'Authorization': 'Bearer ' + token
  }
});

// 2. Xem chi tiết cuộc họp
const detail = await fetch('/api/meetings/1', {
  headers: {
    'Authorization': 'Bearer ' + token
  }
});

// 3. Gửi feedback
await fetch('/api/meetings/1/feedbacks', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    feedback_content: 'Em thấy biên bản ghi thiếu thông tin...'
  })
});

// 4. Tải biên bản
window.location.href = `/api/meetings/1/download-minutes?token=${token}`;
```

---

## Chuẩn bị Template

Tạo file `storage/app/templates/meeting_minutes_template.docx` với các placeholder:

- `${FACULTY_NAME}` - Tên khoa
- `${CLASS_NAME}` - Tên lớp
- `${HOUR}`, `${MINUTE}`, `${DAY}`, `${MONTH}`, `${YEAR}` - Thời gian họp
- `${LOCATION}` - Địa điểm
- `${ADVISOR_NAME}` - Tên GVCV
- `${LEADER_NAME}` - Lớp trưởng
- `${VICE_LEADER_NAME}` - Lớp phó
- `${SECRETARY_NAME}` - Bí thư Đoàn
- `${ATTENDED_COUNT}` / `${TOTAL_COUNT}` - Số SV tham dự / tổng SV
- `${MEETING_SUMMARY}` - Nội dung họp
- `${CLASS_FEEDBACK}` - Ý kiến đóng góp
- `${END_HOUR}`, `${END_MINUTE}` - Thời gian kết thúc

---

## Testing

### Postman Collection

Import collection này vào Postman để test:

```json
{
  "info": {
    "name": "Meeting API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Get Meetings",
      "request": {
        "method": "GET",
        "url": "{{baseUrl}}/meetings"
      }
    },
    {
      "name": "Create Meeting",
      "request": {
        "method": "POST",
        "url": "{{baseUrl}}/meetings",
        "body": {
          "mode": "raw",
          "raw": "{\n  \"class_id\": 1,\n  \"title\": \"Test Meeting\"\n}"
        }
      }
    }
  ]
}
```

---

**Liên hệ hỗ trợ**: support@school.edu.vn  
**Version**: 1.0.0  
**Last Updated**: 2025-03-15