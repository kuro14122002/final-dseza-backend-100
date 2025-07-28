<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\SchemaExtension;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;
use Drupal\graphql_core_schema\CoreComposableResolver;
use Drupal\graphql_core_schema\CoreSchemaExtensionInterface;

/**
 * A schema extension to read field configuration.
 *
 * @SchemaExtension(
 *   id = "field_config",
 *   name = "Field Config Extension",
 *   description = "An extension that provides additional properties for field config entities.",
 *   schema = "core_composable"
 * )
 */
class FieldConfigExtension extends SdlSchemaExtensionPluginBase implements CoreSchemaExtensionInterface {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeDependencies() {
    return ['field_config', 'field_storage_config'];
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

    $registry->addFieldResolver(
      'FieldItemList',
      'fieldConfig',
      $builder->callback(function ($value) {
        if ($value instanceof FieldItemListInterface) {
          return $value->getFieldDefinition();
        }
      })
    );

    $registry->addFieldResolver(
      'FieldConfig',
      'fieldStorageDefinition',
      CoreComposableResolver::resolveCallMethod($builder, 'getFieldStorageDefinition')
    );

    $registry->addFieldResolver(
      'FieldDefinition',
      'name',
      CoreComposableResolver::resolveCallMethod($builder, 'getName')
    );

    $registry->addFieldResolver(
      'FieldDefinition',
      'type',
      CoreComposableResolver::resolveCallMethod($builder, 'getType')
    );

    $registry->addFieldResolver(
      'FieldDefinition',
      'targetEntityTypeId',
      CoreComposableResolver::resolveCallMethod($builder, 'getTargetEntityTypeId')
    );

    $registry->addFieldResolver(
      'FieldDefinition',
      'targetBundle',
      CoreComposableResolver::resolveCallMethod($builder, 'getTargetBundle')
    );

    $registry->addFieldResolver(
      'FieldDefinition',
      'isRequired',
      CoreComposableResolver::resolveCallMethod($builder, 'isRequired')
    );

    $registry->addFieldResolver(
      'FieldDefinition',
      'isReadOnly',
      CoreComposableResolver::resolveCallMethod($builder, 'isReadOnly')
    );

    $registry->addFieldResolver(
      'FieldDefinition',
      'isTranslatable',
      CoreComposableResolver::resolveCallMethod($builder, 'isTranslatable')
    );

    $registry->addFieldResolver(
      'FieldDefinition',
      'uniqueIdentifier',
      CoreComposableResolver::resolveCallMethod($builder, 'getUniqueIdentifier')
    );

    $registry->addFieldResolver(
      'BaseFieldDefinition',
      'description',
      CoreComposableResolver::resolveCallMethod($builder, 'getDescription')
    );

    $registry->addTypeResolver('FieldDefinition', function ($value) {
      if ($value instanceof BaseFieldDefinition) {
        return 'BaseFieldDefinition';
      }
      return 'FieldConfig';
    });
  }

}
