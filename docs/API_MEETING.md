# Tài liệu API - Module Quản lý Cuộc họp lớp

## Mục lục

1. [Tổng quan](#tổng-quan)
2. [Authentication](#authentication)
3. [Google Calendar Integration](#google-calendar-integration)
4. [Endpoints](#endpoints)
5. [Error Codes](#error-codes)
6. [Examples](#examples)

---

## Tổng quan

Module quản lý cuộc họp lớp bao gồm các chức năng:

-   Tạo, xem, sửa, xóa cuộc họp
-   **Tích hợp Google Calendar và Google Meet** (tạo cuộc họp tự động, gửi email mời)
-   Điểm danh sinh viên
-   **Đồng bộ điểm danh từ Google Calendar**
-   Xuất biên bản họp tự động
-   Upload/Download biên bản
-   Sinh viên gửi feedback
-   Thống kê cuộc họp

**Base URL**: `https://api.example.com/api`

---

## Authentication

Tất cả endpoints yêu cầu JWT token trong header:

```http
Authorization: Bearer {your_jwt_token}
```

### Phân quyền

| Role        | Quyền hạn                                         |
| ----------- | ------------------------------------------------- |
| **Student** | Xem cuộc họp lớp mình, tải biên bản, gửi feedback |
| **Advisor** | Toàn quyền với cuộc họp của lớp mình phụ trách    |
| **Admin**   | Toàn quyền với tất cả cuộc họp                    |

---

## Google Calendar Integration

### Tổng quan

Hệ thống tích hợp với **Google Calendar** và **Google Meet** để:

-   Tự động tạo cuộc họp Google Meet khi tạo meeting
-   Gửi email mời đến tất cả sinh viên trong lớp
-   Đồng bộ thông tin cuộc họp (thời gian, nội dung, người tham dự)
-   Kiểm tra trạng thái phản hồi của sinh viên trên Google Calendar
-   Tự động điểm danh dựa trên phản hồi Google Calendar

### Cấu hình Google Calendar Authentication

Để sử dụng tính năng Google Calendar, admin cần thực hiện xác thực một lần:

#### 1. Kiểm tra trạng thái xác thực

```http
GET /api/auth/google/status
```

**Response:**

```json
{
    "success": true,
    "data": {
        "credentials_exists": true,
        "token_exists": true,
        "is_authenticated": true,
        "token_expired": false,
        "has_refresh_token": true,
        "expires_at": "2025-04-15 14:30:00"
    }
}
```

#### 2. Xác thực với Google (nếu chưa có token)

```http
GET /api/auth/google
```

Endpoint này sẽ redirect đến trang đăng nhập Google. Sau khi người dùng chấp nhận quyền, Google sẽ callback về `/api/auth/google/callback` và lưu token.

#### 3. Hủy xác thực (xóa token)

```http
DELETE /api/auth/google/revoke
```

**Response:**

```json
{
    "success": true,
    "message": "Đã xóa xác thực thành công"
}
```

#### 4. Debug cấu hình

```http
GET /api/auth/google/debug
```

Kiểm tra cấu hình credentials, redirect URI, và trạng thái xác thực.

---

## Endpoints

### 1. Lấy danh sách cuộc họp

```http
GET /api/meetings
```

**Query Parameters:**

| Parameter   | Type    | Required | Description                                                |
| ----------- | ------- | -------- | ---------------------------------------------------------- |
| `class_id`  | integer | No       | Lọc theo lớp (chỉ advisor/admin)                           |
| `status`    | string  | No       | Lọc theo trạng thái: `scheduled`, `completed`, `cancelled` |
| `from_date` | date    | No       | Lọc từ ngày (YYYY-MM-DD)                                   |
| `to_date`   | date    | No       | Lọc đến ngày (YYYY-MM-DD)                                  |

**Response Success (200):**

```json
{
    "success": true,
    "data": [
        {
            "meeting_id": 1,
            "advisor_id": 1,
            "class_id": 1,
            "title": "Họp lớp DH21CNTT tháng 3/2025",
            "summary": "Thông báo điểm rèn luyện...",
            "class_feedback": "Lớp không có ý kiến.",
            "meeting_link": "https://meet.google.com/abc-defg-hij",
            "location": "Phòng B.101",
            "meeting_time": "2025-03-15 10:00:00",
            "end_time": "2025-03-15 11:30:00",
            "status": "completed",
            "minutes_file_path": "meetings/BienBan_DH21CNTT_15032025.docx"
        }
    ]
}
```

---

### 2. Xem chi tiết cuộc họp

```http
GET /api/meetings/{id}
```

**Path Parameters:**

| Parameter | Type    | Description |
| --------- | ------- | ----------- |
| `id`      | integer | ID cuộc họp |

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
    "feedbacks": [...]
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
    "end_time": "2025-04-15 15:30:00",
    "auto_create_meet": true
}
```

**Fields:**

| Field              | Type         | Required | Description                                        |
| ------------------ | ------------ | -------- | -------------------------------------------------- |
| `class_id`         | integer      | Yes      | ID lớp                                             |
| `title`            | string       | Yes      | Tiêu đề (max: 255)                                 |
| `summary`          | string       | No       | Nội dung họp                                       |
| `class_feedback`   | string       | No       | Ý kiến đóng góp của lớp                            |
| `meeting_link`     | string (URL) | No       | Link họp online (max: 2083)                        |
| `location`         | string       | No       | Địa điểm (max: 255)                                |
| `meeting_time`     | datetime     | Yes      | Thời gian bắt đầu                                  |
| `end_time`         | datetime     | No       | Thời gian kết thúc                                 |
| `auto_create_meet` | boolean      | No       | **[MỚI]** Tự động tạo Google Meet và gửi email mời |

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
        "meeting_link": "https://meet.google.com/abc-defg-hij"
    },
    "google_meet": {
        "meet_link": "https://meet.google.com/abc-defg-hij",
        "calendar_link": "https://calendar.google.com/calendar/event?eid=...",
        "attendees_invited": 35,
        "google_event_id": "meet0000000005"
    }
}
```

**Lưu ý về auto_create_meet:**

-   Khi `auto_create_meet: true`, hệ thống sẽ:
    1. Tạo sự kiện trên Google Calendar
    2. Tự động tạo link Google Meet
    3. Gửi email mời đến tất cả sinh viên trong lớp
    4. Lưu link Google Meet vào `meeting_link` của cuộc họp
-   Nếu tạo Google Meet thất bại, cuộc họp vẫn được tạo nhưng không có `google_meet` data
-   Yêu cầu: Admin đã xác thực Google Calendar (`/api/auth/google`)

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
    "status": "completed",
    "sync_to_google": true
}
```

**Fields:** Tất cả fields đều optional (chỉ gửi fields cần update)

| Field            | Type     | Description                                       |
| ---------------- | -------- | ------------------------------------------------- |
| `title`          | string   | Tiêu đề                                           |
| `summary`        | string   | Nội dung họp                                      |
| `class_feedback` | string   | Ý kiến lớp                                        |
| `meeting_link`   | string   | Link họp                                          |
| `location`       | string   | Địa điểm                                          |
| `meeting_time`   | datetime | Thời gian bắt đầu                                 |
| `end_time`       | datetime | Thời gian kết thúc                                |
| `status`         | string   | Trạng thái: `scheduled`, `completed`, `cancelled` |
| `sync_to_google` | boolean  | **[MỚI]** Đồng bộ thay đổi lên Google Calendar    |

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
        { "student_id": 1, "attended": true },
        { "student_id": 2, "attended": false }
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

---

### 8. Upload biên bản thủ công

```http
POST /api/meetings/{id}/upload-minutes
```

**Quyền**: Advisor (của lớp), Admin

**Content-Type**: `multipart/form-data`

**Form Data:**

| Field          | Type | Description                                 |
| -------------- | ---- | ------------------------------------------- |
| `minutes_file` | file | File biên bản (.doc, .docx, .pdf, max 10MB) |

---

### 9. Tải biên bản đã lưu

```http
GET /api/meetings/{id}/download-minutes
```

**Quyền**: Tất cả (nhưng phải thuộc lớp hoặc là CVHT/Admin)

**Response**: File download

---

### 10. Xóa biên bản

```http
DELETE /api/meetings/{id}/minutes
```

**Quyền**: Advisor (của lớp), Admin

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

---

### 13. Xem danh sách feedback

```http
GET /api/meetings/{id}/feedbacks
```

**Quyền**: Tất cả (nhưng phải thuộc lớp hoặc là CVHT/Admin)

---

### 14. Kiểm tra trạng thái phản hồi từ Google Calendar

```http
GET /api/meetings/{id}/google-attendance
```

**Quyền**: Advisor, Admin

**Mô tả**: Lấy trạng thái phản hồi (accepted/declined/tentative/needsAction) của sinh viên từ Google Calendar.

**Điều kiện**: Cuộc họp phải có Google Meet link (`meeting_link` chứa `meet.google.com`)

**Response Success (200):**

```json
{
    "success": true,
    "data": {
        "meeting_id": 1,
        "meeting_title": "Họp lớp DH21CNTT tháng 4/2025",
        "attendees": [
            {
                "email": "student1@example.com",
                "student_id": 1,
                "student_name": "Nguyễn Văn Hùng",
                "response_status": "accepted",
                "status_text": "Đã chấp nhận",
                "comment": null
            }
        ],
        "summary": {
            "total": 35,
            "accepted": 28,
            "declined": 2,
            "tentative": 1,
            "needsAction": 4
        }
    }
}
```

**Response Status:**

| Status        | Ý nghĩa                        |
| ------------- | ------------------------------ |
| `accepted`    | Sinh viên đã chấp nhận lời mời |
| `declined`    | Sinh viên từ chối tham dự      |
| `tentative`   | Sinh viên chưa chắc chắn       |
| `needsAction` | Sinh viên chưa phản hồi        |

---

### 15. Đồng bộ điểm danh từ Google Calendar

```http
POST /api/meetings/{id}/sync-google-attendance
```

**Quyền**: Advisor, Admin

**Mô tả**: Tự động điểm danh dựa trên phản hồi của sinh viên trên Google Calendar. Những sinh viên `accepted` sẽ được đánh dấu `attended = true`.

**Response Success (200):**

```json
{
    "success": true,
    "message": "Đã đồng bộ điểm danh cho 33 sinh viên",
    "data": {
        "synced_count": 33,
        "accepted": 28,
        "declined": 2,
        "tentative": 1,
        "no_response": 4
    }
}
```

**Logic đồng bộ:**

-   **accepted** → `attended = true`
-   **declined, tentative, needsAction** → `attended = false`
-   Tự động chuyển trạng thái cuộc họp sang `completed` nếu đang `scheduled`

**Use case**: Sau khi cuộc họp kết thúc, advisor có thể dùng endpoint này để tự động điểm danh thay vì điểm danh thủ công qua `/api/meetings/{id}/attendance`.

---

### 16. Thống kê cuộc họp

```http
GET /api/meetings/statistics/overview
```

**Quyền**: Advisor, Admin

**Query Parameters:**

| Parameter   | Type    | Description  |
| ----------- | ------- | ------------ |
| `from_date` | date    | Từ ngày      |
| `to_date`   | date    | Đến ngày     |
| `class_id`  | integer | Lọc theo lớp |

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
            "attendance_rate": 90.0
        }
    }
}
```

---

## Error Codes

| Status Code | Description                       |
| ----------- | --------------------------------- |
| **200**     | Success                           |
| **201**     | Created                           |
| **400**     | Bad Request                       |
| **401**     | Unauthorized (Token không hợp lệ) |
| **403**     | Forbidden (Không có quyền)        |
| **404**     | Not Found                         |
| **422**     | Validation Error                  |
| **500**     | Internal Server Error             |

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

### Example 1: Tạo cuộc họp với Google Meet tự động (MỚI)

```javascript
// Kiểm tra trạng thái xác thực Google trước
const authStatus = await fetch("/api/auth/google/status", {
    headers: {
        Authorization: "Bearer " + token,
    },
});

const status = await authStatus.json();
if (!status.data.is_authenticated) {
    // Chuyển hướng đến trang xác thực Google
    window.location.href = "/api/auth/google";
    return;
}

//Tạo cuộc họp với Google Meet tự động
const createMeeting = await fetch("/api/meetings", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        class_id: 1,
        title: "Họp lớp DH21CNTT tháng 4/2025",
        summary: "Thông báo lịch thi cuối kỳ và điểm rèn luyện",
        meeting_time: "2025-04-15 14:00:00",
        end_time: "2025-04-15 15:30:00",
        location: "Họp Online",
        auto_create_meet: true, // ✨ Tự động tạo Google Meet
    }),
});

const result = await createMeeting.json();

if (result.success && result.google_meet) {
    console.log("✅ Meeting created successfully!");
    console.log("Google Meet Link:", result.google_meet.meet_link);
    console.log("Calendar Link:", result.google_meet.calendar_link);
    console.log(
        "Invitations sent to:",
        result.google_meet.attendees_invited,
        "students"
    );
    // Email mời đã được gửi tự động đến tất cả sinh viên!
}
```

### Example 2: Đồng bộ điểm danh từ Google Calendar (MỚI)

```javascript
const meetingId = 5;

// Sau khi cuộc họp kết thúc, kiểm tra phản hồi từ Google Calendar
const checkAttendance = await fetch(
    `/api/meetings/${meetingId}/google-attendance`,
    {
        headers: {
            Authorization: "Bearer " + token,
        },
    }
);

const attendanceData = await checkAttendance.json();

console.log("📊 Attendance Summary:");
console.log("- Accepted:", attendanceData.data.summary.accepted);
console.log("- Declined:", attendanceData.data.summary.declined);
console.log("- No Response:", attendanceData.data.summary.needsAction);

// Tự động đồng bộ điểm danh
const syncResult = await fetch(
    `/api/meetings/${meetingId}/sync-google-attendance`,
    {
        method: "POST",
        headers: {
            Authorization: "Bearer " + token,
        },
    }
);

const syncData = await syncResult.json();

console.log("✅ Synced attendance for", syncData.data.synced_count, "students");
console.log('Những sinh viên "accepted" đã được đánh dấu attended = true');
```

### Example 3: Cập nhật cuộc họp và đồng bộ với Google Calendar (MỚI)

```javascript
// Cập nhật thời gian họp và đồng bộ với Google Calendar
const updateMeeting = await fetch(`/api/meetings/${meetingId}`, {
    method: "PUT",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        title: "Họp lớp DH21CNTT tháng 4/2025 (Cập nhật)",
        meeting_time: "2025-04-16 14:00:00",
        end_time: "2025-04-16 15:30:00",
        sync_to_google: true, // ✨ Đồng bộ thay đổi lên Google Calendar
    }),
});

const result = await updateMeeting.json();
console.log("✅ Meeting updated and synced to Google Calendar");
// Sinh viên sẽ nhận được email thông báo thay đổi thời gian
```

### Example 4: Quy trình truyền thống (không dùng Google Meet)

```javascript
// 1. Tạo cuộc họp thủ công
const createMeeting = await fetch("/api/meetings", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        class_id: 1,
        title: "Họp lớp tháng 4/2025",
        meeting_time: "2025-04-15 14:00:00",
        location: "Phòng B.101",
    }),
});

const meeting = await createMeeting.json();
const meetingId = meeting.data.meeting_id;

// 2. Điểm danh thủ công
await fetch(`/api/meetings/${meetingId}/attendance`, {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        attendances: [
            { student_id: 1, attended: true },
            { student_id: 2, attended: false },
        ],
    }),
});

// 3. Cập nhật nội dung và ý kiến lớp
await fetch(`/api/meetings/${meetingId}/summary`, {
    method: "PUT",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        summary: "Thông báo điểm rèn luyện...",
        class_feedback: "Lớp không có ý kiến.",
    }),
});

// 4. Xuất biên bản
window.location.href = `/api/meetings/${meetingId}/export-minutes?token=${token}`;
```

### Example 5: Sinh viên xem cuộc họp và gửi feedback

```javascript
// 1. Lấy danh sách cuộc họp của lớp
const meetings = await fetch("/api/meetings", {
    headers: {
        Authorization: "Bearer " + token,
    },
});

// 2. Xem chi tiết cuộc họp
const detail = await fetch("/api/meetings/1", {
    headers: {
        Authorization: "Bearer " + token,
    },
});

const meetingDetail = await detail.json();
if (meetingDetail.data.meeting_link) {
    console.log("Join meeting at:", meetingDetail.data.meeting_link);
}

// 3. Gửi feedback
await fetch("/api/meetings/1/feedbacks", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        feedback_content: "Em thấy biên bản ghi thiếu thông tin về quỹ lớp",
    }),
});

// 4. Tải biên bản
window.location.href = `/api/meetings/1/download-minutes?token=${token}`;
```

---

## Changelog

### Version 2.0.0 (2025-11-29)

**🎉 Tính năng mới:**

-   ✨ Tích hợp Google Calendar và Google Meet
-   ✨ Tự động tạo Google Meet link khi tạo cuộc họp
-   ✨ Gửi email mời tự động đến sinh viên
-   ✨ Kiểm tra trạng thái phản hồi từ Google Calendar
-   ✨ Đồng bộ điểm danh tự động dựa trên phản hồi Google Calendar
-   ✨ Cập nhật cuộc họp và đồng bộ với Google Calendar

**📝 Endpoints mới:**

-   `GET /api/auth/google/status` - Kiểm tra trạng thái xác thực Google
-   `GET /api/auth/google` - Xác thực với Google Calendar
-   `DELETE /api/auth/google/revoke` - Hủy xác thực
-   `GET /api/auth/google/debug` - Debug cấu hình
-   `GET /api/meetings/{id}/google-attendance` - Kiểm tra phản hồi từ Google
-   `POST /api/meetings/{id}/sync-google-attendance` - Đồng bộ điểm danh

**🔧 Cập nhật endpoints:**

-   `POST /api/meetings` - Thêm tham số `auto_create_meet`
-   `PUT /api/meetings/{id}` - Thêm tham số `sync_to_google`

### Version 1.0.0 (2025-03-15)

-   🎯 Phiên bản đầu tiên với đầy đủ chức năng quản lý cuộc họp cơ bản

---

**Liên hệ hỗ trợ**: support@school.edu.vn  
**Version**: 2.0.0  
**Last Updated**: 2025-11-29
