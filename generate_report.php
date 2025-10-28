<?php
session_start();
// [PERBAIKAN] Keamanan: Periksa login, peran, DAN client_id
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['client_id'])) {
    http_response_code(403); // Forbidden
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Akses ditolak. Sesi tidak valid.']));
}
// Periksa peran yang diizinkan
if (!in_array($_SESSION['role'], ['administrator', 'auditor'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Akses ditolak. Anda tidak memiliki izin untuk membuat laporan.']));
}

// [PERBAIKAN] Ambil client_id dari sesi untuk memfilter semua query
$client_id = $_SESSION['client_id'];

require_once 'db_config.php'; // Koneksi MySQL ($pdo)
require_once 'fpdf186/mc_table.php'; // Pastikan path ini benar

// --- FUNGSI BANTUAN ---

/**
 * [PERBAIKAN] Fungsi cURL untuk mengunduh gambar (lebih andal daripada Image() bawaan FPDF)
 * Ini mencegah error "Connection timed out" jika fopen diblokir.
 */
function getRemoteImageData($url) {
    if (!function_exists('curl_init')) {
        error_log("Fungsi cURL tidak diaktifkan (extension=curl) di php.ini.");
        return null;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout koneksi 10 detik
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);      // Timeout total 15 detik
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Ikuti redirect jika ada
    
    $imageData = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200 && $imageData) {
        return $imageData;
    } else {
        // Catat error ke log server
        error_log("cURL failed to fetch image from quickchart.io. HTTP Code: $http_code, Error: $error, URL: $url");
        return null;
    }
}

/**
 * Fungsi untuk menghasilkan URL QuickChart
 */
function getChartUrl($data, $type = 'pie', $title = '') {
    if (empty($data)) return null;
    $chartConfig = [
        'type' => $type,
        'data' => [
            'labels' => array_keys($data),
            'datasets' => [[
                'data' => array_values($data),
                'backgroundColor' => ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
            ]]
        ],
        'options' => [
            'title' => ['display' => true, 'text' => $title],
            'legend' => ['position' => 'right']
        ]
    ];
    // Gunakan https untuk quickchart
    return 'https://quickchart.io/chart?width=500&height=300&c=' . urlencode(json_encode($chartConfig));
}

// --- KELAS PDF KUSTOM ---
class PDF_Report extends PDF_MC_Table
{
    private $reportTitlePeriod;

    // [PERBAIKAN] Konstruktor kustom dihapus untuk menyelesaikan fatal error 'Cannot call constructor'
    // PHP akan secara otomatis memanggil konstruktor dari kelas induk (PDF_MC_Table -> FPDF)

    function setReportTitlePeriod($period) {
        $this->reportTitlePeriod = $period;
    }

    function CoverPage() {
        $this->AddPage();
        $this->SetFillColor(45, 52, 71);
        $this->Rect(0, 0, 210, 297, 'F');
        $this->SetY(80);
        $this->SetFont('Arial', 'B', 28);
        $this->SetTextColor(255);
        $this->Cell(0, 15, 'Laporan Keamanan Jaringan', 0, 1, 'C');
        $this->SetFont('Arial', '', 24);
        $this->SetTextColor(200);
        $this->Cell(0, 15, 'WiCanary', 0, 1, 'C');
        $this->Ln(30);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(255);
        $this->Cell(0, 10, 'Periode Laporan: ' . $this->reportTitlePeriod, 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->SetTextColor(200);
        $this->Cell(0, 10, 'Tanggal Dibuat: ' . date('d F Y'), 0, 1, 'C');
    }

    function Header() {
        if ($this->PageNo() == 1) return; // Tidak ada header di halaman sampul
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Laporan Serangan WiCanary - ' . $this->reportTitlePeriod, 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer() {
        if ($this->PageNo() == 1) return; // Tidak ada footer di halaman sampul
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function ChapterTitle($num, $title) {
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(0);
        $this->SetFillColor(230, 230, 240);
        $this->Cell(0, 10, "$num. $title", 0, 1, 'L', true);
        $this->Ln(4);
    }
}


// --- PENGAMBILAN & PEMROSESAN DATA ---
$period = $_GET['period'] ?? 'daily';
$date_condition_sql = "";
$title_period = "";
$params = [':client_id' => $client_id]; // [PERBAIKAN] Siapkan parameter untuk client_id

// [PERBAIKAN] Menggunakan sintaks MySQL untuk filter tanggal
switch ($period) {
    case 'weekly':
        // Sintaks MySQL untuk 7 hari terakhir
        $date_condition_sql = "AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $title_period = "7 Hari Terakhir";
        break;
    case 'monthly':
        // Sintaks MySQL untuk 30 hari terakhir
        $date_condition_sql = "AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $title_period = "30 Hari Terakhir";
        break;
    case 'daily':
    default:
        // Sintaks MySQL untuk 24 jam terakhir
        $date_condition_sql = "AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $title_period = "24 Jam Terakhir";
        break;
}

try {
    // [PERBAIKAN] Kumpulkan semua log sesuai periode DAN client_id
    // Query ini difilter berdasarkan client_id dari sesi
    $sql_logs = "SELECT l.*, s.name as sensor_name 
                 FROM logs l 
                 LEFT JOIN sensors s ON l.sensor_mac = s.mac_address AND l.client_id = s.client_id
                 WHERE l.client_id = :client_id $date_condition_sql 
                 ORDER BY l.id DESC";
                 
    $stmt = $pdo->prepare($sql_logs);
    $stmt->execute($params); // Execute dengan parameter client_id
    $logs = $stmt->fetchAll();

    // Proses data di PHP untuk ringkasan
    $total_attacks = count($logs);
    $attack_types = [];
    $top_sensors = [];
    $top_attackers = [];

    foreach ($logs as $log) {
        $type = str_replace('_', ' ', $log['attack_type']);
        $attack_types[$type] = ($attack_types[$type] ?? 0) + 1;
        
        $sensor_name = $log['sensor_name'] ?? $log['sensor_mac']; // Fallback ke MAC jika nama sensor tidak ada
        $top_sensors[$sensor_name] = ($top_sensors[$sensor_name] ?? 0) + 1;
        
        if ($log['mac'] && $log['mac'] !== 'N/A') {
            $top_attackers[$log['mac']] = ($top_attackers[$log['mac']] ?? 0) + 1;
        }
    }
    arsort($attack_types);
    arsort($top_sensors);
    arsort($top_attackers);

} catch (PDOException $e) {
    error_log("Generate Report PDOException for client ID {$client_id}: " . $e->getMessage());
    die("Database Error: Gagal mengambil data laporan. Periksa log server.");
}

// ======================================================
//   MULAI PEMBUATAN PDF
// ======================================================
$pdf = new PDF_Report(); // Panggil konstruktor sederhana (perbaikan bug)
$pdf->setReportTitlePeriod($title_period);
$pdf->AliasNbPages();
$pdf->CoverPage();

// Halaman 2: Ringkasan Eksekutif & Visual
$pdf->AddPage();
$pdf->ChapterTitle('1', 'Ringkasan Eksekutif');
$pdf->SetFont('Arial', '', 11);
// [PERBAIKAN] Teks Ringkasan Eksekutif dibuat lebih formal dan teknis
$executive_summary = "Dokumen ini menyajikan analisis keamanan jaringan nirkabel yang terdeteksi oleh sistem WiCanary untuk Klien selama periode " . strtolower($title_period) . ". ";
$executive_summary .= "Sistem mencatat total " . number_format($total_attacks) . " anomali atau insiden keamanan. ";

if ($total_attacks > 0) {
    $top_attack_type = key($attack_types) ?? 'N/A';
    $top_attack_count = $attack_types[$top_attack_type] ?? 0;
    $top_attacker_mac = key($top_attackers) ?? 'N/A';
    $executive_summary .= "Vektor serangan yang paling dominan teridentifikasi sebagai '" . $top_attack_type . "', yang mencakup " . number_format($top_attack_count) . " insiden (sekitar " . round(($top_attack_count / $total_attacks) * 100) . "% dari total). ";
    $executive_summary .= "Sumber anomali (Source MAC) paling aktif yang tercatat adalah '" . $top_attacker_mac . "'. ";
    $executive_summary .= "Analisis mendalam mengenai distribusi serangan, sensor yang terdampak, dan log detail disajikan pada bagian berikutnya.";
} else {
    $executive_summary .= "Tidak ada insiden keamanan yang tercatat selama periode ini. Sistem beroperasi dalam kondisi normal.";
}
$pdf->MultiCell(0, 7, $executive_summary);
$pdf->Ln(10);

$pdf->ChapterTitle('2', 'Visualisasi Data');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Distribusi Tipe Serangan', 0, 1, 'C');

$chartUrl = getChartUrl($attack_types);
if ($chartUrl && $total_attacks > 0) {
    // [PERBAIKAN] Menggunakan cURL (fungsi getRemoteImageData) untuk mengambil gambar
    $imageData = getRemoteImageData($chartUrl);
    if ($imageData) {
        // Buat file sementara untuk menampung data gambar
        $tempFile = tempnam(sys_get_temp_dir(), 'chartimg');
        if ($tempFile) {
            file_put_contents($tempFile, $imageData);
            // Gunakan file sementara dengan FPDF
            // Gunakan @ untuk menekan error jika FPDF masih bermasalah dengan gambar PNG
            @$pdf->Image($tempFile, $pdf->GetX() + 30, $pdf->GetY(), 150, 0, 'PNG');
            // Hapus file sementara setelah digunakan
            unlink($tempFile);
        } else {
             $pdf->SetFont('Arial', 'I', 10);
             $pdf->Cell(0, 20, '(Gagal membuat file sementara untuk grafik.)', 0, 1, 'C');
        }
    } else {
        // Fallback jika gambar gagal diunduh
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 20, '(Gagal memuat grafik. Periksa koneksi keluar server ke quickchart.io.)', 0, 1, 'C');
    }
} else {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 20, 'Tidak ada data serangan untuk digambarkan.', 0, 1, 'C');
}

// Halaman 3: Analisis Detail
$pdf->AddPage();
$pdf->ChapterTitle('3', 'Analisis Detail');
// Tabel Top Tipe Serangan
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Tipe Serangan Teratas', 0, 1);
$pdf->SetWidths([130, 60]);
$pdf->Row(['Tipe Serangan', 'Jumlah Insiden'], true);
$pdf->SetFont('Arial', '', 10);
if(empty($attack_types)) $pdf->Row(['Tidak ada data', '']);
else {
    $i = 0;
    foreach($attack_types as $type => $count) {
        if($i++ >= 5) break; // Batasi 5 teratas
        $pdf->Row([$type, $count]);
    }
}
$pdf->Ln(8);
// Tabel Top Sensor Aktif
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Sensor Paling Aktif (Berdasarkan Laporan)', 0, 1);
$pdf->SetWidths([130, 60]);
$pdf->Row(['Nama Sensor', 'Jumlah Laporan'], true);
$pdf->SetFont('Arial', '', 10);
if(empty($top_sensors)) $pdf->Row(['Tidak ada data', '']);
else {
    $i = 0;
    foreach($top_sensors as $name => $count) {
        if($i++ >= 5) break;
        $pdf->Row([$name, $count]);
    }
}
$pdf->Ln(8);
// [PERBAIKAN] Tabel Top Pelaku (Attacker)
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Profil Pelaku Teratas (Source MAC)', 0, 1);
$pdf->SetWidths([80, 110]);
$pdf->Row(['MAC Pelaku/Sumber', 'Jumlah Serangan'], true);
$pdf->SetFont('Arial', '', 10);
if (empty($top_attackers)) $pdf->Row(['Tidak ada data', '']);
else {
    $i = 0;
    foreach ($top_attackers as $mac => $count) {
        if ($i++ >= 5) break;
        $pdf->Row([$mac, $count]);
    }
}
$pdf->Ln(8);


// Halaman Lampiran: Log Detail
$pdf->AddPage('L'); // Halaman landscape
$pdf->ChapterTitle('4', 'Lampiran: Log Detail Insiden');
$pdf->SetFont('Arial', 'B', 8);
// Sesuaikan lebar kolom
$pdf->SetWidths([35, 40, 45, 45, 20, 85]);
$pdf->Row(['Waktu (Timestamp)', 'Tipe Serangan', 'Target SSID', 'MAC Terkait (Source/BSSID)', 'RSSI (dBm)', 'Sensor Pendeteksi'], true);

$pdf->SetFont('Arial', '', 7);
if (empty($logs)) {
    $pdf->Row(['Tidak ada data insiden untuk periode ini.', '', '', '', '', '']);
} else {
    foreach ($logs as $log) {
        $pdf->Row([
            $log['timestamp'],
            str_replace('_', ' ', $log['attack_type']), // Bersihkan nama tipe
            $log['ssid'] ?? 'N/A',
            $log['mac'] ?? 'N/A',
            $log['rssi'] ?? 'N/A',
            ($log['sensor_name'] ?? 'N/A') . ' (' . $log['sensor_mac'] . ')' // Tampilkan nama + mac
        ]);
    }
}

// Output PDF
$pdf->Output('I', 'WiCanary_Report_' . str_replace(' ', '_', $title_period) . '_' . date('Ymd') . '.pdf');
?>

