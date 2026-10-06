<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
require_admin();

// Menunun tam adresi: QR kodun icine yazilacak. Host basligi guvenilmez oldugu icin sadece gecerli karakterler kabul edilir.
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/i', $host)) {
    $host = '';
}
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$menuUrl = $host !== '' ? ($https ? 'https' : 'http') . '://' . $host . url('') : '';

$pageTitle = 'QR Kod';
$activeMenu = 'qr';
require __DIR__ . '/../inc/admin_top.php';
?>
<div class="page-head"><h1>QR Kod</h1></div>
<div class="grid-2">
    <div class="card qr-settings">
        <h2>Menü QR Kodu</h2>
        <p class="muted" style="margin:0 0 12px;font-size:13px">Müşteriler bu kodu telefon kamerasıyla okutunca menünüz açılır. Yazdırıp masalara, kasaya veya vitrine koyabilirsiniz.</p>
        <div class="form-row">
            <label for="qrUrl">Menü adresi</label>
            <input type="url" id="qrUrl" maxlength="300" value="<?= e($menuUrl) ?>" placeholder="https://siteadi.com/">
            <div class="hint">Otomatik bulunur. Alan adınız farklıysa buradan düzeltebilirsiniz.</div>
        </div>
        <div class="form-row">
            <label for="qrCaption">Kartta görünecek yazı</label>
            <input type="text" id="qrCaption" maxlength="60" value="Menü için okutun">
        </div>
        <div class="alert alert-error" id="qrError" hidden></div>
        <div class="inline">
            <button class="btn btn-primary" type="button" id="qrPrint">Yazdır</button>
            <button class="btn" type="button" id="qrPng">PNG indir</button>
            <button class="btn" type="button" id="qrSvg">SVG indir</button>
        </div>
        <div class="hint muted" style="margin-top:12px;font-size:12px">Matbaaya verecekseniz SVG'yi kullanın; her boyutta net çıkar. Alan adınız değişirse kodu yeniden basmanız gerekir.</div>
    </div>
    <div class="card qr-card">
        <div class="qr-name"><?= e(setting('site_name')) ?></div>
        <div class="qr-box" id="qrBox"></div>
        <div class="qr-caption" id="qrCaptionOut"></div>
        <div class="qr-url" id="qrUrlOut"></div>
    </div>
</div>
<script src="<?= e(url('assets/js/qrcode.min.js')) ?>?v=1"></script>
<script src="<?= e(url('assets/js/qr.js')) ?>?v=1"></script>
<?php require __DIR__ . '/../inc/admin_bottom.php'; ?>
