<?php
// Disable output buffering and start fresh
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure no session conflicts
session_start();

// Strict access control
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'ADMIN') {
    ob_clean();
    error_log('Unauthorized PDF access attempt');
    header('Location: ../../login.php');
    exit();
}

// Include required libraries
require_once dirname(__FILE__) . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once('../../Config.php');

class PDF extends TCPDF
{
    // Page header
    public function Header()
    {
        // Logo (if needed)
        // $this->Image('path/to/logo.png', 10, 10, 30);

        // Title
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Laporan History Pembayaran', 0, 1, 'C');

        // Subtitle with date
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 7, 'Tanggal Cetak: ' . date('d-m-Y H:i:s'), 0, 1, 'C');

        // Separator line
        $this->SetLineWidth(0.5);
        $this->Line(10, 33, $this->getPageWidth() - 10, 33);
        $this->Ln(5);
    }

    // Page footer
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Initialize PDF document
$pdf = new PDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document properties
$pdf->SetCreator('Sistem Informasi Kompen');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Laporan History Pembayaran - ' . date('Y-m-d'));
$pdf->SetSubject('Daftar Pembayaran');

// Set margins
$pdf->SetMargins(10, 40, 10);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Add first page
$pdf->AddPage();

// Set font for table
$pdf->SetFont('helvetica', '', 9);

// Table headers
// Table headers
$header = [
    'No',
    'Payment ID',
    'NIM',
    'Nama',
    'Jumlah',
    'Metode',
    'Channel',
    'Status',
    'Tanggal Dibuat',
    'Tanggal Verifikasi'  // Index 9
];

// Sesuaikan lebar kolom (total tetap ~277mm untuk A4 Landscape)
$w = [
    8,      // No (index 0)
    45,     // Payment ID (index 1)
    25,     // NIM (index 2)
    30,     // Nama (index 3)
    25,     // Jumlah (index 4)
    20,     // Metode (index 5)
    20,     // Channel (index 6)
    20,     // Status (index 7)
    42,     // Tanggal Dibuat (index 8)
    42      // Tanggal Verifikasi (index 9)
];


// Header style
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128, 128, 128);
$pdf->SetLineWidth(0.3);
$pdf->SetFont('', 'B', 8);

// Print table header - single row untuk header yang pendek
foreach ($header as $i => $h) {
    switch ($h) {
        case 'Tanggal Dibuat':
            $pdf->Cell($w[$i], 8, 'Tanggal Dibuat', 1, 0, 'C', true);
            break;
        case 'Tanggal Verifikasi':
            $pdf->Cell($w[$i], 8, 'Tanggal Verifikasi', 1, 0, 'C', true);
            break;
        default:
            $pdf->Cell($w[$i], 8, $h, 1, 0, 'C', true);
    }
}
$pdf->Ln();

// Get data - Updated query to include VIRTUAL_ACCOUNT
$query = "SELECT 
    p.PAYMENT_ID, 
    p.NIM, 
    m.NAMA, 
    p.AMOUNT, 
    p.PAYMENT_METHOD, 
    p.PAYMENT_CHANNEL, 
    p.STATUS, 
    TO_CHAR(p.CREATED_AT, 'DD-MM-YYYY HH24:MI:SS') as CREATED_AT,
    TO_CHAR(p.VERIFIED_AT, 'DD-MM-YYYY HH24:MI:SS') as VERIFIED_AT
FROM TBL_PAYMENTS p
LEFT JOIN TBL_MAHASISWA m ON p.NIM = m.NIM
ORDER BY p.CREATED_AT DESC";

