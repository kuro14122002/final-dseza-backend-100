<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;
use Drupal\graphql_core_schema\CoreSchemaExtensionInterface;

/**
 * A schema extension for breadcrumbs.
 *
 * @SchemaExtension(
 *   id = "breadcrumb",
 *   name = "Breadcrumb",
 *   description = "An extension that provides breadcrumbs.",
 *   schema = "core_composable"
 * )
 */
class BreadcrumbExtension extends SdlSchemaExtensionPluginBase implements CoreSchemaExtensionInterface {

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
    $registry->addFieldResolver(
      'InternalUrl',
      'breadcrumb',
      $builder->produce('breadcrumb')->map('url', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'Breadcrumb',
      'title',
      $builder->produce('link_title')->map('link', $builder->fromParent())
    );

    $registry->addFieldResolver(
      'Breadcrumb',
      'url',
      $builder->produce('link_url')->map('link', $builder->fromParent())
    );
  }

}
