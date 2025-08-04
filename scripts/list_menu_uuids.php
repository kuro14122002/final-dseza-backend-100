<?php

/**
 * @file
 * Lists all menu link UUIDs and titles for a specific menu.
 */

// Define the menu name you want to inspect.
$menu_name = 'main1';

// Load all menu link content entities from the specified menu.
$menu_links = \Drupal::entityTypeManager()
  ->getStorage('menu_link_content')
  ->loadByProperties(['menu_name' => $menu_name]);

if (empty($menu_links)) {
  echo "No menu links found in the '{$menu_name}' menu.\n";
  return;
}

$output = [];
foreach ($menu_links as $link) {
  // Get the UUID and Title for each link.
  $output[$link->uuid()] = $link->getTitle();
}

// Print the result in a readable format.
print_r($output);