<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
$fullname = $_SESSION['admin_fullname'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | KLONG 6 CAFE</title>
</head>
<body style="margin:0;background:#fff0f5;font-family:Arial,sans-serif;">
<div style="background:#ffb6c9;padding:25px 50px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <div style="font-size:28px;font-weight:bold;">🍒 KLONG 6 CAFE</div>
        <div style="margin-top:5px;">ADMIN PANEL</div>
    </div>
    <a href="logout.php" style="background:#222;color:white;text-decoration:none;padding:12px 22px;border-radius:25px;">ออกจากระบบ</a>
</div>

<div style="max-width:1100px;margin:40px auto;padding:0 20px;">
    <h1>สวัสดี, <?= htmlspecialchars($fullname) ?> 👋</h1>
    <p style="color:#777;">ยินดีต้อนรับเข้าสู่ระบบจัดการร้าน KLONG 6 CAFE</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-top:35px;">

        <a href="products.php" style="text-decoration:none;color:#111;background:white;padding:30px;border-radius:20px;box-shadow:0 5px 20px rgba(0,0,0,.08);display:block;transition:.2s;">
            <div style="font-size:35px;">🍰</div>
            <h2>จัดการสินค้า</h2>
            <p style="color:#888;">เพิ่ม แก้ไข และจัดการสินค้า</p>
            <div style="color:#c2185b;font-weight:bold;margin-top:20px;">เข้าสู่การจัดการ →</div>
        </a>

        <a href="orders.php" style="text-decoration:none;color:#111;background:white;padding:30px;border-radius:20px;box-shadow:0 5px 20px rgba(0,0,0,.08);display:block;">
            <div style="font-size:35px;">🛒</div>
            <h2>คำสั่งซื้อ</h2>
            <p style="color:#888;">ดูและจัดการออเดอร์ลูกค้า</p>
            <div style="color:#c2185b;font-weight:bold;margin-top:20px;">ดูคำสั่งซื้อ →</div>
        </a>

        <a href="customers.php" style="text-decoration:none;color:#111;background:white;padding:30px;border-radius:20px;box-shadow:0 5px 20px rgba(0,0,0,.08);display:block;">
            <div style="font-size:35px;">👥</div>
            <h2>ลูกค้า</h2>
            <p style="color:#888;">ดูข้อมูลสมาชิกของร้าน</p>
            <div style="color:#c2185b;font-weight:bold;margin-top:20px;">ดูลูกค้า →</div>
        </a>

    </div>
</div>
</body>
</html>