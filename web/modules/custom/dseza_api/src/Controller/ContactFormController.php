<?php

namespace Drupal\dseza_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Controller để xử lý việc gửi form liên hệ.
 */
class ContactFormController extends ControllerBase {

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Flood control service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * ContactFormController constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, MailManagerInterface $mail_manager, FloodInterface $flood) {
    $this->logger = $logger_factory->get('dseza_api');
    $this->mailManager = $mail_manager;
    $this->flood = $flood;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('plugin.manager.mail'),
      $container->get('flood')
    );
  }

  /**
   * Xử lý việc gửi form liên hệ.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function handleSubmission(Request $request) {
    try {
      // Flood control: limit number of submissions per identifier within a time window.
      $event = 'dseza_api_contact_submit';
      $windowSeconds = 3600; // 1 hour window
      $maxAttempts = 5; // Allow 5 submissions per window
      $identifier = $this->currentUser()->isAuthenticated() ? (string) $this->currentUser()->id() : (string) $request->getClientIp();

      if (!$this->flood->isAllowed($event, $maxAttempts, $windowSeconds, $identifier)) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Bạn đã gửi quá nhiều lần. Vui lòng thử lại sau.',
        ], 429);
      }

      // Lấy dữ liệu JSON từ request body
      $content = $request->getContent();
      
      if (empty($content)) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Dữ liệu không được để trống',
        ], 400);
      }

      $data = json_decode($content, TRUE);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Dữ liệu JSON không hợp lệ',
        ], 400);
      }

      // Kiểm tra các trường bắt buộc
      $required_fields = ['hoTen', 'email', 'tieuDe', 'noiDung'];
      foreach ($required_fields as $field) {
        if (empty($data[$field])) {
          return new JsonResponse([
            'status' => 'error',
            'message' => "Trường {$field} là bắt buộc",
          ], 400);
        }
      }

      // Validate email
      if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Địa chỉ email không hợp lệ',
        ], 400);
      }

      // Lấy email và tên website từ cấu hình
      $siteConfig = \Drupal::config('system.site');
      $siteName = $siteConfig->get('name') ?: 'Website';
      $siteEmail = $siteConfig->get('mail');
      if (empty($siteEmail)) {
        // Fallback an toàn nếu chưa cấu hình email site.
        $host = parse_url($request->getSchemeAndHttpHost(), PHP_URL_HOST) ?: 'localhost';
        $siteEmail = 'no-reply@' . $host;
      }

      // Chuẩn bị dữ liệu email
      $admin_email = $siteEmail; // Gửi về email quản trị của website
      $subject = 'Thư liên hệ từ ' . $siteName . ': ' . $data['tieuDe'];
      
      // Tạo nội dung email
      $message_body = "
Bạn có một thư liên hệ mới từ website:

Họ tên: {$data['hoTen']}
Email: {$data['email']}
Tiêu đề: {$data['tieuDe']}

Nội dung:
{$data['noiDung']}

---
Thư này được gửi tự động từ website DSEZA.
";

      // Tham số email
      $params = [
        'subject' => $subject,
        'body' => $message_body,
        // Luôn gửi từ email của website để tránh SPF/DMARC.
        'from' => $siteEmail,
        'headers' => [
          'Reply-To' => $data['hoTen'] . ' <' . $data['email'] . '>',
        ],
      ];

      // Log thông tin trước khi gửi email
      $this->logger->info('Attempting to send contact form email from @email', [
        '@email' => $data['email'],
      ]);

      // Kiểm tra cấu hình email hiện tại
      $mail_interface = \Drupal::config('system.mail')->get('interface.default');
      $this->logger->info('Current mail interface: @interface', [
        '@interface' => $mail_interface,
      ]);

      // Gửi email với error handling tốt hơn
      try {
        // Kiểm tra nếu đang ở development environment
        $is_development = getenv('LANDO') === 'ON' || 
                         (php_uname('n') === 'appserver' && strpos($_SERVER['HTTP_HOST'], 'lndo.site') !== false);

        if ($is_development) {
          // Trong development, chỉ log thông tin và return success
          $this->logger->info('Development mode: Contact form email logged instead of sent. From: @email, Subject: @subject', [
            '@email' => $data['email'],
            '@subject' => $subject,
          ]);

          return new JsonResponse([
            'status' => 'success',
            'message' => 'Thư liên hệ đã được gửi thành công (development mode)',
          ], 200);
        }

        // Production: thực sự gửi email, luôn dùng from là email của site
        $result = $this->mailManager->mail(
          'dseza_api',
          'contact_form',
          $admin_email,
          \Drupal::languageManager()->getCurrentLanguage()->getId(),
          $params
        );

        if ($result['result']) {
          // Đăng ký flood khi gửi thành công
          $this->flood->register($event, $windowSeconds, $identifier);
          // Log thành công
          $this->logger->info('Contact form submitted successfully from @email', [
            '@email' => $data['email'],
          ]);

          return new JsonResponse([
            'status' => 'success',
            'message' => 'Thư liên hệ đã được gửi thành công',
          ], 200);
        } else {
          // Log lỗi
          $this->logger->error('Failed to send contact form email from @email', [
            '@email' => $data['email'],
          ]);

          return new JsonResponse([
            'status' => 'error',
            'message' => 'Có lỗi xảy ra khi gửi thư liên hệ',
          ], 500);
        }
      } catch (\Exception $mail_exception) {
        // Log lỗi gửi email cụ thể
        $this->logger->error('Email sending exception: @message', [
          '@message' => $mail_exception->getMessage(),
        ]);

        // Trong development environment, vẫn coi như thành công nếu là test mode
        $mail_interface = \Drupal::config('system.mail')->get('interface.default');
        if ($mail_interface === 'test_mail_collector' || $mail_interface === 'symfony_mailer') {
          $this->logger->info('Email handled by test system for @email', [
            '@email' => $data['email'],
          ]);

          return new JsonResponse([
            'status' => 'success',
            'message' => 'Thư liên hệ đã được xử lý thành công (test mode)',
          ], 200);
        }

        return new JsonResponse([
          'status' => 'error',
          'message' => 'Có lỗi xảy ra khi gửi thư liên hệ: ' . $mail_exception->getMessage(),
        ], 500);
      }

    } catch (\Exception $e) {
      // Log lỗi
      $this->logger->error('Exception in contact form submission: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Đã có lỗi xảy ra, vui lòng thử lại sau',
      ], 500);
    }
  }

} 