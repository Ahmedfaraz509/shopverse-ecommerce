<?php
declare(strict_types=1);

/* ==================================================================
 |  Home_process  —  ShopVerse (public homepage)
 |
 |  • Hero stats (real product / customer counts)
 |  • Featured categories (top by product count)
 |  • Best sellers
 |  • New arrivals
 |  • Trending picks
 |  • Approved testimonials (reviews with rating >= 4)
 |
 |  All items are active-only and respect stock rules.
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

class Home_process
{
  private PDO $db;

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /* =============================================================
   |  ONE-SHOT PAYLOAD
   * ============================================================= */

  public function getHomeData(): array
  {
    return [
      'stats' => $this->getHeroStats(),
      'categories' => $this->getFeaturedCategories(6),
      'best' => $this->getBestSellers(8),
      'new_arrivals' => $this->getNewArrivals(8),
      'trending' => $this->getTrending(4),
      'testimonials' => $this->getTestimonials(3),
    ];
  }

  /* =============================================================
   |  HERO STATS
   * ============================================================= */

  public function getHeroStats(): array
  {
    $products = (int) $this->db->query(
      "SELECT COUNT(*) FROM products WHERE status = 'active'"
    )->fetchColumn();

    $categories = (int) $this->db->query(
      "SELECT COUNT(*) FROM categories WHERE status = 'active'"
    )->fetchColumn();

    $customers = (int) $this->db->query(
      "SELECT COUNT(*) FROM users WHERE role_id = 2 AND status = 'active'"
    )->fetchColumn();

    /* Aggregate rating across approved reviews */
    $avgRating = (float) $this->db->query(
      "SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE status = 'approved'"
    )->fetchColumn();

    return [
      'products' => $products,
      'categories' => $categories,
      'customers' => $customers,
      'avg_rating' => round($avgRating, 1),
      'products_label' => $products >= 1000
        ? round($products / 1000, 1) . 'k+'
        : $products . '+',
    ];
  }

  /* =============================================================
   |  FEATURED CATEGORIES
   * ============================================================= */

  public function getFeaturedCategories(int $limit = 6): array
  {
    $limit = max(1, min($limit, 12));

    /* Root categories, ordered by their active product count */
    $sql = "SELECT c.id, c.name, c.slug, c.description, c.image,
                       (SELECT COUNT(*)
                          FROM products p
                         WHERE p.category_id = c.id
                           AND p.status = 'active') AS product_count
                  FROM categories c
                 WHERE c.status = 'active'
                   AND c.parent_id IS NULL
                 HAVING product_count > 0
              ORDER BY product_count DESC, c.name ASC
                 LIMIT $limit";

    $rows = $this->db->query($sql)->fetchAll();

    return array_map(static fn(array $r) => [
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
      'description' => $r['description'] ?? null,
      'image' => $r['image'] ?? null,
      'product_count' => (int) $r['product_count'],
    ], $rows);
  }

  /* =============================================================
   |  BEST SELLERS
   * ============================================================= */

  public function getBestSellers(int $limit = 8): array
  {
    $limit = max(1, min($limit, 24));

    /* Real sales first; falls back to best_seller flag + rating */
    $sql = "SELECT p.id, p.name, p.slug, p.sku,
                       p.price, p.compare_at_price, p.stock,
                       p.rating, p.review_count,
                       p.featured, p.best_seller, p.new_arrival,
                       c.name AS category_name,
                       c.slug AS category_slug,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image,
                       (SELECT COALESCE(SUM(oi.quantity), 0)
                          FROM order_items oi
                          JOIN orders o ON o.id = oi.order_id
                         WHERE oi.product_id = p.id
                           AND o.order_status <> 'cancelled') AS units_sold
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 'active'
              ORDER BY units_sold DESC,
                       p.best_seller DESC,
                       p.review_count DESC,
                       p.rating DESC
                 LIMIT $limit";

    $rows = $this->db->query($sql)->fetchAll();

    return array_map([$this, 'presentCard'], $rows);
  }

  /* =============================================================
   |  NEW ARRIVALS
   * ============================================================= */

  public function getNewArrivals(int $limit = 8): array
  {
    $limit = max(1, min($limit, 24));

    $sql = "SELECT p.id, p.name, p.slug, p.sku,
                       p.price, p.compare_at_price, p.stock,
                       p.rating, p.review_count,
                       p.featured, p.best_seller, p.new_arrival,
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
              ORDER BY p.created_at DESC, p.id DESC
                 LIMIT $limit";

    $rows = $this->db->query($sql)->fetchAll();

    return array_map([$this, 'presentCard'], $rows);
  }

  /* =============================================================
   |  TRENDING (for the "Trending Right Now" 2x2 grid)
   * ============================================================= */

  public function getTrending(int $limit = 4): array
  {
    $limit = max(1, min($limit, 12));

    $sql = "SELECT p.id, p.name, p.slug, p.sku,
                       p.price, p.compare_at_price, p.stock,
                       p.rating, p.review_count,
                       p.featured, p.best_seller, p.new_arrival,
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
                   AND (p.featured = 1 OR p.best_seller = 1 OR p.new_arrival = 1)
              ORDER BY p.featured DESC,
                       p.best_seller DESC,
                       p.new_arrival DESC,
                       p.rating DESC,
                       p.review_count DESC
                 LIMIT $limit";

    $rows = $this->db->query($sql)->fetchAll();

    /* Fallback: if no flagged products, just take recent ones */
    if (!$rows) {
      $rows = $this->db->query(
        "SELECT p.id, p.name, p.slug, p.sku,
                        p.price, p.compare_at_price, p.stock,
                        p.rating, p.review_count,
                        p.featured, p.best_seller, p.new_arrival,
                        c.name AS category_name,
                        c.slug AS category_slug,
                        (SELECT pi.image_path FROM product_images pi
                          WHERE pi.product_id = p.id
                       ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
                   FROM products p
                   LEFT JOIN categories c ON c.id = p.category_id
                  WHERE p.status = 'active'
               ORDER BY p.id DESC
                  LIMIT $limit"
      )->fetchAll();
    }

    return array_map([$this, 'presentCard'], $rows);
  }

  /* =============================================================
   |  TESTIMONIALS
   * ============================================================= */

  public function getTestimonials(int $limit = 3): array
  {
    $limit = max(1, min($limit, 12));

    /* Prefer highly-rated, longer reviews with title */
    $sql = "SELECT r.id, r.rating, r.title, r.comment, r.created_at,
                       u.name AS user_name,
                       p.name AS product_name,
                       p.slug AS product_slug
                  FROM reviews r
                  JOIN users    u ON u.id = r.user_id
                  JOIN products p ON p.id = r.product_id
                 WHERE r.status = 'approved'
                   AND r.rating >= 4
                   AND r.comment IS NOT NULL
                   AND CHAR_LENGTH(r.comment) >= 20
              ORDER BY r.rating DESC,
                       CHAR_LENGTH(r.comment) DESC,
                       r.created_at DESC
                 LIMIT $limit";

    $rows = $this->db->query($sql)->fetchAll();

    /* If nothing matched, relax the constraints */
    if (!$rows) {
      $rows = $this->db->query(
        "SELECT r.id, r.rating, r.title, r.comment, r.created_at,
                        u.name AS user_name,
                        p.name AS product_name,
                        p.slug AS product_slug
                   FROM reviews r
                   JOIN users    u ON u.id = r.user_id
                   JOIN products p ON p.id = r.product_id
                  WHERE r.status = 'approved'
               ORDER BY r.rating DESC, r.created_at DESC
                  LIMIT $limit"
      )->fetchAll();
    }

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
        'product_name' => $r['product_name'],
        'product_slug' => $r['product_slug'],
      ];
    }, $rows);
  }

  /* =============================================================
   |  INTERNAL — unify product cards
   * ============================================================= */

  private function presentCard(array $r): array
  {
    $price = (float) $r['price'];
    $oldPrice = $r['compare_at_price'] !== null ? (float) $r['compare_at_price'] : null;

    $discountPct = 0;
    if ($oldPrice && $oldPrice > $price) {
      $discountPct = (int) round((($oldPrice - $price) / $oldPrice) * 100);
    }

    $stock = (int) $r['stock'];
    $threshold = 5;

    return [
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
      'sku' => $r['sku'],
      'price' => round($price, 2),
      'old_price' => $oldPrice !== null ? round($oldPrice, 2) : null,
      'discount_percent' => $discountPct,
      'stock' => $stock,
      'in_stock' => $stock > 0,
      'low_stock' => $stock > 0 && $stock <= $threshold,
      'rating' => (float) $r['rating'],
      'review_count' => (int) $r['review_count'],
      'category_name' => $r['category_name'] ?? null,
      'category_slug' => $r['category_slug'] ?? null,
      'image' => $r['image'] ?? null,
      'featured' => (int) $r['featured'] === 1,
      'best_seller' => (int) $r['best_seller'] === 1,
      'new_arrival' => (int) $r['new_arrival'] === 1,
      'units_sold' => isset($r['units_sold']) ? (int) $r['units_sold'] : null,
    ];
  }
}