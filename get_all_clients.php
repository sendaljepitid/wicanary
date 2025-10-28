<?php
session_start();
header('Content-Type: application/json'); // Set header agar browser tahu ini JSON

// 1. Keamanan: Cek apakah Super Admin sudah login
if (!isset($_SESSION['super_admin_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Akses ditolak. Anda bukan Super Admin.']);
    exit;
}

// 2. Sertakan koneksi database
require 'db_config.php';

// 3. Siapkan array untuk respons
$response = [
    'clients' => [],
    'packages' => []
];

try {
    // 4. Ambil data SEMUA KLIEN (digabung dengan data paket)
    // Ini digunakan untuk mengisi tabel di super_dashboard.php
    $stmt_clients = $pdo->query("
        SELECT 
            c.id, c.company_name, c.email, c.package_id, 
            c.subscription_end_date, c.is_active,
            p.name AS package_name, p.max_sensors 
        FROM clients c
        LEFT JOIN packages p ON c.package_id = p.id
        ORDER BY c.company_name ASC
    ");
    $response['clients'] = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);

    // 5. Ambil data SEMUA PAKET
    // Ini digunakan untuk mengisi dropdown <select> di modal "Edit Klien"
    $stmt_packages = $pdo->query("
        SELECT id, name, max_sensors 
        FROM packages 
        ORDER BY id ASC
    ");
    $response['packages'] = $stmt_packages->fetchAll(PDO::FETCH_ASSOC);

    // 6. Kirim respons sebagai JSON
    echo json_encode($response);

} catch (PDOException $e) {
    // 7. Tangani jika ada error database
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Gagal mengambil data dari database: ' . $e->getMessage()]);
}

?>