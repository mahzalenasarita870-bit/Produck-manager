<?php
session_start();
require_once '../config/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'Umum');
    $price    = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $stock    = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

    if (mb_strlen($name) < 3) $errors[] = 'Nama minimal 3 karakter.';
    if ($price <= 0) $errors[] = 'Harga harus lebih dari 0.';
    if ($stock < 0) $errors[] = 'Stok tidak boleh negatif.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE products SET name = :name, category = :category, price = :price, stock = :stock WHERE id = :id");
        $stmt->execute([
            'name'     => $name,
            'category' => $category,
            'price'    => $price,
            'stock'    => $stock,
            'id'       => $id
        ]);
        header('Location: index.php?status=updated');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Produk</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body>
<div class="container">
    <h2>Edit Produk</h2>
    <?php if (!empty($errors)): ?>
        <div style="color:red;"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <form action="edit.php?id=<?= $id ?>" method="POST">
        <div>
            <label>Nama Produk</label><br>
            <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
        </div>
        <div>
            <label>Kategori</label><br>
            <input type="text" name="category" value="<?= htmlspecialchars($product['category']) ?>" required>
        </div>
        <div>
            <label>Harga</label><br>
            <input type="number" name="price" step="0.01" value="<?= htmlspecialchars($product['price']) ?>" required>
        </div>
        <div>
            <label>Stok</label><br>
            <input type="number" name="stock" value="<?= htmlspecialchars($product['stock']) ?>" required>
        </div>
        <br>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
    </form>
</div>
</body>
</html>