<?php

declare(strict_types=1);

namespace Drupal\ant_bulk;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\auto_node_translate\Translator;
use Drupal\node\Entity\Node;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Batch process for translation.
 */
final class TranslationManager {
  use StringTranslationTrait;

  /**
   * The content moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformation|null
   */
  protected $contentModerationInformation = NULL;

  /**
   * Constructs a TranslationManager object.
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $messenger,
  ) {
  }

  /**
   * Set content_moderation_information.
   *
   * @param Drupal\content_moderation\ModerationInformationInterface|null $content_moderation_information
   *   The content moderation information service.
   */
  public function setContentModerationInformation(ModerationInformationInterface|null $content_moderation_information) {
    $this->contentModerationInformation = $content_moderation_information;
  }

  /**
   * Static method to set the batch process.
   *
   * @param int $total
   *   The total number of items to be translated in the batch process.
   * @param array $nodes
   *   The nodes ids.
   * @param array $translations
   *   The languages ids to translate to.
   * @param mixed $workflows
   *   The languages ids to translate to.
   * @param mixed $context
   *   Information about the current batch process.
   */
  public static function translateBatchSet($total, array $nodes, array $translations, $workflows, &$context) {
    $translation_manager = \Drupal::service('ant_bulk.manager');
    $translation_manager->translateBatch($total, $nodes, $translations, $workflows, $context);
  }

  /**
   * Translation batch.
   *
   * @param int $total
   *   The total number of items to be translated in the batch process.
   * @param array $nodes
   *   The nodes ids.
   * @param array $translations
   *   The languages ids to translate to.
   * @param mixed $workflows
   *   The languages ids to translate to.
   * @param mixed $context
   *   Information about the current batch process.
   */
  public function translateBatch($total, array $nodes, array $translations, $workflows, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_number'] = 0;
      $context['sandbox']['max'] = $total;
    }
    $batchSize = 1;
    $current = $context['sandbox']['progress'];
    for ($i = $current; $i < ($current + $batchSize); $i++) {
      if ($i < $total) {
        $node = $this->entityTypeManager->getStorage('node')->load($nodes[$i]);
        $title = $node->getTitle();
        $this->translator->translateNode($node, $translations);
        if ($this->contentModerationInformation && $this->contentModerationInformation->isModeratedEntity($node) && !empty($workflows)) {
          $this->setTranslationsModeratedState($node, $translations, $workflows);
        }
        $context['message'] = $this->t('translated @title with id:@id', [
          '@title' => $title,
          '@id' => $node->id(),
        ]);
        $context['results'][] = $node->id();
        $context['sandbox']['progress']++;
        $context['sandbox']['current_number'] = $i;
      }
    }
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Displays a message indicating the success or failure of the batch.
   *
   * @param bool $success
   *   Indicates whether the translate operation was successful or not.
   * @param mixed $results
   *   Array containing the results of the translate operation.
   * @param mixed $operations
   *   The array of operations that were executed during the translate process.
   */
  public static function translateFinished($success, $results, $operations) {
    $translation_manager = \Drupal::service('ant_bulk.manager');
    if ($success) {
      $message = $translation_manager->t('@count elements processed.', ['@count' => count($results)]);
    }
    else {
      $message = $translation_manager->t('Finished with an error.');
    }
    $translation_manager->messenger->addStatus($message);
  }

  /**
   * Retrieves nodes to be translated.
   *
   * @param array $bundles
   *   The content types of the nodes that you want to retrieve.
   * @param int $batch_size
   *   The number of nodes to retrieve. If empty all nodes will be retrieved.
   * @param bool $overwrite
   *   Boolean that indicates if we want to overwrite current translations.
   * @param array $languages
   *   Array containing languages Ids to translate to.
   *
   * @return array
   *   A list of nodes based on the provided parameters.
   */
  public function getNodes(array $bundles, $batch_size, $overwrite, array $languages): array {
    $languages_ids = $this->getCheckboxSelectedKeys($languages);
    $nodes = $this->getDefaultNodes($bundles);
    if (!$overwrite) {
      $translated_nodes = $this->getTranslatedNodes($bundles, $languages_ids);
      $nodes = array_filter($nodes, function ($node) use ($translated_nodes) {
        return !in_array($node, $translated_nodes);
      });
    }
    if (!empty($batch_size)) {
      $nodes = array_slice($nodes, 0, intval($batch_size));
    }
    return $nodes;
  }

  /**
   * Retrieves all nodes of the specified types.
   *
   * @param array $bundles
   *   An array that contains the node types (bundle names) for which you want
   *   to retrieve the nodes.
   *
   * @return array
   *   An array of node IDs that match the specified node types.
   */
  public function getDefaultNodes(array $bundles): array {
    $nodes = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundles, 'IN')
      ->sort('nid', 'DESC')
      ->execute();
    return $nodes;
  }

  /**
   * Retrieves all nodes of the specified types and languages.
   *
   * @param array $bundles
   *   An array that contains the node types (bundle names) for which you want
   *   to retrieve the nodes.
   * @param array $languages
   *   An array with the language codes for which you want to retrieve
   *   translated nodes.
   *
   * @return array
   *   Returns an array of node IDs that meet the specified conditions.
   */
  private function getTranslatedNodes(array $bundles, array $languages): array {
    $nodes = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundles, 'IN')
      ->condition('langcode', $languages, 'IN')
      ->sort('nid', 'DESC')
      ->execute();
    return $nodes;
  }

  /**
   * Gets the keys of selected checkboxes.
   *
   * @param array $values
   *   An array of checkbox values in the form key => value.
   *   ex: ['article' => 1, 'blog' => 0].
   *
   * @return array
   *   An with the keys of the selected checkboxes.
   */
  public function getCheckboxSelectedKeys(array $values): array {
    $selected_values = array_filter($values, function ($value) {
      return $value == 1;
    });
    $selected_keys = array_keys($selected_values);
    return $selected_keys;
  }

  /**
   * Updates the moderation state of translations.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The original node.
   * @param array $languages
   *   Information about the languages to translate to in the format:
   *   [language_id => to_translate] ex: ['fr => 0, 'pt-pt' => 1].
   * @param array $workflows
   *   Information about the default state for each workflow.
   */
  private function setTranslationsModeratedState(Node $node, array $languages, array $workflows) {
    $workflow = $this->contentModerationInformation->getWorkflowForEntity($node);
    $state_id = $workflows[$workflow->id()]['state'];
    $state = $workflow->getTypePlugin()->getStates()[$state_id];
    foreach ($languages as $languageId => $value) {
      if ($value) {
        $node_trans = $this->getTranslatedNode($node, $languageId);
        $node_trans->set('moderation_state', $state_id);
        $node_trans = $this->entityTypeManager->getStorage('node')->createRevision($node_trans, $state->isDefaultRevisionState());
        $state->isPublishedState() ? $node_trans->setPublished() : $node_trans->setUnpublished();
        $node_trans->save();
      }
    }
    $node->save();
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
  public function getTranslatedNode(&$node, $languageId) {
    return $node->hasTranslation($languageId) ? $node->getTranslation($languageId) : $node->addTranslation($languageId);
  }

}
