
<?php
session_start();
require_once 'conn.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ========================================
// 1. ข้อมูลพื้นฐาน
// ========================================
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

// ========================================
// 2. ตรวจสอบการเข้าสู่ระบบและตะกร้า
// ========================================
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

if (empty($_GET['order_id']) && empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$customer_id = (int)$_SESSION['customer_id'];
$error = '';
$items = [];
$total = 0;
$deposit = 0;
$balance = 0;
$order = null;
$receiptItems = [];

// ========================================
// 3. ฟังก์ชันสร้างรหัสไม่ซ้ำ
// ========================================
function makeId($conn, $table, $column) {
    // ใช้เฉพาะชื่อตารางและคอลัมน์ที่กำหนดไว้ในโค้ด
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

// ========================================
// 4. ดึงสินค้าจากฐานข้อมูล
// ========================================
function getProduct($conn, $type, $id) {
    if ($type === 'cake') {
        $sql = "SELECT
                    cake_id AS item_id,
                    cake_name AS product_name,
                    price, stock, image, status
                FROM cake
                WHERE cake_id = ?
                LIMIT 1";
    } elseif ($type === 'drink') {
        $sql = "SELECT
                    drink_id AS item_id,
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

// ========================================
// 5. ตรวจสอบสินค้าและคำนวณยอดเงิน
// ========================================
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

// ========================================
// 6. โหลดข้อมูลสินค้าในตะกร้า
// ========================================
try {
    if (empty($_GET['order_id'])) {
        [$items, $total] = getCartDetails($conn, $_SESSION['cart']);
        $deposit = round($total * 0.50, 2);
        $balance = round($total - $deposit, 2);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// ========================================
// 7. บันทึกคำสั่งซื้อ
// ========================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    empty($_GET['order_id']) &&
    !$error
) {
    $pickup_date = trim($_POST['pickup_date'] ?? '');
    $pickup_time = trim($_POST['pickup_time'] ?? '');

    $file = $_FILES['slip'] ?? null;
    $allowed = [];
    $mime = '';

    if (
        !$pickup_date ||
        $pickup_date < date('Y-m-d')
    ) {
        $error = 'กรุณาเลือกวันที่รับสินค้าที่ถูกต้อง';
    } elseif (
        !preg_match('/^\d{2}:\d{2}$/', $pickup_time) ||
        !($timeObject = DateTime::createFromFormat('!H:i', $pickup_time)) ||
        $timeObject->format('H:i') !== $pickup_time ||
        $pickup_time < '10:00' ||
        $pickup_time > '16:00'
    ) {
        $error = 'กรุณาเลือกเวลารับสินค้าในช่วง 10:00–16:00 น.';
    } elseif (
        !$file ||
        $file['error'] !== UPLOAD_ERR_OK
    ) {
        $error = 'กรุณาแนบสลิปการโอนเงิน';
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

    // คำนวณยอดใหม่จากฐานข้อมูลก่อนบันทึก
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
                throw new Exception('ไม่สามารถเตรียมข้อมูลคำสั่งซื้อได้');
            }

            $stmt->bind_param(
                'sisssdddssss',
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

            // บันทึกรายการสินค้าแต่ละรายการ
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
                    throw new Exception('ไม่สามารถบันทึกรายการสินค้าได้');
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

            // ล้างตะกร้าเมื่อบันทึกสำเร็จเท่านั้น
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

            $error = 'บันทึกคำสั่งซื้อไม่สำเร็จ: ' . $e->getMessage();
        }
    }
}

// ========================================
// 8. โหลดข้อมูลสำหรับใบสรุปคำสั่งซื้อ
// ========================================
if (!empty($_GET['order_id'])) {
    $view_id = (string)$_GET['order_id'];

    $sql = "SELECT *
            FROM orders
            WHERE order_id = ? AND customer_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param('si', $view_id, $customer_id);
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
        $cartCount += max(0, (int)($cartItem['quantity'] ?? 0));
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
            --shadow: 0 12px 32px rgba(189, 21, 85, .10);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: 'Mali', sans-serif;
            color: var(--text);

            /* 3D Pink Dessert Background */
            background:
                linear-gradient(
                    rgba(255, 248, 251, 0.30),
                    rgba(255, 244, 248, 0.48)
                ),
                url('pink-3d-bg.png') center center / cover fixed no-repeat;

            background-color: #fff4f8;
        }}

        a {
            color: inherit;
        }

        .announcement {
            background: linear-gradient(110deg, #fff0f6, #ffe0ec, #fff3f8);
            color: var(--red-dark);
            text-align: center;
            padding: 9px 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .header {
            background: linear-gradient(115deg, #ff8db7, #ffbad2 55%, #ffe6ef);
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
            font-size: clamp(23px, 3vw, 32px);
            font-weight: 700;
            line-height: 1.25;
            text-shadow: 0 2px 10px rgba(150, 25, 75, .18);
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

        .account .cart-link {
            color: #fff;
            background: linear-gradient(120deg, #ff5c9b, #ed2674);
            box-shadow: 0 5px 14px rgba(229,44,116,.25);
        }

        .welcome {
            color: #fff;
            font-size: 12px;
            font-weight: 700;
        }

        /* Navigation แบบเดียวกับ cake.php */
        .nav-strip {
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 10px 4%;
            background: linear-gradient(115deg, #ff8db7, #ff9fc2 55%, #ffb3cc);
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
            background: transparent;
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

        .nav a.active {
            background: linear-gradient(120deg, #ff5c9b, #ed2674);
            box-shadow: 0 5px 14px rgba(229,44,116,.35);
        }

        .container {
            width: min(960px, 92%);
            margin: 35px auto 60px;
        }

        .page-heading {
            text-align: center;
            margin: 0 0 24px;
        }

        .eyebrow {
            color: var(--red);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .page-heading h1 {
            margin: 8px 0;
            color: var(--red-dark);
            font-size: clamp(26px, 4vw, 36px);
        }

        .page-heading p {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .panel {
            background: rgba(255,255,255,.94);
            padding: clamp(18px, 3vw, 30px);
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

        .panel h3 {
            color: var(--red-dark);
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

        tbody tr:hover {
            background: #fffafd;
        }

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
            background: linear-gradient(120deg, #fff0f6, #fff8fb);
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 16px;
            margin-top: 18px;
        }

        .deposit .money:last-child {
            margin-bottom: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 18px;
        }

        .field {
            margin-bottom: 17px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
            color: var(--text);
        }

        input {
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

        input:focus {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(237,38,116,.10);
        }

        input[type="file"] {
            background: white;
        }

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
            border: 0;
            border-radius: 999px;
            background: linear-gradient(120deg, #ff5c9b, #ed2674);
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

        .full {
            width: 100%;
        }

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

        .receipt-top {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px dashed var(--border);
            margin-bottom: 20px;
        }

        .receipt-top .receipt-icon {
            font-size: 38px;
        }

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

        .footer {
            background: linear-gradient(115deg, #ffe1ec, #fff1f7);
            text-align: center;
            padding: 28px 15px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.9;
        }

        .footer strong {
            display: block;
            font-size: 20px;
            color: var(--red-dark);
            margin-bottom: 5px;
        }

        .footer p {
            margin: 4px 0;
        }

        @media (max-width: 540px) {
            .header-inner {
                justify-content: center;
                text-align: center;
            }

            .account {
                width: 100%;
            }

            .nav-wrap {
                border-radius: 24px;
            }

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

            .container {
                margin-top: 25px;
            }

            .panel {
                border-radius: 18px;
            }

            .money {
                font-size: 13px;
            }
        }

        @media print {
            body {
                background: white;
            }

            .announcement,
            .header,
            .nav-strip,
            .no-print,
            .footer {
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
    </style>
</head>

<body>

<div class="announcement">
    ♡ Baked with love — ความสุขชิ้นเล็ก ๆ จาก KLONG 6 CAFE ♡
</div>

<header class="header">
    <div class="header-inner">
        <a href="index.php" class="brand">
            🍒 KLONG 6 CAFE
            <small>CAFE • BAKERY • HAPPINESS</small>
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
               class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"
               <?= $currentPage === 'index.php' ? 'aria-current="page"' : '' ?>>
                🏠 หน้าหลัก
            </a>

            <a href="cake.php"
               class="<?= $currentPage === 'cake.php' ? 'active' : '' ?>"
               <?= $currentPage === 'cake.php' ? 'aria-current="page"' : '' ?>>
                🍰 เค้ก
            </a>

            <a href="drink.php"
               class="<?= $currentPage === 'drink.php' ? 'active' : '' ?>"
               <?= $currentPage === 'drink.php' ? 'aria-current="page"' : '' ?>>
                🥤 เครื่องดื่ม
            </a>

            <a href="order.php"
               class="<?= $currentPage === 'order.php' ? 'active' : '' ?>"
               <?= $currentPage === 'order.php' ? 'aria-current="page"' : '' ?>>
                🧾 Order
            </a>

            <a href="my_orders.php"
               class="<?= $currentPage === 'my_orders.php' ? 'active' : '' ?>"
               <?= $currentPage === 'my_orders.php' ? 'aria-current="page"' : '' ?>>
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
        <div class="error">
            ⚠️ <?= e($error) ?>
        </div>
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
                    <?= e($order['pickup_time']) ?>
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
                                    <?= number_format((float)$item['subtotal'], 2) ?>
                                    บาท
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="money total-row">
                <span>ยอดรวมทั้งหมด</span>
                <strong>
                    <?= number_format((float)$order['total_amount'], 2) ?> บาท
                </strong>
            </div>

            <div class="money">
                <span>มัดจำ 50%</span>
                <strong>
                    <?= number_format((float)$order['deposit_amount'], 2) ?> บาท
                </strong>
            </div>

            <div class="money">
                <span>ยอดคงเหลือเมื่อรับสินค้า</span>
                <strong>
                    <?= number_format((float)$order['balance_amount'], 2) ?> บาท
                </strong>
            </div>

            <div class="notice">
                💗 สถานะการชำระเงิน:
                <?= e($order['payment_status'] ?? 'รอตรวจสอบ') ?>
                <br>
                ร้านจะตรวจสอบสลิปมัดจำก่อนยืนยันคำสั่งซื้อ
            </div>

            <div class="no-print" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px">
                <button class="btn" type="button" onclick="window.print()">
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
            <div class="eyebrow">MADE WITH LOVE ♡</div>
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
                                    <?= number_format((float)$item['price'], 2) ?>
                                    บาท
                                </td>
                                <td><?= (int)$item['quantity'] ?></td>
                                <td>
                                    <?= number_format((float)$item['subtotal'], 2) ?>
                                    บาท
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

            <form method="post"
                  action="order.php"
                  enctype="multipart/form-data">

                <div class="form-grid">
                    <div class="field">
                        <label for="pickup_date">วันที่รับสินค้า</label>
                        <input
                            type="date"
                            id="pickup_date"
                            name="pickup_date"
                            min="<?= date('Y-m-d') ?>"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="pickup_time">เวลารับสินค้า</label>
                        <input
                            type="time"
                            id="pickup_time"
                            name="pickup_time"
                            min="10:00"
                            max="16:00"
                            required
                        >
                        <p class="small">รับสินค้าได้ตั้งแต่ 10:00–16:00 น.</p>
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
                        รองรับไฟล์ JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB
                    </p>
                </div>

                <div class="notice">
                    <strong>ยอดมัดจำที่ต้องชำระ:</strong>
                    <?= number_format($deposit, 2) ?> บาท
                    <br>
                    กรุณาตรวจสอบยอดเงินและแนบสลิปให้ถูกต้อง
                    ก่อนกดยืนยันคำสั่งซื้อค่ะ 💗
                </div>

                <button type="submit" class="btn full" style="margin-top:20px">
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

<footer class="footer">
    <strong>🍒 KLONG 6 CAFE 🍒</strong>
    <p>CAFE • BAKERY • HAPPINESS</p>
    <p>ขอบคุณที่แวะมาเติมความหวานกับเรานะคะ ♡</p>
    <p>© <?= date('Y') ?> KLONG 6 CAFE</p>
</footer>

</body>
</html>
