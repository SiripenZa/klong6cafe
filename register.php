
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
    } elseif (!preg_match('/^[0-9]{10}$/', $telephone)) {
        $error = "เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก";
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
do {
    $customer_id = "CUS" . date("ymdHis") . rand(100, 999);

    $check_id = $conn->prepare("
        SELECT customer_id
        FROM customers
        WHERE customer_id = ?
        LIMIT 1
    ");

    $check_id->bind_param("s", $customer_id);
    $check_id->execute();

    $id_result = $check_id->get_result();
    $id_exists = $id_result->num_rows > 0;

    $check_id->close();

} while ($id_exists);
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
        created_at,
        status
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
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

    <style>
        :root {
            --pink-deep: #f0457a;
            --pink: #ff6b9a;
            --pink-soft: #ffc2d6;
            --pink-pale: #fff0f5;
            --berry: #c1123f;
            --ink: #5a2236;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; }

        body {
            font-family: 'Mali', cursive;
            color: var(--ink);
            background: url('login.png') center / cover no-repeat fixed;
            min-height: 100vh;
        }

        /* ===== ส่วนหัว ===== */
        .site-header {
            background: transparent;
        }

        .header-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 20px 28px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .logo { text-decoration: none; color: #fff; display: block; }

        .logo-title {
            font-size: 34px;
            font-weight: 700;
            letter-spacing: 1px;
            text-shadow: 0 2px 6px rgba(193, 18, 63, .25);
        }

        .logo-sub {
            font-size: 11px;
            letter-spacing: 3px;
            font-weight: 600;
            margin-top: 2px;
        }

        .pill-btn {
            display: inline-block;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            padding: 10px 22px;
            border-radius: 999px;
            background: #fff;
            color: var(--pink-deep);
            box-shadow: 0 4px 12px rgba(240, 69, 122, .18);
        }

        .nav-wrap { padding: 0 20px 22px; }

        .nav-pill {
            max-width: 720px;
            margin: 0 auto;
            background: rgba(255, 255, 255, .85);
            border-radius: 999px;
            padding: 8px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
            box-shadow: 0 6px 18px rgba(240, 69, 122, .15);
        }

        .nav-pill a {
            text-decoration: none;
            color: var(--ink);
            font-weight: 700;
            font-size: 14px;
            padding: 10px 22px;
            border-radius: 999px;
        }

        .nav-pill a:hover { background: var(--pink-pale); }

        .nav-pill a.active {
            background: linear-gradient(90deg, var(--pink-deep), var(--pink));
            color: #fff;
            box-shadow: 0 4px 12px rgba(240, 69, 122, .35);
        }

        /* ===== เวทีหลัก (ซ้าย: ต้อนรับ / ขวา: ฟอร์ม) ===== */
        .stage {
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            display: grid;
            place-items: start center;
            padding: 0 16px;
            background: transparent;
        }

        .float {
            position: absolute;
            user-select: none;
            pointer-events: none;
            opacity: .9;
            animation: bob 5s ease-in-out infinite;
        }
        .f1 { top: 40px;  left: 4%;   font-size: 44px; }
        .f2 { top: 120px; right: 5%;  font-size: 38px; animation-delay: 1s; }
        .f3 { bottom: 70px; left: 6%; font-size: 40px; animation-delay: 2s; }
        .f4 { bottom: 40px; right: 8%; font-size: 46px; animation-delay: .5s; }
        .f5 { top: 46%; left: 46%;    font-size: 26px; animation-delay: 1.5s; }

        @keyframes bob {
            0%, 100% { transform: translateY(0) rotate(-4deg); }
            50%      { transform: translateY(-10px) rotate(4deg); }
        }

        .stage-inner {
            position: relative;
            max-width: 420px;
            margin: 30vh auto 0;
            display: grid;
            grid-template-columns: 1fr;
            gap: 32px;
            justify-items: center;
        }

        /* ----- ข้อความต้อนรับ (กึ่งกลาง) ----- */
        .welcome { text-align: center; }

        .welcome small {
            display: block;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 3px;
            color: var(--pink-deep);
            margin-bottom: 8px;
        }

        .welcome h1 {
            margin: 0 0 14px;
            font-size: clamp(36px, 5vw, 56px);
            line-height: 1.12;
            color: var(--pink-deep);
            text-shadow: 0 3px 0 #fff;
        }

        .welcome > p {
            margin: 0 0 26px;
            font-size: 16px;
            line-height: 1.7;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }

        .extras { width: 100%; text-align: center; }

        .perks { display: grid; gap: 14px; margin-bottom: 26px; text-align: left; }

        .perk {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(255, 255, 255, .8);
            border: 1px solid #ffd3e1;
            border-radius: 22px;
            padding: 14px 18px;
            box-shadow: 0 6px 16px rgba(240, 69, 122, .10);
        }

        .perk-icon {
            flex: 0 0 52px;
            height: 52px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 26px;
            background: linear-gradient(135deg, #ffd0e0, #ffe7ef);
        }

        .perk b { display: block; font-size: 15px; color: var(--berry); }
        .perk span { font-size: 13px; color: #8a5568; }

        .badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }

        .badge {
            background: var(--pink);
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 999px;
            box-shadow: 0 4px 10px rgba(240, 69, 122, .25);
        }

        .badge.alt { background: #fff; color: var(--pink-deep); }

        /* ----- ฝั่งขวา: การ์ดฟอร์ม ----- */
        .form-card {
            width: 100%;
            background: transparent;
            border-radius: 0;
            padding: 10px 30px 12px;
            box-shadow: none;
            border: 0;
            font-size: 15px;
            line-height: 1.2;
        }

        .form-head { text-align: center; margin-bottom: 6px; transform: translateY(-24px); }
        .form-head h2 { margin: 0 0 3px; color: var(--pink-deep); font-size: 26px; transform: translateY(-3px); }
        .form-head span { color: #a0647a; font-size: 15px; }

        .alert {
            padding: 6px 10px;
            border-radius: 12px;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 15px;
        }
        .alert.error   { background: #ffe3ea; color: var(--berry); border: 1px solid #ffc2d1; }
        .alert.success { background: #e5f8ea; color: #247a3d; border: 1px solid #bfe9cb; }
        .alert.success a { color: #247a3d; font-weight: 700; }

        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
        .field { margin-bottom: 4px; }

        label { display: block; font-weight: 700; font-size: 15px; margin-bottom: 2px; }

        input[type="text"], input[type="email"], input[type="tel"], input[type="password"], textarea {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #ffd3e1;
            border-radius: 10px;
            background: rgba(255, 248, 251, .88);
            font-family: inherit;
            font-size: 15px;
            line-height: 1.2;
            color: var(--ink);
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }

        input:focus, textarea:focus {
            border-color: var(--pink);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(255, 107, 154, .18);
        }

        textarea { resize: vertical; }

        .submit-btn {
            width: 100%;
            border: 0;
            margin-top: 0;
            padding: 8px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--pink-deep), var(--pink));
            color: #fff;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(240, 69, 122, .35);
            transition: transform .15s, box-shadow .15s;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(240, 69, 122, .4); }
        .submit-btn:focus-visible { outline: 3px solid var(--berry); outline-offset: 3px; }

        .login-link { text-align: center; margin-top: 7px; color: #a0647a; font-size: 15px; }
        .login-link a { color: var(--pink-deep); font-weight: 700; }

        @media (prefers-reduced-motion: reduce) { .float { animation: none; } }

        @media (max-width: 900px) { .f5 { display: none; } }

        @media (max-width: 560px) {
            .row { grid-template-columns: 1fr; }
            .form-card { padding: 10px 18px 12px; }
            .header-inner { justify-content: center; text-align: center; }
            .stage { padding: 0 12px; }
        }
    </style>
</head>

<body>
<main class="stage">

    <div class="stage-inner">

        <!-- ฟอร์มสมัครสมาชิก (กึ่งกลาง) -->
        <section class="form-card">

            <div class="form-head">
                <h2>♡ สมัครสมาชิก ♡</h2>
                <span>กรอกข้อมูลของคุณเพื่อเริ่มต้นกับ KLONG 6 CAFE</span>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert success">
                    <?= htmlspecialchars($success) ?>

                    <div style="margin-top:10px;">
                        <a href="login.php">เข้าสู่ระบบ →</a>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="row">
                    <div class="field">
                        <label for="firstname">ชื่อ *</label>
                        <input type="text" id="firstname" name="firstname" required
                               value="<?= htmlspecialchars($_POST["firstname"] ?? "") ?>">
                    </div>

                    <div class="field">
                        <label for="lastname">นามสกุล *</label>
                        <input type="text" id="lastname" name="lastname" required
                               value="<?= htmlspecialchars($_POST["lastname"] ?? "") ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="email">อีเมล *</label>
                    <input type="email" id="email" name="email" required
                           value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">
                </div>

                <div class="field">
                    <label for="telephone">เบอร์โทรศัพท์ *</label>
                          <input type="tel" id="telephone" name="telephone" required
                              inputmode="numeric" minlength="10" maxlength="10"
                              pattern="[0-9]{10}" title="กรอกตัวเลข 10 หลัก"
                           value="<?= htmlspecialchars($_POST["telephone"] ?? "") ?>">
                </div>

                <div class="row">
                    <div class="field">
                        <label for="password">รหัสผ่าน *</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>

                    <div class="field">
                        <label for="confirm_password">ยืนยันรหัสผ่าน *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                </div>

                <div class="field">
                    <label for="address">ที่อยู่</label>
                    <textarea id="address" name="address" rows="1"><?= htmlspecialchars($_POST["address"] ?? "") ?></textarea>
                </div>

                <button type="submit" class="submit-btn">สมัครสมาชิก 💗</button>

            </form>

            <div class="login-link">
                มีบัญชีอยู่แล้ว?
                <a href="login.php">เข้าสู่ระบบ</a>
            </div>

        </section>

    </div>
</main>

</body>
</html>

<?php
$conn->close();
?>
