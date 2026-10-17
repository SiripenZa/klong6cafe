<?php
session_start();
require_once "conn.php";

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit;
}

$customer_id = (int) $_SESSION["customer_id"];
$firstname = $_SESSION['customer_firstname'] ?? '';
$isLoggedIn = true;
$currentPage = basename($_SERVER['SCRIPT_NAME']);

// นับจำนวนสินค้าในตะกร้า (ใช้แสดงที่ header)
$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $cartItem) {
    if (is_array($cartItem)) {
        $cartCount += max(0, (int)($cartItem['quantity'] ?? 0));
    }
}

// รับคำค้นหาเลขคำสั่งซื้อ
$keyword = trim($_GET["keyword"] ?? "");

// สถานะคำสั่งซื้อ
$order_labels = [
    "pending"    => "รอดำเนินการ",
    "confirmed"  => "ยืนยันคำสั่งซื้อแล้ว",
    "preparing"  => "กำลังเตรียมสินค้า",
    "ready"      => "พร้อมรับสินค้า",
    "completed"  => "เสร็จสิ้น",
    "cancelled"  => "ยกเลิกคำสั่งซื้อ"
];

// สถานะการชำระเงิน
$payment_labels = [
    "pending_review" => "รอตรวจสอบสลิป",
    "pending"        => "รอชำระเงิน",
    "paid"            => "ชำระเงินแล้ว",
    "verified"        => "ตรวจสอบแล้ว",
    "rejected"        => "สลิปไม่ผ่านการตรวจสอบ"
];

// ดึงคำสั่งซื้อเฉพาะของลูกค้าที่เข้าสู่ระบบ
if ($keyword !== "") {
    $sql = "SELECT
                order_id,
                order_date,
                pickup_date,
                pickup_time,
                total_amount,
                deposit_amount,
                balance_amount,
                status,
                payment_status
            FROM orders
            WHERE customer_id = ?
              AND order_id LIKE ?
            ORDER BY order_date DESC";

    $stmt = $conn->prepare($sql);
    $search = "%" . $keyword . "%";
    $stmt->bind_param("is", $customer_id, $search);
} else {
    $sql = "SELECT
                order_id,
                order_date,
                pickup_date,
                pickup_time,
                total_amount,
                deposit_amount,
                balance_amount,
                status,
                payment_status
            FROM orders
            WHERE customer_id = ?
            ORDER BY order_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
}

$stmt->execute();
$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

// ป้องกันข้อความ HTML ที่ไม่ปลอดภัย
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// แสดงวันที่
function formatDate($date) {
    if (empty($date)) {
        return "-";
    }

    $timestamp = strtotime($date);
    return $timestamp ? date("d/m/Y", $timestamp) : "-";
}

// แสดงเวลาแบบ AM/PM
function formatTime($time) {
    if (empty($time)) {
        return "-";
    }

    $timestamp = strtotime($time);
    return $timestamp ? date("g:i A", $timestamp) : "-";
}

// สีสถานะคำสั่งซื้อ
function orderStatusClass($status) {
    switch ($status) {
        case "completed":
            return "success";
        case "cancelled":
            return "cancelled";
        case "ready":
            return "ready";
        case "preparing":
            return "preparing";
        default:
            return "pending";
    }
}

