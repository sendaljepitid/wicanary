<?php
session_start(); // Mulai session

// Hapus semua variabel session
$_SESSION = [];

// Hancurkan session
session_unset();
session_destroy();

// Arahkan kembali ke halaman login klien sebagai default
header("Location: login.php");
exit;
?>