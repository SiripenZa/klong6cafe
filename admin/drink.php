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
        return '../drink.png';
    }
    if (preg_match('/^https?:\/\//i', $image)) {
        return $image;
    }
    if (strpos($image, '/') === false &&
        strpos($image, '\\') === false) {
        $image = 'image/' . $image;
    }
    if (strpos($image, '../') !== 0) {
        $image = '../' . ltrim($image, '/');
    }
    return $image;
}

$pageTitle = 'จัดการเครื่องดื่ม';
$errorMessage = '';
$result = false;
$stmt = null;

/* =========================
   ดึงข้อมูลเครื่องดื่ม
========================= */

$sql = "SELECT
            drink_id,
            drink_name,
            description,
            price,
            stock,
            image,
            status
        FROM drink
        ORDER BY drink_id DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
    } else {
        $errorMessage = 'ไม่สามารถดึงข้อมูลเครื่องดื่มได้';
    }
} else {
    $errorMessage = 'ไม่สามารถเตรียมคำสั่ง SQL ได้';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport"content="width=device-width, initial-scale=1">

<title> <?= h($pageTitle) ?> | KLONG 6 CAFE</title>

<link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap" rel="stylesheet">

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
    background: linear-gradient(180deg,#fff1f6 0%,#fff8fa 45%,#ffffff 100%);
    color: #632b3b;
    font-family: 'Mali', cursive;
    min-height: 100vh;
}

/* =========================
   HEADER
========================= */

header {
    position: relative;
    background: linear-gradient(120deg,rgba(255, 113, 164, 0.95),rgba(255, 183, 205, 0.95));
    padding: 22px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    overflow: hidden;
    box-shadow:0 5px 25px rgba(193, 18, 63, .12);
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
    animation:floating 4s ease-in-out infinite;
}


.brand-area {
    position: relative;
    z-index: 2;
}


.brand {
    font-size: 30px;
    font-weight: 700;
    color: white;
    text-shadow:0 3px 10px rgba(150, 0, 50, .18);
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
    border: 1px solid rgba(255,255,255,.75);
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
    box-shadow: 0 7px 18px rgba(150,0,50,.18);
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
    animation: floating 5s ease-in-out infinite;
}


.decor.two {
    right: 30px;
    top: 430px;
    font-size: 45px;
    animation: floating 6s ease-in-out infinite reverse;
}


.decor.three {
    left: 7%;
    bottom: 40px;
    font-size: 35px;
    animation: floating 4.5s ease-in-out infinite;
}


/* =========================
   MAIN
========================= */

.container {
    position: relative;
    z-index: 1;
    max-width: 1250px;
    margin: 45px auto;
    padding: 0 22px 50px;
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
    font-size: 28px;
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
    border: 1px solid transparent;
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
    box-shadow: 0 7px 16px rgba(193,18,63,.18);
}


/* =========================
   ADD
========================= */

.btn-add {
    background: #f52b7c;
    color: white;
}


.btn-add:hover {
    background: #d91d67;
    color: white;
}


/* =========================
   EDIT
========================= */

.btn-edit {
    background: #ffd1df;
    color:#8e1637;
}


.btn-edit:hover {
    background:#c1123f;
    color: white;
}


/* =========================
   DELETE
========================= */

.btn-delete {
    background: #292126;
    color: white;
}


.btn-delete:hover {
    background: #555;
    color: white;
}


/* =========================
   HOME
========================= */

.btn-home {
    background: white;
    color: #8e1637;
    border-color: #f3c4d1;
}


.btn-home:hover {
    background:#c1123f;
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
    animation: fadeUp .5s ease both;
}

.success {
    margin: 20px 0;
    padding: 14px 18px;
    background: #dcf7e5;
    border-radius: 15px;
    color: #18743a;
    animation: fadeUp .5s ease both;
}


/* =========================
   TABLE
========================= */

.table-wrap {
    margin-top: 25px;
    background:rgba(255,255,255,.92);
    border-radius: 25px;
    overflow-x: auto;
    box-shadow: 0 8px 30px rgba(193,18,63,.08);
    border: 1px solid #f7dce4;
    animation: fadeUp .9s ease both;
}


table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}


th,td {
    padding: 16px 14px;
    border-bottom: 1px solid #f3dce3;
    text-align: left;
    vertical-align: middle;
}


th {
    background: #ffe1e9;
    color: #8e1637;
    font-weight: 700;
}


/* =========================
   TABLE ROW
========================= */

tbody tr {
    transition: .25s ease;
}

tbody tr:hover {
    background: #fff7fa;
    transform: scale(1.002);
}


/* =========================
   PRODUCT IMAGE
========================= */

.product-image {
    width: 82px;
    height: 82px;
    object-fit: cover;
    border-radius: 18px;
    background: #fff0f3;
    display: block;
    border: 3px solid #ffe1e9;
    transition: .35s ease;
}


.product-image:hover {
    transform: scale(1.08) rotate(1deg);
    box-shadow: 0 8px 18px rgba(193,18,63,.15);
}


/* =========================
   PRODUCT NAME
========================= */

.product-name {
    color: #a31643;
    font-weight: 700;
    font-size: 16px;
}

.product-description {
    color: #98717c;
    font-size: 12px;
    margin-top: 5px;
    max-width: 260px;
}


/* =========================
   PRICE
========================= */

.price {
    color: #c1123f;
    font-weight: 700;
    white-space: nowrap;
}


/* =========================
   STATUS
========================= */

.status {
    display: inline-block;
    padding: 7px 13px;
    border-radius: 25px;
    background: #dcf7e5;
    color: #18743a;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    transition: transform .25s ease;
}


.status:hover {
    transform: scale(1.05);
}


