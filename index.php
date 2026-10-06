<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/queries.php';
require_installed();

$categories = active_categories();
$featured = products_with_cover('p.is_featured = 1 AND EXISTS (SELECT 1 FROM categories c WHERE c.id = p.category_id AND c.is_active = 1)', []);

$pageTitle = '';
$activeNav = 'menu';
require __DIR__ . '/inc/layout_top.php';
?>
<section class="hero">
    <?php if (setting('logo') !== ''): ?>
        <img class="logo-big" src="<?= e(upload_url('site', setting('logo'))) ?>" alt="<?= e(setting('site_name')) ?>">
    <?php endif; ?>
    <h1><?= e(setting('site_name')) ?></h1>
    <?php if (setting_localized('site_tagline') !== ''): ?><p><?= e(setting_localized('site_tagline')) ?></p><?php endif; ?>
    <div class="divider"></div>
    <div class="ornament" aria-hidden="true"></div>
    <?php if (active_campaigns('popup')): ?>
        <button class="btn promo-reopen" type="button" data-promo-open>🎁 <?= e(t('Kampanyalar')) ?></button>
    <?php endif; ?>
</section>

<?php if (!$categories): ?>
    <div class="empty"><?= e(t('Henüz menü eklenmemiş.')) ?></div>
<?php else: ?>
    <div class="cat-grid">
        <?php foreach ($categories as $i => $c) {
            $cardHref = url('kategori.php?id=' . (int) $c['id']);
            $cardIndex = $i + 1;
            require __DIR__ . '/inc/category_card.php';
        } ?>
    </div>
<?php endif; ?>

<?php if ($featured): ?>
    <div class="section-title"><h2><?= e(t('Öne Çıkanlar')) ?></h2><span><?= e(t('Menümüzden seçmeler')) ?></span></div>
    <div class="product-list">
        <?php foreach ($featured as $p) { require __DIR__ . '/inc/product_row.php'; } ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/inc/layout_bottom.php'; ?>
