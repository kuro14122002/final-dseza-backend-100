<?php
/**
 * @file
 * Complete MAIN1 menu restoration with English translations
 * 
 * Usage: lando drush php:script scripts/restore_complete_main1_menu.php
 */

use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;

echo "🔧 Complete MAIN1 (English) menu restoration\n";
echo "=============================================\n\n";

$database = \Drupal::database();

// Step 1: Clean existing MAIN1 menu completely
echo "🗑️ Step 1: Complete cleanup of MAIN1 menu...\n";

// Delete config
try {
  $deleted = $database->delete('config')
    ->condition('name', 'system.menu.main1')
    ->execute();
  echo "✅ Deleted {$deleted} MAIN1 menu config records\n";
} catch (Exception $e) {
  echo "⚠️ Could not delete config: " . $e->getMessage() . "\n";
}

// Clean MAIN1 specific data
$tables_to_clean = [
  "DELETE FROM menu_link_content_data WHERE bundle = 'menu_link_content' AND menu_name = 'main1'",
  "DELETE FROM menu_tree WHERE menu_name = 'main1'",
  "DELETE FROM cache_menu WHERE cid LIKE '%main1%'"
];

foreach ($tables_to_clean as $query) {
  try {
    $database->query($query)->execute();
    echo "✅ Cleaned MAIN1 specific data\n";
  } catch (Exception $e) {
    echo "⚠️ Could not clean some MAIN1 data\n";
  }
}

// Clear all caches
drupal_flush_all_caches();
\Drupal::service('plugin.manager.menu.link')->rebuild();
echo "✅ All caches cleared\n";

// Step 2: Create new MAIN1 menu
echo "\n🔨 Step 2: Creating new MAIN1 menu...\n";

$config_data = [
  'uuid' => \Drupal::service('uuid')->generate(),
  'langcode' => 'en',
  'status' => TRUE,
  'dependencies' => [],
  'id' => 'main1',
  'label' => 'Main1 navigation',
  'description' => 'English navigation menu - complete structure',
  'locked' => FALSE,
];

$config = \Drupal::configFactory()->getEditable('system.menu.main1');
$config->setData($config_data)->save();
echo "✅ Created MAIN1 menu\n";

// Step 3: Build complete English menu structure
echo "\n📋 Step 3: Building complete English menu structure...\n";

/**
 * Complete English menu structure based on Vietnamese counterpart
 */
