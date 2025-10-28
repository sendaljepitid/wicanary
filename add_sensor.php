<?php
// add_sensor.php
require_once 'db_config.php';

// --- DATA SENSOR YANG AKAN DIDAFTARKAN ---
// --- UBAH DATA DI BAWAH INI ---
$sensor_mac = "3C:8A:1F:A8:E7:D0"; // Ganti dengan MAC Address ESP32 Anda
$sensor_name = "Sensor Gedung C";   // Beri nama yang deskriptif
$sensor_lat = -6.221601;              // Ganti dengan Latitude lokasi Anda
// --- UBAH DATA DI BAWAH INI ---
$sensor_mac = "8C:4F:00:10:12:74"; // Ganti dengan MAC Address ESP32 Anda
$sensor_name = "Sensor Gedung B";   // Beri nama yang deskriptif
$sensor_lat = -6.221601;              // Ganti dengan Latitude lokasi Anda
$sensor_lon = 106.940459;             // Ganti dengan Longitude lokasi Anda
// --- UBAH DATA DI BAWAH INI ---
$sensor_mac = "A0:B7:65:0D:55:A4"; // Ganti dengan MAC Address ESP32 Anda
$sensor_name = "Sensor Gedung A";   // Beri nama yang deskriptif
$sensor_lat = -6.221601;              // Ganti dengan Latitude lokasi Anda
$sensor_lon = 106.940459;             // Ganti dengan Longitude lokasi Anda

try {
    // Cek apakah sensor sudah ada
    $stmt = $pdo->prepare("SELECT id FROM sensors WHERE mac_address = ?");
    $stmt->execute([$sensor_mac]);

    if ($stmt->fetch()) {
        echo "Sensor dengan MAC Address {$sensor_mac} sudah terdaftar.";
    } else {
        // Jika belum ada, masukkan sensor baru
        $stmt = $pdo->prepare("INSERT INTO sensors (mac_address, name, latitude, longitude) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sensor_mac, $sensor_name, $sensor_lat, $sensor_lon]);
        echo "Sensor '{$sensor_name}' berhasil didaftarkan.";
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
