# API Documentation - DialogueController

## Tổng quan

DialogueController quản lý các ý kiến đối thoại giữa sinh viên và cố vấn học tập, tổng hợp từ 2 nguồn:

-   **Meeting Feedbacks**: Ý kiến phản hồi từ cuộc họp lớp
-   **Notification Responses**: Phản hồi từ thông báo

**Base URL**: `/api/dialogues`

**Authentication**: Tất cả endpoints yêu cầu JWT token qua middleware `auth.api`

## Phân quyền

| Role        | Quyền hạn                                                        |
| ----------- | ---------------------------------------------------------------- |
| **Admin**   | Xem và quản lý tất cả ý kiến của các lớp thuộc khoa mình quản lý |
| **Advisor** | Xem và phản hồi ý kiến của các lớp mình phụ trách                |
| **Student** | Chỉ xem được ý kiến của chính mình                               |

---

## Endpoints

### 1. Lấy danh sách ý kiến đối thoại

**GET** `/api/dialogues`

**Route**: `Route::get('/', [DialogueController::class, 'index'])`

**Middleware**: `auth.api` (tất cả roles)

Lấy danh sách tất cả ý kiến đối thoại từ cả 2 nguồn (Meeting Feedbacks và Notification Responses)

#### Query Parameters

| Tham số             | Kiểu    | Bắt buộc | Mặc định     | Mô tả                                                                     |
| ------------------- | ------- | -------- | ------------ | ------------------------------------------------------------------------- |
| `source`            | string  | Không    | `all`        | Nguồn ý kiến: `all`, `meeting`, `notification`                            |
| `class_id`          | integer | Không    | -            | Lọc theo lớp                                                              |
| `status`            | string  | Không    | -            | Lọc theo trạng thái: `pending`, `resolved` (chỉ áp dụng cho notification) |
| `notification_type` | string  | Không    | -            | Lọc theo loại thông báo                                                   |
| `from_date`         | date    | Không    | -            | Lọc từ ngày (Y-m-d)                                                       |
| `to_date`           | date    | Không    | -            | Lọc đến ngày (Y-m-d)                                                      |
| `keyword`           | string  | Không    | -            | Tìm kiếm theo từ khóa trong nội dung                                      |
| `sort_by`           | string  | Không    | `created_at` | Sắp xếp theo trường                                                       |
| `sort_order`        | string  | Không    | `desc`       | Thứ tự: `asc`, `desc`                                                     |