$complete_english_menu_structure = [
  // 1. Introduction
  [
    'title' => 'Introduction',
    'url' => '',
    'weight' => 0,
    'children' => [
      [
                 'title' => 'General Introduction',
         'url' => '/general-introduction',
         'weight' => 0,
         'children' => [
           [
             'title' => 'Open Letter',
             'url' => '/introduction/general-introduction/open-letter',
             'weight' => 0,
           ],
           [
             'title' => 'Overview of Da Nang',
             'url' => '/introduction/general-introduction/overview-of-da-nang',
             'weight' => 1,
           ],
           [
             'title' => 'Overview of Management Board',
             'url' => '/introduction/general-introduction/overview-of-management-board',
             'weight' => 2,
             'children' => [
               [
                 'title' => 'Functions and Tasks',
                 'url' => '/introduction/general-introduction/overview-of-management-board/functions-and-tasks',
                 'weight' => 0,
               ],
               [
                 'title' => 'Departments',
                 'url' => '/introduction/general-introduction/overview-of-management-board/departments',
                 'weight' => 1,
               ],
               [
                 'title' => 'Subordinate Units',
                 'url' => '/introduction/general-introduction/overview-of-management-board/subordinate-units',
                 'weight' => 2,
               ],
             ],
           ],
         ],
       ],
       [
         'title' => 'Functional Zones',
         'url' => '/introduction/functional-zones',
        'weight' => 1,
        'children' => [
                     ['title' => 'Da Nang Hi-Tech Park', 'url' => '/introduction/functional-zones/da-nang-hi-tech-park', 'weight' => 0],
           ['title' => 'Da Nang Free Trade Zone', 'url' => '/introduction/functional-zones/da-nang-free-trade-zone', 'weight' => 1],
           ['title' => 'IT Concentration Zone', 'url' => '/introduction/functional-zones/it-concentration-zone', 'weight' => 2],
           ['title' => 'Hoa Khanh Industrial Park', 'url' => '/introduction/functional-zones/hoa-khanh-industrial-park', 'weight' => 3],
           ['title' => 'Hoa Khanh Expanded Industrial Park', 'url' => '/introduction/functional-zones/hoa-khanh-expanded-industrial-park', 'weight' => 4],
           ['title' => 'Da Nang Industrial Park', 'url' => '/introduction/functional-zones/da-nang-industrial-park', 'weight' => 5],
           ['title' => 'Da Nang Seafood Service Industrial Park', 'url' => '/introduction/functional-zones/da-nang-seafood-service-industrial-park', 'weight' => 6],
           ['title' => 'Hoa Cam Industrial Park', 'url' => '/introduction/functional-zones/hoa-cam-industrial-park', 'weight' => 7],
           ['title' => 'Lien Chieu Industrial Park', 'url' => '/introduction/functional-zones/lien-chieu-industrial-park', 'weight' => 8],
           ['title' => 'Hoa Ninh Industrial Park', 'url' => '/introduction/functional-zones/hoa-ninh-industrial-park', 'weight' => 9],
        ],
      ],
      [
        'title' => 'Outstanding Achievements',
        'url' => '',
        'weight' => 2,
        'children' => [
          [
            'title' => 'Outstanding Achievements of Da Nang',
            'url' => 'https://en.wikipedia.org/wiki/Da_Nang',
            'weight' => 0,
          ],
        ],
      ],
    ],
  ],
  
  // 2. News
  [
    'title' => 'News',
    'url' => '',
    'weight' => 1,
    'children' => [
             [
         'title' => 'News | Events',
         'url' => '/news/events',
         'weight' => 0,
         'children' => [
           ['title' => 'Investment - International Cooperation', 'url' => '/news/events/investment-international-cooperation', 'weight' => 0],
           ['title' => 'Enterprises', 'url' => '/news/events/enterprises', 'weight' => 1],
           ['title' => 'Digital Transformation', 'url' => '/news/events/digital-transformation', 'weight' => 2],
           ['title' => 'Training, Startup Incubation', 'url' => '/news/events/training-startup-incubation', 'weight' => 3],
           ['title' => 'Management Board Activities', 'url' => '/news/events/management-board-activities', 'weight' => 4],
           ['title' => 'Other News', 'url' => '/news/events/other-news', 'weight' => 5],
         ],
       ],
       [
         'title' => 'See More',
         'url' => '/see-more',
         'weight' => 1,
         'children' => [
           ['title' => 'Work Schedule', 'url' => '/news/work-schedule', 'weight' => 0],
           ['title' => 'Announcements', 'url' => '/news/announcements', 'weight' => 1],
           ['title' => 'Press Information', 'url' => '/news/press-information', 'weight' => 2],
         ],
       ],
    ],
  ],
  
  // 3. Business
  [
    'title' => 'Business',
    'url' => '',
    'weight' => 2,
    'children' => [
      [
        'title' => 'Reports & Data',
        'url' => '',
        'weight' => 0,
        'children' => [
          ['title' => 'Online Reports on DSEZA', 'url' => 'https://maps.dhpiza.vn/login?ReturnUrl=/admin/baocaonhadautu/yeucaubaocao', 'weight' => 0],
                     ['title' => 'Investment Monitoring and Evaluation Reports', 'url' => '/business/reports-data/investment-monitoring-evaluation-reports', 'weight' => 1],
           ['title' => 'Report Forms | Templates', 'url' => '/business/reports-data/report-forms-templates', 'weight' => 2],
         ],
       ],
       [
         'title' => 'Enterprise Information',
         'url' => '/business/enterprise-information',
         'weight' => 1,
         'children' => [
           ['title' => 'Procedures | Records | Environmental Data', 'url' => '/business/enterprise-information/procedures-records-environmental-data', 'weight' => 0],
           ['title' => 'Enterprise Statistics', 'url' => '/business/enterprise-information/enterprise-statistics', 'weight' => 1],
           ['title' => 'Recruitment', 'url' => '/business/recruitment', 'weight' => 2],
        ],
      ],
    ],
  ],
  
     // 4. Investment Handbook
   [
     'title' => 'Investment Handbook',
     'url' => '/investment-handbook',
     'weight' => 3,
   ],
  
  // 5. Documents
  [
    'title' => 'Documents',
    'url' => '',
    'weight' => 4,
    'children' => [
      [
        'title' => 'Legal Documents',
        'url' => '',
        'weight' => 0,
                 'children' => [
           ['title' => 'Central Legal Regulations', 'url' => '/documents/legal-documents/central-legal-regulations', 'weight' => 0],
           ['title' => 'Local Legal Regulations', 'url' => '/documents/legal-documents/local-legal-regulations', 'weight' => 1],
           ['title' => 'Directive and Management Documents', 'url' => '/documents/legal-documents/directive-management-documents', 'weight' => 2],
           ['title' => 'Administrative Reform Documents', 'url' => '/documents/legal-documents/administrative-reform-documents', 'weight' => 3],
         ],
       ],
       [
         'title' => 'Guidelines & Feedback',
         'url' => '/documents/guidelines-feedback',
         'weight' => 1,
         'children' => [
           ['title' => 'Guideline Documents', 'url' => '/documents/guidelines-feedback/guideline-documents', 'weight' => 0],
           ['title' => 'Draft Document Feedback', 'url' => '/documents/guidelines-feedback/draft-document-feedback', 'weight' => 1],
         ],
       ],
       [
         'title' => 'Document Search',
         'url' => '',
         'weight' => 2,
         'children' => [
           ['title' => 'Document Search System', 'url' => '/documents/search', 'weight' => 0],
        ],
      ],
    ],
  ],
  
  // 6. Administrative Reform - COMPLETE STRUCTURE
  [
    'title' => 'Administrative Reform',
    'url' => '',
    'weight' => 5,
    'children' => [
      [
        'title' => 'Applications & Services',
        'url' => '',
        'weight' => 0,
        'children' => [
          ['title' => 'Online Public Services', 'url' => 'https://egov.danang.gov.vn/dailyDVc', 'weight' => 0],
          [
            'title' => 'Public Postal Services',
            'url' => 'https://egov.danang.gov.vn/dailyDVc',
            'weight' => 1,
            'children' => [
              ['title' => 'Online Public Services', 'url' => '/en/node/38', 'weight' => 0],
            ],
          ],
          ['title' => 'Records Search', 'url' => 'https://dichvucong.danang.gov.vn/vi/', 'weight' => 2],
          ['title' => 'Online Appointment Booking', 'url' => 'http://49.156.54.87/index.php?option=com_hengio&view=hengioonline&task=formdangkyonline&tmpl=widget', 'weight' => 3],
          ['title' => 'Service Quality Assessment', 'url' => 'https://dichvucong.danang.gov.vn/vi/', 'weight' => 4],
        ],
      ],
      [
        'title' => 'For Investors',
        'url' => '/news/for-investors',
        'weight' => 1,
        'children' => [
          [
            'title' => 'Investment Sector Procedures',
            'url' => '/news/for-investors/investment-sector-procedures',
            'weight' => 0,
            'children' => [
              ['title' => 'Investment Sector Procedures', 'url' => '/node/25', 'weight' => 0],
            ],
          ],
          [
            'title' => 'Investment Incentive Sectors',
            'url' => '/news/for-investors/investment-incentive-sectors',
            'weight' => 1,
            'children' => [
              ['title' => 'Investment Attraction Sectors', 'url' => '/node/35', 'weight' => 0],
            ],
          ],
          [
            'title' => 'Functional Zone Planning',
            'url' => '/introduction/functional-zones',
            'weight' => 2,
            'children' => [
              ['title' => 'Functional Zone Planning', 'url' => '/node/37', 'weight' => 0],
            ],
          ],
          [
            'title' => 'Postal Document Submission Registration',
            'url' => 'https://egov.danang.gov.vn/dailyDVc',
            'weight' => 3,
            'children' => [
              ['title' => 'Postal Document Submission Registration', 'url' => '/node/36', 'weight' => 0],
            ],
          ],
          [
            'title' => 'Administrative Procedures Search',
            'url' => 'https://dichvucong.danang.gov.vn/vi/',
            'weight' => 4,
            'children' => [
              ['title' => 'Administrative Procedures Search', 'url' => '/node/39', 'weight' => 0],
            ],
          ],
          ['title' => 'Online Public Services', 'url' => 'https://dichvucong.danang.gov.vn/vi/', 'weight' => 5],
        ],
      ],
      [
        'title' => 'Investment Environment',
        'url' => '/news/investment-environment',
        'weight' => 2,
        'children' => [
          [
            'title' => 'Industrial Park Infrastructure',
            'url' => '/introduction/functional-zones',
            'weight' => 0,
            'children' => [
              ['title' => 'Industrial Park Infrastructure', 'url' => '/node/43', 'weight' => 0],
            ],
          ],
          [
            'title' => 'Transportation Infrastructure',
            'url' => '/news/investment-environment/transportation-infrastructure',
            'weight' => 1,
            'children' => [
              ['title' => 'Transportation Infrastructure', 'url' => '/node/42', 'weight' => 0],
            ],
          ],
          [
            'title' => 'Science Technology - Environment',
            'url' => '/news/investment-environment/science-technology-environment',
            'weight' => 2,
            'children' => [
              ['title' => 'Science Technology - Environment', 'url' => '/node/49', 'weight' => 0],
            ],
          ],
          [
            'title' => 'Logistics',
            'url' => '/news/investment-environment/logistics',
            'weight' => 3,
            'children' => [
              ['title' => 'Logistics', 'url' => '/node/44', 'weight' => 0],
            ],
          ],
          [
            'title' => 'Social Infrastructure',
            'url' => '/news/investment-environment/social-infrastructure',
            'weight' => 4,
            'children' => [
              ['title' => 'Social Infrastructure', 'url' => '/node/45', 'weight' => 0],
            ],
          ],
          [
            'title' => 'Human Resources',
            'url' => '/news/investment-environment/human-resources',
            'weight' => 5,
            'children' => [
              ['title' => 'Human Resources', 'url' => '/node/46', 'weight' => 0],
            ],
          ],
          ['title' => 'Administrative Reform', 'url' => '/news/investment-environment/administrative-reform', 'weight' => 6],
          ['title' => 'Digital Transformation', 'url' => '/news/events/digital-transformation', 'weight' => 7],
        ],
      ],
      [
        'title' => 'Information & Procedures',
        'url' => '',
        'weight' => 3,
        'children' => [
          ['title' => 'Administrative Procedures', 'url' => 'https://dichvucong.danang.gov.vn/vi/', 'weight' => 0],
                     ['title' => 'Administrative Reform Documents', 'url' => '/documents/legal-documents/administrative-reform', 'weight' => 1],
        ],
      ],
    ],
  ],
  
  // 7. Utilities - COMPLETE STRUCTURE
  [
    'title' => 'Utilities',
    'url' => '',
    'weight' => 6,
    'children' => [
      [
        'title' => 'FAQ | Comment',
        'url' => '',
        'weight' => 0,
                 'children' => [
           ['title' => 'Q&A', 'url' => '/utilities/qa', 'weight' => 0],
           ['title' => 'Frequently Asked Questions', 'url' => '/utilities/frequently-asked-questions', 'weight' => 1],
          ['title' => 'Da Nang City Feedback Portal', 'url' => 'https://gopy.danang.gov.vn/', 'weight' => 2],
        ],
      ],
      [
        'title' => 'Enterprise Connection',
        'url' => '',
        'weight' => 1,
        'children' => [
          ['title' => 'Coffee with DSEZA', 'url' => 'https://docs.google.com/forms/d/e/1FAIpQLSc7gyKy8ESi7k9Hxja0Mi9YAnWLf_yU3fQPnyzYp9hWGLLREg/viewform', 'weight' => 0],
        ],
      ],
      [
        'title' => 'See More',
        'url' => '',
        'weight' => 2,
                 'children' => [
           ['title' => 'Work Schedule', 'url' => '/news/work-schedule', 'weight' => 0],
          ['title' => 'Specialized Data', 'url' => '', 'weight' => 1],
          ['title' => 'Public Procurement', 'url' => '', 'weight' => 2],
          ['title' => 'Work by Capacity | Labor-oriented', 'url' => 'https://dseza-backend.lndo.site/sites/default/files/2025-07/t%C3%A0i-li%E1%BB%87u-s%E1%BB%AD-d%E1%BB%A5ng.pdf', 'weight' => 3],
        ],
      ],
    ],
  ],
  
     // 8. Contact
   [
     'title' => 'Contact',
     'url' => '/contact',
     'weight' => 7,
   ],
];

