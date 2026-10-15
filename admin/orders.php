<?php
session_start();
require_once "../conn.php";

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin_login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$orderStatuses = [
    "pending"   => "รอดำเนินการ",
    "confirmed" => "ยืนยันคำสั่งซื้อแล้ว",
    "preparing" => "กำลังเตรียมสินค้า",
    "ready"     => "พร้อมรับสินค้า",
    "completed" => "รับสินค้าเรียบร้อย",
    "cancelled" => "ยกเลิกคำสั่งซื้อ"
];

$paymentStatuses = [
    "pending"        => "รอชำระเงิน",
    "pending_review" => "รอตรวจสอบสลิป",
    "verified"       => "ตรวจสอบสลิปแล้ว",
    "paid"           => "ชำระเงินครบแล้ว",
    "rejected"       => "สลิปไม่ผ่าน"
];

$message = "";
$error = "";

/* =====================================================
   บันทึกสถานะออเดอร์และการชำระเงิน
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $orderId = trim($_POST["order_id"] ?? "");
    $action = $_POST["action"] ?? "";

    try {

        if ($orderId === "") {
            throw new Exception("ไม่พบเลขที่คำสั่งซื้อ");
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare(
            "SELECT order_id, status, payment_status
             FROM orders
             WHERE order_id = ?
             FOR UPDATE"
        );

        $stmt->bind_param("s", $orderId);
        $stmt->execute();

        $currentOrder = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$currentOrder) {
            throw new Exception("ไม่พบคำสั่งซื้อที่ต้องการแก้ไข");
        }

        /* =========================
           UPDATE STATUS
        ========================= */

        if ($action === "update_status") {

            $status = $_POST["status"] ?? "";
            $paymentStatus = $_POST["payment_status"] ?? "";

            if (!array_key_exists($status, $orderStatuses)) {
                throw new Exception("สถานะคำสั่งซื้อไม่ถูกต้อง");
            }

            if (!array_key_exists($paymentStatus, $paymentStatuses)) {
                throw new Exception("สถานะการชำระเงินไม่ถูกต้อง");
            }

            $stmt = $conn->prepare(
                "UPDATE orders
                 SET status = ?, payment_status = ?
                 WHERE order_id = ?"
            );

            $stmt->bind_param(
                "sss",
                $status,
                $paymentStatus,
                $orderId
            );

            $stmt->execute();
            $stmt->close();

        /* =========================
           APPROVE SLIP
        ========================= */

        } elseif ($action === "approve_slip") {

            if (empty($_POST["confirm_action"])) {
                throw new Exception("กรุณายืนยันการตรวจสอบสลิป");
            }

            $stmt = $conn->prepare(
                "UPDATE orders
                 SET payment_status = 'verified',
                     status = CASE
                         WHEN status = 'pending' THEN 'confirmed'
                         ELSE status
                     END
                 WHERE order_id = ?
                   AND slip_path IS NOT NULL
                   AND slip_path <> ''
                   AND payment_status IN ('pending_review', 'rejected')"
            );

            $stmt->bind_param("s", $orderId);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {

                $stmt->close();

                throw new Exception(
                    "ไม่สามารถอนุมัติสลิปได้ กรุณาตรวจสอบสถานะปัจจุบัน"
                );
            }

            $stmt->close();

        /* =========================
           REJECT SLIP
        ========================= */

        } elseif ($action === "reject_slip") {

            if (empty($_POST["confirm_action"])) {
                throw new Exception("กรุณายืนยันการปฏิเสธสลิป");
            }

            $stmt = $conn->prepare(
                "UPDATE orders
                 SET payment_status = 'rejected'
                 WHERE order_id = ?
                   AND slip_path IS NOT NULL
                   AND slip_path <> ''
                   AND payment_status IN ('pending_review', 'verified')"
            );

            $stmt->bind_param("s", $orderId);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {

                $stmt->close();

                throw new Exception(
                    "ไม่สามารถปฏิเสธสลิปได้ กรุณาตรวจสอบสถานะปัจจุบัน"
                );
            }

            $stmt->close();

        } else {

            throw new Exception("ไม่พบคำสั่งที่ต้องการดำเนินการ");
        }

        $conn->commit();

        header("Location: orders.php?updated=1");
        exit;

    } catch (Throwable $e) {

        $conn->rollback();
        $error = $e->getMessage();
    }
}


