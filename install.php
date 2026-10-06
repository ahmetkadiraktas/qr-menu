<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/backup.php';
require_once __DIR__ . '/inc/auth.php';

// Kurulum tamamlandiysa bu sayfa kapalidir.
if (is_installed()) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Kurulum kapalı</title>'
       . '<body style="font-family:system-ui;padding:40px;text-align:center">'
       . '<h2>Kurulum zaten tamamlanmış.</h2><p>Sıfırdan kurmak için sunucudaki <code>data/</code> klasöründen <code>menu.db</code>, <code>menu.db-wal</code>, <code>menu.db-shm</code> ve <code>installed.lock</code> dosyalarını silin. Yalnızca şifreyi sıfırlamak için <code>KURULUM.txt</code> dosyasındaki adımlara bakın.</p>'
       . '<p><a href="' . e(url('')) . '">Menüye git</a> · <a href="' . e(url('admin/')) . '">Admin paneli</a></p></body>';
    exit;
}

/**
 * data/ klasoru disaridan okunabiliyor mu? Sunucunun kendisine (127.0.0.1) istek atilarak denenir.
 * true: okunabiliyor (.htaccess korumasi calismiyor), false: korunuyor, null: anlasilamadi.
 */
function data_dir_exposed(): ?bool
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/i', $host)) {
        return null;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $port = (int) ($_SERVER['SERVER_PORT'] ?? ($https ? 443 : 80));
    $ctx = stream_context_create([
        'http' => ['timeout' => 3, 'ignore_errors' => true, 'header' => 'Host: ' . $host . "\r\n", 'follow_location' => 0],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false, 'peer_name' => preg_replace('/:\d+$/', '', $host)],
    ]);
    $fp = @fopen(($https ? 'https' : 'http') . '://127.0.0.1:' . $port . url('data/.htaccess'), 'r', false, $ctx);
    if ($fp === false) {
        return null;
    }
    $status = (string) (stream_get_meta_data($fp)['wrapper_data'][0] ?? '');
    $body = (string) stream_get_contents($fp, 2000);
    fclose($fp);
    if (!preg_match('#^HTTP/\S+\s+(\d{3})#', $status, $m)) {
        return null;
    }
    return (int) $m[1] === 200 && strpos($body, 'denied') !== false;
}

$dataExposed = file_exists(DATA_PATH . '/.htaccess') ? data_dir_exposed() : null;
$isLocalHost = (bool) preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', (string) ($_SERVER['HTTP_HOST'] ?? ''));

$checks = [
    'PHP 7.4+'                       => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO SQLite'                     => extension_loaded('pdo_sqlite'),
    'GD (resim işleme)'              => extension_loaded('gd'),
    'Fileinfo'                       => extension_loaded('fileinfo'),
    'Zip (yedek için)'               => class_exists('ZipArchive'),
    'data/ yazılabilir'              => is_writable(DATA_PATH),
    'uploads/ yazılabilir'           => is_writable(UPLOAD_PATH),
    '.htaccess dosyaları yüklenmiş'  => file_exists(ROOT_PATH . '/.htaccess') && file_exists(DATA_PATH . '/.htaccess') && file_exists(UPLOAD_PATH . '/.htaccess'),
    // Yerel denemede (php -S) .htaccess calismaz; orada yalnizca uyarilir, gercek alan adinda kurulum durdurulur
    'data/ klasörü dışarıya kapalı'  => $dataExposed !== true || $isLocalHost,
];
$ok = !in_array(false, $checks, true);
$error = '';

