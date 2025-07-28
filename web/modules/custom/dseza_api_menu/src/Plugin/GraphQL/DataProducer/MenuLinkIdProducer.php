<?php

namespace Drupal\dseza_api_menu\Plugin\GraphQL\DataProducer;

use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * @DataProducer(
 *   id = "menu_link_id",
 *   name = @Translation("Menu link ID"),
 *   description = @Translation("Returns the menu link ID."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Menu link ID")
 *   ),
 *   consumes = {
 *     "link" = @ContextDefinition("any",
 *       label = @Translation("Menu link")
 *     )
 *   }
 * )
 */
class MenuLinkIdProducer extends DataProducerPluginBase {

  /**
   * Resolver.
   *
   * @param mixed $link
   *   The menu link.
   *
   * @return string
   *   The menu link ID.
   */
  public function resolve($link) {
    return $link->getPluginId();
  }

} 