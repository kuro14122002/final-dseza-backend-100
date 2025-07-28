<?php

/**
 * @file
 * Script to fix paragraphs translation settings. (Version 3)
 *
 * This version builds the entire correct configuration structure from scratch
 * and overwrites the existing one to ensure correctness.
 *
 * Usage: lando drush php:script scripts/fix_translation.php
 */

echo "Starting paragraphs translation fix (v3 - Final)...\n\n";

$config_factory = \Drupal::configFactory();
$success = TRUE;

// ===== STEP 1: Fix the main paragraphs field on the Node =====
$field_storage_config_name = 'field.storage.node.field_noi_dung_bai_viet';
$field_storage_config = $config_factory->getEditable($field_storage_config_name);
if ($field_storage_config->get('translatable')) {
  $field_storage_config->set('translatable', FALSE)->save();
  echo "[OK] Step 1: Disabled translation on '{$field_storage_config_name}'.\n";
} else {
  echo "[INFO] Step 1: Translation on '{$field_storage_config_name}' was already disabled.\n";
}

// ===== STEP 2: Define which fields INSIDE each paragraph type should be translated =====
$translatable_fields_map = [
  'noi_dung_bai_viet' => [
    'field_body',
    'field_anh',
  ],
  'tieu_de' => [
    'field_tieu_de',
  ],
];

echo "\n===== STEP 3: Rebuilding and applying translation settings =====\n";

foreach ($translatable_fields_map as $paragraph_type => $fields_to_translate) {
  $config_id = "language.content_settings.paragraph.{$paragraph_type}";
  $config = $config_factory->getEditable($config_id);

  // Build the complete, correct field settings from scratch.
  $field_settings = [];
  foreach ($fields_to_translate as $field_name) {
    // 'false' means the field IS translatable (the key is 'untranslatable_fields_HIDE').
    $field_settings[$field_name] = FALSE;
  }
  
  // Build the entire desired configuration structure for 'third_party_settings'.
  $correct_third_party_settings = [
    'content_translation' => [
      'enabled' => TRUE,
      'bundle_settings' => [
        'untranslatable_fields_hide' => $field_settings,
      ],
    ],
  ];

  // Set top-level settings and overwrite the third_party_settings completely.
  $config->set('target_entity_type_id', 'paragraph');
  $config->set('target_bundle', $paragraph_type);
  $config->set('default_langcode', 'site_default'); // Use site_default for consistency
  $config->set('language_alterable', TRUE);
  $config->set('third_party_settings', $correct_third_party_settings);
  
  // Save the configuration.
  if ($config->save()) {
    echo "[OK] Successfully rebuilt and saved settings for paragraph type: '{$paragraph_type}'.\n";
  } else {
    echo "[ERROR] Failed to save settings for paragraph type: '{$paragraph_type}'.\n";
    $success = FALSE;
  }
}

echo "\n---------------------------------------------------\n";
if ($success) {
    echo "✅ SUCCESS: Script has finished updating the configuration.\n";
} else {
    echo "❌ ERROR: The script encountered errors. Please review the output.\n";
}
echo "IMPORTANT: Please clear Drupal's cache NOW to see the changes.\n";
echo "Run the following command:\n";
echo "lando drush cr\n";

?>