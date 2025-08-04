<?php
/**
 * @file
 * Script to completely recreate MAIN1 menu to fix UUID issue
 * 
 * Usage: lando drush php:script scripts/recreate_main1_menu.php
 */

use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;

echo "🔧 Recreating MAIN1 menu to fix UUID issue...\n\n";

// Step 1: Delete existing MAIN1 menu and all its links
echo "🗑️ Deleting existing MAIN1 menu...\n";

// Delete all menu links first
$menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$main1_menu_links = $menu_link_storage->loadByProperties(['menu_name' => 'main1']);

if (!empty($main1_menu_links)) {
  $deleted_count = 0;
  foreach ($main1_menu_links as $menu_link) {
    $menu_link->delete();
    $deleted_count++;
  }
  echo "✅ Deleted {$deleted_count} menu links from MAIN1.\n";
}

// Delete the menu itself
$menu = Menu::load('main1');
if ($menu) {
  $menu->delete();
  echo "✅ Deleted MAIN1 menu.\n";
} else {
  echo "⚠️ MAIN1 menu not found.\n";
}

// Step 2: Recreate MAIN1 menu
echo "\n🔨 Creating new MAIN1 menu...\n";

$menu_id = 'main1';
$menu_label = 'Main1';

// Create the menu
$menu = Menu::create([
  'id' => $menu_id,
  'label' => $menu_label,
  'langcode' => 'en',
]);
$menu->save();
echo "✅ Created new MAIN1 menu.\n";

// Step 3: Add basic menu structure (simplified version)
echo "\n📋 Adding basic menu items...\n";

/**
 * Simplified menu structure for MAIN1 (English)
 */
$menu_structure = [
  [
    'title' => 'Introduction',
    'path' => '/en/gioi-thieu',
    'weight' => 0,
    'children' => [
      [
        'title' => 'Welcome Letter',
        'path' => '/en/gioi-thieu/thu-chao-mung',
        'weight' => 0,
      ],
      [
        'title' => 'Functions & Duties',
        'path' => '/en/gioi-thieu/chuc-nang-nhiem-vu',
        'weight' => 1,
      ],
      [
        'title' => 'Management Board',
        'path' => '/en/gioi-thieu/ban-lanh-dao',
        'weight' => 2,
      ],
    ],
  ],
  [
    'title' => 'News',
    'path' => '/en/tin-tuc',
    'weight' => 1,
    'children' => [
      [
        'title' => 'News & Events',
        'path' => '/en/tin-tuc/tin-tuc-su-kien',
        'weight' => 0,
      ],
      [
        'title' => 'Investment News',
        'path' => '/en/tin-tuc/tin-dau-tu',
        'weight' => 1,
      ],
    ],
  ],
  [
    'title' => 'Business',
    'path' => '/en/doanh-nghiep',
    'weight' => 2,
    'children' => [
      [
        'title' => 'Enterprise List',
        'path' => '/en/doanh-nghiep/danh-sach',
        'weight' => 0,
      ],
      [
        'title' => 'Business Support',
        'path' => '/en/doanh-nghiep/ho-tro',
        'weight' => 1,
      ],
    ],
  ],
  [
    'title' => 'Documents',
    'path' => '/en/van-ban',
    'weight' => 3,
    'children' => [
      [
        'title' => 'Legal Documents',
        'path' => '/en/van-ban/van-ban-phap-luat',
        'weight' => 0,
      ],
      [
        'title' => 'Guidance Documents',
        'path' => '/en/van-ban/huong-dan',
        'weight' => 1,
      ],
    ],
  ],
  [
    'title' => 'Services',
    'path' => '/en/dich-vu',
    'weight' => 4,
    'children' => [
      [
        'title' => 'Online Services',
        'path' => '/en/dich-vu/truc-tuyen',
        'weight' => 0,
      ],
    ],
  ],
  [
    'title' => 'Contact',
    'path' => '/en/lien-he',
    'weight' => 5,
  ],
];

/**
 * Create menu links recursively
 */
function createMenuLinks($items, $menu_name, $parent_id = NULL, $depth = 0) {
  $created_count = 0;
  
  foreach ($items as $item) {
    // Convert path to proper format
    $path = $item['path'];
    if (empty($path) || $path === '#') {
      $uri = 'route:<nolink>';
    } else {
      $uri = 'internal:' . $path;
    }
    
    // Create menu link
    $menu_link_data = [
      'title' => $item['title'],
      'link' => [
        'uri' => $uri,
        'options' => [],
      ],
      'menu_name' => $menu_name,
      'weight' => $item['weight'] ?? 0,
      'langcode' => 'en',
    ];
    
    if ($parent_id) {
      $menu_link_data['parent'] = $parent_id;
    }
    
    $menu_link = MenuLinkContent::create($menu_link_data);
    $menu_link->save();
    
    $created_count++;
    $indent = str_repeat('  ', $depth);
    echo "{$indent}✅ Created: {$item['title']}\n";
    
    // Create children if they exist
    if (!empty($item['children'])) {
      $child_count = createMenuLinks($item['children'], $menu_name, $menu_link->getPluginId(), $depth + 1);
      $created_count += $child_count;
    }
  }
  
  return $created_count;
}

$total_created = createMenuLinks($menu_structure, $menu_id);

echo "\n📊 Summary:\n";
echo "  - Created {$total_created} menu items in MAIN1\n";

// Step 4: Clear cache and test
echo "\n🔄 Clearing cache...\n";
\Drupal::service('plugin.manager.menu.link')->rebuild();
drupal_flush_all_caches();

// Test the recreated menu
echo "\n🧪 Testing recreated MAIN1 menu...\n";
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
  
  // List top-level items
  foreach ($tree as $item) {
    echo "  - " . $item->link->getTitle() . "\n";
  }
  
} catch (Exception $e) {
  echo "❌ MAIN1 menu still has issues: " . $e->getMessage() . "\n";
}

echo "\n✅ MAIN1 menu recreation completed!\n"; 