// Daha once kurulmus bir veritabani var ama kilit dosyasi silinmis: sifre sifirlama modu.
// Kotu niyetli birinin admin sifresini degistirmesini onlemek icin sunucuda data/reset.key dosyasi olusturulmasi istenir.
// Yedek zip'inden kurulum yalnizca henuz veritabani yokken sunulur (asagidaki kontrol db() ile dosyayi acmadan once bakilir).
$canRestore = !file_exists(DB_PATH);
$resetMode = false;
// Yedekten geri yuklenen veritabaninda admin sifresi bulunmaz: veriler korunur, sadece yeni sifre istenir.
$restoreMode = false;
$resetKeyPath = DATA_PATH . '/reset.key';
if (file_exists(DB_PATH)) {
    try {
        $existingHash = (string) db()->query("SELECT value FROM settings WHERE key = 'admin_password_hash'")->fetchColumn();
        $resetMode = $existingHash !== '';
        $restoreMode = !$resetMode && setting('site_name') !== '';
    } catch (Throwable $t) {
        $resetMode = true;   // veritabani okunamiyorsa en kisitli kip varsayilir
    }
}
// Mevcut verinin uzerine sifre belirlemek (sifirlama ya da elle geri yukleme) icin sunucuya dosya yazabildigini
// kanitlamak gerekir: data/reset.key dosyasina yazilan kod formda sorulur. Dosyanin yalnizca var olmasi yetmez.
$needsKey = $resetMode || $restoreMode;
$resetKey = $needsKey && is_file($resetKeyPath) ? trim((string) file_get_contents($resetKeyPath)) : '';
$resetAllowed = !$needsKey || strlen($resetKey) >= 8;

