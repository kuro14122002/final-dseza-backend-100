<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer\Link;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Link;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Resolves the title of a link.
 *
 * @DataProducer(
 *   id = "link_title",
 *   name = @Translation("Link Title"),
 *   description = @Translation("The title of the link."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Title")
 *   ),
 *   consumes = {
 *     "link" = @ContextDefinition("any",
 *       label = @Translation("The link.")
 *     ),
 *   }
 * )
 */
class Title extends DataProducerPluginBase {

  /**
   * Resolves the title of a link.
   *
   * @param Link $link
   *   The link.
   *
   * @return string|null
   *   The title of the link.
   */
  public function resolve(Link $link) {
    // Handle all possible return values for the text.
    $text = $link->getText();
    if (is_string($text)) {
      return $text;
    }
    elseif ($text instanceof MarkupInterface) {
      return (string) $text;
    }
    elseif (!empty($text['#markup'])) {
      return $text['#markup'];
    }

    return NULL;
  }

}
