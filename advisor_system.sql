-- =================================================================
-- LỆNH TẠO CSDL VÀ CHỌN CSDL
-- =================================================================
CREATE DATABASE db_advisorsystem_separated
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE db_advisorsystem_separated;

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
-- PHẦN 9: CHÈN DỮ LIỆU MẪU (ĐÃ SỬA LỖI)
-- =================================================================
SET @default_hash = '$2y$10$XDVj1Dr7HJgAdrq8NNpzeurpieW7HRYNK53LcjVOMeGKIMNiib2ky';

-- Units
INSERT INTO Units (unit_id, unit_name, type, description) VALUES
(1, 'Khoa Công nghệ Thông tin', 'faculty', 'Quản lý các ngành thuộc lĩnh vực CNTT'),
(2, 'Khoa Kinh tế', 'faculty', 'Quản lý các ngành thuộc lĩnh vực Kinh tế và Quản trị'),
(3, 'Phòng Công tác Sinh viên', 'department', 'Quản lý các hoạt động ngoại khóa, điểm rèn luyện'),
(4, 'Khoa Ngôn ngữ Anh', 'faculty', 'Quản lý các ngành thuộc lĩnh vực Ngôn ngữ'),
(5, 'Phòng Tài chính - Kế toán', 'department', 'Quản lý các vấn đề học phí');

-- Advisors
INSERT INTO Advisors (advisor_id, user_code, full_name, email, password_hash, phone_number, unit_id) VALUES
(1, 'GV001', 'ThS. Trần Văn An', 'gv.an@school.edu.vn', @default_hash, '090111222', 1),
(2, 'GV002', 'TS. Nguyễn Thị Bích', 'gv.bich@school.edu.vn', @default_hash, '090222333', 2),
(3, 'GV003', 'ThS. Lê Hoàng Cường', 'gv.cuong@school.edu.vn', @default_hash, '090333444', 3),
(4, 'GV004', 'ThS. Đỗ Yến Nhi', 'gv.nhi@school.edu.vn', @default_hash, '090444555', 4);

-- Classes
INSERT INTO Classes (class_id, class_name, advisor_id, faculty_id, description) VALUES
(1, 'DH21CNTT', 1, 1, 'Lớp Đại học 2021 ngành CNTT'),
(2, 'DH22KT', 2, 2, 'Lớp Đại học 2022 ngành Kinh tế'),
(3, 'DH21NNA', 4, 4, 'Lớp Đại học 2021 ngành Ngôn ngữ Anh');

-- Students
INSERT INTO Students (student_id, user_code, full_name, email, password_hash, phone_number, class_id, status) VALUES
(1, '210001', 'Nguyễn Văn Hùng', 'sv.hung@school.edu.vn', @default_hash, '091122334', 1, 'studying'),
(2, '210002', 'Trần Thị Thu Cẩm', 'sv.cam@school.edu.vn', @default_hash, '091234567', 1, 'studying'),
(3, '220001', 'Lê Văn Dũng', 'sv.dung@school.edu.vn', @default_hash, '092233445', 2, 'studying'),
(4, '220002', 'Phạm Hoàng Yến', 'sv.yen@school.edu.vn', @default_hash, '093344556', 2, 'studying'),
(5, '210003', 'Phan Thanh Bình', 'sv.binh@school.edu.vn', @default_hash, '094455667', 1, 'studying'),
(6, '210004', 'Võ Thị Kim Anh', 'sv.anh@school.edu.vn', @default_hash, '095566778', 1, 'studying'),
(7, '210005', 'Trịnh Bảo Quốc', 'sv.quoc@school.edu.vn', @default_hash, '096677889', 3, 'studying'),
(8, '210006', 'Mai Lan Chi', 'sv.chi@school.edu.vn', @default_hash, '097788990', 3, 'studying');

-- Semesters
INSERT INTO Semesters (semester_id, semester_name, academic_year, start_date, end_date) VALUES
(1, 'Học kỳ 1', '2024-2025', '2024-09-05', '2025-01-15'),
(2, 'Học kỳ 2', '2024-2025', '2025-02-10', '2025-06-30'),
(3, 'Học kỳ 1', '2025-2026', '2025-09-08', '2026-01-18');

