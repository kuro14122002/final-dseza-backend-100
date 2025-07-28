<?php

// phpcs:disable Drupal.NamingConventions.ValidVariableName.LowerStart

namespace Drupal\Tests\graphql_core_schema\Kernel\SchemaExtension;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\graphql_core_schema\Kernel\CoreComposableKernelTestBase;
use GraphQL\Server\OperationParams;

/**
 * Tests the image extension.
 *
 * @group graphql_core_schema
 */
class ImageExtensionTest extends CoreComposableKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->enableModules(['file', 'image']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('image_style');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Test that an exception is thrown when no image styles exist.
   */
  public function testNoImageStyles(): void {
    $server = $this
      ->getCoreComposableServerBuilder()
      ->enableValueFields()
      ->enableExtension('image')
      ->enableEntityType('file')
      ->createServer();

    $this->expectException(\Exception::class);
    $this->getSchema($server);
  }

  /**
   * Test that the image style enum is properly generated.
   */
  public function testImageStyleEnum(): void {
    $styles = [
      'test' => 'A test',
      '16_9_desktop' => '16/9 (Desktop)',
      '_1_1_mobile' => '1/1 (Mobile)',
    ];
    foreach ($styles as $name => $label) {
      ImageStyle::create(['name' => $name, 'label' => $label])->save();
    }

    $server = $this
      ->getCoreComposableServerBuilder()
      ->enableValueFields()
      ->enableExtension('image')
      ->enableEntityType('file')
      ->createServer();

    $schema = $this->getSchema($server);

    /** @var \GraphQL\Type\Definition\EnumType $ImageStyleId */
    $ImageStyleId = $schema->getType('ImageStyleId');

    $style = $ImageStyleId->getValue('TEST');
    $this->assertEquals('{test} A test', $style->description);

    $style = $ImageStyleId->getValue('__16_9_DESKTOP');
    $this->assertEquals('{16_9_desktop} 16/9 (Desktop)', $style->description);

    $style = $ImageStyleId->getValue('_1_1_MOBILE');
    $this->assertEquals('{_1_1_mobile} 1/1 (Mobile)', $style->description);
  }

  /**
   * Test that the image style is properly resolved.
   */
  public function testResolveImageStyle(): void {
    NodeType::create(['type' => 'article'])->save();

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_image',
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_image',
      'bundle' => 'article',
      'settings' => [
        'file_extensions' => 'png',
      ],
    ])->save();

    $png1 = File::create([
      'uri' => 'public://test-image-1.png',
    ]);
    $png1->save();
    $png2 = File::create([
      'uri' => 'public://test-image-2.png',
    ]);
    $png2->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Test',
      'field_image' => [$png1, $png2],
    ]);
    $node->save();
    $this->setUpCurrentUser(['uid' => 1]);

    $styles = [
      'test' => 'TEST',
      '16_9_desktop' => '__16_9_DESKTOP',
      '_1_1_mobile' => '_1_1_MOBILE',
    ];
    foreach ($styles as $name => $label) {
      ImageStyle::create(['name' => $name, 'label' => $label])->save();
    }

    $server = $this
      ->getCoreComposableServerBuilder()
      ->enableValueFields()
      ->enableExtension('image')
      ->enableExtension('entity_query')
      ->enableEntityType('file')
      ->enableEntityType('node', ['field_image'])
      ->createServer();

    $query = <<<GQL
    query test(\$id: ID!, \$style: ImageStyleId!) {
      entityById(id: \$id, entityType: NODE) {
        ... on NodeArticle {
          fieldImage {
            derivative(style: \$style) {
              urlPath
              width
              height
            }
          }
        }
      }
    }
    GQL;

    foreach ($styles as $machineId => $enumValue) {
      $result = $server->executeOperation(OperationParams::create([
        'query' => $query,
        'variables' => [
          'id' => $node->id(),
          'style' => $enumValue,
        ],
      ]));
      $images = $result->data['entityById']['fieldImage'];
      foreach ($images as $image) {
        $url = $image['derivative']['urlPath'];
        $this->assertStringContainsString('styles/' . $machineId, $url);
      }
    }
  }

}
