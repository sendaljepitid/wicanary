    <?php
    // db_config.php
    
    // Konfigurasi koneksi ke database MySQL
    $db_host = 'localhost';        // Atau alamat IP/hostname server MySQL Anda
    $db_name = 'wicanary_business'; // Nama database yang baru dibuat
    $db_user = 'root';    // GANTI: Username database MySQL Anda
    $db_pass = '';    // GANTI: Password database MySQL Anda
    $db_charset = 'utf8mb4';
    
    // Opsi koneksi PDO
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Laporkan error sebagai exception
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Hasil query sebagai associative array
        PDO::ATTR_EMULATE_PREPARES   => false,                // Gunakan native prepared statements
    ];
    
    // String koneksi (DSN - Data Source Name)
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    
    // Variabel global untuk koneksi PDO
    $pdo = null;
    
    try {
        // Buat instance koneksi PDO
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        // Set timezone sesi MySQL ke Asia/Jakarta (sesuai setting PHP Anda)
        $pdo->exec("SET time_zone = '+07:00'"); 
    
    } catch (\PDOException $e) {
        // Jika koneksi gagal, catat error dan hentikan eksekusi
        error_log("Koneksi database gagal: " . $e->getMessage()); // Catat error ke log server
        // Jangan tampilkan detail error ke pengguna
        http_response_code(500); // Internal Server Error
        // Berikan pesan error generik
        die("Terjadi masalah koneksi ke database. Silakan coba lagi nanti.");
    }
    
    // Catatan: Kode pembuatan tabel SQLite otomatis sudah tidak diperlukan lagi.
    // Jika Anda ingin memastikan tabel ada, bisa tambahkan pengecekan sederhana,
    // tapi idealnya struktur tabel dikelola melalui migrasi atau SQL manual.
    
    ?>
    
