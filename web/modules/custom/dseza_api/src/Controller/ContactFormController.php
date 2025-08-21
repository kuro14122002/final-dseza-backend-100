<?php

namespace Drupal\dseza_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Entity\Webform;

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

      // Thay vì gửi email, tạo Webform submission cho webform 'contact'.
      $webform = Webform::load('contact');
      if (!$webform) {
        $this->logger->error('Webform "contact" không tồn tại');
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Webform liên hệ chưa được cấu hình. Vui lòng liên hệ quản trị viên.',
        ], 500);
      }

      $values = [
        'webform_id' => 'contact',
        'entity_type' => NULL,
        'entity_id' => NULL,
        'in_draft' => FALSE,
        'langcode' => 'vi',
        'data' => [
          // Phải khớp machine name các phần tử trong webform Contact
          'ho_ten' => $data['hoTen'],
          'email' => $data['email'],
          'tieu_de' => $data['tieuDe'],
          'noi_dung' => $data['noiDung'],
        ],
      ];

      $submission = WebformSubmission::create($values);
      $submission->save();

      // Đăng ký flood khi gửi thành công
      $this->flood->register($event, $windowSeconds, $identifier);
      $this->logger->info('Đã tạo Webform Contact submission từ @email với tiêu đề: @title', [
        '@email' => $data['email'],
        '@title' => $data['tieuDe'],
      ]);

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Thư liên hệ của bạn đã được ghi nhận',
        'sid' => $submission->id(),
        'token' => $submission->getToken(),
      ], 201);

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