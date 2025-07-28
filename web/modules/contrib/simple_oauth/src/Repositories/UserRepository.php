<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\simple_oauth\Entities\UserEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;

/**
 * The user repository.
 */
class UserRepository implements UserRepositoryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The password hashing service.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected PasswordInterface $passwordChecker;

  /**
   * Constructs a UserRepository object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PasswordInterface $password_checker) {
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordChecker = $password_checker;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserEntityByUserCredentials(
    $username,
    $password,
    $grantType,
    ClientEntityInterface $clientEntity
  ): ?UserEntityInterface {
    // Load user by username or email
    $user_storage = $this->entityTypeManager->getStorage('user');
    
    // Try to load by username first
    $users = $user_storage->loadByProperties(['name' => $username]);
    
    // If not found by username, try by email
    if (empty($users)) {
      $users = $user_storage->loadByProperties(['mail' => $username]);
    }
    
    if (empty($users)) {
      return NULL;
    }
    
    $user = reset($users);
    
    // Check if user is active
    if (!$user->isActive()) {
      return NULL;
    }
    
    // Verify password
    if (!$this->passwordChecker->check($password, $user->getPassword())) {
      return NULL;
    }
    
    return new UserEntity($user);
  }

} 