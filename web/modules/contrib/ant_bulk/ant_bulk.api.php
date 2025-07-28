<?php

/**
 * @file
 * Hooks for ant_bulk module.
 */

/**
 * Alter the nodes to be translated.
 *
 * @param array $nodes
 *   An array of Nodes.
 */
function ant_bulk_translation_items_alter(array &$nodes) {
  foreach ($nodes as $key => $node) {
    // Remove nodes from translation.
    if ($node->field_not_to_translate->getString()) {
      unset($nodes[$key]);
    }
  }
}
