# TÀI LIỆU API - XUẤT ĐIỂM RÈN LUYỆN VÀ CTXH

## Tổng quan

API cho phép Admin xuất điểm rèn luyện và điểm công tác xã hội theo lớp hoặc theo khoa, theo học kỳ cụ thể. File xuất ra định dạng Excel (.xlsx) với header đầy đủ thông tin trường, khoa, học kỳ.

**Đặc điểm quan trọng:**

-   ✅ **Điểm rèn luyện**: Tính theo từng học kỳ (70 điểm ban đầu + attended - absent)
-   ✅ **Điểm CTXH**: Tích lũy từ đầu khóa đến học kỳ được chọn
-   ✅ Sử dụng `PointCalculationService` để đảm bảo logic tính điểm nhất quán
-   ✅ Xuất file Excel với thống kê chi tiết và phân bổ xếp loại

**Base URL**: `https://your-domain.com/api/admin`

**Authentication**: Bearer Token (JWT)

**Authorization**: Chỉ dành cho Admin (role = 'admin')

---

## 1. XUẤT ĐIỂM RÈN LUYỆN THEO LỚP

### Endpoint

```
GET /admin/export/training-points/class
```

### Headers

```json
{
    "Authorization": "Bearer {token}"
}
```

### Query Parameters

| Tham số     | Kiểu dữ liệu | Bắt buộc | Mô tả                       |
| ----------- | ------------ | -------- | --------------------------- |
| class_id    | integer      | Có       | ID của lớp cần xuất điểm    |
| semester_id | integer      | Có       | ID của học kỳ cần xuất điểm |

### Ví dụ Request

```
GET /admin/export/training-points/class?class_id=1&semester_id=1
```

### Logic tính điểm

**Điểm rèn luyện = 70 (ban đầu) + Σ(điểm HĐ attended) - Σ(điểm HĐ absent)**

-   Hoạt động `attended`: **Cộng điểm**
-   Hoạt động `absent`: **Trừ điểm** (penalize)
-   Hoạt động `registered`: **Không tính** (chưa điểm danh)
-   Chỉ tính các hoạt động có `point_type = 'ren_luyen'`
-   Chỉ tính các hoạt động diễn ra trong khoảng thời gian của học kỳ

### Response Success (200)

**File Excel được tải về trực tiếp**

-   **Content-Type**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
-   **Content-Disposition**: `attachment; filename="DiemRenLuyen_DH21CNTT_Học kỳ 1_20250327143022.xlsx"`
-   File sẽ tự động download về máy người dùng

**Lưu ý**: API này trả về file Excel trực tiếp, không phải JSON response.

### Cấu trúc file Excel xuất ra

```
┌────────────────────────────────────────────────────────────────────────────┐
│                TRƯỜNG ĐẠI HỌC CÔNG THƯƠNG TP.HCM                          │
│                   KHOA CÔNG NGHỆ THÔNG TIN                                │
│                                                                            │
│                      BẢNG ĐIỂM RÈN LUYỆN                                  │
│                         Lớp: DH21CNTT                                     │
│            Học kỳ: Học kỳ 1 - Năm học: 2024-2025                        │
│                                                                            │
├─────┬─────────┬──────────────┬──────────┬────────────┬──────────────┬─────┤
│ STT │  MSSV   │  Họ và tên   │   Lớp    │ Điểm ban   │ Số HĐ tham   │ Số  │
│     │         │              │          │    đầu     │     dự       │ HĐ  │
│     │         │              │          │            │              │vắng │
├─────┼─────────┼──────────────┼──────────┼────────────┼──────────────┼─────┤
│  1  │ 210001  │ Nguyễn Văn H │ DH21CNTT │     70     │      3       │  0  │
│  2  │ 210002  │ Trần Thị T.. │ DH21CNTT │     70     │      1       │  1  │
├─────┴─────────┴──────────────┴──────────┴────────────┴──────────────┴─────┤
│                            THỐNG KÊ CHUNG                                  │
│ Tổng số sinh viên: 10                                                     │
│ Điểm trung bình: 78.50                                                    │
│ Phân bổ xếp loại:                                                         │
│   - Xuất sắc: 2 SV (20.0%)                                               │
│   - Tốt: 3 SV (30.0%)                                                    │
│   - Khá: 4 SV (40.0%)                                                    │
│   - Trung bình: 1 SV (10.0%)                                             │
└────────────────────────────────────────────────────────────────────────────┘
```

