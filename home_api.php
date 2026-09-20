<?php
declare(strict_types=1);

/* ==================================================================
 |  home_api.php
 |
 |  GET  home_api.php?action=init              → everything the homepage needs
 |  GET  home_api.php?action=categories
 |  GET  home_api.php?action=best&limit=8
 |  GET  home_api.php?action=new&limit=8
 |  GET  home_api.php?action=trending&limit=4
 |  GET  home_api.php?action=testimonials&limit=3
 |  GET  home_api.php?action=stats
 * ================================================================== */

require_once __DIR__ . '/Home_process.php';

$input = array_merge($_GET, $_POST);
$action = strtolower(trim((string) ($input['action'] ?? 'init')));

function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: public, max-age=120');  // 2-min public cache
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $svc = new Home_process();

  $limit = (int) ($input['limit'] ?? 0);

  $result = match ($action) {

    'init', 'all' => [
      'success' => true,
      'data' => $svc->getHomeData(),
    ],

    'stats' => [
      'success' => true,
      'data' => $svc->getHeroStats(),
    ],

    'categories', 'featured_categories' => [
      'success' => true,
      'data' => $svc->getFeaturedCategories($limit > 0 ? $limit : 6),
    ],

    'best', 'best_sellers' => [
      'success' => true,
      'data' => $svc->getBestSellers($limit > 0 ? $limit : 8),
    ],

    'new', 'new_arrivals' => [
      'success' => true,
      'data' => $svc->getNewArrivals($limit > 0 ? $limit : 8),
    ],

    'trending' => [
      'success' => true,
      'data' => $svc->getTrending($limit > 0 ? $limit : 4),
    ],

    'testimonials', 'reviews' => [
      'success' => true,
      'data' => $svc->getTestimonials($limit > 0 ? $limit : 3),
    ],

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[home_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}