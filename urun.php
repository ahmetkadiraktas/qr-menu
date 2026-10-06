<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/queries.php';
require_once __DIR__ . '/inc/stats.php';
require_installed();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT p.*, c.name AS category_name, c.name_en AS category_name_en FROM products p
                       JOIN categories c ON c.id = p.category_id
                       WHERE p.id = ? AND p.is_active = 1 AND c.is_active = 1');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    $pageTitle = t('Ürün bulunamadı');
    $backUrl = url('');
    require __DIR__ . '/inc/layout_top.php';
    echo '<div class="empty">' . e(t('Ürün bulunamadı.')) . '</div>';
    require __DIR__ . '/inc/layout_bottom.php';
    exit;
}
$product = localize($product, 'name', 'description', 'details', 'category_name');
track_view('product', $id);
$imgStmt = db()->prepare('SELECT filename FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, sort_order, id');
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
// Urun puani: tek bir 5 uzerinden puan (+ istege bagli yorum), ayni sayfadan gonderilir
$rateErrors = [];
$rateDone = false;
$rateOld = ['score' => 0, 'comment' => '', 'name' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'rate') {
    csrf_check();
    $rateOld = ['score' => (int) post('score', '0'), 'comment' => mb_substr(post('comment'), 0, 500), 'name' => mb_substr(post('name'), 0, 60)];
    if (post('website') !== '') {   // bal tuzagi
        $rateErrors[] = t('Geçersiz gönderim.');
    } elseif ($rateOld['score'] < 1 || $rateOld['score'] > 5) {
        $rateErrors[] = t('Lütfen bir puan seçin.');
    }
    if (!$rateErrors) {
        $pdo = db();
        try {
            $pdo->exec('BEGIN IMMEDIATE');
            $limitError = submission_limit_error($pdo, 'product_ratings', 'rated_' . $id);
            if ($limitError !== null) {
                $pdo->exec('ROLLBACK');
                $rateErrors[] = t($limitError);
            } else {
                $pdo->prepare('INSERT INTO product_ratings (product_id, score, comment, name, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$id, $rateOld['score'], $rateOld['comment'], $rateOld['name'], ip_bucket(), date('Y-m-d H:i:s')]);
                $pdo->exec('COMMIT');
                mark_submission('rated_' . $id);
                $rateDone = true;
            }
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->exec('ROLLBACK');
            }
            error_log('Urun puani hatasi: ' . $t->getMessage());
            $rateErrors[] = t('Bir hata oluştu, lütfen tekrar deneyin.');
        }
    }
}
$rating = product_rating($id);
$badges = diet_to_array((string) $product['diet'], 'badge');
$soldOut = (int) $product['is_sold_out'] === 1;
$diets = diet_to_array((string) $product['diet'], 'diet');
$allergens = diet_to_array((string) $product['diet'], 'allergen');
$showPrices = setting('show_prices', '1') === '1';
$variants = product_variants($product);
$priceLabel = $showPrices ? product_price_label($product) : '';
$facts = [];
if ($product['prep_minutes'] !== null) {
    $facts[] = '⏱ ' . t('%d dk', (int) $product['prep_minutes']);
}
if ($product['calories'] !== null) {
    $facts[] = '🔥 ' . (int) $product['calories'] . ' kcal';
}

$pageTitle = $product['name'];
$backUrl = url('kategori.php?id=' . (int) $product['category_id']);
$activeNav = 'menu';
require __DIR__ . '/inc/layout_top.php';
?>
<div class="gallery-wrap" id="galleryWrap">
    <div class="gallery" id="gallery">
        <?php if (!$images): ?>
            <div><div class="no-image">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 11h18M5 11a7 7 0 0 1 14 0M4 15h16M6 19h12"/></svg>
                <span><?= e($product['name']) ?></span>
            </div></div>
        <?php else: ?>
            <?php foreach ($images as $i => $img): ?>
                <div><img src="<?= e(upload_url('products', $img)) ?>" alt="<?= e($product['name']) ?> <?= $i + 1 ?>"<?= $i > 0 ? ' loading="lazy"' : '' ?>></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if (count($images) > 1): ?>
        <div class="gallery-counter"><span id="galleryIdx">1</span> / <?= count($images) ?></div>
        <div class="gallery-dots" id="galleryDots"><?php foreach ($images as $i => $_): ?><i class="<?= $i === 0 ? 'active' : '' ?>"></i><?php endforeach; ?></div>
        <button class="icon-btn gallery-nav prev" type="button" data-dir="-1" aria-label="<?= e(t('Önceki')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg></button>
        <button class="icon-btn gallery-nav next" type="button" data-dir="1" aria-label="<?= e(t('Sonraki')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 6l6 6-6 6"/></svg></button>
    <?php endif; ?>
</div>

