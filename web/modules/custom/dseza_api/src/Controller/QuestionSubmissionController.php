<?php

namespace Drupal\dseza_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
   * QuestionSubmissionController constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->logger = $logger_factory->get('dseza_api');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('entity_type.manager')
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

      // Kiểm tra content type 'question' có tồn tại không
      $nodeTypeStorage = $this->entityTypeManager->getStorage('node_type');
      $questionType = $nodeTypeStorage->load('question');
      
      if (!$questionType) {
        $this->logger->error('Content type "question" không tồn tại');
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Hệ thống chưa được cấu hình đúng. Vui lòng liên hệ quản trị viên.',
        ], 500);
      }

      // Tạo node mới với content type 'question'
      $nodeData = [
        'type' => 'question',
        'title' => $data['tieuDe'],
        'status' => FALSE, // Unpublished để chờ duyệt
        'uid' => 1, // Gán cho admin user
        'langcode' => 'vi',
      ];

      $node = Node::create($nodeData);

      // Set các fields trực tiếp với try-catch
      try {
        $node->set('field_nguoi_gui', $data['hoTen']);
      } catch (\Exception $e) {
        $this->logger->warning('Cannot set field_nguoi_gui: @error', ['@error' => $e->getMessage()]);
      }
      
      try {
        $node->set('field_email', $data['email']);
      } catch (\Exception $e) {
        $this->logger->warning('Cannot set field_email: @error', ['@error' => $e->getMessage()]);
      }
      
      try {
        $node->set('field_noi_dung_cau_hoi', [
          'value' => $data['noiDung'],
          'format' => 'basic_html',
        ]);
      } catch (\Exception $e) {
        $this->logger->warning('Cannot set field_noi_dung_cau_hoi: @error', ['@error' => $e->getMessage()]);
        // Fallback: sử dụng body field
        $node->set('body', [
          'value' => $data['noiDung'],
          'format' => 'basic_html',
        ]);
      }

      // Set các trường tùy chọn nếu có
      if (!empty($data['dienThoai'])) {
        try {
          $node->set('field_so_dien_thoai', $data['dienThoai']);
        } catch (\Exception $e) {
          $this->logger->warning('Cannot set field_so_dien_thoai: @error', ['@error' => $e->getMessage()]);
        }
      }

      if (!empty($data['congTy'])) {
        try {
          $node->set('field_dia_chi', $data['congTy']);
        } catch (\Exception $e) {
          $this->logger->warning('Cannot set field_dia_chi: @error', ['@error' => $e->getMessage()]);
        }
      }

      // Lưu node
      $result = $node->save();

      if ($result === SAVED_NEW) {
        // Log thành công
        $this->logger->info('Câu hỏi mới đã được gửi từ @email với tiêu đề: @title', [
          '@email' => $data['email'],
          '@title' => $data['tieuDe'],
        ]);

        return new JsonResponse([
          'status' => 'success',
          'message' => 'Câu hỏi của bạn đã được gửi thành công và đang chờ được duyệt',
          'question_id' => $node->id(),
        ], 201);
      } else {
        throw new \Exception('Không thể lưu câu hỏi');
      }

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