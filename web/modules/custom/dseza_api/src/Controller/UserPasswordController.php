<?php

namespace Drupal\dseza_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Controller để xử lý việc đổi mật khẩu user.
 */
class UserPasswordController extends ControllerBase {

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
   * The password hashing service.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $passwordHasher;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * UserPasswordController constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Password\PasswordInterface $password_hasher
   *   The password hashing service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    PasswordInterface $password_hasher,
    AccountInterface $current_user
  ) {
    $this->logger = $logger_factory->get('dseza_api');
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordHasher = $password_hasher;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('password'),
      $container->get('current_user')
    );
  }

  /**
   * Xử lý việc đổi mật khẩu user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function changePassword(Request $request) {
    // Log the incoming request
    $this->logger->info('Password change request received for user @uid', [
      '@uid' => $this->currentUser->id()
    ]);

    // Set CORS headers for API response
    $response_headers = [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'POST, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
    ];

    try {
      // Check if user is authenticated
      if ($this->currentUser->isAnonymous()) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Bạn phải đăng nhập để thực hiện thao tác này',
          'error' => 'UNAUTHORIZED'
        ], 401, $response_headers);
      }

      // Parse JSON input
      $json_data = json_decode($request->getContent(), TRUE);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Invalid JSON format',
          'error' => 'INVALID_JSON'
        ], 400, $response_headers);
      }

      // Validate required fields
      $required_fields = ['current_password', 'new_password', 'confirm_password'];
      $errors = [];

      foreach ($required_fields as $field) {
        if (empty($json_data[$field])) {
          $errors[] = "Missing required field: $field";
        }
      }

      if (!empty($errors)) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Validation errors',
          'errors' => $errors
        ], 400, $response_headers);
      }

      // Extract data
      $current_password = $json_data['current_password'];
      $new_password = $json_data['new_password'];
      $confirm_password = $json_data['confirm_password'];

      // Validate new password
      if (strlen($new_password) < 6) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự'
        ], 400, $response_headers);
      }

      // Validate password confirmation
      if ($new_password !== $confirm_password) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Mật khẩu mới và xác nhận không khớp'
        ], 400, $response_headers);
      }

      // Check if new password is different from current
      if ($current_password === $new_password) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Mật khẩu mới phải khác mật khẩu hiện tại'
        ], 400, $response_headers);
      }

      // Load current user entity
      $user_storage = $this->entityTypeManager->getStorage('user');
      $user = $user_storage->load($this->currentUser->id());
      
      if (!$user) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Không tìm thấy thông tin người dùng'
        ], 404, $response_headers);
      }

      // Verify current password
      if (!$this->passwordHasher->check($current_password, $user->getPassword())) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Mật khẩu hiện tại không chính xác'
        ], 400, $response_headers);
      }

      // Update password
      $user->setPassword($new_password);
      $user->save();

      $this->logger->info('Password changed successfully for user @uid (@email)', [
        '@uid' => $user->id(),
        '@email' => $user->getEmail()
      ]);

      // Return success response
      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Đổi mật khẩu thành công! Mật khẩu của bạn đã được cập nhật.',
        'user_id' => $user->id(),
        'timestamp' => time()
      ], 200, $response_headers);

    } catch (\Exception $e) {
      // Log the error
      $this->logger->error('Error during password change: @message', [
        '@message' => $e->getMessage(),
      ]);

      // Return error response
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Có lỗi xảy ra khi đổi mật khẩu. Vui lòng thử lại.',
        'error' => 'PASSWORD_CHANGE_ERROR'
      ], 500, $response_headers);
    }
  }

  /**
   * Handle OPTIONS requests for CORS.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Empty response with CORS headers.
   */
  public function handleOptions() {
    return new JsonResponse([], 200, [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'POST, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
    ]);
  }
} 