/* =====================================================
   ตัวกรองและค้นหา
===================================================== */

$search = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$paymentFilter = $_GET["payment_status"] ?? "";

if (!array_key_exists($statusFilter, $orderStatuses)) {
    $statusFilter = "";
}

if (!array_key_exists($paymentFilter, $paymentStatuses)) {
    $paymentFilter = "";
}


/* =====================================================
   Dashboard สรุปข้อมูล
===================================================== */

$summary = [
    "all" => 0,
    "pending" => 0,
    "slips" => 0,
    "ready" => 0
];

$resultSummary = $conn->query(
    "SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(status = 'pending'), 0) AS pending_orders,
        COALESCE(SUM(payment_status = 'pending_review'), 0) AS pending_slips,
        COALESCE(SUM(status = 'ready'), 0) AS ready_orders
     FROM orders"
);

$summaryRow = $resultSummary->fetch_assoc();

$summary["all"] = (int)$summaryRow["total_orders"];
$summary["pending"] = (int)$summaryRow["pending_orders"];
$summary["slips"] = (int)$summaryRow["pending_slips"];
$summary["ready"] = (int)$summaryRow["ready_orders"];


/* =====================================================
   ดึงรายการออเดอร์และข้อมูลลูกค้า
===================================================== */

$sql = "
    SELECT
        o.order_id,
        c.customer_id AS customer_id,
        o.order_date,
        o.pickup_date,
        o.pickup_time,
        o.total_amount,
        o.deposit_amount,
        o.balance_amount,
        o.slip_path,
        o.payment_status,
        o.status,
        o.note,
        c.firstname,
        c.lastname,
        c.email
    FROM orders o
    LEFT JOIN customers c
        ON o.customer_id = c.customer_id
    WHERE (
        ? = ''
        OR o.order_id LIKE ?
        OR c.firstname LIKE ?
        OR c.lastname LIKE ?
        OR c.email LIKE ?
    )
    AND (? = '' OR o.status = ?)
    AND (? = '' OR o.payment_status = ?)
    ORDER BY o.order_date DESC
";

$keyword = "%" . $search . "%";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssssssss",
    $search,
    $keyword,
    $keyword,
    $keyword,
    $keyword,
    $statusFilter,
    $statusFilter,
    $paymentFilter,
    $paymentFilter
);

$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();


/* =====================================================
   ดึงรายการสินค้าแต่ละออเดอร์
===================================================== */

$orderItems = [];

