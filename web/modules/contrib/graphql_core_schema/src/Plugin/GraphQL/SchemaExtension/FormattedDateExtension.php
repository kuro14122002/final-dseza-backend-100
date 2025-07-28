<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\SchemaExtension;

use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;
use Drupal\graphql_core_schema\TypeAwareSchemaExtensionInterface;

/**
 * A schema extensions for date formatting.
 *
 * @SchemaExtension(
 *   id = "formatted_date",
 *   name = "Formatted Date and Time",
 *   description = "Provides fields to get PHP and Drupal formatted dates.",
 *   schema = "core_composable"
 * )
 */
class FormattedDateExtension extends SdlSchemaExtensionPluginBase implements TypeAwareSchemaExtensionInterface {

  /**
   * {@inheritdoc}
   */
  public function getTypeExtensionDefinition(array $types) {
    if (in_array('FieldItemTypeDaterange', $types)) {
      return $this->loadDefinitionFile('FieldItemTypeDaterange');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    $registry->addFieldResolver(
      'DrupalDateTime',
      'formatted',
      $builder->produce('formatted_date')
        ->map('timestamp', $builder->fromParent())
        ->map('format', $builder->fromArgument('format'))
        ->map('drupalDateFormat', $builder->fromArgument('drupalDateFormat'))
    );

    $registry->addFieldResolver(
      'FieldItemTypeTimestampInterface',
      'formatted',
      $builder->produce('formatted_date')
        ->map('timestamp', $builder->fromParent())
        ->map('format', $builder->fromArgument('format'))
        ->map('drupalDateFormat', $builder->fromArgument('drupalDateFormat'))
    );

    $registry->addFieldResolver(
      'FieldItemTypeDaterange',
      'startDate',
      $builder->produce('formatted_date')
        ->map('timestamp', $builder->fromParent())
        ->map('format', $builder->fromArgument('format'))
        ->map('drupalDateFormat', $builder->fromArgument('drupalDateFormat'))
    );
    $registry->addFieldResolver(
      'FieldItemTypeDaterange',
      'endDate',
      $builder->produce('formatted_date')
        ->map('timestamp', $builder->fromParent())
        ->map('format', $builder->fromArgument('format'))
        ->map('drupalDateFormat', $builder->fromArgument('drupalDateFormat'))
    );

    $registry->addFieldResolver(
      'FieldItemTypeDaterange',
      'formatted',
      $builder->produce('formatted_date_range')
        ->map('start',
          $builder->callback(function (DateRangeItem $value) {
            return $value->get('start_date');
          })
        )
        ->map('end',
          $builder->callback(function (DateRangeItem $value) {
            return $value->get('end_date');
          })
        )
        ->map('format', $builder->fromArgument('format'))
        ->map('drupalDateFormat', $builder->fromArgument('drupalDateFormat'))
    );
  }

}
