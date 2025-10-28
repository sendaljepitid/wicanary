<?php
// Pastikan session dimulai di paling awal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk redirect relatif (lebih sederhana)
function redirect($path) {
    // Dapatkan base path dari direktori saat ini
    $host  = $_SERVER['HTTP_HOST'];
    $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    // Tentukan protokol
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";

    // Tambahkan slash hanya jika script_dir bukan root
    if ($uri == '') {
        $uri = '/';
    } else {
         $uri .= '/';
    }

    header("Location: {$protocol}://{$host}{$uri}{$path}");
    exit;
}

// Jika sudah login, langsung arahkan ke admin.php
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    redirect('admin.php');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - WiCanary Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; height: 100vh; background-color: #f8f9fa; }
        .login-card { width: 100%; max-width: 400px; padding: 2rem; border-radius: 1rem; box-shadow: 0 8px 24px rgba(0,0,0,.1); }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="card-body">
            <div class="text-center mb-4">
                <h1 class="h3 mb-3 fw-bold"><i class="fas fa-broadcast-tower me-2 text-primary"></i>WiCanary Login</h1>
                <p class="text-muted">Akses dasbor administrator</p>
            </div>
            <?php
            // Tampilkan pesan error jika ada
            if (isset($_SESSION['error'])) {
                // Gunakan htmlspecialchars untuk mencegah XSS pada pesan error
                echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['error']) . '</div>';
                // Hapus pesan error setelah ditampilkan
                unset($_SESSION['error']);
            }
            ?>
            <form method="POST" action="auth.php">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus> <!-- Tambah autofocus -->
                    <label for="username">Username</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password">Password</label>
                </div>
                <button class="w-100 btn btn-lg btn-primary" type="submit">Login</button>
            </form>
        </div>
    </div>
</body>
</html>

