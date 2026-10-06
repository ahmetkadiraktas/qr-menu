<?php
/** Admin "Menu" sayfasinin kayit islemleri (kategori, urun, urun resimleri) ve ortak yardimcilari */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/** Menu sayfasinin adresi (redirect() ve url() icin goreli yol). $cat = 0 ise kategori listesi. */
function menu_path(int $cat = 0, array $extra = []): string
{
    $query = array_filter(['kategori' => $cat > 0 ? $cat : null] + $extra, fn($v) => $v !== null && $v !== '');
    return 'admin/menu.php' . ($query ? '?' . http_build_query($query) : '');
}

/** Uc nokta menusundeki tek dugmeli islem formu. */
function menu_action_form(string $action, int $id, string $label, int $backCat, string $backQ, array $extra = [], string $confirm = ''): string
{
    $html = '<form method="post"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
          . '<input type="hidden" name="action" value="' . e($action) . '">'
          . '<input type="hidden" name="id" value="' . $id . '">'
          . '<input type="hidden" name="back_cat" value="' . $backCat . '">'
          . '<input type="hidden" name="back_q" value="' . e($backQ) . '">';
    foreach ($extra as $name => $value) {
        $html .= '<input type="hidden" name="' . e((string) $name) . '" value="' . e((string) $value) . '">';
    }
    return $html . '<button type="submit"' . ($confirm !== '' ? ' class="danger"' : '') . '>' . e($label) . '</button></form>';
}

/** Sirali id listesinde $id'yi bir one/arkaya alir ve tablonun sort_order kolonunu yeniden numaralar. */
function menu_reorder(PDO $pdo, string $table, array $ids, int $id, string $dir): void
{
    $ids = array_map('intval', $ids);
    $idx = array_search($id, $ids, true);
    $to = $idx + ($dir === 'up' ? -1 : 1);
    if ($idx !== false && isset($ids[$to])) {
        [$ids[$idx], $ids[$to]] = [$ids[$to], $ids[$idx]];
    }
    $upd = $pdo->prepare("UPDATE $table SET sort_order = ? WHERE id = ?");
    foreach ($ids as $i => $rowId) {
        $upd->execute([$i, $rowId]);
    }
}

/**
 * Surukle-birak ile gelen sirayi kaydeder. $ids: "4,1,3" gibi virgullu id listesi.
 * Urunlerde yalnizca verilen kategoriye ait kayitlar guncellenir.
 */
function menu_save_order(PDO $pdo, string $table, string $ids, int $categoryId = 0): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $ids)), fn($id) => $id > 0)));
    $upd = $pdo->prepare("UPDATE $table SET sort_order = ? WHERE id = ?" . ($table === 'products' ? ' AND category_id = ?' : ''));
    foreach ($ids as $i => $rowId) {
        $upd->execute($table === 'products' ? [$i, $rowId, $categoryId] : [$i, $rowId]);
    }
}

function menu_save_category(PDO $pdo, int $id): void
{
    $name = mb_substr(post('name'), 0, 80);
    $desc = mb_substr(post('description'), 0, 300);
    $nameEn = mb_substr(post('name_en'), 0, 80);
    $descEn = mb_substr(post('description_en'), 0, 300);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($name === '') {
        throw new RuntimeException('Kategori adı boş olamaz.');
    }
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $image = upload_image($_FILES['image'], 'categories');
    }
    if ($id > 0) {
        $old = $pdo->prepare('SELECT image FROM categories WHERE id = ?');
        $old->execute([$id]);
        $oldImage = (string) $old->fetchColumn();
        if ($image === '') {
            $image = isset($_POST['remove_image']) ? '' : $oldImage;
        }
        if ($oldImage !== '' && $oldImage !== $image) {
            delete_upload('categories', $oldImage);
        }
        $pdo->prepare('UPDATE categories SET name=?, description=?, name_en=?, description_en=?, image=?, is_active=? WHERE id=?')
            ->execute([$name, $desc, $nameEn, $descEn, $image, $active, $id]);
        flash('success', 'Kategori güncellendi.');
    } else {
        $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM categories')->fetchColumn();
        $pdo->prepare('INSERT INTO categories (name, description, name_en, description_en, image, sort_order, is_active) VALUES (?,?,?,?,?,?,?)')
            ->execute([$name, $desc, $nameEn, $descEn, $image, $next, $active]);
        flash('success', 'Kategori eklendi.');
    }
}

