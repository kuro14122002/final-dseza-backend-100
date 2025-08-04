<?php
/**
 * @file
 * Restore full MAIN menu structure based on user's GraphQL response
 * 
 * Usage: lando drush php:script scripts/restore_full_main_menu.php
 */

use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;

echo "🔧 Restoring full MAIN menu structure\n";
echo "====================================\n\n";

$database = \Drupal::database();

// Step 1: Clean existing MAIN menu
echo "🗑️ Step 1: Cleaning existing MAIN menu...\n";

// Delete config
try {
  $deleted = $database->delete('config')
    ->condition('name', 'system.menu.main')
    ->execute();
  echo "✅ Deleted {$deleted} MAIN menu config records\n";
} catch (Exception $e) {
  echo "⚠️ Could not delete config: " . $e->getMessage() . "\n";
}

// Clean database
try {
  $database->query("DELETE FROM menu_link_content WHERE bundle = 'menu_link_content'")->execute();
  $database->query("DELETE FROM cache_menu")->execute();
  $database->query("DELETE FROM menu_tree WHERE menu_name = 'main'")->execute();
  echo "✅ Database cleaned\n";
} catch (Exception $e) {
  echo "⚠️ Database cleanup had issues\n";
}

// Clear caches
drupal_flush_all_caches();
\Drupal::service('plugin.manager.menu.link')->rebuild();
echo "✅ Caches cleared\n";

// Step 2: Create new MAIN menu
echo "\n🔨 Step 2: Creating new MAIN menu...\n";

$config_data = [
  'uuid' => \Drupal::service('uuid')->generate(),
  'langcode' => 'vi',
  'status' => TRUE,
  'dependencies' => [],
  'id' => 'main',
  'label' => 'Main navigation',
  'description' => 'Vietnamese navigation menu - full structure',
  'locked' => FALSE,
];

$config = \Drupal::configFactory()->getEditable('system.menu.main');
$config->setData($config_data)->save();
echo "✅ Created MAIN menu\n";

// Step 3: Build full menu structure
echo "\n📋 Step 3: Building full menu structure...\n";

/**
 * Full menu structure based on user's GraphQL response
 */
