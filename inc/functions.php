<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';

// ---------- Oturum ----------
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    $sessDir = DATA_PATH . '/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0700, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        ini_set('session.save_path', $sessDir);
    }
    // Ozel kayit klasorunde eski oturum dosyalari birikmesin diye temizlik acikca ayarlanir
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_SECONDS);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '50');
    session_name('menusess');
    session_start();
}

// ---------- Cikti guvenligi ----------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nl2p(string $text): string
{
    $parts = preg_split('/\R{2,}/', trim($text)) ?: [];
    $out = '';
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        $out .= '<p>' . nl2br(e($p)) . '</p>';
    }
    return $out;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    start_session();
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit(t('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'));
    }
}

// ---------- Yonlendirme & flash ----------
function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** RuntimeException mesajlari kullaniciya gosterilir; diger hatalar loglanip genel mesaj verilir. */
function flash_exception(Throwable $t): void
{
    if ($t instanceof RuntimeException) {
        flash('error', $t->getMessage());
        return;
    }
    error_log('Hata: ' . get_class($t) . ': ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine());
    flash('error', 'İşlem sırasında beklenmeyen bir hata oluştu. Ayrıntı için data/error.log dosyasına bakın.');
}

function like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

function flash_render(): string
{
    // Oturum cerezi yoksa bekleyen mesaj da olamaz; her ziyaretciye bosuna oturum dosyasi acilmaz
    if (!isset($_COOKIE['menusess']) && session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    start_session();
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---------- Ayarlar ----------
function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $s = settings();
    return isset($s[$key]) && $s[$key] !== '' ? $s[$key] : $default;
}

/** Ingilizce modda "<anahtar>_en" doluysa onu, degilse Turkcesini dondurur (slogan, notlar gibi serbest metinler). */
function setting_localized(string $key, string $default = ''): string
{
    if (current_lang() === 'en' && setting($key . '_en') !== '') {
        return setting($key . '_en');
    }
    return setting($key, $default);
}

function setting_save(string $key, string $value): void
{
    db()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')
        ->execute([$key, $value]);
}

// ---------- Yardimcilar ----------
function is_installed(): bool
{
    return file_exists(LOCK_PATH) && file_exists(DB_PATH);
}

function require_installed(): void
{
    if (!is_installed()) {
        redirect('install.php');
    }
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
}

/**
 * Hiz siniri icin adres anahtari. IPv6'da tek bir abone genellikle koca bir /64 blogu kullanabildigi icin
 * adresin ilk 64 biti alinir; IPv4 oldugu gibi kalir.
 */
function ip_bucket(): string
{
    $ip = client_ip();
    $bin = strpos($ip, ':') !== false ? @inet_pton($ip) : false;
    if ($bin === false || strlen($bin) !== 16) {
        return $ip;
    }
    return inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
}

/** Tek degerli GET parametresi (dizi gonderilirse yok sayilir). */
function query(string $key, int $max = 200): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function format_price(?float $price): string
{
    if ($price === null) {
        return '';
    }
    [$dec, $thou] = current_lang() === 'en' ? ['.', ','] : [',', '.'];
    $formatted = fmod($price, 1.0) == 0.0 ? number_format($price, 0, $dec, $thou) : number_format($price, 2, $dec, $thou);
    return $formatted . ' ' . setting('currency', '₺');
}

/**
 * Urunun porsiyon/secenek listesi (gecerli dilde): [['name' => string, 'price' => float|null], ...].
 * variants kolonunda JSON olarak saklanir: [{"name": "Yarım", "name_en": "Half", "price": 180}, ...]
 */
function product_variants(array $product): array
{
    $raw = json_decode((string) ($product['variants'] ?? ''), true);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $v) {
        if (!is_array($v) || !is_string($v['name'] ?? null) || trim($v['name']) === '') {
            continue;
        }
        $names = localize(['name' => $v['name'], 'name_en' => $v['name_en'] ?? ''], 'name');
        $out[] = ['name' => $names['name'], 'price' => is_numeric($v['price'] ?? null) ? (float) $v['price'] : null];
    }
    return $out;
}

/**
 * Listede ve baslikta gosterilecek fiyat. Fiyatli secenek varsa onlardan uretilir: hepsi fiyatliysa "en dusuk – en yuksek",
 * bazilari fiyatsizsa "en dusuk+" (fiyat sabit degil anlamina). Fiyatli secenek yoksa urunun tek fiyati.
 */
function product_price_label(array $product): string
{
    $variants = product_variants($product);
    $prices = array_values(array_filter(array_column($variants, 'price'), fn($p) => $p !== null));
    if ($prices) {
        $min = min($prices);
        $max = max($prices);
        if (count($prices) < count($variants)) {
            return format_price($min) . '+';
        }
        return $min === $max ? format_price($min) : format_price($min) . ' – ' . format_price($max);
    }
    return $product['price'] !== null ? format_price((float) $product['price']) : '';
}

/** Fiyat etiketi bos kaldiginda musteriye gosterilecek acik ifade. */
function price_missing_label(): string
{
    return t('Fiyat belirtilmedi');
}

/**
 * Anonim gonderim siniri (yorum ve urun puani): ayni cihaz (cerez) 10 dk'da 1, ayni adres 10 dk'da en fazla
 * REVIEW_IP_PER_WINDOW (restoran Wi-Fi'sinde herkes ayni IP'dir), tum adresler saatte REVIEW_GLOBAL_PER_HOUR.
 * Hata mesajini (ceviri anahtari) ya da izin varsa null dondurur. Cagiran taraf acik bir yazma kilidi icindeyken cagirmalidir.
 */
function submission_limit_error(PDO $pdo, string $table, string $cookie): ?string
{
    $deviceTs = (int) ($_COOKIE[$cookie] ?? 0);
    if ($deviceTs > 0 && time() - $deviceTs < REVIEW_RATE_LIMIT_SECONDS) {
        return 'Kısa süre önce bir değerlendirme gönderdiniz. Lütfen biraz sonra tekrar deneyin.';
    }
    $recent = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE ip = ? AND created_at > datetime('now','localtime', ?)");
    $recent->execute([ip_bucket(), '-' . REVIEW_RATE_LIMIT_SECONDS . ' seconds']);
    if ((int) $recent->fetchColumn() >= REVIEW_IP_PER_WINDOW) {
        return 'Kısa süre önce bir değerlendirme gönderdiniz. Lütfen biraz sonra tekrar deneyin.';
    }
    if ((int) $pdo->query("SELECT COUNT(*) FROM $table WHERE created_at > datetime('now','localtime','-1 hour')")->fetchColumn() >= REVIEW_GLOBAL_PER_HOUR) {
        return 'Şu anda çok fazla değerlendirme alıyoruz. Lütfen daha sonra tekrar deneyin.';
    }
    return null;
}

/** Gonderim sonrasi cihaz cerezi: ayni cihazdan ayni seyi 10 dk icinde tekrar gondermeyi engeller. */
function mark_submission(string $cookie): void
{
    setcookie($cookie, (string) time(), ['expires' => time() + REVIEW_RATE_LIMIT_SECONDS, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
}

/** Turkce harflere duyarsiz karsilastirma icin metni sadelestirir (SQLite LIKE yalnizca ASCII'de buyuk/kucuk harf ayirmaz). */
function search_fold(string $text): string
{
    $text = str_replace(['İ', 'I', 'ı'], ['i', 'ı', 'ı'], $text);
    return str_replace('ı', 'i', mb_strtolower($text));
}

/** Aranan metin verilen alanlardan birinde (Turkce harflere duyarsiz) geciyor mu? */
function search_matches(string $needle, string ...$haystacks): bool
{
    $n = search_fold($needle);
    foreach ($haystacks as $h) {
        if ($h !== '' && mb_strpos(search_fold($h), $n) !== false) {
            return true;
        }
    }
    return false;
}

/** Hata sonrasi popup formunun doldurulmus kalmasi icin gonderilen alanlar bir sonraki istege tasinir. */
function remember_form(string $kind): void
{
    start_session();
    $data = $_POST;
    unset($data['csrf']);
    $_SESSION['form_old'] = ['kind' => $kind, 'data' => $data];
}

/** remember_form() ile saklanan alanlari (tek kullanimlik) dondurur; tur uyusmuyorsa null. */
function recall_form(string $kind): ?array
{
    if (!isset($_COOKIE['menusess'])) {
        return null;
    }
    start_session();
    $old = $_SESSION['form_old'] ?? null;
    unset($_SESSION['form_old']);
    return is_array($old) && ($old['kind'] ?? '') === $kind && is_array($old['data'] ?? null) ? $old['data'] : null;
}

/**
 * Sabit rozet, diyet ve alerjen secenekleri: anahtar => [ad, ikon, tur]. Urunde diet kolonunda virgullu saklanir.
 * 'badge' turundekiler sadece rozet olarak gosterilir; 'diet' turundekiler menude filtrelenebilir; 'allergen' turundekiler urun detayinda "icerir" olarak gosterilir.
 */
function diet_options(): array
{
    return [
        'yeni'       => ['Yeni', '✨', 'badge'],
        'populer'    => ['Popüler', '🔥', 'badge'],
        'sef'        => ['Şefin önerisi', '👨‍🍳', 'badge'],
        'vegan'      => ['Vegan', '🌱', 'diet'],
        'vejetaryen' => ['Vejetaryen', '🥗', 'diet'],
        'glutensiz'  => ['Glütensiz', '🌾', 'diet'],
        'laktozsuz'  => ['Laktozsuz', '🥛', 'diet'],
        'acili'      => ['Acılı', '🌶️', 'diet'],
        'gluten'     => ['Gluten', '🌾', 'allergen'],
        'sut'        => ['Süt', '🥛', 'allergen'],
        'yumurta'    => ['Yumurta', '🥚', 'allergen'],
        'kuruyemis'  => ['Kuruyemiş', '🌰', 'allergen'],
        'yerfistigi' => ['Yer fıstığı', '🥜', 'allergen'],
        'susam'      => ['Susam', '', 'allergen'],
        'balik'      => ['Balık', '🐟', 'allergen'],
        'deniz'      => ['Deniz ürünü', '🦐', 'allergen'],
        'soya'       => ['Soya', '', 'allergen'],
    ];
}

/** Urunun diet kolonundaki (virgullu) anahtarlardan istenen turdekileri tanim sirasiyla dondurur: anahtar => [ad, ikon, tur]. */
function diet_to_array(string $diet, string $type): array
{
    $keys = array_map('trim', explode(',', $diet));
    $out = [];
    foreach (diet_options() as $key => $opt) {
        if ($opt[2] === $type && in_array($key, $keys, true)) {
            $out[$key] = $opt;
        }
    }
    return $out;
}

function diet_label(array $opt): string
{
    return ($opt[1] !== '' ? $opt[1] . ' ' : '') . t($opt[0]);
}

function upload_url(string $folder, string $filename, bool $thumb = false): string
{
    if ($filename === '') {
        return '';
    }
    if ($thumb) {
        $filename = preg_replace('/(\.\w+)$/', '_thumb$1', $filename);
    }
    return url('uploads/' . $folder . '/' . $filename);
}

// ---------- Resim yukleme ----------
/**
 * Yuklenen resmi dogrular, GD ile yeniden kodlar (guvenlik), boyutlandirir ve thumbnail uretir.
 * Basarili olursa dosya adini dondurur, aksi halde hata mesaji ile Exception firlatir.
 */
function upload_image(array $file, string $folder): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Geçersiz yükleme.');
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('Dosya seçilmedi.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Dosya çok büyük.');
        default:
            throw new RuntimeException('Yükleme hatası (kod ' . (int) $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Dosya 8 MB sınırını aşıyor.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Geçersiz dosya.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Sadece JPG, PNG veya WebP yükleyebilirsiniz.');
    }

    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[0] < 1 || $info[1] < 1) {
        throw new RuntimeException('Resim okunamadı.');
    }
    if ($info[0] * $info[1] > 30000000) {
        throw new RuntimeException('Resim çözünürlüğü çok yüksek (en fazla 30 megapiksel).');
    }
    $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if ($src === false) {
        throw new RuntimeException('Resim okunamadı.');
    }
    if (function_exists('exif_read_data') && $mime === 'image/jpeg') {
        $src = image_fix_orientation($src, $file['tmp_name']);
    }

    $dir = UPLOAD_PATH . '/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
    }
    $hasAlpha = in_array($mime, ['image/png', 'image/webp'], true);
    $ext = $hasAlpha ? 'png' : 'jpg';
    $name = bin2hex(random_bytes(12)) . '.' . $ext;

    image_save_resized($src, $dir . '/' . $name, IMAGE_MAX_SIDE, $hasAlpha);
    image_save_resized($src, $dir . '/' . preg_replace('/\.\w+$/', '_thumb.' . $ext, $name), THUMB_MAX_SIDE, $hasAlpha);
    if (PHP_VERSION_ID < 80000) { imagedestroy($src); }

    return $name;
}

