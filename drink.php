<?php
session_start();
require_once 'conn.php';

// ========================================
// 1. ตั้งค่าพื้นฐาน
// ========================================
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$firstname = $_SESSION['customer_firstname'] ?? '';
$isLoggedIn = !empty($_SESSION['customer_id']);
$currentPage = basename($_SERVER['SCRIPT_NAME']);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

function getCartCount() {
    $count = 0;

    foreach ($_SESSION['cart'] ?? [] as $item) {
        if (is_array($item)) {
            $count += max(0, (int)($item['quantity'] ?? 0));
        }
    }

    return $count;
}

// ========================================
// 2. AJAX เพิ่มเครื่องดื่มลงตะกร้า
// ========================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'add_to_cart'
) {
    header('Content-Type: application/json; charset=utf-8');

    $drinkId = trim((string)($_POST['drink_id'] ?? ''));

    if ($drinkId === '' || strlen($drinkId) > 50) {
        echo json_encode([
            'success' => false,
            'message' => 'รหัสเครื่องดื่มไม่ถูกต้อง'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $conn->prepare(
            "SELECT drink_id, drink_name, description,
                    price, stock, image, status
             FROM drink
             WHERE drink_id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param('s', $drinkId);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $result = $stmt->get_result();
        $drink = $result->fetch_assoc();
        $stmt->close();

        if (!$drink) {
            echo json_encode([
                'success' => false,
                'message' => 'ไม่พบเครื่องดื่มรหัส ' . $drinkId
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stock = (int)$drink['stock'];
        $status = strtolower(trim((string)$drink['status']));

        if ($status !== 'available' || $stock <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'เครื่องดื่มรายการนี้หมดหรือยังไม่พร้อมจำหน่ายค่ะ'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $drinkIdFromDB = (string)$drink['drink_id'];
        $cartKey = 'drink_' . $drinkIdFromDB;

        $oldItem = $_SESSION['cart'][$cartKey] ?? [];

        $oldQuantity = is_array($oldItem)
            ? max(0, (int)($oldItem['quantity'] ?? 0))
            : 0;

        if ($oldQuantity >= $stock) {
            echo json_encode([
                'success' => false,
                'message' => 'จำนวนเครื่องดื่มในตะกร้าถึงขีดจำกัดสต็อกแล้วค่ะ'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $newQuantity = $oldQuantity + 1;

        $_SESSION['cart'][$cartKey] = [
            'item_type'    => 'drink',
            'item_id'      => $drinkIdFromDB,
            'product_id'   => $drinkIdFromDB,
            'product_name' => $drink['drink_name'],
            'name'         => $drink['drink_name'],
            'price'        => (float)$drink['price'],
            'image'        => $drink['image'] ?? '',
            'quantity'     => $newQuantity
        ];

        echo json_encode([
            'success' => true,
            'message' => 'เพิ่ม ' . $drink['drink_name'] . ' ลงตะกร้าแล้ว 💗',
            'cart_count' => getCartCount(),
            'drink_id' => $drinkIdFromDB,
            'quantity' => $newQuantity
        ], JSON_UNESCAPED_UNICODE);

        exit;

    } catch (Throwable $error) {
        error_log('Drink add to cart error: ' . $error->getMessage());

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการเพิ่มสินค้า กรุณาตรวจสอบฐานข้อมูลค่ะ'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}

// ========================================
// 3. จำนวนสินค้าในตะกร้า
// ========================================
$cartCount = getCartCount();

// ========================================
// 4. ดึงข้อมูลเครื่องดื่ม
// ========================================
$products = [];
$dbError = '';

$sql = "SELECT drink_id, drink_name, description,
               price, stock, image, status, created_at
        FROM drink
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $result->free();
} else {
    $dbError = $conn->error;
}

// ========================================
// 5. แบ่งหน้า หน้าละ 10 รายการ
// ========================================
$perPage = 10;
$totalProducts = count($products);
$totalPages = max(1, (int)ceil($totalProducts / $perPage));

$pageNumber = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT
);

if ($pageNumber === false || $pageNumber < 1) {
    $pageNumber = 1;
}

$pageNumber = min($pageNumber, $totalPages);

$products = array_slice(
    $products,
    ($pageNumber - 1) * $perPage,
    $perPage
);

// ========================================
// 6. ข้อความแจ้งเตือน
// ========================================
$message = $_SESSION['cart_message'] ?? '';
$messageType = $_SESSION['cart_message_type'] ?? 'success';

unset(
    $_SESSION['cart_message'],
    $_SESSION['cart_message_type']
);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Drink Menu | KLONG 6 CAFE</title>

<link
    href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&family=Pacifico&display=swap"
    rel="stylesheet"
>

<style>
:root {
    --pink: #f8dce4;
    --pink-light: #fff5f7;
    --red: #b72e4b;
    --red-dark: #8f2039;
    --cream: #fffaf5;
    --text: #56333c;
    --muted: #896872;
    --border: #f3dce2;
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: 'Mali', cursive;
    color: var(--text);
    background: var(--cream);
}

a {
    color: inherit;
    text-decoration: none;
}

button,
input {
    font-family: inherit;
}

.announcement {
    background: var(--red);
    color: white;
    text-align: center;
    padding: 9px 12px;
    font-size: 12px;
}

.header {
    background: linear-gradient(135deg, #fff2f5, var(--pink));
    padding: 20px 5%;
}

.header-inner {
    max-width: 1200px;
    margin: auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.brand {
    color: var(--red);
    font-size: 25px;
    font-weight: 700;
}

.brand small {
    display: block;
    color: var(--text);
    font-size: 10px;
    letter-spacing: 2px;
}

.account {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 12px;
}

.account a {
    padding: 9px 14px;
    border: 1px solid #efd0d9;
    border-radius: 30px;
    background: white;
    color: var(--red);
}

.account a.cart-link {
    background: var(--red);
    color: white;
    border-color: var(--red);
}

.welcome {
    font-weight: 700;
}

.nav a.active,
.nav a:hover {
    background: var(--red);
    color: white;
}

.hero {
    text-align: center;
    padding: 48px 18px;
    background:
        radial-gradient(circle at 85% 15%, #ffe0e8 0, transparent 30%),
        linear-gradient(135deg, #fff5f7, #f9e3e9);
}

.tag {
    display: inline-block;
    background: white;
    color: var(--red);
    border-radius: 30px;
    padding: 7px 16px;
    font-size: 12px;
}

.hero h1 {
    color: var(--red-dark);
    font-size: clamp(28px, 4vw, 40px);
    margin: 14px 0 8px;
}

.hero p {
    margin: 8px 0;
    font-size: 14px;
    line-height: 1.9;
}

.cart-summary {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 18px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 30px;
    color: var(--red);
    font-size: 13px;
    font-weight: 700;
}

.notice {
    width: 92%;
    max-width: 1100px;
    margin: 18px auto 0;
    padding: 13px;
    border-radius: 14px;
    text-align: center;
    background: #e8f6e9;
    color: #267348;
    font-size: 13px;
}

.notice.error {
    background: #fff0f2;
    color: var(--red);
}

.notice a {
    text-decoration: underline;
    font-weight: 700;
}

/* Menu */
.menu-section {
    width: 92%;
    max-width: 1250px;
    margin: 40px auto 60px;
}

.section-heading {
    text-align: center;
    margin-bottom: 25px;
}

.section-heading h2 {
    color: var(--red-dark);
    font-size: 27px;
    margin: 0 0 8px;
}

.section-heading p {
    color: var(--muted);
    font-size: 13px;
}

.search-box {
    display: block;
    width: min(420px, 100%);
    margin: 0 auto 28px;
    padding: 12px 18px;
    border: 1px solid #edc3cf;
    border-radius: 30px;
    outline: none;
    font-size: 13px;
    background: white;
}

.search-box:focus {
    border-color: var(--red);
    box-shadow: 0 0 0 3px rgba(183, 46, 75, .08);
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16px;
    align-items: stretch;
}

.product-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(120, 55, 70, .06);
    transition: transform .25s ease, box-shadow .25s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 22px rgba(120, 55, 70, .12);
}

.product-image {
    display: block;
    width: 100%;
    height: 180px;
    object-fit: cover;
    background: var(--pink-light);
}

.product-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 14px;
}

.product-info h3 {
    margin: 0 0 8px;
    color: var(--red-dark);
    font-size: 14px;
    overflow-wrap: anywhere;
}

.description {
    margin: 0;
    color: #80636a;
    font-size: 11px;
    line-height: 1.8;
    overflow-wrap: anywhere;
    flex: 1;
}

.product-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #edc3cf;
}

.price {
    color: var(--red);
    font-size: 18px;
    font-weight: 700;
}

.stock {
    color: var(--muted);
    font-size: 10px;
    margin-top: 4px;
}

.add-cart-form {
    display: flex;
    justify-content: flex-end;
    margin: 0;
}

.plus-button {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    border: none;
    border-radius: 50%;
    background: var(--red);
    color: white;
    font: 30px Arial, sans-serif;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(183, 46, 75, .18);
    transition: .2s ease;
}

.plus-button:hover:not(:disabled) {
    background: var(--red-dark);
    transform: scale(1.08);
}

.plus-button:disabled {
    background: #aaa;
    cursor: not-allowed;
    box-shadow: none;
}

.empty-message {
    text-align: center;
    padding: 25px 15px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 18px;
    font-size: 13px;
    line-height: 1.9;
    overflow-wrap: anywhere;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 30px;
}

.pagination a {
    min-width: 38px;
    padding: 8px 12px;
    text-align: center;
    background: white;
    color: var(--red);
    border: 1px solid #edc3cf;
    border-radius: 12px;
    font-size: 12px;
}

.pagination a.active,
.pagination a:hover {
    background: var(--red);
    color: white;
}

.orders-footer {
    background: #451f2b;
    text-align: center;
    color: #fbe8ed;
    padding: 22px 12px;
    font-size: 13px;
}

.orders-footer strong {
    font-family: 'Pacifico','Mali',cursive;
    color: white;
    font-size: 23px;
}

@media (max-width: 1100px) {
    .product-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 800px) {
    .product-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .product-image {
        height: 155px;
    }
}

@media (max-width: 550px) {
    .header-inner {
        justify-content: center;
        text-align: center;
    }

    .nav {
        gap: 4px;
        padding: 10px 8px;
    }

    .nav a {
        padding: 9px 11px;
        font-size: 11px;
    }

    .product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .product-image {
        height: 125px;
    }

    .product-info {
        padding: 10px;
    }

    .hero {
        padding: 35px 15px;
    }

    .menu-section {
        width: 94%;
    }
}
</style>

<link rel="stylesheet" href="cafe-theme.css">

<style>
/* ===== Navigation เหมือน cake.php ===== */
.nav-wrap {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255,248,251,.92);
    backdrop-filter: blur(14px);
    box-shadow: 0 6px 20px rgba(184,42,91,.08);
    border-bottom: 1px solid #ffd4e3;
}

.nav {
    max-width: 1320px;
    margin: auto;
    padding: 10px 4%;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.nav a {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 10px 17px;
    border-radius: 28px;
    color: #a72c5c;
    background: transparent;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    transition: background .2s ease, color .2s ease,
                box-shadow .2s ease, transform .2s ease;
}

.nav a:hover,
.nav a.active {
    background: linear-gradient(120deg,#ff80ad,#ed2674);
    color: #fff;
    box-shadow: 0 6px 16px rgba(229,44,116,.23);
}

.nav a:hover {
    transform: translateY(-1px);
}

.search-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: min(520px,100%);
    margin: 22px auto 25px;
}

.search-controls .search-box {
    flex: 1;
    min-width: 0;
    width: auto;
    margin: 0;
}

.search-button {
    flex-shrink: 0;
    padding: 13px 20px;
    border: 0;
    border-radius: 30px;
    background: linear-gradient(120deg,#ff6da3,#ed176b);
    color: #fff;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 6px 15px #e52c7430;
    transition: transform .2s, box-shadow .2s;
}

.search-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px #e52c7440;
}

/* ===== Pink Garden Theme ===== */
:root {
    --pink: #ffb6d0;
    --pink-light: #fff4f8;
    --red: #ed2674;
    --red-dark: #bd1555;
    --cream: #fff8fb;
    --text: #812349;
    --muted: #a85d7c;
    --border: #ffd0e0;
}

body {
    font-family: 'Mali', sans-serif;
    background: #fff8fb;
    color: #812349;
    background-image:
        radial-gradient(ellipse at 10% 35%,#ffd9e7 0,transparent 28%),
        radial-gradient(ellipse at 90% 72%,#ffe2ed 0,transparent 25%);
}

.announcement {
    background: linear-gradient(90deg,#ff7cab,#ed2674,#ff8db7);
    color: #fff;
    font-weight: 600;
    letter-spacing: .4px;
}

.header {
    background: linear-gradient(115deg,#ff8db7,#ffbad2 55%,#ffe6ef);
    padding: 17px 5%;
    box-shadow: 0 8px 25px #d72c6d16;
}

.header-inner {
    max-width: 1320px;
}

.brand {
    color: #fff;
    font-size: clamp(22px,2.5vw,31px);
    text-shadow: 0 2px 12px #b81e5d55;
}

.brand small {
    color: #fff8fb;
    letter-spacing: 2px;
}

.account a {
    border: 1px solid #ffffff9c;
    background: #ffffffdc;
    color: #d21f65;
    box-shadow: 0 4px 12px #b81e5d13;
}

.account a.cart-link {
    background: linear-gradient(120deg,#ff5c9b,#e91d6d);
    border-color: transparent;
    color: #fff;
}

.welcome {
    color: #fff;
}

/* ===== Hero: ใช้พื้นหลังเดียวกับ cake.php ===== */
.nav-wrap {
    position: static;
    top: auto;
    background: transparent;
    backdrop-filter: none;
    box-shadow: none;
    border-bottom: 0;
}

.nav {
    max-width: 1320px;
    margin: auto;
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 10px 4%;
    flex-wrap: wrap;
}

.nav a {
    border-radius: 28px;
    padding: 10px 17px;
    color: #a72c5c;
    font-weight: 600;
    transition: .2s;
    background: rgba(255,255,255,.55);
}

.nav a:hover,
.nav a.active {
    background: linear-gradient(120deg,#ff80ad,#ed2674);
    color: white;
    box-shadow: 0 6px 16px #e52c743b;
}

/*
 * ใช้ class cake-hero และโครงสร้างเดิม
 * เพื่อให้พื้นหลังและขนาดตรงกับ cake.php
 */
.cake-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    min-height: 315px;
    padding: 44px 6%;
    display: grid;
    grid-template-columns: 1.05fr .95fr;
    align-items: center;
    gap: 20px;
    background:
        linear-gradient(100deg,#e94a86d9,#ff9fc2b0 52%,#ffc9dcb0),
        url('น้ำ.jpg') center 48%/cover no-repeat;
}

.cake-hero:before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -1;
    background: linear-gradient(90deg,#c92d6b55,#fff0f61c);
}

.cake-hero-copy {
    max-width: 650px;
    color: white;
    text-shadow: 0 2px 12px #9f164f55;
}

.cake-kicker {
    font-size: 12px;
    letter-spacing: 2px;
    font-weight: 700;
}

.cake-hero h1 {
    font-size: clamp(38px,5vw,65px);
    line-height: 1.12;
    margin: 8px 0;
    color: white;
}

.cake-hero h1 span {
    font-family: 'Pacifico','Mali',cursive;
    color: #fff3f8;
}

.cake-hero p {
    line-height: 1.8;
    font-size: 15px;
    max-width: 500px;
}

.cake-hero .hero-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.cake-hero .hero-buttons a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 20px;
    border-radius: 30px;
    background: linear-gradient(120deg,#ff5c9b,#ed176b);
    color: white;
    font-weight: 700;
    box-shadow: 0 8px 22px #a81d5540;
}

.cake-hero .hero-buttons a.secondary {
    background: #ffffff30;
    border: 1px solid #ffffffad;
    box-shadow: none;
}

.cake-hero-art {
    position: relative;
    min-height: 230px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cake-hero-art img {
    width: min(60%,290px);
    height: 245px;
    object-fit: cover;
    border: 7px solid #ffffffb9;
    border-radius: 44% 44% 28% 28%;
    box-shadow: 0 20px 45px #8b174c55;
    transform: rotate(5deg);
    animation: cakeFloat 5s ease-in-out infinite;
}

.cake-hero-art .cake-decoration {
    position: absolute;
    right: 7%;
    top: 5%;
    font-size: 45px;
    filter: drop-shadow(0 6px 8px #9e164b45);
    animation: cakeFloat 4s ease-in-out infinite reverse;
}

.cake-petal {
    position: absolute;
    color: #fff;
    font-size: 25px;
    opacity: .8;
    animation: cakeFloat 5s ease-in-out infinite;
}

.cake-petal.one {
    left: 7%;
    top: 15%;
}

.cake-petal.two {
    right: 45%;
    bottom: 10%;
    animation-delay: -2s;
}

/* ===== เมนูสินค้าเหมือน cake.php ===== */
.menu-section {
    width: min(1240px,92%);
    margin: 0 auto;
    padding: 34px 0 48px;
}

.section-heading {
    text-align: center;
    margin-bottom: 20px;
}

.section-heading h2 {
    font-size: clamp(28px,3vw,39px);
    color: #d51f65;
    margin: 0 0 5px;
}

.section-heading p {
    color: #a85d7c;
    font-size: 14px;
    margin: 0;
}

.search-box {
    display: block;
    width: min(520px,100%);
    padding: 14px 18px;
    border: 1px solid #ffc5d9;
    border-radius: 30px;
    background: #fff;
    outline: none;
    color: #812349;
    box-shadow: 0 7px 22px #dc4c8110;
    margin: 22px 0 25px;
}

.search-box:focus {
    border-color: #f04486;
    box-shadow: 0 0 0 4px #ffb6d033;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: 20px;
}

.product-card {
    overflow: hidden;
    border: 1px solid #ffd0e0 !important;
    border-radius: 23px !important;
    background: linear-gradient(145deg,#fff,#fff0f6) !important;
    box-shadow: 0 10px 28px #ce397219 !important;
    transition: transform .25s,box-shadow .25s;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 17px 34px #ce39722c !important;
}

.product-image {
    width: 100%;
    height: 230px !important;
    object-fit: cover;
    background: #ffe5ef;
}

.product-info {
    padding: 18px !important;
}

.product-info h3,
.product-name {
    color: #d51f65 !important;
    font-size: 20px;
}

.description {
    color: #a85d7c;
    line-height: 1.75;
}

.product-bottom {
    border-top: 1px solid #ffd8e5;
    padding-top: 13px;
    margin-top: 12px;
}

.price {
    color: #d51f65 !important;
    font-weight: 700;
    font-size: 20px;
}

.stock {
    color: #a85d7c !important;
}

.add-cart-form button,
.plus-button,
.pagination a,
.pagination span {
    border-radius: 25px !important;
}

.add-cart-form button,
.plus-button {
    background: linear-gradient(120deg,#ff6da3,#ed176b) !important;
    color: #fff !important;
    border: 0 !important;
    box-shadow: 0 6px 15px #e52c7430;
}

.plus-button:disabled {
    background: #aaa !important;
    cursor: not-allowed;
}

.notice {
    width: min(1100px,92%);
    margin: 20px auto;
    background: #ffe3ee;
    color: #b71958;
    border: 1px solid #ffc6da;
    border-radius: 16px;
    padding: 14px 18px;
}

.notice.error {
    background: #fff0f0;
    color: #a72b45;
}

.empty-message {
    border-radius: 20px;
    background: #fff;
    border: 1px dashed #f4a9c6;
    padding: 25px;
    color: #a85d7c;
}

.pagination a,
.pagination .active {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    padding: 9px 13px;
    margin: 3px;
    background: #fff;
    border: 1px solid #ffd0e0;
    color: #c91e60;
}

.pagination .active {
    background: #ed2674;
    color: #fff;
}

.cake-footer {
    background: linear-gradient(100deg,#ffe3ee,#fff5f9,#ffd2e3);
    text-align: center;
    color: #a34b70;
    padding: 22px 12px;
    font-size: 13px;
}

.cake-footer strong {
    font-family: 'Pacifico','Mali',cursive;
    color: #d51f65;
    font-size: 23px;
}

@keyframes cakeFloat {
    0%,100% {
        transform: translateY(0) rotate(4deg);
    }

    50% {
        transform: translateY(-12px) rotate(-2deg);
    }
}

/* Cursor */
html,
body,
body * {
    cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Cpath d='M7 3 Q5 2 5 6 L5 26 Q5 30 9 27 L14 21 L18 29 Q20 32 23 30 L24 28 L19 20 L27 20 Q31 20 28 17 Z' fill='%23e83e79' stroke='%23a91e50' stroke-width='1.5'/%3E%3C/svg%3E") 5 4,auto !important;
}

a,
button,
input[type="submit"],
input[type="button"],
[role="button"],
.btn {
    cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='36' height='36' viewBox='0 0 36 36'%3E%3Ctext x='2' y='29' font-size='29'%3E%F0%9F%A5%A4%3C/text%3E%3C/svg%3E") 4 4,pointer !important;
}

/* Navigation แบบ Glass */
.nav-strip {
    position: sticky;
    top: 0;
    z-index: 100;
    padding: 10px 4%;
    background: linear-gradient(115deg,#ff8db7,#ff9fc2 55%,#ffb3cc);
}

.nav-wrap {
    position: static;
    width: fit-content;
    max-width: 100%;
    margin: 0 auto;
    background: rgba(255,255,255,.22);
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 999px;
    box-shadow: 0 6px 20px rgba(184,42,91,.15);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.nav-wrap .nav {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    padding: 6px 10px;
    margin: 0 auto;
    background: transparent;
    border: 0;
    box-shadow: none;
}

.nav-wrap .nav a {
    padding: 9px 17px;
    border-radius: 999px;
    background: transparent;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    text-shadow: 0 1px 6px rgba(150,25,75,.35);
    white-space: nowrap;
    transition: background .2s,transform .2s;
}

.nav-wrap .nav a:hover {
    background: rgba(255,255,255,.28);
    transform: translateY(-1px);
}

.nav-wrap .nav a.active {
    background: linear-gradient(120deg,#ff5c9b,#ed2674);
    box-shadow: 0 5px 14px rgba(229,44,116,.35);
}

/* Responsive เหมือน cake.php */
@media(max-width:850px) {
    .cake-hero {
        grid-template-columns: 1fr;
        text-align: center;
        padding: 35px 6%;
    }

    .cake-hero-copy {
        margin: auto;
    }

    .cake-hero p {
        margin-left: auto;
        margin-right: auto;
    }

    .cake-hero .hero-buttons {
        justify-content: center;
    }

    .cake-hero-art {
        min-height: 210px;
    }

    .cake-hero-art img {
        height: 205px;
    }

    .product-grid {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }

    .product-image {
        height: 200px !important;
    }
}

@media(max-width:540px) {
    .header {
        padding: 15px 4%;
    }

    .header-inner {
        justify-content: center;
        text-align: center;
    }

    .brand {
        width: 100%;
    }

    .account {
        gap: 6px;
    }

    .nav-wrap {
        border-radius: 24px;
    }

    .nav-wrap .nav {
        justify-content: flex-start;
        flex-wrap: nowrap;
        overflow-x: auto;
    }

    .nav-wrap .nav a {
        flex-shrink: 0;
        padding: 8px 12px;
        font-size: 12px;
    }

    .cake-hero {
        padding: 30px 5%;
    }

    .cake-hero h1 {
        font-size: 42px;
    }

    .cake-hero p {
        font-size: 13px;
    }

    .cake-hero-art {
        min-height: 180px;
    }

    .cake-hero-art img {
        height: 175px;
        width: 54%;
    }

    .cake-hero-art .cake-decoration {
        font-size: 33px;
    }

    .menu-section {
        width: 92%;
        padding-top: 27px;
    }

    .product-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .product-card {
        display: grid;
        grid-template-columns: 40% 60%;
        align-items: stretch;
    }

    .product-image {
        height: 100% !important;
        min-height: 190px;
    }

    .product-info {
        padding: 13px !important;
        min-width: 0;
    }

    .product-info h3,
    .product-name {
        font-size: 17px;
    }

    .description {
        font-size: 12px;
    }

    .price {
        font-size: 17px;
    }

    .search-controls {
        gap: 7px;
    }

    .search-button {
        padding: 12px 14px;
    }
}

@media(prefers-reduced-motion:reduce) {
    .cake-hero-art img,
    .cake-decoration,
    .cake-petal {
        animation: none !important;
    }
}

.announcement{
    display:none
}

.header{
    position:absolute;
    top:0;
    left:0;
    z-index:10;
    width:100%;
    padding:17px 5%;
    background:linear-gradient(180deg,rgba(0,0,0,.5),transparent);
    box-shadow:none
}

.header-inner{
    max-width:1320px;
    flex-wrap:nowrap
}

.brand{
    display:flex;
    align-items:center;
    gap:10px;
    color:#fff;
    text-decoration:none
}

.brand-mark{
    font-size:29px
}

.brand-name{
    font-size:clamp(22px,2.5vw,31px);
    font-weight:700;
    line-height:1.2;
    text-shadow:0 2px 10px #000
}

.brand-name small{
    display:block;
    margin-top:3px;
    color:#fff;
    font-size:10px;
    letter-spacing:2px
}

.welcome{
    color:#fff;
    text-shadow:0 2px 8px #000
}

.account a{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:10px 17px;
    border:1px solid rgba(255,105,160,.9);
    border-radius:4px;
    background:rgba(255,182,205,.32);
    color:#fff;
    font-size:13px;
    text-shadow:0 1px 5px rgba(80,20,45,.65);
    backdrop-filter:blur(8px)
}

.account a[href="register.php"]{
    background:#ed367f
}

.nav-strip{
    position:absolute;
    top:19px;
    left:50%;
    z-index:11;
    width:max-content;
    max-width:70%;
    padding:0;
    transform:translateX(-50%);
    background:transparent
}

.nav-wrap{
    position:static;
    width:fit-content;
    max-width:100%;
    margin:0;
    border:1px solid rgba(255,105,160,.9);
    border-radius:40px;
    background:rgba(0,0,0,.22);
    box-shadow:0 8px 25px rgba(0,0,0,.12);
    backdrop-filter:blur(8px)
}
.nav-wrap .nav{
    max-width:1320px;
    justify-content:center;
    flex-wrap:nowrap;
    gap:4px;
    padding:4px 7px;
    overflow-x:auto
}

.nav-wrap .nav a{
    flex:0 0 auto;
    padding:9px 13px;
    border-radius:30px;
    background:transparent;
    color:#fff;
    font-size:13px;
    text-shadow:0 1px 6px rgba(0,0,0,.45)
}

.nav-wrap .nav a:hover,.nav-wrap .nav a.active{
    background:#ed367f;
    color:#fff;
    box-shadow:none
}

@media (min-width:1001px){.cake-hero{min-height:400px;padding-top:125px}}
@media (max-width:1000px){
    .header{
        position:relative;
        top:auto;
        left:auto;
        padding:12px 4%;
        background:rgba(0,0,0,.65)
    }

    .header-inner{
        flex-wrap:wrap;
        justify-content:center
    }

    .account{
        margin-left:auto
    }

    .nav-strip{
        position:sticky;
        top:0;
        left:auto;
        width:100%;
        max-width:none;
        transform:none;
        background:rgba(0,0,0,.78)
    }

    .nav-wrap{
        width:100%;
        max-width:none;
        border:0;
        border-top:1px solid rgba(255,105,160,.9);
        border-bottom:1px solid rgba(255,105,160,.9);
        border-radius:0;
        background:transparent;
        box-shadow:none
    }

    .nav-wrap .nav{
        justify-content:flex-start;
        padding:7px 10px
    }

    .cake-hero{
        min-height:315px;padding-top:35px
    }

}
@media (max-width:600px){
    .header{
        padding:14px 5%;
        background:rgba(0,0,0,.65)
    }

    .header-inner{
        justify-content:center
    }

    .brand-name{
        font-size:19px
    }

    .brand-name small{
        font-size:8px;
        letter-spacing:1.5px
    }

    .account{
        justify-content:center;
        margin:0
    }

    .nav-wrap .nav a{
        padding:9px 12px;
        font-size:12px
    }
    
}

:root {
    --cream: #fff0f5;
}

body {
    background: var(--cream);
    background-image: none;
}

.cake-hero {
    background: url('น้ำ.jpg') center 58% / cover no-repeat !important;
    background-position: center calc(58% - var(--scroll-shift, 0px)) !important;
}

.cake-hero::before {
    content: none !important;
}

.cake-hero::after {
    content: "";
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 0;
    height: 110px;
    background: linear-gradient(to bottom, rgba(255, 240, 245, 0), var(--cream));
    pointer-events: none;
}

.cake-hero-copy,
.cake-hero-art,
.cake-petal {
    z-index: 1;
}
</style>
</head>

<body>

<!-- Announcement -->
<div class="announcement">
    ♡ Fresh drinks made with love — ความสดชื่นจาก KLONG 6 CAFE ♡
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
                    สวัสดี, <?= e($firstname) ?> 💗
                </span>

                <a href="logout.php">ออกจากระบบ</a>

            <?php else: ?>

                <a href="login.php">เข้าสู่ระบบ</a>
                <a href="register.php">สมัครสมาชิก</a>

            <?php endif; ?>


        </div>
    </div>
</header>

<!-- Navigation -->
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
               class="<?= $currentPage === 'drink.php' ? 'active' : '' ?>"
               <?= $currentPage === 'drink.php' ? 'aria-current="page"' : '' ?>>
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

            <a href="cart.php" class="cart-link">
                🛒 ตะกร้า
                (<span class="cart-count"><?= (int)$cartCount ?></span>)
            </a>

        </nav>
    </div>
</div>

<!-- Hero: ใช้พื้นหลังเดียวกับ cake.php -->
<section class="cake-hero" aria-label="Drink menu welcome">

    <span class="cake-petal one" aria-hidden="true">✿</span>
    <span class="cake-petal two" aria-hidden="true">♡</span>

    <div class="cake-hero-copy">

        <div class="cake-kicker">
            A LITTLE FRESHNESS, A LOT OF LOVE
        </div>

        <h1>
            Fresh moments<br>
            <span>with Drinks ♡</span>
        </h1>

        <p>
            เครื่องดื่มแก้วโปรด สดชื่น หอมอร่อย
            เติมความสุขให้ทุกวันของคุณจาก KLONG 6 CAFE 🥤
        </p>

        <div class="hero-buttons">
            <a href="#menu">🥤 เลือกเครื่องดื่มแก้วโปรด ↓</a>
        </div>

    </div>

    <div class="cake-hero-art">

        <img
            src="peach blue latte.jpg"
            alt="เครื่องดื่มจาก Klong 6 Cafe"
            onerror="this.onerror=null;this.src='drink/peach blue latte.jpg';"
        >

        <span class="cake-decoration" aria-hidden="true">🥤 ✨</span>

    </div>
</section>

<!-- ข้อความแจ้งเตือน -->
<?php if ($message !== ''): ?>

    <div class="notice <?= $messageType === 'error' ? 'error' : '' ?>">
        <?= e($message) ?>
        · <a href="cart.php">ดูตะกร้าสินค้า</a>
    </div>

<?php endif; ?>

<!-- รายการเครื่องดื่ม -->
<section class="menu-section" id="menu">

    <div class="section-heading">
        <h2>♡ Drink Menu ♡</h2>
        <p>เลือกเครื่องดื่มแก้วโปรดจาก KLONG 6 CAFE</p>
    </div>

    <!-- ค้นหา -->
    <div class="search-controls">

        <input
            type="search"
            class="search-box"
            id="drinkSearch"
            placeholder="🔍 ค้นหาชื่อเครื่องดื่ม..."
            aria-label="ค้นหาชื่อเครื่องดื่ม"
        >

        <button
            type="button"
            class="search-button"
            id="drinkSearchButton"
        >
            ค้นหา
        </button>

    </div>

    <?php if ($dbError !== ''): ?>

        <div class="empty-message">
            ไม่สามารถดึงข้อมูลเครื่องดื่มได้
            <p><?= e($dbError) ?></p>
        </div>

    <?php elseif ($totalProducts === 0): ?>

        <div class="empty-message">
            ยังไม่มีเครื่องดื่มในฐานข้อมูล 🥤
        </div>

    <?php else: ?>

        <div class="product-grid" id="productGrid">

            <?php foreach ($products as $product): ?>

                <?php
                $drinkName = $product['drink_name'] ?? '';
                $drinkStock = (int)($product['stock'] ?? 0);

                $drinkStatus = strtolower(
                    trim((string)($product['status'] ?? ''))
                );

                $isAvailable =
                    $drinkStatus === 'available' && $drinkStock > 0;

                $image = !empty($product['image'])
                    ? $product['image']
                    : 'drink.png';
                ?>

                <article
                    class="product-card"
                    data-name="<?= e($drinkName) ?>"
                >

                    <img
                        class="product-image"
                        src="<?= e($image) ?>"
                        alt="<?= e($drinkName) ?>"
                        loading="lazy"
                        onerror="this.onerror=null;this.src='peach blue latte.jpg';"
                    >

                    <div class="product-info">

                        <h3><?= e($drinkName) ?></h3>

                        <p class="description">
                            <?= e(
                                !empty($product['description'])
                                    ? $product['description']
                                    : 'เครื่องดื่มแสนอร่อยจาก KLONG 6 CAFE 💗'
                            ) ?>
                        </p>

                        <div class="product-bottom">

                            <div>
                                <div class="price">
                                    ฿<?= number_format(
                                        (float)$product['price'],
                                        2
                                    ) ?>
                                </div>

                                <div class="stock">
                                    <?php if ($isAvailable): ?>
                                        เหลือ <?= $drinkStock ?> ชิ้น
                                    <?php else: ?>
                                        สินค้าหมดหรือยังไม่พร้อมจำหน่าย
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($isAvailable): ?>

                                <form
                                    method="post"
                                    action="drink.php"
                                    class="add-cart-form"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="add_to_cart"
                                    >

                                    <input
                                        type="hidden"
                                        name="drink_id"
                                        value="<?= e($product['drink_id']) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="plus-button"
                                        aria-label="เพิ่ม <?= e($drinkName) ?> ลงตะกร้า"
                                        title="เพิ่มลงตะกร้า"
                                    >
                                        +
                                    </button>

                                </form>

                            <?php else: ?>

                                <button
                                    type="button"
                                    class="plus-button"
                                    disabled
                                    title="สินค้าหมดหรือยังไม่พร้อมจำหน่าย"
                                >
                                    +
                                </button>

                            <?php endif; ?>

                        </div>
                    </div>
                </article>

            <?php endforeach; ?>

        </div>

        <p
            id="noResults"
            class="empty-message"
            style="display:none"
        >
            ไม่พบเครื่องดื่มที่ค้นหา 💗
        </p>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>

            <div class="pagination">

                <?php if ($pageNumber > 1): ?>

                    <a href="drink.php?page=<?= $pageNumber - 1 ?>#menu">
                        « ก่อนหน้า
                    </a>

                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                    <a
                        href="drink.php?page=<?= $i ?>#menu"
                        class="<?= $i === $pageNumber ? 'active' : '' ?>"
                    >
                        <?= $i ?>
                    </a>

                <?php endfor; ?>

                <?php if ($pageNumber < $totalPages): ?>

                    <a href="drink.php?page=<?= $pageNumber + 1 ?>#menu">
                        ถัดไป »
                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>

        <p style="text-align:center;font-size:12px;margin-top:18px">
            หน้า <?= $pageNumber ?> จาก <?= $totalPages ?>
            · เครื่องดื่มทั้งหมด <?= $totalProducts ?> รายการ
        </p>

    <?php endif; ?>

</section>

<!-- Footer -->
<footer class="orders-footer">
    <strong>♡ Klong 6 Cafe ♡</strong>
    <div>Good Coffee&nbsp; • &nbsp;Sweet Drinks&nbsp; • &nbsp;Delicious Cake&nbsp; • &nbsp;Happy Time</div>
</footer>


<script>
// ========================================
// ค้นหาเครื่องดื่ม
// ========================================
const searchInput = document.getElementById('drinkSearch');
const searchButton = document.getElementById('drinkSearchButton');
const cards = document.querySelectorAll('.product-card');
const noResults = document.getElementById('noResults');

function searchDrinks() {
    if (!searchInput) return;

    const keyword = searchInput.value.toLowerCase().trim();
    let visibleCount = 0;

    cards.forEach(function (card) {
        const name = (card.dataset.name || '').toLowerCase();
        const matched = name.includes(keyword);

        card.style.display = matched ? '' : 'none';

        if (matched) {
            visibleCount++;
        }
    });

    if (noResults) {
        noResults.style.display =
            visibleCount === 0 ? 'block' : 'none';
    }
}

if (searchInput) {
    searchInput.addEventListener('input', searchDrinks);

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            searchDrinks();
        }
    });
}

if (searchButton) {
    searchButton.addEventListener('click', searchDrinks);
}

// ========================================
// เพิ่มเครื่องดื่มลงตะกร้าโดยไม่รีเฟรชหน้า
// ========================================
document.querySelectorAll('.add-cart-form').forEach(function (form) {

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const button = form.querySelector('.plus-button');

        if (!button || button.disabled) {
            return;
        }

        const originalText = button.textContent;

        button.disabled = true;
        button.textContent = '...';

        try {
            const response = await fetch('drink.php', {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                alert(data.message || 'ไม่สามารถเพิ่มสินค้าได้');
                return;
            }

            document.querySelectorAll('.cart-count').forEach(function (el) {
                el.textContent = data.cart_count;
            });

            let notice = document.getElementById('ajaxNotice');

            if (!notice) {
                notice = document.createElement('div');
                notice.id = 'ajaxNotice';
                notice.className = 'notice';

                const hero = document.querySelector('.cake-hero');

                if (hero) {
                    hero.insertAdjacentElement('afterend', notice);
                }
            }

            notice.className = 'notice';
            notice.textContent = data.message;

        } catch (error) {
            console.error('Add to cart error:', error);

            alert(
                'เกิดข้อผิดพลาดในการเพิ่มสินค้า กรุณาตรวจสอบ PHP และฐานข้อมูล'
            );

        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }

    });

});

const drinkHero = document.querySelector('.cake-hero');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (drinkHero && !reduceMotion) {
    let framePending = false;

    function updateDrinkHeroBackground() {
        const bounds = drinkHero.getBoundingClientRect();
        const progress = Math.min(
            1,
            Math.max(0, (window.innerHeight - bounds.top) / (window.innerHeight + bounds.height))
        );

        drinkHero.style.setProperty('--scroll-shift', `${progress * 24}px`);
        framePending = false;
    }

    function requestDrinkHeroUpdate() {
        if (framePending) return;

        framePending = true;
        window.requestAnimationFrame(updateDrinkHeroBackground);
    }

    window.addEventListener('scroll', requestDrinkHeroUpdate, { passive: true });
    window.addEventListener('resize', requestDrinkHeroUpdate);
    requestDrinkHeroUpdate();
}
</script>

</body>
</html>
