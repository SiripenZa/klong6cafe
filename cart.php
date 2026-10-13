
<?php
session_start();
require_once 'conn.php';

// ทำให้ข้อมูลตะกร้าเป็นรูปแบบอาร์เรย์ที่ถูกต้อง
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// จัดการเพิ่มสินค้า ลบสินค้า และอัปเดตจำนวน
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // เพิ่มสินค้า
    if ($action === 'add') {
        $product_id = trim((string)($_POST['product_id'] ?? ''));
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        $stmt = $conn->prepare(
            "SELECT product_id, product_name, price, stock, image
             FROM products
             WHERE product_id = ? AND status = 'available'"
        );

        $stmt->bind_param("s", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();

        if ($product && (int)$product['stock'] > 0) {
            $id = (string)$product['product_id'];
            $cart = $_SESSION['cart'];

            // รองรับข้อมูลตะกร้ารูปแบบเก่าที่เก็บเป็นตัวเลข
            $old_quantity = 0;

            if (isset($cart[$id]) && is_array($cart[$id])) {
                $old_quantity = (int)($cart[$id]['quantity'] ?? 0);
            } elseif (isset($cart[$id]) && is_numeric($cart[$id])) {
                $old_quantity = (int)$cart[$id];
            }

            $new_quantity = min(
                $old_quantity + $quantity,
                (int)$product['stock']
            );

            $cart[$id] = [
                'product_id' => $id,
                'product_name' => $product['product_name'],
                'price' => (float)$product['price'],
                'image' => $product['image'] ?? '',
                'quantity' => $new_quantity
            ];

            $_SESSION['cart'] = $cart;
        }

        header('Location: cart.php');
        exit;
    }

    // ลบสินค้า
    if ($action === 'remove') {
        $id = (string)($_POST['product_id'] ?? '');

        unset($_SESSION['cart'][$id]);

        header('Location: cart.php');
        exit;
    }

    // อัปเดตจำนวนสินค้า
    if ($action === 'update') {
        $quantities = $_POST['quantity'] ?? [];
        $cart = $_SESSION['cart'];

        foreach ($quantities as $id => $qty) {
            $id = (string)$id;

            if (!isset($cart[$id]) || !is_array($cart[$id])) {
                continue;
            }

            $stmt = $conn->prepare(
                "SELECT product_id, product_name, price, stock, image, status
                 FROM products
                 WHERE product_id = ?"
            );

            $stmt->bind_param("s", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();

            if (
                !$product ||
                $product['status'] !== 'available' ||
                (int)$product['stock'] < 1
            ) {
                unset($cart[$id]);
                continue;
            }

            $qty = max(1, (int)$qty);
            $qty = min($qty, (int)$product['stock']);

            // อัปเดตข้อมูลจากฐานข้อมูลให้เป็นปัจจุบัน
            $cart[$id] = [
                'product_id' => (string)$product['product_id'],
                'product_name' => $product['product_name'],
                'price' => (float)$product['price'],
                'image' => $product['image'] ?? '',
                'quantity' => $qty
            ];
        }

        $_SESSION['cart'] = $cart;

        header('Location: cart.php');
        exit;
    }
}

// ตรวจสอบข้อมูลตะกร้า และแปลงรูปแบบเก่าที่ไม่ถูกต้อง
$cart = $_SESSION['cart'];
$clean_cart = [];

foreach ($cart as $id => $item) {
    if (!is_array($item)) {
        // รูปแบบเก่าเก็บเป็นตัวเลข จึงดึงข้อมูลสินค้าจากฐานข้อมูล
        if (!is_numeric($item)) {
            continue;
        }

        $old_qty = max(1, (int)$item);

        $stmt = $conn->prepare(
            "SELECT product_id, product_name, price, stock, image
             FROM products
             WHERE product_id = ? AND status = 'available'"
        );

        $id_string = (string)$id;
        $stmt->bind_param("s", $id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();

        if (!$product || (int)$product['stock'] < 1) {
            continue;
        }

        $clean_cart[$id_string] = [
            'product_id' => $id_string,
            'product_name' => $product['product_name'],
            'price' => (float)$product['price'],
            'image' => $product['image'] ?? '',
            'quantity' => min($old_qty, (int)$product['stock'])
        ];

        continue;
    }

    if (
        !isset($item['product_id']) ||
        !isset($item['product_name']) ||
        !isset($item['price']) ||
        !isset($item['quantity'])
    ) {
        continue;
    }

    $clean_cart[(string)$id] = $item;
}

$_SESSION['cart'] = $clean_cart;
$cart = $clean_cart;
$total = 0;

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตะกร้าสินค้า | KLONG 6 CAFE</title>

    <link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
          rel="stylesheet">
</head>

<body style="margin:0;background:#fff0f3;color:#632b3b;font-family:'Mali',cursive;">

<header style="background:#ffd6e2;text-align:center;padding:30px 15px;">
    <h1 style="color:#c1123f;margin:0;">🍒 KLONG 6 CAFE 🍰</h1>
    <p>ตะกร้าสินค้าของคุณ 🛒</p>
</header>

<nav style="
    display:flex;
    justify-content:center;
    align-items:center;
    flex-wrap:wrap;
    gap:8px;
    padding:18px 10px;
    font-family:'Mali',cursive;
">

    <a href="index.php" style="
        display:inline-flex;
        align-items:center;
        gap:7px;
        background:#c1294d;
        color:#fff;
        padding:10px 18px;
        border-radius:50px;
        text-decoration:none;
        font-size:14px;
        font-weight:700;
        white-space:nowrap;
    ">
        ⌂ หน้าหลัก
    </a>

    <a href="cake.php" style="
        display:inline-flex;
        align-items:center;
        gap:7px;
        background:#c1294d;
        color:#fff;
        padding:10px 18px;
        border-radius:50px;
        text-decoration:none;
        font-size:14px;
        font-weight:700;
        white-space:nowrap;
    ">
        🍰 เค้ก
    </a>

    <a href="bread.php" style="
        display:inline-flex;
        align-items:center;
        gap:7px;
        background:transparent;
        color:#632b3b;
        padding:10px 18px;
        border-radius:50px;
        text-decoration:none;
        font-size:14px;
        font-weight:700;
        white-space:nowrap;
    ">
        🥐 ขนมปัง
    </a>

    <a href="drink.php" style="
        display:inline-flex;
        align-items:center;
        gap:7px;
        background:transparent;
        color:#632b3b;
        padding:10px 18px;
        border-radius:50px;
        text-decoration:none;
        font-size:14px;
        font-weight:700;
        white-space:nowrap;
    ">
        🥤 เครื่องดื่ม
    </a>

</nav>

<main style="max-width:1000px;margin:0 auto;padding:20px;">
    <h2 style="color:#c1123f;text-align:center;">🛒 สินค้าในตะกร้า</h2>

    <?php if (empty($cart)): ?>

        <div style="background:white;border-radius:20px;padding:45px 15px;text-align:center;">
            <div style="font-size:55px;">🧁</div>
            <h3>ยังไม่มีสินค้าในตะกร้า</h3>
            <p>เลือกขนมและเครื่องดื่มที่คุณชอบได้เลย 💗</p>

            <a href="cake.php"
               style="display:inline-block;background:#c1123f;color:white;padding:12px 25px;border-radius:30px;text-decoration:none;">
                เลือกซื้อสินค้า
            </a>
        </div>

    <?php else: ?>

        <form method="post" action="cart.php">
            <input type="hidden" name="action" value="update">

            <div style="overflow-x:auto;background:white;border-radius:18px;padding:12px;">
                <table style="width:100%;border-collapse:collapse;min-width:600px;">
                    <thead>
                        <tr style="background:#ffd6e2;color:#c1123f;">
                            <th style="padding:14px;">สินค้า</th>
                            <th style="padding:14px;">ราคา</th>
                            <th style="padding:14px;">จำนวน</th>
                            <th style="padding:14px;">รวม</th>
                            <th style="padding:14px;">จัดการ</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($cart as $id => $item): ?>
                        <?php
                        $price = (float)$item['price'];
                        $quantity = max(1, (int)$item['quantity']);
                        $subtotal = $price * $quantity;
                        $total += $subtotal;
                        $image = trim((string)($item['image'] ?? ''));
                        ?>

                        <tr style="border-bottom:1px solid #f5d5df;text-align:center;">
                            <td style="padding:12px;">
                                <?php if ($image !== ''): ?>
                                    <img src="<?= h($image) ?>"
                                         alt="<?= h($item['product_name']) ?>"
                                         onerror="this.style.display='none';"
                                         style="width:65px;height:65px;object-fit:cover;border-radius:12px;vertical-align:middle;">
                                <?php endif; ?>

                                <div><?= h($item['product_name']) ?></div>
                            </td>

                            <td style="padding:12px;">
                                ฿<?= number_format($price, 2) ?>
                            </td>

                            <td style="padding:12px;">
                                <input type="number"
                                       name="quantity[<?= h($id) ?>]"
                                       value="<?= $quantity ?>"
                                       min="1"
                                       required
                                       style="width:65px;padding:8px;border:1px solid #e8b8c7;border-radius:8px;">
                            </td>

                            <td style="padding:12px;color:#c1123f;font-weight:700;">
                                ฿<?= number_format($subtotal, 2) ?>
                            </td>

                            <td style="padding:12px;">
                                <button type="button"
                                        onclick="if(confirm('ต้องการลบสินค้านี้หรือไม่?')) document.getElementById('remove-<?= h($id) ?>').submit();"
                                        style="background:#222;color:white;border:0;border-radius:20px;padding:9px 15px;cursor:pointer;font-family:inherit;">
                                    ลบ
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="text-align:right;background:white;margin-top:20px;padding:22px;border-radius:18px;">
                <p style="font-size:23px;color:#c1123f;font-weight:700;">
                    ยอดรวม ฿<?= number_format($total, 2) ?>
                </p>

                <button type="submit"
                        style="background:#ffb6cb;color:#632b3b;border:0;padding:12px 20px;border-radius:25px;font-family:inherit;font-weight:700;cursor:pointer;">
                    อัปเดตจำนวน
                </button>
            </div>
        </form>

        <?php foreach ($cart as $id => $item): ?>
            <form id="remove-<?= h($id) ?>" method="post" action="cart.php" style="display:none;">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= h($id) ?>">
            </form>
        <?php endforeach; ?>

        <div style="text-align:center;margin-top:25px;">
            <a href="cake.php"
               style="display:inline-block;background:#c1123f;color:white;padding:13px 25px;border-radius:30px;text-decoration:none;">
                เลือกซื้อสินค้าต่อ
            </a>
        </div>

    <?php endif; ?>
</main>

<footer style="background:#c1123f;color:white;text-align:center;padding:25px;">
    <h3>🍒 KLONG 6 CAFE 🍰</h3>
    <p>Coffee • Cake • Happiness ♡</p>
</footer>

</body>
</html>

<?php
$conn->close();
?>
