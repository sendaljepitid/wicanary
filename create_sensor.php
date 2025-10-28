<?php
session_start();
header('Content-Type: application/json');

// --- Konfigurasi & Keamanan Awal ---

// 1. Keamanan: Pastikan hanya Admin Klien yang bisa akses
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda bukan Administrator Klien.']);
    exit;
}

// 2. Ambil ID Klien dari Session
$client_id = $_SESSION['client_id'];

// 3. Sertakan koneksi database
require 'db_config.php'; // Pastikan file ini ada dan benar

// --- Ambil & Validasi Input ---

// 4. Ambil data dari POST request dan bersihkan spasi ekstra
$name = isset($_POST['name']) ? trim($_POST['name']) : null;
$mac_address = isset($_POST['mac_address']) ? trim($_POST['mac_address']) : null;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

// 5. Validasi input wajib diisi
if (empty($name) || empty($mac_address) || empty($latitude) || empty($longitude)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Semua field (Nama, MAC, Lat, Lon) wajib diisi.']);
    exit;
}

// 6. Validasi format MAC Address
if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac_address)) {
     http_response_code(400);
     echo json_encode(['status' => 'error', 'message' => 'Format MAC Address tidak valid. Gunakan XX:XX:XX:XX:XX:XX']);
     exit;
}
// Standarkan format MAC ke huruf besar
$mac_address = strtoupper($mac_address);

// --- Logika Inti & Interaksi Database ---

try {
    // 7. Cek Kuota Sensor Klien
    // Query untuk mendapatkan batas sensor dari paket klien
    $stmt_check_quota = $pdo->prepare("
        SELECT p.max_sensors
        FROM clients c
        JOIN packages p ON c.package_id = p.id
        WHERE c.id = ?
    ");
    $stmt_check_quota->execute([$client_id]);
    $package_info = $stmt_check_quota->fetch();

    if (!$package_info) {
        // Jika data klien/paket tidak ditemukan (seharusnya tidak terjadi)
        throw new Exception("Gagal memverifikasi paket langganan klien.");
    }
    $max_sensors_allowed = $package_info['max_sensors'];

    // Query untuk menghitung jumlah sensor klien saat ini
    $stmt_count_sensors = $pdo->prepare("SELECT COUNT(id) FROM sensors WHERE client_id = ?");
    $stmt_count_sensors->execute([$client_id]);
    $current_sensor_count = $stmt_count_sensors->fetchColumn(); // Ambil nilai COUNT

    // Bandingkan kuota
    if ($current_sensor_count >= $max_sensors_allowed) {
        http_response_code(402); // Payment Required (atau 403 Forbidden)
        echo json_encode([
            'status' => 'error',
            'message' => "Gagal: Kuota sensor Anda ($max_sensors_allowed sensor) sudah penuh. Hapus sensor lama atau upgrade paket."
        ]);
        exit;
    }

    // 8. Kuota tersedia, Generate Token Unik untuk Sensor
    // Menggunakan random_bytes untuk keamanan kriptografi
    $sensor_token = bin2hex(random_bytes(32)); // 64 karakter hex

    // 9. Masukkan Data Sensor Baru ke Database (termasuk token)
    $stmt_insert_sensor = $pdo->prepare("
        INSERT INTO sensors
            (client_id, name, mac_address, latitude, longitude, sensor_token)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");
    $stmt_insert_sensor->execute([
        $client_id,
        $name,
        $mac_address,
        $latitude,
        $longitude,
        $sensor_token // Simpan token yang baru dibuat
    ]);

    // 10. Kirim Respons Sukses
    echo json_encode(['status' => 'success', 'message' => 'Sensor baru berhasil ditambahkan!']);

} catch (PDOException $e) {
    // --- Penanganan Error Database ---
    if ($e->errorInfo[1] == 1062) { // Kode error MySQL untuk duplicate entry
        http_response_code(409); // Conflict
        // Cek constraint mana yang dilanggar
        if (str_contains($e->getMessage(), 'uk_client_mac')) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal: MAC Address ini sudah terdaftar untuk akun Anda.']);
        } elseif (str_contains($e->getMessage(), 'sensor_token')) {
             // Sangat jarang terjadi, tapi tangani untuk jaga-jaga
             echo json_encode(['status' => 'error', 'message' => 'Gagal: Terjadi konflik token internal. Silakan coba lagi.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal: Data duplikat terdeteksi (Error Code 1062).']);
        }
    } else {
        // Error database lainnya
        http_response_code(500); // Internal Server Error
        error_log("PDOException in create_sensor.php: " . $e->getMessage()); // Catat error detail di log server
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan pada database saat menyimpan sensor.']);
    }
} catch (Exception $e) {
    // --- Penanganan Error Umum Lainnya ---
    http_response_code(500); // Internal Server Error
    error_log("Exception in create_sensor.php: " . $e->getMessage()); // Catat error detail di log server
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi nanti.']);
}
?>