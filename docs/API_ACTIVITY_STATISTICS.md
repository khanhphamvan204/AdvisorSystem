# API Documentation - Activity Statistics (Admin)

## Overview
API này dành cho **Admin** (vai trò quản lý khoa/đơn vị) để thống kê hoạt động của các lớp và sinh viên trong khoa mình quản lý.

**Base URL**: `/api/activities/statistics`

**Authentication**: Bearer Token (JWT)

**Authorization**: Role = `admin` only

---

## 1. Thống kê hoạt động của một lớp

Xem chi tiết các hoạt động mà một lớp đã tham gia, bao gồm số lượng đăng ký, tỷ lệ tham gia, điểm rèn luyện/CTXH đã nhận.

### Endpoint
```
GET /api/activities/statistics/class/{classId}
```

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| classId | integer | Yes | ID của lớp cần thống kê |

### Authorization Rules
- Role phải là `admin`
- Lớp phải thuộc khoa mà admin quản lý (faculty_id = admin.unit_id)

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "class_info": {
      "class_id": 1,
      "class_name": "DH21CNTT",
      "faculty_name": "Khoa Công nghệ Thông tin",
      "advisor_name": "ThS. Trần Văn An",
      "advisor_email": "gv.an@school.edu.vn",
      "total_students": 45
    },
    "admin_info": {
      "unit_name": "Khoa Công nghệ Thông tin",
      "unit_type": "faculty"
    },
    "summary": {
      "total_activities": 8,
      "activities_by_status": {
        "upcoming": 2,
        "ongoing": 1,
        "completed": 5,
        "cancelled": 0
      },
      "total_registrations": 120,
      "total_attended": 95,
      "total_points_earned": {
        "training_points": 750,
        "social_points": 280,
        "total": 1030
      }
    },
    "activities": [
      {
        "activity_id": 1,
        "title": "Hiến máu nhân đạo 2025",
        "start_time": "2025-03-15 08:00:00",
        "end_time": "2025-03-15 11:30:00",
        "location": "Sảnh A",
        "status": "completed",
        "organizer": "Phòng Công tác Sinh viên",
        "advisor_name": "ThS. Lê Hoàng Cường",
        "total_roles": 2,
        "total_registered": 18,
        "status_breakdown": {
          "registered": 2,
          "attended": 15,
          "absent": 1,
          "cancelled": 0
        },
        "participation_rate": "18 sinh viên",
        "points_earned": {
          "training_points": 100,
          "social_points": 75,
          "total": 175
        }
      },
      {
        "activity_id": 2,
        "title": "Workshop AI Tạo sinh",
        "start_time": "2025-03-20 14:00:00",
        "end_time": "2025-03-20 16:00:00",
        "location": "Phòng H.201",
        "status": "completed",
        "organizer": "Khoa Công nghệ Thông tin",
        "advisor_name": "ThS. Trần Văn An",
        "total_roles": 1,
        "total_registered": 25,
        "status_breakdown": {
          "registered": 0,
          "attended": 23,
          "absent": 2,
          "cancelled": 0
        },
        "participation_rate": "25 sinh viên",
        "points_earned": {
          "training_points": 184,
          "social_points": 0,
          "total": 184
        }
      }
    ]
  }
}
```

### Error Responses

#### 403 Forbidden - Role không hợp lệ
```json
{
  "success": false,
  "message": "Chỉ admin mới có quyền truy cập"
}
```

#### 403 Forbidden - Lớp không thuộc khoa
```json
{
  "success": false,
  "message": "Lớp này không thuộc khoa bạn quản lý"
}
```

#### 404 Not Found - Lớp không tồn tại
```json
{
  "success": false,
  "message": "Lớp không tồn tại"
}
```

#### 404 Not Found - Không tìm thấy thông tin admin
```json
{
  "success": false,
  "message": "Không tìm thấy thông tin đơn vị của admin"
}
```

---

## 2. Thống kê các lớp tham gia một hoạt động

Xem danh sách các lớp (thuộc khoa) đã được gán vào một hoạt động, bao gồm số lượng sinh viên đăng ký, tỷ lệ tham gia của từng lớp.

### Endpoint
```
GET /api/activities/statistics/activity/{activityId}
```

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| activityId | integer | Yes | ID của hoạt động cần thống kê |

### Authorization Rules
- Role phải là `admin`
- Hoạt động phải có ít nhất 1 lớp thuộc khoa mà admin quản lý

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "activity_info": {
      "activity_id": 1,
      "title": "Hiến máu nhân đạo 2025",
      "start_time": "2025-03-15 08:00:00",
      "end_time": "2025-03-15 11:30:00",
      "location": "Sảnh A",
      "status": "completed",
      "organizer": "Phòng Công tác Sinh viên",
      "advisor_name": "ThS. Lê Hoàng Cường",
      "total_roles": 2
    },
    "admin_info": {
      "unit_name": "Khoa Công nghệ Thông tin",
      "unit_type": "faculty"
    },
    "summary": {
      "total_classes": 2,
      "total_students_in_classes": 85,
      "total_registered": 35,
      "total_attended": 30,
      "average_participation_rate": "41.18%"
    },
    "classes": [
      {
        "class_id": 1,
        "class_name": "DH21CNTT",
        "faculty_name": "Khoa Công nghệ Thông tin",
        "total_students": 45,
        "total_registered": 18,
        "participation_rate": "40.00%",
        "status_breakdown": {
          "registered": 2,
          "attended": 15,
          "absent": 1,
          "cancelled": 0
        },
        "role_breakdown": [
          {
            "role_name": "Người hiến máu",
            "count": 12,
            "attended": 10
          },
          {
            "role_name": "Tình nguyện viên",
            "count": 6,
            "attended": 5
          }
        ],
        "attended_students": [
          {
            "student_id": 1,
            "user_code": "210001",
            "full_name": "Nguyễn Văn Hùng",
            "role_name": "Người hiến máu",
            "points_awarded": 5,
            "point_type": "ctxh"
          },
          {
            "student_id": 2,
            "user_code": "210002",
            "full_name": "Trần Thị Thu Cẩm",
            "role_name": "Tình nguyện viên",
            "points_awarded": 10,
            "point_type": "ctxh"
          }
        ]
      },
      {
        "class_id": 4,
        "class_name": "DH23CNTT",
        "faculty_name": "Khoa Công nghệ Thông tin",
        "total_students": 40,
        "total_registered": 17,
        "participation_rate": "42.50%",
        "status_breakdown": {
          "registered": 1,
          "attended": 15,
          "absent": 1,
          "cancelled": 0
        },
        "role_breakdown": [
          {
            "role_name": "Người hiến máu",
            "count": 10,
            "attended": 9
          },
          {
            "role_name": "Tình nguyện viên",
            "count": 7,
            "attended": 6
          }
        ],
        "attended_students": [
          {
            "student_id": 4,
            "user_code": "230001",
            "full_name": "Đỗ Minh Nam",
            "role_name": "Người hiến máu",
            "points_awarded": 5,
            "point_type": "ctxh"
          }
        ]
      }
    ]
  }
}
```