function menu_delete_category(PDO $pdo, int $id): void
{
    $imgs = $pdo->prepare('SELECT i.filename FROM product_images i JOIN products p ON p.id = i.product_id WHERE p.category_id = ?');
    $imgs->execute([$id]);
    foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $f) {
        delete_upload('products', (string) $f);
    }
    $old = $pdo->prepare('SELECT image FROM categories WHERE id = ?');
    $old->execute([$id]);
    delete_upload('categories', (string) $old->fetchColumn());
    $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    flash('success', 'Kategori silindi.');
}

/** Formdaki porsiyon/secenek satirlarini dogrular; JSON metni (yoksa bos) dondurur. */
function menu_variants_from_post(): string
{
    $variantsIn = isset($_POST['variants']) && is_array($_POST['variants']) ? $_POST['variants'] : [];
    $variants = [];
    foreach (array_slice($variantsIn, 0, VARIANT_ROWS) as $row) {
        $vName = is_array($row) && is_string($row['name'] ?? null) ? mb_substr(trim($row['name']), 0, 40) : '';
        if ($vName === '') {
            continue;
        }
        $vPriceRaw = is_string($row['price'] ?? null) ? str_replace(',', '.', trim($row['price'])) : '';
        if ($vPriceRaw === '') {
            throw new RuntimeException('"' . $vName . '" seçeneğine fiyat yazın. Her seçeneğin fiyatı olmalı.');
        }
        if (!is_numeric($vPriceRaw) || (float) $vPriceRaw < 0 || (float) $vPriceRaw > 1000000) {
            throw new RuntimeException('"' . $vName . '" seçeneğinin fiyatı geçersiz: yalnızca rakam, 0 ile 1.000.000 arasında.');
        }
        $variants[] = [
            'name'    => $vName,
            'name_en' => is_string($row['name_en'] ?? null) ? mb_substr(trim($row['name_en']), 0, 40) : '',
            'price'   => $vPriceRaw === '' ? null : round((float) $vPriceRaw, 2),
        ];
    }
    return $variants ? (string) json_encode($variants, JSON_UNESCAPED_UNICODE) : '';
}

/** Formla gelen yeni urun resimlerini yukler. [yuklenen sayisi, hata mesajlari] dondurur. */
function menu_upload_product_images(PDO $pdo, int $productId): array
{
    $uploaded = 0;
    $errors = [];
    if (empty($_FILES['images']['name'][0])) {
        return [$uploaded, $errors];
    }
    $coverStmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_cover = 1');
    $coverStmt->execute([$productId]);
    $hasCover = (int) $coverStmt->fetchColumn() > 0;
    $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?');
    $ins = $pdo->prepare('INSERT INTO product_images (product_id, filename, sort_order, is_cover) VALUES (?,?,?,?)');
    $count = count($_FILES['images']['name']);
    for ($i = 0; $i < $count && $i < 20; $i++) {
        $file = [
            'name'     => $_FILES['images']['name'][$i],
            'type'     => $_FILES['images']['type'][$i],
            'tmp_name' => $_FILES['images']['tmp_name'][$i],
            'error'    => $_FILES['images']['error'][$i],
            'size'     => $_FILES['images']['size'][$i],
        ];
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        try {
            $fn = upload_image($file, 'products');
            $sortStmt->execute([$productId]);
            $ins->execute([$productId, $fn, (int) $sortStmt->fetchColumn(), $hasCover ? 0 : 1]);
            $hasCover = true;
            $uploaded++;
        } catch (RuntimeException $re) {
            $errors[] = (string) $file['name'] . ': ' . $re->getMessage();
        }
    }
    return [$uploaded, $errors];
}

