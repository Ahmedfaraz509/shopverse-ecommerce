<?php
declare(strict_types=1);

/* ==================================================================
 |  Shop_process  —  ShopVerse (public product listing)
 |
 |  Filters: search, category (slug or id), price_min/max, rating_min,
 |           on_sale, in_stock, featured, best_seller, new_arrival
 |  Sort:    latest, price-asc, price-desc, popular, rating, oldest
 * ================================================================== */

foreach ([
  __DIR__ . '/database/connect.php',
  __DIR__ . '/../database/connect.php',
  __DIR__ . '/includes/connect.php',
  __DIR__ . '/../includes/connect.php',
  __DIR__ . '/connect.php',
] as $__candidate) {
  if (is_file($__candidate)) {
    require_once $__candidate;
    break;
  }
}
unset($__candidate);

if (!class_exists('Connect')) {
  throw new RuntimeException('Connect class not found — check the path to database/connect.php');
}

class Shop_process
{
  private PDO $db;
  private const MAX_PER_PAGE = 48;

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /* =============================================================
   |  MAIN LISTING
   * ============================================================= */

  public function listProducts(array $f = []): array
  {
    [$where, $params] = $this->buildWhere($f);

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    /* ---- sort ---- */
    $sortMap = [
      'latest' => 'p.created_at DESC, p.id DESC',
      'oldest' => 'p.created_at ASC, p.id ASC',
      'price-asc' => 'p.price ASC, p.id DESC',
      'price-desc' => 'p.price DESC, p.id DESC',
      'name' => 'p.name ASC',
      'name_desc' => 'p.name DESC',
      'popular' => 'p.best_seller DESC, p.review_count DESC, p.rating DESC',
      'rating' => 'p.rating DESC, p.review_count DESC',
      'discount' => '(CASE WHEN p.compare_at_price > p.price
                                    THEN (p.compare_at_price - p.price) / p.compare_at_price
                                    ELSE 0 END) DESC',
    ];
    $sortKey = strtolower((string) ($f['sort'] ?? 'latest'));
    $orderBy = $sortMap[$sortKey] ?? $sortMap['latest'];

    /* ---- pagination ---- */
    $page = max(1, (int) ($f['page'] ?? 1));
    $perPage = (int) ($f['per_page'] ?? $f['limit'] ?? 12);
    $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
    $offset = ($page - 1) * $perPage;

    /* ---- count ---- */
    $countSql = "SELECT COUNT(*)
                       FROM products p
                       LEFT JOIN categories c ON c.id = p.category_id
                       $whereSql";
    $st = $this->db->prepare($countSql);
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    /* ---- rows ---- */
    $sql = "SELECT p.id, p.name, p.slug, p.sku, p.price, p.compare_at_price,
                       p.stock, p.rating, p.review_count,
                       p.featured, p.best_seller, p.new_arrival,
                       p.short_description,
                       c.name AS category_name,
                       c.slug AS category_slug,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                  $whereSql
              ORDER BY $orderBy
                 LIMIT :limit OFFSET :offset";

