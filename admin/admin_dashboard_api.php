<?php
declare(strict_types=1);

/* ==================================================================
 |  admin_dashboard_api.php  —  ADMIN only
 |
 |  GET  admin_dashboard_api.php?action=init
 |  GET  admin_dashboard_api.php?action=stats
 * ================================================================== */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin', '../login.php');

if (session_status() === PHP_SESSION_NONE) {
  @session_start();
}

require_once __DIR__ . '/Admin_dashboard_process.php';

$input = array_merge($_GET, $_POST);
$action = strtolower(trim((string) ($input['action'] ?? 'init')));

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
  $svc = new Admin_dashboard_process();

  $result = match ($action) {

    'init', 'all' => [
      'success' => true,
      'data' => $svc->getDashboard(),
    ],

    'stats' => [
      'success' => true,
      'data' => $svc->getStats(),
    ],

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[admin_dashboard_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}