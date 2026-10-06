<?php
/** Yedekleme yardimcilari: veritabaninin tutarli kopyasi + uploads/ altindaki resimler */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

/** uploads/ altindaki resim dosyalari: tam yol => zip icindeki goreli yol. Yardimci dosyalar (.htaccess, index.html) alinmaz. */
function backup_upload_files(): array
{
    $files = [];
    if (!is_dir(UPLOAD_PATH)) {
        return $files;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_PATH, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && !$file->isLink() && preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) {
            $files[$file->getPathname()] = str_replace('\\', '/', substr($file->getPathname(), strlen(UPLOAD_PATH) + 1));
        }
    }
    ksort($files);
    return $files;
}

/** Yedegin yaklasik boyutu (bayt). */
function backup_size(): int
{
    $size = is_file(DB_PATH) ? (int) filesize(DB_PATH) : 0;
    foreach (array_keys(backup_upload_files()) as $path) {
        $size += (int) filesize($path);
    }
    return $size;
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    return number_format(max(1, (int) ceil($bytes / 1024)), 0, ',', '.') . ' KB';
}

/**
 * Veritabaninin tutarli bir kopyasini $dest dosyasina yazar.
 * Veritabani WAL modunda oldugu icin menu.db'yi duz kopyalamak son degisiklikleri kacirabilir:
 * once VACUUM INTO denenir; eski SQLite surumlerinde WAL ana dosyaya islenip dosya kopyalanir.
 */
function backup_database(string $dest): void
{
    $pdo = db();
    try {
        $pdo->exec('VACUUM INTO ' . $pdo->quote($dest));
        return;
    } catch (PDOException $e) {
        if (is_file($dest)) {
            @unlink($dest);
        }
    }
    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    if (!copy(DB_PATH, $dest)) {
        throw new RuntimeException('Veritabanı kopyalanamadı. data/ klasörünün yazılabilir olduğundan emin olun.');
    }
}

/**
 * Yedek kopyasindan yonetici sifresinin ozetini ve giris denemesi kayitlarini cikarir.
 * Silinen veri dosyanin bos sayfalarinda kalmasin diye secure_delete + VACUUM kullanilir.
 */
function backup_strip_secrets(string $dbFile): void
{
    $copy = new PDO('sqlite:' . $dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $copy->exec('PRAGMA journal_mode = DELETE');
    $copy->exec('PRAGMA secure_delete = ON');
    $copy->exec("DELETE FROM settings WHERE key = 'admin_password_hash'");
    $copy->exec('DELETE FROM login_attempts');
    $copy->exec('VACUUM');
    $copy = null;
}

/** Yuklenen yedek zip'ini (kurulum ekranindan) geri yukler. Geri yuklenen resim sayisini dondurur. */
function backup_restore_upload(array $file): int
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (is_array($error) || $error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Yedek dosyası seçilmedi.');
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Yedek dosyası sunucunun yükleme sınırını (' . ini_get('upload_max_filesize') . ') aşıyor. Zip içindeki YEDEK-OKU.txt dosyasındaki elle geri yükleme adımlarını kullanın.');
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Yedek dosyası yüklenemedi (kod ' . (int) $error . ').');
    }
    return backup_restore_zip($file['tmp_name']);
}

/**
 * Yedek zip'inden veritabanini ve resimleri yerine koyar. Sadece henuz veritabani yokken cagrilmalidir.
 * Zip icinden yalnizca menu.db ve uploads/<bilinen klasor>/<resim> dosyalari alinir; diger her sey yok sayilir.
 */
