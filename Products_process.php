<?php
declare(strict_types=1);

/* ==================================================================
 |  Products_process  —  ShopVerse
 |  Class only. Include this from your API endpoint or admin pages.
 * ================================================================== */

/* ---------- locate database/connect.php (any common layout) ------- */
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

class Products_process
{
  private PDO $db;
  private array $errors = [];

  private const ALLOWED_STATUS = ['active', 'inactive', 'draft'];
  private const DEFAULT_THRESHOLD = 5;
  private const MAX_PER_PAGE = 200;

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /* =============================================================
   |  PUBLIC API
   * ============================================================= */

  /** Products list with filters + pagination. */
  public function listProducts(array $f = []): array
  {
    $where = [];
    $params = [];

    $search = trim((string) ($f['search'] ?? $f['q'] ?? ''));
    if ($search !== '') {
      $like = '%' . $this->escapeLike($search) . '%';
      $where[] = '(p.name LIKE :s1 OR p.sku LIKE :s2 OR p.slug LIKE :s3)';
      $params[':s1'] = $like;
      $params[':s2'] = $like;
      $params[':s3'] = $like;
    }

    $cat = $f['category'] ?? $f['category_id'] ?? null;
    if ($cat !== null && $cat !== '' && strtolower((string) $cat) !== 'all') {
      if (is_numeric($cat)) {
        $where[] = 'p.category_id = :category_id';
        $params[':category_id'] = (int) $cat;
      } else {
        $where[] = 'c.slug = :category_slug';
        $params[':category_slug'] = $this->slugify((string) $cat);
      }
    }

    $status = strtolower(trim((string) ($f['status'] ?? '')));
    if ($status !== '' && $status !== 'all') {
      if ($status === 'low') {
        $where[] = 'p.stock > 0 AND p.stock <= p.low_stock_threshold';
      } elseif ($status === 'out') {
        $where[] = 'p.stock = 0';
      } elseif (in_array($status, self::ALLOWED_STATUS, true)) {
        $where[] = 'p.status = :status';
        $params[':status'] = $status;
      }
    }

    foreach (['featured', 'best_seller', 'new_arrival'] as $flag) {
      if (!empty($f[$flag])) {
        $where[] = "p.`$flag` = 1";
      }
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sortMap = [
      'newest' => 'p.created_at DESC, p.id DESC',
      'oldest' => 'p.created_at ASC, p.id ASC',
      'name' => 'p.name ASC',
      'name_desc' => 'p.name DESC',
      'price' => 'p.price ASC',
      'price_asc' => 'p.price ASC',
      'price_desc' => 'p.price DESC',
      'stock' => 'p.stock ASC',
      'stock_desc' => 'p.stock DESC',
      'rating' => 'p.rating DESC',
    ];
    $sortKey = strtolower((string) ($f['sort'] ?? 'newest'));
    $orderBy = $sortMap[$sortKey] ?? $sortMap['newest'];

    $page = max(1, (int) ($f['page'] ?? 1));
    $perPage = (int) ($f['per_page'] ?? $f['limit'] ?? 50);
    $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
    $offset = ($page - 1) * $perPage;

    $countSql = "SELECT COUNT(*)
                       FROM products p
                       LEFT JOIN categories c ON c.id = p.category_id
                       $whereSql";
    $st = $this->db->prepare($countSql);
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    $sql = "SELECT p.*,
                       c.name AS category_name,
                       c.slug AS category_slug,
                       b.name AS brand_name,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                  LEFT JOIN brands     b ON b.id = p.brand_id
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

    $items = array_map([$this, 'presentProduct'], $st->fetchAll());

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $perPage,
      'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
    ];
  }

  /** Single product. */
  public function getProduct(int $id, bool $withRelations = true): ?array
  {
    $sql = "SELECT p.*,
                       c.name AS category_name,
                       c.slug AS category_slug,
                       b.name AS brand_name,
                       (SELECT pi.image_path
                          FROM product_images pi
                         WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                  LEFT JOIN brands     b ON b.id = p.brand_id
                 WHERE p.id = :id
                 LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
      return null;
    }

    $product = $this->presentProduct($row);

    if ($withRelations) {
      $st = $this->db->prepare(
        "SELECT id, image_path, alt_text, is_primary, sort_order
                   FROM product_images
                  WHERE product_id = :id
               ORDER BY is_primary DESC, sort_order ASC, id ASC"
      );
      $st->execute([':id' => $id]);
      $product['images'] = $st->fetchAll();

      $st = $this->db->prepare(
        "SELECT id, sku, color, size, price, stock, status
                   FROM product_variants
                  WHERE product_id = :id
               ORDER BY id ASC"
      );
      $st->execute([':id' => $id]);
      $product['variants'] = $st->fetchAll();
    }

    return $product;
  }

