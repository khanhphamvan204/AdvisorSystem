# API Documentation - Point Feedback & Student Monitoring Notes

## 📋 Mục lục

-   [Point Feedback API](#point-feedback-api)
-   [Student Monitoring Notes API](#student-monitoring-notes-api)
-   [Authentication & Authorization](#authentication--authorization)
-   [Error Handling](#error-handling)

---

## 🔐 Authentication & Authorization

### Middleware Required

Tất cả endpoints yêu cầu JWT token hợp lệ trong header:

```
Authorization: Bearer {jwt_token}
```

### Middleware tự động inject vào request:

-   `current_role`: 'student' | 'advisor'
-   `current_user_id`: ID của user hiện tại (student_id hoặc advisor_id)

### Phân quyền theo role:

| Role        | Quyền                                                                                      |
| ----------- | ------------------------------------------------------------------------------------------ |
| **Student** | Xem và tạo phản hồi của mình, xem ghi chú về mình                                          |
| **Advisor** | Xem, phê duyệt phản hồi; Tạo, xem, cập nhật, xóa ghi chú cho sinh viên trong lớp phụ trách |

---

# Point Feedback API

## Tổng quan

API quản lý phản hồi điểm rèn luyện/CTXH của sinh viên.

### Base URL

```
/api/point-feedbacks
```

---

## 1. Lấy danh sách phản hồi

### Endpoint

```http
GET /api/point-feedbacks
```

### Query Parameters

| Parameter   | Type    | Required | Description                       |
| ----------- | ------- | -------- | --------------------------------- |
| semester_id | integer | No       | Lọc theo học kỳ                   |
| status      | string  | No       | pending, approved, rejected       |
| student_id  | integer | No       | Lọc theo sinh viên (advisor only) |

### Authorization Rules

-   **Student**: Chỉ xem phản hồi của mình
-   **Advisor**: Xem phản hồi sinh viên trong lớp phụ trách

### Response Success (200)

```json
{
  "success": true,
  "data": [
    {
      "feedback_id": 1,
        "student_id": 2,
        "semester_id": 1,
        "feedback_content": "Em đã tham gia hoạt động...",
        "attachment_path": "point_feedbacks/1234567_2_proof.jpg",
        "status": "pending",
        "advisor_response": null,
        "advisor_id": null,
        "response_at": null,
        "created_at": "2025-03-11T10:00:00.000000Z",
        "student": {
          "student_id": 2,
          "user_code": "210002",
          "full_name": "Trần Thị Thu Cẩm",
          "email": "sv.cam@school.edu.vn",
          "class_id": 1,
          "class": {
            "class_id": 1,
            "class_name": "DH21CNTT"
          }
        },
        "semester": {
          "semester_id": 1,
          "semester_name": "Học kỳ 1",
          "academic_year": "2024-2025"
        },
        "advisor": null
      }
    ]
  }
}
```

### Response Error (403)

```json
{
    "success": false,
    "message": "Không có quyền truy cập"
}
```

---

## 2. Xem chi tiết phản hồi

### Endpoint

```http
GET /api/point-feedbacks/{id}
```

### Path Parameters

| Parameter | Type    | Required | Description     |
| --------- | ------- | -------- | --------------- |
| id        | integer | Yes      | ID của phản hồi |

### Authorization Rules

-   **Student**: Chỉ xem phản hồi của mình
-   **Advisor**: Xem nếu sinh viên thuộc lớp phụ trách

### Response Success (200)

```json
{
    "success": true,
    "data": {
        "feedback_id": 1,
        "student_id": 2,
        "semester_id": 1,
        "feedback_content": "Em đã tham gia hoạt động Hiến máu...",
        "attachment_path": "point_feedbacks/minhchung_cam_hk1.jpg",
        "status": "approved",
        "advisor_response": "Đã kiểm tra và cộng bổ sung 5 điểm",
        "advisor_id": 1,
        "response_at": "2025-03-12T10:00:00.000000Z",
        "created_at": "2025-03-11T09:00:00.000000Z",
        "student": {
            "student_id": 2,
            "user_code": "210002",
            "full_name": "Trần Thị Thu Cẩm",
            "email": "sv.cam@school.edu.vn",
            "phone_number": "091234567",
            "class_id": 1,
            "class": {
                "class_id": 1,
                "class_name": "DH21CNTT",
                "advisor_id": 1
            }
        },
        "semester": {
            "semester_id": 1,
            "semester_name": "Học kỳ 1",
            "academic_year": "2024-2025"
        },
        "advisor": {
            "advisor_id": 1,
            "full_name": "ThS. Trần Văn An",
            "email": "gv.an@school.edu.vn"
        }
    }
}
```

### Response Error (404)

```json
{
    "success": false,
    "message": "Không tìm thấy phản hồi"
}
```

### Response Error (403)

```json
{
    "success": false,
    "message": "Bạn không có quyền xem phản hồi này"
}
```

---

## 3. Tạo phản hồi mới

### Endpoint

```http
POST /api/point-feedbacks
```

### Authorization

**Chỉ Student** được tạo phản hồi

### Request Body (multipart/form-data)

| Field            | Type    | Required | Description                               |
| ---------------- | ------- | -------- | ----------------------------------------- |
| semester_id      | integer | Yes      | ID học kỳ                                 |
| feedback_content | string  | Yes      | Nội dung phản hồi (min: 10, max: 2000)    |
| attachment       | file    | No       | File đính kèm (jpg,jpeg,png,pdf, max 5MB) |

### Example Request

```bash
curl -X POST https://api.example.com/api/point-feedbacks \
  -H "Authorization: Bearer {token}" \
  -F "semester_id=1" \
  -F "feedback_content=Em đã tham gia hoạt động Hiến máu nhân đạo 2025..." \
  -F "attachment=@/path/to/proof.jpg"
```

### Response Success (201)

```json
{
    "success": true,
    "message": "Tạo phản hồi thành công",
    "data": {
        "feedback_id": 3,
        "student_id": 2,
        "semester_id": 1,
        "feedback_content": "Em đã tham gia hoạt động...",
        "attachment_path": "point_feedbacks/1710234567_2_proof.jpg",
        "status": "pending",
        "created_at": "2025-03-12T14:30:00.000000Z",
        "semester": {
            "semester_id": 1,
            "semester_name": "Học kỳ 1",
            "academic_year": "2024-2025"
        },
        "student": {
            "student_id": 2,
            "full_name": "Trần Thị Thu Cẩm",
            "email": "sv.cam@school.edu.vn"
        }
    }
}
```

### Response Error (403)

```json
{
    "success": false,
    "message": "Chỉ sinh viên mới được tạo phản hồi"
}
```

### Response Error (422)

```json
{
    "success": false,
    "message": "Dữ liệu không hợp lệ",
    "errors": {
        "semester_id": ["The semester id field is required."],
        "feedback_content": [
            "The feedback content must be at least 10 characters."
        ]
    }
}
```

---

## 4. Cập nhật phản hồi

### Endpoint

```http
PUT /api/point-feedbacks/{id}
```

### Authorization

**Chỉ Student** - Chỉ cập nhật phản hồi của mình và status = pending

### Path Parameters

| Parameter | Type    | Required | Description     |
| --------- | ------- | -------- | --------------- |
| id        | integer | Yes      | ID của phản hồi |

### Request Body (multipart/form-data)

| Field            | Type   | Required | Description                       |
| ---------------- | ------ | -------- | --------------------------------- |
| feedback_content | string | No       | Nội dung mới (min: 10, max: 2000) |
| attachment       | file   | No       | File đính kèm mới                 |

### Response Success (200)

```json
{
    "success": true,
    "message": "Cập nhật phản hồi thành công",
    "data": {
        "feedback_id": 3,
        "student_id": 2,
        "feedback_content": "Em đã tham gia hoạt động... (updated)",
        "attachment_path": "point_feedbacks/1710234999_2_proof_new.jpg",
        "status": "pending",
        "updated_at": "2025-03-12T15:00:00.000000Z"
    }
}
```

### Response Error (400)

```json
{
    "success": false,
    "message": "Không thể cập nhật phản hồi đã được xử lý"
}
```

### Response Error (403)

```json
{
    "success": false,
    "message": "Bạn không có quyền cập nhật phản hồi này"
}
```

---

## 5. Cố vấn phản hồi và phê duyệt

### Endpoint

```http
POST /api/point-feedbacks/{id}/respond
```

### Authorization

**Advisor** (phụ trách lớp sinh viên)

### Path Parameters

| Parameter | Type    | Required | Description     |
| --------- | ------- | -------- | --------------- |
| id        | integer | Yes      | ID của phản hồi |

### Request Body (JSON)

| Field            | Type   | Required | Description                              |
| ---------------- | ------ | -------- | ---------------------------------------- |
| status           | string | Yes      | "approved" hoặc "rejected"               |
| advisor_response | string | Yes      | Phản hồi của cố vấn (min: 10, max: 1000) |

### Example Request

```json
{
    "status": "approved",
    "advisor_response": "Đã kiểm tra minh chứng. Em được cộng 5 điểm CTXH. Tiếp tục phát huy!"
}
```

### Response Success (200)

```json
{
    "success": true,
    "message": "Đã phê duyệt phản hồi thành công",
    "data": {
        "feedback_id": 1,
        "student_id": 2,
        "status": "approved",
        "advisor_response": "Đã kiểm tra minh chứng...",
        "advisor_id": 1,
        "response_at": "2025-03-12T16:00:00.000000Z",
        "advisor": {
            "advisor_id": 1,
            "full_name": "ThS. Trần Văn An",
            "email": "gv.an@school.edu.vn"
        },
        "student": {
            "student_id": 2,
            "full_name": "Trần Thị Thu Cẩm"
        },
        "semester": {
            "semester_id": 1,
            "semester_name": "Học kỳ 1"
        }
    }
}
```

### Response Error (400)

```json
{
    "success": false,
    "message": "Phản hồi đã được xử lý"
}
```

### Response Error (403)

```json
{
    "success": false,
    "message": "Bạn không có quyền phản hồi phản hồi này"
}
```

---

## 6. Xóa phản hồi

### Endpoint

```http
DELETE /api/point-feedbacks/{id}
```

### Authorization Rules

-   **Student**: Chỉ xóa phản hồi của mình (status = pending)

### Path Parameters

| Parameter | Type    | Required | Description     |
| --------- | ------- | -------- | --------------- |
| id        | integer | Yes      | ID của phản hồi |

### Response Success (200)

```json
{
    "success": true,
    "message": "Xóa phản hồi thành công"
}
```

### Response Error (400)

```json
{
    "success": false,
    "message": "Không thể xóa phản hồi đã được xử lý"
}
```

---

## 7. Thống kê phản hồi

### Endpoint

```http
GET /api/point-feedbacks/statistics/overview
```

### Authorization

**Advisor** (chỉ xem thống kê cho lớp mình phụ trách)

### Query Parameters

| Parameter   | Type    | Required | Description     |
| ----------- | ------- | -------- | --------------- |
| semester_id | integer | No       | Lọc theo học kỳ |

### Response Success (200)

```json
{
  "success": true,
  "data": {
    "total": 150,
    "pending": 45,
    "approved": 85,
    "rejected": 20,
    "by_semester": {
      "1": [
        {
          "semester_id": 1,
          "status": "pending",
          "count": 25,
          "semester": {
            "semester_id": 1,
            "semester_name": "Học kỳ 1",
            "academic_year": "2024-2025"
          }
        },
        {
          "semester_id": 1,
          "status": "approved",
          "count": 50,
          "semester": {...}
        }
      ]
    }
  }
}
```

---

# Student Monitoring Notes API

## Tổng quan

API quản lý ghi chú theo dõi sinh viên của cố vấn.

### Base URL

```
/api/monitoring-notes
```

### Categories

-   `academic`: Học tập
-   `personal`: Cá nhân
-   `attendance`: Chuyên cần
-   `other`: Khác

---

## 1. Lấy danh sách ghi chú

### Endpoint

```http
GET /api/monitoring-notes
```

### Query Parameters

| Parameter   | Type    | Required | Description                           |
| ----------- | ------- | -------- | ------------------------------------- |
| student_id  | integer | No       | Lọc theo sinh viên (advisor only)     |
| semester_id | integer | No       | Lọc theo học kỳ                       |
| category    | string  | No       | academic, personal, attendance, other |

### Authorization Rules

-   **Student**: Chỉ xem ghi chú về mình
-   **Advisor**: Xem ghi chú sinh viên trong lớp phụ trách + ghi chú do mình tạo

### Response Success (200)

```json
{
  "success": true,
  "data": [
    {
      "note_id": 1,
        "student_id": 2,
        "advisor_id": 1,
        "semester_id": 1,
        "category": "academic",
        "title": "Theo dõi SV Cẩm - Rớt môn IT001",
        "content": "SV có điểm giữa kỳ thấp (3.0), vắng 2 buổi...",
        "created_at": "2025-01-19T10:00:00.000000Z",
        "student": {
          "student_id": 2,
          "user_code": "210002",
          "full_name": "Trần Thị Thu Cẩm",
          "email": "sv.cam@school.edu.vn",
          "class_id": 1,
          "class": {
            "class_id": 1,
            "class_name": "DH21CNTT"
          }
        },
        "advisor": {
          "advisor_id": 1,
          "full_name": "ThS. Trần Văn An",
          "email": "gv.an@school.edu.vn"
        },
        "semester": {
          "semester_id": 1,
          "semester_name": "Học kỳ 1",
          "academic_year": "2024-2025"
        },
        "student_academic_data": {
          "gpa_semester": 6.5,
          "cpa_semester": 7.2,
          "academic_warnings_count": 1,
          "training_points_semester": 75,
          "social_points_cumulative": 120
        }
      }
    ]
  }
}
```

### Response Fields - student_academic_data

Mỗi ghi chú bao gồm thông tin học vụ của sinh viên trong học kỳ đó:

| Field                      | Type    | Description                                                   |
| -------------------------- | ------- | ------------------------------------------------------------- |
| `gpa_semester`             | float   | Điểm trung bình học kỳ (GPA) của sinh viên trong học kỳ đó    |
| `cpa_semester`             | float   | Điểm trung bình tích lũy (CPA) của sinh viên đến học kỳ đó    |
| `academic_warnings_count`  | integer | Tổng số lần cảnh cáo học vụ của sinh viên                     |
| `training_points_semester` | integer | Điểm rèn luyện (DRL) của sinh viên trong học kỳ đó            |
| `social_points_cumulative` | integer | Điểm công tác xã hội (CTXH) tích lũy từ đầu khóa đến hiện tại |

---

## 2. Xem chi tiết ghi chú

### Endpoint

```http
GET /api/monitoring-notes/{id}
```

### Path Parameters

| Parameter | Type    | Required | Description    |
| --------- | ------- | -------- | -------------- |
| id        | integer | Yes      | ID của ghi chú |

### Authorization Rules

-   **Student**: Chỉ xem ghi chú về mình
-   **Advisor**: Xem nếu sinh viên thuộc lớp phụ trách hoặc ghi chú do mình tạo

### Response Success (200)

```json
{
    "success": true,
    "data": {
        "note_id": 1,
        "student_id": 2,
        "advisor_id": 1,
        "semester_id": 1,
        "category": "academic",
        "title": "Theo dõi SV Cẩm - Rớt môn IT001",
        "content": "SV có điểm giữa kỳ thấp (3.0), vắng 2 buổi. Cần gặp gỡ và hỗ trợ thêm...",
        "created_at": "2025-01-19T10:00:00.000000Z",
        "student": {
            "student_id": 2,
            "user_code": "210002",
            "full_name": "Trần Thị Thu Cẩm",
            "email": "sv.cam@school.edu.vn",
            "phone_number": "091234567",
            "class_id": 1,
            "class": {
                "class_id": 1,
                "class_name": "DH21CNTT",
                "advisor_id": 1
            }
        },
        "advisor": {
            "advisor_id": 1,
            "full_name": "ThS. Trần Văn An",
            "email": "gv.an@school.edu.vn"
        },
        "semester": {
            "semester_id": 1,
            "semester_name": "Học kỳ 1",
            "academic_year": "2024-2025"
        },
        "student_academic_data": {
            "gpa_semester": 6.5,
            "cpa_semester": 7.2,
            "academic_warnings_count": 1,
            "training_points_semester": 75,
            "social_points_cumulative": 120
        }
    }
}
```

### Response Fields - student_academic_data

Tương tự endpoint `GET /api/monitoring-notes`, response bao gồm thông tin học vụ của sinh viên:

| Field                      | Type    | Description                                                   |
| -------------------------- | ------- | ------------------------------------------------------------- |
| `gpa_semester`             | float   | Điểm trung bình học kỳ (GPA) của sinh viên trong học kỳ đó    |
| `cpa_semester`             | float   | Điểm trung bình tích lũy (CPA) của sinh viên đến học kỳ đó    |
| `academic_warnings_count`  | integer | Tổng số lần cảnh cáo học vụ của sinh viên                     |
| `training_points_semester` | integer | Điểm rèn luyện (DRL) của sinh viên trong học kỳ đó            |
| `social_points_cumulative` | integer | Điểm công tác xã hội (CTXH) tích lũy từ đầu khóa đến hiện tại |

### Response Error (404)

```json
{
    "success": false,
    "message": "Không tìm thấy ghi chú"
}
```

### Response Error (403)

````json
{
    "success": false,
    "message": "Bạn không có quyền xem ghi chú này"
}

## 3. Tạo ghi chú mới

### Endpoint

```http
POST /api/monitoring-notes
````

### Authorization

**Advisor** (chỉ cho sinh viên trong lớp phụ trách)

### Request Body (JSON)

| Field       | Type    | Required | Description                           |
| ----------- | ------- | -------- | ------------------------------------- |
| user_code   | string  | Yes      | Mã số sinh viên                       |
| semester_id | integer | Yes      | ID học kỳ                             |
| category    | string  | Yes      | academic, personal, attendance, other |
| title       | string  | Yes      | Tiêu đề (max: 255)                    |
| content     | string  | Yes      | Nội dung (min: 10, max: 5000)         |

### Example Request

```json
{
    "user_code": "210002",
    "semester_id": 1,
    "category": "academic",
    "title": "Theo dõi chuyên cần HK2",
    "content": "Kiểm tra chuyên cần môn IT001 (học lại) của SV Cẩm hàng tuần. Tuần 1: Có mặt đầy đủ."
}
```

### Response Success (201)

```json
{
  "success": true,
  "message": "Tạo ghi chú theo dõi thành công",
  "data": {
    "note_id": 5,
    "student_id": 2,
    "advisor_id": 1,
    "semester_id": 1,
    "category": "academic",
    "title": "Theo dõi chuyên cần HK2",
    "content": "Kiểm tra chuyên cần...",
    "created_at": "2025-03-12T10:00:00.000000Z",
    "student": {...},
    "advisor": {...},
    "semester": {...}
  }
}
```

### Response Error (403)

```json
{
    "success": false,
    "message": "Bạn chỉ được tạo ghi chú cho sinh viên trong lớp mình phụ trách"
}
```

### Response Error (404)

```json
{
    "success": false,
    "message": "Không tìm thấy sinh viên với mã số này"
}
```

---

## 4. Cập nhật ghi chú

### Endpoint

```http
PUT /api/monitoring-notes/{id}
```

### Authorization

-   **Advisor**: Chỉ cập nhật ghi chú do mình tạo

### Request Body (JSON)

| Field    | Type   | Required | Description                           |
| -------- | ------ | -------- | ------------------------------------- |
| category | string | No       | academic, personal, attendance, other |
| title    | string | No       | Tiêu đề mới                           |
| content  | string | No       | Nội dung mới                          |

### Response Success (200)

```json
{
  "success": true,
  "message": "Cập nhật ghi chú thành công",
  "data": {
    "note_id": 5,
    "title": "Theo dõi chuyên cần HK2 (updated)",
    "content": "Updated content...",
    "student": {...},
    "advisor": {...},
    "semester": {...}
  }
}
```

### Response Error (403)

```json
{
    "success": false,
    "message": "Bạn chỉ được cập nhật ghi chú do mình tạo"
}
```

---

## 5. Xóa ghi chú

### Endpoint

```http
DELETE /api/monitoring-notes/{id}
```

### Authorization

-   **Advisor**: Chỉ xóa ghi chú do mình tạo

### Response Success (200)

```json
{
    "success": true,
    "message": "Xóa ghi chú thành công"
}
```

---

## 6. Timeline ghi chú của sinh viên

### Endpoint

```http
GET /api/monitoring-notes/student/{student_id}/timeline
```

### Path Parameters

| Parameter  | Type    | Required | Description      |
| ---------- | ------- | -------- | ---------------- |
| student_id | integer | Yes      | ID của sinh viên |

### Authorization Rules

-   **Student**: Chỉ xem timeline của mình
-   **Advisor**: Xem timeline sinh viên trong lớp phụ trách

### Response Success (200)

```json
{
  "success": true,
  "data": {
    "student": {
      "student_id": 2,
      "user_code": "210002",
      "full_name": "Trần Thị Thu Cẩm",
      "email": "sv.cam@school.edu.vn",
      "class": {
        "class_id": 1,
        "class_name": "DH21CNTT"
      }
    },
    "total_notes": 4,
    "by_category": {
      "academic": 2,
      "personal": 1,
      "attendance": 1,
      "other": 0
    },
    "notes_by_category": {
      "academic": [
        {
          "note_id": 1,
          "title": "Theo dõi SV Cẩm - Rớt môn IT001",
          "content": "...",
          "created_at": "2025-01-19T10:00:00.000000Z",
          "advisor": {...},
          "semester": {...}
        }
      ],
      "personal": [
        {
          "note_id": 4,
          "title": "SV Cẩm chia sẻ về hoàn cảnh",
          "content": "...",
          "created_at": "2025-03-01T14:30:00.000000Z",
          "advisor": {...},
          "semester": {...}
        }
      ],
      "attendance": [...]
    }
  }
}
```

---

## 7. Thống kê ghi chú

### Endpoint

```http
GET /api/monitoring-notes/statistics/overview
```

### Authorization

**Advisor** (chỉ xem thống kê cho lớp mình phụ trách)

### Query Parameters

| Parameter   | Type    | Required | Description     |
| ----------- | ------- | -------- | --------------- |
| semester_id | integer | No       | Lọc theo học kỳ |

### Response Success (200)

```json
{
  "success": true,
  "data": {
    "total": 125,
    "by_category": {
      "academic": 60,
      "personal": 25,
      "attendance": 30,
      "other": 10
    },
    "by_semester": {
      "1": [
        {
          "semester_id": 1,
          "category": "academic",
          "count": 35,
          "semester": {...}
        }
      ]
    },
    "recent_notes": [
      {
        "note_id": 10,
        "title": "Recent note",
        "created_at": "2025-03-12T10:00:00.000000Z",
        "student": {...},
        "advisor": {...}
      }
    ]
  }
}
```

---

# Error Handling

## Error Response Structure

```json
{
    "success": false,
    "message": "Error message here",
    "errors": {} // Optional validation errors
}
```

## Common HTTP Status Codes

| Code | Meaning               | Description                                       |
| ---- | --------------------- | ------------------------------------------------- |
| 200  | OK                    | Thành công                                        |
| 201  | Created               | Tạo mới thành công                                |
| 400  | Bad Request           | Dữ liệu không hợp lệ hoặc vi phạm logic nghiệp vụ |
| 401  | Unauthorized          | Không có token hoặc token hết hạn                 |
| 403  | Forbidden             | Không có quyền truy cập                           |
| 404  | Not Found             | Không tìm thấy tài nguyên                         |
| 422  | Unprocessable Entity  | Lỗi validation                                    |
| 500  | Internal Server Error | Lỗi server                                        |

## Example Error Responses

### 401 - Unauthorized

```json
{
    "success": false,
    "message": "Token has expired"
}
```

### 403 - Forbidden

```json
{
    "success": false,
    "message": "Bạn không có quyền xem phản hồi này"
}
```

### 404 - Not Found

```json
{
    "success": false,
    "message": "Không tìm thấy phản hồi"
}
```

### 422 - Validation Error

```json
{
    "success": false,
    "message": "Dữ liệu không hợp lệ",
    "errors": {
        "semester_id": ["The semester id field is required."],
        "feedback_content": [
            "The feedback content must be at least 10 characters."
        ]
    }
}
```

---

# Testing Examples

## Using cURL

### Student tạo phản hồi

```bash
curl -X POST https://api.example.com/api/point-feedbacks \
  -H "Authorization: Bearer student_token_here" \
  -H "Content-Type: multipart/form-data" \
  -F "semester_id=1" \
  -F "feedback_content=Em đã tham gia hoạt động tình nguyện..." \
  -F "attachment=@proof.jpg"
```

### Advisor phê duyệt

```bash
curl -X POST https://api.example.com/api/point-feedbacks/1/respond \
  -H "Authorization: Bearer advisor_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "approved",
    "advisor_response": "Đã kiểm tra và cộng điểm"
  }'
```

### Advisor tạo ghi chú

```bash
curl -X POST https://api.example.com/api/monitoring-notes \
  -H "Authorization: Bearer advisor_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "student_id": 2,
    "semester_id": 1,
    "category": "academic",
    "title": "Theo dõi học tập",
    "content": "Sinh viên cần hỗ trợ thêm về môn lập trình"
  }'
```

---

# Notes & Best Practices

## File Upload

-   Max size: 5MB
-   Allowed types: jpg, jpeg, png, pdf
-   Files are stored in `storage/app/public/point_feedbacks/`
-   Filename format: `{timestamp}_{student_id}_{original_name}`

## Authorization Flow

1. Middleware xác thực JWT token
2. Middleware inject `current_role` và `current_user_id` vào request
3. Controller kiểm tra quyền dựa trên role và ownership

## Performance Tips

-   Eager load relationships với `with()` để tránh N+1 query
-   Cache danh sách classes của advisor
-   Sử dụng filters để giới hạn kết quả trả về khi cần thiết

## Security

-   Luôn kiểm tra quyền trước khi trả dữ liệu
-   Validate input kỹ lưỡng
-   Không expose sensitive data trong response
-   Xóa file đính kèm khi xóa phản hồi
-   Kiểm tra ownership trước khi cho phép update/delete

---

# Routes Configuration

## routes/api.php

```php
use App\Http\Controllers\Api\PointFeedbackController;
use App\Http\Controllers\Api\StudentMonitoringNoteController;

// Point Feedback Routes
Route::middleware(['auth.api'])->prefix('point-feedbacks')->group(function () {
    // Xem danh sách phản hồi (có phân quyền tự động trong controller)
    Route::get('/', [PointFeedbackController::class, 'index']);

    // Xem chi tiết một phản hồi
    Route::get('/{id}', [PointFeedbackController::class, 'show']);

    // Thống kê phản hồi (Advisor only)
    Route::get('/statistics/overview', [PointFeedbackController::class, 'statistics'])
        ->middleware('check_role:advisor');

    // Sinh viên tạo phản hồi mới
    Route::post('/', [PointFeedbackController::class, 'store'])
        ->middleware('check_role:student');

    // Sinh viên cập nhật phản hồi (chỉ khi status = pending)
    Route::put('/{id}', [PointFeedbackController::class, 'update'])
        ->middleware('check_role:student');

    // Sinh viên xóa phản hồi (chỉ khi status = pending)
    Route::delete('/{id}', [PointFeedbackController::class, 'destroy'])
        ->middleware('check_role:student');

    // Cố vấn phản hồi và phê duyệt/từ chối
    Route::post('/{id}/respond', [PointFeedbackController::class, 'respond'])
        ->middleware('check_role:advisor');
});

// Student Monitoring Notes Routes
Route::middleware(['auth.api'])->prefix('monitoring-notes')->group(function () {
    // Xem danh sách ghi chú (có phân quyền tự động trong controller)
    Route::get('/', [StudentMonitoringNoteController::class, 'index']);

    // Xem chi tiết một ghi chú
    Route::get('/{id}', [StudentMonitoringNoteController::class, 'show']);

    // Xem timeline ghi chú của một sinh viên
    Route::get('/student/{student_id}/timeline', [StudentMonitoringNoteController::class, 'studentTimeline']);

    // Thống kê ghi chú (Advisor only)
    Route::get('/statistics/overview', [StudentMonitoringNoteController::class, 'statistics'])
        ->middleware('check_role:advisor');

    // Cố vấn tạo ghi chú mới
    Route::post('/', [StudentMonitoringNoteController::class, 'store'])
        ->middleware('check_role:advisor');

    // Cố vấn cập nhật ghi chú (chỉ ghi chú do mình tạo)
    Route::put('/{id}', [StudentMonitoringNoteController::class, 'update'])
        ->middleware('check_role:advisor');

    // Cố vấn xóa ghi chú (chỉ ghi chú do mình tạo)
    Route::delete('/{id}', [StudentMonitoringNoteController::class, 'destroy'])
        ->middleware('check_role:advisor');
});
```

---

# Database Indexes for Performance

## Recommended Indexes

```sql
-- Point_Feedbacks indexes
CREATE INDEX idx_point_feedbacks_student_semester
ON Point_Feedbacks(student_id, semester_id);

CREATE INDEX idx_point_feedbacks_status
ON Point_Feedbacks(status);

CREATE INDEX idx_point_feedbacks_advisor
ON Point_Feedbacks(advisor_id);

CREATE INDEX idx_point_feedbacks_created
ON Point_Feedbacks(created_at DESC);

-- Student_Monitoring_Notes indexes
CREATE INDEX idx_monitoring_notes_student_semester
ON Student_Monitoring_Notes(student_id, semester_id);

CREATE INDEX idx_monitoring_notes_advisor
ON Student_Monitoring_Notes(advisor_id);

CREATE INDEX idx_monitoring_notes_category
ON Student_Monitoring_Notes(category);

CREATE INDEX idx_monitoring_notes_created
ON Student_Monitoring_Notes(created_at DESC);
```

---

# Middleware Implementation

## app/Http/Middleware/JWTAuthMiddleware.php

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWTAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            // Xác thực token
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Lấy payload từ token
            $payload = JWTAuth::parseToken()->getPayload();

            // Inject role và user_id vào request
            $request->merge([
                'current_role' => $payload->get('role'),
                'current_user_id' => $payload->get('id')
            ]);

            return $next($request);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid or expired'
            ], 401);
        }
    }
}
```

## Register Middleware in app/Http/Kernel.php

```php
protected $routeMiddleware = [
    // ...existing middleware
    'auth:api' => \App\Http\Middleware\JWTAuthMiddleware::class,
];
```

---

# Frontend Integration Examples

## React/TypeScript Example

### API Service

```typescript
// services/pointFeedbackService.ts
import axios from "axios";

const API_BASE_URL = "https://api.example.com/api";

interface PointFeedback {
    feedback_id: number;
    student_id: number;
    semester_id: number;
    feedback_content: string;
    attachment_path: string | null;
    status: "pending" | "approved" | "rejected";
    advisor_response: string | null;
    created_at: string;
}

class PointFeedbackService {
    private getAuthHeaders() {
        const token = localStorage.getItem("jwt_token");
        return {
            headers: {
                Authorization: `Bearer ${token}`,
            },
        };
    }

    async getFeedbacks(params?: {
        semester_id?: number;
        status?: string;
        student_id?: number;
        page?: number;
    }) {
        const response = await axios.get(`${API_BASE_URL}/point-feedbacks`, {
            ...this.getAuthHeaders(),
            params,
        });
        return response.data;
    }

    async createFeedback(data: {
        semester_id: number;
        feedback_content: string;
        attachment?: File;
    }) {
        const formData = new FormData();
        formData.append("semester_id", data.semester_id.toString());
        formData.append("feedback_content", data.feedback_content);

        if (data.attachment) {
            formData.append("attachment", data.attachment);
        }

        const response = await axios.post(
            `${API_BASE_URL}/point-feedbacks`,
            formData,
            {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem(
                        "jwt_token"
                    )}`,
                    "Content-Type": "multipart/form-data",
                },
            }
        );
        return response.data;
    }

    async respondToFeedback(
        id: number,
        data: {
            status: "approved" | "rejected";
            advisor_response: string;
        }
    ) {
        const response = await axios.post(
            `${API_BASE_URL}/point-feedbacks/${id}/respond`,
            data,
            this.getAuthHeaders()
        );
        return response.data;
    }

    async deleteFeedback(id: number) {
        const response = await axios.delete(
            `${API_BASE_URL}/point-feedbacks/${id}`,
            this.getAuthHeaders()
        );
        return response.data;
    }

    async getStatistics(params?: { semester_id?: number; class_id?: number }) {
        const response = await axios.get(
            `${API_BASE_URL}/point-feedbacks/statistics`,
            {
                ...this.getAuthHeaders(),
                params,
            }
        );
        return response.data;
    }
}

