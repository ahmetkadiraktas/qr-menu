<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/info.php';
require_once __DIR__ . '/../inc/backup.php';
require_once __DIR__ . '/../inc/themes.php';
require_admin();

const SETTINGS_TABS = [
    'genel'         => 'Genel',
    'tema'          => 'Tema',
    'iletisim'      => 'İletişim',
    'saatler'       => 'Çalışma Saatleri',
    'gorunum'       => 'Görünüm',
    'bilgilendirme' => 'Bilgilendirme',
    'guvenlik'      => 'Güvenlik ve Yedek',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'site') {
            $name = mb_substr(post('site_name'), 0, 80);
            if ($name === '') {
                throw new RuntimeException('Restoran adı boş olamaz.');
            }
            $mapsUrl = mb_substr(post('maps_url'), 0, 500);
            if ($mapsUrl !== '' && !preg_match('#^https?://[^\s"<>]+$#i', $mapsUrl)) {
                throw new RuntimeException('Harita bağlantısı http:// veya https:// ile başlamalı.');
            }
            $hoursIn = isset($_POST['hours']) && is_array($_POST['hours']) ? $_POST['hours'] : [];
            $hours = [];
            for ($d = 1; $d <= 7; $d++) {
                $row = isset($hoursIn[$d]) && is_array($hoursIn[$d]) ? $hoursIn[$d] : [];
                $open = $row['open'] ?? '';
                $close = $row['close'] ?? '';
                $hours[$d] = (isset($row['on']) && is_time($open) && is_time($close)) ? [$open, $close] : null;
            }
            // Tema ve vurgu rengi: renk kurali temaya gore degisir (serbest / hazir palet / sabit)
            $themeIn = post('theme', current_theme());
            if (!isset(themes()[$themeIn])) {
                throw new RuntimeException('Geçerli bir tema seçin.');
            }
            $accentMode = themes()[$themeIn]['accent'];
            $accentIn = $accentMode === 'free' ? post('accent_free') : ($accentMode === 'palette' ? post('accent_' . $themeIn) : '');
            $accent = theme_accent($themeIn, $accentIn);
            setting_save('site_name', $name);
            setting_save('site_tagline', mb_substr(post('site_tagline'), 0, 120));
            setting_save('site_tagline_en', mb_substr(post('site_tagline_en'), 0, 120));
            setting_save('theme', $themeIn);
            if ($accent !== null) {
                setting_save('primary_color', $accent);   // sabit renkli temada mevcut deger korunur
            }
            setting_save('phone', mb_substr(post('phone'), 0, 40));
            setting_save('address', mb_substr(post('address'), 0, 200));
            setting_save('instagram', mb_substr(ltrim(post('instagram'), '@'), 0, 60));
            setting_save('whatsapp', mb_substr(post('whatsapp'), 0, 40));
            setting_save('maps_url', $mapsUrl);
            setting_save('wifi_name', mb_substr(post('wifi_name'), 0, 60));
            setting_save('wifi_password', mb_substr(post('wifi_password'), 0, 60));
            setting_save('info_notes', mb_substr(post('info_notes'), 0, 1000));
            setting_save('info_notes_en', mb_substr(post('info_notes_en'), 0, 1000));
            setting_save('kvkk_text', mb_substr(post('kvkk_text'), 0, 4000));
            setting_save('kvkk_text_en', mb_substr(post('kvkk_text_en'), 0, 4000));
            setting_save('hours', (string) json_encode($hours));
            setting_save('show_prices', isset($_POST['show_prices']) ? '1' : '0');
            setting_save('currency', mb_substr(post('currency', '₺'), 0, 6) ?: '₺');

            foreach (['logo', 'background'] as $key) {
                $current = setting($key);
                if (isset($_POST['remove_' . $key]) && $current !== '') {
                    delete_upload('site', $current);
                    setting_save($key, '');
                    $current = '';
                }
                if (!empty($_FILES[$key]['name'])) {
                    $fn = upload_image($_FILES[$key], 'site');
                    if ($current !== '') {
                        delete_upload('site', $current);
                    }
                    setting_save($key, $fn);
                }
            }
            flash('success', 'Ayarlar kaydedildi.');
        } elseif ($action === 'password') {
            $cur = post('current');
            $new = post('new');
            $new2 = post('new2');
            if (!password_verify($cur, setting('admin_password_hash'))) {
                throw new RuntimeException('Mevcut şifre hatalı.');
            }
            if (mb_strlen($new) < 8) {
                throw new RuntimeException('Yeni şifre en az 8 karakter olmalı.');
            }
            if ($new !== $new2) {
                throw new RuntimeException('Yeni şifreler eşleşmiyor.');
            }
            setting_save('admin_password_hash', password_hash($new, PASSWORD_DEFAULT));
            // Oturum donemi degisir: bu tarayici disindaki tum acik admin oturumlari gecersiz olur
            $epoch = bin2hex(random_bytes(8));
            setting_save('session_epoch', $epoch);
            $_SESSION['epoch'] = $epoch;
            flash('success', 'Şifre değiştirildi. Diğer cihazlardaki oturumlar kapatıldı.');
        }
    } catch (Throwable $t) {
        flash_exception($t);
    }
    // Kayittan sonra ayni sekmede kalinir
    $tab = post('tab');
    redirect('admin/ayarlar.php' . (isset(SETTINGS_TABS[$tab]) ? '#' . $tab : ''));
}

