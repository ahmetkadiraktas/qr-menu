<?php
/**
 * Admin "Menu" sayfasinin popup formlari (kategori ve urun).
 * Beklenen: $categories, $catId, $q, $editCat (array|null), $editProduct (array|null), $editImages (array),
 *           $openModal ('cat'|'prod'|''), $closeHref (string), $menuFlash (string)
 */
declare(strict_types=1);

$catHasEn = $editCat && (trim((string) $editCat['name_en']) !== '' || trim((string) $editCat['description_en']) !== '');
$curVariants = json_decode((string) ($editProduct['variants'] ?? ''), true);
$curVariants = is_array($curVariants) ? array_values($curVariants) : [];
$prodHasEn = $editProduct && (trim($editProduct['name_en'] . $editProduct['description_en'] . $editProduct['details_en']) !== ''
    || (bool) array_filter($curVariants, fn($v) => is_array($v) && trim((string) ($v['name_en'] ?? '')) !== ''));
$curDiet = array_map('trim', explode(',', (string) ($editProduct['diet'] ?? '')));
$selectedCat = $editProduct ? (int) $editProduct['category_id'] : $catId;
$showPrices = setting('show_prices', '1') === '1';
$num = fn($v): string => $v !== null && is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') : '';

/** Popup basligi: baslik, Ingilizce dugmesi ve kapatma. Duzenleme modunda kapatma, popup'siz adrese goturur. */
$modalHead = function (string $titleId, string $title, bool $hasEn, bool $navigates) use ($closeHref): string {
    return '<div class="modal-head">'
        . '<h2 id="' . $titleId . '">' . e($title) . '</h2>'
        . '<button type="button" class="btn btn-sm en-toggle" data-en-toggle aria-pressed="' . ($hasEn ? 'true' : 'false') . '" data-label-on="İngilizceyi gizle" data-label-off="İngilizce ekle">' . ($hasEn ? 'İngilizceyi gizle' : 'İngilizce ekle') . '</button>'
        . ($navigates
            ? '<a class="modal-close" href="' . e($closeHref) . '" aria-label="Kapat">✕</a>'
            : '<button type="button" class="modal-close" data-modal-close aria-label="Kapat">✕</button>')
        . '</div>';
};
$cancelButton = fn(bool $navigates): string => $navigates
    ? '<a class="btn" href="' . e($closeHref) . '">Vazgeç</a>'
    : '<button type="button" class="btn" data-modal-close>Vazgeç</button>';
?>
<!-- Kategori popup'i -->
<div class="modal" id="catModal"<?= $openModal === 'cat' ? '' : ' hidden' ?><?= $openModal === 'cat' ? ' data-close-href="' . e($closeHref) . '"' : '' ?>>
    <div class="modal-box<?= $catHasEn ? ' with-en' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="catModalTitle">
        <?= $modalHead('catModalTitle', $isEditCat ? 'Kategoriyi Düzenle' : 'Yeni Kategori', (bool) $catHasEn, $openModal === 'cat') ?>
        <form method="post" enctype="multipart/form-data" class="modal-body">
            <?php if ($openModal === 'cat') { echo $menuFlash; } ?>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cat_save">
            <input type="hidden" name="id" value="<?= $isEditCat ? (int) $editCat['id'] : 0 ?>">
            <input type="hidden" name="back_cat" value="<?= $catId ?>">
            <div class="pair">
                <div class="form-row"><label>Ad *</label><input type="text" name="name" required maxlength="80" value="<?= e($editCat['name'] ?? '') ?>"></div>
                <div class="form-row en"><label>Ad <span class="muted">(İngilizce)</span></label><input type="text" name="name_en" maxlength="80" value="<?= e($editCat['name_en'] ?? '') ?>"></div>
            </div>
            <div class="pair">
                <div class="form-row"><label>Kısa açıklama</label><input type="text" name="description" maxlength="300" value="<?= e($editCat['description'] ?? '') ?>" placeholder="Kategori sayfasında başlığın altında görünür"></div>
                <div class="form-row en"><label>Kısa açıklama <span class="muted">(İngilizce)</span></label><input type="text" name="description_en" maxlength="300" value="<?= e($editCat['description_en'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <label>Kapak resmi</label>
                <?php if ($editCat && $editCat['image'] !== ''): ?>
                    <img class="site-img-preview" src="<?= e(upload_url('categories', $editCat['image'], true)) ?>" alt="">
                    <label class="check" style="margin-bottom:8px"><input type="checkbox" name="remove_image"> Resmi kaldır</label>
                <?php endif; ?>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-preview="catPreview">
                <div class="preview-strip" id="catPreview"></div>
                <div class="hint">JPG, PNG veya WebP · en fazla 8 MB · 4:3 oran önerilir</div>
            </div>
            <div class="form-row inline">
                <label class="check"><input type="checkbox" name="is_active" <?= !$editCat || (int) $editCat['is_active'] ? 'checked' : '' ?>> Menüde göster</label>
            </div>
            <div class="modal-foot">
                <?= $cancelButton($openModal === 'cat') ?>
                <button class="btn btn-primary" type="submit"><?= $isEditCat ? 'Kaydet' : 'Ekle' ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($categories): ?>
