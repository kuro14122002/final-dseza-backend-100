<?php
/**
 * @file
 * Script to fix broken MAIN1 menu by finding and removing problematic menu link
 * 
 * Usage: lando drush php:script scripts/fix_main1_menu.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

echo "🔧 Fixing MAIN1 menu...\n\n";

// The problematic UUID from the error message
$problematic_uuid = '85c19b88-0c66-47a3-b67b-dbf7f8c223d4';

echo "🔍 Looking for menu links in MAIN1 menu...\n";

// Load all menu links from MAIN1 menu
$menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$main1_menu_links = $menu_link_storage->loadByProperties(['menu_name' => 'main1']);

echo "📋 Found " . count($main1_menu_links) . " menu links in MAIN1 menu.\n\n";

$fixed_count = 0;
$checked_count = 0;

foreach ($main1_menu_links as $menu_link) {
  $checked_count++;
  echo "🔍 Checking menu link {$checked_count}: " . $menu_link->getTitle() . "\n";
  echo "  - ID: " . $menu_link->id() . "\n";
  echo "  - UUID: " . $menu_link->uuid() . "\n";
  
  // Check if this is the problematic UUID
  if ($menu_link->uuid() === $problematic_uuid) {
    echo "❌ Found the problematic menu link!\n";
    echo "🔧 Deleting menu link: " . $menu_link->getTitle() . "\n";
    $menu_link->delete();
    $fixed_count++;
    echo "✅ Deleted successfully!\n\n";
    continue;
  }
  
  // Also check if the linked entity exists
  try {
    $url = $menu_link->getUrlObject();
    if ($url->isRouted()) {
      $route_parameters = $url->getRouteParameters();
      echo "  - Route: " . $url->getRouteName() . "\n";
      
      // Check if it's linking to a node
      if (isset($route_parameters['node'])) {
        $node_id = $route_parameters['node'];
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
        if (!$node) {
          echo "❌ Referenced node {$node_id} does not exist!\n";
          echo "🔧 Deleting broken menu link: " . $menu_link->getTitle() . "\n";
          $menu_link->delete();
          $fixed_count++;
          echo "✅ Deleted successfully!\n\n";
          continue;
        } else {
          echo "✅ Referenced node exists.\n";
        }
      }
    }
    echo "✅ Menu link is valid.\n\n";
  } catch (Exception $e) {
    echo "❌ Error with menu link: " . $e->getMessage() . "\n";
    echo "🔧 Deleting problematic menu link: " . $menu_link->getTitle() . "\n";
    $menu_link->delete();
    $fixed_count++;
    echo "✅ Deleted successfully!\n\n";
  }
}

echo "📊 Summary:\n";
echo "  - Checked: {$checked_count} menu links\n";
echo "  - Fixed: {$fixed_count} problematic menu links\n\n";

// Clear menu cache
echo "🔄 Clearing menu cache...\n";
\Drupal::service('plugin.manager.menu.link')->rebuild();
drupal_flush_all_caches();

echo "✅ Cache cleared!\n";

// Test MAIN1 menu again
echo "\n🧪 Testing MAIN1 menu after fix...\n";
try {
  $menu_link_tree = \Drupal::menuTree();
  $menu_name = 'main1';
  $parameters = $menu_link_tree->getCurrentRouteMenuTreeParameters($menu_name);
  $tree = $menu_link_tree->load($menu_name, $parameters);
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:checkAccess'],
    ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
  ];
  $tree = $menu_link_tree->transform($tree, $manipulators);
  
  echo "✅ MAIN1 menu loaded successfully with " . count($tree) . " top-level items.\n";
} catch (Exception $e) {
  echo "❌ MAIN1 menu still has issues: " . $e->getMessage() . "\n";
}

echo "\n✅ Fix script completed!\n"; 