<?php
/**
 * @file
 * Drupal script to create a translated copy of the “Main navigation” menu.
 *
 * This script is intended to be executed via Drush or on the Drupal console
 * within a Lando‑powered Drupal 10.5.1 environment.  It creates a new menu
 * whose machine name is `main1` (label `Main1`) and builds out the same
 * hierarchical structure as the existing Main Navigation menu, but with
 * English labels.  All paths are preserved exactly from the provided
 * Vietnamese JSON structure and the language for each menu link is set to
 * English (`en`).
 *
 * Usage (from the Drupal root):
 *   lando drush php‑script ../path/to/create_main1_menu.php
 */

use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;

// Define the machine name and label for the new menu.  Drupal machine names
// are expected to be lowercase and without spaces.  Although the user
// requested “Main1” as the machine name, Drupal conventions dictate
// lowercase machine names, so we use `main1` internally.  The human
// readable label remains “Main1”.
$menu_id = 'main1';
$menu_label = 'Main1';

// Create the menu only if it does not already exist.
if (!Menu::load($menu_id)) {
  $menu = Menu::create([
    'id' => $menu_id,
    'label' => $menu_label,
    'langcode' => 'en',
  ]);
  $menu->save();
  print "Created menu {$menu_id}.\n";
}
else {
  print "Menu {$menu_id} already exists. Links will be added to it.\n";
}

/**
 * Convert a provided relative or absolute path into a Drupal link array.
 *
 * Drupal differentiates between internal links (paths beginning with `/`)
 * and external links (absolute URLs).  For internal links we prefix the
 * path with `internal:`; for external links we leave the absolute URL
 * untouched.  An empty path is converted into the front page (`/`).
 *
 * @param string $path
 *   The raw path from the JSON definition.
 *
 * @return array
 *   An array suitable for the `link` property on MenuLinkContent entities.
 */
function build_link_array(string $path): array {
  // Default to the front page if no path is provided.
  if ($path === '' || $path === null) {
    $uri = 'internal:/';
  }
  // Treat absolute URLs (http/https) as external URIs.
  elseif (preg_match('/^https?:\/\//i', $path)) {
    $uri = $path;
  }
  // Otherwise assume an internal route/path.
  else {
    // Ensure the path begins with a slash for consistency.
    $normalized = strpos($path, '/') === 0 ? $path : '/' . $path;
    $uri = 'internal:' . $normalized;
  }
  return [
    'uri' => $uri,
    'options' => [],
  ];
}

/**
 * Recursively build menu links based on a nested array definition.
 *
 * This helper creates a MenuLinkContent entity for each item and, if
 * children are present, continues building deeper levels of the menu.  The
 * resulting menu structure mirrors the four‑level hierarchy provided in
 * the original JSON.  Every link is assigned the English langcode and
 * attached to the configured menu.
 *
 * @param array $items
 *   The nested array of menu items.  Each element must contain:
 *     - title (string): The English label for the menu link.
 *     - path (string): The original path (absolute URL or relative path).
 *     - children (array): Optional nested menu items.
 * @param string|null $parent
 *   The menu link identifier of the parent link, or NULL for top level.
 * @param string $menu_id
 *   The machine name of the menu to which links should be added.
 */
function create_menu_links(array $items, ?string $parent, string $menu_id): void {
  foreach ($items as $weight => $item) {
    // Build the link definition.  Use the provided path verbatim and
    // determine whether it’s internal or external.
    $link = build_link_array($item['path']);

    // Create the menu link entity.
    $menu_link = MenuLinkContent::create([
      'title' => $item['title'],
      'link' => $link,
      'menu_name' => $menu_id,
      'weight' => $weight,
      'langcode' => 'en',
      // If a parent UUID is provided, set the parent accordingly.  The
      // format “menu_link_content:UUID” tells Drupal that the parent is
      // another menu_link_content entity.
      'parent' => $parent ? 'menu_link_content:' . $parent : '',
    ]);
    $menu_link->save();

    // Recursively handle children, passing the new link’s UUID.
    if (!empty($item['children'])) {
      create_menu_links($item['children'], $menu_link->uuid(), $menu_id);
    }
  }
}

