<?php
/**
 * @file
 * Nuclear cleanup - find and destroy the problematic UUID everywhere
 * 
 * Usage: lando drush php:script scripts/nuclear_uuid_cleanup.php
 */

echo "☢️ NUCLEAR UUID CLEANUP\n";
echo "========================\n\n";

$problematic_uuid = '85c19b88-0c66-47a3-b67b-dbf7f8c223d4';
$database = \Drupal::database();

echo "🎯 Target UUID: {$problematic_uuid}\n\n";

// Get all tables in the database
echo "🔍 Scanning all database tables for the problematic UUID...\n";

$tables_to_check = [
  'cache_discovery',
  'cache_menu',
  'menu_link_content',
  'menu_link_content_data', 
  'menu_link_content_field_data',
  'menu_link_content_field_revision',
  'menu_link_content_revision',
  'menu_tree',
  'key_value',
  'cache_data',
  'cache_default',
  'cache_bootstrap',
  'cache_config',
  'cache_entity',
];

$found_locations = [];

foreach ($tables_to_check as $table) {
  echo "  🔎 Checking table: {$table}...\n";
  
  try {
    // Try to find the UUID in this table
    $query = "SELECT * FROM `{$table}` WHERE CAST(`data` AS CHAR) LIKE '%{$problematic_uuid}%' OR CAST(`value` AS CHAR) LIKE '%{$problematic_uuid}%'";
    
    try {
      $result = $database->query($query)->fetchAll();
      if (!empty($result)) {
        $found_locations[] = $table;
        echo "    ❌ FOUND in {$table}: " . count($result) . " records\n";
        
        // Show first record details
        if (isset($result[0])) {
          $first_record = (array)$result[0];
          echo "    📋 Sample record keys: " . implode(', ', array_keys($first_record)) . "\n";
        }
      }
    } catch (Exception $e) {
      // Try simpler query for tables without data/value columns
      try {
        $simple_query = "SELECT * FROM `{$table}` LIMIT 1";
        $sample = $database->query($simple_query)->fetch();
        
        if ($sample) {
          $columns = array_keys((array)$sample);
          
          // Try to search in each column that might contain the UUID
          foreach ($columns as $column) {
            if (in_array($column, ['uuid', 'data', 'value', 'serialized_data', 'id'])) {
              try {
                $column_query = "SELECT * FROM `{$table}` WHERE `{$column}` LIKE '%{$problematic_uuid}%'";
                $result = $database->query($column_query)->fetchAll();
                
                if (!empty($result)) {
                  $found_locations[] = $table;
                  echo "    ❌ FOUND in {$table}.{$column}: " . count($result) . " records\n";
                  break;
                }
              } catch (Exception $e2) {
                // Skip this column
              }
            }
          }
        }
      } catch (Exception $e2) {
        echo "    ⚠️ Cannot access table {$table}\n";
      }
    }
  } catch (Exception $e) {
    echo "    ⚠️ Error checking {$table}: " . $e->getMessage() . "\n";
  }
}

echo "\n📊 Summary: Found UUID in " . count($found_locations) . " locations\n";

if (!empty($found_locations)) {
  echo "🗑️ Cleaning up found locations...\n\n";
  
  foreach ($found_locations as $table) {
    echo "🧹 Cleaning {$table}...\n";
    
    try {
      // Different cleanup strategies for different tables
      if (strpos($table, 'cache_') === 0) {
        // For cache tables, delete entire records containing the UUID
        $deleted = $database->query("DELETE FROM `{$table}` WHERE CAST(`data` AS CHAR) LIKE '%{$problematic_uuid}%' OR CAST(`value` AS CHAR) LIKE '%{$problematic_uuid}%'")->execute();
        echo "  ✅ Deleted {$deleted} cache records\n";
        
      } elseif ($table === 'menu_tree') {
        // For menu_tree, delete by ID pattern
        $deleted = $database->query("DELETE FROM `{$table}` WHERE `id` LIKE '%{$problematic_uuid}%'")->execute();
        echo "  ✅ Deleted {$deleted} menu_tree records\n";
        
      } elseif (strpos($table, 'menu_link_content') === 0) {
        // For menu link content tables
        if (strpos($table, '_data') !== false || strpos($table, '_field_') !== false) {
          // Try by uuid column if exists
          try {
            $deleted = $database->query("DELETE FROM `{$table}` WHERE `uuid` = '{$problematic_uuid}'")->execute();
            echo "  ✅ Deleted {$deleted} records by UUID\n";
          } catch (Exception $e) {
            echo "  ⚠️ Cannot delete by UUID from {$table}\n";
          }
        } else {
          // Main menu_link_content table
          try {
            $deleted = $database->query("DELETE FROM `{$table}` WHERE `uuid` = '{$problematic_uuid}'")->execute();
            echo "  ✅ Deleted {$deleted} menu link records\n";
          } catch (Exception $e) {
            echo "  ⚠️ Cannot delete from {$table}: " . $e->getMessage() . "\n";
          }
        }
        
      } elseif ($table === 'key_value') {
        // For key_value table
        $deleted = $database->query("DELETE FROM `{$table}` WHERE CAST(`value` AS CHAR) LIKE '%{$problematic_uuid}%'")->execute();
        echo "  ✅ Deleted {$deleted} key_value records\n";
        
      } else {
        echo "  ⚠️ Don't know how to clean {$table} safely\n";
      }
      
    } catch (Exception $e) {
      echo "  ❌ Failed to clean {$table}: " . $e->getMessage() . "\n";
    }
  }
}

// Final nuclear option: clear all caches
echo "\n☢️ Nuclear cache clear...\n";
drupal_flush_all_caches();
\Drupal::service('plugin.manager.menu.link')->rebuild();

// Clear specific menu-related caches
$cache_tags = [
  'config:system.menu.main1',
  'menu:main1',
  'menu_link_content_list',
];

foreach ($cache_tags as $tag) {
  \Drupal::service('cache_tags.invalidator')->invalidateTags([$tag]);
}

echo "✅ All caches nuked\n";

// Test menu again
echo "\n🧪 Final test of MAIN1 menu...\n";

try {
  $menu_tree = \Drupal::menuTree();
  $parameters = $menu_tree->getCurrentRouteMenuTreeParameters('main1');
  $tree = $menu_tree->load('main1', $parameters);
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:checkAccess'],
    ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
  ];
  $tree = $menu_tree->transform($tree, $manipulators);
  
  echo "🎉 SUCCESS! MAIN1 menu now works with " . count($tree) . " items!\n";
  
  foreach ($tree as $item) {
    echo "  ✅ " . $item->link->getTitle() . "\n";
  }
  
} catch (Exception $e) {
  echo "❌ Still failing: " . $e->getMessage() . "\n";
  echo "💡 This might require manual database inspection or Drupal rebuild\n";
}

echo "\n☢️ Nuclear cleanup completed!\n"; 