  /** Create OR update (pass $id = null to create). */
  public function saveProduct(array $data, ?int $id = null): array
  {
    $this->errors = [];

    $clean = $this->validateProductData($data, $id);
    if ($this->errors) {
      return ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $this->errors];
    }

    $isUpdate = ($id !== null && $id > 0);

    try {
      $this->db->beginTransaction();

      $categoryId = $this->resolveCategoryId($clean['category']);

      $slug = $this->uniqueSlug(
        $this->slugify($clean['name']),
        $isUpdate ? $id : null
      );

      $sku = $this->uniqueSku(
        $clean['name'],
        $clean['sku'] ?? null,
        $isUpdate ? $id : null
      );

      $fields = [
        'category_id' => $categoryId,
        'brand_id' => $clean['brand_id'],
        'name' => $clean['name'],
        'slug' => $slug,
        'sku' => $sku,
        'description' => $clean['description'],
        'short_description' => $clean['short_description'],
        'price' => $clean['price'],
        'compare_at_price' => $clean['compare_at_price'],
        'stock' => $clean['stock'],
        'low_stock_threshold' => $clean['low_stock_threshold'],
        'status' => $clean['status'],
        'featured' => $clean['featured'],
        'best_seller' => $clean['best_seller'],
        'new_arrival' => $clean['new_arrival'],
      ];

      if ($isUpdate) {
        $exists = $this->db->prepare("SELECT id FROM products WHERE id = :id LIMIT 1");
        $exists->execute([':id' => $id]);
        if (!$exists->fetchColumn()) {
          $this->db->rollBack();
          return ['success' => false, 'message' => 'Product not found.'];
        }

        $set = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($fields)));
        $sql = "UPDATE products SET $set WHERE id = :id";
        $st = $this->db->prepare($sql);
        foreach ($fields as $k => $v) {
          $st->bindValue(":$k", $v, $this->pdoType($v));
        }
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();

