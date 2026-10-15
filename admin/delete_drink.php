
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
    header('Location: drink.php');
    exit;
}

// รับรหัสสินค้า
$drink_id = trim($_POST['drink_id'] ?? '');

if ($drink_id === '') {
    header('Location: drink.php?error=missing_id');
    exit;
}

// ตรวจสอบว่าสินค้ามีอยู่จริงก่อนลบ
$stmt = $conn->prepare(
    'SELECT image FROM drink WHERE drink_id = ?'
);

if (!$stmt) {
    header('Location: drink.php?error=database');
    exit;
}

$stmt->bind_param('i', $drink_id);
$stmt->execute();
$stmt->bind_result($image);

if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: drink.php?error=not_found');
    exit;
}

$stmt->close();

// ลบข้อมูลสินค้าออกจากฐานข้อมูล
$stmt = $conn->prepare(
    'DELETE FROM drink WHERE drink_id = ? LIMIT 1'
);

if (!$stmt) {
    header('Location: drink.php?error=database');
    exit;
}

$stmt->bind_param('i', $drink_id);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    $stmt->close();
    $conn->close();

    header('Location: drink.php?deleted=1');
    exit;
}

$stmt->close();
$conn->close();

header('Location: drink.php?error=delete_failed');
exit;
?>
