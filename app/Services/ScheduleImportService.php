<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Log;

class ScheduleImportService
{
    /**
     * Trả về template Excel có sẵn từ storage
     */
    public function generateTemplate()
    {
        try {
            $templatePath = storage_path('app/templates/schedule_template.xls');

            if (!file_exists($templatePath)) {
                throw new \Exception("Template file not found at: {$templatePath}");
            }

            // Load template từ file có sẵn
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
            $spreadsheet = $reader->load($templatePath);

            return $spreadsheet;
        } catch (\Exception $e) {
            Log::error('Failed to load schedule template', [
                'error' => $e->getMessage(),
                'path' => $templatePath ?? 'unknown'
            ]);
            throw $e;
        }
    }
}
