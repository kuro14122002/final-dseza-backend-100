<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\graphql_core_schema\Plugin\GraphQL\DateProducerBase;

/**
 * Parses a date string or timestamp in the given format.
 *
 * @DataProducer(
 *   id = "drupal_date_time",
 *   name = @Translation("Drupal Date Time"),
 *   description = @Translation("Return a DrupalDateTime."),
 *   produces = @ContextDefinition("eny",
 *     label = @Translation("DrupalDateTime Instance.")
 *   ),
 *   consumes = {
 *     "value" = @ContextDefinition("any",
 *       label = @Translation("The date to parse."),
 *     ),
 *     "format" = @ContextDefinition("string",
 *       label = @Translation("The PHP date() format to use when parsing."),
 *     ),
 *   }
 * )
 */
class DrupalDateTimeParse extends DateProducerBase {

  /**
   * The resolver.
   *
   * @param int|string $value
   *   The value to parse.
   * @param string $format
   *   The format to use for parsing the value.
   *
   * @return DrupalDateTime|null
   *   The DrupalDateTime instance.
   */
  public function resolve(string|int $value, string $format) {
    $dateTime = \DateTime::createFromFormat($format, $value);
    if ($dateTime) {
      return DrupalDateTime::createFromDateTime($dateTime);
    }
  }

}