**Các cột trong bảng:**

1. **STT**: Số thứ tự
2. **MSSV**: Mã số sinh viên
3. **Họ và tên**: Họ tên đầy đủ
4. **Lớp**: Tên lớp
5. **Điểm ban đầu**: 70 điểm cố định
6. **Số HĐ tham dự**: Số hoạt động đã tham dự (attended)
7. **Số HĐ vắng**: Số hoạt động vắng mặt (absent)
8. **Điểm rèn luyện**: Tổng điểm cuối cùng
9. **Xếp loại**: Xuất sắc/Tốt/Khá/TB/Yếu/Kém

---

## 2. XUẤT ĐIỂM RÈN LUYỆN THEO KHOA

### Endpoint

```
GET /admin/export/training-points/faculty
```

### Headers

```json
{
    "Authorization": "Bearer {token}"
}
```

### Query Parameters

| Tham số     | Kiểu dữ liệu | Bắt buộc | Mô tả                       |
| ----------- | ------------ | -------- | --------------------------- |
| faculty_id  | integer      | Có       | ID của khoa cần xuất điểm   |
| semester_id | integer      | Có       | ID của học kỳ cần xuất điểm |

### Ví dụ Request

```
GET /admin/export/training-points/faculty?faculty_id=1&semester_id=1
```

### Response Success (200)

**File Excel được tải về trực tiếp**

-   **Content-Type**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
-   **Content-Disposition**: `attachment; filename="DiemRenLuyen_Khoa Công nghệ Thông tin_Học kỳ 1_20250327143525.xlsx"`
-   File sẽ tự động download về máy người dùng

**Lưu ý**: File xuất theo khoa sẽ chứa sinh viên từ tất cả các lớp thuộc khoa đó, được sắp xếp theo lớp và MSSV.

---

## 3. XUẤT ĐIỂM CTXH THEO LỚP

### Endpoint

```
GET /admin/export/social-points/class
```

### Headers

```json
{
    "Authorization": "Bearer {token}"
}
```

### Query Parameters

| Tham số     | Kiểu dữ liệu | Bắt buộc | Mô tả                       |
| ----------- | ------------ | -------- | --------------------------- |
| class_id    | integer      | Có       | ID của lớp cần xuất điểm    |
| semester_id | integer      | Có       | ID của học kỳ cần xuất điểm |

### Ví dụ Request

```
GET /admin/export/social-points/class?class_id=1&semester_id=1
```

### Logic tính điểm

**Điểm CTXH = Σ(điểm tất cả HĐ CTXH attended từ đầu khóa đến học kỳ được chọn)**

-   **TÍCH LŨY** từ đầu khóa học (không reset mỗi học kỳ)
-   Chỉ tính hoạt động có `status = 'attended'`
-   Chỉ tính hoạt động có `point_type = 'ctxh'`
-   Tính tất cả hoạt động có `start_time <= end_date` của học kỳ được chọn

### Response Success (200)

**File Excel được tải về trực tiếp**

-   **Content-Type**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
-   **Content-Disposition**: `attachment; filename="DiemCTXH_DH21CNTT_Học kỳ 1_20250327144030.xlsx"`
-   File sẽ tự động download về máy người dùng

### Cấu trúc file Excel xuất ra

