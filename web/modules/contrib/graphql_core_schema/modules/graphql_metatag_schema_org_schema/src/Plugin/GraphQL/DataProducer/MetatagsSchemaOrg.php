<?php

declare(strict_types = 1);

namespace Drupal\graphql_metatag_schema_org_schema\Plugin\GraphQL\DataProducer;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_core_schema\GraphQL\Buffers\SubRequestBuffer;
use Drupal\metatag\MetatagManagerInterface;
use GraphQL\Deferred;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A data producer for schema.org metatags.
 *
 * @DataProducer(
 *   id = "metatags_schema_org",
 *   name = @Translation("Metatags (schema.org)"),
 *   description = @Translation("Return the schema.org metatags for a route."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Metatags (schema.org)")
 *   ),
 *   consumes = {
 *     "url" = @ContextDefinition("any",
 *       label = @Translation("Route URL"),
 *     )
 *   }
 * )
 */
class MetatagsSchemaOrg extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Drupal\metatag\MetatagManager definition.
   *
   * @var \Drupal\metatag\MetatagManager
   */
  protected $metatagManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The subrequest buffer.
   *
   * @var \Drupal\graphql_core_schema\GraphQL\Buffers\SubRequestBuffer
   */
  protected $subRequestBuffer;

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
      $container->get('module_handler'),
      $container->get('metatag.manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
      $container->get('current_route_match'),
      $container->get('graphql_core_schema.buffer.subrequest')
    );
  }

  /**
   * Metatags constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\metatag\MetatagManagerInterface $metatagManager
   *   The metatag manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\graphql_core_schema\GraphQL\Buffers\SubRequestBuffer $subRequestBuffer
   *   The sub-request buffer service.
   */
  public function __construct(
    array $configuration,
          $pluginId,
          $pluginDefinition,
    ModuleHandlerInterface $moduleHandler,
    MetatagManagerInterface $metatagManager,
    RouteMatchInterface $routeMatch,
    SubRequestBuffer $subRequestBuffer
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->moduleHandler = $moduleHandler;
    $this->metatagManager = $metatagManager;
    $this->routeMatch = $routeMatch;
    $this->subRequestBuffer = $subRequestBuffer;
  }

  /**
   * The resolver.
   *
   * @param \Drupal\Core\Url $url
   *   The url.
   *
   * @return array
   *   The metatags.
   */
  public function resolve(Url $url) {
    if (!$this->metatagManager) {
      return [];
    }
    $resolver = $this->subRequestBuffer->add($url, function () {
      $tags = metatag_get_tags_from_route();

      // Trigger hook_metatags_attachments_alter().
      // Allow modules to rendered metatags prior to attaching.
      $this->moduleHandler->alter('metatags_attachments', $tags);

      $tags = NestedArray::getValue($tags, ['#attached', 'html_head']) ?: [];

      // Collect tags added by Schema Metatag into structured data array.
      /** @var \Drupal\schema_metatag\SchemaMetatagManagerInterface $schemaMetatagManager */
      $schemaMetatagManager = \Drupal::service('schema_metatag.schema_metatag_manager');
      $items = $schemaMetatagManager::parseJsonld($tags);

      // Turn the structured data array into JSON LD.
      if (count($items) > 0) {
        /** @var Metag $jsonld */
        $jsonld = $schemaMetatagManager::encodeJsonld($items);
        if (!empty($jsonld)) {
          return $jsonld;
        }
      }
      return '';
    });

    return new Deferred(function () use ($resolver) {
      return $resolver();
    });
  }

}
