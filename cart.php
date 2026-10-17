<?php
session_start();
require_once 'conn.php';

$firstname = $_SESSION['customer_firstname'] ?? '';
$isLoggedIn = !empty($_SESSION['customer_id']);
$currentPage = basename($_SERVER['SCRIPT_NAME']);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| ดึงข้อมูลสินค้าจากตาราง cake หรือ drink ตามคีย์ในตะกร้า
| (แก้ให้รองรับรหัสที่เป็นข้อความ เช่น cake_id แบบ VARCHAR)
|--------------------------------------------------------------------------
*/
function getCartProduct($conn, $key) {
    $key = (string)$key;

    if (preg_match('/^cake_(.+)$/', $key, $matches)) {
        $type = 'cake';
        $id = (string)$matches[1];
        $idColumn = 'cake_id';
        $nameColumn = 'cake_name';
    } elseif (preg_match('/^drink_(.+)$/', $key, $matches)) {
        $type = 'drink';
        $id = (string)$matches[1];
        $idColumn = 'drink_id';
        $nameColumn = 'drink_name';
    } else {
        return null;
    }

    $sql = "SELECT {$idColumn} AS item_id,
                   {$nameColumn} AS product_name,
                   price, stock, image, status
            FROM {$type}
            WHERE {$idColumn} = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    $stmt->close();

    if (!$product) {
        return null;
    }

    $product['item_type'] = $type;
    $product['item_id'] = $id;

    return $product;
}

/*
|--------------------------------------------------------------------------
| จัดการลบสินค้าและอัปเดตจำนวน
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'remove') {
        $key = (string)($_POST['product_id'] ?? '');

        if (isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);

            $_SESSION['cart_message'] = 'ลบสินค้าออกจากตะกร้าแล้ว 💗';
            $_SESSION['cart_message_type'] = 'success';
        } else {
            $_SESSION['cart_message'] = 'ไม่พบสินค้าที่ต้องการลบ';
            $_SESSION['cart_message_type'] = 'error';
        }

    } elseif ($action === 'update') {
        $quantities = $_POST['quantity'] ?? [];

        if (is_array($quantities)) {
            foreach ($quantities as $key => $qty) {
                $key = (string)$key;

                if (!isset($_SESSION['cart'][$key])) {
                    continue;
                }

                $qty = filter_var($qty, FILTER_VALIDATE_INT);

                if ($qty === false || $qty < 1) {
                    $_SESSION['cart_message'] =
                        'กรุณาระบุจำนวนสินค้าอย่างน้อย 1 ชิ้น';
                    $_SESSION['cart_message_type'] = 'error';
                    continue;
                }

                $product = getCartProduct($conn, $key);

                if (
                    !$product ||
                    strtolower(trim((string)$product['status'])) !== 'available' ||
                    (int)$product['stock'] < 1
                ) {
                    unset($_SESSION['cart'][$key]);

                    $_SESSION['cart_message'] =
                        'สินค้าบางรายการหมดหรือไม่ได้เปิดขายแล้ว';
                    $_SESSION['cart_message_type'] = 'error';
                    continue;
                }

                $stock = (int)$product['stock'];
                $finalQty = min((int)$qty, $stock);

                $_SESSION['cart'][$key] = [
                    'item_type'    => $product['item_type'],
                    'item_id'      => $product['item_id'],
                    'product_id'   => $product['item_id'],
                    'product_name' => $product['product_name'],
                    'name'         => $product['product_name'],
                    'price'        => (float)$product['price'],
                    'image'        => $product['image'] ?? '',
                    'quantity'     => $finalQty
                ];

                if ((int)$qty > $stock) {
                    $_SESSION['cart_message'] =
                        'ปรับจำนวนตามสต็อกที่เหลือ ' . $stock . ' ชิ้น';
                    $_SESSION['cart_message_type'] = 'error';
                }
            }
        }

        if (empty($_SESSION['cart_message'])) {
            $_SESSION['cart_message'] = 'อัปเดตจำนวนสินค้าเรียบร้อยแล้ว 💗';
            $_SESSION['cart_message_type'] = 'success';
        }

    } else {
        $_SESSION['cart_message'] = 'ไม่พบคำสั่งที่ต้องการ';
        $_SESSION['cart_message_type'] = 'error';
    }

    header('Location: cart.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| เตรียมข้อมูลตะกร้าสำหรับแสดงผล
|--------------------------------------------------------------------------
*/
$message = $_SESSION['cart_message'] ?? '';
$messageType = $_SESSION['cart_message_type'] ?? 'success';

