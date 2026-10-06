<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/info.php';
require_installed();

$notes = setting_localized('info_notes');
$kvkk = current_lang() === 'en' && setting('kvkk_text_en') !== '' ? setting('kvkk_text_en') : kvkk_text();

$pageTitle = t('Bilgilendirme');
$backUrl = url('');
$activeNav = '';
require __DIR__ . '/inc/layout_top.php';
?>
<div class="legal-page">
    <h1><?= e(t('Bilgilendirme')) ?></h1>
    <?php if ($notes === '' && $kvkk === ''): ?>
        <div class="empty"><?= e(t('Henüz bilgilendirme eklenmemiş.')) ?></div>
    <?php endif; ?>

    <?php if ($notes !== ''): ?>
        <section>
            <h2><?= e(t('Notlar')) ?></h2>
            <div class="info-text notes"><?= nl2p($notes) ?></div>
        </section>
    <?php endif; ?>

    <?php if ($kvkk !== ''): ?>
        <section id="kvkk">
            <h2><?= e(t('Kişisel Verilerin Korunması (KVKK)')) ?></h2>
            <div class="info-text"><?= nl2p($kvkk) ?></div>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/inc/layout_bottom.php'; ?>