if (!empty($orders)) {

    $orderIds = [];

    foreach ($orders as $order) {
        $orderIds[] = (string)$order["order_id"];
    }

    $orderIds = array_values(array_unique($orderIds));

    if (!empty($orderIds)) {

        $placeholders = implode(
            ",",
            array_fill(0, count($orderIds), "?")
        );

        $types = str_repeat("s", count($orderIds));

        $itemSql = "
            SELECT
                oi.order_id,
                oi.cake_id,
                oi.drink_id,
                oi.quantity,
                oi.unit_price,
                oi.subtotal,
                c.cake_name,
                d.drink_name
            FROM order_items oi
            LEFT JOIN cake c
                ON oi.cake_id = c.cake_id
            LEFT JOIN drink d
                ON oi.drink_id = d.drink_id
            WHERE oi.order_id IN ($placeholders)
            ORDER BY oi.order_id, oi.order_item_id
        ";

        $itemStmt = $conn->prepare($itemSql);

        $itemStmt->bind_param($types, ...$orderIds);

        $itemStmt->execute();

        $itemsResult = $itemStmt->get_result();

        while ($item = $itemsResult->fetch_assoc()) {

            $id = (string)$item["order_id"];

            if (!isset($orderItems[$id])) {
                $orderItems[$id] = [];
            }

            if (!empty($item["cake_name"])) {

                $item["product_name"] = $item["cake_name"];

            } elseif (!empty($item["drink_name"])) {

                $item["product_name"] = $item["drink_name"];

            } else {

                $item["product_name"] = "สินค้าไม่พบชื่อ";
            }

            $orderItems[$id][] = $item;
        }

        $itemStmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>จัดการคำสั่งซื้อ | KLONG 6 CAFE</title>

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
   เหมือน cake.php
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

    animation: floating 4s ease-in-out infinite;
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

    color: white;

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

    max-width: 1500px;

    margin: 45px auto;

    padding: 0 22px 50px;
}


/* =========================
   TOOLBAR
========================= */

.toolbar {

    margin-top: 10px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    flex-wrap: wrap;

    background: rgba(255,255,255,.8);

    border: 1px solid #f7d4df;

    border-radius: 25px;

    padding: 18px 20px;

    box-shadow:
        0 8px 30px rgba(193,18,63,.08);

    animation:
        fadeUp .7s ease both;
}

.toolbar h2 {

    margin: 0;

    color: #a31643;

    font-size: 25px;
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

    box-shadow:
        0 7px 16px rgba(193,18,63,.18);
}


/* กลับหน้าแอดมิน */

.btn-home {

    background: white;

    color: #8e1637;

    border-color: #f3c4d1;
}

.btn-home:hover {

    background: #c1123f;

    color: white;
}


/* ปุ่มออกจากระบบ */

.btn-dark {

    background: #292126;

    color: white;
}

.btn-dark:hover {

    background: #555;
}


/* ปุ่มค้นหา */

.btn-pink {

    background: #f52b7c;

    color: white;
}

.btn-pink:hover {

    background: #d91d67;
}


/* ปุ่มล้างค่า */

.btn-light {

    background: #ffe1e9;

    color: #8f0d2d;
}

.btn-light:hover {

    background: #c1123f;

    color: white;
}


/* อนุมัติ */

.btn-green {

    background: #16834a;

    color: white;
}

.btn-green:hover {

    background: #116c3c;
}


/* ปฏิเสธ */

.btn-red {

    background: #b4233d;

    color: white;
}

.btn-red:hover {

    background: #8f1c31;
}


/* =========================
   PAGE DESCRIPTION
========================= */

.page-description {

    text-align: center;

    color: #87616c;

    margin: 18px 0 5px;

    animation:
        fadeUp .8s ease both;
}


/* =========================
   NOTICE
========================= */

.notice {

    padding: 14px 18px;

    border-radius: 15px;

    margin: 20px 0;

    animation:
        fadeUp .5s ease both;
}

.success {

    background: #dcf7e5;

    color: #18743a;
}

.error {

    background: #ffe0e6;

    color: #991033;
}


/* =========================
   SUMMARY
========================= */

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 20px;

    margin: 30px 0;

    animation:
        fadeUp .8s ease both;
}

.summary-card {

    background: white;

    padding: 25px;

    border-radius: 22px;

    box-shadow:
        0 5px 20px rgba(0,0,0,.07);

    border: 2px solid transparent;

    transition: .25s ease;
}

.summary-card:hover {

    transform: translateY(-5px);

    border-color: #ffb6c9;

    box-shadow:
        0 12px 25px rgba(193,18,63,.12);
}

.summary-card .icon {

    font-size: 38px;

    margin-bottom: 8px;
}

.summary-card .label {

    color: #87616c;

    margin: 5px 0;
}

.summary-card .number {

    font-size: 32px;

    font-weight: 700;

    color: #a31643;
}


/* =========================
   PANEL
========================= */

.panel {

    background: rgba(255,255,255,.92);

    border-radius: 25px;

    padding: 25px;

    margin: 25px 0;

    box-shadow:
        0 8px 30px rgba(193,18,63,.08);

    border: 1px solid #f7dce4;

    animation:
        fadeUp .9s ease both;
}

.panel h2 {

    margin-top: 0;

    color: #a31643;
}


/* =========================
   FILTERS
========================= */

.filters {

    display: grid;

    grid-template-columns:
        minmax(200px, 2fr)
        1fr
        1fr
        auto;

    gap: 12px;

    align-items: end;
}

label {

    display: block;

    margin-bottom: 6px;

    font-size: 13px;

    font-weight: 700;
}

input,
select {

    width: 100%;

    padding: 11px;

    border: 1px solid #e6bdcb;

    border-radius: 12px;

    font: inherit;

    background: white;

    color: #632b3b;

    outline: none;

    transition: .2s;
}

