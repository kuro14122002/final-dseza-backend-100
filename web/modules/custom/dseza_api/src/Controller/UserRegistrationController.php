<?php

namespace Drupal\dseza_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Password\PasswordInterface;

/**
 * Controller để xử lý việc đăng ký user mới.
 */
class UserRegistrationController extends ControllerBase {

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
   * UserRegistrationController constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Password\PasswordInterface $password_hasher
   *   The password hashing service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    PasswordInterface $password_hasher
  ) {
    $this->logger = $logger_factory->get('dseza_api');
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordHasher = $password_hasher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('password')
    );
  }

  /**
   * Xử lý việc đăng ký user mới.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function handleRegistration(Request $request) {
    // Log the incoming request
    $this->logger->info('User registration request received');

    // Set CORS headers for API response
    $response_headers = [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'POST, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
    ];

    try {
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
      $required_fields = ['name', 'email', 'password', 'password_confirm'];
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
      $name = trim($json_data['name']);
      $email = trim($json_data['email']);
      $password = $json_data['password'];
      $password_confirm = $json_data['password_confirm'];

      // Validate name
      if (strlen($name) < 2) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Tên người dùng phải có ít nhất 2 ký tự'
        ], 400, $response_headers);
      }

      // Validate email format
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Email không hợp lệ'
        ], 400, $response_headers);
      }

      // Validate password
      if (strlen($password) < 6) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Mật khẩu phải có ít nhất 6 ký tự'
        ], 400, $response_headers);
      }

      // Validate password confirmation
      if ($password !== $password_confirm) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Mật khẩu xác nhận không khớp'
        ], 400, $response_headers);
      }

      // Check if user with this email already exists
      $user_storage = $this->entityTypeManager->getStorage('user');
      $existing_users = $user_storage->loadByProperties(['mail' => $email]);
      
      if (!empty($existing_users)) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Email này đã được sử dụng'
        ], 409, $response_headers);
      }

      // Create new user
      $user = $user_storage->create([
        'name' => $name,
        'mail' => $email,
        'pass' => $password,
        'status' => 1, // Active user
        'roles' => ['authenticated'], // Default authenticated role
      ]);

      // Save the user
      $user->save();

      $this->logger->info('User registered successfully: @email', ['@email' => $email]);

      // Return success response
      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Đăng ký thành công! Tài khoản của bạn đã được tạo.',
        'user_id' => $user->id(),
        'user_name' => $user->getAccountName(),
        'user_role' => 'authenticated',
        // Note: For security, we don't auto-login here
        // User will need to login separately
      ], 201, $response_headers);

    } catch (\Exception $e) {
      // Log the error
      $this->logger->error('Error during user registration: @message', [
        '@message' => $e->getMessage(),
      ]);

      // Return error response
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Có lỗi xảy ra khi đăng ký. Vui lòng thử lại.',
        'error' => 'REGISTRATION_ERROR'
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