<!-- Urun popup'i -->
<div class="modal" id="prodModal"<?= $openModal === 'prod' ? '' : ' hidden' ?><?= $openModal === 'prod' ? ' data-close-href="' . e($closeHref) . '"' : '' ?>>
    <div class="modal-box<?= $prodHasEn ? ' with-en' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="prodModalTitle">
        <?= $modalHead('prodModalTitle', $isEditProd ? 'Ürünü Düzenle' : 'Yeni Ürün', (bool) $prodHasEn, $openModal === 'prod') ?>
        <form method="post" enctype="multipart/form-data" class="modal-body">
            <?php if ($openModal === 'prod') { echo $menuFlash; } ?>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="prod_save">
            <input type="hidden" name="id" value="<?= $isEditProd ? (int) $editProduct['id'] : 0 ?>">
            <input type="hidden" name="back_cat" value="<?= $catId ?>">
            <input type="hidden" name="back_q" value="<?= e($q) ?>">

            <div class="pair">
                <div class="form-row"><label>Ürün adı *</label><input type="text" name="name" required maxlength="120" value="<?= e($editProduct['name'] ?? '') ?>"></div>
                <div class="form-row en"><label>Ürün adı <span class="muted">(İngilizce)</span></label><input type="text" name="name_en" maxlength="120" value="<?= e($editProduct['name_en'] ?? '') ?>"></div>
            </div>
            <div class="form-grid">
                <div class="form-row">
                    <label>Kategori *</label>
                    <select name="category_id" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $selectedCat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Fiyat <span class="muted">(boş bırakılabilir)</span></label>
                    <input type="text" inputmode="decimal" name="price" value="<?= e($num($editProduct['price'] ?? null)) ?>" placeholder="Örn. 250"><div class="hint">Yalnızca rakam; kuruş için virgül (örn. 12,50).</div>
                    <?php if (!$showPrices): ?><div class="hint">Fiyat gösterimi ayarlardan kapalı.</div><?php endif; ?>
                </div>
            </div>
            <div class="pair">
                <div class="form-row"><label>Kısa açıklama</label><textarea name="description" maxlength="1000" style="min-height:80px" placeholder="Listede ve detayda görünen açıklama"><?= e($editProduct['description'] ?? '') ?></textarea></div>
                <div class="form-row en"><label>Kısa açıklama <span class="muted">(İngilizce)</span></label><textarea name="description_en" maxlength="1000" style="min-height:80px"><?= e($editProduct['description_en'] ?? '') ?></textarea></div>
            </div>
            <div class="pair">
                <div class="form-row"><label>Detaylar <span class="muted">(içindekiler, porsiyon vb.)</span></label><textarea name="details" maxlength="3000" style="min-height:80px" placeholder="Sadece ürün detay sayfasında görünür"><?= e($editProduct['details'] ?? '') ?></textarea></div>
                <div class="form-row en"><label>Detaylar <span class="muted">(İngilizce)</span></label><textarea name="details_en" maxlength="3000" style="min-height:80px"><?= e($editProduct['details_en'] ?? '') ?></textarea></div>
            </div>

            <div class="form-row" style="margin-bottom:0"><label>Porsiyon / seçenekler <span class="muted">(isteğe bağlı)</span></label></div>
            <?php for ($v = 0; $v < VARIANT_ROWS; $v++): $cv = is_array($curVariants[$v] ?? null) ? $curVariants[$v] : []; ?>
                <div class="pair variant-pair">
                    <div class="variant-row">
                        <input type="text" name="variants[<?= $v ?>][name]" maxlength="40" value="<?= e((string) ($cv['name'] ?? '')) ?>" placeholder="<?= $v === 0 ? 'Örn. Yarım' : ($v === 1 ? 'Örn. Tam' : 'Seçenek adı') ?>" aria-label="Seçenek <?= $v + 1 ?> adı">
                        <input type="text" inputmode="decimal" name="variants[<?= $v ?>][price]" value="<?= e($num($cv['price'] ?? null)) ?>" placeholder="Fiyat" aria-label="Seçenek <?= $v + 1 ?> fiyatı">
                    </div>
                    <div class="en"><input type="text" name="variants[<?= $v ?>][name_en]" maxlength="40" value="<?= e((string) ($cv['name_en'] ?? '')) ?>" placeholder="İngilizce adı" aria-label="Seçenek <?= $v + 1 ?> İngilizce adı"></div>
                </div>
            <?php endfor; ?>
            <div class="form-row"><div class="hint">Her seçeneğe fiyat yazın; seçenek girilince menüde tek fiyat yerine seçenek fiyatları (en düşük – en yüksek) gösterilir. Adı boş satırlar yok sayılır.</div></div>

            <div class="form-grid">
                <div class="form-row"><label>Kalori <span class="muted">(kcal)</span></label><input type="text" inputmode="numeric" name="calories" maxlength="5" value="<?= e((string) ($editProduct['calories'] ?? '')) ?>" placeholder="Örn. 450"></div>
                <div class="form-row"><label>Hazırlama süresi <span class="muted">(dakika)</span></label><input type="text" inputmode="numeric" name="prep_minutes" maxlength="3" value="<?= e((string) ($editProduct['prep_minutes'] ?? '')) ?>" placeholder="Örn. 15"></div>
            </div>

            <?php foreach (['badge' => 'Rozetler', 'diet' => 'Diyet / özellik', 'allergen' => 'Alerjenler (içerir)'] as $type => $groupLabel): ?>
            <div class="form-row">
                <label><?= e($groupLabel) ?></label>
                <div class="inline">
                    <?php foreach (diet_options() as $key => $opt): if ($opt[2] !== $type) { continue; } ?>
                        <label class="check"><input type="checkbox" name="diet[]" value="<?= e($key) ?>" <?= in_array($key, $curDiet, true) ? 'checked' : '' ?>> <?= e(diet_label($opt)) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="form-row inline">
                <label class="check"><input type="checkbox" name="is_active" <?= !$editProduct || (int) $editProduct['is_active'] ? 'checked' : '' ?>> Menüde göster</label>
                <label class="check"><input type="checkbox" name="is_featured" <?= $editProduct && (int) $editProduct['is_featured'] ? 'checked' : '' ?>> Öne çıkar</label>
                <label class="check"><input type="checkbox" name="is_sold_out" <?= $editProduct && (int) $editProduct['is_sold_out'] ? 'checked' : '' ?>> Tükendi</label>
            </div>

            <div class="form-row">
                <label>Resimler<?= $editImages ? ' <span class="muted">(' . count($editImages) . ')</span>' : '' ?></label>
                <?php if ($editImages): ?>
                    <div class="img-grid" style="margin-bottom:12px">
                        <?php foreach ($editImages as $i => $img): $imgId = (int) $img['id']; ?>
                            <div class="img-item <?= (int) $img['is_cover'] ? 'cover' : '' ?>">
                                <?php if ((int) $img['is_cover']): ?><span class="cover-badge">KAPAK</span><?php endif; ?>
                                <a href="<?= e(upload_url('products', $img['filename'])) ?>" target="_blank"><img src="<?= e(upload_url('products', $img['filename'], true)) ?>" alt=""></a>
                                <div class="bar">
                                    <span>
                                        <button class="btn btn-sm" type="submit" form="imgForm" name="op" value="up:<?= $imgId ?>" formnovalidate <?= $i === 0 ? 'disabled' : '' ?> title="Öne al">←</button>
                                        <button class="btn btn-sm" type="submit" form="imgForm" name="op" value="down:<?= $imgId ?>" formnovalidate <?= $i === count($editImages) - 1 ? 'disabled' : '' ?> title="Arkaya al">→</button>
                                    </span>
                                    <span>
                                        <?php if (!(int) $img['is_cover']): ?><button class="btn btn-sm" type="submit" form="imgForm" name="op" value="cover:<?= $imgId ?>" formnovalidate title="Kapak yap">★</button><?php endif; ?>
                                        <button class="btn btn-sm btn-danger" type="submit" form="imgForm" name="op" value="delete:<?= $imgId ?>" formnovalidate data-confirm="Resim silinecek." title="Sil">✕</button>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" data-preview="prodPreview">
                <div class="preview-strip" id="prodPreview"></div>
                <div class="hint">Birden fazla seçebilirsiniz · JPG, PNG, WebP · her biri en fazla 8 MB<?= $editImages ? ' · ★ kapak resmini seçer, oklar sırayı değiştirir' : '' ?></div>
            </div>

            <div class="modal-foot">
                <?= $cancelButton($openModal === 'prod') ?>
                <button class="btn btn-primary" type="submit"><?= $isEditProd ? 'Kaydet' : 'Ürünü Ekle' ?></button>
            </div>
        </form>
        <?php if ($editProduct): ?>
            <!-- Resim dugmeleri ana formun icinde oldugu icin (ic ice form olmaz) bu ayri forma form="imgForm" ile baglanir -->
            <form method="post" id="imgForm" hidden>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="prod_image">
                <input type="hidden" name="id" value="<?= (int) $editProduct['id'] ?>">
                <input type="hidden" name="back_cat" value="<?= $catId ?>">
                <input type="hidden" name="back_q" value="<?= e($q) ?>">
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
