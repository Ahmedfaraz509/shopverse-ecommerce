<?php
declare(strict_types=1);

/* ==================================================================
 |  Orders_process  —  ShopVerse (customer side)
 |
 |  • Reads orders belonging to the CURRENT logged-in user only
 |  • Includes order items, address, payment snapshot
 |  • Allows customer cancellation while order is still cancellable
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

class Orders_process
{
  private PDO $db;
  private array $errors = [];

  /** Statuses a customer is still allowed to cancel. */
  private const CANCELLABLE = ['pending', 'processing'];

  /** Customer-visible order statuses. */
  private const VISIBLE_STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

  private const MAX_PER_PAGE = 100;

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /* =============================================================
   |  PUBLIC API
   * ============================================================= */

  /**
   * List orders for one user.
   * Filters: status, search (order_number), date_from, date_to, sort, page, per_page
   */
  public function listOrders(int $userId, array $f = []): array
  {
    if ($userId <= 0) {
      return $this->emptyPage();
    }

    $where = ['o.user_id = :user_id'];
    $params = [':user_id' => $userId];

    /* status filter */
    $status = strtolower(trim((string) ($f['status'] ?? '')));
    if ($status !== '' && $status !== 'all') {
      if (in_array($status, self::VISIBLE_STATUSES, true)) {
        $where[] = 'o.order_status = :status';
        $params[':status'] = $status;
      }
    }

    /* payment status filter */
    $pay = strtolower(trim((string) ($f['payment_status'] ?? '')));
    if ($pay !== '' && $pay !== 'all') {
      $where[] = 'o.payment_status = :pay';
      $params[':pay'] = $pay;
    }

    /* search order number */
    $search = trim((string) ($f['search'] ?? $f['q'] ?? ''));
    if ($search !== '') {
      $like = '%' . $this->escapeLike($search) . '%';
      $where[] = 'o.order_number LIKE :s';
      $params[':s'] = $like;
    }

    /* date range */
    if (!empty($f['date_from'])) {
      $where[] = 'o.created_at >= :df';
      $params[':df'] = (string) $f['date_from'] . ' 00:00:00';
    }
    if (!empty($f['date_to'])) {
      $where[] = 'o.created_at <= :dt';
      $params[':dt'] = (string) $f['date_to'] . ' 23:59:59';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    /* sort */
    $sortMap = [
      'newest' => 'o.created_at DESC, o.id DESC',
      'oldest' => 'o.created_at ASC, o.id ASC',
      'total' => 'o.total DESC',
      'total_asc' => 'o.total ASC',
    ];
    $sortKey = strtolower((string) ($f['sort'] ?? 'newest'));
    $orderBy = $sortMap[$sortKey] ?? $sortMap['newest'];

    /* pagination */
    $page = max(1, (int) ($f['page'] ?? 1));
    $perPage = (int) ($f['per_page'] ?? $f['limit'] ?? 10);
    $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
    $offset = ($page - 1) * $perPage;

    /* total */
    $st = $this->db->prepare("SELECT COUNT(*) FROM orders o $whereSql");
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    /* rows with item count + first item name for a nice preview */
    $sql = "SELECT o.*,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                       (SELECT oi.product_name
                          FROM order_items oi
                         WHERE oi.order_id = o.id
                      ORDER BY oi.id ASC
                         LIMIT 1) AS first_item_name
                  FROM orders o
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

    $rows = $st->fetchAll();

    /* fetch item summaries for all orders in one go (avoid N+1) */
    $orderIds = array_column($rows, 'id');
    $itemsByOrder = $this->itemsForOrders($orderIds);

    $items = array_map(function (array $r) use ($itemsByOrder) {
      $order = $this->presentOrder($r);
      $order['items'] = $itemsByOrder[(int) $r['id']] ?? [];
      return $order;
    }, $rows);

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $perPage,
      'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
    ];
  }

  /** Full order details (must belong to $userId). */
  public function getOrder(int $orderId, int $userId): ?array
  {
    $sql = "SELECT o.*,
                       a.first_name, a.last_name, a.phone,
                       a.address_line, a.city, a.state, a.postal_code, a.country
                  FROM orders o
                  LEFT JOIN addresses a ON a.id = o.address_id
                 WHERE o.id = :id
                   AND o.user_id = :user_id
                 LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute([':id' => $orderId, ':user_id' => $userId]);
    $row = $st->fetch();

    if (!$row) {
      return null;
    }

    $order = $this->presentOrder($row);
    $order['items'] = $this->itemsForOrders([$orderId])[$orderId] ?? [];
    $order['address'] = [
      'first_name' => $row['first_name'] ?? null,
      'last_name' => $row['last_name'] ?? null,
      'phone' => $row['phone'] ?? null,
      'address_line' => $row['address_line'] ?? null,
      'city' => $row['city'] ?? null,
      'state' => $row['state'] ?? null,
      'postal_code' => $row['postal_code'] ?? null,
      'country' => $row['country'] ?? null,
    ];
    $order['payment'] = $this->getPayment($orderId);

    return $order;
  }

  /** Dashboard counters for the customer. */
  public function getStats(int $userId): array
  {
    if ($userId <= 0) {
      return [
        'total' => 0,
        'pending' => 0,
        'processing' => 0,
        'shipped' => 0,
        'delivered' => 0,
        'cancelled' => 0,
        'spent' => 0.0,
      ];
    }

    $sql = "SELECT
                    COUNT(*)                             AS total,
                    SUM(order_status = 'pending')        AS pending,
                    SUM(order_status = 'processing')     AS processing,
                    SUM(order_status = 'shipped')        AS shipped,
                    SUM(order_status = 'delivered')      AS delivered,
                    SUM(order_status = 'cancelled')      AS cancelled,
                    COALESCE(SUM(CASE WHEN order_status <> 'cancelled'
                                      THEN total ELSE 0 END), 0) AS spent
                  FROM orders
                 WHERE user_id = :uid";
    $st = $this->db->prepare($sql);
    $st->execute([':uid' => $userId]);
    $row = $st->fetch() ?: [];

    return [
      'total' => (int) ($row['total'] ?? 0),
      'pending' => (int) ($row['pending'] ?? 0),
      'processing' => (int) ($row['processing'] ?? 0),
      'shipped' => (int) ($row['shipped'] ?? 0),
      'delivered' => (int) ($row['delivered'] ?? 0),
      'cancelled' => (int) ($row['cancelled'] ?? 0),
      'spent' => round((float) ($row['spent'] ?? 0), 2),
    ];
  }

  /** Cancel an order (only if still cancellable). */
  public function cancelOrder(int $orderId, int $userId): array
  {
    $st = $this->db->prepare(
      "SELECT id, order_number, order_status, payment_status
               FROM orders
              WHERE id = :id AND user_id = :uid
              LIMIT 1"
    );
    $st->execute([':id' => $orderId, ':uid' => $userId]);
    $row = $st->fetch();

    if (!$row) {
      return ['success' => false, 'message' => 'Order not found.'];
    }

    if (!in_array($row['order_status'], self::CANCELLABLE, true)) {
      return [
        'success' => false,
        'message' => "This order can no longer be cancelled (it is already {$row['order_status']}).",
      ];
    }

    try {
      $this->db->beginTransaction();

      $up = $this->db->prepare(
        "UPDATE orders
                    SET order_status = 'cancelled',
                        updated_at   = NOW()
                  WHERE id = :id AND user_id = :uid"
      );
      $up->execute([':id' => $orderId, ':uid' => $userId]);

      /* Put the stock back (products + variants) - same as the admin cancel */
      $itemsSt = $this->db->prepare(
        "SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = :oid"
      );
      $itemsSt->execute([':oid' => $orderId]);
      $upProduct = $this->db->prepare(
        "UPDATE products SET stock = stock + :q, updated_at = NOW() WHERE id = :pid"
      );
      $upVariant = $this->db->prepare(
        "UPDATE product_variants SET stock = stock + :q, updated_at = NOW() WHERE id = :vid"
      );
      foreach ($itemsSt->fetchAll() as $it) {
        $upProduct->execute([':q' => (int) $it['quantity'], ':pid' => (int) $it['product_id']]);
        if (!empty($it['variant_id'])) {
          $upVariant->execute([':q' => (int) $it['quantity'], ':vid' => (int) $it['variant_id']]);
        }
      }

      /* Give the coupon use back so a cancelled order doesn't burn the usage limit */
      $this->db->prepare(
        "UPDATE coupons c JOIN orders o ON o.coupon_id = c.id
            SET c.used_count = GREATEST(c.used_count - 1, 0)
          WHERE o.id = :id"
      )->execute([':id' => $orderId]);

      /* Close the payment record + order payment status (pending -> failed, paid -> refunded) */
      $this->db->prepare(
        "UPDATE payments
            SET status = CASE WHEN status = 'completed' THEN 'refunded' ELSE 'failed' END,
                updated_at = NOW()
          WHERE order_id = :id"
      )->execute([':id' => $orderId]);
      $this->db->prepare(
        "UPDATE orders
            SET payment_status = CASE WHEN payment_status = 'paid' THEN 'refunded' ELSE 'failed' END
          WHERE id = :id"
      )->execute([':id' => $orderId]);

      /* Log to audit trail (best-effort) */
      $this->logAudit('order_cancelled', $orderId, [
        'order_number' => $row['order_number'],
        'by' => 'customer',
      ]);

      $this->db->commit();

      return [
        'success' => true,
        'message' => 'Order cancelled successfully.',
        'id' => $orderId,
      ];
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[ORDERS][CANCEL] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not cancel the order.'];
    }
  }

  public function errors(): array
  {
    return $this->errors;
  }

  /* =============================================================
   |  INTERNAL
   * ============================================================= */

  /** Bulk-fetch items for a set of order ids. */
  private function itemsForOrders(array $orderIds): array
  {
    $orderIds = array_values(array_filter(array_map('intval', $orderIds), fn($i) => $i > 0));
    if (!$orderIds) {
      return [];
    }

    $in = implode(',', array_fill(0, count($orderIds), '?'));

    /* main item rows */
    $sql = "SELECT oi.*,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = oi.product_id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM order_items oi
                 WHERE oi.order_id IN ($in)
              ORDER BY oi.id ASC";
    $st = $this->db->prepare($sql);
    $st->execute($orderIds);
    $rows = $st->fetchAll();

    $out = [];
    foreach ($rows as $r) {
      $oid = (int) $r['order_id'];
      $out[$oid] ??= [];
      $out[$oid][] = [
        'id' => (int) $r['id'],
        'product_id' => (int) $r['product_id'],
        'variant_id' => $r['variant_id'] !== null ? (int) $r['variant_id'] : null,
        'product_name' => $r['product_name'],
        'sku' => $r['sku'],
        'quantity' => (int) $r['quantity'],
        'unit_price' => (float) $r['unit_price'],
        'subtotal' => (float) $r['subtotal'],
        'image' => $r['image'] ?? null,
      ];
    }

    return $out;
  }

  /** Latest payment row for an order. */
  private function getPayment(int $orderId): ?array
  {
    $st = $this->db->prepare(
      "SELECT id, transaction_id, payment_method, amount, status, paid_at
               FROM payments
              WHERE order_id = :id
           ORDER BY id DESC
              LIMIT 1"
    );
    $st->execute([':id' => $orderId]);
    $r = $st->fetch();
    if (!$r) {
      return null;
    }
    return [
      'id' => (int) $r['id'],
      'transaction_id' => $r['transaction_id'],
      'payment_method' => $r['payment_method'],
      'amount' => (float) $r['amount'],
      'status' => $r['status'],
      'paid_at' => $r['paid_at'],
    ];
  }

  private function presentOrder(array $r): array
  {
    $orderStatus = strtolower((string) $r['order_status']);
    $payStatus = strtolower((string) $r['payment_status']);

    return [
      'id' => (int) $r['id'],
      'order_number' => $r['order_number'],
      'subtotal' => (float) $r['subtotal'],
      'discount' => (float) $r['discount'],
      'shipping' => (float) $r['shipping'],
      'tax' => (float) $r['tax'],
      'total' => (float) $r['total'],
      'payment_status' => ucfirst($payStatus),
      'payment_raw' => $payStatus,
      'order_status' => ucfirst($orderStatus),
      'order_raw' => $orderStatus,
      'can_cancel' => in_array($orderStatus, self::CANCELLABLE, true),
      'notes' => $r['notes'] ?? null,
      'item_count' => (int) ($r['item_count'] ?? 0),
      'first_item_name' => $r['first_item_name'] ?? null,
      'created_at' => $r['created_at'] ?? null,
      'updated_at' => $r['updated_at'] ?? null,
    ];
  }

  private function emptyPage(): array
  {
    return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
  }

  private function escapeLike(string $s): string
  {
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);
  }

  private function logAudit(string $action, int $entityId, array $details = []): void
  {
    try {
      $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

      $st = $this->db->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details)
                 VALUES (:user_id, :action, 'orders', :entity_id, :ip, :ua, :details)"
      );
      $st->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':entity_id' => $entityId,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
      ]);
    } catch (Throwable $e) {
      error_log('[ORDERS][AUDIT] ' . $e->getMessage());
    }
  }
}