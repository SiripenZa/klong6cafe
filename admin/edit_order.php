<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$order_id = $_GET['id'] ?? '';

if ($order_id === '') {
    header('Location: orders.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT order_id, customer_id, order_date, pickup_date, pickup_time, total_amount, status, note
    FROM orders
    WHERE order_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $order_id);
$stmt->execute();

$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    die('ไม่พบคำสั่งซื้อนี้');
}

$statuses = [];

$schema = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");

if ($schema && $row = $schema->fetch_assoc()) {

    if (preg_match("/^enum\\((.*)\\)$/", $row['Type'], $matches)) {
        $values = str_getcsv($matches[1], ',', "'");
        $statuses = $values;
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pickup_date = $_POST['pickup_date'] ?? '';
    $pickup_time = $_POST['pickup_time'] ?? '';
    $status = $_POST['status'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if ($status === '') {

        $error = 'กรุณาเลือกสถานะ';

    } elseif (!empty($statuses) && !in_array($status, $statuses, true)) {

        $error = 'สถานะไม่ถูกต้อง';

    } else {

        $stmt = $conn->prepare("
            UPDATE orders
            SET pickup_date = ?,
                pickup_time = ?,
                status = ?,
                note = ?
            WHERE order_id = ?
        ");

        $stmt->bind_param(
            "sssss",
            $pickup_date,
            $pickup_time,
            $status,
            $note,
            $order_id
        );

        if ($stmt->execute()) {
            header('Location: orders.php');
            exit;
        } else {
            $error = 'แก้ไขคำสั่งซื้อไม่สำเร็จ';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>แก้ไขคำสั่งซื้อ | KLONG 6 CAFE</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Tahoma, sans-serif;
    background: #fff0f5;
    color: #222;
}

.header {
    background: #ff4f81;
    color: white;
    padding: 25px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-size: 28px;
    font-weight: bold;
}

.container {
    width: 90%;
    max-width: 900px;
    margin: 40px auto;
}

.card {
    background: white;
    border-radius: 25px;
    padding: 35px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}

h1 {
    color: #d92f5c;
    margin-top: 0;
}

.info {
    background: #fff0f5;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 25px;
}

.info p {
    margin: 8px 0;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

input,
textarea,
select {
    width: 100%;
    padding: 13px 15px;
    border: 2px solid #ffd0dd;
    border-radius: 12px;
    font-size: 16px;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.readonly {
    background: #f5f5f5;
}

.buttons {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

button,
.btn {
    border: none;
    padding: 13px 25px;
    border-radius: 30px;
    cursor: pointer;
    text-decoration: none;
    font-size: 16px;
    font-weight: bold;
}

.save {
    background: #ff4f81;
    color: white;
}

.back {
    background: #222;
    color: white;
}

.error {
    background: #ffe0e6;
    color: #c40035;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 20px;
}
</style>
</head>

<body>

<div class="header">
    <div class="logo">🍒 KLONG 6 CAFE</div>
    <div>ADMIN PANEL</div>
</div>

<div class="container">

<div class="card">

<h1>✏️ แก้ไขคำสั่งซื้อ</h1>

<?php if ($error): ?>
    <div class="error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="info">
    <p>
        <strong>เลขที่คำสั่งซื้อ:</strong>
        <?= htmlspecialchars($order['order_id']) ?>
    </p>

    <p>
        <strong>รหัสลูกค้า:</strong>
        <?= htmlspecialchars($order['customer_id']) ?>
    </p>

    <p>
        <strong>วันที่สั่งซื้อ:</strong>
        <?= htmlspecialchars($order['order_date']) ?>
    </p>

    <p>
        <strong>ยอดรวม:</strong>
        <?= number_format((float)$order['total_amount'], 2) ?> บาท
    </p>
</div>

<form method="post">

    <div class="form-group">
        <label>วันที่นัดรับ</label>

        <input
            type="date"
            name="pickup_date"
            value="<?= htmlspecialchars($order['pickup_date'] ?? '') ?>"
        >
    </div>

    <div class="form-group">
        <label>เวลานัดรับ</label>

        <input
            type="time"
            name="pickup_time"
            value="<?= htmlspecialchars($order['pickup_time'] ?? '') ?>"
        >
    </div>

    <div class="form-group">
        <label>สถานะคำสั่งซื้อ</label>

        <select name="status" required>

            <?php if (!empty($statuses)): ?>

                <?php foreach ($statuses as $status): ?>

                    <option
                        value="<?= htmlspecialchars($status) ?>"
                        <?= $order['status'] === $status ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($status) ?>
                    </option>

                <?php endforeach; ?>

            <?php else: ?>

                <option value="<?= htmlspecialchars($order['status']) ?>">
                    <?= htmlspecialchars($order['status']) ?>
                </option>

            <?php endif; ?>

        </select>
    </div>

    <div class="form-group">
        <label>หมายเหตุ</label>

        <textarea name="note"><?= htmlspecialchars($order['note'] ?? '') ?></textarea>
    </div>

    <div class="buttons">

        <button type="submit" class="save">
            💾 บันทึกการแก้ไข
        </button>

        <a href="orders.php" class="btn back">
            ← กลับ
        </a>

    </div>

</form>

</div>

</div>

</body>
</html>