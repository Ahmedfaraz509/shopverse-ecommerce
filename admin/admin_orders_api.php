<?php
declare(strict_types=1);

/* ==================================================================
 |  admin_orders_api.php  —  ADMIN only
 |
 |  GET  admin_orders_api.php?action=list&status=pending&page=1
 |  GET  admin_orders_api.php?action=get&id=12
 |  GET  admin_orders_api.php?action=stats
 |  POST admin_orders_api.php?action=status      {id, status}
 |  POST admin_orders_api.php?action=payment     {id, status}
 |  POST admin_orders_api.php?action=delete      {id}
 * ================================================================== */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin', '../login.php');            // must run before any output

if (session_status() === PHP_SESSION_NONE) {
  @session_start();
}

require_once __DIR__ . '/Admin_orders_process.php';

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
  $svc = new Admin_orders_process();

  $id = (int) ($input['id'] ?? 0);

  $result = match ($action) {

    'list', 'index' => [
      'success' => true,
      'data' => $svc->listOrders($input),
    ],

    'get', 'view' => (function () use ($svc, $id) {
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid order id.'];
        }
        $o = $svc->getOrder($id);
        return $o
        ? ['success' => true, 'data' => $o]
        : ['success' => false, 'message' => 'Order not found.'];
      })(),

    'stats' => [
      'success' => true,
      'data' => $svc->getStats(),
    ],

    'status' => (function () use ($svc, $input, $id) {
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid order id.'];
        }
        return $svc->updateOrderStatus($id, (string) ($input['status'] ?? ''));
      })(),

    'payment' => (function () use ($svc, $input, $id) {
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid order id.'];
        }
        return $svc->updatePaymentStatus($id, (string) ($input['status'] ?? ''));
      })(),

    'delete', 'destroy' => (function () use ($svc, $id) {
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Invalid order id.'];
        }
        return $svc->deleteOrder($id);
      })(),

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[admin_orders_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}