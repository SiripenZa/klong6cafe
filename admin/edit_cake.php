<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function productImage($image) {
    $image = trim((string)$image);

    if ($image === '') {
        return '../cake.png';
    }

    if (preg_match('/^https?:\/\//i', $image)) {
        return $image;
    }

    if (strpos($image, '/') === false && strpos($image, '\\') === false) {
        $image = 'image/' . $image;
    }

    if (strpos($image, '../') !== 0) {
        $image = '../' . ltrim($image, '/');
    }

    return $image;
}

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    header('Location: cake.php');
    exit;
}

$message = '';
$error = '';

/* =========================
   ดึงข้อมูลเค้ก
========================= */

$stmt = $conn->prepare(
    "SELECT
        cake_id,
        cake_name,
        description,
        price,
        stock,
        image,
        status
     FROM cake
     WHERE cake_id = ?
     LIMIT 1"
);

$stmt->bind_param("s", $id);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();

$stmt->close();

if (!$product) {
    die('ไม่พบเค้กนี้');
}

/* =========================
   UPDATE
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cake_name   = trim($_POST['cake_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $stock       = $_POST['stock'] ?? '';
    $image       = trim($_POST['image'] ?? '');
    $status      = $_POST['status'] ?? 'available';

    /* ตรวจสอบข้อมูล */

    if ($cake_name === '') {

        $error = 'กรุณากรอกชื่อเค้ก';

    } elseif (
        $price === '' ||
        !is_numeric($price) ||
        (float)$price < 0
    ) {

        $error = 'กรุณากรอกราคาให้ถูกต้อง';

    } elseif (
        $stock === '' ||
        !is_numeric($stock) ||
        (float)$stock < 0 ||
        (float)$stock != (int)$stock
    ) {

        $error = 'กรุณากรอกจำนวนสต็อกเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป';

    } elseif (
        !in_array(
            $status,
            ['available', 'unavailable'],
            true
        )
    ) {

        $error = 'สถานะสินค้าไม่ถูกต้อง';

    } else {

        $price = (float)$price;
        $stock = (int)$stock;

        $stmt = $conn->prepare(
            "UPDATE cake
             SET
                cake_name = ?,
                description = ?,
                price = ?,
                stock = ?,
                image = ?,
                status = ?
             WHERE cake_id = ?"
        );

        if (!$stmt) {

            $error = 'เตรียมคำสั่งแก้ไขเค้กไม่สำเร็จ: '
                   . $conn->error;

        } else {

            $stmt->bind_param(
                "ssdisss",
                $cake_name,
                $description,
                $price,
                $stock,
                $image,
                $status,
                $id
            );

            if ($stmt->execute()) {

                $message = 'แก้ไขเค้กเรียบร้อยแล้ว';

                /* อัปเดตข้อมูลที่แสดงในหน้า */

                $product['cake_name'] = $cake_name;
                $product['description'] = $description;
                $product['price'] = $price;
                $product['stock'] = $stock;
                $product['image'] = $image;
                $product['status'] = $status;

            } else {

                $error = 'ไม่สามารถแก้ไขเค้กได้: '
                       . $stmt->error;
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>แก้ไขเค้ก | KLONG 6 CAFE</title>

<link
    href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>

<style>

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;

    background:
        linear-gradient(
            180deg,
            #fff1f6 0%,
            #fff8fa 45%,
            #ffffff 100%
        );

    color: #632b3b;

    font-family: 'Mali', cursive;

    min-height: 100vh;
}

/* =========================
   HEADER
========================= */

header {

    position: relative;

    background:
        linear-gradient(
            120deg,
            rgba(255, 113, 164, 0.95),
            rgba(255, 183, 205, 0.95)
        );

    padding: 22px 5%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    flex-wrap: wrap;

    overflow: hidden;

    box-shadow:
        0 5px 25px rgba(193, 18, 63, .12);
}

header::before {

    content: "♡";

    position: absolute;

    font-size: 180px;

    color: rgba(255,255,255,.12);

    left: -20px;

    top: -90px;

    transform: rotate(-15deg);
}

header::after {

    content: "🍓";

    position: absolute;

    font-size: 45px;

    right: 7%;

    top: 15px;

    animation:
        floating 4s ease-in-out infinite;
}

.brand-area {

    position: relative;

    z-index: 2;
}

.brand {

    font-size: 30px;

    font-weight: 700;

    color: white;

    text-shadow:
        0 3px 10px rgba(150, 0, 50, .18);
}

.subtitle {

    color: #fff;

    margin-top: 4px;

    font-size: 14px;

    letter-spacing: 1px;
}

/* =========================
   LOGOUT
========================= */

.logout-btn {

    position: relative;

    z-index: 5;

    background:
        rgba(255,255,255,.16);

    border:
        1px solid rgba(255,255,255,.75);

    color: white;

    text-decoration: none;

    padding: 11px 22px;

    border-radius: 30px;

    font-weight: 700;

    transition: .3s ease;

    white-space: nowrap;
}

.logout-btn:hover {

    background: white;

    color: #c1123f;

    transform: translateY(-3px);

    box-shadow:
        0 7px 18px rgba(150,0,50,.18);
}

/* =========================
   DECORATIONS
========================= */

.decor {

    position: fixed;

    pointer-events: none;

    opacity: .3;

    z-index: 0;
}

.decor.one {

    left: -20px;

    top: 180px;

    font-size: 55px;

    animation:
        floating 5s ease-in-out infinite;
}

.decor.two {

    right: 30px;

    top: 430px;

    font-size: 45px;

    animation:
        floating 6s ease-in-out infinite reverse;
}

.decor.three {

    left: 7%;

    bottom: 40px;

    font-size: 35px;

    animation:
        floating 4.5s ease-in-out infinite;
}

/* =========================
   MAIN
========================= */

.container {

    position: relative;

    z-index: 1;

    max-width: 1000px;

    margin: 45px auto;

    padding:
        0 22px 50px;
}

.page-heading {

    text-align: center;

    animation:
        fadeUp .7s ease both;
}

.page-heading .small-title {

    color: #c1123f;

    font-size: 14px;

    letter-spacing: 4px;

    font-weight: 700;
}

.page-heading h1 {

    margin:
        8px 0 5px;

    color: #c1123f;

    font-size: 38px;
}

.page-heading p {

    color: #87616c;

    margin-top: 5px;
}

/* =========================
   TOOLBAR
========================= */

.toolbar {

    margin-top: 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    flex-wrap: wrap;

    background:
        rgba(255,255,255,.8);

    border:
        1px solid #f7d4df;

    border-radius: 25px;

    padding: 18px 20px;

    box-shadow:
        0 8px 30px rgba(193,18,63,.08);

    animation:
        fadeUp .8s ease both;
}

.toolbar h2 {

    margin: 0;

    color: #a31643;
}

.toolbar-actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

/* =========================
   BUTTON
========================= */

.btn {

    display: inline-block;

    border:
        1px solid transparent;

    border-radius: 30px;

    padding:
        11px 20px;

    text-decoration: none;

    font-family: inherit;

    font-weight: 700;

    cursor: pointer;

    text-align: center;

    transition: .3s ease;
}

.btn:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 7px 16px
        rgba(193,18,63,.18);
}

.btn-save {

    background: #f52b7c;

    color: white;
}

.btn-save:hover {

    background: #d91d67;
}

.btn-cancel {

    background: white;

    color: #8e1637;

    border-color: #f3c4d1;
}

.btn-cancel:hover {

    background: #c1123f;

    color: white;
}

/* =========================
   MESSAGE
========================= */

.error {

    margin:
        20px 0;

    padding:
        14px 18px;

    background:
        #ffe0e6;

    border-radius:
        15px;

    color:
        #991033;

    animation:
        fadeUp .5s ease both;
}

.success {

    margin:
        20px 0;

    padding:
        14px 18px;

    background:
        #dcf7e5;

    border-radius:
        15px;

    color:
        #18743a;

    animation:
        fadeUp .5s ease both;
}

/* =========================
   FORM CARD
========================= */

.form-card {

    margin-top: 25px;

    background:
        rgba(255,255,255,.92);

    border-radius:
        25px;

    padding:
        35px;

    box-shadow:
        0 8px 30px
        rgba(193,18,63,.08);

    border:
        1px solid #f7dce4;

    animation:
        fadeUp .9s ease both;
}

/* =========================
   FORM
========================= */

.form-group {

    margin-bottom:
        22px;
}

label {

    display:
        block;

    font-weight:
        700;

    margin-bottom:
        8px;

    color:
        #8e1637;
}

input,
textarea,
select {

    width:
        100%;

    box-sizing:
        border-box;

    padding:
        13px 16px;

    border:
        1px solid #ddd;

    border-radius:
        15px;

    font-family:
        'Mali', cursive;

    font-size:
        16px;

    background:
        #fff;

    color:
        #632b3b;

    outline:
        none;

    transition:
        .25s ease;
}

input:focus,
textarea:focus,
select:focus {

    border-color:
        #f52b7c;

    box-shadow:
        0 0 0 3px
        rgba(245,43,124,.10);
}

input[readonly] {

    background:
        #f3f3f3;

    color:
        #777;

    cursor:
        not-allowed;
}

textarea {

    resize:
        vertical;

    min-height:
        120px;
}

.help-text {

    color:
        #98717c;

    font-size:
        13px;

    margin-top:
        7px;
}

/* =========================
   IMAGE PREVIEW
========================= */

.image-preview {

    margin-top:
        12px;
}

.image-preview img {

    width:
        110px;

    height:
        110px;

    object-fit:
        cover;

    border-radius:
        18px;

    background:
        #fff0f3;

    border:
        3px solid #ffe1e9;

    display:
        block;
}

/* =========================
   FORM ROW
========================= */

.form-row {

    display:
        flex;

    gap:
        20px;

    flex-wrap:
        wrap;

    margin-bottom:
        22px;
}

.form-row .form-group {

    flex:
        1;

    min-width:
        220px;

    margin-bottom:
        0;
}

/* =========================
   FORM BUTTONS
========================= */

.form-buttons {

    display:
        flex;

    gap:
        12px;

    justify-content:
        center;

    flex-wrap:
        wrap;

    margin-top:
        30px;
}

.form-buttons .btn {

    min-width:
        150px;
}

/* =========================
   ANIMATION
========================= */

@keyframes fadeUp {

    from {

        opacity: 0;

        transform:
            translateY(25px);
    }

    to {

        opacity: 1;

        transform:
            translateY(0);
    }
}

@keyframes floating {

    0%,
    100% {

        transform:
            translateY(0);
    }

    50% {

        transform:
            translateY(-10px);
    }
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1000px) {

    header {

        justify-content:
            center;

        text-align:
            center;
    }

}

@media (max-width: 700px) {

    .page-heading h1 {

        font-size:
            30px;
    }

    .toolbar {

        align-items:
            stretch;
    }

    .toolbar-actions {

        width:
            100%;
    }

    .toolbar-actions .btn {

        flex:
            1;
    }

    .form-card {

        padding:
            25px;
    }
}

@media (max-width: 550px) {

    .container {

        padding:
            0 12px 40px;

        margin-top:
            30px;
    }

    .brand {

        font-size:
            24px;
    }

    .page-heading h1 {

        font-size:
            27px;
    }

    .toolbar {

        padding:
            15px;

        border-radius:
            20px;
    }

    .form-card {

        padding:
            20px;

        border-radius:
            20px;
    }

    .form-row {

        gap:
            0;
    }

    .form-row .form-group {

        min-width:
            100%;

        margin-bottom:
            22px;
    }

}

</style>

</head>

<body>

<!-- ของตกแต่ง -->

<div class="decor one">🍓</div>

<div class="decor two">🍰</div>

<div class="decor three">♡</div>


<!-- =========================
     HEADER
========================= -->

<header>

    <div class="brand-area">

        <div class="brand">
            🍒 Klong 6 Cafe
        </div>

        <div class="subtitle">
            ADMIN PANEL
        </div>

    </div>


    <a
        href="logout.php"
        class="logout-btn"
    >
        ออกจากระบบ
    </a>

</header>


<!-- =========================
     MAIN
========================= -->

<main class="container">


    <!-- PAGE HEADING -->

    <div class="page-heading">

        <div class="small-title">
            CAKE MANAGEMENT
        </div>

        <h1>
            แก้ไขเค้ก 🍰
        </h1>

        <p>
            แก้ไขข้อมูลเค้กที่มีอยู่ในระบบ
        </p>

    </div>


    <!-- TOOLBAR -->

    <div class="toolbar">

        <h2>
            ข้อมูลเค้ก
        </h2>

        <div class="toolbar-actions">

            <a
                href="cake.php"
                class="btn btn-cancel"
            >
                ← กลับรายการเค้ก
            </a>

        </div>

    </div>


    <!-- SUCCESS -->

    <?php if ($message !== ''): ?>

        <div class="success">

            🍰 <?= h($message) ?>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div class="error">

            <?= h($error) ?>

        </div>

    <?php endif; ?>


    <!-- FORM -->

    <div class="form-card">

        <form
            method="POST"
            action=""
        >


            <!-- รหัสเค้ก -->

            <div class="form-group">

                <label for="cake_id">
                    รหัสเค้ก (cake_id)
                </label>

                <input
                    type="text"
                    id="cake_id"
                    value="<?= h($product['cake_id']) ?>"
                    readonly
                >

                <div class="help-text">
                    รหัสเค้กไม่สามารถแก้ไขได้
                </div>

            </div>


            <!-- ชื่อเค้ก -->

            <div class="form-group">

                <label for="cake_name">
                    ชื่อเค้ก
                </label>

                <input
                    type="text"
                    id="cake_name"
                    name="cake_name"
                    value="<?= h($product['cake_name']) ?>"
                    placeholder="เช่น Blue flower cake"
                    required
                >

            </div>


            <!-- รายละเอียด -->

            <div class="form-group">

                <label for="description">
                    รายละเอียดสินค้า
                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    placeholder="รายละเอียดของเค้ก"
                ><?= h($product['description']) ?></textarea>

            </div>


            <!-- ราคาและสต็อก -->

            <div class="form-row">


                <div class="form-group">

                    <label for="price">
                        ราคา (บาท)
                    </label>

                    <input
                        type="number"
                        id="price"
                        name="price"
                        step="0.01"
                        min="0"
                        value="<?= h($product['price']) ?>"
                        placeholder="420.00"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="stock">
                        จำนวนสต็อก
                    </label>

                    <input
                        type="number"
                        id="stock"
                        name="stock"
                        min="0"
                        step="1"
                        value="<?= h($product['stock']) ?>"
                        placeholder="10"
                        required
                    >

                </div>


            </div>


            <!-- รูปภาพ -->

            <div class="form-group">

                <label for="image">
                    ชื่อไฟล์รูปสินค้า
                </label>

                <input
                    type="text"
                    id="image"
                    name="image"
                    value="<?= h($product['image']) ?>"
                    placeholder="เช่น blueflower.png"
                >

                <div class="help-text">
                    ใส่ชื่อไฟล์รูปหรือภาพที่ระบบของคุณใช้งานอยู่
                </div>

            </div>

            <!-- สถานะ -->

            <div class="form-group">

                <label for="status">
                    สถานะสินค้า
                </label>

                <select
                    id="status"
                    name="status"
                >

                    <option
                        value="available"
                        <?= $product['status'] === 'available'
                            ? 'selected'
                            : '' ?>
                    >
                        พร้อมขาย
                    </option>

                    <option
                        value="unavailable"
                        <?= $product['status'] === 'unavailable'
                            ? 'selected'
                            : '' ?>
                    >
                        ไม่พร้อมขาย
                    </option>

                </select>

            </div>


            <!-- BUTTONS -->

            <div class="form-buttons">

                <a
                    href="cake.php"
                    class="btn btn-cancel"
                >
                    ยกเลิก
                </a>

                <button
                    type="submit"
                    class="btn btn-save"
                >
                    💾 บันทึกการแก้ไข
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