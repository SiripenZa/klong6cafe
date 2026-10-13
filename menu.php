<section style="max-width:1200px; margin:0 auto; padding:25px 20px 70px;">

    <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:25px;">

        <?php if ($result && $result->num_rows > 0): ?>

            <?php while ($product = $result->fetch_assoc()): ?>

                <?php
                $image = !empty($product["image"]) ? $product["image"] : "blueflower.png";
                ?>

                <div style="width:280px; background:#fff; border-radius:25px; overflow:hidden; box-shadow:0 8px 25px rgba(193,18,63,0.10);">

                    <div style="width:100%; height:230px; background:#fff0f4;">
                        <img
                            src="<?= htmlspecialchars($image) ?>"
                            alt="<?= htmlspecialchars($product["product_name"]) ?>"
                            style="width:100%; height:100%; object-fit:cover;"
                        >
                    </div>

                    <div style="padding:22px; text-align:center;">

                        <h3 style="margin:0 0 8px; color:#c1123f; font-size:22px;">
                            <?= htmlspecialchars($product["product_name"]) ?>
                        </h3>

                        <p style="color:#777; font-size:14px; line-height:1.7; min-height:48px; margin:8px 0;">
                            <?= htmlspecialchars($product["description"]) ?>
                        </p>

                        <div style="color:#c1123f; font-size:24px; font-weight:700; margin:15px 0;">
                            ฿<?= number_format((float)$product["price"], 2) ?>
                        </div>

                        <?php if ((int)$product["stock"] > 0): ?>

                            <div style="color:#777; font-size:13px; margin-bottom:15px;">
                                เหลือ <?= (int)$product["stock"] ?> ชิ้น
                            </div>

                            <a href="#"
                               style="display:inline-block; background:#c1123f; color:#fff; padding:11px 28px; border-radius:50px; text-decoration:none; font-weight:700; font-size:15px;">
                                🛒 สั่งซื้อ
                            </a>

                        <?php else: ?>

                            <div style="color:#e63946; font-weight:700; margin-bottom:15px;">
                                สินค้าหมด
                            </div>

                            <span style="display:inline-block; background:#ddd; color:#777; padding:11px 28px; border-radius:50px; font-size:15px;">
                                สินค้าหมด
                            </span>

                        <?php endif; ?>

                    </div>
                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div style="width:100%; text-align:center; padding:60px 20px;">
                <div style="font-size:60px;">🍰</div>

                <h2 style="color:#c1123f;">
                    ยังไม่มีสินค้า
                </h2>

                <p>
                    กรุณาเพิ่มสินค้าจากฐานข้อมูล
                </p>
            </div>

        <?php endif; ?>

    </div>
</section>