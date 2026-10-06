<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/backup.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/ayarlar.php');
}
csrf_check();

// Gecici dosyalar disaridan erisime kapali data/ klasorunde olusturulur ve istek bitince silinir.
$token = bin2hex(random_bytes(8));
$tmpDb = DATA_PATH . '/yedek-' . $token . '.db';
$tmpZip = DATA_PATH . '/yedek-' . $token . '.zip.tmp';
register_shutdown_function(static function () use ($tmpDb, $tmpZip): void {
    foreach ([$tmpDb, $tmpDb . '-journal', $tmpDb . '-wal', $tmpDb . '-shm', $tmpZip] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
});

try {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Yedek için PHP zip eklentisi gerekli. cPanel → "Select PHP Version" → Extensions bölümünden "zip" eklentisini açın.');
    }
    @set_time_limit(300);
    backup_database($tmpDb);
    backup_strip_secrets($tmpDb);

    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Yedek dosyası oluşturulamadı. data/ klasörünün yazılabilir olduğundan emin olun.');
    }
    $zip->addFile($tmpDb, 'menu.db');
    $zip->addFromString('YEDEK-OKU.txt', backup_readme());
    foreach (backup_upload_files() as $path => $relative) {
        $name = 'uploads/' . $relative;
        if ($zip->addFile($path, $name)) {
            $zip->setCompressionName($name, ZipArchive::CM_STORE);   // resimler zaten sikistirilmis
        }
    }
    if (!$zip->close() || !is_file($tmpZip)) {
        throw new RuntimeException('Yedek dosyası yazılamadı. Sunucuda yeterli boş alan olduğundan emin olun.');
    }
} catch (Throwable $t) {
    flash_exception($t);
    redirect('admin/ayarlar.php');
}

session_write_close();   // indirme surerken panelin diger sayfalari kilitlenmesin
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="menu-yedek-' . date('Y-m-d-Hi') . '.zip"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
readfile($tmpZip);
exit;
