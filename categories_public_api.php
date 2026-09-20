<?php
declare(strict_types=1);

/* ==================================================================
 |  categories_public_api.php  —  Public JSON endpoint (no login)
 |
 |  GET  categories_public_api.php?action=list
 |  GET  categories_public_api.php?action=list&root_only=1
 |  GET  categories_public_api.php?action=get&slug=shoes
 |  GET  categories_public_api.php?action=children&parent_id=3
 |  GET  categories_public_api.php?action=top&limit=8
 |  GET  categories_public_api.php?action=menu
 * ================================================================== */

require_once __DIR__ . '/Categories_process.php';

$input = array_merge($_GET, $_POST);

$action = strtolower(trim((string) ($input['action'] ?? 'list')));

function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: public, max-age=300'); // 5-min browser cache
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $svc = new Categories_process();

  $result = match ($action) {

    'list', 'index' => [
      'success' => true,
      'data' => $svc->listPublic($input),
    ],

    'get', 'view' => (function () use ($svc, $input) {
        $slug = trim((string) ($input['slug'] ?? $input['id'] ?? ''));
        if ($slug === '') {
          return ['success' => false, 'message' => 'Missing slug or id.'];
        }
        $c = $svc->getPublic($slug);
        return $c
        ? ['success' => true, 'data' => $c]
        : ['success' => false, 'message' => 'Category not found.'];
      })(),

    'children' => (function () use ($svc, $input) {
        $pid = (int) ($input['parent_id'] ?? 0);
        if ($pid <= 0) {
          return ['success' => false, 'message' => 'Invalid parent_id.'];
        }
        return ['success' => true, 'data' => $svc->childrenOf($pid)];
      })(),

    'top' => [
      'success' => true,
      'data' => $svc->topCategories((int) ($input['limit'] ?? 8)),
    ],

    'menu' => [
      'success' => true,
      'data' => $svc->menuTree(),
    ],

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[categories_public_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}