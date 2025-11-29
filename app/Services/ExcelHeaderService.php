<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelHeaderService
{
    /**
     * Tạo spreadsheet mới với header đẹp từ đầu
     * Bao gồm: logo + thông tin trường + thông tin quốc gia
     * Font: Times New Roman 13
     * 
     * @return Spreadsheet
     */
    public function createWithProfessionalHeader(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 1. INSERT LOGO (A1:A3)
        $logoPath = public_path('images/logo/logo-huit.jpg');
        if (file_exists($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Logo HUIT');
            $drawing->setPath($logoPath);
            $drawing->setCoordinates('A1');
            $drawing->setHeight(80);
            $drawing->setWorksheet($sheet);
        }

        // 2. THÔNG TIN TRƯỜNG (bên trái - B1:D3)
        $sheet->setCellValue('B1', 'BỘ CÔNG THƯƠNG');
        $sheet->mergeCells('B1:D1');
        $sheet->setCellValue('B2', 'TRƯỜNG ĐẠI HỌC CÔNG THƯƠNG');
        $sheet->mergeCells('B2:D2');
        $sheet->setCellValue('B3', 'TP.HCM');
        $sheet->mergeCells('B3:D3');

        // Style cho thông tin trường - Times New Roman 13
        foreach (['B1', 'B2', 'B3'] as $cell) {
            $sheet->getStyle($cell)->applyFromArray([
                'font' => [
                    'name' => 'Times New Roman',
                    'bold' => true,
                    'size' => 13
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ]
            ]);
        }

        // 3. THÔNG TIN QUỐC GIA (bên phải - G1:I2)
        $sheet->setCellValue('G1', 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM');
        $sheet->mergeCells('G1:I1');
        $sheet->setCellValue('G2', 'Độc lập - Tự do - Hạnh phúc');
        $sheet->mergeCells('G2:I2');

        // Style cho thông tin quốc gia - Times New Roman 13 + wrap text
        $sheet->getStyle('G1')->applyFromArray([
            'font' => ['name' => 'Times New Roman', 'bold' => true, 'size' => 13],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true
            ]
        ]);

        $sheet->getStyle('G2')->applyFromArray([
            'font' => ['name' => 'Times New Roman', 'bold' => true, 'size' => 13, 'underline' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true
            ]
        ]);

        // Set row heights (tăng cho đủ chỗ)
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getRowDimension(3)->setRowHeight(25);

        // Set column widths để đủ chỗ hiển thị
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(30); // Độ rộng vừa phải cho text "CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM"
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(15);

        return $spreadsheet;
    }

    /**
     * Điền tiêu đề chính vào sheet
     * Font: Times New Roman 16 (màu đỏ)
     * 
     * @param Worksheet $sheet
     * @param string $title Tiêu đề
     * @param int $row Dòng để điền (mặc định: 5)
     * @param string $maxColumn Cột cuối để merge (mặc định: 'I')
     */
    public function fillTitle(
        Worksheet $sheet,
        string $title,
        int $row = 5,
        string $maxColumn = 'I'
    ): void {
        $sheet->setCellValue('A' . $row, $title);
        $sheet->mergeCells('A' . $row . ':' . $maxColumn . $row);

        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => [
                'name' => 'Times New Roman',
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'FF0000']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension($row)->setRowHeight(30);
    }

    /**
     * Điền thông tin chi tiết (key-value pairs)  
     * Căn GIỮA toàn bộ sheet
     * Font: Times New Roman 13
     * 
     * @param Worksheet $sheet
     * @param array $info Thông tin dạng ['label' => 'value']
     * @param int $startRow Dòng bắt đầu
     * @param string $maxColumn Cột cuối
     * @return int Dòng tiếp theo sau khi điền xong
     */
    public function fillInfoSection(
        Worksheet $sheet,
        array $info,
        int $startRow,
        string $maxColumn = 'I'
    ): int {
        $currentRow = $startRow;

        foreach ($info as $label => $value) {
            // Merge TOÀN BỘ dòng và center
            $sheet->setCellValue('A' . $currentRow, $label . ' ' . $value);
            $sheet->mergeCells('A' . $currentRow . ':' . $maxColumn . $currentRow);

            // CENTER alignment + Times New Roman 13
            $sheet->getStyle('A' . $currentRow)->applyFromArray([
                'font' => ['name' => 'Times New Roman', 'size' => 13],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);

            $currentRow++;
        }

        return $currentRow;
    }

    /**
     * Tạo header cho bảng dữ liệu
     * 
     * @param Worksheet $sheet
     * @param array $headers Danh sách header ['STT', 'MSSV', ...]
     * @param int $row Dòng header
     */
    public function createTableHeader(
        Worksheet $sheet,
        array $headers,
        int $row
    ): void {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }

        // Style cho header
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
            'font' => [
                'name' => 'Times New Roman',
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 13
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        $sheet->getRowDimension($row)->setRowHeight(25);
    }

    /**
     * Điền dữ liệu vào bảng
     * 
     * @param Worksheet $sheet
     * @param array $data Mảng dữ liệu (mỗi phần tử là 1 row)
     * @param int $startRow Dòng bắt đầu
     * @return int Dòng cuối cùng đã điền
     */
    public function fillTableData(
        Worksheet $sheet,
        array $data,
        int $startRow
    ): int {
        $currentRow = $startRow;

        foreach ($data as $rowData) {
            $col = 'A';
            foreach ($rowData as $value) {
                $sheet->setCellValue($col . $currentRow, $value);
                $col++;
            }

            // Style cho dòng dữ liệu - Times New Roman 13
            $lastCol = chr(ord('A') + count($rowData) - 1);
            $sheet->getStyle('A' . $currentRow . ':' . $lastCol . $currentRow)->applyFromArray([
                'font' => ['name' => 'Times New Roman', 'size' => 13],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ]
            ]);

            // Center align cho cột STT
            $sheet->getStyle('A' . $currentRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $currentRow++;
        }

        return $currentRow;
    }

    /**
     * Tự động điều chỉnh độ rộng cột
     * 
     * @param Worksheet $sheet
     * @param array $columns Danh sách cột ['A', 'B', 'C', ...]
     * @param array $specificWidths Độ rộng cụ thể ['C' => 30, ...] (optional)
     */
    public function autoFormatColumns(
        Worksheet $sheet,
        array $columns,
        array $specificWidths = []
    ): void {
        foreach ($columns as $col) {
            if (isset($specificWidths[$col])) {
                $sheet->getColumnDimension($col)->setWidth($specificWidths[$col]);
            } else {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
    }
}
