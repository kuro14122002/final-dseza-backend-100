<?php

namespace Drupal\graphql_core_schema_test\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * Test schema extension.
 *
 * @SchemaExtension(
 *   id = "fruits",
 *   name = "fruits",
 *   description = "fruits",
 *   schema = "core_composable"
 * )
 */
class Fruits extends SdlSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
  }

}
