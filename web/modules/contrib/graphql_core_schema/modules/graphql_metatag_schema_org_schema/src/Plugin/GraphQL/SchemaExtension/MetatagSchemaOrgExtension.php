<?php

namespace Drupal\graphql_metatag_schema_org_schema\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;
use Drupal\graphql_core_schema\CoreSchemaExtensionInterface;

/**
 * The metatag schema extension.
 *
 * @SchemaExtension(
 *   id = "metatags_schema_org",
 *   name = "Metatag (schema.org)",
 *   description = "An extension that provides the schema.org metatag fields.",
 *   schema = "core_composable"
 * )
 */
class MetatagSchemaOrgExtension extends SdlSchemaExtensionPluginBase implements CoreSchemaExtensionInterface {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensionDependencies() {
    return ['routing'];
  }

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    $registry->addFieldResolver('InternalUrl', 'schemaOrgMetatags',
      $builder->produce('metatags_schema_org')->map('url', $builder->fromParent())
    );

    $registry->addFieldResolver('SchemaMetatag', 'json',
      $builder->callback(function ($value) {
        return empty($value) ? NULL : $value;
      })
    );
  }

}
