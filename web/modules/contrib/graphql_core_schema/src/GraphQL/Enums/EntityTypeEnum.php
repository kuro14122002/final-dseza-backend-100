<?php

namespace Drupal\graphql_core_schema\GraphQL\Enums;

use Drupal\graphql_core_schema\EntitySchemaHelper;
use GraphQL\Type\Definition\EnumType;

/**
 * The EntityType enum.
 */
class EntityTypeEnum extends EnumType {

  /**
   * Constructor.
   */
  public function __construct(array $enabledEntityTypes) {
    $entityTypeManager = \Drupal::entityTypeManager();

    $values = [];
    foreach ($enabledEntityTypes as $id) {
      $definition = $entityTypeManager->getDefinition($id);
      $key = strtoupper(EntitySchemaHelper::toSnakeCase($id));
      $values[$key] = [
        'value' => $key,
        'description' => (string) $definition->getLabel(),
      ];
    }
    parent::__construct([
      'name' => 'EntityType',
      'values' => $values,
    ]);
  }

}
