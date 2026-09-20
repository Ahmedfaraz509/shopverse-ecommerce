<?php
declare(strict_types=1);

/* ==================================================================
 |  Customers_process  —  ShopVerse (admin)
 |  Customers = users with role "customer". Order totals come from the
 |  orders table (cancelled orders are not counted as spend).
 * ================================================================== */

foreach ([
  __DIR__ . '/../database/connect.php',
  __DIR__ . '/database/connect.php',
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

class Customers_process
{
  private PDO $db;
  private const VIP_SPEND = 800.00;

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /** All customers with order count / spend, plus headline stats. */
  public function listCustomers(): array
  {
    $sql = "SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at, u.last_login_at,
                   COUNT(o.id)                                                        AS orders,
                   COALESCE(SUM(CASE WHEN o.order_status <> 'cancelled' THEN o.total END), 0) AS spent,
                   MAX(o.created_at)                                                  AS last_order
              FROM users u
              JOIN roles r ON r.id = u.role_id AND r.name = 'customer'
         LEFT JOIN orders o ON o.user_id = u.id
          GROUP BY u.id, u.name, u.email, u.phone, u.status, u.created_at, u.last_login_at
          ORDER BY u.created_at DESC, u.id DESC";
    $rows = $this->db->query($sql)->fetchAll();

    $items = [];
    $revenue = 0.0;
    $vip = 0;
    foreach ($rows as $r) {
      $spent = round((float) $r['spent'], 2);
      $revenue += $spent;
      $isVip = $spent > self::VIP_SPEND;
      $vip += $isVip ? 1 : 0;
      $items[] = [
        'id' => (int) $r['id'],
        'name' => $r['name'],
        'email' => $r['email'],
        'phone' => (string) ($r['phone'] ?? ''),
        'status' => $r['status'],
        'orders' => (int) $r['orders'],
        'spent' => $spent,
        'vip' => $isVip,
        'joined' => substr((string) $r['created_at'], 0, 10),
        'last_order' => $r['last_order'] ? substr((string) $r['last_order'], 0, 10) : null,
      ];
    }

    $n = count($items);
    return [
      'items' => $items,
      'stats' => [
        'total' => $n,
        'vip' => $vip,
        'revenue' => round($revenue, 2),
        'avg_spend' => $n ? round($revenue / $n, 2) : 0.0,
      ],
    ];
  }

  /** One customer with addresses and order history. */
  public function getCustomer(int $id): ?array
  {
    $st = $this->db->prepare(
      "SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at, u.last_login_at, u.email_verified_at
         FROM users u JOIN roles r ON r.id = u.role_id AND r.name = 'customer'
        WHERE u.id = :id LIMIT 1"
    );
    $st->execute([':id' => $id]);
    $u = $st->fetch();
    if (!$u) {
      return null;
    }

    $st = $this->db->prepare(
      "SELECT o.id, o.order_number, o.created_at, o.total, o.order_status, o.payment_status,
              (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
         FROM orders o WHERE o.user_id = :id
     ORDER BY o.created_at DESC, o.id DESC LIMIT 50"
    );
    $st->execute([':id' => $id]);
    $orders = array_map(static fn(array $r): array => [
      'id' => (int) $r['id'],
      'order_number' => $r['order_number'],
      'date' => substr((string) $r['created_at'], 0, 10),
      'total' => (float) $r['total'],
      'status' => $r['order_status'],
      'payment_status' => $r['payment_status'],
      'item_count' => (int) $r['item_count'],
    ], $st->fetchAll());

    $st = $this->db->prepare(
      "SELECT address_line, city, state, postal_code, country, is_default
         FROM addresses WHERE user_id = :id ORDER BY is_default DESC, id DESC LIMIT 10"
    );
    $st->execute([':id' => $id]);

    $spent = 0.0;
    foreach ($orders as $o) {
      if ($o['status'] !== 'cancelled') {
        $spent += $o['total'];
      }
    }

    return [
      'id' => (int) $u['id'],
      'name' => $u['name'],
      'email' => $u['email'],
      'phone' => (string) ($u['phone'] ?? ''),
      'status' => $u['status'],
      'verified' => $u['email_verified_at'] !== null,
      'joined' => substr((string) $u['created_at'], 0, 10),
      'last_login' => $u['last_login_at'] ? substr((string) $u['last_login_at'], 0, 16) : null,
      'orders' => $orders,
      'order_count' => count($orders),
      'spent' => round($spent, 2),
      'last_order' => $orders ? $orders[0]['date'] : null,
      'addresses' => $st->fetchAll(),
    ];
  }
}
