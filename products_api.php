<?php
declare(strict_types=1);

/* ==================================================================
 |  products_api.php  —  Simple JSON endpoint for Products_process
 |
 |  Usage examples:
 |    GET  products_api.php?action=list&search=shirt&page=1
 |    GET  products_api.php?action=get&id=5
 |    POST products_api.php?action=create           (JSON body)
 |    POST products_api.php?action=update&id=5       (JSON body)
 |    POST products_api.php?action=delete&id=5
 |    POST products_api.php?action=bulk             (JSON body)
 |    GET  products_api.php?action=categories
 |    GET  products_api.php?action=brands
 |    GET  products_api.php?action=stats
 * ================================================================== */

require_once __DIR__ . '/Products_process.php';

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

/* ---- read input (GET + POST + JSON body) ---- */
$rawBody = file_get_contents('php://input') ?: '';
$json = json_decode($rawBody, true);
$input = array_merge($_GET, $_POST, is_array($json) ? $json : []);

$action = strtolower(trim((string) ($input['action'] ?? 'list')));

/* ---- helpers ---- */
function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/* ---- dispatch ---- */
try {
  $svc = new Products_process();

  $result = match ($action) {

    'list', 'index' => [
      'success' => true,
      'data' => $svc->listProducts($input),
    ],

    'get', 'view' => (function () use ($svc, $input) {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid product id.'];
        }
        $p = $svc->getProduct($id);
        return $p
        ? ['success' => true, 'data' => $p]
        : ['success' => false, 'message' => 'Product not found.'];
      })(),

    'create', 'store', 'save' => $svc->saveProduct($input, null),

    'update', 'edit' => (function () use ($svc, $input) {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid product id.'];
        }
        return $svc->saveProduct($input, $id);
      })(),

    'delete', 'destroy' => (function () use ($svc, $input) {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid product id.'];
        }
        return $svc->deleteProduct($id);
      })(),

    'bulk' => (function () use ($svc, $input) {
        $bulkAction = strtolower((string) ($input['bulk_action'] ?? $input['do'] ?? ''));
        $ids = $input['ids'] ?? [];
        if (is_string($ids)) {
          $ids = array_filter(explode(',', $ids));
        }
        return $svc->bulkAction($bulkAction, is_array($ids) ? $ids : []);
      })(),

    'categories' => [
      'success' => true,
      'data' => $svc->listCategories(),
    ],

    'brands' => [
      'success' => true,
      'data' => $svc->listBrands(),
    ],

    'stats' => [
      'success' => true,
      'data' => $svc->getStats(),
    ],

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[products_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}