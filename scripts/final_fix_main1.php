<?php
/**
 * @file
 * Final fix for MAIN1 menu - force delete from all tables and recreate
 * 
 * Usage: lando drush php:script scripts/final_fix_main1.php
 */

use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;

echo "🚨 FINAL FIX: Force recreate MAIN1 menu\n";
echo "=====================================\n\n";

$database = \Drupal::database();

// Step 1: Check database structure and delete records
echo "🔍 Step 1: Checking and cleaning database...\n";

// Show table structure first
echo "📋 Database table structures:\n";

try {
  $result = $database->query("SHOW TABLES LIKE 'menu%'")->fetchAll();
  foreach ($result as $table) {
    $table_name = array_values((array)$table)[0];
    echo "  - Table: {$table_name}\n";
  }
} catch (Exception $e) {
  echo "⚠️ Could not list tables\n";
}

// Force delete from system.menu table first
echo "\n🗑️ Deleting menu from system.menu...\n";
try {
  $deleted = $database->delete('menu')
    ->condition('id', 'main1')
    ->execute();
  echo "✅ Deleted {$deleted} records from menu table\n";
} catch (Exception $e) {
  echo "⚠️ Could not delete from menu table: " . $e->getMessage() . "\n";
}

// Delete from config tables
echo "\n🗑️ Deleting from config tables...\n";
try {
  $deleted = $database->delete('config')
    ->condition('name', 'system.menu.main1')
    ->execute();
  echo "✅ Deleted {$deleted} config records\n";
} catch (Exception $e) {
  echo "⚠️ Could not delete config: " . $e->getMessage() . "\n";
}

// Delete menu links - try different approaches
echo "\n🗑️ Cleaning menu link tables...\n";

// First approach: by bundle
try {
  $deleted = $database->delete('menu_link_content')
    ->condition('bundle', 'menu_link_content')
    ->execute();
  echo "✅ Deleted {$deleted} records from menu_link_content (by bundle)\n";
} catch (Exception $e) {
  echo "⚠️ Delete by bundle failed: " . $e->getMessage() . "\n";
}

// Delete all menu_link_content records (nuclear option)
try {
  $result = $database->query("DELETE FROM menu_link_content WHERE id IN (SELECT id FROM menu_link_content_data WHERE menu_name = 'main1')")->execute();
  echo "✅ Force deleted records with subquery\n";
} catch (Exception $e) {
  echo "⚠️ Subquery deletion failed: " . $e->getMessage() . "\n";
}

// Clear all caches before recreating
echo "\n🔄 Step 2: Clearing all caches...\n";
drupal_flush_all_caches();
\Drupal::service('plugin.manager.menu.link')->rebuild();

// Clear entity cache specifically
\Drupal::entityTypeManager()->getStorage('menu')->resetCache(['main1']);
\Drupal::entityTypeManager()->getStorage('menu_link_content')->resetCache();

echo "✅ All caches cleared\n";

// Step 3: Create new MAIN1 menu
echo "\n🔨 Step 3: Creating new MAIN1 menu...\n";

// Check if menu still exists
$existing_menu = Menu::load('main1');
if ($existing_menu) {
  echo "⚠️ Menu main1 still exists, forcing config removal...\n";
  
  // Try to remove config directly
  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('system.menu.main1');
  if (!$config->isNew()) {
    $config->delete();
    echo "✅ Deleted menu config\n";
  }
  
  // Clear cache again
  drupal_flush_all_caches();
}

// Now create the menu
try {
  $menu = Menu::create([
    'id' => 'main1',
    'label' => 'Main1',
    'description' => 'English navigation menu - recreated',
    'langcode' => 'en',
  ]);
  $menu->save();
  echo "✅ Created new MAIN1 menu successfully!\n";
} catch (Exception $e) {
  echo "❌ Failed to create menu: " . $e->getMessage() . "\n";
  echo "🔧 Trying alternative approach...\n";
  
  // Alternative: create via config
  $config_data = [
    'uuid' => \Drupal::service('uuid')->generate(),
    'langcode' => 'en',
    'status' => TRUE,
    'dependencies' => [],
    'id' => 'main1',
    'label' => 'Main1',
    'description' => 'English navigation menu - recreated via config',
    'locked' => FALSE,
  ];
  
  $config = \Drupal::configFactory()->getEditable('system.menu.main1');
  $config->setData($config_data)->save();
  echo "✅ Created menu via config\n";
}

// Step 4: Add basic menu items
echo "\n📋 Step 4: Adding basic menu items...\n";

$menu_items = [
  ['title' => 'Introduction', 'url' => '/en/', 'weight' => 0],
  ['title' => 'News', 'url' => '/en/', 'weight' => 1],
  ['title' => 'Business', 'url' => '/en/', 'weight' => 2],
  ['title' => 'Documents', 'url' => '/en/', 'weight' => 3],
  ['title' => 'Services', 'url' => '/en/', 'weight' => 4],
  ['title' => 'Contact', 'url' => '/en/', 'weight' => 5],
];

$created = 0;
foreach ($menu_items as $item) {
  try {
    $menu_link = MenuLinkContent::create([
      'title' => $item['title'],
      'link' => ['uri' => 'internal:' . $item['url']],
      'menu_name' => 'main1',
      'weight' => $item['weight'],
      'langcode' => 'en',
    ]);
    $menu_link->save();
    $created++;
    echo "  ✅ {$item['title']}\n";
  } catch (Exception $e) {
    echo "  ❌ Failed: {$item['title']} - " . $e->getMessage() . "\n";
  }
}

echo "✅ Created {$created} menu items\n";

// Step 5: Final test
echo "\n🧪 Step 5: Testing MAIN1 menu...\n";
drupal_flush_all_caches();

try {
  $menu_tree = \Drupal::menuTree();
  $parameters = $menu_tree->getCurrentRouteMenuTreeParameters('main1');
  $tree = $menu_tree->load('main1', $parameters);
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:checkAccess'],
    ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
  ];
  $tree = $menu_tree->transform($tree, $manipulators);
  
  echo "✅ SUCCESS! MAIN1 menu works with " . count($tree) . " items:\n";
  foreach ($tree as $item) {
    echo "  - " . $item->link->getTitle() . "\n";
  }
  
  echo "\n🎉 MAIN1 menu is now ready for GraphQL queries!\n";
  echo "Test with: query { menuByName(name: MAIN1) { links { link { label } } } }\n";
  
} catch (Exception $e) {
  echo "❌ Test failed: " . $e->getMessage() . "\n";
}

echo "\n✅ Final fix completed!\n"; 