/** Urunu kaydeder; urunun (yeni) kategori id'sini dondurur. */
function menu_save_product(PDO $pdo, int $id): int
{
    $name = mb_substr(post('name'), 0, 120);
    $catId = (int) post('category_id', '0');
    $desc = mb_substr(post('description'), 0, 1000);
    $details = mb_substr(post('details'), 0, 3000);
    $nameEn = mb_substr(post('name_en'), 0, 120);
    $descEn = mb_substr(post('description_en'), 0, 1000);
    $detailsEn = mb_substr(post('details_en'), 0, 3000);
    $priceRaw = str_replace(',', '.', post('price'));
    $featured = isset($_POST['is_featured']) ? 1 : 0;
    $active = isset($_POST['is_active']) ? 1 : 0;
    $soldOut = isset($_POST['is_sold_out']) ? 1 : 0;
    $dietIn = isset($_POST['diet']) && is_array($_POST['diet']) ? $_POST['diet'] : [];
    $diet = implode(',', array_values(array_filter(array_keys(diet_options()), fn($k) => in_array($k, $dietIn, true))));

    if ($name === '') {
        throw new RuntimeException('Ürün adı boş olamaz.');
    }
    $catExists = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
    $catExists->execute([$catId]);
    if ((int) $catExists->fetchColumn() === 0) {
        throw new RuntimeException('Geçerli bir kategori seçin.');
    }
    if ($priceRaw !== '' && !is_numeric($priceRaw)) {
        throw new RuntimeException('Fiyat yalnızca rakamlardan oluşmalı (örn. 250 veya 12,50).');
    }
    if ($priceRaw !== '' && ((float) $priceRaw < 0 || (float) $priceRaw > 1000000)) {
        throw new RuntimeException('Fiyat 0 ile 1.000.000 arasında olmalı.');
    }
    $price = $priceRaw === '' ? null : round((float) $priceRaw, 2);
    $variantsJson = menu_variants_from_post();
    $caloriesRaw = post('calories');
    $prepRaw = post('prep_minutes');
    if (($caloriesRaw !== '' && (!ctype_digit($caloriesRaw) || (int) $caloriesRaw > 10000)) || ($prepRaw !== '' && (!ctype_digit($prepRaw) || (int) $prepRaw > 600))) {
        throw new RuntimeException('Kalori ve hazırlama süresi sadece rakam olmalı.');
    }
    $calories = $caloriesRaw === '' ? null : (int) $caloriesRaw;
    $prep = $prepRaw === '' ? null : (int) $prepRaw;

    if ($id > 0) {
        $pdo->prepare('UPDATE products SET category_id=?, name=?, description=?, details=?, name_en=?, description_en=?, details_en=?, price=?, variants=?, calories=?, prep_minutes=?, diet=?, is_featured=?, is_active=?, is_sold_out=? WHERE id=?')
            ->execute([$catId, $name, $desc, $details, $nameEn, $descEn, $detailsEn, $price, $variantsJson, $calories, $prep, $diet, $featured, $active, $soldOut, $id]);
    } else {
        $next = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE category_id = ?');
        $next->execute([$catId]);
        $pdo->prepare('INSERT INTO products (category_id, name, description, details, name_en, description_en, details_en, price, variants, calories, prep_minutes, diet, is_featured, is_active, is_sold_out, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$catId, $name, $desc, $details, $nameEn, $descEn, $detailsEn, $price, $variantsJson, $calories, $prep, $diet, $featured, $active, $soldOut, (int) $next->fetchColumn()]);
        $newId = (int) $pdo->lastInsertId();
    }
    [$uploaded, $errors] = menu_upload_product_images($pdo, $id > 0 ? $id : $newId);
    flash('success', ($id > 0 ? 'Ürün güncellendi.' : 'Ürün eklendi.') . ($uploaded ? " $uploaded resim yüklendi." : ''));
    foreach ($errors as $error) {
        flash('error', $error);
    }
    return $catId;
}

/** Urun resmi islemleri. $op: "cover:12", "delete:12", "up:12", "down:12" */
function menu_product_image_op(PDO $pdo, int $productId, string $op): void
{
    [$verb, $imgId] = array_pad(explode(':', $op, 2), 2, '0');
    $imgId = (int) $imgId;
    $s = $pdo->prepare('SELECT * FROM product_images WHERE id = ? AND product_id = ?');
    $s->execute([$imgId, $productId]);
    $img = $s->fetch();
    if (!$img) {
        return;
    }
    if ($verb === 'delete') {
        delete_upload('products', (string) $img['filename']);
        $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imgId]);
        if ((int) $img['is_cover'] === 1) {
            $pdo->prepare('UPDATE product_images SET is_cover = 1 WHERE id = (SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1)')->execute([$productId]);
        }
        flash('success', 'Resim silindi.');
    } elseif ($verb === 'cover') {
        $pdo->prepare('UPDATE product_images SET is_cover = 0 WHERE product_id = ?')->execute([$productId]);
        $pdo->prepare('UPDATE product_images SET is_cover = 1 WHERE id = ?')->execute([$imgId]);
        flash('success', 'Kapak resmi güncellendi.');
    } elseif ($verb === 'up' || $verb === 'down') {
        $rows = $pdo->prepare('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
        $rows->execute([$productId]);
        menu_reorder($pdo, 'product_images', $rows->fetchAll(PDO::FETCH_COLUMN), $imgId, $verb);
    }
}

