<?php
require_once dirname(__FILE__) . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once('../../Config.php');

class PDF extends TCPDF {
    public function Header() {
        // Title
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Daftar Pekerjaan', 0, 1, 'C');
        
        // Tanggal Cetak
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 10, 'Tanggal Cetak: ' . date('d-m-Y H:i:s'), 0, 1, 'C');
        
        // Garis pemisah
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), $this->getPageWidth()-10, $this->GetY());
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Inisialisasi PDF
$pdf = new PDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set informasi dokumen
$pdf->SetCreator('Sikompen');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Daftar Pekerjaan');

// Set margin
$pdf->SetMargins(10, 35, 10);
$pdf->AddPage();

// Header tabel
$header = array(
    'No',
    'Kode',
    'Nama Pekerjaan',
    'Detail Pekerjaan',
    'Jam',
    'Limit',
    'ID PJ',
    'PJ'
);

// Sesuaikan lebar kolom (total 277 mm untuk A4 Landscape)
$w = array(
    10,  // No 
    25,  // Kode
    70,  // Nama Pekerjaan
    70,  // Detail Pekerjaan
    20,  // Jam
    20,  // Limit
    20,  // ID PJ
    32   // PJ
);

// Header tabel dengan warna latar
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0);
$pdf->SetFont('helvetica', 'B', 9);

foreach($header as $i => $h) {
    $pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// Reset style untuk isi tabel
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(255, 255, 255);

try {
    $db = getDB();
    $query = "SELECT * FROM tbl_pekerjaan ORDER BY id_PEKERJAAN";
    $stmt = $db->query($query);
    $no = 1;
    $fill = false;

    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $startY = $pdf->GetY();
        
        // Cek kebutuhan halaman baru
        if ($startY + 20 > $pdf->getPageHeight() - 20) {
            $pdf->AddPage();
            $startY = $pdf->GetY();
            
            // Cetak header lagi di halaman baru
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('helvetica', 'B', 9);
            foreach($header as $i => $h) {
                $pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 9);
        }

        // Set warna latar belakang selang-seling
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

        // Kolom dengan tinggi tetap
        $pdf->Cell($w[0], 6, $no++, 1, 0, 'C', $fill);
        $pdf->Cell($w[1], 6, $row['KODE_PEKERJAAN'], 1, 0, 'C', $fill);

        // Nama Pekerjaan
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell($w[2], 6, $row['NAMA_PEKERJAAN'], 1, 'L', $fill);
        $pdf->SetXY($x + $w[2], $y);

        // Detail Pekerjaan
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell($w[3], 6, $row['DETAIL_PEKERJAAN'], 1, 'L', $fill);
        $pdf->SetXY($x + $w[3], $y);

        // Kolom-kolom lainnya
        $pdf->Cell($w[4], 6, $row['JAM_PEKERJAAN'], 1, 0, 'C', $fill);
        $pdf->Cell($w[5], 6, $row['BATAS_PEKERJA'], 1, 0, 'C', $fill);
        $pdf->Cell($w[6], 6, $row['ID_PENANGGUNG_JAWAB'], 1, 0, 'C', $fill);
        $pdf->Cell($w[7], 6, $row['PENANGGUNG_JAWAB'], 1, 0, 'L', $fill);
        
        $pdf->Ln();
        $fill = !$fill;
    }

    // Total Records
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 7, 'Total Records: ' . ($no-1), 0, 0, 'R');

} catch(PDOException $e) {
    $pdf->Cell(0, 10, 'Database Error: ' . $e->getMessage(), 1, 1, 'C');
}

// Output PDF untuk didownload
$pdf->Output('Daftar_Pekerjaan_' . date('Y-m-d') . '.pdf', 'D');
?>