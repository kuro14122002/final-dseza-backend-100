<?php

namespace Drupal\dseza_api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber để xử lý CORS headers cho API endpoints.
 */
class CorsEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse', 10];
    return $events;
  }

  /**
   * Add CORS headers to API responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    $request = $event->getRequest();
    $response = $event->getResponse();
    
    // Chỉ áp dụng cho API endpoints
    $path = $request->getPathInfo();
    if (strpos($path, '/api/v1/') === 0) {
      // Đọc danh sách allowed origins từ cấu hình.
      $allowedOrigins = \Drupal::config('dseza_api.settings')->get('allowed_origins');
      if (!is_array($allowedOrigins)) {
        // Hỗ trợ cấu hình dạng chuỗi phân tách bằng dấu phẩy.
        $allowedOrigins = array_filter(array_map('trim', explode(',', (string) $allowedOrigins)));
      }

      $origin = $request->headers->get('Origin');
      if ($origin && in_array($origin, $allowedOrigins, true)) {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        // Cho phép credentials khi cần (ví dụ cookie-based auth).
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
      }

      $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
      $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
      $response->headers->set('Access-Control-Max-Age', '86400');
    }
  }

} 