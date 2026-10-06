<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/menu_actions.php';
require_once __DIR__ . '/../inc/themes.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    menu_handle_post($pdo);
}

$catId = (int) ($_GET['kategori'] ?? 0);
$q = query('q', 60);
$categories = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS cnt
                           FROM categories c ORDER BY c.sort_order, c.id')->fetchAll();
$byId = array_column($categories, null, 'id');

// Duzenlenecek kayitlar (popup acik ve dolu gelir)
$editCat = $byId[(int) ($_GET['kat_duzenle'] ?? 0)] ?? null;
$editProduct = null;
$editImages = [];
if ((int) ($_GET['urun'] ?? 0) > 0) {
    $s = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $s->execute([(int) $_GET['urun']]);
    $editProduct = $s->fetch() ?: null;
    if (!$editProduct) {
        flash('error', 'Ürün bulunamadı.');
        redirect(menu_path($catId));
    }
    if ($catId === 0 && $q === '') {
        $catId = (int) $editProduct['category_id'];
    }
    $s = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, sort_order, id');   // musterinin gordugu galeri sirasi
    $s->execute([(int) $editProduct['id']]);
    $editImages = $s->fetchAll();
}
$current = $byId[$catId] ?? null;
if ($catId > 0 && !$current) {
    flash('error', 'Kategori bulunamadı.');
    redirect(menu_path());
}

$new = (string) ($_GET['yeni'] ?? '');
$openModal = $editCat ? 'cat' : ($editProduct ? 'prod' : ($new === 'kategori' ? 'cat' : ($new === 'urun' ? 'prod' : '')));
$isEditProd = $editProduct !== null;
$isEditCat = $editCat !== null;
// Hata sonrasi: gonderilen alanlar formda kalsin (yeni kayitta da, duzenlemede de)
if ($openModal === 'prod' && ($old = recall_form('prod')) !== null) {
    $dietOld = isset($old['diet']) && is_array($old['diet']) ? implode(',', array_map('strval', $old['diet'])) : '';
    $variantsOld = isset($old['variants']) && is_array($old['variants']) ? (string) json_encode(array_values(array_map(fn($v) => is_array($v) ? ['name' => (string) ($v['name'] ?? ''), 'name_en' => (string) ($v['name_en'] ?? ''), 'price' => (string) ($v['price'] ?? '')] : [], $old['variants'])), JSON_UNESCAPED_UNICODE) : '';
    $editProduct = array_merge($editProduct ?? ['id' => 0], [
        'name' => (string) ($old['name'] ?? ''), 'name_en' => (string) ($old['name_en'] ?? ''), 'category_id' => (int) ($old['category_id'] ?? $catId),
        'price' => is_numeric($old['price'] ?? null) ? (float) str_replace(',', '.', (string) $old['price']) : null,
        'description' => (string) ($old['description'] ?? ''), 'description_en' => (string) ($old['description_en'] ?? ''),
        'details' => (string) ($old['details'] ?? ''), 'details_en' => (string) ($old['details_en'] ?? ''),
        'variants' => $variantsOld, 'calories' => (string) ($old['calories'] ?? ''), 'prep_minutes' => (string) ($old['prep_minutes'] ?? ''), 'diet' => $dietOld,
        'is_active' => isset($old['is_active']) ? 1 : 0, 'is_featured' => isset($old['is_featured']) ? 1 : 0, 'is_sold_out' => isset($old['is_sold_out']) ? 1 : 0,
    ]);
}
if ($openModal === 'cat' && ($old = recall_form('cat')) !== null) {
    $editCat = array_merge($editCat ?? ['id' => 0, 'image' => ''], [
        'name' => (string) ($old['name'] ?? ''), 'name_en' => (string) ($old['name_en'] ?? ''),
        'description' => (string) ($old['description'] ?? ''), 'description_en' => (string) ($old['description_en'] ?? ''),
        'is_active' => isset($old['is_active']) ? 1 : 0,
    ]);
}
if ($openModal === 'prod' && !$categories) {
    flash('error', 'Önce bir kategori oluşturmalısınız.');
    redirect(menu_path(0, ['yeni' => 'kategori']));
}
$closeHref = url(menu_path($catId, ['q' => $q]));