export default new PointFeedbackService();
```

### React Component Example

```typescript
// components/PointFeedbackList.tsx
import React, { useState, useEffect } from "react";
import pointFeedbackService from "../services/pointFeedbackService";

interface Feedback {
    feedback_id: number;
    feedback_content: string;
    status: string;
    created_at: string;
    student: {
        full_name: string;
        user_code: string;
    };
}

const PointFeedbackList: React.FC = () => {
    const [feedbacks, setFeedbacks] = useState<Feedback[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        loadFeedbacks();
    }, []);

    const loadFeedbacks = async () => {
        try {
            setLoading(true);
            const response = await pointFeedbackService.getFeedbacks();

            if (response.success) {
                setFeedbacks(response.data);
            } else {
                setError(response.message);
            }
        } catch (err: any) {
            setError(err.response?.data?.message || "Error loading feedbacks");
        } finally {
            setLoading(false);
        }
    };

    const handleApprove = async (feedbackId: number) => {
        const response = prompt("Enter your response:");
        if (!response) return;

        try {
            await pointFeedbackService.respondToFeedback(feedbackId, {
                status: "approved",
                advisor_response: response,
            });

            alert("Feedback approved successfully!");
            loadFeedbacks(); // Reload list
        } catch (err: any) {
            alert(err.response?.data?.message || "Error approving feedback");
        }
    };

    if (loading) return <div>Loading...</div>;
    if (error) return <div>Error: {error}</div>;

    return (
        <div className="feedback-list">
            <h2>Point Feedbacks</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Content</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {feedbacks.map((feedback) => (
                        <tr key={feedback.feedback_id}>
                            <td>{feedback.feedback_id}</td>
                            <td>
                                {feedback.student.full_name}
                                <br />
                                <small>{feedback.student.user_code}</small>
                            </td>
                            <td>
                                {feedback.feedback_content.substring(0, 100)}...
                            </td>
                            <td>
                                <span className={`status-${feedback.status}`}>
                                    {feedback.status}
                                </span>
                            </td>
                            <td>
                                {new Date(
                                    feedback.created_at
                                ).toLocaleDateString()}
                            </td>
                            <td>
                                {feedback.status === "pending" && (
                                    <>
                                        <button
                                            onClick={() =>
                                                handleApprove(
                                                    feedback.feedback_id
                                                )
                                            }
                                        >
                                            Approve
                                        </button>
                                        <button
                                            onClick={() =>
                                                handleReject(
                                                    feedback.feedback_id
                                                )
                                            }
                                        >
                                            Reject
                                        </button>
                                    </>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

export default PointFeedbackList;
```

---

# Mobile (React Native) Integration

```typescript
// services/monitoringNoteService.ts
import axios from "axios";

const API_BASE_URL = "https://api.example.com/api";

interface MonitoringNote {
    note_id: number;
    student_id: number;
    category: "academic" | "personal" | "attendance" | "other";
    title: string;
    content: string;
    created_at: string;
}

class MonitoringNoteService {
    private async getToken() {
        // For React Native, use AsyncStorage
        const AsyncStorage =
            require("@react-native-async-storage/async-storage").default;
        return await AsyncStorage.getItem("jwt_token");
    }

    async getNotes(params?: {
        student_id?: number;
        semester_id?: number;
        category?: string;
    }) {
        const token = await this.getToken();

        const response = await axios.get(`${API_BASE_URL}/monitoring-notes`, {
            headers: { Authorization: `Bearer ${token}` },
            params,
        });
        return response.data;
    }

    async getStudentTimeline(studentId: number) {
        const token = await this.getToken();

        const response = await axios.get(
            `${API_BASE_URL}/monitoring-notes/student/${studentId}/timeline`,
            {
                headers: { Authorization: `Bearer ${token}` },
            }
        );
        return response.data;
    }

    async createNote(data: {
        student_id: number;
        semester_id: number;
        category: string;
        title: string;
        content: string;
    }) {
        const token = await this.getToken();

        const response = await axios.post(
            `${API_BASE_URL}/monitoring-notes`,
            data,
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    "Content-Type": "application/json",
                },
            }
        );
        return response.data;
    }

    async updateNote(id: number, data: Partial<MonitoringNote>) {
        const token = await this.getToken();

        const response = await axios.put(
            `${API_BASE_URL}/monitoring-notes/${id}`,
            data,
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    "Content-Type": "application/json",
                },
            }
        );
        return response.data;
    }

    async deleteNote(id: number) {
        const token = await this.getToken();

        const response = await axios.delete(
            `${API_BASE_URL}/monitoring-notes/${id}`,
            {
                headers: { Authorization: `Bearer ${token}` },
            }
        );
        return response.data;
    }
}

export default new MonitoringNoteService();
```

---

# Testing

## Unit Test Example (PHPUnit)

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Student;
use App\Models\Advisor;
use App\Models\PointFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PointFeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Seed test data
    }

    /** @test */
    public function student_can_create_feedback()
    {
        $student = Student::find(1);
        $token = JWTAuth::claims([
            'id' => $student->student_id,
            'role' => 'student'
        ])->fromUser($student);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->postJson('/api/point-feedbacks', [
            'semester_id' => 1,
            'feedback_content' => 'I participated in blood donation activity'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'feedback_id',
                        'student_id',
                        'semester_id',
                        'feedback_content',
                        'status'
                    ]
                ]);

        $this->assertDatabaseHas('Point_Feedbacks', [
            'student_id' => $student->student_id,
            'semester_id' => 1,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function advisor_can_approve_feedback()
    {
        $advisor = Advisor::find(1);
        $token = JWTAuth::claims([
            'id' => $advisor->advisor_id,
            'role' => 'advisor'
        ])->fromUser($advisor);

        $feedback = PointFeedback::create([
            'student_id' => 1,
            'semester_id' => 1,
            'feedback_content' => 'Test feedback',
            'status' => 'pending'
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->postJson("/api/point-feedbacks/{$feedback->feedback_id}/respond", [
            'status' => 'approved',
            'advisor_response' => 'Approved after verification'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Đã phê duyệt phản hồi thành công'
                ]);

        $this->assertDatabaseHas('Point_Feedbacks', [
            'feedback_id' => $feedback->feedback_id,
            'status' => 'approved',
            'advisor_id' => $advisor->advisor_id
        ]);
    }

    /** @test */
    public function student_cannot_view_other_student_feedback()
    {
        $student = Student::find(2);
        $token = JWTAuth::claims([
            'id' => $student->student_id,
            'role' => 'student'
        ])->fromUser($student);

        $otherStudentFeedback = PointFeedback::create([
            'student_id' => 1, // Different student
            'semester_id' => 1,
            'feedback_content' => 'Test',
            'status' => 'pending'
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->getJson("/api/point-feedbacks/{$otherStudentFeedback->feedback_id}");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem phản hồi này'
                ]);
    }
}
```

---

# Common Issues & Solutions

## Issue 1: Token Expired

**Problem**: User gets 401 after token expires

**Solution**: Implement token refresh mechanism

```typescript
// services/authService.ts
async refreshToken() {
  try {
    const response = await axios.post('/api/auth/refresh');
    localStorage.setItem('jwt_token', response.data.token);
    return response.data.token;
  } catch (error) {
    // Redirect to login
    window.location.href = '/login';
  }
}
```

## Issue 2: File Upload Fails

**Problem**: Large files or wrong mime type

**Solution**:

1. Check file size < 5MB
2. Validate mime type on client
3. Configure server upload limits

```php
// config/filesystems.php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
    'throw' => false,
],
```

## Issue 3: Advisor Cannot See Student Notes

**Problem**: Advisor not in correct class

**Solution**: Verify class relationships

```php
// Check advisor classes
$advisor = Advisor::with('classes')->find($advisorId);
$classIds = $advisor->classes->pluck('class_id');
```

---

# Changelog

## Version 1.0.0 (2025-03-12)

-   Initial release
-   Point Feedback CRUD operations
-   Student Monitoring Notes CRUD operations
-   Role-based authorization
-   File upload support
-   Statistics endpoints
-   Timeline view for students

---

# Support & Contact

For API support or questions:

-   Email: lecntp@gmail.com
-   GitHub Issues: [project-repo]/issues
-   Documentation: https://docs.example.com/api

---

**End of Documentation**
