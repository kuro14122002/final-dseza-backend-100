<?php
/**
 * @file
 * Simple script to completely delete and recreate MAIN1 menu
 * 
 * Usage: lando drush php:script scripts/simple_recreate_main1.php
 */

use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;

echo "🔧 Simple MAIN1 Menu Recreation\n";
echo "================================\n\n";

// Step 1: Force delete menu MAIN1 entirely
echo "🗑️ Step 1: Deleting MAIN1 menu completely...\n";

// First, try to delete all menu links using entity API
$menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');

try {
  $main1_links = $menu_link_storage->loadByProperties(['menu_name' => 'main1']);
  $deleted_count = 0;
  
  foreach ($main1_links as $link) {
    try {
      $link->delete();
      $deleted_count++;
    } catch (Exception $e) {
      // Ignore individual delete errors, we'll clean up manually
      echo "  ⚠️ Could not delete link: " . $link->getTitle() . "\n";
    }
  }
  
  echo "✅ Deleted {$deleted_count} menu links via entity API\n";
} catch (Exception $e) {
  echo "⚠️ Entity API deletion failed: " . $e->getMessage() . "\n";
}

// Force delete menu itself
$menu = Menu::load('main1');
if ($menu) {
  try {
    $menu->delete();
    echo "✅ Deleted MAIN1 menu entity\n";
  } catch (Exception $e) {
    echo "⚠️ Could not delete menu entity: " . $e->getMessage() . "\n";
  }
}

// Step 2: Manual database cleanup
echo "\n🧹 Step 2: Manual database cleanup...\n";

$database = \Drupal::database();

// Clean up menu_link_content table
try {
  $deleted = $database->delete('menu_link_content')
    ->condition('menu_name', 'main1')
    ->execute();
  echo "✅ Cleaned menu_link_content: {$deleted} records\n";
} catch (Exception $e) {
  echo "⚠️ menu_link_content cleanup failed: " . $e->getMessage() . "\n";
}

// Clean up menu_link_content_data table (if exists)
try {
  $deleted = $database->delete('menu_link_content_data')
    ->condition('menu_name', 'main1')
    ->execute();
  echo "✅ Cleaned menu_link_content_data: {$deleted} records\n";
} catch (Exception $e) {
  echo "ℹ️ menu_link_content_data table may not exist or accessible\n";
}

// Clean up menu_link_content_field_data table (if exists)
try {
  $deleted = $database->delete('menu_link_content_field_data')
    ->condition('menu_name', 'main1')
    ->execute();
  echo "✅ Cleaned menu_link_content_field_data: {$deleted} records\n";
} catch (Exception $e) {
  echo "ℹ️ menu_link_content_field_data table may not exist or accessible\n";
}

// Step 3: Clear all caches
echo "\n🔄 Step 3: Clearing caches...\n";
drupal_flush_all_caches();
\Drupal::service('plugin.manager.menu.link')->rebuild();
echo "✅ All caches cleared\n";

// Step 4: Create fresh MAIN1 menu
echo "\n🔨 Step 4: Creating fresh MAIN1 menu...\n";

$menu = Menu::create([
  'id' => 'main1',
  'label' => 'Main1',
  'description' => 'English navigation menu',
  'langcode' => 'en',
]);
$menu->save();
echo "✅ Created new MAIN1 menu\n";

// Step 5: Add basic menu structure
echo "\n📋 Step 5: Adding basic menu items...\n";

$basic_menu_items = [
  [
    'title' => 'Introduction',
    'weight' => 0,
    'url' => '/en/gioi-thieu',
  ],
  [
    'title' => 'News',
    'weight' => 1,
    'url' => '/en/tin-tuc',
  ],
  [
    'title' => 'Business',
    'weight' => 2,
    'url' => '/en/doanh-nghiep',
  ],
  [
    'title' => 'Documents',
    'weight' => 3,
    'url' => '/en/van-ban',
  ],
  [
    'title' => 'Services',
    'weight' => 4,
    'url' => '/en/dich-vu',
  ],
  [
    'title' => 'Q&A',
    'weight' => 5,
    'url' => '/en/hoi-dap',
  ],
  [
    'title' => 'Contact',
    'weight' => 6,
    'url' => '/en/lien-he',
  ],
];

$created_count = 0;
foreach ($basic_menu_items as $item) {
  try {
    $menu_link = MenuLinkContent::create([
      'title' => $item['title'],
      'link' => [
        'uri' => 'internal:' . $item['url'],
        'options' => [],
      ],
      'menu_name' => 'main1',
      'weight' => $item['weight'],
      'langcode' => 'en',
      'expanded' => FALSE,
    ]);
    $menu_link->save();
    $created_count++;
    echo "  ✅ Created: {$item['title']}\n";
  } catch (Exception $e) {
    echo "  ❌ Failed to create: {$item['title']} - " . $e->getMessage() . "\n";
  }
}

echo "✅ Created {$created_count} menu items\n";

// Step 6: Final cache clear and test
echo "\n🧪 Step 6: Final test...\n";
drupal_flush_all_caches();
\Drupal::service('plugin.manager.menu.link')->rebuild();

// Test loading MAIN1 menu
try {
  $menu_link_tree = \Drupal::menuTree();
  $parameters = $menu_link_tree->getCurrentRouteMenuTreeParameters('main1');
  $tree = $menu_link_tree->load('main1', $parameters);
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:checkAccess'],
    ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
  ];
  $tree = $menu_link_tree->transform($tree, $manipulators);
  
  echo "✅ MAIN1 menu loads successfully with " . count($tree) . " items:\n";
  foreach ($tree as $item) {
    echo "  - " . $item->link->getTitle() . "\n";
  }
} catch (Exception $e) {
  echo "❌ MAIN1 menu test failed: " . $e->getMessage() . "\n";
}

echo "\n🎉 MAIN1 menu recreation completed!\n";
echo "Now test GraphQL query: menuByName(name: MAIN1)\n"; 