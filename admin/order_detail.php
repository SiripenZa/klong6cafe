<?php
session_start();
require_once __DIR__ . "/../conn.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: admin_login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

if (empty($_SESSION["orders_csrf_token"])) {
    $_SESSION["orders_csrf_token"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["orders_csrf_token"];

function h($v): string { return htmlspecialchars((string)($v ?? ""), ENT_QUOTES, "UTF-8"); }

$orderStatuses = [
    "pending" => "รอดำเนินการ", "confirmed" => "ยืนยันคำสั่งซื้อแล้ว",
    "preparing" => "กำลังเตรียมสินค้า", "ready" => "พร้อมรับสินค้า",
    "completed" => "รับสินค้าเรียบร้อย", "cancelled" => "ยกเลิกคำสั่งซื้อ"
];
$paymentStatuses = [
    "pending" => "รอชำระเงิน", "pending_review" => "รอตรวจสอบสลิป",
    "verified" => "ตรวจสอบสลิปแล้ว", "paid" => "ชำระเงินครบแล้ว", "rejected" => "สลิปไม่ผ่าน"
];

$orderId = trim((string)($_GET["id"] ?? $_POST["order_id"] ?? ""));
if ($orderId === "") {
    header("Location: orders.php");
    exit;
}

$error = "";

/* ===== จัดการสถานะ / ตรวจสอบสลิป (ย้ายมาจาก orders.php) ===== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string)($_POST["action"] ?? "");
    try {
        if (!hash_equals($csrfToken, (string)($_POST["csrf_token"] ?? ""))) {
            throw new Exception("คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่");
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare(
            "SELECT order_id, status, payment_status, slip_path
             FROM orders WHERE order_id = ? LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $cur = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cur) throw new Exception("ไม่พบคำสั่งซื้อที่ต้องการแก้ไข");

        if ($action === "update_status") {
            $status = (string)($_POST["status"] ?? "");
            $payment = (string)($_POST["payment_status"] ?? "");

            if (!isset($orderStatuses[$status])) throw new Exception("สถานะคำสั่งซื้อไม่ถูกต้อง");
            if (!isset($paymentStatuses[$payment])) throw new Exception("สถานะการชำระเงินไม่ถูกต้อง");

            $old = (string)$cur["payment_status"];
            $new = $old;

            if ($payment !== $old) {
                if (in_array($payment, ["verified", "paid"], true)) {
                    throw new Exception("กรุณาใช้ปุ่มอนุมัติสลิปก่อนเปลี่ยนสถานะการชำระเงิน");
                }
                if (in_array($old, ["verified", "paid"], true)) {
                    throw new Exception("สถานะการชำระเงินนี้ผ่านการตรวจสอบแล้ว ไม่สามารถเปลี่ยนจากหน้านี้ได้");
                }
                $new = $payment;
            }

            $stmt = $conn->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE order_id = ?");
            $stmt->bind_param("sss", $status, $new, $orderId);
            $stmt->execute();
            $stmt->close();

        } elseif ($action === "approve_slip" || $action === "reject_slip") {
            if (trim((string)($cur["slip_path"] ?? "")) === "") {
                throw new Exception("ออเดอร์นี้ยังไม่มีสลิปให้ตรวจสอบ");
            }

            if ($action === "approve_slip") {
                $sql = "UPDATE orders
                        SET payment_status = 'verified',
                            status = CASE WHEN status = 'pending' THEN 'confirmed' ELSE status END
                        WHERE order_id = ? AND payment_status IN ('pending_review','rejected')
                          AND slip_path IS NOT NULL AND slip_path <> ''";
            } else {
                $sql = "UPDATE orders SET payment_status = 'rejected'
                        WHERE order_id = ? AND payment_status IN ('pending_review','verified')
                          AND slip_path IS NOT NULL AND slip_path <> ''";
            }
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $orderId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected !== 1) throw new Exception("ดำเนินการกับสลิปไม่สำเร็จ กรุณาตรวจสอบสถานะปัจจุบัน");
        } else {
            throw new Exception("ไม่พบคำสั่งที่ต้องการดำเนินการ");
        }

        $conn->commit();
        header("Location: order_detail.php?id=" . urlencode($orderId) . "&updated=1");
        exit;

    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        $error = $e->getMessage();
    }
}

/* ===== ดึงข้อมูลออเดอร์ ===== */
$stmt = $conn->prepare("
    SELECT o.*,
        COALESCE(NULLIF(o.customer_name,''),
                 NULLIF(TRIM(CONCAT(COALESCE(c.firstname,''),' ',COALESCE(c.lastname,''))),'')) AS cname,
        COALESCE(NULLIF(o.email,''), c.email) AS cemail,
        COALESCE(NULLIF(o.telephone,''), c.telephone) AS ctel
    FROM orders o
    LEFT JOIN (
        SELECT customer_id, MAX(firstname) AS firstname, MAX(lastname) AS lastname,
               MAX(email) AS email, MAX(telephone) AS telephone
        FROM customers GROUP BY customer_id
    ) c ON CAST(o.customer_id AS CHAR) = CAST(c.customer_id AS CHAR)
    WHERE o.order_id = ? LIMIT 1
");
$stmt->bind_param("s", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

$items = [];
if ($order) {
    $stmt = $conn->prepare("
        SELECT oi.order_item_id, oi.cake_id, oi.drink_id, oi.quantity, oi.unit_price, oi.subtotal,
            CASE
                WHEN oi.cake_id IS NOT NULL AND oi.cake_id <> ''
                    THEN COALESCE(NULLIF(ca.cake_name,''), CONCAT('เค้ก #', oi.cake_id))
                WHEN oi.drink_id IS NOT NULL AND oi.drink_id <> ''
                    THEN COALESCE(NULLIF(dr.drink_name,''), CONCAT('เครื่องดื่ม #', oi.drink_id))
                ELSE 'ไม่พบชื่อสินค้า'
            END AS product_name,
            CASE
                WHEN oi.cake_id IS NOT NULL AND oi.cake_id <> '' THEN 'เค้ก'
                WHEN oi.drink_id IS NOT NULL AND oi.drink_id <> '' THEN 'เครื่องดื่ม'
                ELSE 'สินค้า'
            END AS product_type
        FROM order_items oi
        LEFT JOIN cake ca ON ca.cake_id = oi.cake_id
        LEFT JOIN drink dr ON dr.drink_id = oi.drink_id
        WHERE oi.order_id = ?
        ORDER BY oi.order_item_id
    ");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();

$slip = $order ? trim((string)($order["slip_path"] ?? "")) : "";
$slipUrl = $slip !== "" ? "../" . ltrim($slip, "/") : "";
$slipIsImage = $slip !== "" && in_array(strtolower(pathinfo($slip, PATHINFO_EXTENSION)), ["jpg","jpeg","png","webp"], true);
$pay = $order["payment_status"] ?? "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รายละเอียดคำสั่งซื้อ #<?= h($orderId) ?> | KLONG 6 CAFE</title>
<link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;background:linear-gradient(180deg,#fff1f6,#fff8fa 45%,#fff);color:#632b3b;font-family:'Mali',cursive;min-height:100vh}
header{background:linear-gradient(120deg,#ff71a4f2,#ffb7cdf2);padding:22px 5%;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;box-shadow:0 5px 25px #c1123f1f}
.brand{font-size:28px;font-weight:700;color:#fff;text-shadow:0 3px 10px #9600322e}
.subtitle{color:#fff;font-size:14px;letter-spacing:1px}
.logout-btn{background:#ffffff29;border:1px solid #ffffffbf;color:#fff;text-decoration:none;padding:10px 20px;border-radius:30px;font-weight:700;transition:.3s}
.logout-btn:hover{background:#fff;color:#c1123f}
.container{max-width:1200px;margin:35px auto;padding:0 20px 50px}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:#ffffffcc;border:1px solid #f7d4df;border-radius:25px;padding:16px 20px;box-shadow:0 8px 30px #c1123f14}
.toolbar h2{margin:0;color:#a31643;font-size:24px}
.btn{display:inline-block;border:1px solid transparent;border-radius:30px;padding:10px 18px;text-decoration:none;font:inherit;font-size:14px;font-weight:700;cursor:pointer;text-align:center;transition:.3s}
.btn:hover{transform:translateY(-2px);box-shadow:0 7px 16px #c1123f2e}
.btn-home{background:#fff;color:#8e1637;border-color:#f3c4d1}
.btn-home:hover{background:#c1123f;color:#fff}
.btn-pink{background:#f52b7c;color:#fff}.btn-green{background:#16834a;color:#fff}.btn-red{background:#b4233d;color:#fff}
.notice{padding:14px 18px;border-radius:15px;margin:20px 0}
.success{background:#dcf7e5;color:#18743a}.error{background:#ffe0e6;color:#991033;overflow-wrap:anywhere}
.grid{display:grid;grid-template-columns:2fr 1fr;gap:22px;margin-top:22px;align-items:start}
.panel{background:#fffffff0;border-radius:25px;padding:24px;border:1px solid #f7dce4;box-shadow:0 8px 30px #c1123f14;margin-bottom:22px}
.panel h3{margin:0 0 14px;color:#a31643}
.info{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
.info small{display:block;color:#87616c;font-size:12px}
.info b{overflow-wrap:anywhere}
.badge{display:inline-block;background:#fff0f5;color:#8e1637;border-radius:20px;padding:6px 12px;font-size:13px;font-weight:700}
.badge-verified{background:#dcf7e5;color:#18743a}
.badge-pending_review{background:#ffe0e6;color:#c1123f}
.table-wrap{overflow-x:auto;border-radius:16px;border:1px solid #f7dce4}
table{width:100%;border-collapse:collapse;min-width:520px}
th,td{padding:12px;border-bottom:1px solid #f1dce3;text-align:left}
th{background:#ffe1e9;color:#8e1637;white-space:nowrap}
tbody tr:last-child td{border-bottom:0}
.num{text-align:right;white-space:nowrap}
.totals{margin-top:14px;text-align:right;line-height:1.9}
.totals .grand{font-size:22px;font-weight:700;color:#c1123f}
.slip-img{display:block;max-width:100%;max-height:420px;object-fit:contain;border-radius:14px;border:3px solid #ffe1e9;margin-bottom:10px}
.actions{display:flex;flex-direction:column;gap:8px;margin-top:12px}.actions form{margin:0}.actions .btn{width:100%;border-radius:12px}
label{display:block;margin:12px 0 6px;font-size:13px;font-weight:700}
select{width:100%;padding:10px;border:1px solid #e6bdcb;border-radius:12px;font:inherit;background:#fff;color:#632b3b}
.status-form .btn{width:100%;margin-top:16px}
.muted{color:#87616c;font-size:13px}
@media(max-width:850px){.grid{grid-template-columns:1fr}}
@media(max-width:500px){.info{grid-template-columns:1fr}}
</style>
</head>
<body>

<header>
    <div>
        <div class="brand">🍒 Klong 6 Cafe</div>
        <div class="subtitle">ADMIN PANEL</div>
    </div>
    <a href="logout.php" class="logout-btn">ออกจากระบบ</a>
</header>

<main class="container">

    <div class="toolbar">
        <h2>รายละเอียดคำสั่งซื้อ #<?= h($orderId) ?> 📦</h2>
        <a href="orders.php" class="btn btn-home">← กลับหน้ารายการคำสั่งซื้อ</a>
    </div>

    <?php if (isset($_GET["updated"])): ?>
        <div class="notice success">✓ บันทึกการเปลี่ยนแปลงเรียบร้อยแล้ว</div>
    <?php endif; ?>
    <?php if ($error !== ""): ?>
        <div class="notice error"><?= h($error) ?></div>
    <?php endif; ?>

<?php if (!$order): ?>
    <div class="panel" style="text-align:center;margin-top:22px">
        <div style="font-size:50px">📦</div>
        <strong>ไม่พบคำสั่งซื้อนี้</strong>
    </div>
<?php else: ?>

    <div class="grid">

        <!-- คอลัมน์ซ้าย: ข้อมูลทั้งหมดของออเดอร์ -->
        <div>

            <section class="panel">
                <h3>👤 ข้อมูลลูกค้า</h3>
                <div class="info">
                    <div><small>ชื่อลูกค้า</small><b><?= h(trim((string)$order["cname"]) !== "" ? $order["cname"] : "ลูกค้าทั่วไป (ไม่ระบุชื่อ)") ?></b></div>
                    <div><small>รหัสลูกค้า</small><b><?= h($order["customer_id"] ?? "-") ?></b></div>
                    <div><small>อีเมล</small><b><?= h($order["cemail"] ?: "ไม่มีอีเมล") ?></b></div>
                    <div><small>เบอร์โทร</small><b><?= h($order["ctel"] ?: "ไม่มีเบอร์โทร") ?></b></div>
                </div>
            </section>

            <section class="panel">
                <h3>🍰 รายการสินค้า</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>สินค้า</th><th>ประเภท</th><th class="num">ราคา/ชิ้น</th><th class="num">จำนวน</th><th class="num">รวม (บาท)</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($items): foreach ($items as $it): ?>
                            <tr>
                                <td><strong><?= h($it["product_name"]) ?></strong></td>
                                <td><?= h($it["product_type"]) ?></td>
                                <td class="num"><?= number_format((float)$it["unit_price"], 2) ?></td>
                                <td class="num"><?= (int)$it["quantity"] ?></td>
                                <td class="num"><?= number_format((float)$it["subtotal"], 2) ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="muted">ไม่พบรายการสินค้า</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="totals">
                    <div>ยอดรวม: <span class="grand"><?= number_format((float)$order["total_amount"], 2) ?> บาท</span></div>
                    <div class="muted">มัดจำ: <?= number_format((float)$order["deposit_amount"], 2) ?> บาท</div>
                    <div class="muted">คงเหลือ: <?= number_format((float)$order["balance_amount"], 2) ?> บาท</div>
                </div>

                <?php if (!empty($order["note"])): ?>
                    <p style="margin-top:16px"><strong>หมายเหตุ:</strong><br><?= nl2br(h($order["note"])) ?></p>
                <?php endif; ?>
            </section>

            <section class="panel">
                <h3>🗓 ข้อมูลการสั่งซื้อและรับสินค้า</h3>
                <div class="info">
                    <div><small>วันสั่งซื้อ</small><b><?= h($order["order_date"] ?? "-") ?></b></div>
                    <div><small>สถานะออเดอร์</small><span class="badge"><?= h($orderStatuses[$order["status"]] ?? $order["status"]) ?></span></div>
                    <div><small>วันที่รับสินค้า</small><b><?= h($order["pickup_date"] ?? "-") ?></b></div>
                    <div><small>เวลารับสินค้า</small><b><?= h($order["pickup_time"] ?? "-") ?></b></div>
                </div>
            </section>

        </div>

        <!-- คอลัมน์ขวา: สลิป + จัดการสถานะ -->
        <div>

            <section class="panel">
                <h3>💳 สลิปการชำระเงิน</h3>

                <?php if ($slip !== ""): ?>
                    <?php if ($slipIsImage): ?>
                        <a href="<?= h($slipUrl) ?>" target="_blank" rel="noopener noreferrer">
                            <img class="slip-img" src="<?= h($slipUrl) ?>" alt="สลิปออเดอร์ <?= h($orderId) ?>">
                        </a>
                    <?php endif; ?>
                    <a href="<?= h($slipUrl) ?>" target="_blank" rel="noopener noreferrer" class="muted">เปิดดูสลิป</a>

                    <div class="actions">
                        <?php if (in_array($pay, ["pending_review", "rejected"], true)): ?>
                            <form method="POST" onsubmit="return confirm('ยืนยันการอนุมัติสลิปนี้หรือไม่?');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="order_id" value="<?= h($orderId) ?>">
                                <input type="hidden" name="action" value="approve_slip">
                                <button type="submit" class="btn btn-green">✓ อนุมัติสลิป</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array($pay, ["pending_review", "verified"], true)): ?>
                            <form method="POST" onsubmit="return confirm('ยืนยันการปฏิเสธสลิปนี้หรือไม่?');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="order_id" value="<?= h($orderId) ?>">
                                <input type="hidden" name="action" value="reject_slip">
                                <button type="submit" class="btn btn-red">✕ ปฏิเสธสลิป</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <span class="muted">ยังไม่มีสลิป</span>
                <?php endif; ?>

                <div style="margin-top:14px">
                    <span class="badge badge-<?= h($pay) ?>"><?= h($paymentStatuses[$pay] ?? ($pay ?: "ไม่ระบุ")) ?></span>
                </div>
            </section>

            <section class="panel">
                <h3>⚙️ จัดการสถานะ</h3>
                <form method="POST" class="status-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="order_id" value="<?= h($orderId) ?>">
                    <input type="hidden" name="action" value="update_status">

                    <label for="status">สถานะออเดอร์</label>
                    <select id="status" name="status" required>
                        <?php foreach ($orderStatuses as $v => $l): ?>
                            <option value="<?= h($v) ?>" <?= ($order["status"] ?? "") === $v ? "selected" : "" ?>><?= h($l) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="payment_status">สถานะชำระเงิน</label>
                    <select id="payment_status" name="payment_status" required>
                        <?php foreach ($paymentStatuses as $v => $l): ?>
                            <option value="<?= h($v) ?>" <?= $pay === $v ? "selected" : "" ?>><?= h($l) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-pink">บันทึกสถานะ</button>
                </form>
            </section>

        </div>
    </div>

<?php endif; ?>

</main>
</body>
</html>