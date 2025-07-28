<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer\Link;

use Drupal\Core\Link;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Resolves the URL of a link.
 *
 * @DataProducer(
 *   id = "link_url",
 *   name = @Translation("Link URL"),
 *   description = @Translation("The URL of the link."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("URL")
 *   ),
 *   consumes = {
 *     "link" = @ContextDefinition("any",
 *       label = @Translation("The link.")
 *     ),
 *   }
 * )
 */
class Url extends DataProducerPluginBase {

  /**
   * Resolves the title of a link.
   *
   * @param Link $link
   *   The link.
   *
   * @return \Drupal\Core\Url|null
   *   The URL.
   */
  public function resolve(Link $link) {
    return $link->getUrl();
  }

}
