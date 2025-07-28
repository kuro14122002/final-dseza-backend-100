<?php

/**
 * @file
 * Script to update multilingual configuration for DSEZA backend
 * 
 * This script ensures all content types properly support both Vietnamese (vi) and English (en) languages.
 * Run this script from Drupal root: `drush php:script scripts/update_multilingual_config.php`
 */

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Core\Language\LanguageInterface;

// Ensure both Vietnamese and English languages are available
function ensureLanguagesExist() {
  $languages = ['vi', 'en'];
  
  foreach ($languages as $langcode) {
    $language = ConfigurableLanguage::load($langcode);
    if (!$language) {
      echo "Creating language: $langcode\n";
      $language = ConfigurableLanguage::createFromLangcode($langcode);
      $language->save();
    } else {
      echo "Language $langcode already exists\n";
    }
  }
  
  // Set Vietnamese as default site language
  \Drupal::configFactory()
    ->getEditable('system.site')
    ->set('default_langcode', 'vi')
    ->save();
    
  echo "Set Vietnamese (vi) as default site language\n";
}

// Content types that should support both languages
function getContentTypesToUpdate() {
  return [
    // Node content types
    'node' => [
      'bai_viet',
      'business_partner', 
      'department',
      'du_thao_van_ban',
      'event',
      'functional_zone',
      'investment_card',
      'legal_document',
      'listed_enterprise',
      'page',
      'poll',
      'poll_choice',
      'question',
      'quick_access_link',
      'resource',
      'schedule_item',
      'staff_member',
      'tai_lieu_doanh_nghiep',
      'tin_tuyen_dung'
    ],
    
    // Block content types
    'block_content' => [
      'basic',
      'mega_menu_feature',
      'quick_link'
    ],
    
    // Media types
    'media' => [
      'audio',
      'document', 
      'image',
      'remote_video',
      'video'
    ],
    
    // Taxonomy terms
    'taxonomy_term' => [
      'cap_ban_hanh',
      'chu_de_lien_ket',
      'co_quan_ban_hanh',
      'investment_card_category',
      'khu_hanh_chinh'
    ],
    
    // Paragraph types
    'paragraph' => [
      'file_dinh_kem',
      'image_block',
      'investment_card',
      'partner_logo',
      'rich_text_block',
      'slideshow',
      'thu_vien_anh',
      'video_nhung'
    ]
  ];
}

// Update language content settings for entity types and bundles
function updateContentLanguageSettings() {
  $content_types = getContentTypesToUpdate();
  
  foreach ($content_types as $entity_type_id => $bundles) {
    echo "Updating entity type: $entity_type_id\n";
    
    foreach ($bundles as $bundle) {
      echo "  - Bundle: $bundle\n";
      
      $config_id = $entity_type_id . '.' . $bundle;
      $config = ContentLanguageSettings::loadByEntityTypeBundle($entity_type_id, $bundle);
      
      if (!$config) {
        $config = ContentLanguageSettings::create([
          'target_entity_type_id' => $entity_type_id,
          'target_bundle' => $bundle,
        ]);
      }
      
      // Configure settings
      $config->setDefaultLangcode('vi'); // Vietnamese as default
      $config->setLanguageAlterable(TRUE); // Allow users to change language
      
      // Enable content translation
      $config->setThirdPartySetting('content_translation', 'enabled', TRUE);
      $config->setThirdPartySetting('content_translation', 'bundle_settings', [
        'untranslatable_fields_hide' => '0'
      ]);
      
      $config->save();
      echo "    ✓ Updated language settings for $entity_type_id.$bundle\n";
    }
  }
}

// Configure language negotiation settings
function configureLanguageNegotiation() {
  echo "Configuring language negotiation...\n";
  
  // Enable URL language negotiation
  $config = \Drupal::configFactory()->getEditable('language.types');
  $config->set('configurable', [
    LanguageInterface::TYPE_INTERFACE,
    LanguageInterface::TYPE_CONTENT,
  ]);
  $config->save();
  
  // Configure URL prefixes
  $negotiation_config = \Drupal::configFactory()->getEditable('language.negotiation');
  $negotiation_config->set('url.prefixes', [
    'vi' => 'vi',
    'en' => 'en'
  ]);
  $negotiation_config->set('url.source', 1); // Path prefix
  $negotiation_config->save();
  
  echo "✓ Language negotiation configured\n";
}

// Enable required modules
function enableRequiredModules() {
  echo "Ensuring required modules are enabled...\n";
  
  $modules = [
    'language',
    'locale', 
    'content_translation',
    'config_translation'
  ];
  
  $module_installer = \Drupal::service('module_installer');
  
  foreach ($modules as $module) {
    if (!\Drupal::moduleHandler()->moduleExists($module)) {
      echo "Enabling module: $module\n";
      $module_installer->install([$module]);
    } else {
      echo "Module $module already enabled\n";
    }
  }
}

// Main execution
function main() {
  echo "=== DSEZA Multilingual Configuration Update ===\n\n";
  
  try {
    // Step 1: Enable required modules
    enableRequiredModules();
    echo "\n";
    
    // Step 2: Ensure languages exist
    ensureLanguagesExist();
    echo "\n";
    
    // Step 3: Configure language negotiation
    configureLanguageNegotiation();
    echo "\n";
    
    // Step 4: Update content language settings
    updateContentLanguageSettings();
    echo "\n";
    
    // Step 5: Clear caches
    drupal_flush_all_caches();
    echo "✓ Cleared all caches\n\n";
    
    echo "=== Multilingual configuration update completed successfully! ===\n";
    echo "Both Vietnamese (vi) and English (en) are now properly configured.\n";
    echo "Content editors can now create and manage content in both languages.\n\n";
    
    echo "Next steps:\n";
    echo "1. Verify configuration at: /admin/config/regional/content-language\n";
    echo "2. Test JSON:API endpoints with language prefixes: /vi/jsonapi/* and /en/jsonapi/*\n";
    echo "3. Create sample English content to test translations\n";
    
  } catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
  }
}

// Run the script
main(); 