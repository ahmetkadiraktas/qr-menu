<?php
/** Public tarafta ortak sorgular */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function active_categories(): array
{
    $rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS product_count
                         FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.id')->fetchAll();
    $rows = array_values(array_filter($rows, fn(array $c): bool => (int) $c['product_count'] > 0));   // bos kategori musteriye gosterilmez
    return array_map(fn(array $c): array => localize($c, 'name', 'description'), $rows);
}

/** Yayinda olan ve suresi dolmamis kampanyalar (gecerli dilde). $display: 'popup' (ortada pencere) | 'band' (ust serit) */
function active_campaigns(string $display): array
{
    static $cache = null;
    if ($cache === null) {
        $stmt = db()->prepare("SELECT * FROM campaigns WHERE is_active = 1 AND (ends_on = '' OR ends_on >= ?) ORDER BY sort_order, id");
        $stmt->execute([date('Y-m-d')]);
        $cache = array_map(fn(array $c): array => localize($c, 'title', 'body'), $stmt->fetchAll());
    }
    return array_values(array_filter($cache, fn(array $c): bool => $c['display'] === $display));
}

function products_with_cover(string $where, array $params): array
{
    $sql = "SELECT p.*, (
                SELECT filename FROM product_images i WHERE i.product_id = p.id ORDER BY i.is_cover DESC, i.sort_order, i.id LIMIT 1
            ) AS cover
            FROM products p WHERE p.is_active = 1 AND $where ORDER BY p.is_featured DESC, p.sort_order, p.id";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return array_map(fn(array $p): array => localize($p, 'name', 'description', 'details'), $stmt->fetchAll());
}
