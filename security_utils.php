<?php
// security_utils.php

// Memulai sesi dengan aman jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    // [PENYEMPURNAAN] Menambahkan cookie options untuk keamanan
    session_set_cookie_params([
        'lifetime' => 3600, // Waktu hidup cookie sesi (1 jam)
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']), // Kirim hanya lewat HTTPS jika ada
        'httponly' => true, // Cegah akses cookie via JavaScript (XSS)
        'samesite' => 'Strict' // Cegah CSRF
    ]);
    session_start();
}

/**
 * [PENYEMPURNAAN] Fungsi baru untuk mendapatkan IP address pengguna dengan lebih andal.
 * Mendukung server di belakang proxy atau load balancer.
 *
 * @return string Alamat IP pengguna.
 */
function get_ip_address() {
    // Cek header untuk IP asli jika di belakang proxy
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // HTTP_X_FORWARDED_FOR bisa berisi daftar IP, ambil yang pertama
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ip_list[0]);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    } else {
        // Fallback ke REMOTE_ADDR jika tidak ada proxy
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // Validasi akhir untuk memastikan format IP
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    
    // Default ke REMOTE_ADDR jika validasi gagal
    return $_SERVER['REMOTE_ADDR'];
}

/**
 * Menerapkan rate limiting sederhana berbasis Sesi dan IP.
 *
 * @param int $limit Jumlah maksimum percobaan.
 * @param int $period Jangka waktu dalam detik.
 */
function apply_rate_limiting($limit = 10, $period = 60) {
    // [PERBAIKAN] Gunakan fungsi baru untuk mendapatkan IP yang lebih akurat
    $ip = get_ip_address();
    
    // Buat kunci unik berdasarkan IP
    $key = "rate_limit_" . str_replace(['.', ':'], '_', $ip); // Ganti titik dan titik dua

    // Inisialisasi counter di sesi jika belum ada
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'time' => time()];
    }

    // Reset counter jika periode waktu sudah lewat
    if (time() - $_SESSION[$key]['time'] > $period) {
        $_SESSION[$key] = ['count' => 1, 'time' => time()];
    } else {
        // Tambah hitungan jika masih dalam periode waktu
        $_SESSION[$key]['count']++;
    }

    // Blokir jika hitungan melebihi batas
    if ($_SESSION[$key]['count'] > $limit) {
        http_response_code(429); // Too Many Requests
        header('Content-Type: application/json'); // Pastikan header JSON
        // Catat di log server (opsional tapi bagus untuk pemantauan)
        error_log("Rate limiting diterapkan untuk IP: {$ip}. Percobaan: {$_SESSION[$key]['count']}");
        // Kirim respons error
        die(json_encode(['error' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']));
    }
}
?>
