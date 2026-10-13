<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_name = trim($_POST['product_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $price        = $_POST['price'] ?? '';
    $stock        = $_POST['stock'] ?? '';
    $image        = trim($_POST['image'] ?? '');
    $status       = $_POST['status'] ?? 'available';

    if ($product_name === '') {
        $error = 'กรุณากรอกชื่อสินค้า';
    } elseif ($price === '' || !is_numeric($price) || (float)$price < 0) {
        $error = 'กรุณากรอกราคาให้ถูกต้อง';
    } elseif ($stock === '' || !is_numeric($stock) || (int)$stock < 0) {
        $error = 'กรุณากรอกจำนวนสต็อกให้ถูกต้อง';
    } elseif (!in_array($status, ['available', 'unavailable'], true)) {
        $error = 'สถานะสินค้าไม่ถูกต้อง';
    } else {

        /*
         * สร้าง product_id ความยาว 11 ตัว
         * เช่น P2610134821
         */
        do {
            $product_id = 'P' . date('ymd') . rand(1000, 9999);

            $check = $conn->prepare("
                SELECT product_id
                FROM products
                WHERE product_id = ?
                LIMIT 1
            ");

            $check->bind_param("s", $product_id);
            $check->execute();

            $check_result = $check->get_result();
            $exists = $check_result->num_rows > 0;

            $check->close();

        } while ($exists);

        $price = (float)$price;
        $stock = (int)$stock;

        $stmt = $conn->prepare("
            INSERT INTO products
            (
                product_id,
                product_name,
                description,
                price,
                stock,
                image,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssdiss",
            $product_id,
            $product_name,
            $description,
            $price,
            $stock,
            $image,
            $status
        );

        if ($stmt->execute()) {

            header('Location: products.php?success=added');
            exit;

        } else {

            $error = 'เพิ่มสินค้าไม่สำเร็จ: ' . $stmt->error;
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

    <title>เพิ่มสินค้า | KLONG 6 CAFE</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >
</head>

<body style="
    margin:0;
    background:#fff0f3;
    color:#222;
    font-family:'Mali',cursive;
">

<!-- HEADER -->

<header style="
    background:#ffb6c9;
    padding:25px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
">

    <div>

        <div style="
            font-size:32px;
            font-weight:700;
            color:#111;
        ">
            🍒 KLONG 6 CAFE
        </div>

        <div style="
            font-size:18px;
            color:#111;
        ">
            ADMIN PANEL
        </div>

    </div>

    <a
        href="logout.php"
        style="
            background:#222;
            color:#fff;
            padding:12px 22px;
            border-radius:50px;
            text-decoration:none;
            font-weight:700;
        "
    >
        ออกจากระบบ
    </a>

</header>


<!-- CONTENT -->

<section style="
    max-width:900px;
    margin:50px auto;
    padding:0 20px;
">

    <div style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:25px;
    ">

        <div>

            <h1 style="
                margin:0;
                color:#111;
                font-size:36px;
            ">
                เพิ่มสินค้า 🍰
            </h1>

            <p style="
                color:#75404d;
                margin-top:8px;
            ">
                เพิ่มสินค้าใหม่เข้าสู่เมนูของร้าน
            </p>

        </div>

        <a
            href="products.php"
            style="
                background:#222;
                color:#fff;
                padding:12px 22px;
                border-radius:50px;
                text-decoration:none;
                font-weight:700;
            "
        >
            ← กลับ
        </a>

    </div>


    <?php if ($error !== ''): ?>

        <div style="
            background:#ffdede;
            color:#b00020;
            padding:15px 20px;
            border-radius:15px;
            margin-bottom:20px;
            font-weight:700;
        ">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>


    <!-- FORM -->

    <div style="
        background:#ffffff;
        border-radius:25px;
        padding:35px;
        box-shadow:0 8px 30px rgba(193,18,63,0.10);
    ">

        <form method="POST">

            <!-- ชื่อสินค้า -->

            <div style="margin-bottom:22px;">

                <label style="
                    display:block;
                    font-weight:700;
                    margin-bottom:8px;
                ">
                    ชื่อสินค้า
                </label>

                <input
                    type="text"
                    name="product_name"
                    value="<?= htmlspecialchars($_POST['product_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="เช่น Blue flower cake"
                    required
                    style="
                        width:100%;
                        box-sizing:border-box;
                        padding:13px 16px;
                        border:1px solid #ddd;
                        border-radius:15px;
                        font-family:'Mali',cursive;
                        font-size:16px;
                    "
                >

            </div>


            <!-- รายละเอียด -->

            <div style="margin-bottom:22px;">

                <label style="
                    display:block;
                    font-weight:700;
                    margin-bottom:8px;
                ">
                    รายละเอียดสินค้า
                </label>

                <textarea
                    name="description"
                    rows="4"
                    placeholder="รายละเอียดของสินค้า"
                    style="
                        width:100%;
                        box-sizing:border-box;
                        padding:13px 16px;
                        border:1px solid #ddd;
                        border-radius:15px;
                        font-family:'Mali',cursive;
                        font-size:16px;
                        resize:vertical;
                    "
                ><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

            </div>


            <!-- ราคา + สต็อก -->

            <div style="
                display:flex;
                gap:20px;
                flex-wrap:wrap;
                margin-bottom:22px;
            ">

                <div style="
                    flex:1;
                    min-width:220px;
                ">

                    <label style="
                        display:block;
                        font-weight:700;
                        margin-bottom:8px;
                    ">
                        ราคา (บาท)
                    </label>

                    <input
                        type="number"
                        name="price"
                        step="0.01"
                        min="0"
                        value="<?= htmlspecialchars($_POST['price'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="420.00"
                        required
                        style="
                            width:100%;
                            box-sizing:border-box;
                            padding:13px 16px;
                            border:1px solid #ddd;
                            border-radius:15px;
                            font-family:'Mali',cursive;
                            font-size:16px;
                        "
                    >

                </div>


                <div style="
                    flex:1;
                    min-width:220px;
                ">

                    <label style="
                        display:block;
                        font-weight:700;
                        margin-bottom:8px;
                    ">
                        จำนวนสต็อก
                    </label>

                    <input
                        type="number"
                        name="stock"
                        min="0"
                        value="<?= htmlspecialchars($_POST['stock'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="10"
                        required
                        style="
                            width:100%;
                            box-sizing:border-box;
                            padding:13px 16px;
                            border:1px solid #ddd;
                            border-radius:15px;
                            font-family:'Mali',cursive;
                            font-size:16px;
                        "
                    >

                </div>

            </div>


            <!-- รูป -->

            <div style="margin-bottom:22px;">

                <label style="
                    display:block;
                    font-weight:700;
                    margin-bottom:8px;
                ">
                    ชื่อไฟล์รูปสินค้า
                </label>

                <input
                    type="text"
                    name="image"
                    value="<?= htmlspecialchars($_POST['image'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="เช่น blueflower.png"
                    style="
                        width:100%;
                        box-sizing:border-box;
                        padding:13px 16px;
                        border:1px solid #ddd;
                        border-radius:15px;
                        font-family:'Mali',cursive;
                        font-size:16px;
                    "
                >

                <div style="
                    color:#777;
                    font-size:13px;
                    margin-top:7px;
                ">
                    ใส่ชื่อไฟล์รูปที่อยู่ในโฟลเดอร์เว็บไซต์ เช่น blueflower.png
                </div>

            </div>


            <!-- สถานะ -->

            <div style="margin-bottom:30px;">

                <label style="
                    display:block;
                    font-weight:700;
                    margin-bottom:8px;
                ">
                    สถานะสินค้า
                </label>

                <select
                    name="status"
                    style="
                        width:100%;
                        padding:13px 16px;
                        border:1px solid #ddd;
                        border-radius:15px;
                        font-family:'Mali',cursive;
                        font-size:16px;
                        background:#fff;
                    "
                >

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


            <!-- BUTTON -->

            <div style="
                display:flex;
                gap:12px;
                justify-content:center;
                flex-wrap:wrap;
            ">

                <a
                    href="products.php"
                    style="
                        background:#ddd;
                        color:#333;
                        padding:13px 30px;
                        border-radius:50px;
                        text-decoration:none;
                        font-weight:700;
                    "
                >
                    ยกเลิก
                </a>

                <button
                    type="submit"
                    style="
                        background:#c1123f;
                        color:#fff;
                        border:none;
                        padding:13px 35px;
                        border-radius:50px;
                        font-family:'Mali',cursive;
                        font-size:16px;
                        font-weight:700;
                        cursor:pointer;
                    "
                >
                    + เพิ่มสินค้า
                </button>

            </div>

        </form>

    </div>

</section>

</body>
</html>

<?php
$conn->close();
?>