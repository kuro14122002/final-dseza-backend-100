<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * A schema extension for rendered fields.
 *
 * @SchemaExtension(
 *   id = "render_field_item",
 *   name = "Render Field Item",
 *   description = "An extension that adds fields to render field items.",
 *   schema = "core_composable"
 * )
 */
class RenderFieldItemExtension extends SdlSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getBaseDefinition() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensionDefinition() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    $registry->addFieldResolver('FieldItemType', 'viewFieldItem',
      $builder->produce('render_field')
        ->map('field', $builder->fromParent())
        ->map('viewMode', $builder->fromArgument('viewMode'))
    );

    $registry->addFieldResolver('FieldItemList', 'viewField',
      $builder->produce('render_field')
        ->map('field', $builder->fromParent())
        ->map('viewMode', $builder->fromArgument('viewMode'))
    );
  }

}
