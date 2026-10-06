<?php
/** Admin sablonu (ust). Beklenen: $pageTitle, $activeMenu */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_admin();
$pageTitle = $pageTitle ?? 'Admin';
$activeMenu = $activeMenu ?? '';
$unread = (int) db()->query('SELECT COUNT(*) FROM reviews WHERE is_read = 0')->fetchColumn();
$menu = [
    'dashboard'   => ['dashboard.php',   'Genel Bakış',  '<path d="M3 12l9-8 9 8M5 10v10h14V10"/>'],
    'menu'        => ['menu.php',        'Menü',         '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
    'istatistik'  => ['istatistik.php',  'İstatistik',   '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>'],
    'kampanyalar' => ['kampanyalar.php', 'Kampanyalar',  '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8zM7 7h.01"/>'],
    'yorumlar'    => ['yorumlar.php',    'Yorumlar',     '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1.1-4.4A8 8 0 1 1 21 12z"/>'],
    'qr'          => ['qr.php',          'QR Kod',       '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM21 14v.01M21 21v.01M17 21h.01M21 17.5h-1.5"/>'],
    'ayarlar'     => ['ayarlar.php',     'Ayarlar',      '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>'],
];
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($pageTitle) ?> · Yönetim</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&display=swap" rel="stylesheet">
<?= $extraHead ?? '' ?><link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>?v=13">
</head>
<body>
<div class="topbar-mobile"><button type="button" id="menuToggle" aria-label="Menü"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button> <?= e(setting('site_name')) ?></div>
<div class="admin">
    <aside class="sidebar" id="sidebar">
        <div class="brand"><?= e(setting('site_name')) ?></div>
        <?php foreach ($menu as $key => [$href, $label, $icon]): ?>
            <a href="<?= e(url('admin/' . $href)) ?>" class="<?= $activeMenu === $key ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
                <?= e($label) ?>
                <?php if ($key === 'yorumlar' && $unread > 0): ?><span class="pill"><?= $unread ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <div class="spacer"></div>
        <div class="sub">Site</div>
        <a href="<?= e(url('')) ?>" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg> Menüyü görüntüle</a>
        <a href="<?= e(url('admin/cikis.php?t=' . csrf_token())) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Çıkış</a>
    </aside>
    <div class="content">
        <?= flash_render() ?>
