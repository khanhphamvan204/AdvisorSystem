# API Documentation - Statistics Endpoints

## 1. Thống kê tổng quan cho Admin Khoa

### Endpoint
```
GET /api/admin/dashboard-overview
```

### Mô tả
Lấy thống kê tổng quan về giảng viên, sinh viên, môn học và lớp học thuộc khoa mà admin quản lý.

### Authentication
Yêu cầu Bearer Token với role `admin`

### Headers
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

### Request
Không có body

### Response

#### Success (200 OK)
```json
{
    "success": true,
    "message": "Lấy thống kê tổng quan thành công",
    "data": {
        "unit_id": 1,
        "unit_name": "Khoa Công nghệ Thông tin",
        "total_advisors": 15,
        "total_students": 450,
        "total_courses": 45,
        "total_classes": 12
    }
}
```

#### Error Responses

**403 Forbidden - Không có quyền truy cập**
```json
{
    "success": false,
    "message": "Chỉ admin mới có quyền truy cập thống kê tổng quan"
}
```

**400 Bad Request - Admin chưa được gán khoa**
```json
{
    "success": false,
    "message": "Admin chưa được gán vào khoa nào"
}
```

**401 Unauthorized - Token không hợp lệ**
```json
{
    "success": false,
    "message": "Token không hợp lệ hoặc đã hết hạn"
}
```

