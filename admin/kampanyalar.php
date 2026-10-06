<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/menu_actions.php';   // menu_save_order(), menu_action_form()
require_admin();
$pdo = db();

const CAMPAIGN_DISPLAYS = ['popup' => 'Popup', 'band' => 'Şerit bant'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $id = (int) post('id', '0');
    $back = 'admin/kampanyalar.php';
    try {
        if ($action === 'order') {
            // Surukle-birak arka planda gonderilir: yonlendirme yerine bos yanit doner
            menu_save_order($pdo, 'campaigns', post('ids'));
            http_response_code(204);
            exit;
        }
        if ($action === 'save') {
            $title = mb_substr(post('title'), 0, 120);
            $body = mb_substr(post('body'), 0, 1000);
            $titleEn = mb_substr(post('title_en'), 0, 120);
            $bodyEn = mb_substr(post('body_en'), 0, 1000);
            $display = post('display', 'popup');
            $endsOn = post('ends_on');
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($title === '') {
                throw new RuntimeException('Kampanya başlığı boş olamaz.');
            }
            if (!isset(CAMPAIGN_DISPLAYS[$display])) {
                throw new RuntimeException('Geçerli bir gösterim şekli seçin.');
            }
            if ($endsOn !== '') {
                $d = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $endsOn, $m) ? $m : null;
                if (!$d || !checkdate((int) $d[2], (int) $d[3], (int) $d[1])) {
                    throw new RuntimeException('Bitiş tarihi geçersiz.');
                }
            }
            $image = '';
            if (!empty($_FILES['image']['name'])) {
                $image = upload_image($_FILES['image'], 'campaigns');
            }
            if ($id > 0) {
                $old = $pdo->prepare('SELECT image FROM campaigns WHERE id = ?');
                $old->execute([$id]);
                $oldImage = (string) $old->fetchColumn();
                if ($image === '') {
                    $image = isset($_POST['remove_image']) ? '' : $oldImage;
                }
                if ($oldImage !== '' && $oldImage !== $image) {
                    delete_upload('campaigns', $oldImage);
                }
                $pdo->prepare('UPDATE campaigns SET title=?, body=?, title_en=?, body_en=?, display=?, image=?, ends_on=?, is_active=? WHERE id=?')
                    ->execute([$title, $body, $titleEn, $bodyEn, $display, $image, $endsOn, $active, $id]);
                flash('success', 'Kampanya güncellendi.');
            } else {
                $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM campaigns')->fetchColumn();
                $pdo->prepare('INSERT INTO campaigns (title, body, title_en, body_en, display, image, ends_on, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$title, $body, $titleEn, $bodyEn, $display, $image, $endsOn, $active, $next]);
                flash('success', 'Kampanya eklendi.');
            }
        } elseif ($action === 'delete' && $id > 0) {
            $old = $pdo->prepare('SELECT image FROM campaigns WHERE id = ?');
            $old->execute([$id]);
            delete_upload('campaigns', (string) $old->fetchColumn());
            $pdo->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$id]);
            flash('success', 'Kampanya silindi.');
        } elseif ($action === 'toggle' && $id > 0) {
            $pdo->prepare('UPDATE campaigns SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        }
    } catch (Throwable $t) {
        if ($action === 'order') {
            error_log('Kampanya siralama hatasi: ' . $t->getMessage());
            http_response_code(500);
            exit;
        }
        flash_exception($t);
        if ($action === 'save') {
            remember_form('camp');
            $back .= $id > 0 ? '?duzenle=' . $id : '?yeni=1';   // popup hata mesajiyla ve yazilanlar korunarak yeniden acilir
        }
    }
    redirect($back);
}

$campaigns = $pdo->query('SELECT * FROM campaigns ORDER BY sort_order, id')->fetchAll();
$edit = array_column($campaigns, null, 'id')[(int) ($_GET['duzenle'] ?? 0)] ?? null;
$modalOpen = $edit !== null || isset($_GET['yeni']);
$isEdit = $edit !== null;
if ($modalOpen && ($old = recall_form('camp')) !== null) {
    $edit = array_merge($edit ?? ['id' => 0, 'image' => ''], [
        'title' => (string) ($old['title'] ?? ''), 'body' => (string) ($old['body'] ?? ''), 'title_en' => (string) ($old['title_en'] ?? ''), 'body_en' => (string) ($old['body_en'] ?? ''),
        'display' => (string) ($old['display'] ?? 'popup'), 'ends_on' => (string) ($old['ends_on'] ?? ''), 'is_active' => isset($old['is_active']) ? 1 : 0,
    ]);
}
$hasEn = $edit && trim($edit['title_en'] . $edit['body_en']) !== '';
$closeHref = url('admin/kampanyalar.php');
$today = date('Y-m-d');

