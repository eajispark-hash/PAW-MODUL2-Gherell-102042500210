<?php
require 'koneksi.php';

$action = $_GET['action'] ?? 'list';
$errors = [];
$pesan_error_umum = '';

$form = [
    'kode'     => '',
    'nama'     => '',
    'dosen'    => '',
    'semester' => ''
];


function ambil_id_valid($nilai) {
    if ($nilai === null || !ctype_digit((string)$nilai) || (int)$nilai <= 0) {
        return null;
    }
    return (int)$nilai;
}


function cari_mata_kuliah(mysqli $conn, int $id) {
    $stmt = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';


    if ($post_action === 'create' || $post_action === 'edit') {
        $id = ($post_action === 'edit') ? ambil_id_valid($_POST['id'] ?? null) : null;
        if ($post_action === 'edit' && !$id) {
            set_flash('error', 'ID mata kuliah tidak valid.');
            header('Location: mata_kuliah.php');
            exit;
        }

        $form['kode']     = trim($_POST['kode'] ?? '');
        $form['nama']     = trim($_POST['nama'] ?? '');
        $form['dosen']    = trim($_POST['dosen'] ?? '');
        $form['semester'] = trim($_POST['semester'] ?? '');


        if (strlen($form['kode']) < 3 || strlen($form['kode']) > 20 || !preg_match('/^[A-Z0-9\-]+$/', $form['kode'])) {
            $errors['kode'] = 'Kode harus 3-20 karakter dan hanya berisi huruf kapital, angka, atau tanda strip (-).';
        }


        if (strlen($form['nama']) < 3 || strlen($form['nama']) > 100) {
            $errors['nama'] = 'Nama mata kuliah harus 3-100 karakter.';
        }


        if (strlen($form['dosen']) < 3 || strlen($form['dosen']) > 100) {
            $errors['dosen'] = 'Nama dosen pengampu harus 3-100 karakter.';
        }

        if (!ctype_digit($form['semester']) || (int)$form['semester'] < 1 || (int)$form['semester'] > 14) {
            $errors['semester'] = 'Semester harus berupa bilangan bulat antara 1 sampai 14.';
        }


        if (empty($errors['kode'])) {
            if ($post_action === 'create') {
                $stmt = $conn->prepare('SELECT id FROM mata_kuliah WHERE kode = ?');
                $stmt->bind_param('s', $form['kode']);
            } else {
                $stmt = $conn->prepare('SELECT id FROM mata_kuliah WHERE kode = ? AND id != ?');
                $stmt->bind_param('si', $form['kode'], $id);
            }
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors['kode'] = 'Kode mata kuliah sudah digunakan.';
            }
            $stmt->close();
        }


        if (empty($errors)) {
            $sem_int = (int)$form['semester'];

            if ($post_action === 'create') {
                $stmt = $conn->prepare('INSERT INTO mata_kuliah (kode, nama, dosen, semester) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('sssi', $form['kode'], $form['nama'], $form['dosen'], $sem_int);
                if ($stmt->execute()) {
                    set_flash('success', 'Data mata kuliah berhasil ditambahkan.');
                    header('Location: mata_kuliah.php');
                    exit;
                } else {
                    $pesan_error_umum = 'Data belum tersimpan. Terjadi kesalahan saat menyimpan ke database.';
                }
                $stmt->close();
            } else {

                $lama = cari_mata_kuliah($conn, $id);
                if (!$lama) {
                    set_flash('error', 'Data mata kuliah tidak ditemukan.');
                    header('Location: mata_kuliah.php');
                    exit;
                }

              
                if (
                    $lama['kode'] === $form['kode'] &&
                    $lama['nama'] === $form['nama'] &&
                    $lama['dosen'] === $form['dosen'] &&
                    (int)$lama['semester'] === $sem_int
                ) {
                    set_flash('info', 'Tidak ada perubahan data yang disimpan.');
                    header('Location: mata_kuliah.php');
                    exit;
                }

                $stmt = $conn->prepare('UPDATE mata_kuliah SET kode = ?, nama = ?, dosen = ?, semester = ? WHERE id = ?');
                $stmt->bind_param('sssii', $form['kode'], $form['nama'], $form['dosen'], $sem_int, $id);
                if ($stmt->execute()) {
                    set_flash('success', 'Data mata kuliah berhasil diperbarui.');
                    header('Location: mata_kuliah.php');
                    exit;
                } else {
                    $pesan_error_umum = 'Data belum tersimpan. Periksa kembali isian Anda.';
                }
                $stmt->close();
            }
        } else {
            $pesan_error_umum = 'Data belum tersimpan. Periksa kembali isian pada form.';
        }


        $action = $post_action;
        if ($post_action === 'edit') {
            $_GET['id'] = $id;
        }
    }

  
    elseif ($post_action === 'delete') {
        $id    = ambil_id_valid($_POST['id'] ?? null);
        $token = $_POST['csrf_token'] ?? '';

   
        if (!$id || !hash_equals($_SESSION['csrf_token'], $token)) {
            set_flash('error', 'Permintaan hapus tidak sah atau token keamanan tidak cocok.');
            header('Location: mata_kuliah.php');
            exit;
        }

        $mk = cari_mata_kuliah($conn, $id);
        if (!$mk) {
            set_flash('error', 'Data mata kuliah tidak ditemukan.');
            header('Location: mata_kuliah.php');
            exit;
        }


        $stmt = $conn->prepare('DELETE FROM mata_kuliah WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            set_flash('success', 'Mata kuliah berhasil dihapus.');
            $stmt->close();
            header('Location: mata_kuliah.php');
            exit;
        } else {

            $pesan_error_umum = 'Data belum tersimpan. Mata kuliah masih memiliki materi atau tugas. Hapus data terkait terlebih dahulu.';
            $stmt->close();
            $action = 'delete';
            $_GET['id'] = $id;
        }
    }
}


