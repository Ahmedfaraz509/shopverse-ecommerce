<?php
declare(strict_types=1);

/* ==================================================================
 |  Product_public_process  —  ShopVerse (customer-facing reads)
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

class Product_public_process
{
  private PDO $db;

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /* =============================================================
   |  PUBLIC READS
   * ============================================================= */

  /** Get a single active product by slug or id, with images + variants. */
  public function getBySlugOrId(string $key): ?array
  {
    $key = trim($key);
    if ($key === '') {
      return null;
    }

    $isNumeric = ctype_digit($key);
    $col = $isNumeric ? 'p.id' : 'p.slug';

    $sql = "SELECT p.*,
                       c.name AS category_name,
                       c.slug AS category_slug,
                       b.name AS brand_name,
                       b.slug AS brand_slug,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                  LEFT JOIN brands     b ON b.id = p.brand_id
                 WHERE $col = :key
                   AND p.status = 'active'
                 LIMIT 1";

    $st = $this->db->prepare($sql);
    $st->execute([':key' => $isNumeric ? (int) $key : $key]);
    $row = $st->fetch();

    if (!$row) {
      return null;
    }

    $product = $this->presentProduct($row);
    $product['images'] = $this->getImages((int) $row['id']);
    $product['variants'] = $this->getVariants((int) $row['id']);

    return $product;
  }

  /** Related products (same category, excluding the current one). */
  public function getRelated(int $productId, int $categoryId, int $limit = 4): array
  {
    $limit = max(1, min($limit, 20));

    $sql = "SELECT p.id, p.name, p.slug, p.price, p.compare_at_price,
                       p.stock, p.rating, p.review_count, p.best_seller,
                       p.new_arrival, p.featured,
                       c.name AS category_name,
                       c.slug AS category_slug,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 'active'
                   AND p.id <> :pid
                   AND p.category_id = :cid
              ORDER BY p.best_seller DESC, p.rating DESC, p.id DESC
                 LIMIT $limit";

    $st = $this->db->prepare($sql);
    $st->execute([':pid' => $productId, ':cid' => $categoryId]);
    $rows = $st->fetchAll();

    return array_map([$this, 'presentCard'], $rows);
  }

  /** Same as getRelated but falls back to any active products if the category is empty. */
  public function getRelatedOrFallback(int $productId, int $categoryId, int $limit = 4): array
  {
    $related = $this->getRelated($productId, $categoryId, $limit);
    if (count($related) >= $limit) {
      return $related;
    }

    $need = $limit - count($related);
    $excl = array_merge([$productId], array_column($related, 'id'));
    $in = implode(',', array_fill(0, count($excl), '?'));

    $sql = "SELECT p.id, p.name, p.slug, p.price, p.compare_at_price,
                       p.stock, p.rating, p.review_count, p.best_seller,
                       p.new_arrival, p.featured,
                       c.name AS category_name,
                       c.slug AS category_slug,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 'active'
                   AND p.id NOT IN ($in)
              ORDER BY p.best_seller DESC, p.rating DESC, p.id DESC
                 LIMIT $need";

    $st = $this->db->prepare($sql);
    $st->execute($excl);
    $more = array_map([$this, 'presentCard'], $st->fetchAll());

    return array_merge($related, $more);
  }

  /** Approved reviews for a product. */
  public function getReviews(int $productId, int $limit = 20): array
  {
    $limit = max(1, min($limit, 100));

    $sql = "SELECT r.id, r.rating, r.title, r.comment, r.created_at,
                       u.name AS user_name
                  FROM reviews r
                  LEFT JOIN users u ON u.id = r.user_id
                 WHERE r.product_id = :pid
                   AND r.status = 'approved'
              ORDER BY r.created_at DESC
                 LIMIT $limit";

    $st = $this->db->prepare($sql);
    $st->execute([':pid' => $productId]);
    $rows = $st->fetchAll();

    return array_map(static function (array $r): array {
      $name = (string) ($r['user_name'] ?? 'Anonymous');
      $parts = preg_split('/\s+/', trim($name)) ?: [];
      $initials = strtoupper(substr($parts[0] ?? '', 0, 1)
        . substr($parts[1] ?? '', 0, 1));

      return [
        'id' => (int) $r['id'],
        'rating' => (int) $r['rating'],
        'title' => $r['title'] ?? null,
        'comment' => $r['comment'] ?? null,
        'created_at' => $r['created_at'],
        'user_name' => $name,
        'initials' => $initials ?: 'U',
      ];
    }, $rows);
  }

  /** Rating breakdown: [5 => 12, 4 => 3, ...] */
  public function getRatingBreakdown(int $productId): array
  {
    $st = $this->db->prepare(
      "SELECT rating, COUNT(*) AS n
               FROM reviews
              WHERE product_id = :pid
                AND status = 'approved'
           GROUP BY rating"
    );
    $st->execute([':pid' => $productId]);

    $out = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($st->fetchAll() as $r) {
      $out[(int) $r['rating']] = (int) $r['n'];
    }
    return $out;
  }

  /* =============================================================
   |  HELPERS
   * ============================================================= */

  private function getImages(int $productId): array
  {
    $st = $this->db->prepare(
      "SELECT id, image_path, alt_text, is_primary, sort_order
               FROM product_images
              WHERE product_id = :pid
           ORDER BY is_primary DESC, sort_order ASC, id ASC"
    );
    $st->execute([':pid' => $productId]);

    return array_map(static fn(array $r) => [
      'id' => (int) $r['id'],
      'path' => $r['image_path'],
      'alt' => $r['alt_text'] ?? null,
      'is_primary' => (int) $r['is_primary'] === 1,
    ], $st->fetchAll());
  }

  private function getVariants(int $productId): array
  {
    $st = $this->db->prepare(
      "SELECT id, sku, color, size, price, stock, status
               FROM product_variants
              WHERE product_id = :pid
                AND status = 'active'
           ORDER BY color ASC, size ASC, id ASC"
    );
    $st->execute([':pid' => $productId]);

    return array_map(static fn(array $r) => [
      'id' => (int) $r['id'],
      'sku' => $r['sku'],
      'color' => $r['color'] ?? null,
      'size' => $r['size'] ?? null,
      'price' => $r['price'] !== null ? (float) $r['price'] : null,
      'stock' => (int) $r['stock'],
    ], $st->fetchAll());
  }

  private function presentProduct(array $r): array
  {
    $price = (float) $r['price'];
    $oldPrice = $r['compare_at_price'] !== null ? (float) $r['compare_at_price'] : null;

    $discountPct = 0;
    if ($oldPrice && $oldPrice > $price) {
      $discountPct = (int) round((($oldPrice - $price) / $oldPrice) * 100);
    }

    $stock = (int) $r['stock'];
    $threshold = (int) ($r['low_stock_threshold'] ?? 5);

    return [
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
      'sku' => $r['sku'],
      'description' => $r['description'] ?? null,
      'short_description' => $r['short_description'] ?? null,
      'price' => $price,
      'old_price' => $oldPrice,
      'discount_percent' => $discountPct,
      'stock' => $stock,
      'low_stock_threshold' => $threshold,
      'is_low_stock' => $stock > 0 && $stock <= $threshold,
      'is_out_of_stock' => $stock === 0,
      'rating' => (float) $r['rating'],
      'review_count' => (int) $r['review_count'],
      'category_id' => (int) $r['category_id'],
      'category_name' => $r['category_name'] ?? null,
      'category_slug' => $r['category_slug'] ?? null,
      'brand_name' => $r['brand_name'] ?? null,
      'brand_slug' => $r['brand_slug'] ?? null,
      'image' => $r['image'] ?? null,
      'featured' => (int) $r['featured'] === 1,
      'best_seller' => (int) $r['best_seller'] === 1,
      'new_arrival' => (int) $r['new_arrival'] === 1,
      'created_at' => $r['created_at'] ?? null,
    ];
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
      'price' => $price,
      'old_price' => $oldPrice,
      'discount_percent' => $discountPct,
      'stock' => (int) $r['stock'],
      'rating' => (float) $r['rating'],
      'review_count' => (int) $r['review_count'],
      'category_name' => $r['category_name'] ?? null,
      'category_slug' => $r['category_slug'] ?? null,
      'image' => $r['image'] ?? null,
      'featured' => (int) $r['featured'] === 1,
      'best_seller' => (int) $r['best_seller'] === 1,
      'new_arrival' => (int) $r['new_arrival'] === 1,
    ];
  }
}