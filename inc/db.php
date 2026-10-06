<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Degerlendirme formundaki puan kriterleri sabittir (Ingilizce karsiliklari inc/lang.php icinde).
// Asagidaki tablo tanimlari hem yeni kurulumda hem de eski veritabanlarinin guncellenmesinde kullanilir.
const CAMPAIGNS_TABLE_SQL = "CREATE TABLE IF NOT EXISTS campaigns (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT NOT NULL,
    body       TEXT NOT NULL DEFAULT '',
    title_en   TEXT NOT NULL DEFAULT '',
    body_en    TEXT NOT NULL DEFAULT '',
    display    TEXT NOT NULL DEFAULT 'popup',
    image      TEXT NOT NULL DEFAULT '',
    ends_on    TEXT NOT NULL DEFAULT '',
    is_active  INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
)";

// Istatistik tablolari: gunluk ziyaretci/goruntuleme, urun-kategori tiklamalari ve o gunun ziyaretci ozetleri.
const STATS_TABLES_SQL = "
    CREATE TABLE IF NOT EXISTS stats_daily (
        day       TEXT PRIMARY KEY,
        visitors  INTEGER NOT NULL DEFAULT 0,
        pageviews INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS stats_views (
        day     TEXT NOT NULL,
        type    TEXT NOT NULL,
        item_id INTEGER NOT NULL,
        views   INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (day, type, item_id)
    );
    CREATE TABLE IF NOT EXISTS stats_seen (
        day  TEXT NOT NULL,
        hash TEXT NOT NULL,
        PRIMARY KEY (day, hash)
    );
";

// Urun puanlari: musteri urun sayfasindan tek bir 5 uzerinden puan verir (restoran yorumu ayri: reviews tablosu)
const PRODUCT_RATINGS_TABLE_SQL = "CREATE TABLE IF NOT EXISTS product_ratings (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    score      INTEGER NOT NULL CHECK (score BETWEEN 1 AND 5),
    comment    TEXT NOT NULL DEFAULT '',
    name       TEXT NOT NULL DEFAULT '',
    ip         TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL
)";

const RATING_CRITERIA = ['Lezzet', 'Sunum', 'Servis', 'Temizlik', 'Fiyat / Performans'];

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    try {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    } catch (PDOException $e) {
        error_log('DB baglanti hatasi: ' . $e->getMessage());
        http_response_code(500);
        exit('Veritabanina baglanilamadi. data/ klasorunun yazilabilir oldugundan emin olun.');
    }
    db_migrate($pdo);
    return $pdo;
}

/** Onceki surumle kurulmus veritabanlarini guncel yapiya getirir. */
function db_migrate(PDO $pdo): void
{
    try {
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        if ($version >= DB_SCHEMA_VERSION) {
            return;
        }
        $cols = $pdo->query('PRAGMA table_info(products)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!$cols) {
            return;   // henuz kurulmamis; kolonlar db_create_schema ile gelir
        }
        if (!in_array('is_sold_out', $cols, true)) {
            $pdo->exec('ALTER TABLE products ADD COLUMN is_sold_out INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('diet', $cols, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN diet TEXT NOT NULL DEFAULT ''");
        }
        $pdo->exec(CAMPAIGNS_TABLE_SQL);
        // Kampanya gosterim sekli: popup | band (serit)
        if (!in_array('display', $pdo->query('PRAGMA table_info(campaigns)')->fetchAll(PDO::FETCH_COLUMN, 1), true)) {
            $pdo->exec("ALTER TABLE campaigns ADD COLUMN display TEXT NOT NULL DEFAULT 'popup'");
        }
        $pdo->exec(STATS_TABLES_SQL);
        // Porsiyon/secenekler, kalori, hazirlama suresi
        foreach (['variants' => "TEXT NOT NULL DEFAULT ''", 'calories' => 'INTEGER', 'prep_minutes' => 'INTEGER'] as $col => $type) {
            if (!in_array($col, $cols, true)) {
                $pdo->exec("ALTER TABLE products ADD COLUMN $col $type");
            }
        }
        // Ingilizce icerik alanlari
        $catCols = $pdo->query('PRAGMA table_info(categories)')->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach (['products' => ['name_en', 'description_en', 'details_en'], 'categories' => ['name_en', 'description_en']] as $table => $newCols) {
            foreach (array_diff($newCols, $table === 'products' ? $cols : $catCols) as $col) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $col TEXT NOT NULL DEFAULT ''");
            }
        }
        if ($version < 3 && in_array('tags', $cols, true)) {
            if (!function_exists('diet_options')) {
                return;   // functions.php yuklenmeden cagrildi; sonraki istekte tamamlanir
            }
            db_migrate_free_tags($pdo);
        }
        if ($version < 5) {
            db_fix_criteria($pdo);
        }
        $pdo->exec(PRODUCT_RATINGS_TABLE_SQL);
        if ($version < 10) {
            // Uygulama disinda (ornegin elle duzenlenmis bir veritabaninda) yorumu silinmis puanlar ortalamayi bozmasin
            $pdo->exec('DELETE FROM review_scores WHERE review_id NOT IN (SELECT id FROM reviews)');
        }
        $pdo->exec('PRAGMA user_version = ' . DB_SCHEMA_VERSION);
    } catch (PDOException $e) {
        // Ayni anda gelen iki istek kolonu birlikte eklemeye calisabilir; sonraki istekte tekrar denenir.
        error_log('DB guncelleme hatasi: ' . $e->getMessage());
    }
}