input:focus,
select:focus {

    border-color: #f52b7c;

    box-shadow:
        0 0 0 3px rgba(245,43,124,.10);
}


/* =========================
   TABLE
========================= */

.table-wrap {

    overflow-x: auto;

    border-radius: 18px;

    border: 1px solid #f7dce4;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1100px;
}

th,
td {

    padding: 15px 14px;

    border-bottom:
        1px solid #f1dce3;

    text-align: left;

    vertical-align: top;
}

th {

    background: #ffe1e9;

    color: #8e1637;

    font-weight: 700;

    white-space: nowrap;
}

tbody tr {

    transition: .25s ease;
}

tbody tr:hover {

    background: #fff7fa;
}


/* =========================
   ORDER
========================= */

.order-id {

    font-weight: 700;

    color: #a31643;

    overflow-wrap: anywhere;

    min-width: 125px;
}

.amount {

    white-space: nowrap;

    font-weight: 700;

    color: #c1123f;
}

.muted {

    color: #87616c;

    font-size: 13px;
}

.badge {

    display: inline-block;

    background: #fff0f5;

    color: #8e1637;

    border-radius: 20px;

    padding: 6px 11px;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;
}

.items-list {

    min-width: 190px;

    line-height: 1.9;
}


/* =========================
   SLIP
========================= */

.slip-preview {

    display: block;

    max-width: 140px;

    max-height: 150px;

    object-fit: contain;

    border-radius: 12px;

    margin-bottom: 8px;

    border: 3px solid #ffe1e9;

    transition: .3s;
}

.slip-preview:hover {

    transform: scale(1.04);

    box-shadow:
        0 7px 18px rgba(193,18,63,.12);
}


/* =========================
   REVIEW
========================= */

.status-form {

    min-width: 205px;
}

.status-form label {

    margin-top: 10px;
}

.status-form .btn {

    width: 100%;

    margin-top: 10px;
}

.review-actions {

    display: flex;

    flex-direction: column;

    gap: 7px;

    margin-top: 10px;
}

.review-actions form {

    margin: 0;
}


/* =========================
   EMPTY
========================= */

.empty {

    text-align: center;

    padding: 55px 30px;

    color: #87616c;
}

