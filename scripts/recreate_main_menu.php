<?php
/**
 * @file
 * Recreate MAIN menu completely to eliminate all UUID issues
 * 
 * Usage: lando drush php:script scripts/recreate_main_menu.php
 */

use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;

echo "🔧 Recreating MAIN menu completely\n";
echo "==================================\n\n";

$database = \Drupal::database();

// Step 1: Force delete MAIN menu and clean database
echo "🗑️ Step 1: Force deleting MAIN menu...\n";

// Delete menu config
try {
  $deleted = $database->delete('config')
    ->condition('name', 'system.menu.main')
    ->execute();
  echo "✅ Deleted {$deleted} MAIN menu config records\n";
} catch (Exception $e) {
  echo "⚠️ Could not delete config: " . $e->getMessage() . "\n";
}

// Delete all menu links by bundle (nuclear option)
try {
  $deleted = $database->delete('menu_link_content')
    ->condition('bundle', 'menu_link_content')
    ->execute();
  echo "✅ Deleted {$deleted} menu link content records\n";
} catch (Exception $e) {
  echo "⚠️ Menu link deletion failed: " . $e->getMessage() . "\n";
}

// Clean cache and menu tree
try {
  $database->query("DELETE FROM cache_menu")->execute();
  echo "✅ Cleared cache_menu\n";
} catch (Exception $e) {
  echo "⚠️ Cache clear failed\n";
}

try {
  $database->query("DELETE FROM menu_tree WHERE menu_name = 'main'")->execute();
  echo "✅ Cleared menu_tree for MAIN\n";
} catch (Exception $e) {
  echo "⚠️ Menu tree clear failed\n";
}

// Step 2: Clear caches
echo "\n🔄 Step 2: Clearing all caches...\n";
drupal_flush_all_caches();
\Drupal::service('plugin.manager.menu.link')->rebuild();
\Drupal::entityTypeManager()->getStorage('menu')->resetCache(['main']);
echo "✅ All caches cleared\n";

// Step 3: Create fresh MAIN menu
echo "\n🔨 Step 3: Creating fresh MAIN menu...\n";

// Create via config
$config_data = [
  'uuid' => \Drupal::service('uuid')->generate(),
  'langcode' => 'vi',
  'status' => TRUE,
  'dependencies' => [],
  'id' => 'main',
  'label' => 'Main navigation',
  'description' => 'Vietnamese navigation menu - recreated',
  'locked' => FALSE,
];

$config = \Drupal::configFactory()->getEditable('system.menu.main');
$config->setData($config_data)->save();
echo "✅ Created MAIN menu via config\n";

// Step 4: Add Vietnamese menu items
echo "\n📋 Step 4: Adding Vietnamese menu items...\n";

$vn_menu_items = [
  ['title' => 'Giới thiệu', 'url' => '/vi/', 'weight' => 0],
  ['title' => 'Tin tức', 'url' => '/vi/', 'weight' => 1],
  ['title' => 'Doanh nghiệp', 'url' => '/vi/', 'weight' => 2],
  ['title' => 'Văn bản', 'url' => '/vi/', 'weight' => 3],
  ['title' => 'Dịch vụ', 'url' => '/vi/', 'weight' => 4],
  ['title' => 'Hỏi đáp', 'url' => '/vi/', 'weight' => 5],
  ['title' => 'Liên hệ', 'url' => '/vi/', 'weight' => 6],
];

$created = 0;
foreach ($vn_menu_items as $item) {
  try {
    $menu_link = MenuLinkContent::create([
      'title' => $item['title'],
      'link' => ['uri' => 'internal:' . $item['url']],
      'menu_name' => 'main',
      'weight' => $item['weight'],
      'langcode' => 'vi',
    ]);
    $menu_link->save();
    $created++;
    echo "  ✅ {$item['title']}\n";
  } catch (Exception $e) {
    echo "  ❌ Failed: {$item['title']} - " . $e->getMessage() . "\n";
  }
}

echo "✅ Created {$created} Vietnamese menu items\n";

// Step 5: Final test
echo "\n🧪 Step 5: Testing recreated MAIN menu...\n";
drupal_flush_all_caches();

try {
  $menu_tree = \Drupal::menuTree();
  $parameters = $menu_tree->getCurrentRouteMenuTreeParameters('main');
  $tree = $menu_tree->load('main', $parameters);
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:checkAccess'],
    ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
  ];
  $tree = $menu_tree->transform($tree, $manipulators);
  
  echo "🎉 SUCCESS! MAIN menu works with " . count($tree) . " items:\n";
  foreach ($tree as $item) {
    echo "  - " . $item->link->getTitle() . "\n";
  }
  
} catch (Exception $e) {
  echo "❌ Test failed: " . $e->getMessage() . "\n";
}

echo "\n✅ MAIN menu recreation completed!\n"; 