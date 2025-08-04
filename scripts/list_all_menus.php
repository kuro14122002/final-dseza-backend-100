<?php

/**
 * @file
 * Lists the machine names of all menus in the system.
 */

// Load all 'menu' entities.
$menus = \Drupal::entityTypeManager()->getStorage('menu')->loadMultiple();

// Get the machine names, which are the array keys.
$menu_machine_names = array_keys($menus);

echo "Found menus:\n";
print_r($menu_machine_names);