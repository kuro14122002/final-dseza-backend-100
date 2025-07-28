<?php

namespace Drupal\graphql_core_schema\GraphQL\Enums;

use Drupal\graphql_core_schema\EntitySchemaHelper;
use GraphQL\Type\Definition\EnumType;

/**
 * The Langcode enum.
 */
class LangcodeEnum extends EnumType {

  /**
   * Constructor.
   */
  public function __construct() {
    $languages = \Drupal::languageManager()->getLanguages();

    $values = [];
    foreach ($languages as $language) {
      $key = (string) $language->getId();
      $key = strtoupper(EntitySchemaHelper::toSnakeCase($key));
      $values[$key] = [
        'value' => $language->getId(),
        'description' => $language->getName(),
      ];
    }
    parent::__construct([
      'name' => 'Langcode',
      'values' => $values,
    ]);
  }

}
