<?php
declare(strict_types=1);

/* ==================================================================
 |  Admin_dashboard_process  —  ShopVerse Admin
 |
 |  One-call dashboard payload:
 |    • stats (revenue, orders, customers, products, products sold)
 |    • sales timeseries (last 12 months)
 |    • sales by category (doughnut)
 |    • recent orders (5)
 |    • top products (5)
 |    • low stock alerts (threshold-aware)
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

class Admin_dashboard_process
{
  private PDO $db;

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /* =============================================================
   |  PUBLIC
   * ============================================================= */

  public function getDashboard(): array
  {
    return [
      'stats' => $this->getStats(),
      'timeseries' => $this->getSalesSeries(),
      'categories' => $this->getSalesByCategory(),
      'recent' => $this->getRecentOrders(5),
      'top' => $this->getTopProducts(5),
      'low_stock' => $this->getLowStock(6),
    ];
  }

  /* =============================================================
   |  STATS CARDS
   * ============================================================= */

  public function getStats(): array
  {
    /* Revenue lifetime + this month */
    $ord = $this->db->query(
      "SELECT
                COUNT(*)                                                   AS orders_total,
                SUM(DATE(created_at) = CURDATE())                          AS orders_today,
                SUM(order_status = 'pending')                              AS orders_pending,
                SUM(order_status IN ('pending','processing','shipped'))    AS orders_open,
                COALESCE(SUM(CASE WHEN order_status <> 'cancelled'
                                  THEN total ELSE 0 END), 0)               AS revenue_total,
                COALESCE(SUM(CASE WHEN order_status <> 'cancelled'
                                   AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                                  THEN total ELSE 0 END), 0)               AS revenue_month,
                COALESCE(SUM(CASE WHEN order_status <> 'cancelled'
                                   AND DATE(created_at) = CURDATE()
                                  THEN total ELSE 0 END), 0)               AS revenue_today
               FROM orders"
    )->fetch() ?: [];

    /* Customers */
    $users = $this->db->query(
      "SELECT
                COUNT(*)                              AS total,
                SUM(DATE(created_at) = CURDATE())     AS today,
                SUM(role_id = 2)                      AS customers
               FROM users"
    )->fetch() ?: [];

    /* Products */
    $prod = $this->db->query(
      "SELECT
                COUNT(*)                                                      AS total,
                SUM(status = 'active')                                        AS active,
                SUM(stock = 0)                                                AS out_of_stock,
                SUM(stock > 0 AND stock <= low_stock_threshold)               AS low_stock,
                COALESCE(SUM(stock * price), 0)                               AS inventory_value
               FROM products"
    )->fetch() ?: [];

    /* Units sold (all-time, non-cancelled) */
    $units = (int) $this->db->query(
      "SELECT COALESCE(SUM(oi.quantity), 0)
               FROM order_items oi
               JOIN orders o ON o.id = oi.order_id
              WHERE o.order_status <> 'cancelled'"
    )->fetchColumn();

    return [
      'revenue_total' => round((float) ($ord['revenue_total'] ?? 0), 2),
      'revenue_month' => round((float) ($ord['revenue_month'] ?? 0), 2),
      'revenue_today' => round((float) ($ord['revenue_today'] ?? 0), 2),
      'orders_total' => (int) ($ord['orders_total'] ?? 0),
      'orders_today' => (int) ($ord['orders_today'] ?? 0),
      'orders_pending' => (int) ($ord['orders_pending'] ?? 0),
      'orders_open' => (int) ($ord['orders_open'] ?? 0),
      'customers_total' => (int) ($users['customers'] ?? 0),
      'customers_today' => (int) ($users['today'] ?? 0),
      'products_total' => (int) ($prod['total'] ?? 0),
      'products_active' => (int) ($prod['active'] ?? 0),
      'products_low' => (int) ($prod['low_stock'] ?? 0),
      'products_out' => (int) ($prod['out_of_stock'] ?? 0),
      'units_sold' => $units,
      'inventory_value' => round((float) ($prod['inventory_value'] ?? 0), 2),
    ];
  }

  /* =============================================================
   |  SALES TIMESERIES (last 12 months)
   * ============================================================= */

  public function getSalesSeries(): array
  {
    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS bucket,
                       COUNT(*) AS orders,
                       COALESCE(SUM(CASE WHEN order_status <> 'cancelled'
                                         THEN total ELSE 0 END), 0) AS revenue
                  FROM orders
                 WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
              GROUP BY bucket
              ORDER BY bucket ASC";
    $rows = $this->db->query($sql)->fetchAll();

    $map = [];
    foreach ($rows as $r) {
      $map[$r['bucket']] = [
        'orders' => (int) $r['orders'],
        'revenue' => round((float) $r['revenue'], 2),
      ];
    }

    /* Fill every month so chart never skips */
    $labels = [];
    $revenue = [];
    $orders = [];

    $cursor = new DateTime('first day of this month -11 months');
    $end = new DateTime('first day of this month');
    while ($cursor <= $end) {
      $key = $cursor->format('Y-m-01');
      $labels[] = $cursor->format('M Y');
      $revenue[] = $map[$key]['revenue'] ?? 0;
      $orders[] = $map[$key]['orders'] ?? 0;
      $cursor->modify('+1 month');
    }

    return [
      'labels' => $labels,
      'revenue' => $revenue,
      'orders' => $orders,
    ];
  }

  /* =============================================================
   |  SALES BY CATEGORY (all-time)
   * ============================================================= */

  public function getSalesByCategory(int $limit = 8): array
  {
    $limit = max(1, min($limit, 20));

    $sql = "SELECT c.id,
                       c.name AS category_name,
                       COALESCE(SUM(oi.quantity), 0) AS units,
                       COALESCE(SUM(oi.subtotal), 0) AS revenue
                  FROM order_items oi
                  JOIN orders     o ON o.id = oi.order_id
                  JOIN products   p ON p.id = oi.product_id
                  JOIN categories c ON c.id = p.category_id
                 WHERE o.order_status <> 'cancelled'
              GROUP BY c.id, c.name
              ORDER BY revenue DESC
                 LIMIT $limit";
    $rows = $this->db->query($sql)->fetchAll();

    return [
      'labels' => array_map(fn($r) => $r['category_name'] ?: 'Uncategorised', $rows),
      'revenue' => array_map(fn($r) => round((float) $r['revenue'], 2), $rows),
      'units' => array_map(fn($r) => (int) $r['units'], $rows),
    ];
  }

  /* =============================================================
   |  RECENT ORDERS
   * ============================================================= */

  public function getRecentOrders(int $limit = 5): array
  {
    $limit = max(1, min($limit, 20));

    $sql = "SELECT o.id, o.order_number, o.total,
                       o.order_status, o.payment_status,
                       o.created_at,
                       u.name  AS customer_name,
                       u.email AS customer_email
                  FROM orders o
                  LEFT JOIN users u ON u.id = o.user_id
              ORDER BY o.created_at DESC, o.id DESC
                 LIMIT $limit";
    $rows = $this->db->query($sql)->fetchAll();

    return array_map(static function (array $r): array {
      $os = strtolower((string) $r['order_status']);
      $ps = strtolower((string) $r['payment_status']);
      return [
        'id' => (int) $r['id'],
        'order_number' => $r['order_number'],
        'total' => (float) $r['total'],
        'order_status' => ucfirst($os),
        'order_raw' => $os,
        'payment_status' => ucwords(str_replace('_', ' ', $ps)),
        'payment_raw' => $ps,
        'customer_name' => $r['customer_name'] ?? '—',
        'customer_email' => $r['customer_email'] ?? null,
        'created_at' => $r['created_at'],
      ];
    }, $rows);
  }

  /* =============================================================
   |  TOP PRODUCTS
   * ============================================================= */

  public function getTopProducts(int $limit = 5): array
  {
    $limit = max(1, min($limit, 20));

    $sql = "SELECT oi.product_id,
                       oi.product_name,
                       SUM(oi.quantity) AS units,
                       SUM(oi.subtotal) AS revenue,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = oi.product_id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM order_items oi
                  JOIN orders o ON o.id = oi.order_id
                 WHERE o.order_status <> 'cancelled'
              GROUP BY oi.product_id, oi.product_name
              ORDER BY revenue DESC
                 LIMIT $limit";
    $rows = $this->db->query($sql)->fetchAll();

    return array_map(static fn(array $r) => [
      'product_id' => (int) $r['product_id'],
      'product_name' => $r['product_name'],
      'units' => (int) $r['units'],
      'revenue' => round((float) $r['revenue'], 2),
      'image' => $r['image'] ?? null,
    ], $rows);
  }

  /* =============================================================
   |  LOW STOCK
   * ============================================================= */

  public function getLowStock(int $limit = 6): array
  {
    $limit = max(1, min($limit, 50));

    $sql = "SELECT p.id, p.name, p.slug, p.sku,
                       p.stock, p.low_stock_threshold,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM products p
                 WHERE p.status = 'active'
                   AND p.stock <= p.low_stock_threshold
              ORDER BY p.stock ASC, p.updated_at DESC
                 LIMIT $limit";
    $rows = $this->db->query($sql)->fetchAll();

    return array_map(static fn(array $r) => [
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
      'sku' => $r['sku'],
      'stock' => (int) $r['stock'],
      'low_stock_threshold' => (int) $r['low_stock_threshold'],
      'is_out' => (int) $r['stock'] === 0,
      'image' => $r['image'] ?? null,
    ], $rows);
  }
}