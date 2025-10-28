<?php
// Skrip ini untuk dijalankan oleh Cron Job, bukan oleh browser
echo "Mulai skrip pengingat...\n";

// 1. Load Composer autoloader (jika pakai Composer)
require 'vendor/autoload.php'; 
// 2. Load koneksi database
require 'db_config.php';

// 3. Import class PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 4. Query untuk mencari klien yang akan expire 7 hari lagi
try {
    // Query ini mencari klien yang aktif dan tgl akhirnya = hari_ini + 7 hari
    $sql = "
        SELECT id, company_name, email, subscription_end_date 
        FROM clients 
        WHERE 
            is_active = 1 AND 
            subscription_end_date = CURDATE() + INTERVAL 7 DAY
    ";
    
    $stmt = $pdo->query($sql);
    $clients_to_remind = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($clients_to_remind)) {
        echo "Tidak ada klien yang perlu diingatkan hari ini.\n";
        exit;
    }

    echo "Menemukan " . count($clients_to_remind) . " klien untuk diingatkan:\n";

    // 5. Konfigurasi PHPMailer (atur SMTP Anda)
    // Anda HARUS menggunakan SMTP (misal: Gmail, SendGrid, Mailgun)
    // Jangan gunakan fungsi mail() bawaan PHP.
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // Ganti dengan host SMTP Anda
    $mail->SMTPAuth   = true;
    $mail->Username   = 'email.anda@gmail.com'; // Ganti dengan email Anda
    $mail->Password   = 'password_app_gmail_anda'; // Ganti dengan password Anda
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->setFrom('support@wicanary.com', 'WiCanary Support');
    $mail->isHTML(true); // Kirim sebagai email HTML

    // 6. Loop dan Kirim Email
    foreach ($clients_to_remind as $client) {
        $email_klien = $client['email'];
        $nama_klien = $client['company_name'];
        $tgl_expire = $client['subscription_end_date'];

        echo "- Mengirim ke: $nama_klien ($email_klien)...\n";

        try {
            $mail->clearAddresses(); // Hapus penerima sebelumnya
            $mail->addAddress($email_klien, $nama_klien);

            // Subjek dan Isi Email
            $mail->Subject = 'Peringatan: Langganan WiCanary Anda Akan Berakhir';
            $mail->Body    = "
                Halo $nama_klien,<br><br>
                Ini adalah pengingat bahwa akun WiCanary Anda akan berakhir dalam 7 hari, pada tanggal **$tgl_expire**.<br><br>
                Harap segera perbarui langganan Anda untuk menghindari gangguan layanan.<br><br>
                Terima kasih,<br>
                Tim WiCanary
            ";
            $mail->AltBody = "Halo $nama_klien, Langganan WiCanary Anda akan berakhir pada $tgl_expire. Harap segera perbarui.";

            $mail->send();
            echo "  ...Email terkirim!\n";

        } catch (Exception $e) {
            echo "  ...Gagal mengirim email ke $email_klien. Error: {$mail->ErrorInfo}\n";
        }
    }

    echo "Skrip selesai.\n";

} catch (PDOException $e) {
    echo "Error Database: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error Mailer: " . $e->getMessage() . "\n";
}
?>