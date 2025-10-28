<?php
session_start();
header('Content-Type: application/json'); // Set header agar browser tahu ini JSON

// 1. Keamanan: Cek apakah KLIEN sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Akses ditolak. Silakan login sebagai klien.']);
    exit;
}

// 2. Ambil ID Klien dari Session (INI PENTING UNTUK MULTI-TENANT)
$client_id = $_SESSION['client_id'];

// 3. Sertakan koneksi database
require 'db_config.php';

// 4. Siapkan array untuk respons
$response = [
    'logs' => [],
    'sensors' => [],
    'trend_data' => [],
    'trend_title' => 'Tren Serangan'
];

try {
    // --- [TAMBAHAN BARU] Ambil Info Akun ---
    $response['account_info'] = [
        'company_name' => 'Perusahaan Tidak Ditemukan',
        'username' => 'User Tidak Ditemukan'
    ];

    // Ambil nama perusahaan
    $stmt_client = $pdo->prepare("SELECT company_name FROM clients WHERE id = ?");
    $stmt_client->execute([$client_id]);
    $client_info = $stmt_client->fetch();
    if ($client_info) {
        $response['account_info']['company_name'] = $client_info['company_name'];
    }

    // Ambil username pengguna yang sedang login
    $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt_user->execute([$_SESSION['user_id']]); // Ambil user_id dari session
    $user_info = $stmt_user->fetch();
    if ($user_info) {
        $response['account_info']['username'] = $user_info['username'];
    }
    // 5. Logika untuk Data Grafik Tren
    // Ambil periode dari URL (e.g., ?trend_period=7d)
    $period = $_GET['trend_period'] ?? '7d'; // Default 7 hari
    
    $sql_interval = "INTERVAL 7 DAY";
    $sql_date_format = "'%Y-%m-%d'"; // Group per hari
    $response['trend_title'] = 'Tren Serangan (7 Hari Terakhir)';

    switch ($period) {
        case '24h':
            $sql_interval = "INTERVAL 1 DAY";
            $sql_date_format = "'%Y-%m-%d %H:00'"; // Group per jam
            $response['trend_title'] = 'Tren Serangan (24 Jam Terakhir)';
            break;
        case '30d':
            $sql_interval = "INTERVAL 30 DAY";
            $sql_date_format = "'%Y-%m-%d'"; // Group per hari
            $response['trend_title'] = 'Tren Serangan (30 Hari Terakhir)';
            break;
        case '90d':
            $sql_interval = "INTERVAL 90 DAY";
            $sql_date_format = "'%Y-%m-%d'"; // Group per hari
            $response['trend_title'] = 'Tren Serangan (90 Hari Terakhir)';
            break;
        case '1y':
            $sql_interval = "INTERVAL 1 YEAR";
            $sql_date_format = "'%Y-%m'"; // Group per bulan
            $response['trend_title'] = 'Tren Serangan (1 Tahun Terakhir)';
            break;
        // Default (7d) sudah diatur di atas
    }

    // Query untuk data tren
    $stmt_trend = $pdo->prepare("
        SELECT 
            DATE_FORMAT(timestamp, $sql_date_format) AS attack_period,
            COUNT(id) AS count
        FROM logs
        WHERE 
            client_id = ? AND timestamp >= DATE_SUB(NOW(), $sql_interval)
        GROUP BY attack_period
        ORDER BY attack_period ASC
    ");
    $stmt_trend->execute([$client_id]);
    $response['trend_data'] = $stmt_trend->fetchAll(PDO::FETCH_ASSOC);


    // 6. Ambil Data Sensor (HANYA milik klien ini)
    // Kita juga cek 'last_seen' untuk status Online/Offline
    // (Misal: dianggap offline jika > 5 menit tidak ada kabar)
    $stmt_sensors = $pdo->prepare("
        SELECT 
            id, name, mac_address, latitude, longitude,
            config_max_deauth_score, config_max_beacon_count, 
            config_max_probe_req_count, config_max_auth_req_count, 
            config_max_assoc_req_count, config_max_rts_count, 
            config_max_cts_count, config_check_interval_ms,
            CASE
                WHEN last_seen IS NULL THEN 'Never Seen'
                WHEN last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'Offline'
                ELSE 'Online'
            END AS status
        FROM sensors
        WHERE client_id = ?
        ORDER BY name ASC
    ");
    $stmt_sensors->execute([$client_id]);
    // ... (setelah query $stmt_sensors->execute([$client_id]); ...)
    $response['sensors'] = $stmt_sensors->fetchAll(PDO::FETCH_ASSOC);

    // --- [TAMBAHAN BARU] Ambil Daftar Pengguna Klien ---
    $response['users'] = []; // Default array kosong
    // Hanya Admin Klien yang perlu lihat daftar user lain
    if ($_SESSION['role'] === 'administrator') { 
        $stmt_users = $pdo->prepare("
            SELECT id, username, role, created_at 
            FROM users 
            WHERE client_id = ? 
            ORDER BY username ASC
        ");
        $stmt_users->execute([$client_id]);
        $response['users'] = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
    }
    // --- [AKHIR TAMBAHAN BARU] ---


    // 7. Ambil 200 Log Serangan Terbaru (HANYA milik klien ini)
    // Kita gabung (JOIN) dengan nama sensor agar lebih informatif
    $stmt_logs = $pdo->prepare("
        SELECT 
            l.id, l.timestamp, l.attack_type, l.mac, l.ssid, l.rssi,
            l.sensor_mac,
            s.name AS sensor_name
        FROM logs l
        LEFT JOIN sensors s ON l.sensor_mac = s.mac_address AND l.client_id = s.client_id
        WHERE l.client_id = ?
        ORDER BY l.timestamp DESC
        LIMIT 200
    ");
    $stmt_logs->execute([$client_id]);
    $response['logs'] = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

    // 8. Kirim semua data sebagai JSON
    echo json_encode($response);

} catch (PDOException $e) {
    // 9. Tangani jika ada error database
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Gagal mengambil data dari database: ' . $e->getMessage()]);
}
?>