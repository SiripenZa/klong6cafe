<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$sql = "SELECT customer_id, firstname, lastname, email, telephone, address, created_at
        FROM customers
        ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ลูกค้า | KLONG 6 CAFE</title>
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
    <h1 style="margin-bottom:5px;">ลูกค้า 👥</h1>
    <p style="color:#777;">ข้อมูลสมาชิกของร้าน KLONG 6 CAFE</p>

    <div style="background:white;border-radius:20px;padding:20px;box-shadow:0 5px 20px rgba(0,0,0,.08);overflow-x:auto;margin-top:25px;">
        <table style="width:100%;border-collapse:collapse;min-width:900px;">
            <tr style="background:#ffe1e8;">
                <th style="padding:14px;text-align:left;">Customer ID</th>
                <th style="padding:14px;text-align:left;">ชื่อ</th>
                <th style="padding:14px;text-align:left;">นามสกุล</th>
                <th style="padding:14px;text-align:left;">อีเมล</th>
                <th style="padding:14px;text-align:left;">เบอร์โทร</th>
                <th style="padding:14px;text-align:left;">ที่อยู่</th>
                <th style="padding:14px;text-align:left;">สมัครเมื่อ</th>
            </tr>

            <?php if ($result->num_rows > 0): ?>
                <?php while ($customer = $result->fetch_assoc()): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:14px;font-weight:bold;">
                            <?= htmlspecialchars($customer['customer_id']) ?>
                        </td>
                        <td style="padding:14px;">
                            <?= htmlspecialchars($customer['firstname']) ?>
                        </td>
                        <td style="padding:14px;">
                            <?= htmlspecialchars($customer['lastname']) ?>
                        </td>
                        <td style="padding:14px;">
                            <?= htmlspecialchars($customer['email']) ?>
                        </td>
                        <td style="padding:14px;">
                            <?= htmlspecialchars($customer['telephone']) ?>
                        </td>
                        <td style="padding:14px;color:#777;">
                            <?= htmlspecialchars($customer['address']) ?>
                        </td>
                        <td style="padding:14px;">
                            <?= htmlspecialchars($customer['created_at']) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="padding:45px;text-align:center;color:#888;">
                        ยังไม่มีสมาชิกในระบบ 👥
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <a href="dashboard.php" style="display:inline-block;margin-top:25px;color:#c2185b;text-decoration:none;">← กลับ Dashboard</a>
</div>
</body>
</html>