<?php
/** Dil destegi (Turkce / Ingilizce). Sabit yazilar t() ile, urun/kategori alanlari localize() ile cevrilir. */
declare(strict_types=1);

const LANGS = ['tr', 'en'];

/**
 * Gecerli dil. Oncelik: ?lang= parametresi (cereze de yazilir) > cerez > tarayici dili (Turkce degilse Ingilizce).
 * $force verilirse o dil sabitlenir (admin paneli her zaman Turkcedir).
 */
function current_lang(?string $force = null): string
{
    static $lang = null;
    if ($force !== null && in_array($force, LANGS, true)) {
        $lang = $force;
    }
    if ($lang !== null) {
        return $lang;
    }
    $requested = $_GET['lang'] ?? null;
    if (is_string($requested) && in_array($requested, LANGS, true)) {
        $lang = $requested;
        if (!headers_sent()) {
            setcookie('lang', $lang, [
                'expires'  => time() + 365 * 24 * 3600,
                'path'     => '/',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return $lang;
    }
    $cookie = $_COOKIE['lang'] ?? null;
    if (is_string($cookie) && in_array($cookie, LANGS, true)) {
        return $lang = $cookie;
    }
    $accept = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')));
    return $lang = ($accept === '' || strpos($accept, 'tr') === 0) ? 'tr' : 'en';
}

/** Sabit bir Turkce metni gecerli dile cevirir. Ek argumanlar sprintf ile yerlestirilir. */
function t(string $text, ...$args): string
{
    if (current_lang() === 'en') {
        $text = translations_en()[$text] ?? $text;
    }
    return $args ? vsprintf($text, $args) : $text;
}

/** Ingilizce modda, Ingilizcesi girilmis alanlari (alan_en) asil alanin yerine koyar; bos olanlar Turkce kalir. */
function localize(array $row, string ...$fields): array
{
    if (current_lang() !== 'en') {
        return $row;
    }
    foreach ($fields as $field) {
        $en = $row[$field . '_en'] ?? '';
        if (is_string($en) && trim($en) !== '') {
            $row[$field] = $en;
        }
    }
    return $row;
}

/** Ayni sayfanin diger dildeki adresi. */
function lang_switch_url(): string
{
    $query = $_GET;
    $query['lang'] = current_lang() === 'en' ? 'tr' : 'en';
    return (string) ($_SERVER['SCRIPT_NAME'] ?? url('')) . '?' . http_build_query($query);
}

function translations_en(): array
{
    return [
        // Genel
        'Geri' => 'Back',
        'Kampanyalar' => 'Offers',
        'Kapat' => 'Close',
        'Bilgilendirme' => 'Information',
        'Menü' => 'Menu',
        'Ara' => 'Search',
        'Değerlendir' => 'Review',
        'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.' => 'Session verification failed. Please refresh the page and try again.',
        // Ana sayfa, kategori, arama
        'Henüz menü eklenmemiş.' => 'The menu is not available yet.',
        '%d ürün' => '%d item(s)',
        'Öne Çıkanlar' => 'Featured',
        'Menümüzden seçmeler' => 'Picks from our menu',
        'Kategori bulunamadı' => 'Category not found',
        'Kategori bulunamadı.' => 'Category not found.',
        'Filtrele' => 'Filter',
        'Temizle' => 'Clear',
        'Bu kategoride henüz ürün yok.' => 'No items in this category yet.',
        'Seçtiğiniz filtrelere uyan ürün yok.' => 'No items match your filters.',
        'Ürün adı, malzeme veya özellik...' => 'Dish name, ingredient or feature...',
        'Aramak istediğiniz ürünü yazın.' => 'Type what you are looking for.',
        '"%s" için sonuç bulunamadı.' => 'No results for "%s".',
        'Sonuçlar' => 'Results',
        // Urun
        'Tükendi' => 'Sold out',
        'Öne Çıkan' => 'Featured',
        'Ürün bulunamadı' => 'Item not found',
        'Ürün bulunamadı.' => 'Item not found.',
        'Önceki' => 'Previous',
        'Sonraki' => 'Next',
        '%d değerlendirme' => '%d review(s)',
        'Bu ürün şu an için tükenmiştir.' => 'This item is currently sold out.',
        'Detaylar' => 'Details',
        'Seçenekler' => 'Options',
        '%d dk' => '%d min',
        'İçerir (alerjenler)' => 'Contains (allergens)',
        'Alerjiniz varsa lütfen sipariş öncesinde personele danışın.' => 'If you have an allergy, please ask our staff before ordering.',
        'Bu ürünü puanlayın' => 'Rate this item',
        'Puanınız' => 'Your rating',
        'Puanı gönder' => 'Submit rating',
        'Lütfen bir puan seçin.' => 'Please choose a rating.',
        'Puanınız kaydedildi, teşekkürler!' => 'Thanks, your rating has been saved!',
        'Fiyat belirtilmedi' => 'Price not listed',
        'Restoranımızı puanlayın ve düşüncelerinizi paylaşın. Yorumlar yalnızca işletme tarafından görülür. Tek bir ürünü puanlamak için o ürünün sayfasını kullanabilirsiniz.' => 'Rate our restaurant and share your thoughts. Reviews are only seen by the restaurant. To rate a single dish, use that item\'s page.',
        'Kategoriye dön' => 'Back to category',
        // Rozet, diyet, alerjen
        'Yeni' => 'New',
        'Popüler' => 'Popular',
        'Şefin önerisi' => "Chef's pick",
        'Vejetaryen' => 'Vegetarian',
        'Glütensiz' => 'Gluten-free',
        'Laktozsuz' => 'Lactose-free',
        'Acılı' => 'Spicy',
        'Süt' => 'Milk',
        'Yumurta' => 'Egg',
        'Kuruyemiş' => 'Tree nuts',
        'Yer fıstığı' => 'Peanut',
        'Susam' => 'Sesame',
        'Balık' => 'Fish',
        'Deniz ürünü' => 'Shellfish',
        'Soya' => 'Soy',
        // Degerlendirme
        'Değerlendirme' => 'Review',
        'Geçersiz gönderim.' => 'Invalid submission.',
        'Yorumunuz en az 5 karakter olmalı.' => 'Your comment must be at least 5 characters.',
        'Lütfen tüm kriterleri puanlayın.' => 'Please rate all criteria.',
        'Kısa süre önce bir değerlendirme gönderdiniz. Lütfen biraz sonra tekrar deneyin.' => 'You have recently submitted a review. Please try again later.',
        'Bir hata oluştu, lütfen tekrar deneyin.' => 'Something went wrong, please try again.',
        'Şu anda çok fazla değerlendirme alıyoruz. Lütfen daha sonra tekrar deneyin.' => 'We are receiving too many reviews right now. Please try again later.',
        'Teşekkür ederiz!' => 'Thank you!',
        'Görüşleriniz bizim için çok değerli. Yorumunuz işletmemize iletildi.' => 'Your feedback means a lot to us. Your review has been sent to the restaurant.',
        'Menüye dön' => 'Back to menu',
        'Bizi değerlendirin' => 'Rate us',
        '%d yıldız' => '%d stars',
        '(isteğe bağlı)' => '(optional)',
        'Adınız' => 'Your name',
        'Anonim' => 'Anonymous',
        'Yorumunuz' => 'Your comment',
        'Neyi beğendiniz, neyi geliştirebiliriz?' => 'What did you like, what could we improve?',
        'Gönder' => 'Send',
        'Gönderdiğiniz bilgiler kişisel verilerin korunması kapsamında işlenir.' => 'The information you submit is processed in accordance with personal data protection law.',
        'Aydınlatma metni' => 'Privacy notice',
        'Lezzet' => 'Taste',
        'Sunum' => 'Presentation',
        'Servis' => 'Service',
        'Temizlik' => 'Cleanliness',
        'Fiyat / Performans' => 'Value for money',
        // Isletme bilgileri
        'Bilgi' => 'Info',
        'Yol tarifi' => 'Directions',
        'Ağ adı' => 'Network',
        'Şifre' => 'Password',
        'Kopyala' => 'Copy',
        'Kopyalandı' => 'Copied',
        'İletişim' => 'Contact',
        'Adres' => 'Address',
        'Telefon' => 'Phone',
        'Mesaj gönder' => 'Send a message',
        'Çalışma Saatleri' => 'Opening hours',
        'Kapalı' => 'Closed',
        '24 saat açık' => 'Open 24 hours',
        'Şu an açık · 24 saat' => 'Open now · 24 hours',
        'Şu an açık · Kapanış %s' => 'Open now · Closes %s',
        'Şu an kapalı · Açılış bugün %s' => 'Closed now · Opens today %s',
        'Şu an kapalı · Açılış yarın %s' => 'Closed now · Opens tomorrow %s',
        'Şu an kapalı · Açılış %s %s' => 'Closed now · Opens %s %s',
        'Şu an kapalı' => 'Closed now',
        'Pazartesi' => 'Monday',
        'Salı' => 'Tuesday',
        'Çarşamba' => 'Wednesday',
        'Perşembe' => 'Thursday',
        'Cuma' => 'Friday',
        'Cumartesi' => 'Saturday',
        'Pazar' => 'Sunday',
        'Henüz bilgilendirme eklenmemiş.' => 'No information has been added yet.',
        'Notlar' => 'Notes',
        'Kişisel Verilerin Korunması (KVKK)' => 'Personal Data Protection (KVKK)',
    ];
}
