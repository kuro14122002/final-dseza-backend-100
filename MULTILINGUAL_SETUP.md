# Drupal Multilingual Setup Guide

## Overview
Hướng dẫn thiết lập hệ thống đa ngôn ngữ cho Drupal backend với hỗ trợ tiếng Việt (vi) và tiếng Anh (en).

## Languages Supported
- **Vietnamese (vi)** - Default language
- **English (en)** - Secondary language

## Content Types with Translation Support

### Node Types
- `bai_viet` (Articles)
- `event` (Events) 
- `page` (Basic pages)
- `van_ban` (Documents)

### Block Content Types
- `basic` (Basic blocks)

### Media Types  
- `document` (Documents)
- `image` (Images)
- `remote_video` (Remote videos)

### Taxonomy Terms
- All vocabulary terms support translation

### **⚠️ Paragraphs - CRITICAL CONFIGURATION NOTES**

Based on [official Drupal documentation](https://www.drupal.org/docs/contributed-modules/paragraphs/multilingual-paragraphs-configuration), Paragraphs require special handling:

**❌ NEVER DO THIS:**
- **DO NOT** enable translation on the Paragraphs field (Entity reference revisions) on parent entities
- **DO NOT** make paragraph reference fields translatable on Node/Block/etc.

**✅ CORRECT CONFIGURATION:**
- Paragraph **types** themselves must be translatable
- Individual **fields within paragraphs** should be translatable
- Paragraph **reference fields** on parent entities must be NON-translatable

**Why this matters:**
- Enabling translation on paragraph reference fields allows adding new paragraphs during translation
- This creates inconsistencies between source and translated content
- Can cause data integrity issues

### Supported Paragraph Types
- `file_dinh_kem` (File attachments)
- `image_block` (Image blocks)
- `investment_card` (Investment cards)
- `partner_logo` (Partner logos)  
- `rich_text_block` (Rich text)
- `slideshow` (Slideshows)
- `thu_vien_anh` (Image galleries)
- `video_nhung` (Embedded videos)

## Field Translation Settings

### Translatable Fields
Fields marked for translation include:
- Title fields (`title`, `name`)
- Body/description content (`body`, `field_description`)
- Summary fields (`field_summary`)
- Text content (`field_content`, `field_text_content`)
- Alt text and captions for media
- Custom content fields specific to each bundle

### Non-Translatable Fields
- Entity reference fields (except when specifically needed)
- **Paragraph reference fields** (Entity reference revisions)
- File upload fields (files themselves, but descriptions can be translatable)
- Technical fields (created, changed, uid, etc.)

## API Endpoints

### JSON:API with Language Support
All JSON:API endpoints support language-specific requests:

```bash
# Vietnamese content (default)
GET /vi/jsonapi/node/bai_viet
GET /vi/jsonapi/node/event
GET /vi/jsonapi/paragraph/rich_text_block

# English content  
GET /en/jsonapi/node/bai_viet
GET /en/jsonapi/node/event
GET /en/jsonapi/paragraph/rich_text_block
```

### Language Headers
Include `Accept-Language` header for explicit language requests:
```bash
curl -H "Accept-Language: vi" /jsonapi/node/bai_viet
curl -H "Accept-Language: en" /jsonapi/node/bai_viet
```

## Setup Scripts

### Main Configuration Script
```bash
cd backend
lando php scripts/update_multilingual_config.php
```

### Fix Paragraphs Configuration
```bash
cd backend
lando php scripts/fix_paragraphs_multilingual.php
```

### Create Sample Homepage Content
```bash
cd backend
lando php scripts/create_simple_homepage.php
```

### Fix Language Detection
```bash
cd backend
lando php scripts/fix_language_detection.php
```

## Content Management Workflow

### Creating Multilingual Content

1. **Create source content in Vietnamese** (default language)
2. **Add English translation:**
   - Go to content edit page
   - Click "Translate" tab
   - Click "Add" for English
   - Translate all marked fields

### **Managing Paragraphs Translations**

**IMPORTANT**: When translating content with paragraphs:

1. **Source content** (Vietnamese) contains the paragraph structure
2. **Translation** (English) uses the SAME paragraph instances
3. **Only paragraph fields are translated**, not the paragraph references themselves
4. **Cannot add/remove paragraphs** during translation - structure stays identical

This ensures content consistency between languages.

### Working with Translated Paragraphs

```php
// Load a node with paragraphs in specific language
$node = \Drupal::entityTypeManager()
  ->getStorage('node')
  ->load($nid);

// Get translated version
$translated_node = $node->getTranslation('en');

// Access paragraph field (same structure)
$paragraphs = $translated_node->get('field_paragraphs')->referencedEntities();

foreach ($paragraphs as $paragraph) {
  // Each paragraph will be in English if translation exists
  $translated_text = $paragraph->get('field_text_content')->value;
}
```

## Language Negotiation

### URL Structure
- Vietnamese: `https://dseza-backend.lndo.site/vi/`
- English: `https://dseza-backend.lndo.site/en/`

### Detection Methods (in order of priority)
1. **URL prefix** (`/vi/`, `/en/`)
2. **Accept-Language** header
3. **Default language** (Vietnamese)

## Troubleshooting

### Common Issues

**❌ "Cannot translate paragraph field"**
- **Cause**: Paragraph reference field marked as translatable
- **Fix**: Run `fix_paragraphs_multilingual.php` script

**❌ "Paragraphs disappear in translation"**
- **Cause**: Paragraph field configuration incorrect
- **Fix**: Ensure paragraph types are translatable, but reference fields are not

**❌ Language URLs return 404**
- **Cause**: Language negotiation not configured
- **Fix**: Run `fix_language_detection.php` script

**❌ JSON:API returns wrong language**
- **Cause**: Accept-Language header or URL prefix missing
- **Fix**: Include proper language indicators in requests

### Verification Steps

1. **Check language configuration:**
   ```bash
   lando drush config:get language.negotiation
   ```

2. **Verify content translation settings:**
   ```bash
   lando drush config:get language.content_settings.node.bai_viet
   ```

3. **Test paragraph field configuration:**
   - Go to `/admin/config/regional/content-language`
   - Click "Paragraphs" section
   - Verify paragraph types are checked
   - Verify individual fields within paragraphs are marked for translation

4. **Test API endpoints:**
   ```bash
   curl -H "Accept-Language: vi" https://dseza-backend.lndo.site/jsonapi/node/bai_viet
   curl -H "Accept-Language: en" https://dseza-backend.lndo.site/jsonapi/node/bai_viet
   ```

## Performance Considerations

- **Language cache contexts** are automatically applied
- **JSON:API responses** are cached per language
- **Paragraph translations** share the same revision ID for efficiency
- Consider using **BigPipe** module for better multilingual performance

## Maintenance

### Regular Tasks
1. **Clear caches** after configuration changes
2. **Update translations** when source content changes
3. **Monitor paragraph field integrity** - run verification scripts monthly
4. **Test API endpoints** in both languages after major updates

### Backup Considerations
- Export language configuration: `lando drush config:export`
- Backup paragraph field mappings before major updates
- Test restore procedures with multilingual content

---

**Note**: This configuration follows Drupal best practices for multilingual sites with Paragraphs module. Always test changes in development before applying to production. 