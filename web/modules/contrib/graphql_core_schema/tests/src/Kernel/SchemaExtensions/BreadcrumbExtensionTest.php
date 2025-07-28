<?php

namespace Drupal\Tests\graphql_core_schema\Kernel\SchemaExtension;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\graphql_core_schema\Kernel\CoreComposableKernelTestBase;
use GraphQL\Server\OperationParams;

/**
 * Tests the breadcrumb extension.
 *
 * @group graphql_core_schema
 */
class BreadcrumbExtensionTest extends CoreComposableKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->enableModules(['path_alias']);
    $this->installEntitySchema('path_alias');
  }

  /**
   * Resolves a simple breadcrumb.
   */
  public function testSimpleBreadcrumb(): void {
    NodeType::create(['type' => 'article'])->save();

    \Drupal::state()->set('router.path_roots', ['user', 'admin', 'node']);

    $user = $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $this->setCurrentUser($user);

    $node1 = Node::create([
      'type' => 'article',
      'title' => 'Test',
    ]);
    $node1->save();

    $server = $this->getCoreComposableServerBuilder()
      ->enableEntityType('node')
      ->enableExtension('routing')
      ->enableExtension('breadcrumb')
      ->enableBaseEntityField('label')
      ->enableValueFields()
      ->createServer();

    $query = <<<GQL
    query breadcrumb(\$path: String!) {
      route(path: \$path) {
        __typename
        ... on InternalUrl {
          breadcrumb {
            title
            url {
              path
            }
          }
        }
      }
    }
    GQL;

    $server->executeOperation(OperationParams::create([
      'query' => $query,
      'variables' => [
        'path' => '/en/admin/config/system',
      ],
    ]));
  }

}