### Error Responses

#### 403 Forbidden - Role không hợp lệ
```json
{
  "success": false,
  "message": "Chỉ admin mới có quyền truy cập"
}
```

#### 403 Forbidden - Hoạt động không có lớp thuộc khoa
```json
{
  "success": false,
  "message": "Hoạt động này không có lớp nào thuộc khoa bạn quản lý"
}
```

#### 404 Not Found - Hoạt động không tồn tại
```json
{
  "success": false,
  "message": "Hoạt động không tồn tại"
}
```

---

## 3. Thống kê tổng quan khoa

Xem tổng quan các chỉ số về hoạt động của tất cả lớp trong khoa.

### Endpoint
```
GET /api/activities/statistics/faculty/overview
```

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Authorization Rules
- Role phải là `admin`
- Admin phải có unit_id (thuộc một đơn vị/khoa)


### Success Response (200 OK)
Active_students: Số sinh viên đã attended ít nhất 1 hoạt động

```json
{
  "success": true,
  "data": {
    "faculty_info": {
      "unit_name": "Khoa Công nghệ Thông tin",
      "unit_type": "faculty"
    },
    "summary": {
      "total_classes": 3,
      "total_students": 130,
      "total_activities_assigned": 24,
      "total_registrations": 380,
      "total_active_students": 95
    },
    "classes": [
      {
        "class_id": 1,
        "class_name": "DH21CNTT",
        "advisor_name": "ThS. Trần Văn An",
        "total_students": 45,
        "total_activities": 8,
        "total_registrations": 120,
        "active_students": 38,
        "activity_participation_rate": "84.44%"
      },
      {
        "class_id": 4,
        "class_name": "DH23CNTT",
        "advisor_name": "ThS. Trần Văn An",
        "total_students": 40,
        "total_activities": 7,
        "total_registrations": 105,
        "active_students": 32,
        "activity_participation_rate": "80.00%"
      },
      {
        "class_id": 5,
        "class_name": "DH24CNTT",
        "advisor_name": "ThS. Nguyễn Văn Bình",
        "total_students": 45,
        "total_activities": 9,
        "total_registrations": 155,
        "active_students": 25,
        "activity_participation_rate": "55.56%"
      }
    ]
  }
}
```

