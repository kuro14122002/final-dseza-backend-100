<?php

/**
 * @file
 * Hooks for auto_node_translate module.
 */

/**
 * Alter the information sent to the api before translation.
 *
 * @param string $text
 *   The text element to be translated.
 * @param array $info
 *   An array of information about the text being translated.
 *   Elements:
 *   - field: The field being translated.
 *   - from: The original language id.
 *   - to: The destination language id.
 */
function hook_auto_node_translate_translation_alter(&$text, array &$info) {
  $field = $info['field'];
  $fieldName = $field->getName();
  if ($fieldName == 'title') {
    $text .= (' - Auto Translation');
  }
}
