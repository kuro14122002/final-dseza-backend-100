<?php

namespace Drupal\graphql_core_schema_test\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * Test schema extension.
 *
 * @SchemaExtension(
 *   id = "extend_multiple",
 *   name = "Extend multiple",
 *   description = "Extend multiple",
 *   schema = "core_composable"
 * )
 */
class ExtendMultiple extends SdlSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();
    $registry->addFieldResolver('Node', 'anotherExtendedField', $builder->fromValue('test'));
    $registry->addFieldResolver('User', 'fieldOnUser', $builder->fromValue('user-value'));
  }

}
