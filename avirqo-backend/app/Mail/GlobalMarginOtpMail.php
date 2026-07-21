<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GlobalMarginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public array $changes,
        public array $catalogRows,
        public string $requestedBy,
        public \DateTimeInterface $expiresAt
    ) {}

    public function build()
    {
        return $this->subject('🔐 OTP Verification Required: Global Margin Changes ' . now()->format('F j, Y'))
            ->view('emails.global-margin-otp', [
                'otp' => $this->otp,
                'changes' => $this->changes,
                'catalogRows' => $this->catalogRows,
                'requestedBy' => $this->requestedBy,
                'expiresAt' => $this->expiresAt,
            ])
            ->attachData($this->buildExcel(), 'Avirqo-Global-Margin-Changes.xlsx', [
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }

    private function buildExcel(): string
    {
        $spreadsheet = new Spreadsheet();

        $catalogSheet = $spreadsheet->getActiveSheet();
        $catalogSheet->setTitle('Catalog');
        $headers = ['Brand Name', 'Product Name', 'Old Margin %', 'New Margin %', 'Old Blacklist', 'New Blacklist'];
        foreach ($headers as $index => $header) {
            $cell = chr(65 + $index) . '1';
            $catalogSheet->setCellValue($cell, $header);
            $catalogSheet->getStyle($cell)->getFont()->setBold(true);
            $catalogSheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D9E75');
            $catalogSheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
        }

        foreach ($this->catalogRows as $rowIndex => $row) {
            $sheetRow = $rowIndex + 2;
            $catalogSheet->setCellValue("A{$sheetRow}", $row['brand'] ?? '');
            $catalogSheet->setCellValue("B{$sheetRow}", $row['name'] ?? '');
            $catalogSheet->setCellValue("C{$sheetRow}", number_format((float) $row['old_margin_percentage'], 2, '.', ''));
            $catalogSheet->setCellValue("D{$sheetRow}", number_format((float) $row['new_margin_percentage'], 2, '.', ''));
            $catalogSheet->setCellValue("E{$sheetRow}", !empty($row['old_is_blacklisted']) ? 'Yes' : 'No');
            $catalogSheet->setCellValue("F{$sheetRow}", !empty($row['new_is_blacklisted']) ? 'Yes' : 'No');
        }

        foreach (range('A', 'F') as $column) {
            $catalogSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $changesSheet = $spreadsheet->createSheet();
        $changesSheet->setTitle('Changes');
        $changeHeaders = ['Brand Name', 'Product Name', 'Old Margin %', 'New Margin %', 'Old Blacklist', 'New Blacklist'];
        foreach ($changeHeaders as $index => $header) {
            $cell = chr(65 + $index) . '1';
            $changesSheet->setCellValue($cell, $header);
            $changesSheet->getStyle($cell)->getFont()->setBold(true);
            $changesSheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D9E75');
            $changesSheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
        }

        foreach ($this->changes as $rowIndex => $row) {
            $sheetRow = $rowIndex + 2;
            $changesSheet->setCellValue("A{$sheetRow}", $row['brand'] ?? '');
            $changesSheet->setCellValue("B{$sheetRow}", $row['name'] ?? '');
            $changesSheet->setCellValue("C{$sheetRow}", number_format((float) $row['old_margin_percentage'], 2, '.', ''));
            $changesSheet->setCellValue("D{$sheetRow}", number_format((float) $row['new_margin_percentage'], 2, '.', ''));
            $changesSheet->setCellValue("E{$sheetRow}", !empty($row['old_is_blacklisted']) ? 'Yes' : 'No');
            $changesSheet->setCellValue("F{$sheetRow}", !empty($row['new_is_blacklisted']) ? 'Yes' : 'No');
        }

        foreach (range('A', 'F') as $column) {
            $changesSheet->getColumnDimension($column)->setAutoSize(true);
        }

        if (empty($this->changes)) {
            $changesSheet->setCellValue('A2', 'No margin or blacklist changes were submitted.');
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);

        return $content;
    }
}