.empty-icon {

    font-size: 55px;

    animation:
        floating 3s ease-in-out infinite;
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

    .summary-grid {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .filters {

        grid-template-columns:
            1fr 1fr;
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

        padding:
            0 12px 40px;

        margin-top: 30px;
    }

    header {

        padding: 20px;
    }

    .brand {

        font-size: 24px;
    }

    .summary-grid {

        grid-template-columns: 1fr;

        gap: 12px;
    }

    .summary-card {

        padding: 18px;
    }

    .summary-card .number {

        font-size: 27px;
    }

    .filters {

        grid-template-columns: 1fr;
    }

    .panel {

        padding: 18px;

        border-radius: 20px;
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


    <!-- TOOLBAR -->

    <div class="toolbar">

        <h2>
            📦 จัดการคำสั่งซื้อ
        </h2>

        <div class="toolbar-actions">

            <a
                href="dashboard.php"
                class="btn btn-home"
            >
                ← กลับหน้าแอดมิน
            </a>

        </div>

    </div>


    <p class="page-description">
        ตรวจสอบรายการสินค้า นัดรับสินค้า และจัดการสถานะการชำระเงิน
    </p>


    <!-- SUCCESS -->

    <?php if (isset($_GET["updated"])): ?>

        <div class="notice success">

            ✓ บันทึกการเปลี่ยนแปลงเรียบร้อยแล้ว

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($error !== ""): ?>

        <div class="notice error">

            <?= h($error) ?>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET["error"])): ?>

        <div class="notice error">

            ไม่สามารถดำเนินการได้
            กรุณาตรวจสอบข้อมูลอีกครั้ง

        </div>

    <?php endif; ?>


    <!-- =========================
         SUMMARY
    ========================= -->

    <section class="summary-grid">


        <div class="summary-card">

            <div class="icon">
                🧾
            </div>

            <div class="label">
                ออเดอร์ทั้งหมด
            </div>

            <div class="number">
                <?= $summary["all"] ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="icon">
                ⏳
            </div>

            <div class="label">
                รอดำเนินการ
            </div>

            <div class="number">
                <?= $summary["pending"] ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="icon">
                💳
            </div>

            <div class="label">
                สลิปรอตรวจสอบ
            </div>

            <div class="number">
                <?= $summary["slips"] ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="icon">
                🎁
            </div>

            <div class="label">
                พร้อมรับสินค้า
            </div>

            <div class="number">
                <?= $summary["ready"] ?>
            </div>

        </div>


    </section>


    <!-- =========================
         SEARCH / FILTER
    ========================= -->

    <section class="panel">

        <h2>
            ค้นหาและกรองออเดอร์ 🔎
        </h2>


        <form
            method="GET"
            class="filters"
        >


            <div>

                <label for="search">
                    เลขออเดอร์ / ชื่อลูกค้า / อีเมล
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    placeholder="พิมพ์คำค้นหา..."
                    value="<?= h($search) ?>"
                >

            </div>


            <div>

                <label for="status">
                    สถานะออเดอร์
                </label>

                <select
                    id="status"
                    name="status"
                >

                    <option value="">
                        ทุกสถานะ
                    </option>

                    <?php foreach (
                        $orderStatuses
                        as $value => $label
                    ): ?>

                        <option
                            value="<?= h($value) ?>"
                            <?= $statusFilter === $value
                                ? "selected"
                                : "" ?>
                        >
                            <?= h($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div>

                <label for="payment_status">
                    สถานะการชำระเงิน
                </label>

                <select
                    id="payment_status"
                    name="payment_status"
                >

                    <option value="">
                        ทุกสถานะ
                    </option>

                    <?php foreach (
                        $paymentStatuses
                        as $value => $label
                    ): ?>

                        <option
                            value="<?= h($value) ?>"
                            <?= $paymentFilter === $value
                                ? "selected"
                                : "" ?>
                        >
                            <?= h($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div>

                <button
                    type="submit"
                    class="btn btn-pink"
                >
                    ค้นหา
                </button>

                <a
                    href="orders.php"
                    class="btn btn-light"
                >
                    ล้างค่า
                </a>

            </div>


        </form>

    </section>


    <!-- =========================
         ORDER LIST
    ========================= -->

    <section class="panel">

        <h2>
            รายการคำสั่งซื้อทั้งหมด
        </h2>

        <p class="muted">

            พบ <?= count($orders) ?>
            รายการตามเงื่อนไขที่เลือก

        </p>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            เลขออเดอร์ / ลูกค้า
                        </th>

                        <th>
                            วันสั่งซื้อ
                        </th>

                        <th>
                            รายการสินค้า
                        </th>

                        <th>
                            ข้อมูลรับสินค้า
                        </th>

                        <th>
                            ยอดรวม / มัดจำ / คงเหลือ
                        </th>

                        <th>
                            สลิปและการชำระเงิน
                        </th>

                        <th>
                            จัดการสถานะ
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (count($orders) > 0): ?>


                    <?php foreach ($orders as $order): ?>

                        <?php

                        $id = (string)$order["order_id"];

                        $slip = trim(
                            (string)(
                                $order["slip_path"]
                                ?? ""
                            )
                        );

                        $customerName = trim(
                            ($order["firstname"] ?? "")
                            . " "
                            .
                            ($order["lastname"] ?? "")
                        );

                        ?>


                        <tr>


                            <!-- ORDER / CUSTOMER -->

                            <td>

                                <div class="order-id">
                                    <?= h($id) ?>
                                </div>


                                <div
                                    style="
                                        margin-top:8px;
                                        font-weight:700;
                                    "
                                >

                                    <?= h(
                                        $customerName
                                        ?: "ลูกค้า #"
                                        . $order["customer_id"]
                                    ) ?>

                                </div>


                                <div class="muted">

                                    <?= h(
                                        $order["email"]
                                        ?? "ไม่มีอีเมล"
                                    ) ?>

                                </div>


                                <div class="muted">

                                    รหัสลูกค้า:
                                    <?= h(
                                        $order["customer_id"]
                                    ) ?>

                                </div>

                            </td>


                            <!-- ORDER DATE -->

                            <td>

                                <?= h(
                                    $order["order_date"]
                                ) ?>

                            </td>


                            <!-- ITEMS -->

                            <td>

                                <div class="items-list">

                                    <?php
                                    $items =
                                        $orderItems[$id]
                                        ?? [];
                                    ?>


                                    <?php if (
                                        count($items) > 0
                                    ): ?>


                                        <?php foreach (
                                            $items
                                            as $item
                                        ): ?>

                                            <div>

                                                <strong>

                                                    <?= h(
                                                        $item[
                                                            "product_name"
                                                        ]
                                                    ) ?>

                                                </strong>

                                                <br>

                                                <span class="muted">

                                                    <?= (int)
                                                        $item[
                                                            "quantity"
                                                        ] ?>

                                                    ชิ้น ×

                                                    <?= number_format(
                                                        (float)$item[
                                                            "unit_price"
                                                        ],
                                                        2
                                                    ) ?>

                                                    บาท

                                                </span>

                                                <br>

                                                <span>

                                                    รวม

                                                    <?= number_format(
                                                        (float)$item[
                                                            "subtotal"
                                                        ],
                                                        2
                                                    ) ?>

                                                    บาท

                                                </span>

                                            </div>

                                        <?php endforeach; ?>


                                    <?php else: ?>

                                        <span class="muted">
                                            ไม่พบรายการสินค้า
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $order["note"]
                                    )
                                ): ?>

                                    <div
                                        style="
                                            margin-top:10px;
                                        "
                                    >

                                        <strong>
                                            หมายเหตุ:
                                        </strong>

                                        <br>

                                        <?= nl2br(
                                            h(
                                                $order["note"]
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                            </td>


                            <!-- PICKUP -->

                            <td>

                                <div>

                                    <strong>
                                        วันที่รับ
                                    </strong>

                                    <br>

                                    <?= h(
                                        $order["pickup_date"]
                                    ) ?>

                                </div>


                                <div
                                    style="
                                        margin-top:8px;
                                    "
                                >

                                    <strong>
                                        เวลา
                                    </strong>

                                    <br>

                                    <?= h(
                                        $order["pickup_time"]
                                    ) ?>

                                </div>


                                <div
                                    style="
                                        margin-top:8px;
                                    "
                                >

                                    <span class="badge">

                                        <?= h(
                                            $orderStatuses[
                                                $order["status"]
                                            ]
                                            ??
                                            $order["status"]
                                        ) ?>

                                    </span>

                                </div>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <div class="amount">

                                    <?= number_format(
                                        (float)
                                        $order[
                                            "total_amount"
                                        ],
                                        2
                                    ) ?>

                                    บาท

                                </div>


                                <div
                                    class="muted"
                                    style="
                                        margin-top:8px;
                                    "
                                >

                                    มัดจำ:

                                    <?= number_format(
                                        (float)(
                                            $order[
                                                "deposit_amount"
                                            ]
                                            ?? 0
                                        ),
                                        2
                                    ) ?>

                                    บาท

                                </div>


                                <div
                                    class="muted"
                                    style="
                                        margin-top:5px;
                                    "
                                >

                                    คงเหลือ:

                                    <?= number_format(
                                        (float)(
                                            $order[
                                                "balance_amount"
                                            ]
                                            ?? 0
                                        ),
                                        2
                                    ) ?>

                                    บาท

                                </div>

                            </td>


                            <!-- SLIP -->

                            <td>


                                <?php if ($slip !== ""): ?>


                                    <?php

                                    $slipUrl =
                                        "../"
                                        .
                                        ltrim(
                                            $slip,
                                            "/"
                                        );

                                    $extension =
                                        strtolower(
                                            pathinfo(
                                                $slip,
                                                PATHINFO_EXTENSION
                                            )
                                        );

                                    ?>


                                    <?php if (
                                        in_array(
                                            $extension,
                                            [
                                                "jpg",
                                                "jpeg",
                                                "png",
                                                "webp"
                                            ],
                                            true
                                        )
                                    ): ?>

                                        <a
                                            href="<?= h(
                                                $slipUrl
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >

                                            <img
                                                class="slip-preview"
                                                src="<?= h(
                                                    $slipUrl
                                                ) ?>"
                                                alt="สลิปออเดอร์ <?= h(
                                                    $id
                                                ) ?>"
                                            >

                                        </a>

                                    <?php endif; ?>


                                    <a
                                        href="<?= h(
                                            $slipUrl
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="muted"
                                    >
                                        เปิดดูสลิป
                                    </a>


                                    <div
                                        class="review-actions"
                                    >


                                        <?php if (
                                            in_array(
                                                $order[
                                                    "payment_status"
                                                ],
                                                [
                                                    "pending_review",
                                                    "rejected"
                                                ],
                                                true
                                            )
                                        ): ?>


                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'ยืนยันการอนุมัติสลิปนี้หรือไม่?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="order_id"
                                                    value="<?= h(
                                                        $id
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="approve_slip"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="confirm_action"
                                                    value="1"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-green"
                                                >
                                                    ✓ อนุมัติสลิป
                                                </button>

                                            </form>


                                        <?php endif; ?>


                                        <?php if (
                                            in_array(
                                                $order[
                                                    "payment_status"
                                                ],
                                                [
                                                    "pending_review",
                                                    "verified"
                                                ],
                                                true
                                            )
                                        ): ?>


                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'ยืนยันการปฏิเสธสลิปนี้หรือไม่?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="order_id"
                                                    value="<?= h(
                                                        $id
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="reject_slip"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="confirm_action"
                                                    value="1"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-red"
                                                >
                                                    ✕ ปฏิเสธสลิป
                                                </button>

                                            </form>


                                        <?php endif; ?>


                                    </div>


                                <?php else: ?>

                                    <span class="muted">
                                        ยังไม่มีสลิป
                                    </span>

                                <?php endif; ?>


                                <div
                                    style="
                                        margin-top:10px;
                                    "
                                >

                                    <span class="badge">

                                        <?= h(
                                            $paymentStatuses[
                                                $order[
                                                    "payment_status"
                                                ]
                                            ]
                                            ??
                                            $order[
                                                "payment_status"
                                            ]
                                        ) ?>

                                    </span>

                                </div>


                            </td>


                            <!-- STATUS MANAGEMENT -->

                            <td>

                                <form
                                    method="POST"
                                    class="status-form"
                                >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= h(
                                            $id
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update_status"
                                    >


                                    <label
                                        for="status-<?= h(
                                            $id
                                        ) ?>"
                                    >
                                        สถานะออเดอร์
                                    </label>


                                    <select
                                        id="status-<?= h(
                                            $id
                                        ) ?>"
                                        name="status"
                                        required
                                    >

                                        <?php foreach (
                                            $orderStatuses
                                            as $value => $label
                                        ): ?>

                                            <option
                                                value="<?= h(
                                                    $value
                                                ) ?>"
                                                <?= $order[
                                                    "status"
                                                ] === $value
                                                    ? "selected"
                                                    : "" ?>
                                            >

                                                <?= h(
                                                    $label
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>


                                    <label
                                        for="payment-<?= h(
                                            $id
                                        ) ?>"
                                    >
                                        สถานะชำระเงิน
                                    </label>


                                    <select
                                        id="payment-<?= h(
                                            $id
                                        ) ?>"
                                        name="payment_status"
                                        required
                                    >

                                        <?php foreach (
                                            $paymentStatuses
                                            as $value => $label
                                        ): ?>

                                            <option
                                                value="<?= h(
                                                    $value
                                                ) ?>"
                                                <?= $order[
                                                    "payment_status"
                                                ] === $value
                                                    ? "selected"
                                                    : "" ?>
                                            >

                                                <?= h(
                                                    $label
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>


                                    <button
                                        type="submit"
                                        class="btn btn-pink"
                                    >
                                        บันทึกสถานะ
                                    </button>

                                </form>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >

                            <div class="empty-icon">
                                📦
                            </div>

                            <strong>
                                ไม่พบคำสั่งซื้อที่ตรงกับเงื่อนไข
                            </strong>

                            <p>
                                ลองเปลี่ยนคำค้นหาหรือเงื่อนไขการกรอง
                            </p>

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </section>


</main>

</body>

</html>