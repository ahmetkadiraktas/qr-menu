<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Temel ayarlar. Normalde bu dosyaya dokunmaniza gerek yok.
// ---------------------------------------------------------------

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('DB_PATH', DATA_PATH . '/menu.db');
define('LOCK_PATH', DATA_PATH . '/installed.lock');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

define('MAX_UPLOAD_BYTES', 8 * 1024 * 1024);   // 8 MB
define('IMAGE_MAX_SIDE', 1600);                 // buyuk resim
define('THUMB_MAX_SIDE', 480);                  // kucuk resim
define('REVIEW_RATE_LIMIT_SECONDS', 600);       // ayni cihazdan (cerez) 10 dk'da 1 yorum
define('REVIEW_IP_PER_WINDOW', 8);              // ayni adresten (restoran Wi-Fi'si gibi ortak IP) 10 dk'da en fazla yorum
define('VARIANT_ROWS', 4);                       // urun basina en fazla porsiyon/secenek
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_SECONDS', 900);
define('LOGIN_GLOBAL_MAX', 20);                 // 15 dk icinde bu kadar hatali giris olursa her adrese tek deneme hakki kalir
define('SESSION_IDLE_SECONDS', 12 * 3600);      // admin oturumu bu kadar hareketsizlikten sonra kapanir
define('REVIEW_GLOBAL_PER_HOUR', 30);           // tum adreslerden bir saatte kabul edilen en fazla yorum
define('STATS_MAX_VISITORS_PER_DAY', 20000);    // gunluk ziyaretci ozeti tablosunun ust siniri
define('RESTORE_MAX_FILES', 10000);             // yedekten geri yuklemede en fazla resim sayisi
define('RESTORE_MAX_BYTES', 1024 * 1024 * 1024);// ... ve toplam boyut (1 GB)
define('DB_SCHEMA_VERSION', 11);                 // veritabani yapisi degisince artirilir (bkz. db_migrate)

// Site kok URL'sini otomatik bul (alt klasore kurulsa bile calisir)
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: ''), '/') : '';
    $root = str_replace('\\', '/', ROOT_PATH);
    $path = ($docRoot !== '' && strpos($root, $docRoot) === 0) ? substr($root, strlen($docRoot)) : '';
    $base = rtrim($path, '/');
    return $base;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DATA_PATH . '/error.log');
date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');
