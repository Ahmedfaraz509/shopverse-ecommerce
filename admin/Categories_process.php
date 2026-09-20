<?php
declare(strict_types=1);

/* ==================================================================
 |  Categories_process  —  ShopVerse
 |  Class only. Include from categories_api.php or admin pages.
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

class Categories_process
{
  private PDO $db;
  private array $errors = [];

  private const ALLOWED_STATUS = ['active', 'inactive'];
  private const MAX_PER_PAGE = 500;

  public function __construct(?PDO $db = null)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /* =============================================================
   |  PUBLIC API
   * ============================================================= */

  /** List categories with filters, product counts, parent name. */
  public function listCategories(array $f = []): array
  {
    $where = [];
    $params = [];

    /* ---- search (name / slug / description) ---- */
    $search = trim((string) ($f['search'] ?? $f['q'] ?? ''));
    if ($search !== '') {
      $like = '%' . $this->escapeLike($search) . '%';
      $where[] = '(c.name LIKE :s1 OR c.slug LIKE :s2 OR c.description LIKE :s3)';
      $params[':s1'] = $like;
      $params[':s2'] = $like;
      $params[':s3'] = $like;
    }

    /* ---- status ---- */
    $status = strtolower(trim((string) ($f['status'] ?? '')));
    if ($status !== '' && $status !== 'all') {
      if (in_array($status, self::ALLOWED_STATUS, true)) {
        $where[] = 'c.status = :status';
        $params[':status'] = $status;
      }
    }

    /* ---- parent filter ---- */
    $parent = $f['parent'] ?? $f['parent_id'] ?? null;
    if ($parent !== null && $parent !== '' && strtolower((string) $parent) !== 'all') {
      if (strtolower((string) $parent) === 'root' || (string) $parent === '0') {
        $where[] = 'c.parent_id IS NULL';
      } else {
        $where[] = 'c.parent_id = :parent_id';
        $params[':parent_id'] = (int) $parent;
      }
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    /* ---- sorting ---- */
    $sortMap = [
      'newest' => 'c.created_at DESC, c.id DESC',
      'oldest' => 'c.created_at ASC, c.id ASC',
      'name' => 'c.name ASC',
      'name_desc' => 'c.name DESC',
      'products' => 'product_count DESC, c.name ASC',
      'products_asc' => 'product_count ASC, c.name ASC',
    ];
    $sortKey = strtolower((string) ($f['sort'] ?? 'name'));
    $orderBy = $sortMap[$sortKey] ?? $sortMap['name'];

    /* ---- pagination ---- */
    $page = max(1, (int) ($f['page'] ?? 1));
    $perPage = (int) ($f['per_page'] ?? $f['limit'] ?? 100);
    $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
    $offset = ($page - 1) * $perPage;

    /* ---- total ---- */
    $countSql = "SELECT COUNT(*) FROM categories c $whereSql";
    $st = $this->db->prepare($countSql);
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    /* ---- rows ---- */
    $sql = "SELECT c.*,
                       p.name AS parent_name,
                       p.slug AS parent_slug,
                       (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.id) AS product_count,
                       (SELECT COUNT(*) FROM categories ch WHERE ch.parent_id = c.id) AS child_count
                  FROM categories c
                  LEFT JOIN categories p ON p.id = c.parent_id
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

    $items = array_map([$this, 'presentCategory'], $st->fetchAll());

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $perPage,
      'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
    ];
  }

  /** Single category. */
  public function getCategory(int $id): ?array
  {
    $sql = "SELECT c.*,
                       p.name AS parent_name,
                       p.slug AS parent_slug,
                       (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.id) AS product_count,
                       (SELECT COUNT(*) FROM categories ch WHERE ch.parent_id = c.id) AS child_count
                  FROM categories c
                  LEFT JOIN categories p ON p.id = c.parent_id
                 WHERE c.id = :id
                 LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
      return null;
    }
    return $this->presentCategory($row);
  }

  /** Create OR update (pass $id = null to create). */
  public function saveCategory(array $data, ?int $id = null): array
  {
    $this->errors = [];

    $clean = $this->validateCategoryData($data, $id);
    if ($this->errors) {
      return [
        'success' => false,
        'message' => 'Please fix the highlighted fields.',
        'errors' => $this->errors,
      ];
    }

    $isUpdate = ($id !== null && $id > 0);

    try {
      $this->db->beginTransaction();

      $slug = $this->uniqueSlug(
        $this->slugify($clean['name']),
        $isUpdate ? $id : null
      );

      $fields = [
        'parent_id' => $clean['parent_id'],
        'name' => $clean['name'],
        'slug' => $slug,
        'description' => $clean['description'],
        'image' => $clean['image'],
        'status' => $clean['status'],
      ];

      if ($isUpdate) {
        $exists = $this->db->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $exists->execute([':id' => $id]);
        if (!$exists->fetchColumn()) {
          $this->db->rollBack();
          return ['success' => false, 'message' => 'Category not found.'];
        }

        /* prevent self-parenting */
        if ($clean['parent_id'] === $id) {
          $this->db->rollBack();
          return ['success' => false, 'message' => 'A category cannot be its own parent.'];
        }

        /* prevent parenting under its own descendant */
        if ($clean['parent_id'] && $this->isDescendant($clean['parent_id'], $id)) {
          $this->db->rollBack();
          return ['success' => false, 'message' => 'Cannot set a descendant as the parent.'];
        }

        $set = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($fields)));
        $sql = "UPDATE categories SET $set WHERE id = :id";
        $st = $this->db->prepare($sql);
        foreach ($fields as $k => $v) {
          $st->bindValue(":$k", $v, $this->pdoType($v));
        }
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();

        $categoryId = $id;
        $action = 'updated';
      } else {
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
        $phs = implode(', ', array_map(fn($k) => ":$k", array_keys($fields)));
        $sql = "INSERT INTO categories ($cols) VALUES ($phs)";
        $st = $this->db->prepare($sql);
        foreach ($fields as $k => $v) {
          $st->bindValue(":$k", $v, $this->pdoType($v));
        }
        $st->execute();

        $categoryId = (int) $this->db->lastInsertId();
        $action = 'created';
      }

      $this->logAudit($action, $categoryId, [
        'name' => $clean['name'],
        'slug' => $slug,
      ]);

      $this->db->commit();

      return [
        'success' => true,
        'message' => $isUpdate ? 'Category updated successfully.' : 'Category created successfully.',
        'action' => $action,
        'id' => $categoryId,
        'category' => $this->getCategory($categoryId),
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
      error_log('[CATEGORIES][PDO] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Database error while saving the category.'];
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[CATEGORIES] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Unexpected error while saving the category.'];
    }
  }

  /**
   * Delete a category.
   * Handles:
   *   • Has products   → refuse (or reassign to NULL? we refuse to be safe)
   *   • Has children   → refuse or move children to root
   *   • Otherwise      → hard delete
   */
  public function deleteCategory(int $id, bool $forceMoveChildrenToRoot = false): array
  {
    $st = $this->db->prepare("SELECT id, name FROM categories WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
      return ['success' => false, 'message' => 'Category not found.'];
    }

    /* count products */
    $st = $this->db->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
    $st->execute([':id' => $id]);
    $productCount = (int) $st->fetchColumn();
    if ($productCount > 0) {
      return [
        'success' => false,
        'message' => "Cannot delete: this category has $productCount product(s). Move or delete them first.",
      ];
    }

    /* count children */
    $st = $this->db->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = :id");
    $st->execute([':id' => $id]);
    $childCount = (int) $st->fetchColumn();
    if ($childCount > 0 && !$forceMoveChildrenToRoot) {
      return [
        'success' => false,
        'message' => "Cannot delete: this category has $childCount sub-categor(ies). Move or delete them first.",
      ];
    }

    try {
      $this->db->beginTransaction();

      if ($childCount > 0) {
        $mv = $this->db->prepare(
          "UPDATE categories SET parent_id = NULL WHERE parent_id = :id"
        );
        $mv->execute([':id' => $id]);
      }

      $del = $this->db->prepare("DELETE FROM categories WHERE id = :id");
      $del->execute([':id' => $id]);

      $this->logAudit('deleted', $id, ['name' => $row['name']]);

      $this->db->commit();

      return [
        'success' => true,
        'message' => 'Category deleted.',
        'mode' => 'hard',
        'id' => $id,
        'children_moved' => $childCount,
      ];
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[CATEGORIES][DELETE] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not delete the category.'];
    }
  }

  /** Bulk delete / status change. */
  public function bulkAction(string $action, array $ids): array
  {
    $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));
    if (!$ids) {
      return ['success' => false, 'message' => 'No categories selected.'];
    }

    $in = implode(',', array_fill(0, count($ids), '?'));
    $affected = 0;

    try {
      if ($action === 'delete') {
        foreach ($ids as $id) {
          $r = $this->deleteCategory($id, true);
          if ($r['success']) {
            $affected++;
          }
        }
        return [
          'success' => true,
          'message' => "$affected category(ies) deleted.",
          'affected' => $affected,
        ];
      }

      $map = [
        'activate' => 'active',
        'deactivate' => 'inactive',
      ];
      if (!array_key_exists($action, $map)) {
        return ['success' => false, 'message' => 'Unknown bulk action.'];
      }

      $st = $this->db->prepare("UPDATE categories SET status = ? WHERE id IN ($in)");
      $st->execute(array_merge([$map[$action]], $ids));
      $affected = $st->rowCount();

      return [
        'success' => true,
        'message' => "$affected category(ies) updated.",
        'affected' => $affected,
      ];
    } catch (PDOException $e) {
      error_log('[CATEGORIES][BULK] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Bulk action failed.'];
    }
  }

  /** Flat tree for parent select (excludes a given id + its descendants). */
  public function listForParentSelect(?int $excludeId = null): array
  {
    $sql = "SELECT id, parent_id, name, slug FROM categories";
    $args = [];

    if ($excludeId) {
      $sql .= " WHERE id <> :id";
      $args[':id'] = $excludeId;
    }
    $sql .= " ORDER BY name ASC";

    $st = $this->db->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();

    /* If editing, also drop the descendants of $excludeId */
    if ($excludeId) {
      $descendants = $this->descendantIds($excludeId);
      $rows = array_filter($rows, fn($r) => !in_array((int) $r['id'], $descendants, true));
    }

    return array_map(static fn(array $r): array => [
      'id' => (int) $r['id'],
      'parent_id' => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
      'name' => $r['name'],
      'slug' => $r['slug'],
    ], array_values($rows));
  }

  /** Dashboard counters. */
  public function getStats(): array
  {
    $sql = "SELECT
                    COUNT(*)                                AS total,
                    SUM(status = 'active')                  AS active,
                    SUM(status = 'inactive')                AS inactive,
                    SUM(parent_id IS NULL)                  AS root,
                    (SELECT COUNT(*) FROM products)         AS products
                FROM categories";
    $row = $this->db->query($sql)->fetch() ?: [];

    return [
      'total' => (int) ($row['total'] ?? 0),
      'active' => (int) ($row['active'] ?? 0),
      'inactive' => (int) ($row['inactive'] ?? 0),
      'root' => (int) ($row['root'] ?? 0),
      'products' => (int) ($row['products'] ?? 0),
    ];
  }

  public function errors(): array
  {
    return $this->errors;
  }

  /* =============================================================
   |  VALIDATION
   * ============================================================= */

  public function validateCategoryData(array $data, ?int $ignoreId = null): array
  {
    $this->errors = [];
    $clean = [];

    /* ---- name ---- */
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
      $this->errors['name'] = 'Category name is required.';
    } elseif (mb_strlen($name) < 2) {
      $this->errors['name'] = 'Category name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 100) {
      $this->errors['name'] = 'Category name cannot exceed 100 characters.';
    } else {
      /* unique name? (case-insensitive) */
      $sql = "SELECT id FROM categories WHERE LOWER(name) = LOWER(:name)"
        . ($ignoreId ? " AND id <> :id" : "")
        . " LIMIT 1";
      $st = $this->db->prepare($sql);
      $st->bindValue(':name', $name);
      if ($ignoreId) {
        $st->bindValue(':id', $ignoreId, PDO::PARAM_INT);
      }
      $st->execute();
      if ($st->fetchColumn()) {
        $this->errors['name'] = 'A category with that name already exists.';
      } else {
        $clean['name'] = $name;
      }
    }

    /* ---- description ---- */
    $desc = trim((string) ($data['description'] ?? ''));
    if ($desc === '') {
      $clean['description'] = null;   // optional in DB
    } elseif (mb_strlen($desc) > 5000) {
      $this->errors['description'] = 'Description is too long.';
    } else {
      $clean['description'] = $desc;
    }

    /* ---- image ---- */
    $image = trim((string) ($data['image'] ?? ''));
    if ($image !== '' && mb_strlen($image) > 500) {
      $this->errors['image'] = 'Image URL is too long.';
    } else {
      $clean['image'] = $image !== '' ? $image : null;
    }

    /* ---- status ---- */
    $status = strtolower(trim((string) ($data['status'] ?? 'active')));
    if (!in_array($status, self::ALLOWED_STATUS, true)) {
      $status = 'active';
    }
    $clean['status'] = $status;

    /* ---- parent ---- */
    $parent = $data['parent_id'] ?? $data['parent'] ?? null;
    if ($parent !== null && $parent !== '' && strtolower((string) $parent) !== 'none' && (int) $parent > 0) {
      $parentId = (int) $parent;
      if ($ignoreId && $parentId === $ignoreId) {
        $this->errors['parent_id'] = 'A category cannot be its own parent.';
      } else {
        $st = $this->db->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $st->execute([':id' => $parentId]);
        if (!$st->fetchColumn()) {
          $this->errors['parent_id'] = 'Selected parent category does not exist.';
        } else {
          $clean['parent_id'] = $parentId;
        }
      }
    } else {
      $clean['parent_id'] = null;
    }

    return $this->errors ? [] : $clean;
  }

  /* =============================================================
   |  INTERNAL HELPERS
   * ============================================================= */

  private function presentCategory(array $r): array
  {
    return [
      'id' => (int) $r['id'],
      'parent_id' => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
      'parent_name' => $r['parent_name'] ?? null,
      'parent_slug' => $r['parent_slug'] ?? null,
      'name' => $r['name'],
      'slug' => $r['slug'],
      'description' => $r['description'] ?? null,
      'image' => $r['image'] ?? null,
      'status' => ucfirst((string) $r['status']),
      'status_raw' => $r['status'],
      'product_count' => (int) ($r['product_count'] ?? 0),
      'child_count' => (int) ($r['child_count'] ?? 0),
      'created_at' => $r['created_at'] ?? null,
      'updated_at' => $r['updated_at'] ?? null,
    ];
  }

  private function uniqueSlug(string $base, ?int $ignoreId = null): string
  {
    $base = $base !== '' ? $base : 'category';
    $slug = $base;
    $i = 2;

    while (true) {
      $sql = "SELECT id FROM categories WHERE slug = :slug"
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

    return $text !== '' ? substr($text, 0, 150) : '';
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

  /** All descendant ids of $parentId. */
  private function descendantIds(int $parentId): array
  {
    $out = [];
    $queue = [$parentId];

    while ($queue) {
      $current = array_shift($queue);
      $st = $this->db->prepare("SELECT id FROM categories WHERE parent_id = :id");
      $st->execute([':id' => $current]);
      foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $cid) {
        $cid = (int) $cid;
        if (!in_array($cid, $out, true)) {
          $out[] = $cid;
          $queue[] = $cid;
        }
      }
    }

    return $out;
  }

  /** Is $candidateId a descendant of $ancestorId? */
  private function isDescendant(int $candidateId, int $ancestorId): bool
  {
    return in_array($candidateId, $this->descendantIds($ancestorId), true);
  }

  private function logAudit(string $action, int $entityId, array $details = []): void
  {
    try {
      $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

      $st = $this->db->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details)
                 VALUES (:user_id, :action, 'categories', :entity_id, :ip, :ua, :details)"
      );
      $st->execute([
        ':user_id' => $userId,
        ':action' => 'category_' . $action,
        ':entity_id' => $entityId,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
      ]);
    } catch (Throwable $e) {
      error_log('[CATEGORIES][AUDIT] ' . $e->getMessage());
    }
  }
}