<?php

/**
 * @file
 * Simple Paragraphs Multilingual Fix
 * 
 * Based on Drupal.org documentation:
 * https://www.drupal.org/docs/contributed-modules/paragraphs/multilingual-paragraphs-configuration
 * 
 * CRITICAL RULE: Paragraph reference fields on parent entities MUST NOT be translatable
 * 
 * Run with: lando drush php:script scripts/fix_paragraphs_multilingual.php
 */

use Drupal\language\Entity\ContentLanguageSettings;

echo "=== Simple Paragraphs Multilingual Fix ===\n\n";

try {
  // Step 1: Ensure paragraph types are translatable 
  echo "1. Configuring paragraph types as translatable...\n";
  
  $paragraph_bundles = [
    'file_dinh_kem',
    'image_block', 
    'investment_card',
    'partner_logo',
    'rich_text_block',
    'slideshow',
    'thu_vien_anh',
    'video_nhung'
  ];
  
  foreach ($paragraph_bundles as $bundle) {
    $config = ContentLanguageSettings::loadByEntityTypeBundle('paragraph', $bundle);
    
    if (!$config) {
      $config = ContentLanguageSettings::create([
        'target_entity_type_id' => 'paragraph',
        'target_bundle' => $bundle,
      ]);
    }
    
    // Enable translation for paragraph types
    $config->setDefaultLangcode('vi');
    $config->setLanguageAlterable(TRUE);
    $config->setThirdPartySetting('content_translation', 'enabled', TRUE);
    
    $config->save();
    echo "  ✓ Paragraph type $bundle is now translatable\n";
  }
  
  echo "\n2. Verifying paragraph reference fields are NON-translatable...\n";
  
  // Check common paragraph reference fields
  $parent_entities = [
    'node' => ['bai_viet', 'event', 'page'],
    'block_content' => ['basic']
  ];
  
  foreach ($parent_entities as $entity_type => $bundles) {
    foreach ($bundles as $bundle) {
      $config = ContentLanguageSettings::loadByEntityTypeBundle($entity_type, $bundle);
      
      if ($config && $config->getThirdPartySetting('content_translation', 'enabled')) {
        echo "  Checking $entity_type.$bundle...\n";
        
        // Get all fields for this bundle
        $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
        
        foreach ($field_definitions as $field_name => $field_definition) {
          // Check if this is an entity reference revisions field (paragraphs)
          if ($field_definition->getType() === 'entity_reference_revisions') {
            $target_type = $field_definition->getSetting('target_type');
            
            if ($target_type === 'paragraph') {
              $bundle_settings = $config->getThirdPartySetting('content_translation', 'bundle_settings') ?? [];
              
              // Check if this paragraph field is marked as translatable
              if (isset($bundle_settings['translatable_fields'][$field_name])) {
                echo "    ❌ WARNING: $field_name is marked as translatable - this should be fixed manually\n";
                echo "       Go to /admin/config/regional/content-language and uncheck this field\n";
              } else {
                echo "    ✅ $field_name is correctly NON-translatable\n";
              }
            }
          }
        }
      }
    }
  }
  
  echo "\n3. Configuration summary:\n";
  echo "  ✅ All paragraph types are translatable (content within paragraphs can be translated)\n";
  echo "  ✅ Paragraph reference fields on parent entities should be NON-translatable\n";
  echo "  ✅ This follows Drupal.org best practices for Paragraphs multilingual setup\n\n";
  
  echo "4. Manual verification needed:\n";
  echo "  • Go to /admin/config/regional/content-language\n";
  echo "  • Click 'Paragraphs' section at the top\n";
  echo "  • Verify paragraph types are checked\n";
  echo "  • Scroll to Node, Block sections\n";
  echo "  • Ensure paragraph reference fields are UNCHECKED\n\n";
  
  // Clear caches
  drupal_flush_all_caches();
  echo "✓ Cleared all caches\n\n";
  
  echo "=== Paragraphs multilingual fix completed! ===\n";
  
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
  exit(1);
} 