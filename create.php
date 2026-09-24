<?php
session_start();
require_once '../config/db.php';

$errors = [];
$name = $category = $price = $stock = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'Umum');
    $price    = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $stock    = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

    if (mb_strlen($name) < 3) {
        $errors['name'] = 'Nama produk minimal 3 karakter.';
    }
    if ($price === false || $price <= 0) {
        $errors['price'] = 'Harga harus angka dan lebih dari 0.';
    }
    if ($stock === false || $stock < 0) {
        $errors['stock'] = 'Stok tidak boleh negatif.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE name = :name");
        $stmt->execute(['name' => $name]);
        if ($stmt->fetchColumn() > 0) {
            $errors['name'] = 'Nama produk sudah terdaftar.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock) VALUES (:name, :category, :price, :stock)");
        $stmt->execute([
            'name'     => $name,
            'category' => $category,
            'price'    => $price,
            'stock'    => $stock
        ]);

        header('Location: index.php?status=created');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Produk</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <div class="form-card">
        <h2>Tambah Produk Baru</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin:0; padding-left:20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="create.php" method="POST">
            <div class="form-group">
                <label for="name">Nama Produk</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>
            <div class="form-group">
                <label for="category">Kategori</label>
                <input type="text" id="category" name="category" value="<?= htmlspecialchars($category) ?>" required>
            </div>
            <div class="form-group">
                <label for="price">Harga (Rp)</label>
                <input type="number" id="price" name="price" step="0.01" value="<?= htmlspecialchars($price) ?>" required>
            </div>
            <div class="form-group">
                <label for="stock">Stok</label>
                <input type="number" id="stock" name="stock" value="<?= htmlspecialchars($stock) ?>" required>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Simpan</button>
                <a href="index.php" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>