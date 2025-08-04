<?php
/**
 * @file
 * Force fix menu by directly removing problematic UUID from database
 * 
 * Usage: lando drush php:script scripts/force_fix_menu.php
 */

echo "⚠️ FORCE FIX: Removing problematic menu link UUID from database...\n\n";

$problematic_uuid = '85c19b88-0c66-47a3-b67b-dbf7f8c223d4';

// Get database connection
$database = \Drupal::database();

echo "🔍 Searching for problematic UUID in database tables...\n";

// Check menu_link_content table
$query = $database->select('menu_link_content', 'mlc')
  ->fields('mlc')
  ->condition('mlc.uuid', $problematic_uuid);
$result = $query->execute()->fetchAll();

if ($result) {
  echo "❌ Found problematic record in menu_link_content table\n";
  
  // Force delete from menu_link_content
  $delete_query = $database->delete('menu_link_content')
    ->condition('uuid', $problematic_uuid);
  $deleted = $delete_query->execute();
  echo "🗑️ Deleted {$deleted} records from menu_link_content\n";
} else {
  echo "✅ No records found in menu_link_content table\n";
}

// Check menu_link_content_data table  
$query = $database->select('menu_link_content_data', 'mlcd')
  ->fields('mlcd')
  ->condition('mlcd.uuid', $problematic_uuid);  
$result = $query->execute()->fetchAll();

if ($result) {
  echo "❌ Found problematic record in menu_link_content_data table\n";
  
  // Force delete from menu_link_content_data
  $delete_query = $database->delete('menu_link_content_data')
    ->condition('uuid', $problematic_uuid);
  $deleted = $delete_query->execute();
  echo "🗑️ Deleted {$deleted} records from menu_link_content_data\n";
} else {
  echo "✅ No records found in menu_link_content_data table\n";
}

// Check menu_link_content_field_data table
$query = $database->select('menu_link_content_field_data', 'mlcfd')
  ->fields('mlcfd')
  ->condition('mlcfd.uuid', $problematic_uuid);
$result = $query->execute()->fetchAll();

if ($result) {
  echo "❌ Found problematic record in menu_link_content_field_data table\n";
  
  // Force delete from menu_link_content_field_data
  $delete_query = $database->delete('menu_link_content_field_data')
    ->condition('uuid', $problematic_uuid);
  $deleted = $delete_query->execute();
  echo "🗑️ Deleted {$deleted} records from menu_link_content_field_data\n";
} else {
  echo "✅ No records found in menu_link_content_field_data table\n";
}

// Also check for any references in menu_tree table (if exists)
try {
  $query = $database->select('menu_tree', 'mt')
    ->fields('mt')
    ->condition('mt.id', '%' . $problematic_uuid . '%', 'LIKE');
  $result = $query->execute()->fetchAll();
  
  if ($result) {
    echo "❌ Found references in menu_tree table\n";
    foreach ($result as $record) {
      echo "  - Menu: {$record->menu_name}, ID: {$record->id}\n";
    }
    
    // Delete from menu_tree
    $delete_query = $database->delete('menu_tree')
      ->condition('id', '%' . $problematic_uuid . '%', 'LIKE');
    $deleted = $delete_query->execute();
    echo "🗑️ Deleted {$deleted} records from menu_tree\n";
  } else {
    echo "✅ No references found in menu_tree table\n";
  }
} catch (Exception $e) {
  echo "ℹ️ menu_tree table doesn't exist or inaccessible\n";
}

echo "\n🔄 Clearing all caches...\n";
drupal_flush_all_caches();
\Drupal::service('plugin.manager.menu.link')->rebuild();

echo "\n🧪 Testing menu loading after force fix...\n";

// Test MAIN menu
try {
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
} catch (Exception $e) {
  echo "❌ MAIN menu still has issues: " . $e->getMessage() . "\n";
}

// Test MAIN1 menu
try {
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
  echo "🔧 Will need to recreate MAIN1 menu completely...\n";
}

echo "\n✅ Force fix completed!\n"; 