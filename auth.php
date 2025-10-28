<?php
// auth.php (Versi MySQL Multi-Tenant + Super Admin)
require_once 'security_utils.php'; // Handle session start and rate limiting
apply_rate_limiting(5, 60); // Batasi 5 percobaan login per 60 detik per IP

require_once 'db_config.php'; // Gunakan koneksi MySQL ($pdo)

/**
 * Fungsi untuk membuat URL absolut
 * @param string $path Path relatif (cth: 'admin.php')
 * @return string URL absolut
 */
function get_absolute_url($path) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    // Gunakan dirname() untuk mendapatkan path direktori dengan aman
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    // Bersihkan trailing slash jika ada, dan pastikan format path benar
    $script_dir = rtrim($script_dir, '/\\');
    // Tambahkan slash hanya jika script_dir bukan root
    if ($script_dir == '') {
        $script_dir = '/';
    } else {
         $script_dir .= '/';
    }
    return "{$protocol}://{$host}{$script_dir}{$path}";
}

// Logika anti-brute-force
$max_attempts = 5;
$lockout_time = 300; // 5 menit

// Periksa apakah user sedang dalam masa lockout
if (isset($_SESSION['last_attempt_time']) && (time() - $_SESSION['last_attempt_time'] < $lockout_time)) {
    $_SESSION['error'] = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($lockout_time / 60) . ' menit.';
    header('Location: ' . get_absolute_url('login.php'));
    exit;
}

// Ambil username dan password dari POST request
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Validasi dasar input
if (empty($username) || empty($password)) {
     $_SESSION['error'] = 'Username dan password tidak boleh kosong.';
     header('Location: ' . get_absolute_url('login.php'));
     exit;
}

try {
    // --- [PERBAIKAN] Langkah 1: Coba login sebagai Pengguna Klien ---
    $stmt = $pdo->prepare("SELECT id, client_id, username, password, role FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // BERHASIL login sebagai Pengguna Klien
        
        // Reset proteksi brute-force
        unset($_SESSION['login_attempts'], $_SESSION['last_attempt_time']);

        // Regenerasi session ID untuk keamanan (mencegah session fixation)
        session_regenerate_id(true);

        // Simpan informasi penting ke sesi
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // (administrator, auditor, operator)
        $_SESSION['client_id'] = $user['client_id']; // [PENTING] Simpan ID Klien

        // Arahkan ke halaman admin
        header('Location: ' . get_absolute_url('admin.php'));
        exit;
    }

    // --- [PERBAIKAN] Langkah 2: Jika gagal, coba login sebagai Super Admin ---
    $stmt_super = $pdo->prepare("SELECT id, username, password FROM super_admins WHERE username = :username");
    $stmt_super->execute([':username' => $username]);
    $super_admin = $stmt_super->fetch(PDO::FETCH_ASSOC);

    if ($super_admin && password_verify($password, $super_admin['password'])) {
        // BERHASIL login sebagai Super Admin
        
        // Reset proteksi brute-force
        unset($_SESSION['login_attempts'], $_SESSION['last_attempt_time']);
        
        // Regenerasi session ID
        session_regenerate_id(true);

        // Simpan informasi penting ke sesi
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $super_admin['id'];
        $_SESSION['username'] = $super_admin['username'];
        $_SESSION['role'] = 'superadmin'; // Peran khusus
        // Perhatikan: 'client_id' TIDAK di-set untuk super admin
        // Ini adalah cara dasbor admin membedakan Anda dari admin klien

        header('Location: ' . get_absolute_url('admin.php')); // Arahkan ke dasbor yang sama
        exit;
    }

    // --- Jika semua gagal: Login GAGAL ---
    
    // Implementasi proteksi brute-force
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
    }
    $_SESSION['login_attempts']++;

    if ($_SESSION['login_attempts'] >= $max_attempts) {
        $_SESSION['last_attempt_time'] = time(); // Mulai lockout
        $_SESSION['error'] = 'Username atau password salah. Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($lockout_time / 60) . ' menit.';
    } else {
        $_SESSION['error'] = 'Username atau password salah.';
    }
    
    // Arahkan kembali ke halaman login
    header('Location: ' . get_absolute_url('login.php'));
    exit;

} catch (PDOException $e) {
    // Tangani error database
    error_log("Login PDOException: " . $e->getMessage()); // Catat error
    $_SESSION['error'] = 'Terjadi kesalahan pada server. Silakan coba lagi.';
    header('Location: ' . get_absolute_url('login.php'));
    exit;
}
?>