    $st = $this->db->prepare($sql);
    foreach ($params as $k => $v) {
      $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();

    $items = array_map([$this, 'presentCard'], $st->fetchAll());

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $perPage,
      'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
    ];
  }

  /** Category list with counts (for the sidebar). Respects current filters. */
  public function getCategoryFacets(array $f = []): array
  {
    /* Use a fresh filter set EXCLUDING the category filter so counts are meaningful */
    $fCopy = $f;
    unset($fCopy['category'], $fCopy['category_id'], $fCopy['category_slug']);

    [$where, $params] = $this->buildWhere($fCopy);

    /* Always count only active products */
    $where[] = "p.status = 'active'";
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $sql = "SELECT c.id, c.name, c.slug,
                       COUNT(p.id) AS product_count
                  FROM categories c
                  LEFT JOIN products p ON p.category_id = c.id
                  $whereSql
                 AND c.status = 'active'
              GROUP BY c.id, c.name, c.slug
              ORDER BY c.name ASC";

    /* The join above puts product-conditions in a weird place; rewrite cleanly */
    $sql = "SELECT c.id, c.name, c.slug,
                       (SELECT COUNT(*)
                          FROM products p
                          LEFT JOIN categories c2 ON c2.id = p.category_id
                          $whereSql
                           AND p.category_id = c.id) AS product_count
                  FROM categories c
                 WHERE c.status = 'active'
              ORDER BY c.name ASC";

    $st = $this->db->prepare($sql);
    $st->execute($params);

    return array_map(static fn(array $r) => [
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
      'product_count' => (int) $r['product_count'],
    ], $st->fetchAll());
  }

  /** Price min/max across all active products (for the slider bounds). */
  public function getPriceBounds(): array
  {
    $row = $this->db->query(
      "SELECT MIN(price) AS min_price, MAX(price) AS max_price
               FROM products
              WHERE status = 'active'"
    )->fetch() ?: [];

    $min = $row['min_price'] !== null ? (float) $row['min_price'] : 0.0;
    $max = $row['max_price'] !== null ? (float) $row['max_price'] : 1000.0;

    if ($max <= $min) {
      $max = $min + 100;
    }

    return [
      'min' => (int) floor($min),
      'max' => (int) ceil($max),
    ];
  }

  /** Broad stats for the results bar. */
  public function getStats(): array
  {
    $row = $this->db->query(
      "SELECT COUNT(*) AS total,
                    SUM(stock > 0) AS in_stock,
                    SUM(compare_at_price IS NOT NULL AND compare_at_price > price) AS on_sale
               FROM products
              WHERE status = 'active'"
    )->fetch() ?: [];

    return [
      'total' => (int) ($row['total'] ?? 0),
      'in_stock' => (int) ($row['in_stock'] ?? 0),
      'on_sale' => (int) ($row['on_sale'] ?? 0),
    ];
  }

  /* =============================================================
   |  INTERNAL
   * ============================================================= */

  /** Build the WHERE clause + bound params from filters. */
  private function buildWhere(array $f): array
  {
    $where = ["p.status = 'active'"];
    $params = [];

    /* search */
    $search = trim((string) ($f['search'] ?? $f['q'] ?? ''));
    if ($search !== '') {
      $like = '%' . $this->escapeLike($search) . '%';
      $where[] = '(p.name LIKE :s1 OR p.sku LIKE :s2 OR p.short_description LIKE :s3 OR p.description LIKE :s4)';
      $params[':s1'] = $like;
      $params[':s2'] = $like;
      $params[':s3'] = $like;
      $params[':s4'] = $like;
    }

    /* category (id, slug, csv list, or "all") */
    $cat = $f['category'] ?? $f['category_id'] ?? $f['category_slug'] ?? null;
    if ($cat !== null && $cat !== '' && strtolower((string) $cat) !== 'all') {
      if (is_array($cat)) {
        $ids = array_values(array_filter(array_map('intval', $cat), fn($i) => $i > 0));
        if ($ids) {
          $ph = [];
          foreach ($ids as $i => $id) {
            $key = ":cid$i";
            $ph[] = $key;
            $params[$key] = $id;
          }
          $where[] = 'p.category_id IN (' . implode(',', $ph) . ')';
        }
      } elseif (is_numeric($cat)) {
        $where[] = 'p.category_id = :cat_id';
        $params[':cat_id'] = (int) $cat;
      } else {
        /* support comma-separated slugs */
        $slugs = array_values(array_filter(array_map('trim', explode(',', (string) $cat))));
        if (count($slugs) === 1) {
          $where[] = 'c.slug = :cat_slug';
          $params[':cat_slug'] = $slugs[0];
        } elseif ($slugs) {
          $ph = [];
          foreach ($slugs as $i => $s) {
            $key = ":cs$i";
            $ph[] = $key;
            $params[$key] = $s;
          }
          $where[] = 'c.slug IN (' . implode(',', $ph) . ')';
        }
      }
    }

    /* price */
    if (isset($f['price_min']) && is_numeric($f['price_min']) && (float) $f['price_min'] > 0) {
      $where[] = 'p.price >= :price_min';
      $params[':price_min'] = (float) $f['price_min'];
    }
    if (isset($f['price_max']) && is_numeric($f['price_max']) && (float) $f['price_max'] > 0) {
      $where[] = 'p.price <= :price_max';
      $params[':price_max'] = (float) $f['price_max'];
    }

    /* rating */
    if (isset($f['rating_min']) && is_numeric($f['rating_min']) && (float) $f['rating_min'] > 0) {
      $where[] = 'p.rating >= :rating_min';
      $params[':rating_min'] = (float) $f['rating_min'];
    }

    /* on sale */
    if (!empty($f['on_sale'])) {
      $where[] = 'p.compare_at_price IS NOT NULL AND p.compare_at_price > p.price';
    }

    /* in stock */
    if (!empty($f['in_stock'])) {
      $where[] = 'p.stock > 0';
    }

    /* flags */
    foreach (['featured', 'best_seller', 'new_arrival'] as $flag) {
      if (!empty($f[$flag])) {
        $where[] = "p.`$flag` = 1";
      }
    }

    /* brand */
    $brand = $f['brand'] ?? $f['brand_id'] ?? null;
    if ($brand !== null && $brand !== '' && strtolower((string) $brand) !== 'all') {
      if (is_numeric($brand)) {
        $where[] = 'p.brand_id = :brand_id';
        $params[':brand_id'] = (int) $brand;
      } else {
        $where[] = 'b.slug = :brand_slug';
        $params[':brand_slug'] = (string) $brand;
      }
    }

    return [$where, $params];
  }

  private function presentCard(array $r): array
  {
    $price = (float) $r['price'];
    $oldPrice = $r['compare_at_price'] !== null ? (float) $r['compare_at_price'] : null;

    $discountPct = 0;
    if ($oldPrice && $oldPrice > $price) {
      $discountPct = (int) round((($oldPrice - $price) / $oldPrice) * 100);
    }

    return [
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
      'sku' => $r['sku'],
      'price' => round($price, 2),
      'old_price' => $oldPrice !== null ? round($oldPrice, 2) : null,
      'discount_percent' => $discountPct,
      'stock' => (int) $r['stock'],
      'in_stock' => (int) $r['stock'] > 0,
      'rating' => (float) $r['rating'],
      'review_count' => (int) $r['review_count'],
      'short_description' => $r['short_description'] ?? null,
      'category_name' => $r['category_name'] ?? null,
      'category_slug' => $r['category_slug'] ?? null,
      'image' => $r['image'] ?? null,
      'featured' => (int) $r['featured'] === 1,
      'best_seller' => (int) $r['best_seller'] === 1,
      'new_arrival' => (int) $r['new_arrival'] === 1,
    ];
  }

  private function escapeLike(string $s): string
  {
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);
  }
}