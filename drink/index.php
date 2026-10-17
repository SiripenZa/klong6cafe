
<?php
session_start();
require_once 'conn.php';

$customerName = $_SESSION['customer_firstname'] ?? '';
$isLoggedIn = isset($_SESSION['customer_id']);

// ตรวจสอบหน้าปัจจุบันเพื่อไฮไลต์เมนู
$currentPage = basename($_SERVER['SCRIPT_NAME']);
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

        /* ปุ่มทั่วไป */
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
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav a {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            color: var(--text);
            background: transparent;
            padding: 10px 17px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            transition: background .25s ease, color .25s ease;
        }

        /* เมนูหน้าปัจจุบันเป็นสีแดงค้างไว้ */
        .nav a.active,
        .nav a.active:link,
        .nav a.active:visited,
        .nav a.active:hover {
            background: var(--red) !important;
            color: white !important;
        }

        /* เมนูอื่นเมื่อเอาเมาส์ไปชี้ */
        .nav a:not(.active):hover {
            background: #fff0f4;
            color: var(--red);
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
            grid-template-columns: repeat(2, minmax(0, 1fr));
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

        /* ===== Klong 6 Cafe: Pink 3D Garden Enhancement ===== */
        :root {
            --pink-hot: #ff5f9e;
            --pink-pop: #ff8fba;
            --pink-glow: rgba(255, 95, 158, .22);
            --leaf: #76b88a;
            --leaf-dark: #438a64;
        }

        body {
            overflow-x: hidden;
            background:
                radial-gradient(ellipse at 8% 18%, rgba(255, 190, 215, .22), transparent 34%),
                var(--cream);
        }

        .hero {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            background:
                radial-gradient(ellipse at 82% 22%, rgba(255, 150, 195, .52), transparent 34%),
                radial-gradient(ellipse at 12% 90%, rgba(255, 205, 225, .72), transparent 38%),
                linear-gradient(135deg, #fff0f6 0%, #ffd8e8 48%, #fff4f8 100%);
        }

        .hero-inner { position: relative; z-index: 2; }

        .hero h2 span {
            background: linear-gradient(120deg, #ff4f96, #c72b67 75%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-image {
            perspective: 1000px;
            transform-style: preserve-3d;
        }

        .hero-image img {
            position: relative;
            z-index: 1;
            transform: translateZ(18px);
            transition: transform .25s ease, box-shadow .3s ease;
            box-shadow: 0 24px 55px rgba(190, 48, 105, .2), 0 0 0 1px rgba(255,255,255,.8);
        }

        .hero-image::before {
            content: "";
            position: absolute;
            inset: 10% -5% -5% 8%;
            z-index: 0;
            border-radius: 45px 45px 100px 45px;
            background: linear-gradient(135deg, #ff8fba, #ffc6dd);
            filter: blur(1px);
            transform: rotate(5deg) translateZ(-20px);
            opacity: .8;
        }

        .image-label {
            z-index: 3;
            animation: labelFloat 4s ease-in-out infinite;
            border: 1px solid rgba(255, 180, 210, .55);
        }

        .pink-garden-decor {
            position: absolute;
            inset: 0;
            z-index: 1;
            overflow: hidden;
            pointer-events: none;
        }

        .garden-leaf {
            position: absolute;
            width: clamp(42px, 6vw, 88px);
            height: clamp(76px, 10vw, 132px);
            border-radius: 100% 0 100% 0;
            background: linear-gradient(145deg, #b8e7b8, #64ad7e 70%, #438a64);
            opacity: .36;
            filter: drop-shadow(0 12px 12px rgba(58, 117, 77, .12));
            animation: leafSway 8s ease-in-out infinite;
        }

        .garden-leaf::after {
            content: "";
            position: absolute;
            left: 49%;
            top: 8%;
            height: 82%;
            width: 1px;
            background: rgba(255,255,255,.55);
            transform: rotate(-12deg);
        }

        .leaf-one { top: 3%; left: -1%; transform: rotate(25deg); }
        .leaf-two { top: 18%; right: 2%; transform: rotate(65deg) scale(.8); animation-delay: -2s; }
        .leaf-three { bottom: -4%; left: 34%; transform: rotate(75deg) scale(.65); animation-delay: -4s; }
        .leaf-four { bottom: 8%; right: -2%; transform: rotate(5deg) scale(.9); animation-delay: -1s; }

        .pink-petal {
            position: absolute;
            width: 12px;
            height: 17px;
            border-radius: 100% 0 100% 0;
            background: linear-gradient(135deg, #fff, #ff83b4);
            opacity: .75;
            animation: petalDrift 10s linear infinite;
        }

        .petal-one { left: 18%; top: 14%; }
        .petal-two { left: 53%; top: 8%; animation-delay: -3s; transform: scale(.7); }
        .petal-three { right: 15%; top: 60%; animation-delay: -6s; transform: scale(1.2); }
        .petal-four { left: 42%; bottom: 8%; animation-delay: -4s; transform: scale(.8); }

        .announcement {
            background: linear-gradient(90deg, #d82e70, #f05291, #d82e70);
        }

        .header {
            background: linear-gradient(135deg, #fff4f8, #ffd9e9);
        }

        .nav-wrap {
            background: rgba(255, 255, 255, .94);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }

        .category-card {
            position: relative;
            box-shadow: 0 12px 30px rgba(190, 48, 105, .08);
        }

        .category-card:hover {
            transform: translateY(-8px) perspective(900px) rotateX(1deg);
            box-shadow: 0 22px 42px rgba(190, 48, 105, .16);
        }

        .category-card img {
            transition: transform .55s cubic-bezier(.2,.7,.2,1), filter .4s ease;
        }

        .category-card:hover img {
            transform: scale(1.06);
            filter: saturate(1.08);
        }

        .btn-primary {
            background: linear-gradient(135deg, #f05291, #cf2c6b);
            box-shadow: 0 7px 18px rgba(207, 44, 107, .15);
        }

        .banner {
            background:
                radial-gradient(circle at 12% 20%, rgba(255,255,255,.2), transparent 25%),
                linear-gradient(120deg, #c72b67, #f05291, #e43c80);
        }

        @keyframes leafSway {
            0%, 100% { margin-top: 0; rotate: -4deg; }
            50% { margin-top: 13px; rotate: 5deg; }
        }

        @keyframes petalDrift {
            0% { translate: 0 -20px; rotate: 0deg; opacity: 0; }
            15% { opacity: .75; }
            85% { opacity: .65; }
            100% { translate: 35px 180px; rotate: 240deg; opacity: 0; }
        }

        @keyframes labelFloat {
            0%, 100% { transform: translateY(0) rotate(-1deg); }
            50% { transform: translateY(-7px) rotate(1deg); }
        }

        @media (max-width: 800px) {
            .garden-leaf { opacity: .23; }
            .leaf-three { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .garden-leaf, .pink-petal, .image-label { animation: none !important; }
            .hero-image img { transition: none !important; }
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


<!-- เมนูนำทาง: หน้าหลัก เค้ก เครื่องดื่ม Order และประวัติการสั่งซื้อ -->
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

    </nav>
</div>


<main>

    <section class="hero" id="home">
        <div class="pink-garden-decor" aria-hidden="true">
            <span class="garden-leaf leaf-one"></span>
            <span class="garden-leaf leaf-two"></span>
            <span class="garden-leaf leaf-three"></span>
            <span class="garden-leaf leaf-four"></span>
            <span class="pink-petal petal-one"></span>
            <span class="pink-petal petal-two"></span>
            <span class="pink-petal petal-three"></span>
            <span class="pink-petal petal-four"></span>
        </div>
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

        <a href="menu.php" class="btn">
            ดูเมนูทั้งหมด ♡
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


<script>
(() => {
    const card = document.querySelector('.hero-image');
    const image = card?.querySelector('img');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    if (!card || !image || reduceMotion || !finePointer) return;

    card.addEventListener('pointermove', (event) => {
        const rect = card.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width;
        const y = (event.clientY - rect.top) / rect.height;
        const rotateY = (x - 0.5) * 5;
        const rotateX = (0.5 - y) * 4;
        image.style.transform = `translateZ(18px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.015)`;
    });

    card.addEventListener('pointerleave', () => {
        image.style.transform = '';
    });
})();
</script>

</body>
</html>
