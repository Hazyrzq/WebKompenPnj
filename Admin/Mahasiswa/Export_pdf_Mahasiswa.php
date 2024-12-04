<?php
require_once dirname(__FILE__) . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once('../../Config.php');
class PDF extends TCPDF {
    public function Header() {
        // Title
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Daftar Mahasiswa', 0, 1, 'C');
        
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
$pdf->SetTitle('Daftar Mahasiswa');

// Set margin
$pdf->SetMargins(10, 35, 10);
$pdf->AddPage();

// Header tabel
$header = array(
    'No', 
    'NIM', 
    'Nama',
    'Kelas',
    'Semester',
    'Prodi',
    'Terlambat',
    'Alfa',
    'Total'
);

// Sesuaikan lebar kolom (total 277 mm untuk A4 Landscape)
$w = array(
    10,  // No
    30,  // NIM
    60,  // Nama
    25,  // Kelas
    20,  // Semester
    32,  // Prodi
    30,  // Terlambat
    30,  // Alfa
    30   // Total
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
    $query = "SELECT 
                NIM, 
                NAMA, 
                KELAS, 
                SEMESTER, 
                PRODI, 
                JUMLAH_TERLAMBAT,
                JUMLAH_ALFA
              FROM TBL_MAHASISWA 
              ORDER BY KELAS, NIM";
    
    $stmt = $db->query($query);
    $no = 1;
    $fill = false;

    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Hitung keterlambatan
        $terlambat = $row['JUMLAH_TERLAMBAT'] * 2;  // Konversi ke menit
        $alfa = $row['JUMLAH_ALFA'] * 60;           // Konversi jam ke menit
        $total = $terlambat + $alfa;

        // Set warna latar belakang selang-seling
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

        // Cetak data
        $pdf->Cell($w[0], 6, $no++, 1, 0, 'C', $fill);
        $pdf->Cell($w[1], 6, $row['NIM'], 1, 0, 'C', $fill);
        
        // Handle nama yang panjang
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell($w[2], 6, $row['NAMA'], 1, 'L', $fill);
        $pdf->SetXY($x + $w[2], $y);
        
        $pdf->Cell($w[3], 6, $row['KELAS'], 1, 0, 'C', $fill);
        $pdf->Cell($w[4], 6, $row['SEMESTER'], 1, 0, 'C', $fill);
        $pdf->Cell($w[5], 6, $row['PRODI'], 1, 0, 'C', $fill);
        
        // Data keterlambatan
        $pdf->Cell($w[6], 6, $terlambat . ' menit', 1, 0, 'C', $fill);
        $pdf->Cell($w[7], 6, $alfa . ' menit', 1, 0, 'C', $fill);
        
        // Total dengan background berbeda jika lebih dari batas tertentu
        if ($total > 500) {
            $pdf->SetTextColor(255, 0, 0); // Merah untuk total tinggi
        }
        $pdf->Cell($w[8], 6, $total . ' menit', 1, 0, 'C', $fill);
        $pdf->SetTextColor(0); // Reset warna teks
        
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
$pdf->Output('Daftar_Mahasiswa_' . date('Y-m-d') . '.pdf', 'D');
?>