/**
 * Serbest etiketler kaldirildi. Adi sabit bir secenekle ayni olan eski etiketler (orn. "Popüler", "Acılı")
 * urunun diet kolonuna tasinir; tags kolonu oldugu gibi birakilir ama artik kullanilmaz.
 */
function db_migrate_free_tags(PDO $pdo): void
{
    $byLabel = [];
    foreach (diet_options() as $key => $opt) {
        $byLabel[mb_strtolower($opt[0])] = $key;
    }
    $rows = $pdo->query("SELECT id, tags, diet FROM products WHERE tags <> ''")->fetchAll();
    $upd = $pdo->prepare('UPDATE products SET diet = ? WHERE id = ?');
    foreach ($rows as $row) {
        $keys = array_map('trim', explode(',', (string) $row['diet']));
        foreach (explode(',', (string) $row['tags']) as $tag) {
            $label = mb_strtolower(trim($tag));
            if (isset($byLabel[$label])) {
                $keys[] = $byLabel[$label];
            }
        }
        $diet = implode(',', array_values(array_intersect(array_keys(diet_options()), $keys)));
        if ($diet !== (string) $row['diet']) {
            $upd->execute([$diet, (int) $row['id']]);
        }
    }
}

/**
 * Puan kriterleri artik panelden degistirilemez: sabit listedekiler aktif ve sirali hale getirilir (eksikse eklenir),
 * listede olmayan eski kriterler pasife alinir (gecmis puanlari silinmez).
 */
function db_fix_criteria(PDO $pdo): void
{
    $pdo->exec('UPDATE rating_criteria SET is_active = 0');
    $find = $pdo->prepare('SELECT id FROM rating_criteria WHERE name = ? ORDER BY id LIMIT 1');
    $activate = $pdo->prepare('UPDATE rating_criteria SET is_active = 1, sort_order = ? WHERE id = ?');
    $insert = $pdo->prepare('INSERT INTO rating_criteria (name, sort_order) VALUES (?, ?)');
    foreach (RATING_CRITERIA as $i => $name) {
        $find->execute([$name]);
        $id = $find->fetchColumn();
        if ($id !== false) {
            $activate->execute([$i, (int) $id]);
        } else {
            $insert->execute([$name, $i]);
        }
    }
}

