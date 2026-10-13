<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$customer_id = $_GET['id'] ?? '';

if ($customer_id === '') {
    header('Location: customers.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT customer_id, firstname, lastname, email, telephone, address
    FROM customers
    WHERE customer_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $customer_id);
$stmt->execute();

$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    die('ไม่พบข้อมูลลูกค้านี้');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if ($firstname === '') {

        $error = 'กรุณากรอกชื่อ';

    } elseif ($lastname === '') {

        $error = 'กรุณากรอกนามสกุล';

    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'กรุณากรอกอีเมลให้ถูกต้อง';

    } else {

        if ($new_password !== '') {

            if (strlen($new_password) < 6) {

                $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';

            } else {

                $password_hash = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare("
                    UPDATE customers
                    SET firstname = ?,
                        lastname = ?,
                        email = ?,
                        telephone = ?,
                        password = ?,
                        address = ?
                    WHERE customer_id = ?
                ");

                $stmt->bind_param(
                    "sssssss",
                    $firstname,
                    $lastname,
                    $email,
                    $telephone,
                    $password_hash,
                    $address,
                    $customer_id
                );

                if ($stmt->execute()) {
                    header('Location: customers.php');
                    exit;
                } else {
                    $error = 'แก้ไขข้อมูลลูกค้าไม่สำเร็จ';
                }
            }

        } else {

            $stmt = $conn->prepare("
                UPDATE customers
                SET firstname = ?,
                    lastname = ?,
                    email = ?,
                    telephone = ?,
                    address = ?
                WHERE customer_id = ?
            ");

            $stmt->bind_param(
                "ssssss",
                $firstname,
                $lastname,
                $email,
                $telephone,
                $address,
                $customer_id
            );

            if ($stmt->execute()) {
                header('Location: customers.php');
                exit;
            } else {
                $error = 'แก้ไขข้อมูลลูกค้าไม่สำเร็จ';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>แก้ไขลูกค้า | KLONG 6 CAFE</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Tahoma, sans-serif;
    background: #fff0f5;
    color: #222;
}

.header {
    background: #ff4f81;
    color: white;
    padding: 25px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-size: 28px;
    font-weight: bold;
}

.container {
    width: 90%;
    max-width: 900px;
    margin: 40px auto;
}

.card {
    background: white;
    border-radius: 25px;
    padding: 35px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}

h1 {
    color: #d92f5c;
    margin-top: 0;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

input,
textarea {
    width: 100%;
    padding: 13px 15px;
    border: 2px solid #ffd0dd;
    border-radius: 12px;
    font-size: 16px;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.readonly {
    background: #f5f5f5;
}

.help {
    font-size: 13px;
    color: #777;
    margin-top: 6px;
}

.buttons {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

button,
.btn {
    border: none;
    padding: 13px 25px;
    border-radius: 30px;
    cursor: pointer;
    text-decoration: none;
    font-size: 16px;
    font-weight: bold;
}

.save {
    background: #ff4f81;
    color: white;
}

.back {
    background: #222;
    color: white;
}

.error {
    background: #ffe0e6;
    color: #c40035;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 20px;
}
</style>
</head>

<body>

<div class="header">
    <div class="logo">🍒 KLONG 6 CAFE</div>
    <div>ADMIN PANEL</div>
</div>

<div class="container">

<div class="card">

<h1>👤 แก้ไขข้อมูลลูกค้า</h1>

<?php if ($error): ?>
    <div class="error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post">

    <div class="form-group">
        <label>รหัสลูกค้า</label>

        <input
            type="text"
            value="<?= htmlspecialchars($customer['customer_id']) ?>"
            class="readonly"
            readonly
        >
    </div>

    <div class="form-group">
        <label>ชื่อ</label>

        <input
            type="text"
            name="firstname"
            value="<?= htmlspecialchars($customer['firstname']) ?>"
            required
        >
    </div>

    <div class="form-group">
        <label>นามสกุล</label>

        <input
            type="text"
            name="lastname"
            value="<?= htmlspecialchars($customer['lastname']) ?>"
            required
        >
    </div>

    <div class="form-group">
        <label>อีเมล</label>

        <input
            type="email"
            name="email"
            value="<?= htmlspecialchars($customer['email']) ?>"
            required
        >
    </div>

    <div class="form-group">
        <label>เบอร์โทรศัพท์</label>

        <input
            type="text"
            name="telephone"
            value="<?= htmlspecialchars($customer['telephone']) ?>"
        >
    </div>

    <div class="form-group">
        <label>ที่อยู่</label>

        <textarea name="address"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>เปลี่ยนรหัสผ่านใหม่</label>

        <input
            type="password"
            name="new_password"
            placeholder="ถ้าไม่เปลี่ยน ให้เว้นว่าง"
        >

        <div class="help">
            ถ้าไม่ต้องการเปลี่ยนรหัสผ่าน ให้เว้นช่องนี้ว่าง
        </div>
    </div>

    <div class="buttons">

        <button type="submit" class="save">
            💾 บันทึกการแก้ไข
        </button>

        <a href="customers.php" class="btn back">
            ← กลับ
        </a>

    </div>

</form>

</div>

</div>

</body>
</html>