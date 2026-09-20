<?php
declare(strict_types=1);

/* ==================================================================
 |  Admin_reports_process  —  ShopVerse Admin
 |
 |  • Range-aware analytics (7d, 30d, 90d, 12m, ytd)
 |  • Stats overview
 |  • Revenue + orders time series (grouped by day/week/month)
 |  • Sales by category
 |  • Orders by status
 |  • Top-selling products
 |  • CSV export
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

class Admin_reports_process
{
  private PDO $db;

  /** Preset ranges → [start, end, granularity] */
  private const RANGES = [
    '7d' => ['days' => 6, 'group' => 'day', 'label' => 'Last 7 days'],
    '30d' => ['days' => 29, 'group' => 'day', 'label' => 'Last 30 days'],
    '90d' => ['days' => 89, 'group' => 'week', 'label' => 'Last 90 days'],
    '12m' => ['days' => 364, 'group' => 'month', 'label' => 'Last 12 months'],
    'ytd' => ['days' => 0, 'group' => 'month', 'label' => 'Year to date'],
  ];

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /* =============================================================
   |  PUBLIC
   * ============================================================= */

  /** Everything the reports page needs, in one shot. */
  public function getReport(array $f = []): array
  {
    $range = $this->resolveRange($f);

    return [
      'range' => [
        'key' => $range['key'],
        'label' => $range['label'],
        'start' => $range['start_date'],
        'end' => $range['end_date'],
        'group' => $range['group'],
      ],
      'stats' => $this->getStats($range),
      'timeseries' => $this->getTimeseries($range),
      'categories' => $this->getSalesByCategory($range),
      'statuses' => $this->getOrdersByStatus($range),
      'top' => $this->getTopProducts($range, 10),
    ];
  }

  /** For CSV export — flat rows of orders in the range. */
  public function getOrdersForExport(array $f = []): array
  {
    $range = $this->resolveRange($f);

    $sql = "SELECT o.id, o.order_number, o.created_at,
                       u.name AS customer_name, u.email AS customer_email,
                       o.subtotal, o.discount, o.shipping, o.tax, o.total,
                       o.payment_status, o.order_status,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
                  FROM orders o
                  LEFT JOIN users u ON u.id = o.user_id
                 WHERE o.created_at BETWEEN :s AND :e
              ORDER BY o.created_at DESC";

    $st = $this->db->prepare($sql);
    $st->execute([
      ':s' => $range['start_date'] . ' 00:00:00',
      ':e' => $range['end_date'] . ' 23:59:59',
    ]);

    return $st->fetchAll();
  }

  /* =============================================================
   |  STATS
   * ============================================================= */

  private function getStats(array $r): array
  {
    [$s, $e] = $this->bounds($r);

    $sql = "SELECT
                    COUNT(*)                                              AS total_orders,
                    SUM(order_status = 'cancelled')                       AS cancelled_orders,
                    SUM(order_status = 'delivered')                       AS delivered_orders,
                    SUM(order_status IN ('pending','processing','shipped')) AS open_orders,
                    COALESCE(SUM(CASE WHEN order_status <> 'cancelled'
                                      THEN total ELSE 0 END), 0)          AS revenue,
                    COALESCE(AVG(CASE WHEN order_status <> 'cancelled'
                                      THEN total END), 0)                 AS aov,
                    COALESCE(SUM(CASE WHEN payment_status = 'paid'
                                      THEN total ELSE 0 END), 0)          AS paid_revenue,
                    COUNT(DISTINCT user_id)                               AS unique_customers
                  FROM orders
                 WHERE created_at BETWEEN :s AND :e";
    $st = $this->db->prepare($sql);
    $st->execute([':s' => $s, ':e' => $e]);
    $row = $st->fetch() ?: [];

    /* products sold + items in range */
    $sql2 = "SELECT COALESCE(SUM(oi.quantity), 0) AS units_sold
                   FROM order_items oi
                   JOIN orders o ON o.id = oi.order_id
                  WHERE o.created_at BETWEEN :s AND :e
                    AND o.order_status <> 'cancelled'";
    $st2 = $this->db->prepare($sql2);
    $st2->execute([':s' => $s, ':e' => $e]);
    $unitsSold = (int) $st2->fetchColumn();

    return [
      'total_orders' => (int) ($row['total_orders'] ?? 0),
      'open_orders' => (int) ($row['open_orders'] ?? 0),
      'delivered_orders' => (int) ($row['delivered_orders'] ?? 0),
      'cancelled_orders' => (int) ($row['cancelled_orders'] ?? 0),
      'revenue' => round((float) ($row['revenue'] ?? 0), 2),
      'paid_revenue' => round((float) ($row['paid_revenue'] ?? 0), 2),
      'aov' => round((float) ($row['aov'] ?? 0), 2),
      'units_sold' => $unitsSold,
      'unique_customers' => (int) ($row['unique_customers'] ?? 0),
    ];
  }

  /* =============================================================
   |  TIMESERIES (revenue + orders)
   * ============================================================= */

  private function getTimeseries(array $r): array
  {
    [$s, $e] = $this->bounds($r);
    $group = $r['group'];

    switch ($group) {
      case 'month':
        $dateExpr = "DATE_FORMAT(created_at, '%Y-%m-01')";
        break;
      case 'week':
        $dateExpr = "DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY)";
        break;
      case 'day':
      default:
        $dateExpr = "DATE(created_at)";
    }

    $sql = "SELECT $dateExpr AS bucket,
                       COUNT(*) AS orders,
                       COALESCE(SUM(CASE WHEN order_status <> 'cancelled'
                                         THEN total ELSE 0 END), 0) AS revenue
                  FROM orders
                 WHERE created_at BETWEEN :s AND :e
              GROUP BY bucket
              ORDER BY bucket ASC";

    $st = $this->db->prepare($sql);
    $st->execute([':s' => $s, ':e' => $e]);
    $rows = $st->fetchAll();

    /* Build a filled series (so missing buckets show as 0) */
    $map = [];
    foreach ($rows as $row) {
      $map[$row['bucket']] = [
        'orders' => (int) $row['orders'],
        'revenue' => round((float) $row['revenue'], 2),
      ];
    }

    $labels = [];
    $revenues = [];
    $orders = [];

    foreach ($this->iterateBuckets($r) as $bucket) {
      $labels[] = $this->formatBucketLabel($bucket, $group);
      $revenues[] = $map[$bucket]['revenue'] ?? 0;
      $orders[] = $map[$bucket]['orders'] ?? 0;
    }

    return [
      'labels' => $labels,
      'revenue' => $revenues,
      'orders' => $orders,
      'group' => $group,
    ];
  }

  /** Walks each bucket (day/week/month) between start and end. */
  private function iterateBuckets(array $r): array
  {
    $start = new DateTime($r['start_date']);
    $end = new DateTime($r['end_date']);
    $out = [];

    switch ($r['group']) {
      case 'month':
        $start->modify('first day of this month');
        $end->modify('first day of this month');
        $interval = new DateInterval('P1M');
        break;
      case 'week':
        $start->modify('monday this week');
        $end->modify('monday this week');
        $interval = new DateInterval('P1W');
        break;
      case 'day':
      default:
        $interval = new DateInterval('P1D');
    }

    $cursor = clone $start;
    while ($cursor <= $end) {
      $out[] = $cursor->format('Y-m-d');
      $cursor->add($interval);
    }

    return $out;
  }

  private function formatBucketLabel(string $bucket, string $group): string
  {
    $d = new DateTime($bucket);
    return match ($group) {
      'month' => $d->format('M Y'),
      'week' => 'W' . $d->format('W') . ' ' . $d->format('Y'),
      default => $d->format('M j'),
    };
  }

  /* =============================================================
   |  SALES BY CATEGORY
   * ============================================================= */

  private function getSalesByCategory(array $r, int $limit = 10): array
  {
    [$s, $e] = $this->bounds($r);

    $sql = "SELECT c.id,
                       c.name AS category_name,
                       COALESCE(SUM(oi.quantity), 0)   AS units,
                       COALESCE(SUM(oi.subtotal), 0)   AS revenue
                  FROM order_items oi
                  JOIN orders     o ON o.id = oi.order_id
                  JOIN products   p ON p.id = oi.product_id
                  JOIN categories c ON c.id = p.category_id
                 WHERE o.created_at BETWEEN :s AND :e
                   AND o.order_status <> 'cancelled'
              GROUP BY c.id, c.name
              ORDER BY revenue DESC
                 LIMIT $limit";
    $st = $this->db->prepare($sql);
    $st->execute([':s' => $s, ':e' => $e]);
    $rows = $st->fetchAll();

    return [
      'labels' => array_map(fn($r) => $r['category_name'] ?: 'Uncategorised', $rows),
      'revenue' => array_map(fn($r) => round((float) $r['revenue'], 2), $rows),
      'units' => array_map(fn($r) => (int) $r['units'], $rows),
    ];
  }

  /* =============================================================
   |  ORDERS BY STATUS
   * ============================================================= */

  private function getOrdersByStatus(array $r): array
  {
    [$s, $e] = $this->bounds($r);

    $sql = "SELECT order_status, COUNT(*) AS n
                  FROM orders
                 WHERE created_at BETWEEN :s AND :e
              GROUP BY order_status";
    $st = $this->db->prepare($sql);
    $st->execute([':s' => $s, ':e' => $e]);

    $map = [
      'pending' => 0,
      'processing' => 0,
      'shipped' => 0,
      'delivered' => 0,
      'cancelled' => 0,
    ];
    foreach ($st->fetchAll() as $row) {
      $map[strtolower((string) $row['order_status'])] = (int) $row['n'];
    }

    return [
      'labels' => array_map('ucfirst', array_keys($map)),
      'values' => array_values($map),
      'keys' => array_keys($map),
    ];
  }

  /* =============================================================
   |  TOP PRODUCTS
   * ============================================================= */

  private function getTopProducts(array $r, int $limit = 10): array
  {
    [$s, $e] = $this->bounds($r);
    $limit = max(1, min($limit, 50));

    $sql = "SELECT oi.product_id,
                       oi.product_name,
                       oi.sku,
                       c.name AS category_name,
                       SUM(oi.quantity)  AS units,
                       SUM(oi.subtotal)  AS revenue,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = oi.product_id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM order_items oi
                  JOIN orders   o ON o.id = oi.order_id
                  LEFT JOIN products   p ON p.id = oi.product_id
                  LEFT JOIN categories c ON c.id = p.category_id
                 WHERE o.created_at BETWEEN :s AND :e
                   AND o.order_status <> 'cancelled'
              GROUP BY oi.product_id, oi.product_name, oi.sku, c.name
              ORDER BY revenue DESC
                 LIMIT $limit";
    $st = $this->db->prepare($sql);
    $st->execute([':s' => $s, ':e' => $e]);
    $rows = $st->fetchAll();

    return array_map(static fn(array $x) => [
      'product_id' => (int) $x['product_id'],
      'product_name' => $x['product_name'],
      'sku' => $x['sku'],
      'category_name' => $x['category_name'] ?? '—',
      'units' => (int) $x['units'],
      'revenue' => round((float) $x['revenue'], 2),
      'image' => $x['image'] ?? null,
    ], $rows);
  }

  /* =============================================================
   |  RANGE HELPERS
   * ============================================================= */

  private function resolveRange(array $f): array
  {
    $key = strtolower((string) ($f['range'] ?? '12m'));
    if (!isset(self::RANGES[$key])) {
      $key = '12m';
    }

    $preset = self::RANGES[$key];
    $today = new DateTime('today');

    if ($key === 'ytd') {
      $start = new DateTime('first day of January this year');
    } else {
      $start = (clone $today)->modify('-' . $preset['days'] . ' days');
    }

    /* allow explicit date override */
    if (!empty($f['date_from'])) {
      try {
        $start = new DateTime($f['date_from']);
      } catch (Throwable $e) {
      }
    }
    if (!empty($f['date_to'])) {
      try {
        $today = new DateTime($f['date_to']);
      } catch (Throwable $e) {
      }
    }

    return [
      'key' => $key,
      'label' => $preset['label'],
      'group' => $preset['group'],
      'start_date' => $start->format('Y-m-d'),
      'end_date' => $today->format('Y-m-d'),
    ];
  }

  private function bounds(array $r): array
  {
    return [
      $r['start_date'] . ' 00:00:00',
      $r['end_date'] . ' 23:59:59',
    ];
  }
}