function db_create_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            name_en     TEXT NOT NULL DEFAULT '',
            description_en TEXT NOT NULL DEFAULT '',
            image       TEXT NOT NULL DEFAULT '',
            sort_order  INTEGER NOT NULL DEFAULT 0,
            is_active   INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS products (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            name        TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            details     TEXT NOT NULL DEFAULT '',
            name_en     TEXT NOT NULL DEFAULT '',
            description_en TEXT NOT NULL DEFAULT '',
            details_en  TEXT NOT NULL DEFAULT '',
            price       REAL,
            variants    TEXT NOT NULL DEFAULT '',
            calories    INTEGER,
            prep_minutes INTEGER,
            diet        TEXT NOT NULL DEFAULT '',
            is_featured INTEGER NOT NULL DEFAULT 0,
            is_active   INTEGER NOT NULL DEFAULT 1,
            is_sold_out INTEGER NOT NULL DEFAULT 0,
            sort_order  INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
        CREATE TABLE IF NOT EXISTS product_images (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            filename   TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_cover   INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_images_product ON product_images(product_id);
        CREATE TABLE IF NOT EXISTS rating_criteria (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS reviews (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
            name       TEXT NOT NULL DEFAULT '',
            comment    TEXT NOT NULL,
            ip         TEXT NOT NULL DEFAULT '',
            is_read    INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS review_scores (
            review_id   INTEGER NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
            criteria_id INTEGER NOT NULL REFERENCES rating_criteria(id) ON DELETE CASCADE,
            score       INTEGER NOT NULL CHECK (score BETWEEN 1 AND 5),
            PRIMARY KEY (review_id, criteria_id)
        );
        CREATE TABLE IF NOT EXISTS login_attempts (
            ip         TEXT PRIMARY KEY,
            attempts   INTEGER NOT NULL DEFAULT 0,
            locked_until INTEGER NOT NULL DEFAULT 0
        );
    ");
    $pdo->exec(CAMPAIGNS_TABLE_SQL);
    $pdo->exec(STATS_TABLES_SQL);
    $pdo->exec(PRODUCT_RATINGS_TABLE_SQL);
    $pdo->exec('PRAGMA user_version = ' . DB_SCHEMA_VERSION);   // yeni kurulum guncel yapidadir; gecisler tekrar kosmaz
}

function db_seed_defaults(PDO $pdo): void
{
    $defaults = [
        'site_name'     => 'Restoran Menü',
        'site_tagline'  => 'Lezzetin adresi',
        'logo'          => '',
        'background'    => '',
        'primary_color' => '',          // bos: secili temanin varsayilan vurgu rengi kullanilir
        'theme'         => 'dark',
        'phone'         => '',
        'address'       => '',
        'instagram'     => '',
        'show_prices'   => '1',
        'currency'      => '₺',
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM rating_criteria')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO rating_criteria (name, sort_order) VALUES (?, ?)');
        foreach (RATING_CRITERIA as $i => $name) {
            $stmt->execute([$name, $i]);
        }
    }
}

function db_seed_demo(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $cats = [
        ['Ana Lezzetler', 'Ustalarımızın elinden çıkan özel tarifler', [
            ['Tavuk Şiş Porsiyon', 'Özel marine edilmiş tavuk şiş, pilav ve közlenmiş sebze ile', 330, 'populer'],
            ['Adana Kebap', 'El yapımı zırh kebabı, lavaş ve közlenmiş domates-biber ile', 380, 'acili'],
        ]],
        ['Çorbalar', 'Günlük taze hazırlanan çorbalarımız', [
            ['Mercimek Çorbası', 'Klasik kırmızı mercimek, tereyağı ve limon ile', 90, 'vejetaryen'],
            ['Ezogelin Çorbası', 'Bulgur ve nane ile geleneksel tarif', 90, 'vejetaryen'],
        ]],
        ['İçecekler', 'Soğuk ve sıcak içecekler', [
            ['Ayran', 'Yayık ayranı, bakır kupada', 40, ''],
            ['Türk Kahvesi', 'Közde pişmiş, lokum eşliğinde', 70, ''],
        ]],
    ];
    $catStmt = $pdo->prepare('INSERT INTO categories (name, description, sort_order) VALUES (?, ?, ?)');
    $prodStmt = $pdo->prepare('INSERT INTO products (category_id, name, description, price, diet, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($cats as $ci => [$name, $desc, $products]) {
        $catStmt->execute([$name, $desc, $ci]);
        $catId = (int) $pdo->lastInsertId();
        foreach ($products as $pi => [$pname, $pdesc, $price, $diet]) {
            $prodStmt->execute([$catId, $pname, $pdesc, $price, $diet, $pi]);
        }
    }
}
