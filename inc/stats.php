<?php
/** Ziyaret ve tiklama sayimi. Sayim sirasindaki hatalar musteri sayfasini etkilemez. */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

const STATS_BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|monitor|uptime|lighthouse|headless|curl|wget|python|scrapy|httpclient|okhttp/i';

/** Bu istek sayilacak mi? Botlar, GET disi istekler ve admin girisi yapmis tarayici sayilmaz. */
function stats_should_count(): bool
{
    static $count = null;
    if ($count !== null) {
        return $count;
    }
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || $ua === '' || preg_match(STATS_BOT_PATTERN, $ua)) {
        return $count = false;
    }
    // Oturum cerezi yoksa admin olamaz; her ziyaretciye bosuna oturum acmamak icin once cereze bakilir.
    if (isset($_COOKIE['menusess'])) {
        start_session();
        if (!empty($_SESSION['admin'])) {
            return $count = false;
        }
    }
    return $count = true;
}

/** Ziyaretci ozetini uretmek icin kuruluma ozel rastgele anahtar. */
function stats_salt(): string
{
    $salt = setting('stats_salt');
    if ($salt === '') {
        $salt = bin2hex(random_bytes(16));
        setting_save('stats_salt', $salt);
    }
    return $salt;
}

/**
 * Sayfa goruntulemeyi ve (bugun ilk kez geliyorsa) ziyaretciyi sayar.
 * "Bugun geldi mi" kontrolu icin IP + tarayici bilgisinin geri cozulemeyen ozeti tutulur; onceki gunlerin ozetleri silinir.
 */
function track_visit(): void
{
    if (!stats_should_count()) {
        return;
    }
    try {
        $pdo = db();
        $day = date('Y-m-d');
        $hash = substr(hash_hmac('sha256', $day . '|' . client_ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''), stats_salt()), 0, 32);
        $pdo->prepare('INSERT OR IGNORE INTO stats_daily (day) VALUES (?)')->execute([$day]);
        // Tarayici bilgisini degistirerek tabloyu sisirmek mumkun olmasin diye gunluk ust sinir vardir
        $known = $pdo->prepare('SELECT 1 FROM stats_seen WHERE day = ? AND hash = ?');
        $known->execute([$day, $hash]);
        $isNew = false;
        if (!$known->fetchColumn()) {
            $total = $pdo->prepare('SELECT COUNT(*) FROM stats_seen WHERE day = ?');
            $total->execute([$day]);
            if ((int) $total->fetchColumn() < STATS_MAX_VISITORS_PER_DAY) {
                $seen = $pdo->prepare('INSERT OR IGNORE INTO stats_seen (day, hash) VALUES (?, ?)');
                $seen->execute([$day, $hash]);
                $isNew = $seen->rowCount() === 1;
            }
        }
        $pdo->prepare('UPDATE stats_daily SET pageviews = pageviews + 1, visitors = visitors + ? WHERE day = ?')->execute([$isNew ? 1 : 0, $day]);
        if ($isNew) {
            $pdo->prepare('DELETE FROM stats_seen WHERE day < ?')->execute([$day]);
        }
    } catch (Throwable $t) {
        error_log('Istatistik (ziyaret) hatasi: ' . $t->getMessage());
    }
}

/** Urun veya kategori sayfasinin acilisini sayar. $type: 'product' | 'category' */
function track_view(string $type, int $id): void
{
    if ($id <= 0 || !in_array($type, ['product', 'category'], true) || !stats_should_count()) {
        return;
    }
    try {
        $pdo = db();
        $day = date('Y-m-d');
        $pdo->prepare('INSERT OR IGNORE INTO stats_views (day, type, item_id) VALUES (?, ?, ?)')->execute([$day, $type, $id]);
        $pdo->prepare('UPDATE stats_views SET views = views + 1 WHERE day = ? AND type = ? AND item_id = ?')->execute([$day, $type, $id]);
    } catch (Throwable $t) {
        error_log('Istatistik (tiklama) hatasi: ' . $t->getMessage());
    }
}
