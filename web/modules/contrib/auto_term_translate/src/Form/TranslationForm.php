<?php

namespace Drupal\auto_term_translate\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\auto_node_translate\Translator;
use Drupal\auto_node_translate\AutoNodeTranslateProviderPluginManager;

/**
 * The Translation Form.
 */
class TranslationForm extends FormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The date time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The auto_node_translate translator.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $translator;

  /**
   * The auto_node_translate plugin manager.
   *
   * @var \Drupal\auto_node_translate\AutoNodeTranslateProviderPluginManager
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    LanguageManagerInterface $language_manager,
    ConfigFactoryInterface $config_factory,
    TimeInterface $datetime_time,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    Translator $auto_node_translate_translator,
    AutoNodeTranslateProviderPluginManager $plugin_manager,
  ) {
    $this->languageManager = $language_manager;
    $this->config = $config_factory;
    $this->time = $datetime_time;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->translator = $auto_node_translate_translator;
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('auto_node_translate.translator'),
      $container->get('plugin.manager.auto_node_translate_provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auto_term_translate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $taxonomy_term = NULL) {
    $languages = $this->languageManager->getLanguages();
    $form['translate'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Languages to Translate'),
      '#closed' => FALSE,
      '#tree' => TRUE,
    ];

    foreach ($languages as $language) {
      $languageId = $language->getId();
      if ($languageId !== $taxonomy_term->langcode->value) {
        $label = ($taxonomy_term->hasTranslation($languageId)) ? $this->t('overwrite translation') : $this->t('new translation');
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
    $term = $this->getRouteMatch()->getParameter('taxonomy_term');
    $translations = $form_state->getValues()['translate'];
    foreach ($translations as $lid => $value) {
      if ($value) {
        $this->autoTaxonomyTranslateTerm($term, $lid);
      }
    }
    $form_state->setRedirect('entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()]);
  }

  /**
   * Translates Taxonomy Terms.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   The term to translate.
   * @param mixed $languageId
   *   The language id.
   */
  public function autoTaxonomyTranslateTerm(Term $term, $languageId) {
    $languageFrom = $term->langcode->value;
    $fields = $term->getFields();
    $termTrans = $this->getTranslatedTaxonomy($term, $languageId);
    $excludeFields = $this->translator->getExcludeFields();
    $translatedTypes = $this->translator->getTextFields();
    $config = $this->config->get('auto_node_translate.settings');
    $default_api_id = $config->get('default_api');
    $api = $this->pluginManager->createInstance($default_api_id);
    foreach ($fields as $field) {
      $fieldType = $field->getFieldDefinition()->getType();
      $fieldName = $field->getName();
      if (in_array($fieldType, $translatedTypes) && !in_array($fieldName, $excludeFields)) {
        $translatedValue = $this->translator->translateTextField($field, $fieldType, $api, $languageFrom, $languageId);
        $termTrans->set($fieldName, $translatedValue);
      }
      elseif ($fieldType == 'link') {
        $values = $this->translator->translateLinkField($field, $api, $languageFrom, $languageId);
        $termTrans->set($fieldName, $values);
      }
      elseif ($fieldType == 'entity_reference_revisions') {
        $this->translator->translateParagraphField($field, $api, $languageFrom, $languageId);
      }
      elseif (!in_array($fieldName, $excludeFields)) {
        $termTrans->set($fieldName, $term->get($fieldName)->getValue());
      }
    }
    $term->setNewRevision(TRUE);
    $term->revision_log = $this->t('Automatic translation using @api', ['@api' => $config->get('default_api')]);
    $term->setRevisionCreationTime($this->time->getRequestTime());
    $term->setRevisionUserId($this->currentUser->id());
    $term->save();
  }

  /**
   * Gets or adds translated taxonomy_term.
   *
   * @param mixed $term
   *   The taxonomy_term.
   * @param mixed $languageId
   *   The language id.
   *
   * @return mixed
   *   the translated taxonomy_term.
   */
  public function getTranslatedTaxonomy(&$term, $languageId) {
    return $term->hasTranslation($languageId) ? $term->getTranslation($languageId) : $term->addTranslation($languageId);
  }

}
