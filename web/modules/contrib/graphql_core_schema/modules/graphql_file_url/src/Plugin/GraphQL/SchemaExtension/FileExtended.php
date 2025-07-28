<?php

namespace Drupal\graphql_file_url\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;
use Drupal\graphql_core_schema\CoreSchemaExtensionInterface;

/**
 * A schema extension for config pages.
 *
 * @SchemaExtension(
 *   id = "graphql_file_url",
 *   name = "File entity extension",
 *   description = "An extension that provides file urls.",
 *   schema = "core_composable"
 * )
 */
class FileExtended extends SdlSchemaExtensionPluginBase implements CoreSchemaExtensionInterface {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeDependencies() {
    return ['file'];
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensionDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    $registry->addFieldResolver('File', 'urlAbsolute', $builder->compose(
      $builder->callback(function ($file) {
        return $file->createFileUrl(FALSE);
      })
    ));

    $registry->addFieldResolver('File', 'urlRelative', $builder->compose(
      $builder->callback(function ($file) {
        return $file->createFileUrl(TRUE);
      })
    ));

  }

}
