<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/queries.php';
require_installed();

$q = query('q', 60);
$results = [];
if ($q !== '') {
    // SQLite LIKE Turkce harflerde buyuk/kucuk ayirir ("çay" ile "Çay" eslesmez); urun sayisi az oldugu icin suzme PHP'de yapilir
    $rozetler = [];
    foreach (diet_options() as $key => $opt) {
        if ($opt[2] !== 'allergen' && search_matches($q, $opt[0], t($opt[0]))) {
            $rozetler[] = $key;
        }
    }
    $all = products_with_cover('EXISTS (SELECT 1 FROM categories c WHERE c.id = p.category_id AND c.is_active = 1)', []);
    $results = array_values(array_filter($all, function (array $p) use ($q, $rozetler): bool {
        if (search_matches($q, (string) $p['name'], (string) $p['description'], (string) $p['details'], (string) $p['name_en'], (string) $p['description_en'], (string) $p['details_en'])) {
            return true;
        }
        return (bool) array_intersect($rozetler, array_map('trim', explode(',', (string) $p['diet'])));
    }));
}

$pageTitle = t('Ara');
$backUrl = url('');
$activeNav = 'search';
require __DIR__ . '/inc/layout_top.php';
?>
<form class="search-box" method="get" action="<?= e(url('ara.php')) ?>">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Ürün adı, malzeme veya özellik...')) ?>" autofocus maxlength="60">
    <button class="btn btn-primary" type="submit"><?= e(t('Ara')) ?></button>
</form>

<?php if ($q === ''): ?>
    <div class="empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <div><?= e(t('Aramak istediğiniz ürünü yazın.')) ?></div>
    </div>
<?php elseif (!$results): ?>
    <div class="empty"><?= e(t('"%s" için sonuç bulunamadı.', $q)) ?></div>
<?php else: ?>
    <div class="section-title"><h2><?= e(t('Sonuçlar')) ?></h2><span><?= e(t('%d ürün', count($results))) ?></span></div>
    <div class="product-list">
        <?php foreach ($results as $p) { require __DIR__ . '/inc/product_row.php'; } ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/inc/layout_bottom.php'; ?>
