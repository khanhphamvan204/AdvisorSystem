-- Lệnh tạo CSDL và chọn CSDL
CREATE DATABASE db_advisorsystem
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_advisorsystem;

-- =================================================================
-- PHẦN 1: CẤU TRÚC LÕI (NGƯỜI DÙNG, ĐƠN VỊ, LỚP HỌC)
-- =================================================================

-- Bảng (1) Users (Bảng CHUNG)
CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Mã số (MSSV hoặc Mã GV)',
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL COMMENT 'Mật khẩu đã hash',
    phone_number VARCHAR(15) NULL,
    avatar_url VARCHAR(255) NULL COMMENT 'Đường dẫn ảnh đại diện',
    role ENUM('student', 'advisor') NOT NULL COMMENT 'Phân quyền',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (2) Units (Quản lý các Đơn vị: Khoa, Phòng ban)
CREATE TABLE Units (
    unit_id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(150) NOT NULL UNIQUE,
    type ENUM('faculty', 'department') NOT NULL COMMENT 'faculty = Khoa, department = Phòng ban',
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (3) Classes
CREATE TABLE Classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL UNIQUE,
    advisor_id INT NULL COMMENT 'CVHT của lớp (FK đến Advisors.user_id)',
    faculty_id INT NULL COMMENT 'Khóa ngoại đến Units (Khoa chủ quản của lớp)',
    description TEXT NULL,
    
    FOREIGN KEY (faculty_id) REFERENCES Units(unit_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (4) Students (Bảng RIÊNG cho Sinh viên)
CREATE TABLE Students (
    user_id INT PRIMARY KEY COMMENT 'Khóa chính, FK 1-1 đến Users.user_id',
    class_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'studying',
    
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (5) Advisors (Bảng RIÊNG cho Cố vấn học tập)
CREATE TABLE Advisors (
    user_id INT PRIMARY KEY COMMENT 'Khóa chính, FK 1-1 đến Users.user_id',
    unit_id INT NULL COMMENT 'Khóa ngoại đến Units (Đơn vị công tác của CVHT)',
    
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES Units(unit_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cập nhật khóa ngoại cho Classes (sau khi Advisors đã được tạo)
ALTER TABLE Classes 
ADD CONSTRAINT fk_class_advisor 
FOREIGN KEY (advisor_id) REFERENCES Advisors(user_id) ON DELETE SET NULL;


-- =================================================================
-- PHẦN 2: HỌC VỤ, HỌC KỲ VÀ ĐIỂM CHI TIẾT
-- =================================================================

-- Bảng (6) Semesters (Học kỳ)
CREATE TABLE Semesters (
    semester_id INT AUTO_INCREMENT PRIMARY KEY,
    semester_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    UNIQUE KEY uk_semester_year (semester_name, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (7) Courses (Môn học)
CREATE TABLE Courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(100) NOT NULL,
    credits TINYINT NOT NULL COMMENT 'Số tín chỉ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (8) Course_Grades (Điểm chi tiết của từng môn)
CREATE TABLE Course_Grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    semester_id INT NOT NULL,
    grade_value DECIMAL(4, 2) NULL COMMENT 'Điểm số môn học',
    status ENUM('passed', 'failed', 'studying') NOT NULL DEFAULT 'studying',
    
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT,
    UNIQUE KEY uk_student_course_semester (student_id, course_id, semester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (9) Semester_Reports (Báo cáo tổng kết kỳ)
CREATE TABLE Semester_Reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    semester_id INT NOT NULL,
    gpa DECIMAL(3, 2) NULL DEFAULT 0.00 COMMENT 'GPA học kỳ',
    credits_registered SMALLINT NOT NULL DEFAULT 0,
    credits_passed SMALLINT NOT NULL DEFAULT 0,
    
    -- Tổng kết điểm rèn luyện (tính từ Activities)
    training_point_summary INT NOT NULL DEFAULT 0 COMMENT 'Tổng điểm rèn luyện (ĐRL) của kỳ',
    -- Tổng kết điểm CTXH (tính từ Activities)
    social_point_summary INT NOT NULL DEFAULT 0 COMMENT 'Tổng điểm CTXH của kỳ',
    
    outcome VARCHAR(255) NULL COMMENT 'Kết quả: Học tiếp, Cảnh cáo, ...',
    
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT,
    UNIQUE KEY uk_student_semester (student_id, semester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (10) Academic_Warnings (Cảnh cáo học vụ)
CREATE TABLE Academic_Warnings (
    warning_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    advisor_id INT NOT NULL,
    semester_id INT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    advice TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =================================================================
-- PHẦN 3: KHIẾU NẠI ĐIỂM (ĐÃ ĐƠN GIẢN HÓA)
-- =================================================================

-- Bảng (11) Point_Feedbacks (Khiếu nại điểm)
-- *** ĐÃ SỬA: Bỏ log_id, chỉ cho khiếu nại tổng điểm của học kỳ ***
CREATE TABLE Point_Feedbacks (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    semester_id INT NOT NULL COMMENT 'Khiếu nại về tổng điểm của kỳ',
    feedback_content TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    
    -- Thông tin phản hồi của CVHT
    advisor_response TEXT NULL,
    advisor_id INT NULL COMMENT 'Khóa ngoại đến CVHT đã phản hồi',
    response_at DATETIME NULL COMMENT 'Thời gian CVHT phản hồi',
    
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =================================================================
-- PHẦN 4: HOẠT ĐỘNG VÀ ĐĂNG KÝ (NGUỒN ĐIỂM DUY NHẤT)
-- =================================================================

-- Bảng (12) Activities (Thông tin chung về sự kiện)
CREATE TABLE Activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    advisor_id INT NOT NULL COMMENT 'CVHT tạo/quản lý hoạt động này',
    organizer_unit_id INT NULL COMMENT 'Khóa ngoại đến Units (Đơn vị tổ chức)',
    title VARCHAR(255) NOT NULL,
    general_description TEXT NULL,
    location VARCHAR(255) NULL COMMENT 'Địa điểm tổ chức',
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'upcoming',
    
    FOREIGN KEY (advisor_id) REFERENCES Advisors(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (organizer_unit_id) REFERENCES Units(unit_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (13) Activity_Roles (Bảng "giá" điểm cho các vị trí)
CREATE TABLE Activity_Roles (
    activity_role_id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    requirements TEXT NULL,
    points_awarded INT NOT NULL DEFAULT 0 COMMENT 'Số điểm được cộng',
    point_type ENUM('ctxh', 'ren_luyen') NOT NULL COMMENT 'Hoạt động này cộng vào loại điểm nào',
    max_slots INT NULL,
    
    FOREIGN KEY (activity_id) REFERENCES Activities(activity_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (14) Activity_Registrations (Đăng ký vai trò)
CREATE TABLE Activity_Registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    activity_role_id INT NOT NULL,
    student_id INT NOT NULL,
    registration_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL DEFAULT 'registered' COMMENT 'registered, attended, absent...',
    
    FOREIGN KEY (activity_role_id) REFERENCES Activity_Roles(activity_role_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE,
    UNIQUE KEY uk_role_student (activity_role_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (15) Cancellation_Requests (Yêu cầu hủy đăng ký)
CREATE TABLE Cancellation_Requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (registration_id) REFERENCES Activity_Registrations(registration_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =================================================================
-- PHẦN 5: THÔNG BÁO VÀ PHẢN HỒI (Mô hình N-N)
-- =================================================================

-- Bảng (16) Notifications (Thông báo)
CREATE TABLE Notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    advisor_id INT NOT NULL COMMENT 'CVHT tạo thông báo',
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    link VARCHAR(2083) NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'general',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (advisor_id) REFERENCES Advisors(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (17) Notification_Class (Bảng trung gian N-N cho Thông báo - Lớp)
CREATE TABLE Notification_Class (
    notification_id INT NOT NULL,
    class_id INT NOT NULL,
    
    PRIMARY KEY (notification_id, class_id),
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (18) Notification_Attachments (File đính kèm cho Thông báo)
CREATE TABLE Notification_Attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL COMMENT 'Tên file gốc để hiển thị',
    
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (19) Notification_Recipients (Theo dõi đã đọc)
CREATE TABLE Notification_Recipients (
    recipient_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    student_id INT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    read_at DATETIME NULL,
    
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE,
    UNIQUE KEY uk_notification_student (notification_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (20) Notification_Responses (Phản hồi thông báo)
CREATE TABLE Notification_Responses (
    response_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    student_id INT NOT NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'resolved') NOT NULL DEFAULT 'pending',

    -- Thông tin phản hồi của CVHT
    advisor_response TEXT NULL,
    advisor_id INT NULL COMMENT 'Khóa ngoại đến CVHT đã phản hồi',
    response_at DATETIME NULL COMMENT 'Thời gian CVHT phản hồi',
    
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (notification_id) REFERENCES Notifications(notification_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =================================================================
-- PHẦN 6: HỌP LỚP VÀ BIÊN BẢN
-- =================================================================

-- Bảng (21) Meetings (Cuộc họp lớp)
CREATE TABLE Meetings (
    meeting_id INT AUTO_INCREMENT PRIMARY KEY,
    advisor_id INT NOT NULL,
    class_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    meeting_link VARCHAR(2083) NULL COMMENT 'Link họp (nếu online)',
    location VARCHAR(255) NULL COMMENT 'Địa điểm (nếu offline)',
    meeting_time DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
    minutes_file_path VARCHAR(255) NULL,
    
    FOREIGN KEY (advisor_id) REFERENCES Advisors(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (class_id) REFERENCES Classes(class_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (22) Meeting_Student (Điểm danh SV tham gia họp)
CREATE TABLE Meeting_Student (
    meeting_student_id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    student_id INT NOT NULL,
    attended BOOLEAN NOT NULL DEFAULT FALSE,
    
    FOREIGN KEY (meeting_id) REFERENCES Meetings(meeting_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE,
    UNIQUE KEY uk_meeting_student (meeting_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng (23) Meeting_Feedbacks (Phản hồi Biên bản họp)
CREATE TABLE Meeting_Feedbacks (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    student_id INT NOT NULL,
    feedback_content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (meeting_id) REFERENCES Meetings(meeting_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =================================================================
-- PHẦN 7: ĐỐI THOẠI 1-1 (CHAT)
-- =================================================================

-- Bảng (24) Messages (Tin nhắn đối thoại)
CREATE TABLE Messages (
    message_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    content TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    KEY idx_conversation (sender_id, receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- PHẦN 8: THEO DÕI SINH VIÊN CÁ BIỆT (BỔ SUNG MỚI)
-- =================================================================

-- Bảng (25) Student_Monitoring_Notes (Ghi chú theo dõi SV cá biệt)
CREATE TABLE Student_Monitoring_Notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL COMMENT 'Sinh viên được ghi chú',
    advisor_id INT NOT NULL COMMENT 'CVHT tạo ghi chú',
    semester_id INT NOT NULL COMMENT 'Ghi chú này thuộc học kỳ nào',
    
    category ENUM('academic', 'personal', 'attendance', 'other') NOT NULL DEFAULT 'other' COMMENT 'Phân loại: Học tập, Cá nhân, Chuyên cần...',
    title VARCHAR(255) NOT NULL COMMENT 'Tiêu đề ghi chú',
    content TEXT NOT NULL COMMENT 'Nội dung chi tiết',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES Students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES Advisors(user_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES Semesters(semester_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





-- Dùng một mật khẩu hash giả lập chung
SET @default_hash = '$2y$10$XDVj1Dr7HJgAdrq8NNpzeurpieW7HRYNK53LcjVOMeGKIMNiib2ky';

-- =================================================================
-- PHẦN 1: CẤU TRÚC LÕI (NGƯỜI DÙNG, ĐƠN VỊ, LỚP HỌC)
-- =================================================================

-- Bảng (2) Units
INSERT INTO Units (unit_id, unit_name, type, description) VALUES
(1, 'Khoa Công nghệ Thông tin', 'faculty', 'Quản lý các ngành thuộc lĩnh vực CNTT'),
(2, 'Khoa Kinh tế', 'faculty', 'Quản lý các ngành thuộc lĩnh vực Kinh tế và Quản trị'),
(3, 'Phòng Công tác Sinh viên', 'department', 'Quản lý các hoạt động ngoại khóa, điểm rèn luyện');

-- Bảng (1) Users (Thêm 3 CVHT và 4 Sinh viên)
INSERT INTO Users (user_id, user_code, full_name, email, password_hash, phone_number, role) VALUES
(1, 'GV001', 'ThS. Trần Văn An', 'gv.an@school.edu.vn', @default_hash, '090111222', 'advisor'),
(2, 'GV002', 'TS. Nguyễn Thị Bích', 'gv.bich@school.edu.vn', @default_hash, '090222333', 'advisor'),
(3, 'GV003', 'ThS. Lê Hoàng Cường', 'gv.cuong@school.edu.vn', @default_hash, '090333444', 'advisor'),
(4, '210001', 'Nguyễn Văn Hùng', 'sv.hung@school.edu.vn', @default_hash, '091122334', 'student'),
(5, '210002', 'Trần Thị Thu Cẩm', 'sv.cam@school.edu.vn', @default_hash, '091234567', 'student'),
(6, '220001', 'Lê Văn Dũng', 'sv.dung@school.edu.vn', @default_hash, '092233445', 'student'),
(7, '220002', 'Phạm Hoàng Yến', 'sv.yen@school.edu.vn', @default_hash, '093344556', 'student');

-- Bảng (5) Advisors (Gán CVHT vào các Đơn vị)
INSERT INTO Advisors (user_id, unit_id) VALUES
(1, 1), -- GV. An thuộc Khoa CNTT
(2, 2), -- GV. Bích thuộc Khoa Kinh tế
(3, 3); -- GV. Cường thuộc Phòng CTSV (để tổ chức sự kiện)

-- Bảng (3) Classes (Gán CVHT cho Lớp)
INSERT INTO Classes (class_id, class_name, advisor_id, faculty_id, description) VALUES
(1, 'DH21CNTT', 1, 1, 'Lớp Đại học 2021 ngành CNTT'),
(2, 'DH22KT', 2, 2, 'Lớp Đại học 2022 ngành Kinh tế');

-- Bảng (4) Students (Gán Sinh viên vào Lớp)
INSERT INTO Students (user_id, class_id, status) VALUES
(4, 1, 'studying'), -- SV Hùng vào lớp DH21CNTT
(5, 1, 'studying'), -- SV Cẩm vào lớp DH21CNTT
(6, 2, 'studying'), -- SV Dũng vào lớp DH22KT
(7, 2, 'studying'); -- SV Yến vào lớp DH22KT


-- =================================================================
-- PHẦN 2: HỌC VỤ, HỌC KỲ VÀ ĐIỂM CHI TIẾT
-- =================================================================

-- Bảng (6) Semesters
INSERT INTO Semesters (semester_id, semester_name, academic_year, start_date, end_date) VALUES
(1, 'Học kỳ 1', '2024-2025', '2024-09-05', '2025-01-15'),
(2, 'Học kỳ 2', '2024-2025', '2025-02-10', '2025-06-30'),
(3, 'Học kỳ 1', '2025-2026', '2025-09-08', '2026-01-18');

-- Bảng (7) Courses
INSERT INTO Courses (course_id, course_code, course_name, credits) VALUES
(1, 'IT001', 'Nhập môn Lập trình', 4),
(2, 'IT002', 'Cấu trúc dữ liệu và Giải thuật', 4),
(3, 'BA001', 'Kinh tế vi mô', 3),
(4, 'MK001', 'Nguyên lý Marketing', 3);

-- Bảng (8) Course_Grades
INSERT INTO Course_Grades (student_id, course_id, semester_id, grade_value, status) VALUES
-- SV Hùng (ID 4) - HK1 (ID 1)
(4, 1, 1, 8.5, 'passed'), -- Hùng, IT001, HK1
(4, 2, 1, 7.0, 'passed'), -- Hùng, IT002, HK1
-- SV Cẩm (ID 5) - HK1 (ID 1)
(5, 1, 1, 4.0, 'failed'), -- Cẩm, IT001, HK1 (Rớt)
(5, 2, 1, 5.0, 'passed'), -- Cẩm, IT002, HK1 (Đậu vớt)
-- SV Dũng (ID 6) - HK1 (ID 1)
(6, 3, 1, 9.0, 'passed'), -- Dũng, BA001, HK1
(6, 4, 1, 8.0, 'passed'), -- Dũng, MK001, HK1
-- SV Cẩm (ID 5) - HK2 (ID 2) - Học lại
(5, 1, 2, NULL, 'studying'); -- Cẩm đang học lại IT001 ở HK2

-- Bảng (9) Semester_Reports (Giả sử điểm CTXH/Rèn luyện đã được tổng hợp)
INSERT INTO Semester_Reports (student_id, semester_id, gpa, credits_registered, credits_passed, training_point_summary, social_point_summary, outcome) VALUES
-- HK1 (ID 1)
(4, 1, 7.75, 8, 8, 85, 15, 'Học tiếp'), -- Hùng
(5, 1, 4.50, 8, 4, 70, 5, 'Cảnh cáo học vụ mức 1 (GPA < 5.0)'), -- Cẩm
(6, 1, 8.50, 6, 6, 90, 20, 'Học tiếp (Khen thưởng)'), -- Dũng
(7, 1, 0.00, 0, 0, 50, 0, 'Học tiếp'); -- Yến (Giả sử SV chưa đăng ký môn nào)

-- Bảng (10) Academic_Warnings (CVHT tạo cảnh cáo cho SV Cẩm)
INSERT INTO Academic_Warnings (student_id, advisor_id, semester_id, title, content, advice) VALUES
(5, 1, 1, 'Quyết định Cảnh cáo học vụ HK1 2024-2025', 'Sinh viên Trần Thị Thu Cẩm (210002) có GPA HK1 2024-2025 là 4.50, số tín chỉ đạt: 4/8. Thuộc diện cảnh cáo học vụ mức 1.', 'Yêu cầu sinh viên đăng ký học lại môn IT001 và tập trung hơn vào việc học tập. Liên hệ CVHT nếu cần hỗ trợ.');


-- =================================================================
-- PHẦN 3: KHIẾU NẠI ĐIỂM
-- =================================================================

-- Bảng (11) Point_Feedbacks (SV Cẩm khiếu nại điểm rèn luyện)
INSERT INTO Point_Feedbacks (student_id, semester_id, feedback_content, attachment_path, status) VALUES
(5, 1, 'Em đã tham gia hoạt động "Ngày hội CLB" nhưng chưa được cộng 5 điểm rèn luyện. Em xin gửi kèm ảnh chụp màn hình minh chứng.', 'attachments/minhchung_cam_hk1.jpg', 'pending');


-- =================================================================
-- PHẦN 4: HOẠT ĐỘNG VÀ ĐĂNG KÝ
-- =================================================================

-- Bảng (12) Activities (CV Cường (P.CTSV) và CV An (Khoa CNTT) tạo HĐ)
INSERT INTO Activities (advisor_id, organizer_unit_id, title, general_description, location, start_time, end_time, status) VALUES
(3, 3, 'Hiến máu nhân đạo 2025', 'Hoạt động hiến máu cứu người', 'Sảnh A, Cơ sở 1', '2025-03-15 08:00:00', '2025-03-15 11:30:00', 'completed'),
(1, 1, 'Workshop: Giới thiệu về AI tạo sinh', 'Workshop chuyên đề cho SV Khoa CNTT', 'Phòng Hội thảo H.201', '2025-03-20 14:00:00', '2025-03-20 16:00:00', 'upcoming');

-- Bảng (13) Activity_Roles (Các "vị trí" cho 2 hoạt động trên)
INSERT INTO Activity_Roles (activity_id, role_name, points_awarded, point_type, max_slots) VALUES
(1, 'Tham gia hiến máu', 5, 'ctxh', 100), -- HĐ 1
(1, 'Tình nguyện viên hỗ trợ', 10, 'ctxh', 10), -- HĐ 1
(2, 'Người tham dự', 10, 'ren_luyen', 50); -- HĐ 2

-- Bảng (14) Activity_Registrations (SV đăng ký)
INSERT INTO Activity_Registrations (activity_role_id, student_id, status) VALUES
-- HĐ Hiến máu (Role 1)
(1, 4, 'attended'), -- Hùng đã tham gia (Role 1: Tham gia)
(1, 6, 'attended'), -- Dũng đã tham gia (Role 1: Tham gia)
-- HĐ Workshop (Role 3)
(3, 4, 'registered'), -- Hùng đăng ký (Role 3: Tham dự)
(3, 5, 'registered'); -- Cẩm đăng ký (Role 3: Tham dự)

-- Bảng (15) Cancellation_Requests (SV Cẩm xin hủy)
INSERT INTO Cancellation_Requests (registration_id, reason, status) VALUES
(4, 'Em bị trùng lịch thi giữa kỳ môn học lại. Em xin phép hủy ạ.', 'pending'); -- Cẩm (Reg ID 4) xin hủy


-- =================================================================
-- PHẦN 5: THÔNG BÁO VÀ PHẢN HỒI
-- =================================================================

-- Bảng (16) Notifications (CVHT tạo thông báo)
INSERT INTO Notifications (notification_id, advisor_id, title, summary, type) VALUES
(1, 1, 'Thông báo Họp lớp DH21CNTT tháng 3/2025', 'Triển khai công tác chuẩn bị cho HK2 và giải đáp thắc mắc.', 'general'),
(2, 2, 'Thông báo chung: Quy định về đăng ký môn học HK hè', 'Nhắc nhở sinh viên về các mốc thời gian quan trọng...', 'academic');

-- Bảng (17) Notification_Class (Gửi thông báo 1 cho lớp 1, TB 2 cho cả 2 lớp)
INSERT INTO Notification_Class (notification_id, class_id) VALUES
(1, 1), -- TB 1 -> Lớp DH21CNTT
(2, 1), -- TB 2 -> Lớp DH21CNTT
(2, 2); -- TB 2 -> Lớp DH22KT

-- Bảng (18) Notification_Attachments (Đính kèm file cho TB 2)
INSERT INTO Notification_Attachments (notification_id, file_path, file_name) VALUES
(2, 'attachments/quydinh_dkmh_he_2025.pdf', 'QuyDinh_DKMH_He_2025.pdf');

-- Bảng (19) Notification_Recipients (Ghi nhận đã đọc)
-- Giả sử hệ thống tự động thêm SV của các lớp nhận TB
-- TB 1 (Lớp 1: Hùng, Cẩm)
INSERT INTO Notification_Recipients (notification_id, student_id, is_read, read_at) VALUES
(1, 4, 1, '2025-03-10 09:00:00'), -- Hùng đã đọc TB 1
(1, 5, 0, NULL), -- Cẩm chưa đọc TB 1
-- TB 2 (Lớp 1: Hùng, Cẩm; Lớp 2: Dũng, Yến)
(2, 4, 1, '2025-03-11 10:00:00'), -- Hùng đã đọc TB 2
(2, 5, 1, '2025-03-11 11:30:00'), -- Cẩm đã đọc TB 2
(2, 6, 0, NULL), -- Dũng chưa đọc TB 2
(2, 7, 0, NULL); -- Yến chưa đọc TB 2

-- Bảng (20) Notification_Responses (SV Hùng phản hồi TB 1)
INSERT INTO Notification_Responses (notification_id, student_id, content, status) VALUES
(1, 4, 'Dạ em đã nhận thông báo. Em muốn hỏi là buổi họp có bắt buộc không ạ?', 'pending');


-- =================================================================
-- PHẦN 6: HỌP LỚP VÀ BIÊN BẢN
-- =================================================================

-- Bảng (21) Meetings (CV An (ID 1) tạo cuộc họp cho Lớp 1)
INSERT INTO Meetings (advisor_id, class_id, title, location, meeting_time, status) VALUES
(1, 1, 'Họp lớp DH21CNTT tháng 3/2025 (Triển khai HK2)', 'Phòng B.101', '2025-03-15 10:00:00', 'scheduled');

-- Bảng (22) Meeting_Student (Điểm danh Hùng và Cẩm cho cuộc họp)
INSERT INTO Meeting_Student (meeting_id, student_id, attended) VALUES
(1, 4, 0), -- Hùng (chưa điểm danh)
(1, 5, 0); -- Cẩm (chưa điểm danh)

-- Bảng (23) Meeting_Feedbacks (Chưa có feedback vì họp chưa diễn ra)
-- (Không chèn dữ liệu mẫu)


-- =================================================================
-- PHẦN 7: ĐỐI THOẠI 1-1 (CHAT)
-- =================================================================

-- Bảng (24) Messages (SV Cẩm (ID 5) chat với CV An (ID 1))
INSERT INTO Messages (sender_id, receiver_id, content, is_read) VALUES
(5, 1, 'Thầy ơi, em bị cảnh cáo học vụ HK1, giờ em phải làm sao ạ?', 0), -- Cẩm gửi CV An (chưa đọc)
(1, 5, 'Chào Cẩm, em cần đăng ký học lại ngay môn IT001 trong HK2 này nhé. Cố gắng đạt điểm cao để cải thiện GPA.', 1), -- CV An trả lời Cẩm (đã đọc)
(5, 1, 'Dạ em đăng ký học lại rồi ạ. Em cảm ơn thầy.', 0); -- Cẩm trả lời CV An (chưa đọc)


-- =================================================================
-- PHẦN 8: THEO DÕI SINH VIÊN CÁ BIỆT
-- =================================================================

-- Bảng (25) Student_Monitoring_Notes (CV An (ID 1) ghi chú SV Cẩm (ID 5) trong HK1)
INSERT INTO Student_Monitoring_Notes (student_id, advisor_id, semester_id, category, title, content) VALUES
(5, 1, 1, 'academic', 'Theo dõi SV Cẩm (210002) - Rớt môn IT001', 'SV có điểm giữa kỳ thấp (3.0), vắng 2 buổi. Đã liên hệ nhắc nhở.'),
(5, 1, 2, 'attendance', 'Theo dõi chuyên cần HK2 (môn học lại)', 'Kiểm tra chuyên cần môn IT001 (học lại) của SV Cẩm hàng tuần.');


-- =================================================================
-- TIẾP TỤC CHÈN THÊM DỮ LIỆU (LẦN 2)
-- =================================================================

-- Dùng lại mật khẩu hash giả lập chung
SET @default_hash = '$2y$10$XDVj1Dr7HJgAdrq8NNpzeurpieW7HRYNK53LcjVOMeGKIMNiib2ky';

-- =================================================================
-- PHẦN 1: MỞ RỘNG CẤU TRÚC LÕI
-- =================================================================

-- Bảng (2) Units: Thêm 2 đơn vị mới
INSERT INTO Units (unit_id, unit_name, type, description) VALUES
(4, 'Khoa Ngôn ngữ Anh', 'faculty', 'Quản lý các ngành thuộc lĩnh vực Ngôn ngữ'),
(5, 'Phòng Tài chính - Kế toán', 'department', 'Quản lý các vấn đề học phí');

-- Bảng (1) Users: Thêm 1 CVHT và 4 SV mới (Bắt đầu ID từ 8)
INSERT INTO Users (user_id, user_code, full_name, email, password_hash, phone_number, role) VALUES
(8, 'GV004', 'ThS. Đỗ Yến Nhi', 'gv.nhi@school.edu.vn', @default_hash, '090444555', 'advisor'),
(9, '210003', 'Phan Thanh Bình', 'sv.binh@school.edu.vn', @default_hash, '094455667', 'student'),
(10, '210004', 'Võ Thị Kim Anh', 'sv.anh@school.edu.vn', @default_hash, '095566778', 'student'),
(11, '210005', 'Trịnh Bảo Quốc', 'sv.quoc@school.edu.vn', @default_hash, '096677889', 'student'),
(12, '210006', 'Mai Lan Chi', 'sv.chi@school.edu.vn', @default_hash, '097788990', 'student');

-- Bảng (5) Advisors: Gán CVHT mới vào Khoa mới
INSERT INTO Advisors (user_id, unit_id) VALUES
(8, 4); -- GV. Nhi thuộc Khoa Ngôn ngữ Anh

-- Bảng (3) Classes: Thêm lớp mới
INSERT INTO Classes (class_id, class_name, advisor_id, faculty_id, description) VALUES
(3, 'DH21NNA', 8, 4, 'Lớp Đại học 2021 ngành Ngôn ngữ Anh');

-- Bảng (4) Students: Gán SV mới vào lớp
INSERT INTO Students (user_id, class_id, status) VALUES
(9, 1, 'studying'),  -- SV Bình vào lớp DH21CNTT (Lớp 1)
(10, 1, 'studying'), -- SV Anh vào lớp DH21CNTT (Lớp 1)
(11, 3, 'studying'), -- SV Quốc vào lớp DH21NNA (Lớp 3)
(12, 3, 'studying'); -- SV Chi vào lớp DH21NNA (Lớp 3)


-- =================================================================
-- PHẦN 2: MỞ RỘNG DỮ LIỆU HỌC VỤ
-- =================================================================

-- Bảng (7) Courses: Thêm môn cho khoa mới
INSERT INTO Courses (course_id, course_code, course_name, credits) VALUES
(5, 'EN001', 'Nghe - Nói 1', 3),
(6, 'EN002', 'Đọc - Viết 1', 3);

-- Bảng (8) Course_Grades: Thêm điểm cho HK1 (SV mới) và HK2 (SV cũ + mới)
INSERT INTO Course_Grades (student_id, course_id, semester_id, grade_value, status) VALUES
-- SV mới (ID 9, 10) - HK1 (ID 1) - Lớp 1
(9, 1, 1, 9.0, 'passed'), -- Bình, IT001, HK1
(10, 2, 1, 6.5, 'passed'), -- Anh, IT002, HK1
-- SV mới (ID 11, 12) - HK1 (ID 1) - Lớp 3
(11, 5, 1, 3.5, 'failed'), -- Quốc, EN001, HK1 (Rớt)
(12, 5, 1, 8.0, 'passed'), -- Chi, EN001, HK1
(12, 6, 1, 7.5, 'passed'), -- Chi, EN002, HK1
-- Thêm điểm HK2 (ID 2) cho SV cũ
(4, 2, 2, 8.0, 'passed'), -- Hùng, IT002, HK2
(6, 4, 2, 7.0, 'passed'), -- Dũng, MK001, HK2
(7, 3, 2, NULL, 'studying'), -- Yến, BA001, HK2 (Đang học)
-- Thêm điểm HK2 (ID 2) cho SV mới
(11, 5, 2, NULL, 'studying'); -- Quốc, EN001, HK2 (Học lại)

-- Bảng (9) Semester_Reports: Thêm báo cáo cho SV mới HK1, và tất cả SV cho HK2
INSERT INTO Semester_Reports (student_id, semester_id, gpa, credits_registered, credits_passed, training_point_summary, social_point_summary, outcome) VALUES
-- Thêm SV mới cho HK1 (ID 1)
(9, 1, 9.00, 4, 4, 90, 10, 'Học tiếp (Khen thưởng)'), -- Bình
(10, 1, 6.50, 4, 4, 75, 5, 'Học tiếp'), -- Anh
(11, 1, 3.50, 3, 0, 60, 0, 'Cảnh cáo học vụ mức 1'), -- Quốc
(12, 1, 7.75, 6, 6, 80, 10, 'Học tiếp'), -- Chi
-- Thêm báo cáo HK2 (ID 2) cho tất cả SV
(4, 2, 8.00, 4, 4, 80, 0, 'Học tiếp'), -- Hùng
(5, 2, 0.00, 4, 0, 70, 0, 'Chưa có điểm (Học lại)'), -- Cẩm
(6, 2, 7.00, 3, 3, 85, 0, 'Học tiếp'), -- Dũng
(7, 2, 0.00, 3, 0, 75, 0, 'Chưa có điểm'), -- Yến
(9, 2, 0.00, 0, 0, 80, 0, 'Học tiếp'), -- Bình
(10, 2, 0.00, 0, 0, 75, 0, 'Học tiếp'), -- Anh
(11, 2, 0.00, 3, 0, 60, 0, 'Chưa có điểm (Học lại)'), -- Quốc
(12, 2, 0.00, 0, 0, 80, 0, 'Học tiếp'); -- Chi

-- Bảng (10) Academic_Warnings: Thêm cảnh cáo cho SV Quốc
INSERT INTO Academic_Warnings (student_id, advisor_id, semester_id, title, content, advice) VALUES
(11, 8, 1, 'Quyết định Cảnh cáo học vụ HK1 2024-2025', 'Sinh viên Trịnh Bảo Quốc (210005) có GPA HK1 2024-2025 là 3.50, số tín chỉ đạt: 0/3. Thuộc diện cảnh cáo học vụ mức 1.', 'Yêu cầu SV đăng ký học lại môn EN001 ngay HK2. Liên hệ CVHT (Cô Nhi) để được hỗ trợ.');


-- =================================================================
-- PHẦN 3: XỬ LÝ KHIẾU NẠI
-- =================================================================

-- Xử lý Khiếu nại (ID 1) của SV Cẩm
UPDATE Point_Feedbacks
SET status = 'approved', 
    advisor_response = 'Đã kiểm tra và cộng bổ sung 5 điểm rèn luyện cho em (Hoạt động Ngày hội CLB).', 
    advisor_id = 1, 
    response_at = '2025-03-12 10:00:00'
WHERE feedback_id = 1;

-- Thêm Khiếu nại (ID 2)
INSERT INTO Point_Feedbacks (student_id, semester_id, feedback_content, status) VALUES
(6, 1, 'Em tham gia Tình nguyện viên cho HĐ Hiến máu (10đ) nhưng xem báo cáo HK1 mới thấy cộng 5đ CTXH (như người tham gia). Mong thầy cô xem xét.', 'pending');


-- =================================================================
-- PHẦN 4: THÊM HOẠT ĐỘNG
-- =================================================================

-- Bảng (12) Activities: Thêm 2 hoạt động mới (ID 3, 4)
INSERT INTO Activities (activity_id, advisor_id, organizer_unit_id, title, general_description, location, start_time, end_time, status) VALUES
(3, 2, 2, 'Cuộc thi Ý tưởng Khởi nghiệp 2025', 'Cuộc thi dành cho SV Khoa Kinh tế', 'Hội trường B', '2025-04-10 08:00:00', '2025-04-10 17:00:00', 'upcoming'),
(4, 8, 4, 'CLB Tiếng Anh: Vòng Bán kết', 'Sự kiện thường niên của Khoa NNA', 'Phòng C.101', '2025-04-05 18:00:00', '2025-04-05 20:00:00', 'completed');

-- Cập nhật trạng thái HĐ 2 (Workshop AI) thành 'completed'
UPDATE Activities SET status = 'completed' WHERE activity_id = 2;

-- Bảng (13) Activity_Roles: Thêm vai trò cho 2 HĐ mới (ID 4, 5)
INSERT INTO Activity_Roles (activity_id, role_name, points_awarded, point_type, max_slots) VALUES
(3, 'Đội thi Vòng Chung kết', 20, 'ren_luyen', 50),
(4, 'Khán giả cổ vũ', 5, 'ren_luyen', 100);

-- Bảng (14) Activity_Registrations:
-- Cập nhật HĐ 2 (Role 3): Hùng (Reg ID 3) đã tham gia
UPDATE Activity_Registrations SET status = 'attended' WHERE registration_id = 3;
-- Cập nhật HĐ 2 (Role 3): Cẩm (Reg ID 4) - đã được duyệt hủy (xem P15)
UPDATE Activity_Registrations SET status = 'cancelled' WHERE registration_id = 4;
-- Thêm đăng ký mới cho HĐ 3 và 4
INSERT INTO Activity_Registrations (activity_role_id, student_id, status) VALUES
(4, 6, 'registered'), -- Dũng (Lớp KT) đăng ký HĐ 3 (Role 4: Đội thi)
(4, 7, 'registered'), -- Yến (Lớp KT) đăng ký HĐ 3 (Role 4: Đội thi)
(5, 11, 'attended'), -- Quốc (Lớp NNA) tham gia HĐ 4 (Role 5: Khán giả)
(5, 12, 'attended'); -- Chi (Lớp NNA) tham gia HĐ 4 (Role 5: Khán giả)

-- Bảng (15) Cancellation_Requests:
-- Xử lý yêu cầu (ID 1) của SV Cẩm
UPDATE Cancellation_Requests SET status = 'approved' WHERE request_id = 1;


-- =================================================================
-- PHẦN 5: THÊM THÔNG BÁO VÀ PHẢN HỒI
-- =================================================================

-- Bảng (16) Notifications: Thêm 2 thông báo mới (ID 3, 4)
INSERT INTO Notifications (notification_id, advisor_id, title, summary, type, created_at) VALUES
(3, 1, 'Thông báo khẩn: Cập nhật quy chế thi cử HK2/2024-2025', 'Nhà trường ban hành quy chế mới về việc sử dụng tài liệu trong phòng thi. Chi tiết xem file đính kèm.', 'academic', '2025-03-12 11:00:00'),
(4, 8, 'Thông báo Họp lớp DH21NNA tháng 3/2025', 'Lịch họp lớp lần 2, đề nghị SV tham gia đầy đủ.', 'general', '2025-03-12 14:00:00');

-- Bảng (17) Notification_Class (Gửi TB 3 cho Lớp 1, TB 4 cho Lớp 3)
INSERT INTO Notification_Class (notification_id, class_id) VALUES
(3, 1), -- TB 3 -> Lớp DH21CNTT
(4, 3); -- TB 4 -> Lớp DH21NNA

-- Bảng (18) Notification_Attachments (Đính kèm file cho TB 3)
INSERT INTO Notification_Attachments (notification_id, file_path, file_name) VALUES
(3, 'attachments/QuyCheThiCuMoi_HK2_2025.pdf', 'QuyCheThiCuMoi_HK2_2025.pdf');

-- Bảng (19) Notification_Recipients (Thêm SV mới cho Lớp 1 (TB 1, 2) và tạo recipients cho TB 3, 4)
-- (Giả sử có 1 trigger tự động thêm SV mới của Lớp 1 (Bình, Anh) vào TB 1 và 2)
INSERT INTO Notification_Recipients (notification_id, student_id, is_read, read_at) VALUES
(1, 9, 0, NULL), -- Bình (SV mới Lớp 1) -> TB 1
(1, 10, 0, NULL), -- Anh (SV mới Lớp 1) -> TB 1
(2, 9, 0, NULL), -- Bình (SV mới Lớp 1) -> TB 2
(2, 10, 1, '2025-03-12 09:00:00'), -- Anh (SV mới Lớp 1) -> TB 2 (Đã đọc)
-- Thêm recipients cho TB 3 (Lớp 1: Hùng, Cẩm, Bình, Anh)
(3, 4, 1, '2025-03-12 13:00:00'), -- Hùng
(3, 5, 1, '2025-03-12 14:00:00'), -- Cẩm
(3, 9, 0, NULL), -- Bình
(3, 10, 0, NULL), -- Anh
-- Thêm recipients cho TB 4 (Lớp 3: Quốc, Chi)
(4, 11, 1, '2025-03-12 15:00:00'), -- Quốc
(4, 12, 0, NULL); -- Chi

-- Bảng (20) Notification_Responses
-- Xử lý Phản hồi (ID 1) của SV Hùng
UPDATE Notification_Responses
SET status = 'resolved', 
    advisor_response = 'Chào Hùng, buổi họp này rất quan trọng để phổ biến thông tin HK2, yêu cầu các em tham gia đầy đủ và đúng giờ nhé.', 
    advisor_id = 1, 
    response_at = '2025-03-12 10:15:00'
WHERE response_id = 1;
-- Thêm Phản hồi (ID 2)
INSERT INTO Notification_Responses (notification_id, student_id, content, status) VALUES
(4, 11, 'Dạ em cảm ơn cô. Em sẽ tham gia ạ.', 'resolved'); -- SV Quốc (Lớp 3) phản hồi TB 4 (đã giải quyết)


-- =================================================================
-- PHẦN 6: CẬP NHẬT VÀ THÊM CUỘC HỌP
-- =================================================================

-- Bảng (21) Meetings
-- Cập nhật Cuộc họp (ID 1) thành 'completed' và thêm biên bản
UPDATE Meetings 
SET status = 'completed', 
    minutes_file_path = 'meetings/bienban_hop_lop_dh21cntt_t3_2025.pdf' 
WHERE meeting_id = 1;
-- Thêm Cuộc họp (ID 2) cho Lớp 3
INSERT INTO Meetings (advisor_id, class_id, title, location, meeting_time, status) VALUES
(8, 3, 'Họp lớp DH21NNA tháng 3/2025 (Triển khai HK2)', 'Phòng C.202', '2025-03-18 10:00:00', 'scheduled');

-- Bảng (22) Meeting_Student
-- Cập nhật điểm danh Cuộc họp (ID 1)
UPDATE Meeting_Student SET attended = 1 WHERE meeting_student_id = 1; -- Hùng (ID 4) tham gia
UPDATE Meeting_Student SET attended = 0 WHERE meeting_student_id = 2; -- Cẩm (ID 5) vắng
-- (Giả sử trigger thêm SV mới (Bình, Anh) vào cuộc họp 1)
INSERT INTO Meeting_Student (meeting_id, student_id, attended) VALUES
(1, 9, 1), -- Bình (SV mới Lớp 1) tham gia
(1, 10, 1), -- Anh (SV mới Lớp 1) tham gia
-- Thêm SV cho Cuộc họp (ID 2) (Lớp 3: Quốc, Chi)
(2, 11, 0), -- Quốc
(2, 12, 0); -- Chi

-- Bảng (23) Meeting_Feedbacks: Thêm 1 feedback cho cuộc họp 1
INSERT INTO Meeting_Feedbacks (meeting_id, student_id, feedback_content, created_at) VALUES
(1, 9, 'Em thấy biên bản họp ghi thiếu phần ý kiến của em về quỹ lớp ạ. Mong thầy điều chỉnh.', '2025-03-16 08:00:00');


-- =================================================================
-- PHẦN 7: THÊM HỘI THOẠI CHAT
-- =================================================================

-- Bảng (24) Messages: Thêm 1 cuộc hội thoại mới (SV Dũng (ID 6) chat với CV Bích (ID 2))
INSERT INTO Messages (sender_id, receiver_id, content, is_read, sent_at) VALUES
(6, 2, 'Cô ơi, em muốn hỏi về cuộc thi Ý tưởng Khởi nghiệp. Em và bạn Yến đăng ký tham gia, không biết thể lệ cụ thể thế nào ạ?', 0, '2025-03-12 16:00:00'),
(2, 6, 'Chào Dũng, cô đã gửi thông báo chung cho lớp Kinh tế rồi, nhưng em có thể xem chi tiết ở link này nhé: [link_the_le]', 0, '2025-03-12 16:05:00');


-- =================================================================
-- PHẦN 8: THÊM GHI CHÚ THEO DÕI
-- =================================================================

-- Bảng (25) Student_Monitoring_Notes: Thêm ghi chú cho SV Quốc (ID 11)
INSERT INTO Student_Monitoring_Notes (student_id, advisor_id, semester_id, category, title, content, created_at) VALUES
(11, 8, 1, 'academic', 'Theo dõi SV Quốc (210005) - Rớt môn EN001', 'SV có vẻ gặp khó khăn trong kỹ năng Nghe. Đã hẹn gặp riêng để trao đổi.', '2025-01-20 10:00:00'),
(11, 8, 2, 'personal', 'SV Quốc chia sẻ có vấn đề cá nhân', 'Gia đình SV Quốc gặp khó khăn, em ấy phải đi làm thêm nhiều. Cần quan tâm và hỗ trợ tâm lý.', '2025-03-01 14:30:00');