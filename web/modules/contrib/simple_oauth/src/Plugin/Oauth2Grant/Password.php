<?php

namespace Drupal\simple_oauth\Plugin\Oauth2Grant;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantBase;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Grant\PasswordGrant;

/**
 * The password grant plugin.
 *
 * @Oauth2Grant(
 *   id = "password",
 *   label = @Translation("Password")
 * )
 */
class Password extends Oauth2GrantBase {

  /**
   * {@inheritdoc}
   */
  public function getGrantType(ConsumerInterface $client): GrantTypeInterface {
    $grant = new PasswordGrant(
      $this->getUserRepository(),
      $this->getRefreshTokenRepository()
    );
    
    // Set refresh token TTL to the same as access token.
    $refresh_token_ttl = new \DateInterval(sprintf('PT%dS', $client->get('refresh_token_expiration')->value));
    $grant->setRefreshTokenTTL($refresh_token_ttl);
    
    return $grant;
  }

  /**
   * Get the user repository.
   *
   * @return \League\OAuth2\Server\Repositories\UserRepositoryInterface
   *   The user repository.
   */
  protected function getUserRepository() {
    return \Drupal::service('simple_oauth.repositories.user');
  }

  /**
   * Get the refresh token repository.
   *
   * @return \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface
   *   The refresh token repository.
   */
  protected function getRefreshTokenRepository() {
    return \Drupal::service('simple_oauth.repositories.refresh_token');
  }

} 