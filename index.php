<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// HELPER FUNCTION FORMAT RUPIAH
function formatRupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// 1. KONEKSI DATABASE & AUTO-SETUP TABEL
$host = 'localhost';
$db   = 'store_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        category VARCHAR(100) NOT NULL DEFAULT 'Umum',
        price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        stock INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage() . "<br>Pastikan MySQL/XAMPP aktif dan kredensial database sudah benar.");
}

// 2. GENERATE TOKEN CSRF
if (empty($_SESSION['csrf'])) {$_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors = [];$edit_product = null;
$status =$_GET['status'] ?? '';

// 3. PROSES FORM (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token =$_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'],$token)) {
        http_response_code(403);
        die("Validasi CSRF Token Gagal.");
    }

    $action =$_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {$name     = trim($_POST['name'] ?? '');$category = trim($_POST['category'] ?? '');$raw_price   = $_POST['price'] ?? '';$clean_price = preg_replace('/[^\d]/', '', $raw_price);$price       = $clean_price !== '' ? (float)$clean_price : false;
        
        $stock    = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);$id       = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (mb_strlen($name) < 3) {$errors[] = "Nama produk minimal 3 karakter.";
        }
        if ($price === false || $price <= 0) {$errors[] = "Harga produk harus lebih dari 0.";
        }
        if ($stock === false || $stock < 0) {$errors[] = "Stok tidak boleh bernilai negatif.";
        }

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt =$pdo->prepare("SELECT id FROM products WHERE name = :name");
                $stmt->execute(['name' =>$name]);
            } else {
                $stmt =$pdo->prepare("SELECT id FROM products WHERE name = :name AND id != :id");
                $stmt->execute(['name' => $name, 'id' =>$id]);
            }

            if ($stmt->fetch()) {$errors[] = "Nama produk sudah digunakan. Pilih nama lain.";
            }
        }

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt =$pdo->prepare("INSERT INTO products (name, category, price, stock) VALUES (:name, :category, :price, :stock)");
                $stmt->execute(['name' => $name, 'category' =>$category, 'price' => $price, 'stock' =>$stock]);
            } else {
                $stmt =$pdo->prepare("UPDATE products SET name = :name, category = :category, price = :price, stock = :stock WHERE id = :id");
                $stmt->execute(['name' =>$name, 'category' => $category, 'price' =>$price, 'stock' => $stock, 'id' =>$id]);
            }

            header("Location: index.php?status=success");
            exit;
        }
    }

    if ($action === 'delete') {$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt =$pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute(['id' =>$id]);
        }
        header("Location: index.php?status=deleted");
        exit;
    }
}

// 4. AMBIL DATA UNTUK EDIT (SELECT BY ID)
if (isset($_GET['edit'])) {$edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt =$pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' =>$edit_id]);
        $edit_product =$stmt->fetch();
    }
}

// 5. READ DATA & FITUR PENCARIAN (GET)
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt =$pdo->prepare("SELECT * FROM products WHERE name LIKE :q1 OR category LIKE :q2 ORDER BY id DESC");
    $stmt->execute(['q1' => "%$q%", 'q2' => "%$q%"]);
    $products =$stmt->fetchAll();
} else {
    $products =$pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
}

// 6. HITUNG STATISTIK RINGKASAN
$all_products =$pdo->query("SELECT price, stock FROM products")->fetchAll();
$total_count  = count($all_products);$total_value  = 0;
$total_stock  = 0;
$low_stock    = 0;

