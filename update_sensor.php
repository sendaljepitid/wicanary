<?php
session_start();
header('Content-Type: application/json');

// 1. Keamanan: Cek apakah KLIEN dan ADMIN KLIEN sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda bukan Administrator Klien.']);
    exit;
}

// 2. Ambil ID Klien dari Session
$client_id = $_SESSION['client_id'];

// 3. Sertakan koneksi database
require 'db_config.php';

// 4. Ambil data dari POST
$sensor_id = $_POST['id'] ?? null;
$name = $_POST['name'] ?? null;
$mac_address = $_POST['mac_address'] ?? null; // MAC tidak bisa diedit, tapi kita butuh untuk validasi
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

// Konfigurasi Thresholds (jika kosong, set ke NULL agar database bisa pakai default)
$config_deauth = !empty($_POST['config_max_deauth_score']) ? $_POST['config_max_deauth_score'] : NULL;
$config_beacon = !empty($_POST['config_max_beacon_count']) ? $_POST['config_max_beacon_count'] : NULL;
$config_probe = !empty($_POST['config_max_probe_req_count']) ? $_POST['config_max_probe_req_count'] : NULL;
$config_auth = !empty($_POST['config_max_auth_req_count']) ? $_POST['config_max_auth_req_count'] : NULL;
$config_assoc = !empty($_POST['config_max_assoc_req_count']) ? $_POST['config_max_assoc_req_count'] : NULL;
$config_rts = !empty($_POST['config_max_rts_count']) ? $_POST['config_max_rts_count'] : NULL;
$config_cts = !empty($_POST['config_max_cts_count']) ? $_POST['config_max_cts_count'] : NULL;
$config_check = !empty($_POST['config_check_interval_ms']) ? $_POST['config_check_interval_ms'] : NULL;


// 5. Validasi Sederhana
if (empty($sensor_id) || empty($name) || empty($mac_address) || empty($latitude) || empty($longitude)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'ID, Nama, MAC, Lat, dan Lon tidak boleh kosong.']);
    exit;
}

try {
    // 6. Siapkan SQL Update
    // PENTING: Kita tambahkan "WHERE client_id = ?"
    // Ini memastikan admin klien A tidak bisa mengedit sensor milik klien B
    // hanya dengan menebak $sensor_id.
    $sql = "
        UPDATE sensors SET
            name = ?,
            latitude = ?,
            longitude = ?,
            config_max_deauth_score = ?,
            config_max_beacon_count = ?,
            config_max_probe_req_count = ?,
            config_max_auth_req_count = ?,
            config_max_assoc_req_count = ?,
            config_max_rts_count = ?,
            config_max_cts_count = ?,
            config_check_interval_ms = ?
        WHERE 
            id = ? AND client_id = ? AND mac_address = ?
    ";
    
    $stmt_update = $pdo->prepare($sql);
    $stmt_update->execute([
        $name,
        $latitude,
        $longitude,
        $config_deauth,
        $config_beacon,
        $config_probe,
        $config_auth,
        $config_assoc,
        $config_rts,
        $config_cts,
        $config_check,
        $sensor_id,
        $client_id,  // Keamanan Multi-tenant
        $mac_address // Keamanan tambahan
    ]);

    // 7. Cek apakah ada baris yang ter-update
    $affected_rows = $stmt_update->rowCount();

    if ($affected_rows > 0) {
        // 8. Kirim respons sukses
        echo json_encode([
            'status' => 'success', 
            'message' => 'Data sensor berhasil diperbarui! Sensor akan mengambil konfigurasi baru dalam beberapa saat.'
        ]);
    } else {
        // Ini bisa terjadi jika user mengklik "Simpan" tanpa mengubah apa-apa
        // atau jika mereka mencoba mengedit sensor yang bukan miliknya.
        echo json_encode([
            'status' => 'info', 
            'message' => 'Tidak ada perubahan data yang disimpan.'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>