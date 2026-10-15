<?php
session_start();
require_once "conn.php";

// ตรวจสอบว่าลูกค้าเข้าสู่ระบบแล้วหรือไม่
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit;
}

$customer_id = (int) $_SESSION["customer_id"];

// ตรวจสอบว่ามี order_id ส่งมาหรือไม่
$order_id = trim($_GET["order_id"] ?? "");

if ($order_id === "") {
    die("ไม่พบหมายเลขคำสั่งซื้อ");
}

// ดึงข้อมูลคำสั่งซื้อของลูกค้าคนที่เข้าสู่ระบบเท่านั้น
$sql = "SELECT
            order_id,
            customer_id,
            order_date,
            pickup_date,
            pickup_time,
            total_amount,
            deposit_amount,
            balance_amount,
            payment_status,
            status,
            note
        FROM orders
        WHERE order_id = ? AND customer_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $order_id, $customer_id);
$stmt->execute();

$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

// ป้องกันการดูคำสั่งซื้อของลูกค้าคนอื่น
if (!$order) {
    http_response_code(404);
    die("ไม่พบคำสั่งซื้อ หรือคุณไม่มีสิทธิ์เข้าถึงรายการนี้");
}

// ดึงข้อมูลรายการสินค้าในคำสั่งซื้อ
$sql_items = "SELECT
                oi.order_item_id,
                oi.cake_id,
                oi.drink_id,
                oi.quantity,
                oi.unit_price,
                oi.subtotal,
                c.cake_name,
                d.drink_name
            FROM order_items oi
            LEFT JOIN cake c ON oi.cake_id = c.cake_id
            LEFT JOIN drink d ON oi.drink_id = d.drink_id
            WHERE oi.order_id = ?";

$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("s", $order_id);
$stmt_items->execute();

$items_result = $stmt_items->get_result();

$items = [];

while ($item = $items_result->fetch_assoc()) {
    if (!empty($item["cake_id"])) {
        $item["product_name"] = $item["cake_name"] ?: "เค้ก";
        $item["product_type"] = "เค้ก";
    } elseif (!empty($item["drink_id"])) {
        $item["product_name"] = $item["drink_name"] ?: "เครื่องดื่ม";
        $item["product_type"] = "เครื่องดื่ม";
    } else {
        $item["product_name"] = "สินค้า";
        $item["product_type"] = "สินค้า";
    }

    $items[] = $item;
}

$stmt_items->close();

// ฟังก์ชันป้องกันข้อความ HTML ที่ไม่ปลอดภัย
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// แปลงวันที่ให้อ่านง่าย
function formatDate($date) {
    if (empty($date)) {
        return "-";
    }

    $timestamp = strtotime($date);

    return $timestamp ? date("d/m/Y", $timestamp) : "-";
}

// แปลงเวลาให้อยู่ในรูปแบบ 12 ชั่วโมง
function formatTime($time) {
    if (empty($time)) {
        return "-";
    }

    $timestamp = strtotime($time);

    return $timestamp ? date("g:i A", $timestamp) : "-";
}

// แสดงชื่อสถานะการชำระเงิน
$payment_labels = [
    "pending_review" => "รอตรวจสอบสลิป",
    "pending"        => "รอชำระเงิน",
    "paid"            => "ชำระเงินแล้ว",
    "verified"        => "ตรวจสอบการชำระเงินแล้ว",
    "rejected"        => "สลิปไม่ผ่านการตรวจสอบ"
];

$payment_status = $payment_labels[$order["payment_status"] ?? ""] 
    ?? ($order["payment_status"] ?? "ไม่ระบุ");

// แสดงสถานะคำสั่งซื้อ
$order_labels = [
    "pending"    => "รอดำเนินการ",
    "confirmed"  => "ยืนยันคำสั่งซื้อแล้ว",
    "preparing"  => "กำลังเตรียมสินค้า",
    "ready"      => "พร้อมรับสินค้า",
    "completed" => "เสร็จสิ้น",
    "cancelled" => "ยกเลิกคำสั่งซื้อ"
];

