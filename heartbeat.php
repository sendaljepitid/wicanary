<?php
// heartbeat.php (Versi MySQL Multi-Tenant)
require_once 'db_config.php'; // Menggunakan koneksi database MySQL ($pdo)

// Terima data JSON mentah dari ESP32
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// --- Langkah 1: Validasi Input Dasar ---
// Periksa apakah JSON valid dan field wajib ada
if (!$data || !isset($data['sensor_mac']) || !isset($data['status']) || $data['status'] !== 'online') {
    http_response_code(400); // Bad Request
    header('Content-Type: application/json'); // Set header sebelum output
    error_log("Heartbeat failed: Invalid or missing data. JSON received: " . $json); // Catat data mentah yang salah
    // Hentikan eksekusi dan kirim pesan error JSON
    die(json_encode(['status' => 'error', 'message' => 'Invalid or missing data. Required: sensor_mac, status=online.']));
}

$sensor_mac_raw = $data['sensor_mac'];
$current_time = date('Y-m-d H:i:s'); // Waktu saat ini di server

// --- Langkah 2: Validasi Format MAC Address ---
// Bersihkan input: Hapus semua karakter non-heksadesimal (termasuk titik dua, spasi, dll.)
// dan ubah ke huruf besar untuk konsistensi.
$mac_address_cleaned = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $sensor_mac_raw));

// Validasi panjang MAC address (harus tepat 12 karakter hex)
if (strlen($mac_address_cleaned) !== 12) {
    http_response_code(400); // Bad Request
    header('Content-Type: application/json');
    error_log("Heartbeat failed: Invalid MAC format received - Raw: " . htmlspecialchars($sensor_mac_raw) . ", Cleaned: " . $mac_address_cleaned);
    // Kirim pesan error yang lebih informatif
    die(json_encode(['status' => 'error', 'message' => 'Invalid MAC address format received. Expected 12 hex characters.']));
}

// Format ulang ke format standar XX:XX:XX:XX:XX:XX untuk pencarian di database
$formatted_mac = implode(':', str_split($mac_address_cleaned, 2));


// --- [PERBAIKAN] Langkah 3: Update Database dengan Pengecekan Klien Aktif ---
try {
    // Siapkan perintah SQL (prepared statement untuk keamanan)
    // [PERBAIKAN] Query ini HANYA akan berhasil jika sensor MAC ada DAN client.is_active = 1
    $stmt = $pdo->prepare("
        UPDATE sensors s
        JOIN clients c ON s.client_id = c.id
        SET s.last_seen = :last_seen
        WHERE s.mac_address = :mac
          AND c.is_active = 1
    ");

    // Eksekusi perintah dengan data yang aman (timestamp saat ini dan MAC yang sudah diformat)
    $params = [
        ':last_seen' => $current_time,
        ':mac' => $formatted_mac
    ];

    if ($stmt->execute($params)) {
        // Periksa apakah ada baris yang benar-benar diperbarui
        // rowCount() > 0 berarti sensor ditemukan DAN kliennya aktif
        if ($stmt->rowCount() > 0) {
            http_response_code(200); // OK
            header('Content-Type: application/json');
            // Kirim respons sukses
            echo json_encode(['status' => 'success', 'message' => 'Heartbeat received for ' . htmlspecialchars($formatted_mac)]);
        } else {
            // Gagal: Sensor tidak ditemukan ATAU Klien tidak aktif
            http_response_code(404); // Not Found (atau 403 Forbidden, tapi 404 lebih sederhana untuk ESP32)
            header('Content-Type: application/json');
            // Catat error di log server untuk pemantauan
            error_log("Heartbeat failed: Sensor MAC not found in DB or client is inactive - " . $formatted_mac . " (Raw: " . htmlspecialchars($sensor_mac_raw) . ")");
            // Kirim pesan error
            echo json_encode(['status' => 'error', 'message' => 'Sensor MAC address ' . htmlspecialchars($formatted_mac) . ' not found or client account is inactive.']);
        }
    } else {
        // Gagal mengeksekusi perintah UPDATE (error SQL)
        http_response_code(500); // Internal Server Error
        header('Content-Type: application/json');
        // Catat error SQL detail ke log server
        error_log("Heartbeat failed: Database execute error for MAC - " . $formatted_mac . " Error Info: " . implode(" | ", $stmt->errorInfo()));
        // Kirim pesan error generik
        echo json_encode(['status' => 'error', 'message' => 'Failed to update database status. Check server logs.']);
    }
} catch (PDOException $e) {
    // Tangkap error koneksi atau query database lainnya
    error_log("Heartbeat Database PDOException: " . $e->getMessage()); // Catat error detail ke log server
    http_response_code(500); // Internal Server Error
    header('Content-Type: application/json');
    // Kirim pesan error umum
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred on the server.']);
}
?>

