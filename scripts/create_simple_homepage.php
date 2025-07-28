<?php

/**
 * @file
 * Simple script to create homepage content without field dependencies
 * 
 * Run with: lando drush php:script scripts/create_simple_homepage.php
 */

use Drupal\node\Entity\Node;

echo "=== Creating Simple Homepage Content ===\n\n";

try {
  // Create Vietnamese homepage with minimal fields
  echo "1. Creating Vietnamese homepage...\n";
  
  $node_vi = Node::create([
    'type' => 'page',
    'title' => 'Trang chủ Khu Kinh tế Đà Nẵng',
    'langcode' => 'vi',
    'status' => 1,
    'uid' => 1,
  ]);
  
  $node_vi->save();
  echo "   ✅ Vietnamese homepage created with ID: " . $node_vi->id() . "\n";
  
  // Create English translation
  echo "2. Creating English homepage translation...\n";
  
  if ($node_vi->isTranslatable()) {
    $node_en = $node_vi->addTranslation('en');
    $node_en->setTitle('Da Nang Economic Zone Homepage');
    $node_en->save();
    echo "   ✅ English homepage translation created\n";
  } else {
    echo "   ⚠️ Node is not translatable, creating separate English node\n";
    
    $node_en_separate = Node::create([
      'type' => 'page', 
      'title' => 'Da Nang Economic Zone Homepage',
      'langcode' => 'en',
      'status' => 1,
      'uid' => 1,
    ]);
    $node_en_separate->save();
    echo "   ✅ Separate English homepage created with ID: " . $node_en_separate->id() . "\n";
  }
  
  // Set as homepage
  echo "3. Setting as site homepage...\n";
  
  $config = \Drupal::configFactory()->getEditable('system.site');
  $config->set('page.front', '/node/' . $node_vi->id());
  $config->save();
  
  echo "   ✅ Homepage set to node/" . $node_vi->id() . "\n";
  
  // Create some sample articles for API testing
  echo "4. Creating sample articles...\n";
  
  $article_vi = Node::create([
    'type' => 'bai_viet',
    'title' => 'Bài viết mẫu tiếng Việt',
    'langcode' => 'vi', 
    'status' => 1,
    'uid' => 1,
  ]);
  $article_vi->save();
  echo "   ✅ Vietnamese article created: " . $article_vi->id() . "\n";
  
  // Try to create English translation for article
  if ($article_vi->isTranslatable()) {
    $article_en = $article_vi->addTranslation('en');
    $article_en->setTitle('Sample English Article');
    $article_en->save();
    echo "   ✅ English article translation created\n";
  }
  
  // Create sample event
  $event_vi = Node::create([
    'type' => 'event',
    'title' => 'Sự kiện mẫu',
    'langcode' => 'vi',
    'status' => 1,
    'uid' => 1,
  ]);
  $event_vi->save();
  echo "   ✅ Vietnamese event created: " . $event_vi->id() . "\n";
  
  if ($event_vi->isTranslatable()) {
    $event_en = $event_vi->addTranslation('en');
    $event_en->setTitle('Sample Event');
    $event_en->save();
    echo "   ✅ English event translation created\n";
  }
  
  // Clear caches
  drupal_flush_all_caches();
  echo "\n✅ Caches cleared\n";
  
  echo "\n=== Success! ===\n";
  echo "You can now access:\n";
  echo "- Vietnamese: https://dseza-backend.lndo.site/vi\n";
  echo "- English: https://dseza-backend.lndo.site/en\n";
  echo "- Default: https://dseza-backend.lndo.site\n\n";
  
  echo "API endpoints:\n";
  echo "- /vi/jsonapi/node/bai-viet\n";
  echo "- /en/jsonapi/node/bai-viet\n";
  echo "- /vi/jsonapi/node/event\n";
  echo "- /en/jsonapi/node/event\n";
  
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
} 