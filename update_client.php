<?php
session_start();
header('Content-Type: application/json');

// 1. Keamanan: Cek apakah SUPER ADMIN sudah login
if (!isset($_SESSION['super_admin_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda bukan Super Admin.']);
    exit;
}

// 2. Sertakan koneksi database
require 'db_config.php';

// 3. Ambil data dari POST
$client_id = $_POST['client_id'] ?? null;
$company_name = $_POST['company_name'] ?? null;
$email = $_POST['email'] ?? null;
$package_id = $_POST['package_id'] ?? null;
$subscription_end_date = $_POST['subscription_end_date'] ?? null;

// 4. Penanganan khusus untuk checkbox 'is_active'
// Jika checkbox dicentang, nilainya '1'. Jika tidak, datanya tidak akan terkirim.
$is_active = isset($_POST['is_active']) ? 1 : 0;

// 5. Validasi
if (empty($client_id) || empty($company_name) || empty($email) || empty($package_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'ID Klien, Nama, Email, dan Paket tidak boleh kosong.']);
    exit;
}

// Jika tanggal dikosongkan, set ke NULL
if (empty($subscription_end_date)) {
    $subscription_end_date = NULL;
}

try {
    // 6. Siapkan SQL Update
    $sql = "
        UPDATE clients SET
            company_name = ?,
            email = ?,
            package_id = ?,
            subscription_end_date = ?,
            is_active = ?
        WHERE 
            id = ?
    ";
    
    $stmt_update = $pdo->prepare($sql);
    $stmt_update->execute([
        $company_name,
        $email,
        $package_id,
        $subscription_end_date,
        $is_active,
        $client_id
    ]);

    // 7. Cek apakah ada baris yang ter-update
    $affected_rows = $stmt_update->rowCount();

    if ($affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Data klien berhasil diperbarui!']);
    } else {
        echo json_encode(['status' => 'info', 'message' => 'Tidak ada perubahan data yang disimpan.']);
    }

} catch (PDOException $e) {
    // Tangani error duplikat email (karena email UNIQUE)
    if ($e->errorInfo[1] == 1062) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'error', 'message' => 'Gagal: Email tersebut sudah digunakan oleh klien lain.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>