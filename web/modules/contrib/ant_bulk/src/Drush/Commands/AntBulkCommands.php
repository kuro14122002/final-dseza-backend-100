<?php

namespace Drupal\ant_bulk\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ant_bulk\TranslationManager;

/**
 * A Drush command file.
 */
final class AntBulkCommands extends DrushCommands {

  /**
   * Constructs an AntBulkCommands object.
   */
  public function __construct(
    private readonly TranslationManager $translationManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ant_bulk.manager'),
    );
  }

  /**
   * Translates a content type.
   */
  #[CLI\Command(name: 'ant_bulk:translate', aliases: ['anttrans'])]
  #[CLI\Argument(name: 'type', description: 'Content type machine name to translate.')]
  #[CLI\Argument(name: 'language', description: 'Language id translate.')]
  #[CLI\Option(name: 'overwrite', description: 'Overwrite current translations defaults to false')]
  #[CLI\Option(name: 'size', description: 'Batch size. All if omitted')]

  /**
  * Usage.
  */
  #[CLI\Usage(name: 'ant_bulk:translate', description:  "article fr --overwrite=true size=100")]
  public function translate(
    $type,
    $language,
    array $options = [
      'overwrite' => NULL,
      'size' => 0,
    ],
  ) {
    $nodes = $this->translationManager->getNodes([$type], $options['size'], $options['overwrite'], [$language => 1]);
    $operations[] = [
      ['\Drupal\ant_bulk\TranslationManager', 'translateBatchSet'],
      [count($nodes), array_values($nodes), [$language => 1], []],
    ];
    $batch = [
      'title' => 'Translating ...',
      'operations' => $operations,
      'finished' => ['\Drupal\ant_bulk\TranslationManager', 'translateFinished'],
    ];
    batch_set($batch);
    drush_backend_batch_process();
  }

}
