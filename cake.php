
<?php
session_start();
require_once 'conn.php';

// ==========================
// ข้อมูลผู้ใช้งาน
// ==========================
$firstname = $_SESSION['customer_firstname'] ?? '';
$isLoggedIn = !empty($firstname);

// สร้างตะกร้าสินค้า
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ==========================
// เพิ่มสินค้าลงตะกร้า
// ==========================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'add_to_cart'
) {
    $productId = trim($_POST['product_id'] ?? '');

    if ($productId !== '') {
        $stmt = $conn->prepare(
            "SELECT product_id FROM products WHERE product_id = ?"
        );

        if ($stmt) {
            $stmt->bind_param("s", $productId);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($product) {
                $_SESSION['cart'][$productId] =
                    ($_SESSION['cart'][$productId] ?? 0) + 1;

                header('Location: cake.php?added=1#menu');
                exit;
            }
        }
    }

    header('Location: cake.php?error=1#menu');
    exit;
}

// จำนวนสินค้าในตะกร้า
$cartCount = array_sum($_SESSION['cart']);

// ==========================
// ดึงข้อมูลสินค้าจากฐานข้อมูล
// ==========================
$products = [];
$dbError = '';

$sql = "SELECT
            product_id,
            category_id,
            product_name,
            description,
            price,
            stock,
            image,
            status
        FROM products
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    $dbError = $conn->error;
}

// ==========================
// แบ่งหน้า หน้าละ 10 สินค้า
// ==========================
$perPage = 10;
$totalProducts = count($products);
$totalPages = max(1, (int)ceil($totalProducts / $perPage));

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$currentPage = min($currentPage, $totalPages);

$products = array_slice(
    $products,
    ($currentPage - 1) * $perPage,
    $perPage
);

