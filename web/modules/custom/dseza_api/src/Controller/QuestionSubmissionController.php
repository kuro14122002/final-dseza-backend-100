<?php

namespace Drupal\dseza_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Entity\Webform;

/**
 * Controller để xử lý việc gửi câu hỏi.
 */
class QuestionSubmissionController extends ControllerBase {

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Flood control service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * QuestionSubmissionController constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager, FloodInterface $flood) {
    $this->logger = $logger_factory->get('dseza_api');
    $this->entityTypeManager = $entity_type_manager;
    $this->flood = $flood;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('flood')
    );
  }

  /**
   * Xử lý việc gửi câu hỏi.
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
      // Identifier: authenticated user ID, otherwise client IP for anonymous.
      $event = 'dseza_api_question_submit';
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

      // Tạo submission cho Webform 'questionaire'
      $webform = Webform::load('questionaire');
      if (!$webform) {
        $this->logger->error('Webform "questionaire" không tồn tại');
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Webform chưa được cấu hình. Vui lòng liên hệ quản trị viên.',
        ], 500);
      }

      $values = [
        'webform_id' => 'questionaire',
        'entity_type' => NULL,
        'entity_id' => NULL,
        'in_draft' => FALSE,
        'langcode' => 'vi',
        'data' => [
          // Chú ý: các key bên dưới phải khớp machine name của phần tử trong Webform
          'full_name' => $data['hoTen'],
          'email' => $data['email'],
          'phone' => !empty($data['dienThoai']) ? $data['dienThoai'] : '',
          'address' => !empty($data['congTy']) ? $data['congTy'] : '',
          'category' => !empty($data['category']) ? $data['category'] : 'khac',
          'title' => $data['tieuDe'],
          'content' => $data['noiDung'],
        ],
      ];

      $submission = WebformSubmission::create($values);
      $submission->save();

      // Register flood event khi thành công
      $this->flood->register($event, $windowSeconds, $identifier);
      $this->logger->info('Đã tạo Webform submission từ @email với tiêu đề: @title', [
        '@email' => $data['email'],
        '@title' => $data['tieuDe'],
      ]);

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Câu hỏi của bạn đã được gửi thành công và đang chờ xử lý',
        'sid' => $submission->id(),
        'token' => $submission->getToken(),
      ], 201);

    } catch (\Exception $e) {
      // Log lỗi chi tiết
      $this->logger->error('Lỗi khi gửi câu hỏi: @message. Stack trace: @trace. Data: @data', [
        '@message' => $e->getMessage(),
        '@trace' => $e->getTraceAsString(),
        '@data' => json_encode($data ?? []),
      ]);

      // Return error với thông tin chi tiết hơn trong development
      $errorMessage = 'Có lỗi xảy ra trong quá trình xử lý. Vui lòng thử lại sau.';
      
      // Hiển thị lỗi chi tiết nếu đang ở development mode
      if (defined('DRUPAL_ENVIRONMENT') && DRUPAL_ENVIRONMENT === 'dev') {
        $errorMessage .= ' Error: ' . $e->getMessage();
      }

      return new JsonResponse([
        'status' => 'error',
        'message' => $errorMessage,
        'debug' => [
          'error_type' => get_class($e),
          'file' => $e->getFile(),
          'line' => $e->getLine(),
        ]
      ], 500);
    }
  }

  /**
   * Test endpoint để kiểm tra module hoạt động.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function testEndpoint() {
    try {
      // Kiểm tra content type question
      $nodeTypeStorage = $this->entityTypeManager->getStorage('node_type');
      $questionType = $nodeTypeStorage->load('question');
      
      $contentTypeExists = $questionType ? true : false;
      
      // Kiểm tra các fields sử dụng field configs
      $fieldDefinitions = $this->entityTypeManager
        ->getStorage('field_config')
        ->loadByProperties(['entity_type' => 'node', 'bundle' => 'question']);
      
      $availableFields = array_keys($fieldDefinitions);
      $fields = [];
      
      $requiredFields = [
        'field_nguoi_gui' => 'Người gửi',
        'field_email' => 'Email',
        'field_noi_dung_cau_hoi' => 'Nội dung câu hỏi'
      ];
      
      foreach ($requiredFields as $fieldName => $fieldLabel) {
        $fields[$fieldName] = [
          'label' => $fieldLabel,
          'exists' => in_array($fieldName, $availableFields),
          'required' => true
        ];
      }
      
      $optionalFields = [
        'field_so_dien_thoai' => 'Điện thoại',
        'field_dia_chi' => 'Địa chỉ/Công ty'
      ];
      
      foreach ($optionalFields as $fieldName => $fieldLabel) {
        $fields[$fieldName] = [
          'label' => $fieldLabel,
          'exists' => in_array($fieldName, $availableFields),
          'required' => false
        ];
      }

      return new JsonResponse([
        'status' => 'success',
        'message' => 'API module đang hoạt động',
        'timestamp' => date('Y-m-d H:i:s'),
        'system_info' => [
          'content_type_question_exists' => $contentTypeExists,
          'fields' => $fields,
          'module_enabled' => true
        ]
      ]);

    } catch (\Exception $e) {
      $this->logger->error('Lỗi test endpoint: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Lỗi khi kiểm tra hệ thống: ' . $e->getMessage(),
      ], 500);
    }
  }

} 