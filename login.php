<?php
session_start();
require_once "conn.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "กรุณากรอกอีเมลและรหัสผ่าน";
    } else {
        $sql = "SELECT customer_id, firstname, lastname, email, password, status
                FROM customers
                WHERE email = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $customer = $result->fetch_assoc();
            $stmt->close();

            if ($customer && password_verify($password, $customer["password"])) {
                if (
                    isset($customer["status"]) &&
                    strtolower((string)$customer["status"]) !== "active"
                ) {
                    $error = "บัญชีนี้ยังไม่พร้อมใช้งาน";
                } else {
                    session_regenerate_id(true);

                    $customer_id = $customer["customer_id"];
                    $_SESSION["customer_id"] = $customer_id;
                    $_SESSION["customer_firstname"] = $customer["firstname"];
                    $_SESSION["customer_lastname"] = $customer["lastname"];
                    $_SESSION["customer_email"] = $customer["email"];

                    header("Location: index.php");
                    exit;
                }
            } else {
                $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
            }
        } else {
            $error = "ไม่สามารถตรวจสอบข้อมูลได้ กรุณาลองใหม่";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ | Klong 6 Cafe</title>
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

/* ทำให้หน้าจอเป็นฉากหลังเต็มจอและจัดกึ่งกลาง */
.page-scene {
    position: relative;
    width: 100vw;
    height: 100vh;
    background-image: url('login.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* ปรับตำแหน่ง top และ padding-top เพื่อดึงเนื้อหาด้านบนลงมาให้อยู่ในกรอบพอดี */
.login-wrapper {
    position: absolute;
    top: 53%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 420px;
    padding: 35px 30px 25px 30px; /* เพิ่มพื้นที่ด้านบน (padding-top) ให้ข้อความ WELCOME BACK ลงมาต่ำกว่าโบว์ */
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

/* ปุ่มเข้าสู่ระบบ */
.login-button {
    display: block;
    width: 100%;
    margin-top: 5px;
    margin-bottom: 15px;
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

/* เส้นแบ่ง */
.divider {
    width: 100%;
    margin: 5px 0 12px;
    color: #b0576e;
    font-size: 13px;
    font-weight: 300;
    text-align: center;
}

/* ปุ่มสมัครสมาชิก */
.register-button {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    margin-bottom: 12px;
    padding: 13px;
    border: 1.5px solid #ff8fa3;
    border-radius: 30px;
    background: transparent;
    color: #d6336c;
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.2s;
}

.register-button:hover {
    background: rgba(255, 143, 163, 0.15);
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
            <h1 class="login-title">เข้าสู่ระบบ</h1>

            <?php if ($error !== ""): ?>
                <div class="error-message" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" style="width: 100%;">
                <div class="form-group">
                    <input
                        type="email"
                        name="email"
                        class="form-input"
                        placeholder="อีเมล"
                        value="<?= htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="form-group">
                    <input
                        type="password"
                        name="password"
                        class="form-input"
                        placeholder="รหัสผ่าน"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="login-button">
                    ☕ เข้าสู่ระบบ ➜
                </button>
            </form>

            <div class="divider">ยังไม่มีบัญชี?</div>

            <a href="register.php" class="register-button">
                👤 สมัครสมาชิก
            </a>

            <a href="index.php" class="menu-button">
                🏠 กลับหน้าหลัก
            </a>

        </div>
    </section>
</div>
</body>
</html>