<?php

namespace Drupal\auto_node_translate\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access check for entity translation overview.
 */
class AutoNodeTranslateAccessCheck implements AccessInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a AutoNodeTranslateAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks access to the translation overview for the entity and bundle.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account, $entity_type_id = 'node') {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
    if ($entity && $entity->isTranslatable()) {
      // Get entity base info.
      $bundle = $entity->bundle();

      // Get entity access callback.
      $definition = $this->entityTypeManager->getDefinition($entity_type_id);
      $translation = $definition->get('translation');
      $access_callback = $translation['content_translation']['access_callback'];
      $access = call_user_func($access_callback, $entity);
      if ($access->isAllowed()) {
        return $access;
      }

      // Check per entity permission.
      $permission = "auto translate {$entity_type_id}";
      if ($definition->getPermissionGranularity() == 'bundle') {
        $permission = "auto translate {$bundle} {$entity_type_id}";
      }
      return AccessResult::allowedIfHasPermission($account, $permission)->inheritCacheability($access);
    }

    // No opinion.
    return AccessResult::neutral();
  }

}
