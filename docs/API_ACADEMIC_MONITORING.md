# Tài liệu API - Academic Monitoring Controller

## Mục lục

-   [Giới thiệu](#giới-thiệu)
-   [Xác thực](#xác-thực)
-   [APIs cho Student](#apis-cho-student)
    -   [Xem báo cáo học kỳ của mình](#1-xem-báo-cáo-học-kỳ-của-mình)
    -   [Xem danh sách cảnh cáo của mình](#2-xem-danh-sách-cảnh-cáo-của-mình)
-   [APIs cho Advisor](#apis-cho-advisor)
    -   [Xem báo cáo học kỳ sinh viên](#3-xem-báo-cáo-học-kỳ-sinh-viên)
    -   [Xem sinh viên có nguy cơ bỏ học](#4-xem-sinh-viên-có-nguy-cơ-bỏ-học)
    -   [Tạo cảnh cáo học vụ](#5-tạo-cảnh-cáo-học-vụ)
    -   [Xem danh sách cảnh cáo đã tạo](#6-xem-danh-sách-cảnh-cáo-đã-tạo)
    -   [Thống kê tổng quan học vụ](#7-thống-kê-tổng-quan-học-vụ)
    -   [Cập nhật báo cáo học kỳ](#8-cập-nhật-báo-cáo-học-kỳ)
    -   [Cập nhật báo cáo hàng loạt](#9-cập-nhật-báo-cáo-hàng-loạt)
-   [APIs cho Admin](#apis-cho-admin)
    -   [Tải template import cảnh cáo](#10-tải-template-import-cảnh-cáo)
    -   [Import cảnh cáo học vụ từ Excel](#11-import-cảnh-cáo-học-vụ-từ-excel)

---

## Giới thiệu

Controller này quản lý các chức năng theo dõi học vụ và cảnh cáo học vụ cho sinh viên, bao gồm:

-   Xem báo cáo học kỳ (GPA, CPA, điểm rèn luyện, điểm CTXH)
-   Phát hiện sinh viên có nguy cơ bỏ học
-   Tạo và quản lý cảnh cáo học vụ
-   Thống kê và cập nhật điểm số hàng loạt

**Base URL**: `/api`

---

## Xác thực

Tất cả các API đều yêu cầu xác thực qua middleware `auth.api`.

**Headers bắt buộc:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Phân quyền:**

-   `student`: Chỉ xem được thông tin của chính mình
-   `advisor`: Xem và quản lý sinh viên trong lớp mình phụ trách

---

## APIs cho Student

### 1. Xem báo cáo học kỳ của mình

**Endpoint:** `GET /api/academic/my-semester-report/{semester_id}`

**Mô tả:** Sinh viên xem báo cáo học tập của chính mình trong một học kỳ cụ thể.

**Parameters:**
| Tên | Kiểu | Bắt buộc | Mô tả |
|-----|------|----------|-------|
| semester_id | integer | Có | ID của học kỳ cần xem |

**Response thành công (200):**

```json
{
    "success": true,
    "data": {
        "student_info": {
            "student_id": 1,
            "user_code": "SV001",
            "full_name": "Nguyễn Văn A",
            "class_name": "SE01"
        },
        "semester_info": {
            "semester_name": "HK1",
            "academic_year": "2024-2025"
        },
        "report": {
            "gpa": 7.5,
            "gpa_4_scale": 3.0,
            "cpa_10_scale": 7.8,
            "cpa_4_scale": 3.2,
            "credits_registered": 18,
            "credits_passed": 15,
            "training_point_summary": 85,
            "social_point_summary": 120,
            "outcome": "Học tiếp"
        },
        "course_grades": [
            {
                "course_code": "IT001",
                "course_name": "Lập trình căn bản",
                "credits": 3,
                "grade_10": 8.5,
                "grade_letter": "A",
                "grade_4": 4.0,
                "status": "passed"
            }
        ]
    }
}
```

**Response lỗi:**

-   **403 Forbidden:** Không có quyền xem báo cáo

```json
{
    "success": false,
    "message": "Bạn chỉ được xem báo cáo của chính mình"
}
```

-   **404 Not Found:** Không tìm thấy báo cáo

```json
{
    "success": false,
    "message": "Không tìm thấy báo cáo học kỳ"
}
```

---

### 2. Xem danh sách cảnh cáo của mình

**Endpoint:** `GET /api/academic/my-warnings`

**Mô tả:** Sinh viên xem tất cả các cảnh cáo học vụ đã nhận.

**Response thành công (200):**

```json
{
    "success": true,
    "data": {
        "warnings": [
            {
                "warning_id": 1,
                "title": "Cảnh cáo học vụ mức 2 - HK1 2024-2025",
                "content": "Sinh viên Nguyễn Văn A (MSSV: SV001) có CPA thang 4 là 1.8, thấp hơn ngưỡng quy định 2.0...",
                "advice": "Sinh viên cần:\n1. Đăng ký học lại các môn bị rớt...",
                "semester": "HK1 2024-2025",
                "advisor_name": "TS. Trần Văn B",
                "created_at": "15/10/2024 14:30"
            }
        ],
        "total": 1
    }
}
```

---

## APIs cho Advisor

### 3. Xem báo cáo học kỳ sinh viên

**Endpoint:** `GET /api/academic/semester-report/{student_id}/{semester_id}`

**Mô tả:** Cố vấn học tập xem báo cáo học kỳ của sinh viên trong lớp mình quản lý.

**Parameters:**
| Tên | Kiểu | Bắt buộc | Mô tả |
|-----|------|----------|-------|
| student_id | integer | Có | ID của sinh viên |
| semester_id | integer | Có | ID của học kỳ |

**Response:** Giống API #1

**Response lỗi (403):**

```json
{
    "success": false,
    "message": "Bạn chỉ được xem sinh viên trong lớp mình quản lý"
}
```

---

### 4. Xem sinh viên có nguy cơ bỏ học

**Endpoint:** `GET /api/academic/at-risk-students`

**Mô tả:** Liệt kê các sinh viên trong lớp có nguy cơ học vụ hoặc bỏ học.

**Query Parameters:**
| Tên | Kiểu | Bắt buộc | Mô tả |
|-----|------|----------|-------|
| semester_id | integer | Không | ID học kỳ (mặc định: học kỳ mới nhất) |

**Response thành công (200):**

```json
{
    "success": true,
    "data": {
        "semester": {
            "semester_name": "HK1",
            "academic_year": "2024-2025"
        },
        "at_risk_students": [
            {
                "student_id": 5,
                "user_code": "SV005",
                "full_name": "Lê Thị C",
                "class_name": "SE01",
                "status": "active",
                "cpa_4_scale": 1.5,
                "warning_threshold": 2.0,
                "risk_level": "high",
                "risk_reasons": [
                    "CPA (1.5) dưới ngưỡng cảnh cáo (2.0)",
                    "Rớt 4 môn trong học kỳ",
                    "Tỷ lệ tín chỉ đạt thấp (45%)"
                ],
                "failed_courses_count": 4
            }
        ],
        "summary": {
            "total": 3,
            "critical": 0,
            "high": 2,
            "medium": 1
        }
    }
}
```

**Giải thích risk_level:**

-   `low`: Nguy cơ thấp
-   `medium`: Nguy cơ trung bình
-   `high`: Nguy cơ cao
-   `critical`: Nguy cơ rất cao

---

### 5. Tạo cảnh cáo học vụ

**Endpoint:** `POST /api/academic/create-warnings`

**Mô tả:** Tạo cảnh cáo học vụ cho một hoặc nhiều sinh viên.

**Request Body:**

```json
{
    "semester_id": 1,
    "student_ids": [5, 7, 9]
}
```

**Body Parameters:**
| Tên | Kiểu | Bắt buộc | Mô tả |
|-----|------|----------|-------|
| semester_id | integer | Có | ID học kỳ |
| student_ids | array | Có | Danh sách ID sinh viên cần cảnh cáo |

**Response thành công (200):**

```json
{
    "success": true,
    "message": "Tạo cảnh cáo học vụ thành công",
    "data": {
        "warnings_created": [
            {
                "student_name": "Lê Thị C",
                "user_code": "SV005",
                "cpa_4_scale": 1.5,
                "threshold": 2.0,
                "warning_level": 2,
                "warning_id": 15
            }
        ],
        "total_created": 2,
        "errors": [
            "Sinh viên Trần Văn D không đạt ngưỡng cảnh cáo (CPA: 2.5, Ngưỡng: 2.0)"
        ]
    }
}
```

**Response lỗi (422):**

```json
{
    "success": false,
    "message": "Dữ liệu không hợp lệ",
    "errors": {
        "semester_id": ["Học kỳ không tồn tại"],
        "student_ids": ["Danh sách sinh viên không được rỗng"]
    }
}
```

**Lưu ý:**

-   Chỉ tạo cảnh cáo cho sinh viên có CPA dưới ngưỡng quy định
-   Không tạo trùng cảnh cáo trong cùng một học kỳ
-   Mức độ cảnh cáo (1-3) được tính tự động dựa trên khoảng cách CPA với ngưỡng

---

### 6. Xem danh sách cảnh cáo đã tạo

**Endpoint:** `GET /api/academic/warnings-created`

**Mô tả:** Xem tất cả cảnh cáo đã tạo bởi cố vấn.

**Response thành công (200):**

```json
{
    "success": true,
    "data": {
        "warnings": [
            {
                "warning_id": 15,
                "title": "Cảnh cáo học vụ mức 2 - HK1 2024-2025",
                "student_name": "Lê Thị C",
                "user_code": "SV005",
                "class_name": "SE01",
                "semester": "HK1 2024-2025",
                "created_at": "20/10/2024 10:15"
            }
        ],
        "total": 5
    }
}
```

---

### 7. Thống kê tổng quan học vụ

**Endpoint:** `GET /api/academic/statistics`

**Mô tả:** Xem thống kê tổng quan về kết quả học tập của lớp.

**Query Parameters:**
| Tên | Kiểu | Bắt buộc | Mô tả |
|-----|------|----------|-------|
| semester_id | integer | Không | ID học kỳ (mặc định: học kỳ mới nhất) |

**Response thành công (200):**

```json
{
    "success": true,
    "data": {
        "semester": {
            "semester_name": "HK1",
            "academic_year": "2024-2025"
        },
        "total_students": 35,
        "statistics": {
            "excellent": 5,
            "good": 12,
            "average": 10,
            "weak": 5,
            "poor": 3,
            "warned": 8,
            "dropout_risk": 3
        },
        "percentages": {
            "excellent": 14.29,
            "good": 34.29,
            "average": 28.57,
            "weak": 14.29,
            "poor": 8.57,
            "warned": 22.86,
            "dropout_risk": 8.57
        }
    }
}
```

**Giải thích phân loại:**

-   **Excellent (Giỏi):** GPA 4.0 >= 3.6
-   **Good (Khá):** 3.0 <= GPA 4.0 < 3.6
-   **Average (TB):** 2.0 <= GPA 4.0 < 3.0
-   **Weak (Yếu):** 1.0 <= GPA 4.0 < 2.0
-   **Poor (Kém):** GPA 4.0 < 1.0
-   **Warned:** Sinh viên bị cảnh cáo học vụ
-   **Dropout Risk:** Nguy cơ bỏ học cao/rất cao

---

### 8. Cập nhật báo cáo học kỳ

**Endpoint:** `POST /api/academic/update-semester-report`

**Mô tả:** Tính toán lại GPA, CPA, điểm rèn luyện và điểm CTXH cho một sinh viên.

**Request Body:**

```json
{
    "student_id": 5,
    "semester_id": 1
}
```

**Body Parameters:**
| Tên | Kiểu | Bắt buộc | Mô tả |
|-----|------|----------|-------|
| student_id | integer | Có | ID sinh viên |
| semester_id | integer | Có | ID học kỳ |

**Response thành công (200):**

```json
{
    "success": true,
    "message": "Cập nhật báo cáo học kỳ thành công",
    "data": {
        "student_name": "Lê Thị C",
        "user_code": "SV005",
        "report": {
            "gpa": 6.5,
            "gpa_4_scale": 2.5,
            "cpa_10_scale": 6.8,
            "cpa_4_scale": 2.7,
            "credits_registered": 18,
            "credits_passed": 15,
            "training_point_summary": 75,
            "social_point_summary": 95,
            "outcome": "Học tiếp"
        }
    }
}
```

**Response lỗi (403):**

```json
{
    "success": false,
    "message": "Bạn chỉ được cập nhật báo cáo cho sinh viên trong lớp mình quản lý"
}
```

**Response lỗi (422):**

```json
{
    "success": false,
    "message": "Dữ liệu không hợp lệ",
    "errors": {
        "student_id": ["Sinh viên không tồn tại"],
        "semester_id": ["Học kỳ không tồn tại"]
    }
}
```

---

### 9. Cập nhật báo cáo hàng loạt

**Endpoint:** `POST /api/academic/batch-update-semester-reports`

**Mô tả:** Cập nhật báo cáo học kỳ cho tất cả sinh viên trong một lớp.

**Request Body:**

```json
{
    "class_id": 1,
    "semester_id": 1
}
```

**Body Parameters:**
| Tên | Kiểu | Bắt buộc | Mô tả |
|-----|------|----------|-------|
| class_id | integer | Có | ID lớp học |
| semester_id | integer | Có | ID học kỳ |

**Response thành công (200):**

```json
{
    "success": true,
    "message": "Cập nhật báo cáo hàng loạt hoàn tất",
    "data": {
        "class_name": "SE01",
        "results": [
            {
                "student_id": 1,
                "user_code": "SV001",
                "full_name": "Nguyễn Văn A",
                "gpa": 7.5,
                "cpa_4_scale": 3.0,
                "training_points": 85,
                "social_points": 120
            },
            {
                "student_id": 2,
                "user_code": "SV002",
                "full_name": "Trần Thị B",
                "gpa": 8.2,
                "cpa_4_scale": 3.5,
                "training_points": 90,
                "social_points": 135
            }
        ],
        "errors": [
            {
                "student_id": 5,
                "error": "Không tìm thấy điểm của sinh viên trong học kỳ này"
            }
        ],
        "summary": {
            "total_processed": 35,
            "success_count": 33,
            "error_count": 2
        }
    }
}
```

**Response lỗi (403):**

```json
{
    "success": false,
    "message": "Bạn chỉ được cập nhật báo cáo cho lớp mình quản lý"
}
```

**Lưu ý:**

-   API này xử lý từng sinh viên một và trả về kết quả chi tiết
-   Nếu có lỗi với một số sinh viên, các sinh viên khác vẫn được xử lý
-   Thích hợp để đồng bộ dữ liệu sau khi nhập điểm

---

## APIs cho Admin

### 10. Tải template import cảnh cáo

**Endpoint:** `GET /api/academic/download-warnings-template`

**Mô tả:** Admin tải file Excel template để nhập cảnh cáo học vụ hàng loạt.

**Yêu cầu quyền:** `admin`

**Response thành công (200):**

-   File Excel được tải về với tên: `Template_Import_Canh_Cao_Hoc_Vu_YYYYMMDD_HHMMSS.xlsx`
-   Content-Type: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

**Cấu trúc file template:**

**Sheet 1: "Cảnh cáo học vụ"**

| Mã SV  | Họ tên       | Lớp      | Học kỳ   | Năm học   | Tiêu đề               | Nội dung            | Lời khuyên       |
| ------ | ------------ | -------- | -------- | --------- | --------------------- | ------------------- | ---------------- |
| 210001 | Nguyễn Văn A | DH21CNTT | Học kỳ 1 | 2024-2025 | Cảnh cáo học vụ mức 1 | Sinh viên có GPA... | Sinh viên cần... |

**Các cột:**

-   **Mã SV** (Bắt buộc): Mã số sinh viên
-   **Họ tên** (Tùy chọn): Tên sinh viên - dùng để kiểm tra
-   **Lớp** (Tùy chọn): Tên lớp - dùng để kiểm tra
-   **Học kỳ** (Bắt buộc): Tên học kỳ (VD: "Học kỳ 1", "Học kỳ 2")
-   **Năm học** (Bắt buộc): Năm học (VD: "2024-2025")
-   **Tiêu đề** (Bắt buộc): Tiêu đề cảnh cáo
-   **Nội dung** (Bắt buộc): Nội dung chi tiết
-   **Lời khuyên** (Tùy chọn): Lời khuyên cho sinh viên (tự động tạo nếu trống)

**Sheet 2: "Hướng dẫn"**

-   Hướng dẫn chi tiết về cách sử dụng template
-   Quy trình import
-   Xử lý lỗi

**Response lỗi (403):**

```json
{
    "success": false,
    "message": "Chỉ admin mới có quyền tải template"
}
```

**Response lỗi (500):**

```json
{
    "success": false,
    "message": "Lỗi khi tạo template: [Chi tiết lỗi]"
}
```

---

### 11. Import cảnh cáo học vụ từ Excel

**Endpoint:** `POST /api/academic/import-warnings`

**Mô tả:** Admin import cảnh cáo học vụ hàng loạt từ file Excel.

**Yêu cầu quyền:** `admin`

**Content-Type:** `multipart/form-data`

**Request:**

```
POST /api/academic/import-warnings
Content-Type: multipart/form-data
Authorization: Bearer {token}

file: [Excel file]
```

**Form Data Parameters:**
| Tên | Kiểu | Bắt buộc | Mô tả |
|-----|------|----------|-------|
| file | file | Có | File Excel (.xlsx hoặc .xls, max 10MB) |

**Response thành công (200):**

```json
{
    "success": true,
    "message": "Import cảnh cáo học vụ hoàn tất",
    "data": {
        "summary": {
            "total_rows_processed": 10,
            "success_count": 8,
            "error_count": 2,
            "warning_count": 3
        },
        "details": {
            "success": [
                {
                    "row": 2,
                    "user_code": "210001",
                    "student_name": "Nguyễn Văn A",
                    "class_name": "DH21CNTT",
                    "advisor_name": "TS. Trần Văn B",
                    "semester": "Học kỳ 1 2024-2025",
                    "warning_id": 15
                }
            ],
            "errors": [
                {
                    "row": 3,
                    "user_code": "210999",
                    "error": "Không tìm thấy sinh viên với mã số này"
                },
                {
                    "row": 5,
                    "user_code": "210002",
                    "error": "Sinh viên chưa được phân lớp"
                }
            ],
            "warnings": [
                {
                    "row": 4,
                    "user_code": "210003",
                    "warning": "Tên trong file (Nguyễn A) khác với tên trong hệ thống (Nguyễn Văn A)"
                }
            ]
        }
    }
}
```

**Response lỗi (403):**

```json
{
    "success": false,
    "message": "Chỉ admin mới có quyền import cảnh cáo học vụ"
}
```

**Response lỗi (422) - File không hợp lệ:**

```json
{
    "success": false,
    "message": "File không hợp lệ",
    "errors": {
        "file": [
            "File phải có định dạng xlsx hoặc xls",
            "Kích thước file không được vượt quá 10MB"
        ]
    }
}
```

**Response lỗi (422) - Thiếu header:**

```json
{
    "success": false,
    "message": "Thiếu cột bắt buộc: Mã SV",
    "required_headers": ["Mã SV", "Học kỳ", "Năm học", "Tiêu đề", "Nội dung"],
    "found_headers": [
        "Mã sinh viên",
        "Học kỳ",
        "Năm học",
        "Tiêu đề",
        "Nội dung"
    ]
}
```

**Response lỗi (422) - File trống:**

```json
{
    "success": false,
    "message": "File Excel không có dữ liệu hoặc thiếu header"
}
```

**Response lỗi (500):**

```json
{
    "success": false,
    "message": "Lỗi khi import file: [Chi tiết lỗi]"
}
```

**Quy tắc xử lý:**

1. **Validation dữ liệu:**

    - Mã SV phải tồn tại trong hệ thống
    - Sinh viên phải được phân vào lớp
    - Lớp phải có cố vấn học tập
    - Học kỳ và năm học phải khớp với dữ liệu trong DB

2. **Cảnh báo (Warnings):**

    - Tên sinh viên không khớp (hệ thống vẫn tạo cảnh cáo)
    - Lớp không khớp (hệ thống vẫn tạo cảnh cáo)
    - Cảnh cáo tương tự đã tồn tại (bỏ qua dòng này)

3. **Lỗi (Errors):**

    - Thiếu dữ liệu bắt buộc
    - Sinh viên không tồn tại
    - Sinh viên chưa được phân lớp
    - Lớp chưa có cố vấn
    - Không tìm thấy học kỳ

4. **Tự động gán:**
    - `advisor_id`: Lấy từ lớp của sinh viên
    - `advice`: Tự động tạo nếu để trống

**Lưu ý quan trọng:**

> [!IMPORTANT]
>
> -   Hệ thống xử lý từng dòng độc lập. Nếu một số dòng lỗi, các dòng khác vẫn được import thành công.
> -   Transaction được commit sau khi xử lý tất cả các dòng.
> -   Dòng trống (tất cả các cột quan trọng đều trống) sẽ bị bỏ qua.
> -   Header phải chính xác 100% (phân biệt chữ hoa/thường, dấu cách).

> [!TIP]
> Nên tải template bằng API #10 để đảm bảo cấu trúc file đúng.

---

## Các trường hợp lỗi chung

### 401 Unauthorized

```json
{
    "success": false,
    "message": "Unauthorized"
}
```

**Nguyên nhân:** Token không hợp lệ hoặc đã hết hạn

### 500 Internal Server Error

```json
{
    "success": false,
    "message": "Lỗi khi xử lý: [Chi tiết lỗi]"
}
```

**Nguyên nhân:** Lỗi hệ thống hoặc logic nghiệp vụ

---

## Ghi chú quan trọng

### Cách tính điểm

1. **GPA (Grade Point Average):** Điểm trung bình học kỳ

    - Tính theo công thức: Σ(Điểm môn × Số tín chỉ) / Tổng số tín chỉ
    - Có 2 thang: thang 10 và thang 4

2. **CPA (Cumulative Point Average):** Điểm trung bình tích lũy

    - Tính từ đầu khóa học đến học kỳ hiện tại
    - Dùng để xét cảnh cáo và xếp loại tốt nghiệp

3. **Điểm rèn luyện (Training Points):**

    - Tính theo từng học kỳ
    - Chỉ tính các hoạt động có `point_type = 'ren_luyen'`
    - Chỉ tính khi trạng thái = 'attended'

4. **Điểm CTXH (Social Points):**
    - Tích lũy từ đầu khóa học
    - Chỉ tính các hoạt động có `point_type = 'ctxh'`
    - Chỉ tính khi trạng thái = 'attended'

### Ngưỡng cảnh cáo theo năm học

| Năm học | Ngưỡng CPA (thang 4) |
| ------- | -------------------- |
| Năm 1   | 1.20                 |
| Năm 2   | 1.40                 |
| Năm 3   | 1.60                 |
| Năm 4+  | 1.80                 |

### Mức độ cảnh cáo

Dựa trên khoảng cách giữa CPA và ngưỡng:

-   **Mức 1 (Nhẹ):** Khoảng cách < 0.3
-   **Mức 2 (Vừa):** 0.3 <= Khoảng cách < 0.5
-   **Mức 3 (Nặng):** Khoảng cách >= 0.5

### Quy đổi điểm

| Thang 10 | Thang 4 | Điểm chữ | Xếp loại |
| -------- | ------- | -------- | -------- |
| >= 8.5   | 4.0     | A        | Giỏi     |
| >= 8.0   | 3.5     | B+       | Khá      |
| >= 7.0   | 3.0     | B        | Khá      |
| >= 6.5   | 2.5     | C+       | TB       |
| >= 5.5   | 2.0     | C        | TB       |
| >= 5.0   | 1.5     | D+       | TB yếu   |

                    "row": 5,
                    "user_code": "210002",
                    "error": "Sinh viên chưa được phân lớp"
                }
            ],
            "warnings": [
                {
                    "row": 4,
                    "user_code": "210003",
                    "warning": "Tên trong file (Nguyễn A) khác với tên trong hệ thống (Nguyễn Văn A)"
                }
            ]
        }
    }

}

````

**Response lỗi (403):**

```json
{
    "success": false,
    "message": "Chỉ admin mới có quyền import cảnh cáo học vụ"
}
````

**Response lỗi (422) - File không hợp lệ:**

```json
{
    "success": false,
    "message": "File không hợp lệ",
    "errors": {
        "file": [
            "File phải có định dạng xlsx hoặc xls",
            "Kích thước file không được vượt quá 10MB"
        ]
    }
}
```

**Response lỗi (422) - Thiếu header:**

```json
{
    "success": false,
    "message": "Thiếu cột bắt buộc: Mã SV",
    "required_headers": ["Mã SV", "Học kỳ", "Năm học", "Tiêu đề", "Nội dung"],
    "found_headers": [
        "Mã sinh viên",
        "Học kỳ",
        "Năm học",
        "Tiêu đề",
        "Nội dung"
    ]
}
```

**Response lỗi (422) - File trống:**

```json
{
    "success": false,
    "message": "File Excel không có dữ liệu hoặc thiếu header"
}
```

**Response lỗi (500):**

```json
{
    "success": false,
    "message": "Lỗi khi import file: [Chi tiết lỗi]"
}
```

**Quy tắc xử lý:**

1.  **Validation dữ liệu:**

    -   Mã SV phải tồn tại trong hệ thống
    -   Sinh viên phải được phân vào lớp
    -   Lớp phải có cố vấn học tập
    -   Học kỳ và năm học phải khớp với dữ liệu trong DB

2.  **Cảnh báo (Warnings):**

    -   Tên sinh viên không khớp (hệ thống vẫn tạo cảnh cáo)
    -   Lớp không khớp (hệ thống vẫn tạo cảnh cáo)
    -   Cảnh cáo tương tự đã tồn tại (bỏ qua dòng này)

3.  **Lỗi (Errors):**

    -   Thiếu dữ liệu bắt buộc
    -   Sinh viên không tồn tại
    -   Sinh viên chưa được phân lớp
    -   Lớp chưa có cố vấn
    -   Không tìm thấy học kỳ

4.  **Tự động gán:**
    -   `advisor_id`: Lấy từ lớp của sinh viên
    -   `advice`: Tự động tạo nếu để trống

**Lưu ý quan trọng:**

> [!IMPORTANT]
>
> -   Hệ thống xử lý từng dòng độc lập. Nếu một số dòng lỗi, các dòng khác vẫn được import thành công.
> -   Transaction được commit sau khi xử lý tất cả các dòng.
> -   Dòng trống (tất cả các cột quan trọng đều trống) sẽ bị bỏ qua.
> -   Header phải chính xác 100% (phân biệt chữ hoa/thường, dấu cách).

> [!TIP]
> Nên tải template bằng API #10 để đảm bảo cấu trúc file đúng.

---

## Các trường hợp lỗi chung

### 401 Unauthorized

```json
{
    "success": false,
    "message": "Unauthorized"
}
```

**Nguyên nhân:** Token không hợp lệ hoặc đã hết hạn

### 500 Internal Server Error

```json
{
    "success": false,
    "message": "Lỗi khi xử lý: [Chi tiết lỗi]"
}
```

**Nguyên nhân:** Lỗi hệ thống hoặc logic nghiệp vụ

---

## Ghi chú quan trọng

### Cách tính điểm

1.  **GPA (Grade Point Average):** Điểm trung bình học kỳ

    -   Tính theo công thức: Σ(Điểm môn × Số tín chỉ) / Tổng số tín chỉ
    -   Có 2 thang: thang 10 và thang 4

2.  **CPA (Cumulative Point Average):** Điểm trung bình tích lũy

    -   Tính từ đầu khóa học đến học kỳ hiện tại
    -   Dùng để xét cảnh cáo và xếp loại tốt nghiệp

3.  **Điểm rèn luyện (Training Points):**

    -   Tính theo từng học kỳ
    -   Chỉ tính các hoạt động có `point_type = 'ren_luyen'`
    -   Chỉ tính khi trạng thái = 'attended'

4.  **Điểm CTXH (Social Points):**
    -   Tích lũy từ đầu khóa học
    -   Chỉ tính các hoạt động có `point_type = 'ctxh'`
    -   Chỉ tính khi trạng thái = 'attended'

### Ngưỡng cảnh cáo theo năm học

| Năm học | Ngưỡng CPA (thang 4) |
| ------- | -------------------- |
| Năm 1   | 1.20                 |
| Năm 2   | 1.40                 |
| Năm 3   | 1.60                 |
| Năm 4+  | 1.80                 |

### Mức độ cảnh cáo

Dựa trên khoảng cách giữa CPA và ngưỡng:

-   **Mức 1 (Nhẹ):** Khoảng cách < 0.3
-   **Mức 2 (Vừa):** 0.3 <= Khoảng cách < 0.5
-   **Mức 3 (Nặng):** Khoảng cách >= 0.5

### Quy đổi điểm

| Thang 10 | Thang 4 | Điểm chữ | Xếp loại |
| -------- | ------- | -------- | -------- |
| >= 8.5   | 4.0     | A        | Giỏi     |
| >= 8.0   | 3.5     | B+       | Khá      |
| >= 7.0   | 3.0     | B        | Khá      |
| >= 6.5   | 2.5     | C+       | TB       |
| >= 5.5   | 2.0     | C        | TB       |
| >= 5.0   | 1.5     | D+       | TB yếu   |
| >= 4.0   | 1.0     | D        | TB yếu   |
| < 4.0    | 0.0     | F        | Kém      |

---

## Changelog

### Version 1.1.0 (November 2024)

-   Thêm 2 endpoints mới cho Admin:
    -   **API #10**: Download template import cảnh cáo học vụ
    -   **API #11**: Import cảnh cáo học vụ từ file Excel
-   Hỗ trợ import hàng loạt cảnh cáo học vụ với xử lý lỗi chi tiết
-   Template Excel với hướng dẫn sử dụng đầy đủ

### Version 1.0.0 (November 2024)

-   Khởi tạo tài liệu API đầy đủ cho Academic Monitoring Controller
-   Bao gồm 9 endpoints chính cho Student và Advisor
-   Thêm chi tiết về cách tính điểm và ngưỡng cảnh cáo
