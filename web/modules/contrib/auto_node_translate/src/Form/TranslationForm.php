<?php

namespace Drupal\auto_node_translate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\auto_node_translate\Translator;

/**
 * The Translation Form.
 */
class TranslationForm extends FormBase {

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The route service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $route;

  /**
   * The translator service.
   *
   * @var \Drupal\auto_node_translate\Translator
   */
  protected $translator;

  /**
   * Constructs a \Drupal\auto_node_translate\Form\TranslationForm object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config service.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The route service.
   * @param \Drupal\auto_node_translate\Translator $translator
   *   The plugin manager service.
   */
  public function __construct(
    LanguageManagerInterface $language_manager,
    ConfigFactoryInterface $config,
    CurrentRouteMatch $route_match,
    Translator $translator,
  ) {
    $this->languageManager = $language_manager;
    $this->config = $config;
    $this->route = $route_match;
    $this->translator = $translator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('current_route_match'),
      $container->get('auto_node_translate.translator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auto_node_translate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $languages = $this->languageManager->getLanguages();
    $form['translate'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Languages to Translate'),
      '#closed' => FALSE,
      '#tree' => TRUE,
    ];

    foreach ($languages as $language) {
      $languageId = $language->getId();
      if ($languageId !== $node->langcode->value) {
        $label = ($node->hasTranslation($languageId)) ? $this->t('overwrite translation') : $this->t('new translation');
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
    $node = $this->route->getParameter('node');
    $translations = $form_state->getValues()['translate'];
    $this->translator->translateNode($node, $translations);
    $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
  }

}