#### Response Success (200)

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "source": "notification",
            "source_id": 5,
            "source_title": "Thông báo về lịch học",
            "student_id": 123,
            "student_name": "Nguyễn Văn A",
            "student_code": "SV001",
            "class_id": 10,
            "class_name": "CNTT K17",
            "faculty_name": "Công nghệ thông tin",
            "content": "Em có thắc mắc về lịch học...",
            "advisor_response": "Thầy đã ghi nhận ý kiến...",
            "advisor_name": "TS. Trần Văn B",
            "status": "resolved",
            "created_at": "2024-12-20T10:30:00.000000Z",
            "response_at": "2024-12-20T15:20:00.000000Z"
        },
        {
            "id": 2,
            "source": "meeting",
            "source_id": 8,
            "source_title": "Họp đầu năm học",
            "student_id": 124,
            "student_name": "Trần Thị C",
            "student_code": "SV002",
            "class_id": 10,
            "class_name": "CNTT K17",
            "faculty_name": "Công nghệ thông tin",
            "content": "Em muốn đề xuất...",
            "advisor_response": null,
            "advisor_name": "TS. Trần Văn B",
            "status": "pending",
            "created_at": "2024-12-19T14:00:00.000000Z",
            "response_at": null
        }
    ]
}
```

#### Response Error

```json
{
    "success": false,
    "message": "Lỗi khi lấy danh sách ý kiến: ..."
}
```

---

### 2. Xem chi tiết ý kiến đối thoại

**GET** `/api/dialogues/{source}/{id}`

**Route**: `Route::get('/{source}/{id}', [DialogueController::class, 'show'])`

**Middleware**: `auth.api` (tất cả roles)

Xem thông tin chi tiết của một ý kiến đối thoại

#### Path Parameters

| Tham số  | Kiểu    | Mô tả                                |
| -------- | ------- | ------------------------------------ |
| `source` | string  | Nguồn: `meeting` hoặc `notification` |
| `id`     | integer | ID của feedback hoặc response        |

#### Response Success (200) - Meeting Feedback

```json
{
    "success": true,
    "data": {
        "source": "meeting",
        "feedback_id": 2,
        "meeting": {
            "meeting_id": 8,
            "title": "Họp đầu năm học",
            "description": "...",
            "meeting_time": "2024-12-19T09:00:00.000000Z",
            "class": { "class_id": 10, "class_name": "CNTT K17" },
            "advisor": { "advisor_id": 5, "full_name": "TS. Trần Văn B" }
        },
        "student": {
            "student_id": 124,
            "user_code": "SV002",
            "full_name": "Trần Thị C",
            "class": { "class_id": 10, "class_name": "CNTT K17" }
        },
        "content": "Em muốn đề xuất...",
        "created_at": "2024-12-19T14:00:00.000000Z"
    }
}
```

#### Response Success (200) - Notification Response

```json
{
    "success": true,
    "data": {
        "source": "notification",
        "response_id": 1,
        "notification": {
            "notification_id": 5,
            "title": "Thông báo về lịch học",
            "content": "...",
            "type": "announcement",
            "attachments": []
        },
        "student": {
            "student_id": 123,
            "user_code": "SV001",
            "full_name": "Nguyễn Văn A",
            "class": { "class_id": 10, "class_name": "CNTT K17" }
        },
        "content": "Em có thắc mắc về lịch học...",
        "advisor_response": "Thầy đã ghi nhận ý kiến...",
        "advisor": {
            "advisor_id": 5,
            "full_name": "TS. Trần Văn B"
        },
        "status": "resolved",
        "created_at": "2024-12-20T10:30:00.000000Z",
        "response_at": "2024-12-20T15:20:00.000000Z"
    }
}
```

#### Response Error (404)

```json
{
    "success": false,
    "message": "Không tìm thấy ý kiến"
}
```

#### Response Error (403)

```json
{
    "success": false,
    "message": "Bạn không có quyền xem ý kiến này"
}
```

---

### 3. Thống kê tổng hợp ý kiến đối thoại

**GET** `/api/dialogues/statistics/overview`

**Route**: `Route::get('/statistics/overview', [DialogueController::class, 'getStatistics'])`

**Middleware**: `auth.api`, `check_role:advisor,admin`

Lấy thống kê tổng quan về ý kiến đối thoại từ cả 2 nguồn

**Quyền truy cập**: Advisor, Admin

#### Query Parameters

Hỗ trợ các tham số lọc tương tự như endpoint lấy danh sách:

-   `source`, `class_id`, `status`, `notification_type`
-   `from_date`, `to_date`, `keyword`

#### Response Success (200)

```json
{
    "success": true,
    "data": {
        "overview": {
            "total_all": 100,
            "total_meeting": 35,
            "total_notification": 65,
            "notification_pending": 15,
            "notification_resolved": 50,
            "notification_responded": 55,
            "notification_response_rate": 84.62
        },
        "by_source": [
            {
                "source": "meeting",
                "count": 35,
                "percentage": 35.0
            },
            {
                "source": "notification",
                "count": 65,
                "percentage": 65.0
            }
        ],
        "by_class": [
            {
                "class_id": 10,
                "class_name": "CNTT K17",
                "total": 45,
                "from_meeting": 15,
                "from_notification": 30
            },
            {
                "class_id": 11,
                "class_name": "CNTT K18",
                "total": 55,
                "from_meeting": 20,
                "from_notification": 35
            }
        ],
        "top_students": [
            {
                "student_id": 123,
                "student_name": "Nguyễn Văn A",
                "student_code": "SV001",
                "dialogue_count": 12,
                "from_meeting": 5,
                "from_notification": 7
            }
        ],
        "trend_7_days": [
            {
                "date": "2024-12-16",
                "count": 8,
                "meeting": 3,
                "notification": 5
            },
            {
                "date": "2024-12-17",
                "count": 12,
                "meeting": 4,
                "notification": 8
            }
        ]
    }
}
```

#### Response Error (403)

```json
{
    "success": false,
    "message": "Bạn không có quyền xem thống kê"
}
```

---

### 4. Báo cáo chi tiết theo lớp

**GET** `/api/dialogues/reports/by-class`

**Route**: `Route::get('/reports/by-class', [DialogueController::class, 'getReportByClass'])`

**Middleware**: `auth.api`, `check_role:advisor,admin`

Lấy báo cáo chi tiết ý kiến đối thoại của một lớp cụ thể từ **cả 2 nguồn**: Meeting Feedbacks và Notification Responses

**Quyền truy cập**: Advisor (lớp mình phụ trách), Admin (lớp thuộc khoa mình quản lý)

#### Query Parameters

| Tham số     | Kiểu    | Bắt buộc | Mô tả                  |
| ----------- | ------- | -------- | ---------------------- |
| `class_id`  | integer | Có       | ID của lớp cần báo cáo |
| `from_date` | date    | Không    | Lọc từ ngày            |
| `to_date`   | date    | Không    | Lọc đến ngày           |

#### Response Success (200)

```json
{
  "success": true,
  "data": {
    "class": {
      "class_id": 10,
      "class_name": "CNTT K17",
      "faculty_id": 3,
      "advisor_id": 5
    },
    "summary": {
      "total": 45,
      "total_meeting": 15,
      "total_notification": 30,
      "pending": 10,
      "resolved": 35,
      "response_rate": 77.78
    },
    "students": [
      {
        "student_id": 123,
        "user_code": "SV001",
        "full_name": "Nguyễn Văn A",
        "dialogue_count": 8,
        "from_meeting": 3,
        "from_notification": 5,
        "pending_count": 2,
        "resolved_count": 6
      },
      {
        "student_id": 124,
        "user_code": "SV002",
        "full_name": "Trần Thị C",
        "dialogue_count": 5,
        "from_meeting": 2,
        "from_notification": 3,
        "pending_count": 1,
        "resolved_count": 4
      }
    ],
    "dialogues": [
      {
        "id": 1,
        "source": "notification",
        "source_id": 5,
        "source_title": "Thông báo về lịch học",
        "student_id": 123,
        "student_name": "Nguyễn Văn A",
        "student_code": "SV001",
        "content": "Em có thắc mắc...",
        "advisor_response": "Thầy đã ghi nhận...",
        "status": "resolved",
        "created_at": "2024-12-20T10:30:00.000000Z",
        "response_at": "2024-12-20T15:20:00.000000Z",
        "student": { ... },
        "notification": { ... },
        "advisor": { ... }
      },
      {
        "id": 2,
        "source": "meeting",
        "source_id": 8,
        "source_title": "Họp đầu năm học",
        "student_id": 124,
        "student_name": "Trần Thị C",
        "student_code": "SV002",
        "content": "Em muốn đề xuất...",
        "advisor_response": null,
        "status": "pending",
        "created_at": "2024-12-19T14:00:00.000000Z",
        "response_at": null,
        "student": { ... },
        "meeting": { ... }
      }
    ]
  }
}
```

#### Response Error (403)

```json
{
    "success": false,
    "message": "Bạn không có quyền xem báo cáo lớp này"
}
```

#### Response Error (422)

```json
{
    "success": false,
    "message": "Dữ liệu không hợp lệ",
    "errors": {
        "class_id": ["The selected class id is invalid."]
    }
}
```

---

### 5. Xuất báo cáo Excel

**GET** `/api/dialogues/export`

**Route**: `Route::get('/export', [DialogueController::class, 'exportReport'])`

**Middleware**: `auth.api`, `check_role:advisor,admin`

Xuất báo cáo ý kiến đối thoại ra file Excel với header chuyên nghiệp

**Quyền truy cập**: Advisor, Admin

#### Query Parameters

| Tham số     | Kiểu    | Bắt buộc | Mô tả                                            |
| ----------- | ------- | -------- | ------------------------------------------------ |
| `class_id`  | integer | Không    | Lọc theo lớp                                     |
| `from_date` | date    | Không    | Lọc từ ngày (Y-m-d)                              |
| `to_date`   | date    | Không    | Lọc đến ngày (Y-m-d)                             |
| `source`    | string  | Không    | Lọc theo nguồn: `all`, `meeting`, `notification` |

#### Response Success (200)

**Trả về file Excel download trực tiếp**

Response Headers:

```
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="BaoCao_YKienDoiThoai_<timestamp>.xlsx"
```

File sẽ được tải xuống trực tiếp với tên: `BaoCao_YKienDoiThoai_YmdHis.xlsx`

#### File Excel bao gồm:

**Header chuyên nghiệp:**

-   Logo và thông tin trường
-   Tiêu đề báo cáo
-   Thông tin chi tiết (Lớp, Khoa, Nguồn dữ liệu, Thời gian, Tổng số ý kiến)

**Bảng dữ liệu với 11 cột:**

1. **STT**: Số thứ tự
2. **Nguồn**: Cuộc họp / Thông báo
3. **Tiêu đề**: Tiêu đề cuộc họp hoặc thông báo
4. **MSSV**: Mã số sinh viên
5. **Họ tên SV**: Họ và tên sinh viên
6. **Lớp**: Tên lớp
7. **Nội dung**: Nội dung ý kiến
8. **Phản hồi**: Phản hồi của cố vấn
9. **Trạng thái**: Chưa xử lý / Đã xử lý
10. **Ngày tạo**: Ngày tạo ý kiến
11. **Ngày phản hồi**: Ngày phản hồi

#### Response Error (403)

```json
{
    "success": false,
    "message": "Bạn không có quyền xuất báo cáo lớp này"
}
```

#### Response Error (422)

```json
{
    "success": false,
    "message": "Dữ liệu không hợp lệ",
    "errors": {
        "class_id": ["The selected class id is invalid."]
    }
}
```

---

## Lưu ý kỹ thuật

### 1. Phân quyền tự động

Tất cả các endpoint đều tự động kiểm tra quyền dựa trên:

-   `current_role`: Admin, Advisor, hoặc Student
-   `current_user_id`: ID người dùng hiện tại

### 2. Hai nguồn dữ liệu

Controller tổng hợp từ 2 nguồn:

-   **Meeting Feedbacks** (`meeting_feedbacks` table): Không hỗ trợ `advisor_response` trực tiếp
-   **Notification Responses** (`notification_responses` table): Hỗ trợ đầy đủ phản hồi và trạng thái

### 3. Định dạng phản hồi thống nhất

Mọi ý kiến đều được chuẩn hóa về cùng một format bao gồm:

-   `source`: `meeting` hoặc `notification`
-   `student_id`, `student_name`, `student_code`
-   `class_id`, `class_name`, `faculty_name`
-   `content`: Nội dung ý kiến
-   `advisor_response`: Phản hồi (null nếu là meeting feedback hoặc chưa phản hồi)
-   `status`: Trạng thái (`pending` cho meeting, hoặc status thực tế cho notification)

### 4. Lọc và tìm kiếm

Hỗ trợ đa dạng tham số lọc:

-   Theo nguồn (meeting/notification)
-   Theo lớp, thời gian
-   Theo trạng thái (chỉ notification)
-   Tìm kiếm từ khóa trong nội dung

---

## Ví dụ sử dụng

### Lấy tất cả ý kiến của lớp trong tháng 12

```bash
GET /api/dialogues?class_id=10&from_date=2024-12-01&to_date=2024-12-31
```

### Lấy ý kiến chưa phản hồi từ thông báo

```bash
GET /api/dialogues?source=notification&status=pending
```

### Tìm kiếm ý kiến chứa từ "lịch học"

```bash
GET /api/dialogues?keyword=lịch học
```

### Xem thống kê 7 ngày gần nhất

```bash
GET /api/dialogues/statistics/overview?from_date=2024-12-15
```

---

## Xử lý lỗi

Tất cả các endpoint đều trả về mã lỗi chuẩn:

| Mã HTTP | Ý nghĩa                                 |
| ------- | --------------------------------------- |
| 200     | Thành công                              |
| 403     | Không có quyền truy cập                 |
| 404     | Không tìm thấy dữ liệu                  |
| 422     | Dữ liệu không hợp lệ (validation error) |
| 500     | Lỗi server                              |
| 501     | Chức năng chưa được triển khai          |

Format lỗi thống nhất:

```json
{
    "success": false,
    "message": "Mô tả lỗi",
    "errors": {
        "field_name": ["error message"]
    }
}
```
