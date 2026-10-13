
<?php
session_start();
require_once '../conn.php';

// ตรวจสอบการเข้าสู่ระบบแอดมิน
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// ดึงข้อมูลสินค้า
$sql = "SELECT product_id, product_name, description, price, stock, image, status
        FROM products
        ORDER BY product_id DESC";

$result = $conn->query($sql);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// สร้าง URL สำหรับรูปสินค้า
function productImage($image) {
    $image = trim((string)$image);

    if ($image === '') {
        return '../cake.png';
    }

    // ถ้าฐานข้อมูลเก็บเป็น image/blueflower.png
    // หรือเก็บเป็นชื่อไฟล์ blueflower.png
    if (strpos($image, '/') === false && strpos($image, '\\') === false) {
        $image = 'image/' . $image;
    }

    // ถ้า path ไม่ได้เริ่มด้วย ../ ให้เพิ่ม path จาก admin
    if (strpos($image, '../') !== 0) {
        $image = '../' . ltrim($image, '/');
    }

    return $image;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>จัดการสินค้า | KLONG 6 CAFE</title>

    <link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #fff0f3;
            color: #632b3b;
            font-family: 'Mali', cursive;
        }

        header {
            background: #ffd6e2;
            padding: 25px 20px;
            text-align: center;
        }

        header h1 {
            margin: 0;
            color: #c1123f;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 18px;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .toolbar h2 {
            margin: 0;
            color: #c1123f;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 25px;
            padding: 10px 20px;
            text-decoration: none;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
        }

        .btn-add {
            background: #c1123f;
            color: white;
        }

        .btn-edit {
            background: #ffb6c8;
            color: #632b3b;
        }

        .btn-delete {
            background: #222;
            color: white;
        }

        .btn-home {
            background: white;
            color: #632b3b;
        }

        .table-wrap {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 4px 18px rgba(193, 18, 63, .08);
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid #f3dce3;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #ffd6e2;
            color: #8e1637;
        }

        .product-image {
            width: 85px;
            height: 85px;
            object-fit: cover;
            border-radius: 10px;
            background: #fff0f3;
            display: block;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            background: #dcf7e5;
            color: #18743a;
        }

        .status-unavailable {
            background: #eee;
            color: #666;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empty {
            text-align: center;
            padding: 35px;
        }

        @media (max-width: 600px) {
            header h1 {
                font-size: 24px;
            }

            .container {
                margin-top: 20px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>🍒 KLONG 6 CAFE 🍰</h1>
    <p>จัดการสินค้าในร้าน</p>
</header>

<main class="container">

    <div class="toolbar">
        <h2>📦 รายการสินค้า</h2>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="dashboard.php" class="btn btn-home">
                กลับหน้าแอดมิน
            </a>

            <a href="add_product.php" class="btn btn-add">
                + เพิ่มสินค้า
            </a>
        </div>
    </div>

    <div class="table-wrap">

        <table>
            <thead>
                <tr>
                    <th>รูปสินค้า</th>
                    <th>ชื่อสินค้า</th>
                    <th>ราคา</th>
                    <th>สต็อก</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($product = $result->fetch_assoc()): ?>

                    <?php
                    $imageUrl = productImage($product['image'] ?? '');
                    ?>

                    <tr>
                        <td>
                            <img
                                class="product-image"
                                src="<?= h($imageUrl) ?>"
                                alt="<?= h($product['product_name']) ?>"
                                onerror="this.onerror=null;this.src='../cake.png';"
                            >
                        </td>

                        <td>
                            <strong><?= h($product['product_name']) ?></strong>
                        </td>

                        <td>
                            <?= number_format((float)$product['price'], 2) ?> บาท
                        </td>

                        <td>
                            <?= (int)$product['stock'] ?>
                        </td>

                        <td>
                            <?php if ($product['status'] === 'available'): ?>
                                <span class="status">พร้อมขาย</span>
                            <?php else: ?>
                                <span class="status status-unavailable">
                                    ไม่พร้อมขาย
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="actions">

                                <a
                                    class="btn btn-edit"
                                    href="edit_product.php?id=<?= urlencode($product['product_id']) ?>"
                                >
                                    แก้ไข
                                </a>

                                <form
                                    action="delete_product.php"
                                    method="POST"
                                    onsubmit="return confirm('ยืนยันการลบสินค้านี้หรือไม่?');"
                                    style="margin:0;"
                                >
                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= h($product['product_id']) ?>"
                                    >

                                    <button type="submit" class="btn btn-delete">
                                        ลบ
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="empty">
                        <div style="font-size:45px;">🍰</div>
                        <strong>ยังไม่มีสินค้าในระบบ</strong>
                        <p>กดปุ่มเพิ่มสินค้าเพื่อเริ่มต้น</p>
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

</main>

</body>
</html>

<?php
$conn->close();
?>