-- Courses
INSERT INTO Courses (course_id, course_code, course_name, credits) VALUES
(1, 'IT001', 'Nhập môn Lập trình', 4),
(2, 'IT002', 'Cấu trúc dữ liệu và Giải thuật', 4),
(3, 'BA001', 'Kinh tế vi mô', 3),
(4, 'MK001', 'Nguyên lý Marketing', 3),
(5, 'EN001', 'Nghe - Nói 1', 3),
(6, 'EN002', 'Đọc - Viết 1', 3);

-- Course_Grades
INSERT INTO Course_Grades (student_id, course_id, semester_id, grade_value, status) VALUES
(1, 1, 1, 8.5, 'passed'),
(1, 2, 1, 7.0, 'passed'),
(2, 1, 1, 4.0, 'failed'),
(2, 2, 1, 5.0, 'passed'),
(3, 3, 1, 9.0, 'passed'),
(3, 4, 1, 8.0, 'passed'),
(2, 1, 2, NULL, 'studying'),
(5, 1, 1, 9.0, 'passed'),
(6, 2, 1, 6.5, 'passed'),
(7, 5, 1, 3.5, 'failed'),
(8, 5, 1, 8.0, 'passed'),
(8, 6, 1, 7.5, 'passed'),
(1, 2, 2, 8.0, 'passed'),
(3, 4, 2, 7.0, 'passed'),
(4, 3, 2, NULL, 'studying'),
(7, 5, 2, NULL, 'studying');

-- Semester_Reports
INSERT INTO Semester_Reports (student_id, semester_id, gpa, credits_registered, credits_passed, training_point_summary, social_point_summary, outcome) VALUES
(1, 1, 7.75, 8, 8, 85, 15, 'Học tiếp'),
(2, 1, 4.50, 8, 4, 70, 5, 'Cảnh cáo học vụ mức 1 (GPA < 5.0)'),
(3, 1, 8.50, 6, 6, 90, 20, 'Học tiếp (Khen thưởng)'),
(4, 1, 0.00, 0, 0, 50, 0, 'Học tiếp'),
(5, 1, 9.00, 4, 4, 90, 10, 'Học tiếp (Khen thưởng)'),
(6, 1, 6.50, 4, 4, 75, 5, 'Học tiếp'),
(7, 1, 3.50, 3, 0, 60, 0, 'Cảnh cáo học vụ mức 1'),
(8, 1, 7.75, 6, 6, 80, 10, 'Học tiếp'),
(1, 2, 8.00, 4, 4, 80, 0, 'Học tiếp'),
(2, 2, 0.00, 4, 0, 70, 0, 'Chưa có điểm (Học lại)'),
(3, 2, 7.00, 3, 3, 85, 0, 'Học tiếp'),
(4, 2, 0.00, 3, 0, 75, 0, 'Chưa có điểm'),
(5, 2, 0.00, 0, 0, 80, 0, 'Học tiếp'),
(6, 2, 0.00, 0, 0, 75, 0, 'Học tiếp'),
(7, 2, 0.00, 3, 0, 60, 0, 'Chưa có điểm (Học lại)'),
(8, 2, 0.00, 0, 0, 80, 0, 'Học tiếp');

-- Academic_Warnings
INSERT INTO Academic_Warnings (student_id, advisor_id, semester_id, title, content, advice) VALUES
(2, 1, 1, 'Quyết định Cảnh cáo học vụ HK1 2024-2025', 'Sinh viên Trần Thị Thu Cẩm (210002) có GPA HK1 2024-2025 là 4.50...', 'Yêu cầu sinh viên đăng ký học lại...'),
(7, 4, 1, 'Quyết định Cảnh cáo học vụ HK1 2024-2025', 'Sinh viên Trịnh Bảo Quốc (210005) có GPA HK1 2024-2025 là 3.50...', 'Yêu cầu SV đăng ký học lại...');

-- Point_Feedbacks (ĐÃ SỬA LỖI)
INSERT INTO Point_Feedbacks (student_id, semester_id, feedback_content, attachment_path, status, advisor_response, advisor_id, response_at) VALUES
(2, 1, 'Em đã tham gia hoạt động "Ngày hội CLB"...', 'attachments/minhchung_cam_hk1.jpg', 'approved', 'Đã kiểm tra và cộng bổ sung 5 điểm...', 1, '2025-03-12 10:00:00'),
(3, 1, 'Em tham gia Tình nguyện viên cho HĐ Hiến máu...', NULL, 'pending', NULL, NULL, NULL);

