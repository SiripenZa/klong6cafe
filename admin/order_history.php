<?php
session_start();
require_once "../conn.php";

// จำกัดการเข้าถึงเฉพาะผู้ดูแลระบบ
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin_login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function h($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// หน้านี้แสดงเฉพาะออเดอร์ที่ลูกค้ารับสินค้าเรียบร้อยแล้ว
$search = trim((string)($_GET["search"] ?? ""));
$keyword = "%" . $search . "%";

$sql = "
    SELECT
        o.order_id, o.customer_id, o.order_date, o.pickup_date, o.pickup_time,
        o.total_amount, o.deposit_amount, o.balance_amount, o.payment_status,
        o.status, o.note,
        c.firstname, c.lastname, c.email, c.telephone
    FROM orders o
    LEFT JOIN (
        SELECT customer_id,
               MAX(firstname) AS firstname,
               MAX(lastname) AS lastname,
               MAX(email) AS email,
               MAX(telephone) AS telephone
        FROM customers
        GROUP BY customer_id
    ) c ON CAST(o.customer_id AS CHAR) = CAST(c.customer_id AS CHAR)
    WHERE o.status = 'completed'
      AND (
          ? = ''
          OR CAST(o.order_id AS CHAR) LIKE ?
          OR COALESCE(c.firstname, '') LIKE ?
          OR COALESCE(c.lastname, '') LIKE ?
          OR COALESCE(c.email, '') LIKE ?
          OR COALESCE(c.telephone, '') LIKE ?
      )
    ORDER BY o.pickup_date DESC, o.pickup_time DESC, o.order_date DESC, o.order_id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $search, $keyword, $keyword, $keyword, $keyword, $keyword);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[(string)$row["order_id"]] = $row;
}
$stmt->close();
$orders = array_values($orders);

