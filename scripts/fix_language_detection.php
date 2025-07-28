<?php

/**
 * @file
 * Script to fix language detection and URL routing issues
 * 
 * Run with: lando drush php:script scripts/fix_language_detection.php
 */

use Drupal\Core\Language\LanguageInterface;

echo "=== Fixing Language Detection Configuration ===\n\n";

try {
  // 1. Enable language detection methods
  echo "1. Configuring language detection methods...\n";
  
  $language_types_config = \Drupal::configFactory()->getEditable('language.types');
  $language_types_config->set('configurable', [
    LanguageInterface::TYPE_INTERFACE,
    LanguageInterface::TYPE_CONTENT,
  ]);
  $language_types_config->save();
  
  echo "   ✅ Language types configured for interface and content\n";
  
  // 2. Configure language negotiation
  echo "2. Setting up language negotiation...\n";
  
  $negotiation_config = \Drupal::configFactory()->getEditable('language.negotiation');
  
  // Interface language negotiation
  $negotiation_config->set('interface', [
    'enabled' => [
      'language-url' => -8,
      'language-session' => -6,
      'language-user' => -4,
      'language-browser' => -2,
      'language-selected' => 12,
    ],
  ]);
  
  // Content language negotiation  
  $negotiation_config->set('content', [
    'enabled' => [
      'language-url' => -8,
      'language-interface' => 8,
      'language-selected' => 12,
    ],
  ]);
  
  // URL configuration
  $negotiation_config->set('url', [
    'source' => 1, // Path prefix
    'prefixes' => [
      'vi' => 'vi',
      'en' => 'en',
    ],
    'domains' => [
      'vi' => '',
      'en' => '',
    ],
  ]);
  
  $negotiation_config->save();
  echo "   ✅ Language negotiation configured\n";
  
  // 3. Check and enable required modules
  echo "3. Ensuring required modules are enabled...\n";
  
  $required_modules = ['language', 'locale', 'content_translation'];
  $module_installer = \Drupal::service('module_installer');
  
  foreach ($required_modules as $module) {
    if (!\Drupal::moduleHandler()->moduleExists($module)) {
      $module_installer->install([$module]);
      echo "   ✅ Enabled module: $module\n";
    } else {
      echo "   ✓ Module already enabled: $module\n";
    }
  }
  
  // 4. Rebuild routing
  echo "4. Rebuilding routing tables...\n";
  
  \Drupal::service('router.builder')->rebuild();
  echo "   ✅ Routing tables rebuilt\n";
  
  // 5. Clear all caches
  echo "5. Clearing all caches...\n";
  
  drupal_flush_all_caches();
  echo "   ✅ All caches cleared\n";
  
  // 6. Test internal URLs
  echo "6. Testing internal routing...\n";
  
  $url_generator = \Drupal::service('url_generator');
  
  try {
    $vi_url = $url_generator->generateFromRoute('<front>', [], ['language' => \Drupal::languageManager()->getLanguage('vi')]);
    echo "   ✅ Vietnamese URL: $vi_url\n";
    
    $en_url = $url_generator->generateFromRoute('<front>', [], ['language' => \Drupal::languageManager()->getLanguage('en')]);
    echo "   ✅ English URL: $en_url\n";
  } catch (Exception $e) {
    echo "   ⚠️ URL generation issue: " . $e->getMessage() . "\n";
  }
  
  echo "\n=== Configuration Fixed ===\n";
  echo "Language detection has been reconfigured.\n\n";
  
  echo "Expected URLs should now work:\n";
  echo "- Vietnamese: https://dseza-backend.lndo.site/vi\n";
  echo "- English: https://dseza-backend.lndo.site/en\n";
  echo "- Default: https://dseza-backend.lndo.site (should redirect or show Vietnamese)\n\n";
  
  echo "API endpoints:\n";
  echo "- /vi/jsonapi/node/bai-viet\n";
  echo "- /en/jsonapi/node/bai-viet\n";
  echo "- /vi/jsonapi/node/event\n";
  echo "- /en/jsonapi/node/event\n\n";
  
  echo "If URLs still don't work, check:\n";
  echo "1. .htaccess file in web/ directory\n";
  echo "2. Lando nginx configuration\n";
  echo "3. Run: lando drush cr to clear caches again\n";
  
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
} 