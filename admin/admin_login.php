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
        $stmt = $conn->prepare(
            "SELECT admin_id, username, password, fullname
             FROM admins
             WHERE username = ?
             LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_fullname'] = $admin['fullname'];

                $stmt->close();

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }

            $stmt->close();
        } else {
            $error = 'ระบบขัดข้อง กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | KLONG 6 CAFE</title>
<!-- นำเข้า Google Font (Prompt) -->
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html, body {
    width: 100%;
    height: 100vh;
    font-family: 'Prompt', sans-serif;
    overflow: hidden;
}

/* พื้นหลังภาพหลัก เต็มจอและจัดกึ่งกลาง */
.page-scene {
    position: relative;
    width: 100vw;
    height: 100vh;
    background-image: url('../login.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* กล่องฟอร์มวางทับในกรอบแก้วพอดี */
.login-wrapper {
    position: absolute;
    top: 53%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 420px;
    padding: 35px 30px 25px 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #a83e70;
}

.login-content {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.welcome {
    font-size: 13px;
    font-weight: 500;
    letter-spacing: 2px;
    color: #b0576e;
    margin-bottom: 6px;
    text-align: center;
}

.login-title {
    font-size: 26px;
    font-weight: 600;
    color: #d6336c;
    margin-bottom: 20px;
    text-align: center;
}

/* ข้อความแจ้งเตือน Error */
.error-message {
    width: 100%;
    margin-bottom: 15px;
    padding: 12px 15px;
    border: 1px dashed #ffb3c1;
    border-radius: 25px;
    background: rgba(255, 240, 243, 0.9);
    color: #b52d50;
    text-align: center;
    font-size: 13px;
}

.form-group {
    width: 100%;
    margin-bottom: 15px;
    text-align: left;
}

.form-group label {
    display: none; /* ซ่อน Label เพื่อให้ดีไซน์คลีนเหมือนหน้าลูกค้า แต่ยังคงโครงสร้างไว้ */
}

.form-input {
    display: block;
    width: 100%;
    padding: 14px 20px;
    border: 1px solid #ffccd5;
    border-radius: 30px;
    outline: none;
    background: rgba(255, 255, 255, 0.9);
    color: #555;
    font-family: inherit;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-input:focus {
    background: #ffffff;
    border-color: #ff6b8b;
    box-shadow: 0 0 10px rgba(255, 107, 139, 0.3);
}

.form-input::placeholder {
    color: #b897a1;
}

/* ปุ่มเข้าสู่ระบบ Admin */
.login-button {
    display: block;
    width: 100%;
    margin-top: 5px;
    margin-bottom: 20px;
    padding: 14px;
    border: none;
    border-radius: 30px;
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    box-shadow: 0 4px 15px rgba(255, 65, 108, 0.4);
    color: white;
    font-family: inherit;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
}

.login-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 65, 108, 0.6);
}

/* ปุ่มกลับหน้าหลัก */
.menu-button {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    padding: 6px;
    border: none;
    background: transparent;
    color: #b0576e;
    font-family: inherit;
    font-size: 13px;
    text-decoration: none;
    transition: color 0.2s;
}

.menu-button:hover {
    color: #ff416c;
}
</style>
</head>

<body>
<div class="page-scene">
    <section class="login-wrapper">
        <div class="login-content">

            <div class="welcome">❤ ADMIN PORTAL ❤</div>
            <h1 class="login-title">เข้าสู่ระบบผู้ดูแล</h1>

            <?php if ($error !== ''): ?>
                <div class="error-message" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" style="width: 100%;">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-input"
                        placeholder="ชื่อผู้ใช้ Admin"
                        value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="รหัสผ่าน"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="login-button">
                    🛡️ เข้าสู่ระบบผู้ดูแล ➜
                </button>
            </form>

            <a href="../index.php" class="menu-button">
                🏠 กลับหน้าเว็บไซต์
            </a>

        </div>
    </section>
</div>
</body>
</html>