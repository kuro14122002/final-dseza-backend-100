<?php

declare(strict_types=1);

namespace Drupal\ant_bulk\Form;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\content_translation\ContentTranslationManager;
use Drupal\workflows\Entity\Workflow;
use Drupal\ant_bulk\TranslationManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides a Auto Node Translate Bulk form.
 */
final class TranslateForm extends FormBase {

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManager
   */
  protected $contentTranslationManager;

  /**
   * The content moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformation|null
   */
  protected $contentModerationInformation;

  /**
   * The translation manager.
   *
   * @var \Drupal\ant_bulk\TranslationManager
   */
  protected $translationManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    TranslationManager $translation_manager,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    ContentTranslationManager $content_translation_manager,
    private readonly ModuleHandlerInterface $moduleHandler,
    $content_moderation_information,
  ) {
    $this->translationManager = $translation_manager;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->contentTranslationManager = $content_translation_manager;
    $this->contentModerationInformation = $content_moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ant_bulk.manager'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('content_translation.manager'),
      $container->get('module_handler'),
      $container->has('content_moderation.moderation_information') ? $container->get('content_moderation.moderation_information') : NULL
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ant_bulk_translate';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL): array {
    $languages = $this->languageManager->getLanguages();
    $form['translate'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Languages to Translate'),
      '#closed' => FALSE,
      '#tree' => TRUE,
    ];
    $default_language_id = $this->languageManager->getDefaultLanguage()->getId();
    unset($languages[$default_language_id]);
    foreach ($languages as $language) {
      $languageId = $language->getId();
      $form['translate'][$languageId] = [
        '#type' => 'checkbox',
        '#title' => $this->t('@lang', [
          '@lang' => $language->getName(),
        ]),
      ];
    }
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $allowed_types = array_filter($types, function ($type) {
      return $this->contentTranslationManager->isEnabled('node', $type);
    }, ARRAY_FILTER_USE_KEY);
    $form['bundles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content types to translate'),
      '#tree' => TRUE,
    ];
    foreach ($allowed_types as $label => $entity) {
      $total = count($this->translationManager->getDefaultNodes([$label]));
      $form['bundles'][$label] = [
        '#type' => 'checkbox',
        '#title' => $entity->label(),
        '#description' => $this->t("Total nodes: @total", [
          '@total' => $total,
        ]) . $this->getTotalTranslatedDescription($label),
      ];
    }
    if ($this->contentModerationInformation) {
      $form['workflow'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Workflows translation state'),
        '#tree' => TRUE,
      ];
      $workflows = Workflow::loadMultipleByType('content_moderation');
      foreach ($workflows as $label => $workflow) {
        $states = $workflow->getTypePlugin()->getStates();
        $form['workflow'][$label] = [
          '#type' => 'fieldset',
          '#title' => $workflow->label(),
        ];
        $form['workflow'][$label]['state'] = [
          '#type' => 'radios',
          '#options' => array_map(function ($state) {
            return $state->id();
          }, $states),
          '#title' => $this->t('Translation state'),
          '#default_value' => reset($states)->id(),
        ];
      }
    }
    $form['batch_size'] = [
      '#title' => $this->t('Batch size'),
      '#type' => 'number',
      '#min' => 0,
      '#description' => $this->t('Leave empty for all.'),
    ];
    $form['overwrite'] = [
      '#title' => $this->t('Overwrite translations'),
      '#type' => 'checkbox',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('translate'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $bundles = $this->translationManager->getCheckboxSelectedKeys($values['bundles']);
    $languages = $this->translationManager->getCheckboxSelectedKeys($values['translate']);
    if (empty($bundles)) {
      $form_state->setErrorByName('bundles', $this->t('Please select at least one content type.'));
    }
    if (empty($languages)) {
      $form_state->setErrorByName('translate', $this->t('Please select at least one translation language.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $bundles = $this->translationManager->getCheckboxSelectedKeys($values['bundles']);
    $languages = $values['translate'];
    $workflows = $values['workflow'] ?? NULL;
    $nodes = $this->translationManager->getNodes($bundles, $values['batch_size'], $values['overwrite'], $languages);
    $this->moduleHandler->invokeAll('ant_bulk_translation_items_alter', [&$nodes]);
    $operations[] = [
      ['\Drupal\ant_bulk\TranslationManager', 'translateBatchSet'],
      [count($nodes), array_values($nodes), $languages, $workflows],
    ];
    $batch = [
      'title' => $this->t('Translating ...'),
      'operations' => $operations,
      'finished' => ['\Drupal\ant_bulk\TranslationManager', 'translateFinished'],
    ];
    batch_set($batch);
  }

  /**
   * Returns the description for the translated nodes count.
   *
   * @param string $bundle
   *   The content type of the node.
   *
   * @return mixed
   *   The formatted information about the total translations for each language.
   */
  private function getTotalTranslatedDescription($bundle) {
    $languages = $this->languageManager->getLanguages();
    $default_language_id = $this->languageManager->getDefaultLanguage()->getId();
    unset($languages[$default_language_id]);
    $description = "";
    foreach ($languages as $id => $language) {
      $total = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle)
        ->condition('langcode', $id)
        ->count()
        ->execute();
      $description .= $this->t("<br> Translated total for @language: @total", [
        '@language' => $language->getName(),
        '@total' => $total,
      ]);
    }
    return $description;
  }

}
