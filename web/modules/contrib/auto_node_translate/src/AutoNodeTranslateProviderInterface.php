<?php

declare(strict_types=1);

namespace Drupal\auto_node_translate;

/**
 * Interface for auto_node_translate_provider plugins.
 */
interface AutoNodeTranslateProviderInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Translates Text.
   *
   * @param string $text
   *   - The text to translate.
   * @param string $from
   *   - Langcode of original language.
   * @param string $to
   *   - Langcode of destination language.
   *
   * @return string
   *   - the translated string
   */
  public function translate($text, $from, $to): string;

}
