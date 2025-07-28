<?php
/**
 * @file
 * Update menu link translations for the main menu.
 *
 * This script automatically iterates through all Vietnamese menu links
 * in the main menu and creates English translations using a dictionary
 * mapping system. This approach is more maintainable and automatically
 * covers new menu items as they are added.
 *
 * To execute this script in a Lando‑based Drupal 10.5 site run:
 *
 *   lando drush php:script update_menu_translations.php
 *
 * Make sure this file is located in a directory that is scanned by
 * `drush php:script` (typically the Drupal root or a scripts directory).
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Core\Language\LanguageInterface;

/**
 * Dictionary mapping Vietnamese titles to English titles.
 */
$title_dictionary = [
  // Main menu items
  'Giới thiệu' => 'Introduction',
  'Tin tức' => 'News',
  'Sự kiện' => 'Events',
  'Tài liệu' => 'Documents',
  'Doanh nghiệp' => 'Business',
  'Tiện ích' => 'Utilities',
  'Liên hệ' => 'Contact',
  'Home' => 'Home',
  
  // Introduction submenu
  'Giới thiệu chung' => 'General Introduction',
  'General Introduction' => 'General Introduction',
  'Thư ngỏ' => 'Welcome Letter',
  'Tổng quan về Đà Nẵng' => 'About Da Nang City',
  'About Da Nang City' => 'About Da Nang City',
  'Tổng quan về Ban Quản lý' => 'About DSEZA',
  'About DSEZA' => 'About DSEZA',
  'Chức năng, nhiệm vụ' => 'Functions & Responsibilities',
  'Các phòng ban' => 'Departments',
  'Đơn vị trực thuộc' => 'Affiliated Units',
  'Các Khu chức năng' => 'Functional Areas',
  'Thành tựu nổi bật' => 'Outstanding Achievements',
  'Thành tựu nổi bật của Đà Nẵng' => 'Outstanding Achievements of Da Nang',
  
  // Functional Areas
  'Khu công nghệ cao Đà Nẵng' => 'Da Nang High‑Tech Park',
  'Khu thương mại tự do Đà Nẵng' => 'Da Nang Free Trade Zone',
  'Khu CNTT tập trung' => 'Centralized IT Park',
  'Khu công nghiệp Hòa Khánh' => 'Hoa Khanh Industrial Park',
  'Khu công nghiệp Hòa Khánh mở rộng' => 'Hoa Khanh Industrial Park Extension',
  'Khu công nghiệp Đà Nẵng' => 'Da Nang Industrial Park',
  'Khu công nghiệp dịch vụ thủy sản Đà Nẵng' => 'Da Nang Fisheries Service Industrial Park',
  'Khu công nghiệp Hòa Cầm' => 'Hoa Cam Industrial Park',
  'Khu công nghiệp Liên Chiểu' => 'Lien Chieu Industrial Park',
  'Khu công nghiệp Hòa Ninh' => 'Hoa Ninh Industrial Park',
  'Khu công nghiệp Dịch vụ Thủy sản Đà Nẵng' => 'Da Nang Fisheries Service Industrial Park',
  'Các Khu công nghiệp Đà Nẵng' => 'Da Nang Industrial Parks',
  
  // News submenu
  'Tin tức | Sự kiện' => 'News & Events',
  'Xem Thêm' => 'View More',
  'Thông báo' => 'Announcements',
  'Thông tin báo chí' => 'Press Information',
  'Tin nổi bật' => 'Featured News',
  'Đầu tư - Hợp tác Quốc tế' => 'Investment - International Cooperation',
  'Đầu tư - Hợp tác quốc tế' => 'Investment - International Cooperation',
  'Chuyển đổi số' => 'Digital Transformation',
  'Đào tạo và ươm tạo khởi nghiệp' => 'Training and Start‑up Incubation',
  'Đào tạo, Ươm tạo Khởi nghiệp' => 'Training and Start‑up Incubation',
  'Hoạt động Ban Quản lý' => 'Management Board Activities',
  'Tin tức khác' => 'Other News',
  'Tin khác' => 'Other News',
  'Lịch công tác' => 'Work Schedule',
  
  // Business submenu
  'Báo cáo & Dữ liệu' => 'Reports & Data',
  'Báo cáo trực tuyến về DSEZA' => 'Online Reports about DSEZA',
  'Báo cáo giám sát và đánh giá đầu tư' => 'Investment Monitoring and Evaluation Reports',
  'Mẫu | Bảng biểu báo cáo' => 'Forms | Report Templates',
  'Thông tin Doanh nghiệp' => 'Business Information',
  'Thủ tục | Hồ sơ | Dữ liệu môi trường' => 'Procedures | Records | Environmental Data',
  'Thống kê doanh nghiệp' => 'Business Statistics',
  'Tuyển dụng' => 'Recruitment',
  'Hổ trợ doanh nghiệp' => 'Business Support',
  
  // Documents submenu
  'Cẩm Nang Đầu Tư' => 'Investment Handbook',
  'Văn bản' => 'Documents',
  'Văn bản Pháp luật' => 'Legal Documents',
  'Legal documents' => 'Legal Documents',
  'Văn bản pháp quy trung ương' => 'Central Regulatory Documents',
  'Văn bản pháp quy địa phương' => 'Local Regulatory Documents',
  'Văn bản quy định trung ương' => 'Central Regulatory Documents',
  'Văn bản quy định địa phương' => 'Local Regulatory Documents',
  'Văn bản chỉ đạo điều hành' => 'Directive Documents',
  'Văn bản cải cách hành chính' => 'Administrative Reform Documents',
  'Văn bản CCHC' => 'Administrative Reform Documents',
  'Hướng dẫn & Góp ý' => 'Guidance & Feedback',
  'Văn bản hướng dẫn' => 'Guidance Documents',
  'Lấy ý kiến dự thảo văn bản' => 'Draft Document Feedback',
  'Góp ý dự thảo văn bản' => 'Draft Document Feedback',
  'Tra cứu văn bản' => 'Document Search',
  'Hệ thống tra cứu văn bản' => 'Document Search System',
  
  // Applications & Services
  'Ứng dụng & Dịch vụ' => 'Applications & Services',
  'Ứng dụng & dịch vụ' => 'Applications & Services',
  'Administrative reform' => 'Administrative Reform',
  'Bưu chính công ích' => 'Public Postal Services',
  'Dịch vụ công trực tuyến' => 'Online Public Services',
  'Tra cứu hồ sơ' => 'Document Lookup',
  'Đặt lịch hẹn giao dịch trực tuyến' => 'Online Appointment Booking',
  'Đánh giá chất lượng dịch vụ HCC' => 'HCC Service Quality Assessment',
  'Dịch vụ công nổi bật' => 'Featured Public Services',
  
  // For Investors
  'Dành cho nhà đầu tư' => 'For Investors',
  'Thủ tục lĩnh vực đầu tư' => 'Investment Field Procedures',
  'Quy trình lĩnh vực đầu tư' => 'Investment Field Processes',
  'Ngành nghề khuyến khích đầu tư' => 'Investment Encouraged Sectors',
  'Lĩnh vực khuyến khích đầu tư' => 'Investment Encouraged Sectors',
  'Lĩnh vực thu hút đầu tư' => 'Investment Attraction Sectors',
  'Quy hoạch khu chức năng' => 'Functional Area Planning',
  'Đăng ký nộp hồ sơ qua đường bưu điện' => 'Register to submit documents via post',
  'Đăng ký nộp hồ sơ qua bưu điện' => 'Register to submit documents via post',
  'Tra cứu thủ tục hành chính' => 'Look up administrative procedures',
  
  // Investment Environment
  'Môi trường đầu tư' => 'Investment Environment',
  'Cơ sở hạ tầng Khu công nghiệp' => 'Industrial Park Infrastructure',
  'Hạ tầng khu công nghiệp' => 'Industrial Park Infrastructure',
  'Hạ tầng giao thông' => 'Transport Infrastructure',
  'Khoa học công nghệ - Môi trường' => 'Science and Technology - Environment',
  'Logistics' => 'Logistics',
  'Hạ tầng xã hội' => 'Social Infrastructure',
  'Nguồn nhân lực' => 'Human Resources',
  'Cải cách hành chính' => 'Administrative Reform',
  
  // Information & Procedures
  'Thông tin & Thủ tục' => 'Information & Procedures',
  'Thông tin & quy trình' => 'Information & Procedures',
  'Thủ tục hành chính' => 'Administrative Procedures',
  
  // Utilities
  'Hỏi đáp | Góp ý' => 'Q&A | Feedback',
  'FAQ | Comment' => 'Q&A | Feedback',
  'Hỏi đáp' => 'Q&A',
  'Hỏi | Đáp' => 'Q&A',
  'Câu hỏi thường gặp:' => 'Frequently Asked Questions:',
  'Cổng góp ý TP. Đà Nẵng' => 'Da Nang City Feedback Portal',
  'Kết nối doanh nghiệp' => 'Business Connection',
  'Cà phê cùng DSEZA' => 'Coffee with DSEZA',
  'Xem thêm' => 'View More',
  'Dữ liệu chuyên ngành' => 'Specialized Data',
  'Đấu thầu qua mạng' => 'Public Procurement',
  'Mua sắm công' => 'Public Procurement',
  'Làm theo năng lực | Định hướng theo lao động' => 'Work according to ability | Oriented by labour',
  'Làm theo năng lực | Hướng theo lao động' => 'Work according to ability | Oriented by labour',
];

