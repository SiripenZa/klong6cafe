<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$fullname = $_SESSION['admin_fullname'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Admin Dashboard | KLONG 6 CAFE</title>

<link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
      rel="stylesheet">

<style>

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    background: linear-gradient(
        180deg,
        #fff1f6 0%,
        #fff8fa 45%,
        #ffffff 100%
    );
    color: #632b3b;
    font-family: 'Mali', cursive;
    min-height: 100vh;
}

/* =========================
   HEADER
========================= */

header {
    position: relative;
    background:
        linear-gradient(
            120deg,
            rgba(255, 113, 164, 0.95),
            rgba(255, 183, 205, 0.95)
        );
    padding: 22px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    overflow: hidden;
    box-shadow: 0 5px 25px rgba(193, 18, 63, .12);
}

header::before {
    content: "♡";
    position: absolute;
    font-size: 180px;
    color: rgba(255,255,255,.12);
    left: -20px;
    top: -90px;
    transform: rotate(-15deg);
}

header::after {
    content: "🍓";
    position: absolute;
    font-size: 45px;
    right: 7%;
    top: 15px;
    animation: floating 4s ease-in-out infinite;
}

.brand-area {
    position: relative;
    z-index: 2;
}

.brand {
    font-size: 30px;
    font-weight: 700;
    color: white;
    text-shadow: 0 3px 10px rgba(150, 0, 50, .18);
}

.subtitle {
    color: #fff;
    margin-top: 4px;
    font-size: 14px;
    letter-spacing: 1px;
}

/* =========================
   LOGOUT
========================= */

.logout-btn {
    position: relative;
    z-index: 5;
    background: rgba(255,255,255,.16);
    border: 1px solid rgba(255,255,255,.75);
    color: white;
    text-decoration: none;
    padding: 11px 22px;
    border-radius: 30px;
    font-weight: 700;
    transition: .3s ease;
    white-space: nowrap;
}

.logout-btn:hover {
    background: white;
    color: #c1123f;
    transform: translateY(-3px);
    box-shadow: 0 7px 18px rgba(150,0,50,.18);
}

/* =========================
   DECORATIONS
========================= */

.decor {
    position: fixed;
    pointer-events: none;
    opacity: .3;
    z-index: 0;
}

.decor.one {
    left: -20px;
    top: 180px;
    font-size: 55px;
    animation: floating 5s ease-in-out infinite;
}

.decor.two {
    right: 30px;
    top: 430px;
    font-size: 45px;
    animation: floating 6s ease-in-out infinite reverse;
}

.decor.three {
    left: 7%;
    bottom: 40px;
    font-size: 35px;
    animation: floating 4.5s ease-in-out infinite;
}

/* =========================
   MAIN
========================= */

.container {
    position: relative;
    z-index: 1;
    max-width: 1250px;
    margin: 45px auto;
    padding: 0 22px 50px;
}

/* =========================
   WELCOME
========================= */

.welcome {
    text-align: center;
    animation: fadeUp .7s ease both;
}

.welcome .small-title {
    color: #c1123f;
    font-size: 14px;
    letter-spacing: 4px;
    font-weight: 700;
}

.welcome h1 {
    margin: 8px 0 5px;
    color: #c1123f;
    font-size: 38px;
}

.welcome p {
    color: #87616c;
    margin-top: 5px;
}

/* =========================
   CARD GRID
========================= */

.card-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin-top: 35px;
}

/* =========================
   CARD
========================= */

.card {
    position: relative;
    text-decoration: none;
    color: #632b3b;

    background: rgba(255,255,255,.92);

    padding: 30px;
    border-radius: 25px;

    border: 1px solid #f7dce4;

    box-shadow:
        0 8px 30px rgba(193,18,63,.08);

    display: block;

    transition: .3s ease;

    overflow: hidden;

    animation: fadeUp .8s ease both;
}

.card::before {
    content: "♡";
    position: absolute;
    right: -10px;
    top: -25px;
    font-size: 100px;
    color: rgba(255, 113, 164, .08);
}

.card:hover {
    transform: translateY(-7px);
    box-shadow: 0 12px 30px rgba(193,18,63,.14);
    border-color: #f7c4d4;
}

