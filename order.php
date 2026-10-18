<?php
date_default_timezone_set('Asia/Bangkok');

session_start();
require_once 'conn.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$error = '';
$items = [];
$total = 0;
$deposit = 0;
$balance = 0;
$order = null;
$receiptItems = [];

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

if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

if (empty($_GET['order_id']) && empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$customer_id = (string)$_SESSION['customer_id'];

function makeId($conn, $table, $column) {
    $allowedTables = [
        'orders' => ['order_id'],
        'order_items' => ['order_item_id']
    ];

    if (
        !isset($allowedTables[$table]) ||
        !in_array($column, $allowedTables[$table], true)
    ) {
        throw new Exception('ชื่อตารางหรือคอลัมน์ไม่ถูกต้อง');
    }

    do {
        $id = strtoupper(bin2hex(random_bytes(5)) . 'A');

        $sql = "SELECT `$column` FROM `$table`
                WHERE `$column` = ? LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception('ไม่สามารถตรวจสอบรหัสได้');
        }

        $stmt->bind_param('s', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;

        $stmt->close();
    } while ($exists);

    return $id;
}

function getProduct($conn, $type, $id) {
    if ($type === 'cake') {
        $sql = "SELECT cake_id AS item_id,
                       cake_name AS product_name,
                       price, stock, image, status
                FROM cake
                WHERE cake_id = ?
                LIMIT 1";
    } elseif ($type === 'drink') {
        $sql = "SELECT drink_id AS item_id,
                       drink_name AS product_name,
                       price, stock, image, status
                FROM drink
                WHERE drink_id = ?
                LIMIT 1";
    } else {
        return null;
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('ไม่สามารถค้นหาสินค้าได้');
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

    return $product;
}

function getCartDetails($conn, $cart) {
    $items = [];
    $total = 0;

    foreach ($cart as $cartItem) {
        if (!is_array($cartItem)) {
            throw new Exception('ข้อมูลสินค้าในตะกร้าไม่ถูกต้อง');
        }

        $type = $cartItem['item_type'] ?? '';
        $id = (string)(
            $cartItem['item_id'] ??
            $cartItem['product_id'] ??
            ''
        );
        $qty = (int)($cartItem['quantity'] ?? 0);

        if ($id === '' || $qty < 1) {
            throw new Exception('ข้อมูลสินค้าในตะกร้าไม่ถูกต้อง');
        }

        $product = getProduct($conn, $type, $id);

        if (!$product || $product['status'] !== 'available') {
            throw new Exception(
                'สินค้า ' .
                ($cartItem['product_name'] ?? $id) .
                ' ไม่มีจำหน่ายแล้ว'
            );
        }

        if ($qty > (int)$product['stock']) {
            throw new Exception(
                'สินค้า ' . $product['product_name'] .
                ' มีจำนวนคงเหลือไม่เพียงพอ'
            );
        }

        $product['quantity'] = $qty;
        $product['subtotal'] = (float)$product['price'] * $qty;

        $items[] = $product;
        $total += $product['subtotal'];
    }

    return [$items, $total];
}

// โหลดข้อมูลสินค้าในตะกร้า
try {
    if (empty($_GET['order_id'])) {
        [$items, $total] = getCartDetails(
            $conn,
            $_SESSION['cart']
        );

        $deposit = round($total * 0.50, 2);
        $balance = round($total - $deposit, 2);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// บันทึกคำสั่งซื้อ
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    empty($_GET['order_id']) &&
    !$error
) {
    // วันที่รับสินค้าเป็นวันนี้ตามเวลาไทยจากฝั่งเซิร์ฟเวอร์
    $pickup_date = date('Y-m-d');
    $pickup_time = trim($_POST['pickup_time'] ?? '');

    $file = $_FILES['slip'] ?? null;
    $allowed = [];
    $mime = '';

    // เลือกเวลารับสินค้าได้ตั้งแต่ 09:00–15:30 น. เท่านั้น
    // ลูกค้าต้องมารับสินค้าภายในเวลา 16:00 น.
    $timeObject = null;

    if (
        !preg_match('/^\d{2}:\d{2}$/', $pickup_time) ||
        !($timeObject = DateTime::createFromFormat(
            '!H:i',
            $pickup_time
        )) ||
        $timeObject->format('H:i') !== $pickup_time ||
        $pickup_time < '09:00' ||
        $pickup_time > '15:30' ||
        !in_array($timeObject->format('i'), ['00', '30'], true)
    ) {
        $error = 'กรุณาเลือกเวลารับสินค้าตั้งแต่ 09:00–15:30 น. โดย 15:30 น. เป็นเวลาสุดท้ายที่เลือกได้ และต้องมารับสินค้าภายในเวลา 16:00 น.';
    } elseif (
        !$file ||
        $file['error'] !== UPLOAD_ERR_OK
    ) {
        $error = 'กรุณาแนบสลิปการโอนเงินมัดจำ';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $error = 'ไฟล์สลิปต้องมีขนาดไม่เกิน 5 MB';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowed[$mime])) {
            $error = 'รองรับเฉพาะไฟล์ JPG, PNG และ WEBP';
        }
    }

    // คำนวณยอดใหม่จากฐานข้อมูล
    if (!$error) {
        try {
            [$items, $total] = getCartDetails(
                $conn,
                $_SESSION['cart']
            );

            $deposit = round($total * 0.50, 2);
            $balance = round($total - $deposit, 2);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    $uploadDir = __DIR__ . '/uploads/slips/';
    $fullPath = '';
    $relativePath = '';

    if (!$error) {
        if (
            !is_dir($uploadDir) &&
            !mkdir($uploadDir, 0755, true)
        ) {
            $error = 'ไม่สามารถสร้างโฟลเดอร์เก็บสลิปได้';
        }
    }

    if (!$error) {
        $extension = $allowed[$mime];
        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

        $fullPath = $uploadDir . $fileName;
        $relativePath = 'uploads/slips/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $error = 'อัปโหลดสลิปไม่สำเร็จ';
        }
    }

    if (!$error) {
        $transactionStarted = false;

        try {
            $order_id = makeId($conn, 'orders', 'order_id');

            $conn->begin_transaction();
            $transactionStarted = true;

            // ตรวจสอบสต็อกซ้ำและหักสต็อก
            foreach ($items as $item) {
                if ($item['item_type'] === 'cake') {
                    $sql = "UPDATE cake
                            SET stock = stock - ?
                            WHERE cake_id = ?
                              AND status = 'available'
                              AND stock >= ?";
                } else {
                    $sql = "UPDATE drink
                            SET stock = stock - ?
                            WHERE drink_id = ?
                              AND status = 'available'
                              AND stock >= ?";
                }

                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception('ไม่สามารถตรวจสอบสต็อกได้');
                }

                $qty = (int)$item['quantity'];
                $id = (string)$item['item_id'];

                $stmt->bind_param('isi', $qty, $id, $qty);
                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    $stmt->close();

                    throw new Exception(
                        'สินค้า ' . $item['product_name'] .
                        ' มีจำนวนไม่เพียงพอ กรุณาตรวจสอบตะกร้าอีกครั้ง'
                    );
                }

                $stmt->close();
            }

            $orderDate = date('Y-m-d H:i:s');
            $pickupTimeSql = $pickup_time . ':00';
            $status = 'pending';
            $paymentStatus = 'pending_review';
            $note = 'รอตรวจสอบสลิปมัดจำ 50%';

            $sql = "INSERT INTO orders
                    (
                        order_id,
                        customer_id,
                        order_date,
                        pickup_date,
                        pickup_time,
                        total_amount,
                        deposit_amount,
                        balance_amount,
                        slip_path,
                        payment_status,
                        status,
                        note
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    'ไม่สามารถเตรียมข้อมูลคำสั่งซื้อได้'
                );
            }

            $stmt->bind_param(
                'sssssdddssss',
                $order_id,
                $customer_id,
                $orderDate,
                $pickup_date,
                $pickupTimeSql,
                $total,
                $deposit,
                $balance,
                $relativePath,
                $paymentStatus,
                $status,
                $note
            );

            $stmt->execute();
            $stmt->close();

            // บันทึกรายการสินค้า
            foreach ($items as $item) {
                $order_item_id = makeId(
                    $conn,
                    'order_items',
                    'order_item_id'
                );

                $cake_id = null;
                $drink_id = null;

                if ($item['item_type'] === 'cake') {
                    $cake_id = (string)$item['item_id'];
                } else {
                    $drink_id = (string)$item['item_id'];
                }

                $qty = (int)$item['quantity'];
                $unitPrice = (float)$item['price'];
                $subtotal = (float)$item['subtotal'];

                $sql = "INSERT INTO order_items
                        (
                            order_item_id,
                            order_id,
                            cake_id,
                            drink_id,
                            quantity,
                            unit_price,
                            subtotal
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception(
                        'ไม่สามารถบันทึกรายการสินค้าได้'
                    );
                }

                $stmt->bind_param(
                    'ssssidd',
                    $order_item_id,
                    $order_id,
                    $cake_id,
                    $drink_id,
                    $qty,
                    $unitPrice,
                    $subtotal
                );

                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $transactionStarted = false;

            $_SESSION['cart'] = [];

            header(
                'Location: order.php?order_id=' .
                urlencode($order_id)
            );
            exit;

        } catch (Throwable $e) {
            if ($transactionStarted) {
                $conn->rollback();
            }

            if ($fullPath && file_exists($fullPath)) {
                unlink($fullPath);
            }

            $error = 'บันทึกคำสั่งซื้อไม่สำเร็จ: ' .
                     $e->getMessage();
        }
    }
}

