<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
require_admin();
$pdo = db();

$days = (int) ($_GET['gun'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) {
    $days = 30;
}
$today = date('Y-m-d');
$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

// Gunluk ziyaretci: verisi olmayan gunler 0 olarak doldurulur
$stmt = $pdo->prepare('SELECT day, visitors, pageviews FROM stats_daily WHERE day >= ? AND day <= ?');
$stmt->execute([$from, $today]);
$byDay = [];
foreach ($stmt as $row) {
    $byDay[$row['day']] = $row;
}
$series = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime('-' . $i . ' days'));
    $series[$d] = ['visitors' => (int) ($byDay[$d]['visitors'] ?? 0), 'pageviews' => (int) ($byDay[$d]['pageviews'] ?? 0)];
}
$totalVisitors = array_sum(array_column($series, 'visitors'));
$totalViews = array_sum(array_column($series, 'pageviews'));
$maxVisitors = max(1, max(array_column($series, 'visitors')));

$top = function (string $type, string $table, string $nameSql) use ($pdo, $from): array {
    $stmt = $pdo->prepare("SELECT $nameSql AS name, SUM(v.views) AS total FROM stats_views v JOIN $table t ON t.id = v.item_id
                           WHERE v.type = ? AND v.day >= ? GROUP BY t.id ORDER BY total DESC, t.id LIMIT 10");
    $stmt->execute([$type, $from]);
    return $stmt->fetchAll();
};
$topProducts = $top('product', 'products', 't.name');
$topCategories = $top('category', 'categories', 't.name');

$hasData = $totalViews > 0 || $topProducts || $topCategories;
$stats = [
    'Bugünkü ziyaretçi'           => number_format($series[$today]['visitors'], 0, ',', '.'),
    'Ziyaretçi (son ' . $days . ' gün)' => number_format($totalVisitors, 0, ',', '.'),
    'Günlük ortalama'             => number_format($totalVisitors / $days, 1, ',', '.'),
    'Sayfa görüntüleme'           => number_format($totalViews, 0, ',', '.'),
];
$shortDate = fn(string $d): string => date('d.m', (int) strtotime($d));

$pageTitle = 'İstatistik';
$activeMenu = 'istatistik';
require __DIR__ . '/../inc/admin_top.php';
?>
<div class="page-head">
    <h1>İstatistik</h1>
    <div class="actions">
        <?php foreach ([7, 30, 90] as $opt): ?>
            <a class="btn<?= $opt === $days ? ' btn-primary' : '' ?>" href="<?= e(url('admin/istatistik.php?gun=' . $opt)) ?>"<?= $opt === $days ? ' aria-current="true"' : '' ?>>Son <?= $opt ?> gün</a>
        <?php endforeach; ?>
    </div>
</div>

<div class="stats">
    <?php foreach ($stats as $label => $num): ?>
        <div class="stat"><div class="num"><?= e($num) ?></div><div class="lbl"><?= e($label) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Günlük Ziyaretçi</h2>
    <?php if (!$hasData): ?>
        <div class="empty">Henüz veri yok. Müşteriler menüyü açtıkça burada birikir.</div>
    <?php else: ?>
        <div class="day-chart<?= $days > 31 ? ' dense' : '' ?>" role="img" aria-label="Son <?= $days ?> günün günlük ziyaretçi sayıları. En yüksek gün: <?= $maxVisitors ?>.">
            <div class="day-max"><?= $maxVisitors ?></div>
            <div class="day-bars">
                <?php foreach ($series as $d => $s): ?>
                    <div class="day-col" data-tip="<?= e(date('d.m.Y', (int) strtotime($d)) . ' · ' . $s['visitors'] . ' ziyaretçi') ?>">
                        <i class="<?= $s['visitors'] === 0 ? 'zero' : '' ?>" style="height:<?= $s['visitors'] === 0 ? 0 : max(2, round($s['visitors'] / $maxVisitors * 100, 1)) ?>%"></i>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="day-axis"><span><?= e($shortDate($from)) ?></span><span><?= e($shortDate(date('Y-m-d', strtotime('-' . intdiv($days - 1, 2) . ' days')))) ?></span><span>Bugün</span></div>
        </div>
        <details class="day-table">
            <summary>Tablo olarak göster</summary>
            <div class="table-wrap"><table>
                <thead><tr><th>Tarih</th><th style="text-align:right">Ziyaretçi</th><th style="text-align:right">Sayfa görüntüleme</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($series, true) as $d => $s): ?>
                    <tr><td><?= e(date('d.m.Y', (int) strtotime($d))) ?></td><td style="text-align:right"><?= $s['visitors'] ?></td><td style="text-align:right"><?= $s['pageviews'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </details>
    <?php endif; ?>
    <div class="muted" style="font-size:12px;margin-top:10px">Bir kişi aynı gün kaç sayfa gezerse gezsin 1 ziyaretçi sayılır. Admin girişi yapılmış tarayıcı ve botlar sayılmaz.</div>
</div>

<div class="grid-2">
    <?php foreach (['En Çok Tıklanan Ürünler' => $topProducts, 'En Çok Tıklanan Kategoriler' => $topCategories] as $title => $rows): ?>
    <div class="card">
        <h2><?= e($title) ?> <span class="muted" style="font-weight:400">· son <?= $days ?> gün</span></h2>
        <?php if (!$rows): ?><div class="empty">Henüz veri yok.</div><?php else: $rowMax = max(1, (int) $rows[0]['total']); ?>
            <?php foreach ($rows as $i => $r): ?>
                <div class="avg-bar top-bar">
                    <span class="lbl" title="<?= e($r['name']) ?>"><?= $i + 1 ?>. <?= e($r['name']) ?></span>
                    <div class="track"><div class="fill" style="width:<?= max(1, (int) round((int) $r['total'] / $rowMax * 100)) ?>%"></div></div>
                    <span class="val"><?= number_format((int) $r['total'], 0, ',', '.') ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../inc/admin_bottom.php'; ?>
