<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$fullname = $_SESSION['admin_fullname'] ?? 'Admin';

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function baht($v): string { return '฿' . number_format((float)$v, 2); }
function q(mysqli $conn, string $sql): array {
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
function one(mysqli $conn, string $sql): array { $r = q($conn, $sql); return $r[0] ?? []; }

/* นิยามยอดขาย: นับทุกออเดอร์ที่ไม่ถูกยกเลิก ใช้ total_amount */
$valid = "status <> 'cancelled'";

$today = one($conn, "SELECT COALESCE(SUM(total_amount),0) AS sales, COUNT(*) AS cnt
    FROM orders WHERE $valid AND DATE(order_date) = CURDATE()");
$month = one($conn, "SELECT COALESCE(SUM(total_amount),0) AS sales, COUNT(*) AS cnt
    FROM orders WHERE $valid AND YEAR(order_date)=YEAR(CURDATE()) AND MONTH(order_date)=MONTH(CURDATE())");
$all = one($conn, "SELECT COALESCE(SUM(total_amount),0) AS sales, COUNT(*) AS cnt,
    COALESCE(AVG(total_amount),0) AS avg_order FROM orders WHERE $valid");
$pending = one($conn, "SELECT COALESCE(SUM(status='pending'),0) AS pending_orders,
    COALESCE(SUM(payment_status='pending_review'),0) AS pending_slips FROM orders");
$cancelled = one($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE status='cancelled'");

/* ยอดขาย 7 วันล่าสุด */
$dailyRaw = q($conn, "SELECT DATE(order_date) AS d, SUM(total_amount) AS sales FROM orders
    WHERE $valid AND DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(order_date)");
$dailyMap = [];
foreach ($dailyRaw as $r) $dailyMap[$r['d']] = (float)$r['sales'];
$thaiDays = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
$daily = [];
for ($i = 6; $i >= 0; $i--) {
    $ts = strtotime("-$i day");
    $daily[] = [
        'label' => $thaiDays[(int)date('w', $ts)],
        'date'  => date('d/m', $ts),
        'sales' => $dailyMap[date('Y-m-d', $ts)] ?? 0,
        'today' => $i === 0,
    ];
}
$dailyMax = max(1, max(array_column($daily, 'sales')));

/* เค้ก vs เครื่องดื่ม */
$byType = one($conn, "SELECT
    COALESCE(SUM(CASE WHEN oi.cake_id IS NOT NULL AND oi.cake_id <> '' THEN oi.subtotal END),0) AS cake_sales,
    COALESCE(SUM(CASE WHEN oi.cake_id IS NOT NULL AND oi.cake_id <> '' THEN oi.quantity END),0) AS cake_qty,
    COALESCE(SUM(CASE WHEN oi.drink_id IS NOT NULL AND oi.drink_id <> '' THEN oi.subtotal END),0) AS drink_sales,
    COALESCE(SUM(CASE WHEN oi.drink_id IS NOT NULL AND oi.drink_id <> '' THEN oi.quantity END),0) AS drink_qty
    FROM order_items oi JOIN orders o ON o.order_id = oi.order_id WHERE o.status <> 'cancelled'");
$cakeSales  = (float)($byType['cake_sales'] ?? 0);
$drinkSales = (float)($byType['drink_sales'] ?? 0);
if ($cakeSales + $drinkSales > 0) {
    $cakePct = (int)round($cakeSales * 100 / ($cakeSales + $drinkSales));
    $drinkPct = 100 - $cakePct;
} else { $cakePct = 0; $drinkPct = 0; }

/* สินค้าขายดี Top 5 */
$top = q($conn, "SELECT
    CASE WHEN oi.cake_id IS NOT NULL AND oi.cake_id <> ''
              THEN COALESCE(NULLIF(ca.cake_name,''), CONCAT('เค้ก #', oi.cake_id))
         WHEN oi.drink_id IS NOT NULL AND oi.drink_id <> ''
              THEN COALESCE(NULLIF(dr.drink_name,''), CONCAT('เครื่องดื่ม #', oi.drink_id))
         ELSE 'ไม่พบชื่อสินค้า' END AS name,
    CASE WHEN oi.cake_id IS NOT NULL AND oi.cake_id <> '' THEN 'เค้ก' ELSE 'เครื่องดื่ม' END AS type,
    SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS sales
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    LEFT JOIN cake ca ON ca.cake_id = oi.cake_id
    LEFT JOIN drink dr ON dr.drink_id = oi.drink_id
    WHERE o.status <> 'cancelled'
    GROUP BY name, type ORDER BY qty DESC, sales DESC LIMIT 5");
$topMax = max(1, (int)($top[0]['qty'] ?? 1));

/* ออเดอร์ล่าสุด */
$recent = q($conn, "SELECT o.order_id, o.order_date, o.total_amount, o.status,
    COALESCE(NULLIF(o.customer_name,''),
             NULLIF(TRIM(CONCAT(COALESCE(c.firstname,''),' ',COALESCE(c.lastname,''))),''),
             'ลูกค้าทั่วไป') AS customer_name
    FROM orders o
    LEFT JOIN (SELECT customer_id, MAX(firstname) AS firstname, MAX(lastname) AS lastname
               FROM customers GROUP BY customer_id) c
           ON CAST(o.customer_id AS CHAR) = CAST(c.customer_id AS CHAR)
    ORDER BY o.order_date DESC, o.order_id DESC LIMIT 6");

$statusText = ['pending'=>'รอดำเนินการ','confirmed'=>'ยืนยันแล้ว','preparing'=>'กำลังเตรียม',
               'ready'=>'พร้อมรับ','completed'=>'รับสินค้าแล้ว','cancelled'=>'ยกเลิก'];
$statusClass = ['pending'=>'warn','confirmed'=>'info','preparing'=>'info',
                'ready'=>'ok','completed'=>'ok','cancelled'=>'bad'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>ภาพรวมยอดขาย | KLONG 6 CAFE</title>

<link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
      rel="stylesheet">

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
    box-shadow: 0 5px 25px rgba(193, 18, 63, .12);
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
    text-shadow: 0 3px 10px rgba(150, 0, 50, .18);
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
   WELCOME
========================= */

.welcome {
    text-align: left;
    animation: fadeUp .7s ease both;
}

.welcome .small-title {
    color: #c1123f;
    font-size: 14px;
    letter-spacing: 4px;
    font-weight: 700;
}

.welcome h1 {
    margin: 8px 0 5px;
    color: #c1123f;
    font-size: 38px;
}

.welcome p {
    color: #87616c;
    margin-top: 5px;
}

/* =========================
   CARD GRID
========================= */

.card-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin-top: 35px;
}

/* =========================
   CARD
========================= */

.card {
    position: relative;
    text-decoration: none;
    color: #632b3b;
    background: rgba(255,255,255,.92);
    padding: 30px;
    border-radius: 25px;
    border: 1px solid #f7dce4;
    box-shadow: 0 8px 30px rgba(193,18,63,.08);
    display: block;
    transition: .3s ease;
    overflow: hidden;
    animation: fadeUp .8s ease both;
}

.card::before {
    content: "♡";
    position: absolute;
    right: -10px;
    top: -25px;
    font-size: 100px;
    color: rgba(255, 113, 164, .08);
}

.card:hover {
    transform: translateY(-7px);
    box-shadow: 0 12px 30px rgba(193,18,63,.14);
    border-color: #f7c4d4;
}

.card-icon {
    position: relative;
    z-index: 1;
    font-size: 55px;
    margin-bottom: 12px;
    animation: floating 4s ease-in-out infinite;
}

.card h2 {
    position: relative;
    z-index: 1;
    margin: 10px 0;
    color: #a31643;
    font-size: 22px;
}

.card p {
    position: relative;
    z-index: 1;
    color: #98717c;
    line-height: 1.8;
    margin-bottom: 20px;
}

.card-link {
    position: relative;
    z-index: 1;
    color: #c1123f;
    font-weight: 700;
}

/* =========================
   ANIMATION
========================= */

@keyframes fadeUp {

    from {
        opacity: 0;
        transform: translateY(25px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes floating {

    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
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

    .card-grid {
        grid-template-columns: 1fr 1fr;
    }

}

@media (max-width: 700px) {

    .welcome h1 {
        font-size: 30px;
    }

    .card-grid {
        grid-template-columns: 1fr;
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

    .welcome h1 {
        font-size: 27px;
    }

    .card {
        padding: 25px;
        border-radius: 20px;
    }

}


/* =========================
   SALES DASHBOARD
========================= */
.section-title{
    display:flex;
    align-items:center;
    gap:10px;margin:42px 0 18px;
    color:#c1123f;
    font-size:24px;
    font-weight:700
}

.section-title small{
    color:#98717c;
    font-size:13px;
    font-weight:500
}
.stat-grid
{display:grid;
grid-template-columns:repeat(4,1fr);
gap:20px;
margin-top:28px
}

.stat{
    position:relative;
    background:rgba(255,255,255,.92);
    border:1px solid #f7dce4;
    border-radius:25px;
    padding:24px;
    box-shadow:0 8px 30px rgba(193,18,63,.08);
    overflow:hidden;
    animation:fadeUp .8s ease both;
    transition:.3s ease
}

.stat:hover{
    transform:translateY(-5px);
    border-color:#f7c4d4
}

.stat::before{
    content:"♡";
    position:absolute;
    right:-8px;
    top:-22px;
    font-size:90px;
    color:rgba(255,113,164,.09)
}

.stat .icon{
    font-size:34px;
    margin-bottom:6px
}

.stat .label{
    color:#98717c;
    font-size:14px
}

.stat .value{
    color:#a31643;
    font-size:28px;
    font-weight:700;
    margin:4px 0 2px
}

.stat .sub{
    color:#b58a96;
    font-size:13px
}

.stat.highlight{
    background:linear-gradient(135deg,#ff71a4,#ffb7cd);
    border-color:transparent
}

.stat.highlight .label,.stat.highlight .sub{
    color:rgba(255,255,255,.92)
}

.stat.highlight .value{
    color:#fff;
    text-shadow:0 2px 8px rgba(150,0,50,.18)
}

.stat.highlight::before{
    color:rgba(255,255,255,.18)
}


.panel-grid{
    display:grid;
    grid-template-columns:1.5fr 1fr;gap:22px
}

.panel{
    background:rgba(255,255,255,.92);
    border:1px solid #f7dce4;
    border-radius:25px;
    padding:26px;
    box-shadow:0 8px 30px rgba(193,18,63,.08);
    animation:fadeUp .9s ease both;
    min-width:0
}

.panel h3{
    margin:0 0 4px;
    color:#a31643;
    font-size:20px
}

.panel .hint{
    color:#98717c;
    font-size:13px;
    margin-bottom:18px
}


.chart{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:12px;height:230px;
    padding-top:10px;
    border-bottom:2px dashed #f7dce4
}

.bar-col{
    flex:1;
    height:100%;
    display:flex;
    flex-direction:column;
    justify-content:flex-end;
    align-items:center;
    gap:6px
}

.bar-val{
    font-size:12px;
    color:#98717c;
    white-space:nowrap
}

.bar{
    width:100%;
    max-width:56px;
    min-height:6px;
    border-radius:14px 14px 6px 6px;
    background:linear-gradient(180deg,#ffb7cd,#ff71a4);
    transition:.3s ease
}

.bar-col:hover .bar{
    filter:brightness(1.05)
}

.bar-col.today .bar{
    background:linear-gradient(180deg,#ff71a4,#c1123f)
}

.axis{
    display:flex;
    justify-content:space-between;
    gap:12px;
    margin-top:10px
}

.axis div{
    flex:1;
    text-align:center;
    font-size:13px;
    color:#87616c
}

.axis div small{
    display:block;
    color:#b58a96;
    font-size:11px
}

.axis div.today{
    color:#c1123f;
    font-weight:700
}


.split-bar{
    display:flex;
    height:22px;
    border-radius:30px;
    overflow:hidden;
    background:#fde8ef;
    margin:6px 0 18px
}

.split-bar .cake{
    background:linear-gradient(90deg,#ff71a4,#ff94b8)
}

.split-bar .drink{
    background:linear-gradient(90deg,#ffc9d9,#ffdce7)
}

.legend-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 0;
    border-top:1px solid #fbe6ec
}

.legend-row .name{
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:600
}

.dot{
    width:14px;
    height:14px;
    border-radius:50%;
    display:inline-block
}

.dot.cake{
    background:#ff71a4
}
.dot.drink{
    background:#ffc9d9
}

.legend-row .amt{
    text-align:right;
    color:#a31643;
    font-weight:700
}

.legend-row .amt small{
    display:block;
    color:#98717c;
    font-weight:400;
    font-size:12px
}


.rank{
    display:flex;
    align-items:center;
    gap:14px;
    padding:12px 0;
    border-top:1px solid #fbe6ec
}

.rank.first{
    border-top:0
}

.rank-no{
    width:34px;
    height:34px;
    flex:0 0 34px;
    border-radius:50%;
    background:#fde8ef;
    color:#c1123f;
    display:grid;
    place-items:center;
    font-weight:700
}

.rank.first .rank-no{
    background:linear-gradient(135deg,#ff71a4,#ffb7cd);color:#fff
}

.rank-body{
    flex:1;
    min-width:0
}

.rank-name{
    font-weight:600;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis
}

.rank-track{
    height:8px;
    background:#fde8ef;
    border-radius:20px;
    margin-top:6px;
    overflow:hidden
}

.rank-fill{
    height:100%;
    background:linear-gradient(90deg,#ffb7cd,#ff71a4);
    border-radius:20px
}

.rank-meta{
    text-align:right;
    font-size:13px;
    color:#98717c;
    white-space:nowrap
}

.rank-meta b{
    display:block;
    color:#a31643
    ;font-size:15px
}

.pill{
    display:inline-block;
    padding:2px 10px;
    border-radius:20px;
    font-size:11px;
    background:#fde8ef;
    color:#c1123f;
    margin-left:6px;
    vertical-align:middle
}


.table-wrap{
    overflow-x:auto
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:460px
}

th{
    background:#fde8ef;
    color:#a31643;
    padding:12px 14px;
    text-align:left;
    font-size:14px
}

th:first-child{
    border-radius:14px 0 0 14px
}
th:last-child{
    border-radius:0 14px 14px 0
}
td{
    padding:13px 14px;
    border-bottom:1px solid #fbe6ec;
    font-size:14px
}
td.num,th.num{
    text-align:right
}
tr:last-child td{
    border-bottom:0
}
td a{
    color:#c1123f;
    font-weight:700;
    text-decoration:none
}
.badge{
    display:inline-block;
    padding:4px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:600
}
.badge.warn{
    background:#fff0cf;
    color:#a36a00
}
.badge.info{
    background:#e6f0ff;
    color:#2f5fb3
}
.badge.ok{
    background:#e0f7e9;
    color:#23824a
}
.badge.bad{
    background:#ffe0e5;
    color:#c1123f
}
.empty{
    text-align:center;
    color:#b58a96;
    padding:30px 0
}
.view-all{
    display:inline-block;
    margin-top:16px;
    color:#c1123f;
    font-weight:700;
    text-decoration:none
}
.view-all:hover{
    text-decoration:underline
}

@media (max-width:1000px){
    .stat-grid{grid-template-columns:1fr 1fr}
    .panel-grid{grid-template-columns:1fr}
}
@media (max-width:550px){
    .stat-grid{grid-template-columns:1fr}
    .panel{padding:20px}
    .bar-val{font-size:10px}
}

.header-actions{
    position: relative;
    z-index: 5;
    display: flex;
    gap: 10px;
    flex-wrap: wrap}
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
            ภาพรวมยอดขาย 🍒 KLONG 6 CAFE
        </div>

        <div class="subtitle">
            SALES OVERVIEW
        </div>
    </div>

    <div class="header-actions">
        <a href="dashboard.php" class="logout-btn">← กลับหน้าจัดการร้าน</a>
        <a href="logout.php" class="logout-btn">ออกจากระบบ</a>
    </div>
</header>

<!-- =========================
     MAIN
========================= -->

<main class="container">

    <!-- WELCOME -->
    <section class="welcome">
        <h1>
            ภาพรวมยอดขายของร้าน 🍰
        </h1>
    </section>

    <!-- CARD GRID -->

    

    <!-- ===== สรุปตัวเลขยอดขาย ===== -->
    <div class="stat-grid">
        <div class="stat highlight">
            <div class="icon">💰</div>
            <div class="label">ยอดขายวันนี้</div>
            <div class="value"><?= baht($today['sales'] ?? 0) ?></div>
            <div class="sub"><?= (int)($today['cnt'] ?? 0) ?> ออเดอร์</div>
        </div>
        <div class="stat">
            <div class="icon">📅</div>
            <div class="label">ยอดขายเดือนนี้</div>
            <div class="value"><?= baht($month['sales'] ?? 0) ?></div>
            <div class="sub"><?= (int)($month['cnt'] ?? 0) ?> ออเดอร์</div>
        </div>
        <div class="stat">
            <div class="icon">🧾</div>
            <div class="label">ยอดขายรวมทั้งหมด</div>
            <div class="value"><?= baht($all['sales'] ?? 0) ?></div>
            <div class="sub"><?= (int)($all['cnt'] ?? 0) ?> ออเดอร์ · เฉลี่ย <?= baht($all['avg_order'] ?? 0) ?></div>
        </div>
        <div class="stat">
            <div class="icon">⏳</div>
            <div class="label">รอดำเนินการ</div>
            <div class="value"><?= (int)($pending['pending_orders'] ?? 0) ?> ออเดอร์</div>
            <div class="sub">รอตรวจสลิป <?= (int)($pending['pending_slips'] ?? 0) ?> · ยกเลิก <?= (int)($cancelled['cnt'] ?? 0) ?></div>
        </div>
    </div>

    <div class="section-title">📈 ภาพรวมยอดขาย <small>ไม่นับออเดอร์ที่ยกเลิก</small></div>

    <div class="panel-grid">
        <div class="panel">
            <h3>ยอดขาย 7 วันล่าสุด</h3>
            <div class="hint">รวม <?= baht(array_sum(array_column($daily, 'sales'))) ?></div>
            <div class="chart">
                <?php foreach ($daily as $d): ?>
                    <div class="bar-col <?= $d['today'] ? 'today' : '' ?>">
                        <div class="bar-val"><?= $d['sales'] > 0 ? number_format($d['sales'], 0) : '' ?></div>
                        <div class="bar" style="height: <?= max(2, round($d['sales'] * 100 / $dailyMax * 0.78)) ?>%"
                             title="<?= h($d['date']) ?> : <?= baht($d['sales']) ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="axis">
                <?php foreach ($daily as $d): ?>
                    <div class="<?= $d['today'] ? 'today' : '' ?>"><?= h($d['label']) ?><small><?= h($d['date']) ?></small></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <h3>สัดส่วนยอดขาย</h3>
            <div class="hint">เค้ก เทียบกับ เครื่องดื่ม</div>
            <div class="split-bar">
                <div class="cake" style="width: <?= $cakePct ?>%"></div>
                <div class="drink" style="width: <?= $drinkPct ?>%"></div>
            </div>
            <div class="legend-row">
                <div class="name"><span class="dot cake"></span> 🍰 เค้ก (<?= $cakePct ?>%)</div>
                <div class="amt"><?= baht($cakeSales) ?><small><?= (int)($byType['cake_qty'] ?? 0) ?> ชิ้น</small></div>
            </div>
            <div class="legend-row">
                <div class="name"><span class="dot drink"></span> 🥤 เครื่องดื่ม (<?= $drinkPct ?>%)</div>
                <div class="amt"><?= baht($drinkSales) ?><small><?= (int)($byType['drink_qty'] ?? 0) ?> แก้ว</small></div>
            </div>
        </div>
    </div>

    <div class="panel-grid" style="margin-top:22px">
        <div class="panel">
            <h3>🏆 สินค้าขายดี Top 5</h3>
            <div class="hint">เรียงตามจำนวนที่ขายได้</div>
            <?php if (!$top): ?>
                <div class="empty">ยังไม่มีข้อมูลการขาย 🍓</div>
            <?php else: foreach ($top as $i => $t): ?>
                <div class="rank <?= $i === 0 ? 'first' : '' ?>">
                    <div class="rank-no"><?= $i + 1 ?></div>
                    <div class="rank-body">
                        <div class="rank-name"><?= h($t['name']) ?><span class="pill"><?= h($t['type']) ?></span></div>
                        <div class="rank-track"><div class="rank-fill" style="width: <?= round((int)$t['qty'] * 100 / $topMax) ?>%"></div></div>
                    </div>
                    <div class="rank-meta"><b><?= (int)$t['qty'] ?> ชิ้น</b><?= baht($t['sales']) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="panel">
            <h3>🛒 ออเดอร์ล่าสุด</h3>
            <div class="hint">6 รายการล่าสุด</div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ออเดอร์</th><th>ลูกค้า</th><th class="num">ยอด</th><th>สถานะ</th></tr></thead>
                    <tbody>
                    <?php if (!$recent): ?>
                        <tr><td colspan="4" class="empty">ยังไม่มีออเดอร์</td></tr>
                    <?php else: foreach ($recent as $o): $st = (string)$o['status']; ?>
                        <tr>
                            <td><a href="order_detail.php?id=<?= urlencode((string)$o['order_id']) ?>">#<?= h($o['order_id']) ?></a></td>
                            <td><?= h($o['customer_name']) ?></td>
                            <td class="num"><?= baht($o['total_amount']) ?></td>
                            <td><span class="badge <?= h($statusClass[$st] ?? 'info') ?>"><?= h($statusText[$st] ?? $st) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <a class="view-all" href="orders.php">ดูออเดอร์ทั้งหมด →</a>
        </div>
    </div>
</main>
</body>
</html>