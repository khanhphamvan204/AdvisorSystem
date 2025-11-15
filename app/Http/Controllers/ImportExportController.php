<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\Advisor;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportExportController extends Controller
{
    /**
     * Download template Excel files
     */
    public function downloadTemplates(Request $request)
    {
        try {
            $type = $request->query('type'); // classes, advisors, students

            switch ($type) {
                case 'classes':
                    return $this->generateClassTemplate();
                case 'advisors':
                    return $this->generateAdvisorTemplate();
                case 'students':
                    return $this->generateStudentTemplate();
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Loại template không hợp lệ'
                    ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Classes from Excel
     * Chỉ admin mới được phép
     */
    public function importClasses(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:xlsx,xls|max:5120'
            ], [
                'file.required' => 'Vui lòng chọn file',
                'file.mimes' => 'File phải có định dạng .xlsx hoặc .xls',
                'file.max' => 'File không được vượt quá 5MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Bỏ qua header
            array_shift($rows);

            $imported = 0;
            $errors = [];
            $userId = $request->current_user_id;
            $adminAdvisor = Advisor::find($userId);

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 vì bắt đầu từ dòng 2 (sau header)

                // Bỏ qua dòng trống
                if (empty(array_filter($row))) {
                    continue;
                }

                // Validate dữ liệu
                $className = trim($row[0] ?? '');
                $advisorCode = trim($row[1] ?? '');
                $facultyName = trim($row[2] ?? '');
                $description = trim($row[3] ?? '');

                if (empty($className)) {
                    $errors[] = "Dòng {$rowNumber}: Tên lớp không được để trống";
                    continue;
                }

                // Kiểm tra lớp đã tồn tại
                if (ClassModel::where('class_name', $className)->exists()) {
                    $errors[] = "Dòng {$rowNumber}: Lớp {$className} đã tồn tại";
                    continue;
                }

                // Tìm advisor
                $advisorId = null;
                if (!empty($advisorCode)) {
                    $advisor = Advisor::where('user_code', $advisorCode)->first();
                    if (!$advisor) {
                        $errors[] = "Dòng {$rowNumber}: Không tìm thấy cố vấn với mã {$advisorCode}";
                        continue;
                    }
                    $advisorId = $advisor->advisor_id;
                }

                // Tìm faculty
                $facultyId = null;
                if (!empty($facultyName)) {
                    $faculty = Unit::where('unit_name', $facultyName)->where('type', 'faculty')->first();
                    if (!$faculty) {
                        $errors[] = "Dòng {$rowNumber}: Không tìm thấy khoa {$facultyName}";
                        continue;
                    }
                    $facultyId = $faculty->unit_id;

                    // Kiểm tra admin chỉ import lớp cho khoa mình quản lý
                    if ($facultyId !== $adminAdvisor->unit_id) {
                        $errors[] = "Dòng {$rowNumber}: Bạn chỉ có thể import lớp cho khoa mình quản lý";
                        continue;
                    }
                }

                // Tạo lớp
                ClassModel::create([
                    'class_name' => $className,
                    'advisor_id' => $advisorId,
                    'faculty_id' => $facultyId,
                    'description' => $description
                ]);

                $imported++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import thành công {$imported} lớp",
                'data' => [
                    'imported' => $imported,
                    'errors' => $errors
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Advisors from Excel
     * Chỉ admin mới được phép
     */
    public function importAdvisors(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:xlsx,xls|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            array_shift($rows);

            $imported = 0;
            $errors = [];
            $defaultPassword = Hash::make('Password@123');

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                if (empty(array_filter($row))) {
                    continue;
                }

                $userCode = trim($row[0] ?? '');
                $fullName = trim($row[1] ?? '');
                $email = trim($row[2] ?? '');
                $phoneNumber = trim($row[3] ?? '');
                $unitName = trim($row[4] ?? '');
                $role = trim($row[5] ?? 'advisor');
                $password = trim($row[6] ?? '');

                // Validate required fields
                if (empty($userCode) || empty($fullName) || empty($email)) {
                    $errors[] = "Dòng {$rowNumber}: Thiếu thông tin bắt buộc";
                    continue;
                }

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Dòng {$rowNumber}: Email không hợp lệ";
                    continue;
                }

                // Check duplicate
                if (Advisor::where('user_code', $userCode)->exists()) {
                    $errors[] = "Dòng {$rowNumber}: Mã giảng viên {$userCode} đã tồn tại";
                    continue;
                }

                if (Advisor::where('email', $email)->exists()) {
                    $errors[] = "Dòng {$rowNumber}: Email {$email} đã tồn tại";
                    continue;
                }

                // Find unit
                $unitId = null;
                if (!empty($unitName)) {
                    $unit = Unit::where('unit_name', $unitName)->first();
                    if (!$unit) {
                        $errors[] = "Dòng {$rowNumber}: Không tìm thấy đơn vị {$unitName}";
                        continue;
                    }
                    $unitId = $unit->unit_id;
                }

                // Validate role
                if (!in_array($role, ['advisor', 'admin'])) {
                    $errors[] = "Dòng {$rowNumber}: Vai trò không hợp lệ (chỉ nhận 'advisor' hoặc 'admin')";
                    continue;
                }

                // Create advisor
                Advisor::create([
                    'user_code' => $userCode,
                    'full_name' => $fullName,
                    'email' => $email,
                    'password_hash' => empty($password) ? $defaultPassword : Hash::make($password),
                    'phone_number' => $phoneNumber,
                    'unit_id' => $unitId,
                    'role' => $role
                ]);

                $imported++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import thành công {$imported} giảng viên",
                'data' => [
                    'imported' => $imported,
                    'errors' => $errors
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Students from Excel
     * File Excel có nhiều sheet, mỗi sheet = 1 lớp
     * Chỉ admin mới được phép
     */
    public function importStudents(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:xlsx,xls|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());

            $imported = 0;
            $errors = [];
            $defaultPassword = Hash::make('Password@123');
            $userId = $request->current_user_id;
            $adminAdvisor = Advisor::find($userId);

            DB::beginTransaction();

            // Duyệt qua tất cả các sheet
            foreach ($spreadsheet->getAllSheets() as $worksheet) {
                $sheetName = $worksheet->getTitle();

                // Bỏ qua sheet "Hướng dẫn"
                if (strtolower($sheetName) === 'hướng dẫn' || strtolower($sheetName) === 'huong dan') {
                    continue;
                }

                // Tìm hoặc tạo lớp
                $class = ClassModel::where('class_name', $sheetName)->first();

                if (!$class) {
                    $errors[] = "Sheet '{$sheetName}': Không tìm thấy lớp {$sheetName}. Vui lòng tạo lớp trước.";
                    continue;
                }

                // Kiểm tra quyền: admin chỉ import sinh viên cho lớp thuộc khoa mình quản lý
                if ($class->faculty_id !== $adminAdvisor->unit_id) {
                    $errors[] = "Sheet '{$sheetName}': Bạn không có quyền import sinh viên cho lớp này";
                    continue;
                }

                $rows = $worksheet->toArray();
                array_shift($rows); // Bỏ header

                foreach ($rows as $index => $row) {
                    $rowNumber = $index + 2;

                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $userCode = trim($row[0] ?? '');
                    $fullName = trim($row[1] ?? '');
                    $email = trim($row[2] ?? '');
                    $phoneNumber = trim($row[3] ?? '');
                    $status = trim($row[4] ?? 'studying');
                    $password = trim($row[5] ?? '');

                    // Validate required fields
                    if (empty($userCode) || empty($fullName) || empty($email)) {
                        $errors[] = "Sheet '{$sheetName}' - Dòng {$rowNumber}: Thiếu thông tin bắt buộc";
                        continue;
                    }

                    // Validate email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Sheet '{$sheetName}' - Dòng {$rowNumber}: Email không hợp lệ";
                        continue;
                    }

                    // Check duplicate
                    if (Student::where('user_code', $userCode)->exists()) {
                        $errors[] = "Sheet '{$sheetName}' - Dòng {$rowNumber}: MSSV {$userCode} đã tồn tại";
                        continue;
                    }

                    if (Student::where('email', $email)->exists()) {
                        $errors[] = "Sheet '{$sheetName}' - Dòng {$rowNumber}: Email {$email} đã tồn tại";
                        continue;
                    }

                    // Validate status
                    if (!in_array($status, ['studying', 'graduated', 'dropped'])) {
                        $status = 'studying';
                    }

                    // Create student
                    Student::create([
                        'user_code' => $userCode,
                        'full_name' => $fullName,
                        'email' => $email,
                        'password_hash' => empty($password) ? $defaultPassword : Hash::make($password),
                        'phone_number' => $phoneNumber,
                        'class_id' => $class->class_id,
                        'status' => $status
                    ]);

                    $imported++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import thành công {$imported} sinh viên",
                'data' => [
                    'imported' => $imported,
                    'errors' => $errors
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Classes to Excel
     */
    public function exportClasses(Request $request)
    {
        try {
            $userId = $request->current_user_id;
            $role = $request->current_role;

            $query = ClassModel::with(['advisor', 'faculty', 'students']);

            // Admin chỉ xuất lớp thuộc khoa mình quản lý
            if ($role === 'admin') {
                $advisor = Advisor::find($userId);
                $query->where('faculty_id', $advisor->unit_id);
            } elseif ($role === 'advisor') {
                $query->where('advisor_id', $userId);
            }

            $classes = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header
            $sheet->setCellValue('A1', 'Tên lớp');
            $sheet->setCellValue('B1', 'Mã cố vấn');
            $sheet->setCellValue('C1', 'Tên cố vấn');
            $sheet->setCellValue('D1', 'Tên khoa');
            $sheet->setCellValue('E1', 'Số sinh viên');
            $sheet->setCellValue('F1', 'Mô tả');

            // Data
            $row = 2;
            foreach ($classes as $class) {
                $sheet->setCellValue('A' . $row, $class->class_name);
                $sheet->setCellValue('B' . $row, $class->advisor ? $class->advisor->user_code : '');
                $sheet->setCellValue('C' . $row, $class->advisor ? $class->advisor->full_name : '');
                $sheet->setCellValue('D' . $row, $class->faculty ? $class->faculty->unit_name : '');
                $sheet->setCellValue('E' . $row, $class->students->count());
                $sheet->setCellValue('F' . $row, $class->description);
                $row++;
            }

            // Auto size columns
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'Danh_sach_lop_' . date('YmdHis') . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Students by Class
     */
    public function exportStudents(Request $request, $classId)
    {
        try {
            $userId = $request->current_user_id;
            $role = $request->current_role;

            $class = ClassModel::with(['students', 'advisor', 'faculty'])->find($classId);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy lớp'
                ], 404);
            }

            // Check permission
            if ($role === 'admin') {
                $advisor = Advisor::find($userId);
                if ($class->faculty_id !== $advisor->unit_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xuất danh sách lớp này'
                    ], 403);
                }
            } elseif ($role === 'advisor') {
                if ($class->advisor_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có quyền xuất danh sách lớp này'
                    ], 403);
                }
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header
            $sheet->setCellValue('A1', 'MSSV');
            $sheet->setCellValue('B1', 'Họ và tên');
            $sheet->setCellValue('C1', 'Email');
            $sheet->setCellValue('D1', 'Số điện thoại');
            $sheet->setCellValue('E1', 'Trạng thái');

            // Data
            $row = 2;
            foreach ($class->students as $student) {
                $sheet->setCellValue('A' . $row, $student->user_code);
                $sheet->setCellValue('B' . $row, $student->full_name);
                $sheet->setCellValue('C' . $row, $student->email);
                $sheet->setCellValue('D' . $row, $student->phone_number);
                $sheet->setCellValue('E' . $row, $student->status);
                $row++;
            }

            // Auto size
            foreach (range('A', 'E') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'Danh_sach_SV_' . $class->class_name . '_' . date('YmdHis') . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods for generating templates
    private function generateClassTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'class_name');
        $sheet->setCellValue('B1', 'advisor_code');
        $sheet->setCellValue('C1', 'faculty_name');
        $sheet->setCellValue('D1', 'description');

        // Sample data
        $sheet->setCellValue('A2', 'DH21CNTT');
        $sheet->setCellValue('B2', 'GV001');
        $sheet->setCellValue('C2', 'Khoa Công nghệ Thông tin');
        $sheet->setCellValue('D2', 'Lớp đại học 2021 ngành CNTT');

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Template_Classes.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    private function generateAdvisorTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'user_code');
        $sheet->setCellValue('B1', 'full_name');
        $sheet->setCellValue('C1', 'email');
        $sheet->setCellValue('D1', 'phone_number');
        $sheet->setCellValue('E1', 'unit_name');
        $sheet->setCellValue('F1', 'role');
        $sheet->setCellValue('G1', 'password');

        // Sample data
        $sheet->setCellValue('A2', 'GV001');
        $sheet->setCellValue('B2', 'ThS. Trần Văn An');
        $sheet->setCellValue('C2', 'gv.an@school.edu.vn');
        $sheet->setCellValue('D2', '090111222');
        $sheet->setCellValue('E2', 'Khoa Công nghệ Thông tin');
        $sheet->setCellValue('F2', 'advisor');
        $sheet->setCellValue('G2', '');

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Template_Advisors.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    private function generateStudentTemplate()
    {
        $spreadsheet = new Spreadsheet();

        // Sheet 1: DH21CNTT
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('DH21CNTT');

        $sheet1->setCellValue('A1', 'user_code');
        $sheet1->setCellValue('B1', 'full_name');
        $sheet1->setCellValue('C1', 'email');
        $sheet1->setCellValue('D1', 'phone_number');
        $sheet1->setCellValue('E1', 'status');
        $sheet1->setCellValue('F1', 'password');

        $sheet1->setCellValue('A2', '210001');
        $sheet1->setCellValue('B2', 'Nguyễn Văn Hùng');
        $sheet1->setCellValue('C2', 'sv.hung@school.edu.vn');
        $sheet1->setCellValue('D2', '091122334');
        $sheet1->setCellValue('E2', 'studying');
        $sheet1->setCellValue('F2', '');

        // Sheet 2: DH22KT
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('DH22KT');

        $sheet2->setCellValue('A1', 'user_code');
        $sheet2->setCellValue('B1', 'full_name');
        $sheet2->setCellValue('C1', 'email');
        $sheet2->setCellValue('D1', 'phone_number');
        $sheet2->setCellValue('E1', 'status');
        $sheet2->setCellValue('F1', 'password');

        $sheet2->setCellValue('A2', '220001');
        $sheet2->setCellValue('B2', 'Lê Văn Dũng');
        $sheet2->setCellValue('C2', 'sv.dung@school.edu.vn');
        $sheet2->setCellValue('D2', '092233445');
        $sheet2->setCellValue('E2', 'studying');
        $sheet2->setCellValue('F2', '');

        // Auto size all sheets
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Template_Students.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}