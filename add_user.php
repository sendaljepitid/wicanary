<?php
// create_admin.php
// Skrip utilitas untuk mendaftarkan KLIEN BARU dan ADMIN PERTAMA mereka.
// PENTING: Ganti variabel di bawah ini, lalu jalankan skrip ini sekali dari browser.
// Setelah selesai, HAPUS atau UBAH NAMA file ini demi keamanan.

require_once 'db_config.php'; // Menggunakan koneksi MySQL ($pdo)

// --- [ EDIT DATA DI BAWAH INI SESUAI KEBUTUHAN KLIEN BARU ] ---

// 1. Tentukan detail Klien Baru
$company_name_to_add = 'PT Klien Percobaan'; // GANTI: Nama perusahaan klien
$contact_email_to_add = 'kontak@klienpercobaan.com'; // GANTI: Email kontak klien (harus unik)
$package_id_to_add = 1; // GANTI: ID Paket (dari tabel 'packages'. 1 = Basic, 2 = Medium, dst.)
$subscription_end_date = '2025-12-31'; // GANTI: Tanggal kedaluwarsa langganan

// 2. Tentukan detail login untuk ADMIN PERTAMA klien ini
$admin_username_to_add = 'admin_percobaan'; // GANTI: Username admin (harus unik)
$admin_password_to_add = 'PasswordAdminKuat456!'; // GANTI: Password admin

// --- [ SELESAI EDIT ] ---


// --- Logika Pembuatan Klien & Admin ---
echo "<pre>"; // Mulai preformatted text agar output mudah dibaca

// Validasi dasar
if (empty($company_name_to_add) || empty($contact_email_to_add) || empty($package_id_to_add) || empty($admin_username_to_add) || empty($admin_password_to_add)) {
    die("Error: Harap isi semua variabel di dalam skrip `create_admin.php`.");
}

// Hash password admin
$hashed_password = password_hash($admin_password_to_add, PASSWORD_DEFAULT);
if ($hashed_password === false) {
     die("Error: Gagal melakukan hashing password.");
}
echo "Password admin berhasil di-hash.\n";

// Memulai Transaksi Database: Ini penting.
// Jika pembuatan user gagal, pendaftaran klien juga akan dibatalkan.
$pdo->beginTransaction();

try {
    // Cek duplikat (email klien atau username admin)
    $stmt_check_email = $pdo->prepare("SELECT id FROM clients WHERE email = :email");
    $stmt_check_email->execute([':email' => $contact_email_to_add]);
    if ($stmt_check_email->fetch()) {
        throw new Exception("Email klien '{$contact_email_to_add}' sudah terdaftar.");
    }
    echo "Email klien tersedia.\n";

    $stmt_check_user = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt_check_user->execute([':username' => $admin_username_to_add]);
    if ($stmt_check_user->fetch()) {
        throw new Exception("Username admin '{$admin_username_to_add}' sudah terdaftar.");
    }
    echo "Username admin tersedia.\n";

    // Langkah 1: Masukkan Klien baru ke tabel 'clients'
    $stmt_insert_client = $pdo->prepare(
        "INSERT INTO clients (company_name, email, package_id, subscription_end_date, is_active) 
         VALUES (:company_name, :email, :package_id, :subscription_end_date, 1)"
    );
    $client_params = [
        ':company_name' => $company_name_to_add,
        ':email' => $contact_email_to_add,
        ':package_id' => $package_id_to_add,
        ':subscription_end_date' => $subscription_end_date
    ];
    $stmt_insert_client->execute($client_params);
    
    // Dapatkan ID dari klien yang baru saja dibuat
    $new_client_id = $pdo->lastInsertId();
    echo "Klien '{$company_name_to_add}' berhasil dibuat dengan ID: {$new_client_id}.\n";

    // Langkah 2: Masukkan Admin baru ke tabel 'users'
    $stmt_insert_user = $pdo->prepare(
        "INSERT INTO users (client_id, username, password, role) 
         VALUES (:client_id, :username, :password, 'administrator')" // Role di-hardcode sebagai 'administrator'
    );
    $user_params = [
        ':client_id' => $new_client_id,
        ':username'  => $admin_username_to_add,
        ':password'  => $hashed_password
    ];
    $stmt_insert_user->execute($user_params);
    echo "Admin '{$admin_username_to_add}' untuk klien ID {$new_client_id} berhasil dibuat.\n";

    // Jika semua berhasil, konfirmasi transaksi
    $pdo->commit();

    echo "\n----------------------------------------\n";
    echo "BERHASIL: Klien dan Admin berhasil dibuat.\n";
    echo "----------------------------------------\n\n";
    echo "PENTING: Hapus atau ubah nama file 'create_admin.php' ini sekarang juga demi keamanan!";

} catch (Exception $e) {
    // Jika terjadi error, batalkan semua perubahan (rollback)
    $pdo->rollBack();
    die("Database Error: ". $e->getMessage());
}
echo "</pre>";
?>