$pageTitle = 'Ayarlar';
$activeMenu = 'ayarlar';
require __DIR__ . '/../inc/admin_top.php';
?>
<div class="page-head"><h1>Ayarlar</h1></div>

<nav class="tabs" aria-label="Ayar bölümleri">
    <?php foreach (SETTINGS_TABS as $key => $label): ?>
        <a class="tab" href="#<?= e($key) ?>" data-tab="<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<form method="post" enctype="multipart/form-data" class="settings">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="site">
    <input type="hidden" name="tab" value="genel" data-tab-field>

    <section class="card" id="genel" data-panel="genel">
        <h2>Genel</h2>
        <div class="form-row"><label>Restoran adı *</label><input type="text" name="site_name" required maxlength="80" value="<?= e(setting('site_name')) ?>"></div>
        <div class="form-grid">
            <div class="form-row"><label>Slogan</label><input type="text" name="site_tagline" maxlength="120" value="<?= e(setting('site_tagline')) ?>" placeholder="Ana sayfada başlığın altında"></div>
            <div class="form-row"><label>Slogan <span class="muted">(İngilizce, isteğe bağlı)</span></label><input type="text" name="site_tagline_en" maxlength="120" value="<?= e(setting('site_tagline_en')) ?>"></div>
        </div>
        <div class="form-grid">
            <div class="form-row"><label>Para birimi</label><input type="text" name="currency" maxlength="6" value="<?= e(setting('currency', '₺')) ?>"></div>
            <div class="form-row"><label>&nbsp;</label><label class="check"><input type="checkbox" name="show_prices" <?= setting('show_prices', '1') === '1' ? 'checked' : '' ?>> Fiyatları göster</label></div>
        </div>
    </section>

    <section class="card" id="tema" data-panel="tema">
        <h2>Tema</h2>
        <p class="section-lead">Müşteri menüsünün görünümü. Yönetim paneli bundan etkilenmez; yalnızca Menü sayfasındaki önizleme seçtiğiniz temayı gösterir.</p>
        <?php $savedTheme = isset(themes()[setting('theme', DEFAULT_THEME)]) ? setting('theme', DEFAULT_THEME) : DEFAULT_THEME; $savedColor = strtolower(setting('primary_color')); ?>
        <div class="theme-grid">
            <?php foreach (themes() as $tid => $t): ?>
                <label class="theme-card">
                    <input type="radio" name="theme" value="<?= e($tid) ?>" <?= $tid === $savedTheme ? 'checked' : '' ?> data-theme-radio>
                    <span class="theme-card-body">
                        <span class="theme-shots">
                            <?php foreach ([1 => 'Kategoriler', 2 => 'Kategorinin içi'] as $n => $alt): $shot = 'assets/img/themes/' . $tid . '-' . $n . '.jpg'; ?>
                                <?php if (is_file(ROOT_PATH . '/' . $shot)): ?>
                                    <img src="<?= e(url($shot)) ?>?v=<?= (int) filemtime(ROOT_PATH . '/' . $shot) ?>" alt="<?= e($t['label'] . ' teması: ' . $alt) ?>" loading="lazy" width="390" height="844">
                                <?php else: ?>
                                    <span class="theme-shot-ph" aria-hidden="true"></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </span>
                        <span class="theme-name"><?= e($t['label']) ?><span class="status on theme-selected">Seçili</span></span>
                        <span class="theme-desc"><?= e($t['desc']) ?></span>
                        <span class="theme-fit"><?= e($t['fit']) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="form-row" style="margin-top:22px">
            <label>Vurgu rengi</label>
            <?php foreach (themes() as $tid => $t): $accents = theme_meta($tid)['accents']; $cur = theme_accent($tid, $savedColor); ?>
                <div class="accent-panel" data-accent-for="<?= e($tid) ?>">
                    <?php if ($t['accent'] === 'free'): ?>
                        <div class="inline"><input type="color" name="accent_free" value="<?= e($cur ?? '#2563eb') ?>" aria-label="<?= e($t['label']) ?> vurgu rengi"><span class="muted"><?= e($t['label']) ?>: istediğiniz rengi seçin. Fiyatlarda, seçili sekmede ve düğmelerde kullanılır.</span></div>
                    <?php elseif ($t['accent'] === 'palette' && $accents): ?>
                        <div class="swatches" role="radiogroup" aria-label="<?= e($t['label']) ?> vurgu rengi">
                            <?php foreach ($accents as $hex): ?>
                                <label class="swatch" title="<?= e($hex) ?>"><input type="radio" name="accent_<?= e($tid) ?>" value="<?= e($hex) ?>" <?= $cur === $hex ? 'checked' : '' ?>><span style="background:<?= e($hex) ?>"></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="hint"><?= e($t['label']) ?> temasının hazır renklerinden birini seçin.</div>
                    <?php else: ?>
                        <div class="hint"><?= e($t['label']) ?> temasının renkleri sabittir; renk uyumu temanın kimliğidir.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="hint muted">Kaydetmeden görmek için: <?php foreach (themes() as $tid => $t): ?><a href="<?= e(url('?tema=' . $tid)) ?>" target="_blank" rel="noopener"><?= e($t['label']) ?> ↗</a> <?php endforeach; ?></div>
    </section>

    <section class="card" id="iletisim" data-panel="iletisim">
        <h2>İletişim</h2>
        <p class="section-lead">Müşteri menüsünde alt bardaki işletme sayfasında görünür. Boş bıraktığınız alan gösterilmez.</p>
        <div class="form-grid">
            <div class="form-row"><label>Telefon</label><input type="text" name="phone" maxlength="40" value="<?= e(setting('phone')) ?>"></div>
            <div class="form-row"><label>WhatsApp numarası</label><input type="text" name="whatsapp" maxlength="40" value="<?= e(setting('whatsapp')) ?>" placeholder="05xx xxx xx xx"></div>
        </div>
        <div class="form-row"><label>Adres</label><input type="text" name="address" maxlength="200" value="<?= e(setting('address')) ?>"></div>
        <div class="form-row"><label>Harita bağlantısı</label><input type="url" name="maps_url" maxlength="500" value="<?= e(setting('maps_url')) ?>" placeholder="https://maps.app.goo.gl/..."><div class="hint">Google Haritalar'da işletmenizi açıp "Paylaş" ile kopyalayın. Boş bırakılırsa "Yol tarifi" adresten üretilir.</div></div>
        <div class="form-row"><label>Instagram kullanıcı adı</label><input type="text" name="instagram" maxlength="60" value="<?= e(setting('instagram')) ?>" placeholder="kullaniciadi"></div>
        <div class="form-grid">
            <div class="form-row"><label>Wi-Fi ağ adı</label><input type="text" name="wifi_name" maxlength="60" value="<?= e(setting('wifi_name')) ?>"></div>
            <div class="form-row"><label>Wi-Fi şifresi</label><input type="text" name="wifi_password" maxlength="60" value="<?= e(setting('wifi_password')) ?>" autocomplete="off"></div>
        </div>
        <div class="hint muted">Wi-Fi şifresi menüyü açan herkese görünür; yalnızca misafir ağının şifresini yazın.</div>
    </section>

    <section class="card" id="saatler" data-panel="saatler">
        <h2>Çalışma Saatleri</h2>
        <?php $hours = business_hours(); foreach (DAY_NAMES as $d => $dayName): $h = $hours[$d] ?? null; ?>
            <div class="hours-row">
                <label class="check"><input type="checkbox" name="hours[<?= $d ?>][on]" <?= $h ? 'checked' : '' ?>> <?= e($dayName) ?></label>
                <input type="time" name="hours[<?= $d ?>][open]" value="<?= e($h[0] ?? '09:00') ?>" aria-label="<?= e($dayName) ?> açılış">
                <span class="muted">–</span>
                <input type="time" name="hours[<?= $d ?>][close]" value="<?= e($h[1] ?? '22:00') ?>" aria-label="<?= e($dayName) ?> kapanış">
            </div>
        <?php endforeach; ?>
        <div class="hint muted" style="font-size:12px;margin-top:8px">İşaretsiz günler kapalı sayılır. Gece yarısını geçiyorsanız kapanışı ertesi günün saatiyle yazın (örn. 10:00 – 02:00); açılış ve kapanış aynıysa "24 saat açık" sayılır. Hiçbir gün işaretli değilse menüde açık/kapalı bilgisi gösterilmez.</div>
    </section>

    <section class="card" id="gorunum" data-panel="gorunum">
        <h2>Görünüm</h2>
        <div class="form-row">
            <label>Logo</label>
            <?php if (setting('logo') !== ''): ?><img class="site-img-preview" src="<?= e(upload_url('site', setting('logo'), true)) ?>" alt=""><label class="check" style="margin-bottom:8px"><input type="checkbox" name="remove_logo"> Logoyu kaldır</label><?php endif; ?>
            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-preview="logoPreview"><div class="preview-strip" id="logoPreview"></div>
            <div class="hint">Şeffaf PNG önerilir. Yoksa restoran adı yazı olarak gösterilir.</div>
        </div>
        <div class="form-row">
            <label>Arka plan fotoğrafı</label>
            <?php if (setting('background') !== ''): ?><img class="site-img-preview" src="<?= e(upload_url('site', setting('background'), true)) ?>" alt=""><label class="check" style="margin-bottom:8px"><input type="checkbox" name="remove_background"> Arka planı kaldır</label><?php endif; ?>
            <input type="file" name="background" accept="image/jpeg,image/png,image/webp" data-preview="bgPreview"><div class="preview-strip" id="bgPreview"></div>
            <div class="hint">Yalnızca arka plan fotoğrafı kullanan temada (Dark) görünür: tüm sayfalarda koyu bir katmanla arkada durur. Diğer temalarda yok sayılır.</div>
        </div>
    </section>

    <section class="card" id="bilgilendirme" data-panel="bilgilendirme">
        <h2>Bilgilendirme</h2>
        <p class="section-lead">Müşteri menüsünün sağ üstündeki ⓘ sayfasında görünür.</p>
        <div class="form-row">
            <label>Notlar</label>
            <textarea name="info_notes" maxlength="1000" style="min-height:90px" placeholder="Örn. Fiyatlarımıza KDV dahildir. Servis ücreti alınmaz."><?= e(setting('info_notes')) ?></textarea>
            <div class="hint">Sayfanın en üstünde görünür. Boş bırakılırsa gösterilmez.</div>
            <textarea name="info_notes_en" maxlength="1000" style="min-height:70px;margin-top:8px" placeholder="İngilizce notlar (isteğe bağlı; boşsa Türkçesi gösterilir)"><?= e(setting('info_notes_en')) ?></textarea>
        </div>
        <div class="form-row">
            <label>KVKK aydınlatma metni</label>
            <textarea name="kvkk_text" maxlength="4000" style="min-height:190px"><?= e(kvkk_text()) ?></textarea>
            <div class="hint">Notların altında görünür; değerlendirme formundan da bağlantı verilir. Bu bir örnek metindir, kendi işletmenize göre düzenleyin. Boş bırakılırsa gösterilmez.</div>
            <textarea name="kvkk_text_en" maxlength="4000" style="min-height:100px;margin-top:8px" placeholder="İngilizce KVKK metni (isteğe bağlı; boşsa Türkçesi gösterilir)"><?= e(setting('kvkk_text_en')) ?></textarea>
        </div>
    </section>

    <div class="save-bar" data-save-bar>
        <span class="muted">Tüm sekmelerdeki değişiklikler birlikte kaydedilir.</span>
        <button class="btn btn-primary" type="submit">Ayarları Kaydet</button>
    </div>