function menu_delete_product(PDO $pdo, int $id): void
{
    $imgs = $pdo->prepare('SELECT filename FROM product_images WHERE product_id = ?');
    $imgs->execute([$id]);
    foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $f) {
        delete_upload('products', (string) $f);
    }
    $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    flash('success', 'Ürün silindi.');
}

/** POST istegini isler ve her durumda menu sayfasina yonlendirir. */
function menu_handle_post(PDO $pdo): void
{
    $action = post('action');
    $id = (int) post('id', '0');
    $backCat = (int) post('back_cat', '0');
    $backQ = mb_substr(post('back_q'), 0, 60);
    $back = menu_path($backCat, ['q' => $backQ]);
    $toggles = [
        'cat_toggle'   => 'UPDATE categories SET is_active = 1 - is_active WHERE id = ?',
        'prod_toggle'  => 'UPDATE products SET is_active = 1 - is_active WHERE id = ?',
        'prod_soldout' => 'UPDATE products SET is_sold_out = 1 - is_sold_out WHERE id = ?',
        'prod_feature' => 'UPDATE products SET is_featured = 1 - is_featured WHERE id = ?',
    ];
    try {
        if ($action === 'cat_save') {
            menu_save_category($pdo, $id);
        } elseif ($action === 'prod_save') {
            $back = menu_path(menu_save_product($pdo, $id), ['q' => $backQ]);
        } elseif ($action === 'cat_order' || $action === 'prod_order') {
            // Surukle-birak arka planda (fetch ile) gonderilir: yonlendirme yerine bos yanit doner
            if ($action === 'cat_order') {
                menu_save_order($pdo, 'categories', post('ids'));
            } else {
                menu_save_order($pdo, 'products', post('ids'), $backCat);
            }
            http_response_code(204);
            exit;
        } elseif ($id <= 0) {
            // asagidaki islemlerin hepsi mevcut bir kayit ister
        } elseif (isset($toggles[$action])) {
            $pdo->prepare($toggles[$action])->execute([$id]);
        } elseif ($action === 'cat_delete') {
            menu_delete_category($pdo, $id);
            $back = menu_path();
        } elseif ($action === 'prod_delete') {
            menu_delete_product($pdo, $id);
        } elseif ($action === 'prod_image') {
            menu_product_image_op($pdo, $id, post('op'));
            $back = menu_path($backCat, ['q' => $backQ, 'urun' => $id]);   // resim islemlerinden sonra popup acik kalir
        }
    } catch (Throwable $t) {
        if ($action === 'cat_order' || $action === 'prod_order') {
            error_log('Siralama hatasi: ' . $t->getMessage());
            http_response_code(500);
            exit;
        }
        flash_exception($t);
        // Hata olunca ilgili popup, yazilanlar korunarak yeniden acilir
        if ($action === 'cat_save') {
            remember_form('cat');
            $back = menu_path($backCat, $id > 0 ? ['kat_duzenle' => $id] : ['yeni' => 'kategori']);
        } elseif ($action === 'prod_save') {
            remember_form('prod');
            $back = menu_path($backCat, ['q' => $backQ] + ($id > 0 ? ['urun' => $id] : ['yeni' => 'urun']));
        }
    }
    redirect($back);
}
