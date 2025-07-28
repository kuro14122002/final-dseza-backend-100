<?php

namespace Drupal\auto_term_translate\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * The Translation Form.
 */
class BulkTranslationForm extends TranslationForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auto_term_vocabulary_translate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $vocabulary = NULL) {
    $languages = $this->languageManager->getLanguages();
    $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $vocabularyEntity = $storage->load($vocabulary);
    $langcode = $vocabularyEntity->language()->getId();
    $form['translate'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Languages to Translate'),
      '#closed' => FALSE,
      '#tree' => TRUE,
    ];

    foreach ($languages as $language) {
      $languageId = $language->getId();
      if ($languageId !== $langcode) {
        $form['translate'][$languageId] = [
          '#type' => 'checkbox',
          '#title' => $this->t('@lang', [
            '@lang' => $language->getName(),
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
    $values = $form_state->getValues();
    $vocabulary = $this->getRouteMatch()->getParameter('vocabulary');
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $storage->getQuery();
    $query->condition('vid', $vocabulary);
    $query->accessCheck(FALSE);
    $tids = $query->execute();
    $terms = $storage->loadMultiple($tids);
    $translations = $values['translate'];
    $languages = [];
    foreach ($translations as $lid => $value) {
      if ($value) {
        $languages[] = $lid;
      }
    }
    $operations[] = [
      [$this, 'translateTerms'],
      [count($terms), array_values($terms), $languages],
    ];
    $batch = [
      'title' => $this->t('Translating Terms ...'),
      'operations' => $operations,
      'finished' => [$this, 'finished'],
    ];
    batch_set($batch);
    $form_state->setRedirect('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary]);
  }

  /**
   * Batch processor.
   *
   * @param int $total
   *   The total number of items to process.
   * @param array $terms
   *   An array of the terms to translate.
   * @param array $languages
   *   The languages to translate to.
   * @param array $context
   *   The context of the batch operation. It is used to
   *   store the progress of the batch operation.
   */
  public function translateTerms($total, array $terms, array $languages, array &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_number'] = 0;
      $context['sandbox']['max'] = $total;
    }
    $batchSize = 1;
    $current = $context['sandbox']['progress'];
    for ($i = $current; $i < ($current + $batchSize); $i++) {
      $result = '';
      if ($i < $total) {
        $result = $this->t('Term @name translated to:', ['@name' => $terms[$i]->getName()]);
        foreach ($languages as $lid) {
          $this->autoTaxonomyTranslateTerm($terms[$i], $lid);
          $result .= $lid . ' ';
        }
      }
      $context['message'] = $result;
      $context['results'][] = $terms[$i]->id();
      $context['sandbox']['progress']++;
      $context['sandbox']['current_number'] = $i;
    }
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Callback function when the batch process is finished.
   *
   * @param bool $success
   *   Indicates whether the batch has completed successfully.
   * @param array $tids
   *   Ids of the translated terms.
   * @param array $operations
   *   Operations that will be executed.
   */
  public function finished($success, array $tids, array $operations) {
    if ($success) {
      $message = $this->t('@count terms processed.', ['@count' => count($tids)]);
    }
    else {
      $message = $this->t('Finished with an error.');
    }
    $this->messenger()->addStatus($message);
  }

}
