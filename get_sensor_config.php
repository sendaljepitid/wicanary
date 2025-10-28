<?php
// get_sensor_config.php
require_once 'db_config.php'; // Sertakan koneksi database ($pdo)

// --- Langkah 1: Ambil dan Validasi MAC Address ---
$mac_address_raw = $_GET['mac'] ?? null;

// Periksa apakah parameter 'mac' ada
if (!$mac_address_raw) {
    http_response_code(400); // Bad Request
    header('Content-Type: application/json'); // Set header sebelum output
    // Hentikan eksekusi dan kirim pesan error JSON
    die(json_encode(['error' => 'Parameter MAC address (?mac=...) tidak ditemukan dalam permintaan.']));
}

// Bersihkan input: Hapus semua karakter non-heksadesimal (termasuk titik dua, spasi, dll.)
// dan ubah ke huruf besar untuk konsistensi.
$mac_address_cleaned = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac_address_raw));

// [PENYEMPURNAAN] Validasi panjang MAC address (harus tepat 12 karakter hex)
if (strlen($mac_address_cleaned) !== 12) {
    http_response_code(400); // Bad Request
    header('Content-Type: application/json');
    // Catat error di log server untuk debugging internal
    error_log("Get Sensor Config Error: Invalid MAC format. Raw: " . htmlspecialchars($mac_address_raw) . ", Cleaned: " . $mac_address_cleaned);
    // Kirim pesan error yang lebih informatif ke ESP32
    die(json_encode(['error' => 'Format MAC address tidak valid. Diharapkan 12 karakter heksadesimal (misal: AABBCCDDEEFF).']));
}

// Format ulang ke format standar XX:XX:XX:XX:XX:XX untuk pencarian di database
// Ini aman dilakukan karena kita sudah memastikan panjangnya 12 karakter.
$formatted_mac = implode(':', str_split($mac_address_cleaned, 2));

// --- Langkah 2: Ambil Konfigurasi dari Database ---
try {
    // Siapkan query untuk mengambil semua kolom konfigurasi dari tabel 'sensors'
    // Pastikan nama kolom di SELECT cocok persis dengan nama kolom di tabel MySQL Anda
    $stmt = $pdo->prepare("SELECT
                                config_max_deauth_score,
                                config_max_beacon_count,
                                config_max_probe_req_count,
                                config_max_auth_req_count,
                                config_max_assoc_req_count,
                                config_max_rts_count,
                                config_max_cts_count,
                                config_check_interval_ms
                           FROM sensors
                           WHERE mac_address = :mac");

    // Eksekusi query dengan MAC address yang sudah diformat dan divalidasi
    if ($stmt->execute([':mac' => $formatted_mac])) {
        $config = $stmt->fetch(PDO::FETCH_ASSOC); // Ambil satu baris hasil sebagai array asosiatif

        if ($config) {
            // Jika sensor ditemukan, kirim konfigurasinya
            header('Content-Type: application/json');

            // [PENYEMPURNAAN] Pastikan semua nilai adalah integer, tangani NULL dengan default 0
            foreach ($config as $key => &$value) { // Gunakan referensi (&) untuk mengubah array asli
                 // Jika nilai dari DB adalah NULL (misalnya kolom baru belum diisi),
                 // berikan default 0 sebelum konversi ke integer.
                 $value = (int)($value ?? 0);
            }
            unset($value); // Hapus referensi setelah loop selesai (praktik baik)

            echo json_encode($config); // Kirim JSON ke ESP32

        } else {
            // Sensor dengan MAC tersebut tidak terdaftar di database
            http_response_code(404); // Not Found
            header('Content-Type: application/json');
            // Catat error di log server untuk pemantauan
            error_log("Config request failed: Sensor MAC not found in DB - " . $formatted_mac . " (Raw input: " . htmlspecialchars($mac_address_raw) . ")");
            // Kirim pesan error JSON
            echo json_encode(['error' => 'Sensor dengan MAC ' . htmlspecialchars($formatted_mac) . ' tidak terdaftar.']);
        }

    } else {
         // Jika query gagal dieksekusi (error SQL)
        http_response_code(500); // Internal Server Error
        header('Content-Type: application/json');
        // Catat error SQL detail ke log server
        error_log("Get Sensor Config DB execute error: " . implode(":", $stmt->errorInfo()) . " | MAC: " . $formatted_mac);
        // Kirim pesan error generik
        echo json_encode(['error' => 'Gagal mengeksekusi query database. Periksa log server.']);
    }

} catch (PDOException $e) {
    // Tangkap error koneksi atau query database lainnya
    error_log("Get Sensor Config PDOException: " . $e->getMessage()); // Catat error detail ke log server
    http_response_code(500); // Internal Server Error
    header('Content-Type: application/json');
    // Kirim pesan error umum ke ESP32
    echo json_encode(['error' => 'Terjadi kesalahan pada server database. Periksa log server.']);
}
?>

