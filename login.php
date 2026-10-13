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

        $stmt = $conn->prepare("
            SELECT customer_id, firstname, lastname, email, password
            FROM customers
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $customer = $result->fetch_assoc();

        if ($customer && password_verify($password, $customer["password"])) {

            $_SESSION["customer_id"] = $customer["customer_id"];
            $_SESSION["customer_firstname"] = $customer["firstname"];
            $_SESSION["customer_lastname"] = $customer["lastname"];
            $_SESSION["customer_email"] = $customer["email"];

            header("Location: index.php");
            exit;

        } else {

            $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
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
    <title>เข้าสู่ระบบ | KLONG 6 CAFE</title>

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

<section style="max-width:480px; margin:60px auto 80px; padding:0 20px;">

    <div style="background:#fff; border-radius:25px; padding:35px; box-shadow:0 8px 25px rgba(193,18,63,0.10);">

        <div style="text-align:center;">
            <div style="color:#c1123f; letter-spacing:4px; font-size:14px;">
                WELCOME BACK
            </div>

            <h2 style="color:#c1123f; font-size:32px; margin:8px 0 25px;">
                เข้าสู่ระบบ 💗
            </h2>
        </div>

        <?php if ($error !== ""): ?>
            <div style="background:#ffe0e0; color:#c1123f; padding:12px 15px; border-radius:12px; margin-bottom:18px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <label>อีเมล</label>

            <input
                type="email"
                name="email"
                required
                value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                style="width:100%; box-sizing:border-box; padding:14px; margin:7px 0 18px; border:1px solid #ddd; border-radius:12px; font-family:inherit;"
            >

            <label>รหัสผ่าน</label>

            <input
                type="password"
                name="password"
                required
                style="width:100%; box-sizing:border-box; padding:14px; margin:7px 0 22px; border:1px solid #ddd; border-radius:12px; font-family:inherit;"
            >

            <button
                type="submit"
                style="width:100%; border:0; background:#c1123f; color:#fff; padding:14px; border-radius:50px; font-family:inherit; font-size:17px; font-weight:700; cursor:pointer;">
                เข้าสู่ระบบ 💗
            </button>

        </form>

        <div style="text-align:center; margin-top:20px; color:#777;">
            ยังไม่มีบัญชี?
            <a href="register.php" style="color:#c1123f; font-weight:700;">
                สมัครสมาชิก
            </a>
        </div>

        <div style="text-align:center; margin-top:15px;">
            <a href="index.php" style="color:#777; text-decoration:none;">
                ← กลับหน้าหลัก
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