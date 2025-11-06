-- =================================================================
-- LỆNH TẠO CSDL VÀ CHỌN CSDL
-- =================================================================
CREATE DATABASE db_advisorsystem
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE db_advisorsystem;

-- =================================================================
-- PHẦN 1: CẤU TRÚC LÕI (ĐÃ TÁCH BIỆT)
-- =================================================================
-- Bảng (1) Students
CREATE TABLE Students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Mã số (MSSV)',
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(15) NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    class_id INT NOT NULL COMMENT 'FK (sẽ thêm ở dưới)',
    status VARCHAR(50) NOT NULL DEFAULT 'studying'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (2) Advisors
CREATE TABLE Advisors (
    advisor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Mã số (Mã GV)',
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(15) NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    unit_id INT NULL COMMENT 'FK đến Units'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (3) Units
CREATE TABLE Units (
    unit_id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(150) NOT NULL UNIQUE,
    type ENUM('faculty', 'department') NOT NULL COMMENT 'faculty = Khoa, department = Phòng ban',
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (4) Classes
CREATE TABLE Classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL UNIQUE,
    advisor_id INT NULL COMMENT 'CVHT của lớp',
    faculty_id INT NULL COMMENT 'Khoa chủ quản',
    description TEXT NULL,
    FOREIGN KEY (faculty_id) REFERENCES Units(unit_id) ON DELETE SET NULL,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cập nhật khóa ngoại cho Students và Advisors
ALTER TABLE Students
ADD CONSTRAINT fk_student_class
FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE RESTRICT;

ALTER TABLE Advisors
ADD CONSTRAINT fk_advisor_unit
FOREIGN KEY (unit_id) REFERENCES Units(unit_id) ON DELETE SET NULL;

-- =================================================================
-- PHẦN 2: HỌC VỤ, HỌC KỲ VÀ ĐIỂM CHI TIẾT
-- =================================================================
-- Bảng (5) Semesters
CREATE TABLE Semesters (
    semester_id INT AUTO_INCREMENT PRIMARY KEY,
    semester_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    UNIQUE KEY uk_semester_year (semester_name, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (6) Courses
CREATE TABLE Courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(100) NOT NULL,
    credits TINYINT NOT NULL COMMENT 'Số tín chỉ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (7) Course_Grades
CREATE TABLE Course_Grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    semester_id INT NOT NULL,
    grade_value DECIMAL(4, 2) NULL,
    status ENUM('passed', 'failed', 'studying') NOT NULL DEFAULT 'studying',
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT,
    UNIQUE KEY uk_student_course_semester (student_id, course_id, semester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (8) Semester_Reports
CREATE TABLE Semester_Reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    semester_id INT NOT NULL,
    gpa DECIMAL(3, 2) NULL DEFAULT 0.00,
    credits_registered SMALLINT NOT NULL DEFAULT 0,
    credits_passed SMALLINT NOT NULL DEFAULT 0,
    training_point_summary INT NOT NULL DEFAULT 0,
    social_point_summary INT NOT NULL DEFAULT 0,
    outcome VARCHAR(255) NULL,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT,
    UNIQUE KEY uk_student_semester (student_id, semester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (9) Academic_Warnings
CREATE TABLE Academic_Warnings (
    warning_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    advisor_id INT NOT NULL,
    semester_id INT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    advice TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- PHẦN 3: KHIẾU NẠI ĐIỂM
-- =================================================================
-- Bảng (10) Point_Feedbacks
CREATE TABLE Point_Feedbacks (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    semester_id INT NOT NULL,
    feedback_content TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    advisor_response TEXT NULL,
    advisor_id INT NULL,
    response_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- PHẦN 4: HOẠT ĐỘNG VÀ ĐĂNG KÝ
-- =================================================================
-- Bảng (11) Activities
CREATE TABLE Activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    advisor_id INT NOT NULL,
    organizer_unit_id INT NULL,
    title VARCHAR(255) NOT NULL,
    general_description TEXT NULL,
    location VARCHAR(255) NULL,
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'upcoming',
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE RESTRICT,
    FOREIGN KEY (organizer_unit_id) REFERENCES Units(unit_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (12) Activity_Roles
CREATE TABLE Activity_Roles (
    activity_role_id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    requirements TEXT NULL,
    points_awarded INT NOT NULL DEFAULT 0,
    point_type ENUM('ctxh', 'ren_luyen') NOT NULL,
    max_slots INT NULL,
    FOREIGN KEY (activity_id) REFERENCES Activities(activity_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (13) Activity_Registrations
CREATE TABLE Activity_Registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    activity_role_id INT NOT NULL,
    student_id INT NOT NULL,
    registration_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL DEFAULT 'registered',
    FOREIGN KEY (activity_role_id) REFERENCES Activity_Roles(activity_role_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    UNIQUE KEY uk_role_student (activity_role_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (14) Cancellation_Requests
CREATE TABLE Cancellation_Requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES Activity_Registrations(registration_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- PHẦN 5: THÔNG BÁO VÀ PHẢN HỒI
-- =================================================================
-- Bảng (15) Notifications
CREATE TABLE Notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    advisor_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    link VARCHAR(2083) NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'general',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (16) Notification_Class
CREATE TABLE Notification_Class (
    notification_id INT NOT NULL,
    class_id INT NOT NULL,
    PRIMARY KEY (notification_id, class_id),
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (17) Notification_Attachments
CREATE TABLE Notification_Attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (18) Notification_Recipients
CREATE TABLE Notification_Recipients (
    recipient_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    student_id INT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    read_at DATETIME NULL,
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    UNIQUE KEY uk_notification_student (notification_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (19) Notification_Responses
CREATE TABLE Notification_Responses (
    response_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    student_id INT NOT NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'resolved') NOT NULL DEFAULT 'pending',
    advisor_response TEXT NULL,
    advisor_id INT NULL,
    response_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- PHẦN 6: HỌP LỚP VÀ BIÊN BẢN
-- =================================================================
-- Bảng (20) Meetings
CREATE TABLE Meetings (
    meeting_id INT AUTO_INCREMENT PRIMARY KEY,
    advisor_id INT NOT NULL,
    class_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    meeting_link VARCHAR(2083) NULL,
    location VARCHAR(255) NULL,
    meeting_time DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
    minutes_file_path VARCHAR(255) NULL,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE RESTRICT,
    FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (21) Meeting_Student
CREATE TABLE Meeting_Student (
    meeting_student_id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    student_id INT NOT NULL,
    attended BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (meeting_id) REFERENCES Meetings(meeting_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    UNIQUE KEY uk_meeting_student (meeting_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (22) Meeting_Feedbacks
CREATE TABLE Meeting_Feedbacks (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    student_id INT NOT NULL,
    feedback_content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES Meetings(meeting_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- PHẦN 7: ĐỐI THOẠI 1-1 (CHAT)
-- =================================================================
-- Bảng (23) Messages
CREATE TABLE Messages (
    message_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    advisor_id INT NOT NULL,
    sender_type ENUM('student', 'advisor') NOT NULL,
    content TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE CASCADE,
    KEY idx_conversation (student_id, advisor_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- PHẦN 8: THEO DÕI SINH VIÊN CÁ BIỆT
-- =================================================================
-- Bảng (24) Student_Monitoring_Notes
CREATE TABLE Student_Monitoring_Notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    advisor_id INT NOT NULL,
    semester_id INT NOT NULL,
    category ENUM('academic', 'personal', 'attendance', 'other') NOT NULL DEFAULT 'other',
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- DỮ LIỆU MẪU HOÀN CHỈNH CHO HỆ THỐNG CỐ VẤN HỌC TẬP
-- =================================================================

SET @default_hash = '$2y$10$XDVj1Dr7HJgAdrq8NNpzeurpieW7HRYNK53LcjVOMeGKIMNiib2ky';

-- =================================================================
-- 1. UNITS (Đơn vị: Khoa và Phòng ban)
-- =================================================================
INSERT INTO Units (unit_id, unit_name, type, description) VALUES
(1, 'Khoa Công nghệ Thông tin', 'faculty', 'Quản lý các ngành thuộc lĩnh vực CNTT'),
(2, 'Khoa Kinh tế', 'faculty', 'Quản lý các ngành thuộc lĩnh vực Kinh tế và Quản trị Kinh doanh'),
(3, 'Phòng Công tác Sinh viên', 'department', 'Quản lý các hoạt động ngoại khóa, điểm rèn luyện sinh viên'),
(4, 'Khoa Ngôn ngữ Anh', 'faculty', 'Quản lý các ngành thuộc lĩnh vực Ngôn ngữ học'),
(5, 'Phòng Tài chính - Kế toán', 'department', 'Quản lý các vấn đề học phí, học bổng'),
(6, 'Khoa Kỹ thuật', 'faculty', 'Quản lý các ngành kỹ thuật cơ khí, điện, xây dựng'),
(7, 'Phòng Đào tạo', 'department', 'Quản lý học vụ, thi cử, đăng ký môn học');

-- =================================================================
-- 2. ADVISORS (Cố vấn học tập / Giảng viên)
-- =================================================================
INSERT INTO Advisors (advisor_id, user_code, full_name, email, password_hash, phone_number, unit_id) VALUES
(1, 'GV001', 'ThS. Trần Văn An', 'gv.tvan@school.edu.vn', @default_hash, '0901112222', 1),
(2, 'GV002', 'TS. Nguyễn Thị Bích', 'gv.ntbich@school.edu.vn', @default_hash, '0902223333', 2),
(3, 'GV003', 'ThS. Lê Hoàng Cường', 'gv.lhcuong@school.edu.vn', @default_hash, '0903334444', 3),
(4, 'GV004', 'ThS. Đỗ Yến Nhi', 'gv.dynhi@school.edu.vn', @default_hash, '0904445555', 4),
(5, 'GV005', 'GS.TS. Phạm Minh Tuấn', 'gv.pmtuan@school.edu.vn', @default_hash, '0905556666', 1),
(6, 'GV006', 'TS. Võ Thị Lan Anh', 'gv.vtlanh@school.edu.vn', @default_hash, '0906667777', 2),
(7, 'GV007', 'ThS. Hoàng Văn Đức', 'gv.hvduc@school.edu.vn', @default_hash, '0907778888', 6),
(8, 'GV008', 'ThS. Trương Thị Mai', 'gv.ttmai@school.edu.vn', @default_hash, '0908889999', 7);

-- =================================================================
-- 3. CLASSES (Lớp học)
-- =================================================================
INSERT INTO Classes (class_id, class_name, advisor_id, faculty_id, description) VALUES
(1, 'DH21CNTT01', 1, 1, 'Lớp Đại học 2021 ngành Công nghệ Thông tin - Lớp 1'),
(2, 'DH21CNTT02', 5, 1, 'Lớp Đại học 2021 ngành Công nghệ Thông tin - Lớp 2'),
(3, 'DH22KT01', 2, 2, 'Lớp Đại học 2022 ngành Kinh tế'),
(4, 'DH22QTKD01', 6, 2, 'Lớp Đại học 2022 ngành Quản trị Kinh doanh'),
(5, 'DH21NNA01', 4, 4, 'Lớp Đại học 2021 ngành Ngôn ngữ Anh'),
(6, 'DH23KTCO01', 7, 6, 'Lớp Đại học 2023 ngành Kỹ thuật Cơ khí');

-- =================================================================
-- 4. STUDENTS (Sinh viên)
-- =================================================================
INSERT INTO Students (student_id, user_code, full_name, email, password_hash, phone_number, class_id, status) VALUES
-- Lớp DH21CNTT01 (CVHT: GV001 - Trần Văn An)
(1, '210001', 'Nguyễn Văn Hùng', 'sv.hung210001@school.edu.vn', @default_hash, '0911223344', 1, 'studying'),
(2, '210002', 'Trần Thị Thu Cẩm', 'sv.cam210002@school.edu.vn', @default_hash, '0912345678', 1, 'studying'),
(3, '210003', 'Phan Thanh Bình', 'sv.binh210003@school.edu.vn', @default_hash, '0944556677', 1, 'studying'),
(4, '210004', 'Võ Thị Kim Anh', 'sv.anh210004@school.edu.vn', @default_hash, '0955667788', 1, 'studying'),
(5, '210005', 'Lý Hoàng Nam', 'sv.nam210005@school.edu.vn', @default_hash, '0966778899', 1, 'studying'),

-- Lớp DH21CNTT02 (CVHT: GV005 - Phạm Minh Tuấn)
(6, '210006', 'Đặng Thị Hương', 'sv.huong210006@school.edu.vn', @default_hash, '0977889900', 2, 'studying'),
(7, '210007', 'Bùi Văn Tài', 'sv.tai210007@school.edu.vn', @default_hash, '0988990011', 2, 'studying'),
(8, '210008', 'Ngô Thị Lan', 'sv.lan210008@school.edu.vn', @default_hash, '0999001122', 2, 'studying'),

-- Lớp DH22KT01 (CVHT: GV002 - Nguyễn Thị Bích)
(9, '220001', 'Lê Văn Dũng', 'sv.dung220001@school.edu.vn', @default_hash, '0922334455', 3, 'studying'),
(10, '220002', 'Phạm Hoàng Yến', 'sv.yen220002@school.edu.vn', @default_hash, '0933445566', 3, 'studying'),
(11, '220003', 'Trịnh Minh Quân', 'sv.quan220003@school.edu.vn', @default_hash, '0944556688', 3, 'studying'),

-- Lớp DH22QTKD01 (CVHT: GV006 - Võ Thị Lan Anh)
(12, '220004', 'Huỳnh Thị Thu', 'sv.thu220004@school.edu.vn', @default_hash, '0955667799', 4, 'studying'),
(13, '220005', 'Nguyễn Văn Phúc', 'sv.phuc220005@school.edu.vn', @default_hash, '0966778800', 4, 'studying'),

-- Lớp DH21NNA01 (CVHT: GV004 - Đỗ Yến Nhi)
(14, '210009', 'Trịnh Bảo Quốc', 'sv.quoc210009@school.edu.vn', @default_hash, '0966778899', 5, 'studying'),
(15, '210010', 'Mai Lan Chi', 'sv.chi210010@school.edu.vn', @default_hash, '0977889900', 5, 'studying'),
(16, '210011', 'Phan Thị Hà', 'sv.ha210011@school.edu.vn', @default_hash, '0988990022', 5, 'studying'),

-- Lớp DH23KTCO01 (CVHT: GV007 - Hoàng Văn Đức)
(17, '230001', 'Lê Minh Khôi', 'sv.khoi230001@school.edu.vn', @default_hash, '0999001133', 6, 'studying'),
(18, '230002', 'Đỗ Văn Long', 'sv.long230002@school.edu.vn', @default_hash, '0900112244', 6, 'studying');

-- =================================================================
-- 5. SEMESTERS (Học kỳ)
-- =================================================================
INSERT INTO Semesters (semester_id, semester_name, academic_year, start_date, end_date) VALUES
(1, 'Học kỳ 1', '2023-2024', '2023-09-04', '2024-01-12'),
(2, 'Học kỳ 2', '2023-2024', '2024-02-05', '2024-06-28'),
(3, 'Học kỳ 1', '2024-2025', '2024-09-02', '2025-01-17'),
(4, 'Học kỳ 2', '2024-2025', '2025-02-10', '2025-06-30'),
(5, 'Học kỳ 1', '2025-2026', '2025-09-08', '2026-01-16');

-- =================================================================
-- 6. COURSES (Môn học)
-- =================================================================
INSERT INTO Courses (course_id, course_code, course_name, credits) VALUES
-- Môn CNTT
(1, 'IT001', 'Nhập môn Lập trình', 4),
(2, 'IT002', 'Cấu trúc dữ liệu và Giải thuật', 4),
(3, 'IT003', 'Lập trình Hướng đối tượng', 4),
(4, 'IT004', 'Cơ sở dữ liệu', 3),
(5, 'IT005', 'Mạng máy tính', 3),

-- Môn Kinh tế
(6, 'BA001', 'Kinh tế vi mô', 3),
(7, 'BA002', 'Kinh tế vĩ mô', 3),
(8, 'MK001', 'Nguyên lý Marketing', 3),
(9, 'MK002', 'Quản trị Kinh doanh', 3),
(10, 'AC001', 'Kế toán Tài chính', 3),

-- Môn Ngôn ngữ
(11, 'EN001', 'Nghe - Nói 1', 3),
(12, 'EN002', 'Đọc - Viết 1', 3),
(13, 'EN003', 'Nghe - Nói 2', 3),
(14, 'EN004', 'Đọc - Viết 2', 3),

-- Môn Kỹ thuật
(15, 'ME001', 'Vẽ kỹ thuật', 3),
(16, 'ME002', 'Cơ học kỹ thuật', 4);

-- =================================================================
-- 7. COURSE_GRADES (Điểm môn học)
-- =================================================================
INSERT INTO Course_Grades (student_id, course_id, semester_id, grade_value, status) VALUES
-- Sinh viên 1: Nguyễn Văn Hùng (210001) - HK1 2024-2025
(1, 1, 3, 8.5, 'passed'),
(1, 2, 3, 7.0, 'passed'),
(1, 3, 3, 8.0, 'passed'),
(1, 4, 3, 7.5, 'passed'),

-- Sinh viên 2: Trần Thị Thu Cẩm (210002) - HK1 2024-2025 (Học yếu)
(2, 1, 3, 4.0, 'failed'),
(2, 2, 3, 5.0, 'passed'),
(2, 3, 3, 4.5, 'failed'),
(2, 4, 3, 6.0, 'passed'),

-- Sinh viên 2: HK2 2024-2025 (Học lại)
(2, 1, 4, NULL, 'studying'),
(2, 3, 4, NULL, 'studying'),

-- Sinh viên 3: Phan Thanh Bình (210003) - Học tốt
(3, 1, 3, 9.0, 'passed'),
(3, 2, 3, 8.5, 'passed'),
(3, 3, 3, 9.0, 'passed'),
(3, 4, 3, 8.0, 'passed'),

-- Sinh viên 4: Võ Thị Kim Anh (210004)
(4, 1, 3, 7.0, 'passed'),
(4, 2, 3, 6.5, 'passed'),
(4, 3, 3, 7.5, 'passed'),
(4, 4, 3, 7.0, 'passed'),

-- Sinh viên 5: Lý Hoàng Nam (210005)
(5, 1, 3, 8.0, 'passed'),
(5, 2, 3, 7.5, 'passed'),
(5, 3, 3, 8.5, 'passed'),
(5, 4, 3, 8.0, 'passed'),

-- Sinh viên 6: Đặng Thị Hương (210006)
(6, 1, 3, 7.5, 'passed'),
(6, 2, 3, 7.0, 'passed'),

-- Sinh viên 7: Bùi Văn Tài (210007)
(7, 1, 3, 6.0, 'passed'),
(7, 2, 3, 5.5, 'passed'),

-- Sinh viên 8: Ngô Thị Lan (210008)
(8, 1, 3, 8.5, 'passed'),
(8, 2, 3, 8.0, 'passed'),

-- Sinh viên 9: Lê Văn Dũng (220001)
(9, 6, 3, 9.0, 'passed'),
(9, 7, 3, 8.0, 'passed'),
(9, 8, 3, 8.5, 'passed'),

-- Sinh viên 10: Phạm Hoàng Yến (220002)
(10, 6, 3, 7.0, 'passed'),
(10, 7, 3, 6.5, 'passed'),
(10, 8, 3, 7.0, 'passed'),

-- Sinh viên 11: Trịnh Minh Quân (220003)
(11, 6, 3, 8.0, 'passed'),
(11, 7, 3, 7.5, 'passed'),

-- Sinh viên 12: Huỳnh Thị Thu (220004)
(12, 8, 3, 8.5, 'passed'),
(12, 9, 3, 8.0, 'passed'),
(12, 10, 3, 7.5, 'passed'),

-- Sinh viên 13: Nguyễn Văn Phúc (220005)
(13, 8, 3, 7.0, 'passed'),
(13, 9, 3, 6.5, 'passed'),

-- Sinh viên 14: Trịnh Bảo Quốc (210009) - Học yếu
(14, 11, 3, 3.5, 'failed'),
(14, 12, 3, 5.0, 'passed'),
(14, 13, 3, 4.0, 'failed'),

-- Sinh viên 14: HK2 2024-2025 (Học lại)
(14, 11, 4, NULL, 'studying'),
(14, 13, 4, NULL, 'studying'),

-- Sinh viên 15: Mai Lan Chi (210010)
(15, 11, 3, 8.0, 'passed'),
(15, 12, 3, 7.5, 'passed'),
(15, 13, 3, 8.0, 'passed'),

-- Sinh viên 16: Phan Thị Hà (210011)
(16, 11, 3, 7.0, 'passed'),
(16, 12, 3, 7.5, 'passed'),

-- Sinh viên 17: Lê Minh Khôi (230001)
(17, 15, 3, 7.5, 'passed'),
(17, 16, 3, 7.0, 'passed'),

-- Sinh viên 18: Đỗ Văn Long (230002)
(18, 15, 3, 6.5, 'passed'),
(18, 16, 3, 6.0, 'passed');

-- =================================================================
-- 8. SEMESTER_REPORTS (Báo cáo điểm học kỳ)
-- =================================================================
INSERT INTO Semester_Reports (student_id, semester_id, gpa, credits_registered, credits_passed, training_point_summary, social_point_summary, outcome) VALUES
-- HK1 2024-2025
(1, 3, 7.75, 16, 16, 85, 15, 'Học tiếp (Khen thưởng - Khá)'),
(2, 3, 4.88, 16, 8, 70, 5, 'Cảnh cáo học vụ mức 1 (GPA < 5.0)'),
(3, 3, 8.63, 16, 16, 95, 25, 'Học tiếp (Khen thưởng - Giỏi)'),
(4, 3, 7.00, 16, 16, 75, 10, 'Học tiếp'),
(5, 3, 8.00, 16, 16, 80, 15, 'Học tiếp (Khen thưởng - Khá)'),
(6, 3, 7.25, 8, 8, 70, 5, 'Học tiếp'),
(7, 3, 5.75, 8, 8, 65, 0, 'Học tiếp'),
(8, 3, 8.25, 8, 8, 80, 10, 'Học tiếp (Khen thưởng - Khá)'),
(9, 3, 8.50, 9, 9, 90, 20, 'Học tiếp (Khen thưởng - Giỏi)'),
(10, 3, 6.83, 9, 9, 75, 10, 'Học tiếp'),
(11, 3, 7.75, 6, 6, 80, 5, 'Học tiếp'),
(12, 3, 8.00, 9, 9, 85, 15, 'Học tiếp (Khen thưởng - Khá)'),
(13, 3, 6.75, 6, 6, 70, 5, 'Học tiếp'),
(14, 3, 4.17, 9, 3, 60, 0, 'Cảnh cáo học vụ mức 1 (GPA < 5.0)'),
(15, 3, 7.83, 9, 9, 80, 10, 'Học tiếp'),
(16, 3, 7.25, 6, 6, 75, 5, 'Học tiếp'),
(17, 3, 7.25, 7, 7, 70, 5, 'Học tiếp'),
(18, 3, 6.25, 7, 7, 65, 0, 'Học tiếp'),

-- HK2 2024-2025 (Đang học)
(1, 4, 0.00, 12, 0, 85, 0, 'Đang học'),
(2, 4, 0.00, 8, 0, 70, 0, 'Đang học (Học lại)'),
(3, 4, 0.00, 12, 0, 95, 0, 'Đang học'),
(4, 4, 0.00, 12, 0, 75, 0, 'Đang học'),
(5, 4, 0.00, 12, 0, 80, 0, 'Đang học'),
(14, 4, 0.00, 6, 0, 60, 0, 'Đang học (Học lại)'),
(15, 4, 0.00, 9, 0, 80, 0, 'Đang học');

-- =================================================================
-- 9. ACADEMIC_WARNINGS (Cảnh cáo học vụ)
-- =================================================================
INSERT INTO Academic_Warnings (student_id, advisor_id, semester_id, title, content, advice, created_at) VALUES
(2, 1, 3, 'Quyết định Cảnh cáo học vụ HK1 năm học 2024-2025', 
'Sinh viên Trần Thị Thu Cẩm (MSSV: 210002) thuộc lớp DH21CNTT01 có kết quả học tập HK1/2024-2025 như sau:\n- GPA: 4.88/10\n- Số tín chỉ đăng ký: 16\n- Số tín chỉ đạt: 8\n- Các môn học rớt: IT001 (4.0), IT003 (4.5)\n\nTheo quy chế đào tạo, sinh viên bị cảnh cáo học vụ mức 1 do GPA < 5.0.',
'Yêu cầu sinh viên:\n1. Đăng ký học lại các môn IT001 và IT003 trong HK2/2024-2025\n2. Tham gia các buổi học phụ đạo do khoa tổ chức\n3. Gặp CVHT định kỳ 2 tuần/lần để báo cáo tình hình học tập\n4. Nếu tiếp tục có GPA < 5.0 trong HK2, sẽ bị xem xét buộc thôi học',
'2025-01-20 10:00:00'),

(14, 4, 3, 'Quyết định Cảnh cáo học vụ HK1 năm học 2024-2025',
'Sinh viên Trịnh Bảo Quốc (MSSV: 210009) thuộc lớp DH21NNA01 có kết quả học tập HK1/2024-2025 như sau:\n- GPA: 4.17/10\n- Số tín chỉ đăng ký: 9\n- Số tín chỉ đạt: 3\n- Các môn học rớt: EN001 (3.5), EN003 (4.0)\n\nTheo quy chế đào tạo, sinh viên bị cảnh cáo học vụ mức 1 do GPA < 5.0.',
'Yêu cầu sinh viên:\n1. Đăng ký học lại các môn EN001 và EN003 trong HK2/2024-2025\n2. Tham gia CLB tiếng Anh để cải thiện kỹ năng\n3. Gặp CVHT hàng tuần để theo dõi tiến độ học tập\n4. Cân nhắc giảm thời gian làm thêm để tập trung học tập',
'2025-01-21 14:00:00');

-- =================================================================
-- 10. POINT_FEEDBACKS (Khiếu nại điểm rèn luyện)
-- =================================================================
INSERT INTO Point_Feedbacks (student_id, semester_id, feedback_content, attachment_path, status, advisor_response, advisor_id, response_at, created_at) VALUES
(2, 3, 'Thưa thầy,\nEm là sinh viên Trần Thị Thu Cẩm (210002). Em đã tham gia hoạt động "Ngày hội Câu lạc bộ" do Phòng CTSV tổ chức vào ngày 15/12/2024 nhưng em thấy điểm rèn luyện chưa được cộng.\nEm xin gửi minh chứng đính kèm: giấy chứng nhận tham gia.\nEm mong thầy kiểm tra và cộng điểm giúp em ạ.\nEm cảm ơn thầy!',
'attachments/minhchung_cam_hk1_ngayhoi_clb.jpg',
'approved',
'Chào Cẩm,\nThầy đã kiểm tra minh chứng của em. Hoạt động này được tính 5 điểm Công tác xã hội. Thầy đã cập nhật vào hệ thống, em có thể kiểm tra lại điểm rèn luyện.\nChúc em học tập tốt!',
1,
'2025-03-05 10:30:00',
'2025-03-01 14:20:00'),

(9, 3, 'Kính gửi cô,\nEm là Lê Văn Dũng (220001). Em tham gia làm Tình nguyện viên cho hoạt động "Hiến máu nhân đạo" ngày 10/11/2024 do Phòng CTSV tổ chức, nhưng em không thấy điểm được cộng vào điểm rèn luyện HK1.\nEm có giữ giấy xác nhận từ BTC.\nEm nhờ cô kiểm tra giúp em ạ.',
NULL,
'pending',
NULL,
NULL,
NULL,
'2025-03-08 09:15:00'),

(15, 3, 'Thưa cô,\nEm là Mai Lan Chi (210010). Em thấy điểm rèn luyện của em trong HK1 là 80 điểm, nhưng em đã tham gia đầy đủ các hoạt động của lớp và khoa. Em nghĩ có thể có sai sót trong việc chấm điểm.\nEm mong cô xem xét lại giúp em.\nCảm ơn cô!',
NULL,
'rejected',
'Chào Chi,\nCô đã xem xét hồ sơ điểm rèn luyện của em. Điểm 80 là chính xác dựa trên các tiêu chí:\n- Học tập: 20 điểm\n- Ý thức tổ chức kỷ luật: 25 điểm\n- Hoạt động xã hội: 20 điểm\n- Hoạt động văn hóa, thể thao: 15 điểm\nEm có thể xem chi tiết tại phần "Chi tiết điểm rèn luyện" trên hệ thống.\nNếu còn thắc mắc, em có thể liên hệ trực tiếp với cô.',
4,
'2025-03-10 15:00:00',
'2025-03-06 11:00:00');

-- =================================================================
-- 11. ACTIVITIES (Hoạt động ngoại khóa)
-- =================================================================
INSERT INTO Activities (activity_id, advisor_id, organizer_unit_id, title, general_description, location, start_time, end_time, status) VALUES
(1, 3, 3, 'Hiến máu nhân đạo 2025', 
'Hoạt động hiến máu tình nguyện "Giọt hồng yêu thương" do Phòng Công tác Sinh viên phối hợp với Hội Chữ thập đỏ tổ chức. Kêu gọi sinh viên tham gia hiến máu cứu người.', 
'Sảnh A, Cơ sở chính', 
'2024-11-10 08:00:00', 
'2024-11-10 12:00:00', 
'completed'),

(2, 1, 1, 'Workshop: Giới thiệu về AI và Machine Learning', 
'Workshop chuyên đề về Trí tuệ nhân tạo dành cho sinh viên Khoa CNTT, có diễn giả từ các công ty công nghệ hàng đầu.', 
'Phòng Hội thảo H.201', 
'2024-12-05 14:00:00', 
'2024-12-05 17:00:00', 
'completed'),

(3, 2, 2, 'Cuộc thi Ý tưởng Khởi nghiệp 2025', 
'Cuộc thi khởi nghiệp dành cho sinh viên Khoa Kinh tế, cơ hội để các bạn trẻ trình bày ý tưởng kinh doanh và nhận được tài trợ từ các nhà đầu tư.', 
'Hội trường B', 
'2025-04-10 08:00:00', 
'2025-04-10 17:00:00', 
'upcoming'),

(4, 4, 4, 'CLB Tiếng Anh: Vòng Bán kết English Contest', 
'Cuộc thi tiếng Anh thường niên của Khoa Ngôn ngữ Anh, vòng Bán kết với các thí sinh xuất sắc từ vòng loại.', 
'Phòng C.101', 
'2025-03-25 18:00:00', 
'2025-03-25 21:00:00', 
'completed'),

(5, 3, 3, 'Ngày hội Câu lạc bộ 2024', 
'Ngày hội giới thiệu các Câu lạc bộ sinh viên, hoạt động ngoại khóa, tạo sân chơi lành mạnh cho sinh viên năm nhất làm quen với môi trường đại học.', 
'Sân vận động trường', 
'2024-12-15 08:00:00', 
'2024-12-15 16:00:00', 
'completed'),

(6, 5, 1, 'Hội thảo: Xu hướng Công nghệ 2025', 
'Hội thảo về các xu hướng công nghệ mới như Blockchain, IoT, Cloud Computing cho sinh viên CNTT.', 
'Hội trường A', 
'2025-03-15 09:00:00', 
'2025-03-15 12:00:00', 
'completed'),

(7, 6, 2, 'Giao lưu Doanh nghiệp - Sinh viên', 
'Chương trình kết nối sinh viên Khoa Kinh tế với các doanh nghiệp, tìm hiểu cơ hội thực tập và việc làm.', 
'Phòng B.301', 
'2025-04-20 14:00:00', 
'2025-04-20 17:00:00', 
'upcoming'),

(8, 3, 3, 'Tình nguyện Mùa hè Xanh 2025', 
'Chiến dịch tình nguyện hè về vùng nông thôn, miền núi hỗ trợ dạy học, khám bệnh, tu sửa nhà cho người nghèo.', 
'Các tỉnh miền núi phía Bắc', 
'2025-07-01 07:00:00', 
'2025-07-15 18:00:00', 
'upcoming');

-- =================================================================
-- 12. ACTIVITY_ROLES (Vai trò trong hoạt động)
-- =================================================================
INSERT INTO Activity_Roles (activity_role_id, activity_id, role_name, description, requirements, points_awarded, point_type, max_slots) VALUES
-- Hoạt động 1: Hiến máu
(1, 1, 'Người hiến máu', 'Tham gia hiến máu tình nguyện', 'Đủ sức khỏe, cân nặng >= 45kg', 10, 'ctxh', 100),
(2, 1, 'Tình nguyện viên hỗ trợ', 'Hỗ trợ đăng ký, hướng dẫn người hiến máu', 'Nhiệt tình, có trách nhiệm', 15, 'ctxh', 20),
(3, 1, 'Ban tổ chức', 'Tham gia Ban tổ chức, điều phối hoạt động', 'Có kinh nghiệm tổ chức sự kiện', 20, 'ren_luyen', 10),

-- Hoạt động 2: Workshop AI
(4, 2, 'Người tham dự', 'Tham dự Workshop và hoàn thành bài kiểm tra', 'Sinh viên Khoa CNTT', 10, 'ren_luyen', 100),
(5, 2, 'Trợ giảng', 'Hỗ trợ giảng viên, chuẩn bị thiết bị', 'Có kiến thức cơ bản về AI', 15, 'ren_luyen', 5),

-- Hoạt động 3: Cuộc thi Khởi nghiệp
(6, 3, 'Đội thi Vòng Chung kết', 'Tham gia thi với ý tưởng khởi nghiệp', 'Có ý tưởng kinh doanh khả thi', 25, 'ren_luyen', 20),
(7, 3, 'Người tham dự', 'Tham dự và học hỏi từ các ý tưởng', 'Sinh viên Khoa Kinh tế', 5, 'ren_luyen', 150),

-- Hoạt động 4: English Contest
(8, 4, 'Thí sinh Bán kết', 'Thi Bán kết English Contest', 'Vượt qua vòng loại', 20, 'ren_luyen', 15),
(9, 4, 'Khán giả cổ vũ', 'Tham dự cổ vũ các thí sinh', 'Sinh viên Khoa NNA', 5, 'ren_luyen', 100),

-- Hoạt động 5: Ngày hội CLB
(10, 5, 'Người tham gia', 'Tham gia Ngày hội, tìm hiểu các CLB', 'Tất cả sinh viên', 5, 'ctxh', 500),
(11, 5, 'Đại diện CLB', 'Giới thiệu CLB, tuyển thành viên mới', 'Thành viên CLB', 15, 'ren_luyen', 50),

-- Hoạt động 6: Hội thảo Công nghệ
(12, 6, 'Người tham dự', 'Tham dự hội thảo', 'Sinh viên Khoa CNTT', 8, 'ren_luyen', 200),

-- Hoạt động 7: Giao lưu Doanh nghiệp
(13, 7, 'Người tham dự', 'Tham dự giao lưu với doanh nghiệp', 'Sinh viên Khoa Kinh tế, QTKD', 10, 'ren_luyen', 100),

-- Hoạt động 8: Mùa hè Xanh
(14, 8, 'Tình nguyện viên', 'Tham gia chiến dịch tình nguyện hè', 'Đăng ký trước, khỏe mạnh', 50, 'ctxh', 50),
(15, 8, 'Trưởng nhóm', 'Điều phối nhóm tình nguyện', 'Có kinh nghiệm, kỹ năng lãnh đạo', 80, 'ctxh', 5);

-- =================================================================
-- 13. ACTIVITY_REGISTRATIONS (Đăng ký tham gia hoạt động)
-- =================================================================
INSERT INTO Activity_Registrations (registration_id, activity_role_id, student_id, registration_time, status) VALUES
-- Hoạt động 1: Hiến máu (Đã hoàn thành)
(1, 1, 1, '2024-11-05 10:00:00', 'attended'),
(2, 1, 9, '2024-11-06 09:00:00', 'attended'),
(3, 2, 3, '2024-11-05 11:00:00', 'attended'),
(4, 2, 15, '2024-11-06 10:00:00', 'attended'),
(5, 3, 5, '2024-11-04 14:00:00', 'attended'),

-- Hoạt động 2: Workshop AI (Đã hoàn thành)
(6, 4, 1, '2024-12-01 08:00:00', 'attended'),
(7, 4, 2, '2024-12-01 09:00:00', 'cancelled'),
(8, 4, 3, '2024-12-01 08:30:00', 'attended'),
(9, 4, 4, '2024-12-02 10:00:00', 'attended'),
(10, 4, 6, '2024-12-02 11:00:00', 'attended'),
(11, 4, 8, '2024-12-03 09:00:00', 'attended'),
(12, 5, 5, '2024-11-28 14:00:00', 'attended'),

-- Hoạt động 3: Cuộc thi Khởi nghiệp (Sắp diễn ra)
(13, 6, 9, '2025-03-15 10:00:00', 'registered'),
(14, 6, 10, '2025-03-15 11:00:00', 'registered'),
(15, 6, 12, '2025-03-16 09:00:00', 'registered'),
(16, 7, 11, '2025-03-20 10:00:00', 'registered'),
(17, 7, 13, '2025-03-20 11:00:00', 'registered'),

-- Hoạt động 4: English Contest (Đã hoàn thành)
(18, 8, 14, '2025-03-10 08:00:00', 'attended'),
(19, 8, 15, '2025-03-10 09:00:00', 'attended'),
(20, 9, 16, '2025-03-20 10:00:00', 'attended'),
(21, 9, 7, '2025-03-20 11:00:00', 'attended'),
(22, 9, 8, '2025-03-21 09:00:00', 'attended'),

-- Hoạt động 5: Ngày hội CLB (Đã hoàn thành)
(23, 10, 2, '2024-12-10 08:00:00', 'attended'),
(24, 10, 4, '2024-12-10 09:00:00', 'attended'),
(25, 10, 6, '2024-12-11 08:00:00', 'attended'),
(26, 11, 1, '2024-12-08 10:00:00', 'attended'),
(27, 11, 3, '2024-12-08 11:00:00', 'attended'),

-- Hoạt động 6: Hội thảo Công nghệ (Đã hoàn thành)
(28, 12, 1, '2025-03-10 10:00:00', 'attended'),
(29, 12, 3, '2025-03-10 11:00:00', 'attended'),
(30, 12, 5, '2025-03-11 09:00:00', 'attended'),
(31, 12, 6, '2025-03-11 10:00:00', 'attended'),
(32, 12, 7, '2025-03-12 09:00:00', 'attended'),

-- Hoạt động 7: Giao lưu DN (Sắp diễn ra)
(33, 13, 9, '2025-04-10 10:00:00', 'registered'),
(34, 13, 10, '2025-04-10 11:00:00', 'registered'),
(35, 13, 12, '2025-04-11 09:00:00', 'registered'),

-- Hoạt động 8: Mùa hè Xanh (Sắp diễn ra)
(36, 14, 3, '2025-06-01 10:00:00', 'registered'),
(37, 14, 5, '2025-06-01 11:00:00', 'registered'),
(38, 14, 9, '2025-06-02 09:00:00', 'registered'),
(39, 15, 1, '2025-05-28 14:00:00', 'registered');

-- =================================================================
-- 14. CANCELLATION_REQUESTS (Yêu cầu hủy đăng ký)
-- =================================================================
INSERT INTO Cancellation_Requests (request_id, registration_id, reason, status, requested_at) VALUES
(1, 7, 'Em bị trùng lịch thi giữa kỳ môn IT002. Em xin phép hủy tham gia Workshop. Em xin lỗi ạ!', 
'approved', 
'2024-12-04 10:00:00'),

(2, 16, 'Em có việc gia đình đột xuất, không thể tham dự được. Em xin phép hủy đăng ký ạ.', 
'pending', 
'2025-04-08 14:30:00');

-- =================================================================
-- 15. NOTIFICATIONS (Thông báo)
-- =================================================================
INSERT INTO Notifications (notification_id, advisor_id, title, summary, link, type, created_at) VALUES
(1, 1, 'Thông báo Họp lớp DH21CNTT01 tháng 3/2025', 
'Lớp DH21CNTT01 tổ chức họp lớp vào ngày 15/03/2025 tại phòng B.101. Nội dung: Triển khai công tác chuẩn bị cho HK2/2024-2025, đánh giá kết quả HK1, thông báo các hoạt động sắp tới.', 
NULL, 
'general', 
'2025-03-08 09:00:00'),

(2, 2, 'Thông báo: Quy định về đăng ký môn học HK Hè 2025', 
'Nhà trường thông báo quy định mới về đăng ký môn học HK Hè 2025. Sinh viên cần lưu ý các mốc thời gian quan trọng và điều kiện đăng ký. Xem file đính kèm để biết chi tiết.', 
'https://daotao.school.edu.vn/dkmh-he-2025', 
'academic', 
'2025-03-10 08:00:00'),

(3, 1, 'Thông báo khẩn: Cập nhật quy chế thi cử HK2/2024-2025', 
'Nhà trường ban hành quy chế thi cử mới cho HK2/2024-2025. Tất cả sinh viên phải đọc kỹ và tuân thủ nghiêm túc. Vi phạm quy chế sẽ bị xử lý kỷ luật nghiêm khắc.', 
NULL, 
'academic', 
'2025-03-12 11:00:00'),

(4, 4, 'Thông báo Họp lớp DH21NNA01 tháng 3/2025', 
'Lớp DH21NNA01 tổ chức họp lớp lần 2 trong năm học. Nội dung: Đánh giá kết quả học tập HK1, kế hoạch HK2, thông báo về English Contest.', 
NULL, 
'general', 
'2025-03-12 14:00:00'),

(5, 5, 'Thông báo: Workshop về Blockchain Technology', 
'Khoa CNTT tổ chức Workshop về công nghệ Blockchain vào cuối tháng 4. Sinh viên quan tâm có thể đăng ký tham gia. Số lượng có hạn.', 
'https://cntt.school.edu.vn/workshop-blockchain', 
'general', 
'2025-03-18 10:00:00'),

(6, 3, 'Thông báo: Chiến dịch Tình nguyện Mùa hè Xanh 2025', 
'Phòng CTSV phát động chiến dịch Tình nguyện Mùa hè Xanh 2025. Đăng ký trước ngày 15/06/2025. Cơ hội tốt để rèn luyện kỹ năng và đóng góp cho cộng đồng.', 
NULL, 
'general', 
'2025-03-20 09:00:00'),

(7, 6, 'Thông báo: Học bổng Khuyến khích học tập HK1/2024-2025', 
'Danh sách sinh viên đạt học bổng Khuyến khích học tập HK1/2024-2025 đã được công bố. Sinh viên kiểm tra trên hệ thống và làm thủ tục nhận học bổng.', 
'https://taichinh.school.edu.vn/hocbong-hk1-2024', 
'general', 
'2025-03-22 08:00:00');

-- =================================================================
-- 16. NOTIFICATION_CLASS (Thông báo gửi đến lớp nào)
-- =================================================================
INSERT INTO Notification_Class (notification_id, class_id) VALUES
-- TB1: Họp lớp DH21CNTT01
(1, 1),

-- TB2: Đăng ký môn HK Hè - Gửi cho tất cả lớp năm 2 trở lên
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5),

-- TB3: Quy chế thi cử - Gửi lớp DH21CNTT01
(3, 1),

-- TB4: Họp lớp DH21NNA01
(4, 5),

-- TB5: Workshop Blockchain - Gửi các lớp CNTT
(5, 1), (5, 2),

-- TB6: Mùa hè Xanh - Gửi tất cả các lớp
(6, 1), (6, 2), (6, 3), (6, 4), (6, 5), (6, 6),

-- TB7: Học bổng - Gửi các lớp có SV đạt học bổng
(7, 1), (7, 2), (7, 3), (7, 4), (7, 5);

-- =================================================================
-- 17. NOTIFICATION_ATTACHMENTS (File đính kèm thông báo)
-- =================================================================
INSERT INTO Notification_Attachments (notification_id, file_path, file_name) VALUES
(2, 'attachments/quydinh_dkmh_he_2025.pdf', 'QuyDinh_DKMH_He_2025.pdf'),
(3, 'attachments/QuyCheThiCuMoi_HK2_2025.pdf', 'QuyCheThiCuMoi_HK2_2025.pdf'),
(7, 'attachments/DanhSach_HocBong_HK1_2024.xlsx', 'DanhSach_HocBong_HK1_2024.xlsx');

-- =================================================================
-- 18. NOTIFICATION_RECIPIENTS (Người nhận thông báo)
-- =================================================================
INSERT INTO Notification_Recipients (notification_id, student_id, is_read, read_at) VALUES
-- TB1: Họp lớp DH21CNTT01 (gửi cho SV 1,2,3,4,5)
(1, 1, 1, '2025-03-08 10:00:00'),
(1, 2, 0, NULL),
(1, 3, 1, '2025-03-08 11:30:00'),
(1, 4, 1, '2025-03-09 09:00:00'),
(1, 5, 0, NULL),

-- TB2: Đăng ký HK Hè (gửi cho SV các lớp 1,2,3,4,5)
(2, 1, 1, '2025-03-10 14:00:00'),
(2, 2, 1, '2025-03-10 15:00:00'),
(2, 3, 1, '2025-03-11 09:00:00'),
(2, 4, 0, NULL),
(2, 5, 1, '2025-03-11 10:00:00'),
(2, 6, 0, NULL),
(2, 7, 0, NULL),
(2, 8, 1, '2025-03-12 08:00:00'),
(2, 9, 1, '2025-03-11 11:00:00'),
(2, 10, 0, NULL),
(2, 11, 1, '2025-03-12 09:00:00'),
(2, 12, 1, '2025-03-11 14:00:00'),
(2, 13, 0, NULL),
(2, 14, 1, '2025-03-10 16:00:00'),
(2, 15, 1, '2025-03-11 08:00:00'),
(2, 16, 0, NULL),

-- TB3: Quy chế thi cử (gửi cho SV lớp 1)
(3, 1, 1, '2025-03-12 13:00:00'),
(3, 2, 1, '2025-03-12 14:00:00'),
(3, 3, 1, '2025-03-13 09:00:00'),
(3, 4, 0, NULL),
(3, 5, 1, '2025-03-13 10:00:00'),

-- TB4: Họp lớp DH21NNA01 (gửi cho SV 14,15,16)
(4, 14, 1, '2025-03-12 15:00:00'),
(4, 15, 1, '2025-03-13 08:00:00'),
(4, 16, 0, NULL),

-- TB5: Workshop Blockchain (gửi cho SV lớp 1,2)
(5, 1, 1, '2025-03-18 11:00:00'),
(5, 2, 0, NULL),
(5, 3, 1, '2025-03-18 14:00:00'),
(5, 4, 0, NULL),
(5, 5, 1, '2025-03-19 09:00:00'),
(5, 6, 1, '2025-03-18 15:00:00'),
(5, 7, 0, NULL),
(5, 8, 1, '2025-03-19 10:00:00'),

-- TB6: Mùa hè Xanh (gửi tất cả)
(6, 1, 1, '2025-03-20 10:00:00'),
(6, 2, 0, NULL),
(6, 3, 1, '2025-03-20 11:00:00'),
(6, 4, 0, NULL),
(6, 5, 1, '2025-03-20 14:00:00'),
(6, 6, 0, NULL),
(6, 7, 0, NULL),
(6, 8, 1, '2025-03-21 09:00:00'),
(6, 9, 1, '2025-03-20 15:00:00'),
(6, 10, 0, NULL),
(6, 11, 0, NULL),
(6, 12, 1, '2025-03-21 10:00:00'),
(6, 13, 0, NULL),
(6, 14, 1, '2025-03-21 11:00:00'),
(6, 15, 1, '2025-03-20 16:00:00'),
(6, 16, 0, NULL),
(6, 17, 0, NULL),
(6, 18, 0, NULL),

-- TB7: Học bổng (gửi các SV có GPA cao)
(7, 1, 1, '2025-03-22 09:00:00'),
(7, 3, 1, '2025-03-22 10:00:00'),
(7, 5, 1, '2025-03-22 11:00:00'),
(7, 8, 1, '2025-03-22 14:00:00'),
(7, 9, 1, '2025-03-22 15:00:00'),
(7, 12, 1, '2025-03-23 09:00:00'),
(7, 15, 1, '2025-03-22 16:00:00');

-- =================================================================
-- 19. NOTIFICATION_RESPONSES (Phản hồi thông báo)
-- =================================================================
INSERT INTO Notification_Responses (response_id, notification_id, student_id, content, status, advisor_response, advisor_id, response_at, created_at) VALUES
(1, 1, 1, 'Dạ em đã nhận thông báo. Em muốn hỏi là buổi họp có bắt buộc tham gia không ạ? Em có việc gia đình vào ngày hôm đó.', 
'resolved', 
'Chào Hùng,\nBuổi họp này rất quan trọng vì sẽ có nhiều thông tin về HK2. Nếu em có việc bận, em có thể xin phép trước và thầy sẽ gửi biên bản họp cho em sau. Nhưng thầy khuyến khích em sắp xếp để tham gia.\nThầy An', 
1, 
'2025-03-09 10:15:00', 
'2025-03-08 16:00:00'),

(2, 4, 14, 'Dạ em cảm ơn cô đã thông báo. Em sẽ tham gia đầy đủ ạ.', 
'resolved', 
NULL, 
NULL, 
NULL, 
'2025-03-13 09:00:00'),

(3, 2, 2, 'Thưa thầy/cô,\nEm bị cảnh cáo học vụ HK1, em có được đăng ký môn học HK Hè không ạ? Em đang lo lắng lắm.', 
'resolved', 
'Chào Cẩm,\nEm hoàn toàn có thể đăng ký môn học HK Hè. Thực tế, thầy khuyến khích em đăng ký để cải thiện kết quả học tập. Em nên tập trung học lại các môn đã rớt trong HK Hè này.\nNếu có thắc mắc gì, em liên hệ trực tiếp với thầy nhé.\nThầy An', 
1, 
'2025-03-11 09:30:00', 
'2025-03-10 18:00:00'),

(4, 6, 3, 'Thưa thầy,\nEm rất muốn tham gia chiến dịch Mùa hè Xanh. Em có thể đăng ký vai trò Trưởng nhóm được không ạ? Em đã có kinh nghiệm tổ chức hoạt động tình nguyện từ trước.', 
'pending', 
NULL, 
NULL, 
NULL, 
'2025-03-21 10:00:00');

-- =================================================================
-- 20. MEETINGS (Cuộc họp lớp)
-- =================================================================
INSERT INTO Meetings (meeting_id, advisor_id, class_id, title, summary, meeting_link, location, meeting_time, status, minutes_file_path) VALUES
(1, 1, 1, 'Họp lớp DH21CNTT01 tháng 3/2025', 
'Triển khai công tác chuẩn bị cho HK2/2024-2025. Đánh giá kết quả học tập HK1. Thông báo các hoạt động sắp tới của lớp và khoa.', 
NULL, 
'Phòng B.101', 
'2025-03-15 10:00:00', 
'completed', 
'meetings/bienban_hop_lop_dh21cntt01_t3_2025.pdf'),

(2, 4, 5, 'Họp lớp DH21NNA01 tháng 3/2025', 
'Đánh giá kết quả học tập HK1. Kế hoạch HK2. Thông báo về English Contest và các hoạt động ngoại khóa.', 
NULL, 
'Phòng C.202', 
'2025-03-18 14:00:00', 
'completed', 
'meetings/bienban_hop_lop_dh21nna01_t3_2025.pdf'),

(3, 2, 3, 'Họp lớp DH22KT01 đầu HK2', 
'Họp lớp đầu HK2/2024-2025. Thông báo kế hoạch học tập, cuộc thi Khởi nghiệp, cơ hội thực tập tại doanh nghiệp.', 
'https://meet.google.com/abc-defg-hij', 
'Online qua Google Meet', 
'2025-02-15 15:00:00', 
'completed', 
'meetings/bienban_hop_lop_dh22kt01_t2_2025.pdf'),

(4, 5, 2, 'Họp lớp DH21CNTT02 tháng 4/2025', 
'Chuẩn bị cho kỳ thi cuối HK2. Thông báo về đăng ký môn học năm 4. Trao đổi về vấn đề việc làm sau tốt nghiệp.', 
NULL, 
'Phòng B.205', 
'2025-04-10 10:00:00', 
'scheduled', 
NULL),

(5, 6, 4, 'Họp lớp DH22QTKD01 - Giao lưu Alumni', 
'Gặp gỡ và giao lưu với các cựu sinh viên đã tốt nghiệp, đang làm việc tại các doanh nghiệp. Chia sẻ kinh nghiệm học tập và tìm việc.', 
NULL, 
'Phòng B.301', 
'2025-04-15 14:00:00', 
'scheduled', 
NULL);

-- =================================================================
-- 21. MEETING_STUDENT (Sinh viên tham dự họp)
-- =================================================================
INSERT INTO Meeting_Student (meeting_student_id, meeting_id, student_id, attended) VALUES
-- Cuộc họp 1: DH21CNTT01
(1, 1, 1, 1),
(2, 1, 2, 0),
(3, 1, 3, 1),
(4, 1, 4, 1),
(5, 1, 5, 1),

-- Cuộc họp 2: DH21NNA01
(6, 2, 14, 1),
(7, 2, 15, 1),
(8, 2, 16, 0),

-- Cuộc họp 3: DH22KT01
(9, 3, 9, 1),
(10, 3, 10, 1),
(11, 3, 11, 0),

-- Cuộc họp 4: DH21CNTT02 (chưa diễn ra)
(12, 4, 6, 0),
(13, 4, 7, 0),
(14, 4, 8, 0),

-- Cuộc họp 5: DH22QTKD01 (chưa diễn ra)
(15, 5, 12, 0),
(16, 5, 13, 0);

-- =================================================================
-- 22. MEETING_FEEDBACKS (Phản hồi về cuộc họp)
-- =================================================================
INSERT INTO Meeting_Feedbacks (meeting_id, student_id, feedback_content, created_at) VALUES
(1, 3, 'Thưa thầy,\nEm thấy biên bản họp ghi thiếu phần ý kiến của em về quỹ lớp. Em có đề xuất về việc sử dụng quỹ lớp để tổ chức các hoạt động gắn kết nhưng không thấy ghi trong biên bản ạ.\nEm mong thầy bổ sung giúp em.', 
'2025-03-16 08:30:00'),

(2, 15, 'Dạ em cảm ơn cô đã tổ chức buổi họp. Tuy nhiên em thấy thời gian hơi ngắn, chưa có đủ thời gian để thảo luận về các vấn đề quan trọng. Em đề xuất buổi họp sau nên kéo dài hơn một chút ạ.', 
'2025-03-19 10:00:00'),

(3, 9, 'Thưa cô,\nEm rất hứng thú với thông tin về cuộc thi Khởi nghiệp. Em muốn hỏi thêm về tiêu chí đánh giá và giải thưởng. Cô có thể chia sẻ thêm thông tin không ạ?', 
'2025-02-16 09:00:00');

-- =================================================================
-- 23. MESSAGES (Tin nhắn giữa sinh viên và CVHT)
-- =================================================================
INSERT INTO Messages (message_id, student_id, advisor_id, sender_type, content, attachment_path, is_read, sent_at) VALUES
-- Cuộc trò chuyện 1: Sinh viên 2 (Cẩm) và CVHT (GV001 - Thầy An)
(1, 2, 1, 'student', 'Thầy ơi, em bị cảnh cáo học vụ HK1, giờ em phải làm sao ạ? Em rất lo lắng.', NULL, 1, '2025-03-11 09:00:00'),
(2, 2, 1, 'advisor', 'Chào Cẩm,\nThầy hiểu em đang lo lắng. Điều quan trọng bây giờ là em cần đăng ký học lại ngay các môn IT001 và IT003 trong HK2 này. Thầy sẽ hỗ trợ em trong quá trình học tập.', NULL, 1, '2025-03-11 09:05:00'),
(3, 2, 1, 'student', 'Dạ em đã đăng ký học lại rồi ạ. Em muốn hỏi thầy, nếu em học lại mà vẫn không qua thì sao ạ?', NULL, 1, '2025-03-11 09:10:00'),
(4, 2, 1, 'advisor', 'Em cần cố gắng hết sức. Nếu cần, em có thể tham gia các lớp học phụ đạo mà khoa tổ chức. Thầy cũng sẽ sắp xếp thời gian để hỗ trợ em nếu em gặp khó khăn trong việc học.', NULL, 1, '2025-03-11 09:15:00'),
(5, 2, 1, 'student', 'Dạ em cảm ơn thầy nhiều ạ. Em sẽ cố gắng hết sức!', NULL, 0, '2025-03-11 09:20:00'),

-- Cuộc trò chuyện 2: Sinh viên 9 (Dũng) và CVHT (GV002 - Cô Bích)
(6, 9, 2, 'student', 'Cô ơi, em muốn hỏi về cuộc thi Ý tưởng Khởi nghiệp. Em có ý tưởng về một ứng dụng quản lý chi tiêu cá nhân, không biết có phù hợp không ạ?', NULL, 1, '2025-03-12 16:00:00'),
(7, 9, 2, 'advisor', 'Chào Dũng,\nCô đã gửi thông báo chung cho lớp rồi. Ý tưởng của em nghe có vẻ hay đấy. Em nên chuẩn bị kỹ về tính khả thi, thị trường mục tiêu và mô hình kinh doanh. Cô có thể hỗ trợ em xem xét ý tưởng nếu em cần.', NULL, 1, '2025-03-12 16:05:00'),
(8, 9, 2, 'student', 'Dạ em cảm ơn cô! Em sẽ chuẩn bị kỹ và xin phép được gửi bản kế hoạch cho cô xem trước ạ.', NULL, 0, '2025-03-12 16:10:00'),

-- Cuộc trò chuyện 3: Sinh viên 14 (Quốc) và CVHT (GV004 - Cô Nhi)
(9, 14, 4, 'student', 'Cô ơi, em là Quốc. Em muốn xin lỗi vì kết quả học tập HK1 của em không tốt. Em có vấn đề gia đình nên ảnh hưởng đến việc học.', NULL, 1, '2025-03-13 10:00:00'),
(10, 14, 4, 'advisor', 'Chào Quốc,\nCô cảm thông với hoàn cảnh của em. Điều quan trọng là em đã nhận ra vấn đề. Bây giờ em cần tập trung vào việc học lại các môn đã rớt. Nếu em gặp khó khăn về tài chính hoặc vấn đề gì khác, em có thể chia sẻ với cô.', NULL, 1, '2025-03-13 10:10:00'),
(11, 14, 4, 'student', 'Dạ em cảm ơn cô. Em đang phải đi làm thêm nhiều để lo cho gia đình, nhưng em sẽ cố gắng sắp xếp thời gian học tập hợp lý hơn ạ.', NULL, 1, '2025-03-13 10:15:00'),
(12, 14, 4, 'advisor', 'Cô hiểu. Nhưng em cần cân bằng giữa việc học và làm thêm. Nếu cần, cô có thể giới thiệu em một số nguồn học bổng hoặc hỗ trợ tài chính. Em đến gặp cô vào giờ hành chính để cô tư vấn kỹ hơn nhé.', NULL, 0, '2025-03-13 10:20:00'),

-- Cuộc trò chuyện 4: Sinh viên 1 (Hùng) và CVHT (GV001 - Thầy An)
(13, 1, 1, 'student', 'Thầy ơi, em muốn hỏi về việc tham gia nghiên cứu khoa học. Em có hứng thú với lĩnh vực AI và muốn tìm hiểu sâu hơn.', NULL, 1, '2025-03-14 15:00:00'),
(14, 1, 1, 'advisor', 'Chào Hùng,\nThầy rất vui khi biết em có hứng thú với nghiên cứu. Khoa có một số đề tài nghiên cứu đang mở. Thầy sẽ gửi thông tin chi tiết cho em. Em cũng nên tham gia Workshop về AI sắp tới để mở rộng kiến thức.', NULL, 1, '2025-03-14 15:10:00'),
(15, 1, 1, 'student', 'Dạ em cảm ơn thầy! Em đã đăng ký Workshop rồi ạ. Em rất mong được tham gia nghiên cứu.', NULL, 0, '2025-03-14 15:15:00'),

-- Cuộc trò chuyện 5: Sinh viên 3 (Bình) và CVHT (GV001 - Thầy An)
(16, 3, 1, 'student', 'Thầy ơi, em muốn xin ý kiến của thầy về việc đi thực tập sớm. Em được một công ty mời thực tập nhưng sợ ảnh hưởng đến việc học.', NULL, 1, '2025-03-16 09:00:00'),
(17, 3, 1, 'advisor', 'Chào Bình,\nĐây là cơ hội tốt đấy. Tuy nhiên, em cần đảm bảo rằng lịch thực tập không trung với lịch học. Thầy khuyên em nên thực tập vào HK Hè hoặc các ngày cuối tuần. Em gửi thông tin về công ty và lịch thực tập cho thầy xem nhé.', NULL, 1, '2025-03-16 09:10:00'),
(18, 3, 1, 'student', 'Dạ em sẽ gửi thông tin cho thầy. Em cảm ơn thầy đã tư vấn ạ!', NULL, 0, '2025-03-16 09:15:00'),

-- Cuộc trò chuyện 6: Sinh viên 10 (Yến) và CVHT (GV002 - Cô Bích)
(19, 10, 2, 'student', 'Cô ơi, em muốn hỏi về học bổng. Em thấy một số bạn được nhận học bổng nhưng điểm không cao hơn em. Em không hiểu tiêu chí như thế nào ạ?', NULL, 1, '2025-03-23 10:00:00'),
(20, 10, 2, 'advisor', 'Chào Yến,\nHọc bổng không chỉ dựa vào điểm học tập mà còn xét đến nhiều yếu tố khác như: hoàn cảnh gia đình, điểm rèn luyện, kết quả hoạt động ngoại khóa. Em có thể xem chi tiết tiêu chí trên website của phòng Tài chính. Nếu em đủ điều kiện, em có thể nộp hồ sơ xét học bổng cho kỳ tiếp theo.', NULL, 1, '2025-03-23 10:15:00'),
(21, 10, 2, 'student', 'Dạ em hiểu rồi ạ. Em cảm ơn cô đã giải thích!', NULL, 0, '2025-03-23 10:20:00');

-- =================================================================
-- 24. STUDENT_MONITORING_NOTES (Ghi chú theo dõi sinh viên)
-- =================================================================
INSERT INTO Student_Monitoring_Notes (note_id, student_id, advisor_id, semester_id, category, title, content, created_at) VALUES
-- Theo dõi sinh viên 2 (Cẩm) - Học yếu
(1, 2, 1, 3, 'academic', 'Theo dõi SV Trần Thị Thu Cẩm - Rớt môn IT001', 
'Sinh viên có điểm giữa kỳ thấp (3.0/10) cho môn IT001. Vắng 2 buổi học vì lý do gia đình. Cần theo dõi sát sao quá trình học lại trong HK2.', 
'2025-01-19 10:00:00'),

(2, 2, 1, 4, 'attendance', 'Theo dõi chuyên cần HK2 - Môn học lại IT001', 
'Kiểm tra chuyên cần môn IT001 (học lại) của sinh viên Cẩm hàng tuần. Tuần 1-2: Đi học đầy đủ. Sinh viên có vẻ có quyết tâm cải thiện kết quả.', 
'2025-02-15 11:00:00'),

(3, 2, 1, 4, 'academic', 'Kiểm tra tiến độ học tập tuần 4-5 HK2', 
'Sinh viên Cẩm đang cố gắng theo kịp chương trình. Điểm bài tập và kiểm tra nhỏ đã cải thiện (từ 3-4 điểm lên 6-7 điểm). Tiếp tục động viên và hỗ trợ.', 
'2025-03-10 14:00:00'),

-- Theo dõi sinh viên 14 (Quốc) - Học yếu + vấn đề cá nhân
(4, 14, 4, 3, 'academic', 'Theo dõi SV Trịnh Bảo Quốc - Rớt môn EN001', 
'Sinh viên có vẻ gặp khó khăn trong kỹ năng Nghe (Listening). Điểm Speaking cũng không tốt. Cần tư vấn về phương pháp học và khuyến khích tham gia CLB Tiếng Anh.', 
'2025-01-20 10:00:00'),

(5, 14, 4, 4, 'personal', 'SV Quốc chia sẻ vấn đề cá nhân', 
'Sinh viên Quốc chia sẻ với tôi rằng gia đình đang gặp khó khăn về tài chính. Bố mẹ mất việc nên em phải đi làm thêm nhiều. Điều này ảnh hưởng đến thời gian học tập và tinh thần của em. Tôi đã tư vấn về các nguồn học bổng và hỗ trợ tài chính.', 
'2025-03-01 14:30:00'),

(6, 14, 4, 4, 'personal', 'Theo dõi tình trạng tâm lý SV Quốc', 
'Sinh viên có dấu hiệu stress và lo lắng nhiều. Đã khuyên em cân nhắc giảm giờ làm thêm để tập trung học tập. Hẹn gặp lại sau 2 tuần để theo dõi tình hình.', 
'2025-03-15 10:00:00'),

-- Theo dõi sinh viên giỏi - Hùng (1) và Bình (3)
(7, 1, 1, 3, 'academic', 'SV Nguyễn Văn Hùng - Thành tích tốt', 
'Sinh viên có kết quả học tập ổn định, GPA 7.75. Có hứng thú với nghiên cứu khoa học, đặc biệt là AI. Nên khuyến khích em tham gia các đề tài nghiên cứu của khoa.', 
'2025-01-25 11:00:00'),

(8, 3, 1, 3, 'academic', 'SV Phan Thanh Bình - Học sinh xuất sắc', 
'Sinh viên có GPA cao (8.63), tích cực tham gia các hoạt động của lớp và khoa. Đã nhận được lời mời thực tập từ công ty. Cần tư vấn để em cân bằng giữa học tập và thực tập.', 
'2025-03-16 15:00:00'),

-- Theo dõi sinh viên có vấn đề về điểm rèn luyện
(9, 7, 5, 3, 'attendance', 'SV Bùi Văn Tài - Điểm rèn luyện thấp', 
'Sinh viên có điểm rèn luyện HK1 chỉ đạt 65/100. Ít tham gia các hoạt động ngoại khóa. Cần động viên em tham gia nhiều hơn để cải thiện điểm rèn luyện.', 
'2025-01-30 09:00:00'),

-- Theo dõi tích cực
(10, 9, 2, 3, 'academic', 'SV Lê Văn Dũng - Kết quả xuất sắc', 
'Sinh viên đạt GPA 8.50, tích cực tham gia các hoạt động. Có tiềm năng về khởi nghiệp. Đã đăng ký tham gia cuộc thi Ý tưởng Khởi nghiệp 2025. Hỗ trợ em hoàn thiện ý tưởng.', 
'2025-03-18 10:00:00');