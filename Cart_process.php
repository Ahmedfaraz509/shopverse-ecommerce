<?php
declare(strict_types=1);

/* ==================================================================
 |  Cart_process  —  ShopVerse
 |
 |  • Guests  → session cart  ($_SESSION['guest_cart'])
 |  • Logged  → DB cart       (carts + cart_items)
 |  • Coupon  → validated against `coupons` table
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

class Cart_process
{
  private PDO $db;
  private ?int $userId;
  private array $errors = [];

  private const SESSION_KEY = 'guest_cart';
  private const COUPON_SESSION = 'cart_coupon';
  private const MAX_QTY_PER_ITEM = 99;

  /* Shipping / tax rules — tune to your business */
  private const FREE_SHIPPING_OVER = 100.00;
  private const FLAT_SHIPPING = 10.00;
  private const TAX_RATE = 0.00;   // 0 = disabled, e.g. 0.05 for 5%

  public function __construct(?PDO $db = null, ?int $userId = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($userId === null && session_status() === PHP_SESSION_ACTIVE) {
      $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
    $this->userId = ($userId && $userId > 0) ? $userId : null;

    if (session_status() === PHP_SESSION_NONE) {
      @session_start();
    }

    if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
      $_SESSION[self::SESSION_KEY] = [];
    }
  }

  /* =============================================================
   |  PUBLIC API
   * ============================================================= */

  /** Full cart: items + summary. */
  public function getCart(): array
  {
    $items = $this->userId ? $this->loadDbItems() : $this->loadSessionItems();
    $coupon = $this->getAppliedCoupon();
    $summary = $this->computeSummary($items, $coupon);

    return [
      'items' => $items,
      'coupon' => $coupon,
      'summary' => $summary,
      'is_guest' => $this->userId === null,
    ];
  }

  /** Add / increment an item. */
  public function addItem(int $productId, int $quantity = 1, ?int $variantId = null): array
  {
    if ($productId <= 0) {
      return ['success' => false, 'message' => 'Invalid product.'];
    }
    $quantity = max(1, min((int) $quantity, self::MAX_QTY_PER_ITEM));

    $product = $this->loadProduct($productId, $variantId);
    if (!$product) {
      return ['success' => false, 'message' => 'Product not found or unavailable.'];
    }
    if ($product['available_stock'] <= 0) {
      return ['success' => false, 'message' => 'This product is out of stock.'];
    }

    $key = $this->itemKey($productId, $variantId);
    $current = $this->currentQuantity($productId, $variantId);
    $target = min($current + $quantity, $product['available_stock'], self::MAX_QTY_PER_ITEM);

    if ($target <= $current) {
      return [
        'success' => false,
        'message' => 'Only ' . $product['available_stock'] . ' in stock.',
      ];
    }

    if ($this->userId) {
      $this->dbUpsert($productId, $variantId, $target);
    } else {
      $_SESSION[self::SESSION_KEY][$key] = $target;
    }

    return [
      'success' => true,
      'message' => 'Added to cart.',
      'cart' => $this->getCart(),
    ];
  }

  /** Set exact quantity. 0 = remove. */
  public function setQuantity(int $productId, int $quantity, ?int $variantId = null): array
  {
    $quantity = max(0, min((int) $quantity, self::MAX_QTY_PER_ITEM));
    $key = $this->itemKey($productId, $variantId);

    if ($quantity === 0) {
      return $this->removeItem($productId, $variantId);
    }

    $product = $this->loadProduct($productId, $variantId);
    if (!$product) {
      return ['success' => false, 'message' => 'Product not found.'];
    }

    $quantity = min($quantity, $product['available_stock']);
    if ($quantity <= 0) {
      return ['success' => false, 'message' => 'Not enough stock.'];
    }

    if ($this->userId) {
      $this->dbUpsert($productId, $variantId, $quantity);
    } else {
      $_SESSION[self::SESSION_KEY][$key] = $quantity;
    }

    return [
      'success' => true,
      'message' => 'Quantity updated.',
      'cart' => $this->getCart(),
    ];
  }

  /** Remove one line. */
  public function removeItem(int $productId, ?int $variantId = null): array
  {
    $key = $this->itemKey($productId, $variantId);

    if ($this->userId) {
      $st = $this->db->prepare(
        "DELETE ci FROM cart_items ci
                  JOIN carts c ON c.id = ci.cart_id
                 WHERE c.user_id = :uid
                   AND ci.product_id = :pid
                   AND " . ($variantId ? 'ci.variant_id = :vid' : 'ci.variant_id IS NULL')
      );
      $bind = [':uid' => $this->userId, ':pid' => $productId];
      if ($variantId) {
        $bind[':vid'] = $variantId;
      }
      $st->execute($bind);
    } else {
      unset($_SESSION[self::SESSION_KEY][$key]);
    }

    return [
      'success' => true,
      'message' => 'Item removed.',
      'cart' => $this->getCart(),
    ];
  }

  /** Clear the whole cart (keeps coupon unless $keepCoupon = false). */
  public function clearCart(bool $keepCoupon = false): array
  {
    if ($this->userId) {
      $st = $this->db->prepare(
        "DELETE ci FROM cart_items ci
                  JOIN carts c ON c.id = ci.cart_id
                 WHERE c.user_id = :uid"
      );
      $st->execute([':uid' => $this->userId]);
    } else {
      $_SESSION[self::SESSION_KEY] = [];
    }

    if (!$keepCoupon) {
      unset($_SESSION[self::COUPON_SESSION]);
    }

    return [
      'success' => true,
      'message' => 'Cart cleared.',
      'cart' => $this->getCart(),
    ];
  }

  /** Apply a coupon code. */
  public function applyCoupon(string $code): array
  {
    $code = strtoupper(trim($code));
    if ($code === '') {
      return ['success' => false, 'message' => 'Enter a coupon code.'];
    }

    $st = $this->db->prepare(
      "SELECT * FROM coupons WHERE code = :code LIMIT 1"
    );
    $st->execute([':code' => $code]);
    $coupon = $st->fetch();

    if (!$coupon) {
      return ['success' => false, 'message' => 'Invalid coupon code.'];
    }

    if (strtolower($coupon['status']) !== 'active') {
      return ['success' => false, 'message' => 'This coupon is no longer active.'];
    }

    $now = time();
    if (!empty($coupon['starts_at']) && strtotime($coupon['starts_at']) > $now) {
      return ['success' => false, 'message' => 'This coupon is not active yet.'];
    }
    if (!empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < $now) {
      return ['success' => false, 'message' => 'This coupon has expired.'];
    }
    if (!empty($coupon['usage_limit']) && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
      return ['success' => false, 'message' => 'This coupon has reached its usage limit.'];
    }

    /* Cart subtotal check */
    $subtotal = $this->subtotalOf($this->currentItems());
    if (!empty($coupon['minimum_order_amount']) && $subtotal < (float) $coupon['minimum_order_amount']) {
      return [
        'success' => false,
        'message' => 'Minimum order for this coupon is $'
          . number_format((float) $coupon['minimum_order_amount'], 2) . '.',
      ];
    }

    $_SESSION[self::COUPON_SESSION] = $code;

    return [
      'success' => true,
      'message' => 'Coupon applied: ' . $code,
      'cart' => $this->getCart(),
    ];
  }

  /** Remove coupon. */
  public function removeCoupon(): array
  {
    unset($_SESSION[self::COUPON_SESSION]);
    return [
      'success' => true,
      'message' => 'Coupon removed.',
      'cart' => $this->getCart(),
    ];
  }

  /** Total item count (for the header badge). */
  public function count(): int
  {
    $items = $this->currentItems();
    $n = 0;
    foreach ($items as $it) {
      $n += (int) $it['quantity'];
    }
    return $n;
  }

  /** Merge guest session cart into the user's DB cart (call after login). */
  public function mergeGuestCartToUser(): array
  {
    if (!$this->userId) {
      return ['success' => false, 'message' => 'Not logged in.'];
    }
    $guest = $_SESSION[self::SESSION_KEY] ?? [];
    if (!$guest) {
      return ['success' => true, 'message' => 'Nothing to merge.'];
    }

    $cartId = $this->ensureUserCart();

    foreach ($guest as $key => $qty) {
      [$pid, $vid] = $this->parseKey((string) $key);
      if ($pid <= 0) {
        continue;
      }

      $product = $this->loadProduct($pid, $vid);
      if (!$product) {
        continue;
      }

      $existing = $this->currentQuantity($pid, $vid);
      $target = min($existing + (int) $qty, $product['available_stock'], self::MAX_QTY_PER_ITEM);

      $this->dbUpsert($pid, $vid, $target);
    }

    $_SESSION[self::SESSION_KEY] = [];

    return ['success' => true, 'message' => 'Guest cart merged.'];
  }

  public function errors(): array
  {
    return $this->errors;
  }

  /* =============================================================
   |  ITEM LOADING
   * ============================================================= */

  /** Unified internal list of items with resolved product data. */
  private function currentItems(): array
  {
    return $this->userId ? $this->loadDbItems() : $this->loadSessionItems();
  }

  /** DB cart items. */
  private function loadDbItems(): array
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

    return $this->shapeItems($rows);
  }

  /** Session cart items. */
  private function loadSessionItems(): array
  {
    $cart = $_SESSION[self::SESSION_KEY] ?? [];
    if (!$cart) {
      return [];
    }

    $productIds = [];
    $variantIds = [];
    foreach ($cart as $key => $qty) {
      [$pid, $vid] = $this->parseKey((string) $key);
      if ($pid > 0)
        $productIds[] = $pid;
      if ($vid > 0)
        $variantIds[] = $vid;
    }
    $productIds = array_values(array_unique($productIds));
    $variantIds = array_values(array_unique($variantIds));

    if (!$productIds) {
      return [];
    }

    $inP = implode(',', array_fill(0, count($productIds), '?'));

    $sql = "SELECT p.id AS product_id, p.name, p.slug, p.sku, p.price, p.stock, p.status,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM products p
                 WHERE p.id IN ($inP)";
    $st = $this->db->prepare($sql);
    $st->execute($productIds);
    $products = [];
    foreach ($st->fetchAll() as $p) {
      $products[(int) $p['product_id']] = $p;
    }

    $variants = [];
    if ($variantIds) {
      $inV = implode(',', array_fill(0, count($variantIds), '?'));
      $st = $this->db->prepare(
        "SELECT id, product_id, price, stock, color, size FROM product_variants WHERE id IN ($inV)"
      );
      $st->execute($variantIds);
      foreach ($st->fetchAll() as $v) {
        $variants[(int) $v['id']] = $v;
      }
    }

    $rows = [];
    foreach ($cart as $key => $qty) {
      [$pid, $vid] = $this->parseKey((string) $key);
      if (!isset($products[$pid])) {
        continue;
      }
      $p = $products[$pid];
      $v = $vid && isset($variants[$vid]) ? $variants[$vid] : null;

      $rows[] = [
        'product_id' => $pid,
        'variant_id' => $vid ?: null,
        'quantity' => (int) $qty,
        'name' => $p['name'],
        'slug' => $p['slug'],
        'sku' => $p['sku'],
        'price' => $p['price'],
        'stock' => $p['stock'],
        'status' => $p['status'],
        'variant_price' => $v['price'] ?? null,
        'variant_stock' => $v['stock'] ?? null,
        'color' => $v['color'] ?? null,
        'size' => $v['size'] ?? null,
        'image' => $p['image'] ?? null,
      ];
    }

    return $this->shapeItems($rows);
  }

  /** Common shape for both sources. */
  private function shapeItems(array $rows): array
  {
    $out = [];
    foreach ($rows as $r) {
      $unitPrice = $r['variant_price'] !== null
        ? (float) $r['variant_price']
        : (float) $r['price'];

      $stock = $r['variant_id'] && $r['variant_stock'] !== null
        ? (int) $r['variant_stock']
        : (int) $r['stock'];

      $available = strtolower((string) $r['status']) === 'active' ? $stock : 0;

      $qty = (int) $r['quantity'];
      $lineTotal = round($unitPrice * $qty, 2);

      $out[] = [
        'product_id' => (int) $r['product_id'],
        'variant_id' => $r['variant_id'] !== null ? (int) $r['variant_id'] : null,
        'quantity' => $qty,
        'name' => $r['name'],
        'slug' => $r['slug'],
        'sku' => $r['sku'],
        'unit_price' => round($unitPrice, 2),
        'line_total' => $lineTotal,
        'image' => $r['image'] ?? null,
        'color' => $r['color'] ?? null,
        'size' => $r['size'] ?? null,
        'available_stock' => $available,
        'in_stock' => $available >= $qty,
        'status' => $r['status'],
      ];
    }
    return $out;
  }

  /* =============================================================
   |  SUMMARY / COUPON
   * ============================================================= */

  private function subtotalOf(array $items): float
  {
    $s = 0.0;
    foreach ($items as $it) {
      $s += (float) $it['line_total'];
    }
    return round($s, 2);
  }

  private function computeSummary(array $items, ?array $coupon): array
  {
    $subtotal = $this->subtotalOf($items);

    $discount = 0.0;
    $couponWarning = null;
    if ($coupon) {
      $min = (float) ($coupon['minimum_order_amount'] ?? 0);
      if ($min > 0 && $subtotal < $min) {
        // Checkout ignores the coupon below its minimum, so the cart must show the same total.
        $couponWarning = 'Add $' . number_format($min - $subtotal, 2) . ' more to use ' . $coupon['code'] . '.';
      } else {
        if ($coupon['discount_type'] === 'percentage') {
          $discount = $subtotal * ((float) $coupon['discount_value'] / 100);
          if (!empty($coupon['maximum_discount'])) {
            $discount = min($discount, (float) $coupon['maximum_discount']);
          }
        } else { // fixed
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
      'coupon_warning' => $couponWarning,
      'free_ship_over' => self::FREE_SHIPPING_OVER,
      'amount_to_free' => $subtotal > 0 ? round(max(0, self::FREE_SHIPPING_OVER - $afterDiscount), 2) : 0,
      'item_count' => array_sum(array_map(fn($i) => (int) $i['quantity'], $items)),
      'currency' => '$',
    ];
  }

  private function getAppliedCoupon(): ?array
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

    return [
      'id' => (int) $c['id'],
      'code' => $c['code'],
      'description' => $c['description'] ?? null,
      'discount_type' => $c['discount_type'],
      'discount_value' => (float) $c['discount_value'],
      'minimum_order_amount' => $c['minimum_order_amount'] !== null ? (float) $c['minimum_order_amount'] : null,
      'maximum_discount' => $c['maximum_discount'] !== null ? (float) $c['maximum_discount'] : null,
      'expires_at' => $c['expires_at'],
    ];
  }

  /* =============================================================
   |  DB HELPERS
   * ============================================================= */

  /** Get (or create) the carts row for the current user. */
  private function ensureUserCart(): int
  {
    if (!$this->userId) {
      return 0;
    }

    $st = $this->db->prepare("SELECT id FROM carts WHERE user_id = :uid LIMIT 1");
    $st->execute([':uid' => $this->userId]);
    $id = $st->fetchColumn();
    if ($id) {
      return (int) $id;
    }

    $ins = $this->db->prepare("INSERT INTO carts (user_id) VALUES (:uid)");
    $ins->execute([':uid' => $this->userId]);

    return (int) $this->db->lastInsertId();
  }

  private function dbUpsert(int $productId, ?int $variantId, int $quantity): void
  {
    $cartId = $this->ensureUserCart();

    /* does a row already exist? */
    $sql = "SELECT id FROM cart_items
                 WHERE cart_id = :cid
                   AND product_id = :pid
                   AND " . ($variantId ? 'variant_id = :vid' : 'variant_id IS NULL')
      . " LIMIT 1";
    $bind = [':cid' => $cartId, ':pid' => $productId];
    if ($variantId) {
      $bind[':vid'] = $variantId;
    }
    $st = $this->db->prepare($sql);
    $st->execute($bind);
    $rowId = $st->fetchColumn();

    if ($rowId) {
      $up = $this->db->prepare(
        "UPDATE cart_items SET quantity = :q, updated_at = NOW() WHERE id = :id"
      );
      $up->execute([':q' => $quantity, ':id' => $rowId]);
    } else {
      $in = $this->db->prepare(
        "INSERT INTO cart_items (cart_id, product_id, variant_id, quantity)
                 VALUES (:cid, :pid, :vid, :q)"
      );
      $in->execute([
        ':cid' => $cartId,
        ':pid' => $productId,
        ':vid' => $variantId ?: null,
        ':q' => $quantity,
      ]);
    }
  }

  private function currentQuantity(int $productId, ?int $variantId): int
  {
    if ($this->userId) {
      $cartId = $this->ensureUserCart();
      $sql = "SELECT quantity FROM cart_items
                     WHERE cart_id = :cid AND product_id = :pid AND "
        . ($variantId ? 'variant_id = :vid' : 'variant_id IS NULL')
        . " LIMIT 1";
      $bind = [':cid' => $cartId, ':pid' => $productId];
      if ($variantId) {
        $bind[':vid'] = $variantId;
      }
      $st = $this->db->prepare($sql);
      $st->execute($bind);
      return (int) $st->fetchColumn();
    }

    $key = $this->itemKey($productId, $variantId);
    return (int) ($_SESSION[self::SESSION_KEY][$key] ?? 0);
  }

  /** Product info + variant price/stock. */
  private function loadProduct(int $productId, ?int $variantId = null): ?array
  {
    $st = $this->db->prepare(
      "SELECT id, name, price, stock, status FROM products WHERE id = :id LIMIT 1"
    );
    $st->execute([':id' => $productId]);
    $p = $st->fetch();
    if (!$p) {
      return null;
    }

    $stock = (int) $p['stock'];
    $status = strtolower((string) $p['status']);

    if ($variantId) {
      $st = $this->db->prepare(
        "SELECT price, stock, status FROM product_variants
                  WHERE id = :id AND product_id = :pid LIMIT 1"
      );
      $st->execute([':id' => $variantId, ':pid' => $productId]);
      $v = $st->fetch();
      if (!$v) {
        return null;
      }
      $stock = (int) $v['stock'];
      $status = strtolower((string) $v['status']);
    }

    return [
      'id' => (int) $p['id'],
      'name' => $p['name'],
      'available_stock' => $status === 'active' ? $stock : 0,
    ];
  }

  private function itemKey(int $productId, ?int $variantId): string
  {
    return $productId . ':' . ($variantId ?: 0);
  }

  private function parseKey(string $key): array
  {
    $parts = explode(':', $key);
    return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
  }
}