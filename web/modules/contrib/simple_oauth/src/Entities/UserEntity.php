<?php

namespace Drupal\simple_oauth\Entities;

use Drupal\user\UserInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * The user entity.
 */
class UserEntity implements UserEntityInterface {

  /**
   * The Drupal user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $drupalUser;

  /**
   * Constructs a UserEntity object.
   *
   * @param \Drupal\user\UserInterface $drupal_user
   *   The Drupal user entity.
   */
  public function __construct(UserInterface $drupal_user) {
    $this->drupalUser = $drupal_user;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentifier(): string {
    return (string) $this->drupalUser->id();
  }

  /**
   * Get the Drupal user entity.
   *
   * @return \Drupal\user\UserInterface
   *   The Drupal user entity.
   */
  public function getDrupalUser(): UserInterface {
    return $this->drupalUser;
  }

}
