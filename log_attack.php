<?php
// Set header sebagai JSON
header('Content-Type: application/json');

// 1. Sertakan koneksi database
require 'db_config.php'; // Pastikan file ini ada dan benar

// --- Ambil & Validasi Input ---

// 2. Baca data JSON mentah dari body POST request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true); // Decode menjadi array asosiatif

// 3. Cek apakah data JSON valid
if (empty($data)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada data terkirim atau format JSON salah.']);
    exit;
}

// 4. Validasi data yang WAJIB ada (Termasuk Token!)
$client_id = $data['client_id'] ?? null;
$sensor_mac = $data['sensor_mac'] ?? null;
$attack_type = $data['attack_type'] ?? null;
// [PERUBAHAN] Ambil token dari JSON
$sensor_token = $data['sensor_token'] ?? null;

if (empty($client_id) || empty($sensor_mac) || empty($attack_type) || empty($sensor_token)) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'status' => 'error',
        'message' => 'Data tidak lengkap. Membutuhkan: client_id, sensor_mac, attack_type, dan sensor_token.'
    ]);
    exit;
}

// Standarkan MAC
$sensor_mac = strtoupper($sensor_mac);

// 5. Ambil data OPSIONAL (boleh NULL)
$mac = $data['mac'] ?? null;
$ssid = $data['ssid'] ?? null;
$rssi = $data['rssi'] ?? null;

// --- Logika Inti & Interaksi Database ---

try {
    // --- [PERUBAHAN PENTING] Validasi Sensor & Token ---
    // Sebelum INSERT log, pastikan sensor ini valid dan token-nya cocok
    $stmt_validate = $pdo->prepare("
        SELECT id
        FROM sensors
        WHERE
            client_id = ? AND
            mac_address = ? AND
            sensor_token = ?
        LIMIT 1
    ");
    $stmt_validate->execute([$client_id, $sensor_mac, $sensor_token]);
    $sensor_exists = $stmt_validate->fetchColumn(); // Ambil ID atau false

    if (!$sensor_exists) {
        // Jika kombinasi client_id, mac, dan token tidak ditemukan
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'Otentikasi sensor gagal (ID, MAC, atau Token tidak valid). Log ditolak.']);
        exit;
    }
    // --- [AKHIR PERUBAHAN PENTING] ---

    // 6. Sensor valid, masukkan data log ke database
    $sql_insert_log = "
        INSERT INTO logs (client_id, sensor_mac, attack_type, mac, ssid, rssi)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmt_insert = $pdo->prepare($sql_insert_log);
    $stmt_insert->execute([
        $client_id,
        $sensor_mac,
        $attack_type,
        $mac,
        $ssid,
        $rssi
    ]);

    // 7. Kirim respons sukses
    echo json_encode(['status' => 'success', 'message' => 'Log serangan berhasil disimpan.']);

} catch (PDOException $e) {
    // --- Penanganan Error Database ---
    http_response_code(500); // Internal Server Error
    error_log("PDOException in log_attack.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan pada database saat menyimpan log.']);
} catch (Exception $e) {
    // --- Penanganan Error Umum Lainnya ---
    http_response_code(500);
    error_log("Exception in log_attack.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem saat menyimpan log.']);
}
?>