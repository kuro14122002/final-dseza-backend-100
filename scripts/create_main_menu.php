<?php
/**
 * @file
 * Drupal script to create a simple "Main navigation" menu.
 *
 * Usage: lando drush php:script scripts/create_main_menu.php
 */

use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;

$menu_id = 'main';
$menu_label = 'Main navigation';

// Create the menu if it doesn't exist
if (!Menu::load($menu_id)) {
  $menu = Menu::create([
    'id' => $menu_id,
    'label' => $menu_label,
    'langcode' => 'vi',
  ]);
  $menu->save();
  print "Created menu {$menu_id}.\n";
} else {
  print "Menu {$menu_id} already exists.\n";
}

// Create some basic menu items
$menu_items = [
  [
    'title' => 'Giới thiệu',
    'path' => '/gioi-thieu',
    'weight' => 0,
  ],
  [
    'title' => 'Tin tức',
    'path' => '/tin-tuc',
    'weight' => 1,
  ],
  [
    'title' => 'Doanh nghiệp',
    'path' => '/doanh-nghiep',
    'weight' => 2,
  ],
  [
    'title' => 'Liên hệ',
    'path' => '/lien-he',
    'weight' => 3,
  ],
];

foreach ($menu_items as $item) {
  // Check if link already exists
  $existing = \Drupal::entityTypeManager()
    ->getStorage('menu_link_content')
    ->loadByProperties([
      'title' => $item['title'],
      'menu_name' => $menu_id,
    ]);

  if (empty($existing)) {
    $menu_link = MenuLinkContent::create([
      'title' => $item['title'],
      'link' => [
        'uri' => 'internal:' . $item['path'],
        'options' => [],
      ],
      'menu_name' => $menu_id,
      'weight' => $item['weight'],
      'langcode' => 'vi',
    ]);
    $menu_link->save();
    print "Created menu item: {$item['title']}\n";
  } else {
    print "Menu item already exists: {$item['title']}\n";
  }
}

print "Finished creating main menu.\n"; 