</form>

<div class="settings" id="guvenlik" data-panel="guvenlik">
    <section class="card">
        <h2>Admin Şifresi</h2>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="tab" value="guvenlik">
            <div class="form-row"><label>Mevcut şifre</label><input type="password" name="current" required autocomplete="current-password"></div>
            <div class="form-grid">
                <div class="form-row"><label>Yeni şifre</label><input type="password" name="new" required minlength="8" autocomplete="new-password"></div>
                <div class="form-row"><label>Yeni şifre (tekrar)</label><input type="password" name="new2" required minlength="8" autocomplete="new-password"></div>
            </div>
            <button class="btn" type="submit">Şifreyi Değiştir</button>
        </form>
    </section>

    <section class="card">
        <h2>Yedek</h2>
        <p class="section-lead">Tüm verileri (ürünler, kategoriler, yorumlar, kampanyalar, ayarlar) ve resimleri tek bir zip dosyası olarak indirir. Yaklaşık boyut: <b><?= e(format_bytes(backup_size())) ?></b></p>
        <form method="post" action="<?= e(url('admin/yedek.php')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">Yedeği indir</button>
        </form>
        <div class="hint muted" style="margin-top:10px;font-size:12px">Geri yükleme adımları zip içindeki <code>YEDEK-OKU.txt</code> dosyasında yazar. Yedekte admin şifresi bulunmaz; geri yüklerken yeni şifre belirlenir. Yedek müşteri yorumlarını içerir, güvenli bir yerde saklayın.</div>
    </section>
</div>
<?php require __DIR__ . '/../inc/admin_bottom.php'; ?>
