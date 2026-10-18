<?php
session_start();

$customerName = $_SESSION['customer_firstname'] ?? '';
$isLoggedIn = isset($_SESSION['customer_id']);
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$menus = [
	'Home.php' => '🏠 หน้าหลัก',
	'cake.php' => '🍰 เค้ก',
	'drink.php' => '🥤 เครื่องดื่ม',
	'order.php' => '🧾 คำสั่งซื้อ',
	'my_orders.php' => '📋 ประวัติการสั่งซื้อ',
    'cart.php'     => '🛒 ตะกร้า'

];
?>
<!DOCTYPE html>
<html lang="th">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Klong 6 Cafe | Coffee, Drinks & Cake</title>
	<link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap" rel="stylesheet">
	<style>
		:root { --pink-frame: rgba(255, 105, 160, .9); --pink-glow: rgba(255, 75, 140, .22); }
		* { box-sizing: border-box; }
		html {
			scroll-behavior: smooth;
			scrollbar-width: none;
			-ms-overflow-style: none;
		}
		html::-webkit-scrollbar,
		body::-webkit-scrollbar { display: none; }
		body {
			margin: 0;
			min-height: 100vh;
			overflow-x: hidden;
			scrollbar-width: none;
			-ms-overflow-style: none;
			background: #000;
			color: #fff;
			font-family: "Segoe UI", Tahoma, sans-serif;
		}
		canvas {
			position: fixed;
			inset: 0;
			z-index: 0;
			display: block;
			width: 100%;
			height: 100%;
			pointer-events: none;
		}
		a { color: inherit; -webkit-tap-highlight-color: transparent; }
		.site-header, .nav-wrap, main, footer { position: relative; z-index: 1; }
		.site-header, .nav-wrap { font-family: 'Mali', cursive; }
		.site-header {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			z-index: 10;
			padding: 17px 5%;
			background: linear-gradient(180deg, rgba(0, 0, 0, .5), transparent);
		}
		.header-inner {
			max-width: 1320px;
			margin: auto;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 20px;
			flex-wrap: nowrap;
		}
		.brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
		.brand-mark { font-size: 29px; }
		.brand-name { font-family: 'Mali', cursive; font-size: clamp(22px, 2.5vw, 31px); font-weight: 700; line-height: 1.2; text-shadow: 0 2px 10px #000; }
		.brand-name small { display: block; margin-top: 3px; font-size: 10px; letter-spacing: 2px; }
		.account { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; font-family: 'Mali', cursive; font-size: 12px; }
		.welcome { color: #fff; font-size: 12px; font-weight: 700; text-decoration: none; text-shadow: 0 2px 8px #000; }
		.welcome:hover { color: #ffd4e2; text-decoration: underline; text-underline-offset: 4px; }
		.btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 42px;
			padding: 10px 17px;
			border: 1px solid var(--pink-frame);
			border-radius: 4px;
			background: rgba(255, 182, 205, .32);
			color: #fff;
			font: inherit;
			font-size: 13px;
			font-weight: 600;
			text-decoration: none;
			text-shadow: 0 1px 5px rgba(80, 20, 45, .65);
			backdrop-filter: blur(8px);
			transition: background .2s ease, transform .2s ease;
		}
		.btn:hover { background: rgba(255, 182, 205, .52); transform: translateY(-2px); }
		.btn-primary { background: #ed367f; color: #fff; border-color: var(--pink-frame); }
		.btn-primary:hover { background: #c51b62; }
		.nav-wrap {
			position: absolute;
			top: 19px;
			left: 50%;
			z-index: 11;
			width: max-content;
			max-width: 70%;
			transform: translateX(-50%);
			border: 1px solid var(--pink-frame);
			border-radius: 40px;
			background: rgba(0,0,0,.22);
			box-shadow: 0 8px 25px rgba(0,0,0,.12);
			backdrop-filter: blur(8px);
		}
		.nav {
			max-width: 1320px;
			margin: auto;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 4px;
			padding: 4px 7px;
			overflow-x: auto;
		}
		.nav a {
			flex: 0 0 auto;
			padding: 9px 13px;
			border-radius: 30px;
			color: #fff;
			font-size: 13px;
			text-decoration: none;
			white-space: nowrap;
		}
		.nav a:hover, .nav a.active { color: #fff; background: #ed367f; }
		main { width: 100%; }
		.hero {
			min-height: min(760px, calc(100vh - 80px));
			display: grid;
			place-items: center;
			padding: 70px 5%;
		}
		.hero-inner {
			width: min(900px, 100%);
			display: grid;
			grid-template-columns: 1fr;
			align-items: center;
			justify-items: center;
			text-align: center;
		}
		.hero-copy {
			width: 100%;
			max-width: 800px;
			color: #6b3e2e;
			text-shadow: 0 1px 6px rgba(255,255,255,.75), 0 2px 15px rgba(0,0,0,.35);
		}
		.eyebrow { font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
		.hero h2 { margin: 15px 0; font-size: clamp(38px, 5vw, 68px); line-height: 1.12; }
		.hero-copy .eyebrow,
		.hero-copy h2 {
			font-family: 'Mali', cursive;
			color: #fff;
			-webkit-text-stroke: 1px #ff69a0;
			paint-order: stroke fill;
		}
		.hero p {
			display: block;
			max-width: none;
			margin: 8px auto 0;
			white-space: nowrap;
			font-family: 'Mali', cursive;
			color: #fff;
			font-size: 18px;
			font-weight: 700;
			line-height: 1.8;
			text-shadow: 0 2px 10px rgba(0,0,0,.85);
		}
		.hero-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 24px; }
		.reference-products { position: relative; height: 390px; }
		.reference-products img {
			position: absolute;
			object-fit: cover;
			border: 5px solid var(--pink-frame);
			border-radius: 5px;
			box-shadow: 0 18px 40px rgba(0,0,0,.4), 0 0 18px var(--pink-glow);
			transition: transform .3s ease;
		}
		.reference-products img:hover { z-index: 3; transform: translateY(-8px); }
		.floating-coffee { top: 10px; left: 4%; width: 43%; height: 300px; transform: rotate(-5deg); }
		.floating-drink { top: 50px; left: 40%; width: 36%; height: 270px; transform: rotate(4deg); }
		.floating-cake { right: 0; bottom: 0; width: 42%; height: 220px; transform: rotate(2deg); }
		.section {
			width: min(1320px, 100%);
			margin: auto;
			padding: 70px 5%;
		}
		.section-heading { margin-bottom: 24px; text-shadow: 0 2px 12px #000; text-align: center; }
		.section-heading h2 { margin: 0 0 8px; font-size: clamp(26px, 3vw, 38px); }
		.section-heading p { margin: 0; line-height: 1.8; }
		.categories {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 24px;
			align-items: stretch;
		}
		.category-card {
			display: flex;
			flex-direction: column;
			align-items: center;
			width: 88%;
			justify-self: center;
			min-width: 0;
			overflow: hidden;
			border: 2px solid var(--pink-frame);
			border-radius: 18px;
			background: linear-gradient(135deg, rgba(30, 8, 18, .95), rgba(0, 0, 0, .88));
			box-shadow: 0 16px 36px rgba(0, 0, 0, .32), 0 0 22px var(--pink-glow);
			transition: transform .25s ease, box-shadow .25s ease;
		}
		.category-card:hover {
			transform: translateY(-5px);
			box-shadow: 0 22px 42px rgba(0, 0, 0, .38), 0 0 28px var(--pink-glow);
		}
		.category-card img {
			display: block;
			width: 100%;
			max-width: none;
			height: auto;
			object-fit: contain;
			object-position: center;
			margin: 0;
			padding: 0;
			border-radius: 10px 10px 0 0;
		}
		.category-content {
			position: static;
			display: flex;
			width: 100%;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 22px 20px 26px;
			text-align: center;
			background: transparent;
		}
		.category-content h3 {
			margin: 0 0 10px;
			font-size: 24px;
			color: #ffd6e5;
			text-align: center;
		}
		.category-content p {
			margin: 0 0 18px;
			color: rgba(255, 255, 255, .9);
			font-size: 15px;
			line-height: 1.8;
			text-align: center;
		}
		.category-content .btn { align-self: center; }

		.order-strip {
			margin: 30px auto 70px;
			width: min(1220px, 90%);
			padding: 30px;
			border: 1px solid var(--pink-frame);
			background: rgba(0,0,0,.68);
			text-align: center;
		}
		.order-strip h2 { margin: 0 0 8px; font-size: 26px; }
		.order-strip p { margin: 0 0 18px; line-height: 1.8; }
		.order-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
		footer { padding: 30px 5%; background: rgba(0,0,0,.82); text-align: center; }
		footer h2 { margin: 0 0 8px; font-size: 20px; }
		footer p { margin: 8px 0; color: rgba(255,255,255,.78); font-size: 13px; line-height: 1.8; }
		.footer-line { width: min(680px, 90%); height: 1px; margin: 20px auto; background: var(--pink-frame); }
		@media (max-width: 1000px) {
			.site-header { position: relative; padding: 12px 4%; background: rgba(0,0,0,.65); }
			.header-inner { flex-wrap: wrap; justify-content: center; }
			.account { margin-left: auto; }
			.nav-wrap { position: sticky; top: 0; left: auto; width: 100%; max-width: none; transform: none; border: 0; border-top: 1px solid var(--pink-frame); border-bottom: 1px solid var(--pink-frame); border-radius: 0; background: rgba(0,0,0,.78); }
			.nav { justify-content: flex-start; padding: 7px 10px; }
			.hero p { white-space: normal; }
			.hero-inner { grid-template-columns: 1fr 1fr; gap: 20px; }
			.hero { min-height: 0; padding-top: 55px; }
			.reference-products { height: 310px; }
			.floating-coffee { height: 240px; }
			.floating-drink { height: 210px; }
			.floating-cake { height: 170px; }
		}
		@media (max-width: 600px) {
			.site-header { padding: 14px 5%; background: rgba(0,0,0,.65); }
			.header-inner { justify-content: center; }
			.brand-name { font-size: 19px; }
			.brand-name small { font-size: 8px; letter-spacing: 1.5px; }
			.account { justify-content: center; margin: 0; }
			.welcome { width: 100%; text-align: center; }
			.btn { min-height: 38px; padding: 8px 12px; font-size: 12px; }
			.nav a { padding: 9px 12px; font-size: 12px; }
			.hero { min-height: auto; padding: 55px 6% 35px; }
			.hero-inner { grid-template-columns: 1fr; gap: 12px; }
			.hero-copy { text-align: center; }
			.hero h2 { font-size: 42px; }
			.hero p { margin-inline: auto; font-size: 16px; white-space: normal; }
			.hero-actions { justify-content: center; }
			.reference-products { height: 270px; }
			.floating-coffee { height: 205px; }
			.floating-drink { top: 30px; height: 185px; }
			.floating-cake { height: 145px; }
			.section { padding: 45px 5%; }
			.categories { grid-template-columns: 1fr; gap: 22px; }
			.category-card { width: 88%; min-height: 0; }
			.category-card img { width: 100%; max-width: none; height: auto; margin: 0; object-fit: contain; object-position: center; }
			.category-content { padding: 20px 16px 24px; align-items: center; justify-content: center; text-align: center; }
			.category-content h3 { font-size: 21px; }
			.category-content p { font-size: 13px; }

			.order-strip { width: 90%; padding: 24px 16px; }
		}
		@media (prefers-reduced-motion: reduce) {
			html { scroll-behavior: auto; }
			*, *::before, *::after { transition-duration: .01ms !important; }
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
	<canvas aria-hidden="true"></canvas>
	<header class="site-header">
		<div class="header-inner">
			<a class="brand" href="index.php" aria-label="Klong 6 Cafe หน้าหลัก">
				<span class="brand-mark" aria-hidden="true">🍒</span>
				<span class="brand-name">KLONG 6 CAFE<small>CAFE • BAKERY • HAPPINESS</small></span>
			</a>
			<div class="account">
				<?php if ($isLoggedIn): ?>
					<a class="welcome" href="viewprofile.php">สวัสดี, <?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?></a>
					<a class="btn" href="logout.php">ออกจากระบบ</a>
				<?php else: ?>
					<a class="btn" href="login.php">เข้าสู่ระบบ</a>
					<a class="btn btn-primary" href="register.php">สมัครสมาชิก</a>
				<?php endif; ?>
			</div>
		</div>
	</header>
	<div class="nav-wrap">
		<nav class="nav" aria-label="เมนูหลัก">
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
		<section class="hero" id="Home">
			<div class="hero-inner">
				<div class="hero-copy">
					<div class="eyebrow">Coffee · Sweet Drinks · Cake</div>
					<h2>Welcome to<br>Klong 6 Cafe</h2>
					<p>กาแฟดี เครื่องดื่มสดชื่น และเค้กโฮมเมด พร้อมเติมช่วงเวลาดี ๆ ให้ทุกวันของคุณ</p>
					<div class="hero-actions">
						<a class="btn btn-primary" href="#categories">เลือกดูเมนู</a>
					</div>
				</div>
			</div>
		</section>
		<section class="section" id="categories">
			<div class="section-heading">
				<h2>เมนูของเรา</h2>
				<p>เลือกหมวดหมู่ที่คุณชื่นชอบ แล้วสั่งได้เลย</p>
			</div>
			<div class="categories">
				<article class="category-card">
					<img src="water2.png" alt="เครื่องดื่มสดชื่น" loading="lazy">
					<div class="category-content">
						<h3>เครื่องดื่ม</h3>
						<p>เลือกเครื่องดื่มแก้วโปรดได้ที่นี่</p>
						<a class="btn" href="drink.php">ดูเครื่องดื่ม</a>
					</div>
				</article>
				<article class="category-card">
					<img src="cake.png" alt="เค้กโฮมเมด" loading="lazy">
					<div class="category-content">
						<h3>เค้ก</h3>
						<p>เค้กโฮมเมดสำหรับช่วงเวลาพิเศษ</p>
						<a class="btn" href="cake.php">ดูเค้ก</a>
					</div>
				</article>
			</div>
		</section>
		<section class="order-strip" id="orders">
			<h2>พร้อมเลือกเมนูโปรดหรือยัง?</h2>
			<p>สั่งซื้อและติดตามรายการของคุณได้จากเมนูด้านบน</p>
			<div class="order-links">
				<a class="btn btn-primary" href="order.php">สั่งซื้อ</a>
				<a class="btn" href="cart.php">ดูตะกร้า</a>
				<a class="btn" href="my_orders.php">ประวัติการสั่งซื้อ</a>
			</div>
		</section>
	</main>
	<footer id="contact">
		<h2>Klong 6 Cafe</h2>
		<p>Coffee · Drinks · Cake</p>
		<div class="footer-line"></div>
		<p>ขอบคุณที่แวะมาเยี่ยมชมร้านของเรา</p>
		<p>© <?= date('Y') ?> Klong 6 Cafe</p>
	</footer>
	<script>
		const canvas = document.querySelector('canvas');
		const context = canvas.getContext('2d', { alpha: false });
		const frameCount = 300;
		const frames = Array.from({ length: frameCount }, (_, index) => {
			const image = new Image();
			image.decoding = 'async';
			image.src = `shop2%204k/ezgif-frame-${String(index + 1).padStart(3, '0')}.jpg`;
			image.onload = draw;
			return image;
		});

		let targetFrame = 0;
		let currentFrame = 0;
		let animationPending = false;
		let previousAnimationTime = 0;

		function resizeCanvas() {
			const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
			canvas.width = Math.round(window.innerWidth * pixelRatio);
			canvas.height = Math.round(window.innerHeight * pixelRatio);
			context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
			draw();
		}

		function drawImage(image, opacity) {
			if (!image.complete || !image.naturalWidth) return;
			const scale = Math.max(window.innerWidth / image.naturalWidth, window.innerHeight / image.naturalHeight);
			const width = image.naturalWidth * scale;
			const height = image.naturalHeight * scale;
			context.globalAlpha = opacity;
			context.drawImage(image, (window.innerWidth - width) / 2, (window.innerHeight - height) / 2, width, height);
			context.globalAlpha = 1;
		}

		function draw() {
			const firstIndex = Math.floor(currentFrame);
			const nextIndex = Math.min(firstIndex + 1, frameCount - 1);
			const blend = currentFrame - firstIndex;
			context.fillStyle = '#000';
			context.fillRect(0, 0, window.innerWidth, window.innerHeight);
			drawImage(frames[firstIndex], 1);
			if (blend > 0) drawImage(frames[nextIndex], blend);
		}

		function animate(timestamp) {
			const elapsed = previousAnimationTime
				? Math.min(timestamp - previousAnimationTime, 64)
				: 16.67;
			previousAnimationTime = timestamp;
			const easing = 1 - Math.exp(-elapsed / 90);
			currentFrame += (targetFrame - currentFrame) * easing;
			if (Math.abs(targetFrame - currentFrame) < 0.01) currentFrame = targetFrame;
			draw();
			if (currentFrame !== targetFrame) {
				requestAnimationFrame(animate);
			} else {
				animationPending = false;
				previousAnimationTime = 0;
			}
		}

		function updateScrollFrame() {
			const scrollRange = document.documentElement.scrollHeight - window.innerHeight;
			const progress = scrollRange > 0 ? window.scrollY / scrollRange : 0;
			targetFrame = Math.max(0, Math.min(frameCount - 1, progress * (frameCount - 1)));
			if (!animationPending) {
				animationPending = true;
				requestAnimationFrame(animate);
			}
		}

		window.addEventListener('scroll', updateScrollFrame, { passive: true });
		window.addEventListener('resize', resizeCanvas);
		resizeCanvas();
		updateScrollFrame();
	</script>
</body>
</html>
