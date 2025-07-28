<?php

namespace Drupal\graphql_core_schema\Plugin\GraphQL\DataProducer\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Returns the referenced entities of an entity.
 *
 * @DataProducer(
 *   id = "entity_referenced_entities",
 *   name = @Translation("Entity Referenced Entities"),
 *   description = @Translation("Returns the entities referenced by the entity."),
 *   produces = @ContextDefinition("entity",
 *     label = @Translation("Entity"),
 *     multiple = TRUE,
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity")
 *     )
 *   }
 * )
 */
class ReferencedEntities extends DataProducerPluginBase {

  /**
   * Resolver.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $fieldContext
   *   The field context.
   *
   * @return EntityInterface[]
   *   The referenced entities.
   */
  public function resolve(EntityInterface $entity, FieldContext $fieldContext) {
    $entities = $entity->referencedEntities();
    $filtered = [];

    foreach ($entities as $entity) {
      $accessResult = $entity->access('view', NULL, TRUE);
      $fieldContext->addCacheableDependency($entity);
      $fieldContext->addCacheableDependency($accessResult);
      $filtered[] = $accessResult->isAllowed() ? $entity : NULL;
    }

    return $filtered;
  }

}
