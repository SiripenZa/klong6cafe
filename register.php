<?php
session_start();
require_once "conn.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $firstname = trim($_POST["firstname"] ?? "");
    $lastname = trim($_POST["lastname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $telephone = trim($_POST["telephone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $address = trim($_POST["address"] ?? "");

    if ($firstname === "" || $lastname === "" || $email === "" || $telephone === "" || $password === "") {
        $error = "กรุณากรอกข้อมูลที่จำเป็นให้ครบ";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } elseif (strlen($password) < 6) {
        $error = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน";
    } else {

        // ตรวจสอบว่าอีเมลนี้มีสมาชิกแล้วหรือยัง
        $check = $conn->prepare("
            SELECT customer_id
            FROM customers
            WHERE email = ?
            LIMIT 1
        ");

        $check->bind_param("s", $email);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $error = "อีเมลนี้ถูกใช้สมัครสมาชิกแล้ว";

        } else {

            // สร้าง customer_id
            $customer_id = "CUS" . date("ymdHis") . rand(10, 99);

            // เข้ารหัสรหัสผ่าน
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO customers
                (
                    customer_id,
                    firstname,
                    lastname,
                    email,
                    telephone,
                    password,
                    address,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->bind_param(
                "sssssss",
                $customer_id,
                $firstname,
                $lastname,
                $email,
                $telephone,
                $password_hash,
                $address
            );

            if ($stmt->execute()) {

                $success = "สมัครสมาชิกสำเร็จ สามารถเข้าสู่ระบบได้เลย";

            } else {

                $error = "สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก | KLONG 6 CAFE</title>

    <link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body style="margin:0; background:#ffe5ed; font-family:'Mali',cursive; color:#222;">

<header style="background:#ffd5e1; text-align:center; padding:35px 20px;">
    <div style="font-size:14px; letter-spacing:5px; color:#c1123f;">
        SWEET MOMENTS EVERY DAY
    </div>

    <h1 style="margin:10px 0; color:#c1123f; font-size:36px;">
        🍒 KLONG 6 CAFE 🍰
    </h1>

    <p style="margin:0; color:#65313f;">
        Coffee ♡ Cake ♡ Happiness
    </p>
</header>

<nav style="text-align:center; padding:18px 10px;">
    <a href="index.php"
       style="display:inline-block; color:#000; text-decoration:none; margin:5px 8px;">
        หน้าหลัก
    </a>

    <a href="menu.php"
       style="display:inline-block; color:#000; text-decoration:none; margin:5px 8px;">
        เมนู
    </a>

    <a href="login.php"
       style="display:inline-block; background:#000; color:#fff; padding:10px 22px; border-radius:50px; text-decoration:none; margin:5px 8px;">
        เข้าสู่ระบบ
    </a>
</nav>

<section style="max-width:600px; margin:30px auto 70px; padding:0 20px;">

    <div style="background:#fff; border-radius:25px; padding:35px; box-shadow:0 8px 25px rgba(193,18,63,0.10);">

        <div style="text-align:center;">
            <div style="color:#c1123f; letter-spacing:4px; font-size:14px;">
                CREATE ACCOUNT
            </div>

            <h2 style="color:#c1123f; font-size:32px; margin:8px 0 25px;">
                สมัครสมาชิก 💗
            </h2>
        </div>

        <?php if ($error !== ""): ?>
            <div style="background:#ffe0e0; color:#c1123f; padding:12px 15px; border-radius:12px; margin-bottom:18px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div style="background:#e3f8e8; color:#247a3d; padding:12px 15px; border-radius:12px; margin-bottom:18px;">
                <?= htmlspecialchars($success) ?>

                <div style="margin-top:10px;">
                    <a href="login.php" style="color:#247a3d; font-weight:700;">
                        เข้าสู่ระบบ →
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST">

            <label>ชื่อ *</label>
            <input
                type="text"
                name="firstname"
                required
                value="<?= htmlspecialchars($_POST["firstname"] ?? "") ?>"
                style="width:100%; box-sizing:border-box; padding:13px; margin:7px 0 15px; border:1px solid #ddd; border-radius:12px; font-family:inherit;"
            >

            <label>นามสกุล *</label>
            <input
                type="text"
                name="lastname"
                required
                value="<?= htmlspecialchars($_POST["lastname"] ?? "") ?>"
                style="width:100%; box-sizing:border-box; padding:13px; margin:7px 0 15px; border:1px solid #ddd; border-radius:12px; font-family:inherit;"
            >

            <label>อีเมล *</label>
            <input
                type="email"
                name="email"
                required
                value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                style="width:100%; box-sizing:border-box; padding:13px; margin:7px 0 15px; border:1px solid #ddd; border-radius:12px; font-family:inherit;"
            >

            <label>เบอร์โทรศัพท์ *</label>
            <input
                type="text"
                name="telephone"
                required
                value="<?= htmlspecialchars($_POST["telephone"] ?? "") ?>"
                style="width:100%; box-sizing:border-box; padding:13px; margin:7px 0 15px; border:1px solid #ddd; border-radius:12px; font-family:inherit;"
            >

            <label>รหัสผ่าน *</label>
            <input
                type="password"
                name="password"
                required
                minlength="6"
                style="width:100%; box-sizing:border-box; padding:13px; margin:7px 0 15px; border:1px solid #ddd; border-radius:12px; font-family:inherit;"
            >

            <label>ยืนยันรหัสผ่าน *</label>
            <input
                type="password"
                name="confirm_password"
                required
                minlength="6"
                style="width:100%; box-sizing:border-box; padding:13px; margin:7px 0 15px; border:1px solid #ddd; border-radius:12px; font-family:inherit;"
            >

            <label>ที่อยู่</label>
            <textarea
                name="address"
                rows="4"
                style="width:100%; box-sizing:border-box; padding:13px; margin:7px 0 20px; border:1px solid #ddd; border-radius:12px; font-family:inherit; resize:vertical;"
            ><?= htmlspecialchars($_POST["address"] ?? "") ?></textarea>

            <button
                type="submit"
                style="width:100%; border:0; background:#c1123f; color:#fff; padding:14px; border-radius:50px; font-family:inherit; font-size:17px; font-weight:700; cursor:pointer;">
                สมัครสมาชิก 💗
            </button>

        </form>

        <div style="text-align:center; margin-top:20px; color:#777;">
            มีบัญชีอยู่แล้ว?
            <a href="login.php" style="color:#c1123f; font-weight:700;">
                เข้าสู่ระบบ
            </a>
        </div>

    </div>

</section>

<footer style="background:#c1123f; color:#fff; text-align:center; padding:35px 20px;">
    <h2>🍒 KLONG 6 CAFE 🍰</h2>
    <p>Coffee ♡ Cake ♡ Happiness</p>
</footer>

</body>
</html>

<?php
$conn->close();
?>