// ดึงรายการสินค้าแยกจากออเดอร์ เพื่อไม่ให้จำนวนแถวออเดอร์ซ้ำ
$orderItems = [];
$orderIds = array_map(static fn($order) => (string)$order["order_id"], $orders);
if ($orderIds) {
    $placeholders = implode(",", array_fill(0, count($orderIds), "?"));
    $types = str_repeat("s", count($orderIds));
    $itemSql = "
        SELECT oi.order_item_id, oi.order_id, oi.cake_id, oi.drink_id,
               oi.quantity, oi.unit_price, oi.subtotal,
               ca.cake_name, dr.drink_name
        FROM order_items oi
        LEFT JOIN cake ca ON oi.cake_id = ca.cake_id
        LEFT JOIN drink dr ON oi.drink_id = dr.drink_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.order_id, oi.order_item_id
    ";
    $itemStmt = $conn->prepare($itemSql);
    $itemStmt->bind_param($types, ...$orderIds);
    $itemStmt->execute();
    $itemsResult = $itemStmt->get_result();
    while ($item = $itemsResult->fetch_assoc()) {
        $id = (string)$item["order_id"];
        if (!isset($orderItems[$id])) $orderItems[$id] = [];
        if (!empty($item["cake_id"])) {
            $item["product_name"] = $item["cake_name"] ?: ("เค้ก #" . $item["cake_id"]);
        } elseif (!empty($item["drink_id"])) {
            $item["product_name"] = $item["drink_name"] ?: ("เครื่องดื่ม #" . $item["drink_id"]);
        } else {
            $item["product_name"] = "ไม่พบรหัสสินค้า";
        }
        $orderItems[$id][] = $item;
    }
    $itemStmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ประวัติการสั่งซื้อ | KLONG 6 CAFE</title>
<link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:linear-gradient(180deg,#fff1f6 0%,#fff8fa 45%,#fff 100%);color:#632b3b;font-family:'Mali',cursive;min-height:100vh}header{background:linear-gradient(120deg,#ff71a4,#ffb7cd);padding:22px 5%;display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;box-shadow:0 5px 25px #c1123f1f}.brand{font-size:28px;font-weight:700;color:#fff}.subtitle{color:#fff;font-size:14px;letter-spacing:1px}.container{max-width:1450px;margin:35px auto;padding:0 20px 50px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;background:#ffffffdc;border:1px solid #f7d4df;border-radius:22px;padding:18px 20px;box-shadow:0 8px 30px #c1123f14}.toolbar h1{margin:0;color:#a31643;font-size:24px}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-block;border:0;border-radius:30px;padding:10px 17px;text-decoration:none;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;text-align:center;transition:.2s}.btn:hover{transform:translateY(-2px)}.btn-home{background:#fff;color:#8e1637;border:1px solid #f3c4d1}.btn-pink{background:#f52b7c;color:#fff}.btn-light{background:#ffe1e9;color:#8f0d2d}.description{color:#87616c;margin:18px 0}.panel{background:#fffffff0;border-radius:22px;padding:22px;margin:20px 0;box-shadow:0 8px 30px #c1123f14;border:1px solid #f7dce4}.search-form{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.search-field{flex:1;min-width:220px}label{display:block;margin-bottom:6px;font-size:13px;font-weight:700}input{width:100%;padding:11px;border:1px solid #e6bdcb;border-radius:12px;font:inherit;font-size:14px;background:#fff;color:#632b3b;outline:none}input:focus{border-color:#f52b7c;box-shadow:0 0 0 3px #f52b7c1a}.table-wrap{overflow-x:auto;border-radius:16px;border:1px solid #f7dce4}table{width:100%;border-collapse:collapse;min-width:1100px}th,td{padding:14px;border-bottom:1px solid #f1dce3;text-align:left;vertical-align:top}th{background:#ffe1e9;color:#8e1637;white-space:nowrap}tbody tr:hover{background:#fff7fa}.order-id{font-weight:700;color:#a31643}.muted{color:#87616c;font-size:12px}.amount{white-space:nowrap;font-weight:700;color:#c1123f}.badge{display:inline-block;background:#e0f8e8;color:#18743a;border-radius:20px;padding:6px 10px;font-size:12px;font-weight:700;white-space:nowrap}.item{padding:0 0 8px;margin-bottom:8px;border-bottom:1px dashed #f0d0da}.item:last-child{border:0;margin:0;padding:0}.empty{text-align:center;padding:45px 20px;color:#87616c}.empty-icon{font-size:45px}@media(max-width:600px){.container{padding:0 12px 35px;margin-top:25px}.toolbar{align-items:stretch}.actions{width:100%}.actions .btn{flex:1}.panel{padding:15px}.toolbar h1{font-size:21px}}
</style>
</head>
<body>
<header>
    <div><div class="brand">🍒 Klong 6 Cafe</div><div class="subtitle">ORDER HISTORY • ADMIN PANEL</div></div>
</header>
<main class="container">
    <div class="toolbar">
        <h1>🕘 ประวัติการสั่งซื้อ</h1>
        <div class="actions">
            <a href="orders.php" class="btn btn-home">← กลับจัดการคำสั่งซื้อ</a>
            <a href="dashboard.php" class="btn btn-light">หน้าแอดมิน</a>
        </div>
    </div>
    <p class="description">แสดงเฉพาะคำสั่งซื้อที่มีสถานะ “รับสินค้าเรียบร้อย” (completed) เรียงจากวันที่รับสินค้าล่าสุด</p>

    <section class="panel">
        <form method="GET" class="search-form">
            <div class="search-field"><label for="search">ค้นหาเลขออเดอร์ / ชื่อลูกค้า / อีเมล / เบอร์โทร</label><input id="search" name="search" type="text" value="<?= h($search) ?>" placeholder="พิมพ์คำค้นหา..."></div>
            <button class="btn btn-pink" type="submit">ค้นหา</button>
            <a href="order_history.php" class="btn btn-light">ล้างค่า</a>
        </form>
    </section>

    <section class="panel">
        <p class="muted">พบ <?= count($orders) ?> ออเดอร์ที่รับสินค้าเรียบร้อยแล้ว</p>
        <div class="table-wrap"><table>
            <thead><tr><th>เลขออเดอร์ / ลูกค้า</th><th>วันสั่งซื้อ</th><th>รายการสินค้า</th><th>วันที่และเวลารับสินค้า</th><th>ยอดรวม / มัดจำ / คงเหลือ</th><th>การชำระเงิน</th><th>สถานะ</th></tr></thead>
            <tbody>
            <?php if ($orders): foreach ($orders as $order): ?>
                <?php
                $id = (string)$order["order_id"];
                $customerName = trim((string)($order["firstname"] ?? "") . " " . (string)($order["lastname"] ?? ""));
                $items = $orderItems[$id] ?? [];
                ?>
                <tr>
                    <td><div class="order-id">#<?= h($id) ?></div><strong><?= h($customerName !== "" ? $customerName : "ลูกค้า #" . ($order["customer_id"] ?? "-")) ?></strong><div class="muted"><?= h($order["email"] ?? "ไม่มีอีเมล") ?></div><div class="muted"><?= h($order["telephone"] ?? "ไม่มีเบอร์โทร") ?></div></td>
                    <td><?= h($order["order_date"] ?? "-") ?></td>
                    <td><?php if ($items): foreach ($items as $item): ?><div class="item"><strong><?= h($item["product_name"] ?? "สินค้าไม่พบชื่อ") ?></strong><br><span class="muted"><?= (int)($item["quantity"] ?? 0) ?> ชิ้น × <?= number_format((float)($item["unit_price"] ?? 0), 2) ?> บาท</span><br>รวม <?= number_format((float)($item["subtotal"] ?? 0), 2) ?> บาท</div><?php endforeach; else: ?><span class="muted">ไม่พบรายการสินค้า</span><?php endif; ?><?php if (!empty($order["note"])): ?><div class="muted" style="margin-top:8px"><strong>หมายเหตุ:</strong> <?= nl2br(h($order["note"])) ?></div><?php endif; ?></td>
                    <td><strong><?= h($order["pickup_date"] ?? "-") ?></strong><br><?= h($order["pickup_time"] ?? "-") ?></td>
                    <td><div class="amount"><?= number_format((float)($order["total_amount"] ?? 0), 2) ?> บาท</div><div class="muted" style="margin-top:7px">มัดจำ: <?= number_format((float)($order["deposit_amount"] ?? 0), 2) ?> บาท</div><div class="muted" style="margin-top:5px">คงเหลือ: <?= number_format((float)($order["balance_amount"] ?? 0), 2) ?> บาท</div></td>
                    <td><?= h($order["payment_status"] ?? "ไม่ระบุ") ?></td>
                    <td><span class="badge">✓ รับสินค้าเรียบร้อย</span></td>
                </tr>
            <?php endforeach; else: ?><tr><td colspan="7" class="empty"><div class="empty-icon">📦</div><strong>ยังไม่มีประวัติคำสั่งซื้อที่รับสินค้าเรียบร้อย</strong><p>ลองค้นหาด้วยคำอื่น หรือตรวจสอบว่าออเดอร์ถูกเปลี่ยนสถานะเป็น completed แล้ว</p></td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</main>
</body>
</html>
