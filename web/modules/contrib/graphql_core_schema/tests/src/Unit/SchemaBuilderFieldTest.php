<?php

namespace Drupal\Tests\graphql_core_schema\Unit;

use Drupal\graphql_core_schema\SchemaBuilder\SchemaBuilderField;
use Drupal\Tests\UnitTestCase;
use GraphQL\Language\Parser;

/**
 * Unit test class.
 */
class SchemaBuilderFieldTest extends UnitTestCase {

  /**
   * Test that a field is created correctly from a FieldDefinitionNode.
   */
  public function testCreateFromNode() {
    $sdl = <<<GQL
    type Foobar {
      """
      Index: 0
      """
      field0: String

      """
      Index: 1
      """
      field1: String!

      """
      Index: 2
      """
      field2: [String]

      """
      Index: 3
      """
      field3: [String!]

      """
      Index: 4
      """
      field4: [String!]!

      """
      Index: 5
      """
      field5(
        """
        Description for argument.
        """
        foobar: [String!]!
      ): [String!]!
    }
    GQL;
    /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $node */
    $node = iterator_to_array(Parser::parse($sdl)->definitions->getIterator())[0];

    foreach ($node->fields as $index => $fieldNode) {
      $field = SchemaBuilderField::createFromNode($fieldNode);
      $this->assertEquals('String', $field->type);
      $this->assertEquals('Index: ' . $index, $field->description);
      $this->assertEquals('field' . $index, $field->getName());

      if ($index === 0) {
        $this->assertEmpty($field->typeModifiers);
      }
      elseif ($index === 1) {
        $this->assertEquals(['non-null'], $field->typeModifiers);
      }
      elseif ($index === 2) {
        $this->assertEquals(['list'], $field->typeModifiers);
      }
      elseif ($index === 3) {
        $this->assertEquals(['list', 'non-null'], $field->typeModifiers);
      }
      elseif ($index === 4) {
        $this->assertEquals(['non-null', 'list', 'non-null'], $field->typeModifiers);
      }
      elseif ($index === 5) {
        $this->assertNotEmpty($field->arguments);
        $arg = $field->arguments[0];
        $this->assertEquals('foobar', $arg->getName());
        $this->assertEquals('Description for argument.', $arg->getDescription());
        $this->assertEquals('String', $arg->type);
        $this->assertEquals(['non-null', 'list', 'non-null'], $arg->typeModifiers);
      }
    }
  }

  /**
   * Test that the description is correctly generated.
   */
  public function testGetDescription() {
    $field1 = new SchemaBuilderField('foobar');
    $field1->description('The description.');
    $this->assertEquals('The description.', $field1->getDescription());

    $field2 = new SchemaBuilderField('foobar');
    $field2->description('The description.')->machineName('field_test');
    $this->assertEquals('{field: field_test} The description.', $field2->getDescription());

    $field3 = new SchemaBuilderField('foobar');
    $field3->description('The description.')->valueField();
    $this->assertEquals('{value} The description.', $field3->getDescription());

    $field4 = new SchemaBuilderField('foobar');
    $field4->description('The description.')->valueField()->machineName('field_test');
    $this->assertEquals('{value} {field: field_test} The description.', $field4->getDescription());
  }

}