-- Activities
INSERT INTO Activities (activity_id, advisor_id, organizer_unit_id, title, general_description, location, start_time, end_time, status) VALUES
(1, 3, 3, 'Hiến máu nhân đạo 2025', 'Hoạt động hiến máu cứu người', 'Sảnh A, Cơ sở 1', '2025-03-15 08:00:00', '2025-03-15 11:30:00', 'completed'),
(2, 1, 1, 'Workshop: Giới thiệu về AI tạo sinh', 'Workshop chuyên đề cho SV Khoa CNTT', 'Phòng Hội thảo H.201', '2025-03-20 14:00:00', '2025-03-20 16:00:00', 'completed'),
(3, 2, 2, 'Cuộc thi Ý tưởng Khởi nghiệp 2025', 'Cuộc thi dành cho SV Khoa Kinh tế', 'Hội trường B', '2025-04-10 08:00:00', '2025-04-10 17:00:00', 'upcoming'),
(4, 4, 4, 'CLB Tiếng Anh: Vòng Bán kết', 'Sự kiện thường niên của Khoa NNA', 'Phòng C.101', '2025-04-05 18:00:00', '2025-04-05 20:00:00', 'completed');

-- Activity_Roles
INSERT INTO Activity_Roles (activity_role_id, activity_id, role_name, points_awarded, point_type, max_slots) VALUES
(1, 1, 'Tham gia hiến máu', 5, 'ctxh', 100),
(2, 1, 'Tình nguyện viên hỗ trợ', 10, 'ctxh', 10),
(3, 2, 'Người tham dự', 10, 'ren_luyen', 50),
(4, 3, 'Đội thi Vòng Chung kết', 20, 'ren_luyen', 50),
(5, 4, 'Khán giả cổ vũ', 5, 'ren_luyen', 100);

-- Activity_Registrations
INSERT INTO Activity_Registrations (registration_id, activity_role_id, student_id, status) VALUES
(1, 1, 1, 'attended'),
(2, 1, 3, 'attended'),
(3, 3, 1, 'attended'),
(4, 3, 2, 'cancelled'),
(5, 4, 3, 'registered'),
(6, 4, 4, 'registered'),
(7, 5, 7, 'attended'),
(8, 5, 8, 'attended');

-- Cancellation_Requests
INSERT INTO Cancellation_Requests (request_id, registration_id, reason, status) VALUES
(1, 4, 'Em bị trùng lịch thi giữa kỳ môn học lại. Em xin phép hủy ạ.', 'approved');

-- Notifications
INSERT INTO Notifications (notification_id, advisor_id, title, summary, type, created_at) VALUES
(1, 1, 'Thông báo Họp lớp DH21CNTT tháng 3/2025', 'Triển khai công tác chuẩn bị cho HK2...', 'general', '2025-03-09 08:00:00'),
(2, 2, 'Thông báo chung: Quy định về đăng ký môn học HK hè', 'Nhắc nhở sinh viên về các mốc thời gian...', 'academic', '2025-03-10 08:00:00'),
(3, 1, 'Thông báo khẩn: Cập nhật quy chế thi cử HK2/2024-2025', 'Nhà trường ban hành quy chế mới...', 'academic', '2025-03-12 11:00:00'),
(4, 4, 'Thông báo Họp lớp DH21NNA tháng 3/2025', 'Lịch họp lớp lần 2...', 'general', '2025-03-12 14:00:00');

-- Notification_Class
INSERT INTO Notification_Class (notification_id, class_id) VALUES
(1, 1), (2, 1), (2, 2), (3, 1), (4, 3);

-- Notification_Attachments
INSERT INTO Notification_Attachments (notification_id, file_path, file_name) VALUES
(2, 'attachments/quydinh_dkmh_he_2025.pdf', 'QuyDinh_DKMH_He_2025.pdf'),
(3, 'attachments/QuyCheThiCuMoi_HK2_2025.pdf', 'QuyCheThiCuMoi_HK2_2025.pdf');

