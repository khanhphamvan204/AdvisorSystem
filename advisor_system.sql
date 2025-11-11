CREATE DATABASE db_advisorsystem
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE db_advisorsystem;

CREATE TABLE Students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(15) NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    class_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'studying'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Advisors (
    advisor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(15) NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    unit_id INT NULL,
    role ENUM('advisor', 'admin') NOT NULL DEFAULT 'advisor' 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Units (
    unit_id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(150) NOT NULL UNIQUE,
    type ENUM('faculty', 'department') NOT NULL,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL UNIQUE,
    advisor_id INT NULL,
    faculty_id INT NULL,
    description TEXT NULL,
    FOREIGN KEY (faculty_id) REFERENCES Units(unit_id) ON DELETE SET NULL,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(advisor_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE Students
ADD CONSTRAINT fk_student_class
FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE RESTRICT;

ALTER TABLE Advisors
ADD CONSTRAINT fk_advisor_unit
FOREIGN KEY (unit_id) REFERENCES Units(unit_id) ON DELETE SET NULL;

CREATE TABLE Semesters (
    semester_id INT AUTO_INCREMENT PRIMARY KEY,
    semester_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    UNIQUE KEY uk_semester_year (semester_name, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(100) NOT NULL,
    credits TINYINT NOT NULL,
    unit_id INT NULL, 
    FOREIGN KEY (unit_id) REFERENCES Units(unit_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Course_Grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    semester_id INT NOT NULL,
    grade_value DECIMAL(4, 2) NULL,
    grade_letter CHAR(2) NULL,
    grade_4_scale DECIMAL(3, 2) NULL,
    status ENUM('passed', 'failed', 'studying') NOT NULL DEFAULT 'studying',
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT,
    UNIQUE KEY uk_student_course_semester (student_id, course_id, semester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Semester_Reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    semester_id INT NOT NULL,
    gpa DECIMAL(3, 2) NULL DEFAULT 0.00,
    gpa_4_scale DECIMAL(3, 2) NULL DEFAULT 0.00,
    cpa_10_scale DECIMAL(4, 2) NULL DEFAULT 0.00,
    cpa_4_scale DECIMAL(3, 2) NULL DEFAULT 0.00,
    credits_registered SMALLINT NOT NULL DEFAULT 0,
    credits_passed SMALLINT NOT NULL DEFAULT 0,
    training_point_summary INT NOT NULL DEFAULT 0,
    social_point_summary INT NOT NULL DEFAULT 0,
    outcome VARCHAR(255) NULL,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT,
    UNIQUE KEY uk_student_semester (student_id, semester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE Activity_Class (
    activity_id INT NOT NULL,
    class_id INT NOT NULL,
    PRIMARY KEY (activity_id, class_id),
    FOREIGN KEY (activity_id) REFERENCES Activities(activity_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE Cancellation_Requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES Activity_Registrations(registration_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE Notification_Class (
    notification_id INT NOT NULL,
    class_id INT NOT NULL,
    PRIMARY KEY (notification_id, class_id),
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Notification_Attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE Meeting_Student (
    meeting_student_id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    student_id INT NOT NULL,
    attended BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (meeting_id) REFERENCES Meetings(meeting_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE,
    UNIQUE KEY uk_meeting_student (meeting_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Meeting_Feedbacks (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    student_id INT NOT NULL,
    feedback_content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES Meetings(meeting_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- THIẾT LẬP MẬT KHẨU MẶC ĐỊNH
SET @default_hash = '$2y$10$XDVj1Dr7HJgAdrq8NNpzeurpieW7HRYNK53LcjVOMeGKIMNiib2ky';

-- ========================================
-- 1. Units (5 dòng)
-- ========================================
INSERT INTO Units (unit_name, type, description) VALUES
('Khoa Công nghệ Thông tin', 'faculty', 'Quản lý các ngành thuộc lĩnh vực CNTT'),
('Khoa Kinh tế', 'faculty', 'Quản lý các ngành Kinh tế, Quản trị kinh doanh'),
('Khoa Ngôn ngữ Anh', 'faculty', 'Quản lý ngành Ngôn ngữ Anh và Biên - Phiên dịch'),
('Phòng Công tác Sinh viên', 'department', 'Quản lý hoạt động ngoại khóa, điểm rèn luyện'),
('Phòng Đào tạo', 'department', 'Quản lý chương trình đào tạo, lịch học, thi cử');

-- ========================================
-- 2. Advisors (5 dòng)
-- ========================================
INSERT INTO Advisors (user_code, full_name, email, password_hash, phone_number, unit_id, role) VALUES
('GV001', 'ThS. Trần Văn An', 'gv.an@school.edu.vn', @default_hash, '090111222', 1, 'advisor'),
('GV002', 'TS. Nguyễn Thị Bích', 'gv.bich@school.edu.vn', @default_hash, '090222333', 2, 'advisor'),
('GV003', 'ThS. Lê Hoàng Cường', 'gv.cuong@school.edu.vn', @default_hash, '090333444', 4, 'advisor'),
('GV004', 'ThS. Đỗ Yến Nhi', 'gv.nhi@school.edu.vn', @default_hash, '090444555', 3, 'advisor'),
('ADMIN001', 'Quản trị viên Hệ thống', 'admin@school.edu.vn', @default_hash, '090999999', 5, 'admin');

-- ========================================
-- 3. Classes (5 dòng)
-- ========================================
INSERT INTO Classes (class_name, advisor_id, faculty_id, description) VALUES
('DH21CNTT', 1, 1, 'Lớp Đại học 2021 ngành Công nghệ Thông tin'),
('DH22KT', 2, 2, 'Lớp Đại học 2022 ngành Kinh tế'),
('DH21NNA', 4, 3, 'Lớp Đại học 2021 ngành Ngôn ngữ Anh'),
('DH23CNTT', 1, 1, 'Lớp Đại học 2023 ngành Công nghệ Thông tin'),
('DH23KT', 2, 2, 'Lớp Đại học 2023 ngành Kinh tế');

-- ========================================
-- 4. Students (5 dòng)
-- ========================================
INSERT INTO Students (user_code, full_name, email, password_hash, phone_number, class_id, status) VALUES
('210001', 'Nguyễn Văn Hùng', 'sv.hung@school.edu.vn', @default_hash, '091122334', 1, 'studying'),
('210002', 'Trần Thị Thu Cẩm', 'sv.cam@school.edu.vn', @default_hash, '091234567', 1, 'studying'),
('220001', 'Lê Văn Dũng', 'sv.dung@school.edu.vn', @default_hash, '092233445', 2, 'studying'),
('230001', 'Đỗ Minh Nam', 'sv.nam@school.edu.vn', @default_hash, '091112233', 4, 'studying'),
('230002', 'Bùi Thị Hương', 'sv.huong@school.edu.vn', @default_hash, '091223344', 4, 'studying');

-- ========================================
-- 5. Semesters (5 dòng)
-- ========================================
INSERT INTO Semesters (semester_name, academic_year, start_date, end_date) VALUES
('Học kỳ 1', '2024-2025', '2024-09-05', '2025-01-15'),
('Học kỳ 2', '2024-2025', '2025-02-10', '2025-06-30'),
('Học kỳ 1', '2023-2024', '2023-09-04', '2024-01-20'),
('Học kỳ 2', '2023-2024', '2024-02-05', '2024-06-25'),
('Học kỳ hè', '2024', '2024-07-01', '2024-08-20');

-- ========================================
-- 6. Courses (5 dòng)
-- ========================================
INSERT INTO Courses (course_code, course_name, credits, unit_id) VALUES
('IT001', 'Nhập môn Lập trình', 4, 1),
('IT002', 'Cấu trúc Dữ liệu và Giải thuật', 4, 1),
('BA001', 'Kinh tế vi mô', 3, 2),
('EN001', 'Nghe - Nói 1', 3, 3),
('IT003', 'Lập trình Web', 3, 1);

-- ========================================
-- 7. Course_Grades (5 dòng)
-- ========================================
INSERT INTO Course_Grades (student_id, course_id, semester_id, grade_value, grade_letter, grade_4_scale, status) VALUES
(1, 1, 1, 8.5, 'B+', 3.3, 'passed'),
(1, 2, 1, 7.0, 'C+', 2.7, 'passed'),
(2, 1, 1, 4.0, 'F', 0.0, 'failed'),
(3, 3, 1, 9.0, 'A', 4.0, 'passed'),
(4, 5, 1, 7.8, 'B', 3.0, 'passed');

-- ========================================
-- 8. Semester_Reports (5 dòng)
-- ========================================
INSERT INTO Semester_Reports (student_id, semester_id, gpa, gpa_4_scale, cpa_10_scale, cpa_4_scale, credits_registered, credits_passed, training_point_summary, social_point_summary, outcome) VALUES
(1, 1, 7.75, 3.00, 7.75, 3.00, 8, 8, 85, 15, 'Học tiếp'),
(2, 1, 4.00, 0.00, 4.00, 0.00, 4, 0, 70, 5, 'Cảnh cáo học vụ mức 1'),
(3, 1, 9.00, 4.00, 9.00, 4.00, 3, 3, 90, 20, 'Học tiếp (Khen thưởng)'),
(4, 1, 7.80, 3.00, 7.80, 3.00, 3, 3, 80, 10, 'Học tiếp'),
(5, 1, 0.00, 0.00, 0.00, 0.00, 0, 0, 75, 0, 'Chưa có điểm');

-- ========================================
-- 9. Academic_Warnings (5 dòng)
-- ========================================
INSERT INTO Academic_Warnings (student_id, advisor_id, semester_id, title, content, advice, created_at) VALUES
(2, 1, 1, 'Cảnh cáo học vụ HK1 2024-2025', 'Sinh viên Trần Thị Thu Cẩm có GPA 4.0, rớt môn IT001.', 'Đăng ký học lại môn IT001 ngay trong HK2.', '2025-01-20 10:00:00'),
(2, 1, 2, 'Theo dõi học lại IT001', 'Kiểm tra chuyên cần và điểm giữa kỳ.', 'Hỗ trợ tài liệu, học nhóm.', '2025-02-15 11:00:00'),
(1, 1, 1, 'Khen thưởng học kỳ', 'GPA 7.75, đạt loại khá.', 'Tiếp tục phát huy.', '2025-01-25 09:00:00'),
(3, 2, 1, 'Khen thưởng xuất sắc', 'GPA 9.0, đạt loại giỏi.', 'Cử tham gia học bổng.', '2025-01-26 10:00:00'),
(4, 1, 1, 'Theo dõi tiến độ', 'Sinh viên mới, cần theo dõi chuyên cần.', 'Gặp cố vấn định kỳ.', '2025-01-18 14:00:00');

-- ========================================
-- 10. Point_Feedbacks (5 dòng)
-- ========================================
INSERT INTO Point_Feedbacks (student_id, semester_id, feedback_content, attachment_path, status, advisor_response, advisor_id, response_at) VALUES
(1, 1, 'Tham gia Ngày hội CNTT 2024', 'attachments/ngayhoicntt_hung.jpg', 'approved', 'Cộng 10 điểm rèn luyện.', 1, '2025-01-22 09:30:00'),
(2, 1, 'Tham gia CLB Tiếng Anh', NULL, 'pending', NULL, NULL, NULL),
(3, 1, 'Hiến máu nhân đạo HK1', 'attachments/hienmau_dung.jpg', 'approved', 'Cộng 5 điểm CTXH.', 3, '2025-01-23 10:00:00'),
(4, 1, 'Tình nguyện dọn vệ sinh campus', NULL, 'rejected', 'Chưa đủ minh chứng.', 1, '2025-01-24 11:00:00'),
(5, 1, 'Tham gia hội thảo AI', 'attachments/ai_huong.jpg', 'approved', 'Cộng 8 điểm rèn luyện.', 1, '2025-01-25 14:00:00');

-- ========================================
-- 11. Activities (5 dòng)
-- ========================================
INSERT INTO Activities (advisor_id, organizer_unit_id, title, general_description, location, start_time, end_time, status) VALUES
(3, 4, 'Hiến máu nhân đạo 2025', 'Hoạt động cứu người', 'Sảnh A', '2025-03-15 08:00:00', '2025-03-15 11:30:00', 'completed'),
(1, 1, 'Workshop AI Tạo sinh', 'Giới thiệu công nghệ AI', 'Phòng H.201', '2025-03-20 14:00:00', '2025-03-20 16:00:00', 'completed'),
(2, 2, 'Cuộc thi Ý tưởng Khởi nghiệp', 'Thi ý tưởng kinh doanh', 'Hội trường B', '2025-04-10 08:00:00', '2025-04-10 17:00:00', 'upcoming'),
(1, 1, 'Cuộc thi Lập trình sinh viên', 'Thi đấu lập trình', 'Lab CNTT', '2025-06-01 08:00:00', '2025-06-01 17:00:00', 'upcoming'),
(4, 3, 'CLB Tiếng Anh: Hùng biện', 'Thi hùng biện tiếng Anh', 'Phòng C.101', '2025-04-05 18:00:00', '2025-04-05 20:00:00', 'completed');

-- ========================================
-- 12. Activity_Roles (5 dòng)
-- ========================================
INSERT INTO Activity_Roles (activity_id, role_name, description, requirements, points_awarded, point_type, max_slots) VALUES
(1, 'Người hiến máu', 'Hiến máu cứu người', NULL, 5, 'ctxh', 100),
(1, 'Tình nguyện viên', 'Hỗ trợ tổ chức', 'Có kỹ năng giao tiếp', 10, 'ctxh', 15),
(2, 'Người tham dự', 'Nghe workshop', NULL, 8, 'ren_luyen', 60),
(3, 'Đội thi', 'Tham gia vòng chung kết', 'Có ý tưởng rõ ràng', 20, 'ren_luyen', 50),
(5, 'Khán giả', 'Cổ vũ cuộc thi', NULL, 5, 'ren_luyen', 120);

-- ========================================
-- 13. Activity_Registrations (5 dòng)
-- ========================================
INSERT INTO Activity_Registrations (activity_role_id, student_id, registration_time, status) VALUES
(1, 1, '2025-03-01 09:00:00', 'attended'),
(1, 3, '2025-03-01 09:15:00', 'attended'),
(3, 1, '2025-03-10 10:00:00', 'attended'),
(4, 3, '2025-03-20 11:00:00', 'registered'),
(5, 2, '2025-03-25 12:00:00', 'cancelled');

-- ========================================
-- 14. Cancellation_Requests (5 dòng)
-- ========================================
INSERT INTO Cancellation_Requests (registration_id, reason, status, requested_at) VALUES
(5, 'Trùng lịch thi giữa kỳ.', 'approved', '2025-03-26 08:00:00'),
(3, 'Bận việc gia đình.', 'pending', '2025-03-15 09:00:00'),
(1, 'Bị ốm đột xuất.', 'rejected', '2025-03-14 10:00:00'),
(2, 'Đổi ca làm thêm.', 'approved', '2025-03-14 11:00:00'),
(4, 'Chưa đủ điều kiện tham gia.', 'pending', '2025-03-21 14:00:00');

-- ========================================
-- 15. Notifications (5 dòng)
-- ========================================
INSERT INTO Notifications (advisor_id, title, summary, link, type, created_at) VALUES
(1, 'Họp lớp DH21CNTT tháng 3', 'Triển khai HK2', NULL, 'general', '2025-03-09 08:00:00'),
(2, 'Quy định đăng ký môn HK hè', 'Nhắc nhở mốc thời gian', 'https://school.edu.vn/dkmh-he', 'academic', '2025-03-10 08:00:00'),
(1, 'Cập nhật quy chế thi HK2', 'Quy chế mới', 'attachments/quyche_thi_hk2.pdf', 'academic', '2025-03-12 11:00:00'),
(4, 'Họp lớp DH21NNA tháng 3', 'Lịch họp lần 2', NULL, 'general', '2025-03-12 14:00:00'),
(3, 'Thông báo hiến máu 2025', 'Mời tham gia', 'https://school.edu.vn/hienmau2025', 'general', '2025-03-01 09:00:00');

-- ========================================
-- 16. Notification_Class (5 dòng)
-- ========================================
INSERT INTO Notification_Class (notification_id, class_id) VALUES
(1, 1), (2, 1), (3, 1), (4, 3);

-- ========================================
-- 17. Notification_Attachments (2 dòng - ví dụ)
-- ========================================
INSERT INTO Notification_Attachments (notification_id, file_path, file_name) VALUES
(2, 'attachments/dkmh_he_2025.pdf', 'DKMH_He_2025.pdf'),
(3, 'attachments/quyche_thi_hk2.pdf', 'QuyChe_Thi_HK2_2025.pdf');

-- ========================================
-- 18. Notification_Recipients (5 dòng)
-- ========================================
INSERT INTO Notification_Recipients (notification_id, student_id, is_read, read_at) VALUES
(1, 1, TRUE, '2025-03-10 09:00:00'),
(1, 2, FALSE, NULL),
(2, 1, TRUE, '2025-03-11 10:00:00'),
(2, 3, FALSE, NULL),
(4, 5, TRUE, '2025-03-12 15:00:00');

-- ========================================
-- 19. Notification_Responses (3 dòng)
-- ========================================
INSERT INTO Notification_Responses (notification_id, student_id, content, status, advisor_response, advisor_id, response_at) VALUES
(1, 1, 'Buổi họp có bắt buộc không ạ?', 'resolved', 'Có, rất quan trọng.', 1, '2025-03-12 10:15:00'),
(4, 5, 'Em sẽ tham gia ạ.', 'resolved', NULL, NULL, NULL),
(2, 1, 'Em đã đăng ký môn hè rồi ạ.', 'pending', NULL, NULL, NULL);

-- ========================================
-- 20. Meetings (3 dòng)
-- ========================================
INSERT INTO Meetings (advisor_id, class_id, title, location, meeting_time, status, minutes_file_path) VALUES
(1, 1, 'Họp lớp DH21CNTT tháng 3', 'Phòng B.101', '2025-03-15 10:00:00', 'completed', 'meetings/bienban_dh21cntt_t3.pdf'),
(4, 3, 'Họp lớp DH21NNA tháng 3', 'Phòng C.202', '2025-03-18 10:00:00', 'scheduled', NULL),
(2, 2, 'Họp lớp DH22KT tháng 3', 'Phòng B.205', '2025-03-20 14:00:00', 'scheduled', NULL);

-- ========================================
-- 21. Meeting_Student (5 dòng)
-- ========================================
INSERT INTO Meeting_Student (meeting_id, student_id, attended) VALUES
(1, 1, TRUE), (1, 2, FALSE), (1, 4, TRUE),
(2, 5, FALSE), (3, 3, FALSE);

-- ========================================
-- 22. Meeting_Feedbacks (2 dòng)
-- ========================================
INSERT INTO Meeting_Feedbacks (meeting_id, student_id, feedback_content, created_at) VALUES
(1, 1, 'Ý kiến về quỹ lớp chưa được ghi.', '2025-03-16 08:00:00'),
(1, 4, 'Cảm ơn thầy đã tổ chức họp.', '2025-03-16 09:00:00');

-- ========================================
-- 23. Messages (5 dòng)
-- ========================================
INSERT INTO Messages (student_id, advisor_id, sender_type, content, is_read, sent_at) VALUES
(2, 1, 'student', 'Thầy ơi em bị cảnh cáo, phải làm sao?', FALSE, '2025-03-11 09:00:00'),
(2, 1, 'advisor', 'Em đăng ký học lại IT001 nhé.', TRUE, '2025-03-11 09:05:00'),
(3, 2, 'student', 'Cô ơi em muốn hỏi về cuộc thi khởi nghiệp.', FALSE, '2025-03-12 16:00:00'),
(1, 1, 'student', 'Em muốn xin tài liệu ôn thi.', TRUE, '2025-03-13 10:00:00'),
(4, 1, 'student', 'Em mới vào lớp, cần hỗ trợ gì ạ?', FALSE, '2025-03-14 11:00:00');

-- ========================================
-- 24. Student_Monitoring_Notes (5 dòng)
-- ========================================
INSERT INTO Student_Monitoring_Notes (student_id, advisor_id, semester_id, category, title, content, created_at) VALUES
(2, 1, 1, 'academic', 'Rớt môn IT001', 'Điểm giữa kỳ thấp, vắng 2 buổi.', '2025-01-19 10:00:00'),
(2, 1, 2, 'attendance', 'Theo dõi học lại', 'Kiểm tra chuyên cần hàng tuần.', '2025-02-15 11:00:00'),
(1, 1, 1, 'academic', 'Học tốt', 'GPA 7.75, đạt loại khá.', '2025-01-20 09:00:00'),
(3, 2, 1, 'academic', 'Xuất sắc', 'GPA 9.0, khen thưởng.', '2025-01-21 10:00:00'),
(4, 1, 1, 'personal', 'Sinh viên mới', 'Hỗ trợ hòa nhập lớp.', '2025-01-18 14:00:00');