foreach ($all_products as $item) {$total_value += ($item['price'] *$item['stock']);
    $total_stock +=$item['stock'];
    if ($item['stock'] <= 5) {$low_stock++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Manager Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --bg-canvas: #dbeafe;
            --bg-panel: rgba(255, 255, 255, 0.88);
            --bg-card: #ffffff;
            --bg-input: #f8fafc;
            --border-color: #e2e8f0;
            --primary: #06b6d4;
            --primary-gradient: linear-gradient(135deg, #00d2ff 0%, #0072ff 100%);
            --hero-gradient: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 12px;
            --shadow-soft: 0 10px 30px -5px rgba(148, 163, 184, 0.25);
            --shadow-hover: 0 18px 40px -8px rgba(148, 163, 184, 0.35);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #c4b5fd 0%, #93c5fd 40%, #a5f3fc 100%);
            background-attachment: fixed;
            color: var(--text-main);
            padding: 32px 16px;
            min-height: 100vh;
            line-height: 1.5;
        }

        .container {
            max-width: 1260px;
            margin: 0 auto;
            background: var(--bg-panel);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 32px;
            box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(255, 255, 255, 0.8) inset;
        }

        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            flex-wrap: wrap;
            gap: 16px;
        }

        .brand-logo { display: flex; align-items: center; gap: 16px; }

        .brand-icon {
            width: 52px; height: 52px;
            background: var(--primary-gradient);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: white;
            box-shadow: 0 10px 20px rgba(0, 114, 255, 0.3);
        }

        .brand-title { font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        .brand-subtitle { font-size: 13px; color: var(--text-muted); font-weight: 500; }

        .stats-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 32px;
        }

        @media (max-width: 1024px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 600px) { .stats-grid { grid-template-columns: 1fr; } }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex; align-items: center; gap: 16px;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
        }

        .stat-card.hero-stat {
            background: var(--hero-gradient);
            border: none; color: white;
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.35);
        }

        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-hover); }

        .stat-icon {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }

        .stat-icon.hero { background: rgba(255, 255, 255, 0.2); color: #ffffff; }
        .stat-icon.cyan { background: #e0f2fe; color: #0284c7; }
        .stat-icon.blue { background: #dbeafe; color: #2563eb; }
        .stat-icon.amber { background: #fef3c7; color: #d97706; }

        .stat-value { font-size: 24px; font-weight: 800; line-height: 1.2; }
        .stat-value.hero-text { color: #ffffff; font-size: 26px; }
        .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 4px; }
        .stat-card.hero-stat .stat-label { color: rgba(255, 255, 255, 0.85); }

        .badge-pill {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 700; padding: 4px 10px;
            border-radius: 20px; margin-top: 6px;
        }

        .badge-pill.success { background: rgba(255, 255, 255, 0.25); color: #ffffff; }

        .main-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 28px;
            align-items: start;
        }

        @media (max-width: 992px) { .main-layout { grid-template-columns: 1fr; } }

        .card-form {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 28px;
            position: sticky; top: 24px;
            box-shadow: var(--shadow-soft);
        }

        .form-header {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 24px; padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-header h2 { font-size: 19px; font-weight: 700; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-muted); }
        .input-wrapper { position: relative; }
        .input-wrapper i {
            position: absolute; left: 16px; top: 50%;
            transform: translateY(-50%); color: var(--text-light); font-size: 15px;
        }

        .form-control {
            width: 100%; padding: 13px 16px 13px 46px;
            border-radius: var(--radius-md); border: 1px solid var(--border-color);
            background: var(--bg-input); font-family: inherit; font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.15); background: #ffffff;
        }

        .price-preview-tag {
            font-size: 12px; color: #0284c7; margin-top: 6px;
            font-weight: 700; display: flex; align-items: center; gap: 6px;
        }

        .btn {
            padding: 13px 22px; border-radius: var(--radius-md); border: none;
            cursor: pointer; font-weight: 700; font-size: 14px; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            transition: var(--transition); font-family: inherit;
        }

        .btn-block { width: 100%; }

        .btn-primary {
            background: var(--primary-gradient); color: white;
            box-shadow: 0 8px 20px rgba(0, 114, 255, 0.28);
        }

        .btn-primary:hover { opacity: 0.95; transform: translateY(-2px); }

        .btn-secondary { background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border-color); }
        .btn-secondary:hover { background: #e2e8f0; }

        .btn-danger { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
        .btn-danger:hover { background: #ef4444; color: white; }

        .search-container { margin-bottom: 24px; }
        .search-form { display: flex; gap: 12px; }
        .search-input-group { flex-grow: 1; }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 22px;
            display: flex; flex-direction: column; justify-content: space-between;
            transition: var(--transition); box-shadow: var(--shadow-soft);
        }

        .product-card:hover {
            border-color: var(--primary); transform: translateY(-4px); box-shadow: var(--shadow-hover);
        }

        .card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; gap: 8px; }

        .category-badge {
            background: #e0f2fe; color: #0369a1; font-size: 11px;
            font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;
        }

        .stock-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; }
        .stock-badge.safe { background: #dcfce7; color: #15803d; }
        .stock-badge.warning { background: #fef3c7; color: #b45309; }
        .stock-badge.danger { background: #fee2e2; color: #b91c1c; }

        .product-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .price-tag { font-size: 22px; font-weight: 800; color: #0284c7; margin-bottom: 16px; }

        .stock-info-box {
            background: #f8fafc; border: 1px solid var(--border-color);
            border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 18px;
        }

        .stock-info-header { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-muted); font-weight: 600; }
        .stock-info-header strong.safe { color: #16a34a; }
        .stock-info-header strong.warning { color: #d97706; }
        .stock-info-header strong.danger { color: #dc2626; }

        .stock-progress-bar { height: 6px; background: #e2e8f0; border-radius: 10px; margin-top: 8px; overflow: hidden; }
        .stock-progress-fill { height: 100%; border-radius: 10px; }
        .stock-progress-fill.safe { background: #22c55e; }
        .stock-progress-fill.warning { background: #f59e0b; }
        .stock-progress-fill.danger { background: #ef4444; }

        .card-actions { display: flex; gap: 10px; padding-top: 16px; border-top: 1px solid var(--border-color); }
        .card-actions .btn { flex: 1; padding: 9px 12px; font-size: 12px; }

        .alert {
            background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
            padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: 24px;
            display: flex; align-items: center; gap: 14px; font-size: 14px;
        }

        .alert.alert-success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }

        .empty-state {
            text-align: center; padding: 64px 20px; background: var(--bg-card);
            border: 1px dashed var(--border-color); border-radius: var(--radius-lg);
            grid-column: 1 / -1; color: var(--text-muted);
        }
    </style>
</head>
<body>

<div class="container">
    <header class="app-header">
        <div class="brand-logo">
            <div class="brand-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div>
                <h1 class="brand-title">Product Manager Pro</h1>
                <p class="brand-subtitle">Kelola inventaris toko dengan sistem pemantauan real-time</p>
            </div>
        </div>
    </header>

    <section class="stats-grid">
        <div class="stat-card hero-stat">
            <div class="stat-icon hero"><i class="fa-solid fa-wallet"></i></div>
            <div>
                <div class="stat-value hero-text"><?= formatRupiah($total_value) ?></div>
                <div class="stat-label">Total Nilai Inventaris</div>
                <div class="badge-pill success"><i class="fa-solid fa-arrow-trend-up"></i> Nilai Aset Terdaftar</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon cyan"><i class="fa-solid fa-box-archive"></i></div>
            <div>
                <div class="stat-value"><?= number_format($total_count) ?></div>
                <div class="stat-label">Total Jenis Produk</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-layer-group"></i></div>
            <div>
                <div class="stat-value"><?= number_format($total_stock) ?></div>
                <div class="stat-label">Total Unit Stok</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon amber"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
                <div class="stat-value"><?= number_format($low_stock) ?></div>
                <div class="stat-label">Stok Menipis (≤ 5)</div>
            </div>
        </div>
    </section>

    <?php if ($status === 'success'): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check" style="font-size:22px;"></i>
            <div>Data produk berhasil disimpan!</div>
        </div>
    <?php elseif ($status === 'deleted'): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check" style="font-size:22px;"></i>
            <div>Produk telah berhasil dihapus.</div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert">
            <i class="fa-solid fa-circle-exclamation" style="font-size:22px;"></i>
            <div>
                <?php foreach ($errors as$err): ?>
                    <div><?= htmlspecialchars($err, ENT_QUOTES, "UTF-8") ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="main-layout">
        <aside class="card-form">
            <div class="form-header">
                <i class="fa-solid <?= $edit_product ? 'fa-pen-to-square' : 'fa-circle-plus' ?>" style="color: var(--primary); font-size: 20px;"></i>
                <h2><?= $edit_product ? 'Edit Produk' : 'Tambah Produk Baru' ?></h2>
            </div>

            <form method="POST" action="index.php">
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                <input type="hidden" name="action" value="<?= $edit_product ? 'update' : 'create' ?>">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="id" value="<?= $edit_product['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Nama Produk</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-tag"></i>
                        <input type="text" id="name" name="name" class="form-control" placeholder="Contoh: Laptop Asus Zenbook" value="<?= htmlspecialchars($edit_product['name'] ?? '', ENT_QUOTES, "UTF-8") ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="category">Kategori</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-folder"></i>
                        <input type="text" id="category" name="category" class="form-control" placeholder="Elektronik, Pakaian, dll." value="<?= htmlspecialchars($edit_product['category'] ?? 'Umum', ENT_QUOTES, "UTF-8") ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="price">Harga Produk (Rupiah)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-money-bill-wave"></i>
                        <input type="text" id="price" name="price" class="form-control" placeholder="Contoh: 12.000.000" value="<?= isset($edit_product['price']) ? number_format($edit_product['price'], 0, ',', '.') : '' ?>" oninput="formatRupiahInput(this)" required>
                    </div>
                    <div class="price-preview-tag" id="pricePreview">
                        <i class="fa-solid fa-eye"></i> Format: <?= isset($edit_product['price']) ? formatRupiah($edit_product['price']) : 'Rp 0' ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="stock">Jumlah Stok</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-layer-group"></i>
                        <input type="number" id="stock" name="stock" class="form-control" placeholder="10" value="<?= htmlspecialchars($edit_product['stock'] ?? '', ENT_QUOTES, "UTF-8") ?>" required>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 28px;">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <?= $edit_product ? 'Update Produk' : 'Simpan Produk' ?>
                    </button>
                    <?php if ($edit_product): ?>
                        <a href="index.php" class="btn btn-secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </aside>

        <main>
            <div class="search-container">
                <form method="GET" action="index.php" class="search-form">
                    <div class="input-wrapper search-input-group">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q, ENT_QUOTES, "UTF-8") ?>" placeholder="Cari berdasarkan nama atau kategori...">
                    </div>
                    <button type="submit" class="btn btn-secondary">Cari</button>
                    <?php if ($q !== ''): ?>
                        <a href="index.php" class="btn btn-secondary" title="Reset Pencarian"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="products-grid">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open" style="font-size: 48px; margin-bottom: 16px;"></i>
                        <h3>Tidak Ada Produk Ditemukan</h3>
                        <p style="margin-top: 6px;">Coba ubah kata kunci pencarian atau tambahkan produk baru.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($products as$p): ?>
                    <?php 
                        $stock_class = 'safe';
                        $stock_label =$p['stock'] . ' unit';
                        if ($p['stock'] == 0) {
                            $stock_class = 'danger';$stock_label = 'Habis';
                        } elseif ($p['stock'] <= 5) {$stock_class = 'warning';
                            $stock_label = 'Sisa ' .$p['stock'];
                        }

                        $stock_percentage = min(100, max(8, ($p['stock'] / 20) * 100));
                    ?>
                    <div class="product-card">
                        <div>
                            <div class="card-top">
                                <span class="category-badge"><?= htmlspecialchars($p['category'], ENT_QUOTES, "UTF-8") ?></span>
                                <span class="stock-badge <?= $stock_class ?>">
                                    <i class="fa-solid fa-cubes"></i> <?= $stock_label ?>
                                </span>
                            </div>

                            <h3 class="product-title"><?= htmlspecialchars($p['name'], ENT_QUOTES, "UTF-8") ?></h3>
                            <div class="price-tag"><?= formatRupiah($p['price']) ?></div>

                            <div class="stock-info-box">
                                <div class="stock-info-header">
                                    <span><i class="fa-solid fa-boxes-stacked"></i> Stok Tersedia</span>
                                    <strong class="<?= $stock_class ?>"><?= number_format($p['stock']) ?> Unit</strong>
                                </div>
                                <div class="stock-progress-bar">
                                    <div class="stock-progress-fill <?= $stock_class ?>" style="width: <?= $stock_percentage ?>%;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="card-actions">
                            <!-- PAUTAN BUTANG EDIT YANG SUDAH DIBETULKAN MEGGUNAKAN TANDA SOAL (?) -->
                            <a href="index.php?edit=<?= $p['id'] ?>" class="btn btn-secondary">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </a>

                            <form method="POST" action="index.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?');" style="flex: 1;">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-block">
                                    <i class="fa-solid fa-trash-can"></i> Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<script>
    function formatRupiahInput(input) {
        let rawValue = input.value.replace(/[^\d]/g, '');
        
        if (rawValue === '') {
            input.value = '';
            document.getElementById('pricePreview').innerHTML = '<i class="fa-solid fa-eye"></i> Format: Rp 0';
            return;
        }

        let formatted = new Intl.NumberFormat('id-ID').format(rawValue);
        input.value = formatted;

        document.getElementById('pricePreview').innerHTML = '<i class="fa-solid fa-eye"></i> Format: Rp ' + formatted;
    }
</script>

</body>
</html>