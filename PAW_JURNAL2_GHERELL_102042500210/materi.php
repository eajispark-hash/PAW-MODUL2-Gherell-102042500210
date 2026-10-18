<?php
require 'koneksi.php';

$action = $_GET['action'] ?? 'list';
$errors = [];
$pesan_error_umum = '';

$form = [
    'mata_kuliah_id' => '',
    'judul'          => '',
    'isi'            => '',
    'tautan'         => ''
];

function ambil_id_valid($nilai) {
    if ($nilai === null || !ctype_digit((string)$nilai) || (int)$nilai <= 0) {
        return null;
    }
    return (int)$nilai;
}

// Ambil seluruh mata kuliah untuk pilihan dropdown
function semua_mata_kuliah(mysqli $conn): array {
    $stmt = $conn->prepare('SELECT id, kode, nama FROM mata_kuliah ORDER BY kode ASC');
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

// Ambil 1 materi beserta kode dan nama mata kuliahnya (JOIN)
function cari_materi(mysqli $conn, int $id) {
    $sql = 'SELECT m.id, m.mata_kuliah_id, m.judul, m.isi, m.tautan, mk.kode AS mk_kode, mk.nama AS mk_nama
            FROM materi m
            JOIN mata_kuliah mk ON m.mata_kuliah_id = mk.id
            WHERE m.id = ?';
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
            set_flash('error', 'ID materi tidak valid.');
            header('Location: materi.php');
            exit;
        }

        $form['mata_kuliah_id'] = trim($_POST['mata_kuliah_id'] ?? '');
        $form['judul']          = trim($_POST['judul'] ?? '');
        $form['isi']            = trim($_POST['isi'] ?? '');
        $form['tautan']         = trim($_POST['tautan'] ?? '');

        // 1. Pemeriksaan mata kuliah: harus dipilih dari daftar mata kuliah yang ada di database (Pengujian 3)
        $mk_id_valid = ambil_id_valid($form['mata_kuliah_id']);
        if (!$mk_id_valid) {
            $errors['mata_kuliah_id'] = 'Pilih mata kuliah yang valid.';
        } else {
            $stmt = $conn->prepare('SELECT id FROM mata_kuliah WHERE id = ?');
            $stmt->bind_param('i', $mk_id_valid);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $errors['mata_kuliah_id'] = 'Mata kuliah yang dipilih tidak terdaftar di database.';
            }
            $stmt->close();
        }

        // 2. Pemeriksaan judul: 3-150 karakter
        if (strlen($form['judul']) < 3 || strlen($form['judul']) > 150) {
            $errors['judul'] = 'Judul materi harus 3-150 karakter.';
        }

        // 3. Pemeriksaan isi: 3-2000 karakter
        if (strlen($form['isi']) < 3 || strlen($form['isi']) > 2000) {
            $errors['isi'] = 'Isi materi harus 3-2000 karakter.';
        }

        // 4. Pemeriksaan tautan: maks 255 karakter, diawali http:// atau https:// (Mencegah ERROR J01 javascript:alert)
        if (
            strlen($form['tautan']) > 255 ||
            !preg_match('/^https?:\/\/.+/i', $form['tautan']) ||
            !filter_var($form['tautan'], FILTER_VALIDATE_URL)
        ) {
            $errors['tautan'] = 'Gunakan tautan http/https yang valid.';
        }

        if (empty($errors)) {
            if ($post_action === 'create') {
                $stmt = $conn->prepare('INSERT INTO materi (mata_kuliah_id, judul, isi, tautan) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('isss', $mk_id_valid, $form['judul'], $form['isi'], $form['tautan']);
                if ($stmt->execute()) {
                    set_flash('success', 'Data materi berhasil ditambahkan.');
                    header('Location: materi.php');
                    exit;
                } else {
                    $pesan_error_umum = 'Data belum tersimpan. Terjadi kesalahan database.';
                }
                $stmt->close();
            } else {
                $lama = cari_materi($conn, $id);
                if (!$lama) {
                    set_flash('error', 'Data materi tidak ditemukan.');
                    header('Location: materi.php');
                    exit;
                }

                // Cek jika tidak ada perubahan saat edit
                if (
                    (int)$lama['mata_kuliah_id'] === $mk_id_valid &&
                    $lama['judul'] === $form['judul'] &&
                    $lama['isi'] === $form['isi'] &&
                    $lama['tautan'] === $form['tautan']
                ) {
                    set_flash('info', 'Tidak ada perubahan data materi yang disimpan.');
                    header('Location: materi.php');
                    exit;
                }

                $stmt = $conn->prepare('UPDATE materi SET mata_kuliah_id = ?, judul = ?, isi = ?, tautan = ? WHERE id = ?');
                $stmt->bind_param('isssi', $mk_id_valid, $form['judul'], $form['isi'], $form['tautan'], $id);
                if ($stmt->execute()) {
                    set_flash('success', 'Data materi berhasil diperbarui.');
                    header('Location: materi.php');
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
            header('Location: materi.php');
            exit;
        }

        $stmt = $conn->prepare('DELETE FROM materi WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            set_flash('success', 'Data materi berhasil dihapus.');
        } else {
            set_flash('error', 'Data materi gagal dihapus atau tidak ditemukan.');
        }
        $stmt->close();
        header('Location: materi.php');
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
        set_flash('error', 'ID materi tidak valid.');
        header('Location: materi.php');
        exit;
    }
    $data_detail = cari_materi($conn, $id);
    if (!$data_detail) {
        set_flash('error', 'Data materi tidak ditemukan.');
        header('Location: materi.php');
        exit;
    }

    if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $form = [
            'mata_kuliah_id' => (string)$data_detail['mata_kuliah_id'],
            'judul'          => $data_detail['judul'],
            'isi'            => $data_detail['isi'],
            'tautan'         => $data_detail['tautan']
        ];
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Materi - LMS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">LMS Universitas NCIT</a>
        <div class="nav-links">
            <a href="index.php">Beranda</a>
            <a href="mata_kuliah.php">Mata kuliah</a>
            <a href="materi.php" class="active">Materi</a>
            <a href="tugas.php">Tugas</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card">
        <h1>Materi</h1>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['tipe']) ?>"><?= e($flash['pesan']) ?></div>
        <?php endif; ?>

        <?php if ($pesan_error_umum !== ''): ?>
            <div class="alert alert-error"><?= e($pesan_error_umum) ?></div>
        <?php endif; ?>

        <?php if ($action === 'create' || $action === 'edit'): ?>
            <!-- FORM TAMBAH / EDIT MATERI (Gambar 4 & Gambar 9 Jurnal) -->
            <form method="post" action="materi.php<?= $action === 'edit' ? '?action=edit&id=' . e($data_detail['id']) : '?action=create' ?>">
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
                        <label for="judul">Judul materi</label>
                        <input type="text" id="judul" name="judul" value="<?= e($form['judul']) ?>" required>
                        <?php if (isset($errors['judul'])): ?>
                            <div class="field-error"><?= e($errors['judul']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="isi">Isi materi</label>
                    <textarea id="isi" name="isi" required><?= e($form['isi']) ?></textarea>
                    <?php if (isset($errors['isi'])): ?>
                        <div class="field-error"><?= e($errors['isi']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="tautan">Tautan referensi</label>
                    <input type="text" id="tautan" name="tautan" value="<?= e($form['tautan']) ?>" placeholder="https://..." required>
                    <?php if (isset($errors['tautan'])): ?>
                        <div class="field-error"><?= e($errors['tautan']) ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">Simpan data</button>
                <a href="materi.php" class="btn">Batal</a>
            </form>

        <?php elseif ($action === 'detail'): ?>
            <!-- DETAIL MATERI (Gambar 5 Jurnal) -->
            <h2><?= e($data_detail['judul']) ?></h2>
            <table class="detail-table">
                <tr>
                    <th>Mata kuliah</th>
                    <td><?= e($data_detail['mk_kode']) ?> - <?= e($data_detail['mk_nama']) ?></td>
                </tr>
                <tr>
                    <th>Judul materi</th>
                    <td><?= e($data_detail['judul']) ?></td>
                </tr>
                <tr>
                    <th>Isi materi</th>
                    <td><?= nl2br(e($data_detail['isi'])) ?></td>
                </tr>
                <tr>
                    <th>Tautan referensi</th>
                    <td><a href="<?= e($data_detail['tautan']) ?>" target="_blank" rel="noopener noreferrer"><?= e($data_detail['tautan']) ?></a></td>
                </tr>
            </table>
            <div style="margin-top: 20px;">
                <a href="materi.php?action=edit&id=<?= e($data_detail['id']) ?>" class="btn btn-primary">Edit data</a>
                <a href="materi.php" class="btn">Kembali</a>
            </div>

        <?php elseif ($action === 'delete'): ?>
            <!-- KONFIRMASI HAPUS MATERI -->
            <p style="margin-bottom: 10px;">Hapus materi <strong><?= e($data_detail['judul']) ?></strong>?</p>
            <p class="subtitle">Data baru dihapus setelah tombol konfirmasi dikirim.</p>
            <form method="post" action="materi.php?action=delete&id=<?= e($data_detail['id']) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e($data_detail['id']) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <button type="submit" class="btn btn-danger">Ya, hapus data</button>
                <a href="materi.php" class="btn">Batal</a>
            </form>

        <?php else: ?>
            <!-- DAFTAR, PENCARIAN & FILTER MATERI (Gambar 7 & Gambar 8 Jurnal) -->
            <?php
            $q         = trim($_GET['q'] ?? '');
            $filter_mk = ambil_id_valid($_GET['mata_kuliah_id'] ?? null);

            $sql = 'SELECT m.id, m.judul, mk.kode AS mk_kode, mk.nama AS mk_nama
                    FROM materi m
                    JOIN mata_kuliah mk ON m.mata_kuliah_id = mk.id
                    WHERE 1=1';
            $types  = '';
            $params = [];

            if ($q !== '') {
                $sql .= ' AND m.judul LIKE ?';
                $types .= 's';
                $params[] = '%' . $q . '%';
            }
            if ($filter_mk) {
                $sql .= ' AND m.mata_kuliah_id = ?';
                $types .= 'i';
                $params[] = $filter_mk;
            }
            $sql .= ' ORDER BY m.id DESC';

            $stmt = $conn->prepare($sql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $hasil = $stmt->get_result();
            ?>

            <div class="toolbar">
                <span class="subtitle" style="margin-bottom: 0;">Menampilkan <?= e($hasil->num_rows) ?> data.</span>
                <a href="materi.php?action=create" class="btn btn-primary">Tambah materi</a>
            </div>

            <form method="get" action="materi.php" class="filter-bar">
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul materi...">
                <select name="mata_kuliah_id">
                    <option value="">Semua mata kuliah</option>
                    <?php foreach ($opsi_mk as $mk): ?>
                        <option value="<?= e($mk['id']) ?>" <?= $filter_mk === (int)$mk['id'] ? 'selected' : '' ?>>
                            <?= e($mk['kode']) ?> - <?= e($mk['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn">Cari</button>
                <a href="materi.php" class="btn">Reset</a>
            </form>

            <?php if ($hasil->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Mata kuliah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $hasil->fetch_assoc()): ?>
                            <tr>
                                <td><?= e($row['judul']) ?></td>
                                <td><?= e($row['mk_kode']) ?> - <?= e($row['mk_nama']) ?></td>
                                <td class="action-links">
                                    <a href="materi.php?action=detail&id=<?= e($row['id']) ?>">Detail</a>
                                    <a href="materi.php?action=edit&id=<?= e($row['id']) ?>">Edit</a>
                                    <a href="materi.php?action=delete&id=<?= e($row['id']) ?>" class="text-danger">Hapus</a>
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