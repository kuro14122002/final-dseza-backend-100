<?php
/**
 * @file
 * Test script to check if GraphQL menu queries work
 * 
 * Usage: lando drush php:script scripts/test_menu_graphql.php
 */

echo "🧪 Testing GraphQL menu queries...\n\n";

// Test if GraphQL module is enabled
$module_handler = \Drupal::moduleHandler();
if (!$module_handler->moduleExists('graphql')) {
  echo "❌ GraphQL module is not enabled!\n";
  exit(1);
}

echo "✅ GraphQL module is enabled.\n";

// Test menu access directly
echo "\n🔍 Testing menu access...\n";

// Test MAIN menu
try {
  $menu_storage = \Drupal::entityTypeManager()->getStorage('menu');
  $main_menu = $menu_storage->load('main');
  
  if ($main_menu) {
    echo "✅ MAIN menu exists: " . $main_menu->label() . "\n";
    
    // Load menu links
    $menu_link_tree = \Drupal::menuTree();
    $menu_name = 'main';
    $parameters = $menu_link_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $tree = $menu_link_tree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_link_tree->transform($tree, $manipulators);
    
    echo "✅ MAIN menu loaded successfully with " . count($tree) . " top-level items.\n";
  } else {
    echo "❌ MAIN menu not found!\n";
  }
} catch (Exception $e) {
  echo "❌ Error loading MAIN menu: " . $e->getMessage() . "\n";
}

// Test MAIN1 menu
try {
  $main1_menu = $menu_storage->load('main1');
  
  if ($main1_menu) {
    echo "✅ MAIN1 menu exists: " . $main1_menu->label() . "\n";
    
    // Load menu links
    $menu_name = 'main1';
    $parameters = $menu_link_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $tree = $menu_link_tree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_link_tree->transform($tree, $manipulators);
    
    echo "✅ MAIN1 menu loaded successfully with " . count($tree) . " top-level items.\n";
  } else {
    echo "❌ MAIN1 menu not found!\n";
  }
} catch (Exception $e) {
  echo "❌ Error loading MAIN1 menu: " . $e->getMessage() . "\n";
}

// List all available menus
echo "\n📋 Available menus:\n";
$menus = $menu_storage->loadMultiple();
foreach ($menus as $menu_id => $menu) {
  echo "  - {$menu_id}: " . $menu->label() . "\n";
}

// Check for broken menu links in database
echo "\n🔍 Checking for problematic menu links...\n";
$database = \Drupal::database();

$query = $database->select('menu_link_content_data', 'mlcd')
  ->fields('mlcd', ['id', 'uuid', 'title', 'menu_name', 'link__uri'])
  ->condition('mlcd.uuid', '85c19b88-0c66-47a3-b67b-dbf7f8c223d4');

$result = $query->execute()->fetchAll();

if ($result) {
  echo "❌ Found problematic menu link in database:\n";
  foreach ($result as $record) {
    echo "  - ID: {$record->id}, Title: {$record->title}, Menu: {$record->menu_name}, URI: {$record->link__uri}\n";
  }
} else {
  echo "✅ No problematic menu link found in database.\n";
}

echo "\n✅ Menu test completed!\n"; 