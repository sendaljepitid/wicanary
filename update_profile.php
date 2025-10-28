<?php
session_start();
header('Content-Type: application/json');

// 1. Keamanan: Cek apakah KLIEN sudah login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Silakan login.']);
    exit;
}

// 2. Ambil ID Pengguna dari Session
$user_id = $_SESSION['user_id'];

// 3. Sertakan koneksi database
require 'db_config.php';

// 4. Ambil data dari POST
$new_password = $_POST['new_password'] ?? null;

// 5. Validasi
if (empty($new_password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Password baru tidak boleh kosong.']);
    exit;
}
if (strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password baru minimal harus 6 karakter.']);
    exit;
}

try {
    // 6. Hash password baru
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // 7. Update password di database
    $sql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hashed_password, $user_id]);

    // 8. Kirim respons sukses
    echo json_encode(['status' => 'success', 'message' => 'Password berhasil diperbarui.']);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>