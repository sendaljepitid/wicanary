<?php
// create_superadmin.php
// Skrip utilitas untuk menambahkan Super Admin pertama secara manual.
// PENTING: Ganti variabel di bawah ini, lalu jalankan skrip ini sekali dari browser.
// Setelah selesai, HAPUS atau UBAH NAMA file ini demi keamanan.

require_once 'db_config.php'; // Menggunakan koneksi MySQL ($pdo)

// --- [ EDIT DATA DI BAWAH INI SESUAI KEBUTUHAN ANDA ] ---

// Tentukan detail login untuk Super Admin (Anda)
$super_admin_username = 'superadmin';             // GANTI: Username yang Anda inginkan
$super_admin_password = 'PasswordSuperAman123!'; // GANTI: Password baru yang kuat

// --- [ SELESAI EDIT ] ---


// --- Logika Pembuatan Super Admin ---
echo "<pre>"; // Mulai preformatted text agar output mudah dibaca

// Validasi dasar
if (empty($super_admin_username) || empty($super_admin_password)) {
    die("Error: Harap isi variabel \$super_admin_username dan \$super_admin_password di dalam skrip `create_superadmin.php`.");
}

// [PENTING] Hash password sebelum disimpan ke database
// Fungsi password_hash() adalah WAJIB agar password_verify() di auth.php berfungsi
$hashed_password = password_hash($super_admin_password, PASSWORD_DEFAULT);
if ($hashed_password === false) {
     die("Error: Gagal melakukan hashing password.");
}
echo "Password berhasil di-hash.\n";

try {
    // Cek apakah tabel super_admins ada
    $pdo->query("SELECT 1 FROM super_admins LIMIT 1");
} catch (PDOException $e) {
    die("Error Fatal: Tabel 'super_admins' tidak ditemukan. Pastikan Anda sudah menjalankan skrip SQL untuk membuat database.\n" . $e->getMessage());
}
echo "Tabel 'super_admins' ditemukan.\n";


try {
    // Cek apakah username sudah ada (karena username di-set UNIQUE)
    $stmt_check = $pdo->prepare("SELECT id FROM super_admins WHERE username = :username");
    $stmt_check->execute([':username' => $super_admin_username]);
    
    if ($stmt_check->fetch()) {
        die("Error: Username '{$super_admin_username}' sudah terdaftar di tabel super_admins. Anda tidak perlu menjalankan skrip ini lagi.\n\nLogin di halaman login.php.");
    }
    echo "Username '{$super_admin_username}' tersedia.\n";

    // Masukkan user baru ke tabel 'super_admins'
    $stmt_insert = $pdo->prepare(
        "INSERT INTO super_admins (username, password) VALUES (:username, :password)"
    );
    
    $params = [
        ':username'  => $super_admin_username,
        ':password'  => $hashed_password, // Simpan password yang sudah di-hash
    ];

    if ($stmt_insert->execute($params)) {
        echo "\n----------------------------------------\n";
        echo "BERHASIL: Super Admin '{$super_admin_username}' telah dibuat.\n";
        echo "----------------------------------------\n\n";
        echo "LANGKAH SELANJUTNYA:\n";
        echo "1. Buka halaman login.php dan masuk dengan akun ini.\n";
        echo "2. HAPUS atau ubah nama file 'create_superadmin.php' ini sekarang juga demi keamanan!";
    } else {
        echo "Gagal membuat super admin. Periksa log error server.";
    }

} catch (PDOException $e) {
    die("Database Error: ". $e->getMessage());
}
echo "</pre>";
?>

