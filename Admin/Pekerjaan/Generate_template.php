<?php
require_once dirname(__FILE__) . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header rows
$headers = [
    'A1' => 'KODE_PEKERJAAN',
    'B1' => 'NAMA_PEKERJAAN',
    'C1' => 'JAM_PEKERJAAN',
    'D1' => 'DETAIL_PEKERJAAN',
    'E1' => 'BATAS_PEKERJA',
    'F1' => 'ID_PENANGGUNG_JAWAB',
    'G1' => 'PENANGGUNG_JAWAB'
];

// Add headers to sheet
foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Style the header row
$sheet->getStyle('A1:F1')->getFont()->setBold(true);
$sheet->getStyle('A1:F1')->getAlignment()->setHorizontal('center');

// Auto size columns
foreach(range('A','F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Set content type
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_pekerjaan.xlsx"');
header('Cache-Control: max-age=0');

// Create Excel file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>