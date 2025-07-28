<?php

namespace Drupal\graphql_core_schema_test\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * Test schema extension.
 *
 * @SchemaExtension(
 *   id = "field_item",
 *   name = "Field Item",
 *   description = "Field Item",
 *   schema = "core_composable"
 * )
 */
class FieldItem extends SdlSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();
    $registry->addFieldResolver('FieldItemType', 'customFieldItemField', $builder->callback(function ($value) {
      return $value->getEntity();
    }));
  }

}
