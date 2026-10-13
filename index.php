
<?php
session_start();
require_once 'conn.php';

$customerName = $_SESSION['customer_firstname'] ?? '';
$isLoggedIn = isset($_SESSION['customer_id']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLONG 6 CAFE | Coffee, Cake & Happiness</title>

    <link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --pink: #f8dce4;
            --pink-light: #fff5f7;
            --red: #b72e4b;
            --red-dark: #8f2039;
            --cream: #fffaf5;
            --text: #56333c;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: 'Mali', cursive;
            background: var(--cream);
            color: var(--text);
        }

        a {
            -webkit-tap-highlight-color: transparent;
        }

        /* แถบประกาศ */
        .announcement {
            background: var(--red);
            color: white;
            text-align: center;
            padding: 9px 12px;
            font-size: 12px;
            letter-spacing: 1px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #fff2f5, #f8dce4);
            padding: 20px 5%;
        }

        .header-inner {
            max-width: 1200px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .brand {
            text-decoration: none;
            color: var(--red);
        }

        .brand small {
            display: block;
            font-size: 11px;
            letter-spacing: 3px;
        }

        .brand h1 {
            margin: 4px 0;
            font-size: clamp(23px, 3vw, 34px);
        }

        .account {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .welcome {
            font-size: 13px;
            font-weight: 700;
        }

        /* ปุ่ม */
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            padding: 12px 21px;
            border-radius: 50px;
            text-decoration: none;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid transparent;
            cursor: pointer;
            transition: transform .25s ease,
                        background .25s ease,
                        box-shadow .25s ease;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(143, 32, 57, .15);
        }

        .btn-primary {
            background: var(--red);
            color: white;
        }

        .btn-primary:hover {
            background: var(--red-dark);
        }

        .btn-light {
            background: white;
            color: var(--red);
            border-color: #efd0d9;
        }

        .btn-light:hover {
            background: #fff0f4;
        }

        /* Navigation */
        .nav-wrap {
            background: white;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(90, 35, 50, .06);
        }

        .nav {
            max-width: 1200px;
            margin: auto;
            padding: 12px 5%;
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav a {
            text-decoration: none;
            color: var(--text);
            padding: 10px 19px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            transition: .25s ease;
        }

        .nav a:hover,
        .nav a.active {
            background: var(--red);
            color: white;
        }

        /* Hero */
        .hero {
            padding: 55px 5%;
            background:
                radial-gradient(circle at 85% 15%, #ffe0e8 0, transparent 30%),
                linear-gradient(135deg, #fff5f7, #f9e3e9);
        }

        .hero-inner {
            max-width: 1200px;
            margin: auto;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            align-items: center;
            gap: 45px;
        }

        .eyebrow {
            color: var(--red);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 3px;
        }

        .hero h2 {
            font-size: clamp(30px, 4.5vw, 52px);
            line-height: 1.45;
            color: var(--red-dark);
            margin: 14px 0;
        }

        .hero h2 span {
            color: #d65a77;
        }

        .hero p {
            line-height: 2;
            font-size: 14px;
            max-width: 470px;
            color: #79545d;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 25px;
        }

        .hero-note {
            margin-top: 22px;
            font-size: 12px;
            color: #98717b;
        }

        .hero-image {
            position: relative;
        }

        .hero-image img {
            display: block;
            width: 100%;
            height: 430px;
            object-fit: cover;
            border-radius: 35px 35px 100px 35px;
            border: 8px solid white;
            box-shadow: 0 18px 45px rgba(143, 32, 57, .13);
        }

        .image-label {
            position: absolute;
            left: -15px;
            bottom: 25px;
            background: white;
            color: var(--red);
            padding: 13px 20px;
            border-radius: 18px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, .08);
            font-size: 13px;
            font-weight: 700;
        }

        /* Section */
        .section {
            max-width: 1200px;
            margin: auto;
            padding: 65px 5%;
        }

        .section-heading {
            text-align: center;
            margin-bottom: 35px;
        }

        .section-heading small {
            color: var(--red);
            letter-spacing: 3px;
            font-size: 11px;
            font-weight: 700;
        }

        .section-heading h2 {
            margin: 10px 0;
            font-size: clamp(25px, 3vw, 34px);
            color: var(--red-dark);
        }

        .section-heading p {
            font-size: 13px;
            line-height: 1.9;
            color: #896872;
        }

        /* Category cards */
        .categories {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 25px;
        }

        .category-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            text-align: center;
            border: 1px solid #f5e6e9;
            box-shadow: 0 8px 25px rgba(100, 40, 55, .05);
            transition: transform .3s ease, box-shadow .3s ease;
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 18px 35px rgba(143, 32, 57, .12);
        }

        .category-card img {
            display: block;
            width: 100%;
            height: 280px;
            object-fit: cover;
            transition: transform .4s ease;
        }

        .category-card:hover img {
            transform: scale(1.04);
        }

        .category-content {
            padding: 20px 15px 25px;
        }

        .category-content h3 {
            color: var(--red-dark);
            font-size: 20px;
            margin: 0 0 8px;
        }

        .category-content p {
            color: #896872;
            font-size: 12px;
            line-height: 1.8;
            min-height: 42px;
        }

        .category-content .btn {
            margin-top: 10px;
        }

        /* Highlight banner */
        .banner {
            background: linear-gradient(120deg, #b72e4b, #d65a77);
            color: white;
            padding: 38px 5%;
            text-align: center;
        }

        .banner h2 {
            font-size: clamp(22px, 3vw, 30px);
            margin: 0 0 10px;
        }

        .banner p {
            font-size: 13px;
            line-height: 1.9;
            margin-bottom: 22px;
        }

        .banner .btn {
            background: white;
            color: var(--red);
        }

        /* Footer */
        footer {
            background: #451f2b;
            color: #fbe8ed;
            padding: 35px 5%;
            text-align: center;
        }

        footer h3 {
            color: white;
            letter-spacing: 2px;
        }

        footer p {
            font-size: 12px;
            line-height: 2;
            color: #e6c7d0;
        }

        .footer-line {
            height: 1px;
            max-width: 700px;
            margin: 22px auto;
            background: rgba(255,255,255,.15);
        }

        /* Responsive */
        @media (max-width: 800px) {
            .hero-inner {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .hero {
                padding-top: 40px;
            }

            .hero-image img {
                height: 350px;
            }

            .categories {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .category-card:last-child {
                grid-column: 1 / -1;
                max-width: 520px;
                width: 100%;
                justify-self: center;
            }
        }

        @media (max-width: 520px) {
            .header-inner {
                justify-content: center;
                text-align: center;
            }

            .account {
                justify-content: center;
            }

            .nav {
                gap: 4px;
                padding: 10px 8px;
            }

            .nav a {
                padding: 9px 12px;
                font-size: 12px;
            }

            .hero {
                padding: 35px 6%;
            }

            .hero h2 {
                font-size: 31px;
            }

            .hero-image img {
                height: 280px;
                border-radius: 25px 25px 65px 25px;
            }

            .image-label {
                left: -5px;
                bottom: 15px;
                font-size: 11px;
                padding: 10px 13px;
            }

            .section {
                padding: 45px 5%;
            }

            .categories {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .category-card:last-child {
                grid-column: auto;
            }

            .category-card img {
                height: 250px;
            }

            .hero-actions .btn {
                flex: 1;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition: none !important;
            }
        }
    </style>
</head>

<body>

<div class="announcement">
    ♡ SWEET MOMENTS EVERY DAY — WELCOME TO KLONG 6 CAFE ♡
</div>

<header class="header">
    <div class="header-inner">

        <a href="index.php" class="brand">
            <small>COFFEE • CAKE • HAPPINESS</small>
            <h1>🍒 KLONG 6 CAFE</h1>
        </a>

        <div class="account">
            <?php if ($isLoggedIn): ?>
                <span class="welcome">
                    สวัสดี, <?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?> 💗
                </span>

                <a href="logout.php" class="btn btn-light">
                    ออกจากระบบ
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-light">
                    เข้าสู่ระบบ
                </a>

                <a href="register.php" class="btn btn-primary">
                    สมัครสมาชิก ♡
                </a>
            <?php endif; ?>
        </div>

    </div>
</header>

<div class="nav-wrap">
    <nav class="nav">
        <a href="index.php" class="active">⌂ หน้าหลัก</a>
        <a href="cake.php">🍰 เค้ก</a>
        <a href="drink.php">🥤 เครื่องดื่ม</a>
    </nav>
</div>

<main>
    <section class="hero" id="home">
        <div class="hero-inner">

            <div>
                <div class="eyebrow">A LITTLE CAFE, A LOT OF LOVE</div>

                <h2>
                    Life is sweeter<br>
                    with <span>cake!</span> 🍰
                </h2>

                <p>
                    เติมความสุขให้ทุกวัน ด้วยกาแฟหอม ๆ
                    เค้กแสนอร่อย และขนมอบสดใหม่
                    ให้ทุกช่วงเวลาของคุณพิเศษกว่าที่เคย ♡
                </p>

                <div class="hero-actions">
                    <a href="#categories" class="btn btn-primary">
                        เลือกชมเมนู <span>→</span>
                    </a>

                    <a href="drink.php" class="btn btn-light">
                        🥤 ดูเครื่องดื่ม
                    </a>
                </div>

                <div class="hero-note">
                    ✿ Made with love, served with happiness ✿
                </div>
            </div>

            <div class="hero-image">
                <img src="รูปร้าน.png" alt="เค้กแสนอร่อยจาก KLONG 6 CAFE">
                <div class="image-label">
                    🍒 Sweet treats, happy hearts
                </div>
            </div>

        </div>
    </section>

    <section class="section" id="categories">

        <div class="section-heading">
            <small>DISCOVER OUR MENU</small>
            <h2>เลือกความอร่อยที่ใช่สำหรับคุณ ♡</h2>
            <p>
                วันนี้อยากเติมความหวานด้วยอะไรดี?
                เลือกดูเมนูโปรดของคุณได้เลย
            </p>
        </div>

        <div class="categories">

            <article class="category-card">
                <img src="cake.png" alt="เมนูเค้ก" loading="lazy">

                <div class="category-content">
                    <h3>🍰 Cake</h3>
                    <p>เค้กแสนอร่อย เติมความหวานให้วันพิเศษ</p>
                    <a href="cake.php" class="btn btn-primary">
                        ดูเมนูเค้ก →
                    </a>
                </div>
            </article>


            <article class="category-card">
                <img src="water2.png" alt="เมนูเครื่องดื่ม" loading="lazy">

                <div class="category-content">
                    <h3>🥤 Drinks</h3>
                    <p>เครื่องดื่มสดชื่น เติมพลังให้ทุกวัน</p>
                    <a href="drink.php" class="btn btn-primary">
                        ดูเมนูเครื่องดื่ม →
                    </a>
                </div>
            </article>

        </div>
    </section>

    <section class="banner">
        <h2>Make today a little sweeter 💗</h2>
        <p>
            ความสุขชิ้นเล็ก ๆ รอคุณอยู่ที่ KLONG 6 CAFE
            <br>
            มาเลือกเมนูโปรดของคุณกันเลย
        </p>

        <a href="#categories" class="btn">
            ค้นพบเมนูโปรดของคุณ ♡
        </a>
    </section>
</main>

<footer id="contact">
    <h3>🍒 KLONG 6 CAFE 🍒</h3>
    <p>Coffee • Cake • Bakery • Happiness</p>

    <div class="footer-line"></div>

    <p>
        ขอบคุณที่แวะมาเยี่ยมชมร้านของเรา ♡
        <br>
        Made with love, just for you.
    </p>

    <p>© <?= date('Y') ?> KLONG 6 CAFE. All rights reserved.</p>
</footer>

</body>
</html>

<?php
require_once 'conn.php';

$sql = "SELECT product_id, product_name, description, price, stock, image
        FROM products
        WHERE status = 'available'
        ORDER BY product_id DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เค้ก | KLONG 6 CAFE</title>

    <link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
          rel="stylesheet">
</head>

<body style="
    margin:0;
    background:#fff0f3;
    color:#632b3b;
    font-family:'Mali',cursive;
">

<header style="
    background:#ffd6e2;
    text-align:center;
    padding:30px 15px;
">

    <p style="
        color:#c1123f;
        letter-spacing:3px;
        font-size:13px;
    ">
        SWEET MOMENTS EVERY DAY
    </p>

    <h1 style="
        margin:10px 0;
        color:#c1123f;
        font-size:clamp(25px,5vw,40px);
    ">
        🍒 KLONG 6 CAFE 🍰
    </h1>

    <p>
        Coffee • Cake • Happiness ♡
    </p>

</header>

<nav style="
    width:fit-content;
    max-width:95%;
    margin:10px auto 25px;
    display:flex;
    gap:5px;
    flex-wrap:wrap;
    justify-content:center;
">

    <a href="index.php"
       style="
           color:#000;
           padding:12px 22px;
           border-radius:50px;
           text-decoration:none;
           font-weight:700;
       ">
        หน้าหลัก
    </a>

    <a href="cake.php"
       style="
           background:#000;
           color:#fff;
           padding:12px 22px;
           border-radius:50px;
           text-decoration:none;
           font-weight:700;
       ">
        เค้ก
    </a>

    <a href="drink.php"
       style="
           color:#000;
           padding:12px 22px;
           border-radius:50px;
           text-decoration:none;
           font-weight:700;
       ">
        เครื่องดื่ม
    </a>

</nav>

<section style="
    text-align:center;
    padding:25px 20px;
">

    <div style="
        color:#c1123f;
        font-size:14px;
        letter-spacing:4px;
    ">
        OUR CAKES
    </div>

    <h2 style="
        color:#c1123f;
        font-size:38px;
        margin:8px 0;
    ">
        เค้กของเรา 🍰
    </h2>

    <p style="color:#75404d;">
        เค้กแสนอร่อยจาก KLONG 6 CAFE 💗
    </p>

</section>

<section style="
    max-width:1200px;
    margin:0 auto;
    padding:20px 20px 70px;
">

    <div style="
        display:flex;
        flex-wrap:wrap;
        justify-content:center;
        gap:25px;
    ">

        <?php if ($result && $result->num_rows > 0): ?>

            <?php while ($product = $result->fetch_assoc()): ?>

                <?php
                $image = !empty($product['image'])
                    ? $product['image']
                    : 'cake.png';
                ?>

                <div style="
                    width:280px;
                    background:#fff;
                    border-radius:25px;
                    overflow:hidden;
                    box-shadow:0 8px 25px rgba(193,18,63,0.12);
                    text-align:center;
                ">

                    <div style="
                        width:100%;
                        height:230px;
                        background:#fff0f4;
                    ">

                        <img
                            src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>"
                            style="
                                width:100%;
                                height:100%;
                                object-fit:cover;
                            "
                        >

                    </div>

                    <div style="
                        padding:22px;
                    ">

                        <h3 style="
                            margin:0 0 10px;
                            color:#c1123f;
                            font-size:22px;
                        ">
                            <?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <p style="
                            color:#777;
                            font-size:14px;
                            line-height:1.7;
                            min-height:48px;
                            margin:8px 0;
                        ">
                            <?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <div style="
                            color:#e63946;
                            font-size:24px;
                            font-weight:700;
                            margin:15px 0;
                        ">
                            ฿<?= number_format((float)$product['price'], 2) ?>
                        </div>

                        <?php if ((int)$product['stock'] > 0): ?>

                            <div style="
                                color:#777;
                                font-size:13px;
                                margin-bottom:15px;
                            ">
                                เหลือ <?= (int)$product['stock'] ?> ชิ้น
                            </div>

                            <a
                                href="#"
                                style="
                                    display:inline-block;
                                    background:#c1123f;
                                    color:#fff;
                                    padding:11px 28px;
                                    border-radius:50px;
                                    text-decoration:none;
                                    font-weight:700;
                                "
                            >
                                🛒 สั่งซื้อ
                            </a>

                        <?php else: ?>

                            <div style="
                                color:#e63946;
                                font-weight:700;
                                margin-bottom:15px;
                            ">
                                สินค้าหมด
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div style="
                width:100%;
                text-align:center;
                padding:60px 20px;
            ">

                <div style="font-size:60px;">
                    🍰
                </div>

                <h2 style="color:#c1123f;">
                    ยังไม่มีสินค้า
                </h2>

                <p>
                    กรุณาเพิ่มสินค้าจากระบบจัดการสินค้า
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>

<footer style="
    background:#c1123f;
    color:#fff;
    text-align:center;
    padding:40px 20px;
">

    <h2>
        🍒 KLONG 6 CAFE 🍰
    </h2>

    <p>
        Coffee ♡ Cake ♡ Happiness
    </p>

    <p style="
        font-size:13px;
        opacity:0.8;
    ">
        © 2026 KLONG 6 CAFE
    </p>

</footer>

</body>
</html>

<?php
$conn->close();
?>