function backup_restore_zip(string $zipPath): int
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Yedekten kurmak için PHP zip eklentisi gerekli. cPanel → "Select PHP Version" → Extensions bölümünden "zip" eklentisini açın.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Yedek dosyası açılamadı. "Yedeği indir" ile aldığınız zip dosyasını seçin.');
    }
    $tmpDb = DATA_PATH . '/geri-' . bin2hex(random_bytes(8)) . '.db';
    try {
        $dbStat = $zip->statName('menu.db');
        if ($dbStat === false) {
            throw new RuntimeException('Bu dosya bir menü yedeği değil (içinde menu.db yok).');
        }
        if ($dbStat['size'] > 300 * 1024 * 1024) {
            throw new RuntimeException('Yedekteki veritabanı beklenenden çok büyük.');
        }
        backup_extract_entry($zip, 'menu.db', $tmpDb);
        backup_check_database($tmpDb);
        foreach ([DB_PATH . '-wal', DB_PATH . '-shm'] as $stale) {
            if (is_file($stale)) {
                @unlink($stale);
            }
        }
        if (!rename($tmpDb, DB_PATH)) {
            throw new RuntimeException('Veritabanı yerine konamadı. data/ klasörünün yazılabilir olduğundan emin olun.');
        }

        $count = 0;
        $bytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            // Klasor ve dosya adi beyaz listeyle sinirlanir: zip icindeki "../" gibi yollar disari yazamaz
            if ($stat === false || !preg_match('#^uploads/(products|categories|campaigns|site)/([A-Za-z0-9_-]+\.(?:jpe?g|png|webp))$#', $stat['name'], $m)) {
                continue;
            }
            if ($stat['size'] > 25 * 1024 * 1024) {
                continue;
            }
            $bytes += (int) $stat['size'];
            if ($count >= RESTORE_MAX_FILES || $bytes > RESTORE_MAX_BYTES) {
                break;   // beklenenden buyuk arsiv: disk dolmasin diye kalan resimler alinmaz
            }
            $dir = UPLOAD_PATH . '/' . $m[1];
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new RuntimeException('uploads/ klasörüne yazılamadı. Klasör izinlerini kontrol edin.');
            }
            $dest = $dir . '/' . $m[2];
            backup_extract_entry($zip, $stat['name'], $dest);
            if (@getimagesize($dest) === false) {
                @unlink($dest);   // resim degil
                continue;
            }
            $count++;
        }
        return $count;
    } finally {
        $zip->close();
        if (is_file($tmpDb)) {
            @unlink($tmpDb);
        }
    }
}

function backup_extract_entry(ZipArchive $zip, string $name, string $dest): void
{
    $in = $zip->getStream($name);
    $out = $in !== false ? fopen($dest, 'wb') : false;
    if ($in === false || $out === false) {
        if (is_resource($in)) {
            fclose($in);
        }
        throw new RuntimeException('Yedekten "' . $name . '" çıkarılamadı.');
    }
    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);
}

/** Cikarilan dosyanin saglam bir menu veritabani oldugunu dogrular. */
function backup_check_database(string $dbFile): void
{
    try {
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $healthy = $pdo->query('PRAGMA quick_check')->fetchColumn() === 'ok';
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        $pdo = null;
    } catch (PDOException $e) {
        $healthy = false;
        $tables = [];
    }
    if (!$healthy || array_diff(['settings', 'categories', 'products'], $tables)) {
        throw new RuntimeException('Yedekteki veritabanı okunamadı veya bozuk.');
    }
}

function backup_readme(): string
{
    return implode("\r\n", [
        'ONLINE MENU - YEDEK',
        '===================',
        'Alindigi tarih: ' . date('d.m.Y H:i'),
        '',
        'ICINDEKILER',
        '- menu.db   : tum veriler (urunler, kategoriler, yorumlar, kampanyalar, ayarlar, istatistikler)',
        '- uploads/  : tum resimler',
        '',
        'GERI YUKLEME - KOLAY YOL',
        '1) Yeni bir sunucuya kuruyorsaniz site dosyalarini yukleyin.',
        '   Mevcut kurulumun uzerine geri yukluyorsaniz data/ klasorundeki menu.db, menu.db-wal, menu.db-shm ve',
        '   installed.lock dosyalarini silin.',
        '2) Tarayicidan siteadi.com/install.php adresini acin, yeni admin sifrenizi yazin ve "Yedekten kur" alaninda',
        '   bu zip dosyasini secip kurulumu tamamlayin. Urunler, yorumlar, ayarlar ve resimler geri gelir.',
        '',
        'GERI YUKLEME - ELLE (zip sunucunun yukleme sinirini asiyorsa, cPanel Dosya Yoneticisi ile)',
        '1) Sitenin kurulu oldugu klasorde data/ klasorunu acin.',
        '   Varsa menu.db-wal, menu.db-shm ve installed.lock dosyalarini silin, sonra bu yedekteki menu.db dosyasini',
        '   data/ icine yukleyin (eskisinin uzerine yazin).',
        '2) Bu yedekteki uploads/ klasorunun icindekileri sitedeki uploads/ klasorune yukleyin (alt klasorleriyle birlikte).',
        '3) data/ icinde reset.key adinda bir dosya olusturup icine kendi belirleyeceginiz en az 8 karakterlik bir kod yazin.',
        '   Tarayicidan siteadi.com/install.php adresini acin, ayni kodu ve yeni admin sifrenizi girin.',
        '   Yedekte admin sifresi bulunmaz; bu adim olmadan panele girilemez. Urunleriniz ve yorumlariniz korunur.',
        '(Site yeni bir sunucuya tasiniyorsa once site dosyalarini yukleyin, sonra ayni 3 adimi uygulayin.)',
        '',
        'UYARI: Bu dosya musteri yorumlarini icerir. Guvenli bir yerde saklayin, paylasmayin.',
        '',
    ]);
}