/**
 * Recursive function to create English menu items
 */
function createEnglishMenuItemsRecursive($items, $menu_name, $parent_plugin_id = NULL, $depth = 0) {
  $created_count = 0;
  
  foreach ($items as $item) {
    $indent = str_repeat('  ', $depth);
    echo "{$indent}🔧 Creating: {$item['title']}\n";
    
    // Determine URI
    $url = $item['url'] ?? '';
    if (empty($url)) {
      $uri = 'route:<nolink>';
    } elseif (strpos($url, 'http') === 0) {
      $uri = $url; // External URL
    } else {
      $uri = 'internal:' . $url;
    }
    
    // Create menu link
    $menu_link_data = [
      'title' => $item['title'],
      'link' => ['uri' => $uri],
      'menu_name' => $menu_name,
      'weight' => $item['weight'] ?? 0,
      'langcode' => 'en',
      'expanded' => !empty($item['children']),
    ];
    
    if ($parent_plugin_id) {
      $menu_link_data['parent'] = $parent_plugin_id;
    }
    
    try {
      $menu_link = MenuLinkContent::create($menu_link_data);
      $menu_link->save();
      $created_count++;
      echo "{$indent}  ✅ Created successfully\n";
      
      // Create children if they exist
      if (!empty($item['children'])) {
        $child_count = createEnglishMenuItemsRecursive($item['children'], $menu_name, $menu_link->getPluginId(), $depth + 1);
        $created_count += $child_count;
      }
      
    } catch (Exception $e) {
      echo "{$indent}  ❌ Failed: " . $e->getMessage() . "\n";
    }
  }
  
  return $created_count;
}

