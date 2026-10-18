<?php
require 'koneksi.php';

$action = $_GET['action'] ?? 'list';
$errors = [];
$pesan_error_umum = '';
$status_valid = ['Draf', 'Aktif', 'Selesai'];

$form = [
    'mata_kuliah_id' => '',
    'judul'          => '',
    'instruksi'      => '',
    'tenggat'        => '',
    'status'         => 'Draf'
];

function ambil_id_valid($nilai) {
    if ($nilai === null || !ctype_digit((string)$nilai) || (int)$nilai <= 0) {
        return null;
    }
    return (int)$nilai;
}

// Validasi tanggal format YYYY-MM-DD yang benar-benar valid di kalender
function tanggal_valid(string $tanggal): bool {
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $d && $d->format('Y-m-d') === $tanggal;
}

function semua_mata_kuliah(mysqli $conn): array {
    $stmt = $conn->prepare('SELECT id, kode, nama FROM mata_kuliah ORDER BY kode ASC');
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

function cari_tugas(mysqli $conn, int $id) {
    $sql = 'SELECT t.id, t.mata_kuliah_id, t.judul, t.instruksi, t.tenggat, t.status, mk.kode AS mk_kode, mk.nama AS mk_nama
            FROM tugas t
            JOIN mata_kuliah mk ON t.mata_kuliah_id = mk.id
            WHERE t.id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $data;
}

$opsi_mk = semua_mata_kuliah($conn);

// ==========================================
// PEMROSESAN FORM POST (TAMBAH, EDIT, HAPUS)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'create' || $post_action === 'edit') {
        $id = ($post_action === 'edit') ? ambil_id_valid($_POST['id'] ?? null) : null;
        if ($post_action === 'edit' && !$id) {
            set_flash('error', 'ID tugas tidak valid.');
            header('Location: tugas.php');
            exit;
        }

        $form['mata_kuliah_id'] = trim($_POST['mata_kuliah_id'] ?? '');
        $form['judul']          = trim($_POST['judul'] ?? '');
        $form['instruksi']      = trim($_POST['instruksi'] ?? '');
        $form['tenggat']        = trim($_POST['tenggat'] ?? '');
        $form['status']         = trim($_POST['status'] ?? '');

        // 1. Pemeriksaan mata kuliah di database
        $mk_id_valid = ambil_id_valid($form['mata_kuliah_id']);
        if (!$mk_id_valid) {
            $errors['mata_kuliah_id'] = 'Pilih mata kuliah yang valid.';
        } else {
            $stmt = $conn->prepare('SELECT id FROM mata_kuliah WHERE id = ?');
            $stmt->bind_param('i', $mk_id_valid);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $errors['mata_kuliah_id'] = 'Mata kuliah yang dipilih tidak terdaftar.';
            }
            $stmt->close();
        }

        // 2. Pemeriksaan judul: 3-150 karakter
        if (strlen($form['judul']) < 3 || strlen($form['judul']) > 150) {
            $errors['judul'] = 'Judul tugas harus 3-150 karakter.';
        }

        // 3. Pemeriksaan instruksi: 3-2000 karakter
        if (strlen($form['instruksi']) < 3 || strlen($form['instruksi']) > 2000) {
            $errors['instruksi'] = 'Instruksi tugas harus 3-2000 karakter.';
        }

        // 4. Pemeriksaan tenggat: tanggal yang valid
        if (!tanggal_valid($form['tenggat'])) {
            $errors['tenggat'] = 'Masukkan tanggal tenggat yang valid.';
        }

        // 5. Pemeriksaan status: hanya Draf, Aktif, atau Selesai
        if (!in_array($form['status'], $status_valid, true)) {
            $errors['status'] = 'Status hanya boleh Draf, Aktif, atau Selesai.';
        }

        if (empty($errors)) {
            if ($post_action === 'create') {
                $stmt = $conn->prepare('INSERT INTO tugas (mata_kuliah_id, judul, instruksi, tenggat, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('issss', $mk_id_valid, $form['judul'], $form['instruksi'], $form['tenggat'], $form['status']);
                if ($stmt->execute()) {
                    set_flash('success', 'Data tugas berhasil ditambahkan.');
                    header('Location: tugas.php');
                    exit;
                } else {
                    $pesan_error_umum = 'Data belum tersimpan. Terjadi kesalahan database.';
                }
                $stmt->close();
            } else {
                $lama = cari_tugas($conn, $id);
                if (!$lama) {
                    set_flash('error', 'Data tugas tidak ditemukan.');
                    header('Location: tugas.php');
                    exit;
                }

                // Cek jika tidak ada perubahan saat edit
                if (
                    (int)$lama['mata_kuliah_id'] === $mk_id_valid &&
                    $lama['judul'] === $form['judul'] &&
                    $lama['instruksi'] === $form['instruksi'] &&
                    $lama['tenggat'] === $form['tenggat'] &&
                    $lama['status'] === $form['status']
                ) {
                    set_flash('info', 'Tidak ada perubahan data tugas yang disimpan.');
                    header('Location: tugas.php');
                    exit;
                }

                $stmt = $conn->prepare('UPDATE tugas SET mata_kuliah_id = ?, judul = ?, instruksi = ?, tenggat = ?, status = ? WHERE id = ?');
                $stmt->bind_param('issssi', $mk_id_valid, $form['judul'], $form['instruksi'], $form['tenggat'], $form['status'], $id);
                if ($stmt->execute()) {
                    set_flash('success', 'Data tugas berhasil diperbarui.');
                    header('Location: tugas.php');
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
            set_flash('error', 'Permintaan hapus tidak sah atau token tidak cocok.');
            header('Location: tugas.php');
            exit;
        }

        $stmt = $conn->prepare('DELETE FROM tugas WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            set_flash('success', 'Data tugas berhasil dihapus.');
        } else {
            set_flash('error', 'Data tugas gagal dihapus atau tidak ditemukan.');
        }
        $stmt->close();
        header('Location: tugas.php');
        exit;
    }
}

// ==========================================
// PERSIAPAN DATA UNTUK TAMPILAN GET
// ==========================================
$data_detail = null;
if (in_array($action, ['detail', 'edit', 'delete'], true)) {
    $id = ambil_id_valid($_GET['id'] ?? null);
    if (!$id) {
        set_flash('error', 'ID tugas tidak valid.');
        header('Location: tugas.php');
        exit;
    }
    $data_detail = cari_tugas($conn, $id);
    if (!$data_detail) {
        set_flash('error', 'Data tugas tidak ditemukan.');
        header('Location: tugas.php');
        exit;
    }

    if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $form = [
            'mata_kuliah_id' => (string)$data_detail['mata_kuliah_id'],
            'judul'          => $data_detail['judul'],
            'instruksi'      => $data_detail['instruksi'],
            'tenggat'        => $data_detail['tenggat'],
            'status'         => $data_detail['status']
        ];
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tugas - LMS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">LMS Universitas NCIT</a>
        <div class="nav-links">
            <a href="index.php">Beranda</a>
            <a href="mata_kuliah.php">Mata kuliah</a>
            <a href="materi.php">Materi</a>
            <a href="tugas.php" class="active">Tugas</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card">
        <h1>Tugas</h1>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['tipe']) ?>"><?= e($flash['pesan']) ?></div>
        <?php endif; ?>

        <?php if ($pesan_error_umum !== ''): ?>
            <div class="alert alert-error"><?= e($pesan_error_umum) ?></div>
        <?php endif; ?>

        <?php if ($action === 'create' || $action === 'edit'): ?>
            <!-- FORM TAMBAH / EDIT TUGAS (Gambar 6 Jurnal) -->
            <form method="post" action="tugas.php<?= $action === 'edit' ? '?action=edit&id=' . e($data_detail['id']) : '?action=create' ?>">
                <input type="hidden" name="action" value="<?= e($action) ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= e($data_detail['id']) ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mata_kuliah_id">Mata kuliah</label>
                        <select id="mata_kuliah_id" name="mata_kuliah_id" required>
                            <option value="">Pilih Mata kuliah</option>
                            <?php foreach ($opsi_mk as $mk): ?>
                                <option value="<?= e($mk['id']) ?>" <?= (string)$form['mata_kuliah_id'] === (string)$mk['id'] ? 'selected' : '' ?>>
                                    <?= e($mk['kode']) ?> - <?= e($mk['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['mata_kuliah_id'])): ?>
                            <div class="field-error"><?= e($errors['mata_kuliah_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="judul">Judul tugas</label>
                        <input type="text" id="judul" name="judul" value="<?= e($form['judul']) ?>" required>
                        <?php if (isset($errors['judul'])): ?>
                            <div class="field-error"><?= e($errors['judul']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="instruksi">Instruksi tugas</label>
                    <textarea id="instruksi" name="instruksi" required><?= e($form['instruksi']) ?></textarea>
                    <?php if (isset($errors['instruksi'])): ?>
                        <div class="field-error"><?= e($errors['instruksi']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tenggat">Tenggat</label>
                        <input type="date" id="tenggat" name="tenggat" value="<?= e($form['tenggat']) ?>" required>
                        <?php if (isset($errors['tenggat'])): ?>
                            <div class="field-error"><?= e($errors['tenggat']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <?php foreach ($status_valid as $st): ?>
                                <option value="<?= e($st) ?>" <?= $form['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <div class="field-error"><?= e($errors['status']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Simpan data</button>
                <a href="tugas.php" class="btn">Batal</a>
            </form>

        <?php elseif ($action === 'detail'): ?>
            <!-- DETAIL TUGAS -->
            <h2><?= e($data_detail['judul']) ?></h2>
            <table class="detail-table">
                <tr>
                    <th>Mata kuliah</th>
                    <td><?= e($data_detail['mk_kode']) ?> - <?= e($data_detail['mk_nama']) ?></td>
                </tr>
                <tr>
                    <th>Judul tugas</th>
                    <td><?= e($data_detail['judul']) ?></td>
                </tr>
                <tr>
                    <th>Instruksi tugas</th>
                    <td><?= nl2br(e($data_detail['instruksi'])) ?></td>
                </tr>
                <tr>
                    <th>Tenggat</th>
                    <td><?= e($data_detail['tenggat']) ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><span class="badge"><?= e($data_detail['status']) ?></span></td>
                </tr>
            </table>
            <div style="margin-top: 20px;">
                <a href="tugas.php?action=edit&id=<?= e($data_detail['id']) ?>" class="btn btn-primary">Edit data</a>
                <a href="tugas.php" class="btn">Kembali</a>
            </div>

        <?php elseif ($action === 'delete'): ?>
            <!-- KONFIRMASI HAPUS TUGAS -->
            <p style="margin-bottom: 10px;">Hapus tugas <strong><?= e($data_detail['judul']) ?></strong>?</p>
            <p class="subtitle">Data baru dihapus setelah tombol konfirmasi dikirim.</p>
            <form method="post" action="tugas.php?action=delete&id=<?= e($data_detail['id']) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e($data_detail['id']) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <button type="submit" class="btn btn-danger">Ya, hapus data</button>
                <a href="tugas.php" class="btn">Batal</a>
            </form>

        <?php else: ?>
            <!-- DAFTAR, PENCARIAN & FILTER TUGAS -->
            <?php
            $q         = trim($_GET['q'] ?? '');
            $filter_mk = ambil_id_valid($_GET['mata_kuliah_id'] ?? null);

            $sql = 'SELECT t.id, t.judul, t.tenggat, t.status, mk.kode AS mk_kode, mk.nama AS mk_nama
                    FROM tugas t
                    JOIN mata_kuliah mk ON t.mata_kuliah_id = mk.id
                    WHERE 1=1';
            $types  = '';
            $params = [];

            if ($q !== '') {
                $sql .= ' AND t.judul LIKE ?';
                $types .= 's';
                $params[] = '%' . $q . '%';
            }
            if ($filter_mk) {
                $sql .= ' AND t.mata_kuliah_id = ?';
                $types .= 'i';
                $params[] = $filter_mk;
            }
            $sql .= ' ORDER BY t.tenggat ASC, t.id DESC';

            $stmt = $conn->prepare($sql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $hasil = $stmt->get_result();
            ?>

            <div class="toolbar">
                <span class="subtitle" style="margin-bottom: 0;">Menampilkan <?= e($hasil->num_rows) ?> data.</span>
                <a href="tugas.php?action=create" class="btn btn-primary">Tambah tugas</a>
            </div>

            <form method="get" action="tugas.php" class="filter-bar">
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul tugas...">
                <select name="mata_kuliah_id">
                    <option value="">Semua mata kuliah</option>
                    <?php foreach ($opsi_mk as $mk): ?>
                        <option value="<?= e($mk['id']) ?>" <?= $filter_mk === (int)$mk['id'] ? 'selected' : '' ?>>
                            <?= e($mk['kode']) ?> - <?= e($mk['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn">Cari</button>
                <a href="tugas.php" class="btn">Reset</a>
            </form>

            <?php if ($hasil->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Mata kuliah</th>
                            <th>Tenggat / Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $hasil->fetch_assoc()): ?>
                            <tr>
                                <td><?= e($row['judul']) ?></td>
                                <td><?= e($row['mk_kode']) ?> - <?= e($row['mk_nama']) ?></td>
                                <td><?= e($row['tenggat']) ?> &bull; <span class="badge"><?= e($row['status']) ?></span></td>
                                <td class="action-links">
                                    <a href="tugas.php?action=detail&id=<?= e($row['id']) ?>">Detail</a>
                                    <a href="tugas.php?action=edit&id=<?= e($row['id']) ?>">Edit</a>
                                    <a href="tugas.php?action=delete&id=<?= e($row['id']) ?>" class="text-danger">Hapus</a>
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