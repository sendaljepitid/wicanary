<?php
// Set header sebagai JSON
header('Content-Type: application/json');

// 1. Sertakan koneksi database
require 'db_config.php'; // Pastikan file ini ada dan benar

// --- Ambil & Validasi Input ---

// 2. Ambil MAC Address dari sensor (via GET request)
$mac_address = $_GET['mac'] ?? null;

// [PERUBAHAN] Ambil Token dari sensor (via GET request, OPSIONAL)
$token_from_sensor = $_GET['token'] ?? null;

// Validasi MAC
if (empty($mac_address)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Parameter MAC address diperlukan.']);
    exit;
}
if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac_address)) {
     http_response_code(400);
     echo json_encode(['error' => 'Format MAC Address tidak valid.']);
     exit;
}
$mac_address = strtoupper($mac_address); // Standarkan format

// --- Logika Inti & Interaksi Database ---

try {
    // [PERUBAHAN] Query untuk mencari sensor
    // Sekarang kita pilih semua kolom, termasuk sensor_token
    // Dan kita cek token JIKA dikirim oleh sensor
    $sql_find_sensor = "SELECT * FROM sensors WHERE mac_address = ?";
    $params = [$mac_address];

    if (!empty($token_from_sensor)) {
        // Jika sensor mengirim token, tambahkan validasi token ke query
        $sql_find_sensor .= " AND sensor_token = ?";
        $params[] = $token_from_sensor; // Tambahkan token ke parameter
    } else {
        // Jika sensor TIDAK mengirim token (misalnya check-in pertama kali
        // setelah update firmware, atau sensor lama), kita cari berdasarkan MAC saja
        // Namun, kita bisa menambahkan pengecekan bahwa token di DB memang NULL
        // $sql_find_sensor .= " AND sensor_token IS NULL"; // Opsional: lebih ketat
    }

    $stmt_find = $pdo->prepare($sql_find_sensor);
    $stmt_find->execute($params);
    $sensor = $stmt_find->fetch(PDO::FETCH_ASSOC);

    // 4. Handle jika Sensor Tidak Ditemukan (atau token salah)
    if (!$sensor) {
        // Jika token dikirim tapi tidak cocok, atau MAC tidak ditemukan
        http_response_code(404); // Not Found (atau 403 Forbidden jika token salah)
        $error_message = !empty($token_from_sensor)
                         ? 'Sensor tidak ditemukan atau token tidak valid.'
                         : 'Sensor tidak terdaftar di sistem.';
        echo json_encode(['error' => $error_message]);
        exit;
    }

    // --- Sensor Ditemukan & Valid ---

    // 5. Lakukan Heartbeat (Update 'last_seen')
    $stmt_heartbeat = $pdo->prepare("UPDATE sensors SET last_seen = NOW() WHERE id = ?");
    $stmt_heartbeat->execute([$sensor['id']]);

    // 6. Siapkan Respons Konfigurasi (Termasuk Token!)
    // Pastikan semua nilai di-casting ke tipe data yang benar (int/string)
    $config_response = [
        'client_id'                 => (int)$sensor['client_id'],
        'sensor_id'                 => (int)$sensor['id'],
        // [PERUBAHAN] Kirim token ke sensor (bisa null jika belum ada)
        'sensor_token'              => $sensor['sensor_token'], // Kirim apa adanya (string atau null)
        'config_max_deauth_score'   => (int)$sensor['config_max_deauth_score'],
        'config_max_beacon_count'   => (int)$sensor['config_max_beacon_count'],
        'config_max_probe_req_count'=> (int)$sensor['config_max_probe_req_count'],
        'config_max_auth_req_count' => (int)$sensor['config_max_auth_req_count'],
        'config_max_assoc_req_count'=> (int)$sensor['config_max_assoc_req_count'],
        'config_max_rts_count'      => (int)$sensor['config_max_rts_count'],
        'config_max_cts_count'      => (int)$sensor['config_max_cts_count'],
        'config_check_interval_ms'  => (int)$sensor['config_check_interval_ms']
    ];

    // 7. Kirim JSON ke sensor
    echo json_encode($config_response);

} catch (PDOException $e) {
    // --- Penanganan Error Database ---
    http_response_code(500); // Internal Server Error
    error_log("PDOException in sensor_checkin.php: " . $e->getMessage());
    echo json_encode(['error' => 'Terjadi kesalahan pada database saat check-in sensor.']);
} catch (Exception $e) {
    // --- Penanganan Error Umum Lainnya ---
    http_response_code(500);
    error_log("Exception in sensor_checkin.php: " . $e->getMessage());
    echo json_encode(['error' => 'Terjadi kesalahan sistem saat check-in sensor.']);
}
?>