<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    header('Location: products.php');
    exit;
}

$message = '';
$error = '';

$stmt = $conn->prepare("SELECT product_id, product_name, description, price, stock, image, status FROM products WHERE product_id = ? LIMIT 1");
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    die('ไม่พบสินค้านี้');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    $image = trim($_POST['image'] ?? '');
    $status = $_POST['status'] ?? 'available';

    if ($product_name === '') {
        $error = 'กรุณากรอกชื่อสินค้า';
    } elseif (!is_numeric($price) || $price < 0) {
        $error = 'กรุณากรอกราคาให้ถูกต้อง';
    } elseif (!is_numeric($stock) || $stock < 0) {
        $error = 'กรุณากรอกจำนวนสินค้าให้ถูกต้อง';
    } else {
        $stmt = $conn->prepare("UPDATE products SET product_name = ?, description = ?, price = ?, stock = ?, image = ?, status = ? WHERE product_id = ?");
        $stmt->bind_param("ssdisss", $product_name, $description, $price, $stock, $image, $status, $id);

        if ($stmt->execute()) {
            $message = 'แก้ไขสินค้าเรียบร้อยแล้ว';

            $product['product_name'] = $product_name;
            $product['description'] = $description;
            $product['price'] = $price;
            $product['stock'] = $stock;
            $product['image'] = $image;
            $product['status'] = $status;
        } else {
            $error = 'ไม่สามารถแก้ไขสินค้าได้: ' . $stmt->error;
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>แก้ไขสินค้า | KLONG 6 CAFE</title>
</head>
<body style="margin:0;background:#fff0f5;font-family:Arial,sans-serif;">
<div style="background:#ffb6c9;padding:22px 50px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <div style="font-size:28px;font-weight:bold;">🍒 KLONG 6 CAFE</div>
        <div>ADMIN PANEL</div>
    </div>
    <a href="logout.php" style="background:#222;color:white;text-decoration:none;padding:12px 22px;border-radius:25px;">ออกจากระบบ</a>
</div>

<div style="max-width:750px;margin:40px auto;padding:0 20px;">
    <div style="background:white;padding:35px;border-radius:25px;box-shadow:0 5px 20px rgba(0,0,0,.08);">
        <h1 style="margin-top:0;">แก้ไขสินค้า 🍰</h1>

        <?php if ($message): ?>
            <div style="background:#dff7e7;color:#18723c;padding:14px;border-radius:12px;margin-bottom:20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#ffe1e8;color:#c2185b;padding:14px;border-radius:12px;margin-bottom:20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <label>รหัสสินค้า</label>
            <input type="text" value="<?= htmlspecialchars($product['product_id']) ?>" readonly
            style="width:100%;box-sizing:border-box;padding:13px;margin:8px 0 18px;border:1px solid #ddd;border-radius:12px;background:#eee;">

            <label>ชื่อสินค้า</label>
            <input type="text" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>" required
            style="width:100%;box-sizing:border-box;padding:13px;margin:8px 0 18px;border:1px solid #ddd;border-radius:12px;">

            <label>รายละเอียด</label>
            <textarea name="description" rows="4"
            style="width:100%;box-sizing:border-box;padding:13px;margin:8px 0 18px;border:1px solid #ddd;border-radius:12px;"><?= htmlspecialchars($product['description']) ?></textarea>

            <label>ราคา</label>
            <input type="number" name="price" step="0.01" min="0" value="<?= htmlspecialchars($product['price']) ?>" required
            style="width:100%;box-sizing:border-box;padding:13px;margin:8px 0 18px;border:1px solid #ddd;border-radius:12px;">

            <label>จำนวนสินค้า</label>
            <input type="number" name="stock" min="0" value="<?= htmlspecialchars($product['stock']) ?>" required
            style="width:100%;box-sizing:border-box;padding:13px;margin:8px 0 18px;border:1px solid #ddd;border-radius:12px;">

            <label>ชื่อไฟล์รูป</label>
            <input type="text" name="image" value="<?= htmlspecialchars($product['image']) ?>" placeholder="เช่น cake.png หรือ images/cake.png"
            style="width:100%;box-sizing:border-box;padding:13px;margin:8px 0 18px;border:1px solid #ddd;border-radius:12px;">

            <label>สถานะ</label>
            <select name="status"
            style="width:100%;box-sizing:border-box;padding:13px;margin:8px 0 25px;border:1px solid #ddd;border-radius:12px;">
                <option value="available" <?= $product['status'] === 'available' ? 'selected' : '' ?>>พร้อมขาย</option>
                <option value="unavailable" <?= $product['status'] === 'unavailable' ? 'selected' : '' ?>>ไม่พร้อมขาย</option>
            </select>

            <button type="submit" style="width:100%;padding:14px;border:0;border-radius:25px;background:#222;color:white;font-size:16px;cursor:pointer;">
                💾 บันทึกการแก้ไข
            </button>
        </form>

        <a href="products.php" style="display:block;text-align:center;margin-top:20px;color:#c2185b;text-decoration:none;">
            ← กลับหน้าจัดการสินค้า
        </a>
    </div>
</div>
</body>
</html>