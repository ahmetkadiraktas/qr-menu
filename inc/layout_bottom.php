</main>

<?php
require_once __DIR__ . '/queries.php';
$promoCampaigns = active_campaigns('popup');
?>
<?php if ($promoCampaigns): ?>
<div class="promo" id="promo" data-key="<?= e(implode('-', array_column($promoCampaigns, 'id'))) ?>" hidden>
    <div class="promo-box" role="dialog" aria-modal="true" aria-label="<?= e(t('Kampanyalar')) ?>">
        <button class="icon-btn promo-close" type="button" id="promoClose" aria-label="<?= e(t('Kapat')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
        <div class="promo-slides" id="promoSlides">
            <?php foreach ($promoCampaigns as $pc): ?>
                <div class="promo-slide">
                    <?php if ($pc['image'] !== ''): ?><img src="<?= e(upload_url('campaigns', (string) $pc['image'])) ?>" alt="" loading="lazy"><?php endif; ?>
                    <div class="promo-text">
                        <h2><?= e($pc['title']) ?></h2>
                        <?= nl2p((string) $pc['body']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($promoCampaigns) > 1): ?>
            <div class="promo-dots"><?php foreach ($promoCampaigns as $i => $_): ?><i class="<?= $i === 0 ? 'active' : '' ?>"></i><?php endforeach; ?></div>
            <button class="icon-btn promo-nav prev" type="button" data-dir="-1" aria-label="<?= e(t('Önceki')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg></button>
            <button class="icon-btn promo-nav next" type="button" data-dir="1" aria-label="<?= e(t('Sonraki')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 6l6 6-6 6"/></svg></button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<nav class="bottombar">
    <?php if (has_business_info()): ?>
    <a href="<?= e(url('bilgi.php')) ?>" class="<?= $activeNav === 'info' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9M4 9v11h16V9M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M9 20v-6h6v6"/></svg>
        <span><?= e(setting('site_name')) ?></span>
    </a>
    <?php endif; ?>
    <a href="<?= e(url('')) ?>" class="<?= $activeNav === 'menu' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        <?= e(t('Menü')) ?>
    </a>
    <a href="<?= e(url('ara.php')) ?>" class="<?= $activeNav === 'search' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <?= e(t('Ara')) ?>
    </a>
    <a href="<?= e(url('degerlendirme.php')) ?>" class="<?= $activeNav === 'review' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1.1-4.4A8 8 0 1 1 21 12z"/></svg>
        <?= e(t('Değerlendir')) ?>
    </a>
</nav>
<script src="<?= e(url('assets/js/app.js')) ?>?v=7"></script>
</body>
</html>
