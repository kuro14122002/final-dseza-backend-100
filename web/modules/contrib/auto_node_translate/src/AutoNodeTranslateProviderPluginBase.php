<?php

declare(strict_types=1);

namespace Drupal\auto_node_translate;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for auto_node_translate_provider plugins.
 */
abstract class AutoNodeTranslateProviderPluginBase extends PluginBase implements AutoNodeTranslateProviderInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

}
