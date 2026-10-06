<?php
/**
 * Kategori karti parcasi (musteri ana sayfasi ve admin Menu sayfasi ortak kullanir).
 * Beklenen: $c (array: id, name, image; product_count veya cnt), $cardHref (string), $cardIndex (int, 1'den baslar)
 */
declare(strict_types=1);
$cardCount = (int) ($c['product_count'] ?? $c['cnt'] ?? 0);
?>
<a class="cat-card cyc-<?= (($cardIndex - 1) % 6) + 1 ?><?= $c['image'] === '' ? ' no-photo' : '' ?>" href="<?= e($cardHref) ?>" style="--i:<?= (int) $cardIndex ?>">
    <span class="idx" aria-hidden="true"><?= sprintf('%02d', $cardIndex) ?></span>
    <?php if ($c['image'] !== ''): ?>
        <img src="<?= e(upload_url('categories', $c['image'])) ?>" alt="" loading="lazy">
    <?php else: ?>
        <div class="placeholder" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></div>
    <?php endif; ?>
    <div class="label"><span class="label-name"><?= e($c['name']) ?></span><span class="count"><?= e(t('%d ürün', $cardCount)) ?></span></div>
</a>
