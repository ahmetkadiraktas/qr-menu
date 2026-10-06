<?php
/**
 * Public sayfa sablonu (ust kisim).
 * Beklenen degiskenler: $pageTitle (string), $backUrl (string|null), $activeNav (string)
 */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/info.php';
require_once __DIR__ . '/stats.php';
require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/themes.php';
require_installed();
track_visit();

$pageTitle = $pageTitle ?? '';
$backUrl   = $backUrl ?? null;
$activeNav = $activeNav ?? 'menu';
$siteName  = setting('site_name', 'Menü');
$logo      = setting('logo');
$bgImage   = setting('background');
$theme       = current_theme();
$themeMeta   = theme_meta($theme);
$themeFonts  = theme_fonts_url($theme);
$themeStyle  = theme_style_attr($theme);
$themeCssVer = (string) @filemtime(ROOT_PATH . '/assets/css/themes/' . $theme . '.css');   // tema dosyasi degisince onbellek yenilenir
if (!themes()[$theme]['background'] || !preg_match('/^[a-f0-9]+\.(jpg|png)$/', $bgImage)) {
    $bgImage = '';   // dosya adi CSS url() icine yazilir; yalnizca upload_image() bicimindeki adlar kabul edilir
}
?>
<!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= e($themeMeta['theme_color']) ?>">
<title><?= e($pageTitle !== '' ? $pageTitle . ' · ' . $siteName : $siteName) ?></title>
<?php if ($themeFonts !== ''): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= e($themeFonts) ?>" rel="stylesheet">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(url('assets/css/menu-base.css')) ?>?v=2">
<link rel="stylesheet" href="<?= e(url('assets/css/themes/' . $theme . '.css')) ?>?v=<?= e($themeCssVer) ?>">
<?php if ($logo !== ''): ?><link rel="icon" href="<?= e(upload_url('site', $logo, true)) ?>"><?php endif; ?>
</head>
<body class="m t-<?= e($theme) ?>"<?= $themeStyle !== '' ? ' style="' . e($themeStyle) . '"' : '' ?>>
<div class="bg-photo<?= $bgImage !== '' ? ' has-image' : '' ?>"<?= $bgImage !== '' ? ' style="background-image:url(' . e(upload_url('site', $bgImage)) . ')"' : '' ?>></div>
<div class="bg-overlay"></div>

<header class="topbar">
    <div class="left">
        <?php if ($backUrl !== null): ?>
            <a class="icon-btn" href="<?= e($backUrl) ?>" aria-label="<?= e(t('Geri')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
        <?php endif; ?>
    </div>
    <a class="brand" href="<?= e(url('')) ?>">
        <?php if ($logo !== ''): ?>
            <img src="<?= e(upload_url('site', $logo, true)) ?>" alt="<?= e($siteName) ?>">
        <?php else: ?>
            <span><?= e($siteName) ?></span>
        <?php endif; ?>
    </a>
    <div class="right">
        <a class="icon-btn lang-btn" href="<?= e(lang_switch_url()) ?>" hreflang="<?= current_lang() === 'en' ? 'tr' : 'en' ?>" aria-label="<?= current_lang() === 'en' ? 'Türkçe' : 'English' ?>" rel="nofollow"><?= current_lang() === 'en' ? 'TR' : 'EN' ?></a>
        <?php if (has_legal_info()): ?>
            <a class="icon-btn" href="<?= e(url('yasal.php')) ?>" aria-label="<?= e(t('Bilgilendirme')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.01"/></svg>
            </a>
        <?php endif; ?>
    </div>
</header>

<?php $bandCampaigns = active_campaigns('band'); ?>
<?php if ($bandCampaigns): ?>
<div class="promo-band" id="promoBand" data-key="<?= e(implode('-', array_column($bandCampaigns, 'id'))) ?>">
    <div class="promo-band-items">
        <?php foreach ($bandCampaigns as $i => $bc): ?>
            <p class="promo-band-item<?= $i === 0 ? ' active' : '' ?>"><b><?= e($bc['title']) ?></b><?php if (trim((string) $bc['body']) !== ''): ?> <span><?= e((string) preg_replace('/\s+/u', ' ', trim((string) $bc['body']))) ?></span><?php endif; ?></p>
        <?php endforeach; ?>
    </div>
    <button class="promo-band-close" type="button" id="promoBandClose" aria-label="<?= e(t('Kapat')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
</div>
<?php /* Bu ziyarette kapatildiysa sayfa cizilmeden gizlenir (goz kirpmasin) */ ?>
<script>try{var b=document.getElementById('promoBand');if(sessionStorage.getItem('bandClosed')===b.getAttribute('data-key'))b.hidden=true}catch(e){}</script>
<?php endif; ?>

<main class="container page">
<?= flash_render() ?>
