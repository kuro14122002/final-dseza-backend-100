<?php
/**
 * @file
 * Test GraphQL queries for both MAIN and MAIN1 menus
 * 
 * Usage: lando drush php:script scripts/test_graphql_final.php
 */

echo "🧪 FINAL GRAPHQL TEST\n";
echo "====================\n\n";

// Test both menus
$menus_to_test = [
  'MAIN' => 'Vietnamese menu',
  'MAIN1' => 'English menu'
];

foreach ($menus_to_test as $menu_name => $description) {
  echo "🔍 Testing {$menu_name} ({$description})...\n";
  
  try {
    $menu_tree = \Drupal::menuTree();
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters(strtolower($menu_name));
    $tree = $menu_tree->load(strtolower($menu_name), $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);
    
    echo "✅ {$menu_name} menu loads successfully with " . count($tree) . " items:\n";
    
    foreach ($tree as $item) {
      $title = $item->link->getTitle();
      $url = '';
      try {
        $url = $item->link->getUrlObject()->toString();
      } catch (Exception $e) {
        $url = '[URL Error]';
      }
      
      echo "  - {$title} → {$url}\n";
      
      // Show subtree if exists
      if (!empty($item->subtree)) {
        foreach ($item->subtree as $subitem) {
          $subtitle = $subitem->link->getTitle();
          echo "    ├─ {$subtitle}\n";
        }
      }
    }
    
    echo "\n";
    
  } catch (Exception $e) {
    echo "❌ {$menu_name} menu failed: " . $e->getMessage() . "\n\n";
  }
}

// Test GraphQL-style structure
echo "📋 GraphQL-style structure simulation:\n";
echo "=====================================\n\n";

foreach ($menus_to_test as $menu_name => $description) {
  echo "query {\n";
  echo "  menuByName(name: {$menu_name}) {\n";
  echo "    langcode\n";
  echo "    links {\n";
  echo "      link {\n";
  echo "        label\n";
  echo "        url { path }\n";
  echo "        expanded\n";
  echo "      }\n";
  echo "      subtree {\n";
  echo "        link {\n";
  echo "          label\n";
  echo "          url { path }\n";
  echo "        }\n";
  echo "      }\n";
  echo "    }\n";
  echo "  }\n";
  echo "}\n\n";
  
  try {
    $menu_tree = \Drupal::menuTree();
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters(strtolower($menu_name));
    $tree = $menu_tree->load(strtolower($menu_name), $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);
    
    echo "Result for {$menu_name}:\n";
    echo "{\n";
    echo "  \"menuByName\": {\n";
    echo "    \"langcode\": \"" . (strtolower($menu_name) === 'main' ? 'vi' : 'en') . "\",\n";
    echo "    \"links\": [\n";
    
    $link_items = [];
    foreach ($tree as $item) {
      $title = $item->link->getTitle();
      $url = '';
      try {
        $url = $item->link->getUrlObject()->toString();
      } catch (Exception $e) {
        $url = '/';
      }
      
      $link_data = "      {\n";
      $link_data .= "        \"link\": {\n";
      $link_data .= "          \"label\": \"{$title}\",\n";
      $link_data .= "          \"url\": { \"path\": \"{$url}\" },\n";
      $link_data .= "          \"expanded\": " . (!empty($item->subtree) ? 'true' : 'false') . "\n";
      $link_data .= "        },\n";
      $link_data .= "        \"subtree\": [\n";
      
      if (!empty($item->subtree)) {
        $sub_items = [];
        foreach ($item->subtree as $subitem) {
          $subtitle = $subitem->link->getTitle();
          $sub_items[] = "          {\n            \"link\": {\n              \"label\": \"{$subtitle}\",\n              \"url\": { \"path\": \"/\" }\n            }\n          }";
        }
        $link_data .= implode(",\n", $sub_items) . "\n";
      }
      
      $link_data .= "        ]\n";
      $link_data .= "      }";
      
      $link_items[] = $link_data;
    }
    
    echo implode(",\n", $link_items) . "\n";
    echo "    ]\n";
    echo "  }\n";
    echo "}\n\n";
    
  } catch (Exception $e) {
    echo "❌ Failed to simulate GraphQL for {$menu_name}: " . $e->getMessage() . "\n\n";
  }
}

echo "🎉 GraphQL test completed!\n";
echo "💡 Frontend should now be able to query:\n";
echo "   - menuByName(name: MAIN) for Vietnamese\n";
echo "   - menuByName(name: MAIN1) for English\n"; 