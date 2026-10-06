<?php
/** Isletme bilgileri: calisma saatleri, acik/kapali durumu ve iletisim baglantilari */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

const DAY_NAMES = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];

/** @param mixed $value */
function is_time($value): bool
{
    return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
}

/**
 * Gun (1=Pazartesi .. 7=Pazar) => [acilis, kapanis] veya null (kapali).
 * Kapanis acilistan kucuk/esitse ertesi gune sarkar (orn. 10:00-02:00); esitse 24 saat aciktir.
 * Hic saat ayarlanmamissa bos dizi doner.
 */
function business_hours(): array
{
    $raw = json_decode(setting('hours'), true);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    for ($d = 1; $d <= 7; $d++) {
        $r = $raw[$d] ?? null;
        $out[$d] = (is_array($r) && count($r) === 2 && is_time($r[0] ?? null) && is_time($r[1] ?? null)) ? [$r[0], $r[1]] : null;
    }
    return array_filter($out) ? $out : [];
}

/** Ayni saatlere sahip ardisik gunleri birlestirir: [['from' => 1, 'to' => 5, 'hours' => [acilis, kapanis]|null], ...] */
function business_hours_grouped(): array
{
    $groups = [];
    foreach (business_hours() as $day => $h) {
        $last = count($groups) - 1;
        if ($last >= 0 && $groups[$last]['hours'] === $h) {
            $groups[$last]['to'] = $day;
        } else {
            $groups[] = ['from' => $day, 'to' => $day, 'hours' => $h];
        }
    }
    return $groups;
}

/** Su anki durum: ['open' => bool, 'text' => string]. Calisma saati ayarlanmamissa null. */
function open_status(?DateTimeImmutable $now = null): ?array
{
    $hours = business_hours();
    if (!$hours) {
        return null;
    }
    $now = $now ?? new DateTimeImmutable('now');
    $day = (int) $now->format('N');
    $time = $now->format('H:i');
    $prev = $day === 1 ? 7 : $day - 1;

    $range = null;   // su an icinde bulundugumuz mesai
    $y = $hours[$prev];
    $t = $hours[$day];
    if ($y !== null && $y[1] <= $y[0] && $time < $y[1]) {
        $range = $y;                                        // dunden sarkan gece mesaisi
    } elseif ($t !== null && $time >= $t[0] && ($t[1] <= $t[0] || $time < $t[1])) {
        $range = $t;
    }
    if ($range !== null) {
        return ['open' => true, 'text' => $range[0] === $range[1] ? t('Şu an açık · 24 saat') : t('Şu an açık · Kapanış %s', $range[1])];
    }

    if ($t !== null && $time < $t[0]) {
        return ['open' => false, 'text' => t('Şu an kapalı · Açılış bugün %s', $t[0])];
    }
    for ($i = 1; $i <= 7; $i++) {
        $d = (($day - 1 + $i) % 7) + 1;
        if ($hours[$d] !== null) {
            return ['open' => false, 'text' => $i === 1 ? t('Şu an kapalı · Açılış yarın %s', $hours[$d][0]) : t('Şu an kapalı · Açılış %s %s', t(DAY_NAMES[$d]), $hours[$d][0])];
        }
    }
    return ['open' => false, 'text' => t('Şu an kapalı')];
}

function tel_href(string $phone): string
{
    $n = (string) preg_replace('/[^\d+]/', '', $phone);
    return $n !== '' ? 'tel:' . $n : '';
}

/** 05xx / 5xx ile yazilan Turkiye numaralarini 90 ile baslayan uluslararasi bicime cevirir. */
function whatsapp_href(string $number): string
{
    $n = (string) preg_replace('/\D/', '', $number);
    if (strlen($n) === 11 && $n[0] === '0') {
        $n = '9' . $n;
    } elseif (strlen($n) === 10 && $n[0] === '5') {
        $n = '90' . $n;
    }
    return strlen($n) >= 10 ? 'https://wa.me/' . $n : '';
}

/** Harita baglantisi girilmisse o, yoksa adresten uretilen Google Haritalar aramasi. */
function maps_href(): string
{
    $custom = setting('maps_url');
    if ($custom !== '' && preg_match('#^https?://[^\s"<>]+$#i', $custom)) {
        return $custom;   // kayitta da dogrulanir; geri yuklenen bir veritabanina karsi ciktida tekrar bakilir
    }
    $address = setting('address');
    return $address !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address) : '';
}

/**
 * Bilgilendirme sayfasinda (yasal.php) gosterilen KVKK metni.
 * Ayarlardan hic kaydedilmediyse ornek metin doner; admin bos kaydederse gosterilmez.
 */
function kvkk_text(): string
{
    $all = settings();
    if (array_key_exists('kvkk_text', $all)) {
        return $all['kvkk_text'];
    }
    return "Değerlendirme formunda paylaştığınız yorum, puan ve (yazdıysanız) isim bilgisi ile bağlantı sırasında oluşan IP adresiniz; "
         . "hizmet kalitemizi ölçmek, geri bildirimlerinizi değerlendirmek ve kötüye kullanımı önlemek amacıyla işletmemiz tarafından işlenir. "
         . "Bu bilgiler yalnızca işletme yetkilileri tarafından görülür ve üçüncü kişilerle paylaşılmaz.\n\n"
         . "6698 sayılı Kişisel Verilerin Korunması Kanunu'nun 11. maddesi kapsamındaki haklarınızı (verilerinize erişme, "
         . "düzeltilmesini veya silinmesini isteme gibi) kullanmak için işletmemize başvurabilirsiniz.";
}

/** Ust bardaki bilgilendirme (i) sayfasinda gosterilecek bir metin var mi? */
function has_legal_info(): bool
{
    return setting('info_notes') !== '' || kvkk_text() !== '';
}

function has_business_info(): bool
{
    foreach (['phone', 'whatsapp', 'address', 'maps_url', 'instagram', 'wifi_name', 'wifi_password'] as $key) {
        if (setting($key) !== '') {
            return true;
        }
    }
    return (bool) business_hours();
}
