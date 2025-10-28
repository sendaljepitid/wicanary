<?php
// Mulai session di paling atas
session_start();

// Jika sudah login, lempar ke super dashboard
if (isset($_SESSION['super_admin_id'])) {
    header("Location: super_dashboard.php");
    exit;
}

// Sertakan file koneksi database
require 'db_config.php';

$error_message = '';

// Cek jika form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        // 1. Cari super admin berdasarkan username
        $stmt = $pdo->prepare("SELECT * FROM super_admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // 2. Verifikasi admin dan password
        if ($admin && password_verify($password, $admin['password'])) {
            
            // 3. Sukses! Simpan data super admin ke session
            $_SESSION['super_admin_id'] = $admin['id'];
            $_SESSION['super_admin_username'] = $admin['username'];

            // Lempar ke super dashboard
            header("Location: super_dashboard.php");
            exit;

        } else {
            // Gagal login
            $error_message = "Username atau password salah!";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Login - WiCanary</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            background-color: #f4f7fa; 
            font-family: 'Inter', sans-serif;
        }
        .login-card { 
            width: 100%; 
            max-width: 400px; 
            padding: 2rem; 
            box-shadow: 0 4px 12px rgba(0,0,0,.05); 
            border: 1px solid #e0e5eb; 
            border-radius: 0.75rem; 
            background-color: #fff; 
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 class="text-center mb-4 fw-bold">
            <i class="fas fa-shield-halved text-danger"></i>
            Super Admin
        </h2>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form action="super_login.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username:</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password:</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div>
                <button type="submit" class="btn btn-primary w-100">