```
┌────────────────────────────────────────────────────────────────────────────┐
│                TRƯỜNG ĐẠI HỌC CÔNG THƯƠNG TP.HCM                          │
│                   KHOA CÔNG NGHỆ THÔNG TIN                                │
│                                                                            │
│               BẢNG ĐIỂM CÔNG TÁC XÃ HỘI (TÍCH LŨY)                       │
│                         Lớp: DH21CNTT                                     │
│            Tính đến: Học kỳ 1 - Năm học: 2024-2025                       │
│              (Điểm CTXH được tích lũy từ đầu khóa học)                   │
│                                                                            │
├─────┬─────────┬──────────────┬──────────┬────────────┬──────────────┬─────┤
│ STT │  MSSV   │  Họ và tên   │   Lớp    │ Số HĐ CTXH │  Điểm CTXH   │ Xếp │
│     │         │              │          │            │              │loại │
├─────┼─────────┼──────────────┼──────────┼────────────┼──────────────┼─────┤
│  1  │ 210001  │ Nguyễn Văn H │ DH21CNTT │     5      │      25      │ Xuất│
│     │         │              │          │            │              │ sắc │
│  2  │ 210002  │ Trần Thị T.. │ DH21CNTT │     2      │       8      │ Khá │
├─────┴─────────┴──────────────┴──────────┴────────────┴──────────────┴─────┤
│                            THỐNG KÊ CHUNG                                  │
│ Tổng số sinh viên: 10                                                     │
│ Điểm trung bình: 15.80                                                    │
│ Phân bổ xếp loại:                                                         │
│   - Xuất sắc: 3 SV (30.0%)                                               │
│   - Tốt: 2 SV (20.0%)                                                    │
│   - Khá: 3 SV (30.0%)                                                    │
│   - Trung bình: 2 SV (20.0%)                                             │
└────────────────────────────────────────────────────────────────────────────┘
```

**Các cột trong bảng:**

1. **STT**: Số thứ tự
2. **MSSV**: Mã số sinh viên
3. **Họ và tên**: Họ tên đầy đủ
4. **Lớp**: Tên lớp
5. **Số HĐ CTXH**: Tổng số hoạt động CTXH đã tham dự (tích lũy)
6. **Điểm CTXH**: Tổng điểm CTXH tích lũy
7. **Xếp loại**: Xuất sắc/Tốt/Khá/TB/Yếu

---

## 4. XUẤT ĐIỂM CTXH THEO KHOA

### Endpoint

```
GET /admin/export/social-points/faculty
```

### Headers

```json
{
    "Authorization": "Bearer {token}"
}
```

### Query Parameters

| Tham số     | Kiểu dữ liệu | Bắt buộc | Mô tả                               |
| ----------- | ------------ | -------- | ----------------------------------- |
| faculty_id  | integer      | Có       | ID của khoa cần xuất điểm           |
| semester_id | integer      | Có       | ID của học kỳ làm mốc tính tích lũy |

### Ví dụ Request

```
GET /admin/export/social-points/faculty?faculty_id=1&semester_id=1
```

### Response Success (200)

**File Excel được tải về trực tiếp**

-   **Content-Type**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
-   **Content-Disposition**: `attachment; filename="DiemCTXH_Khoa Công nghệ Thông tin_Học kỳ 1_20250327144535.xlsx"`
-   File sẽ tự động download về máy người dùng

---

## RESPONSE ERRORS (TẤT CẢ ENDPOINTS)

### 401 - Unauthorized

```json
{
    "success": false,
    "message": "Token không hợp lệ hoặc đã hết hạn"
}
```

### 403 - Forbidden

```json
{
    "success": false,
    "message": "Bạn không có quyền truy cập"
}
```

### 422 - Validation Error

```json
{
    "success": false,
    "message": "Dữ liệu không hợp lệ",
    "errors": {
        "class_id": ["Lớp không tồn tại"],
        "semester_id": ["Học kỳ không tồn tại"]
    }
}
```

### 500 - Server Error

```json
{
    "success": false,
    "message": "Lỗi khi xuất điểm rèn luyện",
    "error": "Chi tiết lỗi..."
}
```

---

## THANG ĐIỂM VÀ XẾP LOẠI

### Xếp loại điểm rèn luyện (DRL)

| Xếp loại   | Khoảng điểm |
| ---------- | ----------- |
| Xuất sắc   | ≥ 90        |
| Tốt        | 80-89       |
| Khá        | 65-79       |
| Trung bình | 50-64       |
| Yếu        | 35-49       |
| Kém        | < 35        |

### Xếp loại điểm CTXH