// ป้องกัน HTML ที่ไม่ปลอดภัย
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cake Menu | KLONG 6 CAFE</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
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
        }

        * {
            box-sizing: border-box;
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

        .announcement {
            background: var(--red);
            color: white;
            text-align: center;
            padding: 9px 12px;
            font-size: 13px;
        }

        header {
            background: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .logo {
            color: var(--red);
            font-size: 24px;
            font-weight: 700;
        }

        .logo small {
            display: block;
            font-size: 10px;
            color: var(--text);
            letter-spacing: 2px;
            text-align: center;
        }

        .account {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 12px;
        }

        .account a {
            padding: 8px 12px;
            border: 1px solid var(--red);
            border-radius: 25px;
            color: var(--red);
        }

        .account .cart-link {
            color: white;
            background: var(--red);
        }

        nav {
            background: var(--pink);
            padding: 12px 5%;
            display: flex;
            justify-content: center;
            gap: 28px;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        nav a {
            font-size: 14px;
            font-weight: 600;
            transition: .2s;
        }

        nav a:hover,
        nav a.active {
            color: var(--red);
        }

        .hero {
            text-align: center;
            padding: 42px 18px;
            background: linear-gradient(135deg, #fff0f4, #ffe2e9);
        }

        .tag {
            display: inline-block;
            background: white;
            color: var(--red);
            border-radius: 30px;
            padding: 6px 15px;
            font-size: 12px;
        }

        .hero h1 {
            color: var(--red);
            font-size: clamp(28px, 4vw, 40px);
            margin: 13px 0 7px;
        }

        .hero p {
            margin: 7px 0;
            font-size: 14px;
        }

        .cart-summary {
            display: inline-block;
            margin-top: 12px;
            padding: 8px 16px;
            background: white;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .notice {
            width: 90%;
            max-width: 1100px;
            margin: 18px auto 0;
            padding: 12px;
            border-radius: 12px;
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

        .menu-section {
            width: 92%;
            max-width: 1250px;
            margin: 35px auto 55px;
        }

        .section-heading {
            text-align: center;
            margin-bottom: 20px;
        }

        .section-heading h2 {
            color: var(--red);
            font-size: 25px;
            margin-bottom: 5px;
        }

        .section-heading p {
            font-size: 13px;
        }

        .search-box {
            display: block;
            width: min(380px, 100%);
            margin: 0 auto 25px;
            padding: 11px 16px;
            border: 1px solid #edc3cf;
            border-radius: 25px;
            outline: none;
            font-family: inherit;
            font-size: 13px;
            background: white;
        }

        .search-box:focus {
            border-color: var(--red);
        }

        /* 5 กล่องต่อแถว x 2 แถว = 10 กล่อง */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 15px;
            align-items: stretch;
        }

        .product-card {
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: white;
            border: 1px solid #f3dce2;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(120, 55, 70, .07);
            transition: .25s;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 18px rgba(120, 55, 70, .13);
        }

       
        .product-image {
            display: block;
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--pink-light);
        }


        .product-info {
            padding: 10px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .product-info h3 {
            margin: 0 0 6px;
            color: var(--red);
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .description {
            font-size: 11px;
            line-height: 1.6;
            color: #80636a;
            margin: 0 0 10px;
            flex: 1;
        }

        .product-bottom {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            margin-top: auto;
            padding-top: 9px;
            border-top: 1px dashed #edc3cf;
        }

        .price {
            color: var(--red);
            font-size: 18px;
            font-weight: 700;
        }

        .price small {
            display: block;
            font-size: 10px;
            color: #987c82;
            font-weight: 400;
        }

        .product-bottom form {
            margin: 0;
        }

        .order-button {
            display: block;
            width: 100%;
            padding: 8px 5px;
            border: none;
            border-radius: 20px;
            background: var(--red);
            color: white;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .order-button:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
        }

        .empty-message {
            text-align: center;
            padding: 25px 15px;
            background: white;
            border-radius: 18px;
            font-size: 13px;
            line-height: 1.9;
        }

        .error-detail {
            color: var(--red);
            overflow-wrap: anywhere;
        }

        /* ปุ่มเปลี่ยนหน้า */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 28px;
        }

        .pagination a {
            min-width: 36px;
            padding: 7px 11px;
            text-align: center;
            background: white;
            color: var(--red);
            border: 1px solid #edc3cf;
            border-radius: 11px;
            font-size: 12px;
            transition: .2s;
        }

        .pagination a.active,
        .pagination a:hover {
            background: var(--red);
            color: white;
        }

        footer {
            padding: 23px 15px;
            background: var(--pink);
            text-align: center;
            font-size: 12px;
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
                height: 115px;
            }
        }

        @media (max-width: 550px) {
            header {
                justify-content: center;
                text-align: center;
            }

            nav {
                gap: 15px;
            }

            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .product-image {
                height: 110px;
            }

            .product-info {
                padding: 9px;
            }
        }
    </style>
</head>

<body>

<div class="announcement">
    ♡ Baked with love — ความสุขชิ้นเล็ก ๆ จาก KLONG 6 CAFE ♡
</div>

<header>
    <a href="index.php" class="logo">
        KLONG 6 CAFE
        <small>CAFE • BAKERY • HAPPINESS</small>
    </a>

    <div class="account">
        <?php if ($isLoggedIn): ?>
            <span>สวัสดี, <?= e($firstname) ?> 💗</span>
            <a href="logout.php">ออกจากระบบ</a>
        <?php else: ?>
            <a href="login.php">เข้าสู่ระบบ</a>
            <a href="register.php">สมัครสมาชิก</a>
        <?php endif; ?>

        <a href="cart.php" class="cart-link">
            🛒 ตะกร้า (<?= (int)$cartCount ?>)
        </a>
    </div>
</header>

<nav>
    <a href="index.php">หน้าแรก</a>
    <a href="cake.php" class="active">เค้ก</a>
    <a href="drink.php">เครื่องดื่ม</a>
</nav>

<section class="hero">
    <span class="tag">♡ SWEET MOMENTS ♡</span>
    <h1>Our Lovely Cakes 🍰</h1>
    <p>เค้กแสนอร่อย เติมความหวานให้วันพิเศษของคุณ</p>
    <p>เลือกชิ้นที่ชอบ แล้วกดสั่งซื้อได้เลยนะคะ 💕</p>

    <div class="cart-summary">
        🛒 ในตะกร้ามี <?= (int)$cartCount ?> ชิ้น
    </div>
</section>

<?php if (isset($_GET['added'])): ?>
    <div class="notice">
        ✓ เพิ่มสินค้าในตะกร้าเรียบร้อยแล้ว 💗
        <a href="cart.php">ดูตะกร้าสินค้า</a>
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="notice error">
        ไม่สามารถเพิ่มสินค้าได้ กรุณาลองใหม่อีกครั้ง
    </div>
<?php endif; ?>

<section class="menu-section" id="menu">

    <div class="section-heading">
        <h2>♡ Cake Menu ♡</h2>
        <p>เลือกสินค้าที่คุณชื่นชอบ</p>
    </div>

    <input
        type="text"
        class="search-box"
        id="cakeSearch"
        placeholder="🔍 ค้นหาชื่อสินค้า..."
        aria-label="ค้นหาชื่อสินค้า"
    >

    <?php if ($dbError !== ''): ?>

        <div class="empty-message">
            <h3>ไม่สามารถดึงข้อมูลสินค้าได้</h3>
            <p>รายละเอียดข้อผิดพลาด:</p>
            <p class="error-detail"><?= e($dbError) ?></p>
        </div>

    <?php elseif ($totalProducts === 0): ?>

        <div class="empty-message">
            <h3>ยังไม่มีสินค้าในฐานข้อมูล 🍰</h3>
            <p>กรุณาตรวจสอบว่าตาราง products มีข้อมูลสินค้าแล้วหรือไม่</p>
        </div>

    <?php else: ?>

        <div class="product-grid" id="productGrid">

            <?php foreach ($products as $product): ?>
                <article
                    class="product-card"
                    data-name="<?= e($product['product_name']) ?>"
                >

                    <img
                        class="product-image"
                        src="<?= e(
                            !empty($product['image'])
                                ? $product['image']
                                : 'blueflower.png'
                        ) ?>"
                        alt="<?= e($product['product_name']) ?>"
                        onerror="this.onerror=null;this.src='blueflower.png';"
                    >

                    <div class="product-info">

                        <h3><?= e($product['product_name']) ?></h3>

                        <p class="description">
                            <?= e(
                                !empty($product['description'])
                                    ? $product['description']
                                    : 'เค้กแสนอร่อยจาก KLONG 6 CAFE 💗'
                            ) ?>
                        </p>

                        <div class="product-bottom">

                            <div class="price">
                                ฿<?= number_format(
                                    (float)$product['price'],
                                    2
                                ) ?>
                                <small>ราคาต่อชิ้น</small>
                            </div>

                            <form method="post" action="cake.php#menu">
                                <input
                                    type="hidden"
                                    name="action"
                                    value="add_to_cart"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= e($product['product_id']) ?>"
                                >

                                <button
                                    type="submit"
                                    class="order-button"
                                >
                                    🛒 สั่งซื้อ
                                </button>
                            </form>

                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

        </div>

        <p
            id="noResults"
            class="empty-message"
            style="display:none;"
        >
            ไม่พบสินค้าที่ค้นหา ลองพิมพ์ชื่ออื่นดูนะคะ 💗
        </p>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">

                <?php if ($currentPage > 1): ?>
                    <a href="cake.php?page=<?= $currentPage - 1 ?>#menu">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a
                        href="cake.php?page=<?= $i ?>#menu"
                        class="<?= $i === $currentPage ? 'active' : '' ?>"
                    >
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="cake.php?page=<?= $currentPage + 1 ?>#menu">
                        ถัดไป »
                    </a>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <p style="text-align:center;font-size:12px;margin-top:15px;">
            หน้า <?= $currentPage ?> จาก <?= $totalPages ?>
            · สินค้าทั้งหมด <?= $totalProducts ?> รายการ
        </p>

    <?php endif; ?>

</section>

<footer>
    <strong>KLONG 6 CAFE</strong>
    <p>ขอบคุณที่แวะมาเติมความหวานกับเรานะคะ ♡</p>
</footer>

<script>
    const searchInput = document.getElementById('cakeSearch');
    const cards = document.querySelectorAll('.product-card');
    const noResults = document.getElementById('noResults');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const keyword = this.value.toLowerCase().trim();
            let visibleCount = 0;

            cards.forEach(function (card) {
                const name = card.dataset.name.toLowerCase();
                const matched = name.includes(keyword);

                card.style.display = matched ? 'flex' : 'none';

                if (matched) {
                    visibleCount++;
                }
            });

            if (noResults) {
                noResults.style.display =
                    visibleCount === 0 ? 'block' : 'none';
            }
        });
    }
</script>

</body>
</html>