$order_status = $order_labels[$order["status"] ?? ""] 
    ?? ($order["status"] ?? "ไม่ระบุ");

$total_amount = (float) $order["total_amount"];
$deposit_amount = (float) $order["deposit_amount"];
$balance_amount = (float) $order["balance_amount"];

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ใบสรุปคำสั่งซื้อ | KLONG 6 CAFE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --pink: #b72e4b;
            --pink-dark: #8f0d2d;
            --pink-light: #ffd6e2;
            --pink-pale: #fff7f8;
            --text: #632b3b;
            --white: #fff;
            --border: #f0cbd5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            background: var(--pink-pale);
            color: var(--text);
            font-family: "Mali", sans-serif;
        }

        .receipt {
            width: 100%;
            max-width: 760px;
            margin: auto;
            padding: 35px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(143, 13, 45, 0.08);
        }

        .header {
            text-align: center;
            padding-bottom: 22px;
            border-bottom: 2px dashed var(--border);
        }

        .logo {
            margin: 0;
            color: var(--pink-dark);
            font-size: 30px;
            font-weight: 700;
        }

        .subtitle {
            margin-top: 5px;
            font-size: 14px;
        }

        .receipt-title {
            margin-top: 22px;
            color: var(--pink);
            font-size: 22px;
            font-weight: 700;
        }

        .order-number {
            margin-top: 8px;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .section {
            margin-top: 25px;
        }

        .section h2 {
            margin: 0 0 13px;
            padding-bottom: 8px;
            color: var(--pink-dark);
            font-size: 17px;
            border-bottom: 1px solid var(--border);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 7px 0;
            font-size: 14px;
        }

        .info-row span:last-child {
            text-align: right;
            overflow-wrap: anywhere;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #f5e2e7;
        }

        th {
            background: var(--pink-pale);
            color: var(--pink-dark);
            white-space: nowrap;
        }

        .number {
            text-align: right;
            white-space: nowrap;
        }

        .total-box {
            margin-top: 20px;
            padding: 18px;
            background: var(--pink-pale);
            border-radius: 12px;
        }

        .grand-total {
            margin-top: 8px;
            padding-top: 12px;
            border-top: 1px dashed var(--border);
            color: var(--pink-dark);
            font-size: 20px;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            background: var(--pink-light);
            border-radius: 20px;
            font-size: 12px;
        }

        .notice {
            margin-top: 20px;
            padding: 12px;
            background: #fff8e8;
            border: 1px solid #f1dfb4;
            border-radius: 10px;
            font-size: 12px;
            line-height: 1.8;
        }

        .note {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .footer {
            margin-top: 30px;
            padding-top: 18px;
            border-top: 2px dashed var(--border);
            text-align: center;
            font-size: 13px;
            line-height: 1.9;
        }

        .actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin: 25px auto 0;
        }

        .btn {
            display: inline-block;
            padding: 11px 18px;
            border: none;
            border-radius: 25px;
            font-family: inherit;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--pink);
            color: white;
        }

        .btn-secondary {
            background: var(--pink-light);
            color: var(--pink-dark);
        }

        @media (max-width: 520px) {
            body {
                padding: 15px 8px;
            }

            .receipt {
                padding: 20px 13px;
            }

            .logo {
                font-size: 24px;
            }

            .info-row {
                font-size: 12px;
            }

            table {
                font-size: 11px;
            }

            th, td {
                padding: 9px 5px;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                padding: 0;
                background: white;
                color: #222;
            }

            .receipt {
                max-width: none;
                padding: 0;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }

            .actions {
                display: none !important;
            }

            .section,
            .total-box,
            .notice,
            .header {
                break-inside: avoid;
            }

            .total-box {
                background: #fff7f8 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            th {
                background: #fff7f8 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
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

<div class="receipt">

    <div class="header">
        <h1 class="logo">🍒 KLONG 6 CAFE</h1>
        <div class="subtitle">CAKES • DRINKS • GOOD VIBES</div>

        <div class="receipt-title">ใบสรุปคำสั่งซื้อ</div>

        <div class="order-number">
            หมายเลขคำสั่งซื้อ:
            <strong><?= e($order["order_id"]) ?></strong>
        </div>

        <div class="order-number">
            วันที่สั่งซื้อ:
            <?= e(date("d/m/Y H:i", strtotime($order["order_date"]))) ?>
        </div>
    </div>

    <div class="section">
        <h2>รายละเอียดการรับสินค้า</h2>

        <div class="info-row">
            <span>วันที่รับสินค้า</span>
            <strong><?= e(formatDate($order["pickup_date"])) ?></strong>
        </div>

        <div class="info-row">
            <span>เวลารับสินค้า</span>
            <strong><?= e(formatTime($order["pickup_time"])) ?></strong>
        </div>

        <div class="info-row">
            <span>สถานะคำสั่งซื้อ</span>
            <span class="status"><?= e($order_status) ?></span>
        </div>

        <div class="info-row">
            <span>สถานะการชำระเงิน</span>
            <span class="status"><?= e($payment_status) ?></span>
        </div>
    </div>

    <div class="section">
        <h2>รายการสินค้า</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th class="number">ราคา/ชิ้น</th>
                        <th class="number">จำนวน</th>
                        <th class="number">รวม</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <?= e($item["product_name"]) ?>
                                    <br>
                                    <small><?= e($item["product_type"]) ?></small>
                                </td>

                                <td class="number">
                                    <?= number_format((float) $item["unit_price"], 2) ?>
                                </td>

                                <td class="number">
                                    <?= (int) $item["quantity"] ?>
                                </td>

                                <td class="number">
                                    <?= number_format((float) $item["subtotal"], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center;">
                                ไม่พบรายการสินค้า
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="total-box">
            <div class="info-row">
                <span>ยอดรวมทั้งหมด</span>
                <strong><?= number_format($total_amount, 2) ?> บาท</strong>
            </div>

            <div class="info-row">
                <span>ยอดมัดจำ (50%)</span>
                <strong><?= number_format($deposit_amount, 2) ?> บาท</strong>
            </div>

            <div class="info-row grand-total">
                <span>ยอดคงเหลือ</span>
                <span><?= number_format($balance_amount, 2) ?> บาท</span>
            </div>
        </div>
    </div>

    <?php if (!empty($order["note"])): ?>
        <div class="section">
            <h2>หมายเหตุจากลูกค้า</h2>
            <div class="note"><?= e($order["note"]) ?></div>
        </div>
    <?php endif; ?>

    <div class="notice">
        <strong>หมายเหตุ:</strong>
        ใบสรุปนี้แสดงรายละเอียดคำสั่งซื้อเท่านั้น
        สถานะการชำระเงินจะแสดงตามข้อมูลในระบบ
        หากขึ้นว่า “รอตรวจสอบสลิป” กรุณารอร้านตรวจสอบก่อน
        และยอดคงเหลือให้ชำระตามเงื่อนไขของทางร้าน
    </div>

    <div class="footer">
        ขอบคุณที่เลือกใช้บริการ<br>
        <strong>KLONG 6 CAFE 🍒</strong><br>
        ขอให้มีความสุขกับขนมและเครื่องดื่มของเรานะคะ
    </div>

</div>

<div class="actions">
    <button class="btn btn-primary" onclick="window.print()">
        🖨️ พิมพ์ / บันทึกเป็น PDF
    </button>

    <a class="btn btn-secondary" href="index.php">
        🏠 กลับหน้าหลัก
    </a>
</div>

</body>
</html>
