<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/queries.php';
require_once __DIR__ . '/inc/stats.php';
require_installed();

$id = (int) ($_GET['id'] ?? 0);
$categories = active_categories();
$current = null;
foreach ($categories as $c) {
    if ((int) $c['id'] === $id) {
        $current = $c;
        break;
    }
}
if ($current === null) {
    if ($id > 0) {
        http_response_code(404);
        $pageTitle = t('Kategori bulunamadı');
        $backUrl = url('');
        require __DIR__ . '/inc/layout_top.php';
        echo '<div class="empty">' . e(t('Kategori bulunamadı.')) . ' <a href="' . e(url('')) . '">' . e(t('Menüye dön')) . '</a></div>';
        require __DIR__ . '/inc/layout_bottom.php';
        exit;
    }
    if (!$categories) {
        redirect('');
    }
    redirect('kategori.php?id=' . (int) $categories[0]['id']);
}
track_view('category', $id);
$allProducts = products_with_cover('p.category_id = ?', [$id]);

// Diyet filtresi: ?f=vegan,glutensiz  (secilenlerin TUMUNU tasiyan urunler kalir)
$filterRaw = $_GET['f'] ?? '';
$filterIn = is_string($filterRaw) ? explode(',', $filterRaw) : [];
$filterOptions = [];   // bu kategoride en az bir urunde bulunan secenekler
foreach ($allProducts as $p) {
    $filterOptions += diet_to_array((string) $p['diet'], 'diet');
}
$filterOptions = array_intersect_key(diet_to_array(implode(',', array_keys($filterOptions)), 'diet'), $filterOptions);
$filters = array_values(array_filter(array_keys($filterOptions), fn($k) => in_array($k, $filterIn, true)));
$products = array_values(array_filter($allProducts, function (array $p) use ($filters): bool {
    return !array_diff($filters, array_keys(diet_to_array((string) $p['diet'], 'diet')));
}));
$filterUrl = fn(array $keys): string => url('kategori.php?id=' . $id . ($keys ? '&f=' . implode(',', $keys) : ''));

$pageTitle = $current['name'];
$backUrl = url('');
$activeNav = 'menu';
require __DIR__ . '/inc/layout_top.php';
?>
<div class="chip-bar-wrap">
    <div class="chip-bar" id="chipBar">
        <?php foreach ($categories as $c): ?>
            <a class="chip<?= (int) $c['id'] === $id ? ' active' : '' ?>" href="<?= e(url('kategori.php?id=' . (int) $c['id'])) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="cat-head">
    <div class="ornament" aria-hidden="true"></div>
    <h1><?= e($current['name']) ?></h1>
    <?php if ($current['description'] !== ''): ?><p><?= e($current['description']) ?></p><?php endif; ?>
</div>

<?php if ($filterOptions): ?>
    <div class="filter-bar" aria-label="<?= e(t('Filtrele')) ?>">
        <?php foreach ($filterOptions as $key => $opt): $on = in_array($key, $filters, true); ?>
            <a class="filter-chip<?= $on ? ' active' : '' ?>" href="<?= e($filterUrl($on ? array_values(array_diff($filters, [$key])) : array_merge($filters, [$key]))) ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" rel="nofollow"><?= e(diet_label($opt)) ?></a>
        <?php endforeach; ?>
        <?php if ($filters): ?><a class="filter-clear" href="<?= e($filterUrl([])) ?>"><?= e(t('Temizle')) ?></a><?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$allProducts): ?>
    <div class="empty"><?= e(t('Bu kategoride henüz ürün yok.')) ?></div>
<?php elseif (!$products): ?>
    <div class="empty"><?= e(t('Seçtiğiniz filtrelere uyan ürün yok.')) ?></div>
<?php else: ?>
    <div class="product-list">
        <?php foreach ($products as $p) { require __DIR__ . '/inc/product_row.php'; } ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/inc/layout_bottom.php'; ?>