.card-icon {
    position: relative;
    z-index: 1;
    font-size: 55px;
    margin-bottom: 12px;
    animation: floating 4s ease-in-out infinite;
}

.card h2 {
    position: relative;
    z-index: 1;
    margin: 10px 0;
    color: #a31643;
    font-size: 22px;
}

.card p {
    position: relative;
    z-index: 1;
    color: #98717c;
    line-height: 1.8;
    margin-bottom: 20px;
}

.card-link {
    position: relative;
    z-index: 1;
    color: #c1123f;
    font-weight: 700;
}

/* =========================
   ANIMATION
========================= */

@keyframes fadeUp {

    from {
        opacity: 0;
        transform: translateY(25px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }

}

@keyframes floating {

    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-10px);
    }

}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1000px) {

    header {
        justify-content: center;
        text-align: center;
    }

    .card-grid {
        grid-template-columns: 1fr 1fr;
    }

}

@media (max-width: 700px) {

    .welcome h1 {
        font-size: 30px;
    }

    .card-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 550px) {

    .container {
        padding: 0 12px 40px;
        margin-top: 30px;
    }

    .brand {
        font-size: 24px;
    }

    .welcome h1 {
        font-size: 27px;
    }

    .card {
        padding: 25px;
        border-radius: 20px;
    }

}

</style>
</head>

<body>

<!-- ของตกแต่ง -->
<div class="decor one">🍓</div>
<div class="decor two">🍰</div>
<div class="decor three">♡</div>

<!-- =========================
     HEADER
========================= -->

<header>

    <div class="brand-area">

        <div class="brand">
            🍒 Klong 6 Cafe
        </div>

        <div class="subtitle">
            ADMIN PANEL
        </div>

    </div>

    <a href="logout.php" class="logout-btn">
        ออกจากระบบ
    </a>

</header>


<!-- =========================
     MAIN
========================= -->

<main class="container">

    <!-- WELCOME -->

    <section class="welcome">

        <div class="small-title">
            ADMIN DASHBOARD
        </div>

        <h1>
            สวัสดี, <?= htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8') ?> 👋
        </h1>

        <p>
            ยินดีต้อนรับเข้าสู่ระบบจัดการร้าน KLONG 6 CAFE
        </p>

    </section>


    <!-- CARD GRID -->

    <div class="card-grid">

        <!-- การ์ดจัดการเค้ก -->

        <a href="cake.php" class="card">

            <div class="card-icon">
                🍰
            </div>

            <h2>
                จัดการเค้ก
            </h2>

            <p>
                เพิ่ม แก้ไข ลบ และจัดการข้อมูลเค้กภายในร้าน
            </p>

            <div class="card-link">
                เข้าสู่การจัดการเค้ก →
            </div>

        </a>


        <!-- การ์ดจัดการเครื่องดื่ม -->

        <a href="drink.php" class="card">

            <div class="card-icon">
                🥤
            </div>

            <h2>
                จัดการเครื่องดื่ม
            </h2>

            <p>
                เพิ่ม แก้ไข ลบ และจัดการข้อมูลเครื่องดื่มภายในร้าน
            </p>

            <div class="card-link">
                เข้าสู่การจัดการเครื่องดื่ม →
            </div>

        </a>


        <!-- การ์ดจัดการออเดอร์ -->

        <a href="orders.php" class="card">

            <div class="card-icon">
                📦
            </div>

            <h2>
                จัดการออเดอร์
            </h2>

            <p>
                เพิ่ม แก้ไข ลบ และจัดการข้อมูลออเดอร์ภายในร้าน
            </p>

            <div class="card-link">
                เข้าสู่การจัดการออเดอร์ →
            </div>

        </a>


        <!-- การ์ดลูกค้า -->

        <a href="customers.php" class="card">

            <div class="card-icon">
                👥
            </div>

            <h2>
                จัดการลูกค้า
            </h2>

            <p>
                ดูข้อมูลสมาชิกและข้อมูลลูกค้าของร้าน
            </p>

            <div class="card-link">
                ดูลูกค้า →
            </div>

        </a>

    </div>

</main>

</body>
</html>