$pageFlash = flash_render();   // popup aciksa mesajlar popup'in icinde gosterilir
$pageTitle = 'Kampanyalar';
$activeMenu = 'kampanyalar';
require __DIR__ . '/../inc/admin_top.php';
?>
<?php if (!$modalOpen) { echo $pageFlash; } ?>
<div class="page-head">
    <h1>Kampanyalar</h1>
    <div class="actions"><a class="btn btn-accent" href="<?= e(url('admin/kampanyalar.php?yeni=1')) ?>" data-modal-open="campModal">+ Kampanya ekle</a></div>
</div>

<div class="card">
    <p class="muted" style="margin:0 0 14px;font-size:13px"><b>Popup</b> kampanyalar müşteri menüyü açtığında ortada pencere olarak çıkar. <b>Şerit bant</b> kampanyalar her sayfanın üstünde ince bir bant olarak görünür.</p>
    <?php if (!$campaigns): ?>
        <div class="empty">Henüz kampanya yok. "+ Kampanya ekle" ile başlayın.</div>
    <?php else: ?>
        <?php if (count($campaigns) > 1): ?><p class="muted" style="margin:0 0 10px;font-size:12px" data-sort-status>Sıralamak için <span aria-hidden="true">⠿</span> işaretinden tutup sürükleyin.</p><?php endif; ?>
        <div class="camp-list" data-sort="order">
            <?php foreach ($campaigns as $c):
                $cid = (int) $c['id'];
                $ended = !(int) $c['is_active'];
                $expired = $c['ends_on'] !== '' && $c['ends_on'] < $today;
            ?>
                <div class="camp-row<?= $ended || $expired ? ' is-off' : '' ?>" data-id="<?= $cid ?>" data-group="0">
                    <?php if (count($campaigns) > 1): ?><span class="drag-handle" title="Sürükleyerek sırala" aria-hidden="true">⠿</span><?php endif; ?>
                    <?php if ($c['image'] !== ''): ?><img class="thumb-sm" src="<?= e(upload_url('campaigns', $c['image'], true)) ?>" alt=""><?php else: ?><div class="thumb-ph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16"/><path d="M3 16l5-5 4 4 3-3 6 6"/></svg></div><?php endif; ?>
                    <div class="camp-info">
                        <b><?= e($c['title']) ?></b>
                        <?php if ($c['body'] !== ''): ?><div class="muted camp-body"><?= e(mb_strimwidth($c['body'], 0, 110, '…')) ?></div><?php endif; ?>
                        <div class="camp-meta">
                            <span class="status type"><?= e(CAMPAIGN_DISPLAYS[$c['display']] ?? 'Popup') ?></span>
                            <?php if ($ended): ?><span class="status off">Bitti</span>
                            <?php elseif ($expired): ?><span class="status new">Süresi doldu</span>
                            <?php else: ?><span class="status on">Yayında</span><?php endif; ?>
                            <?php if ($c['ends_on'] !== ''): ?><span class="muted">Bitiş: <?= e(date('d.m.Y', (int) strtotime($c['ends_on']))) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <details class="kebab">
                        <summary aria-label="<?= e($c['title']) ?> işlemleri">⋮</summary>
                        <div class="kebab-menu">
                            <a href="<?= e(url('admin/kampanyalar.php?duzenle=' . $cid)) ?>">Düzenle</a>
                            <?= menu_action_form('toggle', $cid, $ended ? 'Yeniden yayınla' : 'Bitir', 0, '') ?>
                            <?= menu_action_form('delete', $cid, 'Sil', 0, '', [], "'" . $c['title'] . "' kampanyası silinecek.") ?>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Kampanya popup'i -->
