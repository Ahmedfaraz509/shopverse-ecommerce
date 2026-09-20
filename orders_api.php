<?php
declare(strict_types=1);

/* ==================================================================
 |  orders_api.php  —  Customer orders endpoint (login required)
 |
 |  GET  orders_api.php?action=list&status=delivered&page=1
 |  GET  orders_api.php?action=get&id=12
 |  GET  orders_api.php?action=stats
 |  POST orders_api.php?action=cancel&id=12
 * ================================================================== */

require_once __DIR__ . '/includes/auth.php';       // adjust path if needed
require_once __DIR__ . '/Orders_process.php';

require_login_json();

if (session_status() === PHP_SESSION_NONE) {
  @session_start();
}

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
  $svc = new Orders_process();

  $result = match ($action) {

    'list', 'index' => [
      'success' => true,
      'data' => $svc->listOrders($userId, $input),
    ],

    'get', 'view' => (function () use ($svc, $input, $userId) {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid order id.'];
        }
        $o = $svc->getOrder($id, $userId);
        return $o
        ? ['success' => true, 'data' => $o]
        : ['success' => false, 'message' => 'Order not found.'];
      })(),

    'stats' => [
      'success' => true,
      'data' => $svc->getStats($userId),
    ],

    'cancel' => (function () use ($svc, $input, $userId) {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid order id.'];
        }
        return $svc->cancelOrder($id, $userId);
      })(),

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[orders_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}