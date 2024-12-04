<?php
require_once dirname(__FILE__) . '/../../../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once('../../Config.php');
class PDF extends TCPDF
{
    // Page header
    public function Header()
    {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Daftar Setup Bertugas', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'Generated: ' . date('d-m-Y H:i:s'), 0, 1, 'C');
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

// Inisialisasi dokumen PDF
$pdf = new PDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set informasi dokumen
$pdf->SetCreator('DesignKompen');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Daftar Setup Bertugas');

// Set margin
$pdf->SetMargins(10, 15, 10);

// Tambah halaman
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Header tabel
$header = array('No', 'NIP', 'Nama User', 'Role', 'Tanggal Bertugas');

// Lebar kolom (total = 275 untuk A4 Landscape)
$w = array(15, 50, 80, 50, 80);

// Buat header tabel
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);
$pdf->SetFont('', 'B');

foreach ($header as $i => $h) {
    $pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// Isi tabel
$pdf->SetFont('', '');
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0);

try {
    // Get data from database
    $query = "SELECT 
                b.NIP,
                u.NAMA_USER,
                u.ROLE,
                TO_CHAR(b.TANGGAL_BERTUGAS, 'DD-MM-YYYY HH24:MI') as TANGGAL_BERTUGAS
              FROM SETUP_BERTUGAS b
              JOIN TBL_USER u ON b.NIP = u.NIP
              ORDER BY b.TANGGAL_BERTUGAS DESC";

    $stmt = executeQuery($query);
    $bertugasData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bertugasData)) {
        $pdf->Cell(array_sum($w), 10, 'Tidak ada data setup bertugas.', 1, 1, 'C');
    } else {
        $no = 1;
        foreach ($bertugasData as $row) {
            // Set warna merah untuk role ADMIN
            if ($row['ROLE'] === 'ADMIN') {
                $pdf->SetTextColor(255, 0, 0);
            } else {
                $pdf->SetTextColor(0);
            }

            $pdf->Cell($w[0], 6, $no++, 1, 0, 'C');
            $pdf->Cell($w[1], 6, $row['NIP'], 1, 0, 'L');

            // Untuk nama user, gunakan MultiCell jika teks panjang
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->MultiCell($w[2], 6, $row['NAMA_USER'], 1, 'L');
            $pdf->SetXY($x + $w[2], $y);

            $pdf->Cell($w[3], 6, $row['ROLE'], 1, 0, 'C');
            $pdf->Cell($w[4], 6, $row['TANGGAL_BERTUGAS'], 1, 0, 'C');
            $pdf->Ln();
        }
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Output PDF
$filename = 'daftar_setup_bertugas_' . date('YmdHis') . '.pdf';
$pdf->Output($filename, 'D');
?>