if ($ok && $_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    // Gonderilen veri post_max_size sinirini asinca PHP formu tamamen bos birakir
    $error = 'Seçtiğiniz dosya sunucunun yükleme sınırını (' . ini_get('post_max_size') . ') aşıyor. Zip içindeki YEDEK-OKU.txt dosyasındaki elle geri yükleme adımlarını kullanın.';
} elseif ($ok && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$resetAllowed || ($needsKey && !hash_equals($resetKey, post('reset_key')))) {
        http_response_code(403);
        exit('Sıfırlama kodu hatalı. data/reset.key dosyasına en az 8 karakterlik bir kod yazın ve formda aynı kodu girin.');
    }
    $siteName = post('site_name');
    $pass = post('password');
    $pass2 = post('password2');
    $backupFile = $canRestore && !empty($_FILES['backup']['name']) ? $_FILES['backup'] : null;
    $demo = isset($_POST['demo']) && !$restoreMode && $backupFile === null;

    if (($resetMode || $restoreMode) && $siteName === '') {
        $siteName = setting('site_name', 'Restoran Menü');
    }
    if ($siteName === '' && $backupFile === null) {
        $error = 'Restoran adı boş olamaz.';
    } elseif (mb_strlen($pass) < 8) {
        $error = 'Şifre en az 8 karakter olmalı.';
    } elseif ($pass !== $pass2) {
        $error = 'Şifreler eşleşmiyor.';
    } else {
        try {
            if ($backupFile !== null) {
                backup_restore_upload($backupFile);
            }
            $pdo = db();
            db_create_schema($pdo);
            db_seed_defaults($pdo);
            if ($backupFile !== null) {
                $siteName = setting('site_name', $siteName !== '' ? $siteName : 'Restoran Menü');   // yedekteki ad korunur
            }
            setting_save('site_name', $siteName);
            setting_save('admin_password_hash', password_hash($pass, PASSWORD_DEFAULT));
            if ($demo) {
                db_seed_demo($pdo);
            }
            file_put_contents(LOCK_PATH, date('c'));
            foreach ([DATA_PATH, UPLOAD_PATH, UPLOAD_PATH . '/products', UPLOAD_PATH . '/categories', UPLOAD_PATH . '/campaigns', UPLOAD_PATH . '/site', ROOT_PATH . '/inc', DATA_PATH . '/sessions'] as $dir) {
                if (is_dir($dir) && !file_exists($dir . '/index.html')) {
                    @file_put_contents($dir . '/index.html', '');
                }
            }
            if (file_exists($resetKeyPath)) {
                @unlink($resetKeyPath);
            }
            admin_session_begin();
            flash('success', $backupFile !== null ? 'Yedek geri yüklendi. Hoş geldiniz!' : 'Kurulum tamamlandı. Hoş geldiniz!');
            redirect('admin/dashboard.php');
        } catch (Throwable $t) {
            error_log('Kurulum hatasi: ' . $t->getMessage());
            $error = $t instanceof RuntimeException ? $t->getMessage() : 'Kurulum sırasında hata oluştu. Ayrıntı data/error.log dosyasında.';
            if ($backupFile !== null) {
                // Yarim kalan geri yuklemede veritabani birakilmaz ki ayni ekrandan tekrar denenebilsin
                foreach ([DB_PATH, DB_PATH . '-wal', DB_PATH . '-shm'] as $partial) {
                    if (is_file($partial)) {
                        @unlink($partial);
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kurulum · Online Menü</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>?v=13">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1>Online Menü Kurulumu</h1>
    <p class="muted">Birkaç saniye içinde menünüz hazır olacak.</p>

    <ul class="check-list">
        <?php foreach ($checks as $label => $pass): ?>
            <li class="<?= $pass ? 'pass' : 'fail' ?>"><span><?= e($label) ?></span><b><?= $pass ? 'Tamam' : 'Eksik' ?></b></li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$ok): ?>
        <div class="alert alert-error">Eksik gereksinimler var. cPanel → "Select PHP Version" bölümünden ilgili eklentileri açın, klasör izinlerini (755) kontrol edin ve gizli <code>.htaccess</code> dosyalarının da yüklendiğinden emin olun.<?php if ($dataExposed === true): ?> <b>data/ klasörü dışarıdan okunabiliyor:</b> sunucunuz <code>.htaccess</code> kurallarını uygulamıyor; bu haliyle veritabanı indirilebilir. Hosting desteğinden <code>AllowOverride All</code> ayarını isteyin.<?php endif; ?></div>
    <?php elseif (!$resetAllowed): ?>
        <div class="alert alert-info"><b><?= $resetMode ? 'Şifre sıfırlama modu.' : 'Yedekten geri yükleme.' ?></b> Mevcut bir veritabanı bulundu. Güvenlik için cPanel Dosya Yöneticisi'nden <code>data/reset.key</code> adında bir dosya oluşturun, içine kendi belirleyeceğiniz <b>en az 8 karakterlik bir kod</b> yazıp kaydedin, sonra bu sayfayı yenileyin. Ürünleriniz ve yorumlarınız korunur.</div>
    <?php else: ?>
        <?php if ($resetMode): ?><div class="alert alert-info">Şifre sıfırlama modu: yeni şifre belirleyin. Mevcut veriler korunur, <code>reset.key</code> otomatik silinir.</div><?php endif; ?>
        <?php if ($restoreMode): ?><div class="alert alert-info">Yedekten geri yükleme: mevcut verileriniz bulundu ve korunacak. Yeni bir admin şifresi belirleyin.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" autocomplete="off"<?= $canRestore ? ' enctype="multipart/form-data"' : '' ?>>
            <?= csrf_field() ?>
            <label>Restoran adı
                <input type="text" name="site_name" <?= $resetMode || $canRestore ? '' : 'required' ?> maxlength="80" value="<?= e(post('site_name') !== '' ? post('site_name') : ($resetMode || $restoreMode ? setting('site_name') : '')) ?>" placeholder="Örn. Ferhatlar Enderun">
            </label>
            <?php if ($needsKey): ?>
            <label>Sıfırlama kodu
                <input type="text" name="reset_key" required autocomplete="off" placeholder="data/reset.key dosyasına yazdığınız kod">
            </label>
            <?php endif; ?>
            <label>Admin şifresi
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label>Şifre (tekrar)
                <input type="password" name="password2" required minlength="8" autocomplete="new-password">
            </label>
            <?php if (!$resetMode && !$restoreMode): ?><label class="checkbox"><input type="checkbox" name="demo" checked> Örnek kategori ve ürünler eklensin (sonra silebilirsiniz)</label><?php endif; ?>
            <?php if ($canRestore): ?>
                <label>Yedekten kur <span class="muted" style="font-weight:400">(isteğe bağlı)</span>
                    <input type="file" name="backup" accept=".zip,application/zip">
                </label>
                <p class="muted" style="font-size:12px;margin:6px 0 0">Daha önce panelden "Yedeği indir" ile aldığınız zip dosyasını seçerseniz ürünler, yorumlar, ayarlar ve resimler geri yüklenir; restoran adı yedekten alınır. En fazla <?= e((string) ini_get('upload_max_filesize')) ?>.</p>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-block">Kurulumu Tamamla</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