/**
 * Dictionary mapping English URI segments to Vietnamese URI segments.
 * This is used to convert English URLs to Vietnamese URLs for the Vietnamese menu.
 */
$uri_dictionary = [
  // Main menu slugs
  'about' => 'gioi-thieu',
  'news' => 'tin-tuc',
  'events' => 'su-kien',
  'documents' => 'tai-lieu',
  'business' => 'doanh-nghiep',
  'utilities' => 'tien-ich',
  'contact' => 'lien-he',
  'home' => 'trang-chu',
  'introduction' => 'gioi-thieu',
  
  // Introduction submenu slugs
  'general-introduction' => 'gioi-thieu-chung',
  'welcome-letter' => 'thu-ngo',
  'about-da-nang-city' => 'tong-quan-ve-da-nang',
  'about-dseza' => 'tong-quan-ve-ban-quan-ly',
  'functions-responsibilities' => 'chuc-nang-nhiem-vu',
  'departments' => 'cac-phong-ban',
  'affiliated-units' => 'don-vi-truc-thuoc',
  'functional-areas' => 'cac-khu-chuc-nang',
  'outstanding-achievements' => 'thanh-tuu-noi-bat',
  'outstanding-achievements-da-nang' => 'thanh-tuu-noi-bat-cua-da-nang',
  
  // News & Events slugs
  'news-events' => 'tin-tuc-su-kien',
  'view-more' => 'xem-them',
  'announcements' => 'thong-bao',
  'press-information' => 'thong-tin-bao-chi',
  'featured-news' => 'tin-noi-bat',
  'investment-international-cooperation' => 'dau-tu-hop-tac-quoc-te',
  'digital-transformation' => 'chuyen-doi-so',
  'training-incubation-startup' => 'dao-tao-uom-tao-khoi-nghiep',
  'management-board-activities' => 'hoat-dong-ban-quan-ly',
  'other-news' => 'tin-tuc-khac',
  'work-schedule' => 'lich-cong-tac',
  
  // Business submenu slugs
  'reports-data' => 'bao-cao-du-lieu',
  'online-reports-dseza' => 'bao-cao-truc-tuyen-ve-dseza',
  'investment-monitoring-evaluation-reports' => 'bao-cao-giam-sat-va-danh-gia-dau-tu',
  'forms-report-templates' => 'mau-bang-bieu-bao-cao',
  'business-information' => 'thong-tin-doanh-nghiep',
  'environmental-data-procedures-files' => 'thu-tuc-ho-so-du-lieu-moi-truong',
  'business-statistics' => 'thong-ke-doanh-nghiep',
  'recruitment' => 'tuyen-dung',
  'business-support' => 'ho-tro-doanh-nghiep',
  
  // Documents submenu slugs
  'investment-handbook' => 'cam-nang-dau-tu',
  'legal-documents' => 'van-ban-phap-luat',
  'central-regulations' => 'quy-dinh-trung-uong',
  'local-regulations' => 'quy-dinh-dia-phuong',
  'directive-administration' => 'chi-dao-dieu-hanh',
  'administrative-reform' => 'cai-cach-hanh-chinh',
  'guidance-feedback' => 'huong-dan-gop-y',
  'guidance-documents' => 'van-ban-huong-dan',
  'draft-document-feedback' => 'lay-y-kien-du-thao',
  'document-search' => 'tra-cuu-van-ban',
  'document-search-system' => 'he-thong-tra-cuu-van-ban',
  
  // Applications & Services slugs
  'applications-services' => 'ung-dung-dich-vu',
  'public-postal-services' => 'buu-chinh-cong-ich',
  'online-public-services' => 'dich-vu-cong-truc-tuyen',
  'document-lookup' => 'tra-cuu-ho-so',
  'online-appointment-booking' => 'dat-lich-hen-giao-dich-truc-tuyen',
  'hcc-service-quality-assessment' => 'danh-gia-chat-luong-dich-vu-hcc',
  'featured-public-services' => 'dich-vu-cong-noi-bat',
  
  // For Investors slugs
  'for-investors' => 'danh-cho-nha-dau-tu',
  'investment-procedures' => 'thu-tuc-linh-vuc-dau-tu',
  'investment-field-processes' => 'quy-trinh-linh-vuc-dau-tu',
  'investment-encouraged-sectors' => 'nganh-nghe-khuyen-khich-dau-tu',
  'investment-attraction-sectors' => 'linh-vuc-thu-hut-dau-tu',
  'functional-area-planning' => 'quy-hoach-khu-chuc-nang',
  'register-submit-documents-post' => 'dang-ky-nop-ho-so-qua-buu-dien',
  'look-up-administrative-procedures' => 'tra-cuu-thu-tuc-hanh-chinh',
  
  // Investment Environment slugs
  'investment-environment' => 'moi-truong-dau-tu',
  'industrial-park-infrastructure' => 'ha-tang-khu-cong-nghiep',
  'transport-infrastructure' => 'ha-tang-giao-thong',
  'science-technology-environment' => 'khoa-hoc-cong-nghe-moi-truong',
  'logistics' => 'logistics',
  'social-infrastructure' => 'ha-tang-xa-hoi',
  'human-resources' => 'nguon-nhan-luc',
  
  // Information & Procedures slugs
  'information-procedures' => 'thong-tin-thu-tuc',
  'administrative-procedures' => 'thu-tuc-hanh-chinh',
  
  // Utilities slugs
  'qa-feedback' => 'hoi-dap-gop-y',
  'faq' => 'hoi-dap',
  'frequently-asked-questions' => 'cau-hoi-thuong-gap',
  'da-nang-city-feedback-portal' => 'cong-gop-y-tp-da-nang',
  'business-connection' => 'ket-noi-doanh-nghiep',
  'coffee-with-dseza' => 'ca-phe-cung-dseza',
  'specialized-data' => 'du-lieu-chuyen-nganh',
  'public-procurement' => 'dau-thau-qua-mang',
  'work-ability-oriented-labour' => 'lam-theo-nang-luc-dinh-huong-theo-lao-dong',
  
  // Functional Areas slugs
  'da-nang-high-tech-park' => 'khu-cong-nghe-cao-da-nang',
  'da-nang-free-trade-zone' => 'khu-thuong-mai-tu-do-da-nang',
  'centralized-it-park' => 'khu-cntt-tap-trung',
  'hoa-khanh-industrial-park' => 'khu-cong-nghiep-hoa-khanh',
  'hoa-khanh-industrial-park-extension' => 'khu-cong-nghiep-hoa-khanh-mo-rong',
  'da-nang-industrial-park' => 'khu-cong-nghiep-da-nang',
  'da-nang-fisheries-service-industrial-park' => 'khu-cong-nghiep-dich-vu-thuy-san-da-nang',
  'hoa-cam-industrial-park' => 'khu-cong-nghiep-hoa-cam',
  'lien-chieu-industrial-park' => 'khu-cong-nghiep-lien-chieu',
  'hoa-ninh-industrial-park' => 'khu-cong-nghiep-hoa-ninh',
  'da-nang-industrial-parks' => 'cac-khu-cong-nghiep-da-nang',
  
  // Additional comprehensive mappings for URL translation
  'su-kien' => 'su-kien',
  'for-investors' => 'danh-cho-nha-dau-tu',
  'investment-procedures' => 'thu-tuc-linh-vuc-dau-tu',  
  'investment-encouraged-sectors' => 'nganh-nghe-khuyen-khich-dau-tu',
  'investment-environment' => 'moi-truong-dau-tu',
  'transport-infrastructure' => 'ha-tang-giao-thong',
  'science-technology-environment' => 'khoa-hoc-cong-nghe-moi-truong',
  'social-infrastructure' => 'ha-tang-xa-hoi',
  'human-resources' => 'nguon-nhan-luc',
  'legal-documents' => 'van-ban-phap-luat',
  'central-regulations' => 'quy-dinh-trung-uong',
  'local-regulations' => 'quy-dinh-dia-phuong',  
  'directive-administration' => 'chi-dao-dieu-hanh',
  'feedback-guidance' => 'huong-dan-gop-y',
  'guidance-documents' => 'van-ban-huong-dan',
  'draft-document-feedback' => 'lay-y-kien-du-thao',
  'environmental-data-procedures-files' => 'thu-tuc-ho-so-du-lieu-moi-truong',
  'business-statistics' => 'thong-ke-doanh-nghiep',
  'business-information' => 'thong-tin-doanh-nghiep',
  
  // Missing mappings that I see in the logs  
  'training-incubation-startup' => 'dao-tao-uom-tao-khoi-nghiep',
  
  // Complete missing translations for all menu items
  'general-introduction' => 'gioi-thieu-chung',
  'about-da-nang-city' => 'tong-quan-ve-da-nang', 
  'about-dseza' => 'tong-quan-ve-ban-quan-ly',
  'da-nang-fisheries-service-industrial-park' => 'khu-cong-nghiep-dich-vu-thuy-san-da-nang',
  'investment-international-cooperation' => 'dau-tu-hop-tac-quoc-te',
  'other-news' => 'tin-tuc-khac',
  'administrative-reform' => 'cai-cach-hanh-chinh',
  'applications-services' => 'ung-dung-dich-vu',
  'information-procedures' => 'thong-tin-thu-tuc',
  'qa-feedback' => 'hoi-dap-gop-y',
  
  // Additional essential mappings that appear in URLs
  'welcome-letter' => 'thu-ngo',
  'management-board-activities' => 'hoat-dong-ban-quan-ly',
  'training-incubation-startup' => 'dao-tao-uom-tao-khoi-nghiep',
  'work-schedule' => 'lich-cong-tac',
  
  // Complete comprehensive mappings for all URL segments seen in the menu JSON
  'feedback-guidance' => 'huong-dan-gop-y',
  'draft-document-feedback' => 'lay-y-kien-du-thao',
  'guidance-documents' => 'van-ban-huong-dan',
  'directive-administration' => 'chi-dao-dieu-hanh',
  'central-regulations' => 'quy-dinh-trung-uong',
  'local-regulations' => 'quy-dinh-dia-phuong',
  
  // ALL mappings from the JSON that need translation
  'faq' => 'hoi-dap',
  'utilities' => 'tien-ich',
  'node' => 'node', // keep node paths as-is since they're internal Drupal paths
  'vi' => 'vi', // keep language prefix as-is
];