// Define the hierarchical menu structure with English titles and original
// paths.  Each entry may contain a nested `children` array for
// sub‑navigation.  The weight of each item (its array index) defines its
// order among siblings.  See the user’s JSON for reference.
$menu_structure = [
  // Top level: Introduction
  [
    'title' => 'Introduction',
    'path' => '',
    'children' => [
      [
        'title' => 'General Introduction',
        'path' => '/gioi-thieu-chung',
        'children' => [
          [
            'title' => 'Letter',
            'path' => '/gioi-thieu/gioi-thieu-chung/thu-ngo',
            'children' => [],
          ],
          [
            'title' => 'Overview of Da Nang',
            'path' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-da-nang',
            'children' => [],
          ],
          [
            'title' => 'Overview of the Management Board',
            'path' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-ban-quan-ly',
            'children' => [
              [
                'title' => 'Functions & duties',
                'path' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-ban-quan-ly/chuc-nang-nhiem-vu',
                'children' => [],
              ],
              [
                'title' => 'Departments',
                'path' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-ban-quan-ly/cac-phong-ban',
                'children' => [],
              ],
              [
                'title' => 'Subordinate units',
                'path' => '/gioi-thieu/gioi-thieu-chung/tong-quan-ve-ban-quan-ly/don-vi-truc-thuoc',
                'children' => [],
              ],
            ],
          ],
        ],
      ],
      [
        'title' => 'Functional Areas',
        'path' => '/gioi-thieu/cac-khu-chuc-nang',
        'children' => [
          [
            'title' => 'Da Nang Hi‑Tech Park',
            'path' => '/vi/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghe-cao-da-nang',
            'children' => [],
          ],
          [
            'title' => 'Da Nang Free Trade Zone',
            'path' => '/gioi-thieu/cac-khu-chuc-nang/khu-thuong-mai-tu-do-da-nang',
            'children' => [],
          ],
          [
            'title' => 'Centralized IT Zone',
            'path' => '/gioi-thieu/cac-khu-chuc-nang/khu-cntt-tap-trung',
            'children' => [],
          ],
          [
            'title' => 'Hoa Khanh Industrial Park',
            'path' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-khanh',
            'children' => [],
          ],
          [
            'title' => 'Extended Hoa Khanh Industrial Park',
            'path' => '/vi/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-khanh-mo-rong',
            'children' => [],
          ],
          [
            'title' => 'Da Nang Industrial Park',
            'path' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-da-nang',
            'children' => [],
          ],
          [
            'title' => 'Da Nang Aquatic Services Industrial Park',
            'path' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-dich-vu-thuy-san-da-nang',
            'children' => [],
          ],
          [
            'title' => 'Hoa Cam Industrial Park',
            'path' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-cam',
            'children' => [],
          ],
          [
            'title' => 'Lien Chieu Industrial Park',
            'path' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-lien-chieu',
            'children' => [],
          ],
          [
            'title' => 'Hoa Ninh Industrial Park',
            'path' => '/gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-ninh',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'Significant achievements',
        'path' => '',
        'children' => [
          [
            'title' => 'Significant achievements of Da Nang',
            'path' => 'https://vi.wikipedia.org/wiki/%C4%90%C3%A0_N%E1%BA%B5ng',
            'children' => [],
          ],
        ],
      ],
    ],
  ],
  // Top level: News
  [
    'title' => 'News',
    'path' => '',
    'children' => [
      [
        'title' => 'News & events',
        'path' => '/tin-tuc/su-kien',
        'children' => [
          [
            'title' => 'Investment & international cooperation',
            'path' => '/tin-tuc/su-kien/dau-tu-hop-tac-quoc-te',
            'children' => [],
          ],
          [
            'title' => 'Business',
            'path' => '/tin-tuc/su-kien/doanh-nghiep',
            'children' => [],
          ],
          [
            'title' => 'Digital transformation',
            'path' => '/tin-tuc/su-kien/chuyen-doi-so',
            'children' => [],
          ],
          [
            'title' => 'Training & startup incubation',
            'path' => '/tin-tuc/su-kien/dao-tao-uom-tao-khoi-nghiep',
            'children' => [],
          ],
          [
            'title' => 'Management Board activities',
            'path' => '/tin-tuc/su-kien/hoat-dong-ban-quan-ly',
            'children' => [],
          ],
          [
            'title' => 'Other news',
            'path' => '/tin-tuc/su-kien/tin-tuc-khac',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'More',
        'path' => '/xem_them',
        'children' => [
          [
            'title' => 'Work schedule',
            'path' => '/tin-tuc/lich-cong-tac',
            'children' => [],
          ],
          [
            'title' => 'Notifications',
            'path' => '/tin-tuc/thong-bao',
            'children' => [],
          ],
          [
            'title' => 'Press information',
            'path' => '/tin-tuc/thong-tin-bao-chi',
            'children' => [],
          ],
        ],
      ],
    ],
  ],
  // Top level: Enterprise
  [
    'title' => 'Enterprise',
    'path' => '',
    'children' => [
      [
        'title' => 'Reports & data',
        'path' => '',
        'children' => [
          [
            'title' => 'Online report about DSEZA',
            'path' => 'https://maps.dhpiza.vn/login?ReturnUrl=/admin/baocaonhadautu/yeucaubaocao',
            'children' => [],
          ],
          [
            'title' => 'Investment monitoring & evaluation report',
            'path' => '/doanh-nghiep/bao-cao-du-lieu/bao-cao-giam-sat-va-danh-gia-dau-tu',
            'children' => [],
          ],
          [
            'title' => 'Reporting templates & forms',
            'path' => '/doanh-nghiep/bao-cao-du-lieu/mau-bang-bieu-bao-cao',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'Business information',
        'path' => '/doanh-nghiep/thong-tin-doanh-nghiep',
        'children' => [
          [
            'title' => 'Procedures | documents | environmental data',
            'path' => '/doanh-nghiep/thong-tin-doanh-nghiep/thu-tuc-ho-so-du-lieu-moi-truong',
            'children' => [],
          ],
          [
            'title' => 'Business statistics',
            'path' => '/doanh-nghiep/thong-tin-doanh-nghiep/thong-ke-doanh-nghiep',
            'children' => [],
          ],
          [
            'title' => 'Recruitment',
            'path' => '/doanh-nghiep/tuyen-dung',
            'children' => [],
          ],
        ],
      ],
    ],
  ],
  // Top level: Investment Handbook
  [
    'title' => 'Investment Handbook',
    'path' => '/cam-nang-dau-tu',
    'children' => [],
  ],
  // Top level: Documents
  [
    'title' => 'Documents',
    'path' => '',
    'children' => [
      [
        'title' => 'Legal documents',
        'path' => '',
        'children' => [
          [
            'title' => 'Central legal documents',
            'path' => '/van-ban/van-ban-phap-luat/quy-dinh-trung-uong',
            'children' => [],
          ],
          [
            'title' => 'Local legal documents',
            'path' => '/van-ban/van-ban-phap-luat/quy-dinh-dia-phuong',
            'children' => [],
          ],
          [
            'title' => 'Executive directives',
            'path' => '/van-ban/van-ban-phap-luat/chi-dao-dieu-hanh',
            'children' => [],
          ],
          [
            'title' => 'Administrative reform documents',
            'path' => '/van-ban/van-ban-phap-luat/cai-cach-hanh-chinh',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'Guidance & feedback',
        'path' => '/van-ban/huong-dan-gop-y',
        'children' => [
          [
            'title' => 'Guidance documents',
            'path' => '/tai-lieu/huong-dan-gop-y/van-ban-huong-dan',
            'children' => [],
          ],
          [
            'title' => 'Feedback on draft documents',
            'path' => '/tai-lieu/huong-dan-gop-y/lay-y-kien-du-thao',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'Document lookup',
        'path' => '',
        'children' => [
          [
            'title' => 'Document search system',
            'path' => '/van-ban',
            'children' => [],
          ],
        ],
      ],
    ],
  ],
  // Top level: Administrative reform
  [
    'title' => 'Administrative reform',
    'path' => '',
    'children' => [
      [
        'title' => 'Applications & services',
        'path' => '',
        'children' => [
          [
            'title' => 'Online public service',
            'path' => 'https://egov.danang.gov.vn/dailyDVc',
            'children' => [],
          ],
          [
            'title' => 'Public postal service',
            'path' => 'https://egov.danang.gov.vn/dailyDVc',
            'children' => [
              [
                'title' => 'Online public service',
                'path' => '/vi/node/38',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Record lookup',
            'path' => 'https://dichvucong.danang.gov.vn/vi/',
            'children' => [],
          ],
          [
            'title' => 'Online appointment booking',
            'path' => 'http://49.156.54.87/index.php?option=com_hengio&view=hengioonline&task=formdangkyonline&tmpl=widget',
            'children' => [],
          ],
          [
            'title' => 'Evaluate HCC service quality',
            'path' => 'https://dichvucong.danang.gov.vn/vi/',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'For investors',
        'path' => '/tin-tuc/danh-cho-nha-dau-tu',
        'children' => [
          [
            'title' => 'Investment procedures',
            'path' => '/tin-tuc/danh-cho-nha-dau-tu/thu-tuc-linh-vuc-dau-tu',
            'children' => [
              [
                'title' => 'Investment procedure',
                'path' => '/vi/node/25',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Fields encouraged for investment',
            'path' => '/tin-tuc/danh-cho-nha-dau-tu/nganh-nghe-khuyen-khich-dau-tu',
            'children' => [
              [
                'title' => 'Fields attracting investment',
                'path' => '/vi/node/35',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Functional area planning',
            'path' => '/gioi-thieu/cac-khu-chuc-nang',
            'children' => [
              [
                'title' => 'Functional area planning',
                'path' => '/vi/node/37',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Register to submit documents via post',
            'path' => 'https://egov.danang.gov.vn/dailyDVc',
            'children' => [
              [
                'title' => 'Register to submit documents via post',
                'path' => '/vi/node/36',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Administrative procedure lookup',
            'path' => 'https://dichvucong.danang.gov.vn/vi/',
            'children' => [
              [
                'title' => 'Administrative procedure lookup',
                'path' => '/vi/node/39',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Online public service',
            'path' => 'https://dichvucong.danang.gov.vn/vi/',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'Investment environment',
        'path' => '/tin-tuc/moi-truong-dau-tu',
        'children' => [
          [
            'title' => 'Industrial park infrastructure',
            'path' => '/gioi-thieu/cac-khu-chuc-nang',
            'children' => [
              [
                'title' => 'Industrial park infrastructure',
                'path' => '/vi/node/43',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Transportation infrastructure',
            'path' => '/tin-tuc/moi-truong-dau-tu/ha-tang-giao-thong',
            'children' => [
              [
                'title' => 'Transportation infrastructure',
                'path' => '/vi/node/42',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Science & technology – environment',
            'path' => '/tin-tuc/moi-truong-dau-tu/khoa-hoc-cong-nghe-moi-truong',
            'children' => [
              [
                'title' => 'Science & technology – environment',
                'path' => '/vi/node/49',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Logistics',
            'path' => '/tin-tuc/moi-truong-dau-tu/logistics',
            'children' => [
              [
                'title' => 'Logistics',
                'path' => '/vi/node/44',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Social infrastructure',
            'path' => '/tin-tuc/moi-truong-dau-tu/ha-tang-xa-hoi',
            'children' => [
              [
                'title' => 'Social infrastructure',
                'path' => '/vi/node/45',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Human resources',
            'path' => '/tin-tuc/moi-truong-dau-tu/nguon-nhan-luc',
            'children' => [
              [
                'title' => 'Human resources',
                'path' => '/vi/node/46',
                'children' => [],
              ],
            ],
          ],
          [
            'title' => 'Administrative reform',
            'path' => '/tin-tuc/moi-truong-dau-tu/cai-cach-hanh-chinh',
            'children' => [],
          ],
          [
            'title' => 'Digital transformation',
            'path' => '/tin-tuc/su-kien/chuyen-doi-so',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'Information & processes',
        'path' => '',
        'children' => [
          [
            'title' => 'Administrative procedures',
            'path' => 'https://dichvucong.danang.gov.vn/vi/',
            'children' => [],
          ],
          [
            'title' => 'Administrative reform documents',
            'path' => '/documents/legal-documents/administrative-reform',
            'children' => [],
          ],
        ],
      ],
    ],
  ],
  // Top level: Utilities
  [
    'title' => 'Utilities',
    'path' => '',
    'children' => [
      [
        'title' => 'FAQ & comments',
        'path' => '',
        'children' => [
          [
            'title' => 'Q & A',
            'path' => '/tien-ich/hoi-dap',
            'children' => [],
          ],
          [
            'title' => 'Frequently asked questions',
            'path' => '/tien-ich/cau-hoi-thuong-gap',
            'children' => [],
          ],
          [
            'title' => 'Da Nang City feedback portal',
            'path' => 'https://gopy.danang.gov.vn/',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'Business connection',
        'path' => '',
        'children' => [
          [
            'title' => 'Coffee with DSEZA',
            'path' => 'https://docs.google.com/forms/d/e/1FAIpQLSc7gyKy8ESi7k9Hxja0Mi9YAnWLf_yU3fQPnyzYp9hWGLLREg/viewform',
            'children' => [],
          ],
        ],
      ],
      [
        'title' => 'More',
        'path' => '',
        'children' => [
          [
            'title' => 'Work schedule',
            'path' => '/tin-tuc/lich-cong-tac',
            'children' => [],
          ],
          [
            'title' => 'Sectoral data',
            'path' => '',
            'children' => [],
          ],
          [
            'title' => 'Public procurement',
            'path' => '',
            'children' => [],
          ],
          [
            'title' => 'Follow ability | Labour orientation',
            'path' => 'https://dseza-backend.lndo.site/sites/default/files/2025-07/t%C3%A0i-li%E1%BB%87u-s%E1%BB%AD-d%E1%BB%A5ng.pdf',
            'children' => [],
          ],
        ],
      ],
    ],
  ],
  // Top level: Contact
  [
    'title' => 'Contact',
    'path' => '/lien-he',
    'children' => [],
  ],
];

// Build the menu links based on the defined structure.
create_menu_links($menu_structure, null, $menu_id);

print "Finished creating the menu and links.\n";
