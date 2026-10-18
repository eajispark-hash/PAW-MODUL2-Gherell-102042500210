<?php
require 'koneksi.php';


function hitung_data(mysqli $conn, string $tabel): int {
    $tabel_valid = ['mata_kuliah', 'materi', 'tugas'];
    if (!in_array($tabel, $tabel_valid, true)) {
        return 0;
    }
    $sql = "SELECT COUNT(*) AS total FROM {$tabel}";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $hasil = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$hasil['total'];
}

$jml_mk     = hitung_data($conn, 'mata_kuliah');
$jml_materi = hitung_data($conn, 'materi');
$jml_tugas  = hitung_data($conn, 'tugas');


$stmt = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah ORDER BY kode ASC');
$stmt->execute();
$daftar_mk = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Beranda - Learning Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">LMS Universitas NCIT</a>
        <div class="nav-links">
            <a href="index.php" class="active">Beranda</a>
            <a href="mata_kuliah.php">Mata kuliah</a>
            <a href="materi.php">Materi</a>
            <a href="tugas.php">Tugas</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card">
        <h1>Learning Management System</h1>
        <p class="subtitle">Pengelolaan mata kuliah, materi pembelajaran, dan tugas.</p>

        
        <div class="summary-grid">
            <a href="mata_kuliah.php" class="summary-box">
                <div class="summary-title">Mata kuliah</div>
                <div class="summary-count"><?= e($jml_mk) ?></div>
            </a>
            <a href="materi.php" class="summary-box">
                <div class="summary-title">Materi</div>
                <div class="summary-count"><?= e($jml_materi) ?></div>
            </a>
            <a href="tugas.php" class="summary-box">
                <div class="summary-title">Tugas</div>
                <div class="summary-count"><?= e($jml_tugas) ?></div>
            </a>
        </div>

        <h2>Mata kuliah tersedia</h2>
        <?php if ($daftar_mk->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Mata kuliah</th>
                        <th>Dosen / Semester</th>
                        <th>Pintasan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $daftar_mk->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= e($row['kode']) ?> - <?= e($row['nama']) ?></strong></td>
                            <td><?= e($row['dosen']) ?> / Semester <?= e($row['semester']) ?></td>
                            <td class="action-links">
                                <a href="mata_kuliah.php?action=detail&id=<?= e($row['id']) ?>">Detail</a>
                                <a href="materi.php?mata_kuliah_id=<?= e($row['id']) ?>">Lihat Materi</a>
                                <a href="tugas.php?mata_kuliah_id=<?= e($row['id']) ?>">Lihat Tugas</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="subtitle">Belum ada mata kuliah yang terdaftar.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>