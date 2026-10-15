
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
    <title>Klong 6 Cafe | Coffee • Sweet Drinks • Cake</title>

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


/* ===== Klong 6 Cafe: Transparent Pink Navigation ===== */
.nav-wrap {
    position: absolute;
    z-index: 40;
    top: 18px;
    left: 50%;
    transform: translateX(-50%);

    width: max-content;
    max-width: 94%;

    background: rgba(255, 255, 255, 0.20);
    border: 1px solid rgba(255, 255, 255, 0.38);
    border-radius: 50px;

    box-shadow: 0 8px 25px rgba(100, 20, 50, 0.08);

    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 3px;
    flex-wrap: nowrap;
    padding: 5px 7px;
}

.nav a {
    display: inline-flex;
    justify-content: center;
    align-items: center;

    padding: 10px 14px;
    border-radius: 40px;

    color: #fff;
    text-shadow: 0 1px 8px rgba(100, 20, 50, 0.45);
    text-decoration: none;
    white-space: nowrap;

    font-family: 'Mali', cursive;
    font-size: 13px;
    font-weight: 700;

    transition:
        background 0.25s ease,
        color 0.25s ease,
        transform 0.25s ease;
}

/* ไฮไลต์หน้าปัจจุบัน */
.nav a.active,
.nav a.active:link,
.nav a.active:visited,
.nav a.active:hover {
    background: linear-gradient(135deg, #ff70a7, #ef367f) !important;
    color: #fff !important;
    text-shadow: none;
    box-shadow: 0 5px 18px rgba(233, 44, 119, 0.28);
}

/* เอฟเฟกต์เมื่อนำเมาส์ไปชี้ */
.nav a:not(.active):hover {
    background: rgba(255, 255, 255, 0.28);
    color: #fff;
    transform: translateY(-1px);
}

/* แสดงเมนูให้ใช้งานง่ายบนแท็บเล็ต */
@media (max-width: 1000px) {
    .nav-wrap {
        position: sticky;
        top: 0;
        left: auto;
        transform: none;

        width: 100%;
        max-width: none;

        border-radius: 0;
        border: 0;
        background: rgba(255, 245, 249, 0.96);

        box-shadow: 0 4px 15px rgba(90, 35, 50, 0.08);
    }

    .nav {
        justify-content: flex-start;
        overflow-x: auto;
        scrollbar-width: thin;
        padding: 7px 10px;
    }

    .nav a {
        color: #a62b5e;
        text-shadow: none;
        flex-shrink: 0;
    }

    .nav a.active,
    .nav a.active:link,
    .nav a.active:visited,
    .nav a.active:hover {
        color: #fff !important;
    }

    .nav a:not(.active):hover {
        background: #ffe5ef;
        color: #a62b5e;
    }
}

/* มือถือจอเล็ก */
@media (max-width: 600px) {
    .nav {
        gap: 4px;
        padding: 8px;
    }

    .nav a {
        font-size: 12px;
        padding: 9px 12px;
    }
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
            gap: 18px;
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
            height: 220px;
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

        
/* ===== Smooth Floating + Soft Sparkle ===== */


/* ทำให้กรอบรูปทั้ง 3 รูปมีขอบมนเหมือนกัน */
.reference-products img,
.reference-products .floating-coffee,
.reference-products .floating-drink,
.reference-products .floating-cake {
    border-radius: 32px !important;
    border: 6px solid rgba(255, 255, 255, 0.9);
    overflow: hidden;
    box-sizing: border-box;
}

/* ป้องกันรูปภาพล้นออกนอกกรอบ */
.reference-products img {
    object-fit: cover;
}

/* คงเอฟเฟกต์เมื่อชี้รูป */
.reference-products img:hover {
    z-index: 20 !important;
    scale: 1.08;
    filter: drop-shadow(
        0 18px 22px rgba(255, 65, 140, 0.30)
    );
}


/* เมื่อชี้รูป ให้เด้งขึ้นด้านหน้า */
.reference-products img:hover {
    z-index: 20 !important;
    scale: 1.08;
    filter: drop-shadow(0 18px 22px rgba(255, 65, 140, .30));
    box-shadow: 0 24px 48px rgba(190, 48, 105, .32);
}

/* กำหนดจังหวะให้แต่ละรูปลอยไม่พร้อมกัน */
.reference-products .floating-coffee {
    animation-delay: 0s;
}

.reference-products .floating-drink {
    animation-delay: -1.7s;
}

.reference-products .floating-cake {
    animation-delay: -3.2s;
}

/* ลอยขึ้นลง พร้อมแสงเรืองสีชมพูอ่อน */
@keyframes cafeFloatGlow {
    0%, 100% {
        translate: 0 0;
        filter: drop-shadow(0 10px 12px rgba(235, 70, 140, .10))
                brightness(1);
    }

    50% {
        translate: 0 -10px;
        filter: drop-shadow(0 16px 20px rgba(255, 95, 158, .22))
                brightness(1.025);
    }
}

/* เมื่อเลื่อนเมาส์ผ่านรูป */
.reference-products img:hover {
    scale: 1.035;
    filter: drop-shadow(0 15px 22px rgba(255, 70, 145, .30))
            brightness(1.04);
}

/* ประกายวิบวับเล็ก ๆ รอบรูป */
.reference-products::before,
.reference-products::after {
    content: "✦";
    position: absolute;
    z-index: 5;
    color: #fff;
    pointer-events: none;
    text-shadow:
        0 0 5px #fff,
        0 0 12px #ff8fba,
        0 0 20px rgba(255, 95, 158, .55);
    animation: cafeTwinkle 3s ease-in-out infinite;
}

.reference-products::before {
    top: 12%;
    left: 48%;
    font-size: 21px;
}

.reference-products::after {
    right: 7%;
    top: 55%;
    font-size: 15px;
    animation-delay: -1.4s;
}



/* เอฟเฟกต์เมื่อชี้รูปสินค้า */
.reference-products img {
    transition:
        scale .45s cubic-bezier(.2,.8,.2,1),
        filter .45s ease;
}

/* รูปที่ชี้จะขยายและเด่นขึ้น */
.reference-products img:hover {
    scale: 1.10;
    filter: drop-shadow(0 18px 22px rgba(255,65,140,.38))
            brightness(1.05);
}

/* ให้รูปซ้อนทับกันได้ */
.reference-products {
    isolation: isolate;
}

.reference-products img {
    position: absolute;
}

/* ให้รูปที่เมาส์ชี้อยู่ชั้นบนสุด */
.reference-products img:hover {
    z-index: 20 !important;
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


        /* Reference-inspired pink garden landing page */
        .announcement { display: none; }
        .header {
            position: absolute;
            z-index: 20;
            top: 0;
            left: 0;
            right: 0;
            padding: 14px 5%;
            background: linear-gradient(180deg, rgba(111, 37, 65, .35), transparent);
        }
        .header-inner { max-width: 1400px; }
        .brand { color: #fff; text-shadow: 0 2px 12px rgba(130, 28, 68, .25); }
        .brand small { color: rgba(255,255,255,.9); letter-spacing: 2px; }
        .brand h1 { font-size: clamp(20px, 2.4vw, 30px); }
        .nav-wrap {
            position: absolute;
            z-index: 19;
            top: 18px;
            left: 50%;
            transform: translateX(-50%);
            width: max-content;
            max-width: 90%;
            background: rgba(255,255,255,.2);
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 50px;
            box-shadow: 0 8px 25px rgba(100, 20, 50, .08);
        }
        .nav { padding: 5px 8px; gap: 3px; }
        .nav a { color: white; text-shadow: 0 1px 6px rgba(90,20,50,.3); padding: 9px 13px; }
        .nav a.active, .nav a.active:link, .nav a.active:visited, .nav a.active:hover {
            background: linear-gradient(135deg, #ff70a7, #ef367f) !important;
            color: white !important;
            text-shadow: none;
        }
        .hero {
            min-height: 610px;
            display: flex;
            align-items: center;
            padding: 115px 5% 70px;
            background:
              radial-gradient(ellipse at 80% 25%, rgba(255,255,255,.18), transparent 26%),
              linear-gradient(115deg, rgba(202,48,103,.42), rgba(255,143,185,.15)),
              url('รูปร้าน.png') center/cover no-repeat,
              linear-gradient(135deg,#ffb5d0,#fff0f6);
        }
        .hero::after {
            content: ""; position:absolute; inset:0; z-index:0; pointer-events:none;
            background: linear-gradient(90deg, rgba(104,26,57,.40), rgba(104,26,57,.08) 62%, rgba(255,205,224,.08));
        }
        .hero-inner { max-width: 1400px; grid-template-columns: .9fr 1.1fr; gap: 30px; }
        .hero-inner > div:first-child { color: white; }
        .eyebrow { color: #fff2f7; text-shadow: 0 2px 10px rgba(80,10,40,.3); }
        .hero h2 { color: white; font-size: clamp(38px, 5vw, 68px); line-height: 1.16; text-shadow: 0 3px 18px rgba(103,19,52,.25); }
        .hero h2 span { background: linear-gradient(120deg,#fff,#ffe1ed); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .hero p { color: white; font-size: clamp(14px,1.4vw,18px); text-shadow: 0 2px 10px rgba(80,10,40,.3); }
        .hero-note { color: #fff4f8; text-shadow: 0 2px 8px rgba(80,10,40,.25); }
        .hero-image img { height: 390px; object-fit: contain; background: transparent; border: 0; box-shadow: none; border-radius: 0; }
        .hero-image::before { inset: 8% 5% 3%; background: rgba(255,255,255,.12); filter: blur(24px); border-radius: 50%; }
        .image-label { color: #cf2c6b; }
        .section { max-width: 1400px; padding-top: 30px; }
        .section-heading { text-align: left; margin-bottom: 22px; }
        .section-heading h2 { color: #bd174e; }
        .category-card { display: grid; grid-template-columns: 1.1fr .9fr; align-items: center; text-align: left; border-radius: 20px; background: linear-gradient(120deg,#fff,#ffe4ee); border-color: #ffd0e0; }
        .category-card img { height: 210px; min-width: 0; }
        .category-content { padding: 16px 12px; }
        .category-content h3 { color: #e33e82; font-size: clamp(16px,1.8vw,24px); }
        .category-content p { min-height: 0; }
        .category-content .btn { padding: 9px 16px; }
        .banner { background: linear-gradient(120deg,#ffd4e5,#fff2f7,#ffc5dc); color: #c52c68; }
        .banner .btn { color: white; background: linear-gradient(135deg,#ff70a7,#e52d77); }
        footer { background: #fff0f6; color: #c52c68; }
        footer h3 { color: #c52c68; }
        footer p { color: #a95b78; }
        .footer-line { background: rgba(197,44,104,.16); }
        @media (max-width: 900px) {
            .nav-wrap { top: 84px; }
            .header { padding: 10px 4%; }
            .header-inner { justify-content: space-between; }
            .hero { padding-top: 145px; }
            .hero-inner { grid-template-columns: 1fr 1fr; }
            .hero-image img { height: 300px; }
            .categories { grid-template-columns: 1fr; }
            .category-card img { height: 220px; }
        }
        @media (max-width: 600px) {
            .header { position: relative; background: linear-gradient(135deg,#ff8fb8,#f34e91); }
            .brand { color: white; }
            .account { gap: 6px; }
            .account .btn { padding: 8px 11px; font-size: 11px; }
            .nav-wrap { position: sticky; transform: none; top: 0; left: auto; width: 100%; max-width: none; border-radius: 0; background: rgba(255,255,255,.96); }
            .nav { flex-wrap: nowrap; overflow-x: auto; justify-content: flex-start; }
            .nav a { color: #8f2039; text-shadow: none; white-space: nowrap; }
            .hero { padding: 35px 6%; min-height: auto; }
            .hero-inner { grid-template-columns: 1fr; gap: 12px; }
            .hero h2 { font-size: 39px; }
            .hero-image img { height: 220px; }
            .image-label { left: 5px; bottom: 8px; }
            .section-heading { text-align: center; }
            .category-card { grid-template-columns: 1fr; text-align: center; }
            .category-card img { height: 230px; }
            .category-content { padding: 14px 18px 20px; }
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



        /* Pink garden reference layout */
        :root{--pink:#ffb5d0;--pink-light:#fff4f8;--red:#ff3e8b;--red-dark:#c51b62;--cream:#fff8fb;--text:#9d315d}
        body{background:#fff8fb;color:#9d315d}
        .announcement{display:none}
        .header{position:absolute;z-index:30;top:0;left:0;width:100%;background:transparent;padding:16px 5%;color:white}
        .header-inner{max-width:1400px;flex-wrap:nowrap}
        .brand{color:#fff;display:flex;align-items:center;gap:9px;text-shadow:0 2px 12px #b61c5e}
        .brand small{font-size:29px;letter-spacing:0}
        .brand h1{font-size:clamp(20px,2.1vw,30px);font-style:italic;margin:0;color:white}
        .account{margin-left:auto}
        .nav-wrap{position:absolute;top:19px;left:50%;transform:translateX(-50%);width:max-content;max-width:70%;background:#ffffff22;border:1px solid #ffffff40;border-radius:40px;box-shadow:none;backdrop-filter:blur(8px);z-index:29}
        .nav{padding:4px 7px;gap:3px;flex-wrap:nowrap}
        .nav a{color:#fff;text-shadow:0 1px 8px #96305b;padding:9px 17px;white-space:nowrap}
        .nav a.active,.nav a.active:link,.nav a.active:visited,.nav a.active:hover{background:#ff4e91!important;color:white!important;box-shadow:0 5px 18px #e92c7750}
        .hero{position:relative;min-height:620px;padding:110px 5% 62px;overflow:hidden;background:linear-gradient(100deg,#ff8eb6ad,#ffc1d778 48%,#fff0f66b),url('รูปร้าน.png') center 43%/cover no-repeat}
        .hero:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,#ec5d94a8 0%,#f67aa34f 40%,#ffffff08 78%);pointer-events:none}
        .hero:after{content:"";position:absolute;inset:auto 0 0;height:95px;background:linear-gradient(transparent,#fff8fb);pointer-events:none}
        .hero-inner{position:relative;z-index:2;max-width:1400px;grid-template-columns:.82fr 1.18fr;gap:15px}
        .hero-inner>div:first-child{position:relative;z-index:3}
        .eyebrow{color:#fff;letter-spacing:2px;text-shadow:0 2px 8px #8b1748}
        .hero h2,.hero .reference-title{font-size:clamp(39px,5vw,70px);line-height:1.14;color:#fff;margin:12px 0;text-shadow:0 3px 18px #a7255d55}
        .hero h2 span{color:#fff;font-style:italic;text-shadow:0 3px 15px #a7255d75}
        .hero p{color:#fff;font-size:clamp(14px,1.4vw,18px);font-weight:700;text-shadow:0 2px 9px #8b1748a3;line-height:1.8}
        .hero-note{color:white;text-shadow:0 2px 8px #8b1748}
        .hero-actions{margin-top:22px}
        .btn-primary{background:linear-gradient(110deg,#ff5ba0,#ed1670);color:white;box-shadow:0 8px 22px #e82d7650}
        .btn-primary:hover{background:#e91f70}
        .btn-light{background:#ffffff35;color:white;border-color:#ffffffa3}
        .reference-products{height:390px;display:block;perspective:900px}
        .reference-products img{position:absolute;object-fit:cover;background:#fff;border:7px solid #ffffffc9;border-radius:45% 45% 28% 28%;box-shadow:0 24px 48px #8c234c60;transition:transform .3s}
        .reference-products .floating-coffee{width:43%;height:300px;left:10%;top:20px;transform:rotate(-9deg);animation:referenceFloat 5s ease-in-out infinite}
        .reference-products .floating-drink{width:36%;height:270px;left:42%;top:65px;transform:rotate(8deg);animation:referenceFloat 4.5s ease-in-out -1.7s infinite}
        .reference-products .floating-cake{width:42%;height:235px;right:0;bottom:0;border-radius:25px;transform:rotate(4deg);animation:referenceFloat 5.5s ease-in-out -2.5s infinite}
        .reference-products .image-label{left:32%;bottom:5px;background:#ff4e91;color:white;border-radius:25px;z-index:3}
        .floating-strawberry,.floating-beans{position:absolute;z-index:4;filter:drop-shadow(0 4px 7px #9e275a55);animation:referenceFloat 4s ease-in-out infinite}
        .floating-strawberry{font-size:48px;left:44%;top:3%}.floating-beans{font-size:30px;left:4%;bottom:10%}
        .section{max-width:1450px;padding:25px 5% 22px}
        .section-heading{text-align:left;margin:0 0 18px}
        .section-heading small{display:none}
        .section-heading h2{font-size:clamp(27px,3vw,37px);color:#d81b69;margin:0}
        .section-heading p{display:none}
        .categories{gap:16px}
        .category-card{display:grid;grid-template-columns:58% 42%;align-items:center;text-align:left;border:1px solid #ffc5da;border-radius:22px;background:linear-gradient(115deg,#fff,#ffe1ed);box-shadow:0 8px 22px #e63c8019}
        .category-card img{height:210px;min-height:210px;object-fit:cover}
        .category-content{padding:12px 10px}
        .category-content h3{font-size:clamp(19px,2.2vw,31px);font-style:italic;color:#ed2d7d;margin:0 0 6px;line-height:1.15}
        .category-content p{font-size:12px;min-height:0;line-height:1.7;margin:5px 0 12px;color:#a24c70}
        .category-content .btn{padding:9px 16px;font-size:12px;margin:0}
        .banner{display:none}
        .bottom-slogan{display:block;text-align:center;padding:8px 10px 24px;color:#ff4e91;font-style:italic;font-size:14px}
        footer{background:linear-gradient(180deg,#ffeaf2,#ffc7dc);color:#b54572;padding:18px 5%}
        footer h3{color:#e72f7a}
        footer p{color:#b45b7c}
        .footer-line{background:#ed8fb4}
        @keyframes referenceFloat{0%,100%{translate:0 0}50%{translate:0 -12px}}
        @media(max-width:1000px){.header{position:relative;background:linear-gradient(110deg,#ff8ab6,#f54d91);padding:12px 4%}.header-inner{flex-wrap:wrap;justify-content:center}.nav-wrap{position:sticky;transform:none;left:auto;top:0;width:100%;max-width:none;border-radius:0;background:#fff9fc;border:0}.nav{overflow-x:auto;justify-content:flex-start}.nav a{color:#a62b5e;text-shadow:none}.hero{padding-top:45px}.hero-inner{grid-template-columns:1fr 1fr}.reference-products{height:320px}.reference-products .floating-coffee{height:250px}.reference-products .floating-drink{height:220px}.reference-products .floating-cake{height:180px}.categories{grid-template-columns:1fr}.category-card{grid-template-columns:48% 52%}}
        @media(max-width:600px){.hero{padding:35px 5% 45px;min-height:auto}.hero-inner{grid-template-columns:1fr;text-align:center}.hero-actions{justify-content:center}.hero-note{display:none}.reference-products{height:265px;margin-top:10px}.reference-products .floating-coffee{height:205px;width:42%;left:8%}.reference-products .floating-drink{height:190px;width:36%;left:39%;top:28px}.reference-products .floating-cake{height:150px;width:42%;right:0}.floating-strawberry{font-size:34px}.floating-beans{font-size:22px}.section{padding:22px 4% 12px}.section-heading{text-align:left}.category-card{grid-template-columns:47% 53%;min-height:175px}.category-card img{height:175px;min-height:175px}.category-content h3{font-size:22px}.category-content p{font-size:10px}.category-content .btn{padding:8px 12px;font-size:10px}.account{justify-content:center}}
        @media(prefers-reduced-motion:reduce){.reference-products img,.floating-strawberry,.floating-beans{animation:none!important;transition:none!important}}

        
/* ทำให้เมนูอยู่ด้านหน้าและคลิกได้ */
.nav-wrap {
    position: absolute;
    z-index: 1000;
    pointer-events: auto;
}

.nav,
.nav a {
    pointer-events: auto;
    position: relative;
}

.nav a {
    cursor: pointer;
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
            <small>☕</small>
            <h1>Klong 6 Cafe</h1>
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


<!-- เมนูนำทางหลักของ Klong 6 Cafe -->

<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);

$menus = [
    'index.php'    => '🏠 หน้าหลัก',
    'cake.php'     => '🍰 เค้ก',
    'drink.php'    => '🥤 เครื่องดื่ม',
    'order.php'    => '🧾 Order',
    'my_orders.php'=> '📋 ประวัติการสั่งซื้อ',
    'cart.php'     => '🛒 ตะกร้า'
];
?>

<div class="nav-wrap">
    <nav class="nav">
        <?php foreach ($menus as $page => $label): ?>
            <a href="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>"
               class="<?= $currentPage === $page ? 'active' : '' ?>"
               <?= $currentPage === $page ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
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

                <h2 class="reference-title">
                    Welcome to<br>
                    <span>Klong 6 Cafe</span> ♡
                </h2>

                <p>
                    “กาแฟดี น้ำหวานสดชื่น เค้กอร่อย<br>
                    ในบรรยากาศสุดน่ารัก”
                </p>

                <div class="hero-actions">
                    <a href="#categories" class="btn btn-primary">
                        ☕ ดูเมนูทั้งหมด <span>→</span>
                    </a>

                    <a href="drink.php" class="btn btn-light">
                        🥤 ดูเครื่องดื่ม
                    </a>
                </div>

                <div class="hero-note">
                    ✿ Made with love, served with happiness ✿
                </div>
            </div>

            <div class="hero-image reference-products" aria-label="เมนูเครื่องดื่มและขนมของ Klong 6 Cafe">
                <img class="floating-coffee" src="drink/matcha latte.jpg" alt="กาแฟลาเต้ของ Klong 6 Cafe">
                <img class="floating-drink" src="drink/Blue Latte.jpg" alt="เครื่องดื่มสีชมพู">
                <img class="floating-cake" src="image/Cherry Pop Cake.jpg" alt="เค้กสตรอว์เบอร์รี">
                <span class="floating-strawberry">🍓</span>
                <span class="floating-beans">☕ ✦ 🍓</span>
                <div class="image-label">Klong 6 Cafe ♡</div>
            </div>

        </div>
    </section>

    <section class="section" id="categories">

        <div class="section-heading">
            <small>♡ MENU FAVORITES ♡</small>
            <h2>เมนูแนะนำ ♡</h2>
            <p>เติมความสุขให้วันธรรมดา ด้วยเมนูโปรดของคุณ</p>
        </div>

        <div class="categories">

            <article class="category-card">
                <img src="water2.png" alt="เครื่องดื่มหวานสดชื่น" loading="lazy">
                <div class="category-content">
                    <h3>Sweet Drinks ♡</h3>
                    <p>น้ำหวานสุดสดชื่น สีสันสดใส</p>
                    <a href="drink.php" class="btn btn-primary">ดูเมนู →</a>
                </div>
            </article>

            <article class="category-card">
                <img src="cake.png" alt="เค้กสตรอว์เบอร์รี" loading="lazy">
                <div class="category-content">
                    <h3>Cake ♡</h3>
                    <p>เค้กโฮมเมด เนื้อนุ่ม หวานละมุน</p>
                    <a href="cake.php" class="btn btn-primary">ดูเมนู →</a>
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
<div class="bottom-slogan">♡ Good Coffee ・ Sweet Drinks ・ Delicious Cake ・ Happy Time ♡</div>

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
