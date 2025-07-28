<?php

namespace Drupal\graphql_core_schema;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\graphql_core_schema\GraphQL\CoreComposableValidator;

/**
 * Altering the service definition.
 */
class GraphqlCoreSchemaServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->removeDefinition('graphql.subrequest_subscriber');
    $container->getDefinition('graphql.validator')->setClass(CoreComposableValidator::class);
  }

}