function image_fix_orientation($img, string $path)
{
    $exif = @exif_read_data($path);
    if (!$exif || empty($exif['Orientation'])) {
        return $img;
    }
    switch ((int) $exif['Orientation']) {
        case 3:
            return imagerotate($img, 180, 0) ?: $img;
        case 6:
            return imagerotate($img, -90, 0) ?: $img;
        case 8:
            return imagerotate($img, 90, 0) ?: $img;
    }
    return $img;
}

function image_save_resized($src, string $dest, int $maxSide, bool $alpha): void
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, $maxSide / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    if ($alpha) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = $alpha ? imagepng($dst, $dest, 7) : imagejpeg($dst, $dest, 84);
    if (PHP_VERSION_ID < 80000) { imagedestroy($dst); }
    if (!$ok) {
        throw new RuntimeException('Resim kaydedilemedi. uploads/ klasörünün yazılabilir olduğundan emin olun.');
    }
}

function delete_upload(string $folder, string $filename): void
{
    if ($filename === '' || strpos($filename, '/') !== false || strpos($filename, '..') !== false) {
        return;
    }
    $base = UPLOAD_PATH . '/' . $folder . '/';
    foreach ([$filename, preg_replace('/(\.\w+)$/', '_thumb$1', $filename)] as $f) {
        if (is_file($base . $f)) {
            @unlink($base . $f);
        }
    }
}

// ---------- Puan hesaplama ----------
function product_rating(int $productId): array
{
    $row = db()->prepare('SELECT COUNT(*) AS cnt, AVG(score) AS avg FROM product_ratings WHERE product_id = ?');
    $row->execute([$productId]);
    $r = $row->fetch() ?: ['cnt' => 0, 'avg' => null];
    return ['count' => (int) $r['cnt'], 'avg' => $r['avg'] !== null ? round((float) $r['avg'], 1) : null];
}

function stars_html(?float $avg, int $size = 14): string
{
    if ($avg === null) {
        return '';
    }
    $full = (int) floor($avg);
    $half = ($avg - $full) >= 0.5 ? 1 : 0;
    $html = '<span class="stars" style="--star-size:' . $size . 'px" aria-label="' . e((string) $avg) . ' / 5">';
    for ($i = 1; $i <= 5; $i++) {
        $cls = $i <= $full ? 'full' : (($i === $full + 1 && $half) ? 'half' : 'off');
        $html .= '<i class="star ' . $cls . '"></i>';
    }
    return $html . '</span>';
}
