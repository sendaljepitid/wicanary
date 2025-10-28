<?php
// get_guest_summary.php (Versi MySQL Multi-Tenant)
require_once 'db_config.php'; // Menggunakan koneksi MySQL ($pdo)

// Inisialisasi array $summary untuk memastikan semua key ada,
// bahkan jika query gagal atau tidak ada data.
$summary = [
    'attack_types' => [],
    'top_attackers' => [],
    'top_targets' => [],
    'stats' => ['total_attacks' => 0, 'total_sensors' => 0],
    'latest_logs' => [],
    'sensors' => []
];

try {
    // --- Langkah 1: Ambil Data Agregat Hanya dari Klien Aktif ---

    // Data 1: Jumlah per jenis serangan dari klien aktif
    $stmt_types = $pdo->query("
        SELECT l.attack_type, COUNT(*) as count
        FROM logs l
        JOIN clients c ON l.client_id = c.id
        WHERE c.is_active = 1 -- Hanya dari klien aktif
        GROUP BY l.attack_type
        ORDER BY count DESC
    ");
    // Konversi hasil ke format [attack_type => count]
    $attack_types_assoc = $stmt_types->fetchAll(PDO::FETCH_ASSOC);
    foreach ($attack_types_assoc as $row) {
        $summary['attack_types'][$row['attack_type']] = (int)$row['count'];
    }


    // Data 2: Top 5 pelaku dari klien aktif
    $stmt_attackers = $pdo->query("
        SELECT l.mac, COUNT(*) as count
        FROM logs l
        JOIN clients c ON l.client_id = c.id
        WHERE c.is_active = 1 AND l.mac IS NOT NULL AND l.mac != 'N/A'
        GROUP BY l.mac
        ORDER BY count DESC
        LIMIT 5
    ");
    $summary['top_attackers'] = $stmt_attackers->fetchAll();

    // Data 3: Top 5 target SSID dari klien aktif
    $stmt_targets = $pdo->query("
        SELECT l.ssid, COUNT(*) as count
        FROM logs l
        JOIN clients c ON l.client_id = c.id
        WHERE c.is_active = 1 AND l.ssid IS NOT NULL AND l.ssid != 'N/A' AND l.ssid != '' AND l.ssid != 'Unknown AP'
        GROUP BY l.ssid
        ORDER BY count DESC
        LIMIT 5
    ");
    $summary['top_targets'] = $stmt_targets->fetchAll();

    // Data 4: Statistik Utama (Total serangan & sensor dari klien aktif)
    $total_attacks = $pdo->query("
        SELECT COUNT(*) FROM logs l
        JOIN clients c ON l.client_id = c.id
        WHERE c.is_active = 1
    ")->fetchColumn();
    
    $total_sensors = $pdo->query("
        SELECT COUNT(*) FROM sensors s
        JOIN clients c ON s.client_id = c.id
        WHERE c.is_active = 1
    ")->fetchColumn();
    
    $summary['stats'] = [
        'total_attacks' => (int)$total_attacks,
        'total_sensors' => (int)$total_sensors
    ];

    // Data 5: Log Serangan Terbaru (5 teratas dari semua klien aktif)
    $stmt_latest = $pdo->query("
        SELECT l.timestamp, l.attack_type, s.name as sensor_name
        FROM logs l
        JOIN clients c ON l.client_id = c.id
        LEFT JOIN sensors s ON l.sensor_mac = s.mac_address AND l.client_id = s.client_id -- Join sensor milik klien yang sama
        WHERE c.is_active = 1
        ORDER BY l.id DESC
        LIMIT 5
    ");
    $summary['latest_logs'] = $stmt_latest->fetchAll();

    // Data 6: Lokasi Sensor Publik (hanya dari klien aktif)
    $stmt_sensors = $pdo->query("
        SELECT s.name, s.latitude, s.longitude
        FROM sensors s
        JOIN clients c ON s.client_id = c.id
        WHERE c.is_active = 1 AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL
    ");
    $summary['sensors'] = $stmt_sensors->fetchAll();

} catch (PDOException $e) {
    // Jika terjadi error database, catat di log server
    // Untuk halaman publik, kita tidak menghentikan eksekusi,
    // kita hanya akan mengirimkan data $summary yang sebagian besar kosong.
    error_log("Get Guest Summary PDOException: " . $e->getMessage());
    // Anda bisa tambahkan field error ke summary jika perlu debugging di frontend
    // $summary['error'] = 'Gagal mengambil data ringkasan.';
}

// Kirim hasil (meskipun mungkin kosong jika ada error) sebagai JSON
header('Content-Type: application/json');
echo json_encode($summary);
?>