        $productId = $id;
        $action = 'updated';
      } else {
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
        $phs = implode(', ', array_map(fn($k) => ":$k", array_keys($fields)));
        $sql = "INSERT INTO products ($cols) VALUES ($phs)";
        $st = $this->db->prepare($sql);
        foreach ($fields as $k => $v) {
          $st->bindValue(":$k", $v, $this->pdoType($v));
        }
        $st->execute();

        $productId = (int) $this->db->lastInsertId();
        $action = 'created';
      }

      if (!empty($clean['image'])) {
        $this->savePrimaryImage($productId, $clean['image']);
      } elseif ($isUpdate && array_key_exists('image', $data) && $clean['image'] === null) {
        $del = $this->db->prepare("DELETE FROM product_images WHERE product_id = :id AND is_primary = 1");
        $del->execute([':id' => $productId]);
      }

      $this->logAudit($action, $productId, [
        'name' => $clean['name'],
        'sku' => $sku,
      ]);

      $this->db->commit();

      return [
        'success' => true,
        'message' => $isUpdate ? 'Product updated successfully.' : 'Product created successfully.',
        'action' => $action,
        'id' => $productId,
        'product' => $this->getProduct($productId),
      ];
    } catch (InvalidArgumentException $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      return ['success' => false, 'message' => $e->getMessage(), 'errors' => $this->errors];
    } catch (PDOException $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[PRODUCTS][PDO] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Database error while saving the product.'];
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[PRODUCTS] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Unexpected error while saving the product.'];
    }
  }

  /** Hard delete, falling back to soft delete when FKs block it. */
  public function deleteProduct(int $id): array
  {
    $st = $this->db->prepare("SELECT id, name FROM products WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
      return ['success' => false, 'message' => 'Product not found.'];
    }

    try {
      $del = $this->db->prepare("DELETE FROM products WHERE id = :id");
      $del->execute([':id' => $id]);

      $this->logAudit('deleted', $id, ['name' => $row['name']]);

      return ['success' => true, 'message' => 'Product deleted.', 'mode' => 'hard', 'id' => $id];
    } catch (PDOException $e) {
      if ((string) $e->getCode() === '23000' || str_contains($e->getMessage(), '1451')) {
        $up = $this->db->prepare("UPDATE products SET status = 'inactive' WHERE id = :id");
        $up->execute([':id' => $id]);

        $this->logAudit('archived', $id, ['name' => $row['name']]);

        return [
          'success' => true,
          'message' => 'Product is referenced by existing orders/carts, so it was archived (set to Inactive) instead.',
          'mode' => 'soft',
          'id' => $id,
        ];
      }
      error_log('[PRODUCTS][DELETE] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not delete the product.'];
    }
  }

  /** Bulk delete / status change. */
  public function bulkAction(string $action, array $ids): array
  {
    $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));
    if (!$ids) {
      return ['success' => false, 'message' => 'No products selected.'];
    }

    $in = implode(',', array_fill(0, count($ids), '?'));
    $affected = 0;

    try {
      if ($action === 'delete') {
        foreach ($ids as $id) {
          $r = $this->deleteProduct($id);
          if ($r['success']) {
            $affected++;
          }
        }
        return ['success' => true, 'message' => "$affected product(s) processed.", 'affected' => $affected];
      }

      $map = [
        'activate' => 'active',
        'deactivate' => 'inactive',
        'draft' => 'draft',
        'feature' => null,
        'unfeature' => null,
      ];
      if (!array_key_exists($action, $map)) {
        return ['success' => false, 'message' => 'Unknown bulk action.'];
      }

      if (in_array($action, ['feature', 'unfeature'], true)) {
        $val = $action === 'feature' ? 1 : 0;
        $st = $this->db->prepare("UPDATE products SET featured = ? WHERE id IN ($in)");
        $st->execute(array_merge([$val], $ids));
      } else {
        $st = $this->db->prepare("UPDATE products SET status = ? WHERE id IN ($in)");
        $st->execute(array_merge([$map[$action]], $ids));
      }

      $affected = $st->rowCount();
      return ['success' => true, 'message' => "$affected product(s) updated.", 'affected' => $affected];
    } catch (PDOException $e) {
      error_log('[PRODUCTS][BULK] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Bulk action failed.'];
    }
  }

  /** Categories for filter + modal selects. */
  public function listCategories(bool $activeOnly = false, bool $withCounts = true): array
  {
    $sql = "SELECT c.id, c.parent_id, c.name, c.slug, c.status";
    if ($withCounts) {
      $sql .= ", (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count";
    }
    $sql .= " FROM categories c";
    if ($activeOnly) {
      $sql .= " WHERE c.status = 'active'";
    }
    $sql .= " ORDER BY c.name ASC";

    $rows = $this->db->query($sql)->fetchAll();

    return array_map(static function (array $r): array {
      return [
        'id' => (int) $r['id'],
        'parent_id' => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
        'name' => $r['name'],
        'slug' => $r['slug'],
        'status' => $r['status'],
        'product_count' => isset($r['product_count']) ? (int) $r['product_count'] : null,
      ];
    }, $rows);
  }

  /** Create a category on the fly. */
  public function createCategory(string $name, ?int $parentId = null): array
  {
    $name = trim($name);
    if ($name === '') {
      return ['success' => false, 'message' => 'Category name is required.'];
    }
    if (mb_strlen($name) > 100) {
      return ['success' => false, 'message' => 'Category name is too long.'];
    }

    $slug = $this->uniqueCategorySlug($this->slugify($name));

    try {
      $st = $this->db->prepare(
        "INSERT INTO categories (parent_id, name, slug, status)
                 VALUES (:parent_id, :name, :slug, 'active')"
      );
      $st->execute([
        ':parent_id' => $parentId ?: null,
        ':name' => $name,
        ':slug' => $slug,
      ]);

      return [
        'success' => true,
        'message' => 'Category created.',
        'category' => [
          'id' => (int) $this->db->lastInsertId(),
          'name' => $name,
          'slug' => $slug,
        ],
      ];
    } catch (PDOException $e) {
      error_log('[PRODUCTS][CATEGORY] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not create the category.'];
    }
  }

  /** Brands for optional brand select. */
  public function listBrands(): array
  {
    $rows = $this->db->query(
      "SELECT id, name, slug FROM brands WHERE status = 'active' ORDER BY name ASC"
    )->fetchAll();

    return array_map(static fn(array $r): array => [
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
    ], $rows);
  }

  /** Dashboard counters. */
  public function getStats(): array
  {
    $sql = "SELECT
                    COUNT(*)                                                        AS total,
                    SUM(status = 'active')                                          AS active,
                    SUM(status = 'inactive')                                        AS inactive,
                    SUM(status = 'draft')                                           AS draft,
                    SUM(stock = 0)                                                  AS out_of_stock,
                    SUM(stock > 0 AND stock <= low_stock_threshold)                 AS low_stock,
                    COALESCE(SUM(stock * price), 0)                                 AS inventory_value
                FROM products";
    $row = $this->db->query($sql)->fetch() ?: [];

    return [
      'total' => (int) ($row['total'] ?? 0),
      'active' => (int) ($row['active'] ?? 0),
      'inactive' => (int) ($row['inactive'] ?? 0),
      'draft' => (int) ($row['draft'] ?? 0),
      'out_of_stock' => (int) ($row['out_of_stock'] ?? 0),
      'low_stock' => (int) ($row['low_stock'] ?? 0),
      'inventory_value' => round((float) ($row['inventory_value'] ?? 0), 2),
    ];
  }

  public function errors(): array
  {
    return $this->errors;
  }

  /* =============================================================
   |  VALIDATION
   * ============================================================= */

  public function validateProductData(array $data, ?int $ignoreId = null): array
  {
    $this->errors = [];
    $clean = [];

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
      $this->errors['name'] = 'Product name is required.';
    } elseif (mb_strlen($name) < 2) {
      $this->errors['name'] = 'Product name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 200) {
      $this->errors['name'] = 'Product name cannot exceed 200 characters.';
    } else {
      $clean['name'] = $name;
    }

    $price = $data['price'] ?? null;
    if ($price === null || $price === '') {
      $this->errors['price'] = 'Price is required.';
    } elseif (!is_numeric($price) || (float) $price < 0) {
      $this->errors['price'] = 'Enter a valid price.';
    } else {
      $clean['price'] = round((float) $price, 2);
    }

    $old = $data['oldPrice'] ?? $data['compare_at_price'] ?? null;
    if ($old !== null && $old !== '') {
      if (!is_numeric($old) || (float) $old < 0) {
        $this->errors['oldPrice'] = 'Enter a valid compare-at price.';
      } else {
        $clean['compare_at_price'] = round((float) $old, 2);
      }
    } else {
      $clean['compare_at_price'] = null;
    }

    $stock = $data['stock'] ?? null;
    if ($stock === null || $stock === '') {
      $this->errors['stock'] = 'Stock quantity is required.';
    } elseif (!is_numeric($stock) || (int) $stock < 0) {
      $this->errors['stock'] = 'Enter a valid stock quantity.';
    } else {
      $clean['stock'] = (int) $stock;
    }

    $lst = $data['low_stock_threshold'] ?? null;
    $clean['low_stock_threshold'] = (is_numeric($lst) && (int) $lst >= 0)
      ? (int) $lst
      : self::DEFAULT_THRESHOLD;

    $status = strtolower(trim((string) ($data['status'] ?? 'active')));
    if (!in_array($status, self::ALLOWED_STATUS, true)) {
      $status = 'active';
    }
    $clean['status'] = $status;

    $category = $data['category'] ?? $data['category_id'] ?? null;
    if ($category === null || $category === '') {
      $this->errors['category'] = 'Category is required.';
    } else {
      $clean['category'] = $category;
    }

    $brand = $data['brand_id'] ?? $data['brand'] ?? null;
    $clean['brand_id'] = ($brand !== null && $brand !== '' && is_numeric($brand))
      ? (int) $brand
      : null;

    $clean['description'] = ($d = trim((string) ($data['description'] ?? ''))) !== '' ? $d : null;
    $clean['short_description'] = ($s = trim((string) ($data['short_description'] ?? ''))) !== '' ? $s : null;

    $image = trim((string) ($data['image'] ?? ''));
    if ($image !== '' && mb_strlen($image) > 500) {
      $this->errors['image'] = 'Image URL is too long.';
    } else {
      $clean['image'] = $image !== '' ? $image : null;
    }

    $sku = trim((string) ($data['sku'] ?? ''));
    if ($sku !== '' && !preg_match('/^[A-Za-z0-9\-_\.]{2,100}$/', $sku)) {
      $this->errors['sku'] = 'SKU may only contain letters, numbers, dash, underscore and dot.';
    } else {
      $clean['sku'] = $sku !== '' ? strtoupper($sku) : null;
    }

    foreach (['featured', 'best_seller', 'new_arrival'] as $flag) {
      $v = $data[$flag] ?? null;
      $clean[$flag] = (in_array($v, [1, '1', true, 'true', 'on', 'yes'], true)) ? 1 : 0;
    }

    if (
      !empty($clean['compare_at_price']) && !empty($clean['price'])
      && $clean['compare_at_price'] < $clean['price']
    ) {
      $this->errors['oldPrice'] = 'Compare-at price should be greater than or equal to the price.';
    }

    return $this->errors ? [] : $clean;
  }

  /* =============================================================
   |  INTERNAL HELPERS
   * ============================================================= */

  private function presentProduct(array $r): array
  {
    $status = ucfirst((string) ($r['status'] ?? 'active'));

    return [
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
      'sku' => $r['sku'],
      'category_id' => (int) $r['category_id'],
      'category' => $r['category_name'] ?? null,
      'category_slug' => $r['category_slug'] ?? null,
      'brand_id' => isset($r['brand_id']) ? (int) $r['brand_id'] : null,
      'brand' => $r['brand_name'] ?? null,
      'price' => (float) $r['price'],
      'oldPrice' => $r['compare_at_price'] !== null ? (float) $r['compare_at_price'] : null,
      'compare_at_price' => $r['compare_at_price'] !== null ? (float) $r['compare_at_price'] : null,
      'stock' => (int) $r['stock'],
      'low_stock_threshold' => (int) ($r['low_stock_threshold'] ?? self::DEFAULT_THRESHOLD),
      'is_low_stock' => (int) $r['stock'] > 0
        && (int) $r['stock'] <= (int) ($r['low_stock_threshold'] ?? self::DEFAULT_THRESHOLD),
      'is_out_of_stock' => (int) $r['stock'] === 0,
      'rating' => (float) ($r['rating'] ?? 0),
      'review_count' => (int) ($r['review_count'] ?? 0),
      'status' => $status,
      'status_raw' => $r['status'],
      'featured' => (int) ($r['featured'] ?? 0),
      'best_seller' => (int) ($r['best_seller'] ?? 0),
      'new_arrival' => (int) ($r['new_arrival'] ?? 0),
      'image' => $r['image'] ?? null,
      'description' => $r['description'] ?? null,
      'short_description' => $r['short_description'] ?? null,
      'created_at' => $r['created_at'] ?? null,
      'updated_at' => $r['updated_at'] ?? null,
    ];
  }

  private function resolveCategoryId($value, bool $createIfMissing = true): int
  {
    if (is_numeric($value)) {
      $id = (int) $value;
      $st = $this->db->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
      $st->execute([':id' => $id]);
      if ($st->fetchColumn()) {
        return $id;
      }
      throw new InvalidArgumentException('The selected category no longer exists.');
    }

    $name = trim((string) $value);
    if ($name === '') {
      throw new InvalidArgumentException('Category is required.');
    }

    $slug = $this->slugify($name);

    $st = $this->db->prepare(
      "SELECT id FROM categories WHERE slug = :slug OR name = :name LIMIT 1"
    );
    $st->execute([':slug' => $slug, ':name' => $name]);
    $id = $st->fetchColumn();
    if ($id) {
      return (int) $id;
    }

    if (!$createIfMissing) {
      throw new InvalidArgumentException('Unknown category: ' . $name);
    }

    $ins = $this->db->prepare(
      "INSERT INTO categories (name, slug, status) VALUES (:name, :slug, 'active')"
    );
    $ins->execute([':name' => $name, ':slug' => $this->uniqueCategorySlug($slug)]);

    return (int) $this->db->lastInsertId();
  }

  private function savePrimaryImage(int $productId, string $path): void
  {
    $del = $this->db->prepare("DELETE FROM product_images WHERE product_id = :id AND is_primary = 1");
    $del->execute([':id' => $productId]);

    $ins = $this->db->prepare(
      "INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order)
             VALUES (:product_id, :image_path, :alt_text, 1, 0)"
    );
    $ins->execute([
      ':product_id' => $productId,
      ':image_path' => $path,
      ':alt_text' => null,
    ]);
  }

  private function uniqueSlug(string $base, ?int $ignoreId = null): string
  {
    $base = $base !== '' ? $base : 'product';
    $slug = $base;
    $i = 2;

    while (true) {
      $sql = "SELECT id FROM products WHERE slug = :slug"
        . ($ignoreId ? " AND id <> :id" : "")
        . " LIMIT 1";
      $st = $this->db->prepare($sql);
      $st->bindValue(':slug', $slug);
      if ($ignoreId) {
        $st->bindValue(':id', $ignoreId, PDO::PARAM_INT);
      }
      $st->execute();

      if (!$st->fetchColumn()) {
        return $slug;
      }
      $slug = $base . '-' . $i++;
      if ($i > 5000) {
        return $base . '-' . bin2hex(random_bytes(4));
      }
    }
  }

  private function uniqueCategorySlug(string $base): string
  {
    $base = $base !== '' ? $base : 'category';
    $slug = $base;
    $i = 2;

    while (true) {
      $st = $this->db->prepare("SELECT id FROM categories WHERE slug = :slug LIMIT 1");
      $st->execute([':slug' => $slug]);
      if (!$st->fetchColumn()) {
        return $slug;
      }
      $slug = $base . '-' . $i++;
      if ($i > 5000) {
        return $base . '-' . bin2hex(random_bytes(4));
      }
    }
  }

  private function uniqueSku(string $name, ?string $provided = null, ?int $ignoreId = null): string
  {
    $candidate = ($provided !== null && trim($provided) !== '')
      ? strtoupper(trim($provided))
      : $this->buildSku($name);

    $sku = $candidate;
    $i = 2;

    while (true) {
      $sql = "SELECT id FROM products WHERE sku = :sku"
        . ($ignoreId ? " AND id <> :id" : "")
        . " LIMIT 1";
      $st = $this->db->prepare($sql);
      $st->bindValue(':sku', $sku);
      if ($ignoreId) {
        $st->bindValue(':id', $ignoreId, PDO::PARAM_INT);
      }
      $st->execute();

      if (!$st->fetchColumn()) {
        return $sku;
      }
      $sku = $candidate . '-' . $i++;
      if ($i > 5000) {
        return $candidate . '-' . strtoupper(bin2hex(random_bytes(3)));
      }
    }
  }

  private function buildSku(string $name): string
  {
    $alpha = preg_replace('/[^A-Za-z]/', '', $name) ?? '';
    $alpha = strtoupper(substr($alpha, 0, 3));
    if ($alpha === '') {
      $alpha = 'GEN';
    }
    $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

    return "SV-{$alpha}-{$rand}";
  }

  private function slugify(string $text): string
  {
    $text = trim($text);
    if ($text === '') {
      return '';
    }
    if (function_exists('iconv')) {
      $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
      if ($converted !== false && $converted !== '') {
        $text = $converted;
      }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text !== '' ? substr($text, 0, 200) : '';
  }

  private function escapeLike(string $s): string
  {
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);
  }

  private function pdoType($v): int
  {
    return match (true) {
      is_int($v) => PDO::PARAM_INT,
      is_bool($v) => PDO::PARAM_BOOL,
      $v === null => PDO::PARAM_NULL,
      default => PDO::PARAM_STR,
    };
  }

  private function logAudit(string $action, int $entityId, array $details = []): void
  {
    try {
      $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

      $st = $this->db->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details)
                 VALUES (:user_id, :action, 'products', :entity_id, :ip, :ua, :details)"
      );
      $st->execute([
        ':user_id' => $userId,
        ':action' => 'product_' . $action,
        ':entity_id' => $entityId,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
      ]);
    } catch (Throwable $e) {
      error_log('[PRODUCTS][AUDIT] ' . $e->getMessage());
    }
  }
}