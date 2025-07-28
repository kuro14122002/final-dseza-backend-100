<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_core_schema\GraphQL\Buffers\SubRequestBuffer;
use Drupal\views\Entity\Render\EntityTranslationRenderTrait;
use Drupal\views\ViewExecutable;
use GraphQL\Deferred;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The data producer to execute views.
 *
 * @DataProducer(
 *   id = "view_executor",
 *   name = @Translation("View Executor"),
 *   description = @Translation("Execute the view and return the results."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Executable")
 *   ),
 *   consumes = {
 *     "viewExecutable" = @ContextDefinition("any",
 *       label = @Translation("View Executable"),
 *     ),
 *     "page" = @ContextDefinition("any",
 *       label = @Translation("Page"),
 *       required = FALSE
 *     ),
 *     "sortBy" = @ContextDefinition("string",
 *       label = @Translation("Sort by"),
 *       required = FALSE
 *     ),
 *     "sortOrder" = @ContextDefinition("string",
 *       label = @Translation("Sort order"),
 *       required = FALSE
 *     ),
 *    "contextualFilters" = @ContextDefinition("string",
 *        label = @Translation("Contextual filters"),
 *        required = FALSE
 *      ),
 *     "filters" = @ContextDefinition("any",
 *       label = @Translation("Filters"),
 *       required = FALSE
 *     ),
 *     "queryParams" = @ContextDefinition("any",
 *       label = @Translation("Query Parameters"),
 *       required = FALSE
 *     ),
 *   }
 * )
 */
class ViewExecutor extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  use EntityTranslationRenderTrait {
    getEntityTranslationRenderer as parentGetEntityTranslationRenderer;
  }

  /**
   * The subrequest buffer.
   *
   * @var \Drupal\graphql_core_schema\GraphQL\Buffers\SubRequestBuffer
   */
  protected $subRequestBuffer;

  /**
   * Language manager.
   *
   * @var LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * View.
   *
   * @var ViewExecutable
   */
  protected ViewExecutable $view;

  /**
   * Entity type ID.
   *
   * @var string
   */
  protected string $entityTypeId;

  /**
   * Entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Entity type repository.
   *
   * @var EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('graphql_core_schema.buffer.subrequest'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository')
    );
  }

  /**
   * The constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param SubRequestBuffer $subRequestBuffer
   *   The sub-request buffer service.
   * @param LanguageManagerInterface $languageManager
   *   Language manager.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param EntityRepositoryInterface $entityRepository
   *   Entity type repository.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    SubRequestBuffer $subRequestBuffer,
    LanguageManagerInterface $languageManager,
    EntityTypeManagerInterface $entityTypeManager,
    EntityRepositoryInterface $entityRepository
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->subRequestBuffer = $subRequestBuffer;
    $this->languageManager = $languageManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRepository = $entityRepository;
  }

  /**
   * The resolver.
   *
   * @param \Drupal\views\ViewExecutable $executable
   *   The view.
   * @param int $page
   *   The page.
   * @param string $sortBy
   *   The sort field.
   * @param string $sortOrder
   *   The sort order.
   * @param array|null $contextualFilters
   *   The contextual filters.
   * @param array|null $filters
   *   The filters.
   * @param array|null $queryParams
   *   The query params.
   *
   * @return \GraphQL\Deferred
   *   The result.
   */
  public function resolve(ViewExecutable $executable, $page, $sortBy, $sortOrder, ?array $contextualFilters = [], ?array $filters = [], ?array $queryParams = []) {
    $page = $page ?? 0;
    $url = $executable->hasUrl() ? $executable->getUrl() : Url::fromRoute('<front>');
    if ($queryParams) {
      $url->setOption('query', $queryParams);
    }
    $exposedInput = $filters ?? [];
    if ($sortBy) {
      $exposedInput['sort_by'] = $sortBy;
    }
    if ($sortOrder) {
      $exposedInput['sort_order'] = $sortOrder;
    }
    if ($contextualFilters) {
      $executable->setArguments($contextualFilters);
    }
    // Needed by the EntityTranslationRenderTrait.
    $this->view = $executable;
    $baseEntityType = $executable->getBaseEntityType();
    if (!empty($baseEntityType)) {
      $this->entityTypeId = $baseEntityType->id();
    }

    $self = $this;
    $resolve = $this->subRequestBuffer->add($url, function () use ($executable, $page, $exposedInput, $self) {
      if ($page) {
        $executable->setCurrentPage($page);
      }
      if (!empty($exposedInput)) {
        $executable->setExposedInput($exposedInput);
      }
      $executable->execute();
      $executable->render();
      $rows = [];
      foreach ($executable->result as $row) {
        // Some views, especially those with search api backend do not have a base entity type on the view.
        // To avoid fatal error, we provide the entity type per rows because it can be different in the same view.
        if (empty($this->entityTypeId)) {
          $this->entityTypeId = $row->_entity->getEntityTypeId();
        }
        $rows[] = $self->getEntityTranslationByRelationship($row->_entity, $row);
      }

      return [
        'rows' => $rows,
        'total_rows' => $executable->getPager()->getTotalItems(),
        'executable' => $executable,
      ];
    });

    return new Deferred(function () use ($resolve) {
      return $resolve();
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityTranslationRenderer() {
    // We need to call the query method on the renderer so that the language
    // alias is set or getLanguage will only return the default language.
    $renderer = $this->parentGetEntityTranslationRenderer();
    $renderer->query($this->view->getQuery());
    return $renderer;
  }

  /**
   * Get the entity type manager.
   *
   * @todo open issue to have this added as an abstract method on the trait.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * Get the entity type repository.
   *
   * @todo open issue to have this added as an abstract method on the trait.
   *
   * @return EntityRepositoryInterface
   *   The entity type repository.
   */
  public function getEntityRepository(): EntityRepositoryInterface {
    return $this->entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId ?? '';
  }

  /**
   * {@inheritdoc}
   */
  protected function getLanguageManager(): LanguageManagerInterface {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getView(): ViewExecutable {
    return $this->view;
  }

}
