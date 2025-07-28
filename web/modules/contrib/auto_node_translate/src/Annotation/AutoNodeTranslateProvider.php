<?php

declare(strict_types=1);

namespace Drupal\auto_node_translate\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines auto_node_translate_provider annotation object.
 *
 * @Annotation
 */
final class AutoNodeTranslateProvider extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public string $title;

  /**
   * The description of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public string $description;

}