// โหลดข้อมูลใบสรุปคำสั่งซื้อ
if (!empty($_GET['order_id'])) {
    $view_id = (string)$_GET['order_id'];

    $sql = "SELECT *
            FROM orders
            WHERE order_id = ? AND customer_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param('ss', $view_id, $customer_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $order = $result->fetch_assoc();

        $stmt->close();
    }

    if ($order) {
        $sql = "SELECT
                    oi.*,
                    c.cake_name,
                    d.drink_name
                FROM order_items oi
                LEFT JOIN cake c ON oi.cake_id = c.cake_id
                LEFT JOIN drink d ON oi.drink_id = d.drink_id
                WHERE oi.order_id = ?";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param('s', $view_id);
            $stmt->execute();

            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $row['display_name'] =
                    $row['cake_name'] ?:
                    $row['drink_name'] ?:
                    'สินค้า';

                $receiptItems[] = $row;
            }

            $stmt->close();
        }
    } else {
        $error = 'ไม่พบคำสั่งซื้อ หรือคุณไม่มีสิทธิ์ดูคำสั่งซื้อนี้';
    }
}

// จำนวนสินค้าในตะกร้า
$cartCount = 0;

foreach ($_SESSION['cart'] as $cartItem) {
    if (is_array($cartItem)) {
        $cartCount += max(
            0,
            (int)($cartItem['quantity'] ?? 0)
        );
    } else {
        $cartCount += max(0, (int)$cartItem);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order | KLONG 6 CAFE</title>

<link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&family=Pacifico&display=swap" rel="stylesheet">

<style>
:root {
    --pink: #ffb6d0;
    --pink-light: #fff4f8;
    --red: #ed2674;
    --red-dark: #bd1555;
    --cream: #fff8fb;
    --text: #812349;
    --muted: #a85d7c;
    --border: #ffd0e0;
    --white: #fff;
    --shadow: 0 12px 32px rgba(189,21,85,.10);
}

* { box-sizing: border-box; }

html { scroll-behavior: smooth; }

body {
    margin: 0;
    font-family: 'Mali', sans-serif;
    color: var(--text);
    background:
        linear-gradient(
            rgba(255,248,251,.30),
            rgba(255,244,248,.48)
        ),
        url('pink-3d-bg.png') center center / cover fixed no-repeat;
    background-color: #fff4f8;
}

a { color: inherit; }

.announcement {
    background: linear-gradient(110deg,#fff0f6,#ffe0ec,#fff3f8);
    color: var(--red-dark);
    text-align: center;
    padding: 9px 12px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .3px;
}

.header {
    background: linear-gradient(115deg,#ff8db7,#ffbad2 55%,#ffe6ef);
    padding: 22px 5%;
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
    text-decoration: none;
    color: #fff;
    font-size: clamp(23px,3vw,32px);
    font-weight: 700;
    line-height: 1.25;
    text-shadow: 0 2px 10px rgba(150,25,75,.18);
}

.brand small {
    display: block;
    margin-top: 5px;
    font-size: 10px;
    letter-spacing: 2px;
    font-weight: 600;
}

.account {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
}

.account a {
    text-decoration: none;
    padding: 9px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    background: rgba(255,255,255,.82);
    color: var(--red-dark);
    transition: .2s;
}

.account a:hover {
    background: white;
    transform: translateY(-2px);
}

.welcome {
    color: #fff;
    font-size: 12px;
    font-weight: 700;
}

.nav-strip {
    position: sticky;
    top: 0;
    z-index: 100;
    padding: 10px 4%;
    background: linear-gradient(115deg,#ff8db7,#ff9fc2 55%,#ffb3cc);
}

.nav-wrap {
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

.nav {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    padding: 6px 10px;
    margin: 0 auto;
}

.nav a {
    padding: 9px 17px;
    border-radius: 999px;
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    text-shadow: 0 1px 6px rgba(150,25,75,.35);
    white-space: nowrap;
    transition: background .2s, transform .2s;
}

.nav a:hover {
    background: rgba(255,255,255,.28);
    transform: translateY(-1px);
}

.nav a.active,
.nav .cart-link {
    background: linear-gradient(120deg,#ff5c9b,#ed2674);
    box-shadow: 0 5px 14px rgba(229,44,116,.25);
}

.container {
    width: min(960px,92%);
    margin: 35px auto 60px;
}

.page-heading {
    text-align: center;
    margin: 0 0 24px;
}ประวัติการสั่งซื้อ 

.eyebrow {
    color: var(--red);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
}

.page-heading h1 {
    margin: 8px 0;
    color: var(--red-dark);
    font-size: clamp(26px,4vw,36px);
}

.page-heading p {
    color: var(--muted);
    font-size: 13px;
    margin: 0;
}

.panel {
    background: rgba(255,255,255,.94);
    padding: clamp(18px,3vw,30px);
    border: 1px solid rgba(255,208,224,.8);
    border-radius: 24px;
    box-shadow: var(--shadow);
    margin-bottom: 22px;
}

.panel h2 {
    color: var(--red-dark);
    font-size: 20px;
    margin: 0 0 18px;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 520px;
}

th, td {
    padding: 13px 10px;
    border-bottom: 1px solid #ffe0eb;
    text-align: left;
    font-size: 13px;
}

th {
    background: #fff0f6;
    color: var(--red-dark);
    font-weight: 700;
}

tbody tr:hover { background: #fffafd; }

.money {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    margin: 13px 0;
    font-size: 14px;
}

.money strong {
    color: var(--red-dark);
    text-align: right;
}

.total-row {
    border-top: 1px dashed var(--border);
    padding-top: 15px;
    margin-top: 15px;
}

.deposit {
    background: linear-gradient(120deg,#fff0f6,#fff8fb);
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 16px;
    margin-top: 18px;
}

.deposit .money:last-child { margin-bottom: 0; }

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 18px;
}

.field { margin-bottom: 17px; }

label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 7px;
    color: var(--text);
}

input, select {
    display: block;
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #f1bfd2;
    border-radius: 13px;
    background: #fffafd;
    color: var(--text);
    font: inherit;
    font-size: 13px;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}

input:focus, select:focus {
    border-color: var(--red);
    box-shadow: 0 0 0 3px rgba(237,38,116,.10);
}

input[readonly] {
    cursor: default;
    background: #fff0f6;
    color: var(--red-dark);
    font-weight: 700;
}

input[type="file"] { background: white; }

.small {
    font-size: 12px;
    line-height: 1.8;
    color: var(--muted);
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border: 1px solid transparent;
    border-radius: 999px;
    background: linear-gradient(120deg,#ff5c9b,#ed2674);
    color: white;
    text-decoration: none;
    text-align: center;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(229,44,116,.20);
    transition: transform .2s, box-shadow .2s;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 9px 20px rgba(229,44,116,.25);
}

.btn-light {
    background: white;
    color: var(--red-dark);
    border: 1px solid var(--border);
    box-shadow: none;
}

.full { width: 100%; }

.error {
    padding: 14px 16px;
    color: #a50e40;
    background: #fff0f4;
    border: 1px solid #ffc3d7;
    border-radius: 14px;
    margin-bottom: 18px;
    font-size: 13px;
    overflow-wrap: anywhere;
}

.notice {
    padding: 14px 16px;
    border-radius: 14px;
    background: #fff7fb;
    border: 1px solid var(--border);
    font-size: 12px;
    line-height: 1.9;
    color: var(--muted);
}

/* กล่องแจ้งเตือนเวลารับสินค้า */
.pickup-warning {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px 20px;
    margin: 18px 0;
    background: #fff0f0;
    border: 2px solid #e53935;
    border-left: 8px solid #c62828;
    border-radius: 16px;
    color: #a31515;
    box-shadow: 0 6px 18px rgba(198,40,40,.15);
}

.pickup-warning .warning-icon {
    font-size: 32px;
    line-height: 1.2;
    flex-shrink: 0;
}

.pickup-warning .warning-title {
    display: block;
    font-size: 17px;
    font-weight: 700;
    margin-bottom: 6px;
    color: #b71c1c;
}

.pickup-warning .warning-text {
    font-size: 14px;
    line-height: 1.9;
    font-weight: 600;
}

.pickup-warning .warning-time {
    display: inline-block;
    margin-top: 5px;
    padding: 4px 12px;
    background: #c62828;
    color: #fff;
    border-radius: 8px;
    font-size: 17px;
    font-weight: 700;
}

.receipt-top {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px dashed var(--border);
    margin-bottom: 20px;
}

.receipt-icon { font-size: 38px; }

.receipt-top h1 {
    color: var(--red-dark);
    margin: 5px 0;
    font-size: 27px;
}

.receipt-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 22px;
}

.meta-item {
    padding: 13px;
    border-radius: 13px;
    background: #fff6fa;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.meta-item strong {
    display: block;
    color: var(--red-dark);
    margin-bottom: 5px;
}

.orders-footer{
    background:#451f2b;
    text-align:center;
    color:#fbe8ed;
    padding:22px 12px;
    font-size:13px
}
.orders-footer strong{
    font-family:'Pacifico','Mali',cursive;
    color:#fff;
    font-size:23px
}

@media (max-width: 540px) {
    .pickup-warning {
        padding: 14px;
        gap: 10px;
    }

    .pickup-warning .warning-icon {
        font-size: 26px;
    }

    .pickup-warning .warning-title {
        font-size: 15px;
    }

    .pickup-warning .warning-text {
        font-size: 12px;
    }

    .pickup-warning .warning-time {
        font-size: 15px;
    }

    .header-inner {
        justify-content: center;
        text-align: center;
    }

    .account { width: 100%; }

    .nav-wrap { border-radius: 24px; }

    .nav {
        justify-content: flex-start;
        flex-wrap: nowrap;
        overflow-x: auto;
    }

    .nav a {
        flex-shrink: 0;
        padding: 8px 12px;
        font-size: 12px;
    }

    .form-grid,
    .receipt-meta {
        grid-template-columns: 1fr;
    }

    .container { margin-top: 25px; }

    .panel { border-radius: 18px; }

    .money { font-size: 13px; }
}

@media print {
    body { background: white; }

    .announcement,
    .header,
    .nav-strip,
    .no-print,
    .orders-footer {
        display: none !important;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .panel {
        box-shadow: none;
        border: 1px solid #ddd;
        break-inside: avoid;
    }
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
@media (min-width:1001px){.container{padding-top:145px}.page-heading{margin-top:-80px}}
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

<div class="announcement">
    ♡ Baked with love — ความสุขชิ้นเล็ก ๆ จาก KLONG 6 CAFE ♡
</div>

<header class="header">
    <div class="header-inner">
        <a href="index.php" class="brand">
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
                <a href="register.php">สมัครสมาชิก ♡</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="nav-strip">
    <div class="nav-wrap">
        <nav class="nav">
                <a href="index.php"
                    class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
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

            <a href="cart.php" class="cart-link">
                🛒 ตะกร้า
                (<span class="cart-count"><?= (int)$cartCount ?></span>)
            </a>
        </nav>
    </div>
</div>

<main class="container">

<?php if ($error): ?>
    <div class="error">⚠️ <?= e($error) ?></div>
<?php endif; ?>

<?php if ($order): ?>

    <section class="panel" id="receipt">
        <div class="receipt-top">
            <div class="receipt-icon">🍒</div>
            <h1>สรุปคำสั่งซื้อ</h1>
            <p class="small">
                ขอบคุณที่เลือกใช้บริการ KLONG 6 CAFE ♡
            </p>
        </div>

        <div class="receipt-meta">
            <div class="meta-item">
                <strong>เลขที่คำสั่งซื้อ</strong>
                <?= e($order['order_id']) ?>
            </div>

            <div class="meta-item">
                <strong>วันที่สั่งซื้อ</strong>
                <?= e($order['order_date']) ?>
            </div>

            <div class="meta-item">
                <strong>วันที่รับสินค้า</strong>
                <?= e($order['pickup_date']) ?>
            </div>

            <div class="meta-item">
                <strong>เวลารับสินค้า</strong>
                <?= e($order['pickup_time']) ?> น.
            </div>
        </div>

        <!-- แจ้งเตือนเวลารับสินค้าบนใบสรุปคำสั่งซื้อ -->
        <div class="pickup-warning" role="alert">
            <div class="warning-icon">⏰</div>
            <div>
                <span class="warning-title">
                    กรุณามารับสินค้าตามเวลาที่นัดหมาย
                </span>

                <div class="warning-text">
                    วันที่นัดรับสินค้า:
                    <strong><?= e($order['pickup_date']) ?></strong>
                    <br>
                    เวลานัดรับ:
                    <strong><?= e($order['pickup_time']) ?> น.</strong>
                    <br>
                    <span class="warning-time">
                        เลือกเวลารับสินค้าได้ถึง 15:30 น.
                    </span>
                    <br>
                    กรุณามารับสินค้าไม่เกินเวลา 16:00 น. เพื่อให้ทางร้านจัดเตรียมสินค้าได้อย่างเหมาะสมค่ะ 💗
                </div>
            </div>
        </div>

        <h2>🧁 รายการสินค้าที่สั่ง</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>รายการสินค้า</th>
                        <th>จำนวน</th>
                        <th>รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receiptItems as $item): ?>
                        <tr>
                            <td><?= e($item['display_name']) ?></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td>
                                <?= number_format(
                                    (float)$item['subtotal'],
                                    2
                                ) ?> บาท
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="money total-row">
            <span>ยอดรวมทั้งหมด</span>
            <strong>
                <?= number_format(
                    (float)$order['total_amount'],
                    2
                ) ?> บาท
            </strong>
        </div>

        <div class="money">
            <span>มัดจำ 50%</span>
            <strong>
                <?= number_format(
                    (float)$order['deposit_amount'],
                    2
                ) ?> บาท
            </strong>
        </div>

        <div class="money">
            <span>ยอดคงเหลือเมื่อรับสินค้า</span>
            <strong>
                <?= number_format(
                    (float)$order['balance_amount'],
                    2
                ) ?> บาท
            </strong>
        </div>

        <div class="notice">
            💗 สถานะการชำระเงิน:
            <?= e($order['payment_status'] ?? 'รอตรวจสอบ') ?>
            <br>
            ร้านจะตรวจสอบสลิปมัดจำก่อนยืนยันคำสั่งซื้อ
        </div>

        <div class="no-print"
             style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px">
            <button class="btn" type="button"
                    onclick="window.print()">
                🖨️ พิมพ์ / บันทึกเป็น PDF
            </button>

            <a class="btn btn-light" href="my_orders.php">
                📋 ดูประวัติการสั่งซื้อ
            </a>

            <a class="btn btn-light" href="index.php">
                🏠 กลับหน้าหลัก
            </a>
        </div>
    </section>

<?php elseif (!$error): ?>

    <div class="page-heading">
        <h1>🧾 ยืนยันคำสั่งซื้อ</h1>
        <p>ตรวจสอบรายการ เลือกเวลารับสินค้า และแนบสลิปมัดจำ</p>
    </div>

    <section class="panel">
        <h2>🧁 รายการสินค้า</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th>ราคา</th>
                        <th>จำนวน</th>
                        <th>รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?></td>
                            <td>
                                <?= number_format(
                                    (float)$item['price'],
                                    2
                                ) ?> บาท
                            </td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td>
                                <?= number_format(
                                    (float)$item['subtotal'],
                                    2
                                ) ?> บาท
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="money total-row">
            <span>ยอดรวมทั้งหมด</span>
            <strong><?= number_format($total, 2) ?> บาท</strong>
        </div>

        <div class="deposit">
            <div class="money">
                <span>💗 ยอดมัดจำ 50%</span>
                <strong><?= number_format($deposit, 2) ?> บาท</strong>
            </div>

            <div class="money">
                <span>ยอดคงเหลือเมื่อรับสินค้า</span>
                <strong><?= number_format($balance, 2) ?> บาท</strong>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>📅 ข้อมูลรับสินค้าและชำระมัดจำ</h2>

        <!-- แจ้งเตือนก่อนเลือกเวลารับสินค้า -->
        <div class="pickup-warning" role="alert">
            <div class="warning-icon">⚠️</div>
            <div>
                <span class="warning-title">
                    เลือกเวลารับสินค้าได้ถึง 15:30 น.
                </span>

                <div class="warning-text">
                    ร้านเปิดให้เลือกเวลารับสินค้าตั้งแต่ 09:00–15:30 น.
                    <br>
                    <span class="warning-time">
                        ต้องมารับสินค้าไม่เกิน 16:00 น.
                    </span>
                    <br>
                    กรุณาเลือกเวลารับสินค้าและมารับตามเวลาที่เลือกไว้นะคะ 💗
                </div>
            </div>
        </div>

        <form method="post"
              action="order.php"
              enctype="multipart/form-data">

            <div class="form-grid">
                <div class="field">
                    <label for="pickup_date">วันที่รับสินค้า</label>

                    <input
                        type="text"
                        id="pickup_date"
                        name="pickup_date"
                        value="<?= e(date('Y-m-d')) ?>"
                        readonly
                        required
                    >

                    <p class="small">
                        📅 รับสินค้าวันนี้เท่านั้น
                        — อัปเดตอัตโนมัติตามเวลาไทย
                    </p>
                </div>

                <div class="field">
                    <label for="pickup_time">เวลารับสินค้า</label>

                    <select id="pickup_time"
                            name="pickup_time"
                            required>
                        <option value="">-- เลือกเวลา --</option>
                        <?php
                        // แสดงตัวเลือกทุก 30 นาที ตั้งแต่ 09:00 ถึง 15:30
                        // ไม่มีตัวเลือก 16:00 น.
                        for (
                            $t = strtotime('09:00');
                            $t <= strtotime('15:30');
                            $t += 30 * 60
                        ) {
                            $val = date('H:i', $t);

                            $selected = (
                                ($_POST['pickup_time'] ?? '') === $val
                            ) ? ' selected' : '';

                            echo '<option value="' .
                                e($val) . '"' .
                                $selected . '>' .
                                e($val) . ' น.</option>';
                        }
                        ?>
                    </select>

                    <p class="small">
                        เลือกได้ตั้งแต่ 09:00–15:30 น.
                        โดย 15:30 น. เป็นเวลาสุดท้ายที่เลือกได้
                        และต้องมารับสินค้าภายในเวลา 16:00 น.
                    </p>
                </div>
            </div>

            <div class="field">
                <label for="slip">แนบสลิปโอนเงินมัดจำ</label>

                <input
                    type="file"
                    id="slip"
                    name="slip"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    required
                >

                <p class="small">
                    รองรับไฟล์ JPG, PNG หรือ WEBP
                    ขนาดไม่เกิน 5 MB
                </p>
            </div>

            <div class="notice">
                <strong>ยอดมัดจำที่ต้องชำระ:</strong>
                <?= number_format($deposit, 2) ?> บาท
                <br>
                กรุณาตรวจสอบยอดเงินและแนบสลิปให้ถูกต้อง
                ก่อนกดยืนยันคำสั่งซื้อค่ะ 💗
            </div>

            <button type="submit"
                    class="btn full"
                    style="margin-top:20px">
                💗 ยืนยันคำสั่งซื้อ
            </button>

            <a href="cart.php"
               class="btn btn-light full"
               style="margin-top:10px">
                ← กลับไปแก้ไขตะกร้า
            </a>
        </form>
    </section>

<?php endif; ?>

</main>

<footer class="orders-footer">
    <strong>♡ Klong 6 Cafe ♡</strong>
    <div>Good Coffee&nbsp; • &nbsp;Sweet Drinks&nbsp; • &nbsp;Delicious Cake&nbsp; • &nbsp;Happy Time</div>
</footer>

<script>
(function () {
    const pickupDate = document.getElementById('pickup_date');

    if (!pickupDate) return;

    function updatePickupDate() {
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Bangkok',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        }).formatToParts(new Date());

        const values = {};

        parts.forEach(function (part) {
            values[part.type] = part.value;
        });

        const today =
            values.year + '-' +
            values.month + '-' +
            values.day;

        pickupDate.value = today;
    }

    updatePickupDate();

    // ตรวจสอบวันใหม่ทุกวินาที
    setInterval(updatePickupDate, 1000);
})();
</script>

</body>
</html>