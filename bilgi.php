<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/info.php';
require_installed();

$status = open_status();
$hourGroups = business_hours_grouped();
$today = (int) date('N');
$siteName = setting('site_name');
$tagline = setting_localized('site_tagline');
$logo = setting('logo');
$phone = setting('phone');
$address = setting('address');
$ig = ltrim(setting('instagram'), '@');
$wifiName = setting('wifi_name');
$wifiPass = setting('wifi_password');
$telHref = tel_href($phone);
$waHref = whatsapp_href(setting('whatsapp'));
$mapsHref = maps_href();

$pageTitle = t('Bilgi');
$backUrl = url('');
$activeNav = 'info';
require __DIR__ . '/inc/layout_top.php';
?>
<div class="info-page">
    <div class="info-top">
        <?php if ($status): ?><div class="open-status <?= $status['open'] ? 'is-open' : 'is-closed' ?>"><i></i><?= e($status['text']) ?></div><?php endif; ?>
        <section class="hero">
            <?php if ($logo !== ''): ?><img class="logo-big" src="<?= e(upload_url('site', $logo)) ?>" alt=""><?php endif; ?>
            <h1><?= e($siteName) ?></h1>
            <?php if ($tagline !== ''): ?><p><?= e($tagline) ?></p><?php endif; ?>
        </section>
    </div>

    <?php if ($telHref !== '' || $mapsHref !== ''): ?>
        <div class="info-actions">
            <?php if ($telHref !== ''): ?>
                <a class="btn btn-primary" href="<?= e($telHref) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
                    <?= current_lang() === 'en' ? 'Call' : 'Ara' ?>
                </a>
            <?php endif; ?>
            <?php if ($mapsHref !== ''): ?>
                <a class="btn" href="<?= e($mapsHref) ?>" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <?= e(t('Yol tarifi')) ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($wifiName !== '' || $wifiPass !== ''): ?>
        <section class="info-section">
            <h2>Wi-Fi</h2>
            <div class="info-panel">
                <?php if ($wifiName !== ''): ?><div class="info-line"><span><?= e(t('Ağ adı')) ?></span><b><?= e($wifiName) ?></b></div><?php endif; ?>
                <?php if ($wifiPass !== ''): ?>
                    <div class="info-line">
                        <span><?= e(t('Şifre')) ?></span>
                        <b class="wifi-pass"><?= e($wifiPass) ?> <button type="button" class="copy-btn" data-copy="<?= e($wifiPass) ?>" data-copied="<?= e(t('Kopyalandı')) ?>" hidden><?= e(t('Kopyala')) ?></button></b>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($address !== '' || $phone !== '' || $waHref !== '' || $ig !== ''): ?>
        <section class="info-section">
            <h2><?= e(t('İletişim')) ?></h2>
            <div class="info-panel">
                <?php if ($address !== ''): ?><div class="info-line"><span><?= e(t('Adres')) ?></span><b><?= e($address) ?></b></div><?php endif; ?>
                <?php if ($phone !== ''): ?><div class="info-line"><span><?= e(t('Telefon')) ?></span><b><?php if ($telHref !== ''): ?><a href="<?= e($telHref) ?>"><?= e($phone) ?></a><?php else: ?><?= e($phone) ?><?php endif; ?></b></div><?php endif; ?>
                <?php if ($waHref !== ''): ?><div class="info-line"><span>WhatsApp</span><b><a href="<?= e($waHref) ?>" target="_blank" rel="noopener"><?= e(t('Mesaj gönder')) ?></a></b></div><?php endif; ?>
                <?php if ($ig !== ''): ?><div class="info-line"><span>Instagram</span><b><a href="https://instagram.com/<?= e($ig) ?>" target="_blank" rel="noopener">@<?= e($ig) ?></a></b></div><?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hourGroups): ?>
        <section class="info-section">
            <h2><?= e(t('Çalışma Saatleri')) ?></h2>
            <div class="info-panel">
                <?php foreach ($hourGroups as $g): $h = $g['hours']; ?>
                    <div class="info-line<?= $today >= $g['from'] && $today <= $g['to'] ? ' today' : '' ?>">
                        <span><?= e(t(DAY_NAMES[$g['from']]) . ($g['to'] !== $g['from'] ? ' – ' . t(DAY_NAMES[$g['to']]) : '')) ?></span>
                        <b><?= $h === null ? e(t('Kapalı')) : ($h[0] === $h[1] ? e(t('24 saat açık')) : e($h[0]) . ' – ' . e($h[1])) ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/inc/layout_bottom.php'; ?>
