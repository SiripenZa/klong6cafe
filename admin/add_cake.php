<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$error = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าจากฟอร์ม
    $cake_id     = trim($_POST['cake_id'] ?? '');
    $cake_name   = trim($_POST['cake_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $stock       = $_POST['stock'] ?? '';
    $image       = trim($_POST['image'] ?? '');
    $status      = $_POST['status'] ?? 'available';

    // ตรวจสอบข้อมูล
    if ($cake_id === '') {

        $error = 'กรุณากรอกรหัสเค้ก';

    } elseif ($cake_name === '') {

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

        // ตรวจสอบว่ารหัสเค้กซ้ำหรือไม่
        $check = $conn->prepare(
            "SELECT cake_id
             FROM cake
             WHERE cake_id = ?
             LIMIT 1"
        );

        if (!$check) {
            $error =
                'ตรวจสอบรหัสเค้กไม่สำเร็จ: '
                . $conn->error;
        } else {
            $check->bind_param(
                's',
                $cake_id
            );

            $check->execute();
            $checkResult = $check->get_result();
            $exists = $checkResult->num_rows > 0;
            $check->close();

            if ($exists) {
                $error =
                    'รหัสเค้กนี้มีอยู่แล้ว กรุณาใช้รหัสอื่น';
            } else {
                $price = (float)$price;
                $stock = (int)$stock;

                // เพิ่มข้อมูลลงตาราง cake
                $stmt = $conn->prepare(
                    "INSERT INTO cake
                    (
                        cake_id,
                        cake_name,
                        description,
                        price,
                        stock,
                        image,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $error =
                        'เตรียมคำสั่งเพิ่มเค้กไม่สำเร็จ: '
                        . $conn->error;
                } else {
                    $stmt->bind_param(
                        'sssdiss',
                        $cake_id,
                        $cake_name,
                        $description,
                        $price,
                        $stock,
                        $image,
                        $status
                    );
                    if ($stmt->execute()) {
                        $stmt->close();
                        header(
                            'Location: cake.php?success=added'
                        );
                        exit;
                    } else {
                        $error =
                            'เพิ่มเค้กไม่สำเร็จ: '
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
<meta
    name="viewport"
    content="width=device-width, initial-scale=1">
<title>
    เพิ่มเค้ก | KLONG 6 CAFE
</title>

<link
    href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>

<style>

/* =========================
   RESET
========================= */
* {
    box-sizing: border-box;
}
html {
    scroll-behavior: smooth;
}

/* =========================
   BODY
========================= */
body {
    margin: 0;

    background: linear-gradient(
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

/* =========================
   BRAND
========================= */
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
    background: rgba(255,255,255,.16);
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
    max-width: 900px;
    margin: 45px auto;
    padding: 0 22px 50px;
}

/* =========================
   PAGE HEADING
========================= */
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
    background: rgba(255,255,255,.8);
    border: 1px solid #f7d4df;
    border-radius: 25px;
    padding: 18px 20px;
    box-shadow: 0 8px 30px rgba(193,18,63,.08);
    animation: fadeUp .8s ease both;
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

    padding: 11px 20px;

    text-decoration: none;

    font-family: inherit;

    font-weight: 700;

    cursor: pointer;

    text-align: center;

    transition: .3s ease;
}


.btn:hover {
    transform: translateY(-3px);

    box-shadow:
        0 7px 16px rgba(193,18,63,.18);
}


/* =========================
   BUTTON ADD
========================= */

.btn-add {
    background: #f52b7c;

    color: white;
}


.btn-add:hover {
    background: #d91d67;
}


/* =========================
   BUTTON EDIT
========================= */

.btn-edit {
    background: #ffd1df;

    color: #8e1637;
}


.btn-edit:hover {
    background: #c1123f;

    color: white;
}


/* =========================
   BUTTON DELETE
========================= */

.btn-delete {
    background: #292126;

    color: white;
}


.btn-delete:hover {
    background: #555;
}


/* =========================
   BUTTON HOME
========================= */

.btn-home {
    background: white;

    color: #8e1637;

    border-color: #f3c4d1;
}


.btn-home:hover {
    background: #c1123f;

    color: white;
}


/* =========================
   MESSAGE
========================= */

.error {
    margin: 20px 0;

    padding: 14px 18px;

    background: #ffe0e6;

    border-radius: 15px;

    color: #991033;

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

    border-radius: 25px;

    padding: 35px;

    box-shadow:
        0 8px 30px rgba(193,18,63,.08);

    border:
        1px solid #f7dce4;

    animation:
        fadeUp .9s ease both;
}


/* =========================
   FORM GROUP
========================= */

.form-group {
    margin-bottom: 22px;
}


label {
    display: block;

    font-weight: 700;

    color: #a31643;

    margin-bottom: 8px;
}


/* =========================
   INPUT
========================= */

input,
textarea,
select {
    width: 100%;

    box-sizing: border-box;

    padding: 13px 16px;

    border:
        1px solid #f0ccd7;

    border-radius: 15px;

    font-family:
        'Mali', cursive;

    font-size: 16px;

    background: #fff;

    color: #632b3b;

    outline: none;

    transition:
        .3s ease;
}


input:focus,
textarea:focus,
select:focus {

    border-color:
        #f52b7c;

    box-shadow:
        0 0 0 4px
        rgba(245,43,124,.10);
}


textarea {
    resize: vertical;
}


/* =========================
   FORM ROW
========================= */

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


/* =========================
   HELP TEXT
========================= */

.help-text {
    color: #98717c;

    font-size: 13px;

    margin-top: 7px;
}


/* =========================
   FORM BUTTONS
========================= */

.form-buttons {
    display: flex;

    gap: 12px;

    justify-content: center;

    flex-wrap: wrap;

    margin-top: 30px;
}


/* =========================
   CANCEL
========================= */

.btn-cancel {
    background: white;

    color: #8e1637;

    border:
        1px solid #f3c4d1;
}


.btn-cancel:hover {
    background: #c1123f;

    color: white;
}


/* =========================
   SUBMIT
========================= */

.btn-submit {
    background: #f52b7c;

    color: white;

    padding:
        11px 25px;
}


.btn-submit:hover {
    background: #d91d67;
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
        justify-content: center;

        text-align: center;
    }

}


@media (max-width: 700px) {

    .page-heading h1 {
        font-size: 30px;
    }

    .toolbar {
        align-items: stretch;
    }

    .toolbar-actions {
        width: 100%;
    }

    .toolbar-actions .btn {
        flex: 1;
    }

}


@media (max-width: 550px) {

    .container {
        padding:
            0 12px 40px;

        margin-top: 30px;
    }

    .brand {
        font-size: 24px;
    }

    .page-heading h1 {
        font-size: 27px;
    }

    .toolbar {
        padding: 15px;

        border-radius: 20px;
    }

    .form-card {
        padding: 22px;

        border-radius: 20px;
    }

}

</style>

</head>


<body>


<!-- =========================
     DECORATIONS
========================= -->

<div class="decor one">
    🍓
</div>

<div class="decor two">
    🍰
</div>

<div class="decor three">
    ♡
</div>


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
            KLONG 6 CAFE
        </div>

        <h1>
            เพิ่มเค้ก 🍰
        </h1>

        <p>
            เพิ่มสินค้าใหม่เข้าสู่เมนูของร้าน
        </p>

    </div>


    <!-- TOOLBAR -->

    <div class="toolbar">

        <h2>
            🍰 จัดการเค้ก
        </h2>


        <div class="toolbar-actions">

            <a
                href="cake.php"
                class="btn btn-home"
            >
                ← กลับหน้าเค้ก
            </a>

        </div>

    </div>


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


            <!-- =====================
                 CAKE ID
            ====================== -->

            <div class="form-group">

                <label for="cake_id">
                    รหัสเค้ก (cake_id)
                </label>

                <input
                    type="text"
                    id="cake_id"
                    name="cake_id"
                    value="<?= h(
                        $_POST['cake_id'] ?? ''
                    ) ?>"
                    placeholder="เช่น C001"
                    maxlength="50"
                    required
                >

                <div class="help-text">
                    กรอกรหัสเค้กที่ไม่ซ้ำกับสินค้าเดิมในฐานข้อมูล
                </div>

            </div>


            <!-- =====================
                 CAKE NAME
            ====================== -->

            <div class="form-group">

                <label for="cake_name">
                    ชื่อเค้ก
                </label>

                <input
                    type="text"
                    id="cake_name"
                    name="cake_name"
                    value="<?= h(
                        $_POST['cake_name'] ?? ''
                    ) ?>"
                    placeholder="เช่น Blue flower cake"
                    required
                >

            </div>


            <!-- =====================
                 DESCRIPTION
            ====================== -->

            <div class="form-group">

                <label for="description">
                    รายละเอียดสินค้า
                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    placeholder="รายละเอียดของเค้ก"
                ><?= h(
                    $_POST['description'] ?? ''
                ) ?></textarea>

            </div>


            <!-- =====================
                 PRICE / STOCK
            ====================== -->

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
                        value="<?= h(
                            $_POST['price'] ?? ''
                        ) ?>"
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
                        value="<?= h(
                            $_POST['stock'] ?? ''
                        ) ?>"
                        placeholder="10"
                        required
                    >

                </div>


            </div>


            <!-- =====================
                 IMAGE
===================== -->

            <div class="form-group">

                <label for="image">
                    ชื่อไฟล์รูปสินค้า
                </label>

                <input
                    type="text"
                    id="image"
                    name="image"
                    value="<?= h(
                        $_POST['image'] ?? ''
                    ) ?>"
                    placeholder="เช่น blueflower.png"
                >

                <div class="help-text">
                    ใส่ชื่อไฟล์รูปหรือพาธรูปที่ระบบของคุณใช้งานอยู่
                </div>

            </div>


            <!-- =====================
                 STATUS
===================== -->

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
                        <?= (
                            ($_POST['status'] ?? 'available')
                            === 'available'
                        )
                        ? 'selected'
                        : '' ?>
                    >
                        พร้อมขาย
                    </option>


                    <option
                        value="unavailable"
                        <?= (
                            ($_POST['status'] ?? '')
                            === 'unavailable'
                        )
                        ? 'selected'
                        : '' ?>
                    >
                        ไม่พร้อมขาย
                    </option>

                </select>

            </div>


            <!-- =====================
                 BUTTONS
===================== -->

            <div class="form-buttons">

                <a
                    href="cake.php"
                    class="btn btn-cancel"
                >
                    ยกเลิก
                </a>


                <button
                    type="submit"
                    class="btn btn-submit"
                >
                    + เพิ่มเค้ก
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