<?php

declare(strict_types=1);

namespace Drupal\auto_node_translate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Auto Node Translate settings for this site.
 */
final class MyMemorySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'auto_node_translate_my_memory_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['auto_node_translate.my_memory_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('auto_node_translate.my_memory_settings');
    $form['mm_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $config->get('mm_email'),
      '#description' => $this->t('If you provide an email the limit will be increased from 1000 to 10000 words'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->config('auto_node_translate.my_memory_settings')
      ->set('mm_email', $form_state->getValue('mm_email'))
      ->save();
  }

}