**500 Internal Server Error**
```json
{
    "success": false,
    "message": "Lỗi khi lấy thống kê: {error_message}"
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `unit_id` | integer | ID của khoa |
| `unit_name` | string | Tên khoa |
| `total_advisors` | integer | Tổng số giảng viên cố vấn thuộc khoa |
| `total_students` | integer | Tổng số sinh viên thuộc khoa |
| `total_courses` | integer | Tổng số môn học thuộc khoa |
| `total_classes` | integer | Tổng số lớp học thuộc khoa |

### Business Rules
- Admin chỉ xem được thống kê của khoa mình quản lý (dựa vào `unit_id`)
- Chỉ tính các advisor có role = 'advisor', không tính admin
- Sinh viên được tính qua quan hệ: Students → Classes → Units (faculty_id)

### Example Usage

#### cURL
```bash
curl -X GET "http://localhost:8000/api/admin/dashboard-overview" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json"
```

#### JavaScript (Axios)
```javascript
const response = await axios.get('/api/admin/dashboard-overview', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});
console.log(response.data);
```

---

## 2. Thống kê tổng quan cho Cố vấn học tập

### Endpoint
```
GET /api/advisor/overview
```

### Mô tả
Lấy thống kê tổng quan về sinh viên, lớp học, thông báo và cuộc họp mà cố vấn quản lý.

### Authentication
Yêu cầu Bearer Token với role `advisor`

### Headers
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

### Request
Không có body

### Response

#### Success (200 OK)
```json
{
    "success": true,
    "message": "Lấy thống kê cố vấn thành công",
    "data": {
        "advisor_id": 1,
        "advisor_name": "ThS. Trần Văn An",
        "unit_name": "Khoa Công nghệ Thông tin",
        "total_students": 45,
        "total_classes": 3,
        "total_notifications": 12,
        "total_meetings": 8,
        "upcoming_meetings": 2,
        "unread_notifications": 5
    }
}
```

#### Error Responses

**403 Forbidden - Không có quyền truy cập**
```json
{
    "success": false,
    "message": "Chỉ cố vấn học tập mới có quyền truy cập"
}
```

**404 Not Found - Không tìm thấy advisor**
```json
{
    "success": false,
    "message": "Không tìm thấy thông tin cố vấn"
}
```

**401 Unauthorized - Token không hợp lệ**
```json
{
    "success": false,
    "message": "Token không hợp lệ hoặc đã hết hạn"
}
```

**500 Internal Server Error**
```json
{
    "success": false,
    "message": "Lỗi khi lấy thống kê: {error_message}"
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `advisor_id` | integer | ID của cố vấn |
| `advisor_name` | string | Tên đầy đủ của cố vấn |
| `unit_name` | string\|null | Tên khoa/đơn vị mà cố vấn thuộc về |
| `total_students` | integer | Tổng số sinh viên mà cố vấn quản lý |
| `total_classes` | integer | Tổng số lớp được phân công |
| `total_notifications` | integer | Tổng số thông báo đã gửi |
| `total_meetings` | integer | Tổng số cuộc họp đã tổ chức |
| `upcoming_meetings` | integer | Số cuộc họp sắp tới (status='scheduled' và meeting_time > now) |
| `unread_notifications` | integer | Số lượng thông báo chưa được sinh viên đọc |

### Business Rules
- Advisor chỉ xem được thống kê của các lớp mình quản lý
- Sinh viên được tính qua quan hệ: Students → Classes (advisor_id)
- Cuộc họp sắp tới: status = 'scheduled' và meeting_time > thời gian hiện tại
- Thông báo chưa đọc: đếm từ bảng Notification_Recipients có is_read = false

### Example Usage

#### cURL
```bash
curl -X GET "http://localhost:8000/api/advisor/overview" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json"
```

#### JavaScript (Axios)
```javascript
const response = await axios.get('/api/advisor/overview', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});
console.log(response.data);
```

#### React Example
```javascript
import { useState, useEffect } from 'react';
import axios from 'axios';

function AdvisorDashboard() {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchStats = async () => {
            try {
                const token = localStorage.getItem('token');
                const response = await axios.get('/api/advisor/overview', {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                setStats(response.data.data);
            } catch (error) {
                console.error('Lỗi khi lấy thống kê:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchStats();
    }, []);

    if (loading) return <div>Đang tải...</div>;

    return (
        <div className="dashboard">
            <h1>Xin chào, {stats.advisor_name}</h1>
            <div className="stats-grid">
                <div className="stat-card">
                    <h3>Sinh viên</h3>
                    <p>{stats.total_students}</p>
                </div>
                <div className="stat-card">
                    <h3>Lớp học</h3>
                    <p>{stats.total_classes}</p>
                </div>
                <div className="stat-card">
                    <h3>Cuộc họp sắp tới</h3>
                    <p>{stats.upcoming_meetings}</p>
                </div>
                <div className="stat-card">
                    <h3>Thông báo chưa đọc</h3>
                    <p>{stats.unread_notifications}</p>
                </div>
            </div>
        </div>
    );
}
```

---

## Route Configuration

### File: `routes/api.php`

```php
<?php

use App\Http\Controllers\StatisticsController;

// Routes cho Admin
Route::middleware(['auth.api', 'check_role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard-overview', [StatisticsController::class, 'getDashboardOverview']);
});

// Routes cho Advisor
Route::middleware(['auth.api', 'check_role:advisor'])->prefix('advisor')->group(function () {
    Route::get('/overview', [StatisticsController::class, 'getAdvisorOverview']);
});
```

---

## Database Relationships

### Admin Dashboard Overview
```
Advisors (role='advisor', unit_id) → COUNT
Students → Classes (faculty_id) → COUNT
Courses (unit_id) → COUNT
Classes (faculty_id) → COUNT
```

### Advisor Overview
```
Students → Classes (advisor_id) → COUNT
Classes (advisor_id) → COUNT
Notifications (advisor_id) → COUNT
Meetings (advisor_id) → COUNT
Meetings (advisor_id, status='scheduled', meeting_time > now) → COUNT
Notification_Recipients → Notifications (advisor_id), is_read=false → COUNT
```

---

## Error Handling

### Common Error Codes

| Status Code | Meaning | Action |
|-------------|---------|--------|
| 200 | Success | Dữ liệu trả về thành công |
| 400 | Bad Request | Kiểm tra dữ liệu đầu vào |
| 401 | Unauthorized | Token hết hạn hoặc không hợp lệ, yêu cầu đăng nhập lại |
| 403 | Forbidden | Không có quyền truy cập, kiểm tra role |
| 404 | Not Found | Không tìm thấy tài nguyên |
| 500 | Internal Server Error | Lỗi server, liên hệ admin |

---

## Testing

### Postman Collection

#### Test Admin Dashboard Overview
1. **Pre-request Script**: Lưu token admin
2. **Request**: GET `/api/admin/dashboard-overview`
3. **Tests**:
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has required fields", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data).to.have.property('unit_id');
    pm.expect(jsonData.data).to.have.property('total_advisors');
    pm.expect(jsonData.data).to.have.property('total_students');
    pm.expect(jsonData.data).to.have.property('total_courses');
    pm.expect(jsonData.data).to.have.property('total_classes');
});
```

#### Test Advisor Overview
1. **Pre-request Script**: Lưu token advisor
2. **Request**: GET `/api/advisor/overview`
3. **Tests**:
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has required fields", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.data).to.have.property('advisor_id');
    pm.expect(jsonData.data).to.have.property('total_students');
    pm.expect(jsonData.data).to.have.property('total_classes');
    pm.expect(jsonData.data).to.have.property('total_notifications');
    pm.expect(jsonData.data).to.have.property('total_meetings');
});
```

---

## Notes

- Cả hai endpoint đều yêu cầu authentication qua JWT token
- Middleware `check_role` kiểm tra quyền truy cập dựa trên role trong token
- Admin chỉ thấy thống kê của khoa mình quản lý (unit_id)
- Advisor chỉ thấy thống kê của các lớp mình được phân công (advisor_id)
- Tất cả các số liệu đều được tính real-time từ database
- Không có caching, mọi request đều query trực tiếp

---

## Performance Considerations

- Với số lượng lớn dữ liệu, nên cân nhắc thêm caching (Redis)
- Có thể tối ưu bằng eager loading các relationships
- Nên thêm index cho các cột thường xuyên query (unit_id, advisor_id, faculty_id)

```sql
-- Indexes đề xuất
CREATE INDEX idx_advisors_unit_role ON Advisors(unit_id, role);
CREATE INDEX idx_classes_advisor ON Classes(advisor_id);
CREATE INDEX idx_classes_faculty ON Classes(faculty_id);
CREATE INDEX idx_students_class ON Students(class_id);
CREATE INDEX idx_meetings_advisor_status ON Meetings(advisor_id, status, meeting_time);
CREATE INDEX idx_notifications_advisor ON Notifications(advisor_id);
```