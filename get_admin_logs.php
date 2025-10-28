<?php
// get_admin_logs.php (Versi MySQL Multi-Tenant)
session_start();

// [PERBAIKAN] Keamanan: Periksa login, DAN client_id
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['client_id'])) {
    http_response_code(403); // Forbidden
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Akses ditolak. Sesi tidak valid atau client ID tidak ditemukan.']));
}

require_once 'db_config.php'; // Koneksi MySQL ($pdo)

// Ambil client_id dari sesi
$client_id = $_SESSION['client_id'];

try {
    // [PERBAIKAN] Query difilter berdasarkan client_id
    // Mengambil 100 log terbaru HANYA untuk klien ini
    // Melakukan LEFT JOIN untuk mendapatkan nama sensor (jika ada)
    $stmt = $pdo->prepare("
        SELECT l.*, s.name as sensor_name
        FROM logs l
        LEFT JOIN sensors s ON l.sensor_mac = s.mac_address AND l.client_id = s.client_id
        WHERE l.client_id = :client_id
        ORDER BY l.id DESC
        LIMIT 100
    ");

    // Eksekusi query dengan client_id yang aman
    if ($stmt->execute([':client_id' => $client_id])) {
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($logs);
    } else {
        // Gagal eksekusi query
        http_response_code(500); // Internal Server Error
        header('Content-Type: application/json');
        error_log("Get Admin Logs Error: Failed to execute query for client ID {$client_id}. Error: " . implode(":", $stmt->errorInfo()));
        echo json_encode(['error' => 'Gagal mengambil log dari database.']);
    }

} catch (PDOException $e) {
    // Tangkap error database
    error_log("Get Admin Logs PDOException for client ID {$client_id}: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Terjadi kesalahan pada server database.']);
}
?>