.status-unavailable {
    background: #eee;
    color: #666;
}


/* =========================
   ACTIONS
========================= */

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}


.actions form {
    margin: 0;
}

.actions .btn {
    font-size: 14px;
    padding: 10px 20px;
    border-radius: 25px;
}


/* =========================
   EMPTY
========================= */

.empty {
    text-align: center;
    padding: 60px 30px;
    color: #87616c;
}


.empty-icon {
    font-size: 60px;
    animation: floating 3s ease-in-out infinite;
}


.empty strong {
    display: block;
    margin-top: 10px;
    color: #a31643;
    font-size: 18px;
}


/* =========================
   ANIMATION
========================= */

@keyframes fadeUp {
    from {
        opacity:
            0;
        transform:
            translateY(25px);
    }
    to {
        opacity:
            1;
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
        padding: 0 12px 40px;
        margin-top: 30px;
    }

    .brand {
        font-size: 24px;
    }


    .toolbar {
        padding: 15px;

        border-radius: 20px;
    }

    .toolbar h2 {
        font-size: 24px;
    }

}

</style>
</head>

<body>
<!-- =========================
     DECORATIONS
========================= -->

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

    <a href="logout.php" class="logout-btn">ออกจากระบบ</a>

</header>


<!-- =========================
     MAIN
========================= -->

<main class="container">


    <!-- =========================
         TOOLBAR
    ========================= -->

    <div class="toolbar">

        <h2>
            จัดการเครื่องดื่ม 🥤
        </h2>

        <div class="toolbar-actions">
            <a href="dashboard.php" class="btn btn-home">
                ← กลับหน้าแอดมิน
            </a>
            <a href="add_drink.php" class="btn btn-add">
                + เพิ่มเครื่องดื่ม
            </a>
        </div>
    </div>


    <!-- =========================
         ERROR
    ========================= -->

    <?php if ($errorMessage !== ''): ?>
        <div class="error">
            <?= h($errorMessage) ?>
        </div>
    <?php endif; ?>


    <!-- =========================
         SUCCESS
    ========================= -->

    <?php if (
        isset($_GET['success']) &&
        $_GET['success'] === 'added'
    ): ?>

        <div class="success">
            เพิ่มเครื่องดื่มเรียบร้อยแล้ว
        </div>

    <?php endif; ?>


    <!-- =========================
         TABLE
    ========================= -->

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>
                        รหัสเครื่องดื่ม
                    </th>
                    <th>
                        รูปเครื่องดื่ม
                    </th>
                    <th>
                        ชื่อเครื่องดื่ม
                    </th>
                    <th>
                        ราคา
                    </th>
                    <th>
                        สต็อก
                    </th>
                    <th>
                        สถานะ
                    </th>
                    <th>
                        จัดการ
                    </th>
                </tr>
            </thead>
        <tbody>

            <?php if (
                $result &&
                $result->num_rows > 0
            ): ?>

                <?php while (
                    $product =
                    $result->fetch_assoc()
                ): ?>

                    <tr>
                        <!-- DRINK ID -->
                        <td>
                            <strong>
                                #<?= h(
                                    $product['drink_id']
                                ) ?>
                            </strong>
                        </td>

                        <!-- IMAGE -->
                        <td>
                            <img class="product-image" src="<?= h(productImage($product['image'] ?? '')) 
                            ?>"
                                alt="<?= h(
                                    $product['drink_name']
                                ) ?>"

                                onerror="
                                    this.onerror=null;
                                    this.src='../drink.png';">

                        </td>

                        <!-- NAME -->
                        <td>
                            <div class="product-name">
                                <?= h(
                                    $product['drink_name']
                                ) ?>
                            </div>

                            <?php if (
                                !empty(
                                    $product['description']
                                )
                            ): ?>
                                <div
                                    class="product-description">
                                    <?= h(
                                        $product['description']
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- PRICE -->
                        <td>
                            <span class="price">
                                <?= number_format(
                                    (float)$product['price'],
                                    2
                                ) ?>
                                บาท
                            </span>
                        </td>

                        <!-- STOCK -->
                        <td>
                            <?= (int)$product['stock'] ?>
                            ชิ้น
                        </td>

                        <!-- STATUS -->
                        <td>
                            <?php if (
                                $product['status']
                                === 'available'
                            ): ?>
                                <span class="status">
                                    พร้อมขาย
                                </span>

                            <?php else: ?>
                                <span
                                    class="status status-unavailable">
                                    ไม่พร้อมขาย
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- ACTIONS -->
                        <td>
                            <div class="actions">

                                <!-- EDIT -->
                                <a class="btn btn-edit" href="edit_drink.php?id=<?= urlencode($product['drink_id']) ?>">
                                    แก้ไข
                                </a>

                                <!-- DELETE -->
                                <form
                                    action="delete_drink.php"
                                    method="POST"
                                    onsubmit="return confirm('ยืนยันการลบเครื่องดื่มนี้หรือไม่?');">

                                    <input
                                        type="hidden"
                                        name="drink_id"
                                        value="<?= h($product['drink_id']) ?>">
                                    <button
                                        type="submit"
                                        class="btn btn-delete">
                                        ลบ
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>

                <?php endwhile; ?>
                    <?php elseif (
                        $errorMessage === ''): ?>
                <tr>
                    <td colspan="7"class="empty">
                        <div class="empty-icon">
                            🥤
                        </div>

                        <strong>ยังไม่มีสินค้าในหมวดเครื่องดื่ม</strong>

                        <p>ยังไม่พบข้อมูลในตาราง drink</p>
                    </td>
                </tr>
                    <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>

<?php
if ($stmt) {
    $stmt->close();
}
$conn->close();
?>