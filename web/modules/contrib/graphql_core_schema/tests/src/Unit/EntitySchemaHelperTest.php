<?php

namespace Drupal\Tests\graphql_core_schema\Unit;

use Drupal\graphql_core_schema\EntitySchemaHelper;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test class.
 */
class EntitySchemaHelperTest extends UnitTestCase {

  /**
   * The enum test cases.
   */
  const ENUM_TEST_CASES = [
    'test' => 'TEST',
    '16_9' => '__16_9',
    '16_9__' => '__16_9__',
    '_16_9__test' => '_16_9__TEST',
    '_test' => '_TEST',
    '_1' => '_1',
    '_1_a' => '_1_A',
    '_16_9_' => '_16_9_',
  ];

  /**
   * Test that enum values are properly encoded.
   */
  public function testEncodeEnumValue() {
    foreach (self::ENUM_TEST_CASES as $value => $expected) {
      $encoded = EntitySchemaHelper::encodeEnumValue($value);
      $this->assertEquals($expected, $encoded);
    }
  }

  /**
   * Test that enum values are properly decoded.
   */
  public function testDecodeEnumValue() {
    foreach (self::ENUM_TEST_CASES as $expected => $value) {
      $decoded = EntitySchemaHelper::decodeEnumValue($value);
      $this->assertEquals($expected, $decoded);
    }
  }

}
