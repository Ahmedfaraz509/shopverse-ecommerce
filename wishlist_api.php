<?php
declare(strict_types=1);

/* ==================================================================
 |  wishlist_api.php  —  login required
 |
 |  GET  wishlist_api.php?action=list
 |  GET  wishlist_api.php?action=count
 |  GET  wishlist_api.php?action=has&product_id=12
 |  POST wishlist_api.php?action=add      {product_id}
 |  POST wishlist_api.php?action=remove   {product_id}
 |  POST wishlist_api.php?action=toggle   {product_id}
 |  POST wishlist_api.php?action=clear
 |  POST wishlist_api.php?action=add_all_to_cart   {empty_after?}
 * ================================================================== */

require_once __DIR__ . '/includes/auth.php';       // adjust if needed
require_login_json();

if (session_status() === PHP_SESSION_NONE) {
  @session_start();
}

require_once __DIR__ . '/Wishlist_process.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);

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

if ($userId <= 0) {
  jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

try {
  $svc = new Wishlist_process(null, $userId);

  $pid = (int) ($input['product_id'] ?? $input['id'] ?? 0);

  $result = match ($action) {

    'list', 'index' => [
      'success' => true,
      'data' => [
        'items' => $svc->listItems(),
        'count' => $svc->count(),
      ],
    ],

    'count' => [
      'success' => true,
      'data' => ['count' => $svc->count()],
    ],

    'ids' => [
      'success' => true,
      'data' => ['ids' => $svc->ids()],
    ],

    'has' => (function () use ($svc, $pid) {
        if ($pid <= 0) {
          return ['success' => false, 'message' => 'Missing product_id.'];
        }
        return [
        'success' => true,
        'data' => ['product_id' => $pid, 'in_wishlist' => $svc->has($pid)],
        ];
      })(),

    'add' => (function () use ($svc, $pid) {
        if ($pid <= 0) {
          return ['success' => false, 'message' => 'Missing product_id.'];
        }
        return $svc->add($pid);
      })(),

    'remove', 'delete' => (function () use ($svc, $pid) {
        if ($pid <= 0) {
          return ['success' => false, 'message' => 'Missing product_id.'];
        }
        return $svc->remove($pid);
      })(),

    'toggle' => (function () use ($svc, $pid) {
        if ($pid <= 0) {
          return ['success' => false, 'message' => 'Missing product_id.'];
        }
        return $svc->toggle($pid);
      })(),

    'clear' => $svc->clear(),

    'add_all_to_cart', 'move_all' => $svc->addAllToCart(
      !isset($input['empty_after']) || (bool) $input['empty_after']
    ),

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[wishlist_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}