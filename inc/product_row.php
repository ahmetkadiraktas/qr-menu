<?php
/**
 * Urun satiri parcasi (musteri sayfalari ve admin Menu sayfasi ortak kullanir).
 * Beklenen: $p (array: id, name, description, price, variants, diet, is_featured, is_sold_out, cover)
 * Istege bagli: $rowHref (string; verilmezse urun sayfasi), $rowShowCategory (bool; $p['category_name'] gosterilir)
 */
declare(strict_types=1);
$priceLabel = setting('show_prices', '1') === '1' || !empty($rowForcePrices) ? product_price_label($p) : '';   // admin onizlemesinde fiyat kapaliyken de gorunur
$soldOut = (int) $p['is_sold_out'] === 1;
$badges = diet_to_array((string) $p['diet'], 'badge');
$diets = diet_to_array((string) $p['diet'], 'diet');
$GLOBALS['__row_index'] = ($GLOBALS['__row_index'] ?? 0) + 1;   // sayfadaki satir sirasi (temalar renk dongusu ve gecikme icin kullanir)
$rowIndex = (int) $GLOBALS['__row_index'];
?>
<a class="product-row cyc-<?= (($rowIndex - 1) % 6) + 1 ?><?= $soldOut ? ' sold-out' : '' ?><?= (int) $p['is_featured'] === 1 ? ' is-featured' : '' ?><?= empty($p['cover']) ? ' no-photo' : '' ?>" href="<?= e($rowHref ?? url('urun.php?id=' . (int) $p['id'])) ?>" style="--i:<?= $rowIndex ?>">
    <div class="thumb">
        <?php if (!empty($p['cover'])): ?>
            <img src="<?= e(upload_url('products', (string) $p['cover'], true)) ?>" alt="" loading="lazy">
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 11h18M5 11a7 7 0 0 1 14 0M4 15h16M6 19h12"/></svg>
        <?php endif; ?>
    </div>
    <div class="info">
        <?php if (!empty($rowShowCategory) && !empty($p['category_name'])): ?><p class="row-cat"><?= e($p['category_name']) ?></p><?php endif; ?>
        <div class="row-head">
            <h3 class="name"><?= e($p['name']) ?></h3>
            <span class="leader" aria-hidden="true"></span>
            <?php if ($priceLabel !== ''): ?><span class="price head-price" aria-hidden="true"><?= e($priceLabel) ?></span><?php elseif (setting('show_prices', '1') === '1'): ?><span class="price head-price price-missing" aria-hidden="true"><?= e(price_missing_label()) ?></span><?php endif; ?>
        </div>
        <?php if ($p['description'] !== ''): ?><p class="desc"><?= e($p['description']) ?></p><?php endif; ?>
        <div class="meta">
            <?php if ($priceLabel !== ''): ?><span class="price meta-price"><?= e($priceLabel) ?></span><?php elseif (setting('show_prices', '1') === '1'): ?><span class="price meta-price price-missing"><?= e(price_missing_label()) ?></span><?php endif; ?>
            <?php if ($soldOut): ?><span class="tag badge-soldout"><?= e(t('Tükendi')) ?></span><?php endif; ?>
            <?php if ((int) $p['is_featured'] === 1): ?><span class="tag badge-featured"><?= e(t('Öne Çıkan')) ?></span><?php endif; ?>
            <?php foreach ($badges as $b): ?><span class="tag tag-badge"><?= e(diet_label($b)) ?></span><?php endforeach; ?>
            <?php foreach ($diets as $d): ?><span class="tag tag-diet"><?= e(diet_label($d)) ?></span><?php endforeach; ?>
        </div>
    </div>
</a>
