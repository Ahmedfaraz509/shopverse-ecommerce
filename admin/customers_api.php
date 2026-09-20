<?php
declare(strict_types=1);

/* ==================================================================
 |  customers_api.php  —  admin only
 |    GET customers_api.php?action=list
 |    GET customers_api.php?action=get&id=5
 * ================================================================== */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/Customers_process.php';

function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (!is_logged_in()) {
  jsonResponse(['success' => false, 'message' => 'Please log in.'], 401);
}
if (current_role() !== 'admin') {
  jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
}

$action = strtolower(trim((string) ($_GET['action'] ?? 'list')));

try {
  $svc = new Customers_process();

  $result = match ($action) {
    'list', 'index' => ['success' => true, 'data' => $svc->listCustomers()],
    'get', 'view' => (function () use ($svc): array {
        $id = (int) ($_GET['id'] ?? 0);
        $c = $id > 0 ? $svc->getCustomer($id) : null;
        return $c ? ['success' => true, 'data' => $c] : ['success' => false, 'message' => 'Customer not found.'];
      })(),
    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[customers_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}
