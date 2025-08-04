<?php
/**
 * @file
 * Fix the new problematic UUID in MAIN menu
 * 
 * Usage: lando drush php:script scripts/fix_main_uuid.php
 */

echo "🔧 Fixing MAIN menu UUID issue\n";
echo "==============================\n\n";

$problematic_uuid = 'ad5edeb0-7276-4d5c-bf71-2820b8c569fd';
$database = \Drupal::database();

echo "🎯 Target UUID: {$problematic_uuid}\n\n";

// Quick scan and cleanup
$tables_to_clean = ['cache_menu', 'menu_tree'];

foreach ($tables_to_clean as $table) {
  echo "🧹 Cleaning {$table}...\n";
  
  try {
    if ($table === 'cache_menu') {
      $deleted = $database->query("DELETE FROM `{$table}` WHERE CAST(`data` AS CHAR) LIKE '%{$problematic_uuid}%'")->execute();
      echo "  ✅ Deleted {$deleted} cache records\n";
    } elseif ($table === 'menu_tree') {
      $deleted = $database->query("DELETE FROM `{$table}` WHERE `id` LIKE '%{$problematic_uuid}%'")->execute();
      echo "  ✅ Deleted {$deleted} menu_tree records\n";
    }
  } catch (Exception $e) {
    echo "  ⚠️ Failed to clean {$table}: " . $e->getMessage() . "\n";
  }
}

// Clear caches
echo "\n🔄 Clearing caches...\n";
drupal_flush_all_caches();
\Drupal::service('plugin.manager.menu.link')->rebuild();
echo "✅ Caches cleared\n";

// Test MAIN menu
echo "\n🧪 Testing MAIN menu...\n";
try {
  $menu_tree = \Drupal::menuTree();
  $parameters = $menu_tree->getCurrentRouteMenuTreeParameters('main');
  $tree = $menu_tree->load('main', $parameters);
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:checkAccess'],
    ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
  ];
  $tree = $menu_tree->transform($tree, $manipulators);
  
  echo "✅ MAIN menu now works with " . count($tree) . " items!\n";
  
  foreach ($tree as $item) {
    echo "  - " . $item->link->getTitle() . "\n";
  }
  
} catch (Exception $e) {
  echo "❌ MAIN menu still failing: " . $e->getMessage() . "\n";
}

echo "\n✅ MAIN menu fix completed!\n"; 