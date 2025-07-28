<?php

namespace Drupal\Tests\graphql_core_schema\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\graphql_core_schema\Traits\CoreComposableServerBuilder;
use GraphQL\Server\OperationParams;

/**
 * Test that access checks are performed by the default resolver.
 *
 * @group graphql_core_schema
 */
class AccessCheckTest extends CoreComposableKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->enableModules(['graphql_core_schema_test']);
  }

  /**
   * Test access checks on entity references.
   */
  public function testResolveEntityReference(): void {
    Vocabulary::create(['vid' => 'tag'])->save();
    Vocabulary::create(['vid' => 'location'])->save();
    NodeType::create(['type' => 'article'])->save();
    NodeType::create(['type' => 'restricted'])->save();

    $this->createEntityReferenceField(
      'node',
      'article',
      'field_restricted',
      'Tags',
      'taxonomy_term',
      'default',
      ['target_bundles' => ['tag' => 'tag', 'location' => 'location']],
      6
    );

    $this->createEntityReferenceField(
      'node',
      'article',
      'field_unrestricted',
      'Tags',
      'taxonomy_term',
      'default',
      ['target_bundles' => ['tag' => 'tag', 'location' => 'location']],
      6
    );
    $editorUser = $this->createUser(['name' => 'John Editor']);

    $user = $this->setUpCurrentUser();
    $role = $this->createRole(['access content']);
    $user->addRole($role);
    $this->setCurrentUser($user);

    $tag1 = Term::create([
      'vid' => 'tag',
      'name' => 'Tag 1',
    ]);
    $tag1->save();

    $tag2 = Term::create([
      'vid' => 'tag',
      'name' => 'Tag 2',
    ]);
    $tag2->save();

    $location = Term::create([
      'vid' => 'location',
      'name' => 'Location',
    ]);
    $location->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Test',
      'uid' => $editorUser->id(),
      'field_restricted' => [
        [
          'target_id' => $tag1->id(),
        ],
        [
          'target_id' => $location->id(),
        ],
      ],
      'field_unrestricted' => [
        [
          'target_id' => $tag1->id(),
        ],
        [
          'target_id' => $location->id(),
        ],
      ],
    ]);
    $node->save();

    $builder = new CoreComposableServerBuilder();
    $server = $builder
      ->enableEntityType('node', ['field_unrestricted', 'field_restricted', 'uid'])
      ->enableEntityType('taxonomy_term')
      ->enableEntityType('user')
      ->enableExtension('entity_query')
      ->enableBaseEntityField('referencedEntities')
      ->enableBaseEntityField('label')
      ->enableValueFields()
      ->createServer();

    $query = <<<GQL
    query entityById(\$id: ID!) {
      article: entityById(id: \$id, entityType: NODE) {
        ... on NodeArticle {
          referencedEntities {
            label
          }
          fieldRestricted {
            label
          }
          fieldUnrestricted {
            label
          }
          fieldRestrictedRawField {
            first {
              entity {
                label
              }
            }
            list {
              entity {
                label
              }
            }
          }
          fieldUnrestrictedRawField {
            first {
              entity {
                label
              }
            }
            list {
              entity {
                label
              }
            }
          }
          uid {
            label
          }
        }
      }
    }
GQL;
    $params = OperationParams::create([
      'query' => $query,
      'variables' => [
        'id' => $node->id(),
      ],
    ]);
    $result = $server->executeOperation($params);
    $data = $result->data['article'];

    $this->assertCount(7, $data['referencedEntities']);
    foreach ($data['referencedEntities'] as $item) {
      if ($item) {
        $this->assertStringNotContainsString('Location', $item['label'], 'No location taxonomy term is exposed via referencedEntities.');
      }
    }

    $this->assertEmpty($data['fieldRestricted']);
    $this->assertCount(2, $data['fieldUnrestricted']);
    $this->assertEquals('Tag 1', $data['fieldUnrestricted'][0]['label']);
    $this->assertEmpty($data['fieldUnrestricted'][1]);
    $this->assertEmpty($data['fieldRestrictedRawField'], 'Field without access is NULL.');

    $this->assertCount(2, $data['fieldUnrestrictedRawField']['list']);
    $this->assertEquals('Tag 1', $data['fieldUnrestrictedRawField']['first']['entity']['label']);
    $this->assertEquals('Tag 1', $data['fieldUnrestrictedRawField']['list'][0]['entity']['label']);
    $this->assertEmpty($data['fieldUnrestrictedRawField']['list'][1]['entity']);
    $this->assertEmpty($data['uid'], 'Reference to user should be empty because current user does not have permission.');
  }

  /**
   * Test access checks on entity references.
   */
  public function testCurrentUser(): void {
    $user = $this->setUpCurrentUser();
    $role = $this->createRole(['access content']);
    $user->addRole($role);
    $this->setCurrentUser($user);

    $builder = new CoreComposableServerBuilder();
    $server = $builder
      ->enableEntityType('user', ['init', 'name', 'roles', 'mail'])
      ->enableEntityType('user_role')
      ->enableExtension('user')
      ->enableExtension('entity_query')
      ->enableValueFields()
      ->enableBaseEntityField('label')
      ->createServer();

    $query = <<<GQL
    query {
      currentUser {
        label
        init
        name
        mail
        roles {
          label
        }
      }
    }
GQL;
    $params = OperationParams::create([
      'query' => $query,
    ]);
    $result = $server->executeOperation($params);
    $data = $result->data['currentUser'];
    $this->assertEmpty($data['roles'], "Can't see own roles.");
    $this->assertEquals($user->getAccountName(), $data['name'], 'Can see own user name');
    $this->assertEquals($user->getEmail(), $data['mail'], 'Can see own email');
  }

}