$full_menu_structure = [
  // 1. Giới thiệu
  [
    'title' => 'Giới thiệu',
    'url' => '',
    'weight' => 0,
    'children' => [
      [
        'title' => 'Giới thiệu chung',
        'url' => '/gioi-thieu-chung',
        'weight' => 0,
        'children' => [
          [
            'title' => 'Thư ngỏ',
            'url' => '/gioi-thieu/gioi-thieu-chung/thu-ngo',
            'weight' => 0,
          ],
          [
            'title' => 'Tổng quan về Đà Nẵng',
            'url' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-da-nang',
            'weight' => 1,
          ],
          [
            'title' => 'Tổng quan về Ban Quản lý',
            'url' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-ban-quan-ly',
            'weight' => 2,
            'children' => [
              [
                'title' => 'Chức năng, nhiệm vụ',
                'url' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-ban-quan-ly/chuc-nang-nhiem-vu',
                'weight' => 0,
              ],
              [
                'title' => 'Các phòng ban',
                'url' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-ban-quan-ly/cac-phong-ban',
                'weight' => 1,
              ],
              [
                'title' => 'Đơn vị trực thuộc',
                'url' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-ban-quan-ly/don-vi-truc-thuoc',
                'weight' => 2,
              ],
            ],
          ],
        ],
      ],
      [
        'title' => 'Các Khu chức năng',
        'url' => '/gioi-thieu/cac-khu-chuc-nang',
        'weight' => 1,
        'children' => [
          ['title' => 'Khu công nghệ cao Đà Nẵng', 'url' => '/vi/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghe-cao-da-nang', 'weight' => 0],
          ['title' => 'Khu thương mại tự do Đà Nẵng', 'url' => '/gioi-thieu/cac-khu-chuc-nang/khu-thuong-mai-tu-do-da-nang', 'weight' => 1],
          ['title' => 'Khu CNTT tập trung', 'url' => '/gioi-thieu/cac-khu-chuc-nang/khu-cntt-tap-trung', 'weight' => 2],
          ['title' => 'Khu công nghiệp Hòa Khánh', 'url' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-khanh', 'weight' => 3],
          ['title' => 'Khu công nghiệp Hòa Khánh mở rộng', 'url' => '/vi/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-khanh-mo-rong', 'weight' => 4],
          ['title' => 'Khu công nghiệp Đà Nẵng', 'url' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-da-nang', 'weight' => 5],
          ['title' => 'Khu công nghiệp Dịch vụ Thủy sản Đà Nẵng', 'url' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-dich-vu-thuy-san-da-nang', 'weight' => 6],
          ['title' => 'Khu công nghiệp Hòa Cầm', 'url' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-cam', 'weight' => 7],
          ['title' => 'Khu công nghiệp Liên Chiểu', 'url' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-lien-chieu', 'weight' => 8],
          ['title' => 'Khu công nghiệp Hòa Ninh', 'url' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-ninh', 'weight' => 9],
        ],
      ],
      [
        'title' => 'Thành tựu nổi bật',
        'url' => '',
        'weight' => 2,
        'children' => [
          [
            'title' => 'Thành tựu nổi bật của Đà Nẵng',
            'url' => 'https://vi.wikipedia.org/wiki/%C4%90%C3%A0_N%E1%BA%B5ng',
            'weight' => 0,
          ],
        ],
      ],
    ],
  ],
  
  // 2. Tin tức
  [
    'title' => 'Tin tức',
    'url' => '',
    'weight' => 1,
    'children' => [
      [
        'title' => 'Tin tức | Sự kiện',
        'url' => '/tin-tuc/su-kien',
        'weight' => 0,
        'children' => [
          ['title' => 'Đầu tư - Hợp tác Quốc tế', 'url' => '/tin-tuc/su-kien/dau-tu-hop-tac-quoc-te', 'weight' => 0],
          ['title' => 'Doanh nghiệp', 'url' => '/tin-tuc/su-kien/doanh-nghiep', 'weight' => 1],
          ['title' => 'Chuyển đổi số', 'url' => '/tin-tuc/su-kien/chuyen-doi-so', 'weight' => 2],
          ['title' => 'Đào tạo, Ươm tạo Khởi nghiệp', 'url' => '/tin-tuc/su-kien/dao-tao-uom-tao-khoi-nghiep', 'weight' => 3],
          ['title' => 'Hoạt động Ban Quản lý', 'url' => '/tin-tuc/su-kien/hoat-dong-ban-quan-ly', 'weight' => 4],
          ['title' => 'Tin khác', 'url' => '/tin-tuc/su-kien/tin-tuc-khac', 'weight' => 5],
        ],
      ],
      [
        'title' => 'Xem Thêm',
        'url' => '/xem_them',
        'weight' => 1,
        'children' => [
          ['title' => 'Lịch công tác', 'url' => '/tin-tuc/lich-cong-tac', 'weight' => 0],
          ['title' => 'Thông báo', 'url' => '/tin-tuc/thong-bao', 'weight' => 1],
          ['title' => 'Thông tin báo chí', 'url' => '/tin-tuc/thong-tin-bao-chi', 'weight' => 2],
        ],
      ],
    ],
  ],
  
  // 3. Doanh nghiệp
  [
    'title' => 'Doanh nghiệp',
    'url' => '',
    'weight' => 2,
    'children' => [
      [
        'title' => 'Báo cáo & Dữ liệu',
        'url' => '',
        'weight' => 0,
        'children' => [
          ['title' => 'Báo cáo trực tuyến về DSEZA', 'url' => 'https://maps.dhpiza.vn/login?ReturnUrl=/admin/baocaonhadautu/yeucaubaocao', 'weight' => 0],
          ['title' => 'Báo cáo giám sát và đánh giá đầu tư', 'url' => '/doanh-nghiep/bao-cao-du-lieu/bao-cao-giam-sat-va-danh-gia-dau-tu', 'weight' => 1],
          ['title' => 'Mẫu | Bảng biểu báo cáo', 'url' => '/doanh-nghiep/bao-cao-du-lieu/mau-bang-bieu-bao-cao', 'weight' => 2],
        ],
      ],
      [
        'title' => 'Thông tin Doanh nghiệp',
        'url' => '/doanh-nghiep/thong-tin-doanh-nghiep',
        'weight' => 1,
        'children' => [
          ['title' => 'Thủ tục | Hồ sơ | Dữ liệu môi trường', 'url' => '/doanh-nghiep/thong-tin-doanh-nghiep/thu-tuc-ho-so-du-lieu-moi-truong', 'weight' => 0],
          ['title' => 'Thống kê doanh nghiệp', 'url' => '/doanh-nghiep/thong-tin-doanh-nghiep/thong-ke-doanh-nghiep', 'weight' => 1],
          ['title' => 'Tuyển dụng', 'url' => '/doanh-nghiep/tuyen-dung', 'weight' => 2],
        ],
      ],
    ],
  ],
  
  // 4. Cẩm Nang Đầu Tư
  [
    'title' => 'Cẩm Nang Đầu Tư',
    'url' => '/cam-nang-dau-tu',
    'weight' => 3,
  ],
  
  // 5. Văn bản
  [
    'title' => 'Văn bản',
    'url' => '',
    'weight' => 4,
    'children' => [
      [
        'title' => 'Văn bản Pháp luật',
        'url' => '',
        'weight' => 0,
        'children' => [
          ['title' => 'Văn bản pháp quy trung ương', 'url' => '/van-ban/van-ban-phap-luat/quy-dinh-trung-uong', 'weight' => 0],
          ['title' => 'Văn bản pháp quy địa phương', 'url' => '/van-ban/van-ban-phap-luat/quy-dinh-dia-phuong', 'weight' => 1],
          ['title' => 'Văn bản chỉ đạo điều hành', 'url' => '/van-ban/van-ban-phap-luat/chi-dao-dieu-hanh', 'weight' => 2],
          ['title' => 'Văn bản CCHC', 'url' => '/van-ban/van-ban-phap-luat/cai-cach-hanh-chinh', 'weight' => 3],
        ],
      ],
      [
        'title' => 'Hướng dẫn & Góp ý',
        'url' => '/van-ban/huong-dan-gop-y',
        'weight' => 1,
        'children' => [
          ['title' => 'Văn bản hướng dẫn', 'url' => '/tai-lieu/huong-dan-gop-y/van-ban-huong-dan', 'weight' => 0],
          ['title' => 'Góp ý dự thảo văn bản', 'url' => '/tai-lieu/huong-dan-gop-y/lay-y-kien-du-thao', 'weight' => 1],
        ],
      ],
      [
        'title' => 'Tra cứu văn bản',
        'url' => '',
        'weight' => 2,
        'children' => [
          ['title' => 'Hệ thống tra cứu văn bản', 'url' => '/van-ban', 'weight' => 0],
        ],
      ],
    ],
  ],
  
  // 6. Liên hệ
  [
    'title' => 'Liên hệ',
    'url' => '/lien-he',
    'weight' => 5,
  ],
];

/**
 * Recursive function to create menu items
 */
function createMenuItemsRecursive($items, $menu_name, $parent_plugin_id = NULL, $depth = 0) {
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
      'langcode' => 'vi',
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
        $child_count = createMenuItemsRecursive($item['children'], $menu_name, $menu_link->getPluginId(), $depth + 1);
        $created_count += $child_count;
      }
      
    } catch (Exception $e) {
      echo "{$indent}  ❌ Failed: " . $e->getMessage() . "\n";
    }
  }
  
  return $created_count;
}

$total_created = createMenuItemsRecursive($full_menu_structure, 'main');

echo "\n📊 Summary: Created {$total_created} menu items\n";

// Step 4: Final test
echo "\n🧪 Step 4: Testing restored MAIN menu...\n";
drupal_flush_all_caches();

try {
  $menu_tree = \Drupal::menuTree();
  $parameters = $menu_tree->getCurrentRouteMenuTreeParameters('main');
  $tree = $menu_tree->load('main', $parameters);
  $manipulators = [
    ['callable' => 'menu.default_tree_manipulators:checkAccess'],
    ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
  ];
  $tree = $menu_tree->transform($tree, $manipulators);
  
  echo "🎉 SUCCESS! MAIN menu restored with " . count($tree) . " top-level items:\n";
  foreach ($tree as $item) {
    echo "  - " . $item->link->getTitle();
    if (!empty($item->subtree)) {
      echo " (" . count($item->subtree) . " subitems)";
    }
    echo "\n";
  }
  
} catch (Exception $e) {
  echo "❌ Test failed: " . $e->getMessage() . "\n";
}

echo "\n✅ Full MAIN menu restoration completed!\n"; 