<div class="modal" id="campModal"<?= $modalOpen ? ' data-close-href="' . e($closeHref) . '"' : ' hidden' ?>>
    <div class="modal-box<?= $hasEn ? ' with-en' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="campModalTitle">
        <div class="modal-head">
            <h2 id="campModalTitle"><?= $isEdit ? 'Kampanyayı Düzenle' : 'Yeni Kampanya' ?></h2>
            <button type="button" class="btn btn-sm en-toggle" data-en-toggle aria-pressed="<?= $hasEn ? 'true' : 'false' ?>" data-label-on="İngilizceyi gizle" data-label-off="İngilizce ekle"><?= $hasEn ? 'İngilizceyi gizle' : 'İngilizce ekle' ?></button>
            <?php if ($modalOpen): ?><a class="modal-close" href="<?= e($closeHref) ?>" aria-label="Kapat">✕</a>
            <?php else: ?><button type="button" class="modal-close" data-modal-close aria-label="Kapat">✕</button><?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="modal-body">
            <?php if ($modalOpen) { echo $pageFlash; } ?>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $isEdit ? (int) $edit['id'] : 0 ?>">
            <div class="form-row">
                <label>Gösterim şekli</label>
                <div class="inline">
                    <?php foreach (CAMPAIGN_DISPLAYS as $value => $label): ?>
                        <label class="check"><input type="radio" name="display" value="<?= e($value) ?>" <?= ($edit['display'] ?? 'popup') === $value ? 'checked' : '' ?>> <?= e($label) ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="hint">Popup: menü açılınca ortada pencere. Şerit bant: her sayfanın üstünde tek satırlık ince bant.</div>
            </div>
            <div class="pair">
                <div class="form-row"><label>Başlık *</label><input type="text" name="title" required maxlength="120" value="<?= e($edit['title'] ?? '') ?>" placeholder="Örn. Hafta içi öğle menüsü %20 indirimli"></div>
                <div class="form-row en"><label>Başlık <span class="muted">(İngilizce)</span></label><input type="text" name="title_en" maxlength="120" value="<?= e($edit['title_en'] ?? '') ?>"></div>
            </div>
            <div class="pair">
                <div class="form-row"><label>Metin</label><textarea name="body" maxlength="1000" style="min-height:90px" placeholder="Kampanyanın ayrıntıları"><?= e($edit['body'] ?? '') ?></textarea></div>
                <div class="form-row en"><label>Metin <span class="muted">(İngilizce)</span></label><textarea name="body_en" maxlength="1000" style="min-height:90px"><?= e($edit['body_en'] ?? '') ?></textarea></div>
            </div>
            <div class="form-row">
                <label>Resim</label>
                <?php if ($edit && $edit['image'] !== ''): ?>
                    <img class="site-img-preview" src="<?= e(upload_url('campaigns', $edit['image'], true)) ?>" alt="">
                    <label class="check" style="margin-bottom:8px"><input type="checkbox" name="remove_image"> Resmi kaldır</label>
                <?php endif; ?>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-preview="campPreview">
                <div class="preview-strip" id="campPreview"></div>
                <div class="hint">Resim yalnızca popup'ta gösterilir; şerit bantta görünmez. JPG, PNG veya WebP · en fazla 8 MB</div>
            </div>
            <div class="form-row">
                <label>Bitiş tarihi <span class="muted">(isteğe bağlı)</span></label>
                <input type="date" name="ends_on" value="<?= e($edit['ends_on'] ?? '') ?>" style="width:auto">
                <div class="hint">Bu tarihten sonra kampanya kendiliğinden gösterilmez. Boş bırakırsanız siz bitirene kadar yayında kalır.</div>
            </div>
            <div class="form-row inline">
                <label class="check"><input type="checkbox" name="is_active" <?= !$edit || (int) $edit['is_active'] ? 'checked' : '' ?>> Yayında</label>
            </div>
            <div class="modal-foot">
                <?php if ($modalOpen): ?><a class="btn" href="<?= e($closeHref) ?>">Vazgeç</a>
                <?php else: ?><button type="button" class="btn" data-modal-close>Vazgeç</button><?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Kaydet' : 'Ekle' ?></button>
            </div>
        </form>
    </div>
</div>
<script src="<?= e(url('assets/js/sortable.min.js')) ?>?v=1"></script>
<?php require __DIR__ . '/../inc/admin_bottom.php'; ?>