$data_detail = null;
if (in_array($action, ['detail', 'edit', 'delete'], true)) {
    $id = ambil_id_valid($_GET['id'] ?? null);
    if (!$id) {
        set_flash('error', 'ID mata kuliah tidak valid.');
        header('Location: mata_kuliah.php');
        exit;
    }
    $data_detail = cari_mata_kuliah($conn, $id);
    if (!$data_detail) {
        set_flash('error', 'Data mata kuliah tidak ditemukan.');
        header('Location: mata_kuliah.php');
        exit;
    }

    if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $form = [
            'kode'     => $data_detail['kode'],
            'nama'     => $data_detail['nama'],
            'dosen'    => $data_detail['dosen'],
            'semester' => (string)$data_detail['semester']
        ];
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Mata Kuliah - LMS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">LMS Universitas NCIT</a>
        <div class="nav-links">
            <a href="index.php">Beranda</a>
            <a href="mata_kuliah.php" class="active">Mata kuliah</a>
            <a href="materi.php">Materi</a>
            <a href="tugas.php">Tugas</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card">
        <h1>Mata kuliah</h1>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['tipe']) ?>"><?= e($flash['pesan']) ?></div>
        <?php endif; ?>

        <?php if ($pesan_error_umum !== ''): ?>
            <div class="alert alert-error"><?= e($pesan_error_umum) ?></div>
        <?php endif; ?>

        <?php if ($action === 'create' || $action === 'edit'): ?>
   
            <p class="subtitle"><?= $action === 'create' ? 'Tambah mata kuliah baru' : 'Edit data mata kuliah' ?></p>
            <form method="post" action="mata_kuliah.php<?= $action === 'edit' ? '?action=edit&id=' . e($data_detail['id']) : '?action=create' ?>">
                <input type="hidden" name="action" value="<?= e($action) ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= e($data_detail['id']) ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="kode">Kode mata kuliah</label>
                        <input type="text" id="kode" name="kode" value="<?= e($form['kode']) ?>" placeholder="Contoh: PAW-101" required>
                        <?php if (isset($errors['kode'])): ?>
                            <div class="field-error"><?= e($errors['kode']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="nama">Nama mata kuliah</label>
                        <input type="text" id="nama" name="nama" value="<?= e($form['nama']) ?>" placeholder="3-100 karakter" required>
                        <?php if (isset($errors['nama'])): ?>
                            <div class="field-error"><?= e($errors['nama']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dosen">Dosen pengampu</label>
                        <input type="text" id="dosen" name="dosen" value="<?= e($form['dosen']) ?>" placeholder="3-100 karakter" required>
                        <?php if (isset($errors['dosen'])): ?>
                            <div class="field-error"><?= e($errors['dosen']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <input type="number" id="semester" name="semester" min="1" max="14" value="<?= e($form['semester']) ?>" required>
                        <?php if (isset($errors['semester'])): ?>
                            <div class="field-error"><?= e($errors['semester']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Simpan data</button>
                <a href="mata_kuliah.php" class="btn">Batal</a>
            </form>

        <?php elseif ($action === 'detail'): ?>
           
            <h2><?= e($data_detail['kode']) ?> - <?= e($data_detail['nama']) ?></h2>
            <table class="detail-table">
                <tr>
                    <th>Kode mata kuliah</th>
                    <td><?= e($data_detail['kode']) ?></td>
                </tr>
                <tr>
                    <th>Nama mata kuliah</th>
                    <td><?= e($data_detail['nama']) ?></td>
                </tr>
                <tr>
                    <th>Dosen pengampu</th>
                    <td><?= e($data_detail['dosen']) ?></td>
                </tr>
                <tr>
                    <th>Semester</th>
                    <td>Semester <?= e($data_detail['semester']) ?></td>
                </tr>
            </table>
            <div style="margin-top: 20px;">
                <a href="mata_kuliah.php?action=edit&id=<?= e($data_detail['id']) ?>" class="btn btn-primary">Edit data</a>
                <a href="mata_kuliah.php" class="btn">Kembali</a>
            </div>

        <?php elseif ($action === 'delete'): ?>
        
            <p style="margin-bottom: 10px;">Hapus <strong><?= e($data_detail['nama']) ?></strong>?</p>
            <p class="subtitle">Data baru dihapus setelah tombol konfirmasi dikirim.</p>
            <form method="post" action="mata_kuliah.php?action=delete&id=<?= e($data_detail['id']) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e($data_detail['id']) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <button type="submit" class="btn btn-danger">Ya, hapus data</button>
                <a href="mata_kuliah.php" class="btn">Batal</a>
            </form>

        <?php else: ?>

            <?php
            $q = trim($_GET['q'] ?? '');
            if ($q !== '') {
                $like = '%' . $q . '%';
                $stmt = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah WHERE kode LIKE ? OR nama LIKE ? ORDER BY kode ASC');
                $stmt->bind_param('ss', $like, $like);
            } else {
                $stmt = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah ORDER BY kode ASC');
            }
            $stmt->execute();
            $hasil = $stmt->get_result();
            ?>

            <div class="toolbar">
                <span class="subtitle" style="margin-bottom: 0;">Menampilkan <?= e($hasil->num_rows) ?> data.</span>
                <a href="mata_kuliah.php?action=create" class="btn btn-primary">Tambah mata kuliah</a>
            </div>

            <form method="get" action="mata_kuliah.php" class="filter-bar">
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari kode atau nama mata kuliah...">
                <button type="submit" class="btn">Cari</button>
                <a href="mata_kuliah.php" class="btn">Reset</a>
            </form>

            <?php if ($hasil->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mata kuliah</th>
                            <th>Dosen / Semester</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $hasil->fetch_assoc()): ?>
                            <tr>
                                <td><?= e($row['kode']) ?> - <?= e($row['nama']) ?></td>
                                <td><?= e($row['dosen']) ?> / Semester <?= e($row['semester']) ?></td>
                                <td class="action-links">
                                    <a href="mata_kuliah.php?action=detail&id=<?= e($row['id']) ?>">Detail</a>
                                    <a href="mata_kuliah.php?action=edit&id=<?= e($row['id']) ?>">Edit</a>
                                    <a href="mata_kuliah.php?action=delete&id=<?= e($row['id']) ?>" class="text-danger">Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="margin-top: 16px;">Data tidak ditemukan.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>