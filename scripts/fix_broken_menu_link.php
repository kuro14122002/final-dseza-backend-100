<?php
/**
 * @file
 * Script to fix broken menu link causing MenuLinkContent error
 * 
 * Usage: lando drush php:script scripts/fix_broken_menu_link.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

// The problematic UUID from the error message
$problematic_uuid = '85c19b88-0c66-47a3-b67b-dbf7f8c223d4';

echo "🔍 Looking for menu link with UUID: {$problematic_uuid}\n";

// Try to load the menu link by UUID
$menu_links = \Drupal::entityTypeManager()
  ->getStorage('menu_link_content')
  ->loadByProperties(['uuid' => $problematic_uuid]);

if (!empty($menu_links)) {
  $menu_link = reset($menu_links);
  echo "✅ Found menu link:\n";
  echo "  - ID: " . $menu_link->id() . "\n";
  echo "  - Title: " . $menu_link->getTitle() . "\n";
  echo "  - Menu: " . $menu_link->getMenuName() . "\n";
  echo "  - URL: " . $menu_link->getUrlObject()->toString() . "\n";
  
  // Check if the linked entity exists
  try {
    $url = $menu_link->getUrlObject();
    if ($url->isRouted()) {
      $route_name = $url->getRouteName();
      $route_parameters = $url->getRouteParameters();
      echo "  - Route: {$route_name}\n";
      echo "  - Parameters: " . json_encode($route_parameters) . "\n";
      
      // If it's an entity route, check if the entity exists
      if (isset($route_parameters['node'])) {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($route_parameters['node']);
        if (!$node) {
          echo "❌ Referenced node does not exist!\n";
          echo "🔧 Deleting broken menu link...\n";
          $menu_link->delete();
          echo "✅ Broken menu link deleted successfully!\n";
        } else {
          echo "✅ Referenced node exists and is accessible.\n";
        }
      }
    }
  } catch (Exception $e) {
    echo "❌ Error accessing linked entity: " . $e->getMessage() . "\n";
    echo "🔧 Deleting broken menu link...\n";
    $menu_link->delete();
    echo "✅ Broken menu link deleted successfully!\n";
  }
} else {
  echo "❌ Menu link with UUID {$problematic_uuid} not found in database.\n";
  
  // Let's check all menu links for potential issues
  echo "\n🔍 Checking all menu links for broken references...\n";
  
  $menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  $menu_links = $menu_link_storage->loadMultiple();
  
  $broken_count = 0;
  foreach ($menu_links as $menu_link) {
    try {
      // Try to get the URL - this will fail if the entity doesn't exist
      $url = $menu_link->getUrlObject();
      if ($url->isRouted()) {
        $route_parameters = $url->getRouteParameters();
        if (isset($route_parameters['node'])) {
          $node = \Drupal::entityTypeManager()->getStorage('node')->load($route_parameters['node']);
          if (!$node) {
            echo "❌ Found broken menu link: " . $menu_link->getTitle() . " (ID: " . $menu_link->id() . ")\n";
            echo "   UUID: " . $menu_link->uuid() . "\n";
            echo "   Menu: " . $menu_link->getMenuName() . "\n";
            echo "🔧 Deleting broken menu link...\n";
            $menu_link->delete();
            $broken_count++;
          }
        }
      }
    } catch (Exception $e) {
      echo "❌ Found broken menu link: " . $menu_link->getTitle() . " (ID: " . $menu_link->id() . ")\n";
      echo "   UUID: " . $menu_link->uuid() . "\n";
      echo "   Menu: " . $menu_link->getMenuName() . "\n";
      echo "   Error: " . $e->getMessage() . "\n";
      echo "🔧 Deleting broken menu link...\n";
      $menu_link->delete();
      $broken_count++;
    }
  }
  
  echo "\n✅ Fixed {$broken_count} broken menu links.\n";
}

// Clear menu cache
\Drupal::service('plugin.manager.menu.link')->rebuild();
echo "\n🔄 Menu cache cleared.\n";

echo "\n✅ Script completed successfully!\n"; 