<?php
declare(strict_types=1);

/* ==================================================================
 |  categories_api.php  —  JSON endpoint for Categories_process
 |
 |  Examples:
 |    GET  categories_api.php?action=list&search=shoes
 |    GET  categories_api.php?action=get&id=3
 |    GET  categories_api.php?action=parents&exclude_id=3
 |    GET  categories_api.php?action=stats
 |    POST categories_api.php?action=create         (JSON body)
 |    POST categories_api.php?action=update&id=3     (JSON body)
 |    POST categories_api.php?action=delete&id=3
 |    POST categories_api.php?action=bulk           (JSON body)
 * ================================================================== */

require_once __DIR__ . '/Categories_process.php';

/* ---- admin only ----
 * Must use the app's session (SHOPVERSE_SESS). Previously a bare session_start() created a
 * separate PHPSESSID session: nobody was authenticated, and audit_logs.user_id was always NULL. */
require_once __DIR__ . '/../includes/auth.php';

(function (): void {
  $deny = static function (int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
  };
  if (!is_logged_in()) {
    $deny(401, 'Please log in.');
  }
  if (current_role() !== 'admin') {
    $deny(403, 'Access denied.');
  }
})();

$rawBody = file_get_contents('php://input') ?: '';
$json = json_decode($rawBody, true);
$input = array_merge($_GET, $_POST, is_array($json) ? $json : []);

$action = strtolower(trim((string) ($input['action'] ?? 'list')));

function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $svc = new Categories_process();

  $result = match ($action) {

    'list', 'index' => [
      'success' => true,
      'data' => $svc->listCategories($input),
    ],

    'get', 'view' => (function () use ($svc, $input) {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid category id.'];
        }
        $c = $svc->getCategory($id);
        return $c
        ? ['success' => true, 'data' => $c]
        : ['success' => false, 'message' => 'Category not found.'];
      })(),

    'parents', 'for_parent' => (function () use ($svc, $input) {
        $exclude = (int) ($input['exclude_id'] ?? $input['exclude'] ?? 0);
        return [
        'success' => true,
        'data' => $svc->listForParentSelect($exclude ?: null),
        ];
      })(),

    'create', 'store', 'save' => $svc->saveCategory($input, null),

    'update', 'edit' => (function () use ($svc, $input) {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid category id.'];
        }
        return $svc->saveCategory($input, $id);
      })(),

    'delete', 'destroy' => (function () use ($svc, $input) {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid category id.'];
        }
        $force = !empty($input['force']) || !empty($input['move_children']);
        return $svc->deleteCategory($id, (bool) $force);
      })(),

    'bulk' => (function () use ($svc, $input) {
        $bulkAction = strtolower((string) ($input['bulk_action'] ?? $input['do'] ?? ''));
        $ids = $input['ids'] ?? [];
        if (is_string($ids)) {
          $ids = array_filter(explode(',', $ids));
        }
        return $svc->bulkAction($bulkAction, is_array($ids) ? $ids : []);
      })(),

    'stats' => [
      'success' => true,
      'data' => $svc->getStats(),
    ],

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[categories_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}