<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_core_schema\GraphQL\Buffers\SubRequestBuffer;
use GraphQL\Deferred;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The data producer for local tasks.
 *
 * @DataProducer(
 *   id = "local_tasks",
 *   name = @Translation("Local Tasks"),
 *   description = @Translation("Return the local tasks for an URL."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Local Tasks")
 *   ),
 *   consumes = {
 *     "url" = @ContextDefinition("any",
 *       label = @Translation("Route URL"),
 *     )
 *   }
 * )
 */
class LocalTasks extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

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
      $container->get('plugin.manager.menu.local_task'),
      $container->get('graphql_core_schema.buffer.subrequest'),
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
   * @param \Drupal\Core\Menu\LocalTaskManagerInterface $localTaskManager
   *   The local task manager.
   * @param \Drupal\graphql_core_schema\GraphQL\Buffers\SubRequestBuffer $subRequestBuffer
   *   The subrequest buffer.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected LocalTaskManagerInterface $localTaskManager,
    protected SubRequestBuffer $subRequestBuffer,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * The resolver.
   *
   * @param mixed $url
   *   The url.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $fieldContext
   *   The field context.
   *
   * @return array
   *   The result.
   */
  public function resolve($url, FieldContext $fieldContext) {
    $resolver = $this->subRequestBuffer->add($url, function () use ($url, $fieldContext) {
      $routeName = $url->getRouteName();
      $localTasks = $this->localTaskManager->getLocalTasks($routeName, 0);
      $tabs = $localTasks['tabs'] ?? [];
      $cacheability = $localTasks['cacheability'] ?? NULL;
      if ($cacheability && $cacheability instanceof CacheableDependencyInterface) {
        $fieldContext->addCacheableDependency($cacheability);
      }
      $visible = [];

      foreach ($tabs as $key => $tab) {
        if (Element::isVisibleElement($tab)) {
          $tab['_key'] = $key;
          $visible[] = $tab;
        }
      }

      uasort($visible, [SortArray::class, 'sortByWeightProperty']);
      return $visible;
    });

    return new Deferred($resolver);
  }

}