-- Notification_Recipients
INSERT INTO Notification_Recipients (notification_id, student_id, is_read, read_at) VALUES
(1, 1, 1, '2025-03-10 09:00:00'),
(1, 2, 0, NULL),
(2, 1, 1, '2025-03-11 10:00:00'),
(2, 2, 1, '2025-03-11 11:30:00'),
(2, 3, 0, NULL),
(2, 4, 0, NULL),
(1, 5, 0, NULL),
(1, 6, 0, NULL),
(2, 5, 0, NULL),
(2, 6, 1, '2025-03-12 09:00:00'),
(3, 1, 1, '2025-03-12 13:00:00'),
(3, 2, 1, '2025-03-12 14:00:00'),
(3, 5, 0, NULL),
(3, 6, 0, NULL),
(4, 7, 1, '2025-03-12 15:00:00'),
(4, 8, 0, NULL);

-- Notification_Responses
INSERT INTO Notification_Responses (response_id, notification_id, student_id, content, status, advisor_response, advisor_id, response_at) VALUES
(1, 1, 1, 'Dạ em đã nhận thông báo. Em muốn hỏi là buổi họp có bắt buộc không ạ?', 'resolved', 'Chào Hùng, buổi họp này rất quan trọng...', 1, '2025-03-12 10:15:00'),
(2, 4, 7, 'Dạ em cảm ơn cô. Em sẽ tham gia ạ.', 'resolved', NULL, NULL, NULL);

-- Meetings
INSERT INTO Meetings (meeting_id, advisor_id, class_id, title, location, meeting_time, status, minutes_file_path) VALUES
(1, 1, 1, 'Họp lớp DH21CNTT tháng 3/2025 (Triển khai HK2)', 'Phòng B.101', '2025-03-15 10:00:00', 'completed', 'meetings/bienban_hop_lop_dh21cntt_t3_2025.pdf'),
(2, 4, 3, 'Họp lớp DH21NNA tháng 3/2025 (Triển khai HK2)', 'Phòng C.202', '2025-03-18 10:00:00', 'scheduled', NULL);

-- Meeting_Student
INSERT INTO Meeting_Student (meeting_id, student_id, attended) VALUES
(1, 1, 1), (1, 2, 0), (1, 5, 1), (1, 6, 1),
(2, 7, 0), (2, 8, 0);

-- Meeting_Feedbacks
INSERT INTO Meeting_Feedbacks (meeting_id, student_id, feedback_content, created_at) VALUES
(1, 5, 'Em thấy biên bản họp ghi thiếu phần ý kiến của em về quỹ lớp ạ.', '2025-03-16 08:00:00');

-- Messages
INSERT INTO Messages (student_id, advisor_id, sender_type, content, is_read, sent_at) VALUES
(2, 1, 'student', 'Thầy ơi, em bị cảnh cáo học vụ HK1, giờ em phải làm sao ạ?', 0, '2025-03-11 09:00:00'),
(2, 1, 'advisor', 'Chào Cẩm, em cần đăng ký học lại ngay môn IT001 trong HK2 này nhé.', 1, '2025-03-11 09:05:00'),
(2, 1, 'student', 'Dạ em đăng ký học lại rồi ạ. Em cảm ơn thầy.', 0, '2025-03-11 09:10:00'),
(3, 2, 'student', 'Cô ơi, em muốn hỏi về cuộc thi Ý tưởng Khởi nghiệp.', 0, '2025-03-12 16:00:00'),
(3, 2, 'advisor', 'Chào Dũng, cô đã gửi thông báo chung cho lớp Kinh tế rồi...', 0, '2025-03-12 16:05:00');

-- Student_Monitoring_Notes
INSERT INTO Student_Monitoring_Notes (student_id, advisor_id, semester_id, category, title, content, created_at) VALUES
(2, 1, 1, 'academic', 'Theo dõi SV Cẩm (210002) - Rớt môn IT001', 'SV có điểm giữa kỳ thấp (3.0), vắng 2 buổi.', '2025-01-19 10:00:00'),
(2, 1, 2, 'attendance', 'Theo dõi chuyên cần HK2 (môn học lại)', 'Kiểm tra chuyên cần môn IT001 (học lại) của SV Cẩm hàng tuần.', '2025-02-15 11:00:00'),
(7, 4, 1, 'academic', 'Theo dõi SV Quốc (210005) - Rớt môn EN001', 'SV có vẻ gặp khó khăn trong kỹ năng Nghe.', '2025-01-20 10:00:00'),
(7, 4, 2, 'personal', 'SV Quốc chia sẻ có vấn đề cá nhân', 'Gia đình SV Quốc gặp khó khăn, em ấy phải đi làm thêm nhiều.', '2025-03-01 14:30:00');