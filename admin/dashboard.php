<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();
$stats = [
    'Kategori'  => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'Ürün'      => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'Resim'     => (int) $pdo->query('SELECT COUNT(*) FROM product_images')->fetchColumn(),
    'Yorum'     => (int) $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
    'Okunmamış' => (int) $pdo->query('SELECT COUNT(*) FROM reviews WHERE is_read = 0')->fetchColumn(),
];
$averages = $pdo->query('SELECT c.name, AVG(s.score) AS avg, COUNT(s.score) AS cnt FROM rating_criteria c
                         LEFT JOIN review_scores s ON s.criteria_id = c.id
                         WHERE c.is_active = 1 GROUP BY c.id ORDER BY c.sort_order, c.id')->fetchAll();
$recent = $pdo->query('SELECT r.*, p.name AS product_name FROM reviews r LEFT JOIN products p ON p.id = r.product_id
                       ORDER BY r.id DESC LIMIT 5')->fetchAll();
$topProducts = $pdo->query('SELECT p.name, AVG(pr.score) AS avg, COUNT(*) AS cnt FROM products p
                            JOIN product_ratings pr ON pr.product_id = p.id
                            GROUP BY p.id ORDER BY avg DESC, cnt DESC LIMIT 5')->fetchAll();

$pageTitle = 'Genel Bakış';
$activeMenu = 'dashboard';
require __DIR__ . '/../inc/admin_top.php';
?>
<div class="page-head">
    <h1>Genel Bakış</h1>
    <div class="actions">
        <a class="btn btn-accent" href="<?= e(url('admin/menu.php?yeni=urun')) ?>">+ Yeni Ürün</a>
        <a class="btn" href="<?= e(url('admin/menu.php?yeni=kategori')) ?>">+ Yeni Kategori</a>
    </div>
</div>

<div class="stats">
    <?php foreach ($stats as $label => $num): ?>
        <div class="stat"><div class="num"><?= $num ?></div><div class="lbl"><?= e($label) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="grid-2">
    <div class="card">
        <h2>Kriter Ortalamaları</h2>
        <?php if (!$averages): ?><div class="empty">Kriter tanımlı değil.</div><?php endif; ?>
        <?php foreach ($averages as $a): $avg = $a['avg'] !== null ? (float) $a['avg'] : 0.0; ?>
            <div class="avg-bar">
                <span class="lbl"><?= e($a['name']) ?></span>
                <div class="track"><div class="fill" style="width:<?= (int) round($avg / 5 * 100) ?>%"></div></div>
                <span class="val"><?= $a['avg'] !== null ? number_format($avg, 1, ',', '') : '–' ?></span>
            </div>
        <?php endforeach; ?>
        <div class="muted" style="font-size:12px;margin-top:8px">5 üzerinden ortalama puan.</div>
    </div>
    <div class="card">
        <h2>En Beğenilen Ürünler</h2>
        <?php if (!$topProducts): ?><div class="empty">Henüz ürün puanı yok. Müşteriler ürün sayfasının altından puan verir.</div><?php else: ?>
            <table>
                <?php foreach ($topProducts as $tp): ?>
                    <tr><td><?= e($tp['name']) ?></td><td style="text-align:right"><?= stars_html(round((float) $tp['avg'], 1)) ?> <b><?= number_format((float) $tp['avg'], 1, ',', '') ?></b> <span class="muted">(<?= (int) $tp['cnt'] ?>)</span></td></tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Son Yorumlar</h2>
    <?php if (!$recent): ?><div class="empty">Henüz yorum yok.</div><?php else: ?>
        <table>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td style="width:130px" class="muted"><?= e(date('d.m.Y H:i', (int) strtotime($r['created_at']))) ?></td>
                    <td><b><?= e($r['name'] !== '' ? $r['name'] : 'Anonim') ?></b><br><span class="muted"><?= e(mb_strimwidth($r['comment'], 0, 120, '…')) ?></span></td>
                    <td style="text-align:right"><?php if (!(int) $r['is_read']): ?><span class="status new">Yeni</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <div style="margin-top:12px"><a class="btn btn-sm" href="<?= e(url('admin/yorumlar.php')) ?>">Tüm yorumlar →</a></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../inc/admin_bottom.php'; ?>