try {
    $db = getDB();
    $stmt = $db->prepare($query);
    $stmt->execute();

    // Reset styles for data
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(0);
    $pdf->SetFont('', '', 9);

    // Data rows
    $no = 1;
    $fill = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Check page break
        if ($pdf->GetY() > $pdf->getPageHeight() - 25) {
            $pdf->AddPage();

            // Reprint headers
            $pdf->SetFont('', 'B', 9);
            $pdf->SetFillColor(220, 220, 220);
            foreach ($header as $i => $h) {
                $pdf->Cell($w[$i], 8, $h, 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('', '', 9);
            $pdf->SetFillColor(255, 255, 255);
        }

        // Alternate fill colors
        $fill = !$fill;
        $fillColor = $fill ? 245 : 255;
        $pdf->SetFillColor($fillColor, $fillColor, $fillColor);

        // Print data row
        // Dalam loop while untuk data rows
        $pdf->SetFont('', '', 8); // Font lebih kecil untuk data
        $pdf->Cell($w[0], 7, $no++, 1, 0, 'C', true);
        $pdf->Cell($w[1], 7, $row['PAYMENT_ID'], 1, 0, 'L', true); // Left align untuk Payment ID
        $pdf->Cell($w[2], 7, $row['NIM'], 1, 0, 'C', true);

        // Handle nama tanpa truncate jika mungkin
        $pdf->Cell($w[3], 7, $row['NAMA'], 1, 0, 'L', true);

        $pdf->Cell($w[4], 7, 'Rp ' . number_format($row['AMOUNT'], 0,  ',', '.'), 1, 0, 'C', true);
        $pdf->Cell($w[5], 7, $row['PAYMENT_METHOD'], 1, 0, 'C', true);
        $pdf->Cell($w[6], 7, $row['PAYMENT_CHANNEL'], 1, 0, 'C', true);

        // Status dengan warna yang lebih baik
        $status = strtoupper($row['STATUS']);
        switch ($status) {
            case 'VERIFIED':
                $pdf->SetTextColor(0, 128, 0); // Green
                break;
            case 'FAILED':
                $pdf->SetTextColor(200, 0, 0); // Red
                break;
            case 'PENDING':
                $pdf->SetTextColor(200, 128, 0); // Orange
                break;
            default:
                $pdf->SetTextColor(0); // Black
        }
        $pdf->Cell($w[7], 7, $status, 1, 0, 'C', true);
        $pdf->SetTextColor(0);

        // Virtual Account dengan font monospace
        $pdf->SetFont('courier', '', 8);
        $pdf->SetFont('helvetica', '', 8);

        // Handle dates properly
        $created_at = $row['CREATED_AT'] ?: '-';
        if ($created_at != '-') {
            $created_date = DateTime::createFromFormat('d-m-Y H:i:s', $created_at);
            if ($created_date) {
                $created_at = $created_date->format('d-m-Y H:i');
            }
        }

        $verified_at = $row['VERIFIED_AT'] ?: '-';
        if ($verified_at != '-') {
            $verified_date = DateTime::createFromFormat('d-m-Y H:i:s', $verified_at);
            if ($verified_date) {
                $verified_at = $verified_date->format('d-m-Y H:i');
            }
        }

        $pdf->Cell($w[8], 7, $created_at, 1, 0, 'C', true);    // Index 8 untuk Tanggal Dibuat
        $pdf->Cell($w[9], 7, $verified_at, 1, 0, 'C', true);   // Index 9 untuk Tanggal Verifikasi
        $pdf->Ln();
    }

    // Add total records at bottom
    $pdf->Ln(5);
    $pdf->SetFont('', 'B', 9);
    $pdf->Cell(array_sum($w), 7, 'Total Records: ' . ($no - 1), 0, 0, 'R');

} catch (PDOException $e) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Database Error: ' . $e->getMessage(), 1, 1, 'C');
    error_log("Database error in payment report: " . $e->getMessage());
}

// Clean output buffer
ob_clean();

// Generate PDF
$pdf->Output('Laporan_Pembayaran_' . date('YmdHis') . '.pdf', 'D');

// End output buffering
while (ob_get_level()) {
    ob_end_flush();
}

exit();