
<?php
session_start();
require_once '../conn.php';

// ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// อนุญาตให้ลบผ่าน POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

// รับรหัสสินค้า
$product_id = trim($_POST['product_id'] ?? '');

if ($product_id === '') {
    header('Location: products.php?error=missing_id');
    exit;
}

// ตรวจสอบว่าสินค้ามีอยู่จริงก่อนลบ
$stmt = $conn->prepare(
    'SELECT image FROM products WHERE product_id = ?'
);

if (!$stmt) {
    header('Location: products.php?error=database');
    exit;
}

$stmt->bind_param('s', $product_id);
$stmt->execute();
$stmt->bind_result($image);

if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: products.php?error=not_found');
    exit;
}

$stmt->close();

// ลบข้อมูลสินค้าออกจากฐานข้อมูล
$stmt = $conn->prepare(
    'DELETE FROM products WHERE product_id = ?'
);

if (!$stmt) {
    header('Location: products.php?error=database');
    exit;
}

$stmt->bind_param('s', $product_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $stmt->close();
    $conn->close();

    header('Location: products.php?deleted=1');
    exit;
}

$stmt->close();
$conn->close();

header('Location: products.php?error=delete_failed');
exit;
?>
