
<!DOCTYPE html>
<html lang="th">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title></title>
	<style>
		* {
			box-sizing: border-box;
		}

		html {
			scroll-behavior: smooth;
		}

		html,
		body {
			margin: 0;
			min-height: 600vh;
			overflow-x: hidden;
			scrollbar-width: none;
			-ms-overflow-style: none;
		}

		html::-webkit-scrollbar,
		body::-webkit-scrollbar {
			display: none;
		}

		body {
			background: #000;
		}

		canvas {
			position: fixed;
			inset: 0;
			display: block;
			width: 100%;
			height: 100%;
		}
	</style>
</head>
<body>
	<canvas aria-hidden="true"></canvas>
	<script>
		const canvas = document.querySelector('canvas');
		const context = canvas.getContext('2d', { alpha: false });
		const frameCount = 300;
		const frames = Array.from({ length: frameCount }, (_, index) => {
			const image = new Image();
			image.decoding = 'async';
			image.src = `shop/ezgif-frame-${String(index + 1).padStart(3, '0')}.jpg`;
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

			const scale = Math.max(
				window.innerWidth / image.naturalWidth,
				window.innerHeight / image.naturalHeight
			);
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
