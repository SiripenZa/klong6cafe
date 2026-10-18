<?php
session_start();
require_once 'conn.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = (string)$_SESSION['customer_id'];
$stmt = $conn->prepare('SELECT customer_id, firstname, lastname, email, telephone, address, created_at FROM customers WHERE customer_id = ? LIMIT 1');

if (!$stmt) {
    http_response_code(500);
    exit('ไม่สามารถโหลดข้อมูลโปรไฟล์ได้');
}

$stmt->bind_param('s', $customerId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    header('Location: logout.php');
    exit;
}

$escape = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ของฉัน | Klong 6 Cafe</title>
    <link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --pink: #ed367f; --pink-dark: #c51b62; --pink-soft: #fff0f6; --ink: #632b43; --line: #ffd0e0; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(145deg, #fff7fa, #ffe6ef); color: var(--ink); font-family: 'Mali', sans-serif; }
        .site-header { padding: 18px 5%; background: linear-gradient(120deg, #ff8fb8, #ed367f); color: #fff; }
        .header-inner { width: min(1180px, 100%); margin: auto; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { color: inherit; font-size: 22px; font-weight: 700; text-decoration: none; }
        .header-link, .button { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 9px 16px; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: var(--pink-dark); font: inherit; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .page { width: min(900px, 92%); margin: 42px auto; }
        .profile-card { overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: rgba(255,255,255,.92); box-shadow: 0 16px 40px rgba(174,45,96,.12); }
        .card-heading { padding: 24px 28px; border-bottom: 1px solid var(--line); background: var(--pink-soft); }
        .card-heading h1 { margin: 0; color: var(--pink-dark); font-size: 27px; }
        .card-heading p { margin: 4px 0 0; font-size: 13px; }
        .profile-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 24px; padding: 12px 28px; }
        .detail { min-width: 0; padding: 17px 0; border-bottom: 1px solid #f5e3ea; }
        .detail dt { margin-bottom: 5px; color: #a75b79; font-size: 12px; }
        .detail dd { margin: 0; overflow-wrap: anywhere; font-size: 15px; font-weight: 600; white-space: pre-wrap; }
        .card-actions { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 28px 24px; }
        .button-primary { border-color: var(--pink); background: var(--pink); color: #fff; }
        .button-primary:hover { background: var(--pink-dark); }
        @media (max-width: 600px) { .site-header { padding: 15px 4%; } .brand { font-size: 18px; } .page { margin: 24px auto; } .card-heading { padding: 20px; } .card-heading h1 { font-size: 22px; } .profile-details { grid-template-columns: 1fr; padding: 8px 20px; } .detail { padding: 13px 0; } .card-actions { padding: 16px 20px 20px; } }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="Home.php">🍒 KLONG 6 CAFE</a>
            <a class="header-link" href="Home.php">กลับหน้าหลัก</a>
        </div>
    </header>
    <main class="page">
        <section class="profile-card">
            <div class="card-heading">
                <h1>โปรไฟล์ของฉัน</h1>
                <p>ข้อมูลบัญชีลูกค้า</p>
            </div>
            <dl class="profile-details">
                <div class="detail"><dt>รหัสสมาชิก</dt><dd><?= $escape($profile['customer_id']) ?></dd></div>
                <div class="detail"><dt>ชื่อ - นามสกุล</dt><dd><?= $escape($profile['firstname'] . ' ' . $profile['lastname']) ?></dd></div>
                <div class="detail"><dt>อีเมล</dt><dd><?= $escape($profile['email']) ?></dd></div>
                <div class="detail"><dt>เบอร์โทรศัพท์</dt><dd><?= $escape($profile['telephone']) ?></dd></div>
                <div class="detail"><dt>ที่อยู่</dt><dd><?= $escape($profile['address'] ?: 'ยังไม่ได้ระบุ') ?></dd></div>
                <div class="detail"><dt>วันที่สมัคร</dt><dd><?= $escape($profile['created_at']) ?></dd></div>
            </dl>
            <div class="card-actions">
                <a class="button button-primary" href="editprofile.php">แก้ไขโปรไฟล์</a>
            </div>
        </section>
    </main>
</body>
</html>