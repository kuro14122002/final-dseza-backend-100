<?php

// phpcs:disable Drupal.NamingConventions.ValidVariableName.LowerStart

namespace Drupal\Tests\graphql_core_schema\Kernel;

use Drupal\node\Entity\NodeType;

/**
 * Tests schema building with extensions.
 *
 * @group graphql_core_schema
 */
class ComplexSchemaBuilding extends CoreComposableKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->enableModules(['graphql_core_schema_test']);
  }

  /**
   * Test that existing types and interfaces are merged when defined in base definition files.
   */
  public function testExtendedInterface(): void {
    NodeType::create(['type' => 'article'])->save();

    $server = $this
      ->getCoreComposableServerBuilder()
      ->enableValueFields()
      ->enableExtension('extend_node')
      ->enableEntityType('node')
      ->createServer();

    $schema = $this->getSchema($server);
    /** @var \GraphQL\Type\Definition\InterfaceType $Node */
    $Node = $schema->getType('Node');
    $this->assertTrue($Node->hasField('fieldFromExtension'), 'Has field added by a schema extension.');
    $this->assertTrue($Node->hasField('id'), 'Has field added by schema builder.');

    /** @var \GraphQL\Type\Definition\ObjectType $NodeArticle */
    $NodeArticle = $schema->getType('NodeArticle');
    $this->assertTrue($NodeArticle->hasField('regularExtendedField'));
  }

  /**
   * Test that multiple existing types and interfaces are merged when defined in base definition files.
   */
  public function testMultipleExtendedInterface(): void {
    NodeType::create(['type' => 'article'])->save();

    $server = $this
      ->getCoreComposableServerBuilder()
      ->enableValueFields()
      ->enableExtension('extend_node')
      ->enableExtension('extend_multiple')
      ->enableEntityType('node')
      ->enableEntityType('user')
      ->createServer();

    $schema = $this->getSchema($server);

    /** @var \GraphQL\Type\Definition\InterfaceType $Node */
    $Node = $schema->getType('Node');
    $this->assertTrue($Node->hasField('fieldFromExtension'));
    $this->assertTrue($Node->hasField('anotherExtendedField'));
    $this->assertTrue($Node->hasField('id'), 'Has field added by schema builder.');

    /** @var \GraphQL\Type\Definition\InterfaceType $EntityPaywalled */
    $EntityPaywalled = $schema->getType('EntityPaywalled');
    $this->assertNotNull($EntityPaywalled);
    $this->assertTrue($EntityPaywalled->hasField('isPaywalled'), 'Has field added by schema extension.');
    $this->assertTrue($EntityPaywalled->hasField('id'), 'Has field inherited by interface.');
    $this->assertTrue($Node->implementsInterface($EntityPaywalled), 'Implements interface added by schema extension.');
    $this->assertTrue($Node->implementsInterface($schema->getType('EntityLinkable')), 'Implements interface added by implementing other interface.');
    $this->assertTrue($Node->hasField('isPaywalled'), 'Has field added from interface added by schema extension.');

    /** @var \GraphQL\Type\Definition\InterfaceType $User */
    $User = $schema->getType('User');
    $this->assertTrue($User->hasField('fieldOnUser'));
  }

  /**
   * Test extending a non-entity interface.
   */
  public function testExtendNonEntityInterface(): void {
    NodeType::create(['type' => 'article'])->save();

    $server = $this
      ->getCoreComposableServerBuilder()
      ->enableValueFields()
      ->enableExtension('field_item')
      ->enableEntityType('node', ['nid'])
      ->createServer();

    $schema = $this->getSchema($server);

    /** @var \GraphQL\Type\Definition\InterfaceType $Node */
    $Node = $schema->getType('Node');
    $this->assertTrue($Node->hasField('nid'));

    /** @var \GraphQL\Type\Definition\InterfaceType $FieldItemType */
    $FieldItemType = $schema->getType('FieldItemType');
    $this->assertTrue($FieldItemType->hasField('customFieldItemField'));
    $this->assertEquals('NodeArticle', $FieldItemType->getField('customFieldItemField')->getType()->name);
  }

  /**
   * Test that types unrelated to core schema types remain untouched.
   */
  public function testUnrelatedTypes(): void {
    NodeType::create(['type' => 'article'])->save();

    $server = $this
      ->getCoreComposableServerBuilder()
      ->enableValueFields()
      ->enableExtension('fruits')
      ->enableEntityType('node', ['nid'])
      ->createServer();

    $schema = $this->getSchema($server);

    /** @var \GraphQL\Type\Definition\InterfaceType $Fruit */
    $Fruit = $schema->getType('Fruit');
    $this->assertNotNull($Fruit);
    $this->assertTrue($Fruit->hasField('color'));
    $this->assertEquals('A fruit.', $Fruit->description);

    /** @var \GraphQL\Type\Definition\ObjectType $Apple */
    $Apple = $schema->getType('Apple');
    $this->assertNotNull($Apple);
    $this->assertTrue($Apple->hasField('color'));
    $this->assertTrue($Apple->hasField('sweetness'));
    $this->assertEquals('A round fruit.', $Apple->description);

    /** @var \GraphQL\Type\Definition\ObjectType $Banana */
    $Banana = $schema->getType('Banana');
    $this->assertNotNull($Apple);
    $this->assertTrue($Banana->hasField('color'));
    $this->assertTrue($Banana->hasField('brand'));
    $this->assertEquals('An elongated fruit.', $Banana->description);

    /** @var \GraphQL\Type\Definition\EnumType $Color */
    $Color = $schema->getType('Color');
    $this->assertNotNull($Color);
    $this->assertEquals('The color.', $Color->description);
    $values = [
      'RED' => 'Red.',
      'GREEN' => 'Green.',
      'YELLOW' => 'Yellow.',
    ];

    foreach ($values as $name => $description) {
      $value = $Color->getValue($name);
      $this->assertNotNull($value);
      $this->assertEquals($description, $value->description);
    }
  }

}