<article class="product-detail">
    <a class="cat-link" href="<?= e($backUrl) ?>"><?= e($product['category_name']) ?></a>
    <div class="head">
        <h1><?= e($product['name']) ?></h1>
        <?php if ($priceLabel !== ''): ?><span class="price"><?= e($priceLabel) ?></span><?php elseif ($showPrices): ?><span class="price price-missing"><?= e(price_missing_label()) ?></span><?php endif; ?>
    </div>
    <?php if ($rating['avg'] !== null): ?>
        <div class="rating-line" style="margin-top:8px"><?= stars_html($rating['avg'], 15) ?> <b><?= e(number_format($rating['avg'], 1, current_lang() === 'en' ? '.' : ',', '')) ?></b> · <?= e(t('%d değerlendirme', (int) $rating['count'])) ?></div>
    <?php endif; ?>
    <?php if ($badges || $diets || $soldOut || (int) $product['is_featured'] === 1): ?>
        <div class="tags">
            <?php if ($soldOut): ?><span class="tag badge-soldout"><?= e(t('Tükendi')) ?></span><?php endif; ?>
            <?php if ((int) $product['is_featured'] === 1): ?><span class="tag badge-featured"><?= e(t('Öne Çıkan')) ?></span><?php endif; ?>
            <?php foreach ($badges as $b): ?><span class="tag"><?= e(diet_label($b)) ?></span><?php endforeach; ?>
            <?php foreach ($diets as $d): ?><span class="tag tag-diet"><?= e(diet_label($d)) ?></span><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($facts): ?><div class="product-facts"><?php foreach ($facts as $f): ?><span><?= e($f) ?></span><?php endforeach; ?></div><?php endif; ?>
    <?php if ($soldOut): ?><div class="soldout-note"><?= e(t('Bu ürün şu an için tükenmiştir.')) ?></div><?php endif; ?>
    <?php if ($product['description'] !== ''): ?><div class="desc"><?= nl2p((string) $product['description']) ?></div><?php endif; ?>
    <?php if ($variants): ?>
        <div class="details variants">
            <h3><?= e(t('Seçenekler')) ?></h3>
            <?php foreach ($variants as $v): ?>
                <div class="variant-line"><span><?= e($v['name']) ?></span><?php if ($showPrices): ?><b class="price<?= $v['price'] === null ? ' price-missing' : '' ?>"><?= e($v['price'] !== null ? format_price($v['price']) : price_missing_label()) ?></b><?php endif; ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($product['details'] !== ''): ?>
        <div class="details"><h3><?= e(t('Detaylar')) ?></h3><?= nl2p((string) $product['details']) ?></div>
    <?php endif; ?>
    <?php if ($allergens): ?>
        <div class="details allergens">
            <h3><?= e(t('İçerir (alerjenler)')) ?></h3>
            <div class="tags"><?php foreach ($allergens as $a): ?><span class="tag tag-allergen"><?= e(diet_label($a)) ?></span><?php endforeach; ?></div>
            <p class="allergen-note"><?= e(t('Alerjiniz varsa lütfen sipariş öncesinde personele danışın.')) ?></p>
        </div>
    <?php endif; ?>
    <div class="details rate-box" id="puan">
        <h3><?= e(t('Bu ürünü puanlayın')) ?></h3>
        <?php if ($rateDone): ?>
            <div class="alert alert-success"><?= e(t('Puanınız kaydedildi, teşekkürler!')) ?></div>
        <?php else: ?>
            <?php foreach ($rateErrors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
            <form method="post" action="<?= e(url('urun.php?id=' . $id)) ?>#puan" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="rate">
                <input class="honey" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="criteria-list"><div class="criterion">
                    <span class="cname"><?= e(t('Puanınız')) ?></span>
                    <span class="star-input" role="radiogroup" aria-label="<?= e(t('Puanınız')) ?>">
                        <?php for ($s = 5; $s >= 1; $s--): ?>
                            <input type="radio" id="rate<?= $s ?>" name="score" value="<?= $s ?>"<?= $rateOld['score'] === $s ? ' checked' : '' ?>>
                            <label for="rate<?= $s ?>" title="<?= e(t('%d yıldız', $s)) ?>"></label>
                        <?php endfor; ?>
                    </span>
                </div></div>
                <div class="form-group">
                    <label for="rate-comment"><?= e(t('Yorumunuz')) ?> <span style="font-weight:400;color:var(--muted)"><?= e(t('(isteğe bağlı)')) ?></span></label>
                    <textarea id="rate-comment" name="comment" maxlength="500" rows="3" placeholder="<?= e(t('Neyi beğendiniz, neyi geliştirebiliriz?')) ?>"><?= e($rateOld['comment']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="rate-name"><?= e(t('Adınız')) ?> <span style="font-weight:400;color:var(--muted)"><?= e(t('(isteğe bağlı)')) ?></span></label>
                    <input type="text" id="rate-name" name="name" maxlength="60" value="<?= e($rateOld['name']) ?>" placeholder="<?= e(t('Anonim')) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block"><?= e(t('Puanı gönder')) ?></button>
            </form>
        <?php endif; ?>
    </div>
    <div class="cta-row">
        <a class="btn" href="<?= e($backUrl) ?>"><?= e(t('Kategoriye dön')) ?></a>
    </div>
</article>
<?php require __DIR__ . '/inc/layout_bottom.php'; ?>
