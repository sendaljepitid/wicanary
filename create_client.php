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
$company_name = $_POST['company_name'] ?? null;
$email = $_POST['email'] ?? null;
$package_id = $_POST['package_id'] ?? null;
$subscription_end_date = $_POST['subscription_end_date'] ?? null;

// Data untuk admin klien
$admin_username = $_POST['admin_username'] ?? null;
$admin_password = $_POST['admin_password'] ?? null;

// 4. Validasi
if (empty($company_name) || empty($email) || empty($package_id) || empty($admin_username) || empty($admin_password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi (kecuali tgl. akhir).']);
    exit;
}

// Jika tanggal dikosongkan, set ke NULL
if (empty($subscription_end_date)) {
    $subscription_end_date = NULL;
}

// 5. Gunakan TRANSAKSI DATABASE
// Ini penting. Jika gagal membuat user, data klien juga akan dibatalkan.
try {
    $pdo->beginTransaction();

    // --- Langkah A: Masukkan Klien Baru ---
    $sql_client = "
        INSERT INTO clients (company_name, email, package_id, subscription_end_date, is_active)
        VALUES (?, ?, ?, ?, 1) -- Langsung set aktif
    ";
    $stmt_client = $pdo->prepare($sql_client);
    $stmt_client->execute([
        $company_name,
        $email,
        $package_id,
        $subscription_end_date
    ]);

    // Ambil ID klien yang baru saja dibuat
    $new_client_id = $pdo->lastInsertId();

    // --- Langkah B: Buat Admin untuk Klien Tersebut ---
    $hashed_password = password_hash($admin_password, PASSWORD_BCRYPT);
    
    $sql_user = "
        INSERT INTO users (client_id, username, password, role)
        VALUES (?, ?, ?, 'administrator')
    ";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([
        $new_client_id,
        $admin_username,
        $hashed_password
    ]);

    // --- Langkah C: Jika semua berhasil, commit ---
    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Klien dan Admin Klien berhasil dibuat.']);

} catch (PDOException $e) {
    // 6. Tangani jika ada error (Batalkan transaksi)
    $pdo->rollBack();

    // Cek error duplikat (Error code 1062)
    if ($e->errorInfo[1] == 1062) {
        if (str_contains($e->getMessage(), 'clients.email')) {
             echo json_encode(['status' => 'error', 'message' => 'Gagal: Email perusahaan tersebut sudah terdaftar.']);
        } elseif (str_contains($e->getMessage(), 'users.username')) {
             echo json_encode(['status' => 'error', 'message' => 'Gagal: Username admin tersebut sudah digunakan.']);
        } else {
             echo json_encode(['status' => 'error', 'message' => 'Gagal: Data duplikat.']);
        }
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>