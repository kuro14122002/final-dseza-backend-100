<?php

namespace Drupal\dseza_api_menu\Plugin\GraphQL\DataProducer;

use Drupal\Core\Url;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * @DataProducer(
 *   id = "menu_link_url",
 *   name = @Translation("Menu link URL"),
 *   description = @Translation("Returns the menu link URL."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Menu link URL")
 *   ),
 *   consumes = {
 *     "link" = @ContextDefinition("any",
 *       label = @Translation("Menu link")
 *     )
 *   }
 * )
 */
class MenuLinkUrlProducer extends DataProducerPluginBase {

  /**
   * Resolver.
   *
   * @param mixed $link
   *   The menu link.
   *
   * @return string
   *   The menu link URL.
   */
  public function resolve($link) {
    try {
      $url = $link->getUrlObject();
      if ($url->isExternal()) {
        return $url->toString();
      }
      return $url->toString();
    }
    catch (\Exception $e) {
      return '';
    }
  }

} 