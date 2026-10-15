```php
<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: order.php');
    exit;
}

$order_id = trim($_POST['order_id'] ?? '');
$status = $_POST['status'] ?? '';
$payment_status = $_POST['payment_status'] ?? '';

$allowed_status = [
    'pending',
    'confirmed',
    'preparing',
    'ready',
    'completed',
    'cancelled'
];

$allowed_payment_status = [
    'pending_review',
    'approved',
    'rejected',
    'paid'
];

if (
    $order_id === '' ||
    !in_array($status, $allowed_status, true) ||
    !in_array($payment_status, $allowed_payment_status, true)
) {
    exit('ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบสถานะในฐานข้อมูล');
}

$stmt = $conn->prepare(
    "UPDATE orders
     SET status = ?, payment_status = ?
     WHERE order_id = ?"
);

$stmt->bind_param('sss', $status, $payment_status, $order_id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();

    header('Location: order.php?updated=1');
    exit;
}

echo 'ไม่สามารถอัปเดตออเดอร์ได้';
$stmt->close();
$conn->close();
?>
```