<?php
/**
 * Musteri menusu temalari. Her temanin gorunumu assets/css/themes/<ad>.css dosyasindadir;
 * yazi tipleri, tarayici cubugu rengi ve secilebilir vurgu renkleri o dosyanin basindaki "@theme" basligindan okunur.
 */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

const DEFAULT_THEME = 'dark';

/**
 * Tema listesi. accent: 'free' (serbest renk secici) | 'palette' (temanin hazir renkleri) | 'fixed' (renk sabit).
 * background: Ayarlar'daki arka plan fotografini kullanir mi?
 */
function themes(): array
{
    return [
        'clean'   => ['label' => 'Clean',    'accent' => 'free',    'background' => false,
                      'desc' => 'Sade, ferah ve nötr. Beyaz zemin ve bol boşlukla tasarım geri planda kalır, ürünler öne çıkar.',
                      'fit'  => 'Kafe, brunch ve fast-casual mekânlar'],
        'dark'    => ['label' => 'Dark',     'accent' => 'palette', 'background' => true,
                      'desc' => 'Loş ortamlar için koyu zemin, açık metin ve karanlıkta parlayan tek bir vurgu rengi.',
                      'fit'  => 'Bar, pub, burgerci ve gece mekânları'],
        'klasik'  => ['label' => 'Klasik',   'accent' => 'fixed',   'background' => false,
                      'desc' => 'Basılı restoran menüsünün dijital karşılığı: krem kâğıt, serif başlıklar, noktalı fiyat çizgileri.',
                      'fit'  => 'Meyhane, ocakbaşı ve esnaf lokantaları'],
        'bauhaus' => ['label' => 'Bauhaus',  'accent' => 'fixed',   'background' => false,
                      'desc' => 'Grafik ve cesur: beyaz, siyah ve kırmızı; keskin köşeli bloklar, geometrik şekiller ve güçlü bir ızgara.',
                      'fit'  => 'Konsept kafeler ve üçüncü dalga kahveciler'],
        'canli'   => ['label' => 'Canlı',    'accent' => 'fixed',   'background' => false,
                      'desc' => 'Renkli, yuvarlak ve oyunbaz: pastel kategoriler, çıkartma kartlar, yaylanan hareketler.',
                      'fit'  => 'Dondurmacı, bubble tea ve tatlıcılar'],
        'artdeco' => ['label' => 'Art Deco', 'accent' => 'fixed',   'background' => false,
                      'desc' => '1920\'lerin lüks ve simetrik dili: zümrüt zemin, altın çizgiler, yelpaze ve basamak motifleri.',
                      'fit'  => 'Lounge, kokteyl barı ve fine dining'],
    ];
}

/** Gecerli tema. ?tema=<ad> yalnizca o istek icin onizleme saglar; gecersiz degerler yok sayilir. */
function current_theme(): string
{
    $preview = $_GET['tema'] ?? null;
    if (is_string($preview) && isset(themes()[$preview])) {
        return $preview;
    }
    $saved = setting('theme', DEFAULT_THEME);
    return isset(themes()[$saved]) ? $saved : DEFAULT_THEME;
}

/**
 * Tema dosyasinin basindaki basligi okur:
 *   /* @theme
 *      fonts: Inter:wght@400;600|Playfair+Display:wght@600
 *      theme-color: #111111
 *      accents: #f5c451, #7dd3fc        (free: ilki varsayilan; palette: secenekler, ilki varsayilan)
 *   * /
 * Doner: ['fonts' => string[], 'theme_color' => string, 'accents' => string[]]
 */
function theme_meta(string $theme): array
{
    static $cache = [];
    if (isset($cache[$theme])) {
        return $cache[$theme];
    }
    $meta = ['fonts' => [], 'theme_color' => '#ffffff', 'accents' => []];
    $file = ROOT_PATH . '/assets/css/themes/' . $theme . '.css';
    $head = isset(themes()[$theme]) && is_file($file) ? (string) file_get_contents($file, false, null, 0, 2000) : '';
    if (preg_match('~/\*\s*@theme(.*?)\*/~s', $head, $m)) {
        if (preg_match('/^\s*fonts:\s*(.+)$/m', $m[1], $f)) {
            foreach (explode('|', trim($f[1])) as $font) {
                $font = trim($font);
                if ($font !== '' && preg_match('/^[A-Za-z0-9+:;@,.\-]+$/', $font)) {
                    $meta['fonts'][] = $font;
                }
            }
        }
        if (preg_match('/^\s*theme-color:\s*(#[0-9a-fA-F]{6})\s*$/m', $m[1], $c)) {
            $meta['theme_color'] = strtolower($c[1]);
        }
        if (preg_match('/^\s*accents:\s*(.+)$/m', $m[1], $a) && preg_match_all('/#[0-9a-fA-F]{6}/', $a[1], $all)) {
            $meta['accents'] = array_values(array_unique(array_map('strtolower', $all[0])));
        }
    }
    return $cache[$theme] = $meta;
}

/** Temanin Google Fonts adresi (yazi tipi yoksa bos). */
function theme_fonts_url(string $theme): string
{
    $fonts = theme_meta($theme)['fonts'];
    return $fonts ? 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $fonts) . '&display=swap' : '';
}

/**
 * Kaydedilecek/uygulanacak vurgu rengi. Sabit renkli temada null (tema kendi rengini kullanir);
 * paletli temada palet disi renk temanin varsayilanina cekilir; serbest temada gecerli her renk kabul edilir.
 */
function theme_accent(string $theme, string $color): ?string
{
    $mode = themes()[$theme]['accent'] ?? 'fixed';
    $accents = theme_meta($theme)['accents'];
    $color = strtolower($color);
    $valid = preg_match('/^#[0-9a-f]{6}$/', $color) === 1;
    if ($mode === 'fixed') {
        return null;
    }
    if ($mode === 'palette') {
        return $valid && in_array($color, $accents, true) ? $color : ($accents[0] ?? null);
    }
    return $valid ? $color : ($accents[0] ?? null);
}

/** Vurgu renginin ustune yazilacak metin rengi (okunabilirlik icin siyah ya da beyaz). */
function contrast_text(string $hex): string
{
    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
    $lin = fn(int $v): float => ($v / 255) <= 0.03928 ? ($v / 255) / 12.92 : ((($v / 255) + 0.055) / 1.055) ** 2.4;
    $lum = 0.2126 * $lin((int) $r) + 0.7152 * $lin((int) $g) + 0.0722 * $lin((int) $b);
    return $lum > 0.36 ? '#111111' : '#ffffff';
}

/** Tema kok elemanina (body ya da admin onizlemesi) yazilacak style degeri: secilebilir vurgu rengi degiskenleri. */
function theme_style_attr(string $theme): string
{
    $accent = theme_accent($theme, setting('primary_color'));
    if ($accent === null) {
        return '';
    }
    [$r, $g, $b] = sscanf($accent, '#%02x%02x%02x');
    return '--accent:' . $accent . ';--accent-rgb:' . (int) $r . ',' . (int) $g . ',' . (int) $b . ';--on-accent:' . contrast_text($accent);
}
