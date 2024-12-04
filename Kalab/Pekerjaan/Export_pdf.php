<?php
// Pastikan path yang benar
require_once dirname(__FILE__) . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once('../../Config.php');
class PDF extends TCPDF {
    // Page header
    public function Header() {
        // Jika ingin menambahkan header khusus
    }
    
    // Page footer
    public function Footer() {
        // Jika ingin menambahkan footer khusus
    }
}

// Inisialisasi dokumen PDF
$pdf = new PDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set informasi dokumen
$pdf->SetCreator('DesignKompen');
$pdf->SetAuthor('Kalab');
$pdf->SetTitle('Daftar Pekerjaan');

// Set margin
$pdf->SetMargins(10, 10, 10);

// Hapus header dan footer default
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Tambah halaman
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Header tabel
$header = array('No', 'Kode', 'Nama Pekerjaan', 'Detail Pekerjaan', 'Jam', 'Limit', 'ID PJ', 'PJ');

// Lebar kolom (total = 275 untuk A4 Landscape)
$w = array(15, 25, 70, 65, 25, 25, 25, 25);

// Buat header tabel
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);
$pdf->SetFont('', 'B');

foreach($header as $i => $h) {
    $pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// Isi tabel
$pdf->SetFont('', '');
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0);

try {
    $query = "SELECT * FROM tbl_pekerjaan ORDER BY id_PEKERJAAN";
    $stmt = executeQuery($query);
    
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $startY = $pdf->GetY();
        
        // Cek apakah halaman baru diperlukan
        if ($startY + 20 > $pdf->getPageHeight() - $pdf->getMargins()['bottom']) {
            $pdf->AddPage();
            $startY = $pdf->GetY();
        }
        
        // Kolom No
        $pdf->Cell($w[0], 6, $row['ID_PEKERJAAN'], 1, 0, 'C');
        
        // Kolom Kode
        $pdf->Cell($w[1], 6, $row['KODE_PEKERJAAN'], 1, 0, 'C');
        
        // Simpan posisi X setelah kolom kode
        $xAfterKode = $pdf->GetX();
        
        // Reset Y ke posisi awal
        $pdf->SetY($startY);
        $pdf->SetX($xAfterKode);
        
        // Kolom Nama Pekerjaan dengan MultiCell
        $pdf->MultiCell($w[2], 6, $row['NAMA_PEKERJAAN'], 1, 'L');
        
        // Dapatkan Y terbesar setelah nama pekerjaan
        $yAfterNama = $pdf->GetY();
        
        // Reset ke posisi setelah kolom nama
        $pdf->SetY($startY);
        $pdf->SetX($xAfterKode + $w[2]);
        
        // Kolom Detail Pekerjaan dengan MultiCell
        $pdf->MultiCell($w[3], 6, $row['DETAIL_PEKERJAAN'], 1, 'L');
        
        // Dapatkan Y terbesar setelah detail
        $yAfterDetail = $pdf->GetY();
        
        // Gunakan Y terbesar antara nama dan detail
        $maxY = max($yAfterNama, $yAfterDetail);
        $cellHeight = $maxY - $startY;
        
        // Reset ke posisi awal untuk kolom-kolom tersisa
        $pdf->SetY($startY);
        $pdf->SetX($xAfterKode + $w[2] + $w[3]);
        
        // Kolom-kolom tersisa dengan tinggi yang sama
        $pdf->Cell($w[4], $cellHeight, $row['JAM_PEKERJAAN'], 1, 0, 'C');
        $pdf->Cell($w[5], $cellHeight, $row['BATAS_PEKERJA'], 1, 0, 'C');
        $pdf->Cell($w[6], $cellHeight, $row['ID_PENANGGUNG_JAWAB'], 1, 0, 'C');
        $pdf->Cell($w[7], $cellHeight, $row['PENANGGUNG_JAWAB'], 1, 0, 'L');
        
        // Pindah ke baris berikutnya
        $pdf->SetY($maxY);
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Output PDF
$pdf->Output('daftar_pekerjaan.pdf', 'D');