// Listelenecek urunler: kategorinin icindekiler veya arama sonuclari (musteri menusundeki sirayla, gizliler dahil)
$products = [];
if ($current || $q !== '') {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name,
            (SELECT filename FROM product_images i WHERE i.product_id = p.id ORDER BY i.is_cover DESC, i.sort_order, i.id LIMIT 1) AS cover
            FROM products p JOIN categories c ON c.id = p.category_id
            WHERE " . ($q !== '' ? '1=1' : 'p.category_id = ?') . " ORDER BY " . ($q !== '' ? 'c.sort_order, c.id, ' : '') . 'p.is_featured DESC, p.sort_order, p.id');
    $stmt->execute($q !== '' ? [] : [$catId]);
    $products = $stmt->fetchAll();
    if ($q !== '') {
        // Turkce harflere duyarsiz arama (bkz. search_matches)
        $products = array_values(array_filter($products, fn(array $p): bool => search_matches($q, (string) $p['name'], (string) $p['name_en'])));
    }
}

// Onizleme alani musteri menusunun secili temasini kullanir
$theme = current_theme();
$themeStyle = theme_style_attr($theme);
$themeFonts = theme_fonts_url($theme);
$extraHead = ($themeFonts !== '' ? '<link href="' . e($themeFonts) . '" rel="stylesheet">' . "\n" : '')
    . '<link rel="stylesheet" href="' . e(url('assets/css/menu-base.css')) . '?v=1">' . "\n"
    . '<link rel="stylesheet" href="' . e(url('assets/css/themes/' . $theme . '.css')) . '?v=' . e((string) @filemtime(ROOT_PATH . '/assets/css/themes/' . $theme . '.css')) . '">' . "\n";
// Ekleme dugmeleri: JS popup'i sayfayi yenilemeden acar; bir popup adresle acikken (duzenleme) baglanti olarak calisir
$addCatHref = url(menu_path($catId, ['yeni' => 'kategori']));
$addProdHref = url(menu_path($catId, ['yeni' => 'urun']));

$menuFlash = flash_render();   // acik popup varsa mesajlar popup'in icinde gosterilir
$pageTitle = 'Menü';
$activeMenu = 'menu';
require __DIR__ . '/../inc/admin_top.php';
?>
<?php if ($openModal === '') { echo $menuFlash; } ?>
<div class="page-head">
    <h1>Menü</h1>
    <div class="actions">
        <form class="inline" method="get" action="<?= e(url('admin/menu.php')) ?>" role="search">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Ürün ara..." maxlength="60" style="width:180px" aria-label="Ürün ara">
            <button class="btn" type="submit">Ara</button>
        </form>
        <a class="btn" href="<?= e(url($current ? 'kategori.php?id=' . $catId : '')) ?>" target="_blank">Menüde gör ↗</a>
    </div>
</div>

<div class="mp-head">
<?php if ($q !== '' || $current): ?>
    <a class="btn mp-back" href="<?= e(url(menu_path())) ?>">← Kategoriler</a>
    <div class="mp-title">
        <?php if ($q !== ''): ?>
            <h2>"<?= e($q) ?>" için sonuçlar</h2><p class="muted"><?= count($products) ?> ürün</p>
        <?php else: ?>
            <h2><?= e($current['name']) ?><?php if (!(int) $current['is_active']): ?> <span class="status off">Gizli</span><?php endif; ?></h2>
            <p class="muted"><?= count($products) ?> ürün<?= count($products) > 1 ? ' · sıralamak için ⠿ işaretinden tutup sürükleyin' : '' ?></p>
        <?php endif; ?>
    </div>
    <?php if ($categories): ?><a class="btn btn-primary" href="<?= e($addProdHref) ?>" data-modal-open="prodModal">+ Ürün ekle</a><?php endif; ?>
<?php else: ?>
    <div class="mp-title">
        <h2>Kategoriler</h2>
        <p class="muted"><?= count($categories) ?> kategori · içine girip ürünleri yönetin<?= count($categories) > 1 ? ' · sıralamak için ⠿ işaretinden tutup sürükleyin' : '' ?></p>
    </div>
    <a class="btn btn-primary" href="<?= e($addCatHref) ?>" data-modal-open="catModal">+ Kategori ekle</a>
<?php endif; ?>
    <span class="muted mp-status" data-sort-status hidden></span>
</div>

