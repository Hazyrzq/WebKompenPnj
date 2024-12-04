<?php
require_once dirname(__FILE__) . '/../../../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once('../../Config.php');

// Definisikan fungsi fetchData di awal sebelum digunakan
function fetchData()
{
    try {
        $pdo = getDB();
        $query = "SELECT p.*, m.nama as NAMA_MAHASISWA 
                  FROM tbl_pengajuan p 
                  JOIN tbl_mahasiswa m ON p.kode_user = m.nim
                  WHERE p.STATUS_APPROVAL1 = 'Approved'
                  ORDER BY p.TANGGAL_PENGAJUAN DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in fetchData: " . $e->getMessage());
        return [];
    }
}

class PDF extends TCPDF
{
    public function Header()
    {
    }

    public function Footer()
    {
    }
}

function drawTableHeader($pdf, $header, $widths)
{
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('', 'B');

    foreach ($header as $i => $h) {
        $pdf->Cell($widths[$i], 7, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();
}

function drawRow($pdf, $row, $widths, $no, $startY)
{
    $pdf->SetY($startY);
    $maxHeight = 7;
    $currentX = $pdf->GetX();

    // Draw all cells
    // No
    $pdf->Cell($widths[0], $maxHeight, $no, 1, 0, 'C');

    // ID Pengajuan
    $pdf->Cell($widths[1], $maxHeight, $row['ID_PENGAJUAN'], 1, 0, 'C');

    // Kode Kegiatan
    $pdf->Cell($widths[2], $maxHeight, $row['KODE_KEGIATAN'], 1, 0, 'L');

    // Nama Mahasiswa
    $pdf->Cell($widths[3], $maxHeight, $row['NAMA_MAHASISWA'], 1, 0, 'L');

    // Total
    $pdf->Cell($widths[4], $maxHeight, $row['TOTAL'], 1, 0, 'C');

    // Sisa
    $pdf->Cell($widths[5], $maxHeight, $row['SISA'], 1, 0, 'C');

    // Status Approval 1
    $pdf->Cell($widths[6], $maxHeight, $row['STATUS_APPROVAL1'], 1, 0, 'C');

    // Status Approval 2
    $pdf->Cell($widths[7], $maxHeight, $row['STATUS_APPROVAL2'], 1, 0, 'C');

    // Status Approval 3
    $pdf->Cell($widths[8], $maxHeight, $row['STATUS_APPROVAL3'], 1, 1, 'C');

    return $maxHeight;
}

function drawTableContent($pdf, $data, $widths, $header)
{
    $pdf->SetFont('', '');
    $pdf->SetFillColor(255, 255, 255);

    if (empty($data)) {
        $pdf->Cell(array_sum($widths), 10, 'Tidak ada data mahasiswa yang disetujui.', 1, 1, 'C');
        return;
    }

    $no = 1;
    foreach ($data as $row) {
        $startY = $pdf->GetY();
        if ($startY + 20 > $pdf->getPageHeight() - $pdf->getMargins()['bottom']) {
            $pdf->AddPage();
            drawTableHeader($pdf, $header, $widths);
            $startY = $pdf->GetY();
        }
        drawRow($pdf, $row, $widths, $no++, $startY);
    }
}

function generatePDF()
{
    $pdf = new PDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('SistemPengajuan');
    $pdf->SetAuthor('PLP');
    $pdf->SetTitle('Daftar Pengajuan Disetujui');

    $pdf->SetMargins(10, 10, 10);
    $pdf->SetHeaderMargin(10);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);

    $header = [
        'No',
        'ID Pengajuan',
        'Kode Kegiatan',
        'Nama Mahasiswa',
        'Total',
        'Sisa',
        'Status Approval 1',
        'Status Approval 2',
        'Status Approval 3'
    ];

    $widths = [10, 25, 50, 50, 20, 20, 32, 35, 35];

    drawTableHeader($pdf, $header, $widths);

    $data = fetchData();
    drawTableContent($pdf, $data, $widths, $header);

    $pdf->Output('daftar_pengajuan_disetujui.pdf', 'D');
}

// Execute PDF generation
generatePDF();
?>