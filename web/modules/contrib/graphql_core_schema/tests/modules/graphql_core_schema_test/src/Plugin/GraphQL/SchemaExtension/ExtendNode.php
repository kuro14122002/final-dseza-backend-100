<?php

namespace Drupal\graphql_core_schema_test\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * Test schema extension.
 *
 * @SchemaExtension(
 *   id = "extend_node",
 *   name = "Extend Node",
 *   description = "Extend Node",
 *   schema = "core_composable"
 * )
 */
class ExtendNode extends SdlSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();
    $registry->addFieldResolver('Node', 'fieldFromExtension', $builder->fromValue('foobar'));
  }

}
