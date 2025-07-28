<?php

namespace Drupal\Tests\graphql_core_schema\Unit;

use Drupal\graphql_core_schema\SchemaBuilder\SchemaBuilderField;
use Drupal\graphql_core_schema\SchemaBuilder\SchemaBuilderObjectType;
use Drupal\Tests\UnitTestCase;
use GraphQL\Language\Parser;

/**
 * Unit test class.
 */
class SchemaBuilderTypeTest extends UnitTestCase {

  /**
   * Test that a type is created correctly from a ObjectTypeDefinitionNode.
   */
  public function testCreateFromNode() {
    $sdl = <<<GQL
    """
    The type description.
    """
    type Apple implements Fruit {
      """
      A simple description.
      """
      simpleField: String

      """
      The field description.
      """
      complexField(
        """
        Description for argument.
        """
        foobar: [String!]!
      ): [String!]!
    }
    GQL;
    /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $node */
    $node = iterator_to_array(Parser::parse($sdl)->definitions->getIterator())[0];
    $type = SchemaBuilderObjectType::createFromNode($node);

    $this->assertEquals('Apple', $type->getName());
    $this->assertEquals('The type description.', $type->getDescription());
    $this->assertEquals(['Fruit'], $type->interfaces);

    $fields = $type->getFields();
    $this->assertCount(2, $fields);
    $simpleField = $fields['simpleField'];
    $this->assertEquals('simpleField', $simpleField->getName());
    $this->assertEquals('String', $simpleField->type);
    $this->assertEquals('A simple description.', $simpleField->getDescription());

    $complexField = $fields['complexField'];
    $this->assertEquals('complexField', $complexField->getName());
    $this->assertEquals('String', $complexField->type);
    $this->assertEquals('The field description.', $complexField->getDescription());
  }

  /**
   * Test that duplicate fields are not generated.
   */
  public function testDuplicateFields() {
    $type = new SchemaBuilderObjectType('Foobar');
    $type->description('The description.')
      ->addField(
        (new SchemaBuilderField('test'))->type('String'),
      )
      ->addField(
        (new SchemaBuilderField('test'))->type('String')
      )
      ->addField(
        (new SchemaBuilderField('test'))->type('String')
      );

    $fields = $type->getFields();
    $this->assertCount(1, $fields);
  }

  /**
   * Test that duplicate fields are generated if a machine name is provided.
   */
  public function testDuplicateFieldsWithMachineName() {
    $type = new SchemaBuilderObjectType('Foobar');
    $type->description('The description.')
      ->addField(
        (new SchemaBuilderField('field11'))->type('String')->machineName('field_11')
      )
      ->addField(
        (new SchemaBuilderField('field111'))->type('String')->machineName('field_11_1')
      )
      ->addField(
        (new SchemaBuilderField('field11RawField'))->type('String')->machineName('field_1_1')
      )
      ->addField(
        (new SchemaBuilderField('field11'))->type('String')->machineName('field_1_1')->valueField()
      )
      ->addField(
        (new SchemaBuilderField('field11'))->type('String')->machineName('field_11')->valueField()
      );

    $fields = $type->getFields();
    $this->assertCount(5, $fields);
  }

  /**
   * Test that fields are removed.
   */
  public function testKeepFields() {
    $type = new SchemaBuilderObjectType('Foobar');
    $fields = ['one', 'two', 'three', 'four', 'five'];

    foreach ($fields as $fieldName) {
      $type->addField(new SchemaBuilderField($fieldName));
    }

    $type->keepFields(['two', 'five']);

    $this->assertCount(2, $type->getFields());
    $this->assertNotEmpty($type->getFields()['two']);
    $this->assertNotEmpty($type->getFields()['five']);
  }

}
