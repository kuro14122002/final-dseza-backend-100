<?php

namespace Drupal\auto_block_translate\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\block_content\Entity\BlockContent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\auto_node_translate\Translator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\auto_node_translate\AutoNodeTranslateProviderPluginManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormBase;
use Drupal\content_translation\ContentTranslationManager;

/**
 * The Translation Form.
 */
class TranslationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly LanguageManagerInterface $languageManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ContentTranslationManager $contentTranslationManager,
    private readonly ConfigFactoryInterface $config,
    private readonly AutoNodeTranslateProviderPluginManager $pluginManager,
    private readonly TimeInterface $time,
    private readonly AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('auto_node_translate.translator'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('content_translation.manager'),
      $container->get('config.factory'),
      $container->get('plugin.manager.auto_node_translate_provider'),
      $container->get('datetime.time'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auto_block_translate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $block_content = NULL) {
    $languages = $this->languageManager->getLanguages();
    $form['translate'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Languages to Translate'),
      '#closed' => FALSE,
      '#tree' => TRUE,
    ];

    foreach ($languages as $language) {
      $languageId = $language->getId();
      if ($languageId !== $block_content->langcode->value) {
        $label = ($block_content->hasTranslation($languageId)) ? $this->t('overwrite translation') : $this->t('new translation');
        $form['translate'][$languageId] = [
          '#type' => 'checkbox',
          '#title' => $this->t('@lang (@label)', [
            '@lang' => $language->getName(),
            '@label' => $label,
          ]),
        ];
      }
    }
    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Translate'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config->get('auto_node_translate.settings');
    if (empty($config->get('default_api'))) {
      $form_state->setError($form['translate'], $this->t('Error, translation API is not configured!'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $block_content = $this->getRouteMatch()->getParameter('block_content');
    $translations = $form_state->getValues()['translate'];
    $this->autoBlockTranslateBlock($block_content, $translations);
    $form_state->setRedirect('entity.block_content.canonical', ['block_content' => $block_content->id()]);
  }

  /**
   * Translates block_content.
   *
   * @param \Drupal\block_content\Entity\BlockContent $block_content
   *   The block_content to translate.
   * @param mixed $translations
   *   The translations array.
   */
  public function autoBlockTranslateBlock(BlockContent $block_content, $translations) {
    $languageFrom = $block_content->langcode->value;
    $fields = $block_content->getFields();
    $excludeFields = $this->translator->getExcludeFields();
    $translatedTypes = $this->translator->getTextFields();
    $config = $this->config->get('auto_node_translate.settings');
    $default_api_id = $config->get('default_api');
    $api = $this->pluginManager->createInstance($default_api_id);
    foreach ($translations as $languageId => $value) {
      if ($value) {
        $block_content_trans = $this->getTranslatedBlock($block_content, $languageId);
        foreach ($fields as $field) {
          $fieldType = $field->getFieldDefinition()->getType();
          $fieldName = $field->getName();

          if (in_array($fieldType, $translatedTypes) && !in_array($fieldName, $excludeFields)) {
            $translatedValue = $this->translator->translateTextField($field, $fieldType, $api, $languageFrom, $languageId);
            $block_content_trans->set($fieldName, $translatedValue);
          }
          elseif ($fieldType == 'link') {
            $values = $this->translator->translateLinkField($field, $api, $languageFrom, $languageId);
            $block_content_trans->set($fieldName, $values);
          }
          elseif ($fieldType == 'entity_reference_revisions') {
            // Process later.
          }
          elseif (!in_array($fieldName, $excludeFields)) {
            $block_content_trans->set($fieldName, $block_content->get($fieldName)->getValue());
          }
        }
      }
    }

    foreach ($fields as $field) {
      $fieldType = $field->getFieldDefinition()->getType();
      if ($fieldType == 'entity_reference_revisions') {
        $this->translator->translateParagraphField($field, $api, $languageFrom, $translations);
      }
    }

    $block_content->setNewRevision(TRUE);
    $block_content->revision_log = $this->t('Automatic translation using @api', ['@api' => $config->get('default_api')]);
    $block_content->setRevisionCreationTime($this->time->getRequestTime());
    $block_content->setRevisionUserId($this->currentUser->id());
    $block_content->save();
  }

  /**
   * Gets or adds translated block_content.
   *
   * @param mixed $block_content
   *   The block_content.
   * @param mixed $languageId
   *   The language id.
   *
   * @return mixed
   *   the translated block_content.
   */
  public function getTranslatedBlock(&$block_content, $languageId) {
    return $block_content->hasTranslation($languageId) ? $block_content->getTranslation($languageId) : $block_content->addTranslation($languageId);
  }

}
