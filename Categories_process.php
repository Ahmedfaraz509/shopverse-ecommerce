<?php
declare(strict_types=1);

/* ==================================================================
 |  Categories_process  —  ShopVerse (public site copy)
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
   |  PUBLIC READS
   * ============================================================= */

  /**
   * Public category listing.
   *   • Always restricted to status = 'active'
   *   • Optional: only root categories (parent_id IS NULL)
   *   • Optional: include child count + product count
   */
  public function listPublic(array $f = []): array
  {
    $where = ["c.status = 'active'"];
    $params = [];

    /* root only? */
    if (!empty($f['root_only'])) {
      $where[] = 'c.parent_id IS NULL';
    }

    /* specific parent */
    if (isset($f['parent_id']) && is_numeric($f['parent_id'])) {
      $where[] = 'c.parent_id = :parent_id';
      $params[':parent_id'] = (int) $f['parent_id'];
    }

    /* slug lookup */
    if (!empty($f['slug'])) {
      $where[] = 'c.slug = :slug';
      $params[':slug'] = (string) $f['slug'];
    }

    /* search */
    $search = trim((string) ($f['search'] ?? $f['q'] ?? ''));
    if ($search !== '') {
      $like = '%' . $this->escapeLike($search) . '%';
      $where[] = '(c.name LIKE :s1 OR c.description LIKE :s2)';
      $params[':s1'] = $like;
      $params[':s2'] = $like;
    }

    /* sort */
    $sortMap = [
      'name' => 'c.name ASC',
      'name_desc' => 'c.name DESC',
      'newest' => 'c.created_at DESC, c.id DESC',
      'products' => 'product_count DESC, c.name ASC',
      'children' => 'child_count DESC, c.name ASC',
    ];
    $sortKey = strtolower((string) ($f['sort'] ?? 'name'));
    $orderBy = $sortMap[$sortKey] ?? $sortMap['name'];

    /* pagination */
    $page = max(1, (int) ($f['page'] ?? 1));
    $perPage = (int) ($f['per_page'] ?? $f['limit'] ?? 100);
    $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
    $offset = ($page - 1) * $perPage;

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    /* total */
    $countSql = "SELECT COUNT(*) FROM categories c $whereSql";
    $st = $this->db->prepare($countSql);
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    /* rows */
    $sql = "SELECT c.*,
                       p.name AS parent_name,
                       p.slug AS parent_slug,
                       (SELECT COUNT(*)
                          FROM products pr
                         WHERE pr.category_id = c.id
                           AND pr.status = 'active')      AS product_count,
                       (SELECT COUNT(*)
                          FROM categories ch
                         WHERE ch.parent_id = c.id
                           AND ch.status = 'active')      AS child_count
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

    $items = array_map([$this, 'presentPublic'], $st->fetchAll());

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $perPage,
      'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
    ];
  }

  /** Single public category by slug or id (must be active). */
  public function getPublic(string $slugOrId): ?array
  {
    $col = ctype_digit($slugOrId) ? 'id' : 'slug';
    $sql = "SELECT c.*,
                          p.name AS parent_name,
                          p.slug AS parent_slug,
                          (SELECT COUNT(*)
                             FROM products pr
                            WHERE pr.category_id = c.id
                              AND pr.status = 'active') AS product_count,
                          (SELECT COUNT(*)
                             FROM categories ch
                            WHERE ch.parent_id = c.id
                              AND ch.status = 'active') AS child_count
                     FROM categories c
                     LEFT JOIN categories p ON p.id = c.parent_id
                    WHERE c.$col = :val
                      AND c.status = 'active'
                    LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute([':val' => $slugOrId]);
    $row = $st->fetch();

    return $row ? $this->presentPublic($row) : null;
  }

  /** Children of a category (active only). */
  public function childrenOf(int $parentId): array
  {
    $st = $this->db->prepare(
      "SELECT c.*,
                    (SELECT COUNT(*)
                       FROM products pr
                      WHERE pr.category_id = c.id
                        AND pr.status = 'active') AS product_count,
                    (SELECT COUNT(*)
                       FROM categories ch
                      WHERE ch.parent_id = c.id
                        AND ch.status = 'active') AS child_count
               FROM categories c
              WHERE c.parent_id = :pid
                AND c.status = 'active'
           ORDER BY c.name ASC"
    );
    $st->execute([':pid' => $parentId]);

    return array_map([$this, 'presentPublic'], $st->fetchAll());
  }

  /** Featured / "top" categories by product count. */
  public function topCategories(int $limit = 8): array
  {
    $limit = max(1, min($limit, 50));
    $sql = "SELECT c.*,
                         (SELECT COUNT(*)
                            FROM products pr
                           WHERE pr.category_id = c.id
                             AND pr.status = 'active') AS product_count,
                         0 AS child_count
                    FROM categories c
                   WHERE c.status = 'active'
                     AND c.parent_id IS NULL
                ORDER BY product_count DESC, c.name ASC
                   LIMIT $limit";
    $rows = $this->db->query($sql)->fetchAll();

    return array_map([$this, 'presentPublic'], $rows);
  }

  /** Category menu tree (2 levels max, for nav). */
  public function menuTree(): array
  {
    $roots = $this->listPublic(['root_only' => 1, 'sort' => 'name'])['items'];

    foreach ($roots as &$root) {
      $root['children'] = $this->childrenOf((int) $root['id']);
    }
    unset($root);

    return $roots;
  }

  public function errors(): array
  {
    return $this->errors;
  }

  /* =============================================================
   |  INTERNAL
   * ============================================================= */

  private function presentPublic(array $r): array
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
      'status' => $r['status'],
      'product_count' => (int) ($r['product_count'] ?? 0),
      'child_count' => (int) ($r['child_count'] ?? 0),
      'created_at' => $r['created_at'] ?? null,
    ];
  }

  private function escapeLike(string $s): string
  {
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);
  }
}