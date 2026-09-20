<?php
declare(strict_types=1);

/* ==================================================================
 |  Admin_orders_process  —  ShopVerse Admin
 |
 |  • Lists ALL orders (search, filter, paginate, sort)
 |  • Full order details (customer + address + items + payment)
 |  • Update order_status with transition validation
 |  • Update payment_status
 |  • Cancel restores stock, marks payment cancelled/refunded
 |  • Stats for the dashboard strip
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

class Admin_orders_process
{
  private PDO $db;

  private const ALLOWED_ORDER_STATUS = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
  private const ALLOWED_PAY_STATUS = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'];
  private const MAX_PER_PAGE = 200;

  /**
   * Allowed status transitions.
   * Key = current, values = what you're allowed to move to.
   * 'delivered' and 'cancelled' are terminal (cannot be changed by admin).
   */
  private const TRANSITIONS = [
    'pending' => ['processing', 'shipped', 'cancelled'],
    'processing' => ['shipped', 'cancelled'],
    'shipped' => ['delivered'],
    'delivered' => [],
    'cancelled' => [],
  ];

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /* =============================================================
   |  LIST
   * ============================================================= */

  public function listOrders(array $f = []): array
  {
    [$where, $params] = $this->buildWhere($f);
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $sortMap = [
      'newest' => 'o.created_at DESC, o.id DESC',
      'oldest' => 'o.created_at ASC, o.id ASC',
      'total' => 'o.total DESC',
      'total_asc' => 'o.total ASC',
      'status' => 'o.order_status ASC, o.created_at DESC',
    ];
    $sortKey = strtolower((string) ($f['sort'] ?? 'newest'));
    $orderBy = $sortMap[$sortKey] ?? $sortMap['newest'];

    $page = max(1, (int) ($f['page'] ?? 1));
    $perPage = (int) ($f['per_page'] ?? $f['limit'] ?? 20);
    $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
    $offset = ($page - 1) * $perPage;

    $countSql = "SELECT COUNT(*)
                       FROM orders o
                       LEFT JOIN users u ON u.id = o.user_id
                       $whereSql";
    $st = $this->db->prepare($countSql);
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    $sql = "SELECT o.*,
                       u.name  AS customer_name,
                       u.email AS customer_email,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                       (SELECT oi.product_name
                          FROM order_items oi
                         WHERE oi.order_id = o.id
                      ORDER BY oi.id ASC LIMIT 1) AS first_item_name
                  FROM orders o
                  LEFT JOIN users u ON u.id = o.user_id
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

    $items = array_map([$this, 'present'], $st->fetchAll());

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $perPage,
      'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
    ];
  }

  /* =============================================================
   |  GET ONE (full detail)
   * ============================================================= */

  public function getOrder(int $id): ?array
  {
    $sql = "SELECT o.*,
                       u.name  AS customer_name,
                       u.email AS customer_email,
                       u.phone AS customer_phone,
                       a.first_name, a.last_name, a.phone AS address_phone,
                       a.address_line, a.city, a.state, a.postal_code, a.country
                  FROM orders o
                  LEFT JOIN users u ON u.id = o.user_id
                  LEFT JOIN addresses a ON a.id = o.address_id
                 WHERE o.id = :id
                 LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
      return null;
    }

    $order = $this->present($row);
    $order['items'] = $this->itemsFor($id);
    $order['payment'] = $this->paymentFor($id);
    $order['address'] = [
      'first_name' => $row['first_name'] ?? null,
      'last_name' => $row['last_name'] ?? null,
      'phone' => $row['address_phone'] ?? null,
      'address_line' => $row['address_line'] ?? null,
      'city' => $row['city'] ?? null,
      'state' => $row['state'] ?? null,
      'postal_code' => $row['postal_code'] ?? null,
      'country' => $row['country'] ?? null,
    ];
    $order['allowed_transitions'] = self::TRANSITIONS[$order['order_raw']] ?? [];

    return $order;
  }

  /* =============================================================
   |  MUTATIONS
   * ============================================================= */

  /** Update order status with transition validation. */
  public function updateOrderStatus(int $id, string $newStatus): array
  {
    $newStatus = strtolower(trim($newStatus));
    if (!in_array($newStatus, self::ALLOWED_ORDER_STATUS, true)) {
      return ['success' => false, 'message' => 'Invalid status.'];
    }

    $st = $this->db->prepare(
      "SELECT id, order_number, order_status FROM orders WHERE id = :id LIMIT 1"
    );
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
      return ['success' => false, 'message' => 'Order not found.'];
    }

    $current = strtolower((string) $row['order_status']);

    if ($current === $newStatus) {
      return ['success' => true, 'message' => 'Status unchanged.', 'status' => $newStatus];
    }

    $allowed = self::TRANSITIONS[$current] ?? [];
    if (!in_array($newStatus, $allowed, true)) {
      return [
        'success' => false,
        'message' => "Cannot change status from \"$current\" to \"$newStatus\".",
      ];
    }

    try {
      $this->db->beginTransaction();

      $up = $this->db->prepare(
        "UPDATE orders
                    SET order_status = :s,
                        updated_at   = NOW()
                  WHERE id = :id"
      );
      $up->execute([':s' => $newStatus, ':id' => $id]);

      /* Side effects */
      if ($newStatus === 'delivered') {
        /* Mark COD orders paid on delivery */
        $pay = $this->db->prepare(
          "UPDATE payments
                        SET status = 'completed', paid_at = NOW(), updated_at = NOW()
                      WHERE order_id = :id AND payment_method = 'cod' AND status <> 'completed'"
        );
        $pay->execute([':id' => $id]);

        $this->db->prepare(
          "UPDATE orders SET payment_status = 'paid' WHERE id = :id AND payment_status = 'pending'"
        )->execute([':id' => $id]);
      }

      if ($newStatus === 'cancelled') {
        $this->restoreStock($id);

        /* give the coupon use back */
        $this->db->prepare(
          "UPDATE coupons c JOIN orders o ON o.coupon_id = c.id
              SET c.used_count = GREATEST(c.used_count - 1, 0)
            WHERE o.id = :id"
        )->execute([':id' => $id]);

        /* Mark payment status accordingly */
        $this->db->prepare(
          "UPDATE payments
                        SET status = CASE
                            WHEN status = 'completed' THEN 'refunded'
                            ELSE 'failed'
                        END,
                        updated_at = NOW()
                      WHERE order_id = :id"
        )->execute([':id' => $id]);

        $this->db->prepare(
          "UPDATE orders
                        SET payment_status = CASE
                            WHEN payment_status = 'paid' THEN 'refunded'
                            ELSE 'failed'
                        END
                      WHERE id = :id"
        )->execute([':id' => $id]);
      }

      $this->logAudit('order_status_changed', $id, [
        'order_number' => $row['order_number'],
        'from' => $current,
        'to' => $newStatus,
      ]);

      $this->db->commit();

      return [
        'success' => true,
        'message' => 'Order status updated to "' . $newStatus . '".',
        'status' => $newStatus,
        'order' => $this->getOrder($id),
      ];
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[ADMIN_ORDERS][STATUS] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not update the order.'];
    }
  }

  /** Update payment status independently (e.g. mark bank transfer paid). */
  public function updatePaymentStatus(int $id, string $newStatus): array
  {
    $newStatus = strtolower(trim($newStatus));
    if (!in_array($newStatus, self::ALLOWED_PAY_STATUS, true)) {
      return ['success' => false, 'message' => 'Invalid payment status.'];
    }

    $st = $this->db->prepare("SELECT order_number FROM orders WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $orderNumber = $st->fetchColumn();
    if (!$orderNumber) {
      return ['success' => false, 'message' => 'Order not found.'];
    }

    try {
      $this->db->beginTransaction();

      $this->db->prepare(
        "UPDATE orders SET payment_status = :s, updated_at = NOW() WHERE id = :id"
      )->execute([':s' => $newStatus, ':id' => $id]);

      /* mirror onto payments row */
      $mapped = match ($newStatus) {
        'paid' => 'completed',
        'refunded' => 'refunded',
        'failed' => 'failed',
        'partially_refunded' => 'refunded',
        default => 'pending',
      };
      $paidAt = $newStatus === 'paid' ? date('Y-m-d H:i:s') : null;

      $this->db->prepare(
        "UPDATE payments
                    SET status = :s,
                        paid_at = COALESCE(:paid_at, paid_at),
                        updated_at = NOW()
                  WHERE order_id = :id"
      )->execute([':s' => $mapped, ':paid_at' => $paidAt, ':id' => $id]);

      $this->logAudit('order_payment_changed', $id, [
        'order_number' => $orderNumber,
        'to' => $newStatus,
      ]);

      $this->db->commit();

      return [
        'success' => true,
        'message' => 'Payment status updated.',
        'status' => $newStatus,
        'order' => $this->getOrder($id),
      ];
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[ADMIN_ORDERS][PAY] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not update the payment.'];
    }
  }

  /** Delete an order (rare — audit logged). */
  public function deleteOrder(int $id): array
  {
    $st = $this->db->prepare("SELECT order_number, order_status FROM orders WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
      return ['success' => false, 'message' => 'Order not found.'];
    }

    if (!in_array(strtolower((string) $row['order_status']), ['cancelled', 'pending'], true)) {
      return [
        'success' => false,
        'message' => 'Only pending or cancelled orders can be deleted.',
      ];
    }

    try {
      $this->db->beginTransaction();

      /* restore stock first (in case of pending) */
      if (strtolower((string) $row['order_status']) !== 'cancelled') {
        $this->restoreStock($id);
      }

      $this->db->prepare("DELETE FROM orders WHERE id = :id")->execute([':id' => $id]);

      $this->logAudit('order_deleted', $id, [
        'order_number' => $row['order_number'],
      ]);

      $this->db->commit();
      return ['success' => true, 'message' => 'Order deleted.'];
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[ADMIN_ORDERS][DELETE] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not delete the order.'];
    }
  }

  /* =============================================================
   |  STATS
   * ============================================================= */

  public function getStats(): array
  {
    $sql = "SELECT
                    COUNT(*)                                          AS total,
                    SUM(order_status = 'pending')                     AS pending,
                    SUM(order_status = 'processing')                  AS processing,
                    SUM(order_status = 'shipped')                     AS shipped,
                    SUM(order_status = 'delivered')                   AS delivered,
                    SUM(order_status = 'cancelled')                   AS cancelled,
                    COALESCE(SUM(CASE WHEN order_status <> 'cancelled'
                                      THEN total ELSE 0 END), 0)      AS revenue,
                    COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE()
                                       AND order_status <> 'cancelled'
                                      THEN total ELSE 0 END), 0)      AS revenue_today,
                    SUM(DATE(created_at) = CURDATE())                 AS orders_today
                FROM orders";
    $row = $this->db->query($sql)->fetch() ?: [];

    return [
      'total' => (int) ($row['total'] ?? 0),
      'pending' => (int) ($row['pending'] ?? 0),
      'processing' => (int) ($row['processing'] ?? 0),
      'shipped' => (int) ($row['shipped'] ?? 0),
      'delivered' => (int) ($row['delivered'] ?? 0),
      'cancelled' => (int) ($row['cancelled'] ?? 0),
      'revenue' => round((float) ($row['revenue'] ?? 0), 2),
      'revenue_today' => round((float) ($row['revenue_today'] ?? 0), 2),
      'orders_today' => (int) ($row['orders_today'] ?? 0),
    ];
  }

  /* =============================================================
   |  INTERNAL
   * ============================================================= */

  private function buildWhere(array $f): array
  {
    $where = ['1=1'];
    $params = [];

    $status = strtolower(trim((string) ($f['status'] ?? '')));
    if ($status !== '' && $status !== 'all' && in_array($status, self::ALLOWED_ORDER_STATUS, true)) {
      $where[] = 'o.order_status = :status';
      $params[':status'] = $status;
    }

    $payStatus = strtolower(trim((string) ($f['payment_status'] ?? '')));
    if ($payStatus !== '' && $payStatus !== 'all' && in_array($payStatus, self::ALLOWED_PAY_STATUS, true)) {
      $where[] = 'o.payment_status = :pay';
      $params[':pay'] = $payStatus;
    }

    $search = trim((string) ($f['search'] ?? $f['q'] ?? ''));
    if ($search !== '') {
      $like = '%' . $this->escapeLike($search) . '%';
      $where[] = '(o.order_number LIKE :s1 OR u.name LIKE :s2 OR u.email LIKE :s3)';
      $params[':s1'] = $like;
      $params[':s2'] = $like;
      $params[':s3'] = $like;
    }

    if (!empty($f['date_from'])) {
      $where[] = 'o.created_at >= :df';
      $params[':df'] = (string) $f['date_from'] . ' 00:00:00';
    }
    if (!empty($f['date_to'])) {
      $where[] = 'o.created_at <= :dt';
      $params[':dt'] = (string) $f['date_to'] . ' 23:59:59';
    }

    return [$where, $params];
  }

  private function itemsFor(int $orderId): array
  {
    $sql = "SELECT oi.*,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = oi.product_id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM order_items oi
                 WHERE oi.order_id = :oid
              ORDER BY oi.id ASC";
    $st = $this->db->prepare($sql);
    $st->execute([':oid' => $orderId]);

    return array_map(static fn(array $r) => [
      'id' => (int) $r['id'],
      'product_id' => (int) $r['product_id'],
      'variant_id' => $r['variant_id'] !== null ? (int) $r['variant_id'] : null,
      'product_name' => $r['product_name'],
      'sku' => $r['sku'],
      'quantity' => (int) $r['quantity'],
      'unit_price' => (float) $r['unit_price'],
      'subtotal' => (float) $r['subtotal'],
      'image' => $r['image'] ?? null,
    ], $st->fetchAll());
  }

  private function paymentFor(int $orderId): ?array
  {
    $st = $this->db->prepare(
      "SELECT id, transaction_id, payment_method, amount, status, paid_at
               FROM payments
              WHERE order_id = :id
           ORDER BY id DESC LIMIT 1"
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

  /** Restore product stock for a cancelled order (best-effort). */
  private function restoreStock(int $orderId): void
  {
    $st = $this->db->prepare(
      "SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = :oid"
    );
    $st->execute([':oid' => $orderId]);
    $items = $st->fetchAll();

    $upProduct = $this->db->prepare(
      "UPDATE products SET stock = stock + :q, updated_at = NOW() WHERE id = :pid"
    );
    $upVariant = $this->db->prepare(
      "UPDATE product_variants SET stock = stock + :q, updated_at = NOW() WHERE id = :vid"
    );

    foreach ($items as $it) {
      $upProduct->execute([
        ':q' => (int) $it['quantity'],
        ':pid' => (int) $it['product_id'],
      ]);
      if ($it['variant_id']) {
        $upVariant->execute([
          ':q' => (int) $it['quantity'],
          ':vid' => (int) $it['variant_id'],
        ]);
      }
    }
  }

  private function present(array $r): array
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
      'payment_status' => ucwords(str_replace('_', ' ', $payStatus)),
      'payment_raw' => $payStatus,
      'order_status' => ucfirst($orderStatus),
      'order_raw' => $orderStatus,
      'notes' => $r['notes'] ?? null,
      'customer_id' => isset($r['user_id']) ? (int) $r['user_id'] : null,
      'customer_name' => $r['customer_name'] ?? null,
      'customer_email' => $r['customer_email'] ?? null,
      'customer_phone' => $r['customer_phone'] ?? null,
      'item_count' => (int) ($r['item_count'] ?? 0),
      'first_item_name' => $r['first_item_name'] ?? null,
      'created_at' => $r['created_at'] ?? null,
      'updated_at' => $r['updated_at'] ?? null,
    ];
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
                 VALUES (:uid, :action, 'orders', :eid, :ip, :ua, :details)"
      );
      $st->execute([
        ':uid' => $userId,
        ':action' => $action,
        ':eid' => $entityId,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
      ]);
    } catch (Throwable $e) {
      error_log('[ADMIN_ORDERS][AUDIT] ' . $e->getMessage());
    }
  }
}