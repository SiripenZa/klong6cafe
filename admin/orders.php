<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$sql = "SELECT order_id, customer_id, order_date, pickup_date, pickup_time, total_amount, status, note
        FROM orders
        ORDER BY order_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>คำสั่งซื้อ | KLONG 6 CAFE</title>
</head>
<body style="margin:0;background:#fff0f5;font-family:Arial,sans-serif;">
<div style="background:#ffb6c9;padding:22px 50px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <div style="font-size:28px;font-weight:bold;">🍒 KLONG 6 CAFE</div>
        <div>ADMIN PANEL</div>
    </div>
    <a href="logout.php" style="background:#222;color:white;text-decoration:none;padding:12px 22px;border-radius:25px;">ออกจากระบบ</a>
</div>

<div style="max-width:1200px;margin:35px auto;padding:0 20px;">
    <h1 style="margin-bottom:5px;">คำสั่งซื้อ 🛒</h1>
    <p style="color:#777;">รายการคำสั่งซื้อทั้งหมดของลูกค้า</p>

    <div style="background:white;border-radius:20px;padding:20px;box-shadow:0 5px 20px rgba(0,0,0,.08);overflow-x:auto;margin-top:25px;">
        <table style="width:100%;border-collapse:collapse;min-width:900px;">
            <tr style="background:#ffe1e8;">
                <th style="padding:14px;text-align:left;">Order ID</th>
                <th style="padding:14px;text-align:left;">Customer ID</th>
                <th style="padding:14px;text-align:left;">วันที่สั่ง</th>
                <th style="padding:14px;text-align:left;">วันรับสินค้า</th>
                <th style="padding:14px;text-align:left;">เวลารับ</th>
                <th style="padding:14px;text-align:left;">ยอดรวม</th>
                <th style="padding:14px;text-align:left;">สถานะ</th>
                <th style="padding:14px;text-align:left;">หมายเหตุ</th>
                <th style="padding:14px;text-align:center;">จัดการ</th>
</tr>

            <?php if ($result->num_rows > 0): ?>
                <?php while ($order = $result->fetch_assoc()): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:14px;font-weight:bold;">
                            <?= htmlspecialchars($order['order_id']) ?>
                        </td>
                        <td style="padding:14px;">
                            <?= htmlspecialchars($order['customer_id']) ?>
                        </td>
                        <td style="padding:14px;">
                            <?= htmlspecialchars($order['order_date']) ?>
                        </td>
                        <td style="padding:14px;">
                            <?= htmlspecialchars($order['pickup_date']) ?>
                        </td>
                        <td style="padding:14px;">
                            <?= htmlspecialchars($order['pickup_time']) ?>
                        </td>
                        <td style="padding:14px;font-weight:bold;">
                            <?= number_format((float)$order['total_amount'], 2) ?> บาท
                        </td>
                        <td style="padding:14px;">
                            <span style="background:#ffe1e8;padding:7px 12px;border-radius:20px;">
                                <?= htmlspecialchars($order['status']) ?>
                            </span>
                        </td>
                        <td style="padding:14px;color:#777;">
                            <?= htmlspecialchars($order['note']) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="padding:45px;text-align:center;color:#888;">
                        ยังไม่มีคำสั่งซื้อในระบบ 🛒
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <a href="dashboard.php" style="display:inline-block;margin-top:25px;color:#c2185b;text-decoration:none;">← กลับ Dashboard</a>
</div>
</body>
</html>