<?php
declare(strict_types=1);

/* ==================================================================
 |  shop_api.php
 |
 |  GET  shop_api.php?action=list&search=shirt&category=shoes
 |          &price_min=20&price_max=200&rating_min=4
 |          &sort=price-asc&page=1&per_page=12
 |
 |  GET  shop_api.php?action=facets           → sidebar (categories + counts)
 |  GET  shop_api.php?action=price-bounds     → min/max for slider
 |  GET  shop_api.php?action=stats
 |  GET  shop_api.php?action=init             → bundle: facets + bounds + stats
 * ================================================================== */

require_once __DIR__ . '/Shop_process.php';

$input = array_merge($_GET, $_POST);
$action = strtolower(trim((string) ($input['action'] ?? 'list')));

function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: public, max-age=60');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $svc = new Shop_process();

  $result = match ($action) {

    'list', 'index', 'products' => [
      'success' => true,
      'data' => $svc->listProducts($input),
    ],

    'facets', 'categories' => [
      'success' => true,
      'data' => $svc->getCategoryFacets($input),
    ],

    'price-bounds', 'bounds' => [
      'success' => true,
      'data' => $svc->getPriceBounds(),
    ],

    'stats' => [
      'success' => true,
      'data' => $svc->getStats(),
    ],

    'init' => [
      'success' => true,
      'data' => [
        'facets' => $svc->getCategoryFacets($input),
        'bounds' => $svc->getPriceBounds(),
        'stats' => $svc->getStats(),
        'listing' => $svc->listProducts($input),
      ],
    ],

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[shop_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}