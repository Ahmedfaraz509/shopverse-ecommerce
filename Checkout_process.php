<?php
declare(strict_types=1);

/* ==================================================================
 |  Checkout_process  —  ShopVerse
 |
 |  Turns the logged-in user's cart into a real order:
 |    • addresses row
 |    • orders row
 |    • order_items rows
 |    • payments row
 |    • products.stock decrement (per line)
 |    • coupons.used_count increment
 |    • cart emptied + coupon session cleared
 |
 |  All inside one transaction — nothing partial.
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

class Checkout_process
{
  private PDO $db;
  private int $userId;
  private array $errors = [];

  /** UI label  →  DB enum */
  private const PAYMENT_MAP = [
    'cash on delivery' => 'cod',
    'cod' => 'cod',
    'credit card' => 'card',
    'card' => 'card',
    'bank transfer' => 'bank_transfer',
    'bank_transfer' => 'bank_transfer',
    'wallet' => 'wallet',
  ];

  private const COUPON_SESSION = 'cart_coupon';

  /* -------- shipping/tax must MATCH Cart_process.php -------- */
  private const FREE_SHIPPING_OVER = 100.00;
  private const FLAT_SHIPPING = 10.00;
  private const TAX_RATE = 0.00;

  public function __construct(?PDO $db = null, ?int $userId = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (session_status() === PHP_SESSION_NONE) {
      @session_start();
    }

    if ($userId === null) {
      $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    }
    $this->userId = (int) $userId;
  }

  /* =============================================================
   |  PUBLIC API
   * ============================================================= */

  /** Everything the checkout page needs to render (cart + user). */
  public function getCheckoutData(): array
  {
    if ($this->userId <= 0) {
      return ['success' => false, 'message' => 'Not logged in.'];
    }

    $cart = $this->loadCart();
    if (!$cart['items']) {
      return ['success' => false, 'message' => 'Your cart is empty.'];
    }

    $coupon = $this->loadCoupon();
    $summary = $this->computeSummary($cart['items'], $coupon);

    return [
      'success' => true,
      'data' => [
        'user' => $this->loadUser(),
        'items' => $cart['items'],
        'coupon' => $coupon,
        'summary' => $summary,
      ],
    ];
  }

  /** Place the order. */
  public function placeOrder(array $data): array
  {
    $this->errors = [];

    if ($this->userId <= 0) {
      return ['success' => false, 'message' => 'Please log in to place an order.'];
    }

    /* ---- validate ---- */
    $clean = $this->validate($data);
    if ($this->errors) {
      return [
        'success' => false,
        'message' => 'Please fix the highlighted fields.',
        'errors' => $this->errors,
      ];
    }

    /* ---- load cart fresh ---- */
    $cart = $this->loadCart();
    if (!$cart['items']) {
      return ['success' => false, 'message' => 'Your cart is empty.'];
    }

    /* ---- stock re-check (prevents oversell) ---- */
    foreach ($cart['items'] as $it) {
      if (!$it['in_stock']) {
        return [
          'success' => false,
          'message' => "Not enough stock for \"{$it['name']}\" "
            . "(only {$it['available_stock']} left). "
            . 'Please update your cart.',
        ];
      }
    }

    /* ---- coupon ---- */
    $coupon = $this->loadCoupon();
    $summary = $this->computeSummary($cart['items'], $coupon);

    try {
      $this->db->beginTransaction();

      /* 0) lock the product rows and re-check stock INSIDE the transaction,
       *    so two customers can't both buy the last unit */
      foreach ($cart['items'] as $it) {
        $lk = $this->db->prepare("SELECT stock, status FROM products WHERE id = :pid FOR UPDATE");
        $lk->execute([':pid' => $it['product_id']]);
        $prow = $lk->fetch();
        $left = $prow ? (int) $prow['stock'] : 0;
        $active = $prow && strtolower((string) $prow['status']) === 'active';

        if ($it['variant_id']) {
          $lv = $this->db->prepare("SELECT stock, status FROM product_variants WHERE id = :vid FOR UPDATE");
          $lv->execute([':vid' => $it['variant_id']]);
          $vrow = $lv->fetch();
          $left = $vrow ? (int) $vrow['stock'] : 0;
          $active = $active && $vrow && strtolower((string) $vrow['status']) === 'active';
        }

        if (!$active || $left < (int) $it['quantity']) {
          $this->db->rollBack();
          return [
            'success' => false,
            'message' => "Not enough stock for \"{$it['name']}\" (only " . ($active ? $left : 0)
              . ' left). Please update your cart.',
          ];
        }
      }

      /* 1) address */
      $addressId = $this->insertAddress($clean);

      /* 2) order */
      $orderNumber = $this->generateOrderNumber();
      $paymentEnum = self::PAYMENT_MAP[strtolower($clean['payment'])] ?? 'cod';

      $ins = $this->db->prepare(
        "INSERT INTO orders
                    (user_id, address_id, coupon_id, order_number,
                     subtotal, discount, shipping, tax, total,
                     payment_status, order_status, notes)
                 VALUES
                    (:uid, :aid, :cid, :num,
                     :subtotal, :discount, :shipping, :tax, :total,
                     :pay_status, 'pending', :notes)"
      );
      $ins->execute([
        ':uid' => $this->userId,
        ':aid' => $addressId,
        ':cid' => $coupon['id'] ?? null,
        ':num' => $orderNumber,
        ':subtotal' => $summary['subtotal'],
        ':discount' => $summary['discount'],
        ':shipping' => $summary['shipping'],
        ':tax' => $summary['tax'],
        ':total' => $summary['total'],
        ':pay_status' => ($paymentEnum === 'cod' ? 'pending' : 'pending'),
        ':notes' => $clean['notes'],
      ]);
      $orderId = (int) $this->db->lastInsertId();

      /* 3) order items + stock decrement */
      $itemStmt = $this->db->prepare(
        "INSERT INTO order_items
                    (order_id, product_id, variant_id, product_name, sku,
                     quantity, unit_price, subtotal)
                 VALUES
                    (:oid, :pid, :vid, :name, :sku, :qty, :price, :sub)"
      );

      foreach ($cart['items'] as $it) {
        $itemStmt->execute([
          ':oid' => $orderId,
          ':pid' => $it['product_id'],
          ':vid' => $it['variant_id'],
          ':name' => $it['name'],
          ':sku' => $it['sku'],
          ':qty' => $it['quantity'],
          ':price' => $it['unit_price'],
          ':sub' => $it['line_total'],
        ]);

        /* decrement product stock */
        $dec = $this->db->prepare(
          "UPDATE products
                        SET stock = GREATEST(stock - :q, 0),
                            updated_at = NOW()
                      WHERE id = :pid"
        );
        $dec->execute([':q' => $it['quantity'], ':pid' => $it['product_id']]);

        /* decrement variant stock too if applicable */
        if ($it['variant_id']) {
          $decV = $this->db->prepare(
            "UPDATE product_variants
                            SET stock = GREATEST(stock - :q, 0),
                                updated_at = NOW()
                          WHERE id = :vid"
          );
          $decV->execute([':q' => $it['quantity'], ':vid' => $it['variant_id']]);
        }
      }

      /* 4) payment record */
      $pay = $this->db->prepare(
        "INSERT INTO payments
                    (order_id, transaction_id, payment_method, amount, status)
                 VALUES
                    (:oid, NULL, :method, :amount, 'pending')"
      );
      $pay->execute([
        ':oid' => $orderId,
        ':method' => $paymentEnum,
        ':amount' => $summary['total'],
      ]);

      /* 5) coupon usage */
      if (!empty($coupon['id'])) {
        $this->db->prepare(
          "UPDATE coupons
                        SET used_count = used_count + 1, updated_at = NOW()
                      WHERE id = :id"
        )->execute([':id' => $coupon['id']]);
      }

      /* 6) empty the cart */
      $this->db->prepare(
        "DELETE ci FROM cart_items ci
                  JOIN carts c ON c.id = ci.cart_id
                 WHERE c.user_id = :uid"
      )->execute([':uid' => $this->userId]);

      unset($_SESSION[self::COUPON_SESSION]);

      /* 7) audit log */
      $this->logAudit('order_placed', $orderId, [
        'order_number' => $orderNumber,
        'total' => $summary['total'],
        'payment' => $paymentEnum,
        'items' => count($cart['items']),
      ]);

      $this->db->commit();

      return [
        'success' => true,
        'message' => 'Order placed successfully.',
        'order' => [
          'id' => $orderId,
          'order_number' => $orderNumber,
          'total' => $summary['total'],
          'subtotal' => $summary['subtotal'],
          'discount' => $summary['discount'],
          'shipping' => $summary['shipping'],
          'tax' => $summary['tax'],
          'payment_method' => $paymentEnum,
          'payment_label' => $clean['payment'],
          'email' => $clean['email'],
          'item_count' => count($cart['items']),
        ],
      ];
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[CHECKOUT] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not place the order. Please try again.'];
    }
  }

  public function errors(): array
  {
    return $this->errors;
  }

  /* =============================================================
   |  VALIDATION
   * ============================================================= */

  private function validate(array $d): array
  {
    $this->errors = [];
    $clean = [];

    $clean['firstName'] = trim((string) ($d['firstName'] ?? $d['first_name'] ?? ''));
    if (mb_strlen($clean['firstName']) < 2) {
      $this->errors['firstName'] = 'Please enter your first name.';
    }

    $clean['lastName'] = trim((string) ($d['lastName'] ?? $d['last_name'] ?? ''));
    if (mb_strlen($clean['lastName']) < 2) {
      $this->errors['lastName'] = 'Please enter your last name.';
    }

    $clean['email'] = trim((string) ($d['email'] ?? ''));
    if (!filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
      $this->errors['email'] = 'Please enter a valid email.';
    }

    $clean['phone'] = trim((string) ($d['phone'] ?? ''));
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $clean['phone'])) {
      $this->errors['phone'] = 'Please enter a valid phone number.';
    }

    $clean['address'] = trim((string) ($d['address'] ?? $d['address_line'] ?? ''));
    if ($clean['address'] === '') {
      $this->errors['address'] = 'Please enter your address.';
    }

    $clean['city'] = trim((string) ($d['city'] ?? ''));
    if ($clean['city'] === '') {
      $this->errors['city'] = 'Please enter your city.';
    }

    $clean['state'] = trim((string) ($d['state'] ?? ''));
    if ($clean['state'] === '') {
      $this->errors['state'] = 'Please enter your state.';
    }

    $clean['postal'] = trim((string) ($d['postal'] ?? $d['postal_code'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9\s\-]{3,10}$/', $clean['postal'])) {
      $this->errors['postal'] = 'Please enter a valid postal code.';
    }

    $clean['country'] = trim((string) ($d['country'] ?? ''));
    if ($clean['country'] === '') {
      $this->errors['country'] = 'Please select your country.';
    }

    $clean['notes'] = trim((string) ($d['notes'] ?? ''));
    if (mb_strlen($clean['notes']) > 2000) {
      $clean['notes'] = mb_substr($clean['notes'], 0, 2000);
    }

    $clean['payment'] = trim((string) ($d['payment'] ?? ''));
    if (!array_key_exists(strtolower($clean['payment']), self::PAYMENT_MAP)) {
      $this->errors['payment'] = 'Please select a payment method.';
    }

    /* terms */
    $terms = $d['terms'] ?? $d['accept_terms'] ?? null;
    if (!in_array($terms, [1, '1', true, 'true', 'on', 'yes'], true)) {
      $this->errors['terms'] = 'You must accept the terms to continue.';
    }

    return $this->errors ? [] : $clean;
  }

  /* =============================================================
   |  CART / COUPON / SUMMARY
   * ============================================================= */

  private function loadCart(): array
  {
    $cartId = $this->ensureUserCart();

    $sql = "SELECT ci.product_id, ci.variant_id, ci.quantity,
                       p.name, p.slug, p.sku, p.price, p.stock, p.status,
                       v.price AS variant_price, v.stock AS variant_stock,
                       v.color, v.size,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM cart_items ci
                  JOIN products p ON p.id = ci.product_id
                  LEFT JOIN product_variants v ON v.id = ci.variant_id
                 WHERE ci.cart_id = :cid
              ORDER BY ci.created_at ASC";

    $st = $this->db->prepare($sql);
    $st->execute([':cid' => $cartId]);
    $rows = $st->fetchAll();

    $items = [];
    foreach ($rows as $r) {
      $unit = $r['variant_price'] !== null
        ? (float) $r['variant_price']
        : (float) $r['price'];
      $stock = $r['variant_id'] && $r['variant_stock'] !== null
        ? (int) $r['variant_stock']
        : (int) $r['stock'];
      $active = strtolower((string) $r['status']) === 'active';
      $qty = (int) $r['quantity'];

      $items[] = [
        'product_id' => (int) $r['product_id'],
        'variant_id' => $r['variant_id'] !== null ? (int) $r['variant_id'] : null,
        'quantity' => $qty,
        'name' => $r['name'],
        'slug' => $r['slug'],
        'sku' => $r['sku'],
        'unit_price' => round($unit, 2),
        'line_total' => round($unit * $qty, 2),
        'image' => $r['image'] ?? null,
        'color' => $r['color'] ?? null,
        'size' => $r['size'] ?? null,
        'available_stock' => $active ? $stock : 0,
        'in_stock' => $active && $stock >= $qty,
      ];
    }

    return ['items' => $items];
  }

  private function loadCoupon(): ?array
  {
    $code = $_SESSION[self::COUPON_SESSION] ?? null;
    if (!$code) {
      return null;
    }

    $st = $this->db->prepare("SELECT * FROM coupons WHERE code = :c LIMIT 1");
    $st->execute([':c' => $code]);
    $c = $st->fetch();

    if (!$c || strtolower($c['status']) !== 'active') {
      unset($_SESSION[self::COUPON_SESSION]);
      return null;
    }

    $now = time();
    if (!empty($c['starts_at']) && strtotime($c['starts_at']) > $now) {
      unset($_SESSION[self::COUPON_SESSION]);
      return null;
    }
    if (!empty($c['expires_at']) && strtotime($c['expires_at']) < $now) {
      unset($_SESSION[self::COUPON_SESSION]);
      return null;
    }
    if (!empty($c['usage_limit']) && (int) $c['used_count'] >= (int) $c['usage_limit']) {
      unset($_SESSION[self::COUPON_SESSION]);
      return null;
    }

    return [
      'id' => (int) $c['id'],
      'code' => $c['code'],
      'description' => $c['description'] ?? null,
      'discount_type' => $c['discount_type'],
      'discount_value' => (float) $c['discount_value'],
      'minimum_order_amount' => $c['minimum_order_amount'] !== null ? (float) $c['minimum_order_amount'] : null,
      'maximum_discount' => $c['maximum_discount'] !== null ? (float) $c['maximum_discount'] : null,
    ];
  }

  private function computeSummary(array $items, ?array $coupon): array
  {
    $subtotal = 0.0;
    foreach ($items as $it) {
      $subtotal += (float) $it['line_total'];
    }
    $subtotal = round($subtotal, 2);

    $discount = 0.0;
    if ($coupon) {
      /* honor minimum_order_amount here too */
      $minOk = empty($coupon['minimum_order_amount'])
        || $subtotal >= (float) $coupon['minimum_order_amount'];

      if ($minOk) {
        if ($coupon['discount_type'] === 'percentage') {
          $discount = $subtotal * ((float) $coupon['discount_value'] / 100);
          if (!empty($coupon['maximum_discount'])) {
            $discount = min($discount, (float) $coupon['maximum_discount']);
          }
        } else {
          $discount = min((float) $coupon['discount_value'], $subtotal);
        }
        $discount = round(max(0, $discount), 2);
      }
    }

    $afterDiscount = max(0, $subtotal - $discount);

    $shipping = 0.0;
    if ($subtotal > 0 && $afterDiscount < self::FREE_SHIPPING_OVER) {
      $shipping = self::FLAT_SHIPPING;
    }

    $tax = round($afterDiscount * self::TAX_RATE, 2);
    $total = round($afterDiscount + $shipping + $tax, 2);

    return [
      'subtotal' => $subtotal,
      'discount' => $discount,
      'shipping' => round($shipping, 2),
      'tax' => $tax,
      'total' => $total,
      'item_count' => array_sum(array_map(fn($i) => (int) $i['quantity'], $items)),
    ];
  }

  /* =============================================================
   |  DB HELPERS
   * ============================================================= */

  private function ensureUserCart(): int
  {
    $st = $this->db->prepare("SELECT id FROM carts WHERE user_id = :uid LIMIT 1");
    $st->execute([':uid' => $this->userId]);
    $id = $st->fetchColumn();
    if ($id) {
      return (int) $id;
    }
    $this->db->prepare("INSERT INTO carts (user_id) VALUES (:uid)")
      ->execute([':uid' => $this->userId]);
    return (int) $this->db->lastInsertId();
  }

  private function insertAddress(array $c): int
  {
    /* Re-use an identical saved address instead of adding a duplicate row on every order */
    $find = $this->db->prepare(
      "SELECT id FROM addresses
        WHERE user_id = :uid AND first_name = :fn AND last_name = :ln AND phone = :ph
          AND address_line = :addr AND city = :city AND state = :state
          AND postal_code = :postal AND country = :country
        ORDER BY id ASC LIMIT 1"
    );
    $find->execute([
      ':uid' => $this->userId,
      ':fn' => $c['firstName'],
      ':ln' => $c['lastName'],
      ':ph' => $c['phone'],
      ':addr' => $c['address'],
      ':city' => $c['city'],
      ':state' => $c['state'],
      ':postal' => $c['postal'],
      ':country' => $c['country'],
    ]);
    $existing = $find->fetchColumn();
    if ($existing) {
      return (int) $existing;
    }

    $st = $this->db->prepare(
      "INSERT INTO addresses
                (user_id, first_name, last_name, phone,
                 address_line, city, state, postal_code, country,
                 address_type, is_default)
             VALUES
                (:uid, :fn, :ln, :ph,
                 :addr, :city, :state, :postal, :country,
                 'both', 0)"
    );
    $st->execute([
      ':uid' => $this->userId,
      ':fn' => $c['firstName'],
      ':ln' => $c['lastName'],
      ':ph' => $c['phone'],
      ':addr' => $c['address'],
      ':city' => $c['city'],
      ':state' => $c['state'],
      ':postal' => $c['postal'],
      ':country' => $c['country'],
    ]);
    return (int) $this->db->lastInsertId();
  }

  private function loadUser(): array
  {
    $st = $this->db->prepare(
      "SELECT id, name, email, phone FROM users WHERE id = :id LIMIT 1"
    );
    $st->execute([':id' => $this->userId]);
    $u = $st->fetch() ?: [];

    $nameParts = preg_split('/\s+/', trim((string) ($u['name'] ?? ''))) ?: [];

    return [
      'id' => (int) ($u['id'] ?? 0),
      'name' => $u['name'] ?? '',
      'email' => $u['email'] ?? '',
      'phone' => $u['phone'] ?? '',
      'first_name' => $nameParts[0] ?? '',
      'last_name' => count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '',
    ];
  }

  /** Unique order number: SV-YYYYMMDD-XXXXXX */
  private function generateOrderNumber(): string
  {
    $prefix = 'SV-' . date('Ymd') . '-';

    for ($i = 0; $i < 10; $i++) {
      $candidate = $prefix . strtoupper(bin2hex(random_bytes(3)));
      $st = $this->db->prepare("SELECT 1 FROM orders WHERE order_number = :n LIMIT 1");
      $st->execute([':n' => $candidate]);
      if (!$st->fetchColumn()) {
        return $candidate;
      }
    }

    return $prefix . strtoupper(bin2hex(random_bytes(5)));
  }

  private function logAudit(string $action, int $entityId, array $details = []): void
  {
    try {
      $st = $this->db->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details)
                 VALUES (:uid, :action, 'orders', :eid, :ip, :ua, :details)"
      );
      $st->execute([
        ':uid' => $this->userId,
        ':action' => $action,
        ':eid' => $entityId,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
      ]);
    } catch (Throwable $e) {
      error_log('[CHECKOUT][AUDIT] ' . $e->getMessage());
    }
  }
}