<?php
session_start();
header('Content-Type: application/json');

// 1. Keamanan: Cek Admin Klien
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}
$client_id = $_SESSION['client_id'];

// 2. Sertakan DB Config
require 'db_config.php';

// 3. Ambil Data POST
$username = isset($_POST['username']) ? trim($_POST['username']) : null;
$password = $_POST['password'] ?? null;
$role = $_POST['role'] ?? null; // Harus 'auditor' atau 'operator'

// 4. Validasi Input
if (empty($username) || empty($password) || empty($role)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi.']);
    exit;
}
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password minimal 6 karakter.']);
    exit;
}
if ($role !== 'auditor' && $role !== 'operator') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Peran (Role) tidak valid.']);
    exit;
}

try {
    // 5. Cek Batas 5 Pengguna
    $stmt_count = $pdo->prepare("SELECT COUNT(id) FROM users WHERE client_id = ?");
    $stmt_count->execute([$client_id]);
    $user_count = $stmt_count->fetchColumn();

    if ($user_count >= 5) {
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'Gagal: Anda sudah mencapai batas maksimal 5 pengguna. Hapus pengguna lama jika ingin menambah baru.']);
        exit;
    }

    // 6. Hash Password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // 7. Insert User Baru
    $sql = "INSERT INTO users (client_id, username, password, role) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$client_id, $username, $hashed_password, $role]);

    // 8. Kirim Sukses
    echo json_encode(['status' => 'success', 'message' => 'Pengguna baru berhasil ditambahkan.']);

} catch (PDOException $e) {
    // Tangani error duplikat username
    if ($e->errorInfo[1] == 1062) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'error', 'message' => 'Gagal: Username tersebut sudah digunakan.']);
    } else {
        http_response_code(500);
        error_log("DB Error create_user: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan database.']);
    }
}
?>