| Xếp loại   | Khoảng điểm |
| ---------- | ----------- |
| Xuất sắc   | ≥ 20        |
| Tốt        | 15-19       |
| Khá        | 10-14       |
| Trung bình | 5-9         |
| Yếu        | < 5         |

---

## SO SÁNH ĐIỂM RÈN LUYỆN VS ĐIỂM CTXH

| Tiêu chí         | Điểm Rèn luyện      | Điểm CTXH            |
| ---------------- | ------------------- | -------------------- |
| **Phạm vi tính** | Theo từng học kỳ    | Tích lũy từ đầu khóa |
| **Điểm ban đầu** | 70                  | 0                    |
| **Reset mỗi kỳ** | ✅ Có (reset về 70) | ❌ Không (tích lũy)  |
| **Attended**     | Cộng điểm           | Cộng điểm            |
| **Absent**       | Trừ điểm (penalize) | Không tính           |
| **Registered**   | Không tính          | Không tính           |
| **Point type**   | `ren_luyen`         | `ctxh`               |

---

## VÍ DỤ TÍNH ĐIỂM

### Ví dụ 1: Điểm rèn luyện HK1

Sinh viên A trong HK1 có:

-   Điểm ban đầu: **70**
-   Tham dự Workshop AI (+8): **attended** → +8
-   Tham dự CLB Lập trình (+10): **attended** → +10
-   Vắng Seminar Khởi nghiệp (+5): **absent** → -5
-   Đăng ký Thi Olympic (+15): **registered** → không tính

**Tổng điểm DRL = 70 + 8 + 10 - 5 = 83 (Tốt)**

### Ví dụ 2: Điểm CTXH tích lũy

Sinh viên B từ HK1 đến HK3:

-   HK1: Hiến máu (+5), Tình nguyện (+10) → **15 điểm**
-   HK2: Dọn vệ sinh (+3), Hiến máu (+5) → **+8 điểm**
-   HK3: Chưa tham gia → **+0 điểm**

**Tổng điểm CTXH đến HK3 = 15 + 8 + 0 = 23 (Xuất sắc)**

---

## VÍ DỤ CURL

### Xuất điểm rèn luyện theo lớp

```bash
curl -X GET "https://your-domain.com/api/admin/export/training-points/class?class_id=1&semester_id=1" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -o DiemRenLuyen.xlsx
```

### Xuất điểm CTXH theo khoa

```bash
curl -X GET "https://your-domain.com/api/admin/export/social-points/faculty?faculty_id=1&semester_id=1" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -o DiemCTXH.xlsx
```

---

## LƯU Ý QUAN TRỌNG

### 1. Logic tính điểm

-   ✅ Sử dụng `PointCalculationService::calculateTrainingPoints()` và `PointCalculationService::calculateSocialPoints()`
-   ✅ Đảm bảo nhất quán với các API khác trong hệ thống
-   ✅ Điểm rèn luyện: tính theo HK, điểm CTXH: tích lũy

### 2. Middleware

-   `auth.api`: Xác thực JWT token
-   `check_role:admin`: Kiểm tra quyền Admin
-   Token payload tự động inject `current_user_id` và `current_role` vào request

### 3. File xuất

-   Định dạng: `.xlsx` (Excel 2007+)
-   Lưu tại: `storage/app/public/exports/`
-   URL download: `https://domain.com/storage/exports/filename.xlsx`
-   Tên file: `DiemRenLuyen_{Tên}_HocKy_{Timestamp}.xlsx`

### 4. Dữ liệu

-   Chỉ xuất sinh viên có `status = 'studying'`
-   Sắp xếp theo MSSV
-   Theo khoa: group theo lớp

### 5. Thống kê

-   File Excel bao gồm:
    -   Tổng số sinh viên
    -   Điểm trung bình
    -   Phân bổ xếp loại (số lượng + phần trăm)

---

## MÃ LỖI HTTP

| Mã  | Ý nghĩa                            |
| --- | ---------------------------------- |
| 200 | Thành công                         |
| 401 | Chưa xác thực / Token không hợp lệ |
| 403 | Không có quyền (không phải admin)  |
| 422 | Dữ liệu đầu vào không hợp lệ       |
| 500 | Lỗi server                         |
