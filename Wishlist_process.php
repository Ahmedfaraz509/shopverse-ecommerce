<?php
declare(strict_types=1);

/* ==================================================================
 |  Wishlist_process  —  ShopVerse
 |
 |  • Per-user wishlist (wishlist table: user_id + product_id unique)
 |  • Idempotent add / toggle
 |  • addAllToCart() copies every wishlist item to the cart in a
 |    single transaction, then optionally empties the wishlist
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

class Wishlist_process
{
  private PDO $db;
  private int $userId;

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

  /** List all wishlist items (with fresh product data). */
  public function listItems(): array
  {
    if ($this->userId <= 0) {
      return [];
    }

    $sql = "SELECT w.id           AS wishlist_id,
                       w.created_at   AS saved_at,
                       p.id, p.name, p.slug, p.sku,
                       p.price, p.compare_at_price, p.stock,
                       p.rating, p.review_count,
                       p.featured, p.best_seller, p.new_arrival,
                       p.status,
                       c.name AS category_name,
                       c.slug AS category_slug,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM wishlist w
                  JOIN products p ON p.id = w.product_id
                  LEFT JOIN categories c ON c.id = p.category_id
                 WHERE w.user_id = :uid
              ORDER BY w.created_at DESC, w.id DESC";

    $st = $this->db->prepare($sql);
    $st->execute([':uid' => $this->userId]);

    return array_map([$this, 'present'], $st->fetchAll());
  }

  /** Add a product (idempotent — silent if already present). */
  public function add(int $productId): array
  {
    if ($this->userId <= 0) {
      return ['success' => false, 'message' => 'Please log in to use your wishlist.'];
    }
    if ($productId <= 0) {
      return ['success' => false, 'message' => 'Invalid product.'];
    }

    if (!$this->productExists($productId)) {
      return ['success' => false, 'message' => 'Product not found.'];
    }

    if ($this->has($productId)) {
      return [
        'success' => true,
        'message' => 'Already in your wishlist.',
        'added' => false,
        'in_wishlist' => true,
        'count' => $this->count(),
      ];
    }

    try {
      $st = $this->db->prepare(
        "INSERT INTO wishlist (user_id, product_id) VALUES (:uid, :pid)"
      );
      $st->execute([':uid' => $this->userId, ':pid' => $productId]);

      return [
        'success' => true,
        'message' => 'Added to your wishlist.',
        'added' => true,
        'in_wishlist' => true,
        'count' => $this->count(),
      ];
    } catch (PDOException $e) {
      /* duplicate key safety net (user_id, product_id is unique) */
      if ((string) $e->getCode() === '23000') {
        return [
          'success' => true,
          'message' => 'Already in your wishlist.',
          'added' => false,
          'in_wishlist' => true,
          'count' => $this->count(),
        ];
      }
      error_log('[WISHLIST][ADD] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not add to wishlist.'];
    }
  }

  /** Remove a product. */
  public function remove(int $productId): array
  {
    if ($this->userId <= 0) {
      return ['success' => false, 'message' => 'Please log in.'];
    }
    if ($productId <= 0) {
      return ['success' => false, 'message' => 'Invalid product.'];
    }

    $st = $this->db->prepare(
      "DELETE FROM wishlist WHERE user_id = :uid AND product_id = :pid"
    );
    $st->execute([':uid' => $this->userId, ':pid' => $productId]);

    return [
      'success' => true,
      'message' => $st->rowCount() ? 'Removed from wishlist.' : 'Not in your wishlist.',
      'removed' => $st->rowCount() > 0,
      'in_wishlist' => false,
      'count' => $this->count(),
    ];
  }

  /** Toggle: add if missing, remove if present. */
  public function toggle(int $productId): array
  {
    if ($this->has($productId)) {
      return $this->remove($productId);
    }
    return $this->add($productId);
  }

  /** Wipe the wishlist. */
  public function clear(): array
  {
    if ($this->userId <= 0) {
      return ['success' => false, 'message' => 'Please log in.'];
    }

    $st = $this->db->prepare("DELETE FROM wishlist WHERE user_id = :uid");
    $st->execute([':uid' => $this->userId]);

    return [
      'success' => true,
      'message' => 'Wishlist cleared.',
      'removed' => (int) $st->rowCount(),
      'count' => 0,
    ];
  }

  /** Product ids in the user's wishlist (lets product cards show a filled heart). */
  public function ids(): array
  {
    if ($this->userId <= 0) {
      return [];
    }
    $st = $this->db->prepare("SELECT product_id FROM wishlist WHERE user_id = :uid");
    $st->execute([':uid' => $this->userId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
  }

  /** Number of saved products. */
  public function count(): int
  {
    if ($this->userId <= 0) {
      return 0;
    }
    $st = $this->db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = :uid");
    $st->execute([':uid' => $this->userId]);
    return (int) $st->fetchColumn();
  }

  /** Is a given product in the current user's wishlist? */
  public function has(int $productId): bool
  {
    if ($this->userId <= 0 || $productId <= 0) {
      return false;
    }
    $st = $this->db->prepare(
      "SELECT 1 FROM wishlist WHERE user_id = :uid AND product_id = :pid LIMIT 1"
    );
    $st->execute([':uid' => $this->userId, ':pid' => $productId]);
    return (bool) $st->fetchColumn();
  }

  /**
   * Add every wishlist item to the cart.
   * @param bool $emptyAfter  If true, wishlist is emptied on success.
   */
  public function addAllToCart(bool $emptyAfter = true): array
  {
    if ($this->userId <= 0) {
      return ['success' => false, 'message' => 'Please log in.'];
    }

    $items = $this->listItems();
    if (!$items) {
      return ['success' => false, 'message' => 'Your wishlist is empty.'];
    }

    try {
      $this->db->beginTransaction();

      /* cart id (create if missing) */
      $cartId = $this->ensureCart();

      $added = 0;
      $skipped = 0;

      foreach ($items as $p) {
        if ($p['is_out_of_stock']) {
          $skipped++;
          continue;
        }

        /* existing line? */
        $st = $this->db->prepare(
          "SELECT id, quantity FROM cart_items
                      WHERE cart_id = :cid AND product_id = :pid AND variant_id IS NULL
                      LIMIT 1"
        );
        $st->execute([':cid' => $cartId, ':pid' => $p['id']]);
        $existing = $st->fetch();

        if ($existing) {
          $newQty = min((int) $existing['quantity'] + 1, max(1, $p['stock']));
          $up = $this->db->prepare(
            "UPDATE cart_items SET quantity = :q, updated_at = NOW() WHERE id = :id"
          );
          $up->execute([':q' => $newQty, ':id' => $existing['id']]);
        } else {
          $in = $this->db->prepare(
            "INSERT INTO cart_items (cart_id, product_id, variant_id, quantity)
                         VALUES (:cid, :pid, NULL, 1)"
          );
          $in->execute([':cid' => $cartId, ':pid' => $p['id']]);
        }
        $added++;
      }

      if ($emptyAfter && $added > 0) {
        $this->db->prepare("DELETE FROM wishlist WHERE user_id = :uid")
          ->execute([':uid' => $this->userId]);
      }

      $this->db->commit();

      $msg = $added > 0
        ? "Added {$added} item" . ($added === 1 ? '' : 's') . " to your cart."
        : 'No items could be added.';

      if ($skipped > 0) {
        $msg .= " ({$skipped} out of stock)";
      }

      return [
        'success' => true,
        'message' => $msg,
        'added' => $added,
        'skipped' => $skipped,
        'count' => $this->count(),
      ];
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[WISHLIST][MOVE_ALL] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not add items to cart.'];
    }
  }

  /* =============================================================
   |  INTERNAL
   * ============================================================= */

  private function ensureCart(): int
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

  private function productExists(int $id): bool
  {
    $st = $this->db->prepare(
      "SELECT 1 FROM products WHERE id = :id AND status = 'active' LIMIT 1"
    );
    $st->execute([':id' => $id]);
    return (bool) $st->fetchColumn();
  }

  private function present(array $r): array
  {
    $price = (float) $r['price'];
    $oldPrice = $r['compare_at_price'] !== null ? (float) $r['compare_at_price'] : null;

    $discountPct = 0;
    if ($oldPrice && $oldPrice > $price) {
      $discountPct = (int) round((($oldPrice - $price) / $oldPrice) * 100);
    }

    $stock = (int) $r['stock'];
    $isActive = strtolower((string) $r['status']) === 'active';

    return [
      'wishlist_id' => (int) $r['wishlist_id'],
      'saved_at' => $r['saved_at'],
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
      'sku' => $r['sku'],
      'price' => round($price, 2),
      'old_price' => $oldPrice !== null ? round($oldPrice, 2) : null,
      'discount_percent' => $discountPct,
      'stock' => $stock,
      'in_stock' => $isActive && $stock > 0,
      'is_out_of_stock' => !$isActive || $stock === 0,
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