/**
 * Translates URI from English to Vietnamese segments with enhanced logic.
 * This ensures all menu items use Vietnamese URLs.
 *
 * @param string $uri
 *   The original URI.
 * @return string
 *   The URI with Vietnamese segments.
 */
function translate_uri($uri) {
  global $uri_dictionary;
  
  // Handle external URLs - return as is
  if (strpos($uri, 'http') === 0) {
    return $uri;
  }
  
  // Handle route:<nolink> and entity: routes - return as is
  if (strpos($uri, 'route:') === 0 || strpos($uri, 'entity:') === 0) {
    return $uri;
  }
  
  // Handle internal routes
  if (strpos($uri, 'internal:/') === 0) {
    $path = substr($uri, 10); // Remove 'internal:/'
    
    // Handle empty paths
    if (empty($path)) {
      return $uri;
    }
    
    // Full path translation mappings for common patterns
    $full_path_mappings = [
      'general-introduction' => 'gioi-thieu-chung',
      'introduction/general-introduction/welcome-letter' => 'gioi-thieu/gioi-thieu-chung/thu-ngo',
      'introduction/general-introduction/about-da-nang-city' => 'gioi-thieu/gioi-thieu-chung/tong-quan-ve-da-nang',
      'introduction/general-introduction/about-dseza' => 'gioi-thieu/gioi-thieu-chung/tong-quan-ve-ban-quan-ly',
      'about/functional-areas' => 'gioi-thieu/cac-khu-chuc-nang',
      'about/functional-areas/da-nang-high-tech-park' => 'gioi-thieu/cac-khu-chuc-nang/khu-cong-nghe-cao-da-nang',
      'about/functional-areas/da-nang-free-trade-zone' => 'gioi-thieu/cac-khu-chuc-nang/khu-thuong-mai-tu-do-da-nang',
      'about/functional-areas/centralized-it-park' => 'gioi-thieu/cac-khu-chuc-nang/khu-cntt-tap-trung',
      'about/functional-areas/hoa-khanh-industrial-park' => 'gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-khanh',
      'about/functional-areas/hoa-khanh-industrial-park-extension' => 'gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-khanh-mo-rong',
      'about/functional-areas/da-nang-industrial-park' => 'gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-da-nang',
      'about/functional-areas/da-nang-fisheries-service-industrial-park' => 'gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-dich-vu-thuy-san-da-nang',
      'about/functional-areas/hoa-cam-industrial-park' => 'gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-hoa-cam',
      'about/functional-areas/lien-chieu-industrial-park' => 'gioi-thieu/cac-khu-chuc-nang/khu-cong-nghiep-lien-chieu',
      'news/events/investment-international-cooperation' => 'tin-tuc/su-kien/dau-tu-hop-tac-quoc-te',
      'news/events/business' => 'tin-tuc/su-kien/doanh-nghiep',
      'news/events/digital-transformation' => 'tin-tuc/su-kien/chuyen-doi-so',
      'news/events/training-incubation-startup' => 'tin-tuc/su-kien/dao-tao-uom-tao-khoi-nghiep',
      'news/events/management-board-activities' => 'tin-tuc/su-kien/hoat-dong-ban-quan-ly',
      'news/events/other-news' => 'tin-tuc/su-kien/tin-tuc-khac',
      'business/business-information/environmental-data-procedures-files' => 'doanh-nghiep/thong-tin-doanh-nghiep/thu-tuc-ho-so-du-lieu-moi-truong',
      'business/business-information/business-statistics' => 'doanh-nghiep/thong-tin-doanh-nghiep/thong-ke-doanh-nghiep',
      'business/recruitment' => 'doanh-nghiep/tuyen-dung',
      'documents/legal-documents/central-regulations' => 'tai-lieu/van-ban-phap-luat/quy-dinh-trung-uong',
      'documents/legal-documents/local-regulations' => 'tai-lieu/van-ban-phap-luat/quy-dinh-dia-phuong',
      'documents/legal-documents/directive-administration' => 'tai-lieu/van-ban-phap-luat/chi-dao-dieu-hanh',
      'documents/legal-documents/administrative-reform' => 'tai-lieu/van-ban-phap-luat/cai-cach-hanh-chinh',
      'documents/feedback-guidance/guidance-documents' => 'tai-lieu/huong-dan-gop-y/van-ban-huong-dan',
      'documents/feedback-guidance/draft-document-feedback' => 'tai-lieu/huong-dan-gop-y/lay-y-kien-du-thao',
      'news/for-investors' => 'tin-tuc/danh-cho-nha-dau-tu',
      'news/for-investors/investment-procedures' => 'tin-tuc/danh-cho-nha-dau-tu/thu-tuc-linh-vuc-dau-tu',
      'news/for-investors/investment-encouraged-sectors' => 'tin-tuc/danh-cho-nha-dau-tu/nganh-nghe-khuyen-khich-dau-tu',
      'news/investment-environment' => 'tin-tuc/moi-truong-dau-tu',
      'news/investment-environment/transport-infrastructure' => 'tin-tuc/moi-truong-dau-tu/ha-tang-giao-thong',
      'news/investment-environment/science-technology-environment' => 'tin-tuc/moi-truong-dau-tu/khoa-hoc-cong-nghe-moi-truong',
      'news/investment-environment/logistics' => 'tin-tuc/moi-truong-dau-tu/logistics',
      'news/investment-environment/social-infrastructure' => 'tin-tuc/moi-truong-dau-tu/ha-tang-xa-hoi',
      'news/investment-environment/human-resources' => 'tin-tuc/moi-truong-dau-tu/nguon-nhan-luc',
      'news/investment-environment/administrative-reform' => 'tin-tuc/moi-truong-dau-tu/cai-cach-hanh-chinh',
      'utilities/faq' => 'tien-ich/hoi-dap',
      'news/work-schedule' => 'tin-tuc/lich-cong-tac',
    ];
    
    // Try full path translation first
    if (isset($full_path_mappings[$path])) {
      return 'internal:/' . $full_path_mappings[$path];
    }
    
    // Fallback to segment-by-segment translation
    $segments = explode('/', $path);
    foreach ($segments as &$segment) {
      if (isset($uri_dictionary[$segment])) {
        $segment = $uri_dictionary[$segment];
      }
    }
    
    return 'internal:/' . implode('/', $segments);
  }
  
  // Handle direct paths (without internal:/)
  if (strpos($uri, '/') === 0) {
    $path = trim($uri, '/');
    
    // Full path mappings for direct paths
    $full_direct_mappings = [
      'general-introduction' => 'gioi-thieu-chung',
      'cam-nang-dau-tu' => 'cam-nang-dau-tu',
      'lien-he' => 'lien-he',
      'van-ban' => 'van-ban',
      'xem_them' => 'xem_them',
    ];
    
    if (isset($full_direct_mappings[$path])) {
      return '/' . $full_direct_mappings[$path];
    }
    
    // Fallback to segment translation
    $segments = explode('/', $path);
    foreach ($segments as &$segment) {
      if (isset($uri_dictionary[$segment])) {
        $segment = $uri_dictionary[$segment];
      }
    }
    
    return '/' . implode('/', $segments);
  }
  
  return $uri;
}