unset($_SESSION['cart_message'], $_SESSION['cart_message_type']);

$cart = [];
$total = 0;
$totalQuantity = 0;

foreach ($_SESSION['cart'] as $key => $item) {
    if (!is_array($item)) {
        continue;
    }

    $quantity = (int)($item['quantity'] ?? 0);

    if ($quantity < 1) {
        continue;
    }

    $product = getCartProduct($conn, $key);

    if ($product) {
        // อ่านข้อมูลล่าสุดจากฐานข้อมูล
        if (
            strtolower(trim((string)$product['status'])) !== 'available' ||
            (int)$product['stock'] < 1
        ) {
            continue;
        }

        $quantity = min($quantity, (int)$product['stock']);

        $item['item_type'] = $product['item_type'];
        $item['item_id'] = $product['item_id'];
        $item['product_id'] = $product['item_id'];
        $item['product_name'] = $product['product_name'];
        $item['name'] = $product['product_name'];
        $item['price'] = (float)$product['price'];
        $item['image'] = $product['image'] ?? '';

    } else {
        // ถ้าค้นฐานข้อมูลไม่ได้ ให้ใช้ข้อมูลที่เก็บอยู่ใน session
        // แต่แสดงเฉพาะรายการที่รู้จักประเภทสินค้า
        if (
            !isset($item['item_type']) ||
            !in_array($item['item_type'], ['cake', 'drink'], true)
        ) {
            continue;
        }

        if (
            empty($item['product_name']) &&
            empty($item['name'])
        ) {
            continue;
        }
    }

    $item['quantity'] = $quantity;

    if (empty($item['product_name'])) {
        $item['product_name'] = $item['name'] ?? 'สินค้า';
    }

    $cart[$key] = $item;
    $_SESSION['cart'][$key] = $item;

    $total += (float)($item['price'] ?? 0) * $quantity;
    $totalQuantity += $quantity;
}

// เอารายการที่ไม่สามารถแสดงได้ออกจากตะกร้า
$_SESSION['cart'] = $cart;

