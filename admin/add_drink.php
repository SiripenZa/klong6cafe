<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$error = '';
$categories = $conn->query("
    SELECT category_id, category_name
    FROM categories
    ORDER BY category_name ASC
");

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าจากฟอร์ม
    $drink_id     = trim($_POST['drink_id'] ?? '');
    $category_id  = trim($_POST['category_id'] ?? '');
    $drink_name   = trim($_POST['drink_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $stock       = $_POST['stock'] ?? '';
    $image       = trim($_POST['image'] ?? '');
    $status      = $_POST['status'] ?? 'available';

    // ตรวจสอบข้อมูล
    if ($drink_id === '') {
        $error = 'กรุณากรอกรหัสเครื่องดื่ม';
    } elseif ($category_id === '') {
        $error = 'กรุณาเลือกหมวดหมู่เครื่องดื่ม';
    } elseif ($drink_name === '') {
        $error = 'กรุณากรอกชื่อเครื่องดื่ม';
    } elseif ($price === '' || !is_numeric($price) || (float)$price < 0) {
        $error = 'กรุณากรอกราคาให้ถูกต้อง';
    } elseif (
        $stock === '' ||
        !is_numeric($stock) ||
        (float)$stock < 0 ||
        (float)$stock != (int)$stock
    ) {
        $error = 'กรุณากรอกจำนวนสต็อกเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป';
    } elseif (!in_array($status, ['available', 'unavailable'], true)) {
        $error = 'สถานะสินค้าไม่ถูกต้อง';
    } else {

        // ตรวจสอบว่ารหัสเครื่องดื่มซ้ำหรือไม่
        $check = $conn->prepare(
            "SELECT drink_id FROM drink WHERE drink_id = ? LIMIT 1"
        );

        if (!$check) {
            $error = 'ตรวจสอบรหัสเครื่องดื่มไม่สำเร็จ: ' . $conn->error;
        } else {
            $check->bind_param('s', $drink_id);
            $check->execute();

            $checkResult = $check->get_result();
            $exists = $checkResult->num_rows > 0;

            $check->close();

            if ($exists) {
                $error = 'รหัสเครื่องดื่มนี้มีอยู่แล้ว กรุณาใช้รหัสอื่น';
            } else {

                $price = (float)$price;
                $stock = (int)$stock;

                // เพิ่มข้อมูลลงตาราง drink
                $stmt = $conn->prepare(
                    "INSERT INTO drink
                    (
                        drink_id,
                        category_id,
                        drink_name,
                        description,
                        price,
                        stock,
                        image,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $error = 'เตรียมคำสั่งเพิ่มเครื่องดื่มไม่สำเร็จ: '
                           . $conn->error;
                } else {
                    $stmt->bind_param(
                        'ssssdiss',
                        $drink_id,
                        $category_id,
                        $drink_name,
                        $description,
                        $price,
                        $stock,
                        $image,
                        $status
                    );

                    if ($stmt->execute()) {
                        $stmt->close();
                        header('Location: drink.php?success=added');
                        exit;
                    } else {
                        $error = 'เพิ่มเครื่องดื่มไม่สำเร็จ: '
                               . $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>เพิ่มเครื่องดื่ม | KLONG 6 CAFE</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #fff0f3;
            color: #222;
            font-family: 'Mali', cursive;
        }

        header {
            background: #ffb6c9;
            padding: 25px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .brand {
            font-size: 32px;
            font-weight: 700;
            color: #111;
        }

        .admin-label {
            font-size: 18px;
            color: #111;
        }

        .btn {
            display: inline-block;
            padding: 12px 22px;
            border-radius: 50px;
            text-decoration: none;
            font-family: inherit;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            border: none;
        }

        .btn-dark {
            background: #222;
            color: #fff;
        }

        .btn-cancel {
            background: #ddd;
            color: #333;
        }

        .btn-submit {
            background: #c1123f;
            color: #fff;
            font-size: 16px;
            padding: 13px 35px;
        }

        .container {
            max-width: 900px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0;
            color: #111;
            font-size: 36px;
        }

        .page-heading p {
            color: #75404d;
            margin-top: 8px;
        }

        .form-card {
            background: #fff;
            border-radius: 25px;
            padding: 35px;
            box-shadow: 0 8px 30px rgba(193, 18, 63, .10);
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
        }

        input,
        textarea,
        select {
            width: 100%;
            box-sizing: border-box;
            padding: 13px 16px;
            border: 1px solid #ddd;
            border-radius: 15px;
            font-family: 'Mali', cursive;
            font-size: 16px;
            background: #fff;
        }

        textarea {
            resize: vertical;
        }

        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .form-row .form-group {
            flex: 1;
            min-width: 220px;
            margin-bottom: 0;
        }

        .help-text {
            color: #777;
            font-size: 13px;
            margin-top: 7px;
        }

        .error {
            background: #ffdede;
            color: #b00020;
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .form-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        @media (max-width: 600px) {
            header {
                padding: 20px;
            }

            .brand {
                font-size: 25px;
            }

            .page-heading h1 {
                font-size: 28px;
            }

            .form-card {
                padding: 22px;
            }

            .container {
                margin: 25px auto;
            }
        }
    </style>
</head>

<body>

<header>
    <div>
        <div class="brand">🍒 KLONG 6 CAFE</div>
        <div class="admin-label">ADMIN PANEL</div>
    </div>

    <a href="logout.php" class="btn btn-dark">
        ออกจากระบบ
    </a>
</header>

<main class="container">

    <div class="page-heading">
        <div>
            <h1>เพิ่มเครื่องดื่ม 🥤</h1>
            <p>เพิ่มสินค้าใหม่เข้าสู่เมนูของร้าน</p>
        </div>

        <a href="drink.php" class="btn btn-dark">
            ← กลับ
        </a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="form-card">

        <form method="POST" action="">

            <!-- รหัสเครื่องดื่ม -->
            <div class="form-group">
                <label for="drink_id">รหัสเครื่องดื่ม (drink_id)</label>

                <input
                    type="text"
                    id="drink_id"
                    name="drink_id"
                    value="<?= h($_POST['drink_id'] ?? '') ?>"
                    placeholder="เช่น D001"
                    maxlength="11"
                    required
                >

                <div class="help-text">
                    กรอกรหัสเครื่องดื่มที่ไม่ซ้ำกับสินค้าเดิมในฐานข้อมูล
                </div>
            </div>
            <!-- หมวดหมู่ -->
<div class="form-group">

    <label for="category_id">
        หมวดหมู่
    </label>

    <select
        id="category_id"
        name="category_id"
        required
    >

        <option value="">
            -- เลือกหมวดหมู่ --
        </option>

        <?php if ($categories): ?>

            <?php while ($category = $categories->fetch_assoc()): ?>

                <option
                    value="<?= h($category['category_id']) ?>"
                    <?= (
                        ($_POST['category_id'] ?? '')
                        === $category['category_id']
                    )
                    ? 'selected'
                    : '' ?>
                >
                    <?= h($category['category_name']) ?>
                </option>

            <?php endwhile; ?>

        <?php endif; ?>

    </select>

</div>

            <!-- ชื่อเครื่องดื่ม -->
            <div class="form-group">
                <label for="drink_name">ชื่อเครื่องดื่ม</label>

                <input
                    type="text"
                    id="drink_name"
                    name="drink_name"
                    value="<?= h($_POST['drink_name'] ?? '') ?>"
                    placeholder="เช่น Blue flower cake"
                    required
                >
            </div>

            <!-- รายละเอียด -->
            <div class="form-group">
                <label for="description">รายละเอียดสินค้า</label>

                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    placeholder="รายละเอียดของเค้ก"
                ><?= h($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- ราคาและสต็อก -->
            <div class="form-row">

                <div class="form-group">
                    <label for="price">ราคา (บาท)</label>

                    <input
                        type="number"
                        id="price"
                        name="price"
                        step="0.01"
                        min="0"
                        value="<?= h($_POST['price'] ?? '') ?>"
                        placeholder="420.00"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="stock">จำนวนสต็อก</label>

                    <input
                        type="number"
                        id="stock"
                        name="stock"
                        min="0"
                        step="1"
                        value="<?= h($_POST['stock'] ?? '') ?>"
                        placeholder="10"
                        required
                    >
                </div>

            </div>

            <!-- รูปภาพ -->
            <div class="form-group">
                <label for="image">ชื่อไฟล์รูปสินค้า</label>

                <input
                    type="text"
                    id="image"
                    name="image"
                    value="<?= h($_POST['image'] ?? '') ?>"
                    placeholder="เช่น blueflower.png"
                >

                <div class="help-text">
                    ใส่ชื่อไฟล์รูปหรือพาธรูปที่ระบบของคุณใช้งานอยู่
                </div>
            </div>

            <!-- สถานะ -->
            <div class="form-group">
                <label for="status">สถานะสินค้า</label>

                <select id="status" name="status">
                    <option
                        value="available"
                        <?= (($_POST['status'] ?? 'available') === 'available') ? 'selected' : '' ?>
                    >
                        พร้อมขาย
                    </option>

                    <option
                        value="unavailable"
                        <?= (($_POST['status'] ?? '') === 'unavailable') ? 'selected' : '' ?>
                    >
                        ไม่พร้อมขาย
                    </option>
                </select>
            </div>

            <!-- ปุ่ม -->
            <div class="form-buttons">

                <a href="drink.php" class="btn btn-cancel">
                    ยกเลิก
                </a>

                <button type="submit" class="btn btn-submit">
                    + เพิ่มเครื่องดื่ม
                </button>
            </div>
        </form>
    </div>
</main>
</body>
</html>

<?php
$conn->close();
?>