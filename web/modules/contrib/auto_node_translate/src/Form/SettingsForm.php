<?php

declare(strict_types=1);

namespace Drupal\auto_node_translate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\auto_node_translate\AutoNodeTranslateProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Auto Node Translate settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * The plugin manager service.
   *
   * @var \Drupal\auto_node_translate\AutoNodeTranslateProviderPluginManager
   */
  protected $pluginManager;

  /**
   * Constructs a \Drupal\auto_node_translate\Form\ConfigForm object.
   *
   * @param \Drupal\auto_node_translate\AutoNodeTranslateProviderPluginManager $plugin_manager
   *   The plugin manager service.
   */
  public function __construct(AutoNodeTranslateProviderPluginManager $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.auto_node_translate_provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'auto_node_translate_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['auto_node_translate.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $translators = $this->pluginManager->getDefinitions();
    $options['_'] = $this->t('Select a provider');
    $default_api = $this->config('auto_node_translate.settings')->get('default_api');
    foreach ($translators as $id => $info) {
      $options[$id] = $info['label'];
    }
    $form['default_api'] = [
      '#type' => 'select',
      '#title' => $this->t('Providers'),
      '#options' => $options,
      '#default_value' => $default_api,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('auto_node_translate.settings')
      ->set('default_api', $form_state->getValue('default_api'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