$total = round($total, 2);
$cartCount = $totalQuantity;
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ตะกร้าสินค้า | KLONG 6 CAFE</title>

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
.cart-hero{
    position:relative;
    isolation:isolate;
    overflow:hidden;
    padding:46px 6%;
    text-align:center;
    background:
        linear-gradient(100deg,#e94a86d9,#ff9fc2b0 52%,#ffc9dcb0),
        url('รูปร้าน.png') center 48%/cover no-repeat;
}
.cart-hero:before{
    content:"";
    position:absolute;
    inset:0;
    z-index:-1;
    background:linear-gradient(90deg,#c92d6b55,#fff0f61c);
}
.cart-kicker{
    color:#fff;
    font-size:12px;
    letter-spacing:2px;
    font-weight:700;
    text-shadow:0 2px 12px #9f164f55;
}
.cart-hero h1{
    margin:8px 0;
    color:#fff;
    font-size:clamp(34px,5vw,56px);
    line-height:1.15;
    text-shadow:0 2px 12px #9f164f55;
}
.cart-hero h1 span{
    font-family:'Pacifico','Mali',cursive;
    color:#fff3f8;
}
.cart-hero p{
    margin:6px auto 0;
    color:#fff;
    font-size:15px;
    text-shadow:0 2px 12px #9f164f55;
}
.cart-petal{
    position:absolute;
    color:#fff;
    font-size:25px;
    opacity:.8;
    animation:cartFloat 5s ease-in-out infinite;
}
.cart-petal.one{ left:8%; top:22%; }
.cart-petal.two{ right:9%; bottom:18%; animation-delay:-2s; }

@keyframes cartFloat{
    0%,100%{ transform:translateY(0); }
    50%{ transform:translateY(-12px); }
}

/* เนื้อหา */
main{
    width:min(1100px,92%);
    margin:0 auto;
    padding:34px 0 50px;
}

.section-heading{
    text-align:center;
    margin-bottom:20px;
}
.section-heading h2{
    margin:0 0 5px;
    color:#d51f65;
    font-size:clamp(26px,3vw,36px);
}
.section-heading p{
    margin:0;
    color:var(--muted);
    font-size:14px;
}

/* ข้อความแจ้งเตือน */
.message{
    width:100%;
    margin:0 auto 20px;
    padding:14px 18px;
    border-radius:16px;
    text-align:center;
    background:#ffe3ee;
    color:#b71958;
    border:1px solid #ffc6da;
    font-size:14px;
}
.message.error{
    background:#fff0f0;
    color:#a72b45;
}

/* การ์ดตะกร้า */
.panel{
    background:linear-gradient(145deg,#fff,#fff0f6);
    border:1px solid #ffd0e0;
    border-radius:23px;
    padding:20px;
    box-shadow:0 10px 28px #ce397219;
}

.table-wrap{ overflow-x:auto; }

table{
    width:100%;
    border-collapse:collapse;
    min-width:620px;
}
th{
    background:#ffdce9;
    color:#bd1555;
    padding:14px 10px;
    font-size:14px;
}
th:first-child{ border-radius:16px 0 0 16px; }
th:last-child{ border-radius:0 16px 16px 0; }

td{
    padding:14px 10px;
    text-align:center;
    border-bottom:1px solid #ffd8e5;
    color:var(--text);
}
tbody tr:last-child td{ border-bottom:0; }

.product-image{
    width:76px;
    height:76px;
    object-fit:cover;
    border-radius:16px;
    border:3px solid #fff;
    box-shadow:0 5px 14px #ce397222;
    display:block;
    margin:0 auto 8px;
    background:#ffe5ef;
}
.product-name{
    color:#d51f65;
    font-size:15px;
}
.type-label{
    display:inline-block;
    margin-top:6px;
    padding:3px 10px;
    background:#fff0f4;
    border:1px solid #ffd0e0;
    color:#bd1555;
    border-radius:20px;
    font-size:11px;
}
.subtotal{
    color:#d51f65;
    font-weight:700;
}

.quantity-input{
    width:76px;
    padding:10px;
    border:1px solid #ffc5d9;
    border-radius:14px;
    background:#fff;
    color:var(--text);
    text-align:center;
    outline:none;
}
.quantity-input:focus{
    border-color:#f04486;
    box-shadow:0 0 0 4px #ffb6d033;
}

.qty-box{
    display:inline-flex;
    align-items:center;
    gap:6px;
}
.qty-btn{
    width:34px;
    height:34px;
    border:1px solid #ffc5d9;
    border-radius:50%;
    background:#fff;
    color:#bd1555;
    font-size:18px;
    font-weight:700;
    line-height:1;
    padding:0;
    cursor:pointer;
    transition:background .2s,transform .2s;
}
.qty-btn:hover{
    background:#ffe0ec;
    transform:translateY(-1px);
}
.quantity-input{
    width:56px;
    padding:8px 4px;
    -moz-appearance:textfield;
}
.quantity-input::-webkit-outer-spin-button,
.quantity-input::-webkit-inner-spin-button{
    -webkit-appearance:none;
    margin:0;
}

/* ปุ่ม */
.button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:0;
    border-radius:30px;
    padding:12px 22px;
    font-size:13px;
    font-weight:700;
    cursor:pointer;
    transition:transform .2s,box-shadow .2s;
}
.button:hover{ transform:translateY(-1px); }

.button-primary{
    background:linear-gradient(120deg,#ff6da3,#ed176b);
    color:#fff;
    box-shadow:0 6px 15px #e52c7430;
}
.button-primary:hover{ box-shadow:0 8px 18px #e52c7440; }

.button-light{
    background:#fff;
    color:#c91e60;
    border:1px solid #ffc5d9;
}
.button-light:hover{ background:#fff0f6; }

.button-dark{
    padding:9px 18px;
    background:linear-gradient(120deg,#9d3a5d,#6e2340);
    color:#fff;
}

/* สรุปยอด */
.summary{
    margin-top:22px;
    padding-top:18px;
    border-top:1px dashed #ffc5d9;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
}
.summary p{ margin:0; color:var(--muted); font-size:14px; }
.summary .total{
    margin-top:4px;
    color:#d51f65;
    font-size:26px;
    font-weight:700;
}

.actions{
    display:flex;
    justify-content:center;
    flex-wrap:wrap;
    gap:12px;
    margin-top:24px;
}

/* ตะกร้าว่าง */
.empty{
    text-align:center;
    padding:42px 15px;
}
.empty-icon{ font-size:58px; }
.empty h3{ color:#d51f65; margin:10px 0 6px; }
.empty p{ color:var(--muted); margin:0; }

/* Footer */
.cart-footer{
    background:linear-gradient(100deg,#ffe3ee,#fff5f9,#ffd2e3);
    text-align:center;
    color:#a34b70;
    padding:22px 12px;
    font-size:13px;
}
.cart-footer strong{
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

    .cart-hero{ padding:32px 5%; }
    .cart-hero p{ font-size:13px; }

    main{ padding-top:27px; }
    .panel{ padding:12px; }

    .summary{
        flex-direction:column;
        align-items:stretch;
        text-align:center;
    }
    .actions .button{ width:100%; }
}

@media(prefers-reduced-motion:reduce){
    .cart-petal{ animation:none!important; }
}

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
.cart-hero{min-height:400px;padding-top:125px}
@media (max-width:1000px){
    .header{position:relative;top:auto;left:auto;padding:12px 4%;background:rgba(0,0,0,.65)}
    .header-inner{flex-wrap:wrap;justify-content:center}
    .account{margin-left:auto}
    .nav-strip{position:sticky;top:0;left:auto;width:100%;max-width:none;transform:none;background:rgba(0,0,0,.78)}
    .nav-wrap{width:100%;max-width:none;border:0;border-top:1px solid rgba(255,105,160,.9);border-bottom:1px solid rgba(255,105,160,.9);border-radius:0;background:transparent;box-shadow:none}
    .nav-wrap .nav{justify-content:flex-start;padding:7px 10px}
    .cart-hero{min-height:315px;padding:35px 6%}
}
@media (max-width:600px){
    .header{padding:14px 5%;background:rgba(0,0,0,.65)}
    .header-inner{justify-content:center}
    .brand-name{font-size:19px}
    .brand-name small{font-size:8px;letter-spacing:1.5px}
    .account{justify-content:center;margin:0}
    .nav-wrap .nav a{padding:9px 12px;font-size:12px}
    .cart-hero{padding:35px 5%}
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

            <?php if ($isLoggedIn): ?>

                <span class="welcome">
                    สวัสดี, <?= h($firstname) ?> 💗
                </span>

                <a href="logout.php">ออกจากระบบ</a>

            <?php else: ?>

                <a href="login.php">เข้าสู่ระบบ</a>
                <a href="register.php">สมัครสมาชิก</a>

            <?php endif; ?>


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
           class="<?= $currentPage === 'my_orders.php' ? 'active' : '' ?>">
            📋 ประวัติการสั่งซื้อ
        </a>

        <a href="cart.php"
           class="cart-link <?= $currentPage === 'cart.php' ? 'active' : '' ?>"
           aria-current="page">
            🛒 ตะกร้า (<span class="cart-count"><?= (int)$cartCount ?></span>)
        </a>

    </nav>
  </div>
</div>

<!-- Hero -->
<section class="cart-hero" aria-label="Cart welcome">
    <span class="cart-petal one" aria-hidden="true">✿</span>
    <span class="cart-petal two" aria-hidden="true">♡</span>
    <div class="cart-kicker">YOUR SWEET BASKET</div>
    <h1>My <span>Cart ♡</span></h1>
    <p>ตรวจสอบสินค้าที่คุณเลือกก่อนไปชำระเงินนะคะ 🍓</p>
</section>

<main>

    <div class="section-heading">
        <h2>🛒 สินค้าในตะกร้า</h2>
        <p>แก้ไขจำนวนหรือลบรายการที่ไม่ต้องการได้ที่นี่</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="message <?= $messageType === 'error' ? 'error' : '' ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cart)): ?>

        <section class="panel empty">
            <div class="empty-icon">🧁</div>
            <h3>ยังไม่มีสินค้าในตะกร้า</h3>
            <p>เลือกเค้กและเครื่องดื่มที่คุณชอบได้เลย 💗</p>

            <div class="actions">
                <a class="button button-primary" href="cake.php">🍰 เลือกซื้อเค้ก</a>
                <a class="button button-light" href="drink.php">🥤 เลือกซื้อเครื่องดื่ม</a>
            </div>
        </section>

    <?php else: ?>

        <section class="panel">
            <form method="post" action="cart.php">
                <input type="hidden" name="action" value="update">

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>สินค้า</th>
                                <th>ราคา/ชิ้น</th>
                                <th>จำนวน</th>
                                <th>รวม</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($cart as $key => $item): ?>
                            <?php
                                $price = (float)($item['price'] ?? 0);
                                $quantity = (int)$item['quantity'];
                                $subtotal = $price * $quantity;
                                $image = trim((string)($item['image'] ?? ''));
                                $productName = $item['product_name'] ?? $item['name'] ?? 'สินค้า';
                                $type = $item['item_type'] ?? '';
                            ?>

                            <tr>
                                <td>
                                    <?php if ($image !== ''): ?>
                                        <img class="product-image"
                                             src="<?= h($image) ?>"
                                             alt="<?= h($productName) ?>"
                                             onerror="this.style.display='none';">
                                    <?php endif; ?>

                                    <strong class="product-name"><?= h($productName) ?></strong>

                                    <br>

                                    <span class="type-label">
                                        <?= $type === 'cake' ? '🍰 เค้ก' : '🥤 เครื่องดื่ม' ?>
                                    </span>
                                </td>

                                <td>฿<?= number_format($price, 2) ?></td>

<td>
    <div class="qty-box">
        <button type="button" class="qty-btn" data-action="minus" aria-label="ลดจำนวน">−</button>
        <input
            class="quantity-input"
            type="number"
            name="quantity[<?= h($key) ?>]"
            value="<?= $quantity ?>"
            min="1"
            required
        >
        <button type="button" class="qty-btn" data-action="plus" aria-label="เพิ่มจำนวน">+</button>
    </div>
</td>

                                <td class="subtotal">
                                    ฿<?= number_format($subtotal, 2) ?>
                                </td>

                                <td>
                                <button
                                    type="submit"
                                    class="button button-dark"
                                    form="remove-<?= h($key) ?>"
                                    onclick="return confirm('ต้องการลบสินค้านี้ออกจากตะกร้าหรือไม่?');"
                                >
                                    ลบ
                                </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="summary">
                    <div>
                        <p>จำนวนทั้งหมด <?= (int)$totalQuantity ?> ชิ้น</p>
                        <p class="total">ยอดรวม ฿<?= number_format($total, 2) ?></p>
                    </div>

                    <button type="submit" class="button button-light">
                        อัปเดตจำนวนสินค้า
                    </button>
                </div>
            </form>
        </section>

        <?php foreach ($cart as $key => $item): ?>
            <form
                id="remove-<?= h($key) ?>"
                method="post"
                action="cart.php"
                style="display:none;"
            >
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= h($key) ?>">
            </form>
        <?php endforeach; ?>

        <div class="actions">
            <a href="cake.php" class="button button-light">+ เลือกซื้อเค้กเพิ่ม</a>
            <a href="drink.php" class="button button-light">+ เลือกซื้อเครื่องดื่มเพิ่ม</a>
            <a href="order.php" class="button button-primary">ดำเนินการต่อ →</a>
        </div>

    <?php endif; ?>
</main>

<footer class="cart-footer">
    <strong>♡ Klong 6 Cafe ♡</strong>
    <div>Good Coffee&nbsp; • &nbsp;Sweet Drinks&nbsp; • &nbsp;Delicious Cake&nbsp; • &nbsp;Happy Time</div>
</footer>

<script>
function removeProduct(key) {
    if (confirm('ต้องการลบสินค้านี้ออกจากตะกร้าหรือไม่?')) {
        const form = document.getElementById('remove-' + key);
        if (form) {
            form.submit();
        }
    }
}

document.querySelectorAll('.qty-box').forEach(function (box) {
    var input = box.querySelector('.quantity-input');

    box.querySelectorAll('.qty-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var val = parseInt(input.value, 10) || 1;

            if (btn.dataset.action === 'plus') val++;
            if (btn.dataset.action === 'minus' && val > 1) val--;

            input.value = val;
        });
    });
});

</script>

</body>
</html>

<?php
$conn->close();
?>