// สีสถานะการชำระเงิน
function paymentStatusClass($status) {
    switch ($status) {
        case "paid":
        case "verified":
            return "success";
        case "rejected":
            return "cancelled";
        default:
            return "pending";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>ประวัติการสั่งซื้อ | KLONG 6 CAFE</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&family=Pacifico&display=swap"
      rel="stylesheet">

<style>
/* ===== Pink Garden Theme (เหมือนหน้าเค้ก) ===== */
:root{
    --pink:#ffb6d0;
    --pink-light:#fff4f8;
    --red:#ed2674;
    --red-dark:#bd1555;
    --cream:#fff8fb;
    --text:#812349;
    --muted:#a85d7c;
    --border:#ffd0e0;
}

*{ box-sizing:border-box; }
html{ scroll-behavior:smooth; }

body{
    margin:0;
    font-family:'Mali',sans-serif;
    color:var(--text);
    background:#fff8fb;
    background-image:
        radial-gradient(ellipse at 10% 35%,#ffd9e7 0,transparent 28%),
        radial-gradient(ellipse at 90% 72%,#ffe2ed 0,transparent 25%);
}

a{ color:inherit; text-decoration:none; }
button,input{ font-family:inherit; }

/* แถบประกาศ */
.announcement{
    background:linear-gradient(90deg,#ff7cab,#ed2674,#ff8db7);
    color:#fff;
    text-align:center;
    padding:9px 12px;
    font-size:12px;
    font-weight:600;
    letter-spacing:.4px;
}

/* Header */
.header{
    background:linear-gradient(115deg,#ff8db7,#ffbad2 55%,#ffe6ef);
    padding:17px 5%;
    box-shadow:0 8px 25px #d72c6d16;
}
.header-inner{
    max-width:1320px;
    margin:auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    flex-wrap:wrap;
}
.brand{
    color:#fff;
    font-size:clamp(22px,2.5vw,31px);
    font-weight:700;
    text-shadow:0 2px 12px #b81e5d55;
}
.brand small{
    display:block;
    color:#fff8fb;
    font-size:10px;
    letter-spacing:2px;
}
.account{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
    font-size:12px;
}
.account a{
    padding:9px 14px;
    border:1px solid #ffffff9c;
    border-radius:30px;
    background:#ffffffdc;
    color:#d21f65;
    box-shadow:0 4px 12px #b81e5d13;
}
.account a.cart-link{
    background:linear-gradient(120deg,#ff5c9b,#e91d6d);
    border-color:transparent;
    color:#fff;
}
.welcome{ color:#fff; font-weight:700; }

/* ===== Glass Nav เหมือนหน้าเค้ก ===== */
.nav-strip{
    position:sticky;
    top:0;
    z-index:100;
    padding:10px 4%;
    background:linear-gradient(115deg,#ff8db7,#ff9fc2 55%,#ffb3cc);
}
.nav-wrap{
    position:static;
    width:fit-content;
    max-width:100%;
    margin:0 auto;
    background:rgba(255,255,255,.22);
    border:1px solid rgba(255,255,255,.5);
    border-radius:999px;
    box-shadow:0 6px 20px rgba(184,42,91,.15);
    backdrop-filter:blur(12px);
    -webkit-backdrop-filter:blur(12px);
}
.nav-wrap .nav{
    display:flex;
    justify-content:center;
    align-items:center;
    flex-wrap:wrap;
    gap:4px;
    padding:5px 8px;
    margin:0 auto;
    background:transparent;
    border:0;
    box-shadow:none;
}
.nav-wrap .nav a{
    padding:9px 17px;
    border-radius:999px;
    background:transparent;
    color:#fff;
    font-size:13px;
    font-weight:700;
    text-shadow:0 1px 6px rgba(150,25,75,.35);
    white-space:nowrap;
    transition:background .2s,transform .2s;
}
.nav-wrap .nav a:hover{
    background:rgba(255,255,255,.28);
    transform:translateY(-1px);
}
.nav-wrap .nav a.active{
    background:linear-gradient(120deg,#ff5c9b,#ed2674);
    box-shadow:0 5px 14px rgba(229,44,116,.35);
}

/* Hero */
.orders-hero{
    position:relative;
    isolation:isolate;
    overflow:hidden;
    padding:46px 6%;
    text-align:center;
    background:
        linear-gradient(100deg,#e94a86d9,#ff9fc2b0 52%,#ffc9dcb0),
        url('รูปร้าน.png') center 48%/cover no-repeat;
}
.orders-hero:before{
    content:"";
    position:absolute;
    inset:0;
    z-index:-1;
    background:linear-gradient(90deg,#c92d6b55,#fff0f61c);
}
.hero-kicker{
    color:#fff;
    font-size:12px;
    letter-spacing:2px;
    font-weight:700;
    text-shadow:0 2px 12px #9f164f55;
}
.orders-hero h1{
    margin:8px 0;
    color:#fff;
    font-size:clamp(34px,5vw,56px);
    line-height:1.15;
    text-shadow:0 2px 12px #9f164f55;
}
.orders-hero h1 span{
    font-family:'Pacifico','Mali',cursive;
    color:#fff3f8;
}
.orders-hero p{
    margin:6px auto 0;
    color:#fff;
    font-size:15px;
    text-shadow:0 2px 12px #9f164f55;
}
.hero-petal{
    position:absolute;
    color:#fff;
    font-size:25px;
    opacity:.8;
    animation:heroFloat 5s ease-in-out infinite;
}
.hero-petal.one{ left:8%; top:22%; }
.hero-petal.two{ right:9%; bottom:18%; animation-delay:-2s; }

@keyframes heroFloat{
    0%,100%{ transform:translateY(0); }
    50%{ transform:translateY(-12px); }
}

/* เนื้อหา */
.container{
    width:min(1100px,92%);
    margin:0 auto;
    padding:34px 0 50px;
}

.page-heading{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
    margin-bottom:20px;
}
.page-heading h2{
    margin:0;
    color:#d51f65;
    font-size:clamp(24px,3vw,34px);
}
.count{
    margin-top:4px;
    color:var(--muted);
    font-size:13px;
}

/* ปุ่ม */
.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:11px 20px;
    border:0;
    border-radius:30px;
    font-size:13px;
    font-weight:700;
    text-align:center;
    cursor:pointer;
    transition:transform .2s,box-shadow .2s,background .2s;
}
.btn:hover{ transform:translateY(-1px); }

.btn-primary{
    background:linear-gradient(120deg,#ff6da3,#ed176b);
    color:#fff;
    box-shadow:0 6px 15px #e52c7430;
}
.btn-primary:hover{ box-shadow:0 8px 18px #e52c7440; }

.btn-light{
    background:#fff;
    color:#c91e60;
    border:1px solid #ffc5d9;
}
.btn-light:hover{ background:#fff0f6; }

/* ช่องค้นหา */
.search-box{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:24px;
}
.search-box input{
    flex:1;
    min-width:0;
    padding:14px 18px;
    border:1px solid #ffc5d9;
    border-radius:30px;
    background:#fff;
    color:var(--text);
    font-size:13px;
    outline:none;
    box-shadow:0 7px 22px #dc4c8110;
}
.search-box input:focus{
    border-color:#f04486;
    box-shadow:0 0 0 4px #ffb6d033;
}

/* การ์ดออเดอร์ */
.order-card{
    margin-bottom:22px;
    padding:22px;
    background:linear-gradient(145deg,#fff,#fff0f6);
    border:1px solid #ffd0e0;
    border-radius:23px;
    box-shadow:0 10px 28px #ce397219;
    transition:transform .25s,box-shadow .25s;
}
.order-card:hover{
    transform:translateY(-3px);
    box-shadow:0 17px 34px #ce39722c;
}

.order-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    flex-wrap:wrap;
    gap:12px;
    padding-bottom:15px;
    border-bottom:1px dashed #ffc5d9;
}
.order-number{
    color:#d51f65;
    font-size:17px;
    font-weight:700;
    overflow-wrap:anywhere;
}
.order-date{
    margin-top:5px;
    color:var(--muted);
    font-size:12px;
}

.status-group{
    display:flex;
    flex-wrap:wrap;
    gap:7px;
}
.status{
    display:inline-block;
    padding:5px 12px;
    border-radius:20px;
    font-size:11px;
    font-weight:600;
}
.pending{ background:#fff1c9; color:#805a00; }
.success{ background:#dff5e6; color:#216b3a; }
.ready{ background:#dff1ff; color:#155e8b; }
.preparing{ background:#f0e5ff; color:#6941a5; }
.cancelled{ background:#ffe0e0; color:#a52c2c; }

.order-info{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
    padding:18px 0;
}
.info-item{
    padding:13px 15px;
    background:#fff;
    border:1px solid #ffe0ea;
    border-radius:16px;
}
.info-label{
    margin-bottom:5px;
    color:var(--muted);
    font-size:12px;
}
.info-value{
    color:var(--text);
    font-size:14px;
    font-weight:600;
    overflow-wrap:anywhere;
}

.money{
    padding-top:15px;
    border-top:1px dashed #ffc5d9;
}
.money-row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    padding:5px 0;
    font-size:13px;
}
.money-row span:last-child{
    text-align:right;
    white-space:nowrap;
}
.money-total{
    margin-top:6px;
    padding-top:10px;
    border-top:1px solid #ffd0e0;
    color:#d51f65;
    font-size:18px;
    font-weight:700;
}

.order-actions{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:18px;
}

/* ไม่พบข้อมูล */
.empty{
    padding:50px 20px;
    background:#fff;
    border:1px dashed #f4a9c6;
    border-radius:23px;
    text-align:center;
}
.empty-icon{ font-size:44px; }
.empty h3{ margin:10px 0; color:#d51f65; }
.empty p{ margin:0 0 16px; color:var(--muted); font-size:13px; }

/* Footer */
.orders-footer{
    background:linear-gradient(100deg,#ffe3ee,#fff5f9,#ffd2e3);
    text-align:center;
    color:#a34b70;
    padding:22px 12px;
    font-size:13px;
}
.orders-footer strong{
    font-family:'Pacifico','Mali',cursive;
    color:#d51f65;
    font-size:23px;
}

/* Responsive */
@media(max-width:540px){
    .header{ padding:15px 4%; }
    .header-inner{ justify-content:center; text-align:center; }
    .brand{ width:100%; }
    .account{ gap:6px; }

    .nav-strip{ padding:9px 3%; }
    .nav-wrap{ border-radius:24px; width:100%; }
    .nav-wrap .nav{
        justify-content:flex-start;
        flex-wrap:nowrap;
        overflow-x:auto;
    }
    .nav-wrap .nav a{ flex-shrink:0; padding:8px 12px; font-size:12px; }

    .orders-hero{ padding:32px 5%; }
    .orders-hero p{ font-size:13px; }

    .container{ padding-top:27px; }
    .page-heading h2{ font-size:22px; }

    .search-box{ flex-wrap:wrap; }
    .search-box input{ flex-basis:100%; }
    .search-box .btn{ flex:1; }

    .order-card{ padding:16px; }
    .order-info{ grid-template-columns:1fr; gap:9px; }
    .order-top{ flex-direction:column; }
    .order-actions .btn{ flex:1; }
}

@media(prefers-reduced-motion:reduce){
    .hero-petal{ animation:none!important; }
    .order-card{ transition:none; }
}

/* Cursor ปกติ: ลูกศรมนสีชมพูเข้ม */
html, body, body * {
    cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Cpath d='M7 3 Q5 2 5 6 L5 26 Q5 30 9 27 L14 21 L18 29 Q20 32 23 30 L24 28 L19 20 L27 20 Q31 20 28 17 Z' fill='%23e83e79' stroke='%23a91e50' stroke-width='1.5'/%3E%3C/svg%3E") 5 4, auto !important;
}

/* เมื่อชี้ปุ่มหรือลิงก์: Emoji เค้ก */
a, button, input[type="submit"],
input[type="button"], [role="button"], .btn {
    cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='36' height='36' viewBox='0 0 36 36'%3E%3Ctext x='2' y='29' font-size='29'%3E%F0%9F%8D%B0%3C/text%3E%3C/svg%3E") 4 4, pointer !important;
}
/* Home-style header and centered navigation */
.announcement{display:none}
.header{position:absolute;top:0;left:0;z-index:10;width:100%;padding:17px 5%;background:linear-gradient(180deg,rgba(0,0,0,.5),transparent);box-shadow:none}
.header-inner{max-width:1320px;flex-wrap:nowrap}
.brand{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none}
.brand-mark{font-size:29px}
.brand-name{font-size:clamp(22px,2.5vw,31px);font-weight:700;line-height:1.2;text-shadow:0 2px 10px #000}
.brand-name small{display:block;margin-top:3px;color:#fff;font-size:10px;letter-spacing:2px}
.welcome{color:#fff;text-shadow:0 2px 8px #000}
.account a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:10px 17px;border:1px solid rgba(255,105,160,.9);border-radius:4px;background:rgba(255,182,205,.32);color:#fff;font-size:13px;text-shadow:0 1px 5px rgba(80,20,45,.65);backdrop-filter:blur(8px)}
.account a[href="register.php"]{background:#ed367f}
.nav-strip{position:absolute;top:19px;left:50%;z-index:11;width:max-content;max-width:70%;padding:0;transform:translateX(-50%);background:transparent}
.nav-wrap{position:static;width:fit-content;max-width:100%;margin:0;border:1px solid rgba(255,105,160,.9);border-radius:40px;background:rgba(0,0,0,.22);box-shadow:0 8px 25px rgba(0,0,0,.12);backdrop-filter:blur(8px)}
.nav-wrap .nav{max-width:1320px;justify-content:center;flex-wrap:nowrap;gap:4px;padding:4px 7px;overflow-x:auto}
.nav-wrap .nav a{flex:0 0 auto;padding:9px 13px;border-radius:30px;background:transparent;color:#fff;font-size:13px;text-shadow:0 1px 6px rgba(0,0,0,.45)}
.nav-wrap .nav a:hover,.nav-wrap .nav a.active{background:#ed367f;color:#fff;box-shadow:none}
@media (min-width:1001px){.orders-hero{padding-top:125px}}
@media (max-width:1000px){
    .header{position:relative;top:auto;left:auto;padding:12px 4%;background:rgba(0,0,0,.65)}
    .header-inner{flex-wrap:wrap;justify-content:center}
    .account{margin-left:auto}
    .nav-strip{position:sticky;top:0;left:auto;width:100%;max-width:none;transform:none;background:rgba(0,0,0,.78)}
    .nav-wrap{width:100%;max-width:none;border:0;border-top:1px solid rgba(255,105,160,.9);border-bottom:1px solid rgba(255,105,160,.9);border-radius:0;background:transparent;box-shadow:none}
    .nav-wrap .nav{justify-content:flex-start;padding:7px 10px}
}
@media (max-width:600px){
    .header{padding:14px 5%;background:rgba(0,0,0,.65)}
    .header-inner{justify-content:center}
    .brand-name{font-size:19px}
    .brand-name small{font-size:8px;letter-spacing:1.5px}
    .account{justify-content:center;margin:0}
    .nav-wrap .nav a{padding:9px 12px;font-size:12px}
}
</style>
</head>

<body>

<!-- แถบประกาศ -->
<div class="announcement">
    ♡ Baked with love — ความสุขชิ้นเล็ก ๆ จาก KLONG 6 CAFE ♡
</div>

<!-- Header -->
<header class="header">
    <div class="header-inner">

        <a href="Home.php" class="brand">
            <span class="brand-mark" aria-hidden="true">🍒</span>
            <span class="brand-name">KLONG 6 CAFE<small>CAFE • BAKERY • HAPPINESS</small></span>
        </a>

        <div class="account">

            <span class="welcome">
                สวัสดี, <?= e($firstname) ?> 💗
            </span>

            <a href="logout.php">ออกจากระบบ</a>


        </div>
    </div>
</header>

<!-- เมนูนำทาง -->
<div class="nav-strip">
  <div class="nav-wrap">
    <nav class="nav">

          <a href="Home.php"
              class="<?= $currentPage === 'Home.php' ? 'active' : '' ?>">
            🏠 หน้าหลัก
        </a>

        <a href="cake.php"
           class="<?= $currentPage === 'cake.php' ? 'active' : '' ?>">
            🍰 เค้ก
        </a>

        <a href="drink.php"
           class="<?= $currentPage === 'drink.php' ? 'active' : '' ?>">
            🥤 เครื่องดื่ม
        </a>

        <a href="order.php"
           class="<?= $currentPage === 'order.php' ? 'active' : '' ?>">
            🧾 คำสั่งซื้อ
        </a>

        <a href="my_orders.php"
           class="<?= $currentPage === 'my_orders.php' ? 'active' : '' ?>"
           aria-current="page">
            📋 ประวัติการสั่งซื้อ
        </a>

        <a href="cart.php" class="cart-link">
            🛒 ตะกร้า
            (<span class="cart-count"><?= (int)$cartCount ?></span>)
        </a>

    </nav>
  </div>
</div>

<!-- Hero -->
<section class="orders-hero" aria-label="Order history welcome">
    <span class="hero-petal one" aria-hidden="true">✿</span>
    <span class="hero-petal two" aria-hidden="true">♡</span>
    <div class="hero-kicker">YOUR SWEET MEMORIES</div>
    <h1>My <span>Orders ♡</span></h1>
    <p>ติดตามสถานะคำสั่งซื้อและใบสรุปของคุณได้ที่นี่ 🍓</p>
</section>

<main class="container">

    <div class="page-heading">
        <div>
            <h2>ประวัติการสั่งซื้อ 🧁</h2>
            <div class="count">
                พบ <?= count($orders) ?> รายการ
            </div>
        </div>

        <a href="Home.php" class="btn btn-light">
            ← กลับหน้าหลัก
        </a>
    </div>

    <form class="search-box" method="GET" action="my_orders.php">
        <input
            type="text"
            name="keyword"
            placeholder="ค้นหาด้วยหมายเลขคำสั่งซื้อ..."
            value="<?= e($keyword) ?>"
        >

        <button type="submit" class="btn btn-primary">
            🔍 ค้นหา
        </button>

        <?php if ($keyword !== ""): ?>
            <a href="my_orders.php" class="btn btn-light">
                ล้างการค้นหา
            </a>
        <?php endif; ?>
    </form>

    <?php if (count($orders) > 0): ?>

        <?php foreach ($orders as $order): ?>

            <?php
                $order_status = $order_labels[$order["status"] ?? ""]
                    ?? ($order["status"] ?? "ไม่ระบุ");

                $payment_status = $payment_labels[$order["payment_status"] ?? ""]
                    ?? ($order["payment_status"] ?? "ไม่ระบุ");
            ?>

            <section class="order-card">

                <div class="order-top">
                    <div>
                        <div class="order-number">
                            ออเดอร์ #<?= e($order["order_id"]) ?>
                        </div>

                        <div class="order-date">
                            วันที่สั่งซื้อ:
                            <?= e(
                                $order["order_date"]
                                    ? date("d/m/Y H:i", strtotime($order["order_date"]))
                                    : "-"
                            ) ?>
                        </div>
                    </div>

                    <div class="status-group">
                        <span class="status <?= e(orderStatusClass($order["status"] ?? "")) ?>">
                            <?= e($order_status) ?>
                        </span>

                        <span class="status <?= e(paymentStatusClass($order["payment_status"] ?? "")) ?>">
                            <?= e($payment_status) ?>
                        </span>
                    </div>
                </div>

                <div class="order-info">

                    <div class="info-item">
                        <div class="info-label">📅 วันที่รับสินค้า</div>
                        <div class="info-value">
                            <?= e(formatDate($order["pickup_date"])) ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">🕒 เวลารับสินค้า</div>
                        <div class="info-value">
                            <?= e(formatTime($order["pickup_time"])) ?>
                        </div>
                    </div>

                </div>

                <div class="money">

                    <div class="money-row">
                        <span>ยอดมัดจำ</span>
                        <span>
                            <?= number_format((float) $order["deposit_amount"], 2) ?>
                            บาท
                        </span>
                    </div>

                    <div class="money-row">
                        <span>ยอดคงเหลือ</span>
                        <span>
                            <?= number_format((float) $order["balance_amount"], 2) ?>
                            บาท
                        </span>
                    </div>

                    <div class="money-row money-total">
                        <span>ยอดรวมทั้งหมด</span>
                        <span>
                            <?= number_format((float) $order["total_amount"], 2) ?>
                            บาท
                        </span>
                    </div>

                </div>

                <div class="order-actions">
                    <a
                        href="download_receipt.php?order_id=<?= urlencode($order["order_id"]) ?>"
                        class="btn btn-primary"
                    >
                        🧾 ดูใบสรุปคำสั่งซื้อ
                    </a>
                </div>

            </section>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty">
            <div class="empty-icon">🍰</div>

            <?php if ($keyword !== ""): ?>
                <h3>ไม่พบคำสั่งซื้อ</h3>
                <p>ลองตรวจสอบหมายเลขออเดอร์อีกครั้งนะคะ</p>
                <a href="my_orders.php" class="btn btn-light">
                    ดูคำสั่งซื้อทั้งหมด
                </a>
            <?php else: ?>
                <h3>ยังไม่มีประวัติการสั่งซื้อ</h3>
                <p>เลือกเค้กหรือเครื่องดื่มที่ชอบ แล้วมาเติมความหวานกันนะคะ</p>
                <a href="Home.php" class="btn btn-primary">
                    เลือกซื้อสินค้า
                </a>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</main>

<footer class="orders-footer">
    <strong>♡ Klong 6 Cafe ♡</strong>
    <div>Good Coffee&nbsp; • &nbsp;Sweet Drinks&nbsp; • &nbsp;Delicious Cake&nbsp; • &nbsp;Happy Time</div>
</footer>

</body>
</html>