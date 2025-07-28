<?php

declare(strict_types=1);

namespace Drupal\auto_node_translate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Translation helpers.
 */
final class Translator {
  use StringTranslationTrait;

  /**
   * Constructs a Translator object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly AccountProxyInterface $currentUser,
    private readonly ConfigFactoryInterface $config,
    private readonly AutoNodeTranslateProviderPluginManager $pluginManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Translates node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to translate.
   * @param mixed $translations
   *   The translations array.
   */
  public function translateNode(Node $node, $translations) {
    $languageFrom = $node->langcode->value;
    $fields = $node->getFields();
    $excludeFields = $this->getExcludeFields();
    $translatedTypes = $this->getTextFields();
    $config = $this->config->get('auto_node_translate.settings');
    $default_api_id = $config->get('default_api');
    $api = $this->pluginManager->createInstance($default_api_id);
    foreach ($translations as $languageId => $value) {
      if ($value) {
        $node_trans = $this->getTransledNode($node, $languageId);
        foreach ($fields as $field) {
          $fieldType = $field->getFieldDefinition()->getType();
          $fieldName = $field->getName();
          $isTranslatable = $field->getFieldDefinition()->isTranslatable();

          if (in_array($fieldType, $translatedTypes) && !in_array($fieldName, $excludeFields)) {
            if (!$isTranslatable) {
              continue;
            }
            $translatedValue = $this->translateTextField($field, $fieldType, $api, $languageFrom, $languageId);
            $node_trans->set($fieldName, $translatedValue);
          }
          elseif ($fieldType == 'link') {
            if (!$isTranslatable) {
              continue;
            }
            $values = $this->translateLinkField($field, $api, $languageFrom, $languageId);
            $node_trans->set($fieldName, $values);
          }
          elseif ($fieldType == 'entity_reference_revisions') {
            // Process later.
          }
          elseif (!in_array($fieldName, $excludeFields)) {
            $node_trans->set($fieldName, $node->get($fieldName)->getValue());
          }
        }
      }
    }

    foreach ($fields as $field) {
      $fieldType = $field->getFieldDefinition()->getType();
      if ($fieldType == 'entity_reference_revisions') {
        $this->translateParagraphField($field, $api, $languageFrom, $translations);
      }
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage($this->t('Automatica translation using @api', ['@api' => $config->get('default_api')]));
    $node->setRevisionCreationTime($this->time->getRequestTime());
    $node->setRevisionUserId($this->currentUser->id());
    $node->save();
  }

  /**
   * Translates paragraph.
   *
   * @param mixed $paragraph
   *   The paragraph to translate.
   * @param mixed $api
   *   The api to use.
   * @param mixed $languageFrom
   *   The language from.
   * @param mixed $translations
   *   The translations array.
   */
  public function translateParagraph($paragraph, $api, $languageFrom, $translations) {
    $excludeFields = $this->getExcludeFields();
    $translatedTypes = $this->getTextFields();
    $fields = $paragraph->getFields();
    foreach ($translations as $languageId => $value) {
      if ($value) {
        $paragraph_trans = $paragraph->hasTranslation($languageId) ? $paragraph->getTranslation($languageId) : $paragraph->addTranslation($languageId);
        foreach ($fields as $field) {
          $fieldType = $field->getFieldDefinition()->getType();
          $fieldName = $field->getName();
          $isTranslatable = $field->getFieldDefinition()->isTranslatable();

          if (in_array($fieldType, $translatedTypes) && !in_array($fieldName, $excludeFields)) {
            if (!$isTranslatable) {
              continue;
            }
            $translatedValue = $this->translateTextField($field, $fieldType, $api, $languageFrom, $languageId);
            $paragraph_trans->set($fieldName, $translatedValue);
          }
          elseif ($fieldType == 'link') {
            if (!$isTranslatable) {
              continue;
            }
            $values = $this->translateLinkField($field, $api, $languageFrom, $languageId);
            $paragraph_trans->set($fieldName, $values);
          }
          elseif ($fieldType == 'entity_reference_revisions') {
            // Process later.
          }
          elseif (!in_array($fieldName, $excludeFields)) {
            $paragraph_trans->set($fieldName, $paragraph->get($fieldName)->getValue());
          }
        }
      }
    }

    foreach ($fields as $field) {
      $fieldType = $field->getFieldDefinition()->getType();
      if ($fieldType == 'entity_reference_revisions') {
        $this->translateParagraphField($field, $api, $languageFrom, $translations);
      }
    }
    $paragraph->save();
  }

  /**
   * Translates paragraph field.
   *
   * @param mixed $field
   *   The field to translate.
   * @param mixed $api
   *   The api to use.
   * @param mixed $languageFrom
   *   The language from.
   * @param mixed $translations
   *   The translations array.
   */
  public function translateParagraphField($field, $api, $languageFrom, $translations) {
    $targetParagraphs = $field->getValue();
    foreach ($targetParagraphs as $target) {
      $paragraph = $this->entityTypeManager->getStorage('paragraph')->load($target['target_id'], $target['target_revision_id']);
      $this->translateParagraph($paragraph, $api, $languageFrom, $translations);
    }
  }

  /**
   * Translates text field.
   *
   * @param mixed $field
   *   The field to translate.
   * @param string $fieldType
   *   The field type.
   * @param mixed $api
   *   The api to use.
   * @param mixed $languageFrom
   *   The language from.
   * @param mixed $languageId
   *   The language id.
   */
  public function translateTextField($field, $fieldType, $api, $languageFrom, $languageId) {
    $translatedValue = [];
    $values = $field->getValue();
    $fieldDefinition = $field->getFieldDefinition()->getFieldStorageDefinition();
    $max_length = $fieldDefinition->getSettings()['max_length'] ?? 255;

    foreach ($values as $key => $text) {
      if (!empty($text['value'])) {
        $info = [
          "field" => $field,
          "from" => $languageFrom,
          "to" => $languageId,
        ];
        $textToTranslate = $text['value'];
        $this->moduleHandler->invokeAll('auto_node_translate_translation_alter',
          [
            &$textToTranslate,
            &$info,
          ]
        );
        $translatedText = $api->translate($textToTranslate, $languageFrom, $languageId);
        if (in_array($fieldType, ['string', 'text']) && (mb_strlen($translatedText) > $max_length)) {
          $translatedText = mb_substr($translatedText, 0, $max_length);
        }
        $translatedValue[$key]['value'] = $translatedText;
        if (isset($text['format'])) {
          $translatedValue[$key]['format'] = $text['format'];
        }
      }
      else {
        $translatedValue[$key] = [];
      }
    }

    return $translatedValue;
  }

  /**
   * Translates link field.
   *
   * @param mixed $field
   *   The field to translate.
   * @param mixed $api
   *   The api to use.
   * @param mixed $languageFrom
   *   The language from.
   * @param mixed $languageId
   *   The language id.
   */
  public function translateLinkField($field, $api, $languageFrom, $languageId) {
    $values = $field->getValue();
    foreach ($values as $key => $link) {
      if (!empty($link['title'])) {
        $info = [
          "field" => $field,
          "from" => $languageFrom,
          "to" => $languageId,
        ];
        $textToTranslate = $link['title'];
        $this->moduleHandler->invokeAll('auto_node_translate_translation_alter',
        [
          &$textToTranslate,
          &$info,
        ]
        );
        $translatedText = $api->translate($textToTranslate, $languageFrom, $languageId);
        $values[$key]['title'] = $translatedText;
      }
    }
    return $values;
  }

  /**
   * Returns excluded fields.
   */
  public function getExcludeFields() : array {
    return [
      'langcode',
      'parent_id',
      'parent_type',
      'parent_field_name',
      'default_langcode',
      'id',
      'uuid',
      'revision_id',
      'type',
      'status',
      'created',
      'behavior_settings',
      'revision_default',
      'revision_translation_affected',
      'content_translation_source',
      'content_translation_outdated',
      'content_translation_changed',
    ];
  }

  /**
   * Returns text fields.
   */
  public function getTextFields() : array {
    return [
      'string',
      'string_long',
      'text',
      'text_long',
      'text_with_summary',
    ];
  }

  /**
   * Gets or adds translated node.
   *
   * @param mixed $node
   *   The node.
   * @param mixed $languageId
   *   The language id.
   *
   * @return mixed
   *   the translated node.
   */
  public function getTransledNode(&$node, $languageId) {
    return $node->hasTranslation($languageId) ? $node->getTranslation($languageId) : $node->addTranslation($languageId);
  }

}
