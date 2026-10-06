<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $id = (int) post('id', '0');
    if ($action === 'read' && $id > 0) {
        $pdo->prepare('UPDATE reviews SET is_read = 1 - is_read WHERE id = ?')->execute([$id]);
    } elseif ($action === 'read_all') {
        $pdo->exec('UPDATE reviews SET is_read = 1');
        flash('success', 'Tüm yorumlar okundu olarak işaretlendi.');
    } elseif ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        flash('success', 'Yorum silindi.');
    } elseif ($action === 'rating_delete' && $id > 0) {
        $pdo->prepare('DELETE FROM product_ratings WHERE id = ?')->execute([$id]);
        flash('success', 'Ürün puanı silindi.');
    }
    redirect('admin/yorumlar.php' . (($f = post('filter')) !== '' ? '?filtre=' . rawurlencode($f) : ''));
}

$filter = (string) ($_GET['filtre'] ?? '');
$where = '1=1';
if ($filter === 'yeni') {
    $where = 'r.is_read = 0';
}
$reviews = $pdo->query("SELECT r.*, p.name AS product_name FROM reviews r LEFT JOIN products p ON p.id = r.product_id WHERE $where ORDER BY r.id DESC LIMIT 300")->fetchAll();
$scores = [];
if ($reviews) {
    // Yalnizca listelenen yorumlarin puanlari okunur (tum tablo degil)
    $ids = array_map('intval', array_column($reviews, 'id'));
    $scoresStmt = $pdo->prepare('SELECT s.review_id, c.name, s.score FROM review_scores s JOIN rating_criteria c ON c.id = s.criteria_id
                                 WHERE s.review_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY c.sort_order, c.id');
    $scoresStmt->execute($ids);
    foreach ($scoresStmt as $row) {
        $scores[(int) $row['review_id']][] = $row;
    }
}
$averages = $pdo->query('SELECT c.name, AVG(s.score) AS avg, COUNT(s.score) AS cnt FROM rating_criteria c
                         LEFT JOIN review_scores s ON s.criteria_id = c.id WHERE c.is_active = 1 GROUP BY c.id ORDER BY c.sort_order, c.id')->fetchAll();
$total = (int) $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
// Urun puanlari (urun sayfasindan verilen tek puanlar)
$ratings = $pdo->query('SELECT pr.*, p.name AS product_name FROM product_ratings pr JOIN products p ON p.id = pr.product_id ORDER BY pr.id DESC LIMIT 200')->fetchAll();
$ratingTotal = (int) $pdo->query('SELECT COUNT(*) FROM product_ratings')->fetchColumn();

$pageTitle = 'Yorumlar';
$activeMenu = 'yorumlar';
require __DIR__ . '/../inc/admin_top.php';
?>
<div class="page-head">
    <h1>Restoran Yorumları <span class="muted" style="font-size:15px;font-weight:400">(<?= $total ?>)</span></h1>
    <form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn" type="submit">Tümünü okundu işaretle</button></form>
</div>

<?php if ($averages): ?>
<div class="card">
    <h2>Restoran Ortalamaları</h2>
    <div class="grid-2">
        <?php foreach ($averages as $a): $avg = $a['avg'] !== null ? (float) $a['avg'] : 0.0; ?>
            <div class="avg-bar">
                <span class="lbl"><?= e($a['name']) ?></span>
                <div class="track"><div class="fill" style="width:<?= (int) round($avg / 5 * 100) ?>%"></div></div>
                <span class="val"><?= $a['avg'] !== null ? number_format($avg, 1, ',', '') : '–' ?></span>
                <span class="muted" style="font-size:12px;width:60px">(<?= (int) $a['cnt'] ?> oy)</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="filters">
    <?php foreach (['' => 'Tümü', 'yeni' => 'Okunmamış'] as $k => $label): ?>
        <a class="btn btn-sm <?= $filter === $k ? 'btn-primary' : '' ?>" href="<?= e(url('admin/yorumlar.php' . ($k !== '' ? '?filtre=' . $k : ''))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$reviews): ?><div class="card"><div class="empty">Bu filtrede yorum yok.</div></div><?php endif; ?>
<?php foreach ($reviews as $r): $rid = (int) $r['id']; ?>
    <div class="review <?= (int) $r['is_read'] ? '' : 'unread' ?>">
        <div class="head">
            <div>
                <span class="who"><?= e($r['name'] !== '' ? $r['name'] : 'Anonim') ?></span>
                <?php if (!(int) $r['is_read']): ?> <span class="status new">Yeni</span><?php endif; ?>
            </div>
            <span class="when"><?= e(date('d.m.Y H:i', (int) strtotime($r['created_at']))) ?></span>
        </div>
        <?php if (!empty($scores[$rid])): ?>
            <div class="scores">
                <?php foreach ($scores[$rid] as $s): ?>
                    <span><?= e($s['name']) ?>: <?= stars_html((float) $s['score']) ?> <b><?= (int) $s['score'] ?></b></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="text"><?= e($r['comment']) ?></div>
        <div class="foot">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= $rid ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button class="btn btn-sm" type="submit"><?= (int) $r['is_read'] ? 'Okunmadı işaretle' : 'Okundu işaretle' ?></button></form>
            <form method="post" data-confirm="Yorum kalıcı olarak silinecek. Emin misiniz?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $rid ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button class="btn btn-sm btn-danger" type="submit">Sil</button></form>
        </div>
    </div>
<?php endforeach; ?>

<div class="page-head" style="margin-top:34px">
    <h1>Ürün Puanları <span class="muted" style="font-size:15px;font-weight:400">(<?= $ratingTotal ?>)</span></h1>
</div>
<?php if (!$ratings): ?><div class="card"><div class="empty">Henüz ürün puanı yok. Müşteriler ürün sayfasının altından puan verir.</div></div><?php endif; ?>
<?php foreach ($ratings as $pr): ?>
    <div class="review">
        <div class="head">
            <div>
                <span class="who"><?= e($pr['name'] !== '' ? $pr['name'] : 'Anonim') ?></span>
                <span class="prod"><?= e($pr['product_name']) ?></span>
                <?= stars_html((float) $pr['score']) ?> <b><?= (int) $pr['score'] ?></b>
            </div>
            <span class="when"><?= e(date('d.m.Y H:i', (int) strtotime($pr['created_at']))) ?></span>
        </div>
        <?php if ($pr['comment'] !== ''): ?><div class="text"><?= e($pr['comment']) ?></div><?php endif; ?>
        <div class="foot">
            <form method="post" data-confirm="Puan kalıcı olarak silinecek. Emin misiniz?"><?= csrf_field() ?><input type="hidden" name="action" value="rating_delete"><input type="hidden" name="id" value="<?= (int) $pr['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Sil</button></form>
        </div>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/../inc/admin_bottom.php'; ?>
