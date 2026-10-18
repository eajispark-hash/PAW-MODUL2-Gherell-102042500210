<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


mysqli_report(MYSQLI_REPORT_OFF);

$host = 'localhost';
$user = 'root';
$pass = ''; 
$db   = 'db_lms_paw';

$conn = new mysqli($host, $user, $pass, $db);


if ($conn->connect_error) {
    die('Koneksi database gagal: ' . htmlspecialchars($conn->connect_error));
}

$conn->set_charset('utf8mb4');


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}


function set_flash($tipe, $pesan) {
    $_SESSION['flash'] = [
        'tipe'  => $tipe,
        'pesan' => $pesan
    ];
}


function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
?>