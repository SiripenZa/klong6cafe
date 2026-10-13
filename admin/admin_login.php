<?php
session_start();
require_once '../conn.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $stmt = $conn->prepare("SELECT admin_id, username, password, fullname FROM admins WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_fullname'] = $admin['fullname'];

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | KLONG 6 CAFE</title>
</head>
<body style="margin:0;background:#fff0f5;font-family:Arial,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;">
<div style="width:380px;background:white;padding:40px;border-radius:25px;box-shadow:0 10px 30px rgba(0,0,0,.12);text-align:center;">
    <div style="font-size:35px;">🍒</div>
    <h1 style="margin:8px 0;color:#222;">KLONG 6 CAFE</h1>
    <p style="color:#888;margin-bottom:30px;">ADMIN LOGIN</p>

    <?php if ($error): ?>
        <div style="background:#ffe1e8;color:#c2185b;padding:12px;border-radius:12px;margin-bottom:18px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="username" placeholder="ชื่อผู้ใช้" required
        style="width:100%;box-sizing:border-box;padding:14px;border:1px solid #ddd;border-radius:12px;margin-bottom:15px;font-size:16px;">

        <input type="password" name="password" placeholder="รหัสผ่าน" required
        style="width:100%;box-sizing:border-box;padding:14px;border:1px solid #ddd;border-radius:12px;margin-bottom:20px;font-size:16px;">

        <button type="submit" style="width:100%;padding:14px;border:0;border-radius:25px;background:#222;color:white;font-size:16px;cursor:pointer;">
            เข้าสู่ระบบ
        </button>
    </form>

    <a href="../index.php" style="display:inline-block;margin-top:20px;color:#c2185b;text-decoration:none;">
        ← กลับหน้าเว็บไซต์
    </a>
</div>
</body>
</html>