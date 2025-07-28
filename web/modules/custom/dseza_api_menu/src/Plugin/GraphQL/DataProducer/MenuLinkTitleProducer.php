<?php

namespace Drupal\dseza_api_menu\Plugin\GraphQL\DataProducer;

use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * @DataProducer(
 *   id = "menu_link_title",
 *   name = @Translation("Menu link title"),
 *   description = @Translation("Returns the menu link title."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Menu link title")
 *   ),
 *   consumes = {
 *     "link" = @ContextDefinition("any",
 *       label = @Translation("Menu link")
 *     )
 *   }
 * )
 */
class MenuLinkTitleProducer extends DataProducerPluginBase {

  /**
   * Resolver.
   *
   * @param mixed $link
   *   The menu link.
   *
   * @return string
   *   The menu link title.
   */
  public function resolve($link) {
    return $link->getTitle();
  }

} 