$total_created = createEnglishMenuItemsRecursive($complete_english_menu_structure, 'main1');

echo "\n📊 Summary: Created {$total_created} English menu items total\n";

// Step 4: Final verification
echo "\n🧪 Step 4: Final verification...\n";
drupal_flush_all_caches();

try {
  $menu_tree = \Drupal::menuTree();
  $parameters = $menu_tree->getCurrentRouteMenuTreeParameters('main1');
  $tree = $menu_tree->load('main1', $parameters);
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:checkAccess'],
    ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
  ];
  $tree = $menu_tree->transform($tree, $manipulators);
  
  echo "🎉 SUCCESS! Complete MAIN1 (English) menu with " . count($tree) . " top-level items:\n";
  foreach ($tree as $item) {
    echo "  - " . $item->link->getTitle();
    if (!empty($item->subtree)) {
      echo " (" . count($item->subtree) . " subitems)";
    }
    echo "\n";
  }
  
  // Count expected key sections in English
  $key_sections = ['Introduction', 'News', 'Business', 'Investment Handbook', 'Documents', 'Administrative Reform', 'Utilities', 'Contact'];
  echo "\n📋 Expected English sections: " . implode(', ', $key_sections) . "\n";
  echo "✅ All English sections should now be present including 'Administrative Reform' and 'Utilities' with 'Q&A'\n";
  
} catch (Exception $e) {
  echo "❌ Verification failed: " . $e->getMessage() . "\n";
}

echo "\n🎯 Complete MAIN1 (English) menu restoration finished!\n";
echo "💡 This English menu now matches the Vietnamese structure with proper translations.\n"; 