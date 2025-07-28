<?php

namespace Drupal\graphql_core_schema\GraphQL\Enums;

use Drupal\graphql_core_schema\EntitySchemaHelper;
use GraphQL\Type\Definition\EnumType;

/**
 * The DrupalDateFormat enum.
 */
class DrupalDateFormatEnum extends EnumType {

  /**
   * Constructor.
   */
  public function __construct() {
    // Add date format enums.
    $dateFormatStorage = \Drupal::entityTypeManager()->getStorage('date_format');
    $formats = array_values($dateFormatStorage->loadMultiple());
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter */
    $dateFormatter = \Drupal::service('date.formatter');
    $timestamp = 1668433941;

    $values = [];
    foreach ($formats as $format) {
      $id = (string) $format->id();
      $key = strtoupper(EntitySchemaHelper::toSnakeCase($id));
      $description = $dateFormatter->format($timestamp, $id);
      $values[$key] = [
        'value' => $id,
        'description' => $description,
      ];
    }
    parent::__construct([
      'name' => 'DrupalDateFormat',
      'values' => $values,
    ]);
  }

}
