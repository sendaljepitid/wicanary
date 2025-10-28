<?php
// manage_client_user.php
session_start();
require_once 'db_config.php'; // Koneksi MySQL ($pdo)

// Keamanan: Hanya administrator klien yang login yang bisa mengakses
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'administrator' || !isset($_SESSION['client_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda tidak memiliki izin.']));
}

$client_id = $_SESSION['client_id']; // Ambil ID klien dari admin yang login
$action = $_REQUEST['action'] ?? ''; // Tentukan aksi (get_users, add_user, update_user, delete_user)

try {
    switch ($action) {
        
        // --- AKSI: MENDAPATKAN SEMUA PENGGUNA UNTUK KLIEN INI ---
        case 'get_users':
            // Ambil semua pengguna yang terkait dengan client_id ini
            $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE client_id = :client_id ORDER BY username ASC");
            $stmt->execute([':client_id' => $client_id]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'users' => $users]);
            break;

        // --- AKSI: MENAMBAHKAN PENGGUNA BARU ---
        case 'add_user':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            // Validasi
            if (empty($username) || empty($password) || empty($role)) {
                throw new Exception('Semua field (username, password, role) wajib diisi.', 400);
            }
            // Pastikan admin tidak mencoba membuat peran yang tidak valid
            if (!in_array($role, ['administrator', 'auditor', 'operator'])) {
                 throw new Exception('Peran yang dipilih tidak valid.', 400);
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Cek duplikat username
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt_check->execute([':username' => $username]);
            if ($stmt_check->fetch()) {
                 throw new Exception('Username ini sudah digunakan. Silakan pilih yang lain.', 409); // 409 Conflict
            }

            // Masukkan ke database
            $stmt_insert = $pdo->prepare("INSERT INTO users (client_id, username, password, role) VALUES (:client_id, :username, :password, :role)");
            $stmt_insert->execute([
                ':client_id' => $client_id,
                ':username' => $username,
                ':password' => $hashed_password,
                ':role' => $role
            ]);
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Pengguna baru berhasil ditambahkan.']);
            break;
            
        // --- AKSI: MENGAMBIL DETAIL SATU PENGGUNA (untuk modal edit) ---
        case 'get_user_details':
             $user_id = $_GET['user_id'] ?? 0;
             $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = :id AND client_id = :client_id");
             $stmt->execute([':id' => $user_id, ':client_id' => $client_id]);
             $user = $stmt->fetch();
             if (!$user) throw new Exception('Pengguna tidak ditemukan.', 404);
             header('Content-Type: application/json');
             echo json_encode(['status' => 'success', 'data' => $user]);
             break;

        // --- AKSI: MEMPERBARUI PENGGUNA ---
        case 'update_user':
            $user_id = $_POST['user_id'] ?? 0;
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? ''; // Password opsional
            $role = $_POST['role'] ?? '';

            if (empty($user_id) || empty($username) || empty($role)) {
                throw new Exception('ID, Username, dan Role wajib diisi.', 400);
            }
             if (!in_array($role, ['administrator', 'auditor', 'operator'])) {
                 throw new Exception('Peran yang dipilih tidak valid.', 400);
            }

            // Cek duplikat username (pastikan bukan user saat ini)
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $stmt_check->execute([':username' => $username, ':id' => $user_id]);
            if ($stmt_check->fetch()) {
                 throw new Exception('Username ini sudah digunakan oleh pengguna lain.', 409);
            }
            
            // Jika password diisi, perbarui. Jika tidak, biarkan yang lama.
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE users SET username = :username, password = :password, role = :role WHERE id = :id AND client_id = :client_id");
                $stmt_update->execute([
                    ':username' => $username,
                    ':password' => $hashed_password,
                    ':role' => $role,
                    ':id' => $user_id,
                    ':client_id' => $client_id
                ]);
            } else {
                $stmt_update = $pdo->prepare("UPDATE users SET username = :username, role = :role WHERE id = :id AND client_id = :client_id");
                $stmt_update->execute([
                    ':username' => $username,
                    ':role' => $role,
                    ':id' => $user_id,
                    ':client_id' => $client_id
                ]);
            }

            if ($stmt_update->rowCount() > 0) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Pengguna berhasil diperbarui.']);
            } else {
                 throw new Exception('Pengguna tidak ditemukan atau tidak ada perubahan data.', 404);
            }
            break;

        // --- AKSI: MENGHAPUS PENGGUNA ---
        case 'delete_user':
            $user_id = $_POST['user_id'] ?? 0;
            
            // Pengaman: Jangan biarkan admin menghapus dirinya sendiri
            if ($user_id == $_SESSION['user_id']) {
                 throw new Exception('Anda tidak dapat menghapus akun Anda sendiri.', 403);
            }

            $stmt_delete = $pdo->prepare("DELETE FROM users WHERE id = :id AND client_id = :client_id");
            $stmt_delete->execute([':id' => $user_id, ':client_id' => $client_id]);

            if ($stmt_delete->rowCount() > 0) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Pengguna berhasil dihapus.']);
            } else {
                throw new Exception('Pengguna tidak ditemukan atau gagal dihapus.', 404);
            }
            break;

        default:
            throw new Exception('Aksi tidak valid.', 400);
    }
} catch (Exception $e) {
    // Tangani semua error (termasuk validasi dan PDO)
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    header('Content-Type: application/json');
    error_log("User Management Error (Client: {$client_id}): " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