### Error Responses

#### 403 Forbidden - Role không hợp lệ
```json
{
  "success": false,
  "message": "Chỉ admin mới có quyền truy cập"
}
```

#### 404 Not Found - Không tìm thấy thông tin admin
```json
{
  "success": false,
  "message": "Không tìm thấy thông tin đơn vị của admin"
}
```

---

## Routes Configuration

---

## Data Models

### Class Info Object
```typescript
{
  class_id: number;
  class_name: string;
  faculty_name: string;
  advisor_name: string;
  advisor_email: string;
  total_students: number;
}
```

### Activity Info Object
```typescript
{
  activity_id: number;
  title: string;
  start_time: string; // ISO 8601 datetime
  end_time: string; // ISO 8601 datetime
  location: string;
  status: "upcoming" | "ongoing" | "completed" | "cancelled";
  organizer: string;
  advisor_name: string;
  total_roles: number;
}
```

### Status Breakdown Object
```typescript
{
  registered: number; // Đã đăng ký
  attended: number;   // Đã tham gia
  absent: number;     // Vắng mặt
  cancelled: number;  // Đã hủy
}
```

### Points Earned Object
```typescript
{
  training_points: number;  // Điểm rèn luyện
  social_points: number;    // Điểm CTXH
  total: number;            // Tổng điểm
}
```

### Role Breakdown Object
```typescript
{
  role_name: string;
  count: number;      // Tổng số đăng ký
  attended: number;   // Số người đã tham gia
}
```

### Student Registration Object
```typescript
{
  student_id: number;
  user_code: string;
  full_name: string;
  role_name: string;
  points_awarded: number;
  point_type: "ctxh" | "ren_luyen";
}
```

---

## Usage Examples

### Example 1: Thống kê hoạt động của lớp DH21CNTT

**Request:**
```bash
curl -X GET "https://api.school.edu.vn/api/activities/statistics/class/1" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json"
```

**Use Case:**
- Admin khoa CNTT muốn xem lớp DH21CNTT đã tham gia những hoạt động nào
- Xem tỷ lệ tham gia và điểm đã nhận của lớp
- Đánh giá hiệu quả hoạt động ngoại khóa của lớp

---

### Example 2: Thống kê lớp tham gia hoạt động "Hiến máu"

**Request:**
```bash
curl -X GET "https://api.school.edu.vn/api/activities/statistics/activity/1" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json"
```

**Use Case:**
- Admin muốn xem hoạt động "Hiến máu nhân đạo" có những lớp nào tham gia
- So sánh tỷ lệ tham gia giữa các lớp
- Xem chi tiết sinh viên nào đã attended để có thể cộng điểm

---

### Example 3: Xem tổng quan khoa CNTT

**Request:**
```bash
curl -X GET "https://api.school.edu.vn/api/activities/statistics/faculty/overview" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json"
```

**Use Case:**
- Admin xem tổng quan các chỉ số hoạt động của toàn khoa
- So sánh các lớp với nhau về mức độ tích cực
- Báo cáo định kỳ về hoạt động ngoại khóa

---

## Security Considerations

1. **Authentication**: Tất cả requests phải có JWT token hợp lệ
2. **Authorization**: Chỉ role `admin` mới được truy cập
3. **Data Isolation**: Admin chỉ thấy dữ liệu của khoa mình quản lý
4. **Token Expiration**: Token JWT hết hạn sau 24 giờ (configurable)

---

## Performance Notes

- API sử dụng **Eager Loading** để tối ưu query
- Với lớp có nhiều sinh viên (>100), response time ~200-500ms
- Nên cache kết quả cho endpoint `/faculty/overview` vì ít thay đổi

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-11-10 | Initial release |

---

## Support

Nếu có vấn đề hoặc câu hỏi, vui lòng liên hệ:
- Email: support@school.edu.vn
- Docs: https://docs.school.edu.vn/api