<?php /* Onizleme alani: musteri menusuyle ayni sablon parcalari ve secili temanin stilleri (.m .t-<tema>) */ ?>
<div class="mp m t-<?= e($theme) ?>"<?= $themeStyle !== '' ? ' style="' . e($themeStyle) . '"' : '' ?>>
<?php if ($q !== '' || $current): ?>
    <?php if (!$products): ?>
        <div class="empty"><?= $q !== '' ? 'Eşleşen ürün bulunamadı.' : 'Bu kategoride henüz ürün yok. "+ Ürün ekle" ile başlayın.' ?></div>
    <?php endif; ?>
    <div class="product-list"<?= $q === '' ? ' data-sort="prod_order" data-sort-cat="' . $catId . '"' : '' ?>>
        <?php foreach ($products as $p):
            $pid = (int) $p['id'];
            $hidden = !(int) $p['is_active'];
            $soldOut = (int) $p['is_sold_out'] === 1;
            $rowHref = url(menu_path($q !== '' ? 0 : $catId, ['q' => $q, 'urun' => $pid]));
            $rowShowCategory = $q !== '';
            $rowForcePrices = true;
        ?>
            <div class="mp-item<?= $hidden ? ' is-hidden' : '' ?>" data-id="<?= $pid ?>" data-group="<?= (int) $p['is_featured'] ?>">
                <?php require __DIR__ . '/../inc/product_row.php'; ?>
                <div class="mp-tools">
                    <details class="kebab">
                        <summary aria-label="<?= e($p['name']) ?> işlemleri">⋮</summary>
                        <div class="kebab-menu">
                            <a href="<?= e($rowHref) ?>">Düzenle</a>
                            <?= menu_action_form('prod_soldout', $pid, $soldOut ? 'Stokta olarak işaretle' : 'Tükendi olarak işaretle', $catId, $q) ?>
                            <?= menu_action_form('prod_toggle', $pid, $hidden ? 'Menüde göster' : 'Menüden gizle', $catId, $q) ?>
                            <?= menu_action_form('prod_feature', $pid, (int) $p['is_featured'] ? 'Öne çıkarmayı kaldır' : 'Öne çıkar', $catId, $q) ?>
                            <?= menu_action_form('prod_delete', $pid, 'Sil', $catId, $q, [], "'" . $p['name'] . "' silinecek. Ürüne yazılmış yorumlar genel yoruma dönüşür.") ?>
                        </div>
                    </details>
                    <?php if ($q === '' && count($products) > 1): ?><span class="drag-handle" title="Sürükleyerek sırala" aria-hidden="true">⠿</span><?php endif; ?>
                    <?php if ($hidden): ?><span class="status off">Gizli</span><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php if (!$categories): ?>
        <div class="empty">Henüz kategori yok. "+ Kategori ekle" ile başlayın.</div>
    <?php endif; ?>
    <div class="cat-grid" data-sort="cat_order">
        <?php foreach ($categories as $i => $c):
            $cid = (int) $c['id'];
            $hidden = !(int) $c['is_active'];
            $cardHref = url(menu_path($cid));
            $cardIndex = $i + 1;
        ?>
            <div class="mp-cat<?= $hidden ? ' is-hidden' : '' ?>" data-id="<?= $cid ?>" data-group="0">
                <?php require __DIR__ . '/../inc/category_card.php'; ?>
                <?php if (count($categories) > 1): ?><span class="drag-handle" title="Sürükleyerek sırala" aria-hidden="true">⠿</span><?php endif; ?>
                <?php if ($hidden): ?><span class="status off mp-flag-corner">Gizli</span><?php endif; ?>
                <details class="kebab">
                    <summary aria-label="<?= e($c['name']) ?> işlemleri">⋮</summary>
                    <div class="kebab-menu">
                        <a href="<?= e(url(menu_path(0, ['kat_duzenle' => $cid]))) ?>">Düzenle</a>
                        <?= menu_action_form('cat_toggle', $cid, $hidden ? 'Menüde göster' : 'Menüden gizle', 0, '') ?>
                        <?= menu_action_form('cat_delete', $cid, 'Sil', 0, '', [], "'" . $c['name'] . "' kategorisi" . ((int) $c['cnt'] > 0 ? ' ve içindeki ' . (int) $c['cnt'] . ' ürün' : '') . ' silinecek.') ?>
                    </div>
                </details>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/../inc/menu_modals.php'; ?>
<script src="<?= e(url('assets/js/sortable.min.js')) ?>?v=1"></script>
<?php require __DIR__ . '/../inc/admin_bottom.php'; ?>