// Load all Vietnamese menu links from the main menu
$source_links = \Drupal::entityTypeManager()
  ->getStorage('menu_link_content')
  ->loadByProperties([
    'menu_name' => 'main',
    'langcode' => 'vi'
  ]);

$processed_count = 0;
$skipped_count = 0;

foreach ($source_links as $link) {
  $src_title = $link->getTitle();
  
  // Skip if no translation exists for this title
  if (!isset($title_dictionary[$src_title])) {
    \Drupal::logger('menu_translation')->warning('No English translation found for title: @title', ['@title' => $src_title]);
    $skipped_count++;
    continue;
  }
  
  $en_title = $title_dictionary[$src_title];
  $original_uri = $link->link->first()->uri;
  $en_uri = translate_uri($original_uri);
  
  // Create or retrieve the English translation
  $langcode = 'en';
  if (!$link->hasTranslation($langcode)) {
    $translation = $link->addTranslation($langcode);
    \Drupal::logger('menu_translation')->info('Created new English translation for: @title', ['@title' => $src_title]);
  } else {
    $translation = $link->getTranslation($langcode);
    \Drupal::logger('menu_translation')->info('Updated existing English translation for: @title', ['@title' => $src_title]);
  }
  
  // Set the translated title and link
  $translation->set('title', $en_title);
  $translation->set('link', [
    'uri' => $en_uri,
    'title' => '',
    'options' => [],
  ]);
  
  // Save the menu link with its translation
  $link->save();
  $processed_count++;
  
  \Drupal::logger('menu_translation')->notice('Successfully processed menu item: @vi_title -> @en_title (@vi_uri -> @en_uri)', [
    '@vi_title' => $src_title,
    '@en_title' => $en_title,
    '@vi_uri' => $original_uri,
    '@en_uri' => $en_uri,
  ]);
}

print "Menu link translations completed.\n";
print "Processed: {$processed_count} items\n";
print "Skipped: {$skipped_count} items (no translation found)\n";

// Log summary
\Drupal::logger('menu_translation')->notice('Menu translation script completed. Processed: @processed, Skipped: @skipped', [
  '@processed' => $processed_